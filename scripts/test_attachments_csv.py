import sys
sys.stdout.reconfigure(encoding='utf-8')
sys.stderr.reconfigure(encoding='utf-8')
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
PREFIX = 'cvfiletest-' + uuid.uuid4().hex
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


import csv
import io

# HTTP multipart uploads exercise is_uploaded_file/move_uploaded_file for real.
def raw(c,path,fields=None,file=None,method=None):
    headers={};body=None
    if file is not None:
        boundary='cv-'+uuid.uuid4().hex;parts=[]
        for key,value in (fields or {}).items():
            parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+key+'"\r\n\r\n'+str(value)+'\r\n').encode())
        name,data,mime=file
        parts.extend([('--'+boundary+'\r\nContent-Disposition: form-data; name="attachment"; filename="'+name+'"\r\nContent-Type: '+mime+'\r\n\r\n').encode(),data,b'\r\n',('--'+boundary+'--\r\n').encode()])
        body=b''.join(parts);headers['Content-Type']='multipart/form-data; boundary='+boundary
    elif fields is not None:
        body=urllib.parse.urlencode(fields).encode();headers['Content-Type']='application/x-www-form-urlencoded'
    req=urllib.request.Request(BASE+path,data=body,headers=headers,method=method)
    try: response=c.open(req,timeout=20)
    except urllib.error.HTTPError as error: response=error
    data=response.read()
    assert b'Fatal error' not in data and b'SQLSTATE' not in data,data[:200]
    return response.code,response.headers,data

def make_pdf():
    objects=[b'<< /Type /Catalog /Pages 2 0 R >>',b'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',b'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] >>']
    data=b'%PDF-1.4\n';offsets=[0]
    for i,obj in enumerate(objects,1): offsets.append(len(data));data+=str(i).encode()+b' 0 obj\n'+obj+b'\nendobj\n'
    start=len(data);data+=b'xref\n0 4\n0000000000 65535 f \n'+b''.join(('%010d 00000 n \n'%o).encode() for o in offsets[1:])
    return data+b'trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n'+str(start).encode()+b'\n%%EOF\n'

