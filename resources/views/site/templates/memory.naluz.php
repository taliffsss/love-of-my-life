{{-- Memory game: pairs of the photos, face down. The letter opens when every pair is found (or the game is skipped). --}}
<div class="chrome">@include('site.partials.music')</div>
<div class="mem scroller" id="mem">
  <div class="mem-stage">
    <header class="mem-head">
      <p class="eyebrow">For {{ $page->recipient }}</p>
      <h2 class="mem-title">Find the pairs</h2>
      <p class="mem-score" id="memScore"></p>
    </header>
    <div class="mem-grid" id="memGrid" role="group" aria-label="Cards, face down"></div>
    <button type="button" class="pill ghost" id="memSkip">Skip the game</button>
  </div>
  <div class="mem-after" id="memAfter" hidden></div>
</div>
