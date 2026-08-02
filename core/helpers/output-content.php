<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

function sr_linkify_plain_text_urls(string $value, bool $openInNewTab = false): string
{
    if ($value === '') {
        return '';
    }

    $pattern = '~https?://[^\s<>"\']+~iu';
    if (preg_match_all($pattern, $value, $matches, PREG_OFFSET_CAPTURE) < 1) {
        return sr_e($value);
    }

    $html = '';
    $offset = 0;
    foreach ($matches[0] as $match) {
        $url = (string) $match[0];
        $matchOffset = (int) $match[1];
        $trailing = '';
        while ($url !== '' && preg_match('/[.,!?;:\)\]\}]\z/u', $url) === 1) {
            $trailing = substr($url, -1) . $trailing;
            $url = substr($url, 0, -1);
        }

        $html .= sr_e(substr($value, $offset, $matchOffset - $offset));
        if ($url !== '' && sr_is_http_url($url)) {
            $escapedUrl = sr_e($url);
            $targetAttribute = $openInNewTab ? ' target="_blank"' : '';
            $html .= '<a href="' . $escapedUrl . '"' . $targetAttribute . ' rel="nofollow noopener noreferrer">' . $escapedUrl . '</a>';
            $html .= sr_e($trailing);
        } else {
            $html .= sr_e((string) $match[0]);
        }
        $offset = $matchOffset + strlen((string) $match[0]);
    }

    return $html . sr_e(substr($value, $offset));
}

function sr_plain_text_html(string $value, bool $linkUrls = false, bool $openLinksInNewTab = false): string
{
    if ($linkUrls) {
        return nl2br(sr_linkify_plain_text_urls($value, $openLinksInNewTab), false);
    }

    return nl2br(sr_e($value), false);
}

function sr_rich_text_allowed_html_tags(): array
{
    return [
        'p' => ['style'],
        'br' => [],
        'strong' => [],
        'em' => [],
        'u' => [],
        's' => [],
        'span' => ['style'],
        'blockquote' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'a' => ['href'],
        'h1' => ['style'],
        'h2' => ['style'],
        'h3' => ['style'],
        'h4' => ['style'],
        'img' => ['src', 'alt', 'width', 'height'],
        'figure' => ['class'],
        'figcaption' => [],
        'table' => [],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
        'hr' => [],
    ];
}

function sr_sanitize_rich_text_html(string $html): string
{
    $html = sr_strip_rich_text_dropped_containers($html);
    $purifiedHtml = sr_sanitize_rich_text_html_with_purifier($html);
    if (is_string($purifiedHtml)) {
        $html = $purifiedHtml;
    }

    return sr_sanitize_rich_text_html_fallback($html);
}

function sr_strip_rich_text_dropped_containers(string $html): string
{
    if ($html === '' || !class_exists('DOMDocument')) {
        return $html;
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="UTF-8"><div id="sr-rich-text-strip-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return $html;
    }

    foreach (['script', 'style', 'iframe', 'object', 'embed', 'form', 'meta'] as $tagName) {
        while (true) {
            $nodes = $document->getElementsByTagName($tagName);
            if ($nodes->length < 1) {
                break;
            }

            $node = $nodes->item(0);
            if (!$node instanceof DOMNode || !$node->parentNode instanceof DOMNode) {
                break;
            }

            $node->parentNode->removeChild($node);
        }
    }

    $root = null;
    foreach ($document->getElementsByTagName('div') as $div) {
        if ($div instanceof DOMElement && $div->getAttribute('id') === 'sr-rich-text-strip-root') {
            $root = $div;
            break;
        }
    }
    if (!$root instanceof DOMElement) {
        return $html;
    }

    $output = '';
    foreach ($root->childNodes as $child) {
        $serialized = $document->saveHTML($child);
        if (is_string($serialized)) {
            $output .= $serialized;
        }
    }

    return $output;
}

