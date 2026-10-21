<header class="topbar">
  <div class="container">
    @include('partials.brand')
    <nav aria-label="Main" class="hide-sm">
      <a href="/dashboard">Your pages</a>
      <a href="/account/billing">Billing</a>
      @if ($user !== null && $user->is_admin)
      <a href="/admin">Admin</a>
      @endif
    </nav>
    <span class="spacer"></span>
    @if ($user !== null)
    @php $avatar = app(\App\Services\PhotoStorage::class)->url($user->avatar_disk, $user->avatar_path); @endphp
    <a class="me" href="/account" title="Your profile" aria-label="Your profile">
      @if ($avatar !== null)<img class="avatar avatar-sm" src="{{ $avatar }}" alt="" width="30" height="30" />@else<span class="avatar avatar-sm" aria-hidden="true">{{ mb_strtoupper(mb_substr((string) $user->name, 0, 1)) }}</span>@endif
      <span class="who">{{ $user->email }}</span>
    </a>
    <form method="post" action="/logout">
      @csrf
      <button class="btn btn-ghost btn-sm" type="submit">Sign out</button>
    </form>
    @endif
  </div>
</header>
