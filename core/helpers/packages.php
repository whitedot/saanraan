<?php

declare(strict_types=1);

function sr_package_root(): string
{
    return SR_ROOT . '/sr-packages';
}

function sr_package_type_root(string $packageType, string $moduleKey = ''): string
{
    if ($packageType === 'theme') {
        return sr_package_root() . '/themes';
    }

    if ($packageType === 'skin' && sr_is_safe_module_key($moduleKey)) {
        return sr_package_root() . '/skins/' . $moduleKey;
    }

    return '';
}

function sr_package_skin_contracts(): array
{
    return [
        'community' => ['version' => '1.0', 'views' => ['list', 'post', 'form']],
        'quiz' => ['version' => '1.0', 'views' => ['home', 'view', 'result']],
        'survey' => ['version' => '1.0', 'views' => ['home', 'view', 'complete']],
    ];
}

function sr_package_public_domains(): array
{
    return ['site', 'content', 'community', 'quiz', 'survey'];
}

function sr_package_manifest_error(array &$result, string $group, string $message): void
{
    $group = in_array($group, ['structure', 'version', 'path', 'asset', 'contract'], true) ? $group : 'structure';
    $result['errors'][] = $message;
    if (!isset($result['error_groups'][$group]) || !is_array($result['error_groups'][$group])) {
        $result['error_groups'][$group] = [];
    }
    $result['error_groups'][$group][] = $message;
}

function sr_package_empty_manifest_result(string $packageType, string $packageKey, string $packageRoot, string $manifestFile, string $moduleKey = ''): array
{
    return [
        'type' => $packageType,
        'module_key' => $moduleKey,
        'key' => $packageKey,
        'package_key' => $packageKey,
        'root' => $packageRoot,
        'manifest_file' => $manifestFile,
        'manifest' => [],
        'label' => $packageKey,
        'provider_label' => '외부 패키지',
        'author' => '',
        'license' => '',
        'source_url' => '',
        'version' => '',
        'saanraan_min_version' => '',
        'contract_version' => '',
        'style_profile' => '',
        'supports_domains' => [],
        'views' => [],
        'assets' => [],
        'asset_ids' => [],
        'is_valid' => false,
        'status' => 'invalid',
        'trust_level' => 'privileged_php',
        'trust_warning' => '스킨·테마 패키지는 적용 시 애플리케이션 권한으로 실행되는 특권 PHP 코드입니다.',
        'warnings' => [],
        'errors' => [],
        'error_groups' => [
            'structure' => [],
            'version' => [],
            'path' => [],
            'asset' => [],
            'contract' => [],
        ],
    ];
}

function sr_package_key_is_valid(string $packageType, string $packageKey): bool
{
    if ($packageType === 'theme') {
        return preg_match('/\A[a-z][a-z0-9_]{1,39}\.[a-z][a-z0-9_]{1,39}\z/', $packageKey) === 1
            && $packageKey !== 'common.basic'
            && !in_array(strtok($packageKey, '.') ?: '', ['common', 'core'], true);
    }

    if ($packageType === 'skin') {
        return preg_match('/\A[a-z][a-z0-9]{1,39}_[a-z][a-z0-9_]{0,39}\z/', $packageKey) === 1;
    }

    return false;
}

function sr_package_asset_id_is_valid(string $assetId): bool
{
    return preg_match('/\A[a-z][a-z0-9_-]{0,63}\z/', $assetId) === 1;
}

function sr_package_relative_path_is_safe(string $path): bool
{
    $path = trim($path);
    if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, "\0")) {
        return false;
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1 || str_contains($path, '://')) {
        return false;
    }

    foreach (explode('/', $path) as $segment) {
        if (
            $segment === ''
            || $segment === '.'
            || $segment === '..'
            || str_starts_with($segment, '.')
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,120}\z/', $segment) !== 1
        ) {
            return false;
        }
    }

    return true;
}

