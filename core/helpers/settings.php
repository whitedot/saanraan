<?php

declare(strict_types=1);

function sr_load_site(PDO $pdo): ?array
{
    $settings = sr_site_settings($pdo);
    $publicLayoutKey = (string) ($settings['public_layout_key'] ?? 'common.basic');
    if ($publicLayoutKey === 'basic') {
        $publicLayoutKey = 'common.basic';
    }

    return [
        'name' => (string) ($settings['site.name'] ?? 'Saanraan'),
        'base_url' => (string) ($settings['site.base_url'] ?? ''),
        'timezone' => (string) ($settings['site.timezone'] ?? 'Asia/Seoul'),
        'default_locale' => (string) ($settings['site.default_locale'] ?? 'ko'),
        'supported_locales' => (string) ($settings['site.supported_locales'] ?? (string) ($settings['site.default_locale'] ?? 'ko')),
        'default_currency' => sr_site_default_currency($pdo),
        'status' => (string) ($settings['site.status'] ?? 'active'),
        'member_only_enabled' => in_array((string) ($settings['site.member_only_enabled'] ?? '0'), ['1', 'true', 'yes', 'on'], true),
        'home_path' => (string) ($settings['site.home_path'] ?? '/'),
        'public_layout_key' => $publicLayoutKey,
        'public_theme_key' => (string) ($settings['public_theme_key'] ?? 'default'),
    ];
}

function sr_site_display_name(?array $site = null, ?PDO $pdo = null): string
{
    $siteName = is_array($site) ? trim((string) ($site['name'] ?? $site['site_name'] ?? '')) : '';
    if ($siteName === '' && $pdo instanceof PDO) {
        $siteName = trim((string) sr_site_setting($pdo, 'site.name', ''));
    }

    return $siteName !== '' ? $siteName : 'Saanraan';
}

function sr_known_currency_min_units(): array
{
    // Values are integer settlement minimum units, not decimal exponents.
    // USD currently means whole-dollar settlement in the 1.x integer model.
    return [
        'KRW' => 1,
        'USD' => 1,
    ];
}

function sr_normalize_currency_code(string $currency): string
{
    return strtoupper(trim($currency));
}

function sr_currency_min_unit(string $currency): int
{
    $currency = sr_normalize_currency_code($currency);
    $minUnits = sr_known_currency_min_units();

    return (int) ($minUnits[$currency] ?? 0);
}

function sr_currency_is_known(string $currency): bool
{
    return sr_currency_min_unit($currency) > 0;
}

function sr_site_default_currency(?PDO $pdo = null): string
{
    $currency = 'KRW';
    if ($pdo instanceof PDO) {
        $settings = sr_site_settings($pdo);
        $currency = (string) ($settings['site.default_currency'] ?? $currency);
    }

    $currency = sr_normalize_currency_code($currency);
    return sr_currency_is_known($currency) ? $currency : 'KRW';
}

function sr_enabled_module_keys(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT module_key FROM sr_modules WHERE status = 'enabled' ORDER BY id ASC");
    } catch (PDOException $exception) {
        if (sr_module_registry_missing_exception($exception)) {
            return [];
        }
        throw $exception;
    }
    $moduleKeys = [];
    foreach ($stmt->fetchAll() as $row) {
        $moduleKey = (string) ($row['module_key'] ?? '');
        if (sr_is_safe_module_key($moduleKey)) {
            $moduleKeys[] = $moduleKey;
        }
    }

    return $moduleKeys;
}

function sr_installed_module_keys(PDO $pdo): array
{
    try {
        $stmt = $pdo->query('SELECT module_key FROM sr_modules ORDER BY id ASC');
    } catch (PDOException $exception) {
        if (sr_module_registry_missing_exception($exception)) {
            return [];
        }
        throw $exception;
    }
    $moduleKeys = [];
    foreach ($stmt->fetchAll() as $row) {
        $moduleKey = (string) ($row['module_key'] ?? '');
        if (sr_is_safe_module_key($moduleKey)) {
            $moduleKeys[] = $moduleKey;
        }
    }

    return $moduleKeys;
}

function sr_module_registry_missing_exception(PDOException $exception): bool
{
    $code = (string) $exception->getCode();
    $message = strtolower($exception->getMessage());

    return $code === '42S02'
        || str_contains($message, 'no such table: sr_modules')
        || (str_contains($message, 'base table or view not found') && str_contains($message, 'sr_modules'));
}

function sr_is_safe_module_key(string $moduleKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $moduleKey) === 1;
}

function sr_module_enabled(PDO $pdo, string $moduleKey): bool
{
    if (!sr_is_safe_module_key($moduleKey)) {
        return false;
    }

    return in_array($moduleKey, sr_enabled_module_keys($pdo), true);
}

