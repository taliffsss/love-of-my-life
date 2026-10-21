@extends('layouts.product')
@section('title', 'Confirm your e-mail · ' . $brand)
@section('content')
<div class="auth">
  @include('partials.auth-art', ['quote' => 'Nearly there. One code and your page can begin.', 'note' => 'We only use your e-mail to confirm it is you and to send receipts.', 'theme' => $theme ?? 'default'])
  <main class="auth-main">
    <div class="auth-card">
      <div class="row" style="justify-content:space-between">
        <span class="hide-art">@include('partials.brand')</span>
      </div>
      <h1 class="display">{{ $changing ? 'Confirm your new e-mail' : 'Check your inbox' }}</h1>
      <p class="muted">We sent a 6-digit code to <strong>{{ $address }}</strong>. It works for 15 minutes.</p>
      <form method="post" action="/email/verify" class="stack" data-validate novalidate>
        @csrf
        <div class="field">
          <label for="code">Confirmation code</label>
          <input class="input code-input" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="7" placeholder="000000" required pattern="\s*[0-9]{3}\s?[0-9]{3}\s*" data-msg-required="Enter the 6-digit code from the e-mail." data-msg-pattern="The code has 6 digits." autofocus @if (!empty($errors['code'])) aria-invalid="true" aria-describedby="code-error" @endif />
          @include('partials.errors', ['field' => 'code'])
        </div>
        <button class="btn btn-primary btn-lg" type="submit" style="width:100%">Confirm e-mail</button>
      </form>
      <form method="post" action="/email/verification-notification" class="stack" style="margin-top:14px">
        @csrf
        <button class="btn btn-secondary" type="submit" style="width:100%" @if (!$canResend) disabled @endif>Send a new code</button>
        <p class="hint" style="text-align:center">{{ $canResend ? 'Nothing in your inbox? Check spam, or ask for a new code.' : 'A code was just sent. You can ask for another in a minute.' }}</p>
      </form>
      <div class="row" style="justify-content:space-between;margin-top:14px">
        <a href="/account">{{ $changing ? 'Use a different address' : 'Wrong e-mail? Change it' }}</a>
        <form method="post" action="/logout">@csrf<button class="btn btn-ghost btn-sm" type="submit">Sign out</button></form>
      </div>
    </div>
  </main>
</div>
@endsection
