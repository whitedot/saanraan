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
