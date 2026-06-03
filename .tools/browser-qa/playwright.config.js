const baseURL = process.env.SR_BROWSER_QA_BASE_URL || process.env.SR_SMOKE_BASE_URL || 'http://127.0.0.1:8080';

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
    browserName: 'chromium',
    channel: 'chrome',
    headless: true,
    viewport: { width: 1366, height: 900 },
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    video: 'off',
    trace: 'off',
  },
};
