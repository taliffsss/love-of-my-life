@extends('layouts.product')
@section('title', $op['title'])
@section('description', $op['description'])
@section('bodyClass', 'lp lp-t-' . $theme)
@section('robots', 'index, follow')
@section('siteCss', 'public')
@section('fontFamilies', '&family=Newsreader:ital,wght@1,400..600')
@push('head')
    {{-- an occasion page: the landing page's look (landing.css) plus the few pieces /invitations added (invitations.css); wording in App\Services\OccasionPages --}}
    <link rel="stylesheet" href="/assets/dist/landing.css?v={{ (string) @filemtime(base_path('public/assets/dist/landing.css')) }}" />
    <link rel="stylesheet" href="/assets/dist/invitations.css?v={{ (string) @filemtime(base_path('public/assets/dist/invitations.css')) }}" />
    <link rel="canonical" href="{{ $pageUrl }}" />
    <script type="application/ld+json">{!! json_for_html($structuredData) !!}</script>
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="{{ $brand }}" />
    <meta property="og:title" content="{{ $op['h1'] }}" />
    <meta property="og:description" content="{{ $op['description'] }}" />
    <meta property="og:url" content="{{ $pageUrl }}" />
    <meta property="og:image" content="{{ $siteUrl }}og-image.png" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $op['h1'] }}" />
    <meta name="twitter:description" content="{{ $op['description'] }}" />
    <meta name="twitter:image" content="{{ $siteUrl }}og-image.png" />
@endpush
@section('content')
<header class="hero-nav">
  <div class="container">
    @include('partials.brand')
    <span class="spacer"></span>
    <nav aria-label="Main" class="row">
      <a class="hide-sm" href="#templates">Templates</a>
      <a class="hide-sm" href="#plans">Plans</a>
      <a class="hide-sm" href="/">Love pages</a>
      @if ($user !== null)
      <a class="btn btn-light btn-sm" href="/dashboard">Your pages</a>
      @else
      <a href="/login">Sign in</a>
      <a class="btn btn-light btn-sm" href="/register">Start free</a>
      @endif
    </nav>
  </div>
</header>

<div class="hero lp-hero" id="hero" data-season="love">
  <div class="lp-wrap lp-hero-grid">
    <div class="lp-hero-copy">
      <p class="lp-kicker">{{ $op['kicker'] }}</p>
      <h1 class="lp-h1">{{ $op['h1'] }}</h1>
      <p class="lp-lead">{{ $op['lead'] }}</p>
      <div class="lp-cta">
        <a class="lp-btn lp-btn-wax" href="/register">Start free: build it first</a>
        <a class="lp-btn lp-btn-line" href="/demo?layout={{ $op['sample'][0] }}&amp;theme={{ $op['sample'][1] }}">See a sample</a>
      </div>
      <p class="lp-fine">Build and preview for free. Choose a plan when you are ready to send it.</p>
    </div>
    <div class="lp-stage inv-stage">
      <img class="inv-lettie" src="/assets/occasions/{{ $op['occasion'] }}-lettie.svg" width="400" height="400" alt="{{ $op['mascot_alt'] }}" />
      <figure class="inv-phone">
        <img src="/assets/occasions/{{ $op['occasion'] }}-phone.jpg" width="390" height="844" alt="{{ $op['alt'] }}" />
        <figcaption>A sample page, with made-up content.</figcaption>
      </figure>
    </div>
  </div>
</div>

