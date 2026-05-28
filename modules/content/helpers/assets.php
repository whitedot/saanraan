<?php

declare(strict_types=1);

function sr_content_setting_source_values(): array
{
    return ['content', 'group', 'all'];
}

function sr_content_normalize_setting_source(string $source): string
{
    if ($source === 'here_only') {
        return 'content';
    }

    return in_array($source, sr_content_setting_source_values(), true) ? $source : 'content';
}

function sr_content_asset_modules(?PDO $pdo = null): array
{
    require_once SR_ROOT . '/modules/member/helpers/assets.php';

    return sr_member_ledger_asset_definitions($pdo);
}

function sr_content_asset_charge_policies(): array
{
    return sr_content_asset_view_charge_policies() + sr_content_asset_download_charge_policies();
}

function sr_content_asset_view_charge_policies(): array
{
    return [
        'once' => '최초 1회',
        'every_view' => '매 열람',
    ];
}

function sr_content_asset_download_charge_policies(): array
{
    return [
        'once' => '최초 1회',
        'every_download' => '매 다운로드',
    ];
}

function sr_content_asset_action_directions(): array
{
    return [
        'grant' => '지급',
        'use' => '차감',
    ];
}

function sr_content_asset_module_is_available(PDO $pdo, string $assetModule): bool
{
    $options = sr_content_asset_modules($pdo);
    if (!isset($options[$assetModule])) {
        return false;
    }

    $option = $options[$assetModule];
    $moduleKey = (string) ($option['module_key'] ?? '');
    if (!sr_module_enabled($pdo, $moduleKey)) {
        return false;
    }

    return function_exists((string) ($option['balance_function'] ?? ''))
        && function_exists((string) ($option['transaction_function'] ?? ''));
}

function sr_content_asset_module_options(PDO $pdo): array
{
    $available = [];
    foreach (sr_content_asset_modules($pdo) as $assetModule => $option) {
        if (sr_content_asset_module_is_available($pdo, (string) $assetModule)) {
            $available[$assetModule] = $option;
        }
    }

    return $available;
}

function sr_content_asset_module_label(string $assetModule, ?PDO $pdo = null): string
{
    $options = sr_content_asset_modules($pdo);
    return isset($options[$assetModule]) ? (string) $options[$assetModule]['label'] : '회원 자산';
}

function sr_content_asset_module_unit_label(string $assetModule, ?PDO $pdo = null): string
{
    $options = sr_content_asset_modules($pdo);
    return isset($options[$assetModule]) ? (string) ($options[$assetModule]['unit_label'] ?? '') : '';
}

function sr_content_asset_option_unit_label(array $assetModuleOptions, string $assetModule): string
{
    return isset($assetModuleOptions[$assetModule]) ? (string) ($assetModuleOptions[$assetModule]['unit_label'] ?? '') : '';
}

function sr_content_asset_single_amount_input_group_html(string $fieldName, int $amount, array $assetModuleOptions, string $assetModule, string $label, string $id = '', bool $hidden = false, string $sourceFieldName = ''): string
{
    $unitLabel = sr_content_asset_option_unit_label($assetModuleOptions, $assetModule);
    $idAttribute = $id !== '' ? ' id="' . sr_e($id) . '"' : '';
    $hiddenAttribute = $hidden ? ' hidden' : '';
    $unitOptions = [];
    foreach ($assetModuleOptions as $optionKey => $option) {
        $unitOptions[(string) $optionKey] = (string) ($option['unit_label'] ?? '');
    }
    $unitSourceAttribute = $sourceFieldName !== '' ? ' data-admin-asset-unit-source="' . sr_e($sourceFieldName) . '"' : '';
    $unitOptionsAttribute = $sourceFieldName !== ''
        ? ' data-admin-asset-unit-options="' . sr_e((string) json_encode($unitOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"'
        : '';

    return '<div class="input-group admin-asset-single-amount-group" data-admin-asset-unit-group' . $unitSourceAttribute . $unitOptionsAttribute . ' data-content-action-grant-amount' . $hiddenAttribute . '>'
        . '<input' . $idAttribute . ' type="text" inputmode="numeric" pattern="[0-9,]*" name="' . sr_e($fieldName) . '" value="' . sr_e((string) max(0, $amount)) . '" class="form-input admin-asset-setting-amount" aria-label="' . sr_e($label) . '" data-admin-asset-amount-input>'
        . '<span class="input-group-text" data-admin-asset-unit-label>' . sr_e($unitLabel) . '</span>'
        . '</div>';
}

function sr_content_asset_deduction_order(): array
{
    return array_keys(sr_content_asset_modules());
}

function sr_content_asset_module_keys_from_value(mixed $value): array
{
    $rawValues = is_array($value) ? $value : preg_split('/[\s,]+/', (string) $value);
    $selected = [];
    foreach (is_array($rawValues) ? $rawValues : [] as $rawValue) {
        $assetModule = sr_content_clean_slug((string) $rawValue);
        if (isset(sr_content_asset_modules()[$assetModule])) {
            $selected[$assetModule] = true;
        }
    }

    $ordered = [];
    foreach (sr_content_asset_deduction_order() as $assetModule) {
        if (isset($selected[$assetModule])) {
            $ordered[] = $assetModule;
        }
    }

    return $ordered;
}

function sr_content_asset_module_value_from_keys(array $assetModules): string
{
    return implode(',', sr_content_asset_module_keys_from_value($assetModules));
}

function sr_content_asset_module_labels(string $assetModuleValue, ?PDO $pdo = null): string
{
    $labels = [];
    foreach (sr_content_asset_module_keys_from_value($assetModuleValue) as $assetModule) {
        $labels[] = sr_content_asset_module_label($assetModule, $pdo);
    }

    return $labels !== [] ? implode(', ', $labels) : '회원 자산';
}

function sr_content_require_asset_group_policy_helpers(): void
{
    if (!function_exists('sr_admin_asset_group_policies_from_json')) {
        require_once SR_ROOT . '/modules/admin/helpers/asset-group-policies.php';
    }
}

function sr_content_asset_group_policy_json_from_value(mixed $value): string
{
    sr_content_require_asset_group_policy_helpers();

    return sr_admin_asset_group_policy_json_from_value($value);
}

function sr_content_asset_group_policies_from_value(mixed $value): array
{
    sr_content_require_asset_group_policy_helpers();

    try {
        return sr_admin_asset_group_policies_from_json(is_string($value) ? $value : sr_admin_asset_group_policy_json_from_value($value));
    } catch (Throwable $exception) {
        return [];
    }
}

function sr_content_asset_group_policy_json_from_post(string $fieldName): string
{
    sr_content_require_asset_group_policy_helpers();

    return sr_admin_asset_group_policy_json_from_value(sr_admin_asset_group_policies_from_post($fieldName));
}

function sr_content_asset_group_policy_json_from_input(mixed $input): string
{
    sr_content_require_asset_group_policy_helpers();

    return sr_admin_asset_group_policy_json_from_value(sr_admin_asset_group_policies_from_input($input));
}

function sr_content_asset_policy_set_key_is_valid(string $setKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $setKey) === 1;
}

function sr_content_asset_policy_set_statuses(): array
{
    return ['enabled', 'disabled', 'archived'];
}

function sr_content_asset_policy_sets(PDO $pdo, bool $enabledOnly = false): array
{
    try {
        $sql = 'SELECT * FROM sr_content_asset_policy_sets';
        if ($enabledOnly) {
            $sql .= " WHERE status = 'enabled'";
        }
        $sql .= ' ORDER BY title ASC, id ASC';
        $stmt = $pdo->query($sql);
        return $stmt !== false ? $stmt->fetchAll() : [];
    } catch (Throwable $exception) {
        return [];
    }
}

function sr_content_asset_policy_set_by_id(PDO $pdo, int $setId): ?array
{
    if ($setId < 1) {
        return null;
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM sr_content_asset_policy_sets WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $setId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    } catch (Throwable $exception) {
        return null;
    }
}

function sr_content_asset_policy_set_key_exists(PDO $pdo, string $setKey, int $exceptId = 0): bool
{
    $stmt = $pdo->prepare(
        'SELECT id FROM sr_content_asset_policy_sets WHERE set_key = :set_key AND id <> :id LIMIT 1'
    );
    $stmt->execute([
        'set_key' => $setKey,
        'id' => $exceptId,
    ]);

    return is_array($stmt->fetch());
}

function sr_content_save_asset_policy_set(PDO $pdo, array $values, int $accountId, int $setId = 0): int
{
    $now = sr_now();
    $params = [
        'set_key' => (string) ($values['set_key'] ?? ''),
        'title' => (string) ($values['title'] ?? ''),
        'description' => (string) ($values['description'] ?? ''),
        'status' => (string) ($values['status'] ?? 'enabled'),
        'policies_json' => sr_content_asset_group_policy_json_from_value($values['policies_json'] ?? ''),
        'updated_by' => $accountId,
        'updated_at' => $now,
    ];

    if ($setId > 0) {
        $params['id'] = $setId;
        $stmt = $pdo->prepare(
            'UPDATE sr_content_asset_policy_sets
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
        'INSERT INTO sr_content_asset_policy_sets
            (set_key, title, description, status, policies_json, created_by, updated_by, created_at, updated_at)
         VALUES
            (:set_key, :title, :description, :status, :policies_json, :created_by, :updated_by, :created_at, :updated_at)'
    );
    $stmt->execute($params);

    return (int) $pdo->lastInsertId();
}

function sr_content_asset_policy_set_select_html(string $id, string $name, array $policySets, int $selectedId): string
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

function sr_content_asset_modules_available(PDO $pdo, array $assetModules): bool
{
    $assetModules = sr_content_asset_module_keys_from_value($assetModules);
    if ($assetModules === []) {
        return false;
    }

    foreach ($assetModules as $assetModule) {
        if (!sr_content_asset_module_is_available($pdo, $assetModule)) {
            return false;
        }
    }

    return true;
}

function sr_content_asset_combined_balance(PDO $pdo, array $assetModules, int $accountId): int
{
    $balance = 0;
    foreach (sr_content_asset_module_keys_from_value($assetModules) as $assetModule) {
        $balance += sr_content_asset_balance($pdo, $assetModule, $accountId);
    }

    return $balance;
}

function sr_content_asset_amounts_from_value(mixed $value, array $assetModules = [], int $fallbackAmount = 0): array
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
            $assetModule = sr_content_clean_slug((string) $assetModule);
            if (isset(sr_content_asset_modules()[$assetModule])) {
                $amountValue = is_string($amount) && preg_match('/\A\d{1,3}(?:,\d{3})+\z/', trim($amount)) === 1
                    ? str_replace(',', '', trim($amount))
                    : $amount;
                $amounts[$assetModule] = min(999999999, max(0, (int) $amountValue));
            }
        }
    }

    $ordered = [];
    $modules = sr_content_asset_module_keys_from_value($assetModules);
    foreach ($modules as $assetModule) {
        $amount = $amounts[$assetModule] ?? 0;
        if ($amount <= 0 && $fallbackAmount > 0 && count($modules) === 1) {
            $amount = $fallbackAmount;
        }
        if ($amount > 0) {
            $ordered[$assetModule] = $amount;
        }
    }

    return $ordered;
}

