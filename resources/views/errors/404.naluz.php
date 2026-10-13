<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex" />
    <title>Not found</title>
    <link rel="stylesheet" href="/assets/ui.css" />
  </head>
  <body>
    <main class="auth-main" style="min-height:100dvh">
      <div class="auth-card" style="text-align:center">
        <p class="eyebrow">404</p>
        <h1 class="display" style="font-size:2.4rem;margin-top:10px">Nothing here</h1>
        <p class="muted" style="margin-top:10px">{{ $message !== 'Not Found' ? $message : 'This page does not exist, or it is not published yet.' }}</p>
        <p style="margin-top:22px"><a class="btn btn-secondary" href="/">Go home</a></p>
      </div>
    </main>
  </body>
</html>
