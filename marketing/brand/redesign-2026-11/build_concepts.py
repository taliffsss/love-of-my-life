#!/usr/bin/env python3
"""Builds the three logo concepts (black masters) for the 2026-11 redesign.

    pip install fonttools brotli uharfbuzz skia-pathops
    python3 marketing/brand/redesign-2026-11/build_concepts.py
"""
import io
from pathlib import Path

import pathops
import uharfbuzz as hb
from fontTools.pens.boundsPen import BoundsPen
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.svgLib.path import parse_path
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer

OUT = Path(__file__).resolve().parent
FONTS = OUT.parents[2] / "public" / "assets" / "fonts"
BLACK = "#000000"


def shape(d: str) -> pathops.Path:
    p = pathops.Path()
    parse_path(d, p.getPen())
    return p


def union(*ps):
    out = ps[0]
    for p in ps[1:]:
        out = pathops.op(out, p, pathops.PathOp.UNION)
    return out


def minus(a, b):
    return pathops.op(a, b, pathops.PathOp.DIFFERENCE)


def grow(p: pathops.Path, by: float) -> pathops.Path:
    """The shape offset outwards by `by` units (shape + a stroke of its outline)."""
    ring = pathops.Path(p)
    ring.stroke(by * 2, pathops.LineCap.ROUND_CAP, pathops.LineJoin.ROUND_JOIN, 4)
    ring.convertConicsToQuads()
    return union(p, ring)


def d_of(p: pathops.Path) -> str:
    p = pathops.simplify(p, clockwise=False)
    pen = SVGPathPen(None, ntos=lambda v: f"{v:.1f}".rstrip("0").rstrip("."))
    p.draw(pen)
    return pen.getCommands()


# --- A: Heart Flap — a heart tucked into the envelope pocket; the gap between them is the flap line ------------------
def concept_a():
    heart = shape("M128 166C84 140 24 112 24 74C24 45 47 22 76 22C99 22 119 35 128 55C137 35 157 22 180 22C209 22 232 45 232 74C232 112 172 140 128 166Z")
    pocket = shape("M24 108C45 136 88 158 128 184C168 158 211 136 232 108V206Q232 234 204 234H52Q24 234 24 206Z")
    return union(heart, pocket)


# --- B: Lettie — the mascot reduced to flap, heart seal, eyes, smile and curl ----------------------------------------
def concept_b():
    body = shape("M60 58H196Q236 58 236 98V192Q236 232 196 232H60Q20 232 20 192V98Q20 58 60 58Z")
    # the flap line: a band following Lettie's V
    band = shape("M8 84L8 66L128 160L248 66L248 84L136 172Q128 178 120 172Z")
    seal = shape("M128 177C112 167 99 155 99 141C99 131 107 123 116 123C121 123 126 126 128 131C130 126 135 123 140 123C149 123 157 131 157 141C157 155 144 167 128 177Z")
    mark = minus(minus(body, band), grow(seal, 10))
    mark = union(mark, seal)
    eyes = union(shape("M70 175C79 175 85 183 85 192S79 209 70 209S55 201 55 192S61 175 70 175Z"),
                 shape("M186 175C195 175 201 183 201 192S195 209 186 209S171 201 171 192S177 175 186 175Z"))
    smile = shape("M112 200Q128 215 144 200"); smile.stroke(9, pathops.LineCap.ROUND_CAP, pathops.LineJoin.ROUND_JOIN, 4); smile.convertConicsToQuads()
    mark = minus(mark, union(eyes, smile))
    curl = shape("M124 62C118 48 138 44 130 29"); curl.stroke(10, pathops.LineCap.ROUND_CAP, pathops.LineJoin.ROUND_JOIN, 4); curl.convertConicsToQuads()
    tip = shape("M131 34C122 29 114 22 114 14C114 8 118 5 123 5C127 5 130 7 131 11C132 7 135 5 139 5C144 5 148 8 148 14C148 22 140 29 131 34Z")
    return union(mark, curl, tip)