function sr_enabled_module_asset_paths(?PDO $pdo, array $moduleAssetPaths): array
{
    if (!$pdo instanceof PDO) {
        return [];
    }

    $paths = [];
    foreach ($moduleAssetPaths as $moduleKey => $assetPath) {
        if (!is_string($moduleKey) || !sr_module_enabled($pdo, $moduleKey)) {
            continue;
        }
        if (!is_string($assetPath) || $assetPath === '') {
            continue;
        }
        $paths[] = $assetPath;
    }

    return $paths;
}

function sr_module_homepage_contract(string $moduleKey, string $contractFile): array
{
    $contract = sr_load_module_contract_file($moduleKey, $contractFile);
    if (is_callable($contract)) {
        return [
            'candidates_function' => $contract,
            'available_function' => null,
        ];
    }

    if (!is_array($contract)) {
        return [];
    }

    return [
        'candidates_function' => is_callable($contract['candidates_function'] ?? null) ? $contract['candidates_function'] : null,
        'available_function' => is_callable($contract['available_function'] ?? null) ? $contract['available_function'] : null,
    ];
}

function sr_site_home_path_is_available(PDO $pdo, string $homePath): bool
{
    if ($homePath === '/') {
        return true;
    }

    if (!sr_is_safe_relative_url($homePath)) {
        return false;
    }

    if ($homePath === '/admin' || str_starts_with($homePath, '/admin/')) {
        return false;
    }

    foreach (sr_enabled_module_contract_files($pdo, 'homepage-candidates.php') as $moduleKey => $candidatesFile) {
        $contract = sr_module_homepage_contract($moduleKey, $candidatesFile);
        $availableFunction = $contract['available_function'] ?? null;
        if (is_callable($availableFunction)) {
            try {
                $available = $availableFunction($pdo, $homePath);
                if ($available === true) {
                    return true;
                }
                if ($available === false) {
                    return false;
                }
            } catch (Throwable) {
                continue;
            }
        }

        $candidatesFunction = $contract['candidates_function'] ?? null;
        if (!is_callable($candidatesFunction)) {
            continue;
        }

        try {
            $candidates = $candidatesFunction($pdo);
        } catch (Throwable) {
            $candidates = [];
        }

        if (!is_array($candidates)) {
            continue;
        }

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            if ((string) ($candidate['path'] ?? '') === $homePath && !empty($candidate['available'])) {
                return true;
            }
        }
    }

    foreach (sr_enabled_module_contract_files($pdo, 'paths.php', ['admin']) as $moduleKey => $pathsFile) {
        $paths = sr_load_module_contract_file($moduleKey, $pathsFile);
        if (!is_array($paths)) {
            continue;
        }

        if (isset($paths['GET ' . $homePath])) {
            return true;
        }

        foreach ($paths as $route => $_actionRelativePath) {
            $route = (string) $route;
            if (!str_starts_with($route, 'GET ') || !str_ends_with($route, '/*')) {
                continue;
            }

            $prefix = substr($route, 4, -1);
            if ($prefix !== '' && str_starts_with($homePath, $prefix)) {
                return true;
            }
        }
    }

    return false;
}

function sr_module_record_status(PDO $pdo, string $moduleKey): string
{
    $module = sr_module_record_entry($pdo, $moduleKey);
    return is_array($module) ? (string) ($module['status'] ?? '') : '';
}

