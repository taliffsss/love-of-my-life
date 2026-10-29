"""Lettie's invitation looks: the same Lettie, now holding an invitation card with a QR code (a real one, it opens the site).

    python3 marketing/invitation/build_mascot.py

Reads marketing/promo/lettie.svg (the master) and qr-lettie-card.svg, writes marketing/invitation/lettie-invite*.svg:
  lettie-invite.svg            gold seal, holding the card (the everyday invitation look)
  lettie-invite-wedding.svg    + a sheer veil with a ring and a gem (wedding invitations)
  lettie-invite-birthday.svg   + a striped party hat (debuts and birthday parties)
  lettie-invite-christening.svg + a frilled bonnet with a blue bow (christenings and baby showers)
Her body, face and proportions are the master's, untouched; as with her seasonal looks only the clothes, the seal's tint and
the things around her change (see marketing/brand/LETTIE.md). The viewBox is wider than the master's so the card fits.
"""

from __future__ import annotations

import re
from pathlib import Path

HERE = Path(__file__).resolve().parent
MASTER = HERE.parent / "promo" / "lettie.svg"
QR = HERE / "qr-lettie-card.svg"

GOLD, GOLD_DK, GOLD_LT = "#e6b85c", "#b8862f", "#f9de9a"
VIEWBOX = "-80 0 560 400"  # the master is 0 0 400 400; the card sticks out on the right


def gold_seal(svg: str) -> str:
    """The heart seal and the little heart on her curl turn gold."""
    svg = svg.replace('stop-color="#f58fa8"', f'stop-color="{GOLD_LT}"').replace('stop-color="#e0607e"', f'stop-color="{GOLD}"')
    svg = svg.replace('stop-color="#b8405f"', f'stop-color="{GOLD_DK}"')
    return svg.replace('fill="#f2a7b8"', 'fill="#f0cfa8"')


def card() -> str:
    qr = QR.read_text()
    paths = re.search(r'<path class="qr-data-dark[^>]*d="([^"]+)"', qr).group(1)
    return f"""
    <!-- the invitation card Lettie holds: a real QR code (tagged link to the site) -->
    <g id="l-card" transform="translate(398 196) rotate(9)">
      <rect x="-60" y="-84" width="120" height="168" rx="10" fill="#000" opacity=".14" transform="translate(5 7)"/>
      <rect x="-60" y="-84" width="120" height="168" rx="10" fill="#fffaf3" stroke="{GOLD}" stroke-width="4"/>
      <rect x="-52" y="-76" width="104" height="152" rx="6" fill="none" stroke="{GOLD_LT}" stroke-width="1.5"/>
      <text x="0" y="-52" text-anchor="middle" font-family="'Cormorant Garamond', Georgia, serif" font-style="italic" font-size="19" fill="#8a2c45">You're invited</text>
      <rect x="-40" y="-36" width="80" height="80" rx="4" fill="#fff"/>
      <g transform="translate(-37 -33) scale({74 / 49:.4f})"><path fill="#2a1220" d="{paths}"/></g>
      <text x="0" y="64" text-anchor="middle" font-family="Inter, system-ui, sans-serif" font-weight="600" font-size="11" letter-spacing="1.5" fill="#8a2c45">SCAN ME</text>
    </g>"""


def sparkles() -> str:
    def star(x: float, y: float, r: float, fill: str) -> str:
        k = r * 0.28
        return f'<path d="M{x} {y - r} L{x + k} {y - k} L{x + r} {y} L{x + k} {y + k} L{x} {y + r} L{x - k} {y + k} L{x - r} {y} L{x - k} {y - k} Z" fill="{fill}"/>'
    return f"""
    <!-- a little confetti -->
    <g id="l-sparkles" opacity=".95">{star(-18, 150, 15, GOLD)}{star(14, 78, 10, GOLD_LT)}{star(-30, 250, 9, "#f7a1b5")}{star(432, 92, 11, GOLD)}
      <circle cx="26" cy="190" r="4" fill="#f7a1b5"/><circle cx="-8" cy="104" r="3.5" fill="{GOLD_LT}"/><circle cx="430" cy="300" r="4" fill="#f7a1b5"/></g>"""


