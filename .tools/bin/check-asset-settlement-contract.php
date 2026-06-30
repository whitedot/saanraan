#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/helpers.php';
require_once $root . '/modules/member/helpers/assets.php';
require_once $root . '/modules/content/helpers.php';
require_once $root . '/modules/community/helpers.php';

$errors = [];

function sr_asset_settlement_check_contains(string $file, array $needles): void
{
    global $errors;

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read required contract document: ' . $file;
        return;
    }

    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            $errors[] = $file . ' must document asset settlement contract marker: ' . $needle;
        }
    }
}

function sr_asset_settlement_check_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

function sr_asset_settlement_check_forbidden_exchange_refs(array $files): void
{
    global $errors;

    $patterns = [
        '/\bsr_asset_exchange_policies\b/' => 'asset exchange policy table/helper collection',
        '/\bsr_asset_exchange_logs\b/' => 'asset exchange execution log table',
        '/\bsr_asset_exchange_policy\s*\(/' => 'asset exchange policy helper',
    ];

    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }

        $contents = file_get_contents($file);
        if (!is_string($contents)) {
            $errors[] = 'cannot read asset settlement boundary file: ' . $file;
            continue;
        }

        foreach ($patterns as $pattern => $label) {
            if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) !== false && $matches[0] !== []) {
                foreach ($matches[0] as $match) {
                    $line = substr_count(substr($contents, 0, (int) $match[1]), "\n") + 1;
                    $errors[] = $file . ':' . $line . ' must not read ' . $label . ' from purchase_power settlement code.';
                }
            }
        }
    }
}

