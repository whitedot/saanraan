<?php

$adminPageTitle = sr_t('popup_layer::ui.settings.fca22866');
$adminPageSubtitle = '';
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
    'editor' => [
        'id' => 'popup_layer_admin_settings_help_editor',
        'title' => '팝업 본문 에디터 도움말',
        'body' => '<p>' . sr_e('팝업 등록과 수정 화면의 본문 입력 방식입니다.') . '</p>'
            . '<p>' . sr_e('HTML 또는 CKEditor를 선택하면 팝업 본문은 허용된 HTML만 정화해 저장합니다. Markdown은 공개 출력 시 제한된 문법으로 HTML 변환됩니다.') . '</p>',
    ],
];
$popupLayerTargetServiceOptions = sr_popup_layer_target_service_options($availableTargets, true);
$popupLayerDefaultTargetServiceKey = sr_popup_layer_selected_target_service_key($popupLayerDefaultTargetOption);
if ($popupLayerDefaultTargetServiceKey === '') {
    $popupLayerDefaultTargetServiceKey = sr_popup_layer_public_target_option_value();
}
$popupLayerMatchTypeOptions = [
    'all' => '전체',
    'exact' => '선택',
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/popup-layers/settings')); ?>" class="admin-form ui-form-theme">
    <section class="card">
        <h2><?php echo sr_e(sr_t('popup_layer::ui.settings.fca22866')); ?></h2>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="save_settings">
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('popup_layer_admin_popup_layer_settings_popup_layer_skin_key', sr_t('popup_layer::ui.text.58f7674b'), $popupLayerSettingsHelp['skin_key']['id'], $popupLayerHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="popup_layer_admin_popup_layer_settings_popup_layer_skin_key" name="popup_layer_skin_key" class="form-select" required>
                                    <?php foreach ($popupLayerSkinOptions as $skinKey => $skinOption) { ?>
                                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $popupLayerSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                <p class="form-help"><?php echo sr_e(sr_t('popup_layer::settings.help.skin_key.inline')); ?></p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('popup_layer_admin_popup_layer_settings_default_status', sr_t('popup_layer::settings.default_status'), $popupLayerSettingsHelp['default_status']['id'], $popupLayerHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="popup_layer_admin_popup_layer_settings_default_status" name="popup_layer_default_status" class="form-select" required>
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo $popupLayerDefaultStatus === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help"><?php echo sr_e(sr_t('popup_layer::settings.help.default_status.inline')); ?></p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('popup_layer_admin_popup_layer_settings_default_target_service_key', '기본 서비스', $popupLayerSettingsHelp['default_target_option']['id'], $popupLayerHelpOpenLabel, true); ?>
            <div class="form-field">
                <input type="hidden" name="popup_layer_default_target_option" value="<?php echo sr_e($popupLayerDefaultTargetOption); ?>" data-admin-target-option>
                <select id="popup_layer_admin_popup_layer_settings_default_target_service_key" name="popup_layer_default_target_service_key" class="form-select" required data-admin-target-service>
                    <?php foreach ($popupLayerTargetServiceOptions as $serviceKey => $serviceLabel) { ?>
                        <option value="<?php echo sr_e((string) $serviceKey); ?>"<?php echo $popupLayerDefaultTargetServiceKey === (string) $serviceKey ? ' selected' : ''; ?>><?php echo sr_e((string) $serviceLabel); ?></option>
                    <?php } ?>
                </select>
                <p class="form-help"><?php echo sr_e('새 팝업레이어가 처음 사용할 서비스입니다. 공용은 직접 선택용 팝업레이어입니다.'); ?></p>
            </div>
        </div>
        <div class="form-row" data-admin-target-detail-row<?php echo sr_popup_layer_is_public_target_option($popupLayerDefaultTargetOption) ? ' hidden' : ''; ?>>
            <label class="form-label" for="popup_layer_admin_popup_layer_settings_default_target_detail_option"><?php echo sr_e('기본 노출위치'); ?> <span class="sr-required-label" data-admin-target-detail-required<?php echo sr_popup_layer_is_public_target_option($popupLayerDefaultTargetOption) ? ' hidden' : ''; ?>><?php echo sr_e('(필수)'); ?></span></label>
            <div class="form-field">
                <select id="popup_layer_admin_popup_layer_settings_default_target_detail_option" name="popup_layer_default_target_detail_option" class="form-select" data-admin-target-detail<?php echo sr_popup_layer_is_public_target_option($popupLayerDefaultTargetOption) ? ' disabled' : ' required'; ?>>
                    <?php foreach ($availableTargets as $target) { ?>
                        <?php $optionValue = sr_popup_layer_target_option_value($target); ?>
                        <option value="<?php echo sr_e($optionValue); ?>" data-service="<?php echo sr_e(sr_popup_layer_target_service_key($target)); ?>"<?php echo $popupLayerDefaultTargetOption === $optionValue ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_popup_layer_target_detail_label($target)); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="form-help"><?php echo sr_e(sr_t('popup_layer::settings.help.default_target_option.inline')); ?></p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('popup_layer_admin_popup_layer_settings_default_match_type', sr_t('popup_layer::settings.default_match_type'), $popupLayerSettingsHelp['default_match_type']['id'], $popupLayerHelpOpenLabel, true); ?>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('popup_layer_admin_popup_layer_settings_default_match_type', 'popup_layer_default_match_type', $popupLayerMatchTypeOptions, $popupLayerDefaultMatchType, true); ?>
                <p class="form-help"><?php echo sr_e(sr_t('popup_layer::settings.help.default_match_type.inline')); ?></p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('popup_layer_admin_popup_layer_settings_default_dismiss_cookie_days', sr_t('popup_layer::settings.default_dismiss_cookie_days'), $popupLayerSettingsHelp['default_dismiss_cookie_days']['id'], $popupLayerHelpOpenLabel); ?>
            <div class="form-field">
                <input id="popup_layer_admin_popup_layer_settings_default_dismiss_cookie_days" type="number" name="popup_layer_default_dismiss_cookie_days" value="<?php echo sr_e((string) $popupLayerDefaultDismissCookieDays); ?>" class="form-input" min="0" max="365">
                <p class="form-help"><?php echo sr_e(sr_t('popup_layer::settings.help.default_dismiss_cookie_days.inline')); ?></p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('popup_layer_admin_popup_layer_settings_editor', '본문 에디터', $popupLayerSettingsHelp['editor']['id'], $popupLayerHelpOpenLabel, true); ?>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('popup_layer_admin_popup_layer_settings_editor', 'popup_layer_editor', $popupLayerEditorOptions, $popupLayerEditorKey, true); ?>
                <p class="form-help">팝업 등록/수정 화면의 본문 입력에만 적용됩니다.</p>
            </div>
        </div>
    </section>
    <div class="form-sticky-actions form-actions form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('popup_layer::ui.list.f0aa41f6')); ?></a>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('popup_layer::ui.settings.save.2132c0ee')); ?></button>
    </div>
</form>

<?php foreach ($popupLayerSettingsHelp as $popupLayerHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $popupLayerHelpModal['id'], (string) $popupLayerHelpModal['title'], (string) $popupLayerHelpModal['body']); ?>
<?php } ?>

<script>
(function () {
    var form = document.querySelector('form.admin-form');
    if (!form) {
        return;
    }
    var publicTarget = <?php echo sr_js_json_encode(sr_popup_layer_public_target_option_value()); ?>;
    var service = form.querySelector('[data-admin-target-service]');
    var detail = form.querySelector('[data-admin-target-detail]');
    var targetOption = form.querySelector('[data-admin-target-option]');
    var detailRow = form.querySelector('[data-admin-target-detail-row]');
    var detailRequired = form.querySelector('[data-admin-target-detail-required]');
    var allMatch = form.querySelector('input[name="popup_layer_default_match_type"][value="all"]');

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
        if (isPublic && allMatch) {
            allMatch.checked = true;
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
