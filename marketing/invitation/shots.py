"""Film the invitation templates for the Invitation campaign from a local copy of the app (no real data).

    uv run --no-project --python 3.12 --with playwright==1.63.0 python marketing/invitation/shots.py --base http://127.0.0.1:8096

Makes an account on the throw-away database the app runs on, builds one page per invitation template with made-up names and
the promo's illustrations as photos, and saves phone-sized stills of each page to marketing/out/invitation/shots/. Nothing here
touches a real account. The page is addressed to the guests ("Our family and friends"); the hosts' names in the text are invented. Cards that show the stills say so.
"""

from __future__ import annotations

import argparse
import json
from pathlib import Path

from playwright.sync_api import Page, sync_playwright

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "marketing" / "out" / "invitation" / "shots"
PHOTOS = ROOT / "marketing" / "promo" / "photos"

# template key -> (recipient shown in the dashboard, the message people read, number of photos)
PAGES = {
    "invitation-wedding": ("Our family and friends", "Together with our families, we joyfully invite you to celebrate our wedding.\n\nSaturday, 12 December 2026, 3:00 PM, at San Agustin Church, Manila. Reception to follow at the Garden Hall.\n\nKindly let us know by 1 December. We cannot wait to celebrate with you.", 3),
    "invitation-baptism": ("Family and friends", "With grateful hearts we invite you to the baptism of our little one, Luna.\n\nSunday, 17 January 2027, 10:00 AM, at Santo Niño Parish, Cebu City. Lunch follows at our home.\n\nBeing there with the people we love makes this day complete.", 3),
    "invitation-debut": ("My friends and family", "I am turning 18, and I would love for you to be part of the night I will remember forever.\n\nSaturday, 6 February 2027, 6:00 PM, at The Pavilion, Quezon City. Dinner and dancing follow the 18 roses.\n\nDress code: pastel formal. Your presence is the best gift.", 3),
    "invitation-baby-shower": ("Family and friends", "We are so excited to share that our family is growing! Please join us for a baby shower for Mica.\n\nSaturday, 20 February 2027, 2:00 PM, at the Rosewood Cafe, Pasig. Games, food and plenty of cuddles.\n\nPlease reply by 10 February.", 3),
}


# what a guest taps after "Open", per template (the envelope's seal, the book, the gift)
STEPS = {
    "invitation-wedding": "#envSeal",
    "invitation-baptism": "text=Open the book",
    "invitation-baby-shower": "text=Tap to unwrap",
}


def png_photos(browser, tmp: Path) -> list[Path]:
    """The promo's SVG illustrations as PNG files, because the dashboard takes raster photos."""
    tmp.mkdir(parents=True, exist_ok=True)
    page = browser.new_page(viewport={"width": 1200, "height": 900})
    files = []
    for svg in sorted(PHOTOS.glob("*.svg"))[:4]:
        page.goto(svg.as_uri())
        target = tmp / f"{svg.stem}.png"
        page.screenshot(path=str(target))
        files.append(target)
    page.close()
    return files


def end_tour(page: Page) -> None:
    """The first-visit tour covers the dashboard; end it so the forms can be used."""
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
    report: dict[str, object] = {}
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        photos = png_photos(browser, OUT / "_photos")
        ctx = browser.new_context(viewport={"width": 390, "height": 844}, device_scale_factor=2)
        page = ctx.new_page()
        errors: list[str] = []
        page.on("console", lambda m: errors.append(m.text) if m.type == "error" else None)
        page.goto(f"{args.base}/register")
        page.fill("#name", "Invitation Demo")
        page.fill("#email", "invitation-demo@example.com")
        page.fill("#password", "correct-horse-battery")
        page.fill("#password_confirmation", "correct-horse-battery")
        page.click("form[action='/register'] button[type=submit]")
        page.wait_for_url("**/dashboard**")
        for key, (recipient, message, count) in PAGES.items():
            page.goto(f"{args.base}/dashboard")
            end_tour(page)
            page.fill(".create-form #recipient", recipient)
            page.locator("input[name=occasion][value=invitation]").evaluate("n => { n.checked = true; n.dispatchEvent(new Event('change', {bubbles: true})); }")
            page.locator(f"input[name=template][value='{key}']").evaluate("n => { n.checked = true; n.dispatchEvent(new Event('change', {bubbles: true})); }")
            page.click(".create-form button[type=submit]")
            page.wait_for_url("**/dashboard/pages/*")
            end_tour(page)
            slug = page.url.rstrip("/").split("/")[-1].split("?")[0]
            page.fill("#letter-message", message)
            page.click("form[action$='/letter'] button[type=submit], form:has(#letter-message) button[type=submit]")
            page.wait_for_load_state("networkidle")
            page.set_input_files("#photo-input", [str(p) for p in photos[:count]])
            page.wait_for_timeout(2500)
            page.goto(f"{args.base}/dashboard/pages/{slug}/preview")
            page.wait_for_load_state("networkidle")
            page.wait_for_timeout(1500)
            page.screenshot(path=str(OUT / f"{key}-1-closed.png"))
            page.get_by_role("button", name="Open", exact=True).first.click()
            page.wait_for_timeout(4500)
            page.screenshot(path=str(OUT / f"{key}-2-open.png"))
            step = STEPS.get(key)
            if step:
                target = page.locator(step).first
                if target.count():
                    target.click(force=True)
                    page.wait_for_timeout(5500)
                    page.screenshot(path=str(OUT / f"{key}-3-read.png"))
            opened = page.evaluate("() => document.body.innerText.slice(0, 600)")
            info = page.evaluate("() => ({title: document.title, text: document.body.innerText.slice(0, 400), scrollW: document.documentElement.scrollWidth, w: innerWidth})")
            report[key] = {"slug": slug, "opened_text": opened, **info}
        browser.close()
        report["console_errors"] = errors
    (OUT / "report.json").write_text(json.dumps(report, indent=2))
    print(json.dumps(report, indent=2)[:3000])


if __name__ == "__main__":
    main()
