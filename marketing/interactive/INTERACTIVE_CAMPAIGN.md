# Interactive campaign: reels and posts people answer

Four 15-second reels and one quiz. Each asks the viewer to do one small thing (pick a number, tag someone, vote, finish a
sentence) so the algorithm sees comments and shares, not just views. Voice: Lettie. English first, then Taglish.

**Links:** `{link:ix-…}` in the captions below stands for the tagged link; `python3 marketing/social/utm.py interactive-2026` prints the tagged link for every placement below (`ix-*`).
Campaign name: `interactive-2026`.

**Honesty rules (same as `social/CONTENT_PLAN.md`):** no giveaways or "winners" (needs prize and rules first); no fake
numbers or testimonials; "free" means free to build and preview; no prices unless you add them from `config/saas.php`.
**The one promise these reels make:** "I'll write you back" / "pick one" means a person replies. Plan 20 minutes a day
for replies during the first 24 h of each post, or do not post the pick-a-letter reel.

## The four reels (`marketing/out/social/reels/`, 1080×1920, 15.4 s, voiced by Lettie, captions burned in, `.en_US.srt` beside each)

| File | What happens on screen | Viewer's action | Post |
|---|---|---|---|
| `pick-a-letter.mp4` | Three sealed envelopes, a finger taps each one | Comment 1, 2 or 3 | Day 1, 19:00 |
| `scratch-tag.mp4` | A gold card is scratched off to reveal "Tag the person you'd send it to" | Tag someone | Day 3, 20:00 |
| `this-or-that.mp4` | Four quick rounds: envelope or scratch card, stars or storybook, song or photos, midnight or morning | Comment your four, e.g. "1, 2, 1, 2" | Day 5, 12:30 |
| `finish-line.mp4` | "Our song is…" is typed out with three example endings | Finish the sentence in the comments | Day 7, 19:00 |

### Captions

**pick-a-letter**
> Pick a letter. Any letter. 💌
> Comment 1, 2 or 3 and Lettie will write you back.
> 1 = a promise · 2 = a memory · 3 = a surprise
> Want to send a real one to someone? Build it free → {link:ix-pick}
>
> Pumili ng sulat 💌 I-comment ang 1, 2 o 3 at susulatan ka ni Lettie. Gumawa ng totoong sulat para sa kanya: libre gawin at i-preview.
> #LoveOfMyLife #loveletter #monthsary

**scratch-tag**
> Scratch to reveal who's getting your next love letter. 🎟️
> Tag them below 👇 (they'll wonder what it's about)
> Make it for them tonight, free to build → {link:ix-scratch}
>
> I-scratch para malaman kung sino ang susulatan mo. I-tag mo na siya 👇
> #LoveOfMyLife #tagsomeone #giftideasph

**this-or-that**
> This or that: love letter edition. ✨
> 💌 Envelope or 🎟️ scratch card · ✨ stars or 📖 storybook · 🎵 our song or 📸 our photos · 🌙 midnight or ☀️ morning
> Comment your four like this: 1, 2, 1, 2. We'll tell you what your answers say about you.
> All of these are real ways to open a letter on Love of My Life → {link:ix-thisthat}
>
> Alin ang pipiliin mo? I-comment ang apat mong sagot, tulad ng 1, 2, 1, 2.
> #thisorthat #LoveOfMyLife #couplegoals

**finish-line**
> "Our song is ________." 🎵
> Finish the sentence in the comments. Lettie reads every one.
> Then put your song on a page only they can open → {link:ix-fill}
>
> Tapusin ang sentence: "Ang kanta namin ay ________." 🥹
> #ourSong #LoveOfMyLife #kilig

**Page is not shown in these reels,** so the end card says "Free to build and preview." (not "Sample page shown").

### Reply bank (Lettie answers each comment; copy, then change one word so replies do not look automated)

*pick-a-letter, comment 1 "a promise":* "Here's yours: I promise to choose you on the busy days too. 💌 Send this to someone? Tag them 👇"
*2 "a memory":* "Here's yours: remember the last time you laughed so hard you forgot the joke? Keep that one close. 💌"
*3 "a surprise":* "Here's yours: someone is thinking about you right now. Tell them first. 💌"
*Taglish:* 1 "Pangako: pipiliin kita kahit pagod na tayo pareho 💌" · 2 "Alaala: yung tawang hindi mo na maalala kung bakit 🥹" · 3 "Sorpresa: may nag-iisip sa'yo ngayon. Sabihin mo muna 💌"
*this-or-that:* mostly 1s → "Romantic at heart: break-the-seal energy 💌" · mostly 2s → "Playful: scratch-card energy 🎟️". Keep it light; never rank people.
*finish-line:* answer with a heart and one line from the commenter's own words; never reuse a stranger's answer elsewhere without asking.

