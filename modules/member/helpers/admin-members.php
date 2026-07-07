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
    $status = (string) ($member['status'] ?? ($member['account_status'] ?? ''));
    if (in_array($status, sr_admin_member_terminal_statuses(), true)) {
        return sr_t('member::account.withdrawn_display_name');
    }

    if (isset($member['public_name'])) {
        return sr_log_line_value((string) $member['public_name'], 80);
    }
    if (isset($member['nickname']) && trim((string) $member['nickname']) !== '') {
        return sr_log_line_value((string) $member['nickname'], 80);
    }

    return sr_log_line_value((string) ($member['display_name'] ?? ''), 80);
}

function sr_admin_member_with_public_name(PDO $pdo, array $member): array
{
    $settings = sr_member_settings($pdo);
    $member['public_name'] = sr_member_public_name([
        'display_name' => (string) ($member['display_name'] ?? ''),
        'nickname' => (string) ($member['nickname'] ?? ''),
        'status' => (string) ($member['status'] ?? ($member['account_status'] ?? '')),
    ], $settings);

    return $member;
}

function sr_admin_member_rows_with_public_name(PDO $pdo, array $rows): array
{
    foreach ($rows as $index => $row) {
        if (is_array($row)) {
            $rows[$index] = sr_admin_member_with_public_name($pdo, $row);
        }
    }

    return $rows;
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
        $lastAccountId = 0;
        do {
            $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE id > :last_id ORDER BY id ASC LIMIT 500');
            $stmt->bindValue('last_id', $lastAccountId, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $accountId = (int) ($row['id'] ?? 0);
                if ($accountId > 0 && hash_equals($identifier, sr_admin_member_public_hash($config, $accountId))) {
                    return $accountId;
                }
                $lastAccountId = max($lastAccountId, $accountId);
            }
        } while ($rows !== []);

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
            $stmt = $pdo->prepare(
                'SELECT id
                 FROM sr_member_accounts
                 WHERE login_id_hash = :login_id_hash
                    OR account_identifier_hash = :account_identifier_hash
                 LIMIT 1'
            );
            $stmt->execute([
                'login_id_hash' => $loginIdHash,
                'account_identifier_hash' => $loginIdHash,
            ]);
            $row = $stmt->fetch();
            if (is_array($row)) {
                return (int) $row['id'];
            }
        }

        $accountIds = sr_member_public_name_lookup_account_ids($pdo, [$keyword]);
        if ($accountIds !== []) {
            return (int) $accountIds[0];
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
        $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE login_id_hash = :login_id_hash OR account_identifier_hash = :account_identifier_hash LIMIT 1');
        $stmt->execute([
            'login_id_hash' => $loginIdHash,
            'account_identifier_hash' => $loginIdHash,
        ]);
        $row = $stmt->fetch();
        return is_array($row) ? (int) $row['id'] : 0;
    }

    if ($field === 'name') {
        $accountIds = sr_member_public_name_lookup_account_ids($pdo, [$keyword]);
        if ($accountIds !== []) {
            return (int) $accountIds[0];
        }

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
        $matchesWithdrawnLabel = $keyword === sr_t('member::account.withdrawn_display_name');

        if ($field === 'hash' || $field === 'login_id') {
            $where[] = $accountId > 0 ? 'a.id = :account_id' : '1 = 0';
            if ($accountId > 0) {
                $params['account_id'] = $accountId;
            }
        } elseif ($field === 'email') {
            $where[] = "a.email LIKE :keyword_like ESCAPE '\\\\'";
            $params['keyword_like'] = $like;
        } elseif ($field === 'name') {
            $nameClauses = ["a.display_name LIKE :keyword_display_name_like ESCAPE '\\\\'", "n.nickname LIKE :keyword_nickname_like ESCAPE '\\\\'"];
            if ($matchesWithdrawnLabel) {
                $nameClauses[] = "a.status IN ('withdrawn', 'anonymized')";
            }
            $where[] = '(' . implode(' OR ', $nameClauses) . ')';
            $params['keyword_display_name_like'] = $like;
            $params['keyword_nickname_like'] = $like;
        } else {
            $clauses = ["a.email LIKE :keyword_email_like ESCAPE '\\\\'", "a.display_name LIKE :keyword_name_like ESCAPE '\\\\'", "n.nickname LIKE :keyword_nickname_like ESCAPE '\\\\'"];
            $params['keyword_email_like'] = $like;
            $params['keyword_name_like'] = $like;
            $params['keyword_nickname_like'] = $like;
            if ($accountId > 0) {
                $clauses[] = 'a.id = :account_id';
                $params['account_id'] = $accountId;
            }
            if ($matchesWithdrawnLabel) {
                $clauses[] = "a.status IN ('withdrawn', 'anonymized')";
            }
            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }
    }

    $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
    $stmt = $pdo->prepare(
        'SELECT a.id, a.email, a.display_name, a.status, a.created_at, COALESCE(n.nickname, \'\') AS nickname
         FROM sr_member_accounts a
         LEFT JOIN sr_member_nicknames n ON n.account_id = a.id
         ' . $whereSql . '
         ORDER BY a.id DESC
         LIMIT ' . $limit
    );
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }

        $accountId = (int) ($row['id'] ?? 0);
        $row = sr_admin_member_with_public_name($pdo, $row);
        $rows[] = [
            'id' => $accountId,
            'account_public_hash' => $accountId > 0 ? sr_admin_member_public_hash($config, $accountId) : '',
            'display_name' => sr_admin_member_display_name_preview($row),
            'nickname' => (string) ($row['nickname'] ?? ''),
            'public_name' => (string) ($row['public_name'] ?? ''),
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

    $join = sr_member_nicknames_table_exists($pdo) ? 'LEFT JOIN sr_member_nicknames n ON n.account_id = a.id' : '';
    $nicknameSelect = sr_member_nicknames_table_exists($pdo) ? 'COALESCE(n.nickname, \'\') AS nickname' : "'' AS nickname";
    $stmt = $pdo->prepare(
        'SELECT a.id, a.account_identifier_hash, a.login_id_hash, a.email, a.email_hash, a.display_name, a.locale, a.status, a.email_verified_at, a.last_login_at, a.created_at, a.updated_at,
                ' . $nicknameSelect . '
         FROM sr_member_accounts a
         ' . $join . '
         WHERE a.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $accountId]);
    $member = $stmt->fetch();

    return is_array($member) ? sr_admin_member_with_public_name($pdo, $member) : null;
}

