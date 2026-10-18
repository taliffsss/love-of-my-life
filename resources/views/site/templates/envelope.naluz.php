{{-- Envelope: a sealed envelope. The seal opens it: the letter slides out, with the photos tucked in after it. --}}
<div class="chrome">@include('site.partials.music')</div>
<div class="env scroller" id="env">
  <div class="env-stage">
    <div class="env-scene" id="envScene">
      <div class="env-back" aria-hidden="true"></div>
      <div class="env-paper" aria-hidden="true"></div>
      <div class="env-front" aria-hidden="true"><p class="env-to">For {{ $page->recipient }}</p></div>
      <div class="env-flap" aria-hidden="true"></div>
      <button type="button" class="env-seal" id="envSeal" aria-label="Break the seal and open the envelope">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor" aria-hidden="true"><path d="M12 21s-8-4.900-8-11.100C4 6.700 6.300 5 8.600 5c1.500 0 2.900.8 3.400 2 .5-1.200 1.900-2 3.400-2C17.700 5 20 6.700 20 9.900 20 16.100 12 21 12 21z"/></svg>
      </button>
    </div>
    <p class="hint" id="envHint">Tap the seal to open</p>
  </div>
  <div class="env-open" id="envOpen" hidden></div>
</div>
