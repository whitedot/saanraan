<?php

$adminPageTitle = sr_t('logo_manager::ui.text.e046e24f');
$adminPageSubtitle = sr_t('logo_manager::ui.text.52f5a11e');
$adminContainerClass = 'admin-page-logo-manager admin-ui-scope';
$logoSortOptions = sr_admin_logo_sort_options();
$logoDefaultSort = sr_admin_logo_default_sort();
$logoSort = isset($logoSort) && is_array($logoSort) ? $logoSort : $logoDefaultSort;
$logoManagerCurrentQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
$logoManagerActionSuffix = $logoManagerCurrentQuery !== '' ? '?' . $logoManagerCurrentQuery : '';
$logoManagerIconSizeOptions = sr_logo_manager_icon_size_options();
$logoManagerDefaultIconKeys = sr_logo_manager_default_icon_variant_keys();
$logoManagerNow = is_string($logoManagerNow ?? null) ? $logoManagerNow : sr_now();
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<section class="admin-card card logo-manager-current">
    <div class="card-header">
        <div>
            <h2 class="card-title"><?php echo sr_e(sr_t('logo_manager::ui.text.25b72af8')); ?></h2>
            <p class="admin-dashboard-meta"><?php echo sr_e(sr_t('logo_manager::ui.active.status.99c1cd06')); ?></p>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="logo-manager-logo-modal" data-overlay="#logo-manager-logo-modal"><?php echo sr_e(sr_t('logo_manager::ui.logo.add')); ?></button>
    </div>
    <div class="card-body logo-manager-current-grid">
        <?php foreach ($positionOptions as $positionKey => $positionOption) { ?>
            <?php $active = is_array($activeLogos[$positionKey] ?? null) ? $activeLogos[$positionKey] : null; ?>
            <article class="logo-manager-current-item">
                <strong><?php echo sr_e((string) ($positionOption['label'] ?? $positionKey)); ?></strong>
                <?php if ($active !== null) { ?>
                    <img src="<?php echo sr_e(sr_logo_manager_url_for_output(sr_logo_manager_logo_url($active))); ?>" alt="" loading="lazy" decoding="async">
                    <span><?php echo sr_e((string) ($active['title'] ?? '')); ?></span>
                    <?php if ((string) $positionKey === sr_logo_manager_public_symbol_position_key() && !empty($active['use_as_public_symbol'])) { ?>
                        <small><?php echo sr_e(sr_t('logo_manager::ui.public_symbol.yes')); ?></small>
                    <?php } ?>
                    <small><?php echo sr_e((string) ($active['starts_at'] ?? sr_t('logo_manager::ui.text.8902fb48'))); ?> - <?php echo sr_e((string) ($active['ends_at'] ?? sr_t('logo_manager::ui.text.8902fb48'))); ?></small>
                <?php } else { ?>
                    <span class="logo-manager-empty"><?php echo sr_e(sr_t('logo_manager::ui.text.8789bbce')); ?></span>
                    <small><?php echo sr_e((string) ($positionOption['hint'] ?? sr_t('logo_manager::ui.position.empty_help'))); ?></small>
                <?php } ?>
            </article>
        <?php } ?>
    </div>
</section>

