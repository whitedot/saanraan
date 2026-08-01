<?php

declare(strict_types=1);

function sr_community_report_reason_keys(): array
{
    return ['spam', 'abuse', 'personal_info', 'illegal', 'other'];
}

function sr_community_report_reason_label(string $reasonKey): string
{
    $labels = [
        'spam' => sr_t('community::report.reason.spam'),
        'abuse' => sr_t('community::report.reason.abuse'),
        'personal_info' => sr_t('community::report.reason.personal_info'),
        'illegal' => sr_t('community::report.reason.illegal'),
        'other' => sr_t('community::report.reason.other'),
    ];

    return (string) ($labels[$reasonKey] ?? $reasonKey);
}

function sr_community_report_target_type_label(string $targetType): string
{
    $labels = [
        'post' => sr_t('community::ui.text.0b138cfe'),
        'comment' => sr_t('community::ui.text.c9fff683'),
        'message' => sr_t('community::ui.text.919bd592'),
    ];

    return (string) ($labels[$targetType] ?? $targetType);
}

function sr_community_public_report_context(string $targetType, int $targetId): array
{
    if ($targetId < 1 || preg_match('/\A[a-z][a-z0-9_]{1,29}\z/', $targetType) !== 1) {
        return [];
    }

    $reasonOptions = [];
    foreach (sr_community_report_reason_keys() as $reasonKey) {
        $reasonOptions[$reasonKey] = sr_community_report_reason_label($reasonKey);
    }

    return [
        'target_type' => $targetType,
        'target_id' => $targetId,
        'title' => sr_community_report_target_type_label($targetType) . ' 신고',
        'reason_options' => $reasonOptions,
    ];
}

function sr_community_public_report_pop_feedback(): array
{
    $errors = [];
    if (isset($_SESSION['sr_community_report_errors']) && is_array($_SESSION['sr_community_report_errors'])) {
        foreach ($_SESSION['sr_community_report_errors'] as $error) {
            if (is_string($error) && $error !== '') {
                $errors[] = $error;
            }
        }
    }
    $notice = isset($_SESSION['sr_community_report_notice']) && is_string($_SESSION['sr_community_report_notice'])
        ? $_SESSION['sr_community_report_notice']
        : '';
    unset($_SESSION['sr_community_report_errors'], $_SESSION['sr_community_report_notice']);

    return [
        'errors' => $errors,
        'notice' => $notice,
    ];
}

function sr_community_public_report_form_html(array $context): string
{
    $targetType = (string) ($context['target_type'] ?? '');
    $targetId = (int) ($context['target_id'] ?? 0);
    $reasonOptions = is_array($context['reason_options'] ?? null) ? $context['reason_options'] : [];
    if ($targetId < 1 || preg_match('/\A[a-z][a-z0-9_]{1,29}\z/', $targetType) !== 1 || $reasonOptions === []) {
        return '';
    }

    $title = trim((string) ($context['title'] ?? ''));
    if ($title === '') {
        $title = sr_community_report_target_type_label($targetType) . ' 신고';
    }
    $fieldIdPrefix = 'community_public_report_' . $targetType . '_' . (string) $targetId;

    ob_start();
    ?>
    <form method="post" action="<?php echo sr_e(sr_url('/community/report')); ?>" class="card">
        <div class="card-header"><h2 class="card-title"><?php echo sr_e($title); ?></h2></div>
        <div class="card-body ui-card-body-stack">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="target_type" value="<?php echo sr_e($targetType); ?>">
            <input type="hidden" name="target_id" value="<?php echo sr_e((string) $targetId); ?>">
            <p>
                <label class="ui-field" for="<?php echo sr_e($fieldIdPrefix . '_reason_key'); ?>">
                    <span><?php echo sr_e(sr_t('community::ui.text.162e66be')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                    <select id="<?php echo sr_e($fieldIdPrefix . '_reason_key'); ?>" name="reason_key" required class="form-select form-control-medium">
                        <?php foreach ($reasonOptions as $reasonKey => $reasonLabel) { ?>
                            <option value="<?php echo sr_e((string) $reasonKey); ?>"><?php echo sr_e((string) $reasonLabel); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label class="ui-field" for="<?php echo sr_e($fieldIdPrefix . '_memo_text'); ?>">
                    <span><?php echo sr_e(sr_t('community::ui.text.c8a14bcd')); ?></span>
                    <textarea id="<?php echo sr_e($fieldIdPrefix . '_memo_text'); ?>" name="memo_text" rows="3" cols="60" class="form-textarea form-control-wide"></textarea>
                </label>
            </p>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.text.bbb56c63')); ?></button>
        </div>
    </form>
    <?php
    $html = ob_get_clean();

    return is_string($html) ? $html : '';
}
