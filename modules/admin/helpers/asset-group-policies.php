<?php

declare(strict_types=1);

function sr_admin_asset_group_policy_modes(): array
{
    return ['fixed', 'multiplier', 'delta', 'exempt', 'disabled'];
}

function sr_admin_asset_group_policy_json_from_value(mixed $value): string
{
    if (is_string($value)) {
        return trim($value);
    }

    if (!is_array($value) || $value === []) {
        return '';
    }

    $json = json_encode(array_values($value), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return is_string($json) ? $json : '';
}

function sr_admin_asset_group_policies_from_json(string $json): array
{
    $json = trim($json);
    if ($json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Asset group policy JSON is invalid.');
    }

    $policies = [];
    foreach (array_values($decoded) as $index => $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Asset group policy row is invalid.');
        }

        $policies[] = sr_admin_asset_group_policy_normalize($row, $index + 1);
    }

    return $policies;
}

function sr_admin_asset_group_policies_from_post(string $fieldName): array
{
    $posted = $_POST[$fieldName] ?? [];
    return sr_admin_asset_group_policies_from_input($posted);
}

function sr_admin_asset_group_policies_from_input(mixed $posted): array
{
    if (!is_array($posted)) {
        return [];
    }

    $groupKeys = is_array($posted['group_key'] ?? null) ? $posted['group_key'] : [];
    $modes = is_array($posted['mode'] ?? null) ? $posted['mode'] : [];
    $values = is_array($posted['value'] ?? null) ? $posted['value'] : [];
    $minLevels = is_array($posted['min_level'] ?? null) ? $posted['min_level'] : [];
    $priorities = is_array($posted['priority'] ?? null) ? $posted['priority'] : [];
    $statuses = is_array($posted['status'] ?? null) ? $posted['status'] : [];
    $maxRows = max(count($groupKeys), count($modes), count($values), count($minLevels), count($priorities), count($statuses));

    $policies = [];
    for ($index = 0; $index < $maxRows; $index += 1) {
        $row = [
            'group_key' => is_scalar($groupKeys[$index] ?? null) ? (string) $groupKeys[$index] : '',
            'mode' => is_scalar($modes[$index] ?? null) ? (string) $modes[$index] : '',
            'value' => is_scalar($values[$index] ?? null) ? (string) $values[$index] : '',
            'min_level' => is_scalar($minLevels[$index] ?? null) ? (string) $minLevels[$index] : '0',
            'priority' => is_scalar($priorities[$index] ?? null) ? (string) $priorities[$index] : '',
            'status' => is_scalar($statuses[$index] ?? null) ? (string) $statuses[$index] : '',
        ];

        $allBlank = trim($row['group_key']) === ''
            && trim($row['mode']) === ''
            && trim($row['value']) === ''
            && in_array(trim($row['min_level']), ['', '0'], true)
            && in_array(trim($row['priority']), ['', '0'], true)
            && in_array(trim($row['status']), ['', 'active'], true);
        if ($allBlank) {
            continue;
        }

        $policies[] = sr_admin_asset_group_policy_normalize($row, count($policies) + 1);
    }

    return $policies;
}

function sr_admin_asset_group_policy_normalize(array $row, int $policyId): array
{
    $mode = strtolower(trim((string) ($row['mode'] ?? $row['adjustment_mode'] ?? '')));
    $groupKey = strtolower(trim((string) ($row['group_key'] ?? '')));
    $status = strtolower(trim((string) ($row['status'] ?? 'active')));
    $value = array_key_exists('value', $row) ? $row['value'] : ($row['amount'] ?? ($row['multiplier'] ?? ''));
    $minLevel = array_key_exists('min_level', $row) ? $row['min_level'] : 0;

    return [
        'policy_id' => (int) ($row['policy_id'] ?? $policyId),
        'group_key' => preg_replace('/[^a-z0-9_]/', '', $groupKey) ?? '',
        'mode' => $mode,
        'value' => is_scalar($value) ? trim((string) $value) : '',
        'min_level' => max(0, (int) $minLevel),
        'priority' => (int) ($row['priority'] ?? 0),
        'status' => $status === 'inactive' ? 'inactive' : 'active',
    ];
}