# --- C: Secret Seal — a wax seal pressed with a keyhole whose bow is a heart ----------------------------------------
def concept_c(ring=True):
    wax = shape("M128 14C150 14 160 26 180 32C204 40 220 54 224 78C228 98 242 108 242 130C242 154 228 164 220 184C210 208 194 220 170 224"
                "C152 228 142 242 122 242C100 242 90 228 72 220C48 210 36 196 32 172C28 152 14 144 14 124C14 100 28 90 36 70C46 46 62 36 86 32C104 28 108 14 128 14Z")
    if ring:
        outer = shape("M128 40C176.6 40 216 79.4 216 128S176.6 216 128 216S40 176.6 40 128S79.4 40 128 40Z")
        inner = shape("M128 50C171.1 50 206 84.9 206 128S171.1 206 128 206S50 171.1 50 128S84.9 50 128 50Z")
        wax = minus(wax, minus(outer, inner))
    heart = shape("M128 143C106 130 78 115 78 94C78 79 90 69 104 69C115 69 124 75 128 85C132 75 141 69 152 69C166 69 178 79 178 94C178 115 150 130 128 143Z")
    stem = shape("M116 128H140L151 176Q153 185 144 185H112Q103 185 105 176Z")
    return minus(wax, union(heart, stem))


# --- words as outlines ----------------------------------------------------------------------------------------------
def words(text, font_file, wght, size, tracking=0.0, extra=None):
    font = TTFont(FONTS / font_file)
    axes = {"wght": wght}
    for axis in font["fvar"].axes:
        if axis.axisTag != "wght":
            axes[axis.axisTag] = (extra or {}).get(axis.axisTag, axis.defaultValue)
    font = instancer.instantiateVariableFont(font, axes)
    font.flavor = None
    buf = io.BytesIO(); font.save(buf)
    face = hb.Face(buf.getvalue()); hbf = hb.Font(face)
    b = hb.Buffer(); b.add_str(text); b.guess_segment_properties()
    hb.shape(hbf, b, {"kern": True, "liga": True})
    glyphs, order = font.getGlyphSet(), font.getGlyphOrder()
    s = size / font["head"].unitsPerEm
    x, parts, box = 0.0, [], [1e9, 1e9, -1e9, -1e9]
    for info, pos in zip(b.glyph_infos, b.glyph_positions):
        g = glyphs[order[info.codepoint]]
        t = (s, 0, 0, -s, x + pos.x_offset * s, -pos.y_offset * s)
        pen = SVGPathPen(glyphs, ntos=lambda v: f"{v:.1f}".rstrip("0").rstrip("."))
        g.draw(TransformPen(pen, t)); parts.append(pen.getCommands())
        bp = BoundsPen(glyphs); g.draw(TransformPen(bp, t))
        if bp.bounds:
            x0, y0, x1, y1 = bp.bounds
            box = [min(box[0], x0), min(box[1], y0), max(box[2], x1), max(box[3], y1)]
        x += pos.x_advance * s + tracking
    return " ".join(p for p in parts if p), box


def svg(title, w, h, body):
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" role="img" aria-labelledby="t">'
            f'<title id="t">{title}</title>{body}</svg>\n')


def build(key, mark, font, wght, size, tracking, text):
    d = d_of(mark)
    (OUT / f"{key}-symbol.svg").write_text(svg("Love of My Life", 256, 256, f'<path fill="{BLACK}" d="{d}"/>'))
    wd, (x0, y0, x1, y1) = words(text, font, wght, size, tracking)
    # words centred optically on the x-height so descenders do not push them up
    _, xbox = words("ove", font, wght, size, tracking)
    tx = 232 + 44 - x0
    ty = 134 - (xbox[1] + xbox[3]) / 2
    width = round(tx + x1 + 24)
    body = f'<path fill="{BLACK}" d="{d}"/><path fill="{BLACK}" transform="translate({tx:.1f} {ty:.1f})" d="{wd}"/>'
    (OUT / f"{key}-lockup.svg").write_text(svg("Love of My Life", width, 256, body))


build("a", concept_a(), "bricolage-grotesque.woff2", 700, 138, -3.0, "love of my life")
build("b", concept_b(), "bricolage-grotesque.woff2", 800, 124, -2.0, "Love of My Life")
build("c", concept_c(), "newsreader-italic.woff2", 600, 140, -1.0, "Love of My Life")
(OUT / "c-symbol-small.svg").write_text(svg("Love of My Life", 256, 256, f'<path fill="{BLACK}" d="{d_of(concept_c(False))}"/>'))
print("built")
