document.querySelectorAll('[data-library-card-capture]').forEach(function (form) {
    const video = form.querySelector('video');
    const canvas = form.querySelector('canvas');
    const preview = form.querySelector('.library-card-preview');
    const openButton = form.querySelector('[data-open-camera]');
    const captureButton = form.querySelector('[data-capture-photo]');
    const retakeButton = form.querySelector('[data-retake-photo]');
    const submitButton = form.querySelector('[data-submit-photo]');
    const status = form.querySelector('.library-camera-status');
    let stream = null;
    let capturedPhoto = null;
    let previewUrl = '';

    function stopCamera() {
        if (stream) stream.getTracks().forEach(function (track) { track.stop(); });
        stream = null;
    }

    function clearPreview() {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = '';
        preview.removeAttribute('src');
        preview.hidden = true;
        capturedPhoto = null;
    }

    openButton.addEventListener('click', async function () {
        if (!navigator.mediaDevices?.getUserMedia) {
            status.textContent = 'Live camera access is not supported by this browser. Use a phone browser with camera permission enabled.';
            return;
        }
        try {
            stopCamera();
            clearPreview();
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false
            });
            video.srcObject = stream;
            await video.play();
            video.hidden = false;
            captureButton.hidden = false;
            retakeButton.hidden = true;
            submitButton.disabled = true;
            status.textContent = 'Place the complete library card inside the camera view.';
        } catch (error) {
            stopCamera();
            status.textContent = 'Camera access is required. Allow camera permission in your browser, then press Open Camera again.';
        }
    });

    captureButton.addEventListener('click', function () {
        if (!stream || !video.videoWidth || !video.videoHeight) return;
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        canvas.toBlob(function (blob) {
            if (!blob) {
                status.textContent = 'The camera could not capture the photo. Please try again.';
                return;
            }
            capturedPhoto = blob;
            previewUrl = URL.createObjectURL(blob);
            preview.src = previewUrl;
            preview.hidden = false;
            video.hidden = true;
            captureButton.hidden = true;
            retakeButton.hidden = false;
            submitButton.disabled = false;
            stopCamera();
            status.textContent = 'Photo captured. Check that the whole card is readable before submitting.';
        }, 'image/jpeg', 0.9);
    });

    retakeButton.addEventListener('click', function () {
        clearPreview();
        submitButton.disabled = true;
        openButton.click();
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!capturedPhoto) {
            status.textContent = 'Open the live camera and capture your library card first.';
            return;
        }
        submitButton.disabled = true;
        status.textContent = 'Submitting captured card…';
        const formData = new FormData(form);
        formData.append('library_card_photo', capturedPhoto, 'library-card-camera.jpg');
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            window.location.assign(response.url || form.action);
        } catch (error) {
            submitButton.disabled = false;
            status.textContent = 'The captured card could not be submitted. Check your connection and try again.';
        }
    });

    window.addEventListener('pagehide', function () {
        stopCamera();
        clearPreview();
    });

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-close-requirements-modal]')) stopCamera();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') stopCamera();
    });
});
