@extends('layouts.product')
@section('title', 'Traffic · Admin · ' . $brand)
@section('content')
@include('partials.appbar')
@php
  $money = fn (int $c): string => \App\Services\Plans::money($c, $currency);
  $t = $traffic['total'];
  $pct = fn (?float $v): string => $v === null ? '–' : rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
  $name = fn (string $s): string => $s === 'direct' ? 'direct' : $s;
@endphp
<main class="container admin">
  <div class="page-head"><div><h1>Traffic</h1><p class="muted" style="margin-top:6px">Where visitors come from, and what they turn into. Read-only.</p></div></div>
  @include('partials.admin-nav', ['section' => 'traffic'])

  <div class="row" style="justify-content:space-between;margin:6px 0 16px;flex-wrap:wrap;gap:10px">
    <div class="chips" role="group" aria-label="Period">
      @foreach ([7, 30, 90] as $d)
      <a class="chip chip-dark" href="/admin/traffic?days={{ $d }}" @if ($traffic['days'] === $d) aria-pressed="true" @else aria-pressed="false" @endif style="text-decoration:none">Last {{ $d }} days</a>
      @endforeach
    </div>
    <p class="small muted" style="margin:0">Visits and visitors are counted in the period. Sign-ups are the accounts created in it, with the first campaign that brought them; customers are those who have paid. A rate above 100% means people signed up in this period after visiting before it.</p>
  </div>

  <section class="stat-grid" aria-label="Key numbers">
    <div class="card stat"><span class="n">{{ number_format($t['visitors']) }}</span><span class="l">Visitors</span><span class="s">{{ number_format($t['visits']) }} visits</span></div>
    <div class="card stat"><span class="n">{{ number_format($t['signups']) }}</span><span class="l">Sign-ups</span><span class="s">{{ $pct($t['visitors'] > 0 ? round($t['signups'] / $t['visitors'] * 100, 1) : null) }} of visitors</span></div>
    <div class="card stat"><span class="n">{{ number_format($t['customers']) }}</span><span class="l">Customers</span><span class="s">paid, of those sign-ups</span></div>
    <div class="card stat"><span class="n">{{ $money($t['revenue']) }}</span><span class="l">Revenue</span><span class="s">from those customers</span></div>
  </section>

  @foreach ([['By source and medium', 'bySource', false, 'No visits recorded yet. Share a link with UTM tags (marketing/UTM.md) and they appear here.'], ['By campaign', 'byCampaign', true, 'No campaigns yet.'], ['By ad or post (utm_content)', 'byContent', false, 'No tagged ads or posts yet.']] as [$title, $key, $withSource, $empty])
  <section class="card admin-card" aria-labelledby="t-{{ $key }}">
    <h2 id="t-{{ $key }}">{{ $title }}</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr>
          @if ($key === 'bySource')<th scope="col">Source</th><th scope="col">Medium</th>@endif
          @if ($key === 'byCampaign')<th scope="col">Campaign</th><th scope="col">Source / medium</th>@endif
          @if ($key === 'byContent')<th scope="col">Campaign</th><th scope="col">Ad or post</th>@endif
          <th scope="col" class="num">Visitors</th><th scope="col" class="num">Sign-ups</th><th scope="col" class="num">Rate</th><th scope="col" class="num">Customers</th><th scope="col" class="num">Revenue</th>
        </tr></thead>
        <tbody>
          @foreach ($traffic[$key] as $r)
          <tr>
            @if ($key === 'bySource')<td>{{ $r['source'] }}</td><td>{{ $r['medium'] }}</td>@endif
            @if ($key === 'byCampaign')<td>{{ $r['campaign'] }}</td><td>{{ $r['source'] }} / {{ $r['medium'] }}</td>@endif
            @if ($key === 'byContent')<td>{{ $r['campaign'] }}</td><td>{{ $r['content'] }}</td>@endif
            <td class="num">{{ number_format($r['visitors']) }}</td><td class="num">{{ number_format($r['signups']) }}</td><td class="num">{{ $pct($r['rate']) }}</td><td class="num">{{ number_format($r['customers']) }}</td><td class="num">{{ $money($r['revenue']) }}</td>
          </tr>
          @endforeach
          @if (count($traffic[$key]) === 0)<tr><td colspan="7" class="muted">{{ $empty }}</td></tr>@endif
        </tbody>
      </table>
    </div>
  </section>
  @endforeach
</main>
@endsection
