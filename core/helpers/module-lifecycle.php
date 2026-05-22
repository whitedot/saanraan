<?php

declare(strict_types=1);

function sr_schema_update_files(string $directory): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $paths = glob($directory . '/*.sql');
    if ($paths === false) {
        return [];
    }

    sort($paths, SORT_STRING);

    $updates = [];
    foreach ($paths as $path) {
        $version = basename($path, '.sql');
        if (preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $version) !== 1) {
            continue;
        }

        $updates[] = [
            'version' => $version,
            'path' => $path,
            'checksum' => sr_schema_update_checksum($path),
        ];
    }

    return $updates;
}

function sr_schema_update_checksum(string $path): string
{
    if (!is_file($path)) {
        return '';
    }

    $checksum = hash_file('sha256', $path);
    return is_string($checksum) ? $checksum : '';
}

function sr_schema_update_statement_count(string $path): int
{
    if (!is_file($path)) {
        return 0;
    }

    $sql = file_get_contents($path);
    if (!is_string($sql)) {
        return 0;
    }

    return count(sr_split_sql_statements($sql));
}

function sr_schema_update_path_is_allowed(array $update): bool
{
    $scope = (string) ($update['scope'] ?? '');
    $moduleKey = (string) ($update['module_key'] ?? '');
    $version = (string) ($update['version'] ?? '');
    $path = (string) ($update['path'] ?? '');

    if (preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $version) !== 1 || !is_file($path)) {
        return false;
    }

    if ($scope === 'core') {
        $expectedDirectory = realpath(SR_ROOT . '/database/core/updates');
        $expectedModuleKey = '';
    } elseif ($scope === 'module' && sr_is_safe_module_key($moduleKey)) {
        $expectedDirectory = realpath(SR_ROOT . '/modules/' . $moduleKey . '/updates');
        $expectedModuleKey = $moduleKey;
    } else {
        return false;
    }

    if ($moduleKey !== $expectedModuleKey || $expectedDirectory === false) {
        return false;
    }

    $realPath = realpath($path);
    if ($realPath === false || strpos($realPath, $expectedDirectory . DIRECTORY_SEPARATOR) !== 0) {
        return false;
    }

    return basename($realPath) === $version . '.sql';
}

function sr_schema_update_lock_acquire(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare('SELECT GET_LOCK(:lock_name, 10) AS lock_acquired');
        $stmt->execute(['lock_name' => 'saanraan_schema_updates']);
        $row = $stmt->fetch();
    } catch (Throwable $exception) {
        return false;
    }

    return is_array($row) && (string) ($row['lock_acquired'] ?? '') === '1';
}

function sr_schema_update_lock_release(PDO $pdo): void
{
    try {
        $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $stmt->execute(['lock_name' => 'saanraan_schema_updates']);
    } catch (Throwable $ignored) {
    }
}

function sr_applied_schema_version_map(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT scope, module_key, version FROM sr_schema_versions');
    $applied = [];

    foreach ($stmt->fetchAll() as $row) {
        $key = (string) $row['scope'] . '|' . (string) $row['module_key'] . '|' . (string) $row['version'];
        $applied[$key] = true;
    }

    return $applied;
}

function sr_schema_version_rows(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT scope, module_key, version, applied_at
         FROM sr_schema_versions
         ORDER BY scope ASC, module_key ASC, version ASC'
    );

    return $stmt->fetchAll();
}

