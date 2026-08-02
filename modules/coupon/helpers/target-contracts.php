<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_coupon_target_contract_helper_path(string $moduleKey, array $target): string
{
    $helpers = (string) ($target['helpers'] ?? '');
    if ($helpers === '' || preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) !== 1) {
        return '';
    }

    $path = SR_ROOT . '/modules/' . $moduleKey . '/' . $helpers;
    return is_file($path) ? $path : '';
}

function sr_coupon_target_contracts(PDO $pdo): array
{
    $contracts = [];
    foreach (sr_enabled_module_contract_files($pdo, 'coupon-targets.php', ['coupon']) as $moduleKey => $file) {
        $contractTargets = sr_load_module_contract_file($moduleKey, $file);
        if (!is_array($contractTargets)) {
            continue;
        }

        foreach ($contractTargets as $target) {
            if (!is_array($target)) {
                continue;
            }

            $targetType = (string) ($target['target_type'] ?? '');
            $label = sr_coupon_clean_text((string) ($target['label'] ?? ''), 80);
            if ($targetType === '' || $label === '' || preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $targetType) !== 1) {
                continue;
            }
            if (isset($contracts[$targetType])) {
                continue;
            }

            $helperPath = sr_coupon_target_contract_helper_path($moduleKey, $target);
            if ($helperPath !== '') {
                require_once $helperPath;
            }

            $target['module_key'] = $moduleKey;
            $target['label'] = $label;
            $target['capabilities'] = sr_coupon_target_contract_capabilities($target);
            $contracts[$targetType] = $target;
        }
    }

    return $contracts;
}

function sr_coupon_target_contract_capabilities(array $target): array
{
    $capabilities = [];
    foreach ($target['capabilities'] ?? [] as $capability) {
        $capability = (string) $capability;
        if (preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $capability) === 1) {
            $capabilities[$capability] = true;
        }
    }

    foreach ([
        'search' => 'search_function',
        'health' => 'health_function',
        'admin_url' => 'admin_url_function',
        'pricing' => 'pricing_function',
        'redeem' => 'redeem_function',
        'revoke_access' => 'revoke_access_function',
    ] as $capability => $functionKey) {
        $functionName = (string) ($target[$functionKey] ?? '');
        if ($functionName !== '' && function_exists($functionName)) {
            $capabilities[$capability] = true;
        }
    }

    return array_keys($capabilities);
}

function sr_coupon_target_contract_has_capability(array $target, string $capability): bool
{
    return in_array($capability, array_map('strval', $target['capabilities'] ?? []), true);
}

function sr_coupon_target_capability_labels(): array
{
    return [
        'search' => '검색',
        'health' => '상태 확인',
        'admin_url' => '관리자 링크',
        'pricing' => '가격 조회',
        'redeem' => '사용 처리',
        'revoke_access' => '접근권 회수',
    ];
}

function sr_coupon_target_capability_summary(array $capabilities): string
{
    $labels = sr_coupon_target_capability_labels();
    $summary = [];
    foreach ($capabilities as $capability) {
        $capability = (string) $capability;
        $summary[] = (string) ($labels[$capability] ?? $capability);
    }

    return implode(', ', array_values(array_unique(array_filter($summary))));
}

function sr_coupon_assert_refundable_target_contract(PDO $pdo, string $targetType, string $refundablePolicy): void
{
    if ($refundablePolicy !== 'refundable' || $targetType === 'all') {
        return;
    }

    $contracts = sr_coupon_target_contracts($pdo);
    $target = $contracts[$targetType] ?? null;
    if (!is_array($target) || !sr_coupon_target_contract_has_capability($target, 'revoke_access')) {
        throw new InvalidArgumentException('환급 가능 쿠폰은 접근권 회수를 지원하는 사용처에만 연결할 수 있습니다.');
    }
}

function sr_coupon_assert_refundable_benefit_model(string $couponType, string $refundablePolicy): void
{
    if ($refundablePolicy !== 'refundable' || $couponType === 'access') {
        return;
    }

    throw new InvalidArgumentException('정액/정률 할인 쿠폰은 복합 자산 결제 취소 계약이 준비될 때까지 환급 가능으로 설정할 수 없습니다.');
}

