<?php

declare(strict_types=1);

function sr_embed_manager_marker_pattern(): string
{
    return '/<span\b(?=[^>]*\bsr-embed-manager-marker\b)(?=[^>]*\bdata-sr-embed-manager-ref=(["\'])([^"\']+)\\1)[^>]*><\/span>/iu';
}

function sr_embed_manager_extract_marker_refs(string $bodyHtml): array
{
    if ($bodyHtml === '') {
        return [];
    }

    if (preg_match_all(sr_embed_manager_marker_pattern(), $bodyHtml, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
        return [];
    }

    $refs = [];
    $position = 0;
    foreach ($matches as $match) {
        $markerHtml = (string) ($match[0][0] ?? '');
        $attributes = sr_embed_manager_marker_attributes($markerHtml);
        $refKey = sr_embed_manager_clean_ref_key((string) ($attributes['data-sr-embed-manager-ref'] ?? ($match[2][0] ?? '')));
        if ($refKey === '') {
            continue;
        }

        $refs[] = [
            'ref_key' => $refKey,
            'target_module' => sr_embed_manager_clean_identifier((string) ($attributes['data-sr-embed-manager-target-module'] ?? '')),
            'target_type' => sr_embed_manager_clean_identifier((string) ($attributes['data-sr-embed-manager-target-type'] ?? '')),
            'target_id' => sr_embed_manager_clean_target_id((string) ($attributes['data-sr-embed-manager-target-id'] ?? '')),
            'variant' => sr_embed_manager_clean_identifier((string) ($attributes['data-sr-embed-manager-variant'] ?? 'card')),
            'label_snapshot' => sr_embed_manager_clean_label((string) ($attributes['data-sr-embed-manager-label'] ?? '')),
            'position' => $position,
            'source_offset' => (int) ($match[0][1] ?? 0),
        ];
        $position++;
    }

    return $refs;
}

function sr_embed_manager_marker_attributes(string $markerHtml): array
{
    if ($markerHtml === '' || preg_match_all('/\s([a-z0-9_-]+)\s*=\s*(["\'])(.*?)\\2/iu', $markerHtml, $matches, PREG_SET_ORDER) < 1) {
        return [];
    }

    $attributes = [];
    foreach ($matches as $match) {
        $name = strtolower((string) ($match[1] ?? ''));
        $attributes[$name] = html_entity_decode((string) ($match[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return $attributes;
}

function sr_embed_manager_clean_ref_key(string $value): string
{
    $value = trim($value);
    return preg_match('/\Aem_[a-z0-9_]{6,70}\z/', $value) === 1 ? $value : '';
}

function sr_embed_manager_clean_identifier(string $value): string
{
    $value = trim($value);
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) === 1 ? $value : '';
}

function sr_embed_manager_clean_target_id(string $value): string
{
    $value = trim($value);
    return preg_match('/\A[1-9][0-9]{0,19}\z/', $value) === 1 ? $value : '';
}

function sr_embed_manager_clean_label(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, 255) : substr($value, 0, 255);
}

function sr_embed_manager_new_ref_key(): string
{
    try {
        return 'em_' . bin2hex(random_bytes(16));
    } catch (Throwable $exception) {
        return 'em_' . str_replace('.', '_', uniqid('', true));
    }
}

function sr_embed_manager_search_payload(string $targetModule, string $targetType, string $targetId, string $label, string $variant = 'card'): array
{
    return [
        'ref_key' => sr_embed_manager_new_ref_key(),
        'target_module' => sr_embed_manager_clean_identifier($targetModule),
        'target_type' => sr_embed_manager_clean_identifier($targetType),
        'target_id' => sr_embed_manager_clean_target_id($targetId),
        'variant' => sr_embed_manager_clean_identifier($variant) ?: 'card',
        'label' => sr_embed_manager_clean_label($label),
    ];
}

function sr_embed_manager_allowed_statuses(): array
{
    return ['active', 'removed', 'broken', 'private', 'deleted'];
}

function sr_embed_manager_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_embed_manager_refs LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_embed_manager_admin_refs(PDO $pdo, array $filters, int $limit = 100): array
{
    if (!sr_embed_manager_table_exists($pdo)) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $where = [];
    $params = [];

    $statusValues = isset($filters['status']) && is_array($filters['status']) ? $filters['status'] : [];
    $status = $statusValues === [] ? '' : (string) $statusValues[0];
    if ($status !== '' && in_array($status, sr_embed_manager_allowed_statuses(), true)) {
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
            FROM sr_embed_manager_refs'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
        . ' ORDER BY updated_at DESC, id DESC
            LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_embed_manager_sync_body_refs(PDO $pdo, string $ownerModule, string $ownerType, int $ownerId, string $ownerField, string $bodyHtml, ?int $accountId = null): void
{
    if ($ownerId < 1 || !sr_embed_manager_table_exists($pdo)) {
        return;
    }

    $ownerModule = sr_embed_manager_clean_identifier($ownerModule);
    $ownerType = sr_embed_manager_clean_identifier($ownerType);
    $ownerField = sr_embed_manager_clean_identifier($ownerField) ?: 'body';
    if ($ownerModule === '' || $ownerType === '') {
        throw new InvalidArgumentException('임베드 참조 소유자 정보가 올바르지 않습니다.');
    }

    $markers = sr_embed_manager_extract_marker_refs($bodyHtml);
    $seen = [];
    foreach ($markers as $marker) {
        $refKey = (string) $marker['ref_key'];
        if (isset($seen[$refKey])) {
            throw new InvalidArgumentException('본문 임베드 참조 키가 중복되었습니다.');
        }
        $seen[$refKey] = true;
    }

    $existing = sr_embed_manager_owner_refs_by_key($pdo, $ownerModule, $ownerType, $ownerId, $ownerField);
    $now = sr_now();
    $activeKeys = [];
    foreach ($markers as $marker) {
        $refKey = (string) $marker['ref_key'];
        $targetModule = (string) ($marker['target_module'] ?? '');
        $targetType = (string) ($marker['target_type'] ?? '');
        $targetId = (string) ($marker['target_id'] ?? '');
        $variant = (string) ($marker['variant'] ?? 'card');
        $label = (string) ($marker['label_snapshot'] ?? '');

        $knownGlobal = sr_embed_manager_ref_by_key($pdo, $refKey);
        if (is_array($knownGlobal)
            && ((string) ($knownGlobal['owner_module'] ?? '') !== $ownerModule
                || (string) ($knownGlobal['owner_type'] ?? '') !== $ownerType
                || (int) ($knownGlobal['owner_id'] ?? 0) !== $ownerId
                || (string) ($knownGlobal['owner_field'] ?? '') !== $ownerField)
        ) {
            throw new InvalidArgumentException('본문 임베드 참조 키가 다른 문서에서 이미 사용 중입니다.');
        }

        if ($targetModule === '' || $targetType === '' || $targetId === '') {
            $known = $existing[$refKey] ?? null;
            if (is_array($known)) {
                $targetModule = (string) ($known['target_module'] ?? '');
                $targetType = (string) ($known['target_type'] ?? '');
                $targetId = (string) ($known['target_id'] ?? '');
                $variant = $variant !== '' ? $variant : (string) ($known['variant'] ?? 'card');
                $label = $label !== '' ? $label : (string) ($known['label_snapshot'] ?? '');
            }
        }

        $target = sr_embed_manager_resolve_target($pdo, $targetModule, $targetType, $targetId);
        if ($target === null) {
            throw new InvalidArgumentException('본문 임베드 대상이 올바르지 않습니다.');
        }
        if ($label === '') {
            $label = (string) ($target['label_snapshot'] ?? '');
        }

        sr_embed_manager_upsert_ref($pdo, [
            'ref_key' => $refKey,
            'owner_module' => $ownerModule,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'owner_field' => $ownerField,
            'target_module' => $targetModule,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'variant' => $variant !== '' ? $variant : 'card',
            'label_snapshot' => $label,
            'image_snapshot' => (string) ($target['image_snapshot'] ?? ''),
            'sort_order' => (int) ($marker['position'] ?? 0),
            'status' => (string) ($target['status'] ?? 'active'),
            'created_by_account_id' => $accountId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $activeKeys[] = $refKey;
    }

    sr_embed_manager_remove_missing_owner_refs($pdo, $ownerModule, $ownerType, $ownerId, $ownerField, $activeKeys, $now);
}

function sr_embed_manager_owner_refs_by_key(PDO $pdo, string $ownerModule, string $ownerType, int $ownerId, string $ownerField): array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_embed_manager_refs
         WHERE owner_module = :owner_module
           AND owner_type = :owner_type
           AND owner_id = :owner_id
           AND owner_field = :owner_field'
    );
    $stmt->execute([
        'owner_module' => $ownerModule,
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'owner_field' => $ownerField,
    ]);

    $refs = [];
    foreach ($stmt->fetchAll() as $row) {
        $refs[(string) ($row['ref_key'] ?? '')] = $row;
    }

    return $refs;
}

function sr_embed_manager_ref_by_key(PDO $pdo, string $refKey): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM sr_embed_manager_refs WHERE ref_key = :ref_key LIMIT 1');
    $stmt->execute(['ref_key' => $refKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_embed_manager_resolve_target(PDO $pdo, string $targetModule, string $targetType, string $targetId): ?array
{
    $targetModule = sr_embed_manager_clean_identifier($targetModule);
    $targetType = sr_embed_manager_clean_identifier($targetType);
    $targetId = sr_embed_manager_clean_target_id($targetId);
    if ($targetModule === '' || $targetType === '' || $targetId === '') {
        return null;
    }

    if ($targetModule === 'content' && $targetType === 'content') {
        return sr_embed_manager_resolve_content_target($pdo, (int) $targetId);
    }
    if ($targetModule === 'community' && $targetType === 'post') {
        return sr_embed_manager_resolve_community_post_target($pdo, (int) $targetId);
    }

    return null;
}

function sr_embed_manager_resolve_content_target(PDO $pdo, int $contentId): ?array
{
    if ($contentId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, title, status, cover_image_url FROM sr_content_items WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $contentId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return [
            'label_snapshot' => '콘텐츠 #' . (string) $contentId,
            'image_snapshot' => '',
            'status' => 'broken',
        ];
    }

    return [
        'label_snapshot' => (string) ($row['title'] ?? ('콘텐츠 #' . (string) $contentId)),
        'image_snapshot' => (string) ($row['cover_image_url'] ?? ''),
        'status' => (string) ($row['status'] ?? '') === 'published' ? 'active' : 'private',
    ];
}

function sr_embed_manager_resolve_community_post_target(PDO $pdo, int $postId): ?array
{
    if ($postId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT p.id, p.title, p.status, b.status AS board_status, b.read_policy
         FROM sr_community_posts p
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         WHERE p.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $postId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return [
            'label_snapshot' => '게시글 #' . (string) $postId,
            'image_snapshot' => '',
            'status' => 'broken',
        ];
    }

    $postStatus = (string) ($row['status'] ?? '');
    if ($postStatus === 'deleted') {
        $status = 'deleted';
    } elseif ($postStatus === 'published' && (string) ($row['board_status'] ?? '') === 'enabled' && (string) ($row['read_policy'] ?? 'public') === 'public') {
        $status = 'active';
    } else {
        $status = 'private';
    }

    return [
        'label_snapshot' => (string) ($row['title'] ?? ('게시글 #' . (string) $postId)),
        'image_snapshot' => '',
        'status' => $status,
    ];
}

function sr_embed_manager_upsert_ref(PDO $pdo, array $ref): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO sr_embed_manager_refs
            (ref_key, owner_module, owner_type, owner_id, owner_field, target_module, target_type, target_id, variant, label_snapshot, image_snapshot, sort_order, status, created_by_account_id, created_at, updated_at)
         VALUES
            (:ref_key, :owner_module, :owner_type, :owner_id, :owner_field, :target_module, :target_type, :target_id, :variant, :label_snapshot, :image_snapshot, :sort_order, :status, :created_by_account_id, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            owner_module = VALUES(owner_module),
            owner_type = VALUES(owner_type),
            owner_id = VALUES(owner_id),
            owner_field = VALUES(owner_field),
            target_module = VALUES(target_module),
            target_type = VALUES(target_type),
            target_id = VALUES(target_id),
            variant = VALUES(variant),
            label_snapshot = VALUES(label_snapshot),
            image_snapshot = VALUES(image_snapshot),
            sort_order = VALUES(sort_order),
            status = VALUES(status),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute($ref);
}

function sr_embed_manager_remove_missing_owner_refs(PDO $pdo, string $ownerModule, string $ownerType, int $ownerId, string $ownerField, array $activeKeys, string $now): void
{
    $params = [
        'owner_module' => $ownerModule,
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'owner_field' => $ownerField,
        'updated_at' => $now,
    ];
    $sql = 'UPDATE sr_embed_manager_refs
            SET status = \'removed\', updated_at = :updated_at
            WHERE owner_module = :owner_module
              AND owner_type = :owner_type
              AND owner_id = :owner_id
              AND owner_field = :owner_field';
    $cleanKeys = [];
    foreach ($activeKeys as $index => $refKey) {
        $cleanKey = sr_embed_manager_clean_ref_key((string) $refKey);
        if ($cleanKey === '') {
            continue;
        }
        $placeholder = 'ref_key_' . (string) $index;
        $params[$placeholder] = $cleanKey;
        $cleanKeys[] = ':' . $placeholder;
    }
    if ($cleanKeys !== []) {
        $sql .= ' AND ref_key NOT IN (' . implode(', ', $cleanKeys) . ')';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function sr_embed_manager_rewrite_body_refs_for_copy(PDO $pdo, string $sourceOwnerModule, string $sourceOwnerType, int $sourceOwnerId, string $sourceOwnerField, string $targetOwnerModule, string $targetOwnerType, int $targetOwnerId, string $targetOwnerField, string $bodyHtml, ?int $accountId = null): string
{
    if ($sourceOwnerId < 1 || $targetOwnerId < 1 || $bodyHtml === '' || !sr_embed_manager_table_exists($pdo)) {
        return $bodyHtml;
    }

    $sourceOwnerModule = sr_embed_manager_clean_identifier($sourceOwnerModule);
    $sourceOwnerType = sr_embed_manager_clean_identifier($sourceOwnerType);
    $sourceOwnerField = sr_embed_manager_clean_identifier($sourceOwnerField) ?: 'body';
    $targetOwnerModule = sr_embed_manager_clean_identifier($targetOwnerModule);
    $targetOwnerType = sr_embed_manager_clean_identifier($targetOwnerType);
    $targetOwnerField = sr_embed_manager_clean_identifier($targetOwnerField) ?: 'body';
    $sourceRefs = sr_embed_manager_owner_refs_by_key($pdo, $sourceOwnerModule, $sourceOwnerType, $sourceOwnerId, $sourceOwnerField);
    if ($sourceRefs === []) {
        return $bodyHtml;
    }

    $map = [];
    foreach (sr_embed_manager_extract_marker_refs($bodyHtml) as $marker) {
        $oldKey = (string) ($marker['ref_key'] ?? '');
        if ($oldKey !== '' && isset($sourceRefs[$oldKey]) && !isset($map[$oldKey])) {
            $map[$oldKey] = sr_embed_manager_new_ref_key();
        }
    }
    if ($map === []) {
        return $bodyHtml;
    }

    $rewritten = preg_replace_callback(sr_embed_manager_marker_pattern(), static function (array $matches) use ($map): string {
        $attributes = sr_embed_manager_marker_attributes((string) ($matches[0] ?? ''));
        $oldKey = sr_embed_manager_clean_ref_key((string) ($attributes['data-sr-embed-manager-ref'] ?? ''));
        if ($oldKey === '' || !isset($map[$oldKey])) {
            return (string) ($matches[0] ?? '');
        }

        return preg_replace('/\bdata-sr-embed-manager-ref=(["\'])[^"\']+\\1/iu', 'data-sr-embed-manager-ref="${1}' . $map[$oldKey] . '${1}', (string) ($matches[0] ?? '')) ?? (string) ($matches[0] ?? '');
    }, $bodyHtml) ?? $bodyHtml;

    $now = sr_now();
    foreach ($map as $oldKey => $newKey) {
        $sourceRef = $sourceRefs[$oldKey];
        sr_embed_manager_upsert_ref($pdo, [
            'ref_key' => $newKey,
            'owner_module' => $targetOwnerModule,
            'owner_type' => $targetOwnerType,
            'owner_id' => $targetOwnerId,
            'owner_field' => $targetOwnerField,
            'target_module' => (string) ($sourceRef['target_module'] ?? ''),
            'target_type' => (string) ($sourceRef['target_type'] ?? ''),
            'target_id' => (string) ($sourceRef['target_id'] ?? ''),
            'variant' => (string) ($sourceRef['variant'] ?? 'card'),
            'label_snapshot' => (string) ($sourceRef['label_snapshot'] ?? ''),
            'image_snapshot' => (string) ($sourceRef['image_snapshot'] ?? ''),
            'sort_order' => (int) ($sourceRef['sort_order'] ?? 0),
            'status' => (string) ($sourceRef['status'] ?? 'active'),
            'created_by_account_id' => $accountId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    sr_embed_manager_sync_body_refs($pdo, $targetOwnerModule, $targetOwnerType, $targetOwnerId, $targetOwnerField, $rewritten, $accountId);

    return $rewritten;
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