def veil() -> str:
    return f"""
    <!-- wedding: a sheer veil, a gold ring and a gem -->
    <g id="l-hat">
      <path d="M116 128 C 108 62, 150 38, 200 38 C 250 38, 292 62, 284 128 Z" fill="#fff" opacity=".62" stroke="#eab4c0" stroke-width="3" stroke-linejoin="round"/>
      <path d="M140 124 C 142 80, 168 56, 200 54" fill="none" stroke="#fff" stroke-width="3" opacity=".8" stroke-linecap="round"/>
      <circle cx="200" cy="30" r="10" fill="none" stroke="{GOLD}" stroke-width="5"/>
      <path d="M200 8 L207 17 L200 24 L193 17 Z" fill="#fff" stroke="{GOLD}" stroke-width="3" stroke-linejoin="round"/>
    </g>"""


def party_hat() -> str:
    apex, base_y, left, right = (214, 26), 126, 150, 250
    def edge(y: float) -> tuple[float, float]:
        t = (base_y - y) / (base_y - apex[1])
        return left + (apex[0] - left) * t, right + (apex[0] - right) * t
    bands = []
    for y1, y2, color in ((96, 80, GOLD), (66, 52, GOLD)):
        (l1, r1), (l2, r2) = edge(y1), edge(y2)
        bands.append(f'<path d="M{l1:.1f} {y1} L{r1:.1f} {y1} L{r2:.1f} {y2} L{l2:.1f} {y2} Z" fill="{color}"/>')
    return f"""
    <!-- birthday and debut: a striped party hat with a pompom -->
    <g id="l-hat">
      <path d="M{left} {base_y} L{apex[0]} {apex[1]} L{right} {base_y} Z" fill="#f48aa6" stroke="#d9647f" stroke-width="3" stroke-linejoin="round"/>
      {''.join(bands)}
      <ellipse cx="200" cy="{base_y}" rx="54" ry="8" fill="{GOLD}" stroke="{GOLD_DK}" stroke-width="2"/>
      <circle cx="{apex[0]}" cy="{apex[1] - 2}" r="11" fill="{GOLD_LT}" stroke="{GOLD_DK}" stroke-width="2"/>
    </g>"""


def bonnet() -> str:
    ruffle = "".join(f'<circle cx="{x}" cy="124" r="9.5" fill="#fffdf8" stroke="#eab4c0" stroke-width="2.5"/>' for x in range(128, 275, 18))
    return f"""
    <!-- christening and baby shower: a frilled bonnet with a pale blue bow -->
    <g id="l-hat">
      <path d="M122 124 C 122 62, 158 42, 200 42 C 242 42, 278 62, 278 124 Z" fill="#fffdf8" stroke="#eab4c0" stroke-width="3" stroke-linejoin="round"/>
      {ruffle}
      <path d="M126 112 Q 200 98 274 112" fill="none" stroke="#a9d3f0" stroke-width="7" stroke-linecap="round"/>
      <g transform="translate(262 108) rotate(-14)">
        <path d="M0 0 C -14 -14 -30 -10 -28 2 C -26 12 -10 10 0 0 Z" fill="#a9d3f0" stroke="#7fb4dc" stroke-width="2"/>
        <path d="M0 0 C 14 -14 30 -10 28 2 C 26 12 10 10 0 0 Z" fill="#a9d3f0" stroke="#7fb4dc" stroke-width="2"/>
        <circle r="6" fill="#c9e4f7" stroke="#7fb4dc" stroke-width="2"/>
      </g>
    </g>"""


def build(name: str, hat: str) -> None:
    svg = gold_seal(MASTER.read_text())
    svg = re.sub(r'viewBox="0 0 400 400" width="400" height="400"', f'viewBox="{VIEWBOX}" width="560" height="400"', svg, count=1)
    svg = svg.replace('aria-label="Lettie, the little love letter"', 'aria-label="Lettie, the little love letter, holding an invitation"')
    # the card goes behind the raised arm so her hand holds it; the arm points out to the right
    svg = svg.replace('<g id="l-arm-r" transform="translate(324 238) rotate(-22)">', card() + '\n    <g id="l-arm-r" transform="translate(324 238) rotate(-70)">')
    svg = svg.replace('<ellipse id="l-shadow"', sparkles() + '\n  <ellipse id="l-shadow"', 1)
    svg = svg.replace("  </g>\n</svg>", hat + "\n  </g>\n</svg>")
    (HERE / f"{name}.svg").write_text(svg)
    print(f"  {name}.svg")


if __name__ == "__main__":
    build("lettie-invite", "")
    build("lettie-invite-wedding", veil())
    build("lettie-invite-birthday", party_hat())
    build("lettie-invite-christening", bonnet())
