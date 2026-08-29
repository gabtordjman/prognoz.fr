#!/usr/bin/env python3
"""Prognoz promo badges — retro 88x31 + modern banner, site palette."""

from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont

DIR = Path(__file__).resolve().parent

# Site CSS tokens
BG = (15, 26, 20)           # --bg
FELT = (26, 50, 38)         # --felt
FELT_LT = (35, 68, 50)      # --felt-light
GREEN = (45, 107, 72)       # --green
GREEN_DK = (30, 80, 53)     # --green-dark
GREEN_LT = (74, 148, 104)   # --green-light
BRASS = (154, 116, 32)      # --brass
BRASS_LT = (196, 160, 53)   # --brass-light
BRASS_SH = (232, 208, 120)  # --brass-shine
PAPER = (244, 241, 228)     # cream
PAPER_DK = (228, 217, 196)  # --paper
INK = (26, 22, 18)          # --ink
WOOD = (61, 42, 26)
WHITE = (255, 255, 255)
BLACK = (0, 0, 0)
SILVER = (200, 200, 190)
GRAY = (110, 110, 100)
DK = (40, 40, 35)

FONT5: dict[str, list[str]] = {
    "A": ["01110", "10001", "10001", "11111", "10001", "10001", "10001"],
    "B": ["11110", "10001", "10001", "11110", "10001", "10001", "11110"],
    "C": ["01110", "10001", "10000", "10000", "10000", "10001", "01110"],
    "D": ["11110", "10001", "10001", "10001", "10001", "10001", "11110"],
    "E": ["11111", "10000", "10000", "11110", "10000", "10000", "11111"],
    "F": ["11111", "10000", "10000", "11110", "10000", "10000", "10000"],
    "G": ["01110", "10001", "10000", "10111", "10001", "10001", "01110"],
    "H": ["10001", "10001", "10001", "11111", "10001", "10001", "10001"],
    "I": ["11111", "00100", "00100", "00100", "00100", "00100", "11111"],
    "J": ["00111", "00010", "00010", "00010", "00010", "10010", "01100"],
    "K": ["10001", "10010", "10100", "11000", "10100", "10010", "10001"],
    "L": ["10000", "10000", "10000", "10000", "10000", "10000", "11111"],
    "M": ["10001", "11011", "10101", "10001", "10001", "10001", "10001"],
    "N": ["10001", "11001", "10101", "10011", "10001", "10001", "10001"],
    "O": ["01110", "10001", "10001", "10001", "10001", "10001", "01110"],
    "P": ["11110", "10001", "10001", "11110", "10000", "10000", "10000"],
    "Q": ["01110", "10001", "10001", "10001", "10101", "10010", "01101"],
    "R": ["11110", "10001", "10001", "11110", "10100", "10010", "10001"],
    "S": ["01111", "10000", "10000", "01110", "00001", "00001", "11110"],
    "T": ["11111", "00100", "00100", "00100", "00100", "00100", "00100"],
    "U": ["10001", "10001", "10001", "10001", "10001", "10001", "01110"],
    "V": ["10001", "10001", "10001", "10001", "10001", "01010", "00100"],
    "W": ["10001", "10001", "10001", "10001", "10101", "11011", "10001"],
    "X": ["10001", "10001", "01010", "00100", "01010", "10001", "10001"],
    "Y": ["10001", "10001", "01010", "00100", "00100", "00100", "00100"],
    "Z": ["11111", "00001", "00010", "00100", "01000", "10000", "11111"],
    " ": ["00000", "00000", "00000", "00000", "00000", "00000", "00000"],
    "!": ["00100", "00100", "00100", "00100", "00100", "00000", "00100"],
    ".": ["00000", "00000", "00000", "00000", "00000", "00000", "00100"],
    "É": ["01110", "00000", "11111", "10000", "11110", "10000", "11111"],
    "È": ["01100", "00000", "11111", "10000", "11110", "10000", "11111"],
}


