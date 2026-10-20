#!/usr/bin/env python3
"""Builds the brand: the logo, the favicons and app icons, the Safari/Windows tile icons and the social-share image.

    pip install fonttools pillow
    python3 marketing/brand/build_brand.py

Writes into public/ (everything the site serves):
    logo.svg, logo-dark.svg      mark + "Love of My Life" (the words are outlines, no font needed), for light / dark backgrounds
    logo-mark.svg, favicon.svg   the mark alone (a heart with a keyhole: the question that opens a love page)
    safari-pinned-tab.svg        the mark in one colour, for Safari's pinned tabs
    favicon.ico                  16, 32 and 48 px for old browsers and crawlers that ask for /favicon.ico
    apple-touch-icon.png         180 px, full square (iOS rounds it itself)
    icons/favicon-16|32|48.png, icon-192|512.png (rounded), icon-maskable-192|512.png (full bleed, mark inside the safe zone),
    icons/mstile-150.png, logo.png (mark + words on transparent, 1200 px wide)
    og-image.png                 1200x630 for link previews (Open Graph, Twitter)
Also writes marketing/brand/app-icon-1024.png: the 1024x1024 app icon for the App Store / Google Play (opaque, square, full bleed:
the stores round the corners themselves; no transparency).

The mark is drawn once, in HEART/KEYHOLE below, and both the SVGs and the PNGs come from those numbers, so they match.
The words are set in Cormorant Garamond Italic (SIL Open Font License), fetched into a cache folder on the first run.
Colours are the product's own (marketing/brand/LETTIE.md): the heart goes from #f58fa8 through #e0607e to #b8405f, on the
night background #2a1220 to #170b14.
"""

from __future__ import annotations

import math
import os
import sys
import urllib.request
from pathlib import Path

from fontTools.pens.boundsPen import BoundsPen
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer
from PIL import Image, ImageDraw, ImageFilter, ImageFont

ROOT = Path(__file__).resolve().parents[2]
PUBLIC = ROOT / "public"
HERE = Path(__file__).resolve().parent
CACHE = Path(os.environ.get("BRAND_FONT_CACHE", Path.home() / ".cache" / "love-of-my-life-brand"))
FONT_URL = "https://raw.githubusercontent.com/google/fonts/main/ofl/cormorantgaramond/CormorantGaramond-Italic%5Bwght%5D.ttf"
FONT_FILE = CACHE / "CormorantGaramond-Italic[wght].ttf"
BRAND_NAME = "Love of My Life"
TAGLINE = "A love letter they unlock with a word only they know."

# --- colours -------------------------------------------------------------------------------------------------------
ROSE_TOP, ROSE_MID, ROSE_BOTTOM = "#f58fa8", "#e0607e", "#b8405f"
CREAM = "#fffaf3"
NIGHT_TOP, NIGHT_BOTTOM = "#2a1220", "#170b14"
INK = "#2a1621"          # the words on a light background
LIGHT_TEXT = "#fbeef0"   # the words on a dark background
GOLD = "#f0cfa8"

# --- the mark, in a 64 x 64 box -------------------------------------------------------------------------------------
# A heart (cubic Beziers: start, then (control, control, end) per segment), a keyhole and a soft highlight.
HEART = [(32, 57), [((32, 57), (6, 41), (6, 22)), ((6, 13), (13, 7), (21, 7)), ((26, 7), (30, 10), (32, 14)),
                    ((34, 10), (38, 7), (43, 7)), ((51, 7), (58, 13), (58, 22)), ((58, 41), (32, 57), (32, 57))]]
HEART_SVG = "M32 57C32 57 6 41 6 22C6 13 13 7 21 7C26 7 30 10 32 14C34 10 38 7 43 7C51 7 58 13 58 22C58 41 32 57 32 57Z"
KEYHOLE_CIRCLE = (32.0, 27.0, 6.2)  # cx, cy, r
KEYHOLE_STEM = [(28.4, 31.0), (35.6, 31.0), (38.0, 44.0), (38.0, 45.5), (36.5, 45.5), (27.5, 45.5), (26.0, 45.5), (26.0, 44.0)]
KEYHOLE_STEM_SVG = "M28.4 31H35.6L38 44Q38 45.5 36.5 45.5H27.5Q26 45.5 26 44Z"
HIGHLIGHT = (19.0, 18.0, 6.0, 3.4, -35.0)  # cx, cy, rx, ry, rotation in degrees
HEART_BBOX = (6.0, 7.0, 58.0, 57.0)


