const path = require('path');
const { test, expect } = require('@playwright/test');

const root = path.resolve(__dirname, '../../..');
const fixtures = [
  ['community basic', 'community-comments-pagination', 'modules/community/theme/basic/assets/common.css', 'modules/community/theme/basic/assets/module.css'],
  ['content basic', 'content-comments-pagination', 'modules/content/theme/basic/assets/common.css', 'modules/content/theme/basic/assets/module.css'],
  ['quiz basic', 'quiz-comments-pagination', 'modules/quiz/theme/basic/assets/common.css', 'modules/quiz/theme/basic/assets/module.css'],
  ['survey basic', 'survey-comments-pagination', 'modules/survey/theme/basic/assets/common.css', 'modules/survey/theme/basic/assets/module.css'],
];

for (const [name, className, commonStylesheet, stylesheet] of fixtures) {
  test(`${name} comment pagination keeps computed theme colors`, async ({ page }) => {
    await page.setContent(`<!doctype html>
      <html lang="ko" data-color-scheme="light">
      <head><style>
        :root[data-color-scheme="light"] {
          --sr-text: #172033;
          --sr-border: #ccd4e0;
          --sr-surface: #ffffff;
          --color-default-700: #172033;
          --color-primary: #172033;
          --color-white: #ffffff;
        }
        :root[data-color-scheme="dark"] {
          --sr-text: #e5ecf5;
          --sr-border: #475569;
          --sr-surface: #111827;
          --color-default-700: #e5ecf5;
          --color-primary: #e5ecf5;
          --color-white: #111827;
        }
      </style></head>
      <body><nav class="${className}"><a class="btn btn-ghost-default" href="#">2</a><span class="btn btn-solid-primary" aria-current="page">3</span></nav></body>
      </html>`);
    await page.addStyleTag({ path: path.join(root, commonStylesheet.replace('/common.css', '/reset.css')) });
    await page.addStyleTag({ path: path.join(root, commonStylesheet) });
    await page.addStyleTag({ path: path.join(root, stylesheet) });

    const computed = async () => page.evaluate(() => {
      const link = getComputedStyle(document.querySelector('a'));
      const current = getComputedStyle(document.querySelector('[aria-current="page"]'));
      return {
        paginationGap: getComputedStyle(document.querySelector('nav')).gap,
        paginationJustify: getComputedStyle(document.querySelector('nav')).justifyContent,
        linkColor: link.color,
        linkBorder: link.borderTopColor,
        currentColor: current.color,
        currentBackground: current.backgroundColor,
        currentBorder: current.borderTopColor,
      };
    });

    expect(await computed()).toEqual({
      paginationGap: '8px',
      paginationJustify: 'center',
      linkColor: 'rgb(23, 32, 51)',
      linkBorder: 'rgba(0, 0, 0, 0)',
      currentColor: 'rgb(255, 255, 255)',
      currentBackground: 'rgb(23, 32, 51)',
      currentBorder: 'rgb(23, 32, 51)',
    });

    await page.locator('html').evaluate((element) => element.setAttribute('data-color-scheme', 'dark'));
    expect(await computed()).toEqual({
      paginationGap: '8px',
      paginationJustify: 'center',
      linkColor: 'rgb(229, 236, 245)',
      linkBorder: 'rgba(0, 0, 0, 0)',
      currentColor: 'rgb(17, 24, 39)',
      currentBackground: 'rgb(229, 236, 245)',
      currentBorder: 'rgb(229, 236, 245)',
    });
  });
}

const panelFixtures = [
  {
    name: 'community basic',
    commonStylesheet: 'modules/community/theme/basic/assets/common.css',
    stylesheet: 'modules/community/theme/basic/assets/module.css',
    wrapperOpen: '<main class="community-screen">',
    wrapperClose: '</main>',
    panel: 'community-comments-panel',
    header: 'community-comments-panel-header',
    count: 'community-comments-count',
    list: 'community-comment-list',
    item: 'community-comment-item',
    form: 'community-comment-form',
  },
  {
    name: 'content basic',
    commonStylesheet: 'modules/content/theme/basic/assets/common.css',
    stylesheet: 'modules/content/theme/basic/assets/module.css',
    wrapperOpen: '<main class="content-page">',
    wrapperClose: '</main>',
    panel: 'content-comments',
    header: 'content-comments-panel-header',
    count: 'content-comments-count',
    list: '',
    item: 'content-comment-item',
    form: 'content-comment-form',
  },
  {
    name: 'quiz basic',
    commonStylesheet: 'modules/quiz/theme/basic/assets/common.css',
    stylesheet: 'modules/quiz/theme/basic/assets/module.css',
    wrapperOpen: '<main class="sr-quiz-page"><div class="quiz-page-main">',
    wrapperClose: '</div></main>',
    panel: 'sr-quiz-comments',
    header: 'sr-quiz-comments-panel-header',
    count: 'sr-quiz-comments-count',
    list: 'sr-quiz-comment-list',
    item: 'sr-quiz-comment-item',
    form: 'sr-quiz-comment-form',
  },
  {
    name: 'survey basic',
    commonStylesheet: 'modules/survey/theme/basic/assets/common.css',
    stylesheet: 'modules/survey/theme/basic/assets/module.css',
    wrapperOpen: '<main class="sr-survey-page"><div class="survey-page-main">',
    wrapperClose: '</div></main>',
    panel: 'sr-survey-comments',
    header: 'sr-survey-comments-panel-header',
    count: 'sr-survey-comments-count',
    list: 'sr-survey-comment-list',
    item: 'sr-survey-comment-item',
    form: 'sr-survey-comment-form',
  },
];

