from __future__ import annotations

import math
import random
from pathlib import Path

from PIL import Image, ImageDraw, ImageEnhance, ImageFilter, ImageFont


ROOT = Path(__file__).resolve().parents[1]
ASSET_DIR = ROOT / "assets" / "roastery"
OUT_DIR = ASSET_DIR / "shopee-slides"

PRODUCT_PATH = ASSET_DIR / "IJEN FIRST LIGHT.png"
LOGO_PATH = ASSET_DIR / "logo 2.png"

W = H = 1200

MAROON = (95, 20, 26)
DARK = (42, 30, 25)
CREAM = (244, 235, 219)
PAPER = (239, 228, 211)
INK = (45, 33, 28)
MUTED = (122, 86, 69)
GOLD = (193, 142, 75)
SAGE = (92, 112, 83)
SKY = (196, 210, 213)


def font(name: str, size: int) -> ImageFont.FreeTypeFont:
    fonts = Path("C:/Windows/Fonts")
    return ImageFont.truetype(str(fonts / name), size=size)


FONT = {
    "display": "georgiab.ttf",
    "display_i": "georgiai.ttf",
    "serif": "georgia.ttf",
    "serif_b": "georgiab.ttf",
    "sans": "segoeui.ttf",
    "sans_b": "segoeuib.ttf",
    "sans_l": "segoeuil.ttf",
}


def f(key: str, size: int) -> ImageFont.FreeTypeFont:
    return font(FONT[key], size)


def rgba(c, a=255):
    return (*c, a)


def canvas(color=PAPER):
    return Image.new("RGB", (W, H), color)


def draw_noise(img: Image.Image, alpha: int = 16, seed: int = 1) -> None:
    rng = random.Random(seed)
    px = Image.new("RGBA", img.size, (0, 0, 0, 0))
    p = px.load()
    for _ in range(9000):
        x = rng.randrange(W)
        y = rng.randrange(H)
        v = rng.randrange(70, 190)
        p[x, y] = (v, v, v, rng.randrange(2, alpha))
    img.alpha_composite(px) if img.mode == "RGBA" else img.paste(Image.alpha_composite(img.convert("RGBA"), px).convert("RGB"))


def add_paper_texture(img: Image.Image, seed: int = 1) -> Image.Image:
    base = img.convert("RGBA")
    rng = random.Random(seed)
    overlay = Image.new("RGBA", base.size, (0, 0, 0, 0))
    d = ImageDraw.Draw(overlay)
    for _ in range(170):
        x = rng.randint(-200, W + 100)
        y = rng.randint(0, H)
        length = rng.randint(80, 320)
        color = (125, 91, 65, rng.randint(5, 14))
        d.line((x, y, x + length, y + rng.randint(-8, 8)), fill=color, width=1)
    for _ in range(80):
        x = rng.randint(0, W)
        y = rng.randint(0, H)
        r = rng.randint(1, 3)
        d.ellipse((x - r, y - r, x + r, y + r), fill=(91, 64, 45, rng.randint(5, 18)))
    return Image.alpha_composite(base, overlay).convert("RGB")


def paste_alpha(dst: Image.Image, src: Image.Image, xy: tuple[int, int], alpha: int = 255) -> None:
    if src.mode != "RGBA":
        src = src.convert("RGBA")
    if alpha < 255:
        a = src.getchannel("A").point(lambda p: int(p * alpha / 255))
        src = src.copy()
        src.putalpha(a)
    dst.paste(src, xy, src)


def cover_crop() -> Image.Image:
    im = Image.open(PRODUCT_PATH).convert("RGB")
    # Crop the existing lifestyle photo into square while keeping the pouch strong.
    crop = im.crop((0, 185, im.width, 1209))
    crop = crop.resize((W, H), Image.Resampling.LANCZOS)
    return crop


