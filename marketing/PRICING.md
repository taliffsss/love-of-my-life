# Pricing: plans, photos and what they earn

Status: **built** (2026-10-18). The plans and the photo pack live in `config/saas.php` and can still be changed freely:
version 3.0 has not launched, so no customer pays these prices yet. Payments are confirmed by hand until automatic
charging exists (see "What is left").

## The plans

| | **Sweet** | **Sweetheart** (most popular) | **Forever** |
|---|---|---|---|
| Monthly | **₱200 / month** | **₱350 / month** | none |
| Yearly (twelve months, paid at once) | ₱2,400 / year | ₱4,200 / year | none |
| One time | none | none | **₱4,999** |
| Photos it adds to the account | 12 | 40 | 60 |
| Templates | 5: Classic, Cinematic, Polaroid, Envelope, Timeline | all 10 | all 10 |
| Own address `name.loveofmylife.love` | no | **yes** | yes |
| Song, lock question, link, QR code, change anything any time | yes | yes | yes |
| Stays online | while paid | while paid | always |

- **Free preview:** build everything and preview it privately, with all 10 templates and 6 photos, before paying.
- **Extra photos:** 12 for ₱100, 1 to 10 packs an order. Paid once, kept for good, shared by all the account's pages.

**What changed from the first monthly plans (₱59 / ₱99 / ₱1,499):**
- **Prices start at ₱200 a month**, and the other plans keep roughly their distance from it.
- **No yearly discount.** A year costs twelve months, so the "2 months free" offer is gone. The yearly option stays
  because, while payments are manual, paying once a year saves the customer eleven GCash transfers.
- **Photos are counted per account, and extra photos are sold.**

**Why these numbers:**
- **₱200 is the entry price you set.** Sweetheart at ₱350 is 1.75 times that. It adds 28 photos, worth ₱233 in packs,
  plus every template and the own address, so upgrading is the better deal for anyone who wants more than Sweet.
- **Forever at ₱4,999** is about 14 months of Sweetheart, and a little more than one year of it (₱4,200). Couples who
  plan to keep the page pay once; for everyone else the monthly price looks small next to it.

## Photos

The allowance belongs to the account, not to one page:
- **It adds up.** It is the photos of the plans of the account's live pages, added up: a Sweet page and a Sweetheart
  page give 52. While no page is live, it is the free preview's 6. Photo packs come on top.
- **Every photo counts.** That means every photo on every page, drafts included; deleting a photo or a page frees its
  place. Five free drafts can no longer hold 30 photos between them.
- **When a plan ends, its photos stop counting.** Nothing is deleted or hidden, but no photo can be added until the
  account fits again: renew, buy packs, or delete some. The dashboard says how many to delete or which packs make room.
- **The server enforces all of this.** It checks before storing an upload and again after saving it, so two uploads at
  once can't get past the limit. The browser checks first only to save a wasted upload.
- **Photos can be up to 10 MB each.** Larger files get "This photo is 14.2 MB. Photos can be up to 10 MB each." The
  browser shrinks the rest to about 0.5–0.9 MB.
- **Deleted photos leave storage.** If Spaces fails at that moment, the file waits in a trash table, and
  `bin/empty-trash.php` (daily cron) deletes it later.

