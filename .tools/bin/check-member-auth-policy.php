#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('TOY_ROOT', $root);

require_once $root . '/modules/member/helpers/accounts.php';
require_once $root . '/modules/member/helpers/tokens.php';
require_once $root . '/modules/member/helpers/throttle.php';

$errors = [];

function toy_member_auth_policy_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function toy_member_auth_policy_assert(bool $condition, string $message): void
{
    if (!$condition) {
        toy_member_auth_policy_error($message);
    }
}

function toy_member_auth_policy_read(string $path): string
{
    global $root;

    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content)) {
        toy_member_auth_policy_error('Cannot read file: ' . $path);
        return '';
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
}

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

toy_member_auth_policy_assert(
    toy_member_email_verification_blocks_login(['email_verification_enabled' => true], $unverifiedAccount),
    'Email verification should block active unverified accounts when enabled.'
);
toy_member_auth_policy_assert(
    !toy_member_email_verification_blocks_login(['email_verification_enabled' => false], $unverifiedAccount),
    'Email verification should not block login when disabled.'
);
toy_member_auth_policy_assert(
    !toy_member_email_verification_blocks_login(['email_verification_enabled' => true], $verifiedAccount),
    'Verified account should not be blocked by email verification policy.'
);
toy_member_auth_policy_assert(
    !toy_member_email_verification_blocks_login(['email_verification_enabled' => true], null),
    'Missing account should not be treated as email verification block.'
);

$_SESSION = [];
$sampleTokenHash = str_repeat('a', 64);
toy_member_store_password_reset_session_hash($sampleTokenHash);
toy_member_auth_policy_assert(
    toy_member_password_reset_session_hash(900) === $sampleTokenHash,
    'Password reset session hash should be readable within its lifetime.'
);
$_SESSION['toy_password_reset_token_stored_at'] = (string) (time() - 901);
toy_member_auth_policy_assert(
    toy_member_password_reset_session_hash(900) === '',
    'Password reset session hash should expire after its short lifetime.'
);
toy_member_auth_policy_assert(
    !isset($_SESSION['toy_password_reset_token_hash'], $_SESSION['toy_password_reset_token_stored_at']),
    'Expired password reset session hash should be cleared.'
);
toy_member_auth_policy_assert(
    in_array('login_email_unverified', toy_member_login_failure_event_types(), true),
    'Unverified email login blocks should count as login failure throttle events.'
);
toy_member_auth_policy_assert(
    in_array('login_session_failed', toy_member_login_failure_event_types(), true),
    'Login session creation failures should count as login failure throttle events.'
);
toy_member_auth_policy_assert(
    in_array('password_change_reauth', toy_member_reauth_failure_event_types(), true)
        && in_array('password_change_session_failed', toy_member_reauth_failure_event_types(), true)
        && in_array('withdraw_reauth', toy_member_reauth_failure_event_types(), true)
        && in_array('privacy_export_reauth', toy_member_reauth_failure_event_types(), true)
        && in_array('module_setting_reauth', toy_member_reauth_failure_event_types(), true)
        && in_array('privacy_request_export_reauth', toy_member_reauth_failure_event_types(), true)
        && in_array('reauth_blocked', toy_member_reauth_failure_event_types(), true),
    'Sensitive reauth failures should count as reauth throttle events.'
);

$loginAction = toy_member_auth_policy_read('modules/member/actions/login.php');
if ($loginAction !== '') {
    toy_member_auth_policy_assert(
        strpos($loginAction, "toy_post_string_without_truncation('identifier', 255)") !== false
            && strpos($loginAction, '$identifier === null') !== false,
        'Login action should reject overlong raw identifiers instead of truncating them for account lookup.'
    );
    toy_member_auth_policy_assert(
        strpos($loginAction, 'toy_member_email_verification_blocks_login') !== false,
        'Login action should enforce email verification policy.'
    );
    toy_member_auth_policy_assert(
        strpos($loginAction, 'toy_member_email_verification_throttle_status($pdo, (int) $account[\'id\'])') !== false
            && strpos($loginAction, 'toy_member_create_email_verification($pdo, $config, (int) $account[\'id\'], (string) $account[\'email\'])') !== false,
        'Login action should resend email verification within throttle limits after a valid password for an unverified account.'
    );
    toy_member_auth_policy_assert(
        strpos($loginAction, 'login_email_unverified') !== false,
        'Login action should log unverified email login blocks.'
    );
    toy_member_auth_policy_assert(
        strpos($loginAction, "'mail_sent' => \$mailSent") !== false,
        'Login action should audit email verification resend mail delivery result.'
    );
    toy_member_auth_policy_assert(
        strpos($loginAction, 'email_verification_mail_failed') !== false,
        'Login action should write an auth log event when verification mail delivery fails.'
    );
    toy_member_auth_policy_assert(
        strpos($loginAction, '$showVerificationUrl = !empty($config[\'debug\']) && toy_is_local_host((string) ($site[\'base_url\'] ?? \'\'));') !== false
            && strpos($loginAction, 'if ($showVerificationUrl)') !== false,
        'Login action should only store debug email verification URLs when the configured site base URL is localhost.'
    );
    toy_member_auth_policy_assert(
        strpos($loginAction, "unset(\$_SESSION['toy_debug_email_verification_url']);") !== false,
        'Login action should clear stale debug email verification URLs outside localhost debug mode.'
    );
    toy_member_auth_policy_assert(
        strpos($loginAction, "toy_get_string('password_reset', 10) === '1'") !== false
            && strpos($loginAction, '비밀번호를 재설정했습니다. 새 비밀번호로 로그인하세요.') !== false,
        'Login action should show a fixed completion notice after password reset redirect.'
    );
}

