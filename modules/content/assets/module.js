(function () {
  'use strict';

  function initToasts() {
    Array.prototype.slice.call(document.querySelectorAll('[data-content-toast-stack]')).forEach(function (toastStack) {
      function closeToast(toast) {
        if (!toast) {
          return;
        }
        toast.classList.add('removing');
        window.setTimeout(function () {
          toast.remove();
          if (toastStack.children.length === 0) {
            toastStack.remove();
          }
        }, 300);
      }

      toastStack.addEventListener('click', function (event) {
        var closeButton = event.target && event.target.closest ? event.target.closest('[data-content-toast-close]') : null;
        if (closeButton) {
          closeToast(closeButton.closest('[data-content-toast]'));
        }
      });

      Array.prototype.slice.call(toastStack.querySelectorAll('[data-content-toast]')).forEach(function (toast) {
        window.setTimeout(function () {
          closeToast(toast);
        }, 6500);
      });
    });
  }

  function closeSubmittedAssetConfirmationModal(form) {
    if (!form || form.getAttribute('data-content-asset-confirmation-submitted') === '1') {
      return;
    }

    form.setAttribute('data-content-asset-confirmation-submitted', '1');
    Array.prototype.slice.call(form.querySelectorAll('button[type="submit"], input[type="submit"]')).forEach(function (submitButton) {
      submitButton.disabled = true;
    });

    var overlay = form.closest ? form.closest('.content-asset-confirmation-modal.overlay') : null;
    if (!overlay) {
      return;
    }

    if (overlay.contains(document.activeElement) && typeof document.activeElement.blur === 'function') {
      document.activeElement.blur();
    }

    overlay.setAttribute('aria-hidden', 'true');
    overlay.setAttribute('inert', '');
    overlay.classList.remove('overlay-open');
    overlay.classList.remove('open');
    overlay.classList.add('hidden');
    overlay.classList.add('pointer-events-none');
    overlay.classList.add('opacity-0');

    if (overlay.id) {
      Array.prototype.slice.call(document.querySelectorAll('[data-overlay="#' + overlay.id + '"], [data-overlay="' + overlay.id + '"]')).forEach(function (trigger) {
        if (trigger.hasAttribute('aria-expanded')) {
          trigger.setAttribute('aria-expanded', 'false');
        }
      });
    }

    if (!document.querySelector('.overlay.overlay-open, .overlay.open')) {
      document.body.classList.remove('overflow-hidden');
    }
  }

  function initAssetConfirmationSubmitClose() {
    document.addEventListener('submit', function (event) {
      var form = event.target && event.target.closest ? event.target.closest('[data-content-asset-confirmation-close-on-submit]') : null;
      if (!form) {
        return;
      }

      closeSubmittedAssetConfirmationModal(form);
    });
  }

  function init() {
    initToasts();
    initAssetConfirmationSubmitClose();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
    return;
  }

  init();
})();
