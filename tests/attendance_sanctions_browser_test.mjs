// Run attendance_sanctions_test.php first to generate disposable-user response snapshots.
import fs from 'node:fs';
import path from 'node:path';
import http from 'node:http';
import {spawn} from 'node:child_process';

const root=path.resolve(import.meta.dirname,'..');
const snapshots=path.join(root,'.tmp/attendance-sanctions-check');
const output=path.join(snapshots,'browser');
fs.mkdirSync(output,{recursive:true});
const profile=path.join(output,'profile-'+Date.now());
const checks=[],errors=[];
const server=http.createServer((request,response)=>{
    const url=new URL(request.url,'http://localhost');
    if(url.pathname==='/auth/connection_status.php'||url.pathname==='/auth/notifications.php'){
        response.setHeader('Content-Type','application/json');
        response.end(JSON.stringify(url.pathname.includes('connection_status')?{available:true}:{academic_period_id:1,unread_count:0,notifications:[]}));return;
    }
    if(url.pathname==='/student/dashboard.php'||url.pathname==='/organization/attendance.php'||url.pathname==='/organization/sanction_submissions.php'){
        const officeStates={policy:'policy.html',saved:'policy-saved.html',edit:'policy-edit.html',locked:'policy-locked.html'};
        const officeFile=url.searchParams.get('edit_policy')==='1'?'policy-edit.html':(Object.hasOwn(officeStates,url.searchParams.get('state'))?officeStates[url.searchParams.get('state')]:'staff.html');
        const file=url.pathname.startsWith('/organization')?(url.pathname.endsWith('/attendance.php')?'attendance.html':officeFile):(url.searchParams.get('state')||'upload')+'.html';
        if(!['staff.html','policy.html','policy-saved.html','policy-edit.html','policy-locked.html','attendance.html','upload.html','returned.html','student.html'].includes(file)){response.writeHead(404);response.end();return;}
        response.setHeader('Content-Type','text/html; charset=utf-8');response.end(fs.readFileSync(path.join(snapshots,file)));return;
    }
    if(url.pathname==='/clearance/sanction_image.php'){
        response.setHeader('Content-Type','image/png');response.end(fs.readFileSync(path.join(snapshots,url.searchParams.get('photo')==='after'?'after.png':'before.png')));return;
    }
    const file=path.resolve(root,'.'+decodeURIComponent(url.pathname));
    if(!url.pathname.startsWith('/assets/')||!file.startsWith(path.join(root,'assets')+path.sep)||!fs.existsSync(file)){response.writeHead(404);response.end();return;}
    response.setHeader('Content-Type',{'.css':'text/css','.js':'text/javascript','.png':'image/png','.svg':'image/svg+xml'}[path.extname(file)]||'application/octet-stream');response.end(fs.readFileSync(file));
});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
const base='http://127.0.0.1:'+server.address().port;
const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
let ws,serial=0;const pending=new Map();
const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(action){for(let i=0;i<100;i++){try{if(await action())return;}catch{}await pause(100);}throw Error('Browser test timed out.');}
function command(method,params={}){const id=++serial;return new Promise((resolve,reject)=>{const timer=setTimeout(()=>{pending.delete(id);reject(Error(method+' timed out'));},10000);pending.set(id,{resolve:value=>{clearTimeout(timer);resolve(value);},reject});ws.send(JSON.stringify({id,method,params}));});}
async function evaluate(expression){const r=await command('Runtime.evaluate',{expression,returnByValue:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;}
async function check(name,expression){const passed=await evaluate(expression)===true;checks.push({name,passed});if(!passed)throw Error(name);}
async function screenshot(name){const r=await command('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(output,name+'.png'),Buffer.from(r.data,'base64'));}
try{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')));
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const target=await(await fetch('http://127.0.0.1:'+port+'/json/new?about:blank',{method:'PUT'})).json();
    ws=new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true});});
    ws.addEventListener('message',event=>{const m=JSON.parse(event.data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);if(p)m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}else if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);});
    await command('Runtime.enable');await command('Page.enable');
    for(const width of [1280,390,320]){
        await command('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<700});
        await command('Page.navigate',{url:base+'/student/dashboard.php#attendance'});
        await until(()=>evaluate("document.readyState==='complete'&&document.body.classList.contains('dashboard-enhanced')"));
        await check(width+'px student page fits screen','document.documentElement.scrollWidth<=innerWidth');
        await check(width+'px separate office submissions',"document.querySelectorAll('.student-sanction-card').length===2&&[...document.querySelectorAll('.sanction-upload-form [name=owner_code]')].map(e=>e.value).join(',')==='ORGANIZATION,STUDENT_COUNCIL'");
        await check(width+'px each form requires exactly Before and After',"[...document.querySelectorAll('.sanction-upload-form')].every(f=>f.querySelectorAll('input[type=file][required]').length===2&&!f.querySelector('input[type=file][multiple]'))");
        await check(width+'px each form blocks missing photos',"[...document.querySelectorAll('.sanction-upload-form')].every(f=>!f.checkValidity())");
        await check(width+'px file controls stay inside cards',"[...document.querySelectorAll('.student-sanction-card input[type=file]')].every(e=>{const b=e.getBoundingClientRect(),c=e.closest('article').getBoundingClientRect();return b.left>=c.left&&b.right<=c.right})");
        await evaluate("document.querySelector('.student-sanctions').scrollIntoView({block:'start'})");await screenshot('student-'+width);
        await command('Page.navigate',{url:base+'/organization/sanction_submissions.php'});
        await until(()=>evaluate("document.readyState==='complete'&&!!document.querySelector('.sanction-review-card')"));
        await evaluate("document.querySelector('.sanction-review-card').scrollIntoView({block:'center'})");
        await until(()=>evaluate("[...document.querySelectorAll('.sanction-photo-pair img')].every(e=>e.complete&&e.naturalWidth>0)"));
        await check(width+'px staff page fits screen','document.documentElement.scrollWidth<=innerWidth');
        await check(width+'px both proof photos load',"document.querySelectorAll('.sanction-photo-pair img').length===2");
        await check(width+'px work confirmation and correction actions exist',"!!document.querySelector('[name=work_verified]')&&!!document.querySelector('button[value=approve]')&&!!document.querySelector('button[value=return]')");
        await check(width+'px submissions sidebar item is selected',"!!document.querySelector('.staff-nav a[aria-current=page][href$=\"/sanction_submissions.php\"]')&&document.querySelector('.sanction-panel h2').textContent.includes('Absence sanctions')");
        await check(width+'px photo layout fits review card',"[...document.querySelectorAll('.sanction-photo-pair img')].every(e=>{const b=e.getBoundingClientRect(),c=e.closest('article').getBoundingClientRect();return b.left>=c.left&&b.right<=c.right})");
        await screenshot('staff-'+width);
        await command('Page.navigate',{url:base+'/organization/sanction_submissions.php?state=policy'});
        await until(()=>evaluate("document.readyState==='complete'&&!!document.querySelector('.sanction-policy-form')"));
        await check(width+'px policy setup fits the screen','document.documentElement.scrollWidth<=innerWidth');
        await check(width+'px sanction setup is on the submissions page',"!!document.querySelector('.staff-nav a[aria-current=page][href$=\"/sanction_submissions.php\"]')&&!!document.querySelector('.sanction-policy-form')");
        await check(width+'px policy requires work, hours and assigned monitor',"!!document.querySelector('.sanction-policy-form textarea[required]')&&!!document.querySelector('.sanction-policy-form [name=hours][required]')&&!!document.querySelector('.sanction-policy-form [name=monitor_user_id][required]')");
        await check(width+'px incomplete policy is blocked',"!document.querySelector('.sanction-policy-form').checkValidity()");
        await evaluate("document.querySelector('.sanction-policy-form').scrollIntoView({block:'center'})");await screenshot('policy-'+width);
        await command('Page.navigate',{url:base+'/organization/sanction_submissions.php?state=saved'});
        await until(()=>evaluate("document.readyState==='complete'&&!!document.querySelector('.sanction-policy-actions a')"));
        await check(width+'px saved policy has Edit and protected Delete',"document.querySelector('.sanction-policy-actions a').textContent==='Edit'&&!!document.querySelector('.sanction-policy-actions form[method=post] [name=csrf_token]')&&!!document.querySelector('.sanction-policy-actions [value=policy_delete]')&&!!document.querySelector('.sanction-policy-actions form[onsubmit]')");
        await check(width+'px saved policy controls fit screen','document.documentElement.scrollWidth<=innerWidth');
        await evaluate("document.querySelector('.sanction-policy-actions a').click()");
        await until(()=>evaluate("document.readyState==='complete'&&!!document.querySelector('.sanction-policy-form [value=policy_update]')"));
        await check(width+'px Edit opens prefilled fields',"document.querySelector('.sanction-policy-form [name=task]').value==='Monitor assigned classroom cleanup'&&Number(document.querySelector('.sanction-policy-form [name=hours]').value)===2&&!!document.querySelector('.sanction-policy-form a')");
        await check(width+'px edit policy form fits screen','document.documentElement.scrollWidth<=innerWidth');
        await evaluate("document.querySelector('.sanction-policy-form').scrollIntoView({block:'center'})");await screenshot('policy-edit-'+width);
        await command('Page.navigate',{url:base+'/organization/sanction_submissions.php?state=locked'});
        await until(()=>evaluate("document.readyState==='complete'&&!!document.querySelector('.sanction-policy-locked')"));
        await check(width+'px Ready policy has no mutation controls',"!document.querySelector('.sanction-policy-actions')&&!document.querySelector('.sanction-policy-form')&&document.querySelector('.sanction-policy-locked').textContent.includes('Office is Ready')");
        await check(width+'px Ready policy fits screen','document.documentElement.scrollWidth<=innerWidth');
    }
    await command('Page.navigate',{url:base+'/student/dashboard.php?state=returned#attendance'});
    await until(()=>evaluate("document.readyState==='complete'&&!!document.querySelector('.sanction-returned')"));
    await check('Returned proof displays feedback and resubmission',"document.querySelector('.sanction-returned').textContent.includes('clearer image')&&document.querySelector('.sanction-upload-form button').textContent.includes('Resubmit')");
    await command('Page.navigate',{url:base+'/student/dashboard.php?state=student#attendance'});
    await until(()=>evaluate("document.readyState==='complete'&&!!document.querySelector('.sanction-submitted')"));
    await check('Pending proof cannot be overwritten through another upload form',"!document.querySelector('.student-sanction-card .sanction-upload-form')");
    if(errors.length)throw Error(errors.join(', '));
    console.log('All '+checks.length+' attendance sanction browser checks passed.');
}catch(error){if(ws?.readyState===WebSocket.OPEN)await screenshot('failure').catch(()=>{});throw error;}
finally{
    fs.writeFileSync(path.join(output,'results.json'),JSON.stringify({checks,errors},null,2));
    if(ws?.readyState===WebSocket.OPEN)await command('Browser.close').catch(()=>{});
    ws?.close();browser.kill();await new Promise(resolve=>server.close(resolve));
}
