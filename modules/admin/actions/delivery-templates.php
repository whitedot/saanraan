<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

function sr_admin_delivery_template_send_test_email(PDO $pdo, ?array $site, array $template, string $recipient, array $rendered): bool
{
    $subject = (string) ($rendered['subject'] ?? '');
    $body = (string) ($rendered['body'] ?? '');
    if ($subject === '' || $body === '') {
        return false;
    }

    if ((string) ($template['pipeline'] ?? '') !== 'notification_queue') {
        return sr_send_mail($site, $recipient, $subject, $body);
    }

    $notificationHelpers = SR_ROOT . '/modules/notification/helpers.php';
    if (!is_file($notificationHelpers)) {
        return false;
    }
    require_once $notificationHelpers;
    if (!function_exists('sr_notification_settings') || !function_exists('sr_notification_mail_config_from_settings')) {
        return false;
    }

    $settings = sr_notification_settings($pdo);
    if (empty($settings['email_channel_enabled'])) {
        return false;
    }

    $previousConfig = sr_runtime_config();
    $runnerConfig = $previousConfig;
    $runnerConfig['mail'] = sr_notification_mail_config_from_settings($settings);
    sr_set_runtime_config($runnerConfig);
    try {
        return sr_send_mail($site, $recipient, $subject, $body);
    } finally {
        sr_set_runtime_config($previousConfig);
    }
}

function sr_admin_delivery_template_with_effective(array $template, ?array $override): array
{
    $hasOverride = is_array($override);
    $effective = $template;
    $effective['override'] = $override;
    $effective['has_override'] = $hasOverride;
    $effective['effective_source'] = $hasOverride ? 'override' : 'default';
    if ($hasOverride) {
        $effective['subject_template'] = (string) ($override['subject_template'] ?? '');
        $effective['body_template'] = (string) ($override['body_template'] ?? '');
        $effective['link_template'] = (string) ($override['link_template'] ?? '');
        $effective['status'] = (string) ($override['status'] ?? 'active');
        $overrideChannels = sr_delivery_template_decode_channels_json((string) ($override['channels_json'] ?? ''));
        if ($overrideChannels !== []) {
            $effective['channels'] = $overrideChannels;
        }
    }

    $template['override'] = $override;
    $template['has_override'] = $hasOverride;
    $template['effective_status'] = $hasOverride ? (string) ($override['status'] ?? '') : (string) ($template['status'] ?? 'active');
    $template['effective'] = $effective;
    return $template;
}

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/delivery-templates', 'view');

