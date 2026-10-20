// Preview a synthetic response generated with DEBUG_CONSOLE_PREVIEW=1.
import fs from 'node:fs';
import path from 'node:path';
import http from 'node:http';
import {spawn} from 'node:child_process';
const root=path.resolve(import.meta.dirname,'..'),out=path.join(root,'.tmp/debug-console-check');
const profile=path.join(out,'browser-' + Date.now());
const checks=[];
const server=http.createServer((req,res)=>{
    const url=new URL(req.url,'http://localhost');
    if(url.pathname==='/debug/'){res.setHeader('Content-Type','text/html; charset=utf-8');res.end(fs.readFileSync(path.join(out,'screen.html')));return;}
    if(url.pathname==='/assets/css/debug-console.css'){res.setHeader('Content-Type','text/css');res.end(fs.readFileSync(path.join(root,'assets/css/debug-console.css')));return;}
    res.writeHead(404);res.end();
});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-address=127.0.0.1','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(action){for(let i=0;i<100;i++){try{const result=await action();if(result)return result;}catch{}await pause(100);}throw Error('Browser timed out.');}
let ws,serial=0;const pending=new Map();
function command(method,params={}){const id=++serial;return new Promise((resolve,reject)=>{const timer=setTimeout(()=>{pending.delete(id);reject(Error(method+' timeout'));},10000);pending.set(id,{resolve:value=>{clearTimeout(timer);resolve(value);},reject});ws.send(JSON.stringify({id,method,params}));});}
async function evaluate(expression){const result=await command('Runtime.evaluate',{expression,returnByValue:true});if(result.exceptionDetails)throw Error(result.exceptionDetails.text);return result.result.value;}
try{
    const port=await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort'))&&fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0]);
    const target=await(await fetch('http://127.0.0.1:'+port+'/json/new?about:blank',{method:'PUT'})).json();
    ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true});});
    ws.addEventListener('message',event=>{const m=JSON.parse(event.data),p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}});
    await command('Runtime.enable');await command('Page.enable');
    for(const width of [1280,390,320]){
        await command('Emulation.setDeviceMetricsOverride',{width,height:1100,deviceScaleFactor:1,mobile:width<700});
        await command('Page.navigate',{url:'http://127.0.0.1:'+server.address().port+'/debug/'});
        await until(()=>evaluate("document.readyState==='complete'"));
        for(const [name,expression] of [
            ['fits viewport','document.documentElement.scrollWidth<=innerWidth'],
            ['forms independent',"!document.querySelector('form form')"],
            ['reset confirmations required',"[...document.querySelectorAll('.reset-panel input[name=confirmation]')].length===2 && [...document.querySelectorAll('.reset-panel input[name=confirmation]')].every(input=>input.required)"],
            ['actions use POST and CSRF',"[...document.forms].every(form=>form.method==='post' && !!form.querySelector('[name=csrf_token]') && !!form.querySelector('[name=action]'))"],
            ['buttons remain within cards',"[...document.querySelectorAll('button')].every(button=>{const b=button.getBoundingClientRect(),p=button.closest('.panel').getBoundingClientRect();return b.left>=p.left && b.right<=p.right;})"]
        ]){const passed=await evaluate(expression);checks.push({name:width+'px '+name,passed});if(!passed)throw Error(width+'px '+name);}
        const image=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(out,'screen-'+width+'.png'),Buffer.from(image.data,'base64'));
    }
    console.log('All '+checks.length+' debug screen browser checks passed.');
}finally{fs.writeFileSync(path.join(out,'results.json'),JSON.stringify(checks,null,2));if(ws?.readyState===WebSocket.OPEN)await command('Browser.close').catch(()=>{});ws?.close();browser.kill();await new Promise(resolve=>server.close(resolve));}
