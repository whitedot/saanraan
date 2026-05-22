<?php

$adminPageTitle = $pageGroupsPage === 'list' ? '페이지 그룹' : ($pageGroupsPage === 'edit' ? '페이지 그룹 수정' : '페이지 그룹 추가');
$adminPageSubtitle = $pageGroupsPage === 'list' ? '페이지 그룹 상태와 연결된 페이지 수를 확인합니다.' : '페이지를 묶어 공개 목록과 사이트 메뉴 연결 자산으로 사용할 그룹을 관리합니다.';
$adminContainerClass = $pageGroupsPage === 'list' ? 'admin-page-group-list admin-ui-scope' : 'admin-page-group-form admin-ui-scope';
$pageGroupFilters = isset($pageGroupFilters) && is_array($pageGroupFilters) ? $pageGroupFilters : ['status' => '', 'field' => 'all', 'q' => ''];
$pageGroupStatusCounts = isset($pageGroupStatusCounts) && is_array($pageGroupStatusCounts) ? $pageGroupStatusCounts : [];
$allowedGroupStatuses = isset($allowedGroupStatuses) && is_array($allowedGroupStatuses) ? $allowedGroupStatuses : sr_page_group_statuses();
$editing = is_array($editPageGroup ?? null);
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
    : (is_array($pageGroupSettings ?? null) ? $pageGroupSettings : []);
$groupSettingValue = static function (array $settings, string $key, string $default): string {
    return (string) ($settings[$key] ?? $default);
};
$assetModuleOptions = isset($assetModuleOptions) && is_array($assetModuleOptions) ? $assetModuleOptions : [];
$assetModuleChoiceOptions = [];
foreach ($assetModuleOptions as $assetModule => $assetOption) {
    $assetModuleChoiceOptions[(string) $assetModule] = (string) ($assetOption['label'] ?? $assetModule);
}
$assetDeductionPriorityLabels = [];
foreach (sr_page_asset_deduction_order() as $assetModule) {
    if (isset($assetModuleChoiceOptions[$assetModule])) {
        $assetDeductionPriorityLabels[] = $assetModuleChoiceOptions[$assetModule];
    }
}
$assetDeductionPriorityHelp = $assetDeductionPriorityLabels !== []
    ? '차감 우선순위: ' . implode(' > ', $assetDeductionPriorityLabels)
    : '활성 자산 모듈 없음';
