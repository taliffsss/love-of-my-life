"""Collect the Halloween campaign into marketing/out/social/halloween/ (one folder per post, ad and carousel).

    python3 marketing/social/build_halloween.py

Reads HALLOWEEN.md: every `### Title: `file.png` ...` section becomes a folder with those files (pictures from
out/social/, videos and their captions from out/social/reels/) and CAPTION-copy-paste.txt (the section's `>` lines).
The folder is rebuilt from scratch every run. Edit the captions in HALLOWEEN.md, not in the output.
"""

from __future__ import annotations

import re
import shutil
from pathlib import Path

HERE = Path(__file__).resolve().parent
SOCIAL = HERE.parent / "out" / "social"
REELS = SOCIAL / "reels"
OUT = SOCIAL / "halloween"


def caption(section: str) -> str:
    text = "\n".join(line[2:] if line.startswith("> ") else "" for line in section.splitlines() if line.startswith(">")).strip()
    return re.sub(r"\n{3,}", "\n\n", text)


def slug(title: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", title.lower()).strip("-")


def main() -> None:
    sections = re.split(r"\n#{2,3} ", (HERE / "HALLOWEEN.md").read_text())[1:]
    shutil.rmtree(OUT, ignore_errors=True)
    OUT.mkdir(parents=True)
    made, missing = 0, []
    for section in sections:
        heading = section.split("\n", 1)[0]
        files = re.findall(r"`([a-z0-9-]+\.(?:png|mp4))`", heading)
        if not files or not any(line.startswith(">") for line in section.splitlines()):
            continue
        made += 1
        target = OUT / f"{made:02d}-{slug(heading.split(':')[0].split('`')[0])}"
        target.mkdir()
        for name in files:
            source = REELS / name if name.endswith(".mp4") else SOCIAL / name
            if not source.exists():
                missing.append(str(source.relative_to(HERE.parent.parent)))
                continue
            shutil.copy(source, target / name)
            if name.endswith(".mp4"):
                for extra, new in ((f"{source.stem}.en_US.srt", "captions.srt"), (f"{source.stem}-poster.png", "thumbnail.png")):
                    if (REELS / extra).exists():
                        shutil.copy(REELS / extra, target / new)
        (target / "CAPTION-copy-paste.txt").write_text(caption(section) + "\n")
        print(f"  {target.name}: {len(list(target.iterdir()))} files")
    if missing:
        print("  NOT BUILT YET (run marketing/make.sh halloween):\n    " + "\n    ".join(sorted(set(missing))))


if __name__ == "__main__":
    main()
