(() => {
    'use strict';
    const input = document.getElementById('id_selfie_image');
    const dialog = document.getElementById('idCameraDialog');
    if (!input || !dialog) return;
    const opener = document.getElementById('idCameraOpen');
    const video = document.getElementById('idCameraVideo');
    const snapshot = document.getElementById('idCameraSnapshot');
    const capture = document.getElementById('idCameraCapture');
    const retake = document.getElementById('idCameraRetake');
    const usePhoto = document.getElementById('idCameraUse');
    const cameraStatus = document.getElementById('idCameraStatus');
    const status = document.getElementById('idPhotoStatus');
    const preview = document.getElementById('idPhotoPreview');
    const limit = 5 * 1024 * 1024;
    let stream = null;
    let version = 0;
    let draft = null;
    let draftUrl = null;
    let previewUrl = null;

    function stopCamera() {
        version++;
        if (stream) stream.getTracks().forEach(track => track.stop());
        stream = null;
        video.srcObject = null;
        capture.disabled = true;
    }

    function clearDraft() {
        draft = null;
        if (draftUrl) URL.revokeObjectURL(draftUrl);
        draftUrl = null;
        snapshot.removeAttribute('src');
        snapshot.hidden = true;
    }

    function closeCamera() {
        stopCamera();
        clearDraft();
        if (dialog.open) dialog.close();
    }

    function cameraError(error) {
        if (error.name === 'NotAllowedError' || error.name === 'SecurityError') {
            return 'Camera permission was denied. Allow camera access in your browser and try again, or use Choose File.';
        }
        if (error.name === 'NotFoundError' || error.name === 'OverconstrainedError') {
            return 'No camera was found. Connect a camera and try again, or use Choose File.';
        }
        return 'The camera could not start. It may be in use by another app. Close it there and try again, or use Choose File.';
    }

    async function startCamera() {
        stopCamera();
        clearDraft();
        video.hidden = false;
        capture.hidden = false;
        retake.hidden = true;
        usePhoto.hidden = true;
        cameraStatus.textContent = 'Allow camera access when your browser asks.';
        const request = version;
        try {
            const opened = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: { ideal: 'user' }, width: { ideal: 1280 }, height: { ideal: 960 } }
            });
            if (request !== version || !dialog.open) {
                opened.getTracks().forEach(track => track.stop());
                return;
            }
            stream = opened;
            video.srcObject = opened;
            await video.play();
            if (request !== version || !dialog.open) return;
            capture.disabled = false;
            cameraStatus.textContent = 'Position your face and ID in the preview, then select Capture photo.';
        } catch (error) {
            if (request !== version || !dialog.open) return;
            stopCamera();
            cameraStatus.textContent = cameraError(error);
            retake.hidden = false;
        }
    }

    opener.addEventListener('click', () => {
        status.textContent = '';
        if (!window.isSecureContext) {
            status.textContent = 'Camera access needs a secure HTTPS connection. You can still upload a photo with Choose File.';
            return;
        }
        if (!navigator.mediaDevices?.getUserMedia || typeof dialog.showModal !== 'function') {
            status.textContent = 'Camera capture is not supported in this browser. Please use Choose File.';
            return;
        }
        if (!dialog.open) dialog.showModal();
        startCamera();
    });

    capture.addEventListener('click', async () => {
        if (!stream || video.readyState < 2 || !video.videoWidth || !video.videoHeight) {
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
            // Keep the image unmirrored so text on the ID stays readable.
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.9));
            if (request !== version || !dialog.open) return;
            if (!blob || blob.size === 0 || blob.size > limit) throw new Error('capture failed');
            draft = new File([blob], 'holding-id-' + Date.now() + '.jpg', { type: 'image/jpeg' });
            draftUrl = URL.createObjectURL(draft);
            snapshot.src = draftUrl;
            snapshot.hidden = false;
            video.hidden = true;
            stopCamera();
            capture.hidden = true;
            retake.hidden = false;
            usePhoto.hidden = false;
            cameraStatus.textContent = 'Check that your face and ID are clear. Retake if needed, or use this photo.';
            usePhoto.focus();
        } catch (_) {
            capture.disabled = false;
            cameraStatus.textContent = 'The photo could not be captured. Please try again or use Choose File.';
        }
    });

    retake.addEventListener('click', startCamera);
    usePhoto.addEventListener('click', () => {
        if (!draft) return;
        try {
            const files = new DataTransfer();
            files.items.add(draft);
            input.files = files.files;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            status.textContent = 'Photo selected. Save your draft to keep it, or submit your application when ready.';
            closeCamera();
        } catch (_) {
            cameraStatus.textContent = 'This browser cannot attach the captured photo. Please close the camera and use Choose File.';
        }
    });

    input.addEventListener('change', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
        preview.hidden = true;
        preview.removeAttribute('src');
        input.setCustomValidity('');
        status.textContent = '';
        const file = input.files[0];
        if (!file) {
            if (input.dataset.savedPhotoUrl) {
                status.textContent = 'Your saved ID photo is ready. Choose File or Take photo to replace it.';
                preview.src = input.dataset.savedPhotoUrl;
                preview.hidden = false;
            }
            return;
        }
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > limit || file.size === 0) {
            const message = 'Choose a JPG, PNG, or WebP image up to 5 MB.';
            input.setCustomValidity(message);
            status.textContent = message;
            return;
        }
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        preview.hidden = false;
    });

    document.getElementById('idCameraClose').addEventListener('click', closeCamera);
    dialog.addEventListener('close', () => { stopCamera(); clearDraft(); });
    dialog.addEventListener('cancel', closeCamera);
    document.addEventListener('visibilitychange', () => { if (document.hidden) closeCamera(); });
    window.addEventListener('pagehide', () => {
        closeCamera();
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
    });
    const uploadPanel = input.closest('[data-panel]');
    new MutationObserver(() => {
        if (!uploadPanel.classList.contains('is-active') && dialog.open) closeCamera();
    }).observe(uploadPanel, { attributes: true, attributeFilter: ['class'] });
})();
