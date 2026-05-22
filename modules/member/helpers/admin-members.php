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

    if ($field === 'all') {
        $accountId = sr_admin_member_account_id_from_identifier($pdo, $config, $keyword);
        if ($accountId > 0) {
            return $accountId;
        }

        $email = sr_normalize_identifier($keyword);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE email_hash = :email_hash LIMIT 1');
            $stmt->execute(['email_hash' => sr_hmac_hash($email, $config)]);
            $row = $stmt->fetch();
            if (is_array($row)) {
                return (int) $row['id'];
            }
        }

        $loginId = sr_member_normalize_login_id($keyword);
        if (sr_member_is_valid_login_id($loginId)) {
            $loginIdHash = sr_hmac_hash($loginId, $config);
            $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE login_id_hash = :login_id_hash OR account_identifier_hash = :login_id_hash LIMIT 1');
            $stmt->execute(['login_id_hash' => $loginIdHash]);
            $row = $stmt->fetch();
            if (is_array($row)) {
                return (int) $row['id'];
            }
        }

        $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE display_name = :display_name ORDER BY id ASC LIMIT 1');
        $stmt->execute(['display_name' => $keyword]);
        $row = $stmt->fetch();
        return is_array($row) ? (int) $row['id'] : 0;
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

    if ($field === 'login_id') {
        $loginId = sr_member_normalize_login_id($keyword);
        if (!sr_member_is_valid_login_id($loginId)) {
            return 0;
        }

        $loginIdHash = sr_hmac_hash($loginId, $config);
        $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE login_id_hash = :login_id_hash OR account_identifier_hash = :login_id_hash LIMIT 1');
        $stmt->execute(['login_id_hash' => $loginIdHash]);
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

function sr_admin_member_account_lookup_filter(PDO $pdo, array $config): array
{
    $field = sr_get_string('field', 30);
    $keyword = trim(sr_get_string('q', 120));
    $allowedFields = ['all', 'hash', 'email', 'login_id', 'name'];
    if (!in_array($field, $allowedFields, true)) {
        $field = 'all';
    }

    $legacyIdentifier = sr_get_string('account_identifier', 80);
    if ($legacyIdentifier === '') {
        $legacyIdentifier = sr_get_string('account_id', 80);
    }
    if ($keyword === '' && $legacyIdentifier !== '') {
        $field = 'hash';
        $keyword = $legacyIdentifier;
    }

    return [
        'field' => $field,
        'keyword' => $keyword,
        'account_id' => sr_admin_member_account_id_from_lookup($pdo, $config, $field, $keyword),
    ];
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

function sr_admin_member_search_rows(PDO $pdo, array $config, string $field, string $keyword, int $limit = 20): array
{
    $allowedFields = ['all', 'hash', 'email', 'login_id', 'name'];
    if (!in_array($field, $allowedFields, true)) {
        $field = 'all';
    }

    $keyword = trim($keyword);
    $limit = max(1, min(30, $limit));
    $params = [];
    $where = [];
    $accountId = 0;
    if ($keyword !== '') {
        $accountId = sr_admin_member_account_id_from_lookup($pdo, $config, $field, $keyword);
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';

        if ($field === 'hash' || $field === 'login_id') {
            $where[] = $accountId > 0 ? 'id = :account_id' : '1 = 0';
            if ($accountId > 0) {
                $params['account_id'] = $accountId;
            }
        } elseif ($field === 'email') {
            $where[] = "email LIKE :keyword_like ESCAPE '\\\\'";
            $params['keyword_like'] = $like;
        } elseif ($field === 'name') {
            $where[] = "display_name LIKE :keyword_like ESCAPE '\\\\'";
            $params['keyword_like'] = $like;
        } else {
            $clauses = ["email LIKE :keyword_email_like ESCAPE '\\\\'", "display_name LIKE :keyword_name_like ESCAPE '\\\\'"];
            $params['keyword_email_like'] = $like;
            $params['keyword_name_like'] = $like;
            if ($accountId > 0) {
                $clauses[] = 'id = :account_id';
                $params['account_id'] = $accountId;
            }
            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }
    }

    $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
    $stmt = $pdo->prepare(
        'SELECT id, email, display_name, status, created_at
         FROM sr_member_accounts
         ' . $whereSql . '
         ORDER BY id DESC
         LIMIT ' . $limit
    );
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }

        $accountId = (int) ($row['id'] ?? 0);
        $rows[] = [
            'id' => $accountId,
            'account_public_hash' => $accountId > 0 ? sr_admin_member_public_hash($config, $accountId) : '',
            'display_name' => sr_admin_member_display_name_preview($row),
            'email' => sr_admin_member_email_display($row),
            'status' => (string) ($row['status'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    return $rows;
}

function sr_admin_member_by_id(PDO $pdo, int $accountId): ?array
{
    if ($accountId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, account_identifier_hash, login_id_hash, email, email_hash, display_name, locale, status, email_verified_at, last_login_at, created_at, updated_at
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
        $errors[] = sr_t('member::action.register.email_too_long');
    }

    if ($loginIdInput === null) {
        $errors[] = sr_t('member::action.register.login_id_too_long');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = sr_t('member::action.register.email_invalid');
    }

    if ($loginId !== '' && !sr_member_is_valid_login_id($loginId)) {
        $errors[] = sr_t('member::action.register.login_id_invalid');
    }

    if ($displayName === '') {
        $errors[] = sr_t('member::action.admin.name_required');
    }

    if ($password === null || $passwordConfirm === null) {
        $errors[] = sr_t('member::action.register.password_too_long');
        $password = '';
        $passwordConfirm = '';
    }

    if (strlen((string) $password) < 8) {
        $errors[] = sr_t('member::action.register.password_too_short');
    }

    if ($password !== $passwordConfirm) {
        $errors[] = sr_t('member::action.register.password_confirm_mismatch');
    }

    if (!in_array($locale, $supportedLocales, true)) {
        $errors[] = sr_t('member::action.account.locale_invalid');
    }

    if (!in_array($status, $allowedCreateStatuses, true)) {
        $errors[] = sr_t('member::action.admin.status_invalid');
    }

    if ($errors === []) {
        $emailHash = sr_hmac_hash($email, $runtimeConfig);
        $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE email_hash = :email_hash LIMIT 1');
        $stmt->execute(['email_hash' => $emailHash]);
        if (is_array($stmt->fetch())) {
            $errors[] = sr_t('member::action.admin.email_duplicate');
        }
    }

    if ($errors === [] && $loginId !== '') {
        $loginIdHash = sr_hmac_hash($loginId, $runtimeConfig);
        $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE account_identifier_hash = :login_id_hash OR login_id_hash = :login_id_hash LIMIT 1');
        $stmt->execute(['login_id_hash' => $loginIdHash]);
        if (is_array($stmt->fetch())) {
            $errors[] = sr_t('member::action.admin.login_id_duplicate');
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
            $errors[] = sr_t('member::action.admin.create_failed');
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

        $notice = sr_t('member::action.admin.created');
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
        $errors[] = sr_t('member::action.admin.member_required');
    }

    if (!in_array($intent, ['status', 'edit', 'revoke_sessions'], true)) {
        $errors[] = sr_t('member::action.admin.intent_invalid');
    }

    if ($intent !== 'revoke_sessions' && !in_array($status, $allowedStatuses, true)) {
        $errors[] = sr_t('member::action.admin.status_invalid');
    }

    if ($intent !== 'revoke_sessions' && $targetAccountId === (int) $account['id'] && $status !== 'active') {
        $errors[] = sr_t('member::action.admin.current_admin_disable_disallowed');
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT id, account_identifier_hash, email, email_hash, login_id_hash, display_name, locale, status FROM sr_member_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $targetAccountId]);
        $targetAccount = $stmt->fetch();

        if (!is_array($targetAccount)) {
            $errors[] = sr_t('member::action.admin.member_not_found');
        }
    }

    if ($errors === []) {
        $targetRoles = sr_admin_current_roles($pdo, $targetAccountId);
        $targetIsOwner = in_array('owner', $targetRoles, true);
        $actorIsOwner = sr_admin_has_role($pdo, (int) $account['id'], ['owner']);

        if ($targetIsOwner && !$actorIsOwner) {
            $errors[] = sr_t('member::action.admin.owner_only');
        }

        if (
            $targetIsOwner
            && $intent !== 'revoke_sessions'
            && $status !== 'active'
            && (string) $targetAccount['status'] === 'active'
            && sr_admin_active_owner_count($pdo) <= 1
        ) {
            $errors[] = sr_t('member::action.admin.last_owner_disable_disallowed');
        }
    }

    if ($errors === [] && $intent === 'revoke_sessions') {
        if ($targetAccountId === (int) $account['id']) {
            $errors[] = sr_t('member::action.admin.current_session_revoke_disallowed');
        } else {
            $revokedCount = sr_member_revoke_account_sessions($pdo, $targetAccountId);
            if ($revokedCount < 0) {
                $errors[] = sr_t('member::action.admin.session_revoke_failed');
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

                $notice = sr_t('member::action.admin.session_revoked');
            }
        }
    } elseif ($errors === [] && $intent === 'edit') {
        $runtimeConfig = sr_runtime_config();
        $supportedLocales = sr_supported_locales($site);
        $emailInput = sr_post_string_without_truncation('email', 255);
        $loginIdInput = sr_post_string_without_truncation('login_id', 40);
        $email = sr_normalize_identifier((string) ($emailInput ?? ''));
        $loginId = $loginIdInput === null ? '' : sr_member_normalize_login_id($loginIdInput);
        $clearLoginId = ($_POST['clear_login_id'] ?? '') === '1';
        $displayName = trim(sr_post_string('display_name', 120));
        $locale = sr_post_string('locale', 20);
        $resultExtra['edit_values'] = [
            'id' => $targetAccountId,
            'email' => $email,
            'login_id' => $loginId,
            'clear_login_id' => $clearLoginId ? '1' : '0',
            'display_name' => $displayName,
            'locale' => $locale,
            'status' => $status,
        ];

        if ($emailInput === null) {
            $errors[] = sr_t('member::action.register.email_too_long');
        }

        if ($loginIdInput === null) {
            $errors[] = sr_t('member::action.register.login_id_too_long');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = sr_t('member::action.register.email_invalid');
        }

        if ($loginId !== '' && !sr_member_is_valid_login_id($loginId)) {
            $errors[] = sr_t('member::action.register.login_id_invalid');
        }

        if ($displayName === '') {
            $errors[] = sr_t('member::action.admin.name_required');
        }

        if (!in_array($locale, $supportedLocales, true)) {
            $errors[] = sr_t('member::action.account.locale_invalid');
        }

        $emailHash = $errors === [] ? sr_hmac_hash($email, $runtimeConfig) : '';
        if ($errors === []) {
            $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE email_hash = :email_hash AND id <> :id LIMIT 1');
            $stmt->execute([
                'email_hash' => $emailHash,
                'id' => $targetAccountId,
            ]);
            if (is_array($stmt->fetch())) {
                $errors[] = sr_t('member::action.admin.email_duplicate');
            }
        }

        $nextLoginIdHash = null;
        if ($errors === [] && $loginId !== '') {
            $nextLoginIdHash = sr_hmac_hash($loginId, $runtimeConfig);
            $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE (login_id_hash = :login_id_hash OR account_identifier_hash = :login_id_hash) AND id <> :id LIMIT 1');
            $stmt->execute([
                'login_id_hash' => $nextLoginIdHash,
                'id' => $targetAccountId,
            ]);
            if (is_array($stmt->fetch())) {
                $errors[] = sr_t('member::action.admin.login_id_duplicate');
            }
        }

        if ($errors === []) {
            $currentAccountIdentifierHash = (string) ($targetAccount['account_identifier_hash'] ?? '');
            $currentEmailHash = (string) ($targetAccount['email_hash'] ?? '');
            $currentLoginIdHash = (string) ($targetAccount['login_id_hash'] ?? '');
            if ($clearLoginId) {
                $nextLoginIdHash = null;
                $accountIdentifierHash = $emailHash;
            } elseif ($loginId !== '') {
                $accountIdentifierHash = (string) $nextLoginIdHash;
            } elseif ($currentLoginIdHash !== '') {
                $nextLoginIdHash = $currentLoginIdHash;
                $accountIdentifierHash = (string) ($targetAccount['account_identifier_hash'] ?? '');
                if ($accountIdentifierHash === '') {
                    $accountIdentifierHash = $currentLoginIdHash;
                }
            } else {
                $nextLoginIdHash = null;
                $accountIdentifierHash = $currentAccountIdentifierHash !== ''
                    && $currentEmailHash !== ''
                    && !hash_equals($currentEmailHash, $currentAccountIdentifierHash)
                    ? $currentAccountIdentifierHash
                    : $emailHash;
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    'UPDATE sr_member_accounts
                     SET account_identifier_hash = :account_identifier_hash,
                         login_id_hash = :login_id_hash,
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
                    'login_id_hash' => $nextLoginIdHash,
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
                $errors[] = sr_t('member::action.admin.update_failed');
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
                    'login_id_changed' => $loginId !== '' || $clearLoginId,
                    'login_id_set' => $nextLoginIdHash !== null,
                ],
            ]);
            $notice = sr_t('member::action.admin.updated');
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

            $errors[] = sr_t('member::action.admin.status_save_failed');
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

            $notice = sr_t('member::action.admin.status_saved');
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
    $allowedFields = ['all', 'hash', 'email', 'login_id', 'name'];
    if (!in_array($field, $allowedFields, true)) {
        $field = 'all';
    }

    $accountId = 0;
    if ($field === 'all' || $field === 'hash' || $field === 'login_id') {
        $accountId = sr_admin_member_account_id_from_lookup($pdo, $config, $field, $keyword);
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
        if ($field === 'hash' || $field === 'login_id') {
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
