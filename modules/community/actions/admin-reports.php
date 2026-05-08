<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);

$errors = [];
$notice = '';
$allowedStatuses = toy_community_report_statuses();

if (toy_request_method() === 'POST') {
    toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);
    toy_require_csrf();

    $reportIdValue = toy_post_string('report_id', 20);
    $reportId = preg_match('/\A[1-9][0-9]*\z/', $reportIdValue) === 1 ? (int) $reportIdValue : 0;
    $status = toy_post_string('status', 30);
    $reviewNote = toy_post_string_without_truncation('review_note', 1000);
    $report = toy_community_report_by_id($pdo, $reportId);

    if (!is_array($report)) {
        $errors[] = '신고 항목을 찾을 수 없습니다.';
    }

    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = '신고 상태 값이 올바르지 않습니다.';
    }

    if ($reviewNote === null) {
        $errors[] = '처리 메모는 1000자 이하로 입력해 주세요.';
        $reviewNote = '';
    }

    if ($errors === []) {
        toy_community_update_report_status($pdo, $reportId, $status, (int) $account['id'], (string) $reviewNote);
        toy_audit_log($pdo, [
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
            ],
        ]);
        $notice = '신고 상태를 변경했습니다.';
    }
}

$reports = toy_community_reports($pdo, 100);

include TOY_ROOT . '/modules/community/views/admin-reports.php';
