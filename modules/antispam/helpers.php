<?php

declare(strict_types=1);

function sr_antispam_default_settings(): array
{
    return [
        'enabled' => false,
        'default_mode' => 'guest',
        'challenge_type' => 'math',
        'ttl_seconds' => 600,
        'min_submit_seconds' => 2,
        'provider_timeout_seconds' => 3,
        'provider_failure_policy' => 'fail_closed',
        'verify_remote_ip_enabled' => false,
        'provider_action_check_enabled' => true,
        'provider_hostname_check_enabled' => true,
        'surface_member_register' => 'always',
        'surface_community_post_guest' => 'guest',
        'surface_community_comment_guest' => 'guest',
    ];
}

function sr_antispam_settings(PDO $pdo): array
{
    return sr_antispam_normalize_settings(
        array_merge(sr_antispam_default_settings(), sr_module_settings($pdo, 'antispam')),
        sr_antispam_provider_options($pdo)
    );
}

function sr_antispam_normalize_settings(array $settings, ?array $providerOptions = null): array
{
    $settings['enabled'] = sr_antispam_bool($settings['enabled'] ?? false);
    $settings['default_mode'] = sr_antispam_mode((string) ($settings['default_mode'] ?? 'guest'));
    $settings['challenge_type'] = sr_antispam_challenge_type((string) ($settings['challenge_type'] ?? 'math'));
    $settings['ttl_seconds'] = min(3600, max(60, (int) ($settings['ttl_seconds'] ?? 600)));
    $settings['min_submit_seconds'] = min(60, max(0, (int) ($settings['min_submit_seconds'] ?? 2)));
    $settings['provider_timeout_seconds'] = min(10, max(1, (int) ($settings['provider_timeout_seconds'] ?? 3)));
    $settings['provider_failure_policy'] = in_array((string) ($settings['provider_failure_policy'] ?? ''), ['fail_closed', 'fallback_math'], true)
        ? (string) $settings['provider_failure_policy']
        : 'fail_closed';
    $settings['verify_remote_ip_enabled'] = sr_antispam_bool($settings['verify_remote_ip_enabled'] ?? false);
    $settings['provider_action_check_enabled'] = sr_antispam_bool($settings['provider_action_check_enabled'] ?? true);
    $settings['provider_hostname_check_enabled'] = sr_antispam_bool($settings['provider_hostname_check_enabled'] ?? true);
    foreach (($providerOptions ?? sr_antispam_provider_options()) as $provider) {
        $siteKeySetting = (string) ($provider['site_key_setting'] ?? '');
        $secretKeySetting = (string) ($provider['secret_key_setting'] ?? '');
        if ($siteKeySetting !== '') {
            $settings[$siteKeySetting] = trim((string) ($settings[$siteKeySetting] ?? ''));
        }
        if ($secretKeySetting !== '') {
            $settings[$secretKeySetting] = trim((string) ($settings[$secretKeySetting] ?? ''));
        }
        $scoreSetting = (string) ($provider['score_setting'] ?? '');
        if ($scoreSetting !== '') {
            $settings[$scoreSetting] = min(1.0, max(0.0, (float) ($settings[$scoreSetting] ?? 0.5)));
        }
    }
    foreach (sr_antispam_surface_keys() as $surfaceKey) {
        $settings['surface_' . str_replace('.', '_', $surfaceKey)] = sr_antispam_mode((string) ($settings['surface_' . str_replace('.', '_', $surfaceKey)] ?? $settings['default_mode']));
    }

    return $settings;
}

function sr_antispam_bool(mixed $value): bool
{
    return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
}

function sr_antispam_mode(string $value): string
{
    return in_array($value, ['off', 'guest', 'always'], true) ? $value : 'guest';
}

function sr_antispam_challenge_type(string $value): string
{
    return $value === 'math' || preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $value) === 1 ? $value : 'math';
}

function sr_antispam_surface_keys(): array
{
    return ['member.register', 'community.post.guest', 'community.comment.guest'];
}

function sr_antispam_mode_options(): array
{
    return [
        'off' => '사용 안 함',
        'guest' => '비회원',
        'always' => '항상',
    ];
}

