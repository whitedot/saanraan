<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';
require_once SR_ROOT . '/modules/community/helpers/publisher-rewards.php';

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
    return ['write_charge', 'comment_charge', 'paid_read', 'paid_attachment_download'];
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

function sr_community_asset_policy_set_select_html(string $id, string $name, array $policySets, int $selectedId): string
{
    $html = '<select id="' . sr_e($id) . '" name="' . sr_e($name) . '" class="form-select">';
    $html .= '<option value="0"' . ($selectedId === 0 ? ' selected' : '') . '>선택 안 함</option>';
    foreach ($policySets as $policySet) {
        $setId = (int) ($policySet['id'] ?? 0);
        if ($setId < 1) {
            continue;
        }
        $html .= '<option value="' . sr_e((string) $setId) . '"' . ($selectedId === $setId ? ' selected' : '') . '>';
        $html .= sr_e((string) ($policySet['title'] ?? $policySet['set_key'] ?? $setId));
        if ((string) ($policySet['status'] ?? '') !== 'enabled') {
            $html .= ' (' . sr_e(sr_admin_code_label((string) ($policySet['status'] ?? ''), 'content_status')) . ')';
        }
        $html .= '</option>';
    }
    return $html . '</select>';
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

function sr_community_asset_policy_set_selection_json_from_post(string $fieldName): string
{
    return sr_community_asset_policy_set_selection_json_from_ids(sr_community_asset_policy_set_ids_from_value($_POST[$fieldName] ?? []));
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

function sr_community_asset_confirmation_request_token(string $eventKey, string $subjectType, int $accountId, int $subjectId, string $fingerprint): string
{
    if ($eventKey === '' || $subjectType === '' || $accountId < 1 || $subjectId < 1 || $fingerprint === '') {
        return '';
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
    if ($token === '' || preg_match('/\A[a-f0-9]{32}\z/', $token) !== 1) {
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

function sr_community_asset_confirmation_fingerprint(string $eventKey, string $subjectType, string $chargePolicy, string $assetModuleValue, int $amount, array $amounts = [], string $policySnapshotJson = ''): string
{
    ksort($amounts, SORT_STRING);
    $amountParts = [];
    foreach ($amounts as $assetModule => $assetAmount) {
        $amountParts[] = (string) $assetModule . ':' . (string) max(0, (int) $assetAmount);
    }

    return hash('sha256', implode('|', [$eventKey, $subjectType, $chargePolicy, $assetModuleValue, (string) max(0, $amount), implode(',', $amountParts), $policySnapshotJson]));
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

    foreach (sr_community_asset_prefix_setting_keys($prefix) as $settingKey) {
        $stmt = $pdo->prepare(
            'SELECT source
             FROM sr_community_board_setting_sources
             WHERE board_id = :board_id
               AND setting_key = :setting_key
             LIMIT 1'
        );
        $stmt->execute([
            'board_id' => $boardId,
            'setting_key' => $settingKey,
        ]);
        $source = $stmt->fetchColumn();
        if (is_string($source) && $source !== '') {
            return sr_community_normalize_board_setting_source($source);
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

    $stmt = $pdo->prepare(
        'SELECT source
         FROM sr_community_board_setting_sources
         WHERE board_id = :board_id
           AND setting_key = :setting_key
         LIMIT 1'
    );
    $stmt->execute([
        'board_id' => $boardId,
        'setting_key' => $settingKey,
    ]);
    $source = $stmt->fetchColumn();
    if (is_string($source) && $source !== '') {
        return sr_community_normalize_board_setting_source($source);
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
    foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
        $moduleValue = (string) ($settings[$assetPrefix . '_asset_module'] ?? '');
        $auditSettings[$assetPrefix . '_enabled'] = sr_community_asset_bool_value_for_audit($settings[$assetPrefix . '_enabled'] ?? false);
        $auditSettings[$assetPrefix . '_asset_module'] = sr_community_asset_prefix_uses_composite($assetPrefix)
            ? sr_community_asset_module_value_from_keys(sr_community_asset_module_keys_from_value($moduleValue, true), true)
            : sr_community_asset_module_key_or_empty($moduleValue);
        $auditSettings[$assetPrefix . '_amount'] = max(0, (int) ($settings[$assetPrefix . '_amount'] ?? 0));
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

function sr_community_board_group_asset_settings_from_storage_for_audit(PDO $pdo, int $groupId): array
{
    if ($groupId < 1) {
        return [];
    }

    $settings = [];
    foreach (sr_community_board_group_asset_setting_keys() as $assetSettingKey) {
        $settings[$assetSettingKey] = sr_community_board_group_setting_value($pdo, $groupId, (string) $assetSettingKey);
    }

    return sr_community_asset_settings_for_audit($settings);
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

function sr_community_asset_combined_balance(PDO $pdo, array $assetModules, int $accountId): int
{
    $balance = 0;
    foreach (sr_community_asset_module_keys_from_value($assetModules, true) as $assetModule) {
        $balance += sr_community_asset_balance($pdo, $assetModule, $accountId);
    }

    return $balance;
}

function sr_community_allocate_asset_use(PDO $pdo, array $assetModules, int $accountId, int $amount): array
{
    $remaining = max(0, $amount);
    $allocations = [];
    foreach (sr_community_asset_module_keys_from_value($assetModules, true) as $assetModule) {
        if ($remaining <= 0) {
            break;
        }

        $balance = sr_community_asset_balance($pdo, $assetModule, $accountId);
        if ($balance <= 0) {
            continue;
        }

        $useAmount = min($balance, $remaining);
        if ($useAmount > 0) {
            $allocations[] = [
                'asset_module' => $assetModule,
                'amount' => $useAmount,
            ];
            $remaining -= $useAmount;
        }
    }

    return $remaining === 0 ? $allocations : [];
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

function sr_community_asset_amount_inputs_html(string $fieldName, array $assetModuleOptions, array $selectedAssetModules, mixed $amountsValue, int $fallbackAmount, string $labelPrefix, bool $syncSelected = false): string
{
    $amounts = sr_community_asset_amount_input_values($amountsValue, $selectedAssetModules, $fallbackAmount);
    $html = '<div class="admin-asset-amount-grid"' . ($syncSelected ? ' data-admin-asset-amount-sync' : '') . '>';
    foreach ($assetModuleOptions as $assetModule => $assetOption) {
        $assetModule = (string) $assetModule;
        $label = (string) ($assetOption['label'] ?? sr_community_asset_module_label($assetModule));
        $unitLabel = (string) ($assetOption['unit_label'] ?? sr_community_asset_module_unit_label($assetModule));
        $isSelected = in_array($assetModule, $selectedAssetModules, true);
        $html .= '<div class="admin-asset-amount-field' . ($isSelected ? ' is-selected' : '') . '" data-admin-asset-amount-field data-admin-asset-module="' . sr_e($assetModule) . '">'
            . '<div class="input-group admin-asset-grouped-input-group">'
            . '<span class="input-group-text">' . sr_e($label) . '</span>'
            . '<input type="text" inputmode="numeric" pattern="[0-9,]*" name="' . sr_e($fieldName) . '[' . sr_e($assetModule) . ']" value="' . sr_e((string) (int) ($amounts[$assetModule] ?? 0)) . '" class="form-input admin-asset-setting-amount" aria-label="' . sr_e($labelPrefix . ' ' . $label) . '" data-admin-asset-amount-input>'
            . ($unitLabel !== '' ? '<span class="input-group-text">' . sr_e($unitLabel) . '</span>' : '')
            . '</div>'
            . '</div>';
    }
    $html .= '</div>';

    return $html;
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

function sr_community_allocate_asset_use_by_amounts(PDO $pdo, array $amounts, int $accountId): array
{
    $allocations = [];
    foreach (sr_community_asset_deduction_order() as $assetModule) {
        $amount = (int) ($amounts[$assetModule] ?? 0);
        if ($amount <= 0) {
            continue;
        }
        if (sr_community_asset_balance($pdo, $assetModule, $accountId) < $amount) {
            return [];
        }
        $allocations[] = [
            'asset_module' => $assetModule,
            'amount' => $amount,
        ];
    }

    return $allocations;
}

function sr_community_asset_settlement_currency(PDO $pdo, array $source = []): string
{
    $currency = (string) ($source['asset_settlement_currency'] ?? '');
    if ($currency === '') {
        $currency = function_exists('sr_site_default_currency') ? sr_site_default_currency($pdo) : 'KRW';
    }
    $currency = function_exists('sr_normalize_currency_code') ? sr_normalize_currency_code($currency) : strtoupper(trim($currency));

    return function_exists('sr_currency_is_known') && sr_currency_is_known($currency) ? $currency : 'KRW';
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

function sr_community_asset_use_balance_available(PDO $pdo, array $config, int $accountId): bool
{
    $assetModules = sr_community_asset_module_keys_from_value($config['asset_module'] ?? '', true);
    $amounts = is_array($config['amounts'] ?? null) ? $config['amounts'] : [];
    $amount = $amounts !== [] ? sr_community_asset_amount_total($amounts) : (int) ($config['amount'] ?? 0);
    $settlementCurrency = sr_community_asset_settlement_currency($pdo, $config);

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

function sr_community_paid_read_session_key(int $accountId, int $postId): string
{
    return (string) $accountId . ':' . (string) $postId;
}

function sr_community_has_paid_read_session(int $accountId, int $postId): bool
{
    $key = sr_community_paid_read_session_key($accountId, $postId);
    $sessions = is_array($_SESSION['sr_community_paid_read_posts'] ?? null) ? $_SESSION['sr_community_paid_read_posts'] : [];
    $createdAt = isset($sessions[$key]) ? (int) $sessions[$key] : 0;

    if ($createdAt > 0 && $createdAt >= time() - 300) {
        return true;
    }

    unset($_SESSION['sr_community_paid_read_posts'][$key]);
    return false;
}

function sr_community_mark_paid_read_session(int $accountId, int $postId): void
{
    if ($accountId < 1 || $postId < 1) {
        return;
    }

    if (!isset($_SESSION['sr_community_paid_read_posts']) || !is_array($_SESSION['sr_community_paid_read_posts'])) {
        $_SESSION['sr_community_paid_read_posts'] = [];
    }

    $_SESSION['sr_community_paid_read_posts'][sr_community_paid_read_session_key($accountId, $postId)] = time();
}

function sr_community_attachment_paid_read_bridge_key(int $accountId, int $attachmentId): string
{
    return (string) $accountId . ':' . (string) $attachmentId;
}

function sr_community_mark_attachment_paid_read_bridge(int $accountId, int $attachmentId, string $fingerprint, int $createdAt = 0): void
{
    if ($accountId < 1 || $attachmentId < 1 || $fingerprint === '') {
        return;
    }

    if (!isset($_SESSION['sr_community_attachment_paid_read_bridges']) || !is_array($_SESSION['sr_community_attachment_paid_read_bridges'])) {
        $_SESSION['sr_community_attachment_paid_read_bridges'] = [];
    }

    $_SESSION['sr_community_attachment_paid_read_bridges'][sr_community_attachment_paid_read_bridge_key($accountId, $attachmentId)] = [
        'created_at' => $createdAt > 0 ? $createdAt : time(),
        'fingerprint' => $fingerprint,
    ];
}

function sr_community_consume_attachment_paid_read_bridge_created_at(int $accountId, int $attachmentId, string $fingerprint): int
{
    $key = sr_community_attachment_paid_read_bridge_key($accountId, $attachmentId);
    $sessions = is_array($_SESSION['sr_community_attachment_paid_read_bridges'] ?? null) ? $_SESSION['sr_community_attachment_paid_read_bridges'] : [];
    $session = isset($sessions[$key]) && is_array($sessions[$key]) ? $sessions[$key] : [];
    $createdAt = (int) ($session['created_at'] ?? 0);
    $sessionFingerprint = (string) ($session['fingerprint'] ?? '');
    unset($_SESSION['sr_community_attachment_paid_read_bridges'][$key]);

    if ($createdAt > 0
        && $createdAt >= time() - 300
        && $fingerprint !== ''
        && hash_equals($sessionFingerprint, $fingerprint)
    ) {
        return $createdAt;
    }

    return 0;
}

function sr_community_consume_attachment_paid_read_bridge(int $accountId, int $attachmentId, string $fingerprint): bool
{
    return sr_community_consume_attachment_paid_read_bridge_created_at($accountId, $attachmentId, $fingerprint) > 0;
}

function sr_community_asset_dedupe_key(string $assetModule, int $accountId, string $eventKey, int $subjectId): string
{
    return 'community.' . $eventKey . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId;
}

function sr_community_asset_log(PDO $pdo, string $dedupeKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_community_asset_logs
         WHERE dedupe_key = :dedupe_key
         LIMIT 1'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_has_asset_event(PDO $pdo, string $assetModule, int $accountId, string $eventKey, int $subjectId): bool
{
    $log = sr_community_asset_log($pdo, sr_community_asset_dedupe_key($assetModule, $accountId, $eventKey, $subjectId));

    return is_array($log)
        && (string) ($log['log_status'] ?? sr_community_asset_log_status_completed()) === sr_community_asset_log_status_completed()
        && ((int) ($log['transaction_id'] ?? 0) > 0 || (int) ($log['amount'] ?? -1) === 0);
}

function sr_community_has_asset_event_for_modules(PDO $pdo, array $assetModules, int $accountId, string $eventKey, int $subjectId): bool
{
    foreach (sr_community_asset_module_keys_from_value($assetModules, true) as $assetModule) {
        if (sr_community_has_asset_event($pdo, $assetModule, $accountId, $eventKey, $subjectId)) {
            return true;
        }
    }

    return false;
}

function sr_community_has_asset_event_history(PDO $pdo, array $assetModules, int $accountId, string $eventKey, int $subjectId, string $policy): bool
{
    $policy = sr_community_once_history_policy($policy);
    if ($policy === 'current_asset_once') {
        return sr_community_has_asset_event_for_modules($pdo, $assetModules, $accountId, $eventKey, $subjectId);
    }

    $params = [
        'account_id' => $accountId,
        'event_key' => $eventKey,
        'subject_id' => $subjectId,
    ];
    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_community_asset_logs
         WHERE account_id = :account_id
           AND event_key = :event_key
           AND subject_id = :subject_id
           AND direction = \'use\'
           AND log_status = \'completed\'
           AND (transaction_id > 0 OR amount = 0)'
        . ' LIMIT 1'
    );
    $stmt->execute($params);

    return is_array($stmt->fetch());
}

function sr_community_has_coupon_access_history(PDO $pdo, int $accountId, string $dedupeKey): bool
{
    if ($accountId <= 0 || $dedupeKey === '' || !sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return false;
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_has_redemption')) {
        return false;
    }

    return sr_coupon_has_redemption($pdo, $accountId, $dedupeKey);
}

function sr_community_access_entitlements_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query('SELECT 1 FROM sr_community_access_entitlements LIMIT 1');
        $exists = $stmt !== false;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_community_asset_log_settlement_metadata_columns_exist(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    $prefix = $pdo instanceof SrPrefixedPDO ? $pdo->srTablePrefix() : 'sr_';
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS column_count
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME IN (\'settlement_kind\', \'snapshot_schema_version\', \'rounding_policy_version\')'
        );
        $stmt->execute(['table_name' => $prefix . 'community_asset_logs']);
        $row = $stmt->fetch();
        $exists = is_array($row) && (int) ($row['column_count'] ?? 0) === 3;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_community_grant_access_entitlement(PDO $pdo, int $accountId, string $subjectType, int $subjectId, string $eventKey, string $sourceKind, string $sourceAssetModule = '', string $sourceChargePolicy = 'once', string $sourceReference = ''): void
{
    if ($accountId <= 0 || $subjectType === '' || $subjectId <= 0 || $eventKey === '' || !sr_community_access_entitlements_table_exists($pdo)) {
        return;
    }

    $insertVerb = 'INSERT IGNORE';
    try {
        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $insertVerb = 'INSERT OR IGNORE';
        }
    } catch (Throwable $exception) {
        $insertVerb = 'INSERT IGNORE';
    }
    $stmt = $pdo->prepare(
        $insertVerb . ' INTO sr_community_access_entitlements
            (account_id, subject_type, subject_id, event_key, source_kind, source_asset_module, source_charge_policy, source_reference, granted_at, created_at)
         VALUES
            (:account_id, :subject_type, :subject_id, :event_key, :source_kind, :source_asset_module, :source_charge_policy, :source_reference, :granted_at, :created_at)'
    );
    $now = sr_now();
    $stmt->execute([
        'account_id' => $accountId,
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'event_key' => $eventKey,
        'source_kind' => $sourceKind,
        'source_asset_module' => $sourceAssetModule,
        'source_charge_policy' => $sourceChargePolicy,
        'source_reference' => $sourceReference,
        'granted_at' => $now,
        'created_at' => $now,
    ]);
}

function sr_community_revoke_coupon_access_entitlements(PDO $pdo, int $accountId, string $sourceReference): int
{
    if ($accountId <= 0 || $sourceReference === '' || !sr_community_access_entitlements_table_exists($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "DELETE FROM sr_community_access_entitlements
         WHERE account_id = :account_id
           AND source_kind = 'coupon'
           AND source_reference = :source_reference"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'source_reference' => $sourceReference,
    ]);

    return $stmt->rowCount();
}

function sr_community_anonymize_access_entitlements(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0 || !sr_community_access_entitlements_table_exists($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_access_entitlements
         SET account_id = NULL,
             source_reference = \'\',
             anonymized_at = :anonymized_at
         WHERE account_id = :account_id'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'anonymized_at' => sr_now(),
    ]);

    return $stmt->rowCount();
}

function sr_community_has_access_entitlement(PDO $pdo, array $assetModules, int $accountId, string $eventKey, string $subjectType, int $subjectId, string $couponDedupeKey, string $policy): bool
{
    $policy = sr_community_once_history_policy($policy);
    if (!sr_community_access_entitlements_table_exists($pdo)) {
        if (sr_community_has_asset_event_history($pdo, $assetModules, $accountId, $eventKey, $subjectId, $policy)) {
            return true;
        }

        return $policy === 'all_access'
            && $couponDedupeKey !== ''
            && sr_community_has_coupon_access_history($pdo, $accountId, $couponDedupeKey);
    }

    $conditions = [
        'account_id = :account_id',
        'subject_type = :subject_type',
        'subject_id = :subject_id',
        'event_key = :event_key',
        'anonymized_at IS NULL',
    ];
    $params = [
        'account_id' => $accountId,
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'event_key' => $eventKey,
    ];

    if ($policy === 'asset_any') {
        $conditions[] = 'source_kind = \'asset\'';
    } elseif ($policy === 'current_asset_once') {
        $moduleKeys = sr_community_asset_module_keys_from_value($assetModules, true);
        if ($moduleKeys === []) {
            return false;
        }
        $placeholders = [];
        foreach ($moduleKeys as $index => $assetModule) {
            $key = 'asset_module_' . (string) $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $assetModule;
        }
        $conditions[] = 'source_kind = \'asset\'';
        $conditions[] = 'source_charge_policy = \'once\'';
        $conditions[] = 'source_asset_module IN (' . implode(', ', $placeholders) . ')';
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_community_access_entitlements
         WHERE ' . implode(' AND ', $conditions) . '
         LIMIT 1'
    );
    $stmt->execute($params);

    return is_array($stmt->fetch());
}

function sr_community_once_access_already_granted(PDO $pdo, array $config, int $accountId, string $eventKey, int $subjectId, string $couponDedupeKey = ''): bool
{
    $settings = sr_community_settings($pdo);
    $policy = sr_community_once_history_policy((string) ($settings['once_history_policy'] ?? 'all_access'));
    $assetModules = sr_community_asset_module_keys_from_value($config['asset_module'] ?? '', true);
    $subjectType = $eventKey === 'attachment_download' ? 'community.attachment' : 'community.post';

    return sr_community_has_access_entitlement($pdo, $assetModules, $accountId, $eventKey, $subjectType, $subjectId, $couponDedupeKey, $policy);
}

// This helper owns its transaction boundary so fallback coupon targets cannot
// commit partial redemption work from a previous target attempt.
function sr_community_try_paid_read_coupon_access(PDO $pdo, int $accountId, array $post, array $paidReadConfig, string $couponDedupeKey): array
{
    $postId = (int) ($post['id'] ?? 0);
    $boardId = (int) ($post['board_id'] ?? 0);
    if ($accountId <= 0 || $postId <= 0 || $couponDedupeKey === '') {
        return ['allowed' => false, 'processed' => false];
    }

    if ((string) ($paidReadConfig['charge_policy'] ?? 'once') === 'once'
        && sr_community_once_access_already_granted($pdo, $paidReadConfig, $accountId, 'post_read', $postId, $couponDedupeKey)
    ) {
        return ['allowed' => true, 'processed' => false, 'already_redeemed' => true];
    }

    if (!sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return ['allowed' => false, 'processed' => false];
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_redeem_for_target')) {
        return ['allowed' => false, 'processed' => false];
    }

    $assetModules = sr_community_asset_module_keys_from_value($paidReadConfig['asset_module'] ?? '', true);
    $assetModuleValue = sr_community_asset_module_value_from_keys($assetModules, true);
    $amounts = is_array($paidReadConfig['amounts'] ?? null) ? $paidReadConfig['amounts'] : [];
    $policyAmounts = sr_community_asset_amounts_with_group_policy($pdo, $accountId, $assetModules, $amounts, (int) ($paidReadConfig['amount'] ?? 0), $paidReadConfig['group_policies_json'] ?? '', (int) ($paidReadConfig['policy_set_id'] ?? 0), 'use');
    $policySnapshotJson = sr_community_asset_group_policy_snapshot_json($policyAmounts['snapshots']);
    $confirmationFingerprint = sr_community_asset_confirmation_fingerprint(
        'post_read',
        'community.post',
        (string) ($paidReadConfig['charge_policy'] ?? 'once'),
        $assetModuleValue,
        (int) $policyAmounts['amount'],
        is_array($policyAmounts['amounts'] ?? null) ? $policyAmounts['amounts'] : [],
        $policySnapshotJson
    );

    $couponContext = [
        'dedupe_key' => $couponDedupeKey,
        'reference_module' => 'community',
        'reference_type' => 'community.post',
        'reference_id' => (string) $postId,
    ];

    foreach ([['community_post', (string) $postId], ['community_board', (string) $boardId]] as $target) {
        if ((string) $target[1] === '0') {
            continue;
        }

        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $couponResult = sr_coupon_redeem_for_target($pdo, $accountId, (string) $target[0], (string) $target[1], $couponContext);
            if (!empty($couponResult['allowed'])) {
                sr_community_grant_access_entitlement($pdo, $accountId, 'community.post', $postId, 'post_read', 'coupon', '', (string) ($paidReadConfig['charge_policy'] ?? 'once'), $couponDedupeKey);
                if ($startedTransaction) {
                    $pdo->commit();
                }

                $couponResult['confirmation_fingerprint'] = $confirmationFingerprint;
                return $couponResult;
            }

            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'community_coupon_entitlement_failed');
            }
        }
    }

    return ['allowed' => false, 'processed' => false];
}

function sr_community_insert_asset_log_placeholder(PDO $pdo, array $row): bool
{
    $settlementAmount = max(0, (int) ($row['settlement_amount'] ?? 0));
    $purchasePowerSnapshotJson = (string) ($row['purchase_power_snapshot_json'] ?? '');
    $settlementKind = sr_community_asset_settlement_kind(
        (string) $row['direction'],
        (int) $row['amount'],
        $settlementAmount,
        $purchasePowerSnapshotJson
    );
    $insertVerb = 'INSERT IGNORE';
    try {
        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $insertVerb = 'INSERT OR IGNORE';
        }
    } catch (Throwable $exception) {
        $insertVerb = 'INSERT IGNORE';
    }
    $stmt = $pdo->prepare(
        $insertVerb . ' INTO sr_community_asset_logs
            (account_id, asset_module, transaction_id, reference_type, reference_id, subject_type, subject_id, event_key, direction, charge_policy, amount, settlement_amount, settlement_currency, purchase_power_snapshot_json, settlement_kind, snapshot_schema_version, rounding_policy_version, log_status, group_policy_snapshot_json, dedupe_key, created_at)
         VALUES
            (:account_id, :asset_module, 0, :reference_type, :reference_id, :subject_type, :subject_id, :event_key, :direction, :charge_policy, :amount, :settlement_amount, :settlement_currency, :purchase_power_snapshot_json, :settlement_kind, :snapshot_schema_version, :rounding_policy_version, :log_status, :group_policy_snapshot_json, :dedupe_key, :created_at)'
    );
    $stmt->execute([
        'account_id' => (int) $row['account_id'],
        'asset_module' => (string) $row['asset_module'],
        'reference_type' => (string) $row['reference_type'],
        'reference_id' => (string) $row['reference_id'],
        'subject_type' => (string) $row['subject_type'],
        'subject_id' => (int) $row['subject_id'],
        'event_key' => (string) $row['event_key'],
        'direction' => (string) $row['direction'],
        'charge_policy' => (string) $row['charge_policy'],
        'amount' => (int) $row['amount'],
        'settlement_amount' => $settlementAmount,
        'settlement_currency' => sr_community_asset_settlement_currency($pdo, ['asset_settlement_currency' => (string) ($row['settlement_currency'] ?? 'KRW')]),
        'purchase_power_snapshot_json' => $purchasePowerSnapshotJson,
        'settlement_kind' => $settlementKind,
        'snapshot_schema_version' => sr_community_asset_snapshot_schema_version(),
        'rounding_policy_version' => sr_community_asset_rounding_policy_version(),
        'log_status' => sr_community_asset_log_status_pending(),
        'group_policy_snapshot_json' => (string) ($row['group_policy_snapshot_json'] ?? ''),
        'dedupe_key' => (string) $row['dedupe_key'],
        'created_at' => sr_now(),
    ]);

    return $stmt->rowCount() > 0;
}

function sr_community_update_asset_log_transaction(PDO $pdo, string $dedupeKey, int $transactionId): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_community_asset_logs
         SET transaction_id = :transaction_id,
             log_status = :log_status
         WHERE dedupe_key = :dedupe_key'
    );
    $stmt->execute([
        'transaction_id' => $transactionId,
        'log_status' => sr_community_asset_log_status_completed(),
        'dedupe_key' => $dedupeKey,
    ]);
}

function sr_community_complete_zero_asset_log(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_community_asset_logs
         SET log_status = :log_status
         WHERE dedupe_key = :dedupe_key
           AND transaction_id = 0
           AND amount = 0'
    );
    $stmt->execute([
        'log_status' => sr_community_asset_log_status_completed(),
        'dedupe_key' => $dedupeKey,
    ]);
}

function sr_community_delete_asset_log_placeholder(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM sr_community_asset_logs
         WHERE dedupe_key = :dedupe_key
           AND log_status = :log_status'
    );
    $stmt->execute([
        'dedupe_key' => $dedupeKey,
        'log_status' => sr_community_asset_log_status_pending(),
    ]);
}

function sr_community_run_asset_event(PDO $pdo, array $config, int $accountId, string $eventKey, string $subjectType, int $subjectId, string $direction, string $reason, bool $process = true, string $requestToken = ''): array
{
    return sr_community_asset_retry_operation($pdo, static function () use ($pdo, $config, $accountId, $eventKey, $subjectType, $subjectId, $direction, $reason, $process, $requestToken): array {
        return sr_community_run_asset_event_once($pdo, $config, $accountId, $eventKey, $subjectType, $subjectId, $direction, $reason, $process, $requestToken);
    });
}

function sr_community_run_asset_event_once(PDO $pdo, array $config, int $accountId, string $eventKey, string $subjectType, int $subjectId, string $direction, string $reason, bool $process = true, string $requestToken = ''): array
{
    $assetModules = sr_community_asset_module_keys_from_value($config['asset_module'] ?? '', true);
    $assetModuleValue = sr_community_asset_module_value_from_keys($assetModules, true);
    $amounts = is_array($config['amounts'] ?? null) ? $config['amounts'] : [];
    $amount = $amounts !== [] ? sr_community_asset_amount_total($amounts) : (int) ($config['amount'] ?? 0);
    $chargePolicy = (string) ($config['charge_policy'] ?? 'once');

    if ($accountId <= 0 || $subjectId <= 0 || $amount <= 0 || $assetModules === []) {
        return ['allowed' => true, 'processed' => false, 'message' => ''];
    }

    if (!sr_community_asset_modules_available($pdo, $assetModules)) {
        return [
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_modules_unavailable',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'message' => sr_t('community::action.error.asset_modules_unavailable'),
        ];
    }

    $once = in_array($chargePolicy, ['once'], true) || in_array($direction, ['grant', 'refund'], true);
    $alreadyProcessed = false;
    if ($once && $direction === 'use') {
        $settings = sr_community_settings($pdo);
        $onceHistoryPolicy = sr_community_once_history_policy((string) ($settings['once_history_policy'] ?? 'all_access'));
        $alreadyProcessed = sr_community_has_access_entitlement($pdo, $assetModules, $accountId, $eventKey, $subjectType, $subjectId, '', $onceHistoryPolicy);
    } elseif ($once) {
        $alreadyProcessed = sr_community_has_asset_event_for_modules($pdo, $assetModules, $accountId, $eventKey, $subjectId);
    }
    if ($alreadyProcessed) {
        return [
            'allowed' => true,
            'processed' => false,
            'already_processed' => true,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'message' => '',
        ];
    }

    $policyAmounts = sr_community_asset_amounts_with_group_policy($pdo, $accountId, $assetModules, $amounts, (int) ($config['amount'] ?? 0), $config['group_policies_json'] ?? '', (int) ($config['policy_set_id'] ?? 0), $direction === 'use' ? 'use' : 'grant');
    $amounts = $amounts !== [] ? $policyAmounts['amounts'] : [];
    $amount = (int) $policyAmounts['amount'];
    $policySnapshotJson = sr_community_asset_group_policy_snapshot_json($policyAmounts['snapshots']);
    $settlementCurrency = sr_community_asset_settlement_currency($pdo, $config);
    $confirmationFingerprint = sr_community_asset_confirmation_fingerprint($eventKey, $subjectType, $chargePolicy, $assetModuleValue, $amount, $amounts, $policySnapshotJson);
    if ($direction === 'use' && sr_community_asset_policy_requires_confirmation($chargePolicy) && !$process) {
        if (sr_community_consume_asset_confirmation_session($eventKey, $subjectType, $accountId, $subjectId, $confirmationFingerprint)) {
            return [
                'allowed' => true,
                'processed' => false,
                'confirmed_access' => true,
                'asset_module' => $assetModuleValue,
                'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
                'amount' => $amount,
                'confirmation_fingerprint' => $confirmationFingerprint,
                'message' => '',
            ];
        }

        return [
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_confirmation_required',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'confirmation_fingerprint' => $confirmationFingerprint,
            'confirmation_request_token' => sr_community_asset_confirmation_request_token($eventKey, $subjectType, $accountId, $subjectId, $confirmationFingerprint),
            'message' => sr_community_asset_confirmation_required_message(),
        ];
    } elseif ($direction === 'use' && sr_community_asset_policy_requires_confirmation($chargePolicy) && $process && !sr_community_asset_confirmation_request_token_valid($eventKey, $subjectType, $accountId, $subjectId, $confirmationFingerprint, $requestToken)) {
        return [
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_confirmation_required',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'confirmation_fingerprint' => $confirmationFingerprint,
            'confirmation_request_token' => sr_community_asset_confirmation_request_token($eventKey, $subjectType, $accountId, $subjectId, $confirmationFingerprint),
            'message' => sr_community_asset_confirmation_required_message(),
        ];
    }

    if ($amount <= 0) {
        $assetModule = (string) ($assetModules[0] ?? $assetModuleValue);
        $stableRequestToken = preg_match('/\A[a-f0-9]{32}\z/', $requestToken) === 1 ? $requestToken : bin2hex(random_bytes(16));
        $dedupeKey = $once
            ? sr_community_asset_dedupe_key($assetModule, $accountId, $eventKey, $subjectId)
            : 'community.' . $eventKey . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId . ':' . $stableRequestToken;
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }
        try {
            sr_community_insert_asset_log_placeholder($pdo, [
                'account_id' => $accountId,
                'asset_module' => $assetModule,
                'reference_type' => $subjectType,
                'reference_id' => (string) $subjectId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'event_key' => $eventKey,
                'direction' => $direction,
                'charge_policy' => $chargePolicy,
                'amount' => 0,
                'group_policy_snapshot_json' => $policySnapshotJson,
                'dedupe_key' => $dedupeKey,
            ]);
            sr_community_complete_zero_asset_log($pdo, $dedupeKey);
            if ($direction === 'use' && in_array($eventKey, ['post_read', 'attachment_download'], true)) {
                sr_community_grant_access_entitlement($pdo, $accountId, $subjectType, $subjectId, $eventKey, 'asset_group_policy', $assetModule, $chargePolicy, $dedupeKey);
            }
            if ($startedTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($startedTransaction && sr_community_asset_is_retryable_transaction_exception($exception)) {
                throw $exception;
            }
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'community_asset_group_event_failed');
            }

            return [
                'allowed' => false,
                'processed' => false,
                'error_key' => 'asset_processing_failed',
                'asset_module' => $assetModuleValue,
                'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
                'amount' => 0,
                'message' => sr_t('community::action.error.asset_processing_failed'),
            ];
        }

        return [
            'allowed' => true,
            'processed' => true,
            'group_policy_applied' => true,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => 0,
            'direction' => $direction,
            'message' => '',
        ];
    }

    $allocations = $direction === 'use'
        ? sr_community_allocate_asset_settlement_use($pdo, $assetModules, $accountId, $amount, $settlementCurrency)
        : [['asset_module' => $assetModules[0], 'amount' => $amount]];
    if ($direction === 'use' && $allocations === []) {
        return [
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_balance_low',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'confirmation_fingerprint' => $confirmationFingerprint,
            'message' => sr_t('community::action.error.asset_balance_low'),
        ];
    }

    $processed = false;
    $processedLogs = [];
    $dedupeKey = '';
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        foreach ($allocations as $allocation) {
            $assetModule = (string) $allocation['asset_module'];
            $allocatedAmount = (int) ($allocation['asset_amount'] ?? $allocation['amount']);
            $allocatedSettlementAmount = $direction === 'use' ? (int) ($allocation['settlement_amount'] ?? 0) : 0;
            $allocationSettlementCurrency = $direction === 'use' ? (string) ($allocation['settlement_currency'] ?? $settlementCurrency) : $settlementCurrency;
            $purchasePowerSnapshotJson = $direction === 'use' ? sr_community_asset_purchase_power_snapshot_json(is_array($allocation['purchase_power_snapshot'] ?? null) ? $allocation['purchase_power_snapshot'] : []) : '';
            $module = sr_community_asset_modules($pdo)[$assetModule];
            $stableRequestToken = preg_match('/\A[a-f0-9]{32}\z/', $requestToken) === 1 ? $requestToken : bin2hex(random_bytes(16));
            $dedupeKey = $once
                ? sr_community_asset_dedupe_key($assetModule, $accountId, $eventKey, $subjectId)
                : 'community.' . $eventKey . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId . ':' . $stableRequestToken;
            $inserted = sr_community_insert_asset_log_placeholder($pdo, [
                'account_id' => $accountId,
                'asset_module' => $assetModule,
                'reference_type' => $subjectType,
                'reference_id' => (string) $subjectId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'event_key' => $eventKey,
                'direction' => $direction,
                'charge_policy' => $chargePolicy,
                'amount' => $allocatedAmount,
                'settlement_amount' => $allocatedSettlementAmount,
                'settlement_currency' => $allocationSettlementCurrency,
                'purchase_power_snapshot_json' => $purchasePowerSnapshotJson,
                'group_policy_snapshot_json' => sr_community_asset_group_policy_snapshot_json(isset($policyAmounts['snapshots'][$assetModule]) ? [$policyAmounts['snapshots'][$assetModule]] : []),
                'dedupe_key' => $dedupeKey,
            ]);
            if (!$inserted) {
                if ($once) {
                    throw new RuntimeException('Incomplete or duplicate community asset event.');
                }
                continue;
            }

            $signedAmount = $direction === 'use' ? -$allocatedAmount : $allocatedAmount;
            $transactionType = $direction === 'use'
                ? (string) ($module['use_type'] ?? 'use')
                : ($direction === 'refund' ? (string) ($module['refund_type'] ?? 'refund') : (string) ($module['credit_type'] ?? 'grant'));
            $transactionId = sr_community_create_asset_transaction($pdo, $assetModule, [
                'account_id' => $accountId,
                'amount' => $signedAmount,
                'transaction_type' => $transactionType,
                'reason' => $reason,
                'reference_type' => $subjectType,
                'reference_id' => (string) $subjectId,
                'created_by_account_id' => null,
            ]);
            sr_community_update_asset_log_transaction($pdo, $dedupeKey, $transactionId);
            if ($direction === 'use' && in_array($eventKey, ['post_read', 'attachment_download'], true)) {
                sr_community_grant_access_entitlement($pdo, $accountId, $subjectType, $subjectId, $eventKey, 'asset', $assetModule, $chargePolicy, $assetModule . ':' . (string) $transactionId);
            }
            $processedLogs[] = [
                'dedupe_key' => $dedupeKey,
                'asset_module' => $assetModule,
                'transaction_id' => $transactionId,
                'amount' => $allocatedAmount,
                'settlement_amount' => $allocatedSettlementAmount,
                'settlement_currency' => $allocationSettlementCurrency,
            ];
            $processed = true;
        }

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        } elseif ($dedupeKey !== '') {
            sr_community_delete_asset_log_placeholder($pdo, $dedupeKey);
        }
        if ($startedTransaction && sr_community_asset_is_retryable_transaction_exception($exception)) {
            throw $exception;
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'community_asset_event_failed');
        }

        return [
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_processing_failed',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'message' => sr_t('community::action.error.asset_processing_failed'),
        ];
    }

    return [
        'allowed' => true,
        'processed' => $processed,
        'asset_module' => $assetModuleValue,
        'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
        'amount' => $amount,
        'direction' => $direction,
        'processed_logs' => $processedLogs,
        'confirmation_fingerprint' => $confirmationFingerprint,
        'message' => '',
    ];
}

