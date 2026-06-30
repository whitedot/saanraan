#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/admin/helpers.php';
require_once $root . '/modules/community/helpers.php';

$errors = [];

function sr_community_attachment_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_community_attachment_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_community_attachment_runtime_error($message);
    }
}

function sr_community_attachment_runtime_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn();
}

function sr_community_attachment_runtime_row(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? $row : [];
}

function sr_community_attachment_runtime_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE sr_site_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL, value_type TEXT NOT NULL DEFAULT "string")');
    $pdo->exec('CREATE TABLE sr_modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_key TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        version TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT "disabled"
    )');
    $pdo->exec('CREATE TABLE sr_module_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_id INTEGER NOT NULL,
        setting_key TEXT NOT NULL,
        setting_value TEXT,
        value_type TEXT NOT NULL DEFAULT "string"
    )');
    $pdo->exec('CREATE TABLE sr_community_asset_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        asset_module TEXT NOT NULL,
        transaction_id INTEGER NOT NULL DEFAULT 0,
        reference_type TEXT NOT NULL,
        reference_id TEXT NOT NULL,
        subject_type TEXT NOT NULL,
        subject_id INTEGER NOT NULL,
        event_key TEXT NOT NULL,
        direction TEXT NOT NULL,
        charge_policy TEXT NOT NULL DEFAULT "once",
        amount INTEGER NOT NULL,
        settlement_amount INTEGER NOT NULL DEFAULT 0,
        settlement_currency TEXT NOT NULL DEFAULT "KRW",
        purchase_power_snapshot_json TEXT,
        settlement_kind TEXT NOT NULL DEFAULT "legacy_unknown",
        snapshot_schema_version TEXT NOT NULL DEFAULT "asset_settlement_snapshot_v1",
        rounding_policy_version TEXT NOT NULL DEFAULT "asset_settlement_rounding_v1",
        log_status TEXT NOT NULL DEFAULT "completed",
        group_policy_snapshot_json TEXT,
        dedupe_key TEXT NOT NULL UNIQUE,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_community_access_entitlements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER,
        subject_type TEXT NOT NULL,
        subject_id INTEGER NOT NULL,
        event_key TEXT NOT NULL,
        source_kind TEXT NOT NULL DEFAULT "asset",
        source_asset_module TEXT NOT NULL DEFAULT "",
        source_charge_policy TEXT NOT NULL DEFAULT "once",
        source_reference TEXT NOT NULL DEFAULT "",
        granted_at TEXT NOT NULL,
        anonymized_at TEXT,
        created_at TEXT NOT NULL,
        UNIQUE(account_id, subject_type, subject_id, event_key)
    )');
    $pdo->exec('CREATE TABLE sr_community_boards (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        board_key TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_community_posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        board_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        status TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_community_attachments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NOT NULL,
        original_name TEXT NOT NULL,
        status TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_member_accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        display_name TEXT NOT NULL,
        email TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_community_attachment_download_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        board_id INTEGER NOT NULL,
        post_id INTEGER NOT NULL,
        attachment_id INTEGER NOT NULL,
        account_id INTEGER,
        download_type TEXT NOT NULL,
        charge_policy TEXT NOT NULL DEFAULT "once",
        asset_module TEXT NOT NULL DEFAULT "",
        amount INTEGER NOT NULL DEFAULT 0,
        asset_access_log_ids_json TEXT,
        post_title_snapshot TEXT NOT NULL DEFAULT "",
        attachment_original_name_snapshot TEXT NOT NULL DEFAULT "",
        created_at TEXT NOT NULL
    )');
    $pdo->exec("INSERT INTO sr_site_settings (setting_key, setting_value, value_type) VALUES ('site.default_currency', 'KRW', 'string')");
    $pdo->exec("INSERT INTO sr_modules (id, module_key, name, version, status) VALUES (1, 'point', '포인트', '1.0.0', 'enabled')");
    $pdo->exec("INSERT INTO sr_community_boards (id, title, board_key) VALUES (33, 'Fixture board', 'fixture')");
    $pdo->exec("INSERT INTO sr_community_posts (id, board_id, title, status) VALUES (77, 33, 'Fixture post', 'published')");
    $pdo->exec("INSERT INTO sr_community_attachments (id, post_id, original_name, status) VALUES (9901, 77, 'fixture.txt', 'active')");
    $pdo->exec("INSERT INTO sr_member_accounts (id, display_name, email) VALUES (10, 'Fixture member', 'fixture@example.test')");
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
sr_community_attachment_runtime_schema($pdo);

$dedupeKey = sr_community_asset_dedupe_key('point', 10, 'attachment_download', 9901);
$placeholder = [
    'account_id' => 10,
    'asset_module' => 'point',
    'reference_type' => 'community.attachment',
    'reference_id' => '9901',
    'subject_type' => 'community.attachment',
    'subject_id' => 9901,
    'event_key' => 'attachment_download',
    'direction' => 'use',
    'charge_policy' => 'once',
    'amount' => 0,
    'settlement_amount' => 0,
    'settlement_currency' => 'KRW',
    'purchase_power_snapshot_json' => '',
    'group_policy_snapshot_json' => '',
    'dedupe_key' => $dedupeKey,
];

