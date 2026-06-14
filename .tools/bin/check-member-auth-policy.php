#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);

require_once $root . '/core/helpers/output.php';
require_once $root . '/modules/member/helpers/accounts.php';
require_once $root . '/modules/member/helpers/tokens.php';
require_once $root . '/modules/member/helpers/throttle.php';

$errors = [];

function sr_member_auth_policy_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_member_auth_policy_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_member_auth_policy_error($message);
    }
}

function sr_member_auth_policy_read(string $path): string
{
    global $root;

    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content)) {
        sr_member_auth_policy_error('Cannot read file: ' . $path);
        return '';
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
}

function sr_member_auth_policy_forbid_markers(string $path, array $markers): void
{
    $content = sr_member_auth_policy_read($path);
    if ($content === '') {
        return;
    }

    foreach ($markers as $marker) {
        sr_member_auth_policy_assert(
            strpos($content, $marker) === false,
            'Sensitive member input should not use truncating helper in ' . $path . ': ' . $marker
        );
    }
}

function sr_member_auth_policy_check_input_helper_fixtures(): void
{
    $previousGet = $_GET;
    $previousPost = $_POST;

    $_GET = ['token' => str_repeat('a', 64)];
    sr_member_auth_policy_assert(
        sr_get_string_without_truncation('token', 64) === str_repeat('a', 64),
        'GET no-truncation helper should accept values at the maximum length.'
    );

    $_GET = ['token' => str_repeat('a', 65)];
    sr_member_auth_policy_assert(
        sr_get_string_without_truncation('token', 64) === null,
        'GET no-truncation helper should reject overlong token values instead of trimming them for lookup.'
    );

    $_GET = ['token' => ['array']];
    sr_member_auth_policy_assert(
        sr_get_string_without_truncation('token', 64) === null,
        'GET no-truncation helper should reject array token values.'
    );

    $_POST = ['password' => str_repeat('b', 255)];
    sr_member_auth_policy_assert(
        sr_post_string_without_truncation('password', 255) === str_repeat('b', 255),
        'POST no-truncation helper should accept values at the maximum length.'
    );

    $_POST = ['password' => str_repeat('b', 256)];
    sr_member_auth_policy_assert(
        sr_post_string_without_truncation('password', 255) === null,
        'POST no-truncation helper should reject overlong password values instead of trimming them.'
    );

    $_POST = ['email' => ['array']];
    sr_member_auth_policy_assert(
        sr_post_string_without_truncation('email', 255) === null,
        'POST no-truncation helper should reject array email values.'
    );

    $_GET = $previousGet;
    $_POST = $previousPost;
}

sr_member_auth_policy_check_input_helper_fixtures();

$memberLang = sr_member_auth_policy_read('modules/member/lang/ko.php');
$privacyLang = sr_member_auth_policy_read('modules/privacy/lang/ko.php');

$unverifiedAccount = [
    'id' => 1,
    'status' => 'active',
    'email_verified_at' => null,
];
$verifiedAccount = [
    'id' => 1,
    'status' => 'active',
    'email_verified_at' => '2026-04-01 00:00:00',
];

sr_member_auth_policy_assert(
    sr_member_email_verification_blocks_login(['email_verification_enabled' => true], $unverifiedAccount),
    'Email verification should block active unverified accounts when enabled.'
);
sr_member_auth_policy_assert(
    !sr_member_email_verification_blocks_login(['email_verification_enabled' => false], $unverifiedAccount),
    'Email verification should not block login when disabled.'
);
sr_member_auth_policy_assert(
    !sr_member_email_verification_blocks_login(['email_verification_enabled' => true], $verifiedAccount),
    'Verified account should not be blocked by email verification policy.'
);
sr_member_auth_policy_assert(
    !sr_member_email_verification_blocks_login(['email_verification_enabled' => true], null),
    'Missing account should not be treated as email verification block.'
);

$_SESSION = [];
$sampleTokenHash = str_repeat('a', 64);
sr_member_store_password_reset_session_hash($sampleTokenHash);
sr_member_auth_policy_assert(
    sr_member_password_reset_session_hash(900) === $sampleTokenHash,
    'Password reset session hash should be readable within its lifetime.'
);
$_SESSION['sr_password_reset_token_stored_at'] = (string) (time() - 901);
sr_member_auth_policy_assert(
    sr_member_password_reset_session_hash(900) === '',
    'Password reset session hash should expire after its short lifetime.'
);
sr_member_auth_policy_assert(
    !isset($_SESSION['sr_password_reset_token_hash'], $_SESSION['sr_password_reset_token_stored_at']),
    'Expired password reset session hash should be cleared.'
);
sr_member_auth_policy_assert(
    in_array('login_email_unverified', sr_member_login_failure_event_types(), true),
    'Unverified email login blocks should count as login failure throttle events.'
);
sr_member_auth_policy_assert(
    in_array('login_session_failed', sr_member_login_failure_event_types(), true),
    'Login session creation failures should count as login failure throttle events.'
);
sr_member_auth_policy_assert(
    in_array('password_change_reauth', sr_member_reauth_failure_event_types(), true)
        && in_array('password_change_session_failed', sr_member_reauth_failure_event_types(), true)
        && in_array('withdraw_reauth', sr_member_reauth_failure_event_types(), true)
        && in_array('privacy_export_reauth', sr_member_reauth_failure_event_types(), true)
        && in_array('module_setting_reauth', sr_member_reauth_failure_event_types(), true)
        && in_array('privacy_request_export_reauth', sr_member_reauth_failure_event_types(), true)
        && in_array('reauth_blocked', sr_member_reauth_failure_event_types(), true),
    'Sensitive reauth failures should count as reauth throttle events.'
);

$loginAction = sr_member_auth_policy_read('modules/member/actions/login.php');
if ($loginAction !== '') {
    sr_member_auth_policy_assert(
        strpos($loginAction, "sr_post_string_without_truncation('identifier', 255)") !== false
            && strpos($loginAction, '$identifier === null') !== false,
        'Login action should reject overlong raw identifiers instead of truncating them for account lookup.'
    );
    sr_member_auth_policy_assert(
        strpos($loginAction, 'sr_member_email_verification_blocks_login') !== false,
        'Login action should enforce email verification policy.'
    );
    sr_member_auth_policy_assert(
        strpos($loginAction, 'sr_member_email_verification_throttle_status($pdo, (int) $account[\'id\'])') !== false
            && strpos($loginAction, 'sr_member_create_email_verification($pdo, $config, (int) $account[\'id\'], (string) $account[\'email\'])') !== false,
        'Login action should resend email verification within throttle limits after a valid password for an unverified account.'
    );
    sr_member_auth_policy_assert(
        strpos($loginAction, 'login_email_unverified') !== false,
        'Login action should log unverified email login blocks.'
    );
    sr_member_auth_policy_assert(
        strpos($loginAction, "'mail_sent' => \$mailSent") !== false,
        'Login action should audit email verification resend mail delivery result.'
    );
    sr_member_auth_policy_assert(
        strpos($loginAction, 'email_verification_mail_failed') !== false,
        'Login action should write an auth log event when verification mail delivery fails.'
    );
    sr_member_auth_policy_assert(
        strpos($loginAction, '$showVerificationUrl = !empty($config[\'debug\']) && sr_is_local_host((string) ($site[\'base_url\'] ?? \'\'));') !== false
            && strpos($loginAction, 'if ($showVerificationUrl)') !== false,
        'Login action should only store debug email verification URLs when the configured site base URL is localhost.'
    );
    sr_member_auth_policy_assert(
        strpos($loginAction, "unset(\$_SESSION['sr_debug_email_verification_url']);") !== false,
        'Login action should clear stale debug email verification URLs outside localhost debug mode.'
    );
    sr_member_auth_policy_assert(
        strpos($loginAction, "sr_get_string('password_reset', 10) === '1'") !== false
            && (
                strpos($loginAction, "sr_t('member::action.login.password_reset_notice')") !== false
                || strpos($loginAction, '비밀번호를 재설정했습니다. 새 비밀번호로 로그인하세요.') !== false
            ),
        'Login action should show a fixed completion notice after password reset redirect.'
    );
}