def gradient_defs(gid: str = "h") -> str:
    return (f'<linearGradient id="{gid}" x1="0" y1="7" x2="0" y2="57" gradientUnits="userSpaceOnUse">'
            f'<stop offset="0" stop-color="{ROSE_TOP}"/><stop offset=".55" stop-color="{ROSE_MID}"/><stop offset="1" stop-color="{ROSE_BOTTOM}"/>'
            "</linearGradient>")


def mark_svg_body(gid: str = "h") -> str:
    cx, cy, rx, ry, rot = HIGHLIGHT
    return (f'<path d="{HEART_SVG}" fill="url(#{gid})"/>'
            f'<ellipse cx="{cx}" cy="{cy}" rx="{rx}" ry="{ry}" transform="rotate({rot} {cx} {cy})" fill="#fff" opacity=".3"/>'
            f'<circle cx="{KEYHOLE_CIRCLE[0]}" cy="{KEYHOLE_CIRCLE[1]}" r="{KEYHOLE_CIRCLE[2]}" fill="{CREAM}"/>'
            f'<path d="{KEYHOLE_STEM_SVG}" fill="{CREAM}"/>')


def write(path: Path, data: str | bytes) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(data.encode() if isinstance(data, str) else data)
    print(f"  {path.relative_to(ROOT)}  ({path.stat().st_size:,} bytes)")


# --- the words, as outlines -----------------------------------------------------------------------------------------

def load_font() -> TTFont:
    if not FONT_FILE.exists():
        FONT_FILE.parent.mkdir(parents=True, exist_ok=True)
        print(f"fetching {FONT_URL}")
        urllib.request.urlretrieve(FONT_URL, FONT_FILE)
    return instancer.instantiateVariableFont(TTFont(FONT_FILE), {"wght": 500})


def wordmark(text: str, size: float, tracking: float = 0.0) -> tuple[str, tuple[float, float, float, float]]:
    """The text as one SVG path (baseline at y = 0, starting at x = 0) and its bounding box (x0, y0, x1, y1)."""
    font = load_font()
    glyphs, cmap = font.getGlyphSet(), font.getBestCmap()
    scale = size / font["head"].unitsPerEm
    x, parts = 0.0, []
    box = [math.inf, math.inf, -math.inf, -math.inf]
    for char in text:
        name = cmap[ord(char)]
        transform = (scale, 0, 0, -scale, x, 0)
        pen = SVGPathPen(glyphs, ntos=lambda v: f"{v:.2f}".rstrip("0").rstrip("."))
        glyphs[name].draw(TransformPen(pen, transform))
        parts.append(pen.getCommands())
        bounds = BoundsPen(glyphs)
        glyphs[name].draw(TransformPen(bounds, transform))
        if bounds.bounds:
            box = [min(box[0], bounds.bounds[0]), min(box[1], bounds.bounds[1]), max(box[2], bounds.bounds[2]), max(box[3], bounds.bounds[3])]
        x += glyphs[name].width * scale + tracking
    return " ".join(p for p in parts if p), tuple(box)  # type: ignore[return-value]


def logo_svg(text_fill: str, title: str = BRAND_NAME) -> str:
    """Mark on the left, the name to its right, vertically centred on the mark."""
    path, (x0, y0, x1, y1) = wordmark(BRAND_NAME, 50, tracking=0.4)
    gap, left = 14.0, 3.0
    mark_x = left - HEART_BBOX[0]                       # the mark's left edge sits at x = left
    text_x = left + (HEART_BBOX[2] - HEART_BBOX[0]) + gap - x0
    text_y = 32.0 - (y0 + y1) / 2                       # the text's middle on the mark's middle (y = 32)
    width = text_x + x1 + 3.0
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width:.1f} 64" width="{width * 2:.0f}" height="128" role="img" aria-labelledby="t">'
            f'<title id="t">{title}</title><defs>{gradient_defs()}</defs>'
            f'<g transform="translate({mark_x} 0)">{mark_svg_body()}</g>'
            f'<path transform="translate({text_x:.2f} {text_y:.2f})" d="{path}" fill="{text_fill}"/></svg>\n')