sr_community_attachment_runtime_assert(sr_community_insert_asset_log_placeholder($pdo, $placeholder), 'community attachment fixture should insert the first asset log placeholder.');
sr_community_attachment_runtime_assert(!sr_community_insert_asset_log_placeholder($pdo, $placeholder), 'community attachment fixture should ignore duplicate asset log placeholders.');
sr_community_complete_zero_asset_log($pdo, $dedupeKey);

$log = sr_community_attachment_runtime_row($pdo, 'SELECT transaction_id, amount, log_status, settlement_kind, snapshot_schema_version, rounding_policy_version FROM sr_community_asset_logs WHERE dedupe_key = :dedupe_key', ['dedupe_key' => $dedupeKey]);
sr_community_attachment_runtime_assert((int) ($log['transaction_id'] ?? -1) === 0, 'community attachment fixture should keep zero-amount log transaction id at 0.');
sr_community_attachment_runtime_assert((int) ($log['amount'] ?? -1) === 0, 'community attachment fixture should keep zero-amount log amount at 0.');
sr_community_attachment_runtime_assert((string) ($log['log_status'] ?? '') === 'completed', 'community attachment fixture should complete zero-amount logs.');
sr_community_attachment_runtime_assert((string) ($log['settlement_kind'] ?? '') === 'paid_settled_zero', 'community attachment fixture should classify zero-amount settlement kind.');
sr_community_attachment_runtime_assert((string) ($log['snapshot_schema_version'] ?? '') === sr_community_asset_snapshot_schema_version(), 'community attachment fixture should store snapshot schema version.');
sr_community_attachment_runtime_assert((string) ($log['rounding_policy_version'] ?? '') === sr_community_asset_rounding_policy_version(), 'community attachment fixture should store rounding policy version.');

$pdo->prepare(
    'INSERT INTO sr_community_attachment_download_logs
        (board_id, post_id, attachment_id, account_id, download_type, charge_policy, asset_module, amount, asset_access_log_ids_json, post_title_snapshot, attachment_original_name_snapshot, created_at)
     VALUES
        (33, 77, 9901, 10, "paid", "once", "point", 0, :asset_access_log_ids_json, "Fixture post", "fixture.txt", :created_at)'
)->execute([
    'asset_access_log_ids_json' => json_encode([(int) sr_community_attachment_runtime_scalar($pdo, 'SELECT id FROM sr_community_asset_logs WHERE dedupe_key = :dedupe_key', ['dedupe_key' => $dedupeKey])]),
    'created_at' => sr_now(),
]);
$downloadLogs = sr_community_admin_attachment_download_logs($pdo, [], 10, 0, sr_community_admin_attachment_download_log_default_sort());
$downloadLogCount = count($downloadLogs);
$downloadLogIds = $downloadLogCount > 0 ? sr_community_attachment_download_log_access_log_ids($downloadLogs[0]) : [];
$downloadSummary = (string) ($downloadLogs[0]['asset_log_summary'] ?? '');
sr_community_attachment_runtime_assert($downloadLogCount === 1, 'community attachment admin download logs should return the fixture row. count=' . (string) $downloadLogCount);
sr_community_attachment_runtime_assert($downloadLogIds !== [], 'community attachment admin download logs should preserve linked asset log ids.');
sr_community_attachment_runtime_assert(str_contains($downloadSummary, '기준 0 KRW'), 'community attachment admin download logs should include settlement amount and currency summary. summary=' . $downloadSummary);
sr_community_attachment_runtime_assert(str_contains($downloadSummary, 'snapshot ' . sr_community_asset_snapshot_schema_version()), 'community attachment admin download logs should include snapshot schema version summary. summary=' . $downloadSummary);
sr_community_attachment_runtime_assert(str_contains($downloadSummary, 'rounding ' . sr_community_asset_rounding_policy_version()), 'community attachment admin download logs should include rounding policy version summary. summary=' . $downloadSummary);

sr_community_grant_access_entitlement($pdo, 10, 'community.attachment', 9901, 'attachment_download', 'asset_group_policy', 'point', 'once', $dedupeKey);
sr_community_grant_access_entitlement($pdo, 10, 'community.attachment', 9901, 'attachment_download', 'asset_group_policy', 'point', 'once', $dedupeKey . ':duplicate');
sr_community_attachment_runtime_assert((int) sr_community_attachment_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_access_entitlements WHERE account_id = 10 AND subject_type = "community.attachment" AND subject_id = 9901 AND event_key = "attachment_download"') === 1, 'community attachment fixture should keep one entitlement per account/attachment/event.');
sr_community_attachment_runtime_assert(sr_community_has_access_entitlement($pdo, ['point'], 10, 'attachment_download', 'community.attachment', 9901, '', 'all_access'), 'community attachment fixture should find granted attachment download entitlement.');

sr_community_anonymize_access_entitlements($pdo, 10);
sr_community_attachment_runtime_assert(!sr_community_has_access_entitlement($pdo, ['point'], 10, 'attachment_download', 'community.attachment', 9901, '', 'all_access'), 'community attachment fixture should ignore anonymized entitlements.');

if ($errors !== []) {
    fwrite(STDERR, "community attachment runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "community attachment runtime checks completed.\n";