function sr_antispam_challenge_type_options(?PDO $pdo = null): array
{
    $options = [
        'math' => '산술 문제',
    ];

    foreach (sr_antispam_provider_options($pdo) as $providerKey => $provider) {
        $options[$providerKey] = (string) ($provider['label'] ?? $providerKey);
    }

    return $options;
}

function sr_antispam_provider_options(?PDO $pdo = null): array
{
    $providers = [];
    $contractFiles = [];
    if ($pdo instanceof PDO) {
        $contractFiles = sr_enabled_module_contract_files($pdo, 'antispam-providers.php', ['antispam']);
    } else {
        $bundledProviderFile = SR_ROOT . '/modules/antispam_captcha_providers/antispam-providers.php';
        if (is_file($bundledProviderFile)) {
            $contractFiles = ['antispam_captcha_providers' => $bundledProviderFile];
        }
    }

    foreach ($contractFiles as $moduleKey => $file) {
        $contract = $pdo instanceof PDO
            ? sr_load_module_contract_file((string) $moduleKey, (string) $file)
            : include (string) $file;
        if (!is_array($contract)) {
            continue;
        }

        foreach ($contract as $providerKey => $provider) {
            $providerKey = is_string($providerKey) ? $providerKey : '';
            if ($providerKey === '' || preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $providerKey) !== 1 || !is_array($provider)) {
                continue;
            }

            $normalized = sr_antispam_normalize_provider_definition($provider);
            if ($normalized !== []) {
                $providers[$providerKey] = $normalized;
            }
        }
    }

    ksort($providers);
    return $providers;
}

function sr_antispam_normalize_provider_definition(array $provider): array
{
    $label = trim((string) ($provider['label'] ?? ''));
    $siteKeySetting = trim((string) ($provider['site_key_setting'] ?? ''));
    $secretKeySetting = trim((string) ($provider['secret_key_setting'] ?? ''));
    $responseField = trim((string) ($provider['response_field'] ?? ''));
    $endpoint = trim((string) ($provider['endpoint'] ?? ''));
    $scriptUrl = trim((string) ($provider['script_url'] ?? ''));
    $widgetClass = trim((string) ($provider['widget_class'] ?? ''));
    $scoreSetting = trim((string) ($provider['score_setting'] ?? ''));
    if (
        $label === ''
        || preg_match('/\A[a-z][a-z0-9_]{1,80}\z/', $siteKeySetting) !== 1
        || preg_match('/\A[a-z][a-z0-9_]{1,80}\z/', $secretKeySetting) !== 1
        || $responseField === ''
        || $endpoint === ''
        || $scriptUrl === ''
        || $widgetClass === ''
        || filter_var($endpoint, FILTER_VALIDATE_URL) === false
        || filter_var($scriptUrl, FILTER_VALIDATE_URL) === false
    ) {
        return [];
    }

    return [
        'label' => $label,
        'site_key_setting' => $siteKeySetting,
        'secret_key_setting' => $secretKeySetting,
        'response_field' => $responseField,
        'endpoint' => $endpoint,
        'script_url' => $scriptUrl,
        'widget_class' => $widgetClass,
        'score_setting' => preg_match('/\A[a-z][a-z0-9_]{1,80}\z/', $scoreSetting) === 1 ? $scoreSetting : '',
    ];
}

function sr_antispam_policy(PDO $pdo, string $surface, array $context = []): array
{
    $settings = sr_antispam_settings($pdo);
    $mode = (string) ($settings['surface_' . str_replace('.', '_', $surface)] ?? $settings['default_mode']);
    $account = $context['account'] ?? null;
    $isGuest = !is_array($account);
    $required = !empty($settings['enabled']) && ($mode === 'always' || ($mode === 'guest' && $isGuest));
    $type = (string) $settings['challenge_type'];
    $providers = sr_antispam_provider_options($pdo);
    if ($type !== 'math' && isset($providers[$type])) {
        $siteKey = (string) ($settings[(string) $providers[$type]['site_key_setting']] ?? '');
        $secretKey = (string) ($settings[(string) $providers[$type]['secret_key_setting']] ?? '');
        if ($siteKey === '' || $secretKey === '') {
            $type = (string) $settings['provider_failure_policy'] === 'fallback_math' ? 'math' : $type;
        }
    }

    return [
        'required' => $required,
        'surface' => $surface,
        'mode' => $mode,
        'type' => $type,
        'settings' => $settings,
    ];
}

