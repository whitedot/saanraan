<?php

declare(strict_types=1);

function sr_delivery_template_key(string $key): string
{
    $key = strtolower(trim($key));
    $key = preg_replace('/[^a-z0-9_.-]+/', '', $key) ?? '';
    return preg_match('/\A[a-z0-9][a-z0-9_.-]{0,188}\z/', $key) === 1 ? $key : '';
}

function sr_delivery_template_placeholders(string $value): array
{
    preg_match_all('/\{([a-zA-Z0-9_]{1,80})\}/', $value, $matches);
    $names = [];
    foreach ($matches[1] ?? [] as $name) {
        $names[(string) $name] = true;
    }
    return array_keys($names);
}

function sr_delivery_template_render_string(string $template, array $metadata): string
{
    $values = [];
    foreach ($metadata as $key => $value) {
        if (is_string($key) && (is_scalar($value) || $value === null)) {
            $values['{' . $key . '}'] = $value === null ? '' : (string) $value;
        }
    }
    return strtr($template, $values);
}

function sr_delivery_template_unwrap_code_text(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", trim($value));
    $patterns = [
        '/\A<pre>\s*<code\b[^>]*>(.*)<\/code>\s*<\/pre>\z/is',
        '/\A<code\b[^>]*>(.*)<\/code>\z/is',
        '/\A```[^\n`]*\n?(.*)\n?```\z/s',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $value, $matches) === 1) {
            return trim(html_entity_decode((string) ($matches[1] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }
    }

    return $value;
}

function sr_delivery_template_body_template_for_editor(string $bodyTemplate, string $linkTemplate): string
{
    $bodyTemplate = sr_delivery_template_unwrap_code_text($bodyTemplate);
    $linkTemplate = sr_clean_single_line($linkTemplate, 255);
    if ($linkTemplate === '' || str_contains($bodyTemplate, $linkTemplate)) {
        return $bodyTemplate;
    }

    return trim($bodyTemplate . "\n\n" . $linkTemplate);
}

function sr_delivery_template_variable_label(string $name): string
{
    $labels = [
        'account_email' => '회원 이메일',
        'account_id' => '회원 번호',
        'amount' => '금액 또는 수량',
        'comment_excerpt' => '댓글 내용',
        'comment_id' => '댓글 번호',
        'document_title' => '문서 제목',
        'effective_date' => '시행일',
        'expires_minutes' => '유효 시간',
        'link_url' => '연결 주소',
        'member_name' => '회원 이름',
        'mfa_code' => '인증 코드',
        'post_id' => '게시글 번호',
        'reset_url' => '비밀번호 재설정 주소',
        'site_name' => '사이트 이름',
        'verification_url' => '이메일 인증 주소',
    ];
    if (isset($labels[$name])) {
        return $labels[$name];
    }

    $label = trim(str_replace('_', ' ', $name));
    return $label !== '' ? $label : $name;
}

function sr_delivery_template_normalize_channels(array $channels): array
{
    $normalized = [];
    foreach ($channels as $channel) {
        $channel = strtolower(trim((string) $channel));
        if ($channel !== '' && preg_match('/\A[a-z0-9_]{1,40}\z/', $channel) === 1 && !in_array($channel, $normalized, true)) {
            $normalized[] = $channel;
        }
    }
    return $normalized;
}

function sr_delivery_template_notification_event_available_channels(array $channels): array
{
    $available = sr_delivery_template_normalize_channels($channels);
    if (!function_exists('sr_notification_member_external_channel_keys')) {
        $notificationHelpers = SR_ROOT . '/modules/notification/helpers.php';
        if (is_file($notificationHelpers)) {
            require_once $notificationHelpers;
        }
    }
    if (function_exists('sr_notification_member_external_channel_keys')) {
        $available = array_merge($available, sr_notification_member_external_channel_keys());
    }
    return sr_delivery_template_normalize_channels($available);
}

function sr_delivery_template_display_label(array $template, string $fallback = '발송 템플릿'): string
{
    $label = sr_clean_single_line((string) ($template['label'] ?? ''), 120);
    $templateKey = sr_delivery_template_key((string) ($template['template_key'] ?? ''));
    $looksInternal = $label === ''
        || ($templateKey !== '' && $label === $templateKey)
        || preg_match('/\A[a-z][a-z0-9_]{1,39}\s*\/\s*[a-z0-9_.-]+\z/', $label) === 1;

    if (!$looksInternal) {
        return $label;
    }

    $subject = (string) ($template['subject_template'] ?? ($template['title_template'] ?? ''));
    $subject = preg_replace('/\{[a-zA-Z0-9_]{1,80}\}/', '', $subject) ?? '';
    $subject = sr_clean_single_line(trim($subject, " \t\n\r\0\x0B:-/|"), 120);
    if ($subject !== '') {
        return $subject;
    }

    return sr_clean_single_line($fallback, 120);
}

function sr_delivery_template_legacy_notification_label(string $moduleKey, string $eventKey, string $titleTemplate): string
{
    if (!sr_is_safe_module_key($moduleKey)) {
        return sr_delivery_template_display_label([
            'subject_template' => $titleTemplate,
        ], '회원 알림');
    }

    $casesFunction = 'sr_' . $moduleKey . '_notification_cases';
    $caseHelperModules = ['coupon', 'deposit', 'point', 'reward'];
    $helperPath = SR_ROOT . '/modules/' . $moduleKey . '/helpers.php';
    if (!function_exists($casesFunction) && in_array($moduleKey, $caseHelperModules, true) && is_file($helperPath)) {
        require_once $helperPath;
    }

    if (function_exists($casesFunction)) {
        $cases = $casesFunction();
        if (is_array($cases)) {
            foreach ($cases as $case) {
                if (!is_array($case) || (string) ($case['event_key'] ?? '') !== $eventKey) {
                    continue;
                }
                $label = sr_clean_single_line((string) ($case['label'] ?? ''), 120);
                if ($label !== '') {
                    return $label;
                }
            }
        }
    }

    return sr_delivery_template_display_label([
        'subject_template' => $titleTemplate,
    ], $moduleKey . ' 알림');
}

function sr_delivery_template_contracts(PDO $pdo): array
{
    $contracts = [];
    foreach (sr_installed_module_contract_files($pdo, 'delivery-templates.php') as $moduleKey => $file) {
        $moduleContracts = sr_load_module_contract_file($moduleKey, $file);
        if (!is_array($moduleContracts)) {
            continue;
        }
        foreach ($moduleContracts as $key => $contract) {
            if (!is_array($contract)) {
                continue;
            }
            $templateKey = sr_delivery_template_key((string) $key);
            if ($templateKey === '') {
                continue;
            }
            $normalized = sr_delivery_template_normalize_contract($pdo, $templateKey, $moduleKey, $contract);
            if ($normalized !== null) {
                $contracts[$templateKey] = $normalized;
            }
        }
    }
    ksort($contracts);
    return $contracts;
}

function sr_delivery_template_legacy_notification_contracts(PDO $pdo): array
{
    try {
        $stmt = $pdo->query(
            'SELECT module_key, event_key, title_template, body_template, link_template, channels_json, status
             FROM sr_notification_event_templates
             ORDER BY module_key ASC, event_key ASC'
        );
    } catch (Throwable) {
        return [];
    }

    $contracts = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $moduleKey = (string) ($row['module_key'] ?? '');
        $eventKey = (string) ($row['event_key'] ?? '');
        $templateKey = sr_delivery_template_key($moduleKey . '.' . $eventKey);
        if ($templateKey === '' || !sr_is_safe_module_key($moduleKey)) {
            continue;
        }
        $channels = sr_delivery_template_decode_channels_json((string) ($row['channels_json'] ?? ''));
        if ($channels === []) {
            $channels = ['site'];
        }
        $titleTemplate = (string) ($row['title_template'] ?? '');
        $contracts[$templateKey] = [
            'template_key' => $templateKey,
            'owner_module' => $moduleKey,
            'provider_module' => 'notification',
            'module_enabled' => sr_module_enabled($pdo, $moduleKey),
            'label' => sr_delivery_template_legacy_notification_label($moduleKey, $eventKey, $titleTemplate),
            'description' => '기존 알림 이벤트 템플릿입니다. 명시 계약으로 이관되기 전까지 호환 경로로 표시됩니다.',
            'category' => 'notification_event',
            'channels' => $channels,
            'available_channels' => sr_delivery_template_notification_event_available_channels($channels),
            'pipeline' => 'notification_queue',
            'editable' => true,
            'disable_policy' => 'no_op',
            'subject_template' => $titleTemplate,
            'body_template' => (string) ($row['body_template'] ?? ''),
            'link_template' => (string) ($row['link_template'] ?? ''),
            'status' => (string) ($row['status'] ?? 'active'),
            'variables' => sr_delivery_template_variables_from_templates(
                (string) ($row['title_template'] ?? ''),
                (string) ($row['body_template'] ?? ''),
                (string) ($row['link_template'] ?? '')
            ),
            'required_variables' => [],
            'sensitive_variables' => [],
            'sample_values' => [],
            'event_module_key' => $moduleKey,
            'event_key' => $eventKey,
            'body_editable' => true,
            'legacy_notification_event' => true,
        ];
    }
    return $contracts;
}

