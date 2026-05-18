/*
 * Saanraan common UI interactions.
 * Shared by project screens and UI-KIT previews.
 * Owns dropdown, overlay/modal, and tablist behavior without UI-KIT shell dependencies.
 */

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
    var dataName = 'dropdown' + name.replace(/(^|-)([a-z])/g, function (_, boundary, letter) {
      return letter.toUpperCase();
    });
    var dataValue = dropdown && dropdown.dataset ? dropdown.dataset[dataName] : '';
    if (!dataValue && dropdown && typeof dropdown.querySelector === 'function') {
      var toggle = dropdown.querySelector(TOGGLE_SELECTOR);
      dataValue = toggle && toggle.dataset ? toggle.dataset[dataName] : '';
    }

    if (dataValue) {
      return String(dataValue).trim().toLowerCase() || fallback;
    }

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


(function () {
  'use strict';

  var ACTIVE_CLASS = 'hs-overlay-open';
  var OPEN_CLASS = 'open';
  var HIDDEN_CLASS = 'hidden';
  var DISABLED_CLASS = 'pointer-events-none';
  var FADE_CLASS = 'opacity-0';
  var overlayStack = [];
  var FOCUSABLE_SELECTOR = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';
  var OVERLAY_TRIGGER_SELECTOR = '[data-hs-overlay]';

  var getElementTarget = function getElementTarget(target) {
    if (!target) {
      return null;
    }

    if (target.nodeType === 1) {
      return target;
    }

    return target.parentElement || null;
  };

  var closestFromEventTarget = function closestFromEventTarget(target, selector) {
    var element = getElementTarget(target);
    return element && typeof element.closest === 'function' ? element.closest(selector) : null;
  };

  var focusElement = function focusElement(element) {
    if (!element || typeof element.focus !== 'function') {
      return false;
    }

    if (!document.contains(element)) {
      return false;
    }

    try {
      element.focus({ preventScroll: true });
    } catch (error) {
      element.focus();
    }

    return document.activeElement === element;
  };

  var isValidFocusTarget = function isValidFocusTarget(element, overlay) {
    if (!element || !document.contains(element)) {
      return false;
    }

    if (overlay && overlay.contains(element)) {
      return false;
    }

    var hiddenOverlay = element.closest && element.closest('.hs-overlay');
    if (hiddenOverlay && hiddenOverlay.getAttribute('aria-hidden') === 'true') {
      return false;
    }

    return true;
  };

  var focusBodyFallback = function focusBodyFallback() {
    if (!document.body) {
      return false;
    }

    var previousTabindex = document.body.getAttribute('tabindex');
    document.body.setAttribute('tabindex', '-1');
    var focused = focusElement(document.body);

    if (previousTabindex === null) {
      document.body.removeAttribute('tabindex');
    } else {
      document.body.setAttribute('tabindex', previousTabindex);
    }

    return focused || document.activeElement === document.body;
  };

  var findReturnTarget = function findReturnTarget(overlay) {
    if (!overlay || !overlay.id) {
      return null;
    }

    var triggers = document.querySelectorAll('[data-hs-overlay="#' + overlay.id + '"], [data-hs-overlay="' + overlay.id + '"]');
    for (var i = 0; i < triggers.length; i += 1) {
      if (isValidFocusTarget(triggers[i], overlay)) {
        return triggers[i];
      }
    }

    return null;
  };

  var restoreFocus = function restoreFocus(overlay) {
    if (!overlay) {
      return false;
    }

    var active = document.activeElement;
    if (!active || !overlay.contains(active)) {
      return true;
    }

    if (isValidFocusTarget(overlay._overlayReturnTarget, overlay) && focusElement(overlay._overlayReturnTarget)) {
      return true;
    }

    if (isValidFocusTarget(overlay._overlayPreviousActive, overlay) && focusElement(overlay._overlayPreviousActive)) {
      return true;
    }

    var discoveredTarget = findReturnTarget(overlay);
    if (isValidFocusTarget(discoveredTarget, overlay) && focusElement(discoveredTarget)) {
      return true;
    }

    if (typeof active.blur === 'function') {
      active.blur();
    }

    return !overlay.contains(document.activeElement);
  };

  var focusOverlay = function focusOverlay(overlay) {
    if (!overlay) {
      return;
    }

    var autofocusTarget = overlay.querySelector('[data-overlay-focus]');
    if (autofocusTarget && focusElement(autofocusTarget)) {
      return;
    }

    var focusable = overlay.querySelector(FOCUSABLE_SELECTOR);
    if (focusable && focusElement(focusable)) {
      return;
    }

    focusElement(overlay);
  };

  var resolveOverlay = function resolveOverlay(selector) {
    if (!selector) {
      return null;
    }

    var trimmed = selector.trim();
    if (!trimmed) {
      return null;
    }

    if (trimmed.startsWith('#')) {
      return document.querySelector(trimmed);
    }

    return document.getElementById(trimmed);
  };

  var lockBodyScroll = function lockBodyScroll() {
    if (!document.body) {
      return;
    }

    if (overlayStack.length) {
      document.body.classList.add('overflow-hidden');
    } else {
      document.body.classList.remove('overflow-hidden');
    }
  };

  var attachBackdropHandler = function attachBackdropHandler(overlay) {
    if (!overlay || overlay._overlayBackdropHandler) {
      return;
    }

    var handler = function handler(event) {
      if (event.target !== overlay) {
        return;
      }

      if (overlay.dataset.hsOverlayStatic === 'true') {
        return;
      }

      event.preventDefault();
      hideOverlay(overlay);
    };

    overlay._overlayBackdropHandler = handler;
    overlay.addEventListener('mousedown', handler);
    overlay.addEventListener('touchstart', handler);
  };

  var detachBackdropHandler = function detachBackdropHandler(overlay) {
    if (!overlay || !overlay._overlayBackdropHandler) {
      return;
    }

    overlay.removeEventListener('mousedown', overlay._overlayBackdropHandler);
    overlay.removeEventListener('touchstart', overlay._overlayBackdropHandler);
    overlay._overlayBackdropHandler = null;
  };

  var showOverlay = function showOverlay(overlay) {
    if (!overlay || overlay.classList.contains(ACTIVE_CLASS)) {
      return;
    }

    overlay.removeAttribute('inert');
    overlay.setAttribute('aria-hidden', 'false');
    overlay.classList.remove(HIDDEN_CLASS);
    overlay.classList.remove(DISABLED_CLASS);

    requestAnimationFrame(function () {
      overlay.classList.remove(FADE_CLASS);
      overlay.classList.add(ACTIVE_CLASS);
      overlay.classList.add(OPEN_CLASS);
    });

    attachBackdropHandler(overlay);
    overlayStack.push(overlay);
    lockBodyScroll();
  };

  var hideOverlay = function hideOverlay(overlay, options) {
    if (options === void 0) {
      options = {};
    }

    if (!overlay || !overlay.classList.contains(ACTIVE_CLASS)) {
      return;
    }

    if (options.skipStatic && overlay.dataset.hsOverlayStatic === 'true') {
      return;
    }

    if (options.restoreFocus !== false) {
      var restored = restoreFocus(overlay);
      if (!restored && overlay.contains(document.activeElement)) {
        focusBodyFallback();
      }
    }

    if (overlay.contains(document.activeElement)) {
      focusBodyFallback();
    }

    overlay.setAttribute('inert', '');
    overlay.setAttribute('aria-hidden', 'true');
    overlay.classList.add(FADE_CLASS);
    overlay.classList.add(DISABLED_CLASS);
    overlay.classList.remove(ACTIVE_CLASS);
    overlay.classList.remove(OPEN_CLASS);

    var finalize = function finalize(event) {
      if (event && event.target !== overlay) {
        return;
      }

      overlay.classList.add(HIDDEN_CLASS);
      overlay.removeEventListener('transitionend', finalize);
    };

    overlay.addEventListener('transitionend', finalize);
    setTimeout(finalize, 400);

    detachBackdropHandler(overlay);

    var index = overlayStack.lastIndexOf(overlay);
    if (index > -1) {
      overlayStack.splice(index, 1);
    }

    lockBodyScroll();

    overlay._overlayPreviousActive = null;
    overlay._overlayReturnTarget = null;
  };

  var handleTrigger = function handleTrigger(trigger) {
    var selector = trigger.getAttribute('data-hs-overlay');
    var overlay = resolveOverlay(selector);

    if (!overlay) {
      if (typeof console !== 'undefined') {
        console.warn('[ui-overlay] Target not found for selector', selector);
      }
      return;
    }

    if (!overlay.classList.contains('hs-overlay')) {
      return;
    }

    var currentOverlay = trigger.closest('.hs-overlay');
    var isSameOverlay = currentOverlay && currentOverlay === overlay;
    var overlayIsActive = overlay.classList.contains(ACTIVE_CLASS);
    var parentOverlay = !isSameOverlay && currentOverlay ? currentOverlay : null;
    var previouslyFocused = document.activeElement;
    var fallbackTarget = trigger;

    if (parentOverlay && parentOverlay._overlayReturnTarget) {
      fallbackTarget = parentOverlay._overlayReturnTarget;
    } else if (parentOverlay && parentOverlay._overlayPreviousActive) {
      fallbackTarget = parentOverlay._overlayPreviousActive;
    }

    if (isSameOverlay && overlayIsActive) {
      hideOverlay(overlay);
      trigger.setAttribute('aria-expanded', 'false');
      return;
    }

    if (parentOverlay && parentOverlay.classList.contains(ACTIVE_CLASS)) {
      hideOverlay(parentOverlay);
    }

    if (overlayIsActive) {
      hideOverlay(overlay);
      trigger.setAttribute('aria-expanded', 'false');
      return;
    }

    overlay._overlayReturnTarget = fallbackTarget;
    overlay._overlayPreviousActive = isValidFocusTarget(previouslyFocused, overlay) ? previouslyFocused : fallbackTarget;

    showOverlay(overlay);
    trigger.setAttribute('aria-expanded', 'true');
    requestAnimationFrame(function () {
      focusOverlay(overlay);
    });
  };

  document.addEventListener('click', function (event) {
    var trigger = closestFromEventTarget(event.target, OVERLAY_TRIGGER_SELECTOR);
    if (!trigger) {
      return;
    }

    event.preventDefault();
    handleTrigger(trigger);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') {
      return;
    }

    for (var i = overlayStack.length - 1; i >= 0; i -= 1) {
      var overlay = overlayStack[i];
      hideOverlay(overlay, { skipStatic: true });
      if (!overlay.dataset.hsOverlayStatic) {
        break;
      }
    }
  });
})();


