<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

function sr_set_locale(string $locale): void
{
    if (preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $locale) !== 1) {
        $locale = 'ko';
    }

    $GLOBALS['sr_locale'] = $locale;
}

function sr_locale(): string
{
    $locale = $GLOBALS['sr_locale'] ?? 'ko';
    return is_string($locale) && $locale !== '' ? $locale : 'ko';
}

function sr_resolve_locale(PDO $pdo, ?array $site): string
{
    $supportedLocales = sr_supported_locales($site);
    $accountId = $_SESSION['sr_account_id'] ?? null;
    if (is_int($accountId) || ctype_digit((string) $accountId)) {
        try {
            $stmt = $pdo->prepare('SELECT locale FROM sr_member_accounts WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int) $accountId]);
            $account = $stmt->fetch();
            if (
                is_array($account)
                && is_string($account['locale'] ?? null)
                && in_array((string) $account['locale'], $supportedLocales, true)
            ) {
                return (string) $account['locale'];
            }
        } catch (Throwable $exception) {
            return is_array($site) ? (string) ($site['default_locale'] ?? 'ko') : 'ko';
        }
    }

    return is_array($site) ? (string) ($site['default_locale'] ?? 'ko') : 'ko';
}

function sr_supported_locales(?array $site): array
{
    $defaultLocale = is_array($site) ? (string) ($site['default_locale'] ?? 'ko') : 'ko';
    $rawLocales = is_array($site) ? (string) ($site['supported_locales'] ?? '') : '';
    $locales = [];

    foreach (preg_split('/[\s,]+/', $rawLocales) ?: [] as $locale) {
        if (preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $locale) === 1) {
            $locales[$locale] = $locale;
        }
    }

    if (preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $defaultLocale) === 1) {
        $locales[$defaultLocale] = $defaultLocale;
    }

    return array_values($locales !== [] ? $locales : ['ko']);
}

function sr_available_locale_options(?array $site = null): array
{
    $locales = [];
    $langDir = SR_ROOT . '/lang';
    if (is_dir($langDir)) {
        foreach (scandir($langDir) ?: [] as $localeDirectory) {
            if (!is_string($localeDirectory) || preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $localeDirectory) !== 1) {
                continue;
            }

            if (is_file($langDir . '/' . $localeDirectory . '/core.php')) {
                $locales[$localeDirectory] = $localeDirectory;
            }
        }
    }

    foreach (sr_supported_locales($site) as $locale) {
        $locales[$locale] = $locale;
    }

    if ($locales === []) {
        $locales['ko'] = 'ko';
    }

    ksort($locales);

    return array_values($locales);
}

function sr_t(string $key, array $params = [], ?string $locale = null): string
{
    $locale = $locale ?? sr_locale();
    $moduleKey = '';
    $translationKey = $key;

    if (strpos($key, '::') !== false) {
        [$moduleKey, $translationKey] = explode('::', $key, 2);
    }

    $translations = sr_load_translations($locale, $moduleKey);
    $message = $translations[$translationKey] ?? null;

    if (!is_string($message) && $locale !== sr_fallback_locale()) {
        $fallbackTranslations = sr_load_translations(sr_fallback_locale(), $moduleKey);
        $message = $fallbackTranslations[$translationKey] ?? null;
    }

    if (!is_string($message)) {
        $message = $key;
    }

    foreach ($params as $name => $value) {
        $message = str_replace('{' . $name . '}', (string) $value, $message);
    }

    return $message;
}

function sr_fallback_locale(): string
{
    return 'ko';
}

function sr_load_translations(string $locale, string $moduleKey = ''): array
{
    static $cache = [];

    if (preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $locale) !== 1) {
        $locale = 'ko';
    }

    if ($moduleKey !== '' && !sr_is_safe_module_key($moduleKey)) {
        return [];
    }

    $cacheKey = $moduleKey . '|' . $locale;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $file = $moduleKey === ''
        ? SR_ROOT . '/lang/' . $locale . '/core.php'
        : SR_ROOT . '/modules/' . $moduleKey . '/lang/' . $locale . '.php';

    if (!is_file($file)) {
        $cache[$cacheKey] = [];
        return [];
    }

    $translations = include $file;
    $cache[$cacheKey] = is_array($translations) ? $translations : [];

    return $cache[$cacheKey];
}

function sr_is_safe_module_action(string $path): bool
{
    if ($path === '' || strpos($path, '..') !== false || strpos($path, '\\') !== false) {
        return false;
    }

    return preg_match('/\Aactions\/[a-z0-9_\-\/]+\.php\z/', $path) === 1;
}

function sr_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sr_public_feedback_toasts(string $namespace, string $notice = '', array $errors = []): string
{
    if (preg_match('/\A[a-z][a-z0-9_-]*\z/', $namespace) !== 1) {
        return '';
    }

    $items = [];
    if ($notice !== '') {
        $items[] = [
            'type' => 'success',
            'message' => $notice,
        ];
    }

    foreach ($errors as $error) {
        $message = trim((string) $error);
        if ($message === '') {
            continue;
        }

        $items[] = [
            'type' => 'error',
            'message' => $message,
        ];
    }

    if ($items === []) {
        return '';
    }

    ob_start();
    ?>
    <div class="<?php echo sr_e($namespace); ?>-toast-stack" data-<?php echo sr_e($namespace); ?>-toast-stack role="status" aria-live="polite" aria-atomic="false">
        <?php foreach ($items as $item) { ?>
            <div class="alert-removable alert <?php echo (string) $item['type'] === 'success' ? 'alert-success' : 'alert-danger'; ?> <?php echo sr_e($namespace); ?>-toast <?php echo sr_e($namespace); ?>-toast-<?php echo sr_e((string) $item['type']); ?>" data-<?php echo sr_e($namespace); ?>-toast role="<?php echo (string) $item['type'] === 'error' ? 'alert' : 'status'; ?>">
                <?php echo sr_e((string) $item['message']); ?>
                <button type="button" class="btn btn-sm btn-ghost-default btn-icon alert-close-leading <?php echo sr_e($namespace); ?>-toast-close" data-<?php echo sr_e($namespace); ?>-toast-close aria-label="<?php echo sr_e('닫기'); ?>">
                    <?php echo sr_material_icon_html('close', $namespace . '-toast-close-icon'); ?>
                </button>
            </div>
        <?php } ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

function sr_time_tooltip_html(?string $value, string $label, string $emptyText = ''): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return sr_e($emptyText);
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return sr_e($value);
    }

    $exactValue = date('Y-m-d H:i:s', $timestamp);
    $machineValue = date('Y-m-d\TH:i:sP', $timestamp);

    return '<time class="sr-time-tooltip" datetime="' . sr_e($machineValue) . '" tabindex="0" data-sr-time-tooltip data-sr-time-tooltip-label="' . sr_e($exactValue) . '" aria-label="' . sr_e('정확한 일시: ' . $exactValue) . '">'
        . sr_e($label)
        . '</time>';
}

