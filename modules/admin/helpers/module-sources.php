<?php

declare(strict_types=1);

function toy_admin_parse_upload_size(string $value): int
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

function toy_admin_module_upload_limit_bytes(): int
{
    $limits = [];
    foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
        $bytes = toy_admin_parse_upload_size((string) ini_get($setting));
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

function toy_admin_module_sources_enabled(PDO $pdo, ?array $config = null): bool
{
    $setting = toy_site_setting($pdo, 'admin.module_sources_enabled', null);
    if ($setting !== null) {
        return !empty($setting);
    }

    return !toy_admin_runtime_is_production($config);
}

function toy_admin_repository_archive_unchecked_enabled(PDO $pdo, ?array $config = null): bool
{
    if (toy_admin_runtime_is_production($config)) {
        return false;
    }

    return !empty(toy_site_setting($pdo, 'admin.repository_archive_unchecked_enabled', true));
}

function toy_admin_module_uncompressed_limit_bytes(): int
{
    return 25 * 1024 * 1024;
}

function toy_admin_format_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / 1024 / 1024, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return (string) $bytes . ' bytes';
}

function toy_admin_module_source_root(): string
{
    return TOY_ROOT . '/modules';
}

function toy_admin_module_work_dir(string $type): string
{
    if (!in_array($type, ['module-upload', 'module-backups'], true)) {
        throw new InvalidArgumentException('Module work directory type is invalid.');
    }

    $directory = TOY_ROOT . '/storage/' . $type;
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException('작업 디렉터리를 만들 수 없습니다.');
    }

    return $directory;
}

function toy_admin_module_registry_path(): string
{
    return TOY_ROOT . '/docs/module-index.json';
}

function toy_admin_normalize_registry_entry(array $entry): ?array
{
    $moduleKey = (string) ($entry['module_key'] ?? '');
    if (!toy_is_safe_module_key($moduleKey)) {
        return null;
    }

    $checksum = strtolower((string) ($entry['checksum'] ?? ''));
    if ($checksum !== '' && preg_match('/\A[a-f0-9]{64}\z/', $checksum) !== 1) {
        $checksum = '';
    }

    $repositoryRefs = [];
    $rawRepositoryRefs = $entry['repository_refs'] ?? [];
    if (is_array($rawRepositoryRefs)) {
        foreach ($rawRepositoryRefs as $ref => $refChecksum) {
            $ref = is_string($ref) ? $ref : '';
            $refChecksum = is_string($refChecksum) ? strtolower($refChecksum) : '';
            if (
                toy_admin_is_safe_repository_ref($ref)
                && toy_admin_repository_ref_is_production_allowed($ref)
                && preg_match('/\A[a-f0-9]{64}\z/', $refChecksum) === 1
            ) {
                $repositoryRefs[$ref] = $refChecksum;
            }
        }
    }
    ksort($repositoryRefs, SORT_STRING);

    return [
        'module_key' => $moduleKey,
        'name' => (string) ($entry['name'] ?? $moduleKey),
        'repository' => (string) ($entry['repository'] ?? ''),
        'latest_version' => (string) ($entry['latest_version'] ?? ''),
        'min_toycore_version' => (string) ($entry['min_toycore_version'] ?? ''),
        'category' => (string) ($entry['category'] ?? ''),
        'zip_url' => (string) ($entry['zip_url'] ?? ''),
        'checksum' => $checksum,
        'repository_refs' => $repositoryRefs,
    ];
}

