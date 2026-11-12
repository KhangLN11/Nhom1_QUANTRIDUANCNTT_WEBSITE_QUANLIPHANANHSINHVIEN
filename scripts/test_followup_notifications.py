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
PREFIX = 'cvfollowuptest-' + uuid.uuid4().hex
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
    fixture = database('$data=' + php_data({'prefix':PREFIX,'password':PASSWORD}) + r''';
$category=$pdo->query('SELECT Id FROM Categories ORDER BY Id LIMIT 1')->fetchColumn();
if (!$category) {fwrite(STDERR,'Import SQL first.');exit(1);}
$pdo->beginTransaction();$ids=[];$emails=[];$refs=[];
$q=$pdo->prepare('INSERT INTO Users (FullName,Email,PasswordHash,Role,IsActive) VALUES (?,?,?,?,?)');
foreach(['Student','Student','Staff','Staff','Staff'] as $i=>$role){
 $email=$data['prefix'].'-'.$i.'@campusvoice.test';
 $q->execute(['Followup fixture '.$i,$email,password_hash($data['password'],PASSWORD_DEFAULT),$role,$i===4?0:1]);
 $ids[]=(int)$pdo->lastInsertId();$emails[]=$email;
}
$q=$pdo->prepare('INSERT INTO Reflections (Title,Content,Status,UserId,CategoryId) VALUES (?,?,?,?,?)');
foreach(['Pending','Pending','InProgress','InProgress','NeedsInfo','Resolved'] as $i=>$status){
 $q->execute([$data['prefix'].'-'.$i,'Original content must stay unchanged',$status,$ids[$i===1?1:0],$category]);$refs[]=(int)$pdo->lastInsertId();
}
$pdo->commit();echo json_encode(['ids'=>$ids,'emails'=>$emails,'refs'=>$refs]);''')
    def sql(code):
        return database('$f=' + php_data(fixture) + '; ' + code)
    def snapshot(i):
        value=sql('$i='+str(i)+r''';$q=$pdo->prepare('SELECT Status,Version,Content,(SELECT COUNT(*) FROM Responses s WHERE s.ReflectionId=r.Id) AS Replies,(SELECT COUNT(*) FROM ReflectionActivities a WHERE a.ReflectionId=r.Id) AS Events,(SELECT COUNT(*) FROM Notifications n WHERE n.ReflectionId=r.Id) AS Notices FROM Reflections r WHERE Id=?');$q->execute([$f['refs'][$i]]);echo json_encode($q->fetch());''')
        for key in ['Version','Replies','Events','Notices']: value[key]=int(value[key])
        return value
    def surl(i): return 'staff-reflection.php?id='+str(fixture['refs'][i])
    def uurl(i): return 'reflection.php?id='+str(fixture['refs'][i])
    def csrf(c,path): return token(request(c,path)[2])
    owner,other,staff_a,staff_b=[login(email) for email in fixture['emails'][:4]]
    def staff_post(i,action,text='',actor=None,version=None):
        actor=actor or staff_a
        return request(actor,surl(i),{'action':action,'reply':text,'version':snapshot(i)['Version'] if version is None else version,'csrf':csrf(actor,surl(i))})
    def supplement(i,text,actor=None,version=None):
        actor=actor or owner
        return request(actor,uurl(i),{'action':'supplement','content':text,'version':snapshot(i)['Version'] if version is None else version,'csrf':csrf(actor,uurl(i))})
    def notices(i):
        return sql('$i='+str(i)+";$q=$pdo->prepare('SELECT Id,UserId,Type,IsRead,ReadAt FROM Notifications WHERE ReflectionId=? ORDER BY Id');$q->execute([$f['refs'][$i]]);echo json_encode($q->fetchAll());")
    def unchanged(i,before): return snapshot(i)==before
    def row_notice(nid):
        return database('$q=$pdo->prepare(\'SELECT IsRead,ReadAt FROM Notifications WHERE Id=?\');$q->execute(['+str(nid)+']);echo json_encode($q->fetch());')
    status,_,body=request(client(),'notifications.php')
    check(status==303,'anonymous notification inbox denied')
    for c in [owner,staff_a]:
        _,_,body=request(c,'notifications.php')
        check('Bạn chưa có thông báo' in body,'empty own inbox')
    before=snapshot(0)
    status,_,_=supplement(0,'not allowed yet')
    check(status==409 and unchanged(0,before),'supplement only NeedsInfo')
    for action in ['request_info','close']:
        status,_,_=staff_post(0,action,'wrong state')
        check(status==409 and unchanged(0,before),'reject state: '+action)
    status,_,_=staff_post(0,'accept')
    check(status==303 and snapshot(0)['Version']==1,'accept increments version')
    for value in ['', ' \n ', '\u00a0\u2003', 'ấ'*10001]:
        before=snapshot(0);status,_,body=staff_post(0,'request_info',value)
        check(status==422 and unchanged(0,before),'request reason validation')
    before=snapshot(0)
    status,_,_=request(staff_a,surl(0),{'action':'request_info','reply':'reason','version':1,'csrf':'wrong'})
    check(status==403 and unchanged(0,before),'request CSRF denied')
    for bad in ['', '-1','abc']:
        status,_,_=request(staff_a,surl(0),{'action':'request_info','reply':'reason','version':bad,'csrf':csrf(staff_a,surl(0))})
        check(status==422 and unchanged(0,before),'invalid version rejected')
    reason1='REQ-ONE <script>alert(1)</script>'
    status,headers,_=staff_post(0,'request_info',reason1)
    state=snapshot(0)
    check(status==303 and state['Status']=='NeedsInfo' and state['Events']==1 and state['Notices']==1 and state['Version']==2,'request event + state + notification committed')
    check(state['Content']=='Original content must stay unchanged','original preserved on request')
    status,_,_=staff_post(0,'request_info',reason1,version=1)
    check(status==409 and unchanged(0,state),'duplicate request creates no event/notice')
    for c,path in [(owner,uurl(0)),(staff_a,surl(0))]:
        _,_,body=request(c,path)
        check(html.escape(reason1) in body and '<script>alert' not in body,'request visible and escaped to both roles')
    status,_,_=supplement(0,'forged by another student',actor=other)
    check(status==404 and unchanged(0,state),'other student cannot supplement')
    status,headers,_=request(staff_a,uurl(0),{'action':'supplement','content':'forged','version':2,'csrf':csrf(staff_a,surl(0))})
    check(status==303 and headers.get('Location')=='staff.php' and unchanged(0,state),'staff cannot submit student supplement')
    for content in ['', ' \t\n ', 'ấ'*10001]:
        status,_,_=supplement(0,content)
        check(status==422 and unchanged(0,state),'supplement content validation')
    status,_,_=request(owner,uurl(0),{'action':'supplement','content':'not saved','version':2,'csrf':'wrong'})
    check(status==403 and unchanged(0,state),'supplement CSRF denied')
    nid=int(notices(0)[0]['Id'])
    _,_,body=request(owner,'notifications.php')
    check(html.escape(reason1) in body and 'notification-count">1<' in body,'own unread notice and badge')
    _,_,body=request(other,'notifications.php')
    check(html.escape(reason1) not in body,'other inbox isolated')
    for c,code,post_token in [(other,404,csrf(other,'notifications.php')),(owner,403,'bad')]:
        status,_,_=request(c,'notifications.php',{'notification_id':nid,'csrf':post_token})
        check(status==code and not int(row_notice(nid)['IsRead']),'read ownership and CSRF enforced')
    request(owner,'notifications.php?notification_id='+str(nid))
    check(not int(row_notice(nid)['IsRead']),'GET never marks read')
    status,headers,_=request(owner,'notifications.php',{'notification_id':nid,'csrf':csrf(owner,'notifications.php')})
    read=row_notice(nid)
    check(status==303 and headers.get('Location')==uurl(0) and int(read['IsRead'])==1 and read['ReadAt'],'open own notice marks read and redirects')
    request(owner,'notifications.php',{'notification_id':nid,'csrf':csrf(owner,'notifications.php')})
    check(row_notice(nid)==read,'repeat open keeps original ReadAt')
    staff_ids=sql("echo json_encode(array_map('intval',$pdo->query(\"SELECT Id FROM Users WHERE Role='Staff' AND IsActive=1 ORDER BY Id\")->fetchAll(PDO::FETCH_COLUMN)));")
    supplement1='SUPPLEMENT-ONE\nPhoto description <b>plain text</b>'
    status,_,_=supplement(0,supplement1)
    state=snapshot(0)
    recipients=sorted(int(n['UserId']) for n in notices(0) if n['Type']=='Supplement')
    check(status==303 and state['Status']=='Pending' and state['Version']==3 and state['Events']==2 and recipients==staff_ids,'supplement returns Pending; notifies exactly active staff scope')
    check(state['Content']=='Original content must stay unchanged' and state['Replies']==0,'supplement separate from original and Responses')
    status,_,_=supplement(0,supplement1,version=2)
    check(status==409 and unchanged(0,state),'duplicate supplement has no duplicate history or notifications')
    staff_notice=next(n for n in notices(0) if int(n['UserId'])==fixture['ids'][2])
    status,headers,_=request(staff_a,'notifications.php',{'notification_id':staff_notice['Id'],'csrf':csrf(staff_a,'notifications.php')})
    check(status==303 and headers.get('Location')==surl(0),'staff notice opens staff detail')
    other_staff_notice=next(n for n in notices(0) if int(n['UserId'])==fixture['ids'][3])
    check(not int(row_notice(other_staff_notice['Id'])['IsRead']),'one staff reading does not mark others read')
    staff_post(0,'accept')
    before=snapshot(0)
    status,_,_=staff_post(0,'request_info','stale first cycle',version=1)
    check(status==409 and unchanged(0,before),'old request rejected after return to same InProgress state')
    reason2='REQ-TWO second round'
    staff_post(0,'request_info',reason2)
    before=snapshot(0)
    status,_,_=supplement(0,'stale first supplement',version=2)
    check(status==409 and unchanged(0,before),'old supplement rejected in next NeedsInfo round')
    supplement2='SUPPLEMENT-TWO second round'
    supplement(0,supplement2)
    staff_post(0,'accept')
    result_text='RESULT-FINAL handled <script>unsafe</script>'
    status,_,_=staff_post(0,'resolve',result_text)
    check(status==303 and snapshot(0)['Status']=='Resolved' and snapshot(0)['Replies']==1,'resolve persists response and history')
    check(len([n for n in notices(0) if n['Type']=='Resolved' and int(n['UserId'])==fixture['ids'][0]])==1,'student receives exactly one result notice')
    before=snapshot(0)
    for value in ['', '  ', 'ấ'*10001]:
        status,_,_=staff_post(0,'close',value)
        check(status==422 and unchanged(0,before),'close requires valid reason')
    status,_,_=request(staff_a,surl(0),{'action':'close','reply':'reason','version':before['Version'],'csrf':'bad'})
    check(status==403 and unchanged(0,before),'close CSRF denied')
    close_reason='CLOSE-REASON completed and confirmed'
    close_version=before['Version']
    status,_,_=staff_post(0,'close',close_reason)
    state=snapshot(0)
    check(status==303 and state['Status']=='Closed' and state['Events']==6 and state['Replies']==1,'close adds reason and preserves previous content')
    check(len([n for n in notices(0) if n['Type']=='Closed'])==1,'close notification exactly once')
    status,_,_=staff_post(0,'close',close_reason,version=close_version)
    check(status==409 and unchanged(0,state),'duplicate close has no duplicate notice')
    for c,path in [(owner,uurl(0)),(staff_a,surl(0))]:
        status,_,body=request(c,path)
        pieces=[html.escape(x) for x in [reason1,supplement1,reason2,supplement2,result_text,close_reason]]
        check(status==200 and all(p in body for p in pieces) and [body.index(p) for p in pieces]==sorted(body.index(p) for p in pieces),'all rounds and close reason displayed in chronological order')
        check(body.count(html.escape(result_text))==1,'final Response and activity rendered only once')
    status,_,_=request(other,uurl(0))
    check(status==404,'closed record remains owner-only for students')
    before=snapshot(0);status,_,_=supplement(0,'closed update')
    check(status==409 and unchanged(0,before),'closed record immutable')
    # Ownership alone on a notice must not bypass reflection access.
    foreign=sql("$q=$pdo->prepare('INSERT INTO Notifications (Title,Content,Type,UserId,ReflectionId) VALUES (?,?,?,?,?)');$q->execute(['Test foreign access','Private','Test',$f['ids'][0],$f['refs'][1]]);echo json_encode((int)$pdo->lastInsertId());")
    status,_,_=request(owner,'notifications.php',{'notification_id':foreign,'csrf':csrf(owner,'notifications.php')})
    check(status==404 and not int(row_notice(foreign)['IsRead']),'notice cannot bypass reflection owner check')
    # Concurrent multi-record operations: one winner, one stale request.
    for student_action in [False,True]:
        if student_action:
            clients=[owner,login(fixture['emails'][0])];version=snapshot(2)['Version'];path=uurl(2)
        else:
            clients=[staff_a,staff_b];version=0;path=surl(2)
        tokens=[csrf(c,path) for c in clients];barrier=threading.Barrier(2)
        def send(i):
            data={'csrf':tokens[i],'version':version,'action':'supplement' if student_action else 'request_info'}
            data['content' if student_action else 'reply']='Concurrent content '+str(i)
            barrier.wait();return request(clients[i],path,data)[0]
        with ThreadPoolExecutor(max_workers=2) as pool: codes=list(pool.map(send,[0,1]))
        expected='Pending' if student_action else 'NeedsInfo'
        check(sorted(codes)==[303,409] and snapshot(2)['Status']==expected and snapshot(2)['Events']==(2 if student_action else 1),'parallel '+('supplement' if student_action else 'request_info')+' exactly once')
    # Real MySQL writes followed by injected exceptions; no trigger/schema modifications.
    for action,index,actor in [('request_info',3,2),('resolve',3,2),('supplement',4,0),('close',5,2)]:
        prefixes=['INSERT INTO ReflectionActivities','UPDATE Reflections','INSERT INTO Notifications']
        if action=='resolve': prefixes.insert(0,'INSERT INTO Responses')
        for prefix in prefixes:
            before=snapshot(index)
            call=("student_supplement_reflection($pdo,$f['refs'][$i],$f['ids'][$actor],'Rollback text',0)" if action=='supplement' else "staff_process_reflection($pdo,$f['refs'][$i],$f['ids'][$actor],$action,'Rollback text',0)")
            result=sql('$i='+str(index)+';$actor='+str(actor)+';$action='+php_data(action)+';$prefix='+php_data(prefix)+r''';require 'includes/staff-workflow.php';
class FailureStatement extends PDOStatement {
 public static $prefix;
 protected function __construct() {}
 public function execute(?array $params=null): bool {
  $ok=parent::execute($params);
  if (strpos($this->queryString,self::$prefix)===0) {throw new PDOException('Injected after real SQL write');}
  return $ok;
 }
}
FailureStatement::$prefix=$prefix;$pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS,[FailureStatement::class]);$failed=false;
try {'''+call+r''';}catch(PDOException $e){$failed=true;}echo json_encode(['failed'=>$failed,'active'=>$pdo->inTransaction()]);''')
            check(result['failed'] and not result['active'] and unchanged(index,before),'rollback '+action+' after '+prefix)
    print('ALL',checks,'FOLLOWUP/NOTIFICATION CHECKS PASSED')
finally:
    if fixture:
        database('$f='+php_data(fixture)+r''';$pdo->beginTransaction();
foreach($f['ids'] as $id){
 $q=$pdo->prepare('DELETE FROM ReflectionActivities WHERE UserId=? OR ReflectionId IN (SELECT Id FROM Reflections WHERE UserId=?)');$q->execute([$id,$id]);
 $q=$pdo->prepare('DELETE FROM Notifications WHERE UserId=? OR ReflectionId IN (SELECT Id FROM Reflections WHERE UserId=?)');$q->execute([$id,$id]);
}
foreach($f['ids'] as $id){$q=$pdo->prepare('DELETE FROM Responses WHERE UserId=? OR ReflectionId IN (SELECT Id FROM Reflections WHERE UserId=?)');$q->execute([$id,$id]);}
foreach($f['ids'] as $id){$q=$pdo->prepare('DELETE FROM Reflections WHERE UserId=?');$q->execute([$id]);$q=$pdo->prepare('DELETE FROM Users WHERE Id=? AND Email LIKE ?');$q->execute([$id,'cvfollowuptest-%@campusvoice.test']);}
$pdo->commit();echo json_encode(true);''')
        print('CLEANUP temporary accounts, activities, replies and notices (including notices to existing staff)')
