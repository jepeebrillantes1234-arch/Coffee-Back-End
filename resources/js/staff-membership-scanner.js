import { BrowserQRCodeReader } from '@zxing/browser';

let controls = null;
let stream = null;
let videoElement = null;
let scanSession = 0;
let requestPending = false;
let lastPayload = '';

function messageForCameraError(error) {
    switch (error?.name) {
        case 'NotAllowedError':
        case 'PermissionDeniedError':
            return 'Camera permission was denied. Allow camera access in your browser settings, then try again.';
        case 'NotFoundError':
        case 'DevicesNotFoundError':
        case 'NotFoundException':
            return 'No camera was found on this device. Enter the Card Number manually.';
        case 'NotReadableError':
        case 'TrackStartError':
            return 'The camera is unavailable or being used by another application. Close other camera apps and try again.';
        case 'SecurityError':
        case 'InsecureContextError':
            return 'Camera access requires a secure HTTPS page or localhost.';
        case 'NotSupportedError':
        case 'TypeError':
            return 'This browser or device does not support camera scanning. Enter the Card Number manually.';
        default:
            return 'The camera could not start. Check browser permissions and try again, or enter the Card Number manually.';
    }
}

export function stopMembershipQrScanner() {
    scanSession += 1;
    requestPending = false;
    if (controls) controls.stop();
    controls = null;
    if (stream) stream.getTracks().forEach((track) => track.stop());
    stream = null;
    if (videoElement) {
        videoElement.pause();
        videoElement.srcObject = null;
    }
    videoElement = null;
}

export async function startMembershipQrScanner(video, status, onCode) {
    stopMembershipQrScanner();
    const session = scanSession;
    lastPayload = '';
    videoElement = video;
    status.textContent = 'Requesting camera access…';

    if (!navigator.mediaDevices?.getUserMedia) {
        status.textContent = 'Camera access is unavailable. Use a supported device on HTTPS or localhost, or enter the Card Number manually.';
        return;
    }

    try {
        const reader = new BrowserQRCodeReader();
        controls = await reader.decodeFromVideoDevice(undefined, video, async (result, error, activeControls) => {
            if (session !== scanSession) {
                activeControls?.stop();
                return;
            }
            if (error && !requestPending) {
                if (['ChecksumException', 'FormatException'].includes(error.name)) {
                    status.textContent = 'The QR code is not clear enough to read. Hold it steady in the camera view.';
                } else if (error.name !== 'NotFoundException') {
                    status.textContent = 'Unable to read this QR code. Hold it steady in the camera view, or enter the Card Number manually.';
                }
            }
            if (!result || requestPending) return;

            const payload = result.getText().trim();
            if (!payload) return;
            if (payload === lastPayload) return;
            lastPayload = payload;
            requestPending = true;
            status.textContent = 'QR code found. Verifying membership…';

            try {
                const verified = await onCode(payload);
                if (session !== scanSession) return;
                status.textContent = verified
                    ? 'Membership verified.'
                    : 'This QR could not be verified. Try another card or enter the Card Number manually.';
            } catch (_) {
                if (session === scanSession) status.textContent = 'Verification could not be completed. Check the connection and try again.';
            } finally {
                requestPending = false;
            }
        });

        if (session !== scanSession) {
            controls?.stop();
            controls = null;
            return;
        }
        stream = video.srcObject instanceof MediaStream ? video.srcObject : null;
        if (!stream) status.textContent = 'Camera is active. Hold the membership QR code in view.';
    } catch (error) {
        stopMembershipQrScanner();
        status.textContent = messageForCameraError(error);
    }
}

window.KjmMembershipQrScanner = {
    start: startMembershipQrScanner,
    stop: stopMembershipQrScanner,
};

window.addEventListener('pagehide', stopMembershipQrScanner);
window.addEventListener('beforeunload', stopMembershipQrScanner);
