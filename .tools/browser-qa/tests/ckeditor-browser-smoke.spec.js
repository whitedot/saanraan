const { test, expect } = require('@playwright/test');

function scriptSafeJson(value) {
  return JSON.stringify(value).replace(/</g, '\\u003c');
}

function textareaAttributes(attributes) {
  return Object.entries(attributes)
    .filter(([, value]) => value !== '')
    .map(([name, value]) => ` ${name}="${String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;')}"`)
    .join('');
}

async function writeCkeditorFixture(page, overrides = {}, textareaOverrides = {}) {
  const config = {
    assetMode: 'self_hosted',
    selfHostedScriptUrl: '/modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js',
    selfHostedStylesheetUrl: '/modules/ckeditor/vendor/ckeditor5/ckeditor5.css',
    pluginStylesheetUrl: '/modules/ckeditor/assets/saanraan-ckeditor.css',
    licenseKey: 'GPL',
    toolbar: ['undo', 'redo', '|', 'bold', 'italic', 'link', 'bulletedList'],
    ...overrides,
  };
  const textarea = {
    id: 'body',
    name: 'body',
    'data-sr-editor': 'ckeditor',
    'data-sr-editor-format-name': 'body_format',
    ...textareaOverrides,
  };

  const html = `<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <title>CKEditor browser smoke</title>
  <style>
    [data-color-scheme="light"] { --sr-surface: #ffffff; --sr-surface-muted: #f4f6f8; --sr-text: #172033; --sr-muted: #5f6b7a; --sr-border: #d9e0e8; --sr-border-soft: #e8edf2; }
    [data-color-scheme="dark"] { --sr-surface: #171a21; --sr-surface-muted: #20242d; --sr-text: #f4f6f8; --sr-muted: #aeb7c4; --sr-border: #343b47; --sr-border-soft: #2b313c; }
  </style>
  <script id="sr-ckeditor-config" type="application/json">${scriptSafeJson(config)}</script>
</head>
<body>
  <form method="post">
    <input type="checkbox" name="community_privacy_consent_accepted" value="1" checked>
    <textarea${textareaAttributes(textarea)}>&lt;p&gt;fixture&lt;/p&gt;</textarea>
  </form>
  <script src="/modules/ckeditor/assets/saanraan-ckeditor.js"></script>
</body>
</html>`;

  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.evaluate((fixtureHtml) => {
    document.open();
    document.write(fixtureHtml);
    document.close();
  }, html);
}

