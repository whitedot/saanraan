<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';
require_once SR_ROOT . '/modules/community/helpers/asset-events.php';

function sr_community_asset_modules(?PDO $pdo = null): array
{
    require_once SR_ROOT . '/modules/member/helpers/assets.php';

    return sr_member_ledger_asset_definitions($pdo);
}

function sr_community_asset_charge_policies(): array
{
    return [
        'once' => sr_t('community::asset_charge.once'),
        'every_view' => sr_t('community::asset_charge.every_view'),
        'every_download' => sr_t('community::asset_charge.every_download'),
        'every_action' => sr_t('community::asset_charge.every_action'),
    ];
}

function sr_community_asset_module_is_available(PDO $pdo, string $assetModule): bool
{
    $modules = sr_community_asset_modules($pdo);
    if (!isset($modules[$assetModule])) {
        return false;
    }

    $module = $modules[$assetModule];
    if (!sr_module_enabled($pdo, (string) ($module['module_key'] ?? ''))) {
        return false;
    }

    return function_exists((string) ($module['balance_function'] ?? ''))
        && function_exists((string) ($module['transaction_function'] ?? ''));
}

function sr_community_asset_module_options(PDO $pdo): array
{
    $available = [];
    foreach (sr_community_asset_modules($pdo) as $assetModule => $module) {
        if (sr_community_asset_module_is_available($pdo, (string) $assetModule)) {
            $available[$assetModule] = $module;
        }
    }

    return $available;
}

function sr_community_asset_module_label(string $assetModule, ?PDO $pdo = null): string
{
    $modules = sr_community_asset_modules($pdo);
    return isset($modules[$assetModule]) ? (string) $modules[$assetModule]['label'] : sr_t('community::asset.member_asset');
}

function sr_community_asset_module_unit_label(string $assetModule, ?PDO $pdo = null): string
{
    $modules = sr_community_asset_modules($pdo);
    return isset($modules[$assetModule]) ? (string) ($modules[$assetModule]['unit_label'] ?? '') : '';
}

function sr_community_asset_option_unit_label(array $assetModuleOptions, string $assetModule): string
{
    return isset($assetModuleOptions[$assetModule]) ? (string) ($assetModuleOptions[$assetModule]['unit_label'] ?? '') : '';
}

function sr_community_asset_single_amount_input_group_html(string $fieldName, int $amount, array $assetModuleOptions, string $assetModule, string $label): string
{
    $unitLabel = sr_community_asset_option_unit_label($assetModuleOptions, $assetModule);

    return '<div class="input-group admin-asset-single-amount-group" data-admin-asset-unit-group>'
        . '<input type="text" inputmode="numeric" pattern="[0-9,]*" name="' . sr_e($fieldName) . '" value="' . sr_e((string) max(0, $amount)) . '" class="form-input admin-asset-setting-amount" aria-label="' . sr_e($label) . '" data-admin-asset-amount-input>'
        . '<span class="input-group-text" data-admin-asset-unit-label>' . sr_e($unitLabel) . '</span>'
        . '</div>';
}

function sr_community_asset_module_key(string $value): string
{
    return isset(sr_community_asset_modules()[$value]) ? $value : 'point';
}

function sr_community_asset_module_key_or_empty(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }

    return isset(sr_community_asset_modules()[$value]) ? $value : 'point';
}

function sr_community_asset_composite_prefixes(): array
{
    return ['write_charge', 'comment_charge', 'message_charge', 'paid_read', 'paid_attachment_download'];
}

function sr_community_asset_prefix_uses_composite(string $prefix): bool
{
    return in_array($prefix, sr_community_asset_composite_prefixes(), true);
}

function sr_community_asset_deduction_order(): array
{
    return array_keys(sr_community_asset_modules());
}

function sr_community_asset_module_keys_from_value(mixed $value, bool $allowEmpty = false): array
{
    $rawValues = is_array($value) ? $value : preg_split('/[\s,]+/', (string) $value);
    $selected = [];
    foreach (is_array($rawValues) ? $rawValues : [] as $rawValue) {
        $assetModule = strtolower(trim((string) $rawValue));
        if (isset(sr_community_asset_modules()[$assetModule])) {
            $selected[$assetModule] = true;
        }
    }

    $ordered = [];
    foreach (sr_community_asset_deduction_order() as $assetModule) {
        if (isset($selected[$assetModule])) {
            $ordered[] = $assetModule;
        }
    }

    if ($ordered !== []) {
        return $ordered;
    }

    return $allowEmpty ? [] : ['point'];
}

function sr_community_asset_module_value_from_keys(array $assetModules, bool $allowEmpty = false): string
{
    return implode(',', sr_community_asset_module_keys_from_value($assetModules, $allowEmpty));
}

function sr_community_asset_module_labels(string $assetModuleValue, ?PDO $pdo = null): string
{
    $labels = [];
    foreach (sr_community_asset_module_keys_from_value($assetModuleValue) as $assetModule) {
        $labels[] = sr_community_asset_module_label($assetModule, $pdo);
    }

    return $labels !== [] ? implode(', ', $labels) : sr_t('community::asset.member_asset');
}

function sr_community_require_asset_group_policy_helpers(): void
{
    if (!function_exists('sr_admin_asset_group_policies_from_json')) {
        require_once SR_ROOT . '/modules/admin/helpers/asset-group-policies.php';
    }
    if (!function_exists('sr_community_account_meets_min_level') && is_file(SR_ROOT . '/modules/community/helpers/levels.php')) {
        require_once SR_ROOT . '/modules/community/helpers/levels.php';
    }
}

function sr_community_asset_group_policy_json_from_value(mixed $value): string
{
    sr_community_require_asset_group_policy_helpers();

    return sr_admin_asset_group_policy_json_from_value($value);
}

function sr_community_asset_group_policies_from_value(mixed $value): array
{
    sr_community_require_asset_group_policy_helpers();

    if (sr_community_asset_policy_set_ids_from_value($value) !== []) {
        return [];
    }

    try {
        return sr_admin_asset_group_policies_from_json(is_string($value) ? $value : sr_admin_asset_group_policy_json_from_value($value));
    } catch (Throwable $exception) {
        return [];
    }
}

function sr_community_asset_group_policy_json_from_post(string $fieldName): string
{
    sr_community_require_asset_group_policy_helpers();

    return sr_admin_asset_group_policy_json_from_value(sr_admin_asset_group_policies_from_post($fieldName));
}

function sr_community_asset_policy_set_key_is_valid(string $setKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $setKey) === 1;
}

function sr_community_asset_policy_set_statuses(): array
{
    return ['enabled', 'disabled', 'archived'];
}

function sr_community_asset_policy_set_sort_options(): array
{
    return [
        'title' => ['columns' => ['title', 'id']],
        'set_key' => ['columns' => ['set_key', 'id']],
        'status' => ['columns' => ['status', 'title', 'id']],
        'updated_at' => ['columns' => ['updated_at', 'id']],
        'created_at' => ['columns' => ['created_at', 'id']],
    ];
}

function sr_community_asset_policy_set_default_sort(): array
{
    return sr_admin_sort_default('title', 'asc');
}