function sr_module_record_entry(PDO $pdo, string $moduleKey): ?array
{
    if (!sr_is_safe_module_key($moduleKey)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT module_key, version, status FROM sr_modules WHERE module_key = :module_key LIMIT 1');
    $stmt->execute(['module_key' => $moduleKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_module_type(string $moduleKey): string
{
    $metadata = sr_module_metadata($moduleKey);
    $type = (string) ($metadata['type'] ?? 'module');

    return in_array($type, ['module', 'plugin'], true) ? $type : 'module';
}

function sr_enabled_module_contract_files(PDO $pdo, string $contractFile, array $excludedModuleKeys = []): array
{
    if (preg_match('/\A[a-z0-9][a-z0-9_.-]{0,80}\.php\z/', $contractFile) !== 1) {
        return [];
    }

    $excluded = [];
    foreach ($excludedModuleKeys as $moduleKey) {
        if (is_string($moduleKey) && sr_is_safe_module_key($moduleKey)) {
            $excluded[$moduleKey] = true;
        }
    }

    $files = [];
    foreach (sr_enabled_module_keys($pdo) as $moduleKey) {
        if (isset($excluded[$moduleKey])) {
            continue;
        }

        if (!sr_module_contract_is_loadable($moduleKey)) {
            continue;
        }

        $moduleDir = SR_ROOT . '/modules/' . $moduleKey;
        $file = $moduleDir . '/' . $contractFile;
        if (!is_file($file)) {
            continue;
        }

        $realModuleDir = realpath($moduleDir);
        $realFile = realpath($file);
        if ($realModuleDir === false || $realFile === false || strpos($realFile, $realModuleDir . DIRECTORY_SEPARATOR) !== 0) {
            continue;
        }

        $files[$moduleKey] = $realFile;
    }

    return $files;
}

function sr_installed_module_contract_files(PDO $pdo, string $contractFile, array $excludedModuleKeys = []): array
{
    if (preg_match('/\A[a-z0-9][a-z0-9_.-]{0,80}\.php\z/', $contractFile) !== 1) {
        return [];
    }

    $excluded = [];
    foreach ($excludedModuleKeys as $moduleKey) {
        if (is_string($moduleKey) && sr_is_safe_module_key($moduleKey)) {
            $excluded[$moduleKey] = true;
        }
    }

    $files = [];
    foreach (sr_installed_module_keys($pdo) as $moduleKey) {
        if (isset($excluded[$moduleKey])) {
            continue;
        }

        if (!sr_module_contract_is_loadable($moduleKey)) {
            continue;
        }

        $moduleDir = SR_ROOT . '/modules/' . $moduleKey;
        $file = $moduleDir . '/' . $contractFile;
        if (!is_file($file)) {
            continue;
        }

        $realModuleDir = realpath($moduleDir);
        $realFile = realpath($file);
        if ($realModuleDir === false || $realFile === false || strpos($realFile, $realModuleDir . DIRECTORY_SEPARATOR) !== 0) {
            continue;
        }

        $files[$moduleKey] = $realFile;
    }

    return $files;
}

function sr_load_module_contract_file(string $moduleKey, string $file): mixed
{
    if (!sr_is_safe_module_key($moduleKey) || !is_file($file)) {
        return null;
    }

    $moduleDir = SR_ROOT . '/modules/' . $moduleKey;
    $realModuleDir = realpath($moduleDir);
    $realFile = realpath($file);
    if ($realModuleDir === false || $realFile === false || strpos($realFile, $realModuleDir . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }

    try {
        return include $realFile;
    } catch (Throwable $exception) {
        if (function_exists('sr_log_exception')) {
            $contractFile = strtolower(basename($realFile));
            $contractLabel = preg_replace('/[^a-z0-9_]+/', '_', $contractFile);
            $contractLabel = is_string($contractLabel) ? trim($contractLabel, '_') : 'contract';
            sr_log_exception($exception, 'module_contract_load_failed_' . $moduleKey . '_' . $contractLabel);
        }

        return null;
    }
}

function sr_module_contract_function(PDO $pdo, string $moduleKey, string $contractFile, string $functionKey): string
{
    if (
        !sr_module_enabled($pdo, $moduleKey)
        || !sr_module_contract_is_loadable($moduleKey)
        || preg_match('/\A[a-z0-9][a-z0-9_.-]{0,80}\.php\z/', $contractFile) !== 1
        || preg_match('/\A[a-z][a-z0-9_]{0,80}\z/', $functionKey) !== 1
    ) {
        return '';
    }

    $contract = sr_load_module_contract_file($moduleKey, SR_ROOT . '/modules/' . $moduleKey . '/' . $contractFile);
    if (!is_array($contract)) {
        return '';
    }

    $helpers = (string) ($contract['helpers'] ?? '');
    if ($helpers !== '') {
        if (preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) !== 1) {
            return '';
        }

        $helperPath = SR_ROOT . '/modules/' . $moduleKey . '/' . $helpers;
        if (!is_file($helperPath)) {
            return '';
        }

        require_once $helperPath;
    }

    $function = (string) ($contract[$functionKey] ?? '');
    return $function !== '' && function_exists($function) ? $function : '';
}

function sr_site_settings(PDO $pdo): array
{
    static $cache = [];
    static $cacheToken = null;

    $currentToken = (int) ($GLOBALS['sr_site_settings_cache_token'] ?? 0);
    if ($cacheToken !== $currentToken) {
        $cache = [];
        $cacheToken = $currentToken;
    }

    if (isset($cache['all'])) {
        return $cache['all'];
    }

    $stmt = $pdo->query('SELECT setting_key, setting_value, value_type FROM sr_site_settings ORDER BY setting_key ASC');

    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[(string) $row['setting_key']] = sr_cast_setting_value($row['setting_value'], (string) $row['value_type']);
    }

    $cache['all'] = $settings;
    return $settings;
}

function sr_clear_site_settings_cache(): void
{
    $GLOBALS['sr_site_settings_cache_token'] = (int) ($GLOBALS['sr_site_settings_cache_token'] ?? 0) + 1;
}

function sr_site_setting(PDO $pdo, string $key, mixed $default = null): mixed
{
    $settings = sr_site_settings($pdo);
    return array_key_exists($key, $settings) ? $settings[$key] : $default;
}

function sr_site_title_suffix(PDO $pdo): string
{
    return sr_clean_single_line((string) sr_site_setting($pdo, 'site.title_suffix', ''), 80);
}

function sr_site_meta_description(PDO $pdo): string
{
    return sr_clean_single_line((string) sr_site_setting($pdo, 'site.meta_description', ''), 255);
}

function sr_site_og_image(PDO $pdo): string
{
    return sr_clean_single_line((string) sr_site_setting($pdo, 'site.og_image', ''), 255);
}

function sr_site_apply_public_meta_defaults(PDO $pdo, array $seo): array
{
    $titleSuffix = sr_site_title_suffix($pdo);
    if ($titleSuffix !== '') {
        $title = trim((string) ($seo['title'] ?? ''));
        if ($title !== '' && !str_ends_with($title, ' - ' . $titleSuffix)) {
            $seo['title'] = $title . ' - ' . $titleSuffix;
        }
    }

    $title = trim((string) ($seo['title'] ?? ''));
    if ($title !== '') {
        $og = isset($seo['og']) && is_array($seo['og']) ? $seo['og'] : [];
        if (trim((string) ($og['title'] ?? '')) === '') {
            $og['title'] = $title;
            $seo['og'] = $og;
        }
    }

    $defaultDescription = sr_site_meta_description($pdo);
    if ($defaultDescription !== '' && trim((string) ($seo['description'] ?? '')) === '') {
        $seo['description'] = $defaultDescription;
    }

    $description = trim((string) ($seo['description'] ?? ''));
    if ($description !== '') {
        $og = isset($seo['og']) && is_array($seo['og']) ? $seo['og'] : [];
        if (trim((string) ($og['description'] ?? '')) === '') {
            $og['description'] = $description;
            $seo['og'] = $og;
        }
    }

    $defaultOgImage = sr_site_og_image($pdo);
    if ($defaultOgImage !== '') {
        $og = isset($seo['og']) && is_array($seo['og']) ? $seo['og'] : [];
        if (trim((string) ($og['image'] ?? '')) === '') {
            $og['image'] = $defaultOgImage;
            $seo['og'] = $og;
        }
    }

    return $seo;
}

function sr_site_og_image_upload_max_bytes(): int
{
    return 5242880;
}

function sr_site_og_image_upload_was_provided(mixed $file): bool
{
    return sr_upload_was_provided($file);
}

function sr_site_upload_og_image(array $file): array
{
    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_site_og_image_upload_max_bytes(),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ]);

    $sourcePath = (string) $validated['tmp_name'];
    $targetFormat = sr_image_format_for_mime((string) $validated['mime_type']);
    if ($targetFormat === '') {
        throw new RuntimeException('허용되지 않은 OG 이미지 형식입니다.');
    }

    $dimensions = @getimagesize($sourcePath);
    if (!is_array($dimensions) || (int) ($dimensions[0] ?? 0) < 1 || (int) ($dimensions[1] ?? 0) < 1) {
        throw new RuntimeException('이미지 크기를 확인할 수 없습니다.');
    }
    if ((int) $dimensions[0] * (int) $dimensions[1] > 25000000) {
        throw new RuntimeException('이미지 픽셀 수가 너무 큽니다.');
    }

    $datePath = date('Y/m');
    $directory = SR_ROOT . '/storage/tmp/site-og-images/' . $datePath;
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('OG 이미지 임시 디렉터리를 만들 수 없습니다. storage/tmp 디렉터리 쓰기 권한을 확인해 주세요.');
    }

    $storedName = sr_upload_random_filename($targetFormat);
    $targetPath = sr_upload_safe_target_path($directory, $storedName);
    sr_upload_assert_target_path_writable($targetPath);

    if (!sr_upload_reencode_image($sourcePath, $targetPath, $targetFormat, [
        'max_pixels' => 25000000,
        'quality' => 88,
    ])) {
        throw new RuntimeException('이미지 재인코딩에 실패했습니다.');
    }

    $storedMimeType = sr_upload_detect_mime($targetPath);
    if (!sr_image_mime_is_allowed($storedMimeType)) {
        @unlink($targetPath);
        throw new RuntimeException('저장된 이미지 MIME을 확인할 수 없습니다.');
    }

    $storageKey = 'site/og-images/' . $datePath . '/' . $storedName;
    $stored = sr_storage_put_file($targetPath, $storageKey, [
        'content_type' => $storedMimeType,
    ]);
    @unlink($targetPath);

    $storageReference = sr_storage_reference((string) $stored['driver'], $storageKey);
    $publicUrl = (string) ($stored['url'] ?? '');

    return [
        'driver' => (string) $stored['driver'],
        'storage_key' => $storageKey,
        'public_url' => $publicUrl !== '' ? $publicUrl : '/site/og-image?file=' . rawurlencode($storageReference),
        'mime_type' => $storedMimeType,
    ];
}

