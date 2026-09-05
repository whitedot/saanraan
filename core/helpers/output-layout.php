<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

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

function sr_public_layout_support_domains(array $supports): array
{
    $domains = [];
    foreach (sr_public_layout_support_targets($supports) as $support) {
        $domain = sr_public_layout_target_domain($support);
        if ($domain !== '') {
            $domains[$domain] = $domain;
        }
    }

    return array_values($domains);
}

function sr_public_layout_domains(): array
{
    return ['site', 'content', 'community'];
}

function sr_public_layout_support_targets(array $supports): array
{
    $targets = [];
    $allowed = array_fill_keys(sr_public_layout_domains(), true);
    foreach ($supports as $support) {
        $support = is_string($support) ? strtolower(trim($support)) : '';
        if ($support === '') {
            continue;
        }

        if (isset($allowed[$support])) {
            $targets[$support] = $support;
            continue;
        }

        if (preg_match('/\A([a-z][a-z0-9_]{0,39})\.([a-z][a-z0-9_]{0,39})\z/', $support, $matches) !== 1) {
            continue;
        }

        $domain = (string) $matches[1];
        if (!isset($allowed[$domain])) {
            continue;
        }

        $targets[$support] = $support;
    }

    return array_values($targets);
}

function sr_public_layout_target_domain(string $target): string
{
    $target = strtolower(trim($target));
    if ($target === '') {
        return '';
    }

    $domain = strpos($target, '.') !== false ? strstr($target, '.', true) : $target;
    $domain = is_string($domain) ? $domain : '';
    $allowed = array_fill_keys(sr_public_layout_domains(), true);

    return isset($allowed[$domain]) ? $domain : '';
}

function sr_public_layout_option_supports_target(array $layoutOption, string $target): bool
{
    $normalizedTargets = sr_public_layout_support_targets([$target]);
    $target = (string) ($normalizedTargets[0] ?? '');
    if ($target === '') {
        return true;
    }

    $supportedTargets = isset($layoutOption['supports_targets']) && is_array($layoutOption['supports_targets'])
        ? sr_public_layout_support_targets($layoutOption['supports_targets'])
        : sr_public_layout_support_targets((array) ($layoutOption['supports_domains'] ?? ['site']));
    $supportedTargetMap = array_fill_keys($supportedTargets !== [] ? $supportedTargets : ['site'], true);
    if (isset($supportedTargetMap[$target])) {
        return true;
    }

    $domain = sr_public_layout_target_domain($target);
    return $domain !== '' && strpos($target, '.') !== false && isset($supportedTargetMap[$domain]);
}

function sr_public_layout_option_supports_targets(array $layoutOption, array $targets): bool
{
    $targets = sr_public_layout_support_targets($targets);
    if ($targets === []) {
        return true;
    }

    foreach ($targets as $target) {
        if (!sr_public_layout_option_supports_target($layoutOption, $target)) {
            return false;
        }
    }

    return true;
}

function sr_public_layout_filter_options_for_targets(array $layoutOptions, array $requiredTargets): array
{
    $requiredTargets = sr_public_layout_support_targets($requiredTargets);
    if ($requiredTargets === []) {
        return $layoutOptions;
    }

    $filtered = [];
    foreach ($layoutOptions as $layoutKey => $layoutOption) {
        if (!is_array($layoutOption)) {
            continue;
        }
        if (!sr_public_layout_option_supports_targets($layoutOption, $requiredTargets)) {
            continue;
        }

        $filtered[$layoutKey] = $layoutOption;
    }

    return $filtered;
}

function sr_public_layout_options_for_targets(?PDO $pdo, array $requiredTargets, bool $includeInstalledModules = false): array
{
    return sr_public_layout_filter_options_for_targets(sr_public_layout_options($pdo, $includeInstalledModules), $requiredTargets);
}

function sr_public_layout_normalized_option(string $layoutKey, array $layoutOption, string $fallbackProviderKey = ''): array
{
    $layoutOption['key'] = $layoutKey;
    $sourceType = (string) ($layoutOption['source_type'] ?? '');
    $assetOwner = (string) ($layoutOption['asset_owner'] ?? '');
    $providerModuleKey = (string) ($layoutOption['provider_module_key'] ?? $fallbackProviderKey);

    if ($sourceType === '') {
        $sourceType = $providerModuleKey === 'core' ? 'core_common' : 'built_in_module';
        $layoutOption['source_type'] = $sourceType;
    }
    if (!isset($layoutOption['source_key'])) {
        $layoutOption['source_key'] = $providerModuleKey !== '' ? $providerModuleKey : $fallbackProviderKey;
    }
    if ($assetOwner === '') {
        $layoutOption['asset_owner'] = $providerModuleKey === 'core' ? 'core' : 'module';
    }
    if (!isset($layoutOption['asset_owner_key'])) {
        $layoutOption['asset_owner_key'] = $providerModuleKey !== '' ? $providerModuleKey : $fallbackProviderKey;
    }

    $layoutOption['provider_module_key'] = $providerModuleKey !== '' ? $providerModuleKey : $fallbackProviderKey;

    $supports = isset($layoutOption['supports']) && is_array($layoutOption['supports']) ? $layoutOption['supports'] : ['site'];
    $supportsTargets = isset($layoutOption['supports_targets']) && is_array($layoutOption['supports_targets'])
        ? sr_public_layout_support_targets($layoutOption['supports_targets'])
        : sr_public_layout_support_targets($supports);
    if ($supportsTargets === [] && isset($layoutOption['supports_domains']) && is_array($layoutOption['supports_domains'])) {
        $supportsTargets = sr_public_layout_support_targets($layoutOption['supports_domains']);
    }
    $supportsTargets = $supportsTargets !== [] ? $supportsTargets : ['site'];
    $supportsDomains = sr_public_layout_support_domains($supportsTargets);
    $layoutOption['supports_targets'] = $supportsTargets;
    $layoutOption['supports_domains'] = $supportsDomains !== [] ? $supportsDomains : ['site'];
    $layoutOption['style_profile'] = sr_public_style_profile_key((string) ($layoutOption['style_profile'] ?? 'kit'));
    $layoutOption['layout_contract'] = (string) ($layoutOption['layout_contract'] ?? '1.0');
    $layoutOption['asset_ids'] = isset($layoutOption['asset_ids']) && is_array($layoutOption['asset_ids']) ? $layoutOption['asset_ids'] : [];
    $layoutOption['is_valid'] = !array_key_exists('is_valid', $layoutOption) || !empty($layoutOption['is_valid']);
    $layoutOption['warnings'] = isset($layoutOption['warnings']) && is_array($layoutOption['warnings']) ? $layoutOption['warnings'] : [];

    return $layoutOption;
}

