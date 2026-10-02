<?php
/**
 * Makes the website installable on a phone: same pages, same login, opened
 * from the home screen like an app. Included at the end of every page.
 *
 * A button with the attribute data-install-app is shown only when the browser
 * offers installation (Chrome on Android, Edge); iPhone users use Safari's
 * Share > Add to Home Screen, which needs no button.
 */
$pwaBase = rtrim((string) config('app.base_url', ''), '/');
?>
<script>
(function () {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(<?= json_encode($pwaBase . '/sw.js') ?>, { scope: <?= json_encode($pwaBase . '/') ?> }).catch(function () {});
  }
  var offer = null;
  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    offer = event;
    document.querySelectorAll('[data-install-app]').forEach(function (button) { button.hidden = false; });
  });
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-install-app]');
    if (!button || !offer) { return; }
    event.preventDefault();
    offer.prompt();
    offer.userChoice.finally(function () { offer = null; button.hidden = true; });
  });
  window.addEventListener('appinstalled', function () {
    document.querySelectorAll('[data-install-app]').forEach(function (button) { button.hidden = true; });
  });
})();
</script>