function sr_pending_schema_updates(PDO $pdo): array
{
    $applied = sr_applied_schema_version_map($pdo);
    $pending = [];

    foreach (sr_schema_update_files(SR_ROOT . '/database/core/updates') as $update) {
        $key = 'core||' . $update['version'];
        if (!isset($applied[$key])) {
            $pending[] = [
                'scope' => 'core',
                'module_key' => '',
                'label' => 'core',
                'version' => $update['version'],
                'path' => $update['path'],
                'checksum' => $update['checksum'],
                'statements' => sr_schema_update_statement_count((string) $update['path']),
            ];
        }
    }

    $stmt = $pdo->query('SELECT module_key FROM sr_modules ORDER BY module_key ASC');
    foreach ($stmt->fetchAll() as $module) {
        $moduleKey = (string) $module['module_key'];
        if (!sr_is_safe_module_key($moduleKey)) {
            continue;
        }

        foreach (sr_schema_update_files(SR_ROOT . '/modules/' . $moduleKey . '/updates') as $update) {
            $key = 'module|' . $moduleKey . '|' . $update['version'];
            if (!isset($applied[$key])) {
                $pending[] = [
                    'scope' => 'module',
                    'module_key' => $moduleKey,
                    'label' => $moduleKey,
                    'version' => $update['version'],
                    'path' => $update['path'],
                    'checksum' => $update['checksum'],
                    'statements' => sr_schema_update_statement_count((string) $update['path']),
                ];
            }
        }
    }

    return $pending;
}

function sr_apply_schema_update(PDO $pdo, array $update): void
{
    if (!sr_schema_update_path_is_allowed($update)) {
        throw new RuntimeException('Schema update path is invalid.');
    }

    $expectedChecksum = (string) ($update['checksum'] ?? '');
    if ($expectedChecksum !== '' && !hash_equals($expectedChecksum, sr_schema_update_checksum((string) $update['path']))) {
        throw new RuntimeException('Schema update checksum changed.');
    }

    sr_execute_sql_file($pdo, (string) $update['path']);
    sr_record_schema_version($pdo, (string) $update['scope'], (string) $update['module_key'], (string) $update['version']);
}

function sr_previous_schema_update_failure(): ?array
{
    $path = SR_ROOT . '/storage/update-failed.json';
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $json = file_get_contents($path);
    $decoded = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($decoded)) {
        return null;
    }

    return [
        'recorded_at' => (string) ($decoded['recorded_at'] ?? ''),
        'stage' => (string) ($decoded['stage'] ?? ''),
        'scope' => (string) ($decoded['scope'] ?? ''),
        'module_key' => (string) ($decoded['module_key'] ?? ''),
        'version' => (string) ($decoded['version'] ?? ''),
        'checksum' => (string) ($decoded['checksum'] ?? ''),
        'message' => sr_log_sensitive_text_sanitize(sr_log_line_value((string) ($decoded['message'] ?? ''), 500)),
    ];
}

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

function sr_format_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / 1024 / 1024, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return (string) $bytes . ' bytes';
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

function sr_php_string_array_value(string $content, string $key): string
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

function sr_php_array_block(string $content, string $key): string
{
    foreach (['\'', '"'] as $quote) {
        $quotedKey = preg_quote($key, '/');
        $quotedQuote = preg_quote($quote, '/');
        $pattern = '/' . $quotedQuote . $quotedKey . $quotedQuote . '\s*=>\s*(\[|array\s*\()/i';
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            continue;
        }

        $token = (string) $matches[1][0];
        $offset = (int) $matches[1][1];
        $openOffset = $offset;
        if ($token !== '[') {
            $parenOffset = strpos($token, '(');
            if ($parenOffset === false) {
                continue;
            }

            $openOffset += $parenOffset;
        }
        $block = sr_php_balanced_block($content, $openOffset, $token === '[' ? '[' : '(', $token === '[' ? ']' : ')');
        if ($block !== '') {
            return $block;
        }
    }

    return '';
}

