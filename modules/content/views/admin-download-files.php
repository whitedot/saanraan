<?php

$editing = is_array($editingFile ?? null);
$formValues = $values !== [] ? $values : ($editing ? $editingFile : [
    'title' => '',
    'status' => 'active',
    'asset_download_enabled' => 0,
    'asset_module' => '',
    'asset_download_amount' => 0,
    'asset_download_amounts_json' => '',
    'asset_download_group_policies_json' => '',
    'asset_download_policy_set_id' => 0,
    'asset_charge_policy' => 'once',
]);
$selectedAssetModules = sr_content_asset_module_keys_from_value($formValues['asset_module'] ?? '');
$adminContainerClass = 'admin-content-download-files admin-ui-scope';
$adminPageTitleUrl = sr_admin_page_title_reset_url(!$showForm, '/admin/content/files');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($showForm) { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/content/files')); ?>" enctype="multipart/form-data" class="admin-form ui-form-theme">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="file_id" value="<?php echo sr_e((string) (int) ($editingFile['id'] ?? 0)); ?>">
        <section class="admin-card card">
            <h2>기본 정보</h2>
            <?php if (!$editing) { ?>
                <div class="admin-form-row">
                    <label class="form-label" for="content_download_file_upload">파일 <span class="sr-required-label">(필수)</span></label>
                    <div class="admin-form-field">
                        <input id="content_download_file_upload" type="file" name="download_file_upload" class="form-input">
                        <p class="admin-form-help"><?php echo sr_e(sr_t('content::ui.pdf.cf7633ac')); ?> <?php echo sr_e(sr_content_format_bytes(sr_content_file_upload_max_bytes())); ?></p>
                    </div>
                </div>
            <?php } else { ?>
                <div class="admin-form-row">
                    <span class="form-label">원본 파일</span>
                    <div class="admin-form-field">
                        <?php echo sr_e((string) ($editingFile['original_name'] ?? '')); ?>
                        <p class="admin-form-help"><?php echo sr_e(sr_content_format_bytes((int) ($editingFile['size_bytes'] ?? 0))); ?></p>
                    </div>
                </div>
            <?php } ?>
            <div class="admin-form-row">
                <label class="form-label" for="content_download_file_title">파일 제목</label>
                <div class="admin-form-field">
                    <input id="content_download_file_title" type="text" name="title" value="<?php echo sr_e((string) ($formValues['title'] ?? '')); ?>" class="form-input form-control-full" maxlength="160">
                    <p class="admin-form-help">공개 콘텐츠 화면의 다운로드 링크에 표시됩니다. 비워 두면 원본 파일명이 사용됩니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_download_file_status">상태</label>
                <div class="admin-form-field">
                    <select id="content_download_file_status" name="status" class="form-select">
                        <option value="active"<?php echo (string) ($formValues['status'] ?? 'active') === 'active' ? ' selected' : ''; ?>>사용</option>
                        <option value="hidden"<?php echo (string) ($formValues['status'] ?? 'active') === 'hidden' ? ' selected' : ''; ?>>숨김</option>
                    </select>
                    <p class="admin-form-help">숨김 파일은 공개 다운로드와 콘텐츠 연결 후보에서 제외됩니다. 기존 콘텐츠 연결도 화면에 표시되지 않습니다.</p>
                </div>
            </div>
        </section>

        <section class="admin-card card">
            <h2>다운로드 과금</h2>
            <div class="admin-form-row">
                <span class="form-label">유료 다운로드 사용</span>
                <div class="admin-form-field">
                    <?php echo sr_admin_switch_html('content_download_file_asset_download_enabled', 'new_content_file_asset_download_enabled', '1', (int) ($formValues['asset_download_enabled'] ?? 0) === 1, '사용'); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_download_file_asset_charge_policy">과금 방식</label>
                <div class="admin-form-field">
                    <select id="content_download_file_asset_charge_policy" name="new_content_file_asset_charge_policy" class="form-select">
                        <?php foreach (sr_content_asset_download_charge_policies() as $policyKey => $policyLabel) { ?>
                            <option value="<?php echo sr_e((string) $policyKey); ?>"<?php echo (string) ($formValues['asset_charge_policy'] ?? 'once') === (string) $policyKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $policyLabel); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">유료 다운로드 자산 설정</span>
                <div class="admin-form-field">
                    <div class="admin-asset-setting-target" data-admin-asset-enable-target="#content_download_file_asset_download_enabled" data-admin-asset-enable-submit-check="always">
                        <?php echo sr_content_asset_grouped_amount_inputs_html('content_download_file_asset_amounts_grouped', 'new_content_file_asset_module', 'new_content_file_asset_download_amounts', $assetModuleOptions, $selectedAssetModules, $formValues['asset_download_amounts_json'] ?? '', (int) ($formValues['asset_download_amount'] ?? 0), '차감 금액', sr_t('content::ui.text.3e195cdd')); ?>
                    </div>
                    <input type="hidden" name="new_content_file_asset_download_amount" value="<?php echo sr_e((string) (int) ($formValues['asset_download_amount'] ?? 0)); ?>">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_download_file_asset_download_policy_set_ids">회원 그룹별 적용</label>
                <div class="admin-form-field admin-policy-set-field">
                    <?php echo sr_content_asset_policy_set_checkboxes_html('content_download_file_asset_download_policy_set_ids', 'new_content_file_asset_download_policy_set_ids', $assetPolicySets, sr_content_asset_policy_set_ids_with_legacy($formValues['asset_download_group_policies_json'] ?? '', (int) ($formValues['asset_download_policy_set_id'] ?? 0)), 'neutral', '', '#content_download_file_asset_amounts_grouped', $pdo); ?>
                </div>
            </div>
        </section>

        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/content/files')); ?>" class="btn btn-solid-light">목록</a>
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
<?php } else { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-summary-stats">
            <span class="admin-summary-meta">총파일 <strong><?php echo sr_e((string) (int) ($downloadFileStatusCounts['total'] ?? 0)); ?>개</strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/content/files?status=active')); ?>" class="admin-summary-meta">사용 <?php echo sr_e((string) (int) ($downloadFileStatusCounts['active'] ?? 0)); ?>개</a>
            <a href="<?php echo sr_e(sr_url('/admin/content/files?status=hidden')); ?>" class="admin-summary-meta">숨김 <?php echo sr_e((string) (int) ($downloadFileStatusCounts['hidden'] ?? 0)); ?>개</a>
        </div>
    </div>
    <?php
    $selectedDownloadFileStatuses = is_array($filters['status'] ?? null) ? $filters['status'] : [];
    $selectedDownloadFileStatus = (string) ($selectedDownloadFileStatuses[0] ?? '');
    ?>
    <form method="get" action="<?php echo sr_e(sr_url('/admin/content/files')); ?>" class="filtering-form filtering filtering-plain admin-content-download-file-filter ui-form-theme">
        <div class="filtering-fields admin-content-download-file-search-grid">
            <label class="filtering-field admin-content-download-file-filter-status" for="content_download_file_filter_status">
                <span class="filtering-label">상태</span>
                <select id="content_download_file_filter_status" name="status" class="form-select filtering-input">
                    <option value="">전체 상태</option>
                    <option value="active"<?php echo $selectedDownloadFileStatus === 'active' ? ' selected' : ''; ?>>사용</option>
                    <option value="hidden"<?php echo $selectedDownloadFileStatus === 'hidden' ? ' selected' : ''; ?>>숨김</option>
                </select>
            </label>
            <div class="filtering-field filtering-field-fill admin-content-download-file-filter-keyword">
                <label for="content_download_file_filter_q" class="filtering-label">검색</label>
                <input id="content_download_file_filter_q" type="text" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="파일 제목, 원본 파일명">
            </div>
            <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('content::ui.search.4b8d541e')); ?></button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <div>
                <h2 class="card-title">다운로드 파일 목록</h2>
            </div>
            <a href="<?php echo sr_e(sr_url('/admin/content/files?new=1')); ?>" class="btn btn-sm btn-outline-secondary">파일 추가</a>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($downloadFileSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url($downloadFileSortOptions, $downloadFileDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="다운로드 파일 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <form id="content-file-bulk-status-form" method="post" action="<?php echo sr_e(sr_url('/admin/content/files')); ?>" class="content-file-bulk-form" data-content-file-bulk-form>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="batch_status">
                <input type="hidden" name="operation_key" value="content.file_set_status">
                <div class="content-file-bulk-actions admin-row-actions" data-content-file-bulk-bar>
                    <div class="content-file-bulk-controls admin-row-actions">
                        <button type="submit" name="target_status" value="active" class="btn btn-sm btn-outline-warning" data-content-file-bulk-submit data-status-label="사용" disabled>사용</button>
                        <button type="submit" name="target_status" value="hidden" class="btn btn-sm btn-outline-warning" data-content-file-bulk-submit data-status-label="숨김" disabled>숨김</button>
                        <button type="button" class="btn btn-sm btn-outline-light" data-content-file-bulk-clear aria-label="선택 해제" title="선택 해제" hidden><?php echo sr_material_icon_html('close'); ?><span data-content-file-selected-count>0</span></button>
                    </div>
                </div>
            </form>
            <?php echo sr_admin_pagination_summary_html($downloadFilePagination); ?>
        </div>
        <div class="table-wrapper">
            <table class="table admin-content-download-file-table">
                <caption class="sr-only">다운로드 파일 목록</caption>
                <thead class="ui-table-head">
                    <tr>
                        <th class="admin-table-checkbox-cell content-file-select-cell">
                            <label class="sr-only" for="content_file_bulk_select_all">현재 페이지 다운로드 파일 전체 선택</label>
                            <input id="content_file_bulk_select_all" type="checkbox" class="form-checkbox" data-content-file-select-all<?php echo $downloadFiles === [] ? ' disabled' : ''; ?>>
                        </th>
                        <th<?php echo sr_admin_sort_aria('title', $downloadFileSort); ?>><?php echo sr_admin_sort_header_html('파일 제목', 'title', $downloadFileSort, $downloadFileSortOptions, $downloadFileDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('original_name', $downloadFileSort); ?>><?php echo sr_admin_sort_header_html('원본 파일명', 'original_name', $downloadFileSort, $downloadFileSortOptions, $downloadFileDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('status', $downloadFileSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $downloadFileSort, $downloadFileSortOptions, $downloadFileDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('size_bytes', $downloadFileSort); ?>><?php echo sr_admin_sort_header_html('크기', 'size_bytes', $downloadFileSort, $downloadFileSortOptions, $downloadFileDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('linked_content_count', $downloadFileSort); ?>><?php echo sr_admin_sort_header_html('연결', 'linked_content_count', $downloadFileSort, $downloadFileSortOptions, $downloadFileDefaultSort); ?></th>
                        <th>다운로드 정책</th>
                        <th<?php echo sr_admin_sort_aria('updated_at', $downloadFileSort); ?>><?php echo sr_admin_sort_header_html('수정일', 'updated_at', $downloadFileSort, $downloadFileSortOptions, $downloadFileDefaultSort); ?></th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($downloadFiles === []) { ?>
                        <tr>
                            <td colspan="9" class="admin-empty-state">등록된 다운로드 파일이 없습니다.</td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($downloadFiles as $downloadFile) { ?>
                            <?php
                            $downloadFileStatus = (string) ($downloadFile['status'] ?? 'active');
                            $statusClass = $downloadFileStatus === 'active' ? 'is-normal' : 'is-left';
                            ?>
                            <tr>
                                <td class="admin-table-checkbox-cell content-file-select-cell">
                                    <label class="sr-only" for="content_file_bulk_select_<?php echo sr_e((string) (int) $downloadFile['id']); ?>"><?php echo sr_e((string) $downloadFile['title']); ?> 선택</label>
                                    <input id="content_file_bulk_select_<?php echo sr_e((string) (int) $downloadFile['id']); ?>" type="checkbox" name="selected_file_ids[]" value="<?php echo sr_e((string) (int) $downloadFile['id']); ?>" class="form-checkbox" form="content-file-bulk-status-form" data-content-file-row-select>
                                </td>
                                <td class="admin-table-break"><strong><?php echo sr_e((string) $downloadFile['title']); ?></strong></td>
                                <td class="admin-table-break"><?php echo sr_e((string) $downloadFile['original_name']); ?></td>
                                <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo $downloadFileStatus === 'active' ? '사용' : '숨김'; ?></span></td>
                                <td class="admin-table-nowrap text-end"><?php echo sr_e(sr_content_format_bytes((int) $downloadFile['size_bytes'])); ?></td>
                                <td class="admin-table-nowrap text-end"><?php echo sr_e(number_format((int) ($downloadFile['linked_content_count'] ?? 0))); ?>개</td>
                                <td class="admin-table-break">
                                    <?php if ((int) ($downloadFile['asset_download_enabled'] ?? 0) === 1) { ?>
                                        <?php echo sr_e(sr_content_asset_module_labels((string) ($downloadFile['asset_module'] ?? ''), $pdo)); ?>
                                        <?php echo sr_e(number_format((int) ($downloadFile['asset_download_amount'] ?? 0))); ?>
                                        · <?php echo sr_e((string) (sr_content_asset_download_charge_policies()[(string) ($downloadFile['asset_charge_policy'] ?? 'once')] ?? $downloadFile['asset_charge_policy'] ?? '')); ?>
                                    <?php } else { ?>
                                        무료
                                    <?php } ?>
                                </td>
                                <td class="admin-table-nowrap"><?php echo sr_content_time_html((string) $downloadFile['updated_at']); ?></td>
                                <td class="admin-table-actions-cell">
                                    <div class="admin-row-actions">
                                        <a href="<?php echo sr_e(sr_url('/admin/content/file-downloads?file_id=' . rawurlencode((string) (int) $downloadFile['id']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="다운로드 내역" title="다운로드 내역"><?php echo sr_material_icon_html('history'); ?></a>
                                        <a href="<?php echo sr_e(sr_url('/admin/content/files?id=' . rawurlencode((string) (int) $downloadFile['id']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="수정" title="수정"><?php echo sr_material_icon_html('edit'); ?></a>
                                        <?php if ($downloadFileStatus === 'active') { ?>
                                            <form method="post" action="<?php echo sr_e(sr_url('/admin/content/files')); ?>" class="admin-inline-form">
                                                <?php echo sr_csrf_field(); ?>
                                                <input type="hidden" name="file_id" value="<?php echo sr_e((string) (int) $downloadFile['id']); ?>">
                                                <input type="hidden" name="action" value="hide">
                                                <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="숨김" title="숨김"><?php echo sr_material_icon_html('visibility_off'); ?></button>
                                            </form>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('history'); ?> 다운로드 내역</span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> 수정</span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('visibility_off'); ?> 숨김</span>
        </div>
        <?php echo sr_admin_status_description_list_html('content_download_file_status', ['active' => '사용', 'hidden' => '숨김']); ?>
    </section>
    <?php echo sr_admin_pagination_html($downloadFilePagination, '다운로드 파일 목록 페이지'); ?>
    <script>
    (function () {
        var bulkForm = document.querySelector('[data-content-file-bulk-form]');
        if (!bulkForm) {
            return;
        }

        var countNode = document.querySelector('[data-content-file-selected-count]');
        var submitButtons = Array.prototype.slice.call(document.querySelectorAll('[data-content-file-bulk-submit]'));
        var clear = document.querySelector('[data-content-file-bulk-clear]');
        var selectAll = document.querySelector('[data-content-file-select-all]');
        var rowChecks = Array.prototype.slice.call(document.querySelectorAll('[data-content-file-row-select]'));

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
            if (!window.confirm('선택한 다운로드 파일 ' + selectedCount + '건의 상태를 "' + statusLabel + '"(으)로 변경합니다.')) {
                event.preventDefault();
            }
        });
        syncBulkState();
    })();
    </script>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
