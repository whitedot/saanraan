#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once SR_ROOT . '/core/helpers/runtime.php';
require_once SR_ROOT . '/core/helpers/sql.php';
require_once SR_ROOT . '/core/helpers/install-reset.php';

$args = array_slice($argv, 1);
$runLiveInstall = false;
$livePrefix = 'check_';
$keepLiveTables = false;
foreach ($args as $arg) {
    if ($arg === '--live-install') {
        $runLiveInstall = true;
        continue;
    }
    if ($arg === '--keep-live-tables') {
        $keepLiveTables = true;
        continue;
    }
    if (str_starts_with($arg, '--prefix=')) {
        $livePrefix = substr($arg, strlen('--prefix='));
        continue;
    }
    if ($arg === '--help' || $arg === '-h') {
        echo "Usage: php .tools/bin/check-install-update-schema-parity.php [--live-install] [--prefix=check_] [--keep-live-tables]\n";
        exit(0);
    }

    fwrite(STDERR, "Unknown option: " . $arg . "\n");
    exit(2);
}

if (!sr_is_safe_table_prefix($livePrefix)) {
    fwrite(STDERR, "Live install table prefix is unsafe: " . $livePrefix . "\n");
    exit(2);
}

$installSchema = sr_schema_parity_parse_install_schema(SR_ROOT);
$updateTargets = sr_schema_parity_parse_update_targets(SR_ROOT);
$findings = sr_schema_parity_find_static_findings($installSchema, $updateTargets);

if ($findings !== []) {
    fwrite(STDERR, "install/update schema parity check failed:\n");
    foreach ($findings as $finding) {
        fwrite(STDERR, "- " . $finding . "\n");
    }
    exit(1);
}

echo "install/update schema parity check passed.\n";
echo "- install tables: " . count($installSchema['tables']) . "\n";
echo "- install columns: " . count($installSchema['columns']) . "\n";
echo "- install indexes: " . count($installSchema['indexes']) . "\n";
echo "- update DDL targets: " . ((int) $updateTargets['target_count']) . "\n";

if ($runLiveInstall) {
    $liveResult = sr_schema_parity_run_live_install_check(SR_ROOT, $installSchema, $livePrefix, $keepLiveTables);
    echo "- live install prefix: " . $livePrefix . "\n";
    echo "- live install tables created: " . (string) $liveResult['created_table_count'] . "\n";
    echo "- live install cleanup: " . ($liveResult['cleaned_up'] ? 'yes' : 'no') . "\n";
}

function sr_schema_parity_sql_paths(string $root, string $kind): array
{
    $paths = [];
    if ($kind === 'install') {
        $candidates = array_merge(
            [rtrim($root, '/\\') . '/database/core/install.sql'],
            glob(rtrim($root, '/\\') . '/modules/*/install.sql') ?: []
        );
    } else {
        $candidates = array_merge(
            glob(rtrim($root, '/\\') . '/database/core/updates/*.sql') ?: [],
            glob(rtrim($root, '/\\') . '/modules/*/updates/*.sql') ?: []
        );
    }

    foreach ($candidates as $path) {
        if (is_file($path)) {
            $paths[] = $path;
        }
    }

    sort($paths, SORT_STRING);
    return $paths;
}

function sr_schema_parity_parse_install_schema(string $root): array
{
    $schema = [
        'tables' => [],
        'columns' => [],
        'indexes' => [],
        'column_definitions' => [],
    ];

    foreach (sr_schema_parity_sql_paths($root, 'install') as $path) {
        sr_schema_parity_parse_sql_into_schema((string) file_get_contents($path), $path, $schema);
    }

    ksort($schema['tables'], SORT_STRING);
    ksort($schema['columns'], SORT_STRING);
    ksort($schema['indexes'], SORT_STRING);
    ksort($schema['column_definitions'], SORT_STRING);
    return $schema;
}

