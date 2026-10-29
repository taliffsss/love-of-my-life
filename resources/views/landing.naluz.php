@extends('layouts.product')
@section('title', $brand . ' · A love letter they unlock with a word only they know')
@section('bodyClass', 'lp lp-t-' . $theme)
@section('robots', 'index, follow')
@push('head')
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300..800&family=Newsreader:ital,opsz,wght@1,6..72,400..600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="/assets/landing.css?v={{ (string) @filemtime(base_path('public/assets/landing.css')) }}" />
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
<header class="hero-nav">
  <div class="container">
    @include('partials.brand')
    <span class="spacer"></span>
    <nav aria-label="Main" class="row">
      <a class="hide-sm" href="#templates">Templates</a>
      <a class="hide-sm" href="#pricing">Pricing</a>
      <a class="hide-sm" href="/invitations">Invitations</a>
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
<div class="hero lp-hero" id="hero" data-season="love" data-seasons="{{ implode(',', $openSeasons) }}">
  @include('partials.landing-scene', ['theme' => $theme])
  <div class="fx-layer" id="fxLayer" aria-hidden="true"></div>
  <div class="lp-wrap lp-hero-grid">
    <div class="lp-hero-copy">
      <p class="lp-kicker" id="heroEyebrow">For monthsaries, anniversaries and “just because”</p>
      <h1 class="lp-h1"><span class="ln"><span>A love letter</span></span></span> <span class="ln"><span>they unlock with</span></span></span> <span class="ln"><span>a word only they know.</span></span></h1>
      <p class="lp-lead">Put your photos, your song and a letter on one private page. It opens with a question only they can answer, then the blur lifts, the music starts and your words begin to scroll.</p>
      <div class="lp-composer">
        <label for="tryWho">Try it now: who is it for?</label>
        <div class="lp-composer-row">
          <input class="lp-input" id="tryWho" type="text" maxlength="20" autocomplete="off" placeholder="Their name" />
          <div class="chips" role="group" aria-label="Pick an occasion to preview">
            <button type="button" class="chip" data-season="love" aria-pressed="true">💕 Everyday</button>
            @if (in_array('valentine', $openSeasons, true))<button type="button" class="chip" data-season="valentine" aria-pressed="false">💘 Valentine’s</button>@endif
            @if (in_array('halloween', $openSeasons, true))<button type="button" class="chip" data-season="halloween" aria-pressed="false">🎃 Halloween</button>@endif
            @if (in_array('christmas', $openSeasons, true))<button type="button" class="chip" data-season="christmas" aria-pressed="false">🎄 Christmas</button>@endif
          </div>
        </div>
      </div>
      <div class="lp-cta">
        <a class="lp-btn lp-btn-wax" href="/register">Start free: build it first</a>
        <a class="lp-btn lp-btn-line" href="/demo">See a demo</a>
      </div>
      <p class="lp-fine">Build and preview for free. Choose a plan when you are ready to send it.</p>
    </div>
    <div class="lp-stage">
      <div class="lp-sheets" aria-hidden="true"><i></i><i></i></div>
      <div class="phone">
        <div class="phone-screen" id="phoneScreen">
          <div class="mock-card" id="mockLock">
            <span class="mock-lock" aria-hidden="true"><svg viewBox="0 0 64 72" width="24" height="27"><path d="M20 32V22a12 12 0 0 1 24 0v10" fill="none" stroke="currentColor" stroke-width="4.5" stroke-linecap="round"/><rect x="10" y="30" width="44" height="34" rx="10" fill="currentColor"/></svg></span>
            <p class="mock-who" id="mockEyebrow">For my love</p>
            <p class="q" id="mockQuestion">What is your endearment?</p>
            <form class="mock-input mock-form" id="tryForm" autocomplete="off">
              <input id="tryAnswer" type="text" maxlength="20" placeholder="type anything…" aria-label="Answer the question: any word opens this sample" />
              <button type="submit" aria-label="Open the sample letter">→</button>
            </form>
            <p class="mock-hint">Tap → to unlock it</p>
          </div>
          <div class="mock-letter" id="mockLetter" hidden>
            <p class="mock-who" id="mockLetterEyebrow">A letter for my love</p>
            <div class="mock-text" id="mockText" aria-live="polite"></div>
            <button type="button" class="lp-btn lp-btn-paper" id="tryAgain">Lock it again</button>
          </div>
        </div>
      </div>
      <p class="phone-note" id="phoneNote">A real sample: type a name, pick an occasion, then unlock it.</p>
    </div>
  </div>