function sr_admin_asset_group_policy_validation_errors(PDO $pdo, array $policies, string $label): array
{
    $errors = [];
    $seenActiveGroups = [];

    foreach ($policies as $index => $policy) {
        if (!is_array($policy)) {
            $errors[] = $label . ' 그룹 정책 ' . (string) ($index + 1) . '행이 올바르지 않습니다.';
            continue;
        }

        $rowLabel = $label . ' 그룹 정책 ' . (string) ($index + 1) . '행';
        $groupKey = (string) ($policy['group_key'] ?? '');
        $mode = (string) ($policy['mode'] ?? '');
        $value = (string) ($policy['value'] ?? '');
        $minLevel = max(0, (int) ($policy['min_level'] ?? 0));
        $status = (string) ($policy['status'] ?? 'active');

        if (!function_exists('sr_member_group_key_is_valid')) {
            require_once SR_ROOT . '/modules/member/helpers/groups.php';
        }

        if (!sr_member_group_key_is_valid($groupKey)) {
            $errors[] = $rowLabel . '의 회원 그룹 key가 올바르지 않습니다.';
            continue;
        }

        $group = sr_member_group_by_key($pdo, $groupKey);
        if (!is_array($group)) {
            $errors[] = $rowLabel . '의 회원 그룹을 찾을 수 없습니다.';
        } elseif ($status === 'active' && (string) ($group['status'] ?? '') !== 'enabled') {
            $errors[] = $rowLabel . '의 회원 그룹이 사용 상태가 아닙니다.';
        }

        if (!in_array($mode, sr_admin_asset_group_policy_modes(), true)) {
            $errors[] = $rowLabel . '의 적용 방식이 올바르지 않습니다.';
        }

        if ($minLevel > 0 && function_exists('sr_community_max_level_value') && $minLevel > sr_community_max_level_value()) {
            $errors[] = $rowLabel . '의 최소 레벨은 ' . (string) sr_community_max_level_value() . ' 이하로 입력하세요.';
        }

        if (in_array($mode, ['fixed', 'delta'], true) && preg_match('/\A-?\d+\z/', $value) !== 1) {
            $errors[] = $rowLabel . '의 금액은 정수로 입력하세요.';
        }

        if ($mode === 'fixed' && (int) $value < 0) {
            $errors[] = $rowLabel . '의 고정 금액은 0 이상이어야 합니다.';
        }

        if ($mode === 'multiplier' && preg_match('/\A\d+(?:\.\d{1,4})?\z/', $value) !== 1) {
            $errors[] = $rowLabel . '의 배율은 0 이상의 숫자로 입력하세요.';
        }

        if ($status === 'active') {
            $conditionKey = $groupKey . '|' . (string) $minLevel;
            if (isset($seenActiveGroups[$conditionKey])) {
                $errors[] = $label . ' 그룹 정책에서 같은 회원 그룹과 최소 레벨의 활성 정책을 중복 저장할 수 없습니다.';
            }
            $seenActiveGroups[$conditionKey] = true;
        }
    }

    return $errors;
}

function sr_admin_asset_group_policy_mode_label(string $mode): string
{
    $labels = [
        'fixed' => '고정 금액',
        'multiplier' => '배율',
        'delta' => '증감액',
        'exempt' => '면제',
        'disabled' => '미지급/미차감',
    ];

    return (string) ($labels[$mode] ?? $mode);
}

function sr_admin_asset_group_policy_status_label(string $status): string
{
    return $status === 'inactive' ? '비활성' : '활성';
}

