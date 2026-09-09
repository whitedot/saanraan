const path = require('path');
const { test, expect } = require('@playwright/test');
const root = path.resolve(__dirname, '../../..');
const storageKey = 'sr_admin_dashboard_section_order_v3';

async function dashboard(page, saved = null, hidden = []) {
  const sections = Array.from({ length: 8 }, (_, index) => `
    <section class="admin-dashboard-section admin-dashboard-module-section" data-admin-dashboard-section="module_${index}" data-admin-dashboard-default-visible="${hidden.includes(index) ? '0' : '1'}">
      <button class="admin-dashboard-section-handle admin-dashboard-module-section-handle" draggable="true" aria-label="Move card">⠿</button>
      <div class="card admin-dashboard-module-default"><div class="card-header"><h2 class="card-title">Module ${index}</h2></div>
        <dl class="admin-dashboard-module-stats">
          <div class="admin-dashboard-module-stat" data-admin-dashboard-emphasis="primary"><dt>Active members</dt><dd>1,284</dd><dd class="admin-dashboard-module-stat-detail">New members 32</dd></div>
          <div class="admin-dashboard-module-stat"><dt>Groups</dt><dd>8</dd></div>
        </dl>
      </div>
    </section>`).join('');
  await page.route('http://dashboard.test/**', route => route.fulfill({
    contentType: 'text/html',
    body: `<html data-theme="light" data-color-scheme="light"><body><main id="container"><details open><summary>Details</summary><div class="admin-dashboard-sections" data-admin-dashboard-sections><section class="card admin-dashboard-section" data-admin-dashboard-section="site">Site</section>${sections}</div></details></main></body></html>`,
  }));
  await page.goto('http://dashboard.test/');
  if (saved) await page.evaluate(({ key, value }) => localStorage.setItem(key, JSON.stringify(value)), { key: storageKey, value: saved });
  for (const file of ['tokens.css', 'common.css', 'admin.css']) {
    await page.addStyleTag({ path: path.join(root, 'modules/admin/assets', file) });
  }
  await page.addScriptTag({ path: path.join(root, 'modules/admin/assets/admin-dashboard.js') });
  await page.evaluate(() => window.AdminDashboard.init());
}

test('dashboard defaults mix row sizes, keep site full-width and exclude hidden modules', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await dashboard(page);
  const spans = () => page.locator('.admin-dashboard-module-section').evaluateAll(elements => elements.map(e => e.dataset.adminDashboardSpan));
  expect(await spans()).toEqual(['half', 'half', 'third', 'third', 'third', 'third', 'third', 'third']);
  await expect(page.locator('[data-admin-dashboard-section="site"]')).toHaveAttribute('data-admin-dashboard-span', 'full');
  // Rendering a default must not overwrite or manufacture a saved user arrangement.
  expect(await page.evaluate(key => localStorage.getItem(key), storageKey)).toBeNull();
  for (const theme of ['light', 'dark']) {
    await page.locator('html').evaluate((e, value) => { e.dataset.theme = value; e.dataset.colorScheme = value; }, theme);
    for (const width of [1440, 900, 390]) {
      await page.setViewportSize({ width, height: 1000 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      const large = page.locator('.admin-dashboard-module-section').first();
      await expect(large.locator('.admin-dashboard-module-stat dd:not(.admin-dashboard-module-stat-detail)').first()).toHaveCSS('font-size', '24px');
      const rows = await large.locator('.admin-dashboard-module-stat').evaluateAll(elements => elements.map(e => e.getBoundingClientRect().top));
      expect(rows[1]).toBeGreaterThan(rows[0]);
      await expect(large.locator('.card')).toHaveCSS('border-radius', '20px');
      await expect(large.locator('.admin-dashboard-module-stat-detail')).toHaveCSS('font-size', '12px');
      await large.locator('button').focus();
      await expect(large.locator('button')).toHaveCSS('outline-style', 'solid');
    }
  }
});

test('dashboard preserves saved full-width layout and order', async ({ page }) => {
  const saved = { items: ['site', ...Array.from({ length: 8 }, (_, i) => `module_${7 - i}`)].map(key => ({ key, span: 'full', auto_span: true })) };
  await dashboard(page, saved);
  expect(await page.locator('.admin-dashboard-module-section').evaluateAll(elements => elements.map(e => e.dataset.adminDashboardSection))).toEqual(saved.items.slice(1).map(item => item.key));
  expect(await page.locator('.admin-dashboard-module-section').evaluateAll(elements => elements.every(e => e.dataset.adminDashboardSpan === 'full'))).toBe(true);
  expect(await page.evaluate(key => JSON.parse(localStorage.getItem(key)), storageKey)).toEqual(saved);
});

test('dashboard default rows use only visible modules', async ({ page }) => {
  await dashboard(page, null, [1, 6]);
  const visible = page.locator('.admin-dashboard-module-section:visible');
  expect(await visible.evaluateAll(elements => elements.map(e => e.dataset.adminDashboardSpan))).toEqual(['half', 'half', 'third', 'third', 'third', 'full']);
  await expect(page.locator('[data-admin-dashboard-section="module_1"]')).toBeHidden();
  await expect(page.locator('[data-admin-dashboard-section="module_6"]')).toBeHidden();
});
