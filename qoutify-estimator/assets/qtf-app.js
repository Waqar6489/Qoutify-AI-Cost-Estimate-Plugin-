(function () {
  'use strict';

  var body = document.body;
  var burger = document.querySelector('.qtf-burger');
  var sidebar = document.getElementById('qtf-sidebar');
  var overlay = document.querySelector('[data-qtf-overlay]');

  /* ---------- Mobile menu: open and close, X animation is done in CSS ---------- */
  function setMenu(open, restoreFocus) {
    body.classList.toggle('qtf-menu-open', open);
    if (burger) {
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      burger.setAttribute('aria-label', burger.getAttribute(open ? 'data-label-close' : 'data-label-open') || '');
    }
    if (open && sidebar) {
      var first = sidebar.querySelector('a');
      if (first) { first.focus(); }
    } else if (burger && restoreFocus) {
      burger.focus();
    }
  }
  function menuIsOpen() { return body.classList.contains('qtf-menu-open'); }

  if (burger) {
    burger.addEventListener('click', function () { setMenu(!menuIsOpen(), true); });
  }
  if (overlay) {
    overlay.addEventListener('click', function () { setMenu(false, true); });
  }
  if (sidebar) {
    var menuLinks = sidebar.querySelectorAll('a');
    for (var i = 0; i < menuLinks.length; i++) {
      menuLinks[i].addEventListener('click', function () { setMenu(false, false); });
    }
  }
  window.addEventListener('resize', function () {
    if (window.innerWidth > 900 && menuIsOpen()) { setMenu(false, false); }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && menuIsOpen()) { setMenu(false, true); return; }
    // Keep Tab inside the open drawer (the menu button is included so it can be closed).
    if (e.key === 'Tab' && menuIsOpen() && sidebar && burger) {
      var items = Array.prototype.slice.call(sidebar.querySelectorAll('a'));
      items.unshift(burger);
      var firstItem = items[0];
      var lastItem = items[items.length - 1];
      if (e.shiftKey && document.activeElement === firstItem) { e.preventDefault(); lastItem.focus(); }
      else if (!e.shiftKey && document.activeElement === lastItem) { e.preventDefault(); firstItem.focus(); }
    }
  });

  /* ---------- Show / hide password ---------- */
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

  /* ---------- Forms that ask before sending ---------- */
  var confirmForms = document.querySelectorAll('form[data-qtf-confirm]');
  for (var c = 0; c < confirmForms.length; c++) {
    confirmForms[c].addEventListener('submit', function (e) {
      if (!window.confirm(this.getAttribute('data-qtf-confirm'))) { e.preventDefault(); }
    });
  }

  /* ---------- Delete account: show the confirmation step first ---------- */
  var openBtn = document.getElementById('qtf-delete-open');
  var panel = document.getElementById('qtf-delete-panel');
  var cancel = document.getElementById('qtf-delete-cancel');
  var delForm = document.getElementById('qtf-delete-form');
  if (openBtn && panel) {
    openBtn.addEventListener('click', function () { panel.hidden = false; openBtn.hidden = true; });
  }
  if (cancel && panel && openBtn) {
    cancel.addEventListener('click', function () { panel.hidden = true; openBtn.hidden = false; openBtn.focus(); });
  }
  if (delForm) {
    delForm.addEventListener('submit', function (e) {
      if (!window.confirm('This permanently deletes your account. Continue?')) { e.preventDefault(); }
    });
  }

  /* ---------- Profile image: drag and drop ---------- */
  var dz = document.querySelector('[data-qtf-dropzone]');
  if (dz) {
    var fileInput = dz.querySelector('input[type="file"]');
    var nameEl = dz.querySelector('[data-qtf-filename]');
    var showName = function () {
      if (nameEl && fileInput) { nameEl.textContent = (fileInput.files && fileInput.files.length) ? fileInput.files[0].name : ''; }
    };
    if (fileInput) { fileInput.addEventListener('change', showName); }
    ['dragenter', 'dragover'].forEach(function (ev) {
      dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('is-over'); });
    });
    dz.addEventListener('drop', function (e) {
      if (fileInput && e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        showName();
      }
    });
  }

  /* ---------- Buy credits: show the total while typing (the server recalculates it) ---------- */
  var buy = document.querySelector('.qtf-buy-form');
  if (buy) {
    var amount = buy.querySelector('input[name="credits"]');
    var out = buy.querySelector('[data-qtf-total]');
    var price = parseFloat(buy.getAttribute('data-price'));
    var symbol = buy.getAttribute('data-symbol') || '$';
    var decimals = parseInt(buy.getAttribute('data-decimals'), 10);
    if (isNaN(decimals)) { decimals = 2; }
    var update = function () {
      var n = parseInt(amount.value, 10);
      var max = parseInt(amount.max, 10) || 500;
      if (!(n >= 1) || n > max || isNaN(price)) { out.textContent = '-'; return; }
      var total = Math.round(n * price * 100) / 100;
      out.textContent = symbol + total.toLocaleString(undefined, {
        minimumFractionDigits: (total % 1 === 0) ? 0 : decimals,
        maximumFractionDigits: decimals
      });
    };
    if (amount && out) { amount.addEventListener('input', update); }
  }

  /* ---------- Print / save as PDF ---------- */
  var printBtn = document.querySelector('[data-qtf-print]');
  if (printBtn) {
    printBtn.addEventListener('click', function () { window.print(); });
  }

  /* ---------- Review popup (only present for a completed estimate without a review) ---------- */
  var modal = document.getElementById('qtf-review-modal');
  if (modal) {
    var lastFocus = null;
    var openModal = function () {
      lastFocus = document.activeElement;
      modal.hidden = false;
      var f = modal.querySelector('input, textarea, button');
      if (f) { f.focus(); }
    };
    // Closing without a choice counts as "maybe later" and is remembered on the server.
    var dismiss = function () {
      var form = document.getElementById('qtf-dismiss-form');
      modal.hidden = true;
      if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
      if (form && window.fetch) {
        fetch(window.location.href, { method: 'POST', body: new FormData(form), credentials: 'same-origin' }).catch(function () {});
      }
    };
    setTimeout(openModal, 1200);
    var closeBtn = modal.querySelector('[data-qtf-modal-close]');
    if (closeBtn) { closeBtn.addEventListener('click', dismiss); }
    modal.addEventListener('click', function (e) { if (e.target === modal) { dismiss(); } });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) { dismiss(); }
    });
  }

  /* ---------- Processing screen: ask for the status every 3 seconds ---------- */
  var box = document.getElementById('qtf-status');
  if (box && window.QTF) {
    var tries = 0;
    var poll = function () {
      tries++;
      if (tries > 120) { return; }
      fetch(window.QTF.rest + 'estimate/' + box.getAttribute('data-uuid') + '/status', {
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': window.QTF.nonce }
      }).then(function (r) { return r.json(); }).then(function (d) {
        if (d && (d.status === 'completed' || d.status === 'failed')) {
          window.location.reload();
        } else {
          setTimeout(poll, 3000);
        }
      }).catch(function () { setTimeout(poll, 5000); });
    };
    setTimeout(poll, 3000);
  }
})();
