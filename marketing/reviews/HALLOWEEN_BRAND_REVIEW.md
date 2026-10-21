# Brand review: the Halloween copy

Reviewed: every caption, ad text, headline and on-screen/narrated line of the Halloween campaign (`social/HALLOWEEN.md`, the five posts,
five ad cards, feed and story ads, the carousel, the two reels' lines in `social/reels/reels.js`).
Against: Lettie's voice and rules (`brand/LETTIE.md`), the kit's honesty rules (`social/CONTENT_PLAN.md`: no testimonials or customer numbers, no claims beyond what the product does, no yearly discounts, don't say how to pay), and the plan facts in `config/saas.php`.

## Summary

**Overall: on brand, with two things to fix before the ads go live.** The voice is warm, playful and never pushy; the Taglish lines read naturally; hashtags are within the 3 – 5 rule; there are no testimonials, numbers about customers or discounts.
**Strengths:** a clear one-image idea ("knock three times"), a gentle sense of humour that stays kind, and short sentences like Lettie's.
**Fix first:** (1) the copy says "start free" but never says that *sending* the Halloween page needs Sweetheart or Forever; (2) "floats and turns as they move" describes something that mainly happens with a mouse, not on a phone.

**Applied already** (in `social/HALLOWEEN.md` and `ad-hw-5`): findings 1, 2 and 4. Findings 3, 5, 6 and 7 are left for you to decide.

## Findings

| # | Issue | Where | Severity | Suggestion |
|---|---|---|---|---|
| 1 | Plan not disclosed: "Start free and send it before the 31st" implies sending is free. Building and previewing are free; **publishing the Halloween template needs Sweetheart (₱350/month) or Forever (₱4,999)** | Ad carousel primary text; `ad-hw-5`; Post 1/2/4 CTA; reels' end line | **High** | Add one short disclosure: ad description "Halloween template with Sweetheart or Forever" and in posts "Start free, send with a plan". Keep "Start free" (it is true for building and previewing) |
| 2 | Motion claim: "The Halloween lock screen floats and turns as they move" is true with a pointer; on a phone it is mostly still | Post 3; chip "3D lock screen" on `hw-lock` | Medium | "On a computer, the Halloween lock screen turns as you move the mouse." or drop the motion claim and keep "pumpkin lock screen" |
| 3 | Inconsistent end line: the five older reels end on "Build it free", the Halloween reels on "Start free" | Reels | Medium | Use "Start free" everywhere in new material; re-voice the older reels when next rendered |
| 4 | Superlative: "the sweetest jump scare they will ever get" | Post 2 | Low | Playful, but unprovable. "a sweet little jump scare" |
| 5 | Product term drifts: "page", "letter", "love page" | Several | Low | Keep **letter** for what you write and **page** for what they open; avoid "love page" in ads |
| 6 | Urgency is mild and true ("before the 31st") but repeats 6 times | Ads, posts | Low | Keep it in the ads and the last two posts only; Lettie is "never pushy" |
| 7 | Emoji density (3 – 4 per post) is fine on social, heavy in ad primary text | Ads | Low | One emoji in ad text |

## Revised sections (before → after)

**1. Plan disclosure**
- *Before:* "Start free and send it before the 31st."
- *After:* "Start free: build and preview it. Send it before the 31st with Sweetheart or Forever."

**2. Motion claim (Post 3)**
- *Before:* "The Halloween lock screen floats and turns as they move."
- *After:* "On a computer, the Halloween lock screen turns as you move the mouse. Then a question only the two of you can answer."

**3. Superlative (Post 2)**
- *Before:* "It is the sweetest jump scare they will ever get."
- *After:* "A sweet little jump scare, made by you."

## Legal / compliance flags

- **Personal-attribute claims (Meta ad policy):** none of the Halloween ads address a person's relationship, health or finances ("Are you lonely?"). Keep it that way when you add OFW variants ("miles apart?" as a *statement about the product's use* is fine; "you are away from your partner" is not).
- **Prices:** only the plan-card prices may appear; none of the pictures show a price. If you add the Sweetheart price to an ad, check `render_social.py`'s price list.
- **Testimonials:** none used. When the first customers arrive, ask for written permission and quote only what they agree to.
- **Contest/giveaway rules:** Post 5 is a poll, not a giveaway. Do not add a prize without Meta's required wording.
- **Privacy:** the site now sets a first-party visitor cookie and saves the campaign on the account for tracking; the Privacy Policy (section 6) was updated. The ads need no extra disclosure.
- **Copyright:** emoji and the template art are original or system emoji; nothing copied.
- **Trademark:** "Lettie" is a working name; search IPOPHL before printing merchandise or paying for large campaigns (noted in `brand/LETTIE.md`).
