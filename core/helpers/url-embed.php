<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

function sr_url_embed_clean_identifier(string $value): string
{
    $value = trim($value);
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) === 1 ? $value : '';
}

function sr_url_embed_clean_target_id(string $value): string
{
    $value = trim($value);
    return preg_match('/\A[1-9][0-9]{0,19}\z/', $value) === 1 ? $value : '';
}

function sr_url_embed_clean_label(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, 255) : substr($value, 0, 255);
}

function sr_url_embed_clean_stylesheet_path(string $value): string
{
    $value = trim($value);
    if ($value === '' || !sr_is_safe_relative_url($value)) {
        return '';
    }

    return preg_match('#\Amodules/[a-z][a-z0-9_]{1,39}/assets/[a-z0-9_./-]+\.css\z#', ltrim($value, '/')) === 1
        ? $value
        : '';
}

function sr_url_embed_module_label(string $moduleKey): string
{
    $labels = [
        'content' => '콘텐츠',
        'community' => '커뮤니티',
        'quiz' => '퀴즈',
        'survey' => '설문',
    ];

    return $labels[$moduleKey] ?? $moduleKey;
}

function sr_url_embed_target_type_label(string $targetModule, string $targetType): string
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

function sr_url_embed_target_label(string $moduleKey, string $targetType, string $targetId): string
{
    $label = sr_url_embed_module_label($moduleKey);
    $typeLabel = sr_url_embed_target_type_label($moduleKey, $targetType);
    if ($typeLabel !== '' && $typeLabel !== $label) {
        $label .= ' / ' . $typeLabel;
    }
    if ($targetId !== '') {
        $label .= ' #' . $targetId;
    }

    return $label;
}

function sr_url_embed_clean_summary(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, 240) : substr($value, 0, 240);
}

function sr_url_embed_safe_url(string $value): string
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

function sr_url_embed_display_base_url(PDO $pdo): string
{
    $baseUrl = '';
    if (function_exists('sr_site_setting')) {
        try {
            $baseUrl = trim((string) sr_site_setting($pdo, 'site.base_url', ''));
        } catch (Throwable $exception) {
            $baseUrl = '';
        }
    }
    if ($baseUrl === '' && function_exists('sr_current_base_url')) {
        $baseUrl = trim((string) sr_current_base_url());
    }

    $baseUrl = rtrim($baseUrl, '/');
    if ($baseUrl !== '') {
        $validBaseUrl = function_exists('sr_is_site_base_url')
            ? sr_is_site_base_url($baseUrl)
            : (sr_is_http_url($baseUrl) && parse_url($baseUrl, PHP_URL_QUERY) === null && parse_url($baseUrl, PHP_URL_FRAGMENT) === null);
        if ($validBaseUrl) {
            return $baseUrl;
        }
    }

    return '';
}