function sr_sanitize_rich_text_html_with_purifier(string $html): ?string
{
    if (!sr_rich_text_purifier_available()) {
        return null;
    }

    try {
        $config = sr_rich_text_purifier_config();
        $purifier = new HTMLPurifier($config);
        $purifiedHtml = $purifier->purify($html);
        return is_string($purifiedHtml) ? $purifiedHtml : null;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'rich_text_html_purifier');
        return null;
    }
}

function sr_rich_text_purifier_config(): HTMLPurifier_Config
{
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Core.Encoding', 'UTF-8');
    $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
    $config->set('HTML.DefinitionID', 'saanraan-rich-text');
    $config->set('HTML.DefinitionRev', 4);
    $config->set('HTML.Allowed', 'p[style],br,strong,em,u,s,span[style],blockquote,ul,ol,li,a[href|rel],h1[style],h2[style],h3[style],h4[style],img[src|alt|width|height],figure[class],figcaption,table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan],hr');
    $config->set('Attr.AllowedClasses', ['image', 'table']);
    $config->set('CSS.AllowedProperties', ['color', 'background-color', 'font-size', 'text-align', 'margin-left']);
    $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true]);
    $config->set('HTML.Nofollow', true);
    $config->set('HTML.TargetBlank', false);

    $cacheDir = sr_rich_text_purifier_cache_dir();
    if ($cacheDir !== '') {
        $config->set('Cache.SerializerPath', $cacheDir);
    } else {
        $config->set('Cache.DefinitionImpl', null);
    }

    $definition = $config->maybeGetRawHTMLDefinition();
    if ($definition instanceof HTMLPurifier_HTMLDefinition) {
        $definition->addElement('figure', 'Block', 'Flow', 'Common', ['class' => 'Class']);
        $definition->addElement('figcaption', 'Block', 'Flow', 'Common');
    }

    return $config;
}

function sr_rich_text_purifier_status(): array
{
    $autoloadPath = '';
    foreach (sr_rich_text_purifier_autoload_paths() as $path) {
        if (is_file($path)) {
            $autoloadPath = $path;
            break;
        }
    }

    $available = sr_rich_text_purifier_available();
    $cacheDir = sr_rich_text_purifier_cache_dir();
    $definitionImpl = '';
    if ($available) {
        try {
            $config = sr_rich_text_purifier_config();
            $definitionImpl = (string) $config->get('Cache.DefinitionImpl');
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'rich_text_html_purifier_status');
        }
    }

    return [
        'available' => $available,
        'version' => $available && class_exists('HTMLPurifier') ? (string) HTMLPurifier::VERSION : '',
        'autoload_path' => $autoloadPath !== '' ? sr_rich_text_purifier_relative_path($autoloadPath) : '',
        'cache_dir' => $cacheDir !== '' ? sr_rich_text_purifier_relative_path($cacheDir) : '',
        'cache_writable' => $cacheDir !== '' && is_writable($cacheDir),
        'definition_impl' => $definitionImpl,
    ];
}

function sr_rich_text_purifier_relative_path(string $path): string
{
    $root = rtrim(str_replace('\\', '/', SR_ROOT), '/');
    $normalizedPath = str_replace('\\', '/', $path);
    if ($root !== '' && str_starts_with($normalizedPath, $root . '/')) {
        return substr($normalizedPath, strlen($root) + 1);
    }

    return $normalizedPath;
}

function sr_rich_text_purifier_available(): bool
{
    if (class_exists('HTMLPurifier') && class_exists('HTMLPurifier_Config')) {
        return true;
    }

    foreach (sr_rich_text_purifier_autoload_paths() as $path) {
        if (is_file($path)) {
            require_once $path;
            if (class_exists('HTMLPurifier') && class_exists('HTMLPurifier_Config')) {
                return true;
            }
        }
    }

    return false;
}

