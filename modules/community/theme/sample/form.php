<?php

$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : (string) ($board['title'] ?? '게시글') . ' 작성';
$formAction = isset($formAction) && is_string($formAction) ? $formAction : '/community/write?key=' . rawurlencode((string) ($board['board_key'] ?? ''));
$submitLabel = isset($submitLabel) && is_string($submitLabel) ? $submitLabel : sr_t('community::ui.create.bb216f10');
$values = isset($values) && is_array($values) ? $values : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$fileAttachmentMaxCount = min(5, max(0, (int) ($settings['file_attachment_max_count'] ?? 3)));
$fileUploadEnabled = !isset($postIdField) && (int) ($board['file_uploads_enabled'] ?? 0) === 1 && $fileAttachmentMaxCount > 0;
$imageUploadEnabled = !isset($postIdField) && (int) ($board['image_uploads_enabled'] ?? 0) === 1 && (int) ($settings['attachment_max_count'] ?? 1) > 0;
$isGuestAuthorForm = isset($isGuestAuthor) && !empty($isGuestAuthor);
$showGuestAuthorFields = $isGuestAuthorForm && !isset($postIdField);
if ($isGuestAuthorForm) {
    $fileUploadEnabled = false;
    $imageUploadEnabled = false;
}
$communityEditorKey = !$isGuestAuthorForm && $pdo instanceof PDO ? sr_community_effective_post_editor($pdo, $board, $settings) : 'textarea';
$ckeditorEnabled = $communityEditorKey === 'ckeditor';
$communityEditorToolbarPreset = $pdo instanceof PDO ? sr_community_post_toolbar_preset($pdo, $settings) : 'community_post_basic';
$editorPostId = isset($postIdField) && is_int($postIdField) ? $postIdField : 0;
$communityEditorAttributes = !$isGuestAuthorForm && $pdo instanceof PDO ? sr_editor_textarea_attributes($pdo, $communityEditorKey, $communityEditorToolbarPreset) : '';
if ($ckeditorEnabled) {
    $communityEditorAttributes .= ' data-sr-editor-upload-url="' . sr_e(sr_community_body_file_upload_url($board, $editorPostId)) . '" data-sr-editor-upload-field="upload" data-sr-editor-upload-csrf="' . sr_e(sr_csrf_token()) . '" data-sr-editor-upload-token="' . sr_e(sr_community_body_file_upload_token()) . '"';
}
$communityPrivacyConsentDisplayTargets = ['post'];
if (($ckeditorEnabled || (!isset($postIdField) && ($imageUploadEnabled || $fileUploadEnabled))) && sr_community_privacy_consent_required_for($pdo, $board, 'attachment_upload')) {
    $communityPrivacyConsentDisplayTargets[] = 'attachment_upload';
}
$communityPrivacyConsentBrowserRequired = sr_community_privacy_consent_required_for($pdo, $board, 'post');
$seo = ['title' => $pageTitle, 'canonical' => $formAction, 'robots' => 'noindex, nofollow'];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings, [
    'consumer_target' => 'community.form',
    'output_slots' => [
        ['module_key' => 'community', 'point_key' => 'community.post.form', 'slot_key' => 'before_form'],
        ['module_key' => 'community', 'point_key' => 'community.post.form', 'slot_key' => 'after_form'],
    ],
]));
?>

