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
    <!-- Google tag (gtag.js): the init lives in /assets/analytics.js because the CSP allows no inline scripts -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $analyticsId }}"></script>
    <script src="/assets/analytics.js?v={{ (string) @filemtime(base_path('public/assets/analytics.js')) }}" data-id="{{ $analyticsId }}"></script>
    @endif
    @include('partials.icons')
    <link rel="manifest" href="/manifest.webmanifest" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;1,500&family=Inter:wght@400;500;600&family=Bricolage+Grotesque:opsz,wght@12..96,300..800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="/assets/ui.css?v={{ (string) @filemtime(base_path('public/assets/ui.css')) }}" />
    <link rel="stylesheet" href="/assets/depth.css?v={{ (string) @filemtime(base_path('public/assets/depth.css')) }}" />
    <link rel="stylesheet" href="/assets/app.css?v={{ (string) @filemtime(base_path('public/assets/app.css')) }}" />
  </head>
  <body class="@yield('bodyClass')">
    @yield('content')
    @if ($flash !== '')
    <div class="toast" role="status" id="toast">{{ $flash }}</div>
    @endif
    <script src="/assets/ui.js?v={{ (string) @filemtime(base_path('public/assets/ui.js')) }}" defer></script>
    <script src="/assets/depth.js?v={{ (string) @filemtime(base_path('public/assets/depth.js')) }}" defer></script>
  </body>
</html>
