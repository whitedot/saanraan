const path = require('path');
const { test, expect } = require('@playwright/test');

const root = path.resolve(__dirname, '../../..');
const fixtures = [
  ['content basic', 'content-comments-pagination', 'modules/content/theme/basic/assets/module.css'],
  ['quiz basic', 'quiz-comments-pagination', 'modules/quiz/theme/basic/assets/module.css'],
  ['survey basic', 'survey-comments-pagination', 'modules/survey/theme/basic/assets/module.css'],
];

for (const [name, className, stylesheet] of fixtures) {
  test(`${name} comment pagination keeps computed theme colors`, async ({ page }) => {
    await page.setContent(`<!doctype html>
      <html lang="ko" data-color-scheme="light">
      <head><style>
        :root {
          --sr-text: #172033;
          --sr-border: #ccd4e0;
          --sr-surface: #ffffff;
        }
        :root[data-color-scheme="dark"] {
          --sr-text: #e5ecf5;
          --sr-border: #475569;
          --sr-surface: #111827;
        }
      </style></head>
      <body><nav class="${className}"><a href="#">2</a><span aria-current="page">3</span></nav></body>
      </html>`);
    await page.addStyleTag({ path: path.join(root, stylesheet) });

    const computed = async () => page.evaluate(() => {
      const link = getComputedStyle(document.querySelector('a'));
      const current = getComputedStyle(document.querySelector('[aria-current="page"]'));
      return {
        linkColor: link.color,
        linkBorder: link.borderTopColor,
        currentColor: current.color,
        currentBackground: current.backgroundColor,
        currentBorder: current.borderTopColor,
      };
    });

    expect(await computed()).toEqual({
      linkColor: 'rgb(23, 32, 51)',
      linkBorder: 'rgb(204, 212, 224)',
      currentColor: 'rgb(255, 255, 255)',
      currentBackground: 'rgb(23, 32, 51)',
      currentBorder: 'rgb(23, 32, 51)',
    });

    await page.locator('html').evaluate((element) => element.setAttribute('data-color-scheme', 'dark'));
    expect(await computed()).toEqual({
      linkColor: 'rgb(229, 236, 245)',
      linkBorder: 'rgb(71, 85, 105)',
      currentColor: 'rgb(17, 24, 39)',
      currentBackground: 'rgb(229, 236, 245)',
      currentBorder: 'rgb(229, 236, 245)',
    });
  });
}
