"""Puts the invitation video together: cards + app footage + Lettie + her voice + the promo's music.

    uv run --no-project --python 3.12 --with numpy --with scipy --with soundfile python marketing/invitation/video/compose.py

Needs (in marketing/out/invitation/): cards/vid-*.png and lettie-invite*.png (render_invite.py), video/footage/*.webm
(footage.py), video/voice/ (voice.py) and ffmpeg. Writes video/invite-dont-print-9x16.mp4 (1080x1920, 30 fps), an .srt of the
narration and a poster. Lettie bobs on the beat; her mouth is not animated (this is not lip-sync).
The music is marketing/promo/music.py's synthesised soundtrack (nothing licensed).
"""

from __future__ import annotations

import json
import subprocess
import sys
from pathlib import Path

import numpy as np
import soundfile as sf
from scipy.signal import resample_poly

ROOT = Path(__file__).resolve().parents[3]
OUT = ROOT / "marketing" / "out" / "invitation"
CARDS, SHOTS, VIDEO = OUT / "cards", OUT / "shots", OUT / "video"
sys.path.insert(0, str(ROOT / "marketing" / "promo"))
from music import RATE, soundtrack, with_voice  # noqa: E402

FFMPEG = "ffmpeg"
FPS = 30
CAPTION_Y = 1400
PHONE = (292, 242, 496, 1073)  # where the footage shows through the bezel (x, y, w, h)
STILLS = [SHOTS / "invitation-wedding-3-read.png", SHOTS / "invitation-debut-2-open.png", SHOTS / "invitation-baptism-3-read.png"]

# scene -> length in seconds; the voice starts `lead` seconds into its scene
SCENES = [("s1", 3.0, 0.5), ("s2", 5.0, 0.35), ("s3", 3.6, 0.35), ("s4", 4.2, 0.35), ("s5", 4.8, 0.35)]


def run(cmd: list[str]) -> None:
    subprocess.run([FFMPEG, "-hide_banner", "-loglevel", "error", "-y", *cmd], check=True)


def png(name: str) -> str:
    return str(CARDS / f"{name}.png")


def segment(name: str, dur: float, bg: str, *, footage: tuple[str, float] | None = None, mascots: list[dict] | None = None, caption: str | None = None) -> Path:
    """One scene: background, the phone with footage in it, Lettie (bobbing), the caption."""
    inputs = ["-loop", "1", "-framerate", str(FPS), "-t", f"{dur}", "-i", bg]
    chain, last, n = [], "[0:v]", 1
    if footage:
        src, start = footage
        inputs += ["-ss", f"{start}", "-t", f"{dur}", "-i", src, "-loop", "1", "-framerate", str(FPS), "-t", f"{dur}", "-i", png("vid-bezel")]
        px, py, pw, ph = PHONE
        chain += [f"[1:v]fps={FPS},scale={pw}:{ph}:flags=lanczos,setpts=PTS-STARTPTS[vid]", f"{last}[vid]overlay={px}:{py}[a]", "[a][2:v]overlay=0:0[b]"]
        last, n = "[b]", 3
    for k, m in enumerate(mascots or []):
        inputs += ["-loop", "1", "-framerate", str(FPS), "-t", f"{dur}", "-i", str(CARDS / f"{m['look']}.png")]
        en = f":enable='between(t,{m['from']},{m['to']})'" if "from" in m else ""
        chain += [f"[{n}:v]scale={m['w']}:-1:flags=lanczos,format=rgba[m{k}]",
                  f"{last}[m{k}]overlay=x={m['x']}:y='{m['y']}+9*sin(2*PI*t*1.4)'{en}[o{k}]"]
        last, n = f"[o{k}]", n + 1
    if caption:
        inputs += ["-loop", "1", "-framerate", str(FPS), "-t", f"{dur}", "-i", png(caption)]
        chain += [f"[{n}:v]format=rgba,fade=t=in:st=0.25:d=0.3:alpha=1[cap]", f"{last}[cap]overlay=0:{CAPTION_Y}[out]"]
        last = "[out]"
    chain.append(f"{last}format=yuv420p[final]")
    path = VIDEO / f"seg-{name}.mp4"
    run([*inputs, "-filter_complex", ";".join(chain), "-map", "[final]", "-t", f"{dur}", "-r", str(FPS), "-c:v", "libx264", "-preset", "medium", "-crf", "17", "-pix_fmt", "yuv420p", str(path)])
    return path


def stills_clip() -> Path:
    """The three template stills crossfaded, as the phone's content in scene 3."""
    path = VIDEO / "stills.mp4"
    each, fade = 1.4, 0.3
    inputs = []
    for img in STILLS:
        inputs += ["-loop", "1", "-framerate", str(FPS), "-t", f"{each + fade}", "-i", str(img)]
    chain = "[0:v][1:v]xfade=transition=fade:duration=0.3:offset=1.4[x1];[x1][2:v]xfade=transition=fade:duration=0.3:offset=2.8,format=yuv420p[v]"
    run([*inputs, "-filter_complex", chain, "-map", "[v]", "-r", str(FPS), "-c:v", "libx264", "-crf", "16", "-pix_fmt", "yuv420p", str(path)])
    return path