function sr_package_resolve_file(string $packageRoot, string $relativePath): string
{
    if (!sr_package_relative_path_is_safe($relativePath)) {
        return '';
    }

    $realRoot = realpath($packageRoot);
    $realFile = realpath($packageRoot . '/' . $relativePath);
    if (!is_string($realRoot) || !is_string($realFile) || !is_file($realFile)) {
        return '';
    }

    return str_starts_with($realFile, $realRoot . DIRECTORY_SEPARATOR) ? $realFile : '';
}

function sr_package_manifest_file_errors(string $packageRoot): array
{
    $errors = [];
    $realRoot = realpath($packageRoot);
    if (!is_string($realRoot) || !is_dir($realRoot)) {
        return ['패키지 루트 디렉터리를 찾을 수 없습니다.'];
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($realRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
            continue;
        }
        $name = $file->getBasename();
        if (str_starts_with($name, '.')) {
            $errors[] = '숨김 파일 또는 dotfile은 패키지에 포함할 수 없습니다: ' . $name;
            continue;
        }
        if (!$file->isFile()) {
            continue;
        }
        $extension = strtolower($file->getExtension());
        if (in_array($extension, ['phtml', 'phar', 'sql', 'sh', 'bat', 'cmd', 'exe'], true)) {
            $errors[] = '실행 또는 배포에 불필요한 파일 형식은 포함할 수 없습니다: ' . $name;
        }
    }

    return array_values(array_unique($errors));
}

function sr_package_decode_manifest(string $manifestFile): array
{
    $json = is_file($manifestFile) ? file_get_contents($manifestFile) : false;
    if (!is_string($json)) {
        return ['manifest' => null, 'error' => 'manifest 파일을 읽을 수 없습니다.'];
    }

    $manifest = json_decode($json, true);
    if (!is_array($manifest)) {
        return ['manifest' => null, 'error' => 'manifest JSON 구조가 올바르지 않습니다.'];
    }

    return ['manifest' => $manifest, 'error' => ''];
}

function sr_package_manifest_string(array $manifest, string $key, int $maxLength = 160): string
{
    $value = $manifest[$key] ?? '';
    if (!is_string($value) && !is_numeric($value)) {
        return '';
    }

    return sr_clean_single_line((string) $value, $maxLength);
}

function sr_package_manifest_saanraan(array $manifest): array
{
    return is_array($manifest['saanraan'] ?? null) ? $manifest['saanraan'] : [];
}

function sr_package_asset_content_types(): array
{
    return [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];
}

function sr_package_normalize_assets(array $manifest, string $packageType, string $packageKey, string $packageRoot, string $moduleKey, array &$result): array
{
    $rawAssets = $manifest['assets'] ?? [];
    if ($rawAssets === null) {
        return [];
    }
    if (!is_array($rawAssets)) {
        sr_package_manifest_error($result, 'asset', 'assets는 asset id와 파일 경로의 객체여야 합니다.');
        return [];
    }

    $contentTypes = sr_package_asset_content_types();
    $assets = [];
    foreach ($rawAssets as $assetId => $assetDefinition) {
        $assetId = is_string($assetId) ? $assetId : '';
        $relativePath = '';
        if (is_string($assetDefinition)) {
            $relativePath = $assetDefinition;
        } elseif (is_array($assetDefinition) && is_string($assetDefinition['file'] ?? null)) {
            $relativePath = (string) $assetDefinition['file'];
        }

        if (!sr_package_asset_id_is_valid($assetId)) {
            sr_package_manifest_error($result, 'asset', 'asset id가 올바르지 않습니다: ' . $assetId);
            continue;
        }
        if (!sr_package_relative_path_is_safe($relativePath)) {
            sr_package_manifest_error($result, 'asset', 'asset 경로가 올바르지 않습니다: ' . $assetId);
            continue;
        }

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        if (!isset($contentTypes[$extension])) {
            sr_package_manifest_error($result, 'asset', '허용되지 않는 asset 확장자입니다: ' . $assetId);
            continue;
        }

        $file = sr_package_resolve_file($packageRoot, $relativePath);
        if ($file === '') {
            sr_package_manifest_error($result, 'asset', 'asset 파일을 찾을 수 없거나 패키지 밖을 가리킵니다: ' . $assetId);
            continue;
        }

        $assets[$assetId] = [
            'id' => $assetId,
            'path' => $relativePath,
            'file' => $file,
            'extension' => $extension,
            'content_type' => $contentTypes[$extension],
            'cache_buster' => sr_package_asset_cache_buster($file, (string) ($manifest['version'] ?? '')),
        ];
        $assets[$assetId]['url'] = sr_package_asset_url_from_buster($packageType, $packageKey, $assetId, $moduleKey, (string) $assets[$assetId]['cache_buster']);
    }

    return $assets;
}

