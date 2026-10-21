import fs from 'node:fs';
import path from 'node:path';
import http from 'node:http';
import {spawn} from 'node:child_process';
// Requires the local Apache application at http://localhost/ClearGo/.
// Uses anonymous GET requests only; generated browser artifacts stay in .tmp.
const here=path.resolve(import.meta.dirname,'../.tmp/pwa-check');
fs.mkdirSync(here,{recursive:true});
// A read-only proxy lets the audit disconnect both page and worker network access.
const server=http.createServer(async(req,res)=>{
 if(req.method!=='GET'){res.writeHead(405);res.end();return;}
 try{const upstream=await fetch('http://localhost'+req.url,{redirect:'manual'});res.writeHead(upstream.status,Object.fromEntries([...upstream.headers].filter(([key])=>['content-type','cache-control','location'].includes(key))));res.end(Buffer.from(await upstream.arrayBuffer()));}
 catch{res.writeHead(502);res.end();}
});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
const proxyPort=server.address().port,base='http://127.0.0.1:'+proxyPort+'/ClearGo/';
const profile=path.join(here,'profile-'+Date.now());
const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-address=127.0.0.1','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
let ws,id=0;const pending=new Map(),result={base,observations:{},checks:[]};
const pause=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn){for(let n=0;n<120;n++){if(await fn())return;await pause(100);}throw Error('Browser condition timed out');}
function command(method,params={}){const key=++id;return new Promise((resolve,reject)=>{const timer=setTimeout(()=>{pending.delete(key);reject(Error(method+' timed out'));},15000);pending.set(key,{resolve:v=>{clearTimeout(timer);resolve(v);},reject});ws.send(JSON.stringify({id:key,method,params}));});}
async function evaluate(expression){const r=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;}
function check(name,passed){result.checks.push({name,passed});console.log((passed?'PASS: ':'FAIL: ')+name);if(!passed)throw Error(name);}
try{
 await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')));
 const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
 const target=await(await fetch('http://127.0.0.1:'+port+'/json/new?about:blank',{method:'PUT'})).json();
 ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise(resolve=>ws.addEventListener('open',resolve,{once:true}));
 ws.addEventListener('message',event=>{const m=JSON.parse(event.data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);if(p)m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}});
 await command('Page.enable');await command('Runtime.enable');await command('Network.enable');
 await command('Page.navigate',{url:base+'login.php'});
 await until(()=>evaluate("document.readyState==='complete'&&!!document.querySelector('[name=email]')"));
 await until(()=>evaluate('!!navigator.serviceWorker.controller'));
 result.observations.online=await evaluate(`(async()=>({url:location.href,secureContext:isSecureContext,manifest:document.querySelector('link[rel=manifest]')?.href||null,appleIcon:document.querySelector('link[rel="apple-touch-icon"]')?.href||null,theme:document.querySelector('meta[name="theme-color"]')?.content||null,worker:(await navigator.serviceWorker.ready).active.scriptURL,scope:(await navigator.serviceWorker.ready).scope,caches:await Promise.all((await caches.keys()).map(async name=>({name,urls:(await(await caches.open(name)).keys()).map(r=>r.url)})))}))()`);
 result.observations.manifest=await command('Page.getAppManifest');
 result.observations.installability=await command('Page.getInstallabilityErrors').catch(e=>({unsupported:e.message}));
 const loginHtml=await(await fetch('http://localhost/ClearGo/login.php')).text();
 check('Character encoding is declared before the shared head assets',loginHtml.indexOf('<meta charset="utf-8">')>0&&loginHtml.indexOf('<meta charset="utf-8">')<1024&&loginHtml.indexOf('<meta charset="utf-8">')<loginHtml.indexOf('rel="manifest"'));
 check('Service worker controls the login page',!!result.observations.online.worker);
 check('Manifest linked in live HTML',!!result.observations.online.manifest);
 check('Browser reports no installation eligibility errors',result.observations.installability.installabilityErrors?.length===0);
 const manifestResponse=await fetch('http://localhost/ClearGo/manifest.webmanifest');
 const manifest=await manifestResponse.json();
 result.observations.manifestContentType=manifestResponse.headers.get('content-type');
 check('Apache serves manifest with its registered content type',manifestResponse.ok&&result.observations.manifestContentType.includes('application/manifest+json'));
 const manifestUrl=result.observations.online.manifest;
 check('Manifest launches the role-aware login within app scope',new URL(manifest.start_url,manifestUrl).href===base+'login.php'&&new URL(manifest.scope,manifestUrl).href===base&&result.observations.manifest.manifest.id===base+'login.php'&&manifest.display==='standalone');
 const icons=await evaluate(`Promise.all(${JSON.stringify(manifest.icons)}.map(icon=>new Promise(resolve=>{const image=new Image();image.onload=()=>resolve({sizes:icon.sizes,actual:image.naturalWidth+'x'+image.naturalHeight});image.onerror=()=>resolve({sizes:icon.sizes,actual:null});image.src=new URL(icon.src,${JSON.stringify(manifestUrl)}).href;})))`);
 result.observations.icons=icons;
 check('All declared app icons load at their declared dimensions',icons.length===3&&icons.every(icon=>icon.sizes===icon.actual));
 check('Apple touch icon and theme are linked',!!result.observations.online.appleIcon&&result.observations.online.theme==='#0b2545');
 // Native OS installation is not performed. Simulate only the prompt event to exercise UI layout/clicks.
 await evaluate(`window.__pwaCalls=0;window.__offerPwa=()=>{const offer=new Event('beforeinstallprompt',{cancelable:true});offer.prompt=()=>{window.__pwaCalls++;return Promise.resolve();};offer.userChoice=Promise.resolve({outcome:'dismissed'});window.dispatchEvent(offer);};window.__offerPwa()`);
 for(const width of [1280,390,320]){
     await command('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<700});
     check(width+'px install card and button fit the screen',await evaluate(`(()=>{const c=document.querySelector('[data-pwa-install]'),b=c.querySelector('button').getBoundingClientRect(),r=c.getBoundingClientRect();return !c.hidden&&document.documentElement.scrollWidth<=innerWidth&&b.width>0&&b.height>=44&&b.left>=r.left&&b.right<=r.right&&r.right<=innerWidth;})()`));
     const shot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(here,'install-'+width+'.png'),Buffer.from(shot.data,'base64'));
 }
 await evaluate(`document.querySelector('[data-pwa-install-button]').click();document.querySelector('[data-pwa-install-button]').click()`);
 await until(()=>evaluate("document.querySelector('[data-pwa-install]').hidden"));
 check('Dismissed install prompt is used only once',await evaluate('window.__pwaCalls===1'));
 await evaluate(`window.__offerPwa();window.dispatchEvent(new Event('appinstalled'))`);
 check('Installed apps hide the install control',await evaluate("document.querySelector('[data-pwa-install]').hidden"));
 check('Service worker scope covers ClearGo',result.observations.online.scope===base);
 check('Only public offline page and logo are cached',result.observations.online.caches.length===1&&result.observations.online.caches[0].urls.length===2&&result.observations.online.caches[0].urls.every(u=>u===base+'errors/offline.html'||u===base+'assets/the-lewis-college-logo.png'));
 const closed=new Promise(resolve=>server.close(resolve));server.closeAllConnections();await closed;
 const post=await evaluate(`fetch(${JSON.stringify(base+'auth/login_process.php')},{method:'POST',body:'qa=offline-only'}).then(r=>({networkFailed:false,status:r.status})).catch(()=>({networkFailed:true}))`);
 result.observations.offlinePost=post;
 check('Offline POST is rejected instead of queued or replaced with cached success',post.networkFailed===true);
 await command('Page.navigate',{url:base+'student/dashboard.php?qa_offline=1'});
 await until(()=>evaluate("location.pathname==='/ClearGo/student/dashboard.php'&&document.readyState==='complete'&&!!document.querySelector('meta[name=cleargo-base]')"));
 result.observations.offline=await evaluate(`({title:document.title,base:document.querySelector('meta[name="cleargo-base"]')?.content,logo:document.querySelector('.connection-brand-logo')?.getAttribute('src'),heading:document.querySelector('h1')?.textContent,hasLoginForm:!!document.querySelector('[name=password]')})`);
 check('Offline navigation displays the public fallback with resolved paths',result.observations.offline.base==='/ClearGo/'&&result.observations.offline.logo==='/ClearGo/assets/the-lewis-college-logo.png'&&result.observations.offline.heading==='ClearGo is temporarily unavailable'&&!result.observations.offline.hasLoginForm);
 result.observations.offlineLogo=await evaluate(`fetch(${JSON.stringify(base+'assets/the-lewis-college-logo.png')}).then(async r=>({ok:r.ok,bytes:(await r.blob()).size})).catch(()=>({ok:false}))`);
 check('College logo loads while offline',result.observations.offlineLogo.ok&&result.observations.offlineLogo.bytes>0);
 await new Promise(resolve=>server.listen(proxyPort,'127.0.0.1',resolve));
 await evaluate("document.querySelector('#connection-retry').click()");
 await until(()=>evaluate("document.readyState==='complete'&&!!document.querySelector('[name=email]')"));
 check('Retry recovers to login without a stale authenticated page',true);
 result.observations.finalUrl=await evaluate('location.href');
 console.log('All '+result.checks.length+' PWA browser checks passed.');
}catch(error){result.error=error.stack;console.error(error);process.exitCode=1;}
finally{fs.writeFileSync(path.join(here,'results.json'),JSON.stringify(result,null,2));if(ws?.readyState===WebSocket.OPEN){await command('Browser.close').catch(()=>{});ws.close();}browser.kill();server.close();server.closeAllConnections();}
