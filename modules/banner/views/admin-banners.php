<?php

$bannerAdminPage = isset($bannerAdminPage) ? (string) $bannerAdminPage : 'list';
$editing = is_array($editBanner);
$adminPageTitle = $bannerAdminPage === 'form' ? ($editing ? sr_t('banner::ui.banner.edit.52756afa') : sr_t('banner::ui.banner.b0dbbde9')) : sr_t('banner::ui.banner.list.f989d740');
$adminPageSubtitle = $bannerAdminPage === 'form' ? sr_t('banner::ui.banner.71184934') : sr_t('banner::ui.banner.status.search.ae378c83');
$adminContainerClass = $bannerAdminPage === 'form' ? 'admin-page-banner-form admin-ui-scope' : 'admin-page-banner-list admin-ui-scope';
$filters = isset($filters) && is_array($filters) ? $filters : ['status' => '', 'target' => '', 'field' => 'all', 'q' => ''];
$adminPageTitleUrl = sr_admin_page_title_reset_url($bannerAdminPage !== 'form', '/admin/banners');
$bannerSortOptions = isset($bannerSortOptions) && is_array($bannerSortOptions) ? $bannerSortOptions : [
    'title' => ['columns' => ['b.title', 'b.id']],
    'status' => ['columns' => ['b.status', 'b.id']],
    'skin_key' => ['columns' => ['b.skin_key', 'b.id']],
    'click_count' => ['columns' => ['b.click_count', 'b.id']],
    'target' => ['columns' => ['t.module_key', 't.point_key', 't.slot_key', 't.match_type', 't.subject_id', 'b.id']],
    'starts_at' => ['columns' => ['b.starts_at', 'b.id']],
    'ends_at' => ['columns' => ['b.ends_at', 'b.id']],
    'sort_order' => ['columns' => ['b.sort_order', 'b.id']],
];
$bannerDefaultSort = isset($bannerDefaultSort) && is_array($bannerDefaultSort) ? $bannerDefaultSort : sr_admin_sort_default('sort_order', 'asc');
$bannerSort = isset($bannerSort) && is_array($bannerSort) ? $bannerSort : $bannerDefaultSort;
$bannerStatusCounts = isset($bannerStatusCounts) && is_array($bannerStatusCounts) ? $bannerStatusCounts : [];
$totalBanners = (int) ($bannerStatusCounts['total'] ?? count($banners ?? []));
$selectedTargetOption = isset($bannerDefaultTargetOption) ? (string) $bannerDefaultTargetOption : sr_banner_public_target_option_value();
if ($editing && (string) ($editBanner['module_key'] ?? '') !== '') {
    $selectedTargetOption = (string) ($editBanner['module_key'] ?? '') . '|' . (string) ($editBanner['point_key'] ?? '') . '|' . (string) ($editBanner['slot_key'] ?? '');
} elseif ($editing) {
    $selectedTargetOption = sr_banner_public_target_option_value();
}
$currentMatchType = $editing ? (string) ($editBanner['match_type'] ?? 'all') : 'all';
if (!in_array($currentMatchType, ['all', 'exact'], true)) {
    $currentMatchType = 'all';
}
$bannerSubjectTargetTypeMap = sr_banner_subject_target_type_map($pdo, $availableTargets);
$bannerSubjectSearchTypes = sr_banner_subject_search_types($pdo, $availableTargets);
$currentSubjectTargetType = (string) ($bannerSubjectTargetTypeMap[$selectedTargetOption] ?? '');
$bannerSubjectSearchEnabled = $currentSubjectTargetType !== '';
$bannerUseImage = $editing && sr_banner_clean_image_url((string) ($editBanner['image_url'] ?? '')) !== '';
if (sr_banner_is_public_target_option($selectedTargetOption) || $currentSubjectTargetType === '') {
    $currentMatchType = 'all';
}
$bannerTargetServiceOptions = sr_banner_target_service_options($availableTargets, true);
$selectedTargetServiceKey = sr_banner_selected_target_service_key($selectedTargetOption);
if ($selectedTargetServiceKey === '') {
    $selectedTargetServiceKey = sr_banner_public_target_option_value();
}
$subjectScopeVisible = $currentSubjectTargetType !== '' && !sr_banner_is_public_target_option($selectedTargetOption);
$subjectRequired = $subjectScopeVisible && $currentMatchType === 'exact';
$bannerSubjectLookupModalId = 'banner-subject-lookup-modal';
$bannerSubjectLookupResultsId = 'banner-subject-lookup-results';
$bannerHelpOpenLabel = sr_t('banner::help.open');
$bannerHelpButtonHtml = static function (string $label, string $modalId) use ($bannerHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $bannerHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$bannerHelpBodyHtml = static function (array $keys): string {
    $html = '';
    foreach ($keys as $key) {
        $html .= '<p>' . sr_e(sr_t((string) $key)) . '</p>';
    }

    return $html;
};
$bannerCopyModals = '';
$bannerReferenceModals = '';
$bannerCopyModalHtml = static function (array $banner, string $returnTo): string {
    $bannerId = (int) ($banner['id'] ?? 0);
    if ($bannerId < 1) {
        return '';
    }
    $modalId = 'banner-copy-modal-' . (string) $bannerId;
    $title = sr_banner_clean_single_line((string) ($banner['title'] ?? '') . ' 복사본', 120);
    ob_start();
    ?>
    <div id="<?php echo sr_e($modalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($modalId); ?>-label" aria-hidden="true" inert>
        <div class="modal-dialog">
            <div class="modal-content ui-form-theme">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/banners/copy')); ?>" data-sr-validate-form>
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($modalId); ?>-label" class="modal-title"><?php echo sr_e('배너 복사'); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($modalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="banner_id" value="<?php echo sr_e((string) $bannerId); ?>">
                        <input type="hidden" name="return_to" value="<?php echo sr_e($returnTo); ?>">
                        <p class="admin-form-help"><?php echo sr_e((string) ($banner['title'] ?? '')); ?></p>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($modalId); ?>-title"><?php echo sr_e('새 제목'); ?> <span class="sr-required-label"><?php echo sr_e('(필수)'); ?></span></label>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($modalId); ?>-title" type="text" name="title" value="<?php echo sr_e($title); ?>" class="form-input form-control-full" maxlength="120" required data-validation-message="새 제목을 입력해 주세요." data-overlay-focus>
                                <p class="admin-form-help"><?php echo sr_e('복사본은 draft로 저장됩니다.'); ?></p>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($modalId); ?>-copy-click-count"><?php echo sr_e('클릭 수'); ?></label>
                            <div class="admin-form-field">
                                <?php echo sr_admin_checkbox_toggle_html($modalId . '-copy-click-count', 'copy_click_count', '1', false, '집계 클릭 수만 복사'); ?>
                                <p class="admin-form-help"><?php echo sr_e('선택하지 않으면 복사본의 클릭 수는 0으로 시작합니다.'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>"><?php echo sr_e('취소'); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e('복사본 만들기'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
};
$bannerHelp = [
    'link_url' => [
        'id' => 'banner_admin_help_link_url',
        'title' => sr_t('banner::help.link_url.title'),
        'body' => $bannerHelpBodyHtml(['banner::help.link_url.body.1', 'banner::help.link_url.body.2']),
    ],
    'image_url' => [
        'id' => 'banner_admin_help_image_url',
        'title' => sr_t('banner::help.image_url.title'),
        'body' => $bannerHelpBodyHtml(['banner::help.image_url.body.1', 'banner::help.image_url.body.2']),
    ],
    'image_upload' => [
        'id' => 'banner_admin_help_image_upload',
        'title' => sr_t('banner::help.image_upload.title'),
        'body' => $bannerHelpBodyHtml(['banner::help.image_upload.body.1', 'banner::help.image_upload.body.2']),
    ],
    'target_option' => [
        'id' => 'banner_admin_help_target_option',
        'title' => sr_t('banner::help.target_option.title'),
        'body' => $bannerHelpBodyHtml(['banner::help.target_option.body.1', 'banner::help.target_option.body.2']),
    ],
    'subject_target' => [
        'id' => 'banner_admin_help_subject_target',
        'title' => sr_t('banner::help.subject_target.title'),
        'body' => $bannerHelpBodyHtml(['banner::help.subject_target.body.1', 'banner::help.subject_target.body.2']),
    ],
    'status' => [
        'id' => 'banner_admin_help_status',
        'title' => sr_t('banner::help.status.title'),
        'body' => $bannerHelpBodyHtml(['banner::help.status.body.1', 'banner::help.status.body.2', 'banner::help.status.body.3']),
    ],
    'skin_key' => [
        'id' => 'banner_admin_help_skin_key',
        'title' => sr_t('banner::help.skin_key.title'),
        'body' => $bannerHelpBodyHtml(['banner::help.skin_key.body.1', 'banner::help.skin_key.body.2']),
    ],
    'starts_at' => [
        'id' => 'banner_admin_help_starts_at',
        'title' => sr_t('banner::help.starts_at.title'),
        'body' => $bannerHelpBodyHtml(['banner::help.starts_at.body.1', 'banner::help.starts_at.body.2']),
    ],
    'ends_at' => [
        'id' => 'banner_admin_help_ends_at',
        'title' => sr_t('banner::help.ends_at.title'),
        'body' => $bannerHelpBodyHtml(['banner::help.ends_at.body.1', 'banner::help.ends_at.body.2']),
    ],
    'sort_order' => [
        'id' => 'banner_admin_help_sort_order',
        'title' => sr_t('banner::help.sort_order.title'),
        'body' => $bannerHelpBodyHtml(['banner::help.sort_order.body.1', 'banner::help.sort_order.body.2']),
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($bannerAdminPage === 'form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/banners/save')); ?>" enctype="multipart/form-data" class="admin-form ui-form-theme" data-admin-subject-form data-sr-validate-form data-public-target-value="<?php echo sr_e(sr_banner_public_target_option_value()); ?>">
        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('banner::ui.section.basic')); ?></h2>
            <p><?php echo sr_e(sr_t('banner::ui.banner.banner.select.banner.active.40c015cc')); ?></p>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="banner_id" value="<?php echo $editing ? sr_e((string) $editBanner['id']) : '0'; ?>">
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_title"><?php echo sr_e(sr_t('banner::ui.text.08b17e43')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('banner::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_title" type="text" name="title" value="<?php echo $editing ? sr_e((string) $editBanner['title']) : ''; ?>" class="form-input form-control-full" maxlength="120" required data-validation-message="배너 제목을 입력해 주세요.">
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('banner_admin_banners_link_url', sr_t('banner::ui.url.http.https.81cff7be'), $bannerHelp['link_url']['id'], $bannerHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_link_url" type="text" name="link_url" value="<?php echo $editing ? sr_e((string) $editBanner['link_url']) : ''; ?>" class="form-input form-control-full" maxlength="255">
                    <p class="admin-form-help"><?php echo sr_e(sr_t('banner::ui.url.help.6f5481db')); ?></p>
                </div>
            </div>
        </section>
        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('banner::ui.section.image_text')); ?></h2>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_use_image"><?php echo sr_e(sr_t('banner::ui.use_image')); ?></label>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label">
                        <input id="banner_admin_banners_use_image" type="checkbox" name="use_image" value="1" class="form-switch form-choice-dark" data-admin-banner-use-image<?php echo $bannerUseImage ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html(sr_t('banner::ui.use_image.choice')); ?>
                    </label>
                    <p class="admin-form-help"><?php echo sr_e(sr_t('banner::ui.use_image.help')); ?></p>
                </div>
            </div>
            <div class="admin-form-row" data-admin-banner-image-row<?php echo $bannerUseImage ? '' : ' hidden'; ?>>
                <?php echo sr_admin_form_label_help_html('banner_admin_banners_image_url', sr_t('banner::ui.url.http.https.url.264bd3d3'), $bannerHelp['image_url']['id'], $bannerHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_image_url" type="text" name="image_url" value="<?php echo $editing ? sr_e((string) $editBanner['image_url']) : ''; ?>" class="form-input form-control-full" maxlength="255" data-admin-banner-image-input<?php echo $bannerUseImage ? '' : ' disabled'; ?>>
                    <p class="admin-form-help"><?php echo sr_e(sr_t('banner::ui.url.help.e0a0162e')); ?></p>
                </div>
            </div>
            <div class="admin-form-row" data-admin-banner-image-row<?php echo $bannerUseImage ? '' : ' hidden'; ?>>
                <?php echo sr_admin_form_label_help_html('banner_admin_banners_image_upload', sr_t('banner::ui.text.cead00a8'), $bannerHelp['image_upload']['id'], $bannerHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_image_upload" type="file" name="image_upload" accept="image/jpeg,image/png,image/webp" class="form-input" data-admin-banner-image-input<?php echo $bannerUseImage ? '' : ' disabled'; ?>>
                    <p class="admin-form-help"><?php echo sr_e(sr_t('banner::ui.image_upload.help.inline')); ?> <?php echo sr_e(sr_banner_format_bytes(sr_banner_image_upload_max_bytes())); ?></p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_body_text"><span data-admin-banner-text-label><?php echo sr_e($bannerUseImage ? sr_t('banner::ui.alt_text') : sr_t('banner::ui.display_text')); ?></span> <span class="sr-required-label"><?php echo sr_e(sr_t('banner::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <textarea id="banner_admin_banners_body_text" name="body_text" maxlength="3000" class="form-textarea" required data-validation-message="배너 문구를 입력해 주세요." data-admin-banner-text-input><?php echo $editing ? sr_e((string) $editBanner['body_text']) : ''; ?></textarea>
                    <p class="admin-form-help" data-admin-banner-text-help><?php echo sr_e($bannerUseImage ? sr_t('banner::ui.alt_text.help') : sr_t('banner::ui.display_text.help')); ?></p>
                </div>
            </div>
        </section>
        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('banner::ui.section.exposure')); ?></h2>
            <input type="hidden" name="target_option" value="<?php echo sr_e($selectedTargetOption); ?>" data-admin-target-option>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('banner_admin_banners_target_service_key', '서비스', $bannerHelp['target_option']['id'], $bannerHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <select id="banner_admin_banners_target_service_key" name="target_service_key" class="form-select" required data-validation-message="서비스를 선택해 주세요." data-admin-target-service>
                        <?php foreach ($bannerTargetServiceOptions as $serviceKey => $serviceLabel) { ?>
                            <option value="<?php echo sr_e((string) $serviceKey); ?>"<?php echo $selectedTargetServiceKey === (string) $serviceKey ? ' selected' : ''; ?>><?php echo sr_e((string) $serviceLabel); ?></option>
                        <?php } ?>
                    </select>
                    <p class="admin-form-help"><?php echo sr_e('공용은 다른 화면에서 직접 선택하는 배너이고, 서비스 선택 시 상세 노출 위치를 고릅니다.'); ?></p>
                </div>
            </div>
            <div class="admin-form-row" data-admin-target-detail-row<?php echo sr_banner_is_public_target_option($selectedTargetOption) ? ' hidden' : ''; ?>>
                <label class="form-label" for="banner_admin_banners_target_detail_option"><?php echo sr_e('상세'); ?> <span class="sr-required-label" data-admin-target-detail-required<?php echo sr_banner_is_public_target_option($selectedTargetOption) ? ' hidden' : ''; ?>><?php echo sr_e(sr_t('banner::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <select id="banner_admin_banners_target_detail_option" name="target_detail_option" class="form-select" data-admin-target-detail data-validation-message="상세 노출 위치를 선택해 주세요."<?php echo sr_banner_is_public_target_option($selectedTargetOption) ? ' disabled' : ' required'; ?>>
                        <?php foreach ($availableTargets as $target) { ?>
                            <?php $optionValue = sr_banner_target_option_value($target); ?>
                            <option value="<?php echo sr_e($optionValue); ?>" data-service="<?php echo sr_e(sr_banner_target_service_key($target)); ?>"<?php echo $selectedTargetOption === $optionValue ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_banner_target_admin_label($target)); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <p class="admin-form-help"><?php echo sr_e(sr_t('banner::ui.banner.settings.select.active.88d78049')); ?></p>
                </div>
            </div>
            <div class="admin-form-row" data-admin-subject-scope-row<?php echo $subjectScopeVisible ? '' : ' hidden'; ?>>
                <?php echo sr_admin_form_label_help_html('banner_admin_banners_match_type_all', sr_t('banner::ui.subject_target.scope'), $bannerHelp['subject_target']['id'], $bannerHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label">
                        <input id="banner_admin_banners_match_type_all" type="radio" name="match_type" value="all" class="form-radio" data-admin-subject-scope<?php echo $currentMatchType === 'exact' ? '' : ' checked'; ?>>
                        <?php echo sr_admin_choice_label_html(sr_t('banner::ui.subject_target.all')); ?>
                    </label>
                    <label class="admin-form-check form-label">
                        <input id="banner_admin_banners_match_type_exact" type="radio" name="match_type" value="exact" class="form-radio" data-admin-subject-scope<?php echo $currentMatchType === 'exact' ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html(sr_t('banner::ui.subject_target.exact')); ?>
                    </label>
                    <p class="admin-form-help" data-admin-subject-public-help<?php echo sr_banner_is_public_target_option($selectedTargetOption) ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('banner::ui.subject_target.public_help')); ?></p>
                </div>
            </div>
            <div class="admin-form-row" data-admin-subject-row<?php echo $subjectRequired ? '' : ' hidden'; ?>>
                <div class="form-label admin-form-label-help"><label for="banner_admin_banners_subject_id"><?php echo sr_e(sr_t('banner::ui.subject.id.14852174')); ?> <span class="sr-required-label" data-admin-subject-required<?php echo $subjectRequired ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('banner::ui.required.1f227c67')); ?></span></label></div>
                <div class="admin-form-field">
                    <div class="admin-lookup-control">
                        <input id="banner_admin_banners_subject_id" type="text" name="subject_id" value="<?php echo $editing ? sr_e((string) ($editBanner['subject_id'] ?? '')) : ''; ?>" class="form-input" maxlength="80" data-admin-subject-id data-validation-message="대상 ID를 입력해 주세요."<?php echo $subjectRequired ? ' required' : ' disabled'; ?>>
                        <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($bannerSubjectLookupModalId); ?>" data-overlay="#<?php echo sr_e($bannerSubjectLookupModalId); ?>" data-overlay-stack="true" data-admin-reference-lookup-open data-banner-subject-search-button data-type-target="#banner_admin_banners_subject_reference_type" data-id-target="#banner_admin_banners_subject_id"<?php echo $bannerSubjectSearchEnabled ? '' : ' disabled hidden'; ?>><?php echo sr_e(sr_t('banner::ui.subject_target.search')); ?></button>
                    </div>
                    <input id="banner_admin_banners_subject_reference_type" type="hidden" name="subject_reference_type" value="<?php echo sr_e($currentSubjectTargetType); ?>" data-admin-subject-reference-type>
                    <p class="admin-form-help"><?php echo sr_e(sr_t('banner::ui.subject_target.subject_help')); ?></p>
                </div>
            </div>
        </section>
        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('banner::ui.section.settings')); ?></h2>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('banner_admin_banners_status', sr_t('banner::ui.status.e10195a1'), $bannerHelp['status']['id'], $bannerHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <select id="banner_admin_banners_status" name="status" class="form-select">
                                            <?php foreach ($allowedStatuses as $status) { ?>
                                                <?php $currentStatus = $editing ? (string) $editBanner['status'] : (isset($bannerDefaultStatus) ? (string) $bannerDefaultStatus : 'draft'); ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>>
                                                    <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                    <p class="admin-form-help"><?php echo sr_e(sr_t('banner::ui.active.status.active.6c539bd1')); ?></p>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('banner_admin_banners_skin_key', sr_t('banner::ui.banner.46b4fae5'), $bannerHelp['skin_key']['id'], $bannerHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <select id="banner_admin_banners_skin_key" name="skin_key" class="form-select">
                                            <?php foreach ($bannerSkinOptions as $skinKey => $skinOption) { ?>
                                                <?php $currentSkinKey = $editing ? (string) ($editBanner['skin_key'] ?? $bannerSkinKey) : $bannerSkinKey; ?>
                                                <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $currentSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                                    <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                                    (<?php echo sr_e(implode(', ', array_map('sr_banner_placement_kind_label', is_array($skinOption['supports'] ?? null) ? $skinOption['supports'] : ['inline']))); ?>)
                                                </option>
                                            <?php } ?>
                                        </select>
                    <p class="admin-form-help"><?php echo sr_e(sr_t('banner::ui.save.97360101')); ?></p>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('banner_admin_banners_starts_at', sr_t('banner::ui.text.65bdaefd'), $bannerHelp['starts_at']['id'], $bannerHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_starts_at" type="datetime-local" name="starts_at" value="<?php echo $editing ? sr_e(sr_banner_admin_datetime_value($editBanner['starts_at'] ?? null)) : ''; ?>" class="form-input">
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('banner_admin_banners_ends_at', sr_t('banner::ui.text.26c25fca'), $bannerHelp['ends_at']['id'], $bannerHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_ends_at" type="datetime-local" name="ends_at" value="<?php echo $editing ? sr_e(sr_banner_admin_datetime_value($editBanner['ends_at'] ?? null)) : ''; ?>" class="form-input">
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('banner_admin_banners_sort_order', sr_t('banner::ui.text.3788952d'), $bannerHelp['sort_order']['id'], $bannerHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_sort_order" type="number" name="sort_order" value="<?php echo $editing ? sr_e((string) $editBanner['sort_order']) : sr_e((string) ($bannerDefaultSortOrder ?? 100)); ?>" class="form-input">
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('banner::ui.list.f07b3200')); ?></a>
            <?php if ($editing) { ?>
                <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="banner-copy-modal-<?php echo sr_e((string) (int) $editBanner['id']); ?>" data-overlay="#banner-copy-modal-<?php echo sr_e((string) (int) $editBanner['id']); ?>"><?php echo sr_e('복사'); ?></button>
            <?php } ?>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('banner::ui.save.5fb92622')); ?></button>
        </div>
    </form>
    <?php echo $editing ? $bannerCopyModalHtml($editBanner, '/admin/banners/edit?id=' . rawurlencode((string) $editBanner['id'])) : ''; ?>
<?php } else { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-summary-stats">
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('banner::ui.banner.58c664bd')); ?> <strong><?php echo sr_e((string) $totalBanners); ?><?php echo sr_e(sr_t('banner::ui.text.a57ab057')); ?></strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/banners?status=enabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('banner::ui.active.93c558d7')); ?> <?php echo sr_e((string) ($bannerStatusCounts['enabled'] ?? 0)); ?><?php echo sr_e(sr_t('banner::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/banners?status=draft')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('banner::ui.text.145b2413')); ?> <?php echo sr_e((string) ($bannerStatusCounts['draft'] ?? 0)); ?><?php echo sr_e(sr_t('banner::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/banners?status=disabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('banner::ui.text.92cdef3c')); ?> <?php echo sr_e((string) ($bannerStatusCounts['disabled'] ?? 0)); ?><?php echo sr_e(sr_t('banner::ui.text.a57ab057')); ?></a>
        </div>
    </div>

    <?php
    $selectedBannerStatuses = is_array($filters['status'] ?? null) ? $filters['status'] : [];
    $selectedBannerTargets = is_array($filters['target'] ?? null) ? $filters['target'] : [];
    $selectedBannerTarget = (string) ($selectedBannerTargets[0] ?? '');
    $selectedBannerTargetService = (string) ($filters['target_service'] ?? '');
    if ($selectedBannerTargetService === '' && $selectedBannerTarget !== '') {
        $selectedBannerTargetService = sr_banner_selected_target_service_key($selectedBannerTarget);
    }
    $bannerDetailFilterOpen = $selectedBannerStatuses !== [] || $selectedBannerTargets !== [] || $selectedBannerTargetService !== '';
    $bannerTargetServiceOptions = sr_banner_target_service_options($availableTargets, true);
    ?>
    <form method="get" action="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="filtering-form admin-banner-filter ui-form-theme">
        <div class="filtering-fields admin-banner-search-grid">
            <div class="filtering filtering-card<?php echo $bannerDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
                <div class="filtering-fields">
                    <div class="filtering-field admin-banner-filter-field">
                        <label for="modules_banner_admin_banners_field" class="filtering-label">검색조건</label>
                        <select id="modules_banner_admin_banners_field" name="field" class="form-select filtering-input">
                            <?php foreach (['all' => sr_t('banner::ui.all.a4b69faf'), 'title' => sr_t('banner::ui.text.08b17e43'), 'link' => sr_t('banner::ui.text.3d54da9c')] as $fieldValue => $fieldLabel) { ?>
                                <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($filters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                                    <?php echo sr_e($fieldLabel); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="filtering-field-fill filtering-field admin-banner-filter-keyword">
                        <label for="modules_banner_admin_banners_q" class="filtering-label"><?php echo sr_e(sr_t('banner::ui.search.bda397fc')); ?></label>
                        <input id="modules_banner_admin_banners_q" type="text" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('banner::ui.text.b2273378')); ?>">
                    </div>
                </div>
                <div id="modules_banner_admin_banners_detail_filters" class="filtering-body" data-filtering-body<?php echo $bannerDetailFilterOpen ? '' : ' hidden'; ?>>
                    <div class="filtering-field admin-banner-filter-status">
                        <span class="filtering-label"><?php echo sr_e(sr_t('banner::ui.status.e10195a1')); ?></span>
                        <?php echo sr_admin_filter_toggle_group_html('modules_banner_admin_banners_status', 'status', sr_admin_code_label_options($allowedStatuses, 'content_status'), $selectedBannerStatuses, sr_t('banner::ui.all.a4b69faf')); ?>
                    </div>
                    <div class="filtering-field admin-banner-filter-target">
                        <label for="modules_banner_admin_banners_target_service" class="filtering-label"><?php echo sr_e('서비스'); ?></label>
                        <select id="modules_banner_admin_banners_target_service" name="target_service" class="form-select filtering-input" data-admin-target-service>
                            <option value=""><?php echo sr_e(sr_t('banner::ui.all.a4b69faf')); ?></option>
                            <?php foreach ($bannerTargetServiceOptions as $serviceKey => $serviceLabel) { ?>
                                <option value="<?php echo sr_e((string) $serviceKey); ?>"<?php echo $selectedBannerTargetService === (string) $serviceKey ? ' selected' : ''; ?>><?php echo sr_e((string) $serviceLabel); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="filtering-field admin-banner-filter-target">
                        <label for="modules_banner_admin_banners_target" class="filtering-label"><?php echo sr_e('상세'); ?></label>
                        <select id="modules_banner_admin_banners_target" name="target" class="form-select filtering-input" data-admin-target-detail>
                            <option value=""><?php echo sr_e(sr_t('banner::ui.all.a4b69faf')); ?></option>
                            <?php foreach ($availableTargets as $target) { ?>
                                <?php $targetValue = sr_banner_target_option_value($target); ?>
                                <option value="<?php echo sr_e($targetValue); ?>" data-service="<?php echo sr_e(sr_banner_target_service_key($target)); ?>"<?php echo $selectedBannerTarget === $targetValue ? ' selected' : ''; ?>><?php echo sr_e(sr_banner_target_admin_label($target)); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="filtering-actions">
                    <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $bannerDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="modules_banner_admin_banners_detail_filters">상세검색</button>
                    <button type="button" class="btn btn-outline-light" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
                    <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('banner::ui.search.4b8d541e')); ?></button>
                </div>
            </div>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?php echo sr_e(sr_t('banner::ui.banner.list.f989d740')); ?></h2>
            </div>
            <a href="<?php echo sr_e(sr_url('/admin/banners/new')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo sr_e(sr_t('banner::ui.banner.c0e70d2c')); ?></a>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($bannerSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url($bannerSortOptions, $bannerDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="배너 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <form id="banner-bulk-status-form" method="post" action="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="admin-banner-bulk-form" data-banner-bulk-form>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="batch_status">
                <input type="hidden" name="operation_key" value="banner.set_status">
                <input type="hidden" name="return_to" value="<?php echo sr_e((string) ($_SERVER['REQUEST_URI'] ?? '/admin/banners')); ?>">
                <div class="admin-banner-bulk-actions admin-row-actions" data-banner-bulk-bar>
                    <div class="admin-banner-bulk-controls admin-row-actions">
                        <button type="submit" name="target_status" value="enabled" class="btn btn-sm btn-outline-warning" data-banner-bulk-submit data-status-label="사용" disabled>사용</button>
                        <button type="submit" name="target_status" value="disabled" class="btn btn-sm btn-outline-warning" data-banner-bulk-submit data-status-label="사용 안 함" disabled>사용 안 함</button>
                        <button type="button" class="btn btn-sm btn-outline-light" data-banner-bulk-clear aria-label="선택 해제" title="선택 해제" hidden><?php echo sr_material_icon_html('close'); ?><span data-banner-selected-count>0</span></button>
                    </div>
                </div>
            </form>
            <?php echo sr_admin_pagination_summary_html($bannerPagination); ?>
        </div>
        <div class="table-wrapper">
        <table class="table admin-banner-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('banner::ui.banner.list.f989d740')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th class="admin-table-checkbox-cell admin-banner-select-cell">
                        <label class="sr-only" for="banner_bulk_select_all">현재 페이지 배너 전체 선택</label>
                        <input id="banner_bulk_select_all" type="checkbox" class="form-checkbox" data-banner-select-all<?php echo $banners === [] ? ' disabled' : ''; ?>>
                    </th>
                    <th<?php echo sr_admin_sort_aria('title', $bannerSort); ?>><?php echo sr_admin_sort_header_html(sr_t('banner::ui.text.08b17e43'), 'title', $bannerSort, $bannerSortOptions, $bannerDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $bannerSort); ?>><?php echo sr_admin_sort_header_html(sr_t('banner::ui.status.e10195a1'), 'status', $bannerSort, $bannerSortOptions, $bannerDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('skin_key', $bannerSort); ?>><?php echo sr_admin_sort_header_html(sr_t('banner::ui.text.776b723f'), 'skin_key', $bannerSort, $bannerSortOptions, $bannerDefaultSort); ?></th>
                    <th><?php echo sr_e(sr_t('banner::ui.text.3d54da9c')); ?></th>
                    <th<?php echo sr_admin_sort_aria('click_count', $bannerSort); ?>><?php echo sr_admin_sort_header_html(sr_t('banner::ui.text.a6bb9eae'), 'click_count', $bannerSort, $bannerSortOptions, $bannerDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('target', $bannerSort); ?>><?php echo sr_admin_sort_header_html(sr_t('banner::ui.text.76389a62'), 'target', $bannerSort, $bannerSortOptions, $bannerDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('starts_at', $bannerSort); ?>><?php echo sr_admin_sort_header_html(sr_t('banner::ui.text.65bdaefd'), 'starts_at', $bannerSort, $bannerSortOptions, $bannerDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('ends_at', $bannerSort); ?>><?php echo sr_admin_sort_header_html(sr_t('banner::ui.text.26c25fca'), 'ends_at', $bannerSort, $bannerSortOptions, $bannerDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('sort_order', $bannerSort); ?>><?php echo sr_admin_sort_header_html(sr_t('banner::ui.text.3788952d'), 'sort_order', $bannerSort, $bannerSortOptions, $bannerDefaultSort); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('banner::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($banners === []) { ?>
                    <tr>
                        <td colspan="11" class="admin-empty-state"><?php echo sr_e(sr_t('banner::ui.create.banner.a9744568')); ?></td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($banners as $banner) { ?>
                        <?php
                        if ((string) ($banner['module_key'] ?? '') === '') {
                            $bannerTargetLabel = sr_t('banner::ui.banner.48de068b');
                        } else {
                            $bannerTargetOption = (string) $banner['module_key'] . '|' . (string) $banner['point_key'] . '|' . (string) $banner['slot_key'];
                            $bannerTargetLabel = (string) ($targetLabels[$bannerTargetOption] ?? (sr_t('banner::ui.text.bb23452c') . (string) $banner['module_key'] . ' / ' . (string) $banner['point_key'] . ' / ' . (string) $banner['slot_key']));
                        }
                        $bannerStatus = (string) $banner['status'];
                        $statusClass = match ($bannerStatus) {
                            'enabled' => 'is-normal',
                            'draft' => 'is-blocked',
                            default => 'is-left',
                        };
                        ?>
                        <tr>
                            <td class="admin-table-checkbox-cell admin-banner-select-cell">
                                <label class="sr-only" for="banner_bulk_select_<?php echo sr_e((string) (int) $banner['id']); ?>"><?php echo sr_e((string) $banner['title']); ?> 선택</label>
                                <input id="banner_bulk_select_<?php echo sr_e((string) (int) $banner['id']); ?>" type="checkbox" name="selected_banner_ids[]" value="<?php echo sr_e((string) (int) $banner['id']); ?>" class="form-checkbox" form="banner-bulk-status-form" data-banner-row-select>
                            </td>
                            <td class="admin-table-break admin-banner-title-cell"><?php echo sr_e((string) $banner['title']); ?></td>
                            <td class="admin-table-nowrap">
                                <span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($bannerStatus, 'content_status')); ?></span>
                            </td>
                            <?php $bannerRowSkinKey = sr_banner_skin_key(['banner_skin_key' => (string) ($banner['skin_key'] ?? 'basic')]); ?>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) ($bannerSkinOptions[$bannerRowSkinKey]['label'] ?? $bannerRowSkinKey)); ?></td>
                            <td class="admin-table-nowrap admin-banner-link-cell">
                                <?php if ((string) ($banner['link_url'] ?? '') !== '') { ?>
                                    <a href="<?php echo sr_e((string) $banner['link_url']); ?>" class="btn btn-sm btn-icon btn-solid-light" target="_blank" rel="noopener noreferrer" aria-label="<?php echo sr_e('링크 새 탭에서 열기'); ?>" title="<?php echo sr_e('링크 새 탭에서 열기'); ?>"><?php echo sr_material_icon_html('open_in_new'); ?></a>
                                <?php } else { ?>
                                    <?php echo sr_e(sr_banner_link_type_label((string) ($banner['link_url'] ?? ''))); ?>
                                <?php } ?>
                            </td>
                            <td class="admin-table-nowrap text-end"><?php echo sr_e(number_format((int) ($banner['click_count'] ?? 0))); ?></td>
                            <td class="admin-table-break admin-banner-target-cell"><?php echo sr_e($bannerTargetLabel); ?></td>
                            <td class="admin-table-nowrap admin-banner-date-cell"><?php echo sr_e((string) ($banner['starts_at'] ?? '-')); ?></td>
                            <td class="admin-table-nowrap admin-banner-date-cell"><?php echo sr_e((string) ($banner['ends_at'] ?? '-')); ?></td>
                            <td class="admin-table-nowrap text-end"><?php echo sr_e((string) $banner['sort_order']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <?php
                                    $bannerCopyModalId = 'banner-copy-modal-' . (string) (int) $banner['id'];
                                    $bannerCopyModals .= $bannerCopyModalHtml($banner, (string) ($_SERVER['REQUEST_URI'] ?? '/admin/banners'));
                                    $bannerReferenceModalId = 'banner-reference-modal-' . (string) (int) $banner['id'];
                                    $bannerReferenceResult = $bannerReadReferencesById[(int) $banner['id']] ?? ['rows' => [], 'errors' => []];
                                    $bannerReferenceModals .= sr_admin_read_reference_modal_html($bannerReferenceModalId, '배너 참조 현황', $bannerReferenceResult);
                                    ?>
                                    <?php echo sr_admin_read_reference_button_html($bannerReferenceModalId, $bannerReferenceResult); ?>
                                    <a href="<?php echo sr_e(sr_url('/admin/banners/edit?id=' . rawurlencode((string) $banner['id']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('banner::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('banner::ui.edit.3537f0cc')); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
                                    <button type="button" class="btn btn-sm btn-icon btn-solid-light" aria-label="<?php echo sr_e('복사'); ?>" title="<?php echo sr_e('복사'); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($bannerCopyModalId); ?>" data-overlay="#<?php echo sr_e($bannerCopyModalId); ?>"><?php echo sr_material_icon_html('content_copy'); ?></button>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/banners/delete')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="banner_id" value="<?php echo sr_e((string) $banner['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="<?php echo sr_e(sr_t('banner::ui.delete.6139b6c3')); ?>" title="<?php echo sr_e(sr_t('banner::ui.delete.6139b6c3')); ?>"><?php echo sr_material_icon_html('delete'); ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
	        </table>
	        </div>
        <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('open_in_new'); ?> 링크 새 탭에서 열기</span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('travel_explore'); ?> 참조 현황</span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> <?php echo sr_e(sr_t('banner::ui.edit.3537f0cc')); ?></span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('content_copy'); ?> 복사</span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('delete'); ?> <?php echo sr_e(sr_t('banner::ui.delete.6139b6c3')); ?></span>
        </div>
	    </section>
    <?php echo $bannerCopyModals; ?>
    <?php echo $bannerReferenceModals; ?>
    <?php echo sr_admin_pagination_html($bannerPagination, '배너 목록 페이지'); ?>
    <script>
    (function () {
        var form = document.querySelector('[data-banner-bulk-form]');
        if (!form) {
            return;
        }
        var countNode = document.querySelector('[data-banner-selected-count]');
        var submitButtons = Array.prototype.slice.call(document.querySelectorAll('[data-banner-bulk-submit]'));
        var clear = document.querySelector('[data-banner-bulk-clear]');
        var selectAll = document.querySelector('[data-banner-select-all]');
        var rowChecks = Array.prototype.slice.call(document.querySelectorAll('[data-banner-row-select]'));

        function checkedRows() {
            return rowChecks.filter(function (input) {
                return input.checked && !input.disabled;
            });
        }

        function syncBulkState() {
            var selectedCount = checkedRows().length;
            if (countNode) {
                countNode.textContent = String(selectedCount);
            }
            submitButtons.forEach(function (button) {
                button.disabled = selectedCount < 1;
            });
            if (clear) {
                clear.hidden = selectedCount < 1;
            }
            if (selectAll) {
                selectAll.checked = selectedCount > 0 && selectedCount === rowChecks.length;
                selectAll.indeterminate = selectedCount > 0 && selectedCount < rowChecks.length;
            }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                rowChecks.forEach(function (input) {
                    if (!input.disabled) {
                        input.checked = selectAll.checked;
                    }
                });
                syncBulkState();
            });
        }
        rowChecks.forEach(function (input) {
            input.addEventListener('change', syncBulkState);
        });
        if (clear) {
            clear.addEventListener('click', function () {
                rowChecks.forEach(function (input) {
                    input.checked = false;
                });
                syncBulkState();
            });
        }
        form.addEventListener('submit', function (event) {
            var selectedCount = checkedRows().length;
            if (selectedCount < 1) {
                event.preventDefault();
                syncBulkState();
                return;
            }
            var submitter = event.submitter || document.activeElement;
            var statusLabel = submitter && submitter.getAttribute ? submitter.getAttribute('data-status-label') : '';
            if (!statusLabel) {
                statusLabel = submitter && submitter.textContent ? submitter.textContent.replace(/\s+/g, ' ').trim() : '선택한 상태';
            }
            if (!window.confirm('선택한 배너 ' + selectedCount + '건의 상태를 "' + statusLabel + '"(으)로 변경합니다.')) {
                event.preventDefault();
            }
        });
        syncBulkState();
    }());
    </script>
<?php } ?>

<?php if ($bannerAdminPage === 'form') { ?>
    <?php foreach ($bannerHelp as $bannerHelpModal) { ?>
        <?php echo sr_admin_help_modal_html((string) $bannerHelpModal['id'], (string) $bannerHelpModal['title'], (string) $bannerHelpModal['body']); ?>
    <?php } ?>
    <div id="<?php echo sr_e($bannerSubjectLookupModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($bannerSubjectLookupModalId); ?>_title" aria-hidden="true" inert data-overlay-stack="true">
        <div class="modal-dialog admin-lookup-dialog">
            <div class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($bannerSubjectLookupModalId); ?>_title" class="modal-title"><?php echo sr_e(sr_t('banner::ui.subject_target.search_title')); ?></h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($bannerSubjectLookupModalId); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <form class="admin-lookup-search-form" data-admin-reference-search-form data-endpoint="<?php echo sr_e(sr_url('/admin/banners/subject-search')); ?>" data-type-target="#banner_admin_banners_subject_reference_type" data-id-target="#banner_admin_banners_subject_id" data-results="#<?php echo sr_e($bannerSubjectLookupResultsId); ?>">
                        <select name="reference_type" class="form-select" aria-label="<?php echo sr_e(sr_t('banner::ui.subject_target.search_type')); ?>">
                            <?php foreach ($bannerSubjectSearchTypes as $targetType => $targetLabel) { ?>
                                <option value="<?php echo sr_e((string) $targetType); ?>"><?php echo sr_e((string) $targetLabel); ?></option>
                            <?php } ?>
                        </select>
                        <input type="text" name="q" maxlength="120" class="form-input" placeholder="<?php echo sr_e(sr_t('banner::ui.subject_target.search_placeholder')); ?>" data-overlay-focus>
                        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('banner::ui.search.4b8d541e')); ?></button>
                    </form>
                    <div id="<?php echo sr_e($bannerSubjectLookupResultsId); ?>" class="admin-lookup-results">
                        <p class="admin-empty-state admin-lookup-empty"><?php echo sr_e(sr_t('banner::ui.subject_target.search_empty')); ?></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($bannerSubjectLookupModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<?php if ($bannerAdminPage === 'form') { ?>
    <script>
    (function () {
        var form = document.querySelector('[data-admin-subject-form]');
        if (!form) {
            return;
        }

        var targetService = form.querySelector('[data-admin-target-service]');
        var targetDetail = form.querySelector('[data-admin-target-detail]');
        var targetOption = form.querySelector('[data-admin-target-option]');
        var targetDetailRow = form.querySelector('[data-admin-target-detail-row]');
        var targetDetailRequired = form.querySelector('[data-admin-target-detail-required]');
        var scopes = form.querySelectorAll('[data-admin-subject-scope]');
        var exact = form.querySelector('input[name="match_type"][value="exact"]');
        var all = form.querySelector('input[name="match_type"][value="all"]');
        var scopeRow = form.querySelector('[data-admin-subject-scope-row]');
        var subject = form.querySelector('[data-admin-subject-id]');
        var subjectRow = form.querySelector('[data-admin-subject-row]');
        var subjectReferenceType = form.querySelector('[data-admin-subject-reference-type]');
        var subjectSearchButton = form.querySelector('[data-banner-subject-search-button]');
        var subjectSearchModal = document.getElementById('<?php echo sr_e($bannerSubjectLookupModalId); ?>');
        var label = form.querySelector('[data-admin-subject-required]');
        var publicHelp = form.querySelector('[data-admin-subject-public-help]');
        var publicTarget = form.getAttribute('data-public-target-value') || '';
        var searchableTargetTypes = <?php echo sr_js_json_encode($bannerSubjectTargetTypeMap); ?>;
        var useImage = form.querySelector('[data-admin-banner-use-image]');
        var imageRows = form.querySelectorAll('[data-admin-banner-image-row]');
        var imageInputs = form.querySelectorAll('[data-admin-banner-image-input]');
        var bannerTextLabel = form.querySelector('[data-admin-banner-text-label]');
        var bannerTextHelp = form.querySelector('[data-admin-banner-text-help]');
        var imageTextLabels = <?php echo sr_js_json_encode([
            'alt' => sr_t('banner::ui.alt_text'),
            'display' => sr_t('banner::ui.display_text'),
        ]); ?>;
        var imageTextHelp = <?php echo sr_js_json_encode([
            'alt' => sr_t('banner::ui.alt_text.help'),
            'display' => sr_t('banner::ui.display_text.help'),
        ]); ?>;

        function syncTargetDetail() {
            var service = targetService ? targetService.value : publicTarget;
            var isPublic = service === publicTarget;
            if (targetDetailRow) {
                targetDetailRow.hidden = isPublic;
            }
            if (targetDetailRequired) {
                targetDetailRequired.hidden = isPublic;
            }
            if (targetDetail) {
                var visibleOptions = [];
                Array.prototype.forEach.call(targetDetail.options, function (option) {
                    var visible = !isPublic && option.getAttribute('data-service') === service;
                    option.hidden = !visible;
                    option.disabled = !visible;
                    if (visible) {
                        visibleOptions.push(option);
                    }
                });
                targetDetail.disabled = isPublic;
                targetDetail.required = !isPublic;
                if (!isPublic && (!targetDetail.value || targetDetail.selectedIndex < 0 || targetDetail.options[targetDetail.selectedIndex].disabled) && visibleOptions.length > 0) {
                    targetDetail.value = visibleOptions[0].value;
                }
            }
            if (targetOption) {
                targetOption.value = isPublic ? publicTarget : (targetDetail ? targetDetail.value : '');
            }
        }

        function syncSubjectRequired() {
            syncTargetDetail();
            var currentTargetOption = targetOption ? targetOption.value : '';
            var isPublic = currentTargetOption === publicTarget;
            var subjectTargetType = searchableTargetTypes[currentTargetOption] || '';
            var scopeVisible = !!subjectTargetType && !isPublic;
            if (!scopeVisible && all) {
                all.checked = true;
            }
            if (scopeRow) {
                scopeRow.hidden = !scopeVisible;
            }
            if (exact) {
                exact.disabled = !scopeVisible;
            }
            var needed = !!(subject && exact && scopeVisible && exact.checked);
            if (label) {
                label.hidden = !needed;
            }
            if (subjectRow) {
                subjectRow.hidden = !needed;
            }
            if (subject) {
                subject.required = needed;
                subject.disabled = !needed;
            }
            if (subjectReferenceType) {
                subjectReferenceType.value = subjectTargetType;
            }
            if (subjectSearchButton) {
                subjectSearchButton.disabled = !scopeVisible;
                subjectSearchButton.hidden = !scopeVisible;
                subjectSearchButton.classList.toggle('hidden', !scopeVisible);
            }
            if (subjectSearchModal) {
                var modalType = subjectSearchModal.querySelector('select[name="reference_type"]');
                if (modalType && subjectTargetType) {
                    modalType.value = subjectTargetType;
                }
            }
            if (publicHelp) {
                publicHelp.hidden = !isPublic;
            }
        }

        form.addEventListener('change', function (event) {
            if (event.target === targetService || event.target === targetDetail || Array.prototype.indexOf.call(scopes, event.target) !== -1) {
                syncSubjectRequired();
            }
        });
        syncSubjectRequired();

        function syncImageMode() {
            var enabled = !!(useImage && useImage.checked);
            imageRows.forEach(function (row) {
                row.hidden = !enabled;
            });
            imageInputs.forEach(function (input) {
                input.disabled = !enabled;
            });
            if (bannerTextLabel) {
                bannerTextLabel.textContent = enabled ? imageTextLabels.alt : imageTextLabels.display;
            }
            if (bannerTextHelp) {
                bannerTextHelp.textContent = enabled ? imageTextHelp.alt : imageTextHelp.display;
            }
        }

        if (useImage) {
            useImage.addEventListener('change', syncImageMode);
            syncImageMode();
        }
    })();
    </script>
<?php } ?>

<script>
(function () {
    document.querySelectorAll('form').forEach(function (form) {
        var service = form.querySelector('[data-admin-target-service]');
        var detail = form.querySelector('[data-admin-target-detail]');
        if (!service || !detail || form.hasAttribute('data-admin-subject-form')) {
            return;
        }

        function syncDetail() {
            var serviceValue = service.value || '';
            Array.prototype.forEach.call(detail.options, function (option) {
                if (option.value === '') {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }
                var visible = serviceValue === '' || option.getAttribute('data-service') === serviceValue;
                option.hidden = !visible;
                option.disabled = !visible;
            });
            if (detail.value && detail.options[detail.selectedIndex] && detail.options[detail.selectedIndex].disabled) {
                detail.value = '';
            }
        }

        service.addEventListener('change', syncDetail);
        form.addEventListener('reset', function () {
            window.setTimeout(syncDetail, 0);
        });
        syncDetail();
    });
}());
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
