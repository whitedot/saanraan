<?php

$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : (string) $board['title'] . ' 글쓰기';
$formAction = isset($formAction) && is_string($formAction)
    ? $formAction
    : '/community/write?key=' . rawurlencode((string) $board['board_key']);
$submitLabel = isset($submitLabel) && is_string($submitLabel) ? $submitLabel : '등록';
$attachmentMaxBytes = min(10485760, max(1, (int) ($settings['attachment_max_bytes'] ?? 2097152)));
$fileAttachmentMaxBytes = min(20971520, max(1024, (int) ($settings['file_attachment_max_bytes'] ?? 5242880)));
$fileAttachmentMaxCount = min(5, max(0, (int) ($settings['file_attachment_max_count'] ?? 3)));
$fileAllowedExtensions = is_array($settings['file_allowed_extensions'] ?? null) ? sr_community_normalize_file_extensions($settings['file_allowed_extensions']) : [];
$fileUploadEnabled = !isset($postIdField) && (int) ($board['file_uploads_enabled'] ?? 0) === 1 && $fileAttachmentMaxCount > 0;
$imageUploadEnabled = !isset($postIdField) && (int) ($board['image_uploads_enabled'] ?? 0) === 1 && (int) ($settings['attachment_max_count'] ?? 1) > 0;
$seo = [
    'title' => $pageTitle,
    'canonical' => $formAction,
    'robots' => 'noindex, nofollow',
];
if (is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <?php if (function_exists('sr_popup_layer_render_public_layer') && sr_module_enabled($pdo, 'popup_layer')) { ?>
            <?php echo sr_popup_layer_render_public_layer($pdo, (int) ($board['popup_layer_form_id'] ?? 0)); ?>
        <?php } ?>

        <p>
            <a href="<?php echo sr_e(sr_url('/community')); ?>">커뮤니티</a>
            /
            <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $board['board_key']))); ?>">
                <?php echo sr_e((string) $board['title']); ?>
            </a>
        </p>

        <h1><?php echo sr_e($pageTitle); ?></h1>

        <?php echo sr_render_output_slot($pdo, [
            'module_key' => 'community',
            'point_key' => 'community.post.form',
            'slot_key' => 'before_form',
            'subject_id' => (string) $board['id'],
        ]); ?>
        <?php if (function_exists('sr_banner_render_public_banner') && sr_module_enabled($pdo, 'banner')) { ?>
            <?php echo sr_banner_render_public_banner($pdo, (int) ($board['banner_before_form_id'] ?? 0)); ?>
        <?php } ?>

        <?php if ($errors !== []) { ?>
            <ul>
                <?php foreach ($errors as $error) { ?>
                    <li><?php echo sr_e($error); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <form method="post" action="<?php echo sr_e(sr_url($formAction)); ?>"<?php echo $imageUploadEnabled || $fileUploadEnabled ? ' enctype="multipart/form-data"' : ''; ?>>
            <?php echo sr_csrf_field(); ?>
            <?php if (isset($postIdField) && is_int($postIdField)) { ?>
                <input type="hidden" name="post_id" value="<?php echo sr_e((string) $postIdField); ?>">
            <?php } ?>
            <p>
                <label>제목<br>
                    <input type="text" name="title" maxlength="160" value="<?php echo sr_e(is_string($values['title']) ? $values['title'] : ''); ?>" required>
                </label>
            </p>
            <p>
                <label>본문<br>
                    <textarea name="body_text" rows="12" cols="80" required><?php echo sr_e(is_string($values['body_text']) ? $values['body_text'] : ''); ?></textarea>
                </label>
            </p>
            <?php if ($imageUploadEnabled) { ?>
                <p>
                    <label>이미지 첨부<br>
                        <input type="file" name="image_attachment" accept="image/jpeg,image/png,image/webp">
                    </label>
                    <br>
                    <small>JPEG, PNG, WebP / 파일당 최대 <?php echo sr_e(sr_community_format_bytes($attachmentMaxBytes)); ?></small>
                </p>
            <?php } ?>
            <?php if ($fileUploadEnabled) { ?>
                <p>
                    <label>파일 첨부<br>
                        <input type="file" name="file_attachments[]" multiple>
                    </label>
                    <br>
                    <small>
                        최대 <?php echo sr_e((string) $fileAttachmentMaxCount); ?>개 /
                        파일당 최대 <?php echo sr_e(sr_community_format_bytes($fileAttachmentMaxBytes)); ?> /
                        허용: <?php echo sr_e(implode(', ', $fileAllowedExtensions)); ?>
                    </small>
                </p>
            <?php } ?>
            <button type="submit"><?php echo sr_e($submitLabel); ?></button>
        </form>

        <?php echo sr_render_output_slot($pdo, [
            'module_key' => 'community',
            'point_key' => 'community.post.form',
            'slot_key' => 'after_form',
            'subject_id' => (string) $board['id'],
        ]); ?>
        <?php if (function_exists('sr_banner_render_public_banner') && sr_module_enabled($pdo, 'banner')) { ?>
            <?php echo sr_banner_render_public_banner($pdo, (int) ($board['banner_after_form_id'] ?? 0)); ?>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
