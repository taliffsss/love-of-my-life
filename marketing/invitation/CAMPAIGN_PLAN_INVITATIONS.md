# Campaign plan: "Invite, don't print" (invitations, Nov to mid-Dec 2026)

Made with the `campaign-plan` structure. **Assumptions** (you gave no budget, target or dates): no budget figure, so the plan is
channel-agnostic with a suggested split; no customers or analytics yet, so every number is a *target to test*, not a forecast.
Today is **Wed 28 Oct 2026**. The plan runs **6 weeks, Mon 2 Nov to Sun 13 Dec**. Companion files: `COPY.md` (every line),
`BRAND_REVIEW_INVITATIONS.md`, `product-facts.json` (what may be claimed), `links.py` (tracking), `../ADS_GUIDE.md` (how to run the ads).

## 1. Campaign overview

- **Name:** Invite, don't print.
- **One sentence:** Make your wedding, debut or christening invitation online: your photos and words, shared as a link or a QR code, with no printing.
- **Primary objective:** first paying customers from a new audience (people planning a celebration, not only couples). **Target to test:** 40 sign-ups and 6 paying customers by 13 Dec (replace with your own number).
- **Secondary:** learn which occasion (wedding, christening, debut) and which hook sells; collect 3 conversations with real hosts; build the audience for the Christmas-party and New Year season.

## 2. Target audience

| | Primary: hosts in the Philippines, 24–45 | Secondary: families abroad and far away |
|---|---|---|
| Who | Couples planning a wedding; parents planning a christening, a debut or a baby shower | OFW relatives and far-away guests; the host who must reach them |
| Pain point | Designing, printing and handing out cards takes time and money, and a changed time or venue means reprinting | A printed card can't reach someone overseas in time; a link can |
| Motivation | Look thoughtful, keep every detail in one place, fix mistakes without reprinting | Be included; get the details on their phone |
| Where | Facebook (groups for brides, mothers, suppliers), Messenger/Viber chats, TikTok, Instagram | Same, plus family group chats |
| Stage | Awareness → consideration (does not know it exists) | Awareness |

Tertiary (only if tests show sign-ups): wedding suppliers (photographers, stylists, coordinators) who could refer hosts.

## 3. Key messages

- **Core:** Tired of printing invitations? Make one online.
- **Supporting:** (1) *Pick a template, add your photos and your words.* (2) *Share the link, or print the QR code on a card: print less, not nothing.* (3) *Change the date or venue later; the link and the QR code stay the same.* (4) *Free to build and preview; send it with a plan.*
- **Proof points:** the real product (stills made from the dashboard preview, live `/demo`), the 13 templates, the working QR code on Lettie's card. **No testimonials, no savings figures**: none exist yet.
- **Differentiator (to confirm):** invitations that open like a gift (a sealed envelope, a storybook). Check what local competitors offer before saying it is unique; do not claim it.
- **Tone:** Lettie: warm, plain, unhurried. Taglish for the Philippines audience, English for the overseas relatives and the OFW angle.

## 4. Channel strategy

| Channel | Why | Format | Effort | Share of any budget |
|---|---|---|---|---|
| Facebook Page (organic) | Where the Page and its posts already are; free | 6 posts + 1 reel (`COPY.md`) | Low | 0% (time only) |
| **Meta ads** (Facebook + Instagram) | Fastest way to reach a cold audience in 6 weeks | 3 ads: hook, change, occasions | Medium | **~60%** |
| Facebook groups (brides, parents, debut planning) | Where hosts ask "who prints invitations?" | Helpful answers; one honest mention where the group allows it | Medium | 0% |
| TikTok (organic) | Template reveals suit the format | The reel + photo carousel from the cards | Low | 0% |
| Supplier referrals | One photographer or coordinator reaches many hosts | A card with Lettie's QR code, offered to a few suppliers | Medium | 0% (no commission invented here; decide one first) |
| Landing page `/invitations` | Ads need a page that talks about invitations | See dependencies | Medium | n/a |
| Testing reserve and contingency | A winner worth scaling, or a fix | n/a | n/a | ~25% test, 15% contingency |

The goal is not to be everywhere: it is two audiences, three creatives and one number.

## 5. Content calendar

