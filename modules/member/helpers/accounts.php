<?php

declare(strict_types=1);

function sr_member_create_account(PDO $pdo, array $config, array $data): int
{
    $email = sr_normalize_identifier((string) ($data['email'] ?? ''));
    $loginId = sr_normalize_login_id((string) ($data['login_id'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $displayName = trim((string) ($data['display_name'] ?? ''));
    $locale = trim((string) ($data['locale'] ?? 'ko'));
    $status = trim((string) ($data['status'] ?? 'active'));
    $emailVerifiedAt = $data['email_verified_at'] ?? null;
    $allowExistingUpdate = !empty($data['allow_existing_update']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Email is invalid.');
    }

    if ($loginId !== '' && !sr_member_is_valid_login_id($loginId)) {
        throw new InvalidArgumentException('Login ID is invalid.');
    }

    if ($password === '') {
        throw new InvalidArgumentException('Password is required.');
    }

    if ($displayName === '') {
        $displayName = $email;
    }

    $identifierValue = $loginId !== '' ? $loginId : $email;
    $identifierHash = sr_hmac_hash($identifierValue, $config);
    $loginIdHash = $loginId !== '' ? sr_hmac_hash($loginId, $config) : null;
    $emailHash = sr_hmac_hash($email, $config);
    $now = sr_now();
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE email_hash = :email_hash LIMIT 1');
    $stmt->execute(['email_hash' => $emailHash]);
    $existing = $stmt->fetch();

    if (is_array($existing) && !$allowExistingUpdate) {
        throw new RuntimeException('Account already exists.');
    }

    if ($loginIdHash !== null) {
        $params = ['login_id_hash' => $loginIdHash];
        $where = '(login_id_hash = :login_id_hash OR account_identifier_hash = :login_id_hash)';
        if (is_array($existing)) {
            $where .= ' AND id <> :id';
            $params['id'] = (int) $existing['id'];
        }

        $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE ' . $where . ' LIMIT 1');
        $stmt->execute($params);
        if (is_array($stmt->fetch())) {
            throw new RuntimeException('Login ID already exists.');
        }
    }

    if (is_array($existing)) {
        $stmt = $pdo->prepare(
            'UPDATE sr_member_accounts
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
        'INSERT INTO sr_member_accounts
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

function sr_member_normalize_login_id(string $loginId): string
{
    return sr_normalize_login_id($loginId);
}

function sr_member_is_valid_login_id(string $loginId): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{3,39}\z/', $loginId) === 1;
}

function sr_normalize_login_id(string $loginId): string
{
    return strtolower(trim($loginId));
}

function sr_member_find_by_identifier(PDO $pdo, array $config, string $identifier, bool $allowEmailLogin = true): ?array
{
    $normalizedIdentifier = sr_normalize_identifier($identifier);
    if ($normalizedIdentifier === '') {
        return null;
    }

    $isEmailIdentifier = filter_var($normalizedIdentifier, FILTER_VALIDATE_EMAIL) !== false;

    if ($isEmailIdentifier) {
        $stmt = $pdo->prepare(
            'SELECT ' . sr_member_account_select_columns() . '
             FROM sr_member_accounts
             WHERE email_hash = :email_hash
             LIMIT 1'
        );
        $stmt->execute(['email_hash' => sr_hmac_hash($normalizedIdentifier, $config)]);
        $account = $stmt->fetch();
        if (is_array($account)) {
            return $account;
        }
    }

    $loginId = sr_member_normalize_login_id($identifier);
    if (sr_member_is_valid_login_id($loginId)) {
        $stmt = $pdo->prepare(
            'SELECT ' . sr_member_account_select_columns() . '
             FROM sr_member_accounts
             WHERE login_id_hash = :login_id_hash
             LIMIT 1'
        );
        $stmt->execute(['login_id_hash' => sr_hmac_hash($loginId, $config)]);
        $account = $stmt->fetch();
        if (is_array($account)) {
            return $account;
        }
    }

    $stmt = $pdo->prepare(
        'SELECT ' . sr_member_account_select_columns() . '
         FROM sr_member_accounts
         WHERE account_identifier_hash = :identifier_hash
         LIMIT 1'
    );
    $stmt->execute(['identifier_hash' => sr_hmac_hash($normalizedIdentifier, $config)]);
    $account = $stmt->fetch();

    return is_array($account) ? $account : null;
}

function sr_member_find_by_id(PDO $pdo, int $accountId): ?array
{
    if ($accountId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT ' . sr_member_account_select_columns() . '
         FROM sr_member_accounts
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $accountId]);
    $account = $stmt->fetch();

    return is_array($account) ? $account : null;
}

function sr_member_find_by_email(PDO $pdo, array $config, string $email): ?array
{
    $normalizedEmail = sr_normalize_identifier($email);
    if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    $emailHash = sr_hmac_hash($normalizedEmail, $config);
    $stmt = $pdo->prepare(
        'SELECT ' . sr_member_account_select_columns() . '
         FROM sr_member_accounts
         WHERE email_hash = :email_hash
         LIMIT 1'
    );
    $stmt->execute(['email_hash' => $emailHash]);
    $account = $stmt->fetch();

    return is_array($account) ? $account : null;
}

function sr_member_current_account(PDO $pdo): ?array
{
    if (!array_key_exists('sr_account_id', $_SESSION)) {
        sr_member_revoke_current_session($pdo);
        unset($_SESSION['sr_session_token_hash']);
        return null;
    }

    $accountId = $_SESSION['sr_account_id'];
    if (!is_int($accountId) && !ctype_digit((string) $accountId)) {
        sr_member_logout($pdo);
        return null;
    }

    $accountId = (int) $accountId;
    if ($accountId < 1) {
        sr_member_logout($pdo);
        return null;
    }

    if (!sr_member_session_is_current($pdo, $accountId)) {
        sr_member_logout($pdo);
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT ' . sr_member_account_select_columns() . '
         FROM sr_member_accounts
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $accountId]);
    $account = $stmt->fetch();

    if (!is_array($account)) {
        sr_member_logout($pdo);
        return null;
    }

    if ((string) $account['status'] !== 'active') {
        sr_member_logout($pdo);
        return null;
    }

    $settings = sr_member_settings($pdo);
    if (sr_member_email_verification_blocks_login($settings, $account)) {
        sr_member_logout($pdo);
        return null;
    }

    return $account;
}

function sr_member_require_login(PDO $pdo): array
{
    sr_request_contract_mark('auth_checked');

    $account = sr_member_current_account($pdo);
    if ($account === null) {
        sr_request_contract_guard_blocked('auth');
        $next = sr_member_current_request_next_path();
        sr_redirect('/login?next=' . rawurlencode($next));
    }

    return $account;
}

function sr_member_public_account_summary(PDO $pdo, int $accountId): ?array
{
    if ($accountId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, display_name, locale, status
         FROM sr_member_accounts
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

function sr_member_public_account_hash(array $config, int $accountId): string
{
    if ($accountId < 1) {
        return '';
    }

    return substr(sr_hmac_hash('member-public-account|' . (string) $accountId, $config), 0, 32);
}

function sr_member_public_account_hash_is_valid(string $publicHash): bool
{
    return preg_match('/\A[a-f0-9]{32}\z/', $publicHash) === 1;
}

function sr_member_public_account_summary_with_hash(PDO $pdo, array $config, int $accountId): ?array
{
    $summary = sr_member_public_account_summary($pdo, $accountId);
    if (!is_array($summary)) {
        return null;
    }

    $summary['public_hash'] = sr_member_public_account_hash($config, (int) $summary['id']);

    return $summary;
}

function sr_member_public_account_summary_by_hash(PDO $pdo, array $config, string $publicHash): ?array
{
    $publicHash = strtolower(trim($publicHash));
    if (!sr_member_public_account_hash_is_valid($publicHash)) {
        return null;
    }

    $accountsByHash = sr_member_public_account_summaries_by_hash($pdo, $config);
    return $accountsByHash[$publicHash] ?? null;
}

function sr_member_public_account_summaries_by_hash(PDO $pdo, array $config): array
{
    static $cachedMaps = [];

    $cacheKey = (string) spl_object_id($pdo) . ':' . sr_hmac_hash('member-public-account-map', $config);
    if (isset($cachedMaps[$cacheKey])) {
        return $cachedMaps[$cacheKey];
    }

    $stmt = $pdo->query("SELECT id, display_name, locale, status FROM sr_member_accounts WHERE status = 'active' ORDER BY id ASC");
    $accountsByHash = [];
    foreach ($stmt->fetchAll() as $account) {
        $accountId = (int) ($account['id'] ?? 0);
        if ($accountId > 0) {
            $accountHash = sr_member_public_account_hash($config, $accountId);
            $accountsByHash[$accountHash] = [
                'id' => (int) $account['id'],
                'display_name' => (string) $account['display_name'],
                'locale' => (string) $account['locale'],
                'status' => (string) $account['status'],
                'public_hash' => $accountHash,
            ];
        }
    }

    $cachedMaps[$cacheKey] = $accountsByHash;

    return $accountsByHash;
}

function sr_member_current_request_next_path(): string
{
    $path = sr_request_path();
    $query = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
    if (is_string($query) && $query !== '') {
        $path .= '?' . $query;
    }

    return sr_member_safe_next_path($path);
}

function sr_member_login_next_path(): string
{
    $next = sr_member_safe_next_path(sr_get_string_without_truncation('next', 1024) ?? '');
    if ($next !== '/') {
        return $next;
    }

    return sr_member_referrer_next_path();
}

function sr_member_referrer_next_path(): string
{
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!is_string($referrer) || $referrer === '' || strlen($referrer) > 2048) {
        return '/';
    }

    if (sr_is_safe_relative_url($referrer)) {
        return sr_member_safe_next_path($referrer);
    }

    if (!sr_is_http_url($referrer) || !sr_member_url_matches_current_host($referrer)) {
        return '/';
    }

    $path = parse_url($referrer, PHP_URL_PATH);
    $path = is_string($path) && $path !== '' ? $path : '/';
    $basePath = sr_base_path();
    if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
        $path = substr($path, strlen($basePath));
        $path = is_string($path) && $path !== '' ? $path : '/';
    } elseif ($basePath !== '') {
        return '/';
    }

    $query = parse_url($referrer, PHP_URL_QUERY);
    if (is_string($query) && $query !== '') {
        $path .= '?' . $query;
    }

    return sr_member_safe_next_path($path);
}

function sr_member_url_matches_current_host(string $url): bool
{
    $currentHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (!sr_http_host_is_valid($currentHost)) {
        return false;
    }

    $currentParts = parse_url('http://' . $currentHost);
    if (!is_array($currentParts)) {
        return false;
    }

    $currentAuthority = strtolower((string) ($currentParts['host'] ?? ''));
    if (isset($currentParts['port'])) {
        $currentAuthority .= ':' . (string) $currentParts['port'];
    }

    $referrerHost = parse_url($url, PHP_URL_HOST);
    if (!is_string($referrerHost) || $referrerHost === '') {
        return false;
    }

    $referrerAuthority = strtolower($referrerHost);
    $referrerPort = parse_url($url, PHP_URL_PORT);
    if (is_int($referrerPort)) {
        $referrerAuthority .= ':' . (string) $referrerPort;
    }

    return $currentAuthority !== '' && hash_equals($currentAuthority, $referrerAuthority);
}

function sr_member_safe_next_path(string $path): string
{
    if (
        $path === ''
        || $path[0] !== '/'
        || str_starts_with($path, '//')
        || strpos($path, '\\') !== false
        || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        || sr_member_next_path_is_auth_path($path)
    ) {
        return '/';
    }

    return $path;
}

function sr_member_next_path_is_auth_path(string $path): bool
{
    $requestPath = parse_url($path, PHP_URL_PATH);
    if (!is_string($requestPath) || $requestPath === '') {
        return true;
    }

    return in_array($requestPath, ['/login', '/logout'], true);
}

function sr_member_verify_login_password(?array $account, string $password): bool
{
    $passwordHash = is_array($account)
        ? (string) ($account['password_hash'] ?? '')
        : sr_member_dummy_password_hash();

    $passwordMatches = password_verify($password, $passwordHash);

    return $passwordMatches
        && is_array($account)
        && (string) ($account['status'] ?? '') === 'active';
}

function sr_member_email_verification_blocks_login(array $settings, ?array $account): bool
{
    return !empty($settings['email_verification_enabled'])
        && is_array($account)
        && (string) ($account['status'] ?? '') === 'active'
        && (string) ($account['email_verified_at'] ?? '') === '';
}

function sr_member_rehash_login_password_if_needed(PDO $pdo, int $accountId, string $password, string $currentHash): void
{
    if ($accountId < 1 || $password === '' || $currentHash === '' || !password_needs_rehash($currentHash, PASSWORD_DEFAULT)) {
        return;
    }

    try {
        $stmt = $pdo->prepare('UPDATE sr_member_accounts SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'updated_at' => sr_now(),
            'id' => $accountId,
        ]);
    } catch (Throwable $ignored) {
    }
}

function sr_member_dummy_password_hash(): string
{
    return '$2y$10$rXJfqk3XCcK2njbFv2w3XuJ3Ny/E6.46vRsuNcSOHg65o0bfe4enK';
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

function sr_member_update_status(PDO $pdo, int $accountId, string $status): void
{
    $stmt = $pdo->prepare('UPDATE sr_member_accounts SET status = :status, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        'status' => $status,
        'updated_at' => sr_now(),
        'id' => $accountId,
    ]);
}

function sr_member_anonymize_account(PDO $pdo, array $config, int $accountId): void
{
    $anonymizedIdentifier = 'anonymized:' . $accountId;
    $anonymizedEmail = 'anonymized-' . $accountId . '@invalid.saanraan.local';
    $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'UPDATE sr_member_accounts
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
        'account_identifier_hash' => sr_hmac_hash($anonymizedIdentifier, $config),
        'email' => $anonymizedEmail,
        'email_hash' => sr_hmac_hash($anonymizedEmail, $config),
        'password_hash' => $passwordHash,
        'display_name' => 'withdrawn',
        'status' => 'anonymized',
        'updated_at' => sr_now(),
        'id' => $accountId,
    ]);
}

function sr_member_update_account_basics(PDO $pdo, int $accountId, string $displayName, string $locale): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_member_accounts
         SET display_name = :display_name,
             locale = :locale,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'display_name' => $displayName,
        'locale' => $locale,
        'updated_at' => sr_now(),
        'id' => $accountId,
    ]);
}

function sr_member_log_auth(PDO $pdo, ?int $accountId, string $eventType, string $result): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO sr_member_auth_logs (account_id, event_type, result, ip_address, user_agent, created_at)
         VALUES (:account_id, :event_type, :result, :ip_address, :user_agent, :created_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'event_type' => $eventType,
        'result' => $result,
        'ip_address' => sr_client_ip(),
        'user_agent' => sr_client_user_agent(),
        'created_at' => sr_now(),
    ]);

    sr_member_record_auth_rate_limits($pdo, $accountId, $eventType, $result);
}