function sr_delivery_template_variables_from_templates(string ...$templates): array
{
    $variables = [];
    foreach ($templates as $template) {
        foreach (sr_delivery_template_placeholders($template) as $placeholder) {
            $variables[$placeholder] = sr_delivery_template_variable_label($placeholder);
        }
    }
    return $variables;
}

function sr_delivery_template_contract(PDO $pdo, string $key): ?array
{
    $key = sr_delivery_template_key($key);
    if ($key === '') {
        return null;
    }
    $contracts = sr_delivery_template_contracts($pdo);
    return isset($contracts[$key]) && is_array($contracts[$key]) ? $contracts[$key] : null;
}

function sr_delivery_template_normalize_contract(PDO $pdo, string $key, string $moduleKey, array $contract): ?array
{
    if (!sr_is_safe_module_key($moduleKey)) {
        return null;
    }
    $ownerModule = sr_is_safe_module_key((string) ($contract['owner_module'] ?? '')) ? (string) $contract['owner_module'] : $moduleKey;
    $category = (string) ($contract['category'] ?? 'notification_event');
    if (!in_array($category, ['transactional_email', 'notification_event', 'admin_operational'], true)) {
        $category = 'notification_event';
    }
    $channels = sr_delivery_template_normalize_channels(isset($contract['channels']) && is_array($contract['channels']) ? $contract['channels'] : ['email']);
    if ($channels === []) {
        $channels = ['email'];
    }
    $availableChannels = sr_delivery_template_normalize_channels(isset($contract['available_channels']) && is_array($contract['available_channels']) ? $contract['available_channels'] : $channels);
    if ($category === 'notification_event') {
        $availableChannels = sr_delivery_template_notification_event_available_channels(array_merge($availableChannels, $channels));
    }
    if ($availableChannels === []) {
        $availableChannels = $channels;
    }
    $variables = [];
    foreach (isset($contract['variables']) && is_array($contract['variables']) ? $contract['variables'] : [] as $name => $label) {
        if (is_string($name) && preg_match('/\A[a-zA-Z0-9_]{1,80}\z/', $name) === 1) {
            $label = trim((string) $label);
            $variables[$name] = $label !== '' && $label !== $name ? $label : sr_delivery_template_variable_label($name);
        }
    }
    $required = [];
    foreach (isset($contract['required_variables']) && is_array($contract['required_variables']) ? $contract['required_variables'] : [] as $name) {
        $name = (string) $name;
        if (isset($variables[$name]) && !in_array($name, $required, true)) {
            $required[] = $name;
        }
    }
    $sensitive = [];
    foreach (isset($contract['sensitive_variables']) && is_array($contract['sensitive_variables']) ? $contract['sensitive_variables'] : [] as $name) {
        $name = (string) $name;
        if (isset($variables[$name]) && !in_array($name, $sensitive, true)) {
            $sensitive[] = $name;
        }
    }
    $sampleValues = [];
    foreach (isset($contract['sample_values']) && is_array($contract['sample_values']) ? $contract['sample_values'] : [] as $name => $value) {
        if (is_string($name) && isset($variables[$name]) && (is_scalar($value) || $value === null)) {
            $sampleValues[$name] = $value === null ? '' : (string) $value;
        }
    }

    return [
        'template_key' => $key,
        'owner_module' => $ownerModule,
        'provider_module' => $moduleKey,
        'module_enabled' => sr_module_enabled($pdo, $ownerModule),
        'label' => trim((string) ($contract['label'] ?? $key)),
        'description' => trim((string) ($contract['description'] ?? '')),
        'category' => $category,
        'channels' => $channels,
        'available_channels' => $availableChannels,
        'pipeline' => (string) ($contract['pipeline'] ?? ($category === 'notification_event' ? 'notification_queue' : 'config_mail')),
        'editable' => array_key_exists('editable', $contract) ? !empty($contract['editable']) : true,
        'disable_policy' => (string) ($contract['disable_policy'] ?? ($category === 'transactional_email' ? 'fallback_to_default' : 'no_op')),
        'subject_template' => (string) ($contract['subject_template'] ?? ($contract['title_template'] ?? '')),
        'body_template' => (string) ($contract['body_template'] ?? ''),
        'link_template' => (string) ($contract['link_template'] ?? ''),
        'status' => (string) ($contract['status'] ?? 'active'),
        'variables' => $variables,
        'required_variables' => $required,
        'sensitive_variables' => $sensitive,
        'sample_values' => $sampleValues,
        'event_module_key' => (string) ($contract['event_module_key'] ?? ''),
        'event_key' => (string) ($contract['event_key'] ?? ''),
        'body_editable' => array_key_exists('body_editable', $contract) ? !empty($contract['body_editable']) : true,
    ];
}

