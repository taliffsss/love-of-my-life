{{-- Scratch card: a card covered in foil. Scratching it (or the Reveal button) shows the photo, then the letter. --}}
<div class="chrome">@include('site.partials.music')</div>
<div class="scr scroller" id="scr">
  <div class="scr-stage">
    <p class="eyebrow">For {{ $page->recipient }}</p>
    <div class="scr-card" id="scrCard">
      <div class="scr-under" id="scrUnder" aria-hidden="true"></div>
      <canvas class="scr-foil" id="scrFoil" aria-hidden="true"></canvas>
    </div>
    <p class="hint" id="scrHint">Scratch the card</p>
    <button type="button" class="pill ghost" id="scrReveal">Reveal it</button>
  </div>
  <div class="scr-after" id="scrAfter" hidden></div>
</div>
