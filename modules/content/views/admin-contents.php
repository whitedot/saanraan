<?php

$sessionErrors = $_SESSION['sr_content_admin_errors'] ?? [];
$sessionValues = $_SESSION['sr_content_admin_values'] ?? [];
unset($_SESSION['sr_content_admin_errors'], $_SESSION['sr_content_admin_values']);
$hasSubmittedValues = is_array($sessionValues) && $sessionValues !== [];
if (is_array($sessionErrors)) {
    $errors = array_merge($errors, array_map('strval', $sessionErrors));
}
if ($hasSubmittedValues) {
    $values = $sessionValues;
    if (is_array($values['content_file_link_ids'] ?? null)) {
        $linkedDownloadFileIds = [];
        foreach ($values['content_file_link_ids'] as $submittedFileId) {
            $linkedDownloadFileIds[(int) $submittedFileId] = true;
        }
    }
}
$editing = is_array($editPage);
$contentAssetAuditUrl = $editing ? sr_admin_asset_settings_audit_url('content.asset_settings.updated', 'content', (string) (int) ($editPage['id'] ?? 0)) : '';
if ($values === []) {
    $values = $editing ? $editPage : sr_content_default_values($pdo, $site ?? null);
}
$contentSeriesOptions = isset($contentSeriesOptions) && is_array($contentSeriesOptions) ? $contentSeriesOptions : [];
$currentContentSeriesItem = isset($currentContentSeriesItem) && is_array($currentContentSeriesItem) ? $currentContentSeriesItem : null;
$contentSeriesValues = [
    'series_id' => array_key_exists('series_id', $values) ? (int) $values['series_id'] : (is_array($currentContentSeriesItem) ? (int) $currentContentSeriesItem['series_id'] : 0),
    'series_episode_label' => array_key_exists('series_episode_label', $values) ? (string) $values['series_episode_label'] : (is_array($currentContentSeriesItem) ? (string) ($currentContentSeriesItem['episode_label'] ?? '') : ''),
    'series_sort_order' => array_key_exists('series_sort_order', $values) ? (int) $values['series_sort_order'] : (is_array($currentContentSeriesItem) ? (int) ($currentContentSeriesItem['sort_order'] ?? 0) : 0),
];

$adminPageTitle = $pageAdminPage === 'form' ? ($editing ? sr_t('content::ui.content.edit.9fdd9b62') : sr_t('content::ui.content.62a2bf90')) : '콘텐츠 관리';
$adminPageSubtitle = $pageAdminPage === 'form' ? sr_t('content::ui.content.status.85bf8a35') : sr_t('content::ui.content.status.search.29f7335b');
$adminContainerClass = $pageAdminPage === 'form' ? 'admin-content-form admin-ui-scope' : 'admin-content-list admin-ui-scope';
$filters = isset($filters) && is_array($filters) ? $filters : ['status' => '', 'content_group_id' => 0, 'field' => 'all', 'q' => ''];
$contentSort = isset($contentSort) && is_array($contentSort) ? $contentSort : sr_content_admin_default_sort();
$pageStatusCounts = isset($pageStatusCounts) && is_array($pageStatusCounts) ? $pageStatusCounts : [];
$pageGroups = isset($pageGroups) && is_array($pageGroups) ? $pageGroups : [];
$newContentFileAssetSettings = [
    'file_asset_download_enabled' => 0,
    'file_asset_module' => '',
    'file_asset_download_amount' => 0,
    'file_asset_download_amounts_json' => '',
    'file_asset_download_group_policies_json' => '',
    'file_asset_download_policy_set_id' => 0,
    'file_asset_charge_policy' => 'once',
];
$publicLayoutOptions = isset($publicLayoutOptions) && is_array($publicLayoutOptions) ? $publicLayoutOptions : sr_public_layout_options($pdo ?? null);
$contentEditorKey = $pdo instanceof PDO ? sr_content_editor_key($pdo) : 'textarea';
$contentEditorAttributes = $pdo instanceof PDO ? sr_editor_textarea_attributes($pdo, $contentEditorKey, 'content_basic') : '';
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
    ? sr_t('content::ui.text.706623d8') . implode(', ', $assetDeductionPriorityLabels)
    : sr_t('content::ui.text.3e195cdd');
