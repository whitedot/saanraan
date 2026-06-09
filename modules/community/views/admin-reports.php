<?php

$adminPageTitle = '커뮤니티 신고 관리';
$adminPageSubtitle = sr_t('community::ui.status.search.9842179b');
$adminContainerClass = 'admin-page-community-report-list admin-ui-scope';
$reportListFilters = isset($reportListFilters) && is_array($reportListFilters) ? $reportListFilters : ['status' => [], 'target_type' => [], 'reason_key' => [], 'field' => 'all', 'q' => ''];
$reportStatusCounts = isset($reportStatusCounts) && is_array($reportStatusCounts) ? $reportStatusCounts : [];
$allowedStatuses = isset($allowedStatuses) && is_array($allowedStatuses) ? $allowedStatuses : [];
$allowedReasonKeys = isset($allowedReasonKeys) && is_array($allowedReasonKeys) ? $allowedReasonKeys : [];
$allowedTargetTypes = isset($allowedTargetTypes) && is_array($allowedTargetTypes) ? $allowedTargetTypes : ['post', 'comment', 'message'];
$reportTargetLabels = [
    'post' => sr_t('community::ui.text.0b138cfe'),
    'comment' => sr_t('community::ui.text.c9fff683'),
    'message' => sr_t('community::ui.text.919bd592'),
];
$totalReports = (int) ($reportStatusCounts['total'] ?? count($reports ?? []));
$selectedReportStatuses = is_array($reportListFilters['status'] ?? null) ? $reportListFilters['status'] : [];
$selectedReportTargetTypes = is_array($reportListFilters['target_type'] ?? null) ? $reportListFilters['target_type'] : [];
$selectedReportReasonKeys = is_array($reportListFilters['reason_key'] ?? null) ? $reportListFilters['reason_key'] : [];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo sr_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo sr_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<div class="admin-local-nav-wrap">
    <div class="admin-local-nav">
        <a href="<?php echo sr_e(sr_url('/admin/community/reports')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('community::ui.all.e078b14a')); ?></a>
    </div>
    <div class="admin-summary-stats">
        <span class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.5648e366')); ?> <strong><?php echo sr_e((string) $totalReports); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></strong></span>
        <a href="<?php echo sr_e(sr_url('/admin/community/reports?status=open')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.d995d6ab')); ?> <?php echo sr_e((string) ($reportStatusCounts['open'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/community/reports?status=reviewing')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.7e0e2126')); ?> <?php echo sr_e((string) ($reportStatusCounts['reviewing'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/community/reports?status=resolved')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.460f7d7a')); ?> <?php echo sr_e((string) ($reportStatusCounts['resolved'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/community/reports?status=dismissed')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.0d655420')); ?> <?php echo sr_e((string) ($reportStatusCounts['dismissed'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
    </div>
</div>

