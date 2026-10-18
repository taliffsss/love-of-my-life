# Pricing proposal: monthly plans for Love of My Life

Status: **built** (2026-10-18), as proposed below. The plans live in `config/saas.php`, and they can still be changed
freely: version 3.0 has not launched, so no customer is on these prices yet. Monthly payments are confirmed by hand
until automatic charging exists (see "What it takes to build", point 4).

## What we sold before (the first 3.0 plans)

| Plan | Price | Lasts | Photos | Own address (`name.love…`) |
|---|---|---|---|---|
| Free preview | ₱0 | draft only, never public | 6 | no |
| Basic | ₱99 | 1 year | 6 | no |
| Plus | ₱199 | 1 year | 20 | no |
| Forever | ₱399 | never expires | 50 | yes |

Every plan gets all 10 templates, all 4 colour themes, the song, the lock question, the link and the QR code.

**The problems:**
- **Low revenue per customer.** A Basic page earns ₱99 a year, and one Facebook or Instagram ad sale probably costs more
  than that. That cost is an assumption; check it with your first ad tests.
- **One payment, endless upkeep.** Forever's ₱399 pays once for a page we host and back up indefinitely.
- **Weak reasons to upgrade.** Everything a buyer sees in the preview comes with every plan, so the plans differ only in
  the number of photos and the address.

## The plans

| | **Sweet** | **Sweetheart** (most popular) | **Forever** |
|---|---|---|---|
| Monthly | **₱59 / month** | **₱99 / month** | none |
| Yearly (2 months free) | ₱590 / year | ₱990 / year | none |
| One time | none | none | **₱1,499** |
| Photos | 12 | 40 | 60 |
| Templates | 5: Classic, Cinematic, Polaroid, Envelope, Timeline | all 10 | all 10 |
| Own address `name.love.markanthonynaluz.com` | no | **yes** | yes |
| Song, lock question, link, QR code, change anything any time | yes | yes | yes |
| Stays online | while subscribed | while subscribed | always |

The Free preview stays as it is: build everything and preview it privately, with **all 10 templates**, before paying.

**Why these numbers:**
- **Small, monthsary-sized prices.** ₱59 is less than a milk tea and ₱99 less than a cinema ticket. Many couples
  here celebrate every *monthsary*, so a monthly plan fits: update the letter and photos each month and the page stays a
  living gift.
- **Two clear reasons to upgrade.** The own address, which used to come only with Forever, moves down to Sweetheart.
  The five playful templates (Scratch card, Storybook, Memory game, Gift box, Starry sky) need Sweetheart too. People try
  them in the free preview, so they know exactly what the upgrade gets them.
- **Forever as the anchor.** At ₱1,499 it costs as much as 15 months of Sweetheart. Couples who plan to keep the page
  get a fair deal, and next to it the monthly price looks small.
- **Yearly plans:** paying for the year costs 10 months (2 free). They bring money in up front and keep more people
  subscribed.

## The money