def product_cut(width: int = 510) -> Image.Image:
    im = Image.open(PRODUCT_PATH).convert("RGBA")
    # Tight crop around the pouch from the original photo. The rounded card mask keeps
    # the remaining background intentional instead of pretending to be a fragile cutout.
    crop = im.crop((205, 345, 850, 1382))
    h = int(width * crop.height / crop.width)
    crop = crop.resize((width, h), Image.Resampling.LANCZOS)
    mask = Image.new("L", crop.size, 0)
    d = ImageDraw.Draw(mask)
    d.rounded_rectangle((0, 0, crop.width, crop.height), radius=34, fill=255)
    crop.putalpha(mask)
    return crop


def load_logo(width: int, colorize: tuple[int, int, int] | None = None) -> Image.Image:
    logo = Image.open(LOGO_PATH).convert("RGBA")
    ratio = width / logo.width
    logo = logo.resize((width, int(logo.height * ratio)), Image.Resampling.LANCZOS)
    if colorize:
        alpha = logo.getchannel("A")
        solid = Image.new("RGBA", logo.size, (*colorize, 255))
        solid.putalpha(alpha)
        return solid
    return logo


def centered_text(draw, xy, text, font_obj, fill, spacing=4, anchor="mm"):
    draw.multiline_text(xy, text, font=font_obj, fill=fill, anchor=anchor, align="center", spacing=spacing)


def text(draw, xy, s, size, fill=INK, style="sans", **kw):
    draw.text(xy, s, font=f(style, size), fill=fill, **kw)


def letter_text(draw, xy, s, size, fill=INK, style="sans_b", spacing=2):
    x, y = xy
    ft = f(style, size)
    for ch in s:
        draw.text((x, y), ch, font=ft, fill=fill)
        x += draw.textlength(ch, font=ft) + spacing


def wrap(draw: ImageDraw.ImageDraw, s: str, font_obj, max_width: int) -> list[str]:
    words = s.split()
    lines = []
    current = ""
    for word in words:
        test = (current + " " + word).strip()
        if draw.textlength(test, font=font_obj) <= max_width or not current:
            current = test
        else:
            lines.append(current)
            current = word
    if current:
        lines.append(current)
    return lines


def pill(draw, box, label, fill=MAROON, fg=CREAM, radius=0, size=22):
    draw.rounded_rectangle(box, radius=radius, fill=fill)
    draw.text(((box[0] + box[2]) / 2, (box[1] + box[3]) / 2 - 1), label, font=f("sans_b", size), fill=fg, anchor="mm")


def bean(img_or_draw, cx, cy, r, angle=0, fill=(76, 39, 25)):
    layer = Image.new("RGBA", (int(r * 3.2), int(r * 3.2)), (0, 0, 0, 0))
    dd = ImageDraw.Draw(layer, "RGBA")
    lx = layer.width / 2
    ly = layer.height / 2
    bbox = (lx - r * 0.72, ly - r, lx + r * 0.72, ly + r)
    dd.ellipse(bbox, fill=(*fill, 255), outline=(45, 25, 20, 140), width=max(1, int(r / 12)))
    dd.arc((lx - r * 0.30, ly - r * 0.78, lx + r * 0.30, ly + r * 0.78), 78, 282, fill=(150, 104, 72, 145), width=max(1, int(r / 9)))
    dd.ellipse((lx - r * .35, ly - r * .62, lx - r * .08, ly - r * .30), fill=(180, 130, 88, 38))
    if angle:
        layer = layer.rotate(angle, expand=True, resample=Image.Resampling.BICUBIC)
    if isinstance(img_or_draw, Image.Image):
        img_or_draw.alpha_composite(layer, (int(cx - layer.width / 2), int(cy - layer.height / 2)))
    else:
        img_or_draw.ellipse((cx - r * 0.72, cy - r, cx + r * 0.72, cy + r), fill=fill, outline=(45, 25, 20), width=2)
        img_or_draw.arc((cx - r * 0.28, cy - r * 0.75, cx + r * 0.28, cy + r * 0.75), 78, 282, fill=(155, 105, 70), width=2)


