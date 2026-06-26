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
$contentPublisherName = sr_site_display_name(is_array($site ?? null) ? $site : null, $pdo ?? null);
$contentPublishedAt = (string) ($page['published_at'] ?? '');
$contentDateText = $contentPublishedAt !== '' ? $contentPublishedAt : (string) ($page['updated_at'] ?? '');
$contentStylesheets = [
    '/modules/banner/assets/module.css',
    '/modules/popup_layer/assets/module.css',
    '/modules/reaction/assets/module.css',
];
if (is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}
$contentReactionCommentTargets = [];
if (
    function_exists('sr_reaction_resolve_targets')
    && empty($contentAdminPreview)
    && is_array($contentComments ?? null)
    && $contentComments !== []
) {
    $contentReactionCommentIds = [];
    foreach ($contentComments as $contentReactionComment) {
        $contentReactionCommentId = (int) ($contentReactionComment['id'] ?? 0);
        if ($contentReactionCommentId > 0) {
            $contentReactionCommentIds[] = (string) $contentReactionCommentId;
        }
    }
    $contentReactionCommentTargets = sr_reaction_resolve_targets(
        $pdo,
        'content',
        'comment',
        $contentReactionCommentIds,
        is_array($account ?? null) ? (int) ($account['id'] ?? 0) : 0
    );
}
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_public_layout_context($contentLayoutSettings, [
    'layout_key' => $pageLayoutKey,
    'stylesheets' => $contentStylesheets,
]));
?>
<main class="content-page content-page-basic">
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
                <?php if ((string) ($pageAccess['error_key'] ?? '') === 'asset_confirmation_required') { ?>
                    <?php
                    $assetConfirmationAssetLabel = (string) ($pageAccess['asset_label'] ?? '');
                    $assetConfirmationAmount = (int) ($pageAccess['amount'] ?? 0);
                    $assetConfirmationMessage = trim($assetConfirmationAssetLabel . ' ' . number_format($assetConfirmationAmount)) . ' 차감 후 콘텐츠를 열람하시겠습니까?';
                    $assetConfirmationAction = sr_content_path((string) $page['slug']);
                    $assetConfirmationId = 0;
                    $assetConfirmationContentId = 0;
                    $assetConfirmationRequestToken = (string) ($pageAccess['confirmation_request_token'] ?? '');
                    $assetConfirmationTitle = '콘텐츠 열람 확인';
                    $assetConfirmationSubmitLabel = sr_t('content::ui.text.ac5b575f');
                    $assetConfirmationCouponIssues = is_array($pageAccess['coupon_issues'] ?? null) ? $pageAccess['coupon_issues'] : [];
                    $assetConfirmationModalId = 'content_asset_access_confirmation_modal';
                    include SR_ROOT . '/modules/content/views/asset-confirmation-modal.php';
                    ?>
                <?php } else { ?>
                    <p><?php echo sr_e((string) ($pageAccess['message'] ?? sr_t('content::ui.content.7d2dd480'))); ?></p>
                <?php } ?>
            </div>
        <?php } else { ?>
            <?php echo sr_public_feedback_toasts('content', (string) ($pageActionNotice ?? ''), is_array($pageActionErrors ?? null) ? $pageActionErrors : []); ?>
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
                <?php echo sr_content_body_html($page, $contentLayoutSettings, $pdo); ?>
            </div>
            <?php if (function_exists('sr_reaction_render_widget') && empty($contentAdminPreview)) { ?>
                <?php echo sr_reaction_render_widget($pdo, 'content', 'content', (string) (int) ($page['id'] ?? 0), is_array($account ?? null) ? $account : null); ?>
            <?php } ?>
            <?php if (is_array($contentQuizLinks ?? null) && $contentQuizLinks !== []) { ?>
                <section class="content-quiz-links">
                    <h2>퀴즈·테스트</h2>
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
                                    <?php $contentFileDownloadUrl = '/content/download?id=' . rawurlencode((string) $contentFile['id']) . '&content_id=' . rawurlencode((string) (int) ($page['id'] ?? 0)); ?>
                                    <?php $contentFileDownloadAccess = $contentFileNeedsConfirmation && is_array($account ?? null) ? sr_content_charge_file_download($pdo, $contentFile, (int) ($account['id'] ?? 0), false, '', 0, false) : []; ?>
                                    <?php if ($contentFileNeedsConfirmation && is_array($account ?? null) && (string) ($contentFileDownloadAccess['error_key'] ?? '') === 'asset_confirmation_required') { ?>
                                        <?php
                                        $assetConfirmationAssetLabel = (string) ($contentFileDownloadAccess['asset_label'] ?? '');
                                        $assetConfirmationAmount = (int) ($contentFileDownloadAccess['amount'] ?? 0);
                                        $assetConfirmationMessage = trim($assetConfirmationAssetLabel . ' ' . number_format($assetConfirmationAmount)) . ' 차감 후 파일을 다운로드하시겠습니까?';
                                        $assetConfirmationAction = '/content/download';
                                        $assetConfirmationId = (int) $contentFile['id'];
                                        $assetConfirmationContentId = (int) ($page['id'] ?? 0);
                                        $assetConfirmationRequestToken = (string) ($contentFileDownloadAccess['confirmation_request_token'] ?? '');
                                        $assetConfirmationTitle = (string) ($contentFile['title'] ?? sr_t('content::ui.text.0a4ca9bc'));
                                        $assetConfirmationSubmitLabel = sr_t('content::ui.text.0a4ca9bc');
                                        $assetConfirmationCouponIssues = is_array($contentFileDownloadAccess['coupon_issues'] ?? null) ? $contentFileDownloadAccess['coupon_issues'] : [];
                                        $assetConfirmationModalId = 'content_file_download_confirmation_' . (string) (int) ($contentFile['id'] ?? 0);
                                        $assetConfirmationOpen = false;
                                        $assetConfirmationCancelUrl = '';
                                        ?>
                                        <button type="button" class="btn btn-solid-light" data-overlay="#<?php echo sr_e($assetConfirmationModalId); ?>">
                                            <?php echo sr_e((string) $contentFile['title']); ?>
                                        </button>
                                        <?php include SR_ROOT . '/modules/content/views/asset-confirmation-modal.php'; ?>
                                    <?php } else { ?>
                                        <a href="<?php echo sr_e(sr_url($contentFileDownloadUrl)); ?>">
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
                <?php echo sr_public_feedback_toasts('content', (string) ($contentCommentNotice ?? ''), is_array($contentCommentErrors ?? null) ? $contentCommentErrors : []); ?>
                <?php if (is_array($contentComments ?? null) && $contentComments !== []) { ?>
                    <ul>
                        <?php foreach ($contentComments as $contentComment) { ?>
                            <?php
                            $contentCommentDepth = min(3, max(1, (int) ($contentComment['depth'] ?? 1)));
                            ?>
                            <li class="content-comment-depth-<?php echo sr_e((string) $contentCommentDepth); ?>">
                                <?php
                                $contentCommentCanViewBody = sr_content_account_can_view_comment_body($contentComment, $page, is_array($account ?? null) ? $account : null, $pdo);
                                $contentCommentCanEdit = is_array($account ?? null) && sr_content_account_can_edit_comment($contentComment, $account);
                                $contentCommentCanDelete = is_array($account ?? null) && sr_content_account_can_delete_comment($contentComment, $account, $pdo);
                                $contentCommentCanHide = sr_content_account_can_hide_comment($pdo, $contentComment, is_array($account ?? null) ? $account : null);
                                $contentCommentCanReply = is_array($account ?? null) && !$contentAdminPreview && $contentCommentCanViewBody && $contentCommentDepth < 3;
                                $contentCommentEditId = 'content_comment_edit_' . (string) $contentComment['id'];
                                $contentCommentEditModalId = 'content_comment_edit_modal_' . (string) (int) $contentComment['id'];
                                $contentCommentReplyId = 'content_comment_reply_' . (string) $contentComment['id'];
                                $contentCommentReplyModalId = 'content_comment_reply_modal_' . (string) (int) $contentComment['id'];
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
                                    <?php if ($contentCommentDepth > 1) { ?>
                                        <span>답글 <?php echo sr_e((string) $contentCommentDepth); ?>단계</span>
                                    <?php } ?>
                                </div>
                                <?php if ($contentCommentCanViewBody) { ?>
                                    <p><?php echo sr_member_mention_plain_text_html((string) $contentComment['body_text']); ?></p>
                                    <?php if (function_exists('sr_reaction_render_widget') && empty($contentAdminPreview)) { ?>
                                        <?php
                                        $contentCommentReactionId = (string) (int) ($contentComment['id'] ?? 0);
                                        $contentCommentReactionOptions = ['label' => '댓글 리액션'];
                                        if (isset($contentReactionCommentTargets[$contentCommentReactionId]) && is_array($contentReactionCommentTargets[$contentCommentReactionId])) {
                                            $contentCommentReactionOptions['resolved_target'] = $contentReactionCommentTargets[$contentCommentReactionId];
                                        }
                                        ?>
                                        <?php echo sr_reaction_render_widget($pdo, 'content', 'comment', $contentCommentReactionId, is_array($account ?? null) ? $account : null, $contentCommentReactionOptions); ?>
                                    <?php } ?>
                                <?php } else { ?>
                                    <p class="content-comment-secret">비밀 댓글입니다.</p>
                                <?php } ?>
                                <?php if ($contentCommentCanEdit || $contentCommentCanDelete || $contentCommentCanHide || $contentCommentCanReply) { ?>
                                    <div class="content-comment-actions">
                                        <?php if ($contentCommentCanReply) { ?>
                                            <button type="button" class="btn btn-ghost-default" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($contentCommentReplyModalId); ?>" data-overlay="#<?php echo sr_e($contentCommentReplyModalId); ?>">답글</button>
                                            <div id="<?php echo sr_e($contentCommentReplyModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($contentCommentReplyModalId . '_title'); ?>" aria-hidden="true" inert>
                                                <div class="modal-dialog">
                                                    <form method="post" action="<?php echo sr_e(sr_url('/content/comment')); ?>" class="modal-content">
                                                        <?php echo sr_csrf_field(); ?>
                                                        <input type="hidden" name="content_id" value="<?php echo sr_e((string) $page['id']); ?>">
                                                        <input type="hidden" name="parent_comment_id" value="<?php echo sr_e((string) $contentComment['id']); ?>">
                                                        <div class="modal-header">
                                                            <h3 id="<?php echo sr_e($contentCommentReplyModalId . '_title'); ?>" class="modal-title">답글 작성</h3>
                                                            <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($contentCommentReplyModalId); ?>">
                                                                <?php echo sr_material_icon_html('close', '', '닫기'); ?>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <strong class="content-comment-reply-source-label">댓글</strong>
                                                            <p class="content-comment-reply-source"><?php echo sr_member_mention_plain_text_html((string) $contentComment['body_text']); ?></p>
                                                            <label for="<?php echo sr_e($contentCommentReplyId); ?>">답글</label>
                                                            <textarea id="<?php echo sr_e($contentCommentReplyId); ?>" name="body_text" rows="3" cols="60" data-overlay-focus data-sr-mention-input data-sr-mention-endpoint="<?php echo sr_e(sr_url('/member/mention-search')); ?>"><?php echo (int) ($contentCommentParentId ?? 0) === (int) $contentComment['id'] ? sr_e((string) ($contentCommentBody ?? '')) : ''; ?></textarea>
                                                            <?php if (!empty($contentSecretCommentsEnabled)) { ?>
                                                                <label class="content-comment-secret-toggle">
                                                                    <input type="checkbox" name="is_secret" value="1"<?php echo (int) ($contentCommentParentId ?? 0) === (int) $contentComment['id'] && !empty($contentCommentIsSecret) ? ' checked' : ''; ?>>
                                                                    <span>비밀 댓글</span>
                                                                </label>
                                                            <?php } ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($contentCommentReplyModalId); ?>">닫기</button>
                                                            <button type="submit" class="btn btn-solid-primary modal-action">답글 등록</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php } ?>
                                        <?php if ($contentCommentCanEdit) { ?>
                                            <button type="button" class="btn btn-ghost-default" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($contentCommentEditModalId); ?>" data-overlay="#<?php echo sr_e($contentCommentEditModalId); ?>">수정</button>
                                            <div id="<?php echo sr_e($contentCommentEditModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($contentCommentEditModalId . '_title'); ?>" aria-hidden="true" inert>
                                                <div class="modal-dialog">
                                                    <form method="post" action="<?php echo sr_e(sr_url('/content/comment/edit')); ?>" class="modal-content">
                                                        <?php echo sr_csrf_field(); ?>
                                                        <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $contentComment['id']); ?>">
                                                        <div class="modal-header">
                                                            <h3 id="<?php echo sr_e($contentCommentEditModalId . '_title'); ?>" class="modal-title">댓글 수정</h3>
                                                            <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($contentCommentEditModalId); ?>">
                                                                <?php echo sr_material_icon_html('close', '', '닫기'); ?>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <label for="<?php echo sr_e($contentCommentEditId); ?>">댓글 수정</label>
                                                            <textarea id="<?php echo sr_e($contentCommentEditId); ?>" name="body_text" rows="3" cols="60" data-overlay-focus data-sr-mention-input data-sr-mention-endpoint="<?php echo sr_e(sr_url('/member/mention-search')); ?>"><?php echo sr_e((string) $contentComment['body_text']); ?></textarea>
                                                            <?php if (!empty($contentSecretCommentsEnabled) || (int) ($contentComment['is_secret'] ?? 0) === 1) { ?>
                                                                <label class="content-comment-secret-toggle">
                                                                    <input type="checkbox" name="is_secret" value="1"<?php echo (int) ($contentComment['is_secret'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                                                    <span>비밀 댓글</span>
                                                                </label>
                                                            <?php } ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($contentCommentEditModalId); ?>">닫기</button>
                                                            <button type="submit" class="btn btn-solid-primary modal-action">저장</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php } ?>
                                        <?php if ($contentCommentCanDelete) { ?>
                                            <form method="post" action="<?php echo sr_e(sr_url('/content/comment/delete')); ?>">
                                                <?php echo sr_csrf_field(); ?>
                                                <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $contentComment['id']); ?>">
                                                <button type="submit" class="btn btn-ghost-danger">삭제</button>
                                            </form>
                                        <?php } ?>
                                        <?php if ($contentCommentCanHide) { ?>
                                            <form method="post" action="<?php echo sr_e(sr_url('/content/comment/hide')); ?>">
                                                <?php echo sr_csrf_field(); ?>
                                                <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $contentComment['id']); ?>">
                                                <button type="submit" class="btn btn-ghost-default">숨기기</button>
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
                        <input type="hidden" name="parent_comment_id" value="0">
                        <label for="content_comment_body">댓글</label>
                        <textarea id="content_comment_body" name="body_text" rows="4" cols="60" data-sr-mention-input data-sr-mention-endpoint="<?php echo sr_e(sr_url('/member/mention-search')); ?>"><?php echo (int) ($contentCommentParentId ?? 0) < 1 ? sr_e((string) ($contentCommentBody ?? '')) : ''; ?></textarea>
                        <?php if (!empty($contentSecretCommentsEnabled)) { ?>
                            <label class="content-comment-secret-toggle">
                                <input type="checkbox" name="is_secret" value="1"<?php echo !empty($contentCommentIsSecret) ? ' checked' : ''; ?>>
                                <span>비밀 댓글</span>
                            </label>
                        <?php } ?>
                        <p class="form-help">@이름 형식으로 회원을 언급할 수 있습니다.</p>
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
<?php if (function_exists('sr_reaction_public_script_html')) { ?>
    <?php echo sr_reaction_public_script_html(); ?>
<?php } ?>
<?php sr_public_layout_end(); ?>
