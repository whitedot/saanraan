<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_coupon_asset_balance(PDO $pdo, string $assetModule, int $accountId): int
{
    $assetOptions = sr_coupon_asset_options($pdo);
    if (!isset($assetOptions[$assetModule])) {
        return 0;
    }

    $balanceFunction = (string) ($assetOptions[$assetModule]['balance_function'] ?? '');
    return $balanceFunction !== '' && function_exists($balanceFunction)
        ? max(0, (int) $balanceFunction($pdo, $accountId))
        : 0;
}

function sr_coupon_asset_transaction(PDO $pdo, string $assetModule, array $data): int
{
    $assetOptions = sr_coupon_asset_options($pdo);
    if (!isset($assetOptions[$assetModule])) {
        throw new RuntimeException('쿠폰 발급에 사용할 수 없는 포인트/금액 항목입니다.');
    }

    $transactionFunction = (string) ($assetOptions[$assetModule]['transaction_function'] ?? '');
    if ($transactionFunction === '' || !function_exists($transactionFunction)) {
        throw new RuntimeException('쿠폰 발급 자산 거래 함수를 찾을 수 없습니다.');
    }

    return (int) $transactionFunction($pdo, $data);
}

function sr_coupon_asset_refund_transactions(PDO $pdo, string $assetModule, array $data): array
{
    $assetOptions = sr_coupon_asset_options($pdo);
    if (!isset($assetOptions[$assetModule])) {
        throw new RuntimeException('쿠폰 발급에 사용할 수 없는 포인트/금액 항목입니다.');
    }

    require_once SR_ROOT . '/modules/member/helpers/assets.php';
    return sr_member_asset_create_refund_transactions($pdo, $assetModule, $data, $assetOptions);
}

