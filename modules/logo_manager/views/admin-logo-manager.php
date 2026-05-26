<?php

$adminPageTitle = sr_t('logo_manager::ui.text.e046e24f');
$adminPageSubtitle = sr_t('logo_manager::ui.text.52f5a11e');
$adminContainerClass = 'admin-page-logo-manager admin-ui-scope';
$assetSort = isset($assetSort) && is_array($assetSort) ? $assetSort : sr_admin_logo_asset_default_sort();
$assignmentSort = isset($assignmentSort) && is_array($assignmentSort) ? $assignmentSort : sr_admin_logo_assignment_default_sort();
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<section class="admin-card card logo-manager-current">
    <div class="card-header">
        <div>
            <h2 class="card-title"><?php echo sr_e(sr_t('logo_manager::ui.text.25b72af8')); ?></h2>
            <p class="admin-dashboard-meta"><?php echo sr_e(sr_t('logo_manager::ui.active.status.99c1cd06')); ?></p>
        </div>
    </div>
    <div class="card-body logo-manager-current-grid">
        <?php foreach ($usageOptions as $usageKey => $usageOption) { ?>
            <?php $active = is_array($activeAssignments[$usageKey] ?? null) ? $activeAssignments[$usageKey] : null; ?>
            <article class="logo-manager-current-item">
                <strong><?php echo sr_e((string) ($usageOption['label'] ?? $usageKey)); ?></strong>
                <?php if ($active !== null) { ?>
                    <img src="<?php echo sr_e(sr_logo_manager_url_for_output(sr_logo_manager_asset_url($active))); ?>" alt="" loading="lazy" decoding="async">
                    <span><?php echo sr_e((string) ($active['title'] ?? '')); ?></span>
                    <small><?php echo sr_e((string) ($active['starts_at'] ?? sr_t('logo_manager::ui.text.8902fb48'))); ?> - <?php echo sr_e((string) ($active['ends_at'] ?? sr_t('logo_manager::ui.text.8902fb48'))); ?></small>
                <?php } else { ?>
                    <span class="logo-manager-empty"><?php echo sr_e(sr_t('logo_manager::ui.text.8789bbce')); ?></span>
                    <small><?php echo sr_e(sr_t('logo_manager::ui.admin.name.5a5ca3bf')); ?></small>
                <?php } ?>
            </article>
        <?php } ?>
    </div>
</section>

