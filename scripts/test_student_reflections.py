# Integration checks: Python standard library + XAMPP PHP, Apache and MySQL.
import base64
import html
import http.cookiejar
import json
import os
from pathlib import Path
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request
import uuid

ROOT = Path(__file__).resolve().parents[1]
BASE = os.environ.get('CV_BASE_URL', 'http://localhost/campusvoice/').rstrip('/') + '/'
PHP = os.environ.get('CV_PHP', r'C:\xampp\php\php.exe')
PASSWORD = 'CV-test-' + uuid.uuid4().hex
PREFIX = 'cvtest-' + uuid.uuid4().hex
checks = 0

def database(code):
    source = '<?php require "includes/bootstrap.php"; $pdo=db(); ' + code
    run = subprocess.run([PHP], input=source, encoding='utf-8', capture_output=True, cwd=ROOT)
    if run.returncode:
        raise RuntimeError(run.stderr or run.stdout)
    return json.loads(run.stdout)

def php_data(data):
    return "json_decode(base64_decode('" + base64.b64encode(json.dumps(data).encode()).decode() + "'), true)"

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None

def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())

def request(c, path, data=None):
    payload = None if data is None else urllib.parse.urlencode(data).encode()
    try:
        response = c.open(BASE + path, payload, timeout=10)
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

def csrf(body):
    return re.search(r'name="csrf" value="([a-f0-9]+)"', body).group(1)

def login(email):
    c = client()
    _, _, body = request(c, 'login.php')
    status, _, _ = request(c, 'login.php', {'email': email, 'password': PASSWORD, 'csrf': csrf(body)})
    check(status == 303, 'test account login')
    return c

