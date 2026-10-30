// Google tag (gtag.js) start-up. The CSP allows no inline scripts, so the snippet's inline part (dataLayer, gtag, config) is this file.
// The measurement id comes from the script tag's data-id (config/saas.php, GA_MEASUREMENT_ID). Google's own script (177 KiB, about 74 of them
// unused on a page view) is fetched once the page has loaded, so it never competes with the first paint; the commands queued here are
// sent as soon as it arrives.
(function () {
  var id = document.currentScript && document.currentScript.dataset.id;
  if (!id) return;
  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  window.gtag = gtag;
  gtag('js', new Date());
  gtag('config', id);
  function load() {
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    document.head.appendChild(s);
  }
  if (document.readyState === 'complete') load();
  else window.addEventListener('load', load, { once: true });
})();
