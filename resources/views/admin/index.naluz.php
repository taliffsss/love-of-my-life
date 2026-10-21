@extends('layouts.product')
@section('title', 'Admin · ' . $brand)
@section('content')
@include('partials.appbar')
@php
  $money = fn (int $c): string => \App\Services\Plans::money($c, $currency);
  $max = max(1, max($stats['signups']));
  $typeNames = ['manual' => 'E-mail and password', 'google' => 'Google', 'facebook' => 'Facebook'];
@endphp
<main class="container admin">
  <div class="page-head"><div><h1>Admin</h1><p class="muted" style="margin-top:6px">How the product is doing. Read-only.</p></div></div>
  @include('partials.admin-nav', ['section' => 'overview'])

  <section class="stat-grid" aria-label="Key numbers">
    <a class="card stat" href="/admin/users"><span class="n">{{ number_format($stats['users']) }}</span><span class="l">Users</span><span class="s">{{ $stats['new7'] }} new in 7 days · {{ $stats['new30'] }} in 30</span></a>
    <a class="card stat" href="/admin/users?subscribers=1"><span class="n">{{ number_format($stats['subscribers']) }}</span><span class="l">Subscribers</span><span class="s">accounts with a live paid page</span></a>
    <div class="card stat"><span class="n">{{ number_format($stats['pages']) }}</span><span class="l">Pages</span><span class="s">{{ $stats['pageStatus']['active'] }} live · {{ $stats['pageStatus']['draft'] }} draft · {{ $stats['pageStatus']['expired'] }} expired</span></div>
    <div class="card stat"><span class="n">{{ $money($stats['orders']['revenue']) }}</span><span class="l">Revenue</span><span class="s">{{ $money($stats['orders']['revenue30']) }} in 30 days · {{ $stats['orders']['paid'] }} paid orders</span></div>
  </section>

  <section class="card admin-card" aria-labelledby="signups-title">
    <h2 id="signups-title">Sign-ups, last 30 days</h2>
    <div class="bars" role="img" aria-label="New users per day, last 30 days. Most in a day: {{ max($stats['signups']) }}. Total: {{ array_sum($stats['signups']) }}.">
      @foreach ($stats['signups'] as $day => $count)
      <span class="bar" title="{{ $day }}: {{ $count }}"><i style="height:{{ $count === 0 ? 2 : max(6, (int) round($count / $max * 100)) }}%"></i></span>
      @endforeach
    </div>
    <p class="small muted">{{ array_key_first($stats['signups']) }} to {{ array_key_last($stats['signups']) }}</p>
  </section>

  <p class="small muted" style="margin:-4px 0 14px">Where do visitors come from? <a href="/admin/traffic">See the traffic by campaign</a>.</p>

  <div class="admin-cols">
    <section class="card admin-card" aria-labelledby="how-title">
      <h2 id="how-title">How people sign up</h2>
      <table class="table"><tbody>
        @foreach ($typeNames as $key => $label)
        <tr><td>{{ $label }}</td><td class="num"><a href="/admin/users?type={{ $key }}">{{ number_format($stats['byType'][$key]) }}</a></td></tr>
        @endforeach
        <tr><td class="muted">Administrators</td><td class="num"><a href="/admin/users?role=admin">{{ $stats['admins'] }}</a></td></tr>
      </tbody></table>
    </section>
    <section class="card admin-card" aria-labelledby="plans-title">
      <h2 id="plans-title">Live pages by plan</h2>
      <table class="table"><tbody>
        @foreach ($plans as $key => $plan)
        @if ($plan['publish'])
        <tr><td>{{ $plan['name'] }}</td><td class="num">{{ number_format($stats['activeByPlan'][$key] ?? 0) }}</td></tr>
        @endif
        @endforeach
        <tr><td class="muted">Orders waiting for payment</td><td class="num">{{ $stats['orders']['pending'] }}</td></tr>
      </tbody></table>
    </section>
  </div>

  <div class="admin-cols">
    <section class="card admin-card" aria-labelledby="latest-title">
      <h2 id="latest-title">Newest users</h2>
      <table class="table"><tbody>
        @foreach ($latestUsers as $u)
        <tr><td>{{ $u->name }}<br><span class="small muted">{{ $u->email }}</span></td><td class="num small muted">{{ \App\Support\Time::show($u->created_at, 'M j') }}</td></tr>
        @endforeach
        @if (count($latestUsers) === 0)<tr><td class="muted">No users yet.</td></tr>@endif
      </tbody></table>
      <p class="small" style="margin-top:10px"><a href="/admin/users">All users</a></p>
    </section>
    <section class="card admin-card" aria-labelledby="orders-title">
      <h2 id="orders-title">Latest paid orders</h2>
      <table class="table"><tbody>
        @foreach ($recentOrders as $o)
        <tr><td>{{ $o->reference }}<br><span class="small muted">{{ $o->plan }}</span></td><td class="num">{{ $money((int) $o->amount) }}<br><span class="small muted">{{ \App\Support\Time::show($o->paid_at, 'M j') }}</span></td></tr>
        @endforeach
        @if (count($recentOrders) === 0)<tr><td class="muted">No paid orders yet.</td></tr>@endif
      </tbody></table>
    </section>
  </div>
</main>
@endsection