<?php $communityReportDetailFilterOpen = $selectedReportStatuses !== [] || $selectedReportTargetTypes !== [] || $selectedReportReasonKeys !== []; ?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/community/reports')); ?>" class="filtering-form admin-community-report-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $communityReportDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields admin-community-report-search-grid">
            <div class="filtering-field admin-community-report-filter-field">
                <label for="community_admin_reports_field" class="filtering-label">검색조건</label>
                <select id="community_admin_reports_field" name="field" class="form-select filtering-input">
                    <?php foreach (['all' => sr_t('community::ui.all.a4b69faf'), 'target' => sr_t('community::ui.text.8c609deb'), 'reporter' => sr_t('community::ui.text.84780e6f'), 'reported' => sr_t('community::ui.member.7a284377'), 'reviewer' => sr_t('community::ui.text.750086e9'), 'memo' => sr_t('community::ui.text.c8a14bcd')] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($reportListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="filtering-field filtering-field-fill admin-community-report-filter-keyword">
                <label for="community_admin_reports_q" class="filtering-label"><?php echo sr_e(sr_t('community::ui.search.bda397fc')); ?></label>
                <input id="community_admin_reports_q" type="text" name="q" value="<?php echo sr_e((string) ($reportListFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('community::ui.member.fbbe7c33')); ?>">
            </div>
        </div>
        <div id="community_report_detail_filters" class="filtering-body" data-filtering-body<?php echo $communityReportDetailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field admin-community-report-filter-status">
                <span class="filtering-label"><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?></span>
                <?php echo sr_admin_filter_toggle_group_html('community_admin_reports_status_filter', 'status', sr_admin_code_label_options($allowedStatuses, 'report_status'), $selectedReportStatuses, sr_t('community::ui.all.a4b69faf')); ?>
            </div>
            <div class="filtering-field admin-community-report-filter-target">
                <span class="filtering-label"><?php echo sr_e(sr_t('community::ui.text.8c609deb')); ?></span>
                <?php
                $reportTargetTypeFilterOptions = [];
                foreach ($allowedTargetTypes as $targetType) {
                    $reportTargetTypeFilterOptions[(string) $targetType] = (string) ($reportTargetLabels[$targetType] ?? sr_admin_code_label($targetType, 'target_type'));
                }
                echo sr_admin_filter_toggle_group_html('community_admin_reports_target_type_filter', 'target_type', $reportTargetTypeFilterOptions, $selectedReportTargetTypes, sr_t('community::ui.all.a4b69faf'));
                ?>
            </div>
            <div class="filtering-field admin-community-report-filter-reason">
                <span class="filtering-label"><?php echo sr_e(sr_t('community::ui.text.ab9442a2')); ?></span>
                <?php
                $reportReasonFilterOptions = [];
                foreach ($allowedReasonKeys as $reasonKey) {
                    $reportReasonFilterOptions[(string) $reasonKey] = sr_community_report_reason_label((string) $reasonKey);
                }
                echo sr_admin_filter_toggle_group_html('community_admin_reports_reason_filter', 'reason_key', $reportReasonFilterOptions, $selectedReportReasonKeys, sr_t('community::ui.all.a4b69faf'));
                ?>
            </div>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $communityReportDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="community_report_detail_filters">상세검색</button>
            <button type="button" class="btn btn-outline-light" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
            <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('community::ui.search.4b8d541e')); ?></button>
        </div>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header"><h2 class="card-title"><?php echo sr_e(sr_t('community::ui.list.27da9d14')); ?></h2></div>
    <?php echo sr_admin_pagination_summary_html($reportPagination); ?>
    <form id="community-report-bulk-status-form" method="post" action="<?php echo sr_e(sr_url('/admin/community/reports')); ?>" class="community-report-bulk-form" data-community-report-bulk-form>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="batch_status">
        <input type="hidden" name="operation_key" value="community.report_set_status">
        <div class="admin-list-actions community-report-bulk-actions" hidden data-community-report-bulk-bar>
            <div class="community-report-bulk-summary" aria-live="polite">
                <strong data-community-report-selected-count>0</strong>개 선택됨
            </div>
            <div class="community-report-bulk-controls">
                <select name="target_status" class="form-select" aria-label="변경할 신고 상태">
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"><?php echo sr_e(sr_admin_code_label($status, 'report_status')); ?></option>
                    <?php } ?>
                </select>
                <input type="text" name="review_note" class="form-input" maxlength="1000" placeholder="<?php echo sr_e(sr_t('community::ui.text.514556d0')); ?>" aria-label="<?php echo sr_e(sr_t('community::ui.text.514556d0')); ?>">
                <button type="submit" class="btn btn-solid-primary" data-community-report-bulk-submit disabled>상태 변경</button>
                <button type="button" class="btn btn-solid-light" data-community-report-bulk-clear>선택 해제</button>
            </div>
        </div>
    </form>
    <div class="table-wrapper">
    <table class="table admin-community-report-table">
        <caption class="sr-only"><?php echo sr_e(sr_t('community::ui.community.list.b4e41b31')); ?></caption>
        <thead class="ui-table-head">
            <tr>
                <th class="community-report-select-cell">
                    <label class="sr-only" for="community_report_bulk_select_all">현재 페이지 신고 전체 선택</label>
                    <input id="community_report_bulk_select_all" type="checkbox" class="form-checkbox" data-community-report-select-all<?php echo $reports === [] ? ' disabled' : ''; ?>>
                </th>
                <th><?php echo sr_e(sr_t('community::ui.text.8c609deb')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.text.ab9442a2')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.text.84780e6f')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.member.7a284377')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.text.c8a14bcd')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.text.ebc9b96e')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.text.750086e9')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.text.73bb6cce')); ?></th>
                <th class="text-end"><?php echo sr_e(sr_t('community::ui.text.460f7d7a')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($reports === []) { ?>
                <tr>
                    <td colspan="11" class="admin-empty-state"><?php echo sr_e(sr_t('community::ui.text.7efff05f')); ?></td>
                </tr>
            <?php } else { ?>
                <?php foreach ($reports as $report) { ?>
                    <?php
                    $reportStatus = (string) $report['status'];
                    $statusClass = match ($reportStatus) {
                        'resolved' => 'is-normal',
                        'open', 'reviewing' => 'is-blocked',
                        default => 'is-left',
                    };
                    $targetType = (string) $report['target_type'];
                    $targetLabel = (string) ($reportTargetLabels[$targetType] ?? sr_admin_code_label($targetType, 'target_type'));
                    $targetId = (int) ($report['target_id'] ?? 0);
                    $targetSummary = $targetType !== '' && $targetId > 0 ? $targetType . ' #' . (string) $targetId : '';
                    $reportStatusSelectId = 'community_admin_report_status_' . (string) $report['id'];
                    $reportReviewNoteId = 'community_admin_report_review_note_' . (string) $report['id'];
                    ?>
                    <tr>
                        <td class="community-report-select-cell">
                            <label class="sr-only" for="community_report_bulk_select_<?php echo sr_e((string) (int) $report['id']); ?>"><?php echo sr_e($targetLabel); ?> 신고 선택</label>
                            <input id="community_report_bulk_select_<?php echo sr_e((string) (int) $report['id']); ?>" type="checkbox" name="selected_report_ids[]" value="<?php echo sr_e((string) (int) $report['id']); ?>" class="form-checkbox" form="community-report-bulk-status-form" data-community-report-row-select>
                        </td>
                        <td class="admin-table-nowrap admin-community-report-target-cell">
                            <?php echo sr_e($targetLabel); ?>
                            <?php if ($targetSummary !== '') { ?>
                                <span class="admin-table-subtext"><?php echo sr_e($targetSummary); ?></span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap admin-community-report-reason-cell"><?php echo sr_e(sr_community_report_reason_label((string) $report['reason_key'])); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($reportStatus, 'report_status')); ?></span></td>
                        <td class="admin-table-break admin-community-report-account-cell"><?php echo sr_e(sr_community_report_account_label(
                            is_string($report['reporter_display_name'] ?? null) ? $report['reporter_display_name'] : null,
                            (int) $report['reporter_account_id'],
                            is_string($report['reporter_account_status'] ?? null) ? $report['reporter_account_status'] : null,
                            is_string($report['reporter_nickname'] ?? null) ? $report['reporter_nickname'] : null,
                            isset($memberSettings) && is_array($memberSettings) ? $memberSettings : null
                        )); ?></td>
                        <td class="admin-table-break admin-community-report-account-cell"><?php echo sr_e(sr_community_report_account_label(
                            is_string($report['reported_display_name'] ?? null) ? $report['reported_display_name'] : null,
                            (int) $report['reported_account_id'],
                            is_string($report['reported_account_status'] ?? null) ? $report['reported_account_status'] : null,
                            is_string($report['reported_nickname'] ?? null) ? $report['reported_nickname'] : null,
                            isset($memberSettings) && is_array($memberSettings) ? $memberSettings : null
                        )); ?></td>
                        <td class="admin-table-break admin-community-report-memo-cell"><?php echo sr_e((string) ($report['memo_text'] ?? '')); ?></td>
                        <td class="admin-table-nowrap admin-community-report-date-cell"><?php echo sr_community_time_html((string) $report['created_at']); ?></td>
                        <td class="admin-table-break admin-community-report-account-cell">
                            <?php if ((int) ($report['reviewer_account_id'] ?? 0) > 0) { ?>
                                <?php echo sr_e(sr_community_report_account_label(
                                    is_string($report['reviewer_display_name'] ?? null) ? $report['reviewer_display_name'] : null,
                                    (int) $report['reviewer_account_id'],
                                    is_string($report['reviewer_account_status'] ?? null) ? $report['reviewer_account_status'] : null,
                                    is_string($report['reviewer_nickname'] ?? null) ? $report['reviewer_nickname'] : null,
                                    isset($memberSettings) && is_array($memberSettings) ? $memberSettings : null
                                )); ?>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap admin-community-report-date-cell"><?php echo sr_e((string) ($report['reviewed_at'] ?? '')); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/community/reports')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="report_id" value="<?php echo sr_e((string) $report['id']); ?>">
                                    <label for="<?php echo sr_e($reportStatusSelectId); ?>" class="sr-only"><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                                    <select id="<?php echo sr_e($reportStatusSelectId); ?>" name="status" class="form-select">
                                            <?php foreach ($allowedStatuses as $status) { ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $status === (string) $report['status'] ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'report_status')); ?></option>
                                            <?php } ?>
                                        </select>
                                    <label for="<?php echo sr_e($reportReviewNoteId); ?>" class="sr-only"><?php echo sr_e(sr_t('community::ui.text.514556d0')); ?></label>
                                    <textarea id="<?php echo sr_e($reportReviewNoteId); ?>" name="review_note" rows="2" cols="24" class="form-textarea" placeholder="<?php echo sr_e(sr_t('community::ui.text.514556d0')); ?>"><?php echo sr_e((string) ($report['review_note'] ?? '')); ?></textarea>
                                    <label class="sr-only" for="community_admin_report_target_action_<?php echo sr_e((string) $report['id']); ?>">대상 조치</label>
                                    <select id="community_admin_report_target_action_<?php echo sr_e((string) $report['id']); ?>" name="target_action" class="form-select">
                                        <?php foreach (sr_community_report_target_action_options((string) $report['target_type']) as $actionKey => $actionLabel) { ?>
                                            <option value="<?php echo sr_e((string) $actionKey); ?>"><?php echo sr_e((string) $actionLabel); ?></option>
                                        <?php } ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('community::ui.text.16f64fe4')); ?></button>
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
<?php echo sr_admin_pagination_html($reportPagination, '신고 목록 페이지'); ?>

<script>
(function () {
    var bulkForm = document.querySelector('[data-community-report-bulk-form]');
    if (!bulkForm) {
        return;
    }

    var bar = document.querySelector('[data-community-report-bulk-bar]');
    var countNode = document.querySelector('[data-community-report-selected-count]');
    var submit = document.querySelector('[data-community-report-bulk-submit]');
    var clear = document.querySelector('[data-community-report-bulk-clear]');
    var selectAll = document.querySelector('[data-community-report-select-all]');
    var rowChecks = Array.prototype.slice.call(document.querySelectorAll('[data-community-report-row-select]'));

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
        if (!window.confirm('선택한 신고 ' + selectedCount + '건의 상태를 "' + statusLabel + '"(으)로 변경합니다.')) {
            event.preventDefault();
        }
    });
    syncBulkState();
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
