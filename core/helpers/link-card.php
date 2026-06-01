<?php

declare(strict_types=1);

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

function sr_link_card_normalized_refs(string $bodyText): array
{
    $refs = [];
    foreach (sr_link_card_extract_tokens($bodyText) as $token) {
        if (!sr_link_card_token_is_valid($token)) {
            continue;
        }

        $key = implode(':', [
            (string) $token['module'],
            (string) $token['entity_type'],
            (string) $token['entity_id'],
            (string) $token['slot'],
            (string) $token['variant'],
            (string) $token['label'],
        ]);
        if (isset($refs[$key])) {
            continue;
        }

        $refs[$key] = [
            'target_module' => (string) $token['module'],
            'target_entity_type' => (string) $token['entity_type'],
            'target_entity_id' => (string) $token['entity_id'],
            'slot_key' => (string) ($token['slot'] ?: 'body'),
            'variant' => (string) ($token['variant'] ?: 'compact'),
            'label' => (string) ($token['label'] ?? ''),
            'sort_order' => (int) ($token['position'] ?? 0),
        ];
    }

    return array_values($refs);
}

function sr_link_card_token_is_valid(array $token): bool
{
    $module = (string) ($token['module'] ?? '');
    $entityType = (string) ($token['entity_type'] ?? '');
    $entityId = (string) ($token['entity_id'] ?? '');

    if ($module === 'content') {
        return $entityType === 'content' && preg_match('/\A[1-9][0-9]*\z/', $entityId) === 1;
    }
    if ($module === 'community') {
        return $entityType === 'post' && preg_match('/\A[1-9][0-9]*\z/', $entityId) === 1;
    }
    if ($module === 'commerce') {
        return $entityType === 'product' && preg_match('/\A[1-9][0-9]*\z/', $entityId) === 1;
    }

    return false;
}

function sr_link_card_validate_tokens(PDO $pdo, string $bodyText, array $allowedTargets): array
{
    $errors = [];
    foreach (sr_link_card_extract_tokens($bodyText) as $token) {
        $label = (string) ($token['raw_token'] ?? 'link card');
        if (!sr_link_card_token_is_valid($token)) {
            $errors[] = '링크 카드 토큰 형식이 올바르지 않습니다: ' . $label;
            continue;
        }

        $targetKey = (string) $token['module'] . ':' . (string) $token['entity_type'];
        if (!in_array($targetKey, $allowedTargets, true)) {
            $errors[] = '허용되지 않은 링크 카드 대상입니다: ' . $targetKey;
            continue;
        }

        $resolved = sr_link_card_resolve_many($pdo, [[
            'target_module' => (string) $token['module'],
            'target_entity_type' => (string) $token['entity_type'],
            'target_entity_id' => (string) $token['entity_id'],
        ]]);
        $resolvedKey = sr_link_card_ref_key((string) $token['module'], (string) $token['entity_type'], (string) $token['entity_id']);
        if (!isset($resolved[$resolvedKey]) || !empty($resolved[$resolvedKey]['broken'])) {
            $errors[] = '연결할 수 없는 링크 카드 대상입니다: ' . $targetKey . '#' . (string) $token['entity_id'];
        }
    }

    return $errors;
}

function sr_link_card_ref_key(string $module, string $entityType, string $entityId): string
{
    return $module . ':' . $entityType . ':' . $entityId;
}

function sr_link_card_resolve_many(PDO $pdo, array $refs): array
{
    $grouped = [];
    foreach ($refs as $ref) {
        $module = (string) ($ref['target_module'] ?? '');
        $entityType = (string) ($ref['target_entity_type'] ?? '');
        $entityId = (string) ($ref['target_entity_id'] ?? '');
        if ($module === '' || $entityType === '' || $entityId === '') {
            continue;
        }
        $grouped[$module][$entityType][] = $entityId;
    }

    $resolved = [];
    foreach ($grouped as $module => $types) {
        if (preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', (string) $module) === 1
            && function_exists('sr_module_enabled')
            && sr_module_enabled($pdo, (string) $module)
            && is_file(SR_ROOT . '/modules/' . (string) $module . '/helpers.php')
        ) {
            require_once SR_ROOT . '/modules/' . (string) $module . '/helpers.php';
        }
        $function = 'sr_' . $module . '_link_card_resolve_many';
        if (!function_exists($function)) {
            foreach ($types as $entityType => $ids) {
                foreach ($ids as $entityId) {
                    $resolved[sr_link_card_ref_key((string) $module, (string) $entityType, (string) $entityId)] = sr_link_card_broken_result((string) $module, (string) $entityType, (string) $entityId);
                }
            }
            continue;
        }

        $moduleResolved = $function($pdo, $types);
        if (is_array($moduleResolved)) {
            $resolved = array_merge($resolved, $moduleResolved);
        }
    }

    return $resolved;
}

function sr_link_card_broken_result(string $module, string $entityType, string $entityId): array
{
    return [
        'module' => $module,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'title' => '연결할 수 없는 항목',
        'summary' => '',
        'url' => '',
        'status' => 'broken',
        'broken' => true,
    ];
}

