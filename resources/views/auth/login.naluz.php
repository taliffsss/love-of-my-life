@extends('layouts.product')
@section('title', 'Sign in · ' . $brand)
@section('content')
<div class="auth">
  <aside class="auth-art">
    @include('partials.brand')
    <blockquote>“Some things are easier to write than to say.”</blockquote>
    <p class="muted small">Your pages, your photos and your letters are waiting.</p>
  </aside>
  <main class="auth-main">
    <div class="auth-card">
      <span class="hide-art">@include('partials.brand')</span>
      <h1 class="display">Welcome back</h1>
      <p class="muted">Sign in to edit your pages.</p>
      @include('partials.social')
      <form method="post" action="/login" class="stack" novalidate>
        @csrf
        <div class="field">
          <label for="email">E-mail</label>
          <input class="input" id="email" name="email" type="email" autocomplete="email" required value="{{ $old['email'] ?? '' }}" @if (!empty($errors['email'])) aria-invalid="true" aria-describedby="email-error" @endif />
          @include('partials.errors', ['field' => 'email'])
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input class="input" id="password" name="password" type="password" autocomplete="current-password" required />
        </div>
        <button class="btn btn-primary btn-lg" type="submit" style="width:100%">Sign in</button>
        <p class="small muted" style="text-align:center">New here? <a href="/register">Create an account</a></p>
      </form>
    </div>
  </main>
</div>
@endsection
