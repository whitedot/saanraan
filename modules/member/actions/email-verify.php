<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$token = sr_get_string_without_truncation('token', 64);
if ($token === null) {
    $token = '';
}
$verification = sr_member_find_email_verification($pdo, $config, $token);

if ($verification === null || $verification['status'] !== 'active') {
    sr_render_error(400, sr_t('member::action.email_verification.link_invalid'));
}

$pdo->beginTransaction();
try {
    if (!sr_member_mark_email_verified($pdo, (int) $verification['id'], (int) $verification['account_id'], (string) $verification['email'])) {
        $pdo->rollBack();
        sr_render_error(400, sr_t('member::action.email_verification.link_invalid'));
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}

sr_member_log_auth($pdo, (int) $verification['account_id'], 'email_verification', 'success');
sr_audit_log($pdo, [
    'actor_account_id' => (int) $verification['account_id'],
    'actor_type' => 'member',
    'event_type' => 'member.email.verified',
    'target_type' => 'member_account',
    'target_id' => (string) $verification['account_id'],
    'result' => 'success',
    'message' => 'Member email verified.',
]);
sr_member_create_security_notification($pdo, (int) $verification['account_id'], 'security.email_verified');

unset($_SESSION['sr_debug_email_verification_url']);

sr_redirect('/email/verified');
