const path = require('path');
const { test, expect } = require('@playwright/test');
const root = path.resolve(__dirname, '../../..');

for (const base of ['assets', 'modules/admin/assets', 'modules/content/theme/basic/assets', 'modules/community/theme/basic/assets']) {
  test(`${base} cards share dashboard surfaces in light and dark`, async ({ page }) => {
    await page.setContent(`<html data-theme="light" data-color-scheme="light"><body>
      <section class="card" id="standard"><div class="card-header"><h2 class="card-title">Card title</h2></div><div class="card-body">Card content <button class="btn btn-solid-primary">Action</button></div><div class="card-footer">Footer</div></section>
      <section class="card table-card"><div class="card-header">Table title</div><div class="table-wrapper"><table class="table"><tr><td>Value</td></tr></table></div></section>
      <section class="card"><img class="card-img-top" alt="Sample"><div class="card-body">Image caption</div></section>
      <section class="card card-solid-primary card-inverse"><div class="card-header"><h2 class="card-title">Color variant</h2></div></section>
      <button class="btn btn-solid-primary" id="outside-button">Outside</button>
    </body></html>`);
    await page.addStyleTag({ path: path.join(root, base, base === 'modules/admin/assets' ? 'tokens.css' : 'reset.css') });
    await page.addStyleTag({ path: path.join(root, base, 'common.css') });
    if (base === 'modules/admin/assets') await page.addStyleTag({ path: path.join(root, base, 'admin.css') });
    for (const theme of ['light', 'dark']) {
      await page.locator('html').evaluate((e, t) => { e.dataset.theme = t; e.dataset.colorScheme = t; }, theme);
      for (const width of [1280, 390]) {
        await page.setViewportSize({ width, height: 900 });
        for (const card of await page.locator('.card').all()) {
          await expect(card).toHaveCSS('border-radius', '20px');
          await expect(card).not.toHaveCSS('box-shadow', 'none');
        }
        await expect(page.locator('#standard .card-header')).toHaveCSS('border-bottom-width', '0px');
        await expect(page.locator('#standard .card-body')).toHaveCSS('padding', '24px');
        await expect(page.locator('#standard .card-title')).toHaveCSS('font-size', '17px');
        await expect(page.locator('.card-img-top')).toHaveCSS('border-top-left-radius', '19px');
        await expect(page.locator('.table-card')).toHaveCSS('overflow', 'hidden');
        await expect(page.locator('.card-inverse .card-title')).toHaveCSS('color', 'rgb(255, 255, 255)');
        // Public actions use 8px corners; the independent admin kit keeps 4px.
        await expect(page.locator('#outside-button')).toHaveCSS('border-radius', base === 'modules/admin/assets' ? '4px' : '8px');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      }
    }
  });
}
