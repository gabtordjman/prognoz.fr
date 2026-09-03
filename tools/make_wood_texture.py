#!/usr/bin/env python3
"""Export a high-res bar-counter wood JPEG (wide strip, for CSS cover)."""

from pathlib import Path

from PIL import Image, ImageEnhance, ImageFilter

ROOT = Path(__file__).resolve().parents[1]
SRC = Path("/root/.cursor/projects/home-tordjman-Documents-prognoz-fr/assets/wood-counter-source.png")
OUT = ROOT / "public/assets/img/wood-counter.jpg"


def main() -> None:
    if not SRC.is_file():
        raise SystemExit(f"Source manquante : {SRC}")

    src = Image.open(SRC).convert("RGB")
    # Drop the blown highlight on the far right of the photo.
    w, h = src.size
    src = src.crop((0, 0, int(w * 0.88), h))

    # Wide strip matching a topbar: ~5:1, retina-ready.
    target_w, target_h = 2560, 512
    scaled = src.resize(
        (target_w, max(target_h, int(src.height * target_w / src.width))),
        Image.Resampling.LANCZOS,
    )
    top = max(0, (scaled.height - target_h) // 2)
    out = scaled.crop((0, top, target_w, top + target_h))
    out = out.filter(ImageFilter.UnsharpMask(radius=1.2, percent=85, threshold=2))
    out = ImageEnhance.Contrast(out).enhance(1.08)
    out = ImageEnhance.Color(out).enhance(1.04)

    OUT.parent.mkdir(parents=True, exist_ok=True)
    out.save(OUT, "JPEG", quality=90, optimize=True, progressive=True)
    print(f"Wrote {OUT} {out.size} {OUT.stat().st_size} bytes")


if __name__ == "__main__":
    main()
