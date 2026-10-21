# SEO audit: loveofmylife.love

Made with the `seo-audit` structure. **Type:** full-site audit of what exists today. **Method:** I read the code and fetched the pages of the
running copy of the site (landing, demo, sign-up, legal, `robots.txt`, `sitemap.xml`). **What I did not have:** no SEO tool (Ahrefs, Semrush,
Search Console) is connected, so **I measured nothing about rankings, search volume, backlinks or speed**. Keyword "difficulty" and "volume"
below are my judgement and must be checked before you rely on them. For competitors I only looked at one site (2-luv.com) once; I did not
run a real competitor comparison.

## Executive summary

Overall: **a small, clean foundation with one real bug and one big gap.** The site has one page worth ranking (the landing page), correct
`robots.txt`, a sitemap, a canonical URL, Organization and WebSite structured data and a mobile viewport. **The bug:** the sitemap lists two pages
that are marked `noindex`. **The gap:** there is nothing to rank for except the brand: no content pages, a generic title and one H1, and the
seasonal templates (Halloween, Christmas, Valentine) are hidden most of the year, so nothing seasonal can accumulate ranking.
Top three priorities: (1) fix the sitemap/noindex conflict; (2) build permanent seasonal pages; (3) give the landing page a title and
headings that carry the words people search.

## Keyword opportunities (hypotheses: volume and difficulty not measured)

| Keyword | Est. difficulty (judgement) | Opportunity | Current ranking | Intent | Content type |
|---|---|---|---|---|---|
| halloween love letter ideas | Low – moderate | High | Not measured | Informational | Blog post (drafted) |
| halloween message for boyfriend / girlfriend | Moderate | High | Not measured | Informational | Blog / list |
| halloween gift for boyfriend philippines | Moderate | High | Not measured | Commercial | Seasonal landing page |
| digital love letter | Moderate – hard | Medium | Not measured | Commercial | Landing (existing) |
| online love letter / secret love letter | Moderate | Medium | Not measured | Commercial | Landing + blog |
| monthsary gift ideas | Moderate | High (Philippine term) | Not measured | Commercial | Blog + landing |
| monthsary message for boyfriend | Moderate | High | Not measured | Informational | Blog |
| gift for long distance boyfriend / OFW girlfriend | Moderate | High | Not measured | Commercial | Blog / audience page |
| valentine's day message for boyfriend | Hard (crowded) | Medium | Not measured | Informational | Seasonal page (opens 31 Jan) |
| christmas love letter / christmas message for girlfriend | Moderate | Medium | Not measured | Informational | Seasonal page (opens 11 Dec) |
| love letter with a question / password protected love letter | Low | Medium | Not measured | Commercial | Feature page |
| digital invitation philippines / debut invitation online | Moderate | Medium | Not measured | Commercial | Invitation page |
| anniversary gift ideas for him / her | Hard | Low – medium | Not measured | Informational | Blog |
| qr code love letter | Low | Medium | Not measured | Informational | Feature page |
| how to write a love letter | Hard | Low (very crowded) | Not measured | Informational | Blog |
(Further question-style ideas for later: "what to write in a monthsary card", "halloween date ideas for couples".)

## On-page issues

| Page | Issue | Severity | Recommended fix |
|---|---|---|---|
| `/` | Title "Love of My Life · A love letter they unlock with a word only they know" (77 characters, cut in results; no searchable words) | High | "Digital love letter with a secret question | Love of My Life" (≈55) |
| `/` | One H1 and five H2s, written for people not for search ("Three steps, one evening.", "Good to know") | Medium | Add keyword-bearing H2s ("Make a digital love letter in three steps", "Templates for monthsary, anniversary, birthday and invitations") |
| `/` | No `<img>` on the page (all art is CSS): nothing for image search, no alt text to optimise | Low | Add a real image or two (the sample phone, the template theater) with descriptive alt text and a social share image check |
| `/` | Meta description is good (≈125 characters) | — | Keep |
| `/` | FAQ is on the page but has no FAQ structured data | Medium | Add `FAQPage` JSON-LD for the eight questions |
| `/` | Pricing has no `Offer` structured data | Low | Add `Product`/`Offer` for the three plans |
| `/demo` | `noindex` (correct) **but listed in the sitemap** | **High** | Remove from `sitemap.xml` or make it indexable |
| `/register` | `noindex` (correct) **but listed in the sitemap** | **High** | Remove from `sitemap.xml` |
| `/terms`, `/privacy` | Indexable, low priority | Low | Fine (keep `priority` low) |
| all | The same `og:image` for every page | Low | Fine for now |

## Content gap recommendations

