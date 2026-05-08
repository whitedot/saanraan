<?php

$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : (string) $board['title'] . ' 글쓰기';
$formAction = isset($formAction) && is_string($formAction)
    ? $formAction
    : '/community/write?key=' . rawurlencode((string) $board['board_key']);
$submitLabel = isset($submitLabel) && is_string($submitLabel) ? $submitLabel : '등록';
$seo = [
    'title' => $pageTitle,
    'canonical' => $formAction,
    'robots' => 'noindex, nofollow',
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
            <a href="<?php echo toy_e(toy_url('/community/board?key=' . rawurlencode((string) $board['board_key']))); ?>">
                <?php echo toy_e((string) $board['title']); ?>
            </a>
        </p>

        <h1><?php echo toy_e($pageTitle); ?></h1>

        <?php echo toy_render_output_slot($pdo, [
            'module_key' => 'community',
            'point_key' => 'community.post.form',
            'slot_key' => 'before_form',
            'subject_id' => (string) $board['id'],
        ]); ?>

        <?php if ($errors !== []) { ?>
            <ul>
                <?php foreach ($errors as $error) { ?>
                    <li><?php echo toy_e($error); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <form method="post" action="<?php echo toy_e(toy_url($formAction)); ?>"<?php echo !isset($postIdField) && (int) ($board['image_uploads_enabled'] ?? 0) === 1 ? ' enctype="multipart/form-data"' : ''; ?>>
            <?php echo toy_csrf_field(); ?>
            <?php if (isset($postIdField) && is_int($postIdField)) { ?>
                <input type="hidden" name="post_id" value="<?php echo toy_e((string) $postIdField); ?>">
            <?php } ?>
            <p>
                <label>제목<br>
                    <input type="text" name="title" maxlength="160" value="<?php echo toy_e(is_string($values['title']) ? $values['title'] : ''); ?>" required>
                </label>
            </p>
            <p>
                <label>본문<br>
                    <textarea name="body_text" rows="12" cols="80" required><?php echo toy_e(is_string($values['body_text']) ? $values['body_text'] : ''); ?></textarea>
                </label>
            </p>
            <?php if (!isset($postIdField) && (int) ($board['image_uploads_enabled'] ?? 0) === 1) { ?>
                <p>
                    <label>이미지 첨부<br>
                        <input type="file" name="image_attachment" accept="image/jpeg,image/png,image/webp">
                    </label>
                </p>
            <?php } ?>
            <button type="submit"><?php echo toy_e($submitLabel); ?></button>
        </form>

        <?php echo toy_render_output_slot($pdo, [
            'module_key' => 'community',
            'point_key' => 'community.post.form',
            'slot_key' => 'after_form',
            'subject_id' => (string) $board['id'],
        ]); ?>
    </main>
</body>
</html>