function sr_coupon_target_pricing(PDO $pdo, string $targetType, string $targetId, int $accountId = 0, array $context = []): array
{
    $contracts = sr_coupon_target_contracts($pdo);
    $target = $contracts[$targetType] ?? null;
    $pricingFunction = is_array($target) ? (string) ($target['pricing_function'] ?? '') : '';
    if (!is_array($target) || $pricingFunction === '' || !function_exists($pricingFunction)) {
        return [
            'ok' => false,
            'failure_code' => 'pricing_not_supported',
            'failure_message' => '가격 조회를 지원하지 않는 쿠폰 사용처입니다.',
        ];
    }

    try {
        $pricing = $pricingFunction($pdo, $targetType, $targetId, $accountId, $context);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'coupon_target_pricing_' . $targetType);
        return [
            'ok' => false,
            'failure_code' => 'pricing_failed',
            'failure_message' => '쿠폰 사용처 가격을 확인할 수 없습니다.',
        ];
    }

    return sr_coupon_normalize_target_pricing($pricing, $targetType, $targetId);
}

function sr_coupon_target_pricing_admin_label(array $pricing): string
{
    if (empty($pricing['ok'])) {
        $message = sr_coupon_clean_text((string) ($pricing['failure_message'] ?? '가격 조회를 지원하지 않습니다.'), 120);
        return '가격 조회: ' . ($message !== '' ? $message : '지원하지 않음');
    }

    $amount = sr_coupon_nonnegative_int_or_null($pricing['price_amount'] ?? 0);
    if ($amount === null) {
        return '가격 조회: 가격 정보 오류';
    }
    $unit = (string) ($pricing['currency_code'] ?? '');
    if ($unit === '') {
        $unit = (string) ($pricing['asset_unit'] ?? '');
    }
    if ($unit === '') {
        $unit = '단위 없음';
    }

    if (!empty($pricing['is_free']) || $amount === 0) {
        return '현재 가격: 무료';
    }

    return '현재 가격: ' . number_format($amount) . $unit;
}

function sr_coupon_normalize_target_pricing(mixed $pricing, string $targetType, string $targetId): array
{
    if (!is_array($pricing) || empty($pricing['ok'])) {
        return [
            'ok' => false,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'failure_code' => sr_coupon_clean_key((string) ($pricing['failure_code'] ?? 'target_unavailable'), 60),
            'failure_message' => sr_coupon_clean_text((string) ($pricing['failure_message'] ?? '사용할 수 없는 대상입니다.'), 255),
        ];
    }

    $priceAmount = sr_coupon_nonnegative_int_or_null($pricing['price_amount'] ?? 0);
    if ($priceAmount === null) {
        return [
            'ok' => false,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'failure_code' => 'pricing_amount_invalid',
            'failure_message' => '쿠폰 사용처 가격 금액이 올바르지 않습니다.',
        ];
    }
    $currencyCode = sr_coupon_clean_currency_code((string) ($pricing['currency_code'] ?? ''));
    $assetUnit = sr_coupon_clean_key((string) ($pricing['asset_unit'] ?? ''), 40);
    if (($currencyCode === '' && $assetUnit === '') || ($currencyCode !== '' && $assetUnit !== '')) {
        return [
            'ok' => false,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'failure_code' => 'pricing_unit_invalid',
            'failure_message' => '쿠폰 사용처 가격 단위가 올바르지 않습니다.',
        ];
    }

    return [
        'ok' => true,
        'target_type' => (string) ($pricing['target_type'] ?? $targetType),
        'target_id' => (string) ($pricing['target_id'] ?? $targetId),
        'price_amount' => $priceAmount,
        'currency_code' => $currencyCode,
        'asset_unit' => $assetUnit,
        'is_free' => $priceAmount === 0,
        'already_entitled' => !empty($pricing['already_entitled']),
        'policy_summary' => sr_coupon_clean_text((string) ($pricing['policy_summary'] ?? ''), 255),
        'priced_at' => sr_coupon_clean_text((string) ($pricing['priced_at'] ?? sr_now()), 30),
        'failure_code' => null,
        'failure_message' => null,
    ];
}

function sr_coupon_issue_member_groups(PDO $pdo): array
{
    if (!function_exists('sr_member_groups') || !function_exists('sr_member_groups_table_exists') || !sr_member_groups_table_exists($pdo)) {
        return [];
    }

    return array_values(array_filter(sr_member_groups($pdo), static function (array $group): bool {
        return (string) ($group['status'] ?? '') === 'enabled';
    }));
}

