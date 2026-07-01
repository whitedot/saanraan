#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}
require_once $root . '/core/helpers.php';
require_once $root . '/modules/asset_ledger/helpers.php';

$errors = [];
$read = static function (string $path) use (&$errors): string {
    $content = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($content)) {
        $errors[] = 'missing file: ' . $path;
        return '';
    }

    return $content;
};
$requireContains = static function (string $path, array $needles) use (&$errors, $read): void {
    $content = $read($path);
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = $path . ' is missing marker: ' . $needle;
        }
    }
};

$requireContains('modules/asset_ledger/install.sql', [
    'CREATE TABLE IF NOT EXISTS sr_asset_recovery_failures',
    'dedupe_key VARCHAR(190) NOT NULL',
    'UNIQUE KEY uq_sr_asset_recovery_dedupe (dedupe_key)',
    'CREATE TABLE IF NOT EXISTS sr_asset_recovery_reversal_links',
    'UNIQUE KEY uq_sr_asset_recovery_reversal_link',
]);
$requireContains('modules/asset_ledger/updates/2026.06.002.sql', [
    'CREATE TABLE IF NOT EXISTS sr_asset_recovery_failures',
    'CREATE TABLE IF NOT EXISTS sr_asset_recovery_reversal_links',
    '{{SR_TABLE_PREFIX}}community_asset_recovery_failures',
    'INSERT IGNORE INTO {{SR_TABLE_PREFIX}}asset_recovery_failures',
    "CONCAT(''source:community:'', original_asset_log_id, '':rev:''",
    "CASE WHEN status = ''resolved'' THEN ''recovered'' ELSE status END",
]);
$requireContains('modules/asset_ledger/helpers.php', [
    "return ['open', 'recovered', 'manually_resolved', 'cancelled'];",
    "return 'source:' . \$sourceModule . ':' . (string) \$sourceLogId . ':rev:' . \$reversalEventKey;",
    'sr_asset_recovery_failure_by_dedupe_key_for_update',
    'sr_asset_recovery_record_failure',
    'sr_asset_recovery_record_reversal_link',
    'sr_asset_recovery_retry',
    "function_exists('sr_module_enabled') && !sr_module_enabled(\$pdo, 'community')",
    "!is_file(SR_ROOT . '/modules/community/helpers.php')",
    "!function_exists('sr_community_reverse_asset_grant_for_operation')",
    'AND version = :version',
    "(string) \$failure['reversal_event_key'],\n            (string) \$failure['reversal_event_key'],\n            'asset.recovery.retry'",
]);
$requireContains('modules/asset_ledger/paths.php', [
    'GET /admin/assets/recovery-failures',
    'POST /admin/assets/recovery-failures',
]);
$requireContains('modules/asset_ledger/actions/admin-assets-recovery-failures.php', [
    "'event_type' => 'asset_recovery.'",
    "'admin_reason' => mb_substr(\$reason, 0, 500)",
]);
$requireContains('modules/asset_ledger/views/admin-recovery-failures.php', [
    '포인트/금액 미회수 관리',
    '미회수 기록',
    'manual_resolve',
    'manual_cancel',
]);
$requireContains('modules/asset_ledger/module.php', [
    "'version' => '2026.06.002'",
    'privacy-export.php',
    'privacy-cleanup.php',
]);
$requireContains('modules/community/helpers/asset-events.php', [
    "require_once SR_ROOT . '/modules/asset_ledger/helpers.php';",
    'sr_asset_recovery_record_failure',
    'sr_asset_recovery_record_reversal_link',
    "'recovered'",
    "'manually_resolved'",
]);
$requireContains('modules/community/actions/admin-recovery-failures.php', [
    "sr_redirect('/admin/assets/recovery-failures'",
]);
$communityMenu = $read('modules/community/admin-menu.php');
if (str_contains($communityMenu, '/admin/community/recovery-failures')) {
    $errors[] = 'community admin menu must not expose duplicate recovery failures screen.';
}
if (is_file('modules/community/views/admin-recovery-failures.php')) {
    $errors[] = 'community module must not keep a duplicate legacy recovery failures view.';
}
$requireContains('docs/core-decisions.md', [
    'sr_asset_recovery_failures',
    'source:{source_module}:{source_log_id}:rev:{reversal_event_key}',
    'manually_resolved',
]);
$requireContains('docs/module-guide.md', [
    '/admin/assets/recovery-failures',
    'sr_asset_recovery_reversal_links',
]);

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('CREATE TABLE sr_member_accounts (id INTEGER PRIMARY KEY, email TEXT NOT NULL, display_name TEXT NOT NULL)');
    $pdo->exec(
        'CREATE TABLE sr_asset_recovery_failures (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            dedupe_key TEXT NOT NULL UNIQUE,
            source_module TEXT NOT NULL,
            source_log_id INTEGER NOT NULL,
            asset_module TEXT NOT NULL,
            account_id INTEGER NOT NULL,
            original_transaction_id INTEGER NOT NULL DEFAULT 0,
            subject_type TEXT NOT NULL,
            subject_id INTEGER NOT NULL,
            grant_event_key TEXT NOT NULL,
            reversal_event_key TEXT NOT NULL,
            operation_event_key TEXT NOT NULL DEFAULT \'\',
            attempted_amount INTEGER NOT NULL DEFAULT 0,
            recovered_amount INTEGER NOT NULL DEFAULT 0,
            unrecovered_amount INTEGER NOT NULL DEFAULT 0,
            failure_reason TEXT NOT NULL DEFAULT \'balance_low\',
            status TEXT NOT NULL DEFAULT \'open\',
            actor_account_id INTEGER NULL,
            actor_type TEXT NOT NULL DEFAULT \'\',
            operation_context_json TEXT NULL,
            attempt_count INTEGER NOT NULL DEFAULT 1,
            version INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            last_attempted_at TEXT NOT NULL,
            resolved_at TEXT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_asset_recovery_reversal_links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            failure_id INTEGER NOT NULL,
            asset_module TEXT NOT NULL,
            reversal_transaction_id INTEGER NOT NULL,
            recovered_amount INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            UNIQUE(failure_id, asset_module, reversal_transaction_id)
        )'
    );

    $basePayload = [
        'source_module' => 'community',
        'source_log_id' => 10,
        'asset_module' => 'point',
        'account_id' => 7,
        'original_transaction_id' => 77,
        'subject_type' => 'community.post',
        'subject_id' => 700,
        'grant_event_key' => 'community.post.reward_grant',
        'reversal_event_key' => 'community.post.reward_reversal',
        'attempted_amount' => 100,
        'recovered_amount' => 20,
        'failure_reason' => 'balance_low',
        'operation_context' => ['operation_event_key' => 'fixture', 'actor_type' => 'admin'],
    ];
    $failureId = sr_asset_recovery_record_failure($pdo, $basePayload);
    sr_asset_recovery_update_manual_status($pdo, $failureId, 'manually_resolved', 1, 'fixture');
    sr_asset_recovery_record_failure($pdo, array_merge($basePayload, ['recovered_amount' => 100, 'failure_reason' => 'recovered']));
    $closed = sr_asset_recovery_failure_by_id($pdo, $failureId);
    if (!is_array($closed)
        || (string) ($closed['status'] ?? '') !== 'manually_resolved'
        || (int) ($closed['recovered_amount'] ?? 0) !== 20
        || (int) ($closed['unrecovered_amount'] ?? 0) !== 80
        || (int) ($closed['version'] ?? 0) !== 2
    ) {
        $errors[] = 'common recovery queue must preserve manually closed rows against later dedupe retries.';
    }

    $fullRecoveryOnlyId = sr_asset_recovery_record_failure($pdo, array_merge($basePayload, [
        'source_log_id' => 12,
        'recovered_amount' => 100,
        'failure_reason' => 'recovered',
    ]));
    $fullRecoveryOnlyCount = (int) $pdo->query("SELECT COUNT(*) FROM sr_asset_recovery_failures WHERE source_log_id = 12")->fetchColumn();
    if ($fullRecoveryOnlyId !== 0 || $fullRecoveryOnlyCount !== 0) {
        $errors[] = 'common recovery queue must not create a new row for first-attempt full recovery.';
    }

    $secondId = sr_asset_recovery_record_failure($pdo, array_merge($basePayload, [
        'source_log_id' => 11,
        'recovered_amount' => 0,
    ]));
    sr_asset_recovery_record_failure($pdo, array_merge($basePayload, [
        'source_log_id' => 11,
        'recovered_amount' => 100,
        'failure_reason' => 'recovered',
    ]));
    $recovered = sr_asset_recovery_failure_by_id($pdo, $secondId);
    if (!is_array($recovered)
        || (string) ($recovered['status'] ?? '') !== 'recovered'
        || (int) ($recovered['recovered_amount'] ?? 0) !== 100
        || (int) ($recovered['unrecovered_amount'] ?? 0) !== 0
    ) {
        $errors[] = 'common recovery queue must close open rows when the recovered amount reaches the attempted amount.';
    }
} catch (Throwable $exception) {
    $errors[] = 'asset recovery queue runtime fixture failed: ' . $exception->getMessage();
}

if ($errors !== []) {
    fwrite(STDERR, "asset recovery queue check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "asset recovery queue check passed.\n";
