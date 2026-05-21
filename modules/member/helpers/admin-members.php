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

function sr_admin_member_account_id_from_lookup(PDO $pdo, array $config, string $field, string $keyword): int
{
    $keyword = trim($keyword);
    if ($keyword === '') {
        return 0;
    }

    if ($field === 'hash' || $field === '') {
        return sr_admin_member_account_id_from_identifier($pdo, $config, $keyword);
    }

    if ($field === 'email') {
        $email = sr_normalize_identifier($keyword);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 0;
        }

        $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE email_hash = :email_hash LIMIT 1');
        $stmt->execute(['email_hash' => sr_hmac_hash($email, $config)]);
        $row = $stmt->fetch();
        return is_array($row) ? (int) $row['id'] : 0;
    }

    if ($field === 'name') {
        $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE display_name = :display_name ORDER BY id ASC LIMIT 1');
        $stmt->execute(['display_name' => $keyword]);
        $row = $stmt->fetch();
        return is_array($row) ? (int) $row['id'] : 0;
    }

    return sr_admin_member_account_id_from_identifier($pdo, $config, $keyword);
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

function sr_admin_member_by_id(PDO $pdo, int $accountId): ?array
{
    if ($accountId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, email, display_name, locale, status, email_verified_at, last_login_at, created_at, updated_at
         FROM sr_member_accounts
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $accountId]);
    $member = $stmt->fetch();

    return is_array($member) ? $member : null;
}

function sr_admin_member_create_allowed_statuses(): array
{
    return ['active', 'pending', 'suspended'];
}

function sr_admin_member_create_default_values(array $site = []): array
{
    $supportedLocales = sr_supported_locales($site);
    $defaultLocale = trim((string) ($site['default_locale'] ?? ''));
    if (!in_array($defaultLocale, $supportedLocales, true)) {
        $defaultLocale = $supportedLocales[0] ?? 'ko';
    }

    return [
        'email' => '',
        'login_id' => '',
        'display_name' => '',
        'locale' => $defaultLocale,
        'status' => 'active',
        'email_verified' => '1',
    ];
}

function sr_admin_member_create_values_from_post(array $site = []): array
{
    $values = sr_admin_member_create_default_values($site);
    $email = sr_post_string_without_truncation('email', 255);
    $loginId = sr_post_string_without_truncation('login_id', 40);

    $values['email'] = $email === null ? '' : $email;
    $values['login_id'] = $loginId === null ? '' : sr_member_normalize_login_id($loginId);
    $values['display_name'] = sr_post_string('display_name', 120);
    $values['locale'] = sr_post_string('locale', 20);
    $values['status'] = sr_post_string('status', 30);
    $values['email_verified'] = ($_POST['email_verified'] ?? '') === '1' ? '1' : '0';

    return $values;
}

