{{-- Gift box: a wrapped present. Untying it lifts the lid; the photos rise out as a deck to swipe, with the letter. --}}
<div class="chrome">@include('site.partials.music')</div>
<div class="gift scroller" id="gift">
  <div class="gift-stage">
    <p class="eyebrow">For {{ $page->recipient }}</p>
    <button type="button" class="gift-box" id="giftBox" aria-label="Unwrap the present">
      <span class="gift-lid" aria-hidden="true"><span class="gift-bow"><i></i><i></i></span></span>
      <span class="gift-body" aria-hidden="true"></span>
    </button>
    <p class="hint">Tap to unwrap</p>
  </div>
  <div class="gift-open" id="giftOpen" hidden>
    <section class="gift-photos" id="giftPhotos" aria-label="Photos">
      <div class="gift-deck" id="giftDeck"></div>
      <div class="gift-nav">
        <button type="button" class="pill ghost gift-prev" id="giftPrev" aria-label="Previous photo">‹</button>
        <span class="gift-count" id="giftCount" aria-hidden="true"></span>
        <button type="button" class="pill" id="giftNext">Next photo</button>
      </div>
    </section>
    <div class="gift-letter" id="giftLetter"></div>
  </div>
</div>
