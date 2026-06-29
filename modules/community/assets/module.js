(function () {
  'use strict';

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

  function initToasts() {
    Array.prototype.slice.call(document.querySelectorAll('[data-community-toast-stack]')).forEach(function (toastStack) {
      function closeToast(toast) {
        if (!toast) {
          return;
        }
        toast.classList.add('removing');
        window.setTimeout(function () {
          toast.remove();
          if (toastStack.children.length === 0) {
            toastStack.remove();
          }
        }, 300);
      }

      toastStack.addEventListener('click', function (event) {
        var closeButton = event.target && event.target.closest ? event.target.closest('[data-community-toast-close]') : null;
        if (closeButton) {
          closeToast(closeButton.closest('[data-community-toast]'));
        }
      });

      Array.prototype.slice.call(toastStack.querySelectorAll('[data-community-toast]')).forEach(function (toast) {
        window.setTimeout(function () {
          closeToast(toast);
        }, 6500);
      });
    });
  }

  function initCopyUrlButtons() {
    Array.prototype.slice.call(document.querySelectorAll('[data-community-copy-url]')).forEach(function (button) {
      if (button.getAttribute('data-community-copy-ready') === '1') {
        return;
      }

      var resetTimer = 0;
      var defaultLabel = button.getAttribute('data-community-copy-default-label') || button.textContent || 'URL 복사';
      var successLabel = button.getAttribute('data-community-copy-success-label') || '복사됨';
      var errorLabel = button.getAttribute('data-community-copy-error-label') || '복사 실패';

      function setButtonLabel(label) {
        button.textContent = label;
      }

      function resetButtonLabel() {
        window.clearTimeout(resetTimer);
        resetTimer = window.setTimeout(function () {
          setButtonLabel(defaultLabel);
          button.removeAttribute('aria-live');
        }, 1800);
      }

      function fallbackCopy(text) {
        var input = document.createElement('textarea');
        input.value = text;
        input.setAttribute('readonly', 'readonly');
        input.style.position = 'fixed';
        input.style.left = '-9999px';
        input.style.top = '0';
        document.body.appendChild(input);
        input.select();

        try {
          return document.execCommand('copy');
        } finally {
          input.remove();
        }
      }

      button.addEventListener('click', function () {
        var rawUrl = button.getAttribute('data-community-copy-url') || '';
        if (rawUrl === '') {
          return;
        }

        var url = rawUrl;
        try {
          url = new URL(rawUrl, window.location.href).toString();
        } catch (error) {
          url = rawUrl;
        }

        var copyPromise = navigator.clipboard && window.isSecureContext
          ? navigator.clipboard.writeText(url).then(function () {
            return true;
          })
          : Promise.resolve(fallbackCopy(url));

        copyPromise.then(function (copied) {
          button.setAttribute('aria-live', 'polite');
          setButtonLabel(copied ? successLabel : errorLabel);
          resetButtonLabel();
        }).catch(function () {
          button.setAttribute('aria-live', 'polite');
          setButtonLabel(errorLabel);
          resetButtonLabel();
        });
      });

      button.setAttribute('data-community-copy-ready', '1');
    });
  }

  function initScrollTargetButtons() {
    Array.prototype.slice.call(document.querySelectorAll('[data-community-scroll-target]')).forEach(function (button) {
      if (button.getAttribute('data-community-scroll-ready') === '1') {
        return;
      }

      button.addEventListener('click', function () {
        var selector = button.getAttribute('data-community-scroll-target') || '';
        var target = selector !== '' ? document.querySelector(selector) : null;
        if (!target) {
          return;
        }

        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      });

      button.setAttribute('data-community-scroll-ready', '1');
    });
  }

  function closeSubmittedAssetConfirmationModal(form) {
    if (!form || form.getAttribute('data-community-asset-confirmation-submitted') === '1') {
      return;
    }

    form.setAttribute('data-community-asset-confirmation-submitted', '1');
    Array.prototype.slice.call(form.querySelectorAll('button[type="submit"], input[type="submit"]')).forEach(function (submitButton) {
      submitButton.disabled = true;
    });

    var overlay = form.closest ? form.closest('.community-asset-confirmation-modal.overlay') : null;
    if (!overlay) {
      return;
    }

    if (overlay.contains(document.activeElement) && typeof document.activeElement.blur === 'function') {
      document.activeElement.blur();
    }

    overlay.setAttribute('aria-hidden', 'true');
    overlay.setAttribute('inert', '');
    overlay.classList.remove('overlay-open');
    overlay.classList.remove('open');
    overlay.classList.add('hidden');
    overlay.classList.add('pointer-events-none');
    overlay.classList.add('opacity-0');

    if (overlay.id) {
      Array.prototype.slice.call(document.querySelectorAll('[data-overlay="#' + overlay.id + '"], [data-overlay="' + overlay.id + '"]')).forEach(function (trigger) {
        if (trigger.hasAttribute('aria-expanded')) {
          trigger.setAttribute('aria-expanded', 'false');
        }
      });
    }

    if (!document.querySelector('.overlay.overlay-open, .overlay.open')) {
      document.body.classList.remove('overflow-hidden');
    }
  }

  function initAssetConfirmationSubmitClose() {
    document.addEventListener('submit', function (event) {
      var form = event.target && event.target.closest ? event.target.closest('[data-community-asset-confirmation-close-on-submit]') : null;
      if (!form) {
        return;
      }

      closeSubmittedAssetConfirmationModal(form);
    });
  }

  function init() {
    initImageLayer();
    initToasts();
    initCopyUrlButtons();
    initScrollTargetButtons();
    initAssetConfirmationSubmitClose();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
    return;
  }

  init();
})();