$templates = sr_delivery_template_contracts($pdo);
$selectedKey = sr_request_method() === 'POST' ? sr_delivery_template_key(sr_post_string('template_key', 190)) : '';
$selectedTemplate = $selectedKey !== '' && isset($templates[$selectedKey]) ? $templates[$selectedKey] : null;

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/delivery-templates', 'edit');
    $intent = sr_post_string('intent', 40);
    $errors = [];
    $notice = '';

    if (!is_array($selectedTemplate)) {
        $errors[] = '발송 템플릿을 찾을 수 없습니다.';
    } elseif ($intent === 'save') {
        $subject = sr_post_string('subject_template', 190);
        $body = sr_post_string_without_truncation('body_template', 5000);
        $body = $body === null ? '' : $body;
        $link = sr_post_string('link_template', 255);
        $status = sr_post_string('status', 30);
        $channels = isset($_POST['channels']) && is_array($_POST['channels']) ? array_values(array_map('strval', $_POST['channels'])) : [];
        try {
            sr_delivery_template_save_override($pdo, $selectedTemplate, $subject, $body, $link, $channels, $status, (int) $account['id']);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'delivery_template.override.saved',
                'target_type' => 'delivery_template',
                'target_id' => $selectedKey,
                'result' => 'success',
                'message' => 'Delivery template override saved.',
                'metadata' => [
                    'owner_module' => (string) ($selectedTemplate['owner_module'] ?? ''),
                    'category' => (string) ($selectedTemplate['category'] ?? ''),
                ],
            ]);
            $notice = '발송 템플릿을 저장했습니다.';
        } catch (Throwable $exception) {
            $errors = preg_split('/\R/', $exception->getMessage()) ?: ['발송 템플릿을 저장할 수 없습니다.'];
        }
    } elseif ($intent === 'restore_default') {
        sr_delivery_template_delete_override($pdo, $selectedKey);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'delivery_template.override.deleted',
            'target_type' => 'delivery_template',
            'target_id' => $selectedKey,
            'result' => 'success',
            'message' => 'Delivery template override deleted.',
            'metadata' => [
                'owner_module' => (string) ($selectedTemplate['owner_module'] ?? ''),
                'category' => (string) ($selectedTemplate['category'] ?? ''),
            ],
        ]);
        $notice = '기본값으로 복원했습니다.';
    } elseif ($intent === 'test_email') {
        $recipient = (string) ($account['email'] ?? '');
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '현재 관리자 계정 이메일이 올바르지 않아 테스트 발송을 할 수 없습니다.';
        }
        if (!in_array('email', (array) ($selectedTemplate['channels'] ?? []), true)) {
            $errors[] = '이 템플릿은 이메일 채널이 없어 테스트 메일을 보낼 수 없습니다.';
        }
        $rateSubject = $selectedKey . '|' . (string) $account['id'];
        if (sr_rate_limit_count($pdo, 'delivery_template.test_email', $rateSubject, 600) >= 5) {
            $errors[] = '테스트 발송 요청이 많습니다. 잠시 후 다시 시도하세요.';
        }
        if ($errors === []) {
            sr_rate_limit_increment($pdo, 'delivery_template.test_email', $rateSubject, 600);
            $sampleValues = isset($selectedTemplate['sample_values']) && is_array($selectedTemplate['sample_values']) ? $selectedTemplate['sample_values'] : [];
            $rendered = sr_delivery_template_render($pdo, $selectedKey, $sampleValues);
            $sent = sr_admin_delivery_template_send_test_email($pdo, $site ?? null, $selectedTemplate, $recipient, $rendered);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'delivery_template.test_email.sent',
                'target_type' => 'delivery_template',
                'target_id' => $selectedKey,
                'result' => $sent ? 'success' : 'failure',
                'message' => $sent ? 'Delivery template test email sent.' : 'Delivery template test email failed.',
                'metadata' => [
                    'pipeline' => (string) ($selectedTemplate['pipeline'] ?? ''),
                    'recipient' => 'current_admin',
                ],
            ]);
            if ($sent) {
                $notice = '현재 관리자 이메일로 테스트 메일을 발송했습니다.';
            } else {
                $errors[] = '테스트 메일을 발송하지 못했습니다. 메일 설정을 확인하세요.';
            }
        }
    } else {
        $errors[] = '발송 템플릿 작업 값이 올바르지 않습니다.';
    }

    sr_admin_flash_result(sr_admin_action_result($errors, $notice));
    sr_redirect('/admin/delivery-templates');
}

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$editKey = sr_delivery_template_key(sr_get_string('edit', 190));
$editTemplate = $editKey !== '' && isset($templates[$editKey]) ? $templates[$editKey] : null;
$overridesByKey = sr_delivery_template_overrides($pdo);

$filteredTemplates = [];
foreach ($templates as $templateKey => $template) {
    $override = $overridesByKey[(string) $templateKey] ?? null;
    $template = sr_admin_delivery_template_with_effective($template, $override);
    $filteredTemplates[$templateKey] = $template;
}

if (is_array($editTemplate)) {
    $editTemplate = sr_admin_delivery_template_with_effective($editTemplate, $overridesByKey[$editKey] ?? null);
}

$moduleOptions = [];
foreach ($templates as $template) {
    $moduleOptions[(string) ($template['owner_module'] ?? '')] = true;
}
ksort($moduleOptions);

include SR_ROOT . '/modules/admin/views/delivery-templates.php';
