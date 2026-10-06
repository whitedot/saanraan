const path = require('path');
const { test, expect } = require('@playwright/test');

const root = path.resolve(__dirname, '../../..');

test('community comment editor modal stays bounded with long content in light and dark modes', async ({ page }) => {
  await page.setViewportSize({ width: 560, height: 789 });
  const longSource = Array.from({ length: 40 }, (_, index) => `긴 원문 ${index + 1} 줄과verylongtokenwithoutspaces${index}`).join('\n');

  await page.setContent(`<!doctype html>
    <html lang="ko" data-color-scheme="light">
    <body>
      <label style="position:absolute;left:-9999px">본문<textarea class="form-textarea" data-plain-editor-fixture>기본 입력 본문</textarea></label>
      <main class="community-screen">
        <div class="modal-overlay overlay overlay-open open">
          <div class="modal-dialog community-comment-editor-dialog">
            <form class="modal-content">
              <div class="modal-header"><h3 class="modal-title">답글 작성</h3><button type="button">닫기</button></div>
              <div class="modal-body">
                <strong class="community-comment-reply-source-label">댓글</strong>
                <p class="community-comment-reply-source" tabindex="0" aria-label="답글 대상 댓글"></p>
                <p class="community-comment-editor-field">
                  <label><span>답글 (필수)</span>
                    <textarea id="comment_body" name="body_text" class="form-textarea" data-sr-editor="ckeditor"></textarea>
                  </label>
                </p>
              </div>
              <div class="modal-footer"><button type="button" class="btn">닫기</button><button type="submit" class="btn btn-solid-primary">답글 작성</button></div>
            </form>
          </div>
        </div>
      </main>
    </body>
    </html>`);
  await page.locator('.community-comment-reply-source').evaluate((element, value) => { element.textContent = value; }, longSource);
  await page.addStyleTag({ path: path.join(root, 'modules/community/theme/basic/assets/reset.css') });
  await page.addStyleTag({ path: path.join(root, 'modules/community/theme/basic/assets/common.css') });
  await page.addStyleTag({ path: path.join(root, 'modules/ckeditor/vendor/ckeditor5/ckeditor5.css') });
  await page.addStyleTag({ path: path.join(root, 'modules/ckeditor/assets/saanraan-ckeditor.css') });
  await page.addStyleTag({ path: path.join(root, 'modules/community/theme/basic/assets/module.css') });
  // Initialize the bundled editor instead of approximating its block DOM with spans.
  await page.evaluate(() => {
    const config = document.createElement('script');
    config.type = 'application/json';
    config.id = 'sr-ckeditor-config';
    config.textContent = JSON.stringify({
      assetMode: 'self_hosted', licenseKey: 'GPL',
      selfHostedScriptUrl: '/modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js',
      toolbar: ['undo', 'redo', '|', 'insertImage', 'link', 'insertTable', '|', 'heading', 'fontSize', '|',
        'bold', 'italic', 'underline', 'strikethrough', '|', 'fontColor', 'fontBackgroundColor',
        'removeFormat', '|', 'alignment', 'horizontalLine', 'blockQuote', '|',
        'bulletedList', 'numberedList', 'outdent', 'indent'],
    });
    document.head.appendChild(config);
  });
  await page.addScriptTag({ path: path.join(root, 'modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js') });
  await page.addScriptTag({ path: path.join(root, 'modules/ckeditor/assets/saanraan-ckeditor.js') });
  await page.evaluate(() => window.srCkeditorEnhance());
  await expect(page.locator('#comment_body')).toHaveAttribute('data-sr-editor-ready', '1');
  await page.evaluate(() => window.srCkeditorInstances.comment_body.setData('<p>' + '긴 수정 본문 '.repeat(500) + '</p>'));


  const snapshot = async () => page.evaluate(() => {
    const content = document.querySelector('.community-comment-editor-dialog > .modal-content');
    const body = document.querySelector('.community-comment-editor-dialog .modal-body');
    const source = document.querySelector('.community-comment-reply-source');
    const editable = document.querySelector('.ck-editor__editable_inline');
    const plainEditor = document.querySelector('[data-plain-editor-fixture]');
    const buttons = Array.from(document.querySelectorAll('.ck-toolbar__items > .ck-button'));
    const contentRect = content.getBoundingClientRect();
    const sourceStyle = getComputedStyle(source);
    return {
      contentTop: contentRect.top,
      contentBottom: contentRect.bottom,
      contentWidth: contentRect.width,
      bodyOverflowY: getComputedStyle(body).overflowY,
      bodyClientWidth: body.clientWidth,
      bodyScrollWidth: body.scrollWidth,
      sourceClientHeight: source.clientHeight,
      sourceScrollHeight: source.scrollHeight,
      sourceBackground: sourceStyle.backgroundColor,
      sourceBorderWidth: sourceStyle.borderTopWidth,
      sourcePadding: sourceStyle.padding,
      sourceColor: sourceStyle.color,
      plainEditorWeight: getComputedStyle(plainEditor).fontWeight,
      editableWeight: getComputedStyle(editable).fontWeight,
      editableHeight: editable.getBoundingClientRect().height,
      toolbarRows: new Set(buttons.map((button) => Math.round(button.getBoundingClientRect().top))).size,
    };
  });

  const light = await snapshot();
  expect(light.contentTop).toBeGreaterThanOrEqual(0);
  expect(light.contentBottom).toBeLessThanOrEqual(789);
  expect(light.contentWidth).toBeLessThanOrEqual(528);
  expect(light.bodyOverflowY).toBe('auto');
  expect(light.bodyScrollWidth).toBeLessThanOrEqual(light.bodyClientWidth);
  expect(light.sourceScrollHeight).toBeGreaterThan(light.sourceClientHeight);
  expect(light.sourceClientHeight).toBeLessThanOrEqual(128);
  expect(light.editableHeight).toBeGreaterThanOrEqual(160);
  expect(light.editableHeight).toBeLessThanOrEqual(240);
  expect(light.plainEditorWeight).toBe('400');
  expect(light.editableWeight).toBe('400');
  expect(light.toolbarRows).toBeGreaterThan(1);
  expect(light.sourceBackground).toBe('rgba(0, 0, 0, 0)');
  expect(light.sourceBorderWidth).toBe('0px');
  expect(light.sourcePadding).toBe('0px');

  await page.locator('html').evaluate((element) => element.setAttribute('data-color-scheme', 'dark'));
  const dark = await snapshot();
  expect(dark.sourceBackground).toBe('rgba(0, 0, 0, 0)');
  expect(dark.sourceBorderWidth).toBe('0px');
  expect(dark.sourcePadding).toBe('0px');
  expect(dark.sourceColor).not.toBe(light.sourceColor);
  expect(dark.plainEditorWeight).toBe('400');
  expect(dark.editableWeight).toBe('400');
  expect(dark.bodyScrollWidth).toBeLessThanOrEqual(dark.bodyClientWidth);
});