function sr_antispam_session_key(string $surface, string $formKey): string
{
    return 'sr_antispam_' . hash('sha256', $surface . '|' . $formKey);
}

function sr_antispam_challenge_create(string $surface, string $formKey, array $options = []): array
{
    $left = random_int(2, 9);
    $right = random_int(1, 9);
    $operator = random_int(0, 1) === 1 ? '+' : '-';
    if ($operator === '-' && $right > $left) {
        [$left, $right] = [$right, $left];
    }
    $answer = $operator === '+' ? $left + $right : $left - $right;
    $issuedAt = time();
    $token = bin2hex(random_bytes(16));
    $_SESSION[sr_antispam_session_key($surface, $formKey)] = [
        'answer_hash' => hash_hmac('sha256', (string) $answer, $token),
        'token' => $token,
        'question' => (string) $left . ' ' . $operator . ' ' . (string) $right,
        'issued_at' => $issuedAt,
        'expires_at' => $issuedAt + min(3600, max(60, (int) ($options['ttl_seconds'] ?? 600))),
    ];

    return [
        'surface' => $surface,
        'form_key' => $formKey,
        'type' => 'math',
        'question' => (string) $left . ' ' . $operator . ' ' . (string) $right,
    ];
}

function sr_antispam_challenge_render(PDO $pdo, string $surface, string $formKey, array $context = []): string
{
    $policy = sr_antispam_policy($pdo, $surface, $context);
    if (empty($policy['required'])) {
        return '';
    }

    $settings = is_array($policy['settings'] ?? null) ? $policy['settings'] : sr_antispam_settings($pdo);
    $html = '<div class="sr-antispam-challenge">';
    $html .= '<input type="text" name="sr_antispam_hp" value="" tabindex="-1" autocomplete="off" class="sr-antispam-hp" aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden;">';
    $html .= '<input type="hidden" name="sr_antispam_form_key" value="' . sr_e($formKey) . '">';
    if ((string) $policy['type'] === 'math') {
        $challenge = sr_antispam_challenge_create($surface, $formKey, ['ttl_seconds' => (int) $settings['ttl_seconds']]);
        $inputId = 'sr_antispam_answer_' . substr(hash('sha256', $surface . $formKey), 0, 12);
        $html .= '<p><label for="' . sr_e($inputId) . '"><span>자동등록방지 <span class="sr-required-label">(필수)</span></span>';
        $html .= '<span class="sr-antispam-question">' . sr_e((string) $challenge['question']) . ' = ?</span>';
        $html .= '<input id="' . sr_e($inputId) . '" type="text" name="sr_antispam_answer" inputmode="numeric" autocomplete="off" required></label></p>';
    } else {
        $challenge = sr_antispam_challenge_create($surface, $formKey, ['ttl_seconds' => (int) $settings['ttl_seconds']]);
        $providers = sr_antispam_provider_options($pdo);
        $provider = $providers[(string) $policy['type']] ?? null;
        $siteKey = is_array($provider) ? (string) ($settings[(string) $provider['site_key_setting']] ?? '') : '';
        if (is_array($provider) && $siteKey !== '') {
            $html .= '<script src="' . sr_e((string) $provider['script_url']) . '" async defer></script>';
            $html .= '<div class="' . sr_e((string) $provider['widget_class']) . '" data-sitekey="' . sr_e($siteKey) . '"></div>';
        }
        if ((string) ($settings['provider_failure_policy'] ?? '') === 'fallback_math') {
            $inputId = 'sr_antispam_answer_' . substr(hash('sha256', $surface . $formKey), 0, 12);
            $html .= '<p><label for="' . sr_e($inputId) . '"><span>자동등록방지 예비 문제</span>';
            $html .= '<span class="sr-antispam-question">' . sr_e((string) $challenge['question']) . ' = ?</span>';
            $html .= '<input id="' . sr_e($inputId) . '" type="text" name="sr_antispam_answer" inputmode="numeric" autocomplete="off"></label></p>';
        }
    }
    $html .= '</div>';

    return $html;
}

