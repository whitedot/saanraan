#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/helpers.php';
require_once $root . '/modules/asset_ledger/asset-ledger.php';
require_once $root . '/modules/payment_ledger/payment-ledger.php';

$errors = [];

function sr_ledger_contract_check(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

foreach ([
    'modules/asset_ledger/asset-ledger.php' => [
        'sr_asset_ledger_retry_operation',
        'sr_asset_ledger_settlement_kind',
    ],
    'modules/payment_ledger/payment-ledger.php' => [
        'sr_payment_ledger_record_if_enabled',
        'sr_payment_ledger_mark_references_reversed_if_enabled',
        'sr_payment_ledger_coupon_redemption_item',
    ],
] as $contractFile => $functions) {
    sr_ledger_contract_check(is_file($contractFile), '공개 원장 계약 파일이 없습니다: ' . $contractFile);
    foreach ($functions as $function) {
        sr_ledger_contract_check(function_exists($function), '공개 원장 계약 함수를 불러올 수 없습니다: ' . $function);
    }
}

foreach (glob('modules/*/module.php') ?: [] as $moduleFile) {
    $moduleKey = basename(dirname($moduleFile));
    if (in_array($moduleKey, ['asset_ledger', 'payment_ledger'], true)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(dirname($moduleFile), FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        if (!is_string($source)) {
            continue;
        }
        foreach (['asset_ledger', 'payment_ledger'] as $provider) {
            sr_ledger_contract_check(
                !str_contains($source, "/modules/{$provider}/helpers.php"),
                $file->getPathname() . '에서 ' . $provider . ' 내부 helper를 직접 불러옵니다.'
            );
        }
    }
}

$providerContracts = [
    'asset_ledger' => 'asset-ledger.php',
    'payment_ledger' => 'payment-ledger.php',
];
foreach ($providerContracts as $moduleKey => $contractFile) {
    $module = require 'modules/' . $moduleKey . '/module.php';
    $provides = is_array($module['contracts']['provides'] ?? null) ? $module['contracts']['provides'] : [];
    sr_ledger_contract_check(in_array($contractFile, $provides, true), $moduleKey . '가 공개 원장 계약을 선언하지 않았습니다.');
}

$expectedConsumers = [
    'asset-ledger.php' => ['content', 'community', 'point', 'reward', 'deposit'],
    'payment-ledger.php' => ['content', 'community', 'coupon'],
];
foreach ($expectedConsumers as $contractFile => $moduleKeys) {
    foreach ($moduleKeys as $moduleKey) {
        $module = require 'modules/' . $moduleKey . '/module.php';
        $consumes = is_array($module['contracts']['consumes'] ?? null) ? $module['contracts']['consumes'] : [];
        sr_ledger_contract_check(in_array($contractFile, $consumes, true), $moduleKey . '가 ' . $contractFile . ' 소비를 선언하지 않았습니다.');
    }
}

$operationCount = 0;
$pdo = new PDO('sqlite::memory:');
$result = sr_asset_ledger_retry_operation($pdo, static function () use (&$operationCount): array {
    $operationCount += 1;
    return ['ok' => true];
}, 'fixture failure');
sr_ledger_contract_check($operationCount === 1 && !empty($result['ok']), '자산 원장 공개 계약이 정상 operation 결과를 보존하지 않습니다.');
sr_ledger_contract_check(sr_asset_ledger_settlement_kind('grant', 10, 0, '') === 'free', '지급 settlement kind가 free가 아닙니다.');
sr_ledger_contract_check(sr_asset_ledger_settlement_kind('use', 0, 0, '') === 'paid_settled_zero', '0원 차감 settlement kind가 올바르지 않습니다.');
sr_ledger_contract_check(sr_asset_ledger_settlement_kind('use', 10, 0, '') === 'legacy_unknown', 'legacy 차감 settlement kind가 올바르지 않습니다.');

$pdo->exec('CREATE TABLE sr_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, module_key TEXT NOT NULL UNIQUE, status TEXT NOT NULL)');
$pdo->exec("INSERT INTO sr_modules (module_key, status) VALUES ('payment_ledger', 'disabled')");
sr_ledger_contract_check(sr_payment_ledger_record_if_enabled($pdo, [], []) === 0, '비활성 결제 원장 기록은 no-op이어야 합니다.');
sr_ledger_contract_check(sr_payment_ledger_mark_references_reversed_if_enabled($pdo, 1, [['reference_id' => '1']], 'fixture') === sr_payment_ledger_empty_reversal_result(), '비활성 결제 원장 역분개는 빈 결과여야 합니다.');

$couponItem = sr_payment_ledger_coupon_redemption_item([
    'processed' => true,
    'coupon_redemption_id' => 7,
    'coupon_issue_id' => 8,
    'coupon_definition_id' => 9,
    'coupon_type' => 'fixed_discount',
    'discount_amount' => 1200,
    'dedupe_key' => 'fixture:coupon:7',
], 'KRW');
sr_ledger_contract_check((string) ($couponItem['reference_id'] ?? '') === '7', '쿠폰 결제 item 참조가 올바르지 않습니다.');
sr_ledger_contract_check((int) ($couponItem['amount'] ?? 0) === -1200, '쿠폰 결제 item 금액이 올바르지 않습니다.');
sr_ledger_contract_check(sr_payment_ledger_coupon_redemption_item([], 'KRW') === [], '미처리 쿠폰은 결제 item을 만들면 안 됩니다.');

if ($errors !== []) {
    fwrite(STDERR, "ledger public contract checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "ledger public contract checks completed.\n";
