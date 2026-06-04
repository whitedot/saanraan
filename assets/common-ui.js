/*
 * Saanraan common UI interactions.
 * Shared by project screens and runtime UI-KIT preview pages.
 * Owns dropdown, overlay/modal, and tablist behavior without preview shell dependencies.
 */

(function () {
  'use strict';

  var DROPDOWN_SELECTOR = '.dropdown';
  var TOGGLE_SELECTOR = '.dropdown-toggle';
  var MENU_SELECTOR = '.dropdown-menu';
  var OPEN_CLASS = 'dropdown-open';
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

    return fallback;
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

  var PICKER_SELECTOR = '[data-link-card-picker]';

  function getElementTarget(target) {
    if (!target) {
      return null;
    }

    if (target.nodeType === 1) {
      return target;
    }

    return target.parentElement || null;
  }

  function insertIntoTextarea(textarea, text) {
    var start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : textarea.value.length;
    var end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : start;
    var before = textarea.value.slice(0, start);
    var after = textarea.value.slice(end);
    var prefix = before !== '' && !/\s$/.test(before) ? '\n' : '';
    var suffix = after !== '' && !/^\s/.test(after) ? '\n' : '';
    var inserted = prefix + text + suffix;

    textarea.value = before + inserted + after;
    textarea.focus();
    textarea.selectionStart = start + inserted.length;
    textarea.selectionEnd = start + inserted.length;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function insertHtmlIntoEditor(editor, html) {
    if (!editor || !editor.model || typeof editor.model.change !== 'function' || !editor.data || !editor.data.processor || typeof editor.data.toModel !== 'function') {
      return false;
    }

    try {
      editor.model.change(function () {
        var viewFragment = editor.data.processor.toView(html);
        var modelFragment = editor.data.toModel(viewFragment);
        editor.model.insertContent(modelFragment);
      });
      return true;
    } catch (error) {
      return false;
    }
  }

  window.srInsertEditorContent = function srInsertEditorContent(textareaId, text, html) {
    var textarea = document.getElementById(textareaId);
    var editor = window.srCkeditorInstances && textareaId ? window.srCkeditorInstances[textareaId] : null;

    if (html && insertHtmlIntoEditor(editor, html)) {
      if (textarea) {
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
      }
      return true;
    }

    if (!textarea) {
      return false;
    }

    insertIntoTextarea(textarea, text);
    return true;
  };

  window.srInsertEditorText = function srInsertEditorText(textareaId, text) {
    return window.srInsertEditorContent(textareaId, text, '');
  };

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (character) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      }[character];
    });
  }

  function safeUrl(value) {
    var url = String(value || '').trim();
    if (/^(\/(?!\/)|https?:\/\/)/i.test(url)) {
      return url;
    }

    return '';
  }

  function buildInsertContent(item) {
    var title = String(item.title || '(제목 없음)').trim();
    var summary = String(item.summary || '').trim();
    var meta = String(item.meta || '').trim();
    var url = safeUrl(item.url || '');
    var textParts = [title];

    if (url !== '') {
      textParts.push(url);
    }
    if (summary !== '') {
      textParts.push(summary);
    } else if (meta !== '') {
      textParts.push(meta);
    }

    var titleHtml = url !== ''
      ? '<a href="' + escapeHtml(url) + '">' + escapeHtml(title) + '</a>'
      : escapeHtml(title);
    var html = '<blockquote><p><strong>' + titleHtml + '</strong></p>';
    if (summary !== '') {
      html += '<p>' + escapeHtml(summary) + '</p>';
    } else if (meta !== '') {
      html += '<p>' + escapeHtml(meta) + '</p>';
    }
    html += '</blockquote>';

    return {
      text: textParts.join('\n'),
      html: html
    };
  }

  function pickerState(picker) {
    if (!picker._linkCardPickerState) {
      picker._linkCardPickerState = { selected: null };
    }

    return picker._linkCardPickerState;
  }

  function setMessage(picker, message) {
    var results = picker.querySelector('[data-link-card-results]');
    if (results) {
      results.textContent = message;
    }
  }

  function renderItems(picker, items) {
    var results = picker.querySelector('[data-link-card-results]');
    var state = pickerState(picker);
    state.selected = null;
    if (!results) {
      return;
    }

    results.innerHTML = '';
    if (!Array.isArray(items) || items.length === 0) {
      results.textContent = picker.dataset.emptyLabel || '검색 결과가 없습니다.';
      return;
    }

    items.forEach(function (item) {
      var button = document.createElement('button');
      var title = document.createElement('strong');
      var meta = document.createElement('span');
      var summary = document.createElement('small');

      button.type = 'button';
      button.className = 'sr-link-card-picker-result';
      button.setAttribute('data-link-card-select', '1');
      button._linkCardItem = item;
      title.textContent = item.title || '(제목 없음)';
      meta.textContent = item.meta || item.url || '';
      summary.textContent = item.summary || '';
      button.appendChild(title);
      button.appendChild(meta);
      if (summary.textContent !== '') {
        button.appendChild(summary);
      }
      results.appendChild(button);
    });
  }

  function searchPicker(picker) {
    var endpoint = picker.dataset.endpoint || '';
    var queryInput = picker.querySelector('[data-link-card-search]');
    var query = queryInput ? queryInput.value : '';
    var target = picker.dataset.target || '';
    if (endpoint === '') {
      return;
    }

    setMessage(picker, picker.dataset.loadingLabel || '검색 중입니다.');

    var url = new URL(endpoint, window.location.href);
    url.searchParams.set('q', query);
    if (target !== '') {
      url.searchParams.set('target', target);
    }

    fetch(url.toString(), {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('Search request failed.');
      }
      return response.json();
    }).then(function (data) {
      renderItems(picker, data && Array.isArray(data.items) ? data.items : []);
    }).catch(function () {
      setMessage(picker, picker.dataset.errorLabel || '검색 결과를 불러오지 못했습니다.');
    });
  }

  function selectResult(picker, button) {
    var state = pickerState(picker);
    Array.prototype.slice.call(picker.querySelectorAll('[data-link-card-select]')).forEach(function (item) {
      item.classList.remove('is-selected');
      item.setAttribute('aria-pressed', 'false');
    });
    button.classList.add('is-selected');
    button.setAttribute('aria-pressed', 'true');
    state.selected = button._linkCardItem || null;
  }

  function insertSelected(picker) {
    var state = pickerState(picker);
    var item = state.selected;
    var textareaId = picker.dataset.textarea || '';
    if (!item) {
      setMessage(picker, picker.dataset.selectLabel || '삽입할 대상을 먼저 선택해 주세요.');
      return;
    }

    var content = buildInsertContent(item);
    window.srInsertEditorContent(textareaId, content.text, content.html);
  }

  document.addEventListener('click', function (event) {
    var target = getElementTarget(event.target);
    var picker = target && target.closest ? target.closest(PICKER_SELECTOR) : null;
    if (!picker) {
      return;
    }

    var searchTrigger = target.closest('[data-link-card-search-trigger]');
    if (searchTrigger) {
      event.preventDefault();
      searchPicker(picker);
      return;
    }

    var selectTrigger = target.closest('[data-link-card-select]');
    if (selectTrigger) {
      event.preventDefault();
      selectResult(picker, selectTrigger);
      return;
    }

    var insertTrigger = target.closest('[data-link-card-insert]');
    if (insertTrigger) {
      event.preventDefault();
      insertSelected(picker);
    }
  });

  document.addEventListener('keydown', function (event) {
    var target = getElementTarget(event.target);
    var input = target && target.closest ? target.closest('[data-link-card-search]') : null;
    if (!input || event.key !== 'Enter') {
      return;
    }

    var picker = input.closest(PICKER_SELECTOR);
    if (!picker) {
      return;
    }

    event.preventDefault();
    searchPicker(picker);
  });
})();


