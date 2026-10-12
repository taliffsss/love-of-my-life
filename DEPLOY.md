# Deploying to your own server

## Docker Swarm + Traefik (https://jinelin-basela.markanthonynaluz.com)

Same pattern as hris-api and dink: a Swarm stack (`deploy/docker-stack.yml`) with Traefik labels, the shared external
`app_network`, the `letsencrypt` cert resolver and the `redirect-to-https@file` middleware from your main Traefik stack.
The image (`Dockerfile`) runs nginx + PHP-FPM under Supervisor. The release pipeline is the one dink uses: push a version
tag and CI checks, the image build, the GitHub Release and (optionally) the deploy all follow.

### One-time setup

1. **DNS:** point an `A` record for `jinelin-basela.markanthonynaluz.com` at the server running Traefik.
2. **Server folder** (default `/opt/love-of-my-life`):
   ```bash
   mkdir -p /opt/love-of-my-life/deploy
   cp deploy/.env.production.example /opt/love-of-my-life/deploy/.env.production   # then fill it in
   chmod 600 /opt/love-of-my-life/deploy/.env.production
   ```
   Fill in `APP_KEY`, `JWT_SECRET`, `UPLOAD_KEY` and the `SPACES_*` values. Generate the keys with
   `php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"` (`APP_KEY`) and
   `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"` (`JWT_SECRET`). Write values **without quotes**.
3. **GitHub** (repo → Settings), as in `.github/workflows/release.yml`:
   - Secrets: `DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN`; for deploys also `DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_USER`.
   - Variables: `DOCKER_IMAGE` (default `taliffsss/jinelin-basela`; the deploy passes it to the stack as `LOM_IMAGE`, so image and stack always match), `DEPLOY_PATH` (default `/opt/love-of-my-life`; a secret with the same name works too),
     `AUTO_DEPLOY` (`true` turns the deploy job on; keep it off until you trust the pipeline).
   - Environment `production` with required reviewers, so every deploy needs a human approval.

### Releasing

```bash
git tag v2.0.0 && git push origin v2.0.0
```

(or Actions → Release → Run workflow with the tag; it creates the tag if it does not exist). The pipeline runs the tests
and a Docker smoke test, pushes `taliffsss/jinelin-basela:2.0.0`, `:2.0` and `:latest`, creates the GitHub Release and,
if `AUTO_DEPLOY=true` and you approve the `production` environment, rolls the stack on the server. A tag with a hyphen
(`v2.0.0-rc.1`) is a pre-release: no `latest`, no deploy.

### Manual build and deploy

```bash
docker build -t taliffsss/jinelin-basela:2.0.0 -t taliffsss/jinelin-basela:latest .
docker push taliffsss/jinelin-basela:2.0.0 && docker push taliffsss/jinelin-basela:latest
# on the server, in /opt/love-of-my-life (with deploy/docker-stack.yml and deploy/.env.production):
LOM_IMAGE=taliffsss/jinelin-basela LOM_VERSION=2.0.0 docker stack deploy -c deploy/docker-stack.yml --with-registry-auth jinelin
docker service logs -f jinelin_web
```

After editing `.env.production`, run the `docker stack deploy` command again: Swarm copies the env file into the service
definition, so `docker service update --force` would restart with the old values.

Try the image locally with `docker compose up --build` (needs a `.env.production` with `APP_URL=http://localhost:8080`).

### Notes

- `storage/` is a named volume (`lom_storage`): the uploaded-photo list (`carousel.json`), the chosen song
  (`song.json`), logs and sessions survive redeploys. Photos themselves are in Spaces.
- Traefik terminates TLS; nginx passes `HTTPS=on` to PHP when it sees `X-Forwarded-Proto: https`, so cookies are
  `Secure` and the CSRF origin check works.
- Rolling updates are `start-first`: the new container must pass its Docker health check (`GET /api/ping`) before the
  old one stops, so deploys have no downtime.
- Rollback: `LOM_IMAGE=<image> LOM_VERSION=<previous-version> docker stack deploy -c deploy/docker-stack.yml --with-registry-auth jinelin`.

## Without Docker (bare metal)