function toy_admin_module_registry_entries(): array
{
    $path = toy_admin_module_registry_path();
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $content = file_get_contents($path);
    if (!is_string($content)) {
        return [];
    }

    $decoded = json_decode($content, true);
    if (!is_array($decoded) || !is_array($decoded['modules'] ?? null)) {
        return [];
    }

    $entries = [];
    foreach ($decoded['modules'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $normalized = toy_admin_normalize_registry_entry($entry);
        if ($normalized !== null) {
            $entries[(string) $normalized['module_key']] = $normalized;
        }
    }

    ksort($entries, SORT_STRING);
    return array_values($entries);
}

function toy_admin_module_registry_entry(string $moduleKey): ?array
{
    if (!toy_is_safe_module_key($moduleKey)) {
        return null;
    }

    foreach (toy_admin_module_registry_entries() as $entry) {
        if ((string) $entry['module_key'] === $moduleKey) {
            return $entry;
        }
    }

    return null;
}

function toy_admin_registry_entry_download_ready(array $entry): bool
{
    return toy_admin_is_https_public_url((string) ($entry['zip_url'] ?? ''))
        && preg_match('/\A[a-f0-9]{64}\z/', (string) ($entry['checksum'] ?? '')) === 1;
}

function toy_admin_registry_entry_repository_ready(array $entry): bool
{
    $repository = (string) ($entry['repository'] ?? '');
    if (!toy_admin_is_https_public_url($repository)) {
        return false;
    }

    $host = strtolower((string) parse_url($repository, PHP_URL_HOST));
    $path = trim((string) parse_url($repository, PHP_URL_PATH), '/');
    return $host === 'github.com' && preg_match('/\Awhitedot\/toycore-module-[a-z0-9-]+\z/', $path) === 1;
}

function toy_admin_is_https_public_url(string $url): bool
{
    return toy_is_public_http_url($url)
        && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
}

function toy_admin_is_safe_repository_ref(string $ref): bool
{
    if ($ref === '' || strlen($ref) > 120 || str_contains($ref, '..')) {
        return false;
    }

    if (str_starts_with($ref, '/') || str_ends_with($ref, '/') || str_contains($ref, '//')) {
        return false;
    }

    return preg_match('/\A[A-Za-z0-9._\/-]+\z/', $ref) === 1;
}

function toy_admin_repository_ref_is_production_allowed(string $ref): bool
{
    return preg_match('/\A[a-f0-9]{40}\z/', $ref) === 1;
}

function toy_admin_runtime_is_production(?array $config = null): bool
{
    $config = is_array($config) ? $config : toy_runtime_config();
    return (string) ($config['env'] ?? 'production') === 'production';
}

function toy_admin_repository_archive_registered_refs(array $entry): array
{
    $repositoryRefs = $entry['repository_refs'] ?? [];
    if (!is_array($repositoryRefs)) {
        return [];
    }

    $refs = [];
    foreach ($repositoryRefs as $ref => $checksum) {
        $ref = is_string($ref) ? $ref : '';
        $checksum = is_string($checksum) ? $checksum : '';
        if (
            toy_admin_is_safe_repository_ref($ref)
            && toy_admin_repository_ref_is_production_allowed($ref)
            && preg_match('/\A[a-f0-9]{64}\z/', $checksum) === 1
        ) {
            $refs[$ref] = $checksum;
        }
    }

    ksort($refs, SORT_STRING);
    return $refs;
}

function toy_admin_repository_archive_expected_checksum(array $entry, string $ref): string
{
    $registeredRefs = toy_admin_repository_archive_registered_refs($entry);
    return (string) ($registeredRefs[$ref] ?? '');
}

function toy_admin_repository_archive_policy_errors(array $entry, string $ref, ?array $config = null): array
{
    $errors = [];
    if (!toy_admin_is_safe_repository_ref($ref)) {
        return ['repository ref 형식이 올바르지 않습니다.'];
    }

    if (!toy_admin_runtime_is_production($config)) {
        return [];
    }

    if (!toy_admin_repository_ref_is_production_allowed($ref)) {
        $errors[] = '운영 환경에서는 branch나 tag ref를 repository archive로 반영할 수 없습니다. 40자 commit SHA를 사용하세요.';
    }

    if (toy_admin_repository_archive_expected_checksum($entry, $ref) === '') {
        $errors[] = '운영 환경에서는 registry의 repository_refs에 commit SHA와 sha256 checksum이 등록된 archive만 반영할 수 있습니다.';
    }

    return $errors;
}

function toy_admin_repository_archive_ready(array $entry, ?array $config = null): bool
{
    if (!toy_admin_registry_entry_repository_ready($entry)) {
        return false;
    }

    if (!toy_admin_runtime_is_production($config)) {
        return true;
    }

    return toy_admin_repository_archive_registered_refs($entry) !== [];
}

function toy_admin_registry_repository_archive_url(array $entry, string $ref): string
{
    if (!toy_admin_registry_entry_repository_ready($entry) || !toy_admin_is_safe_repository_ref($ref)) {
        return '';
    }

    $path = trim((string) parse_url((string) $entry['repository'], PHP_URL_PATH), '/');
    return 'https://codeload.github.com/' . $path . '/zip/' . rawurlencode($ref);
}

function toy_admin_http_stream_status_is_success($stream): bool
{
    if (!is_resource($stream)) {
        return false;
    }

    $metadata = stream_get_meta_data($stream);
    $headers = $metadata['wrapper_data'] ?? [];
    if (!is_array($headers)) {
        return false;
    }

    $statusCode = 0;
    foreach ($headers as $header) {
        if (!is_string($header)) {
            continue;
        }

        if (preg_match('/\AHTTP\/\S+\s+(\d{3})\b/', $header, $matches) === 1) {
            $statusCode = (int) $matches[1];
        }
    }

    return $statusCode >= 200 && $statusCode < 300;
}

function toy_admin_random_suffix(): string
{
    try {
        return bin2hex(random_bytes(6));
    } catch (Throwable $exception) {
        return str_replace('.', '', uniqid('', true));
    }
}

function toy_admin_remove_directory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $realDirectory = realpath($directory);
    $realStorage = realpath(TOY_ROOT . '/storage');
    $realModules = realpath(TOY_ROOT . '/modules');
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

function toy_admin_copy_directory(string $source, string $target): void
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

function toy_admin_zip_entry_is_safe(string $name): bool
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

function toy_admin_zip_entry_is_symlink(ZipArchive $zip, int $index): bool
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

function toy_admin_zip_upload_stats(ZipArchive $zip): array
{
    $entryCount = $zip->numFiles;
    $uncompressedBytes = 0;
    $maxEntries = 1000;
    $maxUncompressedBytes = toy_admin_module_uncompressed_limit_bytes();

    if ($entryCount < 1 || $entryCount > $maxEntries) {
        throw new RuntimeException('zip 파일 항목 수가 허용 범위를 벗어났습니다.');
    }

    for ($i = 0; $i < $entryCount; $i++) {
        $entry = $zip->getNameIndex($i);
        if (!is_string($entry) || !toy_admin_zip_entry_is_safe($entry)) {
            throw new RuntimeException('zip 안에 안전하지 않은 경로가 있습니다.');
        }

        if (toy_admin_zip_entry_is_symlink($zip, $i)) {
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
            throw new RuntimeException('압축 해제 후 모듈 크기는 ' . toy_admin_format_bytes($maxUncompressedBytes) . ' 이하여야 합니다.');
        }
    }

    return [
        'entry_count' => $entryCount,
        'uncompressed_bytes' => $uncompressedBytes,
    ];
}

function toy_admin_path_is_inside(string $path, string $root): bool
{
    $realPath = realpath($path);
    $realRoot = realpath($root);
    if ($realPath === false || $realRoot === false) {
        return false;
    }

    return $realPath === $realRoot || strpos($realPath, $realRoot . DIRECTORY_SEPARATOR) === 0;
}

function toy_admin_validate_extracted_module_tree(string $extractDir): void
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

        if (!toy_admin_path_is_inside($item->getPathname(), $extractDir)) {
            throw new RuntimeException('압축 해제된 모듈 경로가 작업 디렉터리 밖을 가리킵니다.');
        }
    }
}

