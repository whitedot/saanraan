<?php

$adminPageTitle = sr_t('banner::ui.banner.settings.cc368bd0');
$adminPageSubtitle = '';
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
$bannerTargetServiceOptions = sr_banner_target_service_options($availableTargets, true);
$bannerDefaultTargetServiceKey = sr_banner_selected_target_service_key($bannerDefaultTargetOption);
if ($bannerDefaultTargetServiceKey === '') {
    $bannerDefaultTargetServiceKey = sr_banner_public_target_option_value();
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/banners/settings')); ?>" class="admin-form ui-form-theme">
    <section class="card">
        <h2><?php echo sr_e(sr_t('banner::ui.banner.settings.cc368bd0')); ?></h2>
        <p><?php echo sr_e(sr_t('banner::ui.banner.banner.select.settings.115fa68f')); ?></p>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="save_settings">
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('banner_admin_banner_settings_banner_skin_key', sr_t('banner::ui.banner.46b4fae5'), $bannerSettingsHelp['skin_key']['id'], $bannerHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="banner_admin_banner_settings_banner_skin_key" name="banner_skin_key" class="form-select" required>
                                    <?php foreach ($bannerSkinOptions as $skinKey => $skinOption) { ?>
                                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $bannerSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                            (<?php echo sr_e(implode(', ', array_map('sr_banner_placement_kind_label', is_array($skinOption['supports'] ?? null) ? $skinOption['supports'] : ['inline']))); ?>)
                                        </option>
                                    <?php } ?>
                </select>
                <p class="form-help"><?php echo sr_e(sr_t('banner::settings.help.skin_key.inline')); ?></p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('banner_admin_banner_settings_default_status', sr_t('banner::settings.default_status'), $bannerSettingsHelp['default_status']['id'], $bannerHelpOpenLabel, true); ?>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('banner_admin_banner_settings_default_status', 'banner_default_status', sr_admin_code_label_options($allowedStatuses, 'content_status'), (string) $bannerDefaultStatus, true); ?>
                <p class="form-help"><?php echo sr_e(sr_t('banner::settings.help.default_status.inline')); ?></p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('banner_admin_banner_settings_default_target_service_key', '기본 서비스', $bannerSettingsHelp['default_target_option']['id'], $bannerHelpOpenLabel, true); ?>
            <div class="form-field">
                <input type="hidden" name="banner_default_target_option" value="<?php echo sr_e($bannerDefaultTargetOption); ?>" data-admin-target-option>
                <select id="banner_admin_banner_settings_default_target_service_key" name="banner_default_target_service_key" class="form-select" required data-admin-target-service>
                    <?php foreach ($bannerTargetServiceOptions as $serviceKey => $serviceLabel) { ?>
                        <option value="<?php echo sr_e((string) $serviceKey); ?>"<?php echo $bannerDefaultTargetServiceKey === (string) $serviceKey ? ' selected' : ''; ?>><?php echo sr_e((string) $serviceLabel); ?></option>
                    <?php } ?>
                </select>
                <p class="form-help"><?php echo sr_e('새 배너가 처음 사용할 서비스입니다. 공용은 직접 선택용 배너입니다.'); ?></p>
            </div>
        </div>
        <div class="form-row" data-admin-target-detail-row<?php echo sr_banner_is_public_target_option($bannerDefaultTargetOption) ? ' hidden' : ''; ?>>
            <label class="form-label" for="banner_admin_banner_settings_default_target_detail_option"><?php echo sr_e('기본 상세'); ?> <span class="sr-required-label" data-admin-target-detail-required<?php echo sr_banner_is_public_target_option($bannerDefaultTargetOption) ? ' hidden' : ''; ?>><?php echo sr_e('(필수)'); ?></span></label>
            <div class="form-field">
                <select id="banner_admin_banner_settings_default_target_detail_option" name="banner_default_target_detail_option" class="form-select" data-admin-target-detail<?php echo sr_banner_is_public_target_option($bannerDefaultTargetOption) ? ' disabled' : ' required'; ?>>
                    <?php foreach ($availableTargets as $target) { ?>
                        <?php $optionValue = sr_banner_target_option_value($target); ?>
                        <option value="<?php echo sr_e($optionValue); ?>" data-service="<?php echo sr_e(sr_banner_target_service_key($target)); ?>"<?php echo $bannerDefaultTargetOption === $optionValue ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_banner_target_admin_label($target)); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help"><?php echo sr_e(sr_t('banner::settings.help.default_target_option.inline')); ?></p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('banner_admin_banner_settings_default_match_type', sr_t('banner::settings.default_match_type'), $bannerSettingsHelp['default_match_type']['id'], $bannerHelpOpenLabel, true); ?>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('banner_admin_banner_settings_default_match_type', 'banner_default_match_type', sr_admin_code_label_options($allowedMatchTypes, 'match_type'), (string) $bannerDefaultMatchType, true); ?>
                <p class="form-help"><?php echo sr_e(sr_t('banner::settings.help.default_match_type.inline')); ?></p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('banner_admin_banner_settings_default_sort_order', sr_t('banner::settings.default_sort_order'), $bannerSettingsHelp['default_sort_order']['id'], $bannerHelpOpenLabel); ?>
            <div class="form-field">
                <input id="banner_admin_banner_settings_default_sort_order" type="number" name="banner_default_sort_order" value="<?php echo sr_e((string) $bannerDefaultSortOrder); ?>" class="form-input">
                <p class="form-help"><?php echo sr_e(sr_t('banner::settings.help.default_sort_order.inline')); ?></p>
            </div>
        </div>
    </section>
    <div class="form-sticky-actions form-actions form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('banner::ui.banner.list.f989d740')); ?></a>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('banner::ui.banner.settings.save.760342b3')); ?></button>
    </div>
</form>

<?php foreach ($bannerSettingsHelp as $bannerHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $bannerHelpModal['id'], (string) $bannerHelpModal['title'], (string) $bannerHelpModal['body']); ?>
<?php } ?>

<script>
(function () {
    var form = document.querySelector('form.admin-form');
    if (!form) {
        return;
    }
    var publicTarget = <?php echo sr_js_json_encode(sr_banner_public_target_option_value()); ?>;
    var service = form.querySelector('[data-admin-target-service]');
    var detail = form.querySelector('[data-admin-target-detail]');
    var targetOption = form.querySelector('[data-admin-target-option]');
    var detailRow = form.querySelector('[data-admin-target-detail-row]');
    var detailRequired = form.querySelector('[data-admin-target-detail-required]');

    function syncTarget() {
        var isPublic = service && service.value === publicTarget;
        if (detailRow) {
            detailRow.hidden = isPublic;
        }
        if (detailRequired) {
            detailRequired.hidden = isPublic;
        }
        if (detail) {
            var visibleOptions = [];
            Array.prototype.forEach.call(detail.options, function (option) {
                var visible = !isPublic && option.getAttribute('data-service') === service.value;
                option.hidden = !visible;
                option.disabled = !visible;
                if (visible) {
                    visibleOptions.push(option);
                }
            });
            detail.disabled = isPublic;
            detail.required = !isPublic;
            if (!isPublic && (!detail.value || detail.selectedIndex < 0 || detail.options[detail.selectedIndex].disabled) && visibleOptions.length > 0) {
                detail.value = visibleOptions[0].value;
            }
        }
        if (targetOption) {
            targetOption.value = isPublic ? publicTarget : (detail ? detail.value : '');
        }
    }

    if (service) {
        service.addEventListener('change', syncTarget);
    }
    if (detail) {
        detail.addEventListener('change', syncTarget);
    }
    syncTarget();
}());
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
