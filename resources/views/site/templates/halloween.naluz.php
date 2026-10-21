{{-- Trick or treat: a haunted door under the moon. Knock three times; the door creaks open and the treats (photos) and the letter are inside. --}}
<div class="chrome">@include('site.partials.music')</div>
<div class="hw scroller" id="hw">
  <div class="hw-stage">
    <p class="eyebrow">For {{ $page->recipient }}</p>
    <div class="hw-scene" aria-hidden="true">
      <span class="hw-moon"></span>
      <span class="hw-bat hw-bat1">🦇</span><span class="hw-bat hw-bat2">🦇</span><span class="hw-bat hw-bat3">🦇</span>
      <span class="hw-pumpkin hw-p1" id="hwPumpkin">🎃</span><span class="hw-pumpkin hw-p2">🎃</span>
      <span class="hw-ghost">👻</span>
    </div>
    <button type="button" class="hw-door" id="hwDoor" aria-label="Knock on the door">
      <span class="hw-light" aria-hidden="true"></span>
      <span class="hw-leaf" aria-hidden="true"><span class="hw-knob"></span></span>
    </button>
    <p class="hint" id="hwHint">Knock three times</p>
    <p class="hw-knocks" aria-hidden="true"><i></i><i></i><i></i></p>
  </div>
  <div class="hw-open" id="hwOpen" hidden>
    <div class="hw-treats" id="hwTreats"></div>
    <div class="hw-letter" id="hwLetter"></div>
  </div>
</div>
