<?php

$pageTitle = (string) $post['title'];
?>
<!doctype html>
<html lang="<?php echo toy_e(toy_locale()); ?>">
<head>
    <meta charset="utf-8">
    <title><?php echo toy_e($pageTitle); ?></title>
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
            </p>

            <?php echo toy_render_output_slot($pdo, [
                'module_key' => 'community',
                'point_key' => 'community.post.view',
                'slot_key' => 'before_content',
                'subject_id' => (string) $post['id'],
            ]); ?>

            <div>
                <?php echo toy_community_plain_text_html((string) $post['body_text']); ?>
            </div>

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