Assumptions:
- **Payment fees.** These are PayMongo's published rates, VAT included: cards 3.5% + ₱15, GCash 2.5%, Maya 2.0%, QR Ph
  1.5% ([PayMongo fees](https://developers.paymongo.com/docs/fees-and-rates)).
- **Hosting.** Fixed hosting is about ₱1,500–2,000 a month: your share of the server, Spaces at $5 a month for 250 GB,
  and the domain. A page with 60 photos uses about 30 MB, so the cost per page is negligible.

What each payment leaves us after fees:

| Payment | GCash | Maya | QR Ph | Card |
|---|---|---|---|---|
| Sweet ₱59 | ₱57.53 | ₱57.82 | ₱58.12 | ₱41.94 (29% fees) |
| Sweetheart ₱99 | ₱96.53 | ₱97.02 | ₱97.52 | ₱80.54 |
| Sweet yearly ₱590 | ₱575.25 | ₱578.20 | ₱581.15 | ₱554.35 |
| Sweetheart yearly ₱990 | ₱965.25 | ₱970.20 | ₱975.15 | ₱940.35 |
| Forever ₱1,499 | ₱1,461.53 | ₱1,469.02 | ₱1,476.52 | ₱1,431.54 |

What these numbers mean:
- **Monthly plans belong on e-wallets.** Card fees take 29% of a ₱59 payment, so offer GCash, Maya and QR Ph first for
  monthly plans and push cards towards the yearly plans and Forever.
- **Fixed costs are covered early.** About 35 paying pages a month cover the fixed costs at any mix of plans.
- **Advertising is the real cost.** It decides whether we make money. If an ad sale costs ₱150 and a monthly customer
  stays 3 months, a Sweet customer brings in about ₱175 after fees, which is barely profitable. The same customer on
  Sweetheart brings in about ₱290, and on a yearly plan or Forever ₱575–1,460 up front. So the website and the ads should
  lead with Sweetheart and offer the yearly saving prominently.
- **Free advertising from every page.** Every page visitors open is free advertising if it carries a small "Made with
  Love of My Life" link. Today only the demo has one. Worth adding to Sweet pages; that's a separate decision.

## What was built, and what is left

1. **Plans (built).**
   - `config/saas.php` holds the three plans, each with monthly and yearly prices or a one-time price, its photo limit,
     whether it includes the own address, and its templates.
   - Each order records its period.
   - A payment adds a calendar month or year to the time left; Jan 31 + 1 month is the end of February.
2. **Templates per plan (built).**
   - The free preview, and the owner's preview of a live page, show every template.
   - Templates outside the plan are refused in two places, each saying which plans include the template:
     - buying a plan that doesn't include the page's template;
     - saving one on a live page.
   - The picker marks those templates and says so before saving.
   - Visitors only ever see a template of the page's plan.
3. **Dashboard and landing page (built).**
   - Every plan shows its monthly and yearly buttons, with "2 months free" on the yearly one; Forever shows "once".
   - Sweetheart is marked "Most popular".
4. **Paying every month (still manual).**
   - Today a customer gets an order reference, pays by GCash or Maya, and you confirm it with `bin/grant-plan.php`. They
     pay again the same way each month.
   - Automatic monthly charging needs PayMongo Subscriptions. It is documented for cards
     ([PayMongo Subscriptions API](https://developers.paymongo.com/docs/subscriptions)). PayMongo's product page also
     claims GCash and Maya ([PayMongo Subscriptions](https://www.paymongo.com/products/accept-payments/subscriptions)),
     which needs confirming with them, and their account has to be approved first.
   - It also needs renewal reminders by e-mail. The app sends no e-mail yet; the same mailer would also fix the sign-up
     check that reveals which e-mails have an account.
5. **Tests (built)** cover every plan and period, templates per plan, monthly and yearly renewals, month-end dates,
   upgrades and the screens.

## Risks

- **Monthly churn after the occasion.** Couples may cancel once the anniversary is over. The yearly saving and the
  monthsary angle are the answer; watch how long monthly customers actually stay.
- **Manual monthly payments put people off.** Paying by hand every month is a hassle until automatic charging exists.
  Until then, consider selling only yearly plans and Forever online, and offering monthly as "pay as you go".
- **Prices to check after a month of sales.** Is ₱59 too cheap to matter, and is ₱1,499 too much for Forever? The
  conversion numbers will tell; the plans are one config file, so they're easy to change.

## Sources
- [PayMongo: fees and rates](https://developers.paymongo.com/docs/fees-and-rates)
- [PayMongo: Subscriptions API](https://developers.paymongo.com/docs/subscriptions)
- [PayMongo: Subscriptions product page](https://www.paymongo.com/products/accept-payments/subscriptions)
- [HitPay: subscription billing in the Philippines](https://hitpayapp.com/ph/blog/best-subscription-billing-software-philippines)
- Comparable gift pages are sold one-time abroad. For example, Sevgilime in Turkey charges about 200 TL for a year and
  350 TL for life ([listing](https://trustmrr.com/startup/sevgilime-website)); a marketplace listing, not verified.