function sr_admin_handle_member_create_post(PDO $pdo, array $account, array $site = []): array
{
    $errors = [];
    $notice = '';
    $runtimeConfig = sr_runtime_config();
    $allowedCreateStatuses = sr_admin_member_create_allowed_statuses();
    $supportedLocales = sr_supported_locales($site);
    $values = sr_admin_member_create_values_from_post($site);

    $emailInput = sr_post_string_without_truncation('email', 255);
    $loginIdInput = sr_post_string_without_truncation('login_id', 40);
    $password = sr_post_string_without_truncation('password', 255);
    $passwordConfirm = sr_post_string_without_truncation('password_confirm', 255);

    $email = sr_normalize_identifier((string) $values['email']);
    $loginId = sr_member_normalize_login_id((string) $values['login_id']);
    $displayName = trim((string) $values['display_name']);
    $locale = (string) $values['locale'];
    $status = (string) $values['status'];
    $emailVerified = (string) $values['email_verified'] === '1';

    $values['email'] = $email;
    $values['login_id'] = $loginId;
    $values['display_name'] = $displayName;

    if ($emailInput === null) {
        $errors[] = '이메일은 255자 이하로 입력하세요.';
    }

    if ($loginIdInput === null) {
        $errors[] = '로그인 아이디는 40자 이하로 입력하세요.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '이메일 형식이 올바르지 않습니다.';
    }

    if ($loginId !== '' && !sr_member_is_valid_login_id($loginId)) {
        $errors[] = '로그인 아이디는 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄을 포함한 4~40자여야 합니다.';
    }

    if ($displayName === '') {
        $errors[] = '이름을 입력하세요.';
    }

    if ($password === null || $passwordConfirm === null) {
        $errors[] = '비밀번호는 255자 이하로 입력하세요.';
        $password = '';
        $passwordConfirm = '';
    }

    if (strlen((string) $password) < 8) {
        $errors[] = '비밀번호는 8자 이상이어야 합니다.';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = '비밀번호 확인이 일치하지 않습니다.';
    }

    if (!in_array($locale, $supportedLocales, true)) {
        $errors[] = '선호 locale 값이 올바르지 않습니다.';
    }

    if (!in_array($status, $allowedCreateStatuses, true)) {
        $errors[] = '회원 상태 값이 올바르지 않습니다.';
    }

    if ($errors === []) {
        $emailHash = sr_hmac_hash($email, $runtimeConfig);
        $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE email_hash = :email_hash LIMIT 1');
        $stmt->execute(['email_hash' => $emailHash]);
        if (is_array($stmt->fetch())) {
            $errors[] = '이미 사용 중인 이메일입니다.';
        }
    }

    if ($errors === [] && $loginId !== '') {
        $loginIdHash = sr_hmac_hash($loginId, $runtimeConfig);
        $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE account_identifier_hash = :login_id_hash OR login_id_hash = :login_id_hash LIMIT 1');
        $stmt->execute(['login_id_hash' => $loginIdHash]);
        if (is_array($stmt->fetch())) {
            $errors[] = '이미 사용 중인 로그인 아이디입니다.';
        }
    }

    $createdAccountId = 0;
    if ($errors === []) {
        try {
            $createdAccountId = sr_member_create_account($pdo, $runtimeConfig, [
                'email' => $email,
                'login_id' => $loginId,
                'password' => (string) $password,
                'display_name' => $displayName,
                'locale' => $locale,
                'status' => $status,
                'email_verified_at' => $emailVerified ? sr_now() : null,
            ]);
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'admin_member_create');
            $errors[] = '이미 사용 중인 계정 정보이거나 회원을 추가할 수 없습니다.';
        }
    }

    if ($errors === [] && $createdAccountId > 0) {
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'member.account.created',
            'target_type' => 'member_account',
            'target_id' => (string) $createdAccountId,
            'result' => 'success',
            'message' => 'Member account created by admin.',
            'metadata' => [
                'status' => $status,
                'login_id_set' => $loginId !== '',
                'email_verified' => $emailVerified,
            ],
        ]);

        $notice = '회원을 추가했습니다.';
        $values = sr_admin_member_create_default_values($site);
    }

    return sr_admin_action_result($errors, $notice) + [
        'create_values' => $values,
        'created_account_id' => $createdAccountId,
    ];
}

