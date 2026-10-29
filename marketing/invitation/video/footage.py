"""Screen recordings of the real app for the invitation video (no real data; a throw-away copy of the app).

    uv run --no-project --python 3.12 --with playwright==1.63.0 python marketing/invitation/video/footage.py --base http://127.0.0.1:8096

Records, as 390x844 phone pages at 2x:
  envelope.webm  the sample wedding invitation (/demo?layout=envelope&occasion=invitation): Open, then break the seal
  share.webm     the Share box of an invitation in the dashboard: the link and the QR code
and writes footage.json with the second each event happened (relative to the start of the recording).
Writes to marketing/out/invitation/video/footage/. Start the app as shots.py describes.
"""

from __future__ import annotations

import argparse
import json
import time
from pathlib import Path

from playwright.sync_api import Page, sync_playwright

OUT = Path(__file__).resolve().parents[2] / "out" / "invitation" / "video" / "footage"
SIZE = {"width": 780, "height": 1688}


def finish(page: Page, name: str) -> None:
    video = page.video
    page.context.close()
    video.save_as(str(OUT / f"{name}.webm"))


def end_tour(page: Page) -> None:
    page.wait_for_timeout(700)
    for _ in range(3):
        end = page.locator("[data-tour=end]:visible, [data-tour=next]:visible").first
        if page.locator(".tour-pop").count() == 0 or not end.is_visible():
            return
        end.click()
        page.wait_for_timeout(300)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--base", required=True)
    args = parser.parse_args()
    OUT.mkdir(parents=True, exist_ok=True)
    events: dict[str, dict[str, float]] = {}
    with sync_playwright() as pw:
        browser = pw.chromium.launch()

        # 1. the sample invitation: the lock screen's Open button, then the seal
        ctx = browser.new_context(viewport={"width": 390, "height": 844}, device_scale_factor=2, record_video_dir=str(OUT), record_video_size=SIZE)
        page = ctx.new_page()
        t0 = time.monotonic()
        mark = lambda: round(time.monotonic() - t0, 2)  # noqa: E731
        ev: dict[str, float] = {}
        page.goto(f"{args.base}/demo?layout=envelope&theme=blush&occasion=invitation")
        page.wait_for_load_state("networkidle")
        page.wait_for_timeout(1400)
        ev["lock"] = mark()
        page.get_by_role("button", name="Open", exact=True).first.click()
        ev["open"] = mark()
        page.wait_for_timeout(2600)
        ev["seal_ready"] = mark()
        page.locator("#envSeal").click(force=True)
        ev["seal"] = mark()
        page.wait_for_timeout(5200)
        ev["read"] = mark()
        events["envelope"] = ev
        finish(page, "envelope")

        # 2. the Share box: register on the throw-away app, make an invitation, scroll to Share
        ctx = browser.new_context(viewport={"width": 390, "height": 844}, device_scale_factor=2)
        page = ctx.new_page()
        page.goto(f"{args.base}/register")
        page.fill("#name", "Video Demo")
        page.fill("#email", "video-demo@example.com")
        page.fill("#password", "correct-horse-battery")
        page.fill("#password_confirmation", "correct-horse-battery")
        page.click("form[action='/register'] button[type=submit]")
        page.wait_for_url("**/dashboard**")
        end_tour(page)
        page.locator("input[name=occasion][value=invitation]").evaluate("n => { n.checked = true; n.dispatchEvent(new Event('change', {bubbles: true})); }")
        page.locator("input[name=template][value='invitation-wedding']").evaluate("n => { n.checked = true; n.dispatchEvent(new Event('change', {bubbles: true})); }")
        page.fill(".create-form #recipient", "Our family and friends")
        page.click(".create-form button[type=submit]")
        page.wait_for_url("**/dashboard/pages/*")
        end_tour(page)
        edit_url = page.url
        ctx.storage_state(path=str(OUT / "state.json"))
        ctx.close()

        ctx = browser.new_context(viewport={"width": 390, "height": 844}, device_scale_factor=2, storage_state=str(OUT / "state.json"), record_video_dir=str(OUT), record_video_size=SIZE)
        page = ctx.new_page()
        t0 = time.monotonic()
        ev = {}
        page.goto(edit_url)
        page.wait_for_load_state("networkidle")
        end_tour(page)
        page.locator("#share-title").scroll_into_view_if_needed()
        page.evaluate("() => window.scrollTo(0, 0)")
        page.wait_for_timeout(900)
        ev["top"] = mark()
        page.evaluate("""() => { const y = document.querySelector('#share-title').getBoundingClientRect().top + scrollY - 70; window.scrollTo({top: y, behavior: 'smooth'}); }""")
        page.wait_for_timeout(1800)
        ev["share"] = mark()
        page.wait_for_timeout(3200)
        ev["end"] = mark()
        events["share"] = ev
        finish(page, "share")
        browser.close()
    (OUT / "state.json").unlink(missing_ok=True)
    for stray in OUT.glob("*.webm"):
        if stray.stem not in ("envelope", "share"):
            stray.unlink()
    (OUT / "footage.json").write_text(json.dumps(events, indent=2) + "\n")
    print(json.dumps(events, indent=2))


if __name__ == "__main__":
    main()
