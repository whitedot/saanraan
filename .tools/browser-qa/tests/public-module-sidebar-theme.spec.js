const path = require('path');
const { test, expect } = require('@playwright/test');

const repoRoot = path.resolve(__dirname, '..', '..', '..');

async function renderContentSidebar(page, scheme, width) {
  await page.setViewportSize({ width, height: 900 });
  const tokens = scheme === 'dark'
    ? '--sr-text:#f2f4f7;--sr-muted:#aeb7c4;--sr-border:#46505e;--sr-border-soft:#38414d;--sr-surface:#1d232b;--sr-surface-soft:#262d37;--color-primary:#8ab4ff;--color-card:#1d232b;'
    : '--sr-text:#20242a;--sr-muted:#6b7280;--sr-border:#d8dde6;--sr-border-soft:#e8ebf0;--sr-surface:#ffffff;--sr-surface-soft:#f5f7fb;--color-primary:#315efb;--color-card:#ffffff;';
  await page.setContent(`<!doctype html><html data-color-scheme="${scheme}"><head><style>:root{${tokens}}</style></head><body><main class="content-page content-page-view"><div class="content-screen-frame"><div class="content-screen-main"><article class="content-article"><div class="card content-reading-panel"><header class="content-header"><h1>콘텐츠 제목</h1><div class="content-meta"><span>작성자</span><span>방금 전</span></div></header><div class="content-body">읽기 본문</div><div class="reaction-widget">반응</div><div class="content-view-actions content-view-actions-bottom"><div class="content-view-action-group content-view-action-group-trailing"><a class="btn btn-sm btn-outline-default content-edit-link" href="#">수정</a></div></div></div></article><section class="card content-comments-panel"><div class="content-comments-panel-header"><h2>댓글</h2></div><ul class="content-comment-list"><li class="content-comment-item"><div class="content-comment-body">댓글 본문</div></li></ul></section></div><aside class="content-sidebar"><section class="card content-sidebar-section content-sidebar-summary-section"><div class="card-header"><h2 class="card-title">최신댓글</h2></div><div class="card-body content-sidebar-summary-body"><ul class="content-sidebar-list content-sidebar-comment-list"><li><a class="content-sidebar-comment-excerpt" href="#">최신 댓글 본문</a><span class="content-sidebar-comment-meta"><span class="content-sidebar-comment-byline"><span>관리자</span><span aria-hidden="true">·</span><time>17시간 전</time></span><span class="content-sidebar-comment-separator" aria-hidden="true">·</span><a class="content-sidebar-comment-content" href="#">아주 긴 원본 콘텐츠 제목입니다</a></span></li></ul></div></section></aside></div></main></body></html>`);
  await page.addStyleTag({ path: path.join(repoRoot, 'modules/content/theme/basic/assets/reset.css') });
  await page.addStyleTag({ path: path.join(repoRoot, 'modules/content/theme/basic/assets/common.css') });
  await page.addStyleTag({ path: path.join(repoRoot, 'modules/content/theme/basic/assets/module.css') });
  await page.addStyleTag({ content: `:root[data-color-scheme] { ${tokens} }` });
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
        const dividerProbe = document.createElement('span');
        dividerProbe.style.borderTop = '1px solid var(--content-divider)';
        document.querySelector('.content-page').appendChild(dividerProbe);
        const expectedDivider = getComputedStyle(dividerProbe).borderTopColor;
        dividerProbe.remove();
        return {
          columns: frame.gridTemplateColumns,
          link: link.color,
          meta: meta.color,
          readingBackground: reading.backgroundColor,
          readingBorder: reading.borderTopColor,
          contentHeaderDivider: contentHeader.borderBottomColor,
          expectedDivider,
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
      expect(styles.readingBorder).toBe(fixture.scheme === 'dark' ? 'rgb(56, 65, 77)' : 'rgb(232, 235, 240)');
      expect(styles.commentsBackground).toBe(styles.readingBackground);
      expect(styles.commentsBorder).toBe(styles.readingBorder);
      expect(styles.viewActionsJustify).toBe('flex-end');
      expect(styles.viewActionsMarginTop).toBe('24px');
      expect(styles.viewActionsCount).toBe(1);
      expect(styles.bottomViewActionsBorder).toBe(styles.expectedDivider);
      expect(styles.bottomViewActionsMarginTop).toBe('24px');
      expect(styles.bottomViewActionsPaddingTop).toBe('14px');
      expect(styles.commentMetaOverflow).toBe('hidden');
      expect(styles.commentMetaWhiteSpace).toBe('nowrap');
      expect(styles.commentBylineShrink).toBe('0');
      expect(styles.commentBylineWhiteSpace).toBe('nowrap');
      expect(styles.commentContentOverflow).toBe('hidden');
      expect(styles.commentContentTextOverflow).toBe('ellipsis');
      expect(styles.commentContentWhiteSpace).toBe('nowrap');
      expect(styles.summaryBodyPadding).toBe('0px');
      expect(styles.summaryListGap).toBe('12px');
      expect(styles.summaryItemGap).toBe('3px');
      expect(styles.summaryTitleWeight).toBe('700');
      expect(parseFloat(styles.summaryTitleLineHeight) / parseFloat(styles.summaryTitleFontSize)).toBeCloseTo(1.5, 1);
      expect(styles.sectionTitleWeight).toBe('800');
    });
  }

  test('content sidebar moves below the main column on narrow screens', async ({ page }) => {
    await renderContentSidebar(page, 'dark', 760);
    const styles = await page.evaluate(() => {
      const frame = getComputedStyle(document.querySelector('.content-screen-frame'));
      const aside = getComputedStyle(document.querySelector('.content-sidebar'));
      const main = document.querySelector('.content-screen-main').getBoundingClientRect();
      const asideRect = document.querySelector('.content-sidebar').getBoundingClientRect();
      return { columns: frame.gridTemplateColumns, asideColumns: aside.gridTemplateColumns, position: aside.position, border: aside.borderTopColor, asideTop: asideRect.top, mainBottom: main.bottom, borderWidth: aside.borderTopWidth };
    });
    expect(styles.columns.split(' ')).toHaveLength(1);
    expect(styles.asideColumns.split(' ')).toHaveLength(1);
    expect(styles.position).toBe('static');
    expect(styles.border).toBe('rgb(70, 80, 94)');
    expect(styles.borderWidth).toBe('1px');
    expect(styles.asideTop).toBeGreaterThanOrEqual(styles.mainBottom);
  });

});
