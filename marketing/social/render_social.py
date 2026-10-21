"""Render the Facebook and TikTok kit (social.html) to PNG in marketing/out/social/.

    uv run --no-project --python 3.12 --with playwright==1.63.0 python marketing/social/render_social.py [names...]

Needs the promo's stills (marketing/promo/make_promo.sh films them) and an internet connection for Google Fonts. Before it
renders it checks that the prices written on the plan cards still match config/saas.php; each image is then checked
before it is saved: nothing may stick out of the picture, no block of words may overlap another or a phone or Lettie.
"""

from __future__ import annotations

import argparse
import functools
import http.server
import re
import threading
from pathlib import Path

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "marketing" / "out" / "social"
SERVED = (
    "/marketing/social/",
    "/marketing/promo/lettie.svg",
    "/marketing/out/promo/shots/stills/",
    "/public/logo-mark.svg",
    "/public/logo-dark.svg",
    "/public/logo.svg",
)

# card name -> the text its price must appear as, and the centavos config/saas.php must hold for it
PRICES = {
    "sweet month": ("₱200", r"'sweet'.*?'month' => (\d+)", 20000),
    "sweet year": ("₱2,400", r"'sweet'.*?'year' => (\d+)", 240000),
    "sweetheart month": ("₱350", r"'sweetheart'.*?'month' => (\d+)", 35000),
    "sweetheart year": ("₱4,200", r"'sweetheart'.*?'year' => (\d+)", 420000),
    "forever once": ("₱4,999", r"'forever'.*?'once' => (\d+)", 499900),
    "photo pack": ("₱100", r"'photo_pack' => \['photos' => 12, 'price' => (\d+)", 10000),
}

CHECK = """() => {
  const card = document.getElementById('card').getBoundingClientRect();
  const problems = [];
  const inside = (r) => r.left >= card.left - 1 && r.top >= card.top - 1 && r.right <= card.right + 1 && r.bottom <= card.bottom + 1;
  for (const col of document.querySelectorAll('#card > .col')) {
    if (col.scrollHeight > col.clientHeight + 1) problems.push(`content is ${col.scrollHeight - col.clientHeight}px too tall`);
    for (const n of col.children) {
      const r = n.getBoundingClientRect();
      if (r.height && !inside(r)) problems.push(`${n.textContent.trim().slice(0, 30) || 'block'} sticks out`);
    }
  }
  for (const n of document.querySelectorAll('#card > .abs')) {
    if (n.querySelector('.hearts') || n.classList.contains('hearts')) continue;
    const r = n.getBoundingClientRect();
    if (!inside(r)) problems.push(`${n.textContent.trim().slice(0, 30) || n.innerHTML.slice(0, 40)} sticks out (${Math.round(r.left)},${Math.round(r.top)} ${Math.round(r.width)}x${Math.round(r.height)})`);
  }
  const words = Array.from(document.querySelectorAll('#card .headline, #card .sub, #card .kicker, #card .url, #card .logo, #card .hand, #card .pill, #card .tick, #card .price'));
  const art = Array.from(document.querySelectorAll('#card .phone, #card .lettie, #card .orn'));
  const hit = (p, q) => p.left < q.right - 2 && p.right > q.left + 2 && p.top < q.bottom - 2 && p.bottom > q.top + 2;
  words.forEach((a, i) => {
    words.slice(i + 1).forEach((b) => {
      if (a.contains(b) || b.contains(a)) return;
      if (hit(a.getBoundingClientRect(), b.getBoundingClientRect())) problems.push(`"${a.textContent.trim().slice(0, 20)}" overlaps "${b.textContent.trim().slice(0, 20)}"`);
    });
    art.forEach((b) => {
      if (b.contains(a)) return;
      const p = a.getBoundingClientRect();
      const q = b.getBoundingClientRect();
      // Lettie's frame is wider than she is: shrink it to her body before testing
      const k = b.classList.contains('lettie') ? q.width * 0.12 : 0;
      if (hit(p, { left: q.left + k, right: q.right - k, top: q.top + k, bottom: q.bottom - k })) problems.push(`"${a.textContent.trim().slice(0, 20)}" overlaps a picture`);
    });
  });
  return problems;
}"""


