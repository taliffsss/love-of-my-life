#!/usr/bin/env python3
"""Builds the brand and puts it on the site: the logo, favicons, app icons, share image and the header logo.

    pip install fonttools brotli uharfbuzz skia-pathops pillow
    python3 -B marketing/brand/build_brand.py [--skill <logo-design skill folder>]

The mark ("Heart Flap": a heart with a keyhole tucked into an envelope) is drawn once, in redesign-2026-11/build_kit.py.
This script rebuilds that kit (redesign-2026-11/kit/) and then copies from it:
    public/                               logo(-dark).svg, logo-mark.svg, logo.png, favicon.svg|ico, safari-pinned-tab.svg,
                                          apple-touch-icon.png, icons/, og-image.png  (same names as before)
    marketing/brand/app-icon-1024.png     the store icon (opaque, square, full bleed)
    resources/views/partials/brand.naluz.php   the header and footer logo, inline so it follows the page's colours

Needs Chrome and the logo-design skill (its render_png.py draws the PNGs); the skill is found under ~/.claude/plugins,
or give --skill / LOGO_SKILL. Usage rules: redesign-2026-11/kit/LOGO_GUIDE.pdf. Colours: marketing/brand/LETTIE.md has
Lettie's, the guide has the logo's.
"""

from __future__ import annotations

import argparse
import os
import re
import shutil
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PUBLIC = ROOT / "public"
HERE = Path(__file__).resolve().parent
DESIGN = HERE / "redesign-2026-11"
KIT = DESIGN / "kit"
PARTIAL = ROOT / "resources" / "views" / "partials" / "brand.naluz.php"
BRAND_NAME = "Love of My Life"


def find_skill() -> Path:
    cache = Path.home() / ".claude" / "plugins" / "cache" / "logo-design-skill" / "logo-design"
    found = sorted(cache.glob("*/skills/logo-design"), key=lambda p: [int(n) for n in re.findall(r"\d+", p.parts[-3])])
    if not found:
        sys.exit("logo-design skill not found: pass --skill <folder> or set LOGO_SKILL")
    return found[-1]


def paths(svg: Path) -> list[tuple[str, str]]:
    """(transform attribute, path data) for every path of a kit SVG, in drawing order: heart, pocket[, words]."""
    out = []
    for attrs in re.findall(r"<path ([^>]*)/>", svg.read_text()):
        transform = re.search(r'transform="[^"]*"', attrs)
        data = re.search(r'\bd="([^"]*)"', attrs)
        assert data, svg
        out.append((f" {transform.group(0)}" if transform else "", data.group(1)))
    return out


def inline_svg(source: Path, css_class: str, view_box: str, extra: str = "") -> str:
    drawn = "".join(f'<path{" class=\"brand-heart\"" if i == 0 else ""}{t} d="{d}"/>' for i, (t, d) in enumerate(paths(source)))
    return f'<svg class="{css_class}" viewBox="{view_box}"{extra} fill="currentColor" aria-hidden="true" focusable="false">{drawn}</svg>'


def partial() -> str:
    logo = KIT / "svg" / "horizontal" / "lom-horizontal-color.svg"
    view = re.search(r'viewBox="([^"]+)"', logo.read_text()).group(1)
    symbol = inline_svg(KIT / "svg" / "symbol" / "lom-symbol-color.svg", "brand-logo", "24 22 208 212")
    # "slice": where a phone header narrows the box to the symbol's width (ui.css), only the symbol shows
    full = inline_svg(logo, "brand-logo", view, ' preserveAspectRatio="xMinYMid slice"')
    return (
        "{{-- Written by marketing/brand/build_brand.py: change the drawing there, not here. The words in the logo are outlines,\n"
        "     so a site with another APP_NAME gets the symbol and its own name as text. --}}\n"
        '<a class="brand" href="/" aria-label="{{ $brand }}: home">\n'
        f"@if ($brand === '{BRAND_NAME}')\n"
        f"  {full}\n"
        "@else\n"
        f"  {symbol}\n"
        "  <span>{{ $brand }}</span>\n"
        "@endif\n"
        "</a>\n"
    )


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    ap.add_argument("--skill", type=Path, default=Path(os.environ["LOGO_SKILL"]) if os.environ.get("LOGO_SKILL") else None)
    skill = ap.parse_args().skill or find_skill()
    for script, extra in (("build_kit.py", ["--skill", str(skill)]), ("build_guide.py", [])):
        subprocess.run([sys.executable, "-B", str(DESIGN / script), *extra], check=True, cwd=DESIGN)

    print("Copying the brand into public/")
    for source in sorted((KIT / "web").rglob("*")):
        if source.is_file() and source.name != "brand-mark.svg":
            target = PUBLIC / source.relative_to(KIT / "web")
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(source, target)
            print(f"  {target.relative_to(ROOT)}")
    shutil.copyfile(KIT / "app" / "lom-app-icon-1024.png", HERE / "app-icon-1024.png")
    PARTIAL.write_text(partial())
    print(f"  {PARTIAL.relative_to(ROOT)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
