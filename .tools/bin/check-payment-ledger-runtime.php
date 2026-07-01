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

foreach (glob($root . '/modules/*/payment-ledger-targets.php') ?: [] as $targetFile) {
    $providerModuleKey = basename(dirname($targetFile));
    if (!preg_match('/\A[a-z0-9_]+\z/', $providerModuleKey)) {
        continue;
    }

    $targets = require $targetFile;
    if (!is_array($targets)) {
        $errors[] = $providerModuleKey . ' payment ledger target contract must return an array.';
        continue;
    }

    foreach ($targets as $target) {
        if (!is_array($target)) {
            $errors[] = $providerModuleKey . ' payment ledger target entries must be arrays.';
            continue;
        }

        sr_payment_runtime_assert(
            (string) ($target['subject_module'] ?? '') === $providerModuleKey,
            $providerModuleKey . ' payment ledger target subject_module must match its provider module.'
        );
    }
}

$paymentLedgerHelperSource = (string) file_get_contents($root . '/modules/payment_ledger/helpers.php');
sr_payment_runtime_assert(
    str_contains($paymentLedgerHelperSource, '$subjectModule !== (string) $moduleKey'),
    'payment ledger target loading must ignore targets whose subject_module is owned by another provider module.'
);
$implementationSnapshotSource = (string) file_get_contents($root . '/docs/implementation-snapshot.md');
sr_payment_runtime_assert(
    str_contains($implementationSnapshotSource, '쿠폰·자산·외부 결제·접근권 부여 item 묶음'),
    'implementation snapshot must describe payment_ledger access entitlement grant items.'
);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
sr_payment_runtime_create_schema($pdo);
$pdo->exec("INSERT INTO sr_modules (module_key, status) VALUES ('payment_ledger', 'enabled'), ('content', 'enabled'), ('community', 'enabled')");

$paymentItems = [
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
        'snapshot' => [
            'source_reference' => 'content:view:7801:account:7:intent:abc',
            'unrelated_reference' => 'content:view:7801:account:77:intent:abc',
        ],
    ],
];

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
], $paymentItems);

sr_payment_runtime_assert($paymentRecordId > 0, 'payment ledger should create a payment record.');
sr_payment_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_payment_records')->fetchColumn() === 1, 'payment ledger should persist one payment record.');
sr_payment_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_payment_record_items')->fetchColumn() === 3, 'payment ledger should persist payment items.');

$duplicatePaymentRecordId = sr_payment_ledger_record_payment($pdo, [
    'dedupe_key' => 'content.view:payment:7:7801',
    'account_id' => 7,
    'subject_module' => 'content',
    'subject_type' => 'content.view',
    'subject_id' => '7801',
    'payable_amount' => 100,
    'settlement_amount' => 100,
    'settlement_currency' => 'KRW',
], $paymentItems);
sr_payment_runtime_assert($duplicatePaymentRecordId === $paymentRecordId, 'payment ledger should resolve duplicate dedupe keys to the existing record.');
sr_payment_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_payment_records')->fetchColumn() === 1, 'duplicate payment record should not create another row.');
sr_payment_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_payment_record_items')->fetchColumn() === 3, 'duplicate payment calls should not append new items to an immutable record.');

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:7801',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
        'payable_amount' => 100,
        'settlement_amount' => 100,
        'settlement_currency' => 'KRW',
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
            'owner_module' => 'reward',
            'reference_type' => 'reward_transaction',
            'reference_id' => '777',
            'amount' => -10,
            'currency_code' => 'KRW',
            'reversible' => true,
        ],
    ]);
    sr_payment_runtime_assert(false, 'payment ledger should reject mismatched duplicate payment items.');
} catch (RuntimeException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects mismatched duplicate payment items.');
}
sr_payment_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_payment_records')->fetchColumn() === 1, 'mismatched duplicate payment items should not create another row.');
sr_payment_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_payment_record_items')->fetchColumn() === 3, 'mismatched duplicate payment items should not append rows.');

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:7801',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
        'payable_amount' => 100,
        'settlement_amount' => 100,
        'settlement_currency' => 'KRW',
        'status' => 'paid-now',
    ], $paymentItems);
    sr_payment_runtime_assert(false, 'payment ledger should reject invalid duplicate record statuses.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects invalid duplicate record statuses.');
}

