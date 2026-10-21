// Uses only synthetic snapshots created by auth_recovery_test.php; no form is submitted.
import fs from 'node:fs';import path from 'node:path';import http from 'node:http';import {spawn} from 'node:child_process';
const root=path.resolve(import.meta.dirname,'..'),temp=path.join(root,'.tmp');
const snapshots=fs.readdirSync(temp).filter(n=>n.startsWith('auth-recovery-check-')).map(n=>path.join(temp,n)).filter(p=>fs.existsSync(path.join(p,'reset.html'))).sort((a,b)=>fs.statSync(b).mtimeMs-fs.statSync(a).mtimeMs)[0];
if(!snapshots)throw Error('Run auth_recovery_test.php first to generate synthetic snapshots.');
const output=path.join(temp,'auth-recovery-browser');fs.mkdirSync(output,{recursive:true});const profile=path.join(output,'browser-'+Date.now()),checks=[],errors=[];
const server=http.createServer((req,res)=>{const url=new URL(req.url,'http://localhost');if(req.method!=='GET'){res.writeHead(405).end();return;}
 const pages={'/login.php':'login.html','/forgot_password.php':'forgot.html','/verify.php':'verify.html','/reset.php':'reset.html'};
 if(pages[url.pathname]){res.setHeader('Content-Type','text/html; charset=utf-8');res.end(fs.readFileSync(path.join(snapshots,pages[url.pathname])));return;}
 if(url.pathname==='/auth/connection_status.php'){res.setHeader('Content-Type','application/json');res.end('{"available":true}');return;}
 const file=path.resolve(root,'.'+decodeURIComponent(url.pathname));if(!url.pathname.startsWith('/assets/')||!file.startsWith(path.join(root,'assets')+path.sep)||!fs.existsSync(file)){res.writeHead(404).end();return;}
 res.setHeader('Content-Type',{'.css':'text/css','.js':'application/javascript','.png':'image/png','.svg':'image/svg+xml'}[path.extname(file)]||'application/octet-stream');res.end(fs.readFileSync(file));
});await new Promise(r=>server.listen(0,'127.0.0.1',r));const base='http://127.0.0.1:'+server.address().port;
const browserLog=fs.openSync(path.join(output,'browser.log'),'a');
const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-address=127.0.0.1','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:['ignore','ignore',browserLog]});
fs.closeSync(browserLog);
let ws,serial=0;const pending=new Map(),pause=ms=>new Promise(r=>setTimeout(r,ms));
function command(method,params={}){const id=++serial;return new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error(method+' timeout')),10000);pending.set(id,{resolve:v=>{clearTimeout(timer);resolve(v);},reject});ws.send(JSON.stringify({id,method,params}));});}
async function evaluate(expression){const v=await command('Runtime.evaluate',{expression,returnByValue:true});if(v.exceptionDetails)throw Error(JSON.stringify(v.exceptionDetails));return v.result.value;}
async function until(action){for(let n=0;n<100;n++){if(await action())return;await pause(100);}throw Error('UI check timed out');}
async function check(name,expression){const passed=await evaluate(expression)===true;checks.push({name,passed});if(!passed)throw Error(name);}
try{
 await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')));const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];const target=await(await fetch('http://127.0.0.1:'+port+'/json/new?about:blank',{method:'PUT'})).json();ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
 ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);if(p)m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}else if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);});
 await command('Runtime.enable');await command('Page.enable');
 for(const width of [1280,390,320]){
  await command('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<700});
  for(const page of ['login','forgot_password','verify','reset']){
   await command('Page.navigate',{url:base+'/'+page+'.php'});await until(()=>evaluate("document.readyState==='complete'&&!!document.querySelector('form')"));await pause(100);
   await check(width+'px '+page+' fits the screen','document.documentElement.scrollWidth<=innerWidth');
   await check(width+'px '+page+' labels every visible field',"[...document.querySelectorAll('input:not([type=hidden])')].every(e=>!!document.querySelector('label[for=\"'+e.id+'\"]'))");
   if(page==='login'){
    await check(width+'px saved sign-in is optional',"!!document.querySelector('#remember-me')&&!document.querySelector('#remember-me').checked");
    await evaluate("document.querySelector('#remember-me').click()");await check(width+'px saved sign-in checkbox toggles',"document.querySelector('#remember-me').checked");
    await check(width+'px checkbox stays beside its label',"(()=>{const c=document.querySelector('#remember-me').getBoundingClientRect(),t=document.querySelector('.remember-option span').getBoundingClientRect();return c.width===18&&Math.abs((c.top+c.height/2)-(t.top+t.height/2))<3})()");
    await check(width+'px recovery starts closed',"!document.querySelector('#password-recovery-modal').open");
    await evaluate("document.querySelector('[data-open-recovery]').click()");
    await check(width+'px recovery opens over login without navigation',"document.querySelector('#password-recovery-modal').matches(':modal')&&location.pathname==='/login.php'&&document.querySelector('.shell')!==null");
    await check(width+'px recovery focuses the email field',"document.activeElement.id==='recovery-email'");
    await command('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await command('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await until(()=>evaluate("!document.querySelector('#password-recovery-modal').open&&!document.body.classList.contains('recovery-open')"));
    await check(width+'px Escape restores focus to Forgot password',"document.activeElement.matches('[data-open-recovery]')&&!document.body.classList.contains('recovery-open')");
    await evaluate("document.querySelector('[data-open-recovery]').click()");
   }else if(page==='forgot_password')await check(width+'px recovery explains student-only access',"document.body.textContent.includes('For students only')&&document.body.textContent.includes('Registrar')");
   else if(page==='verify')await check(width+'px OTP supports numeric keyboard and autofill',"document.querySelector('#recovery-code').inputMode==='numeric'&&document.querySelector('#recovery-code').autocomplete==='one-time-code'&&document.querySelector('#recovery-code').maxLength===6");
   else await check(width+'px reset confirms the new password',"document.querySelectorAll('#password-recovery-modal input[type=password][autocomplete=new-password]').length===2");
   await check(width+'px '+page+' is an accessible modal',"document.querySelector('#password-recovery-modal').matches(':modal')&&document.querySelector('#password-recovery-modal').getAttribute('aria-labelledby')==='recovery-title'");
   await check(width+'px '+page+' modal stays within viewport',"(()=>{const r=document.querySelector('#password-recovery-modal').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight})()");
   await evaluate("document.querySelector('.recovery-back').focus()");
   await command('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
   await command('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
   await check(width+'px '+page+' keyboard focus stays in modal',"document.querySelector('#password-recovery-modal').contains(document.activeElement)");
   const screenshot=await command('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(output,page+'-'+width+'.png'),Buffer.from(screenshot.data,'base64'));
   await evaluate("document.querySelector('.recovery-close').click()");
   await until(()=>evaluate("!document.body.classList.contains('recovery-open')"));
   await check(width+'px '+page+' close returns to sign in',"!document.querySelector('#password-recovery-modal').open&&document.activeElement.matches('[data-open-recovery]')");
  }
 }
 if(errors.length)throw Error(errors.join(', '));console.log('All '+checks.length+' authentication UI browser checks passed.');
}finally{fs.writeFileSync(path.join(output,'results.json'),JSON.stringify({checks,errors},null,2));if(ws?.readyState===WebSocket.OPEN)await command('Browser.close').catch(()=>{});ws?.close();browser.kill();await new Promise(r=>server.close(r));}
