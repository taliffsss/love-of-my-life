{{-- The brand mark of a way to pay, drawn here (no image files): $id is gcash, paymaya, qrph, card-credit or card-mastercard. --}}
@if ($id === 'gcash')
<span class="pay-logo pay-logo-gcash" aria-hidden="true">G</span>
@elseif ($id === 'paymaya')
<span class="pay-logo pay-logo-paymaya" aria-hidden="true">maya</span>
@elseif ($id === 'qrph')
<span class="pay-logo pay-logo-qrph" aria-hidden="true">
  <svg viewBox="0 0 48 48" width="100%" height="100%" focusable="false">
    <rect x="6" y="6" width="14" height="14" rx="3" fill="none" stroke="#0038a8" stroke-width="4"/><rect x="11" y="11" width="4" height="4" fill="#0038a8"/>
    <rect x="28" y="6" width="14" height="14" rx="3" fill="none" stroke="#ce1126" stroke-width="4"/><rect x="33" y="11" width="4" height="4" fill="#ce1126"/>
    <rect x="6" y="28" width="14" height="14" rx="3" fill="none" stroke="#0038a8" stroke-width="4"/><rect x="11" y="33" width="4" height="4" fill="#0038a8"/>
    <rect x="28" y="28" width="5" height="5" fill="#0038a8"/><rect x="37" y="28" width="5" height="5" fill="#fcd116"/><rect x="28" y="37" width="5" height="5" fill="#fcd116"/><rect x="37" y="37" width="5" height="5" fill="#ce1126"/>
  </svg>
</span>
@elseif ($id === 'card-mastercard')
<span class="pay-logo pay-logo-card-mastercard" aria-hidden="true"><i></i><i></i></span>
@else
<span class="pay-logo pay-logo-card-credit" aria-hidden="true">VISA</span>
@endif
