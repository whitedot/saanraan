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

function sr_admin_member_withdrawal_asset_warning(PDO $pdo, int $accountId): array
{
    $lookupFailed = false;
    try {
        $assets = sr_member_withdrawal_asset_balances($pdo, $accountId);
    } catch (Throwable $exception) {
        $assets = [];
        $lookupFailed = true;
        sr_log_exception($exception, 'admin_member_withdrawal_asset_warning');
    }

    $lines = [];
    foreach ($assets as $asset) {
        if (!is_array($asset)) {
            continue;
        }

        $label = trim((string) ($asset['label'] ?? ''));
        $unitLabel = trim((string) ($asset['unit_label'] ?? ''));
        $processLabel = trim((string) ($asset['process_label'] ?? ''));
        $line = trim($label . ' ' . number_format((int) ($asset['balance'] ?? 0)) . ($unitLabel !== '' ? ' ' . $unitLabel : ''));
        if ($processLabel !== '') {
            $line = trim($line . ' ' . $processLabel);
        }
        if ($line !== '') {
            $lines[] = $line;
        }
    }

    return [
        'assets' => $assets,
        'lookup_failed' => $lookupFailed,
        'lines' => $lines,
        'summary' => $lookupFailed ? '자산 조회 실패' : ($lines !== [] ? implode(', ', $lines) : '없음'),
    ];
}

function sr_admin_member_terminal_status_confirm_message(string $nextStatus, array $assetWarning): string
{
    $lines = isset($assetWarning['lines']) && is_array($assetWarning['lines']) ? $assetWarning['lines'] : [];
    $summary = trim((string) ($assetWarning['summary'] ?? ''));
    if ($summary === '') {
        $summary = $lines !== [] ? implode(', ', $lines) : '없음';
    }
    $message = $nextStatus === 'anonymized'
        ? '이 회원을 익명화할까요?' . "\n" . '계정 식별 정보가 되돌릴 수 없는 익명값으로 바뀌고 세션, 2차 인증, 소셜 로그인 연결이 해제됩니다.'
        : '이 회원을 탈퇴 처리할까요?' . "\n" . '세션, 2차 인증, 소셜 로그인 연결이 해제되고 privacy cleanup이 실행됩니다.';

    $message .= "\n\n" . '현재 조회된 보유 자산: ' . $summary;
    $message .= "\n" . '관리자 탈퇴/익명화는 현재 보유 자산을 자동 정산하지 않습니다.';
    $message .= "\n" . '처리 후에도 계정 ID 또는 공개 해시로 자산 관리자 화면에서 조회해 후속 처리하세요.';

    return $message;
}

function sr_admin_member_asset_followup_link(PDO $pdo, int $actorAccountId, string $path, string $label, array $params = []): array
{
    if ($path === '' || $label === '') {
        return [];
    }
    if (function_exists('sr_admin_get_route_exists') && !sr_admin_get_route_exists($path)) {
        return [];
    }
    if (function_exists('sr_admin_has_permission') && !sr_admin_has_permission($pdo, $actorAccountId, $path, 'view')) {
        return [];
    }

    $query = $params !== [] ? http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '';

    return [
        'label' => $label,
        'url' => $path . ($query !== '' ? '?' . $query : ''),
    ];
}

