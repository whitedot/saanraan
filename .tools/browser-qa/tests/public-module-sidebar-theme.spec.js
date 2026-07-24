const path = require('path');
const { test, expect } = require('@playwright/test');

const repoRoot = path.resolve(__dirname, '..', '..', '..');

async function renderContentSidebar(page, scheme, width) {
  await page.setViewportSize({ width, height: 900 });
  const tokens = scheme === 'dark'
    ? '--sr-text:#f2f4f7;--sr-muted:#aeb7c4;--sr-border:#46505e;--sr-border-soft:#38414d;--sr-surface:#1d232b;--sr-surface-soft:#262d37;--color-primary:#8ab4ff;--color-card:#1d232b;'
    : '--sr-text:#20242a;--sr-muted:#6b7280;--sr-border:#d8dde6;--sr-border-soft:#e8ebf0;--sr-surface:#ffffff;--sr-surface-soft:#f5f7fb;--color-primary:#315efb;--color-card:#ffffff;';
  await page.setContent(`<!doctype html><html data-color-scheme="${scheme}"><head><style>:root{${tokens}}</style></head><body><main class="content-page content-page-view"><div class="content-screen-frame"><div class="content-screen-main"><article class="content-article"><div class="content-reading-panel"><header class="content-header"><h1>콘텐츠 제목</h1><div class="content-meta"><span>작성자</span><span>방금 전</span></div><div class="content-view-actions"><div class="content-view-action-group content-view-action-group-trailing"><a class="btn btn-sm btn-outline-default content-edit-link" href="#">수정</a></div></div></header><div class="content-body">읽기 본문</div><div class="reaction-widget">반응</div><div class="content-view-actions content-view-actions-bottom"><div class="content-view-action-group content-view-action-group-trailing"><a class="btn btn-sm btn-outline-default content-edit-link" href="#">수정</a></div></div></div></article><section class="content-comments-panel"><div class="content-comments-panel-header"><h2>댓글</h2></div><ul class="content-comment-list"><li class="content-comment-item"><div class="content-comment-body">댓글 본문</div></li></ul></section></div><aside class="content-sidebar"><section class="card content-sidebar-section content-sidebar-summary-section"><div class="card-header"><h2 class="card-title">최신댓글</h2></div><div class="card-body content-sidebar-summary-body"><ul class="content-sidebar-list content-sidebar-comment-list"><li><a class="content-sidebar-comment-excerpt" href="#">최신 댓글 본문</a><span class="content-sidebar-comment-meta"><span class="content-sidebar-comment-byline"><span>관리자</span><span aria-hidden="true">·</span><time>17시간 전</time></span><span class="content-sidebar-comment-separator" aria-hidden="true">·</span><a class="content-sidebar-comment-content" href="#">아주 긴 원본 콘텐츠 제목입니다</a></span></li></ul></div></section></aside></div></main></body></html>`);
  await page.addStyleTag({ path: path.join(repoRoot, 'modules/content/theme/basic/assets/common.css') });
  await page.addStyleTag({ path: path.join(repoRoot, 'modules/content/theme/basic/assets/module.css') });
}

async function renderQuizSidebar(page, scheme, width) {
  await page.setViewportSize({ width, height: 900 });
  const tokens = scheme === 'dark'
    ? '--sr-text:#f2f4f7;--sr-muted:#aeb7c4;--sr-border:#46505e;--color-primary:#8ab4ff;'
    : '--sr-text:#20242a;--sr-muted:#6b7280;--sr-border:#d8dde6;--color-primary:#315efb;';
  await page.setContent(`<!doctype html><html data-color-scheme="${scheme}"><head><style>:root{${tokens}}</style></head><body class="sr-quiz-page"><div class="quiz-screen-frame"><main class="quiz-screen-main">본문</main><aside class="quiz-sidebar"><section class="card quiz-sidebar-section quiz-sidebar-summary-section"><div class="card-header"><h2 class="card-title">인기 퀴즈</h2></div><div class="card-body quiz-sidebar-summary-body"><ol class="quiz-sidebar-list quiz-sidebar-popular-list"><li><a class="quiz-sidebar-summary-title" href="#">인기 퀴즈 제목</a><span class="quiz-sidebar-summary-meta">조회 120<span aria-hidden="true">·</span><time>2일 전</time></span></li></ol></div></section><section class="card quiz-sidebar-section quiz-sidebar-summary-section"><div class="card-header"><h2 class="card-title">최신댓글</h2></div><div class="card-body quiz-sidebar-summary-body"><ul class="quiz-sidebar-list quiz-sidebar-comment-list"><li><a class="quiz-sidebar-comment-excerpt" href="#">최신 댓글 본문</a><span class="quiz-sidebar-comment-meta"><span class="quiz-sidebar-comment-byline"><span>관리자</span><span aria-hidden="true">·</span><time>17시간 전</time></span><span class="quiz-sidebar-comment-separator" aria-hidden="true">·</span><a class="quiz-sidebar-comment-content" href="#">아주 긴 원본 퀴즈 제목입니다</a></span></li></ul></div></section></aside></div></body></html>`);
  await page.addStyleTag({ path: path.join(repoRoot, 'modules/quiz/theme/basic/assets/common.css') });
  await page.addStyleTag({ path: path.join(repoRoot, 'modules/quiz/theme/basic/assets/module.css') });
}