function sr_link_card_render_body(PDO $pdo, string $bodyHtml): string
{
    if ($bodyHtml === '') {
        return '';
    }

    $tokens = sr_link_card_extract_tokens($bodyHtml);
    if ($tokens === []) {
        return $bodyHtml;
    }

    $refs = [];
    foreach ($tokens as $token) {
        if (!sr_link_card_token_is_valid($token)) {
            continue;
        }
        $refs[] = [
            'target_module' => (string) $token['module'],
            'target_entity_type' => (string) $token['entity_type'],
            'target_entity_id' => (string) $token['entity_id'],
        ];
    }
    $resolved = sr_link_card_resolve_many($pdo, $refs);

    $renderToken = static function (string $attributeText, string $rawToken) use ($resolved): string {
        $token = sr_link_card_parse_attributes($attributeText);
        if (!sr_link_card_token_is_valid($token)) {
            return sr_e($rawToken);
        }

        $key = sr_link_card_ref_key((string) $token['module'], (string) $token['entity_type'], (string) $token['entity_id']);
        $result = $resolved[$key] ?? sr_link_card_broken_result((string) $token['module'], (string) $token['entity_type'], (string) $token['entity_id']);

        return sr_link_card_render($result, $token);
    };

    $bodyHtml = preg_replace_callback('/<p(?:\s[^>]*)?>\s*(\{\{sr_link_card\s+([^{}]+)\}\})\s*<\/p>/u', static function (array $match) use ($renderToken): string {
        $token = sr_link_card_parse_attributes((string) ($match[2] ?? ''));
        if ((string) ($token['variant'] ?? 'compact') === 'inline') {
            return (string) ($match[0] ?? '');
        }

        return $renderToken((string) ($match[2] ?? ''), (string) ($match[1] ?? ''));
    }, $bodyHtml) ?? $bodyHtml;

    return preg_replace_callback(sr_link_card_token_pattern(), static function (array $match) use ($renderToken): string {
        return $renderToken((string) ($match[1] ?? ''), (string) ($match[0] ?? ''));
    }, $bodyHtml) ?? $bodyHtml;
}

function sr_link_card_render(array $resolved, array $token): string
{
    $variant = (string) ($token['variant'] ?? 'compact');
    if (!in_array($variant, ['compact', 'full', 'inline'], true)) {
        $variant = 'compact';
    }

    $title = (string) ($token['label'] ?? '') !== '' ? (string) $token['label'] : (string) ($resolved['title'] ?? '연결된 항목');
    $summary = trim((string) ($resolved['summary'] ?? ''));
    $module = (string) ($resolved['module'] ?? ($token['module'] ?? ''));
    $status = (string) ($resolved['status'] ?? '');
    $url = (string) ($resolved['url'] ?? '');
    $broken = !empty($resolved['broken']) || $url === '';

    if ($variant === 'inline') {
        $html = '<span class="sr-link-card sr-link-card-inline' . ($broken ? ' is-broken' : '') . '" data-link-card-module="' . sr_e($module) . '">';
        if ($broken) {
            $html .= '<strong class="sr-link-card-title">' . sr_e($title) . '</strong>';
        } else {
            $html .= '<a class="sr-link-card-title" href="' . sr_e(sr_url($url)) . '">' . sr_e($title) . '</a>';
        }
        $html .= '</span>';

        return $html;
    }

    $html = '<aside class="sr-link-card sr-link-card-' . sr_e($variant) . ($broken ? ' is-broken' : '') . '" data-link-card-module="' . sr_e($module) . '">';
    $html .= '<p class="sr-link-card-kicker">' . sr_e(sr_link_card_module_label($module)) . ($status !== '' ? ' · ' . sr_e($status) : '') . '</p>';
    if ($broken) {
        $html .= '<strong class="sr-link-card-title">' . sr_e($title) . '</strong>';
    } else {
        $html .= '<a class="sr-link-card-title" href="' . sr_e(sr_url($url)) . '">' . sr_e($title) . '</a>';
    }
    if ($summary !== '' && $variant !== 'inline') {
        $html .= '<p class="sr-link-card-summary">' . sr_e($summary) . '</p>';
    }
    $html .= '</aside>';

    return $html;
}

function sr_link_card_module_label(string $module): string
{
    if ($module === 'content') {
        return '콘텐츠';
    }
    if ($module === 'community') {
        return '커뮤니티';
    }
    if ($module === 'commerce') {
        return '커머스';
    }

    return '링크';
}

function sr_link_card_reconcile_table(PDO $pdo, string $table, string $subjectColumn, int $subjectId, array $refs, int $accountId): void
{
    if ($subjectId < 1 || !sr_link_card_table_exists($pdo, $table)) {
        return;
    }

    $delete = $pdo->prepare('DELETE FROM ' . $table . ' WHERE ' . $subjectColumn . ' = :subject_id');
    $delete->execute(['subject_id' => $subjectId]);

    if ($refs === []) {
        return;
    }

    $now = sr_now();
    $insert = $pdo->prepare(
        'INSERT INTO ' . $table . '
            (' . $subjectColumn . ', target_module, target_entity_type, target_entity_id, slot_key, variant, label, sort_order, created_by, created_at, updated_at)
         VALUES
            (:subject_id, :target_module, :target_entity_type, :target_entity_id, :slot_key, :variant, :label, :sort_order, :created_by, :created_at, :updated_at)'
    );
    foreach ($refs as $ref) {
        $insert->execute([
            'subject_id' => $subjectId,
            'target_module' => (string) ($ref['target_module'] ?? ''),
            'target_entity_type' => (string) ($ref['target_entity_type'] ?? ''),
            'target_entity_id' => (string) ($ref['target_entity_id'] ?? ''),
            'slot_key' => (string) ($ref['slot_key'] ?? 'body'),
            'variant' => (string) ($ref['variant'] ?? 'compact'),
            'label' => (string) ($ref['label'] ?? ''),
            'sort_order' => (int) ($ref['sort_order'] ?? 0),
            'created_by' => $accountId > 0 ? $accountId : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

function sr_link_card_table_exists(PDO $pdo, string $table): bool
{
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
