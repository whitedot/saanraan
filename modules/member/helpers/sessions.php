<?php

declare(strict_types=1);

function sr_member_login(PDO $pdo, array $account): bool
{
    session_regenerate_id(true);
    $_SESSION['sr_account_id'] = (int) $account['id'];
    $_SESSION['sr_csrf_token'] = bin2hex(random_bytes(32));
    $sessionTokenHash = sr_member_create_session($pdo, (int) $account['id']);
    if ($sessionTokenHash !== '') {
        $_SESSION['sr_session_token_hash'] = $sessionTokenHash;
    } else {
        unset($_SESSION['sr_session_token_hash']);
        unset($_SESSION['sr_account_id']);
        if (sr_member_sessions_table_exists($pdo)) {
            return false;
        }
    }

    $stmt = $pdo->prepare('UPDATE sr_member_accounts SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        'last_login_at' => sr_now(),
        'updated_at' => sr_now(),
        'id' => (int) $account['id'],
    ]);

    return true;
}

function sr_member_create_session(PDO $pdo, int $accountId): string
{
    $sessionTokenHash = hash('sha256', bin2hex(random_bytes(32)));
    $now = sr_now();
    $expiresAt = date('Y-m-d H:i:s', time() + 86400);

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_member_sessions
                (account_id, session_token_hash, remember_token_hash, ip_address, user_agent, expires_at, created_at, last_seen_at)
             VALUES
                (:account_id, :session_token_hash, NULL, :ip_address, :user_agent, :expires_at, :created_at, :last_seen_at)'
        );
        $stmt->execute([
            'account_id' => $accountId,
            'session_token_hash' => $sessionTokenHash,
            'ip_address' => sr_client_ip(),
            'user_agent' => sr_client_user_agent(),
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'last_seen_at' => $now,
        ]);
    } catch (PDOException $exception) {
        return '';
    }

    return $sessionTokenHash;
}

function sr_member_session_is_current(PDO $pdo, int $accountId): bool
{
    $sessionTokenHash = $_SESSION['sr_session_token_hash'] ?? '';
    if (!is_string($sessionTokenHash) || preg_match('/\A[a-f0-9]{64}\z/', $sessionTokenHash) !== 1) {
        return !sr_member_sessions_table_exists($pdo);
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id, expires_at, revoked_at, last_seen_at
             FROM sr_member_sessions
             WHERE account_id = :account_id
               AND session_token_hash = :session_token_hash
             LIMIT 1'
        );
        $stmt->execute([
            'account_id' => $accountId,
            'session_token_hash' => $sessionTokenHash,
        ]);
        $session = $stmt->fetch();
    } catch (PDOException $exception) {
        return false;
    }

    if (!is_array($session) || $session['revoked_at'] !== null || (string) $session['expires_at'] < sr_now()) {
        return false;
    }

    $lastSeenAt = strtotime((string) $session['last_seen_at']);
    if ($lastSeenAt === false || $lastSeenAt <= time() - 300) {
        $stmt = $pdo->prepare('UPDATE sr_member_sessions SET last_seen_at = :last_seen_at WHERE id = :id');
        $stmt->execute([
            'last_seen_at' => sr_now(),
            'id' => (int) $session['id'],
        ]);
    }

    return true;
}

function sr_member_sessions_table_exists(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM sr_member_sessions LIMIT 1');
        return true;
    } catch (PDOException $exception) {
        return false;
    }
}

function sr_member_revoke_current_session(PDO $pdo): int
{
    $sessionTokenHash = $_SESSION['sr_session_token_hash'] ?? '';
    if (!is_string($sessionTokenHash) || preg_match('/\A[a-f0-9]{64}\z/', $sessionTokenHash) !== 1) {
        return 0;
    }

    if (!sr_member_sessions_table_exists($pdo)) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare('UPDATE sr_member_sessions SET revoked_at = :revoked_at WHERE session_token_hash = :session_token_hash AND revoked_at IS NULL');
        $stmt->execute([
            'revoked_at' => sr_now(),
            'session_token_hash' => $sessionTokenHash,
        ]);
    } catch (PDOException $exception) {
        return -1;
    }

    return $stmt->rowCount();
}

function sr_member_revoke_account_sessions(PDO $pdo, int $accountId): int
{
    if (!sr_member_sessions_table_exists($pdo)) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE sr_member_sessions
             SET revoked_at = :revoked_at
             WHERE account_id = :account_id
               AND revoked_at IS NULL'
        );
        $stmt->execute([
            'revoked_at' => sr_now(),
            'account_id' => $accountId,
        ]);
    } catch (PDOException $exception) {
        return -1;
    }

    return $stmt->rowCount();
}

function sr_member_revoke_other_sessions(PDO $pdo, int $accountId): int
{
    if (!sr_member_sessions_table_exists($pdo)) {
        return 0;
    }

    $sessionTokenHash = $_SESSION['sr_session_token_hash'] ?? '';
    if (!is_string($sessionTokenHash) || preg_match('/\A[a-f0-9]{64}\z/', $sessionTokenHash) !== 1) {
        return sr_member_revoke_account_sessions($pdo, $accountId);
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE sr_member_sessions
             SET revoked_at = :revoked_at
             WHERE account_id = :account_id
               AND session_token_hash <> :session_token_hash
               AND revoked_at IS NULL'
        );
        $stmt->execute([
            'revoked_at' => sr_now(),
            'account_id' => $accountId,
            'session_token_hash' => $sessionTokenHash,
        ]);
    } catch (PDOException $exception) {
        return -1;
    }

    return $stmt->rowCount();
}

function sr_member_rotate_current_session(PDO $pdo, int $accountId): bool
{
    if ($accountId < 1) {
        return false;
    }

    if (sr_member_revoke_current_session($pdo) < 0) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['sr_csrf_token'] = bin2hex(random_bytes(32));

    $sessionTokenHash = sr_member_create_session($pdo, $accountId);
    if ($sessionTokenHash === '') {
        unset($_SESSION['sr_session_token_hash']);
        if (!sr_member_sessions_table_exists($pdo)) {
            return true;
        }

        return false;
    }

    $_SESSION['sr_session_token_hash'] = $sessionTokenHash;
    return true;
}

function sr_member_current_session_account_id(): ?int
{
    $accountId = $_SESSION['sr_account_id'] ?? null;
    if (!is_int($accountId) && !ctype_digit((string) $accountId)) {
        return null;
    }

    $accountId = (int) $accountId;
    return $accountId > 0 ? $accountId : null;
}

function sr_member_logout_current_session_if_account(PDO $pdo, int $accountId): bool
{
    if ($accountId < 1 || sr_member_current_session_account_id() !== $accountId) {
        return false;
    }

    return sr_member_logout($pdo);
}

function sr_member_logout(?PDO $pdo = null): bool
{
    $sessionRevoked = true;
    if ($pdo instanceof PDO) {
        $sessionRevoked = sr_member_revoke_current_session($pdo) >= 0;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => (string) ($params['path'] ?? '/'),
            'domain' => (string) ($params['domain'] ?? ''),
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => (string) ($params['samesite'] ?? 'Lax'),
        ]);
    }

    session_destroy();
    return $sessionRevoked;
}