function toy_admin_infer_module_key_from_filename(string $filename): string
{
    $name = strtolower(pathinfo($filename, PATHINFO_FILENAME));
    $name = preg_replace('/[^a-z0-9_.-]+/', '-', $name);
    $name = is_string($name) ? trim($name, '-_.') : '';
    $name = preg_replace('/-\d{4}\.\d{2}\.\d{3}\z/', '', $name);
    $name = is_string($name) ? $name : '';
    if (str_starts_with($name, 'toycore-module-')) {
        $name = substr($name, strlen('toycore-module-'));
    }

    $moduleKey = str_replace('-', '_', $name);
    return toy_is_safe_module_key($moduleKey) ? $moduleKey : '';
}

function toy_admin_php_string_array_value(string $content, string $key): string
{
    foreach (['\'', '"'] as $quote) {
        $quotedKey = preg_quote($key, '/');
        $quotedQuote = preg_quote($quote, '/');
        $pattern = '/' . $quotedQuote . $quotedKey . $quotedQuote . '\s*=>\s*' . $quotedQuote . '((?:\\\\.|[^' . $quotedQuote . '\\\\])*)' . $quotedQuote . '/';
        if (preg_match($pattern, $content, $matches) === 1) {
            return stripcslashes((string) $matches[1]);
        }
    }

    return '';
}

