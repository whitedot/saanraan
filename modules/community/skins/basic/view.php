<?php

$pageTitle = (string) $post['title'];
$seoDescription = preg_replace('/\s+/', ' ', trim((string) $post['body_text']));
if (!is_string($seoDescription)) {
    $seoDescription = '';
}
$seoDescription = function_exists('mb_substr') ? mb_substr($seoDescription, 0, 160) : substr($seoDescription, 0, 160);
$seo = [
    'title' => $pageTitle,
    'description' => $seoDescription,
    'canonical' => '/community/post?id=' . (string) $post['id'],
    'robots' => (string) $post['read_policy'] === 'public' ? 'index, follow' : 'noindex, nofollow',
];
if (is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'stylesheets' => sr_community_skin_stylesheets($skinKey ?? 'basic'),
]);
?>
    <main>
        <?php if (function_exists('sr_popup_layer_render_public_layer') && sr_module_enabled($pdo, 'popup_layer')) { ?>
            <?php echo sr_popup_layer_render_public_layer($pdo, (int) ($post['popup_layer_view_id'] ?? 0)); ?>
        <?php } ?>

        <p>
            <a href="<?php echo sr_e(sr_url('/community')); ?>">커뮤니티</a>
            /
            <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $post['board_key']))); ?>">
                <?php echo sr_e((string) $post['board_title']); ?>
            </a>
        </p>

        <article>
            <h1><?php echo sr_e($pageTitle); ?></h1>
            <p>
                작성자: <?php echo sr_e(sr_community_public_author_label($pdo, (int) $post['author_account_id'], $canViewMemberIdentifiers, $config)); ?>
                /
                작성일: <?php echo sr_e((string) $post['created_at']); ?>
                /
                조회: <?php echo sr_e((string) $post['view_count']); ?>
            </p>
            <?php if (is_array($account)) { ?>
                <?php if (sr_community_account_can_edit_post($post, $account)) { ?>
                    <p><a href="<?php echo sr_e(sr_url('/community/edit?id=' . (string) $post['id'])); ?>">게시글 수정</a></p>
                <?php } ?>
                <?php if (sr_community_account_can_delete_post($post, $account)) { ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/community/delete')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                        <button type="submit">게시글 삭제</button>
                    </form>
                <?php } ?>
                <form method="post" action="<?php echo sr_e(sr_url('/community/scrap')); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                    <input type="hidden" name="intent" value="<?php echo $isScrapped ? 'remove' : 'add'; ?>">
                    <button type="submit"><?php echo $isScrapped ? '스크랩 해제' : '스크랩'; ?></button>
                </form>
                <?php if ($canReportPost) { ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/community/report')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="target_type" value="post">
                        <input type="hidden" name="target_id" value="<?php echo sr_e((string) $post['id']); ?>">
                        <p>
                            <label for="modules_community_view_reason_key">
                    <span>신고 사유 <span class="sr-required-label">(필수)</span></span>
                                <select id="modules_community_view_reason_key" name="reason_key" required>
                                    <?php foreach ($reportReasonKeys as $reasonKey) { ?>
                                        <option value="<?php echo sr_e($reasonKey); ?>"><?php echo sr_e(sr_community_report_reason_label($reasonKey)); ?></option>
                                    <?php } ?>
                                </select>
                            </label>
                        </p>
                        <p>
                            <label for="modules_community_view_memo_text">
                    <span>신고 메모</span>
                                <textarea id="modules_community_view_memo_text" name="memo_text" rows="3" cols="60"></textarea>
                            </label>
                        </p>
                        <button type="submit">게시글 신고</button>
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

            <div>
                <?php echo sr_community_plain_text_html((string) $post['body_text']); ?>
            </div>

            <?php if ($imageAttachments !== []) { ?>
                <section>
                    <h2>첨부 이미지</h2>
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
                    <h2>첨부파일</h2>
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

        <section id="comments">
            <?php echo sr_render_output_slot($pdo, [
                'module_key' => 'community',
                'point_key' => 'community.post.view',
                'slot_key' => 'before_comments',
                'subject_id' => (string) $post['id'],
            ]); ?>

            <h2>댓글</h2>
            <?php if ($commentNotice !== '') { ?>
                <p><?php echo sr_e($commentNotice); ?></p>
            <?php } ?>

            <?php if ($comments === []) { ?>
                <p>댓글이 없습니다.</p>
            <?php } else { ?>
                <ul>
                    <?php foreach ($comments as $comment) { ?>
                        <li>
                            <p>
                                <?php echo sr_e(sr_community_public_author_label($pdo, (int) $comment['author_account_id'], $canViewMemberIdentifiers, $config)); ?>
                                /
                                <?php echo sr_e((string) $comment['created_at']); ?>
                            </p>
                            <p><?php echo sr_community_plain_text_html((string) $comment['body_text']); ?></p>
                            <?php if (is_array($account)) { ?>
                                <?php if (sr_community_account_can_edit_comment($comment, $account)) { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/community/comment/edit')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                        <p>
                                            <label for="modules_community_view_body_text">
                    <span>댓글 수정 <span class="sr-required-label">(필수)</span></span>
                                                <textarea id="modules_community_view_body_text" name="body_text" rows="3" cols="60" required><?php echo sr_e((string) $comment['body_text']); ?></textarea>
                                            </label>
                                        </p>
                                        <button type="submit">댓글 수정</button>
                                    </form>
                                <?php } ?>
                                <?php if (sr_community_account_can_delete_comment($comment, $account)) { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/community/comment/delete')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                        <button type="submit">댓글 삭제</button>
                                    </form>
                                <?php } ?>
                                <?php if ((int) $comment['author_account_id'] !== (int) $account['id']) { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/community/report')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="target_type" value="comment">
                                        <input type="hidden" name="target_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                        <p>
                                            <label for="modules_community_view_reason_key_2">
                    <span>신고 사유 <span class="sr-required-label">(필수)</span></span>
                                                <select id="modules_community_view_reason_key_2" name="reason_key" required>
                                                    <?php foreach ($reportReasonKeys as $reasonKey) { ?>
                                                        <option value="<?php echo sr_e($reasonKey); ?>"><?php echo sr_e(sr_community_report_reason_label($reasonKey)); ?></option>
                                                    <?php } ?>
                                                </select>
                                            </label>
                                        </p>
                                        <p>
                                            <label for="modules_community_view_memo_text_2">
                    <span>신고 메모</span>
                                                <textarea id="modules_community_view_memo_text_2" name="memo_text" rows="3" cols="60"></textarea>
                                            </label>
                                        </p>
                                        <button type="submit">댓글 신고</button>
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
                <form method="post" action="<?php echo sr_e(sr_url('/community/comment')); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                    <p>
                        <label for="modules_community_view_body_text_2">
                    <span>댓글 <span class="sr-required-label">(필수)</span></span>
                            <textarea id="modules_community_view_body_text_2" name="body_text" rows="5" cols="80" required><?php echo sr_e($commentBody); ?></textarea>
                        </label>
                    </p>
                    <button type="submit">댓글 등록</button>
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
    </main>
<?php sr_public_layout_end(); ?>
