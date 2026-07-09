<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/member_oauth/helpers.php';

sr_require_csrf();

$account = sr_member_require_login($pdo);
$oauthAccountId = (int) sr_post_string('oauth_account_id', 20);
$activeOauthAccounts = sr_member_oauth_accounts_for_account($pdo, (int) $account['id']);
$oauthProviderLabel = '';
foreach ($activeOauthAccounts as $activeOauthAccount) {
    if ((int) ($activeOauthAccount['id'] ?? 0) === $oauthAccountId) {
        $oauthProviderKey = (string) ($activeOauthAccount['provider_key'] ?? '');
        $oauthProviders = sr_member_oauth_providers($pdo);
        $oauthProviderLabel = (string) ($oauthProviders[$oauthProviderKey]['label'] ?? $oauthProviderKey);
        break;
    }
}
if ($oauthAccountId < 1) {
    sr_render_error(400, 'OAuth account is invalid.');
}
if (!sr_member_oauth_can_unlink($account, $activeOauthAccounts)) {
    sr_member_log_auth($pdo, (int) $account['id'], 'oauth_unlink_last_method_blocked', 'failure');
    sr_render_error(409, 'Last login method cannot be unlinked.');
}

if (!sr_member_oauth_revoke_account($pdo, $oauthAccountId, (int) $account['id'])) {
    sr_render_error(404, 'OAuth account link not found.');
}

sr_member_log_auth($pdo, (int) $account['id'], 'oauth_unlink', 'success');
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'member.oauth.unlinked',
    'target_type' => 'member_account',
    'target_id' => (string) $account['id'],
    'result' => 'success',
    'message' => 'OAuth provider unlinked from member account.',
    'metadata' => [
        'oauth_account_id' => $oauthAccountId,
    ],
]);
sr_member_create_security_notification($pdo, (int) $account['id'], 'security.oauth_unlinked', [
    'provider_label' => $oauthProviderLabel !== '' ? $oauthProviderLabel : '외부 로그인',
]);

sr_redirect('/account');
