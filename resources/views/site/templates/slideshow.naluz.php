{{-- Classic, Cinematic and Polaroid: the photos (filled by the slideshow in site.js) and the scrolling letter. --}}
<div class="viewer">
  <div class="frame">
    <div class="stage" id="stage"></div>
    <div class="vignette"></div>
    <div class="glow"></div>
    <button class="nav prev" id="prev" aria-label="Previous photo"></button>
    <button class="nav next" id="next" aria-label="Next photo"></button>
    <div class="hud">
      <span class="count" id="count" aria-hidden="true"></span>
      <div class="bar" aria-hidden="true"><i id="barfill"></i></div>
    </div>
  </div>
  @include('site.partials.music')
</div>
@php $lead = $page->occasion === 'invitation' ? 'An invitation for' : 'A letter for'; @endphp
<aside class="letter" id="letter" aria-label="{{ $lead }} {{ $page->recipient }}">
  <div class="letter-head">
    <p class="eyebrow">{{ $lead }}</p>
    <h2 id="letterFor">{{ $page->recipient }}</h2>
  </div>
  <div class="letter-view" id="letterView">
    <div class="letter-text" id="letterText"></div>
  </div>
</aside>
