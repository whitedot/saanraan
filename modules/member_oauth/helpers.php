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

function sr_member_oauth_provider_key(string $providerKey): string
{
    $providerKey = strtolower(trim($providerKey));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $providerKey) === 1 ? $providerKey : '';
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

    return $providers;
}

function sr_member_oauth_public_providers(PDO $pdo): array
{
    return array_values(array_filter(sr_member_oauth_providers($pdo), static function (array $provider): bool {
        return !empty($provider['enabled']) || !empty($provider['mock']);
    }));
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

function sr_member_oauth_link_account(PDO $pdo, int $accountId, string $providerKey, string $subjectHash, array $profile): int
{
    $now = sr_now();
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
        'provider_subject_display' => (string) ($profile['subject_display'] ?? ''),
        'email_snapshot' => (string) ($profile['email'] ?? ''),
        'email_verified_snapshot' => !empty($profile['email_verified']) ? 1 : 0,
        'display_name_snapshot' => (string) ($profile['display_name'] ?? ''),
        'linked_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_member_oauth_create_completion_state(PDO $pdo, string $providerKey, string $subjectHash, array $profile, string $nextPath, int $ttlSeconds): string
{
    $state = sr_member_oauth_create_state($pdo, $providerKey, 'completion', null, $nextPath, $ttlSeconds);
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
        'provider_subject_display' => (string) ($profile['subject_display'] ?? ''),
        'email_snapshot' => (string) ($profile['email'] ?? ''),
        'email_verified_snapshot' => !empty($profile['email_verified']) ? 1 : 0,
        'display_name_snapshot' => (string) ($profile['display_name'] ?? ''),
        'state_hash' => sr_member_oauth_hash((string) $state['state']),
    ]);

    return (string) $state['state'];
}
