<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="#1a0d17" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}" />
    <meta name="author" content="With love" />
    <meta name="keywords" content="{{ $name }}, Jinelin, love letter, romantic photo slideshow, memories" />
    <meta name="robots" content="index, follow, max-image-preview:large" />
    <link rel="canonical" href="{{ $siteUrl }}" />
    <link rel="sitemap" type="application/xml" href="{{ $siteUrl }}sitemap.xml" />
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="For {{ $name }}" />
    <meta property="og:title" content="{{ $title }}" />
    <meta property="og:description" content="{{ $description }}" />
    <meta property="og:url" content="{{ $siteUrl }}" />
    <meta property="og:image" content="{{ $ogImage }}" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="For {{ $name }}" />
    <meta property="og:locale" content="en_US" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $title }}" />
    <meta name="twitter:description" content="{{ $description }}" />
    <meta name="twitter:image" content="{{ $ogImage }}" />
    <script type="application/ld+json">{!! $jsonLd !!}</script>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400;1,500&family=Inter:wght@300;400;500&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="/assets/app.css?v={{ $assetVersion }}" />
  </head>
  <body data-yt="{{ $song['id'] }}">
    <h1 class="sr-only">For {{ $name }}: a romantic photo slideshow and love letter</h1>
    <div class="hearts" aria-hidden="true"></div>

    <!-- Slideshow (plays from the start, blurred behind the modal) -->
    <section id="show" class="show on locked" aria-label="Photo slideshow">
      <div class="viewer">
      <div class="stage" id="stage">
        @if (count($photos) > 0)
          @foreach ($photos as $i => $src)
            <figure class="slide" data-i="{{ $i }}">
              <img class="bg" src="{{ $src }}" alt="" loading="{{ $i < 2 ? 'eager' : 'lazy' }}" decoding="async" />
              <img class="fg" src="{{ $src }}" alt="Photo memory {{ $i + 1 }} for {{ $name }}" loading="{{ $i < 2 ? 'eager' : 'lazy' }}" decoding="async" />
            </figure>
          @endforeach
        @else
          @foreach ($placeholders as $i => $grad)
            <figure class="slide" data-i="{{ $i }}">
              <div class="ph" style="background:linear-gradient(135deg,{{ $grad }})">
                <span>Add your photos to <code>public/photos</code></span>
              </div>
            </figure>
          @endforeach
        @endif
      </div>
      <div class="vignette"></div>
      <div class="glow"></div>
      <button class="nav prev" id="prev" aria-label="Previous photo"></button>
      <button class="nav next" id="next" aria-label="Next photo"></button>
      <div class="hud">
        <span class="count" id="count" aria-hidden="true">01 / 01</span>
        <div class="bar" aria-hidden="true"><i id="barfill"></i></div>
      </div>
      <div class="nowplaying" id="nowplaying" hidden aria-live="polite">
        <span class="eq" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
        <span class="np-text">
          <span class="np-label">Now playing</span>
          <span class="np-title"><span id="npTitle">{{ $song['title'] }}</span> <span class="np-by" id="npBy">@if ($song['artist'] !== '')· {{ $song['artist'] }}@endif</span></span>
        </span>
      </div>
      <button class="mute add" id="addBtn" aria-label="Add photos" hidden>
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V5M7 9l5-5 5 5"/><path d="M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/></svg>
      </button>
      <button class="mute add song" id="songBtn" aria-label="Change the song" hidden>
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V6l10-2v12"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="16" r="2.5"/><path d="M3 5h4M5 3v4"/></svg>
      </button>
      <button class="mute" id="mute" aria-label="Toggle music" hidden>
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V6l10-2v12"/><circle cx="6.500" cy="18" r="2.500"/><circle cx="16.500" cy="16" r="2.500"/></svg>
      </button>
      </div>
      <aside class="letter" id="letter" aria-label="A letter for {{ $name }}">
        <div class="letter-head">
          <p class="eyebrow">A letter for</p>
          <h2>{{ $firstName }}</h2>
        </div>
        <div class="letter-view" id="letterView">
          <div class="letter-text" id="letterText">
            @foreach ($letter as $para)
              <p>{{ $para }}</p>
            @endforeach
          </div>
        </div>
      </aside>
    </section>

    <!-- Password modal -->
    <main id="gate" class="gate">
      <form id="form" class="card" autocomplete="off">
        <div class="lock" id="lock" aria-hidden="true">
          <svg viewBox="0 0 64 72" width="34" height="38">
            <path class="shackle" d="M20 32V22a12 12 0 0 1 24 0v10" fill="none" stroke="currentColor" stroke-width="4.500" stroke-linecap="round"/>
            <rect x="10" y="30" width="44" height="34" rx="10" fill="currentColor"/>
            <path d="M32 55s-9-5.500-9-11.200c0-3 2.300-5 4.800-5 1.700 0 3.400.9 4.200 2.400.8-1.500 2.500-2.400 4.200-2.400 2.500 0 4.800 2 4.800 5C41 49.500 32 55 32 55z" fill="#2a1220" opacity=".85"/>
          </svg>
        </div>
        <p class="eyebrow">For my love</p>
        <label for="pw" class="question">What is your endearment?</label>
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
          <button type="submit" aria-label="Unlock">
            <svg class="i-arrow" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            <svg class="i-heart" viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 21s-8-4.900-8-11.100C4 6.700 6.300 5 8.600 5c1.500 0 2.900.8 3.400 2 .5-1.200 1.900-2 3.400-2C17.700 5 20 6.700 20 9.900 20 16.100 12 21 12 21z"/></svg>
          </button>
        </div>
        <p id="err" class="err" role="alert" aria-live="polite"></p>
      </form>
    </main>

    <dialog id="uploader" class="uploader">
      <form method="dialog" id="upForm">
        <h3>Add photos</h3>
        <p class="up-note">Add more photos?</p>
        <label for="upKey">Upload key</label>
        <input id="upKey" type="password" autocomplete="off" spellcheck="false" />
        <label for="upFiles">Photos</label>
        <input id="upFiles" type="file" accept="image/*" multiple />
        <p id="upStatus" class="up-status" role="status" aria-live="polite"></p>
        <div class="up-actions">
          <button type="button" id="upGo">Upload</button>
          <button type="submit" value="close">Close</button>
        </div>
      </form>
    </dialog>

    <dialog id="songDialog" class="uploader">
      <form method="dialog" id="songForm">
        <h3>Change the song</h3>
        <p class="up-note">Paste a YouTube link. It becomes the new default song.<br />
          Example: <code>https://www.youtube.com/watch?v=fu9yk7gCTbc</code> or <code>https://youtu.be/fu9yk7gCTbc</code></p>
        <label for="songKey">Upload key</label>
        <input id="songKey" type="password" autocomplete="off" spellcheck="false" />
        <label for="songUrl">YouTube link</label>
        <input id="songUrl" type="url" inputmode="url" autocomplete="off" spellcheck="false" placeholder="https://www.youtube.com/watch?v=..." />
        <p id="songStatus" class="up-status" role="status" aria-live="polite"></p>
        <div class="up-actions">
          <button type="button" id="songGo">Save song</button>
          <button type="submit" value="close">Close</button>
        </div>
      </form>
    </dialog>

    @if ($music !== '')
    <audio id="song" src="{{ $music }}" loop preload="auto"></audio>
    @endif
    <script src="/assets/app.js?v={{ $assetVersion }}" defer></script>
  </body>
</html>