sr_member_auth_policy_forbid_markers('modules/member/actions/login.php', [
    "sr_post_string('identifier'",
]);
sr_member_auth_policy_forbid_markers('modules/member/actions/email-verify.php', [
    "sr_get_string('token'",
]);
sr_member_auth_policy_forbid_markers('modules/member/actions/password-reset.php', [
    "sr_get_string('token'",
    "sr_post_string('password'",
    "sr_post_string('password_confirm'",
]);
sr_member_auth_policy_forbid_markers('modules/member/actions/password-reset-request.php', [
    "sr_post_string('email'",
]);
sr_member_auth_policy_forbid_markers('modules/member/actions/register.php', [
    "sr_post_string('email'",
    "sr_post_string('login_id'",
    "sr_post_string('password'",
    "sr_post_string('password_confirm'",
]);

$accountHelper = sr_member_auth_policy_read('modules/member/helpers/accounts.php');
if ($accountHelper !== '') {
    sr_member_auth_policy_assert(
        strpos($accountHelper, 'sr_member_email_verification_blocks_login($settings, $account)') !== false,
        'Current member session should be rejected when email verification is still required.'
    );
    sr_member_auth_policy_assert(
        strpos($accountHelper, "if (!array_key_exists('sr_account_id', \$_SESSION)) {\n        sr_member_revoke_current_session(\$pdo);\n        unset(\$_SESSION['sr_session_token_hash']);\n        return null;\n    }") !== false
            && strpos($accountHelper, "if (!is_int(\$accountId) && !ctype_digit((string) \$accountId)) {\n        sr_member_logout(\$pdo);\n        return null;\n    }") !== false
            && strpos($accountHelper, "if (\$accountId < 1) {\n        sr_member_logout(\$pdo);\n        return null;\n    }") !== false
            && strpos($accountHelper, "if (!is_array(\$account)) {\n        sr_member_logout(\$pdo);\n        return null;\n    }") !== false,
        'Current member account lookup should clear PHP session state when the session account is invalid or missing.'
    );
    sr_member_auth_policy_assert(
        strpos($accountHelper, 'function sr_member_rehash_login_password_if_needed') !== false
            && strpos($accountHelper, 'password_needs_rehash($currentHash, PASSWORD_DEFAULT)') !== false,
        'Login password rehash helper should upgrade stale password hashes.'
    );
    sr_member_auth_policy_assert(
        strpos($accountHelper, 'function sr_member_public_account_summary(PDO $pdo, int $accountId): ?array') !== false
            && strpos($accountHelper, 'SELECT a.id, a.display_name, a.locale, a.status') !== false
            && strpos($accountHelper, "'display_name' => (string) \$account['display_name']") !== false
            && strpos($accountHelper, "'nickname' => (string) (\$account['nickname'] ?? '')") !== false
            && strpos($accountHelper, "'public_name' => sr_member_public_name(\$account, \$settings)") !== false
            && strpos($accountHelper, "'locale' => (string) \$account['locale']") !== false
            && strpos($accountHelper, "'status' => (string) \$account['status']") !== false,
        'Public account summary helper should expose only non-sensitive account summary fields.'
    );
    sr_member_auth_policy_assert(
        strpos($accountHelper, 'email_hash = :email_hash') !== false
            && strpos($accountHelper, 'login_id_hash = :login_id_hash') !== false
            && strpos($accountHelper, 'if ($isEmailIdentifier && !$allowEmailLogin)') === false,
        'Login identifier lookup should always allow email lookup and also support login_id lookup.'
    );
}

$throttleHelper = sr_member_auth_policy_read('modules/member/helpers/throttle.php');
if ($throttleHelper !== '') {
    sr_member_auth_policy_assert(
        strpos($throttleHelper, 'sr_member_login_failure_event_types()') !== false,
        'Login throttle should use the shared login failure event list.'
    );
    sr_member_auth_policy_assert(
        strpos($throttleHelper, 'function sr_member_reauth_throttle_status') !== false
            && strpos($throttleHelper, 'member.reauth.account') !== false
            && strpos($throttleHelper, 'member.reauth.ip') !== false,
        'Sensitive reauth throttle should track account and IP failures.'
    );
}

$settingsHelper = sr_member_auth_policy_read('modules/member/helpers/settings.php');
if ($settingsHelper !== '') {
    sr_member_auth_policy_assert(
        strpos($settingsHelper, 'function sr_member_profile_field_setting_keys') !== false
            && strpos($settingsHelper, 'profile_phone_enabled') !== false
            && strpos($settingsHelper, 'profile_birth_date_enabled') !== false
            && strpos($settingsHelper, 'profile_avatar_enabled') !== false
            && strpos($settingsHelper, 'profile_text_enabled') !== false,
        'Member settings helper should define configurable optional profile fields.'
    );
    sr_member_auth_policy_assert(
        strpos($settingsHelper, 'function sr_member_profile_field_settings') !== false
            && strpos($settingsHelper, 'function sr_member_profile_field_policies') !== false
            && strpos($settingsHelper, 'profile_phone_required') !== false
            && strpos($settingsHelper, 'profile_avatar_required') !== false,
        'Member settings helper should expose normalized optional profile visibility and required policies.'
    );
    sr_member_auth_policy_assert(
        strpos($settingsHelper, 'profile_nickname_enabled') === false
            && strpos($settingsHelper, 'profile_nickname_required') === false,
        'Member settings helper should not define nickname policies because community owns nicknames.'
    );
}

$profileHelper = sr_member_auth_policy_read('modules/member/helpers/profile.php');
if ($profileHelper !== '') {
    sr_member_auth_policy_assert(
        strpos($profileHelper, "sr_post_string('nickname'") === false
            && strpos($profileHelper, "'nickname' =>") === false
            && strpos($profileHelper, 'SELECT nickname') === false,
        'Member profile helper should not accept, return, or read member-owned nicknames.'
    );
}