fixture = None
try:
    fixture = database('$data=' + php_data({'prefix': PREFIX, 'password': PASSWORD}) + r''';
$category=$pdo->query('SELECT Id FROM Categories ORDER BY Id LIMIT 1')->fetchColumn();
if (!$category) { fwrite(STDERR, "No categories: import SQL first.\n"); exit(1); }
$pdo->beginTransaction(); $ids=[]; $emails=[];
$q=$pdo->prepare('INSERT INTO Users (FullName,Email,PasswordHash,Role) VALUES (?,?,?,?)');
foreach (['Student','Student','Staff'] as $i=>$role) {
    $email=$data['prefix'].'-'.$i.'@campusvoice.test';
    $q->execute(['Integration test '.$i,$email,password_hash($data['password'],PASSWORD_DEFAULT),$role]);
    $ids[]=(int)$pdo->lastInsertId(); $emails[]=$email;
}
$pdo->commit(); echo json_encode(['ids'=>$ids,'emails'=>$emails,'category'=>(int)$category]);''')
    ids = fixture['ids']
    def sql(code):
        return database('$f=' + php_data(fixture) + '; ' + code)
    def count():
        return sql("$q=$pdo->prepare('SELECT COUNT(*) FROM Reflections WHERE UserId=?'); $q->execute([$f['ids'][0]]); echo json_encode((int)$q->fetchColumn());")
    anonymous = client()
    paths = ['reflection-create.php', 'reflections.php', 'reflection.php?id=1']
    for path in paths:
        status, headers, _ = request(anonymous, path)
        check(status == 303 and headers.get('Location') == 'login.php', 'anonymous blocked: ' + path)
    staff = login(fixture['emails'][2])
    for path in paths:
        status, headers, _ = request(staff, path)
        check(status == 303 and headers.get('Location') == 'staff.php', 'staff blocked: ' + path)
    owner, other = login(fixture['emails'][0]), login(fixture['emails'][1])
    status, _, body = request(owner, 'student.php')
    check(status == 200 and 'href="reflections.php"' in body and 'href="reflection-create.php"' in body, 'home links')
    status, _, body = request(owner, 'reflections.php')
    check(status == 200 and 'Bạn chưa gửi phản ánh nào' in body, 'empty list guidance')
    status, _, body = request(owner, 'reflection-create.php')
    token = csrf(body)
    check(status == 200 and f'value="{fixture["category"]}"' in body, 'categories from database')
    data = {'title': 'Kiểm tra <script>alert(1)</script>', 'content': 'Nội dung dòng 1\nDòng 2 <img src=x onerror=alert(1)>', 'category_id': fixture['category'], 'csrf': token}
    for patch, code, message in [
        ({'title': '   '}, 422, 'Tiêu đề phải'),
        ({'title': 'ấ' * 201}, 422, 'Tiêu đề phải'),
        ({'content': ' \n '}, 422, 'Nội dung phải'),
        ({'content': 'ấ' * 10001}, 422, 'Nội dung phải'),
        ({'category_id': '4294967295'}, 422, 'danh mục hợp lệ'),
        ({'category_id': '1 OR 1=1'}, 422, 'danh mục hợp lệ'),
        ({'csrf': 'bad'}, 403, 'Phiên làm việc không hợp lệ')]:
        status, _, body = request(owner, 'reflection-create.php', dict(data, **patch))
        check(status == code and message in body and count() == 0, 'invalid input rejected: ' + next(iter(patch)))
        check(html.escape(str(patch.get('content', data['content'])).strip(), quote=True) in body, 'content retained')
    status, headers, _ = request(owner, 'reflection-create.php', dict(data, UserId=ids[1], Status='Resolved'))
    check(status == 303 and headers.get('Location') == 'reflections.php', 'POST redirects to list')
    record = sql("$q=$pdo->prepare('SELECT Id,UserId,Status,Title,Content FROM Reflections WHERE UserId=? ORDER BY Id DESC LIMIT 1'); $q->execute([$f['ids'][0]]); echo json_encode($q->fetch());")
    rid = int(record['Id'])
    check(int(record['UserId']) == ids[0] and record['Status'] == 'Pending' and record['Title'] == data['title'] and record['Content'] == data['content'], 'session owner, Pending and UTF-8 saved')
    status, _, body = request(owner, 'reflections.php')
    check(status == 200 and 'Gửi phản ánh thành công' in body and html.escape(data['title']) in body and '<script>' not in body, 'success flash and safe title')
    _, _, body = request(owner, 'reflections.php')
    check('Gửi phản ánh thành công' not in body and count() == 1, 'refresh without duplicate or flash')
    status, _, body = request(owner, f'reflection.php?id={rid}')
    check(status == 200 and 'Chưa có câu trả lời' in body and html.escape(data['content']) in body and '<img src=x' not in body, 'detail plain text and empty responses')
    for path in [f'reflection.php?id={rid}', 'reflections.php']:
        status, _, body = request(other, path)
        check((status == 404 if 'id=' in path else status == 200) and html.escape(data['title']) not in body, 'other student isolated: ' + path)
    for value in ['', '-1', 'abc', '1%20OR%201=1', '4294967295', '4294967296', '1.5', '%3Cscript%3E']:
        status, _, _ = request(owner, 'reflection.php?id=' + value)
        check(status == 404, 'invalid or missing ID: ' + value)
    status, _, _ = request(owner, 'reflection.php?id[]=1')
    check(status == 404, 'array ID rejected')
    fixture['reflection'] = rid
    sql(r'''$q=$pdo->prepare('INSERT INTO Responses (Content,ReflectionId,UserId) VALUES (?,?,?)');
$q->execute(['Reply one <script>unsafe</script>',$f['reflection'],$f['ids'][2]]);
$q->execute(['Student reply must be hidden',$f['reflection'],$f['ids'][1]]);
$q->execute(['Reply two',$f['reflection'],$f['ids'][2]]); echo json_encode(true);''')
    _, _, body = request(owner, f'reflection.php?id={rid}')
    check('Reply one &lt;script&gt;unsafe&lt;/script&gt;' in body and 'Student reply must be hidden' not in body and body.index('Reply one') < body.index('Reply two'), 'staff responses escaped, filtered and ordered')
    for state, label in [('Pending', 'Chờ tiếp nhận'), ('InProgress', 'Đang xử lý'), ('NeedsInfo', 'Cần bổ sung thông tin'), ('Resolved', 'Đã xử lý'), ('Closed', 'Đã đóng')]:
        sql('$state=' + php_data(state) + "; $q=$pdo->prepare('UPDATE Reflections SET Status=? WHERE Id=? AND UserId=?'); $q->execute([$state,$f['reflection'],$f['ids'][0]]); echo json_encode(true);")
        _, _, body = request(owner, f'reflection.php?id={rid}')
        check(label in body, 'status label: ' + state)
    status, _, _ = request(owner, 'reflection-create.php', dict(data, title='ấ' * 200))
    check(status == 303 and count() == 2, '200-character UTF-8 title accepted')
    sql("$q=$pdo->prepare(\"UPDATE Reflections SET CreatedAt='2026-01-01 10:00:00' WHERE UserId=?\"); $q->execute([$f['ids'][0]]); echo json_encode(true);")
    _, _, body = request(owner, 'reflections.php')
    check(body.index('ấ' * 200) < body.index(html.escape(data['title'])), 'Id descending when timestamps match')
    sql("$q=$pdo->prepare('UPDATE Users SET IsActive=0 WHERE Id=?'); $q->execute([$f['ids'][0]]); echo json_encode(true);")
    status, headers, _ = request(owner, 'reflections.php')
    check(status == 303 and headers.get('Location') == 'login.php', 'inactive session denied')
    print(f'ALL {checks} CHECKS PASSED')
finally:
    if fixture:
        database('$f=' + php_data(fixture) + r''';
$pdo->beginTransaction();
foreach ($f['ids'] as $id) { $q=$pdo->prepare('DELETE FROM ReflectionActivities WHERE UserId=? OR ReflectionId IN (SELECT Id FROM Reflections WHERE UserId=?)'); $q->execute([$id,$id]); $q=$pdo->prepare('DELETE FROM Notifications WHERE UserId=? OR ReflectionId IN (SELECT Id FROM Reflections WHERE UserId=?)'); $q->execute([$id,$id]); }
foreach ($f['ids'] as $id) {
    $q=$pdo->prepare('DELETE FROM Responses WHERE UserId=? OR ReflectionId IN (SELECT Id FROM Reflections WHERE UserId=?)'); $q->execute([$id,$id]);
}
foreach ($f['ids'] as $id) {
    $q=$pdo->prepare('DELETE FROM Reflections WHERE UserId=?'); $q->execute([$id]);
    $q=$pdo->prepare('DELETE FROM Users WHERE Id=? AND Email LIKE ?'); $q->execute([$id,'cvtest-%@campusvoice.test']);
}
$pdo->commit(); echo json_encode(true);''')
        print('CLEANUP: removed temporary accounts, reflections and responses.')
