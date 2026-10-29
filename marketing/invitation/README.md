# Invitation campaign ("Invite, don't print")

Cards, Lettie's invitation looks, copy, plan and brand review for marketing Love of My Life as an online invitation maker
(weddings, debuts, christenings, baby showers). Not part of the app; `marketing/` is in `.dockerignore`.

| File | What it is |
|---|---|
| `CAMPAIGN_PLAN_INVITATIONS.md` | The plan: audience, messages, channels, 6-week calendar, metrics, risks |
| `COPY.md` | Posts (English + Taglish), 3 hooks, 3 Meta ads, the 20 s video script (draft) |
| `BRAND_REVIEW_INVITATIONS.md` | Your original line checked against the brand and the product, with fixes |
| `product-facts.json` | What may be claimed, with evidence, and what may not |
| `links.py` | Tagged links (`utm_campaign=invitations-2026`) |
| `build_mascot.py` | Builds `lettie-invite*.svg` from `promo/lettie.svg` (+ `qr-lettie-card.svg`, a real QR code) |
| `shots.py` | Makes the app stills from a throw-away copy of the app |
| `invite.html`, `invite.js`, `render_invite.py` | The cards, and the renderer that checks and saves them |
| `video/` | The 20 s video: `voiceover.json` + `voice.py` (Kokoro), `footage.py` (screen recordings), `compose.py` (ffmpeg + the promo's music) |
| `FB_POST.md`, `collect.py` | The Facebook caption (English + Taglish) ready to paste, and a folder with everything to upload |

## Rebuild (from the repository root)

```sh
U="uv run --no-project --python 3.12 --with playwright==1.63.0"
python3 marketing/invitation/build_mascot.py                     # the four mascot looks (SVG)
# stills: a throw-away app on :8096 (SQLite file, no e-mail check), then
#   $U python marketing/invitation/shots.py --base http://127.0.0.1:8096
#   env: APP_ENV=local SESSION_SECURE=false PAYMENTS_DRIVER=fake SEASONS_ALWAYS=true EMAIL_VERIFICATION=false
#        DB_CONNECTION=sqlite DB_DATABASE=<empty file>  (run `php naluz migrate` first, then `php -S 127.0.0.1:8096 -t public`)
$U python marketing/invitation/render_invite.py                 # all cards -> marketing/out/invitation/cards/
$U python marketing/invitation/render_invite.py inv-feed-hook   # just one
python3 marketing/invitation/links.py                            # the tagged links
# the video (needs the app on :8096 as above, then):
#   $U python marketing/invitation/video/footage.py --base http://127.0.0.1:8096
#   uv run --no-project --python 3.12 --with kokoro-onnx --with soundfile python marketing/invitation/video/voice.py
#   uv run --no-project --python 3.12 --with numpy --with scipy --with soundfile python marketing/invitation/video/compose.py
python3 marketing/invitation/collect.py                          # everything for the post -> out/invitation/post-now/
```

A card is refused if anything sticks out, if two blocks of words overlap, or if a phone or the mascot covers words.
