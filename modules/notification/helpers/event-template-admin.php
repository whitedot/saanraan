<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/notification/helpers.php';

function sr_notification_event_template_admin_channels(PDO $pdo): array
{
    $channels = function_exists('sr_notification_create_channels') ? sr_notification_create_channels($pdo) : ['site'];
    $channels[] = 'email';
    if (function_exists('sr_notification_member_external_channel_keys')
        && function_exists('sr_notification_member_external_provider_is_ready')
        && function_exists('sr_notification_settings')
    ) {
        $settings = sr_notification_settings($pdo);
        foreach (sr_notification_member_external_channel_keys() as $channel) {
            if (sr_notification_member_external_provider_is_ready($channel, $settings)) {
                $channels[] = $channel;
            }
        }
    }

    return sr_notification_normalize_channels($channels);
}

function sr_notification_event_template_admin_cases(string $moduleKey): array
{
    if (!sr_is_safe_module_key($moduleKey)) {
        return [];
    }
    $function = 'sr_' . $moduleKey . '_notification_cases';
    if (!function_exists($function)) {
        return [];
    }
    $cases = $function();

    return is_array($cases) ? $cases : [];
}

function sr_notification_event_template_admin_case_key(string $moduleKey, string $eventKey): string
{
    if (!sr_is_safe_module_key($moduleKey)) {
        return '';
    }
    $function = 'sr_' . $moduleKey . '_notification_case_key_for_event';
    if (function_exists($function)) {
        return (string) $function($eventKey);
    }
    foreach (sr_notification_event_template_admin_cases($moduleKey) as $caseKey => $case) {
        if ((string) ($case['event_key'] ?? '') === $eventKey) {
            return (string) $caseKey;
        }
    }

    return '';
}

function sr_notification_event_template_admin_case_settings(PDO $pdo, string $moduleKey): array
{
    if (!sr_is_safe_module_key($moduleKey)) {
        return [];
    }
    $settingsFunction = 'sr_' . $moduleKey . '_settings';
    $normalizeFunction = 'sr_' . $moduleKey . '_notification_case_settings_from_value';
    if (!function_exists($settingsFunction) || !function_exists($normalizeFunction)) {
        return [];
    }
    $settings = $settingsFunction($pdo);
    if (!is_array($settings)) {
        return [];
    }
    $caseSettings = $normalizeFunction($settings['notification_cases'] ?? []);

    return is_array($caseSettings) ? $caseSettings : [];
}

function sr_notification_event_template_admin_label(string $moduleKey, string $eventKey, string $titleTemplate): string
{
    $caseKey = sr_notification_event_template_admin_case_key($moduleKey, $eventKey);
    $cases = sr_notification_event_template_admin_cases($moduleKey);
    if ($caseKey !== '' && isset($cases[$caseKey]) && is_array($cases[$caseKey])) {
        $label = sr_notification_clean_single_line((string) ($cases[$caseKey]['label'] ?? ''), 120);
        if ($label !== '') {
            return $label;
        }
    }

    $label = preg_replace('/\{[a-zA-Z0-9_]{1,80}\}/', '', $titleTemplate) ?? '';
    $label = sr_notification_clean_single_line(trim($label, " \t\n\r\0\x0B:-/|"), 120);
    return $label !== '' ? $label : $eventKey;
}

function sr_notification_event_template_admin_variables(array $row): array
{
    $variables = [];
    foreach (['title_template', 'body_template', 'link_template'] as $field) {
        foreach (sr_delivery_template_placeholders((string) ($row[$field] ?? '')) as $placeholder) {
            $variables[$placeholder] = sr_delivery_template_variable_label($placeholder);
        }
    }

    return $variables;
}

function sr_notification_channel_template_code(string $value): string
{
    $value = sr_notification_clean_single_line($value, 120);
    return preg_match('/\A[A-Za-z0-9._-]{1,120}\z/', $value) === 1 ? $value : '';
}

