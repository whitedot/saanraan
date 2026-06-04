<?php

$adminPageTitle = $pageGroupsPage === 'list' ? '콘텐츠 그룹 관리' : ($pageGroupsPage === 'edit' ? sr_t('content::ui.content.edit.700b7706') : sr_t('content::ui.content.5a50b240'));
$adminPageSubtitle = $pageGroupsPage === 'list' ? sr_t('content::ui.content.status.group.6193db1c') : sr_t('content::ui.content.list.menu.active.b056b4c2');
$adminContainerClass = $pageGroupsPage === 'list' ? 'admin-content-group-list admin-ui-scope' : 'admin-content-group-form admin-ui-scope';
$contentGroupFormPage = $pageGroupsPage !== 'list';
$pageGroupFilters = isset($pageGroupFilters) && is_array($pageGroupFilters) ? $pageGroupFilters : ['status' => '', 'field' => 'all', 'q' => ''];
$pageGroupSort = isset($pageGroupSort) && is_array($pageGroupSort) ? $pageGroupSort : sr_content_admin_group_default_sort();
$pageGroupStatusCounts = isset($pageGroupStatusCounts) && is_array($pageGroupStatusCounts) ? $pageGroupStatusCounts : [];
$allowedGroupStatuses = isset($allowedGroupStatuses) && is_array($allowedGroupStatuses) ? $allowedGroupStatuses : sr_content_group_statuses();
$publicLayoutOptions = isset($publicLayoutOptions) && is_array($publicLayoutOptions) ? $publicLayoutOptions : sr_public_layout_options($pdo ?? null);
$editing = is_array($editPageGroup ?? null);
$contentGroupAssetAuditUrl = $editing ? sr_admin_asset_settings_audit_url('content_group.asset_settings.updated', 'content_group', (string) (int) ($editPageGroup['id'] ?? 0)) : '';
if (!is_array($values ?? null) || $values === []) {
    $values = $editing ? $editPageGroup : [
        'group_key' => '',
        'title' => '',
        'description' => '',
        'status' => 'enabled',
        'sort_order' => 0,
    ];
}
$groupSettings = is_array($values['group_settings'] ?? null)
    ? $values['group_settings']
    : ($editing && is_array($pageGroupSettings ?? null) ? $pageGroupSettings : sr_content_group_default_settings($site ?? null, $pdo ?? null));
