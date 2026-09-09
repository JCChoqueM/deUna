#!/usr/bin/env python3
"""
DeUna - QR Code Generator (Python backend)
Generates QR code PNG images from data strings.
Called by QRGen.php for server-side QR code generation.
"""

import sys
import io
import base64
import qrcode
import qrcode.constants


def generate_qr(data, size=320, margin=4):
    """Generate a QR code PNG and return as base64-encoded string."""
    # Determine version based on data length
    qr = qrcode.QRCode(
        version=None,  # auto-select
        error_correction=qrcode.constants.ERROR_CORRECT_M,
        box_size=10,
        border=margin,
    )
    qr.add_data(data)
    qr.make(fit=True)

    img = qr.make_image(fill_color='black', back_color='white')

    # Resize based on requested size
    img_pil = img._img if hasattr(img, '_img') else img
    if hasattr(img_pil, 'resize'):
        box_size = max(2, size // (img_pil.size[0] // (img.box_size if hasattr(img, 'box_size') else 10)))
        # Calculate target size
        target_size = size
        img_pil = img_pil.resize((target_size, target_size), resample=getattr(__import__('PIL').Image, 'NEAREST', 0))

    buffer = io.BytesIO()
    img_pil.save(buffer, format='PNG')
    return base64.b64encode(buffer.getvalue()).decode('ascii')


def main():
    if len(sys.argv) < 2:
        print("Usage: generate_qr.py <data> [size] [margin]", file=sys.stderr)
        sys.exit(1)

    data = sys.argv[1]
    size = int(sys.argv[2]) if len(sys.argv) > 2 else 320
    margin = int(sys.argv[3]) if len(sys.argv) > 3 else 4

    try:
        result = generate_qr(data, size, margin)
        print(result)
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