function sr_php_balanced_block(string $content, int $openOffset, string $openChar, string $closeChar): string
{
    $length = strlen($content);
    if ($openOffset < 0 || $openOffset >= $length || $content[$openOffset] !== $openChar) {
        return '';
    }

    $depth = 0;
    $quote = '';
    $lineComment = false;
    $blockComment = false;
    for ($i = $openOffset; $i < $length; $i++) {
        $char = $content[$i];
        $next = $i + 1 < $length ? $content[$i + 1] : '';

        if ($lineComment) {
            if ($char === "\n") {
                $lineComment = false;
            }
            continue;
        }

        if ($blockComment) {
            if ($char === '*' && $next === '/') {
                $i++;
                $blockComment = false;
            }
            continue;
        }

        if ($quote !== '') {
            if ($char === '\\' && $next !== '') {
                $i++;
                continue;
            }

            if ($char === $quote) {
                $quote = '';
            }
            continue;
        }

        if ($char === '\'' || $char === '"') {
            $quote = $char;
            continue;
        }

        if (($char === '/' && $next === '/') || $char === '#') {
            $lineComment = true;
            if ($char === '/') {
                $i++;
            }
            continue;
        }

        if ($char === '/' && $next === '*') {
            $i++;
            $blockComment = true;
            continue;
        }

        if ($char === $openChar) {
            $depth++;
            continue;
        }

        if ($char === $closeChar) {
            $depth--;
            if ($depth === 0) {
                return substr($content, $openOffset, $i - $openOffset + 1);
            }
        }
    }

    return '';
}

function sr_php_string_list_array_value(string $content, string $key): array
{
    $block = sr_php_array_block($content, $key);
    if ($block === '') {
        return [];
    }

    $values = [];
    foreach (['\'', '"'] as $quote) {
        $quotedQuote = preg_quote($quote, '/');
        $pattern = '/' . $quotedQuote . '((?:\\\\.|[^' . $quotedQuote . '\\\\])*)' . $quotedQuote . '/';
        if (preg_match_all($pattern, $block, $matches) !== false) {
            foreach ($matches[1] as $value) {
                $values[] = stripcslashes((string) $value);
            }
        }
    }

    return array_values(array_unique($values));
}

function sr_php_saanraan_metadata(string $content): array
{
    $block = sr_php_array_block($content, 'saanraan');
    if ($block === '') {
        return [];
    }

    $saanraan = [];
    foreach (['min_version', 'module_contract'] as $key) {
        $value = sr_php_string_array_value($block, $key);
        if ($value !== '') {
            $saanraan[$key] = $value;
        }
    }

    $testedWith = sr_php_string_list_array_value($block, 'tested_with');
    if ($testedWith !== []) {
        $saanraan['tested_with'] = $testedWith;
    }

    return $saanraan;
}

function sr_load_module_metadata_from_file(string $file): array
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
        $value = sr_php_string_array_value($content, $key);
        if ($value !== '') {
            $metadata[$key] = $value;
        }
    }

    $saanraan = sr_php_saanraan_metadata($content);
    if ($saanraan !== []) {
        $metadata['saanraan'] = $saanraan;
    }

    return $metadata;
}

function sr_module_source_candidate(array $candidate): ?array
{
    $moduleKey = (string) ($candidate['module_key'] ?? '');
    $sourceDir = (string) ($candidate['source_dir'] ?? '');
    if (!sr_is_safe_module_key($moduleKey) || !is_dir($sourceDir)) {
        return null;
    }

    $metadata = sr_load_module_metadata_from_file($sourceDir . '/module.php');
    if ($metadata === []) {
        return null;
    }

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

    foreach ($candidates as $candidate) {
        $source = sr_module_source_candidate($candidate);
        if (is_array($source)) {
            return $source;
        }
    }

    throw new RuntimeException('zip 안에서 모듈 구조를 찾을 수 없습니다. 최상위 {module_key}/module.php 구조를 사용하거나 module_key를 입력하세요.');
}

