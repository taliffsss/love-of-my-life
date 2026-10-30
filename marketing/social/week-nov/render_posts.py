"""Render the week's five pictures (post.html) to PNG in marketing/out/social/week-nov/.

    uv run --no-project --python 3.12 --with playwright==1.63.0 python marketing/social/week-nov/render_posts.py [birthday invitation wedding anniversary partner]
    uv run --no-project --python 3.12 --with playwright==1.63.0 python marketing/social/week-nov/render_posts.py sheet     # the five outfits side by side

Needs looks/ (looks.py), the stills and qr.svg (footage.py, make.sh footage) and an internet connection for Google Fonts. Every picture is
checked before it is saved (words inside the safe area, nothing overlapping the phone, Lettie, the QR code or other words).
"""

from __future__ import annotations

import functools
import http.server
import sys
import threading
from pathlib import Path

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[3]
OUT = ROOT / "marketing" / "out" / "social" / "week-nov"
IDS = ["birthday", "invitation", "wedding", "anniversary", "partner"]
SERVED = ("/marketing/social/week-nov/", "/marketing/out/promo/shots/stills/", "/marketing/out/promo/qr.svg")


class Handler(http.server.SimpleHTTPRequestHandler):
    def do_GET(self):
        if not self.path.split("?")[0].startswith(SERVED):
            self.send_error(404)
            return
        super().do_GET()

    def log_message(self, *args):
        pass


def main() -> None:
    args = sys.argv[1:]
    server = http.server.ThreadingHTTPServer(("127.0.0.1", 0), functools.partial(Handler, directory=str(ROOT)))
    threading.Thread(target=server.serve_forever, daemon=True).start()
    base = f"http://127.0.0.1:{server.server_address[1]}/marketing/social/week-nov"
    OUT.mkdir(parents=True, exist_ok=True)
    failed = []
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        if args == ["sheet"]:
            page = browser.new_page(viewport={"width": 2000, "height": 440})
            errors: list[str] = []
            page.on("pageerror", lambda e: errors.append(str(e)))
            page.goto(f"{base}/post.html?p=birthday")
            page.evaluate("() => post.ready")
            page.evaluate(
                """async (ids) => {
              document.body.innerHTML = '<div id="row" style="display:flex;gap:10px;padding:20px;background:#2a1220;width:max-content"></div>';
              for (const id of ids) {
                const svg = await fetch(`/marketing/social/week-nov/looks/lettie-${id}.svg`).then((r) => r.text());
                const d = document.createElement('div'); d.style.cssText = 'width:380px;height:380px'; d.innerHTML = svg; d.firstChild.setAttribute('width', 380); d.firstChild.setAttribute('height', 380);
                document.getElementById('row').appendChild(d);
              }
            }""",
                IDS,
            )
            page.screenshot(path=str(OUT / "lettie-outfits-sheet.png"), omit_background=False)
            if errors:
                raise SystemExit(f"page errors: {errors}")
            print("  lettie-outfits-sheet.png")
        else:
            for pid in args or IDS:
                if pid not in IDS:
                    raise SystemExit(f"unknown picture {pid}; choose from {IDS}")
                page = browser.new_page(viewport={"width": 1080, "height": 1350}, device_scale_factor=1)
                errors = []
                page.on("pageerror", lambda e: errors.append(str(e)))
                page.on("response", lambda r: r.status >= 400 and errors.append(f"{r.status} {r.url}"))
                page.goto(f"{base}/post.html?p={pid}")
                page.evaluate("() => post.ready")
                problems = page.evaluate("() => post.audit()") + errors
                if problems:
                    failed.append(f"{pid}: " + "; ".join(sorted(set(problems))))
                    continue
                page.locator("#card").screenshot(path=str(OUT / f"wk-{pid}.png"))
                print(f"  wk-{pid}.png")
        browser.close()
    server.shutdown()
    if failed:
        raise SystemExit("PROBLEMS:\n  " + "\n  ".join(failed))


if __name__ == "__main__":
    main()
