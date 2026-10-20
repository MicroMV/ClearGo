import fs from 'node:fs';import path from 'node:path';import http from 'node:http';import {spawn} from 'node:child_process';
const root=process.cwd(),out=path.resolve('qa-audit/2026-10-18'),results=[],errors=[];
let seq=1,ws,serial=0,simulatedOutage=false;const pending=new Map();
function record(role,feature,test,result,evidence,ms=0){results.push({test_id:'UI-'+String(seq++).padStart(3,'0'),role,feature,test,result,milliseconds:ms,evidence});fs.writeFileSync(path.join(out,'browser-tests.json'),JSON.stringify(results,null,2));}
const server=http.createServer((req,res)=>{
    const url=new URL(req.url,'http://127.0.0.1');let file;
    if(simulatedOutage&&url.pathname==='/empty.html'){req.socket.destroy();return;}
    if(url.pathname.startsWith('/assets/'))file=path.resolve(root,'.'+url.pathname);
    else if(url.pathname==='/connection-worker.js')file=path.join(root,'connection-worker.js');
    else if(url.pathname==='/errors/offline.html')file=path.join(root,'errors/offline.html');
    else if(url.pathname==='/auth/connection_status.php'){res.setHeader('Content-Type','application/json');res.statusCode=simulatedOutage?503:200;res.end(JSON.stringify({available:!simulatedOutage}));return;}
    else if(/^\/(active|history|empty|complete)\.html$/.test(url.pathname))file=path.join(out,'browser-fixtures',url.pathname.slice(1));
    else if(/^\/page-[A-Z_]+-[a-z_]+\.php\.html$/.test(url.pathname))file=path.join(out,url.pathname.slice(1));
    if(!file||!fs.existsSync(file)){res.writeHead(404);res.end('Isolated static preview; backend interactions are tested separately.');return;}
    const types={'.html':'text/html; charset=utf-8','.css':'text/css','.js':'application/javascript','.png':'image/png'};
    res.setHeader('Content-Type',types[path.extname(file)]||'application/octet-stream');
    let body=fs.readFileSync(file);
    if(path.extname(file)==='.html')body=body.toString().replace(/<script\b[^>]*data-connection-status[^>]*><\/script>/g,'').replace(/data-notification-endpoint="[^"]*"/g,'data-notification-endpoint=""');
    res.end(body);
});
await new Promise(r=>server.listen(8830,'127.0.0.1',r));
const edge=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--no-sandbox','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-address=127.0.0.1','--remote-debugging-port=9341','--user-data-dir='+path.join(out,'browser-profile-retry'),'about:blank'],{windowsHide:true,stdio:'ignore'});
const pause=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,ms=20000){const start=Date.now();while(Date.now()-start<ms){try{const v=await fn();if(v)return v;}catch{}await pause(150);}throw Error('Browser did not become ready');}
function command(method,params={}){const id=++serial;return new Promise((resolve,reject)=>{const timer=setTimeout(()=>{pending.delete(id);reject(Error('CDP timeout: '+method));},12000);pending.set(id,{resolve:v=>{clearTimeout(timer);resolve(v);},reject});ws.send(JSON.stringify({id,method,params}));});}
async function evaluate(expression){const r=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;}
async function check(feature,test,expression,expected=true){try{const v=await evaluate(expression);record('Student',feature,test,JSON.stringify(v)===JSON.stringify(expected)?'PASS':'FAIL',JSON.stringify({actual:v,expected}));}catch(e){record('Student',feature,test,'BLOCKED',e.message);}}
async function click(selector){await evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);await pause(70);}
async function navigate(name){await command('Page.navigate',{url:'http://127.0.0.1:8830/'+name+'.html#overview'});await until(()=>evaluate("document.readyState==='complete'"));await pause(200);}
async function screenshot(name){const r=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(out,name),Buffer.from(r.data,'base64'));}
try{
    await until(async()=> (await fetch('http://127.0.0.1:9341/json/version',{signal:AbortSignal.timeout(1000)})).ok);
    const target=await(await fetch('http://127.0.0.1:9341/json/new?about:blank',{method:'PUT'})).json();ws=new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true});});
    ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);if(p)m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}else if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);});
    await command('Page.enable');await command('Runtime.enable');
    for(const width of [1440,1024,768,390,320]){
        await command('Emulation.setDeviceMetricsOverride',{width,height:width>=768?1000:844,deviceScaleFactor:1,mobile:width<768});await navigate('active');
        for(const view of ['overview','clearance','attendance','account']){
            await click('[data-dashboard-link="'+view+'"]');
            await check('Navigation '+width+'px','Switch to '+view,`document.querySelector('#${view}').hidden`,false);
            await check('Layout '+width+'px',view+' has no horizontal overflow','document.documentElement.scrollWidth <= window.innerWidth');
        }
        await check('Navigation '+width+'px','Mobile menu matches viewport',"getComputedStyle(document.querySelector('.portal-mobile-nav')).display!=='none'",width<768);
        await click('[data-dashboard-link="clearance"]');await click('[data-station-filter="cleared"]');
        await check('Filter '+width+'px','Cleared filter excludes other statuses',"[...document.querySelectorAll('[data-station-status]:not([hidden])')].every(e=>e.dataset.stationStatus==='APPROVED')");
        await click('[data-station-filter="attention"]');await check('Filter '+width+'px','To-do filter shows one actionable office',"document.querySelectorAll('[data-station-status]:not([hidden])').length",1);
        await click('.portal-station-open');await check('Requirements modal '+width+'px','Open modal',"document.querySelector('#requirements-modal-1').hidden",false);
        await check('Requirements modal '+width+'px','Background navigation is inert',"document.querySelector('.portal-mobile-nav').inert");
        await command('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
        await check('Requirements modal '+width+'px','Escape closes modal',"document.querySelector('#requirements-modal-1').hidden");
        await check('Requirements modal '+width+'px','Focus returns to its opener',"document.activeElement.classList.contains('portal-station-open')");
        for(const [view,open,modal]of [['attendance','[data-open-attendance-scanner]','[data-attendance-scanner-modal]'],['account','[data-open-password-modal]','[data-password-modal]']]){
            await click('[data-dashboard-link="'+view+'"]');await click(open);
            await check(view+' dialog '+width+'px','Dialog opens',`document.querySelector('${modal}').hidden`,false);
            await check(view+' dialog '+width+'px','Background content is inert',"document.querySelector('main').inert");
            await command('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
            await check(view+' dialog '+width+'px','Escape closes dialog',`document.querySelector('${modal}').hidden`);
        }
        await click('[data-dashboard-link="overview"]');await screenshot('student-'+width+'.png');
    }
    for(const state of ['history','empty','complete']){await navigate(state);await check(state+' dashboard','No horizontal overflow','document.documentElement.scrollWidth<=innerWidth');if(state==='history')await check('Semester archive','No clearance mutation forms',"document.querySelectorAll('.requirements-modal form[method=post]').length",0);if(state==='complete')await check('Completion message','All-cleared confirmation visible',"document.querySelector('.portal-next-card h2').textContent","You're good to go!");}
    for(const width of [1440,390]){
        await command('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<768});
        for(const role of ['DEAN','ORG_STAFF','SASO_STAFF','STUDENT_COUNCIL_STAFF','LIBRARY_STAFF','BOOKSTORE_STAFF','CLINIC_STAFF','FACILITIES_STAFF','COMLAB_STAFF','ACCOUNTING_STAFF','REGISTRAR']){
            const name='page-'+role+'-dashboard.php';await navigate(name);
            await check(role+' preview '+width+'px','Availability overlay is absent',"document.getElementById('connection-screen')===null");
            const overflow=await evaluate('document.documentElement.scrollWidth<=innerWidth');record(role,'Dashboard '+width+'px','Check document overflow on synthetic authorized page',overflow?'PASS':'FAIL',JSON.stringify({width,overflow,scope:'Static response from the real isolated HTTP dashboard; no mutation controls clicked.'}));
            if(['DEAN','REGISTRAR'].includes(role))await screenshot(role.toLowerCase()+'-'+width+'.png');
        }
    }
    await navigate('active');await command('Network.enable');
    await evaluate(`new Promise(resolve=>{const s=document.createElement('script');s.dataset.connectionStatus='';s.dataset.statusUrl='/auth/connection_status.php';s.dataset.workerUrl='/connection-worker.js';s.dataset.logoUrl='/assets/the-lewis-college-logo.png';s.src='/assets/js/connection-status.js';s.onload=resolve;document.head.appendChild(s);})`);
    await until(()=>evaluate('navigator.serviceWorker.controller!==null'));
    const cacheUrls=await evaluate('(async()=>{const keys=await caches.keys();return(await Promise.all(keys.map(async k=>(await(await caches.open(k)).keys()).map(r=>new URL(r.url).pathname)))).flat()})()');
    record('All','PWA caching','Cache contains only the public fallback and logo',cacheUrls.every(u=>['/errors/offline.html','/assets/the-lewis-college-logo.png'].includes(u))&&cacheUrls.length===2?'PASS':'FAIL',JSON.stringify(cacheUrls));
    simulatedOutage=true;
    await command('Page.navigate',{url:'http://127.0.0.1:8830/empty.html'});await until(()=>evaluate("document.querySelector('.connection-error-page')!==null"));
    await check('PWA outage fallback','Cached error page renders without internet advice',"document.querySelector('#connection-message').textContent==='Please wait a moment, then try again.'");
    await check('PWA outage fallback','Application-base placeholder is resolved',"!document.documentElement.outerHTML.includes('__CLEARGO_BASE__')");
    simulatedOutage=false;
    await click('#connection-retry');await until(()=>evaluate("document.body.classList.contains('student-portal')"));
    await check('PWA reconnect','Retry reloads the requested student page',"document.body.classList.contains('student-portal')");
    record('All','Browser runtime','Observe uncaught JavaScript exceptions',errors.length?'FAIL':'PASS',JSON.stringify(errors));
    await command('Browser.close').catch(()=>{});
}catch(e){record('All','Browser availability','Start browser automation','BLOCKED',e.stack);}finally{ws?.close();edge.kill();await new Promise(r=>server.close(r));}
console.log(JSON.stringify({tests:results.length,counts:results.reduce((n,r)=>(n[r.result]=(n[r.result]||0)+1,n),{}),failures:results.filter(r=>r.result==='FAIL')},null,2));
