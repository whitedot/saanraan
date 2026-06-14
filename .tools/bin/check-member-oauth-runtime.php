#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/helpers.php';
require_once $root . '/modules/member/helpers.php';
require_once $root . '/modules/member_oauth/helpers.php';

$errors = [];

function sr_member_oauth_check_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

function sr_member_oauth_check_read(string $path): string
{
    global $root, $errors;

    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content)) {
        $errors[] = 'cannot read ' . $path;
        return '';
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
}

function sr_member_oauth_check_contains(string $path, array $markers): void
{
    $content = sr_member_oauth_check_read($path);
    foreach ($markers as $marker) {
        sr_member_oauth_check_assert(
            str_contains($content, $marker),
            $path . ' is missing marker: ' . $marker
        );
    }
}

function sr_member_oauth_check_forbids(string $path, array $markers): void
{
    $content = sr_member_oauth_check_read($path);
    foreach ($markers as $marker) {
        sr_member_oauth_check_assert(
            !str_contains($content, $marker),
            $path . ' must not contain marker: ' . $marker
        );
    }
}

function sr_member_oauth_check_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(
        'CREATE TABLE sr_member_oauth_states (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            state_hash TEXT NOT NULL,
            nonce_hash TEXT NOT NULL DEFAULT "",
            code_verifier_hash TEXT NOT NULL DEFAULT "",
            provider_key TEXT NOT NULL,
            flow_type TEXT NOT NULL,
            account_id INTEGER NULL,
            next_path TEXT NOT NULL DEFAULT "/",
            provider_subject_hash TEXT NOT NULL DEFAULT "",
            provider_subject_display TEXT NOT NULL DEFAULT "",
            email_snapshot TEXT NOT NULL DEFAULT "",
            email_verified_snapshot INTEGER NOT NULL DEFAULT 0,
            display_name_snapshot TEXT NOT NULL DEFAULT "",
            issued_at TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            used_at TEXT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_member_oauth_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            provider_key TEXT NOT NULL,
            provider_subject_hash TEXT NOT NULL,
            provider_subject_display TEXT NOT NULL DEFAULT "",
            email_snapshot TEXT NOT NULL DEFAULT "",
            email_verified_snapshot INTEGER NOT NULL DEFAULT 0,
            display_name_snapshot TEXT NOT NULL DEFAULT "",
            linked_at TEXT NOT NULL,
            last_login_at TEXT NULL,
            revoked_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );

    return $pdo;
}

function sr_member_oauth_check_runtime_helpers(): void
{
    $pdo = sr_member_oauth_check_pdo();
    $config = ['app_key' => str_repeat('a', 32)];

    $state = sr_member_oauth_create_state($pdo, 'mock', 'login', null, '/account', 120);
    sr_member_oauth_check_assert(isset($state['state'], $state['nonce'], $state['code_verifier'], $state['code_challenge']), 'OAuth state helper should return state, nonce, verifier, and challenge.');

    $stored = $pdo->query('SELECT * FROM sr_member_oauth_states LIMIT 1')->fetch();
    sr_member_oauth_check_assert(is_array($stored), 'OAuth state should be stored.');
    sr_member_oauth_check_assert((string) $stored['state_hash'] === sr_member_oauth_hash((string) $state['state']), 'OAuth state should be stored as a hash.');
    sr_member_oauth_check_assert((string) $stored['state_hash'] !== (string) $state['state'], 'OAuth raw state must not be stored.');
    sr_member_oauth_check_assert((string) $stored['code_verifier_hash'] !== (string) $state['code_verifier'], 'OAuth raw PKCE verifier must not be stored.');

    $consumed = sr_member_oauth_consume_state($pdo, (string) $state['state'], 'mock', 'login');
    sr_member_oauth_check_assert(is_array($consumed), 'OAuth state should be consumed once.');
    sr_member_oauth_check_assert(sr_member_oauth_consume_state($pdo, (string) $state['state'], 'mock', 'login') === null, 'OAuth state reuse should be rejected.');

    $linkState = sr_member_oauth_create_state($pdo, 'mock', 'link', 7, '/account', 120);
    $linkPreview = sr_member_oauth_state_by_token($pdo, (string) $linkState['state'], 'link');
    sr_member_oauth_check_assert(is_array($linkPreview) && (int) $linkPreview['account_id'] === 7, 'OAuth link state should bind account id.');

    $subjectHash = sr_member_oauth_subject_hash($config, 'mock', 'provider-subject');
    sr_member_oauth_check_assert($subjectHash !== hash('sha256', 'mock:provider-subject'), 'Provider subject hash should use keyed HMAC.');
    $oauthAccountId = sr_member_oauth_link_account($pdo, 7, 'mock', $subjectHash, [
        'subject_display' => 'mock-user',
        'email' => 'mock-user@example.test',
        'email_verified' => true,
        'display_name' => 'mock_user',
    ]);
    sr_member_oauth_check_assert($oauthAccountId > 0, 'OAuth account link should be created.');
    sr_member_oauth_check_assert(sr_member_oauth_account_by_subject($pdo, 'mock', $subjectHash) !== null, 'OAuth account should be found by subject hash.');
    sr_member_oauth_check_assert(sr_member_oauth_account_for_provider($pdo, 7, 'mock') !== null, 'OAuth account should be found by account/provider.');

    $activeAccounts = sr_member_oauth_accounts_for_account($pdo, 7);
    sr_member_oauth_check_assert(count($activeAccounts) === 1, 'Active OAuth account list should include linked provider.');
    sr_member_oauth_check_assert(!sr_member_oauth_can_unlink(['password_hash' => ''], $activeAccounts), 'Last OAuth login method should not be unlinkable without a password.');
    sr_member_oauth_check_assert(sr_member_oauth_can_unlink(['password_hash' => 'hash'], $activeAccounts), 'OAuth account should be unlinkable when password login exists.');
    sr_member_oauth_check_assert(sr_member_oauth_revoke_account($pdo, $oauthAccountId, 7), 'OAuth account should be revoked.');
    sr_member_oauth_check_assert(sr_member_oauth_account_by_subject($pdo, 'mock', $subjectHash) === null, 'Revoked OAuth account should not be returned for login.');
}

