<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';

if (toy_request_method() !== 'POST') {
    toy_render_error(405, '허용되지 않는 요청입니다.');
}

toy_require_csrf();

$account = toy_member_current_account($pdo);
$loggedOut = toy_member_logout($pdo);
if ($account !== null) {
    toy_member_log_auth($pdo, (int) $account['id'], 'logout', $loggedOut ? 'success' : 'failure');
    toy_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'member',
        'event_type' => 'member.logout',
        'target_type' => 'member_account',
        'target_id' => (string) $account['id'],
        'result' => $loggedOut ? 'success' : 'failure',
        'message' => $loggedOut ? 'Member logged out.' : 'Member logout could not revoke current session.',
        'metadata' => [
            'current_session_revoked' => $loggedOut,
        ],
    ]);
}

toy_redirect('/login');
