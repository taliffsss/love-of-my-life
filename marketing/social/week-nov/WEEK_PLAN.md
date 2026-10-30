# Week of 31 October – 6 November 2026: five occasions, five mascots

One picture and one narrated video for each of the product's five occasions (the same five the app offers when you make a page):
**Birthday, Invitation, Wedding, Anniversary, Boyfriend / Girlfriend.** In every piece the narrator is Lettie, as that
occasion's own mascot. Built by the files in this folder; captions are edited **here**, then `collect.py` copies them next to the
pictures and videos in `marketing/out/social/week-nov/post-now/`.

Written for: whoever posts to the Facebook Page (Reels and TikTok can reuse the same 9:16 videos). No personalization: nothing here is
about a named person, and the sample pages show made-up names.

## The mascot, per occasion

A different mascot for every occasion, all named **Lettie** (so the name is what people remember). Each one shares Lettie's face (big
shiny eyes, rosy cheeks, the same smile), her pink-and-gold palette and her way of moving, but has its own body. The default envelope
Lettie (`promo/lettie.svg`) and the 3D model are untouched. Rebuild: `looks.py`.

| Occasion | Lettie as... (`looks/lettie-<id>.svg`) | Style |
|---|---|---|
| Birthday | `birthday`: a cupcake | cream frosting swirl with sprinkles, pink wrapper, one lit candle, confetti |
| Invitation | `invitation`: a scroll | unrolled parchment tied with a red ribbon and a gold heart seal, "You're invited" |
| Wedding | `wedding`: a wedding bell | white bell with a gold rim, veil, flower crown, a gold ring and a bouquet |
| Anniversary | `anniversary`: a champagne bottle | gold bottle in a black bow tie and a "Cheers!" label, bubbles, gold sparkles |
| Boyfriend / Girlfriend | `partner`: a plush heart | red stitched plush heart in a cosy pink hoodie with strings and cuffs |

`marketing/out/social/week-nov/lettie-outfits-sheet.png` shows all five side by side.

## Calendar (PHT; times are guesses to test, as in `../CONTENT_PLAN.md`)

Every day has something. Each occasion gets its picture and its video; the occasions that fall around the quiet days of 1 and 2 November
(All Saints' and All Souls', when families visit cemeteries) are split over two days, one calm piece each day, in the gentlest tone.

| Day | Occasion | Picture | Video |
|---|---|---|---|
| Sat 31 Oct | Birthday | 12:00 | 19:00 |
| Sun 1 Nov | Invitation | 12:00 | – |
| Mon 2 Nov | Invitation | – | 19:00 |
| Tue 3 Nov | Wedding | 12:00 | 19:00 |
| Wed 4 Nov | Anniversary | 12:00 | 19:00 |
| Thu 5 Nov | Boyfriend / Girlfriend | 12:00 | – |
| Fri 6 Nov | Boyfriend / Girlfriend | – | 19:00 (Friday date night) |

The Halloween ads end at midnight on 31 Oct, so the Birthday pair is the only new thing that day besides the Halloween Story.

## Honesty rules for this week (same as the rest of the kit)

- No testimonials, no "X couples already", no customer numbers: there are none yet. The phones show **sample pages** with made-up names.
- **Start free** only with the plan note beside it: building and previewing are free; **sending needs a plan**. The templates used here:
  Invitation (envelope, *Wedding Invitation*) is on Sweet, Sweetheart and Forever; **Birthday (gift, *Surprise Box*), Wedding (storybook,
  *Fairytale*), Anniversary (stars, *Written in the Stars*) and Boyfriend / Girlfriend (scratch, *Will You Be Mine*) are Sweetheart or Forever.**
  Evidence: `config/saas.php` (Sweet = classic, cinematic, polaroid, envelope, timeline) and `Designs::OCCASION_DESIGNS`.
- No RSVP, guest list or reply counts (the app does not collect replies), no "costs less", no own-address (`name.loveofmylife.love`), no prices
  in the captions, no giveaways, no "how to pay".
- The narration is a computer voice (Kokoro, generated locally). If Meta's upload shows an "AI info" option, switch it on.

## Before you post (checked from here, not confirmed in production)

1. https://loveofmylife.love answers 200 and `/invitations` answers 200, but I did **not** confirm that the deployed build offers the
   Birthday, Wedding, Anniversary and Boyfriend/Girlfriend templates named above. Sign in, start a page, pick each occasion and look for
   the template names in the table before the first post.
2. The five phones show the real demo page dressed as each occasion with the app's own `occasions.css` (its ornaments and typeface). The
   app's `/demo` only offers `?occasion=invitation`, so for the other four the occasion was applied by the filming script (`footage.py`).