| Topic | Why | Format | Priority | Effort |
|---|---|---|---|---|
| **Permanent seasonal hubs** (`/halloween`, `/christmas`, `/valentines`) | The templates are offered only two weeks a year, so a page that exists all year can collect links and rank before the season; in season it carries the product, out of season it says "opens on …" and collects e-mails | Landing page per season | **High** | Moderate |
| Halloween love letter ideas | Seasonal, fits the campaign; drafted in `content/halloween-love-letter-ideas.md` | Blog post | High | Quick win (copy exists) |
| Monthsary pages: gift ideas, messages | A Philippine phrase with clear intent and no product competitor in your niche that I could verify | Blog + landing | High | Moderate |
| Long-distance / OFW | Strong audience fit | Audience page + blog | High | Moderate |
| Feature explainers: "love letter with a password", "QR code love letter" | Low competition, matches the product exactly | Feature pages | Medium | Moderate |
| Examples gallery (real pages, with permission) | Social proof and unique content | Gallery | Medium | After the first customers |
| Funnel: awareness (blog) → consideration (templates, comparison) → decision (pricing) | Today there is only decision-stage content | Plan | Medium | Ongoing |

## Technical checklist

| Check | Status | Details |
|---|---|---|
| `robots.txt` | **Pass** | Allows `/`, blocks `/p/`, `/dashboard`, `/admin`, `/api/`, lists the sitemap |
| `sitemap.xml` | **Fail** | Lists `/demo` and `/register`, both `noindex`; Google reports this as "submitted URL marked noindex" |
| Canonical URL | **Pass** | On the landing page; not on the other pages (fine, they are not duplicates) |
| `noindex` on private pages | **Pass** | `/p/`, dashboard, admin, API and the demo carry it |
| HTTPS | **Pass (production)** | Behind the reverse proxy; `Secure` cookies |
| Mobile viewport | **Pass** | `width=device-width, viewport-fit=cover` |
| Structured data | **Warning** | Organization and WebSite only; add FAQPage and Offer |
| Titles / descriptions | **Warning** | Landing title too long and generic; other pages not checked in detail |
| Image alt text | **Warning** | No images on the landing page |
| Internal linking | **Warning** | The landing page links to the demo and sign-up; there is no content to link to |
| Page speed / Core Web Vitals | **Not measured** | Run Lighthouse or PageSpeed Insights; the landing page now loads two extra scripts and animations, so check LCP and layout shift on a phone |
| Broken links | **Not measured** | Run a crawl once the seasonal pages exist |
| Indexation | **Not measured** | Add the site to Search Console and check Coverage |
| Seasonal templates hidden outside their window | **Warning** | Intentional; it means no seasonal URL stays indexable unless a permanent page exists (see gaps) |
| Analytics | **Pass** | GA4 on the landing, sign-in, sign-up and dashboard; UTM tracking now in the admin (`../UTM.md`) |

## Competitor comparison (limited)

I fetched one site, **2-luv.com**, once. It sells digital love letters, interactive gifts and eCards, offers a free preview, charges a one-time price,
lists a few hundred words of FAQ, has a blog and about 14 occasion pages in its footer. I did **not** measure its rankings, traffic or backlinks.
What it suggests (not proves): many occasion landing pages and a blog are how a site like this gets found.

| Dimension | loveofmylife.love | 2-luv.com (one look) | Winner |
|---|---|---|---|
| Indexable pages | ~3 (landing, terms, privacy) | Many (occasions, blog) | 2-luv.com |
| Blog / content | None | Present | 2-luv.com |
| Occasion/seasonal pages | Hidden in the app | Linked from the home page | 2-luv.com |
| Product interactivity | Strong (13 templates, the sample, the 3D theater) | Strong | Tie, not an SEO factor |
| Structured data | Organization, WebSite | Not checked | — |
| Backlinks, traffic, speed | Not measured | Not measured | — |

## Prioritised action plan

**Quick wins (this week)**
1. Remove `/demo` and `/register` from `sitemap.xml` (`SeoController::sitemap`). *High impact, 10 minutes.*
2. Rewrite the landing title and description (see above). *High, 15 minutes.*
3. Add `FAQPage` JSON-LD to the landing page. *Medium, 1 hour.*
4. Add the site to Google Search Console and Bing Webmaster Tools; submit the sitemap. *High, 30 minutes.*
5. Run PageSpeed Insights on mobile for the landing page; fix anything it flags. *Medium, 1 hour.*

**Strategic investments (this quarter)**
1. **Seasonal hubs** that exist all year (`/halloween`, `/christmas`, `/valentines`), with the demo, an idea list and, out of season, a "template opens on …" note. *High, a few days.*
2. A small blog with the monthsary, long-distance and seasonal posts (start from `content/halloween-love-letter-ideas.md`). *High, ongoing.*
3. Feature pages ("love letter with a password", "QR code love letter"). *Medium.*
4. After the first customers: an examples gallery, with permission. *Medium.*
5. Link building the honest way: share the seasonal hubs in the communities in `social/CONTENT_PLAN.md`; ask partners (wedding suppliers, OFW groups) to link. *Medium.*

Each of the quick wins can be built on request (the sitemap fix is a few lines).