function sr_community_asset_policy_sets(PDO $pdo, bool $enabledOnly = false, array $sort = []): array
{
    try {
        $sql = 'SELECT * FROM sr_community_asset_policy_sets';
        if ($enabledOnly) {
            $sql .= " WHERE status = 'enabled'";
        }
        $sql .= function_exists('sr_admin_sort_order_sql')
            ? sr_admin_sort_order_sql(sr_community_asset_policy_set_sort_options(), $sort, sr_community_asset_policy_set_default_sort())
            : ' ORDER BY title ASC, id ASC';
        $stmt = $pdo->query($sql);
        return $stmt !== false ? $stmt->fetchAll() : [];
    } catch (Throwable $exception) {
        return [];
    }
}

function sr_community_asset_policy_set_by_id(PDO $pdo, int $setId): ?array
{
    if ($setId < 1) {
        return null;
    }
    try {
        $stmt = $pdo->prepare('SELECT * FROM sr_community_asset_policy_sets WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $setId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    } catch (Throwable $exception) {
        return null;
    }
}

function sr_community_asset_policy_set_key_exists(PDO $pdo, string $setKey, int $exceptId = 0): bool
{
    $stmt = $pdo->prepare('SELECT id FROM sr_community_asset_policy_sets WHERE set_key = :set_key AND id <> :id LIMIT 1');
    $stmt->execute(['set_key' => $setKey, 'id' => $exceptId]);
    return is_array($stmt->fetch());
}

function sr_community_save_asset_policy_set(PDO $pdo, array $values, int $accountId, int $setId = 0): int
{
    $now = sr_now();
    $params = [
        'set_key' => (string) ($values['set_key'] ?? ''),
        'title' => (string) ($values['title'] ?? ''),
        'description' => (string) ($values['description'] ?? ''),
        'status' => (string) ($values['status'] ?? 'enabled'),
        'policies_json' => sr_community_asset_group_policy_json_from_value($values['policies_json'] ?? ''),
        'updated_by' => $accountId,
        'updated_at' => $now,
    ];

    if ($setId > 0) {
        $params['id'] = $setId;
        $stmt = $pdo->prepare(
            'UPDATE sr_community_asset_policy_sets
             SET set_key = :set_key, title = :title, description = :description, status = :status,
                 policies_json = :policies_json, updated_by = :updated_by, updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute($params);
        return $setId;
    }

    $params['created_by'] = $accountId;
    $params['created_at'] = $now;
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_asset_policy_sets
            (set_key, title, description, status, policies_json, created_by, updated_by, created_at, updated_at)
         VALUES
            (:set_key, :title, :description, :status, :policies_json, :created_by, :updated_by, :created_at, :updated_at)'
    );
    $stmt->execute($params);
    return (int) $pdo->lastInsertId();
}

function sr_community_asset_policy_set_ids_from_value(mixed $value): array
{
    $rawValues = [];
    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return [];
        }
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            if (is_array($decoded['policy_set_ids'] ?? null)) {
                $rawValues = $decoded['policy_set_ids'];
            } elseif ($decoded === array_values($decoded)) {
                $rawValues = $decoded;
            }
        } else {
            $rawValues = preg_split('/[\s,]+/', $trimmed) ?: [];
        }
    } elseif (is_array($value)) {
        $rawValues = is_array($value['policy_set_ids'] ?? null) ? $value['policy_set_ids'] : $value;
    }

    $selected = [];
    foreach ($rawValues as $rawValue) {
        if (!is_scalar($rawValue)) {
            continue;
        }
        $setId = (int) $rawValue;
        if ($setId > 0) {
            $selected[$setId] = true;
        }
    }

    return array_keys($selected);
}

function sr_community_asset_policy_set_ids_with_legacy(mixed $value, int $legacySetId = 0): array
{
    $setIds = sr_community_asset_policy_set_ids_from_value($value);
    if ($setIds === [] && $legacySetId > 0) {
        $setIds[] = $legacySetId;
    }

    return $setIds;
}

function sr_community_asset_policy_set_selection_json_from_ids(array $setIds): string
{
    $setIds = sr_community_asset_policy_set_ids_from_value($setIds);
    if ($setIds === []) {
        return '';
    }

    $json = json_encode(['policy_set_ids' => $setIds], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '';
}

function sr_community_asset_policy_set_first_id(array $setIds): int
{
    $setIds = sr_community_asset_policy_set_ids_from_value($setIds);
    return (int) ($setIds[0] ?? 0);
}

function sr_community_asset_policy_set_options(array $policySets): array
{
    $options = [];
    foreach ($policySets as $policySet) {
        $setId = (int) ($policySet['id'] ?? 0);
        if ($setId < 1) {
            continue;
        }
        $label = (string) ($policySet['title'] ?? $policySet['set_key'] ?? $setId);
        if ((string) ($policySet['status'] ?? '') !== 'enabled') {
            $label .= ' (' . sr_admin_code_label((string) ($policySet['status'] ?? ''), 'content_status') . ')';
        }
        $options[(string) $setId] = $label;
    }

    return $options;
}

function sr_community_asset_policy_set_picker_options(array $policySets, string $operation = 'neutral', ?PDO $pdo = null): array
{
    $operation = in_array($operation, ['grant', 'use', 'neutral'], true) ? $operation : 'neutral';
    $options = [];
    foreach ($policySets as $policySet) {
        $setId = (int) ($policySet['id'] ?? 0);
        if ($setId < 1) {
            continue;
        }

        $summaries = [
            'grant' => sr_community_asset_policy_set_summary($policySet, 'grant', [], $pdo),
            'use' => sr_community_asset_policy_set_summary($policySet, 'use', [], $pdo),
            'neutral' => sr_community_asset_policy_set_summary($policySet, 'neutral', [], $pdo),
        ];
        $assetModules = sr_community_asset_policy_set_asset_modules($policySet);
        foreach ($assetModules as $assetModule) {
            foreach (['grant', 'use', 'neutral'] as $summaryOperation) {
                $summaries[$summaryOperation . '_' . $assetModule] = sr_community_asset_policy_set_summary($policySet, $summaryOperation, [$assetModule], $pdo);
            }
        }

        $options[(string) $setId] = [
            'label' => (string) (sr_community_asset_policy_set_options([$policySet])[(string) $setId] ?? $setId),
            'summary' => sr_community_asset_policy_set_summary($policySet, $operation, [], $pdo),
            'summaries' => $summaries,
            'assets' => $assetModules,
        ];
    }

    return $options;
}

function sr_community_asset_policy_set_asset_modules(array $policySet): array
{
    sr_community_require_asset_group_policy_helpers();
    try {
        $policies = sr_admin_asset_group_policies_from_json((string) ($policySet['policies_json'] ?? ''));
    } catch (Throwable) {
        return [];
    }

    $assetModules = [];
    foreach ($policies as $policy) {
        if (!is_array($policy) || (string) ($policy['status'] ?? 'active') !== 'active') {
            continue;
        }
        $assetModule = strtolower(trim((string) ($policy['asset_module'] ?? '')));
        if ($assetModule !== '' && isset(sr_community_asset_modules()[$assetModule])) {
            $assetModules[$assetModule] = true;
        }
    }

    $ordered = [];
    foreach (sr_community_asset_deduction_order() as $assetModule) {
        if (isset($assetModules[$assetModule])) {
            $ordered[] = $assetModule;
        }
    }

    return $ordered;
}

function sr_community_asset_policy_set_summary(array $policySet, string $operation = 'neutral', array $assetModules = [], ?PDO $pdo = null): string
{
    sr_community_require_asset_group_policy_helpers();
    $operation = in_array($operation, ['grant', 'use', 'neutral'], true) ? $operation : 'neutral';
    $assetModules = sr_community_asset_module_keys_from_value($assetModules, true);
    $assetFilter = array_fill_keys($assetModules, true);

    try {
        $policies = sr_admin_asset_group_policies_from_json((string) ($policySet['policies_json'] ?? ''));
    } catch (Throwable) {
        return '';
    }

    $summaries = [];
    $eligibleCount = 0;
    foreach ($policies as $policy) {
        if (!is_array($policy) || (string) ($policy['status'] ?? 'active') !== 'active') {
            continue;
        }

        $groupKey = (string) ($policy['group_key'] ?? '');
        $policyAssetModule = strtolower(trim((string) ($policy['asset_module'] ?? '')));
        if ($policyAssetModule !== '' && !isset(sr_community_asset_modules()[$policyAssetModule])) {
            $policyAssetModule = '';
        }
        if ($assetFilter !== [] && $policyAssetModule !== '' && !isset($assetFilter[$policyAssetModule])) {
            continue;
        }
        $eligibleCount += 1;
        $assetModule = $policyAssetModule !== '' ? $policyAssetModule : (string) ($assetModules[0] ?? '');
        $assetLabel = $assetModule !== '' ? sr_community_asset_module_label($assetModule, $pdo) : sr_t('community::asset.member_asset');
        $mode = (string) ($policy['mode'] ?? '');
        $modeLabel = function_exists('sr_admin_asset_group_policy_mode_label') ? sr_admin_asset_group_policy_mode_label($mode) : $mode;
        $value = sr_admin_asset_group_policy_value_for_asset($policy, $assetModule);
        $valueLabel = $value !== '' ? ' ' . sr_community_asset_policy_set_summary_value_label($value, $mode, $operation) : '';
        $levelLabel = max(0, (int) ($policy['min_level'] ?? 0)) > 0 ? ' L' . (string) max(0, (int) ($policy['min_level'] ?? 0)) . '+' : '';
        $summaries[] = trim($groupKey . $levelLabel . ': ' . $assetLabel . ' ' . $modeLabel . $valueLabel);
        if (count($summaries) >= 3) {
            break;
        }
    }

    if ($summaries === []) {
        return '활성 회원 그룹별 적용 없음';
    }

    $remaining = max(0, $eligibleCount - count($summaries));
    return implode(' / ', $summaries) . ($remaining > 0 ? ' 외 ' . (string) $remaining . '개' : '');
}

function sr_community_asset_policy_set_summary_value_label(string $value, string $mode, string $operation = 'neutral'): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if ($mode === 'multiplier') {
        return $value . '배';
    }

    if (in_array($mode, ['fixed', 'delta'], true) && preg_match('/\A\d+\z/', $value) === 1 && $operation === 'use' && (int) $value > 0) {
        return '-' . $value;
    }

    return $value;
}