3. Nobody has watched the videos or looked at the pictures yet: they passed layout, loudness, black/frozen-frame and duration checks only.
   Skim all ten before scheduling.

## Ready-to-post pictures and videos

Each section: the files it uses (in backticks) and the caption to paste. `>` lines are the text. `{link:...}` becomes a tagged link.

### Sat 31 Oct, noon, Birthday picture: `wk-birthday.png`
> Make their day unforgettable. 🎂💌
>
> Skip the plain "happy birthday" text. Build a surprise box that opens on your photos and your birthday wishes, then share it as a link.
>
> Free to build and preview. The surprise-box template comes with Sweetheart or Forever. → {link:wk-birthday-img}
>
> Birthday na ba ng mahal mo? Gawan mo siya ng surprise box na bubukas sa photos at wishes mo 🎈
>
> Tag someone with a birthday coming up 👇
> #happybirthday #birthdayideas #birthdayph #loveletter #LoveOfMyLife

Alt text: Lettie, a smiling cupcake with cream frosting, sprinkles and one lit candle, stands beside a phone showing a gift box opening.

### Sat 31 Oct, 7 pm, Birthday video: `wk-birthday.mp4`
> Happy birthday! 🎉 Lettie the cupcake says skip the plain text and open a surprise box instead.
>
> Your photos and your wishes, inside a box that opens when they tap it. Free to build and preview (Sweetheart or Forever to send). → {link:wk-birthday-reel}
>
> Happy birthday! Sa halip na simpleng text, bigyan mo sila ng surprise box na may photos at wishes mo 🎁
> #happybirthday #birthdayph #surprise #LoveOfMyLife

### Sun 1 Nov, noon, Invitation picture: `wk-invitation.png`
> You're invited. 💌
>
> Families are together this weekend. 🤍 Planning a gathering, a debut, a wedding or a christening? Here is an invitation with no printing: pick a template, add your photos and the details, then share a link or print the QR code on a card.
> Wrong time? New venue? Edit it. The link and the QR code stay the same.
>
> Free to build and preview. Send it with a plan. → {link:wk-invitation-img}
>
> Imbitasyon na walang printing: ilagay ang photos at detalye, tapos i-share ang link o i-print ang QR code sa card. Nagbago ang oras o venue? I-edit lang, pareho pa rin ang link at QR 💌
>
> Planning a wedding, debut or christening? Tag the host 👇
> #invitation #weddingph #debut #binyag #LoveOfMyLife

Alt text: Lettie, a smiling parchment scroll tied with a red ribbon and a gold heart seal that reads You're invited, stands beside a phone showing a sealed wedding invitation.

### Mon 2 Nov, 7 pm, Invitation video: `wk-invitation.mp4`
> You're invited! 💌 An invitation with no printing.
>
> Guests tap the seal and the details open. Share a link or a QR code, and edit it any time. Free to build and preview; send it with a plan. → {link:wk-invitation-reel}
>
> Tatap lang ang seal, bubukas na ang imbitasyon. I-share ang link o QR code, at pwedeng i-edit anumang oras.
> #invitation #weddingph #eventsph #LoveOfMyLife

### Tue 3 Nov, noon, Wedding picture: `wk-wedding.png`
> For the day you say "I do." 💍
>
> Tell how it happened, page by page, in a little storybook: your photos, your words, your story. Then share it with everyone who loves you.
>
> Free to build and preview. The storybook template comes with Sweetheart or Forever. → {link:wk-wedding-img}
>
> Para sa araw na sasabihin ninyong "I do." Ikuwento kung paano nagsimula, pahina por pahina, at i-share sa lahat ng nagmamahal sa inyo 🤍
>
> Tag the couple who would love this 👇
> #wedding #weddingph #weddingstory #lovestory #LoveOfMyLife

Alt text: Lettie, a smiling white wedding bell in a veil and flower crown holding a small bouquet, stands beside a phone showing a storybook page.

### Tue 3 Nov, 7 pm, Wedding video: `wk-wedding.mp4`
> For the day you say "I do." 💍 Lettie the wedding bell rings in a storybook for your wedding.
>
> Your story, page by page, to share with the people who love you. Free to build and preview (Sweetheart or Forever to send). → {link:wk-wedding-reel}
>
> Ang kuwento ninyo, pahina por pahina, para sa lahat ng nagmamahal sa inyo 🤍
> #wedding #weddingph #lovestory #LoveOfMyLife

