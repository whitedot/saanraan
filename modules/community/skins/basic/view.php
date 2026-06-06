<?php

$pageTitle = (string) $post['title'];
$seo = sr_community_post_seo_meta($pdo, $post, empty($paidReadConfirmationRequired));
if (is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
$memberSettings = sr_member_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings, [
    'stylesheets' => array_merge(sr_community_skin_stylesheets($skinKey ?? 'basic'), [
        '/modules/banner/assets/public.css',
        '/modules/popup_layer/assets/public.css',
        '/modules/quiz/assets/public.css',
    ]),
]));
?>
    <main class="community-screen">
        <?php if (function_exists('sr_popup_layer_render_public_layer') && sr_module_enabled($pdo, 'popup_layer')) { ?>
            <?php echo sr_popup_layer_render_public_layer($pdo, (int) ($post['popup_layer_view_id'] ?? 0)); ?>
        <?php } ?>

        <p>
            <a href="<?php echo sr_e(sr_url('/community')); ?>"><?php echo sr_e(sr_t('community::ui.community.4a285775')); ?></a>
            /
            <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $post['board_key']))); ?>">
                <?php echo sr_e((string) $post['board_title']); ?>
            </a>
        </p>

        <article>
            <h1><?php echo sr_e($pageTitle); ?></h1>
            <p>
                <?php echo sr_e(sr_t('community::ui.text.f99bc7dd')); ?> <?php echo sr_e(sr_community_author_label_from_row($post, $config, $canViewMemberIdentifiers, $memberSettings, $pdo)); ?>
                <?php echo sr_e(sr_t('community::ui.text.8619f779')); ?> <?php echo sr_e((string) $post['created_at']); ?>
                <?php echo sr_e(sr_t('community::ui.text.e83def32')); ?> <?php echo sr_e((string) $post['view_count']); ?>
                <?php if ((string) ($post['category_title'] ?? '') !== '') { ?>
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
                        <button type="submit"><?php echo sr_e(sr_t('community::ui.delete.3ee40597')); ?></button>
                    </form>
                <?php } ?>
                <?php if (sr_community_account_can_remove_post_og_image($pdo, $post, $account)) { ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/community/post')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo sr_e((string) $post['id']); ?>">
                        <input type="hidden" name="intent" value="remove_og_image">
                        <button type="submit"><?php echo sr_e('OG 이미지 제거'); ?></button>
                    </form>
                <?php } ?>
                <form method="post" action="<?php echo sr_e(sr_url('/community/scrap')); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                    <input type="hidden" name="intent" value="<?php echo $isScrapped ? 'remove' : 'add'; ?>">
                    <button type="submit"><?php echo $isScrapped ? sr_t('community::ui.text.d013b859') : sr_t('community::ui.text.3eac8b2a'); ?></button>
                </form>
                <?php if ($canReportPost) { ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/community/report')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="target_type" value="post">
                        <input type="hidden" name="target_id" value="<?php echo sr_e((string) $post['id']); ?>">
                        <p>
                            <label for="modules_community_view_reason_key">
                    <span><?php echo sr_e(sr_t('community::ui.text.162e66be')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                <select id="modules_community_view_reason_key" name="reason_key" required>
                                    <?php foreach ($reportReasonKeys as $reasonKey) { ?>
                                        <option value="<?php echo sr_e($reasonKey); ?>"><?php echo sr_e(sr_community_report_reason_label($reasonKey)); ?></option>
                                    <?php } ?>
                                </select>
                            </label>
                        </p>
                        <p>
                            <label for="modules_community_view_memo_text">
                    <span><?php echo sr_e(sr_t('community::ui.text.54791a8b')); ?></span>
                                <textarea id="modules_community_view_memo_text" name="memo_text" rows="3" cols="60"></textarea>
                            </label>
                        </p>
                        <button type="submit"><?php echo sr_e(sr_t('community::ui.text.a8faafc9')); ?></button>
                    </form>
                <?php } ?>
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

            <?php if (!empty($paidReadConfirmationRequired)) { ?>
                <form method="post" action="<?php echo sr_e(sr_url('/community/post')); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo sr_e((string) $post['id']); ?>">
                    <button type="submit"><?php echo sr_e(sr_t('community::ui.text.ac5b575f')); ?></button>
                </form>
            </article>
            <?php } else { ?>
            <div class="community-post-body">
                <?php echo sr_community_post_body_html($post); ?>
            </div>

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

            <?php if ($imageAttachments !== []) { ?>
                <section>
                    <h2><?php echo sr_e(sr_t('community::ui.text.01dd6a36')); ?></h2>
                    <ul>
                        <?php foreach ($imageAttachments as $attachment) { ?>
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
                            <button type="submit"><?php echo !empty($isSeriesScrapped) ? '시리즈 스크랩 해제' : '시리즈 스크랩'; ?></button>
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
                        <li>
                            <?php
                            $communityCommentCanViewBody = sr_community_account_can_view_comment_body($comment, $post, is_array($account ?? null) ? $account : null);
                            $communityCommentCanEdit = is_array($account) && sr_community_account_can_edit_comment($comment, $account);
                            $communityCommentCanDelete = is_array($account) && sr_community_account_can_delete_comment($comment, $account, $pdo, $post);
                            $communityCommentCanHide = sr_community_account_can_hide_comment($pdo, $comment, $post, is_array($account ?? null) ? $account : null);
                            $communityCommentEditId = 'modules_community_view_comment_edit_' . (string) $comment['id'];
                            $communityCommentCreatedAt = (string) ($comment['created_at'] ?? '');
                            ?>
                            <p>
                                <?php echo sr_e(sr_community_author_label_from_row($comment, $config, $canViewMemberIdentifiers, $memberSettings, $pdo)); ?>
                                <?php if ($communityCommentCreatedAt !== '') { ?>
                                    /
                                    <time datetime="<?php echo sr_e($communityCommentCreatedAt); ?>" title="<?php echo sr_e($communityCommentCreatedAt); ?>"><?php echo sr_e(sr_community_relative_time_label($communityCommentCreatedAt)); ?></time>
                                <?php } ?>
                                <?php if ((int) ($comment['is_secret'] ?? 0) === 1) { ?>
                                    / <?php echo sr_e('비밀'); ?>
                                <?php } ?>
                            </p>
                            <?php if ($communityCommentCanViewBody) { ?>
                                <p><?php echo sr_community_plain_text_html((string) $comment['body_text']); ?></p>
                            <?php } else { ?>
                                <p class="community-comment-secret"><?php echo sr_e('비밀 댓글입니다.'); ?></p>
                            <?php } ?>
                            <?php if (is_array($account)) { ?>
                                <?php if ($communityCommentCanEdit || $communityCommentCanDelete || $communityCommentCanHide) { ?>
                                    <div class="community-comment-actions">
                                        <?php if ($communityCommentCanEdit) { ?>
                                            <details>
                                                <summary class="btn btn-solid-light"><?php echo sr_e(sr_t('community::ui.edit.4275a1f5')); ?></summary>
                                                <form method="post" action="<?php echo sr_e(sr_url('/community/comment/edit')); ?>">
                                                    <?php echo sr_csrf_field(); ?>
                                                    <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                                    <p>
                                                        <label for="<?php echo sr_e($communityCommentEditId); ?>">
                    <span><?php echo sr_e(sr_t('community::ui.edit.4275a1f5')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                                            <textarea id="<?php echo sr_e($communityCommentEditId); ?>" name="body_text" rows="3" cols="60" required><?php echo sr_e((string) $comment['body_text']); ?></textarea>
                                                        </label>
                                                    </p>
                                                    <label class="community-comment-secret-toggle">
                                                        <input type="checkbox" name="is_secret" value="1"<?php echo (int) ($comment['is_secret'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                                        <span><?php echo sr_e('비밀 댓글'); ?></span>
                                                    </label>
                                                    <button type="submit"><?php echo sr_e(sr_t('community::ui.edit.4275a1f5')); ?></button>
                                                </form>
                                            </details>
                                        <?php } ?>
                                        <?php if ($communityCommentCanDelete) { ?>
                                            <form method="post" action="<?php echo sr_e(sr_url('/community/comment/delete')); ?>">
                                                <?php echo sr_csrf_field(); ?>
                                                <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                                <button type="submit"><?php echo sr_e(sr_t('community::ui.delete.57f509a8')); ?></button>
                                            </form>
                                        <?php } ?>
                                        <?php if ($communityCommentCanHide) { ?>
                                            <form method="post" action="<?php echo sr_e(sr_url('/community/comment/hide')); ?>">
                                                <?php echo sr_csrf_field(); ?>
                                                <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                                <button type="submit"><?php echo sr_e('숨기기'); ?></button>
                                            </form>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                                <?php if ($communityCommentCanViewBody && (int) $comment['author_account_id'] !== (int) $account['id']) { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/community/report')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="target_type" value="comment">
                                        <input type="hidden" name="target_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                        <p>
                                            <label for="modules_community_view_reason_key_2">
                    <span><?php echo sr_e(sr_t('community::ui.text.162e66be')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                                                <select id="modules_community_view_reason_key_2" name="reason_key" required>
                                                    <?php foreach ($reportReasonKeys as $reasonKey) { ?>
                                                        <option value="<?php echo sr_e($reasonKey); ?>"><?php echo sr_e(sr_community_report_reason_label($reasonKey)); ?></option>
                                                    <?php } ?>
                                                </select>
                                            </label>
                                        </p>
                                        <p>
                                            <label for="modules_community_view_memo_text_2">
                    <span><?php echo sr_e(sr_t('community::ui.text.54791a8b')); ?></span>
                                                <textarea id="modules_community_view_memo_text_2" name="memo_text" rows="3" cols="60"></textarea>
                                            </label>
                                        </p>
                                        <button type="submit"><?php echo sr_e(sr_t('community::ui.text.9fc1481d')); ?></button>
                                    </form>
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
                    <p>
                        <label for="modules_community_view_body_text_2">
                    <span><?php echo sr_e(sr_t('community::ui.text.c9fff683')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                            <textarea id="modules_community_view_body_text_2" name="body_text" rows="5" cols="80" required><?php echo sr_e($commentBody); ?></textarea>
                        </label>
                    </p>
                    <label class="community-comment-secret-toggle">
                        <input type="checkbox" name="is_secret" value="1">
                        <span><?php echo sr_e('비밀 댓글'); ?></span>
                    </label>
                    <button type="submit"><?php echo sr_e(sr_t('community::ui.create.8033fdca')); ?></button>
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
    </main>
<?php sr_public_layout_end(); ?>
