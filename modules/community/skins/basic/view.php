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
?>
<!doctype html>
<html lang="<?php echo toy_e(toy_locale()); ?>">
<head>
    <meta charset="utf-8">
    <?php echo toy_seo_tags($seo, $site ?? null); ?>
    <?php echo toy_stylesheet_tag(); ?>
</head>
<body>
    <main>
        <p>
            <a href="<?php echo toy_e(toy_url('/community')); ?>">커뮤니티</a>
            /
            <a href="<?php echo toy_e(toy_url('/community/board?key=' . rawurlencode((string) $post['board_key']))); ?>">
                <?php echo toy_e((string) $post['board_title']); ?>
            </a>
        </p>

        <article>
            <h1><?php echo toy_e($pageTitle); ?></h1>
            <p>
                작성자: <?php echo toy_e(toy_community_public_author_label($pdo, (int) $post['author_account_id'])); ?>
                /
                작성일: <?php echo toy_e((string) $post['created_at']); ?>
                /
                조회: <?php echo toy_e((string) $post['view_count']); ?>
            </p>
            <?php if (is_array($account)) { ?>
                <?php if (toy_community_account_can_edit_post($post, $account)) { ?>
                    <p><a href="<?php echo toy_e(toy_url('/community/edit?id=' . (string) $post['id'])); ?>">게시글 수정</a></p>
                <?php } ?>
                <?php if (toy_community_account_can_delete_post($post, $account)) { ?>
                    <form method="post" action="<?php echo toy_e(toy_url('/community/delete')); ?>">
                        <?php echo toy_csrf_field(); ?>
                        <input type="hidden" name="post_id" value="<?php echo toy_e((string) $post['id']); ?>">
                        <button type="submit">게시글 삭제</button>
                    </form>
                <?php } ?>
                <form method="post" action="<?php echo toy_e(toy_url('/community/scrap')); ?>">
                    <?php echo toy_csrf_field(); ?>
                    <input type="hidden" name="post_id" value="<?php echo toy_e((string) $post['id']); ?>">
                    <input type="hidden" name="intent" value="<?php echo $isScrapped ? 'remove' : 'add'; ?>">
                    <button type="submit"><?php echo $isScrapped ? '스크랩 해제' : '스크랩'; ?></button>
                </form>
                <form method="post" action="<?php echo toy_e(toy_url('/community/report')); ?>">
                    <?php echo toy_csrf_field(); ?>
                    <input type="hidden" name="target_type" value="post">
                    <input type="hidden" name="target_id" value="<?php echo toy_e((string) $post['id']); ?>">
                    <p>
                        <label>신고 사유<br>
                            <select name="reason_key" required>
                                <?php foreach ($reportReasonKeys as $reasonKey) { ?>
                                    <option value="<?php echo toy_e($reasonKey); ?>"><?php echo toy_e(toy_community_report_reason_label($reasonKey)); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                    </p>
                    <p>
                        <label>신고 메모<br>
                            <textarea name="memo_text" rows="3" cols="60"></textarea>
                        </label>
                    </p>
                    <button type="submit">게시글 신고</button>
                </form>
            <?php } ?>

            <?php foreach ($postNotices as $postNotice) { ?>
                <?php if (is_string($postNotice) && $postNotice !== '') { ?>
                    <p><?php echo toy_e($postNotice); ?></p>
                <?php } ?>
            <?php } ?>

            <?php if ($reportNotice !== '') { ?>
                <p><?php echo toy_e($reportNotice); ?></p>
            <?php } ?>

            <?php if ($reportErrors !== []) { ?>
                <ul>
                    <?php foreach ($reportErrors as $error) { ?>
                        <li><?php echo toy_e($error); ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>

            <?php echo toy_render_output_slot($pdo, [
                'module_key' => 'community',
                'point_key' => 'community.post.view',
                'slot_key' => 'before_content',
                'subject_id' => (string) $post['id'],
            ]); ?>

            <div>
                <?php echo toy_community_plain_text_html((string) $post['body_text']); ?>
            </div>

            <?php if ($attachments !== []) { ?>
                <section>
                    <h2>첨부 이미지</h2>
                    <ul>
                        <?php foreach ($attachments as $attachment) { ?>
                            <li>
                                <a href="<?php echo toy_e(toy_url('/community/attachment?id=' . (string) $attachment['id'])); ?>">
                                    <?php echo toy_e((string) $attachment['original_name']); ?>
                                </a>
                                <?php if ((int) ($attachment['size_bytes'] ?? 0) > 0) { ?>
                                    (<?php echo toy_e(toy_community_format_bytes((int) $attachment['size_bytes'])); ?>)
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ul>
                </section>
            <?php } ?>

            <?php echo toy_render_output_slot($pdo, [
                'module_key' => 'community',
                'point_key' => 'community.post.view',
                'slot_key' => 'after_content',
                'subject_id' => (string) $post['id'],
            ]); ?>
        </article>

        <section id="comments">
            <?php echo toy_render_output_slot($pdo, [
                'module_key' => 'community',
                'point_key' => 'community.post.view',
                'slot_key' => 'before_comments',
                'subject_id' => (string) $post['id'],
            ]); ?>

            <h2>댓글</h2>
            <?php if ($commentNotice !== '') { ?>
                <p><?php echo toy_e($commentNotice); ?></p>
            <?php } ?>

            <?php if ($comments === []) { ?>
                <p>댓글이 없습니다.</p>
            <?php } else { ?>
                <ul>
                    <?php foreach ($comments as $comment) { ?>
                        <li>
                            <p>
                                <?php echo toy_e(toy_community_public_author_label($pdo, (int) $comment['author_account_id'])); ?>
                                /
                                <?php echo toy_e((string) $comment['created_at']); ?>
                            </p>
                            <p><?php echo toy_community_plain_text_html((string) $comment['body_text']); ?></p>
                            <?php if (is_array($account)) { ?>
                                <?php if (toy_community_account_can_edit_comment($comment, $account)) { ?>
                                    <form method="post" action="<?php echo toy_e(toy_url('/community/comment/edit')); ?>">
                                        <?php echo toy_csrf_field(); ?>
                                        <input type="hidden" name="comment_id" value="<?php echo toy_e((string) $comment['id']); ?>">
                                        <p>
                                            <label>댓글 수정<br>
                                                <textarea name="body_text" rows="3" cols="60" required><?php echo toy_e((string) $comment['body_text']); ?></textarea>
                                            </label>
                                        </p>
                                        <button type="submit">댓글 수정</button>
                                    </form>
                                <?php } ?>
                                <?php if (toy_community_account_can_delete_comment($comment, $account)) { ?>
                                    <form method="post" action="<?php echo toy_e(toy_url('/community/comment/delete')); ?>">
                                        <?php echo toy_csrf_field(); ?>
                                        <input type="hidden" name="comment_id" value="<?php echo toy_e((string) $comment['id']); ?>">
                                        <button type="submit">댓글 삭제</button>
                                    </form>
                                <?php } ?>
                                <form method="post" action="<?php echo toy_e(toy_url('/community/report')); ?>">
                                    <?php echo toy_csrf_field(); ?>
                                    <input type="hidden" name="target_type" value="comment">
                                    <input type="hidden" name="target_id" value="<?php echo toy_e((string) $comment['id']); ?>">
                                    <p>
                                        <label>신고 사유<br>
                                            <select name="reason_key" required>
                                                <?php foreach ($reportReasonKeys as $reasonKey) { ?>
                                                    <option value="<?php echo toy_e($reasonKey); ?>"><?php echo toy_e(toy_community_report_reason_label($reasonKey)); ?></option>
                                                <?php } ?>
                                            </select>
                                        </label>
                                    </p>
                                    <p>
                                        <label>신고 메모<br>
                                            <textarea name="memo_text" rows="3" cols="60"></textarea>
                                        </label>
                                    </p>
                                    <button type="submit">댓글 신고</button>
                                </form>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>

            <?php if ($commentErrors !== []) { ?>
                <ul>
                    <?php foreach ($commentErrors as $error) { ?>
                        <li><?php echo toy_e($error); ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>

            <?php if ($canComment) { ?>
                <form method="post" action="<?php echo toy_e(toy_url('/community/comment')); ?>">
                    <?php echo toy_csrf_field(); ?>
                    <input type="hidden" name="post_id" value="<?php echo toy_e((string) $post['id']); ?>">
                    <p>
                        <label>댓글<br>
                            <textarea name="body_text" rows="5" cols="80" required><?php echo toy_e($commentBody); ?></textarea>
                        </label>
                    </p>
                    <button type="submit">댓글 등록</button>
                </form>
            <?php } elseif ($commentUnavailableMessage !== '') { ?>
                <p><?php echo toy_e($commentUnavailableMessage); ?></p>
            <?php } ?>

            <?php echo toy_render_output_slot($pdo, [
                'module_key' => 'community',
                'point_key' => 'community.post.view',
                'slot_key' => 'after_comments',
                'subject_id' => (string) $post['id'],
            ]); ?>
        </section>
    </main>
</body>
</html>
