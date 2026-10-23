{{-- The ways to pay: QR Ph, and (when $ewallet) GCash and PayMaya, and (when $cards) Credit card and Mastercard. Each is a square tile; choosing one opens its form in a dialog, and QR Ph makes the payment and shows its code at once. Needs $order, $recurring (the plan renews), $renewal and $auto (method => the provider charges
     again by itself). Rules, enforced by OrdersController: a card always subscribes; GCash and PayMaya subscribe unless the
     customer picks one-time; a plan paid once (Forever) is always one-time; a renewal continues its subscription. --}}
@php
  $money = \App\Services\Plans::money($order->amount, $order->currency);
  $every = ['month' => 'every month', 'year' => 'every year'][(string) $order->period] ?? 'each period';
  $options = [
    'gcash' => ['label' => 'GCash', 'hint' => 'E-wallet', 'kind' => 'wallet', 'method' => 'gcash'],
    'paymaya' => ['label' => 'PayMaya', 'hint' => 'E-wallet', 'kind' => 'wallet', 'method' => 'paymaya'],
    'qrph' => ['label' => 'QR Ph', 'hint' => 'Any bank or e-wallet', 'kind' => 'qr', 'method' => 'qrph'],
    'card-credit' => ['label' => 'Credit card', 'hint' => 'Credit card', 'kind' => 'card', 'method' => 'card'],
    'card-mastercard' => ['label' => 'Mastercard', 'hint' => 'Credit or debit', 'kind' => 'card', 'method' => 'card'],
  ];
  if (empty($ewallet)) {
    unset($options['gcash'], $options['paymaya']); // GCash and PayMaya are hidden until PAYMONGO_EWALLET=true
  }
  if (empty($cards)) {
    unset($options['card-credit'], $options['card-mastercard']); // cards are hidden until PAYMONGO_CARDS=true
  }
  $chosen = (string) ($old['method'] ?? '');
  $once = $order->isPhotoPack() ? 'One-time payment: the photos are paid once and are yours for good.' : 'One-time payment: this plan never expires, so there is nothing to renew.';
  // what a subscription means for a way to pay
  $renews = function (string $method, string $name) use ($auto, $every): string {
    return !empty($auto[$method])
      ? 'Renews automatically ' . $every . '. Cancel any time in your account.'
      : 'Renews ' . $every . ': you will get a reminder and approve each payment with ' . $name . '. Cancel any time in your account.';
  };
@endphp
<div class="pay-grid" role="group" aria-label="How do you want to pay?">
  @foreach ($options as $id => $opt)
  @if ($opt['kind'] === 'qr')
  {{-- QR Ph needs nothing from the customer: choosing it makes the payment and the page opens the code at once --}}
  <form method="post" action="/dashboard/orders/{{ $order->reference }}/pay">
    @csrf
    <input type="hidden" name="method" value="qrph" />
    <button class="pay-tile" type="submit">
      @include('partials.pay-logo', ['id' => $id])
      <span class="pay-name"><b>{{ $opt['label'] }}</b><small>{{ $opt['hint'] }}</small></span>
    </button>
  </form>
  @else
  <button class="pay-tile" type="button" data-open="#pay-{{ $id }}" aria-haspopup="dialog">
    @include('partials.pay-logo', ['id' => $id])
    <span class="pay-name"><b>{{ $opt['label'] }}</b><small>{{ $opt['hint'] }}</small></span>
  </button>
  @endif
  @endforeach
