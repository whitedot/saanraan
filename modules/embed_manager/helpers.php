<?php

declare(strict_types=1);

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

function sr_embed_manager_allowed_statuses(): array
{
    return ['active', 'removed', 'broken', 'private', 'deleted'];
}

function sr_embed_manager_module_label(string $moduleKey): string
{
    $labels = [
        'content' => '콘텐츠',
        'community' => '커뮤니티',
        'quiz' => '퀴즈',
        'survey' => '설문',
    ];

    return $labels[$moduleKey] ?? $moduleKey;
}

function sr_embed_manager_target_type_label(string $targetModule, string $targetType): string
{
    $labels = [
        'content' => [
            'content' => '콘텐츠',
        ],
        'community' => [
            'post' => '게시글',
        ],
        'quiz' => [
            'quiz_set' => '퀴즈',
        ],
        'survey' => [
            'survey_form' => '설문',
        ],
    ];

    return (string) ($labels[$targetModule][$targetType] ?? $targetType);
}

function sr_embed_manager_target_label(string $moduleKey, string $targetType, string $targetId): string
{
    $label = sr_embed_manager_module_label($moduleKey);
    $typeLabel = sr_embed_manager_target_type_label($moduleKey, $targetType);
    if ($typeLabel !== '' && $typeLabel !== $label) {
        $label .= ' / ' . $typeLabel;
    }
    if ($targetId !== '') {
        $label .= ' #' . $targetId;
    }

    return $label;
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
        if (!sr_embed_manager_module_enabled($pdo, (string) $moduleKey)) {
            continue;
        }
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

function sr_embed_manager_absolute_public_url(PDO $pdo, string $url): string
{
    $url = sr_embed_manager_safe_url($url);
    if ($url === '' || sr_is_http_url($url)) {
        return $url;
    }

    $baseUrl = '';
    if (function_exists('sr_site_setting')) {
        $baseUrl = (string) sr_site_setting($pdo, 'site.base_url', '');
    }
    $site = ['base_url' => $baseUrl];
    if ($baseUrl === '' && function_exists('sr_current_base_url')) {
        $site['base_url'] = sr_current_base_url();
    }

    return function_exists('sr_absolute_url') ? sr_absolute_url($site, $url) : $url;
}

function sr_embed_manager_normalize_target_result(PDO $pdo, array $row, string $targetModule, string $targetType, array $definition): ?array
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

    $publicUrl = sr_embed_manager_absolute_public_url($pdo, (string) ($row['public_url'] ?? $row['url'] ?? ''));
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
                $item = sr_embed_manager_normalize_target_result($pdo, $row, (string) $targetModule, (string) $targetType, $definition);
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

function sr_embed_manager_default_settings(): array
{
    return [
        'url_embed_enabled' => false,
        'internal_url_embed_enabled' => true,
        'external_url_embed_enabled' => false,
        'embed_scope' => 'standalone_url_only',
    ];
}

function sr_embed_manager_settings(PDO $pdo): array
{
    try {
        return array_merge(sr_embed_manager_default_settings(), sr_module_settings($pdo, 'embed_manager'));
    } catch (Throwable $exception) {
        return sr_embed_manager_default_settings();
    }
}

function sr_embed_manager_url_embedding_enabled(PDO $pdo): bool
{
    $settings = sr_embed_manager_settings($pdo);
    return !empty($settings['url_embed_enabled']);
}

function sr_embed_manager_module_enabled(PDO $pdo, string $moduleKey): bool
{
    $moduleKey = sr_embed_manager_clean_identifier($moduleKey);
    if ($moduleKey === '' || $moduleKey === 'embed_manager') {
        return true;
    }

    try {
        $settings = sr_module_settings($pdo, $moduleKey);
    } catch (Throwable $exception) {
        return true;
    }
    if (array_key_exists('embed_enabled', $settings)) {
        return !empty($settings['embed_enabled']);
    }
    if (function_exists('sr_module_metadata')) {
        $metadata = sr_module_metadata($moduleKey);
        $defaultSettings = is_array($metadata['settings'] ?? null) ? $metadata['settings'] : [];
        if (array_key_exists('embed_enabled', $defaultSettings)) {
            return !empty($defaultSettings['embed_enabled']);
        }
    }

    return true;
}

function sr_embed_manager_embed_kind_allowed(string $embedKind, array $settings): bool
{
    if ($embedKind === 'internal_url') {
        return !empty($settings['internal_url_embed_enabled']);
    }
    if ($embedKind === 'external_url') {
        return !empty($settings['external_url_embed_enabled']);
    }

    return false;
}

function sr_embed_manager_url_cache_statuses(): array
{
    return ['fresh', 'stale', 'deleted', 'broken'];
}

function sr_embed_manager_table_exists(PDO $pdo): bool
{
    return sr_embed_manager_url_cache_table_exists($pdo);
}

function sr_embed_manager_url_cache_table_exists(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM sr_embed_manager_url_cache LIMIT 1');
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}

function sr_embed_manager_clean_cache_status(string $value): string
{
    $value = trim($value);
    return in_array($value, sr_embed_manager_url_cache_statuses(), true) ? $value : 'broken';
}

function sr_embed_manager_url_normalized_contract(array $definition): array
{
    $targetModule = sr_embed_manager_clean_identifier((string) ($definition['target_module'] ?? ''));
    $targetType = sr_embed_manager_clean_identifier((string) ($definition['target_type'] ?? ''));
    if ($targetModule === '' || $targetType === '') {
        return [];
    }
    if (!is_callable($definition['resolve_url'] ?? null) || !is_callable($definition['render_embed'] ?? null)) {
        return [];
    }

    $variants = [];
    foreach ((array) ($definition['allowed_variants'] ?? ['summary']) as $variant) {
        $variant = sr_embed_manager_clean_identifier((string) $variant);
        if ($variant !== '') {
            $variants[$variant] = true;
        }
    }
    if ($variants === []) {
        $variants['summary'] = true;
    }

    $definition['target_module'] = $targetModule;
    $definition['target_type'] = $targetType;
    $definition['allowed_variants'] = array_keys($variants);
    $definition['default_variant'] = in_array((string) ($definition['default_variant'] ?? 'summary'), $definition['allowed_variants'], true)
        ? (string) ($definition['default_variant'] ?? 'summary')
        : $definition['allowed_variants'][0];

    return $definition;
}

function sr_embed_manager_url_contract_targets(PDO $pdo): array
{
    $targets = [];
    foreach (sr_enabled_module_contract_files($pdo, 'embed-manager-url-targets.php', ['embed_manager']) as $moduleKey => $file) {
        if (!sr_embed_manager_module_enabled($pdo, (string) $moduleKey)) {
            continue;
        }
        $contract = sr_load_module_contract_file($moduleKey, $file);
        if (!is_array($contract)) {
            continue;
        }
        foreach ((array) ($contract['targets'] ?? []) as $definition) {
            if (!is_array($definition)) {
                continue;
            }
            $definition = sr_embed_manager_url_normalized_contract($definition);
            if ($definition !== []) {
                $targets[(string) $definition['target_module']][(string) $definition['target_type']] = $definition;
            }
        }
    }

    return $targets;
}

function sr_embed_manager_request_base_parts(PDO $pdo): array
{
    $baseUrl = '';
    if (function_exists('sr_site_setting')) {
        $baseUrl = (string) sr_site_setting($pdo, 'site.base_url', '');
    }
    if ($baseUrl === '' && function_exists('sr_current_base_url')) {
        $baseUrl = sr_current_base_url();
    }
    $parts = $baseUrl !== '' ? parse_url($baseUrl) : [];
    return is_array($parts) ? $parts : [];
}

function sr_embed_manager_normalize_source_url(PDO $pdo, string $url): array
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $url = rtrim($url, " \t\r\n.,;:");
    if ($url === '') {
        return [];
    }
    if (sr_is_safe_relative_url($url)) {
        return [
            'source_url' => $url,
            'canonical_probe_url' => $url,
            'embed_kind' => 'internal_url',
        ];
    }
    if (!sr_is_http_url($url)) {
        return [];
    }
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return [];
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return [];
    }

    $base = sr_embed_manager_request_base_parts($pdo);
    $host = strtolower((string) ($parts['host'] ?? ''));
    $baseHost = strtolower((string) ($base['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '/');
    $query = (string) ($parts['query'] ?? '');
    $relative = $path . ($query !== '' ? '?' . $query : '');

    return [
        'source_url' => $url,
        'canonical_probe_url' => $baseHost !== '' && $host === $baseHost ? $relative : $url,
        'embed_kind' => $baseHost !== '' && $host === $baseHost ? 'internal_url' : 'external_url',
    ];
}

function sr_embed_manager_clean_resolved_url(array $resolved, array $definition): array
{
    $targetId = sr_embed_manager_clean_target_id((string) ($resolved['target_id'] ?? ''));
    if ($targetId === '') {
        return [];
    }
    $canonicalUrl = sr_embed_manager_safe_url((string) ($resolved['canonical_url'] ?? $resolved['public_url'] ?? ''));
    if ($canonicalUrl === '') {
        return [];
    }
    $variant = sr_embed_manager_clean_identifier((string) ($resolved['variant'] ?? $definition['default_variant'] ?? 'summary'));
    if (!in_array($variant, (array) ($definition['allowed_variants'] ?? ['summary']), true)) {
        $variant = (string) ($definition['default_variant'] ?? 'summary');
    }
    $cacheStatus = sr_embed_manager_clean_cache_status((string) ($resolved['cache_status'] ?? 'fresh'));
    $targetState = sr_embed_manager_clean_identifier((string) ($resolved['target_state'] ?? $resolved['status'] ?? 'public'));
    $resolverState = sr_embed_manager_clean_identifier((string) ($resolved['resolver_state'] ?? 'resolved'));
    $imagePolicy = in_array((string) ($resolved['image_snapshot_policy'] ?? 'none'), ['none', 'opaque_key', 'public_url_ok'], true)
        ? (string) ($resolved['image_snapshot_policy'] ?? 'none')
        : 'none';

    return [
        'source_url' => (string) ($resolved['source_url'] ?? ''),
        'canonical_url' => $canonicalUrl,
        'canonical_url_hash' => hash('sha256', $canonicalUrl),
        'embed_kind' => (string) ($resolved['embed_kind'] ?? 'internal_url'),
        'provider_key' => sr_embed_manager_clean_identifier((string) ($resolved['provider_key'] ?? $definition['target_module'] ?? '')),
        'render_variant' => $variant,
        'target_module' => (string) ($definition['target_module'] ?? ''),
        'target_type' => (string) ($definition['target_type'] ?? ''),
        'target_id' => $targetId,
        'target_cache_version' => sr_embed_manager_clean_label((string) ($resolved['target_cache_version'] ?? $resolved['updated_at'] ?? '')),
        'label_snapshot' => sr_embed_manager_clean_label((string) ($resolved['label_snapshot'] ?? $resolved['title'] ?? '')),
        'summary_snapshot' => sr_embed_manager_clean_summary((string) ($resolved['summary_snapshot'] ?? $resolved['summary'] ?? '')),
        'image_snapshot' => $imagePolicy === 'public_url_ok' ? sr_embed_manager_safe_url((string) ($resolved['image_snapshot'] ?? '')) : '',
        'image_snapshot_policy' => $imagePolicy,
        'target_state' => $targetState,
        'resolver_state' => $resolverState,
        'cache_status' => $cacheStatus,
        'resolved_payload_json' => json_encode($resolved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
    ];
}

function sr_embed_manager_resolve_url(PDO $pdo, string $url, array $context = []): ?array
{
    $settings = sr_embed_manager_settings($pdo);
    $normalized = sr_embed_manager_normalize_source_url($pdo, $url);
    if ($normalized === []) {
        return null;
    }
    if (!sr_embed_manager_embed_kind_allowed((string) $normalized['embed_kind'], $settings)) {
        return null;
    }

    foreach (sr_embed_manager_url_contract_targets($pdo) as $types) {
        foreach ($types as $definition) {
            try {
                $resolved = $definition['resolve_url']($pdo, array_merge($context, $normalized, [
                    'url' => $normalized['canonical_probe_url'],
                ]));
            } catch (Throwable $exception) {
                sr_log_exception($exception, 'embed_manager_url_resolve_failed_' . (string) ($definition['target_module'] ?? '') . '_' . (string) ($definition['target_type'] ?? ''));
                continue;
            }
            if (!is_array($resolved)) {
                continue;
            }
            $resolved = array_merge($resolved, [
                'source_url' => (string) $normalized['source_url'],
                'embed_kind' => (string) $normalized['embed_kind'],
            ]);
            $clean = sr_embed_manager_clean_resolved_url($resolved, $definition);
            return $clean !== [] ? $clean : null;
        }
    }

    return null;
}

function sr_embed_manager_url_from_standalone_text(string $text): string
{
    $text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $text = preg_replace('/\s+/', ' ', $text) ?? '';
    if ($text === '') {
        return '';
    }
    if (preg_match('#\Ahttps?://[^\s<>"\']+\z#iu', $text) === 1 || preg_match('#\A/[^\s<>"\']+\z#u', $text) === 1) {
        return $text;
    }

    return '';
}

function sr_embed_manager_normalized_node_text(DOMNode $node): string
{
    return trim(preg_replace('/\s+/', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

function sr_embed_manager_node_is_only_meaningful_child(DOMNode $node): bool
{
    $parent = $node->parentNode;
    if (!$parent instanceof DOMNode) {
        return true;
    }
    foreach ($parent->childNodes as $sibling) {
        if ($sibling->isSameNode($node)) {
            continue;
        }
        if ($sibling instanceof DOMText && trim($sibling->textContent) === '') {
            continue;
        }

        return false;
    }

    return true;
}

function sr_embed_manager_standalone_replacement_node(DOMNode $node, DOMElement $root): DOMNode
{
    $candidate = $node;
    $candidateText = sr_embed_manager_normalized_node_text($node);
    while ($candidate->parentNode instanceof DOMElement && !$candidate->parentNode->isSameNode($root)) {
        $parent = $candidate->parentNode;
        if (sr_embed_manager_normalized_node_text($parent) !== $candidateText || !sr_embed_manager_node_is_only_meaningful_child($candidate)) {
            break;
        }
        $candidate = $parent;
    }

    return $candidate;
}

function sr_embed_manager_extract_candidate_urls(string $bodyHtml, string $scope = 'standalone_url_only'): array
{
    if ($bodyHtml === '') {
        return [];
    }

    $urls = [];
    $position = 0;
    if (!class_exists('DOMDocument')) {
        if ($scope === 'all_supported_links') {
            return sr_embed_manager_extract_legacy_candidate_urls($bodyHtml);
        }
        if (sr_embed_manager_url_from_standalone_text(strip_tags($bodyHtml)) !== '') {
            return [['url' => sr_embed_manager_url_from_standalone_text(strip_tags($bodyHtml)), 'position' => 0]];
        }

        return [];
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $bodyHtml . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded || !$dom->documentElement instanceof DOMElement) {
        return [];
    }
    $root = $dom->documentElement;

    foreach ($dom->getElementsByTagName('a') as $link) {
        if (!$link instanceof DOMElement) {
            continue;
        }
        $href = trim(html_entity_decode($link->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $label = sr_embed_manager_normalized_node_text($link);
        if ($href === '' || ($label !== '' && $label !== $href)) {
            continue;
        }
        if ($scope !== 'all_supported_links' && !sr_embed_manager_node_is_only_meaningful_child($link)) {
            continue;
        }
        $replaceNode = $scope === 'all_supported_links' ? $link : sr_embed_manager_standalone_replacement_node($link, $root);
        if ($scope === 'all_supported_links' || sr_embed_manager_normalized_node_text($replaceNode) === $label) {
            $urls[] = ['url' => $href, 'position' => $position++];
        }
    }

    $xpath = new DOMXPath($dom);
    $textNodes = $xpath->query('//text()[normalize-space()]');
    if ($textNodes instanceof DOMNodeList) {
        foreach ($textNodes as $textNode) {
            if (!$textNode instanceof DOMText || $textNode->parentNode instanceof DOMElement && strtolower($textNode->parentNode->tagName) === 'a') {
                continue;
            }
            $url = sr_embed_manager_url_from_standalone_text($textNode->textContent);
            if ($url === '') {
                continue;
            }
            $replaceNode = sr_embed_manager_standalone_replacement_node($textNode, $root);
            if (sr_embed_manager_normalized_node_text($replaceNode) === $url) {
                $urls[] = ['url' => $url, 'position' => $position++];
            }
        }
    }

    return $urls;
}

function sr_embed_manager_dom_renderable_nodes(DOMDocument $dom, DOMElement $root, string $scope): array
{
    $items = [];
    $seen = [];
    $position = 0;

    foreach ($dom->getElementsByTagName('a') as $link) {
        if (!$link instanceof DOMElement) {
            continue;
        }
        $href = trim(html_entity_decode($link->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $label = sr_embed_manager_normalized_node_text($link);
        if ($href === '' || ($label !== '' && $label !== $href)) {
            continue;
        }
        if ($scope !== 'all_supported_links' && !sr_embed_manager_node_is_only_meaningful_child($link)) {
            continue;
        }
        $replaceNode = $scope === 'all_supported_links' ? $link : sr_embed_manager_standalone_replacement_node($link, $root);
        if ($scope !== 'all_supported_links' && sr_embed_manager_normalized_node_text($replaceNode) !== $label) {
            continue;
        }
        $key = spl_object_id($replaceNode);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $items[] = ['node' => $replaceNode, 'url' => $href, 'position' => $position++];
    }

    $xpath = new DOMXPath($dom);
    $textNodes = $xpath->query('//text()[normalize-space()]');
    if ($textNodes instanceof DOMNodeList) {
        foreach ($textNodes as $textNode) {
            if (!$textNode instanceof DOMText || $textNode->parentNode instanceof DOMElement && strtolower($textNode->parentNode->tagName) === 'a') {
                continue;
            }
            $url = sr_embed_manager_url_from_standalone_text($textNode->textContent);
            if ($url === '') {
                continue;
            }
            $replaceNode = sr_embed_manager_standalone_replacement_node($textNode, $root);
            if (sr_embed_manager_normalized_node_text($replaceNode) !== $url) {
                continue;
            }
            $key = spl_object_id($replaceNode);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $items[] = ['node' => $replaceNode, 'url' => $url, 'position' => $position++];
        }
    }

    return $items;
}

function sr_embed_manager_extract_legacy_candidate_urls(string $bodyHtml): array
{
    $urls = [];
    $position = 0;
    if (preg_match_all('/<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\\1[^>]*>(.*?)<\/a>/isu', $bodyHtml, $matches, PREG_SET_ORDER) > 0) {
        foreach ($matches as $match) {
            $href = html_entity_decode((string) ($match[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $label = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($match[3] ?? ''))) ?? '');
            $bareHref = trim($href);
            if ($bareHref !== '' && ($label === '' || $label === $bareHref)) {
                $urls[] = ['url' => $bareHref, 'position' => $position++];
            }
        }
    }
    if (preg_match_all('#(?<![="\'])\bhttps?://[^\s<]+#iu', $bodyHtml, $matches, PREG_SET_ORDER) > 0) {
        foreach ($matches as $match) {
            $urls[] = ['url' => (string) ($match[0] ?? ''), 'position' => $position++];
        }
    }

    return $urls;
}

function sr_embed_manager_sync_body_url_cache(PDO $pdo, string $ownerModule, string $ownerType, int $ownerId, string $ownerField, string $bodyHtml, ?int $accountId = null): void
{
    $settings = sr_embed_manager_settings($pdo);
    if ($ownerId < 1 || empty($settings['url_embed_enabled']) || !sr_embed_manager_url_cache_table_exists($pdo)) {
        return;
    }

    $ownerModule = sr_embed_manager_clean_identifier($ownerModule);
    $ownerType = sr_embed_manager_clean_identifier($ownerType);
    $ownerField = sr_embed_manager_clean_identifier($ownerField) ?: 'body';
    if ($ownerModule === '' || $ownerType === '') {
        throw new InvalidArgumentException('URL 임베드 캐시 소유자 정보가 올바르지 않습니다.');
    }
    if (!sr_embed_manager_module_enabled($pdo, $ownerModule)) {
        sr_embed_manager_mark_missing_owner_urls_stale($pdo, $ownerModule, $ownerType, $ownerId, $ownerField, [], sr_now());
        return;
    }

    $now = sr_now();
    $activeHashes = [];
    foreach (sr_embed_manager_extract_candidate_urls($bodyHtml, (string) ($settings['embed_scope'] ?? 'standalone_url_only')) as $candidate) {
        $resolved = sr_embed_manager_resolve_url($pdo, (string) ($candidate['url'] ?? ''), [
            'owner_module' => $ownerModule,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'owner_field' => $ownerField,
        ]);
        if (!is_array($resolved)) {
            continue;
        }
        $resolved['owner_module'] = $ownerModule;
        $resolved['owner_type'] = $ownerType;
        $resolved['owner_id'] = $ownerId;
        $resolved['owner_field'] = $ownerField;
        $resolved['sort_order'] = (int) ($candidate['position'] ?? 0);
        $resolved['created_by_account_id'] = $accountId;
        $resolved['created_at'] = $now;
        $resolved['updated_at'] = $now;
        $resolved['last_resolved_at'] = $now;
        $resolved['last_render_checked_at'] = null;
        sr_embed_manager_upsert_url_cache($pdo, $resolved);
        $activeHashes[] = (string) $resolved['canonical_url_hash'];
    }

    sr_embed_manager_mark_missing_owner_urls_stale($pdo, $ownerModule, $ownerType, $ownerId, $ownerField, $activeHashes, $now);
}

function sr_embed_manager_upsert_url_cache(PDO $pdo, array $row): void
{
    $driver = '';
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable $exception) {
        $driver = '';
    }

    $upsertClause = 'ON DUPLICATE KEY UPDATE
            source_url = VALUES(source_url),
            canonical_url = VALUES(canonical_url),
            embed_kind = VALUES(embed_kind),
            provider_key = VALUES(provider_key),
            render_variant = VALUES(render_variant),
            target_module = VALUES(target_module),
            target_type = VALUES(target_type),
            target_id = VALUES(target_id),
            target_cache_version = VALUES(target_cache_version),
            label_snapshot = VALUES(label_snapshot),
            summary_snapshot = VALUES(summary_snapshot),
            image_snapshot = VALUES(image_snapshot),
            image_snapshot_policy = VALUES(image_snapshot_policy),
            target_state = VALUES(target_state),
            resolver_state = VALUES(resolver_state),
            cache_status = VALUES(cache_status),
            resolved_payload_json = VALUES(resolved_payload_json),
            sort_order = VALUES(sort_order),
            last_resolved_at = VALUES(last_resolved_at),
            updated_at = VALUES(updated_at)';
    if ($driver === 'sqlite') {
        $upsertClause = 'ON CONFLICT(owner_module, owner_type, owner_id, owner_field, canonical_url_hash) DO UPDATE SET
            source_url = excluded.source_url,
            canonical_url = excluded.canonical_url,
            embed_kind = excluded.embed_kind,
            provider_key = excluded.provider_key,
            render_variant = excluded.render_variant,
            target_module = excluded.target_module,
            target_type = excluded.target_type,
            target_id = excluded.target_id,
            target_cache_version = excluded.target_cache_version,
            label_snapshot = excluded.label_snapshot,
            summary_snapshot = excluded.summary_snapshot,
            image_snapshot = excluded.image_snapshot,
            image_snapshot_policy = excluded.image_snapshot_policy,
            target_state = excluded.target_state,
            resolver_state = excluded.resolver_state,
            cache_status = excluded.cache_status,
            resolved_payload_json = excluded.resolved_payload_json,
            sort_order = excluded.sort_order,
            last_resolved_at = excluded.last_resolved_at,
            updated_at = excluded.updated_at';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_embed_manager_url_cache
            (owner_module, owner_type, owner_id, owner_field, source_url, canonical_url, canonical_url_hash, embed_kind, provider_key, render_variant, target_module, target_type, target_id, target_cache_version, label_snapshot, summary_snapshot, image_snapshot, image_snapshot_policy, target_state, resolver_state, cache_status, resolved_payload_json, sort_order, last_resolved_at, last_render_checked_at, created_by_account_id, created_at, updated_at)
         VALUES
            (:owner_module, :owner_type, :owner_id, :owner_field, :source_url, :canonical_url, :canonical_url_hash, :embed_kind, :provider_key, :render_variant, :target_module, :target_type, :target_id, :target_cache_version, :label_snapshot, :summary_snapshot, :image_snapshot, :image_snapshot_policy, :target_state, :resolver_state, :cache_status, :resolved_payload_json, :sort_order, :last_resolved_at, :last_render_checked_at, :created_by_account_id, :created_at, :updated_at)
         ' . $upsertClause
    );
    $stmt->execute([
        'owner_module' => (string) ($row['owner_module'] ?? ''),
        'owner_type' => (string) ($row['owner_type'] ?? ''),
        'owner_id' => (int) ($row['owner_id'] ?? 0),
        'owner_field' => (string) ($row['owner_field'] ?? 'body'),
        'source_url' => (string) ($row['source_url'] ?? ''),
        'canonical_url' => (string) ($row['canonical_url'] ?? ''),
        'canonical_url_hash' => (string) ($row['canonical_url_hash'] ?? ''),
        'embed_kind' => (string) ($row['embed_kind'] ?? 'internal_url'),
        'provider_key' => (string) ($row['provider_key'] ?? ''),
        'render_variant' => (string) ($row['render_variant'] ?? 'summary'),
        'target_module' => (string) ($row['target_module'] ?? ''),
        'target_type' => (string) ($row['target_type'] ?? ''),
        'target_id' => (string) ($row['target_id'] ?? ''),
        'target_cache_version' => (string) ($row['target_cache_version'] ?? ''),
        'label_snapshot' => (string) ($row['label_snapshot'] ?? ''),
        'summary_snapshot' => (string) ($row['summary_snapshot'] ?? ''),
        'image_snapshot' => (string) ($row['image_snapshot'] ?? ''),
        'image_snapshot_policy' => (string) ($row['image_snapshot_policy'] ?? 'none'),
        'target_state' => (string) ($row['target_state'] ?? ''),
        'resolver_state' => (string) ($row['resolver_state'] ?? ''),
        'cache_status' => (string) ($row['cache_status'] ?? 'fresh'),
        'resolved_payload_json' => (string) ($row['resolved_payload_json'] ?? '{}'),
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'last_resolved_at' => $row['last_resolved_at'] ?? null,
        'last_render_checked_at' => $row['last_render_checked_at'] ?? null,
        'created_by_account_id' => $row['created_by_account_id'] ?? null,
        'created_at' => (string) ($row['created_at'] ?? sr_now()),
        'updated_at' => (string) ($row['updated_at'] ?? sr_now()),
    ]);
}

function sr_embed_manager_mark_missing_owner_urls_stale(PDO $pdo, string $ownerModule, string $ownerType, int $ownerId, string $ownerField, array $activeHashes, string $now): void
{
    $params = [
        'owner_module' => $ownerModule,
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'owner_field' => $ownerField,
        'updated_at' => $now,
    ];
    $sql = 'UPDATE sr_embed_manager_url_cache
            SET cache_status = \'stale\', updated_at = :updated_at
            WHERE owner_module = :owner_module
              AND owner_type = :owner_type
              AND owner_id = :owner_id
              AND owner_field = :owner_field';
    $placeholders = [];
    foreach (array_values(array_unique($activeHashes)) as $index => $hash) {
        if (!preg_match('/\A[a-f0-9]{64}\z/', (string) $hash)) {
            continue;
        }
        $key = 'hash_' . (string) $index;
        $params[$key] = (string) $hash;
        $placeholders[] = ':' . $key;
    }
    if ($placeholders !== []) {
        $sql .= ' AND canonical_url_hash NOT IN (' . implode(', ', $placeholders) . ')';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function sr_embed_manager_owner_url_cache_by_source(PDO $pdo, string $ownerModule, string $ownerType, int $ownerId, string $ownerField): array
{
    if (!sr_embed_manager_url_cache_table_exists($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_embed_manager_url_cache
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

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[(string) ($row['source_url'] ?? '')] = $row;
        $rows[(string) ($row['canonical_url'] ?? '')] = $row;
    }

    return $rows;
}

function sr_embed_manager_cached_row_for_url(array $cacheRows, string $url): ?array
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    if (isset($cacheRows[$url]) && is_array($cacheRows[$url])) {
        return $cacheRows[$url];
    }

    return null;
}

function sr_embed_manager_render_cache_key_for_url(array $cacheRows, string $url): string
{
    $row = sr_embed_manager_cached_row_for_url($cacheRows, $url);
    if (is_array($row) && preg_match('/\A[a-f0-9]{64}\z/', (string) ($row['canonical_url_hash'] ?? '')) === 1) {
        return 'canonical:' . (string) $row['canonical_url_hash'];
    }

    return 'source:' . hash('sha256', trim($url));
}

function sr_embed_manager_resolved_from_cache_row(array $row): array
{
    $targetId = sr_embed_manager_clean_target_id((string) ($row['target_id'] ?? ''));
    $canonicalUrl = sr_embed_manager_safe_url((string) ($row['canonical_url'] ?? ''));
    $targetModule = sr_embed_manager_clean_identifier((string) ($row['target_module'] ?? ''));
    $targetType = sr_embed_manager_clean_identifier((string) ($row['target_type'] ?? ''));
    if ($targetId === '' || $canonicalUrl === '' || $targetModule === '' || $targetType === '') {
        return [];
    }

    return [
        'source_url' => (string) ($row['source_url'] ?? ''),
        'canonical_url' => $canonicalUrl,
        'canonical_url_hash' => (string) ($row['canonical_url_hash'] ?? hash('sha256', $canonicalUrl)),
        'embed_kind' => (string) ($row['embed_kind'] ?? 'internal_url'),
        'provider_key' => (string) ($row['provider_key'] ?? $targetModule),
        'render_variant' => (string) ($row['render_variant'] ?? 'summary'),
        'target_module' => $targetModule,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'target_cache_version' => (string) ($row['target_cache_version'] ?? ''),
        'label_snapshot' => (string) ($row['label_snapshot'] ?? ''),
        'summary_snapshot' => (string) ($row['summary_snapshot'] ?? ''),
        'image_snapshot' => (string) ($row['image_snapshot'] ?? ''),
        'image_snapshot_policy' => (string) ($row['image_snapshot_policy'] ?? 'none'),
        'target_state' => (string) ($row['target_state'] ?? ''),
        'resolver_state' => (string) ($row['resolver_state'] ?? ''),
        'cache_status' => sr_embed_manager_clean_cache_status((string) ($row['cache_status'] ?? 'broken')),
        'resolved_payload_json' => (string) ($row['resolved_payload_json'] ?? '{}'),
    ];
}

function sr_embed_manager_cache_resolved_for_render(PDO $pdo, array $resolved, array $context): void
{
    if (!sr_embed_manager_url_cache_table_exists($pdo)) {
        return;
    }
    $ownerModule = sr_embed_manager_clean_identifier((string) ($context['owner_module'] ?? ''));
    $ownerType = sr_embed_manager_clean_identifier((string) ($context['owner_type'] ?? ''));
    $ownerId = (int) ($context['owner_id'] ?? 0);
    $ownerField = sr_embed_manager_clean_identifier((string) ($context['owner_field'] ?? '')) ?: 'body';
    if ($ownerModule === '' || $ownerType === '' || $ownerId < 1) {
        return;
    }

    $now = sr_now();
    $row = array_merge($resolved, [
        'owner_module' => $ownerModule,
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'owner_field' => $ownerField,
        'sort_order' => (int) ($context['sort_order'] ?? 0),
        'created_by_account_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
        'last_resolved_at' => $now,
        'last_render_checked_at' => $now,
    ]);
    sr_embed_manager_upsert_url_cache($pdo, $row);
}

function sr_embed_manager_admin_url_cache_rows(PDO $pdo, array $filters, int $limit = 100): array
{
    if (!sr_embed_manager_url_cache_table_exists($pdo)) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $where = [];
    $params = [];

    $statusValues = isset($filters['status']) && is_array($filters['status']) ? $filters['status'] : [];
    $status = $statusValues === [] ? '' : (string) $statusValues[0];
    if ($status !== '' && in_array($status, sr_embed_manager_url_cache_statuses(), true)) {
        $where[] = 'cache_status = :status';
        $params['status'] = $status;
    }

    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $keyword = function_exists('mb_substr') ? mb_substr($keyword, 0, 120) : substr($keyword, 0, 120);
        $where[] = '(source_url LIKE :keyword OR canonical_url LIKE :keyword OR owner_module LIKE :keyword OR target_module LIKE :keyword OR target_type LIKE :keyword OR target_id LIKE :keyword OR label_snapshot LIKE :keyword)';
        $params['keyword'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
    }

    $sql = 'SELECT *
            FROM sr_embed_manager_url_cache'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
        . ' ORDER BY updated_at DESC, id DESC
            LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_embed_manager_render_body_html(PDO $pdo, string $bodyHtml, string $ownerModule, string $ownerType, int $ownerId, string $ownerField = 'body', array $context = []): string
{
    $settings = sr_embed_manager_settings($pdo);
    if ($bodyHtml === '' || $ownerId < 1 || empty($settings['url_embed_enabled'])) {
        return $bodyHtml;
    }

    $ownerModule = sr_embed_manager_clean_identifier($ownerModule);
    $ownerType = sr_embed_manager_clean_identifier($ownerType);
    $ownerField = sr_embed_manager_clean_identifier($ownerField) ?: 'body';
    if ($ownerModule === '' || $ownerType === '') {
        return $bodyHtml;
    }
    if (!sr_embed_manager_module_enabled($pdo, $ownerModule)) {
        return $bodyHtml;
    }

    $context = array_merge($context, [
        'owner_module' => $ownerModule,
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'owner_field' => $ownerField,
        'embed_scope' => (string) ($settings['embed_scope'] ?? 'standalone_url_only'),
        'url_embed_settings' => $settings,
        'url_cache_by_source' => sr_embed_manager_owner_url_cache_by_source($pdo, $ownerModule, $ownerType, $ownerId, $ownerField),
    ]);
    if ((int) ($context['viewer_account_id'] ?? 0) < 1 && function_exists('sr_member_current_account')) {
        $viewerAccount = sr_member_current_account($pdo);
        if (is_array($viewerAccount)) {
            $context['viewer_account_id'] = (int) ($viewerAccount['id'] ?? 0);
        }
    }

    if (class_exists('DOMDocument')) {
        return sr_embed_manager_render_body_html_dom($pdo, $bodyHtml, $context);
    }

    return $bodyHtml;
}

function sr_embed_manager_render_body_html_dom(PDO $pdo, string $bodyHtml, array $context): string
{
    $wrapped = '<div data-sr-embed-manager-root="1">' . $bodyHtml . '</div>';
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return $bodyHtml;
    }

    $xpath = new DOMXPath($dom);
    $replace = [];
    $nodes = sr_embed_manager_dom_renderable_nodes($dom, $dom->documentElement, (string) ($context['embed_scope'] ?? 'standalone_url_only'));
    if ($nodes === []) {
        return $bodyHtml;
    }
    $renderedByUrl = [];
    foreach ($nodes as $item) {
        $node = $item['node'] ?? null;
        $url = (string) ($item['url'] ?? '');
        if (!$node instanceof DOMNode || $url === '') {
            continue;
        }
        $cacheRows = isset($context['url_cache_by_source']) && is_array($context['url_cache_by_source'])
            ? $context['url_cache_by_source']
            : [];
        $cacheKey = sr_embed_manager_render_cache_key_for_url($cacheRows, $url);
        if (!array_key_exists($cacheKey, $renderedByUrl)) {
            $renderContext = array_merge($context, ['sort_order' => (int) ($item['position'] ?? 0)]);
            $renderedByUrl[$cacheKey] = sr_embed_manager_render_url($pdo, $url, $renderContext);
        }
        $html = (string) $renderedByUrl[$cacheKey];
        if ($html !== '') {
            $replace[] = [$node, $html];
        }
    }
    foreach ($replace as $item) {
        [$node, $html] = $item;
        if (!$node instanceof DOMNode || $node->parentNode === null) {
            continue;
        }
        $fragment = sr_embed_manager_dom_fragment_from_html($dom, $html);
        if ($fragment instanceof DOMDocumentFragment) {
            $node->parentNode->replaceChild($fragment, $node);
        }
    }

    $root = $xpath->query('//*[@data-sr-embed-manager-root="1"]')->item(0);
    if (!$root instanceof DOMElement) {
        return $bodyHtml;
    }
    $html = '';
    foreach ($root->childNodes as $child) {
        $html .= $dom->saveHTML($child);
    }

    return $html !== '' ? $html : $bodyHtml;
}

function sr_embed_manager_dom_fragment_from_html(DOMDocument $targetDom, string $html): ?DOMDocumentFragment
{
    if ($html === '') {
        return null;
    }

    $previous = libxml_use_internal_errors(true);
    $sourceDom = new DOMDocument('1.0', 'UTF-8');
    $loaded = $sourceDom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded || !$sourceDom->documentElement instanceof DOMElement) {
        return null;
    }

    $fragment = $targetDom->createDocumentFragment();
    foreach ($sourceDom->documentElement->childNodes as $child) {
        $fragment->appendChild($targetDom->importNode($child, true));
    }

    return $fragment;
}

function sr_embed_manager_render_url(PDO $pdo, string $url, array $context): string
{
    $cacheRows = isset($context['url_cache_by_source']) && is_array($context['url_cache_by_source'])
        ? $context['url_cache_by_source']
        : [];
    $cachedRow = sr_embed_manager_cached_row_for_url($cacheRows, $url);
    $resolved = is_array($cachedRow) ? sr_embed_manager_resolved_from_cache_row($cachedRow) : [];
    if ($resolved === [] || (string) ($resolved['cache_status'] ?? '') !== 'fresh') {
        $resolved = sr_embed_manager_resolve_url($pdo, $url, $context);
        if (!is_array($resolved)) {
            return '';
        }
        sr_embed_manager_cache_resolved_for_render($pdo, $resolved, $context);
    }
    if ((string) ($resolved['cache_status'] ?? '') !== 'fresh') {
        return '';
    }
    $settings = isset($context['url_embed_settings']) && is_array($context['url_embed_settings'])
        ? $context['url_embed_settings']
        : sr_embed_manager_settings($pdo);
    if (!sr_embed_manager_embed_kind_allowed((string) ($resolved['embed_kind'] ?? ''), $settings)) {
        return '';
    }

    $definition = sr_embed_manager_url_contract_targets($pdo)[$resolved['target_module']][$resolved['target_type']] ?? null;
    if (!is_array($definition) || !is_callable($definition['render_embed'] ?? null)) {
        return '';
    }

    try {
        $rendered = $definition['render_embed']($pdo, $resolved, $context);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'embed_manager_url_render_failed_' . (string) ($resolved['target_module'] ?? '') . '_' . (string) ($resolved['target_type'] ?? ''));
        return '';
    }
    if (is_array($rendered)) {
        $renderCacheStatus = array_key_exists('cache_status', $rendered)
            ? sr_embed_manager_clean_cache_status((string) $rendered['cache_status'])
            : (string) ($resolved['cache_status'] ?? '');
        $renderCacheVersion = sr_embed_manager_clean_label((string) ($rendered['target_cache_version'] ?? ''));
        $cacheStatusChanged = $renderCacheStatus !== '' && $renderCacheStatus !== (string) ($resolved['cache_status'] ?? '');
        $cacheVersionChanged = $renderCacheVersion !== '' && $renderCacheVersion !== (string) ($resolved['target_cache_version'] ?? '');
        if ($cacheStatusChanged || $cacheVersionChanged) {
            $refreshed = sr_embed_manager_resolve_url($pdo, $url, $context);
            if (is_array($refreshed)) {
                sr_embed_manager_cache_resolved_for_render($pdo, $refreshed, $context);
                $resolved = $refreshed;
                if ((string) ($resolved['cache_status'] ?? '') === 'fresh') {
                    try {
                        $rendered = $definition['render_embed']($pdo, $resolved, $context);
                    } catch (Throwable $exception) {
                        sr_log_exception($exception, 'embed_manager_url_render_refresh_failed_' . (string) ($resolved['target_module'] ?? '') . '_' . (string) ($resolved['target_type'] ?? ''));
                        return '';
                    }
                }
            }
        }
    }
    if ((string) ($resolved['cache_status'] ?? '') !== 'fresh') {
        return '';
    }

    $html = is_array($rendered) ? (string) ($rendered['html'] ?? '') : (string) $rendered;
    if ($html === '') {
        return '';
    }

    return sr_embed_manager_sanitize_rendered_fragment($html);
}

function sr_embed_manager_sanitize_rendered_fragment(string $html): string
{
    if ($html === '' || !class_exists('DOMDocument')) {
        return '';
    }
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return '';
    }
    $allowedTags = ['div', 'aside', 'a', 'img', 'strong', 'p', 'span'];
    $allowedAttrs = ['class', 'href', 'src', 'alt', 'loading', 'decoding', 'data-content-embed', 'data-community-embed', 'data-quiz-embed', 'data-survey-embed'];
    $nodes = [];
    foreach ($dom->getElementsByTagName('*') as $node) {
        $nodes[] = $node;
    }
    foreach (array_reverse($nodes) as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }
        if (!in_array(strtolower($node->tagName), $allowedTags, true)) {
            $node->parentNode?->removeChild($node);
            continue;
        }
        foreach (iterator_to_array($node->attributes) as $attribute) {
            if (!$attribute instanceof DOMAttr || !in_array(strtolower($attribute->name), $allowedAttrs, true)) {
                $node->removeAttributeNode($attribute);
                continue;
            }
            if (in_array(strtolower($attribute->name), ['href', 'src'], true) && sr_embed_manager_safe_url($attribute->value) === '') {
                $node->removeAttributeNode($attribute);
            }
        }
    }
    $root = $dom->documentElement;
    if (!$root instanceof DOMElement) {
        return '';
    }
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }

    return $out;
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
