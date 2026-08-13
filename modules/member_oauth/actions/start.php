<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/member_oauth/helpers.php';

$requestMethod = sr_request_method();
if ($requestMethod === 'POST') {
    sr_require_csrf();
}

$providerKey = sr_member_oauth_provider_key($requestMethod === 'POST'
    ? sr_post_string('provider', 60)
    : sr_get_string('provider', 60));
$providers = sr_member_oauth_providers($pdo);
if ($providerKey === '' || !isset($providers[$providerKey])) {
    sr_render_error(404, 'OAuth provider not found.');
}

$flowInput = $requestMethod === 'POST' ? sr_post_string('flow', 20) : sr_get_string('flow', 20);
$flowType = $flowInput === 'link' ? 'link' : 'login';
$accountId = null;
if ($flowType === 'link') {
    if ($requestMethod !== 'POST') {
        sr_render_error(405, 'OAuth 계정 연결은 계정 화면에서 다시 시작해 주세요.');
    }
    $account = sr_member_require_login($pdo);
    $accountId = (int) $account['id'];
}

$nextInput = $requestMethod === 'POST'
    ? sr_post_string_without_truncation('next', 1024)
    : sr_get_string_without_truncation('next', 1024);
$next = sr_member_safe_next_path($nextInput ?? '');
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
