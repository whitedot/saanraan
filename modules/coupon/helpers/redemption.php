<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_coupon_redeem_for_target(PDO $pdo, int $accountId, string $targetType, string $targetId, array $context = []): array
{
    $dedupeKey = sr_coupon_clean_text((string) ($context['dedupe_key'] ?? ''), 160);
    $requestedIssueId = max(0, (int) ($context['coupon_issue_id'] ?? 0));
    if ($accountId <= 0 || $targetType === '' || $dedupeKey === '' || !sr_coupon_usage_enabled($pdo) || !sr_coupon_tables_available($pdo)) {
        return ['allowed' => false, 'processed' => false, 'message' => ''];
    }

    sr_coupon_expire_active_issues($pdo, $accountId);

    if (sr_coupon_has_redemption($pdo, $accountId, $dedupeKey)) {
        return ['allowed' => true, 'processed' => false, 'already_redeemed' => true, 'message' => ''];
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $issueIdCondition = $requestedIssueId > 0 ? ' AND i.id = :issue_id' : '';
        $couponTypeCondition = $requestedIssueId > 0 ? '' : " AND d.coupon_type = 'access'";
        $discountColumns = sr_coupon_definition_discount_columns_available($pdo)
            ? 'd.discount_amount, d.discount_percent, d.discount_currency_code'
            : '0 AS discount_amount, 0 AS discount_percent, \'\' AS discount_currency_code';
        $stmt = $pdo->prepare(
            "SELECT i.*, d.coupon_key, d.title, d.coupon_type, " . $discountColumns . ", d.target_type, d.target_id, d.max_uses_per_issue, d.refundable_policy
             FROM sr_coupon_issues i
             INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
             WHERE i.account_id = :account_id
               AND i.status = 'active'
               AND d.status IN ('active', 'issue_stopped')
               AND (i.starts_at IS NULL OR i.starts_at <= :now_value)
               AND (i.expires_at IS NULL OR i.expires_at >= :now_value)" . $issueIdCondition . $couponTypeCondition . "
             ORDER BY i.expires_at IS NULL ASC, i.expires_at ASC, i.id ASC"
            . sr_coupon_for_update_clause($pdo)
        );
        $params = [
            'account_id' => $accountId,
            'now_value' => sr_now(),
        ];
        if ($requestedIssueId > 0) {
            $params['issue_id'] = $requestedIssueId;
        }
        $stmt->execute($params);
        $selectedIssue = null;
        foreach ($stmt->fetchAll() as $issue) {
            if (sr_coupon_issue_matches_target($issue, $targetType, $targetId)) {
                $selectedIssue = $issue;
                break;
            }
        }

        if (!is_array($selectedIssue)) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }
            return ['allowed' => false, 'processed' => false, 'message' => ''];
        }

        $pricing = sr_coupon_target_pricing($pdo, $targetType, $targetId, $accountId, $context);
        if (array_key_exists('price_amount', $context)) {
            $contextPriceAmount = sr_coupon_nonnegative_int_or_null($context['price_amount']);
            if ($contextPriceAmount === null) {
                $pricing = [
                    'ok' => false,
                    'failure_code' => 'pricing_amount_invalid',
                    'failure_message' => '쿠폰 사용처 가격 금액이 올바르지 않습니다.',
                ];
            } else {
                $pricing['price_amount'] = $contextPriceAmount;
            }
        }
        if (array_key_exists('currency_code', $context)) {
            $pricing['currency_code'] = sr_coupon_clean_currency_code((string) $context['currency_code']);
        }
        if (array_key_exists('policy_summary', $context)) {
            $pricing['policy_summary'] = sr_coupon_clean_text((string) $context['policy_summary'], 255);
        }
        if (!empty($pricing['ok']) && !empty($pricing['already_entitled'])) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return [
                'allowed' => true,
                'processed' => false,
                'already_entitled' => true,
                'message' => '',
            ];
        }
        $discountApplication = sr_coupon_discount_application($selectedIssue, $pricing);
        if (empty($discountApplication['ok'])) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return [
                'allowed' => false,
                'processed' => false,
                'message' => (string) ($discountApplication['message'] ?? ''),
            ];
        }
        $pricing['coupon_type'] = (string) ($discountApplication['coupon_type'] ?? ($selectedIssue['coupon_type'] ?? 'access'));
        $pricing['discount_amount'] = (int) ($discountApplication['discount_amount'] ?? 0);
        $pricing['remaining_amount'] = (int) ($discountApplication['remaining_amount'] ?? 0);

        $now = sr_now();
        $redemptionColumns = [
            'coupon_issue_id',
            'coupon_definition_id',
            'account_id',
            'target_type',
            'target_id',
            'reference_module',
            'reference_type',
            'reference_id',
            'dedupe_key',
            'status',
            'redeemed_at',
            'created_at',
        ];
        $redemptionValues = [
            'coupon_issue_id' => (int) $selectedIssue['id'],
            'coupon_definition_id' => (int) $selectedIssue['coupon_definition_id'],
            'account_id' => $accountId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reference_module' => sr_coupon_clean_key((string) ($context['reference_module'] ?? ''), 60),
            'reference_type' => sr_coupon_clean_text((string) ($context['reference_type'] ?? ''), 80),
            'reference_id' => sr_coupon_clean_text((string) ($context['reference_id'] ?? $targetId), 120),
            'dedupe_key' => $dedupeKey,
            'status' => 'redeemed',
            'redeemed_at' => $now,
            'created_at' => $now,
        ];
        if (sr_coupon_redemption_pricing_columns_available($pdo)) {
            $pricingSnapshot = sr_coupon_redemption_pricing_snapshot_from_result($pricing, $targetType, $targetId);
            foreach (['amount', 'currency_code', 'asset_unit', 'policy_summary', 'priced_at', 'target_snapshot_json'] as $column) {
                $redemptionColumns[] = $column;
                $redemptionValues[$column] = $pricingSnapshot[$column];
            }
        }
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $redemptionColumns);
        $stmt = $pdo->prepare(
            'INSERT INTO sr_coupon_redemptions
                (' . implode(', ', $redemptionColumns) . ')
             VALUES
                (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($redemptionValues);
        $redemptionId = (int) $pdo->lastInsertId();

        $usedCount = (int) $selectedIssue['used_count'] + 1;
        $maxUses = max(1, (int) $selectedIssue['max_uses_per_issue']);
        $newStatus = $usedCount >= $maxUses ? 'used' : 'active';
        $stmt = $pdo->prepare(
            'UPDATE sr_coupon_issues
             SET used_count = :used_count,
                 status = :status,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'used_count' => $usedCount,
            'status' => $newStatus,
            'updated_at' => $now,
            'id' => (int) $selectedIssue['id'],
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        }

        sr_coupon_notify_issue_event($pdo, (int) $selectedIssue['id'], 'redemption.redeemed', null, [
            'redemption_id' => $redemptionId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reference_module' => sr_coupon_clean_key((string) ($context['reference_module'] ?? ''), 60),
            'reference_type' => sr_coupon_clean_text((string) ($context['reference_type'] ?? ''), 80),
            'reference_id' => sr_coupon_clean_text((string) ($context['reference_id'] ?? $targetId), 120),
            'used_count' => $usedCount,
            'max_uses_per_issue' => $maxUses,
            'status_label' => sr_coupon_issue_status_label($newStatus),
            'created_at' => $now,
        ]);

        return [
            'allowed' => true,
            'processed' => true,
            'coupon_issue_id' => (int) $selectedIssue['id'],
            'coupon_definition_id' => (int) $selectedIssue['coupon_definition_id'],
            'coupon_redemption_id' => $redemptionId,
            'dedupe_key' => $dedupeKey,
            'coupon_title' => (string) $selectedIssue['title'],
            'coupon_type' => (string) ($selectedIssue['coupon_type'] ?? 'access'),
            'discount_amount' => (int) ($discountApplication['discount_amount'] ?? 0),
            'remaining_amount' => (int) ($discountApplication['remaining_amount'] ?? 0),
            'full_coverage' => !empty($discountApplication['full_coverage']),
            'message' => '',
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'coupon_redeem_for_target');
        }

        return ['allowed' => false, 'processed' => false, 'message' => ''];
    }
}

