<?php

$pageTitle = (string) $post['title'];
$seo = sr_community_post_seo_meta($pdo, $post, empty($paidReadConfirmationRequired) && !empty($canViewPostBody));
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
$communityReactionsEnabled = !empty($communityLayoutSettings['reaction_enabled']);
if (is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}
if (is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}
$communityReactionCommentTargets = [];
if ($communityReactionsEnabled && function_exists('sr_reaction_resolve_targets') && is_array($comments ?? null) && $comments !== []) {
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
}
$memberSettings = sr_member_settings($pdo);
$communityLayoutContext = sr_community_public_layout_context($communityLayoutSettings, [
    'stylesheets' => array_merge(sr_community_skin_stylesheets($skinKey ?? 'basic'), [
        '/modules/banner/assets/module.css',
        '/modules/popup_layer/assets/module.css',
        '/modules/quiz/assets/module.css',
        '/modules/reaction/assets/module.css',
    ]),
]);
$communityLayoutContext['site_menus'] = array_merge(is_array($communityLayoutContext['site_menus'] ?? null) ? $communityLayoutContext['site_menus'] : [], [
    'secondary' => '',
    'tertiary' => '',
    'quaternary' => '',
    'quinary' => '',
]);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, $communityLayoutContext);
$communityMainLabel = $pageTitle;
?>
    <?php include SR_ROOT . '/modules/community/layouts/basic/home-frame-start.php'; ?>
        <?php if (function_exists('sr_popup_layer_render_public_layer') && sr_module_enabled($pdo, 'popup_layer')) { ?>
            <?php echo sr_popup_layer_render_public_layer($pdo, (int) ($post['popup_layer_view_id'] ?? 0)); ?>
        <?php } ?>

        <p>
            <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $post['board_key']))); ?>">
                <?php echo sr_e((string) $post['board_title']); ?>
            </a>
        </p>

        <article>
            <h1 class="community-post-title community-post-view-title"><?php echo sr_e($pageTitle); ?></h1>
            <p>
                <?php if ((int) ($post['is_secret'] ?? 0) === 1) { ?>
                    <?php echo sr_e('비밀글'); ?>
                    /
                <?php } ?>
                <?php echo sr_e(sr_t('community::ui.text.f99bc7dd')); ?> <?php echo sr_e(sr_community_author_label_from_row($post, $config, $canViewMemberIdentifiers, $memberSettings, $pdo)); ?>
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
            </p>
            <?php if (is_array($account)) { ?>
                <?php if (sr_community_account_can_edit_post($post, $account)) { ?>
                    <p><a href="<?php echo sr_e(sr_url('/community/edit?id=' . (string) $post['id'])); ?>"><?php echo sr_e(sr_t('community::ui.edit.7dfeed85')); ?></a></p>
                <?php } ?>
                <?php if (sr_community_account_can_delete_post($post, $account, $pdo)) { ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/community/delete')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                        <button type="submit" class="btn btn-outline-danger"><?php echo sr_e(sr_t('community::ui.delete.3ee40597')); ?></button>
                    </form>
                <?php } ?>
                <?php if (sr_community_account_can_remove_post_og_image($pdo, $post, $account)) { ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/community/post')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo sr_e((string) $post['id']); ?>">
                        <input type="hidden" name="intent" value="remove_og_image">
                        <button type="submit" class="btn btn-outline-danger"><?php echo sr_e('OG 이미지 제거'); ?></button>
                    </form>
                <?php } ?>
                <form method="post" action="<?php echo sr_e(sr_url('/community/scrap')); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                    <input type="hidden" name="intent" value="<?php echo $isScrapped ? 'remove' : 'add'; ?>">
                    <button type="submit" class="btn btn-solid-light"><?php echo sr_e($isScrapped ? sr_t('community::ui.text.d013b859') : sr_t('community::ui.text.3eac8b2a')); ?></button>
                </form>
                <?php if ($canReportPost) { ?>
                    <?php
                    $communityPostReportModalId = 'community_report_post_modal_' . (string) (int) $post['id'];
                    ?>
                    <button type="button" class="btn btn-outline-warning" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($communityPostReportModalId); ?>" data-overlay="#<?php echo sr_e($communityPostReportModalId); ?>"><?php echo sr_e(sr_t('community::ui.text.a8faafc9')); ?></button>
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
                    <summary class="btn btn-solid-light"><?php echo sr_e('비회원 글 관리'); ?></summary>
                    <form method="post" action="<?php echo sr_e(sr_url('/community/edit?id=' . (string) $post['id'])); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                        <p>
                            <label for="modules_community_view_guest_post_password">
                                <span><?php echo sr_e('수정 비밀번호'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                <input id="modules_community_view_guest_post_password" type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="current-password" required>
                            </label>
                        </p>
                        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.edit.7dfeed85')); ?></button>
                    </form>
                    <form method="post" action="<?php echo sr_e(sr_url('/community/delete')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                        <p>
                            <label for="modules_community_view_guest_post_delete_password">
                                <span><?php echo sr_e('삭제 비밀번호'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                <input id="modules_community_view_guest_post_delete_password" type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="current-password" required>
                            </label>
                        </p>
                        <button type="submit" class="btn btn-outline-danger"><?php echo sr_e(sr_t('community::ui.delete.3ee40597')); ?></button>
                    </form>
                </details>
            <?php } elseif ($postActionUnavailableMessage !== '') { ?>
                <p><?php echo sr_e($postActionUnavailableMessage); ?></p>
            <?php } ?>

            <?php foreach ($postNotices as $postNotice) { ?>
                <?php if (is_string($postNotice) && $postNotice !== '') { ?>
                    <p><?php echo sr_e($postNotice); ?></p>
                <?php } ?>
            <?php } ?>

            <?php if ($reportNotice !== '') { ?>
                <p><?php echo sr_e($reportNotice); ?></p>
            <?php } ?>

            <?php if ($reportErrors !== []) { ?>
                <ul>
                    <?php foreach ($reportErrors as $error) { ?>
                        <li><?php echo sr_e($error); ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>

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
                <form method="post" action="<?php echo sr_e(sr_url('/community/post')); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo sr_e((string) $post['id']); ?>">
                    <input type="hidden" name="asset_request_token" value="<?php echo sr_e((string) ($paidReadConfirmationRequestToken ?? '')); ?>">
                    <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.text.ac5b575f')); ?></button>
                </form>
            </article>
            <?php } else { ?>
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
            <div class="community-post-body">
                <?php echo sr_community_post_body_html($post, $communityLayoutSettings, $pdo); ?>
            </div>
            <?php if ($communityReactionsEnabled && function_exists('sr_reaction_render_widget')) { ?>
                <?php echo sr_reaction_render_widget($pdo, 'community', 'post', (string) (int) ($post['id'] ?? 0), is_array($account ?? null) ? $account : null); ?>
            <?php } ?>

            <?php if (is_array($communityQuizQuizzes ?? null) && $communityQuizQuizzes !== []) { ?>
                <?php
                $sourceQuizzes = $communityQuizQuizzes;
                $sourceModule = 'community';
                $sourceType = 'community_post';
                $sourceId = (int) $post['id'];
                $returnTo = '/community/post?id=' . rawurlencode((string) (int) $post['id']);
                include SR_ROOT . '/modules/quiz/views/source-quizzes.php';
                ?>
            <?php } ?>

            <?php if ($fileAttachments !== []) { ?>
                <section>
                    <h2><?php echo sr_e(sr_t('community::ui.text.0e89a5d4')); ?></h2>
                    <ul>
                        <?php foreach ($fileAttachments as $attachment) { ?>
                            <li>
                                <a href="<?php echo sr_e(sr_url('/community/attachment?id=' . (string) $attachment['id'])); ?>">
                                    <?php echo sr_e((string) $attachment['original_name']); ?>
                                </a>
                                <?php if ((int) ($attachment['size_bytes'] ?? 0) > 0) { ?>
                                    (<?php echo sr_e(sr_community_format_bytes((int) $attachment['size_bytes'])); ?>)
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ul>
                </section>
            <?php } ?>

            <?php if (is_array($communitySeriesContext ?? null) && is_array($communitySeriesContext['items'] ?? null) && $communitySeriesContext['items'] !== []) { ?>
                <nav aria-label="<?php echo sr_e('시리즈 글'); ?>">
                    <h2><?php echo sr_e((string) $communitySeriesContext['title']); ?></h2>
                    <?php if ((string) ($communitySeriesContext['description'] ?? '') !== '') { ?>
                        <p><?php echo sr_e((string) $communitySeriesContext['description']); ?></p>
                    <?php } ?>
                    <?php if (is_array($account)) { ?>
                        <form method="post" action="<?php echo sr_e(sr_url('/community/scrap')); ?>">
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="target_type" value="series">
                            <input type="hidden" name="series_id" value="<?php echo sr_e((string) (int) $communitySeriesContext['id']); ?>">
                            <input type="hidden" name="intent" value="<?php echo !empty($isSeriesScrapped) ? 'remove' : 'add'; ?>">
                            <button type="submit" class="btn btn-solid-light"><?php echo sr_e(!empty($isSeriesScrapped) ? '시리즈 스크랩 해제' : '시리즈 스크랩'); ?></button>
                        </form>
                    <?php } ?>
                    <?php
                    $communitySeriesPreviousItem = null;
                    $communitySeriesNextItem = null;
                    foreach ($communitySeriesContext['items'] as $seriesNavigationItem) {
                        if (!empty($seriesNavigationItem['series_is_previous'])) {
                            $communitySeriesPreviousItem = $seriesNavigationItem;
                        } elseif (!empty($seriesNavigationItem['series_is_next'])) {
                            $communitySeriesNextItem = $seriesNavigationItem;
                        }
                    }
                    ?>
                    <?php if (is_array($communitySeriesPreviousItem) || is_array($communitySeriesNextItem)) { ?>
                        <p>
                            <?php if (is_array($communitySeriesPreviousItem)) { ?>
                                <a href="<?php echo sr_e(sr_url('/community/post?id=' . rawurlencode((string) (int) $communitySeriesPreviousItem['post_id']))); ?>"><?php echo sr_e('이전 글'); ?>: <?php echo sr_e((string) $communitySeriesPreviousItem['post_title']); ?></a>
                            <?php } ?>
                            <?php if (is_array($communitySeriesPreviousItem) && is_array($communitySeriesNextItem)) { ?>
                                /
                            <?php } ?>
                            <?php if (is_array($communitySeriesNextItem)) { ?>
                                <a href="<?php echo sr_e(sr_url('/community/post?id=' . rawurlencode((string) (int) $communitySeriesNextItem['post_id']))); ?>"><?php echo sr_e('다음 글'); ?>: <?php echo sr_e((string) $communitySeriesNextItem['post_title']); ?></a>
                            <?php } ?>
                        </p>
                    <?php } ?>
                    <ol>
                        <?php foreach ($communitySeriesContext['items'] as $seriesItem) { ?>
                            <li>
                                <?php if ((int) ($seriesItem['post_id'] ?? 0) === (int) $post['id']) { ?>
                                    <strong>
                                        <?php echo sr_e((string) ($seriesItem['episode_label'] ?? '') !== '' ? (string) $seriesItem['episode_label'] . ' - ' : ''); ?><?php echo sr_e((string) $seriesItem['post_title']); ?>
                                    </strong>
                                <?php } else { ?>
                                    <a href="<?php echo sr_e(sr_url('/community/post?id=' . rawurlencode((string) (int) $seriesItem['post_id']))); ?>">
                                        <?php echo sr_e((string) ($seriesItem['episode_label'] ?? '') !== '' ? (string) $seriesItem['episode_label'] . ' - ' : ''); ?><?php echo sr_e((string) $seriesItem['post_title']); ?>
                                    </a>
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ol>
                </nav>
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

        <section id="comments" class="community-comments-panel">
            <?php echo sr_render_output_slot($pdo, [
                'module_key' => 'community',
                'point_key' => 'community.post.view',
                'slot_key' => 'before_comments',
                'subject_id' => (string) $post['id'],
            ]); ?>

            <div class="community-comments-panel-header">
                <h2><?php echo sr_e(sr_t('community::ui.text.c9fff683')); ?></h2>
                <?php if ($canComment) { ?>
                    <a href="#community-comment-form" class="btn btn-solid-light"><?php echo sr_e(sr_t('community::ui.create.8033fdca')); ?></a>
                <?php } ?>
            </div>
            <?php if ($commentNotice !== '') { ?>
                <p><?php echo sr_e($commentNotice); ?></p>
            <?php } ?>

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
                            $communityCommentCanViewBody = sr_community_account_can_view_comment_body($comment, $post, is_array($account ?? null) ? $account : null, $pdo);
                            $communityCommentCanEdit = is_array($account) && sr_community_account_can_edit_comment($comment, $account);
                            $communityCommentCanDelete = is_array($account) && sr_community_account_can_delete_comment($comment, $account, $pdo, $post);
                            $communityCommentIsGuestAuthor = (int) ($comment['author_account_id'] ?? 0) < 1 && (string) ($comment['guest_password_hash'] ?? '') !== '';
                            $communityCommentCanHide = sr_community_account_can_hide_comment($pdo, $comment, $post, is_array($account ?? null) ? $account : null);
                            $communityCommentCanReply = $canComment && $communityCommentCanViewBody && $communityCommentDepth < 3;
                            $communityCommentEditId = 'modules_community_view_comment_edit_' . (string) $comment['id'];
                            $communityCommentReplyId = 'modules_community_view_comment_reply_' . (string) $comment['id'];
                            $communityCommentCreatedAt = (string) ($comment['created_at'] ?? '');
                            ?>
                            <p>
                                <?php echo sr_e(sr_community_author_label_from_row($comment, $config, $canViewMemberIdentifiers, $memberSettings, $pdo)); ?>
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
                            </p>
                            <?php if ($communityCommentCanViewBody) { ?>
                                <p><?php echo sr_member_mention_plain_text_html((string) $comment['body_text']); ?></p>
                                <?php if ($communityReactionsEnabled && function_exists('sr_reaction_render_widget')) { ?>
                                    <?php
                                    $communityCommentReactionId = (string) (int) ($comment['id'] ?? 0);
                                    $communityCommentReactionOptions = ['label' => '댓글 리액션'];
                                    if (isset($communityReactionCommentTargets[$communityCommentReactionId]) && is_array($communityReactionCommentTargets[$communityCommentReactionId])) {
                                        $communityCommentReactionOptions['resolved_target'] = $communityReactionCommentTargets[$communityCommentReactionId];
                                    }
                                    ?>
                                    <?php echo sr_reaction_render_widget($pdo, 'community', 'comment', $communityCommentReactionId, is_array($account ?? null) ? $account : null, $communityCommentReactionOptions); ?>
                                <?php } ?>
                            <?php } else { ?>
                                <p class="community-comment-secret"><?php echo sr_e('비밀 댓글입니다.'); ?></p>
                            <?php } ?>
                            <?php if (is_array($account) || $communityCommentCanReply || $communityCommentIsGuestAuthor) { ?>
                                <?php if ($communityCommentCanEdit || $communityCommentCanDelete || $communityCommentCanHide || $communityCommentCanReply || $communityCommentIsGuestAuthor) { ?>
                                    <div class="community-comment-actions">
                                        <?php if ($communityCommentCanReply) { ?>
                                            <details<?php echo $commentParentId === (int) $comment['id'] ? ' open' : ''; ?>>
                                                <summary class="btn btn-solid-light">답글</summary>
                                                <form method="post" action="<?php echo sr_e(sr_url('/community/comment')); ?>">
                                                    <?php echo sr_csrf_field(); ?>
                                                    <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                                                    <input type="hidden" name="parent_comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                                    <p>
                                                        <label for="<?php echo sr_e($communityCommentReplyId); ?>">
                                                            <span>답글 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                                        <textarea id="<?php echo sr_e($communityCommentReplyId); ?>" name="body_text" rows="3" cols="60" required<?php echo is_array($account) ? ' data-sr-mention-input data-sr-mention-endpoint="' . sr_e(sr_url('/member/mention-search')) . '"' : ''; ?>><?php echo $commentParentId === (int) $comment['id'] ? sr_e($commentBody) : ''; ?></textarea>
                                                    </label>
                                                </p>
                                                <?php if (!is_array($account)) { ?>
                                                    <p>
                                                        <label for="<?php echo sr_e($communityCommentReplyId . '_guest_name'); ?>">
                                                            <span><?php echo sr_e('작성자명'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                                            <input id="<?php echo sr_e($communityCommentReplyId . '_guest_name'); ?>" type="text" name="guest_author_name" maxlength="120" value="<?php echo $commentParentId === (int) $comment['id'] ? sr_e($commentGuestAuthorName) : ''; ?>" required>
                                                        </label>
                                                    </p>
                                                    <p>
                                                        <label for="<?php echo sr_e($communityCommentReplyId . '_guest_password'); ?>">
                                                            <span><?php echo sr_e('수정/삭제 비밀번호'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                                            <input id="<?php echo sr_e($communityCommentReplyId . '_guest_password'); ?>" type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="new-password" required>
                                                        </label>
                                                    </p>
                                                <?php } ?>
                                                    <?php if (!empty($secretCommentsEnabled)) { ?>
                                                        <label class="community-comment-secret-toggle">
                                                            <input type="checkbox" name="is_secret" value="1"<?php echo $commentParentId === (int) $comment['id'] && !empty($commentIsSecret) ? ' checked' : ''; ?>>
                                                            <span><?php echo sr_e('비밀 댓글'); ?></span>
                                                        </label>
                                                    <?php } ?>
                                                    <?php echo sr_community_privacy_consent_field_html($pdo, ['id' => (int) $post['board_id']] + $post, ['comment'], true, 'comment_reply_' . (string) $comment['id']); ?>
                                                    <?php if (function_exists('sr_antispam_challenge_render')) { ?>
                                                        <?php echo sr_antispam_challenge_render($pdo, 'community.comment.guest', 'community_comment_' . (string) (int) $post['id'] . '_' . (string) (int) $comment['id'], ['account' => is_array($account ?? null) ? $account : null]); ?>
                                                    <?php } ?>
                                                    <button type="submit" class="btn btn-solid-primary"><?php echo sr_e('답글 작성'); ?></button>
                                                </form>
                                            </details>
                                        <?php } ?>
                                        <?php if ($communityCommentCanEdit) { ?>
                                            <details>
                                                <summary class="btn btn-solid-light"><?php echo sr_e(sr_t('community::ui.edit.4275a1f5')); ?></summary>
                                                <form method="post" action="<?php echo sr_e(sr_url('/community/comment/edit')); ?>">
                                                    <?php echo sr_csrf_field(); ?>
                                                    <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                                    <p>
                                                        <label for="<?php echo sr_e($communityCommentEditId); ?>">
                    <span><?php echo sr_e(sr_t('community::ui.edit.4275a1f5')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                                            <textarea id="<?php echo sr_e($communityCommentEditId); ?>" name="body_text" rows="3" cols="60" required data-sr-mention-input data-sr-mention-endpoint="<?php echo sr_e(sr_url('/member/mention-search')); ?>"><?php echo sr_e((string) $comment['body_text']); ?></textarea>
                                                        </label>
                                                    </p>
                                                    <?php if (!empty($secretCommentsEnabled) || (int) ($comment['is_secret'] ?? 0) === 1) { ?>
                                                        <label class="community-comment-secret-toggle">
                                                            <input type="checkbox" name="is_secret" value="1"<?php echo (int) ($comment['is_secret'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                                            <span><?php echo sr_e('비밀 댓글'); ?></span>
                                                        </label>
                                                    <?php } ?>
                                                    <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.edit.4275a1f5')); ?></button>
                                                </form>
                                            </details>
                                        <?php } ?>
                                        <?php if ($communityCommentCanDelete) { ?>
                                            <form method="post" action="<?php echo sr_e(sr_url('/community/comment/delete')); ?>">
                                                <?php echo sr_csrf_field(); ?>
                                                <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                                <button type="submit" class="btn btn-outline-danger"><?php echo sr_e(sr_t('community::ui.delete.57f509a8')); ?></button>
                                            </form>
                                        <?php } ?>
                                        <?php if (!is_array($account) && $communityCommentIsGuestAuthor) { ?>
                                            <details>
                                                <summary class="btn btn-solid-light"><?php echo sr_e('비회원 댓글 관리'); ?></summary>
                                                <form method="post" action="<?php echo sr_e(sr_url('/community/comment/edit')); ?>">
                                                    <?php echo sr_csrf_field(); ?>
                                                    <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                                    <p>
                                                        <label for="<?php echo sr_e($communityCommentEditId); ?>">
                                                            <span><?php echo sr_e(sr_t('community::ui.edit.4275a1f5')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                                            <textarea id="<?php echo sr_e($communityCommentEditId); ?>" name="body_text" rows="3" cols="60" required><?php echo sr_e((string) $comment['body_text']); ?></textarea>
                                                        </label>
                                                    </p>
                                                    <p>
                                                        <label for="<?php echo sr_e($communityCommentEditId . '_guest_password'); ?>">
                                                            <span><?php echo sr_e('수정 비밀번호'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                                            <input id="<?php echo sr_e($communityCommentEditId . '_guest_password'); ?>" type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="current-password" required>
                                                        </label>
                                                    </p>
                                                    <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.edit.4275a1f5')); ?></button>
                                                </form>
                                                <form method="post" action="<?php echo sr_e(sr_url('/community/comment/delete')); ?>">
                                                    <?php echo sr_csrf_field(); ?>
                                                    <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                                    <p>
                                                        <label for="<?php echo sr_e($communityCommentEditId . '_guest_delete_password'); ?>">
                                                            <span><?php echo sr_e('삭제 비밀번호'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                                            <input id="<?php echo sr_e($communityCommentEditId . '_guest_delete_password'); ?>" type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="current-password" required>
                                                        </label>
                                                    </p>
                                                    <button type="submit" class="btn btn-outline-danger"><?php echo sr_e(sr_t('community::ui.delete.57f509a8')); ?></button>
                                                </form>
                                            </details>
                                        <?php } ?>
                                        <?php if ($communityCommentCanHide) { ?>
                                            <form method="post" action="<?php echo sr_e(sr_url('/community/comment/hide')); ?>">
                                                <?php echo sr_csrf_field(); ?>
                                                <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                                <button type="submit" class="btn btn-outline-warning"><?php echo sr_e('숨기기'); ?></button>
                                            </form>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                                <?php if (is_array($account) && $communityCommentCanViewBody && (int) $comment['author_account_id'] !== (int) $account['id']) { ?>
                                    <?php
                                    $communityCommentReportModalId = 'community_report_comment_modal_' . (string) (int) $comment['id'];
                                    ?>
                                    <button type="button" class="btn btn-outline-warning" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($communityCommentReportModalId); ?>" data-overlay="#<?php echo sr_e($communityCommentReportModalId); ?>"><?php echo sr_e(sr_t('community::ui.text.9fc1481d')); ?></button>
                                    <div id="<?php echo sr_e($communityCommentReportModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($communityCommentReportModalId . '_title'); ?>" aria-hidden="true" inert>
                                        <div class="modal-dialog">
                                            <form method="post" action="<?php echo sr_e(sr_url('/community/report')); ?>" class="modal-content">
                                                <?php echo sr_csrf_field(); ?>
                                                <input type="hidden" name="target_type" value="comment">
                                                <input type="hidden" name="target_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                                <div class="modal-header">
                                                    <h3 id="<?php echo sr_e($communityCommentReportModalId . '_title'); ?>" class="modal-title"><?php echo sr_e(sr_t('community::ui.text.9fc1481d')); ?></h3>
                                                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('community::ui.close')); ?>" data-overlay="#<?php echo sr_e($communityCommentReportModalId); ?>">
                                                        <?php echo sr_material_icon_html('close', '', sr_t('community::ui.close')); ?>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="form-row">
                                                        <label for="<?php echo sr_e($communityCommentReportModalId . '_reason_key'); ?>" class="form-label"><?php echo sr_e(sr_t('community::ui.text.162e66be')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                                                        <div class="form-field">
                                                            <select id="<?php echo sr_e($communityCommentReportModalId . '_reason_key'); ?>" name="reason_key" class="form-select" required data-overlay-focus>
                                                                <?php foreach ($reportReasonKeys as $reasonKey) { ?>
                                                                    <option value="<?php echo sr_e($reasonKey); ?>"><?php echo sr_e(sr_community_report_reason_label($reasonKey)); ?></option>
                                                                <?php } ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="form-row">
                                                        <label for="<?php echo sr_e($communityCommentReportModalId . '_memo_text'); ?>" class="form-label"><?php echo sr_e(sr_t('community::ui.text.54791a8b')); ?></label>
                                                        <div class="form-field">
                                                            <textarea id="<?php echo sr_e($communityCommentReportModalId . '_memo_text'); ?>" name="memo_text" rows="4" class="form-textarea"></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($communityCommentReportModalId); ?>"><?php echo sr_e(sr_t('community::ui.close')); ?></button>
                                                    <button type="submit" class="btn btn-solid-warning modal-action"><?php echo sr_e(sr_t('community::ui.text.9fc1481d')); ?></button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php } ?>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>

            <?php if ($commentErrors !== []) { ?>
                <ul>
                    <?php foreach ($commentErrors as $error) { ?>
                        <li><?php echo sr_e($error); ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>

            <?php if ($canComment) { ?>
                <form id="community-comment-form" method="post" action="<?php echo sr_e(sr_url('/community/comment')); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                    <input type="hidden" name="parent_comment_id" value="0">
                    <p>
                        <label for="modules_community_view_body_text_2">
                    <span><?php echo sr_e(sr_t('community::ui.text.c9fff683')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                            <textarea id="modules_community_view_body_text_2" name="body_text" rows="5" cols="80" required<?php echo is_array($account) ? ' data-sr-mention-input data-sr-mention-endpoint="' . sr_e(sr_url('/member/mention-search')) . '"' : ''; ?>><?php echo $commentParentId < 1 ? sr_e($commentBody) : ''; ?></textarea>
                        </label>
                    </p>
                    <?php if (!is_array($account)) { ?>
                        <p>
                            <label for="modules_community_view_guest_comment_name">
                                <span><?php echo sr_e('작성자명'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                <input id="modules_community_view_guest_comment_name" type="text" name="guest_author_name" maxlength="120" value="<?php echo $commentParentId < 1 ? sr_e($commentGuestAuthorName) : ''; ?>" required>
                            </label>
                        </p>
                        <p>
                            <label for="modules_community_view_guest_comment_password">
                                <span><?php echo sr_e('수정/삭제 비밀번호'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                <input id="modules_community_view_guest_comment_password" type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="new-password" required>
                            </label>
                        </p>
                    <?php } ?>
                    <?php if (!empty($secretCommentsEnabled)) { ?>
                        <label class="community-comment-secret-toggle">
                            <input type="checkbox" name="is_secret" value="1"<?php echo !empty($commentIsSecret) ? ' checked' : ''; ?>>
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
    <?php include SR_ROOT . '/modules/community/layouts/basic/home-frame-end.php'; ?>
<?php if ($communityReactionsEnabled && function_exists('sr_reaction_public_script_html')) { ?>
    <?php echo sr_reaction_public_script_html(); ?>
<?php } ?>
<?php sr_public_layout_end(); ?>
