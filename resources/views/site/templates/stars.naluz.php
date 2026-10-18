{{-- Starry sky: stars in the shape of a heart. Each one holds a photo and a part of the letter; once every star is
     open, the heart lights up and the whole letter is below. --}}
<div class="chrome">@include('site.partials.music')</div>
<div class="sky scroller" id="sky">
  <div class="sky-dust" id="skyDust" aria-hidden="true"></div>
  <div class="sky-stage">
    <header class="sky-head">
      <p class="eyebrow">For {{ $page->recipient }}</p>
      <p class="hint" id="skyHint">Tap a star</p>
    </header>
    <div class="sky-field" id="skyField" role="group" aria-label="Stars in the shape of a heart">
      <svg class="sky-lines" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><polyline id="skyPath" points="" /></svg>
    </div>
    <p class="sky-count" id="skyCount"></p>
  </div>
  <div class="sky-end" id="skyEnd" hidden></div>
</div>
<dialog class="sky-card" id="skyCard" aria-labelledby="skyCardTitle">
  <p class="eyebrow sky-card-title" id="skyCardTitle"></p>
  <div class="sky-card-photo" id="skyCardPhoto"></div>
  <p class="sky-card-text" id="skyCardText"></p>
  <div class="sky-card-actions">
    <button type="button" class="pill ghost" id="skyClose">Back to the sky</button>
    <button type="button" class="pill" id="skyNext" autofocus>Next star</button>
  </div>
</dialog>
