"""Shared by voice_reels.py and render_reels.py: where things are, and a tiny server for the video page."""

from __future__ import annotations

import functools
import http.server
import sys
import threading
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
PROMO = ROOT / "marketing" / "promo"
OUT = ROOT / "marketing" / "out" / "social" / "reels"
IDS = ["ofw-unlock", "scratch-ask", "monthsary-book", "stars-wish", "invite-gift"]
SERVED = (
    "/marketing/social/reels/",
    "/marketing/promo/promo.css",
    "/marketing/promo/lettie.svg",
    "/marketing/out/promo/shots/",
    "/marketing/out/promo/qr.svg",
    "/marketing/out/social/reels/voice/",
)
sys.path.insert(0, str(PROMO))  # music.py and render.py of the promo (soundtrack, loudness, ffmpeg)


class Handler(http.server.SimpleHTTPRequestHandler):
    """Serves only what the video page needs (never the rest of the repository)."""

    def do_GET(self):
        if not self.path.split("?")[0].startswith(SERVED):
            self.send_error(404)
            return
        super().do_GET()

    def log_message(self, *args):
        pass


def serve() -> tuple[http.server.ThreadingHTTPServer, str]:
    server = http.server.ThreadingHTTPServer(("127.0.0.1", 0), functools.partial(Handler, directory=str(ROOT)))
    threading.Thread(target=server.serve_forever, daemon=True).start()
    return server, f"http://127.0.0.1:{server.server_address[1]}"
