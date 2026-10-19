# Deploying to your own server

Since 3.0 this repository is a product: people sign up, build love pages in a dashboard and publish them with a plan.
Every page is served at `/p/<id>`; a **Sweetheart** or **Forever** page can also claim its own address, `<name>.<SAAS_DOMAIN>`. The
original single page (`jinelin-basela.markanthonynaluz.com`) becomes one of those pages, on its old domain
(see [Moving the original site into the product](#moving-the-original-site-into-the-product)).

## What the server needs

| Thing | Why |
|---|---|
| `APP_URL` | the product's address: landing page, sign-in, dashboard (`https://love.markanthonynaluz.com`) |
| `SAAS_DOMAIN` | the domain page addresses live under (defaults to the host of `APP_URL`) |
| DNS `A` record for `SAAS_DOMAIN` **and** a wildcard `A` record `*.SAAS_DOMAIN` | every claimed name works immediately, with nothing to add per page |
| A **wildcard** TLS certificate for `*.SAAS_DOMAIN` | Let's Encrypt only issues wildcards through the DNS-01 challenge (see below) |
| A database | SQLite in the `storage` volume by default (`storage/database.sqlite`); MySQL/PostgreSQL work too (`DB_*`) |
| DigitalOcean Spaces (`SPACES_*`) | where photos are stored; without it photos go to `public/storage` (development only) |

How a request finds its page: `App\Http\Middleware\ResolveSiteHost` runs before routing. `SAAS_DOMAIN`, `www.`,
`localhost` and IP addresses are the product. `<name>.SAAS_DOMAIN` is the Sweetheart or Forever page that claimed `<name>` (404 when
none did). Any other host is looked up as a page's custom domain (set by an admin, e.g. by the import below), otherwise
it is the product. On a page's own host only `/`, `/unlock`, `/robots.txt` and `/sitemap.xml` exist: the dashboard and
sign-in are never served there.

## Docker Swarm + Traefik

Same pattern as hris-api and dink: a Swarm stack (`deploy/docker-stack.yml`) with Traefik labels, the shared external
`app_network`, the `redirect-to-https@file` middleware from your main Traefik stack. The image (`Dockerfile`) runs nginx
+ PHP-FPM under Supervisor; `deploy/entrypoint.sh` runs pending migrations at start (`RUN_MIGRATIONS=false` to skip).

### One-time setup

The product lives at `love.markanthonynaluz.com` (`SAAS_DOMAIN`), so Sweetheart and Forever pages are
`<name>.love.markanthonynaluz.com`.

1. **DNS** (DigitalOcean, domain `markanthonynaluz.com`): two `A` records at the server running Traefik
   (`152.42.252.125`, the same address as `jinelin-basela`): `love` and the wildcard `*.love`. With the API:
   ```bash
   for name in love '*.love'; do
     curl -s -X POST https://api.digitalocean.com/v2/domains/markanthonynaluz.com/records \
       -H "Authorization: Bearer $DO_AUTH_TOKEN" -H "Content-Type: application/json" \
       -d "{\"type\":\"A\",\"name\":\"$name\",\"data\":\"152.42.252.125\",\"ttl\":1800}"
   done
   dig +short love.markanthonynaluz.com anything.love.markanthonynaluz.com   # both print 152.42.252.125
   ```
   The token needs only the `domain:read`, `domain:create` and `domain:delete` scopes.
2. **Wildcard certificate.** The existing `letsencrypt` resolver uses the HTTP challenge, which cannot issue
   `*.love.markanthonynaluz.com`. Add a DNS-challenge resolver named `letsencrypt-dns` to the main Traefik stack:
   ```yaml
   # Traefik static configuration (command flags or traefik.yml)
   - "--certificatesresolvers.letsencrypt-dns.acme.email=you@example.com"
   - "--certificatesresolvers.letsencrypt-dns.acme.storage=/letsencrypt/acme-dns.json"
   - "--certificatesresolvers.letsencrypt-dns.acme.dnschallenge.provider=digitalocean"
   # and, on the Traefik service only (never in the app's .env.production): DO_AUTH_TOKEN=<the same scoped token>
   ```
   The routers that use it are already in `deploy/docker-stack.yml` (`lom-saas` and `lom-saas-http`, Traefik v3
   `HostRegexp` syntax). Traefik asks for the certificate the first time the stack is deployed with them; if the resolver
   has another name on your server, change `tls.certresolver` there. In that file a literal `$` is written `$$`:
   `docker stack deploy` refuses a lone `$` ("invalid interpolation format"). The existing `jinelin-basela` router keeps
   serving the old domain.
3. **Server folder** (default `/opt/love-of-my-life`):
   ```bash
   mkdir -p /opt/love-of-my-life/deploy
   cp deploy/.env.production.example /opt/love-of-my-life/deploy/.env.production   # then fill it in
   chmod 600 /opt/love-of-my-life/deploy/.env.production
   ```
   Fill in `APP_URL` and `SAAS_DOMAIN` (the product's new domain, **not** `jinelin-basela.markanthonynaluz.com`, which
   becomes a page), `APP_KEY`, `JWT_SECRET`, the `SPACES_*` values and `PAYMENTS_MANUAL_INSTRUCTIONS`.
   Generate the keys with `php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"` (`APP_KEY`) and
   `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"` (`JWT_SECRET`). Write values **without quotes**.
   Keep `PAYMENTS_DRIVER=none` in production (`fake` is refused there).
4. **GitHub** (repo → Settings), as in `.github/workflows/release.yml`:
   - Secrets: `DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN`; for deploys also `DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_USER`.
   - Variables: `DOCKER_IMAGE` (default `taliffsss/jinelin-basela`), `DEPLOY_PATH` (default `/opt/love-of-my-life`),
     `AUTO_DEPLOY` (`true` turns the deploy job on), `SITE_URL` (checked after each deploy; set it to the product's
     `APP_URL`. `/api/ping` answers on every host, page hosts included).
   - Environment `production` with required reviewers, so every deploy needs a human approval.

### Releasing

```bash
git tag v3.0.0 && git push origin v3.0.0
```

(or Actions → Release → Run workflow with the tag). The pipeline runs the tests and a Docker smoke test (migrations,
landing page, demo, subdomain 404s, private files), pushes the image, creates the GitHub Release and, if
`AUTO_DEPLOY=true` and you approve the `production` environment, rolls the stack. A tag with a hyphen (`v3.0.0-rc.1`) is
a pre-release: no `latest`, no deploy.

### Manual build and deploy

```bash
docker build -t taliffsss/jinelin-basela:3.0.0 .
docker push taliffsss/jinelin-basela:3.0.0
# on the server, in /opt/love-of-my-life (with deploy/docker-stack.yml and deploy/.env.production):
LOM_IMAGE=taliffsss/jinelin-basela LOM_VERSION=3.0.0 docker stack deploy -c deploy/docker-stack.yml --with-registry-auth jinelin
docker service logs -f jinelin_web
```

After editing `.env.production`, run `docker stack deploy` again: Swarm copies the env file into the service definition.

### Notes

- `storage/` is a named volume (`lom_storage`): the SQLite database, logs and sessions survive redeploys. **Back up the
  database** (accounts, pages, orders), e.g. daily from cron:
  `docker exec $(docker ps -q -f name=jinelin_web) php bin/backup-db.php` writes a consistent copy to
  `storage/backups/`; copy it off the server. Photos are in Spaces.
- **Empty the photo trash**, e.g. daily from cron, next to the backup:
  `docker exec $(docker ps -q -f name=jinelin_web) php bin/empty-trash.php`. Deleted photos' files are normally removed
  at once; this retries any that Spaces refused or that a cut-off request left behind (it exits 1 while some still
  fail, so cron mails you).
- One replica: SQLite in a volume is fine for one container. Before scaling out, move to MySQL/PostgreSQL (`DB_*`).
- Traefik terminates TLS; nginx passes `HTTPS=on` to PHP when it sees `X-Forwarded-Proto: https`, so cookies are
  `Secure` and HSTS is sent (with `includeSubDomains`, which the wildcard certificate covers).
- No health checks (neither Docker's nor Traefik's), on purpose: see 2.2.1 in the changelog. `/api/ping` is never
  rate-limited.
- Rollback: deploy the previous image. 3.0's migrations only add tables, so 2.x ignores them; but 2.x does not know about
  accounts or pages, so anything made in the dashboard is invisible until you roll forward again.

## Moving the original site into the product

The old single page (letter, song, photos, the `yam` answer) becomes a Forever page owned by your account and keeps its
domain. Run this once, after the first 3.0 deploy (until then that domain shows the landing page):

```bash
docker exec -it $(docker ps -q -f name=jinelin_web) php bin/import-legacy.php \
  --email=you@example.com --domain=jinelin-basela.markanthonynaluz.com --answer=yam --name="Your Name"
```

It reads `config/site.php`, `storage/app/{carousel,letter,song}.json` and `public/photos`, creates your account if it does
not exist (the random password is printed once), and serves the page on that domain behind the same answer, in the
original Classic layout and Rose colours. Then sign in and manage it like any other page. Running it twice is refused.

What changes for that page: the answer is now checked on the server and the photos and letter are sent only after it,
so the image sitemap of 2.x is gone (the photos are no longer public). The page stays indexable (`searchable`), and the
in-page edit buttons are replaced by the dashboard.

## Payments

There is no online checkout yet (`PAYMENTS_DRIVER=none`). Choosing a plan in the dashboard creates an order with a
reference (e.g. `LM-7KQ2-9XDA`) and shows `PAYMENTS_MANUAL_INSTRUCTIONS`. When the money arrives:

```bash
docker exec -it $(docker ps -q -f name=jinelin_web) php bin/grant-plan.php LM-7KQ2-9XDA --ref="GCash 1234567890"
# no order yet? php bin/grant-plan.php --page=<page id from the URL> --plan=sweetheart --period=month --ref=...
#   periods: month or year (Sweet, Sweetheart), once (Forever)
```

The plan starts at once: a month or a year added to any time left (Jan 31 + 1 month is the end of February), or, for
Forever, never expires. Monthly plans are paid again each month the same way until online recurring billing exists
(see `marketing/PRICING.md`). Running it twice
changes nothing. Prices and limits are in `config/saas.php`. For testing, `PAYMENTS_DRIVER=fake` adds a "pay (test)"
button; it refuses to run with `APP_ENV=production`.

### GCash, PayMaya and cards, and subscriptions (PayMongo, the default)

`PAYMENTS_DRIVER=auto` (the default) uses PayMongo as soon as `PAYMONGO_SECRET_KEY` is set. With `PAYMONGO_PUBLIC_KEY` as
well, the order page lists **GCash, PayMaya, Credit card and Mastercard**; choosing one opens its form beneath it:

| | What the customer does | Subscription |
|---|---|---|
| GCash, PayMaya | enters their mobile number, then approves the payment in the wallet | *Subscribe* is selected by default; *One-time payment* is the other choice |
| Credit card, Mastercard | enters the card on the page | always a subscription |

A plan bought once (Forever) is always one-time. A renewal always continues its subscription. The card number goes from the
browser straight to PayMongo with the public key (the form's fields have no `name`, so it is never posted here; CSP
`connect-src` allows `api.paymongo.com`); this server only starts the payment and learns whether it was paid.

The order page waits for the payment: PayMongo's `payment.paid` webhook credits the order (register
`<APP_URL>/api/webhooks/paymongo` for `payment.paid` and `checkout_session.payment.paid`), and while the page waits it also
asks PayMongo every few seconds, so it works before the webhook is set up. A customer who changes method starts another
payment; paying an earlier one still reaches the order.

**Who charges a subscription, by method** (docs.paymongo.com, Subscriptions):

- **Cards and PayMaya: PayMongo charges them automatically** each period, once you set `PAYMONGO_SUBSCRIPTIONS=true`.
  PayMongo support has to enable Subscriptions on your account first (and Card Vaulting for cards), so it is off by default.
  This app starts the subscription (customer, plan, subscription; the plan and customer are made once and reused), the
  customer pays the first invoice, and from then on it only asks PayMongo about the subscription (hourly by
  `php naluz schedule:run`, and whenever the owner opens the dashboard): each new paid invoice extends the page, and a
  subscription PayMongo cancels or gives up on ends here. PayMongo's subscription webhooks are not documented, so this is polling.
- **GCash cannot be charged again by itself** (PayMongo does not list it as recurring), and neither can anything while
  `PAYMONGO_SUBSCRIPTIONS` is off. Those subscriptions are *manual*: three days before the page expires a renewal order is
  made, the dashboard shows "Renewal due: pay …", and the customer approves each payment. The pages say which kind a
  subscription is.
- **Unsubscribing:** the dashboard lists every subscription with an *Unsubscribe* button (and the page's Plan section has
  one). For an automatic subscription PayMongo is told to stop first; if that fails the subscription stays on and the
  customer is told, so it is never shown as cancelled while it can still be charged. The time already paid stays.

I could not try any of this against live PayMongo here; run it with sandbox keys first. The PayMongo response fields it reads
(`next_action.redirect.url`, `latest_invoice.payment_intent.id`, `latest_invoice.status`, subscription `status`) are the ones in
its documentation.

### Payment data is encrypted

What identifies a payment at the provider is stored **encrypted** in the database (XChaCha20-Poly1305, with `APP_KEY`) and is
decrypted when the app reads it: the provider's payment, subscription, invoice and customer ids, and the pages that approve a
payment (`orders.provider_ref`/`checkout_url`, `payment_attempts.provider_ref`/`redirect_url`/`subscription_ref`,
`subscriptions.provider_ref`/`last_invoice_ref`, `provider_refs.ref`). Card numbers and phone numbers are never stored. The
amounts, status, plan and the order reference stay readable on purpose: they are what the dashboards add up and what
customers quote.

- **An encrypted id cannot be searched or made unique**, so each provider id also has a *blind index* (an HMAC of it under a
  key derived from `APP_KEY`): webhooks find their payment by it. On the admin Payments page a provider id is therefore found
  by the whole id, not a part of it.
- **Back up `APP_KEY` with the database.** Without it these ids cannot be read, and a webhook can no longer find its payment.
- **Rotating `APP_KEY`:** put the old key in `APP_PREVIOUS_KEYS` (comma separated), set the new `APP_KEY`, and run
  `php bin/reseal-payments.php` at once (until then webhooks cannot find payments sealed under the old key). It stops
  without changing anything if a value cannot be read. When it reports success the old key can go.
- The migration that introduced this seals the values already in the database.

### PayMongo or Xendit (hosted checkout)

Both are built in and off until you choose one with `PAYMENTS_DRIVER` (they have not been tried against the live
providers; test with each one's sandbox keys first).

| | PayMongo | Xendit |
|---|---|---|
| `PAYMENTS_DRIVER` | `paymongo` | `xendit` |
| Keys in `.env` | `PAYMONGO_SECRET_KEY`, `PAYMONGO_WEBHOOK_SECRET` | `XENDIT_SECRET_KEY`, `XENDIT_CALLBACK_TOKEN` |
| Webhook URL to register | `<APP_URL>/api/webhooks/paymongo`, event `checkout_session.payment.paid` | `<APP_URL>/api/webhooks/xendit`, Invoices paid and expired |
| Checks | `Paymongo-Signature` (HMAC-SHA256, test vs live) | `x-callback-token` |

Choosing a plan creates the order, opens a checkout at the provider and sends the customer there. The order turns paid
(and the plan starts) only when the provider's webhook arrives and checks out: signature or token, our order reference,
the checkout id we stored, and the exact amount. A wrong amount is logged and not credited. Delivering a webhook twice
changes nothing. If the provider is down the customer is told and the order stays pending. The checkout host is added
to the page's `form-action` CSP for the active provider only. `/admin/payments` lists every order and its status.

## Without Docker (bare metal)

Requirements: PHP 8.2+ (`mbstring`, `openssl`, `sodium`, `fileinfo`, `curl`, `pdo_sqlite` or your database's driver),
Composer, nginx or Apache.

```bash
git clone https://github.com/taliffsss/love-of-my-life.git /var/www/love-of-my-life
cd /var/www/love-of-my-life
composer install --no-dev --optimize-autoloader
cp .env.example .env
php naluz key:generate --jwt           # fills APP_KEY and JWT_SECRET
# edit .env: APP_URL, SAAS_DOMAIN, SPACES_*, PAYMENTS_MANUAL_INSTRUCTIONS
php naluz migrate
chown -R www-data:www-data storage && chmod -R u+rwX storage
```

Point the web server at **`public/`** (see `deploy/nginx.conf`, or the bundled `public/.htaccess` on Apache) for
`SAAS_DOMAIN`, `*.SAAS_DOMAIN` and any custom domain.

## Photo storage (DigitalOcean Spaces)

| `.env` | Meaning |
|---|---|
| `SPACES_ACCESS_KEY` / `SPACES_SECRET_KEY` | the Spaces key pair |
| `SPACES_BUCKET` | bucket name (keep file listing private) |
| `SPACES_REGION` | e.g. `nyc3`, `sgp1` |
| `SPACES_ENDPOINT_URL` | `https://<region>.digitaloceanspaces.com` (no bucket name) |
| `SPACES_FOLDER` | folder inside the bucket; page photos go to `<folder>/pages/<page id>/<random>.jpg` |
| `SPACES_CDN_URL` | CDN origin used in photo links; leave empty to use the bucket URL |

Photos are resized in the browser (longest side 2200 px, JPEG) before they upload, checked from their bytes on the
server, and stored public-read under unguessable names; their URLs are only sent to a visitor after the page's answer.
Deleting a photo or a page deletes the files too.

## SEO

- The landing page (`/`) is indexable: title, description, canonical, Open Graph, `/sitemap.xml` (landing, demo, sign-up)
  and `/robots.txt`, which keeps `/p/`, `/dashboard` and `/api/` out.
- Pages are `noindex` unless their owner's page is marked `searchable` (the imported original site is). A searchable page
  gets `index, follow`, a canonical link to its own address and its own `robots.txt`/`sitemap.xml` on its host.

## Updating

```bash
git pull && composer install --no-dev --optimize-autoloader && php naluz migrate && php naluz view:clear
```

## Notes

- Keep `APP_DEBUG=false` in production. Serve over HTTPS.
- PHP limits: `upload_max_filesize` and `post_max_size` of at least 16M. Photos are refused above 10 MB
  (`saas.max_upload_bytes`); the higher PHP limit lets the app answer larger files with a clear message.
- `bin/migrate-photos.php` (2.3.0) still copies `public/photos` to Spaces for the original site; run it before the import
  if you want those photos on Spaces too.
