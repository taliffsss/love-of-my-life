{{-- Snow globe: shake it (drag over it, or press the button) until the snow swirls and the tree lights up; the gift is inside. --}}
<div class="chrome">@include('site.partials.music')</div>
<div class="xm scroller" id="xm">
  <div class="xm-stage">
    <p class="eyebrow">For {{ $page->recipient }}</p>
    <div class="xm-globe" id="xmGlobe" role="img" aria-label="A snow globe with a Christmas tree">
      <div class="xm-glass">
        <div class="xm-snow" id="xmSnow" aria-hidden="true"></div>
        <div class="xm-tree" aria-hidden="true">
          <i class="xm-star"></i><i class="xm-t1"></i><i class="xm-t2"></i><i class="xm-t3"></i>
          <b class="xm-o xm-o1"></b><b class="xm-o xm-o2"></b><b class="xm-o xm-o3"></b><b class="xm-o xm-o4"></b><b class="xm-o xm-o5"></b>
        </div>
        <div class="xm-ground" aria-hidden="true"></div>
      </div>
      <div class="xm-base" aria-hidden="true"></div>
    </div>
    <div class="xm-meter" aria-hidden="true"><i id="xmFill"></i></div>
    <button type="button" class="pill" id="xmShake">Shake the globe</button>
    <p class="hint" id="xmHint">Shake it, or drag across it</p>
  </div>
  <div class="xm-open" id="xmOpen" hidden>
    <div class="xm-gifts" id="xmGifts"></div>
    <div class="xm-letter" id="xmLetter"></div>
  </div>
</div>