function sr_relative_time_html(?string $value, string $emptyText = ''): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return sr_e($emptyText);
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return sr_e($value);
    }

    $exactValue = date('Y-m-d H:i:s', $timestamp);
    return sr_time_tooltip_html($exactValue, sr_relative_time_label($exactValue));
}

function sr_json_response(mixed $payload, int $statusCode = 200, array $headers = []): void
{
    if ($statusCode !== 200) {
        http_response_code($statusCode);
    }

    header('Content-Type: application/json; charset=utf-8');
    foreach ($headers as $header) {
        if (is_string($header) && sr_response_header_is_allowed($header)) {
            header($header);
        }
    }

    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($encoded)) {
        http_response_code(500);
        $encoded = '{"ok":false,"message":"JSON response encoding failed."}';
    }

    echo $encoded;
    sr_finish_response();
}

function sr_js_json_encode(mixed $value): string
{
    $encoded = json_encode(
        $value,
        JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_INVALID_UTF8_SUBSTITUTE
    );

    return is_string($encoded) ? $encoded : 'null';
}

function sr_response_header_is_allowed(string $header): bool
{
    if (preg_match('/[\x00-\x1F\x7F]/', $header) === 1) {
        return false;
    }

    $header = trim($header);
    $colonPosition = strpos($header, ':');
    if ($colonPosition === false) {
        return false;
    }

    $name = strtolower(trim(substr($header, 0, $colonPosition)));
    $value = trim(substr($header, $colonPosition + 1));
    if ($name === '' || $value === '') {
        return false;
    }

    if ($name === 'cache-control') {
        return sr_download_cache_control($value) === $value;
    }

    if ($name === 'content-disposition') {
        return preg_match('/\A(?:attachment|inline);\s*filename="[A-Za-z0-9._-]{1,120}"\z/', $value) === 1;
    }

    if ($name === 'content-length') {
        return preg_match('/\A(?:0|[1-9][0-9]{0,18})\z/', $value) === 1;
    }

    if ($name === 'content-security-policy') {
        return preg_match('/\A[A-Za-z][A-Za-z0-9-]*(?:\s+[^,;]+)?(?:;\s*[A-Za-z][A-Za-z0-9-]*(?:\s+[^,;]+)?)*;?\z/', $value) === 1;
    }

    if ($name === 'content-type') {
        return sr_download_content_type($value) === $value;
    }

    if ($name === 'etag') {
        return preg_match('/\A"(?:[A-Fa-f0-9]{32}|[A-Fa-f0-9]{40}|[A-Fa-f0-9]{64})"\z/', $value) === 1;
    }

    if ($name === 'last-modified') {
        return preg_match('/\A(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun), \d{2} (?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) \d{4} \d{2}:\d{2}:\d{2} GMT\z/', $value) === 1
            && strtotime($value) !== false;
    }

    if ($name === 'pragma') {
        return strtolower($value) === 'no-cache';
    }

    if ($name === 'x-content-type-options') {
        return strtolower($value) === 'nosniff';
    }

    return false;
}

function sr_http_date(int $timestamp): string
{
    return gmdate('D, d M Y H:i:s', max(0, $timestamp)) . ' GMT';
}

function sr_etag_matches(string $ifNoneMatch, string $etag): bool
{
    $ifNoneMatch = trim($ifNoneMatch);
    if ($ifNoneMatch === '') {
        return false;
    }

    if ($ifNoneMatch === '*') {
        return true;
    }

    foreach (explode(',', $ifNoneMatch) as $candidate) {
        $candidate = trim($candidate);
        if (str_starts_with($candidate, 'W/')) {
            $candidate = trim(substr($candidate, 2));
        }
        if ($candidate === $etag) {
            return true;
        }
    }

    return false;
}

function sr_send_file_cache_headers(string $cacheControl, string $etag, int $lastModified): void
{
    header('Cache-Control: ' . sr_download_cache_control($cacheControl));
    header('ETag: ' . $etag);
    header('Last-Modified: ' . sr_http_date($lastModified));
}

function sr_file_not_modified(string $etag, int $lastModified): bool
{
    $ifNoneMatch = (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
    if ($ifNoneMatch !== '') {
        return sr_etag_matches($ifNoneMatch, $etag);
    }

    $ifModifiedSince = (string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');
    if ($ifModifiedSince === '') {
        return false;
    }

    $modifiedSince = strtotime($ifModifiedSince);
    return is_int($modifiedSince) && $modifiedSince >= $lastModified;
}

function sr_linkify_plain_text_urls(string $value): string
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
            $html .= '<a href="' . $escapedUrl . '" rel="nofollow noopener noreferrer">' . $escapedUrl . '</a>';
            $html .= sr_e($trailing);
        } else {
            $html .= sr_e((string) $match[0]);
        }
        $offset = $matchOffset + strlen((string) $match[0]);
    }

    return $html . sr_e(substr($value, $offset));
}

function sr_plain_text_html(string $value, bool $linkUrls = false): string
{
    if ($linkUrls) {
        return nl2br(sr_linkify_plain_text_urls($value), false);
    }

    return nl2br(sr_e($value), false);
}

