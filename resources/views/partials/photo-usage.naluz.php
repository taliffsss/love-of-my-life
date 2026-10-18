{{-- The account's photos: used, left, where the allowance comes from, and buying more. Needs $usage and $packs
     (App\Services\PhotoAllowance); $pageSlug brings the buyer back to that page after paying. --}}
@php
  $u = $usage;
  $pack = $packs[1];
  $packText = $pack['photos'] . ' more for ' . \App\Services\Plans::money($pack['price']);
  $state = $u['over'] > 0 ? 'over' : ($u['remaining'] === 0 ? 'full' : ($u['remaining'] <= 3 ? 'low' : 'ok'));
  $pct = $u['total'] > 0 ? min(100, (int) round($u['used'] / $u['total'] * 100)) : 100;
  $from = [];
  foreach ($u['sources'] as $source) {
    $from[] = $source['photos'] . ' with ' . $source['plan'] . ' (for ' . $source['recipient'] . ')';
  }
  if ($from === []) {
    $from[] = $u['included'] . ' with the free preview';
  }
  if ($u['bought'] > 0) {
    $from[] = $u['bought'] . ' bought';
  }
  $selected = max(1, min(count($packs), $u['packs_needed']));
@endphp
<div class="usage usage-{{ $state }}" id="photo-usage" data-remaining="{{ $u['remaining'] }}" data-pack="{{ $packText }}">
  <div class="usage-head">
    <strong>Photos on your account</strong>
    <span class="usage-count"><b id="usage-used">{{ $u['used'] }}</b> of <span id="usage-total">{{ $u['total'] }}</span> used · <span id="usage-left">{{ $u['over'] > 0 ? $u['over'] . ' over' : $u['remaining'] . ' left' }}</span></span>
  </div>
  <div class="meter" role="progressbar" aria-label="Photos used on your account" aria-valuemin="0" aria-valuemax="{{ $u['total'] }}" aria-valuenow="{{ $u['used'] }}"><i id="usage-meter" style="width:{{ $pct }}%"></i></div>
  <p class="small muted">Included: {{ implode(' · ', $from) }}. Every photo on every page counts; deleting one frees its place.</p>
  <p class="notice {{ $state === 'low' ? 'notice-brand' : 'notice-warn' }}" id="usage-alert" role="status" @if ($state === 'ok') hidden @endif>
    @if ($state === 'over')
      A plan ended, so your account holds more photos than it now includes. Nothing was removed; to add new ones, delete some or buy more.
    @elseif ($state === 'full')
      You have used every photo. Buy {{ $packText }} to keep adding, or delete one to make room.
    @elseif ($state === 'low')
      Only {{ $u['remaining'] }} left. When they are used up, add {{ $packText }}.
    @endif
  </p>
  <details class="pack-more" @if ($state !== 'ok' || !empty($errors['packs'])) open @endif>
    <summary>Need more photos? {{ $pack['photos'] }} for {{ \App\Services\Plans::money($pack['price']) }}</summary>
    <form method="post" action="/dashboard/photo-packs" class="pack-form" novalidate>
      @csrf
      @if (!empty($pageSlug))
      <input type="hidden" name="page" value="{{ $pageSlug }}" />
      @endif
      <div class="field">
        <label for="packs">How many</label>
        <select class="select" id="packs" name="packs" @if (!empty($errors['packs'])) aria-invalid="true" aria-describedby="packs-error" @endif>
          @foreach ($packs as $n => $option)
          <option value="{{ $n }}" @if ($n === $selected) selected @endif>{{ $option['photos'] }} photos · {{ \App\Services\Plans::money($option['price']) }}</option>
          @endforeach
        </select>
      </div>
      <button class="btn btn-primary btn-sm" type="submit">Buy photos</button>
    </form>
    @include('partials.errors', ['field' => 'packs'])
    <p class="hint">Paid once, kept for good, and shared by all your pages.</p>
  </details>
</div>