What photos cost us: Spaces is $5 a month for 250 GiB and 1 TiB of downloads. Beyond that, storage is $0.02 per GiB
and downloads $0.01 per GiB ([DigitalOcean Spaces](https://www.digitalocean.com/products/spaces)).
- **Storage:** a pack of 12 is about 8 MB, so ₱100 pays for its storage for decades.
- **Downloads:** a visitor who opens every photo of a 60-photo page downloads about 42 MB. Downloads beyond the free
  1 TiB cost about ₱0.02 per such visit.

## The money

Assumptions:
- **Payment fees:** PayMongo's published rates, VAT included. Cards are 3.5% + ₱15, GCash 2.5%, Maya 2.0%, QR Ph 1.5%
  ([PayMongo fees](https://developers.paymongo.com/docs/fees-and-rates)).
- **Hosting:** about ₱1,500–2,000 a month for your share of the server, Spaces and the domain.

What each payment leaves us after fees:

| Payment | GCash | Maya | QR Ph | Card |
|---|---|---|---|---|
| Sweet ₱200 | ₱195.00 | ₱196.00 | ₱197.00 | ₱178.00 (11% fees) |
| Sweetheart ₱350 | ₱341.25 | ₱343.00 | ₱344.75 | ₱322.75 |
| Sweet yearly ₱2,400 | ₱2,340.00 | ₱2,352.00 | ₱2,364.00 | ₱2,301.00 |
| Sweetheart yearly ₱4,200 | ₱4,095.00 | ₱4,116.00 | ₱4,137.00 | ₱4,038.00 |
| Forever ₱4,999 | ₱4,874.03 | ₱4,899.02 | ₱4,924.02 | ₱4,809.04 |
| 12 photos ₱100 | ₱97.50 | ₱98.00 | ₱98.50 | ₱81.50 (19% fees) |

What these numbers mean:
- **Card fees no longer eat the monthly plans.** At ₱59 they took 29%; at ₱200 they take 11%.
- **Packs belong on e-wallets.** The card's ₱15 is a fifth of a ₱100 pack, so offer GCash, Maya and QR Ph first.
- **Fixed costs are covered early.** About 10 paying pages a month cover hosting.
- **Advertising decides profit.** Suppose an ad sale costs ₱150 and a monthly customer stays 3 months. A Sweet
  customer then brings in about ₱585 after GCash fees, and a Sweetheart customer about ₱1,024.

## Decisions taken

The ones marked *assumed* were my call while building. Each is a line in `config/saas.php` or
`App\Services\PhotoAllowance` if you want it otherwise.

| Rule | Status |
|---|---|
| Plans start at ₱200 a month; no "2 months free" | confirmed by you |
| 12 photos for ₱100; every photo counts towards the limit | confirmed by you |
| Up to 10 MB per photo (counted like phones show it: 10 × 1024 × 1024 bytes) | confirmed; the counting is assumed |
| Sweetheart ₱350/month, Forever ₱4,999, yearly = 12 × monthly | assumed |
| Photos counted per account; live plans add up; the free 6 apply only while no page is live | assumed (from your question about 6 templates sharing 60 photos) |
| A deleted photo frees its place (the limit is on photos kept, not photos ever uploaded) | assumed (you asked that deleting update the usage) |
| Packs never expire and stay when their page is deleted; 1–10 packs an order | assumed |
| When a plan ends: nothing deleted or hidden, uploads blocked until the account fits | assumed |
| A file used by two photos is kept until the last one is deleted (photos can't be shared between pages today, so this is a safeguard) | assumed |

## What is left

- **Paying every month is still manual.** A customer gets an order reference and pays by GCash or Maya, and you confirm
  it with `bin/grant-plan.php <reference>`; packs work the same way. Automatic monthly charging needs PayMongo
  Subscriptions ([API](https://developers.paymongo.com/docs/subscriptions)), and their account has to be approved
  first. The product page also lists GCash and Maya
  ([PayMongo Subscriptions](https://www.paymongo.com/products/accept-payments/subscriptions)); confirm that with them.
- **Renewal reminders need e-mail.** The app sends none yet.

## Risks

- **Monthly churn after the occasion.** Couples may stop paying once the anniversary is over. Watch how long monthly
  customers stay.
- **₱200 is a bigger first step than ₱59.** The free preview, with every template, is what has to sell it. Watch how
  many drafts become paid pages in the first month.
- **Forever is cheap next to a yearly Sweetheart.** The two differ by ₱799, so many yearly buyers may take Forever. That
  brings more money now and less later. If it happens a lot, raise Forever.

## Sources
- [PayMongo: fees and rates](https://developers.paymongo.com/docs/fees-and-rates)
- [PayMongo: Subscriptions API](https://developers.paymongo.com/docs/subscriptions)
- [PayMongo: Subscriptions product page](https://www.paymongo.com/products/accept-payments/subscriptions)
- [DigitalOcean Spaces pricing](https://www.digitalocean.com/products/spaces)
