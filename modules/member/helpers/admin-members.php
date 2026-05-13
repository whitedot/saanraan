<?php

declare(strict_types=1);

function sr_admin_member_allowed_statuses(): array
{
    return ['active', 'pending', 'suspended', 'withdrawn', 'anonymized'];
}

function sr_admin_member_email_display(array $member): string
{
    $email = (string) ($member['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return sr_log_line_value($email, 80);
    }

    [$localPart, $domain] = explode('@', $email, 2);
    $prefix = function_exists('mb_substr') ? mb_substr($localPart, 0, 2) : substr($localPart, 0, 2);

    return $prefix . '***@' . $domain;
}

function sr_admin_member_display_name_preview(array $member): string
{
    return sr_log_line_value((string) ($member['display_name'] ?? ''), 80);
}

function sr_admin_member_public_hash(array $config, int $accountId): string
{
    return sr_member_public_account_hash($config, $accountId);
}

function sr_admin_member_account_id_from_identifier(PDO $pdo, array $config, string $identifier): int
{
    $identifier = strtolower(trim($identifier));
    if ($identifier === '') {
        return 0;
    }

    if (sr_member_public_account_hash_is_valid($identifier)) {
        $stmt = $pdo->query('SELECT id FROM sr_member_accounts ORDER BY id ASC');
        foreach ($stmt->fetchAll() as $row) {
            $accountId = (int) ($row['id'] ?? 0);
            if ($accountId > 0 && hash_equals($identifier, sr_admin_member_public_hash($config, $accountId))) {
                return $accountId;
            }
        }

        return 0;
    }

    if (preg_match('/\A[1-9][0-9]*\z/', $identifier) === 1) {
        return (int) $identifier;
    }

    return 0;
}

function sr_admin_member_row_with_public_hash(array $config, array $row): array
{
    $accountId = (int) ($row['account_id'] ?? ($row['id'] ?? 0));
    $row['account_public_hash'] = sr_admin_member_public_hash($config, $accountId);

    return $row;
}

function sr_admin_member_rows_with_public_hash(array $config, array $rows): array
{
    foreach ($rows as $index => $row) {
        if (is_array($row)) {
            $rows[$index] = sr_admin_member_row_with_public_hash($config, $row);
        }
    }

    return $rows;
}

function sr_admin_handle_members_post(PDO $pdo, array $account, array $allowedStatuses): array
{
    $errors = [];
    $notice = '';
    $intent = sr_post_string('intent', 40);
    $targetAccountId = sr_admin_post_positive_int('account_id');
    $status = sr_post_string('status', 30);

    if ($targetAccountId <= 0) {
        $errors[] = '회원을 선택하세요.';
    }

    if (!in_array($intent, ['status', 'revoke_sessions'], true)) {
        $errors[] = '회원 작업 값이 올바르지 않습니다.';
    }

    if ($intent !== 'revoke_sessions' && !in_array($status, $allowedStatuses, true)) {
        $errors[] = '회원 상태 값이 올바르지 않습니다.';
    }

    if ($intent !== 'revoke_sessions' && $targetAccountId === (int) $account['id'] && $status !== 'active') {
        $errors[] = '현재 로그인한 관리자 계정은 비활성화할 수 없습니다.';
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT status FROM sr_member_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $targetAccountId]);
        $targetAccount = $stmt->fetch();

        if (!is_array($targetAccount)) {
            $errors[] = '회원을 찾을 수 없습니다.';
        }
    }

    if ($errors === []) {
        $targetRoles = sr_admin_current_roles($pdo, $targetAccountId);
        $targetIsOwner = in_array('owner', $targetRoles, true);
        $actorIsOwner = sr_admin_has_role($pdo, (int) $account['id'], ['owner']);

        if ($targetIsOwner && !$actorIsOwner) {
            $errors[] = '소유자 계정 상태와 세션은 소유자만 변경할 수 있습니다.';
        }

        if (
            $targetIsOwner
            && $intent !== 'revoke_sessions'
            && $status !== 'active'
            && (string) $targetAccount['status'] === 'active'
            && sr_admin_active_owner_count($pdo) <= 1
        ) {
            $errors[] = '마지막 활성 소유자 계정은 비활성화할 수 없습니다.';
        }
    }

    if ($errors === [] && $intent === 'revoke_sessions') {
        if ($targetAccountId === (int) $account['id']) {
            $errors[] = '현재 로그인한 관리자 계정의 세션은 여기서 폐기할 수 없습니다.';
        } else {
            $revokedCount = sr_member_revoke_account_sessions($pdo, $targetAccountId);
            if ($revokedCount < 0) {
                $errors[] = '회원 세션을 폐기할 수 없습니다.';
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'member.sessions.revoked',
                    'target_type' => 'member_account',
                    'target_id' => (string) $targetAccountId,
                    'result' => 'failure',
                    'message' => 'Member sessions could not be revoked.',
                ]);
            } else {
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'member.sessions.revoked',
                    'target_type' => 'member_account',
                    'target_id' => (string) $targetAccountId,
                    'result' => 'success',
                    'message' => 'Member sessions revoked.',
                    'metadata' => [
                        'revoked_count' => $revokedCount,
                    ],
                ]);

                $notice = '회원 세션을 폐기했습니다.';
            }
        }
    } elseif ($errors === []) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE sr_member_accounts
                 SET status = :status, updated_at = :updated_at
                 WHERE id = :id'
            );
            $stmt->execute([
                'status' => $status,
                'updated_at' => sr_now(),
                'id' => $targetAccountId,
            ]);
            $revokedSessions = $status === 'active' ? 0 : sr_member_revoke_account_sessions($pdo, $targetAccountId);
            if ($revokedSessions < 0) {
                throw new RuntimeException('Member sessions could not be revoked after status update.');
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = '회원 상태를 저장할 수 없습니다.';
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'member.status.updated',
                'target_type' => 'member_account',
                'target_id' => (string) $targetAccountId,
                'result' => 'failure',
                'message' => 'Member status update failed.',
                'metadata' => [
                    'before_status' => (string) $targetAccount['status'],
                    'after_status' => $status,
                    'reason' => 'session_revoke_failed',
                ],
            ]);
        }

        if ($errors === []) {
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'member.status.updated',
                'target_type' => 'member_account',
                'target_id' => (string) $targetAccountId,
                'result' => 'success',
                'message' => 'Member status updated.',
                'metadata' => [
                    'before_status' => (string) $targetAccount['status'],
                    'after_status' => $status,
                    'revoked_sessions' => $revokedSessions,
                ],
            ]);

            $notice = '회원 상태를 저장했습니다.';
        }
    }

    return sr_admin_action_result($errors, $notice);
}

