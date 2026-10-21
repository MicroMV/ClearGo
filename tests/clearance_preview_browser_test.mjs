// Uses synthetic response snapshots from CCS_COMPLETION_PREVIEW=1.
import fs from 'node:fs';
import path from 'node:path';
import http from 'node:http';
import {spawn} from 'node:child_process';

const root=path.resolve(import.meta.dirname,'..');
const snapshots=path.join(root,'.tmp/ccs-dean-check');
const output=path.join(root,'.tmp/clearance-preview-check');
fs.mkdirSync(output,{recursive:true});
const profile=path.join(output,'browser-' + Date.now());
const checks=[], errors=[], pdfResponses=[];
let pdfDelayMs=0;
let notificationRead=false;
const server=http.createServer((request,response)=>{
    const url=new URL(request.url,'http://localhost');
    if(url.pathname==='/auth/notifications.php') {
        if(request.method==='POST') notificationRead=true;
        response.setHeader('Content-Type','application/json');
        response.end(JSON.stringify({academic_period_id:1,unread_count:notificationRead?0:1,notifications:[{id:999999,title:'ClearGo',message:'The current password is incorrect.',is_read:notificationRead,created_at:'2099-01-01 00:00:00'}]}));return;
    }
    if(url.pathname==='/auth/connection_status.php') {
        response.setHeader('Content-Type','application/json');
        response.end(JSON.stringify({available:true}));return;
    }
    if(url.pathname==='/student/dashboard.php') {
        notificationRead=false;
        response.setHeader('Content-Type','text/html; charset=utf-8');
        response.end(fs.readFileSync(path.join(snapshots,url.searchParams.has('feedback')?'student-feedback.html':'student-clearance.html')));return;
    }
    if(url.pathname==='/student/clearance_preview.php') {
        response.setHeader('Content-Type','text/html; charset=utf-8');
        response.end(fs.readFileSync(path.join(snapshots,'clearance-preview.html')));return;
    }
    if(url.pathname==='/student/clearance_pdf.php') {
        if (url.searchParams.get('view')==='paper') {
            response.setHeader('Content-Type','text/html; charset=utf-8');
            const paper=fs.readFileSync(path.join(snapshots,'clearance-paper.html'));
            setTimeout(()=>response.end(paper),pdfDelayMs);return;
        }
        response.setHeader('Content-Type','application/pdf');
        response.setHeader('Content-Disposition',(url.searchParams.get('view')==='inline'?'inline':'attachment') + '; filename="synthetic-clearance.pdf"');
        response.end(fs.readFileSync(path.join(snapshots,'clearance-preview.pdf')));return;
    }
    const file=path.resolve(root,'.' + decodeURIComponent(url.pathname));
    if(!url.pathname.startsWith('/assets/')||!file.startsWith(path.join(root,'assets')+path.sep)||!fs.existsSync(file)) {response.writeHead(404);response.end();return;}
    response.setHeader('Content-Type',{'.css':'text/css','.png':'image/png','.js':'text/javascript','.svg':'image/svg+xml'}[path.extname(file)]||'application/octet-stream');
    response.end(fs.readFileSync(file));
});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
const base='http://127.0.0.1:' + server.address().port;
const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-address=127.0.0.1','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
let ws,serial=0;const pending=new Map();
const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(action){for(let i=0;i<100;i++){try{const result=await action();if(result)return result;}catch{}await pause(100);}throw Error('Browser test timed out.');}
function command(method,params={}){const id=++serial;return new Promise((resolve,reject)=>{const timer=setTimeout(()=>{pending.delete(id);reject(Error(method+' timed out.'));},10000);pending.set(id,{resolve:value=>{clearTimeout(timer);resolve(value);},reject});ws.send(JSON.stringify({id,method,params}));});}
async function evaluate(expression){const result=await command('Runtime.evaluate',{expression,returnByValue:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;}
async function check(name,expression){const value=await evaluate(expression);checks.push({name,passed:value===true});if(value!==true)throw Error(name+' '+JSON.stringify(await evaluate("({focus:document.activeElement?.outerHTML,frame:document.querySelector('[data-clearance-preview-src]')?.src})")));}
try {
    const port=await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort'))&&fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0]);
    const target=await(await fetch('http://127.0.0.1:'+port+'/json/new?about:blank',{method:'PUT'})).json();
    ws=new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true});});
    ws.addEventListener('message',event=>{const message=JSON.parse(event.data);if(message.id){const item=pending.get(message.id);pending.delete(message.id);if(item)message.error?item.reject(Error(message.error.message)):item.resolve(message.result);}else if(message.method==='Runtime.exceptionThrown')errors.push(message.params.exceptionDetails.text);else if(message.method==='Network.responseReceived'&&message.params.response.url.includes('view=paper'))pdfResponses.push(message.params.response.status);});
    await command('Runtime.enable');await command('Page.enable');await command('Network.enable');
    for(const width of [1280,390,320]) {
        await command('Emulation.setDeviceMetricsOverride',{width,height:1100,deviceScaleFactor:1,mobile:width<700});
        await command('Page.navigate',{url:base+'/student/clearance_preview.php'});
        await until(()=>evaluate("document.readyState==='complete' && !!document.querySelector('.preview-document')"));
        await pause(1500);
        await check(width+'px preview fits screen','document.documentElement.scrollWidth<=innerWidth');
        await check(width+'px readable paper loaded inline',"new URL(document.querySelector('iframe').src).searchParams.get('view')==='paper'");
        await until(()=>evaluate("!!document.querySelector('iframe').contentDocument?.querySelector('[data-paper-sheet] svg')"));
        await check(width+'px actual signature and logo render',"document.querySelector('iframe').contentDocument.querySelectorAll('svg image').length>=2 && document.querySelector('iframe').contentDocument.querySelector('svg').textContent.includes('Dr. Synthetic Dean')");
        if(width<700) {
            await check(width+'px opens at a readable size',"document.querySelector('iframe').contentDocument.querySelector('[data-paper-sheet]').getBoundingClientRect().width>=1100");
            await evaluate("document.querySelector('iframe').contentDocument.querySelector('[data-paper-zoom=fit]').click()");
            await check(width+'px Fit page shows the whole paper',"(()=>{const d=document.querySelector('iframe').contentDocument;return d.querySelector('[data-paper-sheet]').getBoundingClientRect().width<=d.querySelector('[data-paper-scroll]').clientWidth;})()");
            await evaluate("document.querySelector('iframe').contentDocument.querySelector('[data-paper-zoom=read]').click();document.querySelector('iframe').contentDocument.querySelector('[data-paper-zoom=in]').click()");
            await check(width+'px zoom and horizontal pan work',"(()=>{const d=document.querySelector('iframe').contentDocument,v=d.querySelector('[data-paper-scroll]');v.scrollLeft=200;return d.querySelector('[data-paper-scale]').value==='125%' && v.scrollLeft>0;})()");
        }
        await check(width+'px separate download action',"!new URL(document.querySelector('.preview-download').href).searchParams.has('view')");
        await check(width+'px readable preview area',"document.querySelector('iframe').getBoundingClientRect().height>=420");
        await check(width+'px logo is visible',"document.querySelector('.preview-brand img').naturalWidth>0");
        await check(width+'px return link preserves clearance view',"document.querySelector('.preview-back').hash==='#clearance'");
        const screenshot=await command('Page.captureScreenshot',{format:'png'});
        fs.writeFileSync(path.join(output,'preview-'+width+'.png'),Buffer.from(screenshot.data,'base64'));
        await command('Page.navigate',{url:base+'/student/dashboard.php#clearance'});
        await until(()=>evaluate("document.readyState==='complete' && document.body.classList.contains('dashboard-enhanced')"));
        const dashboardScreenshot=await command('Page.captureScreenshot',{format:'png'});
        fs.writeFileSync(path.join(output,'dashboard-'+width+'.png'),Buffer.from(dashboardScreenshot.data,'base64'));
        await check(width+'px hidden preview does not load the PDF',"!document.querySelector('[data-clearance-preview-src]').hasAttribute('src')");
        pdfDelayMs=700;
        await evaluate("document.querySelector('[data-open-clearance-preview]').focus();document.querySelector('[data-open-clearance-preview]').click()");
        await check(width+'px preview opens without leaving dashboard',"location.pathname==='/student/dashboard.php' && location.hash==='#clearance' && document.querySelector('#clearance-preview-dialog').open");
        await check(width+'px pending PDF shows loading feedback',"!document.querySelector('[data-clearance-preview-loading]').hidden && document.querySelector('.portal-clearance-preview__viewer').getAttribute('aria-busy')==='true'");
        if(width===1280) {
            const loading=await command('Page.captureScreenshot',{format:'png'});
            fs.writeFileSync(path.join(output,'dialog-loading.png'),Buffer.from(loading.data,'base64'));
        }
        await until(()=>evaluate("document.querySelector('[data-clearance-preview-loading]').hidden"));
        await check(width+'px loaded PDF clears loading feedback',"document.querySelector('.portal-clearance-preview__viewer').getAttribute('aria-busy')==='false'");
        await pause(1500);
        await check(width+'px dialog fits viewport',"(()=>{const b=document.querySelector('#clearance-preview-dialog').getBoundingClientRect();return b.left>=0&&b.top>=0&&b.right<=innerWidth+1&&b.bottom<=innerHeight+1})()");
        await check(width+'px dialog embeds the zoomable paper',"new URL(document.querySelector('[data-clearance-preview-src]').src).searchParams.get('view')==='paper'");
        await check(width+'px dialog has a separate download',"!new URL(document.querySelector('#clearance-preview-dialog .portal-pdf-download').href).searchParams.has('view')");
        await check(width+'px dashboard scroll is locked',"getComputedStyle(document.body).overflow==='hidden'");
        const modalScreenshot=await command('Page.captureScreenshot',{format:'png'});
        fs.writeFileSync(path.join(output,'dialog-'+width+'.png'),Buffer.from(modalScreenshot.data,'base64'));
        await evaluate("document.querySelector('[data-close-clearance-preview]').click()");
        await until(()=>evaluate("!document.querySelector('#clearance-preview-dialog').open && !document.body.classList.contains('portal-clearance-preview-open')"));
        await check(width+'px close restores focus and unloads PDF',"document.activeElement.matches('[data-open-clearance-preview]') && document.querySelector('[data-clearance-preview-src]').src==='about:blank'");
        await evaluate("document.querySelector('[data-open-clearance-preview]').click()");
        await until(()=>evaluate("document.querySelector('[data-clearance-preview-loading]').hidden && !!document.querySelector('[data-clearance-preview-src]').contentDocument?.querySelector('[data-paper-zoom=in]')"));
        await evaluate("(()=>{const d=document.querySelector('[data-clearance-preview-src]').contentDocument;d.querySelector('[data-paper-zoom=in]').focus();d.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));})()");
        await until(()=>evaluate("!document.querySelector('#clearance-preview-dialog').open"));
        await check(width+'px Escape inside zoom controls closes the dialog',"document.activeElement.matches('[data-open-clearance-preview]')");
        await evaluate("document.querySelector('[data-open-clearance-preview]').click()");
        await check(width+'px reopening shows loading feedback again',"!document.querySelector('[data-clearance-preview-loading]').hidden");
        await command('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
        await command('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
        await until(()=>evaluate("!document.querySelector('#clearance-preview-dialog').open"));
        await check(width+'px Escape returns to the same dashboard view',"location.pathname==='/student/dashboard.php' && location.hash==='#clearance'");
    }
    await command('Page.navigate',{url:base+'/student/dashboard.php?feedback=1#account'});
    await until(()=>evaluate("document.readyState==='complete' && document.body.classList.contains('dashboard-enhanced')"));
    await pause(700);
    await check('Student feedback pops up and is saved in the bell',"document.querySelector('.student-notification-toast')?.textContent.includes('The current password is incorrect.') && document.querySelector('#notificationBox').textContent.includes('The current password is incorrect.')");
    await evaluate("document.querySelector('.student-notification-toast button').click()");
    await until(()=>evaluate("!document.querySelector('.student-notification-toast')"));
    await check('Dismissing the student popup preserves the unread bell entry',"!!document.querySelector('.notification-list .unread') && document.querySelector('#notificationBox').textContent.includes('The current password is incorrect.')");
    await evaluate("document.querySelector('#notificationButton').click()");
    await check('Student notification bell still opens the saved message',"!document.querySelector('#notificationBox').hidden && document.querySelector('#notificationBox').textContent.includes('The current password is incorrect.')");
    if(!pdfResponses.length||pdfResponses.some(status=>status!==200))throw Error('The browser did not receive a valid paper response.');
    if(errors.length)throw Error(errors.join(', '));
    console.log('All '+checks.length+' clearance preview browser checks passed; zoomable paper responses received.');
} finally {
    fs.writeFileSync(path.join(output,'results.json'),JSON.stringify({checks,errors,pdfResponses},null,2));
    if(ws?.readyState===WebSocket.OPEN)await command('Browser.close').catch(()=>{});
    ws?.close();browser.kill();await new Promise(resolve=>server.close(resolve));
}