<main class="example-community-theme example-community-form" data-example-theme-view="community.form">
    <header class="example-community-hero">
        <p class="example-content-kicker">POST FORM FROM THEME</p>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <p><a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) ($board['board_key'] ?? '')))); ?>"><?php echo sr_e((string) ($board['title'] ?? '게시판')); ?></a></p>
    </header>

    <?php echo sr_render_output_slot($pdo, [
        'module_key' => 'community',
        'point_key' => 'community.post.form',
        'slot_key' => 'before_form',
        'subject_id' => (string) ($board['id'] ?? ''),
    ]); ?>
    <?php echo sr_public_feedback_toasts('community', '', $errors); ?>

    <form class="example-community-form-grid" method="post" action="<?php echo sr_e(sr_url($formAction)); ?>"<?php echo $imageUploadEnabled || $fileUploadEnabled ? ' enctype="multipart/form-data"' : ''; ?>>
        <?php echo sr_csrf_field(); ?>
        <?php if (isset($postIdField) && is_int($postIdField)) { ?>
            <input type="hidden" name="post_id" value="<?php echo sr_e((string) $postIdField); ?>">
        <?php } ?>
        <?php if ($showGuestAuthorFields) { ?>
            <label>작성자명 <input type="text" name="guest_author_name" maxlength="120" value="<?php echo sr_e((string) ($values['guest_author_name'] ?? '')); ?>" required></label>
            <label>수정/삭제 비밀번호 <input type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="new-password" required></label>
        <?php } ?>
        <?php if (isset($categories) && is_array($categories) && $categories !== []) { ?>
            <label>
                카테고리
                <select name="category_id"<?php echo !empty($categoryRequired) ? ' required' : ''; ?>>
                    <option value="">선택 안 함</option>
                    <?php foreach ($categories as $category) { ?>
                        <option value="<?php echo sr_e((string) $category['id']); ?>"<?php echo (int) ($values['category_id'] ?? 0) === (int) $category['id'] ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $category['title']); ?>
                        </option>
                    <?php } ?>
                </select>
            </label>
        <?php } ?>
        <label>제목 <input type="text" name="title" maxlength="160" value="<?php echo sr_e((string) ($values['title'] ?? '')); ?>" required></label>
        <label class="example-community-form-wide">
            본문
            <textarea name="body_text" rows="12"<?php echo $ckeditorEnabled ? '' : ' required'; ?><?php echo $communityEditorAttributes; ?>><?php echo sr_e((string) ($values['body_text'] ?? '')); ?></textarea>
        </label>
        <?php echo sr_community_extra_fields_form_html(is_array($extraFieldDefinitions ?? null) ? $extraFieldDefinitions : [], is_array($extraFieldValues ?? null) ? $extraFieldValues : []); ?>
        <?php if (!empty($secretPostsEnabled)) { ?>
            <label><input type="checkbox" name="is_secret" value="1"<?php echo (int) ($values['is_secret'] ?? 0) === 1 ? ' checked' : ''; ?>> 비밀글</label>
        <?php } ?>
        <?php if ($imageUploadEnabled) { ?>
            <label>이미지 <input type="file" name="image_attachment" accept="image/jpeg,image/png,image/webp"></label>
        <?php } ?>
        <?php if ($fileUploadEnabled) { ?>
            <label>첨부 파일 <input type="file" name="file_attachments[]" multiple></label>
        <?php } ?>
        <?php echo sr_community_privacy_consent_field_html($pdo, $board, $communityPrivacyConsentDisplayTargets, $communityPrivacyConsentBrowserRequired, isset($postIdField) ? 'post_edit' : 'post_write'); ?>
        <?php if (!isset($postIdField) && function_exists('sr_antispam_challenge_render')) { ?>
            <?php echo sr_antispam_challenge_render($pdo, 'community.post.guest', 'community_post_' . (string) (int) ($board['id'] ?? 0), $antispamPostContext ?? ['account' => null]); ?>
        <?php } ?>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e($submitLabel); ?></button>
    </form>

    <?php echo sr_render_output_slot($pdo, [
        'module_key' => 'community',
        'point_key' => 'community.post.form',
        'slot_key' => 'after_form',
        'subject_id' => (string) ($board['id'] ?? ''),
    ]); ?>
    <?php if ($ckeditorEnabled && function_exists('sr_ckeditor_public_assets_html')) { ?>
        <?php echo sr_ckeditor_public_assets_html($pdo, $communityEditorToolbarPreset); ?>
    <?php } ?>
</main>

<?php sr_public_layout_end(); ?>
