{{-- The song: what is playing, and the button to pause it. site.js shows both once the page is open. --}}
<div class="nowplaying" id="nowplaying" hidden aria-live="polite">
  <span class="eq" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
  <span class="np-text">
    <span class="np-label">Now playing</span>
    <span class="np-title"><span id="npTitle"></span> <span class="np-by" id="npBy"></span></span>
  </span>
</div>
<button class="mute" id="mute" aria-label="Pause or play the music" hidden>
  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V6l10-2v12"/><circle cx="6.500" cy="18" r="2.500"/><circle cx="16.500" cy="16" r="2.500"/></svg>
</button>
