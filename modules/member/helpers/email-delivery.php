<?php

declare(strict_types=1);

function sr_member_email_delivery_status(PDO $pdo): array
{
    if (!sr_module_enabled($pdo, 'notification')) {
        return [
            'enabled' => true,
            'managed_by_notification' => false,
            'reason' => '',
        ];
    }

    $statusFunction = sr_module_contract_function($pdo, 'notification', 'email-delivery.php', 'status_function');
    if ($statusFunction === '') {
        return [
            'enabled' => false,
            'managed_by_notification' => true,
            'reason' => 'contract_unavailable',
        ];
    }

    try {
        $status = $statusFunction($pdo);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'member_email_delivery_status');
        return [
            'enabled' => false,
            'managed_by_notification' => true,
            'reason' => 'status_unavailable',
        ];
    }
    if (!is_array($status)) {
        return [
            'enabled' => false,
            'managed_by_notification' => true,
            'reason' => 'status_unavailable',
        ];
    }

    return [
        'enabled' => !empty($status['enabled']),
        'managed_by_notification' => true,
        'reason' => (string) ($status['reason'] ?? ''),
    ];
}

function sr_member_email_delivery_available(PDO $pdo): bool
{
    $status = sr_member_email_delivery_status($pdo);
    return !empty($status['enabled']);
}

function sr_member_send_delivery_template_mail(
    PDO $pdo,
    ?array $site,
    string $templateKey,
    string $recipient,
    array $metadata
): bool {
    if (!sr_module_enabled($pdo, 'notification')) {
        return sr_delivery_template_send_mail($pdo, $site, $templateKey, $recipient, $metadata);
    }

    $sendFunction = sr_module_contract_function($pdo, 'notification', 'email-delivery.php', 'send_function');
    if ($sendFunction === '') {
        return false;
    }

    try {
        return $sendFunction($pdo, $site, $templateKey, $recipient, $metadata) === true;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'member_email_delivery_send');
        return false;
    }
}
