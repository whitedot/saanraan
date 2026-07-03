<?php

declare(strict_types=1);

function sr_member_login_throttle_status(PDO $pdo, ?int $accountId): array
{
    $settings = sr_member_settings($pdo);
    $windowSeconds = (int) $settings['login_throttle_window_seconds'];
    $accountLimit = (int) $settings['login_throttle_account_limit'];
    $ipLimit = (int) $settings['login_throttle_ip_limit'];

    $windowSeconds = min(86400, max(60, $windowSeconds));
    $accountLimit = min(100, max(1, $accountLimit));
    $ipLimit = min(500, max(1, $ipLimit));

    $windowStartedAt = date('Y-m-d H:i:s', time() - $windowSeconds);
    $ipAddress = sr_client_ip();
    $useRateLimits = sr_member_rate_limits_table_exists($pdo);

    if ($accountId !== null) {
        $failureCount = $useRateLimits
            ? sr_rate_limit_count($pdo, 'member.login.account', (string) $accountId, $windowSeconds)
            : sr_member_auth_log_count($pdo, sr_member_login_failure_event_types(), 'failure', $accountId, '', $windowStartedAt);
        if ($failureCount >= $accountLimit) {
            return ['limited' => true, 'reason' => 'account'];
        }
    }

    if ($ipAddress !== '') {
        $failureCount = $useRateLimits
            ? sr_rate_limit_count($pdo, 'member.login.ip', $ipAddress, $windowSeconds)
            : sr_member_auth_log_count($pdo, sr_member_login_failure_event_types(), 'failure', null, $ipAddress, $windowStartedAt);
        if ($failureCount >= $ipLimit) {
            return ['limited' => true, 'reason' => 'ip'];
        }
    }

    return ['limited' => false, 'reason' => ''];
}

function sr_member_password_reset_throttle_status(PDO $pdo, ?int $accountId): array
{
    $settings = sr_member_settings($pdo);
    $windowSeconds = (int) $settings['password_reset_throttle_window_seconds'];
    $accountLimit = (int) $settings['password_reset_throttle_account_limit'];
    $ipLimit = (int) $settings['password_reset_throttle_ip_limit'];

    $windowSeconds = min(86400, max(60, $windowSeconds));
    $accountLimit = min(50, max(1, $accountLimit));
    $ipLimit = min(200, max(1, $ipLimit));

    $windowStartedAt = date('Y-m-d H:i:s', time() - $windowSeconds);
    $ipAddress = sr_client_ip();
    $useRateLimits = sr_member_rate_limits_table_exists($pdo);

    if ($accountId !== null) {
        $requestCount = $useRateLimits
            ? sr_rate_limit_count($pdo, 'member.password_reset.account', (string) $accountId, $windowSeconds)
            : sr_member_auth_log_count($pdo, ['password_reset_request', 'password_reset_request_blocked'], '', $accountId, '', $windowStartedAt);
        if ($requestCount >= $accountLimit) {
            return ['limited' => true, 'reason' => 'account'];
        }
    }

    if ($ipAddress !== '') {
        $requestCount = $useRateLimits
            ? sr_rate_limit_count($pdo, 'member.password_reset.ip', $ipAddress, $windowSeconds)
            : sr_member_auth_log_count($pdo, ['password_reset_request', 'password_reset_request_blocked'], '', null, $ipAddress, $windowStartedAt);
        if ($requestCount >= $ipLimit) {
            return ['limited' => true, 'reason' => 'ip'];
        }
    }

    return ['limited' => false, 'reason' => ''];
}

function sr_member_email_verification_throttle_status(PDO $pdo, int $accountId): array
{
    $settings = sr_member_settings($pdo);
    $windowSeconds = (int) $settings['email_verification_throttle_window_seconds'];
    $accountLimit = (int) $settings['email_verification_throttle_account_limit'];
    $ipLimit = (int) $settings['email_verification_throttle_ip_limit'];

    $windowSeconds = min(86400, max(60, $windowSeconds));
    $accountLimit = min(50, max(1, $accountLimit));
    $ipLimit = min(200, max(1, $ipLimit));

    $windowStartedAt = date('Y-m-d H:i:s', time() - $windowSeconds);
    $ipAddress = sr_client_ip();
    $useRateLimits = sr_member_rate_limits_table_exists($pdo);

    $requestCount = $useRateLimits
        ? sr_rate_limit_count($pdo, 'member.email_verification.account', (string) $accountId, $windowSeconds)
        : sr_member_auth_log_count($pdo, ['email_verification_request', 'email_verification_request_blocked'], '', $accountId, '', $windowStartedAt);
    if ($requestCount >= $accountLimit) {
        return ['limited' => true, 'reason' => 'account'];
    }

    if ($ipAddress !== '') {
        $requestCount = $useRateLimits
            ? sr_rate_limit_count($pdo, 'member.email_verification.ip', $ipAddress, $windowSeconds)
            : sr_member_auth_log_count($pdo, ['email_verification_request', 'email_verification_request_blocked'], '', null, $ipAddress, $windowStartedAt);
        if ($requestCount >= $ipLimit) {
            return ['limited' => true, 'reason' => 'ip'];
        }
    }

    return ['limited' => false, 'reason' => ''];
}

