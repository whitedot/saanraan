#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/helpers.php';
require_once $root . '/modules/payment_ledger/helpers.php';

$errors = [];

function sr_payment_runtime_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

function sr_payment_runtime_row(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? $row : [];
}

function sr_payment_runtime_create_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE sr_modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_key TEXT NOT NULL UNIQUE,
        status TEXT NOT NULL DEFAULT 'enabled'
    )");
    $pdo->exec("CREATE TABLE sr_payment_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        dedupe_key TEXT NOT NULL UNIQUE,
        account_id INTEGER NOT NULL,
        subject_module TEXT NOT NULL,
        subject_type TEXT NOT NULL,
        subject_id TEXT NOT NULL,
        payment_kind TEXT NOT NULL DEFAULT 'purchase',
        status TEXT NOT NULL DEFAULT 'paid',
        payable_amount INTEGER NOT NULL DEFAULT 0,
        settlement_amount INTEGER NOT NULL DEFAULT 0,
        settlement_currency TEXT NOT NULL DEFAULT '',
        description TEXT NOT NULL DEFAULT '',
        snapshot_json TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        cancelled_at TEXT
    )");
    $pdo->exec("CREATE TABLE sr_payment_record_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        payment_record_id INTEGER NOT NULL,
        item_kind TEXT NOT NULL,
        owner_module TEXT NOT NULL,
        reference_type TEXT NOT NULL,
        reference_id TEXT NOT NULL,
        amount INTEGER NOT NULL DEFAULT 0,
        currency_code TEXT NOT NULL DEFAULT '',
        reversible INTEGER NOT NULL DEFAULT 0,
        reversal_status TEXT NOT NULL DEFAULT 'none',
        snapshot_json TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE(payment_record_id, item_kind, owner_module, reference_type, reference_id)
    )");
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
sr_payment_runtime_create_schema($pdo);
$pdo->exec("INSERT INTO sr_modules (module_key, status) VALUES ('payment_ledger', 'enabled'), ('content', 'enabled'), ('community', 'enabled')");

$paymentRecordId = sr_payment_ledger_record_payment($pdo, [
    'dedupe_key' => 'content.view:payment:7:7801',
    'account_id' => 7,
    'subject_module' => 'content',
    'subject_type' => 'content.view',
    'subject_id' => '7801',
    'payment_kind' => 'purchase',
    'payable_amount' => 100,
    'settlement_amount' => 100,
    'settlement_currency' => 'KRW',
    'description' => 'fixture content view',
    'snapshot' => ['schema_version' => 'payment_record_v1'],
], [
    [
        'item_kind' => 'coupon_redemption',
        'owner_module' => 'coupon',
        'reference_type' => 'coupon_redemption',
        'reference_id' => '55',
        'amount' => -40,
        'currency_code' => 'KRW',
        'reversible' => true,
    ],
    [
        'item_kind' => 'asset_transaction',
        'owner_module' => 'point',
        'reference_type' => 'point_transaction',
        'reference_id' => '99',
        'amount' => -60,
        'currency_code' => 'KRW',
        'reversible' => true,
    ],
    [
        'item_kind' => 'access_entitlement',
        'owner_module' => 'content',
        'reference_type' => 'content.access',
        'reference_id' => 'content.view:7801:account:7',
        'amount' => 0,
        'currency_code' => '',
        'reversible' => true,
    ],
]);

sr_payment_runtime_assert($paymentRecordId > 0, 'payment ledger should create a payment record.');
sr_payment_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_payment_records')->fetchColumn() === 1, 'payment ledger should persist one payment record.');
sr_payment_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_payment_record_items')->fetchColumn() === 3, 'payment ledger should persist payment items.');

$duplicatePaymentRecordId = sr_payment_ledger_record_payment($pdo, [
    'dedupe_key' => 'content.view:payment:7:7801',
    'account_id' => 7,
    'subject_module' => 'content',
    'subject_type' => 'content.view',
    'subject_id' => '7801',
], [
    [
        'item_kind' => 'coupon_redemption',
        'owner_module' => 'coupon',
        'reference_type' => 'coupon_redemption',
        'reference_id' => '55',
        'amount' => -40,
    ],
    [
        'item_kind' => 'asset_transaction',
        'owner_module' => 'reward',
        'reference_type' => 'reward_transaction',
        'reference_id' => '777',
        'amount' => -10,
    ],
]);
sr_payment_runtime_assert($duplicatePaymentRecordId === $paymentRecordId, 'payment ledger should resolve duplicate dedupe keys to the existing record.');
sr_payment_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_payment_records')->fetchColumn() === 1, 'duplicate payment record should not create another row.');
sr_payment_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_payment_record_items')->fetchColumn() === 3, 'duplicate payment calls should not append new items to an immutable record.');

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:7801',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.download',
        'subject_id' => '7801',
    ], []);
    sr_payment_runtime_assert(false, 'payment ledger should reject mismatched duplicate record data.');
} catch (RuntimeException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects mismatched duplicate record data.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:unknown-target',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.viwe',
        'subject_id' => '7801',
    ], []);
    sr_payment_runtime_assert(false, 'payment ledger should reject unknown subject contracts.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects unknown subject contracts.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:overlong-target',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.' . str_repeat('a', 90),
        'subject_id' => '7801',
    ], []);
    sr_payment_runtime_assert(false, 'payment ledger should reject overlong subject contract keys instead of truncating them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects overlong subject contract keys.');
}