$invalidReplayItems = $paymentItems;
$invalidReplayItems[0]['reversal_status'] = 'waiting-refund';
try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:7801',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
        'payable_amount' => 100,
        'settlement_amount' => 100,
        'settlement_currency' => 'KRW',
    ], $invalidReplayItems);
    sr_payment_runtime_assert(false, 'payment ledger should reject invalid duplicate item reversal statuses.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects invalid duplicate item reversal statuses.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:7801',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.download',
        'subject_id' => '7801',
        'payable_amount' => 100,
        'settlement_amount' => 100,
        'settlement_currency' => 'KRW',
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
        'settlement_currency' => 'KRW',
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
        'settlement_currency' => 'KRW',
    ], []);
    sr_payment_runtime_assert(false, 'payment ledger should reject overlong subject contract keys instead of truncating them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects overlong subject contract keys.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => str_repeat('d', 191),
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
        'settlement_currency' => 'KRW',
    ], []);
    sr_payment_runtime_assert(false, 'payment ledger should reject overlong dedupe keys instead of truncating them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects overlong dedupe keys.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:overlong-subject-id',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => str_repeat('s', 121),
        'settlement_currency' => 'KRW',
    ], []);
    sr_payment_runtime_assert(false, 'payment ledger should reject overlong subject ids instead of truncating them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects overlong subject ids.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:invalid-currency',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
        'settlement_currency' => 'KRWX',
    ], []);
    sr_payment_runtime_assert(false, 'payment ledger should reject invalid settlement currency codes instead of truncating them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects invalid settlement currency codes.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:missing-currency',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
    ], []);
    sr_payment_runtime_assert(false, 'payment ledger should reject missing settlement currency codes instead of defaulting them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects missing settlement currency codes.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:empty-currency',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
        'settlement_currency' => '',
    ], []);
    sr_payment_runtime_assert(false, 'payment ledger should reject explicitly empty settlement currency codes instead of defaulting them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects explicitly empty settlement currency codes.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:invalid-payment-kind',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
        'settlement_currency' => 'KRW',
        'payment_kind' => 'purchase-kind',
    ], []);
    sr_payment_runtime_assert(false, 'payment ledger should reject invalid payment kinds instead of defaulting them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects invalid payment kinds.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:empty-payment-kind',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
        'settlement_currency' => 'KRW',
        'payment_kind' => '',
    ], []);
    sr_payment_runtime_assert(false, 'payment ledger should reject explicitly empty payment kinds instead of defaulting them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects explicitly empty payment kinds.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:invalid-status',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
        'settlement_currency' => 'KRW',
        'status' => 'paid-now',
    ], []);
    sr_payment_runtime_assert(false, 'payment ledger should reject invalid record statuses instead of defaulting them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects invalid record statuses.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:empty-status',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
        'settlement_currency' => 'KRW',
        'status' => '',
    ], []);
    sr_payment_runtime_assert(false, 'payment ledger should reject explicitly empty record statuses instead of defaulting them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects explicitly empty record statuses.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:negative-amount',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
        'settlement_currency' => 'KRW',
        'payable_amount' => -1,
    ], []);
    sr_payment_runtime_assert(false, 'payment ledger should reject negative record amounts instead of clamping them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects negative record amounts.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:nonnumeric-amount',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
        'settlement_currency' => 'KRW',
        'payable_amount' => '100abc',
    ], []);
    sr_payment_runtime_assert(false, 'payment ledger should reject non-integer record amounts instead of casting them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects non-integer record amounts.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:overlong-item-reference',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
        'settlement_currency' => 'KRW',
    ], [
        [
            'item_kind' => 'asset_transaction',
            'owner_module' => 'point',
            'reference_type' => 'point_transaction',
            'reference_id' => str_repeat('r', 121),
            'amount' => -10,
            'currency_code' => 'KRW',
        ],
    ]);
    sr_payment_runtime_assert(false, 'payment ledger should reject overlong item reference ids instead of truncating them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects overlong item reference ids.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:nonnumeric-item-amount',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
        'settlement_currency' => 'KRW',
    ], [
        [
            'item_kind' => 'asset_transaction',
            'owner_module' => 'point',
            'reference_type' => 'point_transaction',
            'reference_id' => 'nonnumeric-item-amount',
            'amount' => '10abc',
            'currency_code' => 'KRW',
        ],
    ]);
    sr_payment_runtime_assert(false, 'payment ledger should reject non-integer item amounts instead of casting them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects non-integer item amounts.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:invalid-item-currency',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
        'settlement_currency' => 'KRW',
    ], [
        [
            'item_kind' => 'asset_transaction',
            'owner_module' => 'point',
            'reference_type' => 'point_transaction',
            'reference_id' => 'invalid-item-currency',
            'amount' => -10,
            'currency_code' => 'KRWX',
        ],
    ]);
    sr_payment_runtime_assert(false, 'payment ledger should reject invalid item currency codes instead of truncating them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects invalid item currency codes.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:invalid-reversal-status',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
        'settlement_currency' => 'KRW',
    ], [
        [
            'item_kind' => 'asset_transaction',
            'owner_module' => 'point',
            'reference_type' => 'point_transaction',
            'reference_id' => 'invalid-reversal-status',
            'amount' => -10,
            'currency_code' => 'KRW',
            'reversible' => true,
            'reversal_status' => 'waiting-refund',
        ],
    ]);
    sr_payment_runtime_assert(false, 'payment ledger should reject invalid item reversal statuses instead of defaulting them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects invalid item reversal statuses.');
}

