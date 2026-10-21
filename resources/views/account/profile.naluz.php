@extends('layouts.product')
@section('title', 'Your profile · ' . $brand)
@section('content')
@include('partials.appbar')
<main class="container account" style="max-width:760px">
  <div class="page-head"><div>
    <h1>Your account</h1>
    <p class="muted" style="margin-top:6px">{{ $user->is_admin ? 'Administrator · ' : '' }}Joined {{ \App\Support\Time::show($user->created_at) }} · signed up with {{ $user->login_type === 'manual' ? 'e-mail' : ucfirst($user->login_type) }}</p>
  </div></div>
  @include('partials.account-tabs', ['section' => $section])

  @if ($user->pending_email)
  <p class="notice notice-brand" role="status">We sent a code to <strong>{{ $user->pending_email }}</strong>. Your sign-in e-mail changes to it when you <a href="/email/verify">enter the code</a>; until then it stays <strong>{{ $user->email }}</strong>.</p>
  @endif
  @if ($confirmationOn && !$user->hasVerifiedEmail())
  <p class="notice notice-warn" role="status">Your e-mail is not confirmed yet. <a href="/email/verify">Enter the code we sent</a>.</p>
  @endif

  <section class="card section-card" aria-labelledby="photo-title">
    <div class="section-head"><div><h2 id="photo-title">Profile photo</h2><p>Shown next to your name in the app. A square picture works best.</p></div></div>
    <div class="section-body">
      <form class="dropzone-form" method="post" action="/account/avatar" enctype="multipart/form-data" data-dropzone data-max="{{ $maxAvatarBytes }}" data-max-label="{{ $maxAvatar }}" novalidate>
        @csrf
        <label class="dropzone" for="avatar">
          <input class="dz-input" id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp,image/gif" aria-describedby="avatar-help{{ !empty($errors['avatar']) ? ' avatar-error' : '' }}" />
          <span class="dz-preview">
            @if ($avatarUrl !== null)<img class="avatar avatar-lg" src="{{ $avatarUrl }}" alt="Your profile photo" width="96" height="96" data-current="{{ $avatarUrl }}" />@else<span class="avatar avatar-lg" aria-hidden="true" data-initial="{{ mb_strtoupper(mb_substr((string) $user->name, 0, 1)) }}">{{ mb_strtoupper(mb_substr((string) $user->name, 0, 1)) }}</span>@endif
          </span>
          <span class="dz-copy">
            <strong class="dz-title"><span class="dz-when-idle">Drag a photo here</span><span class="dz-when-over">Drop it to choose it</span><span class="dz-when-picked">Ready to upload</span></strong>
            <span class="dz-sub dz-idle-text">or <u>browse your files</u></span>
            <span class="dz-sub dz-file" hidden></span>
            <span class="dz-hint" id="avatar-help">JPG, PNG, WebP or GIF · up to {{ $maxAvatar }}</span>
          </span>
        </label>
        <p class="error dz-error" id="avatar-error" role="alert" @if (empty($errors['avatar'])) hidden @endif>{{ $errors['avatar'][0] ?? '' }}</p>
        <div class="dz-actions">
          <button class="btn btn-primary" type="submit" data-dz-submit disabled>Upload photo</button>
          <button class="btn btn-ghost" type="button" data-dz-cancel hidden>Cancel</button>
        </div>
      </form>
      @if ($avatarUrl !== null)
      <form method="post" action="/account/avatar" style="margin-top:10px">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" type="submit" style="color:var(--danger)">Remove photo</button></form>
      @endif
    </div>
  </section>

  <section class="card section-card" aria-labelledby="details-title" style="margin-top:18px">
    <form method="post" action="/account/profile" data-validate novalidate>
      @csrf
      @method('PUT')
      <div class="section-head"><div><h2 id="details-title">Your details</h2><p>Your name and address appear on receipts. Nothing here is shown on your pages.</p></div></div>
      <div class="section-body stack" style="--gap:16px">
        <div class="field">
          <label for="name">Full name</label>
          <input class="input" id="name" name="name" maxlength="80" required autocomplete="name" data-msg-required="Enter your name." value="{{ $old['name'] ?? $user->name }}" @if (!empty($errors['name'])) aria-invalid="true" aria-describedby="name-error" @endif />
          @include('partials.errors', ['field' => 'name'])
        </div>
        <div class="field-row">
          <div class="field">
            <label for="email">E-mail</label>
            <input class="input" id="email" name="email" type="email" maxlength="254" required autocomplete="email" data-msg-required="Enter your e-mail address." data-msg-email="Enter a valid e-mail address, like name@example.com." value="{{ $old['email'] ?? $user->email }}" @if (!empty($errors['email'])) aria-invalid="true" aria-describedby="email-error" @endif />
            <p class="hint">{{ $confirmationOn ? 'If you change it, we send a code to the new address to confirm it.' : 'This is what you sign in with.' }}</p>
            @include('partials.errors', ['field' => 'email'])
          </div>
          <div class="field">
            <label for="phone">Phone number</label>
            <input class="input" id="phone" name="phone" type="tel" maxlength="32" autocomplete="tel" placeholder="+63 917 123 4567" pattern="\+?[0-9][0-9 ().\-]{5,30}" data-msg-pattern="Use digits only, with an optional + at the start (for example +63 917 123 4567)." value="{{ $old['phone'] ?? $user->phone }}" @if (!empty($errors['phone'])) aria-invalid="true" aria-describedby="phone-error" @endif />
            @include('partials.errors', ['field' => 'phone'])
          </div>
        </div>
        <div class="field">
          <label for="address_line1">Address</label>
          <input class="input" id="address_line1" name="address_line1" maxlength="120" autocomplete="address-line1" placeholder="House number and street" value="{{ $old['address_line1'] ?? $user->address_line1 }}" @if (!empty($errors['address_line1'])) aria-invalid="true" aria-describedby="address_line1-error" @endif />
          @include('partials.errors', ['field' => 'address_line1'])
          <input class="input" id="address_line2" name="address_line2" maxlength="120" autocomplete="address-line2" placeholder="Apartment, building, barangay (optional)" aria-label="Address line 2" value="{{ $old['address_line2'] ?? $user->address_line2 }}" style="margin-top:8px" />
          @include('partials.errors', ['field' => 'address_line2'])
        </div>
        <div class="field-row">
          <div class="field">
            <label for="city">City</label>
            <input class="input" id="city" name="city" maxlength="80" autocomplete="address-level2" value="{{ $old['city'] ?? $user->city }}" />
            @include('partials.errors', ['field' => 'city'])
          </div>
          <div class="field">
            <label for="region">Province or state</label>
            <input class="input" id="region" name="region" maxlength="80" autocomplete="address-level1" value="{{ $old['region'] ?? $user->region }}" />
            @include('partials.errors', ['field' => 'region'])
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="postal_code">Postal code</label>
            <input class="input" id="postal_code" name="postal_code" maxlength="20" autocomplete="postal-code" pattern="[A-Za-z0-9 \-]{2,20}" data-msg-pattern="Use letters, numbers, spaces and dashes only." value="{{ $old['postal_code'] ?? $user->postal_code }}" @if (!empty($errors['postal_code'])) aria-invalid="true" aria-describedby="postal_code-error" @endif />
            @include('partials.errors', ['field' => 'postal_code'])
          </div>
          <div class="field">
            <label for="country">Country</label>
            <input class="input" id="country" name="country" maxlength="60" autocomplete="country-name" value="{{ $old['country'] ?? $user->country }}" />
            @include('partials.errors', ['field' => 'country'])
          </div>
        </div>
      </div>
      <div class="section-foot"><button class="btn btn-primary" type="submit">Save changes</button></div>
    </form>
  </section>

  <section class="card section-card" aria-labelledby="password-title" style="margin:18px 0 60px">
    <form method="post" action="/account/password" data-validate novalidate>
      @csrf
      @method('PUT')
      <div class="section-head"><div><h2 id="password-title">{{ $user->login_type === 'manual' ? 'Change your password' : 'Set a password' }}</h2>
        <p>{{ $user->login_type === 'manual' ? 'Use at least 8 characters.' : 'You sign in with ' . ucfirst($user->login_type) . '. You can also add a password, to sign in with your e-mail.' }}</p></div></div>
      <div class="section-body stack" style="--gap:16px">
        @if ($user->login_type === 'manual')
        <div class="field">
          <label for="current_password">Current password</label>
          <input class="input" id="current_password" name="current_password" type="password" autocomplete="current-password" required data-msg-required="Enter your current password." @if (!empty($errors['current_password'])) aria-invalid="true" aria-describedby="current_password-error" @endif />
          @include('partials.errors', ['field' => 'current_password'])
        </div>
        @endif
        <div class="field-row">
          <div class="field">
            <label for="new_password">New password</label>
            <input class="input" id="new_password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="200" required data-msg-required="Choose a new password." data-msg-minlength="Use at least 8 characters." @if (!empty($errors['password'])) aria-invalid="true" aria-describedby="password-error" @endif />
            @include('partials.errors', ['field' => 'password'])
          </div>
          <div class="field">
            <label for="password_confirmation">New password again</label>
            <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required data-match="#new_password" data-msg-required="Type the new password again." data-msg-match="The two passwords do not match." />
          </div>
        </div>
      </div>
      <div class="section-foot"><button class="btn btn-primary" type="submit">{{ $user->login_type === 'manual' ? 'Change password' : 'Set password' }}</button></div>
    </form>
  </section>
</main>
@endsection