function sr_rich_text_allowed_html_tags(): array
{
    return [
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        'u' => [],
        's' => [],
        'blockquote' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'a' => ['href'],
        'h2' => [],
        'h3' => [],
        'span' => ['class', 'data-sr-embed-manager-ref', 'data-sr-embed-manager-target-module', 'data-sr-embed-manager-target-type', 'data-sr-embed-manager-target-id', 'data-sr-embed-manager-variant', 'data-sr-embed-manager-label'],
        'img' => ['src', 'alt', 'width', 'height'],
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
    $config->set('HTML.Allowed', 'p,br,strong,em,u,s,blockquote,ul,ol,li,a[href|rel],h2,h3,span[class|data-sr-embed-manager-ref|data-sr-embed-manager-target-module|data-sr-embed-manager-target-type|data-sr-embed-manager-target-id|data-sr-embed-manager-variant|data-sr-embed-manager-label],img[src|alt|width|height]');
    $config->set('Attr.AllowedClasses', ['sr-embed-manager-marker']);
    $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true]);
    $config->set('HTML.Nofollow', true);
    $config->set('HTML.TargetBlank', false);

    $cacheDir = sr_rich_text_purifier_cache_dir();
    if ($cacheDir !== '') {
        $config->set('Cache.SerializerPath', $cacheDir);
    } else {
        $config->set('Cache.DefinitionImpl', null);
    }

    $config->set('HTML.DefinitionID', 'saanraan-rich-text');
    $config->set('HTML.DefinitionRev', 1);
    $definition = $config->maybeGetRawHTMLDefinition();
    if ($definition !== null) {
        foreach (['data-sr-embed-manager-ref', 'data-sr-embed-manager-target-module', 'data-sr-embed-manager-target-type', 'data-sr-embed-manager-target-id', 'data-sr-embed-manager-variant', 'data-sr-embed-manager-label'] as $attributeName) {
            $definition->addAttribute('span', $attributeName, 'Text');
        }
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

    if ($tagName === 'br') {
        return '<br>';
    }

    $attributes = sr_sanitize_rich_text_html_attributes($node, $tagName, $allowedTags[$tagName]);
    if ($tagName === 'a' && $attributes === '') {
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
        } elseif ($tagName === 'span' && $attributeName === 'class') {
            $classes = preg_split('/\s+/', $value) ?: [];
            $allowedClasses = [];
            foreach ($classes as $className) {
                if ($className === 'sr-embed-manager-marker') {
                    $allowedClasses[] = $className;
                }
            }
            if ($allowedClasses === []) {
                continue;
            }
            $value = implode(' ', $allowedClasses);
        } elseif ($tagName === 'span' && $attributeName === 'data-sr-embed-manager-ref') {
            if (preg_match('/\Aem_[a-z0-9_]{6,70}\z/', $value) !== 1) {
                continue;
            }
        } elseif ($tagName === 'span' && in_array($attributeName, ['data-sr-embed-manager-target-module', 'data-sr-embed-manager-target-type', 'data-sr-embed-manager-variant'], true)) {
            if (preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) !== 1) {
                continue;
            }
        } elseif ($tagName === 'span' && $attributeName === 'data-sr-embed-manager-target-id') {
            if (preg_match('/\A[1-9][0-9]{0,19}\z/', $value) !== 1) {
                continue;
            }
        } elseif ($tagName === 'span' && $attributeName === 'data-sr-embed-manager-label') {
            $value = preg_replace('/\s+/', ' ', $value) ?? '';
            $value = function_exists('mb_substr') ? mb_substr($value, 0, 120) : substr($value, 0, 120);
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

function sr_body_text_html(array $record, bool $linkPlainUrls = false): string
{
    $bodyText = (string) ($record['body_text'] ?? '');
    if ((string) ($record['body_format'] ?? 'plain') === 'html') {
        return sr_sanitize_rich_text_html($bodyText);
    }

    return sr_plain_text_html($bodyText, $linkPlainUrls);
}

function sr_body_text_is_empty(string $bodyText, string $bodyFormat): bool
{
    if ($bodyFormat !== 'html') {
        return trim($bodyText) === '';
    }

    $plainText = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', $bodyText)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    return $plainText === '';
}

function sr_editor_normalize_key(string $editorKey, bool $allowInherit = false): string
{
    $editorKey = strtolower(trim($editorKey));
    if ($allowInherit && $editorKey === 'inherit') {
        return 'inherit';
    }

    return preg_match('/\A[a-z][a-z0-9_]{0,39}\z/', $editorKey) === 1 ? $editorKey : 'textarea';
}

function sr_editor_contract_module_keys(?PDO $pdo): array
{
    if ($pdo instanceof PDO) {
        return array_keys(sr_enabled_module_contract_files($pdo, 'editor-options.php'));
    }

    $moduleKeys = [];
    foreach (glob(SR_ROOT . '/modules/*/editor-options.php') ?: [] as $file) {
        $moduleKey = basename(dirname($file));
        if (sr_is_safe_module_key($moduleKey)) {
            $moduleKeys[] = $moduleKey;
        }
    }

    sort($moduleKeys);
    return $moduleKeys;
}

function sr_editor_contracts(?PDO $pdo = null): array
{
    $contracts = [];
    foreach (sr_editor_contract_module_keys($pdo) as $moduleKey) {
        $file = SR_ROOT . '/modules/' . $moduleKey . '/editor-options.php';
        $contract = is_file($file) ? require $file : null;
        if (!is_array($contract)) {
            continue;
        }

        $editorKey = sr_editor_normalize_key((string) ($contract['key'] ?? ''));
        if ($editorKey === 'textarea' || isset($contracts[$editorKey])) {
            continue;
        }

        $helpers = (string) ($contract['helpers'] ?? '');
        if ($helpers !== '' && preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) === 1) {
            $helperPath = SR_ROOT . '/modules/' . $moduleKey . '/' . $helpers;
            if (is_file($helperPath)) {
                require_once $helperPath;
            }
        }

        $contracts[$editorKey] = [
            'module_key' => $moduleKey,
            'label' => (string) ($contract['label'] ?? $editorKey),
            'assets_function' => (string) ($contract['assets_function'] ?? ''),
        ];
    }

    return $contracts;
}

function sr_editor_available(PDO $pdo, string $editorKey): bool
{
    $editorKey = sr_editor_normalize_key($editorKey);
    if ($editorKey === 'textarea') {
        return true;
    }

    return isset(sr_editor_contracts($pdo)[$editorKey]);
}

function sr_editor_effective_key(PDO $pdo, string $editorKey): string
{
    $editorKey = sr_editor_normalize_key($editorKey);
    return sr_editor_available($pdo, $editorKey) ? $editorKey : 'textarea';
}

function sr_editor_options(PDO $pdo, bool $allowInherit = false): array
{
    $options = $allowInherit ? ['inherit' => '상위 설정 사용'] : [];
    $options['textarea'] = '기본 textarea';
    foreach (sr_editor_contracts($pdo) as $editorKey => $contract) {
        $options[(string) $editorKey] = (string) ($contract['label'] ?? $editorKey);
    }

    return $options;
}

function sr_editor_textarea_attributes(PDO $pdo, string $editorKey, string $presetKey = 'default', string $formatFieldName = 'body_format'): string
{
    $editorKey = sr_editor_effective_key($pdo, $editorKey);
    if ($editorKey === 'textarea') {
        return '';
    }

    return ' data-sr-editor="' . sr_e($editorKey) . '" data-sr-editor-preset="' . sr_e($presetKey) . '" data-sr-editor-format-name="' . sr_e($formatFieldName) . '"';
}

function sr_editor_assets_html(PDO $pdo, string $editorKey, string $presetKey = 'default'): string
{
    $editorKey = sr_editor_effective_key($pdo, $editorKey);
    if ($editorKey === 'textarea') {
        return '';
    }

    $contract = sr_editor_contracts($pdo)[$editorKey] ?? [];
    $assetsFunction = (string) ($contract['assets_function'] ?? '');
    return function_exists($assetsFunction) ? (string) $assetsFunction($pdo, $presetKey) : '';
}

function sr_material_icon_stylesheet_url(): string
{
    return '';
}