def draw_stamp(draw, x, y, label):
    draw.ellipse((x, y, x + 82, y + 82), outline=MAROON, width=2)
    draw.ellipse((x + 10, y + 10, x + 72, y + 72), outline=MAROON, width=1)
    draw.text((x + 41, y + 41), label, font=f("sans_b", 13), fill=MAROON, anchor="mm", align="center")


def shade_panel(img, side="left"):
    ov = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    d = ImageDraw.Draw(ov)
    if side == "left":
        for x in range(W):
            a = int(max(0, 175 * (1 - x / 680)))
            d.line((x, 0, x, H), fill=(31, 18, 15, a))
    else:
        for y in range(H):
            a = int(max(0, 155 * (y / H)))
            d.line((0, y, W, y), fill=(31, 18, 15, a))
    return Image.alpha_composite(img.convert("RGBA"), ov).convert("RGB")


def slide_number(img, n):
    d = ImageDraw.Draw(img)
    d.rectangle((0, 0, 78, 78), fill=MAROON)
    d.text((39, 39), str(n), font=f("sans_b", 36), fill=CREAM, anchor="mm")


def footer_band(img, title, subtitle=None):
    d = ImageDraw.Draw(img, "RGBA")
    d.rectangle((0, 1036, W, H), fill=rgba(MAROON, 244))
    d.line((64, 1036, W - 64, 1036), fill=rgba(GOLD, 130), width=1)
    d.text((600, 1086), title, font=f("sans_b", 27), fill=CREAM, anchor="mm")
    if subtitle:
        d.text((600, 1131), subtitle, font=f("sans", 19), fill=(232, 212, 189), anchor="mm")


def icon_line(draw, xy, kind, color=MAROON, scale=1.0):
    x, y = xy
    s = scale
    w = int(80 * s)
    if kind == "origin":
        draw.polygon([(x, y + w), (x + w * .38, y + w * .23), (x + w * .6, y + w * .62), (x + w * .74, y + w * .39), (x + w, y + w)], outline=color)
        draw.line((x + w * .31, y + w * .37, x + w * .43, y + w * .48, x + w * .51, y + w * .45), fill=color, width=max(1, int(3 * s)))
    elif kind == "process":
        for i in range(5):
            a = math.tau * i / 5 - math.pi / 2
            cx = x + w / 2 + math.cos(a) * w * .28
            cy = y + w / 2 + math.sin(a) * w * .28
            draw.ellipse((cx - 9 * s, cy - 9 * s, cx + 9 * s, cy + 9 * s), outline=color, width=max(1, int(2 * s)))
        draw.ellipse((x + w * .38, y + w * .38, x + w * .62, y + w * .62), outline=color, width=max(1, int(2 * s)))
    elif kind == "arabica":
        draw.line((x + w / 2, y + w * .78, x + w / 2, y + w * .28), fill=color, width=max(1, int(3 * s)))
        draw.ellipse((x + w * .2, y + w * .38, x + w * .52, y + w * .72), outline=color, width=max(1, int(3 * s)))
        draw.ellipse((x + w * .48, y + w * .27, x + w * .82, y + w * .61), outline=color, width=max(1, int(3 * s)))
    elif kind == "sweet":
        draw.ellipse((x + 16*s, y + 23*s, x + 64*s, y + 73*s), outline=color, width=max(1, int(3*s)))
        draw.line((x + 40*s, y + 23*s, x + 40*s, y + 9*s), fill=color, width=max(1, int(3*s)))
        draw.arc((x + 40*s, y, x + 70*s, y + 25*s), 170, 290, fill=color, width=max(1, int(3*s)))
    elif kind == "drop":
        draw.polygon([(x + 40*s, y + 8*s), (x + 67*s, y + 54*s), (x + 40*s, y + 81*s), (x + 13*s, y + 54*s)], outline=color)
    elif kind == "cup":
        draw.rounded_rectangle((x + 15*s, y + 35*s, x + 62*s, y + 66*s), radius=int(7*s), outline=color, width=max(1, int(3*s)))
        draw.arc((x + 56*s, y + 41*s, x + 82*s, y + 61*s), -75, 85, fill=color, width=max(1, int(3*s)))
        draw.line((x + 12*s, y + 75*s, x + 74*s, y + 75*s), fill=color, width=max(1, int(3*s)))
    elif kind == "sun":
        draw.ellipse((x + 27*s, y + 27*s, x + 53*s, y + 53*s), outline=color, width=max(1, int(3*s)))
        for a in range(0, 360, 45):
            r = math.radians(a)
            draw.line((x + 40*s + math.cos(r)*27*s, y + 40*s + math.sin(r)*27*s,
                       x + 40*s + math.cos(r)*38*s, y + 40*s + math.sin(r)*38*s), fill=color, width=max(1, int(2*s)))


