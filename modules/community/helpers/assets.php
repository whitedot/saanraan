<?php

declare(strict_types=1);

function sr_community_asset_modules(): array
{
    return [
        'point' => [
            'label' => sr_t('community::asset.point'),
            'module_key' => 'point',
            'helper' => SR_ROOT . '/modules/point/helpers.php',
            'balance_function' => 'sr_point_balance',
            'transaction_function' => 'sr_point_create_transaction',
            'use_type' => 'use',
            'credit_type' => 'grant',
            'refund_type' => 'refund',
        ],
        'reward' => [
            'label' => sr_t('community::asset.reward'),
            'module_key' => 'reward',
            'helper' => SR_ROOT . '/modules/reward/helpers.php',
            'balance_function' => 'sr_reward_balance',
            'transaction_function' => 'sr_reward_create_transaction',
            'use_type' => 'use',
            'credit_type' => 'grant',
            'refund_type' => 'refund',
        ],
        'deposit' => [
            'label' => sr_t('community::asset.deposit'),
            'module_key' => 'deposit',
            'helper' => SR_ROOT . '/modules/deposit/helpers.php',
            'balance_function' => 'sr_deposit_balance',
            'transaction_function' => 'sr_deposit_create_transaction',
            'use_type' => 'use',
            'credit_type' => 'deposit',
            'refund_type' => 'refund',
        ],
    ];
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
    $modules = sr_community_asset_modules();
    if (!isset($modules[$assetModule])) {
        return false;
    }

    $module = $modules[$assetModule];
    $helper = (string) ($module['helper'] ?? '');
    if (!sr_module_enabled($pdo, (string) ($module['module_key'] ?? '')) || !is_file($helper)) {
        return false;
    }

    require_once $helper;

    return function_exists((string) ($module['balance_function'] ?? ''))
        && function_exists((string) ($module['transaction_function'] ?? ''));
}

function sr_community_asset_module_options(PDO $pdo): array
{
    $available = [];
    foreach (sr_community_asset_modules() as $assetModule => $module) {
        if (sr_community_asset_module_is_available($pdo, (string) $assetModule)) {
            $available[$assetModule] = $module;
        }
    }

    return $available;
}

function sr_community_asset_module_label(string $assetModule): string
{
    $modules = sr_community_asset_modules();
    return isset($modules[$assetModule]) ? sr_t('community::asset.' . $assetModule) : sr_t('community::asset.member_asset');
}

function sr_community_asset_module_key(string $value): string
{
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
    return ['point', 'reward', 'deposit'];
}

function sr_community_asset_module_keys_from_value(mixed $value): array
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

    return $ordered !== [] ? $ordered : ['point'];
}

function sr_community_asset_module_value_from_keys(array $assetModules): string
{
    return implode(',', sr_community_asset_module_keys_from_value($assetModules));
}

function sr_community_asset_module_labels(string $assetModuleValue): string
{
    $labels = [];
    foreach (sr_community_asset_module_keys_from_value($assetModuleValue) as $assetModule) {
        $labels[] = sr_community_asset_module_label($assetModule);
    }

    return $labels !== [] ? implode(', ', $labels) : sr_t('community::asset.member_asset');
}

