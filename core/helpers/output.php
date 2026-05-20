<?php

declare(strict_types=1);

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

function sr_material_icon_font_url(): string
{
    return sr_url('/assets/fonts/material-symbols-outlined.ttf');
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
    return '<script>(function(){var r=document.documentElement;function y(){r.classList.add("sr-material-icons-ready")}if(document.fonts&&document.fonts.load){document.fonts.load("24px \\"Material Symbols Outlined\\"","home").then(y,function(){r.classList.add("sr-material-icons-unavailable")})}else{y()}})();</script>';
}

function sr_stylesheet_tag(array $stylesheets = []): string
{
    $tags = [
        '<link rel="preload" as="font" type="font/ttf" href="' . sr_e(sr_material_icon_font_url()) . '" crossorigin>',
        '<link rel="stylesheet" href="' . sr_e(sr_asset_url('/assets/tokens.css')) . '">',
        '<link rel="stylesheet" href="' . sr_e(sr_asset_url('/assets/icons.css')) . '">',
        '<link rel="stylesheet" href="' . sr_e(sr_asset_url('/assets/ui-kit.css')) . '">',
        '<link rel="stylesheet" href="' . sr_e(sr_asset_url('/assets/saanraan.css')) . '">',
        '<link rel="stylesheet" href="' . sr_e(sr_asset_url('/assets/public-ui.css')) . '">',
    ];

    foreach ($stylesheets as $stylesheet) {
        if (!is_string($stylesheet) || !sr_is_safe_relative_url($stylesheet)) {
            continue;
        }

        $tags[] = '<link rel="stylesheet" href="' . sr_e(sr_asset_url($stylesheet)) . '">';
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
    $colorScheme = is_array($site) ? (string) ($site['ui_color_scheme'] ?? 'light') : 'light';
    return isset(sr_color_scheme_options()[$colorScheme]) ? $colorScheme : 'light';
}

function sr_public_layout_options(): array
{
    return sr_filter_view_options([
        'basic' => [
            'label' => '기본 레이아웃',
            'views' => [
                'layout' => SR_ROOT . '/layouts/public/basic/layout.php',
                'ui_kit' => SR_ROOT . '/layouts/public/basic/ui-kit.php',
            ],
        ],
    ], ['layout'], 'public layout');
}

function sr_public_layout_key(?array $site = null): string
{
    $layoutKey = is_array($site) ? (string) ($site['public_layout_key'] ?? 'basic') : 'basic';
    return isset(sr_public_layout_options()[$layoutKey]) ? $layoutKey : 'basic';
}

function sr_public_layout_file(string $layoutKey): string
{
    $options = sr_public_layout_options();
    if (!isset($options[$layoutKey])) {
        $layoutKey = 'basic';
    }

    $layoutFile = (string) ($options[$layoutKey]['views']['layout'] ?? '');
    if ($layoutFile === '' || !is_file($layoutFile)) {
        $layoutFile = (string) ($options['basic']['views']['layout'] ?? '');
    }

    if ($layoutFile === '' || !is_file($layoutFile)) {
        throw new RuntimeException('기본 공개 레이아웃 파일이 누락되었습니다.');
    }

    return $layoutFile;
}

function sr_public_layout_optional_view_file(string $layoutKey, string $viewKey): ?string
{
    if (preg_match('/\A[a-z0-9_]{1,40}\z/', $viewKey) !== 1) {
        return null;
    }

    $options = sr_public_layout_options();
    if (!isset($options[$layoutKey])) {
        $layoutKey = 'basic';
    }

    $viewFile = (string) ($options[$layoutKey]['views'][$viewKey] ?? '');
    if ($viewFile !== '' && is_file($viewFile)) {
        return $viewFile;
    }

    $fallbackFile = (string) ($options['basic']['views'][$viewKey] ?? '');
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
    $layoutContext = is_array($layoutState['layout_context'] ?? null) ? $layoutState['layout_context'] : [];
    $layoutFile = sr_public_layout_file(sr_public_layout_key($site));

    include $layoutFile;
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
    foreach (sr_enabled_module_contract_files($pdo, 'output-slots.php', [$moduleKey]) as $rendererModuleKey => $file) {
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
    $title = (string) ($seo['title'] ?? ($site['name'] ?? 'Saanraan'));
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
        sr_render_error(500, '리다이렉트 URL이 올바르지 않습니다.');
    }

    sr_enforce_request_contract('before_redirect');

    header('Location: ' . sr_url($url), true, 302);
    sr_finish_response();
}

function sr_redirect_external(string $url): void
{
    if (!sr_is_http_url($url)) {
        sr_render_error(500, '외부 리다이렉트 URL이 올바르지 않습니다.');
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
        sr_render_error(400, '요청 보안 토큰이 올바르지 않습니다.');
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

function sr_send_download_headers(string $contentType, string $filename): void
{
    header('Content-Type: ' . sr_download_content_type($contentType));
    header('Content-Disposition: attachment; filename="' . sr_download_filename($filename) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
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
