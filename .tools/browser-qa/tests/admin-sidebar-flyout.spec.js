const path = require('path');
const { test, expect } = require('@playwright/test');
const root = path.resolve(__dirname, '../../..');

for (const theme of ['light', 'dark']) {
  test(`collapsed admin sidebar keeps long submenu accessible in ${theme}`, async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 600 });
    await page.route('http://sidebar.test/**', route => route.fulfill({ contentType: 'text/html', body: `
      <html data-theme="${theme}" data-color-scheme="${theme}"><body>
      <header id="hd"><nav id="gnb"><h2>Admin</h2><div class="gnb_menu_scroll_wrap"><div id="gnbMenuScroll" class="gnb_menu_scroll">
      <ul id="adminNavList" class="admin-nav-list"><li style="height:320px"></li><li class="admin-nav-item">
      <button class="admin-nav-trigger"><span class="admin-nav-trigger-main">Community</span></button>
      <div class="admin-nav-panel hidden"><ul class="admin-nav-sub-list"><li class="admin-nav-sub-heading">Community</li>
      ${Array.from({ length: 35 }, (_, i) => `<li class="admin-nav-sub-item"><a href="#item-${i}">Menu ${i}</a></li>`).join('')}
      </ul></div></li></ul></div></div></nav></header><div id="wrapper"><main id="container"><button id="btn_gnb">Toggle</button></main></div>
      </body></html>` }));
    await page.goto('http://sidebar.test/');
    for (const file of ['tokens.css', 'common.css', 'admin.css']) await page.addStyleTag({ path: path.join(root, 'modules/admin/assets', file) });
    await page.evaluate(() => localStorage.setItem('sr_admin_sidebar_collapsed', '1'));
    await page.addScriptTag({ path: path.join(root, 'modules/admin/assets/admin-shell.js') });
    await page.evaluate(() => window.AdminShell.init());
    const trigger = page.locator('.admin-nav-trigger');
    const panel = page.locator('.admin-nav-panel');
    const list = panel.locator('ul');
    const last = panel.locator('a').last();
    await trigger.hover();
    await expect(panel).toHaveCSS('visibility', 'visible');
    for (const height of [600, 420]) {
      await page.setViewportSize({ width: 1280, height });
      await trigger.focus();
      await expect.poll(async () => panel.evaluate(e => { const r = e.getBoundingClientRect(); return r.top >= 7 && r.bottom <= innerHeight - 7; })).toBe(true);
      expect(await list.evaluate(e => e.scrollHeight > e.clientHeight)).toBe(true);
      await last.focus();
      await expect(last).toBeInViewport();
      expect(await last.evaluate(e => { const r = e.getBoundingClientRect(); return e.contains(document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2)); })).toBe(true);
      await last.click();
      await expect(page).toHaveURL(/#item-34$/);
      const colors = await list.evaluate(e => { const s = getComputedStyle(e); return [s.backgroundColor, s.color]; });
      expect(colors[0]).not.toBe(colors[1]);
    }
    await page.locator('#btn_gnb').focus();
    await page.locator('#btn_gnb').press('Enter');
    await trigger.click();
    await expect(panel).toHaveCSS('position', 'static');
    await expect(list).toHaveCSS('max-height', 'none');
    await page.setViewportSize({ width: 390, height: 700 });
    await expect(page.locator('body')).not.toHaveClass(/admin-sidebar-condensed/);
    await expect(panel).toHaveCSS('position', 'static');
  });
}
