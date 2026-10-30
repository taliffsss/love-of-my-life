"""Lettie's five occasion characters for the week of 31 Oct - 6 Nov 2026: a different mascot for each occasion, all named Lettie.

    python3 marketing/social/week-nov/looks.py

Writes looks/lettie-<id>.svg (viewBox 0 0 400 400):
  birthday     a cupcake with one lit candle
  invitation   a scroll tied with a gold ribbon and seal, "You're invited"
  wedding      a wedding bell in a veil and flower crown, with a bouquet
  anniversary  a champagne bottle in a bow tie, a gold "Cheers" label
  partner      a red plush heart in a cosy pink hoodie
They share Lettie's face (eyes, cheeks, smile, open mouth: the same shapes and positions as promo/lettie.svg), her pink-and-gold palette
and her way of moving: every character has the parts reels.js animates (#l-body, #l-arm-l, #l-arm-r, #l-eyes, #l-eyes-happy,
#l-mouth-smile, #l-mouth-open, #l-hug), and says where its shoulders are in data-arm-l / data-arm-r on the <svg>.
The default envelope Lettie (promo/lettie.svg) and the 3D model are untouched.
"""

from __future__ import annotations

import re
from pathlib import Path

HERE = Path(__file__).resolve().parent
QR = HERE.parents[1] / "invitation" / "qr-lettie-card.svg"
OUT = HERE / "looks"
GOLD, GOLD_DK, GOLD_LT = "#e6b85c", "#b8862f", "#f9de9a"
HEART = "M0 15 C -19 3, -25 -8, -17 -16 C -11 -22, -2 -20, 0 -12 C 2 -20, 11 -22, 17 -16 C 25 -8, 19 3, 0 15 Z"
OUTLINE = "#eab4c0"


def star(x: float, y: float, r: float, fill: str) -> str:
    k = r * 0.28
    return f'<path d="M{x} {y - r} L{x + k} {y - k} L{x + r} {y} L{x + k} {y + k} L{x} {y + r} L{x - k} {y + k} L{x - r} {y} L{x - k} {y - k} Z" fill="{fill}"/>'


def face(cheek: int = 78) -> str:
    """Lettie's face, exactly as in the master (eyes at y 262, smile and open mouth at y 288); only the cheeks move in for narrow bodies."""
    eye = '<ellipse cx="0" cy="0" rx="14" ry="18" fill="#3a1f2b"/><circle cx="-5" cy="-7" r="5" fill="#fff"/><circle cx="5" cy="6" r="2.4" fill="#fff" opacity=".85"/>'
    return f"""
    <ellipse cx="{200 - cheek}" cy="288" rx="17" ry="9" fill="#f7a1b5" opacity=".6"/>
    <ellipse cx="{200 + cheek}" cy="288" rx="17" ry="9" fill="#f7a1b5" opacity=".6"/>
    <g id="l-eyes"><g transform="translate(154 262)">{eye}</g><g transform="translate(246 262)">{eye}</g></g>
    <g id="l-eyes-happy" opacity="0" fill="none" stroke="#3a1f2b" stroke-width="5" stroke-linecap="round"><path d="M140 266 Q 154 250 168 266"/><path d="M232 266 Q 246 250 260 266"/></g>
    <path id="l-mouth-smile" d="M187 287 Q 200 299 213 287" fill="none" stroke="#3a1f2b" stroke-width="4.5" stroke-linecap="round"/>
    <g id="l-mouth-open" transform="translate(200 288)" opacity="0"><path d="M-12 -1 Q 0 3 12 -1 Q 10 14 0 15 Q -10 14 -12 -1 Z" fill="#8a2c45"/><ellipse cx="0" cy="10" rx="6.5" ry="3.6" fill="#f2849c"/></g>"""


def hug() -> str:
    return f"""
    <g id="l-hug" opacity="0" transform="translate(200 338)">
      <path transform="scale(1.9)" d="{HEART}" fill="url(#l-big-heart)"/>
      <ellipse cx="-16" cy="-19" rx="8" ry="5" fill="#fff" opacity=".45" transform="rotate(-30 -16 -19)"/>
    </g>"""