function sr_member_register_throttle_status(PDO $pdo): array
{
    $settings = sr_member_settings($pdo);
    $windowSeconds = (int) $settings['register_throttle_window_seconds'];
    $ipLimit = (int) $settings['register_throttle_ip_limit'];

    $windowSeconds = min(86400, max(60, $windowSeconds));
    $ipLimit = min(200, max(1, $ipLimit));

    $ipAddress = sr_client_ip();
    if ($ipAddress === '') {
        return ['limited' => false, 'reason' => ''];
    }

    $requestCount = sr_member_rate_limits_table_exists($pdo)
        ? sr_rate_limit_count($pdo, 'member.register.ip', $ipAddress, $windowSeconds)
        : sr_member_auth_log_count($pdo, ['register', 'register_blocked'], '', null, $ipAddress, date('Y-m-d H:i:s', time() - $windowSeconds));
    if ($requestCount >= $ipLimit) {
        return ['limited' => true, 'reason' => 'ip'];
    }

    return ['limited' => false, 'reason' => ''];
}

function sr_member_reauth_throttle_status(PDO $pdo, int $accountId): array
{
    if ($accountId < 1) {
        return ['limited' => false, 'reason' => ''];
    }

    $settings = sr_member_settings($pdo);
    $windowSeconds = min(86400, max(60, (int) $settings['login_throttle_window_seconds']));
    $accountLimit = min(100, max(1, (int) $settings['login_throttle_account_limit']));
    $ipLimit = min(500, max(1, (int) $settings['login_throttle_ip_limit']));
    $windowStartedAt = date('Y-m-d H:i:s', time() - $windowSeconds);
    $ipAddress = sr_client_ip();
    $useRateLimits = sr_member_rate_limits_table_exists($pdo);

    $failureCount = $useRateLimits
        ? sr_rate_limit_count($pdo, 'member.reauth.account', (string) $accountId, $windowSeconds)
        : sr_member_auth_log_count($pdo, sr_member_reauth_failure_event_types(), 'failure', $accountId, '', $windowStartedAt);
    if ($failureCount >= $accountLimit) {
        return ['limited' => true, 'reason' => 'account'];
    }

    if ($ipAddress !== '') {
        $failureCount = $useRateLimits
            ? sr_rate_limit_count($pdo, 'member.reauth.ip', $ipAddress, $windowSeconds)
            : sr_member_auth_log_count($pdo, sr_member_reauth_failure_event_types(), 'failure', null, $ipAddress, $windowStartedAt);
        if ($failureCount >= $ipLimit) {
            return ['limited' => true, 'reason' => 'ip'];
        }
    }

    return ['limited' => false, 'reason' => ''];
}

function sr_member_mfa_throttle_status(PDO $pdo, int $accountId): array
{
    if ($accountId < 1) {
        return ['limited' => false, 'reason' => ''];
    }

    $settings = sr_member_settings($pdo);
    $windowSeconds = min(86400, max(60, (int) $settings['login_throttle_window_seconds']));
    $accountLimit = min(100, max(1, (int) $settings['login_throttle_account_limit']));
    $ipLimit = min(500, max(1, (int) $settings['login_throttle_ip_limit']));
    $windowStartedAt = date('Y-m-d H:i:s', time() - $windowSeconds);
    $ipAddress = sr_client_ip();
    $useRateLimits = sr_member_rate_limits_table_exists($pdo);

    $failureCount = $useRateLimits
        ? sr_rate_limit_count($pdo, 'member.mfa.account', (string) $accountId, $windowSeconds)
        : sr_member_auth_log_count($pdo, sr_member_mfa_failure_event_types(), 'failure', $accountId, '', $windowStartedAt);
    if ($failureCount >= $accountLimit) {
        return ['limited' => true, 'reason' => 'account'];
    }

    if ($ipAddress !== '') {
        $failureCount = $useRateLimits
            ? sr_rate_limit_count($pdo, 'member.mfa.ip', $ipAddress, $windowSeconds)
            : sr_member_auth_log_count($pdo, sr_member_mfa_failure_event_types(), 'failure', null, $ipAddress, $windowStartedAt);
        if ($failureCount >= $ipLimit) {
            return ['limited' => true, 'reason' => 'ip'];
        }
    }

    return ['limited' => false, 'reason' => ''];
}

function sr_member_login_failure_event_types(): array
{
    return ['login', 'login_blocked', 'login_email_unverified', 'login_session_failed'];
}

function sr_member_reauth_failure_event_types(): array
{
    return ['account_page_reauth', 'password_change_reauth', 'password_change_session_failed', 'mfa_setup_reauth', 'mfa_manage_reauth', 'withdraw_reauth', 'privacy_export_reauth', 'module_source_reauth', 'module_setting_reauth', 'site_setting_reauth', 'privacy_request_export_reauth', 'admin_permission_reauth', 'reauth_blocked'];
}

