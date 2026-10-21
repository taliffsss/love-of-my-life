#!/usr/bin/env bash
# Builds the marketing material: promo video, cards, post images, ad carousels, reels and the ready-to-upload folders.
# Run everything, or just one thing. From anywhere; it works from the repository root.
#
#   marketing/make.sh                      # same as: marketing/make.sh help
#   marketing/make.sh all                  # everything (the promo takes ~20 min the first time)
#   marketing/make.sh halloween            # the whole Halloween campaign: footage, pictures, ads, carousels, reels, folders
#   marketing/make.sh halloween reels      # only part of it: footage | images | ads | carousel | reels | collect
#   marketing/make.sh images               # all post images (Facebook + TikTok + Halloween)
#   marketing/make.sh image hw-door ad-hw-1   # only these images
#   marketing/make.sh reels                # voice + video for every reel
#   marketing/make.sh reel halloween-knock # voice + video for one reel
#   marketing/make.sh --dry-run halloween  # show what would run, run nothing
#
# Targets (each one also needs the ones above it in the list the first time; `all` runs them in order):
#   promo      the 37 s promo video and the app footage every other step re-uses      marketing/promo/make_promo.sh
#   footage    film the app only (all clips, or: footage halloween halloweenlock)     marketing/promo/shots.py
#   cards      older social cards and Lettie stickers                                  marketing/cards/render_cards.py
#   images     every post image                                                         marketing/social/render_social.py
#   ads        the Meta ad pictures: carousel (ad-hw-1..5), feed link, story
#   carousel   the photo-mode carousels (car-*), TikTok / Reels
#   voice      Lettie's voice for the reels                                             marketing/social/reels/voice_reels.py
#   reels      voice + video for the reels                                              marketing/social/reels/render_reels.py
#   collect    the ready-to-upload folders: out/social/post-now and out/social/halloween
#   halloween  footage + images + ads + carousel + reels (Halloween only) + collect
#   links      the UTM-tagged links of the campaign (marketing/UTM.md)
#   list       the image names and reel ids you can ask for
#
# Needs `uv`, PHP with vendor/ and an internet connection (Google Fonts); see marketing/README.md. Output: marketing/out/ (git-ignored).
set -euo pipefail
SELF="$(cd "$(dirname "$0")" && pwd)/$(basename "$0")"
cd "$(dirname "$0")/.."
export VIRTUAL_ENV=

DRY=0
if [ "${1:-}" = "--dry-run" ] || [ "${1:-}" = "-n" ]; then DRY=1; shift; fi

PORT="${MARKETING_PORT:-8095}"
OUT=marketing/out
PW="uv run --no-project --python 3.12 --with playwright==1.63.0"
VID="$PW --with imageio-ffmpeg --with numpy --with scipy"
HALLOWEEN_IMAGES="hw-hook hw-door hw-lock hw-steps hw-poll ad-hw-1 ad-hw-2 ad-hw-3 ad-hw-4 ad-hw-5 ad-hw-feed ad-hw-story car-hw-1 car-hw-2 car-hw-3 car-hw-4 car-hw-5"
HALLOWEEN_ADS="ad-hw-1 ad-hw-2 ad-hw-3 ad-hw-4 ad-hw-5 ad-hw-feed ad-hw-story"
HALLOWEEN_CAROUSEL="car-hw-1 car-hw-2 car-hw-3 car-hw-4 car-hw-5"
HALLOWEEN_REELS="halloween-knock halloween-lock"
HALLOWEEN_CLIPS="halloween halloweenlock stills"

say() { printf '\n\033[1m▶ %s\033[0m\n' "$*"; }
run() { if [ "$DRY" = 1 ]; then printf '  would run: %s\n' "$*"; else eval "$@"; fi; }
need() { command -v "$1" >/dev/null 2>&1 || { echo "missing '$1': $2" >&2; exit 1; }; }

check_tools() { [ "$DRY" = 1 ] && return 0; need uv "install uv (https://docs.astral.sh/uv/)"; need php "PHP is needed for the QR code and the footage"; [ -d vendor ] || { echo "run 'composer install' first" >&2; exit 1; }; }

need_footage() {
  [ "$DRY" = 1 ] && return 0
  [ -f "$OUT/promo/shots/shots.json" ] || { echo "no footage yet: run 'marketing/make.sh footage' (or 'promo') first" >&2; exit 1; }
}

# The app on 127.0.0.1:$PORT with a throw-away SQLite file (never your database), for the footage.
APP_PID=""
start_app() {
  mkdir -p "$OUT/promo"
  if lsof -nP -iTCP:"$PORT" -sTCP:LISTEN >/dev/null 2>&1; then echo "port $PORT is taken; stop whatever runs there (or set MARKETING_PORT)" >&2; exit 1; fi
  rm -f "$OUT/promo/app.sqlite" && touch "$OUT/promo/app.sqlite"
  APP_ENV=local APP_DEBUG=false APP_URL="http://127.0.0.1:$PORT" SAAS_DOMAIN=localhost SESSION_SECURE=false PAYMENTS_DRIVER=fake \
    SPACES_ACCESS_KEY= SPACES_SECRET_KEY= SPACES_BUCKET= SEASONS_ALWAYS=true DB_CONNECTION=sqlite DB_DATABASE="$PWD/$OUT/promo/app.sqlite" \
    php naluz migrate >/dev/null
  APP_ENV=local APP_DEBUG=false APP_URL="http://127.0.0.1:$PORT" SAAS_DOMAIN=localhost SESSION_SECURE=false PAYMENTS_DRIVER=fake \
    SPACES_ACCESS_KEY= SPACES_SECRET_KEY= SPACES_BUCKET= SEASONS_ALWAYS=true DB_CONNECTION=sqlite DB_DATABASE="$PWD/$OUT/promo/app.sqlite" \
    php -S "127.0.0.1:$PORT" -t public >"$OUT/promo/app.log" 2>&1 &
  APP_PID=$!
  trap 'kill $APP_PID 2>/dev/null || true' EXIT
  for _ in $(seq 1 30); do curl -fsS "http://127.0.0.1:$PORT/api/ping" >/dev/null 2>&1 && break; sleep 0.5; done
}