function sr_material_icon_font_url(): string
{
    return sr_url('/assets/fonts/material-symbols-outlined.ttf');
}

function sr_icon_stylesheet_tags(): string
{
    return '';
}

function sr_material_icon_name(string $name): string
{
    $name = trim($name);

    return preg_match('/\A[a-z0-9_]+\z/', $name) === 1 ? $name : 'help';
}

function sr_material_icon_class_attr(string $class): string
{
    return sr_ui_icon_class_attr($class);
}

function sr_ui_icon_class_attr(string $class): string
{
    $tokens = [];
    foreach (preg_split('/\s+/', trim($class)) ?: [] as $token) {
        if (preg_match('/\A[a-zA-Z0-9_-]+\z/', $token) === 1) {
            $tokens[] = $token;
        }
    }

    return implode(' ', $tokens);
}

function sr_ui_arrow_icon_paths(): array
{
    return [
        'down' => 'M5 7.5l5 5l5 -5',
        'up' => 'M5 12.5l5 -5l5 5',
        'left' => 'M12.5 5l-5 5l5 5',
        'right' => 'M7.5 5l5 5l-5 5',
    ];
}

function sr_ui_arrow_icon_html(string $direction = 'down', string $class = '', string $label = ''): string
{
    $paths = sr_ui_arrow_icon_paths();
    $direction = isset($paths[$direction]) ? $direction : 'down';
    $classes = trim('ui-arrow-icon ' . sr_ui_icon_class_attr($class));
    $label = trim($label);
    $accessibility = $label === ''
        ? ' aria-hidden="true"'
        : ' role="img" aria-label="' . sr_e($label) . '"';

    return '<svg class="' . sr_e($classes) . '" data-ui-arrow="' . sr_e($direction) . '" viewBox="0 0 20 20"' . $accessibility . ' focusable="false"><path d="' . sr_e($paths[$direction]) . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
}

function sr_material_icon_html(string $name, string $class = '', string $label = '', string $id = ''): string
{
    return sr_icon($name, $class, $label, $id);
}

function sr_icon(string $name, string $class = '', string $label = '', string $id = ''): string
{
    $classes = trim('sr-icon material-symbols-outlined ' . sr_material_icon_class_attr($class));
    $iconName = sr_material_icon_name($name);
    $label = trim($label);
    $accessibility = $label === ''
        ? ' aria-hidden="true"'
        : ' role="img" aria-label="' . sr_e($label) . '"';
    $idAttribute = preg_match('/\A[a-zA-Z][a-zA-Z0-9_-]*\z/', $id) === 1
        ? ' id="' . sr_e($id) . '"'
        : '';

    return '<span class="' . sr_e($classes) . '"' . $idAttribute . ' data-sr-material-icon' . $accessibility . '>' . sr_e($iconName) . '</span>';
}

function sr_material_icon_bootstrap_script(): string
{
    return sr_icon_bootstrap_script();
}

function sr_icon_bootstrap_script(): string
{
    return '<script>(function(){var r=document.documentElement;function y(){r.classList.add("sr-material-icons-ready")}function n(){r.classList.add("sr-material-icons-unavailable");y()}if(document.fonts&&document.fonts.load){document.fonts.load("24px \\"Material Symbols Outlined\\"").then(y,function(){if(document.fonts.ready){document.fonts.ready.then(y,n)}else{n()}})}else{y()}})();</script>';
}

function sr_public_style_profile_paths(string $profile): array
{
    $profile = strtolower(trim($profile));
    $profile = sr_public_style_profile_key($profile);

    if ($profile === 'module') {
        return [];
    }

    $paths = [
        '/assets/reset.css',
    ];

    if ($profile === 'kit') {
        $paths[] = '/assets/ui-kit.css';
    }

    return $paths;
}

function sr_public_style_profile_key(string $profile): string
{
    $profile = strtolower(trim($profile));

    return in_array($profile, ['minimal', 'kit', 'install', 'module'], true) ? $profile : 'kit';
}

function sr_stylesheet_tag(array $stylesheets = [], ?PDO $pdo = null, array $options = []): string
{
    $tags = [
        '<link rel="preload" as="style" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" crossorigin>',
        '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css" crossorigin>',
        sr_icon_stylesheet_tags(),
    ];

    $profile = is_string($options['style_profile'] ?? null) ? sr_public_style_profile_key((string) $options['style_profile']) : 'kit';
    $stylesheetPaths = [];

    foreach (sr_public_style_profile_paths($profile) as $stylesheet) {
        $stylesheetPaths[$stylesheet] = $stylesheet;
    }

    foreach ($stylesheets as $stylesheet) {
        if (!is_string($stylesheet) || !sr_is_safe_relative_url($stylesheet)) {
            continue;
        }

        $stylesheetPaths[$stylesheet] = $stylesheet;
    }

    foreach ($stylesheetPaths as $stylesheet) {
        $tags[] = '<link rel="stylesheet" href="' . sr_e(sr_asset_url($stylesheet)) . '">';
    }

    return implode(PHP_EOL, array_values(array_filter($tags, 'strlen')));
}

function sr_script_tags(array $scripts = []): string
{
    $tags = [];
    $scriptPaths = [];

    foreach ($scripts as $script) {
        if (!is_string($script) || !sr_is_safe_relative_url($script)) {
            continue;
        }

        $scriptPaths[$script] = $script;
    }

    foreach ($scriptPaths as $script) {
        $tags[] = '<script src="' . sr_e(sr_asset_url($script)) . '" defer></script>';
    }

    return implode(PHP_EOL, $tags);
}

function sr_asset_url(string $path): string
{
    $url = sr_url($path);
    if (!str_starts_with($path, '/')) {
        return $url;
    }

    $file = SR_ROOT . $path;
    if (!is_file($file)) {
        return $url;
    }

    return $url . '?v=' . rawurlencode((string) filemtime($file));
}

function sr_color_scheme_options(): array
{
    return [
        'light' => '라이트',
        'dark' => '다크',
        'system' => '시스템 설정',
    ];
}

function sr_color_scheme(?array $site = null): string
{
    $colorScheme = (string) (($site ?? [])['ui_color_scheme'] ?? 'light');

    return isset(sr_color_scheme_options()[$colorScheme]) ? $colorScheme : 'light';
}

function sr_public_layout_default_key(): string
{
    return 'common.basic';
}

function sr_public_layout_legacy_key_map(): array
{
    return [
        'basic' => sr_public_layout_default_key(),
    ];
}

function sr_public_layout_normalize_key(string $layoutKey): string
{
    $layoutKey = trim($layoutKey);
    $legacyMap = sr_public_layout_legacy_key_map();

    return (string) ($legacyMap[$layoutKey] ?? $layoutKey);
}

