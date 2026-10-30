"""Collect the week of 31 Oct - 6 Nov into marketing/out/social/week-nov/post-now/ (one folder per post, in posting order).

    python3 marketing/social/week-nov/collect.py

Reads WEEK_PLAN.md: every `### Title: `file`` section with `>` lines becomes a folder holding those files (pictures from
out/social/week-nov/, videos with their captions .srt and thumbnail from out/social/reels/) and CAPTION-copy-paste.txt (tagged links filled in).
The folder is rebuilt from scratch every run. Edit the captions in WEEK_PLAN.md, not in the output.
"""

from __future__ import annotations

import re
import shutil
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parent))
import utm  # noqa: E402

CAMPAIGN = "week-nov-2026"
OUTPUT = HERE.parents[1] / "out" / "social"
PICTURES, REELS = OUTPUT / "week-nov", OUTPUT / "reels"
TARGET = PICTURES / "post-now"


def caption(section: str) -> str:
    text = "\n".join(line[2:] if line.startswith("> ") else "" for line in section.splitlines() if line.startswith(">")).strip()
    return re.sub(r"\n{3,}", "\n\n", text)


def main() -> None:
    sections = re.split(r"\n#{2,3} ", (HERE / "WEEK_PLAN.md").read_text())[1:]
    shutil.rmtree(TARGET, ignore_errors=True)
    TARGET.mkdir(parents=True)
    made, missing = 0, []
    for section in sections:
        heading = section.split("\n", 1)[0]
        files = re.findall(r"`([a-z0-9-]+\.(?:png|mp4))`", heading)
        if not files or not any(line.startswith(">") for line in section.splitlines()):
            continue
        made += 1
        title = re.sub(r"[^a-z0-9]+", "-", heading.split("`")[0].lower()).strip("-")
        folder = TARGET / f"{made:02d}-{title}"
        folder.mkdir()
        for name in files:
            source = REELS / name if name.endswith(".mp4") else PICTURES / name
            if not source.exists():
                missing.append(str(source))
                continue
            shutil.copy(source, folder / name)
            if name.endswith(".mp4"):
                for extra, new in ((f"{source.stem}.en_US.srt", "captions.srt"), (f"{source.stem}-poster.png", "thumbnail.png")):
                    if (REELS / extra).exists():
                        shutil.copy(REELS / extra, folder / new)
        text = utm.resolve(caption(section), CAMPAIGN)
        alt = re.search(r"^Alt text: (.+)$", section, re.M)
        if alt:
            text += f"\n\n(Alt text for the picture: {alt[1]})"
        (folder / "CAPTION-copy-paste.txt").write_text(text + "\n")
        print(f"  {folder.name}: {len(list(folder.iterdir()))} files")
    (TARGET / "LINKS.md").write_text(f"# Tagged links, campaign {CAMPAIGN}\n\nEvery caption already carries its own link. See marketing/UTM.md.\n\n" + utm.table(CAMPAIGN))
    if missing:
        print("  NOT BUILT YET:\n    " + "\n    ".join(sorted(set(missing))))


if __name__ == "__main__":
    main()