function sr_admin_member_status_filter(array $allowedStatuses): string
{
    $statusFilter = sr_get_string('status', 30);
    if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
        return '';
    }

    return $statusFilter;
}

function sr_admin_members(PDO $pdo, string $statusFilter): array
{
    $members = [];
    $hasSessionTable = sr_member_sessions_table_exists($pdo);
    if ($statusFilter !== '' && $hasSessionTable) {
        $stmt = $pdo->prepare(
            'SELECT a.id, a.email, a.display_name, a.locale, a.status, a.email_verified_at, a.last_login_at, a.created_at, a.updated_at,
                    COUNT(s.id) AS active_session_count
             FROM sr_member_accounts a
             LEFT JOIN sr_member_sessions s ON s.account_id = a.id AND s.revoked_at IS NULL AND s.expires_at >= :now
             WHERE a.status = :status
             GROUP BY a.id, a.email, a.display_name, a.locale, a.status, a.email_verified_at, a.last_login_at, a.created_at, a.updated_at
             ORDER BY a.id DESC
             LIMIT 50'
        );
        $stmt->execute([
            'status' => $statusFilter,
            'now' => sr_now(),
        ]);
    } elseif ($hasSessionTable) {
        $stmt = $pdo->prepare(
            'SELECT a.id, a.email, a.display_name, a.locale, a.status, a.email_verified_at, a.last_login_at, a.created_at, a.updated_at,
                    COUNT(s.id) AS active_session_count
             FROM sr_member_accounts a
             LEFT JOIN sr_member_sessions s ON s.account_id = a.id AND s.revoked_at IS NULL AND s.expires_at >= :now
             GROUP BY a.id, a.email, a.display_name, a.locale, a.status, a.email_verified_at, a.last_login_at, a.created_at, a.updated_at
             ORDER BY a.id DESC
             LIMIT 50'
        );
        $stmt->execute(['now' => sr_now()]);
    } elseif ($statusFilter !== '') {
        $stmt = $pdo->prepare(
            'SELECT id, email, display_name, locale, status, email_verified_at, last_login_at, created_at, updated_at, 0 AS active_session_count
             FROM sr_member_accounts
             WHERE status = :status
             ORDER BY id DESC
             LIMIT 50'
        );
        $stmt->execute(['status' => $statusFilter]);
    } else {
        $stmt = $pdo->query(
            'SELECT id, email, display_name, locale, status, email_verified_at, last_login_at, created_at, updated_at, 0 AS active_session_count
             FROM sr_member_accounts
             ORDER BY id DESC
             LIMIT 50'
        );
    }

    foreach ($stmt->fetchAll() as $row) {
        $members[] = $row;
    }

    return $members;
}