def slide1():
    img = shade_panel(cover_crop(), "left").convert("RGBA")
    d = ImageDraw.Draw(img, "RGBA")
    d.rectangle((0, 0, 430, H), fill=(52, 30, 24, 74))
    paste_alpha(img, load_logo(150, CREAM), (978, 48), 230)
    letter_text(d, (86, 178), "SINGLE ORIGIN", 21, fill=(238, 218, 191), style="sans_b", spacing=5)
    d.text((86, 232), "IJEN\nFIRST\nLIGHT", font=f("display", 77), fill=CREAM, spacing=0)
    d.rectangle((86, 503, 392, 548), fill=rgba(MAROON, 238))
    d.text((239, 526), "NATURAL PROCESS", font=f("sans_b", 20), fill=CREAM, anchor="mm")
    d.text((86, 592), "100% ARABICA", font=f("sans_b", 24), fill=CREAM)
    d.text((86, 631), "MT. IJEN, EAST JAVA", font=f("sans", 22), fill=(239, 220, 196))
    y = 716
    for item in ["RAISIN", "RED APPLE", "BROWN SUGAR"]:
        d.ellipse((86, y + 7, 104, y + 25), outline=CREAM, width=2)
        d.text((123, y), item, font=f("sans_b", 21), fill=CREAM)
        y += 52
    footer_band(img, "LIGHT - MEDIUM ROAST", "Fruity, sweet, clean")
    slide_number(img, 1)
    return img.convert("RGB")


def draw_raisins(draw, center, scale=1.0):
    cx, cy = center
    draw.ellipse((cx - 86, cy - 52, cx + 86, cy + 52), fill=(205, 170, 126), outline=(148, 98, 57), width=2)
    rng = random.Random(12)
    for _ in range(24):
        x = cx + rng.randint(-58, 58)
        y = cy + rng.randint(-29, 29)
        r = rng.randint(12, 18)
        draw.ellipse((x - r, y - r * .75, x + r, y + r * .75), fill=(63, 32, 27), outline=(106, 67, 50), width=1)
        draw.arc((x - r*.4, y - r*.55, x + r*.4, y + r*.55), 75, 250, fill=(130, 88, 65), width=1)


def draw_apple(draw, center, scale=1.0):
    cx, cy = center
    draw.ellipse((cx - 70, cy - 44, cx + 20, cy + 56), fill=(169, 40, 35), outline=(104, 24, 22), width=2)
    draw.ellipse((cx - 10, cy - 50, cx + 78, cy + 52), fill=(214, 191, 143), outline=(130, 82, 51), width=2)
    draw.arc((cx + 14, cy - 38, cx + 56, cy + 40), 85, 275, fill=(127, 87, 57), width=2)
    draw.line((cx - 2, cy - 46, cx + 12, cy - 78), fill=(69, 45, 28), width=5)
    draw.ellipse((cx + 10, cy - 83, cx + 50, cy - 56), fill=(86, 123, 72), outline=SAGE, width=2)


def draw_sugar(draw, center):
    cx, cy = center
    for dx, dy in [(-38, -18), (15, -20), (-8, 23), (48, 20)]:
        box = (cx + dx - 33, cy + dy - 25, cx + dx + 33, cy + dy + 25)
        draw.rounded_rectangle(box, radius=7, fill=(172, 132, 82), outline=(121, 84, 47), width=2)
        draw.line((box[0]+8, box[1]+7, box[2]-10, box[1]+7), fill=(214, 178, 121), width=2)


