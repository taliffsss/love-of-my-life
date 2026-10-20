# Review of the Terms against standard positions

**Document**: Terms and Conditions v1.0 (`resources/views/legal/terms.naluz.php`) · **Side**: service operator (we draft, users accept)
**Basis**: generic commercial/consumer standards (no organisation playbook was configured) · **Not legal advice.**

Flags: GREEN = fine, YELLOW = a choice to confirm or a fallback exists, RED = needs counsel before launch.

| Clause | Position taken | Flag | Note / fallback |
|---|---|---|---|
| Acceptance (§1) | Using the service = agreement; recipients are only bound by §6, 7, 11, 14 | GREEN | Cross-references (§1, §4, §10, §14, §15) are by number: if sections are renumbered, update them |
| Eligibility (§3) | 16+, parent/guardian under 18, parent pays | **YELLOW→RED if not enforced** | A minor’s contract is voidable. The app has no age check. Fallback: raise to 18 for purchase/publish and keep 16–17 for free preview |
| Payments (§4) | Pesos, providers named, auto-renew explained, cancel in dashboard | GREEN | State taxes once the BIR position is known. “GCash renews when you approve” matches the code |
| Refunds (§4) | None once started, 30-day claim for errors/legal rights | YELLOW | Consider a 7-day money-back; it lowers chargebacks and complaints |
| One-time “Forever” (§4, §10) | For as long as the service runs; 60 days’ notice and refund on closure | YELLOW | Without the exit clause, “Forever” invites an unfair-terms argument |
| Plan end (§4) | Page rests, nothing deleted; may delete after a period and a reminder | YELLOW | Fix the period (e.g. 12 months) and make sure a reminder can actually be sent (no mailer yet) |
| User content (§5) | User keeps ownership; narrow hosting licence; user warrants rights and consent | GREEN | Strong consent wording for third parties in photos; matches Policy §9 |
| Lock disclaimer (§5) | Private by design, not guaranteed | GREEN | Protects against “you promised it was private” claims |
| Rules (§6) | Illegal, sexual/minors, non-consensual intimate images, harassment, doxxing, hate, scams | GREEN | Covers RA 9995, RA 11930/9775, RA 11313, RA 10175 themes without citing statutes (plain language) |
| Reports and takedowns (§7) | E-mail; act fast on risk; owner can ask for review; look only on report/security/legal | YELLOW | Promise vs. tooling: no admin takedown tool yet. Add the tool (see Risk R1) or soften “straight away” |
| Third parties (§8) | No responsibility for providers | GREEN | |
| Our IP (§9) | Software, templates, Lettie, logo are ours | GREEN | Do the IPOPHL search (Risk R10) |
| Suspension (§10) | We may suspend for breach/legal/chargeback; warn when possible; refund if without cause | GREEN | Matches `users.status` (active/deactivated/suspended) |
| Availability (§11) | No uptime promise; keep your own copy | GREEN | |
| Warranty (§12) | As-is, subject to non-waivable consumer rights | GREEN | The carve-out keeps the clause enforceable |
| Liability (§13) | Excludes indirect loss; cap = greater of 12 months’ payments and ₱1,000; fraud/wilful misconduct carved out | YELLOW | Courts may refuse to enforce caps for gross negligence; ₱1,000 floor is a business choice (decision 4) |
| Indemnity (§15) | User compensates us for their content/breach | YELLOW | Broad in a consumer contract; fallback: limit to third-party claims arising from the user’s content |
| Changes (§16) | 14 days’ notice for matters that matter; refund if you reject | GREEN | There is no mailer, so “notice” is in-app; add the banner |
| Governing law (§18) | Philippine law; 30 days’ talk first; “proper courts” | YELLOW | Name a venue (decision 7); no arbitration or class-waiver (unfriendly in consumer contracts) |
| Contact (§17) | Operator, address, e-mail from config | **RED until set** | Defaults are placeholders (`LEGAL_*`); an unreal contact breaks both documents |
| General (§19) | Severability, no waiver, assignment, entire agreement, English | GREEN | Add Filipino summary if you want to reach users who prefer it |

## Top issues

1. **Contact and operator are placeholders** until `LEGAL_*` are set (RED until done).
2. **Minors**: the text says what the app does not enforce; decide between enforcing it or raising the age for paying.
3. **Takedown promise vs. tooling** (§7): build the admin tool or soften the wording.

## Redlines already agreed as fallbacks

- **Eligibility fallback**: “You must be at least 18 to buy a plan or publish a page. If you are 16 or 17 you may build and preview pages with a parent or legal guardian’s permission.”
- **Refund fallback**: “You may cancel within 7 days of your first payment for a plan and ask for a full refund if the page has not been opened by anyone else.”
- **Indemnity fallback**: “You will compensate us for claims by third parties that arise from your content or your breach of section 5 or 6.”