function sr_validate_module_source(string $moduleKey, string $sourceDir, array $metadata): array
{
    $errors = [];
    if (in_array($moduleKey, ['member', 'admin', 'privacy'], true)) {
        $errors[] = '회원, 관리자, 개인정보 기본 모듈은 zip 업로드로 교체할 수 없습니다.';
    }

    if (!is_file($sourceDir . '/module.php')) {
        $errors[] = 'module.php 파일이 필요합니다.';
    }

    if (!is_file($sourceDir . '/install.sql')) {
        $errors[] = 'install.sql 파일이 필요합니다.';
    }

    foreach (sr_module_metadata_errors($metadata) as $error) {
        $errors[] = $error;
    }

    foreach (sr_module_contract_file_errors($sourceDir, $metadata) as $error) {
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

function sr_module_pending_update_counts(array $pendingUpdates): array
{
    $counts = [];
    foreach ($pendingUpdates as $update) {
        if ((string) ($update['scope'] ?? '') !== 'module') {
            continue;
        }

        $moduleKey = (string) ($update['module_key'] ?? '');
        if (!sr_is_safe_module_key($moduleKey)) {
            continue;
        }

        $counts[$moduleKey] = (int) ($counts[$moduleKey] ?? 0) + 1;
    }

    return $counts;
}

function sr_sync_module_version(PDO $pdo, string $moduleKey, string $newVersion): void
{
    if (!sr_is_safe_module_key($moduleKey) || preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $newVersion) !== 1) {
        throw new InvalidArgumentException('Module version is invalid.');
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_modules
         SET version = :version, updated_at = :updated_at
         WHERE module_key = :module_key'
    );
    $stmt->execute([
        'version' => $newVersion,
        'updated_at' => sr_now(),
        'module_key' => $moduleKey,
    ]);
}

function sr_sync_file_only_module_versions(PDO $pdo, array $pendingUpdateCounts): array
{
    $synced = [];
    $stmt = $pdo->query('SELECT module_key, version, status FROM sr_modules ORDER BY module_key ASC');
    foreach ($stmt->fetchAll() as $module) {
        $moduleKey = (string) ($module['module_key'] ?? '');
        $installedVersion = (string) ($module['version'] ?? '');
        if (!sr_is_safe_module_key($moduleKey) || (int) ($pendingUpdateCounts[$moduleKey] ?? 0) > 0) {
            continue;
        }

        $metadata = sr_module_metadata($moduleKey);
        $codeVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
        if (
            preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $codeVersion) !== 1
            || strcmp($codeVersion, $installedVersion) <= 0
            || !sr_module_contract_is_loadable($moduleKey)
            || sr_module_requirement_errors($pdo, $moduleKey, $metadata, (string) ($module['status'] ?? 'enabled')) !== []
        ) {
            continue;
        }

        sr_sync_module_version($pdo, $moduleKey, $codeVersion);
        $synced[] = [
            'module_key' => $moduleKey,
            'before_version' => $installedVersion,
            'after_version' => $codeVersion,
        ];
    }

    return $synced;
}

function sr_module_route_conflict_errors(PDO $pdo, string $candidateModuleKey): array
{
    if (!sr_is_safe_module_key($candidateModuleKey)) {
        return ['모듈 키가 올바르지 않습니다.'];
    }

    $candidateRoutes = sr_module_route_map($candidateModuleKey);
    if ($candidateRoutes['errors'] !== []) {
        return $candidateRoutes['errors'];
    }

    $candidateRouteMap = $candidateRoutes['routes'];
    if ($candidateRouteMap === []) {
        return [];
    }

    $errors = [];
    foreach (sr_enabled_module_contract_files($pdo, 'paths.php', [$candidateModuleKey]) as $moduleKey => $pathsFile) {
        $paths = sr_load_module_contract_file($moduleKey, $pathsFile);
        if (!is_array($paths)) {
            continue;
        }

        foreach ($paths as $route => $actionRelativePath) {
            $route = (string) $route;
            $actionRelativePath = (string) $actionRelativePath;
            if (!sr_is_valid_module_route($route)) {
                continue;
            }

            foreach (array_keys($candidateRouteMap) as $candidateRoute) {
                if (!sr_module_routes_conflict((string) $candidateRoute, $route)) {
                    continue;
                }

                $errors[] = $candidateModuleKey . ' 모듈 route가 ' . $moduleKey . ' 모듈과 충돌합니다: ' . (string) $candidateRoute . ' / ' . $route;
            }
        }
    }

    return array_values(array_unique($errors));
}

