<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$account = sr_member_current_account($pdo);
if ($account !== null) {
    sr_redirect('/');
}

$challenge = sr_member_mfa_challenge();
if ($challenge === null) {
    sr_redirect('/login');
}

$errors = [];
$notice = '';
$memberSettings = sr_member_settings($pdo);
$next = sr_member_safe_next_path((string) ($challenge['next_path'] ?? ''));

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $accountId = (int) ($challenge['account_id'] ?? 0);
    $challengeAccount = sr_member_find_by_id($pdo, $accountId);
    if (!is_array($challengeAccount) || (string) ($challengeAccount['status'] ?? '') !== 'active') {
        sr_member_mfa_clear_challenge();
        sr_redirect('/login');
    }

    $memberSettings = sr_member_settings($pdo);
    if (sr_member_email_verification_blocks_login($memberSettings, $challengeAccount)) {
        sr_member_mfa_clear_challenge();
        sr_redirect('/login');
    }

    $code = sr_post_string_without_truncation('code', 40);
    $normalizedCode = is_string($code) ? sr_member_mfa_normalize_code($code) : '';
    $mfaThrottle = sr_member_mfa_throttle_status($pdo, $accountId);
    if (!empty($mfaThrottle['limited'])) {
        $errors[] = sr_t('member::action.login_mfa.throttled');
        sr_member_log_auth($pdo, $accountId, 'mfa_rate_limited', 'failure');
        sr_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'member',
            'event_type' => 'member.mfa.rate_limited',
            'target_type' => 'member_account',
            'target_id' => (string) $accountId,
            'result' => 'failure',
            'message' => 'Member MFA challenge blocked by throttle.',
            'metadata' => [
                'reason' => (string) ($mfaThrottle['reason'] ?? ''),
                'primary_method' => (string) ($challenge['primary_method'] ?? ''),
            ],
        ]);
    } elseif ($code === null || !sr_member_mfa_code_is_valid_format($normalizedCode)) {
        $errors[] = sr_t('member::action.login_mfa.code_invalid');
        sr_member_log_auth($pdo, $accountId, 'mfa_totp_failure', 'failure');
    } else {
        $mfaResult = sr_member_mfa_verify_totp_code($pdo, $accountId, $normalizedCode);
        if (!empty($mfaResult['verified'])) {
            $loginSucceeded = sr_member_login($pdo, $challengeAccount);
            if ($loginSucceeded) {
                sr_member_log_auth($pdo, $accountId, 'mfa_totp_success', 'success');
                sr_audit_log($pdo, [
                    'actor_account_id' => $accountId,
                    'actor_type' => 'member',
                    'event_type' => 'member.mfa.login.completed',
                    'target_type' => 'member_account',
                    'target_id' => (string) $accountId,
                    'result' => 'success',
                    'message' => 'Member MFA challenge completed.',
                    'metadata' => [
                        'factor_id' => (int) ($mfaResult['factor_id'] ?? 0),
                        'primary_method' => (string) ($challenge['primary_method'] ?? ''),
                        'next_path' => $next,
                    ],
                ]);
                sr_redirect(sr_member_safe_next_path($next));
            }

            sr_member_log_auth($pdo, $accountId, 'login_session_failed', 'failure');
            $errors[] = sr_t('member::action.login.session_failed');
        } else {
            $errors[] = (string) ($mfaResult['reason'] ?? '') === 'secret_unavailable'
                ? sr_t('member::action.login_mfa.factor_unavailable')
                : sr_t('member::action.login_mfa.code_invalid');
            sr_member_log_auth($pdo, $accountId, 'mfa_totp_failure', 'failure');
            sr_audit_log($pdo, [
                'actor_account_id' => $accountId,
                'actor_type' => 'member',
                'event_type' => 'member.mfa.login.failed',
                'target_type' => 'member_account',
                'target_id' => (string) $accountId,
                'result' => 'failure',
                'message' => 'Member MFA challenge failed.',
                'metadata' => [
                    'reason' => (string) ($mfaResult['reason'] ?? ''),
                    'primary_method' => (string) ($challenge['primary_method'] ?? ''),
                ],
            ]);
        }
    }
}

include SR_ROOT . '/modules/member/views/login-mfa.php';