$sessionHelper = sr_member_auth_policy_read('modules/member/helpers/sessions.php');
if ($sessionHelper !== '') {
    sr_member_auth_policy_assert(
        strpos($sessionHelper, 'function sr_member_rotate_current_session') !== false
            && strpos($sessionHelper, 'session_regenerate_id(true)') !== false
            && strpos($sessionHelper, 'sr_member_create_session($pdo, $accountId)') !== false
            && strpos($sessionHelper, 'if (!sr_member_sessions_table_exists($pdo))') !== false,
        'Current member session rotation helper should regenerate PHP and member session tokens.'
    );
    sr_member_auth_policy_assert(
        strpos($sessionHelper, 'function sr_member_login(PDO $pdo, array $account): bool') !== false
            && strpos($sessionHelper, "if (\$sessionTokenHash !== '') {\n        \$_SESSION['sr_session_token_hash'] = \$sessionTokenHash;") !== false
            && strpos($sessionHelper, "unset(\$_SESSION['sr_session_token_hash']);") !== false,
        'Member login should clear stale session token hash when DB session creation fails.'
    );
    sr_member_auth_policy_assert(
        strpos($sessionHelper, 'sr_member_sessions_table_exists($pdo)') !== false
            && strpos($sessionHelper, "unset(\$_SESSION['sr_account_id']);") !== false
            && strpos($sessionHelper, 'return false;') !== false,
        'Member login should fail clearly when DB session creation fails while the session table exists.'
    );
    sr_member_auth_policy_assert(
        strpos($sessionHelper, 'function sr_member_revoke_account_sessions') !== false
            && strpos($sessionHelper, 'function sr_member_revoke_other_sessions') !== false
            && strpos($sessionHelper, 'return -1;') !== false,
        'Member session revocation helpers should distinguish DB failure from zero revoked sessions.'
    );
    sr_member_auth_policy_assert(
        strpos($sessionHelper, 'function sr_member_revoke_current_session(PDO $pdo): int') !== false
            && strpos($sessionHelper, 'function sr_member_logout(?PDO $pdo = null): bool') !== false
            && strpos($sessionHelper, '$sessionRevoked = sr_member_revoke_current_session($pdo) >= 0;') !== false,
        'Current session revocation and logout helpers should report DB revocation failure.'
    );
    sr_member_auth_policy_assert(
        strpos($sessionHelper, 'function sr_member_logout_current_session_if_account') !== false
            && strpos($sessionHelper, 'sr_member_current_session_account_id()') !== false,
        'Session helper should support immediate logout of the current session for a target account.'
    );
}

$logoutAction = sr_member_auth_policy_read('modules/member/actions/logout.php');
if ($logoutAction !== '') {
    sr_member_auth_policy_assert(
        strpos($logoutAction, '$loggedOut = sr_member_logout($pdo)') !== false
            && strpos($logoutAction, "'current_session_revoked' => \$loggedOut") !== false
            && strpos($logoutAction, "\$loggedOut ? 'success' : 'failure'") !== false,
        'Logout action should audit current session revocation failure instead of logging unconditional success.'
    );
}

$accountAction = sr_member_auth_policy_read('modules/member/actions/account.php');
if ($accountAction !== '') {
    sr_member_auth_policy_assert(
        strpos($accountAction, "in_array(\$intent, ['basics', 'profile', 'password'], true)") !== false
            && (
                strpos($accountAction, "sr_t('member::action.account.intent_invalid')") !== false
                || strpos($accountAction, '계정 작업 값이 올바르지 않습니다.') !== false
            ),
        'Account action should allowlist account update intents.'
    );
    sr_member_auth_policy_assert(
        strpos($accountAction, 'sr_member_rotate_current_session($pdo, (int) $account[\'id\'])') !== false,
        'Password change should rotate the current member session.'
    );
    sr_member_auth_policy_assert(
        strpos($accountAction, 'password_change_session_failed') !== false
            && strpos($accountAction, 'sr_member_logout($pdo)') !== false,
        'Password change should not remain logged in when current session rotation fails.'
    );
    sr_member_auth_policy_assert(
        strpos($accountAction, 'if ($revokedSessions < 0)') !== false
            && strpos($accountAction, 'Other member sessions could not be revoked after password change.') !== false,
        'Password change should not silently continue when other sessions cannot be revoked.'
    );
    sr_member_auth_policy_assert(
        strpos($accountAction, 'sr_member_reauth_throttle_status($pdo, (int) $account[\'id\'])') !== false
            && strpos($accountAction, 'password_change_reauth') !== false,
        'Password change should throttle current-password reauth failures.'
    );
    sr_member_auth_policy_assert(
        strpos($accountAction, "sr_post_string_without_truncation('new_password', 255)") !== false
            && strpos($accountAction, "sr_post_string_without_truncation('new_password_confirm', 255)") !== false
            && strpos($accountAction, '$newPassword === null || $newPasswordConfirm === null') !== false,
        'Password change should reject overlong raw new-password inputs instead of truncating them.'
    );
    sr_member_auth_policy_assert(
        strpos($accountAction, '$profilePolicies = sr_member_profile_field_policies($memberSettings)') !== false
            && strpos($accountAction, 'sr_member_profile_values_from_post($profilePolicies, $profile)') !== false
            && strpos($accountAction, "['validate_avatar' => false]") !== false
            && strpos($accountAction, 'sr_member_profile_validation_errors($profile, $profilePolicies)') !== false,
        'Account action should update and validate optional profile fields through normalized profile policies.'
    );
    sr_member_auth_policy_assert(
        strpos($accountAction, 'sr_member_avatar_upload_was_provided($_FILES[\'avatar_file\'] ?? null)') !== false
            && strpos($accountAction, 'sr_member_upload_avatar($_FILES[\'avatar_file\'])') !== false
            && strpos($accountAction, 'sr_member_delete_avatar_reference($uploadedAvatarReference)') !== false,
        'Account action should handle member avatar as a validated upload instead of a submitted URL.'
    );
    sr_member_auth_policy_assert(
        strpos($accountAction, 'sr_is_local_host((string) ($site[\'base_url\'] ?? \'\'))') !== false
            && strpos($accountAction, 'sr_debug_email_verification_url') !== false,
        'Account action should only render debug email verification URLs for localhost site base URLs.'
    );
}