function sr_admin_member_terminal_asset_followup(PDO $pdo, array $actorAccount, int $accountId, string $nextStatus, array $assetWarning): array
{
    if ($accountId < 1 || !in_array($nextStatus, sr_admin_member_terminal_statuses(), true)) {
        return [];
    }

    $lines = isset($assetWarning['lines']) && is_array($assetWarning['lines']) ? $assetWarning['lines'] : [];
    if ($lines === [] && empty($assetWarning['lookup_failed'])) {
        return [];
    }

    $runtimeConfig = sr_runtime_config();
    $publicHash = sr_admin_member_public_hash($runtimeConfig, $accountId);
    $actorAccountId = (int) ($actorAccount['id'] ?? 0);
    $assets = isset($assetWarning['assets']) && is_array($assetWarning['assets']) ? $assetWarning['assets'] : [];
    $links = [];
    $assetLinkMap = [
        'point' => [
            ['/admin/points/balances', '포인트 잔액', ['account_identifier' => $publicHash]],
            ['/admin/points/transactions', '포인트 거래', ['account_identifier' => $publicHash]],
        ],
        'reward' => [
            ['/admin/rewards/balances', '적립금 잔액', ['account_identifier' => $publicHash]],
            ['/admin/rewards/transactions', '적립금 거래', ['account_identifier' => $publicHash]],
        ],
        'deposit' => [
            ['/admin/deposits/balances', '예치금 잔액', ['account_identifier' => $publicHash]],
            ['/admin/deposits/transactions', '예치금 거래', ['account_identifier' => $publicHash]],
        ],
        'coupon' => [
            ['/admin/coupons/issues', '쿠폰 지급', ['field' => 'hash', 'q' => $publicHash]],
            ['/admin/coupons/redemptions', '쿠폰 사용', ['field' => 'hash', 'q' => $publicHash]],
        ],
    ];

    foreach (array_keys($assets) as $assetKey) {
        foreach ($assetLinkMap[(string) $assetKey] ?? [] as $linkSpec) {
            $link = sr_admin_member_asset_followup_link($pdo, $actorAccountId, (string) $linkSpec[0], (string) $linkSpec[1], (array) $linkSpec[2]);
            if ($link !== []) {
                $links[] = $link;
            }
        }
    }

    foreach ([
        ['/admin/assets/recovery-failures', '미회수 관리', ['q' => $publicHash]],
        ['/admin/content/payments', '콘텐츠 결제', ['account_id' => $publicHash]],
        ['/admin/community/payments', '커뮤니티 결제', ['account_id' => $publicHash]],
    ] as $linkSpec) {
        $link = sr_admin_member_asset_followup_link($pdo, $actorAccountId, (string) $linkSpec[0], (string) $linkSpec[1], (array) $linkSpec[2]);
        if ($link !== []) {
            $links[] = $link;
        }
    }

    return [
        'account_id' => $accountId,
        'account_public_hash' => $publicHash,
        'status' => $nextStatus,
        'asset_summary' => (string) ($assetWarning['summary'] ?? ($lines !== [] ? implode(', ', $lines) : '자산 조회 실패')),
        'links' => $links,
    ];
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
    $resultData = [];
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
        if ($intent === 'evaluate_groups' && in_array((string) ($targetAccount['status'] ?? ''), sr_admin_member_terminal_statuses(), true)) {
            $errors[] = '탈퇴/익명화 회원은 그룹 규칙을 재평가하지 않습니다.';
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
            $terminalAssetWarning = in_array($status, sr_admin_member_terminal_statuses(), true) && (string) $targetAccount['status'] !== $status
                ? sr_admin_member_withdrawal_asset_warning($pdo, $targetAccountId)
                : [];
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
            $followup = sr_admin_member_terminal_asset_followup($pdo, $account, $targetAccountId, $status, $terminalAssetWarning);
            if ($followup !== []) {
                $resultData['terminal_asset_followup'] = $followup;
            }
        }
    } elseif ($errors === []) {
        $runtimeConfig = sr_runtime_config();
        $statusEffects = [];
        $terminalAssetWarning = in_array($status, sr_admin_member_terminal_statuses(), true) && (string) $targetAccount['status'] !== $status
            ? sr_admin_member_withdrawal_asset_warning($pdo, $targetAccountId)
            : [];
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
            $followup = sr_admin_member_terminal_asset_followup($pdo, $account, $targetAccountId, $status, $terminalAssetWarning);
            if ($followup !== []) {
                $resultData['terminal_asset_followup'] = $followup;
            }
        }
    }

    return sr_admin_action_result($errors, $notice, $resultData) + $resultExtra;
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
        'active_session_count' => ['columns' => ['active_session_count', 'a.id']],
        'id' => ['columns' => ['a.id']],
    ];
}

