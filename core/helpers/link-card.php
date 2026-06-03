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

function sr_link_card_token_rejection_errors(string $bodyText): array
{
    if (sr_link_card_extract_tokens($bodyText) === []) {
        return [];
    }

    return ['본문에는 링크 카드 토큰을 저장할 수 없습니다. 검색 삽입은 일반 HTML 또는 텍스트 링크로 저장해 주세요.'];
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

    $bodyHtml = sr_link_card_split_block_tokens_in_containers($bodyHtml, ['p', 'h2', 'h3'], null, $renderToken);
    $bodyHtml = sr_link_card_split_block_tokens_in_containers($bodyHtml, ['strong', 'em', 'u', 's', 'a'], 'p', $renderToken);

    return preg_replace_callback(sr_link_card_token_pattern(), static function (array $match) use ($renderToken): string {
        return $renderToken((string) ($match[1] ?? ''), (string) ($match[0] ?? ''));
    }, $bodyHtml) ?? $bodyHtml;
}

function sr_link_card_split_block_tokens_in_containers(string $bodyHtml, array $tagNames, ?string $fragmentTagName, callable $renderToken): string
{
    $safeTags = [];
    foreach ($tagNames as $tagName) {
        $tagName = strtolower((string) $tagName);
        if (preg_match('/\A[a-z][a-z0-9]*\z/', $tagName) === 1) {
            $safeTags[] = preg_quote($tagName, '/');
        }
    }
    if ($safeTags === []) {
        return $bodyHtml;
    }

    $pattern = '/<(' . implode('|', $safeTags) . ')(\s[^>]*)?>(.*?)<\/\1>/us';
    return preg_replace_callback($pattern, static function (array $match) use ($fragmentTagName, $renderToken): string {
        $tagName = strtolower((string) ($match[1] ?? 'p'));
        $tagAttributes = (string) ($match[2] ?? '');
        $containerBody = (string) ($match[3] ?? '');
        if (preg_match_all(sr_link_card_token_pattern(), $containerBody, $tokenMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
            return (string) ($match[0] ?? '');
        }

        $parts = [];
        $buffer = '';
        $offset = 0;
        $hasBlockCard = false;
        $bufferWrappers = [];
        $flushFragment = static function () use (&$parts, &$buffer, &$bufferWrappers, $fragmentTagName, $tagName, $tagAttributes): void {
            $fragmentSource = $buffer;
            if ($bufferWrappers !== []) {
                $prefix = '';
                foreach ($bufferWrappers as $wrapper) {
                    $prefix .= (string) ($wrapper['open'] ?? '');
                }
                $fragmentSource = $prefix . $fragmentSource;
            }

            $fragmentHtml = sr_link_card_fragment_inline_html($fragmentSource);
            if (trim($fragmentHtml) === '') {
                $buffer = '';
                $bufferWrappers = [];
                return;
            }

            $outputTag = $fragmentTagName ?: $tagName;
            if ($fragmentTagName !== null && in_array($tagName, ['strong', 'em', 'u', 's', 'a'], true)) {
                $openTag = sr_link_card_sanitized_inline_open_tag($tagName, $tagAttributes);
                if ($openTag !== '') {
                    $fragmentHtml = $openTag . $fragmentHtml . '</' . $tagName . '>';
                }
            }
            $parts[] = '<' . $outputTag . '>' . $fragmentHtml . '</' . $outputTag . '>';
            $buffer = '';
            $bufferWrappers = [];
        };

        foreach ($tokenMatches as $tokenMatch) {
            $rawToken = (string) ($tokenMatch[0][0] ?? '');
            $tokenOffset = (int) ($tokenMatch[0][1] ?? 0);
            $attributeText = (string) ($tokenMatch[1][0] ?? '');
            $token = sr_link_card_parse_attributes($attributeText);
            $buffer .= substr($containerBody, $offset, max(0, $tokenOffset - $offset));
            $offset = $tokenOffset + strlen($rawToken);

            if (!sr_link_card_token_is_valid($token) || (string) ($token['variant'] ?? 'compact') === 'inline') {
                $buffer .= $rawToken;
                continue;
            }

            $flushFragment();
            $parts[] = $renderToken($attributeText, $rawToken);
            $bufferWrappers = sr_link_card_inline_wrappers_at_offset($containerBody, $tokenOffset);
            $hasBlockCard = true;
        }

        $buffer .= substr($containerBody, $offset);
        $flushFragment();

        return $hasBlockCard ? implode('', $parts) : (string) ($match[0] ?? '');
    }, $bodyHtml) ?? $bodyHtml;
}

function sr_link_card_fragment_inline_html(string $html): string
{
    if ($html === '') {
        return '';
    }

    if (!class_exists('DOMDocument')) {
        return sr_e(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="UTF-8"><div id="sr-link-card-fragment-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return sr_e(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $root = null;
    foreach ($document->getElementsByTagName('div') as $div) {
        if ($div instanceof DOMElement && $div->getAttribute('id') === 'sr-link-card-fragment-root') {
            $root = $div;
            break;
        }
    }

    if (!$root instanceof DOMElement) {
        return '';
    }

    $output = '';
    foreach ($root->childNodes as $child) {
        $output .= sr_link_card_fragment_inline_node_html($child);
    }

    return $output;
}

function sr_link_card_fragment_inline_node_html(DOMNode $node): string
{
    if ($node instanceof DOMText) {
        return sr_e($node->wholeText);
    }

    if (!$node instanceof DOMElement) {
        return '';
    }

    $tagName = strtolower($node->tagName);
    if (in_array($tagName, ['script', 'style', 'iframe', 'object', 'embed', 'form'], true)) {
        return '';
    }

    $children = '';
    foreach ($node->childNodes as $child) {
        $children .= sr_link_card_fragment_inline_node_html($child);
    }

    if ($tagName === 'br') {
        return '<br>';
    }
    if ($tagName === 'img') {
        $attributes = sr_link_card_sanitized_inline_attributes($node, ['src', 'alt', 'width', 'height'], $tagName);
        return $attributes === '' ? '' : '<img' . $attributes . '>';
    }
    if (!in_array($tagName, ['strong', 'em', 'u', 's', 'a'], true)) {
        return $children;
    }

    $attributes = sr_link_card_sanitized_inline_attributes($node, $tagName === 'a' ? ['href'] : [], $tagName);
    return '<' . $tagName . $attributes . '>' . $children . '</' . $tagName . '>';
}

function sr_link_card_sanitized_inline_open_tag(string $tagName, string $attributeText): string
{
    $tagName = strtolower($tagName);
    if (!in_array($tagName, ['strong', 'em', 'u', 's', 'a'], true)) {
        return '';
    }
    if (!class_exists('DOMDocument')) {
        return '<' . $tagName . '>';
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="UTF-8"><div id="sr-link-card-open-root"><' . $tagName . $attributeText . '></' . $tagName . '></div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return '<' . $tagName . '>';
    }

    $nodes = $document->getElementsByTagName($tagName);
    $node = $nodes->item(0);
    if (!$node instanceof DOMElement) {
        return '<' . $tagName . '>';
    }

    $attributes = sr_link_card_sanitized_inline_attributes($node, $tagName === 'a' ? ['href'] : [], $tagName);
    return '<' . $tagName . $attributes . '>';
}

function sr_link_card_sanitized_inline_attributes(DOMElement $node, array $allowedAttributes, string $tagName): string
{
    $attributes = '';
    foreach ($allowedAttributes as $attributeName) {
        if (!$node->hasAttribute($attributeName)) {
            continue;
        }

        $value = trim($node->getAttribute($attributeName));
        if ($attributeName === 'href' || $attributeName === 'src') {
            if (!sr_is_safe_relative_url($value) && !sr_is_http_url($value)) {
                continue;
            }
            if ($attributeName === 'src' && sr_is_http_url($value) && strtolower((string) parse_url($value, PHP_URL_SCHEME)) !== 'https') {
                continue;
            }
        } elseif ($attributeName === 'width' || $attributeName === 'height') {
            if (preg_match('/\A[1-9][0-9]{0,3}\z/', $value) !== 1) {
                continue;
            }
        } elseif ($attributeName === 'alt') {
            $value = function_exists('mb_substr') ? mb_substr($value, 0, 160) : substr($value, 0, 160);
        } else {
            continue;
        }

        $attributes .= ' ' . $attributeName . '="' . sr_e($value) . '"';
    }

    if ($tagName === 'a' && $attributes !== '') {
        $attributes .= ' rel="nofollow noopener noreferrer"';
    }

    return $attributes;
}

function sr_link_card_inline_wrappers_at_offset(string $html, int $offset): array
{
    if ($html === '' || $offset < 1) {
        return [];
    }

    $allowedTags = ['a' => true, 'strong' => true, 'em' => true, 'u' => true, 's' => true];
    if (preg_match_all('/<\s*(\/)?\s*([a-z][a-z0-9]*)(\s[^>]*)?>/i', $html, $tagMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
        return [];
    }

    $stack = [];
    foreach ($tagMatches as $tagMatch) {
        $tagHtml = (string) ($tagMatch[0][0] ?? '');
        $tagOffset = (int) ($tagMatch[0][1] ?? 0);
        if ($tagOffset >= $offset) {
            break;
        }

        $tagName = strtolower((string) ($tagMatch[2][0] ?? ''));
        if (!isset($allowedTags[$tagName])) {
            continue;
        }

        $isClosingTag = (string) ($tagMatch[1][0] ?? '') === '/';
        if ($isClosingTag) {
            for ($i = count($stack) - 1; $i >= 0; $i--) {
                if (($stack[$i]['tag'] ?? '') === $tagName) {
                    array_splice($stack, $i, 1);
                    break;
                }
            }
            continue;
        }

        if (str_ends_with($tagHtml, '/>')) {
            continue;
        }

        $stack[] = [
            'tag' => $tagName,
            'open' => sr_link_card_sanitized_inline_open_tag($tagName, (string) ($tagMatch[3][0] ?? '')),
        ];
    }

    return $stack;
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
