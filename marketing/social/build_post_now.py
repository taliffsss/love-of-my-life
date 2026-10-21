"""Collect the ready-to-upload Facebook posts into marketing/out/social/post-now/ (one folder per post).

    python3 marketing/social/build_post_now.py

Reads the captions from FACEBOOK_POSTS.md and copies the images and videos that post uses (render_social.py and
reels/render_reels.py make them first). Each folder gets CAPTION-copy-paste.txt; video folders also get captions.srt
and thumbnail.png, and each video's description is also written next to it as reels/<video>.md. The folder is rebuilt from scratch every run. Edit the captions in FACEBOOK_POSTS.md, not in the output.
"""

from __future__ import annotations

import re
import shutil
from pathlib import Path

HERE = Path(__file__).resolve().parent
SOCIAL = HERE.parent / "out" / "social"
REELS = SOCIAL / "reels"
OUT = SOCIAL / "post-now"
# heading of the post in FACEBOOK_POSTS.md -> folder (posting order, see the schedule there)
ORDER = [
    ("Photo post 1", "01-DAY1-photo-launch"), ("Video post 1", "02-DAY2-video-ofw"), ("Photo post 2", "03-DAY3-photo-occasions"),
    ("Video post 2", "04-DAY4-video-scratch-ask"), ("Photo post 3", "05-DAY5-photo-ofw"), ("Photo post 5", "06-DAY6-photo-poll"),
    ("Video post 3", "07-DAY7-video-monthsary"), ("Photo post 4", "08-DAY8-photo-plans"), ("Video post 4", "09-DAY10-video-stars"),
    ("Video post 5", "10-DAY12-video-invite"),
]


def caption(section: str) -> str:
    text = "\n".join(line[2:] if line.startswith("> ") else "" for line in section.splitlines() if line.startswith(">")).strip()
    return re.sub(r"\n{2,}", "\n\n", text)


def main() -> None:
    sections = re.split(r"\n### ", (HERE / "FACEBOOK_POSTS.md").read_text())[1:]
    shutil.rmtree(OUT, ignore_errors=True)
    OUT.mkdir(parents=True)
    for key, folder in ORDER:
        section = next(s for s in sections if s.startswith(key))
        target = OUT / folder
        target.mkdir()
        video = re.search(r"`([a-z-]+)\.mp4`", section.split("\n", 1)[0])
        if video:
            vid = video.group(1)
            shutil.copy(REELS / f"{vid}.mp4", target / f"video-{vid}.mp4")
            shutil.copy(REELS / f"{vid}.en_US.srt", target / "captions.srt")
            shutil.copy(REELS / f"{vid}-poster.png", target / "thumbnail.png")
            (REELS / f"{vid}.md").write_text(caption(section) + "\n")  # the description, next to the video it belongs to
        else:
            line = re.search(r"\*\*Images?(?:, in order)?:\*\*([^\n]*)", section).group(1)
            names = re.findall(r"`([a-z0-9-]+)\.png`", line)
            for i, name in enumerate(names[:1] if "backup" in line else names, 1):
                shutil.copy(SOCIAL / f"{name}.png", target / f"{i}-{name}.png")
        (target / "CAPTION-copy-paste.txt").write_text(caption(section) + "\n")
        print(f"  {folder}: {len(list(target.iterdir()))} files")


if __name__ == "__main__":
    main()