</div>

<main>
  <section class="lp-facts" aria-label="What to know">
    <ul class="lp-wrap">
      <li><b>No app to install</b><span>They open a link in any browser.</span></li>
      <li><b>Private by default</b><span>A link or a QR code, locked with your question.</span></li>
      <li><b>Yours to change</b><span>Edit it after you send; the link stays the same.</span></li>
      <li><b>Free to start</b><span>Build and preview before you choose a plan.</span></li>
    </ul>
  </section>

  <section class="lp-how" aria-labelledby="how">
    <div class="lp-wrap lp-how-grid">
      <div class="lp-how-head">
        <h2 id="how" class="lp-h2">Three steps, one evening.</h2>
        <p class="lp-sub">Write it tonight, send it when it feels right. Nothing goes out until you do.</p>
      </div>
      <ol class="lp-steps">
        <li><span class="lp-n" aria-hidden="true">1</span><div><h3>Write it and add your photos</h3><p>Type your letter, upload your favourite photos from your phone and paste a YouTube link to your song.</p></div></li>
        <li><span class="lp-n" aria-hidden="true">2</span><div><h3>Choose the question</h3><p>Your endearment, your first date, an inside joke. Only the right answer opens the page. Everything else stays private.</p></div></li>
        <li><span class="lp-n" aria-hidden="true">3</span><div><h3>Send the link or the QR code</h3><p>Share it in a message, or print the QR code on a card, a frame or the gift tag. You can change everything later.</p></div></li>
      </ol>
    </div>
  </section>

  <section class="lp-templates" id="templates" aria-labelledby="templates-title">
    <div class="lp-wrap">
      <div class="lp-section-head">
        <h2 id="templates-title" class="lp-h2">{{ count($layouts) }} templates. Make it look like the two of you.</h2>
        <p class="lp-sub">Pick a template and a colour theme: every template works with every theme. Most of them are little things to play: break a seal, scratch a card, turn the pages, find the pairs. Switch any time from your dashboard; the link stays the same.</p>
      </div>
      <div class="theater" id="theater" hidden>
        <div class="theater-stage" id="theaterStage" tabindex="0" role="group" aria-roledescription="carousel" aria-label="All {{ count($layouts) }} templates, drag or use the arrow keys"></div>
        <div class="theater-info" aria-live="polite">
          <h3 id="theaterName"></h3>
          <p id="theaterAbout"></p>
          <p class="play" id="theaterPlay"></p>
          <div class="theater-actions"><button type="button" class="lp-btn lp-btn-line lp-btn-sm" id="theaterPrev">&larr; Previous</button><button type="button" class="lp-btn lp-btn-wax lp-btn-sm" id="theaterTry">Try it live</button><button type="button" class="lp-btn lp-btn-line lp-btn-sm" id="theaterNext">Next &rarr;</button></div>
        </div>
        <div class="theater-dots" id="theaterDots"></div>
      </div>
      <div class="chips lp-filter" role="group" aria-label="Filter the templates" id="galleryFilter">
        <button type="button" class="chip" data-filter="all" aria-pressed="true">All {{ count($layouts) }}</button>
        <button type="button" class="chip" data-filter="slideshow" aria-pressed="false">Slideshows</button>
        <button type="button" class="chip" data-filter="play" aria-pressed="false">Little games</button>
        @if ($openSeasons !== [])<button type="button" class="chip" data-filter="season" aria-pressed="false">Seasonal</button>@endif
      </div>
      <div class="lp-index" id="gallery">
        @foreach ($layouts as $key => $layout)
        @php $seasonal = !empty($layout['season']); $kind = $seasonal ? 'season' : ($layout['kind'] === 'slideshow' ? 'slideshow' : 'play'); @endphp
        <article class="lp-row" data-kind="{{ $kind }}" data-key="{{ $key }}" data-name="{{ $layout['name'] }}" data-play="{{ $layout['play'] }}" data-about="{{ $layout['description'] }}">
          <div class="shot"><div class="schem schem-{{ $key }}" aria-hidden="true"><span class="ph"></span><span class="lt"><i></i><i></i><i></i><i></i></span></div></div>
          <div class="lp-row-body">
            @if ($seasonal)<span class="season-badge">In season now</span>@endif
            <h3>{{ $layout['name'] }}</h3>
            <p>{{ $layout['description'] }}</p>
            <p class="play">{{ $layout['play'] }}</p>
          </div>
          <a class="lp-btn lp-btn-line lp-btn-sm" data-demo href="/demo?layout={{ $key }}" aria-label="Open the {{ strtolower($layout['name']) }} demo">Try it</a>
        </article>
        @endforeach
      </div>
      <div class="lp-swatches" aria-label="Colour themes">
        @foreach ($themes as $key => $swatch)
        <a href="/demo?theme={{ $key }}">
          <span class="dots" aria-hidden="true">@foreach ($swatch['colors'] as $c)<i style="background:{{ $c }}"></i>@endforeach</span>
          {{ $swatch['name'] }}
        </a>
        @endforeach
      </div>
    </div>
  </section>

  <section class="lp-pricing" id="pricing" aria-labelledby="pricing-title">
    <div class="lp-wrap">
      <div class="lp-section-head">
        <h2 id="pricing-title" class="lp-h2">Monthly, yearly, or once for good.</h2>
        <p class="lp-sub">Build your page and preview it for free. Choose a plan when you are ready to send it; pick one now and we show you the templates it includes.</p>
      </div>
      <div class="lp-finder" id="finder">
        <p class="lp-finder-title">Not sure which plan? Finish the sentence.</p>
        <p class="lp-finder-sentence">I will add
          <span class="finder-q" data-q="photos" role="group" aria-label="How many photos"><button type="button" class="chip" data-v="few" aria-pressed="false">up to 12</button><button type="button" class="chip" data-v="some" aria-pressed="false">13 to 40</button><button type="button" class="chip" data-v="lots" aria-pressed="false">more than 40</button></span>
          photos, keep it online
          <span class="finder-q" data-q="long" role="group" aria-label="How long online"><button type="button" class="chip" data-v="short" aria-pressed="false">for a month or a year</button><button type="button" class="chip" data-v="forever" aria-pressed="false">for good</button></span>
          and want
          <span class="finder-q" data-q="fancy" role="group" aria-label="Which templates"><button type="button" class="chip" data-v="no" aria-pressed="false">the classic</button><button type="button" class="chip" data-v="yes" aria-pressed="false">every</button></span>
          templates.</p>
        <p class="lp-finder-result" id="finderResult" role="status" aria-live="polite" hidden></p>
        <button type="button" class="lp-link" id="finderReset" hidden>Start over</button>
      </div>
      <div class="lp-plans">
        @foreach ($plans as $key => $plan)
        @php $monthly = $plan['prices']['month'] ?? null; $yearly = $plan['prices']['year'] ?? null; $once = $plan['prices']['once'] ?? null; @endphp
        <article class="lp-plan {{ $plan['popular'] ? 'featured' : '' }}" data-plan="{{ $key }}" data-photos="{{ $plan['photos'] }}" data-all="{{ $plan['templates'] === 'all' ? 1 : 0 }}" data-once="{{ isset($plan['prices']['once']) ? 1 : 0 }}">
          <span class="fit-badge" hidden>Best fit for you</span>
          @if ($plan['popular'])<span class="lp-tag">Most popular</span>@endif
          <h3>{{ $plan['name'] }}</h3>
          @if ($monthly !== null)
          <p class="lp-price">{{ \App\Services\Plans::money((int) $monthly) }} <small>a month</small></p>
          @if ($yearly !== null)
          <p class="lp-or">or {{ \App\Services\Plans::money((int) $yearly) }} for a whole year, paid once</p>
          @endif
          @elseif ($once !== null)
          <p class="lp-price">{{ \App\Services\Plans::money((int) $once) }} <small>once</small></p>
          <p class="lp-or">No renewals: it stays online for good.</p>
          @endif
          <p class="lp-blurb">{{ $plan['blurb'] }}</p>
          <ul>
            <li>{{ $plan['photos'] }} photos included</li>
            <li>{{ $plan['templates'] === 'all' ? 'All ' . count($layouts) . ' templates' : count($plan['templates']) . ' templates: ' . implode(', ', array_map(fn ($k) => $layouts[$k]['name'], $plan['templates'])) }}, every colour theme</li>
            <li>Your song, your letter, your question</li>
            <li>Link and printable QR code</li>
            @if ($plan['subdomain'])
            <li>Its own address: <strong>yourname.{{ $domain }}</strong></li>
            @endif
          </ul>
          <a class="lp-btn {{ $plan['popular'] ? 'lp-btn-wax' : 'lp-btn-ink' }}" href="{{ $user !== null ? '/dashboard' : '/register' }}?plan={{ $key }}">Start with {{ $plan['name'] }}</a>
        </article>
        @endforeach
      </div>
      <p class="lp-more">Need more photos? Add {{ $pack['photos'] }} for {{ \App\Services\Plans::money($pack['price']) }} whenever you like: paid once, kept for good, shared by all your pages.</p>
    </div>
  </section>

  <section class="lp-faq" aria-labelledby="faq-title">
    <div class="lp-wrap lp-faq-grid">
      <h2 id="faq-title" class="lp-h2">Good to know</h2>
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

  <section class="lp-close" aria-labelledby="close-title">
    @include('partials.landing-scene', ['theme' => $theme, 'mini' => 1])
    <div class="lp-wrap">
      <h2 id="close-title" class="lp-h2">Make it tonight.</h2>
      <p class="lp-sub">Write the letter, pick the question, send the link. They will remember opening it.</p>
      <a class="lp-btn lp-btn-wax lp-btn-lg" href="/register">Start free: build it first</a>
    </div>
  </section>
