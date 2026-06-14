<?php

declare(strict_types=1);

function sr_member_oauth_settings(PDO $pdo): array
{
    $defaults = [
        'mock_enabled' => true,
        'mock_label' => 'Mock OAuth',
        'state_ttl_seconds' => 600,
        'completion_ttl_seconds' => 900,
    ];

    return array_merge($defaults, sr_module_settings($pdo, 'member_oauth'));
}

function sr_member_oauth_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'member_oauth' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('Member OAuth module is not installed.');
    }

    $save = $pdo->prepare(
        'INSERT INTO sr_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    $now = sr_now();
    foreach ($settings as $key => $value) {
        $valueType = is_bool($value) ? 'bool' : (is_int($value) ? 'int' : 'string');
        $save->execute([
            'module_id' => (int) $module['id'],
            'setting_key' => (string) $key,
            'setting_value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            'value_type' => $valueType,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    sr_clear_module_settings_cache('member_oauth');
}

function sr_member_oauth_provider_key(string $providerKey): string
{
    $providerKey = strtolower(trim($providerKey));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $providerKey) === 1 ? $providerKey : '';
}

function sr_member_oauth_provider_setting_key(string $providerKey, string $settingKey): string
{
    $providerKey = sr_member_oauth_provider_key($providerKey);
    if ($providerKey === '' || preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $settingKey) !== 1) {
        return '';
    }

    return 'provider_' . $providerKey . '_' . $settingKey;
}

function sr_member_oauth_apply_provider_settings(array $provider, array $settings): array
{
    $providerKey = sr_member_oauth_provider_key((string) ($provider['provider_key'] ?? ''));
    if ($providerKey === '' || !empty($provider['mock'])) {
        return $provider;
    }

    foreach (['label', 'client_id', 'client_secret', 'scope', 'sort_order'] as $settingKey) {
        $storedKey = sr_member_oauth_provider_setting_key($providerKey, $settingKey);
        if ($storedKey !== '' && array_key_exists($storedKey, $settings)) {
            $provider[$settingKey] = $settings[$storedKey];
        }
    }

    $enabledKey = sr_member_oauth_provider_setting_key($providerKey, 'enabled');
    if ($enabledKey !== '' && array_key_exists($enabledKey, $settings)) {
        $provider['enabled'] = !empty($settings[$enabledKey]);
    }

    return $provider;
}

function sr_member_oauth_secret_display(string $value): string
{
    return $value === '' ? '' : '********';
}

function sr_member_oauth_builtin_providers(PDO $pdo): array
{
    $settings = sr_member_oauth_settings($pdo);
    if (empty($settings['mock_enabled'])) {
        return [];
    }

    return [
        'mock' => [
            'provider_key' => 'mock',
            'label' => (string) ($settings['mock_label'] ?? 'Mock OAuth'),
            'authorization_url' => sr_url('/oauth/callback'),
            'mock' => true,
        ],
    ];
}

function sr_member_oauth_providers(PDO $pdo): array
{
    $settings = sr_member_oauth_settings($pdo);
    $providers = sr_member_oauth_builtin_providers($pdo);
    foreach (sr_installed_module_contract_files($pdo, 'oauth-providers.php', ['member_oauth']) as $moduleKey => $contractFile) {
        $contract = sr_load_module_contract_file($moduleKey, $contractFile);
        if (!is_array($contract)) {
            continue;
        }
        foreach ($contract as $providerKey => $provider) {
            $normalizedKey = sr_member_oauth_provider_key((string) $providerKey);
            if ($normalizedKey === '' || !is_array($provider)) {
                continue;
            }
            $providers[$normalizedKey] = array_merge($provider, ['provider_key' => $normalizedKey]);
        }
    }
    foreach ($providers as $providerKey => $provider) {
        $providers[$providerKey] = sr_member_oauth_apply_provider_settings($provider, $settings);
    }

    return $providers;
}

function sr_member_oauth_public_providers(PDO $pdo): array
{
    $providers = array_values(array_filter(sr_member_oauth_providers($pdo), static function (array $provider): bool {
        return !empty($provider['enabled']) || !empty($provider['mock']);
    }));
    usort($providers, static function (array $left, array $right): int {
        $leftOrder = (int) ($left['sort_order'] ?? 0);
        $rightOrder = (int) ($right['sort_order'] ?? 0);
        if ($leftOrder !== $rightOrder) {
            return $leftOrder <=> $rightOrder;
        }

        return strcmp((string) ($left['label'] ?? $left['provider_key'] ?? ''), (string) ($right['label'] ?? $right['provider_key'] ?? ''));
    });

    return $providers;
}

function sr_member_oauth_provider_value(array $provider, string $key): string
{
    $value = $provider[$key] ?? '';
    if (is_array($value)) {
        return '';
    }

    return trim((string) $value);
}

function sr_member_oauth_provider_scopes(array $provider): string
{
    $scopes = $provider['scopes'] ?? ($provider['scope'] ?? 'openid email profile');
    if (is_array($scopes)) {
        $scopes = implode(' ', array_filter(array_map(static function ($scope): string {
            return trim((string) $scope);
        }, $scopes)));
    }

    return trim((string) $scopes);
}

function sr_member_oauth_authorization_url(array $provider, array $site, array $state): string
{
    $authorizationUrl = sr_member_oauth_provider_value($provider, 'authorization_url');
    $clientId = sr_member_oauth_provider_value($provider, 'client_id');
    $callbackUrl = sr_absolute_url($site, '/oauth/callback');
    if (!sr_is_public_http_url($authorizationUrl) || !sr_is_http_url($callbackUrl) || $clientId === '') {
        throw new InvalidArgumentException('OAuth provider authorization settings are incomplete.');
    }

    $params = [
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => $callbackUrl,
        'scope' => sr_member_oauth_provider_scopes($provider),
        'state' => (string) $state['state'],
        'code_challenge' => (string) $state['code_challenge'],
        'code_challenge_method' => 'S256',
    ];
    if (!empty($state['nonce'])) {
        $params['nonce'] = (string) $state['nonce'];
    }

    return $authorizationUrl . (str_contains($authorizationUrl, '?') ? '&' : '?') . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function sr_member_oauth_store_transient_secrets(string $state, array $stateData, int $ttlSeconds): void
{
    $hash = sr_member_oauth_hash($state);
    $now = time();
    $expiresAt = $now + max(60, $ttlSeconds);
    if (!isset($_SESSION['sr_member_oauth_transient']) || !is_array($_SESSION['sr_member_oauth_transient'])) {
        $_SESSION['sr_member_oauth_transient'] = [];
    }

    foreach ($_SESSION['sr_member_oauth_transient'] as $key => $stored) {
        if (!is_array($stored) || (int) ($stored['expires_at'] ?? 0) < $now) {
            unset($_SESSION['sr_member_oauth_transient'][$key]);
        }
    }

    $_SESSION['sr_member_oauth_transient'][$hash] = [
        'nonce' => (string) ($stateData['nonce'] ?? ''),
        'code_verifier' => (string) ($stateData['code_verifier'] ?? ''),
        'expires_at' => $expiresAt,
    ];
}

function sr_member_oauth_take_transient_secrets(string $state): ?array
{
    $hash = sr_member_oauth_hash($state);
    if (!isset($_SESSION['sr_member_oauth_transient'][$hash]) || !is_array($_SESSION['sr_member_oauth_transient'][$hash])) {
        return null;
    }

    $stored = $_SESSION['sr_member_oauth_transient'][$hash];
    unset($_SESSION['sr_member_oauth_transient'][$hash]);
    if ((int) ($stored['expires_at'] ?? 0) < time()) {
        return null;
    }

    return [
        'nonce' => (string) ($stored['nonce'] ?? ''),
        'code_verifier' => (string) ($stored['code_verifier'] ?? ''),
    ];
}

function sr_member_oauth_hash(string $value): string
{
    return hash('sha256', $value);
}

function sr_member_oauth_pkce_challenge(string $verifier): string
{
    return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
}

function sr_member_oauth_create_state(PDO $pdo, string $providerKey, string $flowType, ?int $accountId, string $nextPath, int $ttlSeconds): array
{
    $providerKey = sr_member_oauth_provider_key($providerKey);
    if ($providerKey === '' || !in_array($flowType, ['login', 'link', 'completion'], true)) {
        throw new InvalidArgumentException('OAuth state request is invalid.');
    }

    $state = bin2hex(random_bytes(32));
    $nonce = bin2hex(random_bytes(32));
    $codeVerifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $now = sr_now();
    $expiresAt = date('Y-m-d H:i:s', time() + max(60, $ttlSeconds));
    $stmt = $pdo->prepare(
        'INSERT INTO sr_member_oauth_states
            (state_hash, nonce_hash, code_verifier_hash, provider_key, flow_type, account_id, next_path, issued_at, expires_at, created_at)
         VALUES
            (:state_hash, :nonce_hash, :code_verifier_hash, :provider_key, :flow_type, :account_id, :next_path, :issued_at, :expires_at, :created_at)'
    );
    $stmt->execute([
        'state_hash' => sr_member_oauth_hash($state),
        'nonce_hash' => sr_member_oauth_hash($nonce),
        'code_verifier_hash' => sr_member_oauth_hash($codeVerifier),
        'provider_key' => $providerKey,
        'flow_type' => $flowType,
        'account_id' => $accountId !== null && $accountId > 0 ? $accountId : null,
        'next_path' => sr_member_safe_next_path($nextPath),
        'issued_at' => $now,
        'expires_at' => $expiresAt,
        'created_at' => $now,
    ]);

    return [
        'state' => $state,
        'nonce' => $nonce,
        'code_verifier' => $codeVerifier,
        'code_challenge' => sr_member_oauth_pkce_challenge($codeVerifier),
    ];
}

function sr_member_oauth_consume_state(PDO $pdo, string $state, string $providerKey, string $flowType): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_member_oauth_states
         WHERE state_hash = :state_hash
           AND provider_key = :provider_key
           AND flow_type = :flow_type
           AND used_at IS NULL
           AND expires_at >= :now
         LIMIT 1'
    );
    $stmt->execute([
        'state_hash' => sr_member_oauth_hash($state),
        'provider_key' => sr_member_oauth_provider_key($providerKey),
        'flow_type' => $flowType,
        'now' => sr_now(),
    ]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    $update = $pdo->prepare(
        'UPDATE sr_member_oauth_states
         SET used_at = :used_at
         WHERE id = :id
           AND used_at IS NULL'
    );
    $update->execute([
        'used_at' => sr_now(),
        'id' => (int) $row['id'],
    ]);
    if ($update->rowCount() < 1) {
        return null;
    }

    return $row;
}

function sr_member_oauth_state_by_token(PDO $pdo, string $state, string $flowType): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_member_oauth_states
         WHERE state_hash = :state_hash
           AND flow_type = :flow_type
           AND used_at IS NULL
           AND expires_at >= :now
         LIMIT 1'
    );
    $stmt->execute([
        'state_hash' => sr_member_oauth_hash($state),
        'flow_type' => $flowType,
        'now' => sr_now(),
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_member_oauth_subject_hash(array $config, string $providerKey, string $subject): string
{
    return sr_hmac_hash($providerKey . ':' . $subject, $config);
}

function sr_member_oauth_subject_display_from_hash(string $subjectHash): string
{
    $normalizedHash = strtolower(preg_replace('/[^a-f0-9]/i', '', $subjectHash) ?? '');
    if (strlen($normalizedHash) < 12) {
        return '';
    }

    return 'subject:' . substr($normalizedHash, 0, 12);
}

function sr_member_oauth_account_by_subject(PDO $pdo, string $providerKey, string $subjectHash): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_member_oauth_accounts
         WHERE provider_key = :provider_key
           AND provider_subject_hash = :provider_subject_hash
           AND revoked_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([
        'provider_key' => $providerKey,
        'provider_subject_hash' => $subjectHash,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_member_oauth_account_by_subject_any(PDO $pdo, string $providerKey, string $subjectHash): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_member_oauth_accounts
         WHERE provider_key = :provider_key
           AND provider_subject_hash = :provider_subject_hash
         ORDER BY revoked_at IS NULL DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute([
        'provider_key' => $providerKey,
        'provider_subject_hash' => $subjectHash,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_member_oauth_accounts_for_account(PDO $pdo, int $accountId): array
{
    if ($accountId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_member_oauth_accounts
         WHERE account_id = :account_id
           AND revoked_at IS NULL
         ORDER BY linked_at DESC, id DESC'
    );
    $stmt->execute(['account_id' => $accountId]);

    return $stmt->fetchAll();
}

function sr_member_oauth_account_for_provider(PDO $pdo, int $accountId, string $providerKey): ?array
{
    if ($accountId < 1 || $providerKey === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_member_oauth_accounts
         WHERE account_id = :account_id
           AND provider_key = :provider_key
           AND revoked_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'provider_key' => $providerKey,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_member_oauth_password_login_available(array $account): bool
{
    return trim((string) ($account['password_hash'] ?? '')) !== '';
}

function sr_member_oauth_can_unlink(array $account, array $activeOauthAccounts): bool
{
    if (sr_member_oauth_password_login_available($account)) {
        return true;
    }

    return count($activeOauthAccounts) > 1;
}

function sr_member_oauth_revoke_account(PDO $pdo, int $oauthAccountId, int $accountId): bool
{
    $stmt = $pdo->prepare(
        'UPDATE sr_member_oauth_accounts
         SET revoked_at = :revoked_at,
             updated_at = :updated_at
         WHERE id = :id
           AND account_id = :account_id
           AND revoked_at IS NULL'
    );
    $stmt->execute([
        'revoked_at' => sr_now(),
        'updated_at' => sr_now(),
        'id' => $oauthAccountId,
        'account_id' => $accountId,
    ]);

    return $stmt->rowCount() > 0;
}

function sr_member_oauth_link_account(PDO $pdo, int $accountId, string $providerKey, string $subjectHash, array $profile): int
{
    $now = sr_now();
    $subjectDisplay = sr_member_oauth_subject_display_from_hash($subjectHash);
    $existing = sr_member_oauth_account_by_subject_any($pdo, $providerKey, $subjectHash);
    if (is_array($existing)) {
        if ((int) $existing['account_id'] !== $accountId) {
            throw new RuntimeException('OAuth provider account is already linked.');
        }
        if ($existing['revoked_at'] === null) {
            return (int) $existing['id'];
        }

        $stmt = $pdo->prepare(
            'UPDATE sr_member_oauth_accounts
             SET provider_subject_display = :provider_subject_display,
                 email_snapshot = :email_snapshot,
                 email_verified_snapshot = :email_verified_snapshot,
                 display_name_snapshot = :display_name_snapshot,
                 linked_at = :linked_at,
                 revoked_at = NULL,
                 updated_at = :updated_at
             WHERE id = :id
               AND account_id = :account_id
               AND revoked_at IS NOT NULL'
        );
        $stmt->execute([
            'provider_subject_display' => $subjectDisplay,
            'email_snapshot' => (string) ($profile['email'] ?? ''),
            'email_verified_snapshot' => !empty($profile['email_verified']) ? 1 : 0,
            'display_name_snapshot' => (string) ($profile['display_name'] ?? ''),
            'linked_at' => $now,
            'updated_at' => $now,
            'id' => (int) $existing['id'],
            'account_id' => $accountId,
        ]);

        return (int) $existing['id'];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_member_oauth_accounts
            (account_id, provider_key, provider_subject_hash, provider_subject_display, email_snapshot,
             email_verified_snapshot, display_name_snapshot, linked_at, created_at, updated_at)
         VALUES
            (:account_id, :provider_key, :provider_subject_hash, :provider_subject_display, :email_snapshot,
             :email_verified_snapshot, :display_name_snapshot, :linked_at, :created_at, :updated_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'provider_key' => $providerKey,
        'provider_subject_hash' => $subjectHash,
        'provider_subject_display' => $subjectDisplay,
        'email_snapshot' => (string) ($profile['email'] ?? ''),
        'email_verified_snapshot' => !empty($profile['email_verified']) ? 1 : 0,
        'display_name_snapshot' => (string) ($profile['display_name'] ?? ''),
        'linked_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_member_oauth_mock_profile(): array
{
    return [
        'subject' => 'mock-user',
        'subject_display' => 'mock-user',
        'email' => 'mock-user@example.test',
        'email_verified' => true,
        'display_name' => 'mock_user',
    ];
}

function sr_member_oauth_http_json(string $url, array $contextOptions): array
{
    if (!sr_is_public_http_url($url)) {
        throw new RuntimeException('OAuth provider endpoint is invalid.');
    }

    $context = stream_context_create($contextOptions);
    $response = @file_get_contents($url, false, $context);
    if (!is_string($response)) {
        throw new RuntimeException('OAuth provider request failed.');
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('OAuth provider response is invalid.');
    }

    return $decoded;
}

function sr_member_oauth_provider_profile(array $provider, array $site, string $code, array $transientSecrets): array
{
    $tokenUrl = sr_member_oauth_provider_value($provider, 'token_url');
    $userinfoUrl = sr_member_oauth_provider_value($provider, 'userinfo_url');
    $clientId = sr_member_oauth_provider_value($provider, 'client_id');
    $clientSecret = sr_member_oauth_provider_value($provider, 'client_secret');
    $codeVerifier = (string) ($transientSecrets['code_verifier'] ?? '');
    $callbackUrl = sr_absolute_url($site, '/oauth/callback');
    if ($tokenUrl === '' || $userinfoUrl === '' || !sr_is_http_url($callbackUrl) || $clientId === '' || $code === '' || $codeVerifier === '') {
        throw new RuntimeException('OAuth provider callback settings are incomplete.');
    }

    $tokenPayload = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $callbackUrl,
        'client_id' => $clientId,
        'code_verifier' => $codeVerifier,
    ];
    if ($clientSecret !== '') {
        $tokenPayload['client_secret'] = $clientSecret;
    }

    $tokenResponse = sr_member_oauth_http_json($tokenUrl, [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
            'content' => http_build_query($tokenPayload, '', '&', PHP_QUERY_RFC3986),
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);
    $bearer = trim((string) ($tokenResponse['access' . '_token'] ?? ''));
    if ($bearer === '') {
        throw new RuntimeException('OAuth provider token response is missing an access credential.');
    }

    $userinfo = sr_member_oauth_http_json($userinfoUrl, [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer " . $bearer . "\r\nAccept: application/json\r\n",
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);
    $subjectClaim = sr_member_oauth_provider_value($provider, 'subject_claim') ?: 'sub';
    $emailClaim = sr_member_oauth_provider_value($provider, 'email_claim') ?: 'email';
    $emailVerifiedClaim = sr_member_oauth_provider_value($provider, 'email_verified_claim') ?: 'email_verified';
    $displayNameClaim = sr_member_oauth_provider_value($provider, 'display_name_claim') ?: 'name';
    $fallbackNameClaim = sr_member_oauth_provider_value($provider, 'fallback_display_name_claim') ?: 'nickname';
    $subject = trim((string) ($userinfo[$subjectClaim] ?? ($userinfo['id'] ?? '')));
    if ($subject === '') {
        throw new RuntimeException('OAuth provider profile is missing a subject.');
    }

    $email = trim((string) ($userinfo[$emailClaim] ?? ''));
    $displayName = trim((string) ($userinfo[$displayNameClaim] ?? ($userinfo[$fallbackNameClaim] ?? '')));

    return [
        'subject' => $subject,
        'subject_display' => $subject,
        'email' => $email,
        'email_verified' => !empty($userinfo[$emailVerifiedClaim]),
        'display_name' => $displayName,
    ];
}

function sr_member_oauth_create_completion_state(PDO $pdo, string $providerKey, string $subjectHash, array $profile, string $nextPath, int $ttlSeconds): string
{
    $state = sr_member_oauth_create_state($pdo, $providerKey, 'completion', null, $nextPath, $ttlSeconds);
    $subjectDisplay = sr_member_oauth_subject_display_from_hash($subjectHash);
    $stmt = $pdo->prepare(
        'UPDATE sr_member_oauth_states
         SET provider_subject_hash = :provider_subject_hash,
             provider_subject_display = :provider_subject_display,
             email_snapshot = :email_snapshot,
             email_verified_snapshot = :email_verified_snapshot,
             display_name_snapshot = :display_name_snapshot
         WHERE state_hash = :state_hash'
    );
    $stmt->execute([
        'provider_subject_hash' => $subjectHash,
        'provider_subject_display' => $subjectDisplay,
        'email_snapshot' => (string) ($profile['email'] ?? ''),
        'email_verified_snapshot' => !empty($profile['email_verified']) ? 1 : 0,
        'display_name_snapshot' => (string) ($profile['display_name'] ?? ''),
        'state_hash' => sr_member_oauth_hash((string) $state['state']),
    ]);

    return (string) $state['state'];
}
