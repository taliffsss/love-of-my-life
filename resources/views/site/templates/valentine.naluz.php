{{-- Loves me, loves me not: pluck the petals one by one. There is an odd number, so the last one always says "loves me", and the letter opens. --}}
<div class="chrome">@include('site.partials.music')</div>
<div class="vl scroller" id="vl">
  <div class="vl-stage">
    <p class="eyebrow">For {{ $page->recipient }}</p>
    <div class="vl-flower" id="vlFlower">
      <button type="button" class="vl-petal" style="--a:0deg" aria-label="Pluck a petal"></button>
      <button type="button" class="vl-petal" style="--a:51.43deg" aria-label="Pluck a petal"></button>
      <button type="button" class="vl-petal" style="--a:102.86deg" aria-label="Pluck a petal"></button>
      <button type="button" class="vl-petal" style="--a:154.29deg" aria-label="Pluck a petal"></button>
      <button type="button" class="vl-petal" style="--a:205.71deg" aria-label="Pluck a petal"></button>
      <button type="button" class="vl-petal" style="--a:257.14deg" aria-label="Pluck a petal"></button>
      <button type="button" class="vl-petal" style="--a:308.57deg" aria-label="Pluck a petal"></button>
      <span class="vl-heart" aria-hidden="true">♥</span>
    </div>
    <p class="vl-say" id="vlSay" aria-live="polite">Pluck a petal</p>
    <p class="hint" id="vlHint">Tap the petals</p>
  </div>
  <div class="vl-open" id="vlOpen" hidden>
    <div class="vl-photos" id="vlPhotos"></div>
    <div class="vl-letter" id="vlLetter"></div>
  </div>
</div>