function sr_public_layout_provider_key_from_layout_key(string $layoutKey): string
{
    $layoutKey = sr_public_layout_normalize_key($layoutKey);
    $separatorPosition = strpos($layoutKey, '.');
    if ($separatorPosition === false) {
        return '';
    }

    return substr($layoutKey, 0, $separatorPosition);
}

function sr_public_layout_contract_option_is_owned(string $layoutKey, array $layoutOption, string $moduleKey): bool
{
    if (!sr_is_safe_module_key($moduleKey)) {
        return false;
    }

    $layoutProviderKey = sr_public_layout_provider_key_from_layout_key($layoutKey);
    $declaredProviderKey = (string) ($layoutOption['provider_module_key'] ?? $moduleKey);

    return $layoutProviderKey === $moduleKey && $declaredProviderKey === $moduleKey;
}

function sr_public_layout_module_stylesheet(string $layoutKey, string $themeKey = ''): string
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

    return sr_public_layout_module_theme_asset_url($providerKey, $themeKey, 'layout.css');
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
        function_exists('sr_module_registry_cache_token') ? (string) sr_module_registry_cache_token() : 'registry-unknown',
    ]);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $options = [
        sr_public_layout_default_key() => sr_public_layout_normalized_option(sr_public_layout_default_key(), [
            'key' => sr_public_layout_default_key(),
            'label' => sr_t('public_layout.common.label'),
            'provider_module_key' => 'core',
            'provider_label' => sr_t('public_layout.common.provider'),
            'source_type' => 'core_common',
            'source_key' => 'common',
            'asset_owner' => 'core',
            'asset_owner_key' => 'common',
            'supports' => sr_public_layout_domains(),
            'supports_domains' => sr_public_layout_domains(),
            'style_profile' => 'kit',
            'layout_contract' => '1.0',
            'is_valid' => true,
            'warnings' => [],
            'views' => [
                'layout' => SR_ROOT . '/layouts/public/basic/layout.php',
                'home' => SR_ROOT . '/layouts/public/basic/home.php',
                'ui_kit' => SR_ROOT . '/layouts/public/basic/ui-kit.php',
            ],
        ], 'core'),
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
                if (!sr_public_layout_contract_option_is_owned($layoutKey, $layoutOption, (string) $moduleKey)) {
                    continue;
                }

                $layoutOption['key'] = $layoutKey;
                $layoutOption['provider_module_key'] = (string) $moduleKey;
                $layoutOption['asset_owner'] = 'module';
                $layoutOption['asset_owner_key'] = (string) $moduleKey;
                $options[$layoutKey] = sr_public_layout_normalized_option($layoutKey, $layoutOption, $moduleKey);
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

function sr_public_layout_file(string $layoutKey, ?PDO $pdo = null, bool $includeInstalledModules = false, string $themeKey = ''): string
{
    $layoutKey = sr_public_layout_normalize_key($layoutKey);
    $options = sr_public_layout_options($pdo, $includeInstalledModules);
    if (!isset($options[$layoutKey])) {
        $layoutKey = sr_public_layout_default_key();
    }

    $layoutFile = sr_public_layout_theme_view_file(is_array($options[$layoutKey] ?? null) ? $options[$layoutKey] : [], $themeKey, 'layout.php');
    if ($layoutFile === null) {
        $layoutFile = (string) ($options[$layoutKey]['views']['layout'] ?? '');
    }
    if ($layoutFile === '' || !is_file($layoutFile)) {
        $layoutFile = (string) ($options[sr_public_layout_default_key()]['views']['layout'] ?? '');
    }

    if ($layoutFile === '' || !is_file($layoutFile)) {
        throw new RuntimeException('기본 공개 레이아웃 파일이 누락되었습니다.');
    }

    return $layoutFile;
}

function sr_public_layout_theme_view_file(array $layoutOption, string $themeKey, string $viewFile): ?string
{
    if (preg_match('/\A[a-z0-9][a-z0-9_-]{0,79}\.php\z/', $viewFile) !== 1) {
        return null;
    }

    $providerKey = (string) ($layoutOption['provider_module_key'] ?? '');
    if ($providerKey === '' || $providerKey === 'core' || !sr_is_safe_module_key($providerKey)) {
        return null;
    }

    $themeKeys = sr_public_layout_module_theme_candidates($providerKey, $themeKey);
    foreach ($themeKeys as $candidateThemeKey) {
        $path = SR_ROOT . '/modules/' . $providerKey . '/theme/' . $candidateThemeKey . '/' . $viewFile;
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

function sr_public_layout_optional_view_file(string $layoutKey, string $viewKey, ?PDO $pdo = null, bool $includeInstalledModules = false, string $themeKey = ''): ?string
{
    if (preg_match('/\A[a-z0-9_]{1,40}\z/', $viewKey) !== 1) {
        return null;
    }

    $layoutKey = sr_public_layout_normalize_key($layoutKey);
    $options = sr_public_layout_options($pdo, $includeInstalledModules);
    if (!isset($options[$layoutKey])) {
        $layoutKey = sr_public_layout_default_key();
    }

    $themeViewFile = sr_public_layout_theme_view_file(is_array($options[$layoutKey] ?? null) ? $options[$layoutKey] : [], $themeKey, $viewKey . '.php');
    if ($themeViewFile !== null) {
        return $themeViewFile;
    }

    $viewFile = (string) ($options[$layoutKey]['views'][$viewKey] ?? '');
    if ($viewFile !== '' && is_file($viewFile)) {
        return $viewFile;
    }

    $fallbackFile = (string) ($options[sr_public_layout_default_key()]['views'][$viewKey] ?? '');
    return $fallbackFile !== '' && is_file($fallbackFile) ? $fallbackFile : null;
}

function sr_public_layout_option(string $layoutKey, ?PDO $pdo = null, bool $includeInstalledModules = false): ?array
{
    $layoutKey = sr_public_layout_normalize_key($layoutKey);
    $options = sr_public_layout_options($pdo, $includeInstalledModules);
    $option = $options[$layoutKey] ?? null;

    return is_array($option) ? $option : null;
}

function sr_public_layout_shell_stylesheets(string $layoutKey, ?PDO $pdo = null, bool $includeInstalledModules = false, string $themeKey = ''): array
{
    $option = sr_public_layout_option($layoutKey, $pdo, $includeInstalledModules);
    if (!is_array($option)) {
        return [];
    }

    $stylesheet = sr_public_layout_module_stylesheet($layoutKey, $themeKey);
    return $stylesheet !== '' ? [$stylesheet] : [];
}

function sr_public_layout_shell_scripts(string $layoutKey, ?PDO $pdo = null, bool $includeInstalledModules = false): array
{
    return [];
}

function sr_public_theme_default_key(): string
{
    return 'default';
}

function sr_view_theme_key_is_valid(string $themeKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $themeKey) === 1;
}

function sr_view_theme_normalize_key(string $themeKey): string
{
    $themeKey = strtolower(trim($themeKey));
    if ($themeKey === '' || $themeKey === sr_public_theme_default_key()) {
        return sr_public_theme_default_key();
    }

    return sr_view_theme_key_is_valid($themeKey) ? $themeKey : sr_public_theme_default_key();
}

function sr_view_theme_post_key(string $themeKey): string
{
    $themeKey = strtolower(trim($themeKey));
    if ($themeKey === '' || $themeKey === sr_public_theme_default_key()) {
        return sr_public_theme_default_key();
    }

    return sr_view_theme_key_is_valid($themeKey) ? $themeKey : '__invalid__';
}

function sr_view_theme_label(string $themeKey): string
{
    $themeKey = sr_view_theme_normalize_key($themeKey);
    if ($themeKey === sr_public_theme_default_key()) {
        return '기본 테마';
    }
    if ($themeKey === 'basic') {
        return '기본 테마';
    }
    return str_replace('_', ' ', $themeKey);
}

function sr_view_theme_options(string $themeRoot, array $requiredFiles, string $defaultLabel = '기본 테마', string $sourceType = 'module_view_theme', bool $includeDefault = true): array
{
    $options = [];
    if ($includeDefault) {
        $options[sr_public_theme_default_key()] = [
            'theme_key' => sr_public_theme_default_key(),
            'key' => sr_public_theme_default_key(),
            'label' => $defaultLabel,
            'source_type' => 'default_view',
            'view_root' => '',
            'required_files' => [],
            'view_files' => [],
            'is_valid' => true,
        ];
    }

    $realRoot = realpath($themeRoot);
    if (!is_string($realRoot) || !is_dir($realRoot)) {
        return $options;
    }

    $directories = glob($realRoot . '/*', GLOB_ONLYDIR);
    if (!is_array($directories)) {
        return $options;
    }
    sort($directories, SORT_NATURAL);

    foreach ($directories as $directory) {
        $themeKey = basename((string) $directory);
        if (!sr_view_theme_key_is_valid($themeKey) || $themeKey === sr_public_theme_default_key()) {
            continue;
        }

        $realDirectory = realpath((string) $directory);
        if (!is_string($realDirectory) || !str_starts_with($realDirectory, $realRoot . DIRECTORY_SEPARATOR)) {
            continue;
        }

        $missingFiles = [];
        foreach ($requiredFiles as $requiredFile) {
            $requiredFile = (string) $requiredFile;
            if ($requiredFile === '' || !is_file($realDirectory . '/' . $requiredFile)) {
                $missingFiles[] = $requiredFile;
            }
        }
        if ($missingFiles !== []) {
            continue;
        }

        $viewFiles = [];
        $phpFiles = glob($realDirectory . '/*.php');
        foreach (is_array($phpFiles) ? $phpFiles : [] as $phpFile) {
            $fileName = basename((string) $phpFile);
            if (preg_match('/\A[a-z0-9][a-z0-9_-]{0,79}\.php\z/', $fileName) !== 1) {
                continue;
            }
            $viewFiles[$fileName] = (string) $phpFile;
        }

        $options[$themeKey] = [
            'theme_key' => $themeKey,
            'key' => $themeKey,
            'label' => sr_view_theme_label($themeKey),
            'source_type' => $sourceType,
            'view_root' => $realDirectory,
            'required_files' => array_values(array_map('strval', $requiredFiles)),
            'view_files' => $viewFiles,
            'is_valid' => true,
        ];
    }

    return $options;
}

function sr_view_theme_key(string $themeKey, array $options): string
{
    $themeKey = sr_view_theme_normalize_key($themeKey);
    if (isset($options[$themeKey])) {
        return $themeKey;
    }

    return isset($options['basic']) ? 'basic' : sr_public_theme_default_key();
}

function sr_view_theme_file(string $themeRoot, string $themeKey, string $viewFile): ?string
{
    $themeKey = sr_view_theme_normalize_key($themeKey);
    if ($themeKey === sr_public_theme_default_key()) {
        return null;
    }
    if (preg_match('/\A[a-z0-9][a-z0-9_-]{0,79}\.php\z/', $viewFile) !== 1) {
        return null;
    }

    $realRoot = realpath($themeRoot);
    $realDirectory = is_string($realRoot) ? realpath($realRoot . '/' . $themeKey) : false;
    if (!is_string($realRoot) || !is_string($realDirectory) || !str_starts_with($realDirectory, $realRoot . DIRECTORY_SEPARATOR)) {
        return null;
    }

    $realFile = realpath($realDirectory . '/' . $viewFile);
    if (!is_string($realFile) || !str_starts_with($realFile, $realDirectory . DIRECTORY_SEPARATOR) || !is_file($realFile)) {
        return null;
    }

    return $realFile;
}

function sr_module_view_theme_stylesheet_url(string $moduleKey, string $themeKey): string
{
    return sr_module_view_theme_asset_url($moduleKey, $themeKey, 'theme.css');
}

function sr_public_layout_module_theme_candidates(string $moduleKey, string $themeKey): array
{
    if (sr_view_theme_key_is_valid($moduleKey) === false) {
        return [];
    }

    $themeKey = sr_view_theme_normalize_key($themeKey);
    $candidates = [];
    if ($themeKey !== sr_public_theme_default_key()) {
        $candidates[] = $themeKey;
    }
    $candidates[] = 'basic';

    return array_values(array_unique(array_filter($candidates, 'sr_view_theme_key_is_valid')));
}

function sr_public_layout_module_theme_asset_url(string $moduleKey, string $themeKey, string $assetFile): string
{
    foreach (sr_public_layout_module_theme_candidates($moduleKey, $themeKey) as $candidateThemeKey) {
        $assetPath = sr_module_view_theme_asset_url($moduleKey, $candidateThemeKey, $assetFile);
        if ($assetPath !== '') {
            return $assetPath;
        }
    }

    return '';
}

function sr_module_view_theme_asset_url(string $moduleKey, string $themeKey, string $assetFile): string
{
    if (sr_view_theme_key_is_valid($moduleKey) === false) {
        return '';
    }

    $themeKey = sr_view_theme_normalize_key($themeKey);
    if ($themeKey === sr_public_theme_default_key()) {
        return '';
    }
    if (preg_match('/\A[a-z0-9][a-z0-9_-]{0,79}\.(?:css|js)\z/', $assetFile) !== 1) {
        return '';
    }

    $relativePath = '/modules/' . $moduleKey . '/theme/' . rawurlencode($themeKey) . '/assets/' . $assetFile;
    if (is_file(SR_ROOT . $relativePath)) {
        return $relativePath;
    }

    return '';
}

function sr_module_view_theme_asset_url_or_default(string $moduleKey, string $themeKey, string $assetFile, string $defaultPath = ''): string
{
    $assetPath = sr_module_view_theme_asset_url($moduleKey, $themeKey, $assetFile);
    if ($assetPath !== '') {
        return $assetPath;
    }

    return $defaultPath !== '' && is_file(SR_ROOT . $defaultPath) ? $defaultPath : '';
}

function sr_public_theme_normalize_key(string $themeKey): string
{
    $themeKey = strtolower(trim($themeKey));
    if ($themeKey === '' || $themeKey === sr_public_theme_default_key()) {
        return sr_public_theme_default_key();
    }

    return sr_view_theme_key_is_valid($themeKey) || preg_match('/\A[a-z][a-z0-9_]{1,39}\.[a-z][a-z0-9_]{1,39}\z/', $themeKey) === 1
        ? $themeKey
        : sr_public_theme_default_key();
}

function sr_public_theme_options(?PDO $pdo = null, bool $includeInstalledModules = false): array
{
    $cache = $GLOBALS['sr_public_theme_options_runtime_cache'] ?? [];
    if (!is_array($cache)) {
        $cache = [];
    }

    $cacheKey = implode(':', [
        $pdo instanceof PDO ? (string) spl_object_id($pdo) : 'no-pdo',
        $includeInstalledModules ? 'installed' : 'enabled',
        function_exists('sr_locale') ? sr_locale() : 'ko',
    ]);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $options = [
        sr_public_theme_default_key() => [
            'theme_key' => sr_public_theme_default_key(),
            'key' => sr_public_theme_default_key(),
            'label' => '기본 테마',
            'source_type' => 'core_default',
            'source_key' => 'core',
            'provider_label' => 'Saanraan',
            'asset_owner' => 'core',
            'asset_owner_key' => 'core',
            'supports_domains' => sr_public_layout_domains(),
            'theme_contract' => '1.0',
            'views' => [],
            'asset_ids' => [],
            'assets' => [],
            'is_valid' => true,
            'warnings' => [],
        ],
    ];

    foreach (sr_view_theme_options(SR_ROOT . '/core/views/theme', ['home.php'], '기본 초기화면', 'core_view_theme', false) as $themeKey => $themeOption) {
        if ($themeKey === sr_public_theme_default_key() || isset($options[$themeKey]) || !is_array($themeOption)) {
            continue;
        }
        $homeViewFile = (string) ($themeOption['view_files']['home.php'] ?? '');
        if ($homeViewFile === '') {
            continue;
        }

        $options[(string) $themeKey] = [
            'theme_key' => (string) $themeKey,
            'key' => (string) $themeKey,
            'label' => (string) ($themeOption['label'] ?? $themeKey),
            'source_type' => 'core_view_theme',
            'source_key' => 'core',
            'provider_label' => 'Saanraan',
            'asset_owner' => 'core',
            'asset_owner_key' => 'core',
            'supports_domains' => ['site'],
            'theme_contract' => 'local-view-theme',
            'views' => [
                'home' => $homeViewFile,
            ],
            'asset_ids' => [],
            'assets' => [],
            'is_valid' => true,
            'warnings' => [],
        ];
    }

    $cache[$cacheKey] = $options;
    $GLOBALS['sr_public_theme_options_runtime_cache'] = $cache;

    return $cache[$cacheKey];
}

function sr_public_theme_key(?array $site = null, ?PDO $pdo = null): string
{
    $themeKey = is_array($site) && array_key_exists('public_theme_key', $site)
        ? (string) ($site['public_theme_key'] ?? sr_public_theme_default_key())
        : ($pdo instanceof PDO ? (string) sr_site_setting($pdo, 'public_theme_key', sr_public_theme_default_key()) : sr_public_theme_default_key());
    $themeKey = sr_public_theme_normalize_key($themeKey);
    $options = sr_public_theme_options($pdo);
    if ($themeKey === sr_public_theme_default_key() && isset($options['basic'])) {
        return 'basic';
    }

    return isset($options[$themeKey]) ? $themeKey : (isset($options['basic']) ? 'basic' : sr_public_theme_default_key());
}

function sr_public_theme_effective_key(string $themeKey, array $consumerDomains, ?PDO $pdo = null, bool $includeInstalledModules = false, string $scope = 'site'): string
{
    $themeKey = sr_public_theme_normalize_key($themeKey);
    $defaultKey = sr_public_theme_default_key();
    $options = sr_public_theme_options($pdo, $includeInstalledModules);
    if ($themeKey === $defaultKey && isset($options['basic'])) {
        $themeKey = 'basic';
    }
    if (!isset($options[$themeKey])) {
        return $defaultKey;
    }
    if ($themeKey === $defaultKey) {
        return $defaultKey;
    }

    $supportedDomains = array_fill_keys((array) ($options[$themeKey]['supports_domains'] ?? sr_public_layout_domains()), true);
    foreach ($consumerDomains as $domain) {
        $domain = is_string($domain) ? $domain : '';
        if ($domain === '' || isset($supportedDomains[$domain])) {
            continue;
        }

        return $defaultKey;
    }

    return $themeKey;
}

function sr_public_theme_option(string $themeKey, ?PDO $pdo = null, bool $includeInstalledModules = false): ?array
{
    $themeKey = sr_public_theme_normalize_key($themeKey);
    $options = sr_public_theme_options($pdo, $includeInstalledModules);
    $option = $options[$themeKey] ?? null;

    return is_array($option) ? $option : null;
}

function sr_public_theme_stylesheets(string $themeKey, ?PDO $pdo = null, bool $includeInstalledModules = false): array
{
    return [];
}

function sr_public_theme_scripts(string $themeKey, ?PDO $pdo = null, bool $includeInstalledModules = false): array
{
    return [];
}

function sr_public_theme_optional_view_file(string $themeKey, string $viewKey, ?PDO $pdo = null, bool $includeInstalledModules = false): ?string
{
    if (preg_match('/\A[a-z0-9_]{1,40}\z/', $viewKey) !== 1) {
        return null;
    }

    $option = sr_public_theme_option($themeKey, $pdo, $includeInstalledModules);
    if (!is_array($option)) {
        return null;
    }

    $viewFile = (string) ($option['views'][$viewKey] ?? '');
    return $viewFile !== '' && is_file($viewFile) ? $viewFile : null;
}

function sr_public_layout_context_with_theme_assets(array $layoutContext, string $themeKey, ?PDO $pdo = null, bool $includeInstalledModules = false): array
{
    $stylesheets = is_array($layoutContext['stylesheets'] ?? null) ? $layoutContext['stylesheets'] : [];
    $scripts = is_array($layoutContext['scripts'] ?? null) ? $layoutContext['scripts'] : [];
    $layoutContext['stylesheets'] = sr_public_layout_insert_before_module_asset($stylesheets, sr_public_theme_stylesheets($themeKey, $pdo, $includeInstalledModules));
    $layoutContext['scripts'] = sr_public_layout_insert_before_module_asset($scripts, sr_public_theme_scripts($themeKey, $pdo, $includeInstalledModules));

    return $layoutContext;
}

function sr_public_layout_insert_before_module_asset(array $assets, array $insertAssets): array
{
    $insertAssets = array_values(array_filter(array_unique($insertAssets), 'strlen'));
    if ($insertAssets === []) {
        return array_values(array_unique($assets));
    }

    $result = [];
    $inserted = false;
    foreach ($assets as $asset) {
        $asset = is_string($asset) ? $asset : '';
        if ($asset === '') {
            continue;
        }
        if (!$inserted && preg_match('#/modules/[a-z][a-z0-9_]{1,39}/(?:assets|theme/[a-z][a-z0-9_]{1,39}/assets)/module\.(?:css|js)(?:\?|$)#', $asset) === 1) {
            foreach ($insertAssets as $insertAsset) {
                $result[] = $insertAsset;
            }
            $inserted = true;
        }
        $result[] = $asset;
    }

    if (!$inserted) {
        foreach ($insertAssets as $insertAsset) {
            $result[] = $insertAsset;
        }
    }

    return array_values(array_unique($result));
}

function sr_public_layout_context_with_shell_assets(array $layoutContext, string $layoutKey, ?PDO $pdo = null, bool $includeInstalledModules = false): array
{
    $stylesheets = is_array($layoutContext['stylesheets'] ?? null) ? $layoutContext['stylesheets'] : [];
    $scripts = is_array($layoutContext['scripts'] ?? null) ? $layoutContext['scripts'] : [];
    $themeKey = is_string($layoutContext['theme_key'] ?? null) ? (string) $layoutContext['theme_key'] : '';
    $layoutContext['stylesheets'] = sr_public_layout_insert_before_module_asset($stylesheets, sr_public_layout_shell_stylesheets($layoutKey, $pdo, $includeInstalledModules, $themeKey));
    $layoutContext['scripts'] = sr_public_layout_insert_before_module_asset($scripts, sr_public_layout_shell_scripts($layoutKey, $pdo, $includeInstalledModules));

    return $layoutContext;
}

function sr_public_route_domains(PDO $pdo, ?array $site = null): array
{
    $domains = ['site' => 'site'];
    $allowed = array_fill_keys(sr_public_layout_domains(), true);

    foreach (sr_enabled_module_contract_files($pdo, 'paths.php', ['admin']) as $moduleKey => $pathsFile) {
        if (!isset($allowed[$moduleKey])) {
            continue;
        }
        $paths = sr_load_module_contract_file($moduleKey, $pathsFile);
        if (!is_array($paths)) {
            continue;
        }
        foreach ($paths as $route => $_actionRelativePath) {
            if (sr_public_route_domain_candidate((string) $route)) {
                $domains[$moduleKey] = $moduleKey;
                break;
            }
        }
    }

    foreach (sr_enabled_module_contract_files($pdo, 'homepage-candidates.php') as $moduleKey => $_candidatesFile) {
        if (isset($allowed[$moduleKey])) {
            $domains[$moduleKey] = $moduleKey;
        }
    }

    $homePath = is_array($site) ? (string) ($site['home_path'] ?? '') : '';
    if ($homePath !== '' && $homePath !== '/') {
        foreach (array_keys($allowed) as $domain) {
            if ($domain !== 'site' && ($homePath === '/' . $domain || str_starts_with($homePath, '/' . $domain . '/'))) {
                $domains[$domain] = $domain;
            }
        }
    }

    $ordered = [];
    foreach (sr_public_layout_domains() as $domain) {
        if (isset($domains[$domain])) {
            $ordered[] = $domain;
        }
    }

    return $ordered;
}

function sr_public_route_domain_candidate(string $route): bool
{
    if (!str_starts_with($route, 'GET ')) {
        return false;
    }

    $path = trim(substr($route, 4));
    if ($path === '' || $path === '/') {
        return true;
    }
    foreach (['/admin', '/account', '/install', '/oauth'] as $privatePrefix) {
        if ($path === $privatePrefix || str_starts_with($path, $privatePrefix . '/')) {
            return false;
        }
    }

    return true;
}

function sr_public_layout_effective_key(string $layoutKey, array $consumerTargets, ?PDO $pdo = null, bool $includeInstalledModules = false, string $scope = 'site'): string
{
    $layoutKey = sr_public_layout_normalize_key($layoutKey);
    $defaultKey = sr_public_layout_default_key();
    $options = sr_public_layout_options($pdo, $includeInstalledModules);
    if (!isset($options[$layoutKey])) {
        sr_public_layout_record_fallback($scope, $layoutKey, 'missing', $defaultKey);
        return $defaultKey;
    }
    if ($layoutKey === $defaultKey) {
        return $defaultKey;
    }

    $layoutOption = is_array($options[$layoutKey] ?? null) ? $options[$layoutKey] : [];
    $consumerTargets = sr_public_layout_support_targets($consumerTargets);
    $consumerTargets = $consumerTargets !== [] ? $consumerTargets : ['site'];
    foreach ($consumerTargets as $target) {
        $target = is_string($target) ? $target : '';
        if ($target === '' || sr_public_layout_option_supports_target($layoutOption, $target)) {
            continue;
        }

        sr_public_layout_record_fallback($scope, $layoutKey, $target, $defaultKey);
        return $defaultKey;
    }

    return $layoutKey;
}

function sr_public_layout_context_consumer_targets(array $layoutContext, ?PDO $pdo = null, ?array $site = null): array
{
    $targets = [];
    if (isset($layoutContext['consumer_targets']) && is_array($layoutContext['consumer_targets'])) {
        $targets = sr_public_layout_support_targets($layoutContext['consumer_targets']);
    } elseif (isset($layoutContext['consumer_target']) && is_string($layoutContext['consumer_target'])) {
        $targets = sr_public_layout_support_targets([(string) $layoutContext['consumer_target']]);
    }

    if ($targets !== []) {
        return $targets;
    }

    $consumerDomain = is_string($layoutContext['consumer_domain'] ?? null) ? (string) $layoutContext['consumer_domain'] : '';
    if ($consumerDomain !== '') {
        $targets = sr_public_layout_support_targets([$consumerDomain]);
        if ($targets !== []) {
            return $targets;
        }
    }

    return $pdo instanceof PDO ? sr_public_route_domains($pdo, $site) : ['site'];
}

function sr_public_layout_consumer_domains_from_targets(array $targets): array
{
    $domains = [];
    foreach (sr_public_layout_support_targets($targets) as $target) {
        $domain = sr_public_layout_target_domain($target);
        if ($domain !== '') {
            $domains[$domain] = $domain;
        }
    }

    return array_values($domains);
}

function sr_public_layout_record_fallback(string $scope, string $layoutKey, string $unsupportedTarget, string $fallbackLayoutKey): void
{
    $warnings = $GLOBALS['sr_public_layout_fallback_warnings'] ?? [];
    if (!is_array($warnings)) {
        $warnings = [];
    }
    $dedupeKey = $scope . '|' . $layoutKey . '|' . $unsupportedTarget;
    $warnings[$dedupeKey] = [
        'scope' => $scope,
        'layout_key' => $layoutKey,
        'unsupported_domain' => sr_public_layout_target_domain($unsupportedTarget) ?: $unsupportedTarget,
        'unsupported_target' => $unsupportedTarget,
        'fallback_layout_key' => $fallbackLayoutKey,
    ];
    $GLOBALS['sr_public_layout_fallback_warnings'] = $warnings;
}

function sr_public_layout_health_warnings(PDO $pdo, ?array $site = null): array
{
    $warnings = [];
    $defaultKey = sr_public_layout_default_key();
    $options = sr_public_layout_options($pdo, true);
    $siteLayoutKey = sr_public_layout_normalize_key(is_array($site) ? (string) ($site['public_layout_key'] ?? $defaultKey) : $defaultKey);
    $activeTargets = sr_public_route_domains($pdo, $site);
    sr_public_layout_collect_health_warnings($warnings, 'site.public_layout', $siteLayoutKey, $activeTargets, $options, $defaultKey);

    try {
        $stmt = $pdo->query("SELECT mod.module_key, s.setting_value AS layout_key FROM sr_module_settings s INNER JOIN sr_modules mod ON mod.id = s.module_id WHERE s.setting_key = 'layout_key' AND mod.status = 'enabled'");
        if ($stmt instanceof PDOStatement) {
            foreach ($stmt->fetchAll() as $row) {
                $moduleKey = (string) ($row['module_key'] ?? '');
                if (!in_array($moduleKey, sr_public_layout_domains(), true) || $moduleKey === 'site') {
                    continue;
                }
                $targets = sr_public_layout_module_setting_targets($moduleKey);
                sr_public_layout_collect_health_warnings($warnings, $moduleKey . '.layout', sr_public_layout_normalize_key((string) ($row['layout_key'] ?? '')), $targets, $options, $defaultKey);
            }
        }
    } catch (Throwable) {
        return array_values($warnings);
    }

    return array_values($warnings);
}

function sr_public_layout_module_setting_targets(string $moduleKey): array
{
    return [
        'content' => ['content.home', 'content.group', 'content.view', 'content.form', 'content.search'],
        'community' => ['community.home', 'community.group', 'community.list', 'community.post', 'community.form', 'community.search'],
    ][$moduleKey] ?? [$moduleKey];
}

function sr_public_layout_collect_health_warnings(array &$warnings, string $scope, string $layoutKey, array $targets, array $options, string $defaultKey): void
{
    if ($layoutKey === '' || $layoutKey === $defaultKey) {
        return;
    }
    if (!isset($options[$layoutKey])) {
        $dedupeKey = $scope . '|' . $layoutKey . '|missing';
        $warnings[$dedupeKey] = [
            'scope' => $scope,
            'layout_key' => $layoutKey,
            'unsupported_domain' => 'missing',
            'fallback_layout_key' => $defaultKey,
        ];
        return;
    }

    $layoutOption = is_array($options[$layoutKey] ?? null) ? $options[$layoutKey] : [];
    $targets = sr_public_layout_support_targets($targets);
    $targets = $targets !== [] ? $targets : ['site'];
    foreach ($targets as $target) {
        $target = (string) $target;
        if ($target === '' || sr_public_layout_option_supports_target($layoutOption, $target)) {
            continue;
        }
        $dedupeKey = $scope . '|' . $layoutKey . '|' . $target;
        $warnings[$dedupeKey] = [
            'scope' => $scope,
            'layout_key' => $layoutKey,
            'unsupported_domain' => sr_public_layout_target_domain($target) ?: $target,
            'unsupported_target' => $target,
            'fallback_layout_key' => $defaultKey,
        ];
    }
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
    $layoutContext = is_array($layoutState['layout_context'] ?? null) ? $layoutState['layout_context'] : [];
    $layoutKey = (string) ($layoutContext['layout_key'] ?? '');
    if ($layoutKey === '') {
        $layoutKey = sr_public_layout_key($site, $pdo instanceof PDO ? $pdo : null);
    } else {
        $layoutKey = sr_public_layout_normalize_key($layoutKey);
    }
    $includeInstalledLayoutOptions = !empty($layoutContext['include_installed_layout_options']);
    $consumerDomain = is_string($layoutContext['consumer_domain'] ?? null) ? (string) $layoutContext['consumer_domain'] : '';
    $consumerTargets = sr_public_layout_context_consumer_targets($layoutContext, $pdo instanceof PDO ? $pdo : null, $site);
    $consumerDomains = sr_public_layout_consumer_domains_from_targets($consumerTargets);
    $consumerDomains = $consumerDomains !== [] ? $consumerDomains : ($consumerDomain !== '' ? [$consumerDomain] : ['site']);
    $layoutScope = is_string($layoutContext['layout_scope'] ?? null) ? (string) $layoutContext['layout_scope'] : ($consumerDomain !== '' ? $consumerDomain . '.layout' : 'site.public_layout');
    $layoutKey = sr_public_layout_effective_key($layoutKey, $consumerTargets, $pdo instanceof PDO ? $pdo : null, $includeInstalledLayoutOptions, $layoutScope);
    $moduleViewThemeDomains = ['content', 'community'];
    $usesModuleViewTheme = array_intersect($consumerDomains, $moduleViewThemeDomains) !== [];
    $themeKey = (string) ($layoutContext['theme_key'] ?? '');
    if ($themeKey === '') {
        $themeKey = $usesModuleViewTheme ? 'basic' : sr_public_theme_key($site, $pdo instanceof PDO ? $pdo : null);
    } else {
        $themeKey = $usesModuleViewTheme ? sr_view_theme_normalize_key($themeKey) : sr_public_theme_normalize_key($themeKey);
    }
    if ($usesModuleViewTheme && $themeKey === sr_public_theme_default_key()) {
        $themeKey = 'basic';
    }
    $themeScope = is_string($layoutContext['theme_scope'] ?? null) ? (string) $layoutContext['theme_scope'] : ($consumerDomain !== '' ? $consumerDomain . '.theme' : 'site.public_theme');
    if (!$usesModuleViewTheme) {
        $themeKey = sr_public_theme_effective_key($themeKey, $consumerDomains, $pdo instanceof PDO ? $pdo : null, $includeInstalledLayoutOptions, $themeScope);
    }
    $layoutFile = sr_public_layout_file($layoutKey, $pdo instanceof PDO ? $pdo : null, $includeInstalledLayoutOptions, $themeKey);
    $layoutContext['layout_key'] = $layoutKey;
    $layoutContext['theme_key'] = $themeKey;
    if (!isset($layoutContext['style_profile'])) {
        $layoutOptions = sr_public_layout_options($pdo instanceof PDO ? $pdo : null, $includeInstalledLayoutOptions);
        $layoutProfile = (string) ($layoutOptions[$layoutKey]['style_profile'] ?? 'kit');
        if ($layoutProfile === 'minimal' && sr_public_layout_shell_stylesheets($layoutKey, $pdo instanceof PDO ? $pdo : null, $includeInstalledLayoutOptions, $themeKey) !== []) {
            $layoutProfile = 'kit';
        }
        $layoutContext['style_profile'] = sr_public_style_profile_key($layoutProfile);
    }
    if ($pdo instanceof PDO) {
        $layoutContext = sr_public_layout_context_with_output_slot_assets($pdo, $layoutContext, sr_public_layout_output_slot_contexts($layoutContext, $consumerDomains));
    }
    $layoutContext = sr_public_layout_context_with_shell_assets($layoutContext, $layoutKey, $pdo instanceof PDO ? $pdo : null, $includeInstalledLayoutOptions);
    if (!$usesModuleViewTheme) {
        $layoutContext = sr_public_layout_context_with_theme_assets($layoutContext, $themeKey, $pdo instanceof PDO ? $pdo : null, $includeInstalledLayoutOptions);
    }

    include $layoutFile;
}
