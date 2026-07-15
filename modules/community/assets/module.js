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
    var bodyImages = Array.prototype.slice.call(document.querySelectorAll('[data-community-image-layer-body][data-sr-original-src]'));
    if (triggers.length < 1 && bodyImages.length < 1) {
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

    function openLayer(source, url) {
      if (url === '') {
        return;
      }

      activeTrigger = source;
      naturalWidth = 0;
      naturalHeight = 0;
      scale = 1;
      image.removeAttribute('style');
      var sourceImage = source.querySelector ? source.querySelector('img') : null;
      image.alt = sourceImage ? (sourceImage.getAttribute('alt') || '') : (source.getAttribute('alt') || '');
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
        openLayer(trigger, trigger.getAttribute('href') || '');
      });
    });

    bodyImages.forEach(function (bodyImage) {
      bodyImage.addEventListener('click', function (event) {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
          return;
        }
        event.preventDefault();
        openLayer(bodyImage, bodyImage.getAttribute('data-sr-original-src') || '');
      });
      bodyImage.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
          return;
        }
        event.preventDefault();
        openLayer(bodyImage, bodyImage.getAttribute('data-sr-original-src') || '');
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

  function communityDraftJson(element) {
    if (!element) {
      return {};
    }
    try {
      return JSON.parse(element.textContent || '{}') || {};
    } catch (error) {
      return {};
    }
  }

  function communityDraftStorageKey(config) {
    return [
      'sr.community.draft',
      String(config.account_id || 0),
      config.mode || 'create',
      config.board_key || '',
      String(config.post_id || 0)
    ].join(':');
  }

  function communityDraftStorageGet(key) {
    try {
      return window.sessionStorage.getItem(key);
    } catch (error) {
      return null;
    }
  }

  function communityDraftStorageSet(key, value) {
    try {
      window.sessionStorage.setItem(key, value);
    } catch (error) {
      return false;
    }
    return true;
  }

  function communityDraftStorageRemove(key) {
    try {
      window.sessionStorage.removeItem(key);
    } catch (error) {
      return false;
    }
    return true;
  }

  function communityDraftTextHasContent(value) {
    var text = String(value || '');
    if (/<(?:img|video|audio|iframe|object|embed)\b/i.test(text)) {
      return true;
    }
    return text.replace(/<[^>]*>/g, '').replace(/&nbsp;/gi, ' ').trim() !== '';
  }

  function communityDraftObjectHasValue(object) {
    if (!object || typeof object !== 'object') {
      return false;
    }
    return Object.keys(object).some(function (key) {
      var value = object[key];
      if (Array.isArray(value)) {
        return value.some(function (item) {
          return communityDraftTextHasContent(item);
        });
      }
      if (value && typeof value === 'object') {
        return communityDraftObjectHasValue(value);
      }
      return communityDraftTextHasContent(value);
    });
  }

  function communityDraftPayloadHasContent(payload) {
    if (!payload || typeof payload !== 'object') {
      return false;
    }
    if (communityDraftTextHasContent(payload.title) || communityDraftTextHasContent(payload.body_text)) {
      return true;
    }
    if (Number(payload.category_id || 0) > 0 || String(payload.is_secret || '0') === '1' || String(payload.is_notice || '0') === '1') {
      return true;
    }
    if (communityDraftObjectHasValue(payload.extra_field_values || {})) {
      return true;
    }
    var series = payload.series_values || {};
    if (!series || typeof series !== 'object') {
      return false;
    }
    return String(series.series_mode || 'none') !== 'none'
      || Number(series.series_id || 0) > 0
      || communityDraftTextHasContent(series.new_series_title)
      || communityDraftTextHasContent(series.episode_label);
  }

  function communityDraftSnapshotHasContent(snapshot) {
    if (!snapshot || typeof snapshot !== 'object') {
      return false;
    }
    if (communityDraftTextHasContent(snapshot.title) || communityDraftTextHasContent(snapshot.body_text)) {
      return true;
    }
    if (Number(snapshot.category_id || 0) > 0 || String(snapshot.is_secret || '0') === '1' || String(snapshot.is_notice || '0') === '1') {
      return true;
    }
    if (String(snapshot.series_mode || 'none') !== 'none'
      || Number(snapshot.series_id || 0) > 0
      || communityDraftTextHasContent(snapshot.new_series_title)
      || communityDraftTextHasContent(snapshot.series_episode_label)) {
      return true;
    }
    return Object.keys(snapshot).some(function (key) {
      return /^community_extra_fields\[[a-zA-Z0-9_]+\]$/.test(key) && communityDraftTextHasContent(snapshot[key]);
    });
  }

  function communityDraftEditorTextarea(form) {
    return form.querySelector('textarea[name="body_text"]');
  }

  function communityDraftSyncEditorToTextarea(form) {
    var textarea = communityDraftEditorTextarea(form);
    if (!textarea) {
      return;
    }
    var editor = textarea._srCkeditorInstance || (textarea.id && window.srCkeditorInstances ? window.srCkeditorInstances[textarea.id] : null);
    if (editor && typeof editor.getData === 'function') {
      textarea.value = editor.getData();
    }
  }

  function communityDraftSetBody(form, value) {
    var textarea = communityDraftEditorTextarea(form);
    if (!textarea) {
      return;
    }
    textarea.value = value || '';
    var editor = textarea._srCkeditorInstance || (textarea.id && window.srCkeditorInstances ? window.srCkeditorInstances[textarea.id] : null);
    if (editor && typeof editor.setData === 'function') {
      editor.setData(textarea.value);
    }
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function communityDraftSnapshot(form) {
    communityDraftSyncEditorToTextarea(form);
    var fields = {};
    Array.prototype.slice.call(form.elements).forEach(function (field) {
      if (!field.name || field.type === 'file' || field.name === 'csrf_token') {
        return;
      }
      if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
        return;
      }
      if (Object.prototype.hasOwnProperty.call(fields, field.name)) {
        if (!Array.isArray(fields[field.name])) {
          fields[field.name] = [fields[field.name]];
        }
        fields[field.name].push(field.value);
        return;
      }
      fields[field.name] = field.value;
    });
    return fields;
  }

  function communityDraftApply(form, payload) {
    if (!payload || typeof payload !== 'object') {
      return;
    }
    var title = form.querySelector('[name="title"]');
    if (title && Object.prototype.hasOwnProperty.call(payload, 'title')) {
      title.value = payload.title || '';
      title.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (Object.prototype.hasOwnProperty.call(payload, 'body_text')) {
      communityDraftSetBody(form, payload.body_text || '');
    }
    var category = form.querySelector('[name="category_id"]');
    if (category && Object.prototype.hasOwnProperty.call(payload, 'category_id')) {
      category.value = String(payload.category_id || '');
      category.dispatchEvent(new Event('change', { bubbles: true }));
    }
    var secret = form.querySelector('[name="is_secret"]');
    if (secret && Object.prototype.hasOwnProperty.call(payload, 'is_secret')) {
      secret.checked = String(payload.is_secret || '0') === '1';
      secret.dispatchEvent(new Event('change', { bubbles: true }));
    }
    var notice = form.querySelector('[name="is_notice"]');
    if (notice && Object.prototype.hasOwnProperty.call(payload, 'is_notice')) {
      notice.checked = String(payload.is_notice || '0') === '1';
      notice.dispatchEvent(new Event('change', { bubbles: true }));
    }
    var extra = payload.extra_field_values || {};
    Object.keys(extra).forEach(function (key) {
      var control = form.querySelector('[name="community_extra_fields[' + key.replace(/"/g, '\\"') + ']"]');
      if (!control) {
        return;
      }
      if (control.type === 'checkbox') {
        control.checked = String(extra[key] || '') === '1';
      } else {
        control.value = extra[key] || '';
      }
      control.dispatchEvent(new Event('change', { bubbles: true }));
    });
    var series = payload.series_values || {};
    [
      ['series_mode', 'series_mode'],
      ['series_id', 'series_id'],
      ['new_series_title', 'new_series_title'],
      ['series_episode_label', 'episode_label'],
      ['series_sort_order', 'sort_order']
    ].forEach(function (item) {
      var control = form.querySelector('[name="' + item[0] + '"]');
      if (!control || !Object.prototype.hasOwnProperty.call(series, item[1])) {
        return;
      }
      if (control.type === 'radio') {
        Array.prototype.slice.call(form.querySelectorAll('[name="' + item[0] + '"]')).forEach(function (radio) {
          radio.checked = radio.value === String(series[item[1]]);
        });
      } else {
        control.value = String(series[item[1]] || '');
      }
      control.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }

  function communityDraftPanel(form, payload) {
    var panel = document.querySelector('[data-community-draft-panel]');
    if (panel) {
      return panel;
    }
    if (!payload || Object.keys(payload).length < 1) {
      return null;
    }
    panel = document.createElement('div');
    panel.className = 'alert alert-info alert-removable';
    panel.setAttribute('role', 'status');
    panel.setAttribute('data-community-draft-panel', '');
    panel.innerHTML = '<p data-community-draft-message>저장된 임시글이 있습니다.</p><div class="admin-row-actions"><button type="button" class="btn btn-sm btn-solid-primary" data-community-draft-restore>복원</button> <button type="button" class="btn btn-sm btn-outline-secondary" data-community-draft-discard>삭제</button></div>';
    form.parentNode.insertBefore(panel, form);
    return panel;
  }

  function communityDraftPayloadFromStorageSnapshot(snapshot) {
    if (!snapshot || typeof snapshot !== 'object') {
      return {};
    }
    if (!communityDraftSnapshotHasContent(snapshot)) {
      return {};
    }
    var extra = {};
    Object.keys(snapshot).forEach(function (key) {
      var match = /^community_extra_fields\[([a-zA-Z0-9_]+)\]$/.exec(key);
      if (match) {
        extra[match[1]] = snapshot[key];
      }
    });
    return {
      title: snapshot.title || '',
      body_text: snapshot.body_text || '',
      category_id: snapshot.category_id || 0,
      is_secret: snapshot.is_secret || 0,
      is_notice: snapshot.is_notice || 0,
      extra_field_values: extra,
      series_values: {
        series_mode: snapshot.series_mode || 'none',
        series_id: snapshot.series_id || 0,
        new_series_title: snapshot.new_series_title || '',
        episode_label: snapshot.series_episode_label || '',
        sort_order: snapshot.series_sort_order || 0
      }
    };
  }

  function initDraftAutosave() {
    Array.prototype.slice.call(document.querySelectorAll('[data-community-draft-form]')).forEach(function (form) {
      if (form.getAttribute('data-community-draft-ready') === '1') {
        return;
      }
      var config = communityDraftJson(form.querySelector('[data-community-draft-config]'));
      if (!config.enabled || !config.endpoint) {
        return;
      }
      var serverPayload = communityDraftJson(form.querySelector('[data-community-draft-payload]'));
      var storageKey = communityDraftStorageKey(config);
      var storagePayload = {};
      try {
        storagePayload = JSON.parse(communityDraftStorageGet(storageKey) || '{}') || {};
      } catch (error) {
        storagePayload = {};
      }
      var restorePayload = communityDraftPayloadHasContent(serverPayload) ? serverPayload : communityDraftPayloadFromStorageSnapshot(storagePayload);
      var panel = communityDraftPanel(form, restorePayload);
      if (panel) {
        var restore = panel.querySelector('[data-community-draft-restore]');
        var discard = panel.querySelector('[data-community-draft-discard]');
        if (restore) {
          restore.addEventListener('click', function () {
            communityDraftApply(form, restorePayload);
            panel.remove();
          });
        }
        if (discard) {
          discard.addEventListener('click', function () {
            var data = new FormData(form);
            data.set('draft_action', 'delete');
            communityDraftStorageRemove(storageKey);
            fetch(config.endpoint, {
              method: 'POST',
              body: data,
              credentials: 'same-origin',
              headers: { 'Accept': 'application/json' }
            }).catch(function () {});
            panel.remove();
          });
        }
      }

      var interval = Math.max(30, Math.min(600, Number(config.interval_seconds || 60))) * 1000;
      var lastHash = '';
      var inFlight = false;
      var timer = 0;
      var backoff = interval;
      var submitInProgress = false;
      var autosaveController = null;

      function scheduleNextDraftSave(delay) {
        window.clearTimeout(timer);
        timer = window.setTimeout(saveDraft, delay);
      }

      function saveDraft() {
        if (inFlight || submitInProgress) {
          return;
        }
        var snapshot = communityDraftSnapshot(form);
        var hash = JSON.stringify(snapshot);
        if (!communityDraftSnapshotHasContent(snapshot)) {
          communityDraftStorageRemove(storageKey);
          lastHash = '';
          scheduleNextDraftSave(interval);
          return;
        }
        communityDraftStorageSet(storageKey, hash);
        if (hash === lastHash) {
          scheduleNextDraftSave(interval);
          return;
        }
        inFlight = true;
        autosaveController = window.AbortController ? new window.AbortController() : null;
        var data = new FormData(form);
        var fetchOptions = {
          method: 'POST',
          body: data,
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' }
        };
        if (autosaveController) {
          fetchOptions.signal = autosaveController.signal;
        }
        fetch(config.endpoint, fetchOptions).then(function (response) {
          return response.json().catch(function () {
            return { ok: false, message: 'invalid_json' };
          }).then(function (payload) {
            return { status: response.status, payload: payload };
          });
        }).then(function (result) {
          if (result.status >= 200 && result.status < 300 && result.payload && result.payload.ok) {
            lastHash = hash;
            backoff = interval;
            communityDraftStorageSet(storageKey, hash);
          } else {
            backoff = Math.min(interval * 8, Math.max(interval, backoff * 2));
          }
        }).catch(function () {
          backoff = Math.min(interval * 8, Math.max(interval, backoff * 2));
        }).finally(function () {
          inFlight = false;
          autosaveController = null;
          if (!submitInProgress) {
            scheduleNextDraftSave(backoff);
          }
        });
      }

      form.addEventListener('input', function () {
        communityDraftStorageSet(storageKey, JSON.stringify(communityDraftSnapshot(form)));
      });
      form.addEventListener('change', function () {
        communityDraftStorageSet(storageKey, JSON.stringify(communityDraftSnapshot(form)));
      });
      form.addEventListener('submit', function () {
        submitInProgress = true;
        window.clearTimeout(timer);
        communityDraftStorageRemove(storageKey);
        if (autosaveController && typeof autosaveController.abort === 'function') {
          autosaveController.abort();
        }
      });
      scheduleNextDraftSave(interval);
      form.setAttribute('data-community-draft-ready', '1');
    });
  }

  function initCommentPagination() {
    if (document.documentElement.getAttribute('data-community-comment-pagination-ready') === '1') {
      return;
    }

    document.addEventListener('click', function (event) {
      var eventTarget = event.target instanceof Element ? event.target : null;
      var link = eventTarget ? eventTarget.closest('[data-community-comment-page]') : null;
      if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
      }

      var commentsSection = link.closest('#comments');
      if (!commentsSection || commentsSection.getAttribute('aria-busy') === 'true') {
        return;
      }

      event.preventDefault();
      var pageUrl = new URL(link.href, window.location.href);
      var fetchUrl = new URL(pageUrl.href);
      fetchUrl.hash = '';
      fetchUrl.searchParams.set('comment_fragment', '1');
      commentsSection.setAttribute('aria-busy', 'true');

      fetch(fetchUrl.href, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'Accept': 'text/html',
          'X-Requested-With': 'XMLHttpRequest'
        }
      }).then(function (response) {
        if (!response.ok) {
          throw new Error('comment_page_http_' + String(response.status));
        }
        return response.text();
      }).then(function (html) {
        var parsed = new DOMParser().parseFromString(html, 'text/html');
        var nextCommentsSection = parsed.querySelector('#comments');
        if (!nextCommentsSection) {
          throw new Error('comment_page_section_missing');
        }

        var scrollTop = window.scrollY;
        commentsSection.replaceWith(document.importNode(nextCommentsSection, true));
        window.history.replaceState(window.history.state, '', pageUrl.pathname + pageUrl.search + pageUrl.hash);
        initToasts();
        initCopyUrlButtons();
        initScrollTargetButtons();
        window.requestAnimationFrame(function () {
          window.scrollTo({ top: scrollTop, left: window.scrollX, behavior: 'auto' });
        });
      }).catch(function () {
        commentsSection.removeAttribute('aria-busy');
        var previousError = commentsSection.querySelector('[data-community-comment-page-error]');
        if (previousError) {
          previousError.remove();
        }
        var error = document.createElement('div');
        error.className = 'alert alert-danger';
        error.setAttribute('role', 'alert');
        error.setAttribute('data-community-comment-page-error', '1');
        error.textContent = '댓글 페이지를 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.';
        commentsSection.insertBefore(error, commentsSection.firstChild);
      });
    });

    document.documentElement.setAttribute('data-community-comment-pagination-ready', '1');
  }

  function initCommentSharedModals() {
    if (document.documentElement.getAttribute('data-community-comment-shared-modals-ready') === '1') {
      return;
    }

    document.addEventListener('click', function (event) {
      var eventTarget = event.target instanceof Element ? event.target : null;
      if (!eventTarget) {
        return;
      }

      var replyButton = eventTarget.closest('[data-community-comment-reply]');
      if (replyButton) {
        var replyModal = document.querySelector('[data-community-comment-reply-modal]');
        if (!replyModal) {
          return;
        }
        var replyId = replyModal.querySelector('[data-community-comment-reply-id]');
        var replyBody = replyModal.querySelector('[data-community-comment-reply-body]');
        var replySource = replyModal.querySelector('[data-community-comment-reply-source]');
        var replySecret = replyModal.querySelector('[data-community-comment-reply-secret]');
        var nextReplyId = replyButton.getAttribute('data-comment-id') || '';
        var preserveReplyInput = replyId && replyId.value === nextReplyId && replyBody && replyBody.value !== '';
        if (replyId) {
          replyId.value = nextReplyId;
        }
        if (replySource) {
          replySource.textContent = replyButton.getAttribute('data-comment-body') || '';
        }
        if (replyBody && !preserveReplyInput) {
          replyBody.value = '';
        }
        if (replySecret && !preserveReplyInput) {
          replySecret.checked = false;
        }
        return;
      }

      var editButton = eventTarget.closest('[data-community-comment-edit]');
      if (editButton) {
        var editModal = document.querySelector('[data-community-comment-edit-modal]');
        if (!editModal) {
          return;
        }
        var editId = editModal.querySelector('[data-community-comment-edit-id]');
        var editBody = editModal.querySelector('[data-community-comment-edit-body]');
        var editSecret = editModal.querySelector('[data-community-comment-edit-secret]');
        var editSecretField = editModal.querySelector('[data-community-comment-edit-secret-field]');
        var isSecret = editButton.getAttribute('data-comment-secret') === '1';
        if (editId) {
          editId.value = editButton.getAttribute('data-comment-id') || '';
        }
        if (editBody) {
          editBody.value = editButton.getAttribute('data-comment-body') || '';
        }
        if (editSecret) {
          editSecret.checked = isSecret;
        }
        if (editSecretField) {
          editSecretField.hidden = editModal.getAttribute('data-secret-comments-enabled') !== '1' && !isSecret;
        }
        return;
      }

      var reportButton = eventTarget.closest('[data-community-comment-report]');
      if (!reportButton) {
        return;
      }
      var reportModal = document.querySelector('[data-community-comment-report-modal]');
      if (!reportModal) {
        return;
      }
      var reportId = reportModal.querySelector('[data-community-comment-report-id]');
      var reportMemo = reportModal.querySelector('textarea[name="memo_text"]');
      var reportReason = reportModal.querySelector('select[name="reason_key"]');
      if (reportId) {
        reportId.value = reportButton.getAttribute('data-comment-id') || '';
      }
      if (reportMemo) {
        reportMemo.value = '';
      }
      if (reportReason) {
        reportReason.selectedIndex = 0;
      }
    });

    document.documentElement.setAttribute('data-community-comment-shared-modals-ready', '1');
  }

  function initDeferredSummary() {
    Array.prototype.slice.call(document.querySelectorAll('[data-community-summary-deferred]')).forEach(function (summary) {
      var endpoint = summary.getAttribute('data-community-summary-url') || '';
      var fallbackUrl = summary.getAttribute('data-community-summary-fallback-url') || '';
      var status = summary.querySelector('[data-community-summary-status]');
      if (endpoint === '' || summary.getAttribute('data-community-summary-loading') === '1') {
        return;
      }

      summary.setAttribute('data-community-summary-loading', '1');
      fetch(endpoint, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      }).then(function (response) {
        if (!response.ok) {
          throw new Error('community_summary_request_failed');
        }
        return response.text();
      }).then(function (html) {
        var parser = new DOMParser();
        var documentFragment = parser.parseFromString(html, 'text/html');
        var nextSummary = documentFragment.querySelector('.community-home-aside');
        if (!nextSummary) {
          throw new Error('community_summary_markup_missing');
        }
        summary.replaceWith(document.importNode(nextSummary, true));
      }).catch(function () {
        summary.removeAttribute('data-community-summary-loading');
        if (status) {
          status.textContent = '커뮤니티 요약을 불러오지 못했습니다.';
          if (fallbackUrl !== '') {
            var fallbackLink = document.createElement('a');
            fallbackLink.href = fallbackUrl;
            fallbackLink.textContent = '커뮤니티 요약 보기';
            status.appendChild(document.createTextNode(' '));
            status.appendChild(fallbackLink);
          }
        }
      });
    });
  }

  function init() {
    initImageLayer();
    initToasts();
    initCopyUrlButtons();
    initScrollTargetButtons();
    initAssetConfirmationSubmitClose();
    initDraftAutosave();
    initCommentSharedModals();
    initCommentPagination();
    initDeferredSummary();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
    return;
  }

  init();
})();
