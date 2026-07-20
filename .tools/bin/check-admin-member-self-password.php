#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);

$fixtureAuditLogs = [];
$fixtureAuthLogs = [];
$fixtureSecurityNotifications = [];
$fixtureRevokedSessions = 0;
$fixtureRotatedSessions = 0;

function sr_post_string(string $key, int $maxLength = 0): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }

    return $maxLength > 0 ? substr(trim($value), 0, $maxLength) : trim($value);
}

function sr_post_string_without_truncation(string $key, int $maxLength): ?string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) && strlen($value) <= $maxLength ? $value : null;
}

function sr_admin_post_positive_int(string $key): int
{
    $value = $_POST[$key] ?? '';
    return is_string($value) && preg_match('/\A[1-9][0-9]*\z/', $value) === 1 ? (int) $value : 0;
}

function sr_t(string $key, array $replace = []): string
{
    return [
        'member::action.admin.self_password_only' => 'self password only',
        'member::action.account.current_password_invalid' => 'current password invalid',
        'member::action.password.new_too_long' => 'new password too long',
        'member::action.password.new_too_short' => 'new password too short',
        'member::action.password.new_confirm_mismatch' => 'new password mismatch',
        'member::action.admin.updated' => 'member updated',
        'member::action.admin.update_failed' => 'member update failed',
    ][$key] ?? $key;
}

function sr_admin_action_result(array $errors = [], string $notice = '', array $data = []): array
{
    return ['errors' => $errors, 'notice' => $notice, 'data' => $data];
}

function sr_admin_current_roles(PDO $pdo, int $accountId): array
{
    return ['owner'];
}

function sr_admin_is_owner(PDO $pdo, int $accountId): bool
{
    return true;
}

function sr_admin_active_owner_count(PDO $pdo): int
{
    return 2;
}

function sr_runtime_config(): array
{
    return ['app_key' => 'fixture'];
}

function sr_supported_locales(?array $site = null): array
{
    return ['ko'];
}

function sr_normalize_identifier(string $value): string
{
    return strtolower(trim($value));
}

function sr_member_settings(PDO $pdo): array
{
    return ['nickname_enabled' => false, 'nickname_required' => false];
}

function sr_member_profile_extra_field_definitions(array $settings): array
{
    return [];
}

function sr_member_profile_extra_field_plain_values(PDO $pdo, int $accountId): array
{
    return [];
}

function sr_member_profile_extra_field_input_values(array $definitions): array
{
    return [];
}

function sr_member_validate_profile_extra_field_values(array $definitions, array $values): array
{
    return [];
}

function sr_member_normalize_display_name(string $value): string
{
    return trim($value);
}

function sr_member_normalize_nickname(string $value): string
{
    return trim($value);
}

function sr_member_display_name_validation_errors(string $value): array
{
    return $value === '' ? ['display name required'] : [];
}

function sr_member_nickname_validation_errors(PDO $pdo, string $nickname, array $settings, int $accountId = 0): array
{
    return [];
}

function sr_member_reauth_throttle_status(PDO $pdo, int $accountId): array
{
    return ['limited' => false, 'reason' => ''];
}

function sr_member_log_auth(PDO $pdo, ?int $accountId, string $eventType, string $result): void
{
    global $fixtureAuthLogs;
    $fixtureAuthLogs[] = compact('accountId', 'eventType', 'result');
}

function sr_hmac_hash(string $value, array $config): string
{
    return hash('sha256', $value);
}

function sr_now(): string
{
    return '2026-07-20 12:00:00';
}

function sr_member_set_nickname(PDO $pdo, int $accountId, string $nickname): void
{
}

function sr_member_delete_nickname(PDO $pdo, int $accountId): void
{
    $stmt = $pdo->prepare('DELETE FROM sr_member_nicknames WHERE account_id = :account_id');
    $stmt->execute(['account_id' => $accountId]);
}

