<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/member_oauth/helpers.php';

$providerKey = sr_member_oauth_provider_key(sr_get_string('provider', 60));
$providers = sr_member_oauth_providers($pdo);
if ($providerKey === '' || !isset($providers[$providerKey])) {
    sr_render_error(404, 'OAuth provider not found.');
}

$flowType = sr_get_string('flow', 20) === 'link' ? 'link' : 'login';
$accountId = null;
if ($flowType === 'link') {
    $account = sr_member_require_login($pdo);
    $accountId = (int) $account['id'];
}

$next = sr_member_safe_next_path(sr_get_string_without_truncation('next', 1024) ?? '');
$settings = sr_member_oauth_settings($pdo);
$state = sr_member_oauth_create_state($pdo, $providerKey, $flowType, $accountId, $next, (int) $settings['state_ttl_seconds']);

if (!empty($providers[$providerKey]['mock'])) {
    sr_redirect('/oauth/callback?provider=' . rawurlencode($providerKey) . '&state=' . rawurlencode((string) $state['state']) . '&code=mock');
}

try {
    sr_member_oauth_store_transient_secrets((string) $state['state'], $state, (int) $settings['state_ttl_seconds']);
    sr_redirect_trusted_external(
        sr_member_oauth_authorization_url($providers[$providerKey], $site ?? [], $state),
        [sr_member_oauth_provider_value($providers[$providerKey], 'authorization_url')]
    );
} catch (Throwable) {
    sr_render_error(500, 'OAuth provider settings are invalid.');
}
