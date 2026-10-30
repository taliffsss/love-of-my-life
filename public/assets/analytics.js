// Google tag (gtag.js) start-up. The CSP allows no inline scripts, so the snippet's inline part (dataLayer, gtag, config) is this file.
// The measurement id comes from the script tag's data-id (config/saas.php, GA_MEASUREMENT_ID). Google's own script (177 KiB, about 74 of
// them unused on a page view) is fetched once the page has loaded AND the visitor has done something (scrolled, tapped, pressed a key or
// moved the pointer), or after 5 seconds, whichever comes first; the commands queued here are sent as soon as it arrives. So it never
// competes with the first paint, and a visitor who leaves within 5 seconds without touching the page is not counted.
(function () {
  var id = document.currentScript && document.currentScript.dataset.id;
  if (!id) return;
  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  window.gtag = gtag;
  gtag('js', new Date());
  gtag('config', id);
  var started = false, events = ['scroll', 'pointerdown', 'pointermove', 'keydown', 'touchstart'];
  function load() {
    if (started) return;
    started = true;
    events.forEach(function (e) { removeEventListener(e, load); });
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    document.head.appendChild(s);
  }
  function arm() {
    events.forEach(function (e) { addEventListener(e, load, { once: true, passive: true }); });
    setTimeout(load, 5000);
  }
  if (document.readyState === 'complete') arm();
  else addEventListener('load', arm, { once: true });
})();
