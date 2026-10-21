// Exercises real shared logout markup against a local receiver; no school sessions are used.
import fs from 'node:fs';
import path from 'node:path';
import http from 'node:http';
import {spawn,spawnSync} from 'node:child_process';
const root=path.resolve(import.meta.dirname,'..'),output=path.join(root,'.tmp/logout-confirmation-check');
fs.mkdirSync(output,{recursive:true});
const fixture=spawnSync('C:/xamppl/php/php.exe',[path.join(root,'tests/support/logout_fixture.php')],{cwd:root,encoding:'utf8',windowsHide:true});
if(fixture.status!==0)throw Error('Unable to render shared logout fixture.');
const html=fixture.stdout;
if(!html.includes('data-confirm-logout')||!html.includes('logout-confirmation.js'))throw Error('Shared logout markup or assets are missing.');
const csrf=html.match(/name="csrf_token" value="([^"]+)"/)[1],posts=[],checks=[];
const prefix='/school/clearance';
const server=http.createServer((req,res)=>{
 const url=new URL(req.url,'http://localhost');
 if(req.method==='POST'&&url.pathname===prefix+'/logout.php'){
  let body='';req.on('data',c=>body+=c);req.on('end',()=>{posts.push({path:url.pathname,fields:Object.fromEntries(new URLSearchParams(body))});res.setHeader('Content-Type','text/html');res.end('<!doctype html><h1 id="signed-out">Signed out</h1>');});return;
 }
 if(req.method!=='GET'){res.writeHead(405).end();return;}
 if(url.pathname===prefix+'/fixture.php'){
  const staff=url.searchParams.get('role')==='staff';res.setHeader('Content-Type','text/html; charset=utf-8');
  res.end(html.replace('TEST_BODY',staff?'staff-workspace':'student-dashboard student-portal').replace('TEST_FORM_WRAPPER',staff?'staff-sidebar':'portal-signout'));return;
 }
 if(url.pathname===prefix+'/auth/connection_status.php'){res.setHeader('Content-Type','application/json');res.end('{"available":true}');return;}
 const relative=url.pathname.slice(prefix.length),file=path.resolve(root,'.'+relative);
 if(!url.pathname.startsWith(prefix+'/')||(!relative.startsWith('/assets/')&&!['/manifest.webmanifest','/connection-worker.js','/errors/offline.html'].includes(relative))||!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404).end();return;}
 res.setHeader('Content-Type',{'.css':'text/css','.js':'application/javascript','.png':'image/png','.webmanifest':'application/manifest+json','.html':'text/html'}[path.extname(file)]||'application/octet-stream');res.end(fs.readFileSync(file));
});
await new Promise(r=>server.listen(0,'127.0.0.1',r));const base='http://127.0.0.1:'+server.address().port;
const profile=path.join(output,'browser-'+Date.now());
const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
let ws,id=0;const pending=new Map(),pause=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn){for(let i=0;i<150;i++){if(await fn())return;await pause(60);}throw Error('Browser condition timed out.');}
function command(method,params={}){const key=++id;return new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error(method+' timed out')),12000);pending.set(key,{resolve:v=>{clearTimeout(timer);resolve(v);},reject});ws.send(JSON.stringify({id:key,method,params}));});}
async function evaluate(expression){const r=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;}
async function check(name,expression){const passed=await evaluate(expression)===true;checks.push({name,passed});if(!passed)throw Error(name);}
async function key(value,code,modifiers=0){const windowsVirtualKeyCode={Tab:9,Escape:27,Enter:13}[value];await command('Input.dispatchKeyEvent',{type:'keyDown',key:value,code,modifiers,windowsVirtualKeyCode});await command('Input.dispatchKeyEvent',{type:'keyUp',key:value,code,modifiers,windowsVirtualKeyCode});}
try{
 await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')));
 const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
 const target=await(await fetch('http://127.0.0.1:'+port+'/json/new?about:blank',{method:'PUT'})).json();
 ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
 ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);if(p)m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}});
 await command('Page.enable');await command('Runtime.enable');
 for(const role of ['student','staff'])for(const width of [1280,390,320]){
  const label=role+' '+width+'px',before=posts.length;
  await command('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<700});
  await command('Page.navigate',{url:base+prefix+'/fixture.php?role='+role});
  await until(()=>evaluate("document.readyState==='complete'&&!!document.querySelector('[data-confirm-logout]')"));
  await evaluate("document.querySelector('.logout-form button').click()");
  await check(label+' asks for confirmation without navigation',"!!document.querySelector('.cleargo-logout-dialog:modal')&&location.pathname.endsWith('/fixture.php')");
  if(posts.length!==before)throw Error('Logout submitted before confirmation.');
  await check(label+' focuses Cancel and locks background scroll',"document.activeElement.matches('[data-logout-cancel]')&&getComputedStyle(document.body).overflow==='hidden'");
  await check(label+' dialog fits viewport',"(()=>{const r=document.querySelector('.cleargo-logout-dialog').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight})()");
  await key('Tab','Tab',8);
  await check(label+' reverse Tab stays inside confirmation',"document.activeElement.matches('[data-logout-confirm]')");
  await key('Tab','Tab');
  await check(label+' Tab wraps back to Cancel',"document.activeElement.matches('[data-logout-cancel]')");
  const screenshot=await command('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(output,role+'-'+width+'.png'),Buffer.from(screenshot.data,'base64'));
  await evaluate("document.querySelector('[data-logout-cancel]').click()");
  await until(()=>evaluate("!document.body.classList.contains('cleargo-logout-open')"));
  await check(label+' Cancel preserves form and restores focus',"document.querySelector('#draft').value==='Keep this note'&&document.activeElement.matches('.logout-form button')");
  await evaluate("document.querySelector('.logout-form button').click()");await key('Escape','Escape');
  await until(()=>evaluate("!document.body.classList.contains('cleargo-logout-open')"));
  await check(label+' Escape cancels without navigating',"!document.querySelector('.cleargo-logout-dialog').open&&location.pathname.endsWith('/fixture.php')");
  await evaluate("document.querySelector('.logout-form button').click();document.querySelector('[data-logout-cancel]').click();document.querySelector('.logout-form button').click()");
  await pause(60);
  await check(label+' rapid reopen keeps one working dialog',"document.querySelectorAll('.cleargo-logout-dialog').length===1&&document.querySelector('.cleargo-logout-dialog').open&&document.body.classList.contains('cleargo-logout-open')");
  await command('Input.dispatchMouseEvent',{type:'mousePressed',x:4,y:4,button:'left',clickCount:1});await command('Input.dispatchMouseEvent',{type:'mouseReleased',x:4,y:4,button:'left',clickCount:1});
  await until(()=>evaluate("!document.body.classList.contains('cleargo-logout-open')"));
  if(posts.length!==before)throw Error('A cancellation submitted logout.');
  await evaluate("document.querySelector('.logout-form button').click();const b=document.querySelector('[data-logout-confirm]');b.click();b.click()");
  await until(()=>evaluate("!!document.querySelector('#signed-out')"));
  if(posts.length!==before+1||posts.at(-1).fields.csrf_token!==csrf||posts.at(-1).path!==prefix+'/logout.php')throw Error('Confirmation did not submit exactly one original CSRF-protected POST.');
  checks.push({name:label+' confirms exactly one original logout POST',passed:true});
 }
 console.log('All '+checks.length+' logout confirmation browser checks passed.');
}finally{
 fs.writeFileSync(path.join(output,'results.json'),JSON.stringify({checks,postCount:posts.length},null,2));
 if(ws?.readyState===WebSocket.OPEN)await command('Browser.close').catch(()=>{});ws?.close();browser.kill();
 server.closeAllConnections();await new Promise(r=>server.close(r));
}
