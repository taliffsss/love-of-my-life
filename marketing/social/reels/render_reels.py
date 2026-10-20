"""Render the five Facebook videos (reels.html) to MP4 in marketing/out/social/reels/.

    uv run --no-project --python 3.12 --with playwright==1.63.0 --with imageio-ffmpeg --with numpy --with scipy \\
        python marketing/social/reels/render_reels.py [video ids...]

Run voice_reels.py first (it speaks Lettie's lines). Like the promo's render.py: it audits the text layout, steps the
timeline frame by frame (reel.seek(t) + a screenshot, piped into ffmpeg), synthesises the soundtrack around the cues
(music.py), mixes the voice, normalises to -14 LUFS, encodes H.264/AAC (1080x1920, 30 fps) and then checks the file.
Also writes <id>.en_US.srt captions and <id>-poster.png (the end card).
"""

from __future__ import annotations

import json
import re
import subprocess
import sys
import time
import wave
from pathlib import Path

import numpy as np
from common import IDS, OUT, serve
from music import RATE, soundtrack, with_voice
from playwright.sync_api import sync_playwright
from render import FFMPEG, MIN_VOICE_OVER_BED_DB, loudnorm, write_wav  # the promo's render.py
from scipy.signal import resample_poly


def voice_lines(vid: str) -> list[dict]:
    index = OUT / "voice" / vid / "voice.json"
    if not index.exists():
        raise SystemExit(f"{vid}: no voice yet; run voice_reels.py")
    return json.loads(index.read_text())["lines"]


def narration(vid: str) -> list[tuple[float, np.ndarray]]:
    lines = []
    for line in voice_lines(vid):
        with wave.open(str(OUT / "voice" / vid / line["file"])) as w:
            pcm = np.frombuffer(w.readframes(w.getnframes()), dtype="<i2") / 32768.0
            rate = w.getframerate()
        lines.append((line["at"], resample_poly(pcm, RATE, rate)))
    return lines


