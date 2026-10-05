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
function renderSamples(directory) {
  return execFileSync(process.env.PHP_BINARY || 'php', ['-r', `define('SR_ROOT', getcwd()); require 'core/helpers.php'; foreach (['ui-compositions', 'ui-cards', 'form-elements', 'form-validation', 'ui-modals'] as $sample) { include '${directory}/' . $sample . '.php'; }`], { cwd: root, encoding: 'utf8', maxBuffer: 4 * 1024 * 1024 });
}
for (const [base, samples] of kits) {
  test(`${base} kit quality: sample names, layout and conservative contrast`, async ({ page }) => {
    await page.setContent(`<html data-color-scheme="light"><body>${renderSamples(samples)}
      <button id="text-action" class="btn btn-text btn-ghost-default">수정</button>
      <button id="warning" class="btn btn-solid-warning">주의</button>
      <button id="info" class="btn btn-solid-info">안내</button>
    </body></html>`);
    for (const file of [base === 'modules/admin/assets' ? 'tokens.css' : 'reset.css', 'common.css', 'ui-kit-layout.css']) {
      await page.addStyleTag({ path: path.join(root, base, file) });
    }
    // Static HTML owns its accessible names, with no post-render naming script.
    const unnamed = await page.locator('input, select, textarea').evaluateAll(elements => elements.filter(e =>
      !['hidden', 'button', 'submit', 'reset'].includes(e.type) &&
      !e.labels?.length && !e.getAttribute('aria-label') && !e.getAttribute('aria-labelledby')
    ).map(e => e.outerHTML));
    expect(unnamed).toEqual([]);
    for (const scheme of ['light', 'dark']) {
      await page.locator('html').evaluate((e, s) => { e.dataset.colorScheme = s; e.dataset.theme = s; }, scheme);
      for (const width of [390, 1280]) {
        await page.setViewportSize({ width, height: 900 });
        // Scope to newly composed examples: legacy huge tables have their own overflow contract.
        for (const section of await page.locator('[data-ui-kit-sample="ui-compositions"], [data-ui-kit-sample="ui-cards"], [data-ui-kit-sample="form-elements"]').all()) {
          expect(await section.evaluate(e => e.scrollWidth <= e.clientWidth + 1)).toBe(true);
        }
        await expect(page.locator('#text-action')).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
        await page.locator('#text-action').hover();
        await expect(page.locator('#text-action')).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
        await expect(page.locator('.badge-status').first()).toHaveCSS('border-radius', '999px');
        for (const selector of ['#warning', '#info', ...(scheme === 'dark' ? ['.badge-status.is-info', '.badge-status.is-success', '.badge-status.is-warning'] : [])]) {
          for (const state of ['rest', 'hover']) {
            if (state === 'hover') await page.locator(selector).hover();
            else await page.mouse.move(0, 0);
            // Finish CSS transition before sampling the resulting colors.
            await page.locator(selector).evaluate(e => Promise.all(e.getAnimations().map(a => a.finished)));
            const ratio = await page.locator(selector).evaluate(e => {
              const c = document.createElement('canvas'); c.width = c.height = 1;
              const ctx = c.getContext('2d');
              const luminance = color => {
                ctx.fillStyle = color; ctx.fillRect(0, 0, 1, 1);
                const rgb = [...ctx.getImageData(0, 0, 1, 1).data].slice(0, 3).map(v => { v /= 255; return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; });
                return rgb[0] * .2126 + rgb[1] * .7152 + rgb[2] * .0722;
              };
              const s = getComputedStyle(e), a = luminance(s.color), b = luminance(s.backgroundColor);
              return (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
            });
            expect(ratio, `${base} ${scheme} ${selector} ${state}`).toBeGreaterThanOrEqual(4.5);
          }
        }
      }
    }
  });
}

test('overlay keyboard stays in the top dialog and restores its trigger', async ({ page }) => {
  await page.setContent(`<button id="trigger" data-overlay="#dialog">Open</button><button id="background">Background</button>
    <div id="dialog" class="overlay hidden" role="dialog" aria-hidden="true" inert tabindex="-1">
      <button id="first" data-overlay="#dialog">Close</button><button hidden>Hidden</button><fieldset disabled><button>Disabled fieldset</button></fieldset>
      <button id="stack" data-overlay="#nested" data-overlay-stack="true">Nested</button><button id="last">Last</button>
    </div>
    <div id="nested" class="overlay hidden" role="dialog" aria-hidden="true" inert tabindex="-1" data-overlay-static="true"><button id="nested-close" data-overlay="#nested">Close nested</button></div>`);
  await page.addStyleTag({ content: '.hidden { display: none; }' });
  await page.addScriptTag({ path: path.join(root, 'assets/common-ui.js') });
  await page.locator('#trigger').click();
  await expect(page.locator('#first')).toBeFocused();
  await expect(page.locator('#dialog')).toHaveAttribute('aria-modal', 'true');
  await page.keyboard.press('Shift+Tab');
  await expect(page.locator('#last')).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(page.locator('#first')).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(page.locator('#stack')).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('#nested-close')).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(page.locator('#nested-close')).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(page.locator('#nested')).toHaveAttribute('aria-hidden', 'false');
  await expect(page.locator('#dialog')).toHaveAttribute('aria-hidden', 'false');
  await page.keyboard.press('Enter');
  await expect(page.locator('#stack')).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(page.locator('#trigger')).toBeFocused();
  await expect(page.locator('#dialog')).toHaveAttribute('aria-hidden', 'true');
  await expect(page.locator('#dialog')).not.toHaveAttribute('aria-modal', 'true');
});
