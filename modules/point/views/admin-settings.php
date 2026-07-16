<?php

$adminPageTitle = sr_t('point::ui.settings.title');
$adminPageSubtitle = '';
$settings = isset($settings) && is_array($settings) ? $settings : ['usage_enabled' => true, 'display_name' => '포인트', 'unit_label' => 'P', 'default_expiration_days' => '0'];
$pointSettingsHelp = [
    'usage' => [
        'id' => 'point-settings-help-usage',
        'title' => sr_t('point::help.settings.usage.title'),
        'body' => '<p>' . sr_e(sr_t('point::help.settings.usage.body.1')) . '</p>'
            . '<p>' . sr_e(sr_t('point::help.settings.usage.body.2')) . '</p>',
    ],
    'expiration' => [
        'id' => 'point-settings-help-expiration',
        'title' => sr_t('point::help.settings.expiration.title'),
        'body' => '<p>' . sr_e(sr_t('point::help.settings.expiration.body.1')) . '</p>'
            . '<p>' . sr_e(sr_t('point::help.settings.expiration.body.2')) . '</p>'
            . '<p>' . sr_e(sr_t('point::help.settings.expiration.body.3')) . '</p>',
    ],
];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/points/settings')); ?>" class="admin-form ui-form-theme">
    <section class="card">
        <h2><?php echo sr_e(sr_t('point::ui.settings.title')); ?></h2>
        <?php echo sr_csrf_field(); ?>

        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('point_settings_usage_enabled', '포인트 기능', $pointSettingsHelp['usage']['id'], sr_t('point::help.open')); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('point_settings_usage_enabled', 'usage_enabled', '1', !empty($settings['usage_enabled']), '사용'); ?>
                <small class="form-help">끄면 새 포인트 거래와 보상·환전·유료 쿠폰 등 포인트를 사용하는 기능을 중단합니다.</small>
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
            <?php echo sr_admin_form_label_help_html('point_settings_default_expiration_days', '기본 ' . sr_t('point::ui.settings.default_expiration_days'), $pointSettingsHelp['expiration']['id'], sr_t('point::help.open')); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="point_settings_default_expiration_days" type="number" name="default_expiration_days" value="<?php echo sr_e((string) ($settings['default_expiration_days'] ?? '0')); ?>" class="form-input" min="0" max="3650" step="1">
                    <span class="input-group-text"><?php echo sr_e(sr_t('point::ui.settings.days_unit')); ?></span>
                </div>
                <small class="form-help">새로 지급하는 포인트에 적용합니다. 0이면 만료일을 두지 않습니다.</small>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-split">
        <button type="submit" name="intent" value="save_settings" class="btn btn-solid-primary"><?php echo sr_e(sr_t('point::ui.settings.save')); ?></button>
    </div>
</form>

<?php foreach ($pointSettingsHelp as $pointSettingsHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $pointSettingsHelpModal['id'], (string) $pointSettingsHelpModal['title'], (string) $pointSettingsHelpModal['body']); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
