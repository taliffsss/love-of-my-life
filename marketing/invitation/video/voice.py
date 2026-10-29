"""Lettie's voice for the invitation video: speaks voiceover.json with Kokoro-82M (open weights, runs locally, nothing sent anywhere).

    uv run --no-project --python 3.12 --with kokoro-onnx --with soundfile python marketing/invitation/video/voice.py

Uses the model files marketing/promo/voice.py already downloaded (~/.cache/dink-promo/kokoro). Writes
marketing/out/invitation/video/voice/<id>.wav (16-bit mono) and voice.json with each line's length.
"""

from __future__ import annotations

import json
import os
from pathlib import Path

import numpy as np
import soundfile as sf
from kokoro_onnx import Kokoro

HERE = Path(__file__).resolve().parent
OUT = HERE.parents[1] / "out" / "invitation" / "video" / "voice"
MODELS = Path(os.environ.get("PROMO_TTS_CACHE", Path.home() / ".cache" / "dink-promo" / "kokoro"))


def trim(samples: np.ndarray, rate: int, floor: float = 0.008) -> np.ndarray:
    loud = np.flatnonzero(np.abs(samples) > floor)
    if not len(loud):
        return samples
    pad = int(0.04 * rate)
    return samples[max(0, loud[0] - pad) : loud[-1] + pad]


def main() -> None:
    script = json.loads((HERE / "voiceover.json").read_text())
    if not (MODELS / "kokoro-v1.0.onnx").exists():
        raise SystemExit("run marketing/promo/voice.py once first: it downloads and checks the Kokoro model files")
    kokoro = Kokoro(str(MODELS / "kokoro-v1.0.onnx"), str(MODELS / "voices-v1.0.bin"))
    OUT.mkdir(parents=True, exist_ok=True)
    done = []
    for line in script["lines"]:
        samples, rate = kokoro.create(line["say"], voice=script["voice"], speed=0.96, lang=script["lang"])
        samples = trim(samples, rate)
        sf.write(OUT / f"{line['id']}.wav", samples, rate, subtype="PCM_16")
        done.append({**line, "file": f"{line['id']}.wav", "duration": round(len(samples) / rate, 3), "rate": rate})
        print(f"  {line['id']}: {done[-1]['duration']:.2f}s  {line['text']}")
    (OUT / "voice.json").write_text(json.dumps({"voice": script["voice"], "lines": done}, indent=2) + "\n")


if __name__ == "__main__":
    main()
