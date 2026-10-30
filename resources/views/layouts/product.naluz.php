<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="#fbf7f4" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
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
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;1,500&family=Inter:wght@400;500;600&family=Bricolage+Grotesque:wght@300..800@yield('fontFamilies', '')&display=swap" rel="stylesheet" />
    {{-- ui.css + depth.css + app.css, merged and minified by bin/build-assets.mjs (the sources stay in public/assets/). The landing, invitation and occasion pages pick their own bundle (home, page: landing.css included, without the signed-in pages' sections) and the lighter ui-public.js. --}}
    <link rel="stylesheet" href="/assets/dist/@yield('siteCss', 'site').css?v={{ (string) @filemtime(base_path('public/assets/dist/MANIFEST.json')) }}" />
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
