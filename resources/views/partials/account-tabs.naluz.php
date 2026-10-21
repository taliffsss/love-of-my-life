<nav class="admin-tabs" aria-label="Account">
  <a href="/account" @if ($section === 'profile') aria-current="page" @endif>Profile</a>
  <a href="/account/billing" @if ($section === 'billing') aria-current="page" @endif>Billing</a>
</nav>
