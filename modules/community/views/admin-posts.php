<?php

$communityPostsPage = isset($communityPostsPage) ? (string) $communityPostsPage : 'posts';
$adminPageTitle = $communityPostsPage === 'comments' ? '커뮤니티 댓글' : '커뮤니티 게시글';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($communityPostsPage !== 'comments') { ?>
<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header"><h2 class="card-title">게시글 목록</h2></div>
    <?php if ($posts === []) { ?>
        <p>게시글이 없습니다.</p>
    <?php } else { ?>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>게시판</th>
                    <th>제목</th>
                    <th>작성자</th>
                    <th>상태</th>
                    <th>댓글</th>
                    <th>첨부</th>
                    <th>작성일</th>
                    <th class="text-end">처리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $post['id']); ?></td>
                        <td><?php echo sr_e((string) $post['board_title']); ?></td>
                        <td>
                            <?php if ((string) $post['status'] === 'published') { ?>
                                <a href="<?php echo sr_e(sr_url('/community/post?id=' . (string) $post['id'])); ?>">
                                    <?php echo sr_e((string) $post['title']); ?>
                                </a>
                            <?php } else { ?>
                                <?php echo sr_e((string) $post['title']); ?>
                            <?php } ?>
                        </td>
                        <td><?php echo sr_e(sr_community_report_account_label(
                            is_string($post['author_display_name'] ?? null) ? $post['author_display_name'] : null,
                            (int) $post['author_account_id']
                        )); ?></td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $post['status'], 'content_status')); ?></td>
                        <td><?php echo sr_e((string) $post['published_comment_count']); ?></td>
                        <td><?php echo sr_e((string) ($post['active_attachment_count'] ?? 0)); ?></td>
                        <td><?php echo sr_e((string) $post['created_at']); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/community/posts')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="post_status">
                                <input type="hidden" name="post_id" value="<?php echo sr_e((string) $post['id']); ?>">
                                <label>상태
                                    <select name="status" class="form-select">
                                        <?php foreach ($allowedPostStatuses as $status) { ?>
                                            <option value="<?php echo sr_e($status); ?>"<?php echo $status === (string) $post['status'] ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                                        <?php } ?>
                                    </select>
                                </label>
                                <button type="submit" class="btn btn-sm btn-soft-default">변경</button>
                            </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    <?php } ?>
</section>
<?php } ?>

<?php if ($communityPostsPage === 'comments') { ?>
<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header"><h2 class="card-title">댓글 목록</h2></div>
    <?php if ($comments === []) { ?>
        <p>댓글이 없습니다.</p>
    <?php } else { ?>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>게시글</th>
                    <th>작성자</th>
                    <th>본문</th>
                    <th>상태</th>
                    <th>작성일</th>
                    <th class="text-end">처리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($comments as $comment) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $comment['id']); ?></td>
                        <td>
                            <a href="<?php echo sr_e(sr_url('/community/post?id=' . (string) $comment['post_id'])); ?>">
                                <?php echo sr_e((string) $comment['post_title']); ?>
                            </a>
                        </td>
                        <td><?php echo sr_e(sr_community_report_account_label(
                            is_string($comment['author_display_name'] ?? null) ? $comment['author_display_name'] : null,
                            (int) $comment['author_account_id']
                        )); ?></td>
                        <td><?php echo sr_community_plain_text_html((string) $comment['body_text']); ?></td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $comment['status'], 'content_status')); ?></td>
                        <td><?php echo sr_e((string) $comment['created_at']); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/community/comments')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="comment_status">
                                <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $comment['id']); ?>">
                                <label>상태
                                    <select name="status" class="form-select">
                                        <?php foreach ($allowedCommentStatuses as $status) { ?>
                                            <option value="<?php echo sr_e($status); ?>"<?php echo $status === (string) $comment['status'] ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></option>
                                        <?php } ?>
                                    </select>
                                </label>
                                <button type="submit" class="btn btn-sm btn-soft-default">변경</button>
                            </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    <?php } ?>
</section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
