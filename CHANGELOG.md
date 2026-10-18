# Changelog

## 3.0.0 (unreleased, branch `feature/saas-platform`)

The single page becomes a product: anyone can sign up, make love pages and publish them.

- **Accounts and dashboard.** Sign up and sign in (e-mail + password, Argon2id, rate limited). Each account lists its
  pages and edits every part of one: who it is for and the letter, photos (uploaded from the phone, resized in the
  browser, reordered and deleted), a YouTube song, the lock screen (on/off, small heading, question, answer), the design
  with a live preview, sharing (link, printable QR code) and the plan. Someone else's page always answers 404.
- **10 templates and 4 colour themes**, any template with any theme; switch any time, the dashboard previews a choice
  before it is saved:
  - slideshows: Classic (the 2.x look), Cinematic (full-screen photos, the letter floats over them like subtitles) and
    Polaroid (an instant-photo frame next to a handwritten letter);
  - interactive: Envelope (break the heart seal; the letter slides out with the photos tucked in), Scratch card (scratch
    the foil off the first photo), Storybook (turn the pages of a book: a photo and a part of the letter each), Memory
    game (find the pairs of photos to open the letter), Timeline (chapters that appear as you scroll), Gift box (untie the
    present, flick through the photos) and Starry sky (stars in a heart, each holding a photo and a line);
  - colours Rose, Midnight, Sunset and Blush (light).
  Each template is a module: an entry in `App\Services\Designs`, its own `public/assets/templates/<key>.css` and `.js`
  (registered as `window.LoveTemplates.<key>`) and its markup in `resources/views/site/templates/`; only the page's own
  template is loaded. Themes are blocks of CSS variables in `public/assets/themes.css`, which is all a template uses for
  colour. Every interaction has a keyboard way (and a way to skip a game), respects reduced motion, and the content still
  arrives only after the answer. If a template's script fails, the page opens on the plain letter and photos.
  With reduced motion turned on, the zoom out of the lock screen's blur takes 0.4 s instead of 2.8 s, so the page can be
  used as soon as it opens.
- **Private by design.** The answer is checked on the server (normalised and hashed like a password; case and spaces
  don't matter) and the photos and letter are only sent after it, so the page's HTML and script contain neither. Guessing
  is limited to 10 tries a minute. Pages are `noindex` unless marked searchable. 2.x kept the answer in `app.js`.
- **Plans** (`config/saas.php`, reasoning in `marketing/PRICING.md`), monthly, yearly or once, from ₱200 a month; a year
  costs twelve months (no yearly discount):
  - a free draft only its owner can preview, with every template to try;
  - **Sweet**, ₱200 a month or ₱2,400 a year: 12 photos, 5 templates (Classic, Cinematic, Polaroid, Envelope,
    Timeline);
  - **Sweetheart**, ₱350 a month or ₱4,200 a year: 40 photos, all 10 templates, its own address;
  - **Forever**, ₱4,999 once: never expires, 60 photos, everything.

  Renewing adds a calendar month or year to the time left, and a monthly page can renew for a year. A late payment for
  a lower plan never downgrades a page. Payment activation happens exactly once (a conditional update), whatever
  confirms it. A live page shows only its plan's templates; the owner can still preview the others. Orders record their
  period (`orders.period`); a migration renames the first plans of 3.0 development (Basic, Plus) to Sweet and
  Sweetheart.
- **Photos belong to the account** (`App\Services\PhotoAllowance`). Every photo on every page counts, and deleting one,
  or its page, frees its place. The allowance is the photos of the plans of the account's live pages, added up (6 from
  the free preview while none is live), plus photo packs: **12 more photos for ₱100**, 1 to 10 packs an order, paid
  once, kept for good, shared by all pages (an order with plan `photos`; `orders.photos` says how many it adds). A plan
  that ends stops counting: nothing is deleted or hidden, but no photo can be added until the account fits again. The
  server checks the allowance before storing an upload and again after saving it, so two uploads at once can't go past
  it. The dashboard and the editor show used, of, left, where the allowance comes from, and offer packs when it runs low.
