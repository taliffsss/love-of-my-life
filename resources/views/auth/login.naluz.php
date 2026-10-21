@extends('layouts.product')
@section('title', 'Sign in · ' . $brand)
@section('content')
<div class="auth">
  @include('partials.auth-art', ['quote' => '“Some things are easier to write than to say.”', 'note' => 'Your pages, your photos and your letters are waiting.'])
  <main class="auth-main">
    <div class="auth-card">
      <span class="hide-art">@include('partials.brand')</span>
      <h1 class="display">Welcome back</h1>
      <p class="muted">Sign in to edit your pages.</p>
      @include('partials.social')
      <form method="post" action="/login" class="stack" data-validate novalidate>
        @csrf
        <div class="field">
          <label for="email">E-mail</label>
          <input class="input" id="email" name="email" type="email" autocomplete="email" maxlength="254" required data-msg-required="Enter your e-mail address." data-msg-email="Enter a valid e-mail address, like name@example.com." value="{{ $old['email'] ?? '' }}" @if (!empty($errors['email'])) aria-invalid="true" aria-describedby="email-error" @endif />
          @include('partials.errors', ['field' => 'email'])
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input class="input" id="password" name="password" type="password" autocomplete="current-password" required data-msg-required="Enter your password." />
          <p class="hint"><a href="/forgot-password">Forgot your password?</a></p>
        </div>
        <button class="btn btn-primary btn-lg" type="submit" style="width:100%">Sign in</button>
        <p class="small muted" style="text-align:center">New here? <a href="/register">Create an account</a></p>
      </form>
    </div>
  </main>
</div>
@endsection