function sr_member_mfa_failure_event_types(): array
{
    return ['mfa_totp_failure', 'mfa_email_failure', 'mfa_backup_failure', 'mfa_challenge_expired', 'mfa_rate_limited'];
}

function sr_member_rate_limits_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_rate_limits LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_member_auth_log_count(PDO $pdo, array $eventTypes, string $result, ?int $accountId, string $ipAddress, string $windowStartedAt): int
{
    $allowedEventTypes = [];
    foreach ($eventTypes as $eventType) {
        if (is_string($eventType) && preg_match('/\A[a-z0-9_]{1,60}\z/', $eventType) === 1) {
            $allowedEventTypes[] = $eventType;
        }
    }

    if ($allowedEventTypes === []) {
        return 0;
    }

    $placeholders = [];
    $params = [
        'created_at' => $windowStartedAt,
    ];
    foreach ($allowedEventTypes as $index => $eventType) {
        $key = 'event_type_' . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $eventType;
    }

    $conditions = [
        'event_type IN (' . implode(', ', $placeholders) . ')',
        'created_at >= :created_at',
    ];
    if ($result !== '') {
        $conditions[] = 'result = :result';
        $params['result'] = $result;
    }
    if ($accountId !== null) {
        $conditions[] = 'account_id = :account_id';
        $params['account_id'] = $accountId;
    }
    if ($ipAddress !== '') {
        $conditions[] = 'ip_address = :ip_address';
        $params['ip_address'] = $ipAddress;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS request_count
         FROM sr_member_auth_logs
         WHERE ' . implode(' AND ', $conditions)
    );
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['request_count'] ?? 0) : 0;
}

function sr_member_record_auth_rate_limits(PDO $pdo, ?int $accountId, string $eventType, string $result): void
{
    if (!sr_member_rate_limits_table_exists($pdo)) {
        return;
    }

    $settings = sr_member_settings($pdo);
    $ipAddress = sr_client_ip();

    if (in_array($eventType, sr_member_login_failure_event_types(), true) && $result === 'failure') {
        $windowSeconds = min(86400, max(60, (int) $settings['login_throttle_window_seconds']));
        if ($accountId !== null) {
            sr_rate_limit_increment($pdo, 'member.login.account', (string) $accountId, $windowSeconds);
        }
        if ($ipAddress !== '') {
            sr_rate_limit_increment($pdo, 'member.login.ip', $ipAddress, $windowSeconds);
        }
        return;
    }

    if (in_array($eventType, sr_member_reauth_failure_event_types(), true) && $result === 'failure') {
        $windowSeconds = min(86400, max(60, (int) $settings['login_throttle_window_seconds']));
        if ($accountId !== null) {
            sr_rate_limit_increment($pdo, 'member.reauth.account', (string) $accountId, $windowSeconds);
        }
        if ($ipAddress !== '') {
            sr_rate_limit_increment($pdo, 'member.reauth.ip', $ipAddress, $windowSeconds);
        }
        return;
    }

    if (in_array($eventType, sr_member_mfa_failure_event_types(), true) && $result === 'failure') {
        $windowSeconds = min(86400, max(60, (int) $settings['login_throttle_window_seconds']));
        if ($accountId !== null) {
            sr_rate_limit_increment($pdo, 'member.mfa.account', (string) $accountId, $windowSeconds);
        }
        if ($ipAddress !== '') {
            sr_rate_limit_increment($pdo, 'member.mfa.ip', $ipAddress, $windowSeconds);
        }
        return;
    }

    if (in_array($eventType, ['password_reset_request', 'password_reset_request_blocked'], true)) {
        $windowSeconds = min(86400, max(60, (int) $settings['password_reset_throttle_window_seconds']));
        if ($accountId !== null) {
            sr_rate_limit_increment($pdo, 'member.password_reset.account', (string) $accountId, $windowSeconds);
        }
        if ($ipAddress !== '') {
            sr_rate_limit_increment($pdo, 'member.password_reset.ip', $ipAddress, $windowSeconds);
        }
        return;
    }

    if (in_array($eventType, ['email_verification_request', 'email_verification_request_blocked'], true)) {
        $windowSeconds = min(86400, max(60, (int) $settings['email_verification_throttle_window_seconds']));
        if ($accountId !== null) {
            sr_rate_limit_increment($pdo, 'member.email_verification.account', (string) $accountId, $windowSeconds);
        }
        if ($ipAddress !== '') {
            sr_rate_limit_increment($pdo, 'member.email_verification.ip', $ipAddress, $windowSeconds);
        }
        return;
    }

    if (in_array($eventType, ['register', 'register_blocked'], true) && $ipAddress !== '') {
        $windowSeconds = min(86400, max(60, (int) $settings['register_throttle_window_seconds']));
        sr_rate_limit_increment($pdo, 'member.register.ip', $ipAddress, $windowSeconds);
    }
}
