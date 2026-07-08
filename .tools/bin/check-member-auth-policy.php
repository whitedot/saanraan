#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);

require_once $root . '/core/helpers/runtime.php';
require_once $root . '/core/helpers/output.php';
require_once $root . '/modules/member/helpers/accounts.php';
require_once $root . '/modules/member/helpers/sessions.php';
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
$memberModule = sr_member_auth_policy_read('modules/member/module.php');
$memberMfaProviders = sr_member_auth_policy_read('modules/member/member-mfa-providers.php');
sr_member_auth_policy_assert(
    strpos($memberModule, "'mfa_login_mode' => 'disabled'") !== false
        && strpos($memberModule, "'mfa_login_enabled' => false") !== false
        && strpos($memberModule, "'mfa_login_providers_json' => '[\"email\",\"totp\"]'") !== false,
    'Member module install defaults should keep login MFA policy disabled with email and TOTP providers available.'
);
sr_member_auth_policy_assert(
    strpos($memberMfaProviders, "'email' => [") !== false
        && strpos($memberMfaProviders, "'method' => 'email'") !== false
        && strpos($memberMfaProviders, "'account_setup_supported' => false") !== false,
    'Member MFA providers should include built-in email login codes without account setup.'
);

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
        && in_array('mfa_setup_reauth', sr_member_reauth_failure_event_types(), true)
        && in_array('mfa_manage_reauth', sr_member_reauth_failure_event_types(), true)
        && in_array('withdraw_reauth', sr_member_reauth_failure_event_types(), true)
        && in_array('privacy_export_reauth', sr_member_reauth_failure_event_types(), true)
        && in_array('module_setting_reauth', sr_member_reauth_failure_event_types(), true)
        && in_array('privacy_request_export_reauth', sr_member_reauth_failure_event_types(), true)
        && in_array('reauth_blocked', sr_member_reauth_failure_event_types(), true),
    'Sensitive reauth failures should count as reauth throttle events.'
);

$_SESSION = [];
sr_member_mfa_start_challenge(['id' => 77], 'password', '/admin?tab=security', ['provider_key' => 'mock']);
$mfaChallenge = sr_member_mfa_challenge();
sr_member_auth_policy_assert(
    is_array($mfaChallenge)
        && (int) ($mfaChallenge['account_id'] ?? 0) === 77
        && (string) ($mfaChallenge['primary_method'] ?? '') === 'password'
        && (string) ($mfaChallenge['next_path'] ?? '') === '/admin?tab=security'
        && !isset($_SESSION['sr_account_id'], $_SESSION['sr_session_token_hash']),
    'MFA challenge should store pending login state without creating a member session.'
);
sr_member_auth_policy_assert(
    sr_member_current_session_account_id() === null,
    'MFA challenge state should not be treated as a logged-in member session.'
);
sr_member_auth_policy_assert(
    sr_member_safe_next_path('/login/mfa') === '/',
    'MFA route should not be accepted as a login next destination.'
);
$_SESSION['sr_member_mfa_challenge']['expires_at'] = time() - 1;
sr_member_auth_policy_assert(
    sr_member_mfa_challenge() === null && !isset($_SESSION['sr_member_mfa_challenge']),
    'Expired MFA challenge should be cleared.'
);

