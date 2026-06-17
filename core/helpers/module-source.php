<?php

declare(strict_types=1);

require_once SR_ROOT . '/core/helpers/common.php';
require_once SR_ROOT . '/core/helpers/module-metadata.php';

function sr_parse_upload_size(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    if ($unit === 'g') {
        return (int) ($number * 1024 * 1024 * 1024);
    }

    if ($unit === 'm') {
        return (int) ($number * 1024 * 1024);
    }

    if ($unit === 'k') {
        return (int) ($number * 1024);
    }

    return (int) $number;
}

function sr_runtime_is_production(?array $config = null): bool
{
    $config = is_array($config) ? $config : sr_runtime_config();
    return (string) ($config['env'] ?? 'production') === 'production';
}

function sr_module_source_upload_limit_bytes(): int
{
    $limits = [];
    foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
        $bytes = sr_parse_upload_size((string) ini_get($setting));
        if ($bytes > 0) {
            $limits[] = $bytes;
        }
    }

    $defaultLimit = 10 * 1024 * 1024;
    if ($limits === []) {
        return $defaultLimit;
    }

    return min($defaultLimit, ...$limits);
}

function sr_module_sources_enabled(PDO $pdo, ?array $config = null): bool
{
    $stmt = $pdo->prepare(
        "SELECT setting_value, value_type
         FROM sr_site_settings
         WHERE setting_key = 'admin.module_sources_enabled'
         LIMIT 1"
    );
    $stmt->execute();
    $setting = $stmt->fetch();
    if (is_array($setting)) {
        return (string) ($setting['value_type'] ?? '') === 'bool'
            && (bool) sr_cast_setting_value($setting['setting_value'] ?? '', 'bool');
    }

    return !sr_runtime_is_production($config);
}

function sr_module_source_uncompressed_limit_bytes(): int
{
    return 25 * 1024 * 1024;
}

function sr_module_source_root(): string
{
    return SR_ROOT . '/modules';
}

function sr_module_work_dir(string $type): string
{
    if (!in_array($type, ['module-upload', 'module-backups'], true)) {
        throw new InvalidArgumentException('Module work directory type is invalid.');
    }

    $directory = SR_ROOT . '/storage/' . $type;
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException('작업 디렉터리를 만들 수 없습니다.');
    }

    return $directory;
}

function sr_random_suffix(): string
{
    try {
        return bin2hex(random_bytes(6));
    } catch (Throwable $exception) {
        return str_replace('.', '', uniqid('', true));
    }
}

