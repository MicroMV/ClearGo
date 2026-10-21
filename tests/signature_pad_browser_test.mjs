// Run after CCS_COMPLETION_PREVIEW=1 php tests/ccs_completion_workbook_test.php.
// Serves a synthetic profile response and local assets; no account or database mutations.
import fs from 'node:fs';
import path from 'node:path';
import http from 'node:http';
import {spawn} from 'node:child_process';

const root = path.resolve(import.meta.dirname, '..');
const snapshot = path.join(root, '.tmp/ccs-dean-check/profile-feedback.html');
const output = path.join(root, '.tmp/signature-pad-check');
const browserProfile = path.join(output, 'browser-profile-' + Date.now());
const checks = [], errors = [];
fs.mkdirSync(output, {recursive: true});
const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    if (url.pathname === '/auth/profile.php') {
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        const page = url.searchParams.has('edit') ? path.join(root,'.tmp/ccs-dean-check/profile-signature-edit.html')
            : (url.searchParams.has('locked') ? path.join(root,'.tmp/ccs-dean-check/profile-signature-locked.html')
            : (url.searchParams.has('recovery') ? path.join(root,'.tmp/ccs-dean-check/profile-signature-recovery.html')
            : (url.searchParams.has('saved') ? path.join(root,'.tmp/ccs-dean-check/profile-signatures.html') : snapshot)));
        response.end(fs.readFileSync(page, 'utf8')
            .replace(/<script\b[^>]*data-connection-status[^>]*><\/script>/g, '')
            .replace(/data-notification-endpoint="[^"]*"/g, 'data-notification-endpoint=""'));
        return;
    }
    if (url.pathname === '/auth/signature_image.php' && /^\d+$/.test(url.searchParams.get('id') || '')) {
        const image = path.join(root,'.tmp/ccs-dean-check/signature-' + url.searchParams.get('id') + '.png');
        if (fs.existsSync(image)) { response.setHeader('Content-Type','image/png'); response.end(fs.readFileSync(image)); return; }
    }
    const file = path.resolve(root, '.' + decodeURIComponent(url.pathname));
    if (!url.pathname.startsWith('/assets/') || !file.startsWith(path.join(root, 'assets') + path.sep) || !fs.existsSync(file)) {
        response.writeHead(404); response.end(); return;
    }
    response.setHeader('Content-Type', {'.js': 'application/javascript', '.css': 'text/css', '.png': 'image/png'}[path.extname(file)] || 'application/octet-stream');
    response.end(fs.readFileSync(file));
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const base = 'http://127.0.0.1:' + server.address().port;
const browserPath = process.env.EDGE_BINARY || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const browser = spawn(browserPath, ['--headless=new', '--disable-gpu', '--no-first-run',
    '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=0',
    '--user-data-dir=' + browserProfile, 'about:blank'], {windowsHide: true, stdio: 'ignore'});
let ws, serial = 0;
const pending = new Map();
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(action) {
    for (let attempt = 0; attempt < 100; attempt++) {
        try { const result = await action(); if (result) return result; } catch {}
        await pause(100);
    }
    throw Error('Browser test timed out.');
}
function command(method, params = {}) {
    const id = ++serial;
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => { pending.delete(id); reject(Error(method + ' timed out.')); }, 10000);
        pending.set(id, {resolve: value => { clearTimeout(timer); resolve(value); }, reject});
        ws.send(JSON.stringify({id, method, params}));
    });
}
async function evaluate(expression) {
    const result = await command('Runtime.evaluate', {expression, returnByValue: true});
    if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
    return result.result.value;
}
async function check(name, expression) {
    const passed = await evaluate(expression);
    checks.push({name, passed: passed === true});
    if (passed !== true) {
        const screenshot = await command('Page.captureScreenshot',{format:'png'});
        fs.writeFileSync(path.join(output,'failure.png'),Buffer.from(screenshot.data,'base64'));
        const detail = await evaluate("(()=>{const r=document.querySelector('canvas')?.getBoundingClientRect();return {events:window.testPointerEvents,status:document.querySelector('[data-signature-status]')?.textContent,bounds:r?.toJSON(),hit:r?document.elementFromPoint(r.x+30,r.y+60)?.outerHTML.slice(0,200):null}})()");
        throw Error(name+' '+JSON.stringify(detail));
    }
}
try {
    const portFile = path.join(browserProfile, 'DevToolsActivePort');
    const port = await until(() => fs.existsSync(portFile) && fs.readFileSync(portFile, 'utf8').split('\n')[0]);
    const target = await (await fetch('http://127.0.0.1:' + port + '/json/new?about:blank', {method: 'PUT'})).json();
    ws = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { ws.addEventListener('open', resolve, {once: true}); ws.addEventListener('error', reject, {once: true}); });
    ws.addEventListener('message', event => {
        const message = JSON.parse(event.data);
        if (message.id) {
            const item = pending.get(message.id); pending.delete(message.id);
            if (item) message.error ? item.reject(Error(message.error.message)) : item.resolve(message.result);
        } else if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text);
    });
    await command('Runtime.enable');
    await command('Page.enable');
    for (const width of [1920, 1280, 390, 320]) {
        await command('Emulation.setDeviceMetricsOverride', {width, height: 1100, deviceScaleFactor: 1, mobile: width < 700});
        await command('Emulation.setTouchEmulationEnabled', {enabled: width < 700});
        await command('Page.navigate', {url: base + '/auth/profile.php'});
        await until(() => evaluate("document.readyState==='complete' && !!document.querySelector('[data-signature-form]')"));
        await check(width + 'px action feedback pops up and stays in the notification bell', "document.querySelector('.notification-toast')?.textContent.includes('Signature saved.') && !document.querySelector('[data-flash-message]') && document.querySelector('.notification-menu').textContent.includes('Signature saved.')");
        await evaluate("document.querySelector('.notification-toast button').click()");
        await until(()=>evaluate("!document.querySelector('.notification-toast')"));
        await check(width + 'px dismissed popup remains in the bell',"document.querySelector('.notification-menu').textContent.includes('Signature saved.')");
        await evaluate("document.querySelector('.notification-toggle').click()");
        await check(width + 'px notification bell opens saved feedback', "!document.querySelector('.notification-menu').hidden && document.querySelector('.notification-menu').textContent.includes('Signature saved.')");
        await evaluate("document.querySelector('.notification-toggle').click()");
        await evaluate("document.querySelector('canvas').scrollIntoView({block:'center'}); document.querySelector('[name=printed_name]').value='Synthetic Signer'; document.querySelector('[name=position_title]').value='Dean'; window.savedDrawing=null; document.querySelector('[data-signature-form]').addEventListener('submit', e=>{e.preventDefault(); window.savedDrawing=document.querySelector('[data-signature-drawing]').value;});");
        await check(width + 'px profile fits viewport', 'document.documentElement.scrollWidth<=innerWidth');
        await check(width + 'px no signature file upload', "!document.querySelector('[name=signature_image]')");
        await check(width + 'px blank save disabled', "document.querySelector('[data-signature-save]').disabled");
        await evaluate("window.testPointerEvents=[];['pointerdown','pointermove','pointerup','pointercancel','gotpointercapture','lostpointercapture'].forEach(name=>document.querySelector('canvas').addEventListener(name,event=>testPointerEvents.push({type:event.type,x:event.clientX,y:event.clientY,id:event.pointerId,buttons:event.buttons}),true));");
        await pause(150); // Let the browser finish scrolling and repaint before starting a stroke.
        const bounds = await evaluate("(()=>{const r=document.querySelector('canvas').getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height};})()");
        if (width >= 700) {
            await command('Input.dispatchMouseEvent', {type: 'mousePressed', x: bounds.x + 30, y: bounds.y + 60, button: 'left', buttons: 1, clickCount: 1});
            for (let step = 1; step <= 8; step++) { await command('Input.dispatchMouseEvent', {type: 'mouseMoved', x: bounds.x + 30 + step * 12, y: bounds.y + 60 + Math.sin(step) * 20, buttons: 1}); await pause(25); }
            await command('Input.dispatchMouseEvent', {type: 'mouseReleased', x: bounds.x + 126, y: bounds.y + 60, button: 'left', buttons: 0, clickCount: 1});
        } else {
            await command('Input.dispatchTouchEvent', {type: 'touchStart', touchPoints: [{x: bounds.x + 25, y: bounds.y + 50}]});
            for (let step = 1; step <= 8; step++) { await command('Input.dispatchTouchEvent', {type: 'touchMove', touchPoints: [{x: bounds.x + 25 + step * 12, y: bounds.y + 50 + Math.sin(step) * 20}]}); await pause(25); }
            await command('Input.dispatchTouchEvent', {type: 'touchEnd', touchPoints: []});
        }
        await check(width + 'px drawing enables save', "!document.querySelector('[data-signature-save]').disabled");
        await evaluate("document.querySelector('[data-signature-form]').requestSubmit()");
        await check(width + 'px drawing submitted as normalized strokes', '(()=>{const d=JSON.parse(savedDrawing),s=d.strokes;return d.aspect_ratio>=1 && d.aspect_ratio<=8 && s.length===1 && s[0].length>2 && s[0].every(p=>p.every(n=>n>=0&&n<=1));})()');
        await command('Emulation.setDeviceMetricsOverride', {width: width + 10, height: 1100, deviceScaleFactor: 1, mobile: width < 700});
        await evaluate("document.querySelector('[data-signature-form]').requestSubmit()");
        await check(width + 'px resize preserves strokes', 'JSON.parse(savedDrawing).strokes[0].length>2');
        await evaluate("document.querySelector('[data-signature-undo]').click()");
        await check(width + 'px undo removes stroke and disables save', "document.querySelector('[data-signature-save]').disabled && document.querySelector('[data-signature-drawing]').value===''");
        // A second drawing verifies Clear after Undo on each input type.
        await evaluate("(()=>{const c=document.querySelector('canvas'); c.setPointerCapture=()=>{}; for(const [type,x] of [['pointerdown',30],['pointermove',110],['pointerup',110]]) {const r=c.getBoundingClientRect();c.dispatchEvent(new PointerEvent(type,{pointerId:91,button:0,isPrimary:true,clientX:r.x+x,clientY:r.y+60}));}document.querySelector('[data-signature-clear]').click();})()");
        await check(width + 'px clear resets drawing', "document.querySelector('[data-signature-save]').disabled && document.querySelector('[data-signature-undo]').disabled");
        const screenshot = await command('Page.captureScreenshot', {format: 'png'});
        fs.writeFileSync(path.join(output, 'profile-' + width + '.png'), Buffer.from(screenshot.data, 'base64'));
    }
    for (const width of [1920,1280,390,320]) {
        await command('Emulation.setDeviceMetricsOverride', {width, height: 1100, deviceScaleFactor: 1, mobile: width < 700});
        await command('Page.navigate',{url:base + '/auth/profile.php?saved=1'});
        await until(()=>evaluate("document.readyState==='complete' && document.querySelectorAll('[data-saved-signature]').length===2"));
        await evaluate("document.querySelector('.saved-signatures').scrollIntoView({block:'center'})");
        await check(width + 'px saved signature cards fit viewport','document.documentElement.scrollWidth<=innerWidth');
        await check(width + 'px exactly one selected signature',"document.querySelectorAll('[data-saved-signature].is-selected').length===1");
        await check(width + 'px names and positions visible',"[...document.querySelectorAll('[data-saved-signature]')].every(card=>card.querySelector('h3').textContent.includes('Dean') && card.querySelector('p').textContent==='College Dean')");
        await until(()=>evaluate("[...document.querySelectorAll('.saved-signature-preview img')].every(img=>img.naturalWidth>0)"));
        await check(width + 'px previews load',"[...document.querySelectorAll('.saved-signature-preview img')].every(img=>img.naturalWidth>0)");
        await check(width + 'px signature selection has independent form',"!document.querySelector('form form') && document.querySelector('.saved-signature form').checkValidity() && document.querySelector('[data-signature-save]').disabled");
        await evaluate("window.signatureChoice=null;const choice=document.querySelector('.saved-signature form');choice.addEventListener('submit',event=>{event.preventDefault();window.signatureChoice=Object.fromEntries(new FormData(choice));});choice.querySelector('button').click();");
        await check(width + 'px use button submits selected ID and CSRF',"signatureChoice.action==='select' && Number(signatureChoice.signature_id)>0 && signatureChoice.csrf_token.length>0 && !('signature_drawing' in signatureChoice)");
        const screenshot = await command('Page.captureScreenshot',{format:'png'});
        fs.writeFileSync(path.join(output,'saved-signatures-' + width + '.png'),Buffer.from(screenshot.data,'base64'));
    }
    for (const width of [1280,390,320]) {
        await command('Emulation.setDeviceMetricsOverride', {width,height:1100,deviceScaleFactor:1,mobile:width<700});
        await command('Page.navigate',{url:base+'/auth/profile.php?edit=1&width='+width});
        await until(()=>evaluate("document.readyState==='complete' && document.querySelector('[data-signature-existing]') && !document.querySelector('[data-signature-save]').disabled"));
        await check(width+'px editor fits viewport','document.documentElement.scrollWidth<=innerWidth');
        await check(width+'px editor keeps saved identity',"document.querySelector('[name=printed_name]').value==='Dr. Synthetic Dean' && document.querySelector('[name=position_title]').value==='College Dean'");
        await evaluate("window.editedSignature=null;document.querySelector('[data-signature-form]').addEventListener('submit',event=>{event.preventDefault();window.editedSignature=Object.fromEntries(new FormData(event.target));});document.querySelector('[name=printed_name]').value='Edited Signer';document.querySelector('[data-signature-form]').requestSubmit()");
        await check(width+'px name edit preserves existing drawing',"editedSignature.action==='update' && Number(editedSignature.signature_id)>0 && editedSignature.printed_name==='Edited Signer' && editedSignature.signature_drawing===''");
        await command('Page.navigate',{url:base+'/auth/profile.php?locked=1&width='+width});
        await until(()=>evaluate("document.readyState==='complete' && document.querySelector('.signature-management-note')?.textContent.includes('Ready') && !document.querySelector('[data-signature-form]')"));
        await check(width+'px Ready profile fits viewport','document.documentElement.scrollWidth<=innerWidth');
        await check(width+'px Ready hides all signature mutation controls',"!document.querySelector('.signature-card form') && !document.querySelector('.saved-signature-actions') && document.querySelectorAll('[data-saved-signature]').length===2");
        await command('Page.navigate',{url:base+'/auth/profile.php?recovery=1&width='+width});
        await until(()=>evaluate("document.readyState==='complete' && document.querySelector('.signature-management-note')?.textContent.includes('one-time') && !!document.querySelector('[data-signature-form]')"));
        await check(width+'px recovery profile fits viewport','document.documentElement.scrollWidth<=innerWidth');
        await check(width+'px recovery offers first setup and selection',"!!document.querySelector('[data-signature-form]') && !!document.querySelector('.saved-signature [name=action][value=select]')");
        await check(width+'px recovery keeps edit and delete locked',"!document.querySelector('.saved-signature-actions') && !document.querySelector('[name=action][value=update]') && !document.querySelector('[name=action][value=delete]')");
    }
    if (errors.length) throw Error('Uncaught browser exceptions: ' + errors.join(', '));
    console.log('All ' + checks.length + ' signature drawing browser checks passed.');
} finally {
    fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({checks, errors}, null, 2));
    if (ws?.readyState === WebSocket.OPEN) await command('Browser.close').catch(() => {});
    ws?.close(); browser.kill();
    await new Promise(resolve => server.close(resolve));
}