def mark_svg(title: str = BRAND_NAME) -> str:
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img" aria-labelledby="t"><title id="t">{title}</title>'
            f"<defs>{gradient_defs()}</defs>{mark_svg_body()}</svg>\n")


def pinned_tab_svg() -> str:
    cx, cy, r = KEYHOLE_CIRCLE
    circle = f"M{cx - r} {cy}a{r} {r} 0 1 0 {2 * r} 0a{r} {r} 0 1 0 {-2 * r} 0Z"
    return ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><title>' + BRAND_NAME + '</title>'
            f'<path fill="#000" fill-rule="evenodd" d="{HEART_SVG}{circle}{KEYHOLE_STEM_SVG}"/></svg>\n')


# --- the mark, as pixels ---------------------------------------------------------------------------------------------
SS = 4  # supersampling


def bezier(p0, p1, p2, p3, steps=48):
    for i in range(1, steps + 1):
        t = i / steps
        u = 1 - t
        yield (u ** 3 * p0[0] + 3 * u * u * t * p1[0] + 3 * u * t * t * p2[0] + t ** 3 * p3[0],
               u ** 3 * p0[1] + 3 * u * u * t * p1[1] + 3 * u * t * t * p2[1] + t ** 3 * p3[1])


def heart_polygon():
    start, segments = HEART
    points, current = [start], start
    for c1, c2, end in segments:
        points.extend(bezier(current, c1, c2, end))
        current = end
    return points


def hex_rgb(value: str) -> tuple[int, int, int]:
    return tuple(int(value[i:i + 2], 16) for i in (1, 3, 5))  # type: ignore[return-value]


def vertical_gradient(width: int, height: int, stops: list[tuple[float, str]]) -> Image.Image:
    column = Image.new("RGB", (1, height))
    for y in range(height):
        t = y / max(1, height - 1)
        for (t0, c0), (t1, c1) in zip(stops, stops[1:]):
            if t0 <= t <= t1:
                k = (t - t0) / (t1 - t0) if t1 > t0 else 0
                a, b = hex_rgb(c0), hex_rgb(c1)
                column.putpixel((0, y), tuple(round(a[i] + (b[i] - a[i]) * k) for i in range(3)))
                break
    return column.resize((width, height))


def ellipse_polygon(cx, cy, rx, ry, rot_deg, steps=64):
    r = math.radians(rot_deg)
    return [(cx + rx * math.cos(a) * math.cos(r) - ry * math.sin(a) * math.sin(r),
             cy + rx * math.cos(a) * math.sin(r) + ry * math.sin(a) * math.cos(r)) for a in (2 * math.pi * i / steps for i in range(steps))]