function sr_antispam_verify(PDO $pdo, string $surface, string $formKey, array $post, array $context = []): array
{
    $policy = sr_antispam_policy($pdo, $surface, $context);
    if (empty($policy['required'])) {
        return ['ok' => true, 'required' => false, 'errors' => [], 'provider' => 'off'];
    }

    $settings = is_array($policy['settings'] ?? null) ? $policy['settings'] : sr_antispam_settings($pdo);
    $errors = [];
    if (trim((string) ($post['sr_antispam_hp'] ?? '')) !== '') {
        $errors[] = '자동등록방지 검증에 실패했습니다.';
    }
    if ((string) ($post['sr_antispam_form_key'] ?? '') !== $formKey) {
        $errors[] = '자동등록방지 요청 값이 올바르지 않습니다.';
    }
    if ($errors !== []) {
        return ['ok' => false, 'required' => true, 'errors' => $errors, 'provider' => (string) $policy['type']];
    }

    if ((string) $policy['type'] === 'math') {
        $errors = array_merge($errors, sr_antispam_verify_math($surface, $formKey, $post, $settings));
        return ['ok' => $errors === [], 'required' => true, 'errors' => $errors, 'provider' => 'math'];
    }

    $errors = array_merge($errors, sr_antispam_verify_local_timing($surface, $formKey, $settings));
    if ($errors !== []) {
        return ['ok' => false, 'required' => true, 'errors' => $errors, 'provider' => (string) $policy['type']];
    }

    $providerResult = sr_antispam_provider_verify($pdo, (string) $policy['type'], $post, $context + [
        'settings' => $settings,
        'form_key' => $formKey,
    ]);
    if (empty($providerResult['ok']) && (string) $settings['provider_failure_policy'] === 'fallback_math') {
        $errors = array_merge($errors, sr_antispam_verify_math($surface, $formKey, $post, $settings));
        return ['ok' => $errors === [], 'required' => true, 'errors' => $errors, 'provider' => 'math'];
    }
    if (empty($providerResult['ok'])) {
        $errors[] = '자동등록방지 검증에 실패했습니다.';
    } else {
        unset($_SESSION[sr_antispam_session_key($surface, $formKey)]);
    }

    return ['ok' => $errors === [], 'required' => true, 'errors' => $errors, 'provider' => (string) $policy['type'], 'codes' => $providerResult['codes'] ?? []];
}

function sr_antispam_verify_math(string $surface, string $formKey, array $post, array $settings): array
{
    $sessionKey = sr_antispam_session_key($surface, $formKey);
    $stored = $_SESSION[$sessionKey] ?? null;
    unset($_SESSION[$sessionKey]);
    if (!is_array($stored)) {
        return ['자동등록방지 문제가 만료되었습니다. 다시 제출해 주세요.'];
    }
    if ((int) ($stored['expires_at'] ?? 0) < time()) {
        return ['자동등록방지 문제가 만료되었습니다. 다시 제출해 주세요.'];
    }
    if (time() - (int) ($stored['issued_at'] ?? 0) < (int) ($settings['min_submit_seconds'] ?? 0)) {
        return ['자동등록방지 제출 시간이 너무 짧습니다. 잠시 후 다시 시도해 주세요.'];
    }
    $answer = trim((string) ($post['sr_antispam_answer'] ?? ''));
    $token = (string) ($stored['token'] ?? '');
    $expected = (string) ($stored['answer_hash'] ?? '');
    if ($answer === '' || $token === '' || $expected === '' || !hash_equals($expected, hash_hmac('sha256', $answer, $token))) {
        return ['자동등록방지 정답을 확인해 주세요.'];
    }

    return [];
}

function sr_antispam_verify_local_timing(string $surface, string $formKey, array $settings): array
{
    $stored = $_SESSION[sr_antispam_session_key($surface, $formKey)] ?? null;
    if (!is_array($stored)) {
        return ['자동등록방지 문제가 만료되었습니다. 다시 제출해 주세요.'];
    }
    if ((int) ($stored['expires_at'] ?? 0) < time()) {
        return ['자동등록방지 문제가 만료되었습니다. 다시 제출해 주세요.'];
    }
    if (time() - (int) ($stored['issued_at'] ?? 0) < (int) ($settings['min_submit_seconds'] ?? 0)) {
        return ['자동등록방지 제출 시간이 너무 짧습니다. 잠시 후 다시 시도해 주세요.'];
    }

    return [];
}

