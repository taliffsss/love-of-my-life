{{-- Snow globe: shake it (drag over it, or press the button) until the snow swirls and the tree lights up; the gift is inside. --}}
<div class="chrome">@include('site.partials.music')</div>
<div class="xm scroller" id="xm">
  <div class="xm-stage">
    <div class="xm-scene" aria-hidden="true">
      <span class="xm-moon"></span>
      <span class="xm-twinkle" style="left:10%;top:14%">✦</span><span class="xm-twinkle" style="left:26%;top:8%;animation-delay:.7s">✦</span><span class="xm-twinkle" style="left:44%;top:16%;animation-delay:1.2s">✦</span><span class="xm-twinkle" style="left:64%;top:7%;animation-delay:.3s">✦</span><span class="xm-twinkle" style="left:82%;top:17%;animation-delay:1.6s">✦</span>
      @for ($i = 0; $i < 22; $i++)<i class="xm-flake" style="--x:{{ ($i * 37 + 11) % 100 }}%;--s:{{ 3 + ($i * 5) % 6 }}px;--d:{{ 8 + ($i * 3) % 8 }}s;--w:{{ ($i * 17 % 60) - 30 }}px;animation-delay:-{{ ($i * 7) % 12 }}s"></i>@endfor
      <span class="xm-hills"></span>
      <span class="xm-pines"><b>🌲</b><b>🎄</b><b>🌲</b><b>🌲</b><b>🎄</b><b>🌲</b></span>
      <span class="xm-present xm-pr1">🎁</span><span class="xm-present xm-pr2">🎁</span>
    </div>
    <p class="eyebrow">For {{ $page->recipient }}</p>
    <div class="xm-tilt">
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