function sr_community_asset_modules_available(PDO $pdo, array $assetModules): bool
{
    foreach (sr_community_asset_module_keys_from_value($assetModules) as $assetModule) {
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
    ];
    if (sr_community_asset_prefix_uses_composite($prefix)) {
        $keys[] = $prefix . '_amounts_json';
    }

    if (in_array($prefix, ['paid_read', 'paid_attachment_download'], true)) {
        $keys[] = $prefix . '_charge_policy';
    }

    return $keys;
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
        $moduleValue = (string) ($settings[$assetPrefix . '_asset_module'] ?? 'point');
        $auditSettings[$assetPrefix . '_enabled'] = sr_community_asset_bool_value_for_audit($settings[$assetPrefix . '_enabled'] ?? false);
        $auditSettings[$assetPrefix . '_asset_module'] = sr_community_asset_prefix_uses_composite($assetPrefix)
            ? sr_community_asset_module_value_from_keys(sr_community_asset_module_keys_from_value($moduleValue))
            : sr_community_asset_module_key($moduleValue);
        $auditSettings[$assetPrefix . '_amount'] = max(0, (int) ($settings[$assetPrefix . '_amount'] ?? 0));
        if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
            $auditSettings[$assetPrefix . '_amounts_json'] = sr_community_asset_amounts_json_from_map(
                sr_community_asset_amounts_from_value(
                    $settings[$assetPrefix . '_amounts_json'] ?? '',
                    sr_community_asset_module_keys_from_value($auditSettings[$assetPrefix . '_asset_module']),
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
        $auditSettings['post_reward_reversal_enabled'] = sr_community_asset_bool_value_for_audit($settings['post_reward_reversal_enabled'] ?? true);
        $auditSettings['comment_reward_reversal_enabled'] = sr_community_asset_bool_value_for_audit($settings['comment_reward_reversal_enabled'] ?? true);
    }

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
            str_ends_with((string) $assetSettingKey, '_asset_module') ? 'point' : '0'
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

    $module = sr_community_asset_modules()[$assetModule];
    $balanceFunction = (string) $module['balance_function'];

    return (int) $balanceFunction($pdo, $accountId);
}

function sr_community_asset_combined_balance(PDO $pdo, array $assetModules, int $accountId): int
{
    $balance = 0;
    foreach (sr_community_asset_module_keys_from_value($assetModules) as $assetModule) {
        $balance += sr_community_asset_balance($pdo, $assetModule, $accountId);
    }

    return $balance;
}

function sr_community_allocate_asset_use(PDO $pdo, array $assetModules, int $accountId, int $amount): array
{
    $remaining = max(0, $amount);
    $allocations = [];
    foreach (sr_community_asset_module_keys_from_value($assetModules) as $assetModule) {
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
            $amounts[$assetModule] = min(999999999, max(0, (int) $amount));
        }
    }

    $ordered = [];
    foreach (sr_community_asset_module_keys_from_value($assetModules) as $assetModule) {
        $amount = $amounts[$assetModule] ?? 0;
        if ($amount <= 0 && $fallbackAmount > 0 && count(sr_community_asset_module_keys_from_value($assetModules)) === 1) {
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
        $firstModule = sr_community_asset_module_keys_from_value($assetModules)[0] ?? '';
        if ($firstModule !== '') {
            $amounts[$firstModule] = $fallbackAmount;
        }
    }

    return $amounts;
}

function sr_community_asset_amount_inputs_html(string $fieldName, array $assetModuleOptions, array $selectedAssetModules, mixed $amountsValue, int $fallbackAmount, string $labelPrefix): string
{
    $amounts = sr_community_asset_amount_input_values($amountsValue, $selectedAssetModules, $fallbackAmount);
    $html = '<div class="admin-asset-amount-grid">';
    foreach ($assetModuleOptions as $assetModule => $assetOption) {
        $assetModule = (string) $assetModule;
        $label = (string) ($assetOption['label'] ?? sr_community_asset_module_label($assetModule));
        $html .= '<label class="admin-asset-amount-field">'
            . '<span>' . sr_e($label) . '</span>'
            . '<input type="number" name="' . sr_e($fieldName) . '[' . sr_e($assetModule) . ']" min="0" max="999999999" step="1" value="' . sr_e((string) (int) ($amounts[$assetModule] ?? 0)) . '" class="form-input admin-asset-setting-amount" aria-label="' . sr_e($labelPrefix . ' ' . $label) . '">'
            . '</label>';
    }
    $html .= '</div>';

    return $html;
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

function sr_community_asset_use_balance_available(PDO $pdo, array $config, int $accountId): bool
{
    $assetModules = sr_community_asset_module_keys_from_value($config['asset_module'] ?? '');
    $amounts = is_array($config['amounts'] ?? null) ? $config['amounts'] : [];
    if ($amounts !== []) {
        return sr_community_allocate_asset_use_by_amounts($pdo, $amounts, $accountId) !== [];
    }

    return sr_community_asset_combined_balance($pdo, $assetModules, $accountId) >= (int) ($config['amount'] ?? 0);
}

function sr_community_create_asset_transaction(PDO $pdo, string $assetModule, array $data): int
{
    if (!sr_community_asset_module_is_available($pdo, $assetModule)) {
        throw new RuntimeException('Community asset module is not available.');
    }

    $module = sr_community_asset_modules()[$assetModule];
    $transactionFunction = (string) $module['transaction_function'];

    return (int) $transactionFunction($pdo, $data);
}

function sr_community_asset_board_setting(PDO $pdo, array $board, array $settings, string $key, mixed $default): string
{
    $boardId = (int) ($board['id'] ?? 0);
    $prefix = sr_community_asset_prefix_from_setting_key($key);
    $source = 'all';
    if ($boardId > 0 && $prefix !== '') {
        $source = is_string($board['source_' . $key] ?? null)
            ? sr_community_normalize_board_setting_source((string) $board['source_' . $key])
            : sr_community_board_asset_setting_key_source($pdo, $boardId, $key);
    }
    if ($source === 'group') {
        $groupId = (int) ($board['board_group_id'] ?? 0);
        if ($groupId > 0) {
            $value = sr_community_board_group_setting_value($pdo, $groupId, $key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
    }

    if ($boardId > 0 && $source === 'board') {
        $value = sr_community_board_setting_value($pdo, $boardId, $key);
        if (is_string($value) && $value !== '') {
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
    $assetModuleValue = sr_community_asset_board_setting($pdo, $board, $settings, $prefix . '_asset_module', 'point');
    $assetModule = sr_community_asset_prefix_uses_composite($prefix)
        ? sr_community_asset_module_value_from_keys(sr_community_asset_module_keys_from_value($assetModuleValue))
        : sr_community_asset_module_key((string) $assetModuleValue);
    $amount = sr_community_asset_amount_config($pdo, $board, $settings, $prefix . '_amount');
    $amounts = sr_community_asset_prefix_uses_composite($prefix)
        ? sr_community_asset_amounts_from_value(
            sr_community_asset_board_setting($pdo, $board, $settings, $prefix . '_amounts_json', ''),
            sr_community_asset_module_keys_from_value($assetModule),
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
        'asset_modules' => sr_community_asset_module_keys_from_value($assetModule),
        'amount' => $amount,
        'amounts' => $amounts,
        'charge_policy' => $policy,
    ];
}

function sr_community_asset_event_required(array $config): bool
{
    return !empty($config['enabled']) && (int) ($config['amount'] ?? 0) > 0;
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

    return isset($sessions[$key]);
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

    return is_array($log) && (int) ($log['transaction_id'] ?? 0) > 0;
}

function sr_community_has_asset_event_for_modules(PDO $pdo, array $assetModules, int $accountId, string $eventKey, int $subjectId): bool
{
    foreach (sr_community_asset_module_keys_from_value($assetModules) as $assetModule) {
        if (sr_community_has_asset_event($pdo, $assetModule, $accountId, $eventKey, $subjectId)) {
            return true;
        }
    }

    return false;
}

function sr_community_insert_asset_log_placeholder(PDO $pdo, array $row): bool
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_community_asset_logs
            (account_id, asset_module, transaction_id, reference_type, reference_id, subject_type, subject_id, event_key, direction, charge_policy, amount, dedupe_key, created_at)
         VALUES
            (:account_id, :asset_module, 0, :reference_type, :reference_id, :subject_type, :subject_id, :event_key, :direction, :charge_policy, :amount, :dedupe_key, :created_at)'
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
        'dedupe_key' => (string) $row['dedupe_key'],
        'created_at' => sr_now(),
    ]);

    return $stmt->rowCount() > 0;
}

function sr_community_update_asset_log_transaction(PDO $pdo, string $dedupeKey, int $transactionId): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_community_asset_logs
         SET transaction_id = :transaction_id
         WHERE dedupe_key = :dedupe_key'
    );
    $stmt->execute([
        'transaction_id' => $transactionId,
        'dedupe_key' => $dedupeKey,
    ]);
}

function sr_community_delete_asset_log_placeholder(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM sr_community_asset_logs
         WHERE dedupe_key = :dedupe_key
           AND transaction_id = 0'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
}

function sr_community_run_asset_event(PDO $pdo, array $config, int $accountId, string $eventKey, string $subjectType, int $subjectId, string $direction, string $reason): array
{
    $assetModules = sr_community_asset_module_keys_from_value($config['asset_module'] ?? '');
    $assetModuleValue = sr_community_asset_module_value_from_keys($assetModules);
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
            'asset_label' => sr_community_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => sr_t('community::action.error.asset_modules_unavailable'),
        ];
    }

    $once = in_array($chargePolicy, ['once'], true) || in_array($direction, ['grant', 'refund'], true);
    if ($once && sr_community_has_asset_event_for_modules($pdo, $assetModules, $accountId, $eventKey, $subjectId)) {
        return [
            'allowed' => true,
            'processed' => false,
            'already_processed' => true,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => '',
        ];
    }

    $allocations = $direction === 'use'
        ? ($amounts !== [] ? sr_community_allocate_asset_use_by_amounts($pdo, $amounts, $accountId) : sr_community_allocate_asset_use($pdo, $assetModules, $accountId, $amount))
        : [['asset_module' => $assetModules[0], 'amount' => $amount]];
    if ($direction === 'use' && $allocations === []) {
        return [
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_balance_low',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => sr_t('community::action.error.asset_balance_low'),
        ];
    }

    $processed = false;
    $dedupeKey = '';
    try {
        foreach ($allocations as $allocation) {
            $assetModule = (string) $allocation['asset_module'];
            $allocatedAmount = (int) $allocation['amount'];
            $module = sr_community_asset_modules()[$assetModule];
            $dedupeKey = $once
                ? sr_community_asset_dedupe_key($assetModule, $accountId, $eventKey, $subjectId)
                : 'community.' . $eventKey . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId . ':' . bin2hex(random_bytes(8));
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
                'dedupe_key' => $dedupeKey,
            ]);
            if (!$inserted) {
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
            $processed = true;
        }
    } catch (Throwable $exception) {
        if ($dedupeKey !== '') {
            sr_community_delete_asset_log_placeholder($pdo, $dedupeKey);
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'community_asset_event_failed');
        }

        return [
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_processing_failed',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue),
            'amount' => $amount,
            'message' => sr_t('community::action.error.asset_processing_failed'),
        ];
    }

    return [
        'allowed' => true,
        'processed' => $processed,
        'asset_module' => $assetModuleValue,
        'asset_label' => sr_community_asset_module_labels($assetModuleValue),
        'amount' => $amount,
        'direction' => $direction,
        'message' => '',
    ];
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
