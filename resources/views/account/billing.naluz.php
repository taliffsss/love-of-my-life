@extends('layouts.product')
@section('title', 'Billing · ' . $brand)
@section('content')
@include('partials.appbar')
<main class="container account" style="max-width:860px">
  <div class="page-head"><div>
    <h1>Your account</h1>
    <p class="muted" style="margin-top:6px">What you are subscribed to, what you have paid, and how many photos you can add.</p>
  </div></div>
  @include('partials.account-tabs', ['section' => $section])

  @foreach ($due as $renewal)
  <p class="notice notice-brand" role="status">A renewal is waiting: <a href="/dashboard/orders/{{ $renewal->reference }}">pay {{ \App\Services\Plans::money($renewal->amount, $renewal->currency) }}</a> ({{ $renewal->reference }}).</p>
  @endforeach

  <section class="card section-card" aria-labelledby="subs-title">
    <div class="section-head"><div><h2 id="subs-title">Subscriptions</h2><p>Cancelling keeps a page online until the time you paid for runs out.</p></div></div>
    <div class="section-body">
      @if (count($subscriptions) === 0)
      <p class="muted">No running subscription. Pages are free to build; choose a plan on a page when you are ready to send it. <a href="/dashboard">Go to your pages</a></p>
      @else
      <ul class="subs-list">
        @foreach ($subscriptions as $sub)
        @php $forPage = $pages[$sub->page_id] ?? null; @endphp
        <li>
          <div>
            <strong>{{ $plans[$sub->plan]['name'] ?? $sub->plan }}@if ($forPage !== null) for {{ $forPage->recipient }}@endif</strong>
            <span class="small muted">{{ \App\Services\Plans::money($sub->amount, $sub->currency) }} {{ ['month' => 'every month', 'year' => 'every year'][$sub->period] ?? '' }} · {{ \App\Models\Order::METHODS[$sub->method] ?? $sub->method }} · {{ $sub->mode === 'auto' ? 'renews automatically' : 'you approve each payment' }}@if ($forPage !== null && $forPage->expires_at !== null) · paid until {{ \App\Support\Time::show($forPage->expires_at) }}@endif</span>
          </div>
          <form method="post" action="/dashboard/subscriptions/{{ $sub->id }}/cancel" data-confirm="Stop this subscription? Your page stays online until the time you paid for runs out.">
            @csrf
            <input type="hidden" name="back" value="/account/billing" />
            <button class="btn btn-ghost btn-sm" type="submit" style="color:var(--danger)">Cancel</button>
          </form>
        </li>
        @endforeach
      </ul>
      @endif
    </div>
  </section>

  <section class="card usage-card" aria-label="Photos on your account" style="margin-top:18px">
    @include('partials.photo-usage', ['usage' => $usage, 'packs' => $packs, 'upgradeUrl' => '/dashboard'])
  </section>

  <section class="card section-card" aria-labelledby="details-title" style="margin-top:18px">
    <div class="section-head"><div><h2 id="details-title">Billing details</h2><p>Receipts use these details. Change them in your profile.</p></div>
      <a class="btn btn-secondary btn-sm" href="/account">Edit</a></div>
    <div class="section-body">
      <dl class="facts-list">
        <div><dt>Name</dt><dd>{{ $user->name }}</dd></div>
        <div><dt>E-mail</dt><dd>{{ $user->email }}</dd></div>
        <div><dt>Phone</dt><dd>{{ $user->phone ?: 'Not added' }}</dd></div>
        <div><dt>Address</dt><dd>{{ $user->addressLine() !== '' ? $user->addressLine() : 'Not added' }}</dd></div>
      </dl>
    </div>
  </section>

  <section class="card section-card" aria-labelledby="history-title" style="margin:18px 0 60px">
    <div class="section-head"><div><h2 id="history-title">Payment history</h2>
      <p>@if (count($orders) > 0)You have paid {{ \App\Services\Plans::money($totalPaid, $currency) }} in total.@else Nothing yet.@endif</p></div></div>
    <div class="section-body">
      @if (count($orders) === 0)
      <p class="muted">Your orders will show up here: plans, renewals and photo packs.</p>
      @else
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th scope="col">Date</th><th scope="col">For</th><th scope="col">Amount</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Order</span></th></tr></thead>
          <tbody>
            @foreach ($orders as $order)
            @php
              $forPage = $order->page_id !== null ? ($pages[$order->page_id] ?? null) : null;
              $what = $order->plan === \App\Models\Order::PHOTO_PACK ? $order->photos . ' extra photos' : (($plans[$order->plan]['name'] ?? $order->plan) . ($forPage !== null ? ' for ' . $forPage->recipient : ''));
              $when = $order->paid_at ?? $order->created_at;
            @endphp
            <tr>
              <td data-label="Date">{{ $when !== null ? \App\Support\Time::show($when) : '' }}</td>
              <td data-label="For">{{ $what }}<span class="small muted" style="display:block">{{ $order->reference }}@if ($order->method !== null) · {{ \App\Models\Order::METHODS[$order->method] ?? $order->method }}@endif</span></td>
              <td data-label="Amount">{{ \App\Services\Plans::money($order->amount, $order->currency) }}</td>
              <td data-label="Status"><span class="pill pill-{{ $order->status === 'paid' ? 'active' : ($order->status === 'pending' ? 'expired' : 'draft') }}">{{ ucfirst($order->status) }}</span></td>
              <td><a href="/dashboard/orders/{{ $order->reference }}">{{ $order->status === 'pending' ? 'Pay' : 'View' }}</a></td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @endif
    </div>
  </section>
</main>
@endsection