Requirements: PHP 8.2+ (`mbstring`, `openssl`, `sodium`, `fileinfo`, `curl`), Composer, nginx or Apache.

```bash
git clone https://github.com/taliffsss/love-of-my-life.git /var/www/love-of-my-life
cd /var/www/love-of-my-life
git checkout feature/v2.0.0            # or main once merged
composer install --no-dev --optimize-autoloader

cp .env.example .env
php naluz key:generate --jwt           # fills APP_KEY and JWT_SECRET
# edit .env: APP_URL, UPLOAD_KEY, SPACES_*   (see below)

chown -R www-data:www-data storage
chmod -R u+rwX storage
```

Point the web server at **`public/`** (see `deploy/nginx.conf`, or use the bundled `public/.htaccess` on Apache) and
reload it. Then open the site, enter the password, and the upload button appears next to the music button.
If `UPLOAD_KEY` or the `SPACES_*` values are missing, the button still shows but uploading answers with a message
saying what to set in `.env`.

## Photo uploads to DigitalOcean Spaces

1. In DigitalOcean → *API → Spaces Keys*, create a key. Put it in `.env`:

   | `.env` | Meaning |
   |---|---|
   | `SPACES_ACCESS_KEY` / `SPACES_SECRET_KEY` | the Spaces key pair |
   | `SPACES_BUCKET` | bucket name |
   | `SPACES_REGION` | e.g. `nyc3`, `sgp1` |
   | `SPACES_ENDPOINT_URL` | `https://<region>.digitaloceanspaces.com` (no bucket name) |
   | `SPACES_FOLDER` | folder inside the bucket, e.g. `yam` |
   | `SPACES_CDN_URL` | CDN origin used in the saved links; leave empty to use the bucket URL |
   | `UPLOAD_KEY` | the secret you type into the *Add photos* dialog; empty = uploads are refused |

2. Each upload goes browser → `POST /upload` (CSRF-protected, rate limited, checks `UPLOAD_KEY`) → Spaces, with the
   file type verified from its bytes. The public URL is appended to **`storage/app/carousel.json`**, and the carousel
   shows it immediately and on every later visit. `GET /api/photos` returns the same list as JSON.
3. `storage/app/carousel.json` is the file that stores every uploaded endpoint. It is not committed, so `git pull`
   never overwrites it. Back it up with the rest of `storage/app/`. On a brand-new install it starts from
   `database/carousel.seed.json` (the photos uploaded before v2.0.0).

## Changing the song

Unlock the page, click the upload icon, enter the upload key, and paste a YouTube link in *Change the song*, for
example `https://www.youtube.com/watch?v=fu9yk7gCTbc` or `https://youtu.be/fu9yk7gCTbc`. The server checks the link
(YouTube links only, and the video must exist and be embeddable), saves it to **`storage/app/song.json`**, and it
becomes the default song for every visitor. The "Now playing" title comes from YouTube. A saved song wins over any
audio file in `public/music/`. Delete `storage/app/song.json` to go back to the song in `config/site.php`.

## Changing the message

Unlock the page, click the pen icon (left of the song button), enter the upload key, edit the text (blank line =
new paragraph) and click *Save message*. The server first uploads the **old** message as a text file to Spaces
(`<SPACES_FOLDER>/messages/20261012-034000-xxxxxxxx.txt`, private, not publicly readable), and only then saves the new
one to **`storage/app/letter.json`**, which becomes the message for everyone. If the archive upload fails, nothing
changes and the old message stays. Every archived file is listed in `letter.json` under `archive`. Without Spaces
credentials the old message is kept in `storage/app/letters/` on the server instead. Delete `letter.json` to go back
to the text in `config/site.php`.

## Updating

```bash
git pull && composer install --no-dev --optimize-autoloader && php naluz view:clear
```

## Notes

- Keep `APP_DEBUG=false` in production. Serve over HTTPS (cookies become `Secure` and HSTS is sent automatically).
- The unlock password in `public/assets/app.js` is a client-side gate for a surprise, not real security. The upload
  key, by contrast, is checked on the server.
- PHP limits: `upload_max_filesize` and `post_max_size` of at least 16M.