function sr_package_asset_cache_buster(string $file, string $packageVersion = ''): string
{
    $mtime = filemtime($file);
    $size = filesize($file);
    $source = $packageVersion . '|' . (is_int($mtime) ? (string) $mtime : '0') . '|' . (is_int($size) ? (string) $size : '0') . '|' . basename($file);

    return substr(sha1($source), 0, 16);
}

function sr_package_asset_url(string $packageType, string $packageKey, string $assetId, string $moduleKey = ''): string
{
    $asset = sr_package_asset_definition($packageType, $packageKey, $assetId, $moduleKey);
    $cacheBuster = is_array($asset) ? (string) ($asset['cache_buster'] ?? '') : '';
    return sr_package_asset_url_from_buster($packageType, $packageKey, $assetId, $moduleKey, $cacheBuster);
}

function sr_package_asset_url_from_buster(string $packageType, string $packageKey, string $assetId, string $moduleKey = '', string $cacheBuster = ''): string
{
    $query = [
        'type' => $packageType,
        'key' => $packageKey,
        'asset' => $assetId,
    ];
    if ($moduleKey !== '') {
        $query['module'] = $moduleKey;
    }
    if ($cacheBuster !== '') {
        $query['v'] = $cacheBuster;
    }

    return '/sr-package-asset?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function sr_package_theme_candidates(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];
    $themeRoot = sr_package_type_root('theme');
    if (!is_dir($themeRoot)) {
        return $cache;
    }

    foreach (scandir($themeRoot) ?: [] as $packageKey) {
        if (!is_string($packageKey) || $packageKey === '.' || $packageKey === '..') {
            continue;
        }
        $packageRoot = $themeRoot . '/' . $packageKey;
        if (!is_dir($packageRoot)) {
            continue;
        }
        $candidate = sr_package_validate_theme_manifest($packageKey, $packageRoot . '/theme.json', $packageRoot);
        $cache[(string) $candidate['key']] = $candidate;
    }

    ksort($cache);
    return $cache;
}

function sr_package_skin_candidates(string $moduleKey): array
{
    static $cache = [];
    if (!sr_is_safe_module_key($moduleKey)) {
        return [];
    }
    if (isset($cache[$moduleKey])) {
        return $cache[$moduleKey];
    }

    $cache[$moduleKey] = [];
    $skinRoot = sr_package_type_root('skin', $moduleKey);
    if (!is_dir($skinRoot) || !isset(sr_package_skin_contracts()[$moduleKey])) {
        return $cache[$moduleKey];
    }

    foreach (scandir($skinRoot) ?: [] as $packageKey) {
        if (!is_string($packageKey) || $packageKey === '.' || $packageKey === '..') {
            continue;
        }
        $packageRoot = $skinRoot . '/' . $packageKey;
        if (!is_dir($packageRoot)) {
            continue;
        }
        $candidate = sr_package_validate_skin_manifest($moduleKey, $packageKey, $packageRoot . '/skin.json', $packageRoot);
        $cache[$moduleKey][(string) $candidate['key']] = $candidate;
    }

    ksort($cache[$moduleKey]);
    return $cache[$moduleKey];
}

