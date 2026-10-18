{{-- Storybook: a little book. Every page you turn is a photo and a part of the letter. --}}
<div class="chrome">@include('site.partials.music')</div>
<div class="book-wrap" id="bookWrap">
  <div class="book closed" id="book">
    <div class="page left" id="pageLeft"></div>
    <div class="page right" id="pageRight"></div>
  </div>
  <div class="book-nav">
    <button type="button" class="pill ghost book-prev" id="bookPrev" aria-label="Previous page" disabled>‹</button>
    <span class="book-count" id="bookCount" aria-hidden="true"></span>
    <button type="button" class="pill book-next" id="bookNext">Open the book</button>
  </div>
</div>