$groupSettingValue = static function (array $settings, string $key, string $default): string {
    return (string) ($settings[$key] ?? $default);
};
$assetModuleOptions = isset($assetModuleOptions) && is_array($assetModuleOptions) ? $assetModuleOptions : [];
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
$totalPageGroups = (int) ($pageGroupStatusCounts['total'] ?? count($pageGroups ?? []));
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($pageGroupsPage === 'list') { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/content-groups')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('content::ui.all.e078b14a')); ?></a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('content::ui.text.ca286213')); ?> <strong><?php echo sr_e((string) $totalPageGroups); ?><?php echo sr_e(sr_t('content::ui.text.a57ab057')); ?></strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/content-groups?status=enabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('content::ui.active.93c558d7')); ?> <?php echo sr_e((string) ($pageGroupStatusCounts['enabled'] ?? 0)); ?><?php echo sr_e(sr_t('content::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/content-groups?status=disabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('content::ui.text.92cdef3c')); ?> <?php echo sr_e((string) ($pageGroupStatusCounts['disabled'] ?? 0)); ?><?php echo sr_e(sr_t('content::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/content-groups?status=archived')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('content::ui.text.2e4099ba')); ?> <?php echo sr_e((string) ($pageGroupStatusCounts['archived'] ?? 0)); ?><?php echo sr_e(sr_t('content::ui.text.a57ab057')); ?></a>
        </div>
    </div>

    <?php
    $selectedGroupStatuses = is_array($pageGroupFilters['status'] ?? null) ? $pageGroupFilters['status'] : [];
    $contentGroupDetailFilterOpen = $selectedGroupStatuses !== [];
    ?>
    <form method="get" action="<?php echo sr_e(sr_url('/admin/content-groups')); ?>" class="filtering-form admin-content-group-filter ui-form-theme">
        <div class="filtering-fields admin-content-group-search-grid admin-content-filter-stack">
            <div class="filtering filtering-card<?php echo $contentGroupDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
                <div class="filtering-fields">
                    <div class="filtering-field">
                        <label for="content_admin_groups_field" class="filtering-label">검색조건</label>
                        <select id="content_admin_groups_field" name="field" class="form-select filtering-input">
                            <?php foreach (['all' => sr_t('content::ui.all.a4b69faf'), 'key' => 'key', 'title' => sr_t('content::ui.name.253d1510')] as $fieldValue => $fieldLabel) { ?>
                                <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($pageGroupFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                                    <?php echo sr_e($fieldLabel); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="filtering-field-fill filtering-field">
                        <label for="content_admin_groups_q" class="filtering-label"><?php echo sr_e(sr_t('content::ui.search.bda397fc')); ?></label>
                        <input id="content_admin_groups_q" type="text" name="q" value="<?php echo sr_e((string) ($pageGroupFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('content::ui.key.name.7852e80c')); ?>">
                    </div>
                </div>
                <div id="content_admin_groups_detail_filters" class="filtering-body" data-filtering-body<?php echo $contentGroupDetailFilterOpen ? '' : ' hidden'; ?>>
                    <div class="filtering-field">
                        <span class="filtering-label"><?php echo sr_e(sr_t('content::ui.status.e10195a1')); ?></span>
                        <?php echo sr_admin_filter_toggle_group_html('content_admin_groups_status_filter', 'status', sr_admin_code_label_options($allowedGroupStatuses, 'content_status'), $selectedGroupStatuses, sr_t('content::ui.all.a4b69faf')); ?>
                    </div>
                </div>
                <div class="filtering-actions">
                    <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $contentGroupDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="content_admin_groups_detail_filters">상세검색</button>
                    <button type="button" class="btn btn-outline-light" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
                    <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('content::ui.search.4b8d541e')); ?></button>
                </div>
            </div>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?php echo sr_e(sr_t('content::ui.content.list.d2ad38e3')); ?></h2>
                <p class="admin-dashboard-meta"><?php echo sr_e(sr_t('content::ui.active.status.content.group.key.0f2cd28c')); ?></p>
                <p class="admin-dashboard-meta"><?php echo sr_e('콘텐츠 그룹은 운영 묶음과 기본값을 관리합니다. 연재 순서와 회차 내비게이션은 콘텐츠 시리즈에서 관리합니다.'); ?></p>
            </div>
            <a href="<?php echo sr_e(sr_url('/admin/content-groups/new')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo sr_e(sr_t('content::ui.text.6de46476')); ?></a>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($pageGroupSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url(sr_content_admin_group_sort_options(), sr_content_admin_group_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="콘텐츠 그룹 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($pageGroupPagination); ?>
        </div>
        <div class="table-wrapper">
            <table class="table admin-content-group-table">
                <caption class="sr-only"><?php echo sr_e(sr_t('content::ui.content.list.d2ad38e3')); ?></caption>
                <thead class="ui-table-head">
                    <tr>
                        <th<?php echo sr_admin_sort_aria('title', $pageGroupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('content::ui.name.253d1510'), 'title', $pageGroupSort, sr_content_admin_group_sort_options(), sr_content_admin_group_default_sort()); ?></th>
                        <th<?php echo sr_admin_sort_aria('group_key', $pageGroupSort); ?>><?php echo sr_admin_sort_header_html('Key', 'group_key', $pageGroupSort, sr_content_admin_group_sort_options(), sr_content_admin_group_default_sort()); ?></th>
                        <th<?php echo sr_admin_sort_aria('status', $pageGroupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('content::ui.status.e10195a1'), 'status', $pageGroupSort, sr_content_admin_group_sort_options(), sr_content_admin_group_default_sort()); ?></th>
                        <th<?php echo sr_admin_sort_aria('content_count', $pageGroupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('content::ui.content.5ed1a7cd'), 'content_count', $pageGroupSort, sr_content_admin_group_sort_options(), sr_content_admin_group_default_sort()); ?></th>
                        <th<?php echo sr_admin_sort_aria('sort_order', $pageGroupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('content::ui.text.3788952d'), 'sort_order', $pageGroupSort, sr_content_admin_group_sort_options(), sr_content_admin_group_default_sort()); ?></th>
                        <th<?php echo sr_admin_sort_aria('updated_at', $pageGroupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('content::ui.edit.d3a98476'), 'updated_at', $pageGroupSort, sr_content_admin_group_sort_options(), sr_content_admin_group_default_sort()); ?></th>
                        <th class="text-end"><?php echo sr_e(sr_t('content::ui.text.29ae8f30')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($pageGroups ?? []) === []) { ?>
                        <tr>
                            <td colspan="7" class="admin-empty-state"><?php echo sr_e(sr_t('content::ui.create.content.02dd9fe3')); ?></td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($pageGroups as $pageGroup) { ?>
                            <?php
                            $groupStatus = (string) ($pageGroup['status'] ?? '');
                            $statusClass = match ($groupStatus) {
                                'enabled' => 'is-normal',
                                'disabled' => 'is-blocked',
                                default => 'is-left',
                            };
                            ?>
                            <tr>
                                <td class="admin-table-break"><?php echo sr_e((string) ($pageGroup['title'] ?? '')); ?></td>
                                <td class="admin-table-nowrap"><code><?php echo sr_e((string) ($pageGroup['group_key'] ?? '')); ?></code></td>
                                <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($groupStatus, 'content_status')); ?></span></td>
                                <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($pageGroup['content_count'] ?? 0))); ?></td>
                                <td class="admin-table-nowrap"><?php echo sr_e((string) ($pageGroup['sort_order'] ?? 0)); ?></td>
                                <td class="admin-table-nowrap"><?php echo sr_e((string) ($pageGroup['updated_at'] ?? '')); ?></td>
                                <td class="admin-table-actions-cell">
                                    <div class="admin-row-actions">
                                        <a href="<?php echo sr_e(sr_url('/admin/content/new?content_group_id=' . rawurlencode((string) $pageGroup['id']))); ?>" class="btn btn-sm btn-icon btn-solid-light" aria-label="이 그룹에 콘텐츠 추가" title="이 그룹에 콘텐츠 추가"><?php echo sr_material_icon_html('add'); ?></a>
                                        <?php if ((string) ($pageGroup['status'] ?? '') === 'enabled') { ?>
                                            <a href="<?php echo sr_e(sr_url(sr_content_group_path((string) $pageGroup['group_key']))); ?>" class="btn btn-sm btn-icon btn-solid-light" target="_blank" rel="noopener noreferrer" aria-label="<?php echo sr_e(sr_t('content::ui.text.ac5b575f')); ?>" title="<?php echo sr_e(sr_t('content::ui.text.ac5b575f')); ?>"><?php echo sr_material_icon_html('visibility'); ?></a>
                                        <?php } ?>
                                        <a href="<?php echo sr_e(sr_url('/admin/content-groups/edit?id=' . rawurlencode((string) $pageGroup['id']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('content::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('content::ui.edit.3537f0cc')); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php echo sr_admin_pagination_html($pageGroupPagination, '콘텐츠 그룹 목록 페이지'); ?>