def slide2():
    img = add_paper_texture(canvas((245, 238, 226)), 2).convert("RGBA")
    d = ImageDraw.Draw(img, "RGBA")
    d.text((600, 76), "TASTE NOTES", font=f("sans_b", 30), fill=INK, anchor="mm")
    d.line((548, 113, 652, 113), fill=MAROON, width=3)
    sections = [
        ("RAISIN", "Sweet and rich, like natural dried fruit.", draw_raisins),
        ("RED APPLE", "Juicy acidity with a fresh apple lift.", draw_apple),
        ("BROWN SUGAR", "Smooth sweetness and a warm finish.", draw_sugar),
    ]
    y = 225
    for title, desc, painter in sections:
        painter(d, (255, y + 30))
        d.text((430, y - 18), title, font=f("sans_b", 36), fill=MAROON)
        for i, line in enumerate(wrap(d, desc, f("serif", 25), 470)):
            d.text((430, y + 31 + i * 33), line, font=f("serif", 25), fill=INK)
        d.line((150, y + 139, 1050, y + 139), fill=(172, 127, 90, 72), width=1)
        y += 270
    paste_alpha(img, load_logo(130, MAROON), (965, 1000), 210)
    slide_number(img, 2)
    return img.convert("RGB")


def draw_mountain_scene(img):
    d = ImageDraw.Draw(img, "RGBA")
    # Sky wash
    for y in range(H):
        t = y / H
        col = tuple(int(SKY[i] * (1 - t) + (232, 222, 204)[i] * t) for i in range(3))
        d.line((0, y, W, y), fill=(*col, 255))
    # Distant ridges
    ridges = [
        [(0, 690), (130, 620), (260, 650), (405, 540), (548, 575), (690, 430), (853, 590), (1030, 475), (1200, 560), (1200, 1200), (0, 1200)],
        [(0, 790), (140, 730), (278, 750), (440, 648), (610, 710), (770, 588), (950, 735), (1200, 665), (1200, 1200), (0, 1200)],
        [(0, 920), (190, 845), (385, 902), (580, 794), (765, 930), (920, 805), (1200, 890), (1200, 1200), (0, 1200)],
    ]
    fills = [(70, 90, 79, 190), (105, 126, 93, 205), (142, 130, 88, 220)]
    for pts, fill in zip(ridges, fills):
        d.polygon(pts, fill=fill)
    # Lake
    d.ellipse((510, 842, 1170, 1115), fill=(77, 129, 123, 160))
    # Contour lines
    for i in range(11):
        y = 805 + i * 38
        pts = [(0, y + math.sin(x / 70 + i) * 22) for x in range(0, W + 20, 40)]
        d.line(pts, fill=(235, 220, 195, 62), width=2)
    # Foreground leaves
    for x in range(760, 1200, 55):
        for y in range(680, 1060, 48):
            if (x + y) % 3 == 0:
                d.line((x, y + 78, x + 22, y), fill=(45, 75, 50, 170), width=3)
                d.ellipse((x + 8, y + 12, x + 43, y + 40), fill=(77, 110, 62, 160))
                d.ellipse((x - 9, y + 38, x + 24, y + 65), fill=(65, 95, 56, 150))


def slide3():
    img = Image.new("RGB", (W, H), CREAM).convert("RGBA")
    draw_mountain_scene(img)
    d = ImageDraw.Draw(img, "RGBA")
    d.rectangle((0, 0, 505, H), fill=(242, 233, 217, 228))
    d.text((118, 180), "ORIGIN", font=f("sans_b", 26), fill=INK)
    d.line((118, 225, 217, 225), fill=MAROON, width=4)
    d.text((118, 282), "MT. IJEN\nEAST JAVA", font=f("display", 54), fill=INK, spacing=6)
    items = [
        ("origin", "> 1.200 MDPL"),
        ("process", "NATURAL PROCESS"),
        ("arabica", "100% ARABICA"),
    ]
    y = 545
    for kind, label in items:
        icon_line(d, (118, y - 22), kind, MAROON, .65)
        d.text((190, y), label, font=f("sans_b", 25), fill=INK)
        y += 112
    footer_band(img, "From the highlands of Mt. Ijen, East Java", "Indonesia")
    slide_number(img, 3)
    return img.convert("RGB")


