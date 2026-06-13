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
if ($isGuestAuthorForm) {
    $fileUploadEnabled = false;
    $imageUploadEnabled = false;
}
$secretPostsEnabled = !empty($secretPostsEnabled);
$ckeditorEnabled = !$isGuestAuthorForm && $pdo instanceof PDO && sr_community_html_post_body_enabled($pdo, $board, $settings);
$communityEditorToolbarPreset = $pdo instanceof PDO ? sr_community_post_toolbar_preset($pdo, $settings) : 'community_post_basic';
$editorPostId = isset($postIdField) && is_int($postIdField) ? $postIdField : 0;
$communityEditorAttributes = $ckeditorEnabled ? ' data-sr-editor="ckeditor" data-sr-editor-preset="' . sr_e($communityEditorToolbarPreset) . '" data-sr-editor-upload-url="' . sr_e(sr_community_body_file_upload_url($board, $editorPostId)) . '" data-sr-editor-upload-field="upload" data-sr-editor-upload-csrf="' . sr_e(sr_csrf_token()) . '" data-sr-editor-upload-token="' . sr_e(sr_community_body_file_upload_token()) . '"' : '';
$communityPrivacyConsentDisplayTargets = ['post'];
if (($ckeditorEnabled || (!isset($postIdField) && ($imageUploadEnabled || $fileUploadEnabled)))
    && sr_community_privacy_consent_required_for($pdo, $board, 'attachment_upload')) {
    $communityPrivacyConsentDisplayTargets[] = 'attachment_upload';
}
$communityPrivacyConsentBrowserRequired = sr_community_privacy_consent_required_for($pdo, $board, 'post');
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
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings, [
    'stylesheets' => array_merge(sr_community_skin_stylesheets($skinKey ?? 'basic'), [
        '/modules/banner/assets/public.css',
        '/modules/popup_layer/assets/public.css',
    ]),
]));
?>
    <main class="community-screen">
        <?php if (function_exists('sr_popup_layer_render_public_layer') && sr_module_enabled($pdo, 'popup_layer')) { ?>
            <?php echo sr_popup_layer_render_public_layer($pdo, (int) ($board['popup_layer_form_id'] ?? 0)); ?>
        <?php } ?>

        <p>
            <a href="<?php echo sr_e(sr_url('/community')); ?>"><?php echo sr_e(sr_t('community::ui.community.4a285775')); ?></a>
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
            <?php if ($showGuestAuthorFields) { ?>
                <p>
                    <label for="modules_community_form_guest_author_name">
                        <span><?php echo sr_e('작성자명'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                        <input id="modules_community_form_guest_author_name" type="text" name="guest_author_name" maxlength="120" value="<?php echo sr_e((string) ($values['guest_author_name'] ?? '')); ?>" required>
                    </label>
                </p>
                <p>
                    <label for="modules_community_form_guest_password">
                        <span><?php echo sr_e('수정/삭제 비밀번호'); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                        <input id="modules_community_form_guest_password" type="password" name="guest_password" minlength="8" maxlength="255" autocomplete="new-password" required>
                    </label>
                    <br>
                    <small><?php echo sr_e('비회원 글 수정과 삭제에 사용됩니다.'); ?></small>
                </p>
            <?php } ?>
            <?php if (isset($categories) && is_array($categories) && $categories !== []) { ?>
                <p>
                    <label for="modules_community_form_category_id">
                        <span><?php echo sr_e('카테고리'); ?><?php echo !empty($categoryRequired) ? ' <span class="sr-required-label">' . sr_e(sr_t('community::ui.required.1f227c67')) . '</span>' : ''; ?></span>
                        <select id="modules_community_form_category_id" name="category_id"<?php echo !empty($categoryRequired) ? ' required' : ''; ?>>
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
                    <input id="modules_community_form_title" type="text" name="title" maxlength="160" value="<?php echo sr_e(is_string($values['title']) ? $values['title'] : ''); ?>" required>
                </label>
            </p>
            <p>
                <label for="modules_community_form_body_text">
                    <span><?php echo sr_e(sr_t('community::ui.text.9118bb57')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                    <textarea id="modules_community_form_body_text" name="body_text" rows="12" cols="80" required<?php echo $communityEditorAttributes; ?>><?php echo sr_e(is_string($values['body_text']) ? $values['body_text'] : ''); ?></textarea>
                </label>
            </p>
            <?php if ($secretPostsEnabled) { ?>
                <label class="community-post-secret-toggle">
                    <input type="checkbox" name="is_secret" value="1"<?php echo (int) ($values['is_secret'] ?? 0) === 1 ? ' checked' : ''; ?>>
                    <span><?php echo sr_e('비밀글'); ?></span>
                </label>
            <?php } ?>
            <?php if (sr_module_enabled($pdo, 'content') || sr_module_enabled($pdo, 'quiz') || sr_module_enabled($pdo, 'survey')) { ?>
                <div class="sr-link-card-picker" data-link-card-picker data-endpoint="<?php echo sr_e(sr_url('/community/link-card-targets')); ?>" data-target="content,quiz_set,survey_form" data-textarea="modules_community_form_body_text">
                    <div class="sr-link-card-picker-controls">
                        <input type="search" class="form-input" data-link-card-search placeholder="<?php echo sr_e('콘텐츠, 퀴즈, 설문 제목/관리용 키/ID 검색'); ?>">
                        <button type="button" class="btn btn-solid-light" data-link-card-search-trigger><?php echo sr_e('검색'); ?></button>
                        <button type="button" class="btn btn-solid-primary" data-link-card-insert><?php echo sr_e('본문에 삽입'); ?></button>
                    </div>
                    <div class="sr-link-card-picker-results" data-link-card-results><?php echo sr_e('콘텐츠, 퀴즈, 설문을 검색해 본문에 임베드 참조로 삽입합니다.'); ?></div>
                </div>
            <?php } ?>
            <?php if (!$isGuestAuthorForm) { ?>
            <fieldset>
                <legend><?php echo sr_e('시리즈'); ?></legend>
                <p>
                    <label>
                        <input type="radio" name="series_mode" value="none"<?php echo (string) ($seriesValues['series_mode'] ?? 'none') === 'none' ? ' checked' : ''; ?>>
                        <?php echo sr_e('연결 안 함'); ?>
                    </label>
                    <?php if (isset($seriesOptions) && is_array($seriesOptions) && $seriesOptions !== []) { ?>
                        <label>
                            <input type="radio" name="series_mode" value="existing"<?php echo (string) ($seriesValues['series_mode'] ?? '') === 'existing' ? ' checked' : ''; ?>>
                            <?php echo sr_e('기존 시리즈'); ?>
                        </label>
                    <?php } ?>
                    <label>
                        <input type="radio" name="series_mode" value="new"<?php echo (string) ($seriesValues['series_mode'] ?? '') === 'new' ? ' checked' : ''; ?>>
                        <?php echo sr_e('새 시리즈'); ?>
                    </label>
                </p>
                <?php if (isset($seriesOptions) && is_array($seriesOptions) && $seriesOptions !== []) { ?>
                    <p>
                        <label for="modules_community_form_series_id">
                            <span><?php echo sr_e('기존 시리즈'); ?></span>
                            <select id="modules_community_form_series_id" name="series_id">
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
                        <input id="modules_community_form_new_series_title" type="text" name="new_series_title" maxlength="160" value="<?php echo sr_e((string) ($seriesValues['new_series_title'] ?? '')); ?>">
                    </label>
                </p>
                <p>
                    <label for="modules_community_form_series_episode_label">
                        <span><?php echo sr_e('회차 표시'); ?></span>
                        <input id="modules_community_form_series_episode_label" type="text" name="series_episode_label" maxlength="80" value="<?php echo sr_e((string) ($seriesValues['episode_label'] ?? '')); ?>">
                    </label>
                    <br>
                    <small><?php echo sr_e('예: 1화, 프롤로그, 후기'); ?></small>
                </p>
                <p>
                    <label for="modules_community_form_series_sort_order">
                        <span><?php echo sr_e('정렬 순서'); ?></span>
                        <input id="modules_community_form_series_sort_order" type="number" name="series_sort_order" min="0" max="1000000" value="<?php echo sr_e((string) (int) ($seriesValues['sort_order'] ?? 0)); ?>">
                    </label>
                </p>
            </fieldset>
            <?php } ?>
            <?php if ($imageUploadEnabled) { ?>
                <p>
                    <label for="modules_community_form_image_attachment">
                    <span><?php echo sr_e(sr_t('community::ui.text.42bb44a5')); ?></span>
                        <input id="modules_community_form_image_attachment" type="file" name="image_attachment" accept="image/jpeg,image/png,image/webp">
                    </label>
                    <br>
                    <small><?php echo sr_e(sr_t('community::ui.jpeg.png.webp.eefc7fda')); ?> <?php echo sr_e(sr_community_format_bytes($attachmentMaxBytes)); ?></small>
                </p>
            <?php } ?>
            <?php if ($fileUploadEnabled) { ?>
                <p>
                    <label for="modules_community_form_file_attachments">
                    <span><?php echo sr_e(sr_t('community::ui.text.1fe3755c')); ?></span>
                        <input id="modules_community_form_file_attachments" type="file" name="file_attachments[]" multiple>
                    </label>
                    <br>
                    <small>
                        <?php echo sr_e(sr_t('community::ui.text.ee3b70e7')); ?> <?php echo sr_e((string) $fileAttachmentMaxCount); ?><?php echo sr_e(sr_t('community::ui.text.2254e4c9')); ?> <?php echo sr_e(sr_community_format_bytes($fileAttachmentMaxBytes)); ?> <?php echo sr_e(sr_t('community::ui.text.3cf0ac82')); ?> <?php echo sr_e(implode(', ', $fileAllowedExtensions)); ?>
                    </small>
                </p>
            <?php } ?>
            <?php echo sr_community_privacy_consent_field_html($pdo, $board, $communityPrivacyConsentDisplayTargets, $communityPrivacyConsentBrowserRequired, isset($postIdField) ? 'post_edit' : 'post_write'); ?>
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
        <?php if ($ckeditorEnabled && function_exists('sr_ckeditor_public_assets_html')) { ?>
            <?php echo sr_ckeditor_public_assets_html($pdo, $communityEditorToolbarPreset); ?>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
