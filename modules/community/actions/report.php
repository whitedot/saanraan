<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$targetType = sr_post_string('target_type', 30);
$targetIdValue = sr_post_string('target_id', 20);
$targetId = preg_match('/\A[1-9][0-9]*\z/', $targetIdValue) === 1 ? (int) $targetIdValue : 0;
$commentPageValue = sr_post_string('comment_page', 20);
$commentPageNumber = preg_match('/\A[1-9][0-9]*\z/', $commentPageValue) === 1 ? (int) $commentPageValue : 1;
$reasonKey = sr_post_string('reason_key', 40);
$memoText = sr_post_string_without_truncation('memo_text', 1000);
$target = sr_community_report_target($pdo, $targetType, $targetId, (int) $account['id']);
if (!is_array($target)) {
    sr_render_error(404, sr_t('community::action.error.report_target_not_found'));
}

$redirectPath = (string) $target['redirect_path'];
if ($targetType === 'comment' && $commentPageNumber > 1) {
    $redirectPath = str_replace('#comments', '&comment_page=' . rawurlencode((string) $commentPageNumber) . '#comments', $redirectPath);
}
$errors = [];
if (!in_array($reasonKey, sr_community_report_reason_keys(), true)) {
    $errors[] = sr_t('community::action.error.report_reason_required');
}

if ($memoText === null) {
    $errors[] = sr_t('community::action.error.report_memo_too_long');
    $memoText = '';
}

if ((int) $target['reported_account_id'] === (int) $account['id']) {
    $errors[] = sr_t('community::action.error.report_self_forbidden');
}

$settings = sr_community_settings($pdo);
if ($errors === [] && sr_community_report_rate_limited($pdo, (int) $account['id'], $settings)) {
    $errors[] = sr_t('community::action.rate_limit.report');
}

if ($errors === [] && sr_community_report_exists($pdo, (int) $account['id'], (string) $target['target_type'], (int) $target['target_id'])) {
    $errors[] = sr_t('community::action.error.report_duplicate');
}

if ($errors !== []) {
    $_SESSION['sr_community_report_errors'] = $errors;
    sr_redirect($redirectPath);
}

$reportId = sr_community_create_report($pdo, [
    'target_type' => (string) $target['target_type'],
    'target_id' => (int) $target['target_id'],
    'reporter_account_id' => (int) $account['id'],
    'reported_account_id' => (int) $target['reported_account_id'],
    'reason_key' => $reasonKey,
    'memo_text' => (string) $memoText,
]);
sr_community_record_report_rate_limit($pdo, (int) $account['id'], $settings);
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'community.report.created',
    'target_type' => 'community_report',
    'target_id' => (string) $reportId,
    'result' => 'success',
    'message' => 'Community report created.',
    'metadata' => [
        'reported_target_type' => (string) $target['target_type'],
        'reported_target_id' => (int) $target['target_id'],
        'reported_account_id' => (int) $target['reported_account_id'],
        'reason_key' => $reasonKey,
    ],
]);
sr_community_create_admin_report_notifications(
    $pdo,
    $reportId,
    (string) $target['target_type'],
    (int) $target['target_id'],
    $reasonKey,
    (int) $account['id']
);
try {
    $autoActionResult = sr_community_maybe_apply_report_auto_action($pdo, $reportId, $settings);
    if (in_array((string) ($autoActionResult['status'] ?? ''), ['applied', 'skipped', 'failed', 'active_exists'], true)) {
        sr_audit_log($pdo, [
            'actor_account_id' => null,
            'actor_type' => 'system',
            'event_type' => 'community.report.auto_action_evaluated',
            'target_type' => 'community_report_auto_action',
            'target_id' => (string) (int) ($autoActionResult['auto_action_id'] ?? 0),
            'result' => (string) ($autoActionResult['status'] ?? '') === 'applied' ? 'success' : 'skipped',
            'message' => 'Community report auto action evaluated.',
            'metadata' => [
                'source_report_id' => $reportId,
                'reported_target_type' => (string) $target['target_type'],
                'reported_target_id' => (int) $target['target_id'],
                'status' => (string) ($autoActionResult['status'] ?? ''),
                'reason' => (string) ($autoActionResult['reason'] ?? ''),
            ],
        ]);
    }
    if ((string) ($autoActionResult['status'] ?? '') === 'applied' && (int) ($autoActionResult['auto_action_id'] ?? 0) > 0) {
        try {
            $accountGuardResult = sr_community_evaluate_account_guard_after_auto_action($pdo, (int) $autoActionResult['auto_action_id'], $settings);
            sr_audit_log($pdo, [
                'actor_account_id' => null,
                'actor_type' => 'system',
                'event_type' => 'community.account_guard.evaluated',
                'target_type' => 'community_report_auto_action',
                'target_id' => (string) (int) $autoActionResult['auto_action_id'],
                'result' => (string) ($accountGuardResult['status'] ?? '') === 'evaluated' ? 'success' : 'skipped',
                'message' => 'Community account guard evaluated after report auto action.',
                'metadata' => [
                    'source_report_id' => $reportId,
                    'auto_action_id' => (int) $autoActionResult['auto_action_id'],
                    'account_guard_result' => $accountGuardResult,
                ],
            ]);
        } catch (Throwable $accountGuardException) {
            sr_audit_log($pdo, [
                'actor_account_id' => null,
                'actor_type' => 'system',
                'event_type' => 'community.account_guard.evaluation_failed',
                'target_type' => 'community_report_auto_action',
                'target_id' => (string) (int) $autoActionResult['auto_action_id'],
                'result' => 'failure',
                'message' => 'Community account guard evaluation failed after report auto action.',
                'metadata' => [
                    'source_report_id' => $reportId,
                    'auto_action_id' => (int) $autoActionResult['auto_action_id'],
                    'error' => $accountGuardException->getMessage(),
                ],
            ]);
        }
    }
} catch (Throwable $exception) {
    sr_audit_log($pdo, [
        'actor_account_id' => null,
        'actor_type' => 'system',
        'event_type' => 'community.report.auto_action_failed',
        'target_type' => 'community_report',
        'target_id' => (string) $reportId,
        'result' => 'failure',
        'message' => 'Community report auto action failed after report creation.',
        'metadata' => [
            'reported_target_type' => (string) $target['target_type'],
            'reported_target_id' => (int) $target['target_id'],
            'error' => $exception->getMessage(),
        ],
    ]);
}
$_SESSION['sr_community_report_notice'] = sr_t('community::action.notice.report_created');

sr_redirect($redirectPath);
