const { test, expect } = require('@playwright/test');
const { AxeBuilder } = require('@axe-core/playwright');
const fs = require('fs');
const path = require('path');

const adminIdentifier = process.env.SR_BROWSER_QA_ADMIN_IDENTIFIER || process.env.SR_M15_ADMIN_IDENTIFIER || 'admin';
const adminPassword = process.env.SR_BROWSER_QA_ADMIN_PASSWORD || process.env.SR_M15_ADMIN_PASSWORD || '12341234';
const screenshotDir = path.resolve(process.cwd(), '.tools/browser-qa/results/screenshots');

const forbiddenBodyNeedles = [
  'Fatal error',
  'Stack trace',
  'Parse error',
  'Warning:',
  '<?php',
  'CREATE TABLE',
  'password_hash VARCHAR',
  'ref: refs/',
];

const issueRoutes = {
  175: ['/admin'],
  176: ['/admin/settings', '/admin/homepage'],
  177: ['/admin/modules'],
  178: ['/admin/updates'],
  179: ['/admin/retention'],
  180: ['/admin/menu'],
  181: ['/admin/roles'],
  182: ['/admin/audit-logs'],
  183: ['/admin/members', '/admin/members/new', '/admin/members/search?q=admin', '/admin/member-settings', '/admin/member-groups', '/admin/member-groups/new', '/admin/member-group-rules', '/admin/member-group-assignments', '/admin/privacy-requests'],
  184: ['/admin/content', '/admin/content/new', '/admin/content/series', '/admin/content/settings', '/admin/content/files', '/admin/content/file-downloads', '/admin/content/asset-policy-sets', '/admin/content-groups', '/admin/content-groups/new'],
  185: ['/admin/community/settings', '/admin/community/asset-policy-sets', '/admin/community/levels', '/admin/community/boards', '/admin/community/boards/new', '/admin/community/board-groups', '/admin/community/board-groups/new', '/admin/community/posts', '/admin/community/comments', '/admin/community/reports', '/admin/community/series'],
  186: ['/admin/points', '/admin/points/settings', '/admin/points/adjust', '/admin/points/balances', '/admin/points/reference-search?q=qa', '/admin/points/transactions', '/admin/rewards', '/admin/rewards/settings', '/admin/rewards/adjust', '/admin/rewards/balances', '/admin/rewards/reference-search?q=qa', '/admin/rewards/withdrawal-requests', '/admin/rewards/transactions', '/admin/deposits', '/admin/deposits/settings', '/admin/deposits/adjust', '/admin/deposits/balances', '/admin/deposits/reference-search?q=qa', '/admin/deposits/refund-requests', '/admin/deposits/transactions', '/admin/asset-exchange', '/admin/asset-exchange/settings', '/admin/asset-exchange/logs'],
  187: ['/admin/coupons', '/admin/coupons/issues', '/admin/coupons/redemptions', '/admin/coupons/target-search?target_type=content&q=qa', '/admin/coupons/member-search?q=admin'],
  188: ['/admin/notifications', '/admin/notifications/new', '/admin/notification-deliveries', '/admin/notifications/settings'],
  189: ['/admin/banners', '/admin/banners/new', '/admin/banners/settings', '/admin/popup-layers', '/admin/popup-layers/new', '/admin/popup-layers/settings', '/admin/site-menus', '/admin/logo-manager', '/admin/seo', '/admin/ckeditor/settings'],
  190: ['/admin/ui-kit', '/admin', '/admin/members', '/admin/content', '/admin/community/posts'],
  191: ['/', '/robots.txt', '/sitemap.xml', '/assets/module.css', '/content/example', '/community', '/community/board?key=free'],
  192: ['/login', '/register', '/password/reset', '/password/reset/confirm?token=bad', '/email/verify?token=bad', '/email/verified', '/member/profile-image?account=missing'],
  193: ['/account', '/account/withdraw', '/account/privacy-requests'],
  194: ['/content/example', '/content/group?key=example', '/content/download?id=1'],
  195: ['/community', '/community/board?key=free', '/community/post?id=1', '/community/write?key=free', '/community/edit?id=1', '/community/attachment?id=1'],
  196: ['/community/series', '/community/scraps', '/messages', '/message?id=1', '/message/write'],
  197: ['/account/points', '/account/rewards', '/account/deposits', '/account/asset-exchange'],
  198: ['/account/coupons', '/content/example', '/community/post?id=1'],
  199: ['/account/notifications'],
  200: ['/', '/banner/image?file=missing', '/banner/click?id=1', '/logo-manager/image?file=missing', '/seo/image?file=missing', '/robots.txt', '/sitemap.xml'],
  201: ['/admin/ckeditor/settings', '/admin/content/new', '/community/write?key=free'],
  202: ['/register', '/community/write?key=free', '/admin/community/reports', '/admin/points/transactions', '/admin/coupons', '/account/privacy-requests', '/admin/audit-logs'],
};

const protectedPaths = [
  '/database/core/install.sql',
  '/modules/member/install.sql',
  '/modules/community/install.sql',
  '/modules/community/module.php',
  '/core/helpers.php',
  '/config/.gitignore',
  '/storage/.gitignore',
  '/docs/deployment-protection.md',
  '/examples/sample_module/module.php',
  '/AGENTS.md',
  '/README.md',
  '/.tools/bin/check.php',
  '/.git/HEAD',
];

