const camera = document.getElementById('camera');
const status = document.getElementById('status');
const retry = document.getElementById('retry');
const form = document.getElementById('scan-form');
const token = document.getElementById('token');
let stopped = false;
function fail(message) { status.textContent = message; status.classList.add('error'); retry.hidden = false; }
function submitCode(value, stop) {
    const match = String(value || '').match(/^CLEARGO-ATTENDANCE:([a-f0-9]{64})$/i);
    if (!match) { fail('This is not a ClearGo attendance QR code.'); return false; }
    stopped = true;
    Promise.resolve(stop()).finally(() => { token.value = match[1].toLowerCase(); form.submit(); });
    return true;
}
async function startCompatibilityScanner() {
    if (!window.Html5Qrcode) { fail('The ClearGo scanner could not load. Check your internet connection and reload this page.'); return; }
    const scanner = new Html5Qrcode('camera');
    try {
        await scanner.start({ facingMode: { ideal: 'environment' } }, { fps: 10, qrbox: { width: 250, height: 250 } },
            value => submitCode(value, () => scanner.stop()), () => { });
        status.textContent = 'Camera ready. Scan the event QR code.';
    } catch (error) { showCameraError(error); }
}
function showCameraError(error) {
    if (!window.isSecureContext && location.hostname !== 'localhost') {
        fail('Camera access requires HTTPS. Open ClearGo using https://, or use localhost on this device.');
    } else if (error && error.name === 'NotFoundError') {
        fail('No camera was found on this device. Connect a camera and try again.');
    } else {
        fail('Camera access is blocked. In your browser site settings, allow Camera for ClearGo, then select “Enable camera and retry”.');
    }
}
async function startScanner() {
    if (!('BarcodeDetector' in window)) { startCompatibilityScanner(); return; }
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
        const video = document.createElement('video');
        video.autoplay = true; video.playsInline = true; video.muted = true;
        camera.replaceChildren(video);
        video.srcObject = stream;
        const detector = new BarcodeDetector({ formats: ['qr_code'] });
        status.textContent = 'Camera ready. Scan the event QR code.';
        const scan = async () => {
            if (stopped) return;
            try {
                const codes = await detector.detect(video);
                if (codes.length) {
                    if (submitCode(codes[0].rawValue, () => stream.getTracks().forEach(track => track.stop()))) return;
                }
            } catch (_) { /* Keep scanning while the camera initializes. */ }
            window.setTimeout(scan, 250);
        };
        scan();
    } catch (error) { showCameraError(error); }
}
retry.addEventListener('click', function () {
    stopped = false;
    retry.hidden = true;
    status.classList.remove('error');
    status.textContent = 'Starting camera…';
    camera.replaceChildren();
    startScanner();
});
startScanner();
