#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

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
]);
$requireContains('modules/asset_ledger/helpers.php', [
    "return ['open', 'recovered', 'manually_resolved', 'cancelled'];",
    "return 'source:' . \$sourceModule . ':' . (string) \$sourceLogId . ':rev:' . \$reversalEventKey;",
    'sr_asset_recovery_record_failure',
    'sr_asset_recovery_record_reversal_link',
    'sr_asset_recovery_retry',
]);
$requireContains('modules/asset_ledger/paths.php', [
    'GET /admin/assets/recovery-failures',
    'POST /admin/assets/recovery-failures',
]);
$requireContains('modules/asset_ledger/views/admin-recovery-failures.php', [
    '지급 로그',
    '미회수 큐',
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
$requireContains('docs/core-decisions.md', [
    'sr_asset_recovery_failures',
    'source:{source_module}:{source_log_id}:rev:{reversal_event_key}',
    'manually_resolved',
]);
$requireContains('docs/module-guide.md', [
    '/admin/assets/recovery-failures',
    'sr_asset_recovery_reversal_links',
]);

if ($errors !== []) {
    fwrite(STDERR, "asset recovery queue check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "asset recovery queue check passed.\n";
