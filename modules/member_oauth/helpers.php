<?php

declare(strict_types=1);

function sr_member_oauth_settings(PDO $pdo): array
{
    $defaults = [
        'mock_enabled' => true,
        'mock_label' => 'Mock OAuth',
        'state_ttl_seconds' => 600,
        'completion_ttl_seconds' => 900,
    ];

    return array_merge($defaults, sr_module_settings($pdo, 'member_oauth'));
}

function sr_member_oauth_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'member_oauth' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('Member OAuth module is not installed.');
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
        $valueType = is_bool($value) ? 'bool' : (is_int($value) ? 'int' : 'string');
        $save->execute([
            'module_id' => (int) $module['id'],
            'setting_key' => (string) $key,
            'setting_value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            'value_type' => $valueType,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    sr_clear_module_settings_cache('member_oauth');
}

function sr_member_oauth_provider_key(string $providerKey): string
{
    $providerKey = strtolower(trim($providerKey));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $providerKey) === 1 ? $providerKey : '';
}

function sr_member_oauth_provider_setting_key(string $providerKey, string $settingKey): string
{
    $providerKey = sr_member_oauth_provider_key($providerKey);
    if ($providerKey === '' || preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $settingKey) !== 1) {
        return '';
    }

    return 'provider_' . $providerKey . '_' . $settingKey;
}

function sr_member_oauth_apply_provider_settings(array $provider, array $settings): array
{
    $providerKey = sr_member_oauth_provider_key((string) ($provider['provider_key'] ?? ''));
    if ($providerKey === '' || !empty($provider['mock'])) {
        return $provider;
    }

    foreach (['label', 'client_id', 'client_secret', 'scope', 'profile_sync_json', 'sort_order'] as $settingKey) {
        $storedKey = sr_member_oauth_provider_setting_key($providerKey, $settingKey);
        if ($storedKey !== '' && array_key_exists($storedKey, $settings)) {
            $provider[$settingKey] = $settings[$storedKey];
        }
    }

    $enabledKey = sr_member_oauth_provider_setting_key($providerKey, 'enabled');
    if ($enabledKey !== '' && array_key_exists($enabledKey, $settings)) {
        $provider['enabled'] = !empty($settings[$enabledKey]);
    }

    return $provider;
}

function sr_member_oauth_secret_display(string $value): string
{
    return $value === '' ? '' : '********';
}

function sr_member_oauth_builtin_providers(PDO $pdo): array
{
    $settings = sr_member_oauth_settings($pdo);
    if (empty($settings['mock_enabled'])) {
        return [];
    }

    return [
        'mock' => [
            'provider_key' => 'mock',
            'label' => (string) ($settings['mock_label'] ?? 'Mock OAuth'),
            'authorization_url' => sr_url('/oauth/callback'),
            'mock' => true,
        ],
    ];
}

function sr_member_oauth_providers(PDO $pdo): array
{
    $settings = sr_member_oauth_settings($pdo);
    $providers = sr_member_oauth_builtin_providers($pdo);
    foreach (sr_installed_module_contract_files($pdo, 'oauth-providers.php', ['member_oauth']) as $moduleKey => $contractFile) {
        $contract = sr_load_module_contract_file($moduleKey, $contractFile);
        if (!is_array($contract)) {
            continue;
        }
        foreach ($contract as $providerKey => $provider) {
            $normalizedKey = sr_member_oauth_provider_key((string) $providerKey);
            if ($normalizedKey === '' || !is_array($provider)) {
                continue;
            }
            $providers[$normalizedKey] = array_merge($provider, [
                'provider_key' => $normalizedKey,
                'provider_module_key' => (string) $moduleKey,
            ]);
        }
    }
    foreach ($providers as $providerKey => $provider) {
        $providers[$providerKey] = sr_member_oauth_apply_provider_settings($provider, $settings);
    }

    return $providers;
}

function sr_member_oauth_public_providers(PDO $pdo): array
{
    $providers = array_values(array_filter(sr_member_oauth_providers($pdo), static function (array $provider): bool {
        return !empty($provider['enabled']) || !empty($provider['mock']);
    }));
    usort($providers, static function (array $left, array $right): int {
        $leftOrder = (int) ($left['sort_order'] ?? 0);
        $rightOrder = (int) ($right['sort_order'] ?? 0);
        if ($leftOrder !== $rightOrder) {
            return $leftOrder <=> $rightOrder;
        }

        return strcmp((string) ($left['label'] ?? $left['provider_key'] ?? ''), (string) ($right['label'] ?? $right['provider_key'] ?? ''));
    });

    return $providers;
}