- **Photos up to 10 MB each** (`saas.max_upload_bytes`), with a clear message ("This photo is 14.2 MB. Photos can be up
  to 10 MB each.") from the browser before uploading and from the server, including files PHP itself refused. The
  browser shrinks photos to 2,200 px on the long side, keeping their proportions and the way the camera held them,
  with high-quality resampling, JPEG quality 0.9, white instead of black behind transparent parts, and no location or
  other camera data. Every template shows them with `object-fit` (cover or contain): checked in all 10 templates at
  phone and desktop width with panoramas, tall screenshots, tiny and rotated photos.
- **Deleted photos leave storage.** A photo's file is deleted after its row, through a trash table written in the same
  transaction (`storage_trash`), so a delete that fails or is cut short is retried by `php bin/empty-trash.php` instead
  of leaving a file behind. A file another photo still uses is kept. A file whose photo could not be saved is deleted
  again. Photo packs bought from a deleted page stay on the account.
- **Dedicated addresses.** A Sweetheart or Forever page can claim `<name>.<SAAS_DOMAIN>` (3–30 lowercase letters, digits or dashes; reserved
  names refused; one page per name). It works as soon as it is saved: `ResolveSiteHost` maps the host to the page before
  routing, and only the page itself is reachable on that host. The product runs at `love.markanthonynaluz.com`: the
  stack file routes it and `*.love.markanthonynaluz.com` to the app on a wildcard certificate (Traefik DNS challenge with
  DigitalOcean); the two DNS records and the resolver are set up once (DEPLOY.md).
- **Payments.** Choosing a plan or a photo pack creates an order with a reference; with `PAYMENTS_DRIVER=none` the
  customer sees how to pay (GCash/Maya) and an admin activates it with `php bin/grant-plan.php <reference>` (or
  `--email=<e-mail> --packs=<n>` for packs paid without an order). `PAYMENTS_DRIVER=fake` is a test
  checkout and refuses to run in production. Online checkout (PayMongo) is next.
- **The original site** moves in with `php bin/import-legacy.php`: a Forever page owned by your account, on its old
  domain, with the same letter, song, photos and answer.
- Landing page with the templates, prices and FAQ, and a `/demo` anyone can open in every template and theme.
- Database: SQLite in the storage volume (migrations run at container start; `RUN_MIGRATIONS=false` to skip);
  `php bin/backup-db.php` takes a consistent copy.
- Removed: the in-page upload/song/message dialogs and their upload key (`UPLOAD_KEY`), `POST /upload|/song|/letter`,
  `GET /api/photos`, and the 2.x image sitemap.
- Hardening found in review before release:
  - the rate limiter counts `/unlock`, `/unlock/` and `/%75nlock` as one path (they reach the same route), so guessing
    can't dodge the limit by respelling the URL;
  - nginx takes the visitor's address from Traefik's `X-Forwarded-For`, so limits are per visitor instead of one counter
    shared by everyone (one person could otherwise lock all sign-ins);
  - Argon2id uses 19 MiB per hash instead of PHP's 64 MiB default and the container gets 512M, so parallel sign-ins can't
    exhaust it;
  - when a plan starts, pending orders it made pointless (a cheaper plan, anything after Forever) are cancelled;
  - the QR code opens the permanent `/p/` link (a released subdomain could later belong to someone else) and lives at
    `/qr` (nginx serves `*.svg` itself);
  - the fake checkout only runs with `APP_ENV` local/testing/development/staging;
  - the lock-screen answer is never flashed back into the session after a failed save;
  - `/api/ping` answers on page hosts too, so the release check still passes on the original domain after the import.
- Tests no longer read Spaces settings from a developer's `.env` (the 2.3.2 suite failed locally when `SPACES_FOLDER`
  was set); every test gets an in-memory disk.

## 2.3.2

