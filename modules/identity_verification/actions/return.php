<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/identity_verification/helpers.php';

sr_identity_verification_require_provider_response();

$request = sr_identity_verification_request_data();
$stateToken = sr_identity_verification_extract_state($request);
$attempt = sr_identity_verification_attempt_by_state($pdo, $config, $stateToken);
if ($attempt === null) {
    sr_render_error(400, '본인확인 요청을 찾을 수 없습니다.');
}

$returnUrl = sr_identity_verification_safe_return_url((string) ($attempt['return_url'] ?? '/'));
$popupMode = (string) ($attempt['confirm_path'] ?? '') === 'popup';
$finishIdentityVerification = static function (string $returnUrl, string $result, array $identitySnapshot = []) use ($popupMode, $stateToken, $attempt): void {
    $finishUrl = $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'identity_verification=' . rawurlencode($result);
    if ($result === 'success' && $stateToken !== '') {
        $finishUrl .= '&identity_verification_token=' . rawurlencode($stateToken);
    }
    if (!$popupMode) {
        sr_redirect($finishUrl);
    }

    $finishResult = $result;
    $finishPurpose = (string) ($attempt['purpose'] ?? '');
    $finishIdentitySnapshot = sr_identity_verification_identity_snapshot($identitySnapshot);
    include SR_ROOT . '/modules/identity_verification/views/finish.php';
    sr_finish_response();
};
if (sr_identity_verification_attempt_expired($attempt)) {
    sr_identity_verification_mark_attempt($pdo, (int) $attempt['id'], 'expired', [
        'failure_code' => 'attempt_expired',
        'failure_message' => 'Identity verification attempt expired.',
    ]);
    $finishIdentityVerification($returnUrl, 'expired');
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
        $resultId = sr_identity_verification_complete($pdo, $config, $attempt, $verification);
        $identitySnapshot = isset($verification['identity']) && is_array($verification['identity'])
            ? sr_identity_verification_identity_snapshot($verification['identity'])
            : [];
        if ((string) ($attempt['purpose'] ?? '') !== 'member.registration') {
            sr_identity_verification_remember_session_result($attempt, $resultId, $identitySnapshot);
        }
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
        $finishIdentityVerification($returnUrl, 'success', $identitySnapshot);
    }

    sr_identity_verification_mark_attempt($pdo, (int) $attempt['id'], $status === 'canceled' ? 'canceled' : 'failed', [
        'failure_code' => (string) ($verification['failure_code'] ?? 'provider_verification_failed'),
        'failure_message' => (string) ($verification['failure_message'] ?? 'Identity provider verification failed.'),
    ]);
    $finishIdentityVerification($returnUrl, $status === 'canceled' ? 'canceled' : 'failed');
} catch (SrIdentityVerificationDuplicateException $exception) {
    sr_identity_verification_mark_attempt($pdo, (int) $attempt['id'], 'failed', [
        'failure_code' => 'duplicate_identity',
        'failure_message' => 'Identity verification result is already linked to another account.',
    ]);
    $finishIdentityVerification($returnUrl, 'duplicate');
} catch (Throwable $exception) {
    sr_identity_verification_mark_attempt($pdo, (int) $attempt['id'], 'failed', [
        'failure_code' => 'provider_verify_failed',
        'failure_message' => 'Provider verify failed.',
    ]);
    sr_log_exception($exception, 'identity_verification_provider_verify_failed');
    $finishIdentityVerification($returnUrl, 'failed');
}
