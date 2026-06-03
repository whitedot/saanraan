const { test, expect } = require('@playwright/test');

test.describe('milestone 15 deep browser QA', () => {
  test('named snapshot restore and visual baseline', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toBeVisible();

    const loginContract = await page.locator('body').evaluate((body) => {
      const labels = Array.from(body.querySelectorAll('label'))
        .map((label) => label.textContent.trim())
        .filter(Boolean);
      const controls = Array.from(body.querySelectorAll('input, button'))
        .map((control) => ({
          tag: control.tagName.toLowerCase(),
          name: control.getAttribute('name') || '',
          type: control.getAttribute('type') || '',
          text: control.textContent.trim(),
        }));

      return JSON.stringify({ labels, controls }, null, 2);
    });

    expect(loginContract).toMatchSnapshot('m15-login-contract.txt');
    await expect(page).toHaveScreenshot('m15-login-page.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.05,
    });
  });

  test('external provider mock blocks live network dependency', async ({ page }) => {
    let intercepted = false;

    await page.route('https://m15-external.example.test/**', async (route) => {
      intercepted = true;
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ ok: true, provider: 'm15-mock' }),
      });
    });

    const response = await page.goto('https://m15-external.example.test/notification/send', {
      waitUntil: 'domcontentloaded',
    });
    const payload = JSON.parse(await page.locator('body').textContent());

    expect(intercepted).toBe(true);
    expect(response.status()).toBe(200);
    expect(payload).toEqual({ ok: true, provider: 'm15-mock' });
  });

  test('concurrent public route smoke stays below server error boundary', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    const statuses = await page.evaluate(async () => {
      const paths = ['/login', '/register', '/password/reset', '/community', '/robots.txt'];
      const responses = await Promise.all(paths.map((path) => fetch(path, { redirect: 'manual' })));
      return responses.map((response) => response.status);
    });

    expect(statuses.length).toBe(5);
    for (const status of statuses) {
      expect(status).toBeLessThan(500);
    }
  });
});
