<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
if (sr_module_enabled($pdo, 'identity_verification') && is_file(SR_ROOT . '/modules/identity_verification/helpers.php')) {
    require_once SR_ROOT . '/modules/identity_verification/helpers.php';
}

$account = sr_member_require_login($pdo);
$errors = [];
$memberSettings = sr_member_settings($pdo);
$withdrawIdentityPurpose = 'member.withdrawal';
$withdrawIdentityPolicy = function_exists('sr_identity_verification_requirement_policy')
    ? sr_identity_verification_requirement_policy($pdo, (int) $account['id'], $withdrawIdentityPurpose, !empty($memberSettings['identity_withdrawal_required']) ? 'required' : 'off', '/account/withdraw')
    : ['required' => !empty($memberSettings['identity_withdrawal_required']), 'satisfied' => false, 'available' => false, 'start_url' => ''];
$withdrawalAssets = sr_member_withdrawal_asset_balances($pdo, (int) $account['id']);
$refundAccount = [
    'bank' => '',
    'holder' => '',
    'number' => '',
];

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $password = sr_post_string('password', 255);
    $confirmText = sr_post_string('confirm_text', 20);
    $refundAccount = sr_member_withdrawal_refund_account_values();

    $reauthThrottle = sr_member_reauth_throttle_status($pdo, (int) $account['id']);
    if (!empty($reauthThrottle['limited'])) {
        $errors[] = sr_t('member::action.reauth.throttled');
        sr_member_log_auth($pdo, (int) $account['id'], 'reauth_blocked', 'failure');
    } elseif (!password_verify($password, (string) $account['password_hash'])) {
        $errors[] = sr_t('member::action.reauth.password_invalid');
        sr_member_log_auth($pdo, (int) $account['id'], 'withdraw_reauth', 'failure');
    }

    if ($confirmText !== sr_t('member::action.withdraw.confirm_text')) {
        $errors[] = sr_t('member::action.withdraw.confirm_required');
    }
    if (!empty($withdrawIdentityPolicy['required']) && empty($withdrawIdentityPolicy['satisfied'])) {
        $errors[] = !empty($withdrawIdentityPolicy['available'])
            ? '회원탈퇴 전 본인확인을 완료해 주세요.'
            : '본인확인 기능이 준비되지 않아 회원탈퇴를 진행할 수 없습니다.';
    }

    if (isset($withdrawalAssets['deposit'])) {
        $errors = array_merge($errors, sr_member_withdrawal_refund_account_errors($refundAccount));
    }

    if ($errors === []) {
        $withdrawnConsents = 0;
        $processedAssets = [];
        $pdo->beginTransaction();
        try {
            $processedAssets = sr_member_process_asset_withdrawal($pdo, (int) $account['id'], $refundAccount);
            sr_member_delete_profile($pdo, (int) $account['id']);
            $revokedSessions = sr_member_revoke_account_sessions($pdo, (int) $account['id']);
            if ($revokedSessions < 0) {
                throw new RuntimeException('Member sessions could not be revoked before account withdrawal.');
            }
            $deletedMfa = sr_member_delete_mfa($pdo, (int) $account['id']);
            $withdrawnConsents = sr_member_record_consent_withdrawals($pdo, (int) $account['id']);
            sr_member_anonymize_account($pdo, $config, (int) $account['id']);
            $privacyCleanupResults = sr_member_run_privacy_cleanup_contracts($pdo, (int) $account['id'], 'member.anonymized');
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        sr_member_log_auth($pdo, (int) $account['id'], 'withdraw', 'success');
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'member.anonymized',
            'target_type' => 'member_account',
            'target_id' => (string) $account['id'],
            'result' => 'success',
            'message' => 'Member account withdrawn and anonymized.',
            'metadata' => [
                'revoked_sessions' => $revokedSessions,
                'withdrawn_consents' => $withdrawnConsents,
                'deleted_mfa' => $deletedMfa,
                'processed_assets' => $processedAssets,
                'privacy_cleanup' => $privacyCleanupResults ?? [],
                'deposit_refund_account_provided' => isset($processedAssets['deposit']),
            ],
        ]);

        sr_member_logout($pdo);
        sr_redirect('/login');
    }
}

$memberSkinView = sr_member_skin_view(sr_member_skin_key($memberSettings), 'withdraw');
include $memberSkinView;
