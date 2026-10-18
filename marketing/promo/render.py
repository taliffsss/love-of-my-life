"""Render the Love of My Life promo (promo.html) to MP4, narrated by Lettie.

    marketing/promo/make_promo.sh                    # film, speak and render everything (the usual way)
    uv run --no-project --python 3.12 --with playwright==1.63.0 --with imageio-ffmpeg --with numpy --with scipy \\
        python marketing/promo/render.py landscape portrait

Serves marketing/ on a private localhost port, steps the timeline frame by frame in headless Chromium
(`promo.seek(t)` + a screenshot per frame, piped straight into ffmpeg), synthesises the soundtrack around the
timeline's cues (music.py), mixes Lettie's lines over it (voice.py made them), moves her mouth with her voice,
normalises to -14 LUFS (the level YouTube, Instagram and TikTok play at) and muxes H.264 + AAC with faststart. Before
rendering it audits the text layout in every scene; after, it checks the file (duration, streams, black or frozen
stretches, loudness). Adapted from the Dink promo's render.py. Output: marketing/out/promo/.
"""

from __future__ import annotations

import argparse
import functools
import http.server
import json
import re
import subprocess
import sys
import threading
import time
import wave
from pathlib import Path

import imageio_ffmpeg
import numpy as np
from music import RATE, soundtrack, with_voice
from playwright.sync_api import sync_playwright
from scipy.signal import resample_poly

ROOT = Path(__file__).resolve().parents[2]
FFMPEG = imageio_ffmpeg.get_ffmpeg_exe()
SERVED = ("/marketing/promo/", "/marketing/out/promo/")
FORMATS = {"landscape": "16x9", "portrait": "9x16"}
MIN_VOICE_OVER_BED_DB = 6.0
DURATION = 37.5
OUT = ROOT / "marketing" / "out" / "promo"
VOICE = OUT / "voice"
STEM = "love-of-my-life-promo"
NAMES = {fmt: f"{STEM}-{ratio}" for fmt, ratio in FORMATS.items()}


class Handler(http.server.SimpleHTTPRequestHandler):
    """Serves only what the promo page needs (never the rest of the repository)."""

    def do_GET(self):
        if not self.path.split("?")[0].startswith(SERVED):
            self.send_error(404)
            return
        super().do_GET()

    def log_message(self, *args):
        pass


def serve() -> tuple[http.server.ThreadingHTTPServer, str]:
    handler = functools.partial(Handler, directory=str(ROOT))
    server = http.server.ThreadingHTTPServer(("127.0.0.1", 0), handler)
    threading.Thread(target=server.serve_forever, daemon=True).start()
    return server, f"http://127.0.0.1:{server.server_address[1]}"


def write_wav(path: Path, samples: np.ndarray) -> None:
    pcm = (np.clip(samples, -1, 1) * 32767).astype("<i2")
    with wave.open(str(path), "wb") as w:
        w.setnchannels(2)
        w.setsampwidth(2)
        w.setframerate(RATE)
        w.writeframes(pcm.tobytes())


def loudnorm(src: Path, dst: Path) -> dict:
    """Two-pass EBU R128 normalisation to -14 LUFS integrated, then a -2.5 dBFS peak limiter so the
    AAC-encoded file stays under -1 dBTP (the encoder overshoots sample peaks a little)."""
    target = "I=-14:TP=-2:LRA=11"
    probe = subprocess.run(
        [FFMPEG, "-hide_banner", "-i", str(src), "-af", f"loudnorm={target}:print_format=json",
         "-f", "null", "-"],
        capture_output=True, text=True, check=True,
    )  # fmt: skip
    m = json.loads(probe.stderr[probe.stderr.rindex("{") : probe.stderr.rindex("}") + 1])
    measured = (
        f"measured_I={m['input_i']}:measured_TP={m['input_tp']}:measured_LRA={m['input_lra']}:"
        f"measured_thresh={m['input_thresh']}:offset={m['target_offset']}:linear=true"
    )
    subprocess.run(
        [FFMPEG, "-hide_banner", "-loglevel", "error", "-y", "-i", str(src), "-af",
         f"loudnorm={target}:{measured},aresample={RATE},"
         "alimiter=limit=0.75:attack=3:release=60:level=disabled", "-ar", str(RATE), str(dst)],
        check=True,
    )  # fmt: skip
    return m