function sr_asset_settlement_check_runtime_fixture(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('CREATE TABLE sr_site_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT NOT NULL, value_type TEXT NOT NULL)');
    $pdo->exec("INSERT INTO sr_site_settings (setting_key, setting_value, value_type) VALUES ('site.default_currency', 'USD', 'string')");
    sr_clear_site_settings_cache();
    $communityDefaults = sr_community_normalize_settings([], null, $pdo);
    foreach (['write_charge', 'message_charge', 'comment_charge', 'paid_read', 'paid_attachment_download'] as $communityChargePrefix) {
        sr_asset_settlement_check_assert(
            (string) ($communityDefaults[$communityChargePrefix . '_settlement_currency'] ?? '') === 'USD',
            'community unset settlement currency default should follow site.default_currency for ' . $communityChargePrefix . '.'
        );
    }

    $assets = [
        'point' => [
            'label' => 'Point',
            'unit_label' => 'P',
            'purchase_power' => [
                'asset_units' => 1,
                'settlement_units' => 10,
                'settlement_currency' => 'KRW',
            ],
        ],
        'reward' => [
            'label' => 'Reward',
            'unit_label' => 'R',
            'purchase_power' => [
                'asset_units' => 1,
                'settlement_units' => 1,
                'settlement_currency' => 'KRW',
            ],
        ],
        'usd_credit' => [
            'label' => 'USD credit',
            'unit_label' => 'U',
            'purchase_power' => [
                'asset_units' => 1,
                'settlement_units' => 1,
                'settlement_currency' => 'USD',
            ],
        ],
    ];
    $balances = [
        'point' => 100,
        'reward' => 1000,
        'usd_credit' => 1000,
    ];
    $balanceFunction = static function (PDO $pdo, string $assetModule) use ($balances): int {
        return (int) ($balances[$assetModule] ?? 0);
    };

    $zero = sr_member_asset_settlement_plan($pdo, $assets, $balanceFunction, ['point', 'reward'], 0, 'KRW');
    sr_asset_settlement_check_assert((bool) ($zero['ok'] ?? false), 'zero settlement amount should be accepted without allocation.');
    sr_asset_settlement_check_assert(($zero['allocations'] ?? null) === [], 'zero settlement amount should not allocate asset rows.');

    $zeroUnknownCurrency = sr_member_asset_settlement_plan($pdo, $assets, $balanceFunction, ['point'], 0, 'XXX');
    sr_asset_settlement_check_assert(!((bool) ($zeroUnknownCurrency['ok'] ?? false)), 'zero settlement amount with unknown stored currency should fail closed.');
    sr_asset_settlement_check_assert(str_contains((string) ($zeroUnknownCurrency['message'] ?? ''), 'Unknown settlement currency'), 'zero unknown currency failure should be explicit.');
    sr_asset_settlement_check_assert(
        str_contains(sr_member_asset_settlement_config_error_message($zeroUnknownCurrency, '콘텐츠를 열람할 수 없습니다.'), '정산 기준 통화 설정'),
        'zero unknown currency failure should be exposed as configuration error message.'
    );

    $priorityExact = sr_member_asset_settlement_plan($pdo, $assets, $balanceFunction, ['point', 'reward'], 1000, 'KRW');
    sr_asset_settlement_check_assert((bool) ($priorityExact['ok'] ?? false), 'priority settlement plan should cover exact amount with the first eligible asset.');
    sr_asset_settlement_check_assert(count($priorityExact['allocations'] ?? []) === 1, 'priority settlement plan should not spend lower-priority assets when the first asset covers exactly.');
    sr_asset_settlement_check_assert((string) ($priorityExact['allocations'][0]['asset_module'] ?? '') === 'point', 'priority settlement plan should spend point before reward.');
    sr_asset_settlement_check_assert((int) ($priorityExact['allocations'][0]['amount'] ?? 0) === 100, 'priority settlement plan should spend only the exact point amount.');

    $exact = sr_member_asset_settlement_plan($pdo, $assets, $balanceFunction, ['point', 'reward'], 1005, 'KRW');
    sr_asset_settlement_check_assert((bool) ($exact['ok'] ?? false), 'mixed settlement plan should cover exact amount with secondary asset.');
    sr_asset_settlement_check_assert(count($exact['allocations'] ?? []) === 2, 'mixed settlement plan should allocate point and reward rows.');
    sr_asset_settlement_check_assert((int) ($exact['allocations'][0]['settlement_amount'] ?? 0) === 1000, 'point allocation should cover only exact 10 KRW steps.');
    sr_asset_settlement_check_assert((int) ($exact['allocations'][0]['amount'] ?? 0) === 100, 'point allocation should spend 100 point units.');
    sr_asset_settlement_check_assert((int) ($exact['allocations'][1]['settlement_amount'] ?? 0) === 5, 'reward allocation should absorb the exact remainder.');
    sr_asset_settlement_check_assert((string) ($exact['allocations'][0]['purchase_power_snapshot']['rounding_policy_version'] ?? '') === 'asset_settlement_rounding_v1', 'purchase power snapshot should include rounding policy version.');

    $overpay = sr_member_asset_settlement_plan($pdo, $assets, $balanceFunction, ['point'], 1005, 'KRW');
    sr_asset_settlement_check_assert(!((bool) ($overpay['ok'] ?? false)), 'single stepped asset must not ceil overpay an inexact settlement amount.');
    sr_asset_settlement_check_assert(($overpay['allocations'] ?? null) === [], 'failed inexact settlement must not return partial allocations.');
    sr_asset_settlement_check_assert((int) ($overpay['remaining_settlement_amount'] ?? 0) === 5, 'inexact settlement should report the uncovered remainder.');

    $lowBalances = [
        'point' => 100,
        'reward' => 100,
    ];
    $lowBalanceFunction = static function (PDO $pdo, string $assetModule) use ($lowBalances): int {
        return (int) ($lowBalances[$assetModule] ?? 0);
    };
    $insufficient = sr_member_asset_settlement_plan($pdo, $assets, $lowBalanceFunction, ['point', 'reward'], 1600, 'KRW');
    sr_asset_settlement_check_assert(!((bool) ($insufficient['ok'] ?? false)), 'insufficient balance settlement plan should fail.');
    sr_asset_settlement_check_assert((int) ($insufficient['remaining_settlement_amount'] ?? 0) === 500, 'insufficient balance plan should report uncovered settlement remainder.');
    $shortage = sr_member_asset_settlement_shortage($pdo, $assets, $lowBalanceFunction, ['point', 'reward'], 1600, 'KRW');
    sr_asset_settlement_check_assert((string) ($shortage['asset_module'] ?? '') === 'reward', 'shortage helper should report the asset that cannot cover the remaining settlement amount.');
    sr_asset_settlement_check_assert((int) ($shortage['amount'] ?? 0) === 500, 'shortage helper should report the missing asset units.');
    sr_asset_settlement_check_assert((int) ($shortage['settlement_amount'] ?? 0) === 500, 'shortage helper should report the missing settlement amount.');

    $fractionalAssets = [
        'half_point' => [
            'label' => 'Half Point',
            'unit_label' => 'HP',
            'purchase_power' => [
                'asset_units' => 2,
                'settlement_units' => 1,
                'settlement_currency' => 'KRW',
            ],
        ],
        'half_reward' => [
            'label' => 'Half Reward',
            'unit_label' => 'HR',
            'purchase_power' => [
                'asset_units' => 2,
                'settlement_units' => 1,
                'settlement_currency' => 'KRW',
            ],
        ],
    ];
    $fractionalBalances = [
        'half_point' => 1,
        'half_reward' => 1,
    ];
    $fractional = sr_member_asset_settlement_plan(
        $pdo,
        $fractionalAssets,
        static function (PDO $pdo, string $assetModule) use ($fractionalBalances): int {
            return (int) ($fractionalBalances[$assetModule] ?? 0);
        },
        ['half_point', 'half_reward'],
        1,
        'KRW'
    );
    sr_asset_settlement_check_assert((bool) ($fractional['ok'] ?? false), 'fractional settlement carry should allow two half-value assets to cover one settlement unit.');
    sr_asset_settlement_check_assert(count($fractional['allocations'] ?? []) === 2, 'fractional settlement carry should keep both asset rows.');
    sr_asset_settlement_check_assert((int) ($fractional['allocations'][0]['amount'] ?? 0) === 1, 'first fractional asset should spend one asset unit.');
    sr_asset_settlement_check_assert((int) ($fractional['allocations'][0]['settlement_amount'] ?? -1) === 0, 'first fractional asset should carry sub-min-unit settlement value without rounding up.');
    sr_asset_settlement_check_assert((int) ($fractional['allocations'][1]['amount'] ?? 0) === 1, 'second fractional asset should spend one asset unit.');
    sr_asset_settlement_check_assert((int) ($fractional['allocations'][1]['settlement_amount'] ?? 0) === 1, 'second fractional asset should close the carried settlement unit.');
    sr_asset_settlement_check_assert((int) ($fractional['allocations'][0]['purchase_power_snapshot']['fractional_carry_numerator'] ?? -1) === 1, 'fractional carry snapshot should preserve the carried numerator.');
    sr_asset_settlement_check_assert((int) ($fractional['allocations'][0]['purchase_power_snapshot']['fractional_carry_denominator'] ?? 0) === 2, 'fractional carry snapshot should preserve the carry denominator.');
    sr_asset_settlement_check_assert((string) ($fractional['allocations'][0]['purchase_power_snapshot']['snapshot_schema_version'] ?? '') === 'asset_settlement_snapshot_v1', 'purchase power snapshot should include schema version.');
    $fractionalShortage = sr_member_asset_settlement_shortage(
        $pdo,
        $fractionalAssets,
        static function (PDO $pdo, string $assetModule) use ($fractionalBalances): int {
            return (int) ($fractionalBalances[$assetModule] ?? 0);
        },
        ['half_point', 'half_reward'],
        1,
        'KRW'
    );
    sr_asset_settlement_check_assert($fractionalShortage === [], 'fractional settlement carry should not be reported as a balance shortage.');

    $pdo->exec('CREATE TABLE sr_content_asset_access_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        content_id INTEGER NOT NULL,
        account_id INTEGER NOT NULL,
        asset_module TEXT NOT NULL,
        transaction_id INTEGER NOT NULL DEFAULT 0,
        reference_type TEXT NOT NULL,
        reference_id TEXT NOT NULL,
        access_kind TEXT NOT NULL,
        charge_policy TEXT NOT NULL,
        amount INTEGER NOT NULL,
        settlement_amount INTEGER NOT NULL,
        settlement_currency TEXT NOT NULL,
        purchase_power_snapshot_json TEXT NOT NULL,
        settlement_kind TEXT NOT NULL,
        snapshot_schema_version TEXT NOT NULL,
        rounding_policy_version TEXT NOT NULL,
        log_status TEXT NOT NULL,
        group_policy_snapshot_json TEXT NOT NULL,
        dedupe_key TEXT NOT NULL UNIQUE,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_content_asset_action_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        content_id INTEGER NOT NULL,
        account_id INTEGER NOT NULL,
        asset_module TEXT NOT NULL,
        transaction_id INTEGER NOT NULL DEFAULT 0,
        reference_type TEXT NOT NULL,
        reference_id TEXT NOT NULL,
        action_key TEXT NOT NULL,
        direction TEXT NOT NULL,
        amount INTEGER NOT NULL,
        settlement_amount INTEGER NOT NULL,
        settlement_currency TEXT NOT NULL,
        purchase_power_snapshot_json TEXT NOT NULL,
        settlement_kind TEXT NOT NULL,
        snapshot_schema_version TEXT NOT NULL,
        rounding_policy_version TEXT NOT NULL,
        log_status TEXT NOT NULL,
        group_policy_snapshot_json TEXT NOT NULL,
        dedupe_key TEXT NOT NULL UNIQUE,
        created_at TEXT NOT NULL
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
        charge_policy TEXT NOT NULL,
        amount INTEGER NOT NULL,
        settlement_amount INTEGER NOT NULL,
        settlement_currency TEXT NOT NULL,
        purchase_power_snapshot_json TEXT NOT NULL,
        settlement_kind TEXT NOT NULL,
        snapshot_schema_version TEXT NOT NULL,
        rounding_policy_version TEXT NOT NULL,
        log_status TEXT NOT NULL,
        group_policy_snapshot_json TEXT NOT NULL,
        dedupe_key TEXT NOT NULL UNIQUE,
        created_at TEXT NOT NULL
    )');
    $rowByDedupe = static function (PDO $pdo, string $table, string $dedupeKey): array {
        $stmt = $pdo->prepare('SELECT * FROM ' . $table . ' WHERE dedupe_key = :dedupe_key LIMIT 1');
        $stmt->execute(['dedupe_key' => $dedupeKey]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : [];
    };
    $pdo->exec('CREATE TABLE sr_asset_runtime_transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        surface TEXT NOT NULL,
        asset_module TEXT NOT NULL,
        amount INTEGER NOT NULL
    )');
    $countWhere = static function (PDO $pdo, string $table, string $where = '1 = 1', array $params = []): int {
        $stmt = $pdo->prepare('SELECT COUNT(*) AS row_count FROM ' . $table . ' WHERE ' . $where);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return is_array($row) ? (int) ($row['row_count'] ?? 0) : 0;
    };
    $insertRuntimeTransaction = static function (PDO $pdo, string $surface, string $assetModule, int $amount): int {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_asset_runtime_transactions (surface, asset_module, amount)
             VALUES (:surface, :asset_module, :amount)'
        );
        $stmt->execute([
            'surface' => $surface,
            'asset_module' => $assetModule,
            'amount' => $amount,
        ]);

        return (int) $pdo->lastInsertId();
    };
    $assertMultiLedgerRollback = static function (string $surface, string $table, callable $insertPlaceholder, callable $completePlaceholder) use ($pdo, $countWhere, $insertRuntimeTransaction): void {
        $firstKey = $surface . ':rollback:first';
        $secondKey = $surface . ':rollback:second';
        try {
            $pdo->beginTransaction();
            $insertPlaceholder($firstKey, 'point', 10);
            $insertPlaceholder($secondKey, 'reward', 5);
            $firstTransactionId = $insertRuntimeTransaction($pdo, $surface, 'point', -10);
            $completePlaceholder($firstKey, $firstTransactionId);
            throw new RuntimeException('forced multi-ledger rollback fixture');
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }

        sr_asset_settlement_check_assert(
            $countWhere($pdo, $table, 'dedupe_key IN (:first_key, :second_key)', ['first_key' => $firstKey, 'second_key' => $secondKey]) === 0,
            $surface . ' multi-ledger rollback should remove all pending/completed placeholder rows.'
        );
        sr_asset_settlement_check_assert(
            $countWhere($pdo, 'sr_asset_runtime_transactions', 'surface = :surface', ['surface' => $surface]) === 0,
            $surface . ' multi-ledger rollback should remove already-created runtime transaction rows.'
        );

        $successFirstKey = $surface . ':commit:first';
        $successSecondKey = $surface . ':commit:second';
        $pdo->beginTransaction();
        $insertPlaceholder($successFirstKey, 'point', 10);
        $insertPlaceholder($successSecondKey, 'reward', 5);
        $completePlaceholder($successFirstKey, $insertRuntimeTransaction($pdo, $surface, 'point', -10));
        $completePlaceholder($successSecondKey, $insertRuntimeTransaction($pdo, $surface, 'reward', -5));
        $pdo->commit();

        sr_asset_settlement_check_assert(
            $countWhere($pdo, $table, 'dedupe_key IN (:first_key, :second_key) AND log_status = :status', [
                'first_key' => $successFirstKey,
                'second_key' => $successSecondKey,
                'status' => 'completed',
            ]) === 2,
            $surface . ' multi-ledger commit should complete both placeholder rows together.'
        );
        sr_asset_settlement_check_assert(
            $countWhere($pdo, 'sr_asset_runtime_transactions', 'surface = :surface', ['surface' => $surface]) === 2,
            $surface . ' multi-ledger commit should keep both runtime transaction rows.'
        );
    };
    $assertMultiLedgerRollback(
        'content-access',
        'sr_content_asset_access_logs',
        static function (string $dedupeKey, string $assetModule, int $amount) use ($pdo): void {
            sr_content_insert_asset_access_placeholder($pdo, 77, 7, $assetModule, $amount, 'every_view', $dedupeKey, 'content.view', '77', 'view', '{}', $amount, 'KRW', '{"asset_units":1,"settlement_units":1,"settlement_currency":"KRW"}');
        },
        static function (string $dedupeKey, int $transactionId) use ($pdo): void {
            sr_content_update_asset_access_transaction($pdo, $dedupeKey, $transactionId);
        }
    );
    $assertMultiLedgerRollback(
        'content-action',
        'sr_content_asset_action_logs',
        static function (string $dedupeKey, string $assetModule, int $amount) use ($pdo): void {
            sr_content_insert_asset_action_placeholder($pdo, 77, 7, $assetModule, 'use', $amount, $dedupeKey, '{}', $amount, 'KRW', '{"asset_units":1,"settlement_units":1,"settlement_currency":"KRW"}');
        },
        static function (string $dedupeKey, int $transactionId) use ($pdo): void {
            sr_content_update_asset_action_transaction($pdo, $dedupeKey, $transactionId);
        }
    );
    $assertMultiLedgerRollback(
        'community-asset',
        'sr_community_asset_logs',
        static function (string $dedupeKey, string $assetModule, int $amount) use ($pdo): void {
            sr_community_insert_asset_log_placeholder($pdo, [
                'account_id' => 7,
                'asset_module' => $assetModule,
                'reference_type' => 'community.post',
                'reference_id' => '77',
                'subject_type' => 'community.post',
                'subject_id' => 77,
                'event_key' => 'post_read',
                'direction' => 'use',
                'charge_policy' => 'every_view',
                'amount' => $amount,
                'settlement_amount' => $amount,
                'settlement_currency' => 'KRW',
                'purchase_power_snapshot_json' => '{"asset_units":1,"settlement_units":1,"settlement_currency":"KRW"}',
                'group_policy_snapshot_json' => '{}',
                'dedupe_key' => $dedupeKey,
            ]);
        },
        static function (string $dedupeKey, int $transactionId) use ($pdo): void {
            sr_community_update_asset_log_transaction($pdo, $dedupeKey, $transactionId);
        }
    );
    $fractionalSnapshotJson = sr_content_asset_purchase_power_snapshot_json((array) ($fractional['allocations'][0]['purchase_power_snapshot'] ?? []));
    sr_asset_settlement_check_assert(
        sr_content_insert_asset_access_placeholder($pdo, 77, 7, 'half_point', 1, 'every_download', 'content:fractional:access', 'content.download', '9', 'download', '{}', 0, 'KRW', $fractionalSnapshotJson),
        'content asset access placeholder fixture should insert a fractional paid row.'
    );
    sr_asset_settlement_check_assert(
        !sr_content_insert_asset_access_placeholder($pdo, 77, 7, 'half_point', 1, 'every_download', 'content:fractional:access', 'content.download', '9', 'download', '{}', 0, 'KRW', $fractionalSnapshotJson),
        'content asset access placeholder fixture should ignore duplicate fractional claims.'
    );
    $contentAccessRow = $rowByDedupe($pdo, 'sr_content_asset_access_logs', 'content:fractional:access');
    sr_asset_settlement_check_assert((string) ($contentAccessRow['settlement_kind'] ?? '') === 'paid', 'content fractional carry access row with settlement_amount=0 should remain paid when snapshot exists.');
    sr_asset_settlement_check_assert((string) ($contentAccessRow['rounding_policy_version'] ?? '') === 'asset_settlement_rounding_v1', 'content fractional carry access row should store rounding policy version.');
    sr_asset_settlement_check_assert(str_contains((string) ($contentAccessRow['purchase_power_snapshot_json'] ?? ''), 'fractional_carry_numerator'), 'content fractional carry access row should preserve carry snapshot JSON.');

    sr_asset_settlement_check_assert(
        sr_content_insert_asset_action_placeholder($pdo, 77, 7, 'half_point', 'use', 1, 'content:fractional:action', '{}', 0, 'KRW', $fractionalSnapshotJson),
        'content asset action placeholder fixture should insert a fractional paid row.'
    );
    $contentActionRow = $rowByDedupe($pdo, 'sr_content_asset_action_logs', 'content:fractional:action');
    sr_asset_settlement_check_assert((string) ($contentActionRow['settlement_kind'] ?? '') === 'paid', 'content fractional carry action row with settlement_amount=0 should remain paid when snapshot exists.');

    sr_asset_settlement_check_assert(
        sr_community_insert_asset_log_placeholder($pdo, [
            'account_id' => 7,
            'asset_module' => 'half_point',
            'reference_type' => 'community.attachment',
            'reference_id' => '9',
            'subject_type' => 'community.attachment',
            'subject_id' => 9,
            'event_key' => 'attachment_download',
            'direction' => 'use',
            'charge_policy' => 'every_download',
            'amount' => 1,
            'settlement_amount' => 0,
            'settlement_currency' => 'KRW',
            'purchase_power_snapshot_json' => sr_community_asset_purchase_power_snapshot_json((array) ($fractional['allocations'][0]['purchase_power_snapshot'] ?? [])),
            'group_policy_snapshot_json' => '{}',
            'dedupe_key' => 'community:fractional:asset',
        ]),
        'community asset placeholder fixture should insert a fractional paid row.'
    );
    $communityAssetRow = $rowByDedupe($pdo, 'sr_community_asset_logs', 'community:fractional:asset');
    sr_asset_settlement_check_assert((string) ($communityAssetRow['settlement_kind'] ?? '') === 'paid', 'community fractional carry row with settlement_amount=0 should remain paid when snapshot exists.');
    sr_asset_settlement_check_assert((string) ($communityAssetRow['snapshot_schema_version'] ?? '') === 'asset_settlement_snapshot_v1', 'community fractional carry row should store snapshot schema version.');

    $contentKrwFingerprint = sr_content_asset_confirmation_fingerprint('view', 'every_view', 'point', 100, ['point' => 100], '{}', 'KRW');
    $contentUsdFingerprint = sr_content_asset_confirmation_fingerprint('view', 'every_view', 'point', 100, ['point' => 100], '{}', 'USD');
    sr_asset_settlement_check_assert($contentKrwFingerprint !== $contentUsdFingerprint, 'content confirmation fingerprint should include settlement currency.');
    $contentDedupeKey = sr_content_asset_access_dedupe_key_for_policy('every_view', 'content.view', 'point', 7, 11, 'view', str_repeat('a', 32), 100, 'KRW');
    sr_asset_settlement_check_assert(str_contains($contentDedupeKey, ':100:KRW:'), 'content non-once asset dedupe key should include stable settlement amount and currency.');

    $communityKrwFingerprint = sr_community_asset_confirmation_fingerprint('post_read', 'community.post', 'every_view', 'point', 100, ['point' => 100], '{}', 'KRW');
    $communityUsdFingerprint = sr_community_asset_confirmation_fingerprint('post_read', 'community.post', 'every_view', 'point', 100, ['point' => 100], '{}', 'USD');
    sr_asset_settlement_check_assert($communityKrwFingerprint !== $communityUsdFingerprint, 'community confirmation fingerprint should include settlement currency.');

    $currencyMismatch = sr_member_asset_settlement_plan($pdo, $assets, $balanceFunction, ['usd_credit'], 10, 'KRW');
    sr_asset_settlement_check_assert(!((bool) ($currencyMismatch['ok'] ?? false)), 'asset settlement currency mismatch should fail closed.');
    sr_asset_settlement_check_assert(str_contains((string) ($currencyMismatch['message'] ?? ''), 'currency'), 'currency mismatch failure should explain the currency problem.');

    $unknownCurrency = sr_member_asset_settlement_plan($pdo, $assets, $balanceFunction, ['point'], 10, 'XXX');
    sr_asset_settlement_check_assert(!((bool) ($unknownCurrency['ok'] ?? false)), 'unknown settlement currency should fail closed.');
    sr_asset_settlement_check_assert(str_contains((string) ($unknownCurrency['message'] ?? ''), 'Unknown settlement currency'), 'unknown currency failure should be explicit.');
    sr_asset_settlement_check_assert(sr_content_asset_settlement_currency($pdo, ['asset_settlement_currency' => 'XXX']) === 'XXX', 'content settlement currency helper should preserve unknown stored currency for fail-closed runtime validation.');
    sr_asset_settlement_check_assert(sr_community_asset_settlement_currency($pdo, ['asset_settlement_currency' => 'XXX']) === 'XXX', 'community settlement currency helper should preserve unknown stored currency for fail-closed runtime validation.');

    $unknownAssetCurrency = sr_member_asset_settlement_plan(
        $pdo,
        [
            'bad_credit' => [
                'label' => 'Bad credit',
                'unit_label' => 'B',
                'purchase_power' => [
                    'asset_units' => 1,
                    'settlement_units' => 1,
                    'settlement_currency' => 'ZZZ',
                ],
            ],
        ],
        static function (PDO $pdo, string $assetModule): int {
            return 1000;
        },
        ['bad_credit'],
        10,
        'KRW'
    );
    sr_asset_settlement_check_assert(!((bool) ($unknownAssetCurrency['ok'] ?? false)), 'unknown asset purchase power currency should fail closed.');
    sr_asset_settlement_check_assert(str_contains((string) ($unknownAssetCurrency['message'] ?? ''), 'Unknown asset settlement currency'), 'unknown asset purchase power currency failure should be explicit.');

    $invalidContractFailed = false;
    try {
        sr_member_asset_purchase_power_from_contract($pdo, [
            'purchase_power' => [
                'asset_units' => 1,
                'settlement_units' => 1,
                'settlement_currency' => 'ZZZ',
            ],
        ]);
    } catch (InvalidArgumentException $exception) {
        $invalidContractFailed = str_contains($exception->getMessage(), 'Unknown asset purchase power settlement currency');
    }
    sr_asset_settlement_check_assert($invalidContractFailed, 'invalid purchase_power settlement currency must surface as setup error instead of falling back.');

    $contentModules = sr_content_asset_module_keys_from_value(['deposit', 'unknown', 'point', 'reward', 'point']);
    sr_asset_settlement_check_assert($contentModules === ['point', 'reward', 'deposit'], 'content asset module input should normalize to deterministic deduction order.');
    sr_asset_settlement_check_assert(sr_content_asset_module_value_from_keys(['deposit', 'point', 'reward', 'point']) === 'point,reward,deposit', 'content asset module value should serialize in deterministic deduction order.');

    $communityModules = sr_community_asset_module_keys_from_value('deposit point reward point unknown', true);
    sr_asset_settlement_check_assert($communityModules === ['point', 'reward', 'deposit'], 'community asset module input should normalize to deterministic deduction order.');
    sr_asset_settlement_check_assert(sr_community_asset_module_value_from_keys(['deposit', 'point', 'reward', 'point'], true) === 'point,reward,deposit', 'community asset module value should serialize in deterministic deduction order.');
}