(function () {
  'use strict';

  var TABLIST_SELECTOR = '[role="tablist"]';
  var TAB_SELECTOR = '[role="tab"][data-hs-tab]';

  function toArray(nodeList) {
    return Array.prototype.slice.call(nodeList || []);
  }

  function resolvePanel(tab) {
    var selector = tab.getAttribute('data-hs-tab');
    if (!selector) {
      return null;
    }

    try {
      return document.querySelector(selector);
    } catch (error) {
      return null;
    }
  }

  function isDisabled(tab) {
    return tab.disabled || tab.getAttribute('aria-disabled') === 'true';
  }

  function activateTab(state, nextTab, moveFocus) {
    state.entries.forEach(function (entry) {
      var active = entry.tab === nextTab;

      if (!entry.disabled) {
        entry.tab.setAttribute('aria-selected', active ? 'true' : 'false');
        entry.tab.classList.toggle('active', active);
        entry.tab.setAttribute('tabindex', active ? '0' : '-1');
      } else {
        entry.tab.setAttribute('aria-selected', 'false');
        entry.tab.setAttribute('tabindex', '-1');
      }

      if (entry.panel) {
        entry.panel.classList.toggle('hidden', !active);
        entry.panel.setAttribute('aria-hidden', active ? 'false' : 'true');
      }
    });

    state.activeTab = nextTab;

    if (moveFocus && nextTab && typeof nextTab.focus === 'function') {
      nextTab.focus();
    }
  }

  function moveTabFocus(state, currentTab, delta) {
    var tabs = state.enabledTabs;
    var currentIndex = tabs.indexOf(currentTab);
    if (currentIndex === -1) {
      return;
    }

    var nextIndex = (currentIndex + delta + tabs.length) % tabs.length;
    activateTab(state, tabs[nextIndex], true);
  }

  function bindTab(state, tab) {
    if (tab._uiTabBound) {
      return;
    }

    tab._uiTabBound = true;

    tab.addEventListener('click', function (event) {
      event.preventDefault();
      activateTab(state, tab, false);
    });

    tab.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
        event.preventDefault();
        moveTabFocus(state, tab, 1);
        return;
      }

      if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
        event.preventDefault();
        moveTabFocus(state, tab, -1);
        return;
      }

      if (event.key === 'Home') {
        event.preventDefault();
        activateTab(state, state.enabledTabs[0], true);
        return;
      }

      if (event.key === 'End') {
        event.preventDefault();
        activateTab(state, state.enabledTabs[state.enabledTabs.length - 1], true);
      }
    });
  }

  function initTablist(tablist) {
    var tabs = toArray(tablist.querySelectorAll(TAB_SELECTOR));
    if (!tabs.length) {
      return;
    }

    var entries = tabs.map(function (tab) {
      return {
        tab: tab,
        panel: resolvePanel(tab),
        disabled: isDisabled(tab)
      };
    }).filter(function (entry) {
      return !!entry.panel;
    });

    if (!entries.length) {
      return;
    }

    var enabledEntries = entries.filter(function (entry) {
      return !entry.disabled;
    });

    if (!enabledEntries.length) {
      return;
    }

    var state = {
      entries: entries,
      enabledTabs: enabledEntries.map(function (entry) { return entry.tab; }),
      activeTab: null
    };

    state.enabledTabs.forEach(function (tab) {
      bindTab(state, tab);
    });

    var selectedEntry = enabledEntries.find(function (entry) {
      return entry.tab.getAttribute('aria-selected') === 'true';
    });

    activateTab(state, selectedEntry ? selectedEntry.tab : state.enabledTabs[0], false);
  }

  function init() {
    toArray(document.querySelectorAll(TABLIST_SELECTOR)).forEach(initTablist);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
    return;
  }

  init();
})();