## The quiz: "How should they open your love letter?"

`marketing/interactive/quiz.html`. Five questions, four results (Sealed Envelope, Scratch Card, Storybook, Night Sky: the
same four templates the product ships). The result page has a **Save my result** button that draws a 4:5 share card in the
browser (nothing uploaded, nothing stored) and a button to make their own letter, tagged `utm_content=quiz-<result>`.
A tie goes to the last answer.

- **Post it:** the quiz must be on a public URL first. It is a single file with no dependencies except Google Fonts, so any
  static host works; the simplest is to copy it to `public/quiz.html` of the app (a decision for you, see "Open" below).
- **Facebook post** (link post, 4:5 `feed-hook.png` or the poster of any reel as the image):
  > Which way should they open YOUR love letter? 💌🎟️📖✨ 30-second quiz → {link:ix-quiz}
  > Comment your result and tag the person who'd get it.
  >
  > Alin ka? Sobre, scratch card, storybook o mga bituin? Sagutan sa 30 segundo, tapos i-comment ang result mo.
- **Story:** add the quiz link sticker, text "Which one are you?", and ask friends to reply with their result.

## Story stickers (Facebook/Instagram Stories, if your account shows the sticker)

1. Poll: "Envelope or scratch card?" with the poster of `this-or-that` behind it.
2. Question box: "What would your love letter say?" Reply to a few with a Lettie sticker (`marketing/out/cards/lettie-*.png`); ask permission before sharing anyone's answer.
3. Quiz sticker: "Which template has a heart seal?" (answer: Envelope).
4. Emoji slider: "How kilig is monthsary week?"

## Seven days

| Day | Post | Check |
|---|---|---|
| 1 | `pick-a-letter` reel + Story poll | reply to every comment within a few hours |
| 2 | Quiz link post | |
| 3 | `scratch-tag` reel | thank the first taggers; do not DM the tagged people |
| 4 | Story question box | |
| 5 | `this-or-that` reel | post the tally as a Story (counts you actually have) |
| 6 | Quiz result card of the day (any, from the quiz's Save button) | |
| 7 | `finish-line` reel | pick 3 of the answers (with permission) for next week's post |

Compare comments per reel, shares, and `/admin/traffic` visits for each `utm_content` after the week. Keep the format that
brought comments **and** visits; drop the other.

## Rebuild

```sh
marketing/make.sh reels pick-a-letter scratch-tag this-or-that finish-line   # voice + video
# or just the video, after changing words in social/reels/reels.js (INTERACTIVE table):
uv run --no-project --python 3.12 --with playwright==1.63.0 --with imageio-ffmpeg --with numpy --with scipy python marketing/social/reels/render_reels.py pick-a-letter
```
The renderer stops if any word leaves the safe area or overlaps another; scenes are in `SCENES` in `reels.js`.

## Open (yours to decide)

- Where the quiz is hosted (suggested: `public/quiz.html`, then the link in the posts becomes `https://loveofmylife.love/quiz.html`).
- The site must be live at `loveofmylife.love` and the checkout wording settled before the first post (`social/PAGE_SETUP.md`, "Before you post").
- Nobody has watched the four videos yet; checks so far were the layout audit, encoding checks, and pixel sampling of the scratch reveal .

## Video + image pairs (ready-to-upload: `marketing/out/social/post-interactive/`, built by `social/build_interactive_posts.py`)

Facebook cannot mix a video and photos inside one organic post, so every day is a **pair**: the reel (folder `A-reel`) and, a few
hours later, a photo post or carousel (folder `B-photo-post`, 1080×1350) that asks the same question another way. Both use the
caption of that day. Both carry the same tagged link, so Admin → Traffic counts the pair together.

| Day | A: reel | B: photo post | Extra |
|---|---|---|---|
| 1 | `pick-a-letter` | `ix-pick` (3 envelopes) | `C-reply-cards`: three letter cards to send as comment replies |
| 2 | (quiz link post) | 5-image carousel: cover + the four results | `quiz.html` |
| 3 | `scratch-tag` | `ix-scratch-1` → `ix-scratch-2` (before and after) | |
| 5 | `this-or-that` | 5-image carousel: four rounds + "comment your four" | |
| 7 | `finish-line` | `ix-fill-1` → `ix-fill-2` (the sentence, then ideas) | |

The 15 images (`ix-*.png`) are in `marketing/out/social/`; words live in the `ix-*` entries of `social/social.js`.