function sr_coupon_notify_issue_event(PDO $pdo, int $issueId, string $eventKey, ?int $createdByAccountId = null, array $metadata = [], array $channels = []): ?int
{
    $createAccountEventFunction = sr_coupon_notification_event_function($pdo);
    if ($createAccountEventFunction === '') {
        return null;
    }

    if ($channels === []) {
        $caseSetting = sr_coupon_notification_setting_for_event(sr_coupon_settings($pdo), $eventKey);
        if (is_array($caseSetting)) {
            if (empty($caseSetting['enabled'])) {
                return null;
            }
            $channels = sr_coupon_notification_channels_from_value($caseSetting['channels'] ?? ['site']);
        }
    }

    $issue = sr_coupon_issue_by_id($pdo, $issueId);
    if (!is_array($issue)) {
        return null;
    }

    try {
        $payload = [
            'account_id' => (int) $issue['account_id'],
            'module_key' => 'coupon',
            'event_key' => $eventKey,
            'created_by_account_id' => $createdByAccountId !== null && $createdByAccountId > 0 ? $createdByAccountId : null,
            'metadata' => array_merge(sr_coupon_issue_notification_metadata($issue), $metadata),
        ];
        if ($channels !== []) {
            $channels = sr_coupon_notification_channels_from_value($channels);
            $payload['channels'] = $channels;
        }

        return $createAccountEventFunction($pdo, $payload);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'coupon_issue_notification');
        return null;
    }
}

