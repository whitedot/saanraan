<?php

$bannerAdminPage = isset($bannerAdminPage) ? (string) $bannerAdminPage : 'list';
$editing = is_array($editBanner);
$adminPageTitle = $bannerAdminPage === 'form' ? ($editing ? sr_t('banner::ui.banner.edit.52756afa') : sr_t('banner::ui.banner.b0dbbde9')) : sr_t('banner::ui.banner.63182d60');
$adminPageSubtitle = $bannerAdminPage === 'form' ? sr_t('banner::ui.banner.71184934') : sr_t('banner::ui.banner.status.search.ae378c83');
$adminContainerClass = $bannerAdminPage === 'form' ? 'admin-page-banner-form admin-ui-scope' : 'admin-page-banner-list admin-ui-scope';
$filters = isset($filters) && is_array($filters) ? $filters : ['status' => '', 'target' => '', 'field' => 'all', 'q' => ''];
$bannerStatusCounts = isset($bannerStatusCounts) && is_array($bannerStatusCounts) ? $bannerStatusCounts : [];
$totalBanners = (int) ($bannerStatusCounts['total'] ?? count($banners ?? []));
$selectedTargetOption = sr_banner_public_target_option_value();
if ($editing && (string) ($editBanner['module_key'] ?? '') !== '') {
    $selectedTargetOption = (string) ($editBanner['module_key'] ?? '') . '|' . (string) ($editBanner['point_key'] ?? '') . '|' . (string) ($editBanner['slot_key'] ?? '');
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($bannerAdminPage === 'form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/banners/save')); ?>" enctype="multipart/form-data" class="admin-form ui-form-theme">
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
                <label class="form-label" for="banner_admin_banners_link_url"><?php echo sr_e(sr_t('banner::ui.url.http.https.81cff7be')); ?></label>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_link_url" type="text" name="link_url" value="<?php echo $editing ? sr_e((string) $editBanner['link_url']) : ''; ?>" class="form-input form-control-full" maxlength="255">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_image_url"><?php echo sr_e(sr_t('banner::ui.url.http.https.url.264bd3d3')); ?></label>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_image_url" type="text" name="image_url" value="<?php echo $editing ? sr_e((string) $editBanner['image_url']) : ''; ?>" class="form-input form-control-full" maxlength="255">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_image_upload"><?php echo sr_e(sr_t('banner::ui.text.cead00a8')); ?></label>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_image_upload" type="file" name="image_upload" accept="image/jpeg,image/png,image/webp" class="form-input">
                    <br>
                                    <small><?php echo sr_e(sr_t('banner::ui.jpeg.png.webp.6252010a')); ?> <?php echo sr_e(sr_banner_format_bytes(sr_banner_image_upload_max_bytes())); ?><?php echo sr_e(sr_t('banner::ui.text.58609b0c')); ?></small>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_target_option"><?php echo sr_e(sr_t('banner::ui.text.76389a62')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('banner::ui.required.1f227c67')); ?></span></label>
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
                <label class="form-label" for="banner_admin_banners_match_type"><?php echo sr_e(sr_t('banner::ui.text.175f56ba')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('banner::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <select id="banner_admin_banners_match_type" name="match_type" class="form-select">
                                            <?php foreach ($allowedMatchTypes as $matchType) { ?>
                                                <?php $currentMatchType = $editing ? (string) ($editBanner['match_type'] ?? 'all') : 'all'; ?>
                                                <option value="<?php echo sr_e($matchType); ?>"<?php echo $currentMatchType === $matchType ? ' selected' : ''; ?>>
                                                    <?php echo sr_e(sr_admin_code_label($matchType, 'match_type')); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_subject_id"><?php echo sr_e(sr_t('banner::ui.subject.id.14852174')); ?></label>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_subject_id" type="text" name="subject_id" value="<?php echo $editing ? sr_e((string) ($editBanner['subject_id'] ?? '')) : ''; ?>" class="form-input" maxlength="80">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_status"><?php echo sr_e(sr_t('banner::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('banner::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <select id="banner_admin_banners_status" name="status" class="form-select">
                                            <?php foreach ($allowedStatuses as $status) { ?>
                                                <?php $currentStatus = $editing ? (string) $editBanner['status'] : 'draft'; ?>
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
                <label class="form-label" for="banner_admin_banners_skin_key"><?php echo sr_e(sr_t('banner::ui.banner.46b4fae5')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('banner::ui.required.1f227c67')); ?></span></label>
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
                <label class="form-label" for="banner_admin_banners_starts_at"><?php echo sr_e(sr_t('banner::ui.text.65bdaefd')); ?></label>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_starts_at" type="datetime-local" name="starts_at" value="<?php echo $editing ? sr_e(sr_banner_admin_datetime_value($editBanner['starts_at'] ?? null)) : ''; ?>" class="form-input">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_ends_at"><?php echo sr_e(sr_t('banner::ui.text.26c25fca')); ?></label>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_ends_at" type="datetime-local" name="ends_at" value="<?php echo $editing ? sr_e(sr_banner_admin_datetime_value($editBanner['ends_at'] ?? null)) : ''; ?>" class="form-input">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="banner_admin_banners_sort_order"><?php echo sr_e(sr_t('banner::ui.text.3788952d')); ?></label>
                <div class="admin-form-field">
                    <input id="banner_admin_banners_sort_order" type="number" name="sort_order" value="<?php echo $editing ? sr_e((string) $editBanner['sort_order']) : '100'; ?>" class="form-input">
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

    <form method="get" action="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="admin-filter admin-banner-filter ui-form-theme">
        <div class="admin-filter-grid admin-banner-search-grid">
            <div class="admin-filter-field admin-banner-filter-status">
                <label for="modules_banner_admin_banners_status" class="admin-filter-label"><?php echo sr_e(sr_t('banner::ui.status.e10195a1')); ?></label>
                <select id="modules_banner_admin_banners_status" name="status" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($filters['status'] ?? '') === '' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('banner::ui.all.a4b69faf')); ?></option>
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($filters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-banner-filter-target">
                <label for="modules_banner_admin_banners_target" class="admin-filter-label"><?php echo sr_e(sr_t('banner::ui.text.76389a62')); ?></label>
                <select id="modules_banner_admin_banners_target" name="target" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($filters['target'] ?? '') === '' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('banner::ui.all.a4b69faf')); ?></option>
                    <option value="<?php echo sr_e(sr_banner_public_target_option_value()); ?>"<?php echo (string) ($filters['target'] ?? '') === sr_banner_public_target_option_value() ? ' selected' : ''; ?>><?php echo sr_e(sr_t('banner::ui.banner.48de068b')); ?></option>
                    <?php foreach ($availableTargets as $target) { ?>
                        <?php $optionValue = sr_banner_target_option_value($target); ?>
                        <option value="<?php echo sr_e($optionValue); ?>"<?php echo (string) ($filters['target'] ?? '') === $optionValue ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $target['label']); ?>
                        </option>
                    <?php } ?>
                </select>
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
                <input id="modules_banner_admin_banners_q" type="search" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('banner::ui.text.b2273378')); ?>">
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
            <a href="<?php echo sr_e(sr_url('/admin/banners/new')); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('banner::ui.banner.c0e70d2c')); ?></a>
        </div>
        <div class="table-wrapper">
        <table class="table admin-banner-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('banner::ui.banner.list.f989d740')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th><?php echo sr_e(sr_t('banner::ui.text.08b17e43')); ?></th>
                    <th><?php echo sr_e(sr_t('banner::ui.status.e10195a1')); ?></th>
                    <th><?php echo sr_e(sr_t('banner::ui.text.776b723f')); ?></th>
                    <th><?php echo sr_e(sr_t('banner::ui.text.3d54da9c')); ?></th>
                    <th><?php echo sr_e(sr_t('banner::ui.text.a6bb9eae')); ?></th>
                    <th><?php echo sr_e(sr_t('banner::ui.text.76389a62')); ?></th>
                    <th><?php echo sr_e(sr_t('banner::ui.text.b918d5af')); ?></th>
                    <th><?php echo sr_e(sr_t('banner::ui.text.3788952d')); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('banner::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($banners === []) { ?>
                    <tr>
                        <td colspan="9" class="admin-empty-state"><?php echo sr_e(sr_t('banner::ui.create.banner.a9744568')); ?></td>
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
                            <td class="admin-table-nowrap admin-banner-date-cell">
                                <?php echo sr_e((string) ($banner['starts_at'] ?? '-')); ?><br>
                                <?php echo sr_e((string) ($banner['ends_at'] ?? '-')); ?>
                            </td>
                            <td class="admin-table-nowrap text-end"><?php echo sr_e((string) $banner['sort_order']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <a href="<?php echo sr_e(sr_url('/admin/banners/edit?id=' . rawurlencode((string) $banner['id']))); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('banner::ui.edit.3537f0cc')); ?></a>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/banners/delete')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="banner_id" value="<?php echo sr_e((string) $banner['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo sr_e(sr_t('banner::ui.delete.6139b6c3')); ?></button>
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
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
