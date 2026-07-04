<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/identity_verification/helpers.php';
if (is_file(SR_ROOT . '/modules/member/helpers.php')) {
    require_once SR_ROOT . '/modules/member/helpers.php';
}

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
$popupMode = (string) ($source['popup'] ?? '') === '1';

$account = function_exists('sr_member_current_account') ? sr_member_current_account($pdo) : null;
$guestAllowed = in_array($purpose, ['member.registration'], true);
if (!is_array($account) && $purpose === 'member.mfa.login' && function_exists('sr_member_mfa_challenge') && function_exists('sr_member_find_by_id')) {
    $mfaChallenge = sr_member_mfa_challenge();
    $mfaAccountId = is_array($mfaChallenge) ? (int) ($mfaChallenge['account_id'] ?? 0) : 0;
    $mfaAccount = $mfaAccountId > 0 ? sr_member_find_by_id($pdo, $mfaAccountId) : null;
    if (is_array($mfaAccount) && (string) ($mfaAccount['status'] ?? '') === 'active') {
        $account = $mfaAccount;
    }
}
if (!is_array($account) && !$guestAllowed) {
    $account = sr_member_require_login($pdo);
}

$returnUrl = sr_identity_verification_safe_return_url((string) ($source['return_url'] ?? '/mypage/security'));
$provider = sr_identity_verification_select_provider($pdo, (string) ($source['provider_key'] ?? ''), $purpose);
if ($provider === null) {
    sr_render_error(503, '사용 가능한 본인확인 제공자가 없습니다.');
}

$attempt = sr_identity_verification_create_attempt($pdo, $config, $provider, is_array($account) ? (int) $account['id'] : 0, $purpose, $returnUrl, [
    'confirm_path' => $popupMode ? 'popup' : '',
]);

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
    sr_log_exception($exception, 'identity_verification_provider_prepare_failed');
    sr_render_error(503, '본인확인 제공자 요청을 준비하지 못했습니다. 관리자에게 본인확인 제공자 설정 확인을 요청해 주세요.');
}

sr_identity_verification_render_provider_form($prepared);
