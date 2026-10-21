@extends('layouts.product')
@section('title', 'Forgot your password · ' . $brand)
@section('content')
<div class="auth">
  @include('partials.auth-art', ['quote' => 'Some things are easier to write than to say.', 'note' => 'Your pages, your photos and your letters are waiting.'])
  <main class="auth-main">
    <div class="auth-card">
      <div class="row" style="justify-content:space-between">
        <span class="hide-art">@include('partials.brand')</span>
      </div>
      <h1 class="display">Forgot your password?</h1>
      <p class="muted">Enter the e-mail of your account and we send you a 6-digit code to set a new password.</p>
      <form method="post" action="/forgot-password" class="stack" data-validate novalidate>
        @csrf
        <div class="field">
          <label for="email">E-mail</label>
          <input class="input" id="email" name="email" type="email" autocomplete="email" maxlength="254" required autofocus data-msg-required="Enter your e-mail address." data-msg-email="Enter a valid e-mail address, like name@example.com." value="{{ $old['email'] ?? '' }}" @if (!empty($errors['email'])) aria-invalid="true" aria-describedby="email-error" @endif />
          @include('partials.errors', ['field' => 'email'])
        </div>
        <button class="btn btn-primary btn-lg" type="submit" style="width:100%">Send me a code</button>
        <p class="small muted" style="text-align:center">Remembered it? <a href="/login">Sign in</a></p>
      </form>
    </div>
  </main>
</div>
@endsection
