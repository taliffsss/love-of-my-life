@extends('layouts.product')
@section('title', 'Create your account · ' . $brand)
@section('content')
<div class="auth">
  @include('partials.auth-art', ['quote' => 'Build it tonight. Send it when it feels just right.', 'note' => 'Building and previewing are free. You pay only when you send it.'])
  <main class="auth-main">
    <div class="auth-card">
      <div class="row" style="justify-content:space-between">
        <span class="hide-art">@include('partials.brand')</span>
      </div>
      <h1 class="display">Create your account</h1>
      <p class="muted">Free to build and preview. Takes a minute.</p>
      @if (!empty($chosenPlan))
      <p class="notice notice-brand" role="status">You chose <strong>{{ $chosenPlan }}</strong>. It is saved with your account, and you will see the templates it includes.</p>
      @endif
      @include('partials.social')
      <form method="post" action="/register" class="stack" data-validate novalidate>
        @csrf
        <div class="field">
          <label for="name">Your name</label>
          <input class="input" id="name" name="name" type="text" autocomplete="name" maxlength="80" required data-msg-required="Tell us your name." value="{{ $old['name'] ?? '' }}" @if (!empty($errors['name'])) aria-invalid="true" aria-describedby="name-error" @endif />
          @include('partials.errors', ['field' => 'name'])
        </div>
        <div class="field">
          <label for="email">E-mail</label>
          <input class="input" id="email" name="email" type="email" autocomplete="email" maxlength="254" required data-msg-required="Enter your e-mail address." data-msg-email="Enter a valid e-mail address, like name@example.com." value="{{ $old['email'] ?? '' }}" @if (!empty($errors['email'])) aria-invalid="true" aria-describedby="email-error" @endif />
          @include('partials.errors', ['field' => 'email'])
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input class="input" id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="200" required data-msg-required="Choose a password." data-msg-minlength="Use at least 8 characters." aria-describedby="password-hint{{ !empty($errors['password']) ? ' password-error' : '' }}" @if (!empty($errors['password'])) aria-invalid="true" @endif />
          <p class="hint" id="password-hint">At least 8 characters.</p>
          @include('partials.errors', ['field' => 'password'])
        </div>
        <div class="field">
          <label for="password_confirmation">Password again</label>
          <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required data-match="#password" data-msg-required="Type the password again." data-msg-match="The two passwords do not match." />
        </div>
        <p class="hint">By creating an account you agree to our <a href="/terms">Terms</a> and <a href="/privacy">Privacy Policy</a>, and you are 16 or older (under 18 with a parent or guardian’s permission).</p>
        <button class="btn btn-primary btn-lg" type="submit" style="width:100%">Create account</button>
        <p class="small muted" style="text-align:center">Already have an account? <a href="/login">Sign in</a></p>
      </form>
    </div>
  </main>
</div>
@endsection