try {
    sr_payment_ledger_record_payment($pdo, [
        'dedupe_key' => 'content.view:payment:7:empty-reversal-status',
        'account_id' => 7,
        'subject_module' => 'content',
        'subject_type' => 'content.view',
        'subject_id' => '7801',
        'settlement_currency' => 'KRW',
    ], [
        [
            'item_kind' => 'asset_transaction',
            'owner_module' => 'point',
            'reference_type' => 'point_transaction',
            'reference_id' => 'empty-reversal-status',
            'amount' => -10,
            'currency_code' => 'KRW',
            'reversible' => true,
            'reversal_status' => '',
        ],
    ]);
    sr_payment_runtime_assert(false, 'payment ledger should reject explicitly empty item reversal statuses instead of defaulting them.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects explicitly empty item reversal statuses.');
}
sr_payment_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_payment_records')->fetchColumn() === 1, 'invalid payment inputs should not leave extra records.');
sr_payment_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_payment_record_items')->fetchColumn() === 3, 'invalid payment inputs should not leave extra items.');

$partialRefundRecordId = sr_payment_ledger_record_payment($pdo, [
    'dedupe_key' => 'content.download:payment:17:8803',
    'account_id' => 17,
    'subject_module' => 'content',
    'subject_type' => 'content.download',
    'subject_id' => '8803',
    'payment_kind' => 'purchase',
    'payable_amount' => 100,
    'settlement_amount' => 60,
    'settlement_currency' => 'KRW',
], [
    [
        'item_kind' => 'coupon_redemption',
        'owner_module' => 'coupon',
        'reference_type' => 'coupon_redemption',
        'reference_id' => '1701',
        'amount' => -40,
        'currency_code' => 'KRW',
        'reversible' => true,
    ],
    [
        'item_kind' => 'asset_transaction',
        'owner_module' => 'point',
        'reference_type' => 'point_transaction',
        'reference_id' => '1702',
        'amount' => -60,
        'currency_code' => 'KRW',
        'reversible' => true,
    ],
    [
        'item_kind' => 'asset_access_log',
        'owner_module' => 'content',
        'reference_type' => 'content_asset_access_log',
        'reference_id' => '1703',
        'amount' => 60,
        'currency_code' => 'KRW',
        'reversible' => true,
    ],
    [
        'item_kind' => 'access_entitlement',
        'owner_module' => 'content',
        'reference_type' => 'content.access_entitlement',
        'reference_id' => 'content_file:8803:download',
        'amount' => 0,
        'currency_code' => '',
        'reversible' => true,
    ],
]);
$partialRefund = sr_payment_ledger_mark_item_references_reversed($pdo, 17, [
    [
        'item_kind' => 'asset_transaction',
        'owner_module' => 'point',
        'reference_type' => 'point_transaction',
        'reference_id' => '1702',
    ],
    [
        'item_kind' => 'asset_access_log',
        'owner_module' => 'content',
        'reference_type' => 'content_asset_access_log',
        'reference_id' => '1703',
    ],
    [
        'item_kind' => 'access_entitlement',
        'owner_module' => 'content',
        'reference_type' => 'content.access_entitlement',
        'reference_id' => 'content_file:8803:download',
    ],
], 'fixture partial refund');
sr_payment_runtime_assert((int) ($partialRefund['reversed_item_count'] ?? 0) === 3, 'payment ledger partial refund should mark matching items reversed.');
sr_payment_runtime_assert((array) ($partialRefund['refunded_record_ids'] ?? []) === [], 'payment ledger partial refund should not mark records refunded while coupon items remain open.');
sr_payment_runtime_assert((string) $pdo->query('SELECT status FROM sr_payment_records WHERE id = ' . (int) $partialRefundRecordId)->fetchColumn() === 'paid', 'payment ledger partial refund should keep the record paid.');
sr_payment_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_payment_record_items WHERE payment_record_id = " . (int) $partialRefundRecordId . " AND reversal_status = 'reversed'")->fetchColumn() === 3, 'payment ledger partial refund should persist reversed item statuses.');
sr_payment_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_payment_record_items WHERE payment_record_id = " . (int) $partialRefundRecordId . " AND item_kind = 'coupon_redemption' AND reversal_status = 'none'")->fetchColumn() === 1, 'payment ledger partial refund should preserve unreversed coupon items.');