function sr_delivery_template_override(PDO $pdo, string $key): ?array
{
    $key = sr_delivery_template_key($key);
    if ($key === '') {
        return null;
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT template_key, owner_module, category, subject_template, body_template, link_template,
                    channels_json, status, updated_by_account_id, created_at, updated_at
             FROM sr_delivery_template_overrides
             WHERE template_key = :template_key
             LIMIT 1'
        );
        $stmt->execute(['template_key' => $key]);
        $row = $stmt->fetch();
    } catch (Throwable) {
        return null;
    }
    return is_array($row) ? $row : null;
}

function sr_delivery_template_overrides(PDO $pdo): array
{
    try {
        $stmt = $pdo->query(
            'SELECT template_key, owner_module, category, subject_template, body_template, link_template,
                    channels_json, status, updated_by_account_id, created_at, updated_at
             FROM sr_delivery_template_overrides
             ORDER BY template_key ASC'
        );
    } catch (Throwable) {
        return [];
    }

    $overrides = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $templateKey = sr_delivery_template_key((string) ($row['template_key'] ?? ''));
        if ($templateKey !== '') {
            $overrides[$templateKey] = $row;
        }
    }
    return $overrides;
}

function sr_delivery_template_effective(PDO $pdo, string $key): ?array
{
    $contract = sr_delivery_template_contract($pdo, $key);
    if (!is_array($contract)) {
        return null;
    }
    $override = sr_delivery_template_override($pdo, $key);
    $effective = $contract;
    $effective['override'] = $override;
    $effective['has_override'] = is_array($override);
    $effective['effective_source'] = is_array($override) ? 'override' : 'default';
    if (is_array($override)) {
        $effective['subject_template'] = (string) ($override['subject_template'] ?? '');
        $effective['body_template'] = (string) ($override['body_template'] ?? '');
        $effective['link_template'] = (string) ($override['link_template'] ?? '');
        $effective['status'] = (string) ($override['status'] ?? 'active');
        $channels = sr_delivery_template_decode_channels_json((string) ($override['channels_json'] ?? ''));
        if ($channels !== []) {
            $effective['channels'] = $channels;
        }
    }
    return $effective;
}