function sr_remove_directory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $realDirectory = realpath($directory);
    $realStorage = realpath(SR_ROOT . '/storage');
    $realModules = realpath(SR_ROOT . '/modules');
    $insideStorage = $realDirectory !== false && $realStorage !== false && strpos($realDirectory, $realStorage . DIRECTORY_SEPARATOR) === 0;
    $insideModules = $realDirectory !== false && $realModules !== false && strpos($realDirectory, $realModules . DIRECTORY_SEPARATOR) === 0;
    if (!$insideStorage && !$insideModules) {
        throw new RuntimeException('삭제할 디렉터리 경로가 올바르지 않습니다.');
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($realDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($realDirectory);
}

function sr_copy_directory(string $source, string $target): void
{
    if (!is_dir($source)) {
        throw new RuntimeException('복사할 모듈 디렉터리를 찾을 수 없습니다.');
    }

    if (!mkdir($target, 0755, true) && !is_dir($target)) {
        throw new RuntimeException('모듈 대상 디렉터리를 만들 수 없습니다.');
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($items as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        if (!is_string($relative) || $relative === '') {
            continue;
        }

        $targetPath = $target . DIRECTORY_SEPARATOR . $relative;
        if ($item->isLink()) {
            throw new RuntimeException('심볼릭 링크가 포함된 모듈은 업로드할 수 없습니다.');
        }

        if ($item->isDir()) {
            if (!is_dir($targetPath) && !mkdir($targetPath, 0755, true)) {
                throw new RuntimeException('모듈 하위 디렉터리를 만들 수 없습니다.');
            }
        } elseif (!copy($item->getPathname(), $targetPath)) {
            throw new RuntimeException('모듈 파일을 복사할 수 없습니다.');
        }
    }
}

function sr_path_is_inside(string $path, string $root): bool
{
    $realPath = realpath($path);
    $realRoot = realpath($root);
    if ($realPath === false || $realRoot === false) {
        return false;
    }

    return $realPath === $realRoot || strpos($realPath, $realRoot . DIRECTORY_SEPARATOR) === 0;
}

function sr_module_zip_entry_is_safe(string $name): bool
{
    $name = str_replace('\\', '/', $name);
    $pathName = rtrim($name, '/');
    if (
        $name === ''
        || $pathName === ''
        || preg_match('/[\x00-\x1F\x7F]/', $name) === 1
        || str_starts_with($name, '/')
        || str_contains($name, '//')
        || str_contains($name, ':')
    ) {
        return false;
    }

    foreach (explode('/', $pathName) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return false;
        }
    }

    return true;
}

function sr_module_zip_entry_is_symlink(ZipArchive $zip, int $index): bool
{
    if (!method_exists($zip, 'getExternalAttributesIndex')) {
        throw new RuntimeException('zip 항목 속성을 확인할 수 없습니다.');
    }

    $opsys = 0;
    $attributes = 0;
    if (!$zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
        throw new RuntimeException('zip 항목 속성을 확인할 수 없습니다.');
    }

    $mode = ($attributes >> 16) & 0170000;
    return $mode === 0120000;
}

function sr_module_zip_upload_stats(ZipArchive $zip): array
{
    $entryCount = $zip->numFiles;
    $uncompressedBytes = 0;
    $maxEntries = 1000;
    $maxUncompressedBytes = sr_module_source_uncompressed_limit_bytes();

    if ($entryCount < 1 || $entryCount > $maxEntries) {
        throw new RuntimeException('zip 파일 항목 수가 허용 범위를 벗어났습니다.');
    }

    for ($i = 0; $i < $entryCount; $i++) {
        $entry = $zip->getNameIndex($i);
        if (!is_string($entry) || !sr_module_zip_entry_is_safe($entry)) {
            throw new RuntimeException('zip 안에 안전하지 않은 경로가 있습니다.');
        }

        if (sr_module_zip_entry_is_symlink($zip, $i)) {
            throw new RuntimeException('zip 안에 심볼릭 링크가 있습니다.');
        }

        $stats = $zip->statIndex($i);
        if (!is_array($stats)) {
            throw new RuntimeException('zip 항목 정보를 읽을 수 없습니다.');
        }

        $size = (int) ($stats['size'] ?? 0);
        if ($size < 0) {
            throw new RuntimeException('zip 항목 크기가 올바르지 않습니다.');
        }

        $uncompressedBytes += $size;
        if ($uncompressedBytes > $maxUncompressedBytes) {
            throw new RuntimeException('압축 해제 후 모듈 크기는 ' . sr_format_bytes($maxUncompressedBytes) . ' 이하여야 합니다.');
        }
    }

    return [
        'entry_count' => $entryCount,
        'uncompressed_bytes' => $uncompressedBytes,
    ];
}

function sr_validate_extracted_module_tree(string $extractDir): void
{
    if (!is_dir($extractDir)) {
        throw new RuntimeException('압축 해제된 모듈 디렉터리를 찾을 수 없습니다.');
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($extractDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($items as $item) {
        if ($item->isLink()) {
            throw new RuntimeException('압축 해제된 모듈에 심볼릭 링크가 있습니다.');
        }

        if (!sr_path_is_inside($item->getPathname(), $extractDir)) {
            throw new RuntimeException('압축 해제된 모듈 경로가 작업 디렉터리 밖을 가리킵니다.');
        }
    }
}

function sr_module_source_file_errors(string $sourceDir): array
{
    if (!is_dir($sourceDir)) {
        return ['모듈 소스 디렉터리를 찾을 수 없습니다.'];
    }

    $errors = [];
    $blockedNames = [
        '.ds_store' => '서버 설정 또는 비밀 파일',
        '.npmrc' => '패키지 레지스트리 인증 파일',
        '.yarnrc' => '패키지 레지스트리 인증 파일',
        'auth.json' => '패키지 레지스트리 인증 파일',
        'id_dsa' => 'SSH key 파일',
        'id_ecdsa' => 'SSH key 파일',
        'id_ed25519' => 'SSH key 파일',
        'id_rsa' => 'SSH key 파일',
    ];
    $blockedExtensions = [
        'asp' => true,
        'aspx' => true,
        'bak' => true,
        'bash' => true,
        'bat' => true,
        'backup' => true,
        'cgi' => true,
        'cmd' => true,
        'com' => true,
        'db' => true,
        'dll' => true,
        'dump' => true,
        'exe' => true,
        'hta' => true,
        'jsp' => true,
        'key' => true,
        'msi' => true,
        'old' => true,
        'orig' => true,
        'p12' => true,
        'pem' => true,
        'phar' => true,
        'pfx' => true,
        'pht' => true,
        'php3' => true,
        'php4' => true,
        'php5' => true,
        'php6' => true,
        'php7' => true,
        'php8' => true,
        'phtml' => true,
        'pl' => true,
        'py' => true,
        'shtml' => true,
        'sh' => true,
        'sqlite' => true,
        'sqlite3' => true,
    ];

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($items as $item) {
        $path = $item->getPathname();
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($sourceDir) + 1));
        $basename = strtolower($item->getFilename());
        $extension = strtolower(pathinfo($item->getFilename(), PATHINFO_EXTENSION));

        if ($relative === '' || preg_match('/[\x00-\x1F\x7F]/', $relative) === 1) {
            $errors[] = '모듈 파일 경로에 사용할 수 없는 문자가 있습니다.';
            continue;
        }

        if ($item->isDir() && sr_module_source_is_repository_meta_name($basename)) {
            $errors[] = '모듈 zip에는 저장소 메타 디렉터리를 포함할 수 없습니다: ' . $relative;
            continue;
        }

        if (!$item->isFile()) {
            continue;
        }

        if (sr_module_source_is_repository_meta_name($basename)) {
            $errors[] = '모듈 zip에는 저장소 메타 파일을 포함할 수 없습니다: ' . $relative;
            continue;
        }

        if (sr_module_source_is_server_config_name($basename)) {
            $errors[] = '모듈 zip에는 서버 설정 또는 비밀 파일을 포함할 수 없습니다: ' . $relative;
            continue;
        }

        if (isset($blockedNames[$basename])) {
            $errors[] = '모듈 zip에는 ' . $blockedNames[$basename] . '을 포함할 수 없습니다: ' . $relative;
            continue;
        }

        if ($extension !== '' && isset($blockedExtensions[$extension])) {
            $errors[] = '모듈 zip에는 허용되지 않는 실행 파일 확장자를 포함할 수 없습니다: ' . $relative;
        }
    }

    return $errors;
}

function sr_module_source_is_repository_meta_name(string $basename): bool
{
    return in_array($basename, [
        '.git',
        '.gitattributes',
        '.gitignore',
        '.gitmodules',
        '.hg',
        '.hgignore',
        '.hgrc',
        '.svn',
    ], true);
}

function sr_module_source_is_server_config_name(string $basename): bool
{
    return $basename === '.env'
        || str_starts_with($basename, '.env.')
        || $basename === '.htaccess'
        || str_starts_with($basename, '.htaccess.')
        || $basename === '.htpasswd'
        || str_starts_with($basename, '.htpasswd.')
        || $basename === '.user.ini'
        || str_starts_with($basename, '.user.ini.')
        || $basename === 'php.ini'
        || str_starts_with($basename, 'php.ini.')
        || $basename === 'web.config'
        || str_starts_with($basename, 'web.config.');
}

function sr_module_source_route_errors(string $moduleKey, string $sourceDir): array
{
    $routeMap = sr_module_source_route_map($moduleKey, $sourceDir);
    return $routeMap['errors'];
}

function sr_module_source_route_map(string $moduleKey, string $sourceDir): array
{
    $pathsFile = $sourceDir . '/paths.php';
    if (!is_file($pathsFile)) {
        return ['routes' => [], 'errors' => []];
    }

    $content = file_get_contents($pathsFile);
    $paths = is_string($content) ? sr_php_return_string_map($content) : null;
    if (!is_array($paths)) {
        return ['routes' => [], 'errors' => [$moduleKey . ' 모듈의 paths.php는 정적 문자열 배열을 반환해야 합니다.']];
    }

    $routes = [];
    $errors = [];
    foreach ($paths as $route => $actionRelativePath) {
        if (!sr_is_valid_module_route((string) $route)) {
            $errors[] = $moduleKey . ' 모듈 주소 경로 형식이 올바르지 않습니다: ' . (string) $route;
            continue;
        }

        if (!sr_module_source_action_path_is_safe((string) $actionRelativePath)) {
            $errors[] = $moduleKey . ' 모듈 실행 파일 경로가 올바르지 않습니다: ' . (string) $route;
            continue;
        }

        if (!is_file($sourceDir . '/' . (string) $actionRelativePath)) {
            $errors[] = $moduleKey . ' 모듈 실행 파일을 찾을 수 없습니다: ' . (string) $route;
            continue;
        }

        $routes[(string) $route] = (string) $actionRelativePath;
    }

    return ['routes' => $routes, 'errors' => array_values(array_unique($errors))];
}

function sr_module_source_route_conflict_errors(PDO $pdo, string $moduleKey, string $sourceDir): array
{
    if (!sr_is_safe_module_key($moduleKey)) {
        return ['모듈 키가 올바르지 않습니다.'];
    }

    if (sr_module_record_status($pdo, $moduleKey) !== 'enabled') {
        return [];
    }

    $candidateRoutes = sr_module_source_route_map($moduleKey, $sourceDir);
    if ($candidateRoutes['errors'] !== []) {
        return $candidateRoutes['errors'];
    }

    $candidateRouteMap = $candidateRoutes['routes'];
    if ($candidateRouteMap === []) {
        return [];
    }

    $errors = [];
    foreach (sr_enabled_module_contract_files($pdo, 'paths.php', [$moduleKey]) as $enabledModuleKey => $pathsFile) {
        $paths = sr_load_module_contract_file($enabledModuleKey, $pathsFile);
        if (!is_array($paths)) {
            continue;
        }

        foreach ($paths as $route => $actionRelativePath) {
            $route = (string) $route;
            if (!sr_is_valid_module_route($route)) {
                continue;
            }

            foreach (array_keys($candidateRouteMap) as $candidateRoute) {
                if (!sr_module_routes_conflict((string) $candidateRoute, $route)) {
                    continue;
                }

                $errors[] = $moduleKey . ' 모듈 주소 경로가 ' . $enabledModuleKey . ' 모듈과 충돌합니다: ' . (string) $candidateRoute . ' / ' . $route;
            }
        }
    }

    return array_values(array_unique($errors));
}

function sr_module_source_update_errors(string $moduleKey, string $sourceDir, array $metadata): array
{
    $updatesDir = $sourceDir . '/updates';
    if (!is_dir($updatesDir)) {
        return [];
    }

    $moduleVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
    $moduleVersionIsValid = preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $moduleVersion) === 1;
    $errors = [];

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($updatesDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($items as $item) {
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($updatesDir) + 1));
        if ($relative === '') {
            continue;
        }

        if ($item->isDir()) {
            $errors[] = $moduleKey . ' 모듈 updates 디렉터리에는 버전 SQL 파일만 포함할 수 있습니다: updates/' . $relative;
            continue;
        }

        if (!$item->isFile()) {
            continue;
        }

        if (dirname($relative) !== '.' || preg_match('/\A\d{4}\.\d{2}\.\d{3}\.sql\z/', basename($relative)) !== 1) {
            $errors[] = $moduleKey . ' 모듈 업데이트 SQL 파일명은 updates/YYYY.MM.NNN.sql 형식이어야 합니다: updates/' . $relative;
            continue;
        }

        $updateVersion = pathinfo($relative, PATHINFO_FILENAME);
        if ($moduleVersionIsValid && strcmp($updateVersion, $moduleVersion) > 0) {
            $errors[] = $moduleKey . ' 모듈 업데이트 SQL 버전은 module.php version보다 높을 수 없습니다: updates/' . $relative;
        }
    }

    return array_values(array_unique($errors));
}