function sr_public_layout_module_stylesheet(string $layoutKey): string
{
    $layoutKey = sr_public_layout_normalize_key($layoutKey);
    $providerKey = strtok($layoutKey, '.');
    $providerKey = is_string($providerKey) ? $providerKey : '';
    if ($providerKey === '' || $providerKey === 'common' || $providerKey === 'core') {
        return '';
    }
    if (!sr_is_safe_module_key($providerKey)) {
        return '';
    }

    $path = '/modules/' . $providerKey . '/assets/layout.css';
    return is_file(SR_ROOT . $path) ? $path : '';
}

function sr_public_layout_options(?PDO $pdo = null, bool $includeInstalledModules = false): array
{
    $cache = $GLOBALS['sr_public_layout_options_runtime_cache'] ?? [];
    if (!is_array($cache)) {
        $cache = [];
    }

    $cacheKey = implode(':', [
        $pdo instanceof PDO ? (string) spl_object_id($pdo) : 'no-pdo',
        $includeInstalledModules ? 'installed' : 'enabled',
        function_exists('sr_locale') ? sr_locale() : 'ko',
        defined('SR_MODULE_CONTRACT_VERSION') ? SR_MODULE_CONTRACT_VERSION : 'contract-unknown',
    ]);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $options = [
        sr_public_layout_default_key() => [
            'key' => sr_public_layout_default_key(),
            'label' => sr_t('public_layout.common.label'),
            'provider_module_key' => 'core',
            'provider_label' => sr_t('public_layout.common.provider'),
            'supports' => ['site'],
            'style_profile' => 'kit',
            'views' => [
                'layout' => SR_ROOT . '/layouts/public/basic/layout.php',
                'home' => SR_ROOT . '/layouts/public/basic/home.php',
                'ui_kit' => SR_ROOT . '/layouts/public/basic/ui-kit.php',
            ],
        ],
    ];

    if ($pdo instanceof PDO) {
        $contractFiles = $includeInstalledModules
            ? sr_installed_module_contract_files($pdo, 'layout-options.php')
            : sr_enabled_module_contract_files($pdo, 'layout-options.php');
        foreach ($contractFiles as $moduleKey => $file) {
            $moduleOptions = sr_load_module_contract_file($moduleKey, $file);
            if (!is_array($moduleOptions)) {
                continue;
            }

            foreach ($moduleOptions as $layoutKey => $layoutOption) {
                $layoutKey = is_string($layoutKey) ? sr_public_layout_normalize_key($layoutKey) : '';
                if (preg_match('/\A[a-z0-9][a-z0-9_]{0,39}\.[a-z0-9][a-z0-9_]{0,39}\z/', $layoutKey) !== 1 || !is_array($layoutOption)) {
                    continue;
                }

                $layoutOption['key'] = $layoutKey;
                $layoutOption['provider_module_key'] = (string) ($layoutOption['provider_module_key'] ?? $moduleKey);
                $options[$layoutKey] = $layoutOption;
            }
        }
    }

    $cache[$cacheKey] = sr_filter_view_options($options, ['layout'], 'public layout');
    $GLOBALS['sr_public_layout_options_runtime_cache'] = $cache;

    return $cache[$cacheKey];
}

function sr_public_layout_key(?array $site = null, ?PDO $pdo = null): string
{
    $layoutKey = is_array($site) ? (string) ($site['public_layout_key'] ?? sr_public_layout_default_key()) : sr_public_layout_default_key();
    $layoutKey = sr_public_layout_normalize_key($layoutKey);

    return isset(sr_public_layout_options($pdo)[$layoutKey]) ? $layoutKey : sr_public_layout_default_key();
}

function sr_public_layout_file(string $layoutKey, ?PDO $pdo = null, bool $includeInstalledModules = false): string
{
    $layoutKey = sr_public_layout_normalize_key($layoutKey);
    $options = sr_public_layout_options($pdo, $includeInstalledModules);
    if (!isset($options[$layoutKey])) {
        $layoutKey = sr_public_layout_default_key();
    }

    $layoutFile = (string) ($options[$layoutKey]['views']['layout'] ?? '');
    if ($layoutFile === '' || !is_file($layoutFile)) {
        $layoutFile = (string) ($options[sr_public_layout_default_key()]['views']['layout'] ?? '');
    }

    if ($layoutFile === '' || !is_file($layoutFile)) {
        throw new RuntimeException('기본 공개 레이아웃 파일이 누락되었습니다.');
    }

    return $layoutFile;
}

function sr_public_layout_optional_view_file(string $layoutKey, string $viewKey, ?PDO $pdo = null, bool $includeInstalledModules = false): ?string
{
    if (preg_match('/\A[a-z0-9_]{1,40}\z/', $viewKey) !== 1) {
        return null;
    }

    $layoutKey = sr_public_layout_normalize_key($layoutKey);
    $options = sr_public_layout_options($pdo, $includeInstalledModules);
    if (!isset($options[$layoutKey])) {
        $layoutKey = sr_public_layout_default_key();
    }

    $viewFile = (string) ($options[$layoutKey]['views'][$viewKey] ?? '');
    if ($viewFile !== '' && is_file($viewFile)) {
        return $viewFile;
    }

    $fallbackFile = (string) ($options[sr_public_layout_default_key()]['views'][$viewKey] ?? '');
    return $fallbackFile !== '' && is_file($fallbackFile) ? $fallbackFile : null;
}

function sr_filter_view_options(array $options, array $requiredViewKeys, string $label): array
{
    $validOptions = [];
    foreach ($options as $optionKey => $option) {
        if (!is_string($optionKey) || !is_array($option)) {
            continue;
        }

        if (!sr_view_option_has_required_views($option, $requiredViewKeys)) {
            error_log('[saanraan] ' . $label . ' required view is missing: key=' . $optionKey);
            continue;
        }

        $validOptions[$optionKey] = $option;
    }

    return $validOptions;
}

function sr_view_option_has_required_views(array $option, array $requiredViewKeys): bool
{
    $views = isset($option['views']) && is_array($option['views']) ? $option['views'] : [];
    foreach ($requiredViewKeys as $viewKey) {
        $view = (string) ($views[(string) $viewKey] ?? '');
        if ($view === '' || !is_file($view)) {
            return false;
        }
    }

    return true;
}

function sr_public_layout_begin(?PDO $pdo, ?array $site, array $seo = [], array $layoutContext = []): void
{
    $stack = $GLOBALS['sr_public_layout_stack'] ?? [];
    if (!is_array($stack)) {
        $stack = [];
    }

    $stack[] = [
        'pdo' => $pdo,
        'site' => $site,
        'seo' => $seo,
        'layout_context' => $layoutContext,
    ];
    $GLOBALS['sr_public_layout_stack'] = $stack;

    ob_start();
}

