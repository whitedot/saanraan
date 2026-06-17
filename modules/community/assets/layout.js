(function () {
  'use strict';

  var SCROLL_NAV_SELECTOR = '[data-community-scroll-nav]';
  var HIDDEN_CLASS = 'is-community-layout-nav-hidden';
  var STUCK_CLASS = 'is-community-layout-nav-stuck';
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

      nav.classList.toggle(STUCK_CLASS, currentY > TOP_OFFSET);

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

  function handleSearchFormSubmit(event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-community-layout-search-form')) {
      return;
    }

    var input = form.querySelector('[data-community-layout-search-input]');
    var keyword = input instanceof HTMLInputElement ? input.value.trim() : '';
    var minLength = parseInt(form.getAttribute('data-community-layout-search-min-length') || '2', 10);
    var alertMessage = form.getAttribute('data-community-layout-search-alert') || '검색어는 2글자 이상 입력해 주세요.';

    if (!Number.isFinite(minLength) || minLength < 1) {
      minLength = 2;
    }

    if (keyword.length >= minLength) {
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    window.alert(alertMessage);
    if (input instanceof HTMLInputElement) {
      input.focus();
    }
  }

  function init() {
    Array.prototype.slice.call(document.querySelectorAll(SCROLL_NAV_SELECTOR)).forEach(bindScrollNav);
    document.addEventListener('submit', handleSearchFormSubmit, true);
    bindHeaderMenus();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
    return;
  }

  init();
})();