function sr_community_asset_log_by_dedupe_key(PDO $pdo, string $dedupeKey): ?array
{
    return sr_community_asset_log($pdo, $dedupeKey);
}

function sr_community_publisher_reward_log_by_dedupe_key(PDO $pdo, string $dedupeKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_community_publisher_reward_logs
         WHERE dedupe_key = :dedupe_key
         LIMIT 1'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_create_publisher_reward_notification(PDO $pdo, int $publisherAccountId, array $rewardLog): void
{
    if ($publisherAccountId < 1 || !is_file(SR_ROOT . '/modules/community/helpers/notifications.php')) {
        return;
    }

    require_once SR_ROOT . '/modules/community/helpers/notifications.php';
    if (!function_exists('sr_community_create_account_event_notification')) {
        return;
    }

    sr_community_create_account_event_notification($pdo, $publisherAccountId, 'attachment.publisher_reward.granted', [
        'amount' => number_format((int) ($rewardLog['reward_amount'] ?? 0)),
        'asset' => sr_community_asset_module_label((string) ($rewardLog['asset_module'] ?? ''), $pdo),
        'link_url' => sr_url('/community/post?id=' . rawurlencode((string) (int) ($rewardLog['post_id'] ?? 0))),
    ], null);
}

function sr_community_grant_attachment_publisher_reward(PDO $pdo, array $board, array $settings, array $post, array $attachment, int $downloaderAccountId, array $downloadResult): void
{
    $config = sr_community_publisher_reward_config($pdo, $board, $settings);
    if (empty($config['enabled']) || (int) ($config['rate'] ?? 0) <= 0 || empty($downloadResult['processed'])) {
        return;
    }

    $publisherAccountId = (int) ($post['author_account_id'] ?? 0);
    $attachmentId = (int) ($attachment['id'] ?? 0);
    $postId = (int) ($post['id'] ?? 0);
    if ($publisherAccountId < 1 || $downloaderAccountId < 1 || $attachmentId < 1 || $postId < 1 || $publisherAccountId === $downloaderAccountId) {
        return;
    }

    $processedLogs = is_array($downloadResult['processed_logs'] ?? null) ? $downloadResult['processed_logs'] : [];
    foreach ($processedLogs as $processedLog) {
        $dedupeKey = (string) ($processedLog['dedupe_key'] ?? '');
        if ($dedupeKey === '') {
            continue;
        }

        $chargeLog = sr_community_asset_log_by_dedupe_key($pdo, $dedupeKey);
        if (!is_array($chargeLog)
            || (string) ($chargeLog['event_key'] ?? '') !== 'attachment_download'
            || (string) ($chargeLog['direction'] ?? '') !== 'use'
            || (int) ($chargeLog['transaction_id'] ?? 0) < 1
            || (int) ($chargeLog['amount'] ?? 0) <= 0
        ) {
            continue;
        }

        $chargeAmount = (int) $chargeLog['amount'];
        $rewardRate = (int) $config['rate'];
        $rewardAmount = intdiv($chargeAmount * $rewardRate, 100);
        if ($rewardAmount <= 0) {
            continue;
        }

        $rewardDedupeKey = 'community.attachment_download.publisher_reward:' . (string) (int) $chargeLog['id'];
        if (sr_community_publisher_reward_log_by_dedupe_key($pdo, $rewardDedupeKey) !== null) {
            continue;
        }

        $now = sr_now();
        $insert = $pdo->prepare(
            'INSERT INTO sr_community_publisher_reward_logs
                (charge_asset_log_id, charge_transaction_id, reward_transaction_id, reversal_transaction_id,
                 post_id, attachment_id, downloader_account_id, publisher_account_id, asset_module,
                 charge_amount, reward_rate, reward_amount, status, dedupe_key, failure_message, created_at, updated_at)
             VALUES
                (:charge_asset_log_id, :charge_transaction_id, 0, 0,
                 :post_id, :attachment_id, :downloader_account_id, :publisher_account_id, :asset_module,
                 :charge_amount, :reward_rate, :reward_amount, \'pending\', :dedupe_key, NULL, :created_at, :updated_at)'
        );
        try {
            $insert->execute([
                'charge_asset_log_id' => (int) $chargeLog['id'],
                'charge_transaction_id' => (int) $chargeLog['transaction_id'],
                'post_id' => $postId,
                'attachment_id' => $attachmentId,
                'downloader_account_id' => $downloaderAccountId,
                'publisher_account_id' => $publisherAccountId,
                'asset_module' => (string) $chargeLog['asset_module'],
                'charge_amount' => $chargeAmount,
                'reward_rate' => $rewardRate,
                'reward_amount' => $rewardAmount,
                'dedupe_key' => $rewardDedupeKey,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable $exception) {
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'community_publisher_reward_log_insert_failed');
            }
            continue;
        }

        try {
            $transactionId = sr_community_create_asset_transaction($pdo, (string) $chargeLog['asset_module'], [
                'account_id' => $publisherAccountId,
                'amount' => $rewardAmount,
                'transaction_type' => (string) ((sr_community_asset_modules($pdo)[(string) $chargeLog['asset_module']]['credit_type'] ?? 'grant')),
                'reason' => '첨부 다운로드 리워드',
                'reference_type' => 'community.attachment.publisher_reward',
                'reference_id' => (string) (int) $chargeLog['id'],
                'created_by_account_id' => null,
            ]);
            $stmt = $pdo->prepare(
                "UPDATE sr_community_publisher_reward_logs
                 SET reward_transaction_id = :transaction_id,
                     status = 'granted',
                     updated_at = :updated_at
                 WHERE dedupe_key = :dedupe_key"
            );
            $stmt->execute([
                'transaction_id' => $transactionId,
                'updated_at' => sr_now(),
                'dedupe_key' => $rewardDedupeKey,
            ]);
            $rewardLog = sr_community_publisher_reward_log_by_dedupe_key($pdo, $rewardDedupeKey);
            if (is_array($rewardLog)) {
                sr_community_create_publisher_reward_notification($pdo, $publisherAccountId, $rewardLog);
            }
        } catch (Throwable $exception) {
            $stmt = $pdo->prepare(
                "UPDATE sr_community_publisher_reward_logs
                 SET status = 'failed',
                     failure_message = :failure_message,
                     updated_at = :updated_at
                 WHERE dedupe_key = :dedupe_key"
            );
            $stmt->execute([
                'failure_message' => mb_substr($exception->getMessage(), 0, 1000),
                'updated_at' => sr_now(),
                'dedupe_key' => $rewardDedupeKey,
            ]);
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'community_publisher_reward_grant_failed');
            }
        }
    }
}

