"""Photographs the landing page in each of its four looks (default, Halloween, Christmas, Valentine) into marketing/out/theme-preview/.

    uv run --no-project --python 3.12 --with playwright==1.63.0 python marketing/theme_preview.py --base http://127.0.0.1:8095

Needs a copy of the app that is not in production (it answers ?theme=...), which `marketing/make.sh themes` starts. Writes <theme>-desktop.png (the whole page),
<theme>-mobile.png (the top of the page on a phone) and index.html, one page that shows all of them side by side.
"""

from __future__ import annotations

import argparse
from pathlib import Path

from playwright.sync_api import sync_playwright

OUT = Path(__file__).resolve().parent / "out" / "theme-preview"
THEMES = [
    ("default", "Default", "The rest of the year: midnight, wax red and gilt."),
    ("halloween", "Halloween", "17 – 31 October: purple night, pumpkin orange."),
    ("christmas", "Christmas", "11 – 25 December: pine night, berry red and gold."),
    ("valentine", "Valentine's Day", "31 January – 14 February: burgundy night, rose."),
]


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--base", required=True)
    args = parser.parse_args()
    OUT.mkdir(parents=True, exist_ok=True)
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        for key, _name, _about in THEMES:
            for label, size, full in (("desktop", {"width": 1280, "height": 800}, True), ("mobile", {"width": 390, "height": 844}, False)):
                page = browser.new_page(viewport=size, device_scale_factor=1)
                page.goto(f"{args.base}/?theme={key}")
                page.wait_for_load_state("networkidle")
                page.evaluate("() => document.fonts.ready")
                page.wait_for_timeout(2200)  # the headline un-blurs, the theater builds
                page.screenshot(path=str(OUT / f"{key}-{label}.png"), full_page=full)
                page.close()
                print(f"  {key}-{label}.png", flush=True)
        browser.close()
    cards = "".join(
        f'<section><h2>{name}</h2><p>{about}</p><div class="row"><a href="{key}-desktop.png"><img src="{key}-desktop.png" alt="{name}, whole page"></a>'
        f'<a href="{key}-mobile.png"><img class="m" src="{key}-mobile.png" alt="{name}, on a phone"></a></div></section>'
        for key, name, about in THEMES
    )
    (OUT / "index.html").write_text(
        "<!doctype html><meta charset=utf-8><meta name=viewport content='width=device-width,initial-scale=1'><title>Landing page: the four looks</title>"
        "<style>body{font:16px system-ui;margin:0;padding:28px;background:#eee;color:#222}h1{margin:0 0 4px}section{margin:34px 0}h2{margin:0}p{margin:2px 0 12px;color:#555}"
        ".row{display:flex;gap:20px;align-items:flex-start;overflow-x:auto}img{display:block;height:620px;width:auto;border-radius:8px;box-shadow:0 6px 24px #0003;background:#fff}img.m{height:620px}"
        "a{flex:none}</style><h1>The landing page in its four looks</h1><p>Click a picture for the full size. Each look is chosen by the date; this is a preview.</p>" + cards
    )
    print(f"Done: {OUT / 'index.html'}")


if __name__ == "__main__":
    main()
