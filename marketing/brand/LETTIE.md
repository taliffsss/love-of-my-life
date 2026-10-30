# Lettie, the little love letter

Lettie is the mascot of Love of My Life and the narrator of its promo video. She is a love letter that came to life:
a cream-coloured envelope with a pink heart seal, rosy cheeks, big shiny eyes and a curl on top that ends in a tiny heart.

- **3D:** she is a real 3D model ([`resources/js/lettie3d.src.js`](../../resources/js/lettie3d.src.js), bundled to `public/assets/lettie3d.js`). It runs live on the sign-in and sign-up pages (she follows the pointer, blinks, talks while the letter types, hugs a heart when clicked) and is rendered to stickers with `marketing/make.sh lettie3d`: `out/cards/lettie3d-<look>-<hello|idle|love>.png`, transparent, 1024 px.
- **Master artwork (flat, also the fallback when WebGL is off):** [`marketing/promo/lettie.svg`](../promo/lettie.svg), a vector you can scale to any size.
- **Stickers:** transparent PNGs, 1024 px, in `marketing/out/cards/`: `lettie-hello.png` (waving and talking),
  `lettie-idle.png` (listening) and `lettie-love.png` (hugging a big heart, eyes closed and smiling).
- **Character sheet:** `lettie-sheet.png` shows all three poses.
- **Rebuilding:** `marketing/cards/render_cards.py` re-renders all of these.

## Who she is

- **Personality:** warm, sweet, a little shy, always rooting for you. She's the friend who helps you say what you feel
  and never teases you for being cheesy.
- **Her job:** she carries your words to the one you love. In the product's story she *is* the letter that gets opened.
- **Voice:** gentle, smiling, unhurried; short sentences, no slang that dates quickly, never sarcastic.
  - She says: "Hi! I'm Lettie, your little love letter." "Got something sweet to say?" "Watch them smile."
  - She never says: anything pushy ("Buy now!!"), anything that mocks being single, or anything about the other person
    that the customer didn't write.
- **Narration voice:** Kokoro `af_heart`, a warm, friendly female voice generated locally (Apache-2.0). It's set in
  `marketing/promo/voice.py`; `PROMO_VOICE` tries another voice.

## Look

| Part | Colour |
|---|---|
| Body (paper) | `#fffaf3` to `#fde1df` |
| Outline | `#eab4c0` |
| Heart seal | `#f58fa8` to `#e0607e` to `#b8405f` |
| Cheeks | `#f7a1b5` at 60% |
| Eyes and mouth | `#3a1f2b` |

She lives on the product's Rose theme background (`#160a14` to `#2a1220`), with the accent `#f2a7b8` and the gold
`#f0cfa8`. On light backgrounds keep her outline; on photos give her a soft shadow.

**Do:**
- Let her wave, point at the phone, hop when something opens, and hug a heart at the end.
- Keep her round and soft. Animate her with gentle bounces on the beat.

**Her four looks.** She changes with the season, the same windows as the site (`Seasons::theme()`: 14 days before the day). Her body and face never change; her clothes, seal colour and the little things floating around her do:

| Look | When | What she wears |
|---|---|---|
| Default | the rest of the year | nothing extra; rose seal; floating pink hearts |
| Halloween | until 31 Oct | purple witch hat with an orange band; orange seal; glowing embers |
| Christmas | until 25 Dec | red Santa hat with a pom-pom; red seal; snowflakes |
| Valentine | until 14 Feb | gold halo and small white wings; deep-pink seal; bigger hearts |

**Invitation looks** (added 2026-10-28, for the invitation campaign in [`../invitation/`](../invitation/)). Not seasonal: they are used whenever the message is about invitations. Same body and face; the seal turns gold, she holds an invitation card with a real QR code, and the hat says the occasion:

| Look | File | Hat |
|---|---|---|
| Invitation | `invitation/lettie-invite.svg` | none (gold seal, card, confetti) |
| Wedding | `invitation/lettie-invite-wedding.svg` | sheer veil, gold ring and gem |
| Debut and birthday | `invitation/lettie-invite-birthday.svg` | striped party hat |
| Christening and baby shower | `invitation/lettie-invite-christening.svg` | frilled bonnet, pale blue bow |

Built by `invitation/build_mascot.py` from the master `promo/lettie.svg`; stickers are in `out/invitation/cards/`. The default Lettie is unchanged and still the site's mascot; the 3D model has no invitation look yet.

**Occasion mascots** (added 2026-10-30, week of 31 Oct - 6 Nov, [`../social/week-nov/`](../social/week-nov/)). By the owner's decision these are different mascots that keep the name Lettie: a cupcake (`birthday`), a scroll (`invitation`), a wedding bell (`wedding`), a champagne bottle (`anniversary`) and a plush heart in a hoodie (`partner`). They share her face, pink-and-gold palette and animation parts, and each file says where its shoulders are (`data-arm-l/-r`). Built by `social/week-nov/looks.py` into `social/week-nov/looks/lettie-<id>.svg`; the envelope Lettie above stays the product's mascot.

**Talking clips.** `marketing/brand/build_clips.py <campaign folder>` builds vertical clips from a storyboard and its narration (her mouth follows the voice, rising hearts, real app footage in a phone frame). `halloween-clips.json` holds the scripts of three Halloween clips; their claims are the ones approved in the mascot-marketing campaign.

To rebuild after editing the model: `npm i --no-save three esbuild`, then `npx esbuild resources/js/lettie3d.src.js --bundle --minify --format=iife --target=es2019 --outfile=public/assets/lettie3d.js`.

**Don't:**
- Stretch her or recolour her body.
- Give her other characters' clothes, put her in romantic scenes with another character, or use her for anything but
  Love of My Life.

## How she moves (promo.js)

- **Bobbing:** a small bob on every beat of the 88 BPM soundtrack, and a little squash and stretch.
- **Hops:** a hop when each template clip starts.
- **Blinking:** about every three seconds.
- **Talking:** her mouth opens with her voice; the loudness of the narration drives it frame by frame.

## Before using her widely

The name and design are original to this project. Run a quick trademark search (IPOPHL) for "Lettie" in the
gift and online-services classes before printing merchandise or paying for big campaigns. Treat the name as a working
name until then.