$fullRefundRecordId = sr_payment_ledger_record_payment($pdo, [
    'dedupe_key' => 'content.view:payment:coupon:1801',
    'account_id' => 18,
    'subject_module' => 'content',
    'subject_type' => 'content.view',
    'subject_id' => '9901',
    'payment_kind' => 'purchase',
    'payable_amount' => 100,
    'settlement_amount' => 0,
    'settlement_currency' => 'KRW',
], [
    [
        'item_kind' => 'coupon_redemption',
        'owner_module' => 'coupon',
        'reference_type' => 'coupon_redemption',
        'reference_id' => '1801',
        'amount' => -100,
        'currency_code' => 'KRW',
        'reversible' => true,
    ],
    [
        'item_kind' => 'access_entitlement',
        'owner_module' => 'content',
        'reference_type' => 'content.access_entitlement',
        'reference_id' => 'content:9901:view',
        'amount' => 0,
        'currency_code' => '',
        'reversible' => true,
    ],
]);
$fullRefund = sr_payment_ledger_mark_item_references_reversed($pdo, 18, [[
    'item_kind' => 'coupon_redemption',
    'owner_module' => 'coupon',
    'reference_type' => 'coupon_redemption',
    'reference_id' => '1801',
]], 'fixture full coupon refund', false, ['access_entitlement']);
sr_payment_runtime_assert((int) ($fullRefund['reversed_item_count'] ?? 0) === 2, 'payment ledger full refund should mark every reversible item in the matched record reversed.');
sr_payment_runtime_assert((array) ($fullRefund['refunded_record_ids'] ?? []) === [$fullRefundRecordId], 'payment ledger full refund should report refunded records.');
$fullRefundRecord = sr_payment_runtime_row($pdo, 'SELECT status, description FROM sr_payment_records WHERE id = :id', ['id' => $fullRefundRecordId]);
sr_payment_runtime_assert((string) ($fullRefundRecord['status'] ?? '') === 'refunded', 'payment ledger full refund should mark the record refunded.');
sr_payment_runtime_assert((string) ($fullRefundRecord['description'] ?? '') === 'fixture full coupon refund', 'payment ledger full refund should preserve the refund reason.');
sr_payment_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_payment_record_items WHERE payment_record_id = " . (int) $fullRefundRecordId . " AND reversal_status = 'reversed'")->fetchColumn() === 2, 'payment ledger full refund should persist all reversed item statuses.');