(function () {
  'use strict';

  var ACTIVE_CLASS = 'overlay-open';
  var OPEN_CLASS = 'open';
  var HIDDEN_CLASS = 'hidden';
  var DISABLED_CLASS = 'pointer-events-none';
  var FADE_CLASS = 'opacity-0';
  var overlayStack = [];
  var FOCUSABLE_SELECTOR = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';
  var OVERLAY_TRIGGER_SELECTOR = '[data-overlay]';

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

    var hiddenOverlay = element.closest && element.closest('.overlay');
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

    var triggers = document.querySelectorAll('[data-overlay="#' + overlay.id + '"], [data-overlay="' + overlay.id + '"]');
    for (var i = 0; i < triggers.length; i += 1) {
      if (isValidFocusTarget(triggers[i], overlay)) {
        return triggers[i];
      }
    }

    return null;
  };

  var setOverlayTriggersExpanded = function setOverlayTriggersExpanded(overlay, expanded) {
    if (!overlay || !overlay.id) {
      return;
    }

    var triggers = document.querySelectorAll('[data-overlay="#' + overlay.id + '"], [data-overlay="' + overlay.id + '"]');
    for (var i = 0; i < triggers.length; i += 1) {
      if (triggers[i].hasAttribute('aria-expanded')) {
        triggers[i].setAttribute('aria-expanded', expanded ? 'true' : 'false');
      }
    }
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

      if (overlay.dataset.overlayStatic === 'true') {
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
    setOverlayTriggersExpanded(overlay, true);
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

    if (options.skipStatic && overlay.dataset.overlayStatic === 'true') {
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
    setOverlayTriggersExpanded(overlay, false);

    var index = overlayStack.lastIndexOf(overlay);
    if (index > -1) {
      overlayStack.splice(index, 1);
    }

    lockBodyScroll();

    overlay._overlayPreviousActive = null;
    overlay._overlayReturnTarget = null;
  };

  var handleTrigger = function handleTrigger(trigger) {
    var selector = trigger.getAttribute('data-overlay');
    var overlay = resolveOverlay(selector);

    if (!overlay) {
      if (typeof console !== 'undefined') {
        console.warn('[ui-overlay] Target not found for selector', selector);
      }
      return;
    }

    if (!overlay.classList.contains('overlay')) {
      return;
    }

    var currentOverlay = trigger.closest('.overlay');
    var isSameOverlay = currentOverlay && currentOverlay === overlay;
    var overlayIsActive = overlay.classList.contains(ACTIVE_CLASS);
    var parentOverlay = !isSameOverlay && currentOverlay ? currentOverlay : null;
    var shouldStack = trigger.dataset.overlayStack === 'true' || overlay.dataset.overlayStack === 'true';
    var previouslyFocused = document.activeElement;
    var fallbackTarget = trigger;

    if (!shouldStack && parentOverlay && parentOverlay._overlayReturnTarget) {
      fallbackTarget = parentOverlay._overlayReturnTarget;
    } else if (!shouldStack && parentOverlay && parentOverlay._overlayPreviousActive) {
      fallbackTarget = parentOverlay._overlayPreviousActive;
    }

    if (isSameOverlay && overlayIsActive) {
      hideOverlay(overlay);
      return;
    }

    if (parentOverlay && parentOverlay.classList.contains(ACTIVE_CLASS) && !shouldStack) {
      hideOverlay(parentOverlay);
    }

    if (overlayIsActive) {
      hideOverlay(overlay);
      return;
    }

    overlay._overlayReturnTarget = fallbackTarget;
    overlay._overlayPreviousActive = isValidFocusTarget(previouslyFocused, overlay) ? previouslyFocused : fallbackTarget;

    showOverlay(overlay);
    requestAnimationFrame(function () {
      focusOverlay(overlay);
    });
  };

  document.querySelectorAll('.overlay.' + ACTIVE_CLASS + ', .overlay.' + OPEN_CLASS).forEach(function (overlay) {
    overlay.removeAttribute('inert');
    overlay.setAttribute('aria-hidden', 'false');
    overlay.classList.remove(HIDDEN_CLASS);
    overlay.classList.remove(DISABLED_CLASS);
    overlay.classList.remove(FADE_CLASS);
    overlay.classList.add(ACTIVE_CLASS);
    overlay.classList.add(OPEN_CLASS);
    attachBackdropHandler(overlay);
    setOverlayTriggersExpanded(overlay, true);
    if (overlayStack.indexOf(overlay) === -1) {
      overlayStack.push(overlay);
    }
    requestAnimationFrame(function () {
      focusOverlay(overlay);
    });
  });
  lockBodyScroll();

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
      if (!overlay.dataset.overlayStatic) {
        break;
      }
    }
  });
})();


