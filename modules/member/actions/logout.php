<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

if (sr_request_method() !== 'POST') {
    sr_render_error(405, sr_t('member::action.request.method_not_allowed'));
}

sr_require_csrf();

$account = sr_member_current_account($pdo);
$loggedOut = sr_member_logout($pdo);
if ($account !== null) {
    sr_member_log_auth($pdo, (int) $account['id'], 'logout', $loggedOut ? 'success' : 'failure');
    sr_audit_log($pdo, [
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

sr_redirect('/login');
