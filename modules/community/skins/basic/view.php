<?php

$communityCommentFragmentResponse = !empty($communityCommentFragmentRequest);
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
$communityReactionsEnabled = sr_community_effective_board_reaction_enabled($pdo, is_array($postBoard ?? null) ? $postBoard : null, $communityLayoutSettings);
if (sr_module_enabled($pdo, 'reaction') && is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}
$communityReactionCommentTargets = [];
$communityReactionCommentSummaries = [];
if ($communityReactionsEnabled && sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_resolve_targets') && is_array($comments ?? null) && $comments !== []) {
    $communityReactionCommentIds = [];
    foreach ($comments as $communityReactionComment) {
        $communityReactionCommentId = (int) ($communityReactionComment['id'] ?? 0);
        if ($communityReactionCommentId > 0) {
            $communityReactionCommentIds[] = (string) $communityReactionCommentId;
        }
    }
    $communityReactionCommentTargets = sr_reaction_resolve_targets(
        $pdo,
        'community',
        'comment',
        $communityReactionCommentIds,
        is_array($account ?? null) ? (int) ($account['id'] ?? 0) : 0
    );
    if (sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_record_summaries')) {
        $communityReactionCommentSummaries = sr_reaction_record_summaries(
            $pdo,
            'community',
            'comment',
            $communityReactionCommentIds,
            is_array($account ?? null) ? (int) ($account['id'] ?? 0) : 0
        );
    }
}
$memberSettings = sr_member_settings($pdo);
$config = isset($config) && is_array($config) ? $config : sr_runtime_config();
if (!$communityCommentFragmentResponse) {
    $pageTitle = (string) $post['title'];
    $seo = sr_community_post_seo_meta($pdo, $post, empty($paidReadConfirmationRequired) && empty($paidReadBlocked) && !empty($canViewPostBody));
    if (sr_module_enabled($pdo, 'banner') && is_file(SR_ROOT . '/modules/banner/helpers.php')) {
        require_once SR_ROOT . '/modules/banner/helpers.php';
    }
    if (sr_module_enabled($pdo, 'popup_layer') && is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
        require_once SR_ROOT . '/modules/popup_layer/helpers.php';
    }
$communityLayoutContext = sr_community_public_layout_context($communityLayoutSettings, [
    'consumer_target' => 'community.post',
    'stylesheets' => array_merge(sr_community_skin_stylesheets($skinKey ?? 'basic'), sr_enabled_module_asset_paths($pdo ?? null, [
        'banner' => '/modules/banner/assets/module.css',
        'popup_layer' => '/modules/popup_layer/assets/module.css',
        'reaction' => '/modules/reaction/assets/module.css',
    ]), sr_community_post_body_embed_stylesheets($post, $communityLayoutSettings, $pdo ?? null)),
    'output_slots' => [
        ['module_key' => 'community', 'point_key' => 'community.post.view', 'slot_key' => 'before_content'],
        ['module_key' => 'community', 'point_key' => 'community.post.view', 'slot_key' => 'after_content'],
        ['module_key' => 'community', 'point_key' => 'community.post.view', 'slot_key' => 'before_comments'],
        ['module_key' => 'community', 'point_key' => 'community.post.view', 'slot_key' => 'after_comments'],
    ],
]);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, $communityLayoutContext);
$communityMainLabel = $pageTitle;
$communityFrameModifier = 'view';
$communityFrameSummaryDeferred = true;
$communityPostCommentCount = (int) ($post['published_comment_count'] ?? (is_array($comments ?? null) ? count($comments) : 0));
$communityPostBoardUrl = sr_url('/community/board?key=' . rawurlencode((string) $post['board_key']));
$memberFollowFeedback = isset($_SESSION['sr_member_follow_feedback']) && is_array($_SESSION['sr_member_follow_feedback'])
    ? $_SESSION['sr_member_follow_feedback']
    : ['notice' => '', 'errors' => []];
