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
$memberSettings = sr_member_settings($pdo);
$allowedStatuses = sr_community_report_statuses();
$allowedReasonKeys = sr_community_report_reason_keys();
$allowedTargetTypes = ['post', 'comment', 'message'];
$reportListFilters = [
    'status' => sr_admin_get_allowed_array('status', $allowedStatuses, 30),
    'target_type' => sr_admin_get_allowed_array('target_type', $allowedTargetTypes, 30),
    'reason_key' => sr_admin_get_allowed_array('reason_key', $allowedReasonKeys, 30),
    'field' => sr_get_string('field', 20),
    'q' => trim(sr_get_string('q', 120)),
];
if (!in_array($reportListFilters['field'], ['all', 'target', 'reporter', 'reported', 'reviewer', 'memo'], true)) {
    $reportListFilters['field'] = 'all';
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/reports', 'edit');

    $intent = sr_post_string('intent', 40);

    if ($intent === 'batch_status') {
        $operationKey = sr_post_string('operation_key', 80);
        $targetStatus = sr_post_string('target_status', 30);
        $targetAction = sr_post_string('target_action', 40);
        $reviewNote = sr_post_string_without_truncation('review_note', 1000);
        $rawSelectedIds = $_POST['selected_report_ids'] ?? [];
        $selectedIds = sr_admin_positive_int_list_from_input($rawSelectedIds, $hasInvalidSelectedId);
        $normalizedTargetAction = $targetAction === '' ? 'none' : $targetAction;
        $batchTargetActionOptions = sr_community_report_batch_target_action_options();

        if ($operationKey !== 'community.report_set_status') {
            $errors[] = '허용되지 않은 신고 일괄 작업입니다.';
        }
        if (!in_array($targetStatus, $allowedStatuses, true)) {
            $errors[] = sr_t('community::action.admin.report_status_invalid');
        }
        if (!array_key_exists($normalizedTargetAction, $batchTargetActionOptions)) {
            $errors[] = '신고 대상 조치 값이 올바르지 않습니다.';
        }
        $targetActionPolicyError = sr_community_report_target_action_policy_error($targetStatus, $normalizedTargetAction);
        if ($targetActionPolicyError !== '') {
            $errors[] = $targetActionPolicyError;
        }
        if ($selectedIds === []) {
            $errors[] = '상태를 변경할 신고를 선택하세요.';
        }
        if ($hasInvalidSelectedId) {
            $errors[] = '선택한 신고 ID 값이 올바르지 않습니다.';
        }
        if (count($selectedIds) > 100) {
            $errors[] = '신고 상태 일괄 변경은 한 번에 100건 이하로 실행하세요.';
        }
        if ($reviewNote === null) {
            $errors[] = sr_t('community::action.admin.review_note_too_long');
            $reviewNote = '';
        }
        if ($reviewNote !== null && trim((string) $reviewNote) === '') {
            $errors[] = '처리 메모를 입력하세요.';
        }

        $selectedReports = [];
        if ($errors === []) {
            $placeholders = [];
            $params = [];
            foreach ($selectedIds as $index => $selectedId) {
                $paramKey = 'report_id_' . (string) $index;
                $placeholders[] = ':' . $paramKey;
                $params[$paramKey] = $selectedId;
            }
            $stmt = $pdo->prepare(
                'SELECT id, status, target_type, target_id, reported_account_id
                 FROM sr_community_reports
                 WHERE id IN (' . implode(', ', $placeholders) . ')'
            );
            foreach ($params as $paramKey => $selectedId) {
                $stmt->bindValue($paramKey, $selectedId, PDO::PARAM_INT);
            }
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $selectedReports[(int) $row['id']] = $row;
            }
            if (count($selectedReports) !== count($selectedIds)) {
                $errors[] = '선택한 신고 중 찾을 수 없는 항목이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
            }
            if ($normalizedTargetAction !== 'none') {
                foreach ($selectedIds as $selectedId) {
                    $selectedReport = $selectedReports[$selectedId] ?? null;
                    if (!is_array($selectedReport)) {
                        continue;
                    }
                    $mappedTargetAction = sr_community_report_batch_target_action_for_report($normalizedTargetAction, (string) ($selectedReport['target_type'] ?? ''));
                    if ($mappedTargetAction === '' || !array_key_exists($mappedTargetAction, sr_community_report_target_action_options((string) ($selectedReport['target_type'] ?? '')))) {
                        $errors[] = '선택한 신고 대상 유형에는 해당 대상 조치를 일괄 적용할 수 없습니다.';
                        break;
                    }
                }
            }
        }

        if ($errors === [] && $selectedReports !== []) {
            $processedCount = 0;
            $statusChangedCount = 0;
            $sameStatusCount = 0;
            $targetActionAppliedCount = 0;
            $targetActionResults = [];
            $batchFailureMessage = '';
            $reviewNoteValue = trim((string) $reviewNote);
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'UPDATE sr_community_reports
                     SET status = :status,
                         reviewer_account_id = :reviewer_account_id,
                         review_note = :review_note,
                         reviewed_at = :reviewed_at,
                         updated_at = :updated_at
                     WHERE id = :id
                       AND status = :before_status'
                );
                foreach ($selectedIds as $selectedId) {
                    $report = $selectedReports[$selectedId];
                    $beforeStatus = (string) $report['status'];
                    $now = sr_now();
                    $stmt->execute([
                        'status' => $targetStatus,
                        'reviewer_account_id' => (int) $account['id'],
                        'review_note' => $reviewNoteValue,
                        'reviewed_at' => $now,
                        'updated_at' => $now,
                        'id' => $selectedId,
                        'before_status' => $beforeStatus,
                    ]);
                    if ($stmt->rowCount() < 1 && $beforeStatus !== $targetStatus) {
                        $batchFailureMessage = '선택한 신고 중 상태가 바뀐 항목이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
                        throw new RuntimeException($batchFailureMessage);
                    }
                    if ($beforeStatus === $targetStatus) {
                        $sameStatusCount++;
                    } else {
                        $statusChangedCount++;
                    }
                    $mappedTargetAction = sr_community_report_batch_target_action_for_report($normalizedTargetAction, (string) ($report['target_type'] ?? ''));
                    if ($mappedTargetAction !== '' && $mappedTargetAction !== 'none') {
                        $targetActionResult = sr_community_apply_report_target_action($pdo, $report, $mappedTargetAction, (int) $account['id']);
                        $targetActionResults[] = [
                            'report_id' => $selectedId,
                            'target_type' => (string) ($report['target_type'] ?? ''),
                            'target_id' => (int) ($report['target_id'] ?? 0),
                            'result' => $targetActionResult,
                        ];
                        if (!empty($targetActionResult['error'])) {
                            $batchFailureMessage = '신고 대상 조치를 적용하지 못했습니다.';
                            throw new RuntimeException($batchFailureMessage);
                        }
                        if (!empty($targetActionResult['applied'])) {
                            $targetActionAppliedCount++;
                        }
                    }
                    $processedCount++;
                }
                $pdo->commit();

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.report.bulk_status_updated',
                    'target_type' => 'community_report',
                    'target_id' => '',
                    'result' => 'success',
                    'message' => 'Community report statuses updated in bulk.',
                    'metadata' => [
                        'operation_key' => $operationKey,
                        'target_status' => $targetStatus,
                        'target_action' => $normalizedTargetAction,
                        'requested_count' => count($selectedIds),
                        'changed_count' => $statusChangedCount,
                        'processed_count' => $processedCount,
                        'same_status_count' => $sameStatusCount,
                        'target_action_applied_count' => $targetActionAppliedCount,
                        'target_action_results' => $targetActionResults,
                        'review_note_present' => $reviewNoteValue !== '',
                        'selected_ids' => $selectedIds,
                    ],
                ]);

                $notice = '신고 ' . number_format($processedCount) . '건을 처리했습니다.';
                if ($statusChangedCount > 0) {
                    $notice .= ' 상태 변경 ' . number_format($statusChangedCount) . '건.';
                }
                if ($sameStatusCount > 0) {
                    $notice .= ' 이미 같은 상태인 ' . number_format($sameStatusCount) . '건은 처리 메모만 반영했습니다.';
                }
                if ($targetActionAppliedCount > 0) {
                    $notice .= ' 대상 조치 ' . number_format($targetActionAppliedCount) . '건.';
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($batchFailureMessage !== '') {
                    $errors[] = $batchFailureMessage;
                } else {
                    sr_log_exception($exception, 'community_report_batch_status_failed');
                    $errors[] = '신고 상태 일괄 변경 중 오류가 발생했습니다.';
                }
            }
        }
    } else {
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

        $normalizedTargetAction = $targetAction === '' ? 'none' : $targetAction;
        if ($reviewNote === null) {
            $errors[] = sr_t('community::action.admin.review_note_too_long');
            $reviewNote = '';
        }
        if ($reviewNote !== null && trim((string) $reviewNote) === '') {
            $errors[] = '처리 메모를 입력하세요.';
        }
        if (is_array($report) && !array_key_exists($normalizedTargetAction, sr_community_report_target_action_options((string) $report['target_type']))) {
            $errors[] = '신고 대상 조치 값이 올바르지 않습니다.';
        }
        $targetActionPolicyError = sr_community_report_target_action_policy_error($status, $normalizedTargetAction);
        if ($targetActionPolicyError !== '') {
            $errors[] = $targetActionPolicyError;
        }

        if ($errors === []) {
            $targetActionResult = sr_community_apply_report_target_action($pdo, $report, $normalizedTargetAction, (int) $account['id']);
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