function sr_member_update_password(PDO $pdo, int $accountId, string $password): void
{
    $stmt = $pdo->prepare('UPDATE sr_member_accounts SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'updated_at' => sr_now(),
        'id' => $accountId,
    ]);
}

function sr_member_revoke_other_sessions(PDO $pdo, int $accountId): int
{
    global $fixtureRevokedSessions;
    $fixtureRevokedSessions++;
    return 2;
}

function sr_member_rotate_current_session(PDO $pdo, int $accountId): bool
{
    global $fixtureRotatedSessions;
    $fixtureRotatedSessions++;
    $_SESSION['sr_session_token_hash'] = str_repeat('a', 64);
    return true;
}

function sr_member_create_security_notification(PDO $pdo, int $accountId, string $eventKey, array $metadata = [], ?int $createdByAccountId = null): bool
{
    global $fixtureSecurityNotifications;
    $fixtureSecurityNotifications[] = compact('accountId', 'eventKey');
    return true;
}

function sr_member_account_access_remember_credential(int $accountId, string $sessionTokenHash, string $method): void
{
}

function sr_member_account_access_complete(int $accountId, string $sessionTokenHash): void
{
}

function sr_audit_log(PDO $pdo, array $event): void
{
    global $fixtureAuditLogs;
    $fixtureAuditLogs[] = $event;
}

require_once $root . '/modules/member/helpers/admin-members.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec(
    'CREATE TABLE sr_member_accounts (
        id INTEGER PRIMARY KEY,
        account_identifier_hash TEXT NOT NULL,
        email TEXT NOT NULL,
        email_hash TEXT NOT NULL,
        login_id_hash TEXT NULL,
        password_hash TEXT NOT NULL,
        display_name TEXT NOT NULL,
        locale TEXT NOT NULL,
        status TEXT NOT NULL,
        email_verified_at TEXT NULL,
        updated_at TEXT NOT NULL
    )'
);
$pdo->exec('CREATE TABLE sr_member_nicknames (account_id INTEGER PRIMARY KEY, nickname TEXT NOT NULL)');

$insert = $pdo->prepare(
    'INSERT INTO sr_member_accounts
        (id, account_identifier_hash, email, email_hash, login_id_hash, password_hash, display_name, locale, status, email_verified_at, updated_at)
     VALUES
        (:id, :account_identifier_hash, :email, :email_hash, NULL, :password_hash, :display_name, :locale, :status, :email_verified_at, :updated_at)'
);
foreach ([1 => 'owner@example.test', 2 => 'other@example.test'] as $accountId => $email) {
    $emailHash = hash('sha256', $email);
    $insert->execute([
        'id' => $accountId,
        'account_identifier_hash' => $emailHash,
        'email' => $email,
        'email_hash' => $emailHash,
        'password_hash' => password_hash('old-password', PASSWORD_DEFAULT),
        'display_name' => $accountId === 1 ? 'Owner' : 'Other',
        'locale' => 'ko',
        'status' => 'active',
        'email_verified_at' => sr_now(),
        'updated_at' => sr_now(),
    ]);
}

$basePost = static function (int $accountId, string $email, string $displayName): array {
    return [
        'intent' => 'edit',
        'account_id' => (string) $accountId,
        'email' => $email,
        'display_name' => $displayName,
        'nickname' => '',
        'locale' => 'ko',
        'status' => 'active',
        'email_verified' => '1',
    ];
};
$passwordHash = static function (int $accountId) use ($pdo): string {
    $stmt = $pdo->prepare('SELECT password_hash FROM sr_member_accounts WHERE id = :id');
    $stmt->execute(['id' => $accountId]);
    return (string) $stmt->fetchColumn();
};
$actor = ['id' => 1, 'password_hash' => $passwordHash(1)];

