<?php

$adminPageTitle = sr_t('logo_manager::ui.text.e046e24f');
$adminPageSubtitle = sr_t('logo_manager::ui.text.52f5a11e');
$adminContainerClass = 'admin-page-logo-manager admin-ui-scope';
$logoSortOptions = sr_admin_logo_sort_options();
$logoDefaultSort = sr_admin_logo_default_sort();
$logoSort = isset($logoSort) && is_array($logoSort) ? $logoSort : $logoDefaultSort;
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
        <form method="post" action="<?php echo sr_e(sr_url('/admin/logo-manager')); ?>" enctype="multipart/form-data" class="modal-content ui-form-theme">
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
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="logo-manager-logo-modal" data-overlay="#logo-manager-logo-modal"><?php echo sr_e(sr_t('logo_manager::ui.logo.add')); ?></button>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($logoSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url($logoSortOptions, $logoDefaultSort, '', '', 'logo_sort', 'logo_dir', 'logo_page')); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="로고 배치 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php echo sr_admin_pagination_summary_html($logoPagination); ?>
    </div>
    <form id="logo-manager-bulk-status-form" method="post" action="<?php echo sr_e(sr_url('/admin/logo-manager')); ?>" class="logo-manager-bulk-form" data-logo-manager-bulk-form>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="batch_status">
        <input type="hidden" name="operation_key" value="logo_manager.set_status">
        <div class="admin-list-actions logo-manager-bulk-actions" hidden data-logo-manager-bulk-bar>
            <div class="logo-manager-bulk-controls">
                <select name="target_status" class="form-select" aria-label="변경할 로고 배치 상태">
                    <option value="active"><?php echo sr_e(sr_t('logo_manager::ui.active.93c558d7')); ?></option>
                    <option value="disabled"><?php echo sr_e(sr_t('logo_manager::ui.text.92cdef3c')); ?></option>
                </select>
                <button type="submit" class="btn btn-solid-primary" data-logo-manager-bulk-submit disabled>상태 변경</button>
                <button type="button" class="btn btn-solid-light" data-logo-manager-bulk-clear>선택 해제</button>
            </div>
            <div class="logo-manager-bulk-summary" aria-live="polite">
                <strong data-logo-manager-selected-count>0</strong>개 선택됨
            </div>
        </div>
    </form>
    <div class="table-wrapper">
        <table class="table logo-manager-assignments-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('logo_manager::ui.logo.list')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th class="logo-manager-select-cell">
                        <label class="sr-only" for="logo_manager_bulk_select_all">현재 페이지 로고 배치 전체 선택</label>
                        <input id="logo_manager_bulk_select_all" type="checkbox" class="form-checkbox" data-logo-manager-select-all<?php echo $logos === [] ? ' disabled' : ''; ?>>
                    </th>
                    <th<?php echo sr_admin_sort_aria('position_key', $logoSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.position.label'), 'position_key', $logoSort, $logoSortOptions, $logoDefaultSort, 'logo_sort', 'logo_dir', 'logo_page'); ?></th>
                    <th<?php echo sr_admin_sort_aria('title', $logoSort); ?>><?php echo sr_admin_sort_header_html(sr_t('logo_manager::ui.text.ac97396d'), 'title', $logoSort, $logoSortOptions, $logoDefaultSort, 'logo_sort', 'logo_dir', 'logo_page'); ?></th>
                    <th><?php echo sr_e(sr_t('logo_manager::ui.public_symbol.label')); ?></th>
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
                        <tr>
                            <td class="logo-manager-select-cell">
                                <label class="sr-only" for="logo_manager_bulk_select_<?php echo sr_e((string) (int) $logo['id']); ?>"><?php echo sr_e((string) $logo['title']); ?> 선택</label>
                                <input id="logo_manager_bulk_select_<?php echo sr_e((string) (int) $logo['id']); ?>" type="checkbox" name="selected_logo_ids[]" value="<?php echo sr_e((string) (int) $logo['id']); ?>" class="form-checkbox" form="logo-manager-bulk-status-form" data-logo-manager-row-select>
                            </td>
                            <td><?php echo sr_e(sr_logo_manager_position_label((string) $logo['position_key'], $pdo)); ?></td>
                            <td class="admin-table-break">
                                <img class="logo-manager-thumb" src="<?php echo sr_e(sr_logo_manager_url_for_output(sr_logo_manager_logo_url($logo))); ?>" alt="" loading="lazy" decoding="async">
                                <?php echo sr_e((string) $logo['title']); ?>
                            </td>
                            <td>
                                <?php echo (string) ($logo['position_key'] ?? '') === sr_logo_manager_public_symbol_position_key() && !empty($logo['use_as_public_symbol'])
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
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/logo-manager')); ?>" class="admin-inline-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="logo_status">
                                        <input type="hidden" name="logo_id" value="<?php echo sr_e((string) $logo['id']); ?>">
                                        <?php $logoManagerNextStatus = (string) $logo['status'] === 'active' ? 'disabled' : 'active'; ?>
                                        <?php $logoManagerStatusButtonLabel = (string) $logo['status'] === 'active' ? sr_t('logo_manager::ui.text.92cdef3c') : sr_t('logo_manager::ui.active.93c558d7'); ?>
                                        <input type="hidden" name="status" value="<?php echo sr_e($logoManagerNextStatus); ?>">
                                        <button type="submit" class="btn btn-sm btn-solid-light"><?php echo sr_e($logoManagerStatusButtonLabel); ?></button>
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
<?php echo sr_admin_pagination_html($logoPagination, '로고 배치 목록 페이지'); ?>

<script>
(function () {
    var bulkForm = document.querySelector('[data-logo-manager-bulk-form]');
    if (bulkForm) {
        var bar = document.querySelector('[data-logo-manager-bulk-bar]');
        var countNode = document.querySelector('[data-logo-manager-selected-count]');
        var submit = document.querySelector('[data-logo-manager-bulk-submit]');
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
            if (bar) {
                bar.hidden = selectedCount < 1;
            }
            if (submit) {
                submit.disabled = selectedCount < 1;
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
            var status = bulkForm.querySelector('select[name="target_status"]');
            var statusLabel = status && status.options[status.selectedIndex] ? status.options[status.selectedIndex].text : '선택한 상태';
            if (!window.confirm('선택한 로고 배치 ' + selectedCount + '건의 상태를 "' + statusLabel + '"(으)로 변경합니다.')) {
                event.preventDefault();
            }
        });
        syncBulkState();
    }

    var positionSelect = document.querySelector('[data-logo-manager-position-select]');
    var symbolSwitch = document.querySelector('[data-logo-manager-public-symbol-switch]');
    if (!positionSelect || !symbolSwitch) {
        return;
    }
    var sync = function () {
        var enabled = positionSelect.value === <?php echo json_encode(sr_logo_manager_public_symbol_position_key(), JSON_UNESCAPED_SLASHES); ?>;
        symbolSwitch.disabled = !enabled;
        if (!enabled) {
            symbolSwitch.checked = false;
        }
    };
    positionSelect.addEventListener('change', sync);
    sync();
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
