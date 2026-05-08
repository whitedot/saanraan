<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';

$errors = [];
$notice = '';
$method = toy_request_method();
$resetTokenSessionSeconds = 900;
$token = '';
$tokenInputInvalid = false;
if ($method === 'GET') {
    $tokenInput = toy_get_string_without_truncation('token', 64);
    if ($tokenInput === null) {
        $tokenInputInvalid = true;
    } else {
        $token = $tokenInput;
    }
}
$tokenHash = $method === 'GET' && $token !== ''
    ? toy_member_password_reset_token_hash($config, $token)
    : ($tokenInputInvalid ? '' : toy_member_password_reset_session_hash($resetTokenSessionSeconds));

if ($method === 'GET' && ($tokenInputInvalid || $token !== '')) {
    $reset = $tokenHash !== '' ? toy_member_find_password_reset_by_hash($pdo, $tokenHash) : null;
    if ($reset === null) {
        toy_member_clear_password_reset_session_hash();
        toy_render_error(400, '비밀번호 재설정 링크가 올바르지 않거나 만료되었습니다.');
    }

    toy_member_store_password_reset_session_hash($tokenHash);
    toy_redirect('/password/reset/confirm');
}

$reset = $tokenHash !== '' ? toy_member_find_password_reset_by_hash($pdo, $tokenHash) : null;

if ($reset === null) {
    toy_member_clear_password_reset_session_hash();
    toy_render_error(400, '비밀번호 재설정 링크가 올바르지 않거나 만료되었습니다.');
}

if ($method === 'POST') {
    toy_require_csrf();

    $reset = $tokenHash !== '' ? toy_member_find_password_reset_by_hash($pdo, $tokenHash) : null;
    if ($reset === null) {
        toy_member_clear_password_reset_session_hash();
        toy_render_error(400, '비밀번호 재설정 링크가 올바르지 않거나 만료되었습니다.');
    }

    $password = toy_post_string_without_truncation('password', 255);
    $passwordConfirm = toy_post_string_without_truncation('password_confirm', 255);

    if ($password === null || $passwordConfirm === null) {
        $errors[] = '새 비밀번호는 255자 이하로 입력하세요.';
        $password = '';
        $passwordConfirm = '';
    }

    if (strlen($password) < 8) {
        $errors[] = '새 비밀번호는 8자 이상이어야 합니다.';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = '새 비밀번호 확인이 일치하지 않습니다.';
    }

    if ($reset['status'] !== 'active') {
        $errors[] = '활성 계정만 비밀번호를 재설정할 수 있습니다.';
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();

            if (!toy_member_mark_password_reset_used($pdo, (int) $reset['id'])) {
                $pdo->rollBack();
                toy_render_error(400, '비밀번호 재설정 링크가 올바르지 않거나 만료되었습니다.');
            }

            toy_member_update_password($pdo, (int) $reset['account_id'], $password);
            $revokedSessions = toy_member_revoke_account_sessions($pdo, (int) $reset['account_id']);
            if ($revokedSessions < 0) {
                throw new RuntimeException('Member sessions could not be revoked after password reset.');
            }
            $shouldLogoutCurrentSession = toy_member_current_session_account_id() === (int) $reset['account_id'];
            $pdo->commit();

            $loggedOutCurrentSession = false;
            toy_member_clear_password_reset_session_hash();
            if ($shouldLogoutCurrentSession) {
                $loggedOutCurrentSession = toy_member_logout_current_session_if_account($pdo, (int) $reset['account_id']);
            }

            toy_member_log_auth($pdo, (int) $reset['account_id'], 'password_reset', 'success');
            toy_audit_log($pdo, [
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

            toy_redirect('/login?password_reset=1');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}

include TOY_ROOT . '/modules/member/views/password-reset.php';
