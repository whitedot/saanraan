<?php

declare(strict_types=1);

function sr_admin_action_result(array $errors = [], string $notice = ''): array
{
    return [
        'errors' => array_values(array_map('strval', $errors)),
        'notice' => $notice,
    ];
}

function sr_admin_feedback_toasts(string $notice = '', array $errors = []): string
{
    $items = [];
    if ($notice !== '') {
        $items[] = [
            'type' => 'success',
            'title' => '완료',
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
            'title' => '확인 필요',
            'message' => $message,
        ];
    }

    if ($items === []) {
        return '';
    }

    ob_start();
    ?>
    <div class="admin-toast-stack" role="status" aria-live="polite" aria-atomic="false">
        <?php foreach ($items as $item) { ?>
            <div class="admin-toast admin-toast-<?php echo sr_e((string) $item['type']); ?>" data-admin-toast>
                <strong><?php echo sr_e((string) $item['title']); ?></strong>
                <span><?php echo sr_e((string) $item['message']); ?></span>
                <button type="button" class="admin-toast-close" data-admin-toast-close aria-label="알림 닫기">닫기</button>
            </div>
        <?php } ?>
    </div>
    <?php
    return (string) ob_get_clean();
}
