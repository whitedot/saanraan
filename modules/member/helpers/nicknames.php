<?php

declare(strict_types=1);

function sr_member_nicknames_table_exists(PDO $pdo): bool
{
    static $existsByConnection = [];

    $key = (string) spl_object_id($pdo);
    if (array_key_exists($key, $existsByConnection)) {
        return $existsByConnection[$key];
    }

    try {
        $pdo->query('SELECT 1 FROM sr_member_nicknames LIMIT 1');
        $existsByConnection[$key] = true;
    } catch (Throwable $exception) {
        $existsByConnection[$key] = false;
    }

    return $existsByConnection[$key];
}

function sr_member_normalize_display_name(string $displayName): string
{
    return trim($displayName);
}

function sr_member_normalize_nickname(string $nickname): string
{
    return trim($nickname);
}

function sr_member_nickname_lookup_key(string $nickname): string
{
    $nickname = sr_member_normalize_nickname($nickname);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($nickname, 'UTF-8');
    }

    return strtolower($nickname);
}

function sr_member_identity_value_has_space(string $value): bool
{
    return preg_match('/\s/u', $value) === 1;
}

function sr_member_display_name_validation_errors(string $displayName): array
{
    $displayName = sr_member_normalize_display_name($displayName);
    if ($displayName === '') {
        return [sr_t('member::action.register.display_name_required')];
    }
    if (sr_member_identity_value_has_space($displayName)) {
        return [sr_t('member::action.display_name_space_disallowed')];
    }

    return [];
}

function sr_member_nickname_validation_errors(PDO $pdo, string $nickname, array $settings, int $excludeAccountId = 0): array
{
    $nickname = sr_member_normalize_nickname($nickname);
    if (empty($settings['nickname_enabled'])) {
        return [];
    }
    if ($nickname === '') {
        return !empty($settings['nickname_required']) ? [sr_t('member::action.nickname_required')] : [];
    }
    if (sr_member_identity_value_has_space($nickname)) {
        return [sr_t('member::action.nickname_space_disallowed')];
    }
    if (sr_member_nickname_exists($pdo, $nickname, $excludeAccountId)) {
        return [sr_t('member::action.nickname_duplicate')];
    }

    return [];
}

function sr_member_nickname(PDO $pdo, int $accountId): string
{
    if ($accountId < 1 || !sr_member_nicknames_table_exists($pdo)) {
        return '';
    }

    $stmt = $pdo->prepare('SELECT nickname FROM sr_member_nicknames WHERE account_id = :account_id LIMIT 1');
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();

    return is_array($row) ? (string) ($row['nickname'] ?? '') : '';
}

function sr_member_nickname_exists(PDO $pdo, string $nickname, int $excludeAccountId = 0): bool
{
    if (!sr_member_nicknames_table_exists($pdo)) {
        return false;
    }

    $lookupKey = sr_member_nickname_lookup_key($nickname);
    if ($lookupKey === '') {
        return false;
    }

    $params = ['lookup_key' => $lookupKey];
    $where = 'nickname_lookup = :lookup_key';
    if ($excludeAccountId > 0) {
        $where .= ' AND account_id <> :account_id';
        $params['account_id'] = $excludeAccountId;
    }

    $stmt = $pdo->prepare('SELECT account_id FROM sr_member_nicknames WHERE ' . $where . ' LIMIT 1');
    $stmt->execute($params);

    return is_array($stmt->fetch());
}

