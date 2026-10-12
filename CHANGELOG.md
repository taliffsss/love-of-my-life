# Changelog

## 2.0.0

Rebuilt on the NaluzPHP framework (PHP) to be deployed on your own server. The design is unchanged.

- Server-side photo upload to DigitalOcean Spaces (signed with AWS Signature V4, no SDK), with the file type checked from its bytes, CSRF protection, rate limiting and an `UPLOAD_KEY`.
- Every uploaded photo URL is stored in `storage/app/carousel.json`; the carousel reads it, and `GET /api/photos` returns it.
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