<?php } else { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/content-groups')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo $editing ? sr_t('content::ui.content.edit.700b7706') : sr_t('content::ui.content.5a50b240'); ?></h2>
            <p class="admin-form-help"><?php echo sr_e('콘텐츠 그룹은 목록 페이지, 초기화면 후보, 새 콘텐츠 기본값, 그룹/전체 복사 범위를 위한 운영 단위입니다. 독자가 순서대로 읽는 연재 흐름은 콘텐츠 시리즈를 사용하세요.'); ?></p>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="<?php echo $editing ? 'update_group' : 'create_group'; ?>">
            <input type="hidden" name="group_id" value="<?php echo $editing ? sr_e((string) $editPageGroup['id']) : '0'; ?>">
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_groups_group_key"><?php echo sr_e(sr_t('content::ui.key.1057ecca')); ?><?php echo $editing ? '' : sr_t('content::ui.span.class.sr.required.label.07a9346b'); ?></label>
                <div class="admin-form-field">
                    <?php if ($editing) { ?>
                        <code><?php echo sr_e((string) ($values['group_key'] ?? '')); ?></code>
                    <?php } else { ?>
                        <input id="content_admin_groups_group_key" type="text" name="group_key" value="<?php echo sr_e((string) ($values['group_key'] ?? '')); ?>" class="form-input" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-key-input>
                        <p class="admin-form-help"><?php echo sr_e(sr_t('content::ui.active.bd86f3a1')); ?></p>
                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_groups_title"><?php echo sr_e(sr_t('content::ui.name.253d1510')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('content::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="content_admin_groups_title" type="text" name="title" value="<?php echo sr_e((string) ($values['title'] ?? '')); ?>" class="form-input form-control-full" maxlength="120" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_groups_description"><?php echo sr_e(sr_t('content::ui.text.8c3f651d')); ?></label>
                <div class="admin-form-field">
                    <textarea id="content_admin_groups_description" name="description" rows="4" cols="60" class="form-textarea"><?php echo sr_e((string) ($values['description'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_groups_status"><?php echo sr_e(sr_t('content::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('content::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <select id="content_admin_groups_status" name="status" class="form-select">
                        <?php foreach ($allowedGroupStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($values['status'] ?? 'enabled') === $status ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_admin_groups_sort_order"><?php echo sr_e(sr_t('content::ui.text.7d2dc215')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('content::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="content_admin_groups_sort_order" type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e((string) (int) ($values['sort_order'] ?? 0)); ?>" required class="form-input">
                </div>
            </div>
        </section>
        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('content::ui.content.settings.c384599a')); ?></h2>
            <p class="admin-form-help"><?php echo sr_e(sr_t('content::ui.group_defaults_help')); ?></p>
            <div class="admin-form-row">
                <label class="form-label" for="content_group_content_status"><?php echo sr_e(sr_t('content::ui.content.status.ff88bb94')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('content::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <select id="content_group_content_status" name="group_content_status" class="form-select">
                        <?php foreach (sr_content_allowed_statuses() as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo $groupSettingValue($groupSettings, 'status', 'draft') === $status ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_group_layout_key"><?php echo sr_e(sr_t('content::ui.content.fa985852')); ?></label>
                <div class="admin-form-field">
                    <select id="content_group_layout_key" name="group_layout_key" class="form-select">
                        <?php foreach ($publicLayoutOptions as $layoutKey => $layoutOption) { ?>
                            <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo $groupSettingValue($groupSettings, 'layout_key', sr_content_default_layout_key($pdo, $site ?? null)) === (string) $layoutKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </section>
        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('content::ui.text.0d968d36')); ?></h2>
            <?php foreach (sr_content_public_banner_setting_labels() as $bannerSettingKey => $bannerSettingLabel) { ?>
                <div class="admin-form-row">
                    <label class="form-label" for="<?php echo sr_e('content_group_' . (string) $bannerSettingKey); ?>"><?php echo sr_e((string) $bannerSettingLabel); ?></label>
                    <div class="admin-form-field">
                        <select id="<?php echo sr_e('content_group_' . (string) $bannerSettingKey); ?>" name="<?php echo sr_e('group_' . (string) $bannerSettingKey); ?>" class="form-select form-control-full">
                            <option value="0"><?php echo sr_e(sr_t('content::ui.active.4add3230')); ?></option>
                            <?php foreach ($publicBanners as $banner) { ?>
                                <option value="<?php echo sr_e((string) $banner['id']); ?>"<?php echo (int) $groupSettingValue($groupSettings, (string) $bannerSettingKey, '0') === (int) $banner['id'] ? ' selected' : ''; ?>>
                                    <?php echo sr_e((string) $banner['title']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            <?php } ?>
            <?php foreach (sr_content_public_popup_layer_setting_labels() as $popupLayerSettingKey => $popupLayerSettingLabel) { ?>
                <div class="admin-form-row">
                    <label class="form-label" for="<?php echo sr_e('content_group_' . (string) $popupLayerSettingKey); ?>"><?php echo sr_e((string) $popupLayerSettingLabel); ?></label>
                    <div class="admin-form-field">
                        <select id="<?php echo sr_e('content_group_' . (string) $popupLayerSettingKey); ?>" name="<?php echo sr_e('group_' . (string) $popupLayerSettingKey); ?>" class="form-select form-control-full">
                            <option value="0"><?php echo sr_e(sr_t('content::ui.active.4add3230')); ?></option>
                            <?php foreach ($publicPopupLayers as $popupLayer) { ?>
                                <option value="<?php echo sr_e((string) $popupLayer['id']); ?>"<?php echo (int) $groupSettingValue($groupSettings, (string) $popupLayerSettingKey, '0') === (int) $popupLayer['id'] ? ' selected' : ''; ?>>
                                    <?php echo sr_e((string) $popupLayer['title']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            <?php } ?>
        </section>
        <section class="admin-card card">
            <h2>
                <span><?php echo sr_e(sr_t('content::ui.member.4eda7ba7')); ?></span>
                <?php if ($contentGroupAssetAuditUrl !== '') { ?>
                    <span class="admin-form-actions">
                        <a href="<?php echo sr_e($contentGroupAssetAuditUrl); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e('포인트/금액 변경 이력'); ?></a>
                    </span>
                <?php } ?>
            </h2>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('content::ui.member.4eda7ba7')); ?> 사용</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="content_group_asset_access_enabled">
                        <input id="content_group_asset_access_enabled" type="checkbox" name="group_asset_access_enabled" value="1" class="form-checkbox"<?php echo in_array($groupSettingValue($groupSettings, 'asset_access_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html(sr_t('content::ui.active.923da40e')); ?>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_group_asset_charge_policy"><?php echo sr_e(sr_t('content::ui.text.86803f52')); ?></label>
                <div class="admin-form-field">
                    <select id="content_group_asset_charge_policy" name="group_asset_charge_policy" class="form-select">
                        <?php foreach (sr_content_asset_view_charge_policies() as $policyKey => $policyLabel) { ?>
                            <option value="<?php echo sr_e((string) $policyKey); ?>"<?php echo $groupSettingValue($groupSettings, 'asset_charge_policy', 'once') === (string) $policyKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $policyLabel); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_group_asset_module"><?php echo sr_e(sr_t('content::ui.member.4eda7ba7')); ?> 자산 설정</label>
                <div class="admin-form-field">
                    <div class="admin-asset-setting-target" data-admin-asset-enable-target="#content_group_asset_access_enabled" data-admin-asset-enable-submit-check="always">
                        <input id="content_group_asset_access_amount" type="hidden" name="group_asset_access_amount" value="<?php echo sr_e($groupSettingValue($groupSettings, 'asset_access_amount', '0')); ?>">
                        <?php echo sr_content_asset_grouped_amount_inputs_html('content_group_asset_access_amounts_grouped', 'group_asset_module', 'group_asset_access_amounts', $assetModuleOptions, sr_content_asset_module_keys_from_value($groupSettingValue($groupSettings, 'asset_module', '')), $groupSettingValue($groupSettings, 'asset_access_amounts_json', ''), (int) $groupSettingValue($groupSettings, 'asset_access_amount', '0'), sr_t('content::ui.text.a9f15a8b'), sr_t('content::ui.text.3e195cdd')); ?>
                    </div>
                    <p class="admin-form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_group_asset_access_policy_set_ids"><?php echo sr_e('유료 열람 회원 그룹별 적용'); ?></label>
                <div class="admin-form-field admin-policy-set-field">
                    <?php echo sr_content_asset_policy_set_checkboxes_html('content_group_asset_access_policy_set_ids', 'group_asset_access_policy_set_ids', $assetPolicySets, sr_content_asset_policy_set_ids_with_legacy($groupSettingValue($groupSettings, 'asset_access_group_policies_json', ''), (int) $groupSettingValue($groupSettings, 'asset_access_policy_set_id', '0')), 'neutral', '', '#content_group_asset_access_amounts_grouped', $pdo); ?>
                    <p class="admin-form-help">도움말: 선택한 회원 그룹별 적용이 회원의 그룹과 선택한 포인트/금액 항목에 맞는 실제 금액을 계산합니다. 세트의 계산 방식과 조정값은 콘텐츠 회원 그룹별 적용 화면에서 관리합니다.</p>
                </div>
            </div>
        </section>
        <section class="admin-card card">
            <h2>
                <span><?php echo sr_e(sr_t('content::ui.text.76faa117')); ?></span>
                <?php if ($contentGroupAssetAuditUrl !== '') { ?>
                    <span class="admin-form-actions">
                        <a href="<?php echo sr_e($contentGroupAssetAuditUrl); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e('포인트/금액 변경 이력'); ?></a>
                    </span>
                <?php } ?>
            </h2>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('content::ui.text.76faa117')); ?> 사용</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="content_group_asset_action_enabled">
                        <input id="content_group_asset_action_enabled" type="checkbox" name="group_asset_action_enabled" value="1" class="form-checkbox"<?php echo in_array($groupSettingValue($groupSettings, 'asset_action_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html(sr_t('content::ui.active.904d506b')); ?>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_group_asset_action_label"><?php echo sr_e(sr_t('content::ui.text.98fb4605')); ?></label>
                <div class="admin-form-field">
                    <input id="content_group_asset_action_label" type="text" name="group_asset_action_label" value="<?php echo sr_e($groupSettingValue($groupSettings, 'asset_action_label', sr_t('content::ui.text.727333ab'))); ?>" class="form-input" maxlength="80">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_group_asset_action_direction"><?php echo sr_e(sr_t('content::ui.text.af7873a8')); ?></label>
                <div class="admin-form-field">
                    <select id="content_group_asset_action_direction" name="group_asset_action_direction" class="form-select" data-content-action-direction>
                        <?php foreach (sr_content_asset_action_directions() as $directionKey => $directionLabel) { ?>
                            <option value="<?php echo sr_e((string) $directionKey); ?>"<?php echo $groupSettingValue($groupSettings, 'asset_action_direction', 'grant') === (string) $directionKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $directionLabel); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_group_asset_action_module"><?php echo sr_e(sr_t('content::ui.text.76faa117')); ?> 자산 설정</label>
                <div class="admin-form-field">
                    <?php $contentGroupActionAssetModules = sr_content_asset_module_keys_from_value($groupSettingValue($groupSettings, 'asset_action_module', '')); ?>
                    <div class="admin-asset-setting-target" data-admin-asset-enable-target="#content_group_asset_action_enabled" data-admin-asset-enable-submit-check="always">
                        <input id="content_group_asset_action_amount" type="hidden" name="group_asset_action_amount" value="<?php echo sr_e((string) (int) $groupSettingValue($groupSettings, 'asset_action_amount', '0')); ?>">
                        <?php echo sr_content_asset_grouped_amount_inputs_html('content_group_asset_action_amounts_grouped', 'group_asset_action_module', 'group_asset_action_amounts', $assetModuleOptions, $contentGroupActionAssetModules, $groupSettingValue($groupSettings, 'asset_action_amounts_json', ''), (int) $groupSettingValue($groupSettings, 'asset_action_amount', '0'), sr_t('content::ui.text.5c705e1a'), sr_t('content::ui.text.3e195cdd'), '#content_group_asset_action_direction', 'grant'); ?>
                    </div>
                    <p class="admin-form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_group_asset_action_policy_set_ids"><?php echo sr_e('완료 버튼 회원 그룹별 적용'); ?></label>
                <div class="admin-form-field admin-policy-set-field">
                    <?php echo sr_content_asset_policy_set_checkboxes_html('content_group_asset_action_policy_set_ids', 'group_asset_action_policy_set_ids', $assetPolicySets, sr_content_asset_policy_set_ids_with_legacy($groupSettingValue($groupSettings, 'asset_action_group_policies_json', ''), (int) $groupSettingValue($groupSettings, 'asset_action_policy_set_id', '0')), $groupSettingValue($groupSettings, 'asset_action_direction', 'grant'), '#content_group_asset_action_direction', '#content_group_asset_action_amounts_grouped', $pdo); ?>
                    <p class="admin-form-help">도움말: 선택한 회원 그룹별 적용이 회원의 그룹과 선택한 포인트/금액 항목에 맞는 실제 금액을 계산합니다. 세트의 계산 방식과 조정값은 콘텐츠 회원 그룹별 적용 화면에서 관리합니다.</p>
                </div>
            </div>
        </section>
        <section class="admin-card card">
            <h2>
                <span><?php echo sr_e(sr_t('content::ui.text.b065b16b')); ?></span>
                <?php if ($contentGroupAssetAuditUrl !== '') { ?>
                    <span class="admin-form-actions">
                        <a href="<?php echo sr_e($contentGroupAssetAuditUrl); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e('포인트/금액 변경 이력'); ?></a>
                    </span>
                <?php } ?>
            </h2>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('content::ui.text.b065b16b')); ?> 사용</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="content_group_file_asset_download_enabled">
                        <input id="content_group_file_asset_download_enabled" type="checkbox" name="group_file_asset_download_enabled" value="1" class="form-checkbox"<?php echo in_array($groupSettingValue($groupSettings, 'file_asset_download_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html(sr_t('content::ui.text.d07eab27')); ?>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_group_file_asset_charge_policy"><?php echo sr_e(sr_t('content::ui.text.51a83be4')); ?></label>
                <div class="admin-form-field">
                    <select id="content_group_file_asset_charge_policy" name="group_file_asset_charge_policy" class="form-select">
                        <?php foreach (sr_content_asset_download_charge_policies() as $policyKey => $policyLabel) { ?>
                            <option value="<?php echo sr_e((string) $policyKey); ?>"<?php echo $groupSettingValue($groupSettings, 'file_asset_charge_policy', 'once') === (string) $policyKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $policyLabel); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_group_file_asset_module"><?php echo sr_e(sr_t('content::ui.text.b065b16b')); ?> 자산 설정</label>
                <div class="admin-form-field">
                    <div class="admin-asset-setting-target" data-admin-asset-enable-target="#content_group_file_asset_download_enabled" data-admin-asset-enable-submit-check="always">
                        <input id="content_group_file_asset_download_amount" type="hidden" name="group_file_asset_download_amount" value="<?php echo sr_e($groupSettingValue($groupSettings, 'file_asset_download_amount', '0')); ?>">
                        <?php echo sr_content_asset_grouped_amount_inputs_html('content_group_file_asset_download_amounts_grouped', 'group_file_asset_module', 'group_file_asset_download_amounts', $assetModuleOptions, sr_content_asset_module_keys_from_value($groupSettingValue($groupSettings, 'file_asset_module', '')), $groupSettingValue($groupSettings, 'file_asset_download_amounts_json', ''), (int) $groupSettingValue($groupSettings, 'file_asset_download_amount', '0'), sr_t('content::ui.text.a9f15a8b'), sr_t('content::ui.text.3e195cdd')); ?>
                    </div>
                    <p class="admin-form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="content_group_file_asset_download_policy_set_ids"><?php echo sr_e('파일 회원 그룹별 적용'); ?></label>
                <div class="admin-form-field admin-policy-set-field">
                    <?php echo sr_content_asset_policy_set_checkboxes_html('content_group_file_asset_download_policy_set_ids', 'group_file_asset_download_policy_set_ids', $assetPolicySets, sr_content_asset_policy_set_ids_with_legacy($groupSettingValue($groupSettings, 'file_asset_download_group_policies_json', ''), (int) $groupSettingValue($groupSettings, 'file_asset_download_policy_set_id', '0')), 'neutral', '', '#content_group_file_asset_download_amounts_grouped', $pdo); ?>
                    <p class="admin-form-help">도움말: 선택한 회원 그룹별 적용이 회원의 그룹과 선택한 포인트/금액 항목에 맞는 실제 금액을 계산합니다. 세트의 계산 방식과 조정값은 콘텐츠 회원 그룹별 적용 화면에서 관리합니다.</p>
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/content-groups')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('content::ui.list.f07b3200')); ?></a>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('content::ui.save.5fb92622')); ?></button>
        </div>
    </form>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
