# Love of My Life

A romantic, password-protected photo slideshow built with [Astro](https://astro.build).

## Add your content

- **Photos** → drop them in `public/photos/` (jpg, png, webp, gif, avif). They play in filename order, so `01.jpg`, `02.jpg`, … works well.
- **Photos via DigitalOcean Spaces** → drop images in `uploads/` and push to `main` (or run the *Upload photos to Spaces* workflow). It uploads them to Spaces, records the URLs in `src/data/carousel.json`, removes them from the repo and redeploys; they appear after the local photos in the carousel. Needs the repo secrets `SPACES_ACCESS_KEY`, `SPACES_SECRET_KEY`, `SPACES_BUCKET`, `SPACES_REGION`, `SPACES_ENDPOINT_URL`, `SPACES_FOLDER`, `SPACES_CDN_URL`.
- **Song** → put your audio file in `public/music/` (e.g. `janice.mp3`). The first audio file found is used.

Password: `yam` (change `PASSWORD` in `src/pages/index.astro`).

## Develop

```sh
npm install
npm run dev
```

## Deploy

Pushing to `main` runs `.github/workflows/deploy.yml`, which publishes to GitHub Pages
(repo **Settings → Pages → Source: GitHub Actions**).
Live at https://taliffsss.github.io/love-of-my-life/

> The password is a client-side gate for a sweet surprise, not real security: anyone who inspects the page source or the repo can find the photos.