$accountHelper = toy_member_auth_policy_read('modules/member/helpers/accounts.php');
if ($accountHelper !== '') {
    toy_member_auth_policy_assert(
        strpos($accountHelper, 'toy_member_email_verification_blocks_login($settings, $account)') !== false,
        'Current member session should be rejected when email verification is still required.'
    );
    toy_member_auth_policy_assert(
        strpos($accountHelper, "if (!array_key_exists('toy_account_id', \$_SESSION)) {\n        toy_member_revoke_current_session(\$pdo);\n        unset(\$_SESSION['toy_session_token_hash']);\n        return null;\n    }") !== false
            && strpos($accountHelper, "if (!is_int(\$accountId) && !ctype_digit((string) \$accountId)) {\n        toy_member_logout(\$pdo);\n        return null;\n    }") !== false
            && strpos($accountHelper, "if (\$accountId < 1) {\n        toy_member_logout(\$pdo);\n        return null;\n    }") !== false
            && strpos($accountHelper, "if (!is_array(\$account)) {\n        toy_member_logout(\$pdo);\n        return null;\n    }") !== false,
        'Current member account lookup should clear PHP session state when the session account is invalid or missing.'
    );
    toy_member_auth_policy_assert(
        strpos($accountHelper, 'function toy_member_rehash_login_password_if_needed') !== false
            && strpos($accountHelper, 'password_needs_rehash($currentHash, PASSWORD_DEFAULT)') !== false,
        'Login password rehash helper should upgrade stale password hashes.'
    );
    toy_member_auth_policy_assert(
        strpos($accountHelper, 'function toy_member_public_account_summary(PDO $pdo, int $accountId): ?array') !== false
            && strpos($accountHelper, 'SELECT id, display_name, locale, status') !== false
            && strpos($accountHelper, "'display_name' => (string) \$account['display_name']") !== false
            && strpos($accountHelper, "'locale' => (string) \$account['locale']") !== false
            && strpos($accountHelper, "'status' => (string) \$account['status']") !== false,
        'Public account summary helper should expose only non-sensitive account summary fields.'
    );
    toy_member_auth_policy_assert(
        strpos($accountHelper, 'email_hash = :email_hash') !== false
            && strpos($accountHelper, 'email_hash_guard') !== false,
        'Login identifier lookup should allow email fallback when login_id mode is used.'
    );
}

$throttleHelper = toy_member_auth_policy_read('modules/member/helpers/throttle.php');
if ($throttleHelper !== '') {
    toy_member_auth_policy_assert(
        strpos($throttleHelper, 'toy_member_login_failure_event_types()') !== false,
        'Login throttle should use the shared login failure event list.'
    );
    toy_member_auth_policy_assert(
        strpos($throttleHelper, 'function toy_member_reauth_throttle_status') !== false
            && strpos($throttleHelper, 'member.reauth.account') !== false
            && strpos($throttleHelper, 'member.reauth.ip') !== false,
        'Sensitive reauth throttle should track account and IP failures.'
    );
}

$settingsHelper = toy_member_auth_policy_read('modules/member/helpers/settings.php');
if ($settingsHelper !== '') {
    toy_member_auth_policy_assert(
        strpos($settingsHelper, 'function toy_member_profile_field_setting_keys') !== false
            && strpos($settingsHelper, 'profile_nickname_enabled') !== false
            && strpos($settingsHelper, 'profile_phone_enabled') !== false
            && strpos($settingsHelper, 'profile_birth_date_enabled') !== false
            && strpos($settingsHelper, 'profile_avatar_enabled') !== false
            && strpos($settingsHelper, 'profile_text_enabled') !== false,
        'Member settings helper should define configurable optional profile fields.'
    );
    toy_member_auth_policy_assert(
        strpos($settingsHelper, 'function toy_member_profile_field_settings') !== false
            && strpos($settingsHelper, "'nickname' => !empty(\$settings['profile_nickname_enabled'])") !== false,
        'Member settings helper should expose normalized profile field flags.'
    );
}

$sessionHelper = toy_member_auth_policy_read('modules/member/helpers/sessions.php');
if ($sessionHelper !== '') {
    toy_member_auth_policy_assert(
        strpos($sessionHelper, 'function toy_member_rotate_current_session') !== false
            && strpos($sessionHelper, 'session_regenerate_id(true)') !== false
            && strpos($sessionHelper, 'toy_member_create_session($pdo, $accountId)') !== false
            && strpos($sessionHelper, 'if (!toy_member_sessions_table_exists($pdo))') !== false,
        'Current member session rotation helper should regenerate PHP and member session tokens.'
    );
    toy_member_auth_policy_assert(
        strpos($sessionHelper, 'function toy_member_login(PDO $pdo, array $account): bool') !== false
            && strpos($sessionHelper, "if (\$sessionTokenHash !== '') {\n        \$_SESSION['toy_session_token_hash'] = \$sessionTokenHash;") !== false
            && strpos($sessionHelper, "unset(\$_SESSION['toy_session_token_hash']);") !== false,
        'Member login should clear stale session token hash when DB session creation fails.'
    );
    toy_member_auth_policy_assert(
        strpos($sessionHelper, 'toy_member_sessions_table_exists($pdo)') !== false
            && strpos($sessionHelper, "unset(\$_SESSION['toy_account_id']);") !== false
            && strpos($sessionHelper, 'return false;') !== false,
        'Member login should fail clearly when DB session creation fails while the session table exists.'
    );
    toy_member_auth_policy_assert(
        strpos($sessionHelper, 'function toy_member_revoke_account_sessions') !== false
            && strpos($sessionHelper, 'function toy_member_revoke_other_sessions') !== false
            && strpos($sessionHelper, 'return -1;') !== false,
        'Member session revocation helpers should distinguish DB failure from zero revoked sessions.'
    );
    toy_member_auth_policy_assert(
        strpos($sessionHelper, 'function toy_member_revoke_current_session(PDO $pdo): int') !== false
            && strpos($sessionHelper, 'function toy_member_logout(?PDO $pdo = null): bool') !== false
            && strpos($sessionHelper, '$sessionRevoked = toy_member_revoke_current_session($pdo) >= 0;') !== false,
        'Current session revocation and logout helpers should report DB revocation failure.'
    );
    toy_member_auth_policy_assert(
        strpos($sessionHelper, 'function toy_member_logout_current_session_if_account') !== false
            && strpos($sessionHelper, 'toy_member_current_session_account_id()') !== false,
        'Session helper should support immediate logout of the current session for a target account.'
    );
}

