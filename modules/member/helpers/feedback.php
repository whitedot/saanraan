<?php

declare(strict_types=1);

function sr_member_feedback_toasts(string $notice = '', array $errors = []): string
{
    $items = [];
    if ($notice !== '') {
        $items[] = [
            'type' => 'success',
            'title' => sr_t('member::feedback.success_title'),
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
            'title' => sr_t('member::feedback.error_title'),
            'message' => $message,
        ];
    }

    if ($items === []) {
        return '';
    }

    ob_start();
    ?>
    <div class="member-skin-basic-toast-stack" data-member-toast-stack role="status" aria-live="polite" aria-atomic="false">
        <?php foreach ($items as $item) { ?>
            <div class="alert-removable alert <?php echo (string) $item['type'] === 'success' ? 'alert-success' : 'alert-danger'; ?> member-skin-basic-toast" data-member-toast role="<?php echo (string) $item['type'] === 'error' ? 'alert' : 'status'; ?>">
                <span class="member-skin-basic-toast-copy">
                    <strong><?php echo sr_e((string) $item['title']); ?></strong>
                    <span><?php echo sr_e((string) $item['message']); ?></span>
                </span>
                <button type="button" class="btn btn-sm btn-ghost-default btn-icon alert-close-leading member-skin-basic-toast-close" data-member-toast-close aria-label="<?php echo sr_e(sr_t('member::feedback.close_label')); ?>">
                    <?php echo sr_material_icon_html('close', 'member-skin-basic-toast-close-icon'); ?>
                </button>
            </div>
        <?php } ?>
    </div>
    <?php
    return (string) ob_get_clean();
}
