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

function sr_embed_manager_normalize_contract_target(array $definition): array
{
    $targetModule = sr_embed_manager_clean_identifier((string) ($definition['target_module'] ?? ''));
    $targetType = sr_embed_manager_clean_identifier((string) ($definition['target_type'] ?? ''));
    if ($targetModule === '' || $targetType === '') {
        return [];
    }

    $variants = [];
    foreach ((array) ($definition['allowed_variants'] ?? ['card']) as $variant) {
        $variant = sr_embed_manager_clean_identifier((string) $variant);
        if ($variant !== '') {
            $variants[$variant] = true;
        }
    }
    if ($variants === []) {
        $variants['card'] = true;
    }

    foreach (['search', 'resolve'] as $callableKey) {
        if (!is_callable($definition[$callableKey] ?? null)) {
            return [];
        }
    }

    $definition['target_module'] = $targetModule;
    $definition['target_type'] = $targetType;
    $definition['allowed_variants'] = array_keys($variants);
    $definition['default_variant'] = in_array((string) ($definition['default_variant'] ?? 'card'), $definition['allowed_variants'], true)
        ? (string) ($definition['default_variant'] ?? 'card')
        : $definition['allowed_variants'][0];

    return $definition;
}

function sr_embed_manager_contract_targets(PDO $pdo): array
{
    $targets = [];
    foreach (sr_enabled_module_contract_files($pdo, 'embed-manager-targets.php', ['embed_manager']) as $moduleKey => $file) {
        $contract = sr_load_module_contract_file($moduleKey, $file);
        if (!is_array($contract)) {
            continue;
        }

        foreach ((array) ($contract['targets'] ?? []) as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $definition = sr_embed_manager_normalize_contract_target($definition);
            if ($definition === []) {
                continue;
            }

            $targets[(string) $definition['target_module']][(string) $definition['target_type']] = $definition;
        }
    }

    return $targets;
}

function sr_embed_manager_contract_target(PDO $pdo, string $targetModule, string $targetType): ?array
{
    $targetModule = sr_embed_manager_clean_identifier($targetModule);
    $targetType = sr_embed_manager_clean_identifier($targetType);
    if ($targetModule === '' || $targetType === '') {
        return null;
    }

    $targets = sr_embed_manager_contract_targets($pdo);
    return isset($targets[$targetModule][$targetType]) && is_array($targets[$targetModule][$targetType])
        ? $targets[$targetModule][$targetType]
        : null;
}

function sr_embed_manager_normalize_target_filter(string $target): array
{
    $target = trim($target);
    $legacy = [
        'content' => ['content', 'content'],
        'community_post' => ['community', 'post'],
        'post' => ['community', 'post'],
        'quiz_set' => ['quiz', 'quiz_set'],
        'survey_form' => ['survey', 'survey_form'],
    ];
    if (isset($legacy[$target])) {
        return [$legacy[$target]];
    }

    foreach ([':', '/', '.'] as $separator) {
        if (str_contains($target, $separator)) {
            [$module, $type] = array_pad(explode($separator, $target, 2), 2, '');
            $module = sr_embed_manager_clean_identifier($module);
            $type = sr_embed_manager_clean_identifier($type);
            return $module !== '' && $type !== '' ? [[$module, $type]] : [];
        }
    }

    return [];
}

function sr_embed_manager_target_filters_from_value(string $value): array
{
    $filters = [];
    foreach (preg_split('/\s*,\s*/', $value) ?: [] as $target) {
        foreach (sr_embed_manager_normalize_target_filter((string) $target) as $filter) {
            $filters[implode(':', $filter)] = $filter;
        }
    }

    return array_values($filters);
}

function sr_embed_manager_search_result_limit(int $limit, array $filterMap): int
{
    $limit = max(1, min(30, $limit));
    if ($filterMap === []) {
        return $limit;
    }

    return min(90, $limit * max(1, count($filterMap)));
}

