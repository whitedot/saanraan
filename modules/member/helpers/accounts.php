<?php

declare(strict_types=1);

function toy_member_create_account(PDO $pdo, array $config, array $data): int
{
    $email = toy_normalize_identifier((string) ($data['email'] ?? ''));
    $loginId = toy_normalize_login_id((string) ($data['login_id'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $displayName = trim((string) ($data['display_name'] ?? ''));
    $locale = trim((string) ($data['locale'] ?? 'ko'));
    $status = trim((string) ($data['status'] ?? 'active'));
    $emailVerifiedAt = $data['email_verified_at'] ?? null;
    $allowExistingUpdate = !empty($data['allow_existing_update']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Email is invalid.');
    }

    if ($loginId !== '' && !toy_member_is_valid_login_id($loginId)) {
        throw new InvalidArgumentException('Login ID is invalid.');
    }

    if ($password === '') {
        throw new InvalidArgumentException('Password is required.');
    }

    if ($displayName === '') {
        $displayName = $email;
    }

    $identifierValue = $loginId !== '' ? $loginId : $email;
    $identifierHash = toy_hmac_hash($identifierValue, $config);
    $loginIdHash = $loginId !== '' ? toy_hmac_hash($loginId, $config) : null;
    $emailHash = toy_hmac_hash($email, $config);
    $now = toy_now();
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('SELECT id FROM toy_member_accounts WHERE email_hash = :email_hash LIMIT 1');
    $stmt->execute(['email_hash' => $emailHash]);
    $existing = $stmt->fetch();

    if (is_array($existing) && !$allowExistingUpdate) {
        throw new RuntimeException('Account already exists.');
    }

    if (is_array($existing)) {
        $stmt = $pdo->prepare(
            'UPDATE toy_member_accounts
             SET account_identifier_hash = :account_identifier_hash,
                 login_id_hash = :login_id_hash,
                 password_hash = :password_hash,
                 display_name = :display_name,
                 locale = :locale,
                 status = :status,
                 email_verified_at = :email_verified_at,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'account_identifier_hash' => $identifierHash,
            'login_id_hash' => $loginIdHash,
            'password_hash' => $passwordHash,
            'display_name' => $displayName,
            'locale' => $locale,
            'status' => $status,
            'email_verified_at' => is_string($emailVerifiedAt) ? $emailVerifiedAt : null,
            'updated_at' => $now,
            'id' => (int) $existing['id'],
        ]);

        return (int) $existing['id'];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO toy_member_accounts
            (account_identifier_hash, login_id_hash, email, email_hash, password_hash, display_name, locale, status, email_verified_at, created_at, updated_at)
         VALUES
            (:account_identifier_hash, :login_id_hash, :email, :email_hash, :password_hash, :display_name, :locale, :status, :email_verified_at, :created_at, :updated_at)'
    );
    $stmt->execute([
        'account_identifier_hash' => $identifierHash,
        'login_id_hash' => $loginIdHash,
        'email' => $email,
        'email_hash' => $emailHash,
        'password_hash' => $passwordHash,
        'display_name' => $displayName,
        'locale' => $locale,
        'status' => $status,
        'email_verified_at' => is_string($emailVerifiedAt) ? $emailVerifiedAt : null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function toy_member_normalize_login_id(string $loginId): string
{
    return toy_normalize_login_id($loginId);
}

function toy_member_is_valid_login_id(string $loginId): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{3,39}\z/', $loginId) === 1;
}

function toy_normalize_login_id(string $loginId): string
{
    return strtolower(trim($loginId));
}

function toy_member_find_by_identifier(PDO $pdo, array $config, string $identifier): ?array
{
    $normalizedIdentifier = toy_normalize_identifier($identifier);
    $identifierHash = toy_hmac_hash($normalizedIdentifier, $config);
    $emailHash = filter_var($normalizedIdentifier, FILTER_VALIDATE_EMAIL)
        ? toy_hmac_hash($normalizedIdentifier, $config)
        : '';

    $stmt = $pdo->prepare(
        'SELECT ' . toy_member_account_select_columns() . '
         FROM toy_member_accounts
         WHERE account_identifier_hash = :identifier_hash
            OR (:email_hash_guard <> \'\' AND email_hash = :email_hash)
         LIMIT 1'
    );
    $stmt->execute([
        'identifier_hash' => $identifierHash,
        'email_hash_guard' => $emailHash,
        'email_hash' => $emailHash,
    ]);
    $account = $stmt->fetch();

    return is_array($account) ? $account : null;
}

function toy_member_find_by_email(PDO $pdo, array $config, string $email): ?array
{
    $normalizedEmail = toy_normalize_identifier($email);
    if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $emailHash = toy_hmac_hash($normalizedEmail, $config);
    $stmt = $pdo->prepare(
        'SELECT ' . toy_member_account_select_columns() . '
         FROM toy_member_accounts
         WHERE email_hash = :email_hash
         LIMIT 1'
    );
    $stmt->execute(['email_hash' => $emailHash]);
    $account = $stmt->fetch();

    return is_array($account) ? $account : null;
}

function toy_member_current_account(PDO $pdo): ?array
{
    if (!array_key_exists('toy_account_id', $_SESSION)) {
        toy_member_revoke_current_session($pdo);
        unset($_SESSION['toy_session_token_hash']);
        return null;
    }

    $accountId = $_SESSION['toy_account_id'];
    if (!is_int($accountId) && !ctype_digit((string) $accountId)) {
        toy_member_logout($pdo);
        return null;
    }

    $accountId = (int) $accountId;
    if ($accountId < 1) {
        toy_member_logout($pdo);
        return null;
    }

    if (!toy_member_session_is_current($pdo, $accountId)) {
        toy_member_logout($pdo);
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT ' . toy_member_account_select_columns() . '
         FROM toy_member_accounts
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $accountId]);
    $account = $stmt->fetch();

    if (!is_array($account)) {
        toy_member_logout($pdo);
        return null;
    }

    if ((string) $account['status'] !== 'active') {
        toy_member_logout($pdo);
        return null;
    }

    $settings = toy_member_settings($pdo);
    if (toy_member_email_verification_blocks_login($settings, $account)) {
        toy_member_logout($pdo);
        return null;
    }

    return $account;
}

function toy_member_require_login(PDO $pdo): array
{
    toy_request_contract_mark('auth_checked');

    $account = toy_member_current_account($pdo);
    if ($account === null) {
        toy_request_contract_guard_blocked('auth');
        $next = toy_request_path();
        toy_redirect('/login?next=' . rawurlencode($next));
    }

    return $account;
}

function toy_member_public_account_summary(PDO $pdo, int $accountId): ?array
{
    if ($accountId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, display_name, locale, status
         FROM toy_member_accounts
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $accountId]);
    $account = $stmt->fetch();

    if (!is_array($account)) {
        return null;
    }

    return [
        'id' => (int) $account['id'],
        'display_name' => (string) $account['display_name'],
        'locale' => (string) $account['locale'],
        'status' => (string) $account['status'],
    ];
}

function toy_member_safe_next_path(string $path): string
{
    if (
        $path === ''
        || $path[0] !== '/'
        || str_starts_with($path, '//')
        || strpos($path, '\\') !== false
        || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
    ) {
        return '/account';
    }

    return $path;
}

function toy_member_verify_login_password(?array $account, string $password): bool
{
    $passwordHash = is_array($account)
        ? (string) ($account['password_hash'] ?? '')
        : toy_member_dummy_password_hash();

    $passwordMatches = password_verify($password, $passwordHash);

    return $passwordMatches
        && is_array($account)
        && (string) ($account['status'] ?? '') === 'active';
}

function toy_member_email_verification_blocks_login(array $settings, ?array $account): bool
{
    return !empty($settings['email_verification_enabled'])
        && is_array($account)
        && (string) ($account['status'] ?? '') === 'active'
        && (string) ($account['email_verified_at'] ?? '') === '';
}

function toy_member_rehash_login_password_if_needed(PDO $pdo, int $accountId, string $password, string $currentHash): void
{
    if ($accountId < 1 || $password === '' || $currentHash === '' || !password_needs_rehash($currentHash, PASSWORD_DEFAULT)) {
        return;
    }

    try {
        $stmt = $pdo->prepare('UPDATE toy_member_accounts SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'updated_at' => toy_now(),
            'id' => $accountId,
        ]);
    } catch (Throwable $ignored) {
    }
}

function toy_member_dummy_password_hash(): string
{
    return '$2y$10$rXJfqk3XCcK2njbFv2w3XuJ3Ny/E6.46vRsuNcSOHg65o0bfe4enK';
}

function toy_member_update_password(PDO $pdo, int $accountId, string $password): void
{
    $stmt = $pdo->prepare('UPDATE toy_member_accounts SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'updated_at' => toy_now(),
        'id' => $accountId,
    ]);
}

function toy_member_update_status(PDO $pdo, int $accountId, string $status): void
{
    $stmt = $pdo->prepare('UPDATE toy_member_accounts SET status = :status, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        'status' => $status,
        'updated_at' => toy_now(),
        'id' => $accountId,
    ]);
}

