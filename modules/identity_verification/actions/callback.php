<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/identity_verification/helpers.php';

$request = sr_identity_verification_request_data();
$stateToken = sr_identity_verification_extract_state($request);
$attempt = sr_identity_verification_attempt_by_state($pdo, $config, $stateToken);
if ($attempt === null) {
    sr_json_response(['ok' => false, 'message' => 'attempt_not_found'], 400);
}

$providers = sr_identity_verification_providers($pdo);
$provider = $providers[(string) $attempt['provider_key']] ?? null;
if (!is_array($provider)) {
    sr_json_response(['ok' => false, 'message' => 'provider_not_found'], 503);
}

try {
    $verification = sr_identity_verification_call_provider($provider, 'verify_callback', [$pdo, $config, $site, $provider, $attempt, $request]);
    if (!is_array($verification)) {
        throw new RuntimeException('Identity provider callback result is invalid.');
    }
    if ((string) ($verification['status'] ?? '') === 'verified') {
        sr_identity_verification_complete($pdo, $config, $attempt, $verification);
        sr_json_response(['ok' => true, 'status' => 'verified']);
    }
    sr_identity_verification_mark_attempt($pdo, (int) $attempt['id'], 'failed', [
        'failure_code' => (string) ($verification['failure_code'] ?? 'provider_callback_failed'),
        'failure_message' => (string) ($verification['failure_message'] ?? 'Identity provider callback failed.'),
    ]);
    sr_json_response(['ok' => true, 'status' => 'failed']);
} catch (Throwable $exception) {
    sr_identity_verification_mark_attempt($pdo, (int) $attempt['id'], 'failed', [
        'failure_code' => 'provider_callback_exception',
        'failure_message' => 'Provider callback failed.',
    ]);
    sr_json_response(['ok' => false, 'message' => 'provider_callback_exception'], 500);
}
