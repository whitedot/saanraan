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

  test('missing CKEditor bundle keeps textarea fallback and does not mark html format', async ({ page }) => {
    await writeCkeditorFixture(page, {
      selfHostedScriptUrl: '/modules/ckeditor/vendor/ckeditor5/missing-fixture.js',
    });

    await page.waitForFunction(() => document.documentElement.classList.contains('sr-ckeditor-unavailable'), null, { timeout: 10000 });
    await expect(page.locator('textarea[data-sr-editor="ckeditor"]')).toHaveCount(1);
    await expect(page.locator('input[name="body_format"][data-sr-editor-format="ckeditor"]')).toHaveCount(0);
    expect(await page.locator('textarea[data-sr-editor="ckeditor"]').evaluate((textarea) => textarea.dataset.srEditorReady || '')).not.toBe('1');
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