function sr_coupon_claim_paid_campaign_with_asset(PDO $pdo, string $campaignKey, int $accountId, string $intentToken, array $assetModules, string $claimSource = 'coupon_zone', array $sourceContext = []): array
{
    if (!sr_coupon_usage_enabled($pdo)) {
        throw new InvalidArgumentException('쿠폰·이용권을 사용하지 않도록 설정되어 있습니다.');
    }

    if ($accountId <= 0) {
        throw new InvalidArgumentException('로그인 후 쿠폰을 발급받을 수 있습니다.');
    }
    $intentToken = trim($intentToken);
    if ($intentToken === '') {
        throw new InvalidArgumentException('발급 요청 토큰이 없습니다. 화면을 새로고침한 뒤 다시 시도해 주세요.');
    }

    $campaign = sr_coupon_claim_campaign_by_key($pdo, $campaignKey);
    if (!is_array($campaign)) {
        throw new InvalidArgumentException('발급 캠페인을 찾을 수 없습니다.');
    }
    if ((string) ($campaign['claim_type'] ?? '') !== 'paid') {
        throw new InvalidArgumentException('유료 발급 캠페인이 아닙니다.');
    }

    $allowedAssetModules = sr_coupon_asset_module_keys_from_value($pdo, $campaign['allowed_asset_modules_json'] ?? '');
    $selectedAssetModules = sr_coupon_asset_module_keys_from_value($pdo, $assetModules);
    if ($selectedAssetModules === []) {
        throw new InvalidArgumentException('유료 발급에 사용할 포인트/금액 항목을 선택하세요.');
    }
    foreach ($selectedAssetModules as $assetModule) {
        if (!in_array($assetModule, $allowedAssetModules, true)) {
            throw new InvalidArgumentException('유료 발급에 사용할 포인트/금액 항목을 다시 선택하세요.');
        }
    }

    $dedupeKey = 'coupon_paid_claim:' . (string) (int) $campaign['id'] . ':' . (string) $accountId . ':' . $intentToken;
    $dedupeHash = sr_coupon_claim_dedupe_hash($dedupeKey);
    $startedTransaction = !$pdo->inTransaction();
    $savepointName = 'sr_coupon_paid_claim';
    if ($startedTransaction) {
        $pdo->beginTransaction();
    } else {
        $pdo->exec('SAVEPOINT ' . $savepointName);
    }

    try {
        $campaign = sr_coupon_claim_campaign_by_key($pdo, $campaignKey, true);
        if (!is_array($campaign)) {
            throw new InvalidArgumentException('발급 캠페인을 찾을 수 없습니다.');
        }
        if ((string) ($campaign['claim_type'] ?? '') !== 'paid') {
            throw new InvalidArgumentException('유료 발급 캠페인이 아닙니다.');
        }
        $lockedAllowedAssetModules = sr_coupon_asset_module_keys_from_value($pdo, $campaign['allowed_asset_modules_json'] ?? '');
        foreach ($selectedAssetModules as $assetModule) {
            if (!in_array($assetModule, $lockedAllowedAssetModules, true)) {
                throw new InvalidArgumentException('유료 발급에 사용할 포인트/금액 항목을 다시 선택하세요.');
            }
        }
        sr_coupon_validate_claim_campaign($campaign, $claimSource);
        sr_coupon_self_expire_claims($pdo, (int) $campaign['id'], $accountId);

        $existing = sr_coupon_claim_log_by_dedupe_hash($pdo, (int) $campaign['id'], $dedupeHash);
        if (is_array($existing) && (string) ($existing['status'] ?? '') === 'issued' && (int) ($existing['coupon_issue_id'] ?? 0) > 0) {
            if ($startedTransaction) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT ' . $savepointName);
            }
            return [
                'claimed' => false,
                'already_claimed' => true,
                'coupon_issue_id' => (int) $existing['coupon_issue_id'],
                'claim_log_id' => (int) ($existing['id'] ?? 0),
            ];
        }
        if (is_array($existing)) {
            throw new InvalidArgumentException('이전 발급 요청이 아직 처리 중입니다.');
        }

        sr_coupon_assert_claim_limits($pdo, $campaign, $accountId);

        $now = sr_now();
        $stmt = $pdo->prepare(
            'INSERT INTO sr_coupon_claim_logs
                (campaign_id, coupon_definition_id, account_id, coupon_issue_id, claim_source, source_context_json, dedupe_key, dedupe_hash, occupying_account_id, status, reserved_until, failure_code, failure_message, created_at, issued_at, updated_at)
             VALUES
                (:campaign_id, :coupon_definition_id, :account_id, NULL, :claim_source, :source_context_json, :dedupe_key, :dedupe_hash, :occupying_account_id, :status, NULL, \'\', \'\', :created_at, NULL, :updated_at)'
        );
        $stmt->execute([
            'campaign_id' => (int) $campaign['id'],
            'coupon_definition_id' => (int) $campaign['coupon_definition_id'],
            'account_id' => $accountId,
            'claim_source' => $claimSource,
            'source_context_json' => json_encode($sourceContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'dedupe_key' => $dedupeKey,
            'dedupe_hash' => $dedupeHash,
            'occupying_account_id' => (int) ($campaign['per_account_limit'] ?? 1) === 1 ? $accountId : null,
            'status' => 'reserved',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $claimLogId = (int) $pdo->lastInsertId();
        $priceAmount = max(0, (int) ($campaign['price_amount'] ?? 0));
        $priceCurrencyCode = sr_coupon_clean_currency_code((string) ($campaign['price_currency_code'] ?? ''));
        $plan = sr_member_asset_settlement_plan(
            $pdo,
            sr_coupon_asset_options($pdo),
            static function (PDO $pdo, string $assetModule) use ($accountId): int {
                return sr_coupon_asset_balance($pdo, $assetModule, $accountId);
            },
            $selectedAssetModules,
            $priceAmount,
            $priceCurrencyCode
        );
        if (empty($plan['ok'])) {
            throw new InvalidArgumentException('선택한 포인트/금액 항목의 잔액이 부족하거나 정확히 차감할 수 없습니다.');
        }

        $chargedAllocations = [];
        foreach ((array) ($plan['allocations'] ?? []) as $allocation) {
            $assetModule = (string) ($allocation['asset_module'] ?? '');
            $amount = (int) ($allocation['amount'] ?? 0);
            if ($assetModule === '' || $amount <= 0) {
                continue;
            }
            $transactionId = sr_coupon_asset_transaction($pdo, $assetModule, [
                'account_id' => $accountId,
                'amount' => -$amount,
                'transaction_type' => 'use',
                'reason' => '유료 쿠폰 발급: ' . (string) ($campaign['title'] ?? $campaign['campaign_key'] ?? ''),
                'reference_type' => 'coupon_claim',
                'reference_id' => (string) $claimLogId,
                'created_by_account_id' => null,
            ]);
            $allocation['transaction_id'] = $transactionId;
            $chargedAllocations[] = $allocation;
        }
        if ($priceAmount > 0 && $chargedAllocations === []) {
            throw new InvalidArgumentException('차감할 포인트/금액 항목을 찾을 수 없습니다.');
        }
        $roundingPolicyVersion = '';
        foreach ($chargedAllocations as $allocation) {
            if (!is_array($allocation)) {
                continue;
            }
            $purchasePowerSnapshot = is_array($allocation['purchase_power_snapshot'] ?? null) ? $allocation['purchase_power_snapshot'] : [];
            $roundingPolicyVersion = (string) ($purchasePowerSnapshot['rounding_policy_version'] ?? '');
            if ($roundingPolicyVersion !== '') {
                break;
            }
        }
        if ($roundingPolicyVersion === '') {
            throw new RuntimeException('유료 쿠폰 발급 차감 스냅샷의 반올림 정책 버전을 확인할 수 없습니다.');
        }

        $issueId = sr_coupon_issue_to_account(
            $pdo,
            (int) $campaign['coupon_definition_id'],
            $accountId,
            'paid_claim_campaign:' . (string) ($campaign['campaign_key'] ?? ''),
            null,
            null,
            [
                'claim_type' => 'paid',
                'claim_campaign_id' => (int) $campaign['id'],
                'claim_log_id' => $claimLogId,
                'issue_expires_at' => (string) ($campaign['issue_expires_at'] ?? ''),
                'issue_expires_in_days' => $campaign['issue_expires_in_days'] ?? null,
                'clamp_starts_at_to_issued_at' => true,
                'nominal_price_amount' => $priceAmount,
                'nominal_price_currency_code' => $priceCurrencyCode,
                'asset_reference_module' => 'coupon',
                'asset_reference_type' => 'paid_claim',
                'asset_reference_id' => (string) $claimLogId,
                'claim_snapshot' => [
                    'schema_version' => 'coupon_claim_snapshot_v1',
                    'claim_type' => 'paid',
                    'settlement_kind' => 'paid',
                    'snapshot_schema_version' => 'asset_settlement_snapshot_v1',
                    'rounding_policy_version' => $roundingPolicyVersion,
                    'campaign_id' => (int) $campaign['id'],
                    'campaign_key' => (string) ($campaign['campaign_key'] ?? ''),
                    'claim_log_id' => $claimLogId,
                    'nominal_price' => [
                        'amount' => $priceAmount,
                        'currency_code' => $priceCurrencyCode,
                    ],
                    'charged_allocations' => $chargedAllocations,
                ],
            ]
        );

        $claimLogUpdateSql = "UPDATE sr_coupon_claim_logs
             SET coupon_issue_id = :coupon_issue_id,
                 status = 'issued',
                 issued_at = :issued_at,
                 updated_at = :updated_at";
        $claimLogUpdateParams = [
            'coupon_issue_id' => $issueId,
            'issued_at' => sr_now(),
            'updated_at' => sr_now(),
            'id' => $claimLogId,
        ];
        if (sr_coupon_claim_log_asset_reference_columns_available($pdo)) {
            $claimLogUpdateSql .= ",
                 asset_reference_module = :asset_reference_module,
                 asset_reference_type = :asset_reference_type,
                 asset_reference_id = :asset_reference_id,
                 payment_reference_module = '',
                 payment_reference_type = '',
                 payment_reference_id = ''";
            $claimLogUpdateParams['asset_reference_module'] = 'coupon';
            $claimLogUpdateParams['asset_reference_type'] = 'paid_claim';
            $claimLogUpdateParams['asset_reference_id'] = (string) $claimLogId;
        }
        $claimLogUpdateSql .= '
             WHERE id = :id';
        $stmt = $pdo->prepare($claimLogUpdateSql);
        $stmt->execute($claimLogUpdateParams);

        if ($startedTransaction) {
            $pdo->commit();
        } else {
            $pdo->exec('RELEASE SAVEPOINT ' . $savepointName);
        }

        return [
            'claimed' => true,
            'already_claimed' => false,
            'coupon_issue_id' => $issueId,
            'claim_log_id' => $claimLogId,
            'charged_allocations' => $chargedAllocations,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        } elseif (!$startedTransaction && $pdo->inTransaction()) {
            $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepointName);
            $pdo->exec('RELEASE SAVEPOINT ' . $savepointName);
        }

        throw $exception;
    }
}

function sr_coupon_validate_claim_campaign(array $campaign, string $surface): void
{
    $now = sr_now();
    if ((string) ($campaign['status'] ?? '') !== 'active') {
        throw new InvalidArgumentException('현재 발급 중인 쿠폰이 아닙니다.');
    }
    if ((string) ($campaign['visibility'] ?? '') !== 'public') {
        throw new InvalidArgumentException('공개 발급 쿠폰이 아닙니다.');
    }
    if ((string) ($campaign['coupon_status'] ?? '') !== 'active') {
        throw new InvalidArgumentException('연결된 쿠폰이 사용 상태가 아닙니다.');
    }
    if ((string) ($campaign['starts_at'] ?? '') !== '' && strcmp((string) $campaign['starts_at'], $now) > 0) {
        throw new InvalidArgumentException('아직 발급 시작 전입니다.');
    }
    if ((string) ($campaign['ends_at'] ?? '') !== '' && strcmp((string) $campaign['ends_at'], $now) < 0) {
        throw new InvalidArgumentException('발급 기간이 종료되었습니다.');
    }
    if (!in_array($surface, sr_coupon_claim_surfaces_from_value($campaign['exposure_surfaces_json'] ?? ''), true)) {
        throw new InvalidArgumentException('이 화면에서는 발급할 수 없는 쿠폰입니다.');
    }
}

function sr_coupon_claim_log_by_dedupe_hash(PDO $pdo, int $campaignId, string $dedupeHash): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_coupon_claim_logs
         WHERE campaign_id = :campaign_id
           AND dedupe_hash = :dedupe_hash
         LIMIT 1'
    );
    $stmt->execute([
        'campaign_id' => $campaignId,
        'dedupe_hash' => $dedupeHash,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_coupon_assert_claim_limits(PDO $pdo, array $campaign, int $accountId): void
{
    $state = sr_coupon_claim_campaign_state($pdo, $campaign, $accountId);
    if (!$state['claimable']) {
        $message = (string) ($state['message'] ?? '');
        throw new InvalidArgumentException($message !== '' ? $message : '쿠폰을 발급할 수 없습니다.');
    }
}