function sr_module_source_action_path_is_safe(string $path): bool
{
    if ($path === '' || strpos($path, '..') !== false || strpos($path, '\\') !== false) {
        return false;
    }

    return preg_match('/\Aactions\/[a-z0-9_\-\/]+\.php\z/', $path) === 1;
}

function sr_infer_module_key_from_filename(string $filename): string
{
    $name = strtolower(pathinfo($filename, PATHINFO_FILENAME));
    $name = preg_replace('/[^a-z0-9_.-]+/', '-', $name);
    $name = is_string($name) ? trim($name, '-_.') : '';
    $name = preg_replace('/-\d{4}\.\d{2}\.\d{3}\z/', '', $name);
    $name = is_string($name) ? $name : '';
    $moduleKey = str_replace('-', '_', $name);
    return sr_is_safe_module_key($moduleKey) ? $moduleKey : '';
}

function sr_module_source_candidate(array $candidate): ?array
{
    $moduleKey = (string) ($candidate['module_key'] ?? '');
    $sourceDir = (string) ($candidate['source_dir'] ?? '');
    if (!sr_is_safe_module_key($moduleKey) || !is_dir($sourceDir) || !is_file($sourceDir . '/module.php')) {
        return null;
    }

    $metadata = sr_load_module_metadata_from_file($sourceDir . '/module.php');
    return [
        'module_key' => $moduleKey,
        'source_dir' => $sourceDir,
        'metadata' => $metadata,
    ];
}