function sr_embed_manager_normalize_target_result(array $row, string $targetModule, string $targetType, array $definition): ?array
{
    $targetId = sr_embed_manager_clean_target_id((string) ($row['target_id'] ?? $row['entity_id'] ?? $row['id'] ?? ''));
    if ($targetId === '') {
        return null;
    }

    $label = sr_embed_manager_clean_label((string) ($row['label_snapshot'] ?? $row['title'] ?? ''));
    if ($label === '') {
        $label = $targetModule . ' #' . $targetId;
    }

    $status = (string) ($row['status'] ?? 'active');
    if (!in_array($status, sr_embed_manager_allowed_statuses(), true)) {
        $status = 'broken';
    }

    $variant = (string) ($row['variant'] ?? $definition['default_variant'] ?? 'card');
    if (!in_array($variant, (array) ($definition['allowed_variants'] ?? ['card']), true)) {
        $variant = (string) ($definition['default_variant'] ?? 'card');
    }

    $publicUrl = sr_embed_manager_safe_url((string) ($row['public_url'] ?? $row['url'] ?? ''));
    $adminUrl = sr_embed_manager_safe_url((string) ($row['admin_url'] ?? ''));

    return [
        'module' => $targetModule,
        'entity_type' => $targetType,
        'entity_id' => $targetId,
        'title' => $label,
        'summary' => sr_embed_manager_clean_summary((string) ($row['summary'] ?? '')),
        'url' => $publicUrl,
        'admin_url' => $adminUrl,
        'status' => $status,
        'meta' => sr_embed_manager_clean_summary((string) ($row['meta'] ?? '')),
        'embed' => sr_embed_manager_search_payload($targetModule, $targetType, $targetId, $label, $variant),
    ];
}

function sr_embed_manager_search_targets(PDO $pdo, string $keyword, int $limit, array $context = []): array
{
    $limit = max(1, min(30, $limit));
    $filters = sr_embed_manager_target_filters_from_value((string) ($context['targets'] ?? $context['target'] ?? ''));
    $filterMap = [];
    foreach ($filters as $filter) {
        $filterMap[implode(':', $filter)] = true;
    }

    $items = [];
    foreach (sr_embed_manager_contract_targets($pdo) as $targetModule => $types) {
        foreach ($types as $targetType => $definition) {
            if ($filterMap !== [] && !isset($filterMap[$targetModule . ':' . $targetType])) {
                continue;
            }

            $search = $definition['search'] ?? null;
            if (!is_callable($search)) {
                continue;
            }

            try {
                $rows = $search($pdo, [
                    'keyword' => $keyword,
                    'limit' => $limit,
                    'context' => (string) ($context['context'] ?? 'public'),
                    'viewer_account_id' => (int) ($context['viewer_account_id'] ?? 0),
                    'owner_module' => (string) ($context['owner_module'] ?? ''),
                    'owner_type' => (string) ($context['owner_type'] ?? ''),
                ]);
            } catch (Throwable $exception) {
                sr_log_exception($exception, 'embed_manager_search_failed_' . $targetModule . '_' . $targetType);
                continue;
            }

            foreach ((array) $rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $item = sr_embed_manager_normalize_target_result($row, (string) $targetModule, (string) $targetType, $definition);
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
        }
    }

    return array_slice($items, 0, sr_embed_manager_search_result_limit($limit, $filterMap));
}

function sr_embed_manager_clean_summary(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, 240) : substr($value, 0, 240);
}

