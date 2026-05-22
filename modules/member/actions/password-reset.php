<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$errors = [];
$notice = '';
$method = sr_request_method();
$resetTokenSessionSeconds = 900;
$token = '';
$tokenInputInvalid = false;
$memberSettings = sr_member_settings($pdo);
if ($method === 'GET') {
    $tokenInput = sr_get_string_without_truncation('token', 64);
    if ($tokenInput === null) {
        $tokenInputInvalid = true;
    } else {
        $token = $tokenInput;
    }
}
$tokenHash = $method === 'GET' && $token !== ''
    ? sr_member_password_reset_token_hash($config, $token)
    : ($tokenInputInvalid ? '' : sr_member_password_reset_session_hash($resetTokenSessionSeconds));

if ($method === 'GET' && ($tokenInputInvalid || $token !== '')) {
    $reset = $tokenHash !== '' ? sr_member_find_password_reset_by_hash($pdo, $tokenHash) : null;
    if ($reset === null) {
        sr_member_clear_password_reset_session_hash();
        sr_render_error(400, sr_t('member::action.password_reset.link_invalid'));
    }

    sr_member_store_password_reset_session_hash($tokenHash);
    sr_redirect('/password/reset/confirm');
}

$reset = $tokenHash !== '' ? sr_member_find_password_reset_by_hash($pdo, $tokenHash) : null;

if ($reset === null) {
    sr_member_clear_password_reset_session_hash();
    sr_render_error(400, sr_t('member::action.password_reset.link_invalid'));
}

if ($method === 'POST') {
    sr_require_csrf();

    $reset = $tokenHash !== '' ? sr_member_find_password_reset_by_hash($pdo, $tokenHash) : null;
    if ($reset === null) {
        sr_member_clear_password_reset_session_hash();
        sr_render_error(400, sr_t('member::action.password_reset.link_invalid'));
    }

    $password = sr_post_string_without_truncation('password', 255);
    $passwordConfirm = sr_post_string_without_truncation('password_confirm', 255);

    if ($password === null || $passwordConfirm === null) {
        $errors[] = sr_t('member::action.password.new_too_long');
        $password = '';
        $passwordConfirm = '';
    }

    if (strlen($password) < 8) {
        $errors[] = sr_t('member::action.password.new_too_short');
    }

    if ($password !== $passwordConfirm) {
        $errors[] = sr_t('member::action.password.new_confirm_mismatch');
    }

    if ($reset['status'] !== 'active') {
        $errors[] = sr_t('member::action.password_reset.active_only');
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();

            if (!sr_member_mark_password_reset_used($pdo, (int) $reset['id'])) {
                $pdo->rollBack();
                sr_render_error(400, sr_t('member::action.password_reset.link_invalid'));
            }

            sr_member_update_password($pdo, (int) $reset['account_id'], $password);
            $revokedSessions = sr_member_revoke_account_sessions($pdo, (int) $reset['account_id']);
            if ($revokedSessions < 0) {
                throw new RuntimeException('Member sessions could not be revoked after password reset.');
            }
            $shouldLogoutCurrentSession = sr_member_current_session_account_id() === (int) $reset['account_id'];
            $pdo->commit();

            $loggedOutCurrentSession = false;
            sr_member_clear_password_reset_session_hash();
            if ($shouldLogoutCurrentSession) {
                $loggedOutCurrentSession = sr_member_logout_current_session_if_account($pdo, (int) $reset['account_id']);
            }

            sr_member_log_auth($pdo, (int) $reset['account_id'], 'password_reset', 'success');
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $reset['account_id'],
                'actor_type' => 'member',
                'event_type' => 'member.password_reset.completed',
                'target_type' => 'member_account',
                'target_id' => (string) $reset['account_id'],
                'result' => 'success',
                'message' => 'Member password reset completed.',
                'metadata' => [
                    'revoked_sessions' => $revokedSessions,
                    'current_session_logout_required' => $shouldLogoutCurrentSession,
                    'logged_out_current_session' => $loggedOutCurrentSession,
                ],
            ]);

            sr_redirect('/login?password_reset=1');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}

$memberSkinView = sr_member_skin_view(sr_member_skin_key($memberSettings), 'password-reset');
include $memberSkinView;
