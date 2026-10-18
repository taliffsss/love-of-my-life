# Marketing

Promo video, mascot, social cards, copy and the pricing proposal for Love of My Life. None of this is part of the
app; `.dockerignore` keeps it out of the image. Rendered files go to `marketing/out/` (git-ignored).

| | |
|---|---|
| `promo/` | the promo video, narrated by Lettie: `make_promo.sh` films the real app, speaks the lines and renders 16:9 and 9:16 MP4s |
| `cards/` | social cards and Lettie stickers: `render_cards.py` (needs the promo's shots) |
| `brand/LETTIE.md` | the mascot: who she is, colours, poses, do's and don'ts |
| `copy/SOCIAL.md` | posts, ad copy, hashtags, a two-week plan and the honesty rules |
| `PRICING.md` | the proposal for monthly plans (not built yet) |

## Rebuild everything

```sh
marketing/promo/make_promo.sh            # ~20 min: film the app, voice, render both videos into marketing/out/promo/
marketing/promo/make_promo.sh render     # re-render only (after changing promo.js, music.py or the lines)
uv run --no-project --python 3.12 --with playwright==1.63.0 python marketing/cards/render_cards.py   # cards -> marketing/out/cards/
```

**What you need:**
- `uv`, and PHP with the project's `vendor/`.
- An internet connection: the first run downloads the voice model to `~/.cache/dink-promo/kokoro` (it is checked
  against its SHA-256), and Google Fonts load on every run.
- Playwright's Chromium in `~/Library/Caches/ms-playwright`; Playwright installs it if it's missing.

**What the build does:**
- **The app:** it films the app as it ships, from its public `/demo` page on a throw-away SQLite database. It never
  touches your database or your Spaces bucket.
- **The voice:** Kokoro-82M, which runs locally (Apache-2.0); nothing is sent anywhere.
- **The music and sounds:** synthesised in `promo/music.py`, so there's nothing to license.
- **The photos:** illustrations in `promo/photos/`, swapped in for the demo's photos during filming.

**Changing things:**
- **Words:** `COPY` in `promo/promo.js` and `cards/cards.js`.
- **Lettie's lines:** `promo/voiceover.json`.
- **Timing:** `SCENES` and `CLIPS` in `promo/promo.js`.
- **Checks:** the render refuses words outside the safe area or on top of the phone, Lettie or other words. Afterwards it
  checks the files: length, streams, black or frozen stretches, and loudness at -14 LUFS.