<div id="logo-manager-logo-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="logo-manager-logo-modal-label" aria-hidden="true" inert>
    <div class="modal-dialog modal-dialog-lg">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/logo-manager' . $logoManagerActionSuffix)); ?>" enctype="multipart/form-data" class="modal-content ui-form-theme">
            <div class="modal-header">
                <h3 id="logo-manager-logo-modal-label" class="modal-title"><?php echo sr_e(sr_t('logo_manager::ui.logo.add')); ?></h3>
                <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('logo_manager::ui.close.1e8c1020')); ?>" data-overlay="#logo-manager-logo-modal">
                    <?php echo sr_material_icon_html('close', '', sr_t('logo_manager::ui.close.1e8c1020')); ?>
                </button>
            </div>
            <div class="modal-body">
                <p class="admin-dashboard-meta"><?php echo sr_e(sr_t('logo_manager::ui.logo.form_help')); ?></p>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="create_logo">
                <div class="admin-form-row">
                    <label class="form-label" for="logo_manager_position_key"><?php echo sr_e(sr_t('logo_manager::ui.position.label')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('logo_manager::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <select id="logo_manager_position_key" name="position_key" class="form-select" data-overlay-focus required data-logo-manager-position-select>
                            <?php foreach ($positionOptions as $positionKey => $positionOption) { ?>
                                <option value="<?php echo sr_e((string) $positionKey); ?>"><?php echo sr_e((string) ($positionOption['label'] ?? $positionKey)); ?></option>
                            <?php } ?>
                        </select>
                        <small class="admin-form-help"><?php echo sr_e(sr_t('logo_manager::ui.position.help')); ?></small>
                    </div>
                </div>
                <div class="admin-form-row" data-logo-manager-public-symbol-row>
                    <span class="form-label"><?php echo sr_e(sr_t('logo_manager::ui.public_symbol.label')); ?></span>
                    <div class="admin-form-field">
                        <label class="admin-form-check form-label" for="logo_manager_use_as_public_symbol">
                            <input id="logo_manager_use_as_public_symbol" type="checkbox" name="use_as_public_symbol" value="1" class="form-switch form-choice-dark" data-logo-manager-public-symbol-switch>
                            <?php echo sr_admin_choice_label_html(sr_t('logo_manager::ui.public_symbol.label')); ?>
                        </label>
                        <small class="admin-form-help"><?php echo sr_e(sr_t('logo_manager::ui.public_symbol.help')); ?></small>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="logo_manager_title"><?php echo sr_e(sr_t('logo_manager::ui.name.2b2c54b5')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('logo_manager::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="logo_manager_title" type="text" name="title" class="form-input form-control-full" maxlength="120" required>
                    </div>
                </div>
                <div class="admin-form-row">
                    <span class="form-label"><?php echo sr_e(sr_t('logo_manager::ui.active.93c558d7')); ?></span>
                    <div class="admin-form-field">
                        <label class="admin-form-check form-label" for="logo_manager_status_enabled">
                            <input id="logo_manager_status_enabled" type="checkbox" name="status_enabled" value="1" class="form-switch form-choice-dark" checked>
                            <?php echo sr_admin_choice_label_html(sr_t('logo_manager::ui.active.93c558d7')); ?>
                        </label>
                        <small class="admin-form-help"><?php echo sr_e(sr_t('logo_manager::ui.status.save.8ca69925')); ?></small>
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
                        <small class="admin-form-help"><?php echo sr_e(sr_t('logo_manager::ui.period.help')); ?></small>
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
                        <small class="admin-form-help"><?php echo sr_e(sr_t('logo_manager::ui.sort.help')); ?></small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#logo-manager-logo-modal"><?php echo sr_e(sr_t('logo_manager::ui.close.1e8c1020')); ?></button>
                <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('logo_manager::ui.logo.save')); ?></button>
            </div>
        </form>
    </div>
</div>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <div>
            <h2 class="card-title"><?php echo sr_e(sr_t('logo_manager::ui.logo.list')); ?></h2>
            <p class="admin-dashboard-meta"><?php echo sr_e(sr_t('logo_manager::ui.text.e98faae0')); ?></p>
            <p class="admin-dashboard-meta"><?php echo sr_e(sr_t('logo_manager::ui.favicon.status_help')); ?></p>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="logo-manager-logo-modal" data-overlay="#logo-manager-logo-modal"><?php echo sr_e(sr_t('logo_manager::ui.logo.add')); ?></button>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($logoSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url($logoSortOptions, $logoDefaultSort, '', '', 'logo_sort', 'logo_dir', 'logo_page')); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="로고 배치 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <form id="logo-manager-bulk-status-form" method="post" action="<?php echo sr_e(sr_url('/admin/logo-manager' . $logoManagerActionSuffix)); ?>" class="logo-manager-bulk-form" data-logo-manager-bulk-form>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="batch_status">
            <input type="hidden" name="operation_key" value="logo_manager.set_status">
            <div class="logo-manager-bulk-actions admin-row-actions" data-logo-manager-bulk-bar>
                <div class="logo-manager-bulk-controls admin-row-actions">
                    <button type="submit" name="target_status" value="active" class="btn btn-sm btn-outline-warning" data-logo-manager-bulk-submit data-status-label="<?php echo sr_e(sr_t('logo_manager::ui.active.93c558d7')); ?>" disabled><?php echo sr_e(sr_t('logo_manager::ui.active.93c558d7')); ?></button>
                    <button type="submit" name="target_status" value="disabled" class="btn btn-sm btn-outline-warning" data-logo-manager-bulk-submit data-status-label="<?php echo sr_e(sr_t('logo_manager::ui.text.92cdef3c')); ?>" disabled><?php echo sr_e(sr_t('logo_manager::ui.text.92cdef3c')); ?></button>
                    <button type="button" class="btn btn-sm btn-outline-light" data-logo-manager-bulk-clear aria-label="선택 해제" title="선택 해제" hidden><?php echo sr_material_icon_html('close'); ?><span data-logo-manager-selected-count>0</span></button>
                </div>
            </div>
        </form>
        <?php echo sr_admin_pagination_summary_html($logoPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table logo-manager-assignments-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('logo_manager::ui.logo.list')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th class="admin-table-checkbox-cell logo-manager-select-cell">
                        <label class="sr-only" for="logo_manager_bulk_select_all">현재 페이지 로고 배치 전체 선택</label>
                        <input id="logo_manager_bulk_select_all" type="checkbox" class="form-checkbox" data-logo-manager-select-all<?php echo $logos === [] ? ' disabled' : ''; ?>>
                    </th>
                    <th<?php echo sr_admin_sort_aria('position_key', $logoSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.position.label'), 'position_key', $logoSort, $logoSortOptions, $logoDefaultSort, 'logo_sort', 'logo_dir', 'logo_page'); ?></th>
                    <th<?php echo sr_admin_sort_aria('title', $logoSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.text.ac97396d'), 'title', $logoSort, $logoSortOptions, $logoDefaultSort, 'logo_sort', 'logo_dir', 'logo_page'); ?></th>
                    <th><?php echo sr_e(sr_t('logo_manager::ui.public_symbol.list_label')); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $logoSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.status.e10195a1'), 'status', $logoSort, $logoSortOptions, $logoDefaultSort, 'logo_sort', 'logo_dir', 'logo_page'); ?></th>
                    <th<?php echo sr_admin_sort_aria('starts_at', $logoSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.text.65bdaefd'), 'starts_at', $logoSort, $logoSortOptions, $logoDefaultSort, 'logo_sort', 'logo_dir', 'logo_page'); ?></th>
                    <th<?php echo sr_admin_sort_aria('ends_at', $logoSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.text.26c25fca'), 'ends_at', $logoSort, $logoSortOptions, $logoDefaultSort, 'logo_sort', 'logo_dir', 'logo_page'); ?></th>
                    <th<?php echo sr_admin_sort_aria('duration', $logoSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.duration.label'), 'duration', $logoSort, $logoSortOptions, $logoDefaultSort, 'logo_sort', 'logo_dir', 'logo_page'); ?></th>
                    <th<?php echo sr_admin_sort_aria('sort_order', $logoSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.text.3788952d'), 'sort_order', $logoSort, $logoSortOptions, $logoDefaultSort, 'logo_sort', 'logo_dir', 'logo_page'); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('logo_manager::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logos === []) { ?>
                    <tr><td colspan="10" class="admin-empty-state"><?php echo sr_e(sr_t('logo_manager::ui.logo.empty')); ?></td></tr>
                <?php } else { ?>
                    <?php foreach ($logos as $logo) { ?>
                        <?php
                        $logoManagerPositionKey = (string) ($logo['position_key'] ?? '');
                        $logoManagerActiveIdForPosition = (int) ($activeLogoIdsByPosition[$logoManagerPositionKey] ?? 0);
                        $logoManagerIsCurrentLogo = $logoManagerActiveIdForPosition > 0 && $logoManagerActiveIdForPosition === (int) ($logo['id'] ?? 0);
                        $logoManagerStartsAt = is_string($logo['starts_at'] ?? null) ? (string) $logo['starts_at'] : '';
                        $logoManagerEndsAt = is_string($logo['ends_at'] ?? null) ? (string) $logo['ends_at'] : '';
                        $logoManagerIsCurrentPeriod = ($logoManagerStartsAt === '' || $logoManagerStartsAt <= $logoManagerNow)
                            && ($logoManagerEndsAt === '' || $logoManagerEndsAt >= $logoManagerNow);
                        $logoManagerIsActiveCandidate = (string) ($logo['status'] ?? '') === 'active' && $logoManagerIsCurrentPeriod;
                        ?>
                        <tr>
                            <td class="admin-table-checkbox-cell logo-manager-select-cell">
                                <label class="sr-only" for="logo_manager_bulk_select_<?php echo sr_e((string) (int) $logo['id']); ?>"><?php echo sr_e((string) $logo['title']); ?> 선택</label>
                                <input id="logo_manager_bulk_select_<?php echo sr_e((string) (int) $logo['id']); ?>" type="checkbox" name="selected_logo_ids[]" value="<?php echo sr_e((string) (int) $logo['id']); ?>" class="form-checkbox" form="logo-manager-bulk-status-form" data-logo-manager-row-select>
                            </td>
                            <td><?php echo sr_e(sr_logo_manager_position_label($logoManagerPositionKey, $pdo)); ?></td>
                            <td class="admin-table-break">
                                <img class="logo-manager-thumb" src="<?php echo sr_e(sr_logo_manager_url_for_output(sr_logo_manager_logo_url($logo))); ?>" alt="" loading="lazy" decoding="async">
                                <?php echo sr_e((string) $logo['title']); ?>
                                <span class="logo-manager-row-flags">
                                    <?php if ($logoManagerIsCurrentLogo) { ?>
                                        <span class="badge badge-soft-success"><?php echo sr_e(sr_t('logo_manager::ui.current_applied')); ?></span>
                                    <?php } elseif ($logoManagerIsActiveCandidate) { ?>
                                        <span class="badge badge-soft-secondary"><?php echo sr_e(sr_t('logo_manager::ui.current_candidate')); ?></span>
                                    <?php } ?>
                                </span>
                                <?php $logoManagerIconVariants = is_array($iconVariantsByLogoId[(int) $logo['id']] ?? null) ? $iconVariantsByLogoId[(int) $logo['id']] : []; ?>
                                <?php if ($logoManagerIconVariants !== []) { ?>
                                    <div class="logo-manager-icon-preview-list" aria-label="활성 아이콘 세트">
                                        <?php foreach ($logoManagerIconVariants as $variant) { ?>
                                            <?php $variantUrl = sr_logo_manager_icon_variant_url($variant); ?>
                                            <?php if ($variantUrl !== '') { ?>
                                                <span class="logo-manager-icon-preview">
                                                    <img src="<?php echo sr_e(sr_logo_manager_url_for_output($variantUrl)); ?>" alt="" loading="lazy" decoding="async">
                                                    <small><?php echo sr_e((string) (int) $variant['width'] . 'x' . (string) (int) $variant['height']); ?></small>
                                                </span>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </td>
                            <td>
                                <?php echo $logoManagerPositionKey === sr_logo_manager_public_symbol_position_key() && !empty($logo['use_as_public_symbol'])
                                    ? sr_e(sr_t('logo_manager::ui.public_symbol.yes'))
                                    : sr_e(sr_t('logo_manager::ui.public_symbol.no')); ?>
                            </td>
                            <td><span class="admin-status <?php echo (string) $logo['status'] === 'active' ? 'is-normal' : 'is-left'; ?>"><?php echo sr_e(sr_logo_manager_status_label((string) $logo['status'])); ?></span></td>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) ($logo['starts_at'] ?? sr_t('logo_manager::ui.text.8902fb48'))); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) ($logo['ends_at'] ?? sr_t('logo_manager::ui.text.8902fb48'))); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(sr_logo_manager_duration_label($logo['starts_at'] ?? null, $logo['ends_at'] ?? null)); ?></td>
                            <td><?php echo sr_e((string) $logo['sort_order']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <?php $logoManagerEditModalId = 'logo-manager-edit-modal-' . (string) (int) $logo['id']; ?>
                                    <?php $logoManagerIconModalId = 'logo-manager-icon-modal-' . (string) (int) $logo['id']; ?>
                                    <?php if ($logoManagerPositionKey === sr_logo_manager_public_symbol_position_key()) { ?>
                                        <button type="button" class="btn btn-sm btn-icon btn-solid-light" aria-label="아이콘 세트 관리" title="아이콘 세트" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($logoManagerIconModalId); ?>" data-overlay="#<?php echo sr_e($logoManagerIconModalId); ?>"><?php echo sr_material_icon_html('apps'); ?></button>
                                    <?php } ?>
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="로고 배치 수정" title="수정" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($logoManagerEditModalId); ?>" data-overlay="#<?php echo sr_e($logoManagerEditModalId); ?>"><?php echo sr_material_icon_html('edit'); ?></button>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/logo-manager' . $logoManagerActionSuffix)); ?>" class="admin-inline-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="logo_status">
                                        <input type="hidden" name="logo_id" value="<?php echo sr_e((string) $logo['id']); ?>">
                                        <?php $logoManagerNextStatus = (string) $logo['status'] === 'active' ? 'disabled' : 'active'; ?>
                                        <?php $logoManagerStatusButtonLabel = (string) $logo['status'] === 'active' ? sr_t('logo_manager::ui.text.92cdef3c') : sr_t('logo_manager::ui.active.93c558d7'); ?>
                                        <?php $logoManagerStatusButtonClass = $logoManagerNextStatus === 'disabled' ? 'btn-outline-secondary' : 'btn-solid-primary'; ?>
                                        <?php $logoManagerStatusButtonIcon = $logoManagerNextStatus === 'disabled' ? 'toggle_off' : 'toggle_on'; ?>
                                        <?php $logoManagerStatusConfirmMessage = $logoManagerPositionKey === sr_logo_manager_public_symbol_position_key()
                                            ? '이 파비콘/앱 아이콘 로고를 중지할까요? 이 로고와 아이콘 세트는 head link 후보에서 제외됩니다. 같은 용도의 다른 활성 후보가 있으면 그 후보가 적용될 수 있습니다.'
                                            : '이 로고 배치를 미사용 처리할까요? 같은 용도에 다른 활성 로고가 있으면 그 로고가 적용될 수 있습니다.'; ?>
                                        <?php $logoManagerStatusConfirm = $logoManagerNextStatus === 'disabled' ? ' onclick="return confirm(' . sr_e(sr_js_json_encode($logoManagerStatusConfirmMessage)) . ');"' : ''; ?>
                                        <input type="hidden" name="status" value="<?php echo sr_e($logoManagerNextStatus); ?>">
                                        <button type="submit" class="btn btn-sm btn-icon <?php echo sr_e($logoManagerStatusButtonClass); ?>" aria-label="<?php echo sr_e($logoManagerStatusButtonLabel); ?>" title="<?php echo sr_e($logoManagerStatusButtonLabel); ?>"<?php echo $logoManagerStatusConfirm; ?>><?php echo sr_material_icon_html($logoManagerStatusButtonIcon); ?></button>
                                    </form>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/logo-manager' . $logoManagerActionSuffix)); ?>" class="admin-inline-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="delete_logo">
                                        <input type="hidden" name="logo_id" value="<?php echo sr_e((string) $logo['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="로고 배치 삭제" title="삭제" onclick="return confirm('이 로고 배치를 삭제할까요? 원본 이미지와 생성된 아이콘 세트 파일도 함께 정리됩니다.');"><?php echo sr_material_icon_html('delete'); ?></button>
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
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('apps'); ?> <?php echo sr_e(sr_t('logo_manager::ui.icon_legend.icon_set')); ?></span>
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> <?php echo sr_e(sr_t('logo_manager::ui.icon_legend.edit')); ?></span>
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('toggle_on'); ?> <?php echo sr_e(sr_t('logo_manager::ui.icon_legend.status')); ?></span>
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('delete'); ?> <?php echo sr_e(sr_t('logo_manager::ui.icon_legend.delete')); ?></span>
    </div>
</section>
<?php echo sr_admin_pagination_html($logoPagination, '로고 배치 목록 페이지'); ?>

<?php foreach ($logos as $logo) { ?>
    <?php
    $logoManagerEditModalId = 'logo-manager-edit-modal-' . (string) (int) $logo['id'];
    $logoManagerEditPositionKey = (string) ($logo['position_key'] ?? '');
    ?>
    <div id="<?php echo sr_e($logoManagerEditModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($logoManagerEditModalId); ?>-label" aria-hidden="true" inert>
        <div class="modal-dialog modal-dialog-lg">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/logo-manager' . $logoManagerActionSuffix)); ?>" enctype="multipart/form-data" class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($logoManagerEditModalId); ?>-label" class="modal-title">로고 수정</h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('logo_manager::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($logoManagerEditModalId); ?>">
                        <?php echo sr_material_icon_html('close', '', sr_t('logo_manager::ui.close.1e8c1020')); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="admin-dashboard-meta">저장 즉시 현재 활성 로고가 변경될 수 있습니다.</p>
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="update_logo">
                    <input type="hidden" name="logo_id" value="<?php echo sr_e((string) (int) $logo['id']); ?>">
                    <div class="admin-form-row">
                        <label class="form-label" for="logo_manager_edit_position_key_<?php echo sr_e((string) (int) $logo['id']); ?>"><?php echo sr_e(sr_t('logo_manager::ui.position.label')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('logo_manager::ui.required.1f227c67')); ?></span></label>
                        <div class="admin-form-field">
                            <select id="logo_manager_edit_position_key_<?php echo sr_e((string) (int) $logo['id']); ?>" name="position_key" class="form-select" data-overlay-focus required data-logo-manager-position-select>
                                <?php foreach ($positionOptions as $positionKey => $positionOption) { ?>
                                    <option value="<?php echo sr_e((string) $positionKey); ?>"<?php echo (string) $positionKey === $logoManagerEditPositionKey ? ' selected' : ''; ?>><?php echo sr_e((string) ($positionOption['label'] ?? $positionKey)); ?></option>
                                <?php } ?>
                            </select>
                            <small class="admin-form-help"><?php echo sr_e(sr_t('logo_manager::ui.position.help')); ?></small>
                        </div>
                    </div>
                    <div class="admin-form-row" data-logo-manager-public-symbol-row>
                        <span class="form-label"><?php echo sr_e(sr_t('logo_manager::ui.public_symbol.label')); ?></span>
                        <div class="admin-form-field">
                            <label class="admin-form-check form-label" for="logo_manager_edit_use_as_public_symbol_<?php echo sr_e((string) (int) $logo['id']); ?>">
                                <input id="logo_manager_edit_use_as_public_symbol_<?php echo sr_e((string) (int) $logo['id']); ?>" type="checkbox" name="use_as_public_symbol" value="1" class="form-switch form-choice-dark" data-logo-manager-public-symbol-switch<?php echo !empty($logo['use_as_public_symbol']) ? ' checked' : ''; ?>>
                                <?php echo sr_admin_choice_label_html(sr_t('logo_manager::ui.public_symbol.label')); ?>
                            </label>
                            <small class="admin-form-help"><?php echo sr_e(sr_t('logo_manager::ui.public_symbol.help')); ?></small>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="logo_manager_edit_title_<?php echo sr_e((string) (int) $logo['id']); ?>"><?php echo sr_e(sr_t('logo_manager::ui.name.2b2c54b5')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('logo_manager::ui.required.1f227c67')); ?></span></label>
                        <div class="admin-form-field">
                            <input id="logo_manager_edit_title_<?php echo sr_e((string) (int) $logo['id']); ?>" type="text" name="title" value="<?php echo sr_e((string) $logo['title']); ?>" class="form-input form-control-full" maxlength="120" required>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="logo_manager_edit_status_<?php echo sr_e((string) (int) $logo['id']); ?>"><?php echo sr_e(sr_t('logo_manager::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('logo_manager::ui.required.1f227c67')); ?></span></label>
                        <div class="admin-form-field">
                            <select id="logo_manager_edit_status_<?php echo sr_e((string) (int) $logo['id']); ?>" name="status" class="form-select" required>
                                <?php foreach ($logoStatuses as $logoStatus) { ?>
                                    <option value="<?php echo sr_e($logoStatus); ?>"<?php echo (string) $logo['status'] === $logoStatus ? ' selected' : ''; ?>><?php echo sr_e(sr_logo_manager_status_label($logoStatus)); ?></option>
                                <?php } ?>
                            </select>
                            <small class="admin-form-help"><?php echo sr_e(sr_t('logo_manager::ui.status.save.8ca69925')); ?></small>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="logo_manager_edit_alt_text_<?php echo sr_e((string) (int) $logo['id']); ?>"><?php echo sr_e(sr_t('logo_manager::ui.text.c2f4a315')); ?></label>
                        <div class="admin-form-field">
                            <input id="logo_manager_edit_alt_text_<?php echo sr_e((string) (int) $logo['id']); ?>" type="text" name="alt_text" value="<?php echo sr_e((string) $logo['alt_text']); ?>" class="form-input form-control-full" maxlength="160">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="logo_manager_edit_link_url_<?php echo sr_e((string) (int) $logo['id']); ?>"><?php echo sr_e(sr_t('logo_manager::ui.url.f7ca9b13')); ?></label>
                        <div class="admin-form-field">
                            <input id="logo_manager_edit_link_url_<?php echo sr_e((string) (int) $logo['id']); ?>" type="text" name="link_url" value="<?php echo sr_e((string) $logo['link_url']); ?>" class="form-input form-control-full" maxlength="255" placeholder="<?php echo sr_e(sr_t('logo_manager::ui.https.example.com.9a232e78')); ?>">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <span class="form-label">현재 이미지</span>
                        <div class="admin-form-field">
                            <img class="logo-manager-thumb" src="<?php echo sr_e(sr_logo_manager_url_for_output(sr_logo_manager_logo_url($logo))); ?>" alt="" loading="lazy" decoding="async">
                            <small class="admin-form-help"><?php echo sr_e((string) ($logo['original_name'] ?? '')); ?></small>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="logo_manager_edit_logo_file_<?php echo sr_e((string) (int) $logo['id']); ?>"><?php echo sr_e(sr_t('logo_manager::ui.text.4becf8bb')); ?></label>
                        <div class="admin-form-field">
                            <input id="logo_manager_edit_logo_file_<?php echo sr_e((string) (int) $logo['id']); ?>" type="file" name="logo_file" accept="image/jpeg,image/png,image/webp,image/svg+xml,.svg" class="form-input">
                            <small class="admin-form-help">새 파일을 선택하지 않으면 기존 이미지를 유지합니다.</small>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="logo_manager_edit_starts_at_<?php echo sr_e((string) (int) $logo['id']); ?>"><?php echo sr_e(sr_t('logo_manager::ui.text.65bdaefd')); ?></label>
                        <div class="admin-form-field">
                            <input id="logo_manager_edit_starts_at_<?php echo sr_e((string) (int) $logo['id']); ?>" type="datetime-local" name="starts_at" value="<?php echo sr_e(sr_logo_manager_admin_datetime_value($logo['starts_at'] ?? null)); ?>" class="form-input">
                            <small class="admin-form-help"><?php echo sr_e(sr_t('logo_manager::ui.period.help')); ?></small>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="logo_manager_edit_ends_at_<?php echo sr_e((string) (int) $logo['id']); ?>"><?php echo sr_e(sr_t('logo_manager::ui.text.26c25fca')); ?></label>
                        <div class="admin-form-field">
                            <input id="logo_manager_edit_ends_at_<?php echo sr_e((string) (int) $logo['id']); ?>" type="datetime-local" name="ends_at" value="<?php echo sr_e(sr_logo_manager_admin_datetime_value($logo['ends_at'] ?? null)); ?>" class="form-input">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="logo_manager_edit_sort_order_<?php echo sr_e((string) (int) $logo['id']); ?>"><?php echo sr_e(sr_t('logo_manager::ui.text.3788952d')); ?></label>
                        <div class="admin-form-field">
                            <input id="logo_manager_edit_sort_order_<?php echo sr_e((string) (int) $logo['id']); ?>" type="number" name="sort_order" value="<?php echo sr_e((string) $logo['sort_order']); ?>" class="form-input">
                            <small class="admin-form-help"><?php echo sr_e(sr_t('logo_manager::ui.sort.help')); ?></small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($logoManagerEditModalId); ?>"><?php echo sr_e(sr_t('logo_manager::ui.close.1e8c1020')); ?></button>
                    <button type="submit" class="btn btn-solid-primary modal-action">로고 수정 저장</button>
                </div>
            </form>
        </div>
    </div>
    <?php if ($logoManagerEditPositionKey === sr_logo_manager_public_symbol_position_key()) { ?>
        <?php $logoManagerIconModalId = 'logo-manager-icon-modal-' . (string) (int) $logo['id']; ?>
        <div id="<?php echo sr_e($logoManagerIconModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($logoManagerIconModalId); ?>-label" aria-hidden="true" inert>
            <div class="modal-dialog modal-dialog-lg">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/logo-manager' . $logoManagerActionSuffix)); ?>" class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($logoManagerIconModalId); ?>-label" class="modal-title">파비콘/앱아이콘 세트</h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('logo_manager::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($logoManagerIconModalId); ?>">
                            <?php echo sr_material_icon_html('close', '', sr_t('logo_manager::ui.close.1e8c1020')); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="intent" value="generate_icon_set">
                        <input type="hidden" name="logo_id" value="<?php echo sr_e((string) (int) $logo['id']); ?>">
                        <div class="admin-form-row">
                            <span class="form-label">원본 로고</span>
                            <div class="admin-form-field">
                                <img class="logo-manager-thumb" src="<?php echo sr_e(sr_logo_manager_url_for_output(sr_logo_manager_logo_url($logo))); ?>" alt="" loading="lazy" decoding="async">
                                <small class="admin-form-help"><?php echo sr_e((string) ($logo['width'] ?? 0) . 'x' . (string) ($logo['height'] ?? 0) . ' / ' . (string) ($logo['mime_type'] ?? '')); ?></small>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <span class="form-label">생성 크기 <span class="sr-required-label"><?php echo sr_e(sr_t('logo_manager::ui.required.1f227c67')); ?></span></span>
                            <div class="admin-form-field logo-manager-icon-size-grid" data-logo-manager-icon-size-group>
                                <label class="admin-form-check form-label" for="logo_manager_icon_all_<?php echo sr_e((string) (int) $logo['id']); ?>">
                                    <input id="logo_manager_icon_all_<?php echo sr_e((string) (int) $logo['id']); ?>" type="checkbox" class="form-switch form-switch-light" data-logo-manager-icon-size-all>
                                    <?php echo sr_admin_choice_label_html('전체 선택'); ?>
                                </label>
                                <?php foreach ($logoManagerIconSizeOptions as $variantKey => $variantOption) { ?>
                                    <label class="admin-form-check form-label" for="logo_manager_icon_<?php echo sr_e((string) (int) $logo['id']); ?>_<?php echo sr_e($variantKey); ?>">
                                        <input id="logo_manager_icon_<?php echo sr_e((string) (int) $logo['id']); ?>_<?php echo sr_e($variantKey); ?>" type="checkbox" name="icon_variant_keys[]" value="<?php echo sr_e($variantKey); ?>" class="form-switch form-switch-light" data-logo-manager-icon-size-choice <?php echo in_array($variantKey, $logoManagerDefaultIconKeys, true) ? 'checked' : ''; ?>>
                                        <?php echo sr_admin_choice_label_html((string) $variantOption['label']); ?>
                                    </label>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="logo_manager_icon_fit_<?php echo sr_e((string) (int) $logo['id']); ?>">맞춤 방식</label>
                            <div class="admin-form-field">
                                <select id="logo_manager_icon_fit_<?php echo sr_e((string) (int) $logo['id']); ?>" name="fit_mode" class="form-select">
                                    <option value="contain">contain</option>
                                    <option value="cover">cover</option>
                                </select>
                                <small class="admin-form-help">contain은 전체 로고를 맞추고, cover는 정사각형을 꽉 채웁니다.</small>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="logo_manager_icon_bg_<?php echo sr_e((string) (int) $logo['id']); ?>">배경색</label>
                            <div class="admin-form-field">
                                <input id="logo_manager_icon_bg_<?php echo sr_e((string) (int) $logo['id']); ?>" type="text" name="background_color" value="transparent" class="form-input" maxlength="20" pattern="transparent|#[0-9a-fA-F]{6}">
                                <small class="admin-form-help">transparent 또는 #RRGGBB 형식으로 입력하세요.</small>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <span class="form-label">적용</span>
                            <div class="admin-form-field">
                                <label class="admin-form-check form-label" for="logo_manager_icon_activate_<?php echo sr_e((string) (int) $logo['id']); ?>">
                                    <input id="logo_manager_icon_activate_<?php echo sr_e((string) (int) $logo['id']); ?>" type="checkbox" name="activate_icon_set" value="1" class="form-switch form-choice-dark" checked>
                                    <?php echo sr_admin_choice_label_html('생성 후 바로 사용'); ?>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($logoManagerIconModalId); ?>"><?php echo sr_e(sr_t('logo_manager::ui.close.1e8c1020')); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action">아이콘 세트 생성</button>
                    </div>
                </form>
            </div>
        </div>
    <?php } ?>
<?php } ?>

<script>
(function () {
    var bulkForm = document.querySelector('[data-logo-manager-bulk-form]');
    if (bulkForm) {
        var countNode = document.querySelector('[data-logo-manager-selected-count]');
        var submitButtons = Array.prototype.slice.call(document.querySelectorAll('[data-logo-manager-bulk-submit]'));
        var clear = document.querySelector('[data-logo-manager-bulk-clear]');
        var selectAll = document.querySelector('[data-logo-manager-select-all]');
        var rowChecks = Array.prototype.slice.call(document.querySelectorAll('[data-logo-manager-row-select]'));

        var checkedRows = function () {
            return rowChecks.filter(function (input) {
                return input.checked && !input.disabled;
            });
        };

        var syncBulkState = function () {
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
        };

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
        bulkForm.addEventListener('submit', function (event) {
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
            var nextStatus = submitter && submitter.getAttribute ? submitter.getAttribute('value') : '';
            var message = '선택한 로고 배치 ' + selectedCount + '건의 상태를 "' + statusLabel + '"(으)로 변경합니다.';
            if (nextStatus === 'disabled') {
                message += ' 파비콘/앱 아이콘이 포함되어 있으면 해당 로고와 아이콘 세트는 head link 후보에서 제외되고, 같은 용도의 다른 활성 후보가 있으면 그 후보가 적용될 수 있습니다.';
            }
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
        syncBulkState();
    }

    Array.prototype.slice.call(document.querySelectorAll('[data-logo-manager-position-select]')).forEach(function (positionSelect) {
        var form = positionSelect.closest('form');
        var symbolSwitch = form ? form.querySelector('[data-logo-manager-public-symbol-switch]') : null;
        if (!symbolSwitch) {
            return;
        }
        var sync = function () {
            var enabled = positionSelect.value === <?php echo sr_js_json_encode(sr_logo_manager_public_symbol_position_key()); ?>;
            symbolSwitch.disabled = !enabled;
            if (!enabled) {
                symbolSwitch.checked = false;
            }
        };
        positionSelect.addEventListener('change', sync);
        sync();
    });

    Array.prototype.slice.call(document.querySelectorAll('[data-logo-manager-icon-size-group]')).forEach(function (group) {
        var allSwitch = group.querySelector('[data-logo-manager-icon-size-all]');
        var sizeSwitches = Array.prototype.slice.call(group.querySelectorAll('[data-logo-manager-icon-size-choice]'));
        if (!allSwitch || sizeSwitches.length < 1) {
            return;
        }
        var syncAllSwitch = function () {
            var checkedCount = sizeSwitches.filter(function (input) {
                return input.checked && !input.disabled;
            }).length;
            allSwitch.checked = checkedCount > 0 && checkedCount === sizeSwitches.length;
            allSwitch.indeterminate = checkedCount > 0 && checkedCount < sizeSwitches.length;
        };
        allSwitch.addEventListener('change', function () {
            sizeSwitches.forEach(function (input) {
                if (!input.disabled) {
                    input.checked = allSwitch.checked;
                }
            });
            syncAllSwitch();
        });
        sizeSwitches.forEach(function (input) {
            input.addEventListener('change', syncAllSwitch);
        });
        syncAllSwitch();
    });
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
