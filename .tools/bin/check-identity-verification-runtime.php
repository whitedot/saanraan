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

$registrationSnapshotConfig = ['app_key' => 'identity-runtime-check-app-key'];
$registrationSnapshotToken = 'runtime-registration-state-token';
unset($_SESSION[sr_identity_verification_registration_snapshot_session_key()]);
sr_identity_verification_remember_registration_snapshot($registrationSnapshotConfig, $registrationSnapshotToken, [
    'name' => '홍길동',
    'phone' => '010-1234-5678',
    'birth_date' => '19830722',
    'age_over_19' => true,
]);
$registrationSnapshot = sr_identity_verification_take_registration_snapshot($registrationSnapshotConfig, $registrationSnapshotToken);
if (($registrationSnapshot['name'] ?? '') !== '홍길동'
    || ($registrationSnapshot['phone'] ?? '') !== '01012345678'
    || ($registrationSnapshot['birth_date'] ?? '') !== '1983-07-22'
    || ($registrationSnapshot['age_over_19'] ?? '') !== '1'
) {
    $errors[] = 'registration identity snapshot must preserve normalized plain values for the first registration render only.';
}
$registrationSnapshotAgain = sr_identity_verification_take_registration_snapshot($registrationSnapshotConfig, $registrationSnapshotToken);
if ($registrationSnapshotAgain !== []) {
    $errors[] = 'registration identity snapshot must be consumed after one read.';
}
$detailQueryParts = sr_identity_verification_admin_attempt_query_parts(['id' => 42, 'q' => '']);
if (!in_array('a.id = :id', $detailQueryParts['where'] ?? [], true) || (int) (($detailQueryParts['params'] ?? [])['id'] ?? 0) !== 42) {
    $errors[] = 'identity verification direct detail routes must use an exact attempt id filter.';
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
    || !str_contains($identityHelpers, "'use_birth_date' => false")
    || !str_contains($identityHelpers, 'function sr_identity_verification_birth_date_enabled(PDO $pdo): bool')
    || !str_contains($identityHelpers, 'function sr_identity_verification_adult_setting_errors(PDO $pdo, bool $adultRequired')
    || !str_contains($identityHelpers, "str_ends_with(\$purpose, '.adult')")
    || !str_contains($identityHelpers, "'content.view.adult'")
    || !str_contains($identityHelpers, "'quiz.view.adult'")
    || !str_contains($identityHelpers, "'survey.view.adult'")
    || !str_contains($identityHelpers, "sr_request_contract_mark('csrf_checked');")
    || !str_contains($identityHelpers, 'function sr_identity_verification_identity_snapshot(array $identity): array')
    || !str_contains($identityHelpers, 'function sr_identity_verification_session_identity_snapshot(')
    || !str_contains($identityHelpers, 'function sr_identity_verification_remember_registration_snapshot(')
    || !str_contains($identityHelpers, 'function sr_identity_verification_take_registration_snapshot(')
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
    || !str_contains($returnAction, 'sr_identity_verification_remember_registration_snapshot($config, $stateToken, $identitySnapshot)')
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
    || !str_contains($registerAction, '$registrationIdentityUseBirthDate = function_exists')
    || !str_contains($registerAction, 'sr_identity_verification_birth_date_enabled($pdo)')
    || !str_contains($registerAction, 'if ($registrationIdentityUseBirthDate && !empty($registrationIdentityResult[\'birth_date\']))')
    || !str_contains($registerAction, 'sr_identity_verification_take_registration_snapshot($config, $registrationIdentityReturnToken)')
    || !str_contains($registerAction, '$registrationIdentityLockedProfileExtraKeys')
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
    || !str_contains($registerView, '$memberRegisterIdentityBirthDateLocked = !empty($registrationIdentityFieldsLocked) && !empty($registrationIdentityUseBirthDate);')
    || !str_contains($registerView, 'var identityBirthDateLocked = <?php echo !empty($memberRegisterIdentityBirthDateLocked)')
    || !str_contains($registerView, 'if (identityBirthDateLocked && birthDate && identity.birth_date)')
    || !str_contains($registerView, 'var serverIdentity = <?php echo sr_js_json_encode')
    || !str_contains($registerView, "'locked_keys' => !empty(\$registrationIdentityFieldsLocked)")
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

$adminVerificationsAction = file_get_contents($root . '/modules/identity_verification/actions/admin-verifications.php');
$adminVerificationsView = file_get_contents($root . '/modules/identity_verification/views/admin-verifications.php');
$adminVerificationsCss = file_get_contents($root . '/modules/identity_verification/assets/admin.css');
if (!is_string($adminVerificationsAction) || !is_string($adminVerificationsView) || !is_string($adminVerificationsCss)) {
    $errors[] = 'identity verification admin verification files must be readable.';
} elseif (!str_contains($adminVerificationsAction, '$openDetailId')
    || !str_contains($adminVerificationsAction, '$attemptDetailsById')
    || !str_contains($adminVerificationsAction, "\$filters['id'] = \$openDetailId;")
    || !str_contains($identityHelpers, 'a.id = :id')
    || !str_contains($adminVerificationsAction, 'WHERE attempt_id IN (')
    || !str_contains($adminVerificationsView, 'data-overlay="#<?php echo sr_e($attemptDetailModalId); ?>"')
    || !str_contains($adminVerificationsView, 'modal-dialog modal-dialog-lg identity-verification-detail-dialog')
    || !str_contains($adminVerificationsView, 'identity-verification-detail-modal-body')
    || !str_contains($adminVerificationsView, 'admin-summary-stats identity-verification-detail-summary')
    || !str_contains($adminVerificationsView, 'badge badge-pill badge-soft-secondary')
    || !str_contains($adminVerificationsView, 'admin-status <?php echo sr_e(sr_identity_verification_attempt_status_class($attemptStatus)); ?>')
    || !str_contains($adminVerificationsView, 'identity-verification-detail-rows')
    || !str_contains($adminVerificationsView, 'class="form-row-label"')
    || !str_contains($adminVerificationsView, 'class="form-field"')
    || str_contains($adminVerificationsView, '$detail !== null')
    || !str_contains($adminVerificationsCss, '.identity-verification-detail-dialog')
    || !str_contains($adminVerificationsCss, '.identity-verification-detail-summary .badge')
    || !str_contains($adminVerificationsCss, '.identity-verification-detail-summary .admin-status')
    || !str_contains($adminVerificationsCss, '.identity-verification-admin-verifications .modal-body .admin-status.is-normal')
    || !str_contains($adminVerificationsCss, '.identity-verification-admin-verifications .modal-body .admin-status.is-left')
    || str_contains($adminVerificationsCss, '.identity-verification-detail-section')
) {
    $errors[] = 'identity verification admin history detail must render as an admin-styled modal from the list.';
}

$adminProvidersAction = file_get_contents($root . '/modules/identity_verification/actions/admin-providers.php');
$adminProvidersView = file_get_contents($root . '/modules/identity_verification/views/admin-providers.php');
if (!is_string($adminProvidersAction) || !is_string($adminProvidersView)) {
    $errors[] = 'identity verification admin provider settings files must be readable.';
} elseif (!str_contains($adminProvidersAction, "'use_birth_date' => (\$_POST['use_birth_date'] ?? '') === '1'")
    || !str_contains($adminProvidersView, 'name="use_birth_date"')
    || !str_contains($adminProvidersView, '성인확인 정책은 이 설정이 켜져 있을 때만 저장할 수 있습니다')
) {
    $errors[] = 'identity verification settings must expose birth date opt-in for registration and adult policies.';
}

$contentSettingsAction = file_get_contents($root . '/modules/content/actions/admin-settings.php');
$contentSettingsView = file_get_contents($root . '/modules/content/views/admin-settings.php');
$contentViewAction = file_get_contents($root . '/modules/content/actions/view.php');
if (!is_string($contentSettingsAction) || !is_string($contentSettingsView) || !is_string($contentViewAction)) {
    $errors[] = 'content identity access files must be readable.';
} elseif (!str_contains($contentSettingsAction, "'identity_content_view_required' => sr_post_string('identity_content_view_required', 1) === '1'")
    || !str_contains($contentSettingsAction, "\$contentIdentityVerificationAvailable = sr_module_enabled(\$pdo, 'identity_verification')")
    || !str_contains($contentSettingsAction, '콘텐츠 본인확인 설정을 사용하려면 본인확인 모듈을 먼저 설치하고 활성화하세요.')
    || !str_contains($contentSettingsAction, "sr_identity_verification_adult_setting_errors(\$pdo, !empty(\$postedSettings['identity_content_view_adult_required']), '콘텐츠 열람 성인 본인확인')")
    || !str_contains($contentSettingsView, '$contentIdentityVerificationAvailable')
    || !str_contains($contentSettingsView, 'content-settings-identity-unavailable')
    || !str_contains($contentSettingsView, "'identity_content_view_required', '1'")
    || !str_contains($contentSettingsView, "'identity_content_view_adult_required', '1'")
    || !str_contains($contentViewAction, "sr_identity_verification_requirement_policy(\$pdo, (int) \$account['id'], 'content.view'")
    || !str_contains($contentViewAction, "sr_identity_verification_account_satisfies_adult(\$pdo, (int) \$account['id'], 'content.view.adult')")
) {
    $errors[] = 'content settings and view action must support identity and adult identity access policies.';
}

$quizHelpers = file_get_contents($root . '/modules/quiz/helpers.php');
$quizSettingsView = file_get_contents($root . '/modules/quiz/views/admin-settings.php');
$quizSkinView = file_get_contents($root . '/modules/quiz/skins/basic/view.php');
if (!is_string($quizHelpers) || !is_string($quizSettingsView) || !is_string($quizSkinView)) {
    $errors[] = 'quiz identity access files must be readable.';
} elseif (!str_contains($quizHelpers, "'identity_view_required' => false")
    || !str_contains($quizHelpers, '퀴즈 본인확인 설정을 사용하려면 본인확인 모듈을 먼저 설치하고 활성화하세요.')
    || !str_contains($quizHelpers, 'function sr_quiz_enforce_identity_view_policy(')
    || !str_contains($quizHelpers, "sr_identity_verification_requirement_policy(\$pdo, \$accountId, 'quiz.view'")
    || !str_contains($quizHelpers, "sr_identity_verification_account_satisfies_adult(\$pdo, \$accountId, 'quiz.view.adult')")
    || !str_contains($quizSettingsView, '$quizIdentityVerificationAvailable')
    || !str_contains($quizSettingsView, 'quiz-settings-identity-unavailable')
    || !str_contains($quizSettingsView, "'identity_view_required', '1'")
    || !str_contains($quizSettingsView, "'identity_view_adult_required', '1'")
    || !str_contains($quizSkinView, 'sr_quiz_enforce_identity_view_policy($pdo, $quiz, $quizSettings, $currentAccount, $canPreviewAsAdmin);')
) {
    $errors[] = 'quiz settings and view screens must support identity and adult identity participation policies.';
}

$surveyHelpers = file_get_contents($root . '/modules/survey/helpers.php');
$surveySettingsView = file_get_contents($root . '/modules/survey/views/admin-settings.php');
$surveySkinView = file_get_contents($root . '/modules/survey/skins/basic/view.php');
if (!is_string($surveyHelpers) || !is_string($surveySettingsView) || !is_string($surveySkinView)) {
    $errors[] = 'survey identity access files must be readable.';
} elseif (!str_contains($surveyHelpers, "'identity_view_required' => false")
    || !str_contains($surveyHelpers, '설문 본인확인 설정을 사용하려면 본인확인 모듈을 먼저 설치하고 활성화하세요.')
    || !str_contains($surveyHelpers, 'function sr_survey_enforce_identity_view_policy(')
    || !str_contains($surveyHelpers, "sr_identity_verification_requirement_policy(\$pdo, \$accountId, 'survey.view'")
    || !str_contains($surveyHelpers, "sr_identity_verification_account_satisfies_adult(\$pdo, \$accountId, 'survey.view.adult')")
    || !str_contains($surveySettingsView, '$surveyIdentityVerificationAvailable')
    || !str_contains($surveySettingsView, 'survey-settings-identity-unavailable')
    || !str_contains($surveySettingsView, "'identity_view_required', '1'")
    || !str_contains($surveySettingsView, "'identity_view_adult_required', '1'")
    || !str_contains($surveySkinView, 'sr_survey_enforce_identity_view_policy($pdo, $survey, $settings, $currentAccount, $canPreviewAsAdmin);')
) {
    $errors[] = 'survey settings and view screens must support identity and adult identity participation policies.';
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
