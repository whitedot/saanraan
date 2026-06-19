(function () {
  'use strict';

  function initHomeSidebarMenu() {
    Array.prototype.slice.call(document.querySelectorAll('[data-community-home-accordion] .sr-site-menu')).forEach(function (menu) {
      menu.classList.add('community-home-accordion-menu');
    });

    Array.prototype.slice.call(document.querySelectorAll('[data-community-home-accordion] .sr-site-menu-item')).forEach(function (item) {
      item.classList.add('community-home-accordion-item');
    });

    Array.prototype.slice.call(document.querySelectorAll('[data-community-home-accordion] .sr-site-menu-list')).forEach(function (list) {
      list.classList.add('community-home-accordion-list');
      list.removeAttribute('hidden');
      if (list.parentElement && list.parentElement.classList.contains('sr-site-menu-item')) {
        list.classList.add('community-home-accordion-submenu');
        list.setAttribute('aria-hidden', 'false');
        list.style.maxHeight = 'none';
      }
    });

    Array.prototype.slice.call(document.querySelectorAll('[data-community-home-accordion] .sr-site-menu-item-has-children')).forEach(function (item) {
      if (item.getAttribute('data-community-home-menu-ready') === '1') {
        return;
      }

      Array.prototype.slice.call(item.children || []).forEach(function (child) {
        if (child.matches && child.matches('a, .sr-site-menu-link')) {
          child.removeAttribute('aria-haspopup');
          child.removeAttribute('aria-expanded');
          child.removeAttribute('aria-controls');
          child.classList.add('community-home-accordion-link');
          if (child.tagName === 'BUTTON') {
            child.disabled = true;
            child.setAttribute('aria-disabled', 'true');
          }
        }
      });
      item.setAttribute('data-community-home-menu-ready', '1');
    });
  }

  function init() {
    initHomeSidebarMenu();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
    return;
  }

  init();
})();
