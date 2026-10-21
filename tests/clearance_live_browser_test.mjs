// Synthetic responses from CCS_COMPLETION_PREVIEW=1; never connects to school data.
import fs from 'node:fs';
import path from 'node:path';
import http from 'node:http';
import {spawn} from 'node:child_process';

const root = path.resolve(import.meta.dirname, '..');
const snapshots = path.join(root, '.tmp/ccs-dean-check');
const output = path.join(root, '.tmp/clearance-live-check');
fs.mkdirSync(output, {recursive:true});
const profile = path.join(output, 'browser-' + Date.now());
let approved = false, invalidPeriod = false, unavailable = false, liveRequests = 0;
const errors = [], checks = [];
const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    if (url.pathname === '/student/dashboard.php') {
        if (url.searchParams.has('live')) liveRequests++;
        if (unavailable) {response.writeHead(503);response.end();return;}
        let html = fs.readFileSync(path.join(snapshots, 'student-live-' + (approved ? 'after' : 'before') + '.html'), 'utf8');
        if (invalidPeriod) html = html.replace(/data-period-id="\d+"/, 'data-period-id="999999"');
        response.setHeader('Content-Type', 'text/html; charset=utf-8');response.end(html);return;
    }
    if (url.pathname === '/auth/notifications.php') {
        response.setHeader('Content-Type','application/json');
        response.end(JSON.stringify({unread_count:approved ? 1 : 0, notifications:approved ? [{id:99999999,title:'Dean clearance updated',message:'Your Dean approved your clearance.',is_read:false}] : []}));return;
    }
    if (url.pathname === '/auth/connection_status.php') {
        response.setHeader('Content-Type','application/json');response.end('{"available":true}');return;
    }
    const file = path.resolve(root, '.' + decodeURIComponent(url.pathname));
    if (!url.pathname.startsWith('/assets/') || !file.startsWith(path.join(root,'assets')+path.sep) || !fs.existsSync(file)) {response.writeHead(404);response.end();return;}
    response.setHeader('Content-Type', {'.js':'text/javascript','.css':'text/css','.png':'image/png','.svg':'image/svg+xml'}[path.extname(file)] || 'application/octet-stream');
    response.end(fs.readFileSync(file));
});
await new Promise(resolve => server.listen(0,'127.0.0.1',resolve));
const base = 'http://127.0.0.1:' + server.address().port;
const browser = spawn(process.env.EDGE_BINARY || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', ['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-address=127.0.0.1','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'], {windowsHide:true,stdio:'ignore'});
let ws, serial = 0; const pending = new Map();
const pause = ms => new Promise(resolve => setTimeout(resolve,ms));
async function until(action) {for(let i=0;i<100;i++){if(await action())return;await pause(100);}throw Error('Browser test timed out.');}
function command(method,params={}) {const id=++serial;return new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error(method+' timed out')),10000);pending.set(id,{resolve:value=>{clearTimeout(timer);resolve(value);},reject});ws.send(JSON.stringify({id,method,params}));});}
async function evaluate(expression) {const result=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;}
async function check(name,expression) {const passed=await evaluate(expression);checks.push({name,passed:passed===true});if(passed!==true)throw Error(name);}
try {
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')));
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const target=await(await fetch('http://127.0.0.1:'+port+'/json/new?about:blank',{method:'PUT'})).json();
    ws=new WebSocket(target.webSocketDebuggerUrl);
    await new Promise(resolve=>ws.addEventListener('open',resolve,{once:true}));
    ws.addEventListener('message',event=>{const message=JSON.parse(event.data);if(message.id){const job=pending.get(message.id);pending.delete(message.id);if(message.error)job?.reject(message.error);else job?.resolve(message.result);}else if(message.method==='Runtime.exceptionThrown')errors.push(JSON.stringify(message.params.exceptionDetails));});
    await command('Page.enable');await command('Runtime.enable');
    for (const width of [1280,390]) {
        approved=false;invalidPeriod=false;unavailable=false;
        await command('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<700});
        await command('Page.navigate',{url:base+'/student/dashboard.php?viewport='+width+'#clearance'});
        await until(()=>evaluate("document.readyState==='complete' && document.body.classList.contains('dashboard-enhanced')"));
        await check(width+'px starts with no approvals',"document.querySelector('.portal-count strong').textContent==='0'");
        await evaluate("(()=>{document.querySelector('[data-station-filter=attention]').click();document.querySelector('#clearance [data-requirements-modal]').click();window.draftModal=document.querySelector('.requirements-modal:not([hidden])');draftModal.querySelector('.requirements-modal__content').insertAdjacentHTML('beforeend','<form id=live-test-draft><input name=note value=original><input type=file data-file-input><p data-file-status></p></form>');initializeStudentFileInputs(draftModal);window.draftInput=document.querySelector('#live-test-draft [name=note]');draftInput.value='Unsubmitted work';draftInput.dispatchEvent(new Event('input',{bubbles:true}));const upload=document.querySelector('#live-test-draft [type=file]');const files=new DataTransfer();files.items.add(new File(['synthetic'],'proof.png',{type:'image/png'}));upload.files=files.files;upload.dispatchEvent(new Event('change',{bubbles:true}));draftInput.focus();})()");
        approved=true;
        await evaluate('refreshStudentNotifications()');
        await until(()=>evaluate("document.querySelector('.portal-count strong').textContent==='1'"));
        await check(width+'px approval updates count and ring',"document.querySelector('.portal-progress-ring').getAttribute('aria-valuenow')==='9' && document.querySelector('#clearance .portal-summary-pill').textContent.includes('1 / 11')");
        await check(width+'px updates office status and preserves filter',"document.querySelector('[data-station-status]').dataset.stationStatus==='APPROVED' && document.querySelector('[data-station-status]').hidden && document.querySelector('[data-station-filter=attention]').getAttribute('aria-pressed')==='true'");
        await check(width+'px does not navigate or discard a selected upload',"location.hash==='#clearance' && document.querySelector('#live-test-draft [type=file]').files[0].name==='proof.png' && draftInput.value==='Unsubmitted work' && document.activeElement===draftInput && document.querySelector('.requirements-modal:not([hidden])')===draftModal");
        await check(width+'px approval remains in notification bell',"document.querySelector('#notificationBox').textContent.includes('Your Dean approved')");
        await evaluate('closeRequirementsModal()');
        await check(width+'px closing preserves an unfinished draft',"document.querySelector('#live-test-draft [type=file]').files.length===1 && draftInput.isConnected");
        await evaluate("document.querySelectorAll('#clearance [data-requirements-modal]')[1].click()");
        await check(width+'px newly unlocked office opens its requirements',"document.querySelector('.requirements-modal:not([hidden]) h2').textContent.includes('Organization')");
        await command('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape'});
        await command('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape'});
        await check(width+'px newly inserted modal closes with Escape',"!document.querySelector('.requirements-modal:not([hidden])')");
        await evaluate("document.querySelector('#notificationButton').click()");
        await evaluate('refreshStudentClearance()');
        await check(width+'px a background update leaves the bell open',"!document.querySelector('#notificationBox').hidden");
        invalidPeriod=true;approved=false;
        await evaluate('refreshStudentClearance()');
        await check(width+'px rejects a response for another semester',"document.querySelector('.portal-count strong').textContent==='1'");
        unavailable=true;
        await evaluate('refreshStudentClearance()');
        await check(width+'px failed refresh preserves current progress and upload',"document.querySelector('.portal-count strong').textContent==='1' && document.querySelector('#live-test-draft [type=file]').files.length===1");
        const screenshot=await command('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(output,'live-'+width+'.png'),Buffer.from(screenshot.data,'base64'));
    }
    if(errors.length)throw Error(errors.join(', '));
    console.log('All '+checks.length+' live-clearance browser checks passed.');
} finally {
    fs.writeFileSync(path.join(output,'results.json'),JSON.stringify({checks,errors,liveRequests},null,2));
    if(ws?.readyState===WebSocket.OPEN)await command('Browser.close').catch(()=>{});
    ws?.close();browser.kill();await new Promise(resolve=>server.close(resolve));
}