function sr_schema_parity_parse_update_targets(string $root): array
{
    $targets = [
        'tables' => [],
        'columns' => [],
        'indexes' => [],
        'column_definitions' => [],
        'target_count' => 0,
    ];
    $variables = [];
    $sequence = 0;

    foreach (sr_schema_parity_sql_paths($root, 'updates') as $path) {
        $sql = (string) file_get_contents($path);
        foreach (sr_split_sql_statements($sql) as $statement) {
            sr_schema_parity_capture_variable_assignment($statement, $variables);
            foreach (sr_schema_parity_statement_candidates($statement, $variables) as $candidate) {
                $before = $targets['target_count'];
                sr_schema_parity_parse_sql_into_schema($candidate, $path, $targets, true, $sequence);
                if ($targets['target_count'] > $before) {
                    $sequence++;
                }
            }
        }
    }

    return $targets;
}

function sr_schema_parity_capture_variable_assignment(string $statement, array &$variables): void
{
    if (preg_match('/\ASET\s+(@[a-z0-9_]+)\s*=/i', trim($statement), $matches) !== 1) {
        return;
    }

    $literals = sr_schema_parity_string_literals($statement);
    if (count($literals) !== 1) {
        return;
    }

    $variables[strtolower((string) $matches[1])] = sr_schema_parity_normalize_sql((string) $literals[0]);
}

function sr_schema_parity_statement_candidates(string $statement, array $variables): array
{
    $candidates = [$statement];

    foreach (sr_schema_parity_string_literals($statement) as $literal) {
        if (preg_match('/\b(?:CREATE|ALTER|DROP)\s+TABLE\b/i', $literal) === 1) {
            $candidates[] = $literal;
        }
    }

    foreach (sr_schema_parity_concat_values($statement, $variables) as $concatValue) {
        if (preg_match('/\b(?:CREATE|ALTER|DROP)\s+TABLE\b/i', $concatValue) === 1) {
            $candidates[] = $concatValue;
        }
    }

    return array_values(array_unique($candidates));
}

