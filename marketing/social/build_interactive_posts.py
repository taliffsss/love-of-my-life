"""Collect the interactive posts into marketing/out/social/post-interactive/ (one folder per post, in posting order).

    python3 marketing/social/build_interactive_posts.py

Reads the captions from ../interactive/INTERACTIVE_CAMPAIGN.md, fills in the tagged links ({link:ix-*}, see utm.py) and copies
each reel with its subtitles and thumbnail. Each folder gets CAPTION-copy-paste.txt. Rebuilt from scratch every run.
This only prepares files; nothing is posted anywhere.
"""

from __future__ import annotations

import re
import shutil
from pathlib import Path

from utm import resolve

HERE = Path(__file__).resolve().parent
REELS = HERE.parent / "out" / "social" / "reels"
OUT = HERE.parent / "out" / "social" / "post-interactive"
CAMPAIGN = "interactive-2026"
SOCIAL = HERE.parent / "out" / "social"
# caption heading in the campaign file -> (folder, reel id, image names). Each day is a PAIR: the reel and a photo post (single or carousel)
ORDER = [
    ("pick-a-letter", "01-DAY1-reel-pick-a-letter", "pick-a-letter", ["ix-pick"]),
    ("scratch-tag", "02-DAY3-reel-scratch-tag", "scratch-tag", ["ix-scratch-1", "ix-scratch-2"]),
    ("this-or-that", "03-DAY5-reel-this-or-that", "this-or-that", ["ix-tt-1", "ix-tt-2", "ix-tt-3", "ix-tt-4", "ix-tt-5"]),
    ("finish-line", "04-DAY7-reel-finish-line", "finish-line", ["ix-fill-1", "ix-fill-2"]),
]
REPLY_CARDS = ["ix-pick-1", "ix-pick-2", "ix-pick-3"]  # sent as comment replies to the pick-a-letter reel
QUIZ_CARDS = ["ix-quiz-cover", "ix-quiz-envelope", "ix-quiz-scratch", "ix-quiz-storybook", "ix-quiz-stars"]


def captions() -> dict[str, str]:
    text = (HERE.parent / "interactive" / "INTERACTIVE_CAMPAIGN.md").read_text()
    found = {}
    for m in re.finditer(r"\*\*([a-z-]+)\*\*\n((?:>.*\n?)+)", text):
        found[m[1]] = "\n".join(l[2:] if l.startswith("> ") else "" for l in m[2].splitlines()).strip()
    quiz = re.search(r"Facebook post\*\* .*?\n((?:  >.*\n?)+)", text)
    found["quiz"] = "\n".join(l.strip()[2:] if l.strip().startswith("> ") else "" for l in quiz[1].splitlines()).strip()
    return found


def main() -> None:
    caps = captions()
    shutil.rmtree(OUT, ignore_errors=True)
    OUT.mkdir(parents=True)
    for key, folder, vid, images in ORDER:
        d = OUT / folder
        (d / "A-reel").mkdir(parents=True)
        (d / "B-photo-post").mkdir()
        shutil.copy(REELS / f"{vid}.mp4", d / "A-reel" / f"video-{vid}.mp4")
        shutil.copy(REELS / f"{vid}.en_US.srt", d / "A-reel" / "captions.srt")
        shutil.copy(REELS / f"{vid}-poster.png", d / "A-reel" / "thumbnail.png")
        caption = resolve(caps[key], CAMPAIGN) + "\n"
        (d / "A-reel" / "CAPTION-copy-paste.txt").write_text(caption)
        for i, name in enumerate(images, 1):
            shutil.copy(SOCIAL / f"{name}.png", d / "B-photo-post" / f"{i}-{name}.png")
        (d / "B-photo-post" / "CAPTION-copy-paste.txt").write_text(caption)  # same words, posted a few hours after the reel
        if key == "pick-a-letter":
            (d / "C-reply-cards").mkdir()
            for name in REPLY_CARDS:
                shutil.copy(SOCIAL / f"{name}.png", d / "C-reply-cards" / f"{name}.png")
        print(f"  {folder}: reel + {len(images)} image(s)")
    d = OUT / "05-DAY2-quiz-link-post"
    d.mkdir()
    shutil.copy(HERE.parent / "interactive" / "quiz.html", d / "quiz.html")
    for i, name in enumerate(QUIZ_CARDS, 1):
        shutil.copy(SOCIAL / f"{name}.png", d / f"{i}-{name}.png")
    (d / "CAPTION-copy-paste.txt").write_text(resolve(caps["quiz"], CAMPAIGN) + "\n")
    print(f"  {d.name}: quiz + {len(QUIZ_CARDS)} images")


if __name__ == "__main__":
    main()