test.describe('CKEditor browser smoke', () => {
  test('self-hosted CKEditor asset initializes textarea and marks html format', async ({ page }) => {
    const loadedAssets = new Set();
    page.on('response', (response) => {
      const url = response.url();
      if (url.includes('/modules/ckeditor/')) {
        loadedAssets.add(`${response.status()} ${new URL(url).pathname}`);
      }
    });

    await writeCkeditorFixture(page);
    await page.waitForFunction(() => {
      const textarea = document.querySelector('textarea[data-sr-editor="ckeditor"]');
      return textarea && textarea.dataset.srEditorReady === '1';
    }, null, { timeout: 20000 });

    await expect(page.locator('.ck-editor')).toHaveCount(1);
    await expect(page.locator('input[name="body_format"][data-sr-editor-format="ckeditor"]')).toHaveValue('html');
    expect(await page.evaluate(() => Boolean(window.CKEDITOR && window.CKEDITOR.ClassicEditor))).toBe(true);
    expect([...loadedAssets]).toEqual(expect.arrayContaining([
      '200 /modules/ckeditor/assets/saanraan-ckeditor.js',
      '200 /modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js',
      '200 /modules/ckeditor/vendor/ckeditor5/ckeditor5.css',
      '200 /modules/ckeditor/assets/saanraan-ckeditor.css',
    ]));
  });

  test('standard toolbar excludes selection utilities and wraps narrow overflow with image insertion visible', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 720 });
    await writeCkeditorFixture(page, {
      toolbar: [
        'undo', 'redo', '|', 'insertImage', 'link', 'insertTable', '|', 'heading', 'fontSize', '|',
        'bold', 'italic', 'underline', 'strikethrough', '|',
        'fontColor', 'fontBackgroundColor', 'removeFormat', '|',
        'alignment', '|', 'horizontalLine', 'blockQuote', '|',
        'bulletedList', 'numberedList', 'outdent', 'indent',
      ],
    });
    await page.waitForFunction(() => {
      const textarea = document.querySelector('textarea[data-sr-editor="ckeditor"]');
      return textarea && textarea.dataset.srEditorReady === '1' && window.srCkeditorInstances && window.srCkeditorInstances.body;
    }, null, { timeout: 20000 });

    const toolbarState = await page.evaluate(() => {
      const editor = window.srCkeditorInstances.body;
      const container = document.querySelector('.sr-ckeditor');
      const toolbar = document.querySelector('.ck-toolbar');
      const imageButton = Array.from(toolbar.querySelectorAll('button')).find((button) => {
        const label = [button.getAttribute('aria-label'), button.getAttribute('data-cke-tooltip-text'), button.textContent].join(' ');
        return /image|이미지/i.test(label);
      });
      return {
        hasFindAndReplace: editor.plugins.has('FindAndReplace'),
        hasSelectAll: editor.plugins.has('SelectAll'),
        hasImageInsertViaUrl: editor.plugins.has('ImageInsertViaUrl'),
        shouldNotGroupWhenFull: editor.config.get('toolbar.shouldNotGroupWhenFull'),
        groupedDropdownCount: toolbar.querySelectorAll('.ck-toolbar__grouped-dropdown').length,
        imageButtonVisible: Boolean(imageButton && imageButton.getBoundingClientRect().width > 0),
        containerFitsViewport: container.getBoundingClientRect().right <= document.documentElement.clientWidth,
        toolbarFitsContainer: toolbar.scrollWidth <= container.clientWidth,
      };
    });

    expect(toolbarState).toEqual({
      hasFindAndReplace: false,
      hasSelectAll: false,
      hasImageInsertViaUrl: true,
      shouldNotGroupWhenFull: true,
      groupedDropdownCount: 0,
      imageButtonVisible: true,
      containerFitsViewport: true,
      toolbarFitsContainer: true,
    });
  });

  test('missing CKEditor bundle keeps textarea fallback and does not mark html format', async ({ page }) => {
    await writeCkeditorFixture(page, {
      selfHostedScriptUrl: '/modules/ckeditor/vendor/ckeditor5/missing-fixture.js',
    });

    await page.waitForFunction(() => document.documentElement.classList.contains('sr-ckeditor-unavailable'), null, { timeout: 10000 });
    await expect(page.locator('textarea[data-sr-editor="ckeditor"]')).toHaveCount(1);
    await expect(page.locator('input[name="body_format"][data-sr-editor-format="ckeditor"]')).toHaveCount(0);
    expect(await page.locator('textarea[data-sr-editor="ckeditor"]').evaluate((textarea) => textarea.dataset.srEditorReady || '')).not.toBe('1');
  });

  test('official editor theme follows project light and dark tokens', async ({ page }) => {
    await writeCkeditorFixture(page);
    await page.waitForFunction(() => {
      const textarea = document.querySelector('textarea[data-sr-editor="ckeditor"]');
      return textarea && textarea.dataset.srEditorReady === '1';
    }, null, { timeout: 20000 });

    const styles = await page.evaluate(async () => {
      const read = async (scheme) => {
        document.documentElement.dataset.colorScheme = scheme;
        const editable = document.querySelector('.ck-editor__editable_inline');
        editable.focus();
        await new Promise((resolve) => requestAnimationFrame(resolve));
        const toolbar = getComputedStyle(document.querySelector('.ck-toolbar'));
        const content = getComputedStyle(editable);
        return {
          toolbarBackground: toolbar.backgroundColor,
          contentBackground: content.backgroundColor,
          contentColor: content.color,
          borderRadius: content.borderRadius,
          focusShadow: content.boxShadow,
        };
      };

      return { light: await read('light'), dark: await read('dark') };
    });

    expect(styles.light.toolbarBackground).not.toBe('rgba(0, 0, 0, 0)');
    expect(styles.dark.toolbarBackground).not.toBe('rgba(0, 0, 0, 0)');
    expect(styles.light.toolbarBackground).not.toBe(styles.dark.toolbarBackground);
    expect(styles.light.contentColor).not.toBe(styles.dark.contentColor);
    expect(styles.light.borderRadius).not.toBe('0px');
    expect(styles.dark.borderRadius).not.toBe('0px');
    expect(styles.light.focusShadow).not.toBe('none');
    expect(styles.dark.focusShadow).not.toBe('none');
  });

  test('module body themes keep editor input and rendered comment output colors identical', async ({ page }) => {
    const cases = [
      { theme: 'content.basic', expected: 'rgb(170, 17, 34)' },
      { theme: 'community.basic', expected: 'rgb(17, 136, 51)' },
      { theme: 'quiz.basic', expected: 'rgb(23, 32, 51)' },
      { theme: 'survey.basic', expected: 'rgb(23, 32, 51)' },
    ];

    for (const bodyTheme of cases) {
      await writeCkeditorFixture(page, {}, { 'data-sr-editor-body-theme': bodyTheme.theme });
      await page.waitForFunction(() => {
        const textarea = document.querySelector('textarea[data-sr-editor="ckeditor"]');
        return textarea && textarea.dataset.srEditorReady === '1';
      }, null, { timeout: 20000 });

      const colors = await page.evaluate((theme) => {
        document.documentElement.dataset.colorScheme = 'light';
        document.documentElement.style.setProperty('--content-text', '#aa1122');
        document.documentElement.style.setProperty('--community-text', '#118833');
        const output = document.createElement('div');
        output.className = 'sr-ckeditor';
        output.dataset.srEditorOutput = '';
        output.dataset.srEditorBodyTheme = theme;
        output.innerHTML = '<div class="ck-content">저장된 댓글</div>';
        document.body.appendChild(output);

        return {
          editor: getComputedStyle(document.querySelector('.ck-editor__editable_inline')).color,
          output: getComputedStyle(output.querySelector('.ck-content')).color,
        };
      }, bodyTheme.theme);

      expect(colors.editor).toBe(bodyTheme.expected);
      expect(colors.output).toBe(bodyTheme.expected);
    }
  });

  test('upload adapter sends image, csrf, upload token, and consent fields', async ({ page }) => {
    let capturedMultipart = '';
    await page.route('**/ckeditor-upload-fixture', async (route) => {
      const request = route.request();
      const body = request.postDataBuffer();
      capturedMultipart = body ? body.toString('utf8') : '';
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ url: '/content/body-file?tmp=fixture-token&file=image.png' }),
      });
    });

    await writeCkeditorFixture(page, {}, {
      'data-sr-editor-upload-url': '/ckeditor-upload-fixture',
      'data-sr-editor-upload-field': 'body_image',
      'data-sr-editor-upload-csrf': 'csrf-fixture',
      'data-sr-editor-upload-token': 'upload-token-fixture',
    });
    await page.waitForFunction(() => {
      const textarea = document.querySelector('textarea[data-sr-editor="ckeditor"]');
      return textarea && textarea.dataset.srEditorReady === '1' && window.srCkeditorInstances && window.srCkeditorInstances.body;
    }, null, { timeout: 20000 });

    const result = await page.evaluate(async () => {
      const editor = window.srCkeditorInstances.body;
      const repository = editor.plugins.get('FileRepository');
      const file = new File(['fixture image'], 'image.png', { type: 'image/png' });
      const adapter = repository.createUploadAdapter({ file: Promise.resolve(file) });
      return adapter.upload();
    });

    expect(result).toEqual({ default: '/content/body-file?tmp=fixture-token&file=image.png' });
    expect(capturedMultipart).toContain('name="body_image"; filename="image.png"');
    expect(capturedMultipart).toContain('name="csrf_token"');
    expect(capturedMultipart).toContain('csrf-fixture');
    expect(capturedMultipart).toContain('name="upload_token"');
    expect(capturedMultipart).toContain('upload-token-fixture');
    expect(capturedMultipart).toContain('name="community_privacy_consent_accepted"');
  });

  test('upload adapter rejects server error messages', async ({ page }) => {
    await page.route('**/ckeditor-upload-error-fixture', async (route) => {
      await route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({ error: { message: 'fixture upload rejected' } }),
      });
    });

    await writeCkeditorFixture(page, {}, {
      'data-sr-editor-upload-url': '/ckeditor-upload-error-fixture',
      'data-sr-editor-upload-field': 'body_image',
    });
    await page.waitForFunction(() => {
      const textarea = document.querySelector('textarea[data-sr-editor="ckeditor"]');
      return textarea && textarea.dataset.srEditorReady === '1' && window.srCkeditorInstances && window.srCkeditorInstances.body;
    }, null, { timeout: 20000 });

    const errorMessage = await page.evaluate(async () => {
      const editor = window.srCkeditorInstances.body;
      const repository = editor.plugins.get('FileRepository');
      const file = new File(['fixture image'], 'image.png', { type: 'image/png' });
      const adapter = repository.createUploadAdapter({ file: Promise.resolve(file) });
      try {
        await adapter.upload();
        return '';
      } catch (error) {
        return String(error);
      }
    });

    expect(errorMessage).toBe('fixture upload rejected');
  });
});