function sr_site_og_image_storage_key_is_valid(string $key): bool
{
    return preg_match('#\A(?:site|seo)/og-images/\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $key) === 1;
}

function sr_site_og_image_storage_reference(string $reference): ?array
{
    $storage = sr_storage_parse_reference($reference);
    if (!is_array($storage) || !sr_site_og_image_storage_key_is_valid((string) $storage['key'])) {
        return null;
    }

    return $storage;
}

function sr_save_site_setting(PDO $pdo, string $key, string $value, string $valueType = 'string'): void
{
    if (preg_match('/\A[a-z][a-z0-9_.-]{1,119}\z/', $key) !== 1) {
        throw new InvalidArgumentException('Site setting key is invalid.');
    }

    if (!in_array($valueType, ['string', 'int', 'bool', 'json'], true)) {
        throw new InvalidArgumentException('Site setting value type is invalid.');
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_site_settings
            (setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'setting_key' => $key,
        'setting_value' => $value,
        'value_type' => $valueType,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    sr_clear_site_settings_cache();
}

function sr_save_site_settings(PDO $pdo, array $settings): void
{
    foreach ($settings as $key => $setting) {
        if (!is_array($setting)) {
            continue;
        }

        sr_save_site_setting(
            $pdo,
            (string) $key,
            (string) ($setting['value'] ?? ''),
            (string) ($setting['type'] ?? 'string')
        );
    }
}

function sr_module_settings(PDO $pdo, string $moduleKey): array
{
    static $cache = [];
    static $cacheTokens = [];

    if (!sr_is_safe_module_key($moduleKey)) {
        return [];
    }

    $currentToken = (int) ($GLOBALS['sr_module_settings_cache_token'] ?? 0)
        + (int) ($GLOBALS['sr_module_settings_cache_token_' . $moduleKey] ?? 0);
    if (!isset($cacheTokens[$moduleKey]) || $cacheTokens[$moduleKey] !== $currentToken) {
        unset($cache[$moduleKey]);
        $cacheTokens[$moduleKey] = $currentToken;
    }

    if (isset($cache[$moduleKey])) {
        return $cache[$moduleKey];
    }

    $stmt = $pdo->prepare(
        'SELECT s.setting_key, s.setting_value, s.value_type
         FROM sr_module_settings s
         INNER JOIN sr_modules m ON m.id = s.module_id
         WHERE m.module_key = :module_key
         ORDER BY s.setting_key ASC'
    );
    $stmt->execute(['module_key' => $moduleKey]);

    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[(string) $row['setting_key']] = sr_cast_setting_value($row['setting_value'], (string) $row['value_type']);
    }

    $cache[$moduleKey] = $settings;
    return $settings;
}

function sr_module_setting(PDO $pdo, string $moduleKey, string $key, mixed $default = null): mixed
{
    $settings = sr_module_settings($pdo, $moduleKey);
    return array_key_exists($key, $settings) ? $settings[$key] : $default;
}

function sr_clear_module_settings_cache(?string $moduleKey = null): void
{
    if ($moduleKey !== null && !sr_is_safe_module_key($moduleKey)) {
        return;
    }

    $GLOBALS['sr_module_settings_cache_token'] = (int) ($GLOBALS['sr_module_settings_cache_token'] ?? 0) + 1;
    if ($moduleKey !== null) {
        $GLOBALS['sr_module_settings_cache_token_' . $moduleKey] = (int) ($GLOBALS['sr_module_settings_cache_token_' . $moduleKey] ?? 0) + 1;
    }
}

function sr_is_valid_module_route(string $route): bool
{
    if (preg_match('/\A(GET|POST) (\/[^\x00-\x1F\x7F\\\\]*)\z/', $route, $matches) !== 1) {
        return false;
    }

    $path = (string) $matches[2];
    if ($path === '/' || str_starts_with($path, '//')) {
        return false;
    }

    $asteriskCount = substr_count($path, '*');
    if ($asteriskCount === 0) {
        return true;
    }

    return $asteriskCount === 1 && str_ends_with($path, '/*') && strlen($path) > 2;
}

function sr_module_route_matches_request(string $route, string $routeKey): bool
{
    if (!sr_is_valid_module_route($route)) {
        return false;
    }

    if ($route === $routeKey) {
        return true;
    }

    [$routeMethod, $routePath] = explode(' ', $route, 2);
    [$requestMethod, $requestPath] = explode(' ', $routeKey, 2);
    if ($routeMethod !== $requestMethod || !str_ends_with($routePath, '/*')) {
        return false;
    }

    $prefix = substr($routePath, 0, -1);
    return str_starts_with($requestPath, $prefix) && strlen($requestPath) > strlen($prefix);
}

function sr_module_routes_conflict(string $leftRoute, string $rightRoute): bool
{
    if (!sr_is_valid_module_route($leftRoute) || !sr_is_valid_module_route($rightRoute)) {
        return false;
    }

    [$leftMethod, $leftPath] = explode(' ', $leftRoute, 2);
    [$rightMethod, $rightPath] = explode(' ', $rightRoute, 2);
    if ($leftMethod !== $rightMethod) {
        return false;
    }

    if ($leftPath === $rightPath) {
        return true;
    }

    $leftWildcard = str_ends_with($leftPath, '/*');
    $rightWildcard = str_ends_with($rightPath, '/*');
    if (!$leftWildcard && !$rightWildcard) {
        return false;
    }

    $leftPrefix = $leftWildcard ? substr($leftPath, 0, -1) : $leftPath;
    $rightPrefix = $rightWildcard ? substr($rightPath, 0, -1) : $rightPath;

    if ($leftWildcard && $rightWildcard) {
        return str_starts_with($leftPrefix, $rightPrefix) || str_starts_with($rightPrefix, $leftPrefix);
    }

    return $leftWildcard
        ? str_starts_with($rightPath, $leftPrefix) && strlen($rightPath) > strlen($leftPrefix)
        : str_starts_with($leftPath, $rightPrefix) && strlen($leftPath) > strlen($rightPrefix);
}

function sr_module_metadata(string $moduleKey): array
{
    static $cache = [];

    if (!sr_is_safe_module_key($moduleKey)) {
        return [];
    }

    if (isset($cache[$moduleKey])) {
        return $cache[$moduleKey];
    }

    $file = SR_ROOT . '/modules/' . $moduleKey . '/module.php';
    if (!is_file($file)) {
        $cache[$moduleKey] = [];
        return [];
    }

    try {
        $metadata = include $file;
    } catch (Throwable $exception) {
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'module_metadata_load_failed_' . $moduleKey);
        }

        $cache[$moduleKey] = [];
        return [];
    }

    $cache[$moduleKey] = is_array($metadata) ? $metadata : [];

    return $cache[$moduleKey];
}

