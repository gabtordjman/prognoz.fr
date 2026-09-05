#!/usr/bin/env python3
"""Export a sharp bar-counter wood JPEG for CSS 100% × 100% (no cover zoom)."""

from pathlib import Path

from PIL import Image, ImageEnhance, ImageFilter

ROOT = Path(__file__).resolve().parents[1]
CANDIDATES = [
    Path("/root/.cursor/projects/home-tordjman-Documents-prognoz-fr/assets/wood-counter-source.png"),
    ROOT / "tools/assets/wood-counter-source.png",
]
OUT = ROOT / "public/assets/img/wood-counter.jpg"


def main() -> None:
    src_path = next((p for p in CANDIDATES if p.is_file()), None)
    if src_path is None:
        raise SystemExit("Source wood PNG introuvable.")

    src = Image.open(src_path).convert("RGB")
    w, h = src.size
    # Drop the blown highlight on the right; keep several planks (not one zoomed board).
    src = src.crop((
        int(w * 0.02),
        int(h * 0.16),
        int(w * 0.90),
        int(h * 0.84),
    ))

    target_w, target_h = 2048, 480
    out = src.resize((target_w, target_h), Image.Resampling.LANCZOS)
    out = out.filter(ImageFilter.UnsharpMask(radius=1.1, percent=70, threshold=3))
    out = ImageEnhance.Contrast(out).enhance(1.06)
    out = ImageEnhance.Color(out).enhance(1.03)

    OUT.parent.mkdir(parents=True, exist_ok=True)
    out.save(OUT, "JPEG", quality=84, optimize=True, progressive=True)
    print(f"Wrote {OUT} {out.size} {OUT.stat().st_size} bytes")


if __name__ == "__main__":
    main()
