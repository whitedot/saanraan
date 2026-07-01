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
    'report_id' => sr_admin_get_positive_int('report_id'),
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
        $reporterAction = sr_post_string('reporter_action', 40);
        $reviewNote = sr_post_string_without_truncation('review_note', 1000);
        $rawSelectedIds = $_POST['selected_report_ids'] ?? [];
        $selectedIds = sr_admin_positive_int_list_from_input($rawSelectedIds, $hasInvalidSelectedId);
        $normalizedTargetAction = $targetAction === '' ? 'none' : $targetAction;
        $normalizedReporterAction = $reporterAction === '' ? 'none' : $reporterAction;
        $batchTargetActionOptions = sr_community_report_batch_target_action_options();
        $reporterActionOptions = sr_community_report_reporter_action_options();

        if ($operationKey !== 'community.report_set_status') {
            $errors[] = '허용되지 않은 신고 일괄 작업입니다.';
        }
        if (!in_array($targetStatus, $allowedStatuses, true)) {
            $errors[] = sr_t('community::action.admin.report_status_invalid');
        }
        if (!array_key_exists($normalizedTargetAction, $batchTargetActionOptions)) {
            $errors[] = '신고 대상 조치 값이 올바르지 않습니다.';
        }
        if (!array_key_exists($normalizedReporterAction, $reporterActionOptions)) {
            $errors[] = '신고자 조치 값이 올바르지 않습니다.';
        }
        $targetActionPolicyError = sr_community_report_target_action_policy_error($targetStatus, $normalizedTargetAction);
        if ($targetActionPolicyError !== '') {
            $errors[] = $targetActionPolicyError;
        }
        $reporterActionPolicyError = sr_community_report_reporter_action_policy_error($targetStatus, $normalizedReporterAction);
        if ($reporterActionPolicyError !== '') {
            $errors[] = $reporterActionPolicyError;
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
                'SELECT id, status, target_type, target_id, reporter_account_id, reported_account_id
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
            $reporterActionAppliedCount = 0;
            $targetActionResults = [];
            $reporterActionResults = [];
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
                        $targetActionResult = sr_community_apply_report_target_action($pdo, $report, $mappedTargetAction, (int) $account['id'], true);
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
                    if ($normalizedReporterAction !== 'none') {
                        $reporterActionResult = sr_community_apply_report_reporter_action($pdo, $report, $normalizedReporterAction, (int) $account['id'], true);
                        $reporterActionResults[] = [
                            'report_id' => $selectedId,
                            'reporter_account_id' => (int) ($report['reporter_account_id'] ?? 0),
                            'result' => $reporterActionResult,
                        ];
                        if (!empty($reporterActionResult['error'])) {
                            $batchFailureMessage = '신고자 조치를 적용하지 못했습니다.';
                            throw new RuntimeException($batchFailureMessage);
                        }
                        if (!empty($reporterActionResult['applied'])) {
                            $reporterActionAppliedCount++;
                        }
                    }
                    $processedCount++;
                }
                sr_audit_log_required($pdo, [
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
                        'reporter_action' => $normalizedReporterAction,
                        'requested_count' => count($selectedIds),
                        'changed_count' => $statusChangedCount,
                        'processed_count' => $processedCount,
                        'same_status_count' => $sameStatusCount,
                        'target_action_applied_count' => $targetActionAppliedCount,
                        'reporter_action_applied_count' => $reporterActionAppliedCount,
                        'target_action_results' => $targetActionResults,
                        'reporter_action_results' => $reporterActionResults,
                        'review_note_present' => $reviewNoteValue !== '',
                        'selected_ids' => $selectedIds,
                    ],
                ]);
                $pdo->commit();

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
                if ($reporterActionAppliedCount > 0) {
                    $notice .= ' 신고자 조치 ' . number_format($reporterActionAppliedCount) . '건.';
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
        $reporterAction = sr_post_string('reporter_action', 40);
        $autoActionStatus = sr_post_string('auto_action_status', 30);
        $reviewNote = sr_post_string_without_truncation('review_note', 1000);
        $report = sr_community_report_by_id($pdo, $reportId);

        if (!is_array($report)) {
            $errors[] = sr_t('community::action.admin.report_not_found');
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = sr_t('community::action.admin.report_status_invalid');
        }

        $normalizedTargetAction = $targetAction === '' ? 'none' : $targetAction;
        $normalizedReporterAction = $reporterAction === '' ? 'none' : $reporterAction;
        $normalizedAutoActionStatus = $autoActionStatus === '' ? 'none' : $autoActionStatus;
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
        if (!array_key_exists($normalizedReporterAction, sr_community_report_reporter_action_options())) {
            $errors[] = '신고자 조치 값이 올바르지 않습니다.';
        }
        if (!array_key_exists($normalizedAutoActionStatus, sr_community_report_auto_action_review_options())) {
            $errors[] = '신고 자동조치 판단 값이 올바르지 않습니다.';
        }
        $targetActionPolicyError = sr_community_report_target_action_policy_error($status, $normalizedTargetAction);
        if ($targetActionPolicyError !== '') {
            $errors[] = $targetActionPolicyError;
        }
        $reporterActionPolicyError = sr_community_report_reporter_action_policy_error($status, $normalizedReporterAction);
        if ($reporterActionPolicyError !== '') {
            $errors[] = $reporterActionPolicyError;
        }
        if ($normalizedAutoActionStatus === 'released' && $normalizedTargetAction !== 'none') {
            $errors[] = '자동조치를 해제할 때는 별도 대상 조치를 함께 실행하지 마세요.';
        }
        if ($errors === [] && is_array($report) && $normalizedAutoActionStatus !== 'none') {
            $activeAutoAction = sr_community_report_active_auto_action($pdo, (string) $report['target_type'], (int) $report['target_id']);
            if (!is_array($activeAutoAction)) {
                $errors[] = '처리할 활성 신고 자동조치를 찾을 수 없습니다.';
            }
        }

        if ($errors === []) {
            try {
                $pdo->beginTransaction();
                $singleLockStmt = $pdo->prepare('SELECT status FROM sr_community_reports WHERE id = :id FOR UPDATE');
                $singleLockStmt->execute(['id' => $reportId]);
                $currentReport = $singleLockStmt->fetch();
                if (!is_array($currentReport) || (string) ($currentReport['status'] ?? '') !== (string) $report['status']) {
                    throw new RuntimeException('report_status_conflict');
                }

                $now = sr_now();
                $singleStatusStmt = $pdo->prepare(
                    'UPDATE sr_community_reports
                     SET status = :status,
                         reviewer_account_id = :reviewer_account_id,
                         review_note = :review_note,
                         reviewed_at = :reviewed_at,
                         updated_at = :updated_at
                     WHERE id = :id'
                );
                $singleStatusStmt->execute([
                    'status' => $status,
                    'reviewer_account_id' => (int) $account['id'],
                    'review_note' => trim((string) $reviewNote),
                    'reviewed_at' => $now,
                    'updated_at' => $now,
                    'id' => $reportId,
                ]);

                $targetActionResult = sr_community_apply_report_target_action($pdo, $report, $normalizedTargetAction, (int) $account['id'], true);
                if (!empty($targetActionResult['error'])) {
                    throw new RuntimeException('report_target_action_failed');
                }
                $reporterActionResult = sr_community_apply_report_reporter_action($pdo, $report, $normalizedReporterAction, (int) $account['id'], true);
                if (!empty($reporterActionResult['error'])) {
                    throw new RuntimeException('report_reporter_action_failed');
                }
                $autoActionReviewResult = ['action_key' => 'none', 'applied' => false];
                if ($normalizedAutoActionStatus !== 'none') {
                    $activeAutoAction = sr_community_report_active_auto_action($pdo, (string) $report['target_type'], (int) $report['target_id'], true);
                    if (!is_array($activeAutoAction)) {
                        throw new RuntimeException('report_auto_action_missing');
                    }

                    $releaseResult = [];
                    if ($normalizedAutoActionStatus === 'released') {
                        $releaseResult = sr_community_release_report_auto_action_target($pdo, $activeAutoAction);
                    }

                    $transitioned = sr_community_report_auto_action_transition($pdo, (int) $activeAutoAction['id'], $normalizedAutoActionStatus, [
                        'reviewer_account_id' => (int) $account['id'],
                        'metadata' => [
                            'source' => 'admin_report_review',
                            'report_id' => $reportId,
                            'report_status' => $status,
                            'review_note_present' => trim((string) $reviewNote) !== '',
                            'release_result' => $releaseResult,
                        ],
                    ]);
                    if (!$transitioned) {
                        throw new RuntimeException('report_auto_action_transition_failed');
                    }
                    sr_audit_log_required($pdo, [
                        'actor_account_id' => (int) $account['id'],
                        'actor_type' => 'admin',
                        'event_type' => 'community.report.auto_action_reviewed',
                        'target_type' => 'community_report_auto_action',
                        'target_id' => (string) (int) $activeAutoAction['id'],
                        'result' => 'success',
                        'message' => 'Community report auto action reviewed by administrator.',
                        'metadata' => [
                            'report_id' => $reportId,
                            'auto_action_status' => $normalizedAutoActionStatus,
                            'target_type' => (string) $report['target_type'],
                            'target_id' => (int) $report['target_id'],
                            'release_result' => $releaseResult,
                        ],
                    ]);
                    $autoActionReviewResult = [
                        'action_key' => $normalizedAutoActionStatus,
                        'applied' => true,
                        'auto_action_id' => (int) $activeAutoAction['id'],
                        'release_result' => $releaseResult,
                    ];
                }
                sr_audit_log_required($pdo, [
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
                        'reporter_account_id' => (int) $report['reporter_account_id'],
                        'target_action' => $targetActionResult,
                        'reporter_action' => $reporterActionResult,
                        'auto_action_review' => $autoActionReviewResult,
                    ],
                ]);
                $pdo->commit();
                if (!empty($autoActionReviewResult['applied']) && (int) ($autoActionReviewResult['auto_action_id'] ?? 0) > 0) {
                    try {
                        $accountGuardResult = sr_community_evaluate_account_guard_after_auto_action($pdo, (int) $autoActionReviewResult['auto_action_id'], $settings);
                        sr_audit_log($pdo, [
                            'actor_account_id' => null,
                            'actor_type' => 'system',
                            'event_type' => 'community.account_guard.evaluated',
                            'target_type' => 'community_report_auto_action',
                            'target_id' => (string) (int) $autoActionReviewResult['auto_action_id'],
                            'result' => (string) ($accountGuardResult['status'] ?? '') === 'evaluated' ? 'success' : 'skipped',
                            'message' => 'Community account guard evaluated after report auto action review.',
                            'metadata' => [
                                'report_id' => $reportId,
                                'auto_action_review' => $autoActionReviewResult,
                                'account_guard_result' => $accountGuardResult,
                            ],
                        ]);
                    } catch (Throwable $accountGuardException) {
                        sr_audit_log($pdo, [
                            'actor_account_id' => null,
                            'actor_type' => 'system',
                            'event_type' => 'community.account_guard.evaluation_failed',
                            'target_type' => 'community_report_auto_action',
                            'target_id' => (string) (int) $autoActionReviewResult['auto_action_id'],
                            'result' => 'failure',
                            'message' => 'Community account guard evaluation failed after report auto action review.',
                            'metadata' => [
                                'report_id' => $reportId,
                                'auto_action_review' => $autoActionReviewResult,
                                'error' => $accountGuardException->getMessage(),
                            ],
                        ]);
                    }
                }
                $notice = sr_t('community::action.admin.report_status_updated');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($exception instanceof RuntimeException && $exception->getMessage() === 'report_status_conflict') {
                    $errors[] = '신고 상태가 바뀌었거나 찾을 수 없습니다. 목록을 새로고침한 뒤 다시 처리하세요.';
                } elseif ($exception instanceof RuntimeException && $exception->getMessage() === 'report_target_action_failed') {
                    $errors[] = '신고 대상 조치를 적용하지 못했습니다.';
                } elseif ($exception instanceof RuntimeException && $exception->getMessage() === 'report_reporter_action_failed') {
                    $errors[] = '신고자 조치를 적용하지 못했습니다.';
                } elseif ($exception instanceof RuntimeException && $exception->getMessage() === 'report_auto_action_missing') {
                    $errors[] = '활성 신고 자동조치가 이미 처리되었습니다. 목록을 새로고침한 뒤 다시 확인하세요.';
                } elseif ($exception instanceof RuntimeException && $exception->getMessage() === 'report_auto_action_transition_failed') {
                    $errors[] = '신고 자동조치 판단을 저장하지 못했습니다.';
                } else {
                    sr_log_exception($exception, 'community_report_status_failed');
                    $errors[] = '신고 상태 저장 중 오류가 발생했습니다.';
                }
            }
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
$reportAutoActionsByTarget = sr_community_report_auto_actions_by_targets($pdo, $reports);
$canViewAuditLogs = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/audit-logs', 'view');

include SR_ROOT . '/modules/community/views/admin-reports.php';