function sr_package_validate_theme_manifest(string $directoryKey, string $manifestFile, string $packageRoot): array
{
    $result = sr_package_empty_manifest_result('theme', $directoryKey, $packageRoot, $manifestFile);
    $decoded = sr_package_decode_manifest($manifestFile);
    if (!is_array($decoded['manifest'] ?? null)) {
        sr_package_manifest_error($result, 'structure', (string) ($decoded['error'] ?? 'theme.json을 읽을 수 없습니다.'));
        return $result;
    }

    $manifest = $decoded['manifest'];
    $result['manifest'] = $manifest;
    $manifestKey = sr_package_manifest_string($manifest, 'key', 80);
    if ($manifestKey !== '') {
        $result['key'] = $manifestKey;
        $result['package_key'] = $manifestKey;
    }
    if ($manifestKey === '' || $manifestKey !== $directoryKey || !sr_package_key_is_valid('theme', $manifestKey)) {
        sr_package_manifest_error($result, 'structure', 'theme key는 디렉터리명과 같은 vendor.theme 형식이어야 합니다.');
    }
    if ((string) ($manifest['type'] ?? '') !== 'theme') {
        sr_package_manifest_error($result, 'structure', 'theme manifest type은 theme이어야 합니다.');
    }
    if (array_key_exists('provider_module_key', $manifest)) {
        sr_package_manifest_error($result, 'structure', '외부 theme manifest에는 provider_module_key를 사용할 수 없습니다.');
    }

    sr_package_validate_common_manifest_fields($manifest, $result);

    $layoutContract = sr_package_manifest_string($manifest, 'layout_contract', 20);
    if ($layoutContract !== '1.0') {
        sr_package_manifest_error($result, 'contract', 'theme layout_contract는 1.0이어야 합니다.');
    }
    $result['contract_version'] = $layoutContract;

    $styleProfile = sr_package_manifest_string($manifest, 'style_profile', 20);
    if (!in_array($styleProfile, ['minimal', 'kit'], true)) {
        sr_package_manifest_error($result, 'structure', '외부 theme style_profile은 minimal 또는 kit이어야 합니다.');
    }
    $result['style_profile'] = $styleProfile !== '' ? $styleProfile : 'kit';

    $supports = $manifest['supports'] ?? [];
    if (!is_array($supports) || $supports === []) {
        sr_package_manifest_error($result, 'contract', 'theme supports 도메인이 필요합니다.');
    } else {
        $domains = [];
        foreach ($supports as $domain) {
            $domain = is_string($domain) ? strtolower(trim($domain)) : '';
            if (!in_array($domain, sr_package_public_domains(), true)) {
                sr_package_manifest_error($result, 'contract', 'theme supports는 site/content/community/quiz/survey 도메인만 허용합니다.');
                continue;
            }
            $domains[$domain] = $domain;
        }
        $result['supports_domains'] = array_values($domains);
    }

    $result['views'] = sr_package_normalize_view_map($manifest, $packageRoot, ['layout'], ['home'], $result);
    $result['assets'] = sr_package_normalize_assets($manifest, 'theme', (string) $result['key'], $packageRoot, '', $result);
    $result['asset_ids'] = array_keys($result['assets']);
    sr_package_apply_metadata_fields($manifest, $result);
    sr_package_apply_file_scan_errors($packageRoot, $result);
    sr_package_finalize_manifest_result($result);

    return $result;
}

