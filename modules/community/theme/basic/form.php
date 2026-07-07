<?php

$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : (string) $board['title'] . sr_t('community::ui.text.b542075c');
$formAction = isset($formAction) && is_string($formAction)
    ? $formAction
    : '/community/write?key=' . rawurlencode((string) $board['board_key']);
$submitLabel = isset($submitLabel) && is_string($submitLabel) ? $submitLabel : sr_t('community::ui.create.bb216f10');
$attachmentMaxBytes = min(10485760, max(1, (int) ($settings['attachment_max_bytes'] ?? 2097152)));
$fileAttachmentMaxBytes = min(20971520, max(1024, (int) ($settings['file_attachment_max_bytes'] ?? 5242880)));
$fileAttachmentMaxCount = min(5, max(0, (int) ($settings['file_attachment_max_count'] ?? 3)));
$fileAllowedExtensions = is_array($settings['file_allowed_extensions'] ?? null) ? sr_community_normalize_file_extensions($settings['file_allowed_extensions']) : [];
$fileUploadEnabled = !isset($postIdField) && (int) ($board['file_uploads_enabled'] ?? 0) === 1 && $fileAttachmentMaxCount > 0;
$imageUploadEnabled = !isset($postIdField) && (int) ($board['image_uploads_enabled'] ?? 0) === 1 && (int) ($settings['attachment_max_count'] ?? 1) > 0;
$isGuestAuthorForm = isset($isGuestAuthor) && !empty($isGuestAuthor);
$showGuestAuthorFields = $isGuestAuthorForm && !isset($postIdField);
$canWriteNotice = !empty($canWriteNotice);
if ($isGuestAuthorForm) {
    $fileUploadEnabled = false;
    $imageUploadEnabled = false;
}
$secretPostsEnabled = !empty($secretPostsEnabled);
$communityEditorKey = !$isGuestAuthorForm && $pdo instanceof PDO ? sr_community_effective_post_editor($pdo, $board, $settings) : 'textarea';
$ckeditorEnabled = $communityEditorKey === 'ckeditor';
$communityEditorToolbarPreset = $pdo instanceof PDO ? sr_community_post_toolbar_preset($pdo, $settings) : 'community_post_basic';
$editorPostId = isset($postIdField) && is_int($postIdField) ? $postIdField : 0;
$communityEditorAttributes = !$isGuestAuthorForm && $pdo instanceof PDO ? sr_editor_textarea_attributes($pdo, $communityEditorKey, $communityEditorToolbarPreset) : '';
if ($ckeditorEnabled) {
    $communityThemeKey = sr_community_theme_key((string) ($settings['theme_key'] ?? 'basic'));
    $communityEditorAttributes .= ' data-sr-editor-body-theme="community.' . sr_e($communityThemeKey) . '" data-sr-editor-upload-url="' . sr_e(sr_community_body_file_upload_url($board, $editorPostId)) . '" data-sr-editor-upload-field="upload" data-sr-editor-upload-csrf="' . sr_e(sr_csrf_token()) . '" data-sr-editor-upload-token="' . sr_e(sr_community_body_file_upload_token()) . '"';
}
$communityPrivacyConsentDisplayTargets = ['post'];
if (($ckeditorEnabled || (!isset($postIdField) && ($imageUploadEnabled || $fileUploadEnabled)))
    && sr_community_privacy_consent_required_for($pdo, $board, 'attachment_upload')) {
    $communityPrivacyConsentDisplayTargets[] = 'attachment_upload';
}
$communityPrivacyConsentBrowserRequired = sr_community_privacy_consent_required_for($pdo, $board, 'post');
$communityDraftEnabled = !$isGuestAuthorForm && !empty($settings['draft_autosave_enabled']);
$communityDraftMode = isset($postIdField) && is_int($postIdField) ? 'edit' : 'create';
$communityDraftPayload = isset($communityDraftPayload) && is_array($communityDraftPayload) ? $communityDraftPayload : [];
$communityDraftConfig = [
    'enabled' => $communityDraftEnabled,
    'endpoint' => sr_url('/community/draft/autosave'),
    'mode' => $communityDraftMode,
    'account_id' => is_array($account ?? null) ? (int) $account['id'] : 0,
    'board_key' => (string) ($board['board_key'] ?? ''),
    'post_id' => isset($postIdField) && is_int($postIdField) ? $postIdField : 0,
    'interval_seconds' => sr_community_draft_autosave_interval_seconds($settings),
];
$seo = [
    'title' => $pageTitle,
    'canonical' => $formAction,
    'robots' => 'noindex, nofollow',
];
if (sr_module_enabled($pdo, 'banner') && is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (sr_module_enabled($pdo, 'popup_layer') && is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
$communityLayoutContext = sr_community_public_layout_context($communityLayoutSettings, [
    'consumer_target' => 'community.form',
    'stylesheets' => array_merge(sr_community_skin_stylesheets($skinKey ?? 'basic'), sr_enabled_module_asset_paths($pdo ?? null, [
        'banner' => '/modules/banner/assets/module.css',
        'popup_layer' => '/modules/popup_layer/assets/module.css',
    ])),
    'output_slots' => [
        ['module_key' => 'community', 'point_key' => 'community.post.form', 'slot_key' => 'before_form'],
        ['module_key' => 'community', 'point_key' => 'community.post.form', 'slot_key' => 'after_form'],
    ],
]);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, $communityLayoutContext);
$communityMainLabel = $pageTitle;
$communityFrameModifier = 'form';
?>
    <?php include SR_ROOT . '/modules/community/theme/basic/home-frame-start.php'; ?>
        <?php if (function_exists('sr_popup_layer_render_public_layer') && sr_module_enabled($pdo, 'popup_layer')) { ?>
            <?php echo sr_popup_layer_render_public_layer($pdo, (int) ($board['popup_layer_form_id'] ?? 0)); ?>
        <?php } ?>

        <p>
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

        <?php echo sr_public_feedback_toasts('community', '', $errors); ?>

        <?php if ($communityDraftEnabled && $communityDraftPayload !== []) { ?>
            <div class="alert alert-info alert-removable" role="status" data-community-draft-panel>
                <p data-community-draft-message>
                    저장된 임시글이 있습니다.<?php echo !empty($communityDraftPayload['conflict']) ? ' 원글 내용이 바뀌어 덮어쓰기 전에 확인이 필요합니다.' : ''; ?><?php echo (int) ($communityDraftPayload['body_tmp_refs_removed'] ?? 0) > 0 ? ' 세션이 바뀐 임시 이미지는 복원에서 제외됩니다.' : ''; ?>
                </p>
                <div class="admin-row-actions">
                    <button type="button" class="btn btn-sm btn-solid-primary" data-community-draft-restore>복원</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-community-draft-discard>삭제</button>
                </div>
            </div>
        <?php } ?>

        <form method="post" action="<?php echo sr_e(sr_url($formAction)); ?>"<?php echo $imageUploadEnabled || $fileUploadEnabled ? ' enctype="multipart/form-data"' : ''; ?><?php echo $communityDraftEnabled ? ' data-community-draft-form' : ''; ?>>
            <?php echo sr_csrf_field(); ?>
            <?php if ($communityDraftEnabled) { ?>
                <input type="hidden" name="draft_mode" value="<?php echo sr_e($communityDraftMode); ?>">
                <input type="hidden" name="board_key" value="<?php echo sr_e((string) ($board['board_key'] ?? '')); ?>">
                <script type="application/json" data-community-draft-config><?php echo sr_js_json_encode($communityDraftConfig); ?></script>
                <script type="application/json" data-community-draft-payload><?php echo sr_js_json_encode($communityDraftPayload); ?></script>
            <?php } ?>
            <?php if (isset($postIdField) && is_int($postIdField)) { ?>
                <input type="hidden" name="post_id" value="<?php echo sr_e((string) $postIdField); ?>">
            <?php } ?>
            <?php if ($showGuestAuthorFields) { ?>
                <p>
                    <label for="modules_community_form_guest_author_name">
                        <span><?php echo sr_e('작성자명'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                        <input id="modules_community_form_guest_author_name" type="text" name="guest_author_name" maxlength="120" value="<?php echo sr_e((string) ($values['guest_author_name'] ?? '')); ?>" required class="form-input">
                    </label>
                </p>
                <p>
                    <label for="modules_community_form_guest_password">
                        <span><?php echo sr_e('수정/삭제 비밀번호'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                        <input id="modules_community_form_guest_password" type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="new-password" required class="form-input">
                    </label>
                    <br>
                    <small><?php echo sr_e('비회원 글 수정과 삭제에 사용됩니다.'); ?></small>
                </p>
            <?php } ?>
            <?php if (isset($categories) && is_array($categories) && $categories !== []) { ?>
                <p>
                    <label for="modules_community_form_category_id">
                        <span><?php echo sr_e('카테고리'); ?><?php echo !empty($categoryRequired) ? ' <span class="sr-required-label">' . sr_e(sr_t('community::ui.required.1f227c67')) . '</span>' : ''; ?></span>
                        <select id="modules_community_form_category_id" name="category_id" class="form-select"<?php echo !empty($categoryRequired) ? ' required' : ''; ?>>
                            <option value=""><?php echo sr_e('선택 안 함'); ?></option>
                            <?php foreach ($categories as $category) { ?>
                                <option value="<?php echo sr_e((string) $category['id']); ?>"<?php echo (int) ($values['category_id'] ?? 0) === (int) $category['id'] ? ' selected' : ''; ?>>
                                    <?php echo sr_e((string) $category['title']); ?><?php echo (string) ($category['status'] ?? '') !== 'enabled' ? sr_e(' (비활성)') : ''; ?>
                                </option>
                            <?php } ?>
                        </select>
                    </label>
                </p>
            <?php } ?>
            <p>
                <label for="modules_community_form_title">
                    <span><?php echo sr_e(sr_t('community::ui.text.08b17e43')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                    <input id="modules_community_form_title" type="text" name="title" maxlength="160" value="<?php echo sr_e(is_string($values['title']) ? $values['title'] : ''); ?>" required class="form-input">
                </label>
            </p>
            <p>
                <label for="modules_community_form_body_text">
                    <span><?php echo sr_e(sr_t('community::ui.text.9118bb57')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                    <textarea id="modules_community_form_body_text" name="body_text" rows="12" cols="80"<?php echo $ckeditorEnabled ? '' : ' required'; ?> class="form-textarea"<?php echo $communityEditorAttributes; ?>><?php echo sr_e(is_string($values['body_text']) ? $values['body_text'] : ''); ?></textarea>
                </label>
            </p>
            <?php echo sr_community_extra_fields_form_html(is_array($extraFieldDefinitions ?? null) ? $extraFieldDefinitions : [], is_array($extraFieldValues ?? null) ? $extraFieldValues : []); ?>
            <?php if ($secretPostsEnabled) { ?>
                <label class="community-post-secret-toggle">
                    <input type="checkbox" name="is_secret" value="1" class="form-checkbox"<?php echo (int) ($values['is_secret'] ?? 0) === 1 ? ' checked' : ''; ?>>
                    <span><?php echo sr_e('비밀글'); ?></span>
                </label>
            <?php } ?>
            <?php if ($canWriteNotice) { ?>
                <label class="community-post-notice-toggle">
                    <input type="checkbox" name="is_notice" value="1" class="form-checkbox"<?php echo (int) ($values['is_notice'] ?? 0) === 1 ? ' checked' : ''; ?>>
                    <span><?php echo sr_e('공지사항'); ?></span>
                </label>
            <?php } ?>
            <?php if (!$isGuestAuthorForm && !empty($seriesEnabled)) { ?>
            <fieldset>
                <legend><?php echo sr_e('시리즈'); ?></legend>
                <p>
                    <label>
                        <input type="radio" name="series_mode" value="none" class="form-radio"<?php echo (string) ($seriesValues['series_mode'] ?? 'none') === 'none' ? ' checked' : ''; ?>>
                        <?php echo sr_e('연결 안 함'); ?>
                    </label>
                    <?php if (isset($seriesOptions) && is_array($seriesOptions) && $seriesOptions !== []) { ?>
                        <label>
                            <input type="radio" name="series_mode" value="existing" class="form-radio"<?php echo (string) ($seriesValues['series_mode'] ?? '') === 'existing' ? ' checked' : ''; ?>>
                            <?php echo sr_e('기존 시리즈'); ?>
                        </label>
                    <?php } ?>
                    <label>
                        <input type="radio" name="series_mode" value="new" class="form-radio"<?php echo (string) ($seriesValues['series_mode'] ?? '') === 'new' ? ' checked' : ''; ?>>
                        <?php echo sr_e('새 시리즈'); ?>
                    </label>
                </p>
                <?php if (isset($seriesOptions) && is_array($seriesOptions) && $seriesOptions !== []) { ?>
                    <p>
                        <label for="modules_community_form_series_id">
                            <span><?php echo sr_e('기존 시리즈'); ?></span>
                            <select id="modules_community_form_series_id" name="series_id" class="form-select">
                                <option value="0"><?php echo sr_e('선택'); ?></option>
                                <?php foreach ($seriesOptions as $seriesOption) { ?>
                                    <option value="<?php echo sr_e((string) $seriesOption['id']); ?>"<?php echo (int) ($seriesValues['series_id'] ?? 0) === (int) $seriesOption['id'] ? ' selected' : ''; ?>>
                                        <?php echo sr_e((string) $seriesOption['title']); ?> / <?php echo sr_e(sr_community_series_visibility_label((string) $seriesOption['visibility'])); ?> / <?php echo sr_e(sr_community_series_status_label((string) $seriesOption['status'])); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </label>
                    </p>
                <?php } ?>
                <p>
                    <label for="modules_community_form_new_series_title">
                        <span><?php echo sr_e('새 시리즈 제목'); ?></span>
                        <input id="modules_community_form_new_series_title" type="text" name="new_series_title" maxlength="160" value="<?php echo sr_e((string) ($seriesValues['new_series_title'] ?? '')); ?>" class="form-input">
                    </label>
                </p>
                <p>
                    <label for="modules_community_form_series_episode_label">
                        <span><?php echo sr_e('회차 표시'); ?></span>
                        <input id="modules_community_form_series_episode_label" type="text" name="series_episode_label" maxlength="80" value="<?php echo sr_e((string) ($seriesValues['episode_label'] ?? '')); ?>" class="form-input">
                    </label>
                    <br>
                    <small><?php echo sr_e('예: 1화, 프롤로그, 후기'); ?></small>
                </p>
                <p>
                    <label for="modules_community_form_series_sort_order">
                        <span><?php echo sr_e('정렬 순서'); ?></span>
                        <input id="modules_community_form_series_sort_order" type="number" name="series_sort_order" min="0" max="1000000" value="<?php echo sr_e((string) (int) ($seriesValues['sort_order'] ?? 0)); ?>" class="form-input">
                    </label>
                </p>
            </fieldset>
            <?php } ?>
            <?php if ($imageUploadEnabled) { ?>
                <p>
                    <label for="modules_community_form_image_attachment">
                    <span><?php echo sr_e(sr_t('community::ui.text.42bb44a5')); ?></span>
                        <input id="modules_community_form_image_attachment" type="file" name="image_attachment" accept="image/jpeg,image/png,image/webp" class="form-input">
                    </label>
                    <br>
                    <small><?php echo sr_e(sr_t('community::ui.jpeg.png.webp.eefc7fda')); ?> <?php echo sr_e(sr_community_format_bytes($attachmentMaxBytes)); ?></small>
                </p>
            <?php } ?>
            <?php if ($fileUploadEnabled) { ?>
                <p>
                    <label for="modules_community_form_file_attachments">
                    <span><?php echo sr_e(sr_t('community::ui.text.1fe3755c')); ?></span>
                        <input id="modules_community_form_file_attachments" type="file" name="file_attachments[]" multiple class="form-input">
                    </label>
                    <br>
                    <small>
                        <?php echo sr_e(sr_t('community::ui.text.ee3b70e7')); ?> <?php echo sr_e((string) $fileAttachmentMaxCount); ?><?php echo sr_e(sr_t('community::ui.text.2254e4c9')); ?> <?php echo sr_e(sr_community_format_bytes($fileAttachmentMaxBytes)); ?> <?php echo sr_e(sr_t('community::ui.text.3cf0ac82')); ?> <?php echo sr_e(implode(', ', $fileAllowedExtensions)); ?>
                    </small>
                </p>
            <?php } ?>
            <?php echo sr_community_privacy_consent_field_html($pdo, $board, $communityPrivacyConsentDisplayTargets, $communityPrivacyConsentBrowserRequired, isset($postIdField) ? 'post_edit' : 'post_write'); ?>
            <?php if (!isset($postIdField) && function_exists('sr_antispam_challenge_render')) { ?>
                <?php echo sr_antispam_challenge_render($pdo, 'community.post.guest', 'community_post_' . (string) (int) $board['id'], $antispamPostContext ?? ['account' => null]); ?>
            <?php } ?>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e($submitLabel); ?></button>
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
        <?php if ($ckeditorEnabled && function_exists('sr_ckeditor_public_assets_html')) { ?>
            <?php echo sr_ckeditor_public_assets_html($pdo, $communityEditorToolbarPreset); ?>
        <?php } ?>
    <?php include SR_ROOT . '/modules/community/theme/basic/home-frame-end.php'; ?>
<?php sr_public_layout_end(); ?>
