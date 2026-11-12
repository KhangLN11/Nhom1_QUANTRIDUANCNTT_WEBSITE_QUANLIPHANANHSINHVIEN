// Actual browser flow, Node 22+ with built-in WebSocket; no npm dependencies.
// Run: node scripts/test_browser_flow.mjs (Apache/MySQL + migration + demo users first).
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {spawn,spawnSync} from 'node:child_process';
import crypto from 'node:crypto';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const base=(process.env.CV_BASE_URL||'http://localhost/campusvoice/').replace(/\/?$/,'/');
const php=process.env.CV_PHP||'C:/xampp/php/php.exe';
const chromePath=process.env.CV_CHROME||'C:/Program Files/Google/Chrome/Application/chrome.exe';
const output=path.join(root,'artifacts');fs.mkdirSync(output,{recursive:true});
const prefix='cvbrowser-'+crypto.randomUUID();const password=crypto.randomUUID();
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
let fixture,chrome,ws,recordId,checks=0;const logs=[];
function log(message){logs.push(message);console.log(message);}
function check(ok,label){if(!ok)throw Error(label);checks++;log('PASS '+label);}
function db(code){const r=spawnSync(php,[],{cwd:root,input:'<?php require "includes/bootstrap.php"; $pdo=db(); '+code,encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return JSON.parse(r.stdout);}
function snapshot(){return db(`$q=$pdo->prepare('SELECT Status,Version,Content,(SELECT COUNT(*) FROM ReflectionActivities a WHERE a.ReflectionId=r.Id) AS Events,(SELECT COUNT(*) FROM Responses s WHERE s.ReflectionId=r.Id) AS Replies,(SELECT COUNT(*) FROM Notifications n WHERE n.ReflectionId=r.Id) AS Notices FROM Reflections r WHERE Id=?');$q->execute([${recordId}]);echo json_encode($q->fetch());`);}
try{
 const encoded=Buffer.from(JSON.stringify({prefix,password})).toString('base64');
 fixture=db(`$d=json_decode(base64_decode('${encoded}'),true);$cat=$pdo->query('SELECT Id FROM Categories ORDER BY Id LIMIT 1')->fetchColumn();if(!$cat){throw new RuntimeException('No categories');}$pdo->beginTransaction();$ids=[];$emails=[];$q=$pdo->prepare('INSERT INTO Users (FullName,Email,PasswordHash,Role) VALUES (?,?,?,?)');foreach(['Student','Staff'] as $i=>$role){$email=$d['prefix'].'-'.$i.'@campusvoice.test';$q->execute(['Browser fixture '.$i,$email,password_hash($d['password'],PASSWORD_DEFAULT),$role]);$ids[]=(int)$pdo->lastInsertId();$emails[]=$email;}$pdo->commit();echo json_encode(['ids'=>$ids,'emails'=>$emails,'category'=>(int)$cat]);`);
 const profile=fs.mkdtempSync(path.join(os.tmpdir(),'campusvoice-browser-'));
 chrome=spawn(chromePath,['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking','--remote-debugging-port=0',`--user-data-dir=${profile}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
 let port;for(let i=0;i<100;i++){try{port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];break;}catch{}await sleep(100);}if(!port)throw Error('Chrome did not start');
 const version=await(await fetch(`http://127.0.0.1:${port}/json/version`)).json();
 ws=new WebSocket(version.webSocketDebuggerUrl);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});
 let serial=0;const pending=new Map();
 ws.onmessage=e=>{const m=JSON.parse(e.data);if(pending.has(m.id)){const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(Error(JSON.stringify(m.error))):p.resolve(m.result);}};
 const call=(method,params={},sessionId)=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params,...(sessionId?{sessionId}:{})}));});
 const target=await call('Target.createTarget',{url:'about:blank'});
 const {sessionId}=await call('Target.attachToTarget',{targetId:target.targetId,flatten:true});
 const c=(method,params={})=>call(method,params,sessionId);
 await c('Network.enable');await c('Page.enable');await c('Emulation.setDeviceMetricsOverride',{width:1448,height:1086,deviceScaleFactor:1,mobile:false});
 async function evaluate(expression){const v=await c('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(v.exceptionDetails)throw Error(v.exceptionDetails.text);return v.result.value;}
 async function wait(expression){for(let i=0;i<100;i++){if(await evaluate(expression))return;await sleep(100);}throw Error('Browser wait timed out: '+expression);}
 async function visit(page){await c('Page.navigate',{url:base+page});await sleep(150);await wait("document.readyState==='complete'");}
 async function fill(selector,value){await evaluate(`document.querySelector(${JSON.stringify(selector)}).value=${JSON.stringify(value)}`);}
 async function click(selector){await evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);}
 async function signIn(email,pass=password){await c('Network.clearBrowserCookies');await visit('login.php');await wait("!!document.querySelector('#email')");await fill('#email',email);await fill('#password',pass);await click('form button[type=submit]');await wait("!location.pathname.endsWith('/login.php')&&document.readyState==='complete'");}
 async function picture(name){const s=await c('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(output,name+'.png'),Buffer.from(s.data,'base64'));}
 async function openNotice(text,staff=false){await visit('notifications.php');await wait("!!document.querySelector('.notification-list')");const found=await evaluate(`(()=>{const e=[...document.querySelectorAll('.notification-item')].find(e=>e.textContent.includes(${JSON.stringify(text)}));if(!e)return false;e.querySelector('button').click();return true;})()`);check(found,'notification exists: '+text.split(':')[0]);await wait(`location.pathname.endsWith('/${staff?'staff-reflection.php':'reflection.php'}')&&document.readyState==='complete'`);}
 const title=prefix+' Luồng kiểm tra trình duyệt';
 const original='Nội dung gốc: Wi-Fi tại thư viện chưa ổn định.\nGiữ nguyên nội dung này qua mọi lượt bổ sung.';
 const reason=prefix+': Cần cung cấp vị trí và thời gian xảy ra sự cố.';
 const supplement=prefix+': Tầng 2 thư viện, lúc 09:00.';
 const answer=prefix+': Đã kiểm tra và khắc phục kết nối Wi-Fi.';
 const closure=prefix+': Đã hoàn thành xử lý và lưu hồ sơ.';
 await signIn(fixture.emails[0]);await visit('reflection-create.php');await fill('#title',title);await fill('#category_id',String(fixture.category));await fill('#content',original);await click('form[action="reflection-create.php"] button[type=submit]');await wait("location.pathname.endsWith('/reflections.php')&&document.readyState==='complete'");
 recordId=db(`$q=$pdo->prepare('SELECT Id FROM Reflections WHERE UserId=? ORDER BY Id DESC LIMIT 1');$q->execute([${fixture.ids[0]}]);echo json_encode((int)$q->fetchColumn());`);
 const originalStored=snapshot().Content;
 check(originalStored.replace(/\r\n/g,'\n')===original,'browser form preserves text with standard CRLF normalization');
 check(recordId>0&&snapshot().Status==='Pending','browser creates reflection through actual student form');
 check(await evaluate("document.body.textContent.includes('Gửi phản ánh thành công')"),'submission success notice');
 await signIn(fixture.emails[1]);await visit('staff.php');
 const detail=await evaluate(`(()=>{const row=[...document.querySelectorAll('tbody tr')].find(e=>e.textContent.includes(${JSON.stringify(title)}));return row?.querySelector('.detail-link')?.href;})()`);check(!!detail,'staff inbox includes submitted reflection');await c('Page.navigate',{url:detail});await sleep(200);await wait("!!document.querySelector('input[value=accept]')");await click('.staff-processing button[type=submit]');await wait("!!document.querySelector('.status-InProgress')");check(snapshot().Status==='InProgress','first acceptance');
 await fill('#reply',reason);await click('button[value=request_info]');await wait("!!document.querySelector('.status-NeedsInfo')");check(snapshot().Status==='NeedsInfo','staff requests additional information');
 await signIn(fixture.emails[0]);await openNotice(reason);check(await evaluate(`document.body.textContent.includes(${JSON.stringify(reason)})`),'student sees request reason');
 const requestedRead=db(`$q=$pdo->prepare('SELECT IsRead FROM Notifications WHERE UserId=? AND ReflectionId=? AND Type=?');$q->execute([${fixture.ids[0]},${recordId},'NeedsInfo']);echo json_encode((int)$q->fetchColumn());`);check(requestedRead===1,'opening request notice marks read');
 await fill('#content',supplement);await click('.supplement-form button[type=submit]');await wait("!!document.querySelector('.status-Pending')");check(snapshot().Status==='Pending'&&snapshot().Content===originalStored,'student supplement returns Pending and preserves original');
 await signIn(fixture.emails[1]);await openNotice(supplement,true);await wait("!!document.querySelector('input[value=accept]')");await click('.staff-processing button[type=submit]');await wait("!!document.querySelector('.status-InProgress')");check(snapshot().Status==='InProgress','re-accept after supplement');
 await fill('#reply',answer);await click('button[value=resolve]');await wait("!!document.querySelector('.status-Resolved')");check(snapshot().Status==='Resolved'&&Number(snapshot().Replies)===1,'reply and resolution through actual staff form');
 await signIn(fixture.emails[0]);await openNotice(answer);check(await evaluate(`document.body.textContent.includes(${JSON.stringify(answer)})`),'student receives result and sees reply');
 await signIn(fixture.emails[1]);await visit('staff-reflection.php?id='+recordId);await fill('#reply',closure);await click('.staff-processing button[type=submit]');await wait("!!document.querySelector('.status-Closed')");check(snapshot().Status==='Closed','close resolved record with reason');await picture('audit-staff-closed');
 await signIn(fixture.emails[0]);await openNotice(closure);check(await evaluate(`document.body.textContent.includes(${JSON.stringify(closure)})`),'student receives close notice and reason');
 check(Number(snapshot().Events)===4&&Number(snapshot().Replies)===1,'one request, one supplement, one result and one closure event');
 const chronology=await evaluate(`(()=>{const t=document.body.textContent;const p=${JSON.stringify([reason,supplement,answer,closure])}.map(s=>t.indexOf(s));return p.every((x,i)=>x>=0&&(i===0||x>p[i-1]));})()`);check(chronology,'timeline chronological');await picture('audit-student-closed');
 const ownerNotice=db(`$q=$pdo->prepare('SELECT Id FROM Notifications WHERE UserId=? AND ReflectionId=? ORDER BY Id LIMIT 1');$q->execute([${fixture.ids[0]},${recordId}]);echo json_encode((int)$q->fetchColumn());`);
 await signIn(process.env.CV_SECOND_STUDENT||'student2@campusvoice.test',process.env.CV_SECOND_PASSWORD||'Student2@123');await visit('reflection.php?id='+recordId);check(await evaluate("document.body.textContent.includes('Không tìm thấy phản ánh')"),'second student cannot open another student reflection');await picture('audit-other-student-denied');
 await visit('reflections.php');check(!await evaluate(`document.body.textContent.includes(${JSON.stringify(title)})`),'other student list excludes reflection');
 await visit('notifications.php');check(!await evaluate(`document.body.textContent.includes(${JSON.stringify(prefix)})`),'other student inbox excludes private notices');
 const csrf=await evaluate("document.querySelector('input[name=csrf]').value");
 const cookies=(await c('Network.getCookies',{urls:[base]})).cookies.map(x=>x.name+'='+x.value).join('; ');
 const before=JSON.stringify(snapshot());
 for(const [url,data,code] of [
 ['notifications.php',{notification_id:ownerNotice,csrf},404],
 ['staff-reflection.php?id='+recordId,{action:'resolve',reply:'forged',version:6,csrf},303],
 ['reflection.php?id='+recordId,{action:'supplement',content:'forged',version:6,csrf},404]]){
  const response=await fetch(base+url,{method:'POST',redirect:'manual',headers:{Cookie:cookies,'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)});check(response.status===code&&JSON.stringify(snapshot())===before,'second student POST denied: '+url.split('?')[0]);
 }
 await visit('staff.php');await wait("location.pathname.endsWith('/student.php')");check(true,'student cannot access staff inbox');
 log('ALL '+checks+' BROWSER FLOW CHECKS PASSED');await call('Browser.close');
}catch(error){log('FAIL '+error.message);throw error;}
finally{
 if(ws)ws.close();if(chrome)chrome.kill();
 if(fixture){const ids=fixture.ids.map(Number).join(',');db(`$ids=[${ids}];$pdo->beginTransaction();foreach($ids as $id){$q=$pdo->prepare('DELETE FROM ReflectionActivities WHERE UserId=? OR ReflectionId IN (SELECT Id FROM Reflections WHERE UserId=?)');$q->execute([$id,$id]);$q=$pdo->prepare('DELETE FROM Notifications WHERE UserId=? OR ReflectionId IN (SELECT Id FROM Reflections WHERE UserId=?)');$q->execute([$id,$id]);}foreach($ids as $id){$q=$pdo->prepare('DELETE FROM Responses WHERE UserId=? OR ReflectionId IN (SELECT Id FROM Reflections WHERE UserId=?)');$q->execute([$id,$id]);}foreach($ids as $id){$q=$pdo->prepare('DELETE FROM Reflections WHERE UserId=?');$q->execute([$id]);$q=$pdo->prepare('DELETE FROM Users WHERE Id=? AND Email LIKE ?');$q->execute([$id,'cvbrowser-%@campusvoice.test']);}$pdo->commit();echo json_encode(true);`);log('CLEANUP temporary browser accounts, reflection, history and notifications; reusable student2 kept');}
 fs.writeFileSync(path.join(output,'browser-flow-audit.log'),logs.join('\n')+'\n','utf8');
}
