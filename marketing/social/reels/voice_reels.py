"""Lettie's voice for the five Facebook videos: speaks each video's lines (the VIDEOS table in reels.js) with Kokoro-82M.

    uv run --no-project --python 3.12 --with playwright==1.63.0 --with kokoro-onnx --with soundfile \\
        python marketing/social/reels/voice_reels.py [video ids...]

Same voice, model, checks and speed rules as marketing/promo/voice.py (which downloads and verifies the model files):
a line that does not fit its window is spoken up to 1.2x faster, and one that still does not fit stops the run.
Writes marketing/out/social/reels/voice/<id>/<n>.wav and voice.json.
"""

from __future__ import annotations

import json
import sys

import soundfile as sf
from common import IDS, OUT, serve
from kokoro_onnx import Kokoro
from playwright.sync_api import sync_playwright
from voice import MAX_SPEED, MODELS, VOICE, fetch_models, trim  # the promo's voice.py


def main() -> None:
    ids = sys.argv[1:] or IDS
    fetch_models()
    kokoro = Kokoro(str(MODELS / "kokoro-v1.0.onnx"), str(MODELS / "voices-v1.0.bin"))
    server, base = serve()
    try:
        with sync_playwright() as pw:
            browser = pw.chromium.launch()
            page = browser.new_page()
            for vid in ids:
                page.goto(f"{base}/marketing/social/reels/reels.html?v={vid}")
                page.evaluate("() => reel.ready")
                lines = page.evaluate("() => reel.lines")
                out = OUT / "voice" / vid
                out.mkdir(parents=True, exist_ok=True)
                for old in out.glob("*.wav"):
                    old.unlink()
                done = []
                for n, (text, start, until) in enumerate(lines, 1):
                    budget, speed = until - start, 1.0
                    while True:
                        samples, rate = kokoro.create(text, voice=VOICE, speed=speed, lang="en-us")
                        samples = trim(samples, rate)
                        duration = len(samples) / rate
                        if duration <= budget or speed >= MAX_SPEED:
                            break
                        speed = min(MAX_SPEED, round(speed * duration / budget + 0.02, 3))
                    if duration > budget:
                        raise SystemExit(f"{vid} line {n}: {duration:.2f}s does not fit {budget:.2f}s at {speed}x; shorten it in reels.js")
                    sf.write(out / f"{n:02d}.wav", samples, rate, subtype="PCM_16")
                    done.append({"id": f"{n:02d}", "text": text, "at": start, "duration": round(duration, 3), "speed": speed, "rate": rate, "file": f"{n:02d}.wav"})
                    print(f"  {vid} {n}: {duration:.2f}s of {budget:.2f}s at {speed}x  {text}")
                (out / "voice.json").write_text(json.dumps({"voice": VOICE, "lines": done}, indent=2) + "\n")
            browser.close()
    finally:
        server.shutdown()


if __name__ == "__main__":
    main()
