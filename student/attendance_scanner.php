<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';

$user = requireRole('STUDENT');
$csrfToken = createCsrfToken();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ClearGo Attendance Scanner</title>
    <style>:root{--blue:#0d5ea6;--dark:#17324d;--line:#d8e3ed;--page:#f3f8fd}*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--dark);font-family:Arial,sans-serif}main{max-width:620px;margin:7vh auto;padding:20px}.panel{background:#fff;border:1px solid var(--line);border-radius:12px;padding:28px;text-align:center}h1{color:var(--blue)}#camera,#camera video,#camera img{width:100%;min-height:280px;max-height:60vh;background:#172433;border-radius:10px;object-fit:cover}.notice{padding:13px;background:#edf6ff;border-left:4px solid var(--blue);text-align:left}.error{background:#fff0ed;border-left-color:#a33722}button,a{display:inline-block;border:0;border-radius:5px;padding:11px 16px;background:var(--blue);color:#fff;text-decoration:none;font:inherit;cursor:pointer}.muted{color:#587086}</style>
</head>
<body><main><section class="panel">
    <h1>ClearGo Attendance Scanner</h1>
    <p class="muted">Point your camera at the event QR code. Only ClearGo attendance QR codes are accepted.</p>
    <div id="camera" aria-label="Camera preview"></div>
    <p id="status" class="notice">Starting camera…</p>
    <p><button id="retry" type="button" hidden>Enable camera and retry</button> <a href="<?= escapeHtml(applicationUrl('/student/dashboard.php')) ?>">Back to dashboard</a></p>
    <form id="scan-form" method="post" action="<?= escapeHtml(applicationUrl('/student/attendance_scanner_action.php')) ?>" hidden>
        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input id="token" type="hidden" name="token">
    </form>
</section></main>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
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
        await scanner.start({facingMode:{ideal:'environment'}}, {fps:10,qrbox:{width:250,height:250}},
            value => submitCode(value, () => scanner.stop()), () => {});
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
        const stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false});
        const video = document.createElement('video');
        video.autoplay = true; video.playsInline = true; video.muted = true;
        camera.replaceChildren(video);
        video.srcObject = stream;
        const detector = new BarcodeDetector({formats:['qr_code']});
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
</script>
</body></html>
