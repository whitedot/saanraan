const baseURL = process.env.SR_BROWSER_QA_BASE_URL || process.env.SR_SMOKE_BASE_URL || 'http://127.0.0.1:8080';
const chromiumChannel = process.env.SR_BROWSER_QA_CHROMIUM_CHANNEL || '';
const chromiumUse = {
  browserName: 'chromium',
  ...(chromiumChannel !== '' ? { channel: chromiumChannel } : {}),
};

module.exports = {
  testDir: './tests',
  timeout: 45000,
  expect: {
    timeout: 5000,
  },
  workers: 1,
  retries: 0,
  reporter: [
    ['list'],
    ['json', { outputFile: 'results/milestone-15-browser-results.json' }],
  ],
  outputDir: 'test-results',
  use: {
    baseURL,
    headless: true,
    viewport: { width: 1366, height: 900 },
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    video: 'off',
    trace: 'off',
  },
  projects: [
    {
      name: 'chromium-full',
      grepInvert: /milestone 15 deep browser QA/,
      use: chromiumUse,
    },
    {
      name: 'firefox-core',
      grep: /tier 2 core smoke/,
      use: {
        browserName: 'firefox',
      },
    },
    {
      name: 'webkit-core',
      grep: /tier 2 core smoke/,
      use: {
        browserName: 'webkit',
      },
    },
    {
      name: 'chromium-m15-deep',
      grep: /milestone 15 deep browser QA/,
      use: chromiumUse,
    },
  ],
};
