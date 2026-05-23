<?php

$sessionErrors = $_SESSION['sr_content_admin_errors'] ?? [];
$sessionValues = $_SESSION['sr_content_admin_values'] ?? [];
unset($_SESSION['sr_content_admin_errors'], $_SESSION['sr_content_admin_values']);
if (is_array($sessionErrors)) {
    $errors = array_merge($errors, array_map('strval', $sessionErrors));
}
if (is_array($sessionValues)) {
    $values = $sessionValues;
}
$editing = is_array($editPage);
if ($values === []) {
    $values = $editing ? $editPage : [
        'title' => '',
        'content_group_scope' => 'here_only',
        'content_group_id' => 0,
        'slug' => '',
        'summary' => '',
        'body_text' => '',
        'status' => 'draft',
        'layout_key' => sr_public_layout_key($site ?? null, $pdo ?? null),
        'asset_access_enabled' => 0,
        'asset_module' => 'point',
        'asset_access_amount' => 0,
        'asset_charge_policy' => 'once',
        'asset_action_enabled' => 0,
        'asset_action_module' => 'point',
        'asset_action_amount' => 0,
        'asset_action_direction' => 'grant',
        'asset_action_label' => sr_t('content::ui.text.727333ab'),
        'banner_before_content_id' => 0,
        'banner_after_content_id' => 0,
        'popup_layer_id' => 0,
        'seo_title' => '',
        'seo_description' => '',
    ];
}

$adminPageTitle = $pageAdminPage === 'form' ? ($editing ? sr_t('content::ui.content.edit.9fdd9b62') : sr_t('content::ui.content.62a2bf90')) : sr_t('content::ui.content.6c84a1b3');
$adminPageSubtitle = $pageAdminPage === 'form' ? sr_t('content::ui.content.status.85bf8a35') : sr_t('content::ui.content.status.search.29f7335b');
$adminContainerClass = $pageAdminPage === 'form' ? 'admin-content-form admin-ui-scope' : 'admin-content-list admin-ui-scope';
$filters = isset($filters) && is_array($filters) ? $filters : ['status' => '', 'content_group_id' => 0, 'field' => 'all', 'q' => ''];
$pageStatusCounts = isset($pageStatusCounts) && is_array($pageStatusCounts) ? $pageStatusCounts : [];
$pageGroups = isset($pageGroups) && is_array($pageGroups) ? $pageGroups : [];
$publicLayoutOptions = isset($publicLayoutOptions) && is_array($publicLayoutOptions) ? $publicLayoutOptions : sr_public_layout_options($pdo ?? null);
$assetModuleChoiceOptions = [];
foreach ($assetModuleOptions as $assetModule => $assetOption) {
    $assetModuleChoiceOptions[(string) $assetModule] = (string) ($assetOption['label'] ?? $assetModule);
}
$assetDeductionPriorityLabels = [];
foreach (sr_content_asset_deduction_order() as $assetModule) {
    if (isset($assetModuleChoiceOptions[$assetModule])) {
        $assetDeductionPriorityLabels[] = $assetModuleChoiceOptions[$assetModule];
    }
}
$assetDeductionPriorityHelp = $assetDeductionPriorityLabels !== []
    ? sr_t('content::ui.text.706623d8') . implode(' > ', $assetDeductionPriorityLabels)
    : sr_t('content::ui.text.3e195cdd');
