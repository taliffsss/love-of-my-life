# Social kit: Facebook Page and TikTok

Branding and ready-to-post material for the Love of My Life Facebook Page and TikTok account: **56 images**, captions,
a 30-day plan, TikTok scripts and the profile setup text. Not part of the app (`.dockerignore` excludes `marketing/`).

| File | What's in it |
|---|---|
| `marketing/out/social/index.html` | Contact sheet of every image (open it in a browser) |
| `PAGE_SETUP.md` | Names, bios, About text, buttons, auto-reply, reply bank, **what to check before posting** |
| `CONTENT_PLAN.md` | Services, audiences, messaging angles, honesty rules, cadence, 30-day calendar, metrics, idea bank |
| `CAPTIONS.md` | A caption (English and Taglish) for every image, TikTok captions, hashtags |
| `TIKTOK_SCRIPTS.md` | Eight video scripts, carousels, safe zones |

## The images (`marketing/out/social/`, git-ignored)

| Group | Files | Size |
|---|---|---|
| Profile pictures | `fb-profile`, `tt-profile`, `profile-lettie` | 1080×1080 (circle crop) |
| Cover photos | `fb-cover`, `fb-cover-ofw` | 1640×624 |
| Services | `svc-partner`, `svc-anniversary`, `svc-wedding`, `svc-birthday`, `svc-invitation` | 1080×1350 |
| Plans | `plan-sweet`, `plan-sweetheart`, `plan-forever` (1080×1350), `plan-photos` (1080×1080) | |
| Audiences | `ofw-miles`, `ofw-timezone`, `ofw-taglish`, `couples-monthsary`, `couples-ldr`, `teen-first`, `teen-ask` | 1:1 or 4:5 |
| Engagement | `poll-open`, `poll-gift`, `fill-song`, `tag-person`, `quote-*`, `how-3-steps`, `free-first`, `qr-card` | 1:1 or 4:5 |
| Launch | `soon`, `live` | 1080×1350 |
| TikTok | `tt-cover-*` (8 video covers), `car-ofw-1…5`, `car-monthsary-1…5`, `car-templates-1…5` | 1080×1920 |

The phone screens are the app's own demo pages (sample content, not real couples). Each occasion post uses its own
colours (gold anniversary, ivory wedding, confetti birthday, navy invitations); the phones show templates, not the
occasion's own decoration.

## Rebuild

```sh
uv run --no-project --python 3.12 --with playwright==1.63.0 python marketing/social/render_social.py          # all
uv run --no-project --python 3.12 --with playwright==1.63.0 python marketing/social/render_social.py fb-cover # one
```

Needs the promo's stills in `marketing/out/promo/shots/stills/` (they exist; `marketing/promo/make_promo.sh` makes them)
and an internet connection for Google Fonts. The renderer first checks that the prices on the cards still match
`config/saas.php` (it stops if a price changed), then checks every image: nothing may stick out, no words may overlap
each other or a phone or Lettie. Change words in `social.js` (`CARDS`), the domain in `URL_TEXT`.

## Not done / needs you

- Videos: only the existing narrated promo (`marketing/out/promo/`) is ready; the eight scripts need to be filmed.
- The images were verified by layout checks only (no overflow, no overlap, fonts and photos loaded, TikTok safe zone); nobody has looked at them yet. Skim `index.html` before posting.
- Domain/DNS, checkout wording, own-address DNS and the under-18 policy: see `PAGE_SETUP.md`, "Before you post".

## Facebook posts and videos

`FACEBOOK_POSTS.md` has 5 photo posts and 5 video posts (captions in English and Taglish, which images, when to post).
The five videos (`marketing/out/social/reels/*.mp4`, 1080×1920, about 15 s, narrated by Lettie, captions burned in) are
made in `reels/`:

```sh
U="uv run --no-project --python 3.12"
$U --with playwright==1.63.0 --with kokoro-onnx --with soundfile python marketing/social/reels/voice_reels.py      # Lettie's lines
$U --with playwright==1.63.0 --with imageio-ffmpeg --with numpy --with scipy python marketing/social/reels/render_reels.py [id ...]
```

Words and timing are in the `VIDEOS` table of `reels/reels.js`; they reuse the promo's filmed clips, music and voice.

## Interactive reels and quiz

Four more reels that ask the viewer to comment, tag, vote or finish a sentence (`pick-a-letter`, `scratch-tag`, `this-or-that`,
`finish-line`) and a browser quiz. Captions, reply bank, 7-day plan: `marketing/interactive/INTERACTIVE_CAMPAIGN.md`.
