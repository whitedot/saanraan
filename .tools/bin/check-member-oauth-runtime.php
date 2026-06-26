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

function sr_member_oauth_check_base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
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
    $pdo->exec(
        'CREATE TABLE sr_member_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_identifier_hash TEXT NOT NULL,
            login_id_hash TEXT NULL,
            email TEXT NOT NULL,
            email_hash TEXT NOT NULL,
            password_hash TEXT NOT NULL DEFAULT "",
            display_name TEXT NOT NULL,
            locale TEXT NOT NULL DEFAULT "ko",
            status TEXT NOT NULL DEFAULT "active",
            email_verified_at TEXT NULL,
            last_login_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_member_profile_field_values (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            field_key TEXT NOT NULL,
            label_snapshot TEXT NOT NULL DEFAULT "",
            field_type_snapshot TEXT NOT NULL DEFAULT "text",
            visibility_snapshot TEXT NOT NULL DEFAULT "public",
            show_on_profile_snapshot INTEGER NOT NULL DEFAULT 1,
            show_in_admin_snapshot INTEGER NOT NULL DEFAULT 0,
            privacy_purpose_snapshot TEXT NOT NULL DEFAULT "",
            export_policy_snapshot TEXT NOT NULL DEFAULT "include",
            cleanup_policy_snapshot TEXT NOT NULL DEFAULT "anonymize",
            value_text TEXT NULL,
            value_json TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE(account_id, field_key)
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

    $authUrl = sr_member_oauth_authorization_url([
        'authorization_url' => 'https://example.com/authorize',
        'client_id' => 'client-fixture',
        'scopes' => ['openid', 'email'],
    ], ['base_url' => 'https://site.example'], $state);
    $authParts = parse_url($authUrl);
    parse_str((string) ($authParts['query'] ?? ''), $authQuery);
    sr_member_oauth_check_assert((string) ($authParts['scheme'] ?? '') === 'https', 'OAuth authorization URL should be external HTTPS.');
    sr_member_oauth_check_assert((string) ($authQuery['state'] ?? '') === (string) $state['state'], 'OAuth authorization URL should include the state token.');
    sr_member_oauth_check_assert((string) ($authQuery['code_challenge'] ?? '') === (string) $state['code_challenge'], 'OAuth authorization URL should include the PKCE challenge.');
    sr_member_oauth_check_assert((string) ($authQuery['redirect_uri'] ?? '') === 'https://site.example/oauth/callback', 'OAuth authorization URL should include the callback URL.');
    sr_member_oauth_check_assert((string) ($authQuery['scope'] ?? '') === 'openid email', 'OAuth authorization URL should include space-delimited scopes by default.');
    sr_member_oauth_check_assert(sr_member_oauth_scope_setting_value(['openid', 'email', 'openid', 'profile']) === "openid\nemail\nprofile", 'OAuth scope settings should normalize repeated item inputs.');
    sr_member_oauth_check_assert(sr_member_oauth_required_scope_items(['scopes' => ['openid', 'email']]) === ['openid', 'email'], 'OAuth required scope helper should use provider contract scopes by default.');
    sr_member_oauth_check_assert(sr_member_oauth_scope_items_with_required(['profile'], ['scopes' => ['openid', 'email']]) === ['openid', 'email', 'profile'], 'OAuth scope settings should preserve provider-required scopes.');
    $kakaoDefaultRules = sr_member_oauth_default_profile_sync_rules([
        'scopes' => ['profile_nickname', 'account_email'],
        'email_claim' => 'kakao_account.email',
        'email_scope' => 'account_email',
        'display_name_claim' => 'kakao_account.profile.nickname',
        'display_name_scope' => 'profile_nickname',
    ]);
    sr_member_oauth_check_assert(($kakaoDefaultRules[0]['scope'] ?? '') === 'account_email', 'OAuth default profile sync should use provider-specific email scope metadata.');
    sr_member_oauth_check_assert(($kakaoDefaultRules[1]['scope'] ?? '') === 'profile_nickname', 'OAuth default profile sync should use provider-specific display name scope metadata.');
    $kakaoStoredBlankScopeRules = sr_member_oauth_profile_sync_rules([
        'scopes' => ['profile_nickname', 'account_email'],
        'email_claim' => 'kakao_account.email',
        'email_scope' => 'account_email',
        'display_name_claim' => 'kakao_account.profile.nickname',
        'display_name_scope' => 'profile_nickname',
        'profile_sync_json' => json_encode([
            ['target' => 'email', 'scope' => '', 'claim' => 'kakao_account.email'],
            ['target' => 'display_name', 'scope' => '', 'claim' => 'kakao_account.profile.nickname'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    sr_member_oauth_check_assert(($kakaoStoredBlankScopeRules[0]['scope'] ?? '') === 'account_email', 'OAuth stored basic profile sync rules should fill blank email scope from provider metadata.');
    sr_member_oauth_check_assert(($kakaoStoredBlankScopeRules[1]['scope'] ?? '') === 'profile_nickname', 'OAuth stored basic profile sync rules should fill blank display name scope from provider metadata.');
    $emptyScopeAuthUrl = sr_member_oauth_authorization_url([
        'authorization_url' => 'https://example.com/authorize',
        'client_id' => 'client-fixture',
        'scopes' => [],
    ], ['base_url' => 'https://site.example'], $state);
    parse_str((string) (parse_url($emptyScopeAuthUrl, PHP_URL_QUERY) ?: ''), $emptyScopeAuthQuery);
    sr_member_oauth_check_assert(!array_key_exists('scope', $emptyScopeAuthQuery), 'OAuth authorization URL should omit empty scope parameters.');
    sr_member_oauth_check_assert(sr_member_oauth_provider_scopes(['scope' => "account_email\nprofile_nickname", 'scope_delimiter' => ',']) === 'account_email,profile_nickname', 'OAuth stored scope item lists should support provider-specific delimiters.');
    sr_member_oauth_check_assert(sr_member_oauth_provider_scopes(['scopes' => ['account_email', 'profile_nickname'], 'scope_delimiter' => ',']) === 'account_email,profile_nickname', 'OAuth provider scopes should support provider-specific delimiters.');
    sr_member_oauth_check_assert(sr_member_oauth_provider_scopes(['scope' => ['openid', 'email', 'profile'], 'scopes' => ['openid', 'email']]) === 'openid email profile', 'OAuth stored scope settings should drive authorization requests after preserving required scopes.');
    sr_member_oauth_check_assert(sr_member_oauth_truthy('true') === true, 'OAuth truthy helper should accept true strings.');
    sr_member_oauth_check_assert(sr_member_oauth_truthy('false') === false, 'OAuth truthy helper should reject false strings.');
    $jwt = sr_member_oauth_check_base64url('{"alg":"none"}') . '.' . sr_member_oauth_check_base64url('{"sub":"apple-subject","email":"apple@example.test","email_verified":"true","nonce":"nonce-fixture"}') . '.';
    $jwtPayload = sr_member_oauth_jwt_payload($jwt);
    sr_member_oauth_check_assert((string) ($jwtPayload['sub'] ?? '') === 'apple-subject', 'OAuth JWT helper should read ID token payload claims.');
    $primaryEmail = sr_member_oauth_primary_email_from_list([
        ['email' => 'secondary@example.test', 'verified' => true, 'primary' => false],
        ['email' => 'primary@example.test', 'verified' => true, 'primary' => true],
    ]);
    sr_member_oauth_check_assert((string) ($primaryEmail['email'] ?? '') === 'primary@example.test', 'OAuth email helper should prefer primary provider email.');

    sr_member_oauth_store_transient_secrets((string) $state['state'], $state, 120);
    $transient = sr_member_oauth_take_transient_secrets((string) $state['state']);
    sr_member_oauth_check_assert(is_array($transient) && (string) $transient['code_verifier'] === (string) $state['code_verifier'], 'OAuth raw PKCE verifier should be recoverable only from the transient session store.');
    sr_member_oauth_check_assert(sr_member_oauth_take_transient_secrets((string) $state['state']) === null, 'OAuth transient session secrets should be single use.');

    $consumed = sr_member_oauth_consume_state($pdo, (string) $state['state'], 'mock', 'login');
    sr_member_oauth_check_assert(is_array($consumed), 'OAuth state should be consumed once.');
    sr_member_oauth_check_assert(sr_member_oauth_consume_state($pdo, (string) $state['state'], 'mock', 'login') === null, 'OAuth state reuse should be rejected.');

    $linkState = sr_member_oauth_create_state($pdo, 'mock', 'link', 7, '/account', 120);
    $linkPreview = sr_member_oauth_state_by_token($pdo, (string) $linkState['state'], 'link');
    sr_member_oauth_check_assert(is_array($linkPreview) && (int) $linkPreview['account_id'] === 7, 'OAuth link state should bind account id.');

    $subjectHash = sr_member_oauth_subject_hash($config, 'mock', 'provider-subject');
    sr_member_oauth_check_assert($subjectHash !== hash('sha256', 'mock:provider-subject'), 'Provider subject hash should use keyed HMAC.');
    $subjectDisplay = sr_member_oauth_subject_display_from_hash($subjectHash);
    sr_member_oauth_check_assert($subjectDisplay === 'subject:' . substr($subjectHash, 0, 12), 'Provider subject display should use a non-raw hash prefix.');
    $nestedUserinfo = [
        'response' => [
            'id' => 'naver-subject',
            'email' => 'naver-user@example.test',
            'name' => 'Naver User',
        ],
        'kakao_account' => [
            'is_email_verified' => true,
            'profile' => [
                'nickname' => 'Kakao User',
            ],
        ],
    ];
    sr_member_oauth_check_assert(sr_member_oauth_claim_value($nestedUserinfo, 'response.id') === 'naver-subject', 'OAuth provider claim helper should read nested subject paths.');
    sr_member_oauth_check_assert(sr_member_oauth_claim_value($nestedUserinfo, 'kakao_account.profile.nickname') === 'Kakao User', 'OAuth provider claim helper should read nested profile paths.');
    $profileSyncErrors = [];
    $profileSyncJson = sr_member_oauth_profile_sync_rules_json_from_input([
        ['target' => 'email', 'scope' => 'email', 'claim' => 'response.email'],
        ['target' => 'display_name', 'scope' => 'profile', 'claim' => 'response.name'],
        ['target' => 'profile:department', 'scope' => 'profile', 'claim' => 'response.department'],
    ], [
        [
            'key' => 'department',
            'label' => 'Department',
            'type' => 'text',
            'visibility' => 'admin',
            'show_on_profile' => false,
            'show_in_admin' => true,
            'export_policy' => 'include',
            'cleanup_policy' => 'anonymize',
        ],
    ], ['scopes' => ['openid', 'email', 'profile'], 'email_claim' => 'email', 'display_name_claim' => 'name'], $profileSyncErrors, 'Fixture');
    sr_member_oauth_check_assert($profileSyncErrors === [], 'OAuth profile sync rules should accept basic and extra profile targets.');
    sr_member_oauth_check_assert(str_contains($profileSyncJson, 'profile:department'), 'OAuth profile sync rules should preserve extra profile targets.');
    sr_member_oauth_check_assert(str_contains($profileSyncJson, '"scope":"profile"'), 'OAuth profile sync rules should preserve selected scope metadata.');
    $mappedProfileFields = sr_member_oauth_mapped_profile_fields([
        'profile_sync_json' => json_encode([
            ['target' => 'profile:checkbox_false', 'scope' => 'profile', 'claim' => 'response.checkbox_false'],
            ['target' => 'profile:department', 'scope' => 'profile', 'claim' => 'response.department'],
            ['target' => 'profile:ignored_array', 'scope' => 'profile', 'claim' => 'response.array_value'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ], [
        'response' => [
            'checkbox_false' => false,
            'department' => ' Engineering ',
            'array_value' => ['not' => 'scalar'],
        ],
    ]);
    sr_member_oauth_check_assert(array_key_exists('profile:checkbox_false', $mappedProfileFields) && $mappedProfileFields['profile:checkbox_false'] === false, 'OAuth mapped profile fields should preserve boolean false claims.');
    sr_member_oauth_check_assert((string) ($mappedProfileFields['profile:department'] ?? '') === 'Engineering', 'OAuth mapped profile fields should trim string claims.');
    sr_member_oauth_check_assert(!array_key_exists('profile:ignored_array', $mappedProfileFields), 'OAuth mapped profile fields should skip array claims.');
    $completionStateToken = sr_member_oauth_create_completion_state($pdo, 'mock', $subjectHash, [
        'subject_display' => 'provider-subject',
        'email' => 'mock-user@example.test',
        'email_verified' => true,
        'display_name' => 'mock_user',
    ], '/account', 120);
    $completionState = sr_member_oauth_state_by_token($pdo, $completionStateToken, 'completion');
    sr_member_oauth_check_assert(is_array($completionState), 'OAuth completion state should be readable before completion.');
    sr_member_oauth_check_assert((string) ($completionState['provider_subject_display'] ?? '') === $subjectDisplay, 'OAuth completion state must store a non-raw provider subject display.');
    sr_member_oauth_check_assert((string) ($completionState['provider_subject_display'] ?? '') !== 'provider-subject', 'OAuth completion state must not store the raw provider subject display.');
    $oauthAccountId = sr_member_oauth_link_account($pdo, 7, 'mock', $subjectHash, [
        'subject_display' => 'mock-user',
        'email' => 'mock-user@example.test',
        'email_verified' => true,
        'display_name' => 'mock_user',
    ]);
    sr_member_oauth_check_assert($oauthAccountId > 0, 'OAuth account link should be created.');
    sr_member_oauth_check_assert(sr_member_oauth_account_by_subject($pdo, 'mock', $subjectHash) !== null, 'OAuth account should be found by subject hash.');
    sr_member_oauth_check_assert(sr_member_oauth_account_by_subject_any($pdo, 'mock', $subjectHash) !== null, 'OAuth account should be found by subject hash regardless of revocation state.');
    sr_member_oauth_check_assert(sr_member_oauth_account_for_provider($pdo, 7, 'mock') !== null, 'OAuth account should be found by account/provider.');
    $storedOauthAccount = sr_member_oauth_account_for_provider($pdo, 7, 'mock');
    sr_member_oauth_check_assert(is_array($storedOauthAccount) && (string) ($storedOauthAccount['provider_subject_display'] ?? '') === $subjectDisplay, 'Linked OAuth account must store a non-raw provider subject display.');
    sr_member_oauth_check_assert(is_array($storedOauthAccount) && (string) ($storedOauthAccount['provider_subject_display'] ?? '') !== 'mock-user', 'Linked OAuth account must not store the raw profile subject display.');
    $now = sr_now();
    $oldEmail = 'old@example.test';
    $oldEmailHash = sr_hmac_hash($oldEmail, $config);
    $pdo->prepare(
        'INSERT INTO sr_member_accounts
            (id, account_identifier_hash, login_id_hash, email, email_hash, password_hash, display_name, locale, status, email_verified_at, created_at, updated_at)
         VALUES
            (7, :account_identifier_hash, NULL, :email, :email_hash, :password_hash, :display_name, :locale, :status, NULL, :created_at, :updated_at)'
    )->execute([
        'account_identifier_hash' => $oldEmailHash,
        'email' => $oldEmail,
        'email_hash' => $oldEmailHash,
        'password_hash' => 'hash',
        'display_name' => 'OldName',
        'locale' => 'ko',
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $departmentDefinition = [
        'key' => 'department',
        'label' => 'Department',
        'type' => 'text',
        'visibility' => 'admin',
        'show_on_profile' => false,
        'show_in_admin' => true,
        'export_policy' => 'include',
        'cleanup_policy' => 'anonymize',
    ];
    $teamDefinition = [
        'key' => 'team',
        'label' => 'Team',
        'type' => 'select',
        'options' => ['Alpha', 'Beta'],
        'visibility' => 'admin',
        'show_on_profile' => false,
        'show_in_admin' => true,
        'export_policy' => 'include',
        'cleanup_policy' => 'anonymize',
    ];
    $emptyKeepDefinition = [
        'key' => 'empty_keep',
        'label' => 'Empty Keep',
        'type' => 'text',
        'visibility' => 'admin',
        'show_on_profile' => false,
        'show_in_admin' => true,
        'export_policy' => 'include',
        'cleanup_policy' => 'anonymize',
    ];
    $checkboxFalseDefinition = [
        'key' => 'checkbox_false',
        'label' => 'Checkbox False',
        'type' => 'checkbox',
        'visibility' => 'admin',
        'show_on_profile' => false,
        'show_in_admin' => true,
        'export_policy' => 'include',
        'cleanup_policy' => 'anonymize',
    ];
    $checkboxEmptyDefinition = [
        'key' => 'checkbox_empty',
        'label' => 'Checkbox Empty',
        'type' => 'checkbox',
        'visibility' => 'admin',
        'show_on_profile' => false,
        'show_in_admin' => true,
        'export_policy' => 'include',
        'cleanup_policy' => 'anonymize',
    ];
    $orphanDefinition = [
        'key' => 'legacy_orphan',
        'label' => 'Legacy Orphan',
        'type' => 'text',
        'visibility' => 'admin',
        'show_on_profile' => false,
        'show_in_admin' => true,
        'export_policy' => 'include',
        'cleanup_policy' => 'anonymize',
    ];
    sr_member_save_profile_extra_field_value($pdo, 7, $teamDefinition, 'Alpha');
    sr_member_save_profile_extra_field_value($pdo, 7, $emptyKeepDefinition, 'Still here');
    sr_member_save_profile_extra_field_value($pdo, 7, $checkboxFalseDefinition, '1');
    sr_member_save_profile_extra_field_value($pdo, 7, $checkboxEmptyDefinition, '1');
    sr_member_save_profile_extra_field_value($pdo, 7, $orphanDefinition, 'Keep orphan');
    $synced = sr_member_oauth_sync_member_profile($pdo, $config, 7, [
        'id' => 7,
        'account_identifier_hash' => $oldEmailHash,
        'login_id_hash' => '',
        'email' => $oldEmail,
        'email_hash' => $oldEmailHash,
        'display_name' => 'OldName',
        'status' => 'active',
    ], [
        'profile_sync_json' => $profileSyncJson,
    ], [
        'email' => 'new@example.test',
        'email_verified' => true,
        'display_name' => 'NewName',
        'mapped_fields' => [
            'email' => 'new@example.test',
            'display_name' => 'NewName',
            'profile:department' => 'Engineering',
            'profile:team' => 'Gamma',
            'profile:empty_keep' => '',
            'profile:checkbox_false' => false,
            'profile:checkbox_empty' => '',
        ],
    ], [
        'profile_fields_json' => json_encode([
            $departmentDefinition,
            $teamDefinition,
            $emptyKeepDefinition,
            $checkboxFalseDefinition,
            $checkboxEmptyDefinition,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    sr_member_oauth_check_assert(in_array('email', $synced, true) && in_array('display_name', $synced, true) && in_array('profile_extra', $synced, true), 'OAuth profile sync should update changed member basics and mapped extra profile fields.');
    $syncedAccount = $pdo->query('SELECT * FROM sr_member_accounts WHERE id = 7')->fetch();
    sr_member_oauth_check_assert(is_array($syncedAccount) && (string) ($syncedAccount['email'] ?? '') === 'new@example.test', 'OAuth profile sync should update verified provider email.');
    sr_member_oauth_check_assert(is_array($syncedAccount) && (string) ($syncedAccount['display_name'] ?? '') === 'NewName', 'OAuth profile sync should update member display name.');
    $syncedProfileField = $pdo->query('SELECT value_text FROM sr_member_profile_field_values WHERE account_id = 7 AND field_key = "department" LIMIT 1')->fetchColumn();
    sr_member_oauth_check_assert((string) $syncedProfileField === 'Engineering', 'OAuth profile sync should save mapped non-basic values to extra profile fields.');
    $keptInvalidSelectValue = $pdo->query('SELECT value_text FROM sr_member_profile_field_values WHERE account_id = 7 AND field_key = "team" LIMIT 1')->fetchColumn();
    sr_member_oauth_check_assert((string) $keptInvalidSelectValue === 'Alpha', 'OAuth profile sync should preserve existing select profile value when provider value is outside configured options.');
    $keptEmptyValue = $pdo->query('SELECT value_text FROM sr_member_profile_field_values WHERE account_id = 7 AND field_key = "empty_keep" LIMIT 1')->fetchColumn();
    sr_member_oauth_check_assert((string) $keptEmptyValue === 'Still here', 'OAuth profile sync should preserve existing profile value when provider claim is empty.');
    $syncedCheckboxFalseValue = $pdo->query('SELECT value_text FROM sr_member_profile_field_values WHERE account_id = 7 AND field_key = "checkbox_false" LIMIT 1')->fetchColumn();
    sr_member_oauth_check_assert((string) $syncedCheckboxFalseValue === '0', 'OAuth profile sync should save boolean false claims to checkbox extra profile fields.');
    $keptCheckboxEmptyValue = $pdo->query('SELECT value_text FROM sr_member_profile_field_values WHERE account_id = 7 AND field_key = "checkbox_empty" LIMIT 1')->fetchColumn();
    sr_member_oauth_check_assert((string) $keptCheckboxEmptyValue === '1', 'OAuth profile sync should preserve existing checkbox profile value when provider claim is empty.');
    $keptOrphanValue = $pdo->query('SELECT value_text FROM sr_member_profile_field_values WHERE account_id = 7 AND field_key = "legacy_orphan" LIMIT 1')->fetchColumn();
    sr_member_oauth_check_assert((string) $keptOrphanValue === 'Keep orphan', 'OAuth profile sync should not delete profile values outside current OAuth field mappings.');

    $activeAccounts = sr_member_oauth_accounts_for_account($pdo, 7);
    sr_member_oauth_check_assert(count($activeAccounts) === 1, 'Active OAuth account list should include linked provider.');
    sr_member_oauth_check_assert(!sr_member_oauth_can_unlink(['password_hash' => ''], $activeAccounts), 'Last OAuth login method should not be unlinkable without a password.');
    sr_member_oauth_check_assert(sr_member_oauth_can_unlink(['password_hash' => 'hash'], $activeAccounts), 'OAuth account should be unlinkable when password login exists.');
    sr_member_oauth_check_assert(sr_member_oauth_revoke_account($pdo, $oauthAccountId, 7), 'OAuth account should be revoked.');
    sr_member_oauth_check_assert(sr_member_oauth_account_by_subject($pdo, 'mock', $subjectHash) === null, 'Revoked OAuth account should not be returned for login.');
    sr_member_oauth_check_assert(sr_member_oauth_account_by_subject_any($pdo, 'mock', $subjectHash) !== null, 'Revoked OAuth account should remain visible to conflict checks.');
    $reactivatedAccountId = sr_member_oauth_link_account($pdo, 7, 'mock', $subjectHash, [
        'subject_display' => 'mock-user',
        'email' => 'mock-user@example.test',
        'email_verified' => true,
        'display_name' => 'mock_user',
    ]);
    sr_member_oauth_check_assert($reactivatedAccountId === $oauthAccountId, 'Relinking the same account/provider subject should reactivate the revoked link.');
    sr_member_oauth_check_assert(sr_member_oauth_account_by_subject($pdo, 'mock', $subjectHash) !== null, 'Reactivated OAuth account should be returned for login.');
    try {
        sr_member_oauth_link_account($pdo, 8, 'mock', $subjectHash, []);
        sr_member_oauth_check_assert(false, 'Linking the same provider subject to another account should fail.');
    } catch (RuntimeException) {
        sr_member_oauth_check_assert(true, 'Linking the same provider subject to another account should fail.');
    }
}

function sr_member_oauth_check_installer_options(): void
{
    $install = sr_member_oauth_check_read('core/actions/install.php');
    sr_member_oauth_check_assert(str_contains($install, "'member_oauth' => ["), 'Installer optional module list must include member_oauth.');
    sr_member_oauth_check_assert(str_contains($install, "'member_oauth_providers' => ["), 'Installer optional module list must include member_oauth provider plugin.');
    sr_member_oauth_check_assert(strpos($install, "'member_oauth' => [") < strpos($install, "'member_oauth_providers' => ["), 'Installer must list member_oauth before its provider plugin.');
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
    'sr_member_oauth_save_settings',
    'sr_member_oauth_provider_setting_key',
    'sr_member_oauth_apply_provider_settings',
    'sr_member_oauth_provider_admin_status',
    'sr_member_oauth_scope_items',
    'sr_member_oauth_scope_setting_value',
    'sr_member_oauth_required_scope_items',
    'sr_member_oauth_scope_items_with_required',
    'sr_member_oauth_scope_setting_value_with_required',
    'sr_member_oauth_claim_path_options',
    'sr_member_oauth_profile_sync_rules_json_from_input',
    'sr_member_oauth_sync_member_profile',
    'sr_member_oauth_claim_value',
    'sr_member_oauth_secret_display',
    'sr_member_oauth_jwt_payload',
    'sr_member_oauth_primary_email_from_list',
    'sr_member_oauth_truthy',
    'sr_member_oauth_authorization_url',
    'sr_member_oauth_store_transient_secrets',
    'sr_member_oauth_take_transient_secrets',
    'sr_member_oauth_provider_profile',
    'sr_member_oauth_subject_hash',
    'sr_member_oauth_subject_display_from_hash',
    'sr_member_oauth_account_by_subject_any',
    'sr_member_oauth_can_unlink',
    'sr_member_oauth_revoke_account',
]);
sr_member_oauth_check_contains('modules/member_oauth_providers/module.php', [
    "'type' => 'plugin'",
    "'member_oauth'",
    "'oauth-providers.php'",
]);
sr_member_oauth_check_contains('modules/member_oauth_providers/oauth-providers.php', [
    "'google'",
    "'kakao'",
    "'naver'",
    "'github'",
    "'apple'",
    "'scope_delimiter'",
    "'email_url'",
    "'profile_source' => 'id_token'",
    "'response.id'",
    "'kakao_account.profile.nickname'",
]);
sr_member_oauth_check_contains('modules/member_oauth/actions/start.php', [
    "sr_get_string('flow', 20) === 'link'",
    'sr_member_require_login($pdo)',
    'sr_member_oauth_create_state',
    'sr_member_oauth_store_transient_secrets',
    'sr_redirect_trusted_external(sr_member_oauth_authorization_url',
]);
sr_member_oauth_check_contains('modules/member_oauth/actions/admin-settings.php', [
    'sr_admin_require_owner',
    "sr_post_string('intent', 40)",
    'sr_admin_post_int_in_range',
    'sr_post_string_without_truncation($secretKey, 512)',
    '$hasStoredSecret',
    'provider client secret을 입력해 주세요.',
    'sr_member_oauth_scope_setting_value_with_required',
    'sr_member_oauth_profile_sync_rules_json_from_input',
    'sr_member_oauth_save_settings',
    'member_oauth.settings.updated',
    'sr_admin_redirect_with_result',
]);
sr_member_oauth_check_contains('modules/member_oauth/views/admin-settings.php', [
    'sr_admin_feedback_toasts($notice, $errors)',
    'sr_csrf_field()',
    'sr_member_oauth_secret_display',
    'autocomplete="new-password"',
    'data-oauth-required-provider',
    'data-oauth-secret-provider',
    'data-oauth-has-stored-secret',
    'data-oauth-required-secret-for',
    'data-oauth-copy-value',
    'data-oauth-scope-list',
    'data-oauth-required-scope-row',
    'data-oauth-profile-sync-list',
    'data-oauth-profile-sync-target-select',
    'srMemberOauthProfileSyncUsedTargets',
    'srMemberOauthAvailableProfileSyncTargets',
    'data-oauth-profile-sync-scope-select',
    'data-oauth-profile-sync-claim-select',
    'data-oauth-profile-sync-claim-value',
    'data-oauth-profile-sync-claim-custom',
    'data-oauth-add-scope',
    'data-oauth-add-profile-sync',
    '/admin/member-settings#member-settings-section-profile',
    '선택 프로필 항목 관리',
]);
sr_member_oauth_check_contains('modules/member_oauth/actions/callback.php', [
    'sr_member_oauth_state_by_token($pdo, $stateToken, \'login\')',
    'sr_member_oauth_state_by_token($pdo, $stateToken, \'link\')',
    'sr_member_oauth_consume_state',
    'sr_member_oauth_take_transient_secrets',
    'sr_member_oauth_provider_profile',
    'sr_member_oauth_account_by_subject',
    'sr_member_login($pdo, $account)',
    'sr_member_email_verification_blocks_login',
    'sr_member_oauth_create_completion_state',
    'member.oauth.login',
    'member.oauth.login.blocked',
    'member.oauth.linked',
    'sr_member_oauth_sync_member_profile',
    'sr_member_oauth_update_link_snapshot',
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
    'sr_member_record_registration_policy_consents',
    'sr_member_registration_policy_consent_values_from_post',
    'sr_member_oauth_link_account',
    'sr_member_create_email_verification',
    '$emailVerificationEnabled',
    'sr_t(\'member::action.register.email_verification_notice\')',
    'sr_member_login($pdo, $account)',
    'member.oauth.registered',
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
    '비밀번호를 설정하거나 다른 소셜 로그인을 연결한 뒤 해제할 수 있습니다.',
    '$memberAccountHasPassword',
    '비밀번호 설정',
]);

sr_member_oauth_check_installer_options();
sr_member_oauth_check_runtime_helpers();

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo "member oauth runtime checks completed.\n";