| Week | Content piece | Channel | Owner / notes | Status |
|---|---|---|---|---|
| Before 2 Nov | **Dependencies:** deploy the `/invitations` page and the "An invitation for…" wording (both built, uncommitted); confirm the Invitation occasion is live in production; check Admin → Traffic records a tagged visit | App / site | Ads must not start before these | Open |
| 1 (2–8 Nov) | Launch post (hook A) + Story; submit ads 1 and 2 to Meta for review | Facebook, Meta | Meta reviews first | To do |
| 1 | Ads 1–2 live (Wed 4 Nov) | Meta ads | Audience A: hosts; test Taglish vs English | To do |
| 2 (9–15 Nov) | Post: "Wedding" (Wedding Invitation) | Facebook, TikTok | Answer comments within hours | To do |
| 2 | Reel "Invite, don't print" (after approval, see `COPY.md`) | Facebook, TikTok | Needs a screen recording of the Share box | Draft |
| 3 (16–22 Nov) | Post: "Change the venue, keep the link" (hook B); ad 3 live (occasions) | Facebook, Meta | First read of results Mon 16 Nov: pause losers | To do |
| 3 | Share into 3 relevant groups (where rules allow) | Facebook groups | A helpful post, not an ad | To do |
| 4 (23–29 Nov) | Post: "Christening" and "Debut"; supplier cards to 3 suppliers | Facebook, referrals | Christening needs Sweetheart: say so | To do |
| 5 (30 Nov–6 Dec) | Retell best hook; the "print less" post; email the sign-ups who did not publish (`../EMAIL_SEQUENCES.md`) | Facebook, e-mail | | To do |
| 6 (7–13 Dec) | Last push for January events; **Fri 11 Dec: Christmas Party invitation opens** (14 days before 25 Dec): a short bonus post only | Facebook | Too late for most parties; use it for the Christmas-party hosts who read it | To do |
| After 13 Dec | Write up results; talk to sign-ups; plan Valentine (opens 31 Jan) | n/a | `reports/invitations-2026.md` | To do |

Keep about 20% of the slots open for replies and a quick reaction to what works.

## 6. Content pieces needed

| Asset | Files | Priority |
|---|---|---|
| 4 feed images, 2 stories, link preview | `out/invitation/cards/inv-*.png` (built) | Must |
| Lettie's invitation looks (4 stickers + sheet) | `out/invitation/cards/lettie-invite*.png` (built) | Must |
| Copy: posts, 3 hooks, 3 ads, Taglish | `COPY.md` (built, needs your OK) | Must |
| Tagged links | `links.py` (built) | Must |
| `/invitations` landing page | built 2026-10-28 (`resources/views/invitations.naluz.php`), **not yet deployed** | Must (the ads' destination) |
| Reel / video (20 s) | built: `out/invitation/video/` (approve before posting) | Must |
| Supplier QR card (printable) | not built | Nice |
| Onboarding e-mail for hosts (incl. "guests reply by message") | add to `EMAIL_SEQUENCES.md` | Must for the first customers |
| Birthday Party invitation template | not built | Nice (only if birthday is a real focus) |

## 7. Success metrics

| KPI | Target to test | Where |
|---|---|---|
| **Customers** (paid) | 6 | Admin → Traffic (`invitations-2026`) |
| **Sign-ups** | 40 | Admin → Traffic |
| Pages created from an Invitation template | learn, no target | Admin |
| Cost per sign-up | decide in `ADS_GUIDE.md` step 2 | spend ÷ sign-ups |
| Cost per customer | below about ₱200 to break even on month one of Sweet (before fees); ₱350 for Sweetheart | spend ÷ customers |
| Link CTR, CPC, which hook wins | learn | Ads Manager |

Reporting: look every Monday; decide to pause or move budget at the end of weeks 1 and 3.

## 8. Budget allocation

No figure given. Suggested split of whatever you set: ~60% Meta ads, ~25% testing (a second audience or a new hook), ~15% contingency. Production is
already paid for (the cards are built locally). Pick the daily amount by working back from the cost-per-customer ceiling above.

## 9. Risks and mitigations

| Risk | Mitigation |
|---|---|
| Hosts expect **RSVP** and the app has none | Never mention it; the e-mail tells hosts guests reply by message; consider RSVP as the next product feature: it is the strongest gap |
| "It costs more than printing 50 cards" | Do not claim a saving; sell "no printing / edit later / reaches overseas guests"; collect real quotes before ever comparing |
| Formal families expect paper | The "print less" message: print only the QR code on a card, so paper and link work together |
| Ads land on a page that says nothing about invitations | Build `/invitations` first; until then do not spend |
| Weddings and christenings book months ahead | Expect a slow tail: measure sign-ups, not only same-day purchases |
| Christening and Baby Shower templates need Sweetheart | Say so in those posts; do not lead with a Sweet price for them |

## 10. Next steps

1. **You decide:** budget, the target numbers, whether "birthday" deserves its own template, and whether the site mascot should change everywhere (see the brand review).
2. **Build next:** `/invitations` landing page, the "A letter for…" wording on invitation pages, and (strongly suggested) RSVP.
3. **Approve:** the copy in `COPY.md`, the four mascot looks (please look at them), and the video script before anything is voiced or published.
4. **Run:** follow `../ADS_GUIDE.md` with `links.py` for tracking.