function sr_notification_event_template_admin_channel_bindings(PDO $pdo, string $moduleKey): array
{
    if (!sr_is_safe_module_key($moduleKey)) {
        return [];
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT module_key, event_key, channel, provider_template_code, variables_json, status
             FROM sr_notification_channel_template_bindings
             WHERE module_key = :module_key'
        );
        $stmt->execute(['module_key' => $moduleKey]);
    } catch (Throwable) {
        return [];
    }

    $bindings = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $eventKey = (string) ($row['event_key'] ?? '');
        $channel = (string) ($row['channel'] ?? '');
        if ($eventKey === '' || $channel === '') {
            continue;
        }
        $bindings[$eventKey][$channel] = $row;
    }

    return $bindings;
}

function sr_notification_event_template_admin_save_channel_binding(PDO $pdo, string $moduleKey, string $eventKey, string $channel, string $providerTemplateCode, array $variables = []): void
{
    if (!sr_is_safe_module_key($moduleKey) || preg_match('/\A[a-z0-9_.-]{1,120}\z/', $eventKey) !== 1) {
        throw new InvalidArgumentException('채널 템플릿 항목을 찾을 수 없습니다.');
    }
    if ($channel !== 'alimtalk') {
        throw new InvalidArgumentException('지원하지 않는 채널 템플릿입니다.');
    }

    $providerTemplateCode = sr_notification_channel_template_code($providerTemplateCode);
    if ($providerTemplateCode === '') {
        $stmt = $pdo->prepare(
            'DELETE FROM sr_notification_channel_template_bindings
             WHERE module_key = :module_key
               AND event_key = :event_key
               AND channel = :channel'
        );
        $stmt->execute([
            'module_key' => $moduleKey,
            'event_key' => $eventKey,
            'channel' => $channel,
        ]);
        return;
    }

    $cleanVariables = [];
    foreach ($variables as $key => $value) {
        if (is_string($key) && preg_match('/\A[a-zA-Z0-9_]{1,80}\z/', $key) === 1 && is_scalar($value)) {
            $cleanVariables[$key] = sr_notification_clean_single_line((string) $value, 120);
        }
    }
    $variablesJson = $cleanVariables === []
        ? ''
        : (json_encode($cleanVariables, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    $now = sr_now();
    $driver = '';
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable) {
        $driver = '';
    }
    $sql = 'INSERT INTO sr_notification_channel_template_bindings
            (module_key, event_key, channel, provider_template_code, variables_json, status, created_at, updated_at)
        VALUES
            (:module_key, :event_key, :channel, :provider_template_code, :variables_json, \'active\', :created_at, :updated_at)
        ON DUPLICATE KEY UPDATE
            provider_template_code = VALUES(provider_template_code),
            variables_json = VALUES(variables_json),
            status = VALUES(status),
            updated_at = VALUES(updated_at)';
    if ($driver === 'sqlite') {
        $sql = 'INSERT INTO sr_notification_channel_template_bindings
                (module_key, event_key, channel, provider_template_code, variables_json, status, created_at, updated_at)
            VALUES
                (:module_key, :event_key, :channel, :provider_template_code, :variables_json, \'active\', :created_at, :updated_at)
            ON CONFLICT(module_key, event_key, channel) DO UPDATE SET
                provider_template_code = excluded.provider_template_code,
                variables_json = excluded.variables_json,
                status = excluded.status,
                updated_at = excluded.updated_at';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'module_key' => $moduleKey,
        'event_key' => $eventKey,
        'channel' => $channel,
        'provider_template_code' => $providerTemplateCode,
        'variables_json' => $variablesJson,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function sr_notification_event_template_admin_rows(PDO $pdo, string $moduleKey): array
{
    if (!sr_is_safe_module_key($moduleKey)) {
        return [];
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT module_key, event_key, title_template, body_template, link_template, channels_json, status
             FROM sr_notification_event_templates
             WHERE module_key = :module_key
             ORDER BY event_key ASC'
        );
        $stmt->execute(['module_key' => $moduleKey]);
    } catch (Throwable) {
        return [];
    }

    $caseSettings = sr_notification_event_template_admin_case_settings($pdo, $moduleKey);
    $channelBindings = sr_notification_event_template_admin_channel_bindings($pdo, $moduleKey);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $eventKey = (string) ($row['event_key'] ?? '');
        $caseKey = sr_notification_event_template_admin_case_key($moduleKey, $eventKey);
        $channels = sr_notification_template_channels((string) ($row['channels_json'] ?? ''));
        $enabled = (string) ($row['status'] ?? 'active') === 'active';
        if ($caseKey !== '' && isset($caseSettings[$caseKey]) && is_array($caseSettings[$caseKey])) {
            $enabled = !empty($caseSettings[$caseKey]['enabled']);
            $channelsFunction = 'sr_' . $moduleKey . '_notification_channels_from_value';
            $channels = function_exists($channelsFunction)
                ? $channelsFunction($caseSettings[$caseKey]['channels'] ?? ['site'])
                : sr_notification_template_channels(json_encode($caseSettings[$caseKey]['channels'] ?? ['site']));
        }
        $row['case_key'] = $caseKey;
        $row['label'] = sr_notification_event_template_admin_label($moduleKey, $eventKey, (string) ($row['title_template'] ?? ''));
        $row['enabled'] = $enabled;
        $row['channels'] = $channels;
        $row['channel_templates'] = isset($channelBindings[$eventKey]) && is_array($channelBindings[$eventKey]) ? $channelBindings[$eventKey] : [];
        $row['variables'] = sr_notification_event_template_admin_variables($row);
        $rows[] = $row;
    }

    return $rows;
}

function sr_notification_event_template_admin_delivery_rows(PDO $pdo, string $moduleKey, array $templateKeys = []): array
{
    if (!sr_is_safe_module_key($moduleKey)) {
        return [];
    }
    $allowedKeys = [];
    foreach ($templateKeys as $templateKey) {
        $templateKey = sr_delivery_template_key((string) $templateKey);
        if ($templateKey !== '') {
            $allowedKeys[$templateKey] = true;
        }
    }

    $rows = [];
    foreach (sr_delivery_template_contracts($pdo) as $templateKey => $contract) {
        if (!is_array($contract)
            || (string) ($contract['owner_module'] ?? '') !== $moduleKey
            || (string) ($contract['category'] ?? '') !== 'transactional_email'
            || ($allowedKeys !== [] && !isset($allowedKeys[(string) $templateKey]))
        ) {
            continue;
        }
        $effective = sr_delivery_template_effective($pdo, (string) $templateKey);
        $row = is_array($effective) ? $effective : $contract;
        $row['row_type'] = 'delivery';
        $row['template_key'] = (string) $templateKey;
        $row['event_key'] = (string) $templateKey;
        $row['label'] = sr_delivery_template_display_label($contract, (string) $templateKey);
        $row['title_template'] = (string) ($row['subject_template'] ?? '');
        $row['channels'] = isset($row['channels']) && is_array($row['channels']) ? $row['channels'] : ['email'];
        $row['available_channels'] = isset($contract['available_channels']) && is_array($contract['available_channels']) ? $contract['available_channels'] : $row['channels'];
        $row['enabled'] = (string) ($row['status'] ?? 'active') === 'active';
        $row['has_override'] = !empty($row['has_override']);
        $row['variables'] = isset($contract['variables']) && is_array($contract['variables']) ? $contract['variables'] : [];
        $row['required_variables'] = isset($contract['required_variables']) && is_array($contract['required_variables']) ? $contract['required_variables'] : [];
        $row['body_editable'] = !empty($contract['body_editable']);
        $rows[] = $row;
    }

    return $rows;
}

function sr_notification_event_template_admin_save_module_case_settings(PDO $pdo, string $moduleKey, string $eventKey, bool $enabled, array $channels): void
{
    $caseKey = sr_notification_event_template_admin_case_key($moduleKey, $eventKey);
    if ($caseKey === '') {
        return;
    }
    $settingsFunction = 'sr_' . $moduleKey . '_settings';
    $saveFunction = 'sr_' . $moduleKey . '_save_settings';
    $normalizeFunction = 'sr_' . $moduleKey . '_notification_case_settings_from_value';
    if (!function_exists($settingsFunction) || !function_exists($saveFunction) || !function_exists($normalizeFunction)) {
        return;
    }

    $settings = $settingsFunction($pdo);
    if (!is_array($settings)) {
        return;
    }
    $caseSettings = $normalizeFunction($settings['notification_cases'] ?? []);
    if (!is_array($caseSettings)) {
        $caseSettings = [];
    }
    if (!isset($caseSettings[$caseKey]) || !is_array($caseSettings[$caseKey])) {
        $caseSettings[$caseKey] = ['event_key' => $eventKey, 'enabled' => $enabled, 'channels' => ['site']];
    }
    $caseSettings[$caseKey]['event_key'] = $eventKey;
    $caseSettings[$caseKey]['enabled'] = $enabled;
    $caseSettings[$caseKey]['channels'] = $channels;
    $settings['notification_cases'] = $caseSettings;
    if ($moduleKey === 'coupon' && $caseKey === 'definition_disabled') {
        $settings['disabled_reclaim_notifications_enabled'] = $enabled;
        $settings['disabled_reclaim_notification_channels'] = $channels;
    }
    $saveFunction($pdo, $settings);
}

function sr_notification_event_template_admin_save(PDO $pdo, string $moduleKey, string $eventKey, array $data): void
{
    if (!sr_is_safe_module_key($moduleKey) || preg_match('/\A[a-z0-9_.-]{1,120}\z/', $eventKey) !== 1) {
        throw new InvalidArgumentException('알림/메일 항목을 찾을 수 없습니다.');
    }
    $title = sr_notification_clean_single_line((string) ($data['title_template'] ?? ''), 160);
    $body = sr_notification_clean_text((string) ($data['body_template'] ?? ''), 4000);
    $enabled = !empty($data['enabled']);
    $channels = isset($data['channels']) && is_array($data['channels']) ? $data['channels'] : [];
    $availableChannels = sr_notification_event_template_admin_channels($pdo);
    if (function_exists('sr_notification_member_external_channel_keys')) {
        $availableChannels = array_values(array_unique(array_merge($availableChannels, sr_notification_member_external_channel_keys())));
    }
    $allowed = array_fill_keys($availableChannels, true);
    $normalizedChannels = [];
    foreach (sr_notification_normalize_channels($channels) as $channel) {
        if (isset($allowed[$channel])) {
            $normalizedChannels[] = $channel;
        }
    }
    if ($enabled && $normalizedChannels === []) {
        throw new InvalidArgumentException('발송 수단을 하나 이상 선택하세요.');
    }
    if ($normalizedChannels === []) {
        $normalizedChannels = ['site'];
    }
    if ($title === '') {
        throw new InvalidArgumentException('제목을 입력하세요.');
    }
    if ($body === '') {
        throw new InvalidArgumentException('본문을 입력하세요.');
    }
    $alimtalkTemplateCode = sr_notification_channel_template_code((string) ($data['alimtalk_template_code'] ?? ''));
    if (in_array('alimtalk', $normalizedChannels, true) && $alimtalkTemplateCode === '') {
        throw new InvalidArgumentException('알림톡 채널은 카카오 승인 템플릿 코드를 입력해야 합니다.');
    }
    $existsStmt = $pdo->prepare(
        'SELECT link_template
         FROM sr_notification_event_templates
         WHERE module_key = :module_key
           AND event_key = :event_key
         LIMIT 1'
    );
    $existsStmt->execute([
        'module_key' => $moduleKey,
        'event_key' => $eventKey,
    ]);
    $currentLinkTemplate = $existsStmt->fetchColumn();
    if ($currentLinkTemplate === false) {
        throw new InvalidArgumentException('알림/메일 항목을 찾을 수 없습니다.');
    }
    $link = (string) $currentLinkTemplate;

    $stmt = $pdo->prepare(
        'UPDATE sr_notification_event_templates
         SET title_template = :title_template,
             body_template = :body_template,
             link_template = :link_template,
             channels_json = :channels_json,
             status = :status,
             updated_at = :updated_at
         WHERE module_key = :module_key
           AND event_key = :event_key'
    );
    $stmt->execute([
        'title_template' => $title,
        'body_template' => $body,
        'link_template' => $link,
        'channels_json' => json_encode(array_values($normalizedChannels), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'status' => $enabled ? 'active' : 'inactive',
        'updated_at' => sr_now(),
        'module_key' => $moduleKey,
        'event_key' => $eventKey,
    ]);

    if (in_array('alimtalk', $normalizedChannels, true) || (string) ($data['alimtalk_template_code'] ?? '') !== '') {
        sr_notification_event_template_admin_save_channel_binding($pdo, $moduleKey, $eventKey, 'alimtalk', in_array('alimtalk', $normalizedChannels, true) ? $alimtalkTemplateCode : '');
    }
    sr_notification_event_template_admin_save_module_case_settings($pdo, $moduleKey, $eventKey, $enabled, $normalizedChannels);
}

function sr_notification_event_template_admin_save_delivery(PDO $pdo, string $moduleKey, string $templateKey, array $data, int $accountId): void
{
    $templateKey = sr_delivery_template_key($templateKey);
    $contract = $templateKey !== '' ? sr_delivery_template_contract($pdo, $templateKey) : null;
    if (!is_array($contract)
        || (string) ($contract['owner_module'] ?? '') !== $moduleKey
        || (string) ($contract['category'] ?? '') !== 'transactional_email'
    ) {
        throw new InvalidArgumentException('메일 템플릿을 찾을 수 없습니다.');
    }
    if (empty($contract['editable'])) {
        throw new InvalidArgumentException('수정할 수 없는 메일 템플릿입니다.');
    }

    $subject = sr_clean_single_line((string) ($data['title_template'] ?? ''), 190);
    $body = sr_clean_text((string) ($data['body_template'] ?? ''), 5000);
    $link = sr_clean_single_line((string) ($data['link_template'] ?? ''), 255);
    $channels = isset($data['channels']) && is_array($data['channels']) ? $data['channels'] : [];
    $status = !empty($data['enabled']) ? 'active' : 'inactive';
    sr_delivery_template_save_override($pdo, $contract, $subject, $body, $link, $channels, $status, $accountId);
}

function sr_notification_event_template_admin_save_bulk_status(PDO $pdo, string $moduleKey, bool $enabled): int
{
    if (!sr_is_safe_module_key($moduleKey)) {
        throw new InvalidArgumentException('알림/메일 항목을 찾을 수 없습니다.');
    }
    $rows = sr_notification_event_template_admin_rows($pdo, $moduleKey);
    if ($rows === []) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_notification_event_templates
         SET status = :status,
             updated_at = :updated_at
         WHERE module_key = :module_key'
    );
    $stmt->execute([
        'status' => $enabled ? 'active' : 'inactive',
        'updated_at' => sr_now(),
        'module_key' => $moduleKey,
    ]);

    foreach ($rows as $row) {
        $eventKey = (string) ($row['event_key'] ?? '');
        if (preg_match('/\A[a-z0-9_.-]{1,120}\z/', $eventKey) !== 1) {
            continue;
        }
        $channels = isset($row['channels']) && is_array($row['channels']) ? $row['channels'] : ['site'];
        sr_notification_event_template_admin_save_module_case_settings($pdo, $moduleKey, $eventKey, $enabled, $channels);
    }

    return count($rows);
}

function sr_notification_event_template_admin_handle(PDO $pdo, ?array $site, array $context): void
{
    require_once SR_ROOT . '/modules/member/helpers.php';
    require_once SR_ROOT . '/modules/admin/helpers.php';

    $account = sr_member_require_login($pdo);
    $moduleKey = (string) ($context['module_key'] ?? '');
    $permissionPath = (string) ($context['permission_path'] ?? '');
    $returnPath = (string) ($context['return_path'] ?? $permissionPath);
    if (!sr_is_safe_module_key($moduleKey) || $permissionPath === '') {
        sr_render_error(404, '알림/메일 관리 화면을 찾을 수 없습니다.');
        return;
    }

    sr_admin_require_permission($pdo, (int) $account['id'], $permissionPath, 'view');
    $flashResult = sr_admin_pop_flash_result();
    $errors = $flashResult['errors'];
    $notice = (string) $flashResult['notice'];

    if (sr_request_method() === 'POST') {
        sr_require_csrf();
        sr_admin_require_permission($pdo, (int) $account['id'], $permissionPath, 'edit');
        $intent = sr_post_string('intent', 40);
        $templateType = sr_post_string('template_type', 30);
        $eventKey = sr_post_string('event_key', 120);
        $templateKey = sr_delivery_template_key(sr_post_string('template_key', 190));
        try {
            if ($intent === 'bulk_status') {
                $enabled = sr_post_string('bulk_enabled', 1) === '1';
                $updatedCount = sr_notification_event_template_admin_save_bulk_status($pdo, $moduleKey, $enabled);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'notification_event_template.bulk_status_updated',
                    'target_type' => 'notification_event_template',
                    'target_id' => $moduleKey . '.*',
                    'result' => 'success',
                    'message' => 'Notification event template statuses updated.',
                    'metadata' => [
                        'module_key' => $moduleKey,
                        'enabled' => $enabled,
                        'updated_count' => $updatedCount,
                    ],
                ]);
                sr_admin_flash_result(sr_admin_action_result([], '알림 항목 ' . number_format($updatedCount) . '건을 ' . ($enabled ? '사용' : '중지') . '으로 변경했습니다.'));
            } elseif ($templateType === 'delivery' && $intent === 'restore') {
                $contract = $templateKey !== '' ? sr_delivery_template_contract($pdo, $templateKey) : null;
                if (!is_array($contract)
                    || (string) ($contract['owner_module'] ?? '') !== $moduleKey
                    || (string) ($contract['category'] ?? '') !== 'transactional_email'
                ) {
                    throw new InvalidArgumentException('메일 템플릿을 찾을 수 없습니다.');
                }
                sr_delivery_template_delete_override($pdo, $templateKey);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'delivery_template.restored',
                    'target_type' => 'delivery_template',
                    'target_id' => $templateKey,
                    'result' => 'success',
                    'message' => 'Delivery template override restored to default.',
                    'metadata' => [
                        'module_key' => $moduleKey,
                        'template_key' => $templateKey,
                    ],
                ]);
                sr_admin_flash_result(sr_admin_action_result([], '메일 템플릿을 기본값으로 복원했습니다.'));
            } elseif ($templateType === 'delivery') {
                sr_notification_event_template_admin_save_delivery($pdo, $moduleKey, $templateKey, [
                    'title_template' => sr_post_string('title_template', 190),
                    'body_template' => sr_post_string_without_truncation('body_template', 5000) ?? '',
                    'link_template' => sr_post_string('link_template', 255),
                    'enabled' => sr_post_string('enabled', 1) === '1',
                    'channels' => isset($_POST['channels']) && is_array($_POST['channels']) ? array_values(array_map('strval', $_POST['channels'])) : [],
                ], (int) $account['id']);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'delivery_template.updated',
                    'target_type' => 'delivery_template',
                    'target_id' => $templateKey,
                    'result' => 'success',
                    'message' => 'Delivery template override updated.',
                    'metadata' => [
                        'module_key' => $moduleKey,
                        'template_key' => $templateKey,
                    ],
                ]);
                sr_admin_flash_result(sr_admin_action_result([], '메일 템플릿을 저장했습니다.'));
            } else {
                sr_notification_event_template_admin_save($pdo, $moduleKey, $eventKey, [
                    'title_template' => sr_post_string('title_template', 160),
                    'body_template' => sr_post_string_without_truncation('body_template', 4000) ?? '',
                    'enabled' => sr_post_string('enabled', 1) === '1',
                    'channels' => isset($_POST['channels']) && is_array($_POST['channels']) ? array_values(array_map('strval', $_POST['channels'])) : [],
                    'alimtalk_template_code' => sr_post_string('alimtalk_template_code', 120),
                ]);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'notification_event_template.updated',
                    'target_type' => 'notification_event_template',
                    'target_id' => $moduleKey . '.' . $eventKey,
                    'result' => 'success',
                    'message' => 'Notification event template updated.',
                    'metadata' => [
                        'module_key' => $moduleKey,
                        'event_key' => $eventKey,
                    ],
                ]);
                sr_admin_flash_result(sr_admin_action_result([], '알림 항목을 저장했습니다.'));
            }
        } catch (Throwable $exception) {
            sr_admin_flash_result(sr_admin_action_result([$exception->getMessage()], ''));
        }
        sr_redirect($returnPath);
    }

    $adminNotificationTemplateContext = $context;
    $notificationTemplateRows = sr_notification_event_template_admin_rows($pdo, $moduleKey);
    if (!empty($context['include_delivery_templates'])) {
        $deliveryTemplateKeys = isset($context['delivery_template_keys']) && is_array($context['delivery_template_keys'])
            ? $context['delivery_template_keys']
            : [];
        $notificationTemplateRows = array_merge(
            $notificationTemplateRows,
            sr_notification_event_template_admin_delivery_rows($pdo, $moduleKey, $deliveryTemplateKeys)
        );
    }
    $notificationTemplateSortOptions = [
        'label' => static fn(array $row): string => (string) ($row['label'] ?? ''),
        'title' => static fn(array $row): string => (string) ($row['title_template'] ?? ''),
        'status' => static fn(array $row): string => !empty($row['enabled']) ? '0' : '1',
        'channels' => static function (array $row): string {
            $channels = isset($row['channels']) && is_array($row['channels']) ? $row['channels'] : [];
            return implode(',', array_map('strval', $channels));
        },
    ];
    $notificationTemplateDefaultSort = sr_admin_sort_default('title', 'asc');
    $notificationTemplateSort = sr_admin_sort_from_request($notificationTemplateSortOptions, $notificationTemplateDefaultSort);
    $notificationTemplateSortKey = (string) ($notificationTemplateSort['key'] ?? 'label');
    $notificationTemplateSortDirection = (string) ($notificationTemplateSort['dir'] ?? 'asc');
    usort($notificationTemplateRows, static function (array $left, array $right) use ($notificationTemplateSortOptions, $notificationTemplateSortKey, $notificationTemplateSortDirection): int {
        $leftValue = $notificationTemplateSortOptions[$notificationTemplateSortKey]($left);
        $rightValue = $notificationTemplateSortOptions[$notificationTemplateSortKey]($right);
        $result = strnatcasecmp($leftValue, $rightValue);
        if ($result === 0) {
            $result = strnatcasecmp((string) ($left['event_key'] ?? ''), (string) ($right['event_key'] ?? ''));
        }

        return $notificationTemplateSortDirection === 'desc' ? -$result : $result;
    });
    $notificationTemplateChannelOptions = sr_notification_event_template_admin_channels($pdo);
    include SR_ROOT . '/modules/notification/views/admin-event-templates.php';
}
