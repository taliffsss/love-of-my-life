<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="{{ $theme === 'blush' ? '#fbf0ec' : '#140a12' }}" />
    <meta name="color-scheme" content="{{ $theme === 'blush' ? 'light' : 'dark' }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title }}</title>
    <meta name="description" content="Someone made this for {{ $page->recipient }}: photos, a song and a letter." />
    <meta name="robots" content="{{ $indexable ? 'index, follow' : 'noindex, nofollow' }}" />
    @if ($indexable)
    <link rel="canonical" href="{{ $url }}" />
    @endif
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ $title }}" />
    <meta property="og:description" content="Someone made something for you. Open it." />
    <meta property="og:url" content="{{ $url }}" />
    <meta property="og:image" content="{{ $productUrl }}og-image.png" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta name="twitter:card" content="summary_large_image" />
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
    <link rel="icon" href="/icons/favicon-32.png" type="image/png" sizes="32x32" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Caveat:wght@500;600&family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400;1,500&family=Inter:wght@300;400;500&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="/assets/site.css?v={{ $assetVersion }}" />
  </head>
  <body data-layout="{{ $layout }}" data-theme="{{ $theme }}" data-mode="{{ $mode }}">
    <h1 class="sr-only">For {{ $page->recipient }}</h1>
    <div class="hearts" aria-hidden="true"></div>

    <!-- The photos and the letter. Empty until the page opens: site.js fills them from the page data.
         inert while the lock screen is up, so keyboard and screen-reader focus starts on the question. -->
    <section id="show" class="show on locked" aria-label="Photo slideshow" inert>
      <div class="viewer">
        <div class="frame">
          <div class="stage" id="stage"></div>
          <div class="vignette"></div>
          <div class="glow"></div>
          <button class="nav prev" id="prev" aria-label="Previous photo"></button>
          <button class="nav next" id="next" aria-label="Next photo"></button>
          <div class="hud">
            <span class="count" id="count" aria-hidden="true"></span>
            <div class="bar" aria-hidden="true"><i id="barfill"></i></div>
          </div>
        </div>
        <div class="nowplaying" id="nowplaying" hidden aria-live="polite">
          <span class="eq" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
          <span class="np-text">
            <span class="np-label">Now playing</span>
            <span class="np-title"><span id="npTitle"></span> <span class="np-by" id="npBy"></span></span>
          </span>
        </div>
        <button class="mute" id="mute" aria-label="Pause or play the music" hidden>
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V6l10-2v12"/><circle cx="6.500" cy="18" r="2.500"/><circle cx="16.500" cy="16" r="2.500"/></svg>
        </button>
      </div>
      <aside class="letter" id="letter" aria-label="A letter for {{ $page->recipient }}">
        <div class="letter-head">
          <p class="eyebrow">A letter for</p>
          <h2 id="letterFor">{{ $page->recipient }}</h2>
        </div>
        <div class="letter-view" id="letterView">
          <div class="letter-text" id="letterText"></div>
        </div>
      </aside>
    </section>

    <!-- The lock screen -->
    <main id="gate" class="gate">
      <form id="form" class="card" autocomplete="off" novalidate>
        <div class="lock" id="lock" aria-hidden="true">
          <svg viewBox="0 0 64 72" width="34" height="38">
            <path class="shackle" d="M20 32V22a12 12 0 0 1 24 0v10" fill="none" stroke="currentColor" stroke-width="4.500" stroke-linecap="round"/>
            <rect x="10" y="30" width="44" height="34" rx="10" fill="currentColor"/>
            <path class="lock-heart" d="M32 55s-9-5.500-9-11.200c0-3 2.300-5 4.800-5 1.700 0 3.400.9 4.200 2.400.8-1.500 2.500-2.400 4.200-2.400 2.500 0 4.800 2 4.800 5C41 49.500 32 55 32 55z"/>
          </svg>
        </div>
        <p class="eyebrow">{{ $page->gate_eyebrow }}</p>
        @if ($mode === 'locked' || (in_array($mode, ['preview', 'demo'], true) && $page->gate_enabled))
          <label for="pw" class="question">{{ $page->gate_question }}</label>
          <div class="field">
            <input
              id="pw"
              type="password"
              inputmode="text"
              autocapitalize="none"
              autocorrect="off"
              spellcheck="false"
              placeholder="Type it here"
              aria-describedby="err"
              required
            />
            <button type="submit" aria-label="Open">
              <svg class="i-arrow" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
              <svg class="i-heart" viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 21s-8-4.900-8-11.100C4 6.700 6.300 5 8.600 5c1.500 0 2.900.8 3.400 2 .5-1.200 1.900-2 3.400-2C17.700 5 20 6.700 20 9.900 20 16.100 12 21 12 21z"/></svg>
            </button>
          </div>
        @else
          <p class="question">For {{ $page->recipient }}</p>
          <button type="submit" class="open-btn" id="openBtn">
            Open
            <svg class="i-heart" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12 21s-8-4.900-8-11.100C4 6.700 6.300 5 8.600 5c1.500 0 2.900.8 3.400 2 .5-1.200 1.900-2 3.400-2C17.700 5 20 6.700 20 9.900 20 16.100 12 21 12 21z"/></svg>
          </button>
        @endif
        <p id="err" class="err" role="alert" aria-live="polite"></p>
        @if ($mode === 'preview')
          <p class="preview-note">Preview: any answer opens it. Visitors need the real one.</p>
        @elseif ($mode === 'demo')
          <p class="preview-note">Demo: type anything to open it.</p>
        @endif
      </form>
    </main>

    @if ($mode === 'demo')
    <a class="made-with" href="{{ $productUrl }}register">Make one for someone you love &rarr;</a>
    @endif

    <script type="application/json" id="page-config">{!! json_for_html($config) !!}</script>
    <script src="/assets/site.js?v={{ $assetVersion }}" defer></script>
  </body>
</html>