function sr_community_asset_policy_set_checkboxes_html(string $id, string $name, array $policySets, array $selectedIds, string $operation = 'neutral', string $assetSourceSelector = '', ?PDO $pdo = null): string
{
    $operation = in_array($operation, ['grant', 'use', 'neutral'], true) ? $operation : 'neutral';
    $rootAttributes = ' data-admin-select-badge-summary-default="' . sr_e($operation) . '"';
    if ($assetSourceSelector !== '') {
        $rootAttributes .= ' data-admin-select-badge-asset-source="' . sr_e($assetSourceSelector) . '"';
    }
    return sr_admin_select_badge_list_html($id, $name, sr_community_asset_policy_set_picker_options($policySets, $operation, $pdo), array_map('strval', sr_community_asset_policy_set_ids_from_value($selectedIds)), '등록된 회원 그룹별 적용 없음', '그룹 선택', $rootAttributes);
}

function sr_community_asset_policy_set_ids_validation_errors(PDO $pdo, array $setIds, string $label): array
{
    $errors = [];
    foreach (sr_community_asset_policy_set_ids_from_value($setIds) as $setId) {
        if (!is_array(sr_community_asset_policy_set_by_id($pdo, (int) $setId))) {
            $errors[] = $label . ' 회원 그룹별 적용을 찾을 수 없습니다.';
            break;
        }
    }

    return $errors;
}

function sr_community_asset_policy_set_asset_match_errors(PDO $pdo, array $setIds, array $assetModules, string $label): array
{
    $errors = [];
    $assetModules = sr_community_asset_module_keys_from_value($assetModules, true);
    if ($assetModules === []) {
        return $errors;
    }
    $assetMap = array_fill_keys($assetModules, true);
    foreach (sr_community_asset_policy_set_ids_from_value($setIds) as $setId) {
        $policySet = sr_community_asset_policy_set_by_id($pdo, (int) $setId);
        if (!is_array($policySet)) {
            continue;
        }
        $policyAssets = sr_community_asset_policy_set_asset_modules($policySet);
        if ($policyAssets === []) {
            continue;
        }
        $matched = false;
        foreach ($policyAssets as $policyAsset) {
            if (isset($assetMap[$policyAsset])) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            $errors[] = $label . ' 회원 그룹별 적용은 선택한 포인트/금액 항목에 맞는 정책만 선택하세요.';
            break;
        }
    }

    return $errors;
}

function sr_community_asset_amounts_with_group_policy(PDO $pdo, int $accountId, array $assetModules, array $amounts, int $fallbackAmount, mixed $policyValue, int $policySetId = 0, string $operation = 'neutral'): array
{
    sr_community_require_asset_group_policy_helpers();
    $operation = in_array($operation, ['grant', 'use', 'neutral'], true) ? $operation : 'neutral';
    $policySetIds = sr_community_asset_policy_set_ids_with_legacy($policyValue, $policySetId);
    $policySetTitles = [];
    $policies = [];
    if ($policySetIds !== []) {
        $policyIndex = 1;
        foreach ($policySetIds as $selectedSetId) {
            $policySet = sr_community_asset_policy_set_by_id($pdo, (int) $selectedSetId);
            if (!is_array($policySet) || (string) ($policySet['status'] ?? '') !== 'enabled') {
                continue;
            }
            $policySetTitles[] = (string) ($policySet['title'] ?? $policySet['set_key'] ?? $selectedSetId);
            foreach (sr_community_asset_group_policies_from_value((string) ($policySet['policies_json'] ?? '')) as $policy) {
                $policy['policy_id'] = $policyIndex;
                $policyIndex += 1;
                $policies[] = $policy;
            }
        }
    } else {
        $policies = sr_community_asset_group_policies_from_value($policyValue);
    }
    $sourceAmounts = $amounts;
    if ($sourceAmounts === [] && $assetModules !== []) {
        $sourceAmounts[(string) $assetModules[0]] = $fallbackAmount;
    }

    $adjustedAmounts = [];
    $snapshots = [];
    foreach ($sourceAmounts as $assetModule => $baseAmount) {
        $baseAmount = max(0, (int) $baseAmount);
        $snapshot = sr_admin_asset_group_policy_apply($pdo, $accountId, $baseAmount, $policies, (string) $assetModule, $operation);
        $finalAmount = max(0, (int) ($snapshot['final_amount'] ?? $baseAmount));
        $snapshot['asset_module'] = (string) $assetModule;
        $snapshot['policy_set_id'] = (int) ($policySetIds[0] ?? 0);
        $snapshot['policy_set_ids'] = $policySetIds;
        $snapshot['policy_set_key'] = '';
        $snapshot['policy_set_title'] = implode(', ', $policySetTitles);
        $snapshot['final_amount'] = $finalAmount;
        $snapshots[(string) $assetModule] = $snapshot;
        if ($finalAmount > 0) {
            $adjustedAmounts[(string) $assetModule] = $finalAmount;
        }
    }

    return [
        'amounts' => $adjustedAmounts,
        'amount' => sr_community_asset_amount_total($adjustedAmounts),
        'snapshots' => $snapshots,
        'policies_applied' => $policies !== [],
    ];
}

