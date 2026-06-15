(function () {
  'use strict';

  var SCROLL_NAV_SELECTOR = '[data-community-scroll-nav]';
  var HIDDEN_CLASS = 'is-community-layout-nav-hidden';
  var MIN_DELTA = 4;
  var TOP_OFFSET = 8;

  function getScrollY() {
    return Math.max(0, window.pageYOffset || document.documentElement.scrollTop || 0);
  }

  function bindScrollNav(nav) {
    var lastY = getScrollY();
    var ticking = false;

    function update() {
      var currentY = getScrollY();
      var delta = currentY - lastY;
      var hideAfter = Math.max(nav.offsetHeight + 16, 76);

      if (currentY <= TOP_OFFSET) {
        nav.classList.remove(HIDDEN_CLASS);
      } else if (Math.abs(delta) >= MIN_DELTA) {
        if (delta > 0 && currentY > hideAfter) {
          nav.classList.add(HIDDEN_CLASS);
        } else if (delta < 0) {
          nav.classList.remove(HIDDEN_CLASS);
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
    Array.prototype.slice.call(document.querySelectorAll('.community-layout-notification-menu[open], .community-layout-member-menu[open]')).forEach(function (menu) {
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

      var currentMenu = target.closest('.community-layout-notification-menu, .community-layout-member-menu');
      closeHeaderMenus(currentMenu);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeHeaderMenus(null);
      }
    });
  }

  function init() {
    Array.prototype.slice.call(document.querySelectorAll(SCROLL_NAV_SELECTOR)).forEach(bindScrollNav);
    bindHeaderMenus();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
    return;
  }

  init();
})();