function sr_content_asset_amounts_from_post(string $fieldName, array $assetModules, int $fallbackAmount = 0): array
{
    if (!array_key_exists($fieldName, $_POST)) {
        return sr_content_asset_amounts_from_value([], $assetModules, $fallbackAmount);
    }

    $values = $_POST[$fieldName] ?? [];
    return sr_content_asset_amounts_from_value(is_array($values) ? $values : [], $assetModules, 0);
}

function sr_content_asset_amount_input_values(mixed $amountsValue, array $assetModules, int $fallbackAmount = 0): array
{
    $amounts = sr_content_asset_amounts_from_value($amountsValue, $assetModules, 0);
    if ($amounts === [] && $fallbackAmount > 0) {
        $firstModule = sr_content_asset_module_keys_from_value($assetModules)[0] ?? '';
        if ($firstModule !== '') {
            $amounts[$firstModule] = $fallbackAmount;
        }
    }

    return $amounts;
}

function sr_content_asset_amount_inputs_html(string $fieldName, array $assetModuleOptions, array $selectedAssetModules, mixed $amountsValue, int $fallbackAmount, string $labelPrefix, bool $syncSelected = false): string
{
    $amounts = sr_content_asset_amount_input_values($amountsValue, $selectedAssetModules, $fallbackAmount);
    $html = '<div class="admin-asset-amount-grid"' . ($syncSelected ? ' data-admin-asset-amount-sync' : '') . '>';
    foreach ($assetModuleOptions as $assetModule => $assetOption) {
        $assetModule = (string) $assetModule;
        $label = (string) ($assetOption['label'] ?? sr_content_asset_module_label($assetModule));
        $unitLabel = (string) ($assetOption['unit_label'] ?? sr_content_asset_module_unit_label($assetModule));
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

function sr_content_asset_grouped_amount_inputs_html(string $id, string $moduleFieldName, string $amountFieldName, array $assetModuleOptions, array $selectedAssetModules, mixed $amountsValue, int $fallbackAmount, string $labelPrefix, string $emptyLabel): string
{
    $amounts = sr_content_asset_amount_input_values($amountsValue, $selectedAssetModules, $fallbackAmount);
    $selectedMap = [];
    foreach ($selectedAssetModules as $selectedAssetModule) {
        $selectedMap[(string) $selectedAssetModule] = true;
    }

    $idBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($id));
    $idBase = is_string($idBase) && $idBase !== '' ? $idBase : 'content_asset_amounts';
    $html = '<div id="' . sr_e($id) . '" class="admin-asset-amount-grid admin-asset-grouped-amount-grid" role="group" data-admin-asset-amount-sync>';
    if ($assetModuleOptions === []) {
        return $html . '<span class="admin-form-help">' . sr_e($emptyLabel) . '</span></div>';
    }

    $index = 0;
    foreach ($assetModuleOptions as $assetModule => $assetOption) {
        $assetModule = (string) $assetModule;
        if ($assetModule === '') {
            continue;
        }

        $label = (string) ($assetOption['label'] ?? sr_content_asset_module_label($assetModule));
        $unitLabel = (string) ($assetOption['unit_label'] ?? sr_content_asset_module_unit_label($assetModule));
        $inputId = $idBase . '_' . (string) $index;
        $isSelected = isset($selectedMap[$assetModule]);
        $html .= '<div class="admin-asset-amount-field admin-asset-grouped-amount-field' . ($isSelected ? ' is-selected' : '') . '" data-admin-asset-amount-field data-admin-asset-module="' . sr_e($assetModule) . '">'
            . '<div class="input-group admin-asset-grouped-input-group">'
            . '<label class="input-group-text admin-form-check form-label" for="' . sr_e($inputId) . '">'
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

function sr_content_asset_amounts_json_from_map(array $amounts): string
{
    $normalized = [];
    foreach (sr_content_asset_deduction_order() as $assetModule) {
        $amount = min(999999999, max(0, (int) ($amounts[$assetModule] ?? 0)));
        if ($amount > 0) {
            $normalized[$assetModule] = $amount;
        }
    }

    $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($encoded) ? $encoded : '{}';
}

function sr_content_asset_amount_total(array $amounts, int $fallbackAmount = 0): int
{
    return $amounts !== [] ? array_sum(array_map('intval', $amounts)) : max(0, $fallbackAmount);
}

function sr_content_allocate_asset_use_by_amounts(PDO $pdo, array $amounts, int $accountId): array
{
    $allocations = [];
    foreach (sr_content_asset_deduction_order() as $assetModule) {
        $amount = (int) ($amounts[$assetModule] ?? 0);
        if ($amount <= 0) {
            continue;
        }
        if (sr_content_asset_balance($pdo, $assetModule, $accountId) < $amount) {
            return [];
        }
        $allocations[] = [
            'asset_module' => $assetModule,
            'amount' => $amount,
        ];
    }

    return $allocations;
}

function sr_content_normalize_asset_values(array $values, bool $coerceInvalid = true): array
{
    $assetModule = sr_content_asset_module_value_from_keys(sr_content_asset_module_keys_from_value($values['asset_module'] ?? ''));
    $accessAmounts = sr_content_asset_amounts_from_value($values['asset_access_amounts_json'] ?? '', sr_content_asset_module_keys_from_value($assetModule), (int) ($values['asset_access_amount'] ?? 0));

    $chargePolicy = (string) ($values['asset_charge_policy'] ?? 'once');
    if ($coerceInvalid && !isset(sr_content_asset_charge_policies()[$chargePolicy])) {
        $chargePolicy = 'once';
    }

    $values['asset_access_enabled'] = (int) ($values['asset_access_enabled'] ?? 0) === 1 ? 1 : 0;
    $values['asset_module'] = $assetModule;
    $values['asset_access_amount'] = sr_content_asset_amount_total($accessAmounts, max(0, (int) ($values['asset_access_amount'] ?? 0)));
    $values['asset_access_amounts_json'] = sr_content_asset_amounts_json_from_map($accessAmounts);
    $values['asset_access_group_policies_json'] = sr_content_asset_group_policy_json_from_value($values['asset_access_group_policies_json'] ?? '');
    $values['asset_access_policy_set_id'] = max(0, (int) ($values['asset_access_policy_set_id'] ?? 0));
    $values['asset_charge_policy'] = $chargePolicy;

    $actionDirection = (string) ($values['asset_action_direction'] ?? 'grant');
    if ($coerceInvalid && !isset(sr_content_asset_action_directions()[$actionDirection])) {
        $actionDirection = 'grant';
    }

    $actionModule = sr_content_asset_module_value_from_keys(sr_content_asset_module_keys_from_value($values['asset_action_module'] ?? ''));
    $actionAmounts = $actionDirection === 'use'
        ? sr_content_asset_amounts_from_value($values['asset_action_amounts_json'] ?? '', sr_content_asset_module_keys_from_value($actionModule), (int) ($values['asset_action_amount'] ?? 0))
        : [];

    $values['asset_action_enabled'] = (int) ($values['asset_action_enabled'] ?? 0) === 1 ? 1 : 0;
    $values['asset_action_module'] = $actionModule;
    $values['asset_action_amount'] = $actionDirection === 'use'
        ? sr_content_asset_amount_total($actionAmounts, max(0, (int) ($values['asset_action_amount'] ?? 0)))
        : max(0, (int) ($values['asset_action_amount'] ?? 0));
    $values['asset_action_amounts_json'] = sr_content_asset_amounts_json_from_map($actionAmounts);
    $values['asset_action_group_policies_json'] = sr_content_asset_group_policy_json_from_value($values['asset_action_group_policies_json'] ?? '');
    $values['asset_action_policy_set_id'] = max(0, (int) ($values['asset_action_policy_set_id'] ?? 0));
    $values['asset_action_direction'] = $actionDirection;
    $values['asset_action_label'] = sr_content_clean_single_line((string) ($values['asset_action_label'] ?? '완료'), 80);
    if ((string) $values['asset_action_label'] === '') {
        $values['asset_action_label'] = '완료';
    }

    return $values;
}

function sr_content_asset_settings_for_audit(array $values): array
{
    $values = sr_content_normalize_asset_values($values);
    $settings = [];
    foreach (sr_content_group_asset_setting_keys() as $settingKey) {
        $settings[$settingKey] = $values[$settingKey] ?? '';
        $settings['source_' . $settingKey] = sr_content_normalize_setting_source((string) ($values['source_' . $settingKey] ?? 'content'));
    }
    foreach (sr_content_group_file_asset_setting_keys() as $settingKey) {
        $settings['source_' . $settingKey] = sr_content_normalize_setting_source((string) ($values['source_' . $settingKey] ?? 'content'));
    }

    return $settings;
}

function sr_content_file_asset_settings_for_audit(array $file): array
{
    $values = sr_content_normalize_file_asset_values([
        'asset_download_enabled' => (int) ($file['asset_download_enabled'] ?? 0),
        'asset_module' => (string) ($file['asset_module'] ?? ''),
        'asset_download_amount' => (int) ($file['asset_download_amount'] ?? 0),
        'asset_download_amounts_json' => (string) ($file['asset_download_amounts_json'] ?? ''),
        'asset_download_group_policies_json' => (string) ($file['asset_download_group_policies_json'] ?? ''),
        'asset_download_policy_set_id' => (int) ($file['asset_download_policy_set_id'] ?? 0),
        'asset_charge_policy' => (string) ($file['asset_charge_policy'] ?? 'once'),
    ]);

    return [
        'asset_download_enabled' => (int) $values['asset_download_enabled'],
        'asset_module' => (string) $values['asset_module'],
        'asset_download_amount' => (int) $values['asset_download_amount'],
        'asset_download_amounts_json' => (string) $values['asset_download_amounts_json'],
        'asset_charge_policy' => (string) $values['asset_charge_policy'],
    ];
}

function sr_content_files_asset_settings_for_audit(PDO $pdo, int $pageId): array
{
    $settings = [];
    foreach (sr_content_files_for_content($pdo, $pageId) as $file) {
        $settings[(string) (int) $file['id']] = sr_content_file_asset_settings_for_audit($file);
    }

    return $settings;
}

function sr_content_asset_settings_from_storage_for_audit(PDO $pdo, int $pageId): array
{
    $page = sr_content_by_id($pdo, $pageId);
    if (!is_array($page)) {
        return [];
    }

    $sources = sr_content_setting_sources($pdo, $pageId);
    foreach (sr_content_group_asset_setting_keys() as $settingKey) {
        $page['source_' . $settingKey] = $sources[$settingKey] ?? 'content';
    }
    foreach (sr_content_group_file_asset_setting_keys() as $settingKey) {
        $page['source_' . $settingKey] = $sources[$settingKey] ?? 'content';
    }

    return [
        'content' => sr_content_asset_settings_for_audit($page),
        'files' => sr_content_files_asset_settings_for_audit($pdo, $pageId),
    ];
}

function sr_content_group_asset_settings_for_audit(array $settings): array
{
    $assetSettings = sr_content_normalize_asset_values($settings);
    $fileAssetSettings = sr_content_normalize_file_asset_values([
        'asset_download_enabled' => $settings['file_asset_download_enabled'] ?? 0,
        'asset_module' => $settings['file_asset_module'] ?? '',
        'asset_download_amount' => $settings['file_asset_download_amount'] ?? 0,
        'asset_download_amounts_json' => $settings['file_asset_download_amounts_json'] ?? '',
        'asset_download_group_policies_json' => $settings['file_asset_download_group_policies_json'] ?? '',
        'asset_download_policy_set_id' => $settings['file_asset_download_policy_set_id'] ?? 0,
        'asset_charge_policy' => $settings['file_asset_charge_policy'] ?? 'once',
    ]);

    $auditSettings = [];
    foreach (sr_content_group_asset_setting_keys() as $settingKey) {
        $auditSettings[$settingKey] = $assetSettings[$settingKey] ?? '';
    }
    $auditSettings['file_asset_download_enabled'] = (int) $fileAssetSettings['asset_download_enabled'];
    $auditSettings['file_asset_module'] = (string) $fileAssetSettings['asset_module'];
    $auditSettings['file_asset_download_amount'] = (int) $fileAssetSettings['asset_download_amount'];
    $auditSettings['file_asset_download_amounts_json'] = (string) $fileAssetSettings['asset_download_amounts_json'];
    $auditSettings['file_asset_download_group_policies_json'] = (string) $fileAssetSettings['asset_download_group_policies_json'];
    $auditSettings['file_asset_download_policy_set_id'] = (int) $fileAssetSettings['asset_download_policy_set_id'];
    $auditSettings['file_asset_charge_policy'] = (string) $fileAssetSettings['asset_charge_policy'];

    return $auditSettings;
}

function sr_content_group_asset_settings_from_storage_for_audit(PDO $pdo, int $groupId): array
{
    return sr_content_group_asset_settings_for_audit(sr_content_group_settings($pdo, $groupId));
}

function sr_content_asset_access_required(array $page): bool
{
    return (int) ($page['asset_access_enabled'] ?? 0) === 1
        && (int) ($page['asset_access_amount'] ?? 0) > 0;
}

function sr_content_asset_amounts_with_group_policy(PDO $pdo, int $accountId, array $assetModules, array $amounts, int $fallbackAmount, mixed $policyValue, int $policySetId = 0): array
{
    sr_content_require_asset_group_policy_helpers();
    $policySet = $policySetId > 0 ? sr_content_asset_policy_set_by_id($pdo, $policySetId) : null;
    $policySetActive = is_array($policySet) && (string) ($policySet['status'] ?? '') === 'enabled';
    $policies = $policySetActive
        ? sr_content_asset_group_policies_from_value((string) ($policySet['policies_json'] ?? ''))
        : sr_content_asset_group_policies_from_value($policyValue);
    $adjustedAmounts = [];
    $snapshots = [];
    $sourceAmounts = $amounts;
    if ($sourceAmounts === [] && $assetModules !== []) {
        $sourceAmounts[(string) $assetModules[0]] = $fallbackAmount;
    }

    foreach ($sourceAmounts as $assetModule => $baseAmount) {
        $baseAmount = max(0, (int) $baseAmount);
        $snapshot = sr_admin_asset_group_policy_apply($pdo, $accountId, $baseAmount, $policies, (string) $assetModule);
        $finalAmount = max(0, (int) ($snapshot['final_amount'] ?? $baseAmount));
        $snapshot['asset_module'] = (string) $assetModule;
        $snapshot['policy_set_id'] = $policySetActive ? (int) ($policySet['id'] ?? 0) : 0;
        $snapshot['policy_set_key'] = $policySetActive ? (string) ($policySet['set_key'] ?? '') : '';
        $snapshot['policy_set_title'] = $policySetActive ? (string) ($policySet['title'] ?? '') : '';
        $snapshot['final_amount'] = $finalAmount;
        $snapshots[(string) $assetModule] = $snapshot;
        if ($finalAmount > 0) {
            $adjustedAmounts[(string) $assetModule] = $finalAmount;
        }
    }

    return [
        'amounts' => $adjustedAmounts,
        'amount' => sr_content_asset_amount_total($adjustedAmounts),
        'snapshots' => $snapshots,
        'policies_applied' => $policies !== [],
    ];
}

function sr_content_asset_group_policy_snapshot_json(array $snapshots): string
{
    if ($snapshots === []) {
        return '';
    }

    $json = json_encode(array_values($snapshots), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '';
}

function sr_content_asset_access_reference_id(int $pageId): string
{
    return (string) $pageId;
}

function sr_content_asset_access_dedupe_key(string $assetModule, int $accountId, int $subjectId, string $accessKind = 'view'): string
{
    return 'content.' . $accessKind . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId;
}

function sr_content_asset_access_log(PDO $pdo, string $dedupeKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_content_asset_access_logs
         WHERE dedupe_key = :dedupe_key
         LIMIT 1'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_has_paid_access(PDO $pdo, string $assetModule, int $accountId, int $subjectId, string $accessKind = 'view'): bool
{
    $dedupeKey = sr_content_asset_access_dedupe_key($assetModule, $accountId, $subjectId, $accessKind);
    $log = sr_content_asset_access_log($pdo, $dedupeKey);

    return is_array($log) && ((int) ($log['transaction_id'] ?? 0) > 0 || (int) ($log['amount'] ?? -1) === 0);
}

function sr_content_has_paid_access_for_modules(PDO $pdo, array $assetModules, int $accountId, int $subjectId, string $accessKind = 'view'): bool
{
    foreach (sr_content_asset_module_keys_from_value($assetModules) as $assetModule) {
        if (sr_content_has_paid_access($pdo, $assetModule, $accountId, $subjectId, $accessKind)) {
            return true;
        }
    }

    return false;
}

function sr_content_has_asset_access_history(PDO $pdo, array $assetModules, int $accountId, int $subjectId, string $accessKind, string $policy): bool
{
    $policy = sr_content_once_history_policy($policy);
    if ($policy === 'current_asset_once') {
        return sr_content_has_paid_access_for_modules($pdo, $assetModules, $accountId, $subjectId, $accessKind);
    }

    $params = [
        'account_id' => $accountId,
        'reference_id' => (string) $subjectId,
        'access_kind' => $accessKind,
    ];
    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_content_asset_access_logs
         WHERE account_id = :account_id
           AND reference_id = :reference_id
           AND access_kind = :access_kind
           AND (transaction_id > 0 OR amount = 0)'
        . ' LIMIT 1'
    );
    $stmt->execute($params);

    return is_array($stmt->fetch());
}

function sr_content_has_coupon_access_history(PDO $pdo, int $pageId, int $accountId): bool
{
    if ($pageId <= 0 || $accountId <= 0 || !sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return false;
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_has_redemption')) {
        return false;
    }

    return sr_coupon_has_redemption($pdo, $accountId, 'content.view:coupon:' . (string) $accountId . ':' . (string) $pageId);
}

function sr_content_access_entitlements_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query('SELECT 1 FROM sr_content_access_entitlements LIMIT 1');
        $exists = $stmt !== false;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_content_access_entitlement_subject_type(string $accessKind): string
{
    return $accessKind === 'download' ? 'content_file' : 'content';
}

function sr_content_grant_access_entitlement(PDO $pdo, int $accountId, int $contentId, string $subjectType, int $subjectId, string $accessKind, string $sourceKind, string $sourceAssetModule = '', string $sourceChargePolicy = 'once', string $sourceReference = ''): void
{
    if ($accountId <= 0 || $contentId <= 0 || $subjectId <= 0 || $subjectType === '' || $accessKind === '' || !sr_content_access_entitlements_table_exists($pdo)) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_content_access_entitlements
            (account_id, content_id, subject_type, subject_id, access_kind, source_kind, source_asset_module, source_charge_policy, source_reference, granted_at, created_at)
         VALUES
            (:account_id, :content_id, :subject_type, :subject_id, :access_kind, :source_kind, :source_asset_module, :source_charge_policy, :source_reference, :granted_at, :created_at)'
    );
    $now = sr_now();
    $stmt->execute([
        'account_id' => $accountId,
        'content_id' => $contentId,
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'access_kind' => $accessKind,
        'source_kind' => $sourceKind,
        'source_asset_module' => $sourceAssetModule,
        'source_charge_policy' => $sourceChargePolicy,
        'source_reference' => $sourceReference,
        'granted_at' => $now,
        'created_at' => $now,
    ]);
}

function sr_content_anonymize_access_entitlements(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0 || !sr_content_access_entitlements_table_exists($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_content_access_entitlements
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

function sr_content_has_access_entitlement(PDO $pdo, array $assetModules, int $accountId, int $subjectId, string $accessKind, string $policy): bool
{
    $policy = sr_content_once_history_policy($policy);
    if (!sr_content_access_entitlements_table_exists($pdo)) {
        if (sr_content_has_asset_access_history($pdo, $assetModules, $accountId, $subjectId, $accessKind, $policy)) {
            return true;
        }

        return $policy === 'all_access'
            && $accessKind === 'view'
            && sr_content_has_coupon_access_history($pdo, $subjectId, $accountId);
    }

    $conditions = [
        'account_id = :account_id',
        'subject_type = :subject_type',
        'subject_id = :subject_id',
        'access_kind = :access_kind',
        'anonymized_at IS NULL',
    ];
    $params = [
        'account_id' => $accountId,
        'subject_type' => sr_content_access_entitlement_subject_type($accessKind),
        'subject_id' => $subjectId,
        'access_kind' => $accessKind,
    ];

    if ($policy === 'asset_any') {
        $conditions[] = 'source_kind = \'asset\'';
    } elseif ($policy === 'current_asset_once') {
        $moduleKeys = sr_content_asset_module_keys_from_value($assetModules);
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
         FROM sr_content_access_entitlements
         WHERE ' . implode(' AND ', $conditions) . '
         LIMIT 1'
    );
    $stmt->execute($params);

    return is_array($stmt->fetch());
}

function sr_content_once_access_already_granted(PDO $pdo, array $assetModules, int $accountId, int $subjectId, string $accessKind = 'view'): bool
{
    $settings = sr_content_settings($pdo);
    $policy = sr_content_once_history_policy((string) ($settings['once_history_policy'] ?? 'all_access'));

    return sr_content_has_access_entitlement($pdo, $assetModules, $accountId, $subjectId, $accessKind, $policy);
}

function sr_content_asset_balance(PDO $pdo, string $assetModule, int $accountId): int
{
    if (!sr_content_asset_module_is_available($pdo, $assetModule)) {
        return 0;
    }

    $option = sr_content_asset_modules($pdo)[$assetModule];
    $balanceFunction = (string) $option['balance_function'];

    return (int) $balanceFunction($pdo, $accountId);
}

function sr_content_create_asset_transaction(PDO $pdo, string $assetModule, array $data): int
{
    if (!sr_content_asset_module_is_available($pdo, $assetModule)) {
        throw new RuntimeException('Page asset module is not available.');
    }

    $option = sr_content_asset_modules($pdo)[$assetModule];
    $transactionFunction = (string) $option['transaction_function'];

    return (int) $transactionFunction($pdo, $data);
}

function sr_content_allocate_asset_use(PDO $pdo, array $assetModules, int $accountId, int $amount): array
{
    $remaining = max(0, $amount);
    $allocations = [];
    foreach (sr_content_asset_module_keys_from_value($assetModules) as $assetModule) {
        if ($remaining <= 0) {
            break;
        }

        $balance = sr_content_asset_balance($pdo, $assetModule, $accountId);
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

function sr_content_insert_asset_access_placeholder(PDO $pdo, int $pageId, int $accountId, string $assetModule, int $amount, string $chargePolicy, string $dedupeKey, string $referenceType = 'content.view', ?string $referenceId = null, string $accessKind = 'view', string $groupPolicySnapshotJson = ''): bool
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_content_asset_access_logs
            (content_id, account_id, asset_module, transaction_id, reference_type, reference_id, access_kind, charge_policy, amount, group_policy_snapshot_json, dedupe_key, created_at)
         VALUES
            (:content_id, :account_id, :asset_module, 0, :reference_type, :reference_id, :access_kind, :charge_policy, :amount, :group_policy_snapshot_json, :dedupe_key, :created_at)'
    );
    $stmt->execute([
        'content_id' => $pageId,
        'account_id' => $accountId,
        'asset_module' => $assetModule,
        'reference_type' => $referenceType,
        'reference_id' => $referenceId ?? sr_content_asset_access_reference_id($pageId),
        'access_kind' => $accessKind,
        'charge_policy' => $chargePolicy,
        'amount' => $amount,
        'group_policy_snapshot_json' => $groupPolicySnapshotJson,
        'dedupe_key' => $dedupeKey,
        'created_at' => sr_now(),
    ]);

    return $stmt->rowCount() > 0;
}

function sr_content_update_asset_access_transaction(PDO $pdo, string $dedupeKey, int $transactionId): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_content_asset_access_logs
         SET transaction_id = :transaction_id
         WHERE dedupe_key = :dedupe_key'
    );
    $stmt->execute([
        'transaction_id' => $transactionId,
        'dedupe_key' => $dedupeKey,
    ]);
}

function sr_content_delete_asset_access_placeholder(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM sr_content_asset_access_logs
         WHERE dedupe_key = :dedupe_key
           AND transaction_id = 0'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
}

function sr_content_asset_access_result(PDO $pdo, bool $allowed, bool $charged, string $assetModuleValue, int $amount, string $message = '', array $extra = []): array
{
    return array_merge([
        'allowed' => $allowed,
        'charged' => $charged,
        'asset_module' => $assetModuleValue,
        'asset_label' => sr_content_asset_module_labels($assetModuleValue, $pdo),
        'amount' => $amount,
        'message' => $message,
    ], $extra);
}

function sr_content_asset_access_dedupe_key_for_policy(string $chargePolicy, string $referenceType, string $assetModule, int $accountId, int $subjectId, string $accessKind = 'view'): string
{
    if ($chargePolicy === 'once') {
        return sr_content_asset_access_dedupe_key($assetModule, $accountId, $subjectId, $accessKind);
    }

    return $referenceType . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId . ':' . bin2hex(random_bytes(8));
}

function sr_content_charge_view_access(PDO $pdo, array $page, int $accountId): array
{
    $pageId = (int) ($page['id'] ?? 0);
    $assetModules = sr_content_asset_module_keys_from_value($page['asset_module'] ?? '');
    $assetModuleValue = sr_content_asset_module_value_from_keys($assetModules);
    $chargePolicy = (string) ($page['asset_charge_policy'] ?? 'once');
    $amounts = sr_content_asset_amounts_from_value($page['asset_access_amounts_json'] ?? '', $assetModules, (int) ($page['asset_access_amount'] ?? 0));
    $amount = $amounts !== [] ? sr_content_asset_amount_total($amounts) : (int) ($page['asset_access_amount'] ?? 0);

    if ($pageId <= 0 || $accountId <= 0 || !sr_content_asset_access_required($page)) {
        return ['allowed' => true, 'charged' => false, 'message' => ''];
    }

    if ($assetModules === [] || !isset(sr_content_asset_view_charge_policies()[$chargePolicy])) {
        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '콘텐츠 유료 열람 설정이 올바르지 않아 열람할 수 없습니다.');
    }

    if (!sr_content_asset_modules_available($pdo, $assetModules)) {
        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '선택한 자산 모듈을 모두 사용할 수 없어 콘텐츠를 열람할 수 없습니다.');
    }

    $policyAmounts = sr_content_asset_amounts_with_group_policy($pdo, $accountId, $assetModules, $amounts, (int) ($page['asset_access_amount'] ?? 0), $page['asset_access_group_policies_json'] ?? '', (int) ($page['asset_access_policy_set_id'] ?? 0));
    $amounts = $policyAmounts['amounts'];
    $amount = (int) $policyAmounts['amount'];
    if ($amount <= 0) {
        $assetModule = (string) ($assetModules[0] ?? $assetModuleValue);
        $dedupeKey = sr_content_asset_access_dedupe_key_for_policy($chargePolicy, 'content.view', $assetModule, $accountId, $pageId);
        sr_content_insert_asset_access_placeholder($pdo, $pageId, $accountId, $assetModule, 0, $chargePolicy, $dedupeKey, 'content.view', null, 'view', sr_content_asset_group_policy_snapshot_json($policyAmounts['snapshots']));
        sr_content_grant_access_entitlement($pdo, $accountId, $pageId, 'content', $pageId, 'view', 'asset_group_policy', $assetModule, $chargePolicy, $dedupeKey);
        return sr_content_asset_access_result($pdo, true, false, $assetModuleValue, 0, '', ['group_policy_applied' => true]);
    }

    if ($chargePolicy === 'once' && sr_content_once_access_already_granted($pdo, $assetModules, $accountId, $pageId)) {
        return sr_content_asset_access_result($pdo, true, false, $assetModuleValue, $amount, '', ['already_paid' => true]);
    }

    $couponResult = sr_content_try_coupon_access($pdo, $pageId, $accountId, $chargePolicy);
    if (!empty($couponResult['allowed'])) {
        sr_content_grant_access_entitlement($pdo, $accountId, $pageId, 'content', $pageId, 'view', 'coupon', '', $chargePolicy, (string) ($couponResult['dedupe_key'] ?? ''));

        return [
            'allowed' => true,
            'charged' => false,
            'coupon_used' => !empty($couponResult['processed']),
            'already_paid' => !empty($couponResult['already_redeemed']),
            'coupon_title' => (string) ($couponResult['coupon_title'] ?? ''),
            'asset_module' => $assetModuleValue,
            'asset_label' => '쿠폰',
            'amount' => 0,
            'message' => '',
        ];
    }

    $allocations = $amounts !== []
        ? sr_content_allocate_asset_use_by_amounts($pdo, $amounts, $accountId)
        : sr_content_allocate_asset_use($pdo, $assetModules, $accountId, $amount);
    if ($allocations === []) {
        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '선택한 자산 잔액이 부족해 콘텐츠를 열람할 수 없습니다.');
    }

    $dedupeKey = '';
    try {
        foreach ($allocations as $allocation) {
            $assetModule = (string) $allocation['asset_module'];
            $allocatedAmount = (int) $allocation['amount'];
            $assetOption = sr_content_asset_modules($pdo)[$assetModule];
            $dedupeKey = sr_content_asset_access_dedupe_key_for_policy($chargePolicy, 'content.view', $assetModule, $accountId, $pageId);
            $inserted = sr_content_insert_asset_access_placeholder($pdo, $pageId, $accountId, $assetModule, $allocatedAmount, $chargePolicy, $dedupeKey, 'content.view', null, 'view', sr_content_asset_group_policy_snapshot_json(isset($policyAmounts['snapshots'][$assetModule]) ? [$policyAmounts['snapshots'][$assetModule]] : []));
            if (!$inserted) {
                continue;
            }

            $transactionId = sr_content_create_asset_transaction($pdo, $assetModule, [
                'account_id' => $accountId,
                'amount' => -$allocatedAmount,
                'transaction_type' => (string) ($assetOption['use_type'] ?? 'use'),
                'reason' => '콘텐츠 열람',
                'reference_type' => 'content.view',
                'reference_id' => sr_content_asset_access_reference_id($pageId),
                'created_by_account_id' => null,
            ]);
            sr_content_update_asset_access_transaction($pdo, $dedupeKey, $transactionId);
            sr_content_grant_access_entitlement($pdo, $accountId, $pageId, 'content', $pageId, 'view', 'asset', $assetModule, $chargePolicy, $assetModule . ':' . (string) $transactionId);
        }
    } catch (Throwable $exception) {
        if ($dedupeKey !== '') {
            sr_content_delete_asset_access_placeholder($pdo, $dedupeKey);
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'content_asset_access_charge_failed');
        }

        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '회원 자산 차감에 실패해 콘텐츠를 열람할 수 없습니다.');
    }

    return sr_content_asset_access_result($pdo, true, true, $assetModuleValue, $amount);
}