(function () {
  'use strict';

  var TABLIST_SELECTOR = '[role="tablist"]';
  var TAB_SELECTOR = '[role="tab"][data-tab]';

  function toArray(nodeList) {
    return Array.prototype.slice.call(nodeList || []);
  }

  function resolvePanel(tab) {
    var selector = tab.getAttribute('data-tab');
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


(function () {
  'use strict';

  function getElementTarget(target) {
    if (!target) {
      return null;
    }

    return target.nodeType === 1 ? target : target.parentElement || null;
  }

  function resolveTarget(selector) {
    if (!selector) {
      return null;
    }

    var trimmed = String(selector).trim();
    if (!trimmed) {
      return null;
    }

    if (trimmed.charAt(0) === '#') {
      return document.querySelector(trimmed);
    }

    return document.getElementById(trimmed);
  }

  function resolvePasswordConfig(trigger) {
    var raw = trigger.getAttribute('data-toggle-password');
    if (!raw) {
      return null;
    }

    if (raw.charAt(0) !== '{') {
      return { target: raw };
    }

    try {
      return JSON.parse(raw);
    } catch (error) {
      return null;
    }
  }

  function dispatchFormEvent(control, type) {
    if (!control || typeof Event !== 'function') {
      return;
    }

    control.dispatchEvent(new Event(type, { bubbles: true }));
  }

  function resetSelectToDefault(select) {
    var options;
    var defaultIndex;

    if (!select) {
      return;
    }

    options = Array.prototype.slice.call(select.options || []);
    if (select.multiple) {
      options.forEach(function (option) {
        option.selected = option.defaultSelected;
      });
      return;
    }

    defaultIndex = options.findIndex(function (option) {
      return option.defaultSelected;
    });
    select.selectedIndex = defaultIndex >= 0 ? defaultIndex : (options.length > 0 ? 0 : -1);
  }

  function clearFiltering(filtering) {
    if (!filtering) {
      return;
    }

    filtering.querySelectorAll('[data-admin-select-badge-list]').forEach(function (root) {
      root.querySelectorAll('[data-admin-select-badge-item]').forEach(function (item) {
        item.remove();
      });
      root.querySelectorAll('option').forEach(function (option) {
        option.hidden = false;
        option.disabled = false;
      });
    });

    filtering.querySelectorAll('input, select, textarea').forEach(function (control) {
      if (!control || control.disabled || control.type === 'hidden') {
        return;
      }

      if (control.matches('[type="checkbox"], [type="radio"]')) {
        control.checked = control.defaultChecked;
        dispatchFormEvent(control, 'change');
        return;
      }

      if (control.tagName === 'SELECT') {
        resetSelectToDefault(control);
        dispatchFormEvent(control, 'change');
        return;
      }

      control.value = control.defaultValue;
      dispatchFormEvent(control, 'input');
      dispatchFormEvent(control, 'change');
    });
  }

  function filteringControlIsActive(control) {
    if (!control || control.disabled || control.type === 'hidden') {
      return false;
    }
    if (control.matches('[type="checkbox"], [type="radio"]')) {
      return control.checked && !control.matches('[data-filtering-toggle-all]') && control.value !== '' && control.value !== '0';
    }
    if (control.tagName === 'SELECT') {
      return control.value !== '' && control.value !== '0';
    }
    return String(control.value || '').trim() !== '';
  }

  function filteringFieldHasSelection(field) {
    return !!(field && field.querySelector('select, [data-filtering-toggle-group], .admin-check-list input[type="checkbox"]'));
  }

  function filteringFieldIsPrimary(field) {
    return !!(field && (
      field.querySelector('select[name="field"]') ||
      field.querySelector('input[name="q"], textarea[name="q"]')
    ));
  }

  function filteringFieldIsActive(field) {
    if (!field) {
      return false;
    }
    return Array.prototype.some.call(field.querySelectorAll('input, select, textarea'), filteringControlIsActive);
  }

  function enhancePlainFiltering() {
    var autoIndex = 0;

    document.querySelectorAll('form.filtering-form.filtering-plain:not([data-filtering-enhanced])').forEach(function (form) {
      var grid = form.querySelector(':scope > .filtering-fields');
      if (!grid) {
        return;
      }

      var children = Array.prototype.slice.call(grid.children);
      var selectableCount = children.filter(filteringFieldHasSelection).length;
      if (selectableCount < 3) {
        form.setAttribute('data-filtering-enhanced', 'skipped');
        return;
      }

      var submit = children.find(function (child) {
        return child.matches && child.matches('button[type="submit"], input[type="submit"]');
      });
      var fields = children.filter(function (child) {
        return child !== submit;
      });
      var primaryFields = fields.filter(filteringFieldIsPrimary);
      var detailFields = fields.filter(function (field) {
        return primaryFields.indexOf(field) === -1;
      });

      if (primaryFields.length === 0 || detailFields.length === 0) {
        form.setAttribute('data-filtering-enhanced', 'skipped');
        return;
      }

      var detailOpen = detailFields.some(filteringFieldIsActive);
      var detailId = (form.id || 'admin_auto_filtering') + '_detail_' + String(autoIndex);
      autoIndex += 1;

      var card = document.createElement('div');
      card.className = 'filtering filtering-card' + (detailOpen ? ' filtering-open' : '');
      card.setAttribute('data-filtering', '');

      var fieldWrap = document.createElement('div');
      fieldWrap.className = 'filtering-fields';
      primaryFields.forEach(function (field) {
        field.classList.add('filtering-field');
        if (field.querySelector('input[name="q"], textarea[name="q"]')) {
          field.classList.add('filtering-field-fill');
        }
        fieldWrap.appendChild(field);
      });

      var detailWrap = document.createElement('div');
      detailWrap.id = detailId;
      detailWrap.className = 'filtering-body';
      detailWrap.setAttribute('data-filtering-body', '');
      detailWrap.hidden = !detailOpen;
      detailFields.forEach(function (field) {
        field.classList.add('filtering-field');
        detailWrap.appendChild(field);
      });

      var actions = document.createElement('div');
      actions.className = 'filtering-actions';

      var toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'btn btn-solid-light filtering-toggle';
      toggle.setAttribute('data-filtering-toggle', '');
      toggle.setAttribute('aria-expanded', detailOpen ? 'true' : 'false');
      toggle.setAttribute('aria-controls', detailId);
      toggle.textContent = '상세검색';
      actions.appendChild(toggle);

      var reset = document.createElement('button');
      reset.type = 'button';
      reset.className = 'btn btn-outline-light';
      reset.setAttribute('data-filtering-reset', '');
      reset.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">restart_alt</span>초기화';
      actions.appendChild(reset);

      if (submit) {
        actions.appendChild(submit);
      }

      card.appendChild(fieldWrap);
      card.appendChild(detailWrap);
      card.appendChild(actions);
      grid.appendChild(card);
      form.classList.remove('filtering', 'filtering-plain');
      form.setAttribute('data-filtering-enhanced', 'card');
    });
  }

  function optionText(option) {
    return String(option ? option.textContent || '' : '').replace(/\s+/g, ' ').trim();
  }

  function detailSelectShouldBecomeRadio(select) {
    if (!select || select.multiple || !select.name || select.disabled || select.dataset.adminDetailRadioToggleSource === '1') {
      return false;
    }
    if (!select.closest('[data-filtering-body]')) {
      return false;
    }
    return select.options && select.options.length > 0;
  }

  function enhanceDetailSelects() {
    var index = 0;

    document.querySelectorAll('[data-filtering-body] select:not([data-admin-detail-radio-toggle-source])').forEach(function (select) {
      if (!detailSelectShouldBecomeRadio(select)) {
        return;
      }

      var group = document.createElement('div');
      var selectId = select.id || ('admin_detail_radio_select_' + String(index));
      var options = Array.prototype.slice.call(select.options).filter(function (option) {
        return !option.disabled;
      });

      if (options.length === 0) {
        return;
      }

      group.className = 'filtering-toggle-group filtering-radio-toggle-group';
      group.setAttribute('data-filtering-radio-toggle-group', '');
      group.setAttribute('data-filtering-radio-source', '#' + selectId);

      options.forEach(function (option, optionIndex) {
        var id = selectId + '_radio_' + String(index) + '_' + String(optionIndex);
        var item = document.createElement('span');
        var input = document.createElement('input');
        var label = document.createElement('label');
        var groupClass = optionIndex === 0 ? 'btn-group-start' : (optionIndex === options.length - 1 ? 'btn-group-end' : 'btn-group-middle');

        item.className = 'filtering-toggle-item';
        input.id = id;
        input.type = 'radio';
        input.name = select.name;
        input.value = option.value;
        input.className = 'form-choice-toggle-input sr-only';
        input.setAttribute('data-filtering-radio-toggle-choice', '');
        if (option.selected || select.value === option.value) {
          input.checked = true;
        }
        input.defaultChecked = input.checked;

        label.setAttribute('for', id);
        label.className = 'btn btn-choice-light ' + groupClass;
        label.textContent = optionText(option);

        item.appendChild(input);
        item.appendChild(label);
        group.appendChild(item);
      });

      select.id = selectId;
      select.dataset.adminDetailRadioToggleSource = '1';
      select.disabled = true;
      select.hidden = true;
      select.classList.add('admin-detail-radio-toggle-source');
      select.parentNode.insertBefore(group, select.nextSibling);
      index += 1;
    });
  }

  function syncFilterCheckboxToggleGroup(group, sourceControl) {
    if (!group) {
      return;
    }

    var allControl = group.querySelector('[data-filtering-toggle-all]');
    var choices = Array.prototype.slice.call(group.querySelectorAll('[data-filtering-toggle-choice]'));

    if (!allControl || choices.length === 0) {
      return;
    }

    if (sourceControl === allControl && allControl.checked) {
      choices.forEach(function (control) {
        if (control.checked) {
          control.checked = false;
          dispatchFormEvent(control, 'change');
        }
      });
      return;
    }

    var checkedChoices = choices.filter(function (control) {
      return control.checked;
    });

    if (checkedChoices.length === 0) {
      if (!allControl.checked) {
        allControl.checked = true;
        dispatchFormEvent(allControl, 'change');
      }
      return;
    }

    if (checkedChoices.length === choices.length) {
      choices.forEach(function (control) {
        control.checked = false;
        dispatchFormEvent(control, 'change');
      });
      if (!allControl.checked) {
        allControl.checked = true;
        dispatchFormEvent(allControl, 'change');
      }
      return;
    }

    if (allControl.checked) {
      allControl.checked = false;
      dispatchFormEvent(allControl, 'change');
    }
  }

  function initFilteringEnhancements() {
    enhancePlainFiltering();
    enhanceDetailSelects();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFilteringEnhancements);
  } else {
    initFilteringEnhancements();
  }

  document.addEventListener('click', function (event) {
    var target = getElementTarget(event.target);
    var filteringToggle = target && target.closest('[data-filtering-toggle]');

    if (filteringToggle) {
      var filtering = filteringToggle.closest('[data-filtering]');
      var filteringBody = filtering ? filtering.querySelector('[data-filtering-body]') : null;

      if (filteringBody) {
        event.preventDefault();

        var expanded = filteringToggle.getAttribute('aria-expanded') === 'true';
        filteringToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        filtering.classList.toggle('filtering-open', !expanded);
        filteringBody.hidden = expanded;
      }
      return;
    }

    var filteringReset = target && target.closest('[data-filtering-reset]');

    if (filteringReset) {
      event.preventDefault();
      clearFiltering(filteringReset.closest('[data-filtering]'));
      return;
    }

    var filterToggleAll = target && target.closest('[data-filtering-toggle-all]');

    if (filterToggleAll) {
      syncFilterCheckboxToggleGroup(filterToggleAll.closest('[data-filtering-toggle-group]'), filterToggleAll);
      return;
    }

    var filterToggleChoice = target && target.closest('[data-filtering-toggle-choice]');

    if (filterToggleChoice) {
      syncFilterCheckboxToggleGroup(filterToggleChoice.closest('[data-filtering-toggle-group]'), filterToggleChoice);
      return;
    }

    var removeTrigger = target && target.closest('[data-remove-element]');

    if (removeTrigger) {
      var removable = resolveTarget(removeTrigger.getAttribute('data-remove-element'));
      if (removable) {
        event.preventDefault();
        removable.classList.add('removing');
        setTimeout(function () {
          removable.remove();
        }, 300);
      }
      return;
    }

    var passwordTrigger = target && target.closest('[data-toggle-password]');
    if (!passwordTrigger) {
      return;
    }

    var config = resolvePasswordConfig(passwordTrigger);
    var input = config ? resolveTarget(config.target) : null;
    if (!input) {
      return;
    }

    event.preventDefault();

    var visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    passwordTrigger.classList.toggle('password-active', visible);
    passwordTrigger.setAttribute('aria-pressed', visible ? 'true' : 'false');

    if (passwordTrigger.dataset) {
      var label = visible
        ? passwordTrigger.dataset.togglePasswordHideLabel
        : passwordTrigger.dataset.togglePasswordShowLabel;
      if (label) {
        passwordTrigger.setAttribute('aria-label', label);
      }
    }
  });
})();
