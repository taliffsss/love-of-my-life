# Logo redesign, November 2026: "Heart Flap"

A heart with a keyhole tucked into an envelope. Chosen over a mascot mark and a wax seal (`concepts.png`).

| Path | What is in it |
|---|---|
| `kit/LOGO_GUIDE.pdf` | One-page usage guide |
| `kit/svg/` | Vector masters: horizontal, stacked, symbol, small cut, wordmark; each in colour, dark, black, white, crimson |
| `kit/png/` | The same logos as transparent PNGs |
| `kit/web/` | Drop-in files named like `public/`: logos, favicons, app icons, share image |
| `kit/app/`, `kit/social/` | 1024 px store icon, 1080 px profile picture, share image source |
| `kit/presentation/` | `board.html` and five slides with mockups |

Rebuild (needs Chrome):

```sh
pip install fonttools brotli uharfbuzz skia-pathops pillow
python3 -B build_kit.py --skill <logo-design skill folder>   # deletes and rewrites kit/
python3 -B build_guide.py
```

`build_kit.py` clears `kit/` and also rebuilds the presentation from `presentation-spec.json`; run the guide after it.

## On the site

`python3 -B ../build_brand.py` rebuilds this kit and copies it into `public/`, the store icon and
`resources/views/partials/brand.naluz.php`. Change the drawing in `build_kit.py`, then run that. After a CSS change run
`node bin/build-assets.mjs`.

Open: trademark and reverse-image search; Pantone match if anything is printed in spot colour; social pictures rendered
before the change still carry the old logo until `marketing/make.sh images` is run again.
