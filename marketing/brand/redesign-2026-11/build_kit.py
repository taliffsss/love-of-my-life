#!/usr/bin/env python3
"""Builds the full logo kit for the chosen direction (A, "Heart Flap" with a keyhole) into ./kit.

    pip install fonttools brotli uharfbuzz skia-pathops pillow
    python3 -B marketing/brand/redesign-2026-11/build_kit.py --skill <logo-design skill folder>

Needs Chrome (for PNG/PDF rendering through the skill's render_png.py). Nothing in public/ is touched:
kit/web/ mirrors public/ so it can be copied across when the new logo goes live.
"""
import argparse
import json
import shutil
import subprocess
import sys
from pathlib import Path

import build_concepts as base  # also rewrites the concept files, byte for byte

HERE = Path(__file__).resolve().parent
KIT = HERE / "kit"
NAME = "Love of My Life"
TAGLINE = "A love letter they unlock with a word only they know."
CRIMSON, ROSE, INK, BLUSH, PAPER, NIGHT, NIGHT_TOP, GOLD = (
    "#c42f4b", "#ee5b74", "#2a1621", "#fbeef0", "#fffaf6", "#170b14", "#2a1220", "#f0cfa8")
FONT = "bricolage-grotesque.woff2"

# --- the mark ------------------------------------------------------------------------------------------------------
HEART = "M128 166C84 140 24 112 24 74C24 45 47 22 76 22C99 22 119 35 128 55C137 35 157 22 180 22C209 22 232 45 232 74C232 112 172 140 128 166Z"
KEY = ("M128 68C138 68 146 76 146 86C146 92 143.5 96.5 139 100L147 125Q149 131 142.5 131H113.5Q107 131 109 125L117 100"
       "C112.5 96.5 110 92 110 86C110 76 118 68 128 68Z")
# small cut (24 px and below): bigger keyhole, wider flap gap
KEY_SMALL = ("M128 60C142 60 153 71 153 85C153 93 149.5 99.500 144 104L154 130Q157 139 147.5 139H108.500Q99 139 102 130L112 104"
             "C106.500 99.500 103 93 103 85C103 71 114 60 128 60Z").replace(".500", ".5")
# 16 px only: the keyhole snapped to the pixel grid of the tight 212-unit box (4 px bow, 2 px foot)
KEY_16 = ("M128 62C142.6 62 154.5 72 154.5 86C154.5 94 150 100 142 104.500V130H114.500V104.500C106 100 101.500 94 101.500 86C101.500 72 113.400 62 128 62Z"
          ).replace(".500", ".5").replace(".400", ".4")


def pocket(drop=0):
    return (f"M24 {108 + drop}C45 {136 + drop} 88 {158 + drop} 128 {184 + drop}C168 {158 + drop} 211 {136 + drop} 232 {108 + drop}"
            "V206Q232 234 204 234H52Q24 234 24 206Z")


def mark(cut="regular"):
    """(heart-with-keyhole, pocket) as SVG path data. cut: regular | reversed (wider gap on dark) | small."""
    key, drop = (KEY_16, 8) if cut == "px16" else (KEY_SMALL, 8) if cut == "small" else (KEY, 3 if cut == "reversed" else 0)
    heart = base.d_of(base.minus(base.shape(HEART), base.shape(key)))
    return heart, base.d_of(base.shape(pocket(drop)))


SCHEMES = {  # name: (heart, pocket, words, cut)
    "color": (CRIMSON, INK, INK, "regular"),
    "color-dark": (ROSE, BLUSH, BLUSH, "reversed"),
    "black": ("#000000", "#000000", "#000000", "regular"),
    "white": ("#ffffff", "#ffffff", "#ffffff", "reversed"),
    "crimson": (CRIMSON, CRIMSON, CRIMSON, "regular"),
}
SYMBOL_BOX = (24, 22, 232, 234)


def mark_body(h, p, cut):
    heart, pk = mark(cut)
    return f'<path fill="{h}" d="{heart}"/><path fill="{p}" d="{pk}"/>'


def doc(body, box, title=NAME):
    x, y, w, h = (round(v, 1) for v in box)
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{x:g} {y:g} {w:g} {h:g}" width="{w:g}" height="{h:g}" role="img" '
            f'aria-labelledby="t"><title id="t">{title}</title>{body}</svg>\n')


def text(s, size, wght=700, tracking=-3.0):
    return base.words(s, FONT, wght, size, tracking if wght >= 700 else 0.0)


def placed(d, fill, x, y):
    return f'<path fill="{fill}" transform="translate({x:.1f} {y:.1f})" d="{d}"/>'