function sr_package_validate_skin_manifest(string $moduleKey, string $directoryKey, string $manifestFile, string $packageRoot): array
{
    $result = sr_package_empty_manifest_result('skin', $directoryKey, $packageRoot, $manifestFile, $moduleKey);
    $contracts = sr_package_skin_contracts();
    $contract = $contracts[$moduleKey] ?? null;
    if (!is_array($contract)) {
        sr_package_manifest_error($result, 'contract', 'v1 스킨 패키지 대상 모듈이 아닙니다.');
        return $result;
    }

    $decoded = sr_package_decode_manifest($manifestFile);
    if (!is_array($decoded['manifest'] ?? null)) {
        sr_package_manifest_error($result, 'structure', (string) ($decoded['error'] ?? 'skin.json을 읽을 수 없습니다.'));
        return $result;
    }

    $manifest = $decoded['manifest'];
    $result['manifest'] = $manifest;
    $manifestKey = sr_package_manifest_string($manifest, 'key', 80);
    if ($manifestKey !== '') {
        $result['key'] = $manifestKey;
        $result['package_key'] = $manifestKey;
    }
    if ($manifestKey === '' || $manifestKey !== $directoryKey || !sr_package_key_is_valid('skin', $manifestKey)) {
        sr_package_manifest_error($result, 'structure', 'skin key는 디렉터리명과 같은 vendor_package 형식이어야 합니다.');
    }
    if ((string) ($manifest['type'] ?? '') !== 'skin') {
        sr_package_manifest_error($result, 'structure', 'skin manifest type은 skin이어야 합니다.');
    }
    if (isset($manifest['module']) && (string) $manifest['module'] !== $moduleKey) {
        sr_package_manifest_error($result, 'contract', 'skin manifest module이 설치 위치와 일치하지 않습니다.');
    }

    sr_package_validate_common_manifest_fields($manifest, $result);

    $saanraan = sr_package_manifest_saanraan($manifest);
    $moduleContract = is_string($saanraan['module_contract'] ?? null) ? (string) $saanraan['module_contract'] : '';
    if ($moduleContract === '') {
        sr_package_manifest_error($result, 'contract', 'saanraan.module_contract가 필요합니다.');
    } elseif ($moduleContract !== (string) $contract['version']) {
        sr_package_manifest_error($result, 'contract', 'saanraan.module_contract가 ' . $moduleKey . ' 스킨 계약 버전과 맞지 않습니다.');
    }
    $result['contract_version'] = $moduleContract;

    $requiredViews = array_values(array_map('strval', (array) ($contract['views'] ?? [])));
    $result['views'] = sr_package_normalize_view_map($manifest, $packageRoot, $requiredViews, [], $result);
    $result['assets'] = sr_package_normalize_assets($manifest, 'skin', (string) $result['key'], $packageRoot, $moduleKey, $result);
    $result['asset_ids'] = array_keys($result['assets']);
    sr_package_apply_metadata_fields($manifest, $result);
    sr_package_apply_file_scan_errors($packageRoot, $result);
    sr_package_finalize_manifest_result($result);

    return $result;
}

function sr_package_validate_common_manifest_fields(array $manifest, array &$result): void
{
    $manifestVersion = (string) ($manifest['manifest_version'] ?? '');
    if (!in_array($manifestVersion, ['1', '1.0'], true)) {
        sr_package_manifest_error($result, 'version', 'manifest_version은 1.0이어야 합니다.');
    }

    $saanraan = sr_package_manifest_saanraan($manifest);
    $minVersion = is_string($saanraan['min_version'] ?? null) ? (string) $saanraan['min_version'] : '';
    if ($minVersion === '') {
        sr_package_manifest_error($result, 'version', 'saanraan.min_version이 필요합니다.');
    } elseif (preg_match('/\A(?:v?\d+\.\d+\.\d+|\d{4}\.\d{2}\.\d{3})\z/', $minVersion) !== 1) {
        sr_package_manifest_error($result, 'version', 'saanraan.min_version 형식이 올바르지 않습니다.');
    } elseif (!sr_core_version_satisfies_minimum($minVersion)) {
        sr_package_manifest_error($result, 'version', '현재 Saanraan 버전이 saanraan.min_version 요구사항을 만족하지 않습니다.');
    }
    $result['saanraan_min_version'] = $minVersion;
}

