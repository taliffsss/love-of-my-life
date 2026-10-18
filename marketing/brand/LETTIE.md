# Lettie, the little love letter

Lettie is the mascot of Love of My Life and the narrator of its promo video. She is a love letter that came to life:
a cream-coloured envelope with a pink heart seal, rosy cheeks, big shiny eyes and a curl on top that ends in a tiny heart.

- **Master artwork:** [`marketing/promo/lettie.svg`](../promo/lettie.svg), a vector you can scale to any size.
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