def timestamp(seconds: float) -> str:
    ms = int(round(seconds * 1000))
    return f"{ms // 3600000:02}:{ms // 60000 % 60:02}:{ms // 1000 % 60:02},{ms % 1000:03}"


def main() -> None:
    footage = json.loads((SHOTS.parent / "video" / "footage" / "footage.json").read_text())
    voice = json.loads((VIDEO / "voice" / "voice.json").read_text())
    lines = {line["id"]: line for line in voice["lines"]}
    env, share = footage["envelope"], footage["share"]
    env_clip, share_clip = str(VIDEO / "footage" / "envelope.webm"), str(VIDEO / "footage" / "share.webm")
    stills = str(stills_clip())
    env_start = round(env["lock"] - 0.3, 2)
    share_start = round(share["top"] - 0.1, 2)

    def mascot(look: str, w: int, x: int, y: int, **extra) -> dict:
        return {"look": look, "w": w, "x": x, "y": y, **extra}

    segs = [
        segment("s1", 3.0, png("vid-s1"), mascots=[mascot("lettie-invite", 960, 60, 640)]),
        segment("s2", 5.0, png("vid-bg-dark"), footage=(env_clip, env_start), mascots=[mascot("lettie-invite-wedding", 440, -80, 1080)], caption="vid-cap-2"),
        segment("s3", 3.6, png("vid-bg-dark"), footage=(stills, 0), caption="vid-cap-3", mascots=[
            mascot("lettie-invite-wedding", 440, -80, 1080, **{"from": 0, "to": 1.2}),
            mascot("lettie-invite-birthday", 440, -80, 1080, **{"from": 1.2, "to": 2.4}),
            mascot("lettie-invite-christening", 440, -80, 1080, **{"from": 2.4, "to": 3.6})]),
        segment("s4", 4.2, png("vid-bg-dark"), footage=(share_clip, share_start), mascots=[mascot("lettie-invite", 440, -80, 1080)], caption="vid-cap-4"),
        segment("s5", 4.8, png("vid-s5"), mascots=[mascot("lettie-invite", 860, 110, 560)], caption="vid-cap-5"),
    ]
    listing = VIDEO / "segments.txt"
    listing.write_text("".join(f"file '{p.name}'\n" for p in segs))
    silent = VIDEO / "silent.mp4"
    run(["-f", "concat", "-safe", "0", "-i", str(listing), "-c", "copy", str(silent)])

    # sound: the promo's music and effects, ducked under Lettie
    starts, t = {}, 0.0
    for scene, dur, lead in SCENES:
        starts[scene] = t + lead
        t += dur
    total = t
    cues = [
        {"kind": "pop", "t": 0.15}, {"kind": "whoosh", "t": 2.85},
        {"kind": "paper", "t": 3.0 + (env["seal"] - env_start)},
        {"kind": "whoosh", "t": 7.85}, {"kind": "page", "t": 8.5}, {"kind": "page", "t": 9.7},
        {"kind": "whoosh", "t": 11.45}, {"kind": "tap", "t": 11.6 + 1.9},
        {"kind": "whoosh", "t": 15.65}, {"kind": "sparkle", "t": 15.95}, {"kind": "chime", "t": 18.4},
    ]
    bed = soundtrack(cues, total, (5.5, 15.5))
    spoken = []
    for scene, _, _ in SCENES:
        line = lines[scene]
        data, rate = sf.read(VIDEO / "voice" / line["file"])
        if rate != RATE:
            data = resample_poly(data, RATE // rate, 1) if RATE % rate == 0 else resample_poly(data, RATE, rate)
        spoken.append((starts[scene], data))
    mix, above = with_voice(bed, spoken)
    print(f"narration sits {above:+.1f} dB above the music while speaking")
    if above < 6:
        raise SystemExit("narration is buried under the music")
    raw = VIDEO / "mix.raw.wav"
    sf.write(raw, mix, RATE, subtype="PCM_16")
    mastered = VIDEO / "mix.wav"
    run(["-i", str(raw), "-af", "loudnorm=I=-16:TP=-1.5:LRA=9", "-ar", "48000", str(mastered)])

    final = VIDEO / "invite-dont-print-9x16.mp4"
    run(["-i", str(silent), "-i", str(mastered), "-map", "0:v", "-map", "1:a", "-c:v", "copy", "-c:a", "aac", "-b:a", "192k", "-ar", "48000", "-movflags", "+faststart", "-shortest", str(final)])

    cues_srt = []
    for i, (scene, _, _) in enumerate(SCENES, 1):
        line = lines[scene]
        cues_srt.append(f"{i}\n{timestamp(starts[scene])} --> {timestamp(starts[scene] + line['duration'] + 0.2)}\n{line['text']}\n")
    (VIDEO / "invite-dont-print.en_US.srt").write_text("\n".join(cues_srt))
    run(["-ss", "1.2", "-i", str(final), "-frames:v", "1", str(VIDEO / "invite-dont-print-9x16-poster.png")])
    for tmp in (raw, mastered, silent, listing, VIDEO / "stills.mp4", *VIDEO.glob("seg-*.mp4")):
        tmp.unlink(missing_ok=True)
    print(f"wrote {final} ({total:.1f} s)")


if __name__ == "__main__":
    main()
