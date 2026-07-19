const path = require('path');
const { test, expect } = require('@playwright/test');

const repoRoot = path.resolve(__dirname, '..', '..', '..');

async function renderContentSidebar(page, scheme, width) {
  await page.setViewportSize({ width, height: 900 });
  const tokens = scheme === 'dark'
    ? '--sr-text:#f2f4f7;--sr-muted:#aeb7c4;--sr-border:#46505e;--sr-border-soft:#38414d;--sr-surface:#1d232b;--sr-surface-soft:#262d37;--color-primary:#8ab4ff;--color-card:#1d232b;'
    : '--sr-text:#20242a;--sr-muted:#6b7280;--sr-border:#d8dde6;--sr-border-soft:#e8ebf0;--sr-surface:#ffffff;--sr-surface-soft:#f5f7fb;--color-primary:#315efb;--color-card:#ffffff;';
  await page.setContent(`<!doctype html><html data-color-scheme="${scheme}"><head><style>:root{${tokens}}</style></head><body><main class="content-page content-page-view"><div class="content-screen-frame"><div class="content-screen-main"><article class="content-article"><div class="content-reading-panel"><header class="content-header"><h1>콘텐츠 제목</h1></header><div class="content-body">읽기 본문</div></div><section class="content-comments"><div class="content-comments-panel-header"><h2>댓글</h2></div><ul><li class="content-comment-item"><div class="content-comment-body">댓글 본문</div></li></ul></section></article></div><aside class="content-sidebar"><section class="card content-sidebar-section"><div class="card-body"><ul class="content-sidebar-list content-sidebar-comment-list"><li><a href="#">최신 댓글 본문</a><span class="content-sidebar-comment-meta"><span class="content-sidebar-comment-byline"><span>관리자</span><span aria-hidden="true">·</span><time>17시간 전</time></span><span class="content-sidebar-comment-separator" aria-hidden="true">·</span><a class="content-sidebar-comment-content" href="#">아주 긴 원본 콘텐츠 제목입니다</a></span></li></ul></div></section></aside></div></main></body></html>`);
  await page.addStyleTag({ path: path.join(repoRoot, 'modules/content/theme/basic/assets/common.css') });
  await page.addStyleTag({ path: path.join(repoRoot, 'modules/content/theme/basic/assets/module.css') });
}

async function renderQuizSidebar(page, scheme, width) {
  await page.setViewportSize({ width, height: 900 });
  const tokens = scheme === 'dark'
    ? '--sr-text:#f2f4f7;--sr-muted:#aeb7c4;--sr-border:#46505e;--color-primary:#8ab4ff;'
    : '--sr-text:#20242a;--sr-muted:#6b7280;--sr-border:#d8dde6;--color-primary:#315efb;';
  await page.setContent(`<!doctype html><html data-color-scheme="${scheme}"><head><style>:root{${tokens}}</style></head><body class="sr-quiz-page"><div class="quiz-screen-frame"><main class="quiz-screen-main">본문</main><aside class="quiz-sidebar"><section class="card quiz-sidebar-section"><div class="card-body"><ul class="quiz-sidebar-list"><li><a href="#">사이드 링크</a><span>보조 정보</span></li></ul></div></section></aside></div></body></html>`);
  await page.addStyleTag({ path: path.join(repoRoot, 'modules/quiz/theme/basic/assets/common.css') });
  await page.addStyleTag({ path: path.join(repoRoot, 'modules/quiz/theme/basic/assets/module.css') });
}

