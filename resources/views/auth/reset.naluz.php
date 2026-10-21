@extends('layouts.product')
@section('title', 'Set a new password · ' . $brand)
@section('content')
<div class="auth">
  @include('partials.auth-art', ['quote' => 'Some things are easier to write than to say.', 'note' => 'Your pages, your photos and your letters are waiting.'])
  <main class="auth-main">
    <div class="auth-card">
      <div class="row" style="justify-content:space-between">
        <span class="hide-art">@include('partials.brand')</span>
      </div>
      <h1 class="display">Set a new password</h1>
      <p class="muted">Enter the 6-digit code we e-mailed you and choose a new password. The code works for 15 minutes.</p>
      <form method="post" action="/reset-password" class="stack" data-validate novalidate>
        @csrf
        <div class="field">
          <label for="email">E-mail</label>
          <input class="input" id="email" name="email" type="email" autocomplete="email" maxlength="254" required data-msg-required="Enter your e-mail address." data-msg-email="Enter a valid e-mail address, like name@example.com." value="{{ $old['email'] ?? $email }}" @if (!empty($errors['email'])) aria-invalid="true" aria-describedby="email-error" @endif />
          @include('partials.errors', ['field' => 'email'])
        </div>
        <div class="field">
          <label for="code">Code</label>
          <input class="input code-input" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="7" placeholder="000000" required pattern="\s*[0-9]{3}\s?[0-9]{3}\s*" data-msg-required="Enter the 6-digit code from the e-mail." data-msg-pattern="The code has 6 digits." @if ($email !== '') autofocus @endif @if (!empty($errors['code'])) aria-invalid="true" aria-describedby="code-error" @endif />
          @include('partials.errors', ['field' => 'code'])
        </div>
        <div class="field">
          <label for="password">New password</label>
          <input class="input" id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="200" required data-msg-required="Choose a new password." data-msg-minlength="Use at least 8 characters." aria-describedby="password-hint{{ !empty($errors['password']) ? ' password-error' : '' }}" @if (!empty($errors['password'])) aria-invalid="true" @endif />
          <p class="hint" id="password-hint">At least 8 characters.</p>
          @include('partials.errors', ['field' => 'password'])
        </div>
        <div class="field">
          <label for="password_confirmation">New password again</label>
          <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required data-match="#password" data-msg-required="Type the new password again." data-msg-match="The two passwords do not match." />
        </div>
        <button class="btn btn-primary btn-lg" type="submit" style="width:100%">Change password</button>
        <p class="small muted" style="text-align:center">No code? <a href="/forgot-password">Ask for a new one</a> · <a href="/login">Sign in</a></p>
      </form>
    </div>
  </main>
</div>
@endsection
