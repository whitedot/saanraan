<?php

$adminPageTitle = '운영 알림';
$adminPageSubtitle = '관리자 모드에서 조치하거나 확인해야 하는 운영 이벤트를 확인합니다.';
$adminContainerClass = 'admin-page-admin-notifications admin-ui-scope';
$adminNotificationFilters = isset($adminNotificationFilters) && is_array($adminNotificationFilters) ? $adminNotificationFilters : ['status' => ['open'], 'severity' => [], 'field' => 'all', 'q' => ''];
$adminNotificationStatusCounts = isset($adminNotificationStatusCounts) && is_array($adminNotificationStatusCounts) ? $adminNotificationStatusCounts : [];
$allowedAdminNotificationStatuses = isset($allowedAdminNotificationStatuses) && is_array($allowedAdminNotificationStatuses) ? $allowedAdminNotificationStatuses : sr_notification_admin_operation_statuses();
$allowedAdminNotificationSeverities = isset($allowedAdminNotificationSeverities) && is_array($allowedAdminNotificationSeverities) ? $allowedAdminNotificationSeverities : sr_notification_admin_severities();
$selectedAdminNotificationStatuses = is_array($adminNotificationFilters['status'] ?? null) ? $adminNotificationFilters['status'] : [];
$selectedAdminNotificationSeverities = is_array($adminNotificationFilters['severity'] ?? null) ? $adminNotificationFilters['severity'] : [];
$adminNotificationActionUrl = sr_admin_current_get_url('/admin/admin-notifications');
$adminNotificationStatusLabels = [
    'open' => '열림',
    'processed' => '처리됨',
    'archived' => '보관됨',
];
$adminNotificationSeverityLabels = [
    'info' => '정보',
    'warning' => '주의',
    'danger' => '긴급',
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-local-nav-wrap">
    <div class="admin-local-nav">
        <a href="<?php echo sr_e(sr_url('/admin/admin-notifications')); ?>" class="btn btn-solid-light">전체</a>
    </div>
    <div class="admin-summary-stats">
        <span class="admin-summary-meta">전체 <strong><?php echo sr_e((string) ($adminNotificationStatusCounts['total'] ?? 0)); ?>건</strong></span>
        <?php foreach ($allowedAdminNotificationStatuses as $status) { ?>
            <a href="<?php echo sr_e(sr_url('/admin/admin-notifications?status=' . rawurlencode((string) $status))); ?>" class="admin-summary-meta">
                <?php echo sr_e($adminNotificationStatusLabels[$status] ?? $status); ?> <?php echo sr_e((string) ($adminNotificationStatusCounts[$status] ?? 0)); ?>건
            </a>
        <?php } ?>
    </div>
</div>

<?php $adminNotificationDetailFilterOpen = $selectedAdminNotificationStatuses !== [] || $selectedAdminNotificationSeverities !== []; ?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/admin-notifications')); ?>" class="filtering-form admin-notification-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $adminNotificationDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields admin-notification-search-grid">
            <div class="filtering-field admin-notification-filter-field">
                <label for="admin_notification_search_field" class="filtering-label">검색조건</label>
                <select id="admin_notification_search_field" name="field" class="form-select filtering-input">
                    <?php foreach (['all' => '전체', 'title' => '제목', 'body' => '내용', 'source' => '출처', 'target' => '대상'] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($adminNotificationFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>><?php echo sr_e($fieldLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="filtering-field filtering-field-fill admin-notification-filter-keyword">
                <label for="admin_notification_search_keyword" class="filtering-label">검색어</label>
                <input id="admin_notification_search_keyword" type="text" name="q" value="<?php echo sr_e((string) ($adminNotificationFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="제목, 출처, 대상">
            </div>
        </div>
        <div id="admin_notification_detail_filters" class="filtering-body" data-filtering-body<?php echo $adminNotificationDetailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field admin-notification-filter-status">
                <span class="filtering-label">상태</span>
                <?php echo sr_admin_filter_toggle_group_html('admin_notification_status_filter', 'status', array_intersect_key($adminNotificationStatusLabels, array_flip($allowedAdminNotificationStatuses)), $selectedAdminNotificationStatuses, '전체'); ?>
            </div>
            <div class="filtering-field admin-notification-filter-severity">
                <span class="filtering-label">중요도</span>
                <?php echo sr_admin_filter_toggle_group_html('admin_notification_severity_filter', 'severity', array_intersect_key($adminNotificationSeverityLabels, array_flip($allowedAdminNotificationSeverities)), $selectedAdminNotificationSeverities, '전체'); ?>
            </div>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $adminNotificationDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="admin_notification_detail_filters">상세검색</button>
            <button type="button" class="btn btn-outline-light" data-filtering-reset><?php echo sr_material_icon_html('restart_alt'); ?>초기화</button>
            <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
        </div>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">운영 알림 목록</h2>
    </div>
    <div class="admin-list-summary-row">
        <form id="admin-notification-bulk-form" method="post" action="<?php echo sr_e(sr_url('/admin/admin-notifications')); ?>" class="admin-notification-bulk-form" data-admin-notification-bulk-form>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="return_to" value="<?php echo sr_e($adminNotificationActionUrl); ?>">
            <div class="admin-notification-bulk-actions admin-row-actions" data-admin-notification-bulk-bar>
                <div class="admin-notification-bulk-controls admin-row-actions">
                    <button type="submit" name="intent" value="batch_mark_read" class="btn btn-sm btn-outline-secondary" data-admin-notification-bulk-submit data-action-label="읽음" title="선택한 운영 알림을 내 계정 기준 읽음 상태로 변경합니다." disabled>읽음</button>
                    <button type="submit" name="intent" value="batch_acknowledge" class="btn btn-sm btn-outline-secondary" data-admin-notification-bulk-submit data-action-label="확인" title="선택한 운영 알림을 읽음 처리하고 확인 완료 시각을 기록합니다." disabled>확인</button>
                    <button type="submit" name="intent" value="batch_process" class="btn btn-sm btn-outline-secondary" data-admin-notification-bulk-submit data-action-label="처리됨" title="선택한 열린 운영 알림을 조치 완료 상태로 변경합니다." disabled>처리됨</button>
                    <button type="submit" name="intent" value="batch_reopen" class="btn btn-sm btn-outline-secondary" data-admin-notification-bulk-submit data-action-label="다시 열기" title="선택한 처리됨 또는 보관 알림을 열린 상태로 되돌립니다." disabled>다시 열기</button>
                    <button type="submit" name="intent" value="batch_archive" class="btn btn-sm btn-outline-secondary" data-admin-notification-bulk-submit data-action-label="보관" title="선택한 운영 알림을 보관 상태로 변경합니다." disabled>보관</button>
                    <button type="button" class="btn btn-sm btn-outline-light" data-admin-notification-bulk-clear aria-label="선택 해제" title="선택 해제" hidden><?php echo sr_material_icon_html('close'); ?><span data-admin-notification-selected-count>0</span></button>
                </div>
            </div>
        </form>
        <?php echo sr_admin_pagination_summary_html($adminNotificationPagination); ?>
    </div>
    <div class="table-wrapper">
    <table class="table admin-notification-table">
        <caption class="sr-only">운영 알림 목록</caption>
        <thead class="ui-table-head">
            <tr>
                <th class="admin-notification-select-cell">
                    <label class="sr-only" for="admin_notification_bulk_select_all">현재 페이지 운영 알림 전체 선택</label>
                    <input id="admin_notification_bulk_select_all" type="checkbox" class="form-checkbox" data-admin-notification-select-all<?php echo (($adminNotifications ?? []) === []) ? ' disabled' : ''; ?>>
                </th>
                <th class="admin-notification-status-head">상태</th>
                <th class="admin-notification-severity-head">중요도</th>
                <th class="admin-notification-message-head">알림</th>
                <th class="admin-notification-source-head">출처</th>
                <th class="admin-notification-time-head">발생</th>
                <th class="admin-notification-read-head">확인</th>
                <th class="admin-notification-actions-head text-end">작업</th>
            </tr>
        </thead>
        <tbody>
            <?php if (($adminNotifications ?? []) === []) { ?>
                <tr><td colspan="8" class="admin-empty-state">운영 알림이 없습니다.</td></tr>
            <?php } ?>
            <?php foreach (($adminNotifications ?? []) as $adminNotification) { ?>
                <?php
                $notificationId = (int) ($adminNotification['id'] ?? 0);
                $status = (string) ($adminNotification['status'] ?? 'open');
                $severity = (string) ($adminNotification['severity'] ?? 'info');
                $statusClass = match ($status) {
                    'open' => 'is-warning',
                    'processed' => 'is-normal',
                    default => 'is-muted',
                };
                $severityClass = match ($severity) {
                    'danger' => 'is-left',
                    'warning' => 'is-warning',
                    default => 'is-normal',
                };
                $sourceText = trim((string) ($adminNotification['source_module_key'] ?? '') . ' / ' . (string) ($adminNotification['event_key'] ?? ''), ' /');
                $targetText = trim((string) ($adminNotification['target_type'] ?? '') . ' #' . (string) ($adminNotification['target_id'] ?? ''), ' #');
                $actionUrl = sr_notification_admin_clean_action_url((string) ($adminNotification['action_url'] ?? ''));
                ?>
                <tr>
                    <td class="admin-notification-select-cell">
                        <label class="sr-only" for="admin_notification_bulk_select_<?php echo sr_e((string) $notificationId); ?>"><?php echo sr_e((string) ($adminNotification['title'] ?? '')); ?> 선택</label>
                        <input id="admin_notification_bulk_select_<?php echo sr_e((string) $notificationId); ?>" type="checkbox" name="selected_admin_notification_ids[]" value="<?php echo sr_e((string) $notificationId); ?>" class="form-checkbox" form="admin-notification-bulk-form" data-admin-notification-row-select>
                    </td>
                    <td class="admin-table-nowrap admin-notification-status-cell"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e($adminNotificationStatusLabels[$status] ?? $status); ?></span></td>
                    <td class="admin-table-nowrap admin-notification-severity-cell"><span class="admin-status <?php echo sr_e($severityClass); ?>"><?php echo sr_e($adminNotificationSeverityLabels[$severity] ?? $severity); ?></span></td>
                    <td class="admin-table-break admin-notification-message-cell">
                        <strong><?php echo sr_e((string) ($adminNotification['title'] ?? '')); ?></strong>
                        <?php if ((string) ($adminNotification['body_text'] ?? '') !== '') { ?>
                            <br><span class="text-muted"><?php echo sr_e((string) ($adminNotification['body_text'] ?? '')); ?></span>
                        <?php } ?>
                        <?php if ($targetText !== '') { ?>
                            <br><span class="text-muted"><?php echo sr_e($targetText); ?></span>
                        <?php } ?>
                    </td>
                    <td class="admin-table-break admin-notification-source-cell"><?php echo sr_e($sourceText !== '' ? $sourceText : '-'); ?></td>
                    <td class="admin-table-nowrap admin-notification-time-cell">
                        <?php echo sr_notification_time_html((string) ($adminNotification['last_occurred_at'] ?? $adminNotification['created_at'] ?? '')); ?>
                        <?php if ((int) ($adminNotification['occurrence_count'] ?? 1) > 1) { ?>
                            <br><span class="text-muted"><?php echo sr_e(number_format((int) $adminNotification['occurrence_count'])); ?>회</span>
                        <?php } ?>
                    </td>
                    <td class="admin-table-nowrap admin-notification-read-cell">
                        <?php echo empty($adminNotification['read_at']) ? '안읽음' : '읽음'; ?>
                        <?php if (!empty($adminNotification['acknowledged_at'])) { ?>
                            <br><span class="text-muted">확인함</span>
                        <?php } ?>
                    </td>
                    <td class="admin-table-actions-cell">
                        <div class="admin-row-actions">
                            <?php if ($actionUrl !== '') { ?>
                                <a href="<?php echo sr_e(sr_url($actionUrl)); ?>" class="btn btn-sm btn-outline-secondary" aria-label="관련 관리자 화면으로 이동" title="관련 관리자 화면으로 이동">이동</a>
                            <?php } ?>
                            <?php if (empty($adminNotification['read_at'])) { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/admin-notifications')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="return_to" value="<?php echo sr_e($adminNotificationActionUrl); ?>">
                                    <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $notificationId); ?>">
                                    <button type="submit" name="intent" value="mark_read" class="btn btn-sm btn-outline-secondary" aria-label="내 계정 기준 읽음 상태로 변경" title="내 계정 기준 읽음 상태로 변경">읽음</button>
                                </form>
                            <?php } ?>
                            <?php if (empty($adminNotification['acknowledged_at'])) { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/admin-notifications')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="return_to" value="<?php echo sr_e($adminNotificationActionUrl); ?>">
                                    <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $notificationId); ?>">
                                    <button type="submit" name="intent" value="acknowledge" class="btn btn-sm btn-outline-secondary" aria-label="읽음 처리하고 확인 완료 시각 기록" title="읽음 처리하고 확인 완료 시각 기록">확인</button>
                                </form>
                            <?php } ?>
                            <?php if ($status === 'open') { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/admin-notifications')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="return_to" value="<?php echo sr_e($adminNotificationActionUrl); ?>">
                                    <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $notificationId); ?>">
                                    <button type="submit" name="intent" value="process" class="btn btn-sm btn-outline-secondary" aria-label="조치 완료 상태로 변경" title="조치 완료 상태로 변경">처리됨</button>
                                </form>
                            <?php } ?>
                            <?php if ($status !== 'open') { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/admin-notifications')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="return_to" value="<?php echo sr_e($adminNotificationActionUrl); ?>">
                                    <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $notificationId); ?>">
                                    <button type="submit" name="intent" value="reopen" class="btn btn-sm btn-outline-secondary" aria-label="운영 알림을 열린 상태로 되돌림" title="운영 알림을 열린 상태로 되돌림">다시 열기</button>
                                </form>
                            <?php } ?>
                            <?php if ($status !== 'archived') { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/admin-notifications')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="return_to" value="<?php echo sr_e($adminNotificationActionUrl); ?>">
                                    <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $notificationId); ?>">
                                    <button type="submit" name="intent" value="archive" class="btn btn-sm btn-outline-secondary" aria-label="운영 알림을 보관 상태로 변경" title="운영 알림을 보관 상태로 변경">보관</button>
                                </form>
                            <?php } ?>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    </div>
</section>

<?php echo sr_admin_pagination_html($adminNotificationPagination, '운영 알림 목록 페이지'); ?>

<script>
(function () {
    var bulkForm = document.querySelector('[data-admin-notification-bulk-form]');
    if (!bulkForm) {
        return;
    }

    var countNode = document.querySelector('[data-admin-notification-selected-count]');
    var submitButtons = Array.prototype.slice.call(document.querySelectorAll('[data-admin-notification-bulk-submit]'));
    var clear = document.querySelector('[data-admin-notification-bulk-clear]');
    var selectAll = document.querySelector('[data-admin-notification-select-all]');
    var rowChecks = Array.prototype.slice.call(document.querySelectorAll('[data-admin-notification-row-select]'));

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
        var actionLabel = submitter && submitter.getAttribute ? submitter.getAttribute('data-action-label') : '';
        if (!actionLabel) {
            actionLabel = submitter && submitter.textContent ? submitter.textContent.replace(/\s+/g, ' ').trim() : '선택한 작업';
        }
        if (!window.confirm('선택한 운영 알림 ' + selectedCount + '건에 "' + actionLabel + '" 작업을 적용합니다.')) {
            event.preventDefault();
        }
    });
    syncBulkState();
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