fixture=None;trigger=None;storage=None
try:
    fixture=database('$data='+php_data({'prefix':PREFIX,'password':PASSWORD})+r''';
$category=$pdo->query('SELECT Id FROM Categories ORDER BY Id LIMIT 1')->fetchColumn();if(!$category){exit(1);}
$pdo->beginTransaction();$ids=[];$emails=[];$q=$pdo->prepare('INSERT INTO Users (FullName,Email,PasswordHash,Role,StudentCode) VALUES (?,?,?,?,?)');
foreach(['Student','Student','Staff'] as $i=>$role){$email=$data['prefix'].'-'.$i.'@campusvoice.test';$q->execute(['Nguyễn Thị Ánh '.$i,$email,password_hash($data['password'],PASSWORD_DEFAULT),$role,$i===0?'+CV-'.$data['prefix']:null]);$ids[]=(int)$pdo->lastInsertId();$emails[]=$email;}
$pdo->commit();echo json_encode(['ids'=>$ids,'emails'=>$emails,'category'=>(int)$category]);''')
    def sql(code): return database('$f='+php_data(fixture)+'; '+code)
    storage=Path(database("require 'includes/attachments.php';echo json_encode(attachment_storage_dir());"))
    owner,other,staff=[login(email) for email in fixture['emails']]
    def own_count(): return sql("$q=$pdo->prepare('SELECT COUNT(*) FROM Reflections WHERE UserId=?');$q->execute([$f['ids'][0]]);echo json_encode((int)$q->fetchColumn());")
    def fields(title='Tiếng Việt: phòng học, thư viện'):
        _,_,page=request(owner,'reflection-create.php')
        return {'title':title,'content':'Dòng một\nDòng hai, có dấu','category_id':fixture['category'],'csrf':csrf(page)}
    def newest(): return sql("$q=$pdo->prepare('SELECT r.Id,a.Id AS AttachmentId,a.FileName,a.ObjectKey FROM Reflections r JOIN Attachments a ON a.ReflectionId=r.Id WHERE r.UserId=? ORDER BY r.Id DESC LIMIT 1');$q->execute([$f['ids'][0]]);echo json_encode($q->fetch());")
    pdf=make_pdf()
    png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
    jpeg=next((ROOT/'references/diagrams').glob('*.jpg')).read_bytes()
    uploaded=[]
    for name,data,mime in [('Minh chứng tiếng Việt.pdf',pdf,'application/pdf'),('minh-chung.png',png,'application/x-httpd-php'),('minh-chung.JPG',jpeg,'image/jpeg')]:
        status,headers,_=raw(owner,'reflection-create.php',fields(),(name,data,mime))
        check(status==303 and headers.get('Location')=='reflections.php','valid upload '+name)
        record=newest();uploaded.append((record,data))
        check((storage/record['ObjectKey']).read_bytes()==data,'file stored outside web root, exact bytes')
        for who,label in [(owner,'owner'),(staff,'staff')]:
            status,headers,body=raw(who,'attachment-download.php?id='+str(record['AttachmentId']))
            check(status==200 and body==data and headers.get('X-Content-Type-Options')=='nosniff' and 'attachment;' in headers.get('Content-Disposition',''),'download authorized '+label)
        status,_,_=raw(other,'attachment-download.php?id='+str(record['AttachmentId']))
        check(status==404,'other student download denied')
    check('Minh%20ch%E1%BB%A9ng' in raw(owner,'attachment-download.php?id='+str(uploaded[0][0]['AttachmentId']))[1]['Content-Disposition'],'UTF-8 filename header')
    before_count=own_count();before_files=set(storage.iterdir())
    for name,data,mime in [('shell.php',png,'image/png'),('fake.pdf',b'<?php echo 1; ?>','application/pdf'),('fake.jpg',pdf,'image/jpeg'),('empty.pdf',b'','application/pdf'),('big.pdf',b'%PDF-'+b'A'*(5*1024*1024),'application/pdf')]:
        status,_,body=raw(owner,'reflection-create.php',fields(),(name,data,mime))
        check(status==422 and own_count()==before_count and set(storage.iterdir())==before_files,'reject wrong type/empty/oversize '+name)
        check('Dòng một' in body.decode('utf-8'),'text retained after file error')
    invalid=fields();invalid['csrf']='wrong'
    status,_,_=raw(owner,'reflection-create.php',invalid,('ok.pdf',pdf,'application/pdf'))
    check(status==403 and own_count()==before_count and set(storage.iterdir())==before_files,'upload CSRF; no orphan data/file')
    record=uploaded[0][0];rid=int(record['Id'])
    for who,path,code in [(client(),'attachment-download.php?id='+str(record['AttachmentId']),303),(owner,'attachment-download.php?id[]=1',404),(owner,'attachment-download.php?id=-1',404)]:
        check(raw(who,path)[0]==code,'download invalid/anonymous access')
    check(raw(owner,'attachment-download.php?id='+str(record['AttachmentId']),{},method='POST')[0]==405,'download read-only method')
    for who,path in [(owner,'reflection.php?id='+str(rid)),(staff,'staff-reflection.php?id='+str(rid))]:
        _,_,body=request(who,path)
        check('attachment-download.php?id='+str(record['AttachmentId']) in body,'detail lists attachment for authorized role')
    sql('$id='+str(rid)+";$q=$pdo->prepare(\"UPDATE Reflections SET Status='NeedsInfo' WHERE Id=?\");$q->execute([$id]);echo json_encode(true);")
    _,_,page=request(owner,'reflection.php?id='+str(rid))
    data={'action':'supplement','content':'Bổ sung cùng tệp','version':0,'csrf':csrf(page)}
    check(raw(owner,'reflection.php?id='+str(rid),data,('bo-sung.pdf',pdf,'application/pdf'))[0]==303,'supplement can attach file')
    attachment_count=sql('$id='+str(rid)+";$q=$pdo->prepare('SELECT COUNT(*) FROM Attachments WHERE ReflectionId=?');$q->execute([$id]);echo json_encode((int)$q->fetchColumn());")
    check(attachment_count==2,'initial and supplement files retained')
    before_files=set(storage.iterdir())
    check(raw(owner,'reflection.php?id='+str(rid),data,('retry.pdf',pdf,'application/pdf'))[0]==409 and set(storage.iterdir())==before_files,'stale supplement does not move duplicate file')
    sql('$id='+str(rid)+";$q=$pdo->prepare(\"UPDATE Reflections SET Status='Closed' WHERE Id=?\");$q->execute([$id]);echo json_encode(true);")
    check(raw(owner,'attachment-download.php?id='+str(record['AttachmentId']))[0]==200,'closed reflection attachments still downloadable')
    # Formula cells are text data in the exported CSV, not executable spreadsheet formulas.
    sql("$q=$pdo->prepare('INSERT INTO Reflections (Title,Content,UserId,CategoryId) VALUES (?,?,?,?)');$q->execute(['=2+3',chr(9).'=2+4',$f['ids'][0],$f['category']]);echo json_encode(true);")
    status,headers,data=raw(staff,'staff-export.php?status=Pending')
    check(status==200 and 'text/csv' in headers.get('Content-Type','') and data.startswith(bytes([239,187,191])),'CSV MIME and UTF-8 BOM for Excel')
    rows=list(csv.DictReader(io.StringIO(data.decode('utf-8-sig'))))
    own=[row for row in rows if row['Email']==fixture['emails'][0]]
    check(own and all(row['Trạng thái']=='Chờ tiếp nhận' for row in rows),'CSV current status filter applied to all rows')
    check(all(row['ID']!=str(rid) for row in rows),'Closed record excluded from Pending CSV')
    check(any(row['Tiêu đề']=='Tiếng Việt: phòng học, thư viện' and row['Nội dung']=='Dòng một\nDòng hai, có dấu' for row in own),'CSV Vietnamese, quotes/comma/newline roundtrip')
    formula=next(row for row in own if row['Tiêu đề']=="'=2+3")
    check(formula['Nội dung']=="'\t=2+4" and formula['Mã sinh viên'].startswith("'+CV-"),'Excel formula injection neutralized')
    _,_,page=request(staff,'staff.php?status=Pending')
    check('staff-export.php?status=Pending' in page,'export link preserves selected filter')
    for query in ['status=Bad','status[]=Pending','status=Pending%27%20OR%201=1']:
        check(raw(staff,'staff-export.php?'+query)[0]==422,'CSV invalid filter denied')
    for who,location in [(other,'student.php'),(client(),'login.php')]:
        status,h,_=raw(who,'staff-export.php')
        check(status==303 and h.get('Location')==location,'CSV role/session access denied')
    check(raw(staff,'staff-export.php',{},method='POST')[0]==405,'CSV GET only')
    if os.environ.get('CV_TEST_ISOLATED_DB')=='1':
        # Safe only in the dedicated install-test database; no production schema triggers.
        name=os.environ.get('DB_NAME','')
        assert re.fullmatch(r'campusvoice_installtest_[0-9a-f]{12}',name)
        (ROOT/'artifacts/sample-vietnamese.csv').write_bytes(data)
        trigger='cv_fail_'+uuid.uuid4().hex[:12]
        database("$pdo->exec(\"CREATE TRIGGER "+trigger+" BEFORE INSERT ON Attachments FOR EACH ROW BEGIN IF NEW.FileName = 'force-fail.pdf' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='isolated test failure'; END IF; END\");echo json_encode(true);")
        before_count=own_count();before_files=set(storage.iterdir())
        status,_,_=raw(owner,'reflection-create.php',fields(),('force-fail.pdf',pdf,'application/pdf'))
        check(status==503 and own_count()==before_count and set(storage.iterdir())==before_files,'failed metadata insert rolls back reflection and removes physical file')
        database("$pdo->exec('DROP TRIGGER "+trigger+"');echo json_encode(true);");trigger=None
        # Failure after attachment + activity + status writes must also remove the physical file.
        target_id=int(uploaded[1][0]['Id'])
        sql('$id='+str(target_id)+";$q=$pdo->prepare(\"UPDATE Reflections SET Status='NeedsInfo' WHERE Id=?\");$q->execute([$id]);echo json_encode(true);")
        trigger='cv_fail_'+uuid.uuid4().hex[:12]
        database("$pdo->exec(\"CREATE TRIGGER "+trigger+" BEFORE INSERT ON Notifications FOR EACH ROW BEGIN IF NEW.Content = 'force-notice-failure' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='isolated notice failure'; END IF; END\");echo json_encode(true);")
        def supplement_state():
            return sql('$id='+str(target_id)+";$q=$pdo->prepare('SELECT Status,Version,(SELECT COUNT(*) FROM Attachments a WHERE a.ReflectionId=r.Id) AS Files,(SELECT COUNT(*) FROM ReflectionActivities a WHERE a.ReflectionId=r.Id) AS Events FROM Reflections r WHERE Id=?');$q->execute([$id]);echo json_encode($q->fetch());")
        before=supplement_state();before_files=set(storage.iterdir())
        _,_,page=request(owner,'reflection.php?id='+str(target_id))
        status,_,_=raw(owner,'reflection.php?id='+str(target_id),{'action':'supplement','content':'force-notice-failure','version':0,'csrf':csrf(page)},('rollback-notice.pdf',pdf,'application/pdf'))
        check(status==503 and supplement_state()==before and set(storage.iterdir())==before_files,'failed supplement notification rolls back file metadata, activity, status and physical file')
        database("$pdo->exec('DROP TRIGGER "+trigger+"');echo json_encode(true);");trigger=None
    sql("$q=$pdo->prepare('UPDATE Users SET IsActive=0 WHERE Id=?');$q->execute([$f['ids'][2]]);echo json_encode(true);")
    status,h,_=raw(staff,'staff-export.php')
    check(status==303 and h.get('Location')=='login.php','inactive staff cannot export')
    print('ALL',checks,'ATTACHMENT/CSV CHECKS PASSED')
