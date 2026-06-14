<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/member_oauth/helpers.php';

$providerKey = sr_member_oauth_provider_key(sr_get_string('provider', 60));
$stateToken = sr_get_string_without_truncation('state', 255) ?? '';
$state = sr_member_oauth_consume_state($pdo, $stateToken, $providerKey, 'login');
if (!is_array($state)) {
    sr_render_error(400, 'OAuth state is invalid.');
}

$providers = sr_member_oauth_providers($pdo);
if (!isset($providers[$providerKey]) || empty($providers[$providerKey]['mock'])) {
    sr_render_error(501, 'OAuth provider adapter is not implemented.');
}

$subject = 'mock-user';
$profile = [
    'subject_display' => 'mock-user',
    'email' => 'mock-user@example.test',
    'email_verified' => true,
    'display_name' => 'mock_user',
];
$subjectHash = sr_member_oauth_subject_hash($config, $providerKey, $subject);
$oauthAccount = sr_member_oauth_account_by_subject($pdo, $providerKey, $subjectHash);
if (is_array($oauthAccount)) {
    $account = sr_member_find_by_id($pdo, (int) $oauthAccount['account_id']);
    $memberSettings = sr_member_settings($pdo);
    if (!is_array($account) || (string) ($account['status'] ?? '') !== 'active' || sr_member_email_verification_blocks_login($memberSettings, $account)) {
        sr_member_log_auth($pdo, is_array($account) ? (int) $account['id'] : null, 'oauth_login_blocked', 'failure');
        sr_render_error(403, 'OAuth login is not allowed for this account.');
    }
    if (sr_member_login($pdo, $account)) {
        sr_member_group_evaluate_account($pdo, (int) $account['id']);
        sr_member_log_auth($pdo, (int) $account['id'], 'oauth_login', 'success');
        $stmt = $pdo->prepare('UPDATE sr_member_oauth_accounts SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'last_login_at' => sr_now(),
            'updated_at' => sr_now(),
            'id' => (int) $oauthAccount['id'],
        ]);
        sr_redirect((string) $state['next_path']);
    }
    sr_render_error(500, 'OAuth login session failed.');
}

$memberSettings = sr_member_settings($pdo);
if (empty($memberSettings['allow_registration'])) {
    sr_render_error(403, 'Registration is disabled.');
}

$settings = sr_member_oauth_settings($pdo);
$completionState = sr_member_oauth_create_completion_state($pdo, $providerKey, $subjectHash, $profile, (string) $state['next_path'], (int) $settings['completion_ttl_seconds']);
sr_redirect('/oauth/complete?state=' . rawurlencode($completionState));
