<?php

declare(strict_types=1);

function toy_member_record_consent(PDO $pdo, int $accountId, string $consentKey, string $version, bool $consented): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO toy_member_consents (account_id, consent_key, consent_version, consented, ip_address, user_agent, created_at)
         VALUES (:account_id, :consent_key, :consent_version, :consented, :ip_address, :user_agent, :created_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'consent_key' => $consentKey,
        'consent_version' => $version,
        'consented' => $consented ? 1 : 0,
        'ip_address' => toy_client_ip(),
        'user_agent' => toy_client_user_agent(),
        'created_at' => toy_now(),
    ]);
}

function toy_member_latest_consents(PDO $pdo, int $accountId): array
{
    $stmt = $pdo->prepare(
        'SELECT c.id, c.account_id, c.consent_key, c.consent_version, c.consented, c.ip_address, c.user_agent, c.created_at
         FROM toy_member_consents c
         INNER JOIN (
            SELECT consent_key, MAX(id) AS max_id
            FROM toy_member_consents
            WHERE account_id = :account_id
            GROUP BY consent_key
         ) latest ON latest.max_id = c.id
         ORDER BY c.consent_key ASC'
    );
    $stmt->execute(['account_id' => $accountId]);

    return $stmt->fetchAll();
}

function toy_member_record_consent_withdrawals(PDO $pdo, int $accountId): int
{
    $count = 0;
    foreach (toy_member_latest_consents($pdo, $accountId) as $consent) {
        if (empty($consent['consented'])) {
            continue;
        }

        toy_member_record_consent(
            $pdo,
            $accountId,
            (string) $consent['consent_key'],
            (string) $consent['consent_version'],
            false
        );
        $count++;
    }

    return $count;
}

function toy_member_privacy_request_list_preview(?string $value, int $maxLength = 120): string
{
    $maxLength = max(1, $maxLength);
    $preview = toy_log_line_value((string) $value, $maxLength + 1);
    $length = function_exists('mb_strlen') ? mb_strlen($preview) : strlen($preview);
    if ($length <= $maxLength) {
        return $preview;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($preview, 0, $maxLength) . '...';
    }

    return substr($preview, 0, $maxLength) . '...';
}

function toy_member_privacy_export_data(PDO $pdo, int $accountId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, email, display_name, locale, status, email_verified_at, last_login_at, created_at, updated_at
         FROM toy_member_accounts
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $accountId]);
    $account = $stmt->fetch();

    if (!is_array($account)) {
        throw new RuntimeException('Account not found.');
    }

    $profile = toy_member_profile($pdo, $accountId);

    $stmt = $pdo->prepare(
        'SELECT consent_key, consent_version, consented, created_at
         FROM toy_member_consents
         WHERE account_id = :account_id
         ORDER BY id ASC'
    );
    $stmt->execute(['account_id' => $accountId]);
    $consents = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT event_type, result, ip_address, user_agent, created_at
         FROM toy_member_auth_logs
         WHERE account_id = :account_id
         ORDER BY id DESC
         LIMIT 100'
    );
    $stmt->execute(['account_id' => $accountId]);
    $authLogs = $stmt->fetchAll();

    $sessions = [];
    if (toy_member_sessions_table_exists($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT ip_address, user_agent, expires_at, revoked_at, created_at, last_seen_at
             FROM toy_member_sessions
             WHERE account_id = :account_id
             ORDER BY id DESC
             LIMIT 100'
        );
        $stmt->execute(['account_id' => $accountId]);
        $sessions = $stmt->fetchAll();
    }

    return [
        'exported_at' => toy_now(),
        'account' => $account,
        'profile' => $profile,
        'groups' => toy_member_group_privacy_export($pdo, $accountId),
        'consents' => $consents,
        'auth_logs' => $authLogs,
        'sessions' => $sessions,
    ];
}

function toy_member_privacy_export_reauth_errors(PDO $pdo, array $account): array
{
    $password = toy_post_string('current_password', 255);
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1) {
        return ['재인증 계정을 확인할 수 없습니다.'];
    }

    $throttle = toy_member_reauth_throttle_status($pdo, $accountId);
    if (!empty($throttle['limited'])) {
        toy_member_log_auth($pdo, $accountId, 'reauth_blocked', 'failure');
        toy_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'member',
            'event_type' => 'privacy.export.reauth_blocked',
            'target_type' => 'member_account',
            'target_id' => (string) $accountId,
            'result' => 'failure',
            'message' => 'Member privacy export reauthentication blocked by throttle.',
        ]);
        return ['비밀번호 확인 시도가 많습니다. 잠시 후 다시 시도하세요.'];
    }

    if ($password === '' || !password_verify($password, (string) ($account['password_hash'] ?? ''))) {
        toy_member_log_auth($pdo, $accountId, 'privacy_export_reauth', 'failure');
        toy_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'member',
            'event_type' => 'privacy.export.reauth_failed',
            'target_type' => 'member_account',
            'target_id' => (string) $accountId,
            'result' => 'failure',
            'message' => 'Member privacy export reauthentication failed.',
        ]);
        return ['개인정보 사본을 내려받기 전 현재 비밀번호를 다시 입력하세요.'];
    }

    toy_member_log_auth($pdo, $accountId, 'privacy_export_reauth', 'success');
    return [];
}
