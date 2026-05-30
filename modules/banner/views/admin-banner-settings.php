<?php

$adminPageTitle = sr_t('banner::ui.banner.settings.cc368bd0');
$adminPageSubtitle = sr_t('banner::ui.banner.d6c7ef09');
$bannerHelpOpenLabel = sr_t('banner::help.open');
$bannerSettingsHelpBodyHtml = static function (array $keys): string {
    $html = '';
    foreach ($keys as $key) {
        $html .= '<p>' . sr_e(sr_t((string) $key)) . '</p>';
    }

    return $html;
};
$bannerSettingsHelp = [
    'skin_key' => [
        'id' => 'banner_admin_settings_help_skin_key',
        'title' => sr_t('banner::settings.help.skin_key.title'),
        'body' => $bannerSettingsHelpBodyHtml(['banner::settings.help.skin_key.body.1', 'banner::settings.help.skin_key.body.2']),
    ],
    'default_status' => [
        'id' => 'banner_admin_settings_help_default_status',
        'title' => sr_t('banner::settings.help.default_status.title'),
        'body' => $bannerSettingsHelpBodyHtml(['banner::settings.help.default_status.body.1', 'banner::settings.help.default_status.body.2']),
    ],
    'default_target_option' => [
        'id' => 'banner_admin_settings_help_default_target_option',
        'title' => sr_t('banner::settings.help.default_target_option.title'),
        'body' => $bannerSettingsHelpBodyHtml(['banner::settings.help.default_target_option.body.1', 'banner::settings.help.default_target_option.body.2']),
    ],
    'default_match_type' => [
        'id' => 'banner_admin_settings_help_default_match_type',
        'title' => sr_t('banner::settings.help.default_match_type.title'),
        'body' => $bannerSettingsHelpBodyHtml(['banner::settings.help.default_match_type.body.1', 'banner::settings.help.default_match_type.body.2']),
    ],
    'default_sort_order' => [
        'id' => 'banner_admin_settings_help_default_sort_order',
        'title' => sr_t('banner::settings.help.default_sort_order.title'),
        'body' => $bannerSettingsHelpBodyHtml(['banner::settings.help.default_sort_order.body.1', 'banner::settings.help.default_sort_order.body.2']),
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/banners/settings')); ?>" class="admin-form ui-form-theme">
    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('banner::ui.banner.settings.cc368bd0')); ?></h2>
        <p><?php echo sr_e(sr_t('banner::ui.banner.banner.select.settings.115fa68f')); ?></p>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="save_settings">
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('banner_admin_banner_settings_banner_skin_key', sr_t('banner::ui.banner.46b4fae5'), $bannerSettingsHelp['skin_key']['id'], $bannerHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <select id="banner_admin_banner_settings_banner_skin_key" name="banner_skin_key" class="form-select" required>
                                    <?php foreach ($bannerSkinOptions as $skinKey => $skinOption) { ?>
                                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $bannerSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                            (<?php echo sr_e(implode(', ', array_map('sr_banner_placement_kind_label', is_array($skinOption['supports'] ?? null) ? $skinOption['supports'] : ['inline']))); ?>)
                                        </option>
                                    <?php } ?>
                </select>
                <p class="admin-form-help"><?php echo sr_e(sr_t('banner::settings.help.skin_key.inline')); ?></p>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('banner_admin_banner_settings_default_status', sr_t('banner::settings.default_status'), $bannerSettingsHelp['default_status']['id'], $bannerHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <select id="banner_admin_banner_settings_default_status" name="banner_default_status" class="form-select" required>
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo $bannerDefaultStatus === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="admin-form-help"><?php echo sr_e(sr_t('banner::settings.help.default_status.inline')); ?></p>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('banner_admin_banner_settings_default_target_option', sr_t('banner::settings.default_target_option'), $bannerSettingsHelp['default_target_option']['id'], $bannerHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <select id="banner_admin_banner_settings_default_target_option" name="banner_default_target_option" class="form-select" required>
                    <option value="<?php echo sr_e(sr_banner_public_target_option_value()); ?>"<?php echo $bannerDefaultTargetOption === sr_banner_public_target_option_value() ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_t('banner::ui.banner.48de068b')); ?>
                    </option>
                    <?php foreach ($availableTargets as $target) { ?>
                        <?php $optionValue = sr_banner_target_option_value($target); ?>
                        <option value="<?php echo sr_e($optionValue); ?>"<?php echo $bannerDefaultTargetOption === $optionValue ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $target['label']); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="admin-form-help"><?php echo sr_e(sr_t('banner::settings.help.default_target_option.inline')); ?></p>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('banner_admin_banner_settings_default_match_type', sr_t('banner::settings.default_match_type'), $bannerSettingsHelp['default_match_type']['id'], $bannerHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <select id="banner_admin_banner_settings_default_match_type" name="banner_default_match_type" class="form-select" required>
                    <?php foreach ($allowedMatchTypes as $matchType) { ?>
                        <option value="<?php echo sr_e($matchType); ?>"<?php echo $bannerDefaultMatchType === $matchType ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($matchType, 'match_type')); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="admin-form-help"><?php echo sr_e(sr_t('banner::settings.help.default_match_type.inline')); ?></p>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('banner_admin_banner_settings_default_sort_order', sr_t('banner::settings.default_sort_order'), $bannerSettingsHelp['default_sort_order']['id'], $bannerHelpOpenLabel); ?>
            <div class="admin-form-field">
                <input id="banner_admin_banner_settings_default_sort_order" type="number" name="banner_default_sort_order" value="<?php echo sr_e((string) $bannerDefaultSortOrder); ?>" class="form-input">
                <p class="admin-form-help"><?php echo sr_e(sr_t('banner::settings.help.default_sort_order.inline')); ?></p>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('banner::ui.banner.list.f989d740')); ?></a>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('banner::ui.banner.settings.save.760342b3')); ?></button>
    </div>
</form>

<?php foreach ($bannerSettingsHelp as $bannerHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $bannerHelpModal['id'], (string) $bannerHelpModal['title'], (string) $bannerHelpModal['body']); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
