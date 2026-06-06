<?php

$seoTitle = (string) ($page['seo_title'] ?: $page['title']);
$seoDescription = (string) ($page['seo_description'] ?: $page['summary']);
$seo = [
    'title' => $seoTitle,
    'description' => $seoDescription,
    'canonical' => sr_content_path((string) $page['slug']),
    'og' => [
        'title' => $seoTitle,
        'description' => $seoDescription,
        'type' => 'article',
    ],
];
if (sr_content_clean_cover_image_url((string) ($page['cover_image_url'] ?? '')) !== '') {
    $seo['og']['image'] = (string) $page['cover_image_url'];
}
$pageLayoutKey = sr_public_layout_normalize_key((string) ($page['layout_key'] ?? ''));
if ($pageLayoutKey === '' || !isset(sr_public_layout_options($pdo ?? null)[$pageLayoutKey])) {
    $pageLayoutKey = sr_public_layout_key($site ?? null, $pdo ?? null);
}
$contentLayoutSettings = isset($contentLayoutSettings) && is_array($contentLayoutSettings) ? $contentLayoutSettings : sr_content_settings($pdo);
$contentPublisherName = trim((string) (($site ?? [])['name'] ?? ($site ?? [])['site_name'] ?? 'Saanraan'));
$contentPublisherName = $contentPublisherName !== '' ? $contentPublisherName : 'Saanraan';
$contentPublishedAt = (string) ($page['published_at'] ?? '');
$contentDateText = $contentPublishedAt !== '' ? $contentPublishedAt : (string) ($page['updated_at'] ?? '');
$contentStylesheets = [
    '/modules/banner/assets/public.css',
    '/modules/popup_layer/assets/public.css',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_public_layout_context($contentLayoutSettings, [
    'layout_key' => $pageLayoutKey,
    'stylesheets' => $contentStylesheets,
]));
?>
<main class="content-public content-public-basic">
    <?php if (function_exists('sr_popup_layer_render_public_layer') && sr_module_enabled($pdo, 'popup_layer')) { ?>
        <?php echo sr_popup_layer_render_public_layer($pdo, (int) ($page['popup_layer_id'] ?? 0)); ?>
    <?php } ?>

    <?php echo sr_render_output_slot($pdo, [
        'module_key' => 'content',
        'point_key' => 'content.view',
        'slot_key' => 'before_content',
        'subject_id' => (string) $page['id'],
    ]); ?>
    <?php if (function_exists('sr_banner_render_public_banner') && sr_module_enabled($pdo, 'banner')) { ?>
        <?php echo sr_banner_render_public_banner($pdo, (int) ($page['banner_before_content_id'] ?? 0)); ?>
    <?php } ?>

    <article class="content-article">
        <div class="content-reading-panel">
        <header class="content-header">
            <h1><?php echo sr_e((string) $page['title']); ?></h1>
            <?php if ((string) $page['summary'] !== '') { ?>
                <p><?php echo sr_e((string) $page['summary']); ?></p>
            <?php } ?>
            <div class="content-meta" aria-label="<?php echo sr_e('콘텐츠 정보'); ?>">
                <span><?php echo sr_e($contentPublisherName); ?></span>
                <?php if ($contentDateText !== '') { ?>
                    <span><?php echo sr_content_time_html($contentDateText); ?></span>
                <?php } ?>
            </div>
        </header>
        <?php if ((string) ($page['cover_image_url'] ?? '') !== '') { ?>
            <figure class="content-cover-figure">
                <?php echo sr_content_cover_image_html($page, 'content-cover-image', (string) $page['title']); ?>
            </figure>
        <?php } ?>
        <?php if (empty($pageAccess['allowed'])) { ?>
            <div class="content-body">
                <p><?php echo sr_e((string) ($pageAccess['message'] ?? sr_t('content::ui.content.7d2dd480'))); ?></p>
                <?php if ((string) ($pageAccess['error_key'] ?? '') === 'asset_confirmation_required') { ?>
                    <form method="post" action="<?php echo sr_e(sr_url(sr_content_path((string) $page['slug']))); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <button type="submit" class="btn btn-solid-primary">
                            <?php echo sr_e(sr_t('content::ui.text.ac5b575f')); ?>
                        </button>
                    </form>
                <?php } ?>
            </div>
        <?php } else { ?>
            <?php if ((string) ($pageActionNotice ?? '') !== '') { ?>
                <p class="content-access-notice"><?php echo sr_e((string) $pageActionNotice); ?></p>
            <?php } ?>
            <?php if (is_array($pageActionErrors ?? null)) { ?>
                <?php foreach ($pageActionErrors as $pageActionError) { ?>
                    <p class="content-access-notice"><?php echo sr_e((string) $pageActionError); ?></p>
                <?php } ?>
            <?php } ?>
            <?php if (!empty($pageAccess['charged'])) { ?>
                <p class="content-access-notice">
                    <?php echo sr_e((string) ($pageAccess['asset_label'] ?? sr_t('content::ui.member.415a098e'))); ?>
                    <?php echo sr_e(number_format((int) ($pageAccess['amount'] ?? 0))); ?> <?php echo sr_e(sr_t('content::ui.text.375151be')); ?>
                </p>
            <?php } ?>
            <?php if (!empty($pageAccess['coupon_used'])) { ?>
                <p class="content-access-notice">쿠폰으로 열람했습니다.</p>
            <?php } ?>
            <div class="content-body">
                <?php echo sr_content_body_html($page); ?>
            </div>
            <?php if (is_array($contentQuizLinks ?? null) && $contentQuizLinks !== []) { ?>
                <section class="content-quiz-links">
                    <h2>퀴즈</h2>
                    <?php foreach ($contentQuizLinks as $contentQuizIndex => $contentQuiz) { ?>
                        <?php
                        $contentQuizReturnTo = sr_content_path((string) $page['slug']);
                        $contentQuizQuery = http_build_query([
                            'return_to' => $contentQuizReturnTo,
                            'source_module' => 'content',
                            'source_type' => 'content_item',
                            'source_id' => (string) (int) ($page['id'] ?? 0),
                        ]);
                        $contentQuizUrl = '/quiz/' . rawurlencode((string) ($contentQuiz['quiz_key'] ?? '')) . '?' . $contentQuizQuery;
                        $contentQuizDialogId = 'content_quiz_dialog_' . (string) $contentQuizIndex;
                        $contentQuizFrameTitle = (string) ($contentQuiz['title'] ?? '퀴즈');
                        ?>
                        <div class="content-quiz-link">
                            <h3><?php echo sr_e((string) ($contentQuiz['title'] ?? '퀴즈')); ?></h3>
                            <?php if ((string) ($contentQuiz['description'] ?? '') !== '') { ?>
                                <p><?php echo sr_e((string) ($contentQuiz['description'] ?? '')); ?></p>
                            <?php } ?>
                            <button type="button" class="btn btn-solid-primary" data-content-quiz-dialog-open="<?php echo sr_e($contentQuizDialogId); ?>"><?php echo sr_e((string) (($contentQuiz['cta_label'] ?? '') !== '' ? $contentQuiz['cta_label'] : '퀴즈 풀기')); ?></button>
                            <a class="btn btn-solid-light" href="<?php echo sr_e(sr_url($contentQuizUrl)); ?>">새 페이지</a>
                            <dialog id="<?php echo sr_e($contentQuizDialogId); ?>" class="content-quiz-dialog" aria-label="<?php echo sr_e($contentQuizFrameTitle); ?>" aria-modal="true">
                                <form method="dialog" class="content-quiz-dialog-toolbar">
                                    <button type="submit" class="btn btn-sm btn-solid-light">닫기</button>
                                </form>
                                <iframe title="<?php echo sr_e($contentQuizFrameTitle); ?>" src="<?php echo sr_e(sr_url($contentQuizUrl)); ?>" loading="lazy"></iframe>
                            </dialog>
                        </div>
                    <?php } ?>
                    <script>
                    (function () {
                        document.addEventListener('click', function (event) {
                            var opener = event.target && event.target.closest ? event.target.closest('[data-content-quiz-dialog-open]') : null;
                            if (!opener) {
                                return;
                            }
                            var dialog = document.getElementById(opener.getAttribute('data-content-quiz-dialog-open'));
                            if (!dialog || typeof dialog.showModal !== 'function') {
                                var fallback = opener.parentNode ? opener.parentNode.querySelector('a[href]') : null;
                                if (fallback) {
                                    window.location.href = fallback.href;
                                }
                                return;
                            }
                            dialog.showModal();
                        });
                    }());
                    </script>
                </section>
            <?php } ?>
            <?php if (is_array($contentSeriesContext ?? null) && is_array($contentSeriesContext['items'] ?? null) && $contentSeriesContext['items'] !== []) { ?>
                <nav class="content-series-nav" aria-label="<?php echo sr_e('시리즈 콘텐츠'); ?>">
                    <h2><?php echo sr_e((string) $contentSeriesContext['title']); ?></h2>
                    <?php if ((string) ($contentSeriesContext['description'] ?? '') !== '') { ?>
                        <p><?php echo sr_e((string) $contentSeriesContext['description']); ?></p>
                    <?php } ?>
                    <?php $contentSeriesPriceText = sr_content_series_price_summary_text($pdo, is_array($contentSeriesContext['price_summary'] ?? null) ? $contentSeriesContext['price_summary'] : []); ?>
                    <?php if ($contentSeriesPriceText !== '') { ?>
                        <p><?php echo sr_e($contentSeriesPriceText); ?></p>
                    <?php } ?>
                    <?php
                    $contentSeriesPreviousItem = null;
                    $contentSeriesNextItem = null;
                    foreach ($contentSeriesContext['items'] as $seriesNavigationItem) {
                        if (!empty($seriesNavigationItem['series_is_previous'])) {
                            $contentSeriesPreviousItem = $seriesNavigationItem;
                        } elseif (!empty($seriesNavigationItem['series_is_next'])) {
                            $contentSeriesNextItem = $seriesNavigationItem;
                        }
                    }
                    ?>
                    <?php if (is_array($contentSeriesPreviousItem) || is_array($contentSeriesNextItem)) { ?>
                        <p>
                            <?php if (is_array($contentSeriesPreviousItem)) { ?>
                                <a href="<?php echo sr_e(sr_url(sr_content_path((string) $contentSeriesPreviousItem['slug']))); ?>"><?php echo sr_e('이전 콘텐츠'); ?>: <?php echo sr_e((string) $contentSeriesPreviousItem['content_title']); ?></a>
                            <?php } ?>
                            <?php if (is_array($contentSeriesPreviousItem) && is_array($contentSeriesNextItem)) { ?>
                                /
                            <?php } ?>
                            <?php if (is_array($contentSeriesNextItem)) { ?>
                                <a href="<?php echo sr_e(sr_url(sr_content_path((string) $contentSeriesNextItem['slug']))); ?>"><?php echo sr_e('다음 콘텐츠'); ?>: <?php echo sr_e((string) $contentSeriesNextItem['content_title']); ?></a>
                            <?php } ?>
                        </p>
                    <?php } ?>
                    <ol>
                        <?php foreach ($contentSeriesContext['items'] as $seriesItem) { ?>
                            <li>
                                <?php if ((int) ($seriesItem['content_id'] ?? 0) === (int) $page['id']) { ?>
                                    <strong>
                                        <?php echo sr_e((string) ($seriesItem['episode_label'] ?? '') !== '' ? (string) $seriesItem['episode_label'] . ' - ' : ''); ?><?php echo sr_e((string) $seriesItem['content_title']); ?>
                                    </strong>
                                <?php } else { ?>
                                    <a href="<?php echo sr_e(sr_url(sr_content_path((string) $seriesItem['slug']))); ?>">
                                        <?php echo sr_e((string) ($seriesItem['episode_label'] ?? '') !== '' ? (string) $seriesItem['episode_label'] . ' - ' : ''); ?><?php echo sr_e((string) $seriesItem['content_title']); ?>
                                    </a>
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ol>
                </nav>
            <?php } ?>
            <?php if (is_array($contentFiles ?? null) && $contentFiles !== []) { ?>
                <section class="content-downloads">
                    <h2><?php echo sr_e(sr_t('content::ui.text.0a4ca9bc')); ?></h2>
                    <ul>
                        <?php foreach ($contentFiles as $contentFile) { ?>
                            <li>
                                <?php if (empty($contentAdminPreview)) { ?>
                                    <?php $contentFileNeedsConfirmation = (int) ($contentFile['asset_download_enabled'] ?? 0) === 1 && sr_content_asset_policy_requires_confirmation((string) ($contentFile['asset_charge_policy'] ?? 'once')); ?>
                                    <?php if ($contentFileNeedsConfirmation) { ?>
                                        <form method="post" action="<?php echo sr_e(sr_url('/content/download')); ?>">
                                            <?php echo sr_csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo sr_e((string) $contentFile['id']); ?>">
                                            <input type="hidden" name="content_id" value="<?php echo sr_e((string) (int) ($page['id'] ?? 0)); ?>">
                                            <button type="submit" class="btn btn-solid-light">
                                                <?php echo sr_e((string) $contentFile['title']); ?>
                                            </button>
                                        </form>
                                    <?php } else { ?>
                                        <a href="<?php echo sr_e(sr_url('/content/download?id=' . rawurlencode((string) $contentFile['id']) . '&content_id=' . rawurlencode((string) (int) ($page['id'] ?? 0)))); ?>">
                                            <?php echo sr_e((string) $contentFile['title']); ?>
                                        </a>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span>
                                        <?php echo sr_e((string) $contentFile['title']); ?>
                                    </span>
                                <?php } ?>
                                <small>
                                    <?php echo sr_e((string) $contentFile['original_name']); ?>
                                    · <?php echo sr_e(sr_content_format_bytes((int) $contentFile['size_bytes'])); ?>
                                    <?php if ((int) ($contentFile['asset_download_enabled'] ?? 0) === 1) { ?>
                                        · <?php echo sr_e(sr_content_asset_module_labels((string) ($contentFile['asset_module'] ?? ''), $pdo)); ?>
                                        <?php echo sr_e(number_format((int) ($contentFile['asset_download_amount'] ?? 0))); ?>
                                    <?php } ?>
                                </small>
                            </li>
                        <?php } ?>
                    </ul>
                </section>
            <?php } ?>
            <?php if (empty($contentAdminPreview) && sr_content_asset_action_required($page)) { ?>
                <form method="post" action="<?php echo sr_e(sr_url('/content/action')); ?>" class="content-action-form">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="content_id" value="<?php echo sr_e((string) $page['id']); ?>">
                    <button type="submit" class="btn btn-solid-primary">
                        <?php echo sr_e((string) ($page['asset_action_label'] ?? sr_t('content::ui.text.727333ab'))); ?>
                    </button>
                </form>
            <?php } ?>
        <?php } ?>
        </div>
        <?php if (!empty($pageAccess['allowed'])) { ?>
            <section id="content-comments" class="content-comments">
                <div class="content-comments-panel-header">
                    <h2>댓글</h2>
                    <?php if (is_array($account ?? null) && !$contentAdminPreview) { ?>
                        <a href="#content-comment-form" class="btn btn-solid-light">작성</a>
                    <?php } ?>
                </div>
                <?php if ((string) ($contentCommentNotice ?? '') !== '') { ?>
                    <p><?php echo sr_e((string) $contentCommentNotice); ?></p>
                <?php } ?>
                <?php if (is_array($contentCommentErrors ?? null) && $contentCommentErrors !== []) { ?>
                    <ul>
                        <?php foreach ($contentCommentErrors as $contentCommentError) { ?>
                            <li><?php echo sr_e((string) $contentCommentError); ?></li>
                        <?php } ?>
                    </ul>
                <?php } ?>
                <?php if (is_array($contentComments ?? null) && $contentComments !== []) { ?>
                    <ul>
                        <?php foreach ($contentComments as $contentComment) { ?>
                            <li>
                                <?php
                                $contentCommentCanViewBody = sr_content_account_can_view_comment_body($contentComment, $page, is_array($account ?? null) ? $account : null);
                                $contentCommentCanEdit = is_array($account ?? null) && sr_content_account_can_edit_comment($contentComment, $account);
                                $contentCommentCanDelete = is_array($account ?? null) && sr_content_account_can_delete_comment($contentComment, $account, $pdo);
                                $contentCommentCanHide = sr_content_account_can_hide_comment($pdo, $contentComment, is_array($account ?? null) ? $account : null);
                                $contentCommentEditId = 'content_comment_edit_' . (string) $contentComment['id'];
                                $contentCommentCreatedAt = (string) ($contentComment['created_at'] ?? '');
                                ?>
                                <div class="content-comment-meta">
                                    <strong><?php echo sr_e((string) ($contentComment['author_public_name'] ?? $contentComment['author_display_name'] ?? '회원')); ?></strong>
                                    <?php if ($contentCommentCreatedAt !== '') { ?>
                                        <?php echo sr_content_time_html($contentCommentCreatedAt); ?>
                                    <?php } ?>
                                    <?php if ((int) ($contentComment['is_secret'] ?? 0) === 1) { ?>
                                        <span>비밀</span>
                                    <?php } ?>
                                </div>
                                <?php if ($contentCommentCanViewBody) { ?>
                                    <p><?php echo nl2br(sr_e((string) $contentComment['body_text'])); ?></p>
                                <?php } else { ?>
                                    <p class="content-comment-secret">비밀 댓글입니다.</p>
                                <?php } ?>
                                <?php if ($contentCommentCanEdit || $contentCommentCanDelete || $contentCommentCanHide) { ?>
                                    <div class="content-comment-actions">
                                        <?php if ($contentCommentCanEdit) { ?>
                                            <details>
                                                <summary class="btn btn-solid-light">수정</summary>
                                                <form method="post" action="<?php echo sr_e(sr_url('/content/comment/edit')); ?>">
                                                    <?php echo sr_csrf_field(); ?>
                                                    <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $contentComment['id']); ?>">
                                                    <label for="<?php echo sr_e($contentCommentEditId); ?>">댓글 수정</label>
                                                    <textarea id="<?php echo sr_e($contentCommentEditId); ?>" name="body_text" rows="3" cols="60"><?php echo sr_e((string) $contentComment['body_text']); ?></textarea>
                                                    <label class="content-comment-secret-toggle">
                                                        <input type="checkbox" name="is_secret" value="1"<?php echo (int) ($contentComment['is_secret'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                                        <span>비밀 댓글</span>
                                                    </label>
                                                    <button type="submit" class="btn btn-solid-light">저장</button>
                                                </form>
                                            </details>
                                        <?php } ?>
                                        <?php if ($contentCommentCanDelete) { ?>
                                            <form method="post" action="<?php echo sr_e(sr_url('/content/comment/delete')); ?>">
                                                <?php echo sr_csrf_field(); ?>
                                                <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $contentComment['id']); ?>">
                                                <button type="submit" class="btn btn-solid-light">삭제</button>
                                            </form>
                                        <?php } ?>
                                        <?php if ($contentCommentCanHide) { ?>
                                            <form method="post" action="<?php echo sr_e(sr_url('/content/comment/hide')); ?>">
                                                <?php echo sr_csrf_field(); ?>
                                                <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $contentComment['id']); ?>">
                                                <button type="submit" class="btn btn-solid-light">숨기기</button>
                                            </form>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } else { ?>
                    <p>등록된 댓글이 없습니다.</p>
                <?php } ?>
                <?php if (is_array($account ?? null) && !$contentAdminPreview) { ?>
                    <form id="content-comment-form" method="post" action="<?php echo sr_e(sr_url('/content/comment')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="content_id" value="<?php echo sr_e((string) $page['id']); ?>">
                        <label for="content_comment_body">댓글</label>
                        <textarea id="content_comment_body" name="body_text" rows="4" cols="60"><?php echo sr_e((string) ($contentCommentBody ?? '')); ?></textarea>
                        <label class="content-comment-secret-toggle">
                            <input type="checkbox" name="is_secret" value="1">
                            <span>비밀 댓글</span>
                        </label>
                        <p class="admin-form-help">@이름 형식으로 회원을 언급할 수 있습니다.</p>
                        <button type="submit" class="btn btn-solid-light">댓글 등록</button>
                    </form>
                <?php } ?>
            </section>
        <?php } ?>
    </article>

    <?php echo sr_render_output_slot($pdo, [
        'module_key' => 'content',
        'point_key' => 'content.view',
        'slot_key' => 'after_content',
        'subject_id' => (string) $page['id'],
    ]); ?>
    <?php if (function_exists('sr_banner_render_public_banner') && sr_module_enabled($pdo, 'banner')) { ?>
        <?php echo sr_banner_render_public_banner($pdo, (int) ($page['banner_after_content_id'] ?? 0)); ?>
    <?php } ?>
</main>
<?php sr_public_layout_end(); ?>
