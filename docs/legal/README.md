# Legal pack

Internal documents behind the public **Terms and Conditions** (`/terms`) and **Privacy Policy** (`/privacy`). The public text lives in
`resources/views/legal/`; who runs the service and the contact are set by `LEGAL_*` in `.env.production`.

| File | What it is |
|---|---|
| `RISK_ASSESSMENT.md` | Legal risks of the platform, scored, with what the documents and the app already do and what is still open |
| `TERMS_REVIEW.md` | A clause-by-clause review of the Terms against standard consumer-service positions, with the choices made and fallbacks |
| `RESPONSE_TEMPLATES.md` | Ready replies: privacy requests (access, correction, deletion, refusal, extension), takedown reports, breach notice, minors |

**These were drafted by an AI assistant for a Philippine online service. They are not legal advice.** Have a Philippine lawyer read the
Terms, the Privacy Policy and the open decisions before you launch or take money, and re-read them whenever the product changes
(payments, new data, new countries, an app in the stores).

**When you change the product, change the documents.** `tests/Http/LegalPagesTest.php` checks the facts the Policy states against the code
(cookie name and lifetime, no prices in the Terms), but it cannot know that you added, say, an e-mail provider or a new analytics tool.
