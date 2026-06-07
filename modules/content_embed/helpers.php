<?php

declare(strict_types=1);

function sr_content_embed_marker_pattern(): string
{
    return '/<span\b(?=[^>]*\bsr-content-embed-marker\b)(?=[^>]*\bdata-sr-content-embed-ref=(["\'])([^"\']+)\\1)[^>]*><\/span>/iu';
}

function sr_content_embed_extract_marker_refs(string $bodyHtml): array
{
    if ($bodyHtml === '') {
        return [];
    }

    if (preg_match_all(sr_content_embed_marker_pattern(), $bodyHtml, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
        return [];
    }

    $refs = [];
    $position = 0;
    foreach ($matches as $match) {
        $refKey = sr_content_embed_clean_ref_key((string) ($match[2][0] ?? ''));
        if ($refKey === '') {
            continue;
        }

        $refs[] = [
            'ref_key' => $refKey,
            'position' => $position,
            'source_offset' => (int) ($match[0][1] ?? 0),
        ];
        $position++;
    }

    return $refs;
}

function sr_content_embed_clean_ref_key(string $value): string
{
    $value = trim($value);
    return preg_match('/\Ace_[a-z0-9_]{6,70}\z/', $value) === 1 ? $value : '';
}

function sr_content_embed_allowed_statuses(): array
{
    return ['active', 'removed', 'broken', 'private', 'deleted'];
}

function sr_content_embed_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_content_embed_refs LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_content_embed_admin_refs(PDO $pdo, array $filters, int $limit = 100): array
{
    if (!sr_content_embed_table_exists($pdo)) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $where = [];
    $params = [];

    $status = (string) ($filters['status'] ?? '');
    if ($status !== '' && in_array($status, sr_content_embed_allowed_statuses(), true)) {
        $where[] = 'status = :status';
        $params['status'] = $status;
    }

    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $keyword = function_exists('mb_substr') ? mb_substr($keyword, 0, 120) : substr($keyword, 0, 120);
        $where[] = '(ref_key LIKE :keyword OR owner_module LIKE :keyword OR target_module LIKE :keyword OR target_type LIKE :keyword OR target_id LIKE :keyword OR label_snapshot LIKE :keyword)';
        $params['keyword'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
    }

    $sql = 'SELECT *
            FROM sr_content_embed_refs'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
        . ' ORDER BY updated_at DESC, id DESC
            LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_link_card_token_pattern(): string
{
    return '/\{\{sr_link_card\s+([^{}]+)\}\}/u';
}

function sr_link_card_extract_tokens(string $bodyText): array
{
    if ($bodyText === '') {
        return [];
    }

    if (preg_match_all(sr_link_card_token_pattern(), $bodyText, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
        return [];
    }

    $tokens = [];
    $position = 0;
    foreach ($matches as $match) {
        $fields = sr_link_card_parse_attributes((string) $match[1][0]);
        $fields['raw_token'] = (string) $match[0][0];
        $fields['position'] = $position;
        $fields['source_offset'] = (int) $match[0][1];
        $tokens[] = $fields;
        $position++;
    }

    return $tokens;
}

function sr_link_card_parse_attributes(string $attributeText): array
{
    $fields = [
        'module' => '',
        'entity_type' => '',
        'entity_id' => '',
        'variant' => 'compact',
        'label' => '',
        'slot' => 'body',
    ];

    if (preg_match_all('/([a-z_]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^"\s]+))/u', $attributeText, $matches, PREG_SET_ORDER) < 1) {
        return $fields;
    }

    foreach ($matches as $match) {
        $key = (string) ($match[1] ?? '');
        if (!array_key_exists($key, $fields)) {
            continue;
        }

        $value = (string) ($match[2] ?? ($match[3] ?? ($match[4] ?? '')));
        $fields[$key] = sr_link_card_clean_field($key, html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    return $fields;
}

function sr_link_card_clean_field(string $key, string $value): string
{
    $value = trim($value);
    if ($key === 'module') {
        return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) === 1 ? $value : '';
    }
    if ($key === 'entity_type' || $key === 'variant' || $key === 'slot') {
        return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) === 1 ? $value : ($key === 'variant' ? 'compact' : '');
    }
    if ($key === 'entity_id') {
        return preg_match('/\A[1-9][0-9]{0,19}\z/', $value) === 1 ? $value : '';
    }
    if ($key === 'label') {
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        return function_exists('mb_substr') ? mb_substr($value, 0, 120) : substr($value, 0, 120);
    }

    return '';
}

function sr_link_card_token_rejection_errors(string $bodyText): array
{
    if (sr_link_card_extract_tokens($bodyText) === []) {
        return [];
    }

    return ['본문에는 링크 카드 토큰을 저장할 수 없습니다. 검색 삽입은 일반 HTML 또는 텍스트 링크로 저장해 주세요.'];
}

function sr_link_card_clear_legacy_refs(PDO $pdo, string $table, string $subjectColumn, int $subjectId): void
{
    if ($subjectId < 1 || !sr_link_card_table_is_allowed($table, $subjectColumn) || !sr_link_card_table_exists($pdo, $table)) {
        return;
    }

    $delete = $pdo->prepare('DELETE FROM ' . $table . ' WHERE ' . $subjectColumn . ' = :subject_id');
    $delete->execute(['subject_id' => $subjectId]);
}

function sr_link_card_table_exists(PDO $pdo, string $table): bool
{
    if (!sr_link_card_table_is_allowed($table, sr_link_card_subject_column_for_table($table))) {
        return false;
    }

    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    try {
        $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
        $cache[$table] = true;
    } catch (Throwable $exception) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

function sr_link_card_table_is_allowed(string $table, string $subjectColumn): bool
{
    return ($table === 'sr_content_link_refs' && $subjectColumn === 'content_id')
        || ($table === 'sr_community_link_refs' && $subjectColumn === 'post_id');
}

function sr_link_card_subject_column_for_table(string $table): string
{
    if ($table === 'sr_content_link_refs') {
        return 'content_id';
    }
    if ($table === 'sr_community_link_refs') {
        return 'post_id';
    }

    return '';
}
