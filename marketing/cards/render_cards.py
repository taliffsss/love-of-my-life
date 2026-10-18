"""Render the social cards and Lettie stickers (cards.html) to PNG in marketing/out/cards/.

    uv run --no-project --python 3.12 --with playwright==1.63.0 python marketing/cards/render_cards.py [names...]

Needs the promo's shots (marketing/promo/make_promo.sh films them). Each card is checked before it is saved: nothing
may stick out of the card, and no two blocks of words may overlap. Stickers (lettie-*) have a transparent background.
"""

from __future__ import annotations

import argparse
import functools
import http.server
import threading
from pathlib import Path

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "marketing" / "out" / "cards"
SERVED = ("/marketing/cards/", "/marketing/promo/", "/marketing/out/promo/")

CHECK = """() => {
  const card = document.getElementById('card').getBoundingClientRect();
  const problems = [];
  const blocks = Array.from(document.querySelectorAll('#card > .abs')).filter((n) => !n.querySelector('.hearts'));
  for (const n of blocks) {
    const r = n.getBoundingClientRect();
    if (r.left < card.left - 1 || r.top < card.top - 1 || r.right > card.right + 1 || r.bottom > card.bottom + 1) {
      problems.push(`${n.textContent.trim().slice(0, 30) || n.innerHTML.slice(0, 40)} sticks out (${Math.round(r.left)},${Math.round(r.top)} ${Math.round(r.width)}x${Math.round(r.height)})`);
    }
  }
  const words = Array.from(document.querySelectorAll('#card .headline, #card .sub, #card .kicker, #card .url, #card .logo, #card .hand'));
  words.forEach((a, i) => words.slice(i + 1).forEach((b) => {
    if (a.contains(b) || b.contains(a)) return;
    const p = a.getBoundingClientRect(), q = b.getBoundingClientRect();
    if (p.left < q.right - 2 && p.right > q.left + 2 && p.top < q.bottom - 2 && p.bottom > q.top + 2) {
      problems.push(`"${a.textContent.trim().slice(0, 20)}" overlaps "${b.textContent.trim().slice(0, 20)}"`);
    }
  }));
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


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("names", nargs="*", help="cards to render (default: all)")
    args = parser.parse_args()
    server = http.server.ThreadingHTTPServer(("127.0.0.1", 0), functools.partial(Handler, directory=str(ROOT)))
    threading.Thread(target=server.serve_forever, daemon=True).start()
    base = f"http://127.0.0.1:{server.server_address[1]}/marketing/cards/cards.html"
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
                clear = page.evaluate("() => cards.clear")
                page.locator("#card").screenshot(path=str(OUT / f"{name}.png"), omit_background=clear)
                print(f"  {name}.png  {w}x{h}{'  (transparent)' if clear else ''}")
            browser.close()
    finally:
        server.shutdown()
    if failed:
        raise SystemExit("PROBLEMS:\n  " + "\n  ".join(failed))


if __name__ == "__main__":
    main()