def draw_mark(canvas: Image.Image, center: tuple[float, float], heart_width: float) -> None:
    """Draws the mark onto an RGBA canvas (already at supersampled size); heart_width is the heart's width in canvas pixels."""
    s = heart_width / (HEART_BBOX[2] - HEART_BBOX[0])
    cx, cy = center

    def at(pt):
        return (cx + (pt[0] - 32) * s, cy + (pt[1] - 32) * s)

    w, h = canvas.size
    mask = Image.new("L", (w, h), 0)
    ImageDraw.Draw(mask).polygon([at(p) for p in heart_polygon()], fill=255)
    top, bottom = at((0, HEART_BBOX[1]))[1], at((0, HEART_BBOX[3]))[1]
    gradient = Image.new("RGB", (w, h))
    band = vertical_gradient(w, max(2, round(bottom - top)), [(0, ROSE_TOP), (0.55, ROSE_MID), (1, ROSE_BOTTOM)])
    gradient.paste(hex_rgb(ROSE_TOP), (0, 0, w, h))
    gradient.paste(hex_rgb(ROSE_BOTTOM), (0, round(bottom), w, h))
    gradient.paste(band, (0, round(top)))
    canvas.paste(gradient, (0, 0), mask)

    hl = Image.new("L", (w, h), 0)
    hx, hy, rx, ry, rot = HIGHLIGHT
    ImageDraw.Draw(hl).polygon([at(p) for p in ellipse_polygon(hx, hy, rx, ry, rot)], fill=round(255 * 0.3))
    hl = Image.composite(hl, Image.new("L", (w, h), 0), mask)
    canvas.paste((255, 255, 255), (0, 0), hl)

    key = Image.new("L", (w, h), 0)
    kd = ImageDraw.Draw(key)
    kx, ky, kr = KEYHOLE_CIRCLE
    px, py = at((kx, ky))
    kd.ellipse([px - kr * s, py - kr * s, px + kr * s, py + kr * s], fill=255)
    kd.polygon([at(p) for p in KEYHOLE_STEM], fill=255)
    canvas.paste(hex_rgb(CREAM), (0, 0), key)


def rounded_mask(size: int, radius_fraction: float) -> Image.Image:
    mask = Image.new("L", (size, size), 0)
    ImageDraw.Draw(mask).rounded_rectangle([0, 0, size - 1, size - 1], radius=size * radius_fraction, fill=255)
    return mask


def icon(px: int, *, background: bool, heart_fraction: float, rounded: float = 0.0, glow: bool = True) -> Image.Image:
    """A square icon: the mark on the night background (background=True) or alone on transparent."""
    size = px * SS
    canvas = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    if background:
        canvas.paste(vertical_gradient(size, size, [(0, NIGHT_TOP), (1, NIGHT_BOTTOM)]), (0, 0))
        if glow:
            halo = Image.new("L", (size, size), 0)
            ImageDraw.Draw(halo).ellipse([size * 0.2, size * 0.2, size * 0.8, size * 0.8], fill=70)
            halo = halo.filter(ImageFilter.GaussianBlur(size * 0.12))
            canvas.paste(hex_rgb(ROSE_MID), (0, 0), halo)
    draw_mark(canvas, (size / 2, size / 2), size * heart_fraction)
    out = canvas.resize((px, px), Image.LANCZOS)
    if rounded:
        out.putalpha(Image.composite(out.getchannel("A"), Image.new("L", (px, px), 0), rounded_mask(px, rounded)))
    return out


def app_icon() -> Image.Image:
    """1024x1024 store icon: the night background to the edges, the mark in the middle, and no alpha channel (the App Store rejects one)."""
    return icon(1024, background=True, heart_fraction=0.56).convert("RGB")


def png_bytes(image: Image.Image) -> bytes:
    import io
    buffer = io.BytesIO()
    image.save(buffer, "PNG", optimize=True)
    return buffer.getvalue()


def pil_font(size: int, weight: int = 500) -> ImageFont.FreeTypeFont:
    font = ImageFont.truetype(str(FONT_FILE), size)
    font.set_variation_by_axes([weight])
    return font


def logo_png(text_fill: str, width: int = 1200) -> Image.Image:
    """The mark and the name on transparent, `width` px wide."""
    scale = 4
    height = 120 * scale
    mark = Image.new("RGBA", (height, height), (0, 0, 0, 0))
    draw_mark(mark, (height / 2, height / 2), height * 0.86)
    font = pil_font(round(height * 0.62))
    probe = ImageDraw.Draw(Image.new("RGBA", (10, 10)))
    left, top, right, bottom = probe.textbbox((0, 0), BRAND_NAME, font=font)
    gap = round(height * 0.12)
    canvas = Image.new("RGBA", (height + gap + (right - left) + round(height * 0.05), height), (0, 0, 0, 0))
    canvas.alpha_composite(mark, (0, 0))
    ImageDraw.Draw(canvas).text((height + gap - left, height / 2 - (top + bottom) / 2), BRAND_NAME, font=font, fill=text_fill)
    box = canvas.getbbox()
    canvas = canvas.crop(box) if box else canvas
    ratio = width / canvas.width
    return canvas.resize((width, round(canvas.height * ratio)), Image.LANCZOS)