function sr_module_saanraan_metadata(array $metadata): array
{
    return is_array($metadata['saanraan'] ?? null) ? $metadata['saanraan'] : [];
}

function sr_module_known_contract_files(): array
{
    return [
        'paths.php',
        'admin-menu.php',
        'output-slots.php',
        'extension-points.php',
        'privacy-export.php',
        'privacy-cleanup.php',
        'sitemap.php',
        'menu-links.php',
        'member-group-rules.php',
        'dashboard.php',
        'layout-options.php',
        'asset-exchange.php',
        'member-assets.php',
        'member-withdrawal-assets.php',
        'member-summary-rows.php',
        'member-action-rows.php',
        'member-only-routes.php',
        'member-registration.php',
        'member-mfa-providers.php',
        'homepage-candidates.php',
        'editor-options.php',
        'markdown-renderer.php',
        'coupon-targets.php',
        'coupon-references.php',
        'payment-ledger-targets.php',
        'banner-references.php',
        'popup-layer-references.php',
        'member-group-references.php',
        'site-setting-references.php',
        'logo-positions.php',
        'notification-events.php',
        'admin-notification-events.php',
        'antispam-targets.php',
        'antispam-providers.php',
        'oauth-providers.php',
        'identity-provider.php',
        'url-embed-targets.php',
        'reaction-targets.php',
        'operational-status.php',
        'retention-targets.php',
    ];
}

