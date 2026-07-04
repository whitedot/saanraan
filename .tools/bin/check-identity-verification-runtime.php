#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/identity_verification/helpers.php';
require_once $root . '/modules/identity_kcp/helpers/provider.php';

$errors = [];

$identityProvider = [
    'provider_key' => 'identity_fixture',
    'supported_methods' => ['integrated_identity'],
];
$mobileProvider = [
    'provider_key' => 'mobile_fixture',
    'supported_methods' => ['mobile_identity'],
];
$simpleProvider = [
    'provider_key' => 'simple_fixture',
    'supported_methods' => ['simple_auth'],
];

if (!sr_identity_verification_provider_supports_purpose($identityProvider, 'member.registration')) {
    $errors[] = 'integrated identity provider must support registration identity purpose.';
}
if (!sr_identity_verification_provider_supports_purpose($mobileProvider, 'community.adult_board')) {
    $errors[] = 'mobile identity provider must support adult board identity purpose.';
}
if (sr_identity_verification_provider_supports_purpose($simpleProvider, 'member.registration')) {
    $errors[] = 'simple auth provider must not satisfy registration identity purpose.';
}
if (sr_identity_verification_provider_supports_purpose($simpleProvider, 'community.adult_board')) {
    $errors[] = 'simple auth provider must not satisfy adult board identity purpose.';
}
if (!sr_identity_verification_provider_supports_purpose($simpleProvider, 'member.withdrawal')) {
    $errors[] = 'simple auth provider should remain available for one-time withdrawal purpose.';
}

$kcpTestProvider = ['environment' => 'test', 'settings' => []];
$kcpProductionProvider = ['environment' => 'production', 'settings' => []];
if (sr_identity_kcp_site_cd($kcpTestProvider) !== SR_IDENTITY_KCP_TEST_SITE_CD) {
    $errors[] = 'KCP test provider should use the bundled V2 test site code when site_cd is empty.';
}
if (sr_identity_kcp_enc_key($kcpTestProvider) !== SR_IDENTITY_KCP_TEST_ENC_KEY) {
    $errors[] = 'KCP test provider should use the bundled V2 test ENC_KEY when enc_key is empty.';
}
if (sr_identity_kcp_site_cd($kcpProductionProvider) !== '' || sr_identity_kcp_enc_key($kcpProductionProvider) !== '') {
    $errors[] = 'KCP production provider must not fall back to bundled test credentials.';
}
$originalRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$_SERVER['REQUEST_URI'] = '/identity/verify/start?purpose=member.registration';
$identityFormActionSources = function_exists('sr_content_security_policy_form_action_sources')
    ? sr_content_security_policy_form_action_sources()
    : [];
$_SERVER['REQUEST_URI'] = $originalRequestUri;
if (!in_array("'self'", $identityFormActionSources, true)
    || !in_array('https://testcert.kcp.co.kr', $identityFormActionSources, true)
    || !in_array('https://cert.kcp.co.kr', $identityFormActionSources, true)
    || !in_array('https://sa.inicis.com', $identityFormActionSources, true)
) {
    $errors[] = 'Identity verification start response CSP must allow external provider form-action origins.';
}
$originalTimezone = date_default_timezone_get();
date_default_timezone_set('Asia/Seoul');
if (sr_identity_verification_attempt_expired(['expires_at' => gmdate('Y-m-d H:i:s', time() + 600)])) {
    $errors[] = 'Identity verification attempt expiry must interpret stored UTC datetimes as UTC, not local time.';
}
date_default_timezone_set($originalTimezone);
$kcpPayload = ['site_cd' => SR_IDENTITY_KCP_TEST_SITE_CD, 'ordr_idxx' => 'iv_runtime_check'];
$kcpEncrypted = sr_identity_kcp_encrypt_json($kcpTestProvider, $kcpPayload, SR_IDENTITY_KCP_TEST_ENC_KEY, SR_IDENTITY_KCP_TEST_SITE_CD);
$kcpDecrypted = sr_identity_kcp_decrypt_json($kcpTestProvider, (string) $kcpEncrypted['enc_data'], (string) $kcpEncrypted['rv'], SR_IDENTITY_KCP_TEST_ENC_KEY, SR_IDENTITY_KCP_TEST_SITE_CD);
if ($kcpDecrypted !== $kcpPayload) {
    $errors[] = 'KCP V2 bundled crypto fallback should round-trip JSON payloads.';
}

$startAction = file_get_contents($root . '/modules/identity_verification/actions/start.php');
if (!is_string($startAction)) {
    $errors[] = 'identity verification start action must be readable.';
} else {
    $prepareFailureBlock = strstr($startAction, "} catch (Throwable \$exception) {");
    if (!is_string($prepareFailureBlock)
        || !str_contains($prepareFailureBlock, "sr_log_exception(\$exception, 'identity_verification_provider_prepare_failed')")
        || !str_contains($prepareFailureBlock, 'sr_render_error(503,')
        || str_contains($prepareFailureBlock, 'throw $exception;')
    ) {
        $errors[] = 'identity provider prepare failure must render a 503 operator-facing error instead of bubbling to 500.';
    }
    if (!str_contains($startAction, "\$popupMode = (string) (\$source['popup'] ?? '') === '1';")
        || !str_contains($startAction, "'confirm_path' => \$popupMode ? 'popup' : ''")
    ) {
        $errors[] = 'identity verification start action must persist popup mode on popup requests.';
    }
}