function sr_find_module_source(string $extractDir, string $requestedModuleKey, string $filename): array
{
    $inferredModuleKey = $requestedModuleKey !== '' ? $requestedModuleKey : sr_infer_module_key_from_filename($filename);
    $candidates = [];

    if ($requestedModuleKey !== '') {
        $candidates[] = [
            'module_key' => $requestedModuleKey,
            'source_dir' => $extractDir . '/' . $requestedModuleKey,
        ];
    }

    $directories = glob($extractDir . '/*', GLOB_ONLYDIR);
    if (is_array($directories)) {
        sort($directories, SORT_STRING);
        foreach ($directories as $directory) {
            $basename = basename($directory);
            if (is_dir($directory . '/module') && $inferredModuleKey !== '') {
                $candidates[] = [
                    'module_key' => $inferredModuleKey,
                    'source_dir' => $directory . '/module',
                ];
            }

            if ($basename === 'module') {
                if ($inferredModuleKey !== '') {
                    $candidates[] = [
                        'module_key' => $inferredModuleKey,
                        'source_dir' => $directory,
                    ];
                }
                continue;
            }

            if (sr_is_safe_module_key($basename)) {
                $candidates[] = [
                    'module_key' => $basename,
                    'source_dir' => $directory,
                ];
            }
        }
    }

    if ($inferredModuleKey !== '') {
        $candidates[] = [
            'module_key' => $inferredModuleKey,
            'source_dir' => $extractDir,
        ];
    }

    $sources = [];
    $sourceKeys = [];
    foreach ($candidates as $candidate) {
        $source = sr_module_source_candidate($candidate);
        if (is_array($source)) {
            $realSourceDir = realpath((string) $source['source_dir']);
            $sourceKey = (string) $source['module_key'] . "\0" . ($realSourceDir !== false ? $realSourceDir : (string) $source['source_dir']);
            if (!isset($sourceKeys[$sourceKey])) {
                $sourceKeys[$sourceKey] = true;
                $sources[] = $source;
            }
        }
    }

    if ($sources === []) {
        throw new RuntimeException('zip 안에서 모듈 구조를 찾을 수 없습니다. 최상위 {module_key}/module.php 구조를 사용하거나 module_key를 입력하세요.');
    }

    if ($requestedModuleKey !== '') {
        $matchingSources = [];
        foreach ($sources as $source) {
            if ((string) $source['module_key'] === $requestedModuleKey) {
                $matchingSources[] = $source;
            }
        }

        if (count($matchingSources) !== 1 || count($sources) !== 1) {
            throw new RuntimeException('zip 안에는 요청한 모듈 하나만 포함해야 합니다.');
        }

        return $matchingSources[0];
    }

    if (count($sources) !== 1) {
        throw new RuntimeException('zip 안에 여러 모듈 구조가 있습니다. 모듈 zip에는 하나의 모듈만 포함하세요.');
    }

    return $sources[0];
}

