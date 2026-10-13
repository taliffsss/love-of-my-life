<header class="topbar">
  <div class="container">
    @include('partials.brand')
    <nav aria-label="Main" class="hide-sm">
      <a href="/dashboard">Your pages</a>
    </nav>
    <span class="spacer"></span>
    @if ($user !== null)
    <span class="who" title="{{ $user->email }}">{{ $user->email }}</span>
    <form method="post" action="/logout">
      @csrf
      <button class="btn btn-ghost btn-sm" type="submit">Sign out</button>
    </form>
    @endif
  </div>
</header>