$couponAccessOnlyRefundRecordId = sr_payment_ledger_record_payment($pdo, [
    'dedupe_key' => 'content.view:payment:mixed-coupon-access:1901',
    'account_id' => 19,
    'subject_module' => 'content',
    'subject_type' => 'content.view',
    'subject_id' => '9902',
    'payment_kind' => 'purchase',
    'payable_amount' => 100,
    'settlement_amount' => 60,
    'settlement_currency' => 'KRW',
], [
    [
        'item_kind' => 'coupon_redemption',
        'owner_module' => 'coupon',
        'reference_type' => 'coupon_redemption',
        'reference_id' => '1901',
        'amount' => -40,
        'currency_code' => 'KRW',
        'reversible' => true,
    ],
    [
        'item_kind' => 'asset_transaction',
        'owner_module' => 'point',
        'reference_type' => 'point_transaction',
        'reference_id' => '1902',
        'amount' => -60,
        'currency_code' => 'KRW',
        'reversible' => true,
    ],
    [
        'item_kind' => 'access_entitlement',
        'owner_module' => 'content',
        'reference_type' => 'content.access_entitlement',
        'reference_id' => 'content:9902:view',
        'amount' => 0,
        'currency_code' => '',
        'reversible' => true,
    ],
]);
$couponAccessOnlyRefund = sr_payment_ledger_mark_item_references_reversed($pdo, 19, [[
    'item_kind' => 'coupon_redemption',
    'owner_module' => 'coupon',
    'reference_type' => 'coupon_redemption',
    'reference_id' => '1901',
]], 'fixture coupon access refund', false, ['access_entitlement']);
sr_payment_runtime_assert((int) ($couponAccessOnlyRefund['reversed_item_count'] ?? 0) === 2, 'payment ledger coupon access refund should mark only coupon and access items reversed.');
sr_payment_runtime_assert((array) ($couponAccessOnlyRefund['refunded_record_ids'] ?? []) === [], 'payment ledger coupon access refund should not refund records while asset items remain open.');
sr_payment_runtime_assert((string) $pdo->query('SELECT status FROM sr_payment_records WHERE id = ' . (int) $couponAccessOnlyRefundRecordId)->fetchColumn() === 'paid', 'payment ledger coupon access refund should keep mixed records paid.');
sr_payment_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_payment_record_items WHERE payment_record_id = " . (int) $couponAccessOnlyRefundRecordId . " AND item_kind = 'asset_transaction' AND reversal_status = 'none'")->fetchColumn() === 1, 'payment ledger coupon access refund should preserve unreversed asset items.');

sr_payment_ledger_mark_cancelled($pdo, $paymentRecordId, 'fixture cancel');
$cancelled = sr_payment_runtime_row($pdo, 'SELECT status, description, cancelled_at FROM sr_payment_records WHERE id = :id', ['id' => $paymentRecordId]);
sr_payment_runtime_assert((string) ($cancelled['status'] ?? '') === 'cancelled', 'payment ledger should mark a payment record cancelled.');
sr_payment_runtime_assert((string) ($cancelled['description'] ?? '') === 'fixture cancel', 'payment ledger cancellation should preserve the reason.');
sr_payment_runtime_assert((string) ($cancelled['cancelled_at'] ?? '') !== '', 'payment ledger cancellation should store cancelled_at.');
sr_payment_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_payment_record_items WHERE payment_record_id = " . (int) $paymentRecordId . " AND reversal_status = 'pending'")->fetchColumn() === 3, 'payment ledger cancellation should mark reversible items pending.');

$reversedItems = sr_payment_ledger_mark_record_items_reversal_status($pdo, $paymentRecordId, 'reversed');
sr_payment_runtime_assert($reversedItems === 3, 'payment ledger should update reversible item reversal statuses.');
sr_payment_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_payment_record_items WHERE payment_record_id = " . (int) $paymentRecordId . " AND reversal_status = 'reversed'")->fetchColumn() === 3, 'payment ledger should persist item reversal status changes.');

try {
    sr_payment_ledger_mark_record_items_reversal_status($pdo, $paymentRecordId, 'waiting-refund');
    sr_payment_runtime_assert(false, 'payment ledger should reject invalid reversal status updates.');
} catch (InvalidArgumentException) {
    sr_payment_runtime_assert(true, 'payment ledger rejects invalid reversal status updates.');
}
sr_payment_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_payment_record_items WHERE payment_record_id = " . (int) $paymentRecordId . " AND reversal_status = 'reversed'")->fetchColumn() === 3, 'invalid reversal status updates should not mutate item statuses.');

sr_payment_ledger_mark_cancelled($pdo, $paymentRecordId, 'fixture cancel again');
sr_payment_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_payment_record_items WHERE payment_record_id = " . (int) $paymentRecordId . " AND reversal_status = 'pending'")->fetchColumn() === 0, 'payment ledger repeated cancellation should not move reversed items back to pending.');
sr_payment_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_payment_record_items WHERE payment_record_id = " . (int) $paymentRecordId . " AND reversal_status = 'reversed'")->fetchColumn() === 3, 'payment ledger repeated cancellation should preserve reversed item statuses.');

