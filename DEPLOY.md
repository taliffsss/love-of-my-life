# Deploying to your own server

## Docker Swarm + Traefik (https://jinelin-basela.markanthonynaluz.com)

This mirrors the hris-api setup: a Swarm stack (`docker-compose.yml`) with Traefik labels, the shared external
`app_network`, the `letsencrypt` cert resolver and the `redirect-to-https@file` middleware from your main Traefik stack.
The image (`Dockerfile`) runs nginx + PHP-FPM under Supervisor, like the hris-api image.

1. **DNS:** point an `A` record for `jinelin-basela.markanthonynaluz.com` at the server running Traefik.
2. **Server folder** (for example `/opt/love-of-my-life`):
   ```bash
   cp .env.docker.example .env      # then fill it in: APP_KEY, JWT_SECRET, UPLOAD_KEY, SPACES_*
   ```
   Generate the keys with `php naluz key:generate --show` (on any machine with the repo), or
   `php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"` for `APP_KEY` and
   `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"` for `JWT_SECRET`. Write values **without quotes** in `.env`.
3. **Build, push and deploy**, either by hand:
   ```bash
   docker build -t taliffsss/love-of-my-life:2.0.0 -t taliffsss/love-of-my-life:latest .
   docker push taliffsss/love-of-my-life:2.0.0 && docker push taliffsss/love-of-my-life:latest
   # on the server, in the folder with docker-compose.yml and .env:
   sh deploy.sh 2.0.0 jinelin       # = docker stack deploy -c docker-compose.yml jinelin --with-registry-auth
   ```
   or with GitHub Actions: *Actions → Deploy → Run workflow* (version `2.0.0`). It needs these repository secrets:
   `DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN`, `SSH_KEY`, `PROD_SERVER_HOST`, `PROD_SERVER_USER`, `PROD_DEPLOY_PATH`.
4. Check it: `docker service logs -f jinelin_web`, then open the site. Traefik health-checks `GET /api/ping`.

Notes:
- `storage/` is a named volume (`lom_storage`): the uploaded-photo list (`carousel.json`), the chosen song
  (`song.json`), logs and sessions survive redeploys. Photos themselves are in Spaces.
- Traefik terminates TLS; nginx passes `HTTPS=on` to PHP when it sees `X-Forwarded-Proto: https`, so cookies are
  `Secure` and the CSRF origin check works.
- Single replica with a rolling update means a few seconds of downtime per deploy, same as hris-api.
- Rollback: `sh deploy.sh <previous-version> jinelin`.

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

## Updating

```bash
git pull && composer install --no-dev --optimize-autoloader && php naluz view:clear
```

## Notes

- Keep `APP_DEBUG=false` in production. Serve over HTTPS (cookies become `Secure` and HSTS is sent automatically).
- The unlock password in `public/assets/app.js` is a client-side gate for a surprise, not real security. The upload
  key, by contrast, is checked on the server.
- PHP limits: `upload_max_filesize` and `post_max_size` of at least 16M.