$logoutAction = toy_member_auth_policy_read('modules/member/actions/logout.php');
if ($logoutAction !== '') {
    toy_member_auth_policy_assert(
        strpos($logoutAction, '$loggedOut = toy_member_logout($pdo)') !== false
            && strpos($logoutAction, "'current_session_revoked' => \$loggedOut") !== false
            && strpos($logoutAction, "\$loggedOut ? 'success' : 'failure'") !== false,
        'Logout action should audit current session revocation failure instead of logging unconditional success.'
    );
}

$accountAction = toy_member_auth_policy_read('modules/member/actions/account.php');
if ($accountAction !== '') {
    toy_member_auth_policy_assert(
        strpos($accountAction, "in_array(\$intent, ['basics', 'profile', 'password'], true)") !== false
            && strpos($accountAction, '계정 작업 값이 올바르지 않습니다.') !== false,
        'Account action should allowlist account update intents.'
    );
    toy_member_auth_policy_assert(
        strpos($accountAction, 'toy_member_rotate_current_session($pdo, (int) $account[\'id\'])') !== false,
        'Password change should rotate the current member session.'
    );
    toy_member_auth_policy_assert(
        strpos($accountAction, 'password_change_session_failed') !== false
            && strpos($accountAction, 'toy_member_logout($pdo)') !== false,
        'Password change should not remain logged in when current session rotation fails.'
    );
    toy_member_auth_policy_assert(
        strpos($accountAction, 'if ($revokedSessions < 0)') !== false
            && strpos($accountAction, 'Other member sessions could not be revoked after password change.') !== false,
        'Password change should not silently continue when other sessions cannot be revoked.'
    );
    toy_member_auth_policy_assert(
        strpos($accountAction, 'toy_member_reauth_throttle_status($pdo, (int) $account[\'id\'])') !== false
            && strpos($accountAction, 'password_change_reauth') !== false,
        'Password change should throttle current-password reauth failures.'
    );
    toy_member_auth_policy_assert(
        strpos($accountAction, "toy_post_string_without_truncation('new_password', 255)") !== false
            && strpos($accountAction, "toy_post_string_without_truncation('new_password_confirm', 255)") !== false
            && strpos($accountAction, '$newPassword === null || $newPasswordConfirm === null') !== false,
        'Password change should reject overlong raw new-password inputs instead of truncating them.'
    );
    toy_member_auth_policy_assert(
        strpos($accountAction, '$profileFields = toy_member_profile_field_settings($memberSettings)') !== false
            && strpos($accountAction, 'if ($profileFields[\'nickname\'])') !== false
            && strpos($accountAction, 'if ($profileFields[\'phone\'])') !== false
            && strpos($accountAction, 'if ($profileFields[\'birth_date\'])') !== false
            && strpos($accountAction, 'if ($profileFields[\'avatar_path\'])') !== false
            && strpos($accountAction, 'if ($profileFields[\'profile_text\'])') !== false,
        'Account action should only update enabled optional profile fields.'
    );
    toy_member_auth_policy_assert(
        strpos($accountAction, '!toy_is_safe_relative_url($profile[\'avatar_path\'])') !== false
            && strpos($accountAction, '!toy_is_public_http_url($profile[\'avatar_path\'])') !== false
            && strpos($accountAction, '공개 http(s) URL') !== false,
        'Account action should allow only safe relative or public http avatar URLs before saving.'
    );
    toy_member_auth_policy_assert(
        strpos($accountAction, 'toy_is_local_host((string) ($site[\'base_url\'] ?? \'\'))') !== false
            && strpos($accountAction, 'toy_debug_email_verification_url') !== false,
        'Account action should only render debug email verification URLs for localhost site base URLs.'
    );
}

$withdrawAction = toy_member_auth_policy_read('modules/member/actions/withdraw.php');
if ($withdrawAction !== '') {
    toy_member_auth_policy_assert(
        strpos($withdrawAction, 'toy_member_reauth_throttle_status($pdo, (int) $account[\'id\'])') !== false
            && strpos($withdrawAction, 'withdraw_reauth') !== false,
        'Withdraw should throttle current-password reauth failures.'
    );
    toy_member_auth_policy_assert(
        strpos($withdrawAction, 'if ($revokedSessions < 0)') !== false
            && strpos($withdrawAction, 'Member sessions could not be revoked before account withdrawal.') !== false,
        'Withdraw should not continue when account sessions cannot be revoked.'
    );
    toy_member_auth_policy_assert(
        strpos($withdrawAction, 'toy_member_record_consent_withdrawals($pdo, (int) $account[\'id\'])') !== false
            && strpos($withdrawAction, "'withdrawn_consents' => \$withdrawnConsents") !== false,
        'Withdraw should record consent withdrawals before account anonymization.'
    );
}

$privacyExportAction = toy_member_auth_policy_read('modules/privacy/actions/account-privacy-export.php');
if ($privacyExportAction !== '') {
    toy_member_auth_policy_assert(
        strpos($privacyExportAction, 'toy_member_privacy_export_reauth_errors($pdo, $account)') !== false
            && strpos($privacyExportAction, 'toy_render_error(403, $reauthError)') !== false,
        'Privacy export action should enforce current-password reauthentication before generating JSON.'
    );
    toy_member_auth_policy_assert(
        strpos($privacyExportAction, 'JSON_INVALID_UTF8_SUBSTITUTE') !== false
            && strpos($privacyExportAction, '$encodedExport = json_encode($export') !== false
            && strpos($privacyExportAction, 'if (!is_string($encodedExport))') !== false
            && strpos($privacyExportAction, 'echo $encodedExport;') !== false,
        'Privacy export action should encode JSON safely before sending download headers.'
    );
}

