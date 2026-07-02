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

    $challengeProviderKeys = sr_member_mfa_valid_provider_keys(explode(',', (string) (($challenge['context']['mfa_provider_keys'] ?? ''))));
    $activeProviderKeys = sr_member_active_login_mfa_provider_keys($pdo, $accountId);
    if ($challengeProviderKeys === []) {
        $challengeProviderKeys = $activeProviderKeys;
    }
    $availableProviderKeys = array_values(array_intersect($challengeProviderKeys, $activeProviderKeys));
    if ($availableProviderKeys === []) {
        sr_member_mfa_clear_challenge();
        $errors[] = sr_t('member::action.login_mfa.factor_unavailable');
        sr_member_log_auth($pdo, $accountId, 'mfa_challenge_expired', 'failure');
    }

    $code = sr_post_string_without_truncation('code', 80);
    $normalizedCode = is_string($code) ? sr_member_mfa_normalize_code($code) : '';
    $normalizedRecoveryCode = is_string($code) ? sr_member_mfa_recovery_code_normalize($code) : '';
    $mfaThrottle = sr_member_mfa_throttle_status($pdo, $accountId);
    if ($errors === [] && !empty($mfaThrottle['limited'])) {
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
    } elseif ($errors === [] && (
        $code === null
        || (
            !sr_member_mfa_code_is_valid_format($normalizedCode)
            && !sr_member_mfa_recovery_code_is_valid_format($normalizedRecoveryCode)
        )
    )) {
        $errors[] = sr_t('member::action.login_mfa.code_invalid');
        sr_member_log_auth($pdo, $accountId, 'mfa_totp_failure', 'failure');
    } elseif ($errors === []) {
        $mfaMethod = 'totp';
        $mfaResult = [
            'verified' => false,
            'reason' => 'invalid_code',
            'factor_id' => 0,
        ];
        if (in_array('totp', $availableProviderKeys, true) && sr_member_mfa_code_is_valid_format($normalizedCode)) {
            $mfaResult = sr_member_mfa_verify_totp_code($pdo, $accountId, $normalizedCode);
        }
        if (in_array('totp', $availableProviderKeys, true) && empty($mfaResult['verified']) && sr_member_mfa_recovery_code_is_valid_format($normalizedRecoveryCode)) {
            $mfaMethod = 'backup';
            $mfaResult = sr_member_mfa_consume_recovery_code($pdo, $accountId, $normalizedRecoveryCode);
        }
        if (!empty($mfaResult['verified'])) {
            $loginSucceeded = sr_member_login($pdo, $challengeAccount);
            if ($loginSucceeded) {
                sr_member_log_auth($pdo, $accountId, $mfaMethod === 'backup' ? 'mfa_backup_success' : 'mfa_totp_success', 'success');
                sr_audit_log($pdo, [
                    'actor_account_id' => $accountId,
                    'actor_type' => 'member',
                    'event_type' => 'member.mfa.login.completed',
                    'target_type' => 'member_account',
                    'target_id' => (string) $accountId,
                    'result' => 'success',
                    'message' => 'Member MFA challenge completed.',
                    'metadata' => [
                        'method' => $mfaMethod,
                        'factor_id' => (int) ($mfaResult['factor_id'] ?? 0),
                        'recovery_code_id' => (int) ($mfaResult['recovery_code_id'] ?? 0),
                        'remaining_recovery_codes' => $mfaResult['remaining_unused'] ?? null,
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
            sr_member_log_auth($pdo, $accountId, $mfaMethod === 'backup' ? 'mfa_backup_failure' : 'mfa_totp_failure', 'failure');
            sr_audit_log($pdo, [
                'actor_account_id' => $accountId,
                'actor_type' => 'member',
                'event_type' => 'member.mfa.login.failed',
                'target_type' => 'member_account',
                'target_id' => (string) $accountId,
                'result' => 'failure',
                'message' => 'Member MFA challenge failed.',
                'metadata' => [
                    'method' => $mfaMethod,
                    'reason' => (string) ($mfaResult['reason'] ?? ''),
                    'primary_method' => (string) ($challenge['primary_method'] ?? ''),
                ],
            ]);
        }
    }
}

include SR_ROOT . '/modules/member/views/login-mfa.php';
