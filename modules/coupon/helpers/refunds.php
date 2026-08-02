<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_coupon_refund_redemption(PDO $pdo, int $redemptionId, int $adminAccountId, string $refundNote): array
{
    $refundNote = sr_coupon_clean_text($refundNote, 255);
    if (sr_coupon_redemption_has_partial_domain_payment_unit($pdo, $redemptionId)) {
        throw new RuntimeException('쿠폰 일부 할인과 자산 차감이 함께 처리된 복합결제입니다. 소비 도메인의 결제 내역에서 환불하세요.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $refund = sr_coupon_refund_redemption_state_only($pdo, $redemptionId, $adminAccountId, $refundNote);
        $revokedAccess = sr_coupon_revoke_target_access_or_fail(
            $pdo,
            (string) ($refund['target_type'] ?? ''),
            (int) $refund['account_id'],
            (string) $refund['original_dedupe_key']
        );
        sr_coupon_mark_payment_ledger_redemption_refunded_if_available($pdo, (int) $refund['account_id'], $redemptionId, $refundNote);

        if ($startedTransaction) {
            $pdo->commit();
        }

        $notificationPayload = is_array($refund['notification_payload'] ?? null) ? $refund['notification_payload'] : [];
        $notificationPayload['revoked_access_count'] = $revokedAccess;
        sr_coupon_notify_issue_event($pdo, (int) $refund['coupon_issue_id'], (string) ($refund['notification_event_key'] ?? 'redemption.refunded'), $adminAccountId, $notificationPayload);

        $refund['revoked_access_count'] = $revokedAccess;

        return $refund;
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_coupon_redemption_has_partial_domain_payment_unit(PDO $pdo, int $redemptionId): bool
{
    if ($redemptionId <= 0) {
        return false;
    }

    $checks = [
        ['module' => 'content', 'table' => 'sr_content_view_payment_logs'],
        ['module' => 'community', 'table' => 'sr_community_post_read_payment_logs'],
    ];
    foreach ($checks as $check) {
        if (function_exists('sr_module_enabled') && !sr_module_enabled($pdo, (string) $check['module'])) {
            continue;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT id
                 FROM ' . (string) $check['table'] . '
                 WHERE coupon_redemption_id = :coupon_redemption_id
                   AND payment_type = \'coupon_partial_discount_asset\'
                   AND refund_status = \'\'
                 LIMIT 1'
            );
            $stmt->execute(['coupon_redemption_id' => $redemptionId]);
            if (is_array($stmt->fetch())) {
                return true;
            }
        } catch (Throwable $exception) {
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'coupon_partial_domain_payment_lookup_failed');
            }
        }
    }

    return false;
}

