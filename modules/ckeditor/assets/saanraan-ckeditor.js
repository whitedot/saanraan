(function () {
  'use strict';

  var configElement = document.getElementById('sr-ckeditor-config');
  var config = {};

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
      'BlockQuote'
    ];
    var plugins = [];

    pluginNames.forEach(function (pluginName) {
      if (ckeditor[pluginName]) {
        plugins.push(ckeditor[pluginName]);
      }
    });

    return {
      licenseKey: config.licenseKey || 'GPL',
      plugins: plugins,
      toolbar: toolbar,
      link: {
        addTargetToExternalLinks: true,
        defaultProtocol: 'https://'
      }
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
