@extends('layouts.product')
@section('title', 'Your pages · ' . $brand)
@section('content')
@include('partials.appbar')
<main class="container">
  <div class="page-head">
    <div>
      <h1>Your pages</h1>
      <p class="muted" style="margin-top:6px">Each page is one letter for one person. Build it for free; publish it with a plan. Your photos are shared by all your pages.</p>
    </div>
  </div>

  <form class="card create-form" method="post" action="/dashboard/pages" novalidate>
    @csrf
    <div class="field">
      <label for="recipient">Who is the new page for?</label>
      <input class="input" id="recipient" name="recipient" type="text" maxlength="60" required placeholder="Their name or what you call them" value="{{ $old['recipient'] ?? '' }}" @if (!empty($errors['recipient'])) aria-invalid="true" aria-describedby="recipient-error" @endif />
      @include('partials.errors', ['field' => 'recipient'])
    </div>
    <fieldset class="occasions" style="flex-basis:100%">
      <legend>Start from an occasion <span class="muted">(optional)</span></legend>
      <div class="occasion-tabs" role="group" aria-label="Occasion">
        @php $first = array_key_first($occasions); @endphp
        @foreach ($occasions as $okey => $occasion)
        <label class="occasion-tab"><input type="radio" name="occasion" value="{{ $okey }}" @if ($okey === $first) checked @endif /><span>{{ $occasion['name'] }}</span></label>
        @endforeach
      </div>
      @foreach ($occasions as $okey => $occasion)
      <div class="template-grid" data-occasion="{{ $okey }}">
        <p class="small muted" style="grid-column:1/-1">{{ $occasion['blurb'] }}</p>
        @foreach ($templates[$okey] as $tpl)
        <label class="template-card">
          <input type="radio" name="template" value="{{ $tpl['key'] }}" @if (($old['template'] ?? '') === $tpl['key']) checked @endif />
          <span class="name">{{ $tpl['name'] }}</span>
          <span class="small muted">{{ $tpl['blurb'] }}</span>
        </label>
        @endforeach
      </div>
      @endforeach
      @include('partials.errors', ['field' => 'template'])
      <p class="small muted">Pick one for a ready-made letter, question, layout and colours. You can change all of it afterwards.</p>
    </fieldset>
    <button class="btn btn-primary" type="submit">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
      Create page
    </button>
  </form>

  @foreach ($renewals as $renewal)
  <div class="notice notice-brand" role="status" style="margin-bottom:14px">A subscription renewal is due: <a href="/dashboard/orders/{{ $renewal->reference }}">pay {{ \App\Services\Plans::money($renewal->amount, $renewal->currency) }}</a> ({{ $renewal->reference }}).</div>
  @endforeach

  @if (count($accountSubscriptions) > 0)
  <section class="card subs-card" aria-labelledby="subs-title">
    <h2 id="subs-title">Your subscriptions</h2>
    <ul class="subs-list">
      @foreach ($accountSubscriptions as $sub)
      @php $forPage = null; foreach ($pages as $p) { if ($p->id === $sub->page_id) { $forPage = $p; } } @endphp
      <li>
        <div>
          <strong>{{ $plans[$sub->plan]['name'] ?? $sub->plan }}@if ($forPage !== null) for {{ $forPage->recipient }}@endif</strong>
          <span class="small muted">{{ \App\Services\Plans::money($sub->amount, $sub->currency) }} {{ ['month' => 'every month', 'year' => 'every year'][$sub->period] ?? '' }} · {{ \App\Models\Order::METHODS[$sub->method] ?? $sub->method }} · {{ $sub->mode === 'auto' ? 'renews automatically' : 'you approve each payment' }}@if ($forPage !== null && $forPage->expires_at !== null) · paid until {{ \App\Support\Time::show($forPage->expires_at) }}@endif</span>
        </div>
        <form method="post" action="/dashboard/subscriptions/{{ $sub->id }}/cancel" data-confirm="Stop this subscription? Your page stays online until the time you paid for runs out.">
          @csrf
          <button class="btn btn-ghost btn-sm" type="submit" style="color:var(--danger)">Unsubscribe</button>
        </form>
      </li>
      @endforeach
    </ul>
  </section>
  @endif

  <section class="card usage-card" aria-label="Photos on your account">
    @include('partials.photo-usage', ['usage' => $usage, 'packs' => $packs, 'upgradeUrl' => $upgradeUrl])
  </section>

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
        <p class="small muted">Expired {{ \App\Support\Time::show($page->expires_at) }}. Renew to bring it back.</p>
        @else
        <p class="small muted">Only you can see it until you publish it.</p>
        @endif
        <div class="row">
          <a class="btn btn-primary btn-sm" href="/dashboard/pages/{{ $page->slug }}">Edit</a>
          <a class="btn btn-secondary btn-sm" href="/dashboard/pages/{{ $page->slug }}/preview" target="_blank" rel="noopener">Preview</a>
          <button class="btn btn-secondary btn-sm" type="button" data-open="#delete-{{ $page->slug }}" style="color:var(--danger)">Delete</button>
        </div>
      </div>
    </article>
    @endforeach
  </div>
  @foreach ($pages as $page)
  <dialog class="modal" id="delete-{{ $page->slug }}" aria-labelledby="delete-title-{{ $page->slug }}">
    <form method="post" action="/dashboard/pages/{{ $page->slug }}">
      @csrf
      @method('DELETE')
      <div class="modal-body">
        <h2 id="delete-title-{{ $page->slug }}">Delete the page for {{ $page->recipient }}?</h2>
        <p class="muted">Its letter, address and {{ $counts[$page->id] ?? 0 }} photo{{ ($counts[$page->id] ?? 0) === 1 ? '' : 's' }} are removed and the link stops working. Photos stop counting towards your account. This cannot be undone.</p>
        <div class="field">
          <label for="confirm-{{ $page->slug }}">Type <strong>delete</strong> to confirm</label>
          <input class="input" id="confirm-{{ $page->slug }}" name="confirm" autocomplete="off" required pattern="delete" />
        </div>
      </div>
      <div class="section-foot">
        <button class="btn btn-ghost" type="button" data-close>Cancel</button>
        <button class="btn btn-danger" type="submit">Delete forever</button>
      </div>
    </form>
  </dialog>
  @endforeach
  @endif
</main>
@endsection
