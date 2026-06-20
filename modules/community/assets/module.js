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

  function communityIcon(name) {
    var icon = document.createElement('span');
    icon.className = 'material-symbols-outlined';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = name;
    return icon;
  }

  function communityLayerButton(iconName, label, action) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-icon btn-ghost-light community-image-layer-button';
    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);
    button.setAttribute('data-community-image-layer-action', action);
    button.appendChild(communityIcon(iconName));
    return button;
  }

  function initImageLayer() {
    var triggers = Array.prototype.slice.call(document.querySelectorAll('[data-community-image-layer-trigger]'));
    if (triggers.length < 1) {
      return;
    }

    var layer = document.createElement('div');
    layer.className = 'community-image-layer';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    layer.setAttribute('aria-label', '첨부 이미지 보기');
    layer.hidden = true;

    var toolbar = document.createElement('div');
    toolbar.className = 'community-image-layer-toolbar';
    toolbar.appendChild(communityLayerButton('zoom_out', '축소', 'zoom-out'));
    toolbar.appendChild(communityLayerButton('zoom_in', '확대', 'zoom-in'));
    toolbar.appendChild(communityLayerButton('image', '원본 크기', 'actual-size'));

    var closeButton = communityLayerButton('close', '닫기', 'close');
    closeButton.classList.add('community-image-layer-close');

    var viewport = document.createElement('div');
    viewport.className = 'community-image-layer-viewport';

    var image = document.createElement('img');
    image.className = 'community-image-layer-image';
    image.alt = '';
    viewport.appendChild(image);

    layer.appendChild(toolbar);
    layer.appendChild(closeButton);
    layer.appendChild(viewport);
    document.body.appendChild(layer);

    var activeTrigger = null;
    var scale = 1;
    var naturalWidth = 0;
    var naturalHeight = 0;

    function applyScale() {
      if (naturalWidth < 1 || naturalHeight < 1) {
        return;
      }
      image.style.width = Math.max(1, Math.round(naturalWidth * scale)) + 'px';
      image.style.height = Math.max(1, Math.round(naturalHeight * scale)) + 'px';
    }

    function fitScale() {
      if (naturalWidth < 1 || naturalHeight < 1) {
        return 1;
      }
      var rect = viewport.getBoundingClientRect();
      var widthRatio = Math.max(1, rect.width - 32) / naturalWidth;
      var heightRatio = Math.max(1, rect.height - 32) / naturalHeight;
      return Math.min(1, widthRatio, heightRatio);
    }

    function openLayer(trigger) {
      var url = trigger.getAttribute('href') || '';
      if (url === '') {
        return;
      }

      activeTrigger = trigger;
      naturalWidth = 0;
      naturalHeight = 0;
      scale = 1;
      image.removeAttribute('style');
      image.alt = trigger.querySelector('img') ? (trigger.querySelector('img').getAttribute('alt') || '') : '';
      image.src = url;
      layer.hidden = false;
      document.documentElement.classList.add('community-image-layer-open');
      closeButton.focus({ preventScroll: true });
    }

    function closeLayer() {
      layer.hidden = true;
      image.removeAttribute('src');
      image.removeAttribute('style');
      document.documentElement.classList.remove('community-image-layer-open');
      if (activeTrigger && typeof activeTrigger.focus === 'function') {
        activeTrigger.focus({ preventScroll: true });
      }
      activeTrigger = null;
    }

    image.addEventListener('load', function () {
      naturalWidth = image.naturalWidth || 0;
      naturalHeight = image.naturalHeight || 0;
      scale = fitScale();
      applyScale();
    });

    triggers.forEach(function (trigger) {
      trigger.addEventListener('click', function (event) {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
          return;
        }
        event.preventDefault();
        openLayer(trigger);
      });
    });

    layer.addEventListener('click', function (event) {
      if (event.target === layer) {
        closeLayer();
        return;
      }
      var button = event.target.closest ? event.target.closest('[data-community-image-layer-action]') : null;
      if (!button) {
        return;
      }
      var action = button.getAttribute('data-community-image-layer-action');
      if (action === 'close') {
        closeLayer();
      } else if (action === 'zoom-in') {
        scale = Math.min(6, scale * 1.25);
        applyScale();
      } else if (action === 'zoom-out') {
        scale = Math.max(0.05, scale / 1.25);
        applyScale();
      } else if (action === 'actual-size') {
        scale = 1;
        applyScale();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (layer.hidden) {
        return;
      }
      if (event.key === 'Escape') {
        event.preventDefault();
        closeLayer();
      } else if ((event.key === '+' || event.key === '=') && !event.ctrlKey && !event.metaKey) {
        event.preventDefault();
        scale = Math.min(6, scale * 1.25);
        applyScale();
      } else if (event.key === '-' && !event.ctrlKey && !event.metaKey) {
        event.preventDefault();
        scale = Math.max(0.05, scale / 1.25);
        applyScale();
      } else if (event.key === '0' && !event.ctrlKey && !event.metaKey) {
        event.preventDefault();
        scale = 1;
        applyScale();
      }
    });
  }

  function init() {
    initHomeSidebarMenu();
    initImageLayer();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
    return;
  }

  init();
})();