function sr_module_route_map(string $moduleKey): array
{
    $moduleDir = SR_ROOT . '/modules/' . $moduleKey;
    $pathsFile = $moduleDir . '/paths.php';
    if (!is_file($pathsFile)) {
        return ['routes' => [], 'errors' => []];
    }

    $paths = sr_load_module_contract_file($moduleKey, $pathsFile);
    if (!is_array($paths)) {
        return ['routes' => [], 'errors' => [$moduleKey . ' 모듈의 paths.php는 배열을 반환해야 합니다.']];
    }

    $routes = [];
    $errors = [];
    foreach ($paths as $route => $actionRelativePath) {
        $route = (string) $route;
        $actionRelativePath = (string) $actionRelativePath;
        if (!sr_is_valid_module_route($route)) {
            $errors[] = $moduleKey . ' 모듈 route 형식이 올바르지 않습니다: ' . $route;
            continue;
        }

        if (!sr_is_safe_module_action($actionRelativePath)) {
            $errors[] = $moduleKey . ' 모듈 action 경로가 올바르지 않습니다: ' . $route;
            continue;
        }

        if (!is_file($moduleDir . '/' . $actionRelativePath)) {
            $errors[] = $moduleKey . ' 모듈 action 파일을 찾을 수 없습니다: ' . $route;
            continue;
        }

        $routes[$route] = $actionRelativePath;
    }

    return ['routes' => $routes, 'errors' => array_values(array_unique($errors))];
}

function sr_module_code_older_errors(PDO $pdo, string $moduleKey): array
{
    $module = sr_module_record_entry($pdo, $moduleKey);
    $metadata = sr_module_metadata($moduleKey);
    $installedVersion = is_array($module) ? (string) ($module['version'] ?? '') : '';
    $codeVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';

    if (
        preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $installedVersion) === 1
        && preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $codeVersion) === 1
        && strcmp($codeVersion, $installedVersion) < 0
    ) {
        return [sr_t('module.lifecycle.error.code_older')];
    }

    return [];
}

function sr_module_lifecycle_state(array $module): array
{
    $status = (string) ($module['status'] ?? '');
    $metadataErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : [];
    $pendingUpdateCount = (int) ($module['pending_update_count'] ?? 0);
    $versionState = (string) ($module['version_state'] ?? 'unknown');

    if (in_array($status, ['failed', 'installing'], true)) {
        return [
            'state' => 'install_incomplete',
            'label' => sr_t('module.lifecycle.install_incomplete.label'),
            'action' => sr_t('module.lifecycle.install_incomplete.action'),
        ];
    }

    if ($metadataErrors !== []) {
        return [
            'state' => 'contract_error',
            'label' => sr_t('module.lifecycle.contract_error.label'),
            'action' => sr_t('module.lifecycle.contract_error.action'),
        ];
    }

    if ($versionState === 'code_older') {
        return [
            'state' => 'code_older',
            'label' => sr_t('module.lifecycle.code_older.label'),
            'action' => sr_t('module.lifecycle.code_older.action'),
        ];
    }

    if ($pendingUpdateCount > 0) {
        return [
            'state' => 'sql_pending',
            'label' => sr_t('module.lifecycle.sql_pending.label'),
            'action' => sr_t('module.lifecycle.sql_pending.action'),
        ];
    }

    if ($versionState === 'code_newer') {
        return [
            'state' => 'file_only_update',
            'label' => sr_t('module.lifecycle.file_only_update.label'),
            'action' => sr_t('module.lifecycle.file_only_update.action'),
        ];
    }

    if ($status === 'enabled') {
        return [
            'state' => 'enabled_current',
            'label' => sr_t('module.lifecycle.enabled_current.label'),
            'action' => '-',
        ];
    }

    if ($status === 'disabled') {
        return [
            'state' => 'disabled_current',
            'label' => sr_t('module.lifecycle.disabled_current.label'),
            'action' => sr_t('module.lifecycle.disabled_current.action'),
        ];
    }

    return [
        'state' => 'unknown',
        'label' => sr_t('module.lifecycle.unknown.label'),
        'action' => sr_t('module.lifecycle.unknown.action'),
    ];
}