function toy_admin_load_module_metadata_from_file(string $file): array
{
    if (!is_file($file)) {
        return [];
    }

    $content = file_get_contents($file);
    if (!is_string($content) || preg_match('/\breturn\s+(?:\[|array\s*\()/i', $content) !== 1) {
        return [];
    }

    $metadata = [];
    foreach (['name', 'version', 'type', 'description'] as $key) {
        $value = toy_admin_php_string_array_value($content, $key);
        if ($value !== '') {
            $metadata[$key] = $value;
        }
    }

    return $metadata;
}

function toy_admin_module_source_candidate(array $candidate): ?array
{
    $moduleKey = (string) ($candidate['module_key'] ?? '');
    $sourceDir = (string) ($candidate['source_dir'] ?? '');
    if (!toy_is_safe_module_key($moduleKey) || !is_dir($sourceDir)) {
        return null;
    }

    $metadata = toy_admin_load_module_metadata_from_file($sourceDir . '/module.php');
    if ($metadata === []) {
        return null;
    }

    return [
        'module_key' => $moduleKey,
        'source_dir' => $sourceDir,
        'metadata' => $metadata,
    ];
}

function toy_admin_find_module_source(string $extractDir, string $requestedModuleKey, string $filename): array
{
    $inferredModuleKey = $requestedModuleKey !== '' ? $requestedModuleKey : toy_admin_infer_module_key_from_filename($filename);
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

            if (toy_is_safe_module_key($basename)) {
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

    foreach ($candidates as $candidate) {
        $source = toy_admin_module_source_candidate($candidate);
        if (is_array($source)) {
            return $source;
        }
    }

    throw new RuntimeException('zip 안에서 모듈 구조를 찾을 수 없습니다. 최상위 {module_key}/module.php 구조를 사용하거나 module_key를 입력하세요.');
}

function toy_admin_validate_module_source(string $moduleKey, string $sourceDir, array $metadata): array
{
    $errors = [];
    if (in_array($moduleKey, ['member', 'admin'], true)) {
        $errors[] = 'member와 admin 기본 모듈은 zip 업로드로 교체할 수 없습니다.';
    }

    if (!is_file($sourceDir . '/module.php')) {
        $errors[] = 'module.php 파일이 필요합니다.';
    }

    if (!is_file($sourceDir . '/install.sql')) {
        $errors[] = 'install.sql 파일이 필요합니다.';
    }

    $version = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
    if ($version === '' || preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $version) !== 1) {
        $errors[] = 'module.php의 version은 YYYY.MM.NNN 형식이어야 합니다.';
    }

    $type = (string) ($metadata['type'] ?? 'module');
    if (!in_array($type, ['module', 'plugin'], true)) {
        $errors[] = 'module.php의 type은 module 또는 plugin이어야 합니다.';
    }

    foreach (toy_module_contract_errors($metadata) as $error) {
        $errors[] = $error;
    }

    return $errors;
}

function toy_admin_module_upload_version_errors(PDO $pdo, string $moduleKey, array $metadata, bool $allowDowngrade): array
{
    $codeVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
    if ($codeVersion === '' || preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $codeVersion) !== 1) {
        return [];
    }

    $module = toy_module_registry_entry($pdo, $moduleKey);
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

function toy_admin_module_replace_errors(string $moduleKey, bool $replaceConfirmed): array
{
    if (!toy_is_safe_module_key($moduleKey)) {
        return ['모듈 키가 올바르지 않습니다.'];
    }

    if (!is_dir(TOY_ROOT . '/modules/' . $moduleKey) || $replaceConfirmed) {
        return [];
    }

    return [
        '기존 모듈 파일을 교체하려면 백업과 파일 교체 확인을 명시해야 합니다.',
    ];
}