def arm(side: str, x: int, y: int, fill: str, stroke: str, angle: int, cuff: str = "") -> str:
    return f'<g id="l-arm-{side}" transform="translate({x} {y}) rotate({angle})"><ellipse cx="0" cy="20" rx="14" ry="23" fill="{fill}" stroke="{stroke}" stroke-width="4"/>{cuff}</g>'


def svg(name: str, label: str, shoulders: tuple[int, int, int, int], defs: str, body: str, outside: str = "") -> str:
    lx, ly, rx, ry = shoulders
    return f"""<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" width="400" height="400" role="img" aria-label="Lettie, {label}" data-arm-l="{lx} {ly}" data-arm-r="{rx} {ry}">
  <!-- Lettie as {name}: same face, palette and animation parts as promo/lettie.svg; built by marketing/social/week-nov/looks.py -->
  <defs>
    <radialGradient id="l-big-heart" cx=".38" cy=".3" r=".85"><stop offset="0" stop-color="#ff9fb5"/><stop offset=".6" stop-color="#ee6687"/><stop offset="1" stop-color="#c94468"/></radialGradient>
    {defs}
  </defs>
  <ellipse id="l-shadow" cx="200" cy="346" rx="104" ry="11" fill="#000" opacity=".16"/>
  {outside}
  <g id="l-body">{body}
  </g>
</svg>
"""


PAPER = '<linearGradient id="l-paper" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fffaf3"/><stop offset="1" stop-color="#fde1df"/></linearGradient>'


def birthday() -> str:
    defs = PAPER + '<linearGradient id="c-wrap" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f9a3bb"/><stop offset="1" stop-color="#e0607e"/></linearGradient>'
    sprinkles = "".join(f'<rect x="{x}" y="{y}" width="11" height="5" rx="2.5" fill="{c}" transform="rotate({r} {x} {y})"/>' for x, y, c, r in (
        (108, 226, "#ff7ea1", 30), (140, 200, "#7ec8e3", -20), (176, 150, GOLD, 50), (232, 144, "#ff7ea1", -35), (262, 196, "#7ec8e3", 25), (292, 232, GOLD, -45), (96, 268, GOLD, 15), (306, 276, "#ff7ea1", 60), (122, 160, "#ff7ea1", 70), (282, 160, GOLD, -60)))
    body = f"""
    <ellipse cx="160" cy="336" rx="23" ry="9" fill="#f3c2cd"/><ellipse cx="240" cy="336" rx="23" ry="9" fill="#f3c2cd"/>
    {arm("l", 74, 252, "url(#l-paper)", OUTLINE, 22)}{arm("r", 326, 252, "url(#l-paper)", OUTLINE, -22)}
    <!-- the wrapper -->
    <path d="M100 292 L300 292 L282 336 Q 200 346 118 336 Z" fill="url(#c-wrap)" stroke="#d9647f" stroke-width="4" stroke-linejoin="round"/>
    <path d="M132 296 L128 336 M166 296 L164 340 M200 296 L200 342 M234 296 L236 340 M268 296 L272 336" stroke="#fde1df" stroke-width="4" opacity=".7" stroke-linecap="round"/>
    <!-- the frosting: a big soft swirl -->
    <path d="M66 300 C 52 236 82 202 118 196 C 108 150 148 118 200 116 C 252 118 292 150 282 196 C 318 202 348 236 334 300 C 316 312 292 302 270 312 C 246 304 226 316 200 310 C 174 316 154 304 130 312 C 108 302 84 312 66 300 Z" fill="url(#l-paper)" stroke="{OUTLINE}" stroke-width="4" stroke-linejoin="round"/>
    <path d="M122 196 C 150 214 250 214 278 196" fill="none" stroke="#efc0ca" stroke-width="3.5" stroke-linecap="round"/>
    <path d="M142 156 C 170 172 230 172 258 156" fill="none" stroke="#efc0ca" stroke-width="3.5" stroke-linecap="round"/>
    {sprinkles}
    <!-- one lit candle -->
    <rect x="193" y="66" width="14" height="54" rx="4" fill="#7ec8e3" stroke="#4fa3c4" stroke-width="2.5"/>
    <path d="M194 80 L206 74 M194 96 L206 90 M194 112 L206 106" stroke="#fff" stroke-width="3" opacity=".8"/>
    <path d="M200 20 C 188 36, 188 50, 200 62 C 212 50, 212 36, 200 20 Z" fill="#ffb347"/><path d="M200 36 C 195 44, 195 52, 200 58 C 205 52, 205 44, 200 36 Z" fill="#fff0b8"/>
    {face(76)}{hug()}"""
    outside = f'<g id="l-confetti">{star(40, 140, 12, GOLD)}{star(364, 110, 10, "#7ec8e3")}{star(30, 250, 8, "#ff7ea1")}{star(372, 230, 9, GOLD_LT)}<circle cx="70" cy="70" r="5" fill="#ff7ea1"/><circle cx="334" cy="60" r="5" fill="{GOLD}"/></g>'
    return svg("a birthday cupcake", "a cupcake with a lit candle", (74, 252, 326, 252), defs, body, outside)