def mouth_shapes(vid: str, fps: int, duration: float) -> list[float]:
    """How open Lettie's mouth is on every frame: the loudness of her voice, smoothed."""
    shapes = np.zeros(int(round(fps * duration)) + 1)
    for line in voice_lines(vid):
        with wave.open(str(OUT / "voice" / vid / line["file"])) as w:
            pcm = np.frombuffer(w.readframes(w.getnframes()), dtype="<i2") / 32768.0
            rate = w.getframerate()
        hop = rate // fps
        for k in range(len(pcm) // hop):
            frame = int(round(line["at"] * fps)) + k
            if frame < len(shapes):
                shapes[frame] = np.sqrt(np.mean(pcm[k * hop : (k + 1) * hop] ** 2))
    peak = np.percentile(shapes[shapes > 0], 95) if np.any(shapes > 0) else 1.0
    shapes = np.clip(shapes / (peak or 1.0), 0, 1) ** 0.7
    return [round(float(v), 3) for v in np.convolve(shapes, [0.25, 0.5, 0.25], mode="same")]


def write_captions(vid: str) -> None:
    def stamp(s: float) -> str:
        ms = int(round(s * 1000))
        return f"{ms // 3600000:02d}:{ms // 60000 % 60:02d}:{ms // 1000 % 60:02d},{ms % 1000:03d}"

    cues = [f"{i}\n{stamp(l['at'])} --> {stamp(l['at'] + l['duration'] + 0.25)}\n{l['text']}\n" for i, l in enumerate(voice_lines(vid), 1)]
    (OUT / f"{vid}.en_US.srt").write_text("\n".join(cues))


def render(vid: str, base: str) -> tuple[Path, float]:
    OUT.mkdir(parents=True, exist_ok=True)
    target = OUT / f"{vid}.mp4"
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        page = browser.new_page(viewport={"width": 1080, "height": 1920}, device_scale_factor=1)
        errors: list[str] = []
        page.on("pageerror", lambda exc: errors.append(str(exc)))
        page.on("console", lambda msg: msg.type == "error" and errors.append(msg.text))
        page.goto(f"{base}/marketing/social/reels/reels.html?v={vid}")
        page.evaluate("() => reel.ready")
        info = page.evaluate("() => ({fps: reel.fps, duration: reel.duration, cues: reel.cues, groove: reel.groove, endAt: reel.endAt, fonts: reel.fonts()})")
        if errors:
            raise SystemExit(f"{vid}: page errors: {errors}")
        missing = [n for n, ok in info["fonts"] if not ok]
        if missing:
            raise SystemExit(f"{vid}: fonts not loaded: {missing} (the page needs Google Fonts)")
        page.evaluate("m => reel.setMouth(m)", mouth_shapes(vid, info["fps"], info["duration"]))

        problems = []
        for t in [round(x * 0.5, 2) for x in range(1, int(info["duration"] * 2) - 1)]:
            problems += [f"{t:5.2f}s {p}" for p in page.evaluate("t => reel.audit(t)", t)]
        if problems:
            raise SystemExit(f"{vid}: layout problems:\n  " + "\n  ".join(sorted(set(problems))[:25]))

        fps, duration = info["fps"], info["duration"]
        frames = int(round(fps * duration))
        raw, mastered = OUT / f"{vid}.raw.wav", OUT / f"{vid}.wav"
        bed = soundtrack(info["cues"], duration, tuple(info["groove"]))
        bed, above = with_voice(bed, narration(vid))
        if above < MIN_VOICE_OVER_BED_DB:
            raise SystemExit(f"{vid}: narration buried ({above:.1f} dB over the music)")
        write_wav(raw, bed)
        loud = loudnorm(raw, mastered)
        raw.unlink()

        cmd = [
            FFMPEG, "-hide_banner", "-loglevel", "error", "-y",
            "-f", "image2pipe", "-framerate", str(fps), "-c:v", "png", "-i", "-", "-i", str(mastered),
            "-map", "0:v", "-map", "1:a",
            "-c:v", "libx264", "-preset", "slow", "-crf", "17", "-pix_fmt", "yuv420p", "-profile:v", "high", "-level", "4.1",
            "-g", str(fps * 2), "-r", str(fps), "-c:a", "aac", "-b:a", "192k", "-ar", "48000", "-movflags", "+faststart", "-shortest", str(target),
        ]  # fmt: skip
        encoder = subprocess.Popen(cmd, stdin=subprocess.PIPE)
        started = time.monotonic()
        for i in range(frames):
            page.evaluate("t => reel.seek(t)", i / fps)
            encoder.stdin.write(page.screenshot(type="png"))
            if i % (fps * 5) == 0:
                print(f"  {vid}: frame {i}/{frames} ({time.monotonic() - started:.0f}s)", flush=True)
        encoder.stdin.close()
        if encoder.wait() != 0:
            raise SystemExit(f"{vid}: ffmpeg failed")
        mastered.unlink()
        page.evaluate("t => reel.seek(t)", duration - 1.0)
        page.screenshot(path=str(OUT / f"{vid}-poster.png"))
        browser.close()
    write_captions(vid)
    print(f"{vid}: soundtrack {loud['input_i']} LUFS before normalising to -14; voice {above:+.1f} dB over the music")
    return target, duration


def check(path: Path, expected: float) -> list[str]:
    """Inspect the encoded file without looking at it: streams, duration, black or frozen frames, loudness."""
    info = subprocess.run([FFMPEG, "-hide_banner", "-i", str(path)], capture_output=True, text=True).stderr
    problems = []
    dur = re.search(r"Duration: (\d+):(\d+):([\d.]+)", info)
    seconds = int(dur[1]) * 3600 + int(dur[2]) * 60 + float(dur[3]) if dur else 0
    video = re.search(r"Video: h264 \(High\).*?, yuv420p.*?, (\d+)x(\d+).*?, (\d+(?:\.\d+)?) fps", info)
    if not video or (video[1], video[2]) != ("1080", "1920") or float(video[3]) != 30:
        problems.append("video stream not H.264 High yuv420p 1080x1920 30 fps")
    if not re.search(r"Audio: aac.*?, (\d+) Hz, stereo", info):
        problems.append("no stereo AAC audio stream")
    if abs(seconds - expected) > 0.1:
        problems.append(f"duration {seconds:.2f}s, expected {expected}s")
    scan = subprocess.run(
        [FFMPEG, "-hide_banner", "-i", str(path), "-vf", "blackdetect=d=0.25:pix_th=0.06,freezedetect=n=-60dB:d=1.2", "-af", "ebur128=peak=true", "-f", "null", "-"],
        capture_output=True, text=True,
    )  # fmt: skip
    black = re.findall(r"black_start:([\d.]+)", scan.stderr)
    frozen = re.findall(r"freeze_start: ([\d.]+)", scan.stderr)
    loud = re.findall(r"I:\s+(-?[\d.]+) LUFS", scan.stderr)
    peak = re.findall(r"Peak:\s+(-?[\d.]+) dBFS", scan.stderr)
    if black:
        problems.append(f"black stretches at {black}")
    if frozen:
        problems.append(f"frozen picture from {frozen} s")
    if loud and abs(float(loud[-1]) + 14) > 1.0:
        problems.append(f"loudness {loud[-1]} LUFS, expected about -14")
    print(f"{path.name}: {seconds:.2f}s, 1080x1920, {path.stat().st_size / 1e6:.1f} MB, loudness {loud[-1] if loud else '?'} LUFS, true peak {peak[-1] if peak else '?'} dBFS, black: {len(black)}, frozen: {len(frozen)}")
    return problems


def main() -> None:
    ids = sys.argv[1:] or IDS
    bad = [i for i in ids if i not in IDS]
    if bad:
        raise SystemExit(f"unknown video {bad}; choose from {IDS}")
    if not (OUT.parents[1] / "promo" / "shots" / "shots.json").exists():
        raise SystemExit("no shots yet: run marketing/promo/make_promo.sh (it films them first)")
    server, base = serve()
    failed = []
    try:
        for vid in ids:
            path, duration = render(vid, base)
            failed += [f"{path.name}: {p}" for p in check(path, duration)]
    finally:
        server.shutdown()
    if failed:
        print("PROBLEMS:\n  " + "\n  ".join(failed))
        sys.exit(1)


if __name__ == "__main__":
    from pathlib import Path  # noqa: F401 (annotations above are strings)

    main()
