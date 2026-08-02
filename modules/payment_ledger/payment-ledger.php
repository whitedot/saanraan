<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function sr_payment_ledger_empty_reversal_result(): array
{
    return [
        'payment_record_ids' => [],
        'reversed_item_count' => 0,
        'refunded_record_ids' => [],
    ];
}

function sr_payment_ledger_record_if_enabled(PDO $pdo, array $record, array $items): int
{
    if (!function_exists('sr_module_enabled') || !sr_module_enabled($pdo, 'payment_ledger')) {
        return 0;
    }
    if (!sr_payment_ledger_tables_available($pdo)) {
        throw new RuntimeException('결제 기록 기반 테이블이 준비되지 않았습니다.');
    }

    return sr_payment_ledger_record_payment($pdo, $record, $items);
}

function sr_payment_ledger_mark_references_reversed_if_enabled(PDO $pdo, int $accountId, array $references, string $reason, array $excludedItemKinds = []): array
{
    if ($references === [] || !function_exists('sr_module_enabled') || !sr_module_enabled($pdo, 'payment_ledger')) {
        return sr_payment_ledger_empty_reversal_result();
    }
    if (!sr_payment_ledger_tables_available($pdo)) {
        throw new RuntimeException('결제 기록 기반 테이블이 준비되지 않았습니다.');
    }

    return sr_payment_ledger_mark_item_references_reversed($pdo, $accountId, $references, $reason, false, $excludedItemKinds);
}

function sr_payment_ledger_coupon_redemption_item(array $couponResult, string $settlementCurrency): array
{
    $redemptionId = (int) ($couponResult['coupon_redemption_id'] ?? 0);
    if ($redemptionId <= 0 || empty($couponResult['processed'])) {
        return [];
    }

    return [
        'item_kind' => 'coupon_redemption',
        'owner_module' => 'coupon',
        'reference_type' => 'coupon_redemption',
        'reference_id' => (string) $redemptionId,
        'amount' => -max(0, (int) ($couponResult['discount_amount'] ?? 0)),
        'currency_code' => $settlementCurrency,
        'reversible' => true,
        'snapshot' => [
            'coupon_issue_id' => (int) ($couponResult['coupon_issue_id'] ?? 0),
            'coupon_definition_id' => (int) ($couponResult['coupon_definition_id'] ?? 0),
            'coupon_type' => (string) ($couponResult['coupon_type'] ?? ''),
            'dedupe_key' => (string) ($couponResult['dedupe_key'] ?? ''),
        ],
    ];
}