function sr_antispam_provider_verify(PDO $pdo, string $providerKey, array $post, array $context = []): array
{
    $providers = sr_antispam_provider_options($pdo);
    if (!isset($providers[$providerKey])) {
        return ['ok' => false, 'codes' => ['provider_invalid']];
    }
    $settings = is_array($context['settings'] ?? null) ? $context['settings'] : sr_antispam_settings($pdo);
    $provider = $providers[$providerKey];
    $secret = (string) ($settings[(string) $provider['secret_key_setting']] ?? '');
    $token = trim((string) ($post[(string) $provider['response_field']] ?? ''));
    if ($secret === '' || $token === '') {
        return ['ok' => false, 'codes' => ['missing_input']];
    }
    $payload = ['secret' => $secret, 'response' => $token];
    if (!empty($settings['verify_remote_ip_enabled'])) {
        $payload['remoteip'] = sr_client_ip();
    }
    $response = sr_antispam_http_post((string) $provider['endpoint'], $payload, (int) $settings['provider_timeout_seconds']);
    if (!is_array($response)) {
        return ['ok' => false, 'codes' => ['provider_unavailable']];
    }

    return sr_antispam_provider_response_result($providerKey, $response, $context);
}

function sr_antispam_provider_response_result(string $providerKey, array $response, array $context = []): array
{
    $codes = [];
    foreach ((array) ($response['error-codes'] ?? []) as $code) {
        $codes[] = preg_replace('/[^a-zA-Z0-9_.-]/', '', (string) $code) ?: 'error';
    }
    $ok = !empty($response['success']);
    if ($ok && $providerKey === 'recaptcha' && isset($response['score'])) {
        $settings = is_array($context['settings'] ?? null) ? $context['settings'] : [];
        $ok = (float) $response['score'] >= (float) ($settings['recaptcha_min_score'] ?? 0.5);
        if (!$ok) {
            $codes[] = 'score_low';
        }
    }
    $settings = is_array($context['settings'] ?? null) ? $context['settings'] : [];
    $actionCheckEnabled = sr_antispam_bool($settings['provider_action_check_enabled'] ?? true);
    $hostnameCheckEnabled = sr_antispam_bool($settings['provider_hostname_check_enabled'] ?? true);
    $expectedAction = trim((string) ($context['expected_action'] ?? $context['form_key'] ?? ''));
    if ($ok && $actionCheckEnabled && isset($response['action']) && $expectedAction !== '' && !hash_equals($expectedAction, (string) $response['action'])) {
        $ok = false;
        $codes[] = 'action_mismatch';
    }
    $expectedHostname = trim((string) ($context['expected_hostname'] ?? sr_antispam_current_hostname()));
    $responseHostname = strtolower(trim((string) ($response['hostname'] ?? '')));
    if ($ok && $hostnameCheckEnabled && isset($response['hostname']) && $expectedHostname !== '' && !hash_equals(strtolower($expectedHostname), $responseHostname)) {
        $ok = false;
        $codes[] = 'hostname_mismatch';
    }

    return ['ok' => $ok, 'codes' => array_values(array_unique($codes))];
}

function sr_antispam_current_hostname(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }
    $host = preg_replace('/:\d+\z/', '', $host);

    return is_string($host) ? strtolower($host) : '';
}

function sr_antispam_http_post(string $url, array $payload, int $timeoutSeconds): ?array
{
    if (!filter_var($url, FILTER_VALIDATE_URL) || !ini_get('allow_url_fopen')) {
        return null;
    }
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($payload, '', '&'),
            'timeout' => min(10, max(1, $timeoutSeconds)),
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if (!is_string($body) || $body === '') {
        return null;
    }
    $decoded = json_decode($body, true);

    return is_array($decoded) ? $decoded : null;
}

function sr_antispam_secret_display(string $value): string
{
    return $value === '' ? '' : '********';
}

function sr_antispam_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'antispam' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('Antispam module is not installed.');
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
    sr_clear_module_settings_cache('antispam');
}
