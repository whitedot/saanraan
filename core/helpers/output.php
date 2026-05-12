<?php

declare(strict_types=1);

function toy_set_locale(string $locale): void
{
    if (preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $locale) !== 1) {
        $locale = 'ko';
    }

    $GLOBALS['toy_locale'] = $locale;
}

function toy_locale(): string
{
    $locale = $GLOBALS['toy_locale'] ?? 'ko';
    return is_string($locale) && $locale !== '' ? $locale : 'ko';
}

function toy_resolve_locale(PDO $pdo, ?array $site): string
{
    $supportedLocales = toy_supported_locales($site);
    $accountId = $_SESSION['toy_account_id'] ?? null;
    if (is_int($accountId) || ctype_digit((string) $accountId)) {
        try {
            $stmt = $pdo->prepare('SELECT locale FROM toy_member_accounts WHERE id = :id LIMIT 1');
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

function toy_supported_locales(?array $site): array
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

function toy_t(string $key, array $params = [], ?string $locale = null): string
{
    $locale = $locale ?? toy_locale();
    $moduleKey = '';
    $translationKey = $key;

    if (strpos($key, '::') !== false) {
        [$moduleKey, $translationKey] = explode('::', $key, 2);
    }

    $translations = toy_load_translations($locale, $moduleKey);
    $message = $translations[$translationKey] ?? null;

    if (!is_string($message) && $locale !== toy_fallback_locale()) {
        $fallbackTranslations = toy_load_translations(toy_fallback_locale(), $moduleKey);
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

function toy_fallback_locale(): string
{
    return 'ko';
}

function toy_load_translations(string $locale, string $moduleKey = ''): array
{
    static $cache = [];

    if (preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $locale) !== 1) {
        $locale = 'ko';
    }

    if ($moduleKey !== '' && !toy_is_safe_module_key($moduleKey)) {
        return [];
    }

    $cacheKey = $moduleKey . '|' . $locale;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $file = $moduleKey === ''
        ? TOY_ROOT . '/lang/' . $locale . '/core.php'
        : TOY_ROOT . '/modules/' . $moduleKey . '/lang/' . $locale . '.php';

    if (!is_file($file)) {
        $cache[$cacheKey] = [];
        return [];
    }

    $translations = include $file;
    $cache[$cacheKey] = is_array($translations) ? $translations : [];

    return $cache[$cacheKey];
}

function toy_is_safe_module_action(string $path): bool
{
    if ($path === '' || strpos($path, '..') !== false || strpos($path, '\\') !== false) {
        return false;
    }

    return preg_match('/\Aactions\/[a-z0-9_\-\/]+\.php\z/', $path) === 1;
}

function toy_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function toy_stylesheet_tag(): string
{
    return '<link rel="stylesheet" href="' . toy_e(toy_url('/assets/toycore.css')) . '">';
}

function toy_color_scheme_options(): array
{
    return [
        'light' => '라이트',
        'dark' => '다크',
        'system' => '시스템 설정',
    ];
}

function toy_color_scheme(?array $site = null): string
{
    $colorScheme = is_array($site) ? (string) ($site['ui_color_scheme'] ?? 'light') : 'light';
    return isset(toy_color_scheme_options()[$colorScheme]) ? $colorScheme : 'light';
}

function toy_public_layout_options(): array
{
    return [
        'basic' => [
            'label' => '기본 레이아웃',
            'file' => TOY_ROOT . '/layouts/public/basic/layout.php',
        ],
    ];
}

function toy_public_layout_key(?array $site = null): string
{
    $layoutKey = is_array($site) ? (string) ($site['public_layout_key'] ?? 'basic') : 'basic';
    return isset(toy_public_layout_options()[$layoutKey]) ? $layoutKey : 'basic';
}

function toy_public_layout_file(string $layoutKey): string
{
    $options = toy_public_layout_options();
    if (!isset($options[$layoutKey])) {
        $layoutKey = 'basic';
    }

    $layoutFile = (string) ($options[$layoutKey]['file'] ?? '');
    if ($layoutFile === '' || !is_file($layoutFile)) {
        $layoutFile = (string) $options['basic']['file'];
    }

    return $layoutFile;
}

function toy_public_layout_begin(?PDO $pdo, ?array $site, array $seo = [], array $layoutContext = []): void
{
    $stack = $GLOBALS['toy_public_layout_stack'] ?? [];
    if (!is_array($stack)) {
        $stack = [];
    }

    $stack[] = [
        'pdo' => $pdo,
        'site' => $site,
        'seo' => $seo,
        'layout_context' => $layoutContext,
    ];
    $GLOBALS['toy_public_layout_stack'] = $stack;

    ob_start();
}

function toy_public_layout_end(): void
{
    $contentHtml = ob_get_clean();
    $contentHtml = is_string($contentHtml) ? $contentHtml : '';

    $stack = $GLOBALS['toy_public_layout_stack'] ?? [];
    if (!is_array($stack) || $stack === []) {
        echo $contentHtml;
        return;
    }

    $layoutState = array_pop($stack);
    $GLOBALS['toy_public_layout_stack'] = $stack;

    $pdo = $layoutState['pdo'] ?? null;
    $site = is_array($layoutState['site'] ?? null) ? $layoutState['site'] : null;
    $seo = is_array($layoutState['seo'] ?? null) ? $layoutState['seo'] : [];
    $layoutContext = is_array($layoutState['layout_context'] ?? null) ? $layoutState['layout_context'] : [];
    $layoutFile = toy_public_layout_file(toy_public_layout_key($site));

    include $layoutFile;
}

function toy_render_output_slot(PDO $pdo, array $context): string
{
    $moduleKey = (string) ($context['module_key'] ?? '');
    $pointKey = (string) ($context['point_key'] ?? '');
    $slotKey = (string) ($context['slot_key'] ?? '');

    if (
        !toy_is_safe_module_key($moduleKey)
        || preg_match('/\A[a-z0-9][a-z0-9_.-]{0,119}\z/', $pointKey) !== 1
        || preg_match('/\A[a-z0-9][a-z0-9_.-]{0,79}\z/', $slotKey) !== 1
    ) {
        return '';
    }

    $context['module_key'] = $moduleKey;
    $context['point_key'] = $pointKey;
    $context['slot_key'] = $slotKey;

    $output = [];
    foreach (toy_enabled_module_contract_files($pdo, 'output-slots.php', [$moduleKey]) as $rendererModuleKey => $file) {
        $renderer = toy_load_module_contract_file($rendererModuleKey, $file);
        if (!is_callable($renderer)) {
            continue;
        }

        try {
            $rendered = $renderer($pdo, $context);
            if (is_string($rendered) && $rendered !== '') {
                $output[] = $rendered;
            }
        } catch (Throwable $exception) {
            if (function_exists('toy_log_exception')) {
                toy_log_exception($exception, 'module_output_slot_failed_' . $rendererModuleKey);
            }
        }
    }

    return implode("\n", $output);
}

function toy_url(string $path): string
{
    if (!toy_is_safe_relative_url($path)) {
        return toy_base_path() === '' ? '/' : toy_base_path() . '/';
    }

    $basePath = toy_base_path();
    if ($basePath === '' || $path === $basePath || str_starts_with($path, $basePath . '/')) {
        return $path;
    }

    return $basePath . $path;
}

function toy_canonical_url(?array $site, ?string $path = null): string
{
    $path = $path ?? toy_request_path();
    if (!toy_is_safe_relative_url($path)) {
        $path = '/';
    }

    return toy_absolute_url($site, $path);
}

function toy_is_safe_relative_url(string $url): bool
{
    if ($url === '' || $url[0] !== '/' || str_starts_with($url, '//')) {
        return false;
    }

    if (strpos($url, '\\') !== false) {
        return false;
    }

    return preg_match('/[\x00-\x1F\x7F]/', $url) !== 1;
}

function toy_seo_tags(array $seo = [], ?array $site = null): string
{
    $title = (string) ($seo['title'] ?? ($site['name'] ?? 'Toycore'));
    $description = (string) ($seo['description'] ?? '');
    $canonical = (string) ($seo['canonical'] ?? toy_canonical_url($site));
    $robots = (string) ($seo['robots'] ?? 'index, follow');
    $og = isset($seo['og']) && is_array($seo['og']) ? $seo['og'] : [];

    $tags = [];
    $tags[] = '<title>' . toy_e($title) . '</title>';

    if ($description !== '') {
        $tags[] = '<meta name="description" content="' . toy_e($description) . '">';
    }

    if ($canonical !== '' && (toy_is_http_url($canonical) || toy_is_safe_relative_url($canonical))) {
        $tags[] = '<link rel="canonical" href="' . toy_e($canonical) . '">';
    }

    if ($robots !== '') {
        $tags[] = '<meta name="robots" content="' . toy_e($robots) . '">';
    }

    $ogTitle = (string) ($og['title'] ?? $title);
    $ogDescription = (string) ($og['description'] ?? $description);
    $ogType = (string) ($og['type'] ?? 'website');
    $ogImage = (string) ($og['image'] ?? '');

    if ($ogTitle !== '') {
        $tags[] = '<meta property="og:title" content="' . toy_e($ogTitle) . '">';
    }

    if ($ogDescription !== '') {
        $tags[] = '<meta property="og:description" content="' . toy_e($ogDescription) . '">';
    }

    if ($canonical !== '' && (toy_is_http_url($canonical) || toy_is_safe_relative_url($canonical))) {
        $tags[] = '<meta property="og:url" content="' . toy_e($canonical) . '">';
    }

    if ($ogType !== '') {
        $tags[] = '<meta property="og:type" content="' . toy_e($ogType) . '">';
    }

    if ($ogImage !== '' && (toy_is_http_url($ogImage) || toy_is_safe_relative_url($ogImage))) {
        $tags[] = '<meta property="og:image" content="' . toy_e($ogImage) . '">';
    }

    return implode("\n    ", $tags);
}

function toy_redirect(string $url): void
{
    if (!toy_is_safe_relative_url($url)) {
        toy_render_error(500, '리다이렉트 URL이 올바르지 않습니다.');
    }

    toy_enforce_request_contract('before_redirect');

    header('Location: ' . toy_url($url), true, 302);
    toy_finish_response();
}

function toy_redirect_external(string $url): void
{
    if (!toy_is_http_url($url)) {
        toy_render_error(500, '외부 리다이렉트 URL이 올바르지 않습니다.');
    }

    toy_enforce_request_contract('before_redirect');

    header('Location: ' . $url, true, 302);
    toy_finish_response();
}

function toy_finish_response(): void
{
    toy_enforce_request_contract('before_response_end');
    exit;
}

function toy_csrf_token(): string
{
    if (empty($_SESSION['toy_csrf_token']) || !is_string($_SESSION['toy_csrf_token'])) {
        $_SESSION['toy_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['toy_csrf_token'];
}

function toy_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . toy_e(toy_csrf_token()) . '">';
}

function toy_require_csrf(): void
{
    toy_request_contract_mark('csrf_checked');

    $expected = $_SESSION['toy_csrf_token'] ?? '';
    $actual = $_POST['csrf_token'] ?? '';

    if (!is_string($expected) || !is_string($actual) || $expected === '' || !hash_equals($expected, $actual)) {
        toy_request_contract_guard_blocked('csrf');
        toy_render_error(400, '요청 보안 토큰이 올바르지 않습니다.');
    }
}

function toy_post_string(string $key, int $maxLength): string
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

function toy_post_string_without_truncation(string $key, int $maxLength): ?string
{
    $value = $_POST[$key] ?? '';
    if (is_array($value)) {
        return null;
    }

    $value = trim((string) $value);
    return strlen($value) <= $maxLength ? $value : null;
}

function toy_get_string(string $key, int $maxLength): string
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

function toy_get_string_without_truncation(string $key, int $maxLength): ?string
{
    $value = $_GET[$key] ?? '';
    if (is_array($value)) {
        return null;
    }

    $value = trim((string) $value);
    return strlen($value) <= $maxLength ? $value : null;
}

function toy_send_download_headers(string $contentType, string $filename): void
{
    header('Content-Type: ' . toy_download_content_type($contentType));
    header('Content-Disposition: attachment; filename="' . toy_download_filename($filename) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

function toy_download_content_type(string $contentType): string
{
    $contentType = trim($contentType);
    if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9.+-]*\/[A-Za-z0-9][A-Za-z0-9.+-]*(?:;\s*charset=[A-Za-z0-9._-]+)?\z/', $contentType) !== 1) {
        return 'application/octet-stream';
    }

    return $contentType;
}

function toy_download_filename(string $filename): string
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

function toy_absolute_url(?array $site, string $path): string
{
    if (!toy_is_safe_relative_url($path)) {
        $path = '/';
    }

    $baseUrl = is_array($site) ? rtrim((string) ($site['base_url'] ?? ''), '/') : '';
    if ($baseUrl === '' || !toy_is_site_base_url($baseUrl)) {
        return toy_url($path);
    }

    return $baseUrl . '/' . ltrim($path, '/');
}
