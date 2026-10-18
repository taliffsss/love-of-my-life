"""Film the real app for the promo: short clips of the templates being played, and a still of every template.

    uv run --no-project --python 3.12 --with playwright==1.63.0 python marketing/promo/shots.py --base http://127.0.0.1:8095

Uses the public /demo page of a locally running copy of the app (make_promo.sh starts one): no account, no database
rows. Its sample photos are swapped for the illustrations in photos/ and the demo-only notes ("Demo: type anything")
are hidden, so the page looks like a real one. Everything else is the product as it ships.

Clips are filmed in slow motion so every frame is sharp: the page's animations run at 1/SLOW speed (CDP
Animation.setPlaybackRate) and its timers SLOW times longer, and a screenshot is taken every SLOW/FPS seconds. The
result plays back at FPS in real time. Writes marketing/out/promo/shots/<clip>/0000.jpg ... plus stills/<key>.jpg and
shots.json.
"""

from __future__ import annotations

import argparse
import json
import time
from pathlib import Path

from playwright.sync_api import Page, sync_playwright

HERE = Path(__file__).resolve().parent
OUT = HERE.parent / "out" / "promo" / "shots"
FPS = 30
SLOW = 8
PHONE = {"width": 390, "height": 844}
DPR = 2
HIDE_DEMO_NOTES = "() => document.querySelectorAll('.preview-note, .made-with').forEach((n) => (n.style.display = 'none'))"
# the timers of the page run SLOW times longer while window.__slow is SLOW (set at the start of each clip)
SLOW_TIMERS = """
(() => {
  window.__slow = 1;
  const st = window.setTimeout, si = window.setInterval;
  window.setTimeout = (fn, ms, ...a) => st(fn, (Number(ms) || 0) * window.__slow, ...a);
  window.setInterval = (fn, ms, ...a) => si(fn, (Number(ms) || 0) * window.__slow, ...a);
})();
"""


def open_demo(page: Page, base: str, layout: str, theme: str, unlock: bool = True) -> None:
    page.goto(f"{base}/demo?layout={layout}&theme={theme}")
    page.wait_for_load_state("networkidle")
    page.evaluate(HIDE_DEMO_NOTES)  # an inline style attribute: the page's CSP allows those, not <style> tags
    page.evaluate("() => document.fonts.ready")
    if unlock:
        page.fill("#pw", "yam")
        page.press("#pw", "Enter")
        page.wait_for_function("() => !document.getElementById('gate')", timeout=20000)
        page.wait_for_timeout(900)
    page.mouse.move(-10, -10)


def centre(page: Page, selector: str) -> tuple[float, float]:
    box = page.locator(selector).first.bounding_box()
    if not box:
        raise SystemExit(f"{selector}: not on screen")
    return box["x"] + box["width"] / 2, box["y"] + box["height"] / 2


def film(page: Page, cdp, name: str, frames: int, actions: dict[int, callable], slow: int = SLOW) -> int:
    """Screenshots `frames` frames in slow motion, running actions[i] just before frame i. `slow` must leave time for a
    screenshot between two frames (the blurred lock screen takes the longest to paint); a late frame is reported."""
    folder = OUT / name
    folder.mkdir(parents=True, exist_ok=True)
    for old in folder.glob("*.jpg"):
        old.unlink()
    page.evaluate(f"() => {{ window.__slow = {slow}; }}")
    cdp.send("Animation.setPlaybackRate", {"playbackRate": 1 / slow})
    step = slow / FPS
    start = time.monotonic()
    late = 0
    for i in range(frames):
        wait = start + i * step - time.monotonic()
        if wait > 0:
            time.sleep(wait)
        else:
            late += 1
        if i in actions:
            actions[i]()
        page.screenshot(path=str(folder / f"{i:04d}.jpg"), type="jpeg", quality=92)
    cdp.send("Animation.setPlaybackRate", {"playbackRate": 1})
    page.evaluate("() => { window.__slow = 1; }")
    took = time.monotonic() - start
    print(f"  {name}: {frames} frames ({frames / FPS:.1f}s) at 1/{slow} speed in {took:.0f}s"
          f"{f', {late} late' if late else ''}", flush=True)  # fmt: skip
    return frames


def clip_lock(page: Page, cdp, base: str) -> int:
    open_demo(page, base, "cinematic", "rose", unlock=False)
    page.focus("#pw")
    type_ = lambda ch: (lambda: page.keyboard.type(ch))  # noqa: E731
    return film(page, cdp, "lock", 150, {
        14: type_("y"), 20: type_("a"), 26: type_("m"),
        40: lambda: page.keyboard.press("Enter"),
    }, slow=30)  # fmt: skip


def clip_envelope(page: Page, cdp, base: str) -> int:
    open_demo(page, base, "envelope", "midnight")
    x, y = centre(page, "#envSeal")
    return film(page, cdp, "envelope", 105, {8: lambda: page.mouse.click(x, y)})


