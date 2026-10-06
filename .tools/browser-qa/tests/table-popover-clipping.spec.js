const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('@playwright/test');
const root = path.resolve(__dirname, '../../..');
const kits = [
  ['assets', 'layouts/public/basic/ui-kit-samples'],
  ['modules/admin/assets', 'modules/admin/views/ui-kit-samples'],
  ['modules/content/theme/basic/assets', 'modules/content/views/ui-kit-samples'],
  ['modules/community/theme/basic/assets', 'modules/community/views/ui-kit-samples'],
];
for (const [base, samples] of kits) {
  test(`${base} table menus escape horizontal scrolling in light and dark`, async ({ page }) => {
    const html = execFileSync(process.env.PHP_BINARY || 'php', ['-r', `define('SR_ROOT', getcwd()); require 'core/helpers.php'; require 'modules/admin/helpers.php'; include '${samples}/tables-static.php';`], { cwd: root, encoding: 'utf8' });
    await page.setContent(`<html data-color-scheme="light"><body>${html}</body></html>`);
    for (const file of [base === 'modules/admin/assets' ? 'tokens.css' : 'reset.css', 'common.css', 'ui-kit-layout.css']) {
      await page.addStyleTag({ path: path.join(root, base, file) });
    }
    await page.addStyleTag({ path: path.join(root, 'modules/member/assets/public-identity.css') });
    // Exercise the real member-owned markup and runtime inside the same table.
    await page.locator('tbody td').first().evaluate(e => {
      e.innerHTML = `<details class="member-profile-menu"><summary>회원 팝오버</summary><div class="member-profile-menu-dropdown"><a class="member-profile-menu-item" href="#profile">프로필</a><button class="member-profile-menu-item">팔로우 예시</button></div></details>`;
    });
    await page.addScriptTag({ path: path.join(root, 'assets/common-ui.js') });
    await page.addScriptTag({ path: path.join(root, 'modules/member/assets/profile-menu.js') });
    // A transformed shell would also clip a plain fixed-position descendant.
    await page.addStyleTag({ content: '.table-card { transform: translateZ(0); } .table-wrapper { max-height: 90px; }' });
    for (const scheme of ['light', 'dark']) {
      await page.locator('html').evaluate((e, s) => { e.dataset.colorScheme = s; e.dataset.theme = s; }, scheme);
      for (const width of [390, 1280]) {
        await page.setViewportSize({ width, height: 900 });
        if (width === 1280) {
          for (const filter of await page.locator('.filtering-fields').all()) {
            const boxes = await filter.locator('input, select').evaluateAll(es => es.map(e => ({ top: e.getBoundingClientRect().top, height: e.getBoundingClientRect().height })));
            expect(new Set(boxes.map(b => b.top)).size).toBe(1);
            expect(new Set(boxes.map(b => b.height)).size).toBe(1);
          }
        }
        for (const [trigger, panel] of [['.member-profile-menu summary', '.member-profile-menu-dropdown'], ['tbody .dropdown-toggle', 'tbody .dropdown-menu']]) {
          await page.locator(trigger).first().click();
          await expect(page.locator(panel).first()).toBeVisible();
          await expect(page.locator(panel).first()).toHaveJSProperty('popover', 'manual');
          const visible = await page.locator(panel).first().evaluate(e => {
            const r = e.getBoundingClientRect();
            const clip = e.closest('.table-wrapper').getBoundingClientRect();
            const escapes = r.bottom > clip.bottom || r.top < clip.top;
            const hit = document.elementFromPoint(r.left + r.width / 2, r.bottom - 8);
            return { escapes, topLayer: e.matches(':popover-open'), hit: e.contains(hit), within: r.left >= 0 && r.right <= innerWidth && r.top >= 0 && r.bottom <= innerHeight };
          });
          expect(visible).toEqual({ escapes: true, topLayer: true, hit: true, within: true });
          await page.keyboard.press('Escape');
          await expect(page.locator(panel).first()).not.toBeVisible();
        }
        expect(await page.locator('.table-wrapper').evaluate(e => getComputedStyle(e).overflowX)).toBe('auto');
        if (width === 390) expect(await page.locator('.table-wrapper').evaluate(e => e.scrollWidth > e.clientWidth)).toBe(true);
      }
    }
    await page.locator('.member-profile-menu summary').click();
    await expect(page.locator('.member-profile-menu-dropdown')).toBeVisible();
    // A delayed focus-scroll notification must not dismiss a newly placed menu.
    await page.evaluate(() => window.dispatchEvent(new Event('scroll')));
    await expect(page.locator('.member-profile-menu-dropdown')).toBeVisible();
    await expect(page.locator('.member-profile-menu-dropdown')).toHaveJSProperty('popover', 'manual');
    await page.keyboard.press('Escape');
    await expect(page.locator('.member-profile-menu summary')).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('.member-profile-menu-dropdown')).toBeVisible();
    await page.locator('.table-wrapper').evaluate(e => { e.scrollTop += 30; });
    await expect(page.locator('.member-profile-menu')).not.toHaveAttribute('open', '');
    await page.locator('.member-profile-menu summary').click();
    await expect(page.locator('.member-profile-menu-dropdown')).toBeVisible();
    await page.getByRole('heading', { name: '회원 목록', exact: true }).click();
    await expect(page.locator('.member-profile-menu')).not.toHaveAttribute('open', '');
  });
}