function sr_module_declared_contract_files(array $metadata, string $key): array
{
    $contracts = isset($metadata['contracts']) && is_array($metadata['contracts']) ? $metadata['contracts'] : [];
    $files = isset($contracts[$key]) && is_array($contracts[$key]) ? $contracts[$key] : [];
    $valid = [];

    foreach ($files as $file) {
        if (is_string($file)) {
            $valid[] = $file;
        }
    }

    return array_values(array_unique($valid));
}

function sr_version_format(string $version): string
{
    if (preg_match('/\Av?\d+\.\d+\.\d+\z/', $version) === 1) {
        return 'semver';
    }

    if (preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $version) === 1) {
        return 'date';
    }

    return '';
}

function sr_core_version_satisfies_minimum(string $minimumVersion): bool
{
    $coreVersion = SR_CORE_VERSION;
    $coreFormat = sr_version_format($coreVersion);
    $minimumFormat = sr_version_format($minimumVersion);

    if ($coreFormat === '' || $minimumFormat === '' || $coreFormat !== $minimumFormat) {
        return false;
    }

    if ($coreFormat === 'semver') {
        return version_compare(ltrim($coreVersion, 'vV'), ltrim($minimumVersion, 'vV'), '>=');
    }

    return strcmp($coreVersion, $minimumVersion) >= 0;
}

