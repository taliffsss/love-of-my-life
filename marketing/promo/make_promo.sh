#!/usr/bin/env bash
# Builds the Love of My Life promo, narrated by Lettie, into marketing/out/promo/ (git-ignored):
#
#   love-of-my-life-promo-16x9.mp4   1920x1080  YouTube, Facebook feed, the website
#   love-of-my-life-promo-9x16.mp4   1080x1920  Reels, TikTok, Shorts, Stories (captions burned in)
#   *-poster.png                     the end card, for thumbnails
#   love-of-my-life-promo.en_US.srt  captions of the narration (upload with the 16:9 video)
#
#   marketing/promo/make_promo.sh            # film the app, speak the lines, render both (~20 min)
#   marketing/promo/make_promo.sh render     # re-render only, re-using the clips and the voice
#   PROMO_VOICE=af_bella marketing/promo/make_promo.sh   # another narrator (af_heart by default)
#
# 1. A copy of the app runs on 127.0.0.1:8095 with its own throw-away SQLite file (never your database), and
#    shots.py films its /demo page in slow motion: the templates being opened, plus a still of each.
# 2. voice.py speaks voiceover.json with Kokoro-82M, an open text-to-speech model that runs locally (Apache-2.0).
# 3. render.py steps promo.html frame by frame, synthesises the music (music.py), mixes the voice, encodes H.264/AAC
#    at -14 LUFS, then checks the files. Playwright, ffmpeg, numpy and scipy come from uv's cache for this run only.
set -euo pipefail
cd "$(dirname "$0")/../.."
export VIRTUAL_ENV=
OUT=marketing/out/promo
PORT=8095
mkdir -p "$OUT"
PY="uv run --no-project --python 3.12 --with playwright==1.63.0"

php -r 'require "vendor/autoload.php"; file_put_contents($argv[1], App\Services\QrCode::svg("https://love.markanthonynaluz.com"));' "$OUT/qr.svg"

if [ "${1:-}" != "render" ]; then
  if lsof -nP -iTCP:$PORT -sTCP:LISTEN >/dev/null 2>&1; then
    echo "port $PORT is taken; stop whatever runs there first" >&2
    exit 1
  fi
  rm -f "$OUT/app.sqlite" && touch "$OUT/app.sqlite"
  export APP_ENV=local APP_DEBUG=false APP_URL=http://127.0.0.1:$PORT SAAS_DOMAIN=localhost SESSION_SECURE=false \
    PAYMENTS_DRIVER=fake SPACES_ACCESS_KEY= SPACES_SECRET_KEY= SPACES_BUCKET= \
    DB_CONNECTION=sqlite DB_DATABASE="$PWD/$OUT/app.sqlite"
  php naluz migrate >/dev/null
  php -S 127.0.0.1:$PORT -t public >"$OUT/app.log" 2>&1 &
  APP=$!
  trap 'kill $APP 2>/dev/null || true' EXIT
  for _ in $(seq 1 30); do curl -fsS "http://127.0.0.1:$PORT/api/ping" >/dev/null 2>&1 && break; sleep 0.5; done
  $PY python marketing/promo/shots.py --base "http://127.0.0.1:$PORT"
  kill $APP 2>/dev/null || true
  uv run --no-project --python 3.12 --with kokoro-onnx --with soundfile python marketing/promo/voice.py
fi
$PY --with imageio-ffmpeg --with numpy --with scipy python marketing/promo/render.py landscape portrait
echo "Done: $OUT/"