function sr_admin_member_default_sort(): array
{
    return sr_admin_sort_default('id', 'desc');
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

function sr_admin_member_export_limit(): int
{
    return 10000;
}

function sr_admin_member_export_limit_options(): array
{
    return [
        100 => '100건',
        500 => '500건',
        1000 => '1,000건',
        5000 => '5,000건',
        10000 => '10,000건',
        65535 => '65,535건 (XLS 최대)',
    ];
}

function sr_admin_member_export_limit_from_request(): int
{
    $requestedLimit = sr_admin_get_positive_int('export_limit', 10);
    $allowedLimits = array_keys(sr_admin_member_export_limit_options());
    if (!in_array($requestedLimit, $allowedLimits, true)) {
        return sr_admin_member_export_limit();
    }

    return $requestedLimit;
}

function sr_admin_member_export_page_from_request(int $totalCount, int $limit): int
{
    $totalCount = max(0, $totalCount);
    $limit = max(1, $limit);
    $totalPages = max(1, (int) ceil($totalCount / $limit));
    $requestedPage = sr_admin_get_positive_int('export_page', 10);
    if ($requestedPage < 1) {
        return 1;
    }

    return min($requestedPage, $totalPages);
}

function sr_admin_member_export_range(int $totalCount, int $limit, int $page): array
{
    $totalCount = max(0, $totalCount);
    $limit = max(1, $limit);
    $totalPages = max(1, (int) ceil($totalCount / $limit));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $limit;
    $count = max(0, min($limit, $totalCount - $offset));

    return [
        'page' => $page,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'count' => $count,
        'start' => $count > 0 ? $offset + 1 : 0,
        'end' => $count > 0 ? $offset + $count : 0,
        'has_previous' => $page > 1,
        'has_next' => $page < $totalPages,
    ];
}

function sr_admin_member_export_column_definitions(): array
{
    return [
        'sequence' => ['label' => '순번'],
        'public_hash' => ['label' => '공개 해시'],
        'email' => ['label' => '이메일'],
        'public_name' => ['label' => '공개 이름'],
        'nickname' => ['label' => '닉네임'],
        'locale' => ['label' => '언어'],
        'status' => ['label' => '상태'],
        'created_at' => ['label' => '가입일'],
        'birth_date' => ['label' => '생년월일'],
        'is_adult' => ['label' => '성인여부'],
        'marketing_consent' => ['label' => '마케팅 동의'],
        'groups' => ['label' => '소속그룹'],
        'oauth_providers' => ['label' => 'OAuth 제공자'],
        'point_balance' => ['label' => '포인트'],
        'reward_balance' => ['label' => '적립금'],
        'deposit_balance' => ['label' => '예치금'],
        'coupon_count' => ['label' => '쿠폰'],
    ];
}

function sr_admin_member_export_default_columns(): array
{
    return [
        'sequence',
        'public_hash',
        'email',
        'public_name',
        'nickname',
        'status',
        'marketing_consent',
    ];
}

function sr_admin_member_export_column_config_from_request(): array
{
    $definitions = sr_admin_member_export_column_definitions();
    $defaultOrder = sr_admin_member_export_default_columns();
    $postedOrder = $_GET['export_column'] ?? [];
    $postedSelected = $_GET['export_column_enabled'] ?? [];
    $hasPostedConfig = array_key_exists('export_columns_configured', $_GET);

    if (!is_array($postedOrder)) {
        $postedOrder = [];
    }
    if (!is_array($postedSelected)) {
        $postedSelected = [];
    }

    $order = [];
    foreach ($postedOrder as $columnKey) {
        $columnKey = (string) $columnKey;
        if (array_key_exists($columnKey, $definitions) && !in_array($columnKey, $order, true)) {
            $order[] = $columnKey;
        }
    }
    if ($order === [] && !$hasPostedConfig) {
        $order = $defaultOrder;
    }

    $selectedMap = [];
    foreach ($postedSelected as $columnKey) {
        $columnKey = (string) $columnKey;
        if (array_key_exists($columnKey, $definitions)) {
            $selectedMap[$columnKey] = true;
        }
    }

    $selected = [];
    if (!$hasPostedConfig) {
        $selected = $defaultOrder;
    } elseif ($postedSelected === []) {
        $selected = $order;
    } else {
        foreach ($order as $columnKey) {
            if (!empty($selectedMap[$columnKey])) {
                $selected[] = $columnKey;
            }
        }
    }

    return [
        'definitions' => $definitions,
        'order' => $order,
        'selected' => $selected,
    ];
}

function sr_admin_member_export_column_labels(array $columnKeys): array
{
    $definitions = sr_admin_member_export_column_definitions();
    $labels = [];
    foreach ($columnKeys as $columnKey) {
        $columnKey = (string) $columnKey;
        if (isset($definitions[$columnKey])) {
            $labels[] = (string) ($definitions[$columnKey]['label'] ?? $columnKey);
        }
    }

    return $labels;
}

function sr_admin_member_export_table_exists(PDO $pdo, string $tableName): bool
{
    static $cache = [];
    if (!preg_match('/\\A[a-zA-Z0-9_]+\\z/', $tableName)) {
        return false;
    }
    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    try {
        $pdo->query('SELECT 1 FROM ' . $tableName . ' LIMIT 1');
        $cache[$tableName] = true;
    } catch (Throwable) {
        $cache[$tableName] = false;
    }

    return $cache[$tableName];
}

function sr_admin_member_export_context(PDO $pdo, array $accountIds, array $columnKeys): array
{
    $accountIds = array_values(array_unique(array_filter(array_map('intval', $accountIds), static function (int $accountId): bool {
        return $accountId > 0;
    })));
    $context = [
        'profiles' => [],
        'groups' => [],
        'oauth_providers' => [],
        'point_balance' => [],
        'reward_balance' => [],
        'deposit_balance' => [],
        'coupon_count' => [],
    ];
    if ($accountIds === []) {
        return $context;
    }

    $needs = array_fill_keys(array_map('strval', $columnKeys), true);
    [$accountCondition, $accountParams] = sr_admin_sql_in_condition('account_id', 'member_export_account', $accountIds);

    if ((isset($needs['birth_date']) || isset($needs['is_adult'])) && sr_admin_member_export_table_exists($pdo, 'sr_member_profiles')) {
        $stmt = $pdo->prepare('SELECT account_id, birth_date, is_adult FROM sr_member_profiles WHERE ' . $accountCondition);
        $stmt->execute($accountParams);
        foreach ($stmt->fetchAll() as $row) {
            $context['profiles'][(int) ($row['account_id'] ?? 0)] = $row;
        }
    }

    if (isset($needs['groups']) && sr_member_groups_table_exists($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT m.account_id, g.title
             FROM sr_member_group_memberships m
             INNER JOIN sr_member_groups g ON g.id = m.group_id
             WHERE ' . $accountCondition . "
               AND m.status = 'active'
             ORDER BY m.account_id ASC, g.sort_order ASC, g.id ASC"
        );
        $stmt->execute($accountParams);
        foreach ($stmt->fetchAll() as $row) {
            $accountId = (int) ($row['account_id'] ?? 0);
            if ($accountId > 0) {
                $context['groups'][$accountId][] = (string) ($row['title'] ?? '');
            }
        }
    }

    if (isset($needs['oauth_providers']) && sr_admin_member_export_table_exists($pdo, 'sr_member_oauth_accounts')) {
        $stmt = $pdo->prepare(
            'SELECT account_id, provider_key
             FROM sr_member_oauth_accounts
             WHERE ' . $accountCondition . '
               AND revoked_at IS NULL
             ORDER BY account_id ASC, provider_key ASC'
        );
        $stmt->execute($accountParams);
        foreach ($stmt->fetchAll() as $row) {
            $accountId = (int) ($row['account_id'] ?? 0);
            if ($accountId > 0) {
                $context['oauth_providers'][$accountId][] = (string) ($row['provider_key'] ?? '');
            }
        }
    }

    foreach ([
        'point_balance' => 'sr_point_balances',
        'reward_balance' => 'sr_reward_balances',
        'deposit_balance' => 'sr_deposit_balances',
    ] as $contextKey => $tableName) {
        if (!isset($needs[$contextKey]) || !sr_admin_member_export_table_exists($pdo, $tableName)) {
            continue;
        }
        $stmt = $pdo->prepare('SELECT account_id, balance FROM ' . $tableName . ' WHERE ' . $accountCondition);
        $stmt->execute($accountParams);
        foreach ($stmt->fetchAll() as $row) {
            $context[$contextKey][(int) ($row['account_id'] ?? 0)] = (int) ($row['balance'] ?? 0);
        }
    }

    if (isset($needs['coupon_count']) && sr_admin_member_export_table_exists($pdo, 'sr_coupon_issues')) {
        $stmt = $pdo->prepare(
            'SELECT account_id, COUNT(*) AS count_value
             FROM sr_coupon_issues
             WHERE ' . $accountCondition . "
               AND status = 'active'
               AND (expires_at IS NULL OR expires_at >= :now)
             GROUP BY account_id"
        );
        $couponParams = $accountParams;
        $couponParams['now'] = sr_now();
        $stmt->execute($couponParams);
        foreach ($stmt->fetchAll() as $row) {
            $context['coupon_count'][(int) ($row['account_id'] ?? 0)] = (int) ($row['count_value'] ?? 0);
        }
    }

    return $context;
}

function sr_admin_member_export_row_values(array $columnKeys, array $member, array $marketingValues, array $context = []): array
{
    $values = [];
    $accountId = (int) ($member['id'] ?? 0);
    foreach ($columnKeys as $columnKey) {
        switch ((string) $columnKey) {
            case 'sequence':
                $values[] = (string) (int) ($context['sequence'] ?? 0);
                break;
            case 'public_hash':
                $values[] = (string) ($member['account_public_hash'] ?? '');
                break;
            case 'email':
                $values[] = sr_admin_member_email_display($member);
                break;
            case 'public_name':
                $values[] = sr_admin_member_display_name_preview($member);
                break;
            case 'nickname':
                $values[] = trim((string) ($member['nickname'] ?? '')) !== '' ? (string) $member['nickname'] : '';
                break;
            case 'locale':
                $values[] = (string) ($member['locale'] ?? '');
                break;
            case 'status':
                $values[] = sr_admin_code_label((string) ($member['status'] ?? ''), 'member_status');
                break;
            case 'created_at':
                $values[] = (string) ($member['created_at'] ?? '');
                break;
            case 'birth_date':
                $profile = $context['profiles'][$accountId] ?? [];
                $values[] = is_array($profile) ? (string) ($profile['birth_date'] ?? '') : '';
                break;
            case 'is_adult':
                $profile = $context['profiles'][$accountId] ?? [];
                if (!is_array($profile) || !array_key_exists('is_adult', $profile) || $profile['is_adult'] === null) {
                    $values[] = '';
                } else {
                    $values[] = (int) $profile['is_adult'] === 1 ? '예' : '아니오';
                }
                break;
            case 'marketing_consent':
                $values[] = (string) ($marketingValues['status'] ?? '');
                break;
            case 'groups':
                $groups = $context['groups'][$accountId] ?? [];
                $groups = is_array($groups) ? array_values(array_filter(array_map('strval', $groups), static function (string $value): bool {
                    return $value !== '';
                })) : [];
                $values[] = implode(', ', $groups);
                break;
            case 'oauth_providers':
                $providers = $context['oauth_providers'][$accountId] ?? [];
                $providers = is_array($providers) ? array_values(array_filter(array_map('strval', $providers), static function (string $value): bool {
                    return $value !== '';
                })) : [];
                $values[] = implode(', ', array_unique($providers));
                break;
            case 'point_balance':
                $values[] = (string) (int) ($context['point_balance'][$accountId] ?? 0);
                break;
            case 'reward_balance':
                $values[] = (string) (int) ($context['reward_balance'][$accountId] ?? 0);
                break;
            case 'deposit_balance':
                $values[] = (string) (int) ($context['deposit_balance'][$accountId] ?? 0);
                break;
            case 'coupon_count':
                $values[] = (string) (int) ($context['coupon_count'][$accountId] ?? 0);
                break;
        }
    }

    return $values;
}

function sr_admin_member_csv_cell(mixed $value): string
{
    $value = (string) $value;
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
        return "'" . $value;
    }

    return $value;
}