def clip_scratch(page: Page, cdp, base: str) -> int:
    open_demo(page, base, "scratch", "sunset")
    box = page.locator("#scrFoil").bounding_box()
    actions: dict[int, callable] = {}
    # a finger zig-zagging down the card, one short stroke per frame, until the foil gives way
    rows, per_row = 11, 6
    frame = 6
    actions[frame - 1] = lambda: (page.mouse.move(box["x"] + 12, box["y"] + 14), page.mouse.down())
    for r in range(rows):
        y = box["y"] + 14 + r * (box["height"] - 28) / (rows - 1)
        for k in range(per_row):
            frac = (k + 1) / per_row
            x = box["x"] + 12 + (box["width"] - 24) * (frac if r % 2 == 0 else 1 - frac)
            actions[frame] = (lambda px, py: lambda: page.mouse.move(px, py, steps=3))(x, y)
            frame += 1
    actions[frame] = lambda: page.mouse.up()
    return film(page, cdp, "scratch", frame + 45, actions)


def clip_storybook(page: Page, cdp, base: str) -> int:
    open_demo(page, base, "storybook", "rose")
    x, y = centre(page, "#bookNext")
    click = lambda: page.mouse.click(x, y)  # noqa: E731
    return film(page, cdp, "storybook", 120, {8: click, 46: click, 84: click})


def clip_stars(page: Page, cdp, base: str) -> int:
    open_demo(page, base, "stars", "midnight")
    stars = page.locator(".sky-star")
    # the first stars are opened beforehand; the clip taps the last one and completes the heart
    for k in range(stars.count() - 1):
        stars.nth(k).click()
        page.wait_for_timeout(450)
        page.keyboard.press("Escape")
        page.wait_for_timeout(350)
    page.evaluate("() => document.getElementById('sky').scrollTo(0, 0)")
    x, y = centre(page, f".sky-star >> nth={stars.count() - 1}")

    def finish():
        nx, ny = centre(page, "#skyNext")
        page.mouse.click(nx, ny)

    return film(page, cdp, "stars", 135, {8: lambda: page.mouse.click(x, y), 52: finish})


def clip_gift(page: Page, cdp, base: str) -> int:
    open_demo(page, base, "gift", "blush")
    x, y = centre(page, "#giftBox")

    def next_photo():
        nx, ny = centre(page, "#giftNext")
        page.mouse.click(nx, ny)

    return film(page, cdp, "gift", 120, {8: lambda: page.mouse.click(x, y), 80: next_photo})


STILLS = [
    ("classic", "rose"), ("cinematic", "sunset"), ("polaroid", "blush"), ("envelope", "midnight"),
    ("scratch", "sunset"), ("storybook", "rose"), ("memory", "midnight"), ("timeline", "blush"),
    ("gift", "rose"), ("stars", "midnight"),
]  # fmt: skip
CLIPS = {"lock": clip_lock, "envelope": clip_envelope, "scratch": clip_scratch, "storybook": clip_storybook,
         "stars": clip_stars, "gift": clip_gift}  # fmt: skip


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--base", required=True, help="the local app, e.g. http://127.0.0.1:8095")
    parser.add_argument("only", nargs="*", help="clips to (re)film; default all clips and stills")
    args = parser.parse_args()
    photos = {f"/assets/demo/{n}.svg": (HERE / "photos" / f"{name}.svg").read_bytes()
              for n, name in enumerate(["1-shore", "2-coffee", "3-lights", "4-picnic"], 1)}  # fmt: skip


    def swap(route):
        """The demo's sample photos, replaced by the promo's illustrations."""
        path = "/" + route.request.url.split("?")[0].split("/", 3)[3]
        route.fulfill(body=photos[path], content_type="image/svg+xml")

    OUT.mkdir(parents=True, exist_ok=True)
    index_path = OUT / "shots.json"
    index = json.loads(index_path.read_text()) if index_path.exists() else {"fps": FPS, "clips": {}, "stills": []}
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        for name, make in CLIPS.items():
            if args.only and name not in args.only:
                continue
            context = browser.new_context(viewport=PHONE, device_scale_factor=DPR, is_mobile=True, has_touch=True)
            context.add_init_script(SLOW_TIMERS)
            context.route("**/assets/demo/*.svg", swap)
            page = context.new_page()
            errors: list[str] = []
            page.on("pageerror", lambda e: errors.append(str(e)))
            cdp = context.new_cdp_session(page)
            cdp.send("Animation.enable")
            index["clips"][name] = {"frames": make(page, cdp, args.base), "size": [PHONE["width"] * DPR, PHONE["height"] * DPR]}
            if errors:
                raise SystemExit(f"{name}: page errors {errors}")
            context.close()
        if not args.only or "stills" in args.only:
            folder = OUT / "stills"
            folder.mkdir(parents=True, exist_ok=True)
            context = browser.new_context(viewport=PHONE, device_scale_factor=DPR, is_mobile=True, has_touch=True)
            context.route("**/assets/demo/*.svg", swap)
            page = context.new_page()
            for key, theme in STILLS:
                open_demo(page, args.base, key, theme)
                page.wait_for_timeout(1800)
                page.screenshot(path=str(folder / f"{key}.jpg"), type="jpeg", quality=90)
            index["stills"] = [{"key": k, "theme": t} for k, t in STILLS]
            context.close()
            print(f"  stills: {len(STILLS)}")
        browser.close()
    index_path.write_text(json.dumps(index, indent=2) + "\n")


if __name__ == "__main__":
    main()