function sr_community_asset_group_policy_snapshot_json(array $snapshots): string
{
    if ($snapshots === []) {
        return '';
    }

    $json = json_encode(array_values($snapshots), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '';
}

function sr_community_asset_modules_available(PDO $pdo, array $assetModules): bool
{
    $assetModules = sr_community_asset_module_keys_from_value($assetModules, true);
    if ($assetModules === []) {
        return false;
    }

    foreach ($assetModules as $assetModule) {
        if (!sr_community_asset_module_is_available($pdo, $assetModule)) {
            return false;
        }
    }

    return true;
}

function sr_community_asset_charge_policy(string $value, string $fallback = 'once'): string
{
    return isset(sr_community_asset_charge_policies()[$value]) ? $value : $fallback;
}

function sr_community_asset_policy_requires_confirmation(string $chargePolicy): bool
{
    return in_array($chargePolicy, ['once', 'every_view', 'every_download', 'every_action'], true);
}

function sr_community_asset_transaction_retry_max_attempts(): int
{
    return 3;
}

function sr_community_asset_is_retryable_transaction_exception(Throwable $exception): bool
{
    if (!$exception instanceof PDOException) {
        return false;
    }

    $sqlState = (string) $exception->getCode();
    $driverCode = isset($exception->errorInfo[1]) ? (int) $exception->errorInfo[1] : 0;

    return $sqlState === '40001' || in_array($driverCode, [1205, 1213], true);
}

function sr_community_asset_retry_operation(PDO $pdo, callable $operation): array
{
    if ($pdo->inTransaction()) {
        return $operation();
    }

    $maxAttempts = sr_community_asset_transaction_retry_max_attempts();
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            return $operation();
        } catch (Throwable $exception) {
            if ($attempt >= $maxAttempts || !sr_community_asset_is_retryable_transaction_exception($exception)) {
                throw $exception;
            }
            usleep(50000 * $attempt);
        }
    }

    throw new RuntimeException('커뮤니티 자산 처리 재시도 횟수를 초과했습니다.');
}

function sr_community_asset_confirmation_required_message(): string
{
    return sr_t('community::action.error.asset_confirmation_required');
}

function sr_community_asset_log_status_completed(): string
{
    return 'completed';
}

function sr_community_asset_log_status_pending(): string
{
    return 'pending';
}

function sr_community_asset_snapshot_schema_version(): string
{
    return 'asset_settlement_snapshot_v1';
}

function sr_community_asset_rounding_policy_version(): string
{
    return 'asset_settlement_rounding_v1';
}

function sr_community_asset_settlement_kind(string $direction, int $amount, int $settlementAmount, string $purchasePowerSnapshotJson): string
{
    if ($direction !== 'use') {
        return 'free';
    }

    if ($settlementAmount > 0) {
        return 'paid';
    }

    if ($amount === 0) {
        return 'paid_settled_zero';
    }

    return $purchasePowerSnapshotJson !== '' ? 'paid' : 'legacy_unknown';
}

function sr_community_asset_confirmation_session_key(string $eventKey, string $subjectType, int $accountId, int $subjectId): string
{
    return $eventKey . ':' . $subjectType . ':' . (string) $accountId . ':' . (string) $subjectId;
}

function sr_community_asset_confirmation_signed_token(string $eventKey, string $subjectType, int $accountId, int $subjectId, string $fingerprint): string
{
    if ($eventKey === '' || $subjectType === '' || $accountId < 1 || $subjectId < 1 || $fingerprint === '' || session_id() === '') {
        return '';
    }

    $appKey = sr_app_key(sr_runtime_config());
    if ($appKey === '') {
        return '';
    }

    return hash_hmac('sha256', implode('|', [
        'community.asset.confirmation.v2',
        $eventKey,
        $subjectType,
        (string) $accountId,
        (string) $subjectId,
        $fingerprint,
        session_id(),
    ]), $appKey);
}

function sr_community_asset_confirmation_request_token(string $eventKey, string $subjectType, int $accountId, int $subjectId, string $fingerprint): string
{
    if ($eventKey === '' || $subjectType === '' || $accountId < 1 || $subjectId < 1 || $fingerprint === '') {
        return '';
    }

    $signedToken = sr_community_asset_confirmation_signed_token($eventKey, $subjectType, $accountId, $subjectId, $fingerprint);
    if ($signedToken !== '') {
        return $signedToken;
    }

    if (!isset($_SESSION['sr_community_asset_confirmation_requests']) || !is_array($_SESSION['sr_community_asset_confirmation_requests'])) {
        $_SESSION['sr_community_asset_confirmation_requests'] = [];
    }

    $key = sr_community_asset_confirmation_session_key($eventKey, $subjectType, $accountId, $subjectId);
    $session = is_array($_SESSION['sr_community_asset_confirmation_requests'][$key] ?? null) ? $_SESSION['sr_community_asset_confirmation_requests'][$key] : [];
    $createdAt = (int) ($session['created_at'] ?? 0);
    $sessionFingerprint = (string) ($session['fingerprint'] ?? '');
    $token = (string) ($session['token'] ?? '');
    if ($createdAt >= time() - 300 && $token !== '' && hash_equals($sessionFingerprint, $fingerprint)) {
        return $token;
    }

    $token = bin2hex(random_bytes(16));
    $_SESSION['sr_community_asset_confirmation_requests'][$key] = [
        'created_at' => time(),
        'fingerprint' => $fingerprint,
        'token' => $token,
    ];

    return $token;
}

function sr_community_asset_confirmation_request_token_valid(string $eventKey, string $subjectType, int $accountId, int $subjectId, string $fingerprint, string $token): bool
{
    if ($token === '' || preg_match('/\A[a-f0-9]{32}(?:[a-f0-9]{32})?\z/', $token) !== 1) {
        return false;
    }

    $signedToken = sr_community_asset_confirmation_signed_token($eventKey, $subjectType, $accountId, $subjectId, $fingerprint);
    if ($signedToken !== '' && hash_equals($signedToken, $token)) {
        return true;
    }

    if (strlen($token) !== 32) {
        return false;
    }

    $key = sr_community_asset_confirmation_session_key($eventKey, $subjectType, $accountId, $subjectId);
    $sessions = is_array($_SESSION['sr_community_asset_confirmation_requests'] ?? null) ? $_SESSION['sr_community_asset_confirmation_requests'] : [];
    $session = isset($sessions[$key]) && is_array($sessions[$key]) ? $sessions[$key] : [];
    $createdAt = (int) ($session['created_at'] ?? 0);

    return $createdAt >= time() - 300
        && $fingerprint !== ''
        && hash_equals((string) ($session['fingerprint'] ?? ''), $fingerprint)
        && hash_equals((string) ($session['token'] ?? ''), $token);
}