function sr_admin_member_csv_row($output, array $row): void
{
    fputcsv($output, array_map('sr_admin_member_csv_cell', $row));
}

function sr_admin_member_marketing_consent_export_values(?array $consent): array
{
    if ($consent === null) {
        return [
            'status' => '기록 없음',
            'created_at' => '',
            'document_title' => '',
            'version' => '',
        ];
    }

    return [
        'status' => !empty($consent['consented']) ? '동의' : '미동의',
        'created_at' => (string) ($consent['created_at'] ?? ''),
        'document_title' => (string) ($consent['consent_title_snapshot'] ?? ''),
        'version' => (string) ($consent['consent_version'] ?? ''),
    ];
}

function sr_admin_member_marketing_opt_out_upload_limit(): int
{
    return 5000;
}

function sr_admin_member_marketing_opt_out_sample_rows(): array
{
    return [
        ['email'],
        ['sample@example.com'],
    ];
}

function sr_admin_member_marketing_opt_out_parse_upload(array $file, int $limit): array
{
    $errors = [];
    $rows = [];
    $truncated = false;
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        return [
            'errors' => ['수신거부 명단 파일을 선택하세요.'],
            'rows' => [],
            'truncated' => false,
        ];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return [
            'errors' => ['업로드된 파일을 확인할 수 없습니다.'],
            'rows' => [],
            'truncated' => false,
        ];
    }

    $name = (string) ($file['name'] ?? '');
    $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    if ($extension === 'csv') {
        $parsed = sr_admin_member_marketing_opt_out_parse_csv($tmpName, $limit);
    } elseif ($extension === 'xlsx') {
        $parsed = sr_admin_member_marketing_opt_out_parse_xlsx($tmpName, $limit);
    } else {
        $parsed = [
            'errors' => ['CSV 또는 XLSX 파일만 업로드할 수 있습니다.'],
            'rows' => [],
            'truncated' => false,
        ];
    }

    $errors = array_merge($errors, (array) ($parsed['errors'] ?? []));
    $rows = isset($parsed['rows']) && is_array($parsed['rows']) ? $parsed['rows'] : [];
    $truncated = !empty($parsed['truncated']);

    return [
        'errors' => $errors,
        'rows' => $rows,
        'truncated' => $truncated,
    ];
}