function sr_schema_parity_concat_values(string $statement, array $variables): array
{
    $values = [];
    $offset = 0;
    while (preg_match('/\bCONCAT\s*\(/i', $statement, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
        $open = (int) $matches[0][1] + strlen((string) $matches[0][0]) - 1;
        $close = sr_schema_parity_matching_paren($statement, $open);
        if ($close < 0) {
            break;
        }

        $body = substr($statement, $open + 1, $close - $open - 1);
        $parts = [];
        $known = true;
        foreach (sr_schema_parity_split_top_level_commas($body) as $arg) {
            $arg = trim($arg);
            if ($arg === '') {
                continue;
            }
            if (preg_match('/\A@[a-z0-9_]+\z/i', $arg) === 1) {
                $key = strtolower($arg);
                if (!isset($variables[$key])) {
                    $known = false;
                    break;
                }
                $parts[] = (string) $variables[$key];
                continue;
            }

            $literals = sr_schema_parity_string_literals($arg);
            if (count($literals) === 1 && trim($arg) === sr_schema_parity_quote_literal_for_compare((string) $literals[0], $arg)) {
                $parts[] = (string) $literals[0];
                continue;
            }

            $known = false;
            break;
        }

        if ($known && $parts !== []) {
            $values[] = implode('', $parts);
        }
        $offset = $close + 1;
    }

    return $values;
}

function sr_schema_parity_quote_literal_for_compare(string $literal, string $original): string
{
    $trimmed = trim($original);
    if ($trimmed === '') {
        return '';
    }

    $quote = $trimmed[0];
    if ($quote !== "'" && $quote !== '"') {
        return '';
    }

    return $trimmed;
}

function sr_schema_parity_parse_sql_into_schema(
    string $sql,
    string $source,
    array &$schema,
    bool $isUpdate = false,
    int $sequence = 0
): void {
    $normalizedSql = sr_schema_parity_normalize_sql($sql);
    sr_schema_parity_parse_drop_tables($normalizedSql, $source, $schema, $isUpdate, $sequence);
    sr_schema_parity_parse_create_tables($normalizedSql, $source, $schema, $isUpdate, $sequence);
    sr_schema_parity_parse_alter_tables($normalizedSql, $source, $schema, $isUpdate, $sequence);
}

function sr_schema_parity_parse_drop_tables(string $sql, string $source, array &$schema, bool $isUpdate, int $sequence): void
{
    if (preg_match_all('/\bDROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?([^;]+)/i', $sql, $matches) === false) {
        return;
    }

    foreach ($matches[1] as $tableList) {
        foreach (explode(',', (string) $tableList) as $tableToken) {
            $table = sr_schema_parity_table_name($tableToken);
            if ($table === '') {
                continue;
            }
            sr_schema_parity_record($schema, 'tables', $table, 'drop', $source, $isUpdate, $sequence);
            foreach (array_keys($schema['columns']) as $columnKey) {
                if (str_starts_with((string) $columnKey, $table . '.')) {
                    sr_schema_parity_record($schema, 'columns', (string) $columnKey, 'drop', $source, $isUpdate, $sequence);
                }
            }
            foreach (array_keys($schema['indexes']) as $indexKey) {
                if (str_starts_with((string) $indexKey, $table . '.')) {
                    sr_schema_parity_record($schema, 'indexes', (string) $indexKey, 'drop', $source, $isUpdate, $sequence);
                }
            }
        }
    }
}

function sr_schema_parity_parse_create_tables(string $sql, string $source, array &$schema, bool $isUpdate, int $sequence): void
{
    $offset = 0;
    while (preg_match('/\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(`?sr_[a-z0-9_]+`?)/i', $sql, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
        $table = sr_schema_parity_table_name((string) $matches[1][0]);
        $tableOffset = (int) $matches[1][1] + strlen((string) $matches[1][0]);
        $open = strpos($sql, '(', $tableOffset);
        if ($table === '' || $open === false) {
            $offset = $tableOffset;
            continue;
        }
        $close = sr_schema_parity_matching_paren($sql, (int) $open);
        if ($close < 0) {
            $offset = (int) $open + 1;
            continue;
        }

        sr_schema_parity_record($schema, 'tables', $table, 'present', $source, $isUpdate, $sequence);
        $body = substr($sql, (int) $open + 1, $close - (int) $open - 1);
        foreach (sr_schema_parity_split_top_level_commas($body) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $column = sr_schema_parity_column_definition($part);
            if ($column !== null) {
                $key = $table . '.' . $column['name'];
                sr_schema_parity_record($schema, 'columns', $key, 'present', $source, $isUpdate, $sequence);
                $schema['column_definitions'][$key] = [
                    'definition' => $column['definition'],
                    'source' => $source,
                    'sequence' => $sequence,
                ];
                continue;
            }

            $index = sr_schema_parity_index_name($part);
            if ($index !== '') {
                sr_schema_parity_record($schema, 'indexes', $table . '.' . $index, 'present', $source, $isUpdate, $sequence);
            }
        }
        $offset = $close + 1;
    }
}

function sr_schema_parity_parse_alter_tables(string $sql, string $source, array &$schema, bool $isUpdate, int $sequence): void
{
    $offset = 0;
    while (preg_match('/\bALTER\s+TABLE\s+(`?sr_[a-z0-9_]+`?)\s+/i', $sql, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
        $table = sr_schema_parity_table_name((string) $matches[1][0]);
        $start = (int) $matches[0][1] + strlen((string) $matches[0][0]);
        $end = sr_schema_parity_statement_like_end($sql, $start);
        $body = substr($sql, $start, $end - $start);
        foreach (sr_schema_parity_split_top_level_commas($body) as $operation) {
            sr_schema_parity_parse_alter_operation($table, $operation, $source, $schema, $isUpdate, $sequence);
        }
        $offset = max($end + 1, $start + 1);
    }
}

function sr_schema_parity_parse_alter_operation(
    string $table,
    string $operation,
    string $source,
    array &$schema,
    bool $isUpdate,
    int $sequence
): void {
    $operation = trim($operation);
    if ($table === '' || $operation === '') {
        return;
    }

    if (preg_match('/\AADD\s+(?:COLUMN\s+)?(`?[a-z0-9_]+`?)\s+(.+)\z/is', $operation, $matches) === 1) {
        if (preg_match('/\A(?:PRIMARY|UNIQUE|FULLTEXT|SPATIAL|KEY|INDEX)\b/i', (string) $matches[1]) === 1) {
            $index = sr_schema_parity_index_name($operation);
            if ($index !== '') {
                sr_schema_parity_record($schema, 'indexes', $table . '.' . $index, 'present', $source, $isUpdate, $sequence);
            }
            return;
        }

        $column = sr_schema_parity_identifier((string) $matches[1]);
        $key = $table . '.' . $column;
        sr_schema_parity_record($schema, 'columns', $key, 'present', $source, $isUpdate, $sequence);
        $schema['column_definitions'][$key] = [
            'definition' => sr_schema_parity_normalize_column_definition((string) $matches[2]),
            'source' => $source,
            'sequence' => $sequence,
        ];
        return;
    }

    $index = sr_schema_parity_index_name($operation);
    if ($index !== '' && preg_match('/\AADD\s+/i', $operation) === 1) {
        sr_schema_parity_record($schema, 'indexes', $table . '.' . $index, 'present', $source, $isUpdate, $sequence);
        return;
    }

    if (preg_match('/\ADROP\s+COLUMN\s+(`?[a-z0-9_]+`?)/i', $operation, $matches) === 1) {
        sr_schema_parity_record($schema, 'columns', $table . '.' . sr_schema_parity_identifier((string) $matches[1]), 'drop', $source, $isUpdate, $sequence);
        return;
    }

    if (preg_match('/\ADROP\s+(?:INDEX|KEY)\s+(`?[a-z0-9_]+`?)/i', $operation, $matches) === 1) {
        sr_schema_parity_record($schema, 'indexes', $table . '.' . sr_schema_parity_identifier((string) $matches[1]), 'drop', $source, $isUpdate, $sequence);
        return;
    }

    if (preg_match('/\ACHANGE\s+(?:COLUMN\s+)?(`?[a-z0-9_]+`?)\s+(`?[a-z0-9_]+`?)\s+(.+)\z/is', $operation, $matches) === 1) {
        $oldColumn = sr_schema_parity_identifier((string) $matches[1]);
        $newColumn = sr_schema_parity_identifier((string) $matches[2]);
        if ($oldColumn !== $newColumn) {
            sr_schema_parity_record($schema, 'columns', $table . '.' . $oldColumn, 'drop', $source, $isUpdate, $sequence);
        }
        $key = $table . '.' . $newColumn;
        sr_schema_parity_record($schema, 'columns', $key, 'present', $source, $isUpdate, $sequence);
        $schema['column_definitions'][$key] = [
            'definition' => sr_schema_parity_normalize_column_definition((string) $matches[3]),
            'source' => $source,
            'sequence' => $sequence,
        ];
        return;
    }

    if (preg_match('/\AMODIFY\s+(?:COLUMN\s+)?(`?[a-z0-9_]+`?)\s+(.+)\z/is', $operation, $matches) === 1) {
        $key = $table . '.' . sr_schema_parity_identifier((string) $matches[1]);
        sr_schema_parity_record($schema, 'columns', $key, 'present', $source, $isUpdate, $sequence);
        $schema['column_definitions'][$key] = [
            'definition' => sr_schema_parity_normalize_column_definition((string) $matches[2]),
            'source' => $source,
            'sequence' => $sequence,
        ];
    }
}

function sr_schema_parity_record(array &$schema, string $bucket, string $key, string $state, string $source, bool $isUpdate, int $sequence): void
{
    if ($key === '') {
        return;
    }

    $schema[$bucket][$key] = [
        'state' => $state,
        'source' => sr_schema_parity_relative_path($source),
        'sequence' => $sequence,
    ];
    if ($isUpdate) {
        $schema['target_count']++;
    }
}

function sr_schema_parity_find_static_findings(array $installSchema, array $updateTargets): array
{
    $findings = [];

    foreach ($updateTargets['tables'] as $table => $target) {
        if (($target['state'] ?? '') === 'present' && !isset($installSchema['tables'][$table])) {
            $findings[] = 'update-created table is missing from install.sql: ' . $table . ' (' . (string) $target['source'] . ')';
        }
        if (($target['state'] ?? '') === 'drop' && isset($installSchema['tables'][$table])) {
            $findings[] = 'update-dropped table still exists in install.sql: ' . $table . ' (' . (string) $target['source'] . ')';
        }
    }

    foreach ($updateTargets['columns'] as $columnKey => $target) {
        if (($target['state'] ?? '') === 'present' && !isset($installSchema['columns'][$columnKey])) {
            $findings[] = 'update-added column is missing from install.sql: ' . $columnKey . ' (' . (string) $target['source'] . ')';
            continue;
        }
        if (($target['state'] ?? '') === 'drop' && isset($installSchema['columns'][$columnKey])) {
            $findings[] = 'update-dropped column still exists in install.sql: ' . $columnKey . ' (' . (string) $target['source'] . ')';
        }
    }

    foreach ($updateTargets['indexes'] as $indexKey => $target) {
        if (($target['state'] ?? '') === 'present' && !isset($installSchema['indexes'][$indexKey])) {
            $findings[] = 'update-added index is missing from install.sql: ' . $indexKey . ' (' . (string) $target['source'] . ')';
        }
        if (($target['state'] ?? '') === 'drop' && isset($installSchema['indexes'][$indexKey])) {
            $findings[] = 'update-dropped index still exists in install.sql: ' . $indexKey . ' (' . (string) $target['source'] . ')';
        }
    }

    foreach ($updateTargets['column_definitions'] as $columnKey => $targetDefinition) {
        if (!isset($installSchema['column_definitions'][$columnKey], $updateTargets['columns'][$columnKey])) {
            continue;
        }
        if (($updateTargets['columns'][$columnKey]['state'] ?? '') !== 'present') {
            continue;
        }
        $installDefinition = (string) ($installSchema['column_definitions'][$columnKey]['definition'] ?? '');
        $updateDefinition = (string) ($targetDefinition['definition'] ?? '');
        if ($installDefinition !== '' && $updateDefinition !== '' && $installDefinition !== $updateDefinition) {
            $findings[] = 'update column definition differs from install.sql: ' . $columnKey . ' (' . (string) ($targetDefinition['source'] ?? '') . ')';
        }
    }

    sort($findings, SORT_STRING);
    return $findings;
}

function sr_schema_parity_run_live_install_check(string $root, array $installSchema, string $prefix, bool $keepTables): array
{
    $configPath = $root . '/config/config.php';
    if (!is_file($configPath) || !is_readable($configPath)) {
        throw new RuntimeException('config/config.php is required for --live-install.');
    }

    $config = sr_load_config();
    $config['db']['table_prefix'] = $prefix;
    $pdo = sr_db($config);
    $allowlist = sr_install_reset_table_allowlist($root, $prefix);
    sr_schema_parity_drop_tables($pdo, $allowlist, $prefix);

    try {
        sr_execute_sql_file($pdo, $root . '/database/core/install.sql');
        foreach (glob($root . '/modules/*/install.sql') ?: [] as $path) {
            sr_execute_sql_file($pdo, $path);
        }

        $liveFindings = sr_schema_parity_compare_live_schema($pdo, $installSchema, $prefix);
        if ($liveFindings !== []) {
            throw new RuntimeException("Live install schema differs:\n- " . implode("\n- ", $liveFindings));
        }

        $created = sr_install_reset_existing_prefixed_tables($pdo, $prefix);
    } finally {
        if (!$keepTables) {
            sr_schema_parity_drop_tables($pdo, $allowlist, $prefix);
        }
    }

    return [
        'created_table_count' => count($created ?? []),
        'cleaned_up' => !$keepTables,
    ];
}

function sr_schema_parity_drop_tables(PDO $pdo, array $tables, string $prefix): void
{
    $result = sr_install_reset_drop_table_batch($pdo, $tables, $prefix, 500);
    if ((int) ($result['failed_table_count'] ?? 0) > 0 || (int) ($result['remaining_table_count'] ?? 0) > 0) {
        throw new RuntimeException('Could not clean live install check tables.');
    }
}

function sr_schema_parity_compare_live_schema(PDO $pdo, array $installSchema, string $prefix): array
{
    $findings = [];
    $liveTables = array_fill_keys(sr_install_reset_existing_prefixed_tables($pdo, $prefix), true);
    foreach (array_keys($installSchema['tables']) as $table) {
        $liveTable = $prefix . substr((string) $table, 3);
        if (!isset($liveTables[$liveTable])) {
            $findings[] = 'live install missing table: ' . $liveTable;
            continue;
        }

        $columns = array_fill_keys(sr_install_reset_table_columns($pdo, $liveTable), true);
        foreach (array_keys($installSchema['columns']) as $columnKey) {
            if (!str_starts_with((string) $columnKey, (string) $table . '.')) {
                continue;
            }
            $column = substr((string) $columnKey, strlen((string) $table) + 1);
            if (!isset($columns[$column])) {
                $findings[] = 'live install missing column: ' . $liveTable . '.' . $column;
            }
        }
    }

    sort($findings, SORT_STRING);
    return $findings;
}

function sr_schema_parity_normalize_sql(string $sql): string
{
    return str_replace('{{SR_TABLE_PREFIX}}', 'sr_', $sql);
}

function sr_schema_parity_table_name(string $token): string
{
    $token = sr_schema_parity_identifier($token);
    return preg_match('/\Asr_[a-z0-9_]+\z/', $token) === 1 ? $token : '';
}

function sr_schema_parity_identifier(string $token): string
{
    $token = trim($token);
    $token = trim($token, "` \t\n\r\0\x0B");
    return strtolower($token);
}

function sr_schema_parity_column_definition(string $part): ?array
{
    if (preg_match('/\A(?:PRIMARY|UNIQUE|FULLTEXT|SPATIAL|KEY|INDEX|CONSTRAINT|FOREIGN|CHECK)\b/i', $part) === 1) {
        return null;
    }
    if (preg_match('/\A(`?[a-z0-9_]+`?)\s+(.+)\z/is', $part, $matches) !== 1) {
        return null;
    }

    return [
        'name' => sr_schema_parity_identifier((string) $matches[1]),
        'definition' => sr_schema_parity_normalize_column_definition((string) $matches[2]),
    ];
}

function sr_schema_parity_normalize_column_definition(string $definition): string
{
    $definition = trim($definition);
    $definition = preg_replace('/,\s*\z/', '', $definition) ?? $definition;
    $definition = preg_replace('/\s+AFTER\s+`?[a-z0-9_]+`?\s*\z/i', '', $definition) ?? $definition;
    $definition = preg_replace('/\s+FIRST\s*\z/i', '', $definition) ?? $definition;
    $definition = str_replace('`', '', $definition);
    $definition = preg_replace('/\s+/', ' ', $definition) ?? $definition;
    return strtolower(trim($definition));
}

function sr_schema_parity_index_name(string $part): string
{
    $part = trim($part);
    if (preg_match('/\APRIMARY\s+KEY\b/i', $part) === 1) {
        return 'primary';
    }
    if (preg_match('/\A(?:ADD\s+)?(?:UNIQUE\s+|FULLTEXT\s+|SPATIAL\s+)?(?:KEY|INDEX)\s+(`?[a-z0-9_]+`?)/i', $part, $matches) === 1) {
        return sr_schema_parity_identifier((string) $matches[1]);
    }

    return '';
}

function sr_schema_parity_statement_like_end(string $sql, int $start): int
{
    $length = strlen($sql);
    $quote = '';
    $depth = 0;
    for ($i = $start; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';
        if ($quote !== '') {
            if ($char === '\\' && $quote !== '`' && $next !== '') {
                $i++;
                continue;
            }
            if ($char === $quote) {
                if ($next === $quote) {
                    $i++;
                    continue;
                }
                $quote = '';
            }
            continue;
        }
        if ($char === '\'' || $char === '"' || $char === '`') {
            $quote = $char;
            continue;
        }
        if ($char === '(') {
            $depth++;
            continue;
        }
        if ($char === ')') {
            if ($depth === 0) {
                return $i;
            }
            $depth--;
            continue;
        }
        if ($char === ';' && $depth === 0) {
            return $i;
        }
    }

    return $length;
}

function sr_schema_parity_matching_paren(string $sql, int $open): int
{
    $length = strlen($sql);
    $quote = '';
    $depth = 0;
    for ($i = $open; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';
        if ($quote !== '') {
            if ($char === '\\' && $quote !== '`' && $next !== '') {
                $i++;
                continue;
            }
            if ($char === $quote) {
                if ($next === $quote) {
                    $i++;
                    continue;
                }
                $quote = '';
            }
            continue;
        }
        if ($char === '\'' || $char === '"' || $char === '`') {
            $quote = $char;
            continue;
        }
        if ($char === '(') {
            $depth++;
            continue;
        }
        if ($char === ')') {
            $depth--;
            if ($depth === 0) {
                return $i;
            }
        }
    }

    return -1;
}

function sr_schema_parity_split_top_level_commas(string $text): array
{
    $parts = [];
    $part = '';
    $quote = '';
    $depth = 0;
    $length = strlen($text);

    for ($i = 0; $i < $length; $i++) {
        $char = $text[$i];
        $next = $i + 1 < $length ? $text[$i + 1] : '';
        if ($quote !== '') {
            $part .= $char;
            if ($char === '\\' && $quote !== '`' && $next !== '') {
                $i++;
                $part .= $text[$i];
                continue;
            }
            if ($char === $quote) {
                if ($next === $quote) {
                    $i++;
                    $part .= $text[$i];
                    continue;
                }
                $quote = '';
            }
            continue;
        }
        if ($char === '\'' || $char === '"' || $char === '`') {
            $quote = $char;
            $part .= $char;
            continue;
        }
        if ($char === '(') {
            $depth++;
            $part .= $char;
            continue;
        }
        if ($char === ')') {
            $depth = max(0, $depth - 1);
            $part .= $char;
            continue;
        }
        if ($char === ',' && $depth === 0) {
            $parts[] = $part;
            $part = '';
            continue;
        }
        $part .= $char;
    }

    if (trim($part) !== '') {
        $parts[] = $part;
    }

    return $parts;
}

function sr_schema_parity_string_literals(string $sql): array
{
    $literals = [];
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        if ($char !== "'" && $char !== '"') {
            continue;
        }

        $quote = $char;
        $literal = '';
        $i++;
        while ($i < $length) {
            $literalChar = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';
            if ($literalChar === '\\' && $next !== '') {
                $i++;
                $literal .= $sql[$i];
                $i++;
                continue;
            }
            if ($literalChar === $quote) {
                if ($next === $quote) {
                    $literal .= $quote;
                    $i += 2;
                    continue;
                }
                break;
            }
            $literal .= $literalChar;
            $i++;
        }
        $literals[] = $literal;
    }

    return $literals;
}

function sr_schema_parity_relative_path(string $path): string
{
    $prefix = rtrim(SR_ROOT, '/\\') . DIRECTORY_SEPARATOR;
    if (str_starts_with($path, $prefix)) {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($prefix)));
    }

    return str_replace(DIRECTORY_SEPARATOR, '/', $path);
}
