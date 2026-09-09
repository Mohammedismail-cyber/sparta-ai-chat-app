/**
 * Splash / preloader controller.
 * Shown on first paint; hides itself once the page is ready and a
 * minimum display time has passed, so it always reads as an
 * intentional beat rather than a flash.
 */
(function () {
  'use strict';

  const MIN_VISIBLE_MS = 700;
  const start = Date.now();

  function hideSplash() {
    const splash = document.getElementById('splash');
    if (!splash) return;
    const elapsed = Date.now() - start;
    const wait = Math.max(0, MIN_VISIBLE_MS - elapsed);
    setTimeout(() => splash.classList.add('hide'), wait);
  }

  // Expose so pages that gate on real data (e.g. chat.php loading
  // conversations) can call it once their fetch resolves.
  window.SPARTA_HIDE_SPLASH = hideSplash;

  // Default: hide once the page has fully loaded, for pages that
  // don't have their own async readiness signal (e.g. login.php).
  if (!window.SPARTA_MANUAL_SPLASH) {
    if (document.readyState === 'complete') {
      hideSplash();
    } else {
      window.addEventListener('load', hideSplash);
    }
  }
})();