function toy_admin_extract_module_upload(array $file, string $requestedModuleKey): array
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
    $limit = toy_admin_module_upload_limit_bytes();
    if ($size <= 0 || $size > $limit) {
        throw new RuntimeException('업로드 파일 크기는 ' . toy_admin_format_bytes($limit) . ' 이하여야 합니다.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_file($tmpName)) {
        throw new RuntimeException('업로드된 임시 파일을 찾을 수 없습니다.');
    }

    $checksum = hash_file('sha256', $tmpName);
    if (!is_string($checksum)) {
        throw new RuntimeException('업로드 파일 checksum을 계산할 수 없습니다.');
    }

    if ($requestedModuleKey !== '' && !toy_is_safe_module_key($requestedModuleKey)) {
        throw new RuntimeException('입력한 모듈 키가 올바르지 않습니다.');
    }

    $workRoot = toy_admin_module_work_dir('module-upload');
    $extractDir = $workRoot . '/upload-' . date('YmdHis') . '-' . toy_admin_random_suffix();
    if (!mkdir($extractDir, 0755, true)) {
        throw new RuntimeException('업로드 작업 디렉터리를 만들 수 없습니다.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpName) !== true) {
        toy_admin_remove_directory($extractDir);
        throw new RuntimeException('zip 파일을 열 수 없습니다.');
    }

    try {
        $uploadStats = toy_admin_zip_upload_stats($zip);

        if (!$zip->extractTo($extractDir)) {
            throw new RuntimeException('zip 파일을 압축 해제할 수 없습니다.');
        }
    } finally {
        $zip->close();
    }

    try {
        toy_admin_validate_extracted_module_tree($extractDir);

        $source = toy_admin_find_module_source($extractDir, $requestedModuleKey, $filename);
        if ($requestedModuleKey !== '' && (string) $source['module_key'] !== $requestedModuleKey) {
            throw new RuntimeException('zip 내부 모듈 키가 요청한 모듈 키와 일치하지 않습니다.');
        }

        if (!toy_admin_path_is_inside((string) $source['source_dir'], $extractDir)) {
            throw new RuntimeException('zip 안의 모듈 경로가 올바르지 않습니다.');
        }

        $errors = toy_admin_validate_module_source(
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
        toy_admin_remove_directory($extractDir);
        throw $exception;
    }
}

function toy_admin_download_registry_module_zip(array $entry): array
{
    if (!toy_admin_registry_entry_download_ready($entry)) {
        throw new RuntimeException('registry에 유효한 release zip URL과 checksum이 등록되어 있지 않습니다.');
    }

    $moduleKey = (string) $entry['module_key'];
    $version = (string) ($entry['latest_version'] !== '' ? $entry['latest_version'] : 'registry');
    $zipUrl = (string) $entry['zip_url'];
    $expectedChecksum = (string) $entry['checksum'];
    $limit = toy_admin_module_upload_limit_bytes();
    $downloadDir = toy_admin_module_work_dir('module-upload');
    $target = $downloadDir . '/registry-' . $moduleKey . '-' . date('YmdHis') . '-' . toy_admin_random_suffix() . '.zip';

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => "User-Agent: Toycore-Module-Registry\r\n",
        ],
    ]);

    $source = fopen($zipUrl, 'rb', false, $context);
    if (!is_resource($source)) {
        throw new RuntimeException('registry release zip을 다운로드할 수 없습니다.');
    }

    if (!toy_admin_http_stream_status_is_success($source)) {
        fclose($source);
        throw new RuntimeException('registry release zip 다운로드 응답이 성공 상태가 아닙니다.');
    }

    $targetHandle = fopen($target, 'wb');
    if (!is_resource($targetHandle)) {
        fclose($source);
        throw new RuntimeException('registry release zip 임시 파일을 만들 수 없습니다.');
    }

    $hash = hash_init('sha256');
    $bytes = 0;
    try {
        while (!feof($source)) {
            $chunk = fread($source, 8192);
            if (!is_string($chunk)) {
                throw new RuntimeException('registry release zip을 읽을 수 없습니다.');
            }

            if ($chunk === '') {
                continue;
            }

            $bytes += strlen($chunk);
            if ($bytes > $limit) {
                throw new RuntimeException('다운로드 파일 크기는 ' . toy_admin_format_bytes($limit) . ' 이하여야 합니다.');
            }

            hash_update($hash, $chunk);
            if (fwrite($targetHandle, $chunk) === false) {
                throw new RuntimeException('registry release zip 임시 파일을 쓸 수 없습니다.');
            }
        }
    } catch (Throwable $exception) {
        fclose($source);
        fclose($targetHandle);
        if (is_file($target)) {
            unlink($target);
        }
        throw $exception;
    }

    fclose($source);
    fclose($targetHandle);

    $actualChecksum = hash_final($hash);
    if (!hash_equals($expectedChecksum, $actualChecksum)) {
        if (is_file($target)) {
            unlink($target);
        }
        throw new RuntimeException('registry release zip checksum이 일치하지 않습니다.');
    }

    return [
        'error' => UPLOAD_ERR_OK,
        'name' => $moduleKey . '-' . $version . '.zip',
        'size' => $bytes,
        'tmp_name' => $target,
        'registry_module_key' => $moduleKey,
        'registry_zip_url' => $zipUrl,
        'registry_checksum' => $actualChecksum,
    ];
}