async function renderSurveySidebar(page, scheme, width) {
  await page.setViewportSize({ width, height: 900 });
  const tokens = scheme === 'dark'
    ? '--sr-text:#f2f4f7;--sr-muted:#aeb7c4;--sr-border:#46505e;--color-primary:#8ab4ff;'
    : '--sr-text:#20242a;--sr-muted:#6b7280;--sr-border:#d8dde6;--color-primary:#315efb;';
  await page.setContent(`<!doctype html><html data-color-scheme="${scheme}"><head><style>:root{${tokens}}</style></head><body class="sr-survey-page"><div class="survey-screen-frame"><main class="survey-screen-main">본문</main><aside class="survey-sidebar"><section class="card survey-sidebar-section"><div class="card-body"><ul class="survey-sidebar-list"><li><a href="#">사이드 링크</a><span>보조 정보</span></li></ul></div></section></aside></div></body></html>`);
  await page.addStyleTag({ path: path.join(repoRoot, 'modules/survey/theme/basic/assets/common.css') });
  await page.addStyleTag({ path: path.join(repoRoot, 'modules/survey/theme/basic/assets/module.css') });
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
        const reading = getComputedStyle(document.querySelector('.content-reading-panel'));
        const comments = getComputedStyle(document.querySelector('.content-comments'));
        const commentMeta = getComputedStyle(document.querySelector('.content-sidebar-comment-meta'));
        const commentByline = getComputedStyle(document.querySelector('.content-sidebar-comment-byline'));
        const commentContent = getComputedStyle(document.querySelector('.content-sidebar-comment-content'));
        return {
          columns: frame.gridTemplateColumns,
          link: link.color,
          meta: meta.color,
          readingBackground: reading.backgroundColor,
          readingBorder: reading.borderTopColor,
          commentsBackground: comments.backgroundColor,
          commentsBorder: comments.borderTopColor,
          commentMetaOverflow: commentMeta.overflow,
          commentMetaWhiteSpace: commentMeta.whiteSpace,
          commentBylineShrink: commentByline.flexShrink,
          commentBylineWhiteSpace: commentByline.whiteSpace,
          commentContentOverflow: commentContent.overflow,
          commentContentTextOverflow: commentContent.textOverflow,
          commentContentWhiteSpace: commentContent.whiteSpace,
        };
      });
      expect(styles.columns.split(' ').length).toBeGreaterThan(1);
      expect(styles.link).toBe(fixture.text);
      expect(styles.meta).toBe(fixture.muted);
      expect(styles.readingBackground).toBe(fixture.scheme === 'dark' ? 'rgb(29, 35, 43)' : 'rgb(255, 255, 255)');
      expect(styles.readingBorder).toBe(fixture.scheme === 'dark' ? 'rgb(70, 80, 94)' : 'rgb(216, 221, 230)');
      expect(styles.commentsBackground).toBe(styles.readingBackground);
      expect(styles.commentsBorder).toBe(styles.readingBorder);
      expect(styles.commentMetaOverflow).toBe('hidden');
      expect(styles.commentMetaWhiteSpace).toBe('nowrap');
      expect(styles.commentBylineShrink).toBe('0');
      expect(styles.commentBylineWhiteSpace).toBe('nowrap');
      expect(styles.commentContentOverflow).toBe('hidden');
      expect(styles.commentContentTextOverflow).toBe('ellipsis');
      expect(styles.commentContentWhiteSpace).toBe('nowrap');
    });
  }

  test('content sidebar moves below the main column on narrow screens', async ({ page }) => {
    await renderContentSidebar(page, 'dark', 760);
    const styles = await page.evaluate(() => {
      const frame = getComputedStyle(document.querySelector('.content-screen-frame'));
      const aside = getComputedStyle(document.querySelector('.content-sidebar'));
      const commentsHeader = getComputedStyle(document.querySelector('.content-comments-panel-header'));
      return { columns: frame.gridTemplateColumns, asideColumns: aside.gridTemplateColumns, position: aside.position, border: aside.borderTopColor, commentsHeaderBorder: commentsHeader.borderBottomColor };
    });
    expect(styles.columns.split(' ')).toHaveLength(1);
    expect(styles.asideColumns.split(' ')).toHaveLength(1);
    expect(styles.position).toBe('static');
    expect(styles.border).toBe(styles.commentsHeaderBorder);
  });

  for (const fixture of [
    { scheme: 'light', text: 'rgb(32, 36, 42)', muted: 'rgb(107, 114, 128)' },
    { scheme: 'dark', text: 'rgb(242, 244, 247)', muted: 'rgb(174, 183, 196)' },
  ]) {
    test(`quiz sidebar follows ${fixture.scheme} tokens`, async ({ page }) => {
      await renderQuizSidebar(page, fixture.scheme, 1366);
      const styles = await page.evaluate(() => {
        const frame = getComputedStyle(document.querySelector('.quiz-screen-frame'));
        const link = getComputedStyle(document.querySelector('.quiz-sidebar a'));
        const meta = getComputedStyle(document.querySelector('.quiz-sidebar-list span'));
        return { columns: frame.gridTemplateColumns, link: link.color, meta: meta.color };
      });
      expect(styles.columns.split(' ').length).toBeGreaterThan(1);
      expect(styles.link).toBe(fixture.text);
      expect(styles.meta).toBe(fixture.muted);
    });
  }

  test('quiz sidebar moves below the main column on narrow screens', async ({ page }) => {
    await renderQuizSidebar(page, 'dark', 760);
    const styles = await page.evaluate(() => {
      const frame = getComputedStyle(document.querySelector('.quiz-screen-frame'));
      const aside = getComputedStyle(document.querySelector('.quiz-sidebar'));
      return { columns: frame.gridTemplateColumns, position: aside.position, border: aside.borderTopColor };
    });
    expect(styles.columns.split(' ')).toHaveLength(1);
    expect(styles.position).toBe('static');
    expect(styles.border).toBe('rgb(70, 80, 94)');
  });

  for (const fixture of [
    { scheme: 'light', text: 'rgb(32, 36, 42)', muted: 'rgb(107, 114, 128)' },
    { scheme: 'dark', text: 'rgb(242, 244, 247)', muted: 'rgb(174, 183, 196)' },
  ]) {
    test(`survey sidebar follows ${fixture.scheme} tokens`, async ({ page }) => {
      await renderSurveySidebar(page, fixture.scheme, 1366);
      const styles = await page.evaluate(() => {
        const frame = getComputedStyle(document.querySelector('.survey-screen-frame'));
        const link = getComputedStyle(document.querySelector('.survey-sidebar a'));
        const meta = getComputedStyle(document.querySelector('.survey-sidebar-list span'));
        return { columns: frame.gridTemplateColumns, link: link.color, meta: meta.color };
      });
      expect(styles.columns.split(' ').length).toBeGreaterThan(1);
      expect(styles.link).toBe(fixture.text);
      expect(styles.meta).toBe(fixture.muted);
    });
  }

  test('survey sidebar moves below the main column on narrow screens', async ({ page }) => {
    await renderSurveySidebar(page, 'dark', 760);
    const styles = await page.evaluate(() => {
      const frame = getComputedStyle(document.querySelector('.survey-screen-frame'));
      const aside = getComputedStyle(document.querySelector('.survey-sidebar'));
      return { columns: frame.gridTemplateColumns, position: aside.position, border: aside.borderTopColor };
    });
    expect(styles.columns.split(' ')).toHaveLength(1);
    expect(styles.position).toBe('static');
    expect(styles.border).toBe('rgb(70, 80, 94)');
  });
});
