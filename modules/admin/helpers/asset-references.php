<?php

declare(strict_types=1);

function sr_admin_asset_reference_like(string $keyword): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
}

function sr_admin_asset_reference_search(PDO $pdo, array $config, array $options): array
{
    $table = (string) ($options['table'] ?? '');
    if (preg_match('/\Asr_[a-z0-9_]+_transactions\z/', $table) !== 1) {
        return [];
    }

    $allowedTypes = $options['allowed_types'] ?? [];
    if (!is_array($allowedTypes)) {
        $allowedTypes = [];
    }

    $type = (string) ($options['type'] ?? '');
    if (!in_array($type, $allowedTypes, true)) {
        $type = '';
    }

    $keyword = trim((string) ($options['keyword'] ?? ''));
    $limit = max(1, min(30, (int) ($options['limit'] ?? 20)));
    $params = [];
    $where = ["t.reference_type <> ''", "t.reference_id <> ''"];

    if ($type !== '') {
        $where[] = 't.reference_type = :reference_type';
        $params['reference_type'] = $type;
    }

    if ($keyword !== '') {
        $where[] = "(t.reference_id LIKE :keyword_like ESCAPE '\\\\' OR t.reason LIKE :keyword_like ESCAPE '\\\\' OR a.email LIKE :keyword_like ESCAPE '\\\\' OR a.display_name LIKE :keyword_like ESCAPE '\\\\')";
        $params['keyword_like'] = sr_admin_asset_reference_like($keyword);
    }

    $stmt = $pdo->prepare(
        'SELECT t.id, t.account_id, t.reference_type, t.reference_id, t.reason, t.created_at,
                a.email, a.display_name
         FROM ' . $table . ' t
         INNER JOIN sr_member_accounts a ON a.id = t.account_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY t.id DESC
         LIMIT 100'
    );
    $stmt->execute($params);

    $items = [];
    $seen = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }

        $referenceType = (string) ($row['reference_type'] ?? '');
        $referenceId = (string) ($row['reference_id'] ?? '');
        $key = $referenceType . ':' . $referenceId;
        if ($referenceType === '' || $referenceId === '' || isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $accountId = (int) ($row['account_id'] ?? 0);
        $items[] = [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'transaction_id' => (int) ($row['id'] ?? 0),
            'reason' => (string) ($row['reason'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'account_public_hash' => $accountId > 0 ? sr_admin_member_public_hash($config, $accountId) : '',
            'member_name' => sr_admin_member_display_name_preview($row),
            'member_email' => sr_admin_member_email_display($row),
        ];

        if (count($items) >= $limit) {
            break;
        }
    }

    return $items;
}