### Wed 4 Nov, noon, Anniversary picture: `wk-anniversary.png`
> Another year together. ✨
>
> Light up the stars, one memory in each: your photos, your song and your words, in the shape of a heart.
>
> Free to build and preview. The stars template comes with Sweetheart or Forever. → {link:wk-anniversary-img}
>
> Isa na namang taon na magkasama. Isang star para sa bawat alaala, kasama ang photos, kanta at mga salita ninyo ✨
>
> Tag the one you'd send it to 👇
> #anniversary #anniversaryph #lovestory #lovemylife #LoveOfMyLife

Alt text: Lettie, a smiling gold champagne bottle in a black bow tie with a Cheers label, stands beside a phone showing a sky of stars.

### Wed 4 Nov, 7 pm, Anniversary video: `wk-anniversary.mp4`
> Another year together? Celebrate it properly. ✨ Lettie the champagne bottle pops in to say cheers.
>
> Light up the stars, one memory in each. Free to build and preview (Sweetheart or Forever to send). → {link:wk-anniversary-reel}
>
> Anniversary na ba? Isang star para sa bawat alaala ninyo ✨
> #anniversary #anniversaryph #LoveOfMyLife

### Thu 5 Nov, noon, Boyfriend / Girlfriend picture: `wk-partner.png`
> Sweet on someone? 💗
>
> Hide your message under a scratch card. They scratch, they smile, they read what you have been too shy to say.
>
> Free to build and preview. The scratch-card template comes with Sweetheart or Forever. → {link:wk-partner-img}
>
> May gusto kang sabihin pero nahihiya ka? Itago sa scratch card ang mensahe mo. Kakamutin nila, ngingiti sila 💗
>
> Tag your person 👇
> #boyfriend #girlfriend #lovestory #couplegoals #LoveOfMyLife

Alt text: Lettie, a smiling red plush heart in a cosy pink hoodie, stands beside a phone showing a gold scratch card being scratched.

### Fri 6 Nov, 7 pm, Boyfriend / Girlfriend video: `wk-partner.mp4`
> Sweet on someone? 💗 Lettie the plush heart is in her cosiest hoodie.
>
> Hide your message under a scratch card: they scratch, they smile, they feel loved. Free to build and preview (Sweetheart or Forever to send). → {link:wk-partner-reel}
>
> Itago ang mensahe sa scratch card. Kakamutin nila, ngingiti sila, mararamdaman nilang mahal sila 💗
> #boyfriend #girlfriend #lovestory #LoveOfMyLife

## Video scripts (narrator: Lettie, Kokoro `af_heart`; captions burned in the picture and saved as `.srt`)

| Video | Narration | Template and plan |
|---|---|---|
| `wk-birthday` | Happy birthday! Skip the plain text message. / Make a surprise box that opens on your photos. / Add your photos and your birthday wishes. / Love of My Life. Build it free. | Surprise Box (gift): Sweetheart or Forever |
| `wk-invitation` | You're invited! An invitation with no printing. / Guests tap the seal, and the details open. / Share a link or a QR code. Edit it any time. / Love of My Life. Build it free. | Wedding Invitation (envelope): Sweet and up |
| `wk-wedding` | For the day you say I do. / Tell how it happened, page by page, in a storybook. / Then share it with everyone who loves you. / Love of My Life. Build it free. | Fairytale (storybook): Sweetheart or Forever |
| `wk-anniversary` | Another year together? Celebrate it properly. / Light up the stars, one memory in each. / Add your photos, your song and your words. / Love of My Life. Build it free. | Written in the Stars (stars): Sweetheart or Forever |
| `wk-partner` | Sweet on someone? Tell them properly. / Hide your message under a scratch card. / They scratch, they smile, they feel loved. / Love of My Life. Build it free. | Will You Be Mine (scratch): Sweetheart or Forever |

The end card of every video says "Free to build and preview." plus the plan its template needs.

## Rebuild (from the repository root)

```sh
U="uv run --no-project --python 3.12 --with playwright==1.63.0"
python3 marketing/social/week-nov/looks.py                                   # the five mascots
# the app on :8095 with a throw-away SQLite file (make.sh footage starts one), then:
$U python marketing/social/week-nov/footage.py --base http://127.0.0.1:8095  # five clips + stills (~3 min)
$U python marketing/social/week-nov/render_posts.py [sheet]                  # the five pictures (+ the mascot sheet)
uv run --no-project --python 3.12 --with playwright==1.63.0 --with kokoro-onnx --with soundfile \
  python marketing/social/reels/voice_reels.py wk-birthday wk-invitation wk-wedding wk-anniversary wk-partner
uv run --no-project --python 3.12 --with playwright==1.63.0 --with imageio-ffmpeg --with numpy --with scipy \
  python marketing/social/reels/render_reels.py wk-birthday wk-invitation wk-wedding wk-anniversary wk-partner   # ~10 min
python3 marketing/social/week-nov/collect.py                                 # folders to upload + LINKS.md
```