sr_asset_settlement_check_contains('docs/core-decisions.md', [
    '멱등 key는 회원, 소비 모듈, `reference_type`, `reference_id`, 기준금액, 기준 통화, 클라이언트 요청 토큰처럼 재시도 사이에 변하지 않는 입력으로만 만들고',
    '클라이언트 요청 토큰은 HTTP attempt마다 새로 만들지 않고 구매 의도(intent), 즉 확인 화면 렌더 시점에 1회 생성해 확정 POST 재시도 전체에서 동일하게 운반합니다',
    '실행 트랜잭션에 들어가면 원장 row를 잠그기 전에 안정 입력 기반 dedupe key를 가진 claim row를 먼저 insert해야 하며',
    '이 key에는 DB unique 제약을 둡니다',
    '동시 중복 요청은 원장 lock이 아니라 이 unique claim 충돌에서 `processing` 또는 저장된 `completed` 결과로 흡수하고',
    '성공 결과만 claim row와 함께 커밋해 sticky 저장하고',
    '재검증 거부나 실행 실패는 같은 트랜잭션 rollback으로 claim row도 사라지게 둡니다',
    '거부/실패 재시도는 저장된 거부 결과를 반환하지 않고 현재 상태로 부작용 없이 재평가합니다',
    '성공 claim row의 TTL은 확인 token의 staleness window보다 길게 유지하며',
    'window를 막 지난 late duplicate도 새 실행으로 보지 않고 저장된 성공 결과와 표시용 snapshot을 반환합니다',
    '자산별 차감량, 잔액 snapshot, settlement 배분 결과, 확인 화면 fingerprint는 key 재계산 입력으로 쓰지 않습니다',
    '실행 트랜잭션은 참여 자산 row를 결정적 `deduction_order`와 `asset_module` 사전순 tiebreak 순서대로 잠근 뒤',
    '무효화 사유는 잔액 부족/동시 차감처럼 plan 수량을 실행할 수 없는 경우와',
    '확인-실행 사이 구매력 snapshot, 통화 min-unit, rounding/carry `rounding_policy_version`이 달라진 경우를 분리해 기록합니다',
    '마지막 자산의 잔여 settlement 흡수는 정확 충당이 가능한 범위까지만 허용하고',
    '1 자산 단위의 settlement 가치가 통화 최소단위보다 커서 정확한 기준금액을 만들 수 없는 경우 1단위 미만 ceil overpay는 허용하지 않습니다',
    '`price.currency == 각 참여 자산의 purchase_power.settlement_currency`',
    '`asset_units`와 `settlement_units`는 양의 정수여야 하고',
    '`settlement_currency`는 core/settings의 known currency min-unit registry에 존재해야 합니다',
    '자산 설정 저장 또는 관리자 config 로드 시점에 설정 오류로 노출합니다',
    '통화 최소단위 미만의 rational 값은 자산 row 하나에서 올림하지 않고 다음 allocation으로 carry할 수 있으며',
    '기존 확인 화면에서 진행 중인 in-flight 요청은 fail-closed로 재확인이 발생할 수 있음을 통화/정책 변경 워크플로에 안내합니다',
    '통화 min-unit registry는 자산 모듈이 아니라 core/settings가 소유하며',
    '`settlement_numerator`, `settlement_denominator`, `cumulative_settlement_numerator`, `fractional_carry_numerator`, `fractional_carry_denominator`',
    '`snapshot_schema_version`은 snapshot 구조 버전',
    '`rounding_policy_version`은 금액 계산/반올림/잔여 처리 버전',
    '`settlement_kind`는 `paid`, `free`, `paid_settled_zero`, `preview_test_zero`, `legacy_unknown` 중 하나',
    '`free`는 무료 접근뿐 아니라 지급/적립처럼 기준가격 settlement가 발생하지 않는 non-use row를 포함하고',
    '같은 PDO transaction에 동참해야 하며 내부 commit이나 별도 connection을 쓰면 안 됩니다',
    '문구 존재를 보는 정적 체크는 계약 조항 삭제를 막는 가드일 뿐 transaction 동참, carry, overpay, lock 순서의 런타임 준수를 증명하지 못하므로',
    'InnoDB에서는 미커밋 unique claim row에 대한 중복 insert가 선행 트랜잭션의 commit/rollback까지 블록될 수 있으므로',
    'commit 후 duplicate-key, rollback 후 insert 성공, lock wait timeout 시 `processing` 응답을 모두 확인해야 합니다',
    '환전 정책 row를 가격 환산에 사용하지 않는다',
    '사용자가 환전 후 결제를 명시 확인한 POST에서만 환전 실행 후 settlement 계획을 다시 계산한다',
]);

