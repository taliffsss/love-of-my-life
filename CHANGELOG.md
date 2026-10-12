# Changelog

## Unreleased

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
