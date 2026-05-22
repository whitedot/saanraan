<?php

declare(strict_types=1);

function sr_admin_action_result(array $errors = [], string $notice = ''): array
{
    return [
        'errors' => array_values(array_map('strval', $errors)),
        'notice' => $notice,
    ];
}

function sr_admin_flash_result(array $result): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION['sr_admin_action_result'] = sr_admin_action_result(
        isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : [],
        (string) ($result['notice'] ?? '')
    );
}

function sr_admin_pop_flash_result(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return sr_admin_action_result();
    }

    $result = $_SESSION['sr_admin_action_result'] ?? null;
    unset($_SESSION['sr_admin_action_result']);

    if (!is_array($result)) {
        return sr_admin_action_result();
    }

    return sr_admin_action_result(
        isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : [],
        (string) ($result['notice'] ?? '')
    );
}

function sr_admin_feedback_toasts(string $notice = '', array $errors = []): string
{
    $items = [];
    if ($notice !== '') {
        $items[] = [
            'type' => 'success',
            'title' => sr_t('admin::feedback.success_title'),
            'message' => $notice,
        ];
    }

    foreach ($errors as $error) {
        $message = trim((string) $error);
        if ($message === '') {
            continue;
        }

        $items[] = [
            'type' => 'error',
            'title' => sr_t('admin::feedback.error_title'),
            'message' => $message,
        ];
    }

    if ($items === []) {
        return '';
    }

    ob_start();
    ?>
    <div data-admin-toast-stack role="status" aria-live="polite" aria-atomic="false">
        <?php foreach ($items as $item) { ?>
            <div class="admin-flash-message admin-flash-message-<?php echo sr_e((string) $item['type']); ?>" data-admin-toast>
                <strong><?php echo sr_e((string) $item['title']); ?></strong>
                <span><?php echo sr_e((string) $item['message']); ?></span>
                <button type="button" class="btn btn-sm btn-icon" data-admin-toast-close aria-label="<?php echo sr_e(sr_t('admin::feedback.close_label')); ?>">
                    <?php echo sr_material_icon_html('close', 'admin-toast-close-icon'); ?>
                </button>
            </div>
        <?php } ?>
    </div>
    <?php
    return (string) ob_get_clean();
}