sr_asset_settlement_check_contains('docs/module-guide.md', [
    '`deduction_order`가 같으면 소비 모듈과 공통 helper는 `asset_module` 사전순으로 정렬해야 하며',
    '다중 자산 row lock도 같은 순서로 잡아 동시 복합 차감 간 deadlock 가능성을 낮춘다',
    '`transaction_function`은 호출자가 이미 시작한 같은 PDO transaction에 동참해야 하며',
    '`purchase_power => [\'asset_units\' => 양의 정수, \'settlement_units\' => 양의 정수, \'settlement_currency\' => 통화 코드]`',
    '통화 최소단위 미만 rational 값은 row별로 올림하지 않고 다음 allocation으로 carry할 수 있으며',
    '`settlement_numerator`, `settlement_denominator`, `cumulative_settlement_numerator`, `fractional_carry_numerator`, `fractional_carry_denominator`',
    '`asset_units`와 `settlement_units`는 양의 정수, `settlement_currency`는 core/settings min-unit registry에 존재하는 통화인지 자산 설정 저장 또는 관리자 config 로드 시점에 검증해 setup 오류로 노출한다',
    'settlement 기반 차감의 멱등 key는 회원, 소비 모듈, `reference_type`, `reference_id`, 기준금액, 기준 통화, 클라이언트 요청 토큰처럼 안정 입력만 사용한다',
    '클라이언트 요청 토큰은 HTTP attempt마다 새로 만들지 않고 구매 의도(intent), 즉 확인 화면 렌더 시점에 1회 생성해 확정 POST 재시도 전체에서 동일하게 운반한다',
    '실행 트랜잭션은 원장 row lock보다 먼저 안정 입력 기반 dedupe key의 claim row를 insert해야 하며',
    '이 key에는 DB unique 제약을 둔다',
    'duplicate-key가 나면 동시 중복으로 보고 `processing` 또는 저장된 성공 결과를 반환하며',
    '성공 결과만 claim row와 함께 커밋해 sticky 저장하고',
    '재검증 거부나 실행 실패는 rollback으로 claim row도 사라지게 두어 재시도 시 현재 상태로 부작용 없이 재평가한다',
    '마지막 자산의 잔여 settlement 흡수는 정확 충당이 가능한 범위까지만 허용한다',
    '각각 0.5 KRW 가치인 두 allocation처럼 통화 최소단위 미만 값들이 합쳐 정확히 1단위를 만들 수 있으면',
    '확인 화면 이후 실행 전 잔액이 줄어든 경우와 구매력 snapshot, 통화 min-unit, rounding/carry `rounding_policy_version`이 바뀐 경우를 별도 무효화 사유로 기록하고',
    '운영자가 통화 min-unit 또는 rounding/carry `rounding_policy_version`을 변경하면 기존 확인 화면의 in-flight 요청이 fail-closed 재확인으로 떨어질 수 있음을 변경 워크플로에 안내한다',
    '`snapshot_schema_version`, rounding/carry `rounding_policy_version`, 0원/legacy 분류 `settlement_kind`',
    '`settlement_kind`는 `paid`, `free`, `paid_settled_zero`, `preview_test_zero`, `legacy_unknown` 중 하나',
    '`free`는 무료 접근뿐 아니라 지급/적립처럼 기준가격 settlement가 발생하지 않는 non-use row를 포함하고',
    '정적 체크는 계약 문구 회귀 방지용이며 transaction 동참, carry, overpay, lock 순서의 런타임 준수는 구현 시점 테스트 fixture로 검증한다',
    'InnoDB의 미커밋 unique claim 중복 insert는 선행 트랜잭션 commit/rollback까지 블록될 수 있으므로 commit 후 duplicate-key, rollback 후 insert 성공, lock wait timeout 시 `processing` 응답을 함께 확인한다',
    '`purchase_power`는 콘텐츠/커뮤니티 같은 소비 모듈의 settlement 기준이며 환전 모듈 정책 row가 아니다',
    '사용자가 환전 후 결제를 명시 확인한 POST에서만 `asset_exchange` 실행 후 settlement 계획을 다시 계산할 수 있다',
]);