function sr_admin_handle_members_post(PDO $pdo, array $account, array $allowedStatuses, array $site = []): array
{
    $errors = [];
    $notice = '';
    $resultExtra = [];
    $intent = sr_post_string('intent', 40);

    if ($intent === 'create') {
        return sr_admin_handle_member_create_post($pdo, $account, $site);
    }

    $targetAccountId = sr_admin_post_positive_int('account_id');
    $status = sr_post_string('status', 30);

    if ($targetAccountId <= 0) {
        $errors[] = '회원을 선택하세요.';
    }

    if (!in_array($intent, ['status', 'edit', 'revoke_sessions'], true)) {
        $errors[] = '회원 작업 값이 올바르지 않습니다.';
    }

    if ($intent !== 'revoke_sessions' && !in_array($status, $allowedStatuses, true)) {
        $errors[] = '회원 상태 값이 올바르지 않습니다.';
    }

    if ($intent !== 'revoke_sessions' && $targetAccountId === (int) $account['id'] && $status !== 'active') {
        $errors[] = '현재 로그인한 관리자 계정은 비활성화할 수 없습니다.';
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT id, account_identifier_hash, email, email_hash, login_id_hash, display_name, locale, status FROM sr_member_accounts WHERE id = :id LIMIT 1');
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
    } elseif ($errors === [] && $intent === 'edit') {
        $runtimeConfig = sr_runtime_config();
        $supportedLocales = sr_supported_locales($site);
        $email = sr_normalize_identifier(sr_post_string('email', 255));
        $displayName = trim(sr_post_string('display_name', 120));
        $locale = sr_post_string('locale', 20);
        $resultExtra['edit_values'] = [
            'id' => $targetAccountId,
            'email' => $email,
            'display_name' => $displayName,
            'locale' => $locale,
            'status' => $status,
        ];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '이메일 형식이 올바르지 않습니다.';
        }

        if ($displayName === '') {
            $errors[] = '이름을 입력하세요.';
        }

        if (!in_array($locale, $supportedLocales, true)) {
            $errors[] = '선호 locale 값이 올바르지 않습니다.';
        }

        $emailHash = $errors === [] ? sr_hmac_hash($email, $runtimeConfig) : '';
        if ($errors === []) {
            $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE email_hash = :email_hash AND id <> :id LIMIT 1');
            $stmt->execute([
                'email_hash' => $emailHash,
                'id' => $targetAccountId,
            ]);
            if (is_array($stmt->fetch())) {
                $errors[] = '이미 사용 중인 이메일입니다.';
            }
        }

        if ($errors === []) {
            $accountIdentifierHash = (string) ($targetAccount['login_id_hash'] ?? '') === ''
                ? $emailHash
                : (string) ($targetAccount['account_identifier_hash'] ?? '');
            if ($accountIdentifierHash === '') {
                $accountIdentifierHash = $emailHash;
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    'UPDATE sr_member_accounts
                     SET account_identifier_hash = :account_identifier_hash,
                         email = :email,
                         email_hash = :email_hash,
                         display_name = :display_name,
                         locale = :locale,
                         status = :status,
                         updated_at = :updated_at
                     WHERE id = :id'
                );
                $stmt->execute([
                    'account_identifier_hash' => $accountIdentifierHash,
                    'email' => $email,
                    'email_hash' => $emailHash,
                    'display_name' => $displayName,
                    'locale' => $locale,
                    'status' => $status,
                    'updated_at' => sr_now(),
                    'id' => $targetAccountId,
                ]);

                $revokedSessions = $status === 'active' ? 0 : sr_member_revoke_account_sessions($pdo, $targetAccountId);
                if ($revokedSessions < 0) {
                    throw new RuntimeException('Member sessions could not be revoked after account update.');
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = '회원 정보를 저장할 수 없습니다.';
            }
        }

        if ($errors === []) {
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'member.account.updated',
                'target_type' => 'member_account',
                'target_id' => (string) $targetAccountId,
                'result' => 'success',
                'message' => 'Member account updated by admin.',
                'metadata' => [
                    'before_status' => (string) $targetAccount['status'],
                    'after_status' => $status,
                    'email_changed' => $email !== (string) $targetAccount['email'],
                ],
            ]);
            $notice = '회원 정보를 저장했습니다.';
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

    return sr_admin_action_result($errors, $notice) + $resultExtra;
}

function sr_admin_member_status_filter(array $allowedStatuses): string
{
    $statusFilter = sr_get_string('status', 30);
    if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
        return '';
    }

    return $statusFilter;
}

