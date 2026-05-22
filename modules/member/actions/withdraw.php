<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$account = sr_member_require_login($pdo);
$errors = [];
$memberSettings = sr_member_settings($pdo);
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
        $errors[] = '비밀번호 확인 시도가 많습니다. 잠시 후 다시 시도하세요.';
        sr_member_log_auth($pdo, (int) $account['id'], 'reauth_blocked', 'failure');
    } elseif (!password_verify($password, (string) $account['password_hash'])) {
        $errors[] = '비밀번호가 올바르지 않습니다.';
        sr_member_log_auth($pdo, (int) $account['id'], 'withdraw_reauth', 'failure');
    }

    if ($confirmText !== '탈퇴') {
        $errors[] = '확인 문구를 입력하세요.';
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
            $withdrawnConsents = sr_member_record_consent_withdrawals($pdo, (int) $account['id']);
            sr_member_anonymize_account($pdo, $config, (int) $account['id']);
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
                'processed_assets' => $processedAssets,
                'deposit_refund_account_provided' => isset($processedAssets['deposit']),
            ],
        ]);

        sr_member_logout($pdo);
        sr_redirect('/login');
    }
}

$memberSkinView = sr_member_skin_view(sr_member_skin_key($memberSettings), 'withdraw');
include $memberSkinView;
