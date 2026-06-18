(function () {
  'use strict';

  function directChildByClass(element, className) {
    var children = Array.prototype.slice.call(element.children || []);
    for (var index = 0; index < children.length; index += 1) {
      if (children[index].classList && children[index].classList.contains(className)) {
        return children[index];
      }
    }

    return null;
  }

  function directChildByTag(element, tagName) {
    var expectedTagName = String(tagName).toUpperCase();
    var children = Array.prototype.slice.call(element.children || []);
    for (var index = 0; index < children.length; index += 1) {
      if (children[index].tagName === expectedTagName) {
        return children[index];
      }
    }

    return null;
  }

  function setPanelFocusable(submenu, focusable) {
    Array.prototype.slice.call(submenu.querySelectorAll('a, button, input, select, textarea, [tabindex]')).forEach(function (element) {
      if (focusable) {
        if (element.getAttribute('data-community-home-accordion-tabindex') === 'remove') {
          element.removeAttribute('tabindex');
        } else if (element.hasAttribute('data-community-home-accordion-tabindex')) {
          element.setAttribute('tabindex', element.getAttribute('data-community-home-accordion-tabindex'));
        }
        element.removeAttribute('data-community-home-accordion-tabindex');
        return;
      }

      if (!element.hasAttribute('data-community-home-accordion-tabindex')) {
        element.setAttribute(
          'data-community-home-accordion-tabindex',
          element.hasAttribute('tabindex') ? element.getAttribute('tabindex') : 'remove'
        );
      }
      element.setAttribute('tabindex', '-1');
    });
  }

  function syncClosedDescendantPanels(submenu) {
    Array.prototype.slice.call(submenu.querySelectorAll('.community-home-accordion-submenu[aria-hidden="true"]')).forEach(function (closedSubmenu) {
      setPanelFocusable(closedSubmenu, false);
    });
  }

  function setAccordionOpen(item, toggle, submenu, open) {
    item.classList.toggle('is-community-home-accordion-open', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? '하위 메뉴 접기' : '하위 메뉴 펼침');
    toggle.setAttribute('title', open ? '하위 메뉴 접기' : '하위 메뉴 펼침');
    submenu.setAttribute('aria-hidden', open ? 'false' : 'true');
    submenu.style.maxHeight = open ? 'none' : '0px';
    setPanelFocusable(submenu, open);
    if (open) {
      syncClosedDescendantPanels(submenu);
    }
    updateAncestorAccordionHeights(item);
  }

  function updateAncestorAccordionHeights(item) {
    var parentList = item.parentElement;
    while (parentList && parentList.classList && parentList.classList.contains('sr-site-menu-list')) {
      if (parentList.getAttribute('aria-hidden') === 'false') {
        parentList.style.maxHeight = 'none';
      }

      var parentItem = parentList.parentElement;
      parentList = parentItem ? parentItem.parentElement : null;
    }
  }

  function refreshOpenAccordionHeights() {
    Array.prototype.slice.call(document.querySelectorAll('[data-community-home-accordion] .community-home-accordion-submenu[aria-hidden="false"]')).forEach(function (submenu) {
      submenu.style.maxHeight = 'none';
    });
  }

  function initHomeSidebarMenu() {
    Array.prototype.slice.call(document.querySelectorAll('[data-community-home-accordion] .sr-site-menu')).forEach(function (menu) {
      menu.classList.add('community-home-accordion-menu');
    });

    Array.prototype.slice.call(document.querySelectorAll('[data-community-home-accordion] .sr-site-menu-item')).forEach(function (item) {
      item.classList.add('community-home-accordion-item');
    });

    Array.prototype.slice.call(document.querySelectorAll('[data-community-home-accordion] .sr-site-menu-list')).forEach(function (list) {
      list.classList.add('community-home-accordion-list');
    });

    Array.prototype.slice.call(document.querySelectorAll('[data-community-home-accordion] .sr-site-menu-item-has-children')).forEach(function (item, index) {
      if (item.getAttribute('data-community-home-menu-ready') === '1') {
        return;
      }

      var link = directChildByTag(item, 'a');
      var submenu = directChildByClass(item, 'sr-site-menu-list');
      if (!link || !submenu) {
        return;
      }

      var submenuId = submenu.getAttribute('id') || 'community-home-sidebar-submenu-' + String(index + 1);
      submenu.setAttribute('id', submenuId);
      submenu.removeAttribute('hidden');
      link.removeAttribute('aria-haspopup');
      link.removeAttribute('aria-expanded');
      link.removeAttribute('aria-controls');
      link.classList.add('community-home-accordion-link');
      submenu.classList.add('community-home-accordion-submenu');

      var toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'community-home-accordion-toggle';
      toggle.setAttribute('aria-controls', submenuId);
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', '하위 메뉴 펼침');
      toggle.setAttribute('title', '하위 메뉴 펼침');

      link.insertAdjacentElement('afterend', toggle);
      item.setAttribute('data-community-home-menu-ready', '1');

      setAccordionOpen(item, toggle, submenu, true);

      link.addEventListener('click', function (event) {
        var href = String(link.getAttribute('href') || '').trim();
        if (href === '' || href === '#') {
          event.preventDefault();
          setAccordionOpen(item, toggle, submenu, toggle.getAttribute('aria-expanded') !== 'true');
        }
      });

      toggle.addEventListener('click', function () {
        setAccordionOpen(item, toggle, submenu, toggle.getAttribute('aria-expanded') !== 'true');
      });
    });
  }

  function init() {
    initHomeSidebarMenu();
    window.addEventListener('resize', refreshOpenAccordionHeights);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
    return;
  }

  init();
})();
