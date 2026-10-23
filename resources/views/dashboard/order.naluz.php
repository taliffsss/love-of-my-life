@extends('layouts.product')
@section('title', 'Order ' . $order->reference . ' · ' . $brand)
@section('content')
@include('partials.appbar')
<main class="narrow">
  <section class="card order-card" aria-labelledby="order-title">
    <div class="section-head">
      <div>
        <p class="eyebrow">Order {{ $order->reference }}</p>
        <h1 id="order-title" class="display" style="font-size:2rem;margin-top:6px">
          @if ($order->status === 'paid')
            Paid. Thank you!
          @elseif ($order->isPhotoPack())
            {{ $order->photos }} more photos for your account
          @else
            {{ $plan['name'] ?? $order->plan }} for {{ $page?->recipient ?? 'your page' }}
          @endif
        </h1>
      </div>
      <span class="pill {{ $order->status === 'paid' ? 'pill-active' : 'pill-draft' }}">{{ ucfirst($order->status) }}</span>
    </div>
    <div class="section-body stack" style="--gap:18px">
      <dl class="kv">
        @if ($order->isPhotoPack())
        <dt>Photos</dt><dd>{{ $order->photos }} more, kept for good and shared by all your pages</dd>
        @else
        <dt>Plan</dt><dd>{{ $plan['name'] ?? $order->plan }}, {{ ['month' => 'one month', 'year' => 'one year', 'once' => 'for good'][$order->period] ?? $order->period }}</dd>
        @endif
        <dt>Amount</dt><dd>{{ \App\Services\Plans::money($order->amount, $order->currency) }}</dd>
        <dt>Reference</dt><dd>{{ $order->reference }}</dd>
        @if ($order->status === 'paid' && $order->method !== null)
        <dt>Paid with</dt><dd>{{ $methods[$order->method] ?? $order->method }} · {{ $order->billing === 'subscription' ? 'subscription' : 'one-time payment' }}</dd>
        @endif
        @if ($order->paid_at !== null)
        <dt>Paid</dt><dd>{{ \App\Support\Time::show($order->paid_at, 'M j, Y g:i A') }} {{ \App\Support\Time::label() }}</dd>
        @endif
      </dl>
      <p class="small muted" id="no-refund"><strong>No refunds.</strong> Every plan, subscription and photo pack is non-refundable. To stop renewals, cancel a subscription before its next date. See the <a href="/terms#refunds">Terms</a>.</p>

      @if ($order->status === 'pending')
        @if ($online)
          @if ($attempt !== null)
          <div class="pay-wait" id="order-wait" data-status-url="/dashboard/orders/{{ $order->reference }}/status" data-expires="{{ $attempt->expires_at->getTimestamp() }}" data-go="{{ $go ? '1' : '0' }}">
            <p><strong>Waiting for your payment…</strong> <span class="small muted" id="wait-state" role="status"></span></p>
            @if ($attempt->method === 'qrph')
            <p>Scan the QR code with your banking or e-wallet app. This page updates by itself.</p>
            @if ($qr !== null)
            <button class="btn btn-primary btn-lg" type="button" data-open="#qr-dialog">Show QR code</button>
            <dialog class="modal pay-dialog" id="qr-dialog" aria-labelledby="qr-title" @if ($go) data-autoopen @endif>
              <div class="pay-dialog-head">
                @include('partials.pay-logo', ['id' => 'qrph'])
                <div class="pay-name"><h2 id="qr-title">Scan to pay {{ \App\Services\Plans::money($order->amount, $order->currency) }}</h2><small>QR Ph · any bank or e-wallet</small></div>
                <button class="icon-btn" type="button" data-close aria-label="Close">&times;</button>
              </div>
              <div class="pay-dialog-body">
                <img class="pay-qr" src="{{ $qr }}" width="260" height="260" alt="QR Ph code to pay {{ \App\Services\Plans::money($order->amount, $order->currency) }} for order {{ $order->reference }}" />
                <a class="btn btn-primary btn-lg" href="{{ $qr }}" download="QRPh-{{ $order->reference }}.png">Download QR image</a>
                <p class="small muted" style="text-align:center"><strong>On this phone?</strong> Download the image, then open your banking or e-wallet app and choose <em>Upload QR</em> (or <em>Scan from gallery</em>). On another device, choose <em>Scan QR</em> and point the camera at this code. The order turns paid by itself once the payment arrives.</p>
              </div>
            </dialog>
            @else
            <p class="notice notice-warn">The QR code could not be loaded. Choose "Pay another way" and show it again.</p>
            @endif
            @elseif ((string) $attempt->redirect_url !== '')
            <p>Approve the payment in {{ $methods[$attempt->method] ?? $attempt->method }}, then come back: this page updates by itself.</p>
            <a class="btn btn-primary btn-lg" id="continue-link" href="{{ $attempt->redirect_url }}" rel="noopener">Continue to {{ $methods[$attempt->method] ?? $attempt->method }}</a>
            @else
            <p>Confirming your card payment. If your bank asked you to approve it, that opens in a moment.</p>
            @endif
          </div>
          <details class="pay-change">
            <summary>Pay another way</summary>
            @include('partials.pay-methods')
          </details>
          @else
          @if ($order->method !== null)<div class="notice notice-warn">That payment was not completed. Choose how to pay below.</div>@endif
          @include('partials.pay-methods')
          @endif
        @elseif ($canFakePay)
        <div class="notice notice-warn">Test checkout: no real money moves. This button only appears while PAYMENTS_DRIVER=fake.</div>
        <form method="post" action="/dashboard/orders/{{ $order->reference }}/fake-pay">
          @csrf
          <button class="btn btn-primary btn-lg" type="submit">Pay {{ \App\Services\Plans::money($order->amount, $order->currency) }} (test)</button>
        </form>
        @elseif ($hosted)
        <div class="notice notice-brand">Pay securely on {{ ucfirst($order->provider) }}'s page. Once it confirms, this order turns paid and your page goes live; if you have just paid, this can take a minute: reload to check.</div>
        @if ((string) $order->checkout_url !== '')
        <a class="btn btn-primary btn-lg" href="{{ $order->checkout_url }}" rel="noopener">Pay {{ \App\Services\Plans::money($order->amount, $order->currency) }}</a>
        @endif
        @else
        <div class="notice notice-brand">
          <div class="stack" style="--gap:6px">
            <strong>How to pay</strong>
            <p>{{ $instructions }}</p>
            <p>Amount: <strong>{{ \App\Services\Plans::money($order->amount, $order->currency) }}</strong> · Reference: <strong>{{ $order->reference }}</strong></p>
          </div>
        </div>
        @endif
      @endif
    </div>
    <div class="section-foot">
      @if ($page !== null)
      <a class="btn btn-secondary" href="/dashboard/pages/{{ $page->slug }}#{{ $order->isPhotoPack() ? 'photos' : 'plan' }}">Back to the page</a>
      @else
      <a class="btn btn-secondary" href="/dashboard">Your pages</a>
      @endif
    </div>
  </section>
</main>
@endsection
