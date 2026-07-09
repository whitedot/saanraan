<?php

$adminNotificationTemplateContext = isset($adminNotificationTemplateContext) && is_array($adminNotificationTemplateContext) ? $adminNotificationTemplateContext : [];
$notificationTemplateRows = isset($notificationTemplateRows) && is_array($notificationTemplateRows) ? $notificationTemplateRows : [];
$notificationTemplateChannelOptions = isset($notificationTemplateChannelOptions) && is_array($notificationTemplateChannelOptions) ? $notificationTemplateChannelOptions : ['site'];
$notificationTemplateSortOptions = isset($notificationTemplateSortOptions) && is_array($notificationTemplateSortOptions) ? $notificationTemplateSortOptions : [];
$notificationTemplateDefaultSort = isset($notificationTemplateDefaultSort) && is_array($notificationTemplateDefaultSort) ? $notificationTemplateDefaultSort : sr_admin_sort_default('label', 'asc');
$notificationTemplateSort = isset($notificationTemplateSort) && is_array($notificationTemplateSort) ? $notificationTemplateSort : $notificationTemplateDefaultSort;
$adminPageTitle = (string) ($adminNotificationTemplateContext['title'] ?? '알림 템플릿 관리');
$adminPageSubtitle = (string) ($adminNotificationTemplateContext['subtitle'] ?? '회원에게 발송되는 알림 문구와 발송 수단을 관리합니다.');
$adminContainerClass = 'admin-page-notification-event-templates';
$notificationTemplateReturnPath = (string) ($adminNotificationTemplateContext['return_path'] ?? '');
$notificationTemplateTotalCount = count($notificationTemplateRows);
$notificationTemplateEnabledCount = 0;
foreach ($notificationTemplateRows as $notificationTemplateStatusRow) {
    if (!empty($notificationTemplateStatusRow['enabled'])) {
        $notificationTemplateEnabledCount++;
    }
}
$notificationTemplateAllEnabled = $notificationTemplateTotalCount > 0 && $notificationTemplateEnabledCount === $notificationTemplateTotalCount;
$notificationTemplateMixedEnabled = $notificationTemplateEnabledCount > 0 && $notificationTemplateEnabledCount < $notificationTemplateTotalCount;
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<section class="card admin-list-card">
    <div class="card-header">
        <h2 class="card-title">알림 템플릿 목록</h2>
        <div class="card-actions">
            <form method="post" action="<?php echo sr_e(sr_url($notificationTemplateReturnPath)); ?>" class="admin-notification-template-bulk-form" data-notification-template-bulk-form>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="bulk_status">
                <?php echo sr_admin_switch_html('notification_template_bulk_enabled', 'bulk_enabled', '1', $notificationTemplateAllEnabled, '전체 사용', '0', ' data-notification-template-bulk-switch' . ($notificationTemplateMixedEnabled ? ' data-notification-template-bulk-mixed="1"' : '') . ($notificationTemplateRows === [] ? ' disabled' : '')); ?>
            </form>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-notification-template-table">
            <caption class="sr-only">알림 템플릿 목록</caption>
            <thead>
                <tr>
                    <th<?php echo sr_admin_sort_aria('label', $notificationTemplateSort); ?>><?php echo sr_admin_sort_header_html('알림', 'label', $notificationTemplateSort, $notificationTemplateSortOptions, $notificationTemplateDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('title', $notificationTemplateSort); ?>><?php echo sr_admin_sort_header_html('제목', 'title', $notificationTemplateSort, $notificationTemplateSortOptions, $notificationTemplateDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $notificationTemplateSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $notificationTemplateSort, $notificationTemplateSortOptions, $notificationTemplateDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('channels', $notificationTemplateSort); ?>><?php echo sr_admin_sort_header_html('발송 수단', 'channels', $notificationTemplateSort, $notificationTemplateSortOptions, $notificationTemplateDefaultSort); ?></th>
                    <th class="admin-table-actions-cell">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($notificationTemplateRows === []) { ?>
                    <tr><td colspan="5" class="admin-empty-state">표시할 알림 템플릿이 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($notificationTemplateRows as $rowIndex => $templateRow) { ?>
                    <?php
                    $eventKey = (string) ($templateRow['event_key'] ?? '');
                    $label = (string) ($templateRow['label'] ?? $eventKey);
                    $enabled = !empty($templateRow['enabled']);
                    $channels = isset($templateRow['channels']) && is_array($templateRow['channels']) ? $templateRow['channels'] : ['site'];
                    $modalId = 'notification-template-modal-' . (string) $rowIndex;
                    $fieldSuffix = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $eventKey) ?? (string) $rowIndex;
                    $variables = isset($templateRow['variables']) && is_array($templateRow['variables']) ? $templateRow['variables'] : [];
                    ?>
                    <tr>
                        <td class="admin-table-break admin-notification-template-label-cell">
                            <strong><?php echo sr_e($label); ?></strong>
                        </td>
                        <td class="admin-table-break admin-notification-template-title-cell"><?php echo sr_e((string) ($templateRow['title_template'] ?? '')); ?></td>
                        <td class="admin-table-nowrap"><span class="badge-status <?php echo $enabled ? 'is-success' : 'is-danger'; ?>"><?php echo sr_e($enabled ? '사용' : '중지'); ?></span></td>
                        <td class="admin-table-nowrap admin-notification-template-channel-cell">
                            <span class="badge-list">
                                <?php foreach ($channels as $channel) { ?>
                                    <span class="badge-status is-success"><?php echo sr_e(sr_admin_code_label((string) $channel, 'notification_channel')); ?></span>
                                <?php } ?>
                            </span>
                        </td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e('수정'); ?>" title="<?php echo sr_e('수정'); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($modalId); ?>" data-overlay="#<?php echo sr_e($modalId); ?>">
                                    <?php echo sr_material_icon_html('edit'); ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php foreach ($notificationTemplateRows as $rowIndex => $templateRow) { ?>
    <?php
    $eventKey = (string) ($templateRow['event_key'] ?? '');
    $label = (string) ($templateRow['label'] ?? $eventKey);
    $enabled = !empty($templateRow['enabled']);
    $channels = isset($templateRow['channels']) && is_array($templateRow['channels']) ? $templateRow['channels'] : ['site'];
    $modalId = 'notification-template-modal-' . (string) $rowIndex;
    $fieldSuffix = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $eventKey) ?? (string) $rowIndex;
    $bodyFieldId = 'notification_template_body_' . $fieldSuffix;
    $variables = isset($templateRow['variables']) && is_array($templateRow['variables']) ? $templateRow['variables'] : [];
    ?>
    <div id="<?php echo sr_e($modalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($modalId); ?>_title" aria-hidden="true" inert>
        <div class="modal-dialog modal-dialog-lg">
            <form method="post" action="<?php echo sr_e(sr_url($notificationTemplateReturnPath)); ?>" class="modal-content ui-form-theme">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="event_key" value="<?php echo sr_e($eventKey); ?>">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($modalId); ?>_title" class="modal-title"><?php echo sr_e($label); ?></h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($modalId); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body admin-form">
                    <div class="form-row">
                        <label class="form-label" for="notification_template_title_<?php echo sr_e($fieldSuffix); ?>">제목 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <input id="notification_template_title_<?php echo sr_e($fieldSuffix); ?>" type="text" name="title_template" value="<?php echo sr_e((string) ($templateRow['title_template'] ?? '')); ?>" maxlength="160" required class="form-input form-control-full" data-overlay-focus>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="<?php echo sr_e($bodyFieldId); ?>">본문 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <textarea id="<?php echo sr_e($bodyFieldId); ?>" name="body_template" rows="8" maxlength="4000" required class="form-textarea form-control-full"><?php echo sr_e((string) ($templateRow['body_template'] ?? '')); ?></textarea>
                            <?php if ($variables !== []) { ?>
                                <div class="badge-list admin-delivery-template-variable-list notification-template-variable-list" aria-label="본문 변수" data-notification-template-variable-list>
                                    <?php foreach ($variables as $name => $variableLabel) { ?>
                                        <button type="button" class="badge-list-item admin-delivery-template-variable-button notification-template-variable-button" data-notification-template-variable="<?php echo sr_e((string) $name); ?>" data-notification-template-target="<?php echo sr_e($bodyFieldId); ?>" aria-label="<?php echo sr_e((string) $variableLabel . ' 변수 추가'); ?>">
                                            <span class="badge-list-label">{<?php echo sr_e((string) $name); ?>}</span>
                                            <span class="badge-list-summary"><?php echo sr_e((string) $variableLabel); ?></span>
                                        </button>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label">발송 수단 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <div class="filtering-toggle-group admin-checkbox-toggle-group" role="group" aria-label="발송 수단">
                                <?php foreach ($notificationTemplateChannelOptions as $channelIndex => $channel) { ?>
                                    <?php
                                    $channel = (string) $channel;
                                    $channelInputId = 'notification_template_channel_' . $fieldSuffix . '_' . (string) $channelIndex;
                                    $groupClass = $channelIndex === 0 ? 'btn-group-start' : ($channelIndex === count($notificationTemplateChannelOptions) - 1 ? 'btn-group-end' : 'btn-group-middle');
                                    ?>
                                    <span class="filtering-toggle-item">
                                        <input id="<?php echo sr_e($channelInputId); ?>" type="checkbox" name="channels[]" value="<?php echo sr_e($channel); ?>" class="form-choice-toggle-input sr-only"<?php echo in_array($channel, $channels, true) ? ' checked' : ''; ?>>
                                        <label for="<?php echo sr_e($channelInputId); ?>" class="btn btn-choice-light <?php echo sr_e($groupClass); ?>"><?php echo sr_admin_choice_label_html(sr_admin_code_label($channel, 'notification_channel')); ?></label>
                                    </span>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="notification_template_enabled_<?php echo sr_e($fieldSuffix); ?>">상태</label>
                        <div class="form-field">
                            <?php echo sr_admin_switch_html('notification_template_enabled_' . $fieldSuffix, 'enabled', '1', $enabled, '사용'); ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>">닫기</button>
                    <button type="submit" class="btn btn-solid-primary modal-action">저장</button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<script>
(function () {
    var bulkSwitch = document.querySelector('[data-notification-template-bulk-switch]');
    if (bulkSwitch) {
        if (bulkSwitch.getAttribute('data-notification-template-bulk-mixed') === '1') {
            bulkSwitch.indeterminate = true;
        }
        bulkSwitch.addEventListener('change', function () {
            bulkSwitch.indeterminate = false;
            if (bulkSwitch.form) {
                bulkSwitch.form.submit();
            }
        });
    }

    document.querySelectorAll('[data-notification-template-variable]').forEach(function (button) {
        button.addEventListener('click', function () {
            var targetId = button.getAttribute('data-notification-template-target') || '';
            var target = document.getElementById(targetId);
            var name = button.getAttribute('data-notification-template-variable') || '';
            if (!target || !name) {
                return;
            }
            var token = '{' + name + '}';
            var start = typeof target.selectionStart === 'number' ? target.selectionStart : target.value.length;
            var end = typeof target.selectionEnd === 'number' ? target.selectionEnd : target.value.length;
            target.value = target.value.slice(0, start) + token + target.value.slice(end);
            target.focus();
            if (typeof target.setSelectionRange === 'function') {
                target.setSelectionRange(start + token.length, start + token.length);
            }
        });
    });
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
