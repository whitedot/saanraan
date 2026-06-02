<?php

$adminPageTitle = sr_t('admin::ui.settings.4738c9b6');
$siteSettingsHelpOpenLabel = sr_t('admin::settings.help.open');
$siteSettingsHelpBodyHtml = static function (array $translationKeys): string {
    $html = '';
    foreach ($translationKeys as $translationKey) {
        $html .= '<p>' . sr_e(sr_t('admin::' . $translationKey)) . '</p>';
    }

    return $html;
};
$siteSettingsHelp = [
    'base_url' => [
        'id' => 'admin-settings-base-url-help-modal',
        'title' => sr_t('admin::settings.help.base_url.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.base_url.body.1',
            'settings.help.base_url.body.2',
        ]),
    ],
    'timezone' => [
        'id' => 'admin-settings-timezone-help-modal',
        'title' => sr_t('admin::settings.help.timezone.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.timezone.body.1',
            'settings.help.timezone.body.2',
        ]),
    ],
    'default_locale' => [
        'id' => 'admin-settings-default-locale-help-modal',
        'title' => sr_t('admin::settings.help.default_locale.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.default_locale.body.1',
            'settings.help.default_locale.body.2',
        ]),
    ],
    'supported_locales' => [
        'id' => 'admin-settings-supported-locales-help-modal',
        'title' => sr_t('admin::settings.help.supported_locales.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.supported_locales.body.1',
            'settings.help.supported_locales.body.2',
        ]),
    ],
    'status' => [
        'id' => 'admin-settings-status-help-modal',
        'title' => sr_t('admin::settings.help.status.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.status.body.1',
            'settings.help.status.body.2',
            'settings.help.status.body.3',
            'settings.help.status.body.4',
        ]),
    ],
    'public_layout' => [
        'id' => 'admin-settings-public-layout-help-modal',
        'title' => sr_t('admin::settings.help.public_layout.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.public_layout.body.1',
            'settings.help.public_layout.body.2',
        ]),
    ],
    'home_path' => [
        'id' => 'admin-settings-home-path-help-modal',
        'title' => sr_t('admin::settings.help.home_path.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.home_path.body.1',
            'settings.help.home_path.body.2',
        ]),
    ],
    'admin_color_scheme' => [
        'id' => 'admin-settings-admin-color-scheme-help-modal',
        'title' => sr_t('admin::settings.help.ui_color_scheme.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.ui_color_scheme.body.1',
            'settings.help.ui_color_scheme.body.2',
        ]),
    ],
    'admin_skin' => [
        'id' => 'admin-settings-admin-skin-help-modal',
        'title' => sr_t('admin::settings.help.admin_skin.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.admin_skin.body.1',
            'settings.help.admin_skin.body.2',
        ]),
    ],
    'list_pagination_per_page' => [
        'id' => 'admin-settings-list-pagination-per-page-help-modal',
        'title' => sr_t('admin::settings.help.list_pagination_per_page.title'),
        'body_html' => $siteSettingsHelpBodyHtml([
            'settings.help.list_pagination_per_page.body.1',
            'settings.help.list_pagination_per_page.body.2',
        ]),
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if (!$currentHomepageAvailable) { ?>
    <div class="admin-notice">
        <span class="admin-notice-icon">!</span>
        <div class="admin-notice-copy">
            <strong><?php echo sr_e(sr_t('admin::ui.save.active.794b5bfc')); ?></strong>
            <p><?php echo sr_e(sr_t('admin::ui.page.active.select.save.8a7dbcc6')); ?></p>
        </div>
    </div>
<?php } ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="site">
    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('admin::ui.text.f6fc85bc')); ?></h2>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_name"><?php echo sr_e(sr_t('admin::ui.name.51f4c6af')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
            <div class="admin-form-field">
                <input id="admin_settings_name" type="text" name="name" value="<?php echo sr_e($values['name']); ?>" class="form-input" maxlength="120" required>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label admin-form-label-help">
                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="<?php echo sr_e(sr_t('admin::ui.url.09f44187') . ' ' . $siteSettingsHelpOpenLabel); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($siteSettingsHelp['base_url']['id']); ?>" data-overlay="#<?php echo sr_e($siteSettingsHelp['base_url']['id']); ?>">
                    <?php echo sr_material_icon_html('help'); ?>
                </button>
                <span><?php echo sr_e(sr_t('admin::ui.url.09f44187')); ?></span>
            </span>
            <div class="admin-form-field">
                <?php if ($values['base_url'] !== '') { ?>
                    <code><?php echo sr_e($values['base_url']); ?></code>
                <?php } else { ?>
                    <span><?php echo sr_e(sr_t('admin::ui.settings.9182f8fe')); ?></span>
                <?php } ?>
                <p class="admin-form-help"><?php echo sr_e(sr_t('admin::ui.search.active.admin.settings.7aedc357')); ?></p>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_timezone', sr_t('admin::ui.text.26e997a5'), $siteSettingsHelp['timezone']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <select id="admin_settings_timezone" name="timezone" class="form-select" required>
                    <?php foreach ($timezoneOptions as $timezoneOption) { ?>
                        <option value="<?php echo sr_e($timezoneOption); ?>"<?php echo $values['timezone'] === $timezoneOption ? ' selected' : ''; ?>>
                            <?php echo sr_e($timezoneOption); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_default_locale', sr_t('admin::ui.locale.c7cd39b4'), $siteSettingsHelp['default_locale']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <select id="admin_settings_default_locale" name="default_locale" class="form-select" required>
                    <?php foreach ($localeOptions as $localeOption) { ?>
                        <option value="<?php echo sr_e($localeOption); ?>"<?php echo $values['default_locale'] === $localeOption ? ' selected' : ''; ?>>
                            <?php echo sr_e($localeOption); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_supported_locales', sr_t('admin::ui.locale.list.51d8e798'), $siteSettingsHelp['supported_locales']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <?php $selectedSupportedLocales = sr_supported_locales($values); ?>
                <div data-admin-required-checkbox-group data-admin-required-checkbox-message="지원 locale 목록은 최소 한 개 이상 선택하세요.">
                    <?php echo sr_admin_checkbox_list_html('admin_settings_supported_locales', 'supported_locales', array_combine($localeOptions, $localeOptions) ?: [], $selectedSupportedLocales, sr_t('admin::ui.locale.9d745a6e')); ?>
                </div>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_status', sr_t('admin::ui.status.e4163930'), $siteSettingsHelp['status']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <select id="admin_settings_status" name="status" class="form-select">
                                    <option value="active"<?php echo $values['status'] === 'active' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('admin::ui.text.0928a1b8')); ?></option>
                                    <option value="maintenance"<?php echo $values['status'] === 'maintenance' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('admin::ui.text.4fd02e48')); ?></option>
                                </select>
            </div>
        </div>
    </section>
    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('admin::ui.text.b5361f64')); ?></h2>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_public_layout_key', sr_t('admin::ui.text.974e65f4'), $siteSettingsHelp['public_layout']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <select id="admin_settings_public_layout_key" name="public_layout_key" class="form-select">
                                    <?php foreach (sr_public_layout_options($pdo) as $layoutKey => $layoutOption) { ?>
                                        <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo $values['public_layout_key'] === (string) $layoutKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                                        </option>
                                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_home_path', sr_t('admin::ui.text.214b5fb8'), $siteSettingsHelp['home_path']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <select id="admin_settings_home_path" name="home_path" class="form-select form-control-full">
                    <?php foreach ($homepageCandidates as $candidate) { ?>
                        <?php $candidatePath = (string) ($candidate['path'] ?? ''); ?>
                        <?php $candidateSelected = (string) ($values['home_path'] ?? '/') === $candidatePath; ?>
                        <option value="<?php echo sr_e($candidatePath); ?>"<?php echo $candidateSelected ? ' selected' : ''; ?><?php echo empty($candidate['available']) && !$candidateSelected ? ' disabled' : ''; ?>>
                            <?php echo sr_e((string) ($candidate['label'] ?? $candidatePath)); ?>
                            <?php echo $candidatePath !== '/' ? ' - ' . sr_e($candidatePath) : ''; ?>
                            <?php echo empty($candidate['available']) ? sr_t('admin::ui.active.6e2fcb45') : ''; ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="admin-form-help"><?php echo sr_e(sr_t('admin::ui.page.page.community.status.active.ee1178b4')); ?></p>
            </div>
        </div>
    </section>
    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('admin::settings.section.admin_screen')); ?></h2>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_admin_color_scheme', sr_t('admin::ui.ui.cf6c41c6'), $siteSettingsHelp['admin_color_scheme']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <select id="admin_settings_admin_color_scheme" name="admin_color_scheme" class="form-select" data-admin-color-scheme-select>
                    <?php foreach (sr_color_scheme_options() as $colorScheme => $colorSchemeLabel) { ?>
                        <option value="<?php echo sr_e((string) $colorScheme); ?>"<?php echo $values['admin_color_scheme'] === (string) $colorScheme ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $colorSchemeLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_admin_skin_key', sr_t('admin::ui.admin.1465c5b7'), $siteSettingsHelp['admin_skin']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <select id="admin_settings_admin_skin_key" name="admin_skin_key" class="form-select">
                    <?php foreach ($adminSkinOptions as $skinKey => $skinOption) { ?>
                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $adminSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('admin_settings_list_pagination_per_page', '페이징 기본수', $siteSettingsHelp['list_pagination_per_page']['id'], $siteSettingsHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <div class="input-group admin-input-unit">
                    <input id="admin_settings_list_pagination_per_page" type="number" name="list_pagination_per_page" value="<?php echo sr_e((string) $values['list_pagination_per_page']); ?>" class="form-input" min="10" max="500" required>
                    <span class="input-group-text">개</span>
                </div>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_admin_editor">관리자 화면 에디터 <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
            <div class="admin-form-field">
                <select id="admin_settings_admin_editor" name="admin_editor" class="form-select" required>
                    <?php foreach ($adminEditorOptions as $editorKey => $editorLabel) { ?>
                        <option value="<?php echo sr_e((string) $editorKey); ?>"<?php echo (string) ($values['admin_editor'] ?? 'textarea') === (string) $editorKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $editorLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="admin-form-help">알림 등록처럼 관리자에서 긴 본문을 작성하는 화면에 적용됩니다.</p>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <a href="<?php echo sr_e(sr_url('/')); ?>" class="btn btn-solid-light" target="_blank" rel="noopener noreferrer"><?php echo sr_e(sr_t('admin::ui.text.b47e1675')); ?></a>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('admin::ui.save.5fb92622')); ?></button>
    </div>
</form>

<script>
(function () {
    function updateRequiredCheckboxGroup(root) {
        var checkboxes = Array.prototype.slice.call(root.querySelectorAll('input[type="checkbox"]'));
        if (checkboxes.length === 0) {
            return;
        }

        var checked = checkboxes.some(function (checkbox) {
            return checkbox.checked;
        });
        var message = root.getAttribute('data-admin-required-checkbox-message') || '최소 한 개 이상 선택하세요.';
        checkboxes[0].setCustomValidity(checked ? '' : message);
    }

    document.querySelectorAll('[data-admin-required-checkbox-group]').forEach(function (root) {
        updateRequiredCheckboxGroup(root);
        root.addEventListener('change', function () {
            updateRequiredCheckboxGroup(root);
        });
    });
}());
</script>

<?php foreach ($siteSettingsHelp as $siteSettingsHelpModal) { ?>
    <?php echo sr_admin_help_modal_html($siteSettingsHelpModal['id'], $siteSettingsHelpModal['title'], $siteSettingsHelpModal['body_html']); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