class Handler(http.server.SimpleHTTPRequestHandler):
    def do_GET(self):
        if not self.path.split("?")[0].startswith(SERVED):
            self.send_error(404)
            return
        super().do_GET()

    def log_message(self, *args):
        pass


def check_prices() -> None:
    config = (ROOT / "config" / "saas.php").read_text()
    js = (ROOT / "marketing" / "social" / "social.js").read_text()
    wrong = []
    for label, (text, pattern, centavos) in PRICES.items():
        found = re.search(pattern, config, re.S)
        if not found or int(found.group(1)) != centavos:
            wrong.append(f"{label}: config/saas.php no longer says {centavos} centavos")
        if text not in js:
            wrong.append(f"{label}: {text} is missing from social.js")
    if wrong:
        raise SystemExit("PRICES CHANGED, update the cards, copy and PRICES here:\n  " + "\n  ".join(wrong))


GROUPS = [
    ("Halloween: posts (4:5)", ("hw-",)),
    ("Halloween: Meta ads (carousel 1:1, feed link, story)", ("ad-hw-",)),
    ("Halloween: TikTok / Reels photo carousel", ("car-hw-",)),
    ("Profile pictures and covers", ("fb-profile", "tt-profile", "profile-lettie", "fb-cover")),
    ("Facebook: services", ("svc-", "plan-")),
    ("Facebook: audiences", ("ofw-", "couples-", "teen-")),
    ("Facebook: engagement", ("poll-", "fill-", "tag-", "quote-", "how-", "free-", "qr-", "soon", "live")),
    ("TikTok: video covers", ("tt-cover-",)),
    ("TikTok: photo-mode carousels (post the five slides in order)", ("car-",)),
]


def write_index(names: list[str]) -> None:
    """A contact sheet of everything rendered, so the kit can be browsed in one page."""
    taken: set[str] = set()
    sections = []
    for title, prefixes in GROUPS:
        group = [n for n in names if n not in taken and n.startswith(prefixes)]
        taken.update(group)
        figs = "".join(f'<figure><img loading="lazy" src="{n}.png" alt="{n}"><figcaption>{n}</figcaption></figure>' for n in group)
        if group:
            sections.append(f"<h2>{title}</h2><div class=g>{figs}</div>")
    html = (
        "<!doctype html><meta charset=utf-8><meta name=viewport content='width=device-width,initial-scale=1'>"
        "<title>Love of My Life social kit</title><style>body{font:16px system-ui;margin:24px;background:#160a14;color:#fbeef0}"
        "h1,h2{font-family:Georgia,serif;font-style:italic;font-weight:500}.g{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:18px;align-items:end}"
        "figure{margin:0}img{width:100%;height:auto;border-radius:10px;display:block;background:#2a1220}figcaption{font-size:13px;opacity:.7;margin-top:6px}</style>"
        "<h1>Love of My Life: Facebook and TikTok kit</h1>" + "".join(sections)
    )
    (OUT / "index.html").write_text(html)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("names", nargs="*", help="images to render (default: all)")
    args = parser.parse_args()
    check_prices()
    server = http.server.ThreadingHTTPServer(("127.0.0.1", 0), functools.partial(Handler, directory=str(ROOT)))
    threading.Thread(target=server.serve_forever, daemon=True).start()
    base = f"http://127.0.0.1:{server.server_address[1]}/marketing/social/social.html"
    OUT.mkdir(parents=True, exist_ok=True)
    failed = []
    try:
        with sync_playwright() as pw:
            browser = pw.chromium.launch()
            page = browser.new_page()
            page.goto(base)
            names = args.names or page.evaluate("() => cards.names")
            for name in names:
                page.goto(f"{base}?card={name}")
                page.evaluate("() => cards.ready")
                w, h = page.evaluate("() => cards.size")
                page.set_viewport_size({"width": w, "height": h})
                problems = page.evaluate(CHECK)
                if problems:
                    failed.append(f"{name}: " + "; ".join(problems))
                    continue
                page.locator("#card").screenshot(path=str(OUT / f"{name}.png"))
                print(f"  {name}.png  {w}x{h}")
            browser.close()
    finally:
        server.shutdown()
    if not args.names:
        write_index(names)
    if failed:
        raise SystemExit("PROBLEMS:\n  " + "\n  ".join(failed))


if __name__ == "__main__":
    main()
