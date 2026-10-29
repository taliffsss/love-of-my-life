# Invitation campaign: copy, ads and the video script

Voice: Lettie. Warm, short sentences, never pushy. English first, then Taglish; use whichever fits the audience.
Links: `{link:<placement>}` stands for the tagged link (`python3 marketing/invitation/links.py`).
Honesty rules (see `BRAND_REVIEW_INVITATIONS.md`): say *start free* only with the plan note beside it; no RSVP promises; no
"costs less" until you have compared real quotes; no testimonials or numbers; the phones in the pictures show made-up names.

**The idea in one line:** your invitation, online: your photos and your words, shared as a link or a QR code, with no printing.

## What to post where (`marketing/out/invitation/cards/`)

| File | Where |
|---|---|
| `inv-feed-hook.png` (1:1) | Facebook / Instagram feed, ad A (hook) |
| `inv-feed-cost.png` (1:1) | feed, ad B ("No printing. No reprinting.") |
| `inv-feed-steps.png` (1:1) | feed, how it works |
| `inv-feed-occasions.png` (4:5) | feed and ad C (wedding, debut, christening) |
| `inv-story-hook.png`, `inv-story-steps.png` (9:16) | Stories, story ad |
| `inv-link-preview.png` (1200×630) | `og:image` of the future `/invitations` page, link posts |
| `lettie-invite*.png` (transparent) | stickers, comment replies, profile highlights |
| `lettie-invite-sheet.png` | the mascot sheet for the team |

## Your idea, tidied (use this as the launch post)

> **English**
> Tired of printing invitations? 💌
> Make one online instead. Pick a template for your wedding, debut or christening, add your photos and your message, then share the link or print the QR code on a card.
> Less printing, less running around.
> Start free: build and preview it. Send it with a plan. → {link:fb-hook}

> **Taglish**
> Pagod ka na bang gumawa at mag-print ng imbitasyon? 💌
> Gawin mo na lang online: pumili ng template para sa kasal, debut o binyag, ilagay ang photos at mensahe niyo, tapos i-share ang link o i-print ang QR code sa card.
> Mas kaunting papel, mas kaunting takbo.
> Libre gawin at i-preview. May plan kapag ipapadala na. → {link:fb-hook}

## Hooks to test (3 independent first lines, each with its own picture)

| Hook | English | Taglish | Picture |
|---|---|---|---|
| A. Problem | Tired of printing invitations? | Pagod ka na bang mag-print ng imbitasyon? | `inv-feed-hook` |
| B. Change | Wrong time? New venue? Edit it. The link and the QR code stay the same. | Nagbago ang oras o venue? I-edit lang. Pareho pa rin ang link at QR code. | `inv-feed-cost` |
| C. Occasions | Weddings, debuts, christenings and more, in one place. | Kasal, debut, binyag at iba pa, isang lugar lang. | `inv-feed-occasions` |

## Short posts by occasion

**Wedding** (envelope: *Wedding Invitation*, Sweet plan)
> Your guests tap the seal and the invitation opens: the date, the place and your photos. Share the link, or print the QR code on a small card. → {link:ad-wedding}
>
> Tatap lang nila ang seal at bubukas na ang imbitasyon: petsa, lugar at photos niyo. I-share ang link o i-print ang QR code sa maliit na card.

**Christening** (storybook: *Christening*, needs Sweetheart)
> A little book that welcomes the baby, page by page. Share it with the family near and far. → {link:ad-christening}
>
> Isang munting libro na bumabati sa baby, pahina por pahina. I-share sa pamilya, malapit man o malayo.

**Debut and birthday parties** (classic: *Debut*, Sweet plan)
> Your photos, the date, the venue and the dress code, in one page. Change the details any time. → {link:ad-debut}
>
> Photos mo, petsa, venue at dress code, nasa isang page. Pwedeng baguhin anumang oras.

