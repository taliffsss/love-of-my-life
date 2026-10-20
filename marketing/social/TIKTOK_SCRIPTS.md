# TikTok scripts

Eight videos and three carousels. Each video: 15–25 seconds, 9:16, captions on screen (many watch on mute), the hook in
the first 2 seconds. Covers are in `marketing/out/social/tt-cover-*.png`. Use the real app: record the phone screen
of a page being built or opened (the demo page `/demo` is safe; never film a real person's private page).

**Sound:** use TikTok's Commercial Music Library or your own sound; do not use popular tracks from a business account.
`marketing/promo/music.py` makes a royalty-free soundtrack if you want to export one.
**Voice:** your own, or Lettie's Kokoro voice (`marketing/promo/voice.py`). **Sample content** only, never real people.

Safe zone: keep captions between 15% and 75% of the screen height; TikTok covers the bottom and right edge.

| # | Cover | Hook (0–2 s) | Beats | End |
|---|---|---|---|---|
| 1 | `tt-cover-pov` | "POV: you open a love letter that only you can unlock" | Phone shows the lock question → type the answer → blur lifts, music starts, the letter scrolls | "Make one → link in bio" |
| 2 | `tt-cover-ofw` | "3 ways to say 'I miss you' when you're abroad" | 1 Send a letter, not a text. 2 Add the song you played on your last night. 3 Lock it with a question only they know. Show each on screen | "Free to build" |
| 3 | `tt-cover-build` | "Making my girlfriend a page" | Time-lapse: choose Boyfriend/Girlfriend → pick Secret Letter → add 3 photos → type the letter → set the question | "Now to send it 🥹" |
| 4 | `tt-cover-which` | "Envelope, scratch or stars?" | Show three templates opening, 3 seconds each, with a number on screen | "Comment your pick" |
| 5 | `tt-cover-200` | "A monthsary gift from ₱200 a month" | Show the page, then the Sweet plan card (₱200/mo, 12 photos, 5 templates) | "Build it free first" |
| 6 | `tt-cover-rate` | "Rate my love letter 1–10" | Read a sample letter over the Letter template | "Be honest 💌" |
| 7 | `tt-cover-invite` | "Scratch to see the date" | Debut/Gender Reveal/Save the Date invitation: scratch → date appears | "Invitations for every party" |
| 8 | `tt-cover-intro` | "Hi, I'm Lettie, your little love letter" | Lettie waves, shows the lock, the envelope and the stars (cut from the promo) | "Got something sweet to say?" |

**Carousels (photo mode):** post the five slides in order, caption from `CAPTIONS.md`.
`car-ofw-1…5` (OFW), `car-monthsary-1…5` (monthsary), `car-templates-1…5` (templates).

**Reply videos:** when someone asks a question ("how much?", "can I edit it?", "is it private?"), answer it with a
15-second video using the reply bank in `PAGE_SETUP.md`. These are easy wins because the comment is the hook.

**Do not:** claim a price other than those in the plan cards; film a real private page; say "free" without "to build and
preview"; use the "Sweetheart/Forever address" before the wildcard DNS works; tag minors in ads.
