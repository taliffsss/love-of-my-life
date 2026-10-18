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