**Print less** (for people who still want paper)
> Still want a printed card? Print just the QR code. It opens the full invitation with every detail and photo.
>
> Gusto mo pa rin ng printed card? QR code lang ang i-print. Doon na mabubuksan ang buong imbitasyon.

Hashtags (3 to 5): `#LoveOfMyLife #onlineinvitation #weddinginvitation #binyag #debut`

## Meta ad copy (three ads, one idea each)

| Field | Ad 1: hook (picture `inv-feed-hook`) | Ad 2: change (`inv-feed-cost`) | Ad 3: occasions (`inv-feed-occasions`) |
|---|---|---|---|
| Primary text | Tired of printing invitations? Make one online: pick a template, add your photos and message, share the link or QR code. Start free. | Wrong time or a new venue? Edit your invitation. The link and the QR code stay the same. Start free. | Weddings, debuts, christenings and more. Pick a template, add your photos and share it. Start free. |
| Headline (≤ 40) | Invitations, made online | No printing. No reprinting. | Choose your invitation |
| Description | Free to build. Send with a plan. | Free to build. Send with a plan. | Free to build. Christening needs Sweetheart. |
| Button | Learn more | Learn more | Learn more |
| Link | `ad-wedding` (lands on `/invitations`) | `ad-wedding` | `ad-christening` |

Notes for the ads: one emoji at most; no price in the picture; the plan note ("Send with a plan") stays in the description;
no people's faces or real babies; do not target by relationship status, health or finances.

## Video script: "Invite, don't print" (20 s, 9:16, Lettie voice)

**Status: BUILT 2026-10-28 (`marketing/out/invitation/video/invite-dont-print-9x16.mp4`, 20.6 s) from this script, awaiting your approval before it is published.** Lettie bobs on the beat; her mouth is not animated (it is not lip-sync). Narration can be made free and locally with Kokoro (`af_heart`, as the earlier reels); no Runway
or ElevenLabs is needed because the scenes are the app footage and the stickers. Word counts are for about 2 to 2.5 words a second.

| # | Time | On screen | Lettie says | Words | Claim and evidence |
|---|---|---|---|---|---|
| 1 | 0–4 s | `lettie-invite-sticker` hops in on cream, headline "Tired of printing invitations?" | "Tired of printing invitations?" | 4 | none (a question) |
| 2 | 4–8 s | Wedding Invitation: the seal breaks, the envelope opens (real footage, made-up names) | "Make one online. Add your photos and your message." | 9 | photos + message: dashboard edit page |
| 3 | 8–12 s | Cuts: Christening book, Debut page (real footage), mascot swaps hats | "Weddings, debuts, christenings, and more." | 5 | invitation templates: `Designs.php:197-210` |
| 4 | 12–16 s | The Share box: link, then the QR code downloading; Lettie holds up the card | "Share the link, or print the QR code on a card." | 10 | link + printable QR: `QrCode.php`, Share section |
| 5 | 16–20 s | End card: Lettie, `loveofmylife.love`, small line "Start free. Send with a plan." | "Less printing. Start free at loveofmylife dot love." | 8 | free to build and preview: `config/saas.php` |

Taglish version: replace the lines with "Pagod ka na bang mag-print ng imbitasyon?", "Gawin mo online. Ilagay ang photos at mensahe mo.", "Kasal, debut, binyag at iba pa.", "I-share ang link, o i-print ang QR code sa card.", "Mas kaunting papel. Libre gawin sa loveofmylife dot love." Have a native Tagalog speaker read and approve it before voicing.

Captions: burn in the English or the Taglish line of each scene; keep them above y 1500 (platform buttons).
Music: the existing licensed/synthesised track in `marketing/promo/music.py`, quiet under the voice.
Footage: real screen recordings of the sample wedding invitation and of the dashboard Share box (`video/footage.py`), with made-up names. The Share box shows the "link works once published" note of an unpublished page.
