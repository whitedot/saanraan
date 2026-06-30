#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/asset_exchange/helpers.php';
require_once $root . '/modules/notification/helpers.php';

$errors = [];

function sr_asset_exchange_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_asset_exchange_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_asset_exchange_runtime_error($message);
    }
}

function sr_asset_exchange_runtime_schema(PDO $pdo, bool $includeDepositTransactions = true): void
{
    $pdo->exec(
        "CREATE TABLE sr_modules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_key TEXT NOT NULL UNIQUE,
            version TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_module_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_id INTEGER NOT NULL,
            setting_key TEXT NOT NULL,
            setting_value TEXT NOT NULL,
            value_type TEXT NOT NULL DEFAULT 'string',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE (module_id, setting_key)
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_asset_exchange_policies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            from_module_key TEXT NOT NULL,
            to_module_key TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'disabled',
            rate_numerator INTEGER NOT NULL DEFAULT 1,
            rate_denominator INTEGER NOT NULL DEFAULT 1,
            min_amount INTEGER NOT NULL DEFAULT 1,
            max_amount INTEGER NULL,
            rounding_mode TEXT NOT NULL DEFAULT 'floor',
            fee_trigger TEXT NOT NULL DEFAULT 'none',
            fee_basis TEXT NOT NULL DEFAULT 'to_amount',
            fee_rate_numerator INTEGER NOT NULL DEFAULT 0,
            fee_rate_denominator INTEGER NOT NULL DEFAULT 100,
            fee_fixed_amount INTEGER NOT NULL DEFAULT 0,
            fee_min_amount INTEGER NULL,
            fee_max_amount INTEGER NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE (from_module_key, to_module_key)
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_member_accounts (
            id INTEGER PRIMARY KEY,
            email TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'active'
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NULL,
            audience TEXT NOT NULL DEFAULT 'account',
            title TEXT NOT NULL,
            body_text TEXT,
            body_format TEXT NOT NULL DEFAULT 'plain',
            link_url TEXT NOT NULL DEFAULT '',
            source_module_key TEXT NOT NULL DEFAULT '',
            event_key TEXT NOT NULL DEFAULT '',
            metadata_json TEXT,
            status TEXT NOT NULL DEFAULT 'active',
            read_at TEXT,
            created_by_account_id INTEGER,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_notification_deliveries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            notification_id INTEGER NOT NULL,
            channel TEXT NOT NULL,
            recipient TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'queued',
            provider_message_id TEXT NOT NULL DEFAULT '',
            error_message TEXT NOT NULL DEFAULT '',
            attempted_at TEXT,
            locked_at TEXT,
            locked_by TEXT NOT NULL DEFAULT '',
            attempt_count INTEGER NOT NULL DEFAULT 0,
            next_attempt_at TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_notification_event_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_key TEXT NOT NULL,
            event_key TEXT NOT NULL,
            title_template TEXT NOT NULL,
            body_template TEXT,
            link_template TEXT NOT NULL DEFAULT '',
            channels_json TEXT,
            status TEXT NOT NULL DEFAULT 'active',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE(module_key, event_key)
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_notification_push_endpoints (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            provider_key TEXT NOT NULL,
            recipient_type TEXT NOT NULL DEFAULT 'personal',
            endpoint_ciphertext TEXT NOT NULL,
            endpoint_fingerprint TEXT NOT NULL,
            recipient_label TEXT NOT NULL DEFAULT '',
            recipient_masked TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'active',
            key_version TEXT NOT NULL DEFAULT 'v1',
            verified_at TEXT,
            disabled_at TEXT,
            last_used_at TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_point_balances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL UNIQUE,
            balance INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_reward_balances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL UNIQUE,
            balance INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_reward_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            amount INTEGER NOT NULL,
            balance_after INTEGER NOT NULL,
            transaction_type TEXT NOT NULL,
            reason TEXT NOT NULL DEFAULT '',
            reference_type TEXT NOT NULL DEFAULT '',
            reference_id TEXT NOT NULL DEFAULT '',
            created_by_account_id INTEGER NULL,
            created_at TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_deposit_balances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL UNIQUE,
            balance INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )"
    );
    if ($includeDepositTransactions) {
        $pdo->exec(
            "CREATE TABLE sr_deposit_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id INTEGER NOT NULL,
                amount INTEGER NOT NULL,
                balance_after INTEGER NOT NULL,
                transaction_type TEXT NOT NULL,
                reason TEXT NOT NULL DEFAULT '',
                reference_type TEXT NOT NULL DEFAULT '',
                reference_id TEXT NOT NULL DEFAULT '',
                created_by_account_id INTEGER NULL,
                created_at TEXT NOT NULL
            )"
        );
    }
    $pdo->exec(
        "CREATE TABLE sr_asset_exchange_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            exchange_group_id TEXT NOT NULL UNIQUE,
            policy_id INTEGER NULL,
            account_id INTEGER NOT NULL,
            from_module_key TEXT NOT NULL,
            to_module_key TEXT NOT NULL,
            request_amount INTEGER NOT NULL,
            rate_numerator INTEGER NOT NULL,
            rate_denominator INTEGER NOT NULL,
            rounding_mode TEXT NOT NULL,
            deposit_amount INTEGER NOT NULL,
            fee_amount INTEGER NOT NULL DEFAULT 0,
            fee_trigger TEXT NOT NULL DEFAULT 'none',
            fee_basis TEXT NOT NULL DEFAULT 'to_amount',
            status TEXT NOT NULL,
            failure_reason TEXT NOT NULL DEFAULT '',
            from_transaction_id INTEGER NULL,
            to_transaction_id INTEGER NULL,
            fee_transaction_id INTEGER NULL,
            created_by_account_id INTEGER NULL,
            created_at TEXT NOT NULL
        )"
    );
}

