(function () {
  'use strict';

  var HEADER_SELECTOR = '[data-community-scroll-header]';
  var HIDDEN_CLASS = 'is-community-layout-header-hidden';
  var SMART_STICKY_CONTAINER_SELECTOR = '.community-screen';
  var SMART_STICKY_PRIMARY_SELECTOR = ':scope > article';
  var SMART_STICKY_SECONDARY_SELECTOR = '.community-comments-panel';
  var SMART_STICKY_CLASS = 'is-community-smart-sticky';
  var SMART_STICKY_TOP_PROPERTY = '--community-smart-sticky-top';
  var SMART_STICKY_BREAKPOINT = 900;
  var SMART_STICKY_TOP = 76;
  var SMART_STICKY_BOTTOM_GAP = 20;
  var SMART_STICKY_MIN_DIFF = 32;
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

  function bindSmartSticky(container) {
    var primary = container.querySelector(SMART_STICKY_PRIMARY_SELECTOR);
    var secondary = container.querySelector(SMART_STICKY_SECONDARY_SELECTOR);
    var ticking = false;

    if (!primary || !secondary) {
      return;
    }

    function resetElement(element) {
      element.classList.remove(SMART_STICKY_CLASS);
      element.style.removeProperty(SMART_STICKY_TOP_PROPERTY);
    }

    function measure(element) {
      return Math.ceil(element.getBoundingClientRect().height);
    }

    function update() {
      resetElement(primary);
      resetElement(secondary);

      if (window.innerWidth <= SMART_STICKY_BREAKPOINT) {
        ticking = false;
        return;
      }

      var primaryHeight = measure(primary);
      var secondaryHeight = measure(secondary);
      var heightDiff = Math.abs(primaryHeight - secondaryHeight);

      if (heightDiff < SMART_STICKY_MIN_DIFF) {
        ticking = false;
        return;
      }

      var stickyTarget = primaryHeight < secondaryHeight ? primary : secondary;
      var targetHeight = measure(stickyTarget);
      var availableHeight = window.innerHeight - SMART_STICKY_TOP - SMART_STICKY_BOTTOM_GAP;
      var stickyTop = SMART_STICKY_TOP;

      if (targetHeight > availableHeight) {
        stickyTop = Math.min(SMART_STICKY_TOP, window.innerHeight - targetHeight - SMART_STICKY_BOTTOM_GAP);
      }

      stickyTarget.style.setProperty(SMART_STICKY_TOP_PROPERTY, String(Math.round(stickyTop)) + 'px');
      stickyTarget.classList.add(SMART_STICKY_CLASS);
      ticking = false;
    }

    function requestUpdate() {
      if (ticking) {
        return;
      }

      ticking = true;
      window.requestAnimationFrame(update);
    }

    window.addEventListener('load', requestUpdate);
    window.addEventListener('resize', requestUpdate);

    if ('ResizeObserver' in window) {
      var observer = new ResizeObserver(requestUpdate);
      observer.observe(primary);
      observer.observe(secondary);
    }

    requestUpdate();
  }

  function init() {
    Array.prototype.slice.call(document.querySelectorAll(HEADER_SELECTOR)).forEach(bindHeader);
    Array.prototype.slice.call(document.querySelectorAll(SMART_STICKY_CONTAINER_SELECTOR)).forEach(bindSmartSticky);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
    return;
  }

  init();
})();
