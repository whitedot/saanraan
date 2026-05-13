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

function toy_admin_runtime_is_production(?array $config = null): bool
{
    $config = is_array($config) ? $config : toy_runtime_config();
    return (string) ($config['env'] ?? 'production') === 'production';
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

function toy_admin_php_array_block(string $content, string $key): string
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
        $block = toy_admin_php_balanced_block($content, $openOffset, $token === '[' ? '[' : '(', $token === '[' ? ']' : ')');
        if ($block !== '') {
            return $block;
        }
    }

    return '';
}

function toy_admin_php_balanced_block(string $content, int $openOffset, string $openChar, string $closeChar): string
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

function toy_admin_php_string_list_array_value(string $content, string $key): array
{
    $block = toy_admin_php_array_block($content, $key);
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

function toy_admin_php_toycore_metadata(string $content): array
{
    $block = toy_admin_php_array_block($content, 'toycore');
    if ($block === '') {
        return [];
    }

    $toycore = [];
    foreach (['min_version', 'module_contract'] as $key) {
        $value = toy_admin_php_string_array_value($block, $key);
        if ($value !== '') {
            $toycore[$key] = $value;
        }
    }

    $testedWith = toy_admin_php_string_list_array_value($block, 'tested_with');
    if ($testedWith !== []) {
        $toycore['tested_with'] = $testedWith;
    }

    return $toycore;
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

    $toycore = toy_admin_php_toycore_metadata($content);
    if ($toycore !== []) {
        $metadata['toycore'] = $toycore;
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
    if (in_array($moduleKey, ['member', 'admin', 'privacy'], true)) {
        $errors[] = '회원, 관리자, 개인정보 기본 모듈은 zip 업로드로 교체할 수 없습니다.';
    }

    if (!is_file($sourceDir . '/module.php')) {
        $errors[] = 'module.php 파일이 필요합니다.';
    }

    if (!is_file($sourceDir . '/install.sql')) {
        $errors[] = 'install.sql 파일이 필요합니다.';
    }

    foreach (toy_admin_module_metadata_errors($metadata) as $error) {
        $errors[] = $error;
    }

    foreach (toy_module_contract_file_errors($sourceDir, $metadata) as $error) {
        $errors[] = $error;
    }

    return $errors;
}

function toy_admin_module_metadata_errors(array $metadata): array
{
    return toy_module_metadata_errors($metadata);
}

function toy_admin_module_upload_version_errors(PDO $pdo, string $moduleKey, array $metadata, bool $allowDowngrade): array
{
    $codeVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
    if ($codeVersion === '' || preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $codeVersion) !== 1) {
        return [];
    }

    $module = toy_module_record_entry($pdo, $moduleKey);
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
    $stmt = $pdo->query('SELECT module_key, version, status FROM toy_modules ORDER BY module_key ASC');
    foreach ($stmt->fetchAll() as $module) {
        $moduleKey = (string) ($module['module_key'] ?? '');
        $installedVersion = (string) ($module['version'] ?? '');
        if (!toy_is_safe_module_key($moduleKey) || (int) ($pendingUpdateCounts[$moduleKey] ?? 0) > 0) {
            continue;
        }

        $metadata = toy_module_metadata($moduleKey);
        $codeVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
        if (
            preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $codeVersion) !== 1
            || strcmp($codeVersion, $installedVersion) <= 0
            || !toy_module_contract_is_loadable($moduleKey)
            || toy_module_requirement_errors($pdo, $moduleKey, $metadata, (string) ($module['status'] ?? 'enabled')) !== []
        ) {
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