def narration() -> list[tuple[float, np.ndarray]] | None:
    """The synthesised lines (voice.py), resampled to the soundtrack's rate; None if not made."""
    index = VOICE / "voice.json"
    if not index.exists():
        return None
    lines = []
    for line in json.loads(index.read_text())["lines"]:
        with wave.open(str(VOICE / line["file"])) as w:
            pcm = np.frombuffer(w.readframes(w.getnframes()), dtype="<i2") / 32768.0
            rate = w.getframerate()
        lines.append((line["at"], resample_poly(pcm, RATE, rate)))
    return lines


def mouth_shapes(fps: int, duration: float) -> list[float]:
    """How open Lettie's mouth is on every frame (0..1): the loudness of her voice, smoothed a little so it moves
    like talking and not like a meter."""
    shapes = np.zeros(int(round(fps * duration)) + 1)
    index = VOICE / "voice.json"
    if not index.exists():
        return shapes.tolist()
    for line in json.loads(index.read_text())["lines"]:
        with wave.open(str(VOICE / line["file"])) as w:
            pcm = np.frombuffer(w.readframes(w.getnframes()), dtype="<i2") / 32768.0
            rate = w.getframerate()
        hop = rate // fps
        for k in range(len(pcm) // hop):
            frame = int(round(line["at"] * fps)) + k
            if frame < len(shapes):
                shapes[frame] = np.sqrt(np.mean(pcm[k * hop : (k + 1) * hop] ** 2))
    peak = np.percentile(shapes[shapes > 0], 95) if np.any(shapes > 0) else 1.0
    shapes = np.clip(shapes / (peak or 1.0), 0, 1) ** 0.7
    smooth = np.convolve(shapes, [0.25, 0.5, 0.25], mode="same")
    return [round(float(v), 3) for v in smooth]


def write_captions() -> Path | None:
    """SRT captions of the narration (Facebook/YouTube accept them as an upload)."""
    index = VOICE / "voice.json"
    if not index.exists():
        return None

    def stamp(s: float) -> str:
        ms = int(round(s * 1000))
        return f"{ms // 3600000:02d}:{ms // 60000 % 60:02d}:{ms // 1000 % 60:02d},{ms % 1000:03d}"

    cues = []
    for i, line in enumerate(json.loads(index.read_text())["lines"], 1):
        end = line["at"] + line["duration"] + 0.25
        cues.append(f"{i}\n{stamp(line['at'])} --> {stamp(end)}\n{line['text']}\n")
    path = OUT / f"{STEM}.en_US.srt"
    path.write_text("\n".join(cues))
    return path


def render(
    fmt: str, base: str, *, skip_audit: bool = False, voice: bool = True, audio_only: bool = False
) -> Path:  # noqa: C901 (one pass through the steps, in order)
    OUT.mkdir(parents=True, exist_ok=True)
    name = NAMES[fmt]
    target = OUT / f"{name}.mp4"
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        size = {"landscape": (1920, 1080), "portrait": (1080, 1920)}[fmt]
        page = browser.new_page(
            viewport={"width": size[0], "height": size[1]}, device_scale_factor=1
        )
        errors: list[str] = []
        page.on("pageerror", lambda exc: errors.append(str(exc)))
        page.on("console", lambda msg: msg.type == "error" and errors.append(msg.text))
        page.goto(f"{base}/marketing/promo/promo.html?format={fmt}")
        page.evaluate("() => window.promo.ready")
        info = page.evaluate(
            "() => ({fps: promo.fps, duration: promo.duration, cues: promo.cues,"
            " scenes: promo.scenes, groove: promo.groove, fonts: promo.fonts()})"
        )
        if errors:
            raise SystemExit(f"{fmt}: page errors: {errors}")
        missing = [name for name, ok in info["fonts"] if not ok]
        if missing:
            raise SystemExit(f"{fmt}: fonts not loaded: {missing} (the page needs Google Fonts)")
        if voice:
            page.evaluate("m => promo.setMouth(m)", mouth_shapes(info["fps"], info["duration"]))

        # layout audit: sample every scene at several points
        problems = []
        for key, (a, b) in info["scenes"].items():
            for frac in (0.35, 0.6, 0.9):
                t = round(a + (b - a) * frac, 2)
                problems += [
                    f"{t:5.2f}s [{key}] {p}" for p in page.evaluate("t => promo.audit(t)", t)
                ]
        if problems:
            print(f"{fmt}: layout problems:\n  " + "\n  ".join(problems))
            if not skip_audit:
                raise SystemExit(1)

        fps, duration = info["fps"], info["duration"]
        frames = int(round(fps * duration))
        audio = OUT / f"{name}.raw.wav"
        mastered = OUT / f"{name}.wav"
        bed = soundtrack(info["cues"], duration, tuple(info["groove"]))
        lines = narration() if voice else None
        if lines:
            bed, above = with_voice(bed, lines)
            print(f"{fmt}: narration sits {above:+.1f} dB above the music while speaking")
            if above < MIN_VOICE_OVER_BED_DB:
                raise SystemExit(f"{fmt}: narration buried (< {MIN_VOICE_OVER_BED_DB} dB)")
        elif voice:
            print(f"{fmt}: no narration found (run voice.py); rendering music only")
        write_wav(audio, bed)
        loud = loudnorm(audio, mastered)
        audio.unlink()

        if audio_only:
            if not target.exists():
                raise SystemExit(f"{fmt}: no {target.name} to put the new soundtrack into")
            remuxed = target.with_suffix(".tmp.mp4")
            subprocess.run(
                [FFMPEG, "-hide_banner", "-loglevel", "error", "-y", "-i", str(target),
                 "-i", str(mastered), "-map", "0:v", "-map", "1:a", "-c:v", "copy",
                 "-c:a", "aac", "-b:a", "192k", "-ar", "48000", "-movflags", "+faststart",
                 "-shortest", str(remuxed)],
                check=True,
            )  # fmt: skip
            remuxed.replace(target)
            mastered.unlink()
            browser.close()
            print(f"{fmt}: soundtrack replaced ({loud['input_i']} LUFS before normalising)")
            return target

        cmd = [
            FFMPEG, "-hide_banner", "-loglevel", "error", "-y",
            "-f", "image2pipe", "-framerate", str(fps), "-c:v", "png", "-i", "-",
            "-i", str(mastered),
            "-map", "0:v", "-map", "1:a",
            "-c:v", "libx264", "-preset", "slow", "-crf", "17", "-pix_fmt", "yuv420p",
            "-profile:v", "high", "-level", "4.1", "-g", str(fps * 2), "-r", str(fps),
            "-c:a", "aac", "-b:a", "192k", "-ar", "48000",
            "-movflags", "+faststart", "-shortest", str(target),
        ]  # fmt: skip
        encoder = subprocess.Popen(cmd, stdin=subprocess.PIPE)
        started = time.monotonic()
        for i in range(frames):
            page.evaluate("t => promo.seek(t)", i / fps)
            encoder.stdin.write(page.screenshot(type="png"))
            if i % (fps * 5) == 0:
                print(
                    f"  {fmt}: frame {i}/{frames} ({time.monotonic() - started:.0f}s)", flush=True
                )
        encoder.stdin.close()
        if encoder.wait() != 0:
            raise SystemExit(f"{fmt}: ffmpeg failed")
        mastered.unlink()

        page.evaluate("t => promo.seek(t)", duration - 1.2)
        page.screenshot(path=str(OUT / f"{name}-poster.png"))
        browser.close()
    print(f"{fmt}: soundtrack measured {loud['input_i']} LUFS before normalising to -14")
    return target


def check(path: Path, fmt: str) -> list[str]:
    """Inspect the encoded file without looking at it: streams, duration, black or frozen frames,
    loudness. Returns problems (empty = fine) and prints a short report."""
    probe = subprocess.run(
        [FFMPEG, "-hide_banner", "-i", str(path)], capture_output=True, text=True
    )
    info = probe.stderr
    problems = []
    dur = re.search(r"Duration: (\d+):(\d+):([\d.]+)", info)
    seconds = int(dur[1]) * 3600 + int(dur[2]) * 60 + float(dur[3]) if dur else 0
    video = re.search(
        r"Video: h264 \(High\).*?, yuv420p.*?, (\d+)x(\d+).*?, (\d+(?:\.\d+)?) fps", info
    )
    audio = re.search(r"Audio: aac.*?, (\d+) Hz, stereo", info)
    expected = {"landscape": ("1920", "1080"), "portrait": ("1080", "1920")}[fmt]
    if not video or (video[1], video[2]) != expected or float(video[3]) != 30:
        problems.append(f"video stream not H.264 High yuv420p {expected} 30 fps")
    if not audio:
        problems.append("no stereo AAC audio stream")
    if abs(seconds - DURATION) > 0.1:
        problems.append(f"duration {seconds:.2f}s, expected {DURATION}s")
    scan = subprocess.run(
        [FFMPEG, "-hide_banner", "-i", str(path), "-vf",
         "blackdetect=d=0.25:pix_th=0.06,freezedetect=n=-60dB:d=1.2", "-af", "ebur128=peak=true",
         "-f", "null", "-"],
        capture_output=True, text=True,
    )  # fmt: skip
    black = re.findall(r"black_start:([\d.]+) black_end:([\d.]+)", scan.stderr)
    frozen = re.findall(r"freeze_start: ([\d.]+)", scan.stderr)
    loud = re.findall(r"I:\s+(-?[\d.]+) LUFS", scan.stderr)
    peak = re.findall(r"Peak:\s+(-?[\d.]+) dBFS", scan.stderr)
    if black:
        problems.append(f"black stretches at {black}")
    if frozen:
        problems.append(f"frozen picture from {frozen} s")
    size = path.stat().st_size / 1_000_000
    print(
        f"{path.name}: {seconds:.2f}s, {video[1] if video else '?'}x{video[2] if video else '?'}, "
        f"{size:.1f} MB, loudness {loud[-1] if loud else '?'} LUFS, true peak "
        f"{peak[-1] if peak else '?'} dBFS, black: {len(black)}, frozen: {len(frozen)}"
    )
    return problems


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument(
        "formats", nargs="*", default=["landscape", "portrait"], choices=list(FORMATS)
    )
    parser.add_argument("--skip-audit", action="store_true", help="render despite layout problems")
    parser.add_argument("--no-voice", action="store_true", help="music and effects only")
    parser.add_argument(
        "--audio-only",
        action="store_true",
        help="rebuild the soundtrack (e.g. new narration) into the existing videos; no re-render",
    )
    args = parser.parse_args()
    if not (OUT / "shots" / "shots.json").exists():
        raise SystemExit("no shots yet: run marketing/promo/make_promo.sh (it films them first)")
    server, base = serve()
    failed = []
    try:
        for fmt in args.formats:
            path = render(
                fmt,
                base,
                skip_audit=args.skip_audit,
                voice=not args.no_voice,
                audio_only=args.audio_only,
            )
            failed += [f"{path.name}: {p}" for p in check(path, fmt)]
    finally:
        server.shutdown()
    if not args.no_voice and (captions := write_captions()):
        print(f"captions: {captions.name}")
    if failed:
        print("PROBLEMS:\n  " + "\n  ".join(failed))
        sys.exit(1)


if __name__ == "__main__":
    main()
