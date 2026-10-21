@php
  $samples = \App\Services\OccasionTemplates::all();
  $names = ['anniversary' => 'Jinelin', 'wedding' => 'my husband', 'partner' => 'Mara', 'birthday' => 'Lola', 'invitation' => 'our guests'];
  $says = ['anniversary' => 'Happy anniversary! Got something sweet to say?', 'wedding' => 'A wedding! I will carry every word of your vows.', 'partner' => 'Tell them how you feel. I will deliver it.', 'birthday' => 'Make their day! Cake not included.', 'invitation' => 'Weddings, debuts, baptisms: I will deliver every invitation.'];
  $first = array_key_first(\App\Services\Designs::OCCASIONS);
@endphp
<aside class="auth-art" data-auth-art data-active="{{ $first }}">
  <div class="art-glows" aria-hidden="true">
    @foreach (\App\Services\Designs::OCCASIONS as $key => $occasion)
    <i class="art-glow @if ($key === $first) on @endif" data-for="{{ $key }}"></i>
    @endforeach
  </div>
  <div class="art-hearts" aria-hidden="true">
    @for ($i = 0; $i < 14; $i++)
    <i style="--x:{{ ($i * 37 + 9) % 96 }}%;--s:{{ 10 + ($i * 7) % 18 }}px;--d:{{ 9 + ($i * 3) % 9 }}s;--w:{{ ($i * 13 % 80) / 10 }}s"></i>
    @endfor
  </div>

  @include('partials.brand')

  <div class="art-main">
    <div class="art-tabs" role="tablist" aria-label="See a page for">
      @foreach (\App\Services\Designs::OCCASIONS as $key => $occasion)
      <button type="button" class="art-tab" role="tab" data-tab="{{ $key }}" aria-selected="{{ $key === $first ? 'true' : 'false' }}" tabindex="{{ $key === $first ? '0' : '-1' }}">{{ $occasion['name'] }}</button>
      @endforeach
    </div>

    <div class="art-row">
    <div class="art-card" data-art-card>
      @foreach ($samples as $key => $templates)
      @php $t = $templates[0]; @endphp
      <article class="art-sample @if ($key === $first) on @endif" data-sample="{{ $key }}" data-says="{{ $says[$key] }}" role="tabpanel" @if ($key !== $first) hidden @endif>
        <p class="eyebrow">For {{ $names[$key] }}</p>
        <h2 class="display">{{ $t['name'] }}</h2>
        <p class="art-letter" data-text="{{ $t['letter'][0] }}">{{ $t['letter'][0] }}</p>
        <div class="art-photos" aria-hidden="true">@for ($n = 1; $n <= 3; $n++)<img src="/assets/art/{{ $key }}-{{ $n }}.svg" alt="" width="160" height="120" loading="lazy" />@endfor</div>
        <p class="art-lock small"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg> Opens with: “{{ $t['question'] }}”</p>
      </article>
      @endforeach
    </div>
    <div class="art-lettie" data-lettie>
      <p class="art-bubble" data-bubble>{{ $says[$first] }}</p>
      <button type="button" class="lettie-btn" data-lettie-btn aria-label="Say hi to Lettie, the little love letter"><span class="lettie-stage" data-lettie3d data-theme="{{ $theme ?? 'default' }}">@include('partials.lettie')</span></button>
    </div>
    </div>
    <div class="art-copy">
      <blockquote>{{ $quote }}</blockquote>
      <p class="muted small">{{ $note }}</p>
    </div>
  </div>
<script src="/assets/lettie3d.js?v={{ (string) @filemtime(base_path('public/assets/lettie3d.js')) }}" defer></script>
</aside>
