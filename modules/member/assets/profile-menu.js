(function () {
  'use strict';

  function closeMemberProfileMenu(menu, restoreFocus) {
    var panel = menu.querySelector('.member-profile-menu-dropdown');
    if (panel && typeof panel.hidePopover === 'function' && panel.matches(':popover-open')) {
      panel.hidePopover();
    }
    menu.removeAttribute('open');
    if (restoreFocus) {
      var summary = menu.querySelector('summary');
      if (summary) summary.focus({ preventScroll: true });
    }
  }

  function closeMemberProfileMenus(exceptMenu) {
    Array.prototype.slice.call(document.querySelectorAll('.member-profile-menu[open]')).forEach(function (menu) {
      if (menu !== exceptMenu) closeMemberProfileMenu(menu, false);
    });
  }

  function placeMemberProfileMenu(menu) {
    var summary = menu.querySelector('summary');
    var panel = menu.querySelector('.member-profile-menu-dropdown');
    if (!summary || !panel) return;

    // Keep the panel in its owner DOM for theme inheritance, forms and modal focus.
    // The browser top layer escapes scroll clipping and transformed ancestors.
    if (typeof panel.showPopover === 'function') {
      panel.setAttribute('popover', 'manual');
      if (!panel.matches(':popover-open')) panel.showPopover();
    }
    var gap = 8;
    panel.style.position = 'fixed';
    panel.style.margin = '0';
    panel.style.inset = 'auto';
    panel.style.minWidth = 'min(9rem, calc(100vw - 16px))';
    panel.style.maxWidth = Math.max(0, window.innerWidth - gap * 2) + 'px';
    panel.style.maxHeight = Math.max(0, window.innerHeight - gap * 2) + 'px';
    panel.style.overflow = 'auto';
    var anchor = summary.getBoundingClientRect();
    var rect = panel.getBoundingClientRect();
    var top = anchor.bottom + 6;
    if (top + rect.height > window.innerHeight - gap && anchor.top - rect.height - 6 >= gap) {
      top = anchor.top - rect.height - 6;
    }
    panel.style.left = Math.max(gap, Math.min(anchor.left, window.innerWidth - rect.width - gap)) + 'px';
    panel.style.top = Math.max(gap, Math.min(top, window.innerHeight - rect.height - gap)) + 'px';
  }

  document.addEventListener('toggle', function (event) {
    var menu = event.target;
    if (!menu.classList || !menu.classList.contains('member-profile-menu')) return;
    if (menu.open) {
      closeMemberProfileMenus(menu);
      placeMemberProfileMenu(menu);
    } else {
      closeMemberProfileMenu(menu, false);
    }
  }, true);

  document.addEventListener('click', function (event) {
    var target = event.target;
    var currentMenu = target && typeof target.closest === 'function'
      ? target.closest('.member-profile-menu')
      : null;
    closeMemberProfileMenus(currentMenu);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      var menu = document.querySelector('.member-profile-menu[open]');
      if (menu) {
        event.preventDefault();
        event.stopImmediatePropagation();
        closeMemberProfileMenu(menu, true);
      }
    }
  }, true);

  // Close when the anchor's scroll container moves; scrolling the panel itself is allowed.
  window.addEventListener('scroll', function (event) {
    var target = event.target;
    if (target && target.closest && target.closest('.member-profile-menu-dropdown')) return;
    closeMemberProfileMenus(null);
  }, true);
  window.addEventListener('resize', function () {
    document.querySelectorAll('.member-profile-menu[open]').forEach(placeMemberProfileMenu);
  });
  document.querySelectorAll('.member-profile-menu[open]').forEach(placeMemberProfileMenu);
})();