sr_asset_settlement_check_contains('docs/admin-ui-guide.md', [
    '새 콘텐츠·파일·커뮤니티 정책의 settlement currency는 현재 사이트 기본 통화를 기본값으로 저장한다',
    '`amount`를 자산 단위, `settlement_amount`와 `settlement_currency`를 기준가격 충당 단위로 표시한다',
    '구매력 snapshot, `snapshot_schema_version`, `rounding_policy_version`, `settlement_kind`',
]);

sr_asset_settlement_check_contains('docs/implementation-snapshot.md', [
    '`member-assets.php`의 `purchase_power`와 `deduction_order`',
    '`settlement_amount`, `settlement_currency`, `purchase_power_snapshot_json`, `settlement_kind`, `snapshot_schema_version`, `rounding_policy_version`',
    '다운로드 조회와 개인정보 export는 저장된 settlement snapshot을 요약한다',
    '확인 모달이 환전 후 결제를 별도로 확인하고 POST에서 환전 실행 후 settlement 계획을 다시 계산한다',
]);

sr_asset_settlement_check_contains('docs/smoke-test.md', [
    '클라이언트 요청 토큰은 HTTP attempt가 아니라 구매 의도(intent)마다 확인 화면 렌더 시점에 1회 생성되어야 하며',
    '실행 트랜잭션은 원장 row lock보다 먼저 unique 제약이 있는 claim row를 insert해야 하며',
    '성공 후에는 잔액 snapshot이나 자산별 계산 결과가 바뀌어도 원장을 다시 만들지 않고 저장된 성공 결과와 표시용 snapshot을 반환해야 한다',
    '재검증 거부나 실행 실패는 rollback으로 claim row도 사라지므로, 재시도 시 저장된 거부 결과가 아니라 현재 상태로 부작용 없이 재평가되어야 한다',
    '`/admin/content/file-downloads`의 유료 다운로드 내역은 연결 차감 로그의 자산 단위 차감량, 기준 settlement 금액/통화',
    '`/admin/community/attachment-downloads`의 유료 다운로드 내역은 연결 차감 로그의 자산 단위 차감량, 기준 settlement 금액/통화',
    '두 탭 동시 제출은 duplicate-key에서 `processing` 또는 저장된 성공 결과로 흡수되고 lock 획득 뒤에도 claim row 상태를 다시 확인해야 한다',
    '확인 window를 막 지난 late duplicate도 만료 직후 새 실행이 아니라 저장된 성공 결과로 떨어져야 한다',
    'commit 후 duplicate-key, rollback 후 insert 성공, lock wait timeout 시 `processing` 응답을 fixture로 확인한다',
    '구매력/통화 min-unit/`rounding_policy_version`이 바뀌면 snapshot drift 사유로 별도 기록하며',
    'settlement 로그에는 `settlement_kind`, `snapshot_schema_version`, `rounding_policy_version`이 저장되어야 하며',
    '기존 `legacy 1:1 assumed` 또는 `legacy_unknown` 차감 로그는 업데이트에서 삭제되어야 한다',
    '선택된 `access` 쿠폰이나 전액 할인 쿠폰이 있으면 쿠폰 redemption과 접근권만 남기고 자산 원장 거래와 settlement 차감 로그를 만들지 않아야 한다',
    '일부 할인 쿠폰은 할인액과 남은 결제액을 redemption snapshot에 남기고',
    '다중 자산 row lock은 `deduction_order`와 `asset_module` tiebreak 순서로 잡는지 확인한다',
    '`asset_units`/`settlement_units` 양의 정수 여부와 `settlement_currency`의 min-unit registry 존재 여부는 설정 저장 또는 관리자 config 로드 시점에 setup 오류로 드러나야 한다',
    '통화 min-unit 또는 rounding/carry `rounding_policy_version` 변경 직후 기존 확인 화면의 in-flight 요청은 fail-closed 재확인으로 떨어질 수 있음을 운영 워크플로에서 확인한다',
    '1P = 10 KRW, 가격 1,005 KRW 같은 케이스는 정확 충당 불가로 실패하고 ceil overpay가 없어야 하며',
    '기준금액 0은 차감 없이 `settlement_amount=0` 로그와 접근권만 남겨야 한다',
    '문서 정적 체크는 계약 조항 삭제 방지용이므로 transaction 동참, carry, overpay, lock 순서는 구현 테스트 fixture와 필요한 HTTP smoke로 행위를 검증한다',
]);

