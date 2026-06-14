#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once SR_ROOT . '/modules/privacy/helpers/requests.php';

$errors = [];

if (!function_exists('sr_now')) {
    function sr_now(): string
    {
        return '2026-06-14 00:00:00';
    }
}

function sr_privacy_request_admin_note_check_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

$rawNote = '처리 근거 admin@example.test 010-1234-5678 900101-1234567 password=plain token=abc Authorization: Bearer secret-token';
$sanitizedNote = sr_admin_privacy_request_admin_note_sanitize($rawNote);

foreach (['admin@example.test', '010-1234-5678', '900101-1234567', 'plain', 'abc', 'secret-token'] as $rawFragment) {
    sr_privacy_request_admin_note_check_assert(
        !str_contains($sanitizedNote, $rawFragment),
        'Admin privacy request note sanitizer must remove raw sensitive fragment: ' . $rawFragment
    );
}

foreach (['[redacted-email]', '[redacted-phone]', '[redacted-id]', 'password=[masked]', 'token=[masked]', 'Authorization: [masked]'] as $expectedFragment) {
    sr_privacy_request_admin_note_check_assert(
        str_contains($sanitizedNote, $expectedFragment),
        'Admin privacy request note sanitizer must include redaction marker: ' . $expectedFragment
    );
}

$export = sr_admin_privacy_request_export_data(new PDO('sqlite::memory:'), [
    'id' => 1,
    'account_id' => null,
    'request_type' => 'access',
    'status' => 'completed',
    'requester_snapshot' => 'requester@example.test',
    'request_message' => 'request body',
    'admin_note' => $rawNote,
    'handled_by_account_id' => 9,
    'handled_at' => '2026-06-14 00:00:00',
    'created_at' => '2026-06-14 00:00:00',
    'updated_at' => '2026-06-14 00:00:00',
]);
$exportedNote = (string) ($export['privacy_request']['admin_note'] ?? '');
sr_privacy_request_admin_note_check_assert($exportedNote === $sanitizedNote, 'Admin privacy request export must sanitize admin_note at export time.');
sr_privacy_request_admin_note_check_assert(!isset($export['account_data']), 'Admin privacy request export fixture should not load account data when account_id is null.');

$moduleExport = sr_privacy_export_sanitize_module_data([
    'visible' => 'ok',
    'nested' => [
        'api_key' => 'secret-api-key',
        'token_hash' => 'token-hash',
        'body' => 'kept',
    ],
]);
sr_privacy_request_admin_note_check_assert(
    is_array($moduleExport)
        && ($moduleExport['visible'] ?? '') === 'ok'
        && (($moduleExport['nested']['body'] ?? '') === 'kept')
        && !array_key_exists('api_key', $moduleExport['nested'] ?? [])
        && !array_key_exists('token_hash', $moduleExport['nested'] ?? []),
    'Privacy export sanitizer must remove nested secret/hash fields from module data.'
);

if ($errors !== []) {
    fwrite(STDERR, "privacy request admin note checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "privacy request admin note checks completed.\n";