function sr_url_embed_display_base_url_from_source(string $sourceUrl, string $canonicalUrl): string
{
    $sourceUrl = sr_url_embed_safe_url($sourceUrl);
    $canonicalUrl = sr_url_embed_safe_url($canonicalUrl);
    if ($sourceUrl === '' || $canonicalUrl === '' || !sr_is_http_url($sourceUrl) || !sr_is_safe_relative_url($canonicalUrl)) {
        return '';
    }

    $sourceParts = parse_url($sourceUrl);
    $canonicalParts = parse_url($canonicalUrl);
    if (!is_array($sourceParts) || !is_array($canonicalParts)) {
        return '';
    }

    $sourcePath = (string) ($sourceParts['path'] ?? '/');
    $canonicalPath = (string) ($canonicalParts['path'] ?? '/');
    if ($canonicalPath === '' || !str_ends_with($sourcePath, $canonicalPath)) {
        return '';
    }

    $prefixPath = substr($sourcePath, 0, strlen($sourcePath) - strlen($canonicalPath));
    $prefixPath = $prefixPath === '/' ? '' : rtrim($prefixPath, '/');
    $scheme = strtolower((string) ($sourceParts['scheme'] ?? ''));
    $host = (string) ($sourceParts['host'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        return '';
    }

    $port = isset($sourceParts['port']) ? ':' . (string) (int) $sourceParts['port'] : '';
    $baseUrl = $scheme . '://' . $host . $port . $prefixPath;
    $validBaseUrl = function_exists('sr_is_site_base_url')
        ? sr_is_site_base_url($baseUrl)
        : (sr_is_http_url($baseUrl) && parse_url($baseUrl, PHP_URL_QUERY) === null && parse_url($baseUrl, PHP_URL_FRAGMENT) === null);

    return $validBaseUrl ? rtrim($baseUrl, '/') : '';
}

function sr_url_embed_absolute_url(PDO $pdo, string $url, string $sourceUrl = ''): string
{
    $url = sr_url_embed_safe_url($url);
    if ($url === '') {
        return '';
    }
    if (sr_is_http_url($url)) {
        return $url;
    }
    if (!sr_is_safe_relative_url($url)) {
        return '';
    }

    $baseUrl = sr_url_embed_display_base_url($pdo);
    if ($baseUrl === '') {
        $baseUrl = sr_url_embed_display_base_url_from_source($sourceUrl, $url);
    }
    return $baseUrl !== '' ? $baseUrl . '/' . ltrim($url, '/') : '';
}

function sr_url_embed_default_settings(): array
{
    return [
        'url_embed_enabled' => true,
        'internal_url_embed_enabled' => true,
        'external_url_embed_enabled' => true,
        'embed_scope' => 'standalone_url_only',
    ];
}

function sr_url_embed_settings(PDO $pdo): array
{
    try {
        return array_merge(sr_url_embed_default_settings(), sr_module_settings($pdo, 'url_embed'));
    } catch (Throwable $exception) {
        return sr_url_embed_default_settings();
    }
}

function sr_url_embed_module_enabled(PDO $pdo, string $moduleKey): bool
{
    $moduleKey = sr_url_embed_clean_identifier($moduleKey);
    if ($moduleKey === '' || $moduleKey === 'url_embed') {
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

function sr_url_embed_embed_kind_allowed(string $embedKind, array $settings): bool
{
    if ($embedKind === 'internal_url') {
        return !empty($settings['internal_url_embed_enabled']);
    }
    if ($embedKind === 'external_url') {
        return !empty($settings['external_url_embed_enabled']);
    }

    return false;
}

function sr_url_embed_cache_statuses(): array
{
    return ['fresh', 'stale', 'deleted', 'broken'];
}

function sr_url_embed_cache_table_exists(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM sr_url_embed_cache LIMIT 1');
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}

function sr_url_embed_clean_cache_status(string $value): string
{
    $value = trim($value);
    return in_array($value, sr_url_embed_cache_statuses(), true) ? $value : 'broken';
}

function sr_url_embed_url_normalized_contract(array $definition): array
{
    $targetModule = sr_url_embed_clean_identifier((string) ($definition['target_module'] ?? ''));
    $targetType = sr_url_embed_clean_identifier((string) ($definition['target_type'] ?? ''));
    if ($targetModule === '' || $targetType === '') {
        return [];
    }
    if (!is_callable($definition['resolve_url'] ?? null) || !is_callable($definition['render_embed'] ?? null)) {
        return [];
    }

    $variants = [];
    foreach ((array) ($definition['allowed_variants'] ?? ['summary']) as $variant) {
        $variant = sr_url_embed_clean_identifier((string) $variant);
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
    $definition['fragment_cache_public'] = !empty($definition['fragment_cache_public']);
    $definition['fragment_cache_schema'] = sr_url_embed_clean_identifier((string) ($definition['fragment_cache_schema'] ?? 'v1')) ?: 'v1';
    $definition['embed_stylesheet'] = sr_url_embed_clean_stylesheet_path((string) ($definition['embed_stylesheet'] ?? ''));

    return $definition;
}

function sr_url_embed_url_contract_targets(PDO $pdo): array
{
    $targets = [];
    foreach (sr_enabled_module_contract_files($pdo, 'url-embed-targets.php', ['url_embed']) as $moduleKey => $file) {
        if (!sr_url_embed_module_enabled($pdo, (string) $moduleKey)) {
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
            $definition = sr_url_embed_url_normalized_contract($definition);
            if ($definition !== []) {
                $targets[(string) $definition['target_module']][(string) $definition['target_type']] = $definition;
            }
        }
    }

    return $targets;
}

function sr_url_embed_request_base_parts(PDO $pdo): array
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

function sr_url_embed_normalize_source_url(PDO $pdo, string $url): array
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

    $base = sr_url_embed_request_base_parts($pdo);
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

function sr_url_embed_clean_resolved_url(array $resolved, array $definition): array
{
    $targetId = sr_url_embed_clean_target_id((string) ($resolved['target_id'] ?? ''));
    if ($targetId === '') {
        return [];
    }
    $canonicalUrl = sr_url_embed_safe_url((string) ($resolved['canonical_url'] ?? $resolved['public_url'] ?? ''));
    if ($canonicalUrl === '') {
        return [];
    }
    $variant = sr_url_embed_clean_identifier((string) ($resolved['variant'] ?? $definition['default_variant'] ?? 'summary'));
    if (!in_array($variant, (array) ($definition['allowed_variants'] ?? ['summary']), true)) {
        $variant = (string) ($definition['default_variant'] ?? 'summary');
    }
    $cacheStatus = sr_url_embed_clean_cache_status((string) ($resolved['cache_status'] ?? 'fresh'));
    $targetState = sr_url_embed_clean_identifier((string) ($resolved['target_state'] ?? $resolved['status'] ?? 'public'));
    $resolverState = sr_url_embed_clean_identifier((string) ($resolved['resolver_state'] ?? 'resolved'));
    $imagePolicy = in_array((string) ($resolved['image_snapshot_policy'] ?? 'none'), ['none', 'opaque_key', 'public_url_ok'], true)
        ? (string) ($resolved['image_snapshot_policy'] ?? 'none')
        : 'none';

    return [
        'source_url' => (string) ($resolved['source_url'] ?? ''),
        'canonical_url' => $canonicalUrl,
        'canonical_url_hash' => hash('sha256', $canonicalUrl),
        'embed_kind' => (string) ($resolved['embed_kind'] ?? 'internal_url'),
        'provider_key' => sr_url_embed_clean_identifier((string) ($resolved['provider_key'] ?? $definition['target_module'] ?? '')),
        'render_variant' => $variant,
        'target_module' => (string) ($definition['target_module'] ?? ''),
        'target_type' => (string) ($definition['target_type'] ?? ''),
        'target_id' => $targetId,
        'target_cache_version' => sr_url_embed_clean_label((string) ($resolved['target_cache_version'] ?? $resolved['updated_at'] ?? '')),
        'label_snapshot' => sr_url_embed_clean_label((string) ($resolved['label_snapshot'] ?? $resolved['title'] ?? '')),
        'summary_snapshot' => sr_url_embed_clean_summary((string) ($resolved['summary_snapshot'] ?? $resolved['summary'] ?? '')),
        'image_snapshot' => $imagePolicy === 'public_url_ok' ? sr_url_embed_safe_url((string) ($resolved['image_snapshot'] ?? '')) : '',
        'image_snapshot_policy' => $imagePolicy,
        'target_state' => $targetState,
        'resolver_state' => $resolverState,
        'cache_status' => $cacheStatus,
        'resolved_payload_json' => json_encode($resolved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
    ];
}

function sr_url_embed_is_self_reference(array $resolved, array $context): bool
{
    $ownerModule = sr_url_embed_clean_identifier((string) ($context['owner_module'] ?? $resolved['owner_module'] ?? ''));
    $ownerType = sr_url_embed_clean_identifier((string) ($context['owner_type'] ?? $resolved['owner_type'] ?? ''));
    $ownerId = (int) ($context['owner_id'] ?? $resolved['owner_id'] ?? 0);
    $targetModule = sr_url_embed_clean_identifier((string) ($resolved['target_module'] ?? ''));
    $targetType = sr_url_embed_clean_identifier((string) ($resolved['target_type'] ?? ''));
    $targetId = sr_url_embed_clean_target_id((string) ($resolved['target_id'] ?? ''));

    return $ownerModule !== ''
        && $ownerType !== ''
        && $ownerId > 0
        && $ownerModule === $targetModule
        && $ownerType === $targetType
        && (string) $ownerId === $targetId;
}

function sr_url_embed_resolve_url(PDO $pdo, string $url, array $context = []): ?array
{
    $settings = sr_url_embed_settings($pdo);
    $normalized = sr_url_embed_normalize_source_url($pdo, $url);
    if ($normalized === []) {
        return null;
    }
    if (!sr_url_embed_embed_kind_allowed((string) $normalized['embed_kind'], $settings)) {
        return null;
    }

    if ((string) $normalized['embed_kind'] === 'external_url') {
        $external = sr_url_embed_resolve_external_url((string) $normalized['source_url']);
        if (is_array($external)) {
            return $external;
        }
    }

    foreach (sr_url_embed_url_contract_targets($pdo) as $types) {
        foreach ($types as $definition) {
            try {
                $resolved = $definition['resolve_url']($pdo, array_merge($context, $normalized, [
                    'url' => $normalized['canonical_probe_url'],
                ]));
            } catch (Throwable $exception) {
                sr_log_exception($exception, 'url_embed_url_resolve_failed_' . (string) ($definition['target_module'] ?? '') . '_' . (string) ($definition['target_type'] ?? ''));
                continue;
            }
            if (!is_array($resolved)) {
                continue;
            }
            $resolved = array_merge($resolved, [
                'source_url' => (string) $normalized['source_url'],
                'embed_kind' => (string) $normalized['embed_kind'],
            ]);
            $clean = sr_url_embed_clean_resolved_url($resolved, $definition);
            return $clean !== [] ? $clean : null;
        }
    }

    return null;
}

function sr_url_embed_resolve_external_url(string $url): ?array
{
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return null;
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    $path = (string) ($parts['path'] ?? '');
    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $query);

    $provider = '';
    $targetId = '';
    $canonicalUrl = '';
    if (in_array($host, ['youtube.com', 'm.youtube.com'], true)) {
        $videoId = preg_match('#\A/shorts/([A-Za-z0-9_-]{6,})#', $path, $matches) === 1
            ? (string) $matches[1]
            : (string) ($query['v'] ?? '');
        if (preg_match('/\A[A-Za-z0-9_-]{6,}\z/', $videoId) === 1) {
            $provider = 'youtube';
            $targetId = $videoId;
            $canonicalUrl = 'https://www.youtube.com/watch?v=' . rawurlencode($videoId);
        }
    } elseif ($host === 'youtu.be') {
        $videoId = ltrim($path, '/');
        if (preg_match('/\A[A-Za-z0-9_-]{6,}\z/', $videoId) === 1) {
            $provider = 'youtube';
            $targetId = $videoId;
            $canonicalUrl = 'https://www.youtube.com/watch?v=' . rawurlencode($videoId);
        }
    } elseif (in_array($host, ['x.com', 'twitter.com'], true) && preg_match('#\A/[^/]+/status/([0-9]+)#', $path, $matches) === 1) {
        $provider = 'x';
        $targetId = (string) $matches[1];
        $canonicalUrl = 'https://x.com' . $path;
    } elseif ($host === 'instagram.com' && preg_match('#\A/(p|reel|tv)/([A-Za-z0-9_-]+)#', $path, $matches) === 1) {
        $provider = 'instagram';
        $targetId = (string) $matches[2];
        $canonicalUrl = 'https://www.instagram.com/' . (string) $matches[1] . '/' . rawurlencode($targetId) . '/';
    }

    if ($provider === '' || $targetId === '' || $canonicalUrl === '') {
        return null;
    }

    return [
        'source_url' => $url,
        'canonical_url' => $canonicalUrl,
        'canonical_url_hash' => hash('sha256', $canonicalUrl),
        'embed_kind' => 'external_url',
        'provider_key' => $provider,
        'render_variant' => 'embed',
        'target_module' => 'external',
        'target_type' => $provider,
        'target_id' => $targetId,
        'target_cache_version' => '',
        'label_snapshot' => sr_url_embed_external_provider_label($provider),
        'summary_snapshot' => '',
        'image_snapshot' => '',
        'image_snapshot_policy' => 'none',
        'target_state' => 'public',
        'resolver_state' => 'resolved',
        'cache_status' => 'fresh',
        'resolved_payload_json' => '{}',
    ];
}

function sr_url_embed_external_provider_label(string $provider): string
{
    return [
        'youtube' => 'YouTube',
        'x' => 'X',
        'instagram' => 'Instagram',
    ][$provider] ?? 'External';
}

function sr_url_embed_render_external_url(array $resolved): string
{
    $provider = (string) ($resolved['provider_key'] ?? '');
    $targetId = (string) ($resolved['target_id'] ?? '');
    $canonicalUrl = sr_url_embed_safe_url((string) ($resolved['canonical_url'] ?? ''));
    if ($provider === '' || $targetId === '' || $canonicalUrl === '') {
        return '';
    }

    if ($provider === 'youtube') {
        $embedUrl = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($targetId);
        return '<sr-url-embed class="sr-url-embed sr-url-embed-youtube" data-sr-url-embed="youtube">'
            . '<iframe src="' . sr_e($embedUrl) . '" title="YouTube video" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"></iframe>'
            . '<span class="sr-url-embed-caption"><a href="' . sr_e($canonicalUrl) . '" target="_blank" rel="noopener noreferrer">YouTube에서 보기</a></span>'
            . '</sr-url-embed>';
    }

    $label = sr_url_embed_external_provider_label($provider);
    return '<sr-url-embed class="sr-url-embed sr-url-embed-' . sr_e($provider) . '" data-sr-url-embed="' . sr_e($provider) . '">'
        . '<strong>' . sr_e($label) . '</strong>'
        . '<a href="' . sr_e($canonicalUrl) . '" target="_blank" rel="noopener noreferrer">' . sr_e($canonicalUrl) . '</a>'
        . '</sr-url-embed>';
}

function sr_url_embed_url_from_standalone_text(string $text): string
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

function sr_url_embed_normalized_node_text(DOMNode $node): string
{
    return trim(preg_replace('/\s+/', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

function sr_url_embed_node_is_only_meaningful_child(DOMNode $node): bool
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

function sr_url_embed_standalone_replacement_node(DOMNode $node, DOMElement $root): DOMNode
{
    $candidate = $node;
    $candidateText = sr_url_embed_normalized_node_text($node);
    while ($candidate->parentNode instanceof DOMElement && !$candidate->parentNode->isSameNode($root)) {
        $parent = $candidate->parentNode;
        if (sr_url_embed_normalized_node_text($parent) !== $candidateText || !sr_url_embed_node_is_only_meaningful_child($candidate)) {
            break;
        }
        $candidate = $parent;
    }

    return $candidate;
}

function sr_url_embed_extract_candidate_urls(string $bodyHtml, string $scope = 'standalone_url_only'): array
{
    if ($bodyHtml === '') {
        return [];
    }

    $urls = [];
    $position = 0;
    if (!class_exists('DOMDocument')) {
        if ($scope === 'all_supported_links') {
            return sr_url_embed_extract_legacy_candidate_urls($bodyHtml);
        }
        if (sr_url_embed_url_from_standalone_text(strip_tags($bodyHtml)) !== '') {
            return [['url' => sr_url_embed_url_from_standalone_text(strip_tags($bodyHtml)), 'position' => 0]];
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
        $label = sr_url_embed_normalized_node_text($link);
        if ($href === '' || ($label !== '' && $label !== $href)) {
            continue;
        }
        if ($scope !== 'all_supported_links' && !sr_url_embed_node_is_only_meaningful_child($link)) {
            continue;
        }
        $replaceNode = $scope === 'all_supported_links' ? $link : sr_url_embed_standalone_replacement_node($link, $root);
        if ($scope === 'all_supported_links' || sr_url_embed_normalized_node_text($replaceNode) === $label) {
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
            $url = sr_url_embed_url_from_standalone_text($textNode->textContent);
            if ($url === '') {
                continue;
            }
            $replaceNode = sr_url_embed_standalone_replacement_node($textNode, $root);
            if (sr_url_embed_normalized_node_text($replaceNode) === $url) {
                $urls[] = ['url' => $url, 'position' => $position++];
            }
        }
    }

    return $urls;
}

function sr_url_embed_dom_renderable_nodes(DOMDocument $dom, DOMElement $root, string $scope): array
{
    $items = [];
    $seen = [];
    $position = 0;

    foreach ($dom->getElementsByTagName('a') as $link) {
        if (!$link instanceof DOMElement) {
            continue;
        }
        $href = trim(html_entity_decode($link->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $label = sr_url_embed_normalized_node_text($link);
        if ($href === '' || ($label !== '' && $label !== $href)) {
            continue;
        }
        if ($scope !== 'all_supported_links' && !sr_url_embed_node_is_only_meaningful_child($link)) {
            continue;
        }
        $replaceNode = $scope === 'all_supported_links' ? $link : sr_url_embed_standalone_replacement_node($link, $root);
        if ($scope !== 'all_supported_links' && sr_url_embed_normalized_node_text($replaceNode) !== $label) {
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
            $url = sr_url_embed_url_from_standalone_text($textNode->textContent);
            if ($url === '') {
                continue;
            }
            $replaceNode = sr_url_embed_standalone_replacement_node($textNode, $root);
            if (sr_url_embed_normalized_node_text($replaceNode) !== $url) {
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

function sr_url_embed_extract_legacy_candidate_urls(string $bodyHtml): array
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

function sr_url_embed_sync_body_url_cache(PDO $pdo, string $ownerModule, string $ownerType, int $ownerId, string $ownerField, string $bodyHtml, ?int $accountId = null): void
{
    $settings = sr_url_embed_settings($pdo);
    if ($ownerId < 1 || empty($settings['url_embed_enabled']) || !sr_url_embed_cache_table_exists($pdo)) {
        return;
    }

    $ownerModule = sr_url_embed_clean_identifier($ownerModule);
    $ownerType = sr_url_embed_clean_identifier($ownerType);
    $ownerField = sr_url_embed_clean_identifier($ownerField) ?: 'body';
    if ($ownerModule === '' || $ownerType === '') {
        throw new InvalidArgumentException('URL 임베드 캐시 소유자 정보가 올바르지 않습니다.');
    }
    if (!sr_url_embed_module_enabled($pdo, $ownerModule)) {
        sr_url_embed_mark_missing_owner_urls_stale($pdo, $ownerModule, $ownerType, $ownerId, $ownerField, [], sr_now());
        return;
    }

    $now = sr_now();
    $activeHashes = [];
    foreach (sr_url_embed_extract_candidate_urls($bodyHtml, (string) ($settings['embed_scope'] ?? 'standalone_url_only')) as $candidate) {
        $resolved = sr_url_embed_resolve_url($pdo, (string) ($candidate['url'] ?? ''), [
            'owner_module' => $ownerModule,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'owner_field' => $ownerField,
        ]);
        if (!is_array($resolved)) {
            continue;
        }
        if (sr_url_embed_is_self_reference($resolved, [
            'owner_module' => $ownerModule,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
        ])) {
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
        sr_url_embed_upsert_url_cache($pdo, $resolved);
        $activeHashes[] = (string) $resolved['canonical_url_hash'];
    }

    sr_url_embed_mark_missing_owner_urls_stale($pdo, $ownerModule, $ownerType, $ownerId, $ownerField, $activeHashes, $now);
}

function sr_url_embed_upsert_url_cache(PDO $pdo, array $row): void
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
        'INSERT INTO sr_url_embed_cache
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

function sr_url_embed_mark_missing_owner_urls_stale(PDO $pdo, string $ownerModule, string $ownerType, int $ownerId, string $ownerField, array $activeHashes, string $now): void
{
    $params = [
        'owner_module' => $ownerModule,
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'owner_field' => $ownerField,
        'updated_at' => $now,
    ];
    $sql = 'UPDATE sr_url_embed_cache
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

function sr_url_embed_owner_url_cache_by_source(PDO $pdo, string $ownerModule, string $ownerType, int $ownerId, string $ownerField): array
{
    if (!sr_url_embed_cache_table_exists($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_url_embed_cache
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

function sr_url_embed_stylesheet_for_resolved(PDO $pdo, array $resolved): string
{
    if ((string) ($resolved['cache_status'] ?? '') !== 'fresh') {
        return '';
    }
    if ((string) ($resolved['embed_kind'] ?? '') === 'external_url') {
        return '/assets/url-embed.css';
    }
    if ((string) ($resolved['embed_kind'] ?? '') !== 'internal_url') {
        return '';
    }

    $targetModule = sr_url_embed_clean_identifier((string) ($resolved['target_module'] ?? ''));
    $targetType = sr_url_embed_clean_identifier((string) ($resolved['target_type'] ?? ''));
    if ($targetModule === '' || $targetType === '' || !sr_url_embed_module_enabled($pdo, $targetModule)) {
        return '';
    }

    $definition = sr_url_embed_url_contract_targets($pdo)[$targetModule][$targetType] ?? null;
    return is_array($definition) ? (string) ($definition['embed_stylesheet'] ?? '') : '';
}

function sr_url_embed_stylesheets_for_body(PDO $pdo, string $bodyHtml, string $ownerModule, string $ownerType, int $ownerId, string $ownerField = 'body', array $context = []): array
{
    $settings = sr_url_embed_settings($pdo);
    if ($bodyHtml === '' || $ownerId < 1 || empty($settings['url_embed_enabled'])) {
        return [];
    }

    $ownerModule = sr_url_embed_clean_identifier($ownerModule);
    $ownerType = sr_url_embed_clean_identifier($ownerType);
    $ownerField = sr_url_embed_clean_identifier($ownerField) ?: 'body';
    if ($ownerModule === '' || $ownerType === '' || !sr_url_embed_module_enabled($pdo, $ownerModule)) {
        return [];
    }

    $cacheRows = sr_url_embed_owner_url_cache_by_source($pdo, $ownerModule, $ownerType, $ownerId, $ownerField);
    $context = array_merge($context, [
        'owner_module' => $ownerModule,
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'owner_field' => $ownerField,
        'embed_scope' => (string) ($settings['embed_scope'] ?? 'standalone_url_only'),
        'url_embed_settings' => $settings,
        'url_cache_by_source' => $cacheRows,
    ]);

    $stylesheets = [];
    foreach (sr_url_embed_extract_candidate_urls($bodyHtml, (string) $context['embed_scope']) as $candidate) {
        $url = (string) ($candidate['url'] ?? '');
        if ($url === '') {
            continue;
        }
        $cachedRow = sr_url_embed_cached_row_for_url($cacheRows, $url);
        $resolved = is_array($cachedRow) ? sr_url_embed_resolved_from_cache_row($cachedRow) : [];
        if ($resolved === [] || (string) ($resolved['cache_status'] ?? '') !== 'fresh') {
            $resolved = sr_url_embed_resolve_url($pdo, $url, $context);
        }
        if (!is_array($resolved) || sr_url_embed_is_self_reference($resolved, $context)) {
            continue;
        }
        if (!sr_url_embed_embed_kind_allowed((string) ($resolved['embed_kind'] ?? ''), $settings)) {
            continue;
        }
        $stylesheet = sr_url_embed_stylesheet_for_resolved($pdo, $resolved);
        if ($stylesheet !== '') {
            $stylesheets[$stylesheet] = $stylesheet;
        }
    }

    return array_values($stylesheets);
}

function sr_url_embed_cached_row_for_url(array $cacheRows, string $url): ?array
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

function sr_url_embed_render_cache_key_for_url(array $cacheRows, string $url): string
{
    $row = sr_url_embed_cached_row_for_url($cacheRows, $url);
    if (is_array($row) && preg_match('/\A[a-f0-9]{64}\z/', (string) ($row['canonical_url_hash'] ?? '')) === 1) {
        return 'canonical:' . (string) $row['canonical_url_hash'];
    }

    return 'source:' . hash('sha256', trim($url));
}

function sr_url_embed_resolved_from_cache_row(array $row): array
{
    $embedKind = (string) ($row['embed_kind'] ?? 'internal_url');
    $rawTargetId = (string) ($row['target_id'] ?? '');
    $targetId = $embedKind === 'external_url'
        ? (preg_match('/\A[A-Za-z0-9_-]{1,120}\z/', $rawTargetId) === 1 ? $rawTargetId : '')
        : sr_url_embed_clean_target_id($rawTargetId);
    $canonicalUrl = sr_url_embed_safe_url((string) ($row['canonical_url'] ?? ''));
    $targetModule = sr_url_embed_clean_identifier((string) ($row['target_module'] ?? ''));
    $targetType = sr_url_embed_clean_identifier((string) ($row['target_type'] ?? ''));
    if ($targetId === '' || $canonicalUrl === '' || $targetModule === '' || $targetType === '') {
        return [];
    }

    return [
        'source_url' => (string) ($row['source_url'] ?? ''),
        'canonical_url' => $canonicalUrl,
        'canonical_url_hash' => (string) ($row['canonical_url_hash'] ?? hash('sha256', $canonicalUrl)),
        'embed_kind' => $embedKind,
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
        'cache_status' => sr_url_embed_clean_cache_status((string) ($row['cache_status'] ?? 'broken')),
        'resolved_payload_json' => (string) ($row['resolved_payload_json'] ?? '{}'),
    ];
}

function sr_url_embed_cache_resolved_for_render(PDO $pdo, array $resolved, array $context): void
{
    if (!sr_url_embed_cache_table_exists($pdo)) {
        return;
    }
    $ownerModule = sr_url_embed_clean_identifier((string) ($context['owner_module'] ?? ''));
    $ownerType = sr_url_embed_clean_identifier((string) ($context['owner_type'] ?? ''));
    $ownerId = (int) ($context['owner_id'] ?? 0);
    $ownerField = sr_url_embed_clean_identifier((string) ($context['owner_field'] ?? '')) ?: 'body';
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
    sr_url_embed_upsert_url_cache($pdo, $row);
}

function sr_url_embed_mark_target_url_cache_stale(PDO $pdo, string $targetModule, string $targetType, int|string $targetId): void
{
    if (!sr_url_embed_cache_table_exists($pdo)) {
        return;
    }

    $targetModule = sr_url_embed_clean_identifier($targetModule);
    $targetType = sr_url_embed_clean_identifier($targetType);
    $targetId = sr_url_embed_clean_target_id((string) $targetId);
    if ($targetModule === '' || $targetType === '' || $targetId === '') {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_url_embed_cache
         SET cache_status = \'stale\',
             updated_at = :updated_at
         WHERE target_module = :target_module
           AND target_type = :target_type
           AND target_id = :target_id
           AND cache_status = \'fresh\''
    );
    $stmt->execute([
        'updated_at' => sr_now(),
        'target_module' => $targetModule,
        'target_type' => $targetType,
        'target_id' => $targetId,
    ]);
}

function sr_url_embed_fragment_cache_root(): string
{
    if (defined('SR_URL_EMBED_FRAGMENT_CACHE_ROOT')) {
        return rtrim((string) SR_URL_EMBED_FRAGMENT_CACHE_ROOT, '/\\');
    }
    $root = defined('SR_ROOT') ? (string) SR_ROOT : dirname(__DIR__, 2);
    return rtrim($root, '/\\') . '/storage/cache/embeds';
}

function sr_url_embed_fragment_cache_public_allowed(array $resolved, array $definition, array $context): bool
{
    if (empty($definition['fragment_cache_public'])) {
        return false;
    }
    if ((string) ($resolved['cache_status'] ?? '') !== 'fresh' || (string) ($resolved['target_state'] ?? '') !== 'public') {
        return false;
    }
    if ((string) ($resolved['embed_kind'] ?? '') !== 'internal_url') {
        return false;
    }
    if ((string) ($resolved['target_cache_version'] ?? '') === '') {
        return false;
    }
    if (!empty($context['disable_fragment_cache'])) {
        return false;
    }

    return true;
}

function sr_url_embed_fragment_cache_key(array $resolved, array $definition, array $context): string
{
    $locale = sr_url_embed_clean_identifier((string) ($context['locale'] ?? 'ko')) ?: 'ko';
    $payload = [
        'schema' => 'embed_fragment_' . (string) ($definition['fragment_cache_schema'] ?? 'v1'),
        'target_module' => (string) ($resolved['target_module'] ?? ''),
        'target_type' => (string) ($resolved['target_type'] ?? ''),
        'target_id' => (string) ($resolved['target_id'] ?? ''),
        'canonical_url_hash' => (string) ($resolved['canonical_url_hash'] ?? ''),
        'render_variant' => (string) ($resolved['render_variant'] ?? 'summary'),
        'target_cache_version' => (string) ($resolved['target_cache_version'] ?? ''),
        'display_base_url' => (string) ($context['display_base_url'] ?? ''),
        'source_base_url' => sr_url_embed_display_base_url_from_source((string) ($resolved['source_url'] ?? ''), (string) ($resolved['canonical_url'] ?? '')),
        'locale' => $locale,
    ];

    return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: serialize($payload));
}

function sr_url_embed_fragment_cache_path(array $resolved, array $definition, array $context): string
{
    $targetModule = sr_url_embed_clean_identifier((string) ($resolved['target_module'] ?? 'embed')) ?: 'embed';
    $hash = sr_url_embed_fragment_cache_key($resolved, $definition, $context);

    return sr_url_embed_fragment_cache_root() . '/' . $targetModule . '/' . substr($hash, 0, 2) . '/' . $hash . '.html';
}

function sr_url_embed_fragment_cache_read(array $resolved, array $definition, array $context): string
{
    if (!sr_url_embed_fragment_cache_public_allowed($resolved, $definition, $context)) {
        return '';
    }

    $path = sr_url_embed_fragment_cache_path($resolved, $definition, $context);
    if (!is_file($path) || filesize($path) > 262144) {
        return '';
    }

    $html = file_get_contents($path);
    return is_string($html) ? $html : '';
}

function sr_url_embed_fragment_cache_write(array $resolved, array $definition, array $context, string $html): void
{
    if ($html === '' || !sr_url_embed_fragment_cache_public_allowed($resolved, $definition, $context)) {
        return;
    }

    $path = sr_url_embed_fragment_cache_path($resolved, $definition, $context);
    sr_write_file_atomically($path, $html);
}

function sr_url_embed_fragment_cache_admin_date_filter(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) === 1 ? $value : '';
}

function sr_url_embed_fragment_cache_admin_module_key(string $value): string
{
    return sr_url_embed_clean_identifier($value);
}

function sr_url_embed_fragment_cache_admin_filters_from_request(string $moduleKey): array
{
    return [
        'module_key' => sr_url_embed_fragment_cache_admin_module_key($moduleKey),
        'date_from' => sr_url_embed_fragment_cache_admin_date_filter(sr_get_string('date_from', 20)),
        'date_to' => sr_url_embed_fragment_cache_admin_date_filter(sr_get_string('date_to', 20)),
    ];
}

function sr_url_embed_fragment_cache_admin_filter_start_timestamp(string $date): int
{
    if (sr_url_embed_fragment_cache_admin_date_filter($date) === '') {
        return 0;
    }

    $timestamp = strtotime($date . ' 00:00:00');
    return is_int($timestamp) ? $timestamp : 0;
}

function sr_url_embed_fragment_cache_admin_filter_end_timestamp(string $date): int
{
    if (sr_url_embed_fragment_cache_admin_date_filter($date) === '') {
        return 0;
    }

    $timestamp = strtotime($date . ' 23:59:59');
    return is_int($timestamp) ? $timestamp : 0;
}

function sr_url_embed_fragment_cache_admin_cleanup_limit(): int
{
    return 200;
}

function sr_url_embed_fragment_cache_admin_empty_scan(): array
{
    return [
        'rows' => [],
        'summary' => [
            'total_count' => 0,
            'total_bytes' => 0,
            'oldest_at' => '',
            'newest_at' => '',
            'date_counts' => [],
            'date_bytes' => [],
        ],
    ];
}

function sr_url_embed_fragment_cache_admin_relative_path(string $rootRealPath, string $path): string
{
    $realPath = realpath($path);
    if (!is_string($realPath)) {
        return '';
    }

    $prefix = rtrim($rootRealPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($realPath, $prefix)) {
        return '';
    }

    return str_replace(DIRECTORY_SEPARATOR, '/', substr($realPath, strlen($prefix)));
}

function sr_url_embed_fragment_cache_admin_parse_relative_path(string $relative): ?array
{
    if (preg_match('#\A([a-z][a-z0-9_]{1,59})/([a-f0-9]{2})/([a-f0-9]{64})\.html\z#', $relative, $matches) !== 1) {
        return null;
    }

    return [
        'module_key' => (string) $matches[1],
        'hash_prefix' => (string) $matches[2],
        'cache_hash' => (string) $matches[3],
    ];
}

function sr_url_embed_fragment_cache_admin_preview(string $path): string
{
    if (!is_file($path) || filesize($path) > 262144) {
        return '';
    }

    $html = file_get_contents($path);
    if (!is_string($html)) {
        return '';
    }
    $preview = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');

    return function_exists('mb_substr') ? mb_substr($preview, 0, 180) : substr($preview, 0, 180);
}

function sr_url_embed_fragment_cache_admin_scan(array $filters): array
{
    $moduleKey = sr_url_embed_fragment_cache_admin_module_key((string) ($filters['module_key'] ?? ''));
    $root = sr_url_embed_fragment_cache_root();
    if ($moduleKey === '' || !is_dir($root)) {
        return sr_url_embed_fragment_cache_admin_empty_scan();
    }

    $rootRealPath = realpath($root);
    if (!is_string($rootRealPath)) {
        return sr_url_embed_fragment_cache_admin_empty_scan();
    }

    $modulePath = $rootRealPath . DIRECTORY_SEPARATOR . $moduleKey;
    if (!is_dir($modulePath)) {
        return sr_url_embed_fragment_cache_admin_empty_scan();
    }

    $rows = [];
    $totalBytes = 0;
    $dateCounts = [];
    $dateBytes = [];
    $oldestAt = '';
    $newestAt = '';
    $fromTimestamp = sr_url_embed_fragment_cache_admin_filter_start_timestamp((string) ($filters['date_from'] ?? ''));
    $toTimestamp = sr_url_embed_fragment_cache_admin_filter_end_timestamp((string) ($filters['date_to'] ?? ''));
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modulePath, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
            continue;
        }

        $path = $fileInfo->getPathname();
        $relative = sr_url_embed_fragment_cache_admin_relative_path($rootRealPath, $path);
        $parsed = sr_url_embed_fragment_cache_admin_parse_relative_path($relative);
        if ($parsed === null || (string) ($parsed['module_key'] ?? '') !== $moduleKey) {
            continue;
        }

        $mtime = (int) $fileInfo->getMTime();
        if (($fromTimestamp > 0 && $mtime < $fromTimestamp) || ($toTimestamp > 0 && $mtime > $toTimestamp)) {
            continue;
        }

        $sizeBytes = max(0, (int) $fileInfo->getSize());
        $modifiedAt = date('Y-m-d H:i:s', $mtime);
        $dateKey = date('Y-m-d', $mtime);
        $rows[] = [
            'relative_path' => $relative,
            'module_key' => $moduleKey,
            'hash_prefix' => (string) $parsed['hash_prefix'],
            'cache_hash' => (string) $parsed['cache_hash'],
            'size_bytes' => $sizeBytes,
            'modified_at' => $modifiedAt,
            'preview' => sr_url_embed_fragment_cache_admin_preview($path),
        ];
        $totalBytes += $sizeBytes;
        $dateCounts[$dateKey] = (int) ($dateCounts[$dateKey] ?? 0) + 1;
        $dateBytes[$dateKey] = (int) ($dateBytes[$dateKey] ?? 0) + $sizeBytes;
        if ($oldestAt === '' || $modifiedAt < $oldestAt) {
            $oldestAt = $modifiedAt;
        }
        if ($newestAt === '' || $modifiedAt > $newestAt) {
            $newestAt = $modifiedAt;
        }
    }

    usort($rows, static function (array $left, array $right): int {
        return [(string) $right['modified_at'], (string) $right['relative_path']] <=> [(string) $left['modified_at'], (string) $left['relative_path']];
    });
    krsort($dateCounts);
    krsort($dateBytes);

    return [
        'rows' => $rows,
        'summary' => [
            'total_count' => count($rows),
            'total_bytes' => $totalBytes,
            'oldest_at' => $oldestAt,
            'newest_at' => $newestAt,
            'date_counts' => $dateCounts,
            'date_bytes' => $dateBytes,
        ],
    ];
}

function sr_url_embed_fragment_cache_admin_cleanup(array $filters, int $limit = 0): array
{
    $moduleKey = sr_url_embed_fragment_cache_admin_module_key((string) ($filters['module_key'] ?? ''));
    $limit = $limit > 0 ? $limit : sr_url_embed_fragment_cache_admin_cleanup_limit();
    $scan = sr_url_embed_fragment_cache_admin_scan($filters);
    $rows = isset($scan['rows']) && is_array($scan['rows']) ? $scan['rows'] : [];
    $rootRealPath = realpath(sr_url_embed_fragment_cache_root());
    $deletedCount = 0;
    $deletedBytes = 0;
    $errors = [];
    $limitReached = false;
    if ($moduleKey === '' || !is_string($rootRealPath)) {
        return [
            'deleted_count' => 0,
            'deleted_bytes' => 0,
            'errors' => [],
            'limit' => $limit,
            'limit_reached' => false,
        ];
    }

    foreach ($rows as $row) {
        if ($deletedCount >= $limit) {
            $limitReached = true;
            break;
        }
        $relative = (string) ($row['relative_path'] ?? '');
        $parsed = sr_url_embed_fragment_cache_admin_parse_relative_path($relative);
        if ($parsed === null || (string) ($parsed['module_key'] ?? '') !== $moduleKey) {
            continue;
        }
        $path = $rootRealPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $realPath = realpath($path);
        $prefix = rtrim($rootRealPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!is_string($realPath) || !str_starts_with($realPath, $prefix) || !is_file($realPath)) {
            continue;
        }
        $sizeBytes = max(0, (int) filesize($realPath));
        if (@unlink($realPath)) {
            $deletedCount++;
            $deletedBytes += $sizeBytes;
        } else {
            $errors[] = $relative;
        }
    }

    return [
        'deleted_count' => $deletedCount,
        'deleted_bytes' => $deletedBytes,
        'errors' => $errors,
        'limit' => $limit,
        'limit_reached' => $limitReached,
    ];
}

function sr_url_embed_admin_cache_summary(PDO $pdo): array
{
    $summary = [
        'table_exists' => false,
        'row_count' => 0,
        'fresh_count' => 0,
        'stale_count' => 0,
        'deleted_count' => 0,
        'broken_count' => 0,
        'latest_resolved_at' => '',
        'latest_render_checked_at' => '',
        'latest_updated_at' => '',
    ];
    if (!sr_url_embed_cache_table_exists($pdo)) {
        return $summary;
    }

    try {
        $stmt = $pdo->query(
            "SELECT
                COUNT(*) AS row_count,
                SUM(CASE WHEN cache_status = 'fresh' THEN 1 ELSE 0 END) AS fresh_count,
                SUM(CASE WHEN cache_status = 'stale' THEN 1 ELSE 0 END) AS stale_count,
                SUM(CASE WHEN cache_status = 'deleted' THEN 1 ELSE 0 END) AS deleted_count,
                SUM(CASE WHEN cache_status = 'broken' THEN 1 ELSE 0 END) AS broken_count,
                MAX(last_resolved_at) AS latest_resolved_at,
                MAX(last_render_checked_at) AS latest_render_checked_at,
                MAX(updated_at) AS latest_updated_at
             FROM sr_url_embed_cache"
        );
        $row = $stmt->fetch();
        $summary['table_exists'] = true;
        if (is_array($row)) {
            $summary['row_count'] = (int) ($row['row_count'] ?? 0);
            $summary['fresh_count'] = (int) ($row['fresh_count'] ?? 0);
            $summary['stale_count'] = (int) ($row['stale_count'] ?? 0);
            $summary['deleted_count'] = (int) ($row['deleted_count'] ?? 0);
            $summary['broken_count'] = (int) ($row['broken_count'] ?? 0);
            $summary['latest_resolved_at'] = (string) ($row['latest_resolved_at'] ?? '');
            $summary['latest_render_checked_at'] = (string) ($row['latest_render_checked_at'] ?? '');
            $summary['latest_updated_at'] = (string) ($row['latest_updated_at'] ?? '');
        }
    } catch (Throwable) {
        $summary['table_exists'] = false;
    }

    return $summary;
}

function sr_url_embed_render_body_html(PDO $pdo, string $bodyHtml, string $ownerModule, string $ownerType, int $ownerId, string $ownerField = 'body', array $context = []): string
{
    $settings = sr_url_embed_settings($pdo);
    if ($bodyHtml === '' || $ownerId < 1 || empty($settings['url_embed_enabled'])) {
        return $bodyHtml;
    }

    $ownerModule = sr_url_embed_clean_identifier($ownerModule);
    $ownerType = sr_url_embed_clean_identifier($ownerType);
    $ownerField = sr_url_embed_clean_identifier($ownerField) ?: 'body';
    if ($ownerModule === '' || $ownerType === '') {
        return $bodyHtml;
    }
    if (!sr_url_embed_module_enabled($pdo, $ownerModule)) {
        return $bodyHtml;
    }

    $context = array_merge($context, [
        'owner_module' => $ownerModule,
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'owner_field' => $ownerField,
        'embed_scope' => (string) ($settings['embed_scope'] ?? 'standalone_url_only'),
        'url_embed_settings' => $settings,
        'url_cache_by_source' => sr_url_embed_owner_url_cache_by_source($pdo, $ownerModule, $ownerType, $ownerId, $ownerField),
    ]);
    if ((int) ($context['viewer_account_id'] ?? 0) < 1 && function_exists('sr_member_current_account')) {
        $viewerAccount = sr_member_current_account($pdo);
        if (is_array($viewerAccount)) {
            $context['viewer_account_id'] = (int) ($viewerAccount['id'] ?? 0);
        }
    }

    if (class_exists('DOMDocument')) {
        return sr_url_embed_render_body_html_dom($pdo, $bodyHtml, $context);
    }

    return $bodyHtml;
}

function sr_url_embed_render_body_html_dom(PDO $pdo, string $bodyHtml, array $context): string
{
    $wrapped = '<div data-sr-url-embed-root="1">' . $bodyHtml . '</div>';
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
    $nodes = sr_url_embed_dom_renderable_nodes($dom, $dom->documentElement, (string) ($context['embed_scope'] ?? 'standalone_url_only'));
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
        $cacheKey = sr_url_embed_render_cache_key_for_url($cacheRows, $url);
        if (!array_key_exists($cacheKey, $renderedByUrl)) {
            $renderContext = array_merge($context, ['sort_order' => (int) ($item['position'] ?? 0)]);
            $renderedByUrl[$cacheKey] = sr_url_embed_render_url($pdo, $url, $renderContext);
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
        $fragment = sr_url_embed_dom_fragment_from_html($dom, $html);
        if ($fragment instanceof DOMDocumentFragment) {
            $node->parentNode->replaceChild($fragment, $node);
        }
    }

    $root = $xpath->query('//*[@data-sr-url-embed-root="1"]')->item(0);
    if (!$root instanceof DOMElement) {
        return $bodyHtml;
    }
    $html = '';
    foreach ($root->childNodes as $child) {
        $html .= $dom->saveHTML($child);
    }

    return $html !== '' ? $html : $bodyHtml;
}

function sr_url_embed_dom_fragment_from_html(DOMDocument $targetDom, string $html): ?DOMDocumentFragment
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

function sr_url_embed_render_url(PDO $pdo, string $url, array $context): string
{
    if (!array_key_exists('display_base_url', $context)) {
        $context['display_base_url'] = sr_url_embed_display_base_url($pdo);
    }

    $cacheRows = isset($context['url_cache_by_source']) && is_array($context['url_cache_by_source'])
        ? $context['url_cache_by_source']
        : [];
    $cachedRow = sr_url_embed_cached_row_for_url($cacheRows, $url);
    $resolved = is_array($cachedRow) ? sr_url_embed_resolved_from_cache_row($cachedRow) : [];
    if ($resolved === [] || (string) ($resolved['cache_status'] ?? '') !== 'fresh') {
        $resolved = sr_url_embed_resolve_url($pdo, $url, $context);
        if (!is_array($resolved)) {
            return '';
        }
        if (sr_url_embed_is_self_reference($resolved, $context)) {
            return '';
        }
        sr_url_embed_cache_resolved_for_render($pdo, $resolved, $context);
    }
    if (sr_url_embed_is_self_reference($resolved, $context)) {
        return '';
    }
    if ((string) ($resolved['cache_status'] ?? '') !== 'fresh') {
        return '';
    }
    $settings = isset($context['url_embed_settings']) && is_array($context['url_embed_settings'])
        ? $context['url_embed_settings']
        : sr_url_embed_settings($pdo);
    if (!sr_url_embed_embed_kind_allowed((string) ($resolved['embed_kind'] ?? ''), $settings)) {
        return '';
    }

    if ((string) ($resolved['embed_kind'] ?? '') === 'external_url') {
        return sr_url_embed_render_external_url($resolved);
    }

    $definition = sr_url_embed_url_contract_targets($pdo)[$resolved['target_module']][$resolved['target_type']] ?? null;
    if (!is_array($definition) || !is_callable($definition['render_embed'] ?? null)) {
        return '';
    }

    $cachedHtml = sr_url_embed_fragment_cache_read($resolved, $definition, $context);
    if ($cachedHtml !== '') {
        return $cachedHtml;
    }

    try {
        $rendered = $definition['render_embed']($pdo, $resolved, $context);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'url_embed_url_render_failed_' . (string) ($resolved['target_module'] ?? '') . '_' . (string) ($resolved['target_type'] ?? ''));
        return '';
    }
    if (is_array($rendered)) {
        $renderCacheStatus = array_key_exists('cache_status', $rendered)
            ? sr_url_embed_clean_cache_status((string) $rendered['cache_status'])
            : (string) ($resolved['cache_status'] ?? '');
        $renderCacheVersion = sr_url_embed_clean_label((string) ($rendered['target_cache_version'] ?? ''));
        $cacheStatusChanged = $renderCacheStatus !== '' && $renderCacheStatus !== (string) ($resolved['cache_status'] ?? '');
        $cacheVersionChanged = $renderCacheVersion !== '' && $renderCacheVersion !== (string) ($resolved['target_cache_version'] ?? '');
        if ($cacheStatusChanged || $cacheVersionChanged) {
            $refreshed = sr_url_embed_resolve_url($pdo, $url, $context);
            if (is_array($refreshed)) {
                sr_url_embed_cache_resolved_for_render($pdo, $refreshed, $context);
                $resolved = $refreshed;
                if ((string) ($resolved['cache_status'] ?? '') === 'fresh') {
                    try {
                        $rendered = $definition['render_embed']($pdo, $resolved, $context);
                    } catch (Throwable $exception) {
                        sr_log_exception($exception, 'url_embed_url_render_refresh_failed_' . (string) ($resolved['target_module'] ?? '') . '_' . (string) ($resolved['target_type'] ?? ''));
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

    $html = sr_url_embed_sanitize_rendered_fragment($html, $definition);
    sr_url_embed_fragment_cache_write($resolved, $definition, $context, $html);

    return $html;
}

function sr_url_embed_sanitize_rendered_fragment(string $html, array $definition = []): string
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
    $allowedTags = ['div', 'a', 'img', 'strong', 'p', 'span', 'sr-content-embed', 'sr-community-embed', 'sr-coupon-embed', 'sr-quiz-embed', 'sr-survey-embed'];
    $allowedAttrs = ['class', 'href', 'src', 'alt', 'loading', 'decoding', 'data-content-embed', 'data-community-embed', 'data-coupon-embed', 'data-quiz-embed', 'data-survey-embed'];
    $targetModule = sr_url_embed_clean_identifier((string) ($definition['target_module'] ?? ''));
    if ($targetModule !== '') {
        $allowedTags[] = 'sr-' . $targetModule . '-embed';
        $allowedAttrs[] = 'data-' . $targetModule . '-embed';
        $allowedTags = array_values(array_unique($allowedTags));
        $allowedAttrs = array_values(array_unique($allowedAttrs));
    }
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
            if (in_array(strtolower($attribute->name), ['href', 'src'], true) && sr_url_embed_safe_url($attribute->value) === '') {
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