sr_asset_settlement_check_contains('docs/records/issue-115-settlement-contract-2026-06-11.md', [
    '실행 트랜잭션은 원장 row lock보다 먼저 안정 입력 기반 dedupe key의 claim row를 insert해야 하며',
    '최초 성공 결과는 claim row와 함께 저장한다',
    '재검증 거부나 실행 실패는 같은 transaction rollback으로 claim row도 사라지게 두며',
    'window를 막 지난 late duplicate도 새 실행으로 보지 않고 저장된 성공 결과와 표시용 snapshot을 반환하고 원장을 다시 만들지 않는다',
    '런타임 통화 불변식은 `price.currency == 각 참여 자산의 purchase_power.settlement_currency`이며',
    '통화 최소단위 미만 rational 값은 row별로 올림하지 않고 다음 allocation으로 carry하며',
    '기존 `legacy 1:1 assumed` settlement snapshot과 `legacy_unknown` 차감 로그를 보존하지 않고 삭제한다',
    '일부 `fixed_discount`/`percent_discount` 쿠폰은 할인액과 남은 결제액을 redemption snapshot에 남기고',
    '개인정보 export는 raw `purchase_power_snapshot_json`과 함께 `settlement_summary`를 제공해',
    '같은 기준의 `settlement_summaries`를 함께 제공한다',
    '`member-assets.php` 거래 helper는 같은 PDO transaction에 동참해야 하며 내부 commit이나 별도 connection을 쓰면 복합 차감 후보에서 제외한다',
]);

$memberAssetsHelper = file_get_contents('modules/member/helpers/assets.php');
if (!is_string($memberAssetsHelper)) {
    $errors[] = 'cannot read modules/member/helpers/assets.php';
} elseif (!str_contains($memberAssetsHelper, 'strcmp((string) ($left[\'module_key\'] ?? \'\'), (string) ($right[\'module_key\'] ?? \'\'))')) {
    $errors[] = 'member asset definition sorting must keep deterministic module_key tiebreak for equal deduction_order';
}
if (is_string($memberAssetsHelper) && !str_contains($memberAssetsHelper, "'rounding_policy_version' => 'asset_settlement_rounding_v1'")) {
    $errors[] = 'member purchase power snapshot must write rounding_policy_version';
}
if (is_string($memberAssetsHelper) && str_contains($memberAssetsHelper, "'policy_version' => 'asset_settlement_v1'")) {
    $errors[] = 'member purchase power snapshot must not write legacy policy_version';
}
sr_asset_settlement_check_contains('modules/member/helpers/assets.php', [
    'function sr_member_asset_settlement_exchange_suggestion',
    'sr_asset_exchange_policies($pdo, true)',
    'sr_asset_exchange_quote($pdo, $policy, $accountId, $requestAmount)',
    'function sr_member_asset_settlement_execute_exchange_suggestion',
    'sr_asset_exchange_execute($pdo, (array) $suggestion[\'policy\'], $accountId',
]);

$settlementSchemaFiles = [
    'modules/content/install.sql',
    'modules/community/install.sql',
    'modules/content/helpers/assets.php',
    'modules/community/helpers/assets.php',
    'modules/content/privacy-export.php',
    'modules/community/privacy-export.php',
];
foreach ($settlementSchemaFiles as $settlementSchemaFile) {
    sr_asset_settlement_check_contains($settlementSchemaFile, [
        'settlement_kind',
        'snapshot_schema_version',
        'rounding_policy_version',
    ]);
}

sr_asset_settlement_check_contains('modules/content/helpers/assets.php', [
    'if ($settlementAmount > 0) {' . "\n" . '        return \'paid\';' . "\n" . '    }' . "\n\n" . '    if ($amount === 0) {' . "\n" . '        return \'paid_settled_zero\';',
    'return $purchasePowerSnapshotJson !== \'\' ? \'paid\' : \'legacy_unknown\';',
]);

sr_asset_settlement_check_contains('modules/community/helpers/assets.php', [
    'if ($settlementAmount > 0) {' . "\n" . '        return \'paid\';' . "\n" . '    }' . "\n\n" . '    if ($amount === 0) {' . "\n" . '        return \'paid_settled_zero\';',
    'return $purchasePowerSnapshotJson !== \'\' ? \'paid\' : \'legacy_unknown\';',
]);

sr_asset_settlement_check_contains('modules/content/helpers/files.php', [
    "'legacy_unknown' AS settlement_kind",
    "'asset_settlement_snapshot_v1' AS snapshot_schema_version",
    "'asset_settlement_rounding_v1' AS rounding_policy_version",
    "\$accessLog['settlement_amount']",
]);

sr_asset_settlement_check_contains('modules/content/helpers/asset-access.php', [
    '$pendingAccessCharges = [];',
    'foreach ($pendingAccessCharges as $pendingAccessCharge)',
    '$pendingDownloadCharges = [];',
    'foreach ($pendingDownloadCharges as $pendingDownloadCharge)',
    '$assetExchangeConfirmed = false',
    'sr_member_asset_settlement_execute_exchange_suggestion($pdo, $assetExchangeSuggestion, $accountId)',
    'Automatic asset exchange did not create a payable settlement plan.',
    'Automatic asset exchange did not create a payable file settlement plan.',
    'Content asset access is still processing.',
    'Content file asset access is still processing.',
    "sr_content_asset_settlement_config_error_message(\$pdo, \$assetModules, \$accountId, 0, \$settlementCurrency, '콘텐츠를 열람할 수 없습니다.')",
    "sr_content_asset_settlement_config_error_message(\$pdo, \$assetModules, \$accountId, 0, \$settlementCurrency, '파일을 다운로드할 수 없습니다.')",
]);

