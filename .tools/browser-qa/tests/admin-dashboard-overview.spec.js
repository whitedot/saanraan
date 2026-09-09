const path = require('path');
const { test, expect } = require('@playwright/test');
const { overviewDocument } = require('../fixtures/admin-dashboard-overview');
const root = path.resolve(__dirname, '../../..');

async function load(page, options = {}, visibility = {}) {
  await page.route('http://dashboard.test/**', route => route.fulfill({ contentType: 'text/html', body: overviewDocument(options) }));
  await page.goto('http://dashboard.test/');
  await page.evaluate(value => localStorage.setItem('sr_admin_dashboard_section_visibility', JSON.stringify(value)), visibility);
  for (const name of ['tokens.css', 'common.css', 'admin.css']) await page.addStyleTag({ path: path.join(root, 'modules/admin/assets', name) });
  await page.addStyleTag({ content: 'body{background:var(--color-body-bg);color:var(--color-body-color)}*{transition:none!important}' });
  await page.addScriptTag({ path: path.join(root, 'modules/admin/assets/admin-dashboard.js') });
  await page.evaluate(() => window.AdminDashboard.init());
}

test('overview counts only explicit task metrics and keeps details available', async ({ page }) => {
  await load(page);
  await expect(page.locator('[data-admin-overview-task-summary]')).toHaveText('24건 대기');
  await expect(page.locator('[data-admin-overview-task-count]')).toHaveCount(4);
  await expect(page.locator('.admin-dashboard-service-metric')).toHaveCount(3);
  await expect(page.locator('.admin-dashboard-activity-item')).toHaveCount(3);
  await expect(page.locator('.admin-dashboard-details')).not.toHaveAttribute('open', '');
  await page.locator('.admin-dashboard-details>summary').click();
  await expect(page.locator('[data-admin-dashboard-section="site"]')).toBeVisible();
  await expect(page.locator('.admin-dashboard-task-row').first()).toHaveAttribute('href', '/admin/content/submissions?status%5B%5D=pending_review');
});

test('saved visibility and live settings also update the overview and cancel restores it', async ({ page }) => {
  await load(page, {}, { module_content: false });
  await expect(page.locator('[data-admin-overview-task-summary]')).toHaveText('12건 대기');
  await expect(page.locator('.admin-dashboard-service-metric[data-admin-overview-item="module_content"]')).toBeHidden();
  await page.getByRole('button', { name: '설정', exact: true }).click();
  await page.getByRole('checkbox', { name: '커뮤니티 표시', exact: true }).uncheck();
  await expect(page.locator('[data-admin-overview-task-summary]')).toHaveText('5건 대기');
  await page.getByRole('button', { name: '변경취소', exact: true }).click();
  await expect(page.locator('[data-admin-overview-task-summary]')).toHaveText('12건 대기');
});

for (const options of [{ zero: true }, { unknown: true }, { noItems: true }]) {
  test(`overview handles ${JSON.stringify(options)} without claiming unknown counts are clear`, async ({ page }) => {
    await load(page, options);
    const summary = page.locator('[data-admin-overview-task-summary]');
    if (options.zero) {
      await expect(summary).toHaveText('대기 업무 없음');
      await expect(summary).not.toHaveClass(/badge-soft-warning/);
      await expect(page.locator('[data-admin-overview-task-count]')).toHaveCount(4);
    } else if (options.unknown) {
      await expect(summary).toHaveText('12건 대기 · 1개 항목 확인 필요');
      await expect(page.locator('[data-admin-overview-task-count=""] strong')).toHaveText('—');
    } else {
      await expect(summary).toHaveText('표시 항목 없음');
      await expect(page.locator('[data-admin-overview-task-empty]')).toBeVisible();
      await expect(page.locator('.admin-dashboard-service-strip')).toBeHidden();
      await expect(page.locator('.admin-dashboard-assets-card')).toBeHidden();
      await expect(page.locator('.admin-dashboard-activity-card')).toBeHidden();
    }
  });
}

test('overview themes and responsive layout preserve readable text and keyboard actions', async ({ page }) => {
  await load(page);
  for (const theme of ['light', 'dark']) {
    await page.locator('html').evaluate((e, value) => { e.dataset.theme = value; e.dataset.colorScheme = value; }, theme);
    for (const width of [1440, 900, 390]) {
      await page.setViewportSize({ width, height: 1000 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      if (width > 960) {
        const widths = await page.locator('.admin-dashboard-work-grid > .card').evaluateAll(elements => elements.map(e => e.getBoundingClientRect().width));
        expect(Math.abs(widths[0] - widths[1])).toBeLessThan(1);
        const heights = await page.locator('.admin-dashboard-work-grid > .card').evaluateAll(elements => elements.map(e => e.getBoundingClientRect().height));
        expect(Math.abs(heights[0] - heights[1])).toBeLessThan(1);
      }
      await expect(page.locator('.admin-dashboard-asset-name')).toHaveText(['포인트 총 잔액', '적립금 총 잔액', '예치금 총 잔액', '쿠폰 지급']);
      await expect(page.locator('.admin-dashboard-asset-row > strong')).toHaveText(['12,480,000', '320,000', '840,000', '3,120']);
      const link = page.locator('.admin-dashboard-task-row').first();
      await link.focus();
      await expect(link).toHaveCSS('outline-style', 'solid');
      await expect(page.locator('.admin-dashboard-service-strip')).not.toHaveClass(/\bcard\b/);
      await expect(page.locator('#container > .admin-dashboard-overview')).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
      await expect(page.locator('.admin-dashboard-overview')).toHaveCSS('box-shadow', 'none');
      await expect(page.locator('.admin-dashboard-overview')).toHaveCSS('border-radius', '0px');
      await expect(page.locator('.admin-dashboard-overview')).toHaveCSS('display', 'grid');
      await expect(page.locator('.admin-dashboard-service-metric.card')).toHaveCount(3);
      await expect(page.locator('.admin-dashboard-overview .card')).toHaveCount(6);
      await expect(page.locator('.admin-dashboard-work-card')).toHaveCSS('border-radius', '20px');
      const text = await link.evaluate(e => ({ color: getComputedStyle(e).color, background: getComputedStyle(e.closest('.card')).backgroundColor }));
      expect(text.color).not.toBe(text.background);
    }
  }
});

test('recovery link reveals its detail even when the saved section was hidden', async ({ page }) => {
  await load(page, { recovery: true }, { recovery: false });
  await expect(page.locator('[data-admin-dashboard-section="recovery"]')).toBeHidden();
  await page.getByRole('link', { name: '복구 항목 보기' }).click();
  await expect(page.locator('[data-admin-dashboard-section="recovery"]')).toBeVisible();
  expect(await page.evaluate(() => JSON.parse(localStorage.getItem('sr_admin_dashboard_section_visibility')).recovery)).toBe(true);
});