function sr_content_try_coupon_access(PDO $pdo, int $pageId, int $accountId, string $chargePolicy = 'once'): array
{
    if ($pageId <= 0 || $accountId <= 0 || !sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return ['allowed' => false, 'processed' => false];
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_redeem_for_target')) {
        return ['allowed' => false, 'processed' => false];
    }

    $dedupeKey = 'content.view:coupon:' . (string) $accountId . ':' . (string) $pageId;
    if ($chargePolicy !== 'once') {
        $dedupeKey .= ':' . bin2hex(random_bytes(8));
    }

    $result = sr_coupon_redeem_for_target($pdo, $accountId, 'content', (string) $pageId, [
        'dedupe_key' => $dedupeKey,
        'reference_module' => 'content',
        'reference_type' => 'content.view',
        'reference_id' => (string) $pageId,
    ]);
    $result['dedupe_key'] = $dedupeKey;

    return $result;
}

function sr_content_file_download_required(array $file): bool
{
    return (int) ($file['asset_download_enabled'] ?? 0) === 1
        && (int) ($file['asset_download_amount'] ?? 0) > 0;
}

function sr_content_charge_file_download(PDO $pdo, array $file, int $accountId): array
{
    $pageId = (int) ($file['content_id'] ?? 0);
    $fileId = (int) ($file['id'] ?? 0);
    $assetModules = sr_content_asset_module_keys_from_value($file['asset_module'] ?? '');
    $assetModuleValue = sr_content_asset_module_value_from_keys($assetModules);
    $chargePolicy = (string) ($file['asset_charge_policy'] ?? 'once');
    $amounts = sr_content_asset_amounts_from_value($file['asset_download_amounts_json'] ?? '', $assetModules, (int) ($file['asset_download_amount'] ?? 0));
    $amount = $amounts !== [] ? sr_content_asset_amount_total($amounts) : (int) ($file['asset_download_amount'] ?? 0);

    if ($pageId <= 0 || $fileId <= 0 || $accountId <= 0 || !sr_content_file_download_required($file)) {
        return ['allowed' => true, 'charged' => false, 'message' => ''];
    }

    if ($assetModules === [] || !isset(sr_content_asset_download_charge_policies()[$chargePolicy])) {
        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '콘텐츠 파일 다운로드 설정이 올바르지 않아 다운로드할 수 없습니다.');
    }

    if (!sr_content_asset_modules_available($pdo, $assetModules)) {
        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '선택한 자산 모듈을 모두 사용할 수 없어 파일을 다운로드할 수 없습니다.');
    }

    $policyAmounts = sr_content_asset_amounts_with_group_policy($pdo, $accountId, $assetModules, $amounts, (int) ($file['asset_download_amount'] ?? 0), $file['asset_download_group_policies_json'] ?? '', (int) ($file['asset_download_policy_set_id'] ?? 0));
    $amounts = $policyAmounts['amounts'];
    $amount = (int) $policyAmounts['amount'];
    if ($amount <= 0) {
        $assetModule = (string) ($assetModules[0] ?? $assetModuleValue);
        $dedupeKey = sr_content_asset_access_dedupe_key_for_policy($chargePolicy, 'content.download', $assetModule, $accountId, $fileId, 'download');
        sr_content_insert_asset_access_placeholder($pdo, $pageId, $accountId, $assetModule, 0, $chargePolicy, $dedupeKey, 'content.download', (string) $fileId, 'download', sr_content_asset_group_policy_snapshot_json($policyAmounts['snapshots']));
        sr_content_grant_access_entitlement($pdo, $accountId, $pageId, 'content_file', $fileId, 'download', 'asset_group_policy', $assetModule, $chargePolicy, $dedupeKey);
        return sr_content_asset_access_result($pdo, true, false, $assetModuleValue, 0, '', ['group_policy_applied' => true]);
    }

    if ($chargePolicy === 'once' && sr_content_once_access_already_granted($pdo, $assetModules, $accountId, $fileId, 'download')) {
        return sr_content_asset_access_result($pdo, true, false, $assetModuleValue, $amount, '', ['already_paid' => true]);
    }

    $allocations = $amounts !== []
        ? sr_content_allocate_asset_use_by_amounts($pdo, $amounts, $accountId)
        : sr_content_allocate_asset_use($pdo, $assetModules, $accountId, $amount);
    if ($allocations === []) {
        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '선택한 자산 잔액이 부족해 파일을 다운로드할 수 없습니다.');
    }

    $dedupeKey = '';
    try {
        foreach ($allocations as $allocation) {
            $assetModule = (string) $allocation['asset_module'];
            $allocatedAmount = (int) $allocation['amount'];
            $assetOption = sr_content_asset_modules($pdo)[$assetModule];
            $dedupeKey = sr_content_asset_access_dedupe_key_for_policy($chargePolicy, 'content.download', $assetModule, $accountId, $fileId, 'download');
            $inserted = sr_content_insert_asset_access_placeholder($pdo, $pageId, $accountId, $assetModule, $allocatedAmount, $chargePolicy, $dedupeKey, 'content.download', (string) $fileId, 'download', sr_content_asset_group_policy_snapshot_json(isset($policyAmounts['snapshots'][$assetModule]) ? [$policyAmounts['snapshots'][$assetModule]] : []));
            if (!$inserted) {
                continue;
            }

            $transactionId = sr_content_create_asset_transaction($pdo, $assetModule, [
                'account_id' => $accountId,
                'amount' => -$allocatedAmount,
                'transaction_type' => (string) ($assetOption['use_type'] ?? 'use'),
                'reason' => '콘텐츠 파일 다운로드',
                'reference_type' => 'content.download',
                'reference_id' => (string) $fileId,
                'created_by_account_id' => null,
            ]);
            sr_content_update_asset_access_transaction($pdo, $dedupeKey, $transactionId);
            sr_content_grant_access_entitlement($pdo, $accountId, $pageId, 'content_file', $fileId, 'download', 'asset', $assetModule, $chargePolicy, $assetModule . ':' . (string) $transactionId);
        }
    } catch (Throwable $exception) {
        if ($dedupeKey !== '') {
            sr_content_delete_asset_access_placeholder($pdo, $dedupeKey);
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'content_file_download_charge_failed');
        }

        return sr_content_asset_access_result($pdo, false, false, $assetModuleValue, $amount, '회원 자산 차감에 실패해 파일을 다운로드할 수 없습니다.');
    }

    return sr_content_asset_access_result($pdo, true, true, $assetModuleValue, $amount);
}

