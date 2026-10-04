(() => {
    'use strict';

    const dialog = document.getElementById('idCameraDialog');
    const openers = [...document.querySelectorAll('[data-id-camera-target]')];
    if (!dialog || !openers.length) return;

    const video = document.getElementById('idCameraVideo');
    const snapshot = document.getElementById('idCameraSnapshot');
    const capture = document.getElementById('idCameraCapture');
    const retake = document.getElementById('idCameraRetake');
    const usePhoto = document.getElementById('idCameraUse');
    const flip = document.getElementById('idCameraFlip');
    const close = document.getElementById('idCameraClose');
    const title = document.getElementById('idCameraTitle');
    const help = document.getElementById('idCameraHelp');
    const cameraStatus = document.getElementById('idCameraStatus');
    const limit = 5 * 1024 * 1024;
    const minimumShortEdge = 300;
    const minimumLongEdge = 480;
    const previews = new Map();
    let active = null;
    let stream = null;
    let version = 0;
    let capturedFile = null;
    let capturedUrl = null;

    function elements(opener) {
        return {
            opener,
            input: document.getElementById(opener.dataset.idCameraTarget),
            status: document.getElementById(opener.dataset.idCameraStatus),
            preview: document.getElementById(opener.dataset.idCameraPreview),
            title: opener.dataset.idCameraTitle,
            help: opener.dataset.idCameraHelp,
            facingMode: opener.dataset.idCameraFacing || 'environment',
            filename: opener.dataset.idCameraFilename || 'valid-id',
        };
    }

    function stopCamera() {
        version++;
        if (stream) stream.getTracks().forEach(track => track.stop());
        stream = null;
        video.srcObject = null;
        capture.disabled = true;
    }

    function clearCapture() {
        capturedFile = null;
        if (capturedUrl) URL.revokeObjectURL(capturedUrl);
        capturedUrl = null;
        snapshot.removeAttribute('src');
        snapshot.hidden = true;
    }

    function closeCamera() {
        stopCamera();
        clearCapture();
        active = null;
        if (dialog.open) dialog.close();
    }

    function cameraError(error) {
        if (error.name === 'NotAllowedError' || error.name === 'SecurityError') {
            return 'Camera permission was denied. Allow camera access and try again, or use Choose File.';
        }
        if (error.name === 'NotFoundError' || error.name === 'OverconstrainedError') {
            return 'No suitable camera was found. Connect a camera or use Choose File.';
        }
        return 'The camera could not start. Close other camera apps and try again, or use Choose File.';
    }

    async function startCamera() {
        if (!active) return;
        stopCamera();
        clearCapture();
        video.hidden = false;
        capture.hidden = false;
        retake.hidden = true;
        usePhoto.hidden = true;
        if (flip) flip.hidden = true;
        cameraStatus.textContent = 'Allow camera access when your browser asks.';
        const request = version;
        try {
            const opened = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {
                    facingMode: { ideal: active.facingMode },
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                },
            });
            if (request !== version || !dialog.open) {
                opened.getTracks().forEach(track => track.stop());
                return;
            }
            stream = opened;
            video.srcObject = opened;
            await video.play();
            if (request !== version || !dialog.open) return;
            video.dataset.facing = active.facingMode;
            capture.disabled = false;
            cameraStatus.textContent = active.help;
            if (flip) {
                try {
                    const cameras = (await navigator.mediaDevices.enumerateDevices()).filter(device => device.kind === 'videoinput');
                    const supportsFacingMode = Boolean(navigator.mediaDevices.getSupportedConstraints?.().facingMode);
                    flip.hidden = cameras.length < 2 && !(navigator.maxTouchPoints > 0 && supportsFacingMode);
                } catch (_) {
                    flip.hidden = !navigator.mediaDevices.getSupportedConstraints?.().facingMode;
                }
            }
        } catch (error) {
            if (request !== version || !dialog.open) return;
            stopCamera();
            cameraStatus.textContent = cameraError(error);
            retake.hidden = false;
        }
    }

    function setPreview(config, file) {
        const previous = previews.get(config.input.id);
        if (previous) URL.revokeObjectURL(previous);
        const url = URL.createObjectURL(file);
        previews.set(config.input.id, url);
        config.preview.src = url;
        config.preview.hidden = false;
    }

    function checkDimensions(config, file) {
        const objectUrl = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            URL.revokeObjectURL(objectUrl);
            const tooSmall = Math.min(image.naturalWidth, image.naturalHeight) < minimumShortEdge
                || Math.max(image.naturalWidth, image.naturalHeight) < minimumLongEdge;
            const message = tooSmall ? 'The image is too small. Use a clear photo at least 480 by 300 pixels.' : '';
            config.input.setCustomValidity(message);
            if (message) config.status.textContent = message;
        };
        image.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            config.input.setCustomValidity('The selected image could not be read.');
            config.status.textContent = 'The selected image could not be read. Choose another photo.';
        };
        image.src = objectUrl;
    }

    function handleInput(config) {
        const previous = previews.get(config.input.id);
        if (previous) URL.revokeObjectURL(previous);
        previews.delete(config.input.id);
        config.input.setCustomValidity('');
        config.status.textContent = '';
        const file = config.input.files[0];
        if (!file) {
            if (config.input.dataset.savedPhotoUrl) {
                config.preview.src = config.input.dataset.savedPhotoUrl;
                config.preview.hidden = false;
                config.status.textContent = 'Saved photo ready. Choose a file or take a photo to replace it.';
            } else {
                config.preview.hidden = true;
                config.preview.removeAttribute('src');
            }
            return;
        }
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > limit || file.size === 0) {
            const message = 'Choose a JPG, PNG, or WebP image up to 5 MB.';
            config.input.setCustomValidity(message);
            config.status.textContent = message;
            config.preview.hidden = true;
            return;
        }
        setPreview(config, file);
        config.status.textContent = 'Photo selected. Make sure every required detail is readable.';
        checkDimensions(config, file);
    }

    openers.forEach(opener => {
        const config = elements(opener);
        if (!config.input || !config.status || !config.preview) return;
        config.input.addEventListener('change', () => handleInput(config));
        opener.addEventListener('click', () => {
            config.status.textContent = '';
            if (!window.isSecureContext) {
                config.status.textContent = 'Camera access needs HTTPS. You can still upload a photo with Choose File.';
                return;
            }
            if (!navigator.mediaDevices?.getUserMedia || typeof dialog.showModal !== 'function') {
                config.status.textContent = 'Camera capture is unavailable in this browser. Use Choose File.';
                return;
            }
            active = config;
            active.facingMode = opener.dataset.idCameraFacing || 'environment';
            title.textContent = config.title;
            help.textContent = config.help;
            if (!dialog.open) dialog.showModal();
            startCamera();
        });
    });

    capture.addEventListener('click', async () => {
        if (!active || !stream || video.readyState < 2 || !video.videoWidth || !video.videoHeight) {
            cameraStatus.textContent = 'Wait for the camera preview before capturing.';
            return;
        }
        capture.disabled = true;
        const request = version;
        try {
            const scale = Math.min(1, 1920 / Math.max(video.videoWidth, video.videoHeight));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(video.videoWidth * scale);
            canvas.height = Math.round(video.videoHeight * scale);
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.9));
            if (request !== version || !dialog.open) return;
            if (!blob || blob.size === 0 || blob.size > limit) throw new Error('capture failed');
            capturedFile = new File([blob], `${active.filename}-${Date.now()}.jpg`, { type: 'image/jpeg' });
            capturedUrl = URL.createObjectURL(capturedFile);
            snapshot.src = capturedUrl;
            snapshot.hidden = false;
            video.hidden = true;
            stopCamera();
            if (flip) flip.hidden = true;
            capture.hidden = true;
            retake.hidden = false;
            usePhoto.hidden = false;
            cameraStatus.textContent = 'Check that the required details are clear. Retake it if necessary.';
            usePhoto.focus();
        } catch (_) {
            capture.disabled = false;
            cameraStatus.textContent = 'The photo could not be captured. Try again or use Choose File.';
        }
    });

    retake.addEventListener('click', startCamera);
    flip?.addEventListener('click', () => {
        if (!active) return;
        active.facingMode = active.facingMode === 'user' ? 'environment' : 'user';
        cameraStatus.textContent = active.facingMode === 'environment' ? 'Switching to the rear camera...' : 'Switching to the front camera...';
        startCamera();
    });
    usePhoto.addEventListener('click', () => {
        if (!active || !capturedFile) return;
        try {
            const files = new DataTransfer();
            files.items.add(capturedFile);
            active.input.files = files.files;
            active.input.dispatchEvent(new Event('input', { bubbles: true }));
            active.input.dispatchEvent(new Event('change', { bubbles: true }));
            active.status.textContent = 'Photo selected. Save the draft or continue to review your application.';
            closeCamera();
        } catch (_) {
            cameraStatus.textContent = 'This browser cannot attach the captured photo. Use Choose File instead.';
        }
    });

    close.addEventListener('click', closeCamera);
    dialog.addEventListener('close', () => { stopCamera(); clearCapture(); });
    dialog.addEventListener('cancel', closeCamera);
    document.addEventListener('visibilitychange', () => { if (document.hidden) closeCamera(); });
    window.addEventListener('pagehide', () => {
        closeCamera();
        previews.forEach(url => URL.revokeObjectURL(url));
        previews.clear();
    });
})();
