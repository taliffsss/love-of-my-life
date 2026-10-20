# Legal risk assessment: Love of My Life

**Date**: 2026-10-20 · **Prepared by**: AI assistant, for review by counsel · **Privileged**: no (not prepared by or for a lawyer)
**Matter**: launching a Philippine consumer web service where people upload photos and letters (often about another person), lock pages
with a question, and pay for plans; audience includes OFWs, couples and ages 16+; sign-in with Google/Facebook, Google Analytics,
cloud storage, PayMongo/Xendit payments, YouTube-embedded songs.

Scoring: severity (1–5) × likelihood (1–5): 1–4 green, 5–9 yellow, 10–15 orange, 16–25 red.

## Summary

No red risks. Three orange risks drive the design of the Terms and Policy: **harmful content hidden behind a lock** (R1), **minors** (R2)
and **other people’s personal data** (R3). All can be reduced a lot with what is already written and a few open actions below.

## Risk register

| ID | Risk | S | L | Score | Level | Owner | Status |
|---|---|---|---|---|---|---|---|
| R1 | Pages used for non-consensual intimate images, harassment, stalking, blackmail or threats; the lock means we cannot see them without a report | 5 | 3 | 15 | ORANGE | Operator | Mitigated in text (Terms §6, §7); report channel = e-mail only; **no tooling to take a page down except admin user status** |
| R2 | Minors: 16–17-year-olds use and pay; contracts by minors are voidable; children’s images; any sexual content involving minors | 5 | 2 | 10 | ORANGE | Operator | Mitigated in text (age gate 16+, parent permission under 18, zero tolerance); **no age check or parent flow in the app** |
| R3 | Personal data of third parties (recipients) and users under the Data Privacy Act: notice, lawful basis, rights process, breach, NPC | 4 | 3 | 12 | ORANGE | Operator/DPO | Policy written; **NPC registration need, DPO appointment and a rights/breach procedure still to confirm** |
| R4 | Payments and consumer law: auto-renewal disclosure, refunds, taxes and invoices, card/e-wallet data | 3 | 3 | 9 | YELLOW | Operator | Terms §4 written; **BIR registration, receipts/invoices and the refund choice to confirm** |
| R5 | Intellectual property: users’ photos/music, our templates and mascot; “Lettie” and “Love of My Life” as marks | 3 | 3 | 9 | YELLOW | Operator | Terms §5, §9; songs are YouTube embeds (no hosting). **IPOPHL search not done** |
| R6 | Cookies and analytics (Google Analytics) without a consent banner | 2 | 3 | 6 | YELLOW | Operator | Disclosed in Policy §6; GA kept off private pages and admin; **no banner/opt-out control in the app** |
| R7 | Cross-border processing (hosting, Google, payment providers) | 2 | 3 | 6 | YELLOW | Operator | Policy §5 discloses; **check hosting region and provider data-processing terms** |
| R8 | Security incident (account takeover, leaked photos/letters, payment references) | 4 | 2 | 8 | YELLOW | Operator | Policy §11; hashing, encrypted payment refs, rate limits. **No incident runbook; no e-mail verification or password reset** |
| R9 | Terms not binding enough (notice only at sign-up) | 2 | 3 | 6 | YELLOW | Operator | Links at sign-up and footer. **Recommended: a required checkbox and a stored acceptance version/date** |
| R10 | Domain and brand: `loveofmylife.love` registration, trademark conflicts | 3 | 2 | 6 | YELLOW | Operator | **Confirm registration; IPOPHL search for both names** |

## Contributing and mitigating factors

- **Raises risk:** content is personal and emotional; breaches would be painful; locked pages hide content from us; minors in the audience;
  payments through e-wallets are popular with younger users; a single operator with no legal entity stated yet.
- **Lowers risk:** nothing is public by default (hidden from search, locked, no feed, no comments, no messaging between users, no ads);
  card data never touches our servers; payment references are encrypted; free preview before paying; clear ownership and takedown route.

## Open decisions (defaults used in the documents)

| # | Decision | Default in the documents | Why it matters |
|---|---|---|---|
| 1 | Minimum age | 16; under 18 with a parent/guardian, who must pay | PH age of majority is 18; the audience requirement was “16 and above” |
| 2 | Refunds | **None** (owner’s decision): payments are final; only a charge taken by mistake is reversed (30-day window); non-waivable legal rights untouched | A flat “no refund” is common for digital goods but is not enforceable against rights that cannot be waived; keep the mistake-reversal and answer chargebacks with the order record |
| 3 | Closing the service | 60 days’ notice, export, pages stay online to the end of the notice, no new recurring charges; **no refund** | With no refund, “Forever” depends on the notice-and-export promise; counsel should say whether that is fair enough |
| 4 | Liability cap | Greater of 12 months’ payments and ₱1,000 | Typical consumer-service cap; cannot exclude fraud/wilful misconduct |
| 5 | Who is the operator | “Love of My Life”, address “Philippines” (`LEGAL_OPERATOR_NAME`, `LEGAL_ADDRESS`) | A policy needs a real controller and contact; **set them before launch** |
| 6 | Contact mailbox | `hello@loveofmylife.love` (`LEGAL_CONTACT_EMAIL`) | **Create it** and make sure someone reads it |
| 7 | Dispute venue | “proper courts of the Philippines” after 30 days’ notice | Name a city (where you are based) once you know |
| 8 | Content review | Only on report/security/legal request | Staff reading private letters is the largest privacy surprise; keep it so |

## Recommended actions (in order)

1. Set `LEGAL_OPERATOR_NAME`, `LEGAL_ADDRESS`, `LEGAL_CONTACT_EMAIL` (and `LEGAL_DPO_NAME`); create and monitor the mailbox. *Before launch.*
2. Have a Philippine lawyer review `/terms`, `/privacy` and decisions 1–4. *Before taking payments.*
3. Check Data Privacy Act obligations: appoint a DPO (even if registration is not required), decide whether the NPC registration threshold applies
   (check current NPC rules), keep a record of processing and a breach procedure (NPC and affected people within 72 hours of knowledge). *Before launch.*
4. Register for tax with the BIR as needed and issue receipts/invoices for payments (check the current invoicing/e-invoicing rules). *Before taking payments.*
5. Add a required “I agree” checkbox on sign-up and store the version and time accepted (a small migration). *Soon.*
6. Build a **takedown tool** in the admin (hide a page by report, keep the evidence, notify the owner) and a **user data export** and **self-service account deletion**; today all three are manual. *Soon.*
7. Add a cookie/analytics notice with an opt-out (or move analytics to consent). *Soon.*
8. Run a trademark search at IPOPHL for “Love of My Life” and “Lettie”; confirm the domain is registered. *Before paid advertising.*
9. Add e-mail verification and password reset (security and account recovery). *Soon.*
10. Write a one-page incident runbook (who decides, who tells NPC and users, what to preserve). *Before launch.*

## Residual risk after 1–10

R1 ORANGE→YELLOW (6), R2 ORANGE→YELLOW (8), R3 ORANGE→YELLOW (6); the rest GREEN/YELLOW. Review again after launch, after the first report,
after any breach, and whenever a payment method, data type or country is added.

## Outside counsel

Engage a Philippine lawyer now for the review in step 2; again for any NPC inquiry, a report involving a minor or a threat to a person,
a regulator or police request, or a claim of infringement.