<div id="logo-manager-upload-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="logo-manager-upload-modal-label" aria-hidden="true" inert>
    <div class="modal-dialog modal-dialog-lg">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/logo-manager')); ?>" enctype="multipart/form-data" class="modal-content ui-form-theme">
            <div class="modal-header">
                <h3 id="logo-manager-upload-modal-label" class="modal-title"><?php echo sr_e(sr_t('logo_manager::ui.create.d7f97185')); ?></h3>
                <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('logo_manager::ui.close.1e8c1020')); ?>" data-overlay="#logo-manager-upload-modal">
                    <?php echo sr_material_icon_html('close', '', sr_t('logo_manager::ui.close.1e8c1020')); ?>
                </button>
            </div>
            <div class="modal-body">
                <p class="admin-dashboard-meta"><?php echo sr_e(sr_t('logo_manager::ui.jpeg.png.25d6091b')); ?></p>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="upload_asset">
                <div class="admin-form-row">
                    <label class="form-label" for="logo_manager_usage_key"><?php echo sr_e(sr_t('logo_manager::ui.text.dfa401cd')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('logo_manager::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <select id="logo_manager_usage_key" name="usage_key" class="form-select" data-overlay-focus>
                            <?php foreach ($usageOptions as $usageKey => $usageOption) { ?>
                                <option value="<?php echo sr_e((string) $usageKey); ?>"><?php echo sr_e((string) ($usageOption['label'] ?? $usageKey)); ?></option>
                            <?php } ?>
                        </select>
                        <small class="admin-form-help"><?php echo sr_e(sr_t('logo_manager::ui.text.9792ca9f')); ?></small>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="logo_manager_title"><?php echo sr_e(sr_t('logo_manager::ui.name.2b2c54b5')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('logo_manager::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="logo_manager_title" type="text" name="title" class="form-input form-control-full" maxlength="120" required>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="logo_manager_alt_text"><?php echo sr_e(sr_t('logo_manager::ui.text.c2f4a315')); ?></label>
                    <div class="admin-form-field">
                        <input id="logo_manager_alt_text" type="text" name="alt_text" value="<?php echo sr_e($logoManagerDefaultAltText); ?>" class="form-input form-control-full" maxlength="160">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="logo_manager_link_url"><?php echo sr_e(sr_t('logo_manager::ui.url.f7ca9b13')); ?></label>
                    <div class="admin-form-field">
                        <input id="logo_manager_link_url" type="text" name="link_url" class="form-input form-control-full" maxlength="255" placeholder="<?php echo sr_e(sr_t('logo_manager::ui.https.example.com.9a232e78')); ?>">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="logo_manager_logo_file"><?php echo sr_e(sr_t('logo_manager::ui.text.4becf8bb')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('logo_manager::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="logo_manager_logo_file" type="file" name="logo_file" accept="image/jpeg,image/png,image/webp,image/svg+xml,.svg" class="form-input" required>
                        <small class="admin-form-help"><?php echo sr_e(sr_t('logo_manager::ui.1.save.save.cfcc4930')); ?></small>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="logo_manager_starts_at"><?php echo sr_e(sr_t('logo_manager::ui.text.65bdaefd')); ?></label>
                    <div class="admin-form-field">
                        <input id="logo_manager_starts_at" type="datetime-local" name="starts_at" class="form-input">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="logo_manager_ends_at"><?php echo sr_e(sr_t('logo_manager::ui.text.26c25fca')); ?></label>
                    <div class="admin-form-field">
                        <input id="logo_manager_ends_at" type="datetime-local" name="ends_at" class="form-input">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="logo_manager_sort_order"><?php echo sr_e(sr_t('logo_manager::ui.text.3788952d')); ?></label>
                    <div class="admin-form-field">
                        <input id="logo_manager_sort_order" type="number" name="sort_order" value="100" class="form-input">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#logo-manager-upload-modal"><?php echo sr_e(sr_t('logo_manager::ui.close.1e8c1020')); ?></button>
                <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('logo_manager::ui.text.2aa1d541')); ?></button>
            </div>
        </form>
    </div>
</div>

<div id="logo-manager-assignment-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="logo-manager-assignment-modal-label" aria-hidden="true" inert>
    <div class="modal-dialog modal-dialog-lg">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/logo-manager')); ?>" class="modal-content ui-form-theme">
            <div class="modal-header">
                <h3 id="logo-manager-assignment-modal-label" class="modal-title"><?php echo sr_e(sr_t('logo_manager::ui.text.610b141a')); ?></h3>
                <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('logo_manager::ui.close.1e8c1020')); ?>" data-overlay="#logo-manager-assignment-modal">
                    <?php echo sr_material_icon_html('close', '', sr_t('logo_manager::ui.close.1e8c1020')); ?>
                </button>
            </div>
            <div class="modal-body">
                <p class="admin-dashboard-meta"><?php echo sr_e(sr_t('logo_manager::ui.create.c3801215')); ?></p>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="save_assignment">
                <div class="admin-form-row">
                    <label class="form-label" for="logo_manager_assignment_asset"><?php echo sr_e(sr_t('logo_manager::ui.text.93360d4f')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('logo_manager::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <select id="logo_manager_assignment_asset" name="asset_id" class="form-select" required data-overlay-focus>
                            <option value=""><?php echo sr_e(sr_t('logo_manager::ui.select.8d1a750c')); ?></option>
                            <?php foreach ($assets as $asset) { ?>
                                <?php if ((string) ($asset['status'] ?? '') !== 'active') { continue; } ?>
                                <option value="<?php echo sr_e((string) $asset['id']); ?>"><?php echo sr_e('#' . (string) $asset['id'] . ' ' . (string) $asset['title']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="logo_manager_assignment_usage"><?php echo sr_e(sr_t('logo_manager::ui.text.dfa401cd')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('logo_manager::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <select id="logo_manager_assignment_usage" name="usage_key" class="form-select">
                            <?php foreach ($usageOptions as $usageKey => $usageOption) { ?>
                                <option value="<?php echo sr_e((string) $usageKey); ?>"><?php echo sr_e((string) ($usageOption['label'] ?? $usageKey)); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <span class="form-label"><?php echo sr_e(sr_t('logo_manager::ui.active.93c558d7')); ?></span>
                    <div class="admin-form-field">
                        <label class="admin-form-check form-label" for="logo_manager_assignment_status_enabled">
                            <input id="logo_manager_assignment_status_enabled" type="checkbox" name="status_enabled" value="1" class="form-switch" checked>
                            <?php echo sr_admin_choice_label_html(sr_t('logo_manager::ui.active.93c558d7')); ?>
                        </label>
                        <small class="admin-form-help"><?php echo sr_e(sr_t('logo_manager::ui.status.save.8ca69925')); ?></small>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="logo_manager_assignment_alt_text"><?php echo sr_e(sr_t('logo_manager::ui.text.c2f4a315')); ?></label>
                    <div class="admin-form-field">
                        <input id="logo_manager_assignment_alt_text" type="text" name="alt_text" value="<?php echo sr_e($logoManagerDefaultAltText); ?>" class="form-input form-control-full" maxlength="160">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="logo_manager_assignment_link_url"><?php echo sr_e(sr_t('logo_manager::ui.url.f7ca9b13')); ?></label>
                    <div class="admin-form-field">
                        <input id="logo_manager_assignment_link_url" type="text" name="link_url" class="form-input form-control-full" maxlength="255">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="logo_manager_assignment_starts_at"><?php echo sr_e(sr_t('logo_manager::ui.text.65bdaefd')); ?></label>
                    <div class="admin-form-field">
                        <input id="logo_manager_assignment_starts_at" type="datetime-local" name="starts_at" class="form-input">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="logo_manager_assignment_ends_at"><?php echo sr_e(sr_t('logo_manager::ui.text.26c25fca')); ?></label>
                    <div class="admin-form-field">
                        <input id="logo_manager_assignment_ends_at" type="datetime-local" name="ends_at" class="form-input">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="logo_manager_assignment_sort_order"><?php echo sr_e(sr_t('logo_manager::ui.text.3788952d')); ?></label>
                    <div class="admin-form-field">
                        <input id="logo_manager_assignment_sort_order" type="number" name="sort_order" value="100" class="form-input">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#logo-manager-assignment-modal"><?php echo sr_e(sr_t('logo_manager::ui.close.1e8c1020')); ?></button>
                <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('logo_manager::ui.text.124c609c')); ?></button>
            </div>
        </form>
    </div>
</div>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <div>
            <h2 class="card-title"><?php echo sr_e(sr_t('logo_manager::ui.text.93360d4f')); ?></h2>
            <p class="admin-dashboard-meta"><?php echo sr_e(sr_t('logo_manager::ui.status.active.f4b40b50')); ?></p>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="logo-manager-upload-modal" data-overlay="#logo-manager-upload-modal"><?php echo sr_e(sr_t('logo_manager::ui.create.d7f97185')); ?></button>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($assetSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url($assetSortOptions, $assetDefaultSort, '', '', 'asset_sort', 'asset_dir', 'asset_page')); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="로고 자산 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php echo sr_admin_pagination_summary_html($assetPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table logo-manager-assets-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('logo_manager::ui.list.5caa43ae')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th><?php echo sr_e(sr_t('logo_manager::ui.text.dcb06beb')); ?></th>
                    <th<?php echo sr_admin_sort_aria('title', $assetSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.name.253d1510'), 'title', $assetSort, $assetSortOptions, $assetDefaultSort, 'asset_sort', 'asset_dir', 'asset_page'); ?></th>
                    <th<?php echo sr_admin_sort_aria('usage_key', $assetSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.text.dfa401cd'), 'usage_key', $assetSort, $assetSortOptions, $assetDefaultSort, 'asset_sort', 'asset_dir', 'asset_page'); ?></th>
                    <th<?php echo sr_admin_sort_aria('size_bytes', $assetSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.text.82232621'), 'size_bytes', $assetSort, $assetSortOptions, $assetDefaultSort, 'asset_sort', 'asset_dir', 'asset_page'); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $assetSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.status.e10195a1'), 'status', $assetSort, $assetSortOptions, $assetDefaultSort, 'asset_sort', 'asset_dir', 'asset_page'); ?></th>
                    <th<?php echo sr_admin_sort_aria('created_at', $assetSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.create.7aeae93b'), 'created_at', $assetSort, $assetSortOptions, $assetDefaultSort, 'asset_sort', 'asset_dir', 'asset_page'); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('logo_manager::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($assets === []) { ?>
                    <tr><td colspan="7" class="admin-empty-state"><?php echo sr_e(sr_t('logo_manager::ui.create.29c6b901')); ?></td></tr>
                <?php } else { ?>
                    <?php foreach ($assets as $asset) { ?>
                        <tr>
                            <td><img class="logo-manager-thumb" src="<?php echo sr_e(sr_logo_manager_url_for_output(sr_logo_manager_asset_url($asset))); ?>" alt="" loading="lazy" decoding="async"></td>
                            <td class="admin-table-break"><?php echo sr_e((string) $asset['title']); ?></td>
                            <td><?php echo sr_e(sr_logo_manager_usage_label((string) $asset['usage_key'])); ?></td>
                            <td><?php echo sr_e((string) $asset['width']); ?>x<?php echo sr_e((string) $asset['height']); ?><br><small><?php echo sr_e(sr_logo_manager_format_bytes((int) $asset['size_bytes'])); ?></small></td>
                            <td><span class="admin-status <?php echo (string) $asset['status'] === 'active' ? 'is-normal' : 'is-left'; ?>"><?php echo sr_e(sr_logo_manager_status_label((string) $asset['status'])); ?></span></td>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) $asset['created_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/logo-manager')); ?>" class="admin-inline-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="asset_status">
                                        <input type="hidden" name="asset_id" value="<?php echo sr_e((string) $asset['id']); ?>">
                                        <input type="hidden" name="status" value="<?php echo (string) $asset['status'] === 'active' ? 'archived' : 'active'; ?>">
                                        <button type="submit" class="btn btn-sm btn-solid-light"><?php echo (string) $asset['status'] === 'active' ? sr_t('logo_manager::ui.text.2e4099ba') : sr_t('logo_manager::ui.active.93c558d7'); ?></button>
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
<?php echo sr_admin_pagination_html($assetPagination, '로고 자산 목록 페이지'); ?>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <div>
            <h2 class="card-title"><?php echo sr_e(sr_t('logo_manager::ui.text.470ae903')); ?></h2>
            <p class="admin-dashboard-meta"><?php echo sr_e(sr_t('logo_manager::ui.text.e98faae0')); ?></p>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="logo-manager-assignment-modal" data-overlay="#logo-manager-assignment-modal"><?php echo sr_e(sr_t('logo_manager::ui.text.e3241f70')); ?></button>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($assignmentSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url($assignmentSortOptions, $assignmentDefaultSort, '', '', 'assignment_sort', 'assignment_dir', 'assignment_page')); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="로고 적용 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php echo sr_admin_pagination_summary_html($assignmentPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table logo-manager-assignments-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('logo_manager::ui.list.4b760897')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th<?php echo sr_admin_sort_aria('usage_key', $assignmentSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.text.dfa401cd'), 'usage_key', $assignmentSort, $assignmentSortOptions, $assignmentDefaultSort, 'assignment_sort', 'assignment_dir', 'assignment_page'); ?></th>
                    <th<?php echo sr_admin_sort_aria('title', $assignmentSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.text.ac97396d'), 'title', $assignmentSort, $assignmentSortOptions, $assignmentDefaultSort, 'assignment_sort', 'assignment_dir', 'assignment_page'); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $assignmentSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.status.e10195a1'), 'status', $assignmentSort, $assignmentSortOptions, $assignmentDefaultSort, 'assignment_sort', 'assignment_dir', 'assignment_page'); ?></th>
                    <th<?php echo sr_admin_sort_aria('starts_at', $assignmentSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.text.b918d5af'), 'starts_at', $assignmentSort, $assignmentSortOptions, $assignmentDefaultSort, 'assignment_sort', 'assignment_dir', 'assignment_page'); ?></th>
                    <th<?php echo sr_admin_sort_aria('sort_order', $assignmentSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.text.3788952d'), 'sort_order', $assignmentSort, $assignmentSortOptions, $assignmentDefaultSort, 'assignment_sort', 'assignment_dir', 'assignment_page'); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('logo_manager::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($assignments === []) { ?>
                    <tr><td colspan="6" class="admin-empty-state"><?php echo sr_e(sr_t('logo_manager::ui.create.0a600835')); ?></td></tr>
                <?php } else { ?>
                    <?php foreach ($assignments as $assignment) { ?>
                        <tr>
                            <td><?php echo sr_e(sr_logo_manager_usage_label((string) $assignment['usage_key'])); ?></td>
                            <td class="admin-table-break">
                                <img class="logo-manager-thumb" src="<?php echo sr_e(sr_logo_manager_url_for_output(sr_logo_manager_asset_url($assignment))); ?>" alt="" loading="lazy" decoding="async">
                                <?php echo sr_e((string) $assignment['title']); ?>
                            </td>
                            <td><span class="admin-status <?php echo (string) $assignment['status'] === 'active' ? 'is-normal' : 'is-left'; ?>"><?php echo sr_e(sr_logo_manager_status_label((string) $assignment['status'])); ?></span></td>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) ($assignment['starts_at'] ?? sr_t('logo_manager::ui.text.8902fb48'))); ?><br><?php echo sr_e((string) ($assignment['ends_at'] ?? sr_t('logo_manager::ui.text.8902fb48'))); ?></td>
                            <td><?php echo sr_e((string) $assignment['sort_order']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/logo-manager')); ?>" class="admin-inline-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="assignment_status">
                                        <input type="hidden" name="assignment_id" value="<?php echo sr_e((string) $assignment['id']); ?>">
                                        <input type="hidden" name="status" value="<?php echo (string) $assignment['status'] === 'active' ? 'disabled' : 'active'; ?>">
                                        <button type="submit" class="btn btn-sm btn-solid-light"><?php echo (string) $assignment['status'] === 'active' ? sr_t('logo_manager::ui.text.92cdef3c') : sr_t('logo_manager::ui.active.93c558d7'); ?></button>
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
<?php echo sr_admin_pagination_html($assignmentPagination, '로고 적용 목록 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
