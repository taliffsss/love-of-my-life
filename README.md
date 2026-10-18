# Love of My Life

Make a private love page for someone: your photos, your song and a letter, opened with a question only they can
answer. People sign up, build a page in the dashboard for free, and publish it with a one-time plan. Built on the
[NaluzPHP framework](https://github.com/taliffsss/naluzphp-framework).

- **Dashboard:** create pages, write the letter, upload and order photos, pick a YouTube song, set the lock-screen
  question and answer, choose a template and colours with a live preview, share the link or a printable QR code.
- **10 templates**, each with any of the colour themes (Rose, Midnight, Sunset, Blush): the slideshows Classic,
  Cinematic and Polaroid, and the interactive Envelope, Scratch card, Storybook, Memory game, Timeline, Gift box and
  Starry sky. A template is a module: an entry in `app/Services/Designs.php`, `public/assets/templates/<key>.css` +
  `<key>.js` and `resources/views/site/templates/<key>.naluz.php`; the contract its script follows is at the top of
  `public/assets/site.js`. A theme is a block of variables in `public/assets/themes.css`.
- **Plans** (`config/saas.php`, reasoning in `marketing/PRICING.md`):
  - a free draft preview, with every template;
  - **Sweet**, ₱59 a month or ₱590 a year: 12 photos and 5 templates;
  - **Sweetheart**, ₱99 a month or ₱990 a year: 40 photos, all 10 templates, and its own address `<name>.<SAAS_DOMAIN>`,
    which works the moment it is saved;
  - **Forever**, ₱1,499 once: never expires, 60 photos, everything.
  A live page shows only its plan's templates; a draft can try them all.
- **Private by default:** with a question set, the page's HTML holds no photo and no letter; they are sent only after the
  right answer (checked on the server, rate limited). Pages are hidden from search engines.

## Run it locally

```sh
composer install
cp .env.example .env && php naluz key:generate --jwt     # .env.example is set up for local development
php naluz migrate
php naluz run:server        # http://localhost:8000
```

Sign up, create a page, and with `PAYMENTS_DRIVER=fake` publish it with the test checkout. A Sweetheart or Forever page can claim a
name and open at `http://<name>.localhost:8000/` (browsers resolve `*.localhost` to your machine). Without the `SPACES_*`
values photos are stored in `public/storage`.

## Admin scripts

```sh
php bin/grant-plan.php LM-7KQ2-9XDA            # confirm a manual (GCash/Maya) payment and start the plan
php bin/import-legacy.php --email=... --domain=jinelin-basela.markanthonynaluz.com --answer=yam
```

## Deploy

Docker Swarm + Traefik: see [DEPLOY.md](DEPLOY.md) (wildcard DNS and certificate for page addresses, migrations,
payments, moving the original site into the product).

## Develop

```sh
composer check     # coding standard + tests (in-memory SQLite)

# the same suite on PostgreSQL, in a throw-away database (any name ending in _test; it is wiped by every test)
createdb lovable_test
TEST_DB_CONNECTION=pgsql TEST_DB_HOST=localhost TEST_DB_USERNAME=$USER vendor/bin/phpunit
```