$withdrawAction = sr_member_auth_policy_read('modules/member/actions/withdraw.php');
if ($withdrawAction !== '') {
    sr_member_auth_policy_assert(
        strpos($withdrawAction, 'sr_member_reauth_throttle_status($pdo, (int) $account[\'id\'])') !== false
            && strpos($withdrawAction, 'withdraw_reauth') !== false,
        'Withdraw should throttle current-password reauth failures.'
    );
    sr_member_auth_policy_assert(
        strpos($withdrawAction, 'if ($revokedSessions < 0)') !== false
            && strpos($withdrawAction, 'Member sessions could not be revoked before account withdrawal.') !== false,
        'Withdraw should not continue when account sessions cannot be revoked.'
    );
    sr_member_auth_policy_assert(
        strpos($withdrawAction, 'sr_member_record_consent_withdrawals($pdo, (int) $account[\'id\'])') !== false
            && strpos($withdrawAction, "'withdrawn_consents' => \$withdrawnConsents") !== false,
        'Withdraw should record consent withdrawals before account anonymization.'
    );
}

$privacyExportAction = sr_member_auth_policy_read('modules/privacy/actions/account-privacy-export.php');
if ($privacyExportAction !== '') {
    sr_member_auth_policy_assert(
        strpos($privacyExportAction, 'sr_member_privacy_export_reauth_errors($pdo, $account)') !== false
            && strpos($privacyExportAction, 'sr_render_error(403, $reauthError)') !== false,
        'Privacy export action should enforce current-password reauthentication before generating JSON.'
    );
    sr_member_auth_policy_assert(
        strpos($privacyExportAction, 'JSON_INVALID_UTF8_SUBSTITUTE') !== false
            && strpos($privacyExportAction, '$encodedExport = json_encode($export') !== false
            && strpos($privacyExportAction, 'if (!is_string($encodedExport))') !== false
            && strpos($privacyExportAction, 'echo $encodedExport;') !== false,
        'Privacy export action should encode JSON safely before sending download headers.'
    );
}

$memberSettingsHelper = sr_member_auth_policy_read('modules/member/helpers/settings.php');
$memberLoginAction = sr_member_auth_policy_read('modules/member/actions/login.php');
if ($memberSettingsHelper !== '' && $memberLoginAction !== '') {
    sr_member_auth_policy_assert(
        strpos($memberSettingsHelper, 'function sr_member_skin_options(): array') !== false
            && strpos($memberSettingsHelper, "SR_ROOT . '/modules/member/skins/basic/login.php'") !== false
            && strpos($memberLoginAction, "sr_member_skin_view(sr_member_skin_key(\$memberSettings), 'login')") !== false
            && is_file($root . '/modules/member/skins/basic/login.php'),
        'Member public views should render through explicit member skin views with a basic fallback.'
    );
}

$privacyHelper = sr_member_auth_policy_read('modules/member/helpers/privacy.php');
$privacyOrchestrationHelper = sr_member_auth_policy_read('modules/privacy/helpers/requests.php');
if ($privacyHelper !== '' && $privacyOrchestrationHelper !== '') {
    sr_member_auth_policy_assert(
        strpos($privacyHelper, 'function sr_member_privacy_export_reauth_errors') !== false
            && strpos($privacyHelper, "sr_post_string('current_password', 255)") !== false
            && strpos($privacyHelper, 'sr_member_reauth_throttle_status($pdo, $accountId)') !== false
            && strpos($privacyHelper, 'privacy_export_reauth') !== false
            && strpos($privacyHelper, 'privacy.export.reauth_failed') !== false,
        'Privacy helper should require throttled current-password reauthentication for member privacy exports.'
    );
    sr_member_auth_policy_assert(
        strpos($privacyOrchestrationHelper, 'function sr_privacy_export_sanitize_module_data') !== false
            && strpos($privacyOrchestrationHelper, "sr_enabled_module_contract_files(\$pdo, 'privacy-export.php', ['privacy'])") !== false
            && strpos($privacyOrchestrationHelper, 'sr_load_module_contract_file($moduleKey, $exportFile)') !== false
            && strpos($privacyOrchestrationHelper, 'function sr_privacy_export_internal_key') !== false
            && strpos($privacyOrchestrationHelper, '$moduleExportData = $moduleExport($pdo, $accountId)') !== false
            && strpos($privacyOrchestrationHelper, 'if (is_array($moduleExportData))') !== false
            && strpos($privacyOrchestrationHelper, 'sr_privacy_export_sanitize_module_data($moduleExportData)') !== false
            && strpos($privacyOrchestrationHelper, 'catch (Throwable $exception)') !== false
            && strpos($privacyOrchestrationHelper, "sr_log_exception(\$exception, 'privacy_export_module_' . \$moduleKey)") !== false
            && strpos($privacyOrchestrationHelper, 'password|token|secret|credential|bearer|authorization') !== false
            && strpos($privacyOrchestrationHelper, "str_ends_with(\$normalizedKey, '_token_hash')") !== false
            && strpos($privacyOrchestrationHelper, "str_ends_with(\$normalizedKey, '_hash')") !== false,
        'Privacy helper should isolate module privacy export failures and remove internal hash/token/secret fields.'
    );
}

$accountView = sr_member_auth_policy_read('modules/member/views/account.php');
if ($accountView !== '') {
    sr_member_auth_policy_assert(
        strpos($accountView, 'action="<?php echo sr_e(sr_url(\'/account/privacy-export\')); ?>"') !== false
            && strpos($accountView, 'name="current_password"') !== false
            && strpos($accountView, 'autocomplete="current-password" required') !== false,
        'Account view privacy export form should ask for the current password.'
    );
}

$privacyHelper = sr_member_auth_policy_read('modules/member/helpers/privacy.php');
if ($privacyHelper !== '') {
    sr_member_auth_policy_assert(
        strpos($privacyHelper, 'function sr_member_record_consent_withdrawals') !== false
            && strpos($privacyHelper, 'sr_member_latest_consents($pdo, $accountId)') !== false
            && strpos($privacyHelper, 'false') !== false,
        'Privacy helper should record false consent history rows for withdrawn latest consents.'
    );
    sr_member_auth_policy_assert(
        strpos($privacyHelper, 'function sr_member_privacy_request_list_preview') !== false
            && strpos($privacyHelper, 'sr_log_line_value((string) $value, $maxLength + 1)') !== false
            && strpos($privacyHelper, "return mb_substr(\$preview, 0, \$maxLength) . '...';") !== false,
        'Privacy helper should provide a bounded privacy request list preview.'
    );
}

$privacyRequestsAction = sr_member_auth_policy_read('modules/privacy/actions/account-privacy-requests.php');
if ($privacyRequestsAction !== '') {
    sr_member_auth_policy_assert(
        strpos($privacyRequestsAction, "if (sr_request_method() === 'POST')") !== false
            && strpos($privacyRequestsAction, 'sr_require_csrf();') !== false
            && strpos($privacyRequestsAction, "sr_render_error(405, sr_t('privacy::action.error.method_not_allowed'))") !== false
            && strpos($privacyRequestsAction, 'INSERT INTO sr_privacy_requests') === false,
        'Member-facing privacy request action should be guidance-only and must not create ticket rows.'
    );
}