function toy_admin_download_registry_repository_archive(array $entry, string $ref): array
{
    if (!toy_admin_registry_entry_repository_ready($entry)) {
        throw new RuntimeException('registry에 허용된 GitHub repository가 등록되어 있지 않습니다.');
    }

    $policyErrors = toy_admin_repository_archive_policy_errors($entry, $ref, toy_runtime_config());
    if ($policyErrors !== []) {
        throw new RuntimeException(implode(' ', $policyErrors));
    }

    $moduleKey = (string) $entry['module_key'];
    $archiveUrl = toy_admin_registry_repository_archive_url($entry, $ref);
    if ($archiveUrl === '') {
        throw new RuntimeException('repository archive URL을 만들 수 없습니다.');
    }

    $limit = toy_admin_module_upload_limit_bytes();
    $downloadDir = toy_admin_module_work_dir('module-upload');
    $target = $downloadDir . '/repository-' . $moduleKey . '-' . date('YmdHis') . '-' . toy_admin_random_suffix() . '.zip';

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => "User-Agent: Toycore-Module-Repository\r\n",
        ],
    ]);

    $source = fopen($archiveUrl, 'rb', false, $context);
    if (!is_resource($source)) {
        throw new RuntimeException('repository archive zip을 다운로드할 수 없습니다.');
    }

    if (!toy_admin_http_stream_status_is_success($source)) {
        fclose($source);
        throw new RuntimeException('repository archive zip 다운로드 응답이 성공 상태가 아닙니다.');
    }

    $targetHandle = fopen($target, 'wb');
    if (!is_resource($targetHandle)) {
        fclose($source);
        throw new RuntimeException('repository archive zip 임시 파일을 만들 수 없습니다.');
    }

    $hash = hash_init('sha256');
    $bytes = 0;
    try {
        while (!feof($source)) {
            $chunk = fread($source, 8192);
            if (!is_string($chunk)) {
                throw new RuntimeException('repository archive zip을 읽을 수 없습니다.');
            }

            if ($chunk === '') {
                continue;
            }

            $bytes += strlen($chunk);
            if ($bytes > $limit) {
                throw new RuntimeException('다운로드 파일 크기는 ' . toy_admin_format_bytes($limit) . ' 이하여야 합니다.');
            }

            hash_update($hash, $chunk);
            if (fwrite($targetHandle, $chunk) === false) {
                throw new RuntimeException('repository archive zip 임시 파일을 쓸 수 없습니다.');
            }
        }
    } catch (Throwable $exception) {
        fclose($source);
        fclose($targetHandle);
        if (is_file($target)) {
            unlink($target);
        }
        throw $exception;
    }

    fclose($source);
    fclose($targetHandle);

    $checksum = hash_final($hash);
    $expectedChecksum = toy_admin_repository_archive_expected_checksum($entry, $ref);
    if ($expectedChecksum !== '' && !hash_equals($expectedChecksum, $checksum)) {
        if (is_file($target)) {
            unlink($target);
        }
        throw new RuntimeException('repository archive checksum이 registry 값과 일치하지 않습니다.');
    }

    return [
        'error' => UPLOAD_ERR_OK,
        'name' => $moduleKey . '-' . str_replace(['/', '\\'], '-', $ref) . '.zip',
        'size' => $bytes,
        'tmp_name' => $target,
        'registry_module_key' => $moduleKey,
        'repository' => (string) $entry['repository'],
        'repository_ref' => $ref,
        'repository_archive_url' => $archiveUrl,
        'repository_archive_checksum' => $checksum,
    ];
}