def invitation() -> str:
    paths = re.search(r'<path class="qr-data-dark[^>]*d="([^"]+)"', QR.read_text()).group(1)  # noqa: F841 (kept for a future QR variant)
    defs = PAPER + '<linearGradient id="s-parch" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fffaf3"/><stop offset="1" stop-color="#f6e3c4"/></linearGradient>' \
        f'<radialGradient id="s-seal" cx=".38" cy=".32" r=".8"><stop offset="0" stop-color="{GOLD_LT}"/><stop offset=".55" stop-color="{GOLD}"/><stop offset="1" stop-color="{GOLD_DK}"/></radialGradient>'
    body = f"""
    <ellipse cx="160" cy="340" rx="23" ry="8" fill="#f3c2cd"/><ellipse cx="240" cy="340" rx="23" ry="8" fill="#f3c2cd"/>
    {arm("l", 90, 252, "url(#s-parch)", GOLD, 22)}{arm("r", 310, 252, "url(#s-parch)", GOLD, -22)}
    <!-- the unrolled parchment -->
    <rect x="94" y="116" width="212" height="206" fill="url(#s-parch)" stroke="{GOLD}" stroke-width="4"/>
    <rect x="104" y="126" width="192" height="186" fill="none" stroke="{GOLD_LT}" stroke-width="2" stroke-dasharray="2 5"/>
    <!-- the rolls at the top and bottom -->
    <rect x="76" y="96" width="248" height="36" rx="18" fill="url(#s-parch)" stroke="{GOLD}" stroke-width="4"/>
    <ellipse cx="94" cy="114" rx="9" ry="12" fill="#f0d9ac" stroke="{GOLD}" stroke-width="3"/><ellipse cx="306" cy="114" rx="9" ry="12" fill="#f0d9ac" stroke="{GOLD}" stroke-width="3"/>
    <rect x="76" y="304" width="248" height="36" rx="18" fill="url(#s-parch)" stroke="{GOLD}" stroke-width="4"/>
    <ellipse cx="94" cy="322" rx="9" ry="12" fill="#f0d9ac" stroke="{GOLD}" stroke-width="3"/><ellipse cx="306" cy="322" rx="9" ry="12" fill="#f0d9ac" stroke="{GOLD}" stroke-width="3"/>
    <!-- the ribbon and gold heart seal -->
    <rect x="94" y="150" width="212" height="26" fill="#e0607e" stroke="#b8405f" stroke-width="2"/>
    <g transform="translate(200 163)"><circle r="22" fill="url(#s-seal)" stroke="{GOLD_DK}" stroke-width="2.5"/><path transform="scale(.7) translate(0 -2)" d="{HEART}" fill="#fff" opacity=".92"/></g>
    <text x="200" y="224" text-anchor="middle" font-family="'Cormorant Garamond', Georgia, serif" font-style="italic" font-weight="600" font-size="27" fill="#8a2c45">You're invited</text>
    <path d="M196 228 L204 228" stroke="{GOLD}" stroke-width="2"/>
    {face(76)}{hug()}"""
    outside = f'<g id="l-sparkles">{star(40, 130, 13, GOLD)}{star(364, 96, 10, GOLD_LT)}{star(30, 236, 9, "#f7a1b5")}{star(372, 210, 9, GOLD)}<circle cx="60" cy="64" r="4" fill="#f7a1b5"/><circle cx="340" cy="52" r="4" fill="{GOLD_LT}"/></g>'
    return svg("an invitation scroll", "an invitation scroll tied with a gold seal", (90, 252, 310, 252), defs, body, outside)