t_promo()    { say "Promo video + app footage (~20 min)"; run marketing/promo/make_promo.sh "${@}"; }
t_footage()  {
  say "Filming the app${*:+: $*}"
  if [ "$DRY" = 1 ]; then echo "  would run: start the app on :$PORT, then shots.py $*"; return; fi
  mkdir -p "$OUT/promo"; php -r 'require "vendor/autoload.php"; file_put_contents($argv[1], App\Services\QrCode::svg("https://loveofmylife.love"));' "$OUT/promo/qr.svg"
  start_app
  $PW python marketing/promo/shots.py --base "http://127.0.0.1:$PORT" "$@"
  kill "$APP_PID" 2>/dev/null || true; APP_PID=""
}
t_cards()    { say "Social cards and Lettie stickers"; need_footage; run "$PW python marketing/cards/render_cards.py $*"; }
t_images()   { say "Post images${*:+: $*}"; need_footage; run "$PW python marketing/social/render_social.py $*"; }
t_ads()      { say "Meta ad pictures"; t_images ${HALLOWEEN_ADS}; }
t_carousel() { say "Photo-mode carousels"; t_images ${HALLOWEEN_CAROUSEL} car-ofw-1 car-ofw-2 car-ofw-3 car-ofw-4 car-ofw-5 car-monthsary-1 car-monthsary-2 car-monthsary-3 car-monthsary-4 car-monthsary-5 car-templates-1 car-templates-2 car-templates-3 car-templates-4 car-templates-5; }
t_voice()    { say "Lettie's voice${*:+: $*}"; need_footage; run "uv run --no-project --python 3.12 --with playwright==1.63.0 --with kokoro-onnx --with soundfile python marketing/social/reels/voice_reels.py $*"; }
t_reels()    { say "Reels${*:+: $*}"; t_voice "$@"; run "$VID python marketing/social/reels/render_reels.py $*"; }
t_collect()  { say "Ready-to-upload folders"; run python3 marketing/social/build_post_now.py; run python3 marketing/social/build_halloween.py; }

t_halloween() {
  local part="${1:-all}"; shift || true
  case "$part" in
    footage)  t_footage $HALLOWEEN_CLIPS ;;
    images)   t_images $HALLOWEEN_IMAGES ;;
    ads)      t_images $HALLOWEEN_ADS ;;
    carousel) t_images $HALLOWEEN_CAROUSEL ;;
    reels)    t_reels $HALLOWEEN_REELS ;;
    collect)  run python3 marketing/social/build_halloween.py ;;
    all)      t_footage $HALLOWEEN_CLIPS; t_images $HALLOWEEN_IMAGES; t_reels $HALLOWEEN_REELS; run python3 marketing/social/build_halloween.py ;;
    *) echo "halloween: unknown part '$part' (footage|images|ads|carousel|reels|collect)" >&2; exit 2 ;;
  esac
}

t_list() {
  echo "Images (names for: image <name>...):"
  grep -o '^    "[a-z0-9-]*":' marketing/social/social.js | tr -d ' ":' | tr '\n' ' '; echo
  echo; echo "Reels (ids for: reel <id>...):"
  grep -o '^IDS = \[[^]]*\]' marketing/social/reels/common.py | sed 's/IDS = \[//; s/\]//; s/"//g; s/,//g'
  echo; echo "Footage clips (for: footage <clip>...): lock envelope scratch storybook stars gift halloween halloweenlock stills"
  echo; echo "Halloween images: $HALLOWEEN_IMAGES"
}

t_links()    { say "UTM-tagged links"; run python3 marketing/social/utm.py "$@"; }
t_help() { sed -n '2,/^set -euo/p' "$SELF" | sed '$d' | sed 's/^# \{0,1\}//'; }

t_all() {
  [ -f "$OUT/promo/shots/shots.json" ] || t_promo
  t_footage halloween halloweenlock stills
  t_cards
  t_images
  t_reels
  t_collect
}

target="${1:-help}"; [ "$#" -gt 0 ] && shift
case "$target" in
  help|-h|--help) t_help; exit 0 ;;
  list)           t_list; exit 0 ;;
esac
check_tools
case "$target" in
  all)       t_all ;;
  promo)     t_promo "$@" ;;
  footage)   t_footage "$@" ;;
  cards)     t_cards "$@" ;;
  images)    t_images "$@" ;;
  image)     [ "$#" -gt 0 ] || { echo "image: name at least one (see: marketing/make.sh list)" >&2; exit 2; }; t_images "$@" ;;
  ads)       t_ads ;;
  carousel)  t_carousel ;;
  voice)     t_voice "$@" ;;
  reels)     t_reels "$@" ;;
  reel)      [ "$#" -gt 0 ] || { echo "reel: name at least one id (see: marketing/make.sh list)" >&2; exit 2; }; t_reels "$@" ;;
  collect)   t_collect ;;
  links)     t_links "$@" ;;
  halloween) t_halloween "$@" ;;
  *) echo "unknown target '$target'" >&2; t_help >&2; exit 2 ;;
esac
say "Done. Output: $OUT/"
