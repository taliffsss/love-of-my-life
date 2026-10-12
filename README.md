# Love of My Life

A romantic, password-protected photo slideshow with a love letter, built on the
[NaluzPHP framework](https://github.com/taliffsss/naluzphp-framework). Photos can be uploaded from the page itself; they
are stored in DigitalOcean Spaces and appear in the carousel straight away.

## Run it locally

```sh
composer install
cp .env.example .env && php naluz key:generate --jwt
php naluz run:server        # http://127.0.0.1:8000
```

Password: `yam` (change `PASSWORD` in `public/assets/app.js`).

## Content

- **Photos** → drop files in `public/photos/` (jpg, png, webp, gif, avif); they play in filename order, before the uploaded ones.
- **Song** → open the upload dialog and paste a YouTube link under *Change the song*; it becomes the new default (`storage/app/song.json`). Without one, an audio file in `public/music/` is used, else the song in `config/site.php`.
- **Letter and names** → `config/site.php`.

## Uploading photos

Set `UPLOAD_KEY` and the `SPACES_*` values in `.env` (see `.env.example`). After unlocking the page an upload button
appears; enter the upload key, pick photos, and they are sent to Spaces. Every uploaded URL is recorded in
`storage/app/carousel.json`, which the carousel reads (`GET /api/photos` returns the same list).

## Deploy

Docker Swarm + Traefik at <https://jinelin-basela.markanthonynaluz.com>: see [DEPLOY.md](DEPLOY.md) (`Dockerfile`, `docker-compose.yml`, `deploy/deploy.sh`). A bare-metal nginx example is in `deploy/nginx.conf`.

## Develop

```sh
composer check     # coding standard + tests
```

> The unlock password is a client-side gate for a sweet surprise, not real security. The upload key is checked on the server.
