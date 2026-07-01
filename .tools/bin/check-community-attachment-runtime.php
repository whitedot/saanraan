#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/admin/helpers.php';
require_once $root . '/modules/point/helpers.php';
require_once $root . '/modules/payment_ledger/helpers.php';
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
        coupon_redemption_id INTEGER,
        coupon_dedupe_key TEXT NOT NULL DEFAULT "",
        refund_status TEXT NOT NULL DEFAULT "",
        refund_transaction_ids_json TEXT,
        refund_note TEXT NOT NULL DEFAULT "",
        refunded_by_account_id INTEGER,
        refunded_at TEXT,
        access_revoked_at TEXT,
        refund_policy_version TEXT NOT NULL DEFAULT "community_attachment_download_refund_v1",
        post_title_snapshot TEXT NOT NULL DEFAULT "",
        attachment_original_name_snapshot TEXT NOT NULL DEFAULT "",
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_point_balances (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL UNIQUE,
        balance INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_point_transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        amount INTEGER NOT NULL,
        balance_after INTEGER NOT NULL,
        transaction_type TEXT NOT NULL,
        reason TEXT NOT NULL DEFAULT "",
        reference_type TEXT NOT NULL DEFAULT "",
        reference_id TEXT NOT NULL DEFAULT "",
        created_by_account_id INTEGER,
        expires_at TEXT,
        expires_remaining INTEGER NOT NULL DEFAULT 0,
        expired_at TEXT,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_point_expiration_consumptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        consume_transaction_id INTEGER NOT NULL,
        source_transaction_id INTEGER NOT NULL,
        amount INTEGER NOT NULL,
        source_expires_at TEXT,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_payment_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        dedupe_key TEXT NOT NULL UNIQUE,
        account_id INTEGER NOT NULL,
        subject_module TEXT NOT NULL,
        subject_type TEXT NOT NULL,
        subject_id TEXT NOT NULL,
        payment_kind TEXT NOT NULL DEFAULT "purchase",
        status TEXT NOT NULL DEFAULT "paid",
        payable_amount INTEGER NOT NULL DEFAULT 0,
        settlement_amount INTEGER NOT NULL DEFAULT 0,
        settlement_currency TEXT NOT NULL DEFAULT "",
        description TEXT NOT NULL DEFAULT "",
        snapshot_json TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        cancelled_at TEXT
    )');
    $pdo->exec('CREATE TABLE sr_payment_record_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        payment_record_id INTEGER NOT NULL,
        item_kind TEXT NOT NULL,
        owner_module TEXT NOT NULL,
        reference_type TEXT NOT NULL,
        reference_id TEXT NOT NULL,
        amount INTEGER NOT NULL DEFAULT 0,
        currency_code TEXT NOT NULL DEFAULT "",
        reversible INTEGER NOT NULL DEFAULT 0,
        reversal_status TEXT NOT NULL DEFAULT "none",
        snapshot_json TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE(payment_record_id, item_kind, owner_module, reference_type, reference_id)
    )');
    $pdo->exec("INSERT INTO sr_site_settings (setting_key, setting_value, value_type) VALUES ('site.default_currency', 'KRW', 'string')");
    $pdo->exec("INSERT INTO sr_modules (id, module_key, name, version, status) VALUES (1, 'point', '포인트', '1.0.0', 'enabled'), (2, 'payment_ledger', '결제 기록', '1.0.0', 'enabled'), (3, 'community', '커뮤니티', '2026.06.045', 'enabled')");
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

sr_community_record_attachment_download($pdo, [
    'id' => 9901,
    'post_id' => 77,
    'original_name' => 'fixture.txt',
    'post' => [
        'id' => 77,
        'board_id' => 33,
        'title' => 'Fixture post',
    ],
], 10, [
    'paid' => true,
    'charge_policy' => 'once',
    'asset_module' => 'point',
    'amount' => 0,
    'access_log_ids' => [],
    'coupon_redemption_id' => 444,
    'coupon_dedupe_key' => 'community.attachment.download:coupon:10:9901',
]);
$couponDownloadLog = sr_community_attachment_runtime_row($pdo, 'SELECT download_type, amount, coupon_redemption_id, coupon_dedupe_key, refund_policy_version FROM sr_community_attachment_download_logs WHERE coupon_redemption_id = 444 LIMIT 1');
sr_community_attachment_runtime_assert((string) ($couponDownloadLog['download_type'] ?? '') === 'paid', 'community attachment coupon download log should remain a paid download.');
sr_community_attachment_runtime_assert((int) ($couponDownloadLog['amount'] ?? -1) === 0, 'community attachment full-coupon download log should store zero asset amount.');
sr_community_attachment_runtime_assert((int) ($couponDownloadLog['coupon_redemption_id'] ?? 0) === 444, 'community attachment download log should store coupon redemption id.');
sr_community_attachment_runtime_assert((string) ($couponDownloadLog['coupon_dedupe_key'] ?? '') === 'community.attachment.download:coupon:10:9901', 'community attachment download log should store coupon dedupe key.');
sr_community_attachment_runtime_assert((string) ($couponDownloadLog['refund_policy_version'] ?? '') === sr_community_attachment_download_refund_policy_version(), 'community attachment download log should stamp refund policy version.');

