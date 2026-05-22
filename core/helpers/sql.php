<?php

declare(strict_types=1);

function sr_execute_sql_file(PDO $pdo, string $file): void
{
    if (!is_file($file)) {
        throw new RuntimeException('SQL file does not exist: ' . $file);
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('SQL file cannot be read: ' . $file);
    }

    $sql = sr_sql_replace_runtime_placeholders($pdo, $sql);

    foreach (sr_split_sql_statements($sql) as $statement) {
        $pdo->exec($statement);
    }
}

function sr_sql_replace_runtime_placeholders(PDO $pdo, string $sql): string
{
    $tablePrefix = $pdo instanceof SrPrefixedPDO ? $pdo->srTablePrefix() : 'sr_';
    if (!sr_is_safe_table_prefix($tablePrefix)) {
        $tablePrefix = 'sr_';
    }

    return str_replace('{{SR_TABLE_PREFIX}}', $tablePrefix, $sql);
}

function sr_split_sql_statements(string $sql): array
{
    $statements = [];
    $statement = '';
    $quote = '';
    $lineComment = false;
    $blockComment = false;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($lineComment) {
            $statement .= $char;
            if ($char === "\n") {
                $lineComment = false;
            }
            continue;
        }

        if ($blockComment) {
            $statement .= $char;
            if ($char === '*' && $next === '/') {
                $statement .= $next;
                $i++;
                $blockComment = false;
            }
            continue;
        }

        if ($quote !== '') {
            $statement .= $char;
            if ($char === '\\' && $quote !== '`' && $next !== '') {
                $statement .= $next;
                $i++;
                continue;
            }

            if ($char === $quote) {
                if ($next === $quote) {
                    $statement .= $next;
                    $i++;
                    continue;
                }
                $quote = '';
            }
            continue;
        }

        if ($char === '\'' || $char === '"' || $char === '`') {
            $quote = $char;
            $statement .= $char;
            continue;
        }

        if ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) {
            $statement .= $char . $next;
            $i++;
            $lineComment = true;
            continue;
        }

        if ($char === '#') {
            $statement .= $char;
            $lineComment = true;
            continue;
        }

        if ($char === '/' && $next === '*') {
            $statement .= $char . $next;
            $i++;
            $blockComment = true;
            continue;
        }

        if ($char === ';') {
            $trimmed = trim($statement);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $statement = '';
            continue;
        }

        $statement .= $char;
    }

    $trimmed = trim($statement);
    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }

    return $statements;
}

function sr_record_schema_version(PDO $pdo, string $scope, string $moduleKey, string $version): void
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_schema_versions (scope, module_key, version, applied_at)
         VALUES (:scope, :module_key, :version, :applied_at)'
    );
    $stmt->execute([
        'scope' => $scope,
        'module_key' => $moduleKey,
        'version' => $version,
        'applied_at' => sr_now(),
    ]);
}

function sr_schema_update_versions(string $directory): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $paths = glob($directory . '/*.sql');
    if ($paths === false) {
        return [];
    }

    $versions = [];
    foreach ($paths as $path) {
        $version = basename($path, '.sql');
        if (preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $version) === 1) {
            $versions[] = $version;
        }
    }

    sort($versions, SORT_STRING);
    return $versions;
}

function sr_record_installed_module_schema_versions(PDO $pdo, string $moduleKey, string $currentVersion): void
{
    if (!sr_is_safe_module_key($moduleKey)) {
        throw new InvalidArgumentException('Module key is invalid.');
    }

    sr_record_installed_schema_versions(
        $pdo,
        'module',
        $moduleKey,
        $currentVersion,
        SR_ROOT . '/modules/' . $moduleKey . '/updates'
    );
}

function sr_record_installed_core_schema_versions(PDO $pdo, string $currentVersion): void
{
    sr_record_installed_schema_versions($pdo, 'core', '', $currentVersion, SR_ROOT . '/database/core/updates');
}

function sr_record_installed_schema_versions(PDO $pdo, string $scope, string $moduleKey, string $currentVersion, string $updatesDirectory): void
{
    if (preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $currentVersion) !== 1) {
        sr_record_schema_version($pdo, $scope, $moduleKey, $currentVersion);
        return;
    }

    $baseVersion = substr($currentVersion, 0, 8) . '001';
    $versions = [$currentVersion => true];
    if (strcmp($baseVersion, $currentVersion) <= 0) {
        $versions[$baseVersion] = true;
    }

    foreach (sr_schema_update_versions($updatesDirectory) as $version) {
        if (strcmp($version, $currentVersion) <= 0) {
            $versions[$version] = true;
        }
    }

    ksort($versions, SORT_STRING);
    foreach (array_keys($versions) as $version) {
        sr_record_schema_version($pdo, $scope, $moduleKey, (string) $version);
    }
}