$memberSettingsHelper = toy_member_auth_policy_read('modules/member/helpers/settings.php');
$memberLoginAction = toy_member_auth_policy_read('modules/member/actions/login.php');
if ($memberSettingsHelper !== '' && $memberLoginAction !== '') {
    toy_member_auth_policy_assert(
        strpos($memberSettingsHelper, 'function toy_member_skin_options(): array') !== false
            && strpos($memberSettingsHelper, "TOY_ROOT . '/modules/member/skins/basic/login.php'") !== false
            && strpos($memberLoginAction, "toy_member_skin_view(toy_member_skin_key(\$memberSettings), 'login')") !== false
            && is_file($root . '/modules/member/skins/basic/login.php'),
        'Member public views should render through explicit member skin views with a basic fallback.'
    );
}

$privacyHelper = toy_member_auth_policy_read('modules/member/helpers/privacy.php');
$privacyOrchestrationHelper = toy_member_auth_policy_read('modules/privacy/helpers/requests.php');
if ($privacyHelper !== '' && $privacyOrchestrationHelper !== '') {
    toy_member_auth_policy_assert(
        strpos($privacyHelper, 'function toy_member_privacy_export_reauth_errors') !== false
            && strpos($privacyHelper, "toy_post_string('current_password', 255)") !== false
            && strpos($privacyHelper, 'toy_member_reauth_throttle_status($pdo, $accountId)') !== false
            && strpos($privacyHelper, 'privacy_export_reauth') !== false
            && strpos($privacyHelper, 'privacy.export.reauth_failed') !== false,
        'Privacy helper should require throttled current-password reauthentication for member privacy exports.'
    );
    toy_member_auth_policy_assert(
        strpos($privacyOrchestrationHelper, 'function toy_privacy_export_sanitize_module_data') !== false
            && strpos($privacyOrchestrationHelper, "toy_enabled_module_contract_files(\$pdo, 'privacy-export.php', ['privacy'])") !== false
            && strpos($privacyOrchestrationHelper, 'toy_load_module_contract_file($moduleKey, $exportFile)') !== false
            && strpos($privacyOrchestrationHelper, 'function toy_privacy_export_internal_key') !== false
            && strpos($privacyOrchestrationHelper, '$moduleExportData = $moduleExport($pdo, $accountId)') !== false
            && strpos($privacyOrchestrationHelper, 'if (is_array($moduleExportData))') !== false
            && strpos($privacyOrchestrationHelper, 'toy_privacy_export_sanitize_module_data($moduleExportData)') !== false
            && strpos($privacyOrchestrationHelper, 'catch (Throwable $exception)') !== false
            && strpos($privacyOrchestrationHelper, "toy_log_exception(\$exception, 'privacy_export_module_' . \$moduleKey)") !== false
            && strpos($privacyOrchestrationHelper, 'password|token|secret|credential|bearer|authorization') !== false
            && strpos($privacyOrchestrationHelper, "str_ends_with(\$normalizedKey, '_token_hash')") !== false
            && strpos($privacyOrchestrationHelper, "str_ends_with(\$normalizedKey, '_hash')") !== false,
        'Privacy helper should isolate module privacy export failures and remove internal hash/token/secret fields.'
    );
}

$accountView = toy_member_auth_policy_read('modules/member/views/account.php');
if ($accountView !== '') {
    toy_member_auth_policy_assert(
        strpos($accountView, 'action="<?php echo toy_e(toy_url(\'/account/privacy-export\')); ?>"') !== false
            && strpos($accountView, 'name="current_password"') !== false
            && strpos($accountView, 'autocomplete="current-password" required') !== false,
        'Account view privacy export form should ask for the current password.'
    );
}

$privacyHelper = toy_member_auth_policy_read('modules/member/helpers/privacy.php');
if ($privacyHelper !== '') {
    toy_member_auth_policy_assert(
        strpos($privacyHelper, 'function toy_member_record_consent_withdrawals') !== false
            && strpos($privacyHelper, 'toy_member_latest_consents($pdo, $accountId)') !== false
            && strpos($privacyHelper, 'false') !== false,
        'Privacy helper should record false consent history rows for withdrawn latest consents.'
    );
    toy_member_auth_policy_assert(
        strpos($privacyHelper, 'function toy_member_privacy_request_list_preview') !== false
            && strpos($privacyHelper, 'toy_log_line_value((string) $value, $maxLength + 1)') !== false
            && strpos($privacyHelper, "return mb_substr(\$preview, 0, \$maxLength) . '...';") !== false,
        'Privacy helper should provide a bounded privacy request list preview.'
    );
}

$privacyRequestsAction = toy_member_auth_policy_read('modules/privacy/actions/account-privacy-requests.php');
if ($privacyRequestsAction !== '') {
    toy_member_auth_policy_assert(
        strpos($privacyRequestsAction, "\$values = [\n    'request_type' => 'access',\n    'request_message' => '',\n];") !== false
            && strpos($privacyRequestsAction, "'request_type' => \$requestType") !== false
            && strpos($privacyRequestsAction, "'request_message' => \$requestMessage") !== false,
        'Privacy request action should preserve submitted form values.'
    );
    toy_member_auth_policy_assert(
        strpos($privacyRequestsAction, "toy_post_string_without_truncation('request_type', 40)") !== false
            && strpos($privacyRequestsAction, "toy_post_string_without_truncation('request_message', 2000)") !== false
            && strpos($privacyRequestsAction, '$requestMessage === null') !== false,
        'Privacy request action should reject overlong raw request inputs instead of truncating them.'
    );
    toy_member_auth_policy_assert(
        strpos($privacyRequestsAction, "AND status IN (\\'requested\\', \\'reviewing\\')") !== false
            && strpos($privacyRequestsAction, '이미 처리 대기 중인 같은 유형의 개인정보 처리 요청이 있습니다.') !== false,
        'Privacy request action should block duplicate in-progress requests of the same type.'
    );
}

