(function () {
  'use strict';

  var configElement = document.getElementById('sr-ckeditor-config');
  var config = {};
  window.srCkeditorInstances = window.srCkeditorInstances || {};

  if (configElement) {
    try {
      config = JSON.parse(configElement.textContent || '{}');
    } catch (error) {
      config = {};
    }
  }

  function loadStylesheet(url) {
    if (!url || document.querySelector('link[data-sr-ckeditor-stylesheet="' + url + '"]')) {
      return;
    }

    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = url;
    link.setAttribute('data-sr-ckeditor-stylesheet', url);
    document.head.appendChild(link);
  }

  function loadScript(url) {
    return new Promise(function (resolve, reject) {
      if (!url) {
        reject(new Error('CKEditor script URL is empty.'));
        return;
      }

      if (window.CKEDITOR && window.CKEDITOR.ClassicEditor) {
        resolve(window.CKEDITOR);
        return;
      }

      var existing = document.querySelector('script[data-sr-ckeditor-script="' + url + '"]');
      if (existing) {
        existing.addEventListener('load', function () { resolve(window.CKEDITOR); }, { once: true });
        existing.addEventListener('error', reject, { once: true });
        return;
      }

      var script = document.createElement('script');
      script.src = url;
      script.defer = true;
      script.setAttribute('data-sr-ckeditor-script', url);
      script.addEventListener('load', function () { resolve(window.CKEDITOR); }, { once: true });
      script.addEventListener('error', reject, { once: true });
      document.head.appendChild(script);
    });
  }

  function uploadConfig(textarea) {
    var url = textarea.dataset.srEditorUploadUrl || '';
    if (!url) {
      return null;
    }

    return {
      url: url,
      fieldName: textarea.dataset.srEditorUploadField || 'upload',
      csrfToken: textarea.dataset.srEditorUploadCsrf || '',
      uploadToken: textarea.dataset.srEditorUploadToken || '',
      form: textarea.form || null
    };
  }

  function editorConfig(ckeditor, textarea) {
    var toolbar = Array.isArray(config.toolbar) ? config.toolbar : ['undo', 'redo', '|', 'bold', 'italic'];
    var upload = uploadConfig(textarea);
    var pluginNames = [
      'Essentials',
      'Paragraph',
      'Heading',
      'Bold',
      'Italic',
      'Underline',
      'Strikethrough',
      'Link',
      'List',
      'BlockQuote',
      'Image',
      'ImageBlock',
      'ImageInline',
      'ImageInsert',
      'ImageUpload',
      'ImageToolbar',
      'ImageCaption',
      'ImageStyle',
      'ImageResize',
      'FileRepository',
      'GeneralHtmlSupport'
    ];
    var plugins = [];

    pluginNames.forEach(function (pluginName) {
      if (ckeditor[pluginName]) {
        plugins.push(ckeditor[pluginName]);
      }
    });

    var editorOptions = {
      licenseKey: config.licenseKey || 'GPL',
      plugins: plugins,
      toolbar: toolbar,
      link: {
        addTargetToExternalLinks: true,
        defaultProtocol: 'https://'
      }
    };

    if (upload && upload.url && ckeditor.FileRepository) {
      editorOptions.extraPlugins = [saanraanUploadAdapterPlugin(upload)];
      editorOptions.image = {
        toolbar: ['imageTextAlternative', 'toggleImageCaption', '|', 'imageStyle:inline', 'imageStyle:block', 'imageStyle:side']
      };
    }

    return editorOptions;
  }

  function SaanraanUploadAdapter(loader, upload) {
    this.loader = loader;
    this.uploadConfig = upload;
    this.xhr = null;
  }

  SaanraanUploadAdapter.prototype.upload = function () {
    var adapter = this;

    return this.loader.file.then(function (file) {
      return new Promise(function (resolve, reject) {
        var xhr = new XMLHttpRequest();
        var data = new FormData();
        var upload = adapter.uploadConfig || {};
        var fieldName = upload.fieldName || 'upload';

        adapter.xhr = xhr;
        xhr.open('POST', upload.url, true);
        xhr.responseType = 'json';
        xhr.addEventListener('error', function () {
          reject('본문 이미지 업로드를 처리할 수 없습니다.');
        });
        xhr.addEventListener('abort', function () {
          reject('본문 이미지 업로드가 취소되었습니다.');
        });
        xhr.addEventListener('load', function () {
          var response = xhr.response || {};

          if (xhr.status < 200 || xhr.status >= 300 || response.error) {
            reject(response.error && response.error.message ? response.error.message : '본문 이미지 업로드를 처리할 수 없습니다.');
            return;
          }

          if (!response.url) {
            reject('본문 이미지 업로드 응답이 올바르지 않습니다.');
            return;
          }

          resolve({ default: response.url });
        });

        data.append(fieldName, file);
        if (upload.csrfToken) {
          data.append('csrf_token', upload.csrfToken);
        }
        if (upload.uploadToken) {
          data.append('upload_token', upload.uploadToken);
        }
        if (upload.form) {
          var consent = upload.form.querySelector('input[name="community_privacy_consent_accepted"]');
          if (consent && consent.checked) {
            data.append('community_privacy_consent_accepted', consent.value || '1');
          }
        }
        xhr.send(data);
      });
    });
  };

  SaanraanUploadAdapter.prototype.abort = function () {
    if (this.xhr) {
      this.xhr.abort();
    }
  };

  function saanraanUploadAdapterPlugin(upload) {
    return function (editor) {
      editor.plugins.get('FileRepository').createUploadAdapter = function (loader) {
        return new SaanraanUploadAdapter(loader, upload);
      };
    };
  }

  function markHtmlFormat(textarea) {
    var form = textarea.form;
    var formatName = textarea.dataset.srEditorFormatName || 'body_format';
    var existing;
    if (!form) {
      return;
    }

    existing = form.querySelector('input[type="hidden"][name="' + formatName + '"]');
    if (!existing) {
      existing = form.querySelector('input[name="' + formatName + '"][data-sr-editor-format="ckeditor"]');
    }
    if (existing) {
      existing.value = 'html';
      existing.setAttribute('data-sr-editor-format', 'ckeditor');
      return;
    }

    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = formatName;
    input.value = 'html';
    input.setAttribute('data-sr-editor-format', 'ckeditor');
    form.appendChild(input);
  }

  function applyBodyTheme(textarea, editorElement) {
    var cssVars = [
      ['srEditorBodySurface', '--sr-editor-body-surface'],
      ['srEditorBodyText', '--sr-editor-body-text'],
      ['srEditorBodyMuted', '--sr-editor-body-muted'],
      ['srEditorBodyBorder', '--sr-editor-body-border']
    ];

    if (!textarea || !editorElement) {
      return;
    }

    if (textarea.dataset.srEditorBodyTheme) {
      editorElement.setAttribute('data-sr-editor-body-theme', textarea.dataset.srEditorBodyTheme);
    }

    cssVars.forEach(function (item) {
      var value = textarea.dataset[item[0]] || '';
      if (value) {
        editorElement.style.setProperty(item[1], value);
      }
    });
  }

  function editorForTextarea(textarea) {
    if (!textarea) {
      return null;
    }

    if (textarea._srCkeditorInstance) {
      return textarea._srCkeditorInstance;
    }

    if (textarea.id && window.srCkeditorInstances && window.srCkeditorInstances[textarea.id]) {
      return window.srCkeditorInstances[textarea.id];
    }

    return null;
  }

  function syncTextareaValue(textarea, editor, notify) {
    if (!textarea || !editor || typeof editor.getData !== 'function') {
      return;
    }

    var nextValue = editor.getData();
    if (textarea.value === nextValue) {
      return;
    }

    textarea.value = nextValue;
    if (notify) {
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
      textarea.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  function syncFormEditors(form) {
    if (!form || !form.querySelectorAll) {
      return;
    }

    form.querySelectorAll('textarea[data-sr-editor="ckeditor"]').forEach(function (textarea) {
      syncTextareaValue(textarea, editorForTextarea(textarea), false);
    });
  }

  function enhance(ckeditor) {
    if (!ckeditor || !ckeditor.ClassicEditor) {
      return;
    }

    document.querySelectorAll('textarea[data-sr-editor="ckeditor"]').forEach(function (textarea) {
      if (textarea._srCkeditorDestroyPromise) {
        textarea._srCkeditorDestroyPromise.then(function () {
          if (textarea.dataset.srEditor === 'ckeditor') {
            enhance(ckeditor);
          }
        });
        return;
      }

      if (textarea.dataset.srEditorReady === '1' || textarea.dataset.srEditorInitializing === '1' || editorForTextarea(textarea)) {
        return;
      }

      textarea.dataset.srEditorInitializing = '1';
      ckeditor.ClassicEditor.create(textarea, editorConfig(ckeditor, textarea)).then(function (editor) {
        if (textarea.dataset.srEditor !== 'ckeditor') {
          syncTextareaValue(textarea, editor, true);
          textarea.dataset.srEditorReady = '0';
          textarea.dataset.srEditorInitializing = '0';
          if (typeof editor.destroy === 'function') {
            editor.destroy().catch(function () {});
          }
          return;
        }

        if (editor.ui && editor.ui.view && editor.ui.view.element) {
          editor.ui.view.element.classList.add('sr-ckeditor');
          applyBodyTheme(textarea, editor.ui.view.element);
        }
        textarea._srCkeditorInstance = editor;
        if (textarea.id) {
          window.srCkeditorInstances[textarea.id] = editor;
        }
        textarea.dataset.srEditorReady = '1';
        textarea.dataset.srEditorInitializing = '0';
        markHtmlFormat(textarea);
        syncTextareaValue(textarea, editor, false);
        if (editor.model && editor.model.document && typeof editor.model.document.on === 'function') {
          editor.model.document.on('change:data', function () {
            syncTextareaValue(textarea, editor, true);
          });
        }
      }).catch(function () {
        textarea.dataset.srEditorReady = '0';
        textarea.dataset.srEditorInitializing = '0';
      });
    });
  }

  window.srCkeditorEnhance = function () {
    enhance(window.CKEDITOR);
  };

  window.srCkeditorDestroyTextarea = function (textarea) {
    var editor = editorForTextarea(textarea);

    if (!textarea || !editor) {
      return Promise.resolve();
    }

    syncTextareaValue(textarea, editor, true);
    textarea.dataset.srEditorReady = '0';
    textarea.dataset.srEditorInitializing = '0';

    if (textarea._srCkeditorDestroyPromise) {
      return textarea._srCkeditorDestroyPromise;
    }

    function cleanup() {
      textarea._srCkeditorInstance = null;
      textarea._srCkeditorDestroyPromise = null;
      if (textarea.id && window.srCkeditorInstances) {
        delete window.srCkeditorInstances[textarea.id];
      }
    }

    if (typeof editor.destroy === 'function') {
      textarea._srCkeditorDestroyPromise = editor.destroy().catch(function () {}).then(cleanup);
      return textarea._srCkeditorDestroyPromise;
    }

    cleanup();
    return Promise.resolve();
  };

  document.addEventListener('DOMContentLoaded', function () {
    var scriptUrl = config.assetMode === 'self_hosted' ? config.selfHostedScriptUrl : config.cdnScriptUrl;
    var stylesheetUrl = config.assetMode === 'self_hosted' ? config.selfHostedStylesheetUrl : config.cdnStylesheetUrl;

    loadStylesheet(stylesheetUrl);
    loadStylesheet(config.pluginStylesheetUrl);
    loadScript(scriptUrl).then(function (ckeditor) {
      enhance(ckeditor);
      document.dispatchEvent(new CustomEvent('sr:ckeditor-ready'));
    }).catch(function () {
      document.documentElement.classList.add('sr-ckeditor-unavailable');
    });
  });

  document.addEventListener('submit', function (event) {
    syncFormEditors(event.target);
  }, true);
}());