def og_image() -> Image.Image:
    w, h = 1200, 630
    s = 2
    canvas = Image.new("RGBA", (w * s, h * s))
    canvas.paste(vertical_gradient(w * s, h * s, [(0, NIGHT_TOP), (1, NIGHT_BOTTOM)]), (0, 0))
    for (x, y, r, color, alpha) in [(0.16, 0.5, 0.42, ROSE_MID, 85), (0.95, 1.0, 0.45, GOLD, 40)]:
        glow = Image.new("L", (w * s, h * s), 0)
        ImageDraw.Draw(glow).ellipse([(x - r) * w * s, (y - r) * h * s, (x + r) * w * s, (y + r) * h * s], fill=alpha)
        canvas.paste(hex_rgb(color), (0, 0), glow.filter(ImageFilter.GaussianBlur(120 * s)))
    draw_mark(canvas, (250 * s, h * s / 2), 300 * s)

    d = ImageDraw.Draw(canvas)
    title, sub, small = pil_font(112 * s), pil_font(40 * s, 400), pil_font(27 * s, 500)
    x = 470 * s
    d.text((x, 168 * s), BRAND_NAME, font=title, fill=LIGHT_TEXT)
    d.line([(x, 318 * s), (x + 96 * s, 318 * s)], fill=GOLD, width=3 * s)
    words, line, lines = TAGLINE.split(), "", []
    for word in words:
        trial = (line + " " + word).strip()
        if d.textlength(trial, font=sub) > 650 * s and line:
            lines.append(line)
            line = word
        else:
            line = trial
    lines.append(line)
    for i, text in enumerate(lines):
        d.text((x, (348 + i * 52) * s), text, font=sub, fill=(251, 238, 240, 190))
    d.text((x, 500 * s), "PHOTOS   ·   A SONG   ·   A LETTER", font=small, fill=GOLD)
    return canvas.convert("RGB").resize((w, h), Image.LANCZOS)


def main() -> int:
    print("Building the brand into public/")
    write(PUBLIC / "logo.svg", logo_svg(INK))
    write(PUBLIC / "logo-dark.svg", logo_svg(LIGHT_TEXT))
    write(PUBLIC / "logo-mark.svg", mark_svg())
    write(PUBLIC / "favicon.svg", mark_svg())
    write(PUBLIC / "safari-pinned-tab.svg", pinned_tab_svg())

    icons = PUBLIC / "icons"
    for px in (16, 32, 48):
        write(icons / f"favicon-{px}.png", png_bytes(icon(px, background=False, heart_fraction=0.94, glow=False)))
    for px in (192, 512):
        write(icons / f"icon-{px}.png", png_bytes(icon(px, background=True, heart_fraction=0.58, rounded=0.22)))
        write(icons / f"icon-maskable-{px}.png", png_bytes(icon(px, background=True, heart_fraction=0.5)))
    write(icons / "mstile-150.png", png_bytes(icon(150, background=True, heart_fraction=0.56)))
    write(PUBLIC / "apple-touch-icon.png", png_bytes(icon(180, background=True, heart_fraction=0.58)))

    import io
    ico = io.BytesIO()
    icon(256, background=False, heart_fraction=0.94, glow=False).save(ico, format="ICO", sizes=[(16, 16), (32, 32), (48, 48)])
    write(PUBLIC / "favicon.ico", ico.getvalue())

    write(HERE / "app-icon-1024.png", png_bytes(app_icon()))
    write(PUBLIC / "logo.png", png_bytes(logo_png(INK)))
    write(PUBLIC / "og-image.png", png_bytes(og_image()))
    write(PUBLIC / "browserconfig.xml",
          '<?xml version="1.0" encoding="utf-8"?>\n<browserconfig><msapplication><tile><square150x150logo src="/icons/mstile-150.png"/>'
          '<TileColor>#1a0d17</TileColor></tile></msapplication></browserconfig>\n')
    return 0


if __name__ == "__main__":
    sys.exit(main())