$otherAccountRecordId = sr_payment_ledger_record_payment($pdo, [
    'dedupe_key' => 'content.view:payment:77:7802',
    'account_id' => 77,
    'subject_module' => 'content',
    'subject_type' => 'content.view',
    'subject_id' => '7802',
    'payment_kind' => 'purchase',
    'payable_amount' => 100,
    'settlement_amount' => 100,
    'settlement_currency' => 'KRW',
], [
    [
        'item_kind' => 'access_entitlement',
        'owner_module' => 'content',
        'reference_type' => 'content.access',
        'reference_id' => 'content.view:7802:account:77',
        'amount' => 0,
        'currency_code' => '',
        'reversible' => true,
        'snapshot' => [
            'source_reference' => 'content:view:7802:account:77:intent:abc',
        ],
    ],
]);
sr_payment_runtime_assert($otherAccountRecordId > 0, 'payment ledger should create a fixture record for another account.');

$exporter = require $root . '/modules/payment_ledger/privacy-export.php';
$export = $exporter($pdo, 7);
sr_payment_runtime_assert(count((array) ($export['payment_records'] ?? [])) === 1, 'payment ledger privacy export should include account records.');
sr_payment_runtime_assert(count((array) ($export['payment_record_items'] ?? [])) === 3, 'payment ledger privacy export should include record items.');

$cleanup = require $root . '/modules/payment_ledger/privacy-cleanup.php';
$cleanupResult = $cleanup($pdo, 7, ['event_type' => 'member.anonymized']);
sr_payment_runtime_assert((int) ($cleanupResult['payment_records'] ?? 0) === 1, 'payment ledger privacy cleanup should anonymize account records.');
sr_payment_runtime_assert((int) ($cleanupResult['payment_record_items'] ?? 0) === 1, 'payment ledger privacy cleanup should anonymize access item account references.');
sr_payment_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_payment_records WHERE account_id = 0')->fetchColumn() === 1, 'payment ledger privacy cleanup should clear account_id.');
$anonymizedAccessItem = sr_payment_runtime_row(
    $pdo,
    "SELECT reference_id, snapshot_json
     FROM sr_payment_record_items
     WHERE payment_record_id = :payment_record_id
       AND item_kind = 'access_entitlement'
     LIMIT 1",
    ['payment_record_id' => $paymentRecordId]
);
sr_payment_runtime_assert((string) ($anonymizedAccessItem['reference_id'] ?? '') === 'content.view:7801:account:anonymous', 'payment ledger privacy cleanup should remove raw account ids from access item references.');
$anonymizedAccessSnapshot = json_decode((string) ($anonymizedAccessItem['snapshot_json'] ?? ''), true);
sr_payment_runtime_assert(is_array($anonymizedAccessSnapshot) && (string) ($anonymizedAccessSnapshot['source_reference'] ?? '') === 'content:view:7801:account:anonymous:intent:abc', 'payment ledger privacy cleanup should remove raw account ids from access item snapshots.');
sr_payment_runtime_assert(is_array($anonymizedAccessSnapshot) && (string) ($anonymizedAccessSnapshot['unrelated_reference'] ?? '') === 'content:view:7801:account:77:intent:abc', 'payment ledger privacy cleanup should not redact similar account id prefixes in snapshots.');
$otherAccountAccessItem = sr_payment_runtime_row(
    $pdo,
    "SELECT r.account_id, i.reference_id, i.snapshot_json
     FROM sr_payment_records r
     INNER JOIN sr_payment_record_items i ON i.payment_record_id = r.id
     WHERE r.id = :payment_record_id
     LIMIT 1",
    ['payment_record_id' => $otherAccountRecordId]
);
sr_payment_runtime_assert((int) ($otherAccountAccessItem['account_id'] ?? 0) === 77, 'payment ledger privacy cleanup should not anonymize other account records with similar ids.');
sr_payment_runtime_assert((string) ($otherAccountAccessItem['reference_id'] ?? '') === 'content.view:7802:account:77', 'payment ledger privacy cleanup should not redact other account item references with similar ids.');

$brokenCleanupPdo = new PDO('sqlite::memory:');
$brokenCleanupPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$brokenCleanupPdo->beginTransaction();
try {
    $cleanup($brokenCleanupPdo, 7, ['event_type' => 'member.anonymized']);
    sr_payment_runtime_assert(false, 'payment ledger privacy cleanup should propagate storage failures.');
} catch (Throwable) {
    sr_payment_runtime_assert($brokenCleanupPdo->inTransaction(), 'payment ledger privacy cleanup should keep the caller transaction active after rolling back its savepoint.');
    $brokenCleanupPdo->rollBack();
}

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
sr_payment_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_payment_record_items WHERE payment_record_id = ' . (int) $paymentRecordId)->fetchColumn() === 3, 'payment ledger late replay should not append items to anonymized duplicate records.');

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo "payment ledger runtime checks completed.\n";
