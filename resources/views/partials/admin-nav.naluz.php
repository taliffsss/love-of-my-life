<nav class="admin-tabs" aria-label="Admin">
  <a href="/admin" @if ($section === 'overview') aria-current="page" @endif>Overview</a>
  <a href="/admin/payments" @if ($section === 'payments') aria-current="page" @endif>Payments</a>
  <a href="/admin/users" @if ($section === 'users') aria-current="page" @endif>Users</a>
</nav>