function sr_admin_member_marketing_opt_out_parse_csv(string $path, int $limit): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return [
            'errors' => ['CSV 파일을 읽을 수 없습니다.'],
            'rows' => [],
            'truncated' => false,
        ];
    }

    $rows = [];
    $truncated = false;
    while (($row = fgetcsv($handle)) !== false) {
        if (count($rows) >= $limit + 1) {
            $truncated = true;
            break;
        }
        $rows[] = array_map(static function ($value): string {
            return trim((string) $value);
        }, $row);
    }
    fclose($handle);

    return [
        'errors' => [],
        'rows' => $rows,
        'truncated' => $truncated,
    ];
}

function sr_admin_member_marketing_opt_out_parse_xlsx(string $path, int $limit): array
{
    if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) {
        return [
            'errors' => ['현재 PHP 환경에서 XLSX 파일을 처리할 수 없습니다. CSV로 업로드하세요.'],
            'rows' => [],
            'truncated' => false,
        ];
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return [
            'errors' => ['XLSX 파일을 열 수 없습니다.'],
            'rows' => [],
            'truncated' => false,
        ];
    }

    $sharedStrings = sr_admin_member_marketing_opt_out_xlsx_shared_strings($zip);
    $sheetPath = sr_admin_member_marketing_opt_out_xlsx_first_sheet_path($zip);
    $sheetXml = $sheetPath !== '' ? $zip->getFromName($sheetPath) : false;
    if ($sheetXml === false) {
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    }
    if ($sheetXml === false) {
        $zip->close();
        return [
            'errors' => ['XLSX 첫 번째 시트를 읽을 수 없습니다.'],
            'rows' => [],
            'truncated' => false,
        ];
    }

    $xml = simplexml_load_string($sheetXml);
    if (!$xml instanceof SimpleXMLElement) {
        $zip->close();
        return [
            'errors' => ['XLSX 시트 형식이 올바르지 않습니다.'],
            'rows' => [],
            'truncated' => false,
        ];
    }

    $rows = [];
    $truncated = false;
    foreach ($xml->sheetData->row as $rowNode) {
        if (count($rows) >= $limit + 1) {
            $truncated = true;
            break;
        }
        $row = [];
        foreach ($rowNode->c as $cellNode) {
            $cellRef = (string) ($cellNode['r'] ?? '');
            $cellIndex = sr_admin_member_marketing_opt_out_xlsx_cell_index($cellRef);
            if ($cellIndex < 0) {
                $cellIndex = count($row);
            }
            while (count($row) < $cellIndex) {
                $row[] = '';
            }
            $row[$cellIndex] = sr_admin_member_marketing_opt_out_xlsx_cell_value($cellNode, $sharedStrings);
        }
        $rows[] = $row;
    }
    $zip->close();

    return [
        'errors' => [],
        'rows' => $rows,
        'truncated' => $truncated,
    ];
}

