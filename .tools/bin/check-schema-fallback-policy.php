#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_schema_fallback_policy_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/snapshot-schema-fallbacks.php') . ' --json';
$json = shell_exec($command);
if (!is_string($json) || trim($json) === '') {
    sr_schema_fallback_policy_error('schema fallback snapshot command produced no JSON output.');
    $json = '{}';
}

$snapshot = json_decode($json, true);
if (!is_array($snapshot)) {
    sr_schema_fallback_policy_error('schema fallback snapshot JSON could not be decoded.');
    $snapshot = [];
}

$schemaUnavailable = is_array($snapshot['schema_unavailable'] ?? null) ? $snapshot['schema_unavailable'] : [];
$legacyAliases = is_array($snapshot['legacy_unknown_aliases'] ?? null) ? $snapshot['legacy_unknown_aliases'] : [];
$legacyOther = is_array($snapshot['legacy_unknown_other'] ?? null) ? $snapshot['legacy_unknown_other'] : [];
$namedCalls = is_array($snapshot['named_guard_calls'] ?? null) ? $snapshot['named_guard_calls'] : [];
$namedHelpers = is_array($snapshot['named_guard_helpers'] ?? null) ? $snapshot['named_guard_helpers'] : [];
$optionalCalls = is_array($snapshot['optional_guard_calls'] ?? null) ? $snapshot['optional_guard_calls'] : [];

if ($schemaUnavailable !== []) {
    sr_schema_fallback_policy_error('runtime schema_unavailable fallback references must be removed: ' . implode(', ', array_map('strval', $schemaUnavailable)));
}
if ($legacyAliases !== []) {
    $refs = array_map(static fn (array $alias): string => (string) ($alias['ref'] ?? ''), $legacyAliases);
    sr_schema_fallback_policy_error('runtime legacy_unknown SQL aliases must be removed: ' . implode(', ', array_filter($refs)));
}
if ($namedCalls !== []) {
    $refs = array_map(static fn (array $call): string => (string) ($call['ref'] ?? ''), $namedCalls);
    sr_schema_fallback_policy_error('named schema guard calls must be removed from runtime code: ' . implode(', ', array_filter($refs)));
}
if ($namedHelpers !== []) {
    sr_schema_fallback_policy_error('named schema guard helper definitions must not remain in runtime code: ' . implode(', ', array_keys($namedHelpers)));
}

$allowedLegacyUnknownFiles = [
    'modules/community/helpers/assets.php' => true,
    'modules/community/helpers/attachments.php' => true,
    'modules/content/helpers/assets.php' => true,
    'modules/content/helpers/files.php' => true,
];
foreach ($legacyOther as $ref) {
    $file = preg_replace('/:\d+\z/', '', (string) $ref) ?? '';
    if (!isset($allowedLegacyUnknownFiles[$file])) {
        sr_schema_fallback_policy_error('legacy_unknown is only allowed as settlement taxonomy in known ledger/export files: ' . (string) $ref);
    }
}

$allowedOptional = [
    'modules/community/helpers/board-cleanup.php|sr_community_optional_table_exists|$tableName' => true,
    'modules/content/helpers.php|sr_content_optional_table_exists|$tableName' => true,
];
foreach ($optionalCalls as $call) {
    $ref = (string) ($call['ref'] ?? '');
    $file = preg_replace('/:\d+\z/', '', $ref) ?? '';
    $helper = (string) ($call['helper'] ?? '');
    $tableArg = (string) ($call['table_arg'] ?? '');
    $status = (string) ($call['status'] ?? '');
    $key = $file . '|' . $helper . '|' . $tableArg;
    if ($status !== 'needs_manual_resolution' || !isset($allowedOptional[$key])) {
        sr_schema_fallback_policy_error('unexpected generic optional schema guard call: ' . $ref . ' ' . $helper . ' table=' . $tableArg . ' status=' . $status);
    }
}

if (count($optionalCalls) !== count($allowedOptional)) {
    sr_schema_fallback_policy_error('generic optional schema guard allowlist count changed; expected ' . count($allowedOptional) . ', got ' . count($optionalCalls) . '.');
}

if ($errors !== []) {
    fwrite(STDERR, "schema fallback policy checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "schema fallback policy checks completed.\n";
