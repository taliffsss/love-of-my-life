@extends('layouts.product')
@section('title', 'Online wedding, debut, christening invitations · ' . $brand)
@section('description', 'Make your wedding, debut or christening invitation online: pick a template, add photos and your message, then share the link or print the QR code on a card.')
@section('bodyClass', 'lp lp-t-' . $theme)
@section('robots', 'index, follow')
@section('siteCss', 'page')
@section('uiJs', 'ui-public')
{{-- one Google Fonts request for the page: the layout's families plus Newsreader (its italic, without the optical-size axis: 64 KB instead of 147 KB) --}}
@push('hints')
    <link rel="preload" as="image" href="/assets/invitations/lettie-invite.png" fetchpriority="high" />
@endpush
@push('head')
    <link rel="canonical" href="{{ $pageUrl }}" />
    <script type="application/ld+json">{!! json_for_html($structuredData) !!}</script>
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="{{ $brand }}" />
    <meta property="og:title" content="{{ $brand }}: invitations, made online" />
    <meta property="og:description" content="Pick a template, add your photos and your message, then share the link or print the QR code on a card." />
    <meta property="og:url" content="{{ $pageUrl }}" />
    <meta property="og:image" content="{{ $siteUrl }}assets/invitations/og-invitations.jpg" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Lettie, the little love letter, holding an invitation with a QR code" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $brand }}: invitations, made online" />
    <meta name="twitter:description" content="Pick a template, add your photos and your message, then share the link or print the QR code on a card." />
    <meta name="twitter:image" content="{{ $siteUrl }}assets/invitations/og-invitations.jpg" />
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
      <a class="btn btn-light btn-sm" href="/register">Create an invitation</a>
      @endif
    </nav>
  </div>
</header>

<div class="hero lp-hero" id="hero" data-season="love">
  <div class="lp-wrap lp-hero-grid">
    <div class="lp-hero-copy">
      <p class="lp-kicker">Weddings, debuts, christenings and more</p>
      <h1 class="lp-h1">Make your invitation online. No printing.</h1>
      <p class="lp-lead">Tired of printing invitations? Make one online: pick a template, add your photos and your message, then share the link or print the QR code on a card. Guests open it in their browser, with nothing to install.</p>
      <div class="lp-cta">
        <a class="lp-btn lp-btn-wax" href="/register">Start free: build it first</a>
        <a class="lp-btn lp-btn-line" href="/demo?layout=envelope&amp;theme=blush&amp;occasion=invitation">See a sample invitation</a>
      </div>
      <p class="lp-fine">Build and preview for free. Choose a plan when you are ready to send it.</p>
    </div>
    <div class="lp-stage inv-stage">
      <img class="inv-lettie" src="/assets/invitations/lettie-invite.png" width="560" height="415" fetchpriority="high" alt="Lettie, the little love letter, holding an invitation with a QR code" />
      <figure class="inv-phone">
        <img src="/assets/invitations/wedding.jpg" width="390" height="844" alt="A wedding invitation opened on a phone: the date, the place and the photos" />
        <figcaption>A real invitation page, with made-up names.</figcaption>
      </figure>
    </div>
  </div>
</div>

<main>
  <section class="lp-facts" aria-label="What to know">
    <ul class="lp-wrap">
      <li><b>No printing needed</b><span>Share a link, or print only the QR code.</span></li>
      <li><b>No app to install</b><span>Guests open a link in any browser.</span></li>
      <li><b>Fix it later</b><span>Change the time or the venue; the link and the QR code stay the same.</span></li>
      <li><b>Free to start</b><span>Build and preview before you choose a plan.</span></li>
    </ul>
  </section>

  <section class="lp-how" aria-labelledby="how">
    <div class="lp-wrap lp-how-grid">
      <div class="lp-how-head">
        <h2 id="how" class="lp-h2">Make an online invitation in three steps.</h2>
        <p class="lp-sub">Build it tonight, send it when the details are final. Nothing goes out until you do.</p>
      </div>
      <ol class="lp-steps">
        <li><span class="lp-n" aria-hidden="true">1</span><div><h3>Pick a template</h3><p>Wedding, debut, christening, baby shower, engagement party and more. Each one comes with wording you can change.</p></div></li>
        <li><span class="lp-n" aria-hidden="true">2</span><div><h3>Add your photos and your message</h3><p>Write the date, the place and the dress code, and upload your photos from your phone.</p></div></li>
        <li><span class="lp-n" aria-hidden="true">3</span><div><h3>Share the link or the QR code</h3><p>Send the link in a message or a group chat, or print the QR code on a small card. Guests scan it and the invitation opens.</p></div></li>
      </ol>
    </div>
  </section>

  <section class="lp-templates inv-templates" id="templates" aria-labelledby="templates-title">
    <div class="lp-wrap">
      <div class="lp-section-head">
        <h2 id="templates-title" class="lp-h2">Invitation templates</h2>
        <p class="lp-sub">Open a sample to see how it feels. Every template can be tried free; the plan shown is what you need to send it.</p>
      </div>
      <div class="inv-grid">
        @foreach ($templates as $t)
        <article class="inv-card">
          <h3>{{ $t['name'] }}</h3>
          <p>{{ $t['blurb'] }}</p>
          <p class="inv-plan">@if ($t['seasonal'])In season now · @endif{{ $t['plan'] === null ? 'Not on a plan yet' : 'On ' . $t['plan'] . ' and above' }}</p>
          <a class="lp-btn lp-btn-line lp-btn-sm" href="/demo?layout={{ $t['key'] }}&amp;occasion=invitation" aria-label="Open a sample {{ strtolower($t['name']) }} invitation">See a sample</a>
        </article>
        @endforeach
      </div>
      <div class="inv-print">
        <img src="/assets/invitations/lettie-invite-wedding.png" width="320" height="237" alt="" loading="lazy" />
        <div>
          <h3>Still want paper? Print less, not nothing.</h3>
          <p>Print just the QR code on a small card. It opens the full invitation, with every detail and photo, and it keeps working when you change the details.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="lp-pricing inv-plans" id="plans" aria-labelledby="plans-title">
    <div class="lp-wrap">
      <div class="lp-section-head">
        <h2 id="plans-title" class="lp-h2">Free to build. Choose a plan to send it.</h2>
        <p class="lp-sub">Build the invitation and preview it for free; only you can see it. When it is ready, pick the plan that has your template.</p>
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
        <details>
          <summary>Can guests reply from the invitation?</summary>
          <p>Not yet. The invitation tells guests by when to let you know, and they reply to you by message. We do not collect replies or keep a guest list.</p>
        </details>
        <details>
          <summary>Do my guests need an app or an account?</summary>
          <p>No. They open the link, or scan the QR code, in their phone’s browser.</p>
        </details>
        <details>
          <summary>What if the time or the venue changes?</summary>
          <p>Edit the invitation in your dashboard. The link and the QR code stay the same, so nothing needs reprinting.</p>
        </details>
        <details>
          <summary>Does the invitation need a question to open?</summary>
          <p>No. On an invitation the question is optional; with it off, the page opens with one tap. Turn it on if you want only certain people to read it.</p>
        </details>
        <details>
          <summary>Is it public?</summary>
          <p>Only people with the link or the QR code can find it, and pages are hidden from search engines. Be careful with photos of children on a link that anyone you share it with can forward.</p>
        </details>
        <details>
          <summary>How do I pay?</summary>
          <p>Pick a plan in your dashboard. You will see the amount and how to pay.</p>
        </details>
      </div>
    </div>
  </section>

  <section class="lp-close" aria-labelledby="close-title">
    <div class="lp-wrap">
      <h2 id="close-title" class="lp-h2">Invite, don’t print.</h2>
      <p class="lp-sub">Pick a template, add your photos and your message, share the link. Free to build and preview.</p>
      <a class="lp-btn lp-btn-wax lp-btn-lg" href="/register">Start free: build it first</a>
    </div>
  </section>
</main>

<footer class="lp-footer">
  <div class="lp-wrap">
    @include('partials.brand')
    <span><a href="/">Love pages</a> · <a href="/terms">Terms</a> · <a href="/privacy">Privacy</a> · Made with love in the Philippines.</span>
  </div>
</footer>
@endsection