function sr_coupon_notification_event_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_account_event_function');
}

function sr_coupon_issue_notification_metadata(array $issue): array
{
    return [
        'coupon_issue_id' => (int) ($issue['id'] ?? 0),
        'coupon_definition_id' => (int) ($issue['coupon_definition_id'] ?? 0),
        'coupon_key' => (string) ($issue['coupon_key'] ?? ''),
        'coupon_title' => (string) ($issue['title'] ?? ''),
        'asset_label' => '쿠폰·이용권',
        'status' => (string) ($issue['status'] ?? ''),
        'status_label' => sr_coupon_issue_status_label((string) ($issue['status'] ?? '')),
        'issued_reason' => (string) ($issue['issued_reason'] ?? ''),
        'target_type' => (string) ($issue['target_type'] ?? ''),
        'target_id' => (string) ($issue['target_id'] ?? ''),
        'used_count' => (int) ($issue['used_count'] ?? 0),
        'max_uses_per_issue' => (int) ($issue['max_uses_per_issue'] ?? 1),
        'issued_at' => (string) ($issue['issued_at'] ?? ''),
        'expires_at' => (string) ($issue['expires_at'] ?? ''),
        'created_at' => sr_now(),
    ];
}

function sr_coupon_process_account_withdrawal(PDO $pdo, int $accountId): array
{
    if ($accountId <= 0 || !sr_coupon_tables_available($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT i.id,
                CASE WHEN d.refundable_policy = 'refundable' THEN 'refund_requested' ELSE 'withdrawn_expired' END AS next_status
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         WHERE i.account_id = :account_id
           AND i.status = 'active'"
    );
    $stmt->execute(['account_id' => $accountId]);
    $pendingIssues = $stmt->fetchAll();

    $now = sr_now();
    $stmt = $pdo->prepare(
        "UPDATE sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         SET i.status = CASE WHEN d.refundable_policy = 'refundable' THEN 'refund_requested' ELSE 'withdrawn_expired' END,
             i.updated_at = :updated_at
         WHERE i.account_id = :account_id
           AND i.status = 'active'"
    );
    $stmt->execute([
        'updated_at' => $now,
        'account_id' => $accountId,
    ]);
    $updatedCount = $stmt->rowCount();

    foreach ($pendingIssues as $pendingIssue) {
        $issueId = (int) ($pendingIssue['id'] ?? 0);
        $nextStatus = (string) ($pendingIssue['next_status'] ?? '');
        if ($issueId <= 0 || !in_array($nextStatus, ['withdrawn_expired', 'refund_requested'], true)) {
            continue;
        }

        sr_coupon_notify_issue_event($pdo, $issueId, 'issue.status_updated', null, [
            'status_label' => sr_coupon_issue_status_label($nextStatus),
        ]);
    }

    return [
        'label' => '쿠폰·이용권',
        'amount' => $updatedCount,
        'process' => '소멸/환급 검토',
    ];
}
