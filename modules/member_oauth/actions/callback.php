<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/member_oauth/helpers.php';

$providerKey = sr_member_oauth_provider_key(sr_get_string('provider', 60));
$stateToken = sr_get_string_without_truncation('state', 255) ?? '';
$providers = sr_member_oauth_providers($pdo);
if ($providerKey === '' || !isset($providers[$providerKey])) {
    sr_render_error(404, 'OAuth provider not found.');
}

$providerError = sr_get_string_without_truncation('error', 255) ?? '';
if ($providerError !== '') {
    sr_render_error(400, 'OAuth provider returned an error.');
}

$statePreview = sr_member_oauth_state_by_token($pdo, $stateToken, 'login');
if (!is_array($statePreview)) {
    $statePreview = sr_member_oauth_state_by_token($pdo, $stateToken, 'link');
}
if (!is_array($statePreview) || (string) $statePreview['provider_key'] !== $providerKey) {
    sr_render_error(400, '외부 로그인 확인값이 올바르지 않습니다.');
}

$state = sr_member_oauth_consume_state($pdo, $stateToken, $providerKey, (string) $statePreview['flow_type']);
if (!is_array($state)) {
    sr_render_error(400, '외부 로그인 확인값이 올바르지 않습니다.');
}

if (!empty($providers[$providerKey]['mock'])) {
    $profile = sr_member_oauth_mock_profile();
} else {
    $code = sr_get_string_without_truncation('code', 4096) ?? '';
    $transientSecrets = sr_member_oauth_take_transient_secrets($stateToken);
    if ($code === '' || !is_array($transientSecrets)) {
        sr_render_error(400, 'OAuth callback is invalid.');
    }
    try {
        $profile = sr_member_oauth_provider_profile($providers[$providerKey], $site ?? [], $code, $transientSecrets);
    } catch (Throwable) {
        sr_render_error(502, 'OAuth provider profile could not be loaded.');
    }
}
$subjectHash = sr_member_oauth_subject_hash($config, $providerKey, (string) $profile['subject']);
$oauthAccount = sr_member_oauth_account_by_subject($pdo, $providerKey, $subjectHash);
$anyOauthAccount = is_array($oauthAccount) ? $oauthAccount : sr_member_oauth_account_by_subject_any($pdo, $providerKey, $subjectHash);
if ((string) $state['flow_type'] === 'link') {
    $account = sr_member_require_login($pdo);
    if ((int) $state['account_id'] !== (int) $account['id']) {
        sr_render_error(403, 'OAuth link state is not valid for this account.');
    }
    $existingProviderAccount = sr_member_oauth_account_for_provider($pdo, (int) $account['id'], $providerKey);
    if (is_array($anyOauthAccount) && (int) $anyOauthAccount['account_id'] !== (int) $account['id']) {
        sr_member_log_auth($pdo, (int) $account['id'], 'oauth_link_conflict', 'failure');
        sr_render_error(409, 'OAuth provider account is already linked.');
    }
    if (is_array($existingProviderAccount) && (!is_array($oauthAccount) || (int) $existingProviderAccount['id'] !== (int) $oauthAccount['id'])) {
        sr_member_log_auth($pdo, (int) $account['id'], 'oauth_link_provider_exists', 'failure');
        sr_render_error(409, 'OAuth provider is already linked to this account.');
    }
    $linkedOauthAccountId = sr_member_oauth_link_account($pdo, (int) $account['id'], $providerKey, $subjectHash, $profile);
    $memberSettings = sr_member_settings($pdo);
    $syncedFields = sr_member_oauth_sync_member_profile($pdo, $config, (int) $account['id'], $account, $providers[$providerKey], $profile, $memberSettings);
    if (!is_array($existingProviderAccount)) {
        sr_member_log_auth($pdo, (int) $account['id'], 'oauth_link', 'success');
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'member.oauth.linked',
            'target_type' => 'member_account',
            'target_id' => (string) $account['id'],
            'result' => 'success',
            'message' => 'OAuth provider linked to member account.',
            'metadata' => [
                'provider_key' => $providerKey,
                'oauth_account_id' => $linkedOauthAccountId,
                'synced_fields' => $syncedFields,
            ],
        ]);
        sr_member_create_security_notification($pdo, (int) $account['id'], 'security.oauth_linked', [
            'provider_key' => $providerKey,
            'provider_label' => (string) ($providers[$providerKey]['label'] ?? $providerKey),
        ]);
    } elseif ($syncedFields !== []) {
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'member.oauth.profile_synced',
            'target_type' => 'member_account',
            'target_id' => (string) $account['id'],
            'result' => 'success',
            'message' => 'OAuth provider profile synced to member account.',
            'metadata' => [
                'provider_key' => $providerKey,
                'oauth_account_id' => $linkedOauthAccountId,
                'synced_fields' => $syncedFields,
            ],
        ]);
    }
    sr_redirect('/account');
}

