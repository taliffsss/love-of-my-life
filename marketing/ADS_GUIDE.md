# From audience to first customer: a step-by-step guide to running Meta ads for Love of My Life

Written for the Halloween campaign (17 – 31 October) but the steps are the same for Christmas and Valentine's Day.
It assumes **no customers, no history and no pixel yet**, which is true today, so every number below is a *rule to measure
against*, not a benchmark. Nothing here claims what your cost per result will be: you find that out in the first week.

Read with: `social/HALLOWEEN.md` (the posts and ads), `UTM.md` (tracking), `CAMPAIGN_PLAN_HALLOWEEN.md` (the plan),
`EMAIL_SEQUENCES.md` (what happens after they sign up).

---

## Step 0. Before you spend a peso (one evening)

| Check | Why | Done when |
|---|---|---|
| The site opens on a phone from a **mobile-data** connection (not your Wi-Fi), `https://loveofmylife.love` | Most clicks are phones | The landing page, the demo and sign-up all work |
| You can sign up, build a page, and **pay** for a plan end to end | An ad that sends people to a dead checkout burns the budget (`social/CONTENT_PLAN.md`: don't say how to pay until checkout is live) | A test order was paid and the page went live |
| The **Halloween template** is on offer (17 – 31 Oct) | Outside the window it is hidden; ads would promise something people cannot pick | The landing page shows "Halloween" and "In season now" |
| **Admin → Traffic** records a test visit | It is how you will judge the ads | Open a tagged link (`make.sh links`), then see the row in the dashboard |
| A **Facebook Page** for the product, and a **Meta Business** account that owns it (business.facebook.com) | Ads run from a Page, managed in a Business account | You can open Ads Manager and see the Page |
| **Two-factor sign-in** on every admin of that Business account | A hijacked ad account is the most common loss | Turned on |
| A **payment method** in Ads Manager (card) | Meta bills you, not the other way | Added and verified |
| The profile text and rules in `social/PAGE_SETUP.md` read once | Page name, About, the first posts | Done |

> **Honesty rules for every ad:** no testimonials, no "X couples already", no customer numbers (there are none yet). "Start free" means the
> letter, the song and the design are free to build and preview; **photos and the Halloween template come with a plan** (Sweetheart or Forever),
> so never say "free photos" or "free Halloween". Prices only as on the plan cards.

---

## Step 1. Know who you are talking to (the audience)

You do not need a long persona. Write each audience as one sentence, then give each its own message and its own ad set.

> *[Who]* is feeling *[what]* and wants *[outcome]*. They spend time on *[where]*. They care most about *[priority]*.

| # | Audience | In one sentence | Their moment | Message | Starting targeting in Ads Manager |
|---|---|---|---|---|---|
| A | **Couples, 18 – 34, Philippines** | In a relationship, wants a gift that isn't a generic chat greeting | Halloween parties, monthsary, "what do I give them?" | "Skip the happy-halloween text. Knock three times, the door opens on your letter." | Location: Philippines · Age 18 – 34 · leave the audience **broad** (Advantage+ audience) and let the creative find them; add suggestions such as interests in gifts, couples, Halloween |
| B | **Long-distance couples and OFW partners** | Misses someone, wants to show up on the day from far away | Time zones, special days | "Miles apart, still one link away. Make a Halloween surprise they can open when they wake up." | Location: Philippines **and** the countries where Filipinos work (for example Saudi Arabia, UAE, Qatar, Singapore, Hong Kong) with language Tagalog/English. Check whether "lives abroad" (expats from the Philippines) is offered under Detailed targeting; Meta changes these options, so look, don't assume |
| C | **Gift givers (family, friends)** | Wants a small, personal, last-minute gift | The week before the 31st | "A haunted love letter for anyone you love, ready tonight." | Same as A, age 18 – 45 |

**Use two ad sets, not six.** Small budgets learn nothing when split many ways. Start with **A** and **B**. Add C only if A or B
shows sign-ups.

**What not to target:** minors (the product is for 16+ with a guardian under 18: keep **Age 18+**), or anything about a person's
relationship problems, health or finances ("lonely?", "single and sad?") — it breaks Meta's personal-attributes policy and Lettie's voice.

---

## Step 2. Decide what success is, and what you can afford

1. **Goal for the first two weeks:** *sign-ups and first customers*, not likes. Pick a number you can check: for example "15 sign-ups and 2 customers from the Halloween campaign". Write your own; this is a placeholder.
2. **What a customer is worth, from the plan prices** (`config/saas.php`): Sweet ₱200 a month (Halloween template not included), Sweetheart ₱350 a month, Forever ₱4,999 once. Only Sweetheart and Forever include the Halloween template.
   - A Halloween customer is at least **₱350** in the first month (before payment fees, which depend on how they pay: check your provider's rate).
   - So to **break even on the first month**, cost per customer must be below about ₱350 minus fees. If people renew, you can afford more; if they don't, you can't. Do not assume renewals until you have seen them.
3. **Turn it into two numbers to watch:**
   - **Cost per sign-up** = ad spend ÷ sign-ups (from *Admin → Traffic*, same campaign).
   - **Cost per customer** = ad spend ÷ customers (same).
4. **Starting budget:** spend what you can lose while you learn. A common way to start is a small daily budget per ad set for a week; for example ₱200 – ₱300 a day each is a reasonable *test size you choose*, not a figure Meta or this guide promises will work. The campaign window is short (15 days), so decide the **total** (daily × days) and do not top up in a panic.

---

## Step 3. Build the creative (a few minutes)

The kit is already made. Run it (or `make.sh halloween ads` for the ad pictures only):

```sh
marketing/make.sh halloween          # pictures, ads, carousel, reels, folders
marketing/make.sh links              # the UTM-tagged links
```

Everything lands in `marketing/out/social/halloween/`, one folder per ad with its **CAPTION-copy-paste.txt** (primary text, headlines, description, call to action, tagged link).

| Ad | Files | Best for | Placement |
|---|---|---|---|
| **Carousel** (5 squares) | `ad-hw-1…5.png` | Telling the story in order; audience A and B | Facebook and Instagram feeds |
| **Feed link ad** | `ad-hw-feed.png` | A cheap, simple test | Facebook feed |
| **Story / Reel ad** | `ad-hw-story.png`, `halloween-knock.mp4` | Phones, full screen | Stories and Reels |

**Test one thing at a time.** First week: same audience, two creatives (carousel vs. reel). Do not change audience, picture and text all at once, or you will not know what worked.

---

## Step 4. Create the campaign in Ads Manager (the click-by-click)

Ads Manager (adsmanager.facebook.com) → **Create**. Meta renames buttons from time to time; the idea stays: *campaign → ad set → ad*.

### 4.1 Campaign
1. **Objective: Traffic.** (Why not Sales? Sales and Sign-up optimisation need a **Meta Pixel / Conversions API** on the site, which is not installed yet. See Step 9.)
2. **Name:** `halloween-2026` (exactly, lower case: it becomes `utm_campaign`).
3. Special ad categories: **none**. Buying type: Auction. A/B test: off for now.

### 4.2 Ad set (one per audience)
1. **Name:** `A-couples-ph` (or `B-ofw`).
2. **Conversion location:** Website. **Performance goal:** *Maximise number of link clicks* (landing page views needs the pixel).
3. **Budget & schedule:** the daily budget you chose. **Start** 17 Oct, **end** 31 Oct 23:59 (Philippine time).
4. **Audience:** Location and age from Step 1. Language: English and Tagalog. Leave detailed targeting broad.
5. **Placements:** *Advantage+ placements* (let Meta choose) for the first week.

### 4.3 Ad (one per creative)
1. **Name:** `ad-carousel`, `ad-feed`, `ad-story` (exactly; they become `utm_content`).
2. **Identity:** your Facebook Page (and Instagram account if linked).
3. **Format:** Carousel → add the five cards `ad-hw-1…5.png`, one headline each (they are in the caption file). Single image → `ad-hw-feed.png` or `ad-hw-story.png`. Video → `halloween-knock.mp4`.
4. **Primary text, headline, description, call to action:** paste from `CAPTION-copy-paste.txt` (call to action: **Learn more**).
5. **Destination: Website URL** → paste the tagged link from that folder. *Or* use the website URL `https://loveofmylife.love/` and put this in **URL parameters**: `utm_source=facebook&utm_medium=paid_social&utm_campaign={{campaign.name}}&utm_content={{ad.name}}` (see `UTM.md`).
6. **Preview** the ad on Facebook feed, Instagram feed and Stories before publishing. Check that no text is cut and the link opens on your phone.

### 4.4 Review and publish
Meta reviews ads (usually within a day; plan for that). Publish **on 16 Oct** to be live on the 17th.

---

## Step 5. The first 72 hours: check, don't tinker

- **Open the tagged link yourself** from the live ad. See the visit in *Admin → Traffic*. If it is not there, fix tracking before spending more.
- Leave the ad sets alone for 2 – 3 days; Meta needs time to learn.
- Reply to every comment and message within a few hours, in Lettie's voice (warm, never pushy).

---

## Step 6. Read the results (twice a week, 15 minutes)

Two places, one question each:

| Where | Question | Numbers |
|---|---|---|
| **Ads Manager** | Did people see and click? | Reach, CTR (link), CPC, amount spent |
| **Admin → Traffic** (campaign `halloween-2026`) | Did the click become a sign-up and a customer? | Visitors, sign-ups, customers, revenue, per ad (`utm_content`) |

Compute **cost per sign-up** and **cost per customer** per ad. Decide with these rules (adjust to your own numbers):

| After… | If… | Do |
|---|---|---|
| 3 days | An ad has spent about twice your target cost per sign-up and has **no** sign-ups | Pause that ad (not the whole ad set) |
| 3 days | CTR is very low everywhere | The picture or the first line is not working: swap the creative, keep the audience |
| 7 days | Clicks are fine but **sign-ups are not** | The problem is the page, not the ad: check the landing page on a phone, the sign-up form, the first screen after sign-up |
| 7 days | Sign-ups but **no customers** | The problem is the offer or the checkout: see Step 8 |
| 7 days | An ad set clearly wins | Move more of the budget to it, in steps (not doubling overnight) |

> A rate above 100% in the dashboard means people signed up in the period after visiting earlier. It is not an error.

---

## Step 7. Turn visitors into customers (where the money is made)

1. **Make the first minute count.** After sign-up the person lands on the dashboard with the tour (`users.tour_seen_at`). Watch a real
   new user do it once. Where do they stop?
2. **Make the Halloween promise visible.** The landing hero is preset to the season that is on; from the ad they should see Halloween first.
3. **Follow up while they are warm.** Send the onboarding sequence in `EMAIL_SEQUENCES.md` (welcome today, a "finish your page" nudge on day 2, an upgrade note when they hit the photo limit). The app does not send e-mail yet: start by hand, or connect a mailer.
4. **Talk to the first ten people.** For your first customers, message everyone who comments or signs up: "Hi, I'm … from Love of My Life. Did the Halloween page work for you? Anything confusing?" The answers are worth more than any dashboard. Ask **permission** before you quote anyone; never invent a testimonial.

---

## Step 8. Fix the leaks in order

| Leak | Sign | Fix |
|---|---|---|
| Ad → click | Low CTR | New first line or picture; test the reel |
| Click → sign-up | Visitors, few sign-ups | Shorter path: the ad promises "start free"; the first screen must be the sign-up or the live demo, not a long page. Check mobile speed |
| Sign-up → page built | Sign-ups, no pages | Tour, starter templates, onboarding e-mail |
| Page built → paid | Pages, no customers | The plan page: show what the plan gives, in plain words; explain that photos come with a plan *before* they hit the wall |
| Paid → renewed | Customers cancel | A reminder before renewal, a new letter each monthsary (`CAMPAIGN_PLAN_HALLOWEEN.md`) |

---

## Step 9. After the first week: make the ads smarter (needs a small build)

Right now Meta only knows who **clicked**. To let it find people who **sign up and pay**:
1. Install the **Meta Pixel** and the **Conversions API** with a *Complete registration* and a *Purchase* event. This needs a change to the site's security policy (it currently allows scripts only from itself, YouTube and Google) and a consent decision for the Data Privacy Act. Ask for it as a task; do it before Christmas.
2. Then switch the objective to **Sales / Leads** with the *Complete registration* event, and build **retargeting** (people who visited but did not sign up) and **lookalikes** from your first customers.
3. Until then, keep Traffic and judge by *Admin → Traffic*.

---

## Step 10. Close the campaign

On 31 Oct, switch the ads off at midnight (the template closes to new choices on 1 Nov). Within a week write down:
- Spend, visitors, sign-ups, customers, revenue per ad (from *Admin → Traffic*).
- Which audience, which picture and which first line won.
- What you will do differently for Christmas (opens 11 Dec) and Valentine's Day (opens 31 Jan).
Put the numbers in `marketing/reports/halloween-2026.md` and use `marketing:performance-report` if you want help writing it up. Then copy the campaign: change `halloween` to `christmas` in `social/utm.py`, the cards and the captions.

## Weekly routine once you are running

| Day | 20 minutes |
|---|---|
| Mon | Admin → Traffic: last 7 days by campaign and ad. Pause losers, note winners |
| Wed | Ads Manager: spend, CTR, comments to answer |
| Fri | Talk to 3 new sign-ups. Update the next week's posts (`social/CONTENT_PLAN.md`) |

## Common mistakes

- Sending traffic to a page that does not match the ad (the ad says Halloween, the page shows hearts only).
- Changing audience, picture and text at once, so nothing is learnt.
- Judging by likes instead of sign-ups and customers.
- Promising "free" for something that needs a plan.
- Forgetting to switch the ads off when the template closes.
