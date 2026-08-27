from PIL import Image

def trim(path):
    im = Image.open(path).convert("RGBA")
    return im.crop(im.getchannel("A").getbbox())

logomark = trim("dev/brand/ReferralCandy_Logomark_1024.png")
fulllogo = trim("dev/brand/ReferralCandy_FullLogo_2400w.png")

def fit(im, box_w, box_h):
    s = min(box_w / im.width, box_h / im.height)
    return im.resize((max(1, round(im.width * s)), max(1, round(im.height * s))), Image.LANCZOS)

# icons: transparent canvas, logomark at 96%
for size in (128, 250):
    canvas = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    art = fit(logomark, round(size * 0.96), round(size * 0.96))
    canvas.alpha_composite(art, ((size - art.width) // 2, (size - art.height) // 2))
    canvas.save(f"assets/icon-{size}x{size}.png")

# banners: light pink brand tint, full logo centered at 72% width
BG = (253, 240, 247, 255)
for w, h in ((772, 250), (1544, 500)):
    canvas = Image.new("RGBA", (w, h), BG)
    art = fit(fulllogo, round(w * 0.72), round(h * 0.46))
    canvas.alpha_composite(art, ((w - art.width) // 2, (h - art.height) // 2))
    # brand pink accent bar at bottom
    bar = max(2, round(h * 0.016))
    canvas.paste((255, 14, 139, 255), (0, h - bar, w, h))
    canvas.convert("RGB").save(f"assets/banner-{w}x{h}.png")
