<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/helpers.php';

function sr_identity_verification_default_settings(): array
{
    return [
        'enabled' => false,
        'default_provider_key' => '',
        'attempt_ttl_seconds' => 600,
        'result_valid_days' => 365,
        'require_https' => true,
    ];
}

function sr_identity_verification_settings(PDO $pdo): array
{
    $settings = array_merge(sr_identity_verification_default_settings(), sr_module_settings($pdo, 'identity_verification'));
    $settings['enabled'] = sr_truthy($settings['enabled'] ?? false);
    $settings['default_provider_key'] = sr_identity_verification_provider_key((string) ($settings['default_provider_key'] ?? ''));
    $settings['attempt_ttl_seconds'] = min(3600, max(60, (int) ($settings['attempt_ttl_seconds'] ?? 600)));
    $settings['result_valid_days'] = min(3650, max(0, (int) ($settings['result_valid_days'] ?? 365)));
    $settings['require_https'] = sr_truthy($settings['require_https'] ?? true);

    return $settings;
}

function sr_identity_verification_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'identity_verification' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('Identity verification module is not installed.');
    }

    $save = $pdo->prepare(
        'INSERT INTO sr_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    $now = sr_now();
    foreach ($settings as $key => $value) {
        if (!is_string($key) || preg_match('/\A[a-z][a-z0-9_]{1,120}\z/', $key) !== 1) {
            continue;
        }
        $valueType = is_bool($value) ? 'bool' : (is_int($value) ? 'int' : 'string');
        $save->execute([
            'module_id' => (int) $module['id'],
            'setting_key' => $key,
            'setting_value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            'value_type' => $valueType,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    sr_clear_module_settings_cache('identity_verification');
}

function sr_identity_verification_provider_key(string $providerKey): string
{
    $providerKey = strtolower(trim($providerKey));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $providerKey) === 1 ? $providerKey : '';
}

function sr_identity_verification_purpose(string $purpose): string
{
    $purpose = strtolower(trim($purpose));
    return preg_match('/\A[a-z][a-z0-9_.]{1,79}\z/', $purpose) === 1 ? $purpose : '';
}

function sr_identity_verification_setting_key(string $providerKey, string $settingKey): string
{
    $providerKey = sr_identity_verification_provider_key($providerKey);
    if ($providerKey === '' || preg_match('/\A[a-z][a-z0-9_]{1,80}\z/', $settingKey) !== 1) {
        return '';
    }

    return 'provider_' . $providerKey . '_' . $settingKey;
}

function sr_identity_verification_available(PDO $pdo): bool
{
    $settings = sr_identity_verification_settings($pdo);
    if (empty($settings['enabled'])) {
        return false;
    }

    return sr_identity_verification_select_provider($pdo) !== null;
}

function sr_identity_verification_account_satisfies(PDO $pdo, int $accountId, string $purpose, ?int $maxAgeDays = null): bool
{
    $purpose = sr_identity_verification_purpose($purpose);
    if ($accountId <= 0 || $purpose === '') {
        return false;
    }

    $params = [
        'account_id' => $accountId,
        'purpose' => $purpose,
        'now' => sr_now(),
    ];
    $ageSql = '';
    if ($maxAgeDays !== null && $maxAgeDays > 0) {
        $ageSql = ' AND r.verified_at >= :min_verified_at';
        $params['min_verified_at'] = gmdate('Y-m-d H:i:s', time() - ($maxAgeDays * 86400));
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM sr_identity_verification_links l
         INNER JOIN sr_identity_verification_results r ON r.id = l.result_id
         WHERE l.account_id = :account_id
           AND l.purpose = :purpose
           AND l.revoked_at IS NULL
           AND (r.expires_at IS NULL OR r.expires_at > :now)' . $ageSql . '
         LIMIT 1'
    );
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function sr_identity_verification_start_url(string $purpose, string $returnUrl): string
{
    $purpose = sr_identity_verification_purpose($purpose);
    $returnUrl = sr_identity_verification_safe_return_url($returnUrl);
    $query = ['purpose' => $purpose !== '' ? $purpose : 'default'];
    if ($returnUrl !== '/') {
        $query['return_url'] = $returnUrl;
    }

    return sr_url('/identity/verify/start?' . http_build_query($query));
}

function sr_identity_verification_safe_return_url(string $returnUrl): string
{
    $returnUrl = trim($returnUrl);
    if ($returnUrl !== '' && sr_is_safe_relative_url($returnUrl)) {
        return $returnUrl;
    }

    return '/';
}

function sr_identity_verification_providers(PDO $pdo): array
{
    $settings = sr_identity_verification_settings($pdo);
    $providers = [];
    foreach (sr_enabled_module_contract_files($pdo, 'identity-provider.php', ['identity_verification']) as $moduleKey => $contractFile) {
        $contract = sr_load_module_contract_file((string) $moduleKey, (string) $contractFile);
        if (!is_array($contract)) {
            continue;
        }

        $items = isset($contract['provider_key']) ? [$contract] : $contract;
        foreach ($items as $key => $provider) {
            if (!is_array($provider)) {
                continue;
            }
            $providerKey = sr_identity_verification_provider_key((string) ($provider['provider_key'] ?? (is_string($key) ? $key : '')));
            if ($providerKey === '') {
                continue;
            }
            $provider['provider_key'] = $providerKey;
            $provider['provider_module_key'] = (string) $moduleKey;
            $providers[$providerKey] = sr_identity_verification_apply_provider_settings($provider, $settings);
        }
    }
    ksort($providers);

    return $providers;
}

function sr_identity_verification_apply_provider_settings(array $provider, array $settings): array
{
    $providerKey = sr_identity_verification_provider_key((string) ($provider['provider_key'] ?? ''));
    if ($providerKey === '') {
        return $provider;
    }

    $enabledKey = sr_identity_verification_setting_key($providerKey, 'enabled');
    $environmentKey = sr_identity_verification_setting_key($providerKey, 'environment');
    if ($enabledKey !== '' && array_key_exists($enabledKey, $settings)) {
        $provider['enabled'] = sr_truthy($settings[$enabledKey]);
    }
    if ($environmentKey !== '' && array_key_exists($environmentKey, $settings)) {
        $environment = (string) $settings[$environmentKey];
        $provider['environment'] = in_array($environment, ['test', 'production'], true) ? $environment : 'test';
    }

    foreach ((array) ($provider['settings_schema'] ?? []) as $settingKey => $definition) {
        if (!is_string($settingKey) || !is_array($definition)) {
            continue;
        }
        $storedKey = sr_identity_verification_setting_key($providerKey, $settingKey);
        if ($storedKey !== '' && array_key_exists($storedKey, $settings)) {
            $provider['settings'][$settingKey] = (string) $settings[$storedKey];
        }
    }

    return $provider;
}

function sr_identity_verification_provider_setting(array $provider, string $settingKey): string
{
    $settings = isset($provider['settings']) && is_array($provider['settings']) ? $provider['settings'] : [];
    return trim((string) ($settings[$settingKey] ?? ''));
}

function sr_identity_verification_public_providers(PDO $pdo): array
{
    $providers = array_values(array_filter(sr_identity_verification_providers($pdo), static function (array $provider): bool {
        return !empty($provider['enabled']);
    }));
    usort($providers, static function (array $left, array $right): int {
        $leftOrder = (int) ($left['sort_order'] ?? 0);
        $rightOrder = (int) ($right['sort_order'] ?? 0);
        if ($leftOrder !== $rightOrder) {
            return $leftOrder <=> $rightOrder;
        }

        return strcmp((string) ($left['display_name'] ?? $left['provider_key'] ?? ''), (string) ($right['display_name'] ?? $right['provider_key'] ?? ''));
    });

    return $providers;
}

function sr_identity_verification_select_provider(PDO $pdo, string $providerKey = ''): ?array
{
    $providers = sr_identity_verification_providers($pdo);
    $settings = sr_identity_verification_settings($pdo);
    $providerKey = sr_identity_verification_provider_key($providerKey);
    if ($providerKey !== '' && isset($providers[$providerKey]) && !empty($providers[$providerKey]['enabled'])) {
        return $providers[$providerKey];
    }

    $defaultProviderKey = (string) ($settings['default_provider_key'] ?? '');
    if ($defaultProviderKey !== '' && isset($providers[$defaultProviderKey]) && !empty($providers[$defaultProviderKey]['enabled'])) {
        return $providers[$defaultProviderKey];
    }

    return null;
}

function sr_identity_verification_create_attempt(PDO $pdo, array $config, array $provider, int $accountId, string $purpose, string $returnUrl, array $options = []): array
{
    $settings = sr_identity_verification_settings($pdo);
    $now = sr_now();
    $ttl = (int) ($settings['attempt_ttl_seconds'] ?? 600);
    $verificationKey = 'iv_' . bin2hex(random_bytes(24));
    $stateToken = bin2hex(random_bytes(32));
    $nonce = bin2hex(random_bytes(24));
    $expiresAt = gmdate('Y-m-d H:i:s', time() + $ttl);

    $stmt = $pdo->prepare(
        'INSERT INTO sr_identity_verification_attempts
            (verification_key, provider_key, method, account_id, purpose, subject_module, subject_type, subject_id,
             status, state_token_hash, nonce_hash, return_url, confirm_path, requested_at, expires_at, created_at, updated_at)
         VALUES
            (:verification_key, :provider_key, :method, :account_id, :purpose, :subject_module, :subject_type, :subject_id,
             :status, :state_token_hash, :nonce_hash, :return_url, :confirm_path, :requested_at, :expires_at, :created_at, :updated_at)'
    );
    $stmt->execute([
        'verification_key' => $verificationKey,
        'provider_key' => (string) $provider['provider_key'],
        'method' => (string) (($provider['default_method'] ?? '') ?: ((array) ($provider['supported_methods'] ?? ['identity']))[0]),
        'account_id' => $accountId > 0 ? $accountId : null,
        'purpose' => $purpose,
        'subject_module' => (string) ($options['subject_module'] ?? ''),
        'subject_type' => (string) ($options['subject_type'] ?? ''),
        'subject_id' => (string) ($options['subject_id'] ?? ''),
        'status' => 'ready',
        'state_token_hash' => sr_identity_verification_hash_token($stateToken, $config),
        'nonce_hash' => sr_identity_verification_hash_token($nonce, $config),
        'return_url' => $returnUrl,
        'confirm_path' => (string) ($options['confirm_path'] ?? ''),
        'requested_at' => $now,
        'expires_at' => $expiresAt,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $attempt = sr_identity_verification_attempt_by_key($pdo, $verificationKey);
    if ($attempt === null) {
        throw new RuntimeException('Identity verification attempt was not created.');
    }
    $attempt['state_token'] = $stateToken;
    $attempt['nonce'] = $nonce;

    return $attempt;
}

function sr_identity_verification_attempt_by_key(PDO $pdo, string $verificationKey): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM sr_identity_verification_attempts WHERE verification_key = :verification_key LIMIT 1');
    $stmt->execute(['verification_key' => $verificationKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_identity_verification_attempt_by_state(PDO $pdo, array $config, string $stateToken): ?array
{
    $stateToken = trim($stateToken);
    if ($stateToken === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_identity_verification_attempts WHERE state_token_hash = :state_hash LIMIT 1');
    $stmt->execute(['state_hash' => sr_identity_verification_hash_token($stateToken, $config)]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_identity_verification_mark_attempt(PDO $pdo, int $attemptId, string $status, array $fields = []): void
{
    $allowed = ['ready', 'pending', 'verified', 'failed', 'expired', 'canceled'];
    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('Invalid identity verification status.');
    }

    $sets = ['status = :status', 'updated_at = :updated_at'];
    $params = [
        'id' => $attemptId,
        'status' => $status,
        'updated_at' => sr_now(),
    ];
    foreach (['provider_transaction_id', 'provider_reference', 'failure_code', 'failure_message'] as $field) {
        if (array_key_exists($field, $fields)) {
            $sets[] = $field . ' = :' . $field;
            $params[$field] = (string) $fields[$field];
        }
    }
    if ($status === 'verified') {
        $sets[] = 'completed_at = COALESCE(completed_at, :completed_at)';
        $params['completed_at'] = sr_now();
    } elseif (in_array($status, ['failed', 'expired', 'canceled'], true)) {
        $sets[] = 'failed_at = COALESCE(failed_at, :failed_at)';
        $params['failed_at'] = sr_now();
    }

    $stmt = $pdo->prepare('UPDATE sr_identity_verification_attempts SET ' . implode(', ', $sets) . ' WHERE id = :id');
    $stmt->execute($params);
}

function sr_identity_verification_complete(PDO $pdo, array $config, array $attempt, array $verification): int
{
    if ((string) ($attempt['status'] ?? '') === 'verified') {
        $existing = sr_identity_verification_result_by_attempt($pdo, (int) $attempt['id']);
        return $existing !== null ? (int) $existing['id'] : 0;
    }
    if (in_array((string) ($attempt['status'] ?? ''), ['failed', 'expired', 'canceled'], true)) {
        throw new RuntimeException('Identity verification attempt is already closed.');
    }

    $settings = sr_identity_verification_settings($pdo);
    $identity = isset($verification['identity']) && is_array($verification['identity']) ? $verification['identity'] : [];
    $now = sr_now();
    $expiresAt = null;
    $validDays = (int) ($settings['result_valid_days'] ?? 0);
    if ($validDays > 0) {
        $expiresAt = gmdate('Y-m-d H:i:s', time() + ($validDays * 86400));
    }
    $summary = isset($verification['summary']) && is_array($verification['summary']) ? $verification['summary'] : [];
    $summaryJson = json_encode(sr_identity_verification_public_summary($summary), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_identity_verification_results
                (attempt_id, account_id, provider_key, provider_transaction_id, ci_hash, di_hash, name_hash,
                 phone_hash, birth_date, gender, nationality, age_over_14, age_over_19, result_summary_json,
                 verified_at, expires_at, created_at)
             VALUES
                (:attempt_id, :account_id, :provider_key, :provider_transaction_id, :ci_hash, :di_hash, :name_hash,
                 :phone_hash, :birth_date, :gender, :nationality, :age_over_14, :age_over_19, :result_summary_json,
                 :verified_at, :expires_at, :created_at)'
        );
        $stmt->execute([
            'attempt_id' => (int) $attempt['id'],
            'account_id' => $attempt['account_id'] !== null ? (int) $attempt['account_id'] : null,
            'provider_key' => (string) $attempt['provider_key'],
            'provider_transaction_id' => (string) ($verification['provider_transaction_id'] ?? $attempt['provider_transaction_id'] ?? ''),
            'ci_hash' => sr_identity_verification_hmac_field($config, 'ci', (string) ($identity['ci'] ?? '')),
            'di_hash' => sr_identity_verification_hmac_field($config, 'di', (string) ($identity['di'] ?? '')),
            'name_hash' => sr_identity_verification_hmac_field($config, 'name', (string) ($identity['name'] ?? '')),
            'phone_hash' => sr_identity_verification_hmac_field($config, 'phone', sr_identity_verification_digits((string) ($identity['phone'] ?? ''))),
            'birth_date' => sr_identity_verification_birth_date((string) ($identity['birth_date'] ?? '')),
            'gender' => substr((string) ($identity['gender'] ?? ''), 0, 20),
            'nationality' => substr((string) ($identity['nationality'] ?? ''), 0, 20),
            'age_over_14' => array_key_exists('age_over_14', $identity) ? (sr_truthy($identity['age_over_14']) ? 1 : 0) : null,
            'age_over_19' => array_key_exists('age_over_19', $identity) ? (sr_truthy($identity['age_over_19']) ? 1 : 0) : null,
            'result_summary_json' => is_string($summaryJson) ? $summaryJson : '{}',
            'verified_at' => $now,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);
        $resultId = (int) $pdo->lastInsertId();
        sr_identity_verification_mark_attempt($pdo, (int) $attempt['id'], 'verified', [
            'provider_transaction_id' => (string) ($verification['provider_transaction_id'] ?? ''),
        ]);

        if ((int) ($attempt['account_id'] ?? 0) > 0) {
            $link = $pdo->prepare(
                'INSERT INTO sr_identity_verification_links
                    (account_id, result_id, purpose, linked_at, created_at)
                 VALUES
                    (:account_id, :result_id, :purpose, :linked_at, :created_at)
                 ON DUPLICATE KEY UPDATE
                    revoked_at = NULL,
                    linked_at = VALUES(linked_at)'
            );
            $link->execute([
                'account_id' => (int) $attempt['account_id'],
                'result_id' => $resultId,
                'purpose' => (string) $attempt['purpose'],
                'linked_at' => $now,
                'created_at' => $now,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return $resultId;
}

function sr_identity_verification_result_by_attempt(PDO $pdo, int $attemptId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM sr_identity_verification_results WHERE attempt_id = :attempt_id LIMIT 1');
    $stmt->execute(['attempt_id' => $attemptId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_identity_verification_call_provider(array $provider, string $handlerKey, array $args): mixed
{
    $handlers = isset($provider['handlers']) && is_array($provider['handlers']) ? $provider['handlers'] : [];
    $handler = (string) ($handlers[$handlerKey] ?? '');
    if ($handler === '' || !str_contains($handler, ':')) {
        throw new RuntimeException('Identity provider handler is not configured.');
    }

    [$relativePath, $functionName] = explode(':', $handler, 2);
    $relativePath = trim($relativePath);
    $functionName = trim($functionName);
    $moduleKey = (string) ($provider['provider_module_key'] ?? '');
    if (!sr_is_safe_module_key($moduleKey) || $relativePath === '' || str_contains($relativePath, '..')) {
        throw new RuntimeException('Identity provider handler path is invalid.');
    }

    $path = SR_ROOT . '/modules/' . $moduleKey . '/' . ltrim($relativePath, '/');
    if (!is_file($path)) {
        throw new RuntimeException('Identity provider handler file is missing.');
    }
    require_once $path;
    if (!function_exists($functionName)) {
        throw new RuntimeException('Identity provider handler function is missing.');
    }

    return $functionName(...$args);
}

function sr_identity_verification_hash_token(string $token, array $config): string
{
    return sr_hmac_hash('identity.token|' . $token, $config);
}

function sr_identity_verification_hmac_field(array $config, string $field, string $value): string
{
    $value = trim($value);
    return $value === '' ? '' : sr_hmac_hash('identity.' . $field . '|' . $value, $config);
}

function sr_identity_verification_digits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function sr_identity_verification_birth_date(string $value): ?string
{
    $value = trim($value);
    if (preg_match('/\A(\d{4})-?(\d{2})-?(\d{2})\z/', $value, $matches) !== 1) {
        return null;
    }
    $date = $matches[1] . '-' . $matches[2] . '-' . $matches[3];

    return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]) ? $date : null;
}

function sr_identity_verification_public_summary(array $summary): array
{
    $allowed = [];
    foreach (['provider_result_code', 'provider_result_message', 'method', 'age_over_14', 'age_over_19'] as $key) {
        if (array_key_exists($key, $summary) && is_scalar($summary[$key])) {
            $allowed[$key] = (string) $summary[$key];
        }
    }

    return $allowed;
}

function sr_identity_verification_request_data(): array
{
    return sr_request_method() === 'POST' ? $_POST : $_GET;
}

function sr_identity_verification_extract_state(array $request): string
{
    foreach (['state', 'param_opt_1', 'MSTR', 'mstr'] as $key) {
        $value = $request[$key] ?? '';
        if (is_scalar($value)) {
            $value = trim((string) $value);
            if ($value !== '') {
                if (str_contains($value, 'state=')) {
                    parse_str(str_replace('|', '&', $value), $parsed);
                    if (isset($parsed['state']) && is_scalar($parsed['state'])) {
                        return trim((string) $parsed['state']);
                    }
                }
                return $value;
            }
        }
    }

    return '';
}

function sr_identity_verification_http_json(string $url, array $headers, string $body, int $timeoutSeconds = 10): array
{
    if (!sr_is_public_http_url($url)) {
        throw new RuntimeException('Identity provider endpoint is invalid.');
    }

    $headerLines = [];
    foreach ($headers as $name => $value) {
        if (is_string($name) && is_scalar($value)) {
            $headerLines[] = $name . ': ' . (string) $value;
        }
    }
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headerLines),
            'content' => $body,
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if (!is_string($response)) {
        throw new RuntimeException('Identity provider request failed.');
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Identity provider response is invalid.');
    }

    return $decoded;
}

function sr_identity_verification_render_provider_form(array $prepared): void
{
    $action = (string) ($prepared['action'] ?? '');
    $method = strtoupper((string) ($prepared['method'] ?? 'POST'));
    $fields = isset($prepared['fields']) && is_array($prepared['fields']) ? $prepared['fields'] : [];
    if (!sr_is_public_http_url($action)) {
        sr_render_error(500, '본인확인 제공자 호출 주소가 올바르지 않습니다.');
    }
    if (!in_array($method, ['GET', 'POST'], true)) {
        $method = 'POST';
    }
    include SR_ROOT . '/modules/identity_verification/views/provider-form.php';
    exit;
}
