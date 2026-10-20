// Google tag (gtag.js) start-up. The loader script is in the page's head; this is the snippet's inline part
// (dataLayer, gtag, config) as a file, so the Content-Security-Policy can stay free of inline scripts.
// The measurement id comes from the script tag's data-id (config/saas.php, GA_MEASUREMENT_ID).
(function () {
  var id = document.currentScript && document.currentScript.dataset.id;
  if (!id) return;
  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  window.gtag = gtag;
  gtag('js', new Date());
  gtag('config', id);
})();