function toy_admin_install_module_source_files(string $moduleKey, string $sourceDir): array
{
    if (!toy_is_safe_module_key($moduleKey)) {
        throw new InvalidArgumentException('Module key is invalid.');
    }

    $modulesRoot = toy_admin_module_source_root();
    if (!is_dir($modulesRoot) && !mkdir($modulesRoot, 0755, true)) {
        throw new RuntimeException('modules 디렉터리를 만들 수 없습니다.');
    }

    $targetDir = $modulesRoot . '/' . $moduleKey;
    $backupDir = '';
    if (is_dir($targetDir)) {
        $backupRoot = toy_admin_module_work_dir('module-backups');
        $backupDir = $backupRoot . '/' . $moduleKey . '-' . date('YmdHis') . '-' . toy_admin_random_suffix();
        if (!rename($targetDir, $backupDir)) {
            throw new RuntimeException('기존 모듈 디렉터리를 백업할 수 없습니다.');
        }
    }

    try {
        toy_admin_copy_directory($sourceDir, $targetDir);
    } catch (Throwable $exception) {
        if (is_dir($targetDir)) {
            toy_admin_remove_directory($targetDir);
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

function toy_admin_module_pending_update_counts(array $pendingUpdates): array
{
    $counts = [];
    foreach ($pendingUpdates as $update) {
        if ((string) ($update['scope'] ?? '') !== 'module') {
            continue;
        }

        $moduleKey = (string) ($update['module_key'] ?? '');
        if (!toy_is_safe_module_key($moduleKey)) {
            continue;
        }

        $counts[$moduleKey] = (int) ($counts[$moduleKey] ?? 0) + 1;
    }

    return $counts;
}

function toy_admin_sync_module_version(PDO $pdo, string $moduleKey, string $newVersion): void
{
    if (!toy_is_safe_module_key($moduleKey) || preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $newVersion) !== 1) {
        throw new InvalidArgumentException('Module version is invalid.');
    }

    $stmt = $pdo->prepare(
        'UPDATE toy_modules
         SET version = :version, updated_at = :updated_at
         WHERE module_key = :module_key'
    );
    $stmt->execute([
        'version' => $newVersion,
        'updated_at' => toy_now(),
        'module_key' => $moduleKey,
    ]);
}

function toy_admin_sync_file_only_module_versions(PDO $pdo, array $pendingUpdateCounts): array
{
    $synced = [];
    $stmt = $pdo->query('SELECT module_key, version FROM toy_modules ORDER BY module_key ASC');
    foreach ($stmt->fetchAll() as $module) {
        $moduleKey = (string) ($module['module_key'] ?? '');
        $installedVersion = (string) ($module['version'] ?? '');
        if (!toy_is_safe_module_key($moduleKey) || (int) ($pendingUpdateCounts[$moduleKey] ?? 0) > 0) {
            continue;
        }

        $metadata = toy_module_metadata($moduleKey);
        $codeVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
        if (preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $codeVersion) !== 1 || strcmp($codeVersion, $installedVersion) <= 0) {
            continue;
        }

        toy_admin_sync_module_version($pdo, $moduleKey, $codeVersion);
        $synced[] = [
            'module_key' => $moduleKey,
            'before_version' => $installedVersion,
            'after_version' => $codeVersion,
        ];
    }

    return $synced;
}