function sr_member_oauth_provider_admin_status(array $provider, string $callbackUrl): array
{
    $isMock = !empty($provider['mock']);
    $enabled = !empty($provider['enabled']) || $isMock;
    $clientId = sr_member_oauth_provider_value($provider, 'client_id');
    $callbackValid = sr_is_http_url($callbackUrl);
    $visible = $isMock ? $enabled : ($enabled && $clientId !== '' && $callbackValid);
    $items = [
        [
            'label' => '모듈 활성',
            'ok' => true,
            'message' => '제공자 계약이 로드되었습니다.',
        ],
        [
            'label' => '사용 설정',
            'ok' => $enabled,
            'message' => $enabled ? '사용 중입니다.' : '사용을 켜야 로그인 버튼 후보가 됩니다.',
        ],
        [
            'label' => 'Client ID',
            'ok' => $isMock || $clientId !== '',
            'message' => $isMock ? '테스트 로그인 서비스는 클라이언트 ID를 사용하지 않습니다.' : ($clientId !== '' ? '입력되었습니다.' : '클라이언트 ID를 입력해야 합니다.'),
        ],
        [
            'label' => 'Callback URL',
            'ok' => $callbackValid,
            'message' => $callbackValid ? '제공자 콘솔에 등록할 수 있는 URL 형식입니다.' : '공개 기준 URL을 먼저 확인해 주세요.',
        ],
    ];

    return [
        'visible' => $visible,
        'label' => $visible ? '로그인 버튼 노출 가능' : '로그인 버튼 노출 대기',
        'class' => $visible ? 'is-success' : 'is-warning',
        'items' => $items,
    ];
}

function sr_member_oauth_provider_value(array $provider, string $key): string
{
    $value = $provider[$key] ?? '';
    if (is_array($value)) {
        return '';
    }

    return trim((string) $value);
}

function sr_member_oauth_scope_items(mixed $value): array
{
    if (is_array($value)) {
        $rawItems = $value;
    } else {
        $rawItems = preg_split('/[\s,]+/', trim((string) $value)) ?: [];
    }

    $items = [];
    foreach ($rawItems as $rawItem) {
        if (!is_scalar($rawItem)) {
            continue;
        }
        $item = trim((string) $rawItem);
        if ($item === '' || in_array($item, $items, true)) {
            continue;
        }
        $items[] = $item;
        if (count($items) >= 50) {
            break;
        }
    }

    return $items;
}

function sr_member_oauth_scope_setting_value(mixed $value): string
{
    return implode("\n", sr_member_oauth_scope_items($value));
}

function sr_member_oauth_required_scope_items(array $provider): array
{
    if (array_key_exists('required_scopes', $provider)) {
        return sr_member_oauth_scope_items($provider['required_scopes']);
    }

    return sr_member_oauth_scope_items($provider['scopes'] ?? []);
}

function sr_member_oauth_scope_items_with_required(mixed $value, array $provider): array
{
    $items = sr_member_oauth_scope_items($value);
    $merged = [];
    foreach (array_merge(sr_member_oauth_required_scope_items($provider), $items) as $item) {
        if ($item !== '' && !in_array($item, $merged, true)) {
            $merged[] = $item;
        }
    }

    return $merged;
}

function sr_member_oauth_scope_setting_value_with_required(mixed $value, array $provider): string
{
    return implode("\n", sr_member_oauth_scope_items_with_required($value, $provider));
}

function sr_member_oauth_claim_value(array $data, string $claim): mixed
{
    $claim = trim($claim);
    if ($claim === '') {
        return null;
    }

    $current = $data;
    foreach (explode('.', $claim) as $segment) {
        $segment = trim($segment);
        if ($segment === '' || !is_array($current) || !array_key_exists($segment, $current)) {
            return null;
        }

        $current = $current[$segment];
    }

    return is_array($current) ? null : $current;
}

function sr_member_oauth_provider_scopes(array $provider): string
{
    $scopes = $provider['scope'] ?? ($provider['scopes'] ?? 'openid email profile');
    $items = sr_member_oauth_scope_items($scopes);
    if ($items === []) {
        return '';
    }

    $delimiter = sr_member_oauth_provider_value($provider, 'scope_delimiter');
    if ($delimiter === '') {
        $delimiter = ' ';
    }

    return implode($delimiter, $items);
}

function sr_member_oauth_profile_sync_targets(array $extraDefinitions): array
{
    $targets = [
        'email' => '이메일',
        'display_name' => '이름',
    ];
    foreach ($extraDefinitions as $definition) {
        $key = (string) ($definition['key'] ?? '');
        if ($key === '') {
            continue;
        }
        $targets['profile:' . $key] = '선택 프로필: ' . (string) ($definition['label'] ?? $key);
    }

    return $targets;
}

