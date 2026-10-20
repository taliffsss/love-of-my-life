@extends('layouts.product')
@section('title', 'For ' . $page->recipient . ' · ' . $brand)
@section('content')
@include('partials.appbar')
@php
  $status = $page->status();
  $plan = $page->planDetails();
  $photoCount = count($photos);
  $canAdd = $usage['remaining'] > 0;
  $letterText = isset($old['message']) ? (string) $old['message'] : implode("\n\n", $page->paragraphs());
@endphp
<main class="container" data-page="{{ $page->slug }}" data-max-bytes="{{ $maxUploadBytes }}">
  <div class="page-head">
    <div style="min-width:0">
      <a class="small" href="/dashboard">&larr; Your pages</a>
      <h1 style="margin-top:6px">For {{ $page->recipient }}</h1>
      <div class="meta">
        <span class="pill pill-{{ $status }}">{{ $status === 'draft' ? 'Draft: only you can see it' : ($status === 'active' ? 'Live' : 'Expired') }}</span>
        <span class="pill pill-plan">{{ $plan['name'] }}</span>
        @if ($status === 'active' && $page->expires_at !== null)
        <span class="small muted">Online until {{ \App\Support\Time::show($page->expires_at) }}</span>
        @elseif ($status === 'active')
        <span class="small muted">Online forever</span>
        @endif
      </div>
    </div>
    <span class="spacer"></span>
    <div class="row">
      <a class="btn btn-secondary" href="/dashboard/pages/{{ $page->slug }}/preview" target="_blank" rel="noopener">Preview</a>
      @if ($status === 'active')
      <a class="btn btn-primary" href="{{ $url }}" target="_blank" rel="noopener">Open live page</a>
      @else
      <a class="btn btn-primary" href="#plan">Publish</a>
      @endif
    </div>
  </div>

  <nav class="section-nav" aria-label="Page sections">
    <a href="#message">Letter</a>
    <a href="#photos">Photos</a>
    <a href="#song">Song</a>
    <a href="#lock">Lock screen</a>
    <a href="#design">Design</a>
    <a href="#share">Share</a>
    <a href="#plan">Plan</a>
  </nav>

  <div class="editor">
    <div class="editor-main">

      {{-- Letter ------------------------------------------------------------------------------------------------ --}}
      <section class="card section-card" id="message" aria-labelledby="message-title">
        <form method="post" action="/dashboard/pages/{{ $page->slug }}/message" novalidate>
          @csrf
          @method('PUT')
          <div class="section-head">
            <div><h2 id="message-title">The letter</h2><p>It scrolls slowly beside the photos. Leave an empty line between paragraphs.</p></div>
          </div>
          <div class="section-body stack">
            <div class="field">
              <label for="recipient">Who it is for <span class="muted small">(shown as “A letter for …”)</span></label>
              <input class="input" id="recipient" name="recipient" maxlength="60" required value="{{ $old['recipient'] ?? $page->recipient }}" @if (!empty($errors['recipient'])) aria-invalid="true" aria-describedby="recipient-error" @endif />
              @include('partials.errors', ['field' => 'recipient'])
            </div>
            <div class="field">
              <label for="letter-message">Your letter</label>
              <textarea class="textarea" id="letter-message" name="message" required maxlength="8000" @if (!empty($errors['message'])) aria-invalid="true" aria-describedby="message-error" @endif>{{ $letterText }}</textarea>
              @include('partials.errors', ['field' => 'message'])
            </div>
          </div>
          <div class="section-foot"><button class="btn btn-primary" type="submit">Save letter</button></div>
        </form>
      </section>

      {{-- Photos ------------------------------------------------------------------------------------------------ --}}
      <section class="card section-card" id="photos" aria-labelledby="photos-title">
        <div class="section-head">
          <div><h2 id="photos-title">Photos</h2><p>They play in this order, one every 8 seconds. Up to {{ \App\Services\PhotoStorage::megabytes($maxUploadBytes) }} each; they are resized on your phone before they upload and fit every template without stretching.</p></div>
          <span class="small muted" id="photo-count">{{ $photoCount }} on this page</span>
        </div>
        <div class="section-body stack">
          @include('partials.photo-usage', ['usage' => $usage, 'packs' => $packs, 'pageSlug' => $page->slug, 'upgradeUrl' => $canUpgrade ? '#plan' : null])
          <div class="thumbs" id="thumbs">
            @foreach ($photos as $i => $photo)
            <figure class="thumb" data-id="{{ $photo->id }}" style="margin:0">
              <img src="{{ $photo->url }}" alt="Photo {{ $i + 1 }}" loading="lazy" />
              <span class="n">{{ $i + 1 }}</span>
              <div class="tools">
                <span class="row" style="gap:4px">
                  <button type="button" class="icon-btn" data-move="-1" aria-label="Move photo {{ $i + 1 }} earlier"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></button>
                  <button type="button" class="icon-btn" data-move="1" aria-label="Move photo {{ $i + 1 }} later"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg></button>
                </span>
                <button type="button" class="icon-btn danger" data-delete aria-label="Delete photo {{ $i + 1 }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg></button>
              </div>
            </figure>
            @endforeach
            <label class="upload-zone" id="upload-zone" @if (!$canAdd) hidden @endif>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V5M7 9l5-5 5 5"/><path d="M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/></svg>
              <span>Add photos</span>
              <input id="photo-input" type="file" accept="image/*" multiple class="sr-only" />
            </label>
          </div>
          <p class="small" id="photo-status" role="status" aria-live="polite"></p>
        </div>
      </section>

      {{-- Song -------------------------------------------------------------------------------------------------- --}}
      <section class="card section-card" id="song" aria-labelledby="song-title">
        <form method="post" action="/dashboard/pages/{{ $page->slug }}/song" novalidate>
          @csrf
          @method('PUT')
          <div class="section-head">
            <div><h2 id="song-title">Your song</h2><p>It starts softly the moment the page opens, played through YouTube.</p></div>
          </div>
          <div class="section-body stack">
            @if ($page->song_id !== null)
            <div class="notice">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V6l10-2v12"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="16" r="2.5"/></svg>
              <div><strong>{{ $page->song_title }}</strong>@if ((string) $page->song_artist !== '') <span class="muted">· {{ $page->song_artist }}</span>@endif<br /><a class="small" href="https://www.youtube.com/watch?v={{ $page->song_id }}" target="_blank" rel="noopener">Listen on YouTube</a></div>
            </div>
            @else
            <p class="muted small">No song yet. The page opens in silence until you add one.</p>
            @endif
            <div class="field">
              <label for="song-url">{{ $page->song_id !== null ? 'Change it to another YouTube link' : 'Paste a YouTube link' }}</label>
              <input class="input" id="song-url" name="url" type="url" inputmode="url" placeholder="https://www.youtube.com/watch?v=…" value="{{ $old['url'] ?? '' }}" @if (!empty($errors['url'])) aria-invalid="true" aria-describedby="url-error" @endif />
              @include('partials.errors', ['field' => 'url'])
            </div>
          </div>
          <div class="section-foot">
            @if ($page->song_id !== null)
            <button class="btn btn-ghost" type="submit" name="remove" value="1" formnovalidate>Remove song</button>
            @endif
            <button class="btn btn-primary" type="submit">Save song</button>
          </div>
        </form>
      </section>

      {{-- Lock screen ------------------------------------------------------------------------------------------- --}}
      <section class="card section-card" id="lock" aria-labelledby="lock-title">
        <form method="post" action="/dashboard/pages/{{ $page->slug }}/lock" novalidate>
          @csrf
          @method('PUT')
          <div class="section-head">
            <div><h2 id="lock-title">Lock screen</h2><p>The first thing they see. With a question on, nothing on the page is sent until the right answer is typed.</p></div>
          </div>
          <div class="section-body stack">
            <label class="check">
              <input type="checkbox" name="gate_enabled" value="1" @if ($old['gate_enabled'] ?? $page->gate_enabled) checked @endif />
              <span><strong>Ask a question before the page opens</strong><br /><span class="small muted">Off: the page opens with a single tap on “Open”.</span></span>
            </label>
            <div class="field">
              <label for="gate_eyebrow">Small heading</label>
              <input class="input" id="gate_eyebrow" name="gate_eyebrow" maxlength="60" value="{{ $old['gate_eyebrow'] ?? $page->gate_eyebrow }}" @if (!empty($errors['gate_eyebrow'])) aria-invalid="true" aria-describedby="gate_eyebrow-error" @endif />
              @include('partials.errors', ['field' => 'gate_eyebrow'])
            </div>
            <div class="field">
              <label for="gate_question">Question</label>
              <input class="input" id="gate_question" name="gate_question" maxlength="120" required value="{{ $old['gate_question'] ?? $page->gate_question }}" @if (!empty($errors['gate_question'])) aria-invalid="true" aria-describedby="gate_question-error" @endif />
              @include('partials.errors', ['field' => 'gate_question'])
            </div>
            <div class="field">
              <label for="gate_password">Answer</label>
              <input class="input" id="gate_password" name="gate_password" type="password" maxlength="100" autocomplete="new-password" placeholder="{{ $page->hasGate() ? 'Leave empty to keep the current answer' : 'The word that opens the page' }}" aria-describedby="gate_password-hint{{ !empty($errors['gate_password']) ? ' gate_password-error' : '' }}" @if (!empty($errors['gate_password'])) aria-invalid="true" @endif />
              <p class="hint" id="gate_password-hint">Capital letters and extra spaces don’t matter: “Yam”, “yam” and “ yam ” all open it. The answer is stored scrambled; we can’t read it back.</p>
              @include('partials.errors', ['field' => 'gate_password'])
            </div>
          </div>
          <div class="section-foot"><button class="btn btn-primary" type="submit">Save lock screen</button></div>
        </form>
      </section>

      {{-- Design ------------------------------------------------------------------------------------------------ --}}
      <section class="card section-card" id="design" aria-labelledby="design-title">
        <form method="post" action="/dashboard/pages/{{ $page->slug }}/design" id="design-form" novalidate>
          @csrf
          @method('PUT')
          <div class="section-head">
            <div><h2 id="design-title">Design</h2><p>Pick an occasion, then one of its {{ count($layouts) }} designs and a colour theme; every design works with every theme. Change them any time: the preview updates before you save.</p></div>
          </div>
          <div class="section-body stack" style="--gap:18px">
            <fieldset style="border:0;margin:0;padding:0">
              <legend class="label" style="margin-bottom:10px">Occasion</legend>
              @include('partials.errors', ['field' => 'occasion'])
              <div class="occasion-tabs" role="group" aria-label="Occasion">
                <label class="occasion-tab"><input type="radio" name="occasion" value="" @if (empty($page->occasion)) checked @endif /><span>Plain</span></label>
                @foreach ($occasions as $okey => $occasion)
                <label class="occasion-tab"><input type="radio" name="occasion" value="{{ $okey }}" @if ($page->occasion === $okey) checked @endif /><span>{{ $occasion['name'] }}</span></label>
                @endforeach
              </div>
            </fieldset>
            <fieldset style="border:0;margin:0;padding:0">
              <legend class="label" style="margin-bottom:10px">Template</legend>
              @include('partials.errors', ['field' => 'layout'])
              @php $groups = ['' => null] + $occasions; @endphp
              @foreach ($groups as $okey => $occasion)
              @php $okey = (string) $okey; $current = (string) $page->occasion === $okey; @endphp
              <div class="choice-grid design-grid" data-occasion="{{ $okey }}">
                @foreach ($layouts as $key => $layout)
                @php $label = $okey === '' ? $layout['name'] : $occasionDesigns[$okey][$key][0]; $about = $okey === '' ? $layout['description'] : $occasionDesigns[$okey][$key][1]; @endphp
                <label class="choice">
                  <input type="radio" name="layout" value="{{ $key }}" @if ($current && $page->layout === $key) checked @endif @if ($templateNotes[$key]['locked']) data-locked="{{ $label }} comes with {{ $templateNotes[$key]['plans'] }}. Preview it here, then upgrade the plan to use it." @endif />
                  <span class="box">
                    <span class="shot"><span class="schem schem-{{ $key }}" aria-hidden="true" style="display:block"><span class="ph"></span><span class="lt"><i></i><i></i><i></i><i></i></span></span></span>
                    @if ($templateNotes[$key]['plans'] !== '')<span class="plan-badge">{{ $templateNotes[$key]['plans'] }}</span>@endif
                    <span class="txt"><b>{{ $label }}</b><small>{{ $about }}</small><small class="play">{{ $layout['play'] }}</small></span>
                  </span>
                </label>
                @endforeach
              </div>
              @endforeach
            </fieldset>
            <fieldset style="border:0;margin:0;padding:0">
              <legend class="label" style="margin-bottom:10px">Colours</legend>
              <div class="choice-grid">
                @foreach ($themes as $key => $theme)
                <label class="choice theme-choice">
                  <input type="radio" name="theme" value="{{ $key }}" @if ($page->theme === $key) checked @endif />
                  <span class="box"><span class="dots" aria-hidden="true">@foreach ($theme['colors'] as $c)<i style="background:{{ $c }}"></i>@endforeach</span><b>{{ $theme['name'] }}</b></span>
                </label>
                @endforeach
              </div>
            </fieldset>
          </div>
          <p class="notice notice-brand" id="design-locked" role="status" hidden></p>
          <div class="section-foot">
            <a class="btn btn-ghost" id="design-preview-link" href="/dashboard/pages/{{ $page->slug }}/preview" target="_blank" rel="noopener">Preview full screen</a>
            <button class="btn btn-primary" type="submit" id="design-save">Save design</button>
          </div>
        </form>
      </section>

      {{-- Share ------------------------------------------------------------------------------------------------- --}}
      <section class="card section-card" id="share" aria-labelledby="share-title">
        <div class="section-head">
          <div><h2 id="share-title">Share</h2><p>Send the link in a message, or print the QR code on a card.</p></div>
        </div>
        <div class="section-body stack" style="--gap:20px">
          @if ($status !== 'active')
          <div class="notice notice-warn">The link works once the page is published. You can prepare the QR code now: it opens the permanent link, which never changes.</div>
          @endif
          <div class="share-grid">
            <div class="stack">
              <div class="field">
                <label for="share-url">Page link</label>
                <div class="copy-row">
                  <input class="input" id="share-url" value="{{ $url }}" readonly />
                  <button class="btn btn-secondary" type="button" data-copy="#share-url">Copy</button>
                </div>
              </div>
              @if ($url !== $pathUrl)
              <p class="small muted">Permanent link (never changes): <a href="{{ $pathUrl }}" target="_blank" rel="noopener">{{ preg_replace('#^https?://#', '', $pathUrl) }}</a></p>
              @endif

              @if (config('saas.features.subdomains'))
              <div class="stack" style="--gap:10px">
                <h3 class="label" style="font-size:1rem">Dedicated address</h3>
                @if ($page->allowsSubdomain())
                <form method="post" action="/dashboard/pages/{{ $page->slug }}/subdomain" class="stack" style="--gap:8px" novalidate id="subdomain-form">
                  @csrf
                  @method('PUT')
                  <label for="subdomain" class="sr-only">Address name</label>
                  <div class="input-group">
                    <input class="input" id="subdomain" name="subdomain" maxlength="40" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="yourname" value="{{ $old['subdomain'] ?? ($page->subdomain ?? '') }}" aria-describedby="subdomain-help{{ !empty($errors['subdomain']) ? ' subdomain-error' : '' }}" @if (!empty($errors['subdomain'])) aria-invalid="true" @endif />
                    <span class="addon">.{{ $domain }}</span>
                  </div>
                  <p class="hint" id="subdomain-help" aria-live="polite">3 to 30 lowercase letters, numbers or dashes. It works as soon as you save it.</p>
                  @include('partials.errors', ['field' => 'subdomain'])
                  <div class="row">
                    <button class="btn btn-primary btn-sm" type="submit">{{ $page->subdomain !== null ? 'Change address' : 'Claim address' }}</button>
                  </div>
                </form>
                @if ($page->subdomain !== null)
                <form method="post" action="/dashboard/pages/{{ $page->slug }}/subdomain">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-ghost btn-sm" type="submit">Release {{ $page->subdomain }}.{{ $domain }}</button>
                  <p class="hint">Links already sent with this address stop working, and someone else could claim it. The QR code keeps working.</p>
                </form>
                @endif
                @else
                <div class="notice notice-brand">
                  <div>Give this page an address of its own, like <strong>{{ strtolower(preg_replace('/[^a-z0-9]+/i', '', $page->shortName()) ?: 'yourname') }}.{{ $domain }}</strong>. It comes with the Sweetheart and Forever plans. <a href="#plan">See plans</a></div>
                </div>
                @endif
              </div>
              @endif
            </div>
            <div class="qr-box">
              <img src="/dashboard/pages/{{ $page->slug }}/qr" alt="QR code that opens {{ $pathUrl }}" width="150" height="150" />
              <p class="small muted" style="text-align:center">Opens the permanent link, even if the address changes.</p>
              <a class="btn btn-secondary btn-sm" href="/dashboard/pages/{{ $page->slug }}/qr?download=1">Download QR (SVG)</a>
            </div>
          </div>
        </div>
      </section>

      {{-- Plan -------------------------------------------------------------------------------------------------- --}}
      <section class="card section-card" id="plan" aria-labelledby="plan-title">
        <div class="section-head">
          <div>
            <h2 id="plan-title">Plan</h2>
            <p>
              @if ($status === 'draft')
                This page is a free draft. Choose a plan to put it online.
              @elseif ($status === 'expired')
                This page expired on {{ \App\Support\Time::show($page->expires_at) }}. Renew it to bring it back; nothing was deleted.
              @elseif ($page->expires_at !== null)
                {{ $plan['name'] }}: online until {{ \App\Support\Time::show($page->expires_at) }}.
              @else
                {{ $plan['name'] }}: online forever. Nothing more to buy.
              @endif
            </p>
          </div>
        </div>
        <div class="section-body stack" style="--gap:18px">
          @include('partials.errors', ['field' => 'plan'])
          @if ($subscription !== null)
          <div class="notice notice-brand sub-box">
            <div class="stack" style="--gap:6px">
              <strong>Subscribed: {{ $plans[$subscription->plan]['name'] ?? $subscription->plan }}, renews {{ ['month' => 'every month', 'year' => 'every year'][$subscription->period] ?? 'each period' }}</strong>
              <span class="small">Paid by {{ \App\Models\Order::METHODS[$subscription->method] ?? $subscription->method }}. @if ($subscription->mode === 'auto')It renews automatically: the payment provider charges you each time.@else A reminder appears here and on your dashboard {{ \App\Services\Subscriptions::LEAD_DAYS }} days before it is due, and you approve each payment.@endif</span>
              @if ($renewal !== null)
              <a class="btn btn-primary btn-sm" href="/dashboard/orders/{{ $renewal->reference }}" style="width:fit-content">Renewal due: pay {{ \App\Services\Plans::money($renewal->amount, $renewal->currency) }}</a>
              @endif
              <form method="post" action="/dashboard/pages/{{ $page->slug }}/subscription/cancel" data-confirm="Stop this subscription? Your page stays online until it expires.">
                @csrf
                <button class="btn btn-ghost btn-sm" type="submit" style="color:var(--danger)">Cancel subscription</button>
              </form>
            </div>
          </div>
          @endif
          @php
            // the plan the page is on (marked Current once its payment went through) and the plans it can buy
            $cards = [];
            foreach ($plans as $key => $candidate) {
              if (!$candidate['publish']) {
                continue;
              }
              $isCurrent = $status !== 'draft' && $page->plan === $key;
              if ($isCurrent || isset($purchasable[$key])) {
                $cards[$key] = ['plan' => $purchasable[$key] ?? ($candidate + ['offers' => []]), 'current' => $isCurrent];
              }
            }
          @endphp
          @if (count($cards) > 0)
          <div class="plan-row">
            @foreach ($cards as $key => $card)
            @php $option = $card['plan']; $current = $card['current']; @endphp
            <div class="plan-option {{ $option['popular'] ? 'featured' : '' }} {{ $current ? 'current' : '' }}">
              @if ($current)<span class="tag tag-current">{{ $status === 'expired' ? 'Expired' : 'Current' }}</span>@endif
              @if ($option['popular'])<span class="tag">Most popular</span>@endif
              <strong>{{ $current ? (count($option['offers']) > 0 ? 'Renew ' . $option['name'] : $option['name']) : ($status === 'draft' ? $option['name'] : 'Upgrade to ' . $option['name']) }}</strong>
              <span class="small muted">{{ $option['blurb'] }}</span>
              <span class="small">{{ $option['photos'] }} photos · {{ $option['templates'] === 'all' ? 'all ' . count($layouts) . ' templates' : count($option['templates']) . ' templates' }}{{ $option['subdomain'] ? ' · its own address' : '' }}</span>
              @if ($current)
              <span class="small current-note">{{ $status === 'expired' ? 'Expired on ' . \App\Support\Time::show($page->expires_at) : ($page->expires_at !== null ? 'Online until ' . \App\Support\Time::show($page->expires_at) : 'Online forever') }}</span>
              @endif
              @foreach ($option['offers'] as $period => $price)
              <form method="post" action="/dashboard/pages/{{ $page->slug }}/orders">
                @csrf
                <input type="hidden" name="plan" value="{{ $key }}" />
                <input type="hidden" name="period" value="{{ $period }}" />
                <button class="btn {{ $option['popular'] ? 'btn-primary' : 'btn-secondary' }} offer" type="submit">
                  <span>{{ \App\Services\Plans::money($price) }} {{ \App\Services\Plans::per($period) }}</span>
                  @if ($period === 'once')<small>never expires</small>@endif
                </button>
              </form>
              @endforeach
            </div>
            @endforeach
          </div>
          @endif
          @if (count($orders) > 0)
          <div class="table-wrap">
            <table class="orders">
              <caption class="sr-only">Recent orders</caption>
              <thead><tr><th scope="col">Order</th><th scope="col">Plan</th><th scope="col">Amount</th><th scope="col">Status</th></tr></thead>
              <tbody>
                @foreach ($orders as $order)
                <tr>
                  <td><a href="/dashboard/orders/{{ $order->reference }}">{{ $order->reference }}</a></td>
                  <td>@if ($order->isPhotoPack()){{ $order->photos }} photos @else {{ $plans[$order->plan]['name'] ?? $order->plan }} <span class="muted">{{ \App\Services\Plans::per((string) $order->period) }}</span>@endif</td>
                  <td>{{ \App\Services\Plans::money($order->amount, $order->currency) }}</td>
                  <td>{{ ucfirst($order->status) }}</td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          @endif
        </div>
      </section>

      {{-- Delete ------------------------------------------------------------------------------------------------ --}}
      <section class="card section-card danger-zone" aria-labelledby="delete-title">
        <div class="section-head">
          <div><h2 id="delete-title">Delete this page</h2><p>The page, its photos and its address are removed for good. Its photos stop counting towards your account.</p></div>
        </div>
        <div class="section-body">
          <button class="btn btn-secondary" type="button" data-open="#delete-dialog" style="color:var(--danger)">Delete page…</button>
        </div>
      </section>
    </div>

    <aside class="preview-pane" aria-label="Live preview">
      <div class="phone">
        <iframe id="preview-frame" title="Preview of the page for {{ $page->recipient }}" src="/dashboard/pages/{{ $page->slug }}/preview" loading="lazy"></iframe>
      </div>
      <div class="row">
        <a class="btn btn-ghost btn-sm" href="/dashboard/pages/{{ $page->slug }}/preview" target="_blank" rel="noopener">Open preview in a new tab</a>
      </div>
    </aside>
  </div>
</main>

<dialog class="modal" id="delete-dialog" aria-labelledby="delete-dialog-title">
  <form method="post" action="/dashboard/pages/{{ $page->slug }}">
    @csrf
    @method('DELETE')
    <div class="modal-body">
      <h2 id="delete-dialog-title">Delete the page for {{ $page->recipient }}?</h2>
      <p class="muted">Its letter{{ $photoCount > 0 ? ', address and ' . $photoCount . ' photo' . ($photoCount === 1 ? '' : 's') : ' and address' }} are removed and the link stops working.@if ($photoCount > 0) The photos are deleted from storage and stop counting towards your account.@endif This cannot be undone.</p>
      <div class="field">
        <label for="confirm-delete">Type <strong>delete</strong> to confirm</label>
        <input class="input" id="confirm-delete" name="confirm" autocomplete="off" required pattern="delete" />
      </div>
    </div>
    <div class="section-foot">
      <button class="btn btn-ghost" type="button" data-close>Cancel</button>
      <button class="btn btn-danger" type="submit">Delete forever</button>
    </div>
  </form>
</dialog>
@endsection
