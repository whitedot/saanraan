<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/identity_verification/helpers.php';

$account = sr_member_require_login($pdo);
$settings = sr_identity_verification_settings($pdo);
if (empty($settings['enabled'])) {
    sr_render_error(503, '본인확인 기능이 비활성화되어 있습니다.');
}

$baseUrl = (string) ($site['base_url'] ?? '');
if (!empty($settings['require_https']) && !str_starts_with($baseUrl, 'https://') && !sr_is_local_host($baseUrl)) {
    sr_render_error(503, '본인확인은 HTTPS 기준 URL에서만 시작할 수 있습니다.');
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();
}

$source = sr_request_method() === 'POST' ? $_POST : $_GET;
$purpose = sr_identity_verification_purpose((string) ($source['purpose'] ?? ''));
if ($purpose === '') {
    sr_render_error(400, '본인확인 목적이 올바르지 않습니다.');
}

$returnUrl = sr_identity_verification_safe_return_url((string) ($source['return_url'] ?? '/mypage/security'));
$provider = sr_identity_verification_select_provider($pdo, (string) ($source['provider_key'] ?? ''));
if ($provider === null) {
    sr_render_error(503, '사용 가능한 본인확인 제공자가 없습니다.');
}

$attempt = sr_identity_verification_create_attempt($pdo, $config, $provider, (int) $account['id'], $purpose, $returnUrl);

try {
    $prepared = sr_identity_verification_call_provider($provider, 'prepare', [$pdo, $config, $site, $provider, $attempt]);
    if (!is_array($prepared)) {
        throw new RuntimeException('Identity provider prepare result is invalid.');
    }
    sr_identity_verification_mark_attempt($pdo, (int) $attempt['id'], 'pending', [
        'provider_transaction_id' => (string) ($prepared['provider_transaction_id'] ?? ''),
        'provider_reference' => (string) ($prepared['provider_reference'] ?? ''),
    ]);
} catch (Throwable $exception) {
    sr_identity_verification_mark_attempt($pdo, (int) $attempt['id'], 'failed', [
        'failure_code' => 'provider_prepare_failed',
        'failure_message' => 'Provider prepare failed.',
    ]);
    throw $exception;
}

sr_identity_verification_render_provider_form($prepared);
