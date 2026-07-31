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
      <head></head>
      <body><nav class="${className}"><a class="btn btn-ghost-default" href="#">2</a><span class="btn btn-solid-primary" aria-current="page">3</span></nav></body>
      </html>`);
    await page.addStyleTag({ path: path.join(root, commonStylesheet.replace('/common.css', '/reset.css')) });
    await page.addStyleTag({ path: path.join(root, commonStylesheet) });
    await page.addStyleTag({ path: path.join(root, stylesheet) });
    await page.addStyleTag({ content: `
      *, *::before, *::after {
        transition: none !important;
      }
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
    ` });

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

  test(`${name} choice toggle group stacks and aligns left on mobile`, async ({ page }) => {
    await page.setContent(`<!doctype html>
      <html lang="ko" data-color-scheme="light">
      <head><style>:root { --radius: 8px; }</style></head>
      <body>
        <div class="btn-group" data-choice-toggle-group>
          <input id="choice_first" type="checkbox" class="form-choice-toggle-input sr-only">
          <label for="choice_first" class="btn btn-choice-light btn-group-start">첫 번째 옵션</label>
          <input id="choice_second" type="checkbox" class="form-choice-toggle-input sr-only">
          <label for="choice_second" class="btn btn-choice-light btn-group-end">두 번째 옵션</label>
        </div>
      </body>
      </html>`);
    await page.addStyleTag({ path: path.join(root, commonStylesheet.replace('/common.css', '/reset.css')) });
    await page.addStyleTag({ path: path.join(root, commonStylesheet) });

    expect(await page.locator('[data-choice-toggle-group]').evaluate((group) => ({
      display: getComputedStyle(group).display,
      direction: getComputedStyle(group).flexDirection,
    }))).toEqual({ display: 'inline-flex', direction: 'row' });

    await page.setViewportSize({ width: 390, height: 844 });
    const mobile = await page.locator('[data-choice-toggle-group]').evaluate((group) => {
      const labels = Array.from(group.querySelectorAll('label'));
      const groupRect = group.getBoundingClientRect();
      const labelRects = labels.map((label) => label.getBoundingClientRect());
      return {
        direction: getComputedStyle(group).flexDirection,
        groupWidth: Math.round(groupRect.width),
        labelWidths: labelRects.map((rect) => Math.round(rect.width)),
        secondTop: Math.round(labelRects[1].top),
        firstBottom: Math.round(labelRects[0].bottom),
        justify: labels.map((label) => getComputedStyle(label).justifyContent),
        textAlign: labels.map((label) => getComputedStyle(label).textAlign),
        borderTopWidths: labels.map((label) => getComputedStyle(label).borderTopWidth),
        topLeftRadii: labels.map((label) => getComputedStyle(label).borderTopLeftRadius),
        bottomLeftRadii: labels.map((label) => getComputedStyle(label).borderBottomLeftRadius),
      };
    });

    expect(mobile.direction).toBe('column');
    expect(mobile.labelWidths).toEqual([mobile.groupWidth, mobile.groupWidth]);
    expect(mobile.secondTop).toBe(mobile.firstBottom);
    expect(mobile.justify).toEqual(['flex-start', 'flex-start']);
    expect(mobile.textAlign).toEqual(['start', 'start']);
    expect(mobile.borderTopWidths).toEqual(['1px', '0px']);
    expect(mobile.topLeftRadii[0]).not.toBe('0px');
    expect(mobile.topLeftRadii[1]).toBe('0px');
    expect(mobile.bottomLeftRadii).toEqual(['0px', mobile.topLeftRadii[0]]);
  });
}

test('community new comment form fills its row and keeps the secret option before submit', async ({ page }) => {
  await page.setContent(`<!doctype html>
    <html lang="ko" data-color-scheme="light">
    <body>
      <main class="community-screen">
        <section class="community-comments-panel">
          <form class="community-comment-form" style="--community-comments-padding-inline: 28px">
            <p><label><span>댓글</span><textarea class="form-textarea form-control-full"></textarea></label></p>
            <div class="community-comment-form-actions">
              <label class="community-comment-secret-toggle"><input type="checkbox"><span>비밀 댓글</span></label>
              <button type="submit" class="btn btn-solid-primary">댓글 등록</button>
            </div>
          </form>
        </section>
      </main>
    </body>
    </html>`);
  await page.addStyleTag({ path: path.join(root, 'modules/community/theme/basic/assets/reset.css') });
  await page.addStyleTag({ path: path.join(root, 'modules/community/theme/basic/assets/common.css') });
  await page.addStyleTag({ path: path.join(root, 'modules/community/theme/basic/assets/module.css') });

  const layout = await page.evaluate(() => {
    const textarea = document.querySelector('.community-comment-form textarea');
    const label = textarea.closest('label');
    const actions = document.querySelector('.community-comment-form-actions');
    const secret = actions.querySelector('.community-comment-secret-toggle');
    const submit = actions.querySelector('[type="submit"]');

    return {
      textareaWidth: Math.round(textarea.getBoundingClientRect().width),
      labelWidth: Math.round(label.getBoundingClientRect().width),
      textareaMaxWidth: getComputedStyle(textarea).maxWidth,
      actionsDisplay: getComputedStyle(actions).display,
      actionsJustify: getComputedStyle(actions).justifyContent,
      secretFontWeight: getComputedStyle(secret).fontWeight,
      secretBeforeSubmit: Boolean(secret.compareDocumentPosition(submit) & Node.DOCUMENT_POSITION_FOLLOWING),
    };
  });

  expect(layout.textareaWidth).toBe(layout.labelWidth);
  expect(layout.textareaWidth).toBeGreaterThan(768);
  expect(layout).toMatchObject({
    textareaMaxWidth: '100%',
    actionsDisplay: 'flex',
    actionsJustify: 'flex-end',
    secretFontWeight: '400',
    secretBeforeSubmit: true,
  });
});

test('community post write controls fill their form rows', async ({ page }) => {
  await page.setContent(`<!doctype html>
    <html lang="ko" data-color-scheme="light">
    <head><style>
      *, *::before, *::after { transition: none !important; }
      :root[data-color-scheme="light"] {
        --radius: 8px;
        --sr-text: #172033;
        --sr-border: #ccd4e0;
        --sr-surface: #ffffff;
      }
      :root[data-color-scheme="dark"] {
        --radius: 8px;
        --sr-text: #e5ecf5;
        --sr-border: #475569;
        --sr-surface: #111827;
      }
    </style></head>
    <body>
      <main class="community-screen">
        <section class="community-post-form-panel" data-community-post-form-panel>
          <header class="community-post-form-header"><div class="community-post-heading"><p class="community-post-view-board"><a href="#">게시판</a></p><h1 class="type-page-title community-post-form-title">글쓰기</h1></div></header>
          <form class="community-post-form" data-community-post-form>
            <p><label><span>제목</span><input class="form-input form-control-full" type="text"></label></p>
            <p><label><span>카테고리</span><select class="form-select form-control-full"><option>선택</option></select></label></p>
            <p><label><span>본문</span><textarea class="form-textarea form-control-full"></textarea></label></p>
            <p><label><span>정렬 순서</span><input class="form-input form-control-full" type="number"></label></p>
            <div class="btn-group community-post-option-toggles" role="group" aria-label="게시글 옵션" data-community-post-option-toggles>
              <input id="community_post_secret" type="checkbox" name="is_secret" value="1" class="form-choice-toggle-input sr-only" checked>
              <label for="community_post_secret" class="btn btn-choice-light btn-group-start">비밀글</label>
              <input id="community_post_notice" type="checkbox" name="is_notice" value="1" class="form-choice-toggle-input sr-only">
              <label for="community_post_notice" class="btn btn-choice-light btn-group-end">공지사항</label>
            </div>
          </form>
        </section>
        <article class="community-post-view" data-community-post-view><header class="community-post-view-header"><div class="community-post-heading"><p class="community-post-view-board"><a href="#">게시판</a></p><h1 class="community-post-title community-post-view-title">읽기</h1></div></header><div>본문</div></article>
      </main>
    </body>
    </html>`);
  await page.addStyleTag({ path: path.join(root, 'modules/community/theme/basic/assets/reset.css') });
  await page.addStyleTag({ path: path.join(root, 'modules/community/theme/basic/assets/common.css') });
  await page.addStyleTag({ path: path.join(root, 'modules/community/theme/basic/assets/module.css') });

  const controls = await page.locator('[data-community-post-form] :is(.form-input, .form-select, .form-textarea)').evaluateAll((elements) => elements.map((element) => {
    const label = element.closest('label');
    return {
      width: Math.round(element.getBoundingClientRect().width),
      labelWidth: Math.round(label.getBoundingClientRect().width),
      maxWidth: getComputedStyle(element).maxWidth,
    };
  }));

  expect(controls).toHaveLength(4);
  for (const control of controls) {
    expect(control.width).toBe(control.labelWidth);
    expect(control.width).toBeGreaterThan(768);
    expect(control.maxWidth).toBe('100%');
  }

  const panelMetrics = async () => page.evaluate(() => {
    const metrics = (selector) => {
      const element = document.querySelector(selector);
      const style = getComputedStyle(element);
      return {
        background: style.backgroundColor,
        border: style.borderTopColor,
        radius: style.borderTopLeftRadius,
        gap: style.gap,
        paddingTop: style.paddingTop,
        paddingInline: [style.paddingLeft, style.paddingRight],
      };
    };

    return {
      form: metrics('[data-community-post-form-panel]'),
      view: metrics('[data-community-post-view]'),
    };
  });

  const lightPanelMetrics = await panelMetrics();
  expect(lightPanelMetrics.form).toEqual(lightPanelMetrics.view);
  expect(lightPanelMetrics.form.background).toBe('rgb(255, 255, 255)');
  expect(lightPanelMetrics.form.border).toBe('rgb(204, 212, 224)');

  const titleMetrics = await page.evaluate(() => ({
    formSize: getComputedStyle(document.querySelector('.community-post-form-title')).fontSize,
    formMargin: getComputedStyle(document.querySelector('.community-post-form-title')).margin,
    viewSize: getComputedStyle(document.querySelector('.community-post-view-title')).fontSize,
  }));
  expect(titleMetrics.formSize).toBe('26px');
  expect(titleMetrics.formMargin).toBe('0px');
  expect(parseFloat(titleMetrics.viewSize)).toBeGreaterThan(parseFloat(titleMetrics.formSize));

  const optionMetrics = await page.evaluate(() => {
    const group = document.querySelector('[data-community-post-option-toggles]');
    const secretInput = document.querySelector('#community_post_secret');
    const secretLabel = document.querySelector('[for="community_post_secret"]');
    const noticeLabel = document.querySelector('[for="community_post_notice"]');
    return {
      groupDisplay: getComputedStyle(group).display,
      secretChecked: secretInput.checked,
      secretLabelDisplay: getComputedStyle(secretLabel).display,
      secretBackground: getComputedStyle(secretLabel).backgroundColor,
      noticeBackground: getComputedStyle(noticeLabel).backgroundColor,
    };
  });
  expect(optionMetrics).toMatchObject({
    groupDisplay: 'flex',
    secretChecked: true,
    secretLabelDisplay: 'flex',
    noticeBackground: 'rgb(255, 255, 255)',
  });
  expect(optionMetrics.secretBackground).not.toBe(optionMetrics.noticeBackground);
  await page.locator('[for="community_post_notice"]').click();
  await expect(page.locator('#community_post_notice')).toBeChecked();

  await page.locator('html').evaluate((element) => element.setAttribute('data-color-scheme', 'dark'));
  const darkPanelMetrics = await panelMetrics();
  expect(darkPanelMetrics.form).toEqual(darkPanelMetrics.view);
  expect(darkPanelMetrics.form.background).toBe('rgb(17, 24, 39)');
  expect(darkPanelMetrics.form.border).toBe('rgb(71, 85, 105)');
  const darkOptionMetrics = await page.evaluate(() => ({
    secretBackground: getComputedStyle(document.querySelector('[for="community_post_secret"]')).backgroundColor,
    noticeBackground: getComputedStyle(document.querySelector('[for="community_post_notice"]')).backgroundColor,
  }));
  expect(darkOptionMetrics.secretBackground).toBe(darkOptionMetrics.noticeBackground);
  expect(darkOptionMetrics.secretBackground).not.toBe(darkPanelMetrics.form.background);

  await page.setViewportSize({ width: 390, height: 844 });
  const mobileOptionMetrics = await page.evaluate(() => {
    const group = document.querySelector('[data-community-post-option-toggles]');
    const labels = Array.from(group.querySelectorAll('label'));
    const groupRect = group.getBoundingClientRect();
    const labelRects = labels.map((label) => label.getBoundingClientRect());
    return {
      display: getComputedStyle(group).display,
      direction: getComputedStyle(group).flexDirection,
      groupWidth: Math.round(groupRect.width),
      labelWidths: labelRects.map((rect) => Math.round(rect.width)),
      labelTops: labelRects.map((rect) => Math.round(rect.top)),
      firstLabelBottom: Math.round(labelRects[0].bottom),
      labelJustify: labels.map((label) => getComputedStyle(label).justifyContent),
      labelTextAlign: labels.map((label) => getComputedStyle(label).textAlign),
      borderInlineStartWidths: labels.map((label) => getComputedStyle(label).borderInlineStartWidth),
      borderRadii: labels.map((label) => getComputedStyle(label).borderTopLeftRadius),
    };
  });
  expect(mobileOptionMetrics.display).toBe('flex');
  expect(mobileOptionMetrics.direction).toBe('column');
  expect(mobileOptionMetrics.labelWidths).toEqual([
    mobileOptionMetrics.groupWidth,
    mobileOptionMetrics.groupWidth,
  ]);
  expect(mobileOptionMetrics.labelTops[1]).toBe(mobileOptionMetrics.firstLabelBottom);
  expect(mobileOptionMetrics.labelJustify).toEqual(['flex-start', 'flex-start']);
  expect(mobileOptionMetrics.labelTextAlign).toEqual(['start', 'start']);
  expect(mobileOptionMetrics.borderInlineStartWidths).toEqual(['1px', '1px']);
  expect(mobileOptionMetrics.borderRadii).toEqual(['8px', '0px']);
});

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
    author: 'community-comment-author',
    avatar: 'community-comment-author-avatar',
    form: 'community-comment-form',
    empty: 'community-comments-empty',
    unavailable: 'community-comment-unavailable',
  },
  {
    name: 'content basic',
    commonStylesheet: 'modules/content/theme/basic/assets/common.css',
    stylesheet: 'modules/content/theme/basic/assets/module.css',
    wrapperOpen: '<main class="content-page">',
    wrapperClose: '</main>',
    panel: 'content-comments-panel',
    header: 'content-comments-panel-header',
    count: 'content-comments-count',
    list: 'content-comment-list',
    item: 'content-comment-item',
    author: 'content-comment-author',
    avatar: 'content-comment-author-avatar',
    form: 'content-comment-form',
    empty: 'content-comments-empty',
    unavailable: 'content-comment-unavailable',
  },
  {
    name: 'quiz basic',
    commonStylesheet: 'modules/quiz/theme/basic/assets/common.css',
    stylesheet: 'modules/quiz/theme/basic/assets/module.css',
    wrapperOpen: '<main class="sr-quiz-page"><div class="quiz-page-main">',
    wrapperClose: '</div></main>',
    panel: 'quiz-comments-panel',
    header: 'quiz-comments-panel-header',
    count: 'quiz-comments-count',
    list: 'quiz-comment-list',
    item: 'quiz-comment-item',
    author: 'quiz-comment-author',
    avatar: 'quiz-comment-author-avatar',
    form: 'quiz-comment-form',
    empty: 'quiz-comments-empty',
    unavailable: 'quiz-comment-unavailable',
  },
  {
    name: 'survey basic',
    commonStylesheet: 'modules/survey/theme/basic/assets/common.css',
    stylesheet: 'modules/survey/theme/basic/assets/module.css',
    wrapperOpen: '<main class="sr-survey-page"><div class="survey-page-main">',
    wrapperClose: '</div></main>',
    panel: 'survey-comments-panel',
    header: 'survey-comments-panel-header',
    count: 'survey-comments-count',
    list: 'survey-comment-list',
    item: 'survey-comment-item',
    author: 'survey-comment-author',
    avatar: 'survey-comment-author-avatar',
    form: 'survey-comment-form',
    empty: 'survey-comments-empty',
    unavailable: 'survey-comment-unavailable',
  },
];

for (const fixture of panelFixtures) {
  test(`${fixture.name} comment panel keeps complete divider metrics`, async ({ page }) => {
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
          --radius: 8px;
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
            <li class="${fixture.item}"><div class="${fixture.author}" data-comment-author><img class="member-profile-image member-profile-image-size-medium ${fixture.avatar}" data-comment-avatar alt="" width="32" height="32"><span class="member-profile-image member-profile-image-size-medium member-profile-image-fallback member-default-avatar member-avatar-color-8 ${fixture.avatar}" data-comment-avatar-fallback aria-hidden="true">작</span><span>작성자</span></div><div>첫 댓글</div></li>
            <li class="${fixture.item}" data-comment-second><div>둘째 댓글</div></li>
          </ul>
          <form class="${fixture.form}"><p data-comment-form-copy>댓글 작성</p><button type="button">등록</button></form>
          <p class="${fixture.unavailable}" data-comment-unavailable>로그인하면 댓글을 작성할 수 있습니다.</p>
        </section>
      ${fixture.wrapperClose}</body></html>`);
    await page.addStyleTag({ path: path.join(root, fixture.commonStylesheet.replace('/common.css', '/reset.css')) });
    await page.addStyleTag({ path: path.join(root, 'modules/member/assets/public-identity.css') });
    await page.addStyleTag({ path: path.join(root, fixture.commonStylesheet) });
    await page.addStyleTag({ path: path.join(root, fixture.stylesheet) });

    const computed = async () => page.evaluate(() => {
      const panel = getComputedStyle(document.querySelector('section'));
      const header = getComputedStyle(document.querySelector('[data-comment-title]').parentElement);
      const title = getComputedStyle(document.querySelector('[data-comment-title]'));
      const second = getComputedStyle(document.querySelector('[data-comment-second]'));
      const author = getComputedStyle(document.querySelector('[data-comment-author]'));
      const avatar = getComputedStyle(document.querySelector('[data-comment-avatar]'));
      const avatarFallback = getComputedStyle(document.querySelector('[data-comment-avatar-fallback]'));
      const form = getComputedStyle(document.querySelector('form'));
      const formCopy = getComputedStyle(document.querySelector('[data-comment-form-copy]'));
      const unavailable = getComputedStyle(document.querySelector('[data-comment-unavailable]'));
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
        authorDisplay: author.display,
        authorAlign: author.alignItems,
        authorGap: author.gap,
        avatarSize: [avatar.width, avatar.height],
        avatarRadius: avatar.borderTopLeftRadius,
        avatarBorder: avatar.borderTopColor,
        avatarBackground: avatar.backgroundColor,
        avatarObjectFit: avatar.objectFit,
        avatarFallbackSize: [avatarFallback.width, avatarFallback.height],
        avatarFallbackRadius: avatarFallback.borderTopLeftRadius,
        avatarFallbackBorder: avatarFallback.borderTopColor,
        avatarFallbackBackground: avatarFallback.backgroundColor,
        avatarFallbackColor: avatarFallback.color,
        avatarFallbackDisplay: avatarFallback.display,
        avatarFallbackAlign: avatarFallback.alignItems,
        avatarFallbackJustify: avatarFallback.justifyContent,
        avatarFallbackFontWeight: avatarFallback.fontWeight,
        formDivider: form.borderTopColor,
        formPaddingTop: form.paddingTop,
        formCopyColor: formCopy.color,
        formCopyMarginBottom: formCopy.marginBottom,
        unavailableColor: unavailable.color,
        unavailableMargin: unavailable.margin,
        unavailablePaddingBlock: [unavailable.paddingTop, unavailable.paddingBottom],
      };
    });

    const computedByScheme = {};
    for (const scheme of ['light', 'dark']) {
      if (scheme === 'dark') {
        await page.locator('html').evaluate((element) => element.setAttribute('data-color-scheme', 'dark'));
      }
      const styles = await computed();
      computedByScheme[scheme] = styles;
      expect(styles.panelBackground).toBe(scheme === 'dark' ? 'rgb(17, 24, 39)' : 'rgb(255, 255, 255)');
      expect(styles.panelBorder).toBe(scheme === 'dark' ? 'rgb(71, 85, 105)' : 'rgb(204, 212, 224)');
      expect(styles.panelRadius).toBe('12px');
      expect(styles.panelGap).toBe('18px');
      expect(styles.panelPaddingBottom).toBe('22px');
      expect(styles.titleMarginBottom).toBe('0px');
      expect(styles.itemPaddingTop).toBe('18px');
      expect(styles.authorDisplay).toBe('flex');
      expect(styles.authorAlign).toBe('center');
      expect(styles.authorGap).toBe('8px');
      expect(styles.avatarSize).toEqual(['32px', '32px']);
      expect(styles.avatarRadius).toBe('50%');
      expect(styles.avatarBorder).not.toBe('rgba(0, 0, 0, 0)');
      expect(styles.avatarBackground).not.toBe('rgba(0, 0, 0, 0)');
      expect(styles.avatarObjectFit).toBe('cover');
      expect(styles.avatarFallbackSize).toEqual(['32px', '32px']);
      expect(styles.avatarFallbackRadius).toBe('50%');
      expect(styles.avatarFallbackBorder).toBe('rgba(0, 0, 0, 0)');
      expect(styles.avatarFallbackBackground).toBe('rgb(79, 70, 229)');
      expect(styles.avatarFallbackColor).toBe('rgb(255, 255, 255)');
      expect(styles.avatarFallbackDisplay).toBe('flex');
      expect(styles.avatarFallbackAlign).toBe('center');
      expect(styles.avatarFallbackJustify).toBe('center');
      expect(styles.avatarFallbackFontWeight).toBe('700');
      expect(styles.formPaddingTop).toBe('20px');
      expect(styles.formCopyColor).toBe(scheme === 'dark' ? 'rgb(229, 236, 245)' : 'rgb(23, 32, 51)');
      expect(styles.formCopyMarginBottom).toBe('0px');
      expect(styles.unavailableColor).toBe(scheme === 'dark' ? 'rgb(174, 184, 199)' : 'rgb(104, 115, 134)');
      expect(styles.unavailableMargin).toBe('0px');
      expect(styles.unavailablePaddingBlock).toEqual(['10px', '10px']);
      expect(styles.headerDivider).toBe(styles.itemDivider);
      expect(styles.formDivider).toBe(styles.itemDivider);
    }
    expect(computedByScheme.dark.avatarBorder).not.toBe(computedByScheme.light.avatarBorder);
    expect(computedByScheme.dark.avatarBackground).not.toBe(computedByScheme.light.avatarBackground);

    await page.locator('[data-comment-avatar]').evaluate((element) => {
      element.classList.remove('member-profile-image-size-medium');
      element.classList.add('member-profile-image-size-small');
    });
    await page.locator('[data-comment-avatar-fallback]').evaluate((element) => {
      element.classList.remove('member-profile-image-size-medium');
      element.classList.add('member-profile-image-size-small');
    });
    expect((await computed()).avatarSize).toEqual(['24px', '24px']);
    expect((await computed()).avatarFallbackSize).toEqual(['24px', '24px']);
    await page.locator('[data-comment-avatar]').evaluate((element) => {
      element.classList.remove('member-profile-image-size-small');
      element.classList.add('member-profile-image-size-large');
    });
    await page.locator('[data-comment-avatar-fallback]').evaluate((element) => {
      element.classList.remove('member-profile-image-size-small');
      element.classList.add('member-profile-image-size-large');
    });
    expect((await computed()).avatarSize).toEqual(['40px', '40px']);
    expect((await computed()).avatarFallbackSize).toEqual(['40px', '40px']);
    await page.locator('[data-comment-avatar]').evaluate((element) => element.style.setProperty('--member-profile-image-size', '29px'));
    await page.locator('[data-comment-avatar-fallback]').evaluate((element) => element.style.setProperty('--member-profile-image-size', '29px'));
    expect((await computed()).avatarSize).toEqual(['29px', '29px']);
    expect((await computed()).avatarFallbackSize).toEqual(['29px', '29px']);
  });

  test(`${fixture.name} empty guest comment state keeps the complete non-interactive flow`, async ({ page }) => {
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
          --radius: 8px;
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
        <section class="${fixture.panel}" data-comment-panel>
          <div class="${fixture.header}" data-comment-header><h2>댓글 <span class="${fixture.count}">0</span></h2></div>
          <p class="${fixture.empty}" data-comment-empty>댓글이 없습니다.</p>
          <p class="${fixture.unavailable}" data-comment-unavailable>로그인하면 댓글을 작성할 수 있습니다.</p>
        </section>
      ${fixture.wrapperClose}</body></html>`);
    await page.addStyleTag({ path: path.join(root, fixture.commonStylesheet.replace('/common.css', '/reset.css')) });
    await page.addStyleTag({ path: path.join(root, fixture.commonStylesheet) });
    await page.addStyleTag({ path: path.join(root, fixture.stylesheet) });

    expect(await page.locator('[data-comment-panel] a, [data-comment-panel] button').count()).toBe(0);
    expect(await page.locator('[data-comment-panel] > *').evaluateAll((elements) => elements.map((element) => element.getAttribute('data-comment-header') !== null
      ? 'header'
      : (element.getAttribute('data-comment-empty') !== null ? 'empty' : 'unavailable')))).toEqual(['header', 'empty', 'unavailable']);

    for (const scheme of ['light', 'dark']) {
      await page.locator('html').evaluate((element, nextScheme) => element.setAttribute('data-color-scheme', nextScheme), scheme);
      const state = await page.evaluate(() => {
        const panel = document.querySelector('[data-comment-panel]');
        const header = document.querySelector('[data-comment-header]');
        const empty = document.querySelector('[data-comment-empty]');
        const unavailable = document.querySelector('[data-comment-unavailable]');
        const panelStyle = getComputedStyle(panel);
        const emptyStyle = getComputedStyle(empty);
        const unavailableStyle = getComputedStyle(unavailable);
        const headerRect = header.getBoundingClientRect();
        const emptyRect = empty.getBoundingClientRect();
        const unavailableRect = unavailable.getBoundingClientRect();
        return {
          panelBackground: panelStyle.backgroundColor,
          panelBorder: panelStyle.borderTopColor,
          panelGap: panelStyle.gap,
          emptyColor: emptyStyle.color,
          emptyMargin: emptyStyle.margin,
          emptyPaddingBlock: [emptyStyle.paddingTop, emptyStyle.paddingBottom],
          unavailableColor: unavailableStyle.color,
          unavailableMargin: unavailableStyle.margin,
          unavailablePaddingBlock: [unavailableStyle.paddingTop, unavailableStyle.paddingBottom],
          headerToEmptyGap: Math.round(emptyRect.top - headerRect.bottom),
          emptyToUnavailableGap: Math.round(unavailableRect.top - emptyRect.bottom),
          emptyText: empty.textContent.trim(),
          unavailableText: unavailable.textContent.trim(),
        };
      });
      expect(state.panelBackground).toBe(scheme === 'dark' ? 'rgb(17, 24, 39)' : 'rgb(255, 255, 255)');
      expect(state.panelBorder).toBe(scheme === 'dark' ? 'rgb(71, 85, 105)' : 'rgb(204, 212, 224)');
      expect(state.panelGap).toBe('18px');
      expect(state.emptyColor).toBe(scheme === 'dark' ? 'rgb(174, 184, 199)' : 'rgb(104, 115, 134)');
      expect(state.emptyMargin).toBe('0px');
      expect(state.emptyPaddingBlock).toEqual(['10px', '10px']);
      expect(state.unavailableColor).toBe(state.emptyColor);
      expect(state.unavailableMargin).toBe('0px');
      expect(state.unavailablePaddingBlock).toEqual(['10px', '10px']);
      expect(state.headerToEmptyGap).toBe(18);
      expect(state.emptyToUnavailableGap).toBe(18);
      expect(state.emptyText).toBe('댓글이 없습니다.');
      expect(state.unavailableText).toBe('로그인하면 댓글을 작성할 수 있습니다.');
    }
  });
}