unset($_SESSION['sr_member_follow_feedback']);
?>
    <?php include SR_ROOT . '/modules/community/theme/basic/home-frame-start.php'; ?>
        <?php if (function_exists('sr_popup_layer_render_public_layer') && sr_module_enabled($pdo, 'popup_layer')) { ?>
            <?php echo sr_popup_layer_render_public_layer($pdo, (int) ($post['popup_layer_view_id'] ?? 0)); ?>
        <?php } ?>

        <p class="community-post-view-board">
            <a href="<?php echo sr_e($communityPostBoardUrl); ?>">
                <?php echo sr_e((string) $post['board_title']); ?>
            </a>
        </p>

        <article class="community-post-view">
            <header class="community-post-view-header">
            <h1 class="community-post-title community-post-view-title">
                <?php if ((int) ($post['is_notice'] ?? 0) === 1) { ?>
                    <span class="badge badge-soft-info community-post-notice-label"><?php echo sr_e('공지'); ?></span>
                <?php } ?>
                <?php echo sr_e($pageTitle); ?>
            </h1>
            <div class="community-post-view-meta">
                <?php if ((int) ($post['is_secret'] ?? 0) === 1) { ?>
                    <?php echo sr_e('비밀글'); ?>
                    /
                <?php } ?>
                <?php $communityPostAuthorLabel = sr_community_author_label_from_row($post, $config, $canViewMemberIdentifiers, $memberSettings, $pdo); ?>
                <?php echo sr_e(sr_t('community::ui.text.f99bc7dd')); ?> <?php echo sr_member_public_name_menu_html($pdo, is_array($account ?? null) ? $account : null, (int) ($post['author_account_id'] ?? 0), $communityPostAuthorLabel, [
                    'community_board_key' => (string) $post['board_key'],
                    'community_board_accessible' => is_array($postBoard ?? null),
                    'return_to' => (string) ($_SERVER['REQUEST_URI'] ?? '/'),
                    'is_following' => (string) ($communityFollowStatuses[(int) ($post['author_account_id'] ?? 0)] ?? '') === 'active',
                ]); ?>
                <?php echo sr_e(sr_t('community::ui.text.8619f779')); ?> <?php echo sr_community_time_html((string) $post['created_at']); ?>
                <?php echo sr_e(sr_t('community::ui.text.e83def32')); ?> <?php echo sr_e((string) $post['view_count']); ?>
                <?php if (!empty($categoryEnabled) && (string) ($post['category_title'] ?? '') !== '') { ?>
                    / <?php echo sr_e('카테고리'); ?>
                    <?php if ((string) ($post['category_status'] ?? '') === 'enabled' && (string) ($post['category_key'] ?? '') !== '') { ?>
                        <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $post['board_key']) . '&category=' . rawurlencode((string) $post['category_key']))); ?>"><?php echo sr_e((string) $post['category_title']); ?></a>
                    <?php } else { ?>
                        <?php echo sr_e((string) $post['category_title']); ?>
                    <?php } ?>
                <?php } ?>
            </div>
            <div class="community-post-view-actions">
                <a class="btn btn-ghost-default" href="<?php echo sr_e($communityPostBoardUrl); ?>"><?php echo sr_e(sr_t('community::ui.list.f07b3200')); ?></a>
                <button type="button" class="btn btn-ghost-default community-post-comments-jump" data-community-scroll-target="#comments" aria-label="<?php echo sr_e('댓글 ' . number_format($communityPostCommentCount) . '개로 바로가기'); ?>">
                    <?php echo sr_material_icon_html('comment', '', ''); ?>
                    <span><?php echo sr_e(number_format($communityPostCommentCount)); ?></span>
                </button>
            <?php if (is_array($account)) { ?>
                <?php if (sr_community_account_can_edit_post($post, $account)) { ?>
                    <a class="btn btn-ghost-default" href="<?php echo sr_e(sr_url('/community/edit?id=' . (string) $post['id'])); ?>"><?php echo sr_e(sr_t('community::ui.edit.3537f0cc')); ?></a>
                <?php } ?>
                <?php
                $communityCanWriteNotice = sr_community_account_can_write_notice($pdo, [
                    'id' => (int) ($post['board_id'] ?? 0),
                    'status' => (string) ($post['board_status'] ?? 'enabled'),
                ], $account, sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'edit'));
                ?>
                <?php if ($communityCanWriteNotice) { ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/community/notice')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                        <input type="hidden" name="intent" value="<?php echo (int) ($post['is_notice'] ?? 0) === 1 ? 'remove' : 'set'; ?>">
                        <button type="submit" class="btn btn-ghost-default"><?php echo sr_e((int) ($post['is_notice'] ?? 0) === 1 ? '공지 해제' : '공지 지정'); ?></button>
                    </form>
                <?php } ?>
                <?php if (sr_community_account_can_hide_post($pdo, $post, $account)) { ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/community/hide')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                        <button type="submit" class="btn btn-ghost-warning"><?php echo sr_e('숨김'); ?></button>
                    </form>
                <?php } ?>
                <?php if (sr_community_account_can_delete_post($post, $account)) { ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/community/delete')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                        <button type="submit" class="btn btn-ghost-danger"><?php echo sr_e(sr_t('community::ui.delete.6139b6c3')); ?></button>
                    </form>
                <?php } ?>
                <form method="post" action="<?php echo sr_e(sr_url('/community/scrap')); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                    <input type="hidden" name="intent" value="<?php echo $isScrapped ? 'remove' : 'add'; ?>">
                    <button type="submit" class="btn btn-ghost-default"><?php echo sr_e($isScrapped ? sr_t('community::ui.text.d013b859') : sr_t('community::ui.text.3eac8b2a')); ?></button>
                </form>
                <?php if ($canReportPost) { ?>
                    <?php
                    $communityPostReportModalId = 'community_report_post_modal_' . (string) (int) $post['id'];
                    ?>
                    <button type="button" class="btn btn-ghost-warning" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($communityPostReportModalId); ?>" data-overlay="#<?php echo sr_e($communityPostReportModalId); ?>"><?php echo sr_e(sr_t('community::ui.text.a8faafc9')); ?></button>
                    <div id="<?php echo sr_e($communityPostReportModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($communityPostReportModalId . '_title'); ?>" aria-hidden="true" inert>
                        <div class="modal-dialog">
                            <form method="post" action="<?php echo sr_e(sr_url('/community/report')); ?>" class="modal-content">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="target_type" value="post">
                                <input type="hidden" name="target_id" value="<?php echo sr_e((string) $post['id']); ?>">
                                <div class="modal-header">
                                    <h3 id="<?php echo sr_e($communityPostReportModalId . '_title'); ?>" class="modal-title"><?php echo sr_e(sr_t('community::ui.text.a8faafc9')); ?></h3>
                                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('community::ui.close')); ?>" data-overlay="#<?php echo sr_e($communityPostReportModalId); ?>">
                                        <?php echo sr_material_icon_html('close', '', sr_t('community::ui.close')); ?>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-row">
                                        <label for="<?php echo sr_e($communityPostReportModalId . '_reason_key'); ?>" class="form-label"><?php echo sr_e(sr_t('community::ui.text.162e66be')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                                        <div class="form-field">
                                            <select id="<?php echo sr_e($communityPostReportModalId . '_reason_key'); ?>" name="reason_key" class="form-select" required data-overlay-focus>
                                                <?php foreach ($reportReasonKeys as $reasonKey) { ?>
                                                    <option value="<?php echo sr_e($reasonKey); ?>"><?php echo sr_e(sr_community_report_reason_label($reasonKey)); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <label for="<?php echo sr_e($communityPostReportModalId . '_memo_text'); ?>" class="form-label"><?php echo sr_e(sr_t('community::ui.text.54791a8b')); ?></label>
                                        <div class="form-field">
                                            <textarea id="<?php echo sr_e($communityPostReportModalId . '_memo_text'); ?>" name="memo_text" rows="4" class="form-textarea"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($communityPostReportModalId); ?>"><?php echo sr_e(sr_t('community::ui.close')); ?></button>
                                    <button type="submit" class="btn btn-solid-warning modal-action"><?php echo sr_e(sr_t('community::ui.text.a8faafc9')); ?></button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php } ?>
            <?php } elseif ((int) ($post['author_account_id'] ?? 0) < 1 && (string) ($post['guest_password_hash'] ?? '') !== '') { ?>
                <details>
                    <summary class="btn btn-ghost-default"><?php echo sr_e('비회원 글 관리'); ?></summary>
                    <form method="post" action="<?php echo sr_e(sr_url('/community/edit?id=' . (string) $post['id'])); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                        <p>
                            <label for="modules_community_view_guest_post_password">
                                <span><?php echo sr_e('수정 비밀번호'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                <input id="modules_community_view_guest_post_password" type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="current-password" required class="form-input">
                            </label>
                        </p>
                        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.edit.3537f0cc')); ?></button>
                    </form>
                    <form method="post" action="<?php echo sr_e(sr_url('/community/delete')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                        <p>
                            <label for="modules_community_view_guest_post_delete_password">
                                <span><?php echo sr_e('삭제 비밀번호'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                <input id="modules_community_view_guest_post_delete_password" type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="current-password" required class="form-input">
                            </label>
                        </p>
                        <button type="submit" class="btn btn-ghost-danger"><?php echo sr_e(sr_t('community::ui.delete.6139b6c3')); ?></button>
                    </form>
                </details>
            <?php } elseif ($postActionUnavailableMessage !== '') { ?>
                <p><?php echo sr_e($postActionUnavailableMessage); ?></p>
            <?php } ?>
            </div>
            </header>

            <?php echo sr_public_feedback_toasts('community', implode(' ', array_filter(array_map('strval', $postNotices))) . ($reportNotice !== '' ? ($postNotices !== [] ? ' ' : '') . $reportNotice : ''), $reportErrors); ?>
            <?php echo sr_public_feedback_toasts('member', (string) ($memberFollowFeedback['notice'] ?? ''), is_array($memberFollowFeedback['errors'] ?? null) ? $memberFollowFeedback['errors'] : []); ?>

            <?php echo sr_render_output_slot($pdo, [
                'module_key' => 'community',
                'point_key' => 'community.post.view',
                'slot_key' => 'before_content',
                'subject_id' => (string) $post['id'],
            ]); ?>
            <?php if (function_exists('sr_banner_render_public_banner') && sr_module_enabled($pdo, 'banner')) { ?>
                <?php echo sr_banner_render_public_banner($pdo, (int) ($post['banner_before_view_id'] ?? 0)); ?>
            <?php } ?>

            <?php if (empty($canViewPostBody)) { ?>
                <p class="community-post-secret"><?php echo sr_e('비밀글입니다.'); ?></p>
            </article>
            <?php } elseif (!empty($paidReadConfirmationRequired)) { ?>
                <?php
                $assetConfirmationAssetLabel = (string) ($paidReadConfirmationResult['asset_label'] ?? '');
                $assetConfirmationAmount = (int) ($paidReadConfirmationResult['amount'] ?? 0);
                $assetConfirmationMessage = trim($assetConfirmationAssetLabel . ' ' . number_format($assetConfirmationAmount)) . ' 차감 후 게시글을 열람하시겠습니까?';
                $assetConfirmationAction = '/community/post';
                $assetConfirmationId = (int) $post['id'];
                $assetConfirmationRequestToken = (string) ($paidReadConfirmationRequestToken ?? '');
                $assetConfirmationTitle = '게시글 열람 확인';
                $assetConfirmationSubmitLabel = sr_t('community::ui.text.ac5b575f');
                $assetConfirmationCouponIssues = is_array($paidReadConfirmationCouponIssues ?? null) ? $paidReadConfirmationCouponIssues : [];
                $assetConfirmationModalId = 'community_paid_read_confirmation_modal';
                $assetConfirmationCloseOnSubmit = false;
                include SR_ROOT . '/modules/community/views/asset-confirmation-modal.php';
                ?>
            </article>
            <?php } elseif (!empty($paidReadBlocked)) { ?>
                <p class="community-post-secret"><?php echo sr_e($paidReadBlockedMessage !== '' ? $paidReadBlockedMessage : sr_t('community::action.error.paid_read_post_failed')); ?></p>
            </article>
            <?php } else { ?>
            <?php
            $communitySeriesItems = is_array($communitySeriesContext['items'] ?? null) ? $communitySeriesContext['items'] : [];
            $communitySeriesHasCurrentPost = false;
            foreach ($communitySeriesItems as $communitySeriesItemForCheck) {
                if ((int) ($communitySeriesItemForCheck['post_id'] ?? 0) === (int) $post['id']) {
                    $communitySeriesHasCurrentPost = true;
                    break;
                }
            }
            $communityPostHasSeries = is_array($communitySeriesContext ?? null)
                && (int) ($communitySeriesContext['id'] ?? 0) > 0
                && (string) ($communitySeriesContext['title'] ?? '') !== ''
                && $communitySeriesItems !== []
                && $communitySeriesHasCurrentPost;
            ?>
            <?php if ($communityPostHasSeries) { ?>
                <section class="community-post-series-section" aria-labelledby="community-post-series-title">
                    <div class="community-post-series-header">
                        <h2 id="community-post-series-title"><?php echo sr_e((string) $communitySeriesContext['title']); ?></h2>
                        <?php if (is_array($account)) { ?>
                            <form method="post" action="<?php echo sr_e(sr_url('/community/scrap')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="target_type" value="series">
                                <input type="hidden" name="series_id" value="<?php echo sr_e((string) (int) $communitySeriesContext['id']); ?>">
                                <input type="hidden" name="intent" value="<?php echo !empty($isSeriesScrapped) ? 'remove' : 'add'; ?>">
                                <button type="submit" class="btn btn-ghost-default"><?php echo sr_e(!empty($isSeriesScrapped) ? '스크랩 해제' : '스크랩'); ?></button>
                            </form>
                        <?php } ?>
                    </div>
                    <?php if ((string) ($communitySeriesContext['description'] ?? '') !== '') { ?>
                        <p class="community-post-series-description"><?php echo sr_e((string) $communitySeriesContext['description']); ?></p>
                    <?php } ?>
                    <ol class="community-post-series-list">
                        <?php foreach ($communitySeriesItems as $seriesItem) { ?>
                            <?php
                            $communitySeriesItemTitle = (string) ($seriesItem['post_title'] ?? '');
                            $communitySeriesItemEpisode = (string) ($seriesItem['episode_label'] ?? '');
                            $communitySeriesItemLabel = ($communitySeriesItemEpisode !== '' ? $communitySeriesItemEpisode . ' - ' : '') . $communitySeriesItemTitle;
                            $communitySeriesItemIsCurrent = (int) ($seriesItem['post_id'] ?? 0) === (int) $post['id'];
                            ?>
                            <li<?php echo $communitySeriesItemIsCurrent ? ' class="is-current"' : ''; ?>>
                                <?php if ($communitySeriesItemIsCurrent) { ?>
                                    <strong><?php echo sr_e($communitySeriesItemLabel); ?></strong>
                                    <span class="community-post-series-current"><?php echo sr_e('현재글'); ?></span>
                                <?php } else { ?>
                                    <a href="<?php echo sr_e(sr_url('/community/post?id=' . rawurlencode((string) (int) $seriesItem['post_id']))); ?>"><?php echo sr_e($communitySeriesItemLabel); ?></a>
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ol>
                </section>
            <?php } ?>
            <div class="community-post-body">
                <?php echo sr_community_extra_fields_display_html(sr_community_extra_field_values_from_json((string) ($post['extra_values_json'] ?? ''))); ?>
                <?php if ($imageAttachments !== []) { ?>
                    <section class="community-post-image-thumbnails" aria-label="<?php echo sr_e('첨부 이미지'); ?>">
                        <?php foreach ($imageAttachments as $attachment) { ?>
                            <?php
                            $communityAttachmentOriginalUrl = (string) ($attachment['original_url'] ?? sr_community_attachment_public_url($attachment));
                            $communityAttachmentThumbnailUrl = (string) ($attachment['thumbnail_url'] ?? $communityAttachmentOriginalUrl);
                            if ($communityAttachmentOriginalUrl === '' || $communityAttachmentThumbnailUrl === '') {
                                continue;
                            }
                            $communityAttachmentName = (string) ($attachment['original_name'] ?? '');
                            $communityAttachmentSize = (int) ($attachment['size_bytes'] ?? 0);
                            ?>
                            <figure class="community-post-image-thumbnail">
                                <a class="community-post-image-thumbnail-link" href="<?php echo sr_e($communityAttachmentOriginalUrl); ?>" data-community-image-layer-trigger>
                                    <img src="<?php echo sr_e($communityAttachmentThumbnailUrl); ?>" alt="<?php echo sr_e($communityAttachmentName); ?>" loading="lazy">
                                </a>
                                <figcaption class="community-post-image-thumbnail-caption">
                                    <a href="<?php echo sr_e($communityAttachmentOriginalUrl); ?>"><?php echo sr_e($communityAttachmentName !== '' ? $communityAttachmentName : '첨부 이미지'); ?></a>
                                    <?php if ($communityAttachmentSize > 0) { ?>
                                        <span><?php echo sr_e(sr_community_format_bytes($communityAttachmentSize)); ?></span>
                                    <?php } ?>
                                </figcaption>
                            </figure>
                        <?php } ?>
                    </section>
                <?php } ?>
                <?php echo sr_community_post_body_html($post, $communityLayoutSettings, $pdo); ?>
            </div>
            <?php if ($communityReactionsEnabled && sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_render_widget')) { ?>
                <?php echo sr_reaction_render_widget($pdo, 'community', 'post', (string) (int) ($post['id'] ?? 0), is_array($account ?? null) ? $account : null); ?>
            <?php } ?>

            <?php if ($fileAttachments !== []) { ?>
                <section>
                    <h2><?php echo sr_e(sr_t('community::ui.text.0e89a5d4')); ?></h2>
                    <ul>
                        <?php foreach ($fileAttachments as $attachment) { ?>
                            <li>
                                <?php
                                $communityAttachmentDownloadUrl = '/community/attachment?id=' . rawurlencode((string) (int) ($attachment['id'] ?? 0));
                                $communityAttachmentDownloadAccess = [];
                                $communityAttachmentNeedsConfirmation = false;
                                if (is_array($account ?? null) && is_array($postBoard ?? null)) {
                                    $communityAttachmentDownloadConfig = sr_community_asset_event_config($pdo, $postBoard, $settings, 'paid_attachment_download', 'once');
                                    $communityAttachmentIsUploader = (int) ($attachment['uploader_account_id'] ?? 0) === (int) ($account['id'] ?? 0);
                                    $communityAttachmentIsAuthor = (int) ($post['author_account_id'] ?? 0) === (int) ($account['id'] ?? 0);
                                    $communityAttachmentNeedsConfirmation = !$communityAttachmentIsUploader
                                        && !$communityAttachmentIsAuthor
                                        && sr_community_asset_event_required($communityAttachmentDownloadConfig)
                                        && sr_community_asset_policy_requires_confirmation((string) ($communityAttachmentDownloadConfig['charge_policy'] ?? 'once'));
                                    if ($communityAttachmentNeedsConfirmation) {
                                        $communityAttachmentDownloadAccess = sr_community_run_asset_event(
                                            $pdo,
                                            $communityAttachmentDownloadConfig,
                                            (int) $account['id'],
                                            'attachment_download',
                                            'community.attachment',
                                            (int) $attachment['id'],
                                            'use',
                                            'community.attachment.download',
                                            false,
                                            '',
                                            false
                                        );
                                    }
                                }
                                ?>
                                <?php if ($communityAttachmentNeedsConfirmation && (string) ($communityAttachmentDownloadAccess['error_key'] ?? '') === 'asset_confirmation_required') { ?>
                                    <?php
                                    $assetConfirmationAssetLabel = (string) ($communityAttachmentDownloadAccess['asset_label'] ?? '');
                                    $assetConfirmationAmount = (int) ($communityAttachmentDownloadAccess['amount'] ?? 0);
                                    $assetConfirmationMessage = trim($assetConfirmationAssetLabel . ' ' . number_format($assetConfirmationAmount)) . ' 차감 후 첨부 파일을 다운로드하시겠습니까?';
                                    $assetConfirmationAction = '/community/attachment';
                                    $assetConfirmationId = (int) ($attachment['id'] ?? 0);
                                    $assetConfirmationRequestToken = (string) ($communityAttachmentDownloadAccess['confirmation_request_token'] ?? '');
                                    $assetConfirmationTitle = (string) ($attachment['original_name'] ?? sr_t('community::ui.text.0e89a5d4'));
                                    $assetConfirmationSubmitLabel = '다운로드';
                                    $assetConfirmationCouponIssues = sr_community_available_attachment_download_coupon_issues($pdo, (int) ($account['id'] ?? 0), (int) ($attachment['id'] ?? 0));
                                    $assetConfirmationModalId = 'community_attachment_download_confirmation_' . (string) (int) ($attachment['id'] ?? 0);
                                    $assetConfirmationOpen = false;
                                    $assetConfirmationCancelUrl = '';
                                    $assetConfirmationCloseOnSubmit = true;
                                    ?>
                                    <button type="button" class="btn btn-solid-light" data-overlay="#<?php echo sr_e($assetConfirmationModalId); ?>">
                                        <?php echo sr_e((string) $attachment['original_name']); ?>
                                    </button>
                                    <?php include SR_ROOT . '/modules/community/views/asset-confirmation-modal.php'; ?>
                                <?php } else { ?>
                                    <a href="<?php echo sr_e(sr_url($communityAttachmentDownloadUrl)); ?>">
                                        <?php echo sr_e((string) $attachment['original_name']); ?>
                                    </a>
                                <?php } ?>
                                <?php if ((int) ($attachment['size_bytes'] ?? 0) > 0) { ?>
                                    (<?php echo sr_e(sr_community_format_bytes((int) $attachment['size_bytes'])); ?>)
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ul>
                </section>
            <?php } ?>

            <?php echo sr_render_output_slot($pdo, [
                'module_key' => 'community',
                'point_key' => 'community.post.view',
                'slot_key' => 'after_content',
                'subject_id' => (string) $post['id'],
            ]); ?>
            <?php if (function_exists('sr_banner_render_public_banner') && sr_module_enabled($pdo, 'banner')) { ?>
                <?php echo sr_banner_render_public_banner($pdo, (int) ($post['banner_after_view_id'] ?? 0)); ?>
            <?php } ?>
        </article>
        <?php } ?>
        <?php } ?>
        <?php if (!empty($canViewPostBody) && empty($paidReadConfirmationRequired) && empty($paidReadBlocked)) { ?>
        <section id="comments" class="community-comments-panel">
            <?php echo sr_render_output_slot($pdo, [
                'module_key' => 'community',
                'point_key' => 'community.post.view',
                'slot_key' => 'before_comments',
                'subject_id' => (string) $post['id'],
            ]); ?>

            <div class="community-comments-panel-header">
                <h2><?php echo sr_e(sr_t('community::ui.text.c9fff683')); ?> <span class="community-comments-count"><?php echo sr_e(number_format((int) ($post['published_comment_count'] ?? 0))); ?></span></h2>
            </div>
            <?php echo sr_public_feedback_toasts('community', $commentNotice, []); ?>

            <?php if ($comments === []) { ?>
                <p><?php echo sr_e(sr_t('community::ui.text.ff4a5d06')); ?></p>
            <?php } else { ?>
                <ul>
                    <?php foreach ($comments as $comment) { ?>
                        <?php
                            $communityCommentDepth = min(3, max(1, (int) ($comment['depth'] ?? 1)));
                            ?>
                            <li id="community-comment-<?php echo sr_e((string) (int) ($comment['id'] ?? 0)); ?>" class="community-comment-depth-<?php echo sr_e((string) $communityCommentDepth); ?>">
                            <?php
                            $communityCommentCanViewBody = sr_community_account_can_view_comment_body($comment, $post, is_array($account ?? null) ? $account : null, $pdo, $communityCommentPermissionContext ?? []);
                            $communityCommentCanEdit = is_array($account) && sr_community_account_can_edit_comment($comment, $account);
                            $communityCommentCanHide = is_array($account) && sr_community_account_can_hide_comment($pdo, $comment, $post, $account, $communityCommentPermissionContext ?? []);
                            $communityCommentCanDelete = is_array($account) && sr_community_account_can_delete_comment($comment, $account, $pdo, $post, $communityCommentPermissionContext ?? []);
                            $communityCommentIsGuestAuthor = (int) ($comment['author_account_id'] ?? 0) < 1 && (string) ($comment['guest_password_hash'] ?? '') !== '';
                            $communityCommentCanReply = $canComment && $communityCommentCanViewBody && $communityCommentDepth < 3;
                            $communityCommentEditId = 'modules_community_view_comment_edit_' . (string) $comment['id'];
                            $communityCommentReplyId = 'modules_community_view_comment_reply_' . (string) $comment['id'];
                            $communityCommentReplyModalId = 'community_comment_reply_modal_' . (string) (int) $comment['id'];
                            $communityCommentCreatedAt = (string) ($comment['created_at'] ?? '');
                            $communityCommentUrl = sr_url('/community/post?id=' . rawurlencode((string) (int) $post['id']) . '#community-comment-' . rawurlencode((string) (int) ($comment['id'] ?? 0)));
                            ?>
                            <div class="community-comment-meta">
                                <?php $communityCommentAuthorLabel = sr_community_author_label_from_row($comment, $config, $canViewMemberIdentifiers, $memberSettings, $pdo); ?>
                                <?php echo sr_member_public_name_menu_html($pdo, is_array($account ?? null) ? $account : null, (int) ($comment['author_account_id'] ?? 0), $communityCommentAuthorLabel, [
                                    'community_board_key' => (string) $post['board_key'],
                                    'community_board_accessible' => is_array($postBoard ?? null),
                                    'return_to' => (string) ($_SERVER['REQUEST_URI'] ?? '/'),
                                    'is_following' => (string) ($communityFollowStatuses[(int) ($comment['author_account_id'] ?? 0)] ?? '') === 'active',
                                ]); ?>
                                <?php if ($communityCommentCreatedAt !== '') { ?>
                                    /
                                    <?php echo sr_community_time_html($communityCommentCreatedAt); ?>
                                <?php } ?>
                                <?php if ((int) ($comment['is_secret'] ?? 0) === 1) { ?>
                                    / <?php echo sr_e('비밀'); ?>
                                <?php } ?>
                                <?php if ($communityCommentDepth > 1) { ?>
                                    / <?php echo sr_e('답글 ' . (string) $communityCommentDepth . '단계'); ?>
                                <?php } ?>
                            </div>
                            <?php if ($communityCommentCanViewBody) { ?>
                                <p><?php echo sr_member_mention_plain_text_html((string) $comment['body_text']); ?></p>
                                <?php echo sr_comment_extra_fields_display_html((string) ($comment['extra_values_json'] ?? '')); ?>
                                <?php if ($communityReactionsEnabled && sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_render_widget')) { ?>
                                    <?php
                                    $communityCommentReactionId = (string) (int) ($comment['id'] ?? 0);
                                    $communityCommentReactionOptions = ['label' => '댓글 리액션'];
                                    if (isset($communityReactionCommentTargets[$communityCommentReactionId]) && is_array($communityReactionCommentTargets[$communityCommentReactionId])) {
                                        $communityCommentReactionOptions['resolved_target'] = $communityReactionCommentTargets[$communityCommentReactionId];
                                    }
                                    if (isset($communityReactionCommentSummaries[$communityCommentReactionId]) && is_array($communityReactionCommentSummaries[$communityCommentReactionId])) {
                                        $communityCommentReactionOptions['counts'] = (array) ($communityReactionCommentSummaries[$communityCommentReactionId]['counts'] ?? []);
                                        $communityCommentReactionOptions['my_record'] = $communityReactionCommentSummaries[$communityCommentReactionId]['my_record'] ?? null;
                                    }
                                    ?>
                                    <?php echo sr_reaction_render_widget($pdo, 'community', 'comment', $communityCommentReactionId, is_array($account ?? null) ? $account : null, $communityCommentReactionOptions); ?>
                                <?php } ?>
                            <?php } else { ?>
                                <p class="community-comment-secret"><?php echo sr_e('비밀 댓글입니다.'); ?></p>
                            <?php } ?>
                            <div class="community-comment-actions">
                                <button type="button" class="btn btn-ghost-default" data-community-copy-url="<?php echo sr_e($communityCommentUrl); ?>" data-community-copy-default-label="<?php echo sr_e('URL 복사'); ?>" data-community-copy-success-label="<?php echo sr_e('복사됨'); ?>" data-community-copy-error-label="<?php echo sr_e('복사 실패'); ?>"><?php echo sr_e('URL 복사'); ?></button>
                                <?php if (is_array($account) || $communityCommentCanReply || $communityCommentIsGuestAuthor) { ?>
                                    <?php if ($communityCommentCanEdit || $communityCommentCanHide || $communityCommentCanDelete || $communityCommentCanReply || $communityCommentIsGuestAuthor) { ?>
                                        <?php if ($communityCommentCanReply) { ?>
                                            <?php if (is_array($account)) { ?>
                                                <button type="button" class="btn btn-ghost-default" aria-haspopup="dialog" aria-expanded="false" aria-controls="community_comment_reply_modal" data-overlay="#community_comment_reply_modal" data-community-comment-reply data-comment-id="<?php echo sr_e((string) $comment['id']); ?>" data-comment-body="<?php echo sr_e((string) $comment['body_text']); ?>">답글</button>
                                            <?php } else { ?>
                                            <button type="button" class="btn btn-ghost-default" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($communityCommentReplyModalId); ?>" data-overlay="#<?php echo sr_e($communityCommentReplyModalId); ?>">답글</button>
                                            <div id="<?php echo sr_e($communityCommentReplyModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($communityCommentReplyModalId . '_title'); ?>" aria-hidden="true" inert>
                                                <div class="modal-dialog">
                                                    <form method="post" action="<?php echo sr_e(sr_url('/community/comment')); ?>" class="modal-content">
                                                        <?php echo sr_csrf_field(); ?>
                                                        <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                                                        <input type="hidden" name="parent_comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                                        <input type="hidden" name="comment_page" value="<?php echo sr_e((string) ($commentPage['page'] ?? 1)); ?>">
                                                        <div class="modal-header">
                                                            <h3 id="<?php echo sr_e($communityCommentReplyModalId . '_title'); ?>" class="modal-title">답글 작성</h3>
                                                            <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('community::ui.close')); ?>" data-overlay="#<?php echo sr_e($communityCommentReplyModalId); ?>">
                                                                <?php echo sr_material_icon_html('close', '', sr_t('community::ui.close')); ?>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <strong class="community-comment-reply-source-label"><?php echo sr_e('댓글'); ?></strong>
                                                            <p class="community-comment-reply-source"><?php echo sr_member_mention_plain_text_html((string) $comment['body_text']); ?></p>
                                                            <p>
                                                                <label for="<?php echo sr_e($communityCommentReplyId); ?>">
                                                                    <span>답글 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                                                    <textarea id="<?php echo sr_e($communityCommentReplyId); ?>" name="body_text" rows="3" cols="60" required class="form-textarea" data-overlay-focus<?php echo is_array($account) ? ' data-sr-mention-input data-sr-mention-endpoint="' . sr_e(sr_url('/member/mention-search')) . '"' : ''; ?>><?php echo $commentParentId === (int) $comment['id'] ? sr_e($commentBody) : ''; ?></textarea>
                                                                </label>
                                                            </p>
                                                            <?php echo sr_comment_extra_fields_form_html($commentExtraFieldDefinitions, $commentParentId === (int) $comment['id'] ? $commentExtraFieldValues : [], 'comment_extra_fields', 'community_comment_reply_' . (string) $comment['id']); ?>
                                                            <?php if (!is_array($account)) { ?>
                                                                <p>
                                                                    <label for="<?php echo sr_e($communityCommentReplyId . '_guest_name'); ?>">
                                                                        <span><?php echo sr_e('작성자명'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                                                        <input id="<?php echo sr_e($communityCommentReplyId . '_guest_name'); ?>" type="text" name="guest_author_name" maxlength="120" value="<?php echo $commentParentId === (int) $comment['id'] ? sr_e($commentGuestAuthorName) : ''; ?>" required class="form-input">
                                                                    </label>
                                                                </p>
                                                                <p>
                                                                    <label for="<?php echo sr_e($communityCommentReplyId . '_guest_password'); ?>">
                                                                        <span><?php echo sr_e('수정/삭제 비밀번호'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                                                        <input id="<?php echo sr_e($communityCommentReplyId . '_guest_password'); ?>" type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="new-password" required class="form-input">
                                                                    </label>
                                                                </p>
                                                            <?php } ?>
                                                            <?php if (!empty($secretCommentsEnabled)) { ?>
                                                                <label class="community-comment-secret-toggle">
                                                                    <input type="checkbox" name="is_secret" value="1" class="form-checkbox"<?php echo $commentParentId === (int) $comment['id'] && !empty($commentIsSecret) ? ' checked' : ''; ?>>
                                                                    <span><?php echo sr_e('비밀 댓글'); ?></span>
                                                                </label>
                                                            <?php } ?>
                                                            <?php echo sr_community_privacy_consent_field_html($pdo, ['id' => (int) $post['board_id']] + $post, ['comment'], true, 'comment_reply_' . (string) $comment['id']); ?>
                                                            <?php if (function_exists('sr_antispam_challenge_render')) { ?>
                                                                <?php echo sr_antispam_challenge_render($pdo, 'community.comment.guest', 'community_comment_' . (string) (int) $post['id'] . '_' . (string) (int) $comment['id'], ['account' => is_array($account ?? null) ? $account : null]); ?>
                                                            <?php } ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($communityCommentReplyModalId); ?>"><?php echo sr_e(sr_t('community::ui.close')); ?></button>
                                                            <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e('답글 작성'); ?></button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                            <?php } ?>
                                        <?php } ?>
                                        <?php if ($communityCommentCanEdit) { ?>
                                            <button type="button" class="btn btn-ghost-default" aria-haspopup="dialog" aria-expanded="false" aria-controls="community_comment_edit_modal" data-overlay="#community_comment_edit_modal" data-community-comment-edit data-comment-id="<?php echo sr_e((string) $comment['id']); ?>" data-comment-body="<?php echo sr_e((string) $comment['body_text']); ?>" data-comment-secret="<?php echo (int) ($comment['is_secret'] ?? 0) === 1 ? '1' : '0'; ?>"><?php echo sr_e(sr_t('community::ui.edit.3537f0cc')); ?></button>
                                        <?php } ?>
                                        <?php if ($communityCommentCanHide) { ?>
                                            <form method="post" action="<?php echo sr_e(sr_url('/community/comment/hide')); ?>">
                                                <?php echo sr_csrf_field(); ?>
                                                <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                                <input type="hidden" name="comment_page" value="<?php echo sr_e((string) ($commentPage['page'] ?? 1)); ?>">
                                                <button type="submit" class="btn btn-ghost-warning"><?php echo sr_e('숨김'); ?></button>
                                            </form>
                                        <?php } ?>
                                        <?php if ($communityCommentCanDelete) { ?>
                                            <form method="post" action="<?php echo sr_e(sr_url('/community/comment/delete')); ?>">
                                                <?php echo sr_csrf_field(); ?>
                                                <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                                <input type="hidden" name="comment_page" value="<?php echo sr_e((string) ($commentPage['page'] ?? 1)); ?>">
                                                <button type="submit" class="btn btn-ghost-danger"><?php echo sr_e(sr_t('community::ui.delete.6139b6c3')); ?></button>
                                            </form>
                                        <?php } ?>
                                        <?php if (!is_array($account) && $communityCommentIsGuestAuthor) { ?>
                                            <details>
                                                <summary class="btn btn-ghost-default"><?php echo sr_e('비회원 댓글 관리'); ?></summary>
                                                <form method="post" action="<?php echo sr_e(sr_url('/community/comment/edit')); ?>">
                                                    <?php echo sr_csrf_field(); ?>
                                                    <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                                    <input type="hidden" name="comment_page" value="<?php echo sr_e((string) ($commentPage['page'] ?? 1)); ?>">
                                                    <p>
                                                        <label for="<?php echo sr_e($communityCommentEditId); ?>">
                                                            <span><?php echo sr_e(sr_t('community::ui.edit.4275a1f5')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                                            <textarea id="<?php echo sr_e($communityCommentEditId); ?>" name="body_text" rows="3" cols="60" required class="form-textarea"><?php echo sr_e((string) $comment['body_text']); ?></textarea>
                                                        </label>
                                                    </p>
                                                    <p>
                                                        <label for="<?php echo sr_e($communityCommentEditId . '_guest_password'); ?>">
                                                            <span><?php echo sr_e('수정 비밀번호'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                                            <input id="<?php echo sr_e($communityCommentEditId . '_guest_password'); ?>" type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="current-password" required class="form-input">
                                                        </label>
                                                    </p>
                                                    <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.edit.3537f0cc')); ?></button>
                                                </form>
                                                <form method="post" action="<?php echo sr_e(sr_url('/community/comment/delete')); ?>">
                                                    <?php echo sr_csrf_field(); ?>
                                                    <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                                    <input type="hidden" name="comment_page" value="<?php echo sr_e((string) ($commentPage['page'] ?? 1)); ?>">
                                                    <p>
                                                        <label for="<?php echo sr_e($communityCommentEditId . '_guest_delete_password'); ?>">
                                                            <span><?php echo sr_e('삭제 비밀번호'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                                            <input id="<?php echo sr_e($communityCommentEditId . '_guest_delete_password'); ?>" type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="current-password" required class="form-input">
                                                        </label>
                                                    </p>
                                                    <button type="submit" class="btn btn-ghost-danger"><?php echo sr_e(sr_t('community::ui.delete.6139b6c3')); ?></button>
                                                </form>
                                            </details>
                                        <?php } ?>
                                    <?php } ?>
                                <?php } ?>
                                <?php if (is_array($account) && $communityCommentCanViewBody && (int) $comment['author_account_id'] !== (int) $account['id']) { ?>
                                    <button type="button" class="btn btn-ghost-warning" aria-haspopup="dialog" aria-expanded="false" aria-controls="community_report_comment_modal" data-overlay="#community_report_comment_modal" data-community-comment-report data-comment-id="<?php echo sr_e((string) $comment['id']); ?>"><?php echo sr_e(sr_t('community::ui.text.9fc1481d')); ?></button>
                                <?php } ?>
                            </div>
                        </li>
                    <?php } ?>
                </ul>
                <?php if (is_array($account)) { ?>
                    <div id="community_comment_reply_modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="community_comment_reply_modal_title" aria-hidden="true" inert data-community-comment-reply-modal>
                        <div class="modal-dialog">
                            <form method="post" action="<?php echo sr_e(sr_url('/community/comment')); ?>" class="modal-content">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                                <input type="hidden" name="parent_comment_id" value="<?php echo $commentParentId > 0 ? sr_e((string) $commentParentId) : ''; ?>" data-community-comment-reply-id>
                                <input type="hidden" name="comment_page" value="<?php echo sr_e((string) ($commentPage['page'] ?? 1)); ?>">
                                <div class="modal-header">
                                    <h3 id="community_comment_reply_modal_title" class="modal-title">답글 작성</h3>
                                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('community::ui.close')); ?>" data-overlay="#community_comment_reply_modal"><?php echo sr_material_icon_html('close', '', sr_t('community::ui.close')); ?></button>
                                </div>
                                <div class="modal-body">
                                    <strong class="community-comment-reply-source-label"><?php echo sr_e('댓글'); ?></strong>
                                    <p class="community-comment-reply-source" data-community-comment-reply-source></p>
                                    <p>
                                        <label for="community_comment_reply_body">
                                            <span>답글 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                            <textarea id="community_comment_reply_body" name="body_text" rows="3" cols="60" required class="form-textarea" data-overlay-focus data-sr-mention-input data-sr-mention-endpoint="<?php echo sr_e(sr_url('/member/mention-search')); ?>" data-community-comment-reply-body><?php echo $commentParentId > 0 ? sr_e($commentBody) : ''; ?></textarea>
                                        </label>
                                        <?php echo sr_comment_extra_fields_form_html($commentExtraFieldDefinitions, $commentParentId > 0 ? $commentExtraFieldValues : [], 'comment_extra_fields', 'community_comment_reply'); ?>
                                    </p>
                                    <?php if (!empty($secretCommentsEnabled)) { ?>
                                        <label class="community-comment-secret-toggle">
                                            <input type="checkbox" name="is_secret" value="1" class="form-checkbox" data-community-comment-reply-secret<?php echo $commentParentId > 0 && !empty($commentIsSecret) ? ' checked' : ''; ?>>
                                            <span><?php echo sr_e('비밀 댓글'); ?></span>
                                        </label>
                                    <?php } ?>
                                    <?php echo sr_community_privacy_consent_field_html($pdo, ['id' => (int) $post['board_id']] + $post, ['comment'], true, 'comment_reply_member'); ?>
                                    <?php if (function_exists('sr_antispam_challenge_render')) { ?>
                                        <?php echo sr_antispam_challenge_render($pdo, 'community.comment.guest', 'community_comment_' . (string) (int) $post['id'] . '_member_reply', ['account' => $account]); ?>
                                    <?php } ?>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#community_comment_reply_modal"><?php echo sr_e(sr_t('community::ui.close')); ?></button>
                                    <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e('답글 작성'); ?></button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div id="community_comment_edit_modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="community_comment_edit_modal_title" aria-hidden="true" inert data-community-comment-edit-modal data-secret-comments-enabled="<?php echo !empty($secretCommentsEnabled) ? '1' : '0'; ?>">
                        <div class="modal-dialog">
                            <form method="post" action="<?php echo sr_e(sr_url('/community/comment/edit')); ?>" class="modal-content">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="comment_id" value="" data-community-comment-edit-id>
                                <input type="hidden" name="comment_page" value="<?php echo sr_e((string) ($commentPage['page'] ?? 1)); ?>">
                                <div class="modal-header">
                                    <h3 id="community_comment_edit_modal_title" class="modal-title"><?php echo sr_e(sr_t('community::ui.edit.4275a1f5')); ?></h3>
                                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('community::ui.close')); ?>" data-overlay="#community_comment_edit_modal"><?php echo sr_material_icon_html('close', '', sr_t('community::ui.close')); ?></button>
                                </div>
                                <div class="modal-body">
                                    <p>
                                        <label for="community_comment_edit_body">
                                            <span><?php echo sr_e(sr_t('community::ui.edit.4275a1f5')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                            <textarea id="community_comment_edit_body" name="body_text" rows="3" cols="60" required class="form-textarea" data-overlay-focus data-sr-mention-input data-sr-mention-endpoint="<?php echo sr_e(sr_url('/member/mention-search')); ?>" data-community-comment-edit-body></textarea>
                                        </label>
                                    </p>
                                    <label class="community-comment-secret-toggle" data-community-comment-edit-secret-field<?php echo empty($secretCommentsEnabled) ? ' hidden' : ''; ?>>
                                        <input type="checkbox" name="is_secret" value="1" class="form-checkbox" data-community-comment-edit-secret>
                                        <span><?php echo sr_e('비밀 댓글'); ?></span>
                                    </label>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#community_comment_edit_modal"><?php echo sr_e(sr_t('community::ui.close')); ?></button>
                                    <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('community::ui.edit.3537f0cc')); ?></button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div id="community_report_comment_modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="community_report_comment_modal_title" aria-hidden="true" inert data-community-comment-report-modal>
                        <div class="modal-dialog">
                            <form method="post" action="<?php echo sr_e(sr_url('/community/report')); ?>" class="modal-content">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="target_type" value="comment">
                                <input type="hidden" name="target_id" value="" data-community-comment-report-id>
                                <input type="hidden" name="comment_page" value="<?php echo sr_e((string) ($commentPage['page'] ?? 1)); ?>">
                                <div class="modal-header">
                                    <h3 id="community_report_comment_modal_title" class="modal-title"><?php echo sr_e(sr_t('community::ui.text.9fc1481d')); ?></h3>
                                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('community::ui.close')); ?>" data-overlay="#community_report_comment_modal"><?php echo sr_material_icon_html('close', '', sr_t('community::ui.close')); ?></button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-row">
                                        <label for="community_report_comment_reason_key" class="form-label"><?php echo sr_e(sr_t('community::ui.text.162e66be')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                                        <div class="form-field">
                                            <select id="community_report_comment_reason_key" name="reason_key" class="form-select" required data-overlay-focus>
                                                <?php foreach ($reportReasonKeys as $reasonKey) { ?>
                                                    <option value="<?php echo sr_e($reasonKey); ?>"><?php echo sr_e(sr_community_report_reason_label($reasonKey)); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <label for="community_report_comment_memo_text" class="form-label"><?php echo sr_e(sr_t('community::ui.text.54791a8b')); ?></label>
                                        <div class="form-field"><textarea id="community_report_comment_memo_text" name="memo_text" rows="4" class="form-textarea"></textarea></div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#community_report_comment_modal"><?php echo sr_e(sr_t('community::ui.close')); ?></button>
                                    <button type="submit" class="btn btn-solid-warning modal-action"><?php echo sr_e(sr_t('community::ui.text.9fc1481d')); ?></button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php } ?>
                <?php echo sr_community_comment_pagination_html((int) $post['id'], $commentPage); ?>
            <?php } ?>

            <?php echo sr_public_feedback_toasts('community', '', $commentErrors); ?>

            <?php if ($canComment) { ?>
                <form id="community-comment-form" method="post" action="<?php echo sr_e(sr_url('/community/comment')); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                    <input type="hidden" name="parent_comment_id" value="0">
                    <input type="hidden" name="comment_page" value="<?php echo sr_e((string) ($commentPage['page'] ?? 1)); ?>">
                    <p>
                        <label for="modules_community_view_body_text_2">
                    <span><?php echo sr_e(sr_t('community::ui.text.c9fff683')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                            <textarea id="modules_community_view_body_text_2" name="body_text" rows="5" cols="80" required class="form-textarea"<?php echo is_array($account) ? ' data-sr-mention-input data-sr-mention-endpoint="' . sr_e(sr_url('/member/mention-search')) . '"' : ''; ?>><?php echo $commentParentId < 1 ? sr_e($commentBody) : ''; ?></textarea>
                        </label>
                    </p>
                    <?php echo sr_comment_extra_fields_form_html($commentExtraFieldDefinitions, $commentParentId < 1 ? $commentExtraFieldValues : [], 'comment_extra_fields', 'community_comment'); ?>
                    <?php if (!is_array($account)) { ?>
                        <p>
                            <label for="modules_community_view_guest_comment_name">
                                <span><?php echo sr_e('작성자명'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                <input id="modules_community_view_guest_comment_name" type="text" name="guest_author_name" maxlength="120" value="<?php echo $commentParentId < 1 ? sr_e($commentGuestAuthorName) : ''; ?>" required class="form-input">
                            </label>
                        </p>
                        <p>
                            <label for="modules_community_view_guest_comment_password">
                                <span><?php echo sr_e('수정/삭제 비밀번호'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                <input id="modules_community_view_guest_comment_password" type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="new-password" required class="form-input">
                            </label>
                        </p>
                    <?php } ?>
                    <?php if (!empty($secretCommentsEnabled)) { ?>
                        <label class="community-comment-secret-toggle">
                            <input type="checkbox" name="is_secret" value="1" class="form-checkbox"<?php echo !empty($commentIsSecret) ? ' checked' : ''; ?>>
                            <span><?php echo sr_e('비밀 댓글'); ?></span>
                        </label>
                    <?php } ?>
                    <?php echo sr_community_privacy_consent_field_html($pdo, ['id' => (int) $post['board_id']] + $post, ['comment'], true, 'comment_new'); ?>
                    <?php if (function_exists('sr_antispam_challenge_render')) { ?>
                        <?php echo sr_antispam_challenge_render($pdo, 'community.comment.guest', 'community_comment_' . (string) (int) $post['id'] . '_0', ['account' => is_array($account ?? null) ? $account : null]); ?>
                    <?php } ?>
                    <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.create.8033fdca')); ?></button>
                </form>
            <?php } elseif ($commentUnavailableMessage !== '') { ?>
                <p><?php echo sr_e($commentUnavailableMessage); ?></p>
            <?php } ?>

            <?php echo sr_render_output_slot($pdo, [
                'module_key' => 'community',
                'point_key' => 'community.post.view',
                'slot_key' => 'after_comments',
                'subject_id' => (string) $post['id'],
            ]); ?>
        </section>
        <?php } ?>
<?php if (!$communityCommentFragmentResponse) { ?>
    <?php include SR_ROOT . '/modules/community/theme/basic/home-frame-end.php'; ?>
    <?php if ($communityReactionsEnabled && sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_public_script_html')) { ?>
        <?php echo sr_reaction_public_script_html(); ?>
    <?php } ?>
    <?php sr_public_layout_end(); ?>
<?php } ?>
