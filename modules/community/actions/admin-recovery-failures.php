<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/recovery-failures', 'view');

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $intent = sr_post_string('intent', 40);
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/recovery-failures', 'edit');

    $failureIdValue = sr_post_string('failure_id', 20);
    $failureId = preg_match('/\A[1-9][0-9]*\z/', $failureIdValue) === 1 ? (int) $failureIdValue : 0;
    $failure = $failureId > 0 ? sr_community_asset_recovery_failure_by_id($pdo, $failureId) : null;
    if (!is_array($failure)) {
        $errors[] = '미회수 기록을 찾을 수 없습니다.';
    } elseif ((string) ($failure['status'] ?? '') !== 'open') {
        $errors[] = '이미 처리된 미회수 기록입니다.';
    }

    if ($errors === [] && is_array($failure) && $intent === 'retry') {
        $pdo->beginTransaction();
        try {
            $failure = sr_community_asset_recovery_failure_by_id_for_update($pdo, $failureId);
            if (!is_array($failure) || (string) ($failure['status'] ?? '') !== 'open') {
                throw new RuntimeException('이미 처리된 미회수 기록입니다.');
            }
            $result = sr_community_reverse_asset_grant_for_operation(
                $pdo,
                (int) $failure['account_id'],
                (string) $failure['grant_event_key'],
                (string) $failure['subject_type'],
                (int) $failure['subject_id'],
                (string) $failure['reversal_event_key'],
                'community.asset.recovery_retry',
                [
                    'operation_event_key' => 'manual_retry',
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'route_context' => 'admin.community.recovery_failures',
                ]
            );
            if (empty($result['operation_allowed'])) {
                throw new RuntimeException((string) ($result['message'] ?? '미회수 재회수 중 오류가 발생했습니다.'));
            }
            $pdo->commit();
            $notice = (string) ($result['recovery_status'] ?? '') === 'completed'
                ? '미회수 보상을 전액 재회수했습니다.'
                : '재회수 가능한 금액을 처리했고, 남은 미회수 금액을 기록했습니다.';
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.asset_recovery.retry',
                'target_type' => 'community_asset_recovery_failure',
                'target_id' => (string) $failureId,
                'result' => 'success',
                'message' => 'Community asset recovery failure retry processed.',
                'metadata' => [
                    'recovery_status' => (string) ($result['recovery_status'] ?? ''),
                    'subject_type' => (string) $failure['subject_type'],
                    'subject_id' => (int) $failure['subject_id'],
                ],
            ]);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            sr_log_exception($exception, 'community_asset_recovery_retry_failed');
            $errors[] = '미회수 재회수 중 오류가 발생했습니다.';
        }
    } elseif ($errors === [] && is_array($failure) && in_array($intent, ['manual_resolve', 'manual_cancel'], true)) {
        $confirmText = trim(sr_post_string('confirm_text', 80));
        $reason = trim(sr_post_string('admin_reason', 500));
        $expectedConfirmText = $intent === 'manual_resolve' ? '해소' : '취소';
        if ($confirmText !== $expectedConfirmText) {
            $errors[] = '확인 문구를 정확히 입력하세요.';
        }
        if ($reason === '') {
            $errors[] = '관리자 사유를 입력하세요.';
        }
        if ($errors === []) {
            try {
                sr_community_asset_recovery_failure_update_manual_status(
                    $pdo,
                    $failureId,
                    $intent === 'manual_resolve' ? 'resolved' : 'cancelled',
                    (int) $account['id'],
                    $reason
                );
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.asset_recovery.' . ($intent === 'manual_resolve' ? 'resolved' : 'cancelled'),
                    'target_type' => 'community_asset_recovery_failure',
                    'target_id' => (string) $failureId,
                    'result' => 'success',
                    'message' => 'Community asset recovery failure manually updated.',
                    'metadata' => [
                        'admin_reason' => mb_substr($reason, 0, 500),
                    ],
                ]);
                $notice = $intent === 'manual_resolve' ? '미회수 기록을 수동 해소 처리했습니다.' : '미회수 기록을 수동 취소 처리했습니다.';
            } catch (Throwable $exception) {
                sr_log_exception($exception, 'community_asset_recovery_manual_status_failed');
                $errors[] = '미회수 상태 변경 중 오류가 발생했습니다.';
            }
        }
    } elseif ($errors === []) {
        $errors[] = sr_t('community::action.error.intent_invalid');
    }

    $redirectQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/community/recovery-failures' . ($redirectQuery !== '' ? '?' . $redirectQuery : ''));
}

$recoveryFailureFilters = sr_community_asset_recovery_failure_filters_from_request();
$recoveryFailurePagination = sr_admin_pagination_from_total($pdo, sr_community_asset_recovery_failure_count($pdo, $recoveryFailureFilters));
$recoveryFailures = sr_community_asset_recovery_failures($pdo, $recoveryFailureFilters, (int) $recoveryFailurePagination['per_page'], sr_admin_pagination_offset($recoveryFailurePagination));
$assetModuleOptions = sr_community_asset_modules($pdo);

include SR_ROOT . '/modules/community/views/admin-recovery-failures.php';