function sr_rich_text_purifier_autoload_paths(): array
{
    return [
        SR_ROOT . '/modules/htmlpurifier/vendor/autoload.php',
        SR_ROOT . '/modules/htmlpurifier/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php',
        SR_ROOT . '/vendor/autoload.php',
        SR_ROOT . '/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php',
    ];
}

function sr_rich_text_purifier_cache_dir(): string
{
    $cacheDir = SR_ROOT . '/storage/cache/htmlpurifier';
    if (is_dir($cacheDir)) {
        return is_writable($cacheDir) ? $cacheDir : '';
    }

    if (is_dir(SR_ROOT . '/storage') && is_writable(SR_ROOT . '/storage')) {
        return mkdir($cacheDir, 0755, true) || is_dir($cacheDir) ? $cacheDir : '';
    }

    return '';
}

function sr_sanitize_rich_text_html_fallback(string $html): string
{
    if (!class_exists('DOMDocument')) {
        return sr_plain_text_html(strip_tags($html));
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="UTF-8"><div id="sr-rich-text-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return '';
    }

    $root = null;
    foreach ($document->getElementsByTagName('div') as $div) {
        if ($div instanceof DOMElement && $div->getAttribute('id') === 'sr-rich-text-root') {
            $root = $div;
            break;
        }
    }
    if (!$root instanceof DOMElement) {
        return '';
    }

    $output = '';
    foreach ($root->childNodes as $child) {
        $output .= sr_sanitize_rich_text_html_node($child);
    }

    return trim($output);
}

function sr_sanitize_rich_text_html_node(DOMNode $node): string
{
    if ($node instanceof DOMText) {
        return sr_e($node->wholeText);
    }

    if (!$node instanceof DOMElement) {
        return '';
    }

    $tagName = strtolower($node->tagName);
    if (in_array($tagName, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'meta'], true)) {
        return '';
    }

    $allowedTags = sr_rich_text_allowed_html_tags();
    $children = '';
    foreach ($node->childNodes as $child) {
        $children .= sr_sanitize_rich_text_html_node($child);
    }

    if (!isset($allowedTags[$tagName])) {
        return $children;
    }

    if ($tagName === 'br' || $tagName === 'hr') {
        return '<' . $tagName . '>';
    }

    $attributes = sr_sanitize_rich_text_html_attributes($node, $tagName, $allowedTags[$tagName]);
    if ($tagName === 'a' && $attributes === '') {
        return $children;
    }
    if (($tagName === 'span' || $tagName === 'figure') && $attributes === '') {
        return $children;
    }
    if ($tagName === 'img') {
        return $attributes === '' ? '' : '<img' . $attributes . '>';
    }

    return '<' . $tagName . $attributes . '>' . $children . '</' . $tagName . '>';
}