function sr_package_normalize_view_map(array $manifest, string $packageRoot, array $requiredViewKeys, array $optionalViewKeys, array &$result): array
{
    $rawViews = $manifest['views'] ?? [];
    if (!is_array($rawViews)) {
        sr_package_manifest_error($result, 'contract', 'views는 view key와 파일 경로의 객체여야 합니다.');
        return [];
    }

    $allowedKeys = array_fill_keys(array_merge($requiredViewKeys, $optionalViewKeys), true);
    $views = [];
    foreach ($rawViews as $viewKey => $relativePath) {
        $viewKey = is_string($viewKey) ? $viewKey : '';
        $relativePath = is_string($relativePath) ? $relativePath : '';
        if (!isset($allowedKeys[$viewKey])) {
            sr_package_manifest_error($result, 'contract', '허용되지 않는 view key입니다: ' . $viewKey);
            continue;
        }
        if (!sr_package_relative_path_is_safe($relativePath) || strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) !== 'php') {
            sr_package_manifest_error($result, 'path', 'view 경로는 패키지 상대 PHP 파일이어야 합니다: ' . $viewKey);
            continue;
        }
        $file = sr_package_resolve_file($packageRoot, $relativePath);
        if ($file === '') {
            sr_package_manifest_error($result, 'path', 'view 파일을 찾을 수 없거나 패키지 밖을 가리킵니다: ' . $viewKey);
            continue;
        }
        $views[$viewKey] = $file;
    }

    foreach ($requiredViewKeys as $requiredViewKey) {
        if (!isset($views[$requiredViewKey])) {
            sr_package_manifest_error($result, 'contract', '필수 view key가 누락되었습니다: ' . $requiredViewKey);
        }
    }

    return $views;
}

function sr_package_apply_metadata_fields(array $manifest, array &$result): void
{
    $result['label'] = sr_package_manifest_string($manifest, 'label', 120) ?: (string) $result['key'];
    $result['provider_label'] = sr_package_manifest_string($manifest, 'provider_label', 120) ?: '외부 패키지';
    $result['author'] = sr_package_manifest_string($manifest, 'author', 120);
    $result['license'] = sr_package_manifest_string($manifest, 'license', 80);
    $result['source_url'] = sr_package_manifest_string($manifest, 'source_url', 255);
    $result['version'] = sr_package_manifest_string($manifest, 'version', 80);
}

function sr_package_apply_file_scan_errors(string $packageRoot, array &$result): void
{
    foreach (sr_package_manifest_file_errors($packageRoot) as $error) {
        sr_package_manifest_error($result, 'path', $error);
    }
}

function sr_package_finalize_manifest_result(array &$result): void
{
    $result['errors'] = array_values(array_unique(array_map('strval', $result['errors'])));
    foreach ($result['error_groups'] as $group => $messages) {
        $result['error_groups'][$group] = array_values(array_unique(array_map('strval', is_array($messages) ? $messages : [])));
    }
    $result['warnings'] = array_values(array_unique(array_map('strval', $result['warnings'])));
    $result['is_valid'] = $result['errors'] === [];
    $result['status'] = $result['is_valid'] ? 'valid' : 'invalid';
}

function sr_package_external_theme_layout_options(): array
{
    $options = [];
    foreach (sr_package_theme_candidates() as $themeKey => $candidate) {
        if (empty($candidate['is_valid'])) {
            continue;
        }
        $options[(string) $themeKey] = [
            'key' => (string) $themeKey,
            'label' => (string) ($candidate['label'] ?? $themeKey),
            'source_type' => 'external_theme',
            'source_key' => (string) $themeKey,
            'provider_label' => (string) ($candidate['provider_label'] ?? '외부 테마'),
            'asset_owner' => 'package',
            'asset_owner_key' => (string) $themeKey,
            'supports' => (array) ($candidate['supports_domains'] ?? []),
            'supports_domains' => (array) ($candidate['supports_domains'] ?? []),
            'style_profile' => (string) ($candidate['style_profile'] ?? 'kit'),
            'layout_contract' => (string) ($candidate['contract_version'] ?? '1.0'),
            'views' => (array) ($candidate['views'] ?? []),
            'asset_ids' => (array) ($candidate['asset_ids'] ?? []),
            'assets' => (array) ($candidate['assets'] ?? []),
            'is_valid' => true,
            'warnings' => (array) ($candidate['warnings'] ?? []),
            'author' => (string) ($candidate['author'] ?? ''),
            'license' => (string) ($candidate['license'] ?? ''),
            'source_url' => (string) ($candidate['source_url'] ?? ''),
            'trust_level' => (string) ($candidate['trust_level'] ?? 'privileged_php'),
            'trust_warning' => (string) ($candidate['trust_warning'] ?? ''),
        ];
    }

    return $options;
}