function sr_community_asset_confirmation_fingerprint(string $eventKey, string $subjectType, string $chargePolicy, string $assetModuleValue, int $amount, array $amounts = [], string $policySnapshotJson = '', string $settlementCurrency = ''): string
{
    ksort($amounts, SORT_STRING);
    $amountParts = [];
    foreach ($amounts as $assetModule => $assetAmount) {
        $amountParts[] = (string) $assetModule . ':' . (string) max(0, (int) $assetAmount);
    }

    $settlementCurrency = function_exists('sr_normalize_currency_code') ? sr_normalize_currency_code($settlementCurrency) : strtoupper(trim($settlementCurrency));

    return hash('sha256', implode('|', [$eventKey, $subjectType, $chargePolicy, $assetModuleValue, (string) max(0, $amount), $settlementCurrency, implode(',', $amountParts), $policySnapshotJson]));
}

function sr_community_mark_asset_confirmation_session(string $eventKey, string $subjectType, int $accountId, int $subjectId, string $fingerprint): void
{
    if ($eventKey === '' || $subjectType === '' || $accountId < 1 || $subjectId < 1 || $fingerprint === '') {
        return;
    }

    if (!isset($_SESSION['sr_community_asset_confirmations']) || !is_array($_SESSION['sr_community_asset_confirmations'])) {
        $_SESSION['sr_community_asset_confirmations'] = [];
    }

    $_SESSION['sr_community_asset_confirmations'][sr_community_asset_confirmation_session_key($eventKey, $subjectType, $accountId, $subjectId)] = [
        'created_at' => time(),
        'fingerprint' => $fingerprint,
    ];
}

function sr_community_consume_asset_confirmation_session(string $eventKey, string $subjectType, int $accountId, int $subjectId, string $fingerprint): bool
{
    $key = sr_community_asset_confirmation_session_key($eventKey, $subjectType, $accountId, $subjectId);
    $sessions = is_array($_SESSION['sr_community_asset_confirmations'] ?? null) ? $_SESSION['sr_community_asset_confirmations'] : [];
    $session = isset($sessions[$key]) && is_array($sessions[$key]) ? $sessions[$key] : [];
    $createdAt = (int) ($session['created_at'] ?? 0);
    $sessionFingerprint = (string) ($session['fingerprint'] ?? '');
    unset($_SESSION['sr_community_asset_confirmations'][$key]);

    return $createdAt > 0
        && $createdAt >= time() - 300
        && $fingerprint !== ''
        && hash_equals($sessionFingerprint, $fingerprint);
}

function sr_community_once_history_policy_values(): array
{
    return [
        'all_access' => sr_t('community::ui.once_history_policy.all_access'),
        'asset_any' => sr_t('community::ui.once_history_policy.asset_any'),
        'current_asset_once' => sr_t('community::ui.once_history_policy.current_asset_once'),
    ];
}

function sr_community_once_history_policy(string $policy): string
{
    return array_key_exists($policy, sr_community_once_history_policy_values()) ? $policy : 'all_access';
}

function sr_community_asset_policy_source_values(): array
{
    return ['global', 'group', 'board'];
}

function sr_community_asset_policy_source(string $value): string
{
    if ($value === 'all') {
        return 'global';
    }

    if ($value === 'here_only') {
        return 'board';
    }

    return in_array($value, sr_community_asset_policy_source_values(), true) ? $value : 'global';
}

function sr_community_board_asset_policy_source(PDO $pdo, int $boardId): string
{
    $value = sr_community_board_setting_value($pdo, $boardId, 'asset_policy_source');
    if (is_string($value) && $value !== '') {
        return sr_community_asset_policy_source($value);
    }

    foreach (sr_community_asset_setting_keys() as $settingKey) {
        $settingValue = sr_community_board_setting_value($pdo, $boardId, $settingKey);
        if (is_string($settingValue) && $settingValue !== '') {
            return 'board';
        }
    }

    return 'global';
}

function sr_community_asset_prefix_setting_keys(string $prefix): array
{
    if (!in_array($prefix, sr_community_asset_setting_prefixes(), true)) {
        return [];
    }

    $keys = [
        $prefix . '_enabled',
        $prefix . '_asset_module',
        $prefix . '_amount',
        $prefix . '_group_policies_json',
        $prefix . '_policy_set_id',
    ];
    if (sr_community_asset_prefix_uses_composite($prefix)) {
        $keys[] = $prefix . '_settlement_currency';
        $keys[] = $prefix . '_amounts_json';
    }

    if (in_array($prefix, ['paid_read', 'paid_attachment_download'], true)) {
        $keys[] = $prefix . '_charge_policy';
    }

    return $keys;
}

function sr_community_time_html(?string $value, string $emptyText = ''): string
{
    return sr_relative_time_html($value, $emptyText);
}

function sr_community_publisher_reward_config(PDO $pdo, array $board, array $settings): array
{
    $enabled = sr_community_asset_bool_config($pdo, $board, $settings, 'paid_attachment_download_publisher_reward_enabled');
    $rate = (int) sr_community_asset_board_setting($pdo, $board, $settings, 'paid_attachment_download_publisher_reward_rate', (string) ($settings['paid_attachment_download_publisher_reward_rate'] ?? 0));

    return [
        'enabled' => $enabled,
        'rate' => min(100, max(0, $rate)),
    ];
}

function sr_community_asset_prefix_from_setting_key(string $settingKey): string
{
    foreach (sr_community_asset_setting_prefixes() as $prefix) {
        if (str_starts_with($settingKey, $prefix . '_')) {
            return $prefix;
        }
    }

    return '';
}

function sr_community_board_asset_setting_source(PDO $pdo, int $boardId, string $prefix): string
{
    if ($boardId < 1) {
        return 'all';
    }

    $sources = function_exists('sr_community_board_setting_sources') ? sr_community_board_setting_sources($pdo, $boardId) : [];
    foreach (sr_community_asset_prefix_setting_keys($prefix) as $settingKey) {
        if (array_key_exists((string) $settingKey, $sources)) {
            return sr_community_normalize_board_setting_source((string) $sources[(string) $settingKey]);
        }
    }

    $legacySource = sr_community_board_asset_policy_source($pdo, $boardId);
    if ($legacySource === 'global') {
        return 'all';
    }

    return sr_community_normalize_board_setting_source($legacySource);
}

function sr_community_board_asset_setting_key_source(PDO $pdo, int $boardId, string $settingKey): string
{
    $prefix = sr_community_asset_prefix_from_setting_key($settingKey);
    if ($boardId < 1 || $prefix === '') {
        return 'all';
    }

    $sources = function_exists('sr_community_board_setting_sources') ? sr_community_board_setting_sources($pdo, $boardId) : [];
    if (array_key_exists($settingKey, $sources)) {
        return sr_community_normalize_board_setting_source((string) $sources[$settingKey]);
    }

    return sr_community_board_asset_setting_source($pdo, $boardId, $prefix);
}

function sr_community_asset_setting_keys(): array
{
    $keys = [];
    foreach (sr_community_asset_setting_prefixes() as $prefix) {
        foreach (sr_community_asset_prefix_setting_keys($prefix) as $settingKey) {
            $keys[] = $settingKey;
        }
    }
    $keys[] = 'paid_attachment_download_publisher_reward_enabled';
    $keys[] = 'paid_attachment_download_publisher_reward_rate';

    return $keys;
}

