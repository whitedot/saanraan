<?php

$adminPageTitle = sr_t('popup_layer::ui.settings.fca22866');
$adminPageSubtitle = sr_t('popup_layer::ui.text.27af73ff');
$popupLayerHelpOpenLabel = sr_t('popup_layer::help.open');
$popupLayerSettingsHelpBodyHtml = static function (array $keys): string {
    $html = '';
    foreach ($keys as $key) {
        $html .= '<p>' . sr_e(sr_t((string) $key)) . '</p>';
    }

    return $html;
};
$popupLayerSettingsHelp = [
    'skin_key' => [
        'id' => 'popup_layer_admin_settings_help_skin_key',
        'title' => sr_t('popup_layer::settings.help.skin_key.title'),
        'body' => $popupLayerSettingsHelpBodyHtml(['popup_layer::settings.help.skin_key.body.1', 'popup_layer::settings.help.skin_key.body.2']),
    ],
    'default_status' => [
        'id' => 'popup_layer_admin_settings_help_default_status',
        'title' => sr_t('popup_layer::settings.help.default_status.title'),
        'body' => $popupLayerSettingsHelpBodyHtml(['popup_layer::settings.help.default_status.body.1', 'popup_layer::settings.help.default_status.body.2']),
    ],
    'default_target_option' => [
        'id' => 'popup_layer_admin_settings_help_default_target_option',
        'title' => sr_t('popup_layer::settings.help.default_target_option.title'),
        'body' => $popupLayerSettingsHelpBodyHtml(['popup_layer::settings.help.default_target_option.body.1', 'popup_layer::settings.help.default_target_option.body.2']),
    ],
    'default_match_type' => [
        'id' => 'popup_layer_admin_settings_help_default_match_type',
        'title' => sr_t('popup_layer::settings.help.default_match_type.title'),
        'body' => $popupLayerSettingsHelpBodyHtml(['popup_layer::settings.help.default_match_type.body.1', 'popup_layer::settings.help.default_match_type.body.2']),
    ],
    'default_dismiss_cookie_days' => [
        'id' => 'popup_layer_admin_settings_help_default_dismiss_cookie_days',
        'title' => sr_t('popup_layer::settings.help.default_dismiss_cookie_days.title'),
        'body' => $popupLayerSettingsHelpBodyHtml(['popup_layer::settings.help.default_dismiss_cookie_days.body.1', 'popup_layer::settings.help.default_dismiss_cookie_days.body.2']),
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/popup-layers/settings')); ?>" class="admin-form ui-form-theme">
    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('popup_layer::ui.settings.fca22866')); ?></h2>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="save_settings">
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('popup_layer_admin_popup_layer_settings_popup_layer_skin_key', sr_t('popup_layer::ui.text.58f7674b'), $popupLayerSettingsHelp['skin_key']['id'], $popupLayerHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <select id="popup_layer_admin_popup_layer_settings_popup_layer_skin_key" name="popup_layer_skin_key" class="form-select" required>
                                    <?php foreach ($popupLayerSkinOptions as $skinKey => $skinOption) { ?>
                                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $popupLayerSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                <p class="admin-form-help"><?php echo sr_e(sr_t('popup_layer::settings.help.skin_key.inline')); ?></p>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('popup_layer_admin_popup_layer_settings_default_status', sr_t('popup_layer::settings.default_status'), $popupLayerSettingsHelp['default_status']['id'], $popupLayerHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <select id="popup_layer_admin_popup_layer_settings_default_status" name="popup_layer_default_status" class="form-select" required>
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo $popupLayerDefaultStatus === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="admin-form-help"><?php echo sr_e(sr_t('popup_layer::settings.help.default_status.inline')); ?></p>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('popup_layer_admin_popup_layer_settings_default_target_option', sr_t('popup_layer::settings.default_target_option'), $popupLayerSettingsHelp['default_target_option']['id'], $popupLayerHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <select id="popup_layer_admin_popup_layer_settings_default_target_option" name="popup_layer_default_target_option" class="form-select" required>
                    <option value="<?php echo sr_e(sr_popup_layer_public_target_option_value()); ?>"<?php echo $popupLayerDefaultTargetOption === sr_popup_layer_public_target_option_value() ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_t('popup_layer::ui.text.11677edb')); ?>
                    </option>
                    <?php foreach ($availableTargets as $target) { ?>
                        <?php $optionValue = sr_popup_layer_target_option_value($target); ?>
                        <option value="<?php echo sr_e($optionValue); ?>"<?php echo $popupLayerDefaultTargetOption === $optionValue ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_popup_layer_target_option_label($target)); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="admin-form-help"><?php echo sr_e(sr_t('popup_layer::settings.help.default_target_option.inline')); ?></p>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('popup_layer_admin_popup_layer_settings_default_match_type', sr_t('popup_layer::settings.default_match_type'), $popupLayerSettingsHelp['default_match_type']['id'], $popupLayerHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <select id="popup_layer_admin_popup_layer_settings_default_match_type" name="popup_layer_default_match_type" class="form-select" required>
                    <?php foreach ($allowedMatchTypes as $matchType) { ?>
                        <option value="<?php echo sr_e($matchType); ?>"<?php echo $popupLayerDefaultMatchType === $matchType ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($matchType, 'match_type')); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="admin-form-help"><?php echo sr_e(sr_t('popup_layer::settings.help.default_match_type.inline')); ?></p>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('popup_layer_admin_popup_layer_settings_default_dismiss_cookie_days', sr_t('popup_layer::settings.default_dismiss_cookie_days'), $popupLayerSettingsHelp['default_dismiss_cookie_days']['id'], $popupLayerHelpOpenLabel); ?>
            <div class="admin-form-field">
                <input id="popup_layer_admin_popup_layer_settings_default_dismiss_cookie_days" type="number" name="popup_layer_default_dismiss_cookie_days" value="<?php echo sr_e((string) $popupLayerDefaultDismissCookieDays); ?>" class="form-input" min="0" max="365">
                <p class="admin-form-help"><?php echo sr_e(sr_t('popup_layer::settings.help.default_dismiss_cookie_days.inline')); ?></p>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('popup_layer::ui.list.f0aa41f6')); ?></a>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('popup_layer::ui.settings.save.2132c0ee')); ?></button>
    </div>
</form>

<?php foreach ($popupLayerSettingsHelp as $popupLayerHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $popupLayerHelpModal['id'], (string) $popupLayerHelpModal['title'], (string) $popupLayerHelpModal['body']); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