function sr_member_set_nickname(PDO $pdo, int $accountId, string $nickname): void
{
    if ($accountId < 1 || !sr_member_nicknames_table_exists($pdo)) {
        return;
    }

    $nickname = sr_member_normalize_nickname($nickname);
    if ($nickname === '') {
        sr_member_delete_nickname($pdo, $accountId);
        return;
    }

    $lookupKey = sr_member_nickname_lookup_key($nickname);
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_member_nicknames (account_id, nickname, nickname_lookup, created_at, updated_at)
         VALUES (:account_id, :nickname, :nickname_lookup, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            nickname = VALUES(nickname),
            nickname_lookup = VALUES(nickname_lookup),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'nickname' => $nickname,
        'nickname_lookup' => $lookupKey,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function sr_member_delete_nickname(PDO $pdo, int $accountId): void
{
    if ($accountId < 1 || !sr_member_nicknames_table_exists($pdo)) {
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM sr_member_nicknames WHERE account_id = :account_id');
    $stmt->execute(['account_id' => $accountId]);
}

function sr_member_public_name(array $account, array $settings = [], string $fallback = ''): string
{
    $status = (string) ($account['status'] ?? '');
    if (in_array($status, ['withdrawn', 'anonymized'], true)) {
        return sr_t('member::account.withdrawn_display_name');
    }

    $displayName = trim((string) ($account['display_name'] ?? ''));
    $nickname = trim((string) ($account['nickname'] ?? $account['member_nickname'] ?? ''));
    if (!empty($settings['nickname_enabled']) && $nickname !== '') {
        return $nickname;
    }
    if ($displayName !== '') {
        return $displayName;
    }

    return $fallback !== '' ? $fallback : sr_t('member::account.withdrawn_display_name');
}

function sr_member_public_name_for_account_id(PDO $pdo, int $accountId, string $fallback = ''): string
{
    $summary = sr_member_public_account_summary($pdo, $accountId);
    if (!is_array($summary)) {
        return $fallback !== '' ? $fallback : sr_t('member::account.withdrawn_display_name');
    }

    return (string) ($summary['public_name'] ?? sr_member_public_name($summary, sr_member_settings($pdo), $fallback));
}

function sr_member_public_account_summaries(PDO $pdo, array $accountIds): array
{
    $ids = [];
    foreach ($accountIds as $accountId) {
        $accountId = (int) $accountId;
        if ($accountId > 0) {
            $ids[$accountId] = true;
        }
    }
    if ($ids === []) {
        return [];
    }

    $settings = sr_member_settings($pdo);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $join = sr_member_nicknames_table_exists($pdo)
        ? 'LEFT JOIN sr_member_nicknames n ON n.account_id = a.id'
        : '';
    $nicknameSelect = sr_member_nicknames_table_exists($pdo) ? 'n.nickname' : "'' AS nickname";
    $stmt = $pdo->prepare(
        'SELECT a.id, a.display_name, a.locale, a.status, ' . $nicknameSelect . '
         FROM sr_member_accounts a
         ' . $join . '
         WHERE a.id IN (' . $placeholders . ')'
    );
    $stmt->execute(array_keys($ids));

    $summaries = [];
    foreach ($stmt->fetchAll() as $account) {
        $account['public_name'] = sr_member_public_name($account, $settings);
        $summaries[(int) $account['id']] = [
            'id' => (int) $account['id'],
            'display_name' => (string) $account['display_name'],
            'nickname' => (string) ($account['nickname'] ?? ''),
            'public_name' => (string) $account['public_name'],
            'locale' => (string) $account['locale'],
            'status' => (string) $account['status'],
        ];
    }

    return $summaries;
}

function sr_member_public_name_lookup_account_ids(PDO $pdo, array $tokens, array $excludeAccountIds = []): array
{
    $settings = sr_member_settings($pdo);
    $tokenMap = [];
    foreach ($tokens as $token) {
        $token = trim((string) $token);
        if ($token !== '') {
            $tokenMap[$token] = true;
        }
    }
    if ($tokenMap === []) {
        return [];
    }

    $exclude = [];
    foreach ($excludeAccountIds as $accountId) {
        $accountId = (int) $accountId;
        if ($accountId > 0) {
            $exclude[$accountId] = true;
        }
    }

    $accountIds = [];
    $tokens = array_keys($tokenMap);
    if (!empty($settings['nickname_enabled']) && sr_member_nicknames_table_exists($pdo)) {
        $lookupTokens = array_map('sr_member_nickname_lookup_key', $tokens);
        $placeholders = implode(',', array_fill(0, count($lookupTokens), '?'));
        $stmt = $pdo->prepare(
            'SELECT n.account_id
             FROM sr_member_nicknames n
             INNER JOIN sr_member_accounts a ON a.id = n.account_id
             WHERE n.nickname_lookup IN (' . $placeholders . ")
               AND a.status = 'active'"
        );
        $stmt->execute($lookupTokens);
    } else {
        $placeholders = implode(',', array_fill(0, count($tokens), '?'));
        $stmt = $pdo->prepare(
            'SELECT id AS account_id
             FROM sr_member_accounts
             WHERE display_name IN (' . $placeholders . ")
               AND status = 'active'"
        );
        $stmt->execute($tokens);
    }

    foreach ($stmt->fetchAll() as $row) {
        $accountId = (int) ($row['account_id'] ?? 0);
        if ($accountId > 0 && !isset($exclude[$accountId])) {
            $accountIds[$accountId] = true;
        }
    }

    return array_keys($accountIds);
}

function sr_member_mention_token_rows(string $bodyText): array
{
    if (!preg_match_all('/@([^\s@:,.;!?()\[\]{}<>]{2,160})/u', $bodyText, $matches)) {
        return [];
    }

    $rows = [];
    foreach ($matches[1] as $rawToken) {
        $token = trim((string) $rawToken);
        if ($token === '') {
            continue;
        }

        $name = $token;
        $hashPrefix = '';
        $hashPosition = strrpos($token, '#');
        if ($hashPosition !== false) {
            $suffix = strtolower(substr($token, $hashPosition + 1));
            if (preg_match('/\A[a-f0-9]{6,32}\z/', $suffix) === 1) {
                $name = substr($token, 0, $hashPosition);
                $hashPrefix = $suffix;
            }
        }

        $name = trim($name);
        if ($name === '') {
            continue;
        }

        $key = $name . '#' . $hashPrefix;
        $rows[$key] = [
            'token' => $token,
            'public_name' => $name,
            'hash_prefix' => $hashPrefix,
            'has_hash_prefix' => $hashPrefix !== '',
        ];
    }

    return array_values($rows);
}

function sr_member_mention_plain_text_html(string $bodyText): string
{
    if ($bodyText === '') {
        return '';
    }

    if (!preg_match_all('/@([^\s@:,.;!?()\[\]{}<>]{2,160})/u', $bodyText, $matches, PREG_OFFSET_CAPTURE)) {
        return nl2br(sr_e($bodyText), false);
    }

    $html = '';
    $offset = 0;
    foreach ($matches[0] as $index => $match) {
        $fullToken = (string) $match[0];
        $matchOffset = (int) $match[1];
        $rawToken = isset($matches[1][$index][0]) ? (string) $matches[1][$index][0] : '';
        $token = trim($rawToken);
        $name = $token;
        $hashPrefix = '';
        $hashPosition = strrpos($token, '#');
        if ($hashPosition !== false) {
            $suffix = strtolower(substr($token, $hashPosition + 1));
            if (preg_match('/\A[a-f0-9]{6,32}\z/', $suffix) === 1) {
                $name = trim(substr($token, 0, $hashPosition));
                $hashPrefix = $suffix;
            }
        }

        $html .= sr_e(substr($bodyText, $offset, $matchOffset - $offset));
        if ($name !== '' && $hashPrefix !== '') {
            $html .= '<span class="sr-mention"><span class="sr-mention-name">@' . sr_e($name) . '</span><span class="sr-mention-prefix">#' . sr_e($hashPrefix) . '</span></span>';
        } else {
            $html .= sr_e($fullToken);
        }
        $offset = $matchOffset + strlen($fullToken);
    }

    $html .= sr_e(substr($bodyText, $offset));

    return nl2br($html, false);
}

function sr_member_mention_candidate_rows_by_public_name(PDO $pdo, string $publicName): array
{
    $publicName = trim($publicName);
    if ($publicName === '') {
        return [];
    }

    $settings = sr_member_settings($pdo);
    if (!empty($settings['nickname_enabled']) && sr_member_nicknames_table_exists($pdo)) {
        $stmt = $pdo->prepare(
            "SELECT a.id, a.display_name, a.locale, a.status, n.nickname
             FROM sr_member_nicknames n
             INNER JOIN sr_member_accounts a ON a.id = n.account_id
             WHERE n.nickname_lookup = :lookup
               AND a.status = 'active'
             ORDER BY a.id ASC
             LIMIT 50"
        );
        $stmt->execute(['lookup' => sr_member_nickname_lookup_key($publicName)]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, display_name, locale, status, '' AS nickname
             FROM sr_member_accounts
             WHERE display_name = :display_name
               AND status = 'active'
             ORDER BY id ASC
             LIMIT 50"
        );
        $stmt->execute(['display_name' => $publicName]);
    }

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['public_name'] = sr_member_public_name($row, $settings);
        if ((string) $row['public_name'] === $publicName) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function sr_member_mention_account_ids(PDO $pdo, array $config, string $bodyText, array $excludeAccountIds = []): array
{
    $exclude = [];
    foreach ($excludeAccountIds as $accountId) {
        $accountId = (int) $accountId;
        if ($accountId > 0) {
            $exclude[$accountId] = true;
        }
    }

    $accountIds = [];
    foreach (sr_member_mention_token_rows($bodyText) as $token) {
        $candidates = sr_member_mention_candidate_rows_by_public_name($pdo, (string) $token['public_name']);
        if (!empty($token['has_hash_prefix'])) {
            $matches = [];
            foreach ($candidates as $candidate) {
                $accountId = (int) ($candidate['id'] ?? 0);
                $publicHash = sr_member_public_account_hash($config, $accountId);
                if ($accountId > 0 && str_starts_with($publicHash, (string) $token['hash_prefix'])) {
                    $matches[] = $accountId;
                }
            }
            if (count($matches) === 1 && !isset($exclude[$matches[0]])) {
                $accountIds[$matches[0]] = true;
            }
            continue;
        }

        $matches = [];
        foreach ($candidates as $candidate) {
            $accountId = (int) ($candidate['id'] ?? 0);
            if ($accountId > 0) {
                $matches[] = $accountId;
            }
        }
        if (count($matches) === 1 && !isset($exclude[$matches[0]])) {
            $accountIds[$matches[0]] = true;
        }
    }

    return array_keys($accountIds);
}

function sr_member_mention_prefix_length_for_hashes(array $publicHashes, string $publicHash, int $minimum = 6): int
{
    $minimum = max(6, min(32, $minimum));
    for ($length = $minimum; $length <= 32; $length += 2) {
        $prefix = substr($publicHash, 0, $length);
        $count = 0;
        foreach ($publicHashes as $hash) {
            if (str_starts_with((string) $hash, $prefix)) {
                $count++;
            }
        }
        if ($count === 1) {
            return $length;
        }
    }

    return 32;
}

function sr_member_mention_search_rows(PDO $pdo, array $config, string $query, int $limit = 10, array $excludeAccountIds = []): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $limit = max(1, min(20, $limit));
    $settings = sr_member_settings($pdo);
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
    $startLike = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';

    if (!empty($settings['nickname_enabled']) && sr_member_nicknames_table_exists($pdo)) {
        $stmt = $pdo->prepare(
            "SELECT a.id, a.display_name, a.locale, a.status, n.nickname
             FROM sr_member_nicknames n
             INNER JOIN sr_member_accounts a ON a.id = n.account_id
             WHERE n.nickname LIKE :keyword ESCAPE '\\\\'
               AND a.status = 'active'
             ORDER BY CASE WHEN n.nickname LIKE :start_keyword ESCAPE '\\\\' THEN 0 ELSE 1 END, n.nickname ASC, a.id ASC
             LIMIT " . ($limit * 4)
        );
        $stmt->execute(['keyword' => $like, 'start_keyword' => $startLike]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, display_name, locale, status, '' AS nickname
             FROM sr_member_accounts
             WHERE display_name LIKE :keyword ESCAPE '\\\\'
               AND status = 'active'
             ORDER BY CASE WHEN display_name LIKE :start_keyword ESCAPE '\\\\' THEN 0 ELSE 1 END, display_name ASC, id ASC
             LIMIT " . ($limit * 4)
        );
        $stmt->execute(['keyword' => $like, 'start_keyword' => $startLike]);
    }

    $exclude = [];
    foreach ($excludeAccountIds as $accountId) {
        $accountId = (int) $accountId;
        if ($accountId > 0) {
            $exclude[$accountId] = true;
        }
    }

    $rows = [];
    $hashesByName = [];
    foreach ($stmt->fetchAll() as $row) {
        $accountId = (int) ($row['id'] ?? 0);
        if ($accountId < 1 || isset($exclude[$accountId])) {
            continue;
        }
        $publicName = sr_member_public_name($row, $settings);
        if ($publicName === '') {
            continue;
        }
        $publicHash = sr_member_public_account_hash($config, $accountId);
        $row['public_name'] = $publicName;
        $row['public_hash'] = $publicHash;
        $rows[] = $row;
        $hashesByName[$publicName][] = $publicHash;
    }

    $items = [];
    foreach ($rows as $row) {
        $publicName = (string) $row['public_name'];
        $publicHash = (string) $row['public_hash'];
        $prefixLength = sr_member_mention_prefix_length_for_hashes($hashesByName[$publicName] ?? [$publicHash], $publicHash, 6);
        $hashPrefix = substr($publicHash, 0, $prefixLength);
        $items[] = [
            'public_name' => $publicName,
            'hash_prefix' => $hashPrefix,
            'insert_text' => '@' . $publicName . '#' . $hashPrefix . ' ',
        ];
        if (count($items) >= $limit) {
            break;
        }
    }

    return $items;
}