$privacyRequestsView = sr_member_auth_policy_read('modules/privacy/views/account-privacy-requests.php');
if ($privacyRequestsView !== '') {
    sr_member_auth_policy_assert(
        strpos($privacyRequestsView, "sr_t('privacy::ui.privacy.guidance.body.1')") !== false
            && strpos($privacyRequestsView, "sr_t('privacy::ui.privacy.guidance.body.2')") !== false
            && strpos($privacyRequestsView, '<form method="post" action="<?php echo sr_e(sr_url(\'/account/privacy-requests\')); ?>">') === false,
        'Member-facing privacy request view should show guidance without a ticket submission form.'
    );
}

$adminPrivacyRequestsAction = sr_member_auth_policy_read('modules/privacy/actions/admin-privacy-requests.php');
if ($adminPrivacyRequestsAction !== '') {
    sr_member_auth_policy_assert(
        strpos($adminPrivacyRequestsAction, "if (sr_request_method() === 'GET')") !== false
            && strpos($adminPrivacyRequestsAction, "'event_type' => 'privacy.request.list.viewed'") !== false
            && strpos($adminPrivacyRequestsAction, "'filters' => \$privacyRequestListFilters") !== false
            && strpos($adminPrivacyRequestsAction, "'result_count' => count(\$requests)") !== false,
        'Admin privacy request list views should be audited without logging raw request contents.'
    );
    sr_member_auth_policy_assert(
        strpos($adminPrivacyRequestsAction, "sr_post_string('intent', 40)") !== false
            && strpos($adminPrivacyRequestsAction, "\$intent === 'create_request'") !== false
            && strpos($adminPrivacyRequestsAction, 'sr_admin_handle_privacy_request_create_post($pdo, $account, $allowedTypes)') !== false,
        'Admin privacy request action should keep manual response record creation admin-only.'
    );
}

$adminPrivacyRequestsHelper = sr_member_auth_policy_read('modules/privacy/helpers/requests.php');
if ($adminPrivacyRequestsHelper !== '') {
    sr_member_auth_policy_assert(
        strpos($adminPrivacyRequestsHelper, 'function sr_admin_privacy_request_terminal_statuses') !== false
            && strpos($adminPrivacyRequestsHelper, "in_array((string) \$privacyRequest['status'], sr_admin_privacy_request_terminal_statuses(), true)") !== false
            && strpos($adminPrivacyRequestsHelper, '종결된 개인정보 처리 요청 상태는 다시 변경할 수 없습니다.') !== false,
        'Admin privacy request helper should prevent reopening terminal privacy request statuses.'
    );
    sr_member_auth_policy_assert(
        strpos($adminPrivacyRequestsHelper, 'SELECT id, status, admin_note, handled_by_account_id, handled_at FROM sr_privacy_requests WHERE id = :id LIMIT 1') !== false
            && strpos($adminPrivacyRequestsHelper, "\$nextAdminNote = \$adminNote !== '' ? \$adminNote : \$storedAdminNote;") !== false
            && strpos($adminPrivacyRequestsHelper, "'admin_note' => \$nextAdminNote") !== false,
        'Admin privacy request helper should preserve stored admin notes when list forms submit no replacement note.'
    );
    sr_member_auth_policy_assert(
        strpos($adminPrivacyRequestsHelper, '$preserveTerminalHandler = !$statusChanged && $isTerminalStatus;') !== false
            && strpos($adminPrivacyRequestsHelper, "'handled_by_account_id' => \$handledByAccountId") !== false
            && strpos($adminPrivacyRequestsHelper, "'handled_at' => \$handledAt") !== false,
        'Admin privacy request helper should preserve terminal handler and handled time when only terminal notes are updated.'
    );
    sr_member_auth_policy_assert(
        strpos($adminPrivacyRequestsHelper, "sr_post_string_without_truncation('status', 30)") !== false
            && strpos($adminPrivacyRequestsHelper, "sr_post_string_without_truncation('admin_note', 2000)") !== false
            && strpos($adminPrivacyRequestsHelper, '$adminNote === null') !== false,
        'Admin privacy request helper should reject overlong raw status/admin note inputs instead of truncating them.'
    );
    sr_member_auth_policy_assert(
        strpos($adminPrivacyRequestsHelper, 'function sr_admin_handle_privacy_request_create_post') !== false
            && strpos($adminPrivacyRequestsHelper, "sr_admin_post_positive_int('account_id')") !== false
            && strpos($adminPrivacyRequestsHelper, "sr_post_string_without_truncation('request_message', 2000)") !== false
            && strpos($adminPrivacyRequestsHelper, '계정 ID 또는 요청자 중 하나를 입력하세요.') !== false
            && strpos($adminPrivacyRequestsHelper, "sr_hmac_hash(sr_normalize_identifier(\$requesterSnapshot), sr_runtime_config())") !== false
            && strpos($adminPrivacyRequestsHelper, "'source' => 'admin_manual'") !== false,
        'Admin privacy request helper should create minimal manual response records without member-facing ticket intake.'
    );
    sr_member_auth_policy_assert(
        strpos($adminPrivacyRequestsHelper, 'function sr_admin_privacy_request_export_reauth_errors') !== false
            && strpos($adminPrivacyRequestsHelper, "sr_post_string('admin_password', 255)") !== false
            && strpos($adminPrivacyRequestsHelper, 'sr_member_reauth_throttle_status($pdo, $accountId)') !== false
            && strpos($adminPrivacyRequestsHelper, 'privacy_request_export_reauth') !== false
            && strpos($adminPrivacyRequestsHelper, 'privacy.request.export_reauth_failed') !== false,
        'Admin privacy request export should require throttled current-admin reauthentication.'
    );
    sr_member_auth_policy_assert(
        strpos($adminPrivacyRequestsHelper, 'sr_privacy_export_data($pdo, (int) $privacyRequest[\'account_id\'])') !== false
            && strpos($adminPrivacyRequestsHelper, 'catch (Throwable $exception)') !== false
            && strpos($adminPrivacyRequestsHelper, "sr_log_exception(\$exception, 'privacy_request_export_account_' . (int) \$privacyRequest['id'])") !== false
            && strpos($adminPrivacyRequestsHelper, "\$export['account_data_unavailable'] = true") !== false,
        'Admin privacy request export should isolate linked account export failures.'
    );
}