function sr_validate_module_source(string $moduleKey, string $sourceDir, array $metadata): array
{
    $errors = [];
    if (in_array($moduleKey, ['member', 'admin', 'policy_documents', 'privacy'], true)) {
        $errors[] = '회원, 관리자, 정책 문서, 개인정보 기본 모듈은 zip 업로드로 교체할 수 없습니다.';
    }

    if (!is_file($sourceDir . '/module.php')) {
        $errors[] = 'module.php 파일이 필요합니다.';
    } else {
        $moduleContent = file_get_contents($sourceDir . '/module.php');
        if (!is_string($moduleContent) || !sr_php_starts_with_return_array($moduleContent)) {
            $errors[] = 'module.php는 정적 return 배열로 시작해야 합니다.';
        }
    }

    if (!is_file($sourceDir . '/install.sql')) {
        $errors[] = 'install.sql 파일이 필요합니다.';
    }

    foreach (sr_module_source_file_errors($sourceDir) as $error) {
        $errors[] = $error;
    }

    foreach (sr_module_metadata_errors($metadata) as $error) {
        $errors[] = $error;
    }

    foreach (sr_module_contract_file_errors($sourceDir, $metadata) as $error) {
        $errors[] = $error;
    }

    foreach (sr_module_source_update_errors($moduleKey, $sourceDir, $metadata) as $error) {
        $errors[] = $error;
    }

    foreach (sr_module_source_route_errors($moduleKey, $sourceDir) as $error) {
        $errors[] = $error;
    }

    return $errors;
}