function sr_community_asset_bool_value_for_audit(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function sr_community_asset_settings_for_audit(array $settings, bool $includeReversalSettings = false): array
{
    $auditSettings = [];
    $prefixes = array_key_exists('message_charge_enabled', $settings)
        ? sr_community_module_asset_setting_prefixes()
        : sr_community_asset_setting_prefixes();
    foreach ($prefixes as $assetPrefix) {
        $moduleValue = (string) ($settings[$assetPrefix . '_asset_module'] ?? '');
        $auditSettings[$assetPrefix . '_enabled'] = sr_community_asset_bool_value_for_audit($settings[$assetPrefix . '_enabled'] ?? false);
        $auditSettings[$assetPrefix . '_asset_module'] = sr_community_asset_prefix_uses_composite($assetPrefix)
            ? sr_community_asset_module_value_from_keys(sr_community_asset_module_keys_from_value($moduleValue, true), true)
            : sr_community_asset_module_key_or_empty($moduleValue);
        $auditSettings[$assetPrefix . '_amount'] = max(0, (int) ($settings[$assetPrefix . '_amount'] ?? 0));
        if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
            $auditSettings[$assetPrefix . '_settlement_currency'] = function_exists('sr_normalize_currency_code')
                ? sr_normalize_currency_code((string) ($settings[$assetPrefix . '_settlement_currency'] ?? ''))
                : strtoupper(trim((string) ($settings[$assetPrefix . '_settlement_currency'] ?? '')));
        }
        $auditSettings[$assetPrefix . '_group_policies_json'] = sr_community_asset_group_policy_json_from_value($settings[$assetPrefix . '_group_policies_json'] ?? '');
        $auditSettings[$assetPrefix . '_policy_set_id'] = max(0, (int) ($settings[$assetPrefix . '_policy_set_id'] ?? 0));
        if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
            $auditSettings[$assetPrefix . '_amounts_json'] = sr_community_asset_amounts_json_from_map(
                sr_community_asset_amounts_from_value(
                    $settings[$assetPrefix . '_amounts_json'] ?? '',
                    sr_community_asset_module_keys_from_value($auditSettings[$assetPrefix . '_asset_module'], true),
                    $auditSettings[$assetPrefix . '_amount']
                )
            );
        }
        if (in_array($assetPrefix, ['paid_read', 'paid_attachment_download'], true)) {
            $auditSettings[$assetPrefix . '_charge_policy'] = sr_community_asset_charge_policy(
                (string) ($settings[$assetPrefix . '_charge_policy'] ?? 'once'),
                'once'
            );
        }
    }

    if ($includeReversalSettings) {
        $auditSettings['post_reward_reversal_enabled'] = sr_community_asset_bool_value_for_audit($settings['post_reward_reversal_enabled'] ?? false);
        $auditSettings['comment_reward_reversal_enabled'] = sr_community_asset_bool_value_for_audit($settings['comment_reward_reversal_enabled'] ?? false);
    }
    $auditSettings['paid_attachment_download_publisher_reward_enabled'] = sr_community_asset_bool_value_for_audit($settings['paid_attachment_download_publisher_reward_enabled'] ?? false);
    $auditSettings['paid_attachment_download_publisher_reward_rate'] = min(100, max(0, (int) ($settings['paid_attachment_download_publisher_reward_rate'] ?? 0)));
    $auditSettings['once_history_policy'] = sr_community_once_history_policy((string) ($settings['once_history_policy'] ?? 'all_access'));

    return $auditSettings;
}

function sr_community_board_asset_settings_for_audit(PDO $pdo, int $boardId, bool $includeSources = true): array
{
    if ($boardId < 1) {
        return [];
    }

    $board = sr_community_board_by_id($pdo, $boardId);
    if (!is_array($board)) {
        return [];
    }

    $communitySettings = sr_community_settings($pdo);
    $settings = [];
    foreach (sr_community_asset_setting_keys() as $assetSettingKey) {
        $settings[$assetSettingKey] = sr_community_asset_board_setting(
            $pdo,
            $board,
            $communitySettings,
            (string) $assetSettingKey,
            str_ends_with((string) $assetSettingKey, '_asset_module') ? '' : '0'
        );
    }
    $auditSettings = sr_community_asset_settings_for_audit($settings);
    if ($includeSources) {
        foreach (sr_community_asset_setting_keys() as $assetSettingKey) {
            $auditSettings['source_' . (string) $assetSettingKey] = sr_community_board_asset_setting_key_source($pdo, $boardId, (string) $assetSettingKey);
        }
    }

    return $auditSettings;
}

function sr_community_asset_balance(PDO $pdo, string $assetModule, int $accountId): int
{
    if (!sr_community_asset_module_is_available($pdo, $assetModule)) {
        return 0;
    }

    $module = sr_community_asset_modules($pdo)[$assetModule];
    $balanceFunction = (string) $module['balance_function'];

    return (int) $balanceFunction($pdo, $accountId);
}

function sr_community_asset_amounts_from_value(mixed $value, array $assetModules = [], int $fallbackAmount = 0): array
{
    $decoded = null;
    if (is_string($value) && trim($value) !== '') {
        $decoded = json_decode($value, true);
    } elseif (is_array($value)) {
        $decoded = $value;
    }

    $amounts = [];
    if (is_array($decoded)) {
        foreach ($decoded as $assetModule => $amount) {
            $assetModule = sr_community_asset_module_key((string) $assetModule);
            $amountValue = is_string($amount) && preg_match('/\A\d{1,3}(?:,\d{3})+\z/', trim($amount)) === 1
                ? str_replace(',', '', trim($amount))
                : $amount;
            $amounts[$assetModule] = min(999999999, max(0, (int) $amountValue));
        }
    }

    $ordered = [];
    $assetModules = sr_community_asset_module_keys_from_value($assetModules, true);
    foreach ($assetModules as $assetModule) {
        $amount = $amounts[$assetModule] ?? 0;
        if ($amount <= 0 && $fallbackAmount > 0 && count($assetModules) === 1) {
            $amount = $fallbackAmount;
        }
        if ($amount > 0) {
            $ordered[$assetModule] = $amount;
        }
    }

    return $ordered;
}

function sr_community_asset_amounts_json_from_map(array $amounts): string
{
    $normalized = [];
    foreach (sr_community_asset_deduction_order() as $assetModule) {
        $amount = min(999999999, max(0, (int) ($amounts[$assetModule] ?? 0)));
        if ($amount > 0) {
            $normalized[$assetModule] = $amount;
        }
    }

    $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($encoded) ? $encoded : '{}';
}

function sr_community_asset_amounts_from_post(string $fieldName, array $assetModules, int $fallbackAmount = 0): array
{
    if (!array_key_exists($fieldName, $_POST)) {
        return sr_community_asset_amounts_from_value([], $assetModules, $fallbackAmount);
    }

    $values = $_POST[$fieldName] ?? [];
    return sr_community_asset_amounts_from_value(is_array($values) ? $values : [], $assetModules, 0);
}

function sr_community_asset_amount_input_values(mixed $amountsValue, array $assetModules, int $fallbackAmount = 0): array
{
    $amounts = sr_community_asset_amounts_from_value($amountsValue, $assetModules, 0);
    if ($amounts === [] && $fallbackAmount > 0) {
        $firstModule = sr_community_asset_module_keys_from_value($assetModules, true)[0] ?? '';
        if ($firstModule !== '') {
            $amounts[$firstModule] = $fallbackAmount;
        }
    }

    return $amounts;
}