<main>
  <section class="lp-facts" aria-label="What to know">
    <ul class="lp-wrap">
      <li><b>No app to install</b><span>They open a link in any browser.</span></li>
      <li><b>Private by default</b><span>A link or a QR code, locked with your question if you like.</span></li>
      <li><b>Yours to change</b><span>Edit it after you send; the link and the QR code stay the same.</span></li>
      <li><b>Free to start</b><span>Build and preview before you choose a plan.</span></li>
    </ul>
  </section>

  <section class="lp-how" aria-labelledby="how">
    <div class="lp-wrap lp-how-grid">
      <div class="lp-how-head">
        <h2 id="how" class="lp-h2">Three steps for your {{ strtolower($op['name']) }} page.</h2>
        <p class="lp-sub">Write it tonight, send it when it feels right. Nothing goes out until you do.</p>
      </div>
      <ol class="lp-steps">
        @foreach ($op['steps'] as $i => [$stepTitle, $stepText])
        <li><span class="lp-n" aria-hidden="true">{{ $i + 1 }}</span><div><h3>{{ $stepTitle }}</h3><p>{{ $stepText }}</p></div></li>
        @endforeach
      </ol>
    </div>
  </section>

  <section class="lp-templates inv-templates" id="templates" aria-labelledby="templates-title">
    <div class="lp-wrap">
      <div class="lp-section-head">
        <h2 id="templates-title" class="lp-h2">{{ $op['name'] }} templates</h2>
        <p class="lp-sub">Open a sample to see how it feels. Every template can be tried free; the plan shown is what you need to send it.</p>
      </div>
      <div class="inv-grid">
        @foreach ($templates as $t)
        <article class="inv-card">
          <h3>{{ $t['name'] }}</h3>
          <p>{{ $t['blurb'] }}</p>
          <p class="inv-plan">@if ($t['seasonal'])In season now · @endif{{ $t['plan'] === null ? 'Not on a plan yet' : 'On ' . $t['plan'] . ' and above' }}</p>
          <a class="lp-btn lp-btn-line lp-btn-sm" href="/demo?layout={{ $t['key'] }}" aria-label="Try the {{ strtolower($t['name']) }} template">Try the template</a>
        </article>
        @endforeach
      </div>
      <p class="lp-more">The samples show the template with example content. The {{ strtolower($op['name']) }} look (its typeface and decorations) appears on your own page.</p>
    </div>
  </section>

  <section class="lp-pricing inv-plans" id="plans" aria-labelledby="plans-title">
    <div class="lp-wrap">
      <div class="lp-section-head">
        <h2 id="plans-title" class="lp-h2">Free to build. Choose a plan to send it.</h2>
        <p class="lp-sub">Build the page and preview it for free; only you can see it. When it is ready, pick the plan that has your template.</p>
      </div>
      <div class="lp-plans">
        @foreach ($plans as $key => $plan)
        @php $monthly = $plan['prices']['month'] ?? null; $once = $plan['prices']['once'] ?? null; @endphp
        <article class="lp-plan {{ $plan['popular'] ? 'featured' : '' }}">
          @if ($plan['popular'])<span class="lp-tag">Most popular</span>@endif
          <h3>{{ $plan['name'] }}</h3>
          @if ($monthly !== null)
          <p class="lp-price">{{ \App\Services\Plans::money((int) $monthly) }} <small>a month</small></p>
          @elseif ($once !== null)
          <p class="lp-price">{{ \App\Services\Plans::money((int) $once) }} <small>once</small></p>
          <p class="lp-or">No renewals: it stays online for good.</p>
          @endif
          <p class="lp-blurb">{{ $plan['blurb'] }}</p>
          <ul>
            <li>{{ $plan['photos'] }} photos included</li>
            <li>Link and printable QR code</li>
          </ul>
          <a class="lp-btn {{ $plan['popular'] ? 'lp-btn-wax' : 'lp-btn-ink' }}" href="{{ $user !== null ? '/dashboard' : '/register' }}?plan={{ $key }}">Start with {{ $plan['name'] }}</a>
        </article>
        @endforeach
      </div>
      <p class="lp-more">The full list of what each plan includes is on the <a href="/#pricing">main page</a>.</p>
    </div>
  </section>

  <section class="lp-faq" aria-labelledby="faq-title">
    <div class="lp-wrap lp-faq-grid">
      <h2 id="faq-title" class="lp-h2">Good to know</h2>
      <div class="faq">
        @foreach ($op['faq'] as [$question, $answer])
        <details>
          <summary>{{ $question }}</summary>
          <p>{{ $answer }}</p>
        </details>
        @endforeach
        <details>
          <summary>Do they need an app or an account?</summary>
          <p>No. They open the link, or scan the QR code, in their phone’s browser.</p>
        </details>
      </div>
    </div>
  </section>

  <section class="lp-close" aria-labelledby="close-title">
    <div class="lp-wrap">
      <h2 id="close-title" class="lp-h2">Make it tonight.</h2>
      <p class="lp-sub">Your photos, your song and your words, on one private page. Free to build and preview.</p>
      <a class="lp-btn lp-btn-wax lp-btn-lg" href="/register">Start free: build it first</a>
      <p class="lp-more">More occasions:
        @foreach ($others as $i => $o){{ $i > 0 ? ' · ' : ' ' }}<a href="{{ $o['href'] }}">{{ $o['name'] }}</a>@endforeach
        · <a href="/invitations">Invitations</a>@if (! empty($op['see_also'])) · <a href="{{ $op['see_also'][0] }}">{{ $op['see_also'][1] }}</a>@endif</p>
    </div>
  </section>
</main>

<footer class="lp-footer">
  <div class="lp-wrap">
    @include('partials.brand')
    <span><a href="/">Love pages</a> · <a href="/invitations">Invitations</a> · <a href="/terms">Terms</a> · <a href="/privacy">Privacy</a> · Made with love in the Philippines.</span>
  </div>
</footer>
@endsection