function sr_public_layout_end(): void
{
    $contentHtml = ob_get_clean();
    $contentHtml = is_string($contentHtml) ? $contentHtml : '';

    $stack = $GLOBALS['sr_public_layout_stack'] ?? [];
    if (!is_array($stack) || $stack === []) {
        echo $contentHtml;
        return;
    }

    $layoutState = array_pop($stack);
    $GLOBALS['sr_public_layout_stack'] = $stack;

    $pdo = $layoutState['pdo'] ?? null;
    $site = is_array($layoutState['site'] ?? null) ? $layoutState['site'] : null;
    $seo = is_array($layoutState['seo'] ?? null) ? $layoutState['seo'] : [];
    if ($pdo instanceof PDO) {
        $seo = sr_site_apply_public_meta_defaults($pdo, $seo);
    }
    if ($pdo instanceof PDO && is_file(SR_ROOT . '/modules/seo/helpers.php')) {
        require_once SR_ROOT . '/modules/seo/helpers.php';
        $seo = sr_seo_apply_public_defaults($pdo, $seo);
    }
    $layoutContext = is_array($layoutState['layout_context'] ?? null) ? $layoutState['layout_context'] : [];
    $layoutKey = (string) ($layoutContext['layout_key'] ?? '');
    if ($layoutKey === '') {
        $layoutKey = sr_public_layout_key($site, $pdo instanceof PDO ? $pdo : null);
    } else {
        $layoutKey = sr_public_layout_normalize_key($layoutKey);
    }
    $includeInstalledLayoutOptions = !empty($layoutContext['include_installed_layout_options']);
    $layoutFile = sr_public_layout_file($layoutKey, $pdo instanceof PDO ? $pdo : null, $includeInstalledLayoutOptions);
    $layoutModuleStylesheet = sr_public_layout_module_stylesheet($layoutKey);
    if (!isset($layoutContext['style_profile'])) {
        $layoutOptions = sr_public_layout_options($pdo instanceof PDO ? $pdo : null, $includeInstalledLayoutOptions);
        $layoutProfile = (string) ($layoutOptions[$layoutKey]['style_profile'] ?? 'kit');
        if ($layoutProfile === 'minimal' && $layoutModuleStylesheet !== '') {
            $layoutProfile = 'kit';
        }
        $layoutContext['style_profile'] = sr_public_style_profile_key($layoutProfile);
    }
    if ($layoutModuleStylesheet !== '') {
        $layoutStylesheets = is_array($layoutContext['stylesheets'] ?? null) ? $layoutContext['stylesheets'] : [];
        if (!in_array($layoutModuleStylesheet, $layoutStylesheets, true)) {
            array_unshift($layoutStylesheets, $layoutModuleStylesheet);
        }
        $layoutContext['stylesheets'] = $layoutStylesheets;
    }

    include $layoutFile;
}

function sr_pwa_head_tags(?PDO $pdo, ?array $site): string
{
    return '<link rel="manifest" href="' . sr_e(sr_url('/manifest.webmanifest')) . '">' . "\n"
        . '    <meta name="theme-color" content="' . sr_e(sr_pwa_theme_color($pdo, $site)) . '">' . "\n"
        . '    <meta name="mobile-web-app-capable" content="yes">' . "\n"
        . '    <meta name="apple-mobile-web-app-capable" content="yes">';
}

function sr_pwa_registration_script(): string
{
    $serviceWorkerUrl = sr_url('/service-worker.js');
    $scopeUrl = sr_url('/');
    return '<script>(function(){if(!("serviceWorker" in navigator)){return;}window.addEventListener("load",function(){navigator.serviceWorker.register('
        . sr_js_json_encode($serviceWorkerUrl)
        . ',{scope:'
        . sr_js_json_encode($scopeUrl)
        . '}).catch(function(){});});})();</script>';
}

function sr_pwa_theme_color(?PDO $pdo, ?array $site): string
{
    return '#111827';
}

function sr_pwa_manifest_payload(?PDO $pdo, ?array $site): array
{
    $siteName = sr_site_display_name($site, $pdo);

    return [
        'name' => $siteName,
        'short_name' => function_exists('mb_substr') ? mb_substr($siteName, 0, 12) : substr($siteName, 0, 12),
        'description' => $siteName,
        'start_url' => sr_url('/'),
        'scope' => sr_url('/'),
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => sr_pwa_theme_color($pdo, $site),
        'icons' => [
            [
                'src' => sr_url('/assets/pwa-icon.svg'),
                'sizes' => 'any',
                'type' => 'image/svg+xml',
                'purpose' => 'any maskable',
            ],
        ],
    ];
}

function sr_pwa_service_worker_source(): string
{
    $basePath = sr_base_path();
    $assetPrefix = rtrim($basePath, '/') . '/assets/';
    $moduleAssetPattern = '^' . preg_quote(rtrim($basePath, '/') . '/modules/', '#') . '[a-z][a-z0-9_]{1,39}/assets/';
    $ckeditorJs = rtrim($basePath, '/') . '/modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js';
    $ckeditorCss = rtrim($basePath, '/') . '/modules/ckeditor/vendor/ckeditor5/ckeditor5.css';

    return '"use strict";' . "\n"
        . 'const SR_PWA_CACHE = "saanraan-static-v1";' . "\n"
        . 'const SR_ASSET_PREFIX = ' . sr_js_json_encode($assetPrefix) . ';' . "\n"
        . 'const SR_MODULE_ASSET_RE = new RegExp(' . sr_js_json_encode($moduleAssetPattern) . ');' . "\n"
        . 'const SR_CKEDITOR_ASSETS = new Set([' . sr_js_json_encode($ckeditorJs) . ',' . sr_js_json_encode($ckeditorCss) . ']);' . "\n"
        . 'self.addEventListener("install", function(event) { self.skipWaiting(); });' . "\n"
        . 'self.addEventListener("activate", function(event) { event.waitUntil(self.clients.claim()); });' . "\n"
        . 'function srShouldCache(request) {' . "\n"
        . '  if (request.method !== "GET" || request.mode === "navigate") { return false; }' . "\n"
        . '  const url = new URL(request.url);' . "\n"
        . '  if (url.origin !== self.location.origin) { return false; }' . "\n"
        . '  if (url.pathname.indexOf("/admin") === 0 || url.pathname.indexOf("/account/privacy") === 0 || url.pathname.indexOf("/storage") === 0) { return false; }' . "\n"
        . '  return url.pathname.indexOf(SR_ASSET_PREFIX) === 0 || SR_MODULE_ASSET_RE.test(url.pathname) || SR_CKEDITOR_ASSETS.has(url.pathname);' . "\n"
        . '}' . "\n"
        . 'self.addEventListener("fetch", function(event) {' . "\n"
        . '  if (!srShouldCache(event.request)) { return; }' . "\n"
        . '  event.respondWith(caches.match(event.request).then(function(cached) {' . "\n"
        . '    return fetch(event.request).then(function(response) {' . "\n"
        . '      const copy = response.clone();' . "\n"
        . '      if (response.ok && response.type === "basic") {' . "\n"
        . '        caches.open(SR_PWA_CACHE).then(function(cache) { cache.put(event.request, copy); });' . "\n"
        . '      }' . "\n"
        . '      return response;' . "\n"
        . '    }).catch(function() {' . "\n"
        . '      return cached || Response.error();' . "\n"
        . '    });' . "\n"
        . '  }));' . "\n"
        . '});' . "\n";
}