function sr_community_asset_grouped_amount_inputs_html(string $id, string $moduleFieldName, string $amountFieldName, array $assetModuleOptions, array $selectedAssetModules, mixed $amountsValue, int $fallbackAmount, string $labelPrefix, string $emptyLabel): string
{
    $amounts = sr_community_asset_amount_input_values($amountsValue, $selectedAssetModules, $fallbackAmount);
    $selectedMap = [];
    foreach ($selectedAssetModules as $selectedAssetModule) {
        $selectedMap[(string) $selectedAssetModule] = true;
    }

    $idBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($id));
    $idBase = is_string($idBase) && $idBase !== '' ? $idBase : 'community_asset_amounts';
    $html = '<div id="' . sr_e($id) . '" class="admin-asset-amount-grid admin-asset-grouped-amount-grid" role="group" data-admin-asset-amount-sync>';
    if ($assetModuleOptions === []) {
        return $html . '<span class="form-help">' . sr_e($emptyLabel) . '</span></div>';
    }

    $index = 0;
    foreach ($assetModuleOptions as $assetModule => $assetOption) {
        $assetModule = (string) $assetModule;
        if ($assetModule === '') {
            continue;
        }

        $label = (string) ($assetOption['label'] ?? sr_community_asset_module_label($assetModule));
        $unitLabel = (string) ($assetOption['unit_label'] ?? sr_community_asset_module_unit_label($assetModule));
        $inputId = $idBase . '_' . (string) $index;
        $isSelected = isset($selectedMap[$assetModule]);
        $html .= '<div class="admin-asset-amount-field admin-asset-grouped-amount-field' . ($isSelected ? ' is-selected' : '') . '" data-admin-asset-amount-field data-admin-asset-module="' . sr_e($assetModule) . '">'
            . '<div class="input-group admin-asset-grouped-input-group">'
            . '<label class="input-group-text form-check form-label" for="' . sr_e($inputId) . '">'
            . '<input id="' . sr_e($inputId) . '" type="checkbox" name="' . sr_e($moduleFieldName) . '[]" value="' . sr_e($assetModule) . '" class="form-checkbox"' . ($isSelected ? ' checked' : '') . '>'
            . sr_admin_choice_label_html($label)
            . '</label>'
            . '<input type="text" inputmode="numeric" pattern="[0-9,]*" name="' . sr_e($amountFieldName) . '[' . sr_e($assetModule) . ']" value="' . sr_e((string) (int) ($amounts[$assetModule] ?? 0)) . '" class="form-input admin-asset-setting-amount" aria-label="' . sr_e($labelPrefix . ' ' . $label) . '" data-admin-asset-amount-input>'
            . ($unitLabel !== '' ? '<span class="input-group-text">' . sr_e($unitLabel) . '</span>' : '')
            . '</div>'
            . '</div>';
        $index++;
    }

    return $html . '</div>';
}

function sr_community_asset_amount_total(array $amounts, int $fallbackAmount = 0): int
{
    return $amounts !== [] ? array_sum(array_map('intval', $amounts)) : max(0, $fallbackAmount);
}

function sr_community_asset_settlement_currency(PDO $pdo, array $source = []): string
{
    $currency = (string) ($source['asset_settlement_currency'] ?? '');
    if ($currency === '') {
        $currency = function_exists('sr_site_default_currency') ? sr_site_default_currency($pdo) : 'KRW';
    }
    $currency = function_exists('sr_normalize_currency_code') ? sr_normalize_currency_code($currency) : strtoupper(trim($currency));

    return $currency !== '' ? $currency : 'KRW';
}

function sr_community_asset_purchase_power_snapshot_json(array $snapshot): string
{
    $encoded = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($encoded) ? $encoded : '{}';
}

function sr_community_allocate_asset_settlement_use(PDO $pdo, array $assetModules, int $accountId, int $settlementAmount, string $settlementCurrency): array
{
    require_once SR_ROOT . '/modules/member/helpers/assets.php';

    $plan = sr_member_asset_settlement_plan(
        $pdo,
        sr_community_asset_modules($pdo),
        static function (PDO $pdo, string $assetModule) use ($accountId): int {
            return sr_community_asset_balance($pdo, $assetModule, $accountId);
        },
        sr_community_asset_module_keys_from_value($assetModules, true),
        $settlementAmount,
        $settlementCurrency
    );

    return !empty($plan['ok']) ? (array) ($plan['allocations'] ?? []) : [];
}

function sr_community_asset_settlement_exchange_suggestion(PDO $pdo, array $assetModules, int $accountId, int $settlementAmount, string $settlementCurrency): array
{
    require_once SR_ROOT . '/modules/member/helpers/assets.php';

    $assets = sr_community_asset_modules($pdo);
    $assetModuleKeys = sr_community_asset_module_keys_from_value($assetModules, true);

    return sr_member_asset_settlement_exchange_suggestion(
        $pdo,
        $assets,
        static function (PDO $pdo, string $assetModule) use ($accountId): int {
            return sr_community_asset_balance($pdo, $assetModule, $accountId);
        },
        $assetModuleKeys,
        $accountId,
        $settlementAmount,
        $settlementCurrency
    );
}

function sr_community_asset_settlement_exchange_confirmation_extra(PDO $pdo, array $assetModules, int $accountId, int $settlementAmount, string $settlementCurrency): array
{
    require_once SR_ROOT . '/modules/member/helpers/assets.php';

    if (sr_community_allocate_asset_settlement_use($pdo, $assetModules, $accountId, $settlementAmount, $settlementCurrency) !== []) {
        return [];
    }

    $suggestion = sr_community_asset_settlement_exchange_suggestion($pdo, $assetModules, $accountId, $settlementAmount, $settlementCurrency);
    if ($suggestion === []) {
        return [];
    }

    return [
        'asset_exchange_suggestion' => $suggestion,
        'asset_exchange_confirmation_required' => true,
        'message' => sr_member_asset_settlement_exchange_message($pdo, sr_community_asset_modules($pdo), $suggestion, sr_community_asset_confirmation_required_message()),
    ];
}

function sr_community_asset_settlement_exchange_hidden_inputs_html(array $suggestion): string
{
    if ($suggestion === []) {
        return '';
    }

    return '<input type="hidden" name="asset_exchange_confirm" value="1">';
}

function sr_community_asset_balance_shortage_message(PDO $pdo, array $assetModules, int $accountId, int $settlementAmount, string $settlementCurrency, string $suffix, string $fallbackMessage): string
{
    require_once SR_ROOT . '/modules/member/helpers/assets.php';

    $assets = sr_community_asset_modules($pdo);
    $assetModuleKeys = sr_community_asset_module_keys_from_value($assetModules, true);
    $balanceFunction = static function (PDO $pdo, string $assetModule) use ($accountId): int {
        return sr_community_asset_balance($pdo, $assetModule, $accountId);
    };
    $plan = sr_member_asset_settlement_plan($pdo, $assets, $balanceFunction, $assetModuleKeys, $settlementAmount, $settlementCurrency);
    $configErrorMessage = sr_member_asset_settlement_config_error_message($plan, $suffix);
    if ($configErrorMessage !== '') {
        return $configErrorMessage;
    }

    $shortage = sr_member_asset_settlement_shortage(
        $pdo,
        $assets,
        $balanceFunction,
        $assetModuleKeys,
        $settlementAmount,
        $settlementCurrency
    );

    return sr_member_asset_balance_shortage_message($shortage, $suffix, $fallbackMessage);
}