$totalPageGroups = (int) ($pageGroupStatusCounts['total'] ?? count($pageGroups ?? []));
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($pageGroupsPage === 'list') { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/page-groups')); ?>" class="btn btn-solid-light">전체 보기</a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta">총그룹 <strong><?php echo sr_e((string) $totalPageGroups); ?>개</strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/page-groups?status=enabled')); ?>" class="admin-summary-meta">사용 <?php echo sr_e((string) ($pageGroupStatusCounts['enabled'] ?? 0)); ?>개</a>
            <a href="<?php echo sr_e(sr_url('/admin/page-groups?status=disabled')); ?>" class="admin-summary-meta">중지 <?php echo sr_e((string) ($pageGroupStatusCounts['disabled'] ?? 0)); ?>개</a>
            <a href="<?php echo sr_e(sr_url('/admin/page-groups?status=archived')); ?>" class="admin-summary-meta">보관 <?php echo sr_e((string) ($pageGroupStatusCounts['archived'] ?? 0)); ?>개</a>
        </div>
    </div>

    <form method="get" action="<?php echo sr_e(sr_url('/admin/page-groups')); ?>" class="admin-filter admin-page-group-filter ui-form-theme">
        <div class="admin-filter-grid admin-page-group-search-grid">
            <div class="admin-filter-field">
                <label for="page_admin_groups_status_filter" class="admin-filter-label">상태</label>
                <select id="page_admin_groups_status_filter" name="status" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($pageGroupFilters['status'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                    <?php foreach ($allowedGroupStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($pageGroupFilters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field">
                <label for="page_admin_groups_field" class="admin-filter-label">검색 조건</label>
                <select id="page_admin_groups_field" name="field" class="form-select admin-filter-input">
                    <?php foreach (['all' => '전체', 'key' => 'key', 'title' => '이름'] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($pageGroupFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field">
                <label for="page_admin_groups_q" class="admin-filter-label">검색어</label>
                <input id="page_admin_groups_q" type="search" name="q" value="<?php echo sr_e((string) ($pageGroupFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="key, 이름">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <div>
                <h2 class="card-title">페이지 그룹 목록</h2>
                <p class="admin-dashboard-meta">사용 상태의 그룹은 /pages/group?key=group_key 공개 목록과 사이트 메뉴 연결 자산으로 노출됩니다.</p>
            </div>
            <a href="<?php echo sr_e(sr_url('/admin/page-groups/new')); ?>" class="btn btn-sm btn-solid-light">새 그룹 추가</a>
        </div>
        <div class="table-wrapper">
            <table class="table admin-page-group-table">
                <caption class="sr-only">페이지 그룹 목록</caption>
                <thead class="ui-table-head">
                    <tr>
                        <th>이름</th>
                        <th>Key</th>
                        <th>상태</th>
                        <th>페이지 수</th>
                        <th>정렬</th>
                        <th>수정일</th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (($pageGroups ?? []) === []) { ?>
                        <tr>
                            <td colspan="7" class="admin-empty-state">등록된 페이지 그룹이 없습니다.</td>
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
                                <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($pageGroup['page_count'] ?? 0))); ?></td>
                                <td class="admin-table-nowrap"><?php echo sr_e((string) ($pageGroup['sort_order'] ?? 0)); ?></td>
                                <td class="admin-table-nowrap"><?php echo sr_e((string) ($pageGroup['updated_at'] ?? '')); ?></td>
                                <td class="admin-table-actions-cell">
                                    <div class="admin-row-actions">
                                        <?php if ((string) ($pageGroup['status'] ?? '') === 'enabled') { ?>
                                            <a href="<?php echo sr_e(sr_url(sr_page_group_path((string) $pageGroup['group_key']))); ?>" class="btn btn-sm btn-solid-light" target="_blank" rel="noopener noreferrer">보기</a>
                                        <?php } ?>
                                        <a href="<?php echo sr_e(sr_url('/admin/page-groups/edit?id=' . rawurlencode((string) $pageGroup['id']))); ?>" class="btn btn-sm btn-solid-light">수정</a>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
<?php } else { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/page-groups')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo $editing ? '페이지 그룹 수정' : '페이지 그룹 추가'; ?></h2>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="<?php echo $editing ? 'update_group' : 'create_group'; ?>">
            <input type="hidden" name="group_id" value="<?php echo $editing ? sr_e((string) $editPageGroup['id']) : '0'; ?>">
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_groups_group_key">그룹 key</label>
                <div class="admin-form-field">
                    <?php if ($editing) { ?>
                        <code><?php echo sr_e((string) ($values['group_key'] ?? '')); ?></code>
                    <?php } else { ?>
                        <input id="page_admin_groups_group_key" type="text" name="group_key" value="<?php echo sr_e((string) ($values['group_key'] ?? '')); ?>" class="form-input" maxlength="60" required>
                        <p class="admin-form-help">영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용할 수 있습니다.</p>
                    <?php } ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_groups_title">이름</label>
                <div class="admin-form-field">
                    <input id="page_admin_groups_title" type="text" name="title" value="<?php echo sr_e((string) ($values['title'] ?? '')); ?>" class="form-input form-control-full" maxlength="120" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_groups_description">설명</label>
                <div class="admin-form-field">
                    <textarea id="page_admin_groups_description" name="description" rows="4" cols="60" class="form-textarea"><?php echo sr_e((string) ($values['description'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_groups_status">상태</label>
                <div class="admin-form-field">
                    <select id="page_admin_groups_status" name="status" class="form-select">
                        <?php foreach ($allowedGroupStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($values['status'] ?? 'enabled') === $status ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_admin_groups_sort_order">정렬 순서</label>
                <div class="admin-form-field">
                    <input id="page_admin_groups_sort_order" type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e((string) (int) ($values['sort_order'] ?? 0)); ?>" class="form-input">
                </div>
            </div>
        </section>
        <section class="admin-card card">
            <h2>그룹 기본 공개 표시</h2>
            <?php foreach (sr_page_public_banner_setting_labels() as $bannerSettingKey => $bannerSettingLabel) { ?>
                <div class="admin-form-row">
                    <label class="form-label" for="<?php echo sr_e('page_group_' . (string) $bannerSettingKey); ?>"><?php echo sr_e((string) $bannerSettingLabel); ?></label>
                    <div class="admin-form-field">
                        <select id="<?php echo sr_e('page_group_' . (string) $bannerSettingKey); ?>" name="<?php echo sr_e('group_' . (string) $bannerSettingKey); ?>" class="form-select form-control-full">
                            <option value="0">사용 안 함</option>
                            <?php foreach ($publicBanners as $banner) { ?>
                                <option value="<?php echo sr_e((string) $banner['id']); ?>"<?php echo (int) $groupSettingValue($groupSettings, (string) $bannerSettingKey, '0') === (int) $banner['id'] ? ' selected' : ''; ?>>
                                    <?php echo sr_e((string) $banner['title']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            <?php } ?>
            <?php foreach (sr_page_public_popup_layer_setting_labels() as $popupLayerSettingKey => $popupLayerSettingLabel) { ?>
                <div class="admin-form-row">
                    <label class="form-label" for="<?php echo sr_e('page_group_' . (string) $popupLayerSettingKey); ?>"><?php echo sr_e((string) $popupLayerSettingLabel); ?></label>
                    <div class="admin-form-field">
                        <select id="<?php echo sr_e('page_group_' . (string) $popupLayerSettingKey); ?>" name="<?php echo sr_e('group_' . (string) $popupLayerSettingKey); ?>" class="form-select form-control-full">
                            <option value="0">사용 안 함</option>
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
            <h2>그룹 기본 회원 자산</h2>
            <div class="admin-form-row">
                <span class="form-label">유료 열람 사용</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="page_group_asset_access_enabled">
                        <input id="page_group_asset_access_enabled" type="checkbox" name="group_asset_access_enabled" value="1" class="form-checkbox"<?php echo in_array($groupSettingValue($groupSettings, 'asset_access_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html('유료 열람 사용'); ?>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_group_asset_module">차감 자산</label>
                <div class="admin-form-field">
                    <?php echo sr_admin_checkbox_list_html('page_group_asset_module', 'group_asset_module', $assetModuleChoiceOptions, sr_page_asset_module_keys_from_value($groupSettingValue($groupSettings, 'asset_module', 'point')), '활성 자산 모듈 없음'); ?>
                    <p class="admin-form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_group_asset_access_amount">차감 금액</label>
                <div class="admin-form-field">
                    <input id="page_group_asset_access_amount" type="number" name="group_asset_access_amount" value="<?php echo sr_e($groupSettingValue($groupSettings, 'asset_access_amount', '0')); ?>" class="form-input" min="0" max="999999999" step="1">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_group_asset_charge_policy">과금 방식</label>
                <div class="admin-form-field">
                    <select id="page_group_asset_charge_policy" name="group_asset_charge_policy" class="form-select">
                        <?php foreach (sr_page_asset_view_charge_policies() as $policyKey => $policyLabel) { ?>
                            <option value="<?php echo sr_e((string) $policyKey); ?>"<?php echo $groupSettingValue($groupSettings, 'asset_charge_policy', 'once') === (string) $policyKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $policyLabel); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">완료 액션 사용</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="page_group_asset_action_enabled">
                        <input id="page_group_asset_action_enabled" type="checkbox" name="group_asset_action_enabled" value="1" class="form-checkbox"<?php echo in_array($groupSettingValue($groupSettings, 'asset_action_enabled', '0'), ['1', 'true', 'yes', 'on'], true) ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html('완료 액션 사용'); ?>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_group_asset_action_label">버튼 문구</label>
                <div class="admin-form-field">
                    <input id="page_group_asset_action_label" type="text" name="group_asset_action_label" value="<?php echo sr_e($groupSettingValue($groupSettings, 'asset_action_label', '완료')); ?>" class="form-input" maxlength="80">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_group_asset_action_direction">처리 방향</label>
                <div class="admin-form-field">
                    <select id="page_group_asset_action_direction" name="group_asset_action_direction" class="form-select">
                        <?php foreach (sr_page_asset_action_directions() as $directionKey => $directionLabel) { ?>
                            <option value="<?php echo sr_e((string) $directionKey); ?>"<?php echo $groupSettingValue($groupSettings, 'asset_action_direction', 'grant') === (string) $directionKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $directionLabel); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_group_asset_action_module">대상 자산</label>
                <div class="admin-form-field">
                    <?php echo sr_admin_checkbox_list_html('page_group_asset_action_module', 'group_asset_action_module', $assetModuleChoiceOptions, sr_page_asset_module_keys_from_value($groupSettingValue($groupSettings, 'asset_action_module', 'point')), '활성 자산 모듈 없음'); ?>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="page_group_asset_action_amount">금액</label>
                <div class="admin-form-field">
                    <input id="page_group_asset_action_amount" type="number" name="group_asset_action_amount" value="<?php echo sr_e($groupSettingValue($groupSettings, 'asset_action_amount', '0')); ?>" class="form-input" min="0" max="999999999" step="1">
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/page-groups')); ?>" class="btn btn-solid-light">목록</a>
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