function sr_admin_member_create_allowed_statuses(): array
{
    return ['active', 'pending', 'suspended'];
}

function sr_admin_member_terminal_statuses(): array
{
    return ['withdrawn', 'anonymized'];
}

function sr_admin_member_status_transition_errors(array $targetAccount, string $nextStatus): array
{
    $currentStatus = (string) ($targetAccount['status'] ?? '');
    if ($currentStatus === 'anonymized' && $nextStatus !== 'anonymized') {
        return ['익명화된 회원은 다른 상태로 되돌릴 수 없습니다.'];
    }
    if ($currentStatus === 'withdrawn' && !in_array($nextStatus, ['withdrawn', 'anonymized'], true)) {
        return ['탈퇴 처리된 회원은 활성 상태로 되돌릴 수 없습니다. 필요한 경우 새 계정을 만들어 주세요.'];
    }

    return [];
}

function sr_admin_member_apply_status_effects(PDO $pdo, array $config, int $accountId, string $beforeStatus, string $afterStatus): array
{
    $result = [
        'revoked_sessions' => 0,
        'deleted_profile' => false,
        'withdrawn_consents' => 0,
        'member_mfa' => null,
        'account_anonymized' => false,
        'privacy_cleanup' => [],
    ];

    if ($afterStatus !== 'active') {
        $revokedSessions = sr_member_revoke_account_sessions($pdo, $accountId);
        if ($revokedSessions < 0) {
            throw new RuntimeException('Member sessions could not be revoked after account status update.');
        }
        $result['revoked_sessions'] = $revokedSessions;
    }

    if (!in_array($afterStatus, sr_admin_member_terminal_statuses(), true) || $beforeStatus === $afterStatus) {
        return $result;
    }

    sr_member_delete_profile($pdo, $accountId);
    sr_member_delete_nickname($pdo, $accountId);
    $result['deleted_profile'] = true;
    $result['member_mfa'] = sr_member_delete_mfa($pdo, $accountId);
    $result['withdrawn_consents'] = sr_member_record_consent_withdrawals($pdo, $accountId);

    if ($afterStatus === 'anonymized') {
        sr_member_anonymize_account($pdo, $config, $accountId);
        $result['account_anonymized'] = true;
    }

    $result['privacy_cleanup'] = sr_member_run_privacy_cleanup_contracts($pdo, $accountId, 'member.status_' . $afterStatus);

    return $result;
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
        'nickname' => '',
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
    $values['display_name'] = sr_member_normalize_display_name(sr_post_string('display_name', 120));
    $values['nickname'] = sr_member_normalize_nickname(sr_post_string('nickname', 80));
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
    $memberSettings = sr_member_settings($pdo);
    $displayName = sr_member_normalize_display_name((string) $values['display_name']);
    $nickname = sr_member_normalize_nickname((string) ($values['nickname'] ?? ''));
    $locale = (string) $values['locale'];
    $status = (string) $values['status'];
    $emailVerified = (string) $values['email_verified'] === '1';

    $values['email'] = $email;
    $values['login_id'] = $loginId;
    $values['display_name'] = $displayName;
    $values['nickname'] = $nickname;

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

    foreach (sr_member_display_name_validation_errors($displayName) as $displayNameError) {
        $errors[] = $displayNameError;
    }

    foreach (sr_member_nickname_validation_errors($pdo, $nickname, $memberSettings) as $nicknameError) {
        $errors[] = $nicknameError;
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
        $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE account_identifier_hash = :account_identifier_hash OR login_id_hash = :login_id_hash LIMIT 1');
        $stmt->execute([
            'account_identifier_hash' => $loginIdHash,
            'login_id_hash' => $loginIdHash,
        ]);
        if (is_array($stmt->fetch())) {
            $errors[] = sr_t('member::action.admin.login_id_duplicate');
        }
    }

    $createdAccountId = 0;
    if ($errors === []) {
        try {
            $pdo->beginTransaction();
            $createdAccountId = sr_member_create_account($pdo, $runtimeConfig, [
                'email' => $email,
                'login_id' => $loginId,
                'password' => (string) $password,
                'display_name' => $displayName,
                'locale' => $locale,
                'status' => $status,
                'email_verified_at' => $emailVerified ? sr_now() : null,
            ]);
            if (!empty($memberSettings['nickname_enabled']) && $nickname !== '') {
                sr_member_set_nickname($pdo, $createdAccountId, $nickname);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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

    if (!in_array($intent, ['status', 'edit', 'revoke_sessions', 'evaluate_groups'], true)) {
        $errors[] = sr_t('member::action.admin.intent_invalid');
    }

    if (!in_array($intent, ['revoke_sessions', 'evaluate_groups'], true) && !in_array($status, $allowedStatuses, true)) {
        $errors[] = sr_t('member::action.admin.status_invalid');
    }

    if (!in_array($intent, ['revoke_sessions', 'evaluate_groups'], true) && $targetAccountId === (int) $account['id'] && $status !== 'active') {
        $errors[] = sr_t('member::action.admin.current_admin_disable_disallowed');
    }

    if ($errors === []) {
        $stmt = $pdo->prepare(
            'SELECT a.id, a.account_identifier_hash, a.email, a.email_hash, a.login_id_hash, a.display_name, a.locale, a.status,
                    a.email_verified_at, COALESCE(n.nickname, \'\') AS nickname
             FROM sr_member_accounts a
             LEFT JOIN sr_member_nicknames n ON n.account_id = a.id
             WHERE a.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $targetAccountId]);
        $targetAccount = $stmt->fetch();

        if (!is_array($targetAccount)) {
            $errors[] = sr_t('member::action.admin.member_not_found');
        }
    }

    if ($errors === []) {
        $targetRoles = sr_admin_current_roles($pdo, $targetAccountId);
        $targetIsOwner = in_array('owner', $targetRoles, true);
        $actorIsOwner = sr_admin_is_owner($pdo, (int) $account['id']);

        if ($targetIsOwner && !$actorIsOwner) {
            $errors[] = sr_t('member::action.admin.owner_only');
        }

        if (
            $targetIsOwner
            && $intent !== 'revoke_sessions'
            && $intent !== 'evaluate_groups'
            && $status !== 'active'
            && (string) $targetAccount['status'] === 'active'
            && sr_admin_active_owner_count($pdo) <= 1
        ) {
            $errors[] = sr_t('member::action.admin.last_owner_disable_disallowed');
        }

        if (!in_array($intent, ['revoke_sessions', 'evaluate_groups'], true)) {
            $errors = array_merge($errors, sr_admin_member_status_transition_errors($targetAccount, $status));
        }
    }

    if ($errors === [] && $intent === 'evaluate_groups') {
        $summary = sr_member_group_evaluate_account($pdo, $targetAccountId);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'member.group_rules.evaluated',
            'target_type' => 'member_account',
            'target_id' => (string) $targetAccountId,
            'result' => 'success',
            'message' => 'Member group rules evaluated.',
            'metadata' => $summary,
        ]);
        $notice = sr_t('member::action.admin_groups.evaluated', [
            'evaluated' => (string) $summary['evaluated'],
            'granted' => (string) $summary['granted'],
            'revoked' => (string) $summary['revoked'],
        ]);
    } elseif ($errors === [] && $intent === 'revoke_sessions') {
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
        $email = sr_normalize_identifier((string) ($emailInput ?? ''));
        $memberSettings = sr_member_settings($pdo);
        $profileExtraFieldDefinitions = sr_member_profile_extra_field_definitions($memberSettings);
        $adminProfileExtraFieldDefinitions = array_values(array_filter(
            $profileExtraFieldDefinitions,
            static function (array $definition): bool {
                return !empty($definition['show_in_admin']);
            }
        ));
        $storedProfileExtraFieldValues = sr_member_profile_extra_field_plain_values($pdo, $targetAccountId);
        $postedProfileExtraFieldValues = sr_member_profile_extra_field_input_values($adminProfileExtraFieldDefinitions);
        $profileExtraFieldValues = array_merge($storedProfileExtraFieldValues, $postedProfileExtraFieldValues);
        $displayName = sr_member_normalize_display_name(sr_post_string('display_name', 120));
        $nickname = sr_member_normalize_nickname(sr_post_string('nickname', 80));
        $locale = sr_post_string('locale', 20);
        $emailVerified = ($_POST['email_verified'] ?? '') === '1';
        $nextEmailVerifiedAt = $emailVerified
            ? ((string) ($targetAccount['email_verified_at'] ?? '') !== '' ? (string) $targetAccount['email_verified_at'] : sr_now())
            : null;
        $resultExtra['edit_values'] = [
            'id' => $targetAccountId,
            'email' => $email,
            'display_name' => $displayName,
            'nickname' => $nickname,
            'locale' => $locale,
            'status' => $status,
            'email_verified' => $emailVerified ? '1' : '0',
        ];
        $resultExtra['profile_extra_values'] = $profileExtraFieldValues;

        if ($emailInput === null) {
            $errors[] = sr_t('member::action.register.email_too_long');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = sr_t('member::action.register.email_invalid');
        }

        foreach (sr_member_display_name_validation_errors($displayName) as $displayNameError) {
            $errors[] = $displayNameError;
        }

        foreach (sr_member_nickname_validation_errors($pdo, $nickname, $memberSettings, $targetAccountId) as $nicknameError) {
            $errors[] = $nicknameError;
        }

        foreach (sr_member_validate_profile_extra_field_values($adminProfileExtraFieldDefinitions, $postedProfileExtraFieldValues) as $profileError) {
            $errors[] = $profileError;
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
        $currentHasLegacyLoginId = false;
        if ($errors === []) {
            $currentAccountIdentifierHash = (string) ($targetAccount['account_identifier_hash'] ?? '');
            $currentEmailHash = (string) ($targetAccount['email_hash'] ?? '');
            $currentLoginIdHash = (string) ($targetAccount['login_id_hash'] ?? '');
            $currentHasLegacyLoginId = $currentAccountIdentifierHash !== ''
                && $currentEmailHash !== ''
                && !hash_equals($currentEmailHash, $currentAccountIdentifierHash);
            if ($currentLoginIdHash !== '') {
                $nextLoginIdHash = $currentLoginIdHash;
                $accountIdentifierHash = (string) ($targetAccount['account_identifier_hash'] ?? '');
                if ($accountIdentifierHash === '') {
                    $accountIdentifierHash = $currentLoginIdHash;
                }
            } elseif ($currentHasLegacyLoginId) {
                $nextLoginIdHash = null;
                $accountIdentifierHash = $currentAccountIdentifierHash;
            } else {
                $nextLoginIdHash = null;
                $accountIdentifierHash = $emailHash;
            }

            $statusEffects = [];
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
                    'status' => $status,
                    'email_verified_at' => $nextEmailVerifiedAt,
                    'updated_at' => sr_now(),
                    'id' => $targetAccountId,
                ]);
                if (!empty($memberSettings['nickname_enabled']) && !in_array($status, ['withdrawn', 'anonymized'], true)) {
                    sr_member_set_nickname($pdo, $targetAccountId, $nickname);
                } else {
                    sr_member_delete_nickname($pdo, $targetAccountId);
                }
                if ($profileExtraFieldDefinitions !== []) {
                    sr_member_save_profile_extra_field_values($pdo, $targetAccountId, $profileExtraFieldDefinitions, $profileExtraFieldValues);
                }

                $statusEffects = sr_admin_member_apply_status_effects($pdo, $runtimeConfig, $targetAccountId, (string) $targetAccount['status'], $status);
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
                    'email_verified_changed' => (string) ($targetAccount['email_verified_at'] ?? '') !== (string) ($nextEmailVerifiedAt ?? ''),
                    'nickname_changed' => $nickname !== (string) ($targetAccount['nickname'] ?? ''),
                    'login_id_changed' => false,
                    'login_id_set' => $nextLoginIdHash !== null || $currentHasLegacyLoginId,
                    'profile_extra_fields_changed' => $postedProfileExtraFieldValues !== array_intersect_key($storedProfileExtraFieldValues, $postedProfileExtraFieldValues),
                    'status_effects' => $statusEffects,
                ],
            ]);
            $notice = sr_t('member::action.admin.updated');
        }
    } elseif ($errors === []) {
        $runtimeConfig = sr_runtime_config();
        $statusEffects = [];
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
            $statusEffects = sr_admin_member_apply_status_effects($pdo, $runtimeConfig, $targetAccountId, (string) $targetAccount['status'], $status);
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
                    'status_effects' => $statusEffects,
                ],
            ]);

            $notice = sr_t('member::action.admin.status_saved');
        }
    }

    return sr_admin_action_result($errors, $notice) + $resultExtra;
}