sr_member_oauth_check_contains('modules/member_oauth/module.php', [
    "'oauth-providers.php'",
    "'privacy-export.php'",
    "'privacy-cleanup.php'",
]);
sr_member_oauth_check_contains('modules/member_oauth/install.sql', [
    'sr_member_oauth_accounts',
    'provider_subject_hash',
    'sr_member_oauth_states',
    'code_verifier_hash',
    'used_at',
]);
sr_member_oauth_check_contains('modules/member_oauth/helpers.php', [
    'sr_member_oauth_create_state',
    'sr_member_oauth_consume_state',
    'sr_member_oauth_subject_hash',
    'sr_member_oauth_can_unlink',
    'sr_member_oauth_revoke_account',
]);
sr_member_oauth_check_contains('modules/member_oauth/actions/start.php', [
    "sr_get_string('flow', 20) === 'link'",
    'sr_member_require_login($pdo)',
    'sr_member_oauth_create_state',
]);
sr_member_oauth_check_contains('modules/member_oauth/actions/callback.php', [
    'sr_member_oauth_state_by_token($pdo, $stateToken, \'login\')',
    'sr_member_oauth_state_by_token($pdo, $stateToken, \'link\')',
    'sr_member_oauth_consume_state',
    'sr_member_oauth_account_by_subject',
    'sr_member_login($pdo, $account)',
    'sr_member_email_verification_blocks_login',
    'sr_member_oauth_create_completion_state',
    'member.oauth.linked',
]);
sr_member_oauth_check_forbids('modules/member_oauth/actions/callback.php', [
    'sr_member_find_by_identifier',
    'email_hash',
    'access_token',
    'refresh_token',
    'id_token',
]);
sr_member_oauth_check_contains('modules/member_oauth/actions/complete.php', [
    'sr_member_registration_policy_documents($pdo)',
    'sr_member_oauth_consume_state($pdo, $stateToken',
    '$pdo->beginTransaction()',
    'sr_member_create_account',
    'sr_member_record_consent',
    'sr_member_oauth_link_account',
    'sr_member_login($pdo, $account)',
]);
sr_member_oauth_check_contains('modules/member_oauth/actions/unlink.php', [
    'sr_member_require_login($pdo)',
    'sr_member_oauth_can_unlink',
    'sr_member_oauth_revoke_account',
    'member.oauth.unlinked',
]);
sr_member_oauth_check_contains('modules/member/views/login.php', [
    'sr_member_oauth_public_providers($pdo)',
    '/oauth/start?provider=',
]);
sr_member_oauth_check_contains('modules/member/views/account.php', [
    '/oauth/start?provider=',
    'flow=link',
    '/account/oauth/unlink',
]);

sr_member_oauth_check_runtime_helpers();

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo "member oauth runtime checks completed.\n";
