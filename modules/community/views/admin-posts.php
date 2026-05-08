<?php

$adminPageTitle = '커뮤니티 게시글';
include TOY_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo toy_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo toy_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<section>
    <h2>게시글 목록</h2>
    <?php if ($posts === []) { ?>
        <p>게시글이 없습니다.</p>
    <?php } else { ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>게시판</th>
                    <th>제목</th>
                    <th>작성자</th>
                    <th>상태</th>
                    <th>댓글</th>
                    <th>작성일</th>
                    <th>처리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post) { ?>
                    <tr>
                        <td><?php echo toy_e((string) $post['id']); ?></td>
                        <td><?php echo toy_e((string) $post['board_title']); ?></td>
                        <td>
                            <?php if ((string) $post['status'] === 'published') { ?>
                                <a href="<?php echo toy_e(toy_url('/community/post?id=' . (string) $post['id'])); ?>">
                                    <?php echo toy_e((string) $post['title']); ?>
                                </a>
                            <?php } else { ?>
                                <?php echo toy_e((string) $post['title']); ?>
                            <?php } ?>
                        </td>
                        <td><?php echo toy_e((string) ($post['author_display_name'] ?? '') . ' #' . (string) $post['author_account_id']); ?></td>
                        <td><?php echo toy_e((string) $post['status']); ?></td>
                        <td><?php echo toy_e((string) $post['published_comment_count']); ?></td>
                        <td><?php echo toy_e((string) $post['created_at']); ?></td>
                        <td>
                            <form method="post" action="<?php echo toy_e(toy_url('/admin/community/posts')); ?>">
                                <?php echo toy_csrf_field(); ?>
                                <input type="hidden" name="post_id" value="<?php echo toy_e((string) $post['id']); ?>">
                                <label>상태
                                    <select name="status">
                                        <?php foreach ($allowedStatuses as $status) { ?>
                                            <option value="<?php echo toy_e($status); ?>"<?php echo $status === (string) $post['status'] ? ' selected' : ''; ?>><?php echo toy_e($status); ?></option>
                                        <?php } ?>
                                    </select>
                                </label>
                                <button type="submit">변경</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</section>

<?php include TOY_ROOT . '/modules/admin/views/layout-footer.php'; ?>