function sr_admin_member_search_filter(PDO $pdo, array $config): array
{
    $field = sr_get_string('field', 30);
    $keyword = trim(sr_get_string('q', 120));
    $allowedFields = ['all', 'hash', 'email', 'name'];
    if (!in_array($field, $allowedFields, true)) {
        $field = 'all';
    }

    $accountId = 0;
    if (($field === 'all' || $field === 'hash') && sr_member_public_account_hash_is_valid($keyword)) {
        $accountId = sr_admin_member_account_id_from_identifier($pdo, $config, $keyword);
    }

    return [
        'field' => $field,
        'keyword' => $keyword,
        'account_id' => $accountId,
    ];
}

function sr_admin_member_status_counts(PDO $pdo): array
{
    $counts = [
        'total' => 0,
        'active' => 0,
        'pending' => 0,
        'suspended' => 0,
        'withdrawn' => 0,
        'anonymized' => 0,
    ];

    $stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_member_accounts GROUP BY status');
    foreach ($stmt->fetchAll() as $row) {
        $status = (string) ($row['status'] ?? '');
        $count = (int) ($row['count_value'] ?? 0);
        if (array_key_exists($status, $counts)) {
            $counts[$status] = $count;
        }
        $counts['total'] += $count;
    }

    return $counts;
}

function sr_admin_members(PDO $pdo, string $statusFilter, array $searchFilter = []): array
{
    $members = [];
    $hasSessionTable = sr_member_sessions_table_exists($pdo);
    $params = [];
    $where = [];

    if ($statusFilter !== '') {
        $where[] = 'a.status = :status';
        $params['status'] = $statusFilter;
    }

    $field = (string) ($searchFilter['field'] ?? 'all');
    $keyword = trim((string) ($searchFilter['keyword'] ?? ''));
    $accountId = (int) ($searchFilter['account_id'] ?? 0);
    if ($keyword !== '') {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $keyword) . '%';
        if ($field === 'hash') {
            $where[] = $accountId > 0 ? 'a.id = :account_id' : '1 = 0';
            if ($accountId > 0) {
                $params['account_id'] = $accountId;
            }
        } elseif ($field === 'email') {
            $where[] = 'a.email LIKE :keyword_like';
            $params['keyword_like'] = $like;
        } elseif ($field === 'name') {
            $where[] = 'a.display_name LIKE :keyword_like';
            $params['keyword_like'] = $like;
        } else {
            $clauses = ['a.email LIKE :keyword_email_like', 'a.display_name LIKE :keyword_name_like'];
            $params['keyword_email_like'] = $like;
            $params['keyword_name_like'] = $like;
            if ($accountId > 0) {
                $clauses[] = 'a.id = :account_id';
                $params['account_id'] = $accountId;
            }
            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }
    }

    $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
    if ($hasSessionTable) {
        $sql = 'SELECT a.id, a.email, a.display_name, a.locale, a.status, a.email_verified_at, a.last_login_at, a.created_at, a.updated_at,
                       COUNT(s.id) AS active_session_count
                FROM sr_member_accounts a
                LEFT JOIN sr_member_sessions s ON s.account_id = a.id AND s.revoked_at IS NULL AND s.expires_at >= :now
                ' . $whereSql . '
                GROUP BY a.id, a.email, a.display_name, a.locale, a.status, a.email_verified_at, a.last_login_at, a.created_at, a.updated_at
                ORDER BY a.id DESC
                LIMIT 50';
        $params['now'] = sr_now();
    } else {
        $sql = 'SELECT a.id, a.email, a.display_name, a.locale, a.status, a.email_verified_at, a.last_login_at, a.created_at, a.updated_at, 0 AS active_session_count
                FROM sr_member_accounts a
                ' . $whereSql . '
                ORDER BY a.id DESC
                LIMIT 50';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) {
        $members[] = $row;
    }

    return $members;
}
