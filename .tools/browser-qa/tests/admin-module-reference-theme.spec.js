const path = require('path');
const { test, expect } = require('@playwright/test');

const root = path.resolve(__dirname, '../../..');

test('admin module references keep theme colors, label underline, and responsive wrapping', async ({ page }) => {
  await page.setContent(`<!doctype html>
    <html lang="ko" data-theme="light">
    <head></head>
    <body>
      <form class="admin-form">
        <div class="form-row">
          <div class="form-field">
            <p class="form-help">기존 안내문구입니다.</p>
            <ul class="form-help form-help-info form-help-reference-list">
              <li>
                <a href="#">
                  <span class="material-symbols-rounded form-help-reference-icon" aria-hidden="true">extension</span>
                  <span class="form-help-reference-label">사이트 메뉴</span>
                </a>
              </li>
              <li>
                <a href="#">
                  <span class="material-symbols-rounded form-help-reference-icon" aria-hidden="true">extension</span>
                  <span class="form-help-reference-label">콘텐츠 &gt; 콘텐츠 관리</span>
                </a>
              </li>
            </ul>
          </div>
        </div>
      </form>
    </body>
    </html>`);
  await page.addStyleTag({ path: path.join(root, 'modules/admin/assets/tokens.css') });
  await page.addStyleTag({ path: path.join(root, 'modules/admin/assets/common.css') });
  await page.addStyleTag({ path: path.join(root, 'modules/admin/assets/admin.css') });

  const styles = async () => page.evaluate(() => {
    const list = getComputedStyle(document.querySelector('.form-help-reference-list'));
    const firstItem = getComputedStyle(document.querySelector('.form-help-reference-list > li'));
    const link = getComputedStyle(document.querySelector('.form-help-reference-list a'));
    const icon = getComputedStyle(document.querySelector('.form-help-reference-icon'));
    const label = getComputedStyle(document.querySelector('.form-help-reference-label'));
    return {
      listColor: list.color,
      listDisplay: list.display,
      listDirection: list.flexDirection,
      listMarginTop: list.marginTop,
      itemFlexBasis: firstItem.flexBasis,
      linkColor: link.color,
      linkDecoration: link.textDecorationLine,
      iconDecoration: icon.textDecorationLine,
      labelDecoration: label.textDecorationLine,
    };
  });

  const light = await styles();
  expect(light.listDisplay).toBe('flex');
  expect(light.listDirection).toBe('row');
  expect(light.listMarginTop).toBe('8px');
  expect(light.itemFlexBasis).toBe('auto');
  expect(light.linkColor).toBe(light.listColor);
  expect(light.linkDecoration).toBe('none');
  expect(light.iconDecoration).toBe('none');
  expect(light.labelDecoration).toBe('underline');

  await page.locator('html').evaluate((element) => element.setAttribute('data-theme', 'dark'));
  const dark = await styles();
  expect(dark.listColor).toBe(light.listColor);
  expect(dark.linkColor).toBe(dark.listColor);
  expect(dark.iconDecoration).toBe('none');
  expect(dark.labelDecoration).toBe('underline');

  await page.setViewportSize({ width: 600, height: 800 });
  await expect.poll(async () => (await styles()).itemFlexBasis).toBe('100%');
});
