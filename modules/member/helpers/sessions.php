<?php

declare(strict_types=1);

function sr_member_login(PDO $pdo, array $account): bool
{
    sr_member_cleanup_sessions($pdo);

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    sr_member_mfa_clear_challenge();
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

function sr_member_login_or_start_mfa(PDO $pdo, array $account, string $primaryMethod, string $nextPath, array $context = []): string
{
    if (sr_member_mfa_login_required($pdo, $account)) {
        sr_member_mfa_start_challenge($account, $primaryMethod, $nextPath, $context);
        return 'mfa_required';
    }

    return sr_member_login($pdo, $account) ? 'logged_in' : 'session_failed';
}

function sr_member_mfa_login_required(PDO $pdo, array $account): bool
{
    return sr_member_mfa_active_factor_exists($pdo, (int) ($account['id'] ?? 0));
}

function sr_member_mfa_active_factor_exists(PDO $pdo, int $accountId): bool
{
    if ($accountId < 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM sr_member_mfa_factors
         WHERE account_id = :account_id
           AND factor_type = 'totp'
           AND status = 'active'
         LIMIT 1"
    );
    $stmt->execute(['account_id' => $accountId]);

    return (int) $stmt->fetchColumn() > 0;
}

function sr_member_mfa_active_totp_factor(PDO $pdo, int $accountId): ?array
{
    if ($accountId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, factor_type, status, issuer, label, last_used_step, activated_at, created_at, updated_at
         FROM sr_member_mfa_factors
         WHERE account_id = :account_id
           AND factor_type = 'totp'
           AND status = 'active'
         ORDER BY id ASC
         LIMIT 1"
    );
    $stmt->execute(['account_id' => $accountId]);
    $factor = $stmt->fetch();

    return is_array($factor) ? $factor : null;
}

function sr_member_mfa_pending_totp_factor(PDO $pdo, int $accountId): ?array
{
    if ($accountId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, factor_type, status, issuer, label, created_at, updated_at
         FROM sr_member_mfa_factors
         WHERE account_id = :account_id
           AND factor_type = 'totp'
           AND status = 'pending'
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute(['account_id' => $accountId]);
    $factor = $stmt->fetch();

    return is_array($factor) ? $factor : null;
}

function sr_member_mfa_totp_secret_purpose(): string
{
    return 'member.mfa.totp';
}

function sr_member_mfa_totp_period_seconds(): int
{
    return 30;
}

function sr_member_mfa_totp_digits(): int
{
    return 6;
}

function sr_member_mfa_totp_window_steps(): int
{
    return 1;
}

function sr_member_mfa_normalize_code(string $code): string
{
    return preg_replace('/[\s-]+/', '', trim($code)) ?? '';
}

function sr_member_mfa_code_is_valid_format(string $code): bool
{
    return preg_match('/\A[0-9]{6,12}\z/', $code) === 1;
}

function sr_member_mfa_base32_encode(string $value): string
{
    if ($value === '') {
        return '';
    }

    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $buffer = 0;
    $bits = 0;
    $encoded = '';
    $length = strlen($value);
    for ($index = 0; $index < $length; $index++) {
        $buffer = ($buffer << 8) | ord($value[$index]);
        $bits += 8;
        while ($bits >= 5) {
            $encoded .= $alphabet[($buffer >> ($bits - 5)) & 31];
            $bits -= 5;
        }
    }

    if ($bits > 0) {
        $encoded .= $alphabet[($buffer << (5 - $bits)) & 31];
    }

    return $encoded;
}

function sr_member_mfa_base32_decode(string $value): ?string
{
    $clean = strtoupper(preg_replace('/[\s-]+/', '', trim($value)) ?? '');
    $clean = rtrim($clean, '=');
    if ($clean === '') {
        return '';
    }

    $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
    $buffer = 0;
    $bits = 0;
    $decoded = '';
    $length = strlen($clean);
    for ($index = 0; $index < $length; $index++) {
        $char = $clean[$index];
        if (!isset($alphabet[$char])) {
            return null;
        }
        $buffer = ($buffer << 5) | (int) $alphabet[$char];
        $bits += 5;
        if ($bits >= 8) {
            $decoded .= chr(($buffer >> ($bits - 8)) & 255);
            $bits -= 8;
        }
    }

    return $decoded;
}

function sr_member_mfa_totp_display_text(string $value, string $fallback, int $maxLength = 80): string
{
    $clean = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '');
    $clean = str_replace(':', ' ', $clean);
    $clean = preg_replace('/\s+/', ' ', $clean) ?? '';
    if ($clean === '') {
        $clean = $fallback;
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($clean) > $maxLength ? mb_substr($clean, 0, $maxLength) : $clean;
    }

    return strlen($clean) > $maxLength ? substr($clean, 0, $maxLength) : $clean;
}

function sr_member_mfa_totp_otpauth_uri(string $issuer, string $label, string $secretBase32): string
{
    $issuer = sr_member_mfa_totp_display_text($issuer, 'Saanraan', 64);
    $label = sr_member_mfa_totp_display_text($label, 'member', 120);
    $path = rawurlencode($issuer . ':' . $label);
    $query = http_build_query([
        'secret' => $secretBase32,
        'issuer' => $issuer,
        'algorithm' => 'SHA1',
        'digits' => sr_member_mfa_totp_digits(),
        'period' => sr_member_mfa_totp_period_seconds(),
    ], '', '&', PHP_QUERY_RFC3986);

    return 'otpauth://totp/' . $path . '?' . $query;
}

function sr_member_mfa_create_pending_totp_factor(PDO $pdo, int $accountId, string $issuer, string $label, ?array $config = null): array
{
    if ($accountId < 1) {
        return [
            'created' => false,
            'reason' => 'invalid_account',
        ];
    }

    if (sr_member_mfa_active_factor_exists($pdo, $accountId)) {
        return [
            'created' => false,
            'reason' => 'active_exists',
        ];
    }

    $issuer = sr_member_mfa_totp_display_text($issuer, 'Saanraan', 64);
    $label = sr_member_mfa_totp_display_text($label, 'member' . $accountId, 120);
    $secret = random_bytes(20);
    $secretBase32 = sr_member_mfa_base32_encode($secret);
    $now = sr_now();
    $secretCiphertext = sr_member_mfa_totp_secret_ciphertext($secret, $config);
    $secretFingerprint = sr_member_mfa_totp_secret_fingerprint($secret, $config);

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $stmt = $pdo->prepare(
            "DELETE FROM sr_member_mfa_factors
             WHERE account_id = :account_id
               AND factor_type = 'totp'
               AND status = 'pending'"
        );
        $stmt->execute(['account_id' => $accountId]);

        $stmt = $pdo->prepare(
            "INSERT INTO sr_member_mfa_factors (
                account_id, factor_type, status, secret_ciphertext, secret_fingerprint,
                issuer, label, last_used_step, activated_at, disabled_at, created_at, updated_at
             ) VALUES (
                :account_id, 'totp', 'pending', :secret_ciphertext, :secret_fingerprint,
                :issuer, :label, NULL, NULL, NULL, :created_at, :updated_at
             )"
        );
        $stmt->execute([
            'account_id' => $accountId,
            'secret_ciphertext' => $secretCiphertext,
            'secret_fingerprint' => $secretFingerprint,
            'issuer' => $issuer,
            'label' => $label,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $factorId = (int) $pdo->lastInsertId();

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return [
        'created' => true,
        'reason' => '',
        'factor_id' => $factorId,
        'issuer' => $issuer,
        'label' => $label,
        'secret_base32' => $secretBase32,
        'otpauth_uri' => sr_member_mfa_totp_otpauth_uri($issuer, $label, $secretBase32),
    ];
}

function sr_member_mfa_totp_code(string $secret, ?int $time = null): string
{
    $time = $time ?? time();
    $period = sr_member_mfa_totp_period_seconds();
    $step = intdiv(max(0, $time), $period);

    return sr_member_mfa_hotp_code($secret, $step);
}

function sr_member_mfa_hotp_code(string $secret, int $counter): string
{
    $counter = max(0, $counter);
    $high = intdiv($counter, 4294967296);
    $low = $counter % 4294967296;
    $binaryCounter = pack('N2', $high, $low);
    $hash = hash_hmac('sha1', $binaryCounter, $secret, true);
    $offset = ord($hash[19]) & 0x0f;
    $binary =
        ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);
    $modulus = 10 ** sr_member_mfa_totp_digits();

    return str_pad((string) ($binary % $modulus), sr_member_mfa_totp_digits(), '0', STR_PAD_LEFT);
}

function sr_member_mfa_totp_secret_ciphertext(string $secret, ?array $config = null): string
{
    return sr_secret_at_rest_encrypt($secret, sr_member_mfa_totp_secret_purpose(), $config);
}

function sr_member_mfa_totp_secret_fingerprint(string $secret, ?array $config = null): string
{
    return sr_secret_at_rest_fingerprint($secret, sr_member_mfa_totp_secret_purpose(), $config);
}

function sr_member_mfa_activate_pending_totp_factor(PDO $pdo, int $accountId, int $factorId, string $code, ?int $time = null, ?array $config = null): array
{
    $code = sr_member_mfa_normalize_code($code);
    if ($accountId < 1 || $factorId < 1 || !sr_member_mfa_code_is_valid_format($code)) {
        return [
            'activated' => false,
            'reason' => 'invalid_code',
            'factor_id' => 0,
            'step' => null,
        ];
    }

    $time = $time ?? time();
    $period = sr_member_mfa_totp_period_seconds();
    $currentStep = intdiv(max(0, $time), $period);
    $window = sr_member_mfa_totp_window_steps();
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $activeFactor = sr_member_mfa_active_totp_factor($pdo, $accountId);
        if (is_array($activeFactor)) {
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return [
                'activated' => false,
                'reason' => 'active_exists',
                'factor_id' => 0,
                'step' => null,
            ];
        }

        $stmt = $pdo->prepare(
            "SELECT id, secret_ciphertext
             FROM sr_member_mfa_factors
             WHERE id = :id
               AND account_id = :account_id
               AND factor_type = 'totp'
               AND status = 'pending'
             LIMIT 1"
        );
        $stmt->execute([
            'id' => $factorId,
            'account_id' => $accountId,
        ]);
        $factor = $stmt->fetch();
        if (!is_array($factor)) {
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return [
                'activated' => false,
                'reason' => 'factor_unavailable',
                'factor_id' => 0,
                'step' => null,
            ];
        }

        try {
            $secret = sr_secret_at_rest_decrypt(
                (string) ($factor['secret_ciphertext'] ?? ''),
                sr_member_mfa_totp_secret_purpose(),
                $config
            );
        } catch (Throwable $exception) {
            $secret = null;
        }
        if ($secret === null || $secret === '') {
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return [
                'activated' => false,
                'reason' => 'secret_unavailable',
                'factor_id' => 0,
                'step' => null,
            ];
        }

        $matchedStep = null;
        for ($offset = -$window; $offset <= $window; $offset++) {
            $step = $currentStep + $offset;
            if ($step < 0) {
                continue;
            }
            if (hash_equals(sr_member_mfa_hotp_code($secret, $step), $code)) {
                $matchedStep = $step;
                break;
            }
        }

        if ($matchedStep === null) {
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return [
                'activated' => false,
                'reason' => 'invalid_code',
                'factor_id' => 0,
                'step' => null,
            ];
        }

        $stmt = $pdo->prepare(
            "UPDATE sr_member_mfa_factors
             SET status = 'active',
                 last_used_step = :last_used_step,
                 activated_at = :activated_at,
                 updated_at = :updated_at
             WHERE id = :id
               AND account_id = :account_id
               AND factor_type = 'totp'
               AND status = 'pending'"
        );
        $now = sr_now();
        $stmt->execute([
            'last_used_step' => $matchedStep,
            'activated_at' => $now,
            'updated_at' => $now,
            'id' => $factorId,
            'account_id' => $accountId,
        ]);

        if ($stmt->rowCount() !== 1) {
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return [
                'activated' => false,
                'reason' => 'factor_unavailable',
                'factor_id' => 0,
                'step' => null,
            ];
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }

        return [
            'activated' => true,
            'reason' => '',
            'factor_id' => $factorId,
            'step' => $matchedStep,
        ];
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_member_mfa_verify_totp_code(PDO $pdo, int $accountId, string $code, ?int $time = null, ?array $config = null): array
{
    $code = sr_member_mfa_normalize_code($code);
    if ($accountId < 1 || !sr_member_mfa_code_is_valid_format($code)) {
        return [
            'verified' => false,
            'reason' => 'invalid_code',
            'factor_id' => 0,
            'step' => null,
        ];
    }

    $time = $time ?? time();
    $period = sr_member_mfa_totp_period_seconds();
    $currentStep = intdiv(max(0, $time), $period);
    $window = sr_member_mfa_totp_window_steps();
    $stmt = $pdo->prepare(
        "SELECT id, secret_ciphertext, last_used_step
         FROM sr_member_mfa_factors
         WHERE account_id = :account_id
           AND factor_type = 'totp'
           AND status = 'active'
         ORDER BY id ASC"
    );
    $stmt->execute(['account_id' => $accountId]);

    $matchedReplay = false;
    $secretUnavailable = false;
    foreach ($stmt->fetchAll() as $factor) {
        $factorId = (int) ($factor['id'] ?? 0);
        try {
            $secret = sr_secret_at_rest_decrypt(
                (string) ($factor['secret_ciphertext'] ?? ''),
                sr_member_mfa_totp_secret_purpose(),
                $config
            );
        } catch (Throwable $exception) {
            $secretUnavailable = true;
            $secret = null;
        }
        if ($factorId < 1 || $secret === null || $secret === '') {
            $secretUnavailable = true;
            continue;
        }

        for ($offset = -$window; $offset <= $window; $offset++) {
            $step = $currentStep + $offset;
            if ($step < 0) {
                continue;
            }
            if (!hash_equals(sr_member_mfa_hotp_code($secret, $step), $code)) {
                continue;
            }

            $stmt = $pdo->prepare(
                "UPDATE sr_member_mfa_factors
                 SET last_used_step = :last_used_step,
                     updated_at = :updated_at
                 WHERE id = :id
                   AND account_id = :account_id
                   AND factor_type = 'totp'
                   AND status = 'active'
                   AND (last_used_step IS NULL OR last_used_step < :last_used_step)"
            );
            $stmt->execute([
                'last_used_step' => $step,
                'updated_at' => sr_now(),
                'id' => $factorId,
                'account_id' => $accountId,
            ]);

            if ($stmt->rowCount() === 1) {
                return [
                    'verified' => true,
                    'reason' => '',
                    'factor_id' => $factorId,
                    'step' => $step,
                ];
            }

            $matchedReplay = true;
        }
    }

    return [
        'verified' => false,
        'reason' => $matchedReplay ? 'replayed_code' : ($secretUnavailable ? 'secret_unavailable' : 'invalid_code'),
        'factor_id' => 0,
        'step' => null,
    ];
}

function sr_member_mfa_privacy_metadata(PDO $pdo, int $accountId): array
{
    if ($accountId < 1) {
        return [
            'factors' => [],
            'recovery_code_counts' => [],
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT id, factor_type, status, issuer, label, last_used_step, activated_at, disabled_at, created_at, updated_at
         FROM sr_member_mfa_factors
         WHERE account_id = :account_id
         ORDER BY id ASC'
    );
    $stmt->execute(['account_id' => $accountId]);
    $factors = [];
    foreach ($stmt->fetchAll() as $factor) {
        $factors[] = [
            'id' => (int) ($factor['id'] ?? 0),
            'factor_type' => (string) ($factor['factor_type'] ?? ''),
            'status' => (string) ($factor['status'] ?? ''),
            'issuer' => (string) ($factor['issuer'] ?? ''),
            'label' => (string) ($factor['label'] ?? ''),
            'last_used_step' => $factor['last_used_step'] === null ? null : (int) $factor['last_used_step'],
            'activated_at' => $factor['activated_at'],
            'disabled_at' => $factor['disabled_at'],
            'created_at' => (string) ($factor['created_at'] ?? ''),
            'updated_at' => (string) ($factor['updated_at'] ?? ''),
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT status, COUNT(*) AS code_count
         FROM sr_member_mfa_recovery_codes
         WHERE account_id = :account_id
         GROUP BY status
         ORDER BY status ASC'
    );
    $stmt->execute(['account_id' => $accountId]);
    $recoveryCodeCounts = [];
    foreach ($stmt->fetchAll() as $row) {
        $recoveryCodeCounts[(string) ($row['status'] ?? '')] = (int) ($row['code_count'] ?? 0);
    }

    return [
        'factors' => $factors,
        'recovery_code_counts' => $recoveryCodeCounts,
    ];
}

function sr_member_delete_mfa(PDO $pdo, int $accountId): array
{
    if ($accountId < 1) {
        return [
            'factors_deleted' => 0,
            'recovery_codes_deleted' => 0,
        ];
    }

    $stmt = $pdo->prepare('DELETE FROM sr_member_mfa_recovery_codes WHERE account_id = :account_id');
    $stmt->execute(['account_id' => $accountId]);
    $recoveryCodesDeleted = $stmt->rowCount();

    $stmt = $pdo->prepare('DELETE FROM sr_member_mfa_factors WHERE account_id = :account_id');
    $stmt->execute(['account_id' => $accountId]);

    return [
        'factors_deleted' => $stmt->rowCount(),
        'recovery_codes_deleted' => $recoveryCodesDeleted,
    ];
}

function sr_member_mfa_start_challenge(array $account, string $primaryMethod, string $nextPath, array $context = []): void
{
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1) {
        return;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    unset($_SESSION['sr_account_id'], $_SESSION['sr_session_token_hash']);
    $_SESSION['sr_csrf_token'] = bin2hex(random_bytes(32));

    $now = time();
    $_SESSION['sr_member_mfa_challenge'] = [
        'account_id' => $accountId,
        'primary_method' => sr_member_mfa_primary_method($primaryMethod),
        'next_path' => sr_member_safe_next_path($nextPath),
        'created_at' => $now,
        'expires_at' => $now + sr_member_mfa_challenge_ttl_seconds(),
        'context' => sr_member_mfa_challenge_context($context),
    ];
}

function sr_member_mfa_challenge(): ?array
{
    $challenge = $_SESSION['sr_member_mfa_challenge'] ?? null;
    if (!is_array($challenge)) {
        return null;
    }

    $accountId = (int) ($challenge['account_id'] ?? 0);
    $expiresAt = (int) ($challenge['expires_at'] ?? 0);
    if ($accountId < 1 || $expiresAt < time()) {
        sr_member_mfa_clear_challenge();
        return null;
    }

    $challenge['account_id'] = $accountId;
    $challenge['primary_method'] = sr_member_mfa_primary_method((string) ($challenge['primary_method'] ?? ''));
    $challenge['next_path'] = sr_member_safe_next_path((string) ($challenge['next_path'] ?? ''));
    $challenge['created_at'] = (int) ($challenge['created_at'] ?? 0);
    $challenge['expires_at'] = $expiresAt;
    $challenge['context'] = isset($challenge['context']) && is_array($challenge['context'])
        ? sr_member_mfa_challenge_context($challenge['context'])
        : [];

    return $challenge;
}

function sr_member_mfa_clear_challenge(): void
{
    unset($_SESSION['sr_member_mfa_challenge']);
}

function sr_member_mfa_challenge_ttl_seconds(): int
{
    return 300;
}

function sr_member_mfa_primary_method(string $method): string
{
    return in_array($method, ['password', 'register', 'oauth', 'oauth_completion'], true) ? $method : 'password';
}

function sr_member_mfa_challenge_context(array $context): array
{
    $clean = [];
    foreach ($context as $key => $value) {
        if (!is_string($key) || preg_match('/\A[a-z0-9_.:-]{1,80}\z/', $key) !== 1) {
            continue;
        }
        if (is_scalar($value) || $value === null) {
            $clean[$key] = $value === null ? '' : (string) $value;
        }
    }

    return $clean;
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

function sr_member_cleanup_sessions(PDO $pdo, int $revokedRetentionDays = 30): int
{
    if (!sr_member_sessions_table_exists($pdo)) {
        return 0;
    }

    $now = sr_now();
    $revokedBefore = date('Y-m-d H:i:s', time() - max(1, $revokedRetentionDays) * 86400);

    try {
        $stmt = $pdo->prepare(
            'DELETE FROM sr_member_sessions
             WHERE expires_at < :now
                OR (revoked_at IS NOT NULL AND revoked_at < :revoked_before)'
        );
        $stmt->execute([
            'now' => $now,
            'revoked_before' => $revokedBefore,
        ]);
    } catch (PDOException $exception) {
        return -1;
    }

    return $stmt->rowCount();
}

function sr_member_session_is_current(PDO $pdo, int $accountId): bool
{
    if (random_int(1, 100) === 1) {
        sr_member_cleanup_sessions($pdo);
    }

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