$mfaPdo = new PDO('sqlite::memory:');
$mfaPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$mfaPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$mfaPdo->exec('CREATE TABLE sr_member_mfa_factors (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, factor_type TEXT NOT NULL, status TEXT NOT NULL, secret_ciphertext TEXT NOT NULL, secret_fingerprint TEXT NOT NULL, issuer TEXT NOT NULL, label TEXT NOT NULL, last_used_step INTEGER NULL, activated_at TEXT NULL, disabled_at TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
$mfaPdo->exec('CREATE TABLE sr_member_mfa_recovery_codes (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, factor_id INTEGER NULL, code_hash TEXT NOT NULL, status TEXT NOT NULL, batch_uid TEXT NOT NULL, used_at TEXT NULL, revoked_at TEXT NULL, created_at TEXT NOT NULL)');
$mfaPdo->exec("INSERT INTO sr_member_mfa_factors (id, account_id, factor_type, status, secret_ciphertext, secret_fingerprint, issuer, label, last_used_step, activated_at, disabled_at, created_at, updated_at) VALUES (1, 77, 'totp', 'pending', 'cipher-pending', 'finger-pending', 'Site', 'member77', NULL, NULL, NULL, '2026-07-02 00:00:00', '2026-07-02 00:00:00'), (2, 77, 'totp', 'active', 'cipher-active', 'finger-active', 'Site', 'member77', 123, '2026-07-02 00:01:00', NULL, '2026-07-02 00:00:00', '2026-07-02 00:01:00'), (3, 78, 'totp', 'active', 'cipher-other', 'finger-other', 'Site', 'member78', NULL, '2026-07-02 00:01:00', NULL, '2026-07-02 00:00:00', '2026-07-02 00:01:00')");
$mfaPdo->exec("INSERT INTO sr_member_mfa_recovery_codes (id, account_id, factor_id, code_hash, status, batch_uid, used_at, revoked_at, created_at) VALUES (1, 77, 2, 'hash-unused', 'unused', 'batch77', NULL, NULL, '2026-07-02 00:01:00'), (2, 77, 2, 'hash-used', 'used', 'batch77', '2026-07-02 00:02:00', NULL, '2026-07-02 00:01:00'), (3, 78, 3, 'hash-other', 'unused', 'batch78', NULL, NULL, '2026-07-02 00:01:00')");
sr_member_auth_policy_assert(
    sr_member_mfa_active_factor_exists($mfaPdo, 77) && !sr_member_mfa_active_factor_exists($mfaPdo, 79),
    'MFA active factor helper should only treat active TOTP factors as login challenges.'
);
$_SESSION = [];
$oauthMfaGate = sr_member_login_or_start_mfa($mfaPdo, ['id' => 77], 'oauth', '/admin', ['provider_key' => 'mock', 'oauth_account_id' => 12]);
$oauthMfaChallenge = sr_member_mfa_challenge();
sr_member_auth_policy_assert(
    $oauthMfaGate === 'mfa_required'
        && is_array($oauthMfaChallenge)
        && (int) ($oauthMfaChallenge['account_id'] ?? 0) === 77
        && (string) ($oauthMfaChallenge['primary_method'] ?? '') === 'oauth'
        && (string) ($oauthMfaChallenge['next_path'] ?? '') === '/admin'
        && (string) ($oauthMfaChallenge['context']['provider_key'] ?? '') === 'mock'
        && (string) ($oauthMfaChallenge['context']['oauth_account_id'] ?? '') === '12'
        && !isset($_SESSION['sr_account_id'], $_SESSION['sr_session_token_hash'])
        && sr_member_current_session_account_id() === null,
    'OAuth login for an active MFA account should create only an MFA challenge, even when the next path is an admin route.'
);
$_SESSION = [];
$oauthCompletionMfaGate = sr_member_login_or_start_mfa($mfaPdo, ['id' => 77], 'oauth_completion', '/admin/updates', ['provider_key' => 'mock']);
$oauthCompletionMfaChallenge = sr_member_mfa_challenge();
sr_member_auth_policy_assert(
    $oauthCompletionMfaGate === 'mfa_required'
        && is_array($oauthCompletionMfaChallenge)
        && (string) ($oauthCompletionMfaChallenge['primary_method'] ?? '') === 'oauth_completion'
        && (string) ($oauthCompletionMfaChallenge['next_path'] ?? '') === '/admin/updates'
        && !isset($_SESSION['sr_account_id'], $_SESSION['sr_session_token_hash']),
    'OAuth completion auto-login for an active MFA account should not create a completed member session before MFA.'
);
$mfaMetadata = sr_member_mfa_privacy_metadata($mfaPdo, 77);
sr_member_auth_policy_assert(
    count($mfaMetadata['factors'] ?? []) === 2
        && (($mfaMetadata['recovery_code_counts']['unused'] ?? 0) === 1)
        && (($mfaMetadata['recovery_code_counts']['used'] ?? 0) === 1)
        && !array_key_exists('secret_ciphertext', $mfaMetadata['factors'][0] ?? [])
        && !array_key_exists('secret_fingerprint', $mfaMetadata['factors'][0] ?? []),
    'MFA privacy metadata should include status summaries without secret ciphertext, fingerprints, or recovery hashes.'
);
$mfaConfig = ['app_key' => 'member-mfa-runtime-test-app-key'];
$mfaSecret = '12345678901234567890';
$mfaSecretCiphertext = sr_member_mfa_totp_secret_ciphertext($mfaSecret, $mfaConfig);
$mfaPdo->prepare('UPDATE sr_member_mfa_factors SET secret_ciphertext = :secret_ciphertext, secret_fingerprint = :secret_fingerprint, last_used_step = NULL WHERE id = 2')->execute([
    'secret_ciphertext' => $mfaSecretCiphertext,
    'secret_fingerprint' => sr_member_mfa_totp_secret_fingerprint($mfaSecret, $mfaConfig),
]);
sr_member_auth_policy_assert(
    sr_member_mfa_hotp_code($mfaSecret, 1) === '287082',
    'MFA HOTP helper should match the RFC 4226 six-digit counter fixture.'
);
$mfaCode = sr_member_mfa_totp_code($mfaSecret, 59);
$mfaVerify = sr_member_mfa_verify_totp_code($mfaPdo, 77, $mfaCode, 59, $mfaConfig);
$mfaReplay = sr_member_mfa_verify_totp_code($mfaPdo, 77, $mfaCode, 59, $mfaConfig);
$mfaNextCode = sr_member_mfa_totp_code($mfaSecret, 90);
$mfaNextVerify = sr_member_mfa_verify_totp_code($mfaPdo, 77, $mfaNextCode, 90, $mfaConfig);
$mfaWrongKey = sr_member_mfa_verify_totp_code($mfaPdo, 77, $mfaNextCode, 90, ['app_key' => 'wrong-member-mfa-key']);
sr_member_auth_policy_assert(
    !empty($mfaVerify['verified'])
        && (int) ($mfaVerify['factor_id'] ?? 0) === 2
        && empty($mfaReplay['verified'])
        && (string) ($mfaReplay['reason'] ?? '') === 'replayed_code'
        && !empty($mfaNextVerify['verified'])
        && (int) $mfaPdo->query('SELECT last_used_step FROM sr_member_mfa_factors WHERE id = 2')->fetchColumn() === 3,
    'MFA TOTP verification should atomically advance last_used_step and reject replayed steps.'
);
sr_member_auth_policy_assert(
    empty($mfaWrongKey['verified'])
        && (string) ($mfaWrongKey['reason'] ?? '') === 'secret_unavailable',
    'MFA TOTP verification should fail closed when the app key cannot decrypt the stored secret.'
);
$mfaEmailCode = '123456';
sr_member_mfa_start_challenge(['id' => 80], 'password', '/', ['mfa_provider_keys' => 'email']);
$_SESSION['sr_member_mfa_challenge']['context']['email_code_hash'] = sr_member_mfa_email_code_hash($mfaEmailCode, 80, $mfaConfig);
$_SESSION['sr_member_mfa_challenge']['context']['email_code_expires_at'] = (string) (time() + sr_member_mfa_email_code_ttl_seconds());
$mfaEmailVerify = sr_member_mfa_verify_email_code(80, $mfaEmailCode, $mfaConfig);
$mfaEmailReplay = sr_member_mfa_verify_email_code(80, $mfaEmailCode, $mfaConfig);
sr_member_auth_policy_assert(
    !empty($mfaEmailVerify['verified'])
        && empty($mfaEmailReplay['verified'])
        && (string) ($mfaEmailReplay['reason'] ?? '') === 'expired_code'
        && !isset($_SESSION['sr_debug_mfa_email_code']),
    'MFA email code verification should accept a matching hashed session code once and clear it after success.'
);
$mfaPendingSetup = sr_member_mfa_create_pending_totp_factor($mfaPdo, 79, 'Saanraan', 'member79@example.test', $mfaConfig);
$mfaPendingSecret = sr_member_mfa_base32_decode((string) ($mfaPendingSetup['secret_base32'] ?? ''));
$mfaPendingQrDataUri = (string) ($mfaPendingSetup['otpauth_qr_svg_data_uri'] ?? '');
$mfaPendingQrSvg = str_starts_with($mfaPendingQrDataUri, 'data:image/svg+xml;base64,')
    ? base64_decode(substr($mfaPendingQrDataUri, strlen('data:image/svg+xml;base64,')), true)
    : '';
$mfaPendingCode = is_string($mfaPendingSecret) ? sr_member_mfa_totp_code($mfaPendingSecret, 59) : '';
$mfaPendingActivate = sr_member_mfa_activate_pending_totp_factor($mfaPdo, 79, (int) ($mfaPendingSetup['factor_id'] ?? 0), $mfaPendingCode, 59, $mfaConfig);
$mfaSecondPending = sr_member_mfa_create_pending_totp_factor($mfaPdo, 79, 'Saanraan', 'member79@example.test', $mfaConfig);
$mfaPendingMissing = sr_member_mfa_pending_totp_factor($mfaPdo, 79);
sr_member_auth_policy_assert(
    !empty($mfaPendingSetup['created'])
        && (int) ($mfaPendingSetup['factor_id'] ?? 0) > 0
        && str_starts_with((string) ($mfaPendingSetup['otpauth_uri'] ?? ''), 'otpauth://totp/')
        && is_string($mfaPendingQrSvg)
        && str_contains($mfaPendingQrSvg, '<svg')
        && str_contains($mfaPendingQrSvg, 'path fill="#000"')
        && is_string($mfaPendingSecret)
        && !empty($mfaPendingActivate['activated'])
        && (int) $mfaPdo->query("SELECT COUNT(*) FROM sr_member_mfa_factors WHERE account_id = 79 AND status = 'active'")->fetchColumn() === 1
        && empty($mfaSecondPending['created'])
        && (string) ($mfaSecondPending['reason'] ?? '') === 'active_exists'
        && $mfaPendingMissing === null,
    'MFA TOTP setup should create one pending secret, activate it with the first valid code, and prevent duplicate active TOTP factors.'
);
$mfaRecoverySetup = sr_member_mfa_rotate_recovery_codes($mfaPdo, 79, (int) ($mfaPendingActivate['factor_id'] ?? 0), 3, $mfaConfig);
$mfaRecoveryCodes = is_array($mfaRecoverySetup['codes'] ?? null) ? $mfaRecoverySetup['codes'] : [];
$mfaRecoveryConsume = $mfaRecoveryCodes !== [] ? sr_member_mfa_consume_recovery_code($mfaPdo, 79, (string) $mfaRecoveryCodes[0], $mfaConfig) : [];
$mfaRecoveryReplay = $mfaRecoveryCodes !== [] ? sr_member_mfa_consume_recovery_code($mfaPdo, 79, (string) $mfaRecoveryCodes[0], $mfaConfig) : [];
$mfaRecoveryMissingKey = count($mfaRecoveryCodes) > 1 ? sr_member_mfa_consume_recovery_code($mfaPdo, 79, (string) $mfaRecoveryCodes[1], ['app_key' => '']) : [];
$mfaRecoveryRotateAgain = sr_member_mfa_rotate_recovery_codes($mfaPdo, 79, (int) ($mfaPendingActivate['factor_id'] ?? 0), 2, $mfaConfig);
$mfaRecoveryRotatedCodes = is_array($mfaRecoveryRotateAgain['codes'] ?? null) ? $mfaRecoveryRotateAgain['codes'] : [];
$mfaPasswordReauth = sr_member_mfa_management_reauth($mfaPdo, ['id' => 79, 'password_hash' => password_hash('mfa-pass', PASSWORD_DEFAULT)], 'mfa-pass', '', $mfaConfig);
$mfaBackupReauth = $mfaRecoveryRotatedCodes !== [] ? sr_member_mfa_management_reauth($mfaPdo, ['id' => 79, 'password_hash' => ''], '', (string) $mfaRecoveryRotatedCodes[0], $mfaConfig) : [];
$mfaDisable = sr_member_mfa_disable($mfaPdo, 79);
sr_member_auth_policy_assert(
    !empty($mfaRecoverySetup['rotated'])
        && count($mfaRecoveryCodes) === 3
        && preg_match('/\A[A-Z2-7]{4}(?:-[A-Z2-7]{4}){3}\z/', (string) ($mfaRecoveryCodes[0] ?? '')) === 1
        && !empty($mfaRecoveryConsume['verified'])
        && (int) ($mfaRecoveryConsume['remaining_unused'] ?? -1) === 2
        && empty($mfaRecoveryReplay['verified'])
        && empty($mfaRecoveryMissingKey['verified'])
        && (string) ($mfaRecoveryMissingKey['reason'] ?? '') === 'secret_unavailable'
        && !empty($mfaRecoveryRotateAgain['rotated'])
        && count($mfaRecoveryRotatedCodes) === 2
        && !empty($mfaPasswordReauth['verified'])
        && (string) ($mfaPasswordReauth['method'] ?? '') === 'password'
        && !empty($mfaBackupReauth['verified'])
        && (string) ($mfaBackupReauth['method'] ?? '') === 'backup'
        && !empty($mfaDisable['disabled'])
        && (int) $mfaPdo->query("SELECT COUNT(*) FROM sr_member_mfa_factors WHERE account_id = 79 AND status = 'active'")->fetchColumn() === 0
        && (int) $mfaPdo->query("SELECT COUNT(*) FROM sr_member_mfa_factors WHERE account_id = 79 AND status = 'disabled'")->fetchColumn() === 1
        && (int) $mfaPdo->query("SELECT COUNT(*) FROM sr_member_mfa_recovery_codes WHERE account_id = 79 AND status = 'unused'")->fetchColumn() === 0
        && (int) $mfaPdo->query("SELECT COUNT(*) FROM sr_member_mfa_recovery_codes WHERE account_id = 79 AND status = 'revoked'")->fetchColumn() === 3
        && (int) $mfaPdo->query("SELECT COUNT(*) FROM sr_member_mfa_recovery_codes WHERE account_id = 79 AND status = 'used'")->fetchColumn() === 2,
    'MFA recovery codes should be generated once, stored as hashes, consumed atomically, rotated by revoking unused codes, and revoked when MFA is disabled.'
);
$deletedMfa = sr_member_delete_mfa($mfaPdo, 77);
sr_member_auth_policy_assert(
    ($deletedMfa['factors_deleted'] ?? 0) === 2
        && ($deletedMfa['recovery_codes_deleted'] ?? 0) === 2
        && (int) $mfaPdo->query('SELECT COUNT(*) FROM sr_member_mfa_factors WHERE account_id = 78')->fetchColumn() === 1,
    'MFA delete helper should remove only the target account factors and recovery codes.'
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
        strpos($loginAction, 'sr_member_login_or_start_mfa($pdo, $account, \'password\', $next)') !== false
            && strpos($loginAction, "\$loginResult === 'mfa_required'") !== false
            && strpos($loginAction, "sr_redirect('/login/mfa')") !== false,
        'Password login should pass through the MFA gate before member session creation.'
    );
    sr_member_auth_policy_assert(
        strpos($loginAction, 'sr_member_mfa_login_setup_required($pdo, $account)') !== false
            && strpos($loginAction, 'sr_member_redirect_mfa_setup_required()') !== false,
        'Password login should send members without a required setup MFA factor to the security setup screen after session creation.'
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

$registerAction = sr_member_auth_policy_read('modules/member/actions/register.php');
if ($registerAction !== '') {
    sr_member_auth_policy_assert(
        strpos($registerAction, 'sr_member_login_or_start_mfa($pdo, $newAccount, \'register\', \'/account\')') !== false,
        'Registration auto-login should pass through the MFA gate.'
    );
    sr_member_auth_policy_assert(
        strpos($registerAction, 'sr_member_mfa_login_setup_required($pdo, $newAccount)') !== false
            && strpos($registerAction, 'sr_member_redirect_mfa_setup_required()') !== false,
        'Registration auto-login should send members without a required setup MFA factor to the security setup screen.'
    );
}

$oauthCallbackAction = sr_member_auth_policy_read('modules/member_oauth/actions/callback.php');
if ($oauthCallbackAction !== '') {
    sr_member_auth_policy_assert(
        strpos($oauthCallbackAction, 'sr_member_login_or_start_mfa($pdo, $account, \'oauth\', (string) $state[\'next_path\']') !== false,
        'OAuth callback login should pass through the MFA gate.'
    );
    sr_member_auth_policy_assert(
        strpos($oauthCallbackAction, 'sr_member_mfa_login_setup_required($pdo, $account)') !== false
            && strpos($oauthCallbackAction, 'sr_member_redirect_mfa_setup_required()') !== false,
        'OAuth callback login should send members without a required setup MFA factor to the security setup screen.'
    );
}

$oauthCompleteAction = sr_member_auth_policy_read('modules/member_oauth/actions/complete.php');
if ($oauthCompleteAction !== '') {
    sr_member_auth_policy_assert(
        strpos($oauthCompleteAction, 'sr_member_login_or_start_mfa($pdo, $account, \'oauth_completion\', (string) $usedState[\'next_path\']') !== false,
        'OAuth completion auto-login should pass through the MFA gate.'
    );
    sr_member_auth_policy_assert(
        strpos($oauthCompleteAction, 'sr_member_mfa_login_setup_required($pdo, $account)') !== false
            && strpos($oauthCompleteAction, 'sr_member_redirect_mfa_setup_required()') !== false,
        'OAuth completion auto-login should send members without a required setup MFA factor to the security setup screen.'
    );
}

$adminDashboardAction = sr_member_auth_policy_read('modules/admin/actions/dashboard.php');
if ($adminDashboardAction !== '') {
    sr_member_auth_policy_assert(
        strpos($adminDashboardAction, 'sr_member_require_login($pdo)') !== false
            && strpos($adminDashboardAction, 'sr_admin_require_permission($pdo, (int) $account[\'id\'], \'/admin\', \'view\')') !== false,
        'Admin dashboard should require a completed member login before admin permission checks.'
    );
}

$memberPaths = sr_member_auth_policy_read('modules/member/paths.php');
sr_member_auth_policy_assert(
    strpos($memberPaths, "'GET /login/mfa' => 'actions/login-mfa.php'") !== false
        && strpos($memberPaths, "'POST /login/mfa' => 'actions/login-mfa.php'") !== false,
    'Member paths should expose GET/POST /login/mfa.'
);

$accessHelper = sr_member_auth_policy_read('core/helpers/access.php');
sr_member_auth_policy_assert(
    strpos($accessHelper, "'GET /login/mfa' => true") !== false
        && strpos($accessHelper, "'POST /login/mfa' => true") !== false,
    'Member-only mode auth allowlist should include GET/POST /login/mfa.'
);

$loginMfaAction = sr_member_auth_policy_read('modules/member/actions/login-mfa.php');
if ($loginMfaAction !== '') {
    $identitySuccessPosition = strpos($loginMfaAction, "sr_member_log_auth(\$pdo, \$accountId, 'mfa_identity_success', 'success')");
    $identityRedirectPosition = $identitySuccessPosition === false
        ? false
        : strpos($loginMfaAction, 'sr_redirect(sr_member_safe_next_path($next))', $identitySuccessPosition);
    $identitySetupPosition = $identitySuccessPosition === false
        ? false
        : strpos($loginMfaAction, 'sr_member_mfa_login_setup_required($pdo, $challengeAccount)', $identitySuccessPosition);
    sr_member_auth_policy_assert(
        strpos($loginMfaAction, 'sr_member_mfa_throttle_status($pdo, $accountId)') !== false
            && strpos($loginMfaAction, 'sr_member_mfa_verify_totp_code($pdo, $accountId, $normalizedCode)') !== false
            && strpos($loginMfaAction, 'sr_member_mfa_consume_recovery_code($pdo, $accountId, $normalizedRecoveryCode)') !== false
            && strpos($loginMfaAction, '$loginSucceeded = sr_member_login($pdo, $challengeAccount)') !== false
            && strpos($loginMfaAction, 'sr_member_mfa_login_setup_required($pdo, $challengeAccount)') !== false
            && strpos($loginMfaAction, 'sr_member_redirect_mfa_setup_required()') !== false
            && strpos($loginMfaAction, 'sr_redirect(sr_member_safe_next_path($next))') !== false,
        'MFA login action should throttle MFA attempts, verify available factors, complete member login, send required setup members to security, and re-check next path before redirect.'
    );
    sr_member_auth_policy_assert(
        $identitySuccessPosition !== false
            && $identityRedirectPosition !== false
            && $identitySetupPosition !== false
            && $identitySetupPosition < $identityRedirectPosition,
        'Identity MFA success should send members without a required setup MFA factor to the security setup screen before the final next-path redirect.'
    );
    sr_member_auth_policy_assert(
        strpos($loginMfaAction, 'mfa_totp_success') !== false
            && strpos($loginMfaAction, 'mfa_totp_failure') !== false
            && strpos($loginMfaAction, 'mfa_backup_success') !== false
            && strpos($loginMfaAction, 'mfa_backup_failure') !== false
            && strpos($loginMfaAction, 'member.mfa.login.completed') !== false,
        'MFA login action should record auth and audit events for TOTP and backup-code success/failure.'
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
    sr_member_auth_policy_assert(
        strpos($throttleHelper, 'function sr_member_mfa_throttle_status') !== false
            && strpos($throttleHelper, 'function sr_member_mfa_failure_event_types') !== false
            && strpos($throttleHelper, 'member.mfa.account') !== false
            && strpos($throttleHelper, 'member.mfa.ip') !== false
            && strpos($throttleHelper, "'mfa_totp_failure'") !== false,
        'MFA throttle should track account and IP failures through rate limits or auth log fallback.'
    );
}

$settingsHelper = sr_member_auth_policy_read('modules/member/helpers/settings.php');
if ($settingsHelper !== '') {
    sr_member_auth_policy_assert(
        strpos($settingsHelper, 'function sr_member_profile_field_setting_keys') !== false
            && strpos($settingsHelper, 'profile_birth_date_enabled') !== false
            && strpos($settingsHelper, 'profile_is_adult_enabled') !== false
            && strpos($settingsHelper, 'profile_avatar_enabled') !== false
            && strpos($settingsHelper, 'profile_fields_json') !== false
            && strpos($settingsHelper, 'profile_phone_enabled') === false
            && strpos($settingsHelper, 'profile_text_enabled') === false,
        'Member settings helper should keep birth date, adult status, and avatar as fixed optional profile fields and store dynamic profile fields separately.'
    );
    sr_member_auth_policy_assert(
        strpos($settingsHelper, 'function sr_member_profile_field_settings') !== false
            && strpos($settingsHelper, 'function sr_member_profile_field_policies') !== false
            && strpos($settingsHelper, 'profile_birth_date_required') !== false
            && strpos($settingsHelper, 'profile_is_adult_required') !== false
            && strpos($settingsHelper, 'profile_avatar_required') !== false
            && strpos($settingsHelper, 'profile_phone_required') === false
            && strpos($settingsHelper, 'profile_text_required') === false,
        'Member settings helper should expose normalized fixed profile visibility and required policies.'
    );
    sr_member_auth_policy_assert(
        strpos($settingsHelper, 'profile_nickname_enabled') === false
            && strpos($settingsHelper, 'profile_nickname_required') === false,
        'Member settings helper should not keep legacy profile nickname policies.'
    );
}

$memberUpdateSql = '';
foreach (glob($root . '/modules/member/updates/*.sql') ?: [] as $updateFile) {
    $memberUpdateSql .= "\n" . sr_member_auth_policy_read(str_replace($root . '/', '', $updateFile));
}
$communityUpdateSql = '';
foreach (glob($root . '/modules/community/updates/*.sql') ?: [] as $updateFile) {
    $communityUpdateSql .= "\n" . sr_member_auth_policy_read(str_replace($root . '/', '', $updateFile));
}
$communityMembersHelper = sr_member_auth_policy_read('modules/community/helpers/members.php');
sr_member_auth_policy_assert(
    strpos($memberUpdateSql . $communityUpdateSql, 'profile_nickname_enabled') === false
        && strpos($memberUpdateSql . $communityUpdateSql, 'profile_nickname_required') === false
        && strpos($memberUpdateSql, 'profile_phone_enabled') === false
        && strpos($memberUpdateSql, 'profile_phone_required') === false
        && strpos($memberUpdateSql, 'profile_text_enabled') === false
        && strpos($memberUpdateSql, 'profile_text_required') === false
        && strpos($memberUpdateSql . $communityUpdateSql, 'member_profiles DROP COLUMN nickname') === false,
    'Member/community update SQL should not carry legacy fixed-profile setting cleanup residue.'
);
sr_member_auth_policy_assert(
    strpos($communityUpdateSql, 'community_member_nicknames') === false
        && strpos($communityMembersHelper, 'sr_community_member_nicknames_table_exists') === false,
    'Community should not keep the legacy temporary nickname handoff table or compatibility helper.'
);

$profileHelper = sr_member_auth_policy_read('modules/member/helpers/profile.php');
if ($profileHelper !== '') {
    sr_member_auth_policy_assert(
        strpos($profileHelper, "sr_post_string('nickname'") === false
            && strpos($profileHelper, "'nickname' =>") === false
            && strpos($profileHelper, 'SELECT nickname') === false,
        'Member profile helper should not accept, return, or read member-owned nicknames.'
    );
    sr_member_auth_policy_assert(
        strpos($profileHelper, "'phone' =>") === false
            && strpos($profileHelper, 'SELECT phone') === false
            && strpos($profileHelper, 'profile_text') === false,
        'Member profile helper should not keep legacy fixed phone or introduction fields.'
    );
    sr_member_auth_policy_assert(
        strpos($profileHelper, 'function sr_member_default_profile_extra_field_definitions') !== false
            && strpos($profileHelper, "'key' => 'phone'") !== false
            && strpos($profileHelper, "'label' => '휴대폰 번호'") !== false
            && strpos($profileHelper, 'sr_member_merge_default_profile_extra_field_definitions') !== false,
        'Member profile helper should expose phone as a default dynamic profile field.'
    );
}

$memberModule = sr_member_auth_policy_read('modules/member/module.php');
sr_member_auth_policy_assert(
    strpos($memberModule, '"key":"phone"') !== false
        && strpos($memberModule, '"label":"휴대폰 번호"') !== false,
    'Member module default settings should include phone in optional profile fields.'
);
sr_member_auth_policy_assert(
    strpos($memberModule, '\'profile_field_order_json\' => \'["extra:phone"]\'') !== false,
    'Member module default settings should sort phone first in optional profile fields.'
);
sr_member_auth_policy_assert(
    strpos($memberModule, '\'profile_field_order_json\' => \'["extra:phone"]\'') > strpos($memberModule, '"key":"phone"'),
    'Member module default profile order should be defined after the default phone field exists.'
);

$memberMfaProviders = sr_member_auth_policy_read('modules/member/member-mfa-providers.php');
$loginMfaView = sr_member_auth_policy_read('modules/member/views/login-mfa.php');
sr_member_auth_policy_assert(
    strpos($memberMfaProviders, "'label' => '본인인증'") !== false
        && strpos($loginMfaView, '본인인증으로 2차 인증을 완료할 수 있습니다.') !== false,
    'Member MFA identity provider should be labeled as 본인인증 in 2FA UI.'
);

$memberInstallSql = sr_member_auth_policy_read('modules/member/install.sql');
sr_member_auth_policy_assert(
    strpos($memberInstallSql, 'phone VARCHAR') === false
        && strpos($memberInstallSql, 'profile_text') === false,
    'Member install schema should not keep legacy fixed phone or introduction profile columns.'
);
sr_member_auth_policy_assert(
    strpos($memberInstallSql, 'CREATE TABLE IF NOT EXISTS sr_member_mfa_factors') !== false
        && strpos($memberInstallSql, 'CREATE TABLE IF NOT EXISTS sr_member_mfa_recovery_codes') !== false
        && strpos($memberUpdateSql, 'CREATE TABLE IF NOT EXISTS sr_member_mfa_factors') !== false
        && strpos($memberUpdateSql, 'CREATE TABLE IF NOT EXISTS sr_member_mfa_recovery_codes') !== false,
    'Member install and update schema should create MFA factors and recovery code tables.'
);

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
        strpos($sessionHelper, 'function sr_member_session_lifetime_seconds(PDO $pdo): int') !== false
            && strpos($sessionHelper, "function_exists('sr_member_settings')") !== false
            && strpos($sessionHelper, "function_exists('sr_module_metadata')") !== false
            && strpos($sessionHelper, "function_exists('sr_module_settings')") !== false
            && strpos($sessionHelper, 'catch (Throwable $exception)') !== false
            && strpos($sessionHelper, 'return 86400;') !== false,
        'Member session lifetime helper should fall back to the 86400 second default when settings helpers are unavailable or fail.'
    );
    sr_member_auth_policy_assert(
        strpos($sessionHelper, 'sr_member_create_session(PDO $pdo, int $accountId): string') !== false
            && strpos($sessionHelper, 'time() + sr_member_session_lifetime_seconds($pdo)') !== false,
        'Member session creation should keep its public signature and use the configured session lifetime.'
    );
    sr_member_auth_policy_assert(
        strpos($sessionHelper, 'SELECT id, expires_at, revoked_at, created_at, last_seen_at') !== false
            && strpos($sessionHelper, 'if ($storedExpiresAt < time())') !== false
            && strpos($sessionHelper, 'UPDATE sr_member_sessions SET expires_at = :expires_at, last_seen_at = :last_seen_at WHERE id = :id') !== false,
        'Member session current check should reject expired rows and extend active sessions from latest activity.'
    );
    sr_member_auth_policy_assert(
        strpos($sessionHelper, 'WHERE expires_at < :now') !== false
            && strpos($sessionHelper, 'created_before') === false,
        'Member session cleanup should delete expired rows without applying a created_at lifetime cap.'
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
        strpos($accountAction, "in_array(\$intent, ['basics', 'profile', 'password', 'mfa_totp_prepare', 'mfa_totp_activate', 'mfa_recovery_rotate', 'mfa_disable'], true)") !== false
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
        strpos($accountAction, "sr_member_mfa_create_pending_totp_factor(") !== false
            && strpos($accountAction, "sr_member_mfa_activate_pending_totp_factor(") !== false
            && strpos($accountAction, "sr_member_mfa_rotate_recovery_codes(") !== false
            && strpos($accountAction, "sr_member_mfa_management_reauth(") !== false
            && strpos($accountAction, "sr_member_mfa_disable(") !== false
            && strpos($accountAction, "'mfa_setup_reauth'") !== false
            && strpos($accountAction, "'mfa_manage_reauth'") !== false
            && strpos($accountAction, "sr_redirect(\$memberAccountBasePath . '/security')") !== false
            && strpos($accountAction, 'sr_member_mfa_setup_flash') !== false
            && strpos($accountAction, 'otpauth_qr_svg_data_uri') !== false
            && strpos($accountAction, 'sr_member_mfa_recovery_codes_flash') !== false,
        'Account security action should prepare, activate, rotate, and disable TOTP MFA factors with reauth, recovery code creation, and PRG flash handling.'
    );
    sr_member_auth_policy_assert(
        strpos($accountAction, '$hasPasswordLogin') !== false
            && strpos($accountAction, 'password_set') !== false
            && strpos($accountAction, 'member.password.set') !== false
            && strpos($accountAction, '$hasPasswordLogin && !password_verify') !== false,
        'Password action should allow password setup for accounts without an existing password while keeping current-password verification for password changes.'
    );
    sr_member_auth_policy_assert(
        strpos($accountAction, "sr_post_string_without_truncation('new_password', 255)") !== false
            && strpos($accountAction, "sr_post_string_without_truncation('new_password_confirm', 255)") !== false
            && strpos($accountAction, '$newPassword === null || $newPasswordConfirm === null') !== false,
        'Password change should reject overlong raw new-password inputs instead of truncating them.'
    );
    sr_member_auth_policy_assert(
        strpos($accountAction, '$profilePolicies = sr_member_profile_field_policies($memberSettings)') !== false
            && strpos($accountAction, '$profileExtraFieldDefinitions = sr_member_profile_extra_field_definitions($memberSettings)') !== false
            && strpos($accountAction, 'sr_member_profile_values_from_post($profilePolicies, $profile)') !== false
            && strpos($accountAction, 'sr_member_validate_profile_extra_field_values($profileExtraFieldDefinitions, $profileExtraFieldValues)') !== false
            && strpos($accountAction, "['validate_avatar' => false]") !== false
            && strpos($accountAction, 'sr_member_profile_validation_errors($profile, $profilePolicies)') !== false,
        'Account action should update avatar and validate dynamic profile fields through normalized profile policies.'
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
    sr_member_auth_policy_assert(
        strpos($withdrawAction, 'sr_member_delete_mfa($pdo, (int) $account[\'id\'])') !== false
            && strpos($withdrawAction, "'deleted_mfa' => \$deletedMfa") !== false,
        'Withdraw should delete member-owned MFA factors and recovery codes before account anonymization.'
    );
}

$adminMembersHelper = sr_member_auth_policy_read('modules/member/helpers/admin-members.php');
if ($adminMembersHelper !== '') {
    sr_member_auth_policy_assert(
        strpos($adminMembersHelper, 'function sr_admin_member_apply_status_effects(') !== false
            && strpos($adminMembersHelper, 'sr_member_delete_mfa($pdo, $accountId)') !== false
            && strpos($adminMembersHelper, 'sr_member_anonymize_account($pdo, $config, $accountId)') !== false
            && strpos($adminMembersHelper, 'sr_member_record_consent_withdrawals($pdo, $accountId)') !== false
            && strpos($adminMembersHelper, "sr_member_run_privacy_cleanup_contracts(\$pdo, \$accountId, 'member.status_' . \$afterStatus)") !== false,
        'Admin member status changes to withdrawn/anonymized should remove member-owned private data before module privacy cleanup.'
    );
    sr_member_auth_policy_assert(
        strpos($adminMembersHelper, 'function sr_admin_member_withdrawal_asset_warning(') !== false
            && strpos($adminMembersHelper, 'sr_member_withdrawal_asset_balances($pdo, $accountId)') !== false
            && strpos($adminMembersHelper, 'function sr_admin_member_terminal_asset_followup(') !== false
            && strpos($adminMembersHelper, "'terminal_asset_followup'") !== false
            && strpos($adminMembersHelper, 'sr_member_process_asset_withdrawal(') === false,
        'Admin member status warnings should show withdrawal asset balances and provide follow-up lookup links without processing assets.'
    );
}

$adminMembersAction = sr_member_auth_policy_read('modules/member/actions/admin-members.php');
$adminMembersView = sr_member_auth_policy_read('modules/member/views/admin-members.php');
if ($adminMembersAction !== '' && $adminMembersView !== '') {
    sr_member_auth_policy_assert(
        strpos($adminMembersAction, '$memberWithdrawalAssetWarnings[$memberAccountId] = sr_admin_member_withdrawal_asset_warning($pdo, $memberAccountId);') !== false
            && strpos($adminMembersAction, '$memberEditWithdrawalAssetWarning = sr_admin_member_withdrawal_asset_warning($pdo, (int) $editMember[\'id\']);') !== false
            && strpos($adminMembersAction, '$postResultData = isset($postResult[\'data\']) && is_array($postResult[\'data\']) ? $postResult[\'data\'] : [];') !== false
            && strpos($adminMembersView, '현재 조회된 보유 자산:') !== false
            && strpos($adminMembersView, '관리자 탈퇴/익명화는 현재 보유 자산을 자동 정산하지 않습니다.') !== false
            && strpos($adminMembersView, 'admin-member-terminal-followup') !== false
            && strpos($adminMembersView, 'name="intent" value="evaluate_groups"') !== false
            && strpos($adminMembersView, "\$memberEditHasActionContext = \$memberAdminPage === 'edit_form'") !== false
            && strpos($adminMembersView, 'admin-member-edit-actions') !== false
            && strpos($adminMembersView, 'admin-member-edit-action-group-normal') !== false
            && strpos($adminMembersView, 'admin-member-edit-action-group-risk') !== false
            && strpos($adminMembersView, 'member-risk-modal-') !== false
            && strpos($adminMembersView, 'aria-label="위험작업" title="위험작업"') !== false
            && strpos($adminMembersView, 'admin-member-risk-actions') !== false
            && strpos($adminMembersView, '$memberEditCanEvaluateGroups = $memberEditHasActionContext && !in_array($memberEditStatus, $memberTerminalStatuses, true);') !== false
            && strpos($adminMembersView, '<span>회원 차단</span>') !== false
            && strpos($adminMembersView, 'form="<?php echo sr_e($memberEditActionFormPrefix . \'evaluate-groups\'); ?>"') !== false
            && strpos($adminMembersHelper, "탈퇴/익명화 회원은 그룹 규칙을 재평가하지 않습니다.") !== false
            && strpos($adminMembersView, 'data-member-withdraw-confirm') !== false
            && strpos($adminMembersView, 'data-member-anonymize-confirm') !== false,
        'Admin member withdrawn/anonymized confirmations should show current asset balances, hide terminal group reevaluation on list/edit screens, and successful terminal updates should keep follow-up lookup data.'
    );
}

$messagePrivacyCleanup = sr_member_auth_policy_read('modules/message/privacy-cleanup.php');
if ($messagePrivacyCleanup !== '') {
    sr_member_auth_policy_assert(
        strpos($messagePrivacyCleanup, 'sender_account_id = :sender_account_id') !== false
            && strpos($messagePrivacyCleanup, 'recipient_account_id = :recipient_account_id') !== false
            && strpos($messagePrivacyCleanup, "'sender_account_id' => \$accountId") !== false
            && strpos($messagePrivacyCleanup, "'recipient_account_id' => \$accountId") !== false,
        'Message privacy cleanup should use distinct PDO placeholders for sender and recipient account id.'
    );
}

$adminAssetLedgersHelper = sr_member_auth_policy_read('modules/admin/helpers/asset-ledgers.php');
if ($adminAssetLedgersHelper !== '') {
    sr_member_auth_policy_assert(
        strpos($adminAssetLedgersHelper, 'SELECT id, email, display_name, status FROM sr_member_accounts WHERE id = :id LIMIT 1') !== false
            && strpos($adminAssetLedgersHelper, 'INNER JOIN sr_member_accounts a ON a.id = b.account_id') !== false
            && strpos($adminAssetLedgersHelper, "a.status = 'active'") === false,
        'Admin asset ledgers should remain searchable for withdrawn/anonymized member accounts by account id/public hash.'
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
            && strpos($privacyOrchestrationHelper, 'function sr_privacy_module_export_results') !== false
            && strpos($privacyOrchestrationHelper, "'module_export_status' => \$moduleExportResults['status']") !== false
            && strpos($privacyOrchestrationHelper, "'partial_export' => \$moduleExportResults['partial_export']") !== false
            && strpos($privacyOrchestrationHelper, 'function sr_privacy_export_evidence_id') !== false
            && strpos($privacyOrchestrationHelper, "'error_code' => 'module_export_exception'") !== false
            && strpos($privacyOrchestrationHelper, "sr_log_exception(\$exception, 'privacy_export_module_' . \$moduleKey . '_evidence_' . \$evidenceId)") !== false
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
    sr_member_auth_policy_assert(
        strpos($accountView, 'name="intent" value="mfa_totp_prepare"') !== false
            && strpos($accountView, 'name="intent" value="mfa_totp_activate"') !== false
            && strpos($accountView, 'name="intent" value="mfa_recovery_rotate"') !== false
            && strpos($accountView, 'name="intent" value="mfa_disable"') !== false
            && strpos($accountView, 'name="mfa_code"') !== false
            && strpos($accountView, 'member::ui.mfa_totp.secret') !== false
            && strpos($accountView, 'member-skin-basic-mfa-qr') !== false
            && strpos($accountView, 'member::ui.mfa_totp.qr_alt') !== false
            && strpos($accountView, '$memberMfaRecoveryCodes') !== false
            && strpos($accountView, 'member::ui.mfa_recovery.once_help') !== false
            && strpos($accountView, 'member::ui.mfa_totp.reauth_code') !== false,
        'Account security view should expose TOTP setup, QR image, manual secret, first-code activation, recovery rotation, disable controls, and one-time recovery code display.'
    );
}

$memberSkinCss = sr_member_auth_policy_read('modules/member/skins/basic/skin.css');
if ($memberSkinCss !== '') {
    sr_member_auth_policy_assert(
        strpos($memberSkinCss, '.member-skin-basic-mfa-qr') !== false
            && strpos($memberSkinCss, 'aspect-ratio: 1') !== false,
        'Member basic skin should keep the MFA QR image square and namespaced.'
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
            && strpos($adminPrivacyRequestsHelper, '종결된 개인정보 요청 대응 기록 상태는 다시 변경할 수 없습니다.') !== false,
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
            && strpos($adminPrivacyRequestsHelper, "sr_privacy_export_evidence_id('privacy_request_account_data', (int) \$privacyRequest['id'], \$exportedAt)") !== false
            && strpos($adminPrivacyRequestsHelper, "sr_log_exception(\$exception, 'privacy_request_export_account_' . (int) \$privacyRequest['id'] . '_evidence_' . \$evidenceId)") !== false
            && strpos($adminPrivacyRequestsHelper, "\$export['account_data_unavailable'] = true") !== false
            && strpos($adminPrivacyRequestsHelper, "\$export['account_data_status'] = [") !== false,
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
        strpos($adminPrivacyRequestsView, '요청 유형은 접수 분류입니다.') !== false
            && strpos($adminPrivacyRequestsView, '상태 변경은 요청 대응 이력만 저장합니다.') !== false
            && strpos($adminPrivacyRequestsView, '정정, 삭제, 처리 제한, 처리 거부, 동의 철회는 데이터 소유 모듈에서 처리') !== false,
        'Admin privacy request view should explain that request status does not automatically propagate module actions.'
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
$registrationHelper = sr_member_auth_policy_read('modules/member/helpers/registration.php');
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
        strpos($registerAction, 'sr_member_login_or_start_mfa($pdo, $newAccount, \'register\', \'/account\')') !== false
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
    $registerConsent = strpos($registerAction, 'sr_member_record_registration_policy_consents($pdo, $accountId, $transactionPolicyDocuments, $registrationConsentValues);');
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
        strpos($registerAction, '$registrationConsentValues = sr_member_registration_policy_consent_values_from_post($registrationPolicyDocuments);') !== false
            && strpos($registerAction, 'sr_member_record_registration_policy_consents($pdo, $accountId, $transactionPolicyDocuments, $registrationConsentValues);') !== false
            && strpos($registrationHelper, "foreach (['terms', 'privacy', 'marketing'] as \$consentKey)") !== false
            && strpos($registrationHelper, 'if (!is_array($documents[$consentKey] ?? null))') !== false,
        'Register action should record optional marketing consent history only when the optional policy document is available.'
    );
    sr_member_auth_policy_assert(
        strpos($registerAction, 'sr_member_registration_policy_documents($pdo)') !== false
            && strpos($registerAction, '$transactionPolicyDocumentState = sr_member_registration_policy_documents($pdo);') !== false,
        'Register action should load current policy documents from the server for display and again inside the account transaction.'
    );
    sr_member_auth_policy_assert(
        strpos($registrationHelper, "if (!sr_module_enabled(\$pdo, 'policy_documents') || !is_file(SR_ROOT . '/modules/policy_documents/helpers.php'))") !== false
            && strpos($registrationHelper, "if (\$documentKey === '' || !sr_module_enabled(\$pdo, 'policy_documents') || !is_file(SR_ROOT . '/modules/policy_documents/helpers.php'))") !== false,
        'Registration policy document helpers should only load policy_documents helpers when the module is enabled.'
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
        strpos($registerView, 'sr_member_registration_policy_consent_section_html($registrationPolicyDocuments, $registrationConsentValues ?? [], \'register\')') !== false
            && strpos($registrationHelper, 'name="' . "' . sr_e(\$postKey) . '" . '"') !== false
            && strpos($registrationHelper, '$checked = !empty($consentValues[$consentKey]);') !== false,
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
            && strpos($adminSettingsAction, 'sr_member_profile_extra_field_definitions_json_from_input($profileFieldsInput)') !== false
            && strpos($adminSettingsAction, 'profile_removed_field_values_confirmed') !== false
            && strpos($adminSettingsAction, 'sr_member_delete_profile_extra_field_values_by_keys($pdo, $removedProfileExtraFieldKeys)') !== false
            && strpos($adminSettingsAction, "'profile_fields' => sr_member_profile_field_policies(\$settings)") !== false,
        'Member settings action should save avatar visibility/required settings, dynamic profile fields, confirm removed extra field cleanup, and audit them.'
    );
    sr_member_auth_policy_assert(
        strpos($adminSettingsAction, 'sr_admin_post_int_in_range($key, (int) $limits[\'min\'], (int) $limits[\'max\'])') !== false
            && strpos($adminSettingsAction, '$integerValue === null') !== false
            && strpos($adminSettingsAction, '$settings[$key] = $integerValue;') !== false
            && strpos($adminSettingsAction, 'sr_member_clamp_int((int) $rawValue') === false,
        'Member settings action should reject out-of-range integer settings instead of truncating or clamping submitted values.'
    );
    sr_member_auth_policy_assert(
        strpos($adminSettingsAction, 'array_keys(sr_member_integer_setting_keys())') !== false
            && strpos($adminSettingsAction, "'integer_settings' => array_reduce(") !== false,
        'Member settings audit metadata should include the generic integer settings snapshot.'
    );
    sr_member_auth_policy_assert(
        strpos($adminSettingsAction, '$memberIdentityRegistrationAvailable') !== false
            && strpos($adminSettingsAction, '$memberIdentityWithdrawalAvailable') !== false
            && strpos($adminSettingsAction, '$memberIdentityAccountSecurityAvailable') !== false
            && strpos($adminSettingsAction, '회원가입 본인확인을 사용하려면 본인확인 사용을 켜고 회원가입 목적을 지원하는 제공자를 설정하세요.') !== false
            && strpos($adminSettingsAction, '회원탈퇴 본인확인을 사용하려면 본인확인 사용을 켜고 회원탈퇴 목적을 지원하는 제공자를 설정하세요.') !== false
            && strpos($adminSettingsAction, '계정보안작업 본인확인을 사용하려면 본인확인 사용을 켜고 계정 보안 목적을 지원하는 제공자를 설정하세요.') !== false
            && strpos($adminSettingsAction, "\$settings['identity_registration_mode'] = 'disabled';") !== false
            && strpos($adminSettingsAction, "\$settings['identity_withdrawal_required'] = false;") !== false
            && strpos($adminSettingsAction, "\$settings['identity_account_security_required'] = false;") !== false,
        'Member settings action should reject and normalize identity settings when identity verification is unavailable for each purpose.'
    );
}

foreach (glob(SR_ROOT . '/modules/member/updates/*.sql') ?: [] as $memberUpdateSqlPath) {
    $memberUpdateSql = sr_member_auth_policy_read(str_replace(SR_ROOT . '/', '', $memberUpdateSqlPath));
    sr_member_auth_policy_assert(
        strpos($memberUpdateSql, 'login_identifier') === false,
        'Member update SQL should not keep migration-only login_identifier value rewrites: ' . str_replace(SR_ROOT . '/', '', $memberUpdateSqlPath)
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
            && strpos($adminSettingsView, '$requiredKey') !== false
            && strpos($adminSettingsView, 'data-member-profile-extra-fields-builder') !== false
            && strpos($adminSettingsView, 'data-member-profile-extra-field-modal') !== false
            && strpos($adminSettingsView, 'data-member-profile-original-extra-field-keys-json') !== false
            && strpos($adminSettingsView, 'data-member-profile-removed-field-values-confirmed') !== false,
        'Member settings view should expose avatar visibility/required settings, dynamic profile field builder, and removed field value cleanup confirmation.'
    );
    sr_member_auth_policy_assert(
        strpos($adminSettingsView, '$memberIdentityRegistrationAvailable') !== false
            && strpos($adminSettingsView, '$memberIdentityWithdrawalAvailable') !== false
            && strpos($adminSettingsView, '$memberIdentityAccountSecurityAvailable') !== false
            && strpos($adminSettingsView, 'member-settings-identity-unavailable') !== false
            && strpos($adminSettingsView, 'form-help-warning') !== false
            && strpos($adminSettingsView, '본인확인 사용이 꺼져 있거나 목적에 맞는 제공자가 준비되지 않은 항목은 사용할 수 없습니다.') !== false
            && strpos($adminSettingsView, 'disabled aria-describedby="member-settings-identity-unavailable"') !== false,
        'Member settings view should disable identity verification controls and show an unavailable notice when identity verification is unavailable for each purpose.'
    );
}

$accountView = sr_member_auth_policy_read('modules/member/views/account.php');
if ($accountView !== '') {
    sr_member_auth_policy_assert(
        strpos($accountView, 'if ($profileFieldsEnabled)') !== false
            && strpos($accountView, "if (!empty(\$profilePolicies['birth_date']['visible']))") !== false
            && strpos($accountView, "if (!empty(\$profilePolicies['is_adult']['visible']))") !== false
            && strpos($accountView, "if (!empty(\$profilePolicies['avatar_path']['visible']))") !== false
            && strpos($accountView, "if (!empty(\$profilePolicies['phone']['visible']))") === false
            && strpos($accountView, "if (!empty(\$profilePolicies['profile_text']['visible']))") === false
            && strpos($accountView, 'sr_member_profile_extra_fields_form_html') !== false
            && strpos($accountView, 'name="avatar_file"') !== false
            && strpos($accountView, '$memberAccountHasPassword') !== false
            && strpos($accountView, '비밀번호 설정') !== false,
        'Account view should render birth date, adult status, avatar, dynamic profile fields, and password setup without legacy phone/introduction inputs.'
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
        strpos($loginAction, "\$loginResult === 'logged_in'") !== false
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
