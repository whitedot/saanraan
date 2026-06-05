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

  function editorConfig(ckeditor) {
    var toolbar = Array.isArray(config.toolbar) ? config.toolbar : ['undo', 'redo', '|', 'bold', 'italic'];
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
      'FileRepository'
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

    if (config.upload && config.upload.url && ckeditor.FileRepository) {
      editorOptions.extraPlugins = [saanraanUploadAdapterPlugin];
      editorOptions.image = {
        toolbar: ['imageTextAlternative', 'toggleImageCaption', '|', 'imageStyle:inline', 'imageStyle:block', 'imageStyle:side']
      };
    }

    return editorOptions;
  }

  function SaanraanUploadAdapter(loader) {
    this.loader = loader;
    this.xhr = null;
  }

  SaanraanUploadAdapter.prototype.upload = function () {
    var adapter = this;

    return this.loader.file.then(function (file) {
      return new Promise(function (resolve, reject) {
        var xhr = new XMLHttpRequest();
        var data = new FormData();
        var fieldName = config.upload.fieldName || 'upload';

        adapter.xhr = xhr;
        xhr.open('POST', config.upload.url, true);
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
        if (config.upload.csrfToken) {
          data.append('csrf_token', config.upload.csrfToken);
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

  function saanraanUploadAdapterPlugin(editor) {
    editor.plugins.get('FileRepository').createUploadAdapter = function (loader) {
      return new SaanraanUploadAdapter(loader);
    };
  }

  function markHtmlFormat(textarea) {
    var form = textarea.form;
    var formatName = textarea.dataset.srEditorFormatName || 'body_format';
    if (!form || form.querySelector('input[name="' + formatName + '"][data-sr-editor-format="ckeditor"]')) {
      return;
    }

    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = formatName;
    input.value = 'html';
    input.setAttribute('data-sr-editor-format', 'ckeditor');
    form.appendChild(input);
  }

  function enhance(ckeditor) {
    if (!ckeditor || !ckeditor.ClassicEditor) {
      return;
    }

    document.querySelectorAll('textarea[data-sr-editor="ckeditor"]').forEach(function (textarea) {
      if (textarea.dataset.srEditorReady === '1') {
        return;
      }

      ckeditor.ClassicEditor.create(textarea, editorConfig(ckeditor)).then(function (editor) {
        if (editor.ui && editor.ui.view && editor.ui.view.element) {
          editor.ui.view.element.classList.add('sr-ckeditor');
        }
        if (textarea.id) {
          window.srCkeditorInstances[textarea.id] = editor;
        }
        textarea.dataset.srEditorReady = '1';
        markHtmlFormat(textarea);
      }).catch(function () {
        textarea.dataset.srEditorReady = '0';
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var scriptUrl = config.assetMode === 'self_hosted' ? config.selfHostedScriptUrl : config.cdnScriptUrl;
    var stylesheetUrl = config.assetMode === 'self_hosted' ? config.selfHostedStylesheetUrl : config.cdnStylesheetUrl;

    loadStylesheet(stylesheetUrl);
    loadStylesheet(config.pluginStylesheetUrl);
    loadScript(scriptUrl).then(enhance).catch(function () {
      document.documentElement.classList.add('sr-ckeditor-unavailable');
    });
  });
}());