- Lighter carousel, so it no longer stalls or goes black while it plays. Photos are shown as they are: the blurred copy that sat behind every photo (a second image per slide with a 34px blur, so each photo was downloaded, decoded and blurred twice) is gone, and the blur now belongs to the password screen only. The soft fade between photos and the slow zoom stay. Slides that are not on screen are no longer rendered at all (`visibility: hidden`), only the playing photo gets its own compositing layer, and the glow over the photo uses plain transparency instead of a blend mode, which made the browser re-mix the photo underneath on every frame.

## 2.3.1

- Fix for the carousel going black: a slide used to fade in when its timer fired even if its photo had not finished loading (a big photo on a slow connection, a photo that is gone, or one the browser had dropped from memory while hidden), so the screen stayed black until the photo arrived. Now the next two photos are prepared while the current one is shown, a slide only fades in once its photo is loaded and decoded, and a photo that is still not ready after 6 seconds, or that fails to load, is skipped (the next one plays; it comes back on the next round). Measured in a browser with photo 2 delayed 25 seconds and with photo 2 answering 404: the old script showed it (black), the new one goes straight to photo 3.

## 2.3.0

- `bin/migrate-photos.php` copies the 14 photos of `public/photos` to Spaces (public-read, key `<folder>/legacy-NN.jpg`) and lists them in `storage/app/carousel.json`, so they are served from Spaces and render exactly like uploaded photos. Run it once on the server (`--dry-run` shows what it would do); running it again skips photos already migrated. The carousel hides a `public/photos` file once its copy is listed, so nothing shows twice, and the order stays: new uploads first (newest on top), then the migrated photos in filename order.

## 2.2.2

- The first photo is now the first thing seen after the password. The slideshow used to start its 8-second timer when the page loaded, behind the blurred password screen, so by the time `yam` was typed it had moved on (after 20 seconds it was on photo 3 and then 4, and photo 1 was never shown). Now the first photo waits, still, behind the blur; when the blur starts to clear its slow zoom is replayed from the beginning and the timer starts, delayed by the 2.8 seconds the blur takes, so photo 1 stays fully clear for the full 8 seconds before the carousel moves on. The same applies to the reload after a saved change.

## 2.2.1

- **Root cause of the outage found and fixed.** The framework's `throttle` middleware reads a rate-limit counter back from the file cache without checking whether its window has ended, so the count only ever grows. With `storage/` in a persistent volume, the counters of `/api/ping` (probed every 30 seconds by the Docker and the Traefik health checks) passed 60 and the probe was answered with HTTP 429 for good: Swarm stopped the "unhealthy" container, and later Traefik answered 503. The app now uses its own limiter (`App\Http\Middleware\RateLimit`, registered as `throttle`) whose counter starts again every window, and `/api/ping` is never rate-limited. Counter files left on a server by the old limiter are ignored once their window has ended, so nothing has to be cleaned up by hand.
- All health checks are removed, as requested: Docker's (since 2.2.0) and now Traefik's labels in `deploy/docker-stack.yml`. The release workflow still requests the live site after every deploy.
- Tests reproduce the failure (a counter file whose window ended long ago, a health probe every 30 seconds for two hours) and guard that no health check comes back.

## 2.2.0

- The message form has a second required field, **Name**, shown in the panel as "A letter for …" (it starts filled with the current name). The name is saved with the message in `storage/app/letter.json`, and the backup file of the replaced message says who that letter was for. Saving only a new name (same message) counts as a change and backs up the old letter too. A letter saved before names existed keeps the default name from `config/site.php`.

- Fix for the production outage after 2.0.2 / 2.1.0: on the Swarm server the Docker `HEALTHCHECK` marked a working container unhealthy and Swarm stopped it (SIGQUIT, task "Complete"), leaving `jinelin_web` at 0/1 replicas. The image no longer has a Docker health check and `deploy/docker-stack.yml` keeps `healthcheck: disable: true`, so a normal deploy can no longer switch it back on. Traefik still health-checks `/api/ping`.
- The release workflow now requests the live site (`/api/ping` and `/` over HTTPS, `SITE_URL` variable) after every deploy, so a green deploy means the site really answers.
- nginx writes its access and error logs to the container log (`docker service logs jinelin_web`).
- Tests guard the above (no `HEALTHCHECK`, health check disabled in the stack, image name matches the release, live-site check present).