function sr_asset_exchange_runtime_seed(PDO $pdo, int $accountId, int $rewardBalance): void
{
    $now = sr_now();
    foreach (['asset_exchange', 'point', 'reward', 'deposit', 'notification'] as $moduleKey) {
        $pdo->prepare('INSERT INTO sr_modules (module_key, version, status) VALUES (:module_key, :version, :status)')
            ->execute(['module_key' => $moduleKey, 'version' => 'fixture', 'status' => 'enabled']);
    }
    $pdo->prepare('INSERT INTO sr_member_accounts (id, email, status) VALUES (:id, :email, :status)')
        ->execute(['id' => $accountId, 'email' => 'asset-member@example.test', 'status' => 'active']);
    $pdo->prepare('INSERT INTO sr_point_balances (account_id, balance, created_at, updated_at) VALUES (:account_id, :balance, :created_at, :updated_at)')
        ->execute(['account_id' => $accountId, 'balance' => 777, 'created_at' => $now, 'updated_at' => $now]);
    $pdo->prepare('INSERT INTO sr_reward_balances (account_id, balance, created_at, updated_at) VALUES (:account_id, :balance, :created_at, :updated_at)')
        ->execute(['account_id' => $accountId, 'balance' => $rewardBalance, 'created_at' => $now, 'updated_at' => $now]);
    $pdo->prepare('INSERT INTO sr_deposit_balances (account_id, balance, created_at, updated_at) VALUES (:account_id, 0, :created_at, :updated_at)')
        ->execute(['account_id' => $accountId, 'created_at' => $now, 'updated_at' => $now]);
    $stmt = $pdo->prepare(
        'INSERT INTO sr_notification_event_templates
            (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
         VALUES
            (:module_key, :event_key, :title_template, :body_template, :link_template, :channels_json, \'active\', :created_at, :updated_at)'
    );
    foreach ([
        ['point', 'transaction.exchange_out', '포인트 환전 출금', '{transaction_type_label}: {amount_abs}', '/account/points', '["site"]'],
        ['reward', 'transaction.exchange_out', '적립금 환전 출금', '{transaction_type_label}: {amount_abs}', '/account/rewards', '["site"]'],
        ['deposit', 'transaction.exchange_in', '예치금 환전 입금', '{transaction_type_label}: {amount_abs}', '/account/deposits', '["site"]'],
        ['deposit', 'transaction.exchange_fee', '예치금 환전 수수료', '{transaction_type_label}: {amount_abs}', '/account/deposits', '["site"]'],
    ] as $template) {
        $stmt->execute([
            'module_key' => $template[0],
            'event_key' => $template[1],
            'title_template' => $template[2],
            'body_template' => $template[3],
            'link_template' => $template[4],
            'channels_json' => $template[5],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

function sr_asset_exchange_runtime_policy(): array
{
    return [
        'id' => 55,
        'from_module_key' => 'reward',
        'to_module_key' => 'deposit',
        'status' => 'enabled',
        'rate_numerator' => 1,
        'rate_denominator' => 1,
        'min_amount' => 1,
        'max_amount' => null,
        'rounding_mode' => 'floor',
        'fee_trigger' => 'always',
        'fee_basis' => 'to_amount',
        'fee_type' => 'rate',
        'fee_rate_numerator' => 10,
        'fee_rate_denominator' => 100,
        'fee_fixed_amount' => 0,
        'fee_min_amount' => null,
        'fee_max_amount' => null,
    ];
}

function sr_asset_exchange_runtime_balance(PDO $pdo, string $table, int $accountId): int
{
    $stmt = $pdo->prepare('SELECT balance FROM ' . $table . ' WHERE account_id = :account_id LIMIT 1');
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();

    return is_array($row) ? (int) $row['balance'] : 0;
}

function sr_asset_exchange_runtime_count(PDO $pdo, string $table): int
{
    $row = $pdo->query('SELECT COUNT(*) AS row_count FROM ' . $table)->fetch();

    return is_array($row) ? (int) ($row['row_count'] ?? 0) : 0;
}

function sr_asset_exchange_runtime_set_module_setting(PDO $pdo, string $moduleKey, string $settingKey, string $settingValue, string $valueType): void
{
    $stmt = $pdo->prepare('SELECT id FROM sr_modules WHERE module_key = :module_key LIMIT 1');
    $stmt->execute(['module_key' => $moduleKey]);
    $module = $stmt->fetch();
    if (!is_array($module)) {
        sr_asset_exchange_runtime_error('Fixture module must exist before setting update: ' . $moduleKey);
        return;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON CONFLICT(module_id, setting_key) DO UPDATE SET
            setting_value = excluded.setting_value,
            value_type = excluded.value_type,
            updated_at = excluded.updated_at'
    );
    $stmt->execute([
        'module_id' => (int) $module['id'],
        'setting_key' => $settingKey,
        'setting_value' => $settingValue,
        'value_type' => $valueType,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    sr_clear_module_settings_cache($moduleKey);
}

function sr_asset_exchange_runtime_success_case(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_asset_exchange_runtime_schema($pdo);
    sr_asset_exchange_runtime_seed($pdo, 123, 500);

    $policy = sr_asset_exchange_runtime_policy();
    $quote = sr_asset_exchange_quote($pdo, $policy, 123, 100);
    sr_asset_exchange_runtime_assert((int) ($quote['deposit_before_fee'] ?? 0) === 100, 'Asset exchange quote must calculate pre-fee deposit amount.');
    sr_asset_exchange_runtime_assert((int) ($quote['fee_amount'] ?? 0) === 10, 'Asset exchange quote must calculate fee amount.');
    sr_asset_exchange_runtime_assert((int) ($quote['deposit_amount'] ?? 0) === 90, 'Asset exchange quote must calculate final deposit amount.');

    $logId = sr_asset_exchange_execute($pdo, $policy, 123, 100, 9001);
    sr_asset_exchange_runtime_assert($logId > 0, 'Asset exchange execution must return a log id.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_balance($pdo, 'sr_reward_balances', 123) === 400, 'Asset exchange execution must deduct the source balance.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_balance($pdo, 'sr_deposit_balances', 123) === 90, 'Asset exchange execution must add only the final post-fee destination balance.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_count($pdo, 'sr_reward_transactions') === 1, 'Asset exchange execution must create one source transaction.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_count($pdo, 'sr_deposit_transactions') === 2, 'Asset exchange execution must create destination deposit and fee transactions.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_count($pdo, 'sr_asset_exchange_logs') === 1, 'Asset exchange execution must create one completed log.');

    $log = $pdo->query('SELECT * FROM sr_asset_exchange_logs LIMIT 1')->fetch();
    sr_asset_exchange_runtime_assert(is_array($log), 'Asset exchange execution log must be readable.');
    if (is_array($log)) {
        sr_asset_exchange_runtime_assert((string) ($log['status'] ?? '') === 'completed', 'Asset exchange execution log must be completed.');
        sr_asset_exchange_runtime_assert((int) ($log['request_amount'] ?? 0) === 100, 'Asset exchange execution log must store request amount.');
        sr_asset_exchange_runtime_assert((int) ($log['deposit_amount'] ?? 0) === 90, 'Asset exchange execution log must store final post-fee deposit amount.');
        sr_asset_exchange_runtime_assert((int) ($log['fee_amount'] ?? 0) === 10, 'Asset exchange execution log must store fee amount.');
        sr_asset_exchange_runtime_assert((int) ($log['from_transaction_id'] ?? 0) > 0, 'Asset exchange execution log must link the source transaction.');
        sr_asset_exchange_runtime_assert((int) ($log['to_transaction_id'] ?? 0) > 0, 'Asset exchange execution log must link the destination transaction.');
        sr_asset_exchange_runtime_assert((int) ($log['fee_transaction_id'] ?? 0) > 0, 'Asset exchange execution log must link the fee transaction.');
    }
}

function sr_asset_exchange_runtime_rollback_case(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_asset_exchange_runtime_schema($pdo, false);
    sr_asset_exchange_runtime_seed($pdo, 123, 500);

    try {
        sr_asset_exchange_execute($pdo, sr_asset_exchange_runtime_policy(), 123, 100, 9001);
        sr_asset_exchange_runtime_error('Asset exchange execution must fail when the destination ledger write fails.');
    } catch (Throwable $exception) {
        sr_asset_exchange_runtime_assert(str_contains($exception->getMessage(), 'sr_deposit_transactions'), 'Asset exchange rollback fixture must fail at the destination ledger write.');
    }

    sr_asset_exchange_runtime_assert(!$pdo->inTransaction(), 'Asset exchange execution must leave no open transaction after rollback.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_balance($pdo, 'sr_reward_balances', 123) === 500, 'Asset exchange rollback must restore the source balance.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_balance($pdo, 'sr_deposit_balances', 123) === 0, 'Asset exchange rollback must leave the destination balance unchanged.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_count($pdo, 'sr_reward_transactions') === 0, 'Asset exchange rollback must leave no source transaction.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_count($pdo, 'sr_asset_exchange_logs') === 0, 'Asset exchange rollback must leave no completed log.');
}

function sr_asset_exchange_runtime_failure_log_case(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_asset_exchange_runtime_schema($pdo);
    sr_asset_exchange_runtime_seed($pdo, 123, 500);

    $reason = str_repeat('실패 사유 ', 80);
    $logId = sr_asset_exchange_record_failure($pdo, sr_asset_exchange_runtime_policy(), 123, 100, $reason, 9001);
    sr_asset_exchange_runtime_assert(is_int($logId) && $logId > 0, 'Asset exchange failure log must return a log id.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_balance($pdo, 'sr_reward_balances', 123) === 500, 'Asset exchange failure log must not change the source balance.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_balance($pdo, 'sr_deposit_balances', 123) === 0, 'Asset exchange failure log must not change the destination balance.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_count($pdo, 'sr_reward_transactions') === 0, 'Asset exchange failure log must not create source transactions.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_count($pdo, 'sr_deposit_transactions') === 0, 'Asset exchange failure log must not create destination transactions.');

    $stmt = $pdo->prepare('SELECT * FROM sr_asset_exchange_logs WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $logId]);
    $log = $stmt->fetch();
    sr_asset_exchange_runtime_assert(is_array($log), 'Asset exchange failure log must be readable.');
    if (is_array($log)) {
        sr_asset_exchange_runtime_assert((string) ($log['status'] ?? '') === 'failed', 'Asset exchange failure log must store failed status.');
        sr_asset_exchange_runtime_assert((int) ($log['request_amount'] ?? 0) === 100, 'Asset exchange failure log must store request amount.');
        sr_asset_exchange_runtime_assert((int) ($log['deposit_amount'] ?? 0) === 0, 'Asset exchange failure log must not store a destination amount.');
        sr_asset_exchange_runtime_assert((int) ($log['fee_amount'] ?? 0) === 0, 'Asset exchange failure log must not store a fee amount.');
        sr_asset_exchange_runtime_assert((string) ($log['failure_reason'] ?? '') !== '', 'Asset exchange failure log must store a failure reason.');
        $failureReasonLength = function_exists('mb_strlen') ? mb_strlen((string) ($log['failure_reason'] ?? '')) : strlen((string) ($log['failure_reason'] ?? ''));
        sr_asset_exchange_runtime_assert($failureReasonLength <= 255, 'Asset exchange failure log must clamp failure reason length.');
        sr_asset_exchange_runtime_assert((int) ($log['created_by_account_id'] ?? 0) === 9001, 'Asset exchange failure log must store operator evidence.');
        sr_asset_exchange_runtime_assert(($log['from_transaction_id'] ?? null) === null && ($log['to_transaction_id'] ?? null) === null && ($log['fee_transaction_id'] ?? null) === null, 'Asset exchange failure log must not link nonexistent transactions.');
    }
}

function sr_asset_exchange_runtime_notification_settings_case(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_asset_exchange_runtime_schema($pdo);
    sr_asset_exchange_runtime_seed($pdo, 123, 500);

    $groups = sr_asset_exchange_notification_groups($pdo);
    foreach (['point', 'reward', 'deposit'] as $moduleKey) {
        sr_asset_exchange_runtime_assert(isset($groups[$moduleKey]), 'Asset exchange notification settings must expose ' . $moduleKey . ' cases.');
        if (!isset($groups[$moduleKey])) {
            continue;
        }
        $cases = (array) ($groups[$moduleKey]['cases'] ?? []);
        sr_asset_exchange_runtime_assert(count($cases) === 3, 'Asset exchange notification settings must expose only exchange cases for ' . $moduleKey . '.');
        foreach (sr_asset_exchange_notification_event_keys() as $eventKey) {
            $found = false;
            foreach ($cases as $case) {
                if ((string) ($case['event_key'] ?? '') === $eventKey) {
                    $found = true;
                    break;
                }
            }
            sr_asset_exchange_runtime_assert($found, 'Asset exchange notification settings must expose ' . $eventKey . ' for ' . $moduleKey . '.');
        }
        foreach ($cases as $case) {
            sr_asset_exchange_runtime_assert(str_starts_with((string) ($case['event_key'] ?? ''), 'transaction.exchange_'), 'Asset exchange notification settings must not expose non-exchange cases for ' . $moduleKey . '.');
        }
    }

    $pointSettings = sr_point_settings($pdo);
    $pointCases = sr_point_notification_case_settings_from_value($pointSettings['notification_cases'] ?? []);
    $pointCases['transaction_grant'] = [
        'event_key' => 'transaction.grant',
        'enabled' => true,
        'channels' => ['email'],
    ];
    $pointCasesJson = json_encode($pointCases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    sr_asset_exchange_runtime_assert(is_string($pointCasesJson), 'Asset exchange notification fixture must encode point cases.');
    sr_asset_exchange_runtime_set_module_setting($pdo, 'point', 'notification_cases', is_string($pointCasesJson) ? $pointCasesJson : '{}', 'json');

    $groups = sr_asset_exchange_notification_groups($pdo);
    $pointGroup = $groups['point'] ?? null;
    sr_asset_exchange_runtime_assert(is_array($pointGroup), 'Asset exchange notification settings must reload point case settings.');
    if (is_array($pointGroup)) {
        $moduleCaseSettings = is_array($pointGroup['all_case_settings'] ?? null) ? $pointGroup['all_case_settings'] : [];
        $moduleCaseSettings['transaction_exchange_in'] = [
            'event_key' => 'transaction.exchange_in',
            'enabled' => true,
            'channels' => ['site'],
        ];
        $moduleCaseSettings['transaction_exchange_out'] = [
            'event_key' => 'transaction.exchange_out',
            'enabled' => false,
            'channels' => ['site'],
        ];
        $moduleCaseSettings['transaction_exchange_fee'] = [
            'event_key' => 'transaction.exchange_fee',
            'enabled' => true,
            'channels' => ['site'],
        ];

        $moduleCaseSettings = sr_point_notification_case_settings_from_value($moduleCaseSettings);
        $moduleCaseSettingsJson = json_encode($moduleCaseSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        sr_asset_exchange_runtime_assert(is_string($moduleCaseSettingsJson), 'Asset exchange notification fixture must encode changed point cases.');
        sr_asset_exchange_runtime_set_module_setting($pdo, 'point', 'notification_cases', is_string($moduleCaseSettingsJson) ? $moduleCaseSettingsJson : '{}', 'json');
    }

    $savedPointCases = sr_point_notification_case_settings_from_value(sr_point_settings($pdo)['notification_cases'] ?? []);
    sr_asset_exchange_runtime_assert(!empty($savedPointCases['transaction_grant']['enabled']), 'Asset exchange notification settings save must preserve non-exchange point cases.');
    sr_asset_exchange_runtime_assert((array) ($savedPointCases['transaction_grant']['channels'] ?? []) === ['email'], 'Asset exchange notification settings save must preserve non-exchange point channels.');
    sr_asset_exchange_runtime_assert(!empty($savedPointCases['transaction_exchange_in']['enabled']), 'Asset exchange notification settings save must enable changed exchange-in cases.');
    sr_asset_exchange_runtime_assert(empty($savedPointCases['transaction_exchange_out']['enabled']), 'Asset exchange notification settings save must disable changed exchange-out cases.');
    sr_asset_exchange_runtime_assert(!empty($savedPointCases['transaction_exchange_fee']['enabled']), 'Asset exchange notification settings save must enable changed exchange-fee cases.');
}

function sr_asset_exchange_runtime_notification_application_case(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_asset_exchange_runtime_schema($pdo);
    sr_asset_exchange_runtime_seed($pdo, 123, 500);

    $pointCases = sr_point_default_notification_case_settings();
    $pointCases['transaction_exchange_out']['enabled'] = true;
    $pointCases['transaction_exchange_out']['channels'] = ['site'];
    $pointCasesJson = json_encode($pointCases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    sr_asset_exchange_runtime_set_module_setting($pdo, 'point', 'notification_cases', is_string($pointCasesJson) ? $pointCasesJson : '{}', 'json');

    $depositCases = sr_deposit_default_notification_case_settings();
    $depositCases['transaction_exchange_in']['enabled'] = false;
    $depositCases['transaction_exchange_fee']['enabled'] = true;
    $depositCases['transaction_exchange_fee']['channels'] = ['site', 'email'];
    $depositCasesJson = json_encode($depositCases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    sr_asset_exchange_runtime_set_module_setting($pdo, 'deposit', 'notification_cases', is_string($depositCasesJson) ? $depositCasesJson : '{}', 'json');

    $logId = sr_asset_exchange_execute($pdo, sr_asset_exchange_runtime_policy(), 123, 100, 9001);
    $stmt = $pdo->prepare('SELECT from_transaction_id, to_transaction_id, fee_transaction_id FROM sr_asset_exchange_logs WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $logId]);
    $log = $stmt->fetch();
    sr_asset_exchange_runtime_assert(is_array($log), 'Asset exchange notification application fixture must store transaction ids.');
    if (is_array($log)) {
        sr_reward_notify_transaction_created($pdo, (int) ($log['from_transaction_id'] ?? 0));
        sr_deposit_notify_transaction_created($pdo, (int) ($log['to_transaction_id'] ?? 0));
        sr_deposit_notify_transaction_created($pdo, (int) ($log['fee_transaction_id'] ?? 0));
    }

    sr_asset_exchange_runtime_assert(
        (int) $pdo->query("SELECT COUNT(*) FROM sr_notifications WHERE source_module_key = 'reward' AND event_key = 'transaction.exchange_out'")->fetchColumn() === 1,
        'Reward exchange-out notification setting must enable the actual reward notification event.'
    );
    sr_asset_exchange_runtime_assert(
        (int) $pdo->query("SELECT COUNT(*) FROM sr_notifications WHERE source_module_key = 'deposit' AND event_key = 'transaction.exchange_in'")->fetchColumn() === 0,
        'Deposit exchange-in notification setting must suppress the actual deposit notification event.'
    );
    sr_asset_exchange_runtime_assert(
        (int) $pdo->query("SELECT COUNT(*) FROM sr_notifications WHERE source_module_key = 'deposit' AND event_key = 'transaction.exchange_fee'")->fetchColumn() === 1,
        'Deposit exchange-fee notification setting must enable the actual deposit notification event.'
    );
    sr_asset_exchange_runtime_assert(
        (int) $pdo->query("SELECT COUNT(*) FROM sr_notification_deliveries WHERE channel = 'email' AND recipient = 'asset-member@example.test'")->fetchColumn() === 1,
        'Deposit exchange-fee notification setting must apply configured email delivery channel.'
    );
    $rewardNotification = $pdo->query("SELECT body_text FROM sr_notifications WHERE source_module_key = 'reward' AND event_key = 'transaction.exchange_out' LIMIT 1")->fetch();
    sr_asset_exchange_runtime_assert(
        is_array($rewardNotification) && str_contains((string) ($rewardNotification['body_text'] ?? ''), '환전 출금'),
        'Reward exchange notification metadata must render the exchange transaction type label.'
    );
    sr_asset_exchange_runtime_assert(sr_point_transaction_type_label('exchange_in') === '환전 입금', 'Point exchange-in notification metadata label must be localized.');
    sr_asset_exchange_runtime_assert(sr_point_transaction_type_label('exchange_out') === '환전 출금', 'Point exchange-out notification metadata label must be localized.');
    sr_asset_exchange_runtime_assert(sr_point_transaction_type_label('exchange_fee') === '환전 수수료', 'Point exchange-fee notification metadata label must be localized.');
}

function sr_asset_exchange_runtime_relative_value_case(): void
{
    $settings = array_merge(sr_asset_exchange_default_settings(), [
        'policy_default_status' => 'enabled',
        'relative_value_point' => '1',
        'relative_value_reward' => '2',
        'relative_value_deposit' => '3',
        'policy_default_min_amount' => '5',
        'policy_default_rounding_mode' => 'floor',
    ]);
    $rowsBySlot = [];
    foreach (sr_asset_exchange_canonical_policy_rows_from_settings($settings) as $row) {
        $rowsBySlot[sr_asset_exchange_policy_slot_key((string) $row['from_module_key'], (string) $row['to_module_key'])] = $row;
    }

    sr_asset_exchange_runtime_assert(count($rowsBySlot) === 6, 'Asset exchange relative values must derive the fixed six canonical policy rows.');
    sr_asset_exchange_runtime_assert((int) ($rowsBySlot['point>reward']['rate_numerator'] ?? 0) === 2, 'Point to reward derived ratio must use the destination exchange rate as numerator.');
    sr_asset_exchange_runtime_assert((int) ($rowsBySlot['point>reward']['rate_denominator'] ?? 0) === 1, 'Point to reward derived ratio must use the source exchange rate as denominator.');
    sr_asset_exchange_runtime_assert((int) ($rowsBySlot['reward>point']['rate_numerator'] ?? 0) === 1, 'Reward to point derived ratio must invert the exchange rates.');
    sr_asset_exchange_runtime_assert((int) ($rowsBySlot['reward>point']['rate_denominator'] ?? 0) === 2, 'Reward to point derived ratio must invert the exchange rates.');
    sr_asset_exchange_runtime_assert((int) ($rowsBySlot['deposit>reward']['rate_numerator'] ?? 0) === 2, 'Deposit to reward derived ratio must use reward exchange rate.');
    sr_asset_exchange_runtime_assert((int) ($rowsBySlot['deposit>reward']['rate_denominator'] ?? 0) === 3, 'Deposit to reward derived ratio must use deposit exchange rate.');

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_asset_exchange_runtime_schema($pdo);
    sr_asset_exchange_runtime_seed($pdo, 123, 500);
    $safeSettings = array_merge($settings, [
        'relative_value_point' => '1',
        'relative_value_reward' => '1',
        'relative_value_deposit' => '1',
    ]);
    sr_asset_exchange_sync_canonical_policies($pdo, $safeSettings);
    $pointToRewardPolicy = sr_asset_exchange_policy_by_slot($pdo, 'point', 'reward');
    sr_asset_exchange_save_policy($pdo, [
        'id' => is_array($pointToRewardPolicy) ? (int) ($pointToRewardPolicy['id'] ?? 0) : 0,
        'from_module_key' => 'point',
        'to_module_key' => 'reward',
        'status' => 'enabled',
        'min_amount' => '1',
        'rounding_mode' => 'ceil',
        'fee_trigger' => 'none',
        'fee_basis' => 'to_amount',
        'fee_type' => 'rate',
        'fee_rate_numerator' => '0',
        'fee_fixed_amount' => '0',
    ]);
    $rewardToPointPolicy = sr_asset_exchange_policy_by_slot($pdo, 'reward', 'point');
    sr_asset_exchange_save_policy($pdo, [
        'id' => is_array($rewardToPointPolicy) ? (int) ($rewardToPointPolicy['id'] ?? 0) : 0,
        'from_module_key' => 'reward',
        'to_module_key' => 'point',
        'status' => 'enabled',
        'min_amount' => '1',
        'rounding_mode' => 'ceil',
        'fee_trigger' => 'none',
        'fee_basis' => 'to_amount',
        'fee_type' => 'rate',
        'fee_rate_numerator' => '0',
        'fee_fixed_amount' => '0',
    ]);

    $invalidSettings = $safeSettings;
    $invalidSettings['relative_value_reward'] = '2';
    try {
        sr_asset_exchange_sync_canonical_policies($pdo, $invalidSettings);
        sr_asset_exchange_runtime_error('Asset exchange relative value sync must reject enabled fee-free ceil cycles that increase value.');
    } catch (InvalidArgumentException $exception) {
        sr_asset_exchange_runtime_assert(
            str_contains($exception->getMessage(), '반복 환전 가치가 증가'),
            'Asset exchange relative value sync rejection must explain the value-increasing cycle.'
        );
    }
}

function sr_asset_exchange_runtime_dynamic_asset_label_case(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_asset_exchange_runtime_schema($pdo);
    sr_asset_exchange_runtime_seed($pdo, 123, 500);
    sr_asset_exchange_runtime_set_module_setting($pdo, 'point', 'display_name', '마일리지', 'string');
    sr_asset_exchange_runtime_set_module_setting($pdo, 'point', 'unit_label', 'M', 'string');
    sr_asset_exchange_runtime_set_module_setting($pdo, 'reward', 'display_name', '캐시백', 'string');
    sr_asset_exchange_runtime_set_module_setting($pdo, 'reward', 'unit_label', 'CB', 'string');
    sr_asset_exchange_runtime_set_module_setting($pdo, 'deposit', 'display_name', '충전금', 'string');
    sr_asset_exchange_runtime_set_module_setting($pdo, 'deposit', 'unit_label', 'CH', 'string');

    $assets = sr_asset_exchange_assets($pdo);
    sr_asset_exchange_runtime_assert(
        (string) ($assets['point']['label'] ?? '') === '마일리지',
        'Asset exchange assets must use the point display name from point settings.'
    );
    sr_asset_exchange_runtime_assert(
        sr_asset_exchange_asset_label($assets, 'point') === '마일리지',
        'Asset exchange UI labels must resolve the configured point display name.'
    );
    sr_asset_exchange_runtime_assert(
        (string) ($assets['point']['unit_label'] ?? '') === 'M',
        'Asset exchange assets must use the point unit label from point settings.'
    );
    sr_asset_exchange_runtime_assert(
        (string) ($assets['reward']['label'] ?? '') === '캐시백',
        'Asset exchange assets must use the reward display name from reward settings.'
    );
    sr_asset_exchange_runtime_assert(
        (string) ($assets['reward']['unit_label'] ?? '') === 'CB',
        'Asset exchange assets must use the reward unit label from reward settings.'
    );
    sr_asset_exchange_runtime_assert(
        (string) ($assets['deposit']['label'] ?? '') === '충전금',
        'Asset exchange assets must use the deposit display name from deposit settings.'
    );
    sr_asset_exchange_runtime_assert(
        (string) ($assets['deposit']['unit_label'] ?? '') === 'CH',
        'Asset exchange assets must use the deposit unit label from deposit settings.'
    );

    $memberAssetRows = sr_public_layout_member_asset_rows($pdo, 123);
    $memberAssetRowByPath = static function (array $rows, string $path): array {
        foreach ($rows as $row) {
            if (str_ends_with((string) ($row['url'] ?? ''), $path)) {
                return $row;
            }
        }

        return [];
    };
    $pointMemberAssetRow = $memberAssetRowByPath($memberAssetRows, '/account/points');
    $rewardMemberAssetRow = $memberAssetRowByPath($memberAssetRows, '/account/rewards');
    $depositMemberAssetRow = $memberAssetRowByPath($memberAssetRows, '/account/deposits');
    sr_asset_exchange_runtime_assert(
        (string) ($pointMemberAssetRow['label'] ?? '') === '마일리지'
            && (string) ($pointMemberAssetRow['value'] ?? '') === '777M',
        'Public member dropdown must use the configured point display name and unit label.'
    );
    sr_asset_exchange_runtime_assert(
        (string) ($rewardMemberAssetRow['label'] ?? '') === '캐시백'
            && (string) ($rewardMemberAssetRow['value'] ?? '') === '500CB',
        'Public member dropdown must use the configured reward display name and unit label.'
    );
    sr_asset_exchange_runtime_assert(
        (string) ($depositMemberAssetRow['label'] ?? '') === '충전금'
            && (string) ($depositMemberAssetRow['value'] ?? '') === '0CH',
        'Public member dropdown must use the configured deposit display name and unit label.'
    );
}

function sr_asset_exchange_runtime_canonical_sync_case(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_asset_exchange_runtime_schema($pdo);

    $now = sr_now();
    $insertPolicy = $pdo->prepare(
        'INSERT INTO sr_asset_exchange_policies
            (from_module_key, to_module_key, status, rate_numerator, rate_denominator, min_amount, max_amount, rounding_mode,
             fee_trigger, fee_basis, fee_rate_numerator, fee_rate_denominator, fee_fixed_amount, fee_min_amount, fee_max_amount,
             sort_order, created_at, updated_at)
         VALUES
            (:from_module_key, :to_module_key, :status, :rate_numerator, :rate_denominator, :min_amount, NULL, :rounding_mode,
             :fee_trigger, :fee_basis, 0, 100, 0, NULL, NULL, :sort_order, :created_at, :updated_at)'
    );
    $insertPolicy->execute([
        'from_module_key' => 'coupon',
        'to_module_key' => 'deposit',
        'status' => 'enabled',
        'rate_numerator' => 1,
        'rate_denominator' => 1,
        'min_amount' => 1,
        'rounding_mode' => 'floor',
        'fee_trigger' => 'none',
        'fee_basis' => 'to_amount',
        'sort_order' => 99,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $legacyPolicyId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO sr_asset_exchange_logs
            (exchange_group_id, policy_id, account_id, from_module_key, to_module_key, request_amount,
             rate_numerator, rate_denominator, rounding_mode, deposit_amount, fee_amount, fee_trigger, fee_basis,
             status, failure_reason, created_at)
         VALUES
            (:exchange_group_id, :policy_id, 123, \'coupon\', \'deposit\', 10, 1, 1, \'floor\', 10, 0, \'none\', \'to_amount\', \'completed\', \'\', :created_at)'
    )->execute([
        'exchange_group_id' => 'legacy_policy_log',
        'policy_id' => $legacyPolicyId,
        'created_at' => $now,
    ]);

    $settings = array_merge(sr_asset_exchange_default_settings(), [
        'policy_default_status' => 'enabled',
        'relative_value_point' => '1',
        'relative_value_reward' => '2',
        'relative_value_deposit' => '3',
        'policy_default_min_amount' => '5',
    ]);
    sr_asset_exchange_sync_canonical_policies($pdo, $settings);

    sr_asset_exchange_runtime_assert(
        (int) $pdo->query("SELECT COUNT(*) FROM sr_asset_exchange_policies WHERE from_module_key = 'coupon'")->fetchColumn() === 0,
        'Asset exchange canonical sync must remove noncanonical policy rows.'
    );
    sr_asset_exchange_runtime_assert(
        $pdo->query("SELECT policy_id FROM sr_asset_exchange_logs WHERE exchange_group_id = 'legacy_policy_log'")->fetchColumn() === null,
        'Asset exchange canonical sync must preserve old logs while clearing removed policy references.'
    );
    sr_asset_exchange_runtime_assert(
        (int) $pdo->query('SELECT COUNT(*) FROM sr_asset_exchange_policies')->fetchColumn() === 6,
        'Asset exchange canonical sync must keep exactly six policy rows.'
    );
    $pointToReward = $pdo->query("SELECT * FROM sr_asset_exchange_policies WHERE from_module_key = 'point' AND to_module_key = 'reward'")->fetch();
    sr_asset_exchange_runtime_assert(is_array($pointToReward), 'Asset exchange canonical sync must create the point to reward row.');
    if (is_array($pointToReward)) {
        sr_asset_exchange_runtime_assert((string) ($pointToReward['status'] ?? '') === 'enabled', 'Asset exchange canonical sync must apply the common status.');
        sr_asset_exchange_runtime_assert((int) ($pointToReward['rate_numerator'] ?? 0) === 2, 'Asset exchange canonical sync must apply the destination exchange rate.');
        sr_asset_exchange_runtime_assert((int) ($pointToReward['rate_denominator'] ?? 0) === 1, 'Asset exchange canonical sync must apply the source exchange rate.');
        sr_asset_exchange_runtime_assert((int) ($pointToReward['min_amount'] ?? 0) === 5, 'Asset exchange canonical sync must apply common minimum amount.');
        sr_asset_exchange_runtime_assert((int) ($pointToReward['sort_order'] ?? -1) === 0, 'Asset exchange canonical sync must apply fixed canonical sort order.');
    }

    $rewardToDepositPolicy = sr_asset_exchange_policy_by_slot($pdo, 'reward', 'deposit');
    sr_asset_exchange_save_policy($pdo, [
        'id' => is_array($rewardToDepositPolicy) ? (int) ($rewardToDepositPolicy['id'] ?? 0) : 0,
        'from_module_key' => 'reward',
        'to_module_key' => 'deposit',
        'status' => 'disabled',
        'min_amount' => '25',
        'max_amount' => '100',
        'rounding_mode' => 'round',
        'fee_trigger' => 'always',
        'fee_basis' => 'to_amount',
        'fee_type' => 'rate',
        'fee_rate_numerator' => '5',
        'fee_fixed_amount' => '0',
        'fee_min_amount' => '1',
        'fee_max_amount' => '10',
    ]);

    $settings['policy_default_status'] = 'disabled';
    $settings['relative_value_reward'] = '4';
    $settings['policy_default_min_amount'] = '9';
    $settings['policy_default_rounding_mode'] = 'ceil';
    sr_asset_exchange_sync_canonical_policies($pdo, $settings);
    $rewardToDeposit = $pdo->query("SELECT * FROM sr_asset_exchange_policies WHERE from_module_key = 'reward' AND to_module_key = 'deposit'")->fetch();
    sr_asset_exchange_runtime_assert(is_array($rewardToDeposit), 'Asset exchange canonical sync must keep reward to deposit row.');
    if (is_array($rewardToDeposit)) {
        sr_asset_exchange_runtime_assert((string) ($rewardToDeposit['status'] ?? '') === 'disabled', 'Asset exchange canonical sync must preserve per-direction status.');
        sr_asset_exchange_runtime_assert((int) ($rewardToDeposit['rate_numerator'] ?? 0) === 3, 'Asset exchange canonical sync must keep destination exchange rates.');
        sr_asset_exchange_runtime_assert((int) ($rewardToDeposit['rate_denominator'] ?? 0) === 4, 'Asset exchange canonical sync must update changed source exchange rates.');
        sr_asset_exchange_runtime_assert((int) ($rewardToDeposit['min_amount'] ?? 0) === 25, 'Asset exchange canonical sync must preserve per-direction minimum amount.');
        sr_asset_exchange_runtime_assert((string) ($rewardToDeposit['rounding_mode'] ?? '') === 'round', 'Asset exchange canonical sync must preserve per-direction rounding.');
        sr_asset_exchange_runtime_assert((string) ($rewardToDeposit['fee_trigger'] ?? '') === 'always', 'Asset exchange canonical sync must preserve per-direction fee condition.');
        sr_asset_exchange_runtime_assert((int) ($rewardToDeposit['sort_order'] ?? -1) === 3, 'Asset exchange canonical sync must keep fixed canonical sort order.');
    }
}

function sr_asset_exchange_runtime_zero_result_policy_guard_case(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_asset_exchange_runtime_schema($pdo);
    sr_asset_exchange_runtime_seed($pdo, 123, 500);

    foreach ([
        'relative_value_point' => '1',
        'relative_value_reward' => '2',
        'relative_value_deposit' => '3',
    ] as $settingKey => $settingValue) {
        sr_asset_exchange_runtime_set_module_setting($pdo, 'asset_exchange', $settingKey, $settingValue, 'string');
    }
    sr_asset_exchange_sync_canonical_policies($pdo, array_merge(sr_asset_exchange_default_settings(), [
        'policy_default_status' => 'disabled',
        'relative_value_point' => '1',
        'relative_value_reward' => '2',
        'relative_value_deposit' => '3',
    ]));

    $rewardToPointPolicy = sr_asset_exchange_policy_by_slot($pdo, 'reward', 'point');
    try {
        sr_asset_exchange_save_policy($pdo, [
            'id' => is_array($rewardToPointPolicy) ? (int) ($rewardToPointPolicy['id'] ?? 0) : 0,
            'from_module_key' => 'reward',
            'to_module_key' => 'point',
            'status' => 'enabled',
            'min_amount' => '1',
            'rounding_mode' => 'floor',
            'fee_trigger' => 'none',
            'fee_basis' => 'to_amount',
            'fee_type' => 'rate',
            'fee_rate_numerator' => '0',
            'fee_fixed_amount' => '0',
        ]);
        sr_asset_exchange_runtime_error('Asset exchange policy save must reject enabled policies whose minimum request deposits zero.');
    } catch (InvalidArgumentException $exception) {
        sr_asset_exchange_runtime_assert(
            str_contains($exception->getMessage(), '입금 결과가 0'),
            'Asset exchange zero-result policy rejection must explain the zero deposit result.'
        );
    }

    $pointToRewardPolicy = sr_asset_exchange_policy_by_slot($pdo, 'point', 'reward');
    try {
        sr_asset_exchange_save_policy($pdo, [
            'id' => is_array($pointToRewardPolicy) ? (int) ($pointToRewardPolicy['id'] ?? 0) : 0,
            'from_module_key' => 'point',
            'to_module_key' => 'reward',
            'status' => 'enabled',
            'min_amount' => '1',
            'rounding_mode' => 'floor',
            'fee_trigger' => 'always',
            'fee_basis' => 'to_amount',
            'fee_type' => 'fixed',
            'fee_rate_numerator' => '0',
            'fee_fixed_amount' => '2',
        ]);
        sr_asset_exchange_runtime_error('Asset exchange policy save must reject enabled policies whose minimum request becomes zero after fees.');
    } catch (InvalidArgumentException $exception) {
        sr_asset_exchange_runtime_assert(
            str_contains($exception->getMessage(), '수수료 차감 후 입금 결과가 0 이하'),
            'Asset exchange zero-result fee rejection must explain the post-fee zero result.'
        );
    }
}

function sr_asset_exchange_runtime_disabled_setting_case(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_asset_exchange_runtime_schema($pdo);
    sr_asset_exchange_runtime_seed($pdo, 123, 500);

    sr_asset_exchange_sync_canonical_policies($pdo, array_merge(sr_asset_exchange_default_settings(), [
        'policy_default_status' => 'enabled',
    ]));
    sr_asset_exchange_runtime_set_module_setting($pdo, 'asset_exchange', 'exchange_enabled', '0', 'string');

    sr_asset_exchange_runtime_assert(!sr_asset_exchange_enabled($pdo), 'Asset exchange global setting must disable exchange execution.');
    sr_asset_exchange_runtime_assert(!sr_asset_exchange_member_has_available_policy($pdo, 123), 'Asset exchange disabled setting must hide member exchange candidates.');

    try {
        sr_asset_exchange_quote($pdo, sr_asset_exchange_runtime_policy(), 123, 100);
        sr_asset_exchange_runtime_error('Asset exchange quote must fail when global exchange setting is disabled.');
    } catch (InvalidArgumentException $exception) {
        sr_asset_exchange_runtime_assert(
            str_contains($exception->getMessage(), '사용 중지'),
            'Asset exchange disabled quote guard must explain that exchange is disabled.'
        );
    }

    foreach (sr_asset_exchange_policy_slots($pdo, sr_asset_exchange_assets($pdo)) as $slot) {
        sr_asset_exchange_runtime_assert(empty($slot['executable']), 'Asset exchange disabled setting must make every policy slot non-executable.');
    }

    sr_clear_module_settings_cache('asset_exchange');
}

function sr_asset_exchange_runtime_noncanonical_guard_case(): void
{
    $available = sr_asset_exchange_available_policies([
        [
            'id' => 1,
            'from_module_key' => 'point',
            'to_module_key' => 'reward',
            'status' => 'enabled',
            'min_amount' => 1,
        ],
        [
            'id' => 2,
            'from_module_key' => 'coupon',
            'to_module_key' => 'deposit',
            'status' => 'enabled',
            'min_amount' => 1,
        ],
    ], [
        'point' => [],
        'reward' => [],
        'deposit' => [],
        'coupon' => [],
    ]);
    sr_asset_exchange_runtime_assert(count($available) === 1 && (int) ($available[0]['id'] ?? 0) === 1, 'Asset exchange available policies must exclude noncanonical rows.');

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_asset_exchange_runtime_schema($pdo);
    try {
        sr_asset_exchange_quote($pdo, [
            'id' => 2,
            'from_module_key' => 'coupon',
            'to_module_key' => 'deposit',
            'status' => 'enabled',
            'rate_numerator' => 1,
            'rate_denominator' => 1,
            'min_amount' => 1,
            'rounding_mode' => 'floor',
            'fee_trigger' => 'none',
            'fee_basis' => 'to_amount',
        ], 0, 1);
        sr_asset_exchange_runtime_error('Asset exchange quote must fail closed for noncanonical policy rows.');
    } catch (InvalidArgumentException $exception) {
        sr_asset_exchange_runtime_assert(
            str_contains($exception->getMessage(), '고정 환전 조합이 아닌 정책'),
            'Asset exchange noncanonical quote guard must explain the fixed-pair requirement.'
        );
    }
}

if (!extension_loaded('pdo_sqlite')) {
    sr_asset_exchange_runtime_error('pdo_sqlite extension is required for asset exchange runtime checks.');
} else {
    sr_asset_exchange_runtime_success_case();
    sr_asset_exchange_runtime_rollback_case();
    sr_asset_exchange_runtime_failure_log_case();
    sr_asset_exchange_runtime_notification_settings_case();
    sr_asset_exchange_runtime_notification_application_case();
    sr_asset_exchange_runtime_relative_value_case();
    sr_asset_exchange_runtime_dynamic_asset_label_case();
    sr_asset_exchange_runtime_canonical_sync_case();
    sr_asset_exchange_runtime_zero_result_policy_guard_case();
    sr_asset_exchange_runtime_disabled_setting_case();
    sr_asset_exchange_runtime_noncanonical_guard_case();
}

if ($errors !== []) {
    fwrite(STDERR, "asset exchange runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "asset exchange runtime checks completed.\n";
