"""Gathers what you upload for the Facebook post into marketing/out/invitation/post-now/ (video, captions file, poster,
the caption text with its tagged links, and the feed images).

    python3 marketing/invitation/collect.py
"""

from __future__ import annotations

import re
import shutil
from pathlib import Path

HERE = Path(__file__).resolve().parent
OUT = HERE.parents[0] / "out" / "invitation"
DEST = OUT / "post-now"


def main() -> None:
    if DEST.exists():
        shutil.rmtree(DEST)
    DEST.mkdir(parents=True)
    video = OUT / "video"
    for name in ("invite-dont-print-9x16.mp4", "invite-dont-print.en_US.srt", "invite-dont-print-9x16-poster.png"):
        shutil.copy2(video / name, DEST / name)
    for name in ("inv-feed-hook", "inv-feed-cost", "inv-feed-steps", "inv-feed-occasions", "inv-story-hook", "inv-story-steps"):
        shutil.copy2(OUT / "cards" / f"{name}.png", DEST / f"{name}.png")
    # the three ready-to-paste captions of FB_POST.md (the fenced blocks), as plain text
    blocks = re.findall(r"```\n(.*?)\n```", (HERE / "FB_POST.md").read_text(), re.S)
    (DEST / "CAPTION-copy-paste.txt").write_text(
        "ENGLISH\n=======\n" + blocks[0] + "\n\n\nTAGLISH\n=======\n" + blocks[1] + "\n\n\nSee FB_POST.md for the short reel caption, the first-comment link and what not to add.\n"
    )
    print(f"collected into {DEST}")
    for f in sorted(DEST.iterdir()):
        print("  ", f.name)


if __name__ == "__main__":
    main()