$adminPrivacyRequestsView = sr_member_auth_policy_read('modules/privacy/views/admin-privacy-requests.php');
if ($adminPrivacyRequestsView !== '') {
    sr_member_auth_policy_assert(
        strpos($adminPrivacyRequestsView, "placeholder=\"<?php echo sr_e(sr_t('privacy::ui.admin.79636dee')); ?>\"") !== false
            && strpos($adminPrivacyRequestsView, "><?php echo sr_e((string) (\$request['admin_note'] ?? '')); ?></textarea>") === false
            && strpos($adminPrivacyRequestsView, "><?php echo sr_e(\$request['admin_note'] ?? ''); ?></textarea>") === false,
        'Admin privacy request view should not prefill stored admin notes in list forms.'
    );
    sr_member_auth_policy_assert(
        strpos($adminPrivacyRequestsView, 'name="intent" value="create_request"') !== false
            && strpos($adminPrivacyRequestsView, 'name="account_id"') !== false
            && strpos($adminPrivacyRequestsView, 'name="requester_snapshot"') !== false
            && strpos($adminPrivacyRequestsView, 'name="request_message"') !== false
            && strpos($adminPrivacyRequestsView, '외부 문의로 접수한 요청 취지와 확인해야 할 범위만 적으세요.') !== false,
        'Admin privacy request view should provide a minimal manual record form for external contact cases.'
    );
    sr_member_auth_policy_assert(
        strpos($adminPrivacyRequestsView, 'name="admin_password"') !== false
            && strpos($adminPrivacyRequestsView, 'autocomplete="current-password" required') !== false,
        'Admin privacy request export form should ask for current admin password.'
    );
}

$adminPrivacyRequestExportAction = sr_member_auth_policy_read('modules/privacy/actions/admin-privacy-request-export.php');
if ($adminPrivacyRequestExportAction !== '') {
    sr_member_auth_policy_assert(
        strpos($adminPrivacyRequestExportAction, 'sr_admin_privacy_request_export_reauth_errors($pdo, $account, $requestId)') !== false
            && strpos($adminPrivacyRequestExportAction, 'sr_render_error(403, $reauthError)') !== false,
        'Admin privacy request export action should enforce reauthentication before generating JSON.'
    );
    sr_member_auth_policy_assert(
        strpos($adminPrivacyRequestExportAction, 'JSON_INVALID_UTF8_SUBSTITUTE') !== false
            && strpos($adminPrivacyRequestExportAction, '$encodedExport = json_encode($export') !== false
            && strpos($adminPrivacyRequestExportAction, 'if (!is_string($encodedExport))') !== false
            && strpos($adminPrivacyRequestExportAction, 'echo $encodedExport;') !== false,
        'Admin privacy request export action should encode JSON safely before sending download headers.'
    );
}

$registerAction = sr_member_auth_policy_read('modules/member/actions/register.php');
if ($registerAction !== '') {
    sr_member_auth_policy_assert(
        strpos($registerAction, "sr_post_string_without_truncation('email', 255)") !== false
            && strpos($registerAction, '$email === null') !== false,
        'Register action should reject overlong raw email inputs instead of truncating them.'
    );
    sr_member_auth_policy_assert(
        strpos($registerAction, "sr_post_string_without_truncation('login_id', 40)") !== false
            && strpos($registerAction, '$loginId === null') !== false,
        'Register action should reject overlong raw login_id inputs instead of truncating them.'
    );
    sr_member_auth_policy_assert(
        strpos($registerAction, "sr_post_string_without_truncation('password', 255)") !== false
            && strpos($registerAction, "sr_post_string_without_truncation('password_confirm', 255)") !== false
            && strpos($registerAction, '$password === null || $passwordConfirm === null') !== false,
        'Register action should reject overlong raw password inputs instead of truncating them.'
    );
    sr_member_auth_policy_assert(
        strpos($registerAction, 'sr_member_login($pdo, $newAccount)') !== false
            && (
                strpos($registerAction, "sr_t('member::action.register.login_session_failed_notice')") !== false
                || strpos($registerAction, '로그인 세션을 만들 수 없습니다') !== false
                || strpos($memberLang, '로그인 세션을 만들 수 없습니다') !== false
            ),
        'Register action should keep auto-login for immediately verified accounts.'
    );
    sr_member_auth_policy_assert(
        strpos($registerAction, "sr_redirect('/login')") !== false
            && (
                strpos($registerAction, "sr_t('member::action.register.email_verification_notice')") !== false
                || strpos($registerAction, '이메일 인증을 완료한 뒤 로그인하세요') !== false
                || strpos($memberLang, '이메일 인증을 완료한 뒤 로그인하세요') !== false
            ),
        'Register action should not auto-login unverified accounts.'
    );
    sr_member_auth_policy_assert(
        strpos($registerAction, '$verificationMailSent = null') !== false
            && strpos($registerAction, "'email_verification_mail_sent' => \$verificationMailSent") !== false,
        'Register action should audit email verification mail delivery result without storing token values.'
    );
    sr_member_auth_policy_assert(
        strpos($registerAction, 'email_verification_mail_failed') !== false,
        'Register action should write an auth log event when verification mail delivery fails.'
    );
    sr_member_auth_policy_assert(
        strpos($registerAction, '$showVerificationUrl = !empty($config[\'debug\']) && sr_is_local_host((string) ($site[\'base_url\'] ?? \'\'));') !== false
            && strpos($registerAction, 'if ($showVerificationUrl)') !== false,
        'Register action should only store debug email verification URLs when the configured site base URL is localhost.'
    );
    sr_member_auth_policy_assert(
        strpos($registerAction, "unset(\$_SESSION['sr_debug_email_verification_url']);") !== false,
        'Register action should clear stale debug email verification URLs outside localhost debug mode.'
    );
    $registerTransaction = strpos($registerAction, '$pdo->beginTransaction();');
    $registerConsent = strpos($registerAction, "sr_member_record_consent(\$pdo, \$accountId, 'privacy'");
    $registerCommit = strpos($registerAction, '$pdo->commit();');
    $registerMail = strpos($registerAction, '$verificationMailSent = sr_send_mail');
    sr_member_auth_policy_assert(
        $registerTransaction !== false
            && $registerConsent !== false
            && $registerCommit !== false
            && $registerTransaction < $registerConsent
            && $registerConsent < $registerCommit,
        'Register action should create account, verification token, and required consents in one transaction.'
    );
    sr_member_auth_policy_assert(
        $registerCommit !== false
            && $registerMail !== false
            && $registerCommit < $registerMail,
        'Register action should send email only after the account transaction commits.'
    );
    sr_member_auth_policy_assert(
        strpos($registerAction, "\$marketingConsent = (\$_POST['marketing_consent'] ?? '') === '1';") !== false
            && strpos($registerAction, "sr_member_record_consent(\$pdo, \$accountId, 'marketing', (string) \$transactionPolicyDocuments['marketing']['version_key'], \$marketingConsent, \$transactionPolicyDocuments['marketing'])") !== false,
        'Register action should record optional marketing consent history.'
    );
    sr_member_auth_policy_assert(
        strpos($registerAction, 'sr_member_registration_policy_documents($pdo)') !== false
            && strpos($registerAction, '$transactionPolicyDocumentState = sr_member_registration_policy_documents($pdo);') !== false,
        'Register action should load current policy documents from the server for display and again inside the account transaction.'
    );
    sr_member_auth_policy_assert(
        strpos($registerAction, "'login_id' => sr_member_normalize_login_id(\$loginId)") !== false
            && strpos($registerAction, "sr_member_is_valid_login_id(\$values['login_id'])") !== false
            && strpos($registerAction, "'login_id' => \$values['login_id']") !== false,
        'Register action should collect optional login_id and save it when provided.'
    );
}