</div>
@foreach (array_filter($options, static fn (array $o): bool => $o['kind'] !== 'qr') as $id => $opt)
<dialog class="modal pay-dialog" id="pay-{{ $id }}" aria-labelledby="pay-{{ $id }}-title" @if ($chosen !== '' && $chosen === $opt['method'] && ($opt['kind'] !== 'card' || $id === 'card-credit')) data-autoopen @endif>
  <div class="pay-dialog-head">
    @include('partials.pay-logo', ['id' => $id])
    <div class="pay-name"><h2 id="pay-{{ $id }}-title">{{ $opt['label'] }}</h2><small>{{ $opt['hint'] }}</small></div>
    <button class="icon-btn" type="button" data-close aria-label="Close">&times;</button>
  </div>
  <div class="pay-dialog-body">
    <div class="pay-panel">
      @if ($opt['kind'] === 'wallet')
      <form method="post" action="/dashboard/orders/{{ $order->reference }}/pay" class="stack pay-form" novalidate style="--gap:14px">
        @csrf
        <input type="hidden" name="method" value="{{ $opt['method'] }}" />
        <div class="field">
          <label for="phone-{{ $id }}">Your {{ $opt['label'] }} mobile number</label>
          <input class="input" id="phone-{{ $id }}" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="0917 123 4567" maxlength="20" required value="{{ $chosen === $opt['method'] ? ($old['phone'] ?? '') : '' }}" @if ($chosen === $opt['method'] && !empty($errors['phone'])) aria-invalid="true" aria-describedby="phone-error" @endif />
          @if ($chosen === $opt['method'])@include('partials.errors', ['field' => 'phone'])@endif
          <p class="hint">The number of your {{ $opt['label'] }} account. We send it to the payment provider and nowhere else.</p>
        </div>
        @if ($recurring && !$renewal)
        <fieldset class="pay-field">
          <legend class="label">One-time or subscription?</legend>
          <div class="choice-grid">
            <label class="choice"><input type="radio" name="billing" value="subscription" checked /><span class="box"><span class="txt"><b>Subscribe</b><small>{{ $renews($opt['method'], $opt['label']) }}</small></span></span></label>
            <label class="choice"><input type="radio" name="billing" value="one_time" /><span class="box"><span class="txt"><b>One-time payment</b><small>Pay once for {{ ['month' => 'one month', 'year' => 'one year'][(string) $order->period] ?? 'this period' }}. Renew whenever you like.</small></span></span></label>
          </div>
        </fieldset>
        @elseif ($renewal)
        <p class="small muted">This is the renewal of your subscription. {{ $renews($opt['method'], $opt['label']) }}</p>
        @else
        <p class="small muted">{{ $once }}</p>
        @endif
        <button class="btn btn-primary btn-lg" type="submit">Continue to {{ $opt['label'] }}</button>
      </form>
      @else
      <form class="stack pay-form card-form" data-card-form data-card-url="/dashboard/orders/{{ $order->reference }}/card" data-status-url="/dashboard/orders/{{ $order->reference }}/status" novalidate style="--gap:14px">
        {{-- no name= on these fields: the card number is read by the script and sent to the payment provider, never to this server --}}
        <div class="field"><label for="ccname-{{ $id }}">Name on card</label><input class="input" id="ccname-{{ $id }}" data-cc="name" type="text" autocomplete="cc-name" maxlength="80" required /></div>
        <div class="field"><label for="ccnum-{{ $id }}">Card number</label><input class="input" id="ccnum-{{ $id }}" data-cc="number" type="text" inputmode="numeric" autocomplete="cc-number" maxlength="23" placeholder="1234 5678 9012 3456" required /></div>
        <div class="row" style="gap:14px;align-items:flex-start">
          <div class="field" style="flex:1 1 120px"><label for="ccexp-{{ $id }}">Expiry (MM/YY)</label><input class="input" id="ccexp-{{ $id }}" data-cc="exp" type="text" inputmode="numeric" autocomplete="cc-exp" maxlength="5" placeholder="MM/YY" required /></div>
          <div class="field" style="flex:1 1 120px"><label for="cccvc-{{ $id }}">CVC</label><input class="input" id="cccvc-{{ $id }}" data-cc="cvc" type="text" inputmode="numeric" autocomplete="cc-csc" maxlength="4" placeholder="123" required /></div>
        </div>
        @if ($recurring)
        <p class="small muted"><strong>Subscription.</strong> {{ $renews('card', 'your card') }}</p>
        @else
        <p class="small muted">{{ $once }}</p>
        @endif
        <p class="small muted">Your card number goes straight to the payment provider. This site never sees or stores it.</p>
        <p class="notice notice-warn" data-cc-error role="alert" hidden></p>
        <button class="btn btn-primary btn-lg" type="submit" data-cc-submit>Pay {{ $money }}</button>
      </form>
      @endif
    </div>
  </div>
</dialog>
@endforeach