function sr_admin_handle_member_batch_revoke_sessions_post(PDO $pdo, array $account): array
{
    $errors = [];
    $operationKey = sr_post_string('operation_key', 80);
    $rawSelectedIds = $_POST['selected_account_ids'] ?? [];
    $selectedIds = sr_admin_positive_int_list_from_input($rawSelectedIds, $hasInvalidSelectedId);

    if ($operationKey !== 'member.revoke_sessions') {
        $errors[] = '허용되지 않은 일괄 작업입니다.';
    }
    if ($selectedIds === []) {
        $errors[] = '세션을 회수할 회원을 선택하세요.';
    }
    if ($hasInvalidSelectedId) {
        $errors[] = '선택한 회원 값이 올바르지 않습니다.';
    }
    if (count($selectedIds) > 100) {
        $errors[] = '회원 세션 일괄 회수는 한 번에 100건 이하로 실행하세요.';
    }
    if (in_array((int) $account['id'], $selectedIds, true)) {
        $errors[] = sr_t('member::action.admin.current_session_revoke_disallowed');
    }

    $selectedAccounts = [];
    if ($errors === []) {
        $placeholders = [];
        $params = [];
        foreach ($selectedIds as $index => $selectedId) {
            $paramKey = 'account_id_' . (string) $index;
            $placeholders[] = ':' . $paramKey;
            $params[$paramKey] = $selectedId;
        }
        $stmt = $pdo->prepare(
            'SELECT id, status
             FROM sr_member_accounts
             WHERE id IN (' . implode(', ', $placeholders) . ')
             ORDER BY id ASC'
        );
        foreach ($params as $paramKey => $selectedId) {
            $stmt->bindValue($paramKey, $selectedId, PDO::PARAM_INT);
        }
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            $selectedAccounts[(int) ($row['id'] ?? 0)] = $row;
        }
        if (count($selectedAccounts) !== count($selectedIds)) {
            $errors[] = '선택한 회원 중 찾을 수 없는 계정이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
        }
    }

    if ($errors === []) {
        $actorIsOwner = sr_admin_is_owner($pdo, (int) $account['id']);
        $blockedOwnerIds = [];
        foreach ($selectedIds as $selectedId) {
            $targetRoles = sr_admin_current_roles($pdo, $selectedId);
            if (in_array('owner', $targetRoles, true) && !$actorIsOwner) {
                $blockedOwnerIds[] = $selectedId;
            }
        }
        if ($blockedOwnerIds !== []) {
            $errors[] = '매니저 권한 회원의 세션은 매니저만 회수할 수 있습니다: ' . implode(', ', array_map('strval', $blockedOwnerIds));
        }
    }

    if ($errors !== []) {
        return sr_admin_action_result($errors, '');
    }

    $revokedCounts = [];
    $revokedTotal = 0;
    try {
        $pdo->beginTransaction();
        foreach ($selectedIds as $selectedId) {
            $revokedCount = sr_member_revoke_account_sessions($pdo, $selectedId);
            if ($revokedCount < 0) {
                throw new RuntimeException('Member sessions could not be revoked.');
            }
            $revokedCounts[(string) $selectedId] = $revokedCount;
            $revokedTotal += $revokedCount;
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'member.sessions.batch_revoked',
            'target_type' => 'member_account',
            'target_id' => 'batch',
            'result' => 'failure',
            'message' => 'Member sessions could not be revoked.',
            'metadata' => [
                'operation_key' => $operationKey,
                'selected_ids' => $selectedIds,
            ],
        ]);

        return sr_admin_action_result([sr_t('member::action.admin.session_revoke_failed')], '');
    }

    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => 'member.sessions.batch_revoked',
        'target_type' => 'member_account',
        'target_id' => 'batch',
        'result' => 'success',
        'message' => 'Member sessions revoked.',
        'metadata' => [
            'operation_key' => $operationKey,
            'selected_ids' => $selectedIds,
            'revoked_counts' => $revokedCounts,
            'revoked_total' => $revokedTotal,
        ],
    ]);

    return sr_admin_action_result([], '선택한 회원 ' . (string) count($selectedIds) . '명의 세션을 회수했습니다. 회수된 세션: ' . (string) $revokedTotal . '건');
}