$registerView = sr_member_auth_policy_read('modules/member/views/register.php');
if ($registerView !== '') {
    sr_member_auth_policy_assert(
        strpos($registerView, 'name="login_id"') !== false
            && (
                strpos($registerView, "sr_t('member::ui.email.login.email.active.eb627985')") !== false
                || strpos($registerView, '비워두면 이메일로 로그인하고, 입력하면 이메일과 아이디를 모두 사용할 수 있습니다.') !== false
                || strpos($memberLang, '비워두면 이메일로 로그인하고, 입력하면 이메일과 아이디를 모두 사용할 수 있습니다.') !== false
            ),
        'Register view should render optional login_id input for email and login_id parallel login.'
    );
    sr_member_auth_policy_assert(
        strpos($registerView, 'name="marketing_consent"') !== false
            && strpos($registerView, '$marketingConsent ? \' checked\' : \'\'') !== false,
        'Register view should render optional marketing consent and preserve submitted state.'
    );
}

$adminSettingsAction = sr_member_auth_policy_read('modules/member/actions/admin-settings.php');
if ($adminSettingsAction !== '') {
    sr_member_auth_policy_assert(
        strpos($adminSettingsAction, "sr_post_string('login_identifier', 20)") === false
            && strpos($adminSettingsAction, "['login_identifier', (string) \$settings['login_identifier'], 'string']") === false
            && strpos($adminSettingsAction, "'login_identifier' => (string) \$settings['login_identifier']") !== false,
        'Member settings action should keep login_identifier as a fixed normalized policy instead of saving a selectable value.'
    );
    sr_member_auth_policy_assert(
        strpos($adminSettingsAction, 'sr_member_profile_field_definitions()') !== false
            && strpos($adminSettingsAction, "\$settings[\$requiredKey] = (\$_POST[\$requiredKey] ?? '') === '1';") !== false
            && strpos($adminSettingsAction, "'profile_fields' => sr_member_profile_field_policies(\$settings)") !== false,
        'Member settings action should save optional profile visibility/required settings and audit them.'
    );
    sr_member_auth_policy_assert(
        strpos($adminSettingsAction, 'sr_admin_post_int_in_range($key, (int) $limits[\'min\'], (int) $limits[\'max\'])') !== false
            && strpos($adminSettingsAction, '$integerValue === null') !== false
            && strpos($adminSettingsAction, '$settings[$key] = $integerValue;') !== false
            && strpos($adminSettingsAction, 'sr_member_clamp_int((int) $rawValue') === false,
        'Member settings action should reject out-of-range integer settings instead of truncating or clamping submitted values.'
    );
}

$adminSettingsView = sr_member_auth_policy_read('modules/member/views/admin-settings.php');
if ($adminSettingsView !== '') {
    sr_member_auth_policy_assert(
        strpos($adminSettingsView, 'name="login_identifier"') === false
            && (
                strpos($adminSettingsView, "sr_t('member::ui.email.login.login.login.44f3662f')") !== false
                || strpos($adminSettingsView, '이메일 로그인은 항상 허용하고, 로그인 아이디를 입력한 계정은 아이디로도 로그인할 수 있습니다.') !== false
                || strpos($memberLang, '이메일 로그인은 항상 허용하고, 로그인 아이디를 입력한 계정은 아이디로도 로그인할 수 있습니다.') !== false
            ),
        'Member settings view should show the fixed email and login_id parallel login policy without a selector.'
    );
    sr_member_auth_policy_assert(
        (
            strpos($adminSettingsView, "sr_t('member::ui.select.da5d4203')") !== false
            || strpos($adminSettingsView, '선택 프로필 항목') !== false
            || strpos($memberLang, '선택 프로필 항목') !== false
        )
            && strpos($adminSettingsView, 'sr_member_profile_field_definitions()') !== false
            && strpos($adminSettingsView, '$enabledKey') !== false
            && strpos($adminSettingsView, '$requiredKey') !== false,
        'Member settings view should expose optional profile visibility and required field settings.'
    );
}

$accountView = sr_member_auth_policy_read('modules/member/views/account.php');
if ($accountView !== '') {
    sr_member_auth_policy_assert(
        strpos($accountView, 'if ($profileFieldsEnabled)') !== false
            && strpos($accountView, "if (!empty(\$profilePolicies['phone']['visible']))") !== false
            && strpos($accountView, "if (!empty(\$profilePolicies['birth_date']['visible']))") !== false
            && strpos($accountView, "if (!empty(\$profilePolicies['avatar_path']['visible']))") !== false
            && strpos($accountView, "if (!empty(\$profilePolicies['profile_text']['visible']))") !== false
            && strpos($accountView, 'name="avatar_file"') !== false,
        'Account view should render only visible optional profile fields and use file upload for avatar.'
    );
}

$emailVerificationRequestAction = sr_member_auth_policy_read('modules/member/actions/email-verification-request.php');
if ($emailVerificationRequestAction !== '') {
    sr_member_auth_policy_assert(
        strpos($emailVerificationRequestAction, "'mail_sent' => \$mailSent") !== false,
        'Email verification request action should audit mail delivery result.'
    );
    sr_member_auth_policy_assert(
        strpos($emailVerificationRequestAction, 'email_verification_mail_failed') !== false,
        'Email verification request action should write an auth log event when mail delivery fails.'
    );
    sr_member_auth_policy_assert(
        strpos($emailVerificationRequestAction, '$showVerificationUrl = !empty($config[\'debug\']) && sr_is_local_host((string) ($site[\'base_url\'] ?? \'\'));') !== false
            && strpos($emailVerificationRequestAction, 'if ($showVerificationUrl)') !== false,
        'Email verification request action should only store debug verification URLs when the configured site base URL is localhost.'
    );
    sr_member_auth_policy_assert(
        strpos($emailVerificationRequestAction, "unset(\$_SESSION['sr_debug_email_verification_url']);") !== false,
        'Email verification request action should clear stale debug verification URLs outside localhost debug mode.'
    );
}

$loginAction = sr_member_auth_policy_read('modules/member/actions/login.php');
if ($loginAction !== '') {
    sr_member_auth_policy_assert(
        strpos($loginAction, 'sr_member_rehash_login_password_if_needed') !== false,
        'Login action should rehash stale password hashes after successful verification.'
    );
    sr_member_auth_policy_assert(
        strpos($loginAction, 'if (sr_member_login($pdo, $account))') !== false
            && strpos($loginAction, 'login_session_failed') !== false,
        'Login action should not record login success when member session creation fails.'
    );
}

$paths = sr_member_auth_policy_read('modules/member/paths.php');
if ($paths !== '') {
    sr_member_auth_policy_assert(
        strpos($paths, "'GET /email/verified' => 'actions/email-verified.php'") !== false,
        'Email verification success route should be tokenless.'
    );
}

