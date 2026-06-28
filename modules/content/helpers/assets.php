<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/content/helpers/asset-access.php';
require_once SR_ROOT . '/modules/content/helpers/asset-actions.php';

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

function sr_content_asset_policy_requires_confirmation(string $chargePolicy): bool
{
    return in_array($chargePolicy, ['once', 'every_view', 'every_download'], true);
}

function sr_content_asset_transaction_retry_max_attempts(): int
{
    return 3;
}

function sr_content_asset_is_retryable_transaction_exception(Throwable $exception): bool
{
    if (!$exception instanceof PDOException) {
        return false;
    }

    $sqlState = (string) $exception->getCode();
    $driverCode = isset($exception->errorInfo[1]) ? (int) $exception->errorInfo[1] : 0;

    return $sqlState === '40001' || in_array($driverCode, [1205, 1213], true);
}

function sr_content_asset_retry_operation(PDO $pdo, callable $operation): array
{
    if ($pdo->inTransaction()) {
        return $operation();
    }

    $maxAttempts = sr_content_asset_transaction_retry_max_attempts();
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            return $operation();
        } catch (Throwable $exception) {
            if ($attempt >= $maxAttempts || !sr_content_asset_is_retryable_transaction_exception($exception)) {
                throw $exception;
            }
            usleep(50000 * $attempt);
        }
    }

    throw new RuntimeException('콘텐츠 자산 처리 재시도 횟수를 초과했습니다.');
}

function sr_content_asset_confirmation_required_message(): string
{
    return sr_t('content::action.error.asset_confirmation_required');
}

function sr_content_asset_log_status_completed(): string
{
    return 'completed';
}

function sr_content_asset_log_status_pending(): string
{
    return 'pending';
}

function sr_content_asset_snapshot_schema_version(): string
{
    return 'asset_settlement_snapshot_v1';
}

function sr_content_asset_rounding_policy_version(): string
{
    return 'asset_settlement_rounding_v1';
}

function sr_content_asset_settlement_kind_for_use(int $amount, int $settlementAmount, string $purchasePowerSnapshotJson): string
{
    if ($settlementAmount > 0) {
        return 'paid';
    }

    if ($amount === 0) {
        return 'paid_settled_zero';
    }

    return $purchasePowerSnapshotJson !== '' ? 'paid' : 'legacy_unknown';
}

function sr_content_asset_settlement_kind_for_action(string $direction, int $amount, int $settlementAmount, string $purchasePowerSnapshotJson): string
{
    if ($direction !== 'use') {
        return 'free';
    }

    return sr_content_asset_settlement_kind_for_use($amount, $settlementAmount, $purchasePowerSnapshotJson);
}

function sr_content_asset_confirmation_session_key(string $accessKind, int $accountId, int $subjectId): string
{
    return $accessKind . ':' . (string) $accountId . ':' . (string) $subjectId;
}

function sr_content_asset_confirmation_signed_token(string $accessKind, int $accountId, int $subjectId, string $fingerprint): string
{
    if ($accountId < 1 || $subjectId < 1 || $fingerprint === '' || session_id() === '') {
        return '';
    }

    $appKey = sr_app_key(sr_runtime_config());
    if ($appKey === '') {
        return '';
    }

    return hash_hmac('sha256', implode('|', [
        'content.asset.confirmation.v2',
        $accessKind,
        (string) $accountId,
        (string) $subjectId,
        $fingerprint,
        session_id(),
    ]), $appKey);
}

function sr_content_asset_confirmation_request_token(string $accessKind, int $accountId, int $subjectId, string $fingerprint): string
{
    if ($accountId < 1 || $subjectId < 1 || $fingerprint === '') {
        return '';
    }

    $signedToken = sr_content_asset_confirmation_signed_token($accessKind, $accountId, $subjectId, $fingerprint);
    if ($signedToken !== '') {
        return $signedToken;
    }

    if (!isset($_SESSION['sr_content_asset_confirmation_requests']) || !is_array($_SESSION['sr_content_asset_confirmation_requests'])) {
        $_SESSION['sr_content_asset_confirmation_requests'] = [];
    }

    $key = sr_content_asset_confirmation_session_key($accessKind, $accountId, $subjectId);
    $session = is_array($_SESSION['sr_content_asset_confirmation_requests'][$key] ?? null) ? $_SESSION['sr_content_asset_confirmation_requests'][$key] : [];
    $createdAt = (int) ($session['created_at'] ?? 0);
    $sessionFingerprint = (string) ($session['fingerprint'] ?? '');
    $token = (string) ($session['token'] ?? '');
    if ($createdAt >= time() - 300 && $token !== '' && hash_equals($sessionFingerprint, $fingerprint)) {
        return $token;
    }

    $token = bin2hex(random_bytes(16));
    $_SESSION['sr_content_asset_confirmation_requests'][$key] = [
        'created_at' => time(),
        'fingerprint' => $fingerprint,
        'token' => $token,
    ];

    return $token;
}