function sr_coupon_issue_target_account_ids(PDO $pdo, array $runtimeConfig, string $targetMode, string $accountIdentifier, string $groupKey): array
{
    if (!in_array($targetMode, ['member', 'all', 'group'], true)) {
        throw new InvalidArgumentException('쿠폰 지급 대상을 선택해 주세요.');
    }

    if ($targetMode === 'member') {
        $accountId = sr_admin_member_account_id_from_identifier($pdo, $runtimeConfig, $accountIdentifier);
        if ($accountId <= 0) {
            throw new InvalidArgumentException('쿠폰을 지급할 회원을 선택해 주세요.');
        }

        return [$accountId];
    }

    if ($targetMode === 'all') {
        $stmt = $pdo->query("SELECT id FROM sr_member_accounts WHERE status = 'active' ORDER BY id ASC");
        $accountIds = array_map('intval', array_column($stmt->fetchAll(), 'id'));
        if ($accountIds === []) {
            throw new InvalidArgumentException('쿠폰을 지급할 활성 회원이 없습니다.');
        }

        return $accountIds;
    }

    if (
        !function_exists('sr_member_group_by_key')
        || !function_exists('sr_member_group_key_is_valid')
        || !sr_member_group_key_is_valid($groupKey)
    ) {
        throw new InvalidArgumentException('쿠폰을 지급할 회원 그룹을 선택해 주세요.');
    }

    $group = sr_member_group_by_key($pdo, $groupKey);
    if (!is_array($group) || (string) ($group['status'] ?? '') !== 'enabled') {
        throw new InvalidArgumentException('사용 가능한 회원 그룹을 선택해 주세요.');
    }

    $stmt = $pdo->prepare(
        "SELECT DISTINCT m.account_id
         FROM sr_member_group_memberships m
         INNER JOIN sr_member_accounts a ON a.id = m.account_id
         WHERE m.group_id = :group_id
           AND m.status = 'active'
           AND a.status = 'active'
           AND (m.expires_at IS NULL OR m.expires_at >= :now)
         ORDER BY m.account_id ASC"
    );
    $stmt->execute([
        'group_id' => (int) $group['id'],
        'now' => sr_now(),
    ]);
    $accountIds = array_map('intval', array_column($stmt->fetchAll(), 'account_id'));
    if ($accountIds === []) {
        throw new InvalidArgumentException('선택한 회원 그룹에 지급 가능한 활성 회원이 없습니다.');
    }

    return $accountIds;
}

function sr_coupon_target_search(PDO $pdo, string $targetType, string $keyword, int $limit = 20): array
{
    if (!array_key_exists($targetType, sr_coupon_target_types($pdo)) || $targetType === 'all') {
        return [];
    }

    $keyword = sr_coupon_clean_text($keyword, 120);
    $limit = max(1, min(30, $limit));
    $contracts = sr_coupon_target_contracts($pdo);
    $target = $contracts[$targetType] ?? null;
    $searchFunction = is_array($target) ? (string) ($target['search_function'] ?? '') : '';
    if ($searchFunction === '' || !function_exists($searchFunction)) {
        return [];
    }

    try {
        $results = $searchFunction($pdo, $targetType, $keyword, $limit);
        if (!is_array($results)) {
            return [];
        }

        $capabilities = is_array($target) ? array_map('strval', $target['capabilities'] ?? []) : [];
        $capabilitySummary = sr_coupon_target_capability_summary($capabilities);
        foreach ($results as $index => $result) {
            if (!is_array($result)) {
                unset($results[$index]);
                continue;
            }

            $result['capabilities'] = $capabilities;
            $result['capability_label'] = $capabilitySummary !== '' ? '기능: ' . $capabilitySummary : '';
            $referenceId = (string) ($result['reference_id'] ?? '');
            if ($referenceId !== '' && sr_coupon_target_contract_has_capability($target, 'pricing')) {
                $pricing = sr_coupon_target_pricing($pdo, $targetType, $referenceId, 0, ['source' => 'admin_lookup']);
                $result['pricing_label'] = sr_coupon_target_pricing_admin_label($pricing);
                if (!empty($pricing['ok'])) {
                    $snapshot = sr_coupon_redemption_pricing_snapshot_from_result($pricing, $targetType, $referenceId);
                    $result['policy_summary'] = (string) ($snapshot['policy_summary'] ?? '');
                    $result['priced_at'] = (string) ($snapshot['priced_at'] ?? '');
                }
            } elseif ($referenceId !== '') {
                $result['pricing_label'] = '가격 조회: 지원하지 않음';
            }
            $results[$index] = $result;
        }

        return array_values($results);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'coupon_target_search_' . $targetType);
        return [];
    }
}
