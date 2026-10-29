# Brand review: the invitation copy and Lettie's new look

Reviewed: (1) the request as you wrote it, (2) the new copy in `COPY.md`, the six cards and the mascot's invitation looks.
Against: Lettie's voice and rules (`marketing/brand/LETTIE.md`), the kit's honesty rules (`social/CONTENT_PLAN.md`), the plans in
`config/saas.php`, and what the product does today (`product-facts.json`).

## The original line

> tired of creating and printing invitation and cost much? Try loveofmylive.love it has invitation templates just add photos messages, scan qrcode and send it to the person that you want to invite less hassle cost less try now !!

## Summary

**Overall: the idea is strong and on brand; the wording needs five fixes before it can run.** The pain is real and clear, it
promises something the product does (templates, photos, messages, QR code), and it fits Lettie's job: carrying your words to
someone. **Fix first:** the link has a typo (it would go nowhere), "cost less" can't be backed up yet, and nothing says that
sending needs a plan.

## Findings

| # | Issue | Where | Severity | Suggestion |
|---|---|---|---|---|
| 1 | **Wrong address:** "loveofmyl**ive**.love". The site, the legal pages and every other asset use loveofmy**life**.love | "Try loveofmylive.love" | **High** | Use `loveofmylife.love` (all new material does). Worth checking nobody registered the typo before you print it anywhere |
| 2 | **Unsubstantiated comparison:** "cost less" (and "cost much") claims a saving over printing. Nothing in the project measures printing costs, and the plans start at ₱200 a month, so for a small print run printing may be cheaper | "cost much", "cost less" | **High** | Say what is true: "no printing". If you want a saving claim, collect 3 real printing quotes, write the worked example (cards × price vs ₱200 for the month) and show it as an example, not a promise |
| 3 | **Plan not disclosed:** building and previewing are free, but sending needs Sweet (₱200/month) or Sweetheart (₱350/month). The Wedding Invitation and Debut templates are in Sweet; **Christening and Baby Shower need Sweetheart** | whole text, and the christening look | **High** | "Start free: build and preview. Send it with a plan." in every post; in the christening post add "with Sweetheart" |
| 4 | **Inaccurate flow:** "scan qrcode and send it" reads as if the host scans a code. The host *shares the link* or *prints the QR code*; guests scan it | "scan qrcode and send it to the person" | Medium | "Share the link, or print the QR code on a card" |
| 5 | **RSVP risk:** invitations make people think of replies. The templates say "Kindly let us know by [RSVP date]" but the app does not collect, count or remind | any wording about guests replying | **High (if added)** | Do not mention RSVP, guest lists or replies. Tell hosts in the onboarding e-mail that guests reply by message |
| 6 | Tone: "try now !!" is pushy and Lettie never is; space before "!!" and two marks break the one-exclamation rule | "try now !!" | Medium | "Start free." |
| 7 | Vague promise: "less hassle" can't be checked | "less hassle" | Low | Name the thing: "less printing, less running around" is a feeling; "no printing" is a fact. Use "less printing" |
| 8 | Terminology: "invitation" (singular, no article), "qrcode" | several | Low | "invitations", "QR code" (two words, as on the site) |
| 9 | Product wording on the pages themselves: invitation pages still say "A letter for…" and "The envelope is open: a letter for…" | Wedding Invitation and Debut stills | Medium | Not a copy issue but a product one: fix it before paid ads, or crop it out of pictures (the cards avoid the line) |
| 10 | "Birthday": there is no kids' birthday invitation template; there is **Debut (18th)** and **Baby Shower**, and any page can be written as a birthday invitation | the "birthday" in your brief | Medium | Say "debut and birthday parties" only beside the Debut template, or add a Birthday Party template to the invitation group first |

## Revised sections (before → after)

**1. The whole line**
- *Before:* "tired of creating and printing invitation and cost much? Try loveofmylive.love it has invitation templates just add photos messages, scan qrcode and send it to the person that you want to invite less hassle cost less try now !!"
- *After:* "Tired of printing invitations? Make one online: pick a template, add your photos and your message, then share the link or print the QR code on a card. Less printing, less running around. Start free: build and preview it. Send it with a plan. loveofmylife.love"

**2. The cost claim**
- *Before:* "cost less"
- *After (safe):* "No printing. No reprinting." / *After (only with your own quotes):* "50 printed invitations at ₱__ each is ₱__. One month of Sweet is ₱200. (Example: use your own numbers.)"

**3. The plan note**
- *Before:* nothing.
- *After:* ad description "Free to build. Send with a plan." and, on the christening post, "The Christening template is on Sweetheart."

**4. The QR sentence**
- *Before:* "scan qrcode and send it to the person that you want to invite"
- *After:* "Share the link, or print the QR code on a card. Guests scan it and the invitation opens."

## Lettie's invitation looks

**On brand.** The body, face and proportions are the master's untouched; only clothes, the seal's tint and things around her
change, exactly as the seasonal looks do (gold seal, party hat, veil, bonnet). The card she holds carries a **real, scannable QR
code** to the tagged site link. She is still the narrator who "carries your words", now to a guest list. No other character,
no romantic scene, no pushy words.

**Watch:**
- *Positioning (Medium):* the brand is "Love of My Life" and Lettie is a *love* letter. A christening or a reunion invitation is a
  stretch. It works for weddings and debuts. For the rest, keep the product name and give the audience its own landing page
  (`/invitations`) so the romantic voice does not follow them there.
- *The look is an addition, not a replacement:* the default Lettie stays everywhere else (site, sign-in, Valentine's, etc.). Say
  so if you meant a full change of the site mascot: that needs the 3D model and `Seasons::theme()` updated too (not done).
- *Trademark:* "Lettie" is still a working name; search IPOPHL before printing merchandise or spending heavily.
- *I could not look at the pictures myself* (you asked not to have screenshots read). They pass the automatic checks (nothing
  sticks out, no words overlap each other or the art, the mascot's ink stays inside its canvas), but please look at the four
  stickers and `inv-feed-occasions.png` once: the hats and veil were drawn from coordinates.

## Legal and compliance flags

- **Comparative / saving claims:** none in the new copy. Keep it that way until you hold real quotes.
- **Prices:** no picture shows a price. If you add ₱200 or ₱350 to an ad, take it from `config/saas.php` and check `PRICING.md`: apart from the ₱200 floor, those numbers were assumed choices.
- **Testimonials / numbers:** none. When the first customers arrive, ask for written permission and quote only what they agree to.
- **Photos of people and children:** no real people or babies are used (the sample photos are illustrations). Do not use a customer's invitation, which shows their photos and names, without their written permission. Warn customers that photos of children on a link anyone can open are public to whoever has the link (the lock question is optional and off on these templates).
- **Guests' data:** the app collects nothing from guests. Do not promise a guest list or RSVP (see finding 5).
- **Seasonal templates:** the Christmas Party invitation opens only from 11 Dec (14 days before). Do not advertise it before.
- **Meta policy:** do not address a person's relationship status, health or finances; "Are you getting married?" is a personal-attribute claim: use "Planning an invitation?" or the pain-point ("Tired of printing invitations?") instead.
- **Copyright:** emoji are system emoji; the cards, mascot and QR are original. The made-up names in the stills are not real people.
