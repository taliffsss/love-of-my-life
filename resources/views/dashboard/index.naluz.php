@extends('layouts.product')
@section('title', 'Your pages · ' . $brand)
@section('content')
@include('partials.appbar')
<main class="container">
  <div class="page-head">
    <div>
      <h1>Your pages</h1>
      <p class="muted" style="margin-top:6px">Each page is one letter for one person. Build it for free; publish it with a plan.</p>
    </div>
  </div>

  <form class="card create-form" method="post" action="/dashboard/pages" novalidate>
    @csrf
    <div class="field">
      <label for="recipient">Who is the new page for?</label>
      <input class="input" id="recipient" name="recipient" type="text" maxlength="60" required placeholder="Their name or what you call them" value="{{ $old['recipient'] ?? '' }}" @if (!empty($errors['recipient'])) aria-invalid="true" aria-describedby="recipient-error" @endif />
      @include('partials.errors', ['field' => 'recipient'])
    </div>
    <button class="btn btn-primary" type="submit" style="margin-top:28px">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
      Create page
    </button>
  </form>

  @if (count($pages) === 0)
  <section class="card empty" style="margin:22px 0 60px">
    <div class="art" aria-hidden="true"><svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor"><path d="M12 21s-8-4.900-8-11.100C4 6.700 6.300 5 8.600 5c1.500 0 2.900.8 3.400 2 .5-1.200 1.900-2 3.400-2C17.700 5 20 6.700 20 9.900 20 16.100 12 21 12 21z"/></svg></div>
    <h2>Your first page starts with a name</h2>
    <p>Type who it is for above. Then write the letter, add photos and a song, and choose the question that opens it.</p>
    <p style="margin-top:16px"><a class="btn btn-secondary" href="/demo" target="_blank" rel="noopener">See a finished demo</a></p>
  </section>
  @else
  <div class="page-grid">
    @foreach ($pages as $page)
    @php $status = $page->status(); $plan = $page->planDetails(); @endphp
    <article class="card page-card">
      <a class="cover" href="/dashboard/pages/{{ $page->slug }}" aria-label="Edit the page for {{ $page->recipient }}">
        @if (isset($covers[$page->id]))
        <img src="{{ $covers[$page->id] }}" alt="" loading="lazy" />
        @endif
        <span class="for">For {{ $page->recipient }}</span>
      </a>
      <div class="body">
        <div class="facts">
          <span class="pill pill-{{ $status }}">{{ $status === 'draft' ? 'Draft' : ($status === 'active' ? 'Live' : 'Expired') }}</span>
          <span class="pill pill-plan">{{ $plan['name'] }}</span>
          <span>{{ $counts[$page->id] ?? 0 }} photo{{ ($counts[$page->id] ?? 0) === 1 ? '' : 's' }}</span>
        </div>
        @if ($status === 'active')
        <a class="link" href="{{ $urls[$page->id] }}" target="_blank" rel="noopener">{{ preg_replace('#^https?://#', '', $urls[$page->id]) }}</a>
        @elseif ($status === 'expired')
        <p class="small muted">Expired {{ $page->expires_at?->format('M j, Y') }}. Renew to bring it back.</p>
        @else
        <p class="small muted">Only you can see it until you publish it.</p>
        @endif
        <div class="row">
          <a class="btn btn-primary btn-sm" href="/dashboard/pages/{{ $page->slug }}">Edit</a>
          <a class="btn btn-secondary btn-sm" href="/dashboard/pages/{{ $page->slug }}/preview" target="_blank" rel="noopener">Preview</a>
        </div>
      </div>
    </article>
    @endforeach
  </div>
  @endif
</main>
@endsection