</main>

<footer class="lp-footer">
  <div class="lp-wrap">
    @include('partials.brand')
    <span><a href="/terms">Terms</a> · <a href="/privacy">Privacy</a> · Made with love in the Philippines.</span>
  </div>
</footer>

<dialog class="modal demo-modal" id="demoModal" aria-labelledby="demoTitle">
  <div class="demo-head">
    <div><h2 id="demoTitle">Template</h2><p class="small muted" id="demoPlay"></p></div>
    <button type="button" class="icon-btn" data-close aria-label="Close the demo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
  </div>
  <iframe class="demo-frame" id="demoFrame" title="Live demo of the template" loading="lazy"></iframe>
  <div class="demo-foot">
    <div class="chips" role="group" aria-label="Colour theme" id="demoThemes">
      @foreach ($themes as $key => $swatch)
      <button type="button" class="chip chip-dark" data-theme="{{ $key }}" aria-pressed="{{ $key === array_key_first($themes) ? 'true' : 'false' }}"><span class="dots" aria-hidden="true">@foreach ($swatch['colors'] as $c)<i style="background:{{ $c }}"></i>@endforeach</span> {{ $swatch['name'] }}</button>
      @endforeach
    </div>
    <div class="row">
      <button type="button" class="btn btn-ghost btn-sm" id="demoPrev">&larr; Previous</button>
      <button type="button" class="btn btn-ghost btn-sm" id="demoNext">Next &rarr;</button>
      <a class="btn btn-primary btn-sm" href="/register">Make one</a>
    </div>
  </div>
</dialog>

<script src="/assets/landing.js?v={{ (string) @filemtime(base_path('public/assets/landing.js')) }}" defer></script>
@endsection