if (is_array($oauthAccount)) {
    $account = sr_member_find_by_id($pdo, (int) $oauthAccount['account_id']);
    $memberSettings = sr_member_settings($pdo);
    if (!is_array($account) || (string) ($account['status'] ?? '') !== 'active' || sr_member_email_verification_blocks_login($pdo, $memberSettings, $account)) {
        sr_member_log_auth($pdo, is_array($account) ? (int) $account['id'] : null, 'oauth_login_blocked', 'failure');
        sr_audit_log($pdo, [
            'actor_account_id' => is_array($account) ? (int) $account['id'] : null,
            'actor_type' => 'member',
            'event_type' => 'member.oauth.login.blocked',
            'target_type' => 'member_account',
            'target_id' => is_array($account) ? (string) $account['id'] : '',
            'result' => 'failure',
            'message' => 'OAuth login blocked by member account policy.',
            'metadata' => [
                'provider_key' => $providerKey,
            ],
        ]);
        sr_render_error(403, 'OAuth login is not allowed for this account.');
    }
    $loginResult = sr_member_login_or_start_mfa($pdo, $account, 'oauth', (string) $state['next_path'], [
        'provider_key' => $providerKey,
        'oauth_account_id' => (int) $oauthAccount['id'],
    ]);
    if ($loginResult === 'mfa_required') {
        sr_redirect('/login/mfa');
    }
    if ($loginResult === 'logged_in') {
        sr_member_group_evaluate_account($pdo, (int) $account['id']);
        sr_member_oauth_update_link_snapshot($pdo, (int) $oauthAccount['id'], $profile);
        $syncedFields = sr_member_oauth_sync_member_profile($pdo, $config, (int) $account['id'], $account, $providers[$providerKey], $profile, $memberSettings);
        sr_member_log_auth($pdo, (int) $account['id'], 'oauth_login', 'success');
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'member.oauth.login',
            'target_type' => 'member_account',
            'target_id' => (string) $account['id'],
            'result' => 'success',
            'message' => 'OAuth login succeeded.',
            'metadata' => [
                'provider_key' => $providerKey,
                'synced_fields' => $syncedFields,
            ],
        ]);
        $stmt = $pdo->prepare('UPDATE sr_member_oauth_accounts SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'last_login_at' => sr_now(),
            'updated_at' => sr_now(),
            'id' => (int) $oauthAccount['id'],
        ]);
        if (sr_member_mfa_login_setup_required($pdo, $account)) {
            sr_member_redirect_mfa_setup_required();
        }
        sr_redirect((string) $state['next_path']);
    }
    sr_member_log_auth($pdo, (int) $account['id'], 'oauth_login_session_failed', 'failure');
    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'member',
        'event_type' => 'member.oauth.login.session_failed',
        'target_type' => 'member_account',
        'target_id' => (string) $account['id'],
        'result' => 'failure',
        'message' => 'OAuth login matched an account but session creation failed.',
        'metadata' => [
            'provider_key' => $providerKey,
        ],
    ]);
    sr_render_error(500, 'OAuth login session failed.');
}

if (is_array($anyOauthAccount)) {
    sr_render_error(409, 'OAuth provider account was previously linked.');
}

$memberSettings = sr_member_settings($pdo);
if (empty($memberSettings['allow_registration'])) {
    sr_render_error(403, 'Registration is disabled.');
}

$settings = sr_member_oauth_settings($pdo);
$completionState = sr_member_oauth_create_completion_state($pdo, $providerKey, $subjectHash, $profile, (string) $state['next_path'], (int) $settings['completion_ttl_seconds']);
sr_redirect('/oauth/complete?state=' . rawurlencode($completionState));