function sr_admin_member_marketing_opt_out_xlsx_shared_strings(ZipArchive $zip): array
{
    $xmlString = $zip->getFromName('xl/sharedStrings.xml');
    if ($xmlString === false) {
        return [];
    }

    $xml = simplexml_load_string($xmlString);
    if (!$xml instanceof SimpleXMLElement) {
        return [];
    }

    $strings = [];
    foreach ($xml->si as $item) {
        $parts = [];
        if (isset($item->t)) {
            $parts[] = (string) $item->t;
        }
        foreach ($item->r as $run) {
            if (isset($run->t)) {
                $parts[] = (string) $run->t;
            }
        }
        $strings[] = implode('', $parts);
    }

    return $strings;
}

function sr_admin_member_marketing_opt_out_xlsx_first_sheet_path(ZipArchive $zip): string
{
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($workbookXml === false || $relsXml === false) {
        return '';
    }

    $workbook = simplexml_load_string($workbookXml);
    $rels = simplexml_load_string($relsXml);
    if (!$workbook instanceof SimpleXMLElement || !$rels instanceof SimpleXMLElement) {
        return '';
    }

    $namespaces = $workbook->getNamespaces(true);
    $relationshipNamespace = (string) ($namespaces['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $firstRelationshipId = '';
    foreach ($workbook->sheets->sheet as $sheet) {
        $attributes = $sheet->attributes($relationshipNamespace);
        $firstRelationshipId = (string) ($attributes['id'] ?? '');
        if ($firstRelationshipId !== '') {
            break;
        }
    }
    if ($firstRelationshipId === '') {
        return '';
    }

    foreach ($rels->Relationship as $relationship) {
        if ((string) ($relationship['Id'] ?? '') !== $firstRelationshipId) {
            continue;
        }
        $target = ltrim((string) ($relationship['Target'] ?? ''), '/');
        if ($target === '') {
            return '';
        }
        return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
    }

    return '';
}

function sr_admin_member_marketing_opt_out_xlsx_cell_index(string $cellRef): int
{
    if (preg_match('/\A([A-Z]+)[0-9]+\z/i', $cellRef, $matches) !== 1) {
        return -1;
    }

    $letters = strtoupper($matches[1]);
    $index = 0;
    for ($i = 0, $length = strlen($letters); $i < $length; $i++) {
        $index = ($index * 26) + (ord($letters[$i]) - 64);
    }

    return $index - 1;
}

function sr_admin_member_marketing_opt_out_xlsx_cell_value(SimpleXMLElement $cell, array $sharedStrings): string
{
    $type = (string) ($cell['t'] ?? '');
    if ($type === 'inlineStr') {
        return trim((string) ($cell->is->t ?? ''));
    }

    $value = trim((string) ($cell->v ?? ''));
    if ($type === 's') {
        $index = preg_match('/\A[0-9]+\z/', $value) === 1 ? (int) $value : -1;
        return trim((string) ($sharedStrings[$index] ?? ''));
    }

    return $value;
}

function sr_admin_member_marketing_opt_out_identifier_rows(array $rows): array
{
    $header = [];
    $headerRowIndex = -1;
    foreach ($rows as $index => $row) {
        $row = array_map('strval', is_array($row) ? $row : []);
        $nonEmpty = array_filter($row, static function (string $value): bool {
            return trim($value) !== '';
        });
        if ($nonEmpty !== []) {
            $header = $row;
            $headerRowIndex = (int) $index;
            break;
        }
    }

    if ($headerRowIndex < 0) {
        return [
            'errors' => ['업로드 파일에 처리할 행이 없습니다.'],
            'items' => [],
        ];
    }

    $headerMap = sr_admin_member_marketing_opt_out_header_map($header);
    if ($headerMap === []) {
        return [
            'errors' => ['첫 행에 email, public_hash, login_id, account_id 중 하나의 컬럼명을 넣어 주세요.'],
            'items' => [],
        ];
    }

    $items = [];
    $rowNumber = 0;
    foreach ($rows as $index => $row) {
        if ($index <= $headerRowIndex) {
            continue;
        }
        $rowNumber = $index + 1;
        $row = array_map('strval', is_array($row) ? $row : []);
        $item = sr_admin_member_marketing_opt_out_identifier_from_row($row, $headerMap);
        if ($item === null) {
            continue;
        }
        $item['row'] = $rowNumber;
        $items[] = $item;
    }

    if ($items === []) {
        return [
            'errors' => ['수신거부 처리할 회원 식별자가 없습니다.'],
            'items' => [],
        ];
    }

    return [
        'errors' => [],
        'items' => $items,
    ];
}

function sr_admin_member_marketing_opt_out_header_map(array $header): array
{
    $aliases = [
        'email' => ['email', 'emailaddress', '이메일', '메일', '전자우편'],
        'hash' => ['publichash', 'hash', 'accounthash', 'memberhash', '공개해시', '회원해시', '회원공개해시'],
        'login_id' => ['loginid', 'login_id', '아이디', '로그인id', '로그인아이디', '로그인ID'],
        'account_id' => ['accountid', 'account_id', 'id', 'memberid'],
    ];

    $map = [];
    foreach ($header as $index => $label) {
        $normalized = sr_admin_member_marketing_opt_out_header_key((string) $label);
        if ($normalized === '') {
            continue;
        }
        foreach ($aliases as $field => $fieldAliases) {
            if (in_array($normalized, array_map('sr_admin_member_marketing_opt_out_header_key', $fieldAliases), true)) {
                $map[$field] = (int) $index;
            }
        }
    }

    return $map;
}

function sr_admin_member_marketing_opt_out_header_key(string $value): string
{
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    $value = strtolower(trim($value));
    $value = preg_replace('/[\s\-\(\)\[\]\/\.]+/u', '', $value) ?? $value;

    return $value;
}

function sr_admin_member_marketing_opt_out_identifier_from_row(array $row, array $headerMap): ?array
{
    foreach (['email', 'hash', 'login_id', 'account_id'] as $field) {
        if (!isset($headerMap[$field])) {
            continue;
        }
        $value = trim((string) ($row[(int) $headerMap[$field]] ?? ''));
        if ($value === '') {
            continue;
        }

        return [
            'field' => $field,
            'value' => $value,
        ];
    }

    return null;
}

function sr_admin_member_marketing_opt_out_consent_snapshot(PDO $pdo): array
{
    $settings = sr_member_settings($pdo);
    $specs = sr_member_registration_policy_document_specs($settings);
    $documentKey = (string) ($specs['marketing']['document_key'] ?? '');
    $snapshot = $documentKey !== '' ? sr_member_registration_policy_document_snapshot($pdo, $documentKey) : null;
    if (is_array($snapshot)) {
        $snapshot['required'] = false;
        return [
            'version' => (string) (int) ($snapshot['version_id'] ?? 0),
            'snapshot' => $snapshot,
        ];
    }

    return [
        'version' => 'admin-opt-out',
        'snapshot' => [
            'document_key' => $documentKey,
            'title' => '관리자 수신거부 업로드',
            'body_hash' => '',
            'required' => false,
        ],
    ];
}

function sr_admin_handle_member_marketing_opt_out_upload_post(PDO $pdo, array $account, array $config): array
{
    $limit = sr_admin_member_marketing_opt_out_upload_limit();
    $file = isset($_FILES['marketing_opt_out_file']) && is_array($_FILES['marketing_opt_out_file']) ? $_FILES['marketing_opt_out_file'] : [];
    $parsed = sr_admin_member_marketing_opt_out_parse_upload($file, $limit);
    $errors = isset($parsed['errors']) && is_array($parsed['errors']) ? $parsed['errors'] : [];
    if ($errors !== []) {
        return sr_admin_action_result($errors, '');
    }

    $identifierResult = sr_admin_member_marketing_opt_out_identifier_rows((array) ($parsed['rows'] ?? []));
    $errors = isset($identifierResult['errors']) && is_array($identifierResult['errors']) ? $identifierResult['errors'] : [];
    if ($errors !== []) {
        return sr_admin_action_result($errors, '');
    }

    $items = isset($identifierResult['items']) && is_array($identifierResult['items']) ? $identifierResult['items'] : [];
    $summary = [
        'uploaded_rows' => count($items),
        'matched_count' => 0,
        'updated_count' => 0,
        'already_opted_out_count' => 0,
        'duplicate_count' => 0,
        'not_found_count' => 0,
        'invalid_count' => 0,
        'truncated' => !empty($parsed['truncated']),
        'limit' => $limit,
    ];

    $accountIds = [];
    $seenAccountIds = [];
    foreach ($items as $item) {
        $field = (string) ($item['field'] ?? '');
        $value = trim((string) ($item['value'] ?? ''));
        if ($value === '') {
            $summary['invalid_count']++;
            continue;
        }

        if ($field === 'account_id' && preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            $summary['invalid_count']++;
            continue;
        }

        $lookupField = $field === 'account_id' ? 'hash' : $field;
        $accountId = sr_admin_member_account_id_from_lookup($pdo, $config, $lookupField, $value);
        if ($accountId <= 0) {
            $summary['not_found_count']++;
            continue;
        }

        $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $accountId]);
        if (!is_array($stmt->fetch())) {
            $summary['not_found_count']++;
            continue;
        }

        if (isset($seenAccountIds[$accountId])) {
            $summary['duplicate_count']++;
            continue;
        }
        $seenAccountIds[$accountId] = true;
        $accountIds[] = $accountId;
    }

    if ($accountIds === []) {
        return sr_admin_action_result(['매칭된 회원이 없습니다.'], '', ['marketing_opt_out' => $summary]);
    }

    $summary['matched_count'] = count($accountIds);
    $latestConsents = sr_member_latest_consents_by_account_ids($pdo, $accountIds, 'marketing');
    $consentData = sr_admin_member_marketing_opt_out_consent_snapshot($pdo);
    try {
        $pdo->beginTransaction();
        foreach ($accountIds as $accountId) {
            $latestConsent = $latestConsents[$accountId] ?? null;
            if (is_array($latestConsent) && empty($latestConsent['consented'])) {
                $summary['already_opted_out_count']++;
                continue;
            }

            sr_member_record_consent(
                $pdo,
                $accountId,
                'marketing',
                (string) ($consentData['version'] ?? 'admin-opt-out'),
                false,
                is_array($consentData['snapshot'] ?? null) ? $consentData['snapshot'] : []
            );
            $summary['updated_count']++;
        }
        $pdo->commit();
    } catch (Throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sr_audit_log($pdo, [
            'actor_account_id' => (int) ($account['id'] ?? 0),
            'actor_type' => 'admin',
            'event_type' => 'member.marketing_opt_out.imported',
            'target_type' => 'member_account',
            'target_id' => 'batch',
            'result' => 'failure',
            'message' => 'Member marketing opt-out import failed.',
            'metadata' => $summary,
        ]);

        return sr_admin_action_result(['수신거부 명단을 처리하지 못했습니다.'], '', ['marketing_opt_out' => $summary]);
    }

    sr_audit_log($pdo, [
        'actor_account_id' => (int) ($account['id'] ?? 0),
        'actor_type' => 'admin',
        'event_type' => 'member.marketing_opt_out.imported',
        'target_type' => 'member_account',
        'target_id' => 'batch',
        'result' => 'success',
        'message' => 'Member marketing opt-out import completed.',
        'metadata' => $summary,
    ]);

    $notice = '수신거부 명단을 처리했습니다. 거부 처리 ' . (string) $summary['updated_count'] . '명';
    if ($summary['already_opted_out_count'] > 0) {
        $notice .= ', 이미 거부 ' . (string) $summary['already_opted_out_count'] . '명';
    }
    if ($summary['not_found_count'] > 0 || $summary['invalid_count'] > 0 || $summary['duplicate_count'] > 0) {
        $notice .= ', 제외 ' . (string) ($summary['not_found_count'] + $summary['invalid_count'] + $summary['duplicate_count']) . '건';
    }
    if (!empty($summary['truncated'])) {
        $notice .= ', 최대 ' . (string) $limit . '건까지만 반영';
    }

    return sr_admin_action_result([], $notice, ['marketing_opt_out' => $summary]);
}
