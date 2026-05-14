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
