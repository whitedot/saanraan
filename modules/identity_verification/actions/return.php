<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/identity_verification/helpers.php';

$request = sr_identity_verification_request_data();
$stateToken = sr_identity_verification_extract_state($request);
$attempt = sr_identity_verification_attempt_by_state($pdo, $config, $stateToken);
if ($attempt === null) {
    sr_render_error(400, '본인확인 요청을 찾을 수 없습니다.');
}

$returnUrl = sr_identity_verification_safe_return_url((string) ($attempt['return_url'] ?? '/'));
if (strtotime((string) $attempt['expires_at']) !== false && strtotime((string) $attempt['expires_at']) < time()) {
    sr_identity_verification_mark_attempt($pdo, (int) $attempt['id'], 'expired', [
        'failure_code' => 'attempt_expired',
        'failure_message' => 'Identity verification attempt expired.',
    ]);
    sr_redirect($returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'identity_verification=expired');
}

$providers = sr_identity_verification_providers($pdo);
$provider = $providers[(string) $attempt['provider_key']] ?? null;
if (!is_array($provider)) {
    sr_render_error(503, '본인확인 제공자 계약을 찾을 수 없습니다.');
}

try {
    $verification = sr_identity_verification_call_provider($provider, 'verify_return', [$pdo, $config, $site, $provider, $attempt, $request]);
    if (!is_array($verification)) {
        throw new RuntimeException('Identity provider verification result is invalid.');
    }
    $status = (string) ($verification['status'] ?? 'failed');
    if ($status === 'verified') {
        sr_identity_verification_complete($pdo, $config, $attempt, $verification);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) ($attempt['account_id'] ?? 0),
            'actor_type' => 'member',
            'event_type' => 'identity_verification.verified',
            'target_type' => 'identity_verification_attempt',
            'target_id' => (string) $attempt['id'],
            'result' => 'success',
            'message' => 'Identity verification completed.',
            'metadata' => [
                'provider_key' => (string) $attempt['provider_key'],
                'purpose' => (string) $attempt['purpose'],
            ],
        ]);
        sr_redirect($returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'identity_verification=success');
    }

    sr_identity_verification_mark_attempt($pdo, (int) $attempt['id'], $status === 'canceled' ? 'canceled' : 'failed', [
        'failure_code' => (string) ($verification['failure_code'] ?? 'provider_verification_failed'),
        'failure_message' => (string) ($verification['failure_message'] ?? 'Identity provider verification failed.'),
    ]);
    sr_redirect($returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'identity_verification=' . ($status === 'canceled' ? 'canceled' : 'failed'));
} catch (Throwable $exception) {
    sr_identity_verification_mark_attempt($pdo, (int) $attempt['id'], 'failed', [
        'failure_code' => 'provider_verify_failed',
        'failure_message' => 'Provider verify failed.',
    ]);
    throw $exception;
}