function sr_package_external_skin_options(string $moduleKey): array
{
    $options = [];
    foreach (sr_package_skin_candidates($moduleKey) as $skinKey => $candidate) {
        if (empty($candidate['is_valid'])) {
            continue;
        }
        $stylesheets = [];
        foreach ((array) ($candidate['assets'] ?? []) as $assetId => $asset) {
            if (is_array($asset) && (string) ($asset['extension'] ?? '') === 'css') {
                $stylesheets[] = sr_package_asset_url('skin', (string) $skinKey, (string) $assetId, $moduleKey);
            }
        }
        $options[(string) $skinKey] = [
            'skin_key' => (string) $skinKey,
            'label' => (string) ($candidate['label'] ?? $skinKey),
            'source_type' => 'external_skin',
            'source_key' => (string) $skinKey,
            'module_key' => $moduleKey,
            'views' => (array) ($candidate['views'] ?? []),
            'stylesheets' => $stylesheets,
            'actions' => [],
            'assets' => (array) ($candidate['assets'] ?? []),
            'asset_ids' => (array) ($candidate['asset_ids'] ?? []),
            'contract_version' => (string) ($candidate['contract_version'] ?? ''),
            'is_valid' => true,
            'warnings' => (array) ($candidate['warnings'] ?? []),
            'author' => (string) ($candidate['author'] ?? ''),
            'license' => (string) ($candidate['license'] ?? ''),
            'source_url' => (string) ($candidate['source_url'] ?? ''),
            'trust_level' => (string) ($candidate['trust_level'] ?? 'privileged_php'),
            'trust_warning' => (string) ($candidate['trust_warning'] ?? ''),
        ];
    }

    return $options;
}

function sr_package_asset_definition(string $packageType, string $packageKey, string $assetId, string $moduleKey = ''): ?array
{
    if (!sr_package_asset_id_is_valid($assetId)) {
        return null;
    }

    if ($packageType === 'theme') {
        $candidate = sr_package_theme_candidates()[$packageKey] ?? null;
    } elseif ($packageType === 'skin') {
        $candidate = sr_package_skin_candidates($moduleKey)[$packageKey] ?? null;
    } else {
        return null;
    }

    if (!is_array($candidate) || empty($candidate['is_valid'])) {
        return null;
    }

    $asset = $candidate['assets'][$assetId] ?? null;
    return is_array($asset) ? $asset : null;
}

function sr_package_send_asset_response(string $packageType, string $packageKey, string $assetId, string $moduleKey = '', string $version = ''): void
{
    $asset = sr_package_asset_definition($packageType, $packageKey, $assetId, $moduleKey);
    if (!is_array($asset)) {
        sr_render_error(404, '요청한 패키지 자산을 찾을 수 없습니다.');
    }

    $file = (string) ($asset['file'] ?? '');
    $contentType = (string) ($asset['content_type'] ?? '');
    $cacheBuster = (string) ($asset['cache_buster'] ?? '');
    $size = $file !== '' ? filesize($file) : false;
    $mtime = $file !== '' ? filemtime($file) : false;
    if ($file === '' || $contentType === '' || !is_file($file) || !is_int($size) || !is_int($mtime)) {
        sr_render_error(404, '요청한 패키지 자산을 찾을 수 없습니다.');
    }

    $etag = '"' . hash_file('sha256', $file) . '"';
    $longCache = $version !== '' && hash_equals($cacheBuster, $version);
    $cacheControl = $longCache ? 'public, max-age=31536000, immutable' : 'no-store, no-cache, must-revalidate';
    header('X-Content-Type-Options: nosniff');
    sr_send_file_cache_headers($cacheControl, $etag, $mtime);
    if (sr_file_not_modified($etag, $mtime)) {
        http_response_code(304);
        sr_finish_response();
    }

    sr_send_file_headers($contentType, $size, $cacheControl);
    readfile($file);
    sr_finish_response();
}

function sr_package_reference_summary(PDO $pdo, string $packageType, string $packageKey, string $moduleKey = ''): array
{
    if ($packageType === 'theme') {
        return sr_package_theme_reference_summary($pdo, $packageKey);
    }
    if ($packageType === 'skin') {
        return sr_package_skin_reference_summary($pdo, $moduleKey, $packageKey);
    }

    return ['total_count' => 0, 'rows' => [], 'errors' => []];
}

