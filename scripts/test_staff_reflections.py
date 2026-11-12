# HTTP/PDO checks for the staff workflow. Temporary data is deleted in finally.
import base64
from concurrent.futures import ThreadPoolExecutor
import html
import http.cookiejar
import json
import os
from pathlib import Path
import re
import subprocess
import threading
import urllib.error
import urllib.parse
import urllib.request
import uuid

ROOT = Path(__file__).resolve().parents[1]
BASE = os.environ.get('CV_BASE_URL', 'http://localhost/campusvoice/').rstrip('/') + '/'
PHP = os.environ.get('CV_PHP', r'C:\xampp\php\php.exe')
PREFIX = 'cvstafftest-' + uuid.uuid4().hex
PASSWORD = uuid.uuid4().hex
checks = 0

def php_data(value):
    return "json_decode(base64_decode('" + base64.b64encode(json.dumps(value).encode()).decode() + "'),true)"

def database(code):
    result = subprocess.run([PHP], input='<?php require "includes/bootstrap.php"; $pdo=db(); ' + code,
                            encoding='utf-8', capture_output=True, cwd=ROOT)
    if result.returncode:
        raise RuntimeError(result.stderr or result.stdout)
    return json.loads(result.stdout)

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None

def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())

def request(c, path, data=None, method=None):
    payload = None if data is None else urllib.parse.urlencode(data).encode()
    if data is not None and path.startswith('staff-reflection.php?id=') and 'version' not in data:
        _, _, page = request(c, path)
        version = re.search(r'name="version" value="([0-9]+)"', page)
        # Read-only states still have a current version in fixture snapshots.
        if version:
            data = dict(data, version=version.group(1))
        else:
            rid = int(path.split('id=')[1].split('&')[0])
            v = database('$q=$pdo->prepare("SELECT Version FROM Reflections WHERE Id=?");$q->execute(['+str(rid)+']);echo json_encode($q->fetchColumn());')
            data = dict(data, version=v)
        payload = urllib.parse.urlencode(data).encode()
    req = urllib.request.Request(BASE + path, data=payload, method=method)
    try:
        response = c.open(req, timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read().decode('utf-8')
    assert not re.search(r'(Fatal error|Warning:|Parse error|SQLSTATE)', body), body[:500]
    return response.code, response.headers, body

def check(condition, label):
    global checks
    if not condition:
        raise AssertionError(label)
    checks += 1
    print('PASS', label)

def token(body):
    return re.search(r'name="csrf" value="([a-f0-9]+)"', body).group(1)

def login(email):
    c = client()
    _, _, body = request(c, 'login.php')
    status, _, _ = request(c, 'login.php', {'csrf': token(body), 'email': email, 'password': PASSWORD})
    check(status == 303, 'fixture login')
    return c

fixture = None
try:
    fixture = database('$data=' + php_data({'prefix': PREFIX, 'password': PASSWORD}) + r''';
$category=$pdo->query('SELECT Id FROM Categories ORDER BY Id LIMIT 1')->fetchColumn();
if (!$category) { fwrite(STDERR,"Import SQL/categories first.\n"); exit(1); }
$pdo->beginTransaction();$ids=[];$emails=[];$refs=[];$titles=[];
$q=$pdo->prepare('INSERT INTO Users (FullName,Email,PasswordHash,Role) VALUES (?,?,?,?)');
foreach (['Student','Student','Staff','Staff'] as $i=>$role) {
 $email=$data['prefix'].'-'.$i.'@campusvoice.test';
 $q->execute(['Staff flow fixture '.$i,$email,password_hash($data['password'],PASSWORD_DEFAULT),$role]);
 $ids[]=(int)$pdo->lastInsertId();$emails[]=$email;
}
$q=$pdo->prepare('INSERT INTO Reflections (Title,Content,Status,CreatedAt,UserId,CategoryId) VALUES (?,?,?,?,?,?)');
foreach (['Pending','Pending','InProgress','InProgress','NeedsInfo','Closed','Resolved'] as $i=>$status) {
 $title=$data['prefix'].'-'.$i.' <script>unsafe</script>';
 $q->execute([$title,"Nội dung kiểm thử\nDòng hai <script>unsafe</script>",$status,'2026-11-10 10:00:00',$ids[$i%2],$category]);
 $refs[]=(int)$pdo->lastInsertId();$titles[]=$title;
}
$pdo->commit(); echo json_encode(['ids'=>$ids,'emails'=>$emails,'refs'=>$refs,'titles'=>$titles]);''')
    def sql(code):
        return database('$f=' + php_data(fixture) + '; ' + code)
    def snapshot(index):
        return sql('$i=' + str(index) + r''';$q=$pdo->prepare('SELECT Status,(SELECT COUNT(*) FROM Responses WHERE ReflectionId=r.Id) AS Total FROM Reflections r WHERE Id=?');$q->execute([$f['refs'][$i]]);echo json_encode($q->fetch());''')
    def unchanged(index, state, total=0):
        value = snapshot(index)
        return value['Status'] == state and int(value['Total']) == total
    def url(index):
        return 'staff-reflection.php?id=' + str(fixture['refs'][index])
    anonymous = client()
    for path in ['staff.php', url(0)]:
        for data in [None, {'action': 'accept', 'csrf': 'bad'}]:
            status, headers, _ = request(anonymous, path, data)
            check(status == 303 and headers.get('Location') == 'login.php', 'anonymous GET/POST blocked')
    owner, other, staff_a, staff_b = [login(email) for email in fixture['emails']]
    for path in ['staff.php', url(0)]:
        for data in [None, {'action': 'accept'}, {'action': 'resolve', 'reply': 'forged'}]:
            _, _, body = request(owner, 'student.php')
            if data is not None:
                data = dict(data, csrf=token(body))
            status, headers, _ = request(owner, path, data)
            check(status == 303 and headers.get('Location') == 'student.php' and unchanged(0, 'Pending'), 'student GET/POST cannot mutate staff flow')
    status, _, body = request(staff_a, 'staff.php')
    check(status == 200 and all(html.escape(t) in body for t in fixture['titles']), 'staff scope includes both student owners')
    check(body.index(html.escape(fixture['titles'][-1])) < body.index(html.escape(fixture['titles'][0])), 'inbox newest ID first at equal dates')
    check('Staff flow fixture 0' in body and 'Staff flow fixture 1' in body and '<script>unsafe' not in body, 'sender names and escaped titles')
    states = ['Pending','Pending','InProgress','InProgress','NeedsInfo','Closed','Resolved']
    for state in sorted(set(states)):
        status, _, body = request(staff_a, 'staff.php?status=' + state)
        check(status == 200 and all((html.escape(t) in body) == (s == state) for t,s in zip(fixture['titles'], states)), 'status filter: ' + state)
    for query in ['status=Invalid', 'status[]=Pending', 'status=Pending%27%20OR%201=1']:
        status, _, body = request(staff_a, 'staff.php?' + query)
        check(status == 422 and 'Trạng thái lọc không hợp lệ' in body, 'invalid filter rejected')
    for query in ['', '-1', 'abc', '4294967295', '4294967296', '1%20OR%201=1']:
        status, _, _ = request(staff_a, 'staff-reflection.php?id=' + query)
        check(status == 404, 'invalid/missing detail ID')
    status, _, _ = request(staff_a, 'staff-reflection.php?id[]=1')
    check(status == 404, 'array detail ID rejected')
    status, _, body = request(staff_a, url(0))
    csrf = token(body)
    check(status == 200 and 'Nội dung kiểm thử\nDòng hai &lt;script&gt;' in body and 'name="action" value="accept"' in body, 'pending detail and accept form')
    status, _, _ = request(staff_a, url(0) + '&action=accept')
    check(status == 200 and unchanged(0, 'Pending'), 'GET action never changes state')
    status, _, _ = request(staff_a, url(0), {}, method='PUT')
    check(status == 405 and unchanged(0, 'Pending'), 'PUT rejected')
    for action in ['accept', 'resolve']:
        status, _, _ = request(staff_a, url(0), {'action': action, 'reply': 'not saved', 'csrf': 'wrong'})
        check(status == 403 and unchanged(0, 'Pending'), 'invalid CSRF blocked: ' + action)
    status, _, _ = request(staff_a, url(0), {'action': 'resolve', 'reply': 'too soon', 'csrf': csrf})
    check(status == 409 and unchanged(0, 'Pending'), 'cannot resolve Pending')
    status, _, _ = request(staff_a, url(0), {'action': 'reopen', 'csrf': csrf})
    check(status == 422 and unchanged(0, 'Pending'), 'unsupported action rejected')
    status, headers, _ = request(staff_a, url(0), {'action': 'accept', 'csrf': csrf, 'Status': 'Resolved', 'UserId': fixture['ids'][0]})
    check(status == 303 and headers.get('Location') == url(0) and unchanged(0, 'InProgress'), 'Pending to InProgress; ignores posted role/status')
    _, _, body = request(staff_a, url(0))
    check('Tiếp nhận phản ánh thành công' in body and 'name="reply"' in body, 'accept flash and reply form')
    _, _, body = request(staff_a, url(0))
    check('Tiếp nhận phản ánh thành công' not in body, 'flash consumed once')
    status, _, _ = request(staff_a, url(0), {'action': 'accept', 'csrf': csrf})
    check(status == 409 and unchanged(0, 'InProgress'), 'duplicate acceptance blocked')
    for reply in ['', ' \n\t ', '\u00a0\u2003', 'ấ' * 10001]:
        status, _, body = request(staff_a, url(0), {'action': 'resolve', 'reply': reply, 'csrf': csrf})
        check(status == 422 and unchanged(0, 'InProgress'), 'invalid reply blocked')
        if len(reply) > 100:
            check(reply in body, 'invalid reply retained')
    reply = 'Đã kiểm tra và xử lý.\n<script>alert(1)</script>'
    status, headers, _ = request(staff_a, url(0), {'action': 'resolve', 'reply': reply, 'csrf': csrf, 'UserId': fixture['ids'][0], 'Status': 'Closed'})
    check(status == 303 and headers.get('Location') == url(0) and unchanged(0, 'Resolved', 1), 'reply and Resolved committed together')
    response = sql("$q=$pdo->prepare('SELECT Content,UserId FROM Responses WHERE ReflectionId=?');$q->execute([$f['refs'][0]]);echo json_encode($q->fetch());")
    check(response['Content'] == reply and int(response['UserId']) == fixture['ids'][2], 'author is session staff; exact text saved')
    _, _, body = request(staff_a, url(0))
    check('Đã xử lý' in body and html.escape(reply) in body and 'value="close"' in body, 'resolved detail escapes reply and offers closure')
    for c in [staff_a, staff_b]:
        _, _, body = request(c, url(0))
        status, _, _ = request(c, url(0), {'action': 'resolve', 'reply': 'duplicate', 'csrf': token(body)})
        check(status == 409 and unchanged(0, 'Resolved', 1), 'resubmission cannot duplicate response')
    _, _, body = request(owner, 'reflection.php?id=' + str(fixture['refs'][0]))
    check('Đã xử lý' in body and html.escape(reply) in body, 'student owner sees staff reply and new status')
    status, _, _ = request(other, 'reflection.php?id=' + str(fixture['refs'][0]))
    check(status == 404, 'other student cannot see resolved reflection')
    _, _, body = request(owner, 'student.php')
    check('Đã xử lý' in body, 'student home uses consistent Resolved label')
    for index in [4,5]:
        _, _, body = request(staff_a, url(index))
        check('name="action"' not in body, 'read-only terminal/unsupported state form')
        for action in ['accept','resolve']:
            status, _, _ = request(staff_a, url(index), {'action': action, 'reply': 'cannot update', 'csrf': token(body)})
            check(status == 409 and unchanged(index, states[index]), 'server rejects unsupported state transitions')
    # Actual parallel HTTP requests with different sessions hit independent DB transactions.
    for index, action, target, total in [(1,'accept','InProgress',0),(2,'resolve','Resolved',1)]:
        tokens = [token(request(c, url(index))[2]) for c in [staff_a,staff_b]]
        barrier = threading.Barrier(2)
        def send(i):
            barrier.wait()
            return request([staff_a,staff_b][i], url(index), {'action':action,'reply':'Concurrent reply '+str(i),'csrf':tokens[i]})[0]
        with ThreadPoolExecutor(max_workers=2) as pool:
            results = list(pool.map(send, [0,1]))
        check(sorted(results) == [303,409] and unchanged(index, target, total), 'parallel '+action+' exactly once')
    # Inject exceptions AFTER real INSERT / UPDATE on the real MySQL connection.
    # No trigger or schema change is required; workflow must roll back both writes.
    for prefix in ['INSERT INTO Responses','UPDATE Reflections']:
        result = sql('require "includes/staff-workflow.php"; $prefix=' + php_data(prefix) + r''';
class FailureStatement extends PDOStatement {
 public static $prefix;
 protected function __construct() {}
 public function execute(?array $params=null): bool {
  $ok=parent::execute($params);
  if (strpos($this->queryString,self::$prefix)===0) {throw new PDOException('Injected failure after real write');}
  return $ok;
 }
}
FailureStatement::$prefix=$prefix;
$pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS,[FailureStatement::class]);
$failed=false;
try {staff_process_reflection($pdo,$f['refs'][3],$f['ids'][2],'resolve','Must roll back',0);}
catch (PDOException $e) {$failed=true;}
echo json_encode(['failed'=>$failed,'inTransaction'=>$pdo->inTransaction()]);''')
        check(result['failed'] and not result['inTransaction'] and unchanged(3, 'InProgress'), 'atomic rollback after '+prefix)
    # The service independently rejects student and inactive IDs, even without HTTP routing.
    result = sql(r'''require 'includes/staff-workflow.php';$denied=false;try {staff_process_reflection($pdo,$f['refs'][3],$f['ids'][0],'resolve','forged',0);}catch(StaffReflectionNotFound $e){$denied=true;}echo json_encode($denied);''')
    check(result and unchanged(3, 'InProgress'), 'service scope denies Student actor')
    sql("$q=$pdo->prepare('UPDATE Users SET IsActive=0 WHERE Id=?');$q->execute([$f['ids'][3]]);echo json_encode(true);")
    status, headers, _ = request(staff_b, url(3), {'action':'resolve','reply':'blocked','csrf':tokens[1]})
    check(status == 303 and headers.get('Location') == 'login.php' and unchanged(3, 'InProgress'), 'inactive staff session denied')
    result = sql(r'''require 'includes/staff-workflow.php';$denied=false;try {staff_process_reflection($pdo,$f['refs'][3],$f['ids'][3],'resolve','forged',0);}catch(StaffReflectionNotFound $e){$denied=true;}echo json_encode($denied);''')
    check(result and unchanged(3, 'InProgress'), 'service scope denies inactive Staff actor')
    # A completed filter can have no results; preserve the page's empty-result guidance.
    sql("$q=$pdo->prepare('UPDATE Reflections SET Status=? WHERE UserId IN (?,?)');$q->execute(['Pending',$f['ids'][0],$f['ids'][1]]);echo json_encode(true);")
    _, _, body = request(staff_a, 'staff.php?status=Closed')
    # Existing non-test Closed records may legitimately keep this filter nonempty.
    if 'class="reflection-table"' not in body:
        check('Không có phản ánh phù hợp' in body, 'empty filtered inbox guidance')
    print('ALL', checks, 'STAFF CHECKS PASSED')
finally:
    if fixture:
        database('$f=' + php_data(fixture) + r''';$pdo->beginTransaction();
foreach ($f['ids'] as $id) { $q=$pdo->prepare('DELETE FROM ReflectionActivities WHERE UserId=? OR ReflectionId IN (SELECT Id FROM Reflections WHERE UserId=?)'); $q->execute([$id,$id]); $q=$pdo->prepare('DELETE FROM Notifications WHERE UserId=? OR ReflectionId IN (SELECT Id FROM Reflections WHERE UserId=?)'); $q->execute([$id,$id]); }
foreach($f['ids'] as $id){$q=$pdo->prepare('DELETE FROM Responses WHERE UserId=? OR ReflectionId IN (SELECT Id FROM Reflections WHERE UserId=?)');$q->execute([$id,$id]);}
foreach($f['ids'] as $id){$q=$pdo->prepare('DELETE FROM Reflections WHERE UserId=?');$q->execute([$id]);$q=$pdo->prepare('DELETE FROM Users WHERE Id=? AND Email LIKE ?');$q->execute([$id,'cvstafftest-%@campusvoice.test']);}
$pdo->commit();echo json_encode(true);''')
        print('CLEANUP temporary staff/student accounts, responses and reflections')
