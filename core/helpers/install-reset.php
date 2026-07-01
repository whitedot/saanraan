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

function sr_install_reset_storage_preview(PDO $pdo, array $targetTables, array $config = [], array $options = []): array
{
    $maxReferences = max(1, min(50000, (int) ($options['max_references_per_column'] ?? 5000)));
    $requestedTablePrefix = (string) ($options['table_prefix'] ?? 'sr_');
    $tablePrefix = sr_is_safe_table_prefix($requestedTablePrefix) ? $requestedTablePrefix : 'sr_';
    $defaultDriver = 'local';
    if (function_exists('sr_storage_default_driver')) {
        $defaultDriver = sr_storage_default_driver($config);
    }

    $columns = [];
    $referenceCount = 0;
    $safeReferenceCount = 0;
    $unsafeReferenceCount = 0;
    $localReferenceCount = 0;
    $remoteReferenceCount = 0;
    $localExistingFileCount = 0;
    $localExistingBytes = 0;
    $truncated = false;

    foreach ($targetTables as $tableName) {
        $tableName = (string) $tableName;
        if (!sr_install_reset_table_name_is_safe($tableName, $tablePrefix)) {
            continue;
        }

        $tableColumns = sr_install_reset_table_columns($pdo, $tableName);
        foreach (sr_install_reset_storage_key_columns($tableColumns) as $keyColumn) {
            $driverColumn = sr_install_reset_matching_storage_driver_column($tableColumns, $keyColumn);
            $columnPreview = sr_install_reset_storage_column_preview(
                $pdo,
                $tableName,
                $keyColumn,
                $driverColumn,
                $defaultDriver,
                $maxReferences
            );

            $columns[] = $columnPreview;
            $referenceCount += (int) $columnPreview['reference_count'];
            $safeReferenceCount += (int) $columnPreview['safe_reference_count'];
            $unsafeReferenceCount += (int) $columnPreview['unsafe_reference_count'];
            $localReferenceCount += (int) $columnPreview['local_reference_count'];
            $remoteReferenceCount += (int) $columnPreview['remote_reference_count'];
            $localExistingFileCount += (int) $columnPreview['local_existing_file_count'];
            $localExistingBytes += (int) $columnPreview['local_existing_bytes'];
            $truncated = $truncated || !empty($columnPreview['truncated']);
        }
    }

    return [
        'max_references_per_column' => $maxReferences,
        'reference_column_count' => count($columns),
        'reference_count' => $referenceCount,
        'safe_reference_count' => $safeReferenceCount,
        'unsafe_reference_count' => $unsafeReferenceCount,
        'local_reference_count' => $localReferenceCount,
        'remote_reference_count' => $remoteReferenceCount,
        'local_existing_file_count' => $localExistingFileCount,
        'local_existing_bytes' => $localExistingBytes,
        'truncated' => $truncated,
        'columns' => $columns,
    ];
}

function sr_install_reset_table_columns(PDO $pdo, string $tableName): array
{
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $statement = $pdo->query('PRAGMA table_info(' . sr_install_reset_quote_identifier($tableName, $driver) . ')');
        if (!$statement instanceof PDOStatement) {
            return [];
        }

        $columns = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = strtolower((string) ($row['name'] ?? ''));
            if (preg_match('/\A[a-z][a-z0-9_]*\z/', $name) === 1) {
                $columns[] = $name;
            }
        }
        sort($columns, SORT_STRING);
        return array_values(array_unique($columns));
    }

    $statement = $pdo->prepare(
        'SELECT COLUMN_NAME
           FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $tableName]);
    $columns = [];
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $columnName) {
        $name = strtolower((string) $columnName);
        if (preg_match('/\A[a-z][a-z0-9_]*\z/', $name) === 1) {
            $columns[] = $name;
        }
    }
    sort($columns, SORT_STRING);
    return array_values(array_unique($columns));
}

function sr_install_reset_storage_key_columns(array $columns): array
{
    $keyColumns = [];
    foreach ($columns as $column) {
        $column = strtolower((string) $column);
        if ($column === 'storage_key' || str_ends_with($column, '_storage_key')) {
            $keyColumns[] = $column;
        }
    }

    sort($keyColumns, SORT_STRING);
    return array_values(array_unique($keyColumns));
}

function sr_install_reset_matching_storage_driver_column(array $columns, string $keyColumn): string
{
    $columnSet = array_fill_keys(array_map('strval', $columns), true);
    if ($keyColumn === 'storage_key' && isset($columnSet['storage_driver'])) {
        return 'storage_driver';
    }

    if (str_ends_with($keyColumn, '_storage_key')) {
        $candidate = substr($keyColumn, 0, -strlen('_storage_key')) . '_storage_driver';
        if (isset($columnSet[$candidate])) {
            return $candidate;
        }
    }

    return isset($columnSet['storage_driver']) ? 'storage_driver' : '';
}

