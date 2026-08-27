"""Swap the old cyan/pink pinwheel in assets/screenshot-N.png for the new logomark.

Old logo is located by its two saturated-pink petals; the box is repainted with the
surrounding background (per-row linear interpolation across the smooth gradient),
then the new logomark is composited into the same box.
"""
from PIL import Image
import numpy as np
from scipy import ndimage

SRC = "dev/brand/ReferralCandy_Logomark_1024.png"
PAD = 3  # eat the old logo's antialiased edge


def new_logomark():
    im = Image.open(SRC).convert("RGBA")
    a = np.asarray(im).copy()
    # ponytail: white interior gaps -> transparent, so the page gradient shows through
    a[..., 3] = np.where(a[..., :3].min(2) > 235, 0, a[..., 3])
    im = Image.fromarray(a)
    return im.crop(im.getchannel("A").getbbox())


def old_logo_box(a):
    r, g, b = a[:, :, 0], a[:, :, 1], a[:, :, 2]
    pink = (r > 200) & (g < 90) & (b > 90) & (b < 200)
    lab, _ = ndimage.label(pink)
    boxes = [
        (sx.start, sy.start, sx.stop, sy.stop)
        for s, (sy, sx) in enumerate(ndimage.find_objects(lab), 1)
        if (lab[sy, sx] == s).sum() >= 150
    ]
    assert len(boxes) == 2, f"expected 2 pink petals, got {len(boxes)}"
    return (
        min(x[0] for x in boxes), min(x[1] for x in boxes),
        max(x[2] for x in boxes), max(x[3] for x in boxes),
    )


def repaint_background(a, box):
    x0, y0, x1, y1 = box
    left = a[y0:y1, x0 - 1][:, None, :].astype(float)
    right = a[y0:y1, x1][:, None, :].astype(float)
    t = np.linspace(0, 1, x1 - x0)[None, :, None]
    a[y0:y1, x0:x1] = np.round(left * (1 - t) + right * t).astype(np.uint8)


logo = new_logomark()
for i in range(1, 7):
    path = f"assets/screenshot-{i}.png"
    im = Image.open(path).convert("RGB")
    a = np.asarray(im).copy()
    x0, y0, x1, y1 = old_logo_box(a)
    repaint_background(a, (x0 - PAD, y0 - PAD, x1 + PAD, y1 + PAD))
    im = Image.fromarray(a).convert("RGBA")

    size = max(x1 - x0, y1 - y0)
    s = min(size / logo.width, size / logo.height)
    art = logo.resize((round(logo.width * s), round(logo.height * s)), Image.LANCZOS)
    im.alpha_composite(art, (x0, y0 + (size - art.height) // 2))
    im.convert("RGB").save(path)
    print(f"screenshot-{i}: replaced {x1 - x0}x{y1 - y0} logo at ({x0},{y0})")
