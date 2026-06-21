(function () {
  'use strict';

  function initToasts() {
    Array.prototype.slice.call(document.querySelectorAll('[data-quiz-toast-stack]')).forEach(function (toastStack) {
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
        var closeButton = event.target && event.target.closest ? event.target.closest('[data-quiz-toast-close]') : null;
        if (closeButton) {
          closeToast(closeButton.closest('[data-quiz-toast]'));
        }
      });

      Array.prototype.slice.call(toastStack.querySelectorAll('[data-quiz-toast]')).forEach(function (toast) {
        window.setTimeout(function () {
          closeToast(toast);
        }, 6500);
      });
    });
  }

  function init() {
    initToasts();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
    return;
  }

  init();
})();