function sr_public_layout_member_action_rows(PDO $pdo, int $accountId): array
{
    if ($accountId <= 0) {
        return [];
    }

    $rows = [];

    foreach (sr_enabled_module_contract_files($pdo, 'member-action-rows.php') as $moduleKey => $contractFile) {
        $contract = sr_load_module_contract_file($moduleKey, $contractFile);
        $provider = is_callable($contract) ? $contract : ($contract['rows_function'] ?? null);
        if (!is_callable($provider)) {
            continue;
        }

        try {
            $moduleRows = $provider($pdo, $accountId);
        } catch (Throwable $exception) {
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'public_member_action_rows_' . $moduleKey);
            }
            continue;
        }

        if (!is_array($moduleRows)) {
            continue;
        }

        foreach ($moduleRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            if ($label === '' || $value === '' || !sr_is_safe_relative_url($url)) {
                continue;
            }

            $rows[] = [
                'label' => $label,
                'value' => $value,
                'url' => sr_url($url),
            ];
        }
    }

    return $rows;
}

function sr_render_output_slot(PDO $pdo, array $context): string
{
    $moduleKey = (string) ($context['module_key'] ?? '');
    $pointKey = (string) ($context['point_key'] ?? '');
    $slotKey = (string) ($context['slot_key'] ?? '');

    if (
        !sr_is_safe_module_key($moduleKey)
        || preg_match('/\A[a-z0-9][a-z0-9_.-]{0,119}\z/', $pointKey) !== 1
        || preg_match('/\A[a-z0-9][a-z0-9_.-]{0,79}\z/', $slotKey) !== 1
    ) {
        return '';
    }

    $context['module_key'] = $moduleKey;
    $context['point_key'] = $pointKey;
    $context['slot_key'] = $slotKey;

    $output = [];
    foreach (sr_output_slot_renderer_contracts($pdo, $moduleKey) as $rendererContract) {
        $rendererModuleKey = (string) ($rendererContract['module_key'] ?? '');
        $file = (string) ($rendererContract['contract_file'] ?? '');
        $renderer = sr_load_module_contract_file($rendererModuleKey, $file);
        if (!is_callable($renderer)) {
            continue;
        }

        try {
            $rendered = $renderer($pdo, $context);
            if (is_string($rendered) && $rendered !== '') {
                $output[] = $rendered;
            }
        } catch (Throwable $exception) {
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'module_output_slot_failed_' . $rendererModuleKey);
            }
        }
    }

    return implode("\n", $output);
}

function sr_output_slot_renderer_contracts(PDO $pdo, string $excludedModuleKey): array
{
    $excludedModuleKey = sr_is_safe_module_key($excludedModuleKey) ? $excludedModuleKey : '';
    $cache = $GLOBALS['sr_output_slot_renderer_contracts_runtime_cache'] ?? [];
    if (!is_array($cache)) {
        $cache = [];
    }

    $contractName = 'output-slots.php';
    $contractVersion = defined('SR_MODULE_CONTRACT_VERSION') ? SR_MODULE_CONTRACT_VERSION : 'contract-unknown';
    $cacheKey = implode(':', [
        (string) spl_object_id($pdo),
        $contractName,
        $excludedModuleKey,
        $contractVersion,
    ]);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    // Keep this cache value serializable. A future shared cache adapter may reuse
    // this key/value shape, so store contract metadata only.
    $contracts = [];
    foreach (sr_enabled_module_contract_files($pdo, 'output-slots.php', $excludedModuleKey !== '' ? [$excludedModuleKey] : []) as $rendererModuleKey => $file) {
        $contracts[] = [
            'module_key' => (string) $rendererModuleKey,
            'contract_name' => $contractName,
            'contract_version' => $contractVersion,
            'contract_file' => (string) $file,
        ];
    }

    $cache[$cacheKey] = $contracts;
    $GLOBALS['sr_output_slot_renderer_contracts_runtime_cache'] = $cache;

    return $contracts;
}

function sr_url(string $path): string
{
    if (!sr_is_safe_relative_url($path)) {
        return sr_base_path() === '' ? '/' : sr_base_path() . '/';
    }

    $basePath = sr_base_path();
    if ($basePath === '' || $path === $basePath || str_starts_with($path, $basePath . '/')) {
        return $path;
    }

    return $basePath . $path;
}

function sr_canonical_url(?array $site, ?string $path = null): string
{
    $path = $path ?? sr_request_path();
    if (!sr_is_safe_relative_url($path)) {
        $path = '/';
    }

    return sr_absolute_url($site, $path);
}

function sr_is_safe_relative_url(string $url): bool
{
    if ($url === '' || $url[0] !== '/' || str_starts_with($url, '//')) {
        return false;
    }

    if (strpos($url, '\\') !== false) {
        return false;
    }

    return preg_match('/[\x00-\x1F\x7F]/', $url) !== 1;
}

function sr_seo_tags(array $seo = [], ?array $site = null): string
{
    $title = (string) ($seo['title'] ?? sr_site_display_name($site));
    $description = (string) ($seo['description'] ?? '');
    $canonical = (string) ($seo['canonical'] ?? sr_canonical_url($site));
    if (sr_is_safe_relative_url($canonical)) {
        $canonical = sr_absolute_url($site, $canonical);
    } elseif (!sr_is_http_url($canonical)) {
        $canonical = '';
    }

    $robots = (string) ($seo['robots'] ?? 'index, follow');
    $og = isset($seo['og']) && is_array($seo['og']) ? $seo['og'] : [];

    $tags = [];
    $tags[] = '<title>' . sr_e($title) . '</title>';

    if ($description !== '') {
        $tags[] = '<meta name="description" content="' . sr_e($description) . '">';
    }

    if ($canonical !== '') {
        $tags[] = '<link rel="canonical" href="' . sr_e($canonical) . '">';
    }

    if ($robots !== '') {
        $tags[] = '<meta name="robots" content="' . sr_e($robots) . '">';
    }

    $ogTitle = (string) ($og['title'] ?? $title);
    $ogDescription = (string) ($og['description'] ?? $description);
    $ogType = (string) ($og['type'] ?? 'website');
    $ogImage = (string) ($og['image'] ?? '');
    if (sr_is_safe_relative_url($ogImage)) {
        $ogImage = sr_absolute_url($site, $ogImage);
    } elseif ($ogImage !== '' && !sr_is_http_url($ogImage)) {
        $ogImage = '';
    }

    if ($ogTitle !== '') {
        $tags[] = '<meta property="og:title" content="' . sr_e($ogTitle) . '">';
    }

    if ($ogDescription !== '') {
        $tags[] = '<meta property="og:description" content="' . sr_e($ogDescription) . '">';
    }

    if ($canonical !== '') {
        $tags[] = '<meta property="og:url" content="' . sr_e($canonical) . '">';
    }

    if ($ogType !== '') {
        $tags[] = '<meta property="og:type" content="' . sr_e($ogType) . '">';
    }

    if ($ogImage !== '') {
        $tags[] = '<meta property="og:image" content="' . sr_e($ogImage) . '">';
    }

    return implode("\n    ", $tags);
}