function sr_content_asset_action_required(array $page): bool
{
    return (int) ($page['asset_action_enabled'] ?? 0) === 1
        && (int) ($page['asset_action_amount'] ?? 0) > 0;
}

function sr_content_asset_action_dedupe_key(string $assetModule, int $accountId, int $pageId): string
{
    return 'content.action:' . $assetModule . ':' . (string) $accountId . ':' . (string) $pageId . ':complete';
}

function sr_content_asset_action_log(PDO $pdo, string $dedupeKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_content_asset_action_logs
         WHERE dedupe_key = :dedupe_key
         LIMIT 1'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_has_completed_asset_action(PDO $pdo, string $assetModule, int $accountId, int $pageId): bool
{
    $log = sr_content_asset_action_log($pdo, sr_content_asset_action_dedupe_key($assetModule, $accountId, $pageId));

    return is_array($log) && ((int) ($log['transaction_id'] ?? 0) > 0 || (int) ($log['amount'] ?? -1) === 0);
}

function sr_content_has_completed_asset_action_for_modules(PDO $pdo, array $assetModules, int $accountId, int $pageId): bool
{
    foreach (sr_content_asset_module_keys_from_value($assetModules) as $assetModule) {
        if (sr_content_has_completed_asset_action($pdo, $assetModule, $accountId, $pageId)) {
            return true;
        }
    }

    return false;
}

function sr_content_insert_asset_action_placeholder(PDO $pdo, int $pageId, int $accountId, string $assetModule, string $direction, int $amount, string $dedupeKey, string $groupPolicySnapshotJson = ''): bool
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_content_asset_action_logs
            (content_id, account_id, asset_module, transaction_id, reference_type, reference_id, action_key, direction, amount, group_policy_snapshot_json, dedupe_key, created_at)
         VALUES
            (:content_id, :account_id, :asset_module, 0, :reference_type, :reference_id, :action_key, :direction, :amount, :group_policy_snapshot_json, :dedupe_key, :created_at)'
    );
    $stmt->execute([
        'content_id' => $pageId,
        'account_id' => $accountId,
        'asset_module' => $assetModule,
        'reference_type' => 'content.action',
        'reference_id' => (string) $pageId,
        'action_key' => 'complete',
        'direction' => $direction,
        'amount' => $amount,
        'group_policy_snapshot_json' => $groupPolicySnapshotJson,
        'dedupe_key' => $dedupeKey,
        'created_at' => sr_now(),
    ]);

    return $stmt->rowCount() > 0;
}

