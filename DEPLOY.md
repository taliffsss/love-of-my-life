# Deploying to your own server

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

2. Each upload goes browser → `POST /photos` (CSRF-protected, rate limited, checks `UPLOAD_KEY`) → Spaces, with the
   file type verified from its bytes. The public URL is appended to **`storage/app/carousel.json`**, and the carousel
   shows it immediately and on every later visit. `GET /api/photos` returns the same list as JSON.
3. `storage/app/carousel.json` is the file that stores every uploaded endpoint. It is not committed, so `git pull`
   never overwrites it. Back it up with the rest of `storage/app/`. On a brand-new install it starts from
   `database/carousel.seed.json` (the photos uploaded before v2.0.0).

## Updating

```bash
git pull && composer install --no-dev --optimize-autoloader && php naluz view:clear
```

## Notes

- Keep `APP_DEBUG=false` in production. Serve over HTTPS (cookies become `Secure` and HSTS is sent automatically).
- The unlock password in `public/assets/app.js` is a client-side gate for a surprise, not real security. The upload
  key, by contrast, is checked on the server.
- PHP limits: `upload_max_filesize` and `post_max_size` of at least 16M.