function sr_delivery_template_decode_channels_json(string $channelsJson): array
{
    $decoded = trim($channelsJson) !== '' ? json_decode($channelsJson, true) : null;
    return is_array($decoded) ? sr_delivery_template_normalize_channels($decoded) : [];
}

function sr_delivery_template_validate_templates(array $contract, string $subject, string $body, string $link = ''): array
{
    $errors = [];
    $subject = sr_clean_single_line($subject, 190);
    $body = sr_clean_text($body, 5000);
    $link = sr_clean_single_line($link, 255);
    if ($subject === '') {
        $errors[] = '제목을 입력하세요.';
    }
    if (!empty($contract['body_editable']) && $body === '') {
        $errors[] = '본문을 입력하세요.';
    }
    $allowed = array_fill_keys(array_keys(isset($contract['variables']) && is_array($contract['variables']) ? $contract['variables'] : []), true);
    $allTemplateText = $subject . "\n" . $body . "\n" . $link;
    foreach (sr_delivery_template_placeholders($allTemplateText) as $placeholder) {
        if (!isset($allowed[$placeholder])) {
            $errors[] = '허용되지 않은 변수입니다: {' . $placeholder . '}';
        }
    }
    foreach (isset($contract['required_variables']) && is_array($contract['required_variables']) ? $contract['required_variables'] : [] as $required) {
        if (!str_contains($allTemplateText, '{' . $required . '}')) {
            $errors[] = '필수 변수가 빠졌습니다: {' . $required . '}';
        }
    }
    return array_values(array_unique($errors));
}

