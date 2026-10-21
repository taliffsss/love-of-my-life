{{-- The seasonal backdrop of the landing page: layered emoji and shapes that drift and shift with the pointer, like the Halloween template's scene. Decoration only.
     $theme: halloween | christmas | valentine (nothing for the default look); $mini: a few of them, for the closing section. --}}
@if (in_array($theme, ['halloween', 'christmas', 'valentine'], true))
<div class="lp-scene lp-scene-{{ $theme }}{{ !empty($mini) ? ' lp-scene-mini' : '' }}" aria-hidden="true">
  @if ($theme === 'halloween')
  <span class="sc sc-moon"></span>
  <span class="sc sc-bat sc-b1">🦇</span><span class="sc sc-bat sc-b2">🦇</span>@if (empty($mini))<span class="sc sc-bat sc-b3">🦇</span><span class="sc sc-ghost">👻</span>@endif
  <span class="sc sc-pump sc-p1">🎃</span><span class="sc sc-pump sc-p2">🎃</span>
  @if (empty($mini))<span class="sc sc-cand sc-c1">🕯️</span><span class="sc sc-cand sc-c2">🕸️</span>@endif
  @elseif ($theme === 'christmas')
  <span class="sc sc-moon"></span>
  @if (empty($mini))
  <span class="sc sc-lights"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
  @foreach ([[8, 16, .1], [22, 8, .8], [38, 20, .4], [58, 10, 1.2], [76, 18, .6], [90, 8, 1.5]] as [$x, $y, $d])<span class="sc sc-star" style="left:{{ $x }}%;top:{{ $y }}%;animation-delay:{{ $d }}s">✦</span>@endforeach
  @endif
  @for ($i = 0; $i < (empty($mini) ? 26 : 10); $i++)<i class="sc-snow" style="--x:{{ ($i * 37 + 11) % 100 }}%;--s:{{ 3 + ($i * 5) % 6 }}px;--d:{{ 7 + ($i * 3) % 8 }}s;--w:{{ ($i * 17 % 60) - 30 }}px;animation-delay:-{{ ($i * 7) % 12 }}s"></i>@endfor
  <span class="sc sc-trees"><b>🌲</b><b>🎄</b><b>🌲</b><b>🌲</b><b>🎄</b><b>🌲</b><b>🌲</b></span>
  <span class="sc sc-gift sc-g1">🎁</span>@if (empty($mini))<span class="sc sc-gift sc-g2">🎁</span>@endif
  @else
  <span class="sc sc-bigheart">♥</span>
  @for ($i = 0; $i < (empty($mini) ? 16 : 7); $i++)<span class="sc sc-heart" style="--x:{{ ($i * 43 + 7) % 96 }}%;--s:{{ 16 + ($i * 9) % 30 }}px;--d:{{ 9 + ($i * 2) % 8 }}s;animation-delay:-{{ ($i * 5) % 14 }}s">{{ ['♥', '💗', '♥', '💘'][$i % 4] }}</span>@endfor
  @for ($i = 0; $i < (empty($mini) ? 8 : 3); $i++)<span class="sc sc-petal" style="--x:{{ ($i * 61 + 17) % 94 }}%;--s:{{ 18 + ($i * 7) % 18 }}px;--d:{{ 10 + ($i * 3) % 9 }}s;--w:{{ ($i * 23 % 90) - 45 }}px;animation-delay:-{{ ($i * 4) % 12 }}s">{{ ['🌹', '🌸', '🌹'][$i % 3] }}</span>@endfor
  <span class="sc sc-note sc-n1">💌</span>@if (empty($mini))<span class="sc sc-note sc-n2">💌</span>@endif
  @endif
</div>
@endif