$privacyRequestsView = toy_member_auth_policy_read('modules/privacy/views/account-privacy-requests.php');
if ($privacyRequestsView !== '') {
    toy_member_auth_policy_assert(
        strpos($privacyRequestsView, "\$values['request_type'] === \$requestType ? ' selected' : ''") !== false
            && strpos($privacyRequestsView, "toy_e(\$values['request_message'])") !== false,
        'Privacy request view should render preserved form values safely.'
    );
    toy_member_auth_policy_assert(
        strpos($privacyRequestsView, "toy_admin_privacy_request_list_preview(\$request['admin_note'] ?? null)") !== false,
        'Privacy request view should render admin notes through the bounded preview helper.'
    );
}

$adminPrivacyRequestsAction = toy_member_auth_policy_read('modules/privacy/actions/admin-privacy-requests.php');
if ($adminPrivacyRequestsAction !== '') {
    toy_member_auth_policy_assert(
        strpos($adminPrivacyRequestsAction, "if (toy_request_method() === 'GET')") !== false
            && strpos($adminPrivacyRequestsAction, "'event_type' => 'privacy.request.list.viewed'") !== false
            && strpos($adminPrivacyRequestsAction, "'status_filter' => \$statusFilter") !== false
            && strpos($adminPrivacyRequestsAction, "'result_count' => count(\$requests)") !== false,
        'Admin privacy request list views should be audited without logging raw request contents.'
    );
}

$adminPrivacyRequestsHelper = toy_member_auth_policy_read('modules/privacy/helpers/requests.php');
if ($adminPrivacyRequestsHelper !== '') {
    toy_member_auth_policy_assert(
        strpos($adminPrivacyRequestsHelper, 'function toy_admin_privacy_request_terminal_statuses') !== false
            && strpos($adminPrivacyRequestsHelper, "in_array((string) \$privacyRequest['status'], toy_admin_privacy_request_terminal_statuses(), true)") !== false
            && strpos($adminPrivacyRequestsHelper, '종결된 개인정보 처리 요청 상태는 다시 변경할 수 없습니다.') !== false,
        'Admin privacy request helper should prevent reopening terminal privacy request statuses.'
    );
    toy_member_auth_policy_assert(
        strpos($adminPrivacyRequestsHelper, 'SELECT id, status, admin_note FROM toy_privacy_requests WHERE id = :id LIMIT 1') !== false
            && strpos($adminPrivacyRequestsHelper, "\$nextAdminNote = \$adminNote !== '' ? \$adminNote : \$storedAdminNote;") !== false
            && strpos($adminPrivacyRequestsHelper, "'admin_note' => \$nextAdminNote") !== false,
        'Admin privacy request helper should preserve stored admin notes when list forms submit no replacement note.'
    );
    toy_member_auth_policy_assert(
        strpos($adminPrivacyRequestsHelper, "toy_post_string_without_truncation('status', 30)") !== false
            && strpos($adminPrivacyRequestsHelper, "toy_post_string_without_truncation('admin_note', 2000)") !== false
            && strpos($adminPrivacyRequestsHelper, '$adminNote === null') !== false,
        'Admin privacy request helper should reject overlong raw status/admin note inputs instead of truncating them.'
    );
    toy_member_auth_policy_assert(
        strpos($adminPrivacyRequestsHelper, 'function toy_admin_privacy_request_export_reauth_errors') !== false
            && strpos($adminPrivacyRequestsHelper, "toy_post_string('admin_password', 255)") !== false
            && strpos($adminPrivacyRequestsHelper, 'toy_member_reauth_throttle_status($pdo, $accountId)') !== false
            && strpos($adminPrivacyRequestsHelper, 'privacy_request_export_reauth') !== false
            && strpos($adminPrivacyRequestsHelper, 'privacy.request.export_reauth_failed') !== false,
        'Admin privacy request export should require throttled current-admin reauthentication.'
    );
    toy_member_auth_policy_assert(
        strpos($adminPrivacyRequestsHelper, 'toy_privacy_export_data($pdo, (int) $privacyRequest[\'account_id\'])') !== false
            && strpos($adminPrivacyRequestsHelper, 'catch (Throwable $exception)') !== false
            && strpos($adminPrivacyRequestsHelper, "toy_log_exception(\$exception, 'privacy_request_export_account_' . (int) \$privacyRequest['id'])") !== false
            && strpos($adminPrivacyRequestsHelper, "\$export['account_data_unavailable'] = true") !== false,
        'Admin privacy request export should isolate linked account export failures.'
    );
}

$adminPrivacyRequestsView = toy_member_auth_policy_read('modules/privacy/views/admin-privacy-requests.php');
if ($adminPrivacyRequestsView !== '') {
    toy_member_auth_policy_assert(
        strpos($adminPrivacyRequestsView, 'placeholder="새 관리자 메모"') !== false
            && strpos($adminPrivacyRequestsView, "\$request['admin_note'] ?? ''") === false,
        'Admin privacy request view should not prefill stored admin notes in list forms.'
    );
    toy_member_auth_policy_assert(
        strpos($adminPrivacyRequestsView, 'name="admin_password"') !== false
            && strpos($adminPrivacyRequestsView, 'autocomplete="current-password" required') !== false,
        'Admin privacy request export form should ask for current admin password.'
    );
}