def wedding() -> str:
    defs = '<linearGradient id="b-bell" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#f6e6ec"/></linearGradient>' \
        f'<linearGradient id="b-gold" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{GOLD_LT}"/><stop offset="1" stop-color="{GOLD}"/></linearGradient>' + PAPER
    flowers = "".join(f'<g transform="translate({x} {y})"><circle r="10" fill="#fff" stroke="{OUTLINE}" stroke-width="2"/><circle r="3.6" fill="{GOLD}"/></g>' for x, y in ((156, 100), (178, 84), (200, 78), (222, 84), (244, 100)))
    body = f"""
    <!-- the veil trails behind the bell -->
    <path d="M200 70 C 130 80, 70 190, 58 346 L 342 346 C 330 190, 270 80, 200 70 Z" fill="#fff" opacity=".42" stroke="{OUTLINE}" stroke-width="3" stroke-linejoin="round"/>
    {arm("l", 100, 252, "url(#b-bell)", OUTLINE, 22)}{arm("r", 300, 252, "url(#b-bell)", OUTLINE, -22)}
    <!-- the bell -->
    <path d="M200 74 C 152 74 122 122 118 190 C 114 246 94 288 76 318 Q 72 336 96 336 L 304 336 Q 328 336 324 318 C 306 288 286 246 282 190 C 278 122 248 74 200 74 Z" fill="url(#b-bell)" stroke="{OUTLINE}" stroke-width="4" stroke-linejoin="round"/>
    <path d="M150 120 C 140 150 138 180 138 210" fill="none" stroke="#fff" stroke-width="6" opacity=".8" stroke-linecap="round"/>
    <path d="M80 322 Q 200 342 320 322 L 324 334 Q 200 352 76 334 Z" fill="url(#b-gold)" stroke="{GOLD_DK}" stroke-width="2.5" stroke-linejoin="round"/>
    <circle cx="200" cy="354" r="11" fill="url(#b-gold)" stroke="{GOLD_DK}" stroke-width="2.5"/>
    <!-- the flower crown and bow on top -->
    <path d="M146 112 Q 200 66 254 112" fill="none" stroke="#9cc59a" stroke-width="6" stroke-linecap="round"/>{flowers}
    <!-- a gold ring on a ribbon -->
    <g transform="translate(200 160)"><circle r="13" fill="none" stroke="{GOLD}" stroke-width="6"/><path d="M0 -26 L8 -18 L0 -11 L-8 -18 Z" fill="#fff" stroke="{GOLD}" stroke-width="2.5" stroke-linejoin="round"/></g>
    {face(70)}{hug()}
    <!-- the bouquet -->
    <g id="l-bouquet" transform="translate(330 318) rotate(-12)">
      <path d="M-8 0 L-3 36 M8 0 L3 36 M0 0 L0 38" stroke="#6faa73" stroke-width="5" stroke-linecap="round"/>
      <circle cx="-16" cy="-6" r="13" fill="#fff" stroke="{OUTLINE}" stroke-width="2.5"/><circle cx="-16" cy="-6" r="4.5" fill="#f7a1b5"/>
      <circle cx="16" cy="-6" r="13" fill="#fde1df" stroke="{OUTLINE}" stroke-width="2.5"/><circle cx="16" cy="-6" r="4.5" fill="{GOLD}"/>
      <circle cx="0" cy="-18" r="14" fill="#fff" stroke="{OUTLINE}" stroke-width="2.5"/><circle cx="0" cy="-18" r="5" fill="#f58fa8"/>
      <circle cx="0" cy="2" r="12" fill="#fde1df" stroke="{OUTLINE}" stroke-width="2.5"/><circle cx="0" cy="2" r="4" fill="#f7a1b5"/>
    </g>"""
    outside = f'<g id="l-sparkles">{star(34, 120, 12, GOLD_LT)}{star(370, 130, 10, GOLD)}{star(26, 230, 8, "#f7a1b5")}</g>'
    return svg("a wedding bell", "a wedding bell in a veil and flower crown", (100, 252, 300, 252), defs, body, outside)