function sr_content_update_asset_action_transaction(PDO $pdo, string $dedupeKey, int $transactionId): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_content_asset_action_logs
         SET transaction_id = :transaction_id
         WHERE dedupe_key = :dedupe_key'
    );
    $stmt->execute([
        'transaction_id' => $transactionId,
        'dedupe_key' => $dedupeKey,
    ]);
}

function sr_content_delete_asset_action_placeholder(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM sr_content_asset_action_logs
         WHERE dedupe_key = :dedupe_key
           AND transaction_id = 0'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
}

function sr_content_run_asset_action(PDO $pdo, array $page, int $accountId): array
{
    $pageId = (int) ($page['id'] ?? 0);
    $assetModules = sr_content_asset_module_keys_from_value($page['asset_action_module'] ?? '');
    $assetModuleValue = sr_content_asset_module_value_from_keys($assetModules);
    $direction = (string) ($page['asset_action_direction'] ?? 'grant');
    $amounts = $direction === 'use'
        ? sr_content_asset_amounts_from_value($page['asset_action_amounts_json'] ?? '', $assetModules, (int) ($page['asset_action_amount'] ?? 0))
        : [];
    $amount = $amounts !== [] ? sr_content_asset_amount_total($amounts) : (int) ($page['asset_action_amount'] ?? 0);

    if ($pageId <= 0 || $accountId <= 0 || !sr_content_asset_action_required($page)) {
        return ['allowed' => false, 'completed' => false, 'message' => '콘텐츠 완료 버튼을 사용할 수 없습니다.'];
    }

    if ($assetModules === [] || !isset(sr_content_asset_action_directions()[$direction])) {
        return ['allowed' => false, 'completed' => false, 'message' => '콘텐츠 완료 버튼 설정이 올바르지 않습니다.'];
    }

    if (!sr_content_asset_modules_available($pdo, $assetModules)) {
        return [
            'allowed' => false,
            'completed' => false,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'message' => '선택한 자산 모듈을 모두 사용할 수 없어 완료 처리할 수 없습니다.',
        ];
    }

    if (sr_content_has_completed_asset_action_for_modules($pdo, $assetModules, $accountId, $pageId)) {
        return [
            'allowed' => true,
            'completed' => false,
            'already_completed' => true,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'message' => '이미 완료 처리되었습니다.',
        ];
    }

    $baseActionAmounts = $direction === 'use' ? $amounts : [(string) $assetModules[0] => $amount];
    $policyAmounts = sr_content_asset_amounts_with_group_policy($pdo, $accountId, $assetModules, $baseActionAmounts, $amount, $page['asset_action_group_policies_json'] ?? '', (int) ($page['asset_action_policy_set_id'] ?? 0));
    $amounts = $direction === 'use' ? $policyAmounts['amounts'] : [];
    $amount = (int) $policyAmounts['amount'];
    if ($amount <= 0) {
        $assetModule = (string) ($assetModules[0] ?? $assetModuleValue);
        $dedupeKey = sr_content_asset_action_dedupe_key($assetModule, $accountId, $pageId);
        sr_content_insert_asset_action_placeholder($pdo, $pageId, $accountId, $assetModule, $direction, 0, $dedupeKey, sr_content_asset_group_policy_snapshot_json($policyAmounts['snapshots']));
        return [
            'allowed' => true,
            'completed' => true,
            'group_policy_applied' => true,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue, $pdo),
            'amount' => 0,
            'direction' => $direction,
            'message' => '',
        ];
    }

    $allocations = $direction === 'use'
        ? ($amounts !== [] ? sr_content_allocate_asset_use_by_amounts($pdo, $amounts, $accountId) : sr_content_allocate_asset_use($pdo, $assetModules, $accountId, $amount))
        : [['asset_module' => $assetModules[0], 'amount' => $amount]];
    if ($direction === 'use' && $allocations === []) {
        return [
            'allowed' => false,
            'completed' => false,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'message' => '선택한 자산 잔액이 부족해 완료 처리할 수 없습니다.',
        ];
    }

    $dedupeKey = '';
    try {
        foreach ($allocations as $allocation) {
            $assetModule = (string) $allocation['asset_module'];
            $allocatedAmount = (int) $allocation['amount'];
            $dedupeKey = sr_content_asset_action_dedupe_key($assetModule, $accountId, $pageId);
            $inserted = sr_content_insert_asset_action_placeholder($pdo, $pageId, $accountId, $assetModule, $direction, $allocatedAmount, $dedupeKey, sr_content_asset_group_policy_snapshot_json(isset($policyAmounts['snapshots'][$assetModule]) ? [$policyAmounts['snapshots'][$assetModule]] : []));
            if (!$inserted) {
                continue;
            }

            $assetOption = sr_content_asset_modules($pdo)[$assetModule];
            $signedAmount = $direction === 'grant' ? $allocatedAmount : -$allocatedAmount;
            $transactionType = $direction === 'grant'
                ? (string) ($assetOption['credit_type'] ?? 'grant')
                : (string) ($assetOption['use_type'] ?? 'use');
            $transactionId = sr_content_create_asset_transaction($pdo, $assetModule, [
                'account_id' => $accountId,
                'amount' => $signedAmount,
                'transaction_type' => $transactionType,
                'reason' => '콘텐츠 완료 버튼 처리',
                'reference_type' => 'content.action',
                'reference_id' => (string) $pageId,
                'created_by_account_id' => null,
            ]);
            sr_content_update_asset_action_transaction($pdo, $dedupeKey, $transactionId);
        }
    } catch (Throwable $exception) {
        if ($dedupeKey !== '') {
            sr_content_delete_asset_action_placeholder($pdo, $dedupeKey);
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'content_asset_action_failed');
        }

        return [
            'allowed' => false,
            'completed' => false,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_content_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'message' => '회원 자산 처리에 실패했습니다.',
        ];
    }

    return [
        'allowed' => true,
        'completed' => true,
        'asset_module' => $assetModuleValue,
        'asset_label' => sr_content_asset_module_labels($assetModuleValue, $pdo),
        'amount' => $amount,
        'direction' => $direction,
        'message' => '',
    ];
}
