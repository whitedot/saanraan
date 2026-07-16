const path = require('path');
const { test, expect } = require('@playwright/test');

const root = path.resolve(__dirname, '../../..');

test('admin manual draft controls keep action order and theme-aware surfaces', async ({ page }) => {
  await page.setContent(`<!doctype html>
    <html lang="ko" data-theme="light">
    <head></head>
    <body style="background:var(--color-body-bg);color:var(--color-body-color)">
      <form class="admin-form">
        <div class="alert alert-info admin-form-draft-status">
          <div><strong>임시저장본을 불러왔습니다.</strong><span>마지막 임시저장: 방금 전</span></div>
        </div>
        <div class="form-sticky-actions form-actions form-actions-primary">
          <button type="submit" class="btn btn-solid-primary admin-form-final-save">저장</button>
          <button type="submit" class="btn btn-solid-light admin-form-draft-save">임시저장</button>
          <button type="submit" class="btn btn-outline-danger admin-form-draft-delete">임시저장 삭제</button>
        </div>
      </form>
    </body>
    </html>`);
  await page.addStyleTag({ path: path.join(root, 'modules/admin/assets/tokens.css') });
  await page.addStyleTag({ path: path.join(root, 'modules/admin/assets/common.css') });
  await page.addStyleTag({ path: path.join(root, 'modules/admin/assets/admin.css') });

  const styles = async () => page.evaluate(() => {
    const body = getComputedStyle(document.body);
    const status = getComputedStyle(document.querySelector('.admin-form-draft-status'));
    return {
      bodyBackground: body.backgroundColor,
      bodyColor: body.color,
      statusBackground: status.backgroundColor,
      statusColor: status.color,
      statusDirection: status.flexDirection,
      finalOrder: getComputedStyle(document.querySelector('.admin-form-final-save')).order,
      draftOrder: getComputedStyle(document.querySelector('.admin-form-draft-save')).order,
      deleteOrder: getComputedStyle(document.querySelector('.admin-form-draft-delete')).order,
    };
  });

  const light = await styles();
  expect(light.finalOrder).toBe('3');
  expect(light.draftOrder).toBe('1');
  expect(light.deleteOrder).toBe('2');
  expect(light.statusDirection).toBe('row');
  expect(light.statusBackground).not.toBe('rgba(0, 0, 0, 0)');
  expect(light.statusColor).not.toBe(light.statusBackground);

  await page.locator('html').evaluate((element) => element.setAttribute('data-theme', 'dark'));
  const dark = await styles();
  expect(dark.bodyBackground).not.toBe(light.bodyBackground);
  expect(dark.bodyColor).not.toBe(light.bodyColor);
  expect(dark.statusBackground).not.toBe('rgba(0, 0, 0, 0)');
  expect(dark.statusColor).not.toBe(dark.statusBackground);

  await page.setViewportSize({ width: 600, height: 800 });
  await expect.poll(async () => (await styles()).statusDirection).toBe('column');
});