function sr_embed_manager_safe_url(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (sr_is_safe_relative_url($value) || (sr_is_http_url($value) && strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https')) {
        return $value;
    }

    return '';
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

    sr_embed_manager_refresh_known_ref_statuses($pdo);

    $sql = 'SELECT *
            FROM sr_embed_manager_refs'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
        . ' ORDER BY updated_at DESC, id DESC
            LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_embed_manager_refresh_known_ref_statuses(PDO $pdo, int $limit = 300): void
{
    if (!sr_embed_manager_table_exists($pdo)) {
        return;
    }

    $limit = max(1, min(1000, $limit));
    $stmt = $pdo->query(
        'SELECT ref_key, target_module, target_type, target_id, label_snapshot, image_snapshot, status
         FROM sr_embed_manager_refs
         WHERE status <> \'removed\'
         ORDER BY updated_at DESC, id DESC
         LIMIT ' . $limit
    );
    $refs = $stmt->fetchAll();
    if ($refs === []) {
        return;
    }

    $update = $pdo->prepare(
        'UPDATE sr_embed_manager_refs
         SET label_snapshot = :label_snapshot,
             image_snapshot = :image_snapshot,
             status = :status,
             updated_at = :updated_at
         WHERE ref_key = :ref_key'
    );
    $now = sr_now();
    foreach ($refs as $ref) {
        $target = sr_embed_manager_resolve_target($pdo, (string) ($ref['target_module'] ?? ''), (string) ($ref['target_type'] ?? ''), (string) ($ref['target_id'] ?? ''));
        if ($target === null) {
            continue;
        }

        $labelSnapshot = (string) ($target['label_snapshot'] ?? '');
        $imageSnapshot = (string) ($target['image_snapshot'] ?? '');
        $status = (string) ($target['status'] ?? 'active');
        if ($labelSnapshot === (string) ($ref['label_snapshot'] ?? '')
            && $imageSnapshot === (string) ($ref['image_snapshot'] ?? '')
            && $status === (string) ($ref['status'] ?? '')
        ) {
            continue;
        }

        $update->execute([
            'label_snapshot' => $labelSnapshot,
            'image_snapshot' => $imageSnapshot,
            'status' => $status,
            'updated_at' => $now,
            'ref_key' => (string) ($ref['ref_key'] ?? ''),
        ]);
    }
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
        $allowedVariants = (array) ($target['allowed_variants'] ?? ['card']);
        if ($variant === '') {
            $variant = (string) ($target['default_variant'] ?? 'card');
        }
        if (!in_array($variant, $allowedVariants, true)) {
            throw new InvalidArgumentException('본문 임베드 표시 방식이 지원되지 않습니다.');
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

function sr_embed_manager_resolve_target(PDO $pdo, string $targetModule, string $targetType, string $targetId, array $context = []): ?array
{
    $targetModule = sr_embed_manager_clean_identifier($targetModule);
    $targetType = sr_embed_manager_clean_identifier($targetType);
    $targetId = sr_embed_manager_clean_target_id($targetId);
    if ($targetModule === '' || $targetType === '' || $targetId === '') {
        return null;
    }

    $definition = sr_embed_manager_contract_target($pdo, $targetModule, $targetType);
    if (!is_array($definition) || !is_callable($definition['resolve'] ?? null)) {
        return null;
    }

    try {
        $target = $definition['resolve']($pdo, array_merge($context, ['target_id' => $targetId]));
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'embed_manager_resolve_failed_' . $targetModule . '_' . $targetType);
        return null;
    }

    if (!is_array($target)) {
        return null;
    }

    $target['target_module'] = $targetModule;
    $target['target_type'] = $targetType;
    $target['target_id'] = $targetId;
    $target['label_snapshot'] = sr_embed_manager_clean_label((string) ($target['label_snapshot'] ?? $target['title'] ?? ''));
    $target['summary'] = sr_embed_manager_clean_summary((string) ($target['summary'] ?? ''));
    $target['image_snapshot'] = sr_embed_manager_safe_url((string) ($target['image_snapshot'] ?? ''));
    $target['public_url'] = sr_embed_manager_safe_url((string) ($target['public_url'] ?? $target['url'] ?? ''));
    $target['admin_url'] = sr_embed_manager_safe_url((string) ($target['admin_url'] ?? ''));
    $target['status'] = in_array((string) ($target['status'] ?? 'active'), sr_embed_manager_allowed_statuses(), true) ? (string) $target['status'] : 'broken';
    $target['allowed_variants'] = (array) ($definition['allowed_variants'] ?? ['card']);
    $target['default_variant'] = (string) ($definition['default_variant'] ?? 'card');

    if ((string) $target['label_snapshot'] === '') {
        $target['label_snapshot'] = $targetModule . ' #' . $targetId;
    }

    return $target;
}

function sr_embed_manager_owner_public_url(PDO $pdo, string $ownerModule, string $ownerType, int $ownerId): string
{
    if ($ownerModule === 'content' && $ownerType === 'content' && $ownerId > 0) {
        $stmt = $pdo->prepare('SELECT slug FROM sr_content_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $ownerId]);
        $row = $stmt->fetch();
        if (is_array($row) && function_exists('sr_content_path')) {
            return sr_content_path((string) ($row['slug'] ?? ''));
        }
    }

    if ($ownerModule === 'community' && $ownerType === 'post' && $ownerId > 0) {
        return '/community/post?id=' . rawurlencode((string) $ownerId);
    }

    return '';
}

function sr_embed_manager_quiz_source_context_for_owner(PDO $pdo, int $quizId, string $ownerModule, string $ownerType, int $ownerId): array
{
    $sourceModule = '';
    $sourceType = '';
    if ($ownerModule === 'content' && $ownerType === 'content') {
        $sourceModule = 'content';
        $sourceType = 'content_item';
    } elseif ($ownerModule === 'community' && $ownerType === 'post') {
        $sourceModule = 'community';
        $sourceType = 'community_post';
    }

    if ($quizId < 1 || $sourceModule === '' || $ownerId < 1) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id
             FROM sr_quiz_sources
             WHERE quiz_id = :quiz_id
               AND source_module = :source_module
               AND source_type = :source_type
               AND source_id = :source_id
               AND status = \'active\'
             LIMIT 1'
        );
        $stmt->execute([
            'quiz_id' => $quizId,
            'source_module' => $sourceModule,
            'source_type' => $sourceType,
            'source_id' => $ownerId,
        ]);
    } catch (Throwable $exception) {
        return [];
    }

    return is_array($stmt->fetch()) ? [
        'source_module' => $sourceModule,
        'source_type' => $sourceType,
        'source_id' => (string) $ownerId,
    ] : [];
}

function sr_embed_manager_target_url(PDO $pdo, array $ref, array $target, array $context): string
{
    $url = sr_embed_manager_safe_url((string) ($target['public_url'] ?? ''));
    if ($url === '') {
        return '';
    }

    $ownerModule = (string) ($context['owner_module'] ?? '');
    $ownerType = (string) ($context['owner_type'] ?? '');
    $ownerId = (int) ($context['owner_id'] ?? 0);
    $returnTo = sr_embed_manager_safe_url((string) ($context['return_to'] ?? ''));
    if ($returnTo === '') {
        $returnTo = sr_embed_manager_owner_public_url($pdo, $ownerModule, $ownerType, $ownerId);
    }

    $query = [];
    if ($returnTo !== '') {
        $query['return_to'] = $returnTo;
    }
    if ((string) ($ref['target_module'] ?? '') === 'quiz') {
        $source = sr_embed_manager_quiz_source_context_for_owner($pdo, (int) ($ref['target_id'] ?? 0), $ownerModule, $ownerType, $ownerId);
        $query = array_merge($query, $source);
    }

    if ($query === []) {
        return $url;
    }

    return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function sr_embed_manager_render_target_card(PDO $pdo, array $ref, array $target, array $context): string
{
    $status = (string) ($target['status'] ?? 'broken');
    $mode = (string) ($context['mode'] ?? 'public');
    if ($mode === 'public' && $status !== 'active') {
        return '';
    }

    $url = sr_embed_manager_target_url($pdo, $ref, $target, $context);
    $title = (string) ($target['label_snapshot'] ?? $ref['label_snapshot'] ?? '');
    $summary = (string) ($target['summary'] ?? '');
    $variant = (string) ($ref['variant'] ?? 'card');
    $statusLabel = $status === 'active' ? '' : ' (' . $status . ')';
    $class = 'sr-embed-manager-card sr-embed-manager-card-' . sr_e($variant);

    $html = '<aside class="' . $class . '" data-sr-embed-manager-rendered="1">';
    $html .= '<strong>';
    if ($url !== '' && $status === 'active') {
        $html .= '<a href="' . sr_e($url) . '">' . sr_e($title) . '</a>';
    } else {
        $html .= sr_e($title);
    }
    $html .= sr_e($statusLabel) . '</strong>';
    if ($summary !== '' && $variant !== 'button') {
        $html .= '<p>' . sr_e($summary) . '</p>';
    }
    if ($url !== '' && $status === 'active') {
        $label = (string) ($ref['target_module'] ?? '') === 'survey' ? '설문 참여' : ((string) ($ref['target_module'] ?? '') === 'quiz' ? '퀴즈 풀기' : '열기');
        $html .= '<p><a class="btn btn-solid-primary" href="' . sr_e($url) . '">' . sr_e($label) . '</a></p>';
    } elseif ($mode !== 'public' && (string) ($target['admin_url'] ?? '') !== '') {
        $html .= '<p><a class="btn btn-solid-light" href="' . sr_e((string) $target['admin_url']) . '">관리 화면</a></p>';
    }

    return $html . '</aside>';
}

function sr_embed_manager_render_body_html(PDO $pdo, string $bodyHtml, string $ownerModule, string $ownerType, int $ownerId, string $ownerField = 'body', array $context = []): string
{
    if ($bodyHtml === '' || $ownerId < 1 || !sr_embed_manager_table_exists($pdo)) {
        return $bodyHtml;
    }

    $ownerModule = sr_embed_manager_clean_identifier($ownerModule);
    $ownerType = sr_embed_manager_clean_identifier($ownerType);
    $ownerField = sr_embed_manager_clean_identifier($ownerField) ?: 'body';
    $refs = sr_embed_manager_owner_refs_by_key($pdo, $ownerModule, $ownerType, $ownerId, $ownerField);
    if ($refs === []) {
        return $bodyHtml;
    }

    $context = array_merge($context, [
        'owner_module' => $ownerModule,
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'owner_field' => $ownerField,
    ]);
    if ((int) ($context['viewer_account_id'] ?? 0) < 1 && function_exists('sr_member_current_account')) {
        $viewerAccount = sr_member_current_account($pdo);
        if (is_array($viewerAccount)) {
            $context['viewer_account_id'] = (int) ($viewerAccount['id'] ?? 0);
        }
    }

    $renderMarker = function (string $markerHtml) use ($pdo, $refs, $context): string {
        $attributes = sr_embed_manager_marker_attributes($markerHtml);
        $refKey = sr_embed_manager_clean_ref_key((string) ($attributes['data-sr-embed-manager-ref'] ?? ''));
        $ref = $refs[$refKey] ?? null;
        if (!is_array($ref) || (string) ($ref['status'] ?? '') === 'removed') {
            return '';
        }

        try {
            $target = sr_embed_manager_resolve_target($pdo, (string) ($ref['target_module'] ?? ''), (string) ($ref['target_type'] ?? ''), (string) ($ref['target_id'] ?? ''), $context);
            if (!is_array($target)) {
                $target = ['label_snapshot' => (string) ($ref['label_snapshot'] ?? ''), 'status' => 'broken'];
            }

            return sr_embed_manager_render_target_card($pdo, $ref, $target, $context);
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'embed_manager_render_failed');
            return '';
        }
    };

    $bodyHtml = preg_replace_callback('/<blockquote\b[^>]*>.*?' . sr_embed_manager_marker_pattern_fragment() . '.*?<\/blockquote>/isu', static function (array $matches) use ($renderMarker): string {
        if (preg_match(sr_embed_manager_marker_pattern(), (string) ($matches[0] ?? ''), $markerMatches) !== 1) {
            return '';
        }

        return $renderMarker((string) ($markerMatches[0] ?? ''));
    }, $bodyHtml) ?? $bodyHtml;

    return preg_replace_callback(sr_embed_manager_marker_pattern(), static function (array $matches) use ($renderMarker): string {
        return $renderMarker((string) ($matches[0] ?? ''));
    }, $bodyHtml) ?? $bodyHtml;
}

function sr_embed_manager_marker_pattern_fragment(): string
{
    return '<span\b(?=[^>]*\bsr-embed-manager-marker\b)(?=[^>]*\bdata-sr-embed-manager-ref=(["\'])([^"\']+)\1)[^>]*><\/span>';
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

function sr_embed_manager_upsert_ref(PDO $pdo, array $ref): void
{
    $driver = '';
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable $exception) {
        $driver = '';
    }

    $upsertClause = 'ON DUPLICATE KEY UPDATE
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
            updated_at = VALUES(updated_at)';
    if ($driver === 'sqlite') {
        $upsertClause = 'ON CONFLICT(ref_key) DO UPDATE SET
            owner_module = excluded.owner_module,
            owner_type = excluded.owner_type,
            owner_id = excluded.owner_id,
            owner_field = excluded.owner_field,
            target_module = excluded.target_module,
            target_type = excluded.target_type,
            target_id = excluded.target_id,
            variant = excluded.variant,
            label_snapshot = excluded.label_snapshot,
            image_snapshot = excluded.image_snapshot,
            sort_order = excluded.sort_order,
            status = excluded.status,
            updated_at = excluded.updated_at';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_embed_manager_refs
            (ref_key, owner_module, owner_type, owner_id, owner_field, target_module, target_type, target_id, variant, label_snapshot, image_snapshot, sort_order, status, created_by_account_id, created_at, updated_at)
         VALUES
            (:ref_key, :owner_module, :owner_type, :owner_id, :owner_field, :target_module, :target_type, :target_id, :variant, :label_snapshot, :image_snapshot, :sort_order, :status, :created_by_account_id, :created_at, :updated_at)
         ' . $upsertClause
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

        return preg_replace_callback('/\bdata-sr-embed-manager-ref=(["\'])[^"\']+\\1/iu', static function (array $attributeMatches) use ($map, $oldKey): string {
            $quote = (string) ($attributeMatches[1] ?? '"');
            return 'data-sr-embed-manager-ref=' . $quote . $map[$oldKey] . $quote;
        }, (string) ($matches[0] ?? '')) ?? (string) ($matches[0] ?? '');
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