function sr_sanitize_rich_text_html_attributes(DOMElement $node, string $tagName, array $allowedAttributes): string
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
        } elseif ($attributeName === 'class' && $tagName === 'figure') {
            $value = sr_sanitize_rich_text_html_class($value, ['image', 'table']);
            if ($value === '') {
                continue;
            }
        } elseif ($attributeName === 'colspan' || $attributeName === 'rowspan') {
            if (!in_array($tagName, ['th', 'td'], true) || preg_match('/\A[1-9][0-9]?\z/', $value) !== 1) {
                continue;
            }
        } elseif ($attributeName === 'style') {
            $value = sr_sanitize_rich_text_html_style($value, $tagName);
            if ($value === '') {
                continue;
            }
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

function sr_sanitize_rich_text_html_class(string $value, array $allowedClasses): string
{
    $allowed = array_fill_keys($allowedClasses, true);
    $classes = [];
    foreach (preg_split('/\s+/', trim($value)) ?: [] as $className) {
        if (isset($allowed[$className])) {
            $classes[$className] = true;
        }
    }

    return implode(' ', array_keys($classes));
}

function sr_sanitize_rich_text_html_style(string $value, string $tagName): string
{
    $allowedByTag = [
        'p' => ['text-align', 'margin-left'],
        'h1' => ['text-align', 'margin-left'],
        'h2' => ['text-align', 'margin-left'],
        'h3' => ['text-align', 'margin-left'],
        'h4' => ['text-align', 'margin-left'],
        'span' => ['color', 'background-color', 'font-size'],
    ];
    $allowedProperties = $allowedByTag[$tagName] ?? [];
    if ($allowedProperties === []) {
        return '';
    }

    $declarations = [];
    foreach (explode(';', $value) as $declaration) {
        $parts = explode(':', $declaration, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $property = strtolower(trim($parts[0]));
        $propertyValue = strtolower(trim($parts[1]));
        if (!in_array($property, $allowedProperties, true)) {
            continue;
        }

        $isAllowed = match ($property) {
            'text-align' => in_array($propertyValue, ['left', 'center', 'right', 'justify'], true),
            'margin-left' => in_array($propertyValue, ['40px', '80px', '120px', '160px', '200px'], true),
            'font-size' => in_array($propertyValue, ['12px', '14px', '18px', '24px', '32px'], true),
            'color' => in_array($propertyValue, ['#111827', '#6b7280', '#b91c1c', '#a16207', '#15803d', '#1d4ed8', '#7e22ce'], true),
            'background-color' => in_array($propertyValue, ['#f3f4f6', '#fee2e2', '#fef3c7', '#dcfce7', '#dbeafe', '#f3e8ff'], true),
            default => false,
        };
        if ($isAllowed) {
            $declarations[$property] = $propertyValue;
        }
    }

    $style = '';
    foreach ($allowedProperties as $property) {
        if (isset($declarations[$property])) {
            $style .= $property . ':' . $declarations[$property] . ';';
        }
    }

    return $style;
}

function sr_body_text_html(array $record, bool $linkPlainUrls = false, ?PDO $pdo = null, string $markdownMode = 'full', bool $openPlainLinksInNewTab = false): string
{
    $bodyText = (string) ($record['body_text'] ?? '');
    $bodyFormat = sr_body_format((string) ($record['body_format'] ?? 'plain'));
    if ($bodyFormat === 'html') {
        return sr_sanitize_rich_text_html($bodyText);
    }
    if ($bodyFormat === 'markdown') {
        if ($pdo instanceof PDO) {
            $result = sr_markdown_render($pdo, $bodyText, $markdownMode);
            if ($result !== null) {
                return (string) ($result['html'] ?? '');
            }
        }

        return $markdownMode === 'plain' ? sr_e(sr_markdown_plain_text($bodyText)) : sr_markdown_text_html($bodyText);
    }

    return sr_plain_text_html($bodyText, $linkPlainUrls, $openPlainLinksInNewTab);
}

function sr_body_text_plain_text(array $record, ?PDO $pdo = null): string
{
    $bodyText = (string) ($record['body_text'] ?? '');
    $bodyFormat = sr_body_format((string) ($record['body_format'] ?? 'plain'));
    if ($bodyFormat === 'html') {
        $bodyText = str_replace(['<br>', '<br/>', '<br />'], ' ', sr_sanitize_rich_text_html($bodyText));
        $bodyText = html_entity_decode(strip_tags($bodyText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    } elseif ($bodyFormat === 'markdown') {
        $bodyText = $pdo instanceof PDO
            ? sr_markdown_plain_text_for_body($pdo, $bodyText)
            : sr_markdown_plain_text($bodyText);
    }

    return trim(preg_replace('/\s+/u', ' ', $bodyText) ?? '');
}

function sr_body_format(string $value): string
{
    return in_array($value, ['plain', 'html', 'markdown'], true) ? $value : 'plain';
}

function sr_body_editor_stylesheets(string $bodyFormat, string $bodyEditorKey = ''): array
{
    $bodyFormat = sr_body_format($bodyFormat);
    if ($bodyFormat === 'markdown') {
        return ['/assets/editor-md.css'];
    }
    if ($bodyFormat === 'html' && sr_editor_normalize_key($bodyEditorKey) === 'ckeditor') {
        return [
            '/modules/ckeditor/vendor/ckeditor5/ckeditor5.css',
            '/modules/ckeditor/assets/saanraan-ckeditor.css',
        ];
    }

    return [];
}

function sr_markdown_text_html(string $markdown): string
{
    $markdown = trim(str_replace(["\r\n", "\r"], "\n", $markdown));
    if ($markdown === '') {
        return '';
    }

    $html = [];
    $paragraph = [];
    $listType = '';
    $listItems = [];

    $flushParagraph = static function () use (&$html, &$paragraph): void {
        if ($paragraph === []) {
            return;
        }
        $html[] = '<p>' . sr_markdown_inline_html(implode("\n", $paragraph)) . '</p>';
        $paragraph = [];
    };
    $flushList = static function () use (&$html, &$listType, &$listItems): void {
        if ($listType === '' || $listItems === []) {
            return;
        }
        $items = [];
        foreach ($listItems as $item) {
            $items[] = '<li>' . sr_markdown_inline_html($item) . '</li>';
        }
        $html[] = '<' . $listType . '>' . implode('', $items) . '</' . $listType . '>';
        $listType = '';
        $listItems = [];
    };

    foreach (explode("\n", $markdown) as $line) {
        $trimmedLine = trim($line);
        if ($trimmedLine === '') {
            $flushParagraph();
            $flushList();
            continue;
        }

        if (preg_match('/\A(#{1,6})\s+(.+)\z/', $trimmedLine, $headingMatches) === 1) {
            $flushParagraph();
            $flushList();
            $level = strlen($headingMatches[1]);
            $html[] = '<h' . $level . '>' . sr_markdown_inline_html((string) $headingMatches[2]) . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/\A[-*+]\s+(.+)\z/', $trimmedLine, $unorderedMatches) === 1) {
            $flushParagraph();
            if ($listType !== 'ul') {
                $flushList();
                $listType = 'ul';
            }
            $listItems[] = (string) $unorderedMatches[1];
            continue;
        }

        if (preg_match('/\A[0-9]+\.\s+(.+)\z/', $trimmedLine, $orderedMatches) === 1) {
            $flushParagraph();
            if ($listType !== 'ol') {
                $flushList();
                $listType = 'ol';
            }
            $listItems[] = (string) $orderedMatches[1];
            continue;
        }

        $flushList();
        $paragraph[] = $trimmedLine;
    }

    $flushParagraph();
    $flushList();

    return implode("\n", $html);
}

function sr_markdown_inline_html(string $text, bool $allowLineBreaks = true): string
{
    $placeholders = [];
    $text = preg_replace_callback('/`([^`]+)`/', static function (array $matches) use (&$placeholders): string {
        $token = "\x1A" . count($placeholders) . "\x1A";
        $placeholders[$token] = '<code>' . sr_e((string) $matches[1]) . '</code>';
        return $token;
    }, $text) ?? $text;

    $text = preg_replace_callback('/\[([^\]\n]+)\]\(([^)\s]+)\)/', static function (array $matches) use (&$placeholders): string {
        $url = trim((string) $matches[2]);
        if (!sr_is_safe_relative_url($url) && !sr_is_http_url($url)) {
            return (string) $matches[0];
        }

        $token = "\x1A" . count($placeholders) . "\x1A";
        $placeholders[$token] = '<a href="' . sr_e($url) . '" rel="nofollow noopener noreferrer">' . sr_e((string) $matches[1]) . '</a>';
        return $token;
    }, $text) ?? $text;

    $html = sr_e($text);
    $html = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $html) ?? $html;
    $html = preg_replace('/__([^_\n]+)__/', '<strong>$1</strong>', $html) ?? $html;
    $html = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $html) ?? $html;
    $html = preg_replace('/(?<!_)_([^_\n]+)_(?!_)/', '<em>$1</em>', $html) ?? $html;
    if ($allowLineBreaks) {
        $html = nl2br($html, false);
    }

    return strtr($html, $placeholders);
}

function sr_markdown_plain_text(string $markdown): string
{
    return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(sr_markdown_text_html($markdown)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?? '');
}

function sr_markdown_renderer_contracts(PDO $pdo): array
{
    static $cache = [];
    $cacheKey = (string) spl_object_id($pdo);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $contracts = [];
    foreach (sr_enabled_module_contract_files($pdo, 'markdown-renderer.php') as $moduleKey => $file) {
        $contract = sr_load_module_contract_file($moduleKey, $file);
        if (!is_array($contract)) {
            continue;
        }
        if ((string) ($contract['format_key'] ?? '') !== 'markdown') {
            continue;
        }

        $helpers = (string) ($contract['helpers'] ?? '');
        if ($helpers !== '') {
            if (preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) !== 1) {
                continue;
            }

            $helperPath = SR_ROOT . '/modules/' . $moduleKey . '/' . $helpers;
            if (!is_file($helperPath)) {
                continue;
            }

            require_once $helperPath;
        }

        $renderFunction = (string) ($contract['render_function'] ?? '');
        if ($renderFunction === '' || !function_exists($renderFunction)) {
            continue;
        }
        $availableFunction = (string) ($contract['available_function'] ?? '');
        if ($availableFunction !== '' && function_exists($availableFunction) && $availableFunction($pdo) !== true) {
            continue;
        }

        $contracts[$moduleKey] = [
            'module_key' => $moduleKey,
            'render_function' => $renderFunction,
            'stylesheet_function' => (string) ($contract['stylesheet_function'] ?? ''),
            'profile_hash_function' => (string) ($contract['profile_hash_function'] ?? ''),
        ];
    }

    $cache[$cacheKey] = $contracts;
    return $contracts;
}

function sr_markdown_renderer_available(PDO $pdo): bool
{
    return sr_markdown_renderer_contracts($pdo) !== [];
}

function sr_markdown_render(PDO $pdo, string $markdown, string $mode = 'full', array $context = []): ?array
{
    $mode = in_array($mode, ['full', 'inline', 'plain'], true) ? $mode : 'full';
    foreach (sr_markdown_renderer_contracts($pdo) as $contract) {
        $renderFunction = (string) ($contract['render_function'] ?? '');
        if ($renderFunction === '' || !function_exists($renderFunction)) {
            continue;
        }

        try {
            $result = $renderFunction($pdo, $markdown, $mode, $context);
        } catch (Throwable $exception) {
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'markdown_renderer_failed_' . (string) ($contract['module_key'] ?? 'unknown'));
            }
            continue;
        }

        if (is_array($result)) {
            return $result;
        }
    }

    return null;
}

function sr_markdown_plain_text_for_body(PDO $pdo, string $markdown): string
{
    $result = sr_markdown_render($pdo, $markdown, 'plain');
    if (is_array($result)) {
        return trim((string) ($result['plain_text'] ?? strip_tags((string) ($result['html'] ?? ''))));
    }

    return sr_markdown_plain_text($markdown);
}

function sr_markdown_stylesheets(PDO $pdo, string $markdown = '', string $mode = 'full', array $context = []): array
{
    $result = sr_markdown_render($pdo, $markdown, $mode, $context);
    if (!is_array($result)) {
        return [];
    }

    $stylesheets = $result['stylesheets'] ?? [];
    if (!is_array($stylesheets)) {
        return [];
    }

    $clean = [];
    foreach ($stylesheets as $stylesheet) {
        if (is_string($stylesheet) && $stylesheet !== '') {
            $clean[] = $stylesheet;
        }
    }

    return array_values(array_unique($clean));
}
