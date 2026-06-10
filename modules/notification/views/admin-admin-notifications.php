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
<form method="get" action="<?php echo sr_e(sr_url('/admin/admin-notifications')); ?>" class="filtering-form ui-form-theme">
    <div class="filtering filtering-card<?php echo $adminNotificationDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields admin-notification-search-grid">
            <div class="filtering-field">
                <label for="admin_notification_search_field" class="filtering-label">검색조건</label>
                <select id="admin_notification_search_field" name="field" class="form-select filtering-input">
                    <?php foreach (['all' => '전체', 'title' => '제목', 'body' => '내용', 'source' => '출처', 'target' => '대상'] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($adminNotificationFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>><?php echo sr_e($fieldLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="filtering-field filtering-field-fill">
                <label for="admin_notification_search_keyword" class="filtering-label">검색어</label>
                <input id="admin_notification_search_keyword" type="text" name="q" value="<?php echo sr_e((string) ($adminNotificationFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="제목, 출처, 대상">
            </div>
        </div>
        <div id="admin_notification_detail_filters" class="filtering-body" data-filtering-body<?php echo $adminNotificationDetailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field">
                <span class="filtering-label">상태</span>
                <?php echo sr_admin_filter_toggle_group_html('admin_notification_status_filter', 'status', array_intersect_key($adminNotificationStatusLabels, array_flip($allowedAdminNotificationStatuses)), $selectedAdminNotificationStatuses, '전체'); ?>
            </div>
            <div class="filtering-field">
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

<section class="admin-card admin-list-card card">
    <div class="card-header">
        <h2 class="card-title">운영 알림 목록</h2>
    </div>
    <div class="admin-list-summary-row">
        <?php echo sr_admin_pagination_summary_html($adminNotificationPagination); ?>
    </div>
    <table class="table admin-notification-table">
        <thead>
            <tr>
                <th>상태</th>
                <th>중요도</th>
                <th>알림</th>
                <th>출처</th>
                <th>발생</th>
                <th>확인</th>
                <th>작업</th>
            </tr>
        </thead>
        <tbody>
            <?php if (($adminNotifications ?? []) === []) { ?>
                <tr><td colspan="7" class="admin-empty-state">운영 알림이 없습니다.</td></tr>
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
                    <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e($adminNotificationStatusLabels[$status] ?? $status); ?></span></td>
                    <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($severityClass); ?>"><?php echo sr_e($adminNotificationSeverityLabels[$severity] ?? $severity); ?></span></td>
                    <td>
                        <strong><?php echo sr_e((string) ($adminNotification['title'] ?? '')); ?></strong>
                        <?php if ((string) ($adminNotification['body_text'] ?? '') !== '') { ?>
                            <br><span class="text-muted"><?php echo sr_e((string) ($adminNotification['body_text'] ?? '')); ?></span>
                        <?php } ?>
                        <?php if ($targetText !== '') { ?>
                            <br><span class="text-muted"><?php echo sr_e($targetText); ?></span>
                        <?php } ?>
                    </td>
                    <td><?php echo sr_e($sourceText !== '' ? $sourceText : '-'); ?></td>
                    <td class="admin-table-nowrap">
                        <?php echo sr_notification_time_html((string) ($adminNotification['last_occurred_at'] ?? $adminNotification['created_at'] ?? '')); ?>
                        <?php if ((int) ($adminNotification['occurrence_count'] ?? 1) > 1) { ?>
                            <br><span class="text-muted"><?php echo sr_e(number_format((int) $adminNotification['occurrence_count'])); ?>회</span>
                        <?php } ?>
                    </td>
                    <td class="admin-table-nowrap">
                        <?php echo empty($adminNotification['read_at']) ? '미읽음' : '읽음'; ?>
                        <?php if (!empty($adminNotification['acknowledged_at'])) { ?>
                            <br><span class="text-muted">확인함</span>
                        <?php } ?>
                    </td>
                    <td class="admin-table-actions-cell">
                        <div class="admin-row-actions">
                            <?php if ($actionUrl !== '') { ?>
                                <a href="<?php echo sr_e(sr_url($actionUrl)); ?>" class="btn btn-sm btn-solid-light">이동</a>
                            <?php } ?>
                            <?php if (empty($adminNotification['read_at'])) { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/admin-notifications')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="return_to" value="<?php echo sr_e($adminNotificationActionUrl); ?>">
                                    <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $notificationId); ?>">
                                    <button type="submit" name="intent" value="mark_read" class="btn btn-sm btn-solid-light">읽음</button>
                                </form>
                            <?php } ?>
                            <?php if (empty($adminNotification['acknowledged_at'])) { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/admin-notifications')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="return_to" value="<?php echo sr_e($adminNotificationActionUrl); ?>">
                                    <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $notificationId); ?>">
                                    <button type="submit" name="intent" value="acknowledge" class="btn btn-sm btn-solid-primary">확인</button>
                                </form>
                            <?php } ?>
                            <?php if ($status === 'open') { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/admin-notifications')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="return_to" value="<?php echo sr_e($adminNotificationActionUrl); ?>">
                                    <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $notificationId); ?>">
                                    <button type="submit" name="intent" value="process" class="btn btn-sm btn-outline-secondary">처리됨</button>
                                </form>
                            <?php } ?>
                            <?php if ($status !== 'open') { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/admin-notifications')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="return_to" value="<?php echo sr_e($adminNotificationActionUrl); ?>">
                                    <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $notificationId); ?>">
                                    <button type="submit" name="intent" value="reopen" class="btn btn-sm btn-outline-secondary">다시 열기</button>
                                </form>
                            <?php } ?>
                            <?php if ($status !== 'archived') { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/admin-notifications')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="return_to" value="<?php echo sr_e($adminNotificationActionUrl); ?>">
                                    <input type="hidden" name="notification_id" value="<?php echo sr_e((string) $notificationId); ?>">
                                    <button type="submit" name="intent" value="archive" class="btn btn-sm btn-outline-secondary">보관</button>
                                </form>
                            <?php } ?>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</section>

<?php echo sr_admin_pagination_html($adminNotificationPagination, '운영 알림 목록 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