function sr_member_oauth_claim_path_options(array $provider): array
{
    $paths = [];
    foreach ([
        'subject_claim',
        'email_claim',
        'email_verified_claim',
        'display_name_claim',
        'fallback_display_name_claim',
    ] as $key) {
        $path = sr_member_oauth_provider_value($provider, $key);
        if ($path !== '' && !in_array($path, $paths, true)) {
            $paths[] = $path;
        }
    }

    foreach (['claim_paths', 'profile_claims'] as $key) {
        $raw = $provider[$key] ?? [];
        if (!is_array($raw)) {
            continue;
        }
        foreach ($raw as $rawKey => $rawValue) {
            $path = is_string($rawKey) && is_array($rawValue)
                ? (string) ($rawValue['claim'] ?? $rawValue['path'] ?? $rawKey)
                : (is_scalar($rawValue) ? (string) $rawValue : '');
            $path = trim($path);
            if ($path !== '' && preg_match('/\A[a-zA-Z0-9_.:-]+\z/', $path) === 1 && !in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }
    }

    return $paths;
}

function sr_member_oauth_default_profile_sync_rules(array $provider): array
{
    $scopeItems = sr_member_oauth_scope_items($provider['scope'] ?? ($provider['scopes'] ?? []));
    $emailScope = sr_member_oauth_provider_value($provider, 'email_scope');
    $displayNameScope = sr_member_oauth_provider_value($provider, 'display_name_scope');
    $emailScope = $emailScope !== '' && in_array($emailScope, $scopeItems, true) ? $emailScope : (in_array('email', $scopeItems, true) ? 'email' : '');
    $displayNameScope = $displayNameScope !== '' && in_array($displayNameScope, $scopeItems, true) ? $displayNameScope : (in_array('profile', $scopeItems, true) ? 'profile' : '');

    return [
        [
            'target' => 'email',
            'scope' => $emailScope,
            'claim' => sr_member_oauth_provider_value($provider, 'email_claim') ?: 'email',
        ],
        [
            'target' => 'display_name',
            'scope' => $displayNameScope,
            'claim' => sr_member_oauth_provider_value($provider, 'display_name_claim') ?: 'name',
        ],
    ];
}

function sr_member_oauth_profile_sync_rules(array $provider): array
{
    $rawJson = trim((string) ($provider['profile_sync_json'] ?? ''));
    if ($rawJson === '') {
        return sr_member_oauth_default_profile_sync_rules($provider);
    }

    $decoded = json_decode($rawJson, true);
    if (!is_array($decoded)) {
        return sr_member_oauth_default_profile_sync_rules($provider);
    }

    $defaultRulesByTarget = [];
    foreach (sr_member_oauth_default_profile_sync_rules($provider) as $defaultRule) {
        $defaultTarget = (string) ($defaultRule['target'] ?? '');
        if ($defaultTarget !== '') {
            $defaultRulesByTarget[$defaultTarget] = $defaultRule;
        }
    }
    $rules = [];
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        $target = trim((string) ($item['target'] ?? ''));
        $claim = trim((string) ($item['claim'] ?? ''));
        if ($target === '' || $claim === '') {
            continue;
        }
        $scope = trim((string) ($item['scope'] ?? ''));
        if ($scope === '' && isset($defaultRulesByTarget[$target])) {
            $scope = (string) ($defaultRulesByTarget[$target]['scope'] ?? '');
        }
        $rules[] = [
            'target' => $target,
            'scope' => $scope,
            'claim' => $claim,
        ];
        if (count($rules) >= 30) {
            break;
        }
    }

    return $rules !== [] ? $rules : sr_member_oauth_default_profile_sync_rules($provider);
}

