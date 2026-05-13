<?php

declare(strict_types=1);

function sr_member_create_password_reset(PDO $pdo, array $config, int $accountId): string
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = sr_hmac_hash($token, $config);
    $now = sr_now();
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

    $stmt = $pdo->prepare(
        'UPDATE sr_member_password_resets
         SET used_at = :used_at
         WHERE account_id = :account_id
           AND used_at IS NULL'
    );
    $stmt->execute([
        'used_at' => $now,
        'account_id' => $accountId,
    ]);

    $stmt = $pdo->prepare(
        'INSERT INTO sr_member_password_resets (account_id, reset_token_hash, expires_at, created_at)
         VALUES (:account_id, :reset_token_hash, :expires_at, :created_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'reset_token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
        'created_at' => $now,
    ]);

    return $token;
}

function sr_member_find_password_reset(PDO $pdo, array $config, string $token): ?array
{
    if (preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
        return null;
    }

    $tokenHash = sr_member_password_reset_token_hash($config, $token);
    if ($tokenHash === '') {
        return null;
    }

    return sr_member_find_password_reset_by_hash($pdo, $tokenHash);
}

function sr_member_password_reset_token_hash(array $config, string $token): string
{
    if (preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
        return '';
    }

    return sr_hmac_hash($token, $config);
}

function sr_member_find_password_reset_by_hash(PDO $pdo, string $tokenHash): ?array
{
    if (preg_match('/\A[a-f0-9]{64}\z/', $tokenHash) !== 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT r.id, r.account_id, r.reset_token_hash, r.expires_at, r.used_at, r.created_at,
                a.email, a.status
         FROM sr_member_password_resets r
         INNER JOIN sr_member_accounts a ON a.id = r.account_id
         WHERE r.reset_token_hash = :reset_token_hash
         LIMIT 1'
    );
    $stmt->execute(['reset_token_hash' => $tokenHash]);
    $reset = $stmt->fetch();

    if (!is_array($reset) || $reset['used_at'] !== null || (string) $reset['expires_at'] < sr_now()) {
        return null;
    }

    return $reset;
}

function sr_member_store_password_reset_session_hash(string $tokenHash): void
{
    if (preg_match('/\A[a-f0-9]{64}\z/', $tokenHash) !== 1) {
        sr_member_clear_password_reset_session_hash();
        return;
    }

    $_SESSION['sr_password_reset_token_hash'] = $tokenHash;
    $_SESSION['sr_password_reset_token_stored_at'] = (string) time();
}

function sr_member_password_reset_session_hash(int $maxAgeSeconds = 900): string
{
    $tokenHash = $_SESSION['sr_password_reset_token_hash'] ?? '';
    $storedAt = $_SESSION['sr_password_reset_token_stored_at'] ?? '';
    $maxAgeSeconds = max(60, min(3600, $maxAgeSeconds));

    if (
        !is_string($tokenHash)
        || preg_match('/\A[a-f0-9]{64}\z/', $tokenHash) !== 1
        || !is_string($storedAt)
        || preg_match('/\A\d{10,}\z/', $storedAt) !== 1
        || (int) $storedAt < time() - $maxAgeSeconds
    ) {
        sr_member_clear_password_reset_session_hash();
        return '';
    }

    return $tokenHash;
}

function sr_member_clear_password_reset_session_hash(): void
{
    unset($_SESSION['sr_password_reset_token_hash'], $_SESSION['sr_password_reset_token_stored_at']);
}

function sr_member_mark_password_reset_used(PDO $pdo, int $resetId): bool
{
    $stmt = $pdo->prepare(
        'UPDATE sr_member_password_resets
         SET used_at = :used_at
         WHERE id = :id
           AND used_at IS NULL'
    );
    $stmt->execute([
        'used_at' => sr_now(),
        'id' => $resetId,
    ]);

    return $stmt->rowCount() === 1;
}

function sr_member_create_email_verification(PDO $pdo, array $config, int $accountId, string $email): string
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = sr_hmac_hash($token, $config);
    $now = sr_now();
    $expiresAt = date('Y-m-d H:i:s', time() + 86400);

    $stmt = $pdo->prepare(
        'UPDATE sr_member_email_verifications
         SET verified_at = :verified_at
         WHERE account_id = :account_id
           AND verified_at IS NULL'
    );
    $stmt->execute([
        'verified_at' => $now,
        'account_id' => $accountId,
    ]);

    $stmt = $pdo->prepare(
        'INSERT INTO sr_member_email_verifications (account_id, email, verification_token_hash, expires_at, created_at)
         VALUES (:account_id, :email, :verification_token_hash, :expires_at, :created_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'email' => sr_normalize_identifier($email),
        'verification_token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
        'created_at' => $now,
    ]);

    return $token;
}

function sr_member_find_email_verification(PDO $pdo, array $config, string $token): ?array
{
    if (preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
        return null;
    }

    $tokenHash = sr_hmac_hash($token, $config);
    $stmt = $pdo->prepare(
        'SELECT v.id, v.account_id, v.email, v.verification_token_hash, v.expires_at, v.verified_at, v.created_at,
                a.email AS account_email, a.status
         FROM sr_member_email_verifications v
         INNER JOIN sr_member_accounts a ON a.id = v.account_id
         WHERE v.verification_token_hash = :verification_token_hash
         LIMIT 1'
    );
    $stmt->execute(['verification_token_hash' => $tokenHash]);
    $verification = $stmt->fetch();

    if (
        !is_array($verification)
        || $verification['verified_at'] !== null
        || (string) $verification['expires_at'] < sr_now()
        || sr_normalize_identifier((string) $verification['email']) !== sr_normalize_identifier((string) $verification['account_email'])
    ) {
        return null;
    }

    return $verification;
}

function sr_member_mark_email_verified(PDO $pdo, int $verificationId, int $accountId, string $email): bool
{
    $now = sr_now();
    $email = sr_normalize_identifier($email);

    $stmt = $pdo->prepare(
        'UPDATE sr_member_email_verifications
         SET verified_at = :verified_at
         WHERE id = :id
           AND verified_at IS NULL'
    );
    $stmt->execute([
        'verified_at' => $now,
        'id' => $verificationId,
    ]);
    if ($stmt->rowCount() !== 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_member_accounts
         SET email_verified_at = :email_verified_at, updated_at = :updated_at
         WHERE id = :id
           AND email = :email'
    );
    $stmt->execute([
        'email_verified_at' => $now,
        'updated_at' => $now,
        'email' => $email,
        'id' => $accountId,
    ]);

    return $stmt->rowCount() === 1;
}