function sr_community_asset_reversal_config(array $originalLog): array
{
    return [
        'enabled' => true,
        'asset_module' => (string) ($originalLog['asset_module'] ?? 'point'),
        'amount' => (int) ($originalLog['amount'] ?? 0),
        'charge_policy' => 'once',
    ];
}

function sr_community_reverse_asset_grant(PDO $pdo, int $accountId, string $grantEventKey, string $subjectType, int $subjectId, string $reversalEventKey, string $reason): array
{
    foreach (array_keys(sr_community_asset_modules()) as $assetModule) {
        $original = sr_community_asset_log($pdo, sr_community_asset_dedupe_key((string) $assetModule, $accountId, $grantEventKey, $subjectId));
        if (!is_array($original) || (int) ($original['transaction_id'] ?? 0) < 1 || (string) ($original['direction'] ?? '') !== 'grant') {
            continue;
        }

        return sr_community_run_asset_event($pdo, sr_community_asset_reversal_config($original), $accountId, $reversalEventKey, $subjectType, $subjectId, 'use', $reason);
    }

    return ['allowed' => true, 'processed' => false, 'message' => ''];
}

function sr_community_asset_reversal_error_message(array $result, string $balanceLowKey, string $fallbackKey): string
{
    if ((string) ($result['error_key'] ?? '') === 'asset_balance_low') {
        return sr_t($balanceLowKey);
    }

    $message = trim((string) ($result['message'] ?? ''));
    return $message !== '' ? $message : sr_t($fallbackKey);
}
