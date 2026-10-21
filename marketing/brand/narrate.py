"""Slow, warm narration for a campaign folder, with the pacing set on the voice (the skill's `produce` has no speed setting).

    ELEVENLABS_API_KEY=... python3 marketing/brand/narrate.py .marketing-output/<campaign> <voice id> [speed]

Writes generated/scene-NN.mp3 and alignment-NN.json (the same files `produce` writes, so the skill's `render` makes the
captions from them). Billable: run it only after the storyboard is approved. speed 0.7 to 1.2 (default 0.88).
"""
import base64, json, os, sys, urllib.parse, urllib.request
from pathlib import Path

folder, voice = Path(sys.argv[1]), sys.argv[2]
speed = float(sys.argv[3]) if len(sys.argv) > 3 else 0.88
key = os.environ["ELEVENLABS_API_KEY"]
sb = json.load(open(folder / "storyboard.json"))
if not sb.get("approved"):
    raise SystemExit("storyboard is not approved")
(folder / "generated").mkdir(exist_ok=True)
for s in sb["scenes"]:
    body = {"text": s["narration"], "model_id": "eleven_multilingual_v2",
            "voice_settings": {"stability": 0.55, "similarity_boost": 0.8, "style": 0.3, "speed": speed, "use_speaker_boost": True}}
    req = urllib.request.Request(f"https://api.elevenlabs.io/v1/text-to-speech/{urllib.parse.quote(voice)}/with-timestamps",
                                 json.dumps(body).encode(), {"xi-api-key": key, "Content-Type": "application/json"})
    result = json.load(urllib.request.urlopen(req, timeout=120))
    (folder / "generated" / f"scene-{s['id']}.mp3").write_bytes(base64.b64decode(result["audio_base64"]))
    json.dump({"alignment": result.get("normalized_alignment") or result.get("alignment") or {}, "text": s["narration"]},
              open(folder / "generated" / f"alignment-{s['id']}.json", "w"))
    print("scene", s["id"], "ok", flush=True)
