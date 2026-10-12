# Changelog

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
