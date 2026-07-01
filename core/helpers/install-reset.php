<?php

declare(strict_types=1);

function sr_install_reset_sql_source_paths(string $root): array
{
    $paths = [];
    $candidates = [
        rtrim($root, '/\\') . '/database/core/install.sql',
    ];

    foreach (glob(rtrim($root, '/\\') . '/database/core/updates/*.sql') ?: [] as $path) {
        $candidates[] = $path;
    }
    foreach (glob(rtrim($root, '/\\') . '/modules/*/install.sql') ?: [] as $path) {
        $candidates[] = $path;
    }
    foreach (glob(rtrim($root, '/\\') . '/modules/*/updates/*.sql') ?: [] as $path) {
        $candidates[] = $path;
    }

    foreach ($candidates as $path) {
        if (is_file($path)) {
            $paths[] = $path;
        }
    }

    sort($paths, SORT_STRING);
    return array_values(array_unique($paths));
}

function sr_install_reset_table_allowlist(string $root, string $tablePrefix = 'sr_'): array
{
    $tablePrefix = sr_is_safe_table_prefix($tablePrefix) ? $tablePrefix : 'sr_';
    $tables = [];
    foreach (sr_install_reset_sql_source_paths($root) as $path) {
        $sql = file_get_contents($path);
        if (!is_string($sql)) {
            continue;
        }

        foreach (sr_install_reset_table_names_from_sql($sql, $tablePrefix) as $tableName) {
            $tables[$tableName] = true;
        }
    }

    $tableNames = array_keys($tables);
    sort($tableNames, SORT_STRING);
    return $tableNames;
}

function sr_install_reset_table_names_from_sql(string $sql, string $tablePrefix = 'sr_'): array
{
    $tablePrefix = sr_is_safe_table_prefix($tablePrefix) ? $tablePrefix : 'sr_';
    $tables = [];
    $patterns = [
        '/\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?sr_([a-z0-9_]+)`?/i',
        '/\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?\{\{SR_TABLE_PREFIX\}\}([a-z0-9_]+)`?/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $sql, $matches) !== false) {
            foreach ($matches[1] as $suffix) {
                $suffix = strtolower((string) $suffix);
                if (preg_match('/\A[a-z0-9_]+\z/', $suffix) === 1) {
                    $tables[$tablePrefix . $suffix] = true;
                }
            }
        }
    }

    $tableNames = array_keys($tables);
    sort($tableNames, SORT_STRING);
    return $tableNames;
}

function sr_install_reset_existing_prefixed_tables(PDO $pdo, string $tablePrefix = 'sr_'): array
{
    $tablePrefix = sr_is_safe_table_prefix($tablePrefix) ? $tablePrefix : 'sr_';
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $statement = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'");
    } else {
        $statement = $pdo->query('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()');
    }

    if (!$statement instanceof PDOStatement) {
        return [];
    }

    $tableNames = [];
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $tableName) {
        $tableName = (string) $tableName;
        if (sr_install_reset_table_name_is_safe($tableName, $tablePrefix)) {
            $tableNames[$tableName] = true;
        }
    }

    $names = array_keys($tableNames);
    sort($names, SORT_STRING);
    return $names;
}

function sr_install_reset_table_preview(PDO $pdo, array $allowlist, string $tablePrefix = 'sr_'): array
{
    $tablePrefix = sr_is_safe_table_prefix($tablePrefix) ? $tablePrefix : 'sr_';
    $allowlisted = [];
    foreach ($allowlist as $tableName) {
        $tableName = (string) $tableName;
        if (sr_install_reset_table_name_is_safe($tableName, $tablePrefix)) {
            $allowlisted[$tableName] = true;
        }
    }

    $existingTables = sr_install_reset_existing_prefixed_tables($pdo, $tablePrefix);
    $tables = [];
    $totalRows = 0;
    foreach ($existingTables as $tableName) {
        if (!isset($allowlisted[$tableName])) {
            continue;
        }

        $rowCount = sr_install_reset_table_row_count($pdo, $tableName);
        if (is_int($rowCount)) {
            $totalRows += $rowCount;
        }
        $tables[] = [
            'name' => $tableName,
            'rows' => $rowCount,
        ];
    }

    $ignored = array_values(array_diff($existingTables, array_keys($allowlisted)));
    sort($ignored, SORT_STRING);

    return [
        'table_prefix' => $tablePrefix,
        'allowlist_count' => count($allowlisted),
        'existing_prefixed_count' => count($existingTables),
        'target_table_count' => count($tables),
        'target_row_count' => $totalRows,
        'tables' => $tables,
        'ignored_prefixed_tables' => $ignored,
    ];
}

function sr_install_reset_table_row_count(PDO $pdo, string $tableName): ?int
{
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $quotedTable = sr_install_reset_quote_identifier($tableName, $driver);
    try {
        $statement = $pdo->query('SELECT COUNT(*) FROM ' . $quotedTable);
        if (!$statement instanceof PDOStatement) {
            return null;
        }

        $value = $statement->fetchColumn();
        return is_numeric($value) ? (int) $value : null;
    } catch (Throwable $exception) {
        return null;
    }
}

function sr_install_reset_table_name_is_safe(string $tableName, string $tablePrefix = 'sr_'): bool
{
    $tablePrefix = sr_is_safe_table_prefix($tablePrefix) ? $tablePrefix : 'sr_';
    return preg_match('/\A' . preg_quote($tablePrefix, '/') . '[a-z0-9_]+\z/', $tableName) === 1;
}

function sr_install_reset_quote_identifier(string $identifier, string $driver): string
{
    if (preg_match('/\A[a-z][a-z0-9_]*\z/', $identifier) !== 1) {
        throw new InvalidArgumentException('SQL identifier is invalid.');
    }

    if ($driver === 'sqlite') {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    return '`' . str_replace('`', '``', $identifier) . '`';
}