function toy_member_anonymize_account(PDO $pdo, array $config, int $accountId): void
{
    $anonymizedIdentifier = 'anonymized:' . $accountId;
    $anonymizedEmail = 'anonymized-' . $accountId . '@invalid.toycore.local';
    $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'UPDATE toy_member_accounts
         SET account_identifier_hash = :account_identifier_hash,
             login_id_hash = NULL,
             email = :email,
             email_hash = :email_hash,
             password_hash = :password_hash,
             display_name = :display_name,
             status = :status,
             email_verified_at = NULL,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'account_identifier_hash' => toy_hmac_hash($anonymizedIdentifier, $config),
        'email' => $anonymizedEmail,
        'email_hash' => toy_hmac_hash($anonymizedEmail, $config),
        'password_hash' => $passwordHash,
        'display_name' => '탈퇴 회원',
        'status' => 'anonymized',
        'updated_at' => toy_now(),
        'id' => $accountId,
    ]);
}

function toy_member_update_account_basics(PDO $pdo, int $accountId, string $displayName, string $locale): void
{
    $stmt = $pdo->prepare(
        'UPDATE toy_member_accounts
         SET display_name = :display_name,
             locale = :locale,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'display_name' => $displayName,
        'locale' => $locale,
        'updated_at' => toy_now(),
        'id' => $accountId,
    ]);
}

function toy_member_log_auth(PDO $pdo, ?int $accountId, string $eventType, string $result): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO toy_member_auth_logs (account_id, event_type, result, ip_address, user_agent, created_at)
         VALUES (:account_id, :event_type, :result, :ip_address, :user_agent, :created_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'event_type' => $eventType,
        'result' => $result,
        'ip_address' => toy_client_ip(),
        'user_agent' => toy_client_user_agent(),
        'created_at' => toy_now(),
    ]);

    toy_member_record_auth_rate_limits($pdo, $accountId, $eventType, $result);
}
