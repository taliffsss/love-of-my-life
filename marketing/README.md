# Marketing

Promo video, mascot, social cards, the Facebook and TikTok kit, copy and the pricing proposal for Love of My Life.
None of this is part of the app; `.dockerignore` keeps it out of the image. Everything rendered goes to `marketing/out/`
(git-ignored), so it can always be rebuilt from these files.

| Folder / file | What it is |
|---|---|
| `promo/` | The 37 s promo video narrated by Lettie (16:9 and 9:16) |
| `cards/` | Social cards and Lettie stickers |
| `social/` | The Facebook and TikTok kit: profile pictures, covers, 56 post images, captions, plan, TikTok scripts |
| `social/reels/` | Five ~15 s Facebook/Reels videos |
| `social/build_post_now.py` | Collects the 10 ready-to-upload Facebook posts into folders |
| `brand/` | `LETTIE.md` (the mascot) and `build_brand.py` (logo and icons) |
| `copy/SOCIAL.md` | Earlier posts, ad copy, hashtags, two-week plan, honesty rules |
| `PRICING.md` | The plans and prices, and why |

## Before you start (once)

- **`uv`** (runs every Python script below with its own dependencies; nothing is installed globally) and **PHP with
  the project's `vendor/`** (the QR code).
- **An internet connection:** Google Fonts load on every render; the first voice run downloads the Kokoro voice model
  (~350 MB, checked against a SHA-256) to `~/.cache/dink-promo/kokoro`.
- **Playwright's Chromium** in `~/Library/Caches/ms-playwright` (Playwright installs it if missing).
- Run everything **from the repository root** (`love-of-my-life/`).
- `U="uv run --no-project --python 3.12"` is used below as shorthand; paste that line into your shell first.

```sh
U="uv run --no-project --python 3.12"
```

## Build order (each step needs the one before it)

| # | Step | Command | Time | Output (`marketing/out/…`) |
|---|---|---|---|---|
| 1 | Promo video: film the app, speak, render | `marketing/promo/make_promo.sh` | ~20 min | `promo/*.mp4`, `promo/shots/` (clips and stills used by every later step), `promo/qr.svg` |
| 2 | Social cards and Lettie stickers | `$U --with playwright==1.63.0 python marketing/cards/render_cards.py` | ~1 min | `cards/*.png` |
| 3 | Facebook/TikTok images (56) | `$U --with playwright==1.63.0 python marketing/social/render_social.py` | ~2 min | `social/*.png`, `social/index.html` (contact sheet) |
| 4 | Lettie's voice for the 5 videos | `$U --with playwright==1.63.0 --with kokoro-onnx --with soundfile python marketing/social/reels/voice_reels.py` | ~1 min | `social/reels/voice/` |
| 5 | The 5 Facebook videos | `$U --with playwright==1.63.0 --with imageio-ffmpeg --with numpy --with scipy python marketing/social/reels/render_reels.py` | ~2 min each | `social/reels/*.mp4`, `.srt`, `-poster.png` |
| 6 | Ready-to-upload posts | `python3 marketing/social/build_post_now.py` | seconds | `social/post-now/01-…/` to `10-…/` |

Step 1 is only needed once, or when the app's look changes; steps 2–5 re-use its `shots/`.

### Quick rebuilds

```sh
marketing/promo/make_promo.sh render        # promo: re-render only, re-using the clips and the voice (~9 min)
PROMO_VOICE=af_bella marketing/promo/make_promo.sh   # promo: another narrator (af_heart by default)

# one image or card instead of all of them
$U --with playwright==1.63.0 python marketing/social/render_social.py fb-cover plan-sweet
$U --with playwright==1.63.0 python marketing/cards/render_cards.py feed-hook

# one video
$U --with playwright==1.63.0 --with imageio-ffmpeg --with numpy --with scipy python marketing/social/reels/render_reels.py ofw-unlock
# (video ids: ofw-unlock, scratch-ask, monthsary-book, stars-wish, invite-gift)
```

## Where to change things

| To change | Edit | Then re-run |
|---|---|---|
| **The domain** (shown in videos, cards, QR) | `URL_TEXT` in `social/social.js`, `social/reels/reels.js` and `cards/cards.js`; `url` and `previewHost` in `promo/promo.js`; the URL in `promo/make_promo.sh`; text in the `.md` files | steps 1 (`render`), 2, 3, 5, 6 |
| **A price** | `config/saas.php` first, then the cards in `social/social.js` and the copy. `render_social.py` stops with the list of cards to fix if a price no longer matches | step 3 (and 6) |
| **Words on a social image** | `CARDS` in `social/social.js` (or `cards/cards.js` for the older cards) | step 3 (or 2) |
| **A video's words, timing or Lettie's lines** | `VIDEOS` in `social/reels/reels.js` (lines are `[text, from, until]`; a line that doesn't fit its window stops step 4 with a message) | steps 4, 5, 6 |
| **The promo's words / timing / lines** | `COPY`, `SCENES`, `CLIPS` in `promo/promo.js`; `promo/voiceover.json` | step 1 (`render`; run the full script if the lines changed) |
| **A Facebook caption** | `social/FACEBOOK_POSTS.md` | step 6 |
| **A new image** | Add an entry to `CARDS` in `social/social.js` (use `service`, `statement`, `poll`, `price`, `steps` or `slide` helpers) | step 3 |
| **A new video** | Add an entry to `VIDEOS` in `social/reels/reels.js` and its id to `IDS` in `social/reels/common.py` | steps 4, 5 |
| **Lettie** | `promo/lettie.svg`, `brand/LETTIE.md` | steps 1–3, 5 |
| **Logo and icons** | `brand/build_brand.py` | (read by step 3's profile pictures) |

## What the builds check for you

- **Images:** before a picture is saved, nothing may stick out of it and no words may overlap each other, a phone or
  Lettie. TikTok slides keep words inside the safe zone.
- **Prices:** `render_social.py` compares the prices on the cards with `config/saas.php`.
- **Videos:** the text layout is audited every half second; narration must sit at least 6 dB above the music; the encoded
  file is checked for length, resolution, 30 fps, a stereo AAC track, black or frozen stretches and loudness (-14 LUFS).
- None of this replaces watching the result once before posting.

## How the pieces work

- **The app in the videos** is filmed from its public `/demo` page, in slow motion, on a throw-away SQLite database
  (`promo/shots.py`); it never touches your database or storage bucket.
- **Voice:** Kokoro-82M runs locally (Apache-2.0); nothing is sent anywhere. **Music and sounds** are synthesised in
  `promo/music.py`, so there is nothing to license.
- **The photos** in the videos and cards are illustrations from `promo/photos/`, swapped in for the demo's photos.
- Every video frame is drawn by a page that moves only when told the time (`seek(t)`), so a render is identical every run.

## Using the output

- Start with `out/social/post-now/`: one folder per Facebook post, with the images or video and `CAPTION-copy-paste.txt`.
- `out/social/index.html` shows every image; `social/PAGE_SETUP.md` has the profile text and the checklist to read
  before the first post; `social/CONTENT_PLAN.md` and `social/CAPTIONS.md` have the 30-day plan and the captions.
- Always check the domain opens on a phone before posting a link.