for (const fixture of panelFixtures) {
  test(`${fixture.name} comment panel follows community divider metrics`, async ({ page }) => {
    const listClass = fixture.list === '' ? '' : ` class="${fixture.list}"`;
    await page.setContent(`<!doctype html>
      <html lang="ko" data-color-scheme="light">
      <head><style>
        :root[data-color-scheme="light"] {
          --radius: 8px;
          --sr-text: #172033;
          --sr-muted: #687386;
          --sr-muted-strong: #4c586b;
          --sr-border: #ccd4e0;
          --sr-border-soft: #e3e8ef;
          --sr-surface: #ffffff;
          --sr-surface-muted: #f3f6fa;
          --color-card: #ffffff;
        }
        :root[data-color-scheme="dark"] {
          --sr-text: #e5ecf5;
          --sr-muted: #aeb8c7;
          --sr-muted-strong: #c9d2de;
          --sr-border: #475569;
          --sr-border-soft: #344154;
          --sr-surface: #111827;
          --sr-surface-muted: #1f2937;
          --color-card: #111827;
        }
      </style></head>
      <body>${fixture.wrapperOpen}
        <section class="${fixture.panel}">
          <div class="${fixture.header}"><h2 data-comment-title>댓글 <span class="${fixture.count}">2</span></h2></div>
          <ul${listClass}>
            <li class="${fixture.item}"><div>첫 댓글</div></li>
            <li class="${fixture.item}" data-comment-second><div>둘째 댓글</div></li>
          </ul>
          <form class="${fixture.form}"><p data-comment-form-copy>댓글 작성</p><button type="button">등록</button></form>
        </section>
      ${fixture.wrapperClose}</body></html>`);
    await page.addStyleTag({ path: path.join(root, fixture.commonStylesheet.replace('/common.css', '/reset.css')) });
    await page.addStyleTag({ path: path.join(root, fixture.commonStylesheet) });
    await page.addStyleTag({ path: path.join(root, fixture.stylesheet) });

    const computed = async () => page.evaluate(() => {
      const panel = getComputedStyle(document.querySelector('section'));
      const header = getComputedStyle(document.querySelector('[data-comment-title]').parentElement);
      const title = getComputedStyle(document.querySelector('[data-comment-title]'));
      const second = getComputedStyle(document.querySelector('[data-comment-second]'));
      const form = getComputedStyle(document.querySelector('form'));
      const formCopy = getComputedStyle(document.querySelector('[data-comment-form-copy]'));
      return {
        panelBackground: panel.backgroundColor,
        panelBorder: panel.borderTopColor,
        panelRadius: panel.borderTopLeftRadius,
        panelGap: panel.gap,
        panelPaddingBottom: panel.paddingBottom,
        headerDivider: header.borderBottomColor,
        titleMarginBottom: title.marginBottom,
        itemDivider: second.borderTopColor,
        itemPaddingTop: second.paddingTop,
        formDivider: form.borderTopColor,
        formPaddingTop: form.paddingTop,
        formCopyColor: formCopy.color,
        formCopyMarginBottom: formCopy.marginBottom,
      };
    });

    for (const scheme of ['light', 'dark']) {
      if (scheme === 'dark') {
        await page.locator('html').evaluate((element) => element.setAttribute('data-color-scheme', 'dark'));
      }
      const styles = await computed();
      expect(styles.panelBackground).toBe(scheme === 'dark' ? 'rgb(17, 24, 39)' : 'rgb(255, 255, 255)');
      expect(styles.panelBorder).toBe(scheme === 'dark' ? 'rgb(71, 85, 105)' : 'rgb(204, 212, 224)');
      expect(styles.panelRadius).toBe('12px');
      expect(styles.panelGap).toBe('18px');
      expect(styles.panelPaddingBottom).toBe('22px');
      expect(styles.titleMarginBottom).toBe('0px');
      expect(styles.itemPaddingTop).toBe('18px');
      expect(styles.formPaddingTop).toBe('20px');
      expect(styles.formCopyColor).toBe(scheme === 'dark' ? 'rgb(229, 236, 245)' : 'rgb(23, 32, 51)');
      expect(styles.formCopyMarginBottom).toBe('0px');
      expect(styles.headerDivider).toBe(styles.itemDivider);
      expect(styles.formDivider).toBe(styles.itemDivider);
    }
  });
}
