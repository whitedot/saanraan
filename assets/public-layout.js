(function () {
  'use strict';

  var HEADER_SELECTOR = '[data-public-scroll-header]';
  var HIDDEN_CLASS = 'is-public-layout-header-hidden';
  var MIN_DELTA = 4;
  var TOP_OFFSET = 8;

  function getScrollY() {
    return Math.max(0, window.pageYOffset || document.documentElement.scrollTop || 0);
  }

  function bindHeader(header) {
    var lastY = getScrollY();
    var ticking = false;

    function update() {
      var currentY = getScrollY();
      var delta = currentY - lastY;
      var hideAfter = Math.max(header.offsetHeight + 16, 76);

      if (currentY <= TOP_OFFSET) {
        header.classList.remove(HIDDEN_CLASS);
      } else if (Math.abs(delta) >= MIN_DELTA) {
        if (delta > 0 && currentY > hideAfter) {
          header.classList.add(HIDDEN_CLASS);
        } else if (delta < 0) {
          header.classList.remove(HIDDEN_CLASS);
        }
      }

      lastY = currentY;
      ticking = false;
    }

    function requestUpdate() {
      if (ticking) {
        return;
      }

      ticking = true;
      window.requestAnimationFrame(update);
    }

    window.addEventListener('scroll', requestUpdate, { passive: true });
    window.addEventListener('resize', requestUpdate);
    update();
  }

  function closeNotificationMenus(exceptMenu) {
    Array.prototype.slice.call(document.querySelectorAll('.public-layout-notification-menu[open]')).forEach(function (menu) {
      if (menu !== exceptMenu) {
        menu.removeAttribute('open');
      }
    });
  }

  function bindNotificationMenus() {
    document.addEventListener('click', function (event) {
      var target = event.target;
      if (!(target instanceof Element)) {
        closeNotificationMenus(null);
        return;
      }

      var currentMenu = target.closest('.public-layout-notification-menu');
      closeNotificationMenus(currentMenu);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeNotificationMenus(null);
      }
    });
  }

  function init() {
    Array.prototype.slice.call(document.querySelectorAll(HEADER_SELECTOR)).forEach(bindHeader);
    bindNotificationMenus();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
    return;
  }

  init();
})();
