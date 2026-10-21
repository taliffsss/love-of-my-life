"""UTM links for the marketing material: one place that knows the campaign, the placements and how a link is tagged.

    python3 marketing/social/utm.py [campaign]          # prints every tagged link of the campaign

The site records the tags (App\\Services\\Traffic) and the admin dashboard shows them at /admin/traffic. The rules are in marketing/UTM.md.
"""

from __future__ import annotations

import re
import sys
from urllib.parse import urlencode

SITE = "https://loveofmylife.love/"
CAMPAIGN = "halloween-2026"

# placement id -> (source, medium, content). medium: social = organic post, paid_social = Meta ad, bio = link in a profile, qr, email
PLACEMENTS: dict[str, tuple[str, str, str]] = {
    "post-1": ("facebook", "social", "hw-hook"),
    "post-2": ("facebook", "social", "hw-door"),
    "post-3": ("facebook", "social", "hw-lock"),
    "post-4": ("facebook", "social", "hw-steps"),
    "post-5": ("facebook", "social", "hw-poll"),
    "reel-1": ("facebook", "social", "reel-knock"),
    "reel-2": ("facebook", "social", "reel-lock"),
    "tiktok-carousel": ("tiktok", "social", "car-hw"),
    "tiktok-bio": ("tiktok", "bio", "bio"),
    "instagram-bio": ("instagram", "bio", "bio"),
    "facebook-bio": ("facebook", "bio", "bio"),
    "ad-carousel": ("facebook", "paid_social", "ad-carousel"),
    "ad-feed": ("facebook", "paid_social", "ad-feed"),
    "ad-story": ("facebook", "paid_social", "ad-story"),
}


def slug(value: str) -> str:
    return re.sub(r"[^a-z0-9._~-]+", "-", value.lower()).strip("-")[:60]


def link(placement: str, campaign: str = CAMPAIGN, path: str = "") -> str:
    source, medium, content = PLACEMENTS[placement]
    query = urlencode({"utm_source": source, "utm_medium": medium, "utm_campaign": slug(campaign), "utm_content": slug(content)})
    return f"{SITE}{path}?{query}"


def resolve(text: str, campaign: str = CAMPAIGN) -> str:
    """Replaces {link:<placement>} in a caption with its tagged link."""
    return re.sub(r"\{link:([a-z0-9-]+)\}", lambda m: link(m.group(1), campaign), text)


def table(campaign: str = CAMPAIGN) -> str:
    rows = [f"| {p} | {s} | {m} | {c} | {link(p, campaign)} |" for p, (s, m, c) in PLACEMENTS.items()]
    return "| Placement | utm_source | utm_medium | utm_content | Link |\n|---|---|---|---|---|\n" + "\n".join(rows) + "\n"


if __name__ == "__main__":
    print(table(sys.argv[1] if len(sys.argv) > 1 else CAMPAIGN))