function sr_module_contract_errors(array $metadata): array
{
    $errors = [];
    $saanraanMetadata = sr_module_saanraan_metadata($metadata);
    $moduleContract = is_string($saanraanMetadata['module_contract'] ?? null) ? (string) $saanraanMetadata['module_contract'] : '';

    if ($moduleContract === '') {
        $errors[] = 'module.php의 saanraan.module_contract가 필요합니다.';
    } elseif ($moduleContract !== SR_MODULE_CONTRACT_VERSION) {
        $errors[] = 'module.php의 saanraan.module_contract가 현재 코어 계약 버전(' . SR_MODULE_CONTRACT_VERSION . ')과 맞지 않습니다.';
    }

    $minVersion = is_string($saanraanMetadata['min_version'] ?? null) ? (string) $saanraanMetadata['min_version'] : '';
    if ($minVersion === '') {
        $errors[] = 'module.php의 saanraan.min_version이 필요합니다.';
    } elseif (preg_match('/\A(?:v?\d+\.\d+\.\d+|\d{4}\.\d{2}\.\d{3})\z/', $minVersion) !== 1) {
        $errors[] = 'module.php의 saanraan.min_version 형식이 올바르지 않습니다.';
    } elseif (!sr_core_version_satisfies_minimum($minVersion)) {
        $errors[] = '현재 Saanraan 버전(' . SR_CORE_VERSION . ')이 module.php의 saanraan.min_version(' . $minVersion . ') 요구사항을 만족하지 않습니다.';
    }

    $testedWith = $saanraanMetadata['tested_with'] ?? null;
    if (!is_array($testedWith)) {
        $errors[] = 'module.php의 saanraan.tested_with는 배열이어야 합니다.';
    } elseif ($testedWith === []) {
        $errors[] = 'module.php의 saanraan.tested_with가 필요합니다.';
    } else {
        foreach ($testedWith as $version) {
            if (!is_string($version) || preg_match('/\A(?:v?\d+\.\d+\.\d+|\d{4}\.\d{2}\.\d{3})\z/', $version) !== 1) {
                $errors[] = 'module.php의 saanraan.tested_with 버전 형식이 올바르지 않습니다.';
                break;
            }
        }
    }

    return $errors;
}

function sr_module_metadata_errors(array $metadata): array
{
    $errors = [];

    $name = is_string($metadata['name'] ?? null) ? trim((string) $metadata['name']) : '';
    if ($name === '') {
        $errors[] = 'module.php의 name이 필요합니다.';
    }

    $version = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
    if ($version === '' || preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $version) !== 1) {
        $errors[] = 'module.php의 version은 YYYY.MM.NNN 형식이어야 합니다.';
    }

    $type = (string) ($metadata['type'] ?? 'module');
    if (!in_array($type, ['module', 'plugin'], true)) {
        $errors[] = 'module.php의 type은 module 또는 plugin이어야 합니다.';
    }

    foreach (sr_module_contract_errors($metadata) as $error) {
        $errors[] = $error;
    }

    $contracts = $metadata['contracts'] ?? null;
    if ($contracts !== null && !is_array($contracts)) {
        $errors[] = 'module.php의 contracts는 배열이어야 합니다.';
    }

    $knownContractFiles = sr_module_known_contract_files();
    $contracts = is_array($contracts) ? $contracts : [];
    foreach (['provides', 'consumes'] as $contractKey) {
        if (!isset($contracts[$contractKey])) {
            continue;
        }

        if (!is_array($contracts[$contractKey])) {
            $errors[] = 'module.php의 contracts.' . $contractKey . '는 배열이어야 합니다.';
            continue;
        }

        foreach ($contracts[$contractKey] as $file) {
            if (
                !is_string($file)
                || preg_match('/\A[a-z0-9][a-z0-9_.-]{0,80}\.php\z/', $file) !== 1
                || !in_array($file, $knownContractFiles, true)
            ) {
                $errors[] = 'module.php의 contracts.' . $contractKey . ' 계약 파일 선언이 올바르지 않습니다.';
                break;
            }
        }
    }

    return array_values(array_unique($errors));
}