$memberGroups = isset($memberGroups) && is_array($memberGroups) ? $memberGroups : [];
$assetPolicySets = isset($assetPolicySets) && is_array($assetPolicySets) ? $assetPolicySets : [];
$pageGroupScopeLabels = [
    'here_only' => ['visible' => sr_t('content::ui.scope.current_only'), 'sr' => '적용', 'toast' => ''],
    'group' => ['visible' => sr_t('content::ui.scope.copy_group'), 'sr' => '적용', 'toast' => '이 설정을 같은 그룹 콘텐츠에 적용합니다.'],
    'all' => ['visible' => sr_t('content::ui.scope.copy_all'), 'sr' => '적용', 'toast' => '이 설정을 전체 콘텐츠에 적용합니다.'],
];
$pageScopeLabelHtml = static function (array $label): string {
    $srLabel = (string) ($label['sr'] ?? '');
    return sr_e((string) ($label['visible'] ?? '')) . ($srLabel !== '' ? '<span class="sr-only">' . sr_e($srLabel) . '</span>' : '');
};
$pageGroupScopeRadioHtml = static function (string $name, string $selectedScope) use ($pageGroupScopeLabels, $pageScopeLabelHtml): string {
    $selectedScope = array_key_exists($selectedScope, $pageGroupScopeLabels) ? $selectedScope : 'here_only';
    $html = sr_t('content::ui.div.class.admin.setting.source.01280cd8');
    foreach ($pageGroupScopeLabels as $scope => $label) {
        $id = 'content_group_scope_' . $scope;
        $html .= '<label class="admin-form-check form-label" for="' . sr_e($id) . '">';
        $toast = (string) ($label['toast'] ?? '');
        $html .= '<input id="' . sr_e($id) . '" type="radio" name="' . sr_e($name) . '" value="' . sr_e($scope) . '" class="form-radio" data-content-group-scope-option' . ($toast !== '' ? ' data-admin-scope-toast="' . sr_e($toast) . '"' : '') . ($selectedScope === $scope ? ' checked' : '') . '>';
        $html .= $pageScopeLabelHtml($label);
        $html .= '</label>';
    }

    return $html . '</div>';
};
$pageSettingSourceLabels = [
    'content' => $pageGroupScopeLabels['here_only'],
    'group' => $pageGroupScopeLabels['group'],
    'all' => $pageGroupScopeLabels['all'],
];
$pageSettingSource = static function (array $values, string $key): string {
    if (array_key_exists('source_' . $key, $values)) {
        return sr_content_normalize_setting_source((string) $values['source_' . $key]);
    }

    $sources = is_array($values['setting_sources'] ?? null) ? $values['setting_sources'] : [];
    return sr_content_normalize_setting_source((string) ($sources[$key] ?? 'content'));
};
$pageSettingSourceRadioHtml = static function (string $name, string $selectedSource, bool $isMaster = false) use ($pageSettingSourceLabels, $pageScopeLabelHtml): string {
    $selectedSource = array_key_exists($selectedSource, $pageSettingSourceLabels) ? $selectedSource : 'content';
    $baseId = preg_replace('/[^a-zA-Z0-9_]+/', '_', $name);
    $html = sr_t('content::ui.div.class.admin.setting.source.67eda3ac');
    foreach ($pageSettingSourceLabels as $source => $label) {
        $id = 'content_setting_source_' . $baseId . '_' . $source;
        $html .= '<label class="admin-form-check form-label" for="' . sr_e($id) . '">';
        $toast = (string) ($label['toast'] ?? '');
        $html .= '<input id="' . sr_e($id) . '" type="radio" name="' . sr_e($name) . '" value="' . sr_e($source) . '" class="form-radio"' . ($isMaster ? ' data-admin-setting-source-master' : '') . ($toast !== '' ? ' data-admin-scope-toast="' . sr_e($toast) . '"' : '') . ($selectedSource === $source ? ' checked' : '') . '>';
        $html .= $pageScopeLabelHtml($label);
        $html .= '</label>';
    }

    return $html . '</div>';
};
$values['content_group_scope'] = sr_content_group_apply_scope((string) ($values['content_group_scope'] ?? 'here_only'));
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
$contentHelpOpenLabel = sr_t('content::help.open');
$contentHelpButtonHtml = static function (string $label, string $modalId) use ($contentHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $contentHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$contentHelpBodyHtml = static function (array $keys): string {
    $html = '';
    foreach ($keys as $key) {
        $html .= '<p>' . sr_e(sr_t((string) $key)) . '</p>';
    }

    return $html;
};
$contentCopyModals = '';
$contentCopyModalHtml = static function (array $content, string $returnTo): string {
    $contentId = (int) ($content['id'] ?? 0);
    if ($contentId < 1) {
        return '';
    }
    $modalId = 'content-copy-modal-' . (string) $contentId;
    $suggestion = sr_content_copy_suggestion($content);
    ob_start();
    ?>
    <div id="<?php echo sr_e($modalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($modalId); ?>-label" aria-hidden="true" inert>
        <div class="modal-dialog">
            <div class="modal-content ui-form-theme">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/content/copy')); ?>">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($modalId); ?>-label" class="modal-title"><?php echo sr_e('콘텐츠 복사'); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($modalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="content_id" value="<?php echo sr_e((string) $contentId); ?>">
                        <input type="hidden" name="return_to" value="<?php echo sr_e($returnTo); ?>">
                        <p class="admin-form-help"><?php echo sr_e((string) ($content['title'] ?? '')); ?></p>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($modalId); ?>-title"><?php echo sr_e('새 제목'); ?> <span class="sr-required-label"><?php echo sr_e('(필수)'); ?></span></label>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($modalId); ?>-title" type="text" name="title" value="<?php echo sr_e((string) $suggestion['title']); ?>" class="form-input form-control-full" maxlength="160" required data-overlay-focus>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="<?php echo sr_e($modalId); ?>-slug"><?php echo sr_e('Slug'); ?> <span class="sr-required-label"><?php echo sr_e('(필수)'); ?></span></label>
                            <div class="admin-form-field">
                                <input id="<?php echo sr_e($modalId); ?>-slug" type="text" name="slug" value="<?php echo sr_e((string) $suggestion['slug']); ?>" class="form-input form-control-full" maxlength="120" pattern="[a-z0-9][a-z0-9\-]{1,118}[a-z0-9]" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-slug-input>
                                <p class="admin-form-help"><?php echo sr_e('복사본은 초안으로 저장됩니다. 댓글, 이용 로그, 리비전, 시리즈 연결은 복사하지 않습니다.'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>"><?php echo sr_e('취소'); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e('복사본 만들기'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
};
$contentHelp = [
    'title' => [
        'id' => 'content_admin_help_title',
        'title' => sr_t('content::help.title.title'),
        'body' => $contentHelpBodyHtml(['content::help.title.body.1', 'content::help.title.body.2']),
    ],
    'slug' => [
        'id' => 'content_admin_help_slug',
        'title' => sr_t('content::help.slug.title'),
        'body' => $contentHelpBodyHtml(['content::help.slug.body.1', 'content::help.slug.body.2']),
    ],
    'content_group' => [
        'id' => 'content_admin_help_content_group',
        'title' => sr_t('content::help.content_group.title'),
        'body' => $contentHelpBodyHtml(['content::help.content_group.body.1', 'content::help.content_group.body.2', 'content::help.content_group.body.3']),
    ],
    'summary' => [
        'id' => 'content_admin_help_summary',
        'title' => sr_t('content::help.summary.title'),
        'body' => $contentHelpBodyHtml(['content::help.summary.body.1', 'content::help.summary.body.2']),
    ],
    'body_text' => [
        'id' => 'content_admin_help_body_text',
        'title' => sr_t('content::help.body_text.title'),
        'body' => $contentHelpBodyHtml(['content::help.body_text.body.1', 'content::help.body_text.body.2']),
    ],
    'seo_title' => [
        'id' => 'content_admin_help_seo_title',
        'title' => sr_t('content::help.seo_title.title'),
        'body' => $contentHelpBodyHtml(['content::help.seo_title.body.1', 'content::help.seo_title.body.2']),
    ],
    'seo_description' => [
        'id' => 'content_admin_help_seo_description',
        'title' => sr_t('content::help.seo_description.title'),
        'body' => $contentHelpBodyHtml(['content::help.seo_description.body.1', 'content::help.seo_description.body.2']),
    ],
    'status' => [
        'id' => 'content_admin_help_status',
        'title' => sr_t('content::help.status.title'),
        'body' => $contentHelpBodyHtml(['content::help.status.body.1', 'content::help.status.body.2', 'content::help.status.body.3', 'content::help.status.body.4']),
    ],
    'layout' => [
        'id' => 'content_admin_help_layout',
        'title' => sr_t('content::help.layout.title'),
        'body' => $contentHelpBodyHtml(['content::help.layout.body.1', 'content::help.layout.body.2']),
    ],
    'asset_access_enabled' => [
        'id' => 'content_admin_help_asset_access_enabled',
        'title' => sr_t('content::help.asset_access_enabled.title'),
        'body' => $contentHelpBodyHtml(['content::help.asset_access_enabled.body.1', 'content::help.asset_access_enabled.body.2']),
    ],
    'asset_module' => [
        'id' => 'content_admin_help_asset_module',
        'title' => sr_t('content::help.asset_module.title'),
        'body' => $contentHelpBodyHtml(['content::help.asset_module.body.1', 'content::help.asset_module.body.2']),
    ],
    'asset_access_amount' => [
        'id' => 'content_admin_help_asset_access_amount',
        'title' => sr_t('content::help.asset_access_amount.title'),
        'body' => $contentHelpBodyHtml(['content::help.asset_access_amount.body.1', 'content::help.asset_access_amount.body.2']),
    ],
    'asset_charge_policy' => [
        'id' => 'content_admin_help_asset_charge_policy',
        'title' => sr_t('content::help.asset_charge_policy.title'),
        'body' => $contentHelpBodyHtml(['content::help.asset_charge_policy.body.1', 'content::help.asset_charge_policy.body.2']),
    ],
    'asset_action_enabled' => [
        'id' => 'content_admin_help_asset_action_enabled',
        'title' => sr_t('content::help.asset_action_enabled.title'),
        'body' => $contentHelpBodyHtml(['content::help.asset_action_enabled.body.1', 'content::help.asset_action_enabled.body.2']),
    ],
    'asset_action_label' => [
        'id' => 'content_admin_help_asset_action_label',
        'title' => sr_t('content::help.asset_action_label.title'),
        'body' => $contentHelpBodyHtml(['content::help.asset_action_label.body.1', 'content::help.asset_action_label.body.2']),
    ],
    'asset_action_direction' => [
        'id' => 'content_admin_help_asset_action_direction',
        'title' => sr_t('content::help.asset_action_direction.title'),
        'body' => $contentHelpBodyHtml(['content::help.asset_action_direction.body.1', 'content::help.asset_action_direction.body.2']),
    ],
    'asset_action_module' => [
        'id' => 'content_admin_help_asset_action_module',
        'title' => sr_t('content::help.asset_action_module.title'),
        'body' => $contentHelpBodyHtml(['content::help.asset_action_module.body.1', 'content::help.asset_action_module.body.2']),
    ],
    'asset_action_amount' => [
        'id' => 'content_admin_help_asset_action_amount',
        'title' => sr_t('content::help.asset_action_amount.title'),
        'body' => $contentHelpBodyHtml(['content::help.asset_action_amount.body.1', 'content::help.asset_action_amount.body.2']),
    ],
    'banner_before' => [
        'id' => 'content_admin_help_banner_before',
        'title' => sr_t('content::help.banner_before.title'),
        'body' => $contentHelpBodyHtml(['content::help.banner_before.body.1', 'content::help.banner_before.body.2']),
    ],
    'banner_after' => [
        'id' => 'content_admin_help_banner_after',
        'title' => sr_t('content::help.banner_after.title'),
        'body' => $contentHelpBodyHtml(['content::help.banner_after.body.1', 'content::help.banner_after.body.2']),
    ],
    'popup_layer' => [
        'id' => 'content_admin_help_popup_layer',
        'title' => sr_t('content::help.popup_layer.title'),
        'body' => $contentHelpBodyHtml(['content::help.popup_layer.body.1', 'content::help.popup_layer.body.2']),
    ],
    'file_upload' => [
        'id' => 'content_admin_help_file_upload',
        'title' => sr_t('content::help.file_upload.title'),
        'body' => $contentHelpBodyHtml(['content::help.file_upload.body.1', 'content::help.file_upload.body.2', 'content::help.file_upload.body.3']),
    ],
    'file_title' => [
        'id' => 'content_admin_help_file_title',
        'title' => sr_t('content::help.file_title.title'),
        'body' => $contentHelpBodyHtml(['content::help.file_title.body.1', 'content::help.file_title.body.2']),
    ],
    'file_charge' => [
        'id' => 'content_admin_help_file_charge',
        'title' => sr_t('content::help.file_charge.title'),
        'body' => $contentHelpBodyHtml(['content::help.file_charge.body.1', 'content::help.file_charge.body.2', 'content::help.file_charge.body.3', 'content::help.file_charge.body.4']),
    ],
];
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
                <?php echo sr_admin_form_label_help_html('content_admin_contents_title', sr_t('content::ui.text.08b17e43'), $contentHelp['title']['id'], $contentHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <input id="content_admin_contents_title" type="text" name="title" value="<?php echo sr_e((string) ($values['title'] ?? '')); ?>" class="form-input form-control-full" maxlength="160" required>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('content_admin_contents_slug', 'Slug', $contentHelp['slug']['id'], $contentHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <input id="content_admin_contents_slug" type="text" name="slug" value="<?php echo sr_e((string) ($values['slug'] ?? '')); ?>" class="form-input form-control-full" maxlength="120" pattern="[a-z0-9][a-z0-9\-]{1,118}[a-z0-9]" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-slug-input>
                    <br>
                                        <small><?php echo sr_e(sr_t('content::ui.content.slug.active.359891c0')); ?></small>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="form-label admin-form-label-help"><?php echo $contentHelpButtonHtml(sr_t('content::ui.content.5875c5b3'), $contentHelp['content_group']['id']); ?><label for="content_admin_contents_content_group_id"><?php echo sr_e(sr_t('content::ui.content.5875c5b3')); ?> <span class="sr-required-label" data-content-group-required hidden><?php echo sr_e(sr_t('content::ui.required.1f227c67')); ?></span></label></div>
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
                    <p class="admin-form-help"><?php echo sr_e('콘텐츠 그룹은 운영 묶음입니다. 목록 페이지, 초기화면 후보, 새 콘텐츠 기본값, 그룹/전체 복사 범위를 관리하며 읽기 순서나 회차 내비게이션은 만들지 않습니다.'); ?></p>
                    <p class="admin-form-help"><?php echo sr_e(sr_t('content::ui.select.list.menu.10a1aa2a')); ?></p>
                    <p class="admin-form-help"><?php echo sr_e(sr_t('content::ui.scope.copy_help')); ?></p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_series_id"><?php echo sr_e('시리즈'); ?></label>
                <div class="admin-form-field">
                    <select id="content_admin_contents_series_id" name="series_id" class="form-select">
                        <option value="0"<?php echo (int) $contentSeriesValues['series_id'] === 0 ? ' selected' : ''; ?>><?php echo sr_e('연결 안 함'); ?></option>
                        <?php foreach ($contentSeriesOptions as $seriesOption) { ?>
                            <option value="<?php echo sr_e((string) $seriesOption['id']); ?>"<?php echo (int) $contentSeriesValues['series_id'] === (int) $seriesOption['id'] ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $seriesOption['title']); ?> / <?php echo sr_e(sr_content_series_visibility_label((string) $seriesOption['visibility'])); ?> / <?php echo sr_e(sr_content_series_status_label((string) $seriesOption['status'])); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <p class="admin-form-help"><?php echo sr_e('콘텐츠 시리즈는 읽기 흐름입니다. 콘텐츠 그룹과 독립적으로 회차 표시, 정렬 순서, 이전/다음 내비게이션만 관리합니다.'); ?></p>
                    <div class="admin-form-inline">
                        <label for="content_admin_contents_series_episode_label">
                            <span><?php echo sr_e('회차 표시'); ?></span>
                            <input id="content_admin_contents_series_episode_label" type="text" name="series_episode_label" maxlength="80" value="<?php echo sr_e((string) $contentSeriesValues['series_episode_label']); ?>" class="form-input">
                        </label>
                        <label for="content_admin_contents_series_sort_order">
                            <span><?php echo sr_e('정렬 순서'); ?></span>
                            <input id="content_admin_contents_series_sort_order" type="number" name="series_sort_order" min="0" max="1000000" value="<?php echo sr_e((string) (int) $contentSeriesValues['series_sort_order']); ?>" class="form-input">
                        </label>
                    </div>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('content_admin_contents_summary', sr_t('content::ui.text.50f30154'), $contentHelp['summary']['id'], $contentHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <textarea id="content_admin_contents_summary" name="summary" maxlength="1000" class="form-textarea"><?php echo sr_e((string) ($values['summary'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('content_admin_contents_body_text', sr_t('content::ui.text.9118bb57'), $contentHelp['body_text']['id'], $contentHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <textarea id="content_admin_contents_body_text" name="body_text" rows="14" class="form-textarea"<?php echo $contentEditorAttributes; ?>><?php echo sr_e((string) ($values['body_text'] ?? '')); ?></textarea>
                    <br>
                    <small><?php echo sr_e($contentEditorKey === 'ckeditor' ? 'CKEditor 제출은 허용된 HTML만 정화해 저장합니다.' : sr_t('content::ui.content.plain.save.723dab58')); ?></small>
                    <?php if (sr_module_enabled($pdo, 'community')) { ?>
                        <div class="sr-link-card-picker" data-link-card-picker data-endpoint="<?php echo sr_e(sr_url('/admin/content/link-card-targets')); ?>" data-target="community_post" data-textarea="content_admin_contents_body_text">
                            <div class="sr-link-card-picker-controls">
                                <input type="search" class="form-input" data-link-card-search placeholder="<?php echo sr_e('커뮤니티 게시글 제목, 게시판, ID 검색'); ?>">
                                <button type="button" class="btn btn-solid-light" data-link-card-search-trigger><?php echo sr_e('검색'); ?></button>
                                <button type="button" class="btn btn-solid-primary" data-link-card-insert><?php echo sr_e('본문에 삽입'); ?></button>
                            </div>
                            <div class="sr-link-card-picker-results" data-link-card-results><?php echo sr_e('커뮤니티 게시글을 검색해 본문에 HTML 또는 텍스트 링크로 삽입합니다.'); ?></div>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('content_admin_contents_seo_title', sr_t('content::ui.seo.f66e126a'), $contentHelp['seo_title']['id'], $contentHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <input id="content_admin_contents_seo_title" type="text" name="seo_title" value="<?php echo sr_e((string) ($values['seo_title'] ?? '')); ?>" class="form-input form-control-full" maxlength="160">
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('content_admin_contents_seo_description', sr_t('content::ui.seo.b6187d8d'), $contentHelp['seo_description']['id'], $contentHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <input id="content_admin_contents_seo_description" type="text" name="seo_description" value="<?php echo sr_e((string) ($values['seo_description'] ?? '')); ?>" class="form-input form-control-full" maxlength="255">
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('content_admin_contents_status', sr_t('content::ui.status.e10195a1'), $contentHelp['status']['id'], $contentHelpOpenLabel, true); ?>
                <div class="admin-form-field">
                    <select id="content_admin_contents_status" name="status" class="form-select" required data-content-status-select>
                                                <?php foreach (sr_content_allowed_statuses() as $status) { ?>
                                                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($values['status'] ?? 'draft') === $status ? ' selected' : ''; ?>>
                                                        <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
	                    <?php echo $pageSettingSourceRadioHtml('source_status', $pageSettingSource($values, 'status')); ?>
                    <p class="admin-form-help">예약 상태를 선택하면 아래 시각 이후 공개 콘텐츠로 전환됩니다.</p>
	                </div>
	            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_scheduled_publish_at">예약 발행 시각 <span class="sr-required-label" data-content-scheduled-required hidden>(필수)</span></label>
                <div class="admin-form-field">
                    <input id="content_admin_contents_scheduled_publish_at" type="datetime-local" name="scheduled_publish_at" value="<?php echo sr_e(sr_content_datetime_local_value((string) ($values['scheduled_publish_at'] ?? $values['published_at'] ?? ''))); ?>" class="form-input" data-content-scheduled-input>
                    <p class="admin-form-help">상태가 예약일 때만 저장에 사용됩니다. 즉시 공개는 상태를 공개로 선택하세요.</p>
                </div>
            </div>
	            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('content_admin_contents_layout_key', sr_t('content::ui.content.fa985852'), $contentHelp['layout']['id'], $contentHelpOpenLabel); ?>
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
            <h2>
                <span><?php echo sr_e(sr_t('content::ui.text.c9b3e6f0')); ?></span>
                <?php if ($contentAssetAuditUrl !== '') { ?>
                    <span class="admin-form-actions">
                        <a href="<?php echo sr_e($contentAssetAuditUrl); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e('포인트/금액 변경 이력'); ?></a>
                    </span>
                <?php } ?>
            </h2>
            <div class="admin-form-row">
                <div class="form-label admin-form-label-help"><?php echo $contentHelpButtonHtml(sr_t('content::ui.active.923da40e'), $contentHelp['asset_access_enabled']['id']); ?><span><?php echo sr_e(sr_t('content::ui.text.c9b3e6f0')); ?> 사용</span></div>
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
                <?php echo sr_admin_form_label_help_html('content_admin_contents_asset_charge_policy', sr_t('content::ui.text.86803f52'), $contentHelp['asset_charge_policy']['id'], $contentHelpOpenLabel); ?>
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
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('content_admin_contents_asset_module', sr_t('content::ui.text.c9b3e6f0') . ' 자산 설정', $contentHelp['asset_module']['id'], $contentHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <?php $selectedAccessAssetModules = sr_content_asset_module_keys_from_value($values['asset_module'] ?? ''); ?>
                    <div class="admin-asset-setting-line" data-admin-setting-source-group>
                        <div class="admin-asset-setting-control admin-asset-setting-control-full">
                            <div class="admin-asset-setting-target" data-admin-asset-enable-target="#modules_content_admin_contents_asset_access_enabled" data-admin-asset-enable-submit-check="always">
                                <input id="content_admin_contents_asset_access_amount" type="hidden" name="asset_access_amount" value="<?php echo sr_e((string) (int) ($values['asset_access_amount'] ?? 0)); ?>">
                                <?php echo sr_content_asset_grouped_amount_inputs_html('content_admin_contents_asset_access_amounts_grouped', 'asset_module', 'asset_access_amounts', $assetModuleOptions, $selectedAccessAssetModules, $values['asset_access_amounts_json'] ?? '', (int) ($values['asset_access_amount'] ?? 0), sr_t('content::ui.text.a9f15a8b'), sr_t('content::ui.text.3e195cdd')); ?>
                            </div>
                            <p class="admin-form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
                        </div>
                        <div class="admin-asset-setting-scope">
                            <?php echo $pageSettingSourceRadioHtml('source_asset_module', $pageSettingSource($values, 'asset_module'), true); ?>
                            <input type="hidden" name="source_asset_access_amount" value="<?php echo sr_e($pageSettingSource($values, 'asset_module')); ?>" data-admin-setting-source-mirror>
                            <input type="hidden" name="source_asset_access_amounts_json" value="<?php echo sr_e($pageSettingSource($values, 'asset_module')); ?>" data-admin-setting-source-mirror>
                        </div>
                    </div>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_asset_access_policy_set_ids"><?php echo sr_e('회원 그룹별 적용'); ?></label>
                <div class="admin-form-field admin-policy-set-field">
                    <?php echo $pageSettingSourceRadioHtml('source_asset_access_policy_set_id', $pageSettingSource($values, 'asset_access_policy_set_id')); ?>
                    <?php echo sr_content_asset_policy_set_checkboxes_html('content_admin_contents_asset_access_policy_set_ids', 'asset_access_policy_set_ids', $assetPolicySets, sr_content_asset_policy_set_ids_with_legacy($values['asset_access_group_policies_json'] ?? '', (int) ($values['asset_access_policy_set_id'] ?? 0)), 'neutral', '', '#content_admin_contents_asset_access_amounts_grouped', $pdo); ?>
                    <p class="admin-form-help">도움말: 선택한 회원 그룹별 적용이 회원의 그룹과 선택한 포인트/금액 항목에 맞는 실제 금액을 계산합니다. 세트의 계산 방식과 조정값은 콘텐츠 회원 그룹별 적용 화면에서 관리합니다.</p>
                </div>
            </div>
        </section>
        <section class="admin-card card">
            <h2>
                <span><?php echo sr_e(sr_t('content::ui.text.76faa117')); ?></span>
                <?php if ($contentAssetAuditUrl !== '') { ?>
                    <span class="admin-form-actions">
                        <a href="<?php echo sr_e($contentAssetAuditUrl); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e('포인트/금액 변경 이력'); ?></a>
                    </span>
                <?php } ?>
            </h2>
            <div class="admin-form-row">
                <div class="form-label admin-form-label-help"><?php echo $contentHelpButtonHtml(sr_t('content::ui.active.8bcecbe7'), $contentHelp['asset_action_enabled']['id']); ?><span><?php echo sr_e(sr_t('content::ui.text.76faa117')); ?> 사용</span></div>
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
                <?php echo sr_admin_form_label_help_html('content_admin_contents_asset_action_label', sr_t('content::ui.text.98fb4605'), $contentHelp['asset_action_label']['id'], $contentHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <input id="content_admin_contents_asset_action_label" type="text" name="asset_action_label" value="<?php echo sr_e((string) ($values['asset_action_label'] ?? sr_t('content::ui.text.727333ab'))); ?>" class="form-input" maxlength="80">
                    <?php echo $pageSettingSourceRadioHtml('source_asset_action_label', $pageSettingSource($values, 'asset_action_label')); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <?php echo sr_admin_form_label_help_html('content_admin_contents_asset_action_direction', sr_t('content::ui.text.af7873a8'), $contentHelp['asset_action_direction']['id'], $contentHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <select id="content_admin_contents_asset_action_direction" name="asset_action_direction" class="form-select" data-content-action-direction>
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
                <?php echo sr_admin_form_label_help_html('content_admin_contents_asset_action_module', sr_t('content::ui.text.76faa117') . ' 자산 설정', $contentHelp['asset_action_module']['id'], $contentHelpOpenLabel); ?>
                <div class="admin-form-field">
                    <?php $selectedActionAssetModules = sr_content_asset_module_keys_from_value($values['asset_action_module'] ?? ''); ?>
                    <div class="admin-asset-setting-line" data-admin-setting-source-group>
                        <div class="admin-asset-setting-control admin-asset-setting-control-full">
                            <div class="admin-asset-setting-target" data-admin-asset-enable-target="#modules_content_admin_contents_asset_action_enabled" data-admin-asset-enable-submit-check="always">
                                <input id="content_admin_contents_asset_action_amount" type="hidden" name="asset_action_amount" value="<?php echo sr_e((string) (int) ($values['asset_action_amount'] ?? 0)); ?>">
                                <?php echo sr_content_asset_grouped_amount_inputs_html('content_admin_contents_asset_action_amounts_grouped', 'asset_action_module', 'asset_action_amounts', $assetModuleOptions, $selectedActionAssetModules, $values['asset_action_amounts_json'] ?? '', (int) ($values['asset_action_amount'] ?? 0), sr_t('content::ui.text.5c705e1a'), sr_t('content::ui.text.3e195cdd'), '#content_admin_contents_asset_action_direction', 'grant'); ?>
                            </div>
                            <p class="admin-form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
                        </div>
                        <div class="admin-asset-setting-scope">
                            <?php echo $pageSettingSourceRadioHtml('source_asset_action_module', $pageSettingSource($values, 'asset_action_module'), true); ?>
                            <input type="hidden" name="source_asset_action_amount" value="<?php echo sr_e($pageSettingSource($values, 'asset_action_module')); ?>" data-admin-setting-source-mirror>
                            <input type="hidden" name="source_asset_action_amounts_json" value="<?php echo sr_e($pageSettingSource($values, 'asset_action_module')); ?>" data-admin-setting-source-mirror>
                        </div>
                    </div>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e('회원 그룹별 적용'); ?></span>
                <div class="admin-form-field admin-policy-set-field">
                    <?php echo $pageSettingSourceRadioHtml('source_asset_action_policy_set_id', $pageSettingSource($values, 'asset_action_policy_set_id')); ?>
                    <?php echo sr_content_asset_policy_set_checkboxes_html('content_admin_contents_asset_action_policy_set_ids', 'asset_action_policy_set_ids', $assetPolicySets, sr_content_asset_policy_set_ids_with_legacy($values['asset_action_group_policies_json'] ?? '', (int) ($values['asset_action_policy_set_id'] ?? 0)), (string) ($values['asset_action_direction'] ?? 'grant'), '#content_admin_contents_asset_action_direction', '#content_admin_contents_asset_action_amounts_grouped', $pdo); ?>
                    <p class="admin-form-help">도움말: 선택한 회원 그룹별 적용이 회원의 그룹과 선택한 포인트/금액 항목에 맞는 실제 금액을 계산합니다. 세트의 계산 방식과 조정값은 콘텐츠 회원 그룹별 적용 화면에서 관리합니다.</p>
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
                <?php echo sr_admin_form_label_help_html('content_admin_contents_banner_before_content_id', sr_t('content::ui.banner.042ab3f3'), $contentHelp['banner_before']['id'], $contentHelpOpenLabel); ?>
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
                <?php echo sr_admin_form_label_help_html('content_admin_contents_banner_after_content_id', sr_t('content::ui.banner.5818427a'), $contentHelp['banner_after']['id'], $contentHelpOpenLabel); ?>
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
                <?php echo sr_admin_form_label_help_html('content_admin_contents_popup_layer_id', sr_t('content::ui.text.1063d585'), $contentHelp['popup_layer']['id'], $contentHelpOpenLabel); ?>
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
            <h2>
                <span><?php echo sr_e(sr_t('content::ui.text.c7c88adc')); ?></span>
                <span class="admin-form-actions">
                    <a href="<?php echo sr_e(sr_url('/admin/content/files')); ?>" class="btn btn-sm btn-solid-light">파일 관리</a>
                </span>
            </h2>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_contents_download_file_links_select">연결 파일</label>
                <div class="admin-form-field admin-policy-set-field">
                    <?php if ($downloadFiles !== []) { ?>
                        <?php echo sr_content_download_file_link_badge_select_html('content_admin_contents_download_file_links', 'content_file_link_ids', $downloadFiles, array_keys($linkedDownloadFileIds), $pdo); ?>
                        <p class="admin-form-help">미리 등록한 사용 상태 파일만 연결할 수 있습니다. 파일 제목, 숨김, 다운로드 과금 정책은 파일 관리 화면에서 처리합니다.</p>
                    <?php } else { ?>
                        <p class="admin-form-help">연결할 다운로드 파일이 없습니다. 파일 관리 화면에서 파일을 먼저 등록하세요.</p>
                    <?php } ?>
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/content')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('content::ui.list.f07b3200')); ?></a>
            <?php if ($editing) { ?>
                <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="content-copy-modal-<?php echo sr_e((string) (int) $editPage['id']); ?>" data-overlay="#content-copy-modal-<?php echo sr_e((string) (int) $editPage['id']); ?>"><?php echo sr_e('복사'); ?></button>
            <?php } ?>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('content::ui.save.5fb92622')); ?></button>
        </div>
    </form>
    <?php echo $editing ? $contentCopyModalHtml($editPage, '/admin/content/edit?id=' . rawurlencode((string) $editPage['id'])) : ''; ?>
    <?php echo $pdo instanceof PDO ? sr_editor_assets_html($pdo, $contentEditorKey, 'content_basic') : ''; ?>
<?php } else { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/content')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('content::ui.all.e078b14a')); ?></a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('content::ui.content.fc61037b')); ?> <strong><?php echo sr_e((string) $totalPages); ?><?php echo sr_e(sr_t('content::ui.text.a57ab057')); ?></strong></span>
	            <a href="<?php echo sr_e(sr_url('/admin/content?status=published')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('content::ui.text.9d1ba9f4')); ?> <?php echo sr_e((string) ($pageStatusCounts['published'] ?? 0)); ?><?php echo sr_e(sr_t('content::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/content?status=scheduled')); ?>" class="admin-summary-meta">예약 <?php echo sr_e((string) ($pageStatusCounts['scheduled'] ?? 0)); ?><?php echo sr_e(sr_t('content::ui.text.a57ab057')); ?></a>
	            <a href="<?php echo sr_e(sr_url('/admin/content?status=draft')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('content::ui.text.145b2413')); ?> <?php echo sr_e((string) ($pageStatusCounts['draft'] ?? 0)); ?><?php echo sr_e(sr_t('content::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/content?status=hidden')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('content::ui.text.0eeb676f')); ?> <?php echo sr_e((string) ($pageStatusCounts['hidden'] ?? 0)); ?><?php echo sr_e(sr_t('content::ui.text.a57ab057')); ?></a>
        </div>
    </div>

    <?php
    $selectedContentStatuses = is_array($filters['status'] ?? null) ? $filters['status'] : [];
    $contentDetailFilterOpen = $selectedContentStatuses !== [] || (int) ($filters['content_group_id'] ?? 0) > 0;
    ?>
    <form method="get" action="<?php echo sr_e(sr_url('/admin/content')); ?>" class="filtering-form admin-content-filter ui-form-theme">
        <div class="filtering-fields admin-content-search-grid admin-content-filter-stack">
            <div class="filtering filtering-card<?php echo $contentDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
                <div class="filtering-fields">
                    <div class="filtering-field admin-content-filter-field">
                        <label for="modules_content_admin_contents_field" class="filtering-label">검색조건</label>
                        <select id="modules_content_admin_contents_field" name="field" class="form-select filtering-input">
                            <?php foreach (['all' => sr_t('content::ui.all.a4b69faf'), 'title' => sr_t('content::ui.text.08b17e43'), 'slug' => 'Slug'] as $fieldValue => $fieldLabel) { ?>
                                <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($filters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                                    <?php echo sr_e($fieldLabel); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="filtering-field-fill filtering-field admin-content-filter-keyword">
                        <label for="modules_content_admin_contents_q" class="filtering-label"><?php echo sr_e(sr_t('content::ui.search.bda397fc')); ?></label>
                        <input id="modules_content_admin_contents_q" type="text" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('content::ui.slug.afd81de7')); ?>">
                    </div>
                </div>
                <div id="modules_content_admin_contents_detail_filters" class="filtering-body" data-filtering-body<?php echo $contentDetailFilterOpen ? '' : ' hidden'; ?>>
                    <div class="filtering-field admin-content-filter-status">
                        <span class="filtering-label"><?php echo sr_e(sr_t('content::ui.status.e10195a1')); ?></span>
                        <?php echo sr_admin_filter_toggle_group_html('modules_content_admin_contents_status', 'status', sr_admin_code_label_options(sr_content_allowed_statuses(), 'content_status'), $selectedContentStatuses, sr_t('content::ui.all.a4b69faf')); ?>
                    </div>
                    <div class="filtering-field admin-content-filter-group">
                        <span class="filtering-label"><?php echo sr_e(sr_t('content::ui.text.5d908ddd')); ?></span>
                        <?php
                        $contentGroupFilterOptions = [];
                        foreach ($pageGroups as $pageGroup) {
                            $pageGroupId = (string) (int) ($pageGroup['id'] ?? 0);
                            if ($pageGroupId !== '0') {
                                $contentGroupFilterOptions[$pageGroupId] = (string) ($pageGroup['title'] ?? $pageGroup['group_key']);
                            }
                        }
                        $selectedContentGroupIds = (int) ($filters['content_group_id'] ?? 0) > 0 ? [(string) (int) $filters['content_group_id']] : [];
                        echo sr_admin_filter_radio_toggle_group_html('modules_content_admin_contents_content_group_id', 'content_group_id', $contentGroupFilterOptions, $selectedContentGroupIds, sr_t('content::ui.all.a4b69faf'));
                        ?>
                    </div>
                </div>
                <div class="filtering-actions">
                    <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $contentDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="modules_content_admin_contents_detail_filters">상세검색</button>
                    <button type="button" class="btn btn-outline-light" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
                    <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('content::ui.search.4b8d541e')); ?></button>
                </div>
            </div>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?php echo sr_e(sr_t('content::ui.content.list.771ca9aa')); ?></h2>
                <p class="admin-dashboard-meta"><?php echo sr_e(sr_t('content::ui.status.content.slug.d9329b0b')); ?></p>
            </div>
            <a href="<?php echo sr_e(sr_url('/admin/content/new' . ((int) ($filters['content_group_id'] ?? 0) > 0 ? '?content_group_id=' . rawurlencode((string) (int) $filters['content_group_id']) : ''))); ?>" class="btn btn-sm btn-outline-secondary"><?php echo sr_e(sr_t('content::ui.content.530929bb')); ?></a>
        </div>
        <div class="admin-content-list-summary">
            <?php if (empty($contentSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_content_admin_sort_url()); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-content-sort-reset" aria-label="콘텐츠 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($pagePagination); ?>
        </div>
        <div class="table-wrapper">
            <table class="table admin-content-table">
                <caption class="sr-only"><?php echo sr_e(sr_t('content::ui.content.list.771ca9aa')); ?></caption>
                <thead class="ui-table-head">
                    <tr>
                        <th<?php echo sr_content_admin_sort_aria('title', $contentSort); ?>><?php echo sr_content_admin_sort_header_html(sr_t('content::ui.text.08b17e43'), 'title', $contentSort); ?></th>
                        <th<?php echo sr_content_admin_sort_aria('content_group', $contentSort); ?>><?php echo sr_content_admin_sort_header_html(sr_t('content::ui.text.5d908ddd'), 'content_group', $contentSort); ?></th>
                        <th><?php echo sr_e('시리즈'); ?></th>
                        <th<?php echo sr_content_admin_sort_aria('slug', $contentSort); ?>><?php echo sr_content_admin_sort_header_html('Slug', 'slug', $contentSort); ?></th>
                        <th<?php echo sr_content_admin_sort_aria('status', $contentSort); ?>><?php echo sr_content_admin_sort_header_html(sr_t('content::ui.status.e10195a1'), 'status', $contentSort); ?></th>
                        <th<?php echo sr_content_admin_sort_aria('asset_access', $contentSort); ?>><?php echo sr_content_admin_sort_header_html(sr_t('content::ui.text.c9b3e6f0'), 'asset_access', $contentSort); ?></th>
                        <th<?php echo sr_content_admin_sort_aria('created_by', $contentSort); ?>><?php echo sr_content_admin_sort_header_html(sr_t('content::ui.text.f2ee20a7'), 'created_by', $contentSort); ?></th>
                        <th<?php echo sr_content_admin_sort_aria('updated_at', $contentSort); ?>><?php echo sr_content_admin_sort_header_html(sr_t('content::ui.edit.d3a98476'), 'updated_at', $contentSort); ?></th>
                        <th<?php echo sr_content_admin_sort_aria('published_at', $contentSort); ?>><?php echo sr_content_admin_sort_header_html(sr_t('content::ui.text.84b7c221'), 'published_at', $contentSort); ?></th>
                        <th class="text-end"><?php echo sr_e(sr_t('content::ui.text.29ae8f30')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($pages === []) { ?>
                        <tr>
                            <td colspan="10" class="admin-empty-state"><?php echo sr_e(sr_t('content::ui.create.content.8994ccd1')); ?></td>
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
                                <td class="admin-table-nowrap">
                                    <?php if ((string) ($page['content_series_title'] ?? '') !== '') { ?>
                                        <?php echo sr_e((string) $page['content_series_title']); ?>
                                        <?php if ((string) ($page['content_series_episode_label'] ?? '') !== '') { ?>
                                            <span class="admin-dashboard-meta"><?php echo sr_e((string) $page['content_series_episode_label']); ?></span>
                                        <?php } ?>
                                    <?php } ?>
                                </td>
                                <td class="admin-table-nowrap admin-content-slug-cell"><code><?php echo sr_e((string) $page['slug']); ?></code></td>
                                <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($pageStatus, 'content_status')); ?></span></td>
                                <td>
                                    <?php if ((int) ($page['asset_access_enabled'] ?? 0) === 1) { ?>
                                        <?php echo sr_e(sr_content_asset_module_labels((string) ($page['asset_module'] ?? ''), $pdo)); ?>
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
                                        <?php if (in_array((string) $page['status'], ['published', 'draft', 'scheduled'], true)) { ?>
                                            <a href="<?php echo sr_e(sr_url(sr_content_path((string) $page['slug']))); ?>" class="btn btn-sm btn-icon btn-solid-light" target="_blank" rel="noopener noreferrer" aria-label="<?php echo sr_e(sr_t('content::ui.text.ac5b575f')); ?>" title="<?php echo sr_e(sr_t('content::ui.text.ac5b575f')); ?>"><?php echo sr_material_icon_html('visibility'); ?></a>
                                        <?php } ?>
                                        <a href="<?php echo sr_e(sr_url('/admin/content/edit?id=' . rawurlencode((string) $page['id']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('content::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('content::ui.edit.3537f0cc')); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
                                        <?php
                                        $contentCopyModalId = 'content-copy-modal-' . (string) (int) $page['id'];
                                        $contentCopyModals .= $contentCopyModalHtml($page, (string) ($_SERVER['REQUEST_URI'] ?? '/admin/content'));
                                        ?>
                                        <button type="button" class="btn btn-sm btn-icon btn-solid-light" aria-label="<?php echo sr_e('복사'); ?>" title="<?php echo sr_e('복사'); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($contentCopyModalId); ?>" data-overlay="#<?php echo sr_e($contentCopyModalId); ?>"><?php echo sr_material_icon_html('content_copy'); ?></button>
                                        <?php if ((string) $page['status'] !== 'hidden') { ?>
                                            <form method="post" action="<?php echo sr_e(sr_url('/admin/content/delete')); ?>" class="admin-inline-form">
                                                <?php echo sr_csrf_field(); ?>
                                                <input type="hidden" name="content_id" value="<?php echo sr_e((string) $page['id']); ?>">
                                                <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="<?php echo sr_e(sr_t('content::ui.text.0eeb676f')); ?>" title="<?php echo sr_e(sr_t('content::ui.text.0eeb676f')); ?>"><?php echo sr_material_icon_html('delete'); ?></button>
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
    <?php echo $contentCopyModals; ?>
    <?php echo sr_admin_pagination_html($pagePagination, '콘텐츠 목록 페이지'); ?>
<?php } ?>

<?php if ($pageAdminPage === 'form') { ?>
    <?php foreach ($contentHelp as $contentHelpModal) { ?>
        <?php echo sr_admin_help_modal_html((string) $contentHelpModal['id'], (string) $contentHelpModal['title'], (string) $contentHelpModal['body']); ?>
    <?php } ?>
<?php } ?>

<?php if ($pageAdminPage === 'form') { ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var groupSelect = document.querySelector('[data-content-group-select]');
        var scopeOptions = document.querySelectorAll('[data-content-group-scope-option]');
        var sourceOptions = document.querySelectorAll('input[name^="source_"]');
        var requiredLabel = document.querySelector('[data-content-group-required]');
        var statusSelect = document.querySelector('[data-content-status-select]');
        var scheduledInput = document.querySelector('[data-content-scheduled-input]');
        var scheduledRequiredLabel = document.querySelector('[data-content-scheduled-required]');
        var selectFallbackOption = function (option, fallbackValue) {
            var fallback = document.querySelector('input[name="' + option.name + '"][value="' + fallbackValue + '"]');
            if (fallback) {
                fallback.checked = true;
            }
        };
        var syncDisabledLook = function (option) {
            var label = option && option.closest ? option.closest('.admin-form-check') : null;
            if (label) {
                label.classList.toggle('is-disabled-look', option.disabled);
            }
        };
        var syncGroupScope = function () {
            if (!groupSelect || scopeOptions.length === 0) {
                return;
            }
            var hasGroup = groupSelect.value !== '0';
            Array.prototype.slice.call(scopeOptions).forEach(function (option) {
                if (option.value !== 'group') {
                    return;
                }
                option.disabled = !hasGroup;
                syncDisabledLook(option);
                if (!hasGroup && option.checked) {
                    selectFallbackOption(option, 'here_only');
                }
            });
            Array.prototype.slice.call(sourceOptions).forEach(function (option) {
                if (option.value !== 'group') {
                    return;
                }
                option.disabled = !hasGroup;
                syncDisabledLook(option);
                if (!hasGroup && option.checked) {
                    selectFallbackOption(option, 'content');
                }
            });
            var selectedScope = document.querySelector('[data-content-group-scope-option]:checked');
            var useGroup = selectedScope && selectedScope.value === 'group';
            var useGroupSource = Array.prototype.slice.call(sourceOptions).some(function (option) {
                return option.checked && option.value === 'group';
            });
            var needsGroup = useGroup || useGroupSource;
            groupSelect.required = needsGroup;
            if (requiredLabel) {
                requiredLabel.hidden = !needsGroup;
            }
        };

        scopeOptions.forEach(function (option) {
            option.addEventListener('change', syncGroupScope);
        });
        sourceOptions.forEach(function (option) {
            option.addEventListener('change', syncGroupScope);
        });
        if (groupSelect) {
            groupSelect.addEventListener('change', syncGroupScope);
        }
        var syncScheduledPublishAt = function () {
            if (!statusSelect || !scheduledInput) {
                return;
            }
            var needsSchedule = statusSelect.value === 'scheduled';
            scheduledInput.required = needsSchedule;
            if (scheduledRequiredLabel) {
                scheduledRequiredLabel.hidden = !needsSchedule;
            }
        };
        if (statusSelect) {
            statusSelect.addEventListener('change', syncScheduledPublishAt);
        }
        syncGroupScope();
        syncScheduledPublishAt();
    });
    </script>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
