<?php

$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
$post = isset($post) && is_array($post) ? $post : [];
$comments = isset($comments) && is_array($comments) ? $comments : [];
$fileAttachments = isset($fileAttachments) && is_array($fileAttachments) ? $fileAttachments : [];
$imageAttachments = isset($imageAttachments) && is_array($imageAttachments) ? $imageAttachments : [];
$config = isset($config) && is_array($config) ? $config : sr_runtime_config();
$memberSettings = sr_member_settings($pdo);
$seo = sr_community_post_seo_meta($pdo, $post, empty($paidReadConfirmationRequired) && empty($paidReadBlocked) && !empty($canViewPostBody));
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings, [
    'consumer_target' => 'community.post',
    'stylesheets' => array_merge([
        '/modules/banner/assets/module.css',
        '/modules/popup_layer/assets/module.css',
        '/modules/reaction/assets/module.css',
    ], sr_community_post_body_embed_stylesheets($post, $communityLayoutSettings, $pdo ?? null)),
]));
?>

<main class="example-community-theme example-community-reader" data-example-theme-view="community.post">
    <p class="example-community-backlink">
        <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) ($post['board_key'] ?? '')))); ?>">
            <?php echo sr_e((string) ($post['board_title'] ?? '게시판')); ?>
        </a>
    </p>

    <article class="example-community-reader-grid">
        <aside class="example-community-panel">
            <p class="example-content-kicker">POST THEME VIEW</p>
            <?php echo sr_community_time_html((string) ($post['created_at'] ?? '')); ?>
            <p>조회 <?php echo sr_e(number_format((int) ($post['view_count'] ?? 0))); ?></p>
            <?php if (is_array($account ?? null) && sr_community_account_can_edit_post($post, $account)) { ?>
                <p><a class="btn btn-solid-light" href="<?php echo sr_e(sr_url('/community/edit?id=' . (string) (int) ($post['id'] ?? 0))); ?>">수정</a></p>
            <?php } ?>
        </aside>

        <section class="example-community-reader-main">
            <header class="example-community-hero">
                <h1><?php echo sr_e((string) ($post['title'] ?? '')); ?></h1>
                <p>
                    <?php $postAuthorLabel = sr_community_author_label_from_row($post, $config, $canViewMemberIdentifiers, $memberSettings, $pdo); ?>
                    <?php echo sr_member_public_name_menu_html($pdo, is_array($account ?? null) ? $account : null, (int) ($post['author_account_id'] ?? 0), $postAuthorLabel, [
                        'community_board_key' => (string) ($post['board_key'] ?? ''),
                        'community_board_accessible' => is_array($postBoard ?? null),
                        'return_to' => (string) ($_SERVER['REQUEST_URI'] ?? '/'),
                    ]); ?>
                </p>
            </header>

            <?php echo sr_public_feedback_toasts('community', implode(' ', array_filter(array_map('strval', $postNotices ?? []))) . ((string) ($reportNotice ?? '') !== '' ? ' ' . (string) $reportNotice : ''), is_array($reportErrors ?? null) ? $reportErrors : []); ?>
            <?php echo sr_render_output_slot($pdo, [
                'module_key' => 'community',
                'point_key' => 'community.post.view',
                'slot_key' => 'before_content',
                'subject_id' => (string) ($post['id'] ?? ''),
            ]); ?>

            <?php if (empty($canViewPostBody)) { ?>
                <section class="example-community-panel"><p>비밀글입니다.</p></section>
            <?php } elseif (!empty($paidReadConfirmationRequired)) { ?>
                <section class="example-community-panel">
                    <h2>열람 확인</h2>
                    <?php
                    $assetConfirmationAssetLabel = (string) ($paidReadConfirmationResult['asset_label'] ?? '');
                    $assetConfirmationAmount = (int) ($paidReadConfirmationResult['amount'] ?? 0);
                    $assetConfirmationMessage = (string) (($paidReadConfirmationResult['message'] ?? '') ?: (trim($assetConfirmationAssetLabel . ' ' . number_format($assetConfirmationAmount)) . ' 차감 후 게시글을 열람하시겠습니까?'));
                    $assetConfirmationAction = '/community/post';
                    $assetConfirmationId = (int) ($post['id'] ?? 0);
                    $assetConfirmationRequestToken = (string) ($paidReadConfirmationRequestToken ?? '');
                    $assetConfirmationTitle = '게시글 열람 확인';
                    $assetConfirmationSubmitLabel = sr_t('community::ui.text.ac5b575f');
                    $assetConfirmationCouponIssues = is_array($paidReadConfirmationCouponIssues ?? null) ? $paidReadConfirmationCouponIssues : [];
                    $assetConfirmationExchangeSuggestion = is_array($paidReadConfirmationResult['asset_exchange_suggestion'] ?? null) ? $paidReadConfirmationResult['asset_exchange_suggestion'] : [];
                    $assetConfirmationModalId = 'example_community_paid_read_confirmation_modal';
                    $assetConfirmationCloseOnSubmit = false;
                    include SR_ROOT . '/modules/community/views/asset-confirmation-modal.php';
                    ?>
                </section>
            <?php } elseif (!empty($paidReadBlocked)) { ?>
                <section class="example-community-panel"><p><?php echo sr_e((string) ($paidReadBlockedMessage ?? sr_t('community::action.error.paid_read_post_failed'))); ?></p></section>
            <?php } else { ?>
                <div class="example-community-body">
                    <?php echo sr_community_extra_fields_display_html(sr_community_extra_field_values_from_json((string) ($post['extra_values_json'] ?? ''))); ?>
                    <?php echo sr_community_post_body_html($post, $communityLayoutSettings, $pdo); ?>
                </div>

                <?php if (function_exists('sr_reaction_render_widget') && !empty($communityReactionsEnabled)) { ?>
                    <?php echo sr_reaction_render_widget($pdo, 'community', 'post', (string) (int) ($post['id'] ?? 0), is_array($account ?? null) ? $account : null); ?>
                <?php } ?>

                <?php if ($fileAttachments !== []) { ?>
                    <section class="example-community-panel">
                        <h2>Files</h2>
                        <ol>
                            <?php foreach ($fileAttachments as $attachment) { ?>
                                <li>
                                    <a href="<?php echo sr_e(sr_url('/community/attachment?id=' . rawurlencode((string) (int) ($attachment['id'] ?? 0)))); ?>">
                                        <?php echo sr_e((string) ($attachment['original_name'] ?? '첨부 파일')); ?>
                                    </a>
                                </li>
                            <?php } ?>
                        </ol>
                    </section>
                <?php } ?>
            <?php } ?>

            <?php echo sr_render_output_slot($pdo, [
                'module_key' => 'community',
                'point_key' => 'community.post.view',
                'slot_key' => 'after_content',
                'subject_id' => (string) ($post['id'] ?? ''),
            ]); ?>
        </section>
    </article>

    <?php if (!empty($canViewPostBody) && empty($paidReadConfirmationRequired) && empty($paidReadBlocked)) { ?>
        <section id="comments" class="example-community-panel example-community-comments">
            <h2>Comments</h2>
            <?php echo sr_public_feedback_toasts('community', (string) ($commentNotice ?? ''), is_array($commentErrors ?? null) ? $commentErrors : []); ?>
            <?php if ($comments === []) { ?>
                <p>댓글이 없습니다.</p>
            <?php } else { ?>
                <ol>
                    <?php foreach ($comments as $comment) { ?>
                        <?php $commentCanViewBody = sr_community_account_can_view_comment_body($comment, $post, is_array($account ?? null) ? $account : null, $pdo); ?>
                        <li id="community-comment-<?php echo sr_e((string) (int) ($comment['id'] ?? 0)); ?>">
                            <strong><?php echo sr_e(sr_community_author_label_from_row($comment, $config, $canViewMemberIdentifiers, $memberSettings, $pdo)); ?></strong>
                            <?php echo sr_community_time_html((string) ($comment['created_at'] ?? '')); ?>
                            <?php if ($commentCanViewBody) { ?>
                                <p><?php echo sr_member_mention_plain_text_html((string) ($comment['body_text'] ?? '')); ?></p>
                            <?php } else { ?>
                                <p>비밀 댓글입니다.</p>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ol>
            <?php } ?>

            <?php if (!empty($canComment)) { ?>
                <form method="post" action="<?php echo sr_e(sr_url('/community/comment')); ?>" class="example-community-comment-form">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="post_id" value="<?php echo sr_e((string) ($post['id'] ?? '')); ?>">
                    <input type="hidden" name="parent_comment_id" value="0">
                    <label for="example_community_comment_body">댓글</label>
                    <textarea id="example_community_comment_body" name="body_text" rows="4" cols="80" required<?php echo is_array($account ?? null) ? ' data-sr-mention-input data-sr-mention-endpoint="' . sr_e(sr_url('/member/mention-search')) . '"' : ''; ?>><?php echo (int) ($commentParentId ?? 0) < 1 ? sr_e((string) ($commentBody ?? '')) : ''; ?></textarea>
                    <?php if (!is_array($account ?? null)) { ?>
                        <label for="example_community_comment_guest_name">작성자명</label>
                        <input id="example_community_comment_guest_name" type="text" name="guest_author_name" maxlength="120" value="<?php echo sr_e((string) ($commentGuestAuthorName ?? '')); ?>" required>
                        <label for="example_community_comment_guest_password">비밀번호</label>
                        <input id="example_community_comment_guest_password" type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="new-password" required>
                    <?php } ?>
                    <?php if (!empty($secretCommentsEnabled)) { ?>
                        <label><input type="checkbox" name="is_secret" value="1"<?php echo !empty($commentIsSecret) ? ' checked' : ''; ?>> 비밀 댓글</label>
                    <?php } ?>
                    <?php echo sr_community_privacy_consent_field_html($pdo, ['id' => (int) ($post['board_id'] ?? 0)] + $post, ['comment'], true, 'comment_new'); ?>
                    <?php if (function_exists('sr_antispam_challenge_render')) { ?>
                        <?php echo sr_antispam_challenge_render($pdo, 'community.comment.guest', 'community_comment_' . (string) (int) ($post['id'] ?? 0), ['account' => is_array($account ?? null) ? $account : null]); ?>
                    <?php } ?>
                    <button type="submit" class="btn btn-solid-primary">댓글 등록</button>
                </form>
            <?php } elseif ((string) ($commentUnavailableMessage ?? '') !== '') { ?>
                <p><?php echo sr_e((string) $commentUnavailableMessage); ?></p>
            <?php } ?>
        </section>
    <?php } ?>
</main>

<?php if (function_exists('sr_reaction_public_script_html')) { ?>
    <?php echo sr_reaction_public_script_html(); ?>
<?php } ?>
<?php sr_public_layout_end(); ?>