function sr_module_version_drifts(PDO $pdo, array $pendingUpdateCounts): array
{
    $moduleVersionDrifts = [];
    $stmt = $pdo->query('SELECT module_key, version FROM sr_modules ORDER BY module_key ASC');
    foreach ($stmt->fetchAll() as $module) {
        $moduleKey = (string) ($module['module_key'] ?? '');
        if (!sr_is_safe_module_key($moduleKey)) {
            continue;
        }

        $metadata = sr_module_metadata($moduleKey);
        $codeVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
        $installedVersion = (string) ($module['version'] ?? '');
        if ($codeVersion === '' || $installedVersion === '' || $codeVersion === $installedVersion) {
            continue;
        }

        $moduleVersionDrifts[] = [
            'module_key' => $moduleKey,
            'installed_version' => $installedVersion,
            'code_version' => $codeVersion,
            'pending_update_count' => (int) ($pendingUpdateCounts[$moduleKey] ?? 0),
            'state' => strcmp($codeVersion, $installedVersion) > 0 ? 'code_newer' : 'code_older',
        ];
    }

    return $moduleVersionDrifts;
}

function sr_file_only_module_version_drifts(array $moduleVersionDrifts): array
{
    $fileOnlyDrifts = [];
    foreach ($moduleVersionDrifts as $drift) {
        if ((int) ($drift['pending_update_count'] ?? 0) > 0 || (string) ($drift['state'] ?? '') !== 'code_newer') {
            continue;
        }

        $fileOnlyDrifts[] = $drift;
    }

    return $fileOnlyDrifts;
}

function sr_install_module(PDO $pdo, string $moduleKey, string $status, bool $isBundled = false): array
{
    if (!sr_is_safe_module_key($moduleKey)) {
        throw new InvalidArgumentException('Module key is invalid.');
    }

    $moduleDir = SR_ROOT . '/modules/' . $moduleKey;
    $realModulesDir = realpath(SR_ROOT . '/modules');
    $realModuleDir = realpath($moduleDir);
    $installSql = $moduleDir . '/install.sql';
    $metadata = sr_module_metadata($moduleKey);

    if ($realModulesDir === false || $realModuleDir === false || strpos($realModuleDir, $realModulesDir . DIRECTORY_SEPARATOR) !== 0) {
        throw new RuntimeException('설치할 모듈 디렉터리를 찾을 수 없습니다.');
    }

    if ($metadata === []) {
        throw new RuntimeException('모듈 메타데이터를 찾을 수 없습니다.');
    }

    if (!is_file($installSql)) {
        throw new RuntimeException('모듈 설치 SQL 파일을 찾을 수 없습니다.');
    }

    $metadataErrors = array_merge(
        sr_module_metadata_errors($metadata),
        sr_module_contract_file_errors($moduleDir, $metadata),
        sr_module_requirement_errors($pdo, $moduleKey, $metadata, $status)
    );
    if ($status === 'enabled') {
        $metadataErrors = array_merge($metadataErrors, sr_module_route_conflict_errors($pdo, $moduleKey));
    }
    if ($metadataErrors !== []) {
        throw new RuntimeException(implode(' ', array_values(array_unique($metadataErrors))));
    }

    $stmt = $pdo->prepare('SELECT id, status FROM sr_modules WHERE module_key = :module_key LIMIT 1');
    $stmt->execute(['module_key' => $moduleKey]);
    $existingModule = $stmt->fetch();
    if (is_array($existingModule) && !in_array((string) $existingModule['status'], ['failed', 'installing'], true)) {
        throw new RuntimeException('이미 설치된 모듈입니다.');
    }

    $now = sr_now();
    $moduleName = is_string($metadata['name'] ?? null) && (string) $metadata['name'] !== ''
        ? (string) $metadata['name']
        : $moduleKey;
    $moduleVersion = is_string($metadata['version'] ?? null) && (string) $metadata['version'] !== ''
        ? (string) $metadata['version']
        : '2026.04.001';

    try {
        if (is_array($existingModule)) {
            $stmt = $pdo->prepare(
                "UPDATE sr_modules
                 SET name = :name, version = :version, status = 'installing', updated_at = :updated_at
                 WHERE module_key = :module_key"
            );
            $stmt->execute([
                'name' => $moduleName,
                'version' => $moduleVersion,
                'updated_at' => $now,
                'module_key' => $moduleKey,
            ]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO sr_modules (module_key, name, version, status, is_bundled, installed_at, updated_at)
                 VALUES (:module_key, :name, :version, 'installing', :is_bundled, :installed_at, :updated_at)"
            );
            $stmt->execute([
                'module_key' => $moduleKey,
                'name' => $moduleName,
                'version' => $moduleVersion,
                'is_bundled' => $isBundled ? 1 : 0,
                'installed_at' => $now,
                'updated_at' => $now,
            ]);
        }

        sr_execute_sql_file($pdo, $installSql);
        sr_record_installed_module_schema_versions($pdo, $moduleKey, $moduleVersion);

        $completedAt = sr_now();
        $stmt = $pdo->prepare(
            'UPDATE sr_modules
             SET status = :status, installed_at = :installed_at, updated_at = :updated_at
             WHERE module_key = :module_key'
        );
        $stmt->execute([
            'status' => $status,
            'installed_at' => $completedAt,
            'updated_at' => $completedAt,
            'module_key' => $moduleKey,
        ]);
    } catch (Throwable $exception) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE sr_modules
                 SET status = 'failed', updated_at = :updated_at
                 WHERE module_key = :module_key AND status = 'installing'"
            );
            $stmt->execute([
                'updated_at' => sr_now(),
                'module_key' => $moduleKey,
            ]);
        } catch (Throwable $ignored) {
        }

        throw $exception;
    }

    return [
        'module_key' => $moduleKey,
        'name' => $moduleName,
        'version' => $moduleVersion,
        'status' => $status,
    ];
}

