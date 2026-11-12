# Isolated fresh installation: creates and drops ONLY its own random test database.
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import urllib.request
import uuid

sys.stdout.reconfigure(encoding='utf-8')
sys.stderr.reconfigure(encoding='utf-8')
ROOT=Path(__file__).resolve().parents[1]
PHP=os.environ.get('CV_PHP',r'C:\xampp\php\php.exe')
MYSQL=os.environ.get('CV_MYSQL',r'C:\xampp\mysql\bin\mysql.exe')
ARTIFACTS=ROOT/'artifacts';ARTIFACTS.mkdir(exist_ok=True)
name='campusvoice_installtest_'+uuid.uuid4().hex[:12]
assert re.fullmatch(r'campusvoice_installtest_[0-9a-f]{12}',name)
work=Path(tempfile.mkdtemp(prefix='campusvoice-install-')).resolve()
logs=[];server=None;created=False;server_log=None
flags=getattr(subprocess,'CREATE_NO_WINDOW',0)

def run(args,env=None,data=None):
    result=subprocess.run(args,cwd=ROOT,env=env,input=data,capture_output=True,encoding='utf-8',creationflags=flags)
    if result.returncode: raise RuntimeError('Command failed: '+Path(args[0]).name+'\n'+result.stderr+'\n'+result.stdout)
    return result.stdout

def log(value):
    logs.append(value);print(value,flush=True)

config=json.loads(run([PHP],data='<?php echo json_encode(require "config/database.php");'))
mysql_env=dict(os.environ,MYSQL_PWD=config['password'])
mysql_args=[MYSQL,'--host='+config['host'],'--port='+str(config['port']),'--user='+config['user'],'--default-character-set=utf8mb4','--batch','--skip-column-names']
env=dict(os.environ,DB_NAME=name,CV_UPLOAD_DIR=str(work/'uploads'),PYTHONIOENCODING='utf-8',CV_TEST_ISOLATED_DB='1',CV_PHP=PHP)

def php_json(code):
    return json.loads(run([PHP],env=env,data='<?php require "includes/bootstrap.php"; $pdo=db(); '+code))
try:
    exists=run(mysql_args,env=mysql_env,data="SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='"+name+"';").strip()
    if exists: raise RuntimeError('Refusing to reuse an existing database')
    created=True
    for relative in ['database/campusvoice.sql','database/migrations/001_followup_notifications.sql']:
        sql=(ROOT/relative).read_text(encoding='utf-8')
        sql=sql.replace('CREATE DATABASE IF NOT EXISTS campusvoice ','CREATE DATABASE '+name+' ').replace('USE campusvoice;','USE '+name+';')
        run(mysql_args,env=mysql_env,data=sql)
        log('PASS imported '+relative+' into '+name)
    for attempt in range(2):
        run([PHP,'scripts/create_demo_users.php'],env=env)
    seeded=php_json("$q=$pdo->query('SELECT Role,PasswordHash,Email FROM Users');$rows=$q->fetchAll();$expected=['student@campusvoice.test'=>'Student@123','student2@campusvoice.test'=>'Student2@123','staff@campusvoice.test'=>'Staff@123'];$ok=count($rows)===3;foreach($rows as $row){$ok=$ok&&isset($expected[$row['Email']])&&password_verify($expected[$row['Email']],$row['PasswordHash']);}echo json_encode($ok);")
    if not seeded: raise RuntimeError('Demo seed verification failed')
    log('PASS three demo accounts, hashes and repeatable seeding')
    # Re-running additive migration must keep accounts and schema intact.
    sql=(ROOT/'database/migrations/001_followup_notifications.sql').read_text(encoding='utf-8').replace('USE campusvoice;','USE '+name+';')
    run(mysql_args,env=mysql_env,data=sql)
    if php_json("echo json_encode((int)$pdo->query('SELECT COUNT(*) FROM Users')->fetchColumn());")!=3: raise RuntimeError('Migration changed seeded accounts')
    log('PASS migration rerun preserves demo users')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
    base='http://127.0.0.1:'+str(port)+'/'
    env['CV_BASE_URL']=base
    server_log=(work/'server.log').open('w',encoding='utf-8')
    server=subprocess.Popen([PHP,'-d','upload_max_filesize=6M','-d','post_max_size=8M','-S','127.0.0.1:'+str(port),'-t',str(ROOT)],cwd=ROOT,env=env,stdout=server_log,stderr=subprocess.STDOUT,creationflags=flags)
    for attempt in range(100):
        if server.poll() is not None: raise RuntimeError('Isolated PHP server failed')
        try:
            if urllib.request.urlopen(base+'login.php',timeout=1).status==200: break
        except Exception: time.sleep(.1)
    else: raise RuntimeError('Isolated server not ready')
    log('PASS PDO via DB_NAME override and isolated PHP HTTP server '+base)
    scripts=[('python','test_attachments_csv.py'),('python','test_followup_notifications.py'),('python','test_staff_reflections.py'),('python','test_student_reflections.py'),('node','test_browser_flow.mjs')]
    for executable,script in scripts:
        tool=sys.executable if executable=='python' else 'node'
        result=subprocess.run([tool,str(ROOT/'scripts'/script)],cwd=ROOT,env=env,capture_output=True,encoding='utf-8',creationflags=flags)
        (ARTIFACTS/('install-'+script+'.log')).write_text(result.stdout+'\n'+result.stderr,encoding='utf-8')
        for line in result.stdout.splitlines():
            if line.startswith(('ALL','CLEANUP')): log(line)
        if result.returncode: raise RuntimeError(script+' failed\n'+result.stdout+'\n'+result.stderr)
    tables=php_json("$out=[];foreach(['Users','Reflections','Responses','ReflectionActivities','Notifications','Attachments'] as $table){$out[$table]=(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();}echo json_encode($out);")
    if tables!={'Users':3,'Reflections':0,'Responses':0,'ReflectionActivities':0,'Notifications':0,'Attachments':0}: raise RuntimeError('Unexpected test data remains: '+str(tables))
    if list((work/'uploads').glob('*')): raise RuntimeError('Temporary upload file remains')
    log('PASS final database contains only demo accounts; temporary uploads cleaned')
    log('ALL FRESH INSTALL CHECKS PASSED')
except Exception as error:
    log('FAIL '+str(error));raise
finally:
    if server:
        server.terminate()
        try: server.wait(timeout=10)
        except subprocess.TimeoutExpired: server.kill();server.wait(timeout=5)
    if server_log:
        server_log.close()
        shutil.copyfile(work/'server.log',ARTIFACTS/'fresh-install-server.log')
    if created:
        # Strictly bounded target, generated above and verified absent before import.
        assert re.fullmatch(r'campusvoice_installtest_[0-9a-f]{12}',name) and name!=config['name']
        run(mysql_args,env=mysql_env,data='DROP DATABASE IF EXISTS `'+name+'`;')
        log('CLEANUP dropped only '+name+'; current database/config were not changed')
    # Validate the resolved absolute recursive deletion target is our own temp directory.
    assert work.parent==Path(tempfile.gettempdir()).resolve() and work.name.startswith('campusvoice-install-')
    shutil.rmtree(work)
    (ARTIFACTS/'fresh-install.log').write_text('\n'.join(logs)+'\n',encoding='utf-8')
