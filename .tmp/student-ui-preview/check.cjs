const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');
const assert = require('assert/strict');
const root = process.cwd();
const previewPath = path.resolve(root, '.tmp/student-ui-preview');
const php = spawn('C:/xamppl/php/php.exe', ['-S', '127.0.0.1:8814', '-t', root], { windowsHide: true, stdio: 'ignore' });
const edge = spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=9338', '--user-data-dir=' + path.join(previewPath, 'edge-profile'), 'about:blank'], { windowsHide: true, stdio: 'ignore' });
console.log('Preview process IDs:', php.pid, edge.pid);
edge.on('exit', (code) => console.log('Preview browser exit:', code));
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(fn, ms = 15000) { const start = Date.now(); while (Date.now() - start < ms) { try { const value = await fn(); if (value) return value; } catch {} await pause(100); } throw new Error('Timed out waiting for preview'); }
let ws;
const pending = new Map();
const errors = [];
let serial = 0;
function command(method, params = {}) { const id = ++serial; return new Promise((resolve, reject) => { pending.set(id, {resolve,reject}); ws.send(JSON.stringify({id,method,params})); }); }
async function evaluate(expression) { const result = await command('Runtime.evaluate', {expression,returnByValue:true,awaitPromise:true}); if (result.exceptionDetails) throw new Error(result.exceptionDetails.text + ': ' + result.exceptionDetails.exception?.description); return result.result.value; }
async function navigate(name, hash = 'overview') { await command('Page.navigate', {url:'http://127.0.0.1:8814/.tmp/student-ui-preview/' + name + '.html#' + hash}); await until(() => evaluate("document.readyState === 'complete' && document.body?.classList.contains('dashboard-enhanced')")); await pause(150); }
async function click(selector) { await evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`); await pause(50); }
async function screenshot(name) { const result = await command('Page.captureScreenshot', {format:'png',captureBeyondViewport:false}); fs.writeFileSync(path.join(previewPath,name), Buffer.from(result.data,'base64')); }
(async () => {
  try {
    console.log('Waiting for PHP');
    await until(async () => (await fetch('http://127.0.0.1:8814/.tmp/student-ui-preview/active.html', {signal:AbortSignal.timeout(2000)})).ok);
    console.log('Waiting for browser');
    await until(async () => (await fetch('http://127.0.0.1:9338/json/version', {signal:AbortSignal.timeout(2000)})).ok);
    const target = await (await fetch('http://127.0.0.1:9338/json/new?about:blank',{method:'PUT'})).json();
    ws = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve,reject) => {ws.addEventListener('open',resolve,{once:true}); ws.addEventListener('error',reject,{once:true});});
    ws.addEventListener('message', event => {const message=JSON.parse(event.data); if (message.id) {const item=pending.get(message.id); pending.delete(message.id); if(message.error) item.reject(new Error(message.error.message)); else item.resolve(message.result);} else if (message.method==='Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);});
    await command('Page.enable'); await command('Runtime.enable');
    for (const width of [1440,768,390,320]) {
      await command('Emulation.setDeviceMetricsOverride',{width,height:width>=768?1000:844,deviceScaleFactor:1,mobile:width<768});
      await navigate('active');
      assert.equal(await evaluate("document.querySelector('#overview').hidden"), false);
      assert.equal(await evaluate("document.querySelector('#clearance').hidden"), true);
      for (const view of ['overview','clearance','attendance','account']) {
        await click('[data-dashboard-link="' + view + '"]');
        assert.equal(await evaluate(`document.querySelector('#${view}').hidden`), false);
        assert.equal(await evaluate("document.querySelector('.portal-page-heading').offsetParent !== null"), view === 'overview');
        assert.equal(await evaluate('document.documentElement.scrollWidth <= window.innerWidth'), true, 'Horizontal overflow at '+width+' in '+view);
      }
      assert.equal(await evaluate("getComputedStyle(document.querySelector('.portal-mobile-nav')).display !== 'none'"), width<768);
      assert.equal(await evaluate("getComputedStyle(document.querySelector('.portal-desktop-nav')).display !== 'none'"), width>=768);
      if (width < 768) {
        await click('#notificationButton');
        assert.equal(await evaluate("document.querySelector('#notificationBox').getBoundingClientRect().width <= 340"), true);
        assert.equal(await evaluate("document.querySelector('#notificationBox').getBoundingClientRect().height <= Math.min(420, window.innerHeight * .52) + 1"), true);
        assert.equal(await evaluate("document.querySelector('#notificationBox').getBoundingClientRect().left >= 0"), true);
        await click('#notificationButton');
      }
      assert.equal(await evaluate("document.querySelector('#account .logout-form button').textContent"), 'Log out');
      await click('[data-dashboard-link="clearance"]');
      await click('[data-station-filter="cleared"]');
      assert.equal(await evaluate("Array.from(document.querySelectorAll('[data-station-status]:not([hidden])')).every(card => card.dataset.stationStatus==='APPROVED')"), true);
      await click('[data-station-filter="attention"]');
      assert.equal(await evaluate("document.querySelectorAll('[data-station-status]:not([hidden])').length"), 1);
      await click('.portal-station-open');
      assert.equal(await evaluate("document.querySelector('#requirements-modal-1').hidden"), false);
      assert.equal(await evaluate("document.querySelector('.portal-mobile-nav').inert"), true);
      await command('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
      assert.equal(await evaluate("document.querySelector('#requirements-modal-1').hidden"), true);
      assert.equal(await evaluate("document.activeElement.classList.contains('portal-station-open')"), true);
      assert.equal(await evaluate("document.querySelector('.portal-mobile-nav').inert"), false);
      await click('[data-dashboard-link="overview"]');
      await click('.portal-next-card [data-requirements-modal]');
      assert.equal(await evaluate("document.querySelector('#requirements-modal-1').hidden"), false);
      await click('#requirements-modal-1 [data-close-requirements-modal]');
      await click('[data-dashboard-link="attendance"]');
      await click('[data-open-attendance-scanner]');
      assert.equal(await evaluate("document.querySelector('[data-attendance-scanner-modal]').hidden"), false);
      assert.equal(await evaluate("document.querySelector('main').inert"), true);
      assert.equal(await evaluate("document.querySelector('.portal-scanner-modal__dialog').getBoundingClientRect().width <= window.innerWidth"), true);
      await command('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
      assert.equal(await evaluate("document.querySelector('[data-attendance-scanner-modal]').hidden"), true);
      assert.equal(await evaluate("document.querySelector('main').inert"), false);
      assert.equal(await evaluate("document.querySelector('[data-attendance-scanner-modal] iframe').src"), 'about:blank');
      await click('[data-dashboard-link="account"]');
      await click('[data-open-password-modal]');
      assert.equal(await evaluate("document.querySelector('[data-password-modal]').hidden"), false);
      assert.equal(await evaluate("document.querySelector('main').inert"), true);
      assert.equal(await evaluate("document.querySelector('.portal-password-modal__dialog').getBoundingClientRect().width <= window.innerWidth"), true);
      await command('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
      assert.equal(await evaluate("document.querySelector('[data-password-modal]').hidden"), true);
      assert.equal(await evaluate("document.querySelector('main').inert"), false);
      assert.equal(await evaluate("document.querySelector('[data-password-modal] iframe').src"), 'about:blank');
      await evaluate('document.activeElement.blur()');
      if (width===1440) await screenshot('desktop.png');
      if (width===390) {await screenshot('mobile.png'); await click('[data-dashboard-link="clearance"]'); await click('[data-station-filter="all"]'); await screenshot('mobile-clearance.png');}
      console.log('Navigation, filters, modal and overflow checks passed at '+width+'px.');
    }
    await navigate('active','account');
    await evaluate("document.querySelector('[data-period-select]').value='1'; document.querySelector('[data-period-select]').dispatchEvent(new Event('change', {bubbles:true}));");
    await until(() => evaluate("window.location.search.includes('period=1') && document.readyState==='complete'"));
    assert.equal(await evaluate('window.location.hash'),'#account');
    await navigate('redirect','clearance');
    assert.equal(await evaluate("document.querySelector('#requirements-modal-1').hidden"),false);
    await click('#requirements-modal-1 [data-close-requirements-modal]');
    await navigate('history','clearance');
    assert.equal(await evaluate("document.querySelector('#clearance').hidden"), false);
    assert.equal(await evaluate("document.querySelectorAll('.requirements-modal form[method=post]').length"),0);
    assert.equal(await evaluate("document.querySelector('[href=\"/student/attendance_scanner.php\"]')"),null);
    await click('.portal-station-open');
    assert.equal(await evaluate("document.querySelector('#requirements-modal-1').hidden"),false);
    await click('#requirements-modal-1 [data-close-requirements-modal]');
    await navigate('complete');
    assert.equal(await evaluate("document.querySelector('.portal-next-card h2').textContent"),"You're good to go!");
    await click('[data-dashboard-link="clearance"]'); await click('[data-station-filter="attention"]');
    assert.equal(await evaluate("document.querySelector('[data-filter-empty]').hidden"),false);
    await navigate('empty');
    assert.equal(await evaluate('document.documentElement.scrollWidth <= window.innerWidth'),true);
    assert.equal(errors.length,0,errors.join('\n'));
    console.log('Historical, completed and empty dashboards passed. No browser JavaScript errors.');
    await command('Browser.close').catch(()=>{});
  } finally {
    ws?.close(); php.kill(); edge.kill();
  }
})().catch(error=>{console.error(error);process.exitCode=1;});