$adminPrivacyRequestExportAction = toy_member_auth_policy_read('modules/privacy/actions/admin-privacy-request-export.php');
if ($adminPrivacyRequestExportAction !== '') {
    toy_member_auth_policy_assert(
        strpos($adminPrivacyRequestExportAction, 'toy_admin_privacy_request_export_reauth_errors($pdo, $account, $requestId)') !== false
            && strpos($adminPrivacyRequestExportAction, 'toy_render_error(403, $reauthError)') !== false,
        'Admin privacy request export action should enforce reauthentication before generating JSON.'
    );
    toy_member_auth_policy_assert(
        strpos($adminPrivacyRequestExportAction, 'JSON_INVALID_UTF8_SUBSTITUTE') !== false
            && strpos($adminPrivacyRequestExportAction, '$encodedExport = json_encode($export') !== false
            && strpos($adminPrivacyRequestExportAction, 'if (!is_string($encodedExport))') !== false
            && strpos($adminPrivacyRequestExportAction, 'echo $encodedExport;') !== false,
        'Admin privacy request export action should encode JSON safely before sending download headers.'
    );
}

$registerAction = toy_member_auth_policy_read('modules/member/actions/register.php');
if ($registerAction !== '') {
    toy_member_auth_policy_assert(
        strpos($registerAction, "toy_post_string_without_truncation('email', 255)") !== false
            && strpos($registerAction, '$email === null') !== false,
        'Register action should reject overlong raw email inputs instead of truncating them.'
    );
    toy_member_auth_policy_assert(
        strpos($registerAction, "toy_post_string_without_truncation('login_id', 40)") !== false
            && strpos($registerAction, '$loginId === null') !== false,
        'Register action should reject overlong raw login_id inputs instead of truncating them.'
    );
    toy_member_auth_policy_assert(
        strpos($registerAction, "toy_post_string_without_truncation('password', 255)") !== false
            && strpos($registerAction, "toy_post_string_without_truncation('password_confirm', 255)") !== false
            && strpos($registerAction, '$password === null || $passwordConfirm === null') !== false,
        'Register action should reject overlong raw password inputs instead of truncating them.'
    );
    toy_member_auth_policy_assert(
        strpos($registerAction, 'toy_member_login($pdo, $newAccount)') !== false
            && strpos($registerAction, '로그인 세션을 만들 수 없습니다') !== false,
        'Register action should keep auto-login for immediately verified accounts.'
    );
    toy_member_auth_policy_assert(
        strpos($registerAction, "toy_redirect('/login')") !== false
            && strpos($registerAction, '이메일 인증을 완료한 뒤 로그인하세요') !== false,
        'Register action should not auto-login unverified accounts.'
    );
    toy_member_auth_policy_assert(
        strpos($registerAction, '$verificationMailSent = null') !== false
            && strpos($registerAction, "'email_verification_mail_sent' => \$verificationMailSent") !== false,
        'Register action should audit email verification mail delivery result without storing token values.'
    );
    toy_member_auth_policy_assert(
        strpos($registerAction, 'email_verification_mail_failed') !== false,
        'Register action should write an auth log event when verification mail delivery fails.'
    );
    toy_member_auth_policy_assert(
        strpos($registerAction, '$showVerificationUrl = !empty($config[\'debug\']) && toy_is_local_host((string) ($site[\'base_url\'] ?? \'\'));') !== false
            && strpos($registerAction, 'if ($showVerificationUrl)') !== false,
        'Register action should only store debug email verification URLs when the configured site base URL is localhost.'
    );
    toy_member_auth_policy_assert(
        strpos($registerAction, "unset(\$_SESSION['toy_debug_email_verification_url']);") !== false,
        'Register action should clear stale debug email verification URLs outside localhost debug mode.'
    );
    $registerTransaction = strpos($registerAction, '$pdo->beginTransaction();');
    $registerConsent = strpos($registerAction, "toy_member_record_consent(\$pdo, \$accountId, 'privacy'");
    $registerCommit = strpos($registerAction, '$pdo->commit();');
    $registerMail = strpos($registerAction, '$verificationMailSent = toy_send_mail');
    toy_member_auth_policy_assert(
        $registerTransaction !== false
            && $registerConsent !== false
            && $registerCommit !== false
            && $registerTransaction < $registerConsent
            && $registerConsent < $registerCommit,
        'Register action should create account, verification token, and required consents in one transaction.'
    );
    toy_member_auth_policy_assert(
        $registerCommit !== false
            && $registerMail !== false
            && $registerCommit < $registerMail,
        'Register action should send email only after the account transaction commits.'
    );
    toy_member_auth_policy_assert(
        strpos($registerAction, "\$marketingConsent = (\$_POST['marketing_consent'] ?? '') === '1';") !== false
            && strpos($registerAction, "toy_member_record_consent(\$pdo, \$accountId, 'marketing', '2026.04.001', \$marketingConsent)") !== false,
        'Register action should record optional marketing consent history.'
    );
    toy_member_auth_policy_assert(
        strpos($registerAction, "\$loginIdentifierMode = (string) \$memberSettings['login_identifier'];") !== false
            && strpos($registerAction, "'login_id' => toy_member_normalize_login_id(\$loginId)") !== false
            && strpos($registerAction, "toy_member_is_valid_login_id(\$values['login_id'])") !== false
            && strpos($registerAction, "'login_id' => \$loginIdentifierMode === 'login_id' ? \$values['login_id'] : ''") !== false,
        'Register action should collect and validate login_id when login_id identifier mode is enabled.'
    );
}

$registerView = toy_member_auth_policy_read('modules/member/views/register.php');
if ($registerView !== '') {
    toy_member_auth_policy_assert(
        strpos($registerView, '$loginIdentifierMode === \'login_id\'') !== false
            && strpos($registerView, 'name="login_id"') !== false,
        'Register view should render login_id input only when login_id identifier mode is enabled.'
    );
    toy_member_auth_policy_assert(
        strpos($registerView, 'name="marketing_consent"') !== false
            && strpos($registerView, '$marketingConsent ? \' checked\' : \'\'') !== false,
        'Register view should render optional marketing consent and preserve submitted state.'
    );
}