function sr_admin_member_status_filter(array $allowedStatuses): array
{
    return sr_admin_get_allowed_array('status', $allowedStatuses, 30);
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

function sr_admin_member_query_parts(array $statusFilter, array $searchFilter = []): array
{
    $params = [];
    $where = [];

    if ($statusFilter !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('a.status', 'status', $statusFilter);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $field = (string) ($searchFilter['field'] ?? 'all');
    $keyword = trim((string) ($searchFilter['keyword'] ?? ''));
    $accountId = (int) ($searchFilter['account_id'] ?? 0);
    if ($keyword !== '') {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $keyword) . '%';
        $matchesWithdrawnLabel = $keyword === sr_t('member::account.withdrawn_display_name');
        if ($field === 'hash' || $field === 'login_id') {
            $where[] = $accountId > 0 ? 'a.id = :account_id' : '1 = 0';
            if ($accountId > 0) {
                $params['account_id'] = $accountId;
            }
        } elseif ($field === 'email') {
            $where[] = 'a.email LIKE :keyword_like';
            $params['keyword_like'] = $like;
        } elseif ($field === 'name') {
            $nameClauses = ['a.display_name LIKE :keyword_display_name_like', 'n.nickname LIKE :keyword_nickname_like'];
            if ($matchesWithdrawnLabel) {
                $nameClauses[] = "a.status IN ('withdrawn', 'anonymized')";
            }
            $where[] = '(' . implode(' OR ', $nameClauses) . ')';
            $params['keyword_display_name_like'] = $like;
            $params['keyword_nickname_like'] = $like;
        } else {
            $clauses = ['a.email LIKE :keyword_email_like', 'a.display_name LIKE :keyword_name_like', 'n.nickname LIKE :keyword_nickname_like'];
            $params['keyword_email_like'] = $like;
            $params['keyword_name_like'] = $like;
            $params['keyword_nickname_like'] = $like;
            if ($accountId > 0) {
                $clauses[] = 'a.id = :account_id';
                $params['account_id'] = $accountId;
            }
            if ($matchesWithdrawnLabel) {
                $clauses[] = "a.status IN ('withdrawn', 'anonymized')";
            }
            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_admin_member_count(PDO $pdo, array $statusFilter, array $searchFilter = []): int
{
    $queryParts = sr_admin_member_query_parts($statusFilter, $searchFilter);
    $whereSql = $queryParts['where'] === [] ? '' : 'WHERE ' . implode(' AND ', $queryParts['where']);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count_value
         FROM sr_member_accounts a
         LEFT JOIN sr_member_nicknames n ON n.account_id = a.id
         ' . $whereSql
    );
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_admin_member_sort_options(): array
{
    return [
        'email' => ['columns' => ['a.email', 'a.id']],
        'name' => ['columns' => ['a.display_name', 'a.id']],
        'nickname' => ['columns' => ['n.nickname', 'a.id']],
        'status' => ['columns' => ['a.status', 'a.id']],
        'email_verified_at' => ['columns' => ['a.email_verified_at', 'a.id']],
        'last_login_at' => ['columns' => ['a.last_login_at', 'a.id']],
        'active_session_count' => ['columns' => ['active_session_count', 'a.id']],
        'created_at' => ['columns' => ['a.created_at', 'a.id']],
    ];
}

function sr_admin_member_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}

function sr_admin_members(PDO $pdo, array $statusFilter, array $searchFilter = [], int $limit = 0, int $offset = 0, array $sort = []): array
{
    $members = [];
    $hasSessionTable = sr_member_sessions_table_exists($pdo);
    $queryParts = sr_admin_member_query_parts($statusFilter, $searchFilter);
    $where = $queryParts['where'];
    $params = $queryParts['params'];
    $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
    $limitSql = $limit > 0 ? ' LIMIT :limit_value OFFSET :offset_value' : '';
    $orderSql = sr_admin_sort_order_sql(sr_admin_member_sort_options(), $sort, sr_admin_member_default_sort());
    if ($hasSessionTable) {
        $sql = 'SELECT a.id, a.email, a.display_name, a.locale, a.status, a.email_verified_at, a.last_login_at, a.created_at, a.updated_at,
                       COALESCE(n.nickname, \'\') AS nickname,
                       COUNT(s.id) AS active_session_count
                FROM sr_member_accounts a
                LEFT JOIN sr_member_nicknames n ON n.account_id = a.id
                LEFT JOIN sr_member_sessions s ON s.account_id = a.id AND s.revoked_at IS NULL AND s.expires_at >= :now
                ' . $whereSql . '
                GROUP BY a.id, a.email, a.display_name, a.locale, a.status, a.email_verified_at, a.last_login_at, a.created_at, a.updated_at, n.nickname
                ' . $orderSql
                . $limitSql;
        $params['now'] = sr_now();
    } else {
        $sql = 'SELECT a.id, a.email, a.display_name, a.locale, a.status, a.email_verified_at, a.last_login_at, a.created_at, a.updated_at,
                       COALESCE(n.nickname, \'\') AS nickname,
                       0 AS active_session_count
                FROM sr_member_accounts a
                LEFT JOIN sr_member_nicknames n ON n.account_id = a.id
                ' . $whereSql . '
                ' . $orderSql
                . $limitSql;
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, is_int($paramValue) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    if ($limit > 0) {
        $stmt->bindValue('limit_value', max(1, min(1000, $limit)), PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();
    foreach ($stmt->fetchAll() as $row) {
        $members[] = sr_admin_member_with_public_name($pdo, $row);
    }

    return $members;
}
