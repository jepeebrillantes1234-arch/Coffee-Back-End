import { BarcodeFormat, QRCodeWriter } from '@zxing/library';

function drawMembershipQrs() {
    document.querySelectorAll('[data-membership-qr]').forEach((element) => {
        const memberId = element.dataset.membershipQr;
        if (!memberId || element.dataset.rendered === 'true') return;

        try {
            const matrix = new QRCodeWriter().encode(
                memberId,
                BarcodeFormat.QR_CODE,
                512,
                512,
                new Map(),
            );
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            canvas.width = matrix.getWidth();
            canvas.height = matrix.getHeight();
            if (!context) throw new Error('Canvas is unavailable.');

            const image = context.createImageData(canvas.width, canvas.height);
            for (let y = 0; y < canvas.height; y += 1) {
                for (let x = 0; x < canvas.width; x += 1) {
                    const offset = (y * canvas.width + x) * 4;
                    const dark = matrix.get(x, y);
                    image.data[offset] = dark ? 0 : 255;
                    image.data[offset + 1] = dark ? 0 : 255;
                    image.data[offset + 2] = dark ? 0 : 255;
                    image.data[offset + 3] = 255;
                }
            }
            context.putImageData(image, 0, 0);
            canvas.setAttribute('aria-label', 'QR code for ' + memberId);
            element.replaceChildren(canvas);
            element.dataset.rendered = 'true';
        } catch (error) {
            element.textContent = 'QR code could not be generated.';
            element.classList.add('text-xs', 'text-red-700');
            console.error('Membership QR rendering failed.', error);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', drawMembershipQrs, { once: true });
} else {
    drawMembershipQrs();
}