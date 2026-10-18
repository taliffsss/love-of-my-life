"""Lettie's voice: speaks voiceover.json with Kokoro-82M, an open text-to-speech model (Apache-2.0 weights, runs
locally through onnxruntime; nothing is sent anywhere).

    uv run --no-project --python 3.12 --with kokoro-onnx --with soundfile python marketing/promo/voice.py
    PROMO_VOICE=af_bella ...        # another voice (af_heart, warm and friendly, is the default)

The model files (~350 MB) download once to ~/.cache/dink-promo/kokoro (shared with the Dink promo) and are checked
against their SHA-256. Each line is trimmed of silence and, if it would overrun the next cut (`until`), spoken a
little faster (up to 1.2x); a line that still does not fit stops the run. Writes marketing/out/promo/voice/<id>.wav
(16-bit mono) and voice.json, which render.py mixes in and uses for Lettie's mouth.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import urllib.request
from pathlib import Path

import numpy as np
import soundfile as sf
from kokoro_onnx import Kokoro

HERE = Path(__file__).resolve().parent
OUT = HERE.parent / "out"
MODELS = Path(os.environ.get("PROMO_TTS_CACHE", Path.home() / ".cache" / "dink-promo" / "kokoro"))
RELEASE = "https://github.com/thewh1teagle/kokoro-onnx/releases/download/model-files-v1.0/"
FILES = {
    "kokoro-v1.0.onnx": "7d5df8ecf7d4b1878015a32686053fd0eebe2bc377234608764cc0ef3636a6c5",
    "voices-v1.0.bin": "bca610b8308e8d99f32e6fe4197e7ec01679264efed0cac9140fe9c29f1fbf7d",
}
VOICE = os.environ.get("PROMO_VOICE", "af_heart")
MAX_SPEED = 1.2


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as f:
        for block in iter(lambda: f.read(1 << 20), b""):
            digest.update(block)
    return digest.hexdigest()


def fetch_models() -> None:
    MODELS.mkdir(parents=True, exist_ok=True)
    for name, expected in FILES.items():
        path = MODELS / name
        if path.exists() and sha256(path) == expected:
            continue
        print(f"downloading {name} ...", flush=True)
        partial = path.with_suffix(".part")
        urllib.request.urlretrieve(RELEASE + name, partial)  # noqa: S310 (fixed https URL)
        if sha256(partial) != expected:
            partial.unlink()
            raise SystemExit(f"{name}: checksum mismatch, refusing to use it")
        partial.rename(path)


def trim(samples: np.ndarray, rate: int, floor: float = 0.008) -> np.ndarray:
    loud = np.flatnonzero(np.abs(samples) > floor)
    if not len(loud):
        return samples
    pad = int(0.03 * rate)
    return samples[max(0, loud[0] - pad) : loud[-1] + pad]


def main() -> None:
    parser = argparse.ArgumentParser(description="Speak Lettie's lines.")
    parser.parse_args()
    out = OUT / "promo" / "voice"
    script = json.loads((HERE / "voiceover.json").read_text())
    fetch_models()
    kokoro = Kokoro(str(MODELS / "kokoro-v1.0.onnx"), str(MODELS / "voices-v1.0.bin"))
    if VOICE not in kokoro.get_voices():
        raise SystemExit(f"unknown voice {VOICE!r}; try one of {sorted(kokoro.get_voices())}")
    out.mkdir(parents=True, exist_ok=True)
    for old in out.glob("*.wav"):
        old.unlink()
    done = []
    for line in script["lines"]:
        budget = line["until"] - line["at"]
        speed = 1.0
        while True:
            samples, rate = kokoro.create(
                line.get("say", line["text"]), voice=VOICE, speed=speed, lang=script["lang"]
            )
            samples = trim(samples, rate)
            duration = len(samples) / rate
            if duration <= budget or speed >= MAX_SPEED:
                break
            speed = min(MAX_SPEED, round(speed * duration / budget + 0.02, 3))
        if duration > budget:
            raise SystemExit(
                f"{line['id']}: {duration:.2f}s does not fit its {budget:.2f}s even at {speed}x;"
                " shorten the line in voiceover.json"
            )
        sf.write(out / f"{line['id']}.wav", samples, rate, subtype="PCM_16")
        done.append({**line, "file": f"{line['id']}.wav", "duration": round(duration, 3),
                     "speed": speed, "rate": rate})  # fmt: skip
        print(f"  {line['id']}: {duration:.2f}s of {budget:.2f}s at {speed}x  {line['text']}")
    (out / "voice.json").write_text(json.dumps({"voice": VOICE, "lines": done}, indent=2) + "\n")


if __name__ == "__main__":
    main()