function sr_content_asset_confirmation_request_token_valid(string $accessKind, int $accountId, int $subjectId, string $fingerprint, string $token): bool
{
    if ($token === '' || preg_match('/\A[a-f0-9]{32}(?:[a-f0-9]{32})?\z/', $token) !== 1) {
        return false;
    }

    $signedToken = sr_content_asset_confirmation_signed_token($accessKind, $accountId, $subjectId, $fingerprint);
    if ($signedToken !== '' && hash_equals($signedToken, $token)) {
        return true;
    }

    if (strlen($token) !== 32) {
        return false;
    }

    $key = sr_content_asset_confirmation_session_key($accessKind, $accountId, $subjectId);
    $sessions = is_array($_SESSION['sr_content_asset_confirmation_requests'] ?? null) ? $_SESSION['sr_content_asset_confirmation_requests'] : [];
    $session = isset($sessions[$key]) && is_array($sessions[$key]) ? $sessions[$key] : [];
    $createdAt = (int) ($session['created_at'] ?? 0);

    return $createdAt >= time() - 300
        && $fingerprint !== ''
        && hash_equals((string) ($session['fingerprint'] ?? ''), $fingerprint)
        && hash_equals((string) ($session['token'] ?? ''), $token);
}

function sr_content_asset_confirmation_fingerprint(string $accessKind, string $chargePolicy, string $assetModuleValue, int $amount, array $amounts = [], string $policySnapshotJson = ''): string
{
    ksort($amounts, SORT_STRING);
    $amountParts = [];
    foreach ($amounts as $assetModule => $assetAmount) {
        $amountParts[] = (string) $assetModule . ':' . (string) max(0, (int) $assetAmount);
    }

    return hash('sha256', implode('|', [$accessKind, $chargePolicy, $assetModuleValue, (string) max(0, $amount), implode(',', $amountParts), $policySnapshotJson]));
}

function sr_content_entry_access_context(PDO $pdo, array $page, ?array $account = null, string $instanceKey = ''): array
{
    $slug = (string) ($page['slug'] ?? '');
    $path = sr_content_slug_is_valid($slug) ? sr_content_path($slug) : '/content';
    $context = [
        'path' => $path,
        'url' => sr_url($path),
        'modal_id' => '',
        'access' => [],
        'confirmation_required' => false,
    ];

    if (!is_array($account) || !sr_content_asset_access_required($page)) {
        return $context;
    }

    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1) {
        return $context;
    }

    $access = sr_content_charge_view_access($pdo, $page, $accountId, false, '', 0, false);
    if ((string) ($access['error_key'] ?? '') !== 'asset_confirmation_required') {
        return $context;
    }

    $suffix = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $instanceKey);
    $suffix = is_string($suffix) ? trim($suffix, '_') : '';
    $modalId = 'content_entry_access_confirmation_' . (string) (int) ($page['id'] ?? 0);
    if ($suffix !== '') {
        $modalId .= '_' . $suffix;
    }

    $context['modal_id'] = $modalId;
    $context['access'] = $access;
    $context['confirmation_required'] = true;

    return $context;
}

function sr_content_entry_link_attributes(array $context, string $class = '', string $ariaLabel = ''): string
{
    $attributes = ' href="' . sr_e((string) ($context['url'] ?? '/content')) . '"';
    if ($class !== '') {
        $attributes .= ' class="' . sr_e($class) . '"';
    }
    if ($ariaLabel !== '') {
        $attributes .= ' aria-label="' . sr_e($ariaLabel) . '"';
    }

    $modalId = (string) ($context['modal_id'] ?? '');
    if (!empty($context['confirmation_required']) && $modalId !== '') {
        $attributes .= ' aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '"';
    }

    return $attributes;
}