def anniversary() -> str:
    defs = f'<linearGradient id="a-glass" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#d9a441"/><stop offset=".35" stop-color="{GOLD_LT}"/><stop offset="1" stop-color="#d9a441"/></linearGradient>' + PAPER
    bubbles = "".join(f'<circle cx="{x}" cy="{y}" r="{r}" fill="#fff" opacity=".55"/>' for x, y, r in ((128, 196, 5), (278, 214, 4), (136, 316, 4), (270, 300, 5), (292, 176, 3.5)))
    body = f"""
    <ellipse cx="160" cy="338" rx="23" ry="8" fill="#3a2a3a" opacity=".5"/><ellipse cx="240" cy="338" rx="23" ry="8" fill="#3a2a3a" opacity=".5"/>
    {arm("l", 104, 258, "#2b2030", "#0f0a12", 22, f'<ellipse cx="0" cy="37" rx="13" ry="5" fill="#fff"/>')}{arm("r", 296, 258, "#2b2030", "#0f0a12", -22, f'<ellipse cx="0" cy="37" rx="13" ry="5" fill="#fff"/>')}
    <!-- the bottle -->
    <path d="M178 62 L178 132 C 178 164 104 164 104 218 L104 310 Q 104 336 130 336 L270 336 Q 296 336 296 310 L296 218 C 296 164 222 164 222 132 L222 62 Z" fill="url(#a-glass)" stroke="{GOLD_DK}" stroke-width="4" stroke-linejoin="round"/>
    <path d="M122 214 L122 310" stroke="#fff" stroke-width="6" opacity=".4" stroke-linecap="round"/>
    {bubbles}
    <!-- the foil and cork -->
    <path d="M174 48 L226 48 L224 100 L176 100 Z" fill="#c99132" stroke="{GOLD_DK}" stroke-width="3" stroke-linejoin="round"/>
    <path d="M178 62 L222 62 M178 76 L222 76 M178 90 L222 90" stroke="{GOLD_LT}" stroke-width="2" opacity=".8"/>
    <rect x="182" y="30" width="36" height="22" rx="8" fill="#f2dcb8" stroke="{GOLD_DK}" stroke-width="3"/>
    <!-- a bow tie at the neck -->
    <g transform="translate(200 128)"><path d="M0 0 L-30 -16 L-30 16 Z" fill="#241a28" stroke="#0f0a12" stroke-width="2" stroke-linejoin="round"/><path d="M0 0 L30 -16 L30 16 Z" fill="#241a28" stroke="#0f0a12" stroke-width="2" stroke-linejoin="round"/><circle r="7" fill="#e0607e" stroke="#0f0a12" stroke-width="2"/></g>
    <!-- the label -->
    <rect x="140" y="170" width="120" height="58" rx="8" fill="#fffaf3" stroke="{GOLD_DK}" stroke-width="3"/>
    <path transform="translate(200 187) scale(.5)" d="{HEART}" fill="#e0607e"/>
    <text x="200" y="217" text-anchor="middle" font-family="'Cormorant Garamond', Georgia, serif" font-style="italic" font-weight="600" font-size="21" fill="#8a2c45">Cheers!</text>
    {face(64)}{hug()}"""
    outside = f'<g id="l-sparkles">{star(40, 110, 14, GOLD)}{star(362, 90, 11, GOLD_LT)}{star(28, 220, 9, GOLD_LT)}{star(370, 200, 9, GOLD)}{star(66, 60, 7, "#fff")}</g>'
    return svg("a champagne bottle", "a champagne bottle in a bow tie", (104, 258, 296, 258), defs, body, outside)