function sr_admin_asset_group_policy_apply(PDO $pdo, int $accountId, int $baseAmount, array $policies): array
{
    $snapshot = [
        'base_amount' => $baseAmount,
        'final_amount' => $baseAmount,
        'matched' => false,
        'matched_group_id' => 0,
        'matched_group_key' => '',
        'matched_min_level' => 0,
        'matched_level_value' => 0,
        'applied_policy_id' => 0,
        'adjustment_mode' => '',
        'adjustment_value' => '',
        'priority' => 0,
        'evaluated_at' => sr_now(),
    ];

    if ($accountId <= 0 || $baseAmount === 0 || $policies === []) {
        return $snapshot;
    }

    if (!function_exists('sr_member_groups_table_exists')) {
        require_once SR_ROOT . '/modules/member/helpers/groups.php';
    }

    if (!sr_module_enabled($pdo, 'member') || !sr_member_groups_table_exists($pdo)) {
        return $snapshot;
    }

    $stmt = $pdo->prepare(
        "SELECT DISTINCT g.id, g.group_key
         FROM sr_member_group_memberships m
         INNER JOIN sr_member_groups g ON g.id = m.group_id
         WHERE m.account_id = :account_id
           AND m.status = 'active'
           AND g.status = 'enabled'
           AND (m.expires_at IS NULL OR m.expires_at >= :now)"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'now' => sr_now(),
    ]);

    $memberGroups = [];
    foreach ($stmt->fetchAll() as $row) {
        $memberGroups[(string) $row['group_key']] = (int) $row['id'];
    }

    if ($memberGroups === []) {
        return $snapshot;
    }

    $matches = [];
    $levelSnapshot = null;
    foreach ($policies as $policy) {
        if (!is_array($policy) || (string) ($policy['status'] ?? 'active') !== 'active') {
            continue;
        }

        $groupKey = (string) ($policy['group_key'] ?? '');
        if (!isset($memberGroups[$groupKey])) {
            continue;
        }

        $minLevel = max(0, (int) ($policy['min_level'] ?? 0));
        if ($minLevel > 0) {
            if (!function_exists('sr_community_account_meets_min_level')) {
                continue;
            }
            if (!sr_community_account_meets_min_level($pdo, $accountId, $minLevel)) {
                continue;
            }
            if ($levelSnapshot === null && function_exists('sr_community_account_level_snapshot')) {
                $levelSnapshot = sr_community_account_level_snapshot($pdo, $accountId);
            }
            $policy['matched_level_value'] = is_array($levelSnapshot) ? (int) ($levelSnapshot['level_value'] ?? 0) : 0;
        }

        $policy['group_id'] = $memberGroups[$groupKey];
        $matches[] = $policy;
    }

    if ($matches === []) {
        return $snapshot;
    }

    usort($matches, static function (array $left, array $right): int {
        $priority = (int) ($right['priority'] ?? 0) <=> (int) ($left['priority'] ?? 0);
        if ($priority !== 0) {
            return $priority;
        }

        $policy = (int) ($left['policy_id'] ?? 0) <=> (int) ($right['policy_id'] ?? 0);
        if ($policy !== 0) {
            return $policy;
        }

        return (int) ($left['group_id'] ?? 0) <=> (int) ($right['group_id'] ?? 0);
    });

    $policy = $matches[0];
    $mode = (string) ($policy['mode'] ?? '');
    $value = (string) ($policy['value'] ?? '');
    $finalAmount = $baseAmount;
    $sign = $baseAmount < 0 ? -1 : 1;

    if ($mode === 'fixed') {
        $finalAmount = $sign * abs((int) $value);
    } elseif ($mode === 'multiplier') {
        $finalAmount = $sign * (int) floor(abs($baseAmount) * (float) $value);
    } elseif ($mode === 'delta') {
        $finalAmount = $baseAmount + (int) $value;
    } elseif ($mode === 'exempt' || $mode === 'disabled') {
        $finalAmount = 0;
    }

    return array_merge($snapshot, [
        'final_amount' => $finalAmount,
        'matched' => true,
        'matched_group_id' => (int) ($policy['group_id'] ?? 0),
        'matched_group_key' => (string) ($policy['group_key'] ?? ''),
        'matched_min_level' => max(0, (int) ($policy['min_level'] ?? 0)),
        'matched_level_value' => max(0, (int) ($policy['matched_level_value'] ?? 0)),
        'applied_policy_id' => (int) ($policy['policy_id'] ?? 0),
        'adjustment_mode' => $mode,
        'adjustment_value' => $value,
        'priority' => (int) ($policy['priority'] ?? 0),
    ]);
}