function sr_content_entry_access_modal(PDO $pdo, array $page, array $context): string
{
    if (empty($context['confirmation_required']) || (string) ($context['modal_id'] ?? '') === '') {
        return '';
    }

    $access = is_array($context['access'] ?? null) ? $context['access'] : [];
    $assetConfirmationAssetLabel = (string) ($access['asset_label'] ?? '');
    $assetConfirmationAmount = (int) ($access['amount'] ?? 0);
    $assetConfirmationMessage = trim($assetConfirmationAssetLabel . ' ' . number_format($assetConfirmationAmount)) . ' 차감 후 콘텐츠를 열람하시겠습니까?';
    $assetConfirmationAction = (string) ($context['path'] ?? sr_content_path((string) ($page['slug'] ?? '')));
    $assetConfirmationId = 0;
    $assetConfirmationContentId = 0;
    $assetConfirmationRequestToken = (string) ($access['confirmation_request_token'] ?? '');
    $assetConfirmationTitle = '콘텐츠 열람 확인';
    $assetConfirmationSubmitLabel = sr_t('content::ui.text.ac5b575f');
    $assetConfirmationCouponIssues = is_array($access['coupon_issues'] ?? null) ? $access['coupon_issues'] : [];
    $assetConfirmationModalId = (string) $context['modal_id'];
    $assetConfirmationOpen = false;
    $assetConfirmationCancelUrl = '';
    $assetConfirmationCloseOnSubmit = false;

    ob_start();
    include SR_ROOT . '/modules/content/views/asset-confirmation-modal.php';

    return (string) ob_get_clean();
}

function sr_content_mark_asset_confirmation_session(string $accessKind, int $accountId, int $subjectId, string $fingerprint): void
{
    if ($accountId < 1 || $subjectId < 1 || $fingerprint === '' || !in_array($accessKind, ['view', 'download'], true)) {
        return;
    }

    if (!isset($_SESSION['sr_content_asset_confirmations']) || !is_array($_SESSION['sr_content_asset_confirmations'])) {
        $_SESSION['sr_content_asset_confirmations'] = [];
    }

    $_SESSION['sr_content_asset_confirmations'][sr_content_asset_confirmation_session_key($accessKind, $accountId, $subjectId)] = [
        'created_at' => time(),
        'fingerprint' => $fingerprint,
    ];
}

function sr_content_consume_asset_confirmation_session(string $accessKind, int $accountId, int $subjectId, string $fingerprint): bool
{
    $key = sr_content_asset_confirmation_session_key($accessKind, $accountId, $subjectId);
    $sessions = is_array($_SESSION['sr_content_asset_confirmations'] ?? null) ? $_SESSION['sr_content_asset_confirmations'] : [];
    $session = isset($sessions[$key]) && is_array($sessions[$key]) ? $sessions[$key] : [];
    $createdAt = (int) ($session['created_at'] ?? 0);
    $sessionFingerprint = (string) ($session['fingerprint'] ?? '');
    unset($_SESSION['sr_content_asset_confirmations'][$key]);

    return $createdAt > 0
        && $createdAt >= time() - 300
        && $fingerprint !== ''
        && hash_equals($sessionFingerprint, $fingerprint);
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
    return isset($options[$assetModule]) ? (string) $options[$assetModule]['label'] : '포인트/금액';
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

function sr_content_asset_single_amount_input_group_html(string $fieldName, int $amount, array $assetModuleOptions, string $assetModule, string $label, string $id = '', bool $hidden = false, string $sourceFieldName = '', string $inputAttributes = ''): string
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

    return '<div class="input-group admin-asset-single-amount-group" data-admin-asset-unit-group' . $unitSourceAttribute . $unitOptionsAttribute . $hiddenAttribute . '>'
        . '<input' . $idAttribute . ' type="text" inputmode="numeric" pattern="[0-9,]*" name="' . sr_e($fieldName) . '" value="' . sr_e((string) max(0, $amount)) . '" class="form-input admin-asset-setting-amount" aria-label="' . sr_e($label) . '" data-admin-asset-amount-input' . $inputAttributes . '>'
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

    return $labels !== [] ? implode(', ', $labels) : '포인트/금액';
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

    if (sr_content_asset_policy_set_ids_from_value($value) !== []) {
        return [];
    }

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

function sr_content_asset_policy_set_key_is_valid(string $setKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $setKey) === 1;
}