sr_payment_ledger_mark_cancelled($pdo, $paymentRecordId, 'fixture cancel');
$cancelled = sr_payment_runtime_row($pdo, 'SELECT status, description, cancelled_at FROM sr_payment_records WHERE id = :id', ['id' => $paymentRecordId]);
sr_payment_runtime_assert((string) ($cancelled['status'] ?? '') === 'cancelled', 'payment ledger should mark a payment record cancelled.');
sr_payment_runtime_assert((string) ($cancelled['description'] ?? '') === 'fixture cancel', 'payment ledger cancellation should preserve the reason.');
sr_payment_runtime_assert((string) ($cancelled['cancelled_at'] ?? '') !== '', 'payment ledger cancellation should store cancelled_at.');
sr_payment_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_payment_record_items WHERE payment_record_id = " . (int) $paymentRecordId . " AND reversal_status = 'pending'")->fetchColumn() === 3, 'payment ledger cancellation should mark reversible items pending.');

$reversedItems = sr_payment_ledger_mark_record_items_reversal_status($pdo, $paymentRecordId, 'reversed');
sr_payment_runtime_assert($reversedItems === 3, 'payment ledger should update reversible item reversal statuses.');
sr_payment_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_payment_record_items WHERE payment_record_id = " . (int) $paymentRecordId . " AND reversal_status = 'reversed'")->fetchColumn() === 3, 'payment ledger should persist item reversal status changes.');

sr_payment_ledger_mark_cancelled($pdo, $paymentRecordId, 'fixture cancel again');
sr_payment_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_payment_record_items WHERE payment_record_id = " . (int) $paymentRecordId . " AND reversal_status = 'pending'")->fetchColumn() === 0, 'payment ledger repeated cancellation should not move reversed items back to pending.');
sr_payment_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_payment_record_items WHERE payment_record_id = " . (int) $paymentRecordId . " AND reversal_status = 'reversed'")->fetchColumn() === 3, 'payment ledger repeated cancellation should preserve reversed item statuses.');

$exporter = require $root . '/modules/payment_ledger/privacy-export.php';
$export = $exporter($pdo, 7);
sr_payment_runtime_assert(count((array) ($export['payment_records'] ?? [])) === 1, 'payment ledger privacy export should include account records.');
sr_payment_runtime_assert(count((array) ($export['payment_record_items'] ?? [])) === 3, 'payment ledger privacy export should include record items.');

$cleanup = require $root . '/modules/payment_ledger/privacy-cleanup.php';
$cleanupResult = $cleanup($pdo, 7, 'anonymize');
sr_payment_runtime_assert((int) ($cleanupResult['payment_records'] ?? 0) === 1, 'payment ledger privacy cleanup should anonymize account records.');
sr_payment_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_payment_records WHERE account_id = 0')->fetchColumn() === 1, 'payment ledger privacy cleanup should clear account_id.');

$lateReplayRecordId = sr_payment_ledger_record_payment($pdo, [
    'dedupe_key' => 'content.view:payment:7:7801',
    'account_id' => 7,
    'subject_module' => 'content',
    'subject_type' => 'content.view',
    'subject_id' => '7801',
    'payment_kind' => 'purchase',
    'status' => 'paid',
    'payable_amount' => 100,
    'settlement_amount' => 100,
    'settlement_currency' => 'KRW',
], [
    [
        'item_kind' => 'asset_transaction',
        'owner_module' => 'deposit',
        'reference_type' => 'deposit_transaction',
        'reference_id' => 'late-replay',
        'amount' => -10,
    ],
]);
sr_payment_runtime_assert($lateReplayRecordId === $paymentRecordId, 'payment ledger should absorb late replay for anonymized duplicate records.');
sr_payment_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_payment_records WHERE account_id = 0')->fetchColumn() === 1, 'payment ledger late replay should not relink anonymized account records.');
sr_payment_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_payment_record_items')->fetchColumn() === 3, 'payment ledger late replay should not append items to anonymized duplicate records.');

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo "payment ledger runtime checks completed.\n";
