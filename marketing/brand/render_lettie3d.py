"""Render the 3D Lettie stickers: one transparent PNG per theme and pose in marketing/out/cards/.

    uv run --no-project --python 3.12 --with playwright==1.63.0 python marketing/brand/render_lettie3d.py [theme ...]

Files are lettie3d-<theme>-<pose>.png (themes: default, halloween, christmas, valentine; poses: hello, idle, love).
The model is public/assets/lettie3d.js (source: resources/js/lettie3d.src.js).
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
THEMES = ("default", "halloween", "christmas", "valentine")
POSES = ("hello", "idle", "love")


class Handler(http.server.SimpleHTTPRequestHandler):
    def do_GET(self):
        if not self.path.split("?")[0].startswith(("/marketing/brand/", "/public/assets/")):
            self.send_error(404)
            return
        super().do_GET()

    def log_message(self, *args):
        pass


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("themes", nargs="*", help="themes (default: all)")
    args = parser.parse_args()
    themes = args.themes or THEMES
    server = http.server.ThreadingHTTPServer(("127.0.0.1", 0), functools.partial(Handler, directory=str(ROOT)))
    threading.Thread(target=server.serve_forever, daemon=True).start()
    base = f"http://127.0.0.1:{server.server_address[1]}/marketing/brand/lettie3d.html"
    OUT.mkdir(parents=True, exist_ok=True)
    try:
        with sync_playwright() as pw:
            browser = pw.chromium.launch(args=["--use-angle=swiftshader", "--enable-unsafe-swiftshader", "--ignore-gpu-blocklist"])
            page = browser.new_page(viewport={"width": 1024, "height": 1024})
            for theme in themes:
                for pose in POSES:
                    page.goto(f"{base}?theme={theme}&pose={pose}")
                    page.wait_for_function("() => window.ready === true")
                    page.wait_for_timeout(1500)  # let the hug heart and the wave settle
                    page.evaluate("() => lettie.render()")
                    page.locator("#l").screenshot(path=str(OUT / f"lettie3d-{theme}-{pose}.png"), omit_background=True)
                    print(f"  lettie3d-{theme}-{pose}.png")
            browser.close()
    finally:
        server.shutdown()


if __name__ == "__main__":
    main()