function sr_install_reset_storage_column_preview(
    PDO $pdo,
    string $tableName,
    string $keyColumn,
    string $driverColumn,
    string $defaultDriver,
    int $maxReferences
): array {
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $quotedTable = sr_install_reset_quote_identifier($tableName, $driver);
    $quotedKeyColumn = sr_install_reset_quote_identifier($keyColumn, $driver);
    $where = $quotedKeyColumn . " IS NOT NULL AND " . $quotedKeyColumn . " <> ''";
    $statement = $pdo->query('SELECT COUNT(*) FROM ' . $quotedTable . ' WHERE ' . $where);
    $referenceCount = $statement instanceof PDOStatement ? (int) $statement->fetchColumn() : 0;

    $select = $quotedKeyColumn . ' AS storage_key';
    if ($driverColumn !== '') {
        $select .= ', ' . sr_install_reset_quote_identifier($driverColumn, $driver) . ' AS storage_driver';
    }

    $rows = [];
    $statement = $pdo->query('SELECT ' . $select . ' FROM ' . $quotedTable . ' WHERE ' . $where . ' LIMIT ' . (string) $maxReferences);
    if ($statement instanceof PDOStatement) {
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    $safeReferenceCount = 0;
    $unsafeReferenceCount = 0;
    $localReferenceCount = 0;
    $remoteReferenceCount = 0;
    $localExistingFileCount = 0;
    $localExistingBytes = 0;

    foreach ($rows as $row) {
        $key = (string) ($row['storage_key'] ?? '');
        $referenceDriver = strtolower(trim((string) ($row['storage_driver'] ?? $defaultDriver)));
        if (!in_array($referenceDriver, ['local', 's3'], true)) {
            $referenceDriver = $defaultDriver;
        }

        if (!function_exists('sr_storage_key_is_safe') || !sr_storage_key_is_safe($key)) {
            $unsafeReferenceCount++;
            continue;
        }

        $safeReferenceCount++;
        if ($referenceDriver === 's3') {
            $remoteReferenceCount++;
            continue;
        }

        $localReferenceCount++;
        $path = function_exists('sr_storage_local_path') ? sr_storage_local_path($key) : null;
        if (is_string($path) && is_file($path)) {
            $localExistingFileCount++;
            $localExistingBytes += (int) filesize($path);
        }
    }

    return [
        'table' => $tableName,
        'key_column' => $keyColumn,
        'driver_column' => $driverColumn,
        'reference_count' => $referenceCount,
        'sampled_reference_count' => count($rows),
        'safe_reference_count' => $safeReferenceCount,
        'unsafe_reference_count' => $unsafeReferenceCount,
        'local_reference_count' => $localReferenceCount,
        'remote_reference_count' => $remoteReferenceCount,
        'local_existing_file_count' => $localExistingFileCount,
        'local_existing_bytes' => $localExistingBytes,
        'truncated' => $referenceCount > count($rows),
    ];
}

function sr_install_reset_environment_warnings(array $config): array
{
    $warnings = [];
    $env = strtolower(trim((string) ($config['env'] ?? 'production')));
    if ($env === '' || $env === 'production' || $env === 'prod') {
        $warnings[] = 'config env is production-looking.';
    }

    $site = isset($config['site']) && is_array($config['site']) ? $config['site'] : [];
    $baseUrl = trim((string) ($site['base_url'] ?? ''));
    if ($baseUrl !== '' && !sr_install_reset_url_is_local_or_staging($baseUrl)) {
        $warnings[] = 'site base_url is not local/staging-looking.';
    }

    $db = isset($config['db']) && is_array($config['db']) ? $config['db'] : [];
    $dbName = strtolower((string) ($db['name'] ?? ''));
    if (preg_match('/(?:prod|production|live)/', $dbName) === 1) {
        $warnings[] = 'db name is production-looking.';
    }

    $storage = isset($config['storage']) && is_array($config['storage']) ? $config['storage'] : [];
    $s3 = isset($storage['s3']) && is_array($storage['s3']) ? $storage['s3'] : [];
    $bucket = strtolower((string) ($s3['bucket'] ?? ''));
    if ($bucket !== '' && preg_match('/(?:prod|production|live)/', $bucket) === 1) {
        $warnings[] = 'storage bucket is production-looking.';
    }

    return array_values(array_unique($warnings));
}

function sr_install_reset_url_is_local_or_staging(string $url): bool
{
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return false;
    }

    $host = strtolower($host);
    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }

    return str_contains($host, '.test')
        || str_contains($host, '.local')
        || str_contains($host, 'staging')
        || str_contains($host, 'stage')
        || str_contains($host, 'dev');
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