def slide4():
    img = add_paper_texture(canvas((240, 229, 213)), 4).convert("RGBA")
    d = ImageDraw.Draw(img, "RGBA")
    # Coffee bean field
    rng = random.Random(40)
    for _ in range(135):
        cx = rng.randint(580, 1260)
        cy = rng.randint(360, 1010)
        r = rng.randint(18, 42)
        bean(img, cx, cy, r, rng.randint(-55, 55), fill=rng.choice([(65, 31, 19), (81, 42, 25), (99, 55, 35)]))
    d.rectangle((0, 0, 690, H), fill=(242, 233, 217, 226))
    d.text((118, 132), "COFFEE CHARACTER", font=f("sans_b", 27), fill=INK)
    d.line((118, 174, 250, 174), fill=MAROON, width=4)
    attrs = [
        ("sun", "LIGHT - MEDIUM ROAST"),
        ("sweet", "FRUITY"),
        ("drop", "SWEET"),
        ("arabica", "CLEAN"),
        ("cup", "MEDIUM BODY"),
    ]
    y = 300
    for kind, label in attrs:
        icon_line(d, (122, y - 35), kind, MAROON, .62)
        d.text((210, y), label, font=f("sans_b", 25), fill=INK)
        y += 113
    footer_band(img, "Sweet, fruity, and clean", "with balanced body")
    slide_number(img, 4)
    return img.convert("RGB")


def draw_brew_icon(draw, cx, cy, kind, color=MAROON):
    if kind in {"V60", "ORIGAMI", "KALITA"}:
        if kind == "ORIGAMI":
            for i in range(7):
                x0 = cx - 60 + i * 20
                draw.polygon([(x0, cy - 34), (x0 + 20, cy - 34), (cx, cy + 48)], outline=(179, 132, 83), fill=None)
        draw.polygon([(cx - 78, cy - 42), (cx + 78, cy - 42), (cx + 46, cy + 58), (cx - 46, cy + 58)], outline=color, width=4)
        draw.line((cx - 96, cy - 42, cx + 96, cy - 42), fill=color, width=4)
        draw.arc((cx - 35, cy + 45, cx + 35, cy + 88), 0, 180, fill=color, width=4)
    elif kind == "AEROPRESS":
        draw.rounded_rectangle((cx - 36, cy - 74, cx + 36, cy + 70), radius=12, outline=color, width=4)
        draw.rectangle((cx - 48, cy - 92, cx + 48, cy - 74), outline=color, width=4)
        draw.rectangle((cx - 48, cy + 70, cx + 48, cy + 88), outline=color, width=4)
        draw.line((cx - 24, cy - 50, cx + 24, cy - 50), fill=(176, 132, 85), width=4)
    elif kind == "FRENCH PRESS":
        draw.rounded_rectangle((cx - 50, cy - 54, cx + 50, cy + 72), radius=12, outline=color, width=4)
        draw.line((cx, cy - 92, cx, cy + 55), fill=color, width=4)
        draw.line((cx - 44, cy - 12, cx + 44, cy - 12), fill=(176, 132, 85), width=4)
        draw.arc((cx + 45, cy - 19, cx + 95, cy + 35), -80, 80, fill=color, width=4)
    elif kind == "VIETNAM DRIP":
        draw.rectangle((cx - 55, cy - 54, cx + 55, cy + 25), outline=color, width=4)
        draw.line((cx - 76, cy + 25, cx + 76, cy + 25), fill=color, width=4)
        draw.arc((cx - 42, cy + 25, cx + 42, cy + 93), 0, 180, fill=color, width=4)


