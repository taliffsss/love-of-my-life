@extends('layouts.product')
@section('title', 'Edit ' . $target->name . ' · Admin · ' . $brand)
@section('content')
@include('partials.appbar')
<main class="container admin" style="max-width:760px">
  <div class="page-head"><div>
    <p class="small"><a href="/admin/users">&larr; Users</a></p>
    <h1>{{ $target->name }} <span class="pill pill-{{ $target->status }}">{{ ucfirst($target->status) }}</span></h1>
    <p class="muted" style="margin-top:6px">{{ $target->email }} · joined {{ \App\Support\Time::show($target->created_at) }} · {{ $pageCount }} {{ $pageCount === 1 ? 'page' : 'pages' }} · signed up with {{ $target->login_type === 'manual' ? 'e-mail' : ucfirst($target->login_type) }}</p>
  </div></div>

  <section class="card section-card" aria-labelledby="details-title">
    <form method="post" action="/admin/users/{{ $target->id }}" novalidate>
      @csrf
      @method('PUT')
      <div class="section-head"><div><h2 id="details-title">Details</h2></div></div>
      <div class="section-body stack" style="--gap:16px">
        <div class="field">
          <label for="name">Name</label>
          <input class="input" id="name" name="name" maxlength="80" required value="{{ $old['name'] ?? $target->name }}" @if (!empty($errors['name'])) aria-invalid="true" aria-describedby="name-error" @endif />
          @include('partials.errors', ['field' => 'name'])
        </div>
        <div class="field">
          <label for="email">E-mail</label>
          <input class="input" id="email" name="email" type="email" maxlength="254" required value="{{ $old['email'] ?? $target->email }}" @if (!empty($errors['email'])) aria-invalid="true" aria-describedby="email-error" @endif />
          @include('partials.errors', ['field' => 'email'])
        </div>
        <div class="field">
          <label class="check"><input type="checkbox" name="is_admin" value="1" @if ($target->is_admin) checked @endif @if ($self) disabled @endif /> Administrator</label>
          @if ($self)<input type="hidden" name="is_admin" value="{{ $target->is_admin ? '1' : '' }}" />@endif
          <p class="hint">Administrators see this area. You cannot change your own role.</p>
          @include('partials.errors', ['field' => 'is_admin'])
        </div>
      </div>
      <div class="section-foot"><button class="btn btn-primary" type="submit">Save</button></div>
    </form>
  </section>

  <section class="card section-card" id="status" aria-labelledby="status-title" style="margin-top:18px">
    <div class="section-head"><div><h2 id="status-title">Account status</h2>
      <p>A deactivated or suspended account keeps everything it has but cannot sign in, and is signed out at once. Its pages are not changed.</p></div></div>
    <div class="section-body stack" style="--gap:16px">
      @if ($target->status === 'suspended' && (string) $target->status_reason !== '')<div class="notice notice-warn">Suspended: {{ $target->status_reason }}</div>@endif
      @if ($self)
      <p class="muted">This is your own account: you cannot change its status.</p>
      @elseif ($target->is_admin)
      <p class="muted">Remove the administrator role above before deactivating or suspending this account.</p>
      @else
      @if ($target->status !== 'active')
      <form method="post" action="/admin/users/{{ $target->id }}/status">@csrf<input type="hidden" name="status" value="active" /><button class="btn btn-primary" type="submit">Reactivate</button></form>
      @endif
      @if ($target->status !== 'deactivated')
      <form method="post" action="/admin/users/{{ $target->id }}/status">@csrf<input type="hidden" name="status" value="deactivated" /><button class="btn btn-secondary" type="submit">Deactivate</button></form>
      @endif
      @if ($target->status !== 'suspended')
      <form method="post" action="/admin/users/{{ $target->id }}/status" class="stack" style="--gap:10px">@csrf<input type="hidden" name="status" value="suspended" />
        <div class="field"><label for="reason">Reason <span class="muted">(optional, shown to them when they try to sign in)</span></label><input class="input" id="reason" name="reason" maxlength="200" autocomplete="off" /></div>
        <div><button class="btn btn-secondary" type="submit" style="color:var(--danger)">Suspend</button></div>
      </form>
      @endif
      @endif
    </div>
  </section>

  <section class="card section-card danger-zone" id="delete" aria-labelledby="delete-title" style="margin-top:18px">
    <form method="post" action="/admin/users/{{ $target->id }}">
      @csrf
      @method('DELETE')
      <div class="section-head"><div><h2 id="delete-title">Delete account</h2>
        <p>Removes the account, its {{ $pageCount }} {{ $pageCount === 1 ? 'page' : 'pages' }}, photos and sign-ins for good. This cannot be undone.@if ($paidOrders > 0) It has {{ $paidOrders }} paid {{ $paidOrders === 1 ? 'order' : 'orders' }}, which must stay on record, so it cannot be deleted: deactivate it instead.@endif</p></div></div>
      @if ($self || $target->is_admin)
      <div class="section-body"><p class="muted">{{ $self ? 'You cannot delete your own account here.' : 'Remove the administrator role before deleting this account.' }}</p></div>
      @elseif ($paidOrders === 0)
      <div class="section-body">
        <div class="field"><label for="confirm">Type <strong>delete</strong> to confirm</label><input class="input" id="confirm" name="confirm" autocomplete="off" required pattern="delete" /></div>
      </div>
      <div class="section-foot"><button class="btn btn-danger" type="submit">Delete forever</button></div>
      @endif
    </form>
  </section>
</main>
@endsection
