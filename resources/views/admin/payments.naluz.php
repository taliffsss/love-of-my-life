@extends('layouts.product')
@section('title', 'Payments · Admin · ' . $brand)
@section('content')
@include('partials.appbar')
@php
  $money = fn (int $c, ?string $cur = null): string => \App\Services\Plans::money($c, $cur ?? $currency);
  $keep = array_filter($filters, static fn ($v): bool => $v !== '');
  $link = fn (int $page): string => '/admin/payments?' . http_build_query($keep + ['page' => $page]);
  $statusNames = ['pending' => 'Pending', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];
  $pillFor = ['paid' => 'pill-active', 'pending' => 'pill-draft', 'cancelled' => 'pill-expired'];
@endphp
<main class="container admin">
  <div class="page-head"><div><h1>Payments</h1><p class="muted" style="margin-top:6px">Every order, whatever way it was paid. Checkout in use: <strong>{{ $gateway === 'none' ? 'manual (no online checkout)' : $gateway }}</strong>.</p></div></div>
  @include('partials.admin-nav', ['section' => 'payments'])

  <section class="stat-grid" aria-label="Payment totals">
    <a class="card stat" href="/admin/payments?status=paid"><span class="n">{{ $money($summary['paidAmount']) }}</span><span class="l">Received</span><span class="s">{{ number_format($summary['paid']) }} paid orders</span></a>
    <a class="card stat" href="/admin/payments?status=pending"><span class="n">{{ number_format($summary['pending']) }}</span><span class="l">Waiting for payment</span><span class="s">{{ $money($summary['pendingAmount']) }} in pending orders</span></a>
    <a class="card stat" href="/admin/payments?status=cancelled"><span class="n">{{ number_format($summary['cancelled']) }}</span><span class="l">Cancelled</span><span class="s">expired or replaced by a higher plan</span></a>
    <div class="card stat"><span class="l">Received by method</span>
      @foreach ($summary['byProvider'] as $provider => $amount)<span class="s">{{ $provider }}: {{ $money($amount) }}</span>@endforeach
      @if (count($summary['byProvider']) === 0)<span class="s">Nothing yet.</span>@endif
    </div>
  </section>

  <form class="card admin-filter" method="get" action="/admin/payments">
    <div class="field"><label for="q">Reference or e-mail</label><input class="input" id="q" name="q" type="search" maxlength="100" value="{{ $filters['q'] }}" /></div>
    <div class="field"><label for="status">Status</label>
      <select class="select" id="status" name="status"><option value="">Any</option>
        @foreach ($statusNames as $k => $label)<option value="{{ $k }}" @if ($filters['status'] === $k) selected @endif>{{ $label }}</option>@endforeach
      </select></div>
    <div class="field"><label for="provider">Method</label>
      <select class="select" id="provider" name="provider"><option value="">Any</option>
        @foreach ($providers as $p)<option value="{{ $p }}" @if ($filters['provider'] === $p) selected @endif>{{ $p }}</option>@endforeach
      </select></div>
    <button class="btn btn-primary" type="submit">Filter</button>
    @if (count($keep) > 0)<a class="btn btn-ghost" href="/admin/payments">Clear</a>@endif
  </form>

  <div class="card table-wrap">
    <table class="table">
      <thead><tr><th>Order</th><th>Customer</th><th>For</th><th class="num">Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        @foreach ($paginator as $o)
        <tr>
          <td>{{ $o->reference }}@if ((string) $o->provider_ref !== '')<br><span class="small muted">{{ $o->provider_ref }}</span>@endif</td>
          <td>{{ $emails[$o->user_id] ?? 'Deleted user' }}</td>
          <td>{{ $o->isPhotoPack() ? $o->photos . ' photos' : (($plans[$o->plan]['name'] ?? $o->plan) . ', ' . ($o->period ?? '')) }}</td>
          <td class="num">{{ $money((int) $o->amount, $o->currency) }}</td>
          <td>{{ $o->provider }}</td>
          <td><span class="pill {{ $pillFor[$o->status] ?? 'pill-draft' }}">{{ $statusNames[$o->status] ?? ucfirst($o->status) }}</span></td>
          <td>{{ \App\Support\Time::show($o->paid_at ?? $o->created_at, 'M j, Y g:i A') }}</td>
        </tr>
        @endforeach
        @if (count($paginator) === 0)<tr><td colspan="7" class="muted">No payments match.</td></tr>@endif
      </tbody>
    </table>
  </div>

  @if ($paginator->lastPage() > 1)
  <nav class="row" style="justify-content:space-between;margin:18px 0 60px" aria-label="Pages">
    @if ($paginator->currentPage > 1)<a class="btn btn-secondary btn-sm" href="{{ $link($paginator->currentPage - 1) }}">Previous</a>@else<span></span>@endif
    <span class="small muted">Page {{ $paginator->currentPage }} of {{ $paginator->lastPage() }}</span>
    @if ($paginator->hasMorePages())<a class="btn btn-secondary btn-sm" href="{{ $link($paginator->currentPage + 1) }}">Next</a>@else<span></span>@endif
  </nav>
  @endif
</main>
@endsection