def slide5():
    img = add_paper_texture(canvas((246, 239, 229)), 5).convert("RGBA")
    d = ImageDraw.Draw(img, "RGBA")
    d.text((600, 85), "BREW IT YOUR WAY", font=f("sans_b", 30), fill=INK, anchor="mm")
    d.line((537, 123, 663, 123), fill=MAROON, width=3)
    methods = ["V60", "ORIGAMI", "KALITA", "AEROPRESS", "FRENCH PRESS", "VIETNAM DRIP"]
    positions = [(270, 270), (600, 270), (930, 270), (270, 610), (600, 610), (930, 610)]
    for label, pos in zip(methods, positions):
        draw_brew_icon(d, pos[0], pos[1], label)
        d.text((pos[0], pos[1] + 135), label, font=f("sans_b", 19), fill=INK, anchor="mm")
    d.line((160, 893, 1040, 893), fill=(177, 112, 80, 102), width=2)
    d.text((600, 975), "Recommended for filter coffee and manual brew.", font=f("serif", 28), fill=INK, anchor="mm")
    slide_number(img, 5)
    return img.convert("RGB")


def grind_disc(draw, cx, cy, mode):
    rng = random.Random(mode)
    draw.ellipse((cx - 68, cy - 68, cx + 68, cy + 68), fill=(222, 197, 160), outline=(119, 78, 47), width=2)
    if mode == 1:
        for _ in range(18):
            bean(draw, cx + rng.randint(-45, 45), cy + rng.randint(-40, 40), rng.randint(8, 16), rng.randint(-70, 70))
    else:
        count = {2: 80, 3: 180, 4: 340}[mode]
        radius = {2: 7, 3: 4, 4: 2}[mode]
        for _ in range(count):
            x = cx + rng.randint(-52, 52)
            y = cy + rng.randint(-52, 52)
            if (x - cx) ** 2 + (y - cy) ** 2 <= 58 ** 2:
                shade = rng.randint(52, 102)
                draw.ellipse((x-radius, y-radius, x+radius, y+radius), fill=(shade, 42, 24, 218))


def slide6():
    img = add_paper_texture(canvas((243, 232, 216)), 6).convert("RGBA")
    d = ImageDraw.Draw(img, "RGBA")
    d.text((600, 76), "PILIHAN GILINGAN", font=f("sans_b", 30), fill=INK, anchor="mm")
    d.line((535, 113, 665, 113), fill=MAROON, width=3)
    rows = [
        (1, "WHOLE BEAN", "Biji utuh"),
        (2, "COARSE / KASAR", "French Press, Cold Brew"),
        (3, "MEDIUM", "V60, Kalita, Vietnam Drip"),
        (4, "FINE", "Espresso, AeroPress, Tubruk"),
    ]
    y = 218
    for mode, title, desc in rows:
        grind_disc(d, 275, y + 38, mode)
        d.text((405, y - 19), title, font=f("sans_b", 30), fill=MAROON)
        d.text((405, y + 23), desc, font=f("sans", 24), fill=INK)
        y += 202
    footer_band(img, "Tingkat giling disesuaikan", "untuk metode seduh pilihan Anda")
    slide_number(img, 6)
    return img.convert("RGB")


def slide7():
    img = add_paper_texture(canvas((238, 226, 207)), 7).convert("RGBA")
    d = ImageDraw.Draw(img, "RGBA")
    # Quiet close-up packaging surface, built from the real pouch crop.
    src = Image.open(PRODUCT_PATH).convert("RGBA")
    pouch = src.crop((215, 350, 860, 1390)).resize((630, 1015), Image.Resampling.LANCZOS)
    pouch = ImageEnhance.Contrast(pouch).enhance(.92)
    paste_alpha(img, pouch, (635, 85), 178)
    d.rectangle((0, 0, 710, H), fill=(241, 232, 216, 224))
    d.text((120, 180), "FRESHLY\nROASTED", font=f("display", 60), fill=INK, spacing=8)
    d.ellipse((122, 390, 220, 488), outline=MAROON, width=3)
    icon_line(d, (131, 398), "arabica", MAROON, .98)
    notes = [
        ("Tanggal roasting dicantumkan pada kemasan.", 555),
        ("Dikemas untuk menjaga aroma dan karakter kopi.", 674),
        ("Simpan rapat di tempat sejuk dan kering.", 793),
    ]
    for body, y in notes:
        d.ellipse((124, y + 6, 142, y + 24), fill=MAROON)
        for i, line in enumerate(wrap(d, body, f("sans", 25), 440)):
            d.text((168, y + i * 32), line, font=f("sans", 25), fill=INK)
    footer_band(img, "Freshness first", "small-batch roasted coffee")
    slide_number(img, 7)
    return img.convert("RGB")