def put_text(img: Image.Image, x: int, y: int, text: str, color, gap: int = 6) -> None:
    px = x
    for ch in text.upper():
        glyph = FONT5.get(ch, FONT5.get(ch.upper(), FONT5[" "]))
        for row, bits in enumerate(glyph):
            for col, bit in enumerate(bits):
                if bit == "1":
                    xx, yy = px + col, y + row
                    if 0 <= xx < img.width and 0 <= yy < img.height:
                        img.putpixel((xx, yy), color)
        px += gap


def lerp(a, b, t):
    return tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(3))


# ── Retro 88×31 ──────────────────────────────────────────────

def draw_eight_ball(draw: ImageDraw.ImageDraw, cx: int, cy: int, r: int, shine: bool) -> None:
    draw.ellipse([cx - r, cy - r, cx + r, cy + r], fill=(18, 18, 18), outline=BRASS_LT)
    ir = max(3, r // 2 + 1)
    draw.ellipse([cx - ir, cy - ir, cx + ir, cy + ir], fill=PAPER)
    # crisp pixel "8"
    eight = [
        "01110",
        "10001",
        "10001",
        "01110",
        "10001",
        "10001",
        "01110",
    ]
    ox, oy = cx - 2, cy - 3
    for row, bits in enumerate(eight):
        for col, bit in enumerate(bits):
            if bit == "1":
                draw.point((ox + col, oy + row), fill=INK)
    if shine:
        draw.point([(cx - r + 2, cy - r + 2), (cx - r + 3, cy - r + 1)], fill=BRASS_SH)


def make_retro_frame(phase: int) -> Image.Image:
    W, H = 88, 31
    # punchier felt — brighter than CSS so the tiny badge reads as inviting
    felt_hi = (48, 110, 72)
    felt_lo = (18, 40, 28)
    img = Image.new("RGB", (W, H), felt_lo)
    draw = ImageDraw.Draw(img)

    for y in range(3, H - 3):
        t = (y - 3) / max(1, H - 7)
        c = lerp(felt_hi, felt_lo, t * 0.85)
        for x in range(3, W - 3):
            img.putpixel((x, y), c)

    # brass rail shimmer
    for x in range(3, W - 3):
        img.putpixel((x, 3), BRASS_LT)
        img.putpixel((x, 4), BRASS_SH if (x + phase) % 3 != 0 else (255, 240, 160))

    draw.rectangle([0, 0, W - 1, H - 1], outline=BLACK)
    draw.line([(1, 1), (W - 2, 1)], fill=(255, 240, 160))
    draw.line([(1, 1), (1, H - 2)], fill=BRASS_SH)
    draw.line([(1, H - 2), (W - 2, H - 2)], fill=WOOD)
    draw.line([(W - 2, 1), (W - 2, H - 2)], fill=WOOD)

    draw_eight_ball(draw, 9, 17, 6, shine=(phase % 2 == 0))

    top_on = phase != 5
    top_col = (255, 236, 140) if phase % 2 == 0 else PAPER
    bot_col = PAPER if phase % 2 == 0 else (255, 236, 140)

    if top_on:
        put_text(img, 17, 7, "PARIEZ SUR", top_col)
    put_text(img, 20, 17, "PROGNOZ", bot_col)

    sparks = {
        0: [(82, 8, (255, 240, 160)), (84, 24, GREEN_LT)],
        1: [(80, 10, PAPER)],
        2: [(84, 9, BRASS_SH), (81, 23, (120, 200, 140))],
        3: [(83, 22, (255, 240, 160))],
        4: [(82, 8, PAPER)],
        5: [(84, 10, GREEN_LT), (80, 24, BRASS_SH)],
    }
    for x, y, col in sparks.get(phase, []):
        if 0 <= x < W and 0 <= y < H:
            img.putpixel((x, y), col)

    return img


def save_retro() -> Path:
    frames = [make_retro_frame(i) for i in range(6)]
    pal = frames[0].quantize(colors=24, method=Image.Quantize.MEDIANCUT)
    q = [f.quantize(palette=pal) for f in frames]
    out = DIR / "pariez-sur-prognoz-retro.gif"
    q[0].save(
        out,
        save_all=True,
        append_images=q[1:],
        duration=[300, 300, 300, 300, 300, 140],
        loop=0,
        optimize=False,
        disposal=2,
    )
    frames[0].resize((88 * 6, 31 * 6), Image.NEAREST).save(DIR / "pariez-sur-prognoz-retro-preview.png")
    print(f"retro -> {out.name} ({out.stat().st_size} B)")
    return out


# ── Modern banner ────────────────────────────────────────────

def load_font(size: int, bold: bool = True):
    paths = [
        "C:/Windows/Fonts/segoeuib.ttf" if bold else "C:/Windows/Fonts/segoeui.ttf",
        "C:/Windows/Fonts/arialbd.ttf" if bold else "C:/Windows/Fonts/arial.ttf",
        "C:/Windows/Fonts/calibrib.ttf" if bold else "C:/Windows/Fonts/calibri.ttf",
    ]
    for p in paths:
        try:
            return ImageFont.truetype(p, size)
        except OSError:
            continue
    return ImageFont.load_default()


def draw_modern_eight(draw: ImageDraw.ImageDraw, cx: int, cy: int, r: int, glow: float) -> None:
    ring = lerp(BRASS, BRASS_SH, glow)
    draw.ellipse([cx - r - 3, cy - r - 3, cx + r + 3, cy + r + 3], outline=ring, width=2)
    draw.ellipse([cx - r, cy - r, cx + r, cy + r], fill=(14, 14, 14), outline=BRASS_LT, width=2)
    ir = int(r * 0.5)
    draw.ellipse([cx - ir, cy - ir, cx + ir, cy + ir], fill=PAPER)
    f = load_font(max(14, int(r * 1.15)), bold=True)
    # center the "8"
    try:
        bb = draw.textbbox((0, 0), "8", font=f)
        tw, th = bb[2] - bb[0], bb[3] - bb[1]
    except Exception:
        tw, th = 10, 14
    draw.text((cx - tw // 2, cy - th // 2 - 1), "8", fill=INK, font=f)
    # specular
    draw.ellipse([cx - r + 4, cy - r + 5, cx - r + 10, cy - r + 11], fill=(60, 60, 60))


def make_modern_frame(phase: int, w: int = 320, h: int = 80) -> Image.Image:
    img = Image.new("RGB", (w, h), BG)
    pixels = img.load()

    # rich felt radial + gold light sweep (vector-friendly loop)
    sweep_x = (phase / 8.0) * w
    for y in range(h):
        for x in range(w):
            nx = (x / w - 0.28) ** 2
            ny = (y / h - 0.42) ** 2
            t = min(1.0, (nx * 1.4 + ny) * 1.6)
            base = lerp((42, 92, 62), BG, t)  # brighter felt center
            # moving brass caustic
            d = abs(x - sweep_x)
            shimmer = max(0.0, 1.0 - d / 28.0) * 0.22
            c = lerp(base, BRASS_SH, shimmer * 0.45)
            c = lerp(c, GREEN_LT, shimmer * 0.2)
            if y < 4 or y > h - 5:
                edge = 0.7 if y < 2 or y > h - 3 else 0.35
                c = lerp(c, BRASS_LT, edge)
            pixels[x, y] = c

    draw = ImageDraw.Draw(img)

    # soft brass frame
    draw.rounded_rectangle([1, 1, w - 2, h - 2], radius=12, outline=BRASS_LT, width=2)
    draw.rounded_rectangle([4, 4, w - 5, h - 5], radius=10, outline=lerp(BRASS, FELT, 0.55), width=1)

    glow = 0.25 + 0.75 * abs(((phase % 8) / 4) - 1)
    draw_modern_eight(draw, 42, h // 2, 24, glow)

    title = load_font(20, bold=True)
    sub = load_font(12, bold=False)
    cta_font = load_font(11, bold=True)

    draw.text((78, 12), "Pariez sur Prognoz", fill=PAPER, font=title)
    draw.text((78, 36), "Pronos  ·  Classement  ·  Fun", fill=GREEN_LT, font=sub)

    # CTA — short label so it never overflows
    label = "C'EST PARTI →"
    cta_bg = lerp(BRASS_LT, BRASS_SH, 0.2 + 0.6 * ((phase % 6) / 5))
    # measure text for padding
    try:
        bbox = draw.textbbox((0, 0), label, font=cta_font)
        tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    except Exception:
        tw, th = 110, 12
    pad_x, pad_y = 14, 5
    bx0, by0 = 78, 52
    bx1, by1 = bx0 + tw + pad_x * 2, by0 + th + pad_y * 2
    draw.rounded_rectangle([bx0, by0, bx1, by1], radius=8, fill=cta_bg, outline=BRASS_SH)
    draw.text((bx0 + pad_x, by0 + pad_y - 1), label, fill=INK, font=cta_font)

    # sparkles
    if phase % 2 == 0:
        draw.ellipse([bx1 + 4, by0, bx1 + 8, by0 + 4], fill=BRASS_SH)
        draw.ellipse([w - 18, 14, w - 14, 18], fill=PAPER)
    else:
        draw.ellipse([bx1 + 8, by1 - 6, bx1 + 11, by1 - 3], fill=PAPER)
        draw.ellipse([w - 22, h - 18, w - 18, h - 14], fill=GREEN_LT)

    return img


def save_modern() -> Path:
    """Prefer AI base banner if present; else procedural."""
    ai = DIR / "pariez-sur-prognoz-modern-base.png"
    out = DIR / "pariez-sur-prognoz-modern.gif"
    if ai.exists():
        from PIL import ImageEnhance, ImageChops, ImageFilter

        base = Image.open(ai).convert("RGB").resize((320, 180), Image.LANCZOS)
        frames = []
        w, h = base.size
        for i in range(10):
            f = base.copy()
            factor = 1.0 + 0.05 * abs(((i % 10) / 5) - 1)
            f = ImageEnhance.Brightness(f).enhance(factor)
            f = ImageEnhance.Color(f).enhance(1.04 + 0.06 * ((i % 5) / 4))
            overlay = Image.new("RGB", (w, h), (0, 0, 0))
            draw = ImageDraw.Draw(overlay)
            cx = int((i / 10) * (w + 100) - 50)
            for x in range(w):
                d = abs(x - cx)
                if d < 40:
                    a = int(36 * (1 - d / 40))
                    col = (232, 208, 120)
                    draw.line([(x, 0), (x, h)], fill=tuple(int(c * a / 255) for c in col))
            overlay = overlay.filter(ImageFilter.GaussianBlur(10))
            f = Image.blend(f, ImageChops.screen(f, overlay), 0.16)
            frames.append(f)
        pal = frames[0].quantize(colors=80, method=Image.Quantize.MEDIANCUT)
        q = [fr.quantize(palette=pal) for fr in frames]
        q[0].save(
            out,
            save_all=True,
            append_images=q[1:],
            duration=90,
            loop=0,
            optimize=True,
            disposal=2,
        )
        frames[3].save(DIR / "pariez-sur-prognoz-modern-preview.png")
        print(f"modern (ai) -> {out.name} ({out.stat().st_size} B)")
        return out

    frames = [make_modern_frame(i) for i in range(8)]
    pal = frames[0].quantize(colors=64, method=Image.Quantize.MEDIANCUT)
    q = [f.quantize(palette=pal) for f in frames]
    q[0].save(
        out,
        save_all=True,
        append_images=q[1:],
        duration=120,
        loop=0,
        optimize=False,
        disposal=2,
    )
    frames[2].save(DIR / "pariez-sur-prognoz-modern-preview.png")
    print(f"modern -> {out.name} ({out.stat().st_size} B)")
    return out


def main() -> None:
    # replace old sad navy badge
    old = DIR / "pariez-sur-prognoz.gif"
    if old.exists():
        old.unlink()
    old_prev = DIR / "pariez-sur-prognoz-preview.png"
    if old_prev.exists():
        old_prev.unlink()

    save_retro()
    save_modern()
    print("done")


if __name__ == "__main__":
    main()
