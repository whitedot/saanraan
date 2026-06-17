(function () {
  'use strict';

  var HEADER_SELECTOR = '[data-public-scroll-header]';
  var HIDDEN_CLASS = 'is-public-layout-header-hidden';
  var STUCK_CLASS = 'is-public-layout-header-stuck';
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

      header.classList.toggle(STUCK_CLASS, currentY > TOP_OFFSET);

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

  function closeHeaderMenus(exceptMenu) {
    Array.prototype.slice.call(document.querySelectorAll('.public-layout-notification-menu[open], .public-layout-member-menu[open]')).forEach(function (menu) {
      if (menu !== exceptMenu) {
        menu.removeAttribute('open');
      }
    });
  }

  function bindHeaderMenus() {
    document.addEventListener('click', function (event) {
      var target = event.target;
      if (!(target instanceof Element)) {
        closeHeaderMenus(null);
        return;
      }

      var currentMenu = target.closest('.public-layout-notification-menu, .public-layout-member-menu');
      closeHeaderMenus(currentMenu);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeHeaderMenus(null);
      }
    });
  }

  function init() {
    Array.prototype.slice.call(document.querySelectorAll(HEADER_SELECTOR)).forEach(bindHeader);
    bindHeaderMenus();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
    return;
  }

  init();
})();
