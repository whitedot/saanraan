<?php

$adminPageTitle = '회원 CSV 다운로드';
$adminPageSubtitle = '다운로드할 회원 범위와 정렬, 파일당 최대 건수를 지정하세요.';
$adminContainerClass = 'admin-page-member-export admin-ui-scope';
$statusFilter = isset($statusFilter) && is_array($statusFilter) ? $statusFilter : [];
$searchFilter = isset($searchFilter) && is_array($searchFilter) ? $searchFilter : ['field' => 'all', 'keyword' => ''];
$memberSort = isset($memberSort) && is_array($memberSort) ? $memberSort : sr_admin_member_default_sort();
$totalMembers = (int) ($totalMembers ?? 0);
$exportLimit = (int) ($exportLimit ?? sr_admin_member_export_limit());
$memberExportPage = (int) ($memberExportPage ?? sr_admin_member_export_page_from_request($totalMembers, $exportLimit));
$memberExportRange = isset($memberExportRange) && is_array($memberExportRange) ? $memberExportRange : sr_admin_member_export_range($totalMembers, $exportLimit, $memberExportPage);
$expectedExportCount = (int) ($expectedExportCount ?? (int) ($memberExportRange['count'] ?? 0));
$memberExportRangeStart = (int) ($memberExportRange['start'] ?? 0);
$memberExportRangeEnd = (int) ($memberExportRange['end'] ?? 0);
$memberExportTotalPages = (int) ($memberExportRange['total_pages'] ?? 1);
$memberExportDownloadCountText = '';
if ($expectedExportCount > 0) {
    $memberExportDownloadCountText = $memberExportTotalPages > 1
        ? ' (' . number_format($memberExportRangeStart) . '-' . number_format($memberExportRangeEnd) . '/' . number_format($totalMembers) . ')'
        : ' (' . number_format($expectedExportCount) . '/' . number_format($totalMembers) . ')';
}
$memberExportStatusOptions = [];
foreach (sr_admin_member_allowed_statuses() as $status) {
    $memberExportStatusOptions[$status] = sr_admin_code_label($status, 'member_status');
}
$memberExportFieldOptions = [
    'all' => sr_t('member::ui.all.a4b69faf'),
    'hash' => sr_t('member::ui.text.93971787'),
    'email' => sr_t('member::ui.email.3b7dbc4c'),
    'login_id' => sr_t('member::ui.login.0cdb28b5'),
    'name' => sr_t('member::ui.public_name'),
];
$memberExportSortOptions = [
    'id' => '기본 정렬',
    'email' => sr_t('member::ui.email.3b7dbc4c'),
    'name' => sr_t('member::ui.public_name'),
    'nickname' => sr_t('member::ui.nickname'),
    'status' => sr_t('member::ui.status.e10195a1'),
    'active_session_count' => sr_t('member::ui.text.fda1ae9a'),
];
$memberExportSortKey = (string) ($memberSort['key'] ?? 'id');
$memberExportSortDir = (string) ($memberSort['dir'] ?? 'desc');
$memberExportLimitOptions = sr_admin_member_export_limit_options();
$memberExportMaxLimit = max(array_keys($memberExportLimitOptions));
$memberExportColumnConfig = isset($memberExportColumnConfig) && is_array($memberExportColumnConfig) ? $memberExportColumnConfig : sr_admin_member_export_column_config_from_request();
$memberExportColumnDefinitions = isset($memberExportColumnConfig['definitions']) && is_array($memberExportColumnConfig['definitions']) ? $memberExportColumnConfig['definitions'] : sr_admin_member_export_column_definitions();
$memberExportColumnOrder = isset($memberExportColumnConfig['order']) && is_array($memberExportColumnConfig['order']) ? $memberExportColumnConfig['order'] : array_keys($memberExportColumnDefinitions);
$memberExportColumns = isset($memberExportColumns) && is_array($memberExportColumns) ? $memberExportColumns : (isset($memberExportColumnConfig['selected']) && is_array($memberExportColumnConfig['selected']) ? $memberExportColumnConfig['selected'] : array_keys($memberExportColumnDefinitions));
$memberExportColumnRows = $memberExportColumns !== [] ? $memberExportColumns : $memberExportColumnOrder;
$memberExportColumnError = isset($memberExportColumnError) ? (string) $memberExportColumnError : '';
$memberExportMaskOptions = isset($memberExportMaskOptions) && is_array($memberExportMaskOptions) ? $memberExportMaskOptions : sr_admin_member_export_mask_options_from_request();
$adminPageTitleUrl = sr_admin_page_title_reset_url(false, '/admin/members/export');
$memberExportDownloadUrl = static function (int $page) use ($statusFilter, $searchFilter, $memberSort, $exportLimit, $memberExportColumnRows, $memberExportMaskOptions): string {
    $query = [
        'field' => (string) ($searchFilter['field'] ?? 'all'),
        'sort' => (string) ($memberSort['key'] ?? 'id'),
        'dir' => (string) ($memberSort['dir'] ?? 'desc'),
        'export_limit' => $exportLimit,
        'export_columns_configured' => '1',
        'export_column' => array_values(array_map('strval', $memberExportColumnRows)),
        'export_page' => max(1, $page),
        'download' => '1',
    ];
    $keyword = trim((string) ($searchFilter['keyword'] ?? ''));
    if ($keyword !== '') {
        $query['q'] = $keyword;
    }
    if ($statusFilter !== []) {
        $query['status'] = array_values(array_map('strval', $statusFilter));
    }
    if (!empty($memberExportMaskOptions['email'])) {
        $query['mask_email'] = '1';
    }
    if (!empty($memberExportMaskOptions['phone'])) {
        $query['mask_phone'] = '1';
    }

    return sr_url('/admin/members/export?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
};

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts('', []); ?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/members/export')); ?>" class="admin-form ui-form-theme" data-sr-validate-form>
    <section class="card">
        <div class="card-header">
            <h2 class="card-title">다운로드 옵션</h2>
            <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-sm btn-outline-secondary">목록으로</a>
        </div>
        <div class="form-row">
            <span class="form-label">상태</span>
            <div class="form-field">
                <?php echo sr_admin_filter_toggle_group_html('member-export-status-filter', 'status', $memberExportStatusOptions, $statusFilter, sr_t('member::ui.all.a4b69faf')); ?>
                <p class="form-help">선택하지 않으면 모든 회원 상태를 포함합니다.</p>
            </div>
        </div>

        <div class="form-row">
            <label class="form-label" for="member_export_field">검색조건</label>
            <div class="form-field">
                <select id="member_export_field" name="field" class="form-select">
                    <?php foreach ($memberExportFieldOptions as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($searchFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <label class="form-label" for="member_export_keyword">검색어</label>
            <div class="form-field">
                <input type="text" id="member_export_keyword" name="q" value="<?php echo sr_e((string) ($searchFilter['keyword'] ?? '')); ?>" maxlength="120" class="form-input form-control-full">
                <p class="form-help">공개 해시, 이메일, 로그인 ID, 공개 이름 조건은 회원 목록 검색과 같은 기준을 사용합니다.</p>
            </div>
        </div>

        <div class="form-row">
            <label class="form-label" for="member_export_sort">정렬</label>
            <div class="form-field">
                <div class="form-control-group">
                    <select id="member_export_sort" name="sort" class="form-select form-control-group-start">
                        <?php foreach ($memberExportSortOptions as $sortValue => $sortLabel) { ?>
                            <option value="<?php echo sr_e($sortValue); ?>"<?php echo $memberExportSortKey === $sortValue ? ' selected' : ''; ?>>
                                <?php echo sr_e($sortLabel); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <select id="member_export_dir" name="dir" class="form-select form-control-group-end" aria-label="정렬 방향">
                        <option value="desc"<?php echo $memberExportSortDir === 'desc' ? ' selected' : ''; ?>>내림차순</option>
                        <option value="asc"<?php echo $memberExportSortDir === 'asc' ? ' selected' : ''; ?>>오름차순</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-row">
            <label class="form-label" for="member_export_limit">파일당 최대 건수</label>
            <div class="form-field">
                <select id="member_export_limit" name="export_limit" class="form-select">
                    <?php foreach ($memberExportLimitOptions as $limitValue => $limitLabel) { ?>
                        <option value="<?php echo sr_e((string) $limitValue); ?>"<?php echo $exportLimit === (int) $limitValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($limitLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help">실제 다운로드 대상은 실행 시점에 다시 계산됩니다. 한 파일에는 최대 <?php echo sr_e(number_format($memberExportMaxLimit)); ?>건까지 포함합니다.</p>
            </div>
        </div>

        <div class="form-row">
            <span class="form-label">마스킹</span>
            <div class="form-field">
                <label class="form-check form-label" for="member_export_mask_email">
                    <input id="member_export_mask_email" type="checkbox" name="mask_email" value="1" class="form-checkbox"<?php echo !empty($memberExportMaskOptions['email']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('이메일 마스킹'); ?>
                </label>
                <label class="form-check form-label" for="member_export_mask_phone">
                    <input id="member_export_mask_phone" type="checkbox" name="mask_phone" value="1" class="form-checkbox"<?php echo !empty($memberExportMaskOptions['phone']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('휴대폰 번호 마스킹'); ?>
                </label>
                <p class="form-help">선택하지 않으면 해당 값은 원문으로 다운로드됩니다.</p>
            </div>
        </div>

        <div class="form-row">
            <span class="form-label">포함 컬럼</span>
            <div class="form-field">
                <input type="hidden" name="export_columns_configured" value="1">
                <div class="admin-member-export-column-list" data-member-export-column-list data-admin-reorder-list>
                    <?php foreach ($memberExportColumnRows as $memberExportColumnIndex => $memberExportColumnKey) { ?>
                        <?php
                        $memberExportColumnKey = (string) $memberExportColumnKey;
                        if (!isset($memberExportColumnDefinitions[$memberExportColumnKey])) {
                            continue;
                        }
                        $memberExportColumnLabel = (string) ($memberExportColumnDefinitions[$memberExportColumnKey]['label'] ?? $memberExportColumnKey);
                        ?>
                        <div class="admin-member-export-column-row" data-member-export-column-row data-admin-reorder-item>
                            <div class="admin-business-info-order">
                                <span class="admin-drag-handle" draggable="true" aria-label="드래그해서 순서 변경" title="드래그해서 순서 변경" data-admin-reorder-handle><?php echo sr_material_icon_html('apps', 'admin-drag-handle-icon'); ?></span>
                                <button type="button" class="btn btn-icon-xs btn-soft-default admin-member-export-column-move" aria-label="<?php echo sr_e($memberExportColumnLabel); ?> 컬럼 위로 이동" title="위로" data-member-export-column-move="up" data-admin-reorder-move="up">
                                    <?php echo sr_material_icon_html('arrow_upward'); ?>
                                </button>
                                <button type="button" class="btn btn-icon-xs btn-soft-default admin-member-export-column-move" aria-label="<?php echo sr_e($memberExportColumnLabel); ?> 컬럼 아래로 이동" title="아래로" data-member-export-column-move="down" data-admin-reorder-move="down">
                                    <?php echo sr_material_icon_html('arrow_downward'); ?>
                                </button>
                            </div>
                            <label class="sr-only" for="member_export_column_<?php echo sr_e((string) $memberExportColumnIndex); ?>">포함 컬럼</label>
                            <select id="member_export_column_<?php echo sr_e((string) $memberExportColumnIndex); ?>" name="export_column[]" class="form-select admin-member-export-column-select" data-member-export-column-select>
                                <?php foreach ($memberExportColumnDefinitions as $memberExportOptionKey => $memberExportOption) { ?>
                                    <?php $memberExportOptionKey = (string) $memberExportOptionKey; ?>
                                    <option value="<?php echo sr_e($memberExportOptionKey); ?>"<?php echo $memberExportOptionKey === $memberExportColumnKey ? ' selected' : ''; ?>>
                                        <?php echo sr_e((string) ($memberExportOption['label'] ?? $memberExportOptionKey)); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <div class="admin-member-export-column-actions-cell">
                                <button type="button" class="btn btn-sm btn-icon btn-outline-danger admin-member-export-column-remove" aria-label="<?php echo sr_e($memberExportColumnLabel); ?> 컬럼 제거" title="제거" data-member-export-column-remove>
                                    <?php echo sr_material_icon_html('delete'); ?>
                                </button>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <div class="admin-member-export-column-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-member-export-column-add><?php echo sr_material_icon_html('add'); ?>컬럼 추가</button>
                </div>
                <?php if ($memberExportColumnError !== '') { ?>
                    <p class="form-help form-help-warning"><?php echo sr_e($memberExportColumnError); ?></p>
                <?php } ?>
            </div>
        </div>

        <div class="card-footer">
            <button type="submit" class="btn btn-outline-secondary"><?php echo sr_material_icon_html('refresh'); ?><span>대상 확인</span></button>
        </div>
    </section>

    <?php if ($expectedExportCount > 0 && $memberExportColumnError === '') { ?>
        <section class="card admin-member-export-files-section">
            <div class="card-header">
                <h2 class="card-title">다운로드 파일</h2>
            </div>
            <div class="form-row">
                <span class="form-label">파일</span>
                <div class="form-field">
                    <div class="admin-member-export-file-list">
                        <?php for ($memberExportFilePage = 1; $memberExportFilePage <= $memberExportTotalPages; $memberExportFilePage++) { ?>
                            <?php
                            $memberExportFileRange = sr_admin_member_export_range($totalMembers, $exportLimit, $memberExportFilePage);
                            $memberExportFileStart = (int) ($memberExportFileRange['start'] ?? 0);
                            $memberExportFileEnd = (int) ($memberExportFileRange['end'] ?? 0);
                            $memberExportFileLabel = (string) $memberExportFilePage . '번('
                                . number_format($memberExportFileStart) . '-'
                                . number_format($memberExportFileEnd) . '/'
                                . number_format($totalMembers) . ')';
                            ?>
                            <a href="<?php echo sr_e($memberExportDownloadUrl($memberExportFilePage)); ?>" class="btn btn-sm btn-outline-secondary admin-member-export-file-button"><?php echo sr_material_icon_html('download'); ?><span><?php echo sr_e($memberExportFileLabel); ?></span></a>
                        <?php } ?>
                    </div>
                    <p class="form-help">옵션을 바꾼 뒤 대상 확인을 누르면 다운로드 파일 목록이 업데이트됩니다.</p>
                </div>
            </div>
        </section>
    <?php } ?>

    <div class="form-sticky-actions form-actions form-actions-primary form-actions-split">
        <div class="admin-form-secondary-actions">
            <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-solid-light">취소</a>
        </div>
        <div class="admin-member-export-primary-actions">
            <button type="submit" name="download" value="1" class="btn btn-solid-primary"><?php echo sr_material_icon_html('download'); ?><span>CSV 다운로드<?php echo sr_e($memberExportDownloadCountText); ?></span></button>
        </div>
    </div>
</form>

<script>
(function () {
    var columnList = document.querySelector('[data-member-export-column-list]');
    var addButton = document.querySelector('[data-member-export-column-add]');
    var columnDefinitions = <?php echo sr_js_json_encode($memberExportColumnDefinitions); ?> || {};
    var columnKeys = Object.keys(columnDefinitions);
    var moveUpIcon = <?php echo sr_js_json_encode(sr_material_icon_html('arrow_upward')); ?>;
    var moveDownIcon = <?php echo sr_js_json_encode(sr_material_icon_html('arrow_downward')); ?>;
    var dragIcon = <?php echo sr_js_json_encode(sr_material_icon_html('apps', 'admin-drag-handle-icon')); ?>;
    var deleteIcon = <?php echo sr_js_json_encode(sr_material_icon_html('delete')); ?>;
    var columnRowIndex = <?php echo (int) count($memberExportColumnRows); ?>;
    var selectedColumnValues = function () {
        if (!columnList) {
            return [];
        }
        return Array.prototype.slice.call(columnList.querySelectorAll('[data-member-export-column-select]')).map(function (select) {
            return select.value;
        }).filter(function (value) {
            return value !== '';
        });
    };
    var firstAvailableColumn = function () {
        var selected = selectedColumnValues();
        for (var index = 0; index < columnKeys.length; index += 1) {
            if (selected.indexOf(columnKeys[index]) === -1) {
                return columnKeys[index];
            }
        }
        return '';
    };
    var syncColumnOptions = function () {
        var selected = selectedColumnValues();
        Array.prototype.slice.call(columnList.querySelectorAll('[data-member-export-column-select]')).forEach(function (select) {
            Array.prototype.slice.call(select.options).forEach(function (option) {
                option.disabled = option.value !== select.value && selected.indexOf(option.value) !== -1;
            });
        });
        if (addButton) {
            addButton.disabled = firstAvailableColumn() === '';
        }
    };
    var syncColumnRows = function () {
        if (!columnList) {
            return;
        }
        var rows = Array.prototype.slice.call(columnList.querySelectorAll('[data-member-export-column-row]'));
        rows.forEach(function (row, index) {
            var upButton = row.querySelector('[data-member-export-column-move="up"]');
            var downButton = row.querySelector('[data-member-export-column-move="down"]');
            if (upButton) {
                upButton.disabled = index === 0;
            }
            if (downButton) {
                downButton.disabled = index === rows.length - 1;
            }
        });
        syncColumnOptions();
    };
    var createColumnSelect = function (selectedValue, rowIndex) {
        var select = document.createElement('select');
        select.className = 'form-select admin-member-export-column-select';
        select.name = 'export_column[]';
        select.id = 'member_export_column_added_' + rowIndex;
        select.setAttribute('data-member-export-column-select', '');
        columnKeys.forEach(function (columnKey) {
            var option = document.createElement('option');
            option.value = columnKey;
            option.textContent = columnDefinitions[columnKey] && columnDefinitions[columnKey].label ? columnDefinitions[columnKey].label : columnKey;
            option.selected = columnKey === selectedValue;
            select.appendChild(option);
        });
        return select;
    };
    var createColumnRow = function (columnKey) {
        var rowIndex = columnRowIndex;
        var row = document.createElement('div');
        var label = columnDefinitions[columnKey] && columnDefinitions[columnKey].label ? columnDefinitions[columnKey].label : columnKey;
        var labelElement = document.createElement('label');
        var order = document.createElement('div');
        var upButton = document.createElement('button');
        var downButton = document.createElement('button');
        var select = createColumnSelect(columnKey, rowIndex);
        var actions = document.createElement('div');
        var removeButton = document.createElement('button');
        columnRowIndex += 1;
        row.className = 'admin-member-export-column-row';
        row.setAttribute('data-member-export-column-row', '');
        row.setAttribute('data-admin-reorder-item', '');
        order.className = 'admin-business-info-order';
        var dragHandle = document.createElement('span');
        dragHandle.className = 'admin-drag-handle';
        dragHandle.draggable = true;
        dragHandle.setAttribute('aria-label', '드래그해서 순서 변경');
        dragHandle.setAttribute('title', '드래그해서 순서 변경');
        dragHandle.setAttribute('data-admin-reorder-handle', '');
        dragHandle.innerHTML = dragIcon;
        upButton.type = 'button';
        upButton.className = 'btn btn-icon-xs btn-soft-default admin-member-export-column-move';
        upButton.setAttribute('aria-label', label + ' 컬럼 위로 이동');
        upButton.setAttribute('title', '위로');
        upButton.setAttribute('data-member-export-column-move', 'up');
        upButton.setAttribute('data-admin-reorder-move', 'up');
        upButton.innerHTML = moveUpIcon;
        downButton.type = 'button';
        downButton.className = 'btn btn-icon-xs btn-soft-default admin-member-export-column-move';
        downButton.setAttribute('aria-label', label + ' 컬럼 아래로 이동');
        downButton.setAttribute('title', '아래로');
        downButton.setAttribute('data-member-export-column-move', 'down');
        downButton.setAttribute('data-admin-reorder-move', 'down');
        downButton.innerHTML = moveDownIcon;
        order.appendChild(dragHandle);
        order.appendChild(upButton);
        order.appendChild(downButton);
        labelElement.className = 'sr-only';
        labelElement.setAttribute('for', select.id);
        labelElement.textContent = '포함 컬럼';
        actions.className = 'admin-member-export-column-actions-cell';
        removeButton.type = 'button';
        removeButton.className = 'btn btn-sm btn-icon btn-outline-danger admin-member-export-column-remove';
        removeButton.setAttribute('aria-label', label + ' 컬럼 제거');
        removeButton.setAttribute('title', '제거');
        removeButton.setAttribute('data-member-export-column-remove', '');
        removeButton.innerHTML = deleteIcon;
        actions.appendChild(removeButton);
        row.appendChild(order);
        row.appendChild(labelElement);
        row.appendChild(select);
        row.appendChild(actions);
        return row;
    };
    if (!columnList) {
        return;
    }
    columnList.addEventListener('click', function (event) {
        var removeButton = event.target.closest('[data-member-export-column-remove]');
        var row;
        if (removeButton) {
            row = removeButton.closest('[data-member-export-column-row]');
            if (row) {
                row.remove();
                syncColumnRows();
            }
            return;
        }
    });
    columnList.addEventListener('change', function (event) {
        if (event.target && event.target.matches('[data-member-export-column-select]')) {
            syncColumnRows();
        }
    });
    if (addButton) {
        addButton.addEventListener('click', function () {
            var columnKey = firstAvailableColumn();
            var row;
            var select;
            if (columnKey === '') {
                return;
            }
            row = createColumnRow(columnKey);
            columnList.appendChild(row);
            syncColumnRows();
            select = row.querySelector('[data-member-export-column-select]');
            if (select) {
                select.focus();
            }
        });
    }
    syncColumnRows();
}());
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