async function renderSurveySidebar(page, scheme, width) {
  await page.setViewportSize({ width, height: 900 });
  const tokens = scheme === 'dark'
    ? '--sr-text:#f2f4f7;--sr-muted:#aeb7c4;--sr-border:#46505e;--color-primary:#8ab4ff;'
    : '--sr-text:#20242a;--sr-muted:#6b7280;--sr-border:#d8dde6;--color-primary:#315efb;';
  await page.setContent(`<!doctype html><html data-color-scheme="${scheme}"><head><style>:root{${tokens}}</style></head><body class="sr-survey-page"><div class="survey-screen-frame"><main class="survey-screen-main">본문</main><aside class="survey-sidebar"><section class="card survey-sidebar-section survey-sidebar-summary-section"><div class="card-header"><h2 class="card-title">인기 설문</h2></div><div class="card-body survey-sidebar-summary-body"><ol class="survey-sidebar-list survey-sidebar-popular-list"><li><a class="survey-sidebar-summary-title" href="#">인기 설문 제목</a><span class="survey-sidebar-summary-meta">조회 120<span aria-hidden="true">·</span><time>2일 전</time></span></li></ol></div></section><section class="card survey-sidebar-section survey-sidebar-summary-section"><div class="card-header"><h2 class="card-title">최신댓글</h2></div><div class="card-body survey-sidebar-summary-body"><ul class="survey-sidebar-list survey-sidebar-comment-list"><li><a class="survey-sidebar-comment-excerpt" href="#">최신 댓글 본문</a><span class="survey-sidebar-comment-meta"><span class="survey-sidebar-comment-byline"><span>관리자</span><span aria-hidden="true">·</span><time>17시간 전</time></span><span class="survey-sidebar-comment-separator" aria-hidden="true">·</span><a class="survey-sidebar-comment-content" href="#">아주 긴 원본 설문 제목입니다</a></span></li></ul></div></section></aside></div></body></html>`);
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
        const meta = getComputedStyle(document.querySelector('.content-sidebar-comment-byline time'));
        const reading = getComputedStyle(document.querySelector('.content-reading-panel'));
        const contentHeader = getComputedStyle(document.querySelector('.content-header'));
        const comments = getComputedStyle(document.querySelector('.content-comments-panel'));
        const viewActionElements = document.querySelectorAll('.content-view-actions');
        const viewActions = getComputedStyle(viewActionElements[0]);
        const bottomViewActions = getComputedStyle(document.querySelector('.content-view-actions-bottom'));
        const commentMeta = getComputedStyle(document.querySelector('.content-sidebar-comment-meta'));
        const commentByline = getComputedStyle(document.querySelector('.content-sidebar-comment-byline'));
        const commentContent = getComputedStyle(document.querySelector('.content-sidebar-comment-content'));
        const summaryBody = getComputedStyle(document.querySelector('.content-sidebar-summary-body'));
        const summaryList = getComputedStyle(document.querySelector('.content-sidebar-list'));
        const summaryItem = getComputedStyle(document.querySelector('.content-sidebar-list > li'));
        const summaryTitle = getComputedStyle(document.querySelector('.content-sidebar-comment-excerpt'));
        const sectionTitle = getComputedStyle(document.querySelector('.content-sidebar-summary-section .card-title'));
        return {
          columns: frame.gridTemplateColumns,
          link: link.color,
          meta: meta.color,
          readingBackground: reading.backgroundColor,
          readingBorder: reading.borderTopColor,
          contentHeaderDivider: contentHeader.borderBottomColor,
          commentsBackground: comments.backgroundColor,
          commentsBorder: comments.borderTopColor,
          viewActionsJustify: viewActions.justifyContent,
          viewActionsMarginTop: viewActions.marginTop,
          viewActionsCount: viewActionElements.length,
          bottomViewActionsBorder: bottomViewActions.borderTopColor,
          bottomViewActionsMarginTop: bottomViewActions.marginTop,
          bottomViewActionsPaddingTop: bottomViewActions.paddingTop,
          commentMetaOverflow: commentMeta.overflow,
          commentMetaWhiteSpace: commentMeta.whiteSpace,
          commentBylineShrink: commentByline.flexShrink,
          commentBylineWhiteSpace: commentByline.whiteSpace,
          commentContentOverflow: commentContent.overflow,
          commentContentTextOverflow: commentContent.textOverflow,
          commentContentWhiteSpace: commentContent.whiteSpace,
          summaryBodyPadding: summaryBody.paddingTop,
          summaryListGap: summaryList.rowGap,
          summaryItemGap: summaryItem.rowGap,
          summaryTitleWeight: summaryTitle.fontWeight,
          summaryTitleLineHeight: summaryTitle.lineHeight,
          summaryTitleFontSize: summaryTitle.fontSize,
          sectionTitleWeight: sectionTitle.fontWeight,
        };
      });
      expect(styles.columns.split(' ').length).toBeGreaterThan(1);
      expect(styles.link).toBe(fixture.text);
      expect(styles.meta).toBe(fixture.muted);
      expect(styles.readingBackground).toBe(fixture.scheme === 'dark' ? 'rgb(29, 35, 43)' : 'rgb(255, 255, 255)');
      expect(styles.readingBorder).toBe(fixture.scheme === 'dark' ? 'rgb(70, 80, 94)' : 'rgb(216, 221, 230)');
      expect(styles.commentsBackground).toBe(styles.readingBackground);
      expect(styles.commentsBorder).toBe(styles.readingBorder);
      expect(styles.viewActionsJustify).toBe('flex-end');
      expect(styles.viewActionsMarginTop).toBe('16px');
      expect(styles.viewActionsCount).toBe(2);
      expect(styles.bottomViewActionsBorder).toBe(styles.contentHeaderDivider);
      expect(styles.bottomViewActionsMarginTop).toBe('24px');
      expect(styles.bottomViewActionsPaddingTop).toBe('14px');
      expect(styles.commentMetaOverflow).toBe('hidden');
      expect(styles.commentMetaWhiteSpace).toBe('nowrap');
      expect(styles.commentBylineShrink).toBe('0');
      expect(styles.commentBylineWhiteSpace).toBe('nowrap');
      expect(styles.commentContentOverflow).toBe('hidden');
      expect(styles.commentContentTextOverflow).toBe('ellipsis');
      expect(styles.commentContentWhiteSpace).toBe('nowrap');
      expect(styles.summaryBodyPadding).toBe('20px');
      expect(styles.summaryListGap).toBe('12px');
      expect(styles.summaryItemGap).toBe('3px');
      expect(styles.summaryTitleWeight).toBe('700');
      expect(parseFloat(styles.summaryTitleLineHeight) / parseFloat(styles.summaryTitleFontSize)).toBeCloseTo(1.2, 1);
      expect(styles.sectionTitleWeight).toBe('800');
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
        const meta = getComputedStyle(document.querySelector('.quiz-sidebar-summary-meta'));
        const summaryBody = getComputedStyle(document.querySelector('.quiz-sidebar-summary-body'));
        const summaryList = getComputedStyle(document.querySelector('.quiz-sidebar-list'));
        const summaryItem = getComputedStyle(document.querySelector('.quiz-sidebar-list > li'));
        const summaryTitle = getComputedStyle(document.querySelector('.quiz-sidebar-summary-title'));
        const sectionTitle = getComputedStyle(document.querySelector('.quiz-sidebar-summary-section .card-title'));
        const commentMeta = getComputedStyle(document.querySelector('.quiz-sidebar-comment-meta'));
        const commentByline = getComputedStyle(document.querySelector('.quiz-sidebar-comment-byline'));
        const commentTime = getComputedStyle(document.querySelector('.quiz-sidebar-comment-byline time'));
        const commentContent = getComputedStyle(document.querySelector('.quiz-sidebar-comment-content'));
        return {
          columns: frame.gridTemplateColumns,
          link: link.color,
          meta: meta.color,
          summaryBodyPadding: summaryBody.paddingTop,
          summaryListGap: summaryList.rowGap,
          summaryItemGap: summaryItem.rowGap,
          summaryTitleWeight: summaryTitle.fontWeight,
          summaryTitleLineHeight: summaryTitle.lineHeight,
          summaryTitleFontSize: summaryTitle.fontSize,
          sectionTitleWeight: sectionTitle.fontWeight,
          commentMeta: commentMeta.color,
          commentMetaOverflow: commentMeta.overflow,
          commentBylineShrink: commentByline.flexShrink,
          commentTime: commentTime.color,
          commentContentOverflow: commentContent.overflow,
          commentContentTextOverflow: commentContent.textOverflow,
          commentContentWhiteSpace: commentContent.whiteSpace,
        };
      });
      expect(styles.columns.split(' ').length).toBeGreaterThan(1);
      expect(styles.link).toBe(fixture.text);
      expect(styles.meta).toBe(fixture.muted);
      expect(styles.summaryBodyPadding).toBe('20px');
      expect(styles.summaryListGap).toBe('12px');
      expect(styles.summaryItemGap).toBe('3px');
      expect(styles.summaryTitleWeight).toBe('700');
      expect(parseFloat(styles.summaryTitleLineHeight) / parseFloat(styles.summaryTitleFontSize)).toBeCloseTo(1.2, 1);
      expect(styles.sectionTitleWeight).toBe('800');
      expect(styles.commentMeta).toBe(fixture.text);
      expect(styles.commentMetaOverflow).toBe('hidden');
      expect(styles.commentBylineShrink).toBe('0');
      expect(styles.commentTime).toBe(fixture.muted);
      expect(styles.commentContentOverflow).toBe('hidden');
      expect(styles.commentContentTextOverflow).toBe('ellipsis');
      expect(styles.commentContentWhiteSpace).toBe('nowrap');
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
        const meta = getComputedStyle(document.querySelector('.survey-sidebar-summary-meta'));
        const summaryBody = getComputedStyle(document.querySelector('.survey-sidebar-summary-body'));
        const summaryList = getComputedStyle(document.querySelector('.survey-sidebar-list'));
        const summaryItem = getComputedStyle(document.querySelector('.survey-sidebar-list > li'));
        const summaryTitle = getComputedStyle(document.querySelector('.survey-sidebar-summary-title'));
        const sectionTitle = getComputedStyle(document.querySelector('.survey-sidebar-summary-section .card-title'));
        const commentMeta = getComputedStyle(document.querySelector('.survey-sidebar-comment-meta'));
        const commentByline = getComputedStyle(document.querySelector('.survey-sidebar-comment-byline'));
        const commentTime = getComputedStyle(document.querySelector('.survey-sidebar-comment-byline time'));
        const commentContent = getComputedStyle(document.querySelector('.survey-sidebar-comment-content'));
        return {
          columns: frame.gridTemplateColumns,
          link: link.color,
          meta: meta.color,
          summaryBodyPadding: summaryBody.paddingTop,
          summaryListGap: summaryList.rowGap,
          summaryItemGap: summaryItem.rowGap,
          summaryTitleWeight: summaryTitle.fontWeight,
          summaryTitleLineHeight: summaryTitle.lineHeight,
          summaryTitleFontSize: summaryTitle.fontSize,
          sectionTitleWeight: sectionTitle.fontWeight,
          commentMeta: commentMeta.color,
          commentMetaOverflow: commentMeta.overflow,
          commentBylineShrink: commentByline.flexShrink,
          commentTime: commentTime.color,
          commentContentOverflow: commentContent.overflow,
          commentContentTextOverflow: commentContent.textOverflow,
          commentContentWhiteSpace: commentContent.whiteSpace,
        };
      });
      expect(styles.columns.split(' ').length).toBeGreaterThan(1);
      expect(styles.link).toBe(fixture.text);
      expect(styles.meta).toBe(fixture.muted);
      expect(styles.summaryBodyPadding).toBe('20px');
      expect(styles.summaryListGap).toBe('12px');
      expect(styles.summaryItemGap).toBe('3px');
      expect(styles.summaryTitleWeight).toBe('700');
      expect(parseFloat(styles.summaryTitleLineHeight) / parseFloat(styles.summaryTitleFontSize)).toBeCloseTo(1.2, 1);
      expect(styles.sectionTitleWeight).toBe('800');
      expect(styles.commentMeta).toBe(fixture.text);
      expect(styles.commentMetaOverflow).toBe('hidden');
      expect(styles.commentBylineShrink).toBe('0');
      expect(styles.commentTime).toBe(fixture.muted);
      expect(styles.commentContentOverflow).toBe('hidden');
      expect(styles.commentContentTextOverflow).toBe('ellipsis');
      expect(styles.commentContentWhiteSpace).toBe('nowrap');
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
