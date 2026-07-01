#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

function sr_schema_snapshot_files(string $rootDir, string $extension, array $skipDirs = []): array
{
    if (!is_dir($rootDir)) {
        return [];
    }

    $files = [];
    $directory = new RecursiveDirectoryIterator($rootDir, FilesystemIterator::SKIP_DOTS);
    $filter = new RecursiveCallbackFilterIterator(
        $directory,
        static function (SplFileInfo $current) use ($skipDirs): bool {
            if ($current->isDir()) {
                return !in_array($current->getFilename(), $skipDirs, true);
            }

            return true;
        }
    );

    foreach (new RecursiveIteratorIterator($filter) as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === $extension) {
            $files[] = str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname());
        }
    }

    sort($files);
    return $files;
}

function sr_schema_snapshot_php_files(bool $includeTools): array
{
    $files = [];
    $roots = ['core', 'modules'];
    if ($includeTools) {
        $roots[] = '.tools/bin';
    }
    foreach ($roots as $rootDir) {
        $files = array_merge($files, sr_schema_snapshot_files($rootDir, 'php', ['vendor']));
    }
    $files = array_values(array_filter(
        $files,
        static fn (string $file): bool => $file !== '.tools/bin/snapshot-schema-fallbacks.php'
    ));
    sort($files);

    return $files;
}

function sr_schema_snapshot_sql_files(): array
{
    $files = [];
    foreach (['database', 'modules'] as $rootDir) {
        $files = array_merge($files, sr_schema_snapshot_files($rootDir, 'sql', ['vendor']));
    }
    sort($files);

    return $files;
}