$pageGroupScopeLabels = [
    'group' => ['visible' => sr_t('content::ui.text.5d908ddd'), 'sr' => sr_t('content::ui.text.6a1c963d')],
    'all' => ['visible' => sr_t('content::ui.all.a4b69faf'), 'sr' => sr_t('content::ui.text.6a1c963d')],
    'here_only' => ['visible' => sr_t('content::ui.text.c0e39cdd'), 'sr' => sr_t('content::ui.text.6a1c963d')],
];
$pageScopeLabelHtml = static function (array $label): string {
    return sr_e((string) ($label['visible'] ?? '')) . '<span class="sr-only">' . sr_e((string) ($label['sr'] ?? '')) . '</span>';
};
$pageGroupScopeRadioHtml = static function (string $name, string $selectedScope) use ($pageGroupScopeLabels, $pageScopeLabelHtml): string {
    $selectedScope = array_key_exists($selectedScope, $pageGroupScopeLabels) ? $selectedScope : 'here_only';
    $html = sr_t('content::ui.div.class.admin.setting.source.01280cd8');
    foreach ($pageGroupScopeLabels as $scope => $label) {
        $id = 'content_group_scope_' . $scope;
        $html .= '<label class="admin-form-check form-label" for="' . sr_e($id) . '">';
        $html .= '<input id="' . sr_e($id) . '" type="radio" name="' . sr_e($name) . '" value="' . sr_e($scope) . '" class="form-radio" data-content-group-scope-option' . ($selectedScope === $scope ? ' checked' : '') . '>';
        $html .= $pageScopeLabelHtml($label);
        $html .= '</label>';
    }

    return $html . '</div>';
};
$pageSettingSourceLabels = [
    'group' => $pageGroupScopeLabels['group'],
    'all' => $pageGroupScopeLabels['all'],
    'content' => $pageGroupScopeLabels['here_only'],
];
$pageSettingSource = static function (array $values, string $key): string {
    if (array_key_exists('source_' . $key, $values)) {
        return sr_content_normalize_setting_source((string) $values['source_' . $key]);
    }

    $sources = is_array($values['setting_sources'] ?? null) ? $values['setting_sources'] : [];
    return sr_content_normalize_setting_source((string) ($sources[$key] ?? 'content'));
};
$pageSettingSourceRadioHtml = static function (string $name, string $selectedSource) use ($pageSettingSourceLabels, $pageScopeLabelHtml): string {
    $selectedSource = array_key_exists($selectedSource, $pageSettingSourceLabels) ? $selectedSource : 'content';
    $baseId = preg_replace('/[^a-zA-Z0-9_]+/', '_', $name);
    $html = sr_t('content::ui.div.class.admin.setting.source.67eda3ac');
    foreach ($pageSettingSourceLabels as $source => $label) {
        $id = 'content_setting_source_' . $baseId . '_' . $source;
        $html .= '<label class="admin-form-check form-label" for="' . sr_e($id) . '">';
        $html .= '<input id="' . sr_e($id) . '" type="radio" name="' . sr_e($name) . '" value="' . sr_e($source) . '" class="form-radio"' . ($selectedSource === $source ? ' checked' : '') . '>';
        $html .= $pageScopeLabelHtml($label);
        $html .= '</label>';
    }

    return $html . '</div>';
};
$values['content_group_scope'] = sr_content_group_apply_scope((string) ($values['content_group_scope'] ?? ((int) ($values['content_group_id'] ?? 0) > 0 ? 'group' : 'here_only')));
$legacyAssetPolicySource = sr_content_normalize_setting_source((string) ($values['asset_policy_source'] ?? 'content'));
foreach (sr_content_group_asset_access_setting_keys() as $settingKey) {
    $values['source_' . $settingKey] = $pageSettingSource($values, (string) $settingKey);
    if ($values['source_' . $settingKey] === 'content' && isset($values['asset_access_policy_source'])) {
        $values['source_' . $settingKey] = sr_content_normalize_setting_source((string) $values['asset_access_policy_source']);
    } elseif ($values['source_' . $settingKey] === 'content' && $legacyAssetPolicySource !== 'content') {
        $values['source_' . $settingKey] = $legacyAssetPolicySource;
    }
}
foreach (sr_content_group_asset_action_setting_keys() as $settingKey) {
    $values['source_' . $settingKey] = $pageSettingSource($values, (string) $settingKey);
    if ($values['source_' . $settingKey] === 'content' && isset($values['asset_action_policy_source'])) {
        $values['source_' . $settingKey] = sr_content_normalize_setting_source((string) $values['asset_action_policy_source']);
    } elseif ($values['source_' . $settingKey] === 'content' && $legacyAssetPolicySource !== 'content') {
        $values['source_' . $settingKey] = $legacyAssetPolicySource;
    }
}
foreach (sr_content_group_file_asset_setting_keys() as $settingKey) {
    $values['source_' . $settingKey] = $pageSettingSource($values, (string) $settingKey);
    if ($values['source_' . $settingKey] === 'content' && isset($values['file_asset_policy_source'])) {
        $values['source_' . $settingKey] = sr_content_normalize_setting_source((string) $values['file_asset_policy_source']);
    }
}
$values['layout_key'] = sr_public_layout_normalize_key((string) ($values['layout_key'] ?? ''));
if ($values['layout_key'] === '' || !isset($publicLayoutOptions[$values['layout_key']])) {
    $values['layout_key'] = sr_public_layout_key($site ?? null, $pdo ?? null);
}
$totalPages = (int) ($pageStatusCounts['total'] ?? count($pages ?? []));
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($pageAdminPage === 'form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/content/save')); ?>" class="admin-form ui-form-theme" enctype="multipart/form-data">
        <section class="admin-card card">
            <h2><?php echo $editing ? sr_t('content::ui.content.edit.9fdd9b62') : sr_t('content::ui.content.62a2bf90'); ?></h2>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="content_id" value="<?php echo $editing ? sr_e((string) $editPage['id']) : '0'; ?>">
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_title"><?php echo sr_e(sr_t('content::ui.text.08b17e43')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('content::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="content_admin_contents_title" type="text" name="title" value="<?php echo sr_e((string) ($values['title'] ?? '')); ?>" class="form-input form-control-full" maxlength="160" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_slug">Slug <span class="sr-required-label"><?php echo sr_e(sr_t('content::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="content_admin_contents_slug" type="text" name="slug" value="<?php echo sr_e((string) ($values['slug'] ?? '')); ?>" class="form-input form-control-full" maxlength="120" required>
                    <br>
                                        <small><?php echo sr_e(sr_t('content::ui.content.slug.active.359891c0')); ?></small>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_content_group_id"><?php echo sr_e(sr_t('content::ui.content.5875c5b3')); ?> <span class="sr-required-label" data-content-group-required hidden><?php echo sr_e(sr_t('content::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <select id="content_admin_contents_content_group_id" name="content_group_id" class="form-select" data-content-group-select>
                        <option value="0"<?php echo (int) ($values['content_group_id'] ?? 0) === 0 ? ' selected' : ''; ?>><?php echo sr_e(sr_t('content::ui.text.d435d292')); ?></option>
                        <?php foreach ($pageGroups as $pageGroup) { ?>
                            <option value="<?php echo sr_e((string) $pageGroup['id']); ?>"<?php echo (int) ($values['content_group_id'] ?? 0) === (int) $pageGroup['id'] ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) ($pageGroup['title'] ?? $pageGroup['group_key'])); ?>
                                <?php if ((string) ($pageGroup['status'] ?? '') !== 'enabled') { ?>
                                    (<?php echo sr_e(sr_admin_code_label((string) $pageGroup['status'], 'content_status')); ?>)
                                <?php } ?>
                            </option>
                        <?php } ?>
                    </select>
                    <?php echo $pageGroupScopeRadioHtml('content_group_scope', (string) ($values['content_group_scope'] ?? 'here_only')); ?>
                    <p class="admin-form-help"><?php echo sr_e(sr_t('content::ui.select.list.menu.10a1aa2a')); ?></p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_summary"><?php echo sr_e(sr_t('content::ui.text.50f30154')); ?></label>
                <div class="admin-form-field">
                    <textarea id="content_admin_contents_summary" name="summary" maxlength="1000" class="form-textarea"><?php echo sr_e((string) ($values['summary'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_body_text"><?php echo sr_e(sr_t('content::ui.text.9118bb57')); ?></label>
                <div class="admin-form-field">
                    <textarea id="content_admin_contents_body_text" name="body_text" rows="14" class="form-textarea"><?php echo sr_e((string) ($values['body_text'] ?? '')); ?></textarea>
                    <br>
                                        <small><?php echo sr_e(sr_t('content::ui.content.plain.save.723dab58')); ?></small>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_seo_title"><?php echo sr_e(sr_t('content::ui.seo.f66e126a')); ?></label>
                <div class="admin-form-field">
                    <input id="content_admin_contents_seo_title" type="text" name="seo_title" value="<?php echo sr_e((string) ($values['seo_title'] ?? '')); ?>" class="form-input form-control-full" maxlength="160">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_seo_description"><?php echo sr_e(sr_t('content::ui.seo.b6187d8d')); ?></label>
                <div class="admin-form-field">
                    <input id="content_admin_contents_seo_description" type="text" name="seo_description" value="<?php echo sr_e((string) ($values['seo_description'] ?? '')); ?>" class="form-input form-control-full" maxlength="255">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_status"><?php echo sr_e(sr_t('content::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('content::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <select id="content_admin_contents_status" name="status" class="form-select">
                                                <?php foreach (sr_content_allowed_statuses() as $status) { ?>
                                                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($values['status'] ?? 'draft') === $status ? ' selected' : ''; ?>>
                                                        <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                    <?php echo $pageSettingSourceRadioHtml('source_status', $pageSettingSource($values, 'status')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_layout_key"><?php echo sr_e(sr_t('content::ui.content.fa985852')); ?></label>
                <div class="admin-form-field">
                    <select id="content_admin_contents_layout_key" name="layout_key" class="form-select">
                                                <?php foreach ($publicLayoutOptions as $layoutKey => $layoutOption) { ?>
                                                    <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo (string) ($values['layout_key'] ?? '') === (string) $layoutKey ? ' selected' : ''; ?>>
                                                        <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                    <?php echo $pageSettingSourceRadioHtml('source_layout_key', $pageSettingSource($values, 'layout_key')); ?>
                    <p class="admin-form-help"><?php echo sr_e(sr_t('content::ui.content.05b39bf1')); ?></p>
                </div>
            </div>
        </section>
        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('content::ui.text.c9b3e6f0')); ?></h2>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('content::ui.active.923da40e')); ?></span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_content_admin_contents_asset_access_enabled">
                                            <input id="modules_content_admin_contents_asset_access_enabled" type="checkbox" name="asset_access_enabled" value="1" class="form-checkbox"<?php echo (int) ($values['asset_access_enabled'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('content::ui.active.923da40e')); ?>
                                        </label>
                                        <?php echo $pageSettingSourceRadioHtml('source_asset_access_enabled', $pageSettingSource($values, 'asset_access_enabled')); ?>
                                        <p class="admin-form-help"><?php echo sr_e(sr_t('content::ui.select.member.content.42c8795b')); ?></p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_asset_module"><?php echo sr_e(sr_t('content::ui.text.7d96defe')); ?></label>
                <div class="admin-form-field">
                    <?php $selectedAccessAssetModules = sr_content_asset_module_keys_from_value($values['asset_module'] ?? 'point'); ?>
                    <?php echo sr_admin_checkbox_list_html('content_admin_contents_asset_module', 'asset_module', $assetModuleChoiceOptions, $selectedAccessAssetModules, sr_t('content::ui.text.3e195cdd')); ?>
                    <?php echo $pageSettingSourceRadioHtml('source_asset_module', $pageSettingSource($values, 'asset_module')); ?>
                    <p class="admin-form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_asset_access_amount"><?php echo sr_e(sr_t('content::ui.text.a9f15a8b')); ?></label>
                <div class="admin-form-field">
                    <input id="content_admin_contents_asset_access_amount" type="number" name="asset_access_amount" value="<?php echo sr_e((string) (int) ($values['asset_access_amount'] ?? 0)); ?>" class="form-input" min="0" max="999999999" step="1">
                    <?php echo $pageSettingSourceRadioHtml('source_asset_access_amount', $pageSettingSource($values, 'asset_access_amount')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_asset_charge_policy"><?php echo sr_e(sr_t('content::ui.text.86803f52')); ?></label>
                <div class="admin-form-field">
                    <select id="content_admin_contents_asset_charge_policy" name="asset_charge_policy" class="form-select">
                                                <?php foreach (sr_content_asset_view_charge_policies() as $policyKey => $policyLabel) { ?>
                                                    <option value="<?php echo sr_e((string) $policyKey); ?>"<?php echo (string) ($values['asset_charge_policy'] ?? 'once') === (string) $policyKey ? ' selected' : ''; ?>>
                                                        <?php echo sr_e((string) $policyLabel); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                    <?php echo $pageSettingSourceRadioHtml('source_asset_charge_policy', $pageSettingSource($values, 'asset_charge_policy')); ?>
                </div>
            </div>
        </section>
        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('content::ui.text.76faa117')); ?></h2>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('content::ui.active.8bcecbe7')); ?></span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_content_admin_contents_asset_action_enabled">
                                            <input id="modules_content_admin_contents_asset_action_enabled" type="checkbox" name="asset_action_enabled" value="1" class="form-checkbox"<?php echo (int) ($values['asset_action_enabled'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('content::ui.active.904d506b')); ?>
                                        </label>
                                        <?php echo $pageSettingSourceRadioHtml('source_asset_action_enabled', $pageSettingSource($values, 'asset_action_enabled')); ?>
                                        <p class="admin-form-help"><?php echo sr_e(sr_t('content::ui.member.content.select.02996bc9')); ?></p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_asset_action_label"><?php echo sr_e(sr_t('content::ui.text.98fb4605')); ?></label>
                <div class="admin-form-field">
                    <input id="content_admin_contents_asset_action_label" type="text" name="asset_action_label" value="<?php echo sr_e((string) ($values['asset_action_label'] ?? sr_t('content::ui.text.727333ab'))); ?>" class="form-input" maxlength="80">
                    <?php echo $pageSettingSourceRadioHtml('source_asset_action_label', $pageSettingSource($values, 'asset_action_label')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_asset_action_direction"><?php echo sr_e(sr_t('content::ui.text.af7873a8')); ?></label>
                <div class="admin-form-field">
                    <select id="content_admin_contents_asset_action_direction" name="asset_action_direction" class="form-select">
                                                <?php foreach (sr_content_asset_action_directions() as $directionKey => $directionLabel) { ?>
                                                    <option value="<?php echo sr_e((string) $directionKey); ?>"<?php echo (string) ($values['asset_action_direction'] ?? 'grant') === (string) $directionKey ? ' selected' : ''; ?>>
                                                        <?php echo sr_e((string) $directionLabel); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                    <?php echo $pageSettingSourceRadioHtml('source_asset_action_direction', $pageSettingSource($values, 'asset_action_direction')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_asset_action_module"><?php echo sr_e(sr_t('content::ui.text.2f2b6193')); ?></label>
                <div class="admin-form-field">
                    <?php $selectedActionAssetModules = sr_content_asset_module_keys_from_value($values['asset_action_module'] ?? 'point'); ?>
                    <?php echo sr_admin_checkbox_list_html('content_admin_contents_asset_action_module', 'asset_action_module', $assetModuleChoiceOptions, $selectedActionAssetModules, sr_t('content::ui.text.3e195cdd')); ?>
                    <?php echo $pageSettingSourceRadioHtml('source_asset_action_module', $pageSettingSource($values, 'asset_action_module')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_asset_action_amount"><?php echo sr_e(sr_t('content::ui.text.5c705e1a')); ?></label>
                <div class="admin-form-field">
                    <input id="content_admin_contents_asset_action_amount" type="number" name="asset_action_amount" value="<?php echo sr_e((string) (int) ($values['asset_action_amount'] ?? 0)); ?>" class="form-input" min="0" max="999999999" step="1">
                    <?php echo $pageSettingSourceRadioHtml('source_asset_action_amount', $pageSettingSource($values, 'asset_action_amount')); ?>
                </div>
            </div>
        </section>
        <section class="admin-card card">
            <h2>
                <span><?php echo sr_e(sr_t('content::ui.text.a052b2f6')); ?></span>
                <span class="admin-form-actions">
                    <?php if (sr_module_enabled($pdo, 'banner')) { ?>
                        <a href="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('content::ui.banner.42c18eb4')); ?></a>
                    <?php } ?>
                    <?php if (sr_module_enabled($pdo, 'popup_layer')) { ?>
                        <a href="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('content::ui.text.f789aad9')); ?></a>
                    <?php } ?>
                </span>
            </h2>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_banner_before_content_id"><?php echo sr_e(sr_t('content::ui.banner.042ab3f3')); ?></label>
                <div class="admin-form-field">
                    <div class="admin-setting-source-line">
                        <select id="content_admin_contents_banner_before_content_id" name="banner_before_content_id" class="form-select form-control-full">
                            <option value="0"<?php echo (int) ($values['banner_before_content_id'] ?? 0) === 0 ? ' selected' : ''; ?>><?php echo sr_e(sr_t('content::ui.active.4add3230')); ?></option>
                            <?php foreach ($publicBanners as $banner) { ?>
                                <option value="<?php echo sr_e((string) $banner['id']); ?>"<?php echo (int) ($values['banner_before_content_id'] ?? 0) === (int) $banner['id'] ? ' selected' : ''; ?>>
                                    <?php echo sr_e((string) $banner['title']); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <?php echo $pageSettingSourceRadioHtml('source_banner_before_content_id', $pageSettingSource($values, 'banner_before_content_id')); ?>
                    </div>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_banner_after_content_id"><?php echo sr_e(sr_t('content::ui.banner.5818427a')); ?></label>
                <div class="admin-form-field">
                    <div class="admin-setting-source-line">
                        <select id="content_admin_contents_banner_after_content_id" name="banner_after_content_id" class="form-select form-control-full">
                            <option value="0"<?php echo (int) ($values['banner_after_content_id'] ?? 0) === 0 ? ' selected' : ''; ?>><?php echo sr_e(sr_t('content::ui.active.4add3230')); ?></option>
                            <?php foreach ($publicBanners as $banner) { ?>
                                <option value="<?php echo sr_e((string) $banner['id']); ?>"<?php echo (int) ($values['banner_after_content_id'] ?? 0) === (int) $banner['id'] ? ' selected' : ''; ?>>
                                    <?php echo sr_e((string) $banner['title']); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <?php echo $pageSettingSourceRadioHtml('source_banner_after_content_id', $pageSettingSource($values, 'banner_after_content_id')); ?>
                    </div>
                    <small class="admin-form-help"><?php echo sr_e(sr_t('content::ui.banner.select.banner.settings.f34a92f2')); ?></small>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_popup_layer_id"><?php echo sr_e(sr_t('content::ui.text.1063d585')); ?></label>
                <div class="admin-form-field">
                    <div class="admin-setting-source-line">
                        <select id="content_admin_contents_popup_layer_id" name="popup_layer_id" class="form-select form-control-full">
                            <option value="0"<?php echo (int) ($values['popup_layer_id'] ?? 0) === 0 ? ' selected' : ''; ?>><?php echo sr_e(sr_t('content::ui.active.4add3230')); ?></option>
                            <?php foreach ($publicPopupLayers as $popupLayer) { ?>
                                <option value="<?php echo sr_e((string) $popupLayer['id']); ?>"<?php echo (int) ($values['popup_layer_id'] ?? 0) === (int) $popupLayer['id'] ? ' selected' : ''; ?>>
                                    <?php echo sr_e((string) $popupLayer['title']); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <?php echo $pageSettingSourceRadioHtml('source_popup_layer_id', $pageSettingSource($values, 'popup_layer_id')); ?>
                    </div>
                    <small class="admin-form-help"><?php echo sr_e(sr_t('content::ui.select.content.all.settings.bed25394')); ?></small>
                </div>
            </div>
            <?php if ($editing) { ?>
                <div class="admin-form-row">
                    <span class="form-label"><?php echo sr_e(sr_t('content::ui.url.644c2e7a')); ?></span>
                    <div class="admin-form-field">
                        <a href="<?php echo sr_e(sr_url(sr_content_path((string) $editPage['slug']))); ?>" target="_blank" rel="noopener noreferrer"><?php echo sr_e(sr_content_path((string) $editPage['slug'])); ?></a>
                    </div>
                </div>
            <?php } ?>
        </section>
        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('content::ui.text.c7c88adc')); ?></h2>
            <?php if ($editing && $contentFiles !== []) { ?>
                <div class="table-wrapper">
                    <table class="table">
                        <thead class="ui-table-head">
                            <tr>
                                <th><?php echo sr_e(sr_t('content::ui.text.0c8354d0')); ?></th>
                                <th><?php echo sr_e(sr_t('content::ui.text.d07eab27')); ?></th>
                                <th><?php echo sr_e(sr_t('content::ui.delete.6139b6c3')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contentFiles as $contentFile) { ?>
                                <?php $fileId = (int) $contentFile['id']; ?>
                                <?php $contentFileTitleId = 'content_file_title_' . (string) $fileId; ?>
                                <?php $contentFileChargeEnabledId = 'content_file_asset_download_enabled_' . (string) $fileId; ?>
                                <?php $contentFileAssetModuleId = 'content_file_asset_module_' . (string) $fileId; ?>
                                <?php $contentFileAmountId = 'content_file_asset_download_amount_' . (string) $fileId; ?>
                                <?php $contentFileChargePolicyId = 'content_file_asset_charge_policy_' . (string) $fileId; ?>
                                <?php $contentFileDeleteId = 'content_file_delete_' . (string) $fileId; ?>
                                <tr>
                                    <td>
                                        <input type="hidden" name="content_file_ids[]" value="<?php echo sr_e((string) $fileId); ?>">
                                        <label for="<?php echo sr_e($contentFileTitleId); ?>">
                                            <span class="sr-only"><?php echo sr_e(sr_t('content::ui.text.c6713aae')); ?></span>
                                            <input id="<?php echo sr_e($contentFileTitleId); ?>" type="text" name="content_file_title[<?php echo sr_e((string) $fileId); ?>]" value="<?php echo sr_e((string) $contentFile['title']); ?>" class="form-input form-control-full" maxlength="160">
                                        </label>
                                        <br>
                                        <small><?php echo sr_e((string) $contentFile['original_name']); ?> · <?php echo sr_e(sr_content_format_bytes((int) $contentFile['size_bytes'])); ?></small>
                                    </td>
                                    <td>
                                        <label class="admin-form-check form-label" for="<?php echo sr_e($contentFileChargeEnabledId); ?>">
                                            <input id="<?php echo sr_e($contentFileChargeEnabledId); ?>" type="checkbox" name="content_file_asset_download_enabled[<?php echo sr_e((string) $fileId); ?>]" value="1" class="form-checkbox"<?php echo (int) ($contentFile['asset_download_enabled'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('content::ui.text.31833f06')); ?>
                                        </label>
                                        <span class="sr-only"><?php echo sr_e(sr_t('content::ui.text.30430e12')); ?></span>
                                        <?php $selectedFileAssetModules = sr_content_asset_module_keys_from_value($contentFile['asset_module'] ?? 'point'); ?>
                                        <?php echo sr_admin_checkbox_list_html($contentFileAssetModuleId, 'content_file_asset_module[' . (string) $fileId . ']', $assetModuleChoiceOptions, $selectedFileAssetModules, sr_t('content::ui.text.3e195cdd')); ?>
                                        <label for="<?php echo sr_e($contentFileAmountId); ?>">
                                            <span class="sr-only"><?php echo sr_e(sr_t('content::ui.text.c871de35')); ?></span>
                                            <input id="<?php echo sr_e($contentFileAmountId); ?>" type="number" name="content_file_asset_download_amount[<?php echo sr_e((string) $fileId); ?>]" value="<?php echo sr_e((string) (int) ($contentFile['asset_download_amount'] ?? 0)); ?>" class="form-input" min="0" max="999999999" step="1">
                                        </label>
                                        <label for="<?php echo sr_e($contentFileChargePolicyId); ?>">
                                            <span class="sr-only"><?php echo sr_e(sr_t('content::ui.text.51a83be4')); ?></span>
                                            <select id="<?php echo sr_e($contentFileChargePolicyId); ?>" name="content_file_asset_charge_policy[<?php echo sr_e((string) $fileId); ?>]" class="form-select">
                                                <?php foreach (sr_content_asset_download_charge_policies() as $policyKey => $policyLabel) { ?>
                                                    <option value="<?php echo sr_e((string) $policyKey); ?>"<?php echo (string) ($contentFile['asset_charge_policy'] ?? 'once') === (string) $policyKey ? ' selected' : ''; ?>>
                                                        <?php echo sr_e((string) $policyLabel); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </label>
                                    </td>
                                    <td>
                                        <label class="admin-form-check form-label" for="<?php echo sr_e($contentFileDeleteId); ?>">
                                            <input id="<?php echo sr_e($contentFileDeleteId); ?>" type="checkbox" name="content_file_delete[<?php echo sr_e((string) $fileId); ?>]" value="1" class="form-checkbox">
                                            <?php echo sr_admin_choice_label_html(sr_t('content::ui.delete.6139b6c3')); ?>
                                        </label>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } elseif ($editing) { ?>
                <p><?php echo sr_e(sr_t('content::ui.create.c0af4d1f')); ?></p>
            <?php } else { ?>
                <p><?php echo sr_e(sr_t('content::ui.content.save.136c7ad6')); ?></p>
            <?php } ?>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_content_file_upload"><?php echo sr_e(sr_t('content::ui.text.45a992ee')); ?></label>
                <div class="admin-form-field">
                    <input id="content_admin_contents_content_file_upload" type="file" name="content_file_upload" class="form-input">
                    <br>
                                        <small><?php echo sr_e(sr_t('content::ui.pdf.cf7633ac')); ?> <?php echo sr_e(sr_content_format_bytes(sr_content_file_upload_max_bytes())); ?></small>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_new_content_file_title"><?php echo sr_e(sr_t('content::ui.text.8d3d9268')); ?></label>
                <div class="admin-form-field">
                    <input id="content_admin_contents_new_content_file_title" type="text" name="new_content_file_title" value="" class="form-input form-control-full" maxlength="160">
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('content::ui.text.b065b16b')); ?></span>
                <div class="admin-form-field">
	                    <div class="admin-content-file-charge-control">
	                        <div class="admin-content-file-charge-main">
                                <div class="admin-setting-unit">
	                                <label class="admin-form-check form-label" for="modules_content_admin_contents_new_content_file_asset_download_enabled">
	                                    <input id="modules_content_admin_contents_new_content_file_asset_download_enabled" type="checkbox" name="new_content_file_asset_download_enabled" value="1" class="form-checkbox">
	                                    <?php echo sr_admin_choice_label_html(sr_t('content::ui.text.d07eab27')); ?>
	                                </label>
                                </div>
	                            <div class="admin-content-file-charge-assets admin-setting-unit admin-setting-unit-wide">
	                                <?php echo sr_admin_checkbox_list_html('content_admin_contents_new_content_file_asset_module', 'new_content_file_asset_module', $assetModuleChoiceOptions, [], sr_t('content::ui.text.3e195cdd')); ?>
	                                <p class="admin-form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
	                            </div>
                                <div class="admin-setting-unit">
	                                <input type="number" name="new_content_file_asset_download_amount" value="0" class="form-input admin-content-file-charge-amount" min="0" max="999999999" step="1" aria-label="<?php echo sr_e(sr_t('content::ui.text.63526029')); ?>">
                                </div>
                                <div class="admin-setting-unit">
	                                <select name="new_content_file_asset_charge_policy" class="form-select admin-content-file-charge-policy" aria-label="<?php echo sr_e(sr_t('content::ui.text.153a0e9d')); ?>">
	                                    <?php foreach (sr_content_asset_download_charge_policies() as $policyKey => $policyLabel) { ?>
	                                        <option value="<?php echo sr_e((string) $policyKey); ?>">
	                                            <?php echo sr_e((string) $policyLabel); ?>
	                                        </option>
	                                    <?php } ?>
	                                </select>
                                </div>
	                        </div>
	                    </div>
	                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/content')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('content::ui.list.f07b3200')); ?></a>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('content::ui.save.5fb92622')); ?></button>
        </div>
    </form>
<?php } else { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/content')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('content::ui.all.e078b14a')); ?></a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('content::ui.content.fc61037b')); ?> <strong><?php echo sr_e((string) $totalPages); ?><?php echo sr_e(sr_t('content::ui.text.a57ab057')); ?></strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/content?status=published')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('content::ui.text.9d1ba9f4')); ?> <?php echo sr_e((string) ($pageStatusCounts['published'] ?? 0)); ?><?php echo sr_e(sr_t('content::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/content?status=draft')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('content::ui.text.145b2413')); ?> <?php echo sr_e((string) ($pageStatusCounts['draft'] ?? 0)); ?><?php echo sr_e(sr_t('content::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/content?status=hidden')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('content::ui.text.0eeb676f')); ?> <?php echo sr_e((string) ($pageStatusCounts['hidden'] ?? 0)); ?><?php echo sr_e(sr_t('content::ui.text.a57ab057')); ?></a>
        </div>
    </div>

    <form method="get" action="<?php echo sr_e(sr_url('/admin/content')); ?>" class="admin-filter admin-content-filter ui-form-theme">
        <div class="admin-filter-grid admin-content-search-grid">
            <div class="admin-filter-field admin-content-filter-status">
                <label for="modules_content_admin_contents_status" class="admin-filter-label"><?php echo sr_e(sr_t('content::ui.status.e10195a1')); ?></label>
                <select id="modules_content_admin_contents_status" name="status" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($filters['status'] ?? '') === '' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('content::ui.all.a4b69faf')); ?></option>
                    <?php foreach (sr_content_allowed_statuses() as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($filters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-content-filter-group">
                <label for="modules_content_admin_contents_content_group_id" class="admin-filter-label"><?php echo sr_e(sr_t('content::ui.text.5d908ddd')); ?></label>
                <select id="modules_content_admin_contents_content_group_id" name="content_group_id" class="form-select admin-filter-input">
                    <option value="0"<?php echo (int) ($filters['content_group_id'] ?? 0) === 0 ? ' selected' : ''; ?>><?php echo sr_e(sr_t('content::ui.all.a4b69faf')); ?></option>
                    <?php foreach ($pageGroups as $pageGroup) { ?>
                        <option value="<?php echo sr_e((string) $pageGroup['id']); ?>"<?php echo (int) ($filters['content_group_id'] ?? 0) === (int) $pageGroup['id'] ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($pageGroup['title'] ?? $pageGroup['group_key'])); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-content-filter-field">
                <label for="modules_content_admin_contents_field" class="admin-filter-label"><?php echo sr_e(sr_t('content::ui.search.b79bc9c8')); ?></label>
                <select id="modules_content_admin_contents_field" name="field" class="form-select admin-filter-input">
                    <?php foreach (['all' => sr_t('content::ui.all.a4b69faf'), 'title' => sr_t('content::ui.text.08b17e43'), 'slug' => 'Slug'] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($filters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-content-filter-keyword">
                <label for="modules_content_admin_contents_q" class="admin-filter-label"><?php echo sr_e(sr_t('content::ui.search.bda397fc')); ?></label>
                <input id="modules_content_admin_contents_q" type="search" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('content::ui.slug.afd81de7')); ?>">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit"><?php echo sr_e(sr_t('content::ui.search.4b8d541e')); ?></button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?php echo sr_e(sr_t('content::ui.content.list.771ca9aa')); ?></h2>
                <p class="admin-dashboard-meta"><?php echo sr_e(sr_t('content::ui.status.content.slug.d9329b0b')); ?></p>
            </div>
            <a href="<?php echo sr_e(sr_url('/admin/content/new')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo sr_e(sr_t('content::ui.content.530929bb')); ?></a>
        </div>
        <div class="table-wrapper">
            <table class="table admin-content-table">
                <caption class="sr-only"><?php echo sr_e(sr_t('content::ui.content.list.771ca9aa')); ?></caption>
                <thead class="ui-table-head">
                    <tr>
                        <th><?php echo sr_e(sr_t('content::ui.text.08b17e43')); ?></th>
                        <th><?php echo sr_e(sr_t('content::ui.text.5d908ddd')); ?></th>
                        <th>Slug</th>
                        <th><?php echo sr_e(sr_t('content::ui.status.e10195a1')); ?></th>
                        <th><?php echo sr_e(sr_t('content::ui.text.c9b3e6f0')); ?></th>
                        <th><?php echo sr_e(sr_t('content::ui.text.f2ee20a7')); ?></th>
                        <th><?php echo sr_e(sr_t('content::ui.edit.d3a98476')); ?></th>
                        <th><?php echo sr_e(sr_t('content::ui.text.84b7c221')); ?></th>
                        <th class="text-end"><?php echo sr_e(sr_t('content::ui.text.29ae8f30')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($pages === []) { ?>
                        <tr>
                            <td colspan="9" class="admin-empty-state"><?php echo sr_e(sr_t('content::ui.create.content.8994ccd1')); ?></td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($pages as $page) { ?>
                            <?php
                            $pageStatus = (string) $page['status'];
                            $statusClass = match ($pageStatus) {
                                'published' => 'is-normal',
                                'draft' => 'is-blocked',
                                default => 'is-left',
                            };
                            ?>
                            <tr>
                                <td class="admin-table-break admin-content-title-cell"><?php echo sr_e((string) $page['title']); ?></td>
                                <td class="admin-table-nowrap"><?php echo sr_e((string) ($page['content_group_title'] ?? '')); ?></td>
                                <td class="admin-table-nowrap admin-content-slug-cell"><code><?php echo sr_e((string) $page['slug']); ?></code></td>
                                <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($pageStatus, 'content_status')); ?></span></td>
                                <td>
                                    <?php if ((int) ($page['asset_access_enabled'] ?? 0) === 1) { ?>
                                        <?php echo sr_e(sr_content_asset_module_labels((string) ($page['asset_module'] ?? ''))); ?>
                                        <?php echo sr_e(number_format((int) ($page['asset_access_amount'] ?? 0))); ?>
                                        · <?php echo sr_e(sr_content_asset_charge_policies()[(string) ($page['asset_charge_policy'] ?? 'once')] ?? ''); ?>
                                    <?php } else { ?>
                                        <?php echo sr_e(sr_t('content::ui.text.b8fb5347')); ?>
                                    <?php } ?>
                                </td>
                                <td class="admin-table-nowrap"><?php echo sr_e((string) ($page['created_by_name'] ?? '')); ?></td>
                                <td class="admin-table-nowrap admin-content-date-cell"><?php echo sr_e((string) $page['updated_at']); ?></td>
                                <td class="admin-table-nowrap admin-content-date-cell"><?php echo sr_e((string) ($page['published_at'] ?? '')); ?></td>
                                <td class="admin-table-actions-cell">
                                    <div class="admin-row-actions">
                                        <?php if ((string) $page['status'] === 'published') { ?>
                                            <a href="<?php echo sr_e(sr_url(sr_content_path((string) $page['slug']))); ?>" class="btn btn-sm btn-icon btn-solid-light" target="_blank" rel="noopener noreferrer" aria-label="<?php echo sr_e(sr_t('content::ui.text.ac5b575f')); ?>" title="<?php echo sr_e(sr_t('content::ui.text.ac5b575f')); ?>"><?php echo sr_material_icon_html('visibility'); ?></a>
                                        <?php } ?>
                                        <a href="<?php echo sr_e(sr_url('/admin/content/edit?id=' . rawurlencode((string) $page['id']))); ?>" class="btn btn-sm btn-icon btn-solid-light" aria-label="<?php echo sr_e(sr_t('content::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('content::ui.edit.3537f0cc')); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
                                        <?php if ((string) $page['status'] !== 'hidden') { ?>
                                            <form method="post" action="<?php echo sr_e(sr_url('/admin/content/delete')); ?>" class="admin-inline-form">
                                                <?php echo sr_csrf_field(); ?>
                                                <input type="hidden" name="content_id" value="<?php echo sr_e((string) $page['id']); ?>">
                                                <button type="submit" class="btn btn-sm btn-soft-danger"><?php echo sr_e(sr_t('content::ui.text.0eeb676f')); ?></button>
                                            </form>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
<?php } ?>

<?php if ($pageAdminPage === 'form') { ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var groupSelect = document.querySelector('[data-content-group-select]');
        var scopeOptions = document.querySelectorAll('[data-content-group-scope-option]');
        var sourceOptions = document.querySelectorAll('input[name^="source_"]');
        var requiredLabel = document.querySelector('[data-content-group-required]');
        if (!groupSelect || scopeOptions.length === 0) {
            return;
        }

        var syncGroupScope = function () {
            var selectedScope = document.querySelector('[data-content-group-scope-option]:checked');
            var useGroup = selectedScope && selectedScope.value === 'group';
            var useGroupSource = Array.prototype.slice.call(sourceOptions).some(function (option) {
                return option.checked && option.value === 'group';
            });
            var needsGroup = useGroup || useGroupSource;
            groupSelect.disabled = !needsGroup;
            groupSelect.required = needsGroup;
            if (requiredLabel) {
                requiredLabel.hidden = !needsGroup;
            }
            if (!needsGroup) {
                groupSelect.value = '0';
            }
        };

        scopeOptions.forEach(function (option) {
            option.addEventListener('change', syncGroupScope);
        });
        sourceOptions.forEach(function (option) {
            option.addEventListener('change', syncGroupScope);
        });
        syncGroupScope();
    });
    </script>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
