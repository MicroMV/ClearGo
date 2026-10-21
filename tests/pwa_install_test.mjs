import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';

const source = fs.readFileSync(new URL('../assets/js/pwa.js', import.meta.url), 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));
function harness(options = {}) {
    const events = new Map(), mediaEvents = new Map();
    const controls = Array.from({length:options.count ?? 1}, () => {
        const listeners = new Map();
        const button = {hidden:false,disabled:false,addEventListener:(name,fn)=>listeners.set(name,fn),click:()=>listeners.get('click')?.()};
        const help = {textContent:''}, status = {textContent:''};
        return {hidden:true,button,help,status,querySelector:selector=>({'[data-pwa-install-button]':button,'[data-pwa-install-help]':help,'[data-pwa-install-status]':status})[selector]};
    });
    const media = {matches:!!options.standalone,addEventListener:(name,fn)=>mediaEvents.set(name,fn)};
    const context = {navigator:{userAgent:options.ios?'iPhone':'Desktop',platform:options.ipad?'MacIntel':'Win32',maxTouchPoints:options.ipad?5:0,standalone:!!options.iosStandalone},
        document:{querySelectorAll:()=>controls},isSecureContext:!options.insecure,matchMedia:()=>media,addEventListener:(name,fn)=>events.set(name,fn)};
    context.window=context;context.self=context;context.top=options.embedded?{}:context;
    vm.runInNewContext(source,context,{filename:'pwa.js'});
    return {controls,events,emit:(name,event={})=>events.get(name)?.(event),display:matches=>mediaEvents.get('change')?.({matches})};
}
function promptEvent(choice = Promise.resolve({outcome:'dismissed'})) {
    let calls=0,prevented=false;
    return {event:{preventDefault:()=>{prevented=true;},prompt:async()=>{calls++;},userChoice:choice},calls:()=>calls,prevented:()=>prevented};
}

test('installation stays hidden until the browser offers a prompt', () => {
    const h=harness(),p=promptEvent();
    assert.equal(h.controls[0].hidden,true);
    h.emit('beforeinstallprompt',p.event);
    assert.equal(p.prevented(),true);assert.equal(h.controls[0].hidden,false);assert.equal(h.controls[0].button.hidden,false);
});
test('accepted installation prompts once even with rapid clicks on multiple controls', async () => {
    let resolve;const choice=new Promise(r=>{resolve=r;}),h=harness({count:2}),p=promptEvent(choice);
    h.emit('beforeinstallprompt',p.event);h.controls[0].button.click();h.controls[1].button.click();
    assert.equal(p.calls(),1);assert.equal(h.controls[1].button.disabled,true);
    resolve({outcome:'accepted'});await flush();
    assert.ok(h.controls.every(c=>c.hidden));
});
test('a dismissed prompt is not reused and a fresh browser offer can be used', async () => {
    const h=harness(),p=promptEvent();h.emit('beforeinstallprompt',p.event);
    await h.controls[0].button.click();await h.controls[0].button.click();
    assert.equal(p.calls(),1);assert.equal(h.controls[0].hidden,true);
    const next=promptEvent();h.emit('beforeinstallprompt',next.event);await h.controls[0].button.click();assert.equal(next.calls(),1);
});
test('failed prompt shows browser-menu guidance without reusing a consumed event', async () => {
    const h=harness(),p=promptEvent();p.event.prompt=async()=>{throw Error('Browser rejected prompt');};
    h.emit('beforeinstallprompt',p.event);await h.controls[0].button.click();
    assert.equal(h.controls[0].hidden,false);assert.equal(h.controls[0].button.hidden,true);
    assert.match(h.controls[0].status.textContent,/browser menu/);
    const next=promptEvent();h.emit('beforeinstallprompt',next.event);
    assert.equal(h.controls[0].status.textContent,'');assert.equal(h.controls[0].button.hidden,false);
});
test('iPhone and desktop-mode iPad show manual Home Screen instructions', () => {
    for(const option of [{ios:true},{ipad:true}]){
        const h=harness(option);assert.equal(h.controls[0].hidden,false);assert.equal(h.controls[0].button.hidden,true);assert.match(h.controls[0].help.textContent,/Share menu.*Add to Home Screen/);
    }
});
test('installed and standalone apps hide installation controls', () => {
    for(const option of [{standalone:true},{ios:true,iosStandalone:true}]){
        const h=harness(option),p=promptEvent();h.emit('beforeinstallprompt',p.event);assert.equal(h.controls[0].hidden,true);assert.equal(p.prevented(),false);
    }
    const h=harness(),p=promptEvent();h.emit('beforeinstallprompt',p.event);h.emit('appinstalled');assert.equal(h.controls[0].hidden,true);
});
test('changing to standalone hides the controls', () => {
    const h=harness(),p=promptEvent();h.emit('beforeinstallprompt',p.event);h.display(true);assert.equal(h.controls[0].hidden,true);
});
test('embedded, insecure and control-free pages do not suppress browser installation UI', () => {
    for(const option of [{embedded:true},{insecure:true},{count:0}]){
        const h=harness(option);assert.equal(h.events.size,0);
    }
});
test('manifest launch identity and icon URLs remain inside each deployment path', () => {
    const manifest=JSON.parse(fs.readFileSync(new URL('../manifest.webmanifest',import.meta.url),'utf8'));
    for(const prefix of ['/','/ClearGo/','/school/clearance/']){
        const url='https://school.example'+prefix+'manifest.webmanifest';
        // Without an explicit id, browsers use the resolved start_url as the app identity.
        assert.equal(new URL(manifest.id ?? manifest.start_url,url).pathname,prefix+'login.php');
        assert.equal(new URL(manifest.scope,url).pathname,prefix);
        assert.equal(new URL(manifest.start_url,url).pathname,prefix+'login.php');
        for(const icon of manifest.icons){
            assert.equal(new URL(icon.src,url).pathname.startsWith(prefix+'assets/icons/'),true);
            const png=fs.readFileSync(new URL('../'+icon.src,import.meta.url));
            assert.equal(png.subarray(1,4).toString(),'PNG');
            assert.equal(png.readUInt32BE(16)+'x'+png.readUInt32BE(20),icon.sizes);
        }
    }
});