function sr_community_asset_settlement_config_error_message(PDO $pdo, array $assetModules, int $accountId, int $settlementAmount, string $settlementCurrency, string $suffix): string
{
    require_once SR_ROOT . '/modules/member/helpers/assets.php';

    $assets = sr_community_asset_modules($pdo);
    $assetModuleKeys = sr_community_asset_module_keys_from_value($assetModules, true);
    $balanceFunction = static function (PDO $pdo, string $assetModule) use ($accountId): int {
        return sr_community_asset_balance($pdo, $assetModule, $accountId);
    };
    $plan = sr_member_asset_settlement_plan($pdo, $assets, $balanceFunction, $assetModuleKeys, $settlementAmount, $settlementCurrency);

    return sr_member_asset_settlement_config_error_message($plan, $suffix);
}

function sr_community_asset_config_balance_shortage_message(PDO $pdo, array $config, int $accountId, string $suffix, string $fallbackMessage): string
{
    $assetModules = sr_community_asset_module_keys_from_value($config['asset_module'] ?? '', true);
    $amounts = is_array($config['amounts'] ?? null) ? $config['amounts'] : [];
    $amount = $amounts !== [] ? sr_community_asset_amount_total($amounts) : (int) ($config['amount'] ?? 0);
    $settlementCurrency = sr_community_asset_settlement_currency($pdo, $config);

    return sr_community_asset_balance_shortage_message($pdo, $assetModules, $accountId, $amount, $settlementCurrency, $suffix, $fallbackMessage);
}

function sr_community_asset_use_balance_available(PDO $pdo, array $config, int $accountId): bool
{
    $assetModules = sr_community_asset_module_keys_from_value($config['asset_module'] ?? '', true);
    $amounts = is_array($config['amounts'] ?? null) ? $config['amounts'] : [];
    $amount = $amounts !== [] ? sr_community_asset_amount_total($amounts) : (int) ($config['amount'] ?? 0);
    $settlementCurrency = sr_community_asset_settlement_currency($pdo, $config);
    if (sr_community_asset_settlement_config_error_message($pdo, $assetModules, $accountId, $amount, $settlementCurrency, '') !== '') {
        return false;
    }

    return $amount <= 0 || sr_community_allocate_asset_settlement_use($pdo, $assetModules, $accountId, $amount, $settlementCurrency) !== [];
}

function sr_community_create_asset_transaction(PDO $pdo, string $assetModule, array $data): int
{
    if (!sr_community_asset_module_is_available($pdo, $assetModule)) {
        throw new RuntimeException('Community asset module is not available.');
    }

    $module = sr_community_asset_modules($pdo)[$assetModule];
    $transactionFunction = (string) $module['transaction_function'];

    return (int) $transactionFunction($pdo, $data);
}

function sr_community_asset_board_setting(PDO $pdo, array $board, array $settings, string $key, mixed $default): string
{
    $boardId = (int) ($board['id'] ?? 0);
    if ($boardId > 0) {
        $value = sr_community_board_setting_value($pdo, $boardId, $key);
        if (
            is_string($value)
            && (
                $value !== ''
                || str_ends_with($key, '_asset_module')
            )
        ) {
            return $value;
        }
    }

    $value = $settings[$key] ?? $default;
    if (is_array($value)) {
        if (str_ends_with($key, '_amounts_json')) {
            return sr_community_asset_amounts_json_from_map(sr_community_asset_amounts_from_value($value));
        }

        return implode(',', array_map('strval', $value));
    }

    return (string) $value;
}

function sr_community_asset_bool_config(PDO $pdo, array $board, array $settings, string $key, bool $default = false): bool
{
    return sr_community_bool_setting(sr_community_asset_board_setting($pdo, $board, $settings, $key, $default ? '1' : '0'));
}

function sr_community_asset_amount_config(PDO $pdo, array $board, array $settings, string $key): int
{
    return min(999999999, max(0, (int) sr_community_asset_board_setting($pdo, $board, $settings, $key, '0')));
}

function sr_community_asset_event_config(PDO $pdo, array $board, array $settings, string $prefix, string $defaultPolicy = 'once'): array
{
    $enabled = sr_community_asset_bool_config($pdo, $board, $settings, $prefix . '_enabled');
    $assetModuleValue = sr_community_asset_board_setting($pdo, $board, $settings, $prefix . '_asset_module', '');
    $assetModule = sr_community_asset_prefix_uses_composite($prefix)
        ? sr_community_asset_module_value_from_keys(sr_community_asset_module_keys_from_value($assetModuleValue, true), true)
        : sr_community_asset_module_key_or_empty((string) $assetModuleValue);
    $amount = sr_community_asset_amount_config($pdo, $board, $settings, $prefix . '_amount');
    $groupPoliciesJson = sr_community_asset_board_setting($pdo, $board, $settings, $prefix . '_group_policies_json', '');
    $policySetId = (int) sr_community_asset_board_setting($pdo, $board, $settings, $prefix . '_policy_set_id', '0');
    $settlementCurrency = sr_community_asset_settlement_currency($pdo, [
        'asset_settlement_currency' => sr_community_asset_board_setting($pdo, $board, $settings, $prefix . '_settlement_currency', ''),
    ]);
    $amounts = sr_community_asset_prefix_uses_composite($prefix)
        ? sr_community_asset_amounts_from_value(
            sr_community_asset_board_setting($pdo, $board, $settings, $prefix . '_amounts_json', ''),
            sr_community_asset_module_keys_from_value($assetModule, true),
            $amount
        )
        : [];
    if ($amounts !== []) {
        $amount = sr_community_asset_amount_total($amounts);
    }
    $policy = sr_community_asset_charge_policy(sr_community_asset_board_setting($pdo, $board, $settings, $prefix . '_charge_policy', $defaultPolicy), $defaultPolicy);

    return [
        'enabled' => $enabled,
        'asset_module' => $assetModule,
        'asset_modules' => sr_community_asset_module_keys_from_value($assetModule, true),
        'amount' => $amount,
        'asset_settlement_currency' => $settlementCurrency,
        'amounts' => $amounts,
        'group_policies_json' => sr_community_asset_group_policy_json_from_value($groupPoliciesJson),
        'policy_set_id' => $policySetId,
        'charge_policy' => $policy,
    ];
}

function sr_community_asset_event_required(array $config): bool
{
    return !empty($config['enabled'])
        && (int) ($config['amount'] ?? 0) > 0
        && sr_community_asset_module_keys_from_value($config['asset_module'] ?? '', true) !== [];
}

function sr_community_save_board_asset_settings(PDO $pdo, int $boardId, array $assetSettings): void
{
    foreach ($assetSettings as $settingKey => $settingValue) {
        $valueType = is_bool($settingValue) ? 'bool' : (is_int($settingValue) ? 'int' : 'string');
        $settingValue = is_bool($settingValue) ? ($settingValue ? '1' : '0') : (string) $settingValue;
        sr_community_set_board_setting($pdo, $boardId, (string) $settingKey, $settingValue, $valueType);
    }
}
