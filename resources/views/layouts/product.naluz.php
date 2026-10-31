<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="#fbf7f4" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    {{-- Resource hints first, so the browser starts on them before it reads the rest of the head. The fonts are self-hosted (@font-face is in
         ui.css), so there is no Google Fonts stylesheet to wait for: the one font every first screen needs (Bricolage Grotesque) is preloaded and
         arrives alongside the stylesheet instead of after it. dns-prefetch is all the Google tag gets (it loads late, so a full connection now
         would only compete). A page can add preloads of its own (the picture its first screen is made of) through the "hints" stack. --}}
    <link rel="preload" as="font" type="font/woff2" href="/assets/fonts/bricolage-grotesque.woff2" crossorigin />
    @if (! empty($analyticsId))
    <link rel="dns-prefetch" href="https://www.googletagmanager.com" />
    <link rel="dns-prefetch" href="https://www.google-analytics.com" />
    @endif
    @stack('hints')
    {{-- ui.css + depth.css + app.css, merged and minified by bin/build-assets.mjs (the sources stay in public/assets/). The landing, invitation and occasion pages pick their own bundle (home, page: landing.css included, without the signed-in pages' sections) and the lighter ui-public.js. --}}
    <link rel="stylesheet" href="/assets/dist/@yield('siteCss', 'site').css?v={{ (string) @filemtime(base_path('public/assets/dist/MANIFEST.json')) }}" />
    <title>@yield('title', $brand)</title>
    <meta name="description" content="@yield('description', 'Make a private love page: your photos, your song and a letter, opened with a question only they can answer.')" />
    <meta name="robots" content="@yield('robots', 'noindex, nofollow')" />
    @stack('head')
    @if (! empty($analyticsId))
    <!-- Google tag (gtag.js): /assets/analytics.js sets it up and loads Google's script once the page has loaded (the CSP allows no inline scripts) -->
    <script src="/assets/analytics.js?v={{ (string) @filemtime(base_path('public/assets/analytics.js')) }}" data-id="{{ $analyticsId }}" defer></script>
    @endif
    @include('partials.icons')
    <link rel="manifest" href="/manifest.webmanifest" />
  </head>
  <body class="@yield('bodyClass')">
    @yield('content')
    @if ($flash !== '')
    <div class="toast" role="status" id="toast">{{ $flash }}</div>
    @endif
    <script src="/assets/dist/@yield('uiJs', 'ui').js?v={{ (string) @filemtime(base_path('public/assets/dist/MANIFEST.json')) }}" defer></script>
    <script src="/assets/dist/depth.js?v={{ (string) @filemtime(base_path('public/assets/dist/depth.js')) }}" defer></script>
  </body>
</html>