$_POST = $basePost(2, 'other@example.test', 'Other');
$_POST['new_password'] = 'forged-password';
$_POST['new_password_confirm'] = 'forged-password';
$beforeOtherHash = $passwordHash(2);
$result = sr_admin_handle_members_post($pdo, $actor, sr_admin_member_allowed_statuses(), ['supported_locales' => ['ko']]);
$assert(in_array('self password only', $result['errors'], true), 'Another member password payload must be rejected.');
$assert(hash_equals($beforeOtherHash, $passwordHash(2)), 'Rejected another-member payload must not change the password hash.');
$assert(array_filter($fixtureAuditLogs, static fn (array $event): bool => ($event['event_type'] ?? '') === 'member.password.change.denied') !== [], 'Rejected another-member payload must leave an audit event.');

$_POST = $basePost(1, 'owner@example.test', 'Owner');
$_POST['current_password'] = 'wrong-password';
$_POST['new_password'] = 'new-password-1';
$_POST['new_password_confirm'] = 'new-password-1';
$beforeSelfHash = $passwordHash(1);
$result = sr_admin_handle_members_post($pdo, $actor, sr_admin_member_allowed_statuses(), ['supported_locales' => ['ko']]);
$assert(in_array('current password invalid', $result['errors'], true), 'Existing-password self edit must verify the current password.');
$assert(hash_equals($beforeSelfHash, $passwordHash(1)), 'Invalid current password must not change the password hash.');

$_POST = $basePost(1, 'owner@example.test', 'Owner');
$_POST['current_password'] = 'old-password';
$_POST['new_password'] = 'new-password-1';
$_POST['new_password_confirm'] = 'different-password';
$result = sr_admin_handle_members_post($pdo, $actor, sr_admin_member_allowed_statuses(), ['supported_locales' => ['ko']]);
$assert(in_array('new password mismatch', $result['errors'], true), 'Self edit must reject a mismatched new-password confirmation.');
$assert(hash_equals($beforeSelfHash, $passwordHash(1)), 'Mismatched confirmation must not change the password hash.');

$_POST = $basePost(1, 'owner@example.test', 'Owner');
$_POST['current_password'] = 'old-password';
$_POST['new_password'] = 'new-password-1';
$_POST['new_password_confirm'] = 'new-password-1';
$result = sr_admin_handle_members_post($pdo, $actor, sr_admin_member_allowed_statuses(), ['supported_locales' => ['ko']]);
$assert($result['errors'] === [], 'Valid current-account password change must succeed.');
$assert(password_verify('new-password-1', $passwordHash(1)), 'Valid current-account password change must persist the new hash.');
$assert($fixtureRevokedSessions === 1 && $fixtureRotatedSessions === 1, 'Successful password change must revoke other sessions and rotate the current session.');
$assert(array_filter($fixtureSecurityNotifications, static fn (array $event): bool => ($event['eventKey'] ?? '') === 'security.password_changed') !== [], 'Successful password change must create a security notification.');
$assert(array_filter($fixtureAuditLogs, static fn (array $event): bool => ($event['event_type'] ?? '') === 'member.password.changed') !== [], 'Successful password change must leave a dedicated audit event.');

$pdo->prepare('UPDATE sr_member_accounts SET password_hash = :password_hash WHERE id = 1')->execute(['password_hash' => '']);
$actor = ['id' => 1, 'password_hash' => ''];
$_POST = $basePost(1, 'owner@example.test', 'Owner');
$_POST['new_password'] = 'first-password';
$_POST['new_password_confirm'] = 'first-password';
$result = sr_admin_handle_members_post($pdo, $actor, sr_admin_member_allowed_statuses(), ['supported_locales' => ['ko']]);
$assert($result['errors'] === [], 'Passwordless current account must be able to set its first password.');
$assert(password_verify('first-password', $passwordHash(1)), 'Passwordless current account must persist its first password.');
$assert(array_filter($fixtureAuditLogs, static fn (array $event): bool => ($event['event_type'] ?? '') === 'member.password.set') !== [], 'First password setup must leave the password-set audit event.');

$_POST = [];
if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, '[FAIL] ' . $error . PHP_EOL);
    }
    exit(1);
}

echo "Admin member self password checks completed.\n";
