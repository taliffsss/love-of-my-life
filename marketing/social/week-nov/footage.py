"""Film the five occasion clips for the 31 Oct - 6 Nov week: the real /demo page, dressed as each occasion.

    uv run --no-project --python 3.12 --with playwright==1.63.0 python marketing/social/week-nov/footage.py --base http://127.0.0.1:8095 [anniversary wedding partner birthday invitation]

The app only offers /demo?occasion=invitation, so for the other four the page is dressed the way a real page of that occasion is:
body[data-occasion=...] plus the app's own assets/occasions.css (ornaments and typeface, no other change), and the sample letter
the unlock call returns is replaced by a short generic one. Everything else is the product as it ships. Sample photos are the
promo's illustrations, as in marketing/promo/shots.py, whose filming code this re-uses.
Writes marketing/out/promo/shots/wk-<id>/NNNN.jpg, adds the clips to shots.json and saves stills/wk-<id>.jpg (the finished page).
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
PROMO = HERE.parents[1] / "promo"
sys.path.insert(0, str(PROMO))
import shots  # noqa: E402  (the promo's filming code)
from playwright.sync_api import sync_playwright  # noqa: E402

LETTERS = {
    "anniversary": ["Happy anniversary, my love. Another year of choosing each other.", "Every star here is a day I would live again.", "Thank you for every year. Here is to many more together."],
    "wedding": ["Today I promise to choose you, every single day.", "Through the quiet mornings and the loud, happy chaos, you are my home.", "I do, and I always will."],
    "birthday": ["Happy birthday! Today is all about you.", "Every year with you is my favourite one yet. Make a big wish.", "I hope today feels as warm as you make everyone else feel."],
}
# occasion -> (layout, theme, clip length in frames, what to do once the page is open)
SETS = {
    "anniversary": ("stars", "midnight"),
    "wedding": ("storybook", "blush"),
    "partner": ("scratch", "rose"),
    "birthday": ("gift", "sunset"),
    "invitation": ("envelope", "midnight"),
}

SKIN = """
document.addEventListener('DOMContentLoaded', () => {
  document.body.dataset.occasion = %r;
  const l = document.createElement('link'); l.rel = 'stylesheet'; l.href = '/assets/occasions.css'; document.head.appendChild(l);
});
"""


def open_page(page, base: str, occasion: str) -> None:
    layout, theme = SETS[occasion]
    if occasion == "invitation":  # the app's own invitation sample: addressed to the guests, no question
        page.goto(f"{base}/demo?layout={layout}&theme={theme}&occasion=invitation")
        page.wait_for_load_state("networkidle")
        page.evaluate(shots.HIDE_DEMO_NOTES)
        page.evaluate("() => document.fonts.ready")
        page.wait_for_timeout(1200)
        page.mouse.move(-10, -10)
    else:
        shots.open_demo(page, base, layout, theme)
        page.wait_for_timeout(600)  # occasions.css


def clip(occasion: str):
    def make(page, cdp, base: str) -> int:
        open_page(page, base, occasion)
        name = f"wk-{occasion}"
        c = shots.centre
        if occasion == "anniversary":
            stars = page.locator(".sky-star")
            for k in range(stars.count() - 1):  # all but the last star are opened beforehand, as in the promo's clip
                stars.nth(k).click()
                page.wait_for_timeout(450)
                page.keyboard.press("Escape")
                page.wait_for_timeout(350)
            page.evaluate("() => document.getElementById('sky').scrollTo(0, 0)")
            x, y = c(page, f".sky-star >> nth={stars.count() - 1}")

            def finish():
                nx, ny = c(page, "#skyNext")
                page.mouse.click(nx, ny)

            return shots.film(page, cdp, name, 135, {8: lambda: page.mouse.click(x, y), 52: finish})
        if occasion == "wedding":
            x, y = c(page, "#bookNext")
            click = lambda: page.mouse.click(x, y)  # noqa: E731
            return shots.film(page, cdp, name, 120, {8: click, 46: click, 84: click})
        if occasion == "partner":
            box = page.locator("#scrFoil").bounding_box()
            actions: dict[int, callable] = {}
            rows, per_row, frame = 11, 6, 6
            actions[frame - 1] = lambda: (page.mouse.move(box["x"] + 12, box["y"] + 14), page.mouse.down())
            for r in range(rows):
                y = box["y"] + 14 + r * (box["height"] - 28) / (rows - 1)
                for k in range(per_row):
                    frac = (k + 1) / per_row
                    x = box["x"] + 12 + (box["width"] - 24) * (frac if r % 2 == 0 else 1 - frac)
                    actions[frame] = (lambda px, py: lambda: page.mouse.move(px, py, steps=3))(x, y)
                    frame += 1
            actions[frame] = lambda: page.mouse.up()
            return shots.film(page, cdp, name, frame + 45, actions)
        if occasion == "birthday":
            x, y = c(page, "#giftBox")

            def next_photo():
                nx, ny = c(page, "#giftNext")
                page.mouse.click(nx, ny)

            return shots.film(page, cdp, name, 120, {8: lambda: page.mouse.click(x, y), 80: next_photo})
        x, y = c(page, "#envSeal")  # invitation
        return shots.film(page, cdp, name, 105, {8: lambda: page.mouse.click(x, y)})

    return make


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--base", required=True)
    parser.add_argument("only", nargs="*", help="occasions to film; default all five")
    args = parser.parse_args()
    todo = args.only or list(SETS)
    bad = [o for o in todo if o not in SETS]
    if bad:
        raise SystemExit(f"unknown {bad}; choose from {list(SETS)}")
    photos = {f"/assets/demo/{n}.svg": (PROMO / "photos" / f"{name}.svg").read_bytes() for n, name in enumerate(["1-shore", "2-coffee", "3-lights", "4-picnic"], 1)}

    def swap(route):
        path = "/" + route.request.url.split("?")[0].split("/", 3)[3]
        route.fulfill(body=photos[path], content_type="image/svg+xml")

    def letter(occasion: str):
        """The sample letter sits in the page's own JSON (#page-config); swap it for the occasion's short generic one."""
        def handle(route):
            response = route.fetch()
            html = response.text()
            if occasion in LETTERS:
                html, n = re.subn(r'("letter":)\[[^\]]*\]', lambda m: m.group(1) + json.dumps(LETTERS[occasion]), html, count=1)
                if n != 1:
                    raise SystemExit("the demo page no longer carries its letter in #page-config; update footage.py")
            route.fulfill(response=response, body=html)

        return handle

    shots.OUT.mkdir(parents=True, exist_ok=True)
    (shots.OUT / "stills").mkdir(exist_ok=True)
    index_path = shots.OUT / "shots.json"
    index = json.loads(index_path.read_text()) if index_path.exists() else {"fps": shots.FPS, "clips": {}, "stills": []}
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        for occasion in todo:
            context = browser.new_context(viewport=shots.PHONE, device_scale_factor=shots.DPR, is_mobile=True, has_touch=True)
            context.add_init_script(shots.SLOW_TIMERS)
            if occasion != "invitation":
                context.add_init_script(SKIN % occasion)
            context.route("**/assets/demo/*.svg", swap)
            context.route(re.compile(r".*/demo\?.*"), letter(occasion))
            page = context.new_page()
            errors: list[str] = []
            page.on("pageerror", lambda e: errors.append(str(e)))
            cdp = context.new_cdp_session(page)
            cdp.send("Animation.enable")
            frames = clip(occasion)(page, cdp, args.base)
            page.wait_for_timeout(600)
            page.screenshot(path=str(shots.OUT / "stills" / f"wk-{occasion}.jpg"), type="jpeg", quality=92)
            index["clips"][f"wk-{occasion}"] = {"frames": frames, "size": [shots.PHONE["width"] * shots.DPR, shots.PHONE["height"] * shots.DPR]}
            if errors:
                raise SystemExit(f"{occasion}: page errors {errors}")
            context.close()
        browser.close()
    index_path.write_text(json.dumps(index, indent=2) + "\n")


if __name__ == "__main__":
    main()