function sr_content_asset_policy_set_statuses(): array
{
    return ['enabled', 'disabled', 'archived'];
}

function sr_content_asset_policy_set_sort_options(): array
{
    return [
        'title' => ['columns' => ['title', 'id']],
        'set_key' => ['columns' => ['set_key', 'id']],
        'status' => ['columns' => ['status', 'title', 'id']],
        'updated_at' => ['columns' => ['updated_at', 'id']],
        'created_at' => ['columns' => ['created_at', 'id']],
    ];
}

function sr_content_asset_policy_set_default_sort(): array
{
    return sr_admin_sort_default('title', 'asc');
}

function sr_content_asset_policy_sets(PDO $pdo, bool $enabledOnly = false, array $sort = []): array
{
    try {
        $sql = 'SELECT * FROM sr_content_asset_policy_sets';
        if ($enabledOnly) {
            $sql .= " WHERE status = 'enabled'";
        }
        $sql .= function_exists('sr_admin_sort_order_sql')
            ? sr_admin_sort_order_sql(sr_content_asset_policy_set_sort_options(), $sort, sr_content_asset_policy_set_default_sort())
            : ' ORDER BY title ASC, id ASC';
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

function sr_content_asset_policy_set_ids_from_value(mixed $value): array
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

function sr_content_asset_policy_set_ids_with_legacy(mixed $value, int $legacySetId = 0): array
{
    $setIds = sr_content_asset_policy_set_ids_from_value($value);
    if ($setIds === [] && $legacySetId > 0) {
        $setIds[] = $legacySetId;
    }

    return $setIds;
}

function sr_content_asset_policy_set_selection_json_from_ids(array $setIds): string
{
    $setIds = sr_content_asset_policy_set_ids_from_value($setIds);
    if ($setIds === []) {
        return '';
    }

    $json = json_encode(['policy_set_ids' => $setIds], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '';
}

function sr_content_asset_policy_set_first_id(array $setIds): int
{
    $setIds = sr_content_asset_policy_set_ids_from_value($setIds);
    return (int) ($setIds[0] ?? 0);
}

function sr_content_asset_policy_set_options(array $policySets): array
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

function sr_content_asset_policy_set_picker_options(array $policySets, string $operation = 'neutral', ?PDO $pdo = null): array
{
    $operation = in_array($operation, ['grant', 'use', 'neutral'], true) ? $operation : 'neutral';
    $options = [];
    foreach ($policySets as $policySet) {
        $setId = (int) ($policySet['id'] ?? 0);
        if ($setId < 1) {
            continue;
        }

        $summaries = [
            'grant' => sr_content_asset_policy_set_summary($policySet, 'grant', [], $pdo),
            'use' => sr_content_asset_policy_set_summary($policySet, 'use', [], $pdo),
            'neutral' => sr_content_asset_policy_set_summary($policySet, 'neutral', [], $pdo),
        ];
        $assetModules = sr_content_asset_policy_set_asset_modules($policySet);
        foreach ($assetModules as $assetModule) {
            foreach (['grant', 'use', 'neutral'] as $summaryOperation) {
                $summaries[$summaryOperation . '_' . $assetModule] = sr_content_asset_policy_set_summary($policySet, $summaryOperation, [$assetModule], $pdo);
            }
        }

        $options[(string) $setId] = [
            'label' => (string) (sr_content_asset_policy_set_options([$policySet])[(string) $setId] ?? $setId),
            'summary' => sr_content_asset_policy_set_summary($policySet, $operation, [], $pdo),
            'summaries' => $summaries,
            'assets' => $assetModules,
        ];
    }

    return $options;
}

function sr_content_asset_policy_set_asset_modules(array $policySet): array
{
    sr_content_require_asset_group_policy_helpers();
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
        $assetModule = sr_content_clean_slug((string) ($policy['asset_module'] ?? ''));
        if ($assetModule !== '' && isset(sr_content_asset_modules()[$assetModule])) {
            $assetModules[$assetModule] = true;
        }
    }

    $ordered = [];
    foreach (sr_content_asset_deduction_order() as $assetModule) {
        if (isset($assetModules[$assetModule])) {
            $ordered[] = $assetModule;
        }
    }

    return $ordered;
}

function sr_content_asset_policy_set_summary(array $policySet, string $operation = 'neutral', array $assetModules = [], ?PDO $pdo = null): string
{
    sr_content_require_asset_group_policy_helpers();
    $operation = in_array($operation, ['grant', 'use', 'neutral'], true) ? $operation : 'neutral';
    $assetModules = sr_content_asset_module_keys_from_value($assetModules);
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
        $policyAssetModule = sr_content_clean_slug((string) ($policy['asset_module'] ?? ''));
        if ($assetFilter !== [] && $policyAssetModule !== '' && !isset($assetFilter[$policyAssetModule])) {
            continue;
        }
        $eligibleCount += 1;
        $assetModule = $policyAssetModule !== '' ? $policyAssetModule : (string) ($assetModules[0] ?? '');
        $assetLabel = $assetModule !== '' ? sr_content_asset_module_label($assetModule, $pdo) : '전체 항목';
        $mode = (string) ($policy['mode'] ?? '');
        $modeLabel = function_exists('sr_admin_asset_group_policy_mode_label') ? sr_admin_asset_group_policy_mode_label($mode) : $mode;
        $value = sr_admin_asset_group_policy_value_for_asset($policy, $assetModule);
        $valueLabel = $value !== '' ? ' ' . sr_content_asset_policy_set_summary_value_label($value, $mode, $operation) : '';
        $summaries[] = trim($groupKey . ': ' . $assetLabel . ' ' . $modeLabel . $valueLabel);
        if (count($summaries) >= 3) {
            break;
        }
    }

    if ($summaries === []) {
        return '활성 그룹별 적용 없음';
    }

    $remaining = max(0, $eligibleCount - count($summaries));
    return implode(' / ', $summaries) . ($remaining > 0 ? ' 외 ' . (string) $remaining . '개' : '');
}

function sr_content_asset_policy_set_summary_value_label(string $value, string $mode, string $operation = 'neutral'): string
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

function sr_content_asset_policy_set_checkboxes_html(string $id, string $name, array $policySets, array $selectedIds, string $operation = 'neutral', string $summarySourceSelector = '', string $assetSourceSelector = '', ?PDO $pdo = null): string
{
    $rootAttributes = '';
    if (in_array($operation, ['grant', 'use', 'neutral'], true)) {
        $rootAttributes .= ' data-admin-select-badge-summary-default="' . sr_e($operation) . '"';
    }
    if ($summarySourceSelector !== '') {
        $rootAttributes .= ' data-admin-select-badge-summary-source="' . sr_e($summarySourceSelector) . '"';
    }
    if ($assetSourceSelector !== '') {
        $rootAttributes .= ' data-admin-select-badge-asset-source="' . sr_e($assetSourceSelector) . '"';
    }
    return sr_admin_select_badge_list_html($id, $name, sr_content_asset_policy_set_picker_options($policySets, $operation, $pdo), array_map('strval', sr_content_asset_policy_set_ids_from_value($selectedIds)), '등록된 회원 그룹별 적용 없음', '그룹 선택', $rootAttributes);
}

function sr_content_asset_policy_set_ids_validation_errors(PDO $pdo, array $setIds, string $label): array
{
    $errors = [];
    foreach (sr_content_asset_policy_set_ids_from_value($setIds) as $setId) {
        if (!is_array(sr_content_asset_policy_set_by_id($pdo, (int) $setId))) {
            $errors[] = $label . ' 회원 그룹별 적용을 찾을 수 없습니다.';
            break;
        }
    }

    return $errors;
}

function sr_content_asset_policy_set_asset_match_errors(PDO $pdo, array $setIds, array $assetModules, string $label): array
{
    $errors = [];
    $assetModules = sr_content_asset_module_keys_from_value($assetModules);
    if ($assetModules === []) {
        return $errors;
    }
    $assetMap = array_fill_keys($assetModules, true);
    foreach (sr_content_asset_policy_set_ids_from_value($setIds) as $setId) {
        $policySet = sr_content_asset_policy_set_by_id($pdo, (int) $setId);
        if (!is_array($policySet)) {
            continue;
        }
        $policyAssets = sr_content_asset_policy_set_asset_modules($policySet);
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

function sr_content_asset_grouped_amount_inputs_html(string $id, string $moduleFieldName, string $amountFieldName, array $assetModuleOptions, array $selectedAssetModules, mixed $amountsValue, int $fallbackAmount, string $labelPrefix, string $emptyLabel, string $singleWhenSelector = '', string $singleWhenValue = ''): string
{
    $amounts = sr_content_asset_amount_input_values($amountsValue, $selectedAssetModules, $fallbackAmount);
    $selectedMap = [];
    foreach ($selectedAssetModules as $selectedAssetModule) {
        $selectedMap[(string) $selectedAssetModule] = true;
    }

    $idBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($id));
    $idBase = is_string($idBase) && $idBase !== '' ? $idBase : 'content_asset_amounts';
    $singleAttributes = '';
    if ($singleWhenSelector !== '' && $singleWhenValue !== '') {
        $singleAttributes = ' data-admin-asset-single-when-selector="' . sr_e($singleWhenSelector) . '" data-admin-asset-single-when-value="' . sr_e($singleWhenValue) . '"';
    }
    $html = '<div id="' . sr_e($id) . '" class="admin-asset-amount-grid admin-asset-grouped-amount-grid" role="group" data-admin-asset-amount-sync' . $singleAttributes . '>';
    if ($assetModuleOptions === []) {
        return $html . '<span class="form-help">' . sr_e($emptyLabel) . '</span></div>';
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

function sr_content_asset_amount_allocations(array $amounts): array
{
    $allocations = [];
    foreach (sr_content_asset_deduction_order() as $assetModule) {
        $amount = (int) ($amounts[$assetModule] ?? 0);
        if ($amount > 0) {
            $allocations[] = [
                'asset_module' => $assetModule,
                'amount' => $amount,
            ];
        }
    }

    return $allocations;
}

function sr_content_asset_settlement_currency(PDO $pdo, array $source = []): string
{
    $currency = (string) ($source['asset_settlement_currency'] ?? '');
    if ($currency === '') {
        $currency = function_exists('sr_site_default_currency') ? sr_site_default_currency($pdo) : 'KRW';
    }
    $currency = function_exists('sr_normalize_currency_code') ? sr_normalize_currency_code($currency) : strtoupper(trim($currency));

    return function_exists('sr_currency_is_known') && sr_currency_is_known($currency) ? $currency : 'KRW';
}

function sr_content_asset_purchase_power_snapshot_json(array $snapshot): string
{
    $encoded = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($encoded) ? $encoded : '{}';
}

function sr_content_allocate_asset_settlement_use(PDO $pdo, array $assetModules, int $accountId, int $settlementAmount, string $settlementCurrency): array
{
    require_once SR_ROOT . '/modules/member/helpers/assets.php';

    $assets = sr_content_asset_modules($pdo);
    $plan = sr_member_asset_settlement_plan(
        $pdo,
        $assets,
        static function (PDO $pdo, string $assetModule) use ($accountId): int {
            return sr_content_asset_balance($pdo, $assetModule, $accountId);
        },
        sr_content_asset_module_keys_from_value($assetModules),
        $settlementAmount,
        $settlementCurrency
    );

    return !empty($plan['ok']) ? (array) ($plan['allocations'] ?? []) : [];
}

function sr_content_asset_balance_shortage_message(PDO $pdo, array $assetModules, int $accountId, int $settlementAmount, string $settlementCurrency, string $suffix, string $fallbackMessage): string
{
    require_once SR_ROOT . '/modules/member/helpers/assets.php';

    $shortage = sr_member_asset_settlement_shortage(
        $pdo,
        sr_content_asset_modules($pdo),
        static function (PDO $pdo, string $assetModule) use ($accountId): int {
            return sr_content_asset_balance($pdo, $assetModule, $accountId);
        },
        sr_content_asset_module_keys_from_value($assetModules),
        $settlementAmount,
        $settlementCurrency
    );

    return sr_member_asset_balance_shortage_message($shortage, $suffix, $fallbackMessage);
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
    $actionAmounts = sr_content_asset_amounts_from_value($values['asset_action_amounts_json'] ?? '', sr_content_asset_module_keys_from_value($actionModule), (int) ($values['asset_action_amount'] ?? 0));

    $values['asset_action_enabled'] = (int) ($values['asset_action_enabled'] ?? 0) === 1 ? 1 : 0;
    $values['asset_action_module'] = $actionModule;
    $values['asset_action_amount'] = sr_content_asset_amount_total($actionAmounts, max(0, (int) ($values['asset_action_amount'] ?? 0)));
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
