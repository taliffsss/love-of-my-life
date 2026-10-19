@extends('layouts.product')
@section('title', 'Users · Admin · ' . $brand)
@section('content')
@include('partials.appbar')
@php
  $typeNames = ['manual' => 'E-mail', 'google' => 'Google', 'facebook' => 'Facebook'];
  $keep = array_filter($filters, static fn ($v): bool => $v !== '');
  $link = fn (int $page): string => '/admin/users?' . http_build_query($keep + ['page' => $page]);
@endphp
<main class="container admin">
  <div class="page-head"><div><h1>Users</h1><p class="muted" style="margin-top:6px">{{ number_format($paginator->total) }} {{ $paginator->total === 1 ? 'user' : 'users' }}@if (count($keep) > 0) match{{ $paginator->total === 1 ? 'es' : '' }}@endif</p></div></div>
  @include('partials.admin-nav', ['section' => 'users'])

  <form class="card admin-filter" method="get" action="/admin/users">
    <div class="field"><label for="q">Name or e-mail</label><input class="input" id="q" name="q" type="search" maxlength="100" value="{{ $filters['q'] }}" /></div>
    <div class="field"><label for="status">Status</label>
      <select class="select" id="status" name="status"><option value="">Any</option>
        @foreach (['active' => 'Active', 'deactivated' => 'Deactivated', 'suspended' => 'Suspended'] as $k => $label)<option value="{{ $k }}" @if ($filters['status'] === $k) selected @endif>{{ $label }}</option>@endforeach
      </select></div>
    <div class="field"><label for="type">Signed up with</label>
      <select class="select" id="type" name="type"><option value="">Anyone</option>
        @foreach ($typeNames as $k => $label)<option value="{{ $k }}" @if ($filters['type'] === $k) selected @endif>{{ $label }}</option>@endforeach
      </select></div>
    <label class="check"><input type="checkbox" name="subscribers" value="1" @if ($filters['subscribers'] === '1') checked @endif /> Subscribers only</label>
    <label class="check"><input type="checkbox" name="role" value="admin" @if ($filters['role'] === 'admin') checked @endif /> Administrators</label>
    <button class="btn btn-primary" type="submit">Filter</button>
    @if (count($keep) > 0)<a class="btn btn-ghost" href="/admin/users">Clear</a>@endif
  </form>

  <div class="card table-wrap">
    <table class="table">
      <thead><tr><th>User</th><th>Signed up with</th><th>Status</th><th class="num">Pages</th><th>Live plan</th><th>Joined</th><th class="num"><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>
        @foreach ($paginator as $u)
        @php $i = $info[$u->id] ?? ['pages' => 0, 'plans' => []]; @endphp
        <tr>
          <td>{{ $u->name }}@if ($u->is_admin) <span class="pill pill-plan">Admin</span>@endif<br><span class="small muted">{{ $u->email }}</span></td>
          <td>{{ $typeNames[$u->login_type] ?? ucfirst($u->login_type) }}</td>
          <td><span class="pill pill-{{ $u->status }}" @if ($u->status === 'suspended' && (string) $u->status_reason !== '') title="{{ $u->status_reason }}" @endif>{{ ucfirst($u->status) }}</span></td>
          <td class="num">{{ $i['pages'] }}</td>
          <td>@if (count($i['plans']) > 0){{ implode(', ', $i['plans']) }}@else<span class="muted">None</span>@endif</td>
          <td>{{ \App\Support\Time::show($u->created_at) }}</td>
          <td class="num actions">
            <a class="icon-btn" href="/admin/users/{{ $u->id }}/edit" title="Edit" aria-label="Edit {{ $u->name }}">@include('partials.icon', ['name' => 'edit'])</a>
            @if (!$u->is_admin && $u->id !== $me)
            @if ($u->status !== 'active')
            <a class="icon-btn" href="/admin/users/{{ $u->id }}/edit#status" data-user-action="activate" data-user-id="{{ $u->id }}" data-user-name="{{ $u->name }}" title="Reactivate" aria-label="Reactivate {{ $u->name }}">@include('partials.icon', ['name' => 'play'])</a>
            @endif
            @if ($u->status !== 'deactivated')
            <a class="icon-btn" href="/admin/users/{{ $u->id }}/edit#status" data-user-action="deactivate" data-user-id="{{ $u->id }}" data-user-name="{{ $u->name }}" title="Deactivate" aria-label="Deactivate {{ $u->name }}">@include('partials.icon', ['name' => 'pause'])</a>
            @endif
            @if ($u->status !== 'suspended')
            <a class="icon-btn danger" href="/admin/users/{{ $u->id }}/edit#status" data-user-action="suspend" data-user-id="{{ $u->id }}" data-user-name="{{ $u->name }}" title="Suspend" aria-label="Suspend {{ $u->name }}">@include('partials.icon', ['name' => 'ban'])</a>
            @endif
            <a class="icon-btn danger" href="/admin/users/{{ $u->id }}/edit#delete" data-user-action="delete" data-user-id="{{ $u->id }}" data-user-name="{{ $u->name }}" title="Delete" aria-label="Delete {{ $u->name }}">@include('partials.icon', ['name' => 'trash'])</a>
            @endif
          </td>
        </tr>
        @endforeach
        @if (count($paginator) === 0)<tr><td colspan="7" class="muted">No users match.</td></tr>@endif
      </tbody>
    </table>
  </div>

  @php $here = '/admin/users' . (count($keep) > 0 || $paginator->currentPage > 1 ? '?' . http_build_query($keep + ($paginator->currentPage > 1 ? ['page' => $paginator->currentPage] : [])) : ''); @endphp
  <dialog class="modal" id="user-status-dialog" aria-labelledby="user-status-title">
    <form method="post" action="">
      @csrf
      <input type="hidden" name="status" value="" />
      <input type="hidden" name="return" value="{{ $here }}" />
      <div class="modal-body">
        <h2 id="user-status-title"></h2>
        <p class="muted" id="user-status-text"></p>
        <div class="field" id="user-status-reason" hidden>
          <label for="reason">Reason <span class="muted">(optional, shown to them when they try to sign in)</span></label>
          <input class="input" id="reason" name="reason" maxlength="200" autocomplete="off" />
        </div>
      </div>
      <div class="section-foot">
        <button class="btn btn-ghost" type="button" data-close>Cancel</button>
        <button class="btn btn-primary" type="submit" id="user-status-go">Confirm</button>
      </div>
    </form>
  </dialog>
  <dialog class="modal" id="user-delete-dialog" aria-labelledby="user-delete-title">
    <form method="post" action="">
      @csrf
      @method('DELETE')
      <input type="hidden" name="return" value="{{ $here }}" />
      <div class="modal-body">
        <h2 id="user-delete-title"></h2>
        <p class="muted">Their pages, photos and sign-ins are removed for good and the links stop working. Accounts with paid orders cannot be deleted: deactivate those instead. This cannot be undone.</p>
        <div class="field">
          <label for="confirm-user-delete">Type <strong>delete</strong> to confirm</label>
          <input class="input" id="confirm-user-delete" name="confirm" autocomplete="off" required pattern="delete" />
        </div>
      </div>
      <div class="section-foot">
        <button class="btn btn-ghost" type="button" data-close>Cancel</button>
        <button class="btn btn-danger" type="submit">Delete forever</button>
      </div>
    </form>
  </dialog>

  @if ($paginator->lastPage() > 1)
  <nav class="row" style="justify-content:space-between;margin:18px 0 60px" aria-label="Pages">
    @if ($paginator->currentPage > 1)<a class="btn btn-secondary btn-sm" href="{{ $link($paginator->currentPage - 1) }}">Previous</a>@else<span></span>@endif
    <span class="small muted">Page {{ $paginator->currentPage }} of {{ $paginator->lastPage() }}</span>
    @if ($paginator->hasMorePages())<a class="btn btn-secondary btn-sm" href="{{ $link($paginator->currentPage + 1) }}">Next</a>@else<span></span>@endif
  </nav>
  @endif
</main>
@endsection
