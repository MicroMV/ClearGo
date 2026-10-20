import fs from 'node:fs';
import path from 'node:path';
import {spawn,spawnSync} from 'node:child_process';
const out=path.resolve('qa-audit/2026-10-18'),fixture=JSON.parse(fs.readFileSync(path.join(out,'sandbox-fixture.json'))),app=path.join(out,'sandbox-app');
const base='http://127.0.0.1:8829',php='C:/xamppl/php/php.exe';
const env={...process.env,DB_DATABASE:fixture.database,APP_BASE_URL:'/'};
const log=fs.openSync(path.join(out,'sandbox-server.log'),'a');
const server=spawn(php,['-d','mysqlnd.net_read_timeout=5','-S','127.0.0.1:8829','-t',app],{env,windowsHide:true,stdio:['ignore',log,log]});
const results=[];let seq=1;
function record(role,feature,test,result,evidence,ms=0){results.push({test_id:'SB-'+String(seq++).padStart(3,'0'),role,feature,test,result,milliseconds:ms,evidence});fs.writeFileSync(path.join(out,'sandbox-tests.json'),JSON.stringify(results,null,2));}
async function request(url,account={},data){const start=Date.now();const r=await fetch(base+url,{redirect:'manual',signal:AbortSignal.timeout(15000),headers:account.cookie?{Cookie:account.cookie}:{},...(data?{method:'POST',body:new URLSearchParams(data)}:{})});if(r.headers.get('set-cookie'))account.cookie=r.headers.get('set-cookie').split(';')[0];const text=await r.text();return {status:r.status,text,location:r.headers.get('location'),type:r.headers.get('content-type'),ms:Date.now()-start};}
async function login(role){const a={};const first=await request('/login.php',a);a.token=first.text.match(/name="csrf_token" value="([^"]+)/)?.[1];const r=await request('/auth/login_process.php',a,{csrf_token:a.token,...{email:fixture.accounts[role].email,password:fixture.accounts[role].password}});a.login=r;return a;}
try{
    let ready=false;for(let i=0;i<40;i++){try{await request('/login.php');ready=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}
    if(!ready)throw new Error('Sandbox PHP server did not start');
    const accounts={};
    const pages={STUDENT:['/student/dashboard.php','/student/password.php','/student/attendance_scanner.php'],DEAN:['/dean/dashboard.php','/dean/submitted_requirements.php'],ORG_STAFF:['/organization/dashboard.php','/organization/attendance.php','/organization/event_qr.php'],SASO_STAFF:['/saso/dashboard.php'],STUDENT_COUNCIL_STAFF:['/student-council/dashboard.php','/student-council/attendance.php','/student-council/event_qr.php'],LIBRARY_STAFF:['/library/dashboard.php','/library/submissions.php'],BOOKSTORE_STAFF:['/bookstore/dashboard.php','/bookstore/fees.php','/bookstore/submissions.php'],CLINIC_STAFF:['/clinic/dashboard.php','/clinic/schedules.php'],FACILITIES_STAFF:['/facilities/dashboard.php','/facilities/borrowed-items.php'],COMLAB_STAFF:['/comlab/dashboard.php','/comlab/violations.php'],ACCOUNTING_STAFF:['/accounting/dashboard.php','/accounting/submissions.php'],REGISTRAR:['/registrar/dashboard.php','/registrar/students.php','/registrar/staff_accounts.php','/registrar/clearance.php','/registrar/student_import.php','/registrar/audit_logs.php']};
    for(const role of Object.keys(pages)){
        const a=await login(role);accounts[role]=a;
        record(role,'Authentication','Sign in through real login form using synthetic credentials',a.login.status===303?'PASS':'FAIL',JSON.stringify({status:a.login.status,location:a.login.location,error:a.login.text.slice(0,500)}),a.login.ms);
        for(const url of pages[role]){
            const r=await request(url,a); const raw=/Fatal error|SQLSTATE|Stack trace|<b>Warning<\/b>/.test(r.text);
            const ok=(r.status===200&&!raw)||(url.endsWith('event_qr.php')&&r.status===303);
            record(role,url,'Load authorized page with synthetic account',ok?'PASS':'FAIL',JSON.stringify({status:r.status,location:r.location,raw_error:raw,excerpt:raw?r.text.replace(/<[^>]+>/g,' ').slice(0,1000):undefined}),r.ms);
            fs.writeFileSync(path.join(out,'page-'+role+'-'+path.basename(url)+'.html'),r.text);
        }
    }
    for(const [role,a] of Object.entries(accounts))for(const [target,urls]of Object.entries(pages))if(role!==target){const r=await request(urls[0],a);record(role,'Role separation','Open '+target+' dashboard',r.status===403?'PASS':'FAIL',JSON.stringify({status:r.status,target}),r.ms);}
    for(const[role,a]of Object.entries(accounts)){
        const target=role==='STUDENT'?'/student/action.php':role==='DEAN'?'/dean/action.php':role==='ORG_STAFF'?'/organization/action.php':'/clearance/office_action.php';
        const r=await request(target,a,{action:'approve',clearance_id:'1',csrf_token:'invalid'});
        record(role,'CSRF protection','Send synthetic approval request with invalid token',r.status===303?'PASS':'FAIL',JSON.stringify({status:r.status,location:r.location}),r.ms);
        const logout=await request('/logout.php',a,{csrf_token:a.token});
        const after=await request(pages[role][0],a);
        record(role,'Logout','Log out and revisit protected dashboard',logout.status===303&&after.status===303&&after.location==='/login.php'?'PASS':'FAIL',JSON.stringify({logout:logout.status,after:after.status,location:after.location}),logout.ms+after.ms);
    }
    // Preserve only disposable sandbox cookies for the local browser checks.
    const student=await login('STUDENT');fs.writeFileSync(path.join(out,'sandbox-browser-session.json'),JSON.stringify({base,cookie:student.cookie,token:student.token}));
    console.log(JSON.stringify({tests:results.length,counts:results.reduce((r,x)=>(r[x.result]=(r[x.result]||0)+1,r),{}),failures:results.filter(x=>x.result==='FAIL')},null,2));
}catch(e){record('All','Sandbox harness','Execute isolated HTTP tests','BLOCKED',e.stack);process.exitCode=1;}finally{server.kill();fs.closeSync(log);}