$returnAction = file_get_contents($root . '/modules/identity_verification/actions/return.php');
$identityHelpers = file_get_contents($root . '/modules/identity_verification/helpers.php');
if (!is_string($identityHelpers)) {
    $errors[] = 'identity verification helper file must be readable.';
} elseif (!str_contains($identityHelpers, 'function sr_identity_verification_require_provider_response(): void')
    || !str_contains($identityHelpers, "sr_request_contract_mark('csrf_checked');")
    || !str_contains($identityHelpers, 'function sr_identity_verification_identity_snapshot(array $identity): array')
    || !str_contains($identityHelpers, 'function sr_identity_verification_session_identity_snapshot(')
    || !str_contains($identityHelpers, 'function sr_identity_verification_attempt_expired(array $attempt, ?int $now = null): bool')
    || !str_contains($identityHelpers, "new DateTimeZone('UTC')")
    || !str_contains($identityHelpers, 'function sr_identity_verification_result_for_return_token(')
    || !str_contains($identityHelpers, 'function sr_identity_verification_claim_return_token(')
) {
    $errors[] = 'identity verification helper must expose provider response, identity snapshot, UTC expiry, and return token helpers.';
}

if (!is_string($returnAction)) {
    $errors[] = 'identity verification return action must be readable.';
} elseif (!str_contains($returnAction, "\$popupMode = (string) (\$attempt['confirm_path'] ?? '') === 'popup';")
    || !str_contains($returnAction, "include SR_ROOT . '/modules/identity_verification/views/finish.php';")
    || !str_contains($returnAction, 'sr_identity_verification_require_provider_response();')
    || !str_contains($returnAction, 'sr_identity_verification_attempt_expired($attempt)')
    || !str_contains($returnAction, 'identity_verification_token=')
    || !str_contains($returnAction, "(string) (\$attempt['purpose'] ?? '') !== 'member.registration'")
    || !str_contains($returnAction, "sr_log_exception(\$exception, 'identity_verification_provider_verify_failed')")
) {
    $errors[] = 'identity verification return action must handle external provider POST and render popup finish view.';
}

$registerAction = file_get_contents($root . '/modules/member/actions/register.php');
if (!is_string($registerAction)) {
    $errors[] = 'member register action must be readable.';
} elseif (!str_contains($registerAction, "sr_get_string('identity_verification', 30)")
    || !str_contains($registerAction, "sr_get_string('identity_verification_token', 160)")
    || !str_contains($registerAction, "sr_post_string('identity_verification_token', 160)")
    || !str_contains($registerAction, 'sr_identity_verification_result_for_return_token(')
    || str_contains($registerAction, 'sr_identity_verification_claim_return_token(')
    || !str_contains($registerAction, '$registrationIdentityFieldsLocked = $registrationIdentitySatisfied;')
    || !str_contains($registerAction, "sr_identity_verification_hmac_field(\$config, 'name'")
) {
    $errors[] = 'member registration must validate one-form identity verification return tokens and enforce locked identity fields.';
}

$registerView = file_get_contents($root . '/modules/member/views/register.php');
if (!is_string($registerView)) {
    $errors[] = 'member register view must be readable.';
} elseif (!str_contains($registerView, '본인확인 완료')
    || !str_contains($registerView, '본인확인 시간이 만료되었습니다')
    || !str_contains($registerView, 'data-member-identity-locked-field="name"')
    || !str_contains($registerView, 'name="identity_verification_token"')
    || !str_contains($registerView, "searchParams.delete('identity_verification_token')")
    || !str_contains($registerView, "window.addEventListener('pagehide'")
    || !str_contains($registerView, "window.location.replace('/register')")
    || !str_contains($registerView, "sessionStorage.getItem('sr_identity_verification_result')")
) {
    $errors[] = 'member registration view must clearly show identity verification states and lock matched fields.';
}

$callbackAction = file_get_contents($root . '/modules/identity_verification/actions/callback.php');
if (!is_string($callbackAction)) {
    $errors[] = 'identity verification callback action must be readable.';
} elseif (!str_contains($callbackAction, 'sr_identity_verification_require_provider_response();')) {
    $errors[] = 'identity verification callback action must handle external provider POST without site CSRF.';
}

$finishView = file_get_contents($root . '/modules/identity_verification/views/finish.php');
if (!is_string($finishView)) {
    $errors[] = 'identity verification popup finish view must exist.';
} elseif (!str_contains($finishView, 'window.opener.location.href = finishUrl;')
    || !str_contains($finishView, 'window.close();')
    || !str_contains($finishView, "sessionStorage.setItem('sr_identity_verification_result'")
    || !str_contains($finishView, "\$finishResult === 'expired'")
    || !str_contains($finishView, '본인확인 시간이 만료되었습니다')
) {
    $errors[] = 'identity verification popup finish view must refresh opener and show result-specific copy.';
}

$providerFormView = file_get_contents($root . '/modules/identity_verification/views/provider-form.php');
if (!is_string($providerFormView)) {
    $errors[] = 'identity verification provider transfer view must exist.';
} elseif (!str_contains($providerFormView, 'name="form_auth"')
    || !str_contains($providerFormView, 'HTMLFormElement.prototype.submit.call(form);')
    || !str_contains($providerFormView, 'data-identity-provider-submit')
) {
    $errors[] = 'identity verification provider transfer view must support reliable popup form submission.';
}

$commonUi = file_get_contents($root . '/assets/common-ui.js');
if (!is_string($commonUi)) {
    $errors[] = 'common UI script must be readable.';
} elseif (!str_contains($commonUi, "url.pathname !== '/identity/verify/start'")
    || !str_contains($commonUi, "url.searchParams.set('popup', '1')")
    || !str_contains($commonUi, "window.open(url, 'saanraan_identity_verification'")
) {
    $errors[] = 'common UI script must open identity verification start links in a popup.';
}

if ($errors !== []) {
    fwrite(STDERR, "identity verification runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "identity verification runtime checks completed.\n";