## 2.1.0

- New photos go to the very beginning of the carousel: uploaded photos come first, the newest upload on top, followed by the photos in `public/photos`. A batch picked together keeps the order it was picked in. The sitemap, the structured data, the first-photo preload and `GET /api/photos` follow the same order. `storage/app/carousel.json` still lists uploads oldest first.
- After a photo upload, a song change or a message change succeeds, the page reloads to show the saved state and skips the password screen for that one reload, so "yam" is not typed twice. The hint is a one-shot flag in the tab's session storage that expires after 30 seconds; a normal visit, a later refresh or a failed save still asks for the password. If the browser blocks autoplay after the reload, the music button shows the real state and one tap plays the song.

## 2.0.2

- The upload key defaults to `jinelin basela` when `UPLOAD_KEY` is missing or empty, and quotes or a carriage return around the configured key are ignored (Docker's `env_file` keeps quotes literally, so `UPLOAD_KEY="jinelin basela"` used to be rejected).
- SEO: image sitemap, `robots.txt` that hides the action endpoints, richer structured data (`WebSite`, `WebPage`, `Person`, `ImageGallery`), `hreflang`, web manifest, PNG icons and Apple touch icon, first-photo preload, descriptive alt text and `noindex` headers on the JSON endpoints.

## 2.0.1

- The Docker image is `taliffsss/jinelin-basela` everywhere (stack file, release workflow, docs, local compose); the stack deploys exactly the image the release pushed.
- The deploy step now waits until the service runs the new image and fails if it never does or the update rolls back.

## 2.0.0

Rebuilt on the NaluzPHP framework (PHP) to be deployed on your own server. The design is unchanged.

- Server-side photo upload (`POST /upload`) to DigitalOcean Spaces (signed with AWS Signature V4, no SDK), with the file type checked from its bytes, CSRF protection, rate limiting and an `UPLOAD_KEY`.
- Every uploaded photo URL is stored in `storage/app/carousel.json`; the carousel reads it, and `GET /api/photos` returns it.
- Change the song from the page: paste a YouTube link (watch, youtu.be, embed, shorts or music links); it is validated, saved to `storage/app/song.json` and becomes the default.
- Change the message from the page: the old message is uploaded to Spaces as a private text file first, then the new one is saved to `storage/app/letter.json` and shown.
- Docker image (nginx + PHP-FPM + Supervisor), a Swarm stack file with Traefik labels for `jinelin-basela.markanthonynaluz.com` (same pattern as hris-api), and a tag-driven release pipeline (same as dink): CI + Docker smoke test, image push, GitHub Release, optional approved deploy.
- The GitHub token, the upload workflow, the GitHub Pages deploy and the Astro/Node build are removed.
- Added `DEPLOY.md`, an nginx example, a PHP CI workflow and tests.

## 1.2.0

- Upload photos to DigitalOcean Spaces: use the upload button in the slideshow, or drop images in `uploads/` and push to `main` (or run the "Upload photos to Spaces" workflow).
- Uploaded photo URLs are stored in `src/data/carousel.json` and shown in the carousel after the local photos.
- The site redeploys automatically after an upload.

## 1.0.0

First release.

- Password-protected photo slideshow ("What is your endearment?") built with Astro.
- The carousel plays heavily blurred behind a frosted modal; the correct password lifts the modal, clears the blur, and starts the music.
- Slow cross-fading, drifting photo transitions, floating hearts, a progress line and a photo counter.
- Right-hand panel with a letter that rises in from the bottom and scrolls slowly (stacked under the photos on phones).
- Background music streamed through YouTube's official embed, with a "Now playing" title and a ♪ pause button.
- SEO: title, description, canonical URL, Open Graph and Twitter tags, JSON-LD, sitemap, and a share-preview image.
- Mobile friendly, with reduced-motion support.
- Deployed to GitHub Pages from `main` by GitHub Actions.
