"""Tagged links for the Invitation campaign (campaign name invitations-2026). Same tagging rules as marketing/UTM.md.

    python3 marketing/invitation/links.py          # prints every placement and its link

Admin -> Traffic shows the visits per utm_campaign / utm_content. Put the link in the post, the ad or the bio; the QR code on
Lettie's card (qr-lettie-card.svg) was made from the 'qr' / 'lettie-card' link.
"""

from __future__ import annotations

import re
from urllib.parse import urlencode

SITE = "https://loveofmylife.love/"
LANDING = "invitations"  # every link lands on the invitations page
CAMPAIGN = "invitations-2026"

# placement -> (source, medium, content). medium: social = organic post, paid_social = Meta ad, bio = profile link, qr = printed/QR
PLACEMENTS: dict[str, tuple[str, str, str]] = {
    "fb-hook": ("facebook", "social", "inv-feed-hook"),
    "fb-cost": ("facebook", "social", "inv-feed-cost"),
    "fb-steps": ("facebook", "social", "inv-feed-steps"),
    "fb-occasions": ("facebook", "social", "inv-feed-occasions"),
    "fb-story": ("facebook", "social", "inv-story-hook"),
    "reel-invite": ("facebook", "social", "reel-invite"),
    "tiktok-invite": ("tiktok", "social", "reel-invite"),
    "tiktok-bio": ("tiktok", "bio", "bio"),
    "instagram-bio": ("instagram", "bio", "bio"),
    "facebook-bio": ("facebook", "bio", "bio"),
    "group-share": ("facebook", "social", "group-share"),
    "supplier-card": ("supplier", "qr", "supplier-card"),
    "ad-wedding": ("facebook", "paid_social", "ad-wedding"),
    "ad-christening": ("facebook", "paid_social", "ad-christening"),
    "ad-debut": ("facebook", "paid_social", "ad-debut"),
    "qr": ("qr", "qr", "lettie-card"),
}


def slug(value: str) -> str:
    return re.sub(r"[^a-z0-9._~-]+", "-", value.lower()).strip("-")[:60]


def link(placement: str, path: str = LANDING) -> str:
    source, medium, content = PLACEMENTS[placement]
    query = urlencode({"utm_source": source, "utm_medium": medium, "utm_campaign": CAMPAIGN, "utm_content": slug(content)})
    return f"{SITE}{path}?{query}"


def resolve(text: str) -> str:
    """Replaces {link:<placement>} in a caption with its tagged link."""
    return re.sub(r"\{link:([a-z0-9-]+)\}", lambda m: link(m.group(1)), text)


if __name__ == "__main__":
    print("| Placement | Link |\n|---|---|")
    for name in PLACEMENTS:
        print(f"| {name} | {link(name)} |")