function sr_delivery_template_save_override(PDO $pdo, array $contract, string $subject, string $body, string $link, array $channels, string $status, int $accountId): void
{
    $templateKey = (string) ($contract['template_key'] ?? '');
    if ($templateKey === '') {
        throw new InvalidArgumentException('Template key is required.');
    }
    $subject = sr_clean_single_line($subject, 190);
    $body = !empty($contract['body_editable']) ? sr_clean_text($body, 5000) : (string) ($contract['body_template'] ?? '');
    $link = sr_clean_single_line($link, 255);
    $status = in_array($status, ['active', 'inactive'], true) ? $status : 'active';
    $channels = sr_delivery_template_normalize_channels($channels);
    if ($channels === []) {
        $channels = isset($contract['channels']) && is_array($contract['channels']) ? $contract['channels'] : ['email'];
    }
    $availableChannels = sr_delivery_template_normalize_channels(isset($contract['available_channels']) && is_array($contract['available_channels']) ? $contract['available_channels'] : (array) ($contract['channels'] ?? []));
    if ($availableChannels !== []) {
        $allowed = array_fill_keys($availableChannels, true);
        $channels = array_values(array_filter($channels, static fn (string $channel): bool => isset($allowed[$channel])));
        if ($channels === []) {
            $channels = isset($contract['channels']) && is_array($contract['channels']) ? $contract['channels'] : ['email'];
        }
    }
    $errors = sr_delivery_template_validate_templates($contract, $subject, $body, $link);
    if ($errors !== []) {
        throw new InvalidArgumentException(implode("\n", $errors));
    }
    $now = sr_now();
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = $driver === 'sqlite'
        ? 'INSERT INTO sr_delivery_template_overrides
            (template_key, owner_module, category, subject_template, body_template, link_template, channels_json, status, updated_by_account_id, created_at, updated_at)
         VALUES
            (:template_key, :owner_module, :category, :subject_template, :body_template, :link_template, :channels_json, :status, :updated_by_account_id, :created_at, :updated_at)
         ON CONFLICT(template_key) DO UPDATE SET
            owner_module = excluded.owner_module,
            category = excluded.category,
            subject_template = excluded.subject_template,
            body_template = excluded.body_template,
            link_template = excluded.link_template,
            channels_json = excluded.channels_json,
            status = excluded.status,
            updated_by_account_id = excluded.updated_by_account_id,
            updated_at = excluded.updated_at'
        : 'INSERT INTO sr_delivery_template_overrides
            (template_key, owner_module, category, subject_template, body_template, link_template, channels_json, status, updated_by_account_id, created_at, updated_at)
         VALUES
            (:template_key, :owner_module, :category, :subject_template, :body_template, :link_template, :channels_json, :status, :updated_by_account_id, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            owner_module = VALUES(owner_module),
            category = VALUES(category),
            subject_template = VALUES(subject_template),
            body_template = VALUES(body_template),
            link_template = VALUES(link_template),
            channels_json = VALUES(channels_json),
            status = VALUES(status),
            updated_by_account_id = VALUES(updated_by_account_id),
            updated_at = VALUES(updated_at)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'template_key' => $templateKey,
        'owner_module' => (string) ($contract['owner_module'] ?? ''),
        'category' => (string) ($contract['category'] ?? ''),
        'subject_template' => $subject,
        'body_template' => $body,
        'link_template' => $link,
        'channels_json' => json_encode(array_values($channels), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'status' => $status,
        'updated_by_account_id' => $accountId > 0 ? $accountId : null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function sr_delivery_template_delete_override(PDO $pdo, string $key): void
{
    $key = sr_delivery_template_key($key);
    if ($key === '') {
        return;
    }
    $stmt = $pdo->prepare('DELETE FROM sr_delivery_template_overrides WHERE template_key = :template_key');
    $stmt->execute(['template_key' => $key]);
}

function sr_delivery_template_render(PDO $pdo, string $key, array $metadata): array
{
    $contract = sr_delivery_template_contract($pdo, $key);
    if (!is_array($contract)) {
        return ['subject' => '', 'body' => '', 'link' => '', 'source' => 'missing'];
    }
    $effective = sr_delivery_template_effective($pdo, $key);
    $source = 'default';
    if (is_array($effective) && !empty($effective['has_override']) && (string) ($effective['status'] ?? '') === 'active') {
        $errors = sr_delivery_template_validate_templates($contract, (string) $effective['subject_template'], (string) $effective['body_template'], (string) $effective['link_template']);
        if ($errors === []) {
            $source = 'override';
        } else {
            $effective = $contract;
        }
    } elseif (is_array($effective) && !empty($effective['has_override'])) {
        $effective = $contract;
    }
    $effective = is_array($effective) ? $effective : $contract;
    $subject = sr_clean_single_line(sr_delivery_template_render_string((string) ($effective['subject_template'] ?? ''), $metadata), 190);
    $bodyTemplate = (string) ($effective['body_template'] ?? '');
    if ((string) ($contract['category'] ?? '') === 'notification_event' && !empty($contract['body_editable'])) {
        $bodyTemplate = sr_delivery_template_body_template_for_editor($bodyTemplate, (string) ($effective['link_template'] ?? ''));
    } else {
        $bodyTemplate = sr_delivery_template_unwrap_code_text($bodyTemplate);
    }
    $body = sr_clean_text(sr_delivery_template_unwrap_code_text(sr_delivery_template_render_string($bodyTemplate, $metadata)), 5000);
    $link = sr_clean_single_line(sr_delivery_template_render_string((string) ($effective['link_template'] ?? ''), $metadata), 255);
    if (($subject === '' || (!empty($contract['body_editable']) && $body === '')) && $source === 'override') {
        $subject = sr_clean_single_line(sr_delivery_template_render_string((string) ($contract['subject_template'] ?? ''), $metadata), 190);
        $bodyTemplate = (string) ($contract['body_template'] ?? '');
        if ((string) ($contract['category'] ?? '') === 'notification_event' && !empty($contract['body_editable'])) {
            $bodyTemplate = sr_delivery_template_body_template_for_editor($bodyTemplate, (string) ($contract['link_template'] ?? ''));
        } else {
            $bodyTemplate = sr_delivery_template_unwrap_code_text($bodyTemplate);
        }
        $body = sr_clean_text(sr_delivery_template_unwrap_code_text(sr_delivery_template_render_string($bodyTemplate, $metadata)), 5000);
        $link = sr_clean_single_line(sr_delivery_template_render_string((string) ($contract['link_template'] ?? ''), $metadata), 255);
        $source = 'default';
    }
    return ['subject' => $subject, 'body' => $body, 'link' => $link, 'source' => $source];
}

function sr_delivery_template_send_mail(PDO $pdo, ?array $site, string $key, string $to, array $metadata): bool
{
    $rendered = sr_delivery_template_render($pdo, $key, $metadata);
    $subject = (string) ($rendered['subject'] ?? '');
    $body = (string) ($rendered['body'] ?? '');
    if ($subject === '' || $body === '') {
        return false;
    }
    return sr_send_mail($site, $to, $subject, $body);
}
