(function () {
  'use strict';

  var DROPDOWN_SELECTOR = '.hs-dropdown';
  var TOGGLE_SELECTOR = '.hs-dropdown-toggle';
  var MENU_SELECTOR = '.hs-dropdown-menu';
  var OPEN_CLASS = 'hs-dropdown-open';
  var LEGACY_OPEN_CLASS = 'open';
  var VIEWPORT_GAP = 8;
  var MENU_OFFSET = 6;

  var opened = [];

  function getElementTarget(target) {
    if (!target) {
      return null;
    }

    if (target.nodeType === 1) {
      return target;
    }

    return target.parentElement || null;
  }

  function findClosest(target, selector) {
    var element = getElementTarget(target);
    return element && typeof element.closest === 'function' ? element.closest(selector) : null;
  }

  function parseOption(dropdown, name, fallback) {
    var className = dropdown && dropdown.className ? String(dropdown.className) : '';
    var match = className.match(new RegExp('\\[--' + name + ':([^\\]]+)\\]'));
    var raw = match ? match[1] : '';

    if (!raw && window.getComputedStyle) {
      raw = window.getComputedStyle(dropdown).getPropertyValue('--' + name);
    }

    var value = String(raw || '').trim().toLowerCase();
    if (!value) {
      return fallback;
    }

    if ((value.charAt(0) === '"' && value.charAt(value.length - 1) === '"') || (value.charAt(0) === "'" && value.charAt(value.length - 1) === "'")) {
      value = value.slice(1, -1);
    }

    return value || fallback;
  }

  function getConfig(dropdown) {
    if (!dropdown._dropdownConfig) {
      dropdown._dropdownConfig = {
        trigger: parseOption(dropdown, 'trigger', 'click'),
        placement: parseOption(dropdown, 'placement', 'bottom-start'),
        autoClose: parseOption(dropdown, 'auto-close', 'all')
      };
    }

    return dropdown._dropdownConfig;
  }

  function getRefs(dropdown) {
    if (!dropdown) {
      return null;
    }

    var toggle = dropdown.querySelector(TOGGLE_SELECTOR);
    var menu = dropdown.querySelector(MENU_SELECTOR);

    if (!toggle || !menu) {
      return null;
    }

    return { toggle: toggle, menu: menu };
  }

  function getAnchor(dropdown, refs) {
    var splitGroup = refs && refs.toggle ? refs.toggle.closest('.dropdown-split') : null;
    return splitGroup || (refs ? refs.toggle : null);
  }

  function measure(menu) {
    var oldDisplay = menu.style.display;
    var oldVisibility = menu.style.visibility;
    var oldPointer = menu.style.pointerEvents;
    var oldPosition = menu.style.position;
    var oldLeft = menu.style.left;
    var oldTop = menu.style.top;
    var oldMarginTop = menu.style.marginTop;

    menu.style.display = 'block';
    menu.style.visibility = 'hidden';
    menu.style.pointerEvents = 'none';
    menu.style.position = 'fixed';
    menu.style.left = '0';
    menu.style.top = '0';
    menu.style.marginTop = '0';

    var result = {
      width: menu.offsetWidth,
      height: menu.offsetHeight
    };

    menu.style.display = oldDisplay;
    menu.style.visibility = oldVisibility;
    menu.style.pointerEvents = oldPointer;
    menu.style.position = oldPosition;
    menu.style.left = oldLeft;
    menu.style.top = oldTop;
    menu.style.marginTop = oldMarginTop;

    return result;
  }

  function normalizePlacement(placement) {
    var value = String(placement || 'bottom-start').toLowerCase();

    if (value === 'bottom') {
      return { side: 'bottom', align: 'center' };
    }

    if (value === 'top') {
      return { side: 'top', align: 'center' };
    }

    if (value === 'bottom-right') {
      return { side: 'bottom', align: 'end' };
    }

    if (value === 'bottom-left') {
      return { side: 'bottom', align: 'start' };
    }

    if (value === 'top-left') {
      return { side: 'top', align: 'start' };
    }

    if (value === 'top-right') {
      return { side: 'top', align: 'end' };
    }

    var parts = value.split('-');
    var side = parts[0] || 'bottom';
    var align = parts[1] || (side === 'top' || side === 'bottom' ? 'start' : 'center');

    if (align === 'left') {
      align = 'start';
    }

    if (align === 'right') {
      align = 'end';
    }

    return { side: side, align: align };
  }

  function clamp(value, min, max) {
    if (max < min) {
      return min;
    }

    return Math.min(Math.max(value, min), max);
  }

  function getViewportBounds() {
    return {
      left: VIEWPORT_GAP,
      top: VIEWPORT_GAP,
      right: Math.max(VIEWPORT_GAP, window.innerWidth - VIEWPORT_GAP),
      bottom: Math.max(VIEWPORT_GAP, window.innerHeight - VIEWPORT_GAP)
    };
  }

  function getSideCandidates(side) {
    if (side === 'top') {
      return ['top', 'bottom'];
    }

    if (side === 'left') {
      return ['left', 'right'];
    }

    if (side === 'right') {
      return ['right', 'left'];
    }

    return ['bottom', 'top'];
  }

  function getCandidatePosition(anchorRect, menuSize, side, align) {
    var left = anchorRect.left;
    var top = anchorRect.bottom + MENU_OFFSET;

    if (side === 'top') {
      top = anchorRect.top - menuSize.height - MENU_OFFSET;
    } else if (side === 'left') {
      left = anchorRect.left - menuSize.width - MENU_OFFSET;
      top = anchorRect.top;
    } else if (side === 'right') {
      left = anchorRect.right + MENU_OFFSET;
      top = anchorRect.top;
    }

    if (side === 'top' || side === 'bottom') {
      if (align === 'end') {
        left = anchorRect.right - menuSize.width;
      } else if (align === 'center') {
        left = anchorRect.left + (anchorRect.width - menuSize.width) / 2;
      }
    } else {
      if (align === 'end') {
        top = anchorRect.bottom - menuSize.height;
      } else if (align === 'center') {
        top = anchorRect.top + (anchorRect.height - menuSize.height) / 2;
      }
    }

    return { left: left, top: top };
  }

  function getOverflowScore(position, menuSize, bounds) {
    var overflowLeft = Math.max(0, bounds.left - position.left);
    var overflowTop = Math.max(0, bounds.top - position.top);
    var overflowRight = Math.max(0, position.left + menuSize.width - bounds.right);
    var overflowBottom = Math.max(0, position.top + menuSize.height - bounds.bottom);

    return overflowLeft + overflowTop + overflowRight + overflowBottom;
  }

  function choosePlacement(anchorRect, menuSize, placement, bounds) {
    var sides = getSideCandidates(placement.side);
    var best = null;

    for (var i = 0; i < sides.length; i += 1) {
      var side = sides[i];
      var position = getCandidatePosition(anchorRect, menuSize, side, placement.align);
      var score = getOverflowScore(position, menuSize, bounds) + (i > 0 ? 1 : 0);

      if (!best || score < best.score) {
        best = {
          side: side,
          left: position.left,
          top: position.top,
          score: score
        };
      }
    }

    return best || {
      side: placement.side,
      left: anchorRect.left,
      top: anchorRect.bottom + MENU_OFFSET
    };
  }

  function place(dropdown) {
    var refs = getRefs(dropdown);
    if (!refs) {
      return;
    }

    var config = getConfig(dropdown);
    var placement = normalizePlacement(config.placement);
    var anchor = getAnchor(dropdown, refs);
    var anchorRect = anchor ? anchor.getBoundingClientRect() : refs.toggle.getBoundingClientRect();
    var computedMenuStyles = window.getComputedStyle ? window.getComputedStyle(refs.menu) : null;
    var configuredMinWidth = computedMenuStyles ? parseFloat(computedMenuStyles.minWidth) : 0;
    var targetMinWidth = Math.max(anchorRect.width, configuredMinWidth || 0, 120);

    refs.menu.style.minWidth = targetMinWidth + 'px';

    var menuSize = measure(refs.menu);
    var bounds = getViewportBounds();
    var selected = choosePlacement(anchorRect, menuSize, placement, bounds);

    var maxLeft = bounds.right - menuSize.width;
    var maxTop = bounds.bottom - menuSize.height;
    var safeLeft = clamp(selected.left, bounds.left, maxLeft);
    var safeTop = clamp(selected.top, bounds.top, maxTop);

    var availableWidth = Math.max(80, Math.floor(bounds.right - safeLeft));
    var availableHeight = Math.max(80, Math.floor(bounds.bottom - safeTop));

    refs.menu.style.position = 'fixed';
    refs.menu.style.left = safeLeft + 'px';
    refs.menu.style.top = safeTop + 'px';
    refs.menu.style.marginTop = '0';
    refs.menu.style.maxWidth = availableWidth + 'px';
    refs.menu.style.maxHeight = availableHeight + 'px';
    refs.menu.style.overflowY = menuSize.height > availableHeight ? 'auto' : '';
  }

  function syncAria(dropdown, expanded) {
    var refs = getRefs(dropdown);
    if (!refs) {
      return;
    }

    refs.toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    refs.menu.setAttribute('aria-hidden', expanded ? 'false' : 'true');
  }

  function syncVisibility(dropdown, expanded) {
    var refs = getRefs(dropdown);
    if (!refs) {
      return;
    }

    refs.menu.style.position = 'fixed';
    refs.menu.style.marginTop = '0';

    if (expanded) {
      refs.menu.style.left = refs.menu.style.left || '0px';
      refs.menu.style.top = refs.menu.style.top || '0px';
    }

    refs.menu.style.display = expanded ? 'block' : 'none';
    refs.menu.style.opacity = expanded ? '1' : '0';
    refs.menu.style.visibility = expanded ? 'visible' : 'hidden';
  }

  function closeDropdown(dropdown) {
    if (!dropdown || !dropdown.classList.contains(OPEN_CLASS)) {
      return;
    }

    dropdown.classList.remove(OPEN_CLASS);
    dropdown.classList.remove(LEGACY_OPEN_CLASS);
    syncAria(dropdown, false);
    syncVisibility(dropdown, false);
    dropdown.dispatchEvent(new CustomEvent('ui.dropdown.close'));

    opened = opened.filter(function (item) {
      return item !== dropdown;
    });
  }

  function closeAll(except) {
    opened.slice().forEach(function (dropdown) {
      if (dropdown !== except) {
        closeDropdown(dropdown);
      }
    });
  }

  function openDropdown(dropdown) {
    if (!dropdown || dropdown.classList.contains(OPEN_CLASS)) {
      return;
    }

    closeAll(dropdown);
    dropdown.classList.add(OPEN_CLASS);
    dropdown.classList.add(LEGACY_OPEN_CLASS);
    syncAria(dropdown, true);
    syncVisibility(dropdown, true);
    place(dropdown);

    if (opened.indexOf(dropdown) === -1) {
      opened.push(dropdown);
    }

    dropdown.dispatchEvent(new CustomEvent('ui.dropdown.open'));
  }

  function toggleDropdown(dropdown) {
    if (!dropdown) {
      return;
    }

    if (dropdown.classList.contains(OPEN_CLASS)) {
      closeDropdown(dropdown);
      return;
    }

    openDropdown(dropdown);
  }

  function shouldAutoClose(dropdown, eventTarget) {
    var config = getConfig(dropdown);
    var refs = getRefs(dropdown);
    if (!refs) {
      return false;
    }

    if (config.autoClose === 'outside') {
      return !refs.menu.contains(eventTarget);
    }

    if (config.autoClose === 'inside') {
      return refs.menu.contains(eventTarget);
    }

    if (config.autoClose === 'false' || config.autoClose === 'manual') {
      return false;
    }

    return true;
  }

  function bindDropdown(dropdown) {
    if (!dropdown || dropdown._dropdownBound) {
      return;
    }

    var refs = getRefs(dropdown);
    if (!refs) {
      return;
    }

    dropdown._dropdownBound = true;
    syncAria(dropdown, false);
    syncVisibility(dropdown, false);

    var config = getConfig(dropdown);

    if (config.trigger === 'hover') {
      dropdown.addEventListener('mouseenter', function () {
        openDropdown(dropdown);
      });

      dropdown.addEventListener('mouseleave', function () {
        closeDropdown(dropdown);
      });
    }

    refs.toggle.addEventListener('click', function (event) {
      event.preventDefault();
      toggleDropdown(dropdown);
    });

    refs.toggle.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openDropdown(dropdown);
      }

      if (event.key === 'Escape') {
        closeDropdown(dropdown);
      }
    });
  }

  function init() {
    Array.prototype.slice.call(document.querySelectorAll(DROPDOWN_SELECTOR)).forEach(bindDropdown);
  }

  document.addEventListener('click', function (event) {
    var target = getElementTarget(event.target);
    var activeDropdown = findClosest(target, DROPDOWN_SELECTOR);

    opened.slice().forEach(function (dropdown) {
      if (dropdown === activeDropdown) {
        if (shouldAutoClose(dropdown, target) && !findClosest(target, TOGGLE_SELECTOR)) {
          closeDropdown(dropdown);
        }
        return;
      }

      closeDropdown(dropdown);
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeAll();
    }
  });

  window.addEventListener('resize', function () {
    opened.forEach(place);
  });

  window.addEventListener('scroll', function () {
    opened.forEach(place);
  }, true);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
    return;
  }

  init();
})();
