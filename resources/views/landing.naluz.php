@extends('layouts.product')
@section('title', $brand . ' · A love letter they unlock with a word only they know')
@section('robots', 'index, follow')
@push('head')
    <link rel="canonical" href="{{ $siteUrl }}" />
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="{{ $brand }}" />
    <meta property="og:title" content="{{ $brand }}: a love letter they unlock" />
    <meta property="og:description" content="Your photos, your song and a letter on one private page, opened with a question only they can answer." />
    <meta property="og:url" content="{{ $siteUrl }}" />
    <meta property="og:image" content="{{ $siteUrl }}og-image.png" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="{{ $brand }}: a love letter they unlock with a word only they know" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $brand }}: a love letter they unlock" />
    <meta name="twitter:description" content="Your photos, your song and a letter on one private page, opened with a question only they can answer." />
    <meta name="twitter:image" content="{{ $siteUrl }}og-image.png" />
    <meta name="twitter:image:alt" content="{{ $brand }}: a love letter they unlock with a word only they know" />
    <script type="application/ld+json">{!! json_for_html($structuredData) !!}</script>
@endpush
@section('content')
<div class="hero">
  <header class="hero-nav">
    <div class="container">
      @include('partials.brand')
      <span class="spacer"></span>
      <nav aria-label="Main" class="row">
        <a class="hide-sm" href="#templates">Templates</a>
        <a class="hide-sm" href="#pricing">Pricing</a>
        <a class="hide-sm" href="/demo">Demo</a>
        @if ($user !== null)
        <a class="btn btn-light btn-sm" href="/dashboard">Your pages</a>
        @else
        <a href="/login">Sign in</a>
        <a class="btn btn-light btn-sm" href="/register">Create a page</a>
        @endif
      </nav>
    </div>
  </header>
  <div class="container">
    <div>
      <p class="eyebrow">For monthsaries, anniversaries and “just because”</p>
      <h1 style="margin-top:14px">A love letter they <em>unlock</em> with a word only they know.</h1>
      <p class="lead">Put your photos, your song and a letter on one private page. It opens with a question only they can answer, then the blur lifts, the music starts and your words begin to scroll.</p>
      <div class="row">
        <a class="btn btn-light btn-lg" href="/register">Start free: build it first</a>
        <a class="btn btn-outline-light btn-lg" href="/demo">See a demo</a>
      </div>
      <p class="small" style="margin-top:14px;color:var(--night-muted)">Build and preview for free. Choose a plan when you are ready to send it.</p>
    </div>
    <div aria-hidden="true">
      <div class="phone">
        <div class="phone-screen">
          <div class="mock-card">
            <span class="mock-lock"><svg viewBox="0 0 64 72" width="24" height="27"><path d="M20 32V22a12 12 0 0 1 24 0v10" fill="none" stroke="currentColor" stroke-width="4.5" stroke-linecap="round"/><rect x="10" y="30" width="44" height="34" rx="10" fill="currentColor"/></svg></span>
            <p class="eyebrow" style="color:#f0cfa8;margin-top:12px">For my love</p>
            <p class="q">What is your endearment?</p>
            <div class="mock-input"><span>• • • • •</span><span>→</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<main>
  <section class="band" aria-labelledby="how">
    <div class="container">
      <p class="eyebrow">How it works</p>
      <h2 id="how" style="margin-top:10px">Three steps, one evening.</h2>
      <div class="grid-3">
        <div class="card card-pad step">
          <span class="step-n">1</span>
          <h3>Write it and add your photos</h3>
          <p>Type your letter, upload your favourite photos from your phone and paste a YouTube link to your song.</p>
        </div>
        <div class="card card-pad step">
          <span class="step-n">2</span>
          <h3>Choose the question</h3>
          <p>Your endearment, your first date, an inside joke. Only the right answer opens the page. Everything else stays private.</p>
        </div>
        <div class="card card-pad step">
          <span class="step-n">3</span>
          <h3>Send the link or the QR code</h3>
          <p>Share it in a message, or print the QR code on a card, a frame or the gift tag. You can change everything later.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="band band-alt" id="templates" aria-labelledby="templates-title">
    <div class="container">
      <p class="eyebrow">{{ count($layouts) }} templates</p>
      <h2 id="templates-title" style="margin-top:10px">Make it look like the two of you.</h2>
      <p class="sub">Pick a template and a colour theme: every template works with every theme. Most of them are little things to play: break a seal, scratch a card, turn the pages, find the pairs. Switch any time from your dashboard; the link stays the same.</p>
      <div class="grid-templates">
        @foreach ($layouts as $key => $layout)
        <article class="card layout-card">
          <div class="shot"><div class="schem schem-{{ $key }}" aria-hidden="true"><span class="ph"></span><span class="lt"><i></i><i></i><i></i><i></i></span></div></div>
          <div class="body">
            <h3>{{ $layout['name'] }}</h3>
            <p>{{ $layout['description'] }}</p>
            <p class="play">{{ $layout['play'] }}</p>
            <div class="row"><a class="btn btn-secondary btn-sm" href="/demo?layout={{ $key }}" aria-label="Open the {{ strtolower($layout['name']) }} demo">Try it</a></div>
          </div>
        </article>
        @endforeach
      </div>
      <div class="swatches" style="margin-top:22px" aria-label="Colour themes">
        @foreach ($themes as $key => $theme)
        <a class="swatch" href="/demo?theme={{ $key }}" style="text-decoration:none;color:inherit">
          <span class="dots" aria-hidden="true">@foreach ($theme['colors'] as $c)<i style="background:{{ $c }}"></i>@endforeach</span>
          {{ $theme['name'] }}
        </a>
        @endforeach
      </div>
    </div>
  </section>

  <section class="band" id="pricing" aria-labelledby="pricing-title">
    <div class="container">
      <p class="eyebrow">Pricing</p>
      <h2 id="pricing-title" style="margin-top:10px">Monthly, yearly, or once for good.</h2>
      <p class="sub">Build your page and preview it for free, with every template. Choose a plan only when you are ready to send it.</p>
      <div class="plans">
        @foreach ($plans as $key => $plan)
        @php $monthly = $plan['prices']['month'] ?? null; $yearly = $plan['prices']['year'] ?? null; $once = $plan['prices']['once'] ?? null; @endphp
        <article class="card plan {{ $plan['popular'] ? 'featured' : '' }}">
          @if ($plan['popular'])<span class="tag">Most popular</span>@endif
          <h3>{{ $plan['name'] }}</h3>
          @if ($monthly !== null)
          <p class="price">{{ \App\Services\Plans::money((int) $monthly) }} <small>a month</small></p>
          @if ($yearly !== null)
          <p class="or">or {{ \App\Services\Plans::money((int) $yearly) }} for a whole year, paid once</p>
          @endif
          @elseif ($once !== null)
          <p class="price">{{ \App\Services\Plans::money((int) $once) }} <small>once</small></p>
          <p class="or">No renewals: it stays online for good.</p>
          @endif
          <p class="muted">{{ $plan['blurb'] }}</p>
          <ul>
            <li>{{ $plan['photos'] }} photos included</li>
            <li>{{ $plan['templates'] === 'all' ? 'All ' . count($layouts) . ' templates' : count($plan['templates']) . ' templates: ' . implode(', ', array_map(fn ($k) => $layouts[$k]['name'], $plan['templates'])) }}, every colour theme</li>
            <li>Your song, your letter, your question</li>
            <li>Link and printable QR code</li>
            @if ($plan['subdomain'])
            <li>Its own address: <strong>yourname.{{ $domain }}</strong></li>
            @endif
          </ul>
          <a class="btn {{ $plan['popular'] ? 'btn-primary' : 'btn-secondary' }}" href="/register">Start with {{ $plan['name'] }}</a>
        </article>
        @endforeach
      </div>
      <p class="sub plans-more">Need more photos? Add {{ $pack['photos'] }} for {{ \App\Services\Plans::money($pack['price']) }} whenever you like: paid once, kept for good, shared by all your pages.</p>
    </div>
  </section>

  <section class="band band-alt" aria-labelledby="faq-title">
    <div class="narrow">
      <p class="eyebrow">Questions</p>
      <h2 id="faq-title" style="margin-top:10px">Good to know</h2>
      <div class="faq">
        <details>
          <summary>Who can see my page?</summary>
          <p>Only people with the link, and if you set a question, only someone who knows the answer. Your photos and letter are not even sent to the browser until the right answer is typed. Pages are hidden from search engines.</p>
        </details>
        <details>
          <summary>Can I change it after I send it?</summary>
          <p>Yes. Change the letter, the photos, the song, the question, the template or the colours whenever you like. The link and the QR code stay the same.</p>
        </details>
        @if (config('saas.features.subdomains'))
        <details>
          <summary>What is the dedicated address?</summary>
          <p>With the Sweetheart and Forever plans your page can live at its own address, like <strong>yourname.{{ $domain }}</strong>. Pick the name in your dashboard and it works right away.</p>
        </details>
        @endif
        <details>
          <summary>How many photos can I add?</summary>
          <p>Photos belong to your account and are shared by all your pages. The free preview includes {{ $freePhotos }}; each live page adds the photos of its plan: @foreach (array_values($plans) as $i => $p){{ $i > 0 ? ($i === count($plans) - 1 ? ' and ' : ', ') : '' }}{{ $p['name'] }} {{ $p['photos'] }}@endforeach. Every photo counts until you delete it. When you run out, add {{ $pack['photos'] }} more for {{ \App\Services\Plans::money($pack['price']) }}; they never expire. Each photo can be up to {{ $maxPhoto }} and fits every template without being stretched.</p>
        </details>
        <details>
          <summary>What happens when a monthly or yearly plan ends?</summary>
          <p>The page rests: visitors see a short note instead of your content, and nothing is deleted. Renew from your dashboard, for a month or a year, and it is back immediately, with the same link. Renewing early adds the time to what is left.</p>
        </details>
        <details>
          <summary>How do I pay?</summary>
          <p>Pick a plan in your dashboard. You will see the amount and an order reference to pay by GCash or Maya.</p>
        </details>
      </div>
    </div>
  </section>
</main>

<footer class="footer">
  <div class="container">
    @include('partials.brand')
    <span>Made with love in the Philippines.</span>
  </div>
</footer>
@endsection