def draw_pour_over_overlay(img):
    d = ImageDraw.Draw(img, "RGBA")
    # Minimal line-art pour over added over the real coffee-bar photo.
    cx, cy = 300, 655
    d.polygon([(cx - 120, cy - 110), (cx + 120, cy - 110), (cx + 72, cy + 80), (cx - 72, cy + 80)], outline=(248, 235, 214, 210), width=5)
    for i in range(7):
        x = cx - 105 + i * 35
        d.line((x, cy - 108, cx, cy + 72), fill=(248, 235, 214, 70), width=2)
    d.arc((cx - 80, cy + 65, cx + 80, cy + 155), 0, 180, fill=(248, 235, 214, 210), width=5)
    d.line((cx - 130, cy - 110, cx + 130, cy - 110), fill=(248, 235, 214, 210), width=5)
    d.line((cx + 150, cy - 220, cx + 15, cy - 115), fill=(248, 235, 214, 190), width=5)
    d.arc((cx + 70, cy - 310, cx + 245, cy - 170), 180, 285, fill=(248, 235, 214, 190), width=5)


def slide8():
    img = shade_panel(cover_crop(), "bottom").convert("RGBA")
    d = ImageDraw.Draw(img, "RGBA")
    d.rectangle((0, 0, W, H), fill=(40, 24, 18, 35))
    draw_pour_over_overlay(img)
    pouch = product_cut(410)
    shadow = Image.new("RGBA", pouch.size, (0, 0, 0, 0))
    sd = ImageDraw.Draw(shadow)
    sd.rounded_rectangle((14, 20, pouch.width - 8, pouch.height - 6), radius=36, fill=(0, 0, 0, 80))
    shadow = shadow.filter(ImageFilter.GaussianBlur(18))
    paste_alpha(img, shadow, (744, 118), 180)
    paste_alpha(img, pouch, (726, 88), 248)
    d.rectangle((0, 0, 690, H), fill=(40, 24, 18, 95))
    draw_pour_over_overlay(img)
    paste_alpha(img, load_logo(150, CREAM), (72, 55), 230)
    d.text((120, 820), "BREWED FOR\nBETTER MOMENTS", font=f("display", 55), fill=CREAM, spacing=4)
    d.line((122, 948, 470, 948), fill=GOLD, width=4)
    d.text((122, 982), "NAMUA COFFEE ROASTERS", font=f("sans_b", 23), fill=(236, 215, 190))
    d.text((122, 1025), "IJEN FIRST LIGHT", font=f("sans", 22), fill=(236, 215, 190))
    slide_number(img, 8)
    return img.convert("RGB")


def save_under_2mb(img: Image.Image, path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    q = 92
    while q >= 60:
        img.save(path, "JPEG", quality=q, optimize=True, progressive=True)
        if path.stat().st_size <= 2 * 1024 * 1024:
            return
        q -= 4
    img.save(path, "JPEG", quality=60, optimize=True, progressive=True)


def main():
    slides = [slide1, slide2, slide3, slide4, slide5, slide6, slide7, slide8]
    for idx, maker in enumerate(slides, 1):
        img = maker()
        out = OUT_DIR / f"ijen-first-light-slide-{idx:02d}.jpg"
        save_under_2mb(img, out)
        print(f"{out.relative_to(ROOT)}\t{out.stat().st_size}")


if __name__ == "__main__":
    main()
