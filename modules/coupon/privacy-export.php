<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    if ($accountId < 1) {
        return [
            'coupon_issues' => [],
            'coupon_redemptions' => [],
            'coupon_claim_logs' => [],
        ];
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';

    $stmt = $pdo->prepare(
        'SELECT i.id, i.status, i.issued_reason, i.issued_at, i.expires_at, i.used_count,
                d.coupon_key, d.title, d.target_type, d.target_id
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         WHERE i.account_id = :account_id
         ORDER BY i.id DESC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $issues = $stmt->fetchAll();

    $refundColumns = sr_coupon_redemption_refund_columns_available($pdo)
        ? 'r.refunded_at, r.refunded_by_account_id, r.refund_note'
        : 'NULL AS refunded_at, NULL AS refunded_by_account_id, \'\' AS refund_note';
    $pricingColumns = sr_coupon_redemption_pricing_columns_available($pdo)
        ? 'r.amount, r.currency_code, r.asset_unit, r.policy_summary, r.priced_at'
        : '0 AS amount, \'\' AS currency_code, \'\' AS asset_unit, \'\' AS policy_summary, NULL AS priced_at';
    $stmt = $pdo->prepare(
        'SELECT r.id, r.target_type, r.target_id, r.reference_module, r.reference_type, r.reference_id,
                r.status, r.redeemed_at, ' . $refundColumns . ', ' . $pricingColumns . ', d.coupon_key, d.title
         FROM sr_coupon_redemptions r
         INNER JOIN sr_coupon_definitions d ON d.id = r.coupon_definition_id
         WHERE r.account_id = :account_id
         ORDER BY r.id DESC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $redemptions = $stmt->fetchAll();

    $claimLogs = [];
    if (sr_coupon_claim_tables_available($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT l.id, l.claim_source, l.payment_reference_module, l.payment_reference_type, l.payment_reference_id,
                    l.status, l.reserved_until, l.failure_code, l.failure_message, l.created_at, l.issued_at,
                    c.campaign_key, c.title AS campaign_title, d.coupon_key, d.title AS coupon_title
             FROM sr_coupon_claim_logs l
             INNER JOIN sr_coupon_claim_campaigns c ON c.id = l.campaign_id
             INNER JOIN sr_coupon_definitions d ON d.id = l.coupon_definition_id
             WHERE l.account_id = :account_id
             ORDER BY l.id DESC
             LIMIT 1000'
        );
        $stmt->execute(['account_id' => $accountId]);
        $claimLogs = $stmt->fetchAll();
    }

    return [
        'coupon_issues' => $issues,
        'coupon_redemptions' => $redemptions,
        'coupon_claim_logs' => $claimLogs,
    ];
};
