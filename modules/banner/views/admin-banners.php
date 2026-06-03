<?php

$bannerAdminPage = isset($bannerAdminPage) ? (string) $bannerAdminPage : 'list';
$editing = is_array($editBanner);
$adminPageTitle = $bannerAdminPage === 'form' ? ($editing ? sr_t('banner::ui.banner.edit.52756afa') : sr_t('banner::ui.banner.b0dbbde9')) : sr_t('banner::ui.banner.list.f989d740');
$adminPageSubtitle = $bannerAdminPage === 'form' ? sr_t('banner::ui.banner.71184934') : sr_t('banner::ui.banner.status.search.ae378c83');
$adminContainerClass = $bannerAdminPage === 'form' ? 'admin-page-banner-form admin-ui-scope' : 'admin-page-banner-list admin-ui-scope';
$filters = isset($filters) && is_array($filters) ? $filters : ['status' => '', 'target' => '', 'field' => 'all', 'q' => ''];
$bannerSortOptions = isset($bannerSortOptions) && is_array($bannerSortOptions) ? $bannerSortOptions : [
    'title' => ['columns' => ['b.title', 'b.id']],
    'status' => ['columns' => ['b.status', 'b.id']],
    'skin_key' => ['columns' => ['b.skin_key', 'b.id']],
    'click_count' => ['columns' => ['b.click_count', 'b.id']],
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
$currentMatchType = $editing ? (string) ($editBanner['match_type'] ?? 'all') : (isset($bannerDefaultMatchType) ? (string) $bannerDefaultMatchType : 'all');
$subjectRequired = !sr_banner_is_public_target_option($selectedTargetOption) && $currentMatchType === 'exact';
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
    'match_type' => [
        'id' => 'banner_admin_help_match_type',
        'title' => sr_t('banner::help.match_type.title'),
        'body' => $bannerHelpBodyHtml(['banner::help.match_type.body.1', 'banner::help.match_type.body.2']),
    ],
    'subject_id' => [
        'id' => 'banner_admin_help_subject_id',
        'title' => sr_t('banner::help.subject_id.title'),
        'body' => $bannerHelpBodyHtml(['banner::help.subject_id.body.1', 'banner::help.subject_id.body.2']),
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
    <form method="post" action="<?php echo sr_e(sr_url('/admin/banners/save')); ?>" enctype="multipart/form-data" class="admin-form ui-form-theme" data-admin-subject-form data-public-target-value="<?php echo sr_e(sr_banner_public_target_option_value()); ?>">
        <section class="admin-card card">
            <h2><?php echo $editing ? sr_t('banner::ui.banner.edit.52756afa') : sr_t('banner::ui.banner.b0dbbde9'); ?></h2>
            <p><?php echo sr_e(sr_t('banner::ui.banner.banner.select.banner.active.40c015cc')); ?></p>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="banner_id" value="<?php echo $editing ? sr_e((string) $editBanner['id']) : '0'; ?>">
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_title"><?php echo sr_e(sr_t('banner::ui.text.08b17e43')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('banner::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_title" type="text" name="title" value="<?php echo $editing ? sr_e((string) $editBanner['title']) : ''; ?>" class="form-input form-control-full" maxlength="120" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_body_text"><?php echo sr_e(sr_t('banner::ui.text.cb0f2404')); ?></label>
                <div class="admin-form-field">
                    <textarea id="banner_admin_banners_body_text" name="body_text" maxlength="3000" class="form-textarea"><?php echo $editing ? sr_e((string) $editBanner['body_text']) : ''; ?></textarea>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('banner_admin_banners_link_url', sr_t('banner::ui.url.http.https.81cff7be'), $bannerHelp['link_url']['id'], $bannerHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_link_url" type="text" name="link_url" value="<?php echo $editing ? sr_e((string) $editBanner['link_url']) : ''; ?>" class="form-input form-control-full" maxlength="255">
                    <p class="admin-form-help"><?php echo sr_e(sr_t('banner::ui.url.help.6f5481db')); ?></p>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('banner_admin_banners_image_url', sr_t('banner::ui.url.http.https.url.264bd3d3'), $bannerHelp['image_url']['id'], $bannerHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_image_url" type="text" name="image_url" value="<?php echo $editing ? sr_e((string) $editBanner['image_url']) : ''; ?>" class="form-input form-control-full" maxlength="255">
                    <p class="admin-form-help"><?php echo sr_e(sr_t('banner::ui.url.help.e0a0162e')); ?></p>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('banner_admin_banners_image_upload', sr_t('banner::ui.text.cead00a8'), $bannerHelp['image_upload']['id'], $bannerHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_image_upload" type="file" name="image_upload" accept="image/jpeg,image/png,image/webp" class="form-input">
                    <br>
                                    <small><?php echo sr_e(sr_t('banner::ui.jpeg.png.webp.6252010a')); ?> <?php echo sr_e(sr_banner_format_bytes(sr_banner_image_upload_max_bytes())); ?><?php echo sr_e(sr_t('banner::ui.text.58609b0c')); ?></small>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('banner_admin_banners_target_option', sr_t('banner::ui.text.76389a62'), $bannerHelp['target_option']['id'], $bannerHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <select id="banner_admin_banners_target_option" name="target_option" class="form-select">
                                            <option value="<?php echo sr_e(sr_banner_public_target_option_value()); ?>"<?php echo $selectedTargetOption === sr_banner_public_target_option_value() ? ' selected' : ''; ?>>
                                                <?php echo sr_e(sr_t('banner::ui.banner.48de068b')); ?>
                                            </option>
                                            <?php foreach ($availableTargets as $target) { ?>
                                                <?php $optionValue = sr_banner_target_option_value($target); ?>
                                                <option value="<?php echo sr_e($optionValue); ?>"<?php echo $selectedTargetOption === $optionValue ? ' selected' : ''; ?>>
                                                    <?php echo sr_e((string) $target['label']); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                    <br>
                                    <small><?php echo sr_e(sr_t('banner::ui.banner.settings.select.active.88d78049')); ?></small>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('banner_admin_banners_match_type', sr_t('banner::ui.text.175f56ba'), $bannerHelp['match_type']['id'], $bannerHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <select id="banner_admin_banners_match_type" name="match_type" class="form-select">
                                            <?php foreach ($allowedMatchTypes as $matchType) { ?>
                                                <option value="<?php echo sr_e($matchType); ?>"<?php echo $currentMatchType === $matchType ? ' selected' : ''; ?>>
                                                    <?php echo sr_e(sr_admin_code_label($matchType, 'match_type')); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="form-label admin-form-label-help"><?php echo $bannerHelpButtonHtml(sr_t('banner::ui.subject.id.14852174'), $bannerHelp['subject_id']['id']); ?><label for="banner_admin_banners_subject_id"><?php echo sr_e(sr_t('banner::ui.subject.id.14852174')); ?> <span class="sr-required-label" data-admin-subject-required<?php echo $subjectRequired ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('banner::ui.required.1f227c67')); ?></span></label></div>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_subject_id" type="text" name="subject_id" value="<?php echo $editing ? sr_e((string) ($editBanner['subject_id'] ?? '')) : ''; ?>" class="form-input" maxlength="80" data-admin-subject-id<?php echo $subjectRequired ? ' required' : ''; ?>>
                </div>
            </div>
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
                    <br>
                                    <small><?php echo sr_e(sr_t('banner::ui.active.status.active.6c539bd1')); ?></small>
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
                    <br>
                                    <small><?php echo sr_e(sr_t('banner::ui.save.97360101')); ?></small>
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
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('banner::ui.save.5fb92622')); ?></button>
        </div>
    </form>
<?php } else { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('banner::ui.all.e078b14a')); ?></a>
        </div>
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
    ?>
    <form method="get" action="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="admin-filter admin-banner-filter ui-form-theme">
        <div class="admin-filter-grid admin-banner-search-grid">
                    <div class="admin-filter-field admin-banner-filter-status">
                        <label class="admin-filter-label"><?php echo sr_e(sr_t('banner::ui.status.e10195a1')); ?></label>
                        <div class="btn-group" role="group" aria-label="<?php echo sr_e(sr_t('banner::ui.status.e10195a1')); ?>">
                            <?php foreach ($allowedStatuses as $index => $status) { ?>
                                <?php $groupClass = $index === 0 ? 'btn-group-start' : ($index === count($allowedStatuses) - 1 ? 'btn-group-end' : 'btn-group-middle'); ?>
                                <label class="btn btn-choice-light <?php echo sr_e($groupClass); ?>" for="modules_banner_admin_banners_status_<?php echo sr_e($status); ?>">
                                    <input id="modules_banner_admin_banners_status_<?php echo sr_e($status); ?>" type="checkbox" name="status[]" value="<?php echo sr_e($status); ?>" class="form-choice-toggle-input sr-only"<?php echo in_array($status, $selectedBannerStatuses, true) ? ' checked' : ''; ?>>
                                    <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                </label>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="admin-filter-field admin-banner-filter-target">
                        <label class="admin-filter-label"><?php echo sr_e(sr_t('banner::ui.text.76389a62')); ?></label>
                        <div class="btn-group" role="group" aria-label="<?php echo sr_e(sr_t('banner::ui.text.76389a62')); ?>">
                            <?php $bannerTargetOptions = [[sr_banner_public_target_option_value(), sr_t('banner::ui.banner.48de068b')]]; ?>
                            <?php foreach ($availableTargets as $target) { $bannerTargetOptions[] = [sr_banner_target_option_value($target), (string) $target['label']]; } ?>
                            <?php foreach ($bannerTargetOptions as $index => $targetOption) { ?>
                                <?php
                                $targetValue = (string) $targetOption[0];
                                $groupClass = $index === 0 ? 'btn-group-start' : ($index === count($bannerTargetOptions) - 1 ? 'btn-group-end' : 'btn-group-middle');
                                ?>
                                <label class="btn btn-choice-light <?php echo sr_e($groupClass); ?>" for="modules_banner_admin_banners_target_<?php echo sr_e((string) $index); ?>">
                                    <input id="modules_banner_admin_banners_target_<?php echo sr_e((string) $index); ?>" type="checkbox" name="target[]" value="<?php echo sr_e($targetValue); ?>" class="form-choice-toggle-input sr-only"<?php echo in_array($targetValue, $selectedBannerTargets, true) ? ' checked' : ''; ?>>
                                    <?php echo sr_e((string) $targetOption[1]); ?>
                                </label>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="admin-filter-field admin-banner-filter-field">
                        <label for="modules_banner_admin_banners_field" class="admin-filter-label"><?php echo sr_e(sr_t('banner::ui.search.b79bc9c8')); ?></label>
                        <select id="modules_banner_admin_banners_field" name="field" class="form-select admin-filter-input">
                            <?php foreach (['all' => sr_t('banner::ui.all.a4b69faf'), 'title' => sr_t('banner::ui.text.08b17e43'), 'link' => sr_t('banner::ui.text.3d54da9c')] as $fieldValue => $fieldLabel) { ?>
                                <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($filters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                                    <?php echo sr_e($fieldLabel); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="admin-filter-field admin-banner-filter-keyword">
                        <label for="modules_banner_admin_banners_q" class="admin-filter-label"><?php echo sr_e(sr_t('banner::ui.search.bda397fc')); ?></label>
                        <input id="modules_banner_admin_banners_q" type="text" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('banner::ui.text.b2273378')); ?>">
                    </div>
                    <button type="submit" class="btn btn-solid-primary admin-filter-submit"><?php echo sr_e(sr_t('banner::ui.search.4b8d541e')); ?></button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?php echo sr_e(sr_t('banner::ui.banner.list.f989d740')); ?></h2>
                <p class="admin-dashboard-meta"><?php echo sr_e(sr_t('banner::ui.active.status.banner.active.d22e3e06')); ?></p>
            </div>
            <a href="<?php echo sr_e(sr_url('/admin/banners/new')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo sr_e(sr_t('banner::ui.banner.c0e70d2c')); ?></a>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($bannerSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url($bannerSortOptions, $bannerDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="배너 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($bannerPagination); ?>
        </div>
        <div class="table-wrapper">
        <table class="table admin-banner-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('banner::ui.banner.list.f989d740')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th<?php echo sr_admin_sort_aria('title', $bannerSort); ?>><?php echo sr_admin_sort_header_html(sr_t('banner::ui.text.08b17e43'), 'title', $bannerSort, $bannerSortOptions, $bannerDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $bannerSort); ?>><?php echo sr_admin_sort_header_html(sr_t('banner::ui.status.e10195a1'), 'status', $bannerSort, $bannerSortOptions, $bannerDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('skin_key', $bannerSort); ?>><?php echo sr_admin_sort_header_html(sr_t('banner::ui.text.776b723f'), 'skin_key', $bannerSort, $bannerSortOptions, $bannerDefaultSort); ?></th>
                    <th><?php echo sr_e(sr_t('banner::ui.text.3d54da9c')); ?></th>
                    <th<?php echo sr_admin_sort_aria('click_count', $bannerSort); ?>><?php echo sr_admin_sort_header_html(sr_t('banner::ui.text.a6bb9eae'), 'click_count', $bannerSort, $bannerSortOptions, $bannerDefaultSort); ?></th>
                    <th><?php echo sr_e(sr_t('banner::ui.text.76389a62')); ?></th>
                    <th<?php echo sr_admin_sort_aria('starts_at', $bannerSort); ?>><?php echo sr_admin_sort_header_html(sr_t('banner::ui.text.65bdaefd'), 'starts_at', $bannerSort, $bannerSortOptions, $bannerDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('ends_at', $bannerSort); ?>><?php echo sr_admin_sort_header_html(sr_t('banner::ui.text.26c25fca'), 'ends_at', $bannerSort, $bannerSortOptions, $bannerDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('sort_order', $bannerSort); ?>><?php echo sr_admin_sort_header_html(sr_t('banner::ui.text.3788952d'), 'sort_order', $bannerSort, $bannerSortOptions, $bannerDefaultSort); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('banner::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($banners === []) { ?>
                    <tr>
                        <td colspan="10" class="admin-empty-state"><?php echo sr_e(sr_t('banner::ui.create.banner.a9744568')); ?></td>
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
                            <td class="admin-table-break admin-banner-title-cell"><?php echo sr_e((string) $banner['title']); ?></td>
                            <td class="admin-table-nowrap">
                                <span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($bannerStatus, 'content_status')); ?></span>
                                <?php if ($bannerStatus !== 'enabled') { ?>
                                    <br><small><?php echo sr_e(sr_t('banner::ui.active.b452ffcd')); ?></small>
                                <?php } ?>
                            </td>
                            <td class="admin-table-nowrap"><?php echo sr_e(sr_banner_skin_key(['banner_skin_key' => (string) ($banner['skin_key'] ?? 'basic')])); ?></td>
                            <td class="admin-table-break admin-banner-link-cell">
                                <?php echo sr_e(sr_banner_link_type_label((string) ($banner['link_url'] ?? ''))); ?><br>
                                <?php echo sr_e((string) ($banner['link_url'] ?? '')); ?>
                            </td>
                            <td class="admin-table-nowrap text-end"><?php echo sr_e(number_format((int) ($banner['click_count'] ?? 0))); ?></td>
                            <td class="admin-table-break admin-banner-target-cell"><?php echo sr_e($bannerTargetLabel); ?></td>
                            <td class="admin-table-nowrap admin-banner-date-cell"><?php echo sr_e((string) ($banner['starts_at'] ?? '-')); ?></td>
                            <td class="admin-table-nowrap admin-banner-date-cell"><?php echo sr_e((string) ($banner['ends_at'] ?? '-')); ?></td>
                            <td class="admin-table-nowrap text-end"><?php echo sr_e((string) $banner['sort_order']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <a href="<?php echo sr_e(sr_url('/admin/banners/edit?id=' . rawurlencode((string) $banner['id']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('banner::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('banner::ui.edit.3537f0cc')); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
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
    </section>
    <?php echo sr_admin_pagination_html($bannerPagination, '배너 목록 페이지'); ?>
<?php } ?>

<?php if ($bannerAdminPage === 'form') { ?>
    <?php foreach ($bannerHelp as $bannerHelpModal) { ?>
        <?php echo sr_admin_help_modal_html((string) $bannerHelpModal['id'], (string) $bannerHelpModal['title'], (string) $bannerHelpModal['body']); ?>
    <?php } ?>
<?php } ?>

<?php if ($bannerAdminPage === 'form') { ?>
    <script>
    (function () {
        var form = document.querySelector('[data-admin-subject-form]');
        if (!form) {
            return;
        }

        var target = form.querySelector('select[name="target_option"]');
        var match = form.querySelector('select[name="match_type"]');
        var subject = form.querySelector('[data-admin-subject-id]');
        var label = form.querySelector('[data-admin-subject-required]');
        var publicTarget = form.getAttribute('data-public-target-value') || '';

        function syncSubjectRequired() {
            var needed = !!(target && match && target.value !== publicTarget && match.value === 'exact');
            if (label) {
                label.hidden = !needed;
            }
            if (subject) {
                subject.required = needed;
            }
        }

        form.addEventListener('change', function (event) {
            if (event.target === target || event.target === match) {
                syncSubjectRequired();
            }
        });
        syncSubjectRequired();
    })();
    </script>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