function sr_schema_snapshot_install_schema(): array
{
    $schema = [];
    foreach (sr_schema_snapshot_sql_files() as $file) {
        if (!str_ends_with($file, '/install.sql') && $file !== 'database/schema.sql') {
            continue;
        }

        $sql = file_get_contents($file);
        if (!is_string($sql)) {
            continue;
        }

        if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z0-9_]+)`?\s*\((.*?)\)\s*;/is', $sql, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $table = strtolower((string) $match[1]);
                $body = (string) $match[2];
                if ($table === '') {
                    continue;
                }
                $schema[$table] ??= [
                    'file' => $file,
                    'columns' => [],
                ];
                foreach (preg_split('/\R/', $body) ?: [] as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $line = rtrim($line, ',');
                    if (preg_match('/\A(?:PRIMARY|UNIQUE|KEY|INDEX|CONSTRAINT|FULLTEXT|FOREIGN|CHECK)\b/i', $line) === 1) {
                        continue;
                    }
                    if (preg_match('/\A`?([a-z0-9_]+)`?\s+/i', $line, $columnMatch) !== 1) {
                        continue;
                    }
                    $column = strtolower((string) $columnMatch[1]);
                    if ($column !== '') {
                        $schema[$table]['columns'][$column] = true;
                    }
                }
            }
        }
    }

    ksort($schema);
    foreach ($schema as $table => $definition) {
        ksort($schema[$table]['columns']);
    }

    return $schema;
}

function sr_schema_snapshot_split_args(string $source): array
{
    $args = [];
    $current = '';
    $quote = '';
    $escaped = false;
    $depth = 0;
    $length = strlen($source);

    for ($i = 0; $i < $length; $i++) {
        $char = $source[$i];
        if ($quote !== '') {
            $current .= $char;
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                continue;
            }
            if ($char === $quote) {
                $quote = '';
            }
            continue;
        }

        if ($char === '\'' || $char === '"') {
            $quote = $char;
            $current .= $char;
            continue;
        }
        if ($char === '(' || $char === '[') {
            $depth++;
            $current .= $char;
            continue;
        }
        if ($char === ')' || $char === ']') {
            if ($depth > 0) {
                $depth--;
            }
            $current .= $char;
            continue;
        }
        if ($char === ',' && $depth === 0) {
            $args[] = trim($current);
            $current = '';
            continue;
        }
        $current .= $char;
    }

    if (trim($current) !== '') {
        $args[] = trim($current);
    }

    return $args;
}

function sr_schema_snapshot_literal_string(string $expression): ?string
{
    $expression = trim($expression);
    if (preg_match('/\A\'((?:\\\\.|[^\'])*)\'\z/s', $expression, $match) === 1) {
        return stripcslashes((string) $match[1]);
    }
    if (preg_match('/\A"((?:\\\\.|[^"])*)"\z/s', $expression, $match) === 1) {
        return stripcslashes((string) $match[1]);
    }

    return null;
}

function sr_schema_snapshot_line_ref(string $file, int $line): string
{
    return $file . ':' . (string) $line;
}

function sr_schema_snapshot_extract_calls(string $line, string $namePattern): array
{
    $calls = [];
    if (preg_match_all('/\b(' . $namePattern . ')\s*\(/i', $line, $matches, PREG_OFFSET_CAPTURE) === false) {
        return [];
    }

    foreach ($matches[1] as $index => $match) {
        $name = (string) $match[0];
        $openOffset = (int) $matches[0][$index][1] + strlen((string) $matches[0][$index][0]) - 1;
        $quote = '';
        $escaped = false;
        $depth = 0;
        $args = '';
        $length = strlen($line);
        for ($i = $openOffset; $i < $length; $i++) {
            $char = $line[$i];
            if ($quote !== '') {
                if ($i > $openOffset) {
                    $args .= $char;
                }
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($char === '\'' || $char === '"') {
                $quote = $char;
                if ($i > $openOffset) {
                    $args .= $char;
                }
                continue;
            }
            if ($char === '(') {
                if ($depth > 0) {
                    $args .= $char;
                }
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
                if ($depth > 0) {
                    $args .= $char;
                }
                continue;
            }
            if ($i > $openOffset) {
                $args .= $char;
            }
        }

        $calls[] = [
            'name' => $name,
            'args' => trim($args),
        ];
    }

    return $calls;
}

function sr_schema_snapshot_scan_php(array $schema, bool $includeTools): array
{
    $result = [
        'schema_unavailable' => [],
        'legacy_unknown_aliases' => [],
        'legacy_unknown_other' => [],
        'named_guard_calls' => [],
        'named_guard_helpers' => [],
        'optional_guard_calls' => [],
    ];

    foreach (sr_schema_snapshot_php_files($includeTools) as $file) {
        $contents = file_get_contents($file);
        if (!is_string($contents)) {
            continue;
        }
        $lines = preg_split('/\R/', $contents) ?: [];

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            if (strpos($line, 'schema_unavailable') !== false) {
                $result['schema_unavailable'][] = sr_schema_snapshot_line_ref($file, $lineNumber);
            }

            if (strpos($line, 'legacy_unknown') !== false) {
                if (preg_match('/\\\\?\'legacy_unknown\\\\?\'\s+AS\s+([a-z0-9_]+)/i', $line, $aliasMatch) === 1) {
                    $result['legacy_unknown_aliases'][] = [
                        'ref' => sr_schema_snapshot_line_ref($file, $lineNumber),
                        'alias' => strtolower((string) $aliasMatch[1]),
                    ];
                } else {
                    $result['legacy_unknown_other'][] = sr_schema_snapshot_line_ref($file, $lineNumber);
                }
            }

            foreach (sr_schema_snapshot_extract_calls($line, 'sr_[a-z0-9_]+_(?:column|columns)_exist(?:s)?') as $call) {
                $helper = (string) $call['name'];
                    if (str_contains($helper, '_optional_column_exists')) {
                        continue;
                    }
                    if (preg_match('/\bfunction\s+' . preg_quote($helper, '/') . '\s*\(/', $line) === 1) {
                        $result['named_guard_helpers'][$helper] = $file;
                        continue;
                    }
                    $result['named_guard_calls'][] = [
                        'helper' => $helper,
                        'ref' => sr_schema_snapshot_line_ref($file, $lineNumber),
                    ];
            }

            foreach (sr_schema_snapshot_extract_calls($line, 'sr_[a-z0-9_]+_optional_(?:table|column)_exists') as $call) {
                    $helper = (string) $call['name'];
                    if (preg_match('/\bfunction\s+' . preg_quote($helper, '/') . '\s*\(/', $line) === 1) {
                        continue;
                    }
                    $kind = str_contains($helper, '_optional_column_exists') ? 'column' : 'table';
                    $args = sr_schema_snapshot_split_args((string) $call['args']);
                    $tableArg = $args[1] ?? '';
                    $columnArg = $kind === 'column' ? ($args[2] ?? '') : '';
                    $table = sr_schema_snapshot_literal_string($tableArg);
                    $column = $kind === 'column' ? sr_schema_snapshot_literal_string($columnArg) : null;
                    $status = 'needs_manual_resolution';
                    if ($table !== null && $kind === 'table') {
                        $status = isset($schema[strtolower($table)]) ? 'literal_table_in_install' : 'literal_table_missing_from_install';
                    } elseif ($table !== null && $column !== null) {
                        $tableKey = strtolower($table);
                        $columnKey = strtolower($column);
                        if (!isset($schema[$tableKey])) {
                            $status = 'literal_table_missing_from_install';
                        } elseif (isset($schema[$tableKey]['columns'][$columnKey])) {
                            $status = 'literal_column_in_install';
                        } else {
                            $status = 'literal_column_missing_from_install';
                        }
                    }
                    $result['optional_guard_calls'][] = [
                        'helper' => $helper,
                        'kind' => $kind,
                        'ref' => sr_schema_snapshot_line_ref($file, $lineNumber),
                        'table_arg' => $tableArg,
                        'table' => $table,
                        'column_arg' => $columnArg,
                        'column' => $column,
                        'status' => $status,
                    ];
            }
        }
    }

    ksort($result['named_guard_helpers']);
    usort($result['named_guard_calls'], static fn (array $a, array $b): int => strcmp($a['helper'] . $a['ref'], $b['helper'] . $b['ref']));
    usort($result['optional_guard_calls'], static fn (array $a, array $b): int => strcmp($a['status'] . $a['ref'], $b['status'] . $b['ref']));

    return $result;
}

function sr_schema_snapshot_markdown(array $snapshot): string
{
    $optionalByStatus = [];
    foreach ($snapshot['optional_guard_calls'] as $call) {
        $optionalByStatus[(string) $call['status']][] = $call;
    }
    ksort($optionalByStatus);

    $lines = [];
    $lines[] = '# Issue 397 Schema Fallback Snapshot';
    $lines[] = '';
    $lines[] = 'Generated by `.tools/bin/snapshot-schema-fallbacks.php`.';
    $lines[] = '';
    $lines[] = '## Summary';
    $lines[] = '';
    $lines[] = '- `schema_unavailable` occurrences: ' . (string) count($snapshot['schema_unavailable']);
    $lines[] = '- `legacy_unknown` SQL alias occurrences: ' . (string) count($snapshot['legacy_unknown_aliases']);
    $lines[] = '- other `legacy_unknown` occurrences: ' . (string) count($snapshot['legacy_unknown_other']);
    $lines[] = '- named schema guard calls: ' . (string) count($snapshot['named_guard_calls']);
    $lines[] = '- unique named schema guard helpers: ' . (string) count($snapshot['named_guard_helpers']);
    $lines[] = '- generic optional guard calls: ' . (string) count($snapshot['optional_guard_calls']);
    $lines[] = '';
    $lines[] = '## Generic Optional Guard Status';
    $lines[] = '';
    foreach ($optionalByStatus as $status => $calls) {
        $lines[] = '- `' . $status . '`: ' . (string) count($calls);
    }
    $lines[] = '';
    $lines[] = '## Manual Resolution Queue';
    $lines[] = '';
    $manual = $optionalByStatus['needs_manual_resolution'] ?? [];
    if ($manual === []) {
        $lines[] = '- None.';
    } else {
        foreach ($manual as $call) {
            $lines[] = '- ' . $call['ref'] . ' `' . $call['helper'] . '` table=' . '`' . $call['table_arg'] . '` column=' . '`' . ($call['column_arg'] !== '' ? $call['column_arg'] : '-') . '`';
        }
    }
    $lines[] = '';
    $lines[] = '## Legacy Unknown SQL Aliases';
    $lines[] = '';
    foreach ($snapshot['legacy_unknown_aliases'] as $alias) {
        $lines[] = '- ' . $alias['ref'] . ' alias=' . '`' . $alias['alias'] . '`';
    }
    $lines[] = '';
    $lines[] = '## Schema Unavailable';
    $lines[] = '';
    foreach ($snapshot['schema_unavailable'] as $ref) {
        $lines[] = '- ' . $ref;
    }

    return implode("\n", $lines) . "\n";
}

$includeTools = in_array('--include-tools', $argv, true);
$schema = sr_schema_snapshot_install_schema();
$snapshot = sr_schema_snapshot_scan_php($schema, $includeTools);

if (in_array('--json', $argv, true)) {
    echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

echo sr_schema_snapshot_markdown($snapshot);