def partner() -> str:
    defs = '<linearGradient id="h-hood" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fbc9d9"/><stop offset="1" stop-color="#f291b2"/></linearGradient>'
    body = f"""
    <ellipse cx="160" cy="338" rx="23" ry="8" fill="#f3c2cd"/><ellipse cx="240" cy="338" rx="23" ry="8" fill="#f3c2cd"/>
    <!-- the cosy hood behind the heart -->
    <path d="M70 218 C 52 98 118 44 200 44 C 282 44 348 98 330 218 Z" fill="url(#h-hood)" stroke="#e0789b" stroke-width="4" stroke-linejoin="round"/>
    <path d="M96 190 C 94 106 142 70 200 70 C 258 70 306 106 304 190" fill="none" stroke="#fff" stroke-width="5" opacity=".6" stroke-linecap="round"/>
    {arm("l", 62, 214, "url(#h-hood)", "#e0789b", 22, '<ellipse cx="0" cy="38" rx="15" ry="6" fill="#fff" opacity=".85"/>')}{arm("r", 338, 214, "url(#h-hood)", "#e0789b", -22, '<ellipse cx="0" cy="38" rx="15" ry="6" fill="#fff" opacity=".85"/>')}
    <!-- the plush heart -->
    <g transform="translate(200 239) scale(6.4)">
      <path d="{HEART}" fill="url(#l-big-heart)" stroke="#a63a58" stroke-width=".26" stroke-linejoin="round"/>
      <path transform="scale(.86) translate(0 .6)" d="{HEART}" fill="none" stroke="#ffd0dc" stroke-width=".16" stroke-dasharray=".7 .6" opacity=".8"/>
      <ellipse cx="-9.5" cy="-12.5" rx="3.2" ry="1.9" fill="#fff" opacity=".5" transform="rotate(-30 -9.5 -12.5)"/>
    </g>
    <!-- hood strings and a love note -->
    <g stroke="#fff" stroke-width="4" stroke-linecap="round" fill="none"><path d="M120 190 C 116 214, 124 224, 118 244"/><path d="M280 190 C 284 214, 276 224, 282 244"/></g>
    <circle cx="118" cy="248" r="6" fill="#fff" stroke="#e0789b" stroke-width="2"/><circle cx="282" cy="248" r="6" fill="#fff" stroke="#e0789b" stroke-width="2"/>
    {face(62)}{hug()}"""
    outside = f'<g id="l-sparkles"><path transform="translate(40 130) scale(.5)" d="{HEART}" fill="#f58fa8"/><path transform="translate(366 120) scale(.42)" d="{HEART}" fill="#f7a1b5"/><path transform="translate(28 240) scale(.34)" d="{HEART}" fill="#f7a1b5"/>{star(372, 250, 8, GOLD_LT)}</g>'
    return svg("a plush heart in a hoodie", "a red plush heart in a cosy pink hoodie", (62, 214, 338, 214), defs, body, outside)


if __name__ == "__main__":
    OUT.mkdir(exist_ok=True)
    for name, make in (("birthday", birthday), ("invitation", invitation), ("wedding", wedding), ("anniversary", anniversary), ("partner", partner)):
        text = make()
        for part in ("l-body", "l-arm-l", "l-arm-r", "l-eyes", "l-eyes-happy", "l-mouth-smile", "l-mouth-open", "l-hug"):
            if f'id="{part}"' not in text:
                raise SystemExit(f"{name}: missing #{part}")
        (OUT / f"lettie-{name}.svg").write_text(text)
        print(f"  lettie-{name}.svg")
