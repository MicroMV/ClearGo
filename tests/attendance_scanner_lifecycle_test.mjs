import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';

const source = fs.readFileSync(new URL('../assets/js/attendance-scanner.js', import.meta.url), 'utf8');
const validCode = 'CLEARGO-ATTENDANCE:' + 'A'.repeat(64);
const flush = () => new Promise(resolve => setImmediate(resolve));
function deferred() { let resolve, reject; const promise = new Promise((yes,no) => { resolve=yes; reject=no; }); return {promise,resolve,reject}; }
function stream() {
    const track = {readyState:'live', stop() { this.readyState='ended'; }};
    return {getTracks:()=>[track], track};
}
function harness(options = {}) {
    const elements = new Map(), events = new Map(), timers = new Map();
    const streams = [], detectors = [], scanners = [], log = [];
    let serial=0, submissions=0, requests=0;
    function element(tag = '') {
        const classes = new Set(), listeners = new Map();
        return {tag,hidden:false,disabled:false,textContent:'',value:'',children:[],srcObject:null,
            classList:{add:value=>classes.add(value),remove:value=>classes.delete(value),contains:value=>classes.has(value)},
            addEventListener:(name,fn)=>listeners.set(name,fn),
            click() { if(!this.disabled) listeners.get('click')?.(); },
            dispatchClick:()=>listeners.get('click')?.(),
            replaceChildren(...children) { this.children=children; },
            querySelectorAll() { return this.children.filter(child=>child.tag==='video'); },
            pause() {},submit() { submissions++; },
        };
    }
    for(const id of ['camera','status','retry','scan-form','token']) elements.set(id,element(id));
    const media = {getUserMedia:async()=>{
        requests++;
        const next = options.camera ? await options.camera(requests) : stream();
        streams.push(next); log.push('camera '+requests); return next;
    }};
    class NativeDetector {
        constructor() { this.index=detectors.length; detectors.push(this); }
        static async getSupportedFormats() { return options.formats ?? ['qr_code']; }
        async detect() { return options.detect ? options.detect(this.index) : (options.code ? [{rawValue:options.code}] : []); }
    }
    class CompatibilityScanner {
        constructor() { this.index=scanners.length; scanners.push(this); this.isScanning=false; }
        async start(camera,config,success) {
            assert.equal(camera.facingMode,'environment','Use a camera option supported by html5-qrcode.');
            this.success=success; this.camera=camera; this.config=config;
            if(options.startError) throw options.startError;
            this.stream=await media.getUserMedia();
            const video=element('video');video.srcObject=this.stream;elements.get('camera').replaceChildren(video);
            this.isScanning=true;
            if(options.earlyCode) success(options.earlyCode);
            if(options.startGate) await options.startGate.promise;
        }
        async stop() {
            log.push('stop requested');
            if(options.stopGate) await options.stopGate.promise;
            this.stream.getTracks().forEach(track=>track.stop());this.isScanning=false;log.push('stopped');
        }
    }
    const context={console,Promise,Error,location:{hostname:'localhost'},navigator:{mediaDevices:media},
        document:{getElementById:id=>elements.get(id),createElement:tag=>element(tag)},
        isSecureContext:true,Html5Qrcode:CompatibilityScanner,
        setTimeout:fn=>{const id=++serial;timers.set(id,fn);return id;},clearTimeout:id=>timers.delete(id),
        addEventListener:(name,fn)=>events.set(name,fn),
    };
    if(!options.compatibility) context.BarcodeDetector=NativeDetector;
    context.window=context;
    vm.runInNewContext(source,context,{filename:'attendance-scanner.js'});
    return {elements,streams,detectors,scanners,timers,log,fire:(name,event={})=>events.get(name)?.(event),
        retry:()=>elements.get('retry').dispatchClick(),submissions:()=>submissions,requests:()=>requests,
        active:()=>streams.filter(item=>item.track.readyState==='live').length,
    };
}

test('compatibility startup uses a supported rear-camera option', async () => {
    const h=harness({compatibility:true});await flush();
    assert.equal(h.scanners.length,1);assert.equal(h.active(),1);
    assert.match(h.elements.get('status').textContent,/Camera ready/);
});

test('Retry stops native camera tracks and its timer before creating another stream', async () => {
    const options={code:'https://example.test/not-attendance'},h=harness(options);await flush();
    assert.equal(h.timers.size,1);assert.equal(h.active(),1);
    options.code=null;h.retry();h.retry();await flush();
    assert.equal(h.requests(),2);assert.equal(h.active(),1);assert.equal(h.streams[0].track.readyState,'ended');
    assert.equal(h.timers.size,1);assert.equal(h.submissions(),0);
});

test('Retry waits for compatibility stop and ignores callbacks from the old scanner', async () => {
    const gate=deferred(),options={compatibility:true,stopGate:gate},h=harness(options);await flush();
    h.scanners[0].success('invalid');h.retry();await flush();
    h.retry();h.scanners[0].success(validCode);await flush();
    assert.equal(h.requests(),1);assert.equal(h.submissions(),0);
    gate.resolve();await flush();
    assert.equal(h.requests(),2);assert.equal(h.active(),1);
    assert.ok(h.log.indexOf('stopped')<h.log.indexOf('camera 2'));
});

test('a native detection completed after Retry cannot submit an old result', async () => {
    const late=deferred(),h=harness({detect:index=>index===0?late.promise:[]});await flush();
    h.retry();await flush();late.resolve([{rawValue:validCode}]);await flush();
    assert.equal(h.submissions(),0);assert.equal(h.active(),1);assert.equal(h.timers.size,1);
});

test('rapid retries during a permission prompt create only one camera request', async () => {
    const gate=deferred(),h=harness({camera:()=>gate.promise});await flush();
    h.retry();h.retry();await flush();assert.equal(h.requests(),1);
    gate.resolve(stream());await flush();assert.equal(h.requests(),1);assert.equal(h.active(),1);
});

test('a valid QR submits once after startup and camera teardown finish', async () => {
    const gate=deferred(),h=harness({compatibility:true,earlyCode:validCode,startGate:gate});await flush();
    h.scanners[0].success(validCode);assert.equal(h.submissions(),0);
    gate.resolve();await flush();
    assert.equal(h.submissions(),1);assert.equal(h.active(),0);assert.equal(h.timers.size,0);
    assert.equal(h.elements.get('token').value,'a'.repeat(64));
    h.scanners[0].success(validCode);await flush();assert.equal(h.submissions(),1);
});

test('startup errors are not incorrectly described as permission denial', async () => {
    const h=harness({compatibility:true,startError:new TypeError('Bad scanner configuration')});await flush();
    assert.match(h.elements.get('status').textContent,/Unable to start/);
    assert.doesNotMatch(h.elements.get('status').textContent,/permission|access is blocked/);
    assert.equal(h.elements.get('retry').disabled,false);
});

test('page exit releases a late camera stream and a restored page can start again', async () => {
    const gate=deferred(),h=harness({camera:index=>index===1?gate.promise:stream()});await flush();
    h.fire('pagehide');gate.resolve(stream());await flush();
    assert.equal(h.active(),0);assert.equal(h.submissions(),0);assert.equal(h.timers.size,0);
    h.fire('pageshow',{persisted:true});await flush();assert.equal(h.active(),1);assert.equal(h.requests(),2);
});

test('native detection without QR support uses the compatibility decoder', async () => {
    const h=harness({formats:['ean_13']});await flush();
    assert.equal(h.detectors.length,0);assert.equal(h.scanners.length,1);assert.equal(h.active(),1);
});