$emailVerifyAction = sr_member_auth_policy_read('modules/member/actions/email-verify.php');
if ($emailVerifyAction !== '') {
    sr_member_auth_policy_assert(
        strpos($emailVerifyAction, "sr_get_string_without_truncation('token', 64)") !== false
            && strpos($emailVerifyAction, '$token === null') !== false,
        'Email verification action should reject overlong raw token inputs instead of truncating them.'
    );
    sr_member_auth_policy_assert(
        strpos($emailVerifyAction, "sr_redirect('/email/verified')") !== false,
        'Email verification action should redirect to a tokenless success page.'
    );
}

$passwordResetAction = sr_member_auth_policy_read('modules/member/actions/password-reset.php');
if ($passwordResetAction !== '') {
    sr_member_auth_policy_assert(
        strpos($passwordResetAction, "sr_get_string_without_truncation('token', 64)") !== false
            && strpos($passwordResetAction, '$tokenInputInvalid') !== false,
        'Password reset confirm action should reject overlong raw token inputs instead of truncating them.'
    );
    sr_member_auth_policy_assert(
        strpos($passwordResetAction, "sr_post_string_without_truncation('password', 255)") !== false
            && strpos($passwordResetAction, "sr_post_string_without_truncation('password_confirm', 255)") !== false
            && strpos($passwordResetAction, '$password === null || $passwordConfirm === null') !== false,
        'Password reset should reject overlong raw password inputs instead of truncating them.'
    );
    sr_member_auth_policy_assert(
        strpos($passwordResetAction, 'sr_member_store_password_reset_session_hash') !== false,
        'Password reset confirm action should keep only the reset token hash in session after initial validation.'
    );
    sr_member_auth_policy_assert(
        strpos($passwordResetAction, 'sr_member_password_reset_session_hash($resetTokenSessionSeconds)') !== false,
        'Password reset confirm action should enforce a short session hash lifetime.'
    );
    sr_member_auth_policy_assert(
        strpos($passwordResetAction, "sr_redirect('/password/reset/confirm')") !== false,
        'Password reset confirm action should redirect token query URLs to a tokenless form URL.'
    );
    sr_member_auth_policy_assert(
        strpos($passwordResetAction, 'sr_member_logout_current_session_if_account($pdo, (int) $reset[\'account_id\'])') !== false,
        'Password reset completion should immediately clear the current PHP session for the reset account.'
    );
    sr_member_auth_policy_assert(
        strpos($passwordResetAction, '$loggedOutCurrentSession = sr_member_logout_current_session_if_account($pdo, (int) $reset[\'account_id\'])') !== false
            && strpos($passwordResetAction, "'current_session_logout_required' => \$shouldLogoutCurrentSession") !== false
            && strpos($passwordResetAction, "'logged_out_current_session' => \$loggedOutCurrentSession") !== false,
        'Password reset audit metadata should record the actual current-session logout result.'
    );
    sr_member_auth_policy_assert(
        strpos($passwordResetAction, 'if ($revokedSessions < 0)') !== false
            && strpos($passwordResetAction, 'Member sessions could not be revoked after password reset.') !== false,
        'Password reset should not complete when account sessions cannot be revoked.'
    );
    sr_member_auth_policy_assert(
        strpos($passwordResetAction, "sr_redirect('/login?password_reset=1')") !== false,
        'Password reset completion should redirect to login instead of rendering another reset form after session cleanup.'
    );
}

$passwordResetRequestAction = sr_member_auth_policy_read('modules/member/actions/password-reset-request.php');
if ($passwordResetRequestAction !== '') {
    sr_member_auth_policy_assert(
        strpos($passwordResetRequestAction, "sr_post_string_without_truncation('email', 255)") !== false
            && strpos($passwordResetRequestAction, '$email === null') !== false,
        'Password reset request action should reject overlong raw email inputs instead of truncating them.'
    );
    sr_member_auth_policy_assert(
        strpos($passwordResetRequestAction, '$mailSent = sr_send_mail') !== false
            && strpos($passwordResetRequestAction, "'mail_sent' => \$mailSent") !== false,
        'Password reset request action should audit reset mail delivery result.'
    );
    sr_member_auth_policy_assert(
        strpos($passwordResetRequestAction, 'password_reset_mail_failed') !== false,
        'Password reset request action should write an auth log event when reset mail delivery fails.'
    );
    sr_member_auth_policy_assert(
        strpos($passwordResetRequestAction, '$showResetUrl = false;') !== false
            && strpos($passwordResetRequestAction, '$showResetUrl = !empty($config[\'debug\']) && sr_is_local_host((string) ($site[\'base_url\'] ?? \'\'));') !== false,
        'Password reset request action should only expose debug reset URLs when the configured site base URL is localhost.'
    );
}

$tokenHelper = sr_member_auth_policy_read('modules/member/helpers/tokens.php');
if ($tokenHelper !== '') {
    sr_member_auth_policy_assert(
        strpos($tokenHelper, 'function sr_member_password_reset_token_hash') !== false,
        'Password reset token hash helper is missing.'
    );
    sr_member_auth_policy_assert(
        strpos($tokenHelper, 'function sr_member_password_reset_session_hash') !== false,
        'Password reset session hash helper is missing.'
    );
    sr_member_auth_policy_assert(
        strpos($tokenHelper, 'a.email AS account_email') !== false
            && strpos($tokenHelper, "sr_normalize_identifier((string) \$verification['email']) !== sr_normalize_identifier((string) \$verification['account_email'])") !== false
            && strpos($tokenHelper, 'AND email = :email') !== false,
        'Email verification should only verify the current account email that matches the issued token.'
    );
    sr_member_auth_policy_assert(
        strpos($tokenHelper, 'sr_password_reset_token_hash') !== false
            && strpos($tokenHelper, 'sr_password_reset_token_stored_at') !== false,
        'Password reset session should store hash and stored_at only.'
    );
    sr_member_auth_policy_assert(
        strpos($tokenHelper, 'sr_password_reset_token\'') === false
            && strpos($tokenHelper, 'sr_password_reset_token"') === false,
        'Password reset session should not store the raw reset token.'
    );
}

$passwordResetView = sr_member_auth_policy_read('modules/member/views/password-reset.php');
if ($passwordResetView !== '') {
    sr_member_auth_policy_assert(
        strpos($passwordResetView, 'name="token"') === false,
        'Password reset form should not render the reset token into HTML.'
    );
}

$passwordResetRequestView = sr_member_auth_policy_read('modules/member/views/password-reset-request.php');
if ($passwordResetRequestView !== '') {
    sr_member_auth_policy_assert(
        strpos($passwordResetRequestView, '$resetUrl !== \'\' && $showResetUrl') !== false
            && strpos($passwordResetRequestView, '!empty($config[\'debug\'])') === false,
        'Password reset request view should not decide public token exposure directly from debug config.'
    );
}

if ($errors !== []) {
    fwrite(STDERR, "member auth policy checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "member auth policy checks completed.\n";