sr_community_grant_access_entitlement($pdo, 10, 'community.attachment', 9901, 'attachment_download', 'asset_group_policy', 'point', 'once', $dedupeKey);
sr_community_grant_access_entitlement($pdo, 10, 'community.attachment', 9901, 'attachment_download', 'asset_group_policy', 'point', 'once', $dedupeKey . ':duplicate');
sr_community_attachment_runtime_assert((int) sr_community_attachment_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_access_entitlements WHERE account_id = 10 AND subject_type = "community.attachment" AND subject_id = 9901 AND event_key = "attachment_download"') === 1, 'community attachment fixture should keep one entitlement per account/attachment/event.');
sr_community_attachment_runtime_assert(sr_community_has_access_entitlement($pdo, ['point'], 10, 'attachment_download', 'community.attachment', 9901, '', 'all_access'), 'community attachment fixture should find granted attachment download entitlement.');

$refundDedupeKey = $dedupeKey . ':refund-fixture';
$pdo->exec("INSERT INTO sr_point_balances (account_id, balance, created_at, updated_at) VALUES (10, 30, '2026-06-30 00:00:00', '2026-06-30 00:00:00')");
$pdo->exec("INSERT INTO sr_point_transactions (id, account_id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_by_account_id, expires_at, expires_remaining, expired_at, created_at) VALUES (501, 10, -70, 30, 'use', 'attachment download', 'community.attachment', '9901', NULL, NULL, 0, NULL, '2026-06-30 00:00:00')");
$pdo->prepare(
    'INSERT INTO sr_community_asset_logs
        (account_id, asset_module, transaction_id, reference_type, reference_id, subject_type, subject_id, event_key, direction, charge_policy, amount, settlement_amount, settlement_currency, purchase_power_snapshot_json, settlement_kind, snapshot_schema_version, rounding_policy_version, log_status, group_policy_snapshot_json, dedupe_key, created_at)
     VALUES
        (10, "point", 501, "community.attachment", "9901", "community.attachment", 9901, "attachment_download", "use", "once", 70, 70, "KRW", "", "paid", :snapshot_schema_version, :rounding_policy_version, "completed", "", :dedupe_key, :created_at)'
)->execute([
    'snapshot_schema_version' => sr_community_asset_snapshot_schema_version(),
    'rounding_policy_version' => sr_community_asset_rounding_policy_version(),
    'dedupe_key' => $refundDedupeKey,
    'created_at' => sr_now(),
]);
$refundAssetLogId = (int) $pdo->lastInsertId();
$pdo->prepare(
    'INSERT INTO sr_community_attachment_download_logs
        (board_id, post_id, attachment_id, account_id, download_type, charge_policy, asset_module, amount, asset_access_log_ids_json, coupon_redemption_id, coupon_dedupe_key, refund_status, refund_transaction_ids_json, refund_note, refunded_by_account_id, refunded_at, access_revoked_at, refund_policy_version, post_title_snapshot, attachment_original_name_snapshot, created_at)
     VALUES
        (33, 77, 9901, 10, "paid", "once", "point", 70, :asset_access_log_ids_json, 445, "community.attachment.download:coupon:10:9901:mixed", "", "[]", "", NULL, NULL, NULL, "community_attachment_download_refund_v1", "Fixture post", "fixture.txt", :created_at)'
)->execute([
    'asset_access_log_ids_json' => json_encode([$refundAssetLogId]),
    'created_at' => sr_now(),
]);
$refundDownloadLogId = (int) $pdo->lastInsertId();
$paymentRecordId = sr_payment_ledger_record_payment($pdo, [
    'dedupe_key' => 'community.attachment.download:payment:refund-fixture',
    'account_id' => 10,
    'subject_module' => 'community',
    'subject_type' => 'community.attachment.download',
    'subject_id' => '9901',
    'payment_kind' => 'purchase',
    'payable_amount' => 70,
    'settlement_amount' => 70,
    'settlement_currency' => 'KRW',
], [
    [
        'item_kind' => 'asset_transaction',
        'owner_module' => 'point',
        'reference_type' => 'point_transaction',
        'reference_id' => '501',
        'amount' => -70,
        'currency_code' => 'KRW',
        'reversible' => true,
    ],
    [
        'item_kind' => 'asset_access_log',
        'owner_module' => 'community',
        'reference_type' => 'community_asset_log',
        'reference_id' => (string) $refundAssetLogId,
        'amount' => 70,
        'currency_code' => 'KRW',
        'reversible' => true,
    ],
    [
        'item_kind' => 'access_entitlement',
        'owner_module' => 'community',
        'reference_type' => 'community.access_entitlement',
        'reference_id' => 'community.attachment:9901:attachment_download',
        'amount' => 0,
        'currency_code' => '',
        'reversible' => true,
    ],
]);

$refundResult = sr_community_refund_attachment_download($pdo, $refundDownloadLogId, 1, 'fixture refund');
sr_community_attachment_runtime_assert(!empty($refundResult['ok']), 'community attachment refund helper should refund a paid attachment download.');
$refundedDownloadLog = sr_community_attachment_runtime_row($pdo, 'SELECT coupon_redemption_id, coupon_dedupe_key, refund_status, refund_transaction_ids_json, refund_note, refunded_by_account_id, refunded_at, access_revoked_at, refund_policy_version FROM sr_community_attachment_download_logs WHERE id = :id', ['id' => $refundDownloadLogId]);
sr_community_attachment_runtime_assert((int) ($refundedDownloadLog['coupon_redemption_id'] ?? 0) === 445, 'community attachment refund helper should preserve coupon redemption link.');
sr_community_attachment_runtime_assert((string) ($refundedDownloadLog['coupon_dedupe_key'] ?? '') === 'community.attachment.download:coupon:10:9901:mixed', 'community attachment refund helper should preserve coupon dedupe key.');
sr_community_attachment_runtime_assert((string) ($refundedDownloadLog['refund_policy_version'] ?? '') === sr_community_attachment_download_refund_policy_version(), 'community attachment refund helper should preserve refund policy version.');
sr_community_attachment_runtime_assert((string) ($refundedDownloadLog['refund_status'] ?? '') === 'refunded', 'community attachment refund helper should persist refunded status.');
sr_community_attachment_runtime_assert((string) ($refundedDownloadLog['refund_note'] ?? '') === 'fixture refund', 'community attachment refund helper should persist refund note.');
sr_community_attachment_runtime_assert((int) ($refundedDownloadLog['refunded_by_account_id'] ?? 0) === 1, 'community attachment refund helper should persist refund admin account.');
sr_community_attachment_runtime_assert((string) ($refundedDownloadLog['refunded_at'] ?? '') !== '' && (string) ($refundedDownloadLog['access_revoked_at'] ?? '') !== '', 'community attachment refund helper should persist refund and access revoke timestamps.');
$refundIds = json_decode((string) ($refundedDownloadLog['refund_transaction_ids_json'] ?? '[]'), true);
sr_community_attachment_runtime_assert(is_array($refundIds) && count($refundIds) === 1 && str_starts_with((string) $refundIds[0], 'point:'), 'community attachment refund helper should persist refund transaction ids.');
sr_community_attachment_runtime_assert((int) sr_community_attachment_runtime_scalar($pdo, 'SELECT balance FROM sr_point_balances WHERE account_id = 10') === 100, 'community attachment refund helper should restore point balance.');
sr_community_attachment_runtime_assert((int) sr_community_attachment_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_access_entitlements WHERE account_id = 10 AND subject_type = "community.attachment" AND subject_id = 9901 AND event_key = "attachment_download"') === 0, 'community attachment refund helper should revoke attachment download entitlement.');
sr_community_attachment_runtime_assert((string) sr_community_attachment_runtime_scalar($pdo, 'SELECT status FROM sr_payment_records WHERE id = :id', ['id' => $paymentRecordId]) === 'refunded', 'community attachment refund helper should close the payment record when every item is reversed.');
sr_community_attachment_runtime_assert((int) sr_community_attachment_runtime_scalar($pdo, "SELECT COUNT(*) FROM sr_payment_record_items WHERE payment_record_id = :id AND reversal_status = 'reversed'", ['id' => $paymentRecordId]) === 3, 'community attachment refund helper should mark linked payment items reversed.');

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
