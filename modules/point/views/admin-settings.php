<?php

$adminPageTitle = sr_t('point::ui.settings.title');
$adminPageSubtitle = '';
$settings = isset($settings) && is_array($settings) ? $settings : ['usage_enabled' => true, 'display_name' => '포인트', 'unit_label' => 'P', 'default_expiration_days' => '0'];
$notificationCases = isset($notificationCases) && is_array($notificationCases) ? $notificationCases : sr_point_notification_cases();
$notificationCaseSettings = sr_point_notification_case_settings_from_value($settings['notification_cases'] ?? []);
$notificationChannelOptions = isset($notificationChannelOptions) && is_array($notificationChannelOptions) ? $notificationChannelOptions : ['site'];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/points/settings')); ?>" class="admin-form ui-form-theme">
    <section class="card">
        <h2><?php echo sr_e(sr_t('point::ui.settings.title')); ?></h2>
        <?php echo sr_csrf_field(); ?>

        <div class="form-row">
            <label class="form-label" for="point_settings_usage_enabled">포인트 사용 여부</label>
            <div class="form-field">
                <?php echo sr_admin_switch_html('point_settings_usage_enabled', 'usage_enabled', '1', !empty($settings['usage_enabled']), '사용'); ?>
                <small class="form-help">사용하지 않으면 보상, 환전, 쿠폰 유료 발급 등 포인트를 선택하거나 새 거래를 만드는 사용처에서 제외됩니다.</small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="point_settings_display_name"><?php echo sr_e(sr_t('point::ui.settings.display_name')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('point::ui.required.1f227c67')); ?></span></label>
            <div class="form-field">
                <input id="point_settings_display_name" type="text" name="display_name" value="<?php echo sr_e((string) ($settings['display_name'] ?? '포인트')); ?>" class="form-input" maxlength="40" required>
                <small class="form-help"><?php echo sr_e(sr_t('point::ui.settings.display_name_help')); ?></small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="point_settings_unit_label"><?php echo sr_e(sr_t('point::ui.settings.unit_label')); ?></label>
            <div class="form-field">
                <input id="point_settings_unit_label" type="text" name="unit_label" value="<?php echo sr_e((string) ($settings['unit_label'] ?? 'P')); ?>" class="form-input" maxlength="20">
                <small class="form-help"><?php echo sr_e(sr_t('point::ui.settings.unit_label_help')); ?></small>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="point_settings_default_expiration_days"><?php echo sr_e(sr_t('point::ui.settings.default_expiration_days')); ?></label>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="point_settings_default_expiration_days" type="number" name="default_expiration_days" value="<?php echo sr_e((string) ($settings['default_expiration_days'] ?? '0')); ?>" class="form-input" min="0" max="3650" step="1">
                    <span class="input-group-text"><?php echo sr_e(sr_t('point::ui.settings.days_unit')); ?></span>
                </div>
                <small class="form-help"><?php echo sr_e(sr_t('point::ui.settings.default_expiration_days_help')); ?></small>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>회원 알림</h2>
        <?php foreach ($notificationCases as $caseKey => $case) { ?>
            <?php
            $caseKey = (string) $caseKey;
            $caseSetting = isset($notificationCaseSettings[$caseKey]) && is_array($notificationCaseSettings[$caseKey]) ? $notificationCaseSettings[$caseKey] : ['enabled' => true, 'channels' => ['site']];
            $caseEnabled = !empty($caseSetting['enabled']);
            $caseChannels = sr_point_notification_channels_from_value($caseSetting['channels'] ?? ['site']);
            $caseId = 'point_notification_case_' . $caseKey;
            ?>
            <div class="form-row" data-point-notification-case="<?php echo sr_e($caseKey); ?>">
                <label class="form-label" for="<?php echo sr_e($caseId); ?>"><?php echo sr_e((string) ($case['label'] ?? '알림') . ' 사용 여부'); ?></label>
                <div class="form-field">
                    <?php echo sr_admin_switch_html($caseId, 'notification_cases[' . $caseKey . '][enabled]', '1', $caseEnabled, '사용', '0', ' data-point-notification-case-toggle data-point-notification-case-key="' . sr_e($caseKey) . '"'); ?>
                    <p class="form-help"><?php echo sr_e((string) ($case['description'] ?? '')); ?></p>
                </div>
            </div>
            <div class="form-row" data-point-notification-case="<?php echo sr_e($caseKey); ?>">
                <label class="form-label"><?php echo sr_e((string) ($case['label'] ?? '알림') . ' 채널'); ?> <span class="sr-required-label" data-point-notification-required-label data-point-notification-case-key="<?php echo sr_e($caseKey); ?>">(필수)</span></label>
                <div class="form-field">
                    <div class="filtering-toggle-group admin-checkbox-toggle-group" role="group" aria-label="<?php echo sr_e((string) ($case['label'] ?? '알림') . ' 채널'); ?>">
                        <?php foreach ($notificationChannelOptions as $channelIndex => $channel) { ?>
                            <?php
                            $channel = (string) $channel;
                            $channelInputId = $caseId . '_channel_' . (string) $channelIndex;
                            $groupClass = $channelIndex === 0 ? 'btn-group-start' : ($channelIndex === count($notificationChannelOptions) - 1 ? 'btn-group-end' : 'btn-group-middle');
                            ?>
                            <span class="filtering-toggle-item">
                                <input id="<?php echo sr_e($channelInputId); ?>" type="checkbox" name="notification_cases[<?php echo sr_e($caseKey); ?>][channels][]" value="<?php echo sr_e($channel); ?>" class="form-choice-toggle-input sr-only" data-point-notification-channel data-point-notification-case-key="<?php echo sr_e($caseKey); ?>"<?php echo in_array($channel, $caseChannels, true) ? ' checked' : ''; ?>>
                                <label for="<?php echo sr_e($channelInputId); ?>" class="btn btn-choice-light <?php echo sr_e($groupClass); ?>"><?php echo sr_admin_choice_label_html(sr_admin_code_label($channel, 'notification_channel')); ?></label>
                            </span>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>
    </section>

    <div class="form-sticky-actions form-actions form-actions-split">
        <button type="submit" name="intent" value="save_settings" class="btn btn-solid-primary"><?php echo sr_e(sr_t('point::ui.settings.save')); ?></button>
    </div>
</form>

<script>
(function () {
    var form = document.querySelector('form[action="<?php echo sr_e(sr_url('/admin/points/settings')); ?>"]');
    if (!form) {
        return;
    }
    var caseKeys = [];
    Array.prototype.slice.call(form.querySelectorAll('[data-point-notification-case-key]')).forEach(function (control) {
        var caseKey = control.getAttribute('data-point-notification-case-key') || '';
        if (caseKey && caseKeys.indexOf(caseKey) === -1) {
            caseKeys.push(caseKey);
        }
    });
    function syncCase(caseKey) {
        var toggle = form.querySelector('[data-point-notification-case-toggle][data-point-notification-case-key="' + caseKey + '"]');
        var channels = Array.prototype.slice.call(form.querySelectorAll('[data-point-notification-channel][data-point-notification-case-key="' + caseKey + '"]'));
        var enabled = !toggle || toggle.checked;
        var selected = channels.some(function (channel) {
            return channel.checked;
        });
        channels.forEach(function (channel) {
            channel.disabled = !enabled;
        });
        Array.prototype.slice.call(form.querySelectorAll('[data-point-notification-required-label][data-point-notification-case-key="' + caseKey + '"]')).forEach(function (label) {
            label.hidden = !enabled;
        });
        if (channels[0] && typeof channels[0].setCustomValidity === 'function') {
            channels[0].setCustomValidity(!enabled || selected ? '' : '알림 채널을 하나 이상 선택하세요.');
        }
    }
    caseKeys.forEach(function (caseKey) {
        Array.prototype.slice.call(form.querySelectorAll('[data-point-notification-case-key="' + caseKey + '"]')).forEach(function (control) {
            control.addEventListener('change', function () {
                syncCase(caseKey);
            });
        });
        syncCase(caseKey);
    });
    form.addEventListener('submit', function (event) {
        var invalidChannel = null;
        caseKeys.forEach(function (caseKey) {
            syncCase(caseKey);
            if (!invalidChannel) {
                invalidChannel = Array.prototype.slice.call(form.querySelectorAll('[data-point-notification-channel][data-point-notification-case-key="' + caseKey + '"]')).find(function (channel) {
                    return !channel.validity.valid;
                }) || null;
            }
        });
        if (invalidChannel) {
            event.preventDefault();
            invalidChannel.reportValidity();
        }
    });
}());
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
