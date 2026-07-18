const path = require('path');
const { test, expect } = require('@playwright/test');

const repoRoot = path.resolve(__dirname, '..', '..', '..');

async function renderContentSidebar(page, scheme, width) {
  await page.setViewportSize({ width, height: 900 });
  const tokens = scheme === 'dark'
    ? '--sr-text:#f2f4f7;--sr-muted:#aeb7c4;--sr-border:#46505e;--color-primary:#8ab4ff;'
    : '--sr-text:#20242a;--sr-muted:#6b7280;--sr-border:#d8dde6;--color-primary:#315efb;';
  await page.setContent(`<!doctype html><html data-color-scheme="${scheme}"><head><style>:root{${tokens}}</style></head><body><div class="content-screen-frame"><main class="content-screen-main">본문</main><aside class="content-sidebar"><section class="card content-sidebar-section"><div class="card-body"><ul class="content-sidebar-list"><li><a href="#">사이드 링크</a><span>보조 정보</span></li></ul></div></section></aside></div></body></html>`);
  await page.addStyleTag({ path: path.join(repoRoot, 'modules/content/theme/basic/assets/common.css') });
  await page.addStyleTag({ path: path.join(repoRoot, 'modules/content/theme/basic/assets/module.css') });
}

test.describe('public module sidebar theme', () => {
  for (const fixture of [
    { scheme: 'light', text: 'rgb(32, 36, 42)', muted: 'rgb(107, 114, 128)' },
    { scheme: 'dark', text: 'rgb(242, 244, 247)', muted: 'rgb(174, 183, 196)' },
  ]) {
    test(`content sidebar follows ${fixture.scheme} tokens`, async ({ page }) => {
      await renderContentSidebar(page, fixture.scheme, 1366);
      const styles = await page.evaluate(() => {
        const frame = getComputedStyle(document.querySelector('.content-screen-frame'));
        const link = getComputedStyle(document.querySelector('.content-sidebar a'));
        const meta = getComputedStyle(document.querySelector('.content-sidebar-list span'));
        return { columns: frame.gridTemplateColumns, link: link.color, meta: meta.color };
      });
      expect(styles.columns.split(' ').length).toBeGreaterThan(1);
      expect(styles.link).toBe(fixture.text);
      expect(styles.meta).toBe(fixture.muted);
    });
  }

  test('content sidebar moves below the main column on narrow screens', async ({ page }) => {
    await renderContentSidebar(page, 'dark', 760);
    const styles = await page.evaluate(() => {
      const frame = getComputedStyle(document.querySelector('.content-screen-frame'));
      const aside = getComputedStyle(document.querySelector('.content-sidebar'));
      return { columns: frame.gridTemplateColumns, position: aside.position, border: aside.borderTopColor };
    });
    expect(styles.columns.split(' ')).toHaveLength(1);
    expect(styles.position).toBe('static');
    expect(styles.border).toBe('rgb(70, 80, 94)');
  });
});