$adminSettingsAction = toy_member_auth_policy_read('modules/member/actions/admin-settings.php');
if ($adminSettingsAction !== '') {
    toy_member_auth_policy_assert(
        strpos($adminSettingsAction, "toy_post_string('login_identifier', 20)") !== false
            && strpos($adminSettingsAction, "['email', 'login_id']") !== false
            && strpos($adminSettingsAction, "['login_identifier', (string) \$settings['login_identifier'], 'string']") !== false,
        'Member settings action should validate and save login_identifier.'
    );
    toy_member_auth_policy_assert(
        strpos($adminSettingsAction, 'toy_member_profile_field_setting_keys()') !== false
            && strpos($adminSettingsAction, "'profile_fields' => toy_member_profile_field_settings(\$settings)") !== false,
        'Member settings action should save optional profile field settings and audit them.'
    );
    toy_member_auth_policy_assert(
        strpos($adminSettingsAction, 'toy_admin_post_int_in_range($key, (int) $limits[\'min\'], (int) $limits[\'max\'])') !== false
            && strpos($adminSettingsAction, '$integerValue === null') !== false
            && strpos($adminSettingsAction, '$settings[$key] = $integerValue;') !== false
            && strpos($adminSettingsAction, 'toy_member_clamp_int((int) $rawValue') === false,
        'Member settings action should reject out-of-range integer settings instead of truncating or clamping submitted values.'
    );
}

$adminSettingsView = toy_member_auth_policy_read('modules/member/views/admin-settings.php');
if ($adminSettingsView !== '') {
    toy_member_auth_policy_assert(
        strpos($adminSettingsView, 'name="login_identifier"') !== false
            && strpos($adminSettingsView, 'value="login_id"') !== false,
        'Member settings view should expose login_identifier selection.'
    );
    toy_member_auth_policy_assert(
        strpos($adminSettingsView, '선택 프로필 항목') !== false
            && strpos($adminSettingsView, 'toy_member_profile_field_setting_keys()') !== false,
        'Member settings view should expose optional profile field settings.'
    );
}

$accountView = toy_member_auth_policy_read('modules/member/views/account.php');
if ($accountView !== '') {
    toy_member_auth_policy_assert(
        strpos($accountView, 'if ($profileFieldsEnabled)') !== false
            && strpos($accountView, "if (\$profileFields['nickname'])") !== false
            && strpos($accountView, "if (\$profileFields['phone'])") !== false
            && strpos($accountView, "if (\$profileFields['birth_date'])") !== false
            && strpos($accountView, "if (\$profileFields['avatar_path'])") !== false
            && strpos($accountView, "if (\$profileFields['profile_text'])") !== false,
        'Account view should render only enabled optional profile fields.'
    );
}

$emailVerificationRequestAction = toy_member_auth_policy_read('modules/member/actions/email-verification-request.php');
if ($emailVerificationRequestAction !== '') {
    toy_member_auth_policy_assert(
        strpos($emailVerificationRequestAction, "'mail_sent' => \$mailSent") !== false,
        'Email verification request action should audit mail delivery result.'
    );
    toy_member_auth_policy_assert(
        strpos($emailVerificationRequestAction, 'email_verification_mail_failed') !== false,
        'Email verification request action should write an auth log event when mail delivery fails.'
    );
    toy_member_auth_policy_assert(
        strpos($emailVerificationRequestAction, '$showVerificationUrl = !empty($config[\'debug\']) && toy_is_local_host((string) ($site[\'base_url\'] ?? \'\'));') !== false
            && strpos($emailVerificationRequestAction, 'if ($showVerificationUrl)') !== false,
        'Email verification request action should only store debug verification URLs when the configured site base URL is localhost.'
    );
    toy_member_auth_policy_assert(
        strpos($emailVerificationRequestAction, "unset(\$_SESSION['toy_debug_email_verification_url']);") !== false,
        'Email verification request action should clear stale debug verification URLs outside localhost debug mode.'
    );
}

$loginAction = toy_member_auth_policy_read('modules/member/actions/login.php');
if ($loginAction !== '') {
    toy_member_auth_policy_assert(
        strpos($loginAction, 'toy_member_rehash_login_password_if_needed') !== false,
        'Login action should rehash stale password hashes after successful verification.'
    );
    toy_member_auth_policy_assert(
        strpos($loginAction, 'if (toy_member_login($pdo, $account))') !== false
            && strpos($loginAction, 'login_session_failed') !== false,
        'Login action should not record login success when member session creation fails.'
    );
}

$paths = toy_member_auth_policy_read('modules/member/paths.php');
if ($paths !== '') {
    toy_member_auth_policy_assert(
        strpos($paths, "'GET /email/verified' => 'actions/email-verified.php'") !== false,
        'Email verification success route should be tokenless.'
    );
}

$emailVerifyAction = toy_member_auth_policy_read('modules/member/actions/email-verify.php');
if ($emailVerifyAction !== '') {
    toy_member_auth_policy_assert(
        strpos($emailVerifyAction, "toy_get_string_without_truncation('token', 64)") !== false
            && strpos($emailVerifyAction, '$token === null') !== false,
        'Email verification action should reject overlong raw token inputs instead of truncating them.'
    );
    toy_member_auth_policy_assert(
        strpos($emailVerifyAction, "toy_redirect('/email/verified')") !== false,
        'Email verification action should redirect to a tokenless success page.'
    );
}