def horizontal(scheme, cut=None):
    h, p, t, default_cut = SCHEMES[scheme]
    wd, (x0, y0, x1, y1) = text("love of my life", 138)
    _, xbox = text("ove", 138)
    tx, ty = 232 + 44 - x0, 134 - (xbox[1] + xbox[3]) / 2
    top, bottom = min(22, ty + y0), max(234 + (8 if cut == "small" else 0) * 0, ty + y1)
    body = mark_body(h, p, cut or default_cut) + placed(wd, t, tx, ty)
    return doc(body, (24, top, tx + x1 - 24, bottom - top))


def wordmark_only(scheme):
    wd, (x0, y0, x1, y1) = text("love of my life", 138)
    return doc(placed(wd, SCHEMES[scheme][2], 0, 0), (x0, y0, x1 - x0, y1 - y0))


def stacked(scheme):
    h, p, t, cut = SCHEMES[scheme]
    lines = [text("love of", 112), text("my life", 112)]
    width = max(max(b[2] - b[0] for _, b in lines), 270)
    s = 1.3  # the symbol, a little larger than on one line
    sw = 208 * s
    body = f'<g transform="translate({(width - sw) / 2 - 24 * s:.1f} {-22 * s:.1f}) scale({s})">{mark_body(h, p, cut)}</g>'
    baseline = 212 * s + 128
    for i, (wd, b) in enumerate(lines):
        body += placed(wd, t, (width - (b[2] - b[0])) / 2 - b[0], baseline + i * 112)
    bottom = baseline + 112 + lines[1][1][3]
    return doc(body, (0, 0, width, bottom))


def symbol(scheme, cut=None):
    h, p, _, default_cut = SCHEMES[scheme]
    return doc(mark_body(h, p, cut or default_cut), (0, 0, 256, 256))


def tile(size, mark_fraction, radius=0.0, scheme="color-dark", glow=True, cut=None):
    """A square icon: the mark centred on the night background. mark_fraction = mark width / tile width."""
    h, p, _, default_cut = SCHEMES[scheme]
    s = size * mark_fraction / 208
    tx, ty = size / 2 - 128 * s, size / 2 - 130 * s  # 130: optical centre sits a touch above the geometric one
    defs = (f'<defs><linearGradient id="n" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{NIGHT_TOP}"/>'
            f'<stop offset="1" stop-color="{NIGHT}"/></linearGradient></defs>') if glow else ""
    fill = "url(#n)" if glow else NIGHT
    body = (f'{defs}<rect width="{size}" height="{size}" rx="{size * radius:g}" fill="{fill}"/>'
            f'<g transform="translate({tx:.2f} {ty:.2f}) scale({s:.4f})">{mark_body(h, p, cut or default_cut)}</g>')
    return doc(body, (0, 0, size, size))


def og():
    wd, b = text("love of my life", 84, 700, -2.0)
    body = (f'<defs><linearGradient id="n" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{NIGHT_TOP}"/>'
            f'<stop offset="1" stop-color="{NIGHT}"/></linearGradient></defs><rect width="1200" height="630" fill="url(#n)"/>'
            f'<g transform="translate({110 - 24 * 1.4:.1f} {315 - 128 * 1.4:.1f}) scale(1.4)">{mark_body(ROSE, BLUSH, "reversed")}</g>')
    x = 480
    body += placed(wd, BLUSH, x - b[0], 262)
    body += f'<rect x="{x}" y="316" width="96" height="4" fill="{GOLD}"/>'
    for i, line in enumerate(["A love letter they unlock with", "a word only they know."]):
        d, lb = text(line, 40, 500)
        body += placed(d, "#d5bfc9", x - lb[0], 386 + i * 54)
    d, lb = text("PHOTOS   ·   A SONG   ·   A LETTER", 27, 600)
    body += placed(d, GOLD, x - lb[0], 520)
    right = x + b[2] - b[0]
    assert right <= 1200 - 60, f"wordmark runs to {right:.0f}px on the share image"
    return doc(body, (0, 0, 1200, 630))


def write(rel, data):
    path = KIT / rel
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(data)
    return path