function sr_coupon_refund_redemption_state_only(PDO $pdo, int $redemptionId, int $adminAccountId, string $refundNote, array $options = []): array
{
    $refundNote = sr_coupon_clean_text($refundNote, 255);
    if ($redemptionId <= 0) {
        throw new InvalidArgumentException('환불할 쿠폰 사용 내역을 선택하세요.');
    }
    if ($adminAccountId <= 0) {
        throw new InvalidArgumentException('관리자 계정을 확인할 수 없습니다.');
    }
    if ($refundNote === '') {
        throw new InvalidArgumentException('환불 사유를 입력하세요.');
    }
    if (!sr_coupon_redemption_refund_columns_available($pdo)) {
        throw new InvalidArgumentException('쿠폰 환불 컬럼 업데이트를 먼저 적용하세요.');
    }

    $allowedCouponTypes = $options['allowed_coupon_types'] ?? ['access'];
    if (!is_array($allowedCouponTypes)) {
        $allowedCouponTypes = ['access'];
    }
    $allowedCouponTypes = array_values(array_filter(array_map(
        static fn (mixed $type): string => sr_coupon_clean_key((string) $type, 40),
        $allowedCouponTypes
    ), static fn (string $type): bool => $type !== ''));
    if ($allowedCouponTypes === []) {
        $allowedCouponTypes = ['access'];
    }
    $requireRefundablePolicy = array_key_exists('require_refundable_policy', $options)
        ? !empty($options['require_refundable_policy'])
        : true;

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT r.*, d.refundable_policy, d.coupon_type, d.max_uses_per_issue, d.title, i.status AS issue_status, i.used_count
             FROM sr_coupon_redemptions r
             INNER JOIN sr_coupon_definitions d ON d.id = r.coupon_definition_id
             INNER JOIN sr_coupon_issues i ON i.id = r.coupon_issue_id
             WHERE r.id = :id
             LIMIT 1'
            . sr_coupon_for_update_clause($pdo)
        );
        $stmt->execute(['id' => $redemptionId]);
        $redemption = $stmt->fetch();
        if (!is_array($redemption)) {
            throw new InvalidArgumentException('쿠폰 사용 내역을 찾을 수 없습니다.');
        }
        if ((string) ($redemption['status'] ?? '') !== 'redeemed') {
            throw new InvalidArgumentException('이미 환불되었거나 환불할 수 없는 사용 내역입니다.');
        }
        if ($requireRefundablePolicy && (string) ($redemption['refundable_policy'] ?? '') !== 'refundable') {
            throw new InvalidArgumentException('환급 가능 정책인 쿠폰만 수동 환불할 수 있습니다.');
        }
        if (!in_array((string) ($redemption['coupon_type'] ?? 'access'), $allowedCouponTypes, true)) {
            throw new InvalidArgumentException('접근권 쿠폰 사용 내역만 수동 환불할 수 있습니다. 할인 쿠폰 복합 결제는 소비 도메인 취소 계약이 필요합니다.');
        }

        $now = sr_now();
        $usedCount = max(0, (int) ($redemption['used_count'] ?? 0) - 1);
        $issueStatus = (string) ($redemption['issue_status'] ?? '');
        $nextIssueStatus = $issueStatus === 'used' ? 'active' : $issueStatus;

        $originalDedupeKey = (string) ($redemption['dedupe_key'] ?? '');
        $refundedDedupeKey = sr_coupon_refunded_dedupe_key($redemptionId, $originalDedupeKey);

        $stmt = $pdo->prepare(
            "UPDATE sr_coupon_redemptions
             SET status = 'refunded',
                 dedupe_key = :dedupe_key,
                 refunded_at = :refunded_at,
                 refunded_by_account_id = :refunded_by_account_id,
                 refund_note = :refund_note
             WHERE id = :id
               AND status = 'redeemed'"
        );
        $stmt->execute([
            'dedupe_key' => $refundedDedupeKey,
            'refunded_at' => $now,
            'refunded_by_account_id' => $adminAccountId,
            'refund_note' => $refundNote,
            'id' => $redemptionId,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new InvalidArgumentException('이미 환불되었거나 환불할 수 없는 사용 내역입니다.');
        }

        $stmt = $pdo->prepare(
            'UPDATE sr_coupon_issues
             SET used_count = :used_count,
                 status = :status,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'used_count' => $usedCount,
            'status' => $nextIssueStatus,
            'updated_at' => $now,
            'id' => (int) $redemption['coupon_issue_id'],
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'coupon_issue_id' => (int) $redemption['coupon_issue_id'],
            'coupon_definition_id' => (int) $redemption['coupon_definition_id'],
            'account_id' => (int) $redemption['account_id'],
            'target_type' => (string) ($redemption['target_type'] ?? ''),
            'target_id' => (string) ($redemption['target_id'] ?? ''),
            'coupon_type' => (string) ($redemption['coupon_type'] ?? 'access'),
            'coupon_title' => (string) ($redemption['title'] ?? ''),
            'used_count' => $usedCount,
            'issue_status' => $nextIssueStatus,
            'refunded_at' => $now,
            'revoked_access_count' => 0,
            'original_dedupe_key' => $originalDedupeKey,
            'refunded_dedupe_key' => $refundedDedupeKey,
            'notification_event_key' => 'redemption.refunded',
            'notification_payload' => [
                'redemption_id' => $redemptionId,
                'refund_note' => $refundNote,
                'refunded_at' => $now,
                'used_count' => $usedCount,
                'original_dedupe_key' => $originalDedupeKey,
                'refunded_dedupe_key' => $refundedDedupeKey,
                'status_label' => sr_coupon_issue_status_label($nextIssueStatus),
            ],
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_coupon_mark_payment_ledger_redemption_refunded_if_available(PDO $pdo, int $accountId, int $redemptionId, string $reason): array
{
    if ($accountId <= 0 || $redemptionId <= 0 || !function_exists('sr_module_enabled') || !sr_module_enabled($pdo, 'payment_ledger')) {
        return ['payment_record_ids' => [], 'reversed_item_count' => 0, 'refunded_record_ids' => []];
    }
    if (!is_file(SR_ROOT . '/modules/payment_ledger/payment-ledger.php')) {
        throw new RuntimeException('결제 기록 기반 모듈 계약을 찾을 수 없습니다.');
    }

    require_once SR_ROOT . '/modules/payment_ledger/payment-ledger.php';

    return sr_payment_ledger_mark_references_reversed_if_enabled($pdo, $accountId, [[
        'item_kind' => 'coupon_redemption',
        'owner_module' => 'coupon',
        'reference_type' => 'coupon_redemption',
        'reference_id' => (string) $redemptionId,
    ]], '쿠폰 사용 환불: ' . $reason, ['access_entitlement']);
}

function sr_coupon_asset_refund_reference_id(string $assetModule, int $transactionId): string
{
    return sr_coupon_clean_key($assetModule, 60) . '_transaction:' . (string) $transactionId;
}

function sr_coupon_refund_paid_issue_assets(PDO $pdo, int $issueId, int $adminAccountId, string $refundNote): array
{
    $refundNote = sr_coupon_clean_text($refundNote, 255);
    if ($issueId <= 0) {
        throw new InvalidArgumentException('환불할 쿠폰 발급본을 선택하세요.');
    }
    if ($adminAccountId <= 0) {
        throw new InvalidArgumentException('관리자 계정을 확인할 수 없습니다.');
    }
    if ($refundNote === '') {
        throw new InvalidArgumentException('환불 사유를 입력하세요.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT i.*, d.title AS coupon_title
             FROM sr_coupon_issues i
             INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
             WHERE i.id = :id
             LIMIT 1'
            . sr_coupon_for_update_clause($pdo)
        );
        $stmt->execute(['id' => $issueId]);
        $issue = $stmt->fetch();
        if (!is_array($issue)) {
            throw new InvalidArgumentException('쿠폰 발급본을 찾을 수 없습니다.');
        }
        if ((string) ($issue['claim_type'] ?? '') !== 'paid') {
            throw new InvalidArgumentException('유료 발급 쿠폰만 자산 환불할 수 있습니다.');
        }
        if ((string) ($issue['status'] ?? '') === 'refunded') {
            throw new InvalidArgumentException('이미 환불된 쿠폰입니다.');
        }
        if ((int) ($issue['used_count'] ?? 0) > 0 || (string) ($issue['status'] ?? '') === 'used') {
            throw new InvalidArgumentException('이미 사용된 쿠폰은 발급 환불할 수 없습니다.');
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS redemption_count
             FROM sr_coupon_redemptions
             WHERE coupon_issue_id = :issue_id
               AND status = 'redeemed'"
        );
        $stmt->execute(['issue_id' => $issueId]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException('사용 이력이 있는 쿠폰은 발급 환불할 수 없습니다.');
        }

        $claimLog = null;
        $claimLogId = (int) ($issue['claim_log_id'] ?? 0);
        if ($claimLogId > 0) {
            $stmt = $pdo->prepare(
                'SELECT *
                 FROM sr_coupon_claim_logs
                 WHERE id = :id
                 LIMIT 1'
                . sr_coupon_for_update_clause($pdo)
            );
            $stmt->execute(['id' => $claimLogId]);
            $claimLogRow = $stmt->fetch();
            $claimLog = is_array($claimLogRow) ? $claimLogRow : null;
        }

        $snapshot = json_decode((string) ($issue['claim_snapshot_json'] ?? ''), true);
        $allocations = is_array($snapshot) ? (array) ($snapshot['charged_allocations'] ?? []) : [];
        if ($allocations === []) {
            throw new InvalidArgumentException('환불할 자산 차감 스냅샷이 없습니다.');
        }

        $refundTransactions = [];
        foreach ($allocations as $allocation) {
            if (!is_array($allocation)) {
                continue;
            }
            $assetModule = sr_coupon_clean_key((string) ($allocation['asset_module'] ?? ''), 60);
            $amount = (int) ($allocation['amount'] ?? $allocation['asset_amount'] ?? 0);
            $sourceTransactionId = (int) ($allocation['transaction_id'] ?? 0);
            if ($assetModule === '' || $amount <= 0 || $sourceTransactionId <= 0) {
                throw new InvalidArgumentException('환불할 자산 차감 스냅샷이 올바르지 않습니다.');
            }

            $transactionData = [
                'account_id' => (int) ($issue['account_id'] ?? 0),
                'amount' => $amount,
                'transaction_type' => 'refund',
                'reason' => '유료 쿠폰 발급 환불: ' . (string) ($issue['coupon_title'] ?? ''),
                'reference_type' => 'refund',
                'reference_id' => sr_coupon_asset_refund_reference_id($assetModule, $sourceTransactionId),
                'created_by_account_id' => $adminAccountId,
            ];
            $transactionData['refund_expiration_policy'] = 'original';

            foreach (sr_coupon_asset_refund_transactions($pdo, $assetModule, $transactionData) as $refundTransactionId) {
                $refundTransactions[] = [
                    'asset_module' => $assetModule,
                    'source_transaction_id' => $sourceTransactionId,
                    'refund_transaction_id' => $refundTransactionId,
                    'amount' => $amount,
                ];
            }
        }
        if ($refundTransactions === []) {
            throw new InvalidArgumentException('환불할 자산 차감 스냅샷이 없습니다.');
        }

        $now = sr_now();
        $stmt = $pdo->prepare(
            "UPDATE sr_coupon_issues
             SET status = 'refunded',
                 updated_at = :updated_at
             WHERE id = :id
               AND status <> 'refunded'"
        );
        $stmt->execute([
            'updated_at' => $now,
            'id' => $issueId,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new InvalidArgumentException('이미 환불된 쿠폰입니다.');
        }

        if (is_array($claimLog)) {
            $stmt = $pdo->prepare(
                "UPDATE sr_coupon_claim_logs
                 SET status = 'cancelled',
                     occupying_account_id = NULL,
                     failure_code = :failure_code,
                     failure_message = :failure_message,
                     updated_at = :updated_at
                 WHERE id = :id"
            );
            $stmt->execute([
                'failure_code' => 'refunded',
                'failure_message' => $refundNote,
                'updated_at' => $now,
                'id' => (int) ($claimLog['id'] ?? 0),
            ]);
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        sr_coupon_notify_issue_event($pdo, $issueId, 'issue.refunded', $adminAccountId, [
            'refund_note' => $refundNote,
            'refunded_at' => $now,
            'refund_transactions' => $refundTransactions,
            'status_label' => sr_coupon_issue_status_label('refunded'),
        ]);

        return [
            'coupon_issue_id' => $issueId,
            'coupon_definition_id' => (int) ($issue['coupon_definition_id'] ?? 0),
            'account_id' => (int) ($issue['account_id'] ?? 0),
            'claim_log_id' => $claimLogId,
            'refunded_at' => $now,
            'refund_transactions' => $refundTransactions,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function sr_coupon_refunded_dedupe_key(int $redemptionId, string $originalDedupeKey): string
{
    return 'refunded:' . (string) $redemptionId . ':' . substr(sha1($originalDedupeKey), 0, 24);
}

function sr_coupon_revoke_consumer_access(PDO $pdo, int $accountId, string $dedupeKey): int
{
    if ($accountId <= 0 || $dedupeKey === '') {
        return 0;
    }

    $revoked = 0;
    foreach (sr_coupon_target_contracts($pdo) as $target) {
        $revokeFunction = (string) ($target['revoke_access_function'] ?? '');
        if ($revokeFunction === '' || !function_exists($revokeFunction)) {
            continue;
        }

        try {
            $revoked += max(0, (int) $revokeFunction($pdo, $accountId, $dedupeKey));
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'coupon_revoke_consumer_access');
        }
    }

    return $revoked;
}

function sr_coupon_revoke_target_access_or_fail(PDO $pdo, string $targetType, int $accountId, string $dedupeKey): int
{
    if ($targetType === '' || $accountId <= 0 || $dedupeKey === '') {
        return 0;
    }

    $contracts = sr_coupon_target_contracts($pdo);
    $target = $contracts[$targetType] ?? null;
    $revokeFunction = is_array($target) ? (string) ($target['revoke_access_function'] ?? '') : '';
    if ($revokeFunction === '' || !function_exists($revokeFunction)) {
        throw new RuntimeException('쿠폰 사용처 접근권 회수 계약이 없습니다.');
    }

    try {
        $revoked = max(0, (int) $revokeFunction($pdo, $accountId, $dedupeKey));
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'coupon_revoke_target_access');
        throw new RuntimeException('쿠폰 사용처 접근권 회수에 실패했습니다.', 0, $exception);
    }

    if ($revoked < 1) {
        throw new RuntimeException('쿠폰 사용처 접근권 회수 대상이 없습니다.');
    }

    return $revoked;
}
