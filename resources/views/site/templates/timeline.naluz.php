{{-- Timeline: the story as chapters down a line; each photo and part of the letter appears as it scrolls into view. --}}
<div class="chrome">@include('site.partials.music')</div>
<div class="tl scroller" id="tl" tabindex="0" role="region" aria-label="The story of you, from the top">
  <header class="tl-hero">
    <p class="eyebrow">Our story</p>
    <h2>For {{ $page->recipient }}</h2>
    <p class="hint">Scroll down</p>
  </header>
  <div class="tl-body" id="tlBody">
    <div class="tl-line" aria-hidden="true"><i id="tlFill"></i></div>
    <ol class="tl-list" id="tlList"></ol>
  </div>
  <footer class="tl-end" id="tlEnd"></footer>
</div>
<nav class="tl-dots" id="tlDots" aria-label="Chapters"></nav>