$passwordResetAction = toy_member_auth_policy_read('modules/member/actions/password-reset.php');
if ($passwordResetAction !== '') {
    toy_member_auth_policy_assert(
        strpos($passwordResetAction, "toy_get_string_without_truncation('token', 64)") !== false
            && strpos($passwordResetAction, '$tokenInputInvalid') !== false,
        'Password reset confirm action should reject overlong raw token inputs instead of truncating them.'
    );
    toy_member_auth_policy_assert(
        strpos($passwordResetAction, "toy_post_string_without_truncation('password', 255)") !== false
            && strpos($passwordResetAction, "toy_post_string_without_truncation('password_confirm', 255)") !== false
            && strpos($passwordResetAction, '$password === null || $passwordConfirm === null') !== false,
        'Password reset should reject overlong raw password inputs instead of truncating them.'
    );
    toy_member_auth_policy_assert(
        strpos($passwordResetAction, 'toy_member_store_password_reset_session_hash') !== false,
        'Password reset confirm action should keep only the reset token hash in session after initial validation.'
    );
    toy_member_auth_policy_assert(
        strpos($passwordResetAction, 'toy_member_password_reset_session_hash($resetTokenSessionSeconds)') !== false,
        'Password reset confirm action should enforce a short session hash lifetime.'
    );
    toy_member_auth_policy_assert(
        strpos($passwordResetAction, "toy_redirect('/password/reset/confirm')") !== false,
        'Password reset confirm action should redirect token query URLs to a tokenless form URL.'
    );
    toy_member_auth_policy_assert(
        strpos($passwordResetAction, 'toy_member_logout_current_session_if_account($pdo, (int) $reset[\'account_id\'])') !== false,
        'Password reset completion should immediately clear the current PHP session for the reset account.'
    );
    toy_member_auth_policy_assert(
        strpos($passwordResetAction, '$loggedOutCurrentSession = toy_member_logout_current_session_if_account($pdo, (int) $reset[\'account_id\'])') !== false
            && strpos($passwordResetAction, "'current_session_logout_required' => \$shouldLogoutCurrentSession") !== false
            && strpos($passwordResetAction, "'logged_out_current_session' => \$loggedOutCurrentSession") !== false,
        'Password reset audit metadata should record the actual current-session logout result.'
    );
    toy_member_auth_policy_assert(
        strpos($passwordResetAction, 'if ($revokedSessions < 0)') !== false
            && strpos($passwordResetAction, 'Member sessions could not be revoked after password reset.') !== false,
        'Password reset should not complete when account sessions cannot be revoked.'
    );
    toy_member_auth_policy_assert(
        strpos($passwordResetAction, "toy_redirect('/login?password_reset=1')") !== false,
        'Password reset completion should redirect to login instead of rendering another reset form after session cleanup.'
    );
}

$passwordResetRequestAction = toy_member_auth_policy_read('modules/member/actions/password-reset-request.php');
if ($passwordResetRequestAction !== '') {
    toy_member_auth_policy_assert(
        strpos($passwordResetRequestAction, "toy_post_string_without_truncation('email', 255)") !== false
            && strpos($passwordResetRequestAction, '$email === null') !== false,
        'Password reset request action should reject overlong raw email inputs instead of truncating them.'
    );
    toy_member_auth_policy_assert(
        strpos($passwordResetRequestAction, '$mailSent = toy_send_mail') !== false
            && strpos($passwordResetRequestAction, "'mail_sent' => \$mailSent") !== false,
        'Password reset request action should audit reset mail delivery result.'
    );
    toy_member_auth_policy_assert(
        strpos($passwordResetRequestAction, 'password_reset_mail_failed') !== false,
        'Password reset request action should write an auth log event when reset mail delivery fails.'
    );
    toy_member_auth_policy_assert(
        strpos($passwordResetRequestAction, '$showResetUrl = false;') !== false
            && strpos($passwordResetRequestAction, '$showResetUrl = !empty($config[\'debug\']) && toy_is_local_host((string) ($site[\'base_url\'] ?? \'\'));') !== false,
        'Password reset request action should only expose debug reset URLs when the configured site base URL is localhost.'
    );
}

$tokenHelper = toy_member_auth_policy_read('modules/member/helpers/tokens.php');
if ($tokenHelper !== '') {
    toy_member_auth_policy_assert(
        strpos($tokenHelper, 'function toy_member_password_reset_token_hash') !== false,
        'Password reset token hash helper is missing.'
    );
    toy_member_auth_policy_assert(
        strpos($tokenHelper, 'function toy_member_password_reset_session_hash') !== false,
        'Password reset session hash helper is missing.'
    );
    toy_member_auth_policy_assert(
        strpos($tokenHelper, 'a.email AS account_email') !== false
            && strpos($tokenHelper, "toy_normalize_identifier((string) \$verification['email']) !== toy_normalize_identifier((string) \$verification['account_email'])") !== false
            && strpos($tokenHelper, 'AND email = :email') !== false,
        'Email verification should only verify the current account email that matches the issued token.'
    );
    toy_member_auth_policy_assert(
        strpos($tokenHelper, 'toy_password_reset_token_hash') !== false
            && strpos($tokenHelper, 'toy_password_reset_token_stored_at') !== false,
        'Password reset session should store hash and stored_at only.'
    );
    toy_member_auth_policy_assert(
        strpos($tokenHelper, 'toy_password_reset_token\'') === false
            && strpos($tokenHelper, 'toy_password_reset_token"') === false,
        'Password reset session should not store the raw reset token.'
    );
}

$passwordResetView = toy_member_auth_policy_read('modules/member/views/password-reset.php');
if ($passwordResetView !== '') {
    toy_member_auth_policy_assert(
        strpos($passwordResetView, 'name="token"') === false,
        'Password reset form should not render the reset token into HTML.'
    );
}

$passwordResetRequestView = toy_member_auth_policy_read('modules/member/views/password-reset-request.php');
if ($passwordResetRequestView !== '') {
    toy_member_auth_policy_assert(
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
