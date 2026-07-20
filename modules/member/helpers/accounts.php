<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/admin/admin-account-role.php';

function sr_member_account_status_label(string $status): string
{
    $labels = [
        'active' => '정상',
        'pending' => '대기',
        'suspended' => '차단',
        'withdrawn' => '탈퇴',
        'anonymized' => '익명화',
    ];

    return $labels[$status] ?? $status;
}

function sr_member_create_account(PDO $pdo, array $config, array $data): int
{
    $email = sr_normalize_identifier((string) ($data['email'] ?? ''));
    $loginId = sr_normalize_login_id((string) ($data['login_id'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $displayName = sr_member_normalize_display_name((string) ($data['display_name'] ?? ''));
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
    if (sr_member_identity_value_has_space($displayName)) {
        throw new InvalidArgumentException('Display name cannot contain spaces.');
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
        $params = [
            'login_id_hash' => $loginIdHash,
            'account_identifier_hash' => $loginIdHash,
        ];
        $where = '(login_id_hash = :login_id_hash OR account_identifier_hash = :account_identifier_hash)';
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
    if (sr_member_email_verification_blocks_login($pdo, $settings, $account)) {
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

    $requestPath = sr_request_path();
    if (
        sr_member_mfa_login_setup_required($pdo, $account)
        && $requestPath !== '/mypage/security'
    ) {
        sr_member_redirect_mfa_setup_required();
    }

    return $account;
}

function sr_member_require_login_json(PDO $pdo): array
{
    sr_request_contract_mark('auth_checked');

    $account = sr_member_current_account($pdo);
    if ($account === null) {
        sr_request_contract_guard_blocked('auth');
        sr_json_response(['ok' => false, 'message' => 'auth_required'], 401, ['Cache-Control: no-store']);
    }

    return $account;
}

function sr_member_redirect_mfa_setup_required(): void
{
    $_SESSION['sr_member_account_flash'] = [
        'notice' => sr_t('member::action.account.mfa_setup_required_by_policy'),
        'errors' => [],
    ];
    sr_redirect('/mypage/security');
}

function sr_member_public_account_summary(PDO $pdo, int $accountId): ?array
{
    if ($accountId < 1) {
        return null;
    }

    $settings = sr_member_settings($pdo);
    $join = sr_member_nicknames_table_exists($pdo) ? 'LEFT JOIN sr_member_nicknames n ON n.account_id = a.id' : '';
    $nicknameSelect = sr_member_nicknames_table_exists($pdo) ? 'n.nickname' : "'' AS nickname";
    $stmt = $pdo->prepare(
        'SELECT a.id, a.display_name, a.locale, a.status, ' . $nicknameSelect . '
         FROM sr_member_accounts a
         ' . $join . '
         WHERE a.id = :id
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
        'nickname' => (string) ($account['nickname'] ?? ''),
        'public_name' => sr_member_public_name($account, $settings),
        'locale' => (string) $account['locale'],
        'status' => (string) $account['status'],
    ];
}

function sr_member_public_account_hash(array $config, int $accountId): string
{
    if ($accountId < 1) {
        return '';
    }

    $accountIdHex = str_pad(strtolower(dechex($accountId)), 16, '0', STR_PAD_LEFT);
    $left = (int) hexdec(substr($accountIdHex, 0, 8));
    $right = (int) hexdec(substr($accountIdHex, 8, 8));

    for ($round = 0; $round < 6; $round++) {
        $roundValue = sr_member_public_account_hash_round_value($config, $round, $right);
        $nextLeft = $right;
        $right = ($left ^ $roundValue) & 0xffffffff;
        $left = $nextLeft;
    }

    $payload = sprintf('%08x%08x', $left, $right);
    $mac = substr(sr_hmac_hash('member-public-account-mac|' . $payload, $config), 0, 16);

    return $payload . $mac;
}

function sr_member_public_account_hash_round_value(array $config, int $round, int $right): int
{
    $hash = sr_hmac_hash(
        'member-public-account-round|' . (string) $round . '|' . sprintf('%08x', $right),
        $config
    );

    return (int) hexdec(substr($hash, 0, 8));
}

function sr_member_public_account_hash_is_valid(string $publicHash): bool
{
    return preg_match('/\A[a-f0-9]{32}\z/', $publicHash) === 1;
}

function sr_member_public_account_id_from_hash(array $config, string $publicHash): int
{
    $publicHash = strtolower(trim($publicHash));
    if (!sr_member_public_account_hash_is_valid($publicHash)) {
        return 0;
    }

    $payload = substr($publicHash, 0, 16);
    $providedMac = substr($publicHash, 16, 16);
    $expectedMac = substr(sr_hmac_hash('member-public-account-mac|' . $payload, $config), 0, 16);
    if (!hash_equals($expectedMac, $providedMac)) {
        return 0;
    }

    $left = (int) hexdec(substr($payload, 0, 8));
    $right = (int) hexdec(substr($payload, 8, 8));
    for ($round = 5; $round >= 0; $round--) {
        $previousRight = $left;
        $previousLeft = ($right ^ sr_member_public_account_hash_round_value($config, $round, $previousRight)) & 0xffffffff;
        $left = $previousLeft;
        $right = $previousRight;
    }

    $accountIdValue = hexdec(sprintf('%08x%08x', $left, $right));
    return is_int($accountIdValue) && $accountIdValue > 0 ? $accountIdValue : 0;
}

function sr_member_public_account_summary_by_hash(PDO $pdo, array $config, string $publicHash): ?array
{
    $publicHash = strtolower(trim($publicHash));
    $accountId = sr_member_public_account_id_from_hash($config, $publicHash);
    if ($accountId < 1) {
        return null;
    }

    $account = sr_member_public_account_summary($pdo, $accountId);
    if (!is_array($account) || (string) ($account['status'] ?? '') !== 'active') {
        return null;
    }

    $account['public_hash'] = $publicHash;

    return $account;
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

function sr_member_login_url_for_current_request(): string
{
    $requestPath = sr_request_path();
    if (in_array($requestPath, ['/', '/login', '/login/mfa', '/logout', '/register', '/password/reset', '/password/reset/confirm'], true)) {
        return sr_url('/login');
    }

    $next = sr_member_current_request_next_path();
    if ($next === '/') {
        return sr_url('/login');
    }

    return sr_url('/login?next=' . rawurlencode($next));
}

function sr_member_login_next_path(): string
{
    $rawNext = sr_get_string_without_truncation('next', 1024);
    if (is_string($rawNext) && $rawNext !== '') {
        return sr_member_safe_next_path($rawNext);
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
    $requestPath = parse_url($path, PHP_URL_PATH);
    if (
        $path === ''
        || $path[0] !== '/'
        || str_starts_with($path, '//')
        || strpos($path, '\\') !== false
        || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        || !is_string($requestPath)
        || $requestPath === ''
        || preg_match('/%(?:2f|5c)/i', $requestPath) === 1
        || sr_member_next_path_has_dot_segment($requestPath)
        || sr_member_next_path_is_auth_path($path)
    ) {
        return '/';
    }

    return $path;
}

function sr_member_next_path_has_dot_segment(string $requestPath): bool
{
    foreach (explode('/', $requestPath) as $segment) {
        $decodedSegment = rawurldecode($segment);
        if ($decodedSegment === '.' || $decodedSegment === '..') {
            return true;
        }
    }

    return false;
}

function sr_member_next_path_is_auth_path(string $path): bool
{
    $requestPath = parse_url($path, PHP_URL_PATH);
    if (!is_string($requestPath) || $requestPath === '') {
        return true;
    }

    return in_array($requestPath, ['/login', '/login/mfa', '/logout'], true);
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

function sr_member_email_verification_blocks_login(PDO $pdo, array $settings, ?array $account): bool
{
    if (
        empty($settings['email_verification_enabled'])
        || !is_array($account)
        || (string) ($account['status'] ?? '') !== 'active'
        || (string) ($account['email_verified_at'] ?? '') !== ''
    ) {
        return false;
    }

    return !sr_admin_account_role_is_owner($pdo, (int) ($account['id'] ?? 0));
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
    sr_member_delete_nickname($pdo, $accountId);
}

function sr_member_update_account_basics(PDO $pdo, int $accountId, string $displayName, string $locale): void
{
    $displayName = sr_member_normalize_display_name($displayName);
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

function sr_member_update_account_details(
    PDO $pdo,
    array $config,
    array $account,
    string $email,
    ?string $newLoginId,
    string $displayName,
    string $locale,
    bool $emailVerificationEnabled
): array {
    $accountId = (int) ($account['id'] ?? 0);
    $email = sr_normalize_identifier($email);
    $newLoginId = $newLoginId === null ? null : sr_member_normalize_login_id($newLoginId);
    $displayName = sr_member_normalize_display_name($displayName);

    if ($accountId < 1 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('member_account_email_invalid');
    }
    if ($newLoginId !== null && !sr_member_is_valid_login_id($newLoginId)) {
        throw new InvalidArgumentException('member_account_login_id_invalid');
    }
    $emailHash = sr_hmac_hash($email, $config);
    $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE email_hash = :email_hash AND id <> :id LIMIT 1');
    $stmt->execute([
        'email_hash' => $emailHash,
        'id' => $accountId,
    ]);
    if (is_array($stmt->fetch())) {
        throw new RuntimeException('member_account_email_duplicate');
    }

    $currentEmail = sr_normalize_identifier((string) ($account['email'] ?? ''));
    $currentEmailHash = (string) ($account['email_hash'] ?? '');
    $currentLoginIdHash = (string) ($account['login_id_hash'] ?? '');
    $currentAccountIdentifierHash = (string) ($account['account_identifier_hash'] ?? '');
    $currentHasLegacyLoginId = $currentLoginIdHash === ''
        && $currentAccountIdentifierHash !== ''
        && $currentEmailHash !== ''
        && !hash_equals($currentEmailHash, $currentAccountIdentifierHash);

    $nextLoginIdHash = $currentLoginIdHash !== '' ? $currentLoginIdHash : null;
    $accountIdentifierHash = $currentLoginIdHash !== ''
        ? $currentLoginIdHash
        : ($currentHasLegacyLoginId ? $currentAccountIdentifierHash : $emailHash);
    $loginIdChanged = false;

    if ($newLoginId !== null) {
        $nextLoginIdHash = sr_hmac_hash($newLoginId, $config);
        $stmt = $pdo->prepare(
            'SELECT id
             FROM sr_member_accounts
             WHERE (login_id_hash = :login_id_hash OR account_identifier_hash = :account_identifier_hash)
               AND id <> :id
             LIMIT 1'
        );
        $stmt->execute([
            'login_id_hash' => $nextLoginIdHash,
            'account_identifier_hash' => $nextLoginIdHash,
            'id' => $accountId,
        ]);
        if (is_array($stmt->fetch())) {
            throw new RuntimeException('member_account_login_id_duplicate');
        }

        $accountIdentifierHash = $nextLoginIdHash;
        $loginIdChanged = $currentLoginIdHash === '' || !hash_equals($currentLoginIdHash, $nextLoginIdHash);
    }

    $emailChanged = $currentEmail !== $email;
    $emailVerifiedAt = $emailChanged
        ? ($emailVerificationEnabled ? null : sr_now())
        : ($account['email_verified_at'] ?? null);

    $stmt = $pdo->prepare(
        'UPDATE sr_member_accounts
         SET account_identifier_hash = :account_identifier_hash,
             login_id_hash = :login_id_hash,
             email = :email,
             email_hash = :email_hash,
             display_name = :display_name,
             locale = :locale,
             email_verified_at = :email_verified_at,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'account_identifier_hash' => $accountIdentifierHash,
        'login_id_hash' => $nextLoginIdHash,
        'email' => $email,
        'email_hash' => $emailHash,
        'display_name' => $displayName,
        'locale' => $locale,
        'email_verified_at' => is_string($emailVerifiedAt) ? $emailVerifiedAt : null,
        'updated_at' => sr_now(),
        'id' => $accountId,
    ]);

    return [
        'email_changed' => $emailChanged,
        'login_id_changed' => $loginIdChanged,
        'login_id_set' => $nextLoginIdHash !== null || $currentHasLegacyLoginId,
    ];
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