function sr_update_module_status(PDO $pdo, string $moduleKey, string $status): array
{
    if (!sr_is_safe_module_key($moduleKey)) {
        throw new InvalidArgumentException('Module key is invalid.');
    }

    $stmt = $pdo->prepare('SELECT id, status FROM sr_modules WHERE module_key = :module_key LIMIT 1');
    $stmt->execute(['module_key' => $moduleKey]);
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('모듈을 찾을 수 없습니다.');
    }

    $beforeStatus = (string) $module['status'];
    $stmt = $pdo->prepare(
        'UPDATE sr_modules
         SET status = :status, updated_at = :updated_at
         WHERE module_key = :module_key'
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => sr_now(),
        'module_key' => $moduleKey,
    ]);

    return [
        'module_key' => $moduleKey,
        'before_status' => $beforeStatus,
        'after_status' => $status,
    ];
}

function sr_load_module_management_view_data(PDO $pdo): array
{
    $modules = [];
    $pendingUpdateCounts = sr_module_pending_update_counts(sr_pending_schema_updates($pdo));
    $stmt = $pdo->query('SELECT id, module_key, name, version, status, is_bundled, installed_at, updated_at FROM sr_modules ORDER BY id ASC');
    $installedModuleKeys = [];
    foreach ($stmt->fetchAll() as $row) {
        $installedModuleKeys[(string) $row['module_key']] = true;
        $metadata = sr_module_metadata((string) $row['module_key']);
        $moduleDirectory = SR_ROOT . '/modules/' . (string) $row['module_key'];
        $metadataErrors = $metadata === [] ? ['module.php 파일을 읽을 수 없습니다.'] : array_merge(
            sr_module_metadata_errors($metadata),
            sr_module_contract_file_errors($moduleDirectory, $metadata)
        );
        $row['code_name'] = is_string($metadata['name'] ?? null) ? (string) $metadata['name'] : '';
        $row['code_version'] = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
        $row['code_type'] = sr_module_type((string) $row['module_key']);
        $row['description'] = is_string($metadata['description'] ?? null) ? (string) $metadata['description'] : '';
        $saanraanMetadata = is_array($metadata['saanraan'] ?? null) ? $metadata['saanraan'] : [];
        $saanraanTestedWith = $saanraanMetadata['tested_with'] ?? [];
        $row['saanraan_min_version'] = is_string($saanraanMetadata['min_version'] ?? null) ? (string) $saanraanMetadata['min_version'] : '';
        $row['saanraan_tested_with'] = is_array($saanraanTestedWith)
            ? implode(', ', array_map('strval', $saanraanTestedWith))
            : (is_string($saanraanTestedWith) ? $saanraanTestedWith : '');
        $row['saanraan_module_contract'] = is_string($saanraanMetadata['module_contract'] ?? null) ? (string) $saanraanMetadata['module_contract'] : '';
        $row['metadata_errors'] = $metadataErrors;
        $row['pending_update_count'] = (int) ($pendingUpdateCounts[(string) $row['module_key']] ?? 0);
        $row['version_state'] = 'unknown';
        if ((string) $row['code_version'] !== '' && (string) $row['version'] !== '') {
            $comparison = strcmp((string) $row['code_version'], (string) $row['version']);
            if ($comparison > 0) {
                $row['version_state'] = 'code_newer';
            } elseif ($comparison < 0) {
                $row['version_state'] = 'code_older';
            } else {
                $row['version_state'] = 'same';
            }
        }
        $lifecycle = sr_module_lifecycle_state($row);
        $row['lifecycle_state'] = (string) $lifecycle['state'];
        $row['lifecycle_label'] = (string) $lifecycle['label'];
        $row['lifecycle_action'] = (string) $lifecycle['action'];
        $modules[] = $row;
    }

    $installableModules = [];
    $moduleDirectories = glob(SR_ROOT . '/modules/*', GLOB_ONLYDIR);
    if (is_array($moduleDirectories)) {
        sort($moduleDirectories, SORT_STRING);
        foreach ($moduleDirectories as $moduleDirectory) {
            $moduleKey = basename($moduleDirectory);
            if (!sr_is_safe_module_key($moduleKey) || isset($installedModuleKeys[$moduleKey])) {
                continue;
            }

            $metadata = sr_module_metadata($moduleKey);
            if ($metadata === [] && !is_file($moduleDirectory . '/module.php')) {
                continue;
            }
            $missingInstallSql = !is_file($moduleDirectory . '/install.sql');
            $saanraanMetadata = is_array($metadata['saanraan'] ?? null) ? $metadata['saanraan'] : [];
            $saanraanTestedWith = $saanraanMetadata['tested_with'] ?? [];
            $metadataErrors = $metadata === []
                ? ['module.php 파일을 읽을 수 없습니다.']
                : array_merge(
                    sr_module_metadata_errors($metadata),
                    sr_module_contract_file_errors($moduleDirectory, $metadata)
                );
            if ($missingInstallSql) {
                $metadataErrors[] = 'install.sql 파일이 필요합니다.';
            }

            $installableModules[] = [
                'module_key' => $moduleKey,
                'name' => is_string($metadata['name'] ?? null) ? (string) $metadata['name'] : $moduleKey,
                'version' => is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '',
                'type' => sr_module_type($moduleKey),
                'description' => is_string($metadata['description'] ?? null) ? (string) $metadata['description'] : '',
                'saanraan_min_version' => is_string($saanraanMetadata['min_version'] ?? null) ? (string) $saanraanMetadata['min_version'] : '',
                'saanraan_tested_with' => is_array($saanraanTestedWith)
                    ? implode(', ', array_map('strval', $saanraanTestedWith))
                    : (is_string($saanraanTestedWith) ? $saanraanTestedWith : ''),
                'saanraan_module_contract' => is_string($saanraanMetadata['module_contract'] ?? null) ? (string) $saanraanMetadata['module_contract'] : '',
                'metadata_errors' => $metadataErrors,
                'lifecycle_state' => $metadataErrors === [] ? 'not_installed' : 'install_blocked',
                'lifecycle_label' => $metadataErrors === [] ? '미설치' : '설치 차단',
                'lifecycle_action' => $metadataErrors === [] ? '설치 가능' : '모듈 파일 확인',
            ];
        }
    }

    return [
        'modules' => $modules,
        'installable_modules' => $installableModules,
    ];
}