function sr_module_upload_version_errors(PDO $pdo, string $moduleKey, array $metadata, bool $allowDowngrade): array
{
    $codeVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
    if ($codeVersion === '' || preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $codeVersion) !== 1) {
        return [];
    }

    $module = sr_module_record_entry($pdo, $moduleKey);
    if (!is_array($module)) {
        return [];
    }

    $installedVersion = (string) ($module['version'] ?? '');
    if ($installedVersion === '' || preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $installedVersion) !== 1) {
        return [];
    }

    if (strcmp($codeVersion, $installedVersion) >= 0 || $allowDowngrade) {
        return [];
    }

    return [
        '업로드한 코드 버전이 현재 설치 버전보다 낮습니다. 낮은 버전 덮어쓰기를 명시적으로 허용해야 합니다.',
    ];
}

function sr_module_replace_errors(string $moduleKey, bool $replaceConfirmed): array
{
    if (!sr_is_safe_module_key($moduleKey)) {
        return ['모듈 키가 올바르지 않습니다.'];
    }

    if (!is_dir(SR_ROOT . '/modules/' . $moduleKey) || $replaceConfirmed) {
        return [];
    }

    return [
        '기존 모듈 파일을 교체하려면 백업과 파일 교체 확인을 명시해야 합니다.',
    ];
}