def size_of(path):
    head = path.read_text().split(">", 1)[0]
    return float(head.split('width="')[1].split('"')[0]), float(head.split('height="')[1].split('"')[0])


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--skill", required=True, type=Path)
    skill = ap.parse_args().skill
    render_py = skill / "scripts" / "render_png.py"

    def png(src, rel, w, h=None, extra=()):
        out = KIT / rel
        out.parent.mkdir(parents=True, exist_ok=True)
        if h is None:
            vw, vh = size_of(src)
            h = round(w * vh / vw)
        subprocess.run([sys.executable, str(render_py), str(src), "-o", str(out), "--width", str(w), "--height", str(h), *extra],
                       check=True, stdout=subprocess.DEVNULL)
        return out

    shutil.rmtree(KIT, ignore_errors=True)
    (KIT / "presentation").mkdir(parents=True)

    # 1. vector masters
    for scheme in SCHEMES:
        write(f"svg/symbol/lom-symbol-{scheme}.svg", symbol(scheme))
        write(f"svg/symbol-small/lom-symbol-small-{scheme}.svg", symbol(scheme, "small"))
        write(f"svg/horizontal/lom-horizontal-{scheme}.svg", horizontal(scheme))
        write(f"svg/stacked/lom-stacked-{scheme}.svg", stacked(scheme))
        write(f"svg/wordmark/lom-wordmark-{scheme}.svg", wordmark_only(scheme))

    # 2. ready-made PNGs
    for scheme in SCHEMES:
        png(KIT / f"svg/horizontal/lom-horizontal-{scheme}.svg", f"png/lom-horizontal-{scheme}.png", 2400)
        png(KIT / f"svg/stacked/lom-stacked-{scheme}.svg", f"png/lom-stacked-{scheme}.png", 1200)
        png(KIT / f"svg/symbol/lom-symbol-{scheme}.svg", f"png/lom-symbol-{scheme}.png", 1024, 1024)
    avatar = write("social/lom-avatar.svg", tile(1080, 0.54))  # inside the circle crop of Facebook / TikTok
    png(avatar, "social/lom-avatar-1080.png", 1080, 1080)
    store = write("app/lom-app-icon.svg", tile(1024, 0.60))
    png(store, "app/lom-app-icon-1024.png", 1024, 1024)

    # 3. web: same file names as public/
    write("web/logo.svg", horizontal("color"))
    write("web/logo-dark.svg", horizontal("color-dark"))
    write("web/logo-mark.svg", symbol("color"))
    tight = (22, 22, 212, 212)
    fav = write("web/favicon.svg", doc(mark_body(CRIMSON, CRIMSON, "small"), tight))  # crimson reads on light and dark tabs
    write("web/safari-pinned-tab.svg", doc(mark_body("#000000", "#000000", "small"), tight))
    write("web/brand-mark.svg", doc(mark_body("currentColor", "currentColor", "small"), tight))  # for partials/brand
    png(KIT / "web/logo.svg", "web/logo.png", 1200)
    fav16 = write("svg/symbol-small/lom-favicon-16-source.svg", doc(mark_body(CRIMSON, CRIMSON, "px16"), tight))
    shots = [png(fav16 if px == 16 else fav, f"web/icons/favicon-{px}.png", px, px) for px in (16, 32, 48)]
    from PIL import Image
    frames = [Image.open(p).convert("RGBA") for p in shots]
    frames[-1].save(KIT / "web/favicon.ico", format="ICO", sizes=[f.size for f in frames], append_images=frames[:-1])
    rounded = write("app/icon-rounded.svg", tile(512, 0.62, radius=0.22))
    maskable = write("app/icon-maskable.svg", tile(512, 0.52))
    touch = write("app/icon-touch.svg", tile(512, 0.62))
    for px in (192, 512):
        png(rounded, f"web/icons/icon-{px}.png", px, px)
        png(maskable, f"web/icons/icon-maskable-{px}.png", px, px)
    png(touch, "web/icons/mstile-150.png", 150, 150)
    png(touch, "web/apple-touch-icon.png", 180, 180)
    share = write("social/og-image.svg", og())
    png(share, "web/og-image.png", 1200, 630)

    # 4. checks on what was rendered
    app = KIT / "app/lom-app-icon-1024.png"
    Image.open(app).convert("RGB").save(app)  # the stores reject an alpha channel
    expect = {"apple-touch-icon.png": 180, "icons/favicon-16.png": 16, "icons/favicon-32.png": 32, "icons/favicon-48.png": 48,
              "icons/icon-192.png": 192, "icons/icon-512.png": 512, "icons/icon-maskable-192.png": 192,
              "icons/icon-maskable-512.png": 512, "icons/mstile-150.png": 150}
    for rel, px in expect.items():
        im = Image.open(KIT / "web" / rel).convert("RGBA")
        assert im.size == (px, px), (rel, im.size)
        corner = im.getpixel((0, 0))[3]
        if "maskable" in rel or rel == "apple-touch-icon.png":
            assert corner == 255, (rel, "corner must be opaque", corner)
        if rel in ("icons/icon-512.png", "icons/favicon-32.png"):
            assert corner == 0, (rel, "corner must be transparent", corner)
    assert Image.open(KIT / "web/og-image.png").size == (1200, 630)
    assert Image.open(KIT / "web/logo.png").size[0] == 1200
    assert sorted(w for w, _ in Image.open(KIT / "web/favicon.ico").ico.sizes()) == [16, 32, 48]
    subprocess.run([sys.executable, str(skill / "scripts/presentation_board.py"), str(HERE / "presentation-spec.json"),
                    "-o", str(KIT / "presentation/board.html"), "--png-dir", str(KIT / "presentation/slides")], check=True, stdout=subprocess.DEVNULL)
    print("kit built and checked:", sum(1 for p in KIT.rglob("*") if p.is_file()), "files")


if __name__ == "__main__":
    main()
