<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex, nofollow" />
    <title>For {{ $page->recipient }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@1,400&family=Inter:wght@400;500&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="/assets/themes.css?v={{ (string) @filemtime(base_path('public/assets/themes.css')) }}" />
    <link rel="stylesheet" href="/assets/site.css?v={{ (string) @filemtime(base_path('public/assets/site.css')) }}" />
  </head>
  @php $theme = \App\Services\Designs::theme($page->theme); @endphp
  <body data-layout="classic" data-theme="{{ $theme }}" data-scheme="{{ \App\Services\Designs::THEMES[$theme]['scheme'] }}" data-mode="expired">
    <main class="gate">
      <div class="card">
        <p class="eyebrow">For {{ $page->recipient }}</p>
        <p class="question">This page is resting for now.</p>
        <p class="preview-note" style="font-size:.9rem">Its time online has ended. Whoever made it can bring it back from their dashboard, with the same link.</p>
        <p style="margin-top:1.4rem"><a class="open-btn" style="text-decoration:none" href="{{ $productUrl }}">Visit {{ $brand }}</a></p>
      </div>
    </main>
  </body>
</html>
