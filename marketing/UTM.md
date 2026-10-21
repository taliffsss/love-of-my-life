# UTM tracking: where does the traffic come from?

Every link that points at the site from a post, an ad, a bio, an e-mail or a QR code carries four tags. The site records them
(on the landing page, demo, sign-in and sign-up), keeps the **first** campaign that brought a visitor for 30 days, saves it on
the account when they sign up, and shows it at **Admin → Traffic**: visitors, sign-ups, customers and revenue per source,
campaign and ad or post.

```
https://loveofmylife.love/?utm_source=facebook&utm_medium=paid_social&utm_campaign=halloween-2026&utm_content=ad-carousel
```

## The tags

| Tag | Means | Use | Example |
|---|---|---|---|
| `utm_source` | The platform or place the link sits on | lower case, one word | `facebook` `instagram` `tiktok` `youtube` `email` `partner` |
| `utm_medium` | The kind of placement | one of the list below | `social` `paid_social` `bio` `email` `qr` `influencer` `referral` |
| `utm_campaign` | The campaign, with the year | `name-year`, hyphens | `halloween-2026` `christmas-2026` `valentines-2027` `always-on` |
| `utm_content` | Which post, ad or creative | the file or ad name | `hw-hook` `reel-knock` `ad-carousel` |
| `utm_term` | A search keyword (search ads only) | optional | `halloween-gift-for-boyfriend` |

**Mediums:** `social` = an organic post or reel · `paid_social` = a Meta or TikTok ad · `bio` = the link in a profile · `email` = a
newsletter or sequence · `qr` = a printed QR code (one `utm_content` per card or place) · `influencer` = a creator's link (one
`utm_content` per creator) · `referral` = another site (set automatically from the referrer when there are no tags).

## Rules

1. Lower case, no spaces, only letters, digits, `.` `_` `~` `-` (the site cleans anything else and cuts it at 60 characters).
2. Never put a name, e-mail address or any personal data in a tag.
3. Same campaign name everywhere, so the dashboard adds it up. `Halloween 2026`, `halloween_2026` and `halloween-2026` are three campaigns.
4. One `utm_content` per creative, so you can tell which picture or video won.
5. No tags on a link inside the site itself (it would restart the attribution).

## Where the links come from

- **Halloween:** `marketing/social/utm.py` is the one place that knows every placement; the captions in `social/HALLOWEEN.md` use
  `{link:post-1}` and `marketing/make.sh collect` fills them in. `marketing/make.sh links` prints the table.
- **A new campaign:** add its placements to `PLACEMENTS` in `utm.py` (or call `link()` with another `campaign`), write
  `{link:<placement>}` in the caption file and run `make.sh collect`.
- **Meta ads, dynamic version:** instead of one link per ad you can paste this into "URL parameters" of the ad and Meta fills in the
  campaign and ad name: `utm_source=facebook&utm_medium=paid_social&utm_campaign={{campaign.name}}&utm_content={{ad.name}}`.
  Name your Meta campaign `halloween-2026` and the ads `ad-carousel`, `ad-feed`, `ad-story` so they match.

## What is stored (and what is not)

A random visitor id (`lom_v`, first-party cookie, up to 13 months), the tags, the referring website's host, the page and the time. **No IP
address, no device details, no name or e-mail.** The campaign is also saved on the account that signs up. It is described in the Privacy
Policy (section 6). Bots, previews and the pages people make for each other are never counted. A repeat of the same campaign by the same visitor within 30 minutes counts once.

## Reading the dashboard

- **Visitors** are people in the period; **sign-ups** are accounts *created* in the period, with the campaign saved on them; **customers**
  are those accounts that have paid. A rate above 100% means people signed up after visiting before the period.
- Compare **cost per sign-up** and **cost per customer** by dividing what you spent (from Ads Manager) by the sign-ups and customers of the same campaign here. Ads Manager's own numbers say "link clicks"; this dashboard says what happened after the click.
- `direct / none` is traffic with no tag and no referrer: typed addresses, apps that hide the referrer, and untagged links. If it is big, some of your links are missing tags.