function sr_package_reference_count_query(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return is_array($row) ? max(0, (int) ($row['count_value'] ?? 0)) : 0;
    } catch (Throwable) {
        return 0;
    }
}

function sr_package_theme_reference_summary(PDO $pdo, string $layoutKey): array
{
    $rows = [];
    $checks = [
        ['label' => '사이트 기본 공개 레이아웃', 'sql' => "SELECT COUNT(*) AS count_value FROM sr_site_settings WHERE setting_key = 'public_layout_key' AND setting_value = :key"],
        ['label' => '모듈 공개 레이아웃 설정', 'sql' => "SELECT COUNT(*) AS count_value FROM sr_module_settings WHERE setting_key = 'layout_key' AND setting_value = :key"],
    ];

    foreach ($checks as $check) {
        $count = sr_package_reference_count_query($pdo, (string) $check['sql'], ['key' => $layoutKey]);
        if ($count > 0) {
            $rows[] = ['label' => (string) $check['label'], 'count' => $count];
        }
    }

    foreach ([
        ['table' => 'sr_content_groups', 'label' => '콘텐츠 그룹 레이아웃'],
        ['table' => 'sr_content_items', 'label' => '콘텐츠 항목 레이아웃'],
        ['table' => 'sr_community_board_settings', 'label' => '커뮤니티 게시판 레이아웃'],
    ] as $check) {
        $count = sr_package_reference_count_query($pdo, 'SELECT COUNT(*) AS count_value FROM ' . $check['table'] . " WHERE setting_key = 'layout_key' AND setting_value = :key", ['key' => $layoutKey]);
        if ($count > 0) {
            $rows[] = ['label' => (string) $check['label'], 'count' => $count];
        }
    }

    return ['total_count' => array_sum(array_map(static fn (array $row): int => (int) $row['count'], $rows)), 'rows' => $rows, 'errors' => []];
}

function sr_package_skin_reference_summary(PDO $pdo, string $moduleKey, string $skinKey): array
{
    $rows = [];
    $count = sr_package_reference_count_query($pdo, "SELECT COUNT(*) AS count_value FROM sr_module_settings m INNER JOIN sr_modules mod ON mod.id = m.module_id WHERE mod.module_key = :module_key AND m.setting_key = 'skin_key' AND m.setting_value = :key", ['module_key' => $moduleKey, 'key' => $skinKey]);
    if ($count > 0) {
        $moduleLabel = function_exists('sr_admin_code_label') ? sr_admin_code_label($moduleKey, 'module_key') : $moduleKey;
        $rows[] = ['label' => $moduleLabel . ' 기본 스킨 설정', 'count' => $count];
    }

    if ($moduleKey === 'community') {
        $count = sr_package_reference_count_query($pdo, "SELECT COUNT(*) AS count_value FROM sr_community_board_settings WHERE setting_key = 'skin_key' AND setting_value = :key", ['key' => $skinKey]);
        if ($count > 0) {
            $rows[] = ['label' => '커뮤니티 게시판 스킨 설정', 'count' => $count];
        }
    } elseif ($moduleKey === 'quiz') {
        $count = sr_package_reference_count_query($pdo, 'SELECT COUNT(*) AS count_value FROM sr_quiz_sets WHERE skin_key = :key', ['key' => $skinKey]);
        if ($count > 0) {
            $rows[] = ['label' => '퀴즈별 스킨 override', 'count' => $count];
        }
    } elseif ($moduleKey === 'survey') {
        $count = sr_package_reference_count_query($pdo, 'SELECT COUNT(*) AS count_value FROM sr_survey_forms WHERE skin_key = :key', ['key' => $skinKey]);
        if ($count > 0) {
            $rows[] = ['label' => '설문별 스킨 override', 'count' => $count];
        }
    }

    return ['total_count' => array_sum(array_map(static fn (array $row): int => (int) $row['count'], $rows)), 'rows' => $rows, 'errors' => []];
}
