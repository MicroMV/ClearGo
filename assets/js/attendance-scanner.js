const camera = document.getElementById('camera');
const status = document.getElementById('status');
const retry = document.getElementById('retry');
const form = document.getElementById('scan-form');
const token = document.getElementById('token');
let currentSession = null;
let restartTask = null;
let submitted = false;
let leaving = false;

function fail(message) {
    status.textContent = message;
    status.classList.add('error');
    retry.hidden = false;
}
function isCurrent(session) {
    return session === currentSession && session.active && !submitted && !leaving;
}
function stopTracks(stream) {
    stream?.getTracks().forEach(track => { try { track.stop(); } catch (_) { /* Try every track. */ } });
}
function stopSession(session) {
    if (!session) return Promise.resolve();
    session.active = false;
    window.clearTimeout(session.timer);
    if (session.stopping) return session.stopping;
    session.stopping = (async () => {
        // A permission prompt/startup may still be pending. Release its resources before any restart.
        try { await session.started; } catch (_) { /* Startup errors still need cleanup. */ }
        try { if (session.scanner?.isScanning) await session.scanner.stop(); } catch (_) { /* Also release video tracks below. */ }
        stopTracks(session.stream);
        camera.querySelectorAll('video').forEach(video => {
            stopTracks(video.srcObject);
            try { video.pause(); video.srcObject = null; } catch (_) { /* A detached video may already be gone. */ }
        });
    })();
    return session.stopping;
}
function submitCode(value, session) {
    if (!isCurrent(session)) return false;
    const match = String(value || '').match(/^CLEARGO-ATTENDANCE:([a-f0-9]{64})$/i);
    if (!match) { fail('This is not a ClearGo attendance QR code.'); return false; }
    submitted = true;
    retry.hidden = true;
    retry.disabled = true;
    status.classList.remove('error');
    status.textContent = 'QR code read. Recording attendance…';
    stopSession(session).then(() => {
        if (leaving) return;
        token.value = match[1].toLowerCase();
        form.submit();
    });
    return true;
}
async function startCompatibilityScanner(session) {
    if (typeof window.Html5Qrcode !== 'function') {
        const error = new Error('The ClearGo scanner could not load. Check your internet connection and reload this page.');
        error.code = 'SCANNER_UNAVAILABLE';
        throw error;
    }
    camera.classList.add('scanner-camera--compatibility');
    session.scanner = new Html5Qrcode('camera');
    // html5-qrcode accepts a string or an `exact` constraint here, rather than an `ideal` constraint.
    await session.scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 250, height: 250 } },
        value => submitCode(value, session), () => {});
}
function showCameraError(error) {
    if (!window.isSecureContext && location.hostname !== 'localhost') {
        fail('Camera access requires HTTPS. Open ClearGo using https://, or use localhost on this device.');
    } else if (error?.code === 'SCANNER_UNAVAILABLE') {
        fail(error.message);
    } else if (error?.name === 'NotFoundError' || /NotFoundError|DevicesNotFoundError/.test(String(error))) {
        fail('No camera was found on this device. Connect a camera and try again.');
    } else if (['NotAllowedError', 'PermissionDeniedError', 'SecurityError'].includes(error?.name)
        || /NotAllowedError|PermissionDeniedError|permission denied/i.test(String(error))) {
        fail('Camera access is blocked. In your browser site settings, allow Camera for ClearGo, then select “Enable camera and retry”.');
    } else if (['NotReadableError', 'TrackStartError'].includes(error?.name)) {
        fail('The camera could not start. Close other apps using the camera, then retry.');
    } else {
        fail('Unable to start the camera. Please retry. If this continues, try another browser.');
    }
}
async function startSession(session) {
    if (!window.isSecureContext && location.hostname !== 'localhost') throw new Error('HTTPS required');
    let detector = null;
    if (typeof window.BarcodeDetector === 'function') {
        try {
            const formats = typeof BarcodeDetector.getSupportedFormats === 'function' ? await BarcodeDetector.getSupportedFormats() : ['qr_code'];
            if (formats.includes('qr_code')) detector = new BarcodeDetector({ formats: ['qr_code'] });
        } catch (_) { /* Use the compatibility decoder when native QR detection is unavailable. */ }
    }
    if (!isCurrent(session)) return;
    if (!detector) { await startCompatibilityScanner(session); return; }
    session.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
    if (!isCurrent(session)) { stopTracks(session.stream); return; }
    const video = document.createElement('video');
    video.autoplay = true; video.playsInline = true; video.muted = true;
    camera.replaceChildren(video);
    video.srcObject = session.stream;
    const scan = async () => {
        if (!isCurrent(session)) return;
        try {
            const codes = await detector.detect(video);
            // Ignore a detection that completed after Retry, page exit or submission.
            if (!isCurrent(session)) return;
            if (codes.length && submitCode(codes[0].rawValue, session)) return;
        } catch (_) { /* Keep scanning while the camera initializes. */ }
        if (isCurrent(session)) session.timer = window.setTimeout(scan, 250);
    };
    void scan();
}
function startScanner() {
    if (restartTask || submitted || leaving) return restartTask;
    restartTask = Promise.resolve().then(async () => {
        retry.disabled = true;
        retry.hidden = true;
        status.classList.remove('error');
        status.textContent = 'Starting camera…';
        await stopSession(currentSession);
        if (leaving || submitted) return;
        camera.replaceChildren();
        camera.classList.remove('scanner-camera--compatibility');
        const session = { active: true, timer: null, started: null, stopping: null, scanner: null, stream: null };
        currentSession = session;
        session.started = startSession(session);
        try {
            await session.started;
            if (isCurrent(session) && !status.classList.contains('error')) status.textContent = 'Camera ready. Scan the event QR code.';
        } catch (error) {
            await stopSession(session);
            if (session === currentSession && !leaving && !submitted) showCameraError(error);
        }
    }).finally(() => {
        restartTask = null;
        if (!submitted && !leaving) retry.disabled = false;
    });
    return restartTask;
}
retry.addEventListener('click', startScanner);
window.addEventListener('pagehide', () => {
    leaving = true;
    void stopSession(currentSession);
});
window.addEventListener('pageshow', event => {
    if (!event.persisted) return;
    leaving = false;
    submitted = false;
    void startScanner();
});
startScanner();