sr_asset_settlement_check_contains('modules/community/helpers/asset-events.php', [
    '$pendingAssetEvents = [];',
    'foreach ($pendingAssetEvents as $pendingAssetEvent)',
    '$assetExchangeConfirmed = false',
    'sr_member_asset_settlement_execute_exchange_suggestion($pdo, $assetExchangeSuggestion, $accountId)',
    'Automatic asset exchange did not create a payable community settlement plan.',
    'sr_community_allocate_asset_settlement_use($pdo, $assetModules, $accountId, $amount, $settlementCurrency)',
    '$allocatedSettlementAmount = $direction === \'use\' ? (int) ($allocation[\'settlement_amount\'] ?? 0) : 0;',
    '$purchasePowerSnapshotJson = $direction === \'use\' ? sr_community_asset_purchase_power_snapshot_json',
    "'post_write_charge' => '글을 작성할 수 없습니다.',",
    "'comment_write_charge' => '댓글을 작성할 수 없습니다.',",
    "'message_send_charge' => '쪽지를 보낼 수 없습니다.',",
    'Community asset event is still processing.',
    "sr_community_asset_settlement_config_error_message(\$pdo, \$assetModules, \$accountId, 0, \$settlementCurrency, \$zeroAmountSuffix)",
    "'error_key' => 'asset_settlement_config_error'",
]);

sr_asset_settlement_check_contains('modules/community/helpers/assets.php', [
    'function sr_community_asset_event_config',
    "'asset_settlement_currency' => \$settlementCurrency",
    'sr_community_asset_board_setting($pdo, $board, $settings, $prefix . \'_settlement_currency\', \'\')',
    'sr_member_asset_settlement_config_error_message($plan, $suffix)',
    'function sr_community_asset_settlement_exchange_suggestion',
    'function sr_community_asset_settlement_exchange_hidden_inputs_html',
]);

sr_asset_settlement_check_contains('modules/content/helpers/assets.php', [
    'sr_member_asset_settlement_config_error_message($plan, $suffix)',
    'function sr_content_asset_settlement_exchange_suggestion',
    'function sr_content_asset_settlement_exchange_hidden_inputs_html',
]);

foreach ([
    'modules/community/actions/write.php' => [
        "sr_community_asset_event_config(\$pdo, \$board, \$settings, 'write_charge', 'every_action')",
        "sr_community_run_asset_event(\$pdo, \$writeChargeConfig, \$authorAccountId, 'post_write_charge'",
    ],
    'modules/community/actions/comment.php' => [
        "sr_community_asset_event_config(\$pdo, \$board, \$settings, 'comment_charge', 'every_action')",
        "sr_community_run_asset_event(\$pdo, \$commentChargeConfig, \$authorAccountId, 'comment_write_charge'",
    ],
    'modules/community/actions/message-write.php' => [
        "sr_community_asset_event_config(\$pdo, [], \$settings, 'message_charge', 'every_action')",
        "sr_community_run_asset_event(\$pdo, \$messageChargeConfig, (int) \$account['id'], 'message_send_charge'",
    ],
    'modules/community/actions/view.php' => [
        "sr_community_asset_event_config(\$pdo, \$postBoard, \$settings, 'paid_read', 'once')",
        "sr_community_run_asset_event(\n                \$pdo,\n                \$paidReadConfig,\n                (int) \$account['id'],\n                'post_read'",
        "sr_post_string('asset_exchange_confirm', 1) === '1'",
    ],
    'modules/community/actions/attachment.php' => [
        "sr_community_asset_event_config(\$pdo, \$board, \$settings, 'paid_attachment_download', 'once')",
        "sr_community_run_asset_event(\n                \$pdo,\n                \$downloadConfig,\n                (int) \$account['id'],\n                'attachment_download'",
        "sr_post_string('asset_exchange_confirm', 1) === '1'",
    ],
] as $communityActionFile => $markers) {
    sr_asset_settlement_check_contains($communityActionFile, $markers);
}

sr_asset_settlement_check_contains('modules/content/actions/view.php', [
    "sr_post_string('asset_exchange_confirm', 1) === '1'",
]);
sr_asset_settlement_check_contains('modules/content/actions/download.php', [
    "sr_post_string('asset_exchange_confirm', 1) === '1'",
]);
sr_asset_settlement_check_contains('modules/content/views/asset-confirmation-modal.php', [
    '$assetConfirmationExchangeSuggestion',
    'sr_content_asset_settlement_exchange_hidden_inputs_html($assetConfirmationExchangeSuggestion)',
]);
sr_asset_settlement_check_assert(
    substr_count((string) file_get_contents('modules/content/views/asset-confirmation-modal.php'), 'sr_content_asset_settlement_exchange_hidden_inputs_html($assetConfirmationExchangeSuggestion)') >= 2,
    'content confirmation modal coupon and asset payment forms must both preserve exchange confirmation.'
);
sr_asset_settlement_check_contains('modules/community/views/asset-confirmation-modal.php', [
    '$assetConfirmationExchangeSuggestion',
    'sr_community_asset_settlement_exchange_hidden_inputs_html($assetConfirmationExchangeSuggestion)',
]);
sr_asset_settlement_check_assert(
    substr_count((string) file_get_contents('modules/community/views/asset-confirmation-modal.php'), 'sr_community_asset_settlement_exchange_hidden_inputs_html($assetConfirmationExchangeSuggestion)') >= 2,
    'community confirmation modal coupon and asset payment forms must both preserve exchange confirmation.'
);
sr_asset_settlement_check_contains('modules/community/helpers/attachments.php', [
    '$accessResult[\'processed_logs\']',
    '$accessResult[\'logs\']',
    'sr_community_asset_log($pdo, $dedupeKey)',
]);
sr_asset_settlement_check_assert(
    preg_match('/if \(\(\$startedTransaction \|\| \$mixedCouponTransactionOpen\) && sr_content_asset_is_retryable_transaction_exception\(\$exception\)\) \{\s*throw \$exception;\s*\}\s*if \(function_exists\(\'sr_log_exception\'\)\) \{\s*sr_log_exception\(\$exception, \'content_asset_access_charge_failed\'\);/s', (string) file_get_contents('modules/content/helpers/asset-access.php')) === 1
        && preg_match('/if \(\(\$startedTransaction \|\| \$mixedCouponTransactionOpen\) && sr_content_asset_is_retryable_transaction_exception\(\$exception\)\) \{\s*throw \$exception;\s*\}\s*if \(function_exists\(\'sr_log_exception\'\)\) \{\s*sr_log_exception\(\$exception, \'content_file_download_charge_failed\'\);/s', (string) file_get_contents('modules/content/helpers/asset-access.php')) === 1,
    'content mixed coupon asset settlement must preserve retryable transaction exceptions for view and download charges.'
);

sr_asset_settlement_check_contains('modules/content/helpers/asset-actions.php', [
    '$pendingActionCharges = [];',
    'foreach ($pendingActionCharges as $pendingActionCharge)',
    "sr_content_asset_settlement_config_error_message(\$pdo, \$assetModules, \$accountId, 0, \$settlementCurrency, '완료 처리할 수 없습니다.')",
]);

sr_asset_settlement_check_contains('modules/content/helpers/records.php', [
    'asset_access_settlement_currency = :asset_access_settlement_currency',
    'asset_access_amount, asset_access_settlement_currency, asset_access_amounts_json',
    ':asset_access_amount, :asset_access_settlement_currency, :asset_access_amounts_json',
    "'asset_access_settlement_currency' => sr_content_asset_settlement_currency",
    'asset_action_settlement_currency = :asset_action_settlement_currency',
    'asset_action_amount, asset_action_settlement_currency, asset_action_amounts_json',
    ':asset_action_amount, :asset_action_settlement_currency, :asset_action_amounts_json',
    "'asset_action_settlement_currency' => sr_content_asset_settlement_currency",
]);

sr_asset_settlement_check_contains('modules/content/helpers/files.php', [
    "'기준 ' . number_format((int) (\$accessLog['settlement_amount'] ?? 0))",
    "'snapshot ' . (string) (\$accessLog['snapshot_schema_version'] ?? 'asset_settlement_snapshot_v1')",
    "'rounding ' . (string) (\$accessLog['rounding_policy_version'] ?? 'asset_settlement_rounding_v1')",
]);

sr_asset_settlement_check_contains('modules/community/helpers/attachments.php', [
    'function sr_community_admin_attachment_download_logs_with_asset_summaries',
    "'기준 ' . number_format((int) (\$assetLog['settlement_amount'] ?? 0))",
    "'snapshot ' . (string) (\$assetLog['snapshot_schema_version'] ?? 'asset_settlement_snapshot_v1')",
    "'rounding ' . (string) (\$assetLog['rounding_policy_version'] ?? 'asset_settlement_rounding_v1')",
]);

