# Page setup: Facebook Page and TikTok

Everything to paste into the two profiles. Images are in `marketing/out/social/` (open `index.html` there to browse).
Lengths are checked against the platforms' limits as of writing; the platforms change them, so confirm in the form.

## Facebook Page

| Field | Value |
|---|---|
| Page name | Love of My Life |
| Category | The closest fit your form offers (try "Internet company" or "Digital creator"); it can be changed later |
| Profile picture | `fb-profile.png` (the heart mark; shown as a circle). Alternate: `profile-lettie.png` |
| Cover photo | `fb-cover.png` (1640×624). Seasonal swap: `fb-cover-ofw.png`. Phones crop the sides and the profile picture covers the bottom left, so the words are in the middle |
| Short description (≤ 101) | Make a private love-letter page they unlock with a question only they can answer. Free to build. |
| Button | **Sign up** or **Learn more** → the landing page |
| Website | https://loveofmylife.love (see "Before you post") |
| Username/handle | Choose it in the form. Not reserved or checked here |

**About (long)**

> Love of My Life helps you say what you feel, properly. Put your photos, your song and a letter on one private page,
> lock it with a question only they can answer, and send the link or a QR code. For monthsaries, anniversaries,
> birthdays, weddings and invitations, near or far, and for OFWs and long-distance couples especially.
> Build and preview it free. Plans from ₱200 a month.

**Pinned post:** the `live.png` post (or `soon.png` before launch) with the launch caption in `CAPTIONS.md`.
**Featured/Services tab (optional):** the five occasions (Boyfriend/Girlfriend, Anniversary, Wedding, Birthday,
Invitation) and the three plans, with the `svc-*` and `plan-*` images.

**Auto-reply to a first message (Messenger)**

> Hi! 💌 I'm Lettie, Love of My Life's little love letter. You can build your page free here: loveofmylife.love.
> Tell us the occasion and we'll point you to the right template.

**Reply bank (comments and DMs)**

| They ask | Reply |
|---|---|
| "How much?" | "You can build and preview it free. When you're ready to send it, plans start at ₱200 a month; there's also ₱350 a month with all 10 templates and your own address, or ₱4,999 once for good." |
| "Is it private?" | "Yes. Only people with the link can open it, and if you set a question, only someone who knows the answer. The photos and letter aren't even sent to the browser until the right answer is typed." |
| "Can I edit it after I send it?" | "Yes, any time. The link and QR code stay the same." |
| "Can I use it for a wedding/birthday invite?" | "Yes! There are designs for weddings, birthdays, debuts, christenings, baby showers and more." |
| "I'm abroad, can my partner open it?" | "Yes, it's just a link, so it opens on any phone, anywhere." |
| "How do I pay?" | Use whatever the live checkout is at the time (see "Before you post"); never promise a method that isn't switched on. |

## TikTok

| Field | Value |
|---|---|
| Name | Love of My Life |
| Profile picture | `tt-profile.png` (circle crop; the mark has no words so it stays readable small) |
| Bio (≤ 80) | Love letters they unlock 💌 Your photos, song & words. Free to build ⬇️ |
| Link | Needs a business account or the follower threshold TikTok sets; use the landing page |
| Category | Business account → pick the closest category |

Switch to a **Business account** if you want the link early; it is free, but its commercial-music library is
restricted: use the app's Commercial Music Library or original sound for videos (see `TIKTOK_SCRIPTS.md`).

## Before you post (blockers outside this kit)

1. **The domain must be registered and resolve.** `loveofmylife.love` is the domain on every image, caption and the video's
   QR code. On 2026-10-18 the research showed it unregistered, so register it, point DNS at the server, set
   `APP_URL`/`SAAS_DOMAIN` to it and deploy (`DEPLOY.md`), then confirm it opens on a phone before the first post. To
   change the domain again, edit `URL_TEXT` in `social/social.js` and `cards/cards.js`, the strings in `promo/promo.js`
   and `promo/make_promo.sh`, and re-render.
2. **Checkout wording.** The kit never says how to pay. Say "GCash, Maya or card" only once the PayMongo keys are set and
   checkout is live in production; until then the dashboard asks people to pay by GCash/Maya and confirm by reply.
3. **Own address** (`yourname.loveofmylife.love`) needs the wildcard DNS and certificate (`DEPLOY.md`); Sweetheart
   and Forever advertise it, so don't post those cards before it works.
4. **Age.** The audience includes 16–17-year-olds. Paying is for adults (a minor can't hold the payment methods), and
   Meta/TikTok limit advertising to under-18s. Keep teen posts organic and wholesome (they are), don't target ads
   below 18, and consider an age line in the terms. This is a decision for you; nothing here was changed in the app.
5. **Trademark.** `brand/LETTIE.md` already says to search IPOPHL for "Lettie" before paid campaigns or merchandise.
