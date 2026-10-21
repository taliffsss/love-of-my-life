# Campaign plan: Halloween 2026 ("Knock three times")

Made with the `campaign-plan` structure. **Assumptions** (you did not give a budget or a target): no budget figure, so the plan is
channel-agnostic with a suggested split; no customers or analytics yet, so every number below is a *target to test*, not a forecast.
Today is **21 Oct**: the Halloween template has been on offer since **17 Oct** and closes to new choices after **31 Oct**, so this plan starts now and
has **11 days** left. The companion how-to is `ADS_GUIDE.md`; tracking is `UTM.md`; the content is `social/HALLOWEEN.md`.

## 1. Campaign overview

- **Name:** Knock three times.
- **One sentence:** A haunted love letter: they knock three times, the door creaks open, your photos, song and words are inside.
- **Primary objective:** first paying customers. **Target to test:** 15 sign-ups and 2 customers from this campaign by 31 Oct (replace with your own number).
- **Secondary objectives:** learn which audience and creative gets the cheapest sign-up; build the audience for Christmas (opens 11 Dec) and Valentine's Day (opens 31 Jan); collect 3 conversations with real users.

## 2. Target audience

| | Primary: couples 18 – 34 in the Philippines | Secondary: long-distance couples and OFW partners |
|---|---|---|
| Pain point | "Happy Halloween" in the chat feels generic | Wants to be present on the day from far away |
| Motivation | Be the one who made something thoughtful and a little funny | Give a surprise they can open when they wake up |
| Where | Facebook, Instagram, TikTok, Messenger groups | Facebook groups, Messenger, family and couple pages |
| Stage | Awareness → consideration (does not know the product) | Same |
Tertiary (only if A/B show sign-ups): gift givers 18 – 45.

## 3. Key messages

- **Core:** Skip the "happy Halloween" text. Make them a haunted love letter.
- **Supporting:** (1) *Knock three times and the door opens on your photos and letter.* (2) *Locked with a question only they can answer.* (3) *Spooky on the outside, soft inside: your own words.* (4) *Start free: build and preview before you choose a plan.*
- **Proof points:** the live demo (`/demo?layout=halloween`), the reel and the pictures. **No testimonials or numbers**: there are none yet; after the first customers, ask permission and quote only what they agree to.
- **Tone:** Lettie: warm, a little shy, playful, never pushy. Taglish for the Philippines audience; English for the OFW audience.

## 4. Channel strategy

| Channel | Why | Format | Effort | Suggested share of any budget |
|---|---|---|---|---|
| Facebook Page (organic) | Where the Page and the posts already are; free | 5 posts + 2 reels (`social/HALLOWEEN.md`) | Low | 0% (time only) |
| **Meta ads** (Facebook + Instagram) | The fastest way to reach a cold audience in 11 days | Carousel, feed link ad, story/reel ad | Medium | **~70%** |
| TikTok (organic) | Reels and a photo carousel are ready | 1 carousel + the reels | Low | 0% (time only) |
| Retargeting (needs the Meta Pixel) | Cheapest conversions | Later (Christmas) | Medium | 0% now |
| Direct outreach to the first sign-ups | Learning, first testimonials (with permission) | Personal messages | Low | 0% |
| Testing reserve and contingency | A winner worth scaling, or a fix | — | — | ~30% (keep 10 – 15% as contingency) |
The goal is not to be everywhere: it is two audiences, three creatives and one clear number.

## 5. Content calendar (from today)

| Date | Content | Channel | Notes / dependency |
|---|---|---|---|
| Mon 21 Oct | Check **Admin → Traffic** records a tagged visit; publish Post 1 (hook) | Facebook | Dependency: UTM links (`make.sh collect`) |
| Tue 22 | Reel 2 (lock) + TikTok carousel | Facebook, TikTok | Ads: submit for review today (Meta reviews first) |
| Wed 23 | Ads live: carousel + feed (audience A and B) | Meta ads | Dependency: checkout tested, ads approved |
| Thu 24 | Post 3 (lock) | Facebook | Answer comments within hours |
| Sat 26 | Post 5 (poll) | Facebook | Poll only: no giveaway |
| Mon 28 | Post 4 (steps) + first read of results | Facebook, Admin → Traffic | Pause losers, move budget to winner |
| Wed 30 | Reel 1 again with "last two days" | Facebook, TikTok | Story ad on |
| Thu 31 | A Story with Post 2 | Facebook | **Switch ads off at 23:59** |
| Fri 1 Nov – Fri 8 Nov | Write up results; talk to sign-ups; plan Christmas | — | `reports/halloween-2026.md` |
(Posts 2 and Reel 1 from the schedule in `social/HALLOWEEN.md` slot into 21 – 31 Oct wherever the week is light: 25 and 27 Oct.)

## 6. Content pieces needed (all built: `marketing/make.sh halloween`)

| Asset | Files | Priority |
|---|---|---|
| 5 posts | `hw-hook`, `hw-door`, `hw-lock`, `hw-steps`, `hw-poll` | Must |
| 2 reels | `halloween-knock.mp4`, `halloween-lock.mp4` | Must |
| Ad carousel | `ad-hw-1…5` | Must |
| Feed ad, story ad | `ad-hw-feed`, `ad-hw-story` | Must |
| TikTok carousel | `car-hw-1…5` | Nice |
| Tagged links + captions + ad copy | `out/social/halloween/` | Must |
| Blog post / `/halloween` landing page | `content/halloween-love-letter-ideas.md` | Nice (needs a page to live on) |
| Onboarding e-mails | `EMAIL_SEQUENCES.md` | Must for the first customers, even if sent by hand |

## 7. Success metrics

| KPI | Target to test | Where |
|---|---|---|
| **Customers** (paid) | 2 | Admin → Traffic (`halloween-2026`) |
| **Sign-ups** | 15 | Admin → Traffic |
| Cost per sign-up | below what you decide in `ADS_GUIDE.md` Step 2 | spend ÷ sign-ups |
| Cost per customer | below ~₱350 (Sweetheart month, before fees) to break even on month one | spend ÷ customers |
| Link CTR, CPC | learn, no target | Ads Manager |
| Landing → sign-up rate | learn | Admin → Traffic ("Rate") |
Report twice a week during the campaign and once at the end (`marketing:performance-report`).

## 8. Budget

No budget was given. A way to split **whatever you choose**: Meta ads 70% (two ad sets, so each gets ~35%), testing reserve 20%, contingency 10%.
Production cost is zero (it is built). Do not top up mid-campaign to chase a bad ad; reallocate within the total.

## 9. Risks and mitigations

| Risk | Mitigation |
|---|---|
| Ads are approved late or rejected | Submit today; keep wording within the honesty rules (no personal-attribute claims, no "free" for paid features) |
| People click but do not sign up | Check the landing page on mobile data; the ad's promise must match the first screen |
| Sign-ups but no customers (the Halloween template needs Sweetheart or Forever) | Say so plainly before the wall; onboarding e-mail 3; talk to the first users |
| Tracking misses clicks | Test the tagged link before spending; `direct` too high means untagged links |
| The window closes 31 Oct | Switch ads off at midnight; reuse the machine for Christmas |

## 10. Next steps (today)

1. `marketing/make.sh collect` and open one tagged link; see it in **Admin → Traffic**.
2. Test one paid order end to end.
3. Create the Meta campaign (`ADS_GUIDE.md` Step 4) and submit the ads.
4. Post Post 1 today.
5. Decisions only you can make: the **total budget**, the **sign-up and customer targets**, and whether to add the Meta Pixel before Christmas (`ADS_GUIDE.md` Step 9).