const tier2CoreRoutes = ['/login', '/', '/community', '/admin', '/admin/ui-kit', '/account'];
const axeRoutes = ['/login', '/admin/ui-kit', '/admin', '/community', '/account'];

function isProtectedMemberPath(route) {
  return route.startsWith('/admin')
    || route.startsWith('/account')
    || route.startsWith('/community/write')
    || route.startsWith('/community/edit')
    || route.startsWith('/community/series')
    || route.startsWith('/community/scraps')
    || route.startsWith('/message');
}

async function login(page) {
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  const identifier = page.locator('input[name="identifier"]');
  if (await identifier.count() === 0) {
    return;
  }

  await identifier.fill(adminIdentifier);
  await page.locator('input[name="password"]').fill(adminPassword);
  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.locator('button[type="submit"], input[type="submit"]').first().click(),
  ]);
}

async function assertNoBrowserFailures(page, pathLabel, failures) {
  const text = await page.locator('body').textContent({ timeout: 5000 }).catch(() => '');
  for (const needle of forbiddenBodyNeedles) {
    expect(text, `${pathLabel} must not expose ${needle}`).not.toContain(needle);
  }

  expect(failures, `${pathLabel} browser errors`).toEqual([]);
}

async function visit(page, route) {
  const serverFailures = [];
  const runtimeFailures = [];
  const onResponse = (response) => {
    if (response.status() >= 500) {
      serverFailures.push(`${response.status()} ${response.url()}`);
    }
  };
  const onPageError = (error) => {
    runtimeFailures.push(error.message);
  };

  page.on('response', onResponse);
  page.on('pageerror', onPageError);
  let response = null;
  if (route.startsWith('/content/download')) {
    const downloadPromise = page.waitForEvent('download');
    let navigationError = null;
    response = await page.goto(route, { waitUntil: 'domcontentloaded' }).catch((error) => {
      navigationError = error;
      return null;
    });
    const download = await downloadPromise;

    expect(String(navigationError?.message || ''), `${route} should start a download`).toContain('Download is starting');
    expect(await download.failure(), `${route} download failure`).toBeNull();
    await download.delete().catch(() => {});
  } else {
    response = await page.goto(route, { waitUntil: 'domcontentloaded' });
  }
  await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
  page.off('response', onResponse);
  page.off('pageerror', onPageError);

  if (response !== null) {
    expect(response.status(), `${route} final status`).toBeLessThan(500);
  } else {
    expect(route, 'only an expected download may omit a navigation response').toMatch(/^\/content\/download(?:\?|$)/);
  }
  await assertNoBrowserFailures(page, route, [...serverFailures, ...runtimeFailures]);
}

test.describe('milestone 15 browser smoke', () => {
  test.beforeAll(() => {
    fs.mkdirSync(screenshotDir, { recursive: true });
  });

  for (const [issue, routes] of Object.entries(issueRoutes)) {
    test(`#${issue} actual browser route coverage`, async ({ browser }, testInfo) => {
      const context = await browser.newContext();
      const page = await context.newPage();

      if (routes.some(isProtectedMemberPath)) {
        await login(page);
      }

      for (const route of routes) {
        await visit(page, route);
      }

      await page.screenshot({
        path: path.join(screenshotDir, `issue-${issue}.png`),
        fullPage: true,
      });
      testInfo.annotations.push({ type: 'issue', description: `#${issue}` });
      await context.close();
    });
  }

  test('#190 mobile layout browser pass', async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
    const page = await context.newPage();
    await login(page);
    for (const route of ['/admin/ui-kit', '/admin', '/admin/members']) {
      await visit(page, route);
      await expect(page.locator('body')).toBeVisible();
    }
    await page.screenshot({
      path: path.join(screenshotDir, 'issue-190-mobile.png'),
      fullPage: true,
    });
    await context.close();
  });

  test('#191 protected files are not rendered in browser', async ({ page }) => {
    for (const route of protectedPaths) {
      const response = await page.goto(route, { waitUntil: 'domcontentloaded' });
      expect(response, `${route} should return a browser response`).not.toBeNull();
      expect([403, 404], `${route} status`).toContain(response.status());
      await assertNoBrowserFailures(page, route, []);
    }
  });

  test('tier 2 core smoke across alternate browsers', async ({ page }) => {
    await login(page);
    for (const route of tier2CoreRoutes) {
      await visit(page, route);
      await expect(page.locator('body')).toBeVisible();
    }
  });

  test('axe accessibility representative smoke', async ({ page }) => {
    test.setTimeout(120000);
    await login(page);
    for (const route of axeRoutes) {
      await visit(page, route);
      const results = await new AxeBuilder({ page }).analyze();
      const highImpactViolations = results.violations.filter((violation) => {
        if (violation.id === 'color-contrast') {
          return false;
        }

        return violation.impact === 'critical' || violation.impact === 'serious';
      });
      expect(highImpactViolations, `${route} serious/critical axe violations`).toEqual([]);
    }
  });
});
