<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/reports', 'view');

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_community_settings($pdo);
$allowedStatuses = sr_community_report_statuses();
$allowedReasonKeys = sr_community_report_reason_keys();
$allowedTargetTypes = ['post', 'comment', 'message'];
$reportListFilters = [
    'status' => sr_get_string('status', 30),
    'target_type' => sr_get_string('target_type', 30),
    'reason_key' => sr_get_string('reason_key', 30),
    'field' => sr_get_string('field', 20),
    'q' => trim(sr_get_string('q', 120)),
];
if ($reportListFilters['status'] !== '' && !in_array($reportListFilters['status'], $allowedStatuses, true)) {
    $reportListFilters['status'] = '';
}
if ($reportListFilters['target_type'] !== '' && !in_array($reportListFilters['target_type'], $allowedTargetTypes, true)) {
    $reportListFilters['target_type'] = '';
}
if ($reportListFilters['reason_key'] !== '' && !in_array($reportListFilters['reason_key'], $allowedReasonKeys, true)) {
    $reportListFilters['reason_key'] = '';
}
if (!in_array($reportListFilters['field'], ['all', 'target', 'reporter', 'reported', 'reviewer', 'memo'], true)) {
    $reportListFilters['field'] = 'all';
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/reports', 'edit');

    $reportIdValue = sr_post_string('report_id', 20);
    $reportId = preg_match('/\A[1-9][0-9]*\z/', $reportIdValue) === 1 ? (int) $reportIdValue : 0;
    $status = sr_post_string('status', 30);
    $targetAction = sr_post_string('target_action', 40);
    $reviewNote = sr_post_string_without_truncation('review_note', 1000);
    $report = sr_community_report_by_id($pdo, $reportId);

    if (!is_array($report)) {
        $errors[] = sr_t('community::action.admin.report_not_found');
    }

    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = sr_t('community::action.admin.report_status_invalid');
    }

    if ($reviewNote === null) {
        $errors[] = sr_t('community::action.admin.review_note_too_long');
        $reviewNote = '';
    }
    if (is_array($report) && !array_key_exists($targetAction === '' ? 'none' : $targetAction, sr_community_report_target_action_options((string) $report['target_type']))) {
        $errors[] = '신고 대상 조치 값이 올바르지 않습니다.';
    }

    if ($errors === []) {
        $targetActionResult = sr_community_apply_report_target_action($pdo, $report, $targetAction === '' ? 'none' : $targetAction, (int) $account['id']);
        if (!empty($targetActionResult['error'])) {
            $errors[] = '신고 대상 조치를 적용하지 못했습니다.';
        }
    }

    if ($errors === []) {
        sr_community_update_report_status($pdo, $reportId, $status, (int) $account['id'], (string) $reviewNote);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'community.report.status_updated',
            'target_type' => 'community_report',
            'target_id' => (string) $reportId,
            'result' => 'success',
            'message' => 'Community report status updated.',
            'metadata' => [
                'before_status' => (string) $report['status'],
                'after_status' => $status,
                'review_note_present' => trim((string) $reviewNote) !== '',
                'target_type' => (string) $report['target_type'],
                'target_id' => (int) $report['target_id'],
                'reported_account_id' => (int) $report['reported_account_id'],
                'target_action' => $targetActionResult ?? ['action_key' => 'none', 'applied' => false],
            ],
        ]);
        $notice = sr_t('community::action.admin.report_status_updated');
    }

    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/community/reports');
}

$reportStatusCounts = ['total' => 0];
foreach ($allowedStatuses as $status) {
    $reportStatusCounts[$status] = 0;
}
$reportStatusCountStmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_community_reports GROUP BY status');
foreach ($reportStatusCountStmt->fetchAll() as $row) {
    $status = (string) ($row['status'] ?? '');
    $count = (int) ($row['count_value'] ?? 0);
    if (array_key_exists($status, $reportStatusCounts)) {
        $reportStatusCounts[$status] = $count;
    }
    $reportStatusCounts['total'] += $count;
}

$reportPagination = sr_admin_pagination_from_total($pdo, sr_community_report_count($pdo, $reportListFilters));
$reports = sr_community_reports($pdo, (int) $reportPagination['per_page'], $reportListFilters, sr_admin_pagination_offset($reportPagination));

include SR_ROOT . '/modules/community/views/admin-reports.php';