function sr_module_contract_file_errors(string $moduleDirectory, array $metadata): array
{
    $errors = [];
    $knownContractFiles = sr_module_known_contract_files();
    $declaredProvides = sr_module_declared_contract_files($metadata, 'provides');

    foreach ($declaredProvides as $file) {
        if (!is_file($moduleDirectory . '/' . $file)) {
            $errors[] = 'module.php의 contracts.provides에 선언한 ' . $file . ' 파일이 필요합니다.';
        }
    }

    foreach ($knownContractFiles as $file) {
        if (is_file($moduleDirectory . '/' . $file) && !in_array($file, $declaredProvides, true)) {
            $errors[] = $file . ' 파일은 module.php의 contracts.provides에 선언해야 합니다.';
        }
    }

    return array_values(array_unique($errors));
}

function sr_module_contract_is_loadable(string $moduleKey): bool
{
    if (!sr_is_safe_module_key($moduleKey)) {
        return false;
    }

    $metadata = sr_module_metadata($moduleKey);
    return $metadata !== []
        && sr_module_metadata_errors($metadata) === []
        && sr_module_contract_file_errors(SR_ROOT . '/modules/' . $moduleKey, $metadata) === [];
}

function sr_module_requirement_errors(PDO $pdo, string $moduleKey, array $metadata, string $targetStatus = 'enabled'): array
{
    if ($targetStatus !== 'enabled') {
        return [];
    }

    $errors = [];
    $requires = isset($metadata['requires']) && is_array($metadata['requires']) ? $metadata['requires'] : [];
    $requiredModules = isset($requires['modules']) && is_array($requires['modules']) ? $requires['modules'] : [];

    foreach ($requiredModules as $key => $value) {
        $requiredModuleKey = is_string($key) ? $key : (string) $value;
        if (!sr_is_safe_module_key($requiredModuleKey) || $requiredModuleKey === $moduleKey) {
            $errors[] = '모듈 의존성 선언이 올바르지 않습니다.';
            continue;
        }

        $requiredModule = sr_module_record_entry($pdo, $requiredModuleKey);
        if (!is_array($requiredModule) || (string) ($requiredModule['status'] ?? '') !== 'enabled') {
            $errors[] = $requiredModuleKey . ' 모듈을 먼저 활성화해야 합니다.';
            continue;
        }

        $minimumVersion = is_string($key) ? (string) $value : '';
        if ($minimumVersion !== '' && strcmp((string) ($requiredModule['version'] ?? ''), $minimumVersion) < 0) {
            $errors[] = $requiredModuleKey . ' 모듈 ' . $minimumVersion . ' 이상이 필요합니다.';
        }
    }

    $requiredContracts = isset($requires['contracts']) && is_array($requires['contracts']) ? $requires['contracts'] : [];
    foreach ($requiredContracts as $contract) {
        if (!is_array($contract)) {
            $errors[] = '계약 파일 의존성 선언이 올바르지 않습니다.';
            continue;
        }

        $requiredModuleKey = (string) ($contract['module'] ?? '');
        $file = (string) ($contract['file'] ?? '');
        if (!sr_is_safe_module_key($requiredModuleKey) || preg_match('/\A[a-z0-9][a-z0-9_.-]{0,80}\.php\z/', $file) !== 1) {
            $errors[] = '계약 파일 의존성 선언이 올바르지 않습니다.';
            continue;
        }

        if (sr_module_record_status($pdo, $requiredModuleKey) !== 'enabled') {
            $errors[] = $requiredModuleKey . ' 모듈을 먼저 활성화해야 합니다.';
            continue;
        }

        if (!sr_module_contract_is_loadable($requiredModuleKey)) {
            $errors[] = $requiredModuleKey . ' 모듈 메타데이터/계약이 현재 Saanraan과 맞지 않습니다.';
            continue;
        }

        $requiredMetadata = sr_module_metadata($requiredModuleKey);
        if (!in_array($file, sr_module_declared_contract_files($requiredMetadata, 'provides'), true)) {
            $errors[] = $requiredModuleKey . ' 모듈의 module.php contracts.provides에 ' . $file . ' 선언이 필요합니다.';
            continue;
        }

        if (!is_file(SR_ROOT . '/modules/' . $requiredModuleKey . '/' . $file)) {
            $errors[] = $requiredModuleKey . ' 모듈의 ' . $file . ' 계약 파일이 필요합니다.';
        }
    }

    return array_values(array_unique($errors));
}

function sr_cast_setting_value(mixed $value, string $type): mixed
{
    if ($type === 'int') {
        return (int) $value;
    }

    if ($type === 'bool') {
        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }

    if ($type === 'json') {
        $decoded = json_decode((string) $value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    return $value === null ? '' : (string) $value;
}