function sr_extract_module_upload(array $file, string $requestedModuleKey): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive 확장이 필요합니다.');
    }

    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        throw new RuntimeException('zip 파일 업로드에 실패했습니다.');
    }

    $filename = (string) ($file['name'] ?? '');
    if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'zip') {
        throw new RuntimeException('zip 파일만 업로드할 수 있습니다.');
    }

    $size = (int) ($file['size'] ?? 0);
    $limit = sr_module_source_upload_limit_bytes();
    if ($size <= 0 || $size > $limit) {
        throw new RuntimeException('업로드 파일 크기는 ' . sr_format_bytes($limit) . ' 이하여야 합니다.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_file($tmpName)) {
        throw new RuntimeException('업로드된 임시 파일을 찾을 수 없습니다.');
    }

    $checksum = hash_file('sha256', $tmpName);
    if (!is_string($checksum)) {
        throw new RuntimeException('업로드 파일 checksum을 계산할 수 없습니다.');
    }

    if ($requestedModuleKey !== '' && !sr_is_safe_module_key($requestedModuleKey)) {
        throw new RuntimeException('입력한 모듈 키가 올바르지 않습니다.');
    }

    $workRoot = sr_module_work_dir('module-upload');
    $extractDir = $workRoot . '/upload-' . date('YmdHis') . '-' . sr_random_suffix();
    if (!mkdir($extractDir, 0755, true)) {
        throw new RuntimeException('업로드 작업 디렉터리를 만들 수 없습니다.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpName) !== true) {
        sr_remove_directory($extractDir);
        throw new RuntimeException('zip 파일을 열 수 없습니다.');
    }

    try {
        $uploadStats = sr_module_zip_upload_stats($zip);

        if (!$zip->extractTo($extractDir)) {
            throw new RuntimeException('zip 파일을 압축 해제할 수 없습니다.');
        }
    } finally {
        $zip->close();
    }

    try {
        sr_validate_extracted_module_tree($extractDir);
        $packageErrors = sr_module_source_file_errors($extractDir);
        if ($packageErrors !== []) {
            throw new RuntimeException(implode(' ', $packageErrors));
        }

        $source = sr_find_module_source($extractDir, $requestedModuleKey, $filename);
        if ($requestedModuleKey !== '' && (string) $source['module_key'] !== $requestedModuleKey) {
            throw new RuntimeException('zip 내부 모듈 키가 요청한 모듈 키와 일치하지 않습니다.');
        }

        if (!sr_path_is_inside((string) $source['source_dir'], $extractDir)) {
            throw new RuntimeException('zip 안의 모듈 경로가 올바르지 않습니다.');
        }

        $errors = sr_validate_module_source(
            (string) $source['module_key'],
            (string) $source['source_dir'],
            is_array($source['metadata']) ? $source['metadata'] : []
        );
        if ($errors !== []) {
            throw new RuntimeException(implode(' ', $errors));
        }

        $source['extract_dir'] = $extractDir;
        $source['upload'] = [
            'filename' => $filename,
            'size' => $size,
            'checksum' => $checksum,
            'entry_count' => (int) $uploadStats['entry_count'],
            'uncompressed_bytes' => (int) $uploadStats['uncompressed_bytes'],
        ];
        return $source;
    } catch (Throwable $exception) {
        sr_remove_directory($extractDir);
        throw $exception;
    }
}

function sr_install_module_source_files(string $moduleKey, string $sourceDir): array
{
    if (!sr_is_safe_module_key($moduleKey)) {
        throw new InvalidArgumentException('Module key is invalid.');
    }

    $modulesRoot = sr_module_source_root();
    if (!is_dir($modulesRoot) && !mkdir($modulesRoot, 0755, true)) {
        throw new RuntimeException('modules 디렉터리를 만들 수 없습니다.');
    }

    $targetDir = $modulesRoot . '/' . $moduleKey;
    $backupDir = '';
    if (is_dir($targetDir)) {
        $backupRoot = sr_module_work_dir('module-backups');
        $backupDir = $backupRoot . '/' . $moduleKey . '-' . date('YmdHis') . '-' . sr_random_suffix();
        if (!rename($targetDir, $backupDir)) {
            throw new RuntimeException('기존 모듈 디렉터리를 백업할 수 없습니다.');
        }
    }

    try {
        sr_copy_directory($sourceDir, $targetDir);
    } catch (Throwable $exception) {
        if (is_dir($targetDir)) {
            sr_remove_directory($targetDir);
        }

        if ($backupDir !== '' && is_dir($backupDir) && !is_dir($targetDir) && !rename($backupDir, $targetDir)) {
            throw new RuntimeException('기존 모듈 백업을 복구할 수 없습니다.', 0, $exception);
        }

        throw $exception;
    }

    return [
        'target_dir' => $targetDir,
        'backup_dir' => $backupDir,
    ];
}
