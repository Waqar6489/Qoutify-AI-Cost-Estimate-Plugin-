(function () {
  'use strict';

  // Show / hide password with the eye button.
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-qtf-toggle]') : null;
    if (!btn) { return; }
    var input = btn.parentNode.querySelector('input');
    if (!input) { return; }
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    btn.setAttribute('aria-label', btn.getAttribute(show ? 'data-label-hide' : 'data-label-show') || '');
  });

  // "Email verified" screen: go to the dashboard after 4 seconds.
  var link = document.querySelector('[data-qtf-redirect]');
  if (link && link.getAttribute('href')) {
    setTimeout(function () {
      window.location.href = link.getAttribute('href');
    }, 4000);
  }
})();