sr_asset_settlement_check_contains('modules/community/helpers/assets.php', [
    "\$keys[] = \$prefix . '_settlement_currency';",
]);

sr_asset_settlement_check_contains('modules/community/helpers/admin-boards.php', [
    "\$assetSettings[\$assetPrefix . '_settlement_currency'] = sr_community_asset_settlement_currency",
    "\$assetPrefix . '_settlement_currency'",
]);

sr_asset_settlement_check_contains('modules/community/actions/admin-settings.php', [
    "\$assetSettings[\$assetPrefix . '_settlement_currency'] = sr_community_asset_settlement_currency",
    "\$settings[\$assetPrefix . '_settlement_currency'] ?? \$defaultSettlementCurrency",
    "['write_charge_settlement_currency', (string) \$assetSettings['write_charge_settlement_currency'], 'string']",
    "['message_charge_settlement_currency', (string) \$assetSettings['message_charge_settlement_currency'], 'string']",
    "['comment_charge_settlement_currency', (string) \$assetSettings['comment_charge_settlement_currency'], 'string']",
    "['paid_read_settlement_currency', (string) \$assetSettings['paid_read_settlement_currency'], 'string']",
    "['paid_attachment_download_settlement_currency', (string) \$assetSettings['paid_attachment_download_settlement_currency'], 'string']",
]);

$communityLevelHelper = file_get_contents('modules/community/helpers/levels.php');
if (!is_string($communityLevelHelper)) {
    $errors[] = 'cannot read modules/community/helpers/levels.php';
} elseif (str_contains($communityLevelHelper, 'sr_currency_is_known') && str_contains($communityLevelHelper, '_settlement_currency')) {
    $errors[] = 'community level settings must preserve unknown stored settlement currency for fail-closed runtime validation';
}

sr_asset_settlement_check_contains('modules/community/views/admin-boards.php', [
    "'source_' . (string) \$assetPrefix . '_settlement_currency'",
]);

sr_asset_settlement_check_contains('modules/community/views/admin-attachment-downloads.php', [
    '$assetLogSummary = trim((string) ($downloadLog[\'asset_log_summary\'] ?? \'\'));',
    '연결된 차감 로그를 확인할 수 없음',
]);

sr_asset_settlement_check_contains('modules/content/views/admin-file-downloads.php', [
    '$accessSummary = trim((string) ($downloadLog[\'access_log_summary\'] ?? \'\'));',
    '연결된 차감 로그 없음',
]);

sr_asset_settlement_check_contains('modules/content/helpers/groups.php', [
    "'asset_access_settlement_currency'",
    "'asset_action_settlement_currency'",
]);

sr_asset_settlement_check_contains('modules/content/views/admin-contents.php', [
    'name="source_asset_access_settlement_currency"',
    'name="source_asset_action_settlement_currency"',
]);

foreach (['modules/content/privacy-export.php', 'modules/community/privacy-export.php'] as $privacyExportFile) {
    $privacyExport = file_get_contents($privacyExportFile);
    if (!is_string($privacyExport)) {
        $errors[] = 'cannot read privacy export file: ' . $privacyExportFile;
        continue;
    }

    if (str_contains($privacyExport, "'policy_version' =>")) {
        $errors[] = $privacyExportFile . ' must expose rounding_policy_version instead of policy_version in privacy export summaries';
    }
}

sr_asset_settlement_check_contains('modules/content/privacy-export.php', [
    'function sr_content_privacy_add_file_download_settlement_summaries',
    "'settlement_summaries'",
    'sr_content_privacy_asset_settlement_summary($assetLog)',
]);

sr_asset_settlement_check_contains('modules/community/privacy-export.php', [
    'function sr_community_privacy_add_attachment_download_settlement_summaries',
    "'settlement_summaries'",
    'sr_community_privacy_asset_settlement_summary($assetLog)',
]);

sr_asset_settlement_check_contains('.tools/bin/check-privacy-export-runtime.php', [
    'content export must attach linked file download settlement summary',
    'community export must attach linked attachment download settlement summary',
]);

foreach (['modules/content/updates/2026.06.020.sql', 'modules/community/updates/2026.06.020.sql'] as $settlementUpdateFile) {
    sr_asset_settlement_check_contains($settlementUpdateFile, [
        'REPLACE(REPLACE(purchase_power_snapshot_json, \'"policy_version":"asset_settlement_v1"\', \'"rounding_policy_version":"asset_settlement_rounding_v1"\'), \'"policy_version": "asset_settlement_v1"\', \'"rounding_policy_version": "asset_settlement_rounding_v1"\')',
    ]);
}

sr_asset_settlement_check_contains('modules/content/updates/2026.06.025.sql', [
    "WHERE setting_key = 'site.default_currency'",
    "IN ('KRW', 'USD')",
    'asset_access_settlement_currency = @sr_content_asset_settlement_default_currency',
    'asset_action_settlement_currency = @sr_content_asset_settlement_default_currency',
    'asset_download_settlement_currency = @sr_content_asset_settlement_default_currency',
    'settlement_currency = @sr_content_asset_settlement_default_currency',
    '"snapshot_schema_version":"asset_settlement_snapshot_v1"',
    '"rounding_policy_version":"asset_settlement_rounding_v1"',
    "version = '2026.06.025'",
]);

sr_asset_settlement_check_contains('modules/content/updates/2026.06.026.sql', [
    'DELETE FROM {{SR_TABLE_PREFIX}}content_asset_access_logs',
    "purchase_power_snapshot_json LIKE '%legacy 1:1 assumed%'",
    "settlement_kind = 'legacy_unknown'",
    'DELETE FROM {{SR_TABLE_PREFIX}}content_asset_action_logs',
    "version = '2026.06.026'",
]);

sr_asset_settlement_check_contains('modules/community/updates/2026.06.043.sql', [
    "WHERE setting_key = 'site.default_currency'",
    "IN ('KRW', 'USD')",
    'message_charge_settlement_currency',
    's.setting_value = @sr_community_asset_settlement_default_currency',
    'settlement_currency = @sr_community_asset_settlement_default_currency',
    '"snapshot_schema_version":"asset_settlement_snapshot_v1"',
    '"rounding_policy_version":"asset_settlement_rounding_v1"',
    "version = '2026.06.043'",
]);

sr_asset_settlement_check_contains('modules/community/updates/2026.06.044.sql', [
    'DELETE FROM {{SR_TABLE_PREFIX}}community_asset_logs',
    "purchase_power_snapshot_json LIKE '%legacy 1:1 assumed%'",
    "settlement_kind = 'legacy_unknown'",
    "version = '2026.06.044'",
]);

sr_asset_settlement_check_contains('modules/coupon/helpers.php', [
    'sr_coupon_discount_application($selectedIssue, $pricing)',
    'sr_coupon_redemption_pricing_snapshot_from_result($pricing, $targetType, $targetId)',
]);

sr_asset_settlement_check_contains('modules/content/helpers/asset-access.php', [
    '$couponIssueId > 0 ? sr_content_try_coupon_access',
    '$couponIssueId > 0 ? sr_content_try_coupon_download_access',
    "'coupon_used' => !empty(\$couponResult['processed'])",
]);

sr_asset_settlement_check_contains('modules/community/helpers/asset-events.php', [
    'sr_community_try_paid_read_coupon_access',
    'sr_community_try_attachment_download_coupon_access',
    'sr_coupon_redeem_for_target($pdo, $accountId',
]);

sr_asset_settlement_check_forbidden_exchange_refs([
    'modules/content/helpers/assets.php',
    'modules/content/helpers/files.php',
    'modules/content/privacy-export.php',
    'modules/community/helpers/assets.php',
    'modules/community/privacy-export.php',
]);

sr_asset_settlement_check_runtime_fixture();

if ($errors !== []) {
    fwrite(STDERR, "asset settlement contract checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "asset settlement contract checks completed.\n";