finally:
    if trigger:
        database("$pdo->exec('DROP TRIGGER IF EXISTS "+trigger+"');echo json_encode(true);")
    if fixture:
        keys=sql("$q=$pdo->prepare('SELECT a.ObjectKey FROM Attachments a JOIN Reflections r ON r.Id=a.ReflectionId WHERE r.UserId IN (?,?)');$q->execute([$f['ids'][0],$f['ids'][1]]);echo json_encode($q->fetchAll(PDO::FETCH_COLUMN));")
        database('$f='+php_data(fixture)+r''';$pdo->beginTransaction();foreach($f['ids'] as $id){
$q=$pdo->prepare('DELETE FROM Attachments WHERE ReflectionId IN (SELECT Id FROM Reflections WHERE UserId=?)');$q->execute([$id]);
$q=$pdo->prepare('DELETE FROM ReflectionActivities WHERE UserId=? OR ReflectionId IN (SELECT Id FROM Reflections WHERE UserId=?)');$q->execute([$id,$id]);
$q=$pdo->prepare('DELETE FROM Notifications WHERE UserId=? OR ReflectionId IN (SELECT Id FROM Reflections WHERE UserId=?)');$q->execute([$id,$id]);}
foreach($f['ids'] as $id){$q=$pdo->prepare('DELETE FROM Responses WHERE UserId=? OR ReflectionId IN (SELECT Id FROM Reflections WHERE UserId=?)');$q->execute([$id,$id]);}
foreach($f['ids'] as $id){$q=$pdo->prepare('DELETE FROM Reflections WHERE UserId=?');$q->execute([$id]);$q=$pdo->prepare('DELETE FROM Users WHERE Id=? AND Email LIKE ?');$q->execute([$id,'cvfiletest-%@campusvoice.test']);}$pdo->commit();echo json_encode(true);''')
        for key in keys:
            assert re.fullmatch(r'[a-f0-9]{64}\.(pdf|jpg|png)',key)
            target=(storage/key).resolve()
            assert target.parent==storage.resolve()
            target.unlink(missing_ok=True)
        print('CLEANUP temporary attachments/files, accounts, history and notifications')