function sr_member_oauth_profile_sync_rules_json_from_input(mixed $raw, array $extraDefinitions, array $provider, array &$errors, string $providerLabel): string
{
    $allowedTargets = sr_member_oauth_profile_sync_targets($extraDefinitions);
    $allowedScopes = array_fill_keys(sr_member_oauth_scope_items($provider['scope'] ?? ($provider['scopes'] ?? [])), true);
    $defaultRulesByTarget = [];
    foreach (sr_member_oauth_default_profile_sync_rules($provider) as $defaultRule) {
        $defaultTarget = (string) ($defaultRule['target'] ?? '');
        if ($defaultTarget !== '') {
            $defaultRulesByTarget[$defaultTarget] = $defaultRule;
        }
    }
    $rows = is_array($raw) ? $raw : [];
    $rules = [];
    $seen = [];
    foreach ($rows as $index => $row) {
        if (!is_array($row)) {
            $errors[] = $providerLabel . ' 프로필 동기화 #' . (string) ((int) $index + 1) . ' 형식을 확인해 주세요.';
            continue;
        }
        $target = trim((string) ($row['target'] ?? ''));
        $scope = trim((string) ($row['scope'] ?? ''));
        $claim = trim((string) ($row['claim'] ?? ''));
        if ($target === '' && $claim === '') {
            continue;
        }
        if (!isset($allowedTargets[$target])) {
            $errors[] = $providerLabel . ' 프로필 동기화 대상이 올바르지 않습니다.';
            continue;
        }
        if ($scope !== '' && !isset($allowedScopes[$scope])) {
            $errors[] = $providerLabel . ' 회원 정보 가져오기에 필요한 권한은 위 정보 권한 항목 중에서 선택해 주세요.';
            continue;
        }
        if ($claim === '' || strlen($claim) > 120 || preg_match('/\A[a-zA-Z0-9_.:-]+\z/', $claim) !== 1) {
            $errors[] = $providerLabel . ' 프로필 claim path를 확인해 주세요.';
            continue;
        }
        if (isset($seen[$target])) {
            $errors[] = $providerLabel . ' 프로필 동기화 대상이 중복되었습니다.';
            continue;
        }
        $seen[$target] = true;
        $rules[] = [
            'target' => $target,
            'scope' => $scope,
            'claim' => $claim,
        ];
        if (count($rules) >= 30) {
            break;
        }
    }
    foreach (['email', 'display_name'] as $requiredTarget) {
        if (!isset($seen[$requiredTarget]) && isset($defaultRulesByTarget[$requiredTarget])) {
            array_unshift($rules, $defaultRulesByTarget[$requiredTarget]);
            $seen[$requiredTarget] = true;
        }
    }

    if ($rules === []) {
        $rules = sr_member_oauth_default_profile_sync_rules($provider);
    }

    return json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function sr_member_oauth_mapped_profile_fields(array $provider, array $userinfo): array
{
    $mappedFields = [];
    foreach (sr_member_oauth_profile_sync_rules($provider) as $rule) {
        $target = (string) ($rule['target'] ?? '');
        $claim = (string) ($rule['claim'] ?? '');
        $claimValue = sr_member_oauth_claim_value($userinfo, $claim);
        if ($target === '' || !is_scalar($claimValue)) {
            continue;
        }
        $mappedFields[$target] = is_string($claimValue) ? trim($claimValue) : $claimValue;
    }

    return $mappedFields;
}

function sr_member_oauth_authorization_url(array $provider, array $site, array $state): string
{
    $authorizationUrl = sr_member_oauth_provider_value($provider, 'authorization_url');
    $clientId = sr_member_oauth_provider_value($provider, 'client_id');
    $callbackUrl = sr_absolute_url($site, '/oauth/callback');
    if (!sr_is_public_http_url($authorizationUrl) || !sr_is_http_url($callbackUrl) || $clientId === '') {
        throw new InvalidArgumentException('OAuth provider authorization settings are incomplete.');
    }

    $scope = sr_member_oauth_provider_scopes($provider);
    $params = [
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => $callbackUrl,
        'state' => (string) $state['state'],
        'code_challenge' => (string) $state['code_challenge'],
        'code_challenge_method' => 'S256',
    ];
    if ($scope !== '') {
        $params['scope'] = $scope;
    }
    if (!empty($state['nonce'])) {
        $params['nonce'] = (string) $state['nonce'];
    }

    return $authorizationUrl . (str_contains($authorizationUrl, '?') ? '&' : '?') . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function sr_member_oauth_store_transient_secrets(string $state, array $stateData, int $ttlSeconds): void
{
    $hash = sr_member_oauth_hash($state);
    $now = time();
    $expiresAt = $now + max(60, $ttlSeconds);
    if (!isset($_SESSION['sr_member_oauth_transient']) || !is_array($_SESSION['sr_member_oauth_transient'])) {
        $_SESSION['sr_member_oauth_transient'] = [];
    }

    foreach ($_SESSION['sr_member_oauth_transient'] as $key => $stored) {
        if (!is_array($stored) || (int) ($stored['expires_at'] ?? 0) < $now) {
            unset($_SESSION['sr_member_oauth_transient'][$key]);
        }
    }

    $_SESSION['sr_member_oauth_transient'][$hash] = [
        'nonce' => (string) ($stateData['nonce'] ?? ''),
        'code_verifier' => (string) ($stateData['code_verifier'] ?? ''),
        'expires_at' => $expiresAt,
    ];
}

function sr_member_oauth_take_transient_secrets(string $state): ?array
{
    $hash = sr_member_oauth_hash($state);
    if (!isset($_SESSION['sr_member_oauth_transient'][$hash]) || !is_array($_SESSION['sr_member_oauth_transient'][$hash])) {
        return null;
    }

    $stored = $_SESSION['sr_member_oauth_transient'][$hash];
    unset($_SESSION['sr_member_oauth_transient'][$hash]);
    if ((int) ($stored['expires_at'] ?? 0) < time()) {
        return null;
    }

    return [
        'nonce' => (string) ($stored['nonce'] ?? ''),
        'code_verifier' => (string) ($stored['code_verifier'] ?? ''),
    ];
}

function sr_member_oauth_hash(string $value): string
{
    return hash('sha256', $value);
}

function sr_member_oauth_pkce_challenge(string $verifier): string
{
    return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
}

function sr_member_oauth_create_state(PDO $pdo, string $providerKey, string $flowType, ?int $accountId, string $nextPath, int $ttlSeconds): array
{
    $providerKey = sr_member_oauth_provider_key($providerKey);
    if ($providerKey === '' || !in_array($flowType, ['login', 'link', 'completion'], true)) {
        throw new InvalidArgumentException('외부 로그인 요청 확인값이 올바르지 않습니다.');
    }

    $state = bin2hex(random_bytes(32));
    $nonce = bin2hex(random_bytes(32));
    $codeVerifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $now = sr_now();
    $expiresAt = date('Y-m-d H:i:s', time() + max(60, $ttlSeconds));
    $stmt = $pdo->prepare(
        'INSERT INTO sr_member_oauth_states
            (state_hash, nonce_hash, code_verifier_hash, provider_key, flow_type, account_id, next_path, issued_at, expires_at, created_at)
         VALUES
            (:state_hash, :nonce_hash, :code_verifier_hash, :provider_key, :flow_type, :account_id, :next_path, :issued_at, :expires_at, :created_at)'
    );
    $stmt->execute([
        'state_hash' => sr_member_oauth_hash($state),
        'nonce_hash' => sr_member_oauth_hash($nonce),
        'code_verifier_hash' => sr_member_oauth_hash($codeVerifier),
        'provider_key' => $providerKey,
        'flow_type' => $flowType,
        'account_id' => $accountId !== null && $accountId > 0 ? $accountId : null,
        'next_path' => sr_member_safe_next_path($nextPath),
        'issued_at' => $now,
        'expires_at' => $expiresAt,
        'created_at' => $now,
    ]);

    return [
        'state' => $state,
        'nonce' => $nonce,
        'code_verifier' => $codeVerifier,
        'code_challenge' => sr_member_oauth_pkce_challenge($codeVerifier),
    ];
}

function sr_member_oauth_consume_state(PDO $pdo, string $state, string $providerKey, string $flowType): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_member_oauth_states
         WHERE state_hash = :state_hash
           AND provider_key = :provider_key
           AND flow_type = :flow_type
           AND used_at IS NULL
           AND expires_at >= :now
         LIMIT 1'
    );
    $stmt->execute([
        'state_hash' => sr_member_oauth_hash($state),
        'provider_key' => sr_member_oauth_provider_key($providerKey),
        'flow_type' => $flowType,
        'now' => sr_now(),
    ]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    $update = $pdo->prepare(
        'UPDATE sr_member_oauth_states
         SET used_at = :used_at
         WHERE id = :id
           AND used_at IS NULL'
    );
    $update->execute([
        'used_at' => sr_now(),
        'id' => (int) $row['id'],
    ]);
    if ($update->rowCount() < 1) {
        return null;
    }

    return $row;
}

function sr_member_oauth_state_by_token(PDO $pdo, string $state, string $flowType): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_member_oauth_states
         WHERE state_hash = :state_hash
           AND flow_type = :flow_type
           AND used_at IS NULL
           AND expires_at >= :now
         LIMIT 1'
    );
    $stmt->execute([
        'state_hash' => sr_member_oauth_hash($state),
        'flow_type' => $flowType,
        'now' => sr_now(),
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_member_oauth_subject_hash(array $config, string $providerKey, string $subject): string
{
    return sr_hmac_hash($providerKey . ':' . $subject, $config);
}

function sr_member_oauth_subject_display_from_hash(string $subjectHash): string
{
    $normalizedHash = strtolower(preg_replace('/[^a-f0-9]/i', '', $subjectHash) ?? '');
    if (strlen($normalizedHash) < 12) {
        return '';
    }

    return 'subject:' . substr($normalizedHash, 0, 12);
}

function sr_member_oauth_account_by_subject(PDO $pdo, string $providerKey, string $subjectHash): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_member_oauth_accounts
         WHERE provider_key = :provider_key
           AND provider_subject_hash = :provider_subject_hash
           AND revoked_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([
        'provider_key' => $providerKey,
        'provider_subject_hash' => $subjectHash,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_member_oauth_account_by_subject_any(PDO $pdo, string $providerKey, string $subjectHash): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_member_oauth_accounts
         WHERE provider_key = :provider_key
           AND provider_subject_hash = :provider_subject_hash
         ORDER BY revoked_at IS NULL DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute([
        'provider_key' => $providerKey,
        'provider_subject_hash' => $subjectHash,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_member_oauth_accounts_for_account(PDO $pdo, int $accountId): array
{
    if ($accountId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_member_oauth_accounts
         WHERE account_id = :account_id
           AND revoked_at IS NULL
         ORDER BY linked_at DESC, id DESC'
    );
    $stmt->execute(['account_id' => $accountId]);

    return $stmt->fetchAll();
}

function sr_member_oauth_account_for_provider(PDO $pdo, int $accountId, string $providerKey): ?array
{
    if ($accountId < 1 || $providerKey === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_member_oauth_accounts
         WHERE account_id = :account_id
           AND provider_key = :provider_key
           AND revoked_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'provider_key' => $providerKey,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_member_oauth_password_login_available(array $account): bool
{
    return trim((string) ($account['password_hash'] ?? '')) !== '';
}

function sr_member_oauth_can_unlink(array $account, array $activeOauthAccounts): bool
{
    if (sr_member_oauth_password_login_available($account)) {
        return true;
    }

    return count($activeOauthAccounts) > 1;
}

function sr_member_oauth_revoke_account(PDO $pdo, int $oauthAccountId, int $accountId): bool
{
    $stmt = $pdo->prepare(
        'UPDATE sr_member_oauth_accounts
         SET revoked_at = :revoked_at,
             updated_at = :updated_at
         WHERE id = :id
           AND account_id = :account_id
           AND revoked_at IS NULL'
    );
    $stmt->execute([
        'revoked_at' => sr_now(),
        'updated_at' => sr_now(),
        'id' => $oauthAccountId,
        'account_id' => $accountId,
    ]);

    return $stmt->rowCount() > 0;
}

function sr_member_oauth_link_account(PDO $pdo, int $accountId, string $providerKey, string $subjectHash, array $profile): int
{
    $now = sr_now();
    $subjectDisplay = sr_member_oauth_subject_display_from_hash($subjectHash);
    $existing = sr_member_oauth_account_by_subject_any($pdo, $providerKey, $subjectHash);
    if (is_array($existing)) {
        if ((int) $existing['account_id'] !== $accountId) {
            throw new RuntimeException('OAuth provider account is already linked.');
        }
        if ($existing['revoked_at'] === null) {
            sr_member_oauth_update_link_snapshot($pdo, (int) $existing['id'], $profile);
            return (int) $existing['id'];
        }

        $stmt = $pdo->prepare(
            'UPDATE sr_member_oauth_accounts
             SET provider_subject_display = :provider_subject_display,
                 email_snapshot = :email_snapshot,
                 email_verified_snapshot = :email_verified_snapshot,
                 display_name_snapshot = :display_name_snapshot,
                 linked_at = :linked_at,
                 revoked_at = NULL,
                 updated_at = :updated_at
             WHERE id = :id
               AND account_id = :account_id
               AND revoked_at IS NOT NULL'
        );
        $stmt->execute([
            'provider_subject_display' => $subjectDisplay,
            'email_snapshot' => (string) ($profile['email'] ?? ''),
            'email_verified_snapshot' => !empty($profile['email_verified']) ? 1 : 0,
            'display_name_snapshot' => (string) ($profile['display_name'] ?? ''),
            'linked_at' => $now,
            'updated_at' => $now,
            'id' => (int) $existing['id'],
            'account_id' => $accountId,
        ]);

        return (int) $existing['id'];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_member_oauth_accounts
            (account_id, provider_key, provider_subject_hash, provider_subject_display, email_snapshot,
             email_verified_snapshot, display_name_snapshot, linked_at, created_at, updated_at)
         VALUES
            (:account_id, :provider_key, :provider_subject_hash, :provider_subject_display, :email_snapshot,
             :email_verified_snapshot, :display_name_snapshot, :linked_at, :created_at, :updated_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'provider_key' => $providerKey,
        'provider_subject_hash' => $subjectHash,
        'provider_subject_display' => $subjectDisplay,
        'email_snapshot' => (string) ($profile['email'] ?? ''),
        'email_verified_snapshot' => !empty($profile['email_verified']) ? 1 : 0,
        'display_name_snapshot' => (string) ($profile['display_name'] ?? ''),
        'linked_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_member_oauth_mock_profile(): array
{
    return [
        'subject' => 'mock-user',
        'subject_display' => 'mock-user',
        'email' => 'mock-user@example.test',
        'email_verified' => true,
        'display_name' => 'mock_user',
    ];
}

function sr_member_oauth_http_json(string $url, array $contextOptions): array
{
    if (!sr_is_public_http_url($url)) {
        throw new RuntimeException('OAuth provider endpoint is invalid.');
    }

    $context = stream_context_create($contextOptions);
    $response = @file_get_contents($url, false, $context);
    if (!is_string($response)) {
        throw new RuntimeException('OAuth provider request failed.');
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('OAuth provider response is invalid.');
    }

    return $decoded;
}

function sr_member_oauth_base64url_decode(string $value): string
{
    $remainder = strlen($value) % 4;
    if ($remainder > 0) {
        $value .= str_repeat('=', 4 - $remainder);
    }

    $decoded = base64_decode(strtr($value, '-_', '+/'), true);

    return is_string($decoded) ? $decoded : '';
}

function sr_member_oauth_jwt_payload(string $jwt): array
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3 || trim($parts[1]) === '') {
        throw new RuntimeException('OAuth provider ID token is invalid.');
    }

    $decoded = json_decode(sr_member_oauth_base64url_decode($parts[1]), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('OAuth provider ID token payload is invalid.');
    }

    return $decoded;
}

function sr_member_oauth_truthy(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return $value > 0;
    }
    if (is_string($value)) {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y'], true);
    }

    return !empty($value);
}

function sr_member_oauth_primary_email_from_list(array $emails): array
{
    $fallback = null;
    foreach ($emails as $emailRow) {
        if (!is_array($emailRow)) {
            continue;
        }
        $email = trim((string) ($emailRow['email'] ?? ''));
        if ($email === '') {
            continue;
        }
        if ($fallback === null || !empty($emailRow['verified'])) {
            $fallback = $emailRow;
        }
        if (!empty($emailRow['primary'])) {
            return [
                'email' => $email,
                'verified' => sr_member_oauth_truthy($emailRow['verified'] ?? false),
            ];
        }
    }
    if (is_array($fallback)) {
        return [
            'email' => trim((string) ($fallback['email'] ?? '')),
            'verified' => sr_member_oauth_truthy($fallback['verified'] ?? false),
        ];
    }

    return ['email' => '', 'verified' => false];
}

function sr_member_oauth_provider_profile(array $provider, array $site, string $code, array $transientSecrets): array
{
    $tokenUrl = sr_member_oauth_provider_value($provider, 'token_url');
    $userinfoUrl = sr_member_oauth_provider_value($provider, 'userinfo_url');
    $profileSource = sr_member_oauth_provider_value($provider, 'profile_source') ?: 'userinfo';
    $clientId = sr_member_oauth_provider_value($provider, 'client_id');
    $clientSecret = sr_member_oauth_provider_value($provider, 'client_secret');
    $codeVerifier = (string) ($transientSecrets['code_verifier'] ?? '');
    $callbackUrl = sr_absolute_url($site, '/oauth/callback');
    if ($tokenUrl === '' || !sr_is_http_url($callbackUrl) || $clientId === '' || $code === '' || $codeVerifier === '') {
        throw new RuntimeException('OAuth provider callback settings are incomplete.');
    }
    if ($profileSource !== 'id_token' && $userinfoUrl === '') {
        throw new RuntimeException('OAuth provider callback settings are incomplete.');
    }

    $tokenPayload = [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $callbackUrl,
        'client_id' => $clientId,
        'code_verifier' => $codeVerifier,
    ];
    if ($clientSecret !== '') {
        $tokenPayload['client_secret'] = $clientSecret;
    }

    $tokenResponse = sr_member_oauth_http_json($tokenUrl, [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
            'content' => http_build_query($tokenPayload, '', '&', PHP_QUERY_RFC3986),
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);
    $bearer = trim((string) ($tokenResponse['access' . '_token'] ?? ''));
    $idToken = trim((string) ($tokenResponse['id' . '_token'] ?? ''));
    if ($bearer === '' && $idToken === '') {
        throw new RuntimeException('OAuth provider token response is missing an access credential.');
    }

    if ($profileSource === 'id_token') {
        if ($idToken === '') {
            throw new RuntimeException('OAuth provider token response is missing an ID token.');
        }
        $userinfo = sr_member_oauth_jwt_payload($idToken);
        $expectedNonce = (string) ($transientSecrets['nonce'] ?? '');
        $profileNonce = trim((string) ($userinfo['nonce'] ?? ''));
        if ($expectedNonce !== '' && $profileNonce !== '' && !hash_equals($expectedNonce, $profileNonce)) {
            throw new RuntimeException('OAuth provider ID token nonce does not match.');
        }
    } else {
        if ($bearer === '') {
            throw new RuntimeException('OAuth provider token response is missing an access token.');
        }
        $userinfo = sr_member_oauth_http_json($userinfoUrl, [
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer " . $bearer . "\r\nAccept: application/json\r\nUser-Agent: Saanraan OAuth\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);
    }

    $subjectClaim = sr_member_oauth_provider_value($provider, 'subject_claim') ?: 'sub';
    $emailClaim = sr_member_oauth_provider_value($provider, 'email_claim') ?: 'email';
    $emailVerifiedClaim = sr_member_oauth_provider_value($provider, 'email_verified_claim') ?: 'email_verified';
    $displayNameClaim = sr_member_oauth_provider_value($provider, 'display_name_claim') ?: 'name';
    $fallbackNameClaim = sr_member_oauth_provider_value($provider, 'fallback_display_name_claim') ?: 'nickname';
    $subject = trim((string) (sr_member_oauth_claim_value($userinfo, $subjectClaim) ?? ($userinfo['id'] ?? '')));
    if ($subject === '') {
        throw new RuntimeException('OAuth provider profile is missing a subject.');
    }

    $email = trim((string) (sr_member_oauth_claim_value($userinfo, $emailClaim) ?? ''));
    $emailVerified = $emailVerifiedClaim === '' ? false : sr_member_oauth_truthy(sr_member_oauth_claim_value($userinfo, $emailVerifiedClaim));
    $emailUrl = sr_member_oauth_provider_value($provider, 'email_url');
    if ($email === '' && $emailUrl !== '' && $bearer !== '') {
        $emailList = sr_member_oauth_http_json($emailUrl, [
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer " . $bearer . "\r\nAccept: application/json\r\nUser-Agent: Saanraan OAuth\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);
        $primaryEmail = sr_member_oauth_primary_email_from_list($emailList);
        $email = (string) ($primaryEmail['email'] ?? '');
        $emailVerified = !empty($primaryEmail['verified']);
    }

    $displayName = trim((string) (sr_member_oauth_claim_value($userinfo, $displayNameClaim) ?? sr_member_oauth_claim_value($userinfo, $fallbackNameClaim) ?? ''));
    $mappedFields = sr_member_oauth_mapped_profile_fields($provider, $userinfo);

    return [
        'subject' => $subject,
        'subject_display' => $subject,
        'email' => $email,
        'email_verified' => $emailVerified,
        'display_name' => $displayName,
        'mapped_fields' => $mappedFields,
    ];
}

function sr_member_oauth_update_link_snapshot(PDO $pdo, int $oauthAccountId, array $profile): void
{
    if ($oauthAccountId < 1) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_member_oauth_accounts
         SET email_snapshot = :email_snapshot,
             email_verified_snapshot = :email_verified_snapshot,
             display_name_snapshot = :display_name_snapshot,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'email_snapshot' => (string) ($profile['email'] ?? ''),
        'email_verified_snapshot' => !empty($profile['email_verified']) ? 1 : 0,
        'display_name_snapshot' => (string) ($profile['display_name'] ?? ''),
        'updated_at' => sr_now(),
        'id' => $oauthAccountId,
    ]);
}

function sr_member_oauth_sync_member_profile(PDO $pdo, array $config, int $accountId, array $account, array $provider, array $profile, array $memberSettings): array
{
    if ($accountId < 1 || in_array((string) ($account['status'] ?? ''), ['withdrawn', 'anonymized'], true)) {
        return [];
    }

    $mapped = is_array($profile['mapped_fields'] ?? null) ? $profile['mapped_fields'] : [];
    $email = trim((string) ($mapped['email'] ?? $profile['email'] ?? ''));
    $displayName = sr_member_normalize_display_name((string) ($mapped['display_name'] ?? $profile['display_name'] ?? ''));
    $updates = [];
    $changed = [];

    if ($displayName !== '' && $displayName !== (string) ($account['display_name'] ?? '') && sr_member_display_name_validation_errors($displayName) === []) {
        $updates['display_name'] = $displayName;
        $changed[] = 'display_name';
    }

    if (!empty($profile['email_verified']) && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && $email !== (string) ($account['email'] ?? '')) {
        $emailHash = sr_hmac_hash(sr_normalize_identifier($email), $config);
        $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE email_hash = :email_hash AND id <> :id LIMIT 1');
        $stmt->execute([
            'email_hash' => $emailHash,
            'id' => $accountId,
        ]);
        if (!is_array($stmt->fetch())) {
            $updates['email'] = sr_normalize_identifier($email);
            $updates['email_hash'] = $emailHash;
            $updates['email_verified_at'] = sr_now();
            $changed[] = 'email';
        }
    }

    if ($updates !== []) {
        $currentIdentifierHash = (string) ($account['account_identifier_hash'] ?? '');
        $currentEmailHash = (string) ($account['email_hash'] ?? '');
        $nextIdentifierHash = $currentIdentifierHash;
        if (isset($updates['email_hash']) && (string) ($account['login_id_hash'] ?? '') === '' && $currentIdentifierHash !== '' && hash_equals($currentIdentifierHash, $currentEmailHash)) {
            $nextIdentifierHash = (string) $updates['email_hash'];
        }

        $stmt = $pdo->prepare(
            'UPDATE sr_member_accounts
             SET account_identifier_hash = :account_identifier_hash,
                 email = :email,
                 email_hash = :email_hash,
                 display_name = :display_name,
                 email_verified_at = :email_verified_at,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'account_identifier_hash' => $nextIdentifierHash,
            'email' => (string) ($updates['email'] ?? $account['email'] ?? ''),
            'email_hash' => (string) ($updates['email_hash'] ?? $account['email_hash'] ?? ''),
            'display_name' => (string) ($updates['display_name'] ?? $account['display_name'] ?? ''),
            'email_verified_at' => (string) ($updates['email_verified_at'] ?? $account['email_verified_at'] ?? '') !== '' ? (string) ($updates['email_verified_at'] ?? $account['email_verified_at']) : null,
            'updated_at' => sr_now(),
            'id' => $accountId,
        ]);
    }

    $extraDefinitions = sr_member_profile_extra_field_definitions($memberSettings);
    if ($extraDefinitions !== [] && sr_member_profile_field_values_table_exists($pdo)) {
        $extraByKey = [];
        foreach ($extraDefinitions as $definition) {
            $key = (string) ($definition['key'] ?? '');
            if ($key !== '') {
                $extraByKey[$key] = $definition;
            }
        }
        $plainValues = sr_member_profile_extra_field_plain_values($pdo, $accountId);
        $extraChanged = false;
        foreach ($mapped as $target => $value) {
            $target = (string) $target;
            if (!str_starts_with($target, 'profile:')) {
                continue;
            }
            $fieldKey = substr($target, 8);
            if (!isset($extraByKey[$fieldKey])) {
                continue;
            }
            $definition = $extraByKey[$fieldKey];
            $type = (string) ($definition['type'] ?? 'text');
            if (!is_scalar($value)) {
                continue;
            }
            if ($type === 'checkbox') {
                if (is_bool($value)) {
                    $nextValue = $value ? '1' : '0';
                } else {
                    $nextValue = trim((string) $value);
                    if ($nextValue === '') {
                        continue;
                    }
                    $nextValue = sr_member_oauth_truthy($nextValue) ? '1' : '0';
                }
            } else {
                $nextValue = trim((string) $value);
                if ($nextValue === '') {
                    continue;
                }
                if ($type === 'select' && !in_array($nextValue, (array) ($definition['options'] ?? []), true)) {
                    continue;
                }
                $maxLength = sr_member_profile_extra_field_value_max_length($type);
                $nextValue = function_exists('mb_substr') ? mb_substr($nextValue, 0, $maxLength) : substr($nextValue, 0, $maxLength);
            }
            $storedValue = $plainValues[$fieldKey] ?? '';
            if ($storedValue !== $nextValue) {
                sr_member_save_profile_extra_field_value($pdo, $accountId, $definition, $nextValue);
                $plainValues[$fieldKey] = $nextValue;
                $extraChanged = true;
            }
        }
        if ($extraChanged) {
            $changed[] = 'profile_extra';
        }
    }

    return array_values(array_unique($changed));
}

function sr_member_oauth_create_completion_state(PDO $pdo, string $providerKey, string $subjectHash, array $profile, string $nextPath, int $ttlSeconds): string
{
    $state = sr_member_oauth_create_state($pdo, $providerKey, 'completion', null, $nextPath, $ttlSeconds);
    $subjectDisplay = sr_member_oauth_subject_display_from_hash($subjectHash);
    $stmt = $pdo->prepare(
        'UPDATE sr_member_oauth_states
         SET provider_subject_hash = :provider_subject_hash,
             provider_subject_display = :provider_subject_display,
             email_snapshot = :email_snapshot,
             email_verified_snapshot = :email_verified_snapshot,
             display_name_snapshot = :display_name_snapshot
         WHERE state_hash = :state_hash'
    );
    $stmt->execute([
        'provider_subject_hash' => $subjectHash,
        'provider_subject_display' => $subjectDisplay,
        'email_snapshot' => (string) ($profile['email'] ?? ''),
        'email_verified_snapshot' => !empty($profile['email_verified']) ? 1 : 0,
        'display_name_snapshot' => (string) ($profile['display_name'] ?? ''),
        'state_hash' => sr_member_oauth_hash((string) $state['state']),
    ]);

    return (string) $state['state'];
}