function sr_redirect(string $url): void
{
    if (!sr_is_safe_relative_url($url)) {
        sr_render_error(500, sr_t('error.redirect_invalid'));
    }

    sr_enforce_request_contract('before_redirect');

    header('Location: ' . sr_url($url), true, 302);
    sr_finish_response();
}

function sr_redirect_external(string $url): void
{
    if (!sr_is_public_http_url($url)) {
        sr_render_error(500, sr_t('error.external_redirect_invalid'));
    }

    sr_enforce_request_contract('before_redirect');

    header('Location: ' . $url, true, 302);
    sr_finish_response();
}

function sr_redirect_trusted_external(string $url): void
{
    if (!sr_is_http_url($url)) {
        sr_render_error(500, sr_t('error.external_redirect_invalid'));
    }

    sr_enforce_request_contract('before_redirect');

    header('Location: ' . $url, true, 302);
    sr_finish_response();
}

function sr_finish_response(): void
{
    sr_enforce_request_contract('before_response_end');
    exit;
}

function sr_csrf_token(): string
{
    if (empty($_SESSION['sr_csrf_token']) || !is_string($_SESSION['sr_csrf_token'])) {
        $_SESSION['sr_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['sr_csrf_token'];
}

function sr_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . sr_e(sr_csrf_token()) . '">';
}

function sr_require_csrf(): void
{
    sr_request_contract_mark('csrf_checked');

    $expected = $_SESSION['sr_csrf_token'] ?? '';
    $actual = $_POST['csrf_token'] ?? '';

    if (!is_string($expected) || !is_string($actual) || $expected === '' || !hash_equals($expected, $actual)) {
        sr_request_contract_guard_blocked('csrf');
        sr_render_error(400, sr_t('error.csrf_invalid'));
    }
}

function sr_post_string(string $key, int $maxLength): string
{
    $value = $_POST[$key] ?? '';
    if (is_array($value)) {
        return '';
    }

    $value = trim((string) $value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function sr_post_string_without_truncation(string $key, int $maxLength): ?string
{
    $value = $_POST[$key] ?? '';
    if (is_array($value)) {
        return null;
    }

    $value = trim((string) $value);
    return strlen($value) <= $maxLength ? $value : null;
}

function sr_get_string(string $key, int $maxLength): string
{
    $value = $_GET[$key] ?? '';
    if (is_array($value)) {
        return '';
    }

    $value = trim((string) $value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function sr_get_string_without_truncation(string $key, int $maxLength): ?string
{
    $value = $_GET[$key] ?? '';
    if (is_array($value)) {
        return null;
    }

    $value = trim((string) $value);
    return strlen($value) <= $maxLength ? $value : null;
}

function sr_send_download_headers(string $contentType, string $filename, string $disposition = 'attachment', ?int $contentLength = null, string $cacheControl = 'no-store, no-cache, must-revalidate'): void
{
    header('Content-Type: ' . sr_download_content_type($contentType));
    header('Content-Disposition: ' . sr_download_content_disposition($filename, $disposition));
    if ($contentLength !== null && $contentLength > 0) {
        header('Content-Length: ' . (string) $contentLength);
    }
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: ' . sr_download_cache_control($cacheControl));
    header('Pragma: no-cache');
}

function sr_send_file_headers(string $contentType, ?int $contentLength = null, string $cacheControl = 'private, max-age=300', array $headers = []): void
{
    header('Content-Type: ' . sr_download_content_type($contentType));
    if ($contentLength !== null && $contentLength > 0) {
        header('Content-Length: ' . (string) $contentLength);
    }
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: ' . sr_download_cache_control($cacheControl));
    foreach ($headers as $header) {
        if (is_string($header) && sr_response_header_is_allowed($header)) {
            header($header);
        }
    }
}

function sr_download_content_type(string $contentType): string
{
    $contentType = trim($contentType);
    if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9.+-]*\/[A-Za-z0-9][A-Za-z0-9.+-]*(?:;\s*charset=[A-Za-z0-9._-]+)?\z/', $contentType) !== 1) {
        return 'application/octet-stream';
    }

    return $contentType;
}

function sr_download_filename(string $filename): string
{
    $filename = str_replace(['\\', '/'], '-', $filename);
    $filename = preg_replace('/[\x00-\x1F\x7F]+/', '-', $filename);
    $filename = is_string($filename) ? $filename : '';
    $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename);
    $filename = is_string($filename) ? preg_replace('/-+/', '-', $filename) : '';
    $filename = is_string($filename) ? trim($filename, '.-_') : '';

    if ($filename === '') {
        return 'download.bin';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($filename, 0, 120);
    }

    return substr($filename, 0, 120);
}

function sr_download_content_disposition(string $filename, string $disposition = 'attachment'): string
{
    $disposition = strtolower(trim($disposition));
    if (!in_array($disposition, ['attachment', 'inline'], true)) {
        $disposition = 'attachment';
    }

    return $disposition . '; filename="' . sr_download_filename($filename) . '"';
}

function sr_download_cache_control(string $cacheControl): string
{
    $cacheControl = trim($cacheControl);
    if ($cacheControl === '' || preg_match('/[\x00-\x1F\x7F]/', $cacheControl) === 1) {
        return 'no-store, no-cache, must-revalidate';
    }

    if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9\s,=_\-;]*\z/', $cacheControl) !== 1) {
        return 'no-store, no-cache, must-revalidate';
    }

    return $cacheControl;
}

function sr_absolute_url(?array $site, string $path): string
{
    if (!sr_is_safe_relative_url($path)) {
        $path = '/';
    }

    $baseUrl = is_array($site) ? rtrim((string) ($site['base_url'] ?? ''), '/') : '';
    if ($baseUrl === '' || !sr_is_site_base_url($baseUrl)) {
        return sr_url($path);
    }

    return $baseUrl . '/' . ltrim($path, '/');
}
