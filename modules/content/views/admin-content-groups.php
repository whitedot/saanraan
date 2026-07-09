<?php

$adminPageTitle = $pageGroupsPage === 'list' ? '콘텐츠 그룹 관리' : ($pageGroupsPage === 'edit' ? sr_t('content::ui.content.edit.700b7706') : sr_t('content::ui.content.5a50b240'));
$adminPageSubtitle = '';
$adminContainerClass = $pageGroupsPage === 'list' ? 'admin-content-group-list admin-ui-scope' : 'admin-content-group-form admin-ui-scope';
$contentGroupFormPage = $pageGroupsPage !== 'list';
$pageGroupFilters = isset($pageGroupFilters) && is_array($pageGroupFilters) ? $pageGroupFilters : ['status' => '', 'field' => 'all', 'q' => ''];
$pageGroupSort = isset($pageGroupSort) && is_array($pageGroupSort) ? $pageGroupSort : sr_content_admin_group_default_sort();
$pageGroupStatusCounts = isset($pageGroupStatusCounts) && is_array($pageGroupStatusCounts) ? $pageGroupStatusCounts : [];
$allowedGroupStatuses = isset($allowedGroupStatuses) && is_array($allowedGroupStatuses) ? $allowedGroupStatuses : sr_content_group_statuses();
$adminPageTitleUrl = sr_admin_page_title_reset_url($pageGroupsPage === 'list', '/admin/content-groups');
$publicLayoutOptions = isset($publicLayoutOptions) && is_array($publicLayoutOptions) ? $publicLayoutOptions : sr_public_layout_options($pdo ?? null);
$reactionPresetOptions = isset($reactionPresetOptions) && is_array($reactionPresetOptions) ? $reactionPresetOptions : ['' => '리액션 기본값'];
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
                            <?php foreach (['all' => sr_t('content::ui.all.a4b69faf'), 'key' => sr_t('content::ui.key.1057ecca'), 'title' => sr_t('content::ui.name.253d1510')] as $fieldValue => $fieldLabel) { ?>
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
                    <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
                    <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('content::ui.search.4b8d541e')); ?></button>
                </div>
            </div>
        </div>
    </form>

    <section class="card admin-list-card admin-list-form">
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
            <form id="content-group-bulk-status-form" method="post" action="<?php echo sr_e(sr_url('/admin/content-groups')); ?>" class="content-group-bulk-form" data-content-group-bulk-form>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="batch_status">
                <input type="hidden" name="operation_key" value="content.group_set_status">
                <div class="content-group-bulk-actions admin-row-actions" data-content-group-bulk-bar>
                    <div class="content-group-bulk-controls admin-row-actions">
                        <?php foreach ($allowedGroupStatuses as $status) { ?>
                            <button type="submit" name="target_status" value="<?php echo sr_e($status); ?>" class="btn btn-sm btn-outline-warning" data-content-group-bulk-submit data-status-label="<?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>" disabled><?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?></button>
                        <?php } ?>
                        <button type="button" class="btn btn-sm btn-outline-light" data-content-group-bulk-clear aria-label="선택 해제" title="선택 해제" hidden><?php echo sr_material_icon_html('close'); ?><span data-content-group-selected-count>0</span></button>
                    </div>
                </div>
            </form>
            <?php echo sr_admin_pagination_summary_html($pageGroupPagination); ?>
        </div>
        <div class="table-wrapper">
            <table class="table table-list admin-content-group-table">
                <caption class="sr-only"><?php echo sr_e(sr_t('content::ui.content.list.d2ad38e3')); ?></caption>
                <thead>
                    <tr>
                        <th class="admin-table-checkbox-cell content-group-select-cell">
                            <label class="sr-only" for="content_group_bulk_select_all">현재 페이지 콘텐츠 그룹 전체 선택</label>
                            <input id="content_group_bulk_select_all" type="checkbox" class="form-checkbox" data-content-group-select-all<?php echo ($pageGroups ?? []) === [] ? ' disabled' : ''; ?>>
                        </th>
                        <th<?php echo sr_admin_sort_aria('title', $pageGroupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('content::ui.name.253d1510'), 'title', $pageGroupSort, sr_content_admin_group_sort_options(), sr_content_admin_group_default_sort()); ?></th>
                        <th<?php echo sr_admin_sort_aria('group_key', $pageGroupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('content::ui.key.1057ecca'), 'group_key', $pageGroupSort, sr_content_admin_group_sort_options(), sr_content_admin_group_default_sort()); ?></th>
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
                            <td colspan="8" class="admin-empty-state"><?php echo sr_e(sr_t('content::ui.create.content.02dd9fe3')); ?></td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($pageGroups as $pageGroup) { ?>
                            <?php
                            $groupStatus = (string) ($pageGroup['status'] ?? '');
                            $statusClass = match ($groupStatus) {
                                'enabled' => 'is-success',
                                'disabled' => 'is-warning',
                                default => 'is-danger',
                            };
                            ?>
                            <tr>
                                <td class="admin-table-checkbox-cell content-group-select-cell">
                                    <label class="sr-only" for="content_group_bulk_select_<?php echo sr_e((string) (int) $pageGroup['id']); ?>"><?php echo sr_e((string) ($pageGroup['title'] ?? '')); ?> 선택</label>
                                    <input id="content_group_bulk_select_<?php echo sr_e((string) (int) $pageGroup['id']); ?>" type="checkbox" name="selected_group_ids[]" value="<?php echo sr_e((string) (int) $pageGroup['id']); ?>" class="form-checkbox" form="content-group-bulk-status-form" data-content-group-row-select>
                                </td>
                                <td class="admin-table-break"><?php echo sr_e((string) ($pageGroup['title'] ?? '')); ?></td>
                                <td class="admin-table-nowrap"><code><?php echo sr_e((string) ($pageGroup['group_key'] ?? '')); ?></code></td>
                                <td class="admin-table-nowrap"><span class="badge-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($groupStatus, 'content_status')); ?></span></td>
                                <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($pageGroup['content_count'] ?? 0))); ?></td>
                                <td class="admin-table-nowrap"><?php echo sr_e((string) ($pageGroup['sort_order'] ?? 0)); ?></td>
                                <td class="admin-table-nowrap"><?php echo sr_content_time_html((string) ($pageGroup['updated_at'] ?? '')); ?></td>
                                <td class="admin-table-actions-cell">
                                    <div class="admin-row-actions">
                                        <a href="<?php echo sr_e(sr_url('/admin/content/new?content_group_id=' . rawurlencode((string) $pageGroup['id']))); ?>" class="btn btn-sm btn-icon btn-solid-light" aria-label="이 그룹에 콘텐츠 추가" title="이 그룹에 콘텐츠 추가"><?php echo sr_material_icon_html('add'); ?></a>
                                        <?php if ((string) ($pageGroup['status'] ?? '') === 'enabled') { ?>
                                            <a href="<?php echo sr_e(sr_url(sr_content_group_path((string) $pageGroup['group_key']))); ?>" class="btn btn-sm btn-icon btn-solid-light" target="_blank" rel="noopener noreferrer" aria-label="<?php echo sr_e(sr_t('content::ui.text.ac5b575f')); ?>" title="<?php echo sr_e(sr_t('content::ui.text.ac5b575f')); ?>"><?php echo sr_material_icon_html('visibility'); ?></a>
                                        <?php } ?>
                                        <a href="<?php echo sr_e(sr_url('/admin/content-groups/edit?id=' . rawurlencode((string) $pageGroup['id']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('content::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('content::ui.edit.3537f0cc')); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
                                        <form method="post" action="<?php echo sr_e(sr_url('/admin/content-groups')); ?>" class="admin-inline-form">
                                            <?php echo sr_csrf_field(); ?>
                                            <input type="hidden" name="intent" value="delete_group">
                                            <input type="hidden" name="group_id" value="<?php echo sr_e((string) $pageGroup['id']); ?>">
                                            <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="콘텐츠 그룹 삭제" title="콘텐츠 그룹 삭제" onclick="return confirm('이 콘텐츠 그룹을 삭제할까요? 연결 콘텐츠는 삭제하지 않고 그룹 연결만 해제합니다. 외부 운영 참조가 있으면 삭제되지 않습니다.');"><?php echo sr_material_icon_html('delete'); ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('add'); ?> 이 그룹에 콘텐츠 추가</span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('visibility'); ?> <?php echo sr_e(sr_t('content::ui.text.ac5b575f')); ?></span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> <?php echo sr_e(sr_t('content::ui.edit.3537f0cc')); ?></span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('delete'); ?> 콘텐츠 그룹 삭제</span>
        </div>
        <?php echo sr_admin_status_description_list_html('content_status', sr_admin_code_label_options(['enabled', 'disabled'], 'content_status')); ?>
    </section>
    <?php echo sr_admin_pagination_html($pageGroupPagination, '콘텐츠 그룹 목록 페이지'); ?>
    <?php $contentStorageCleanupFailures = is_array($contentStorageCleanupFailures ?? null) ? $contentStorageCleanupFailures : []; ?>
    <?php if ($contentStorageCleanupFailures !== []) { ?>
        <section class="card admin-list-card admin-list-form">
            <div class="card-header">
                <div>
                    <h2 class="card-title">저장소 정리 실패</h2>
                    <p class="admin-dashboard-meta">콘텐츠 삭제 후 남은 파일 정리 대상입니다.</p>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="table table-list">
                    <caption class="sr-only">콘텐츠 저장소 정리 실패 목록</caption>
                    <thead>
                        <tr>
                            <th>대상</th>
                            <th>저장소</th>
                            <th>시도</th>
                            <th>오류</th>
                            <th class="text-end">작업</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contentStorageCleanupFailures as $cleanupFailure) { ?>
                            <tr>
                                <td class="admin-table-nowrap"><?php echo sr_e((string) ($cleanupFailure['source_type'] ?? '')); ?> #<?php echo sr_e((string) (int) ($cleanupFailure['source_id'] ?? 0)); ?></td>
                                <td class="admin-table-break"><code><?php echo sr_e((string) ($cleanupFailure['storage_driver'] ?? 'local')); ?>:<?php echo sr_e((string) ($cleanupFailure['storage_key'] ?? '')); ?></code></td>
                                <td class="admin-table-nowrap"><?php echo sr_e((string) (int) ($cleanupFailure['attempt_count'] ?? 0)); ?>회</td>
                                <td class="admin-table-break"><?php echo sr_e((string) ($cleanupFailure['last_error'] ?? '')); ?></td>
                                <td class="admin-table-actions-cell">
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/content-groups')); ?>" class="admin-inline-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="retry_storage_cleanup_failure">
                                        <input type="hidden" name="failure_id" value="<?php echo sr_e((string) (int) ($cleanupFailure['id'] ?? 0)); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">재시도</button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php } ?>
<?php } else { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/content-groups')); ?>" class="admin-form ui-form-theme">
        <section id="content-group-section-basic" class="card" data-admin-section-anchor>
            <h2><?php echo sr_e($editing ? sr_t('content::ui.content.edit.700b7706') : sr_t('content::ui.content.5a50b240')); ?></h2>
            <p class="form-help"><?php echo sr_e('콘텐츠 그룹은 여러 콘텐츠를 묶어 공개 목록과 사이트 메뉴 후보로 관리하는 운영 단위입니다. 독자가 순서대로 읽는 연재 흐름은 콘텐츠 시리즈를 사용하세요.'); ?></p>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="<?php echo $editing ? 'update_group' : 'create_group'; ?>">
            <input type="hidden" name="group_id" value="<?php echo $editing ? sr_e((string) $editPageGroup['id']) : '0'; ?>">
            <div class="form-row">
                <label class="form-label" for="content_admin_groups_group_key"><?php echo sr_e(sr_t('content::ui.key.1057ecca')); ?><?php echo $editing ? '' : sr_t('content::ui.span.class.sr.required.label.07a9346b'); ?></label>
                <div class="form-field">
                    <?php if ($editing) { ?>
                        <code><?php echo sr_e((string) ($values['group_key'] ?? '')); ?></code>
                    <?php } else { ?>
                        <input id="content_admin_groups_group_key" type="text" name="group_key" value="<?php echo sr_e((string) ($values['group_key'] ?? '')); ?>" class="form-input" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-key-input data-admin-key-suggest-source="#content_admin_groups_title" data-admin-key-suggest-fallback="content_group">
                        <p class="form-help"><?php echo sr_e(sr_t('content::ui.active.bd86f3a1')); ?></p>
                    <?php } ?>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="content_admin_groups_title"><?php echo sr_e(sr_t('content::ui.name.253d1510')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('content::ui.required.1f227c67')); ?></span></label>
                <div class="form-field">
                    <input id="content_admin_groups_title" type="text" name="title" value="<?php echo sr_e((string) ($values['title'] ?? '')); ?>" class="form-input form-control-full" maxlength="120" required>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="content_admin_groups_description"><?php echo sr_e(sr_t('content::ui.text.8c3f651d')); ?></label>
                <div class="form-field">
                    <textarea id="content_admin_groups_description" name="description" rows="4" cols="60" class="form-textarea"><?php echo sr_e((string) ($values['description'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="content_admin_groups_status"><?php echo sr_e(sr_t('content::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('content::ui.required.1f227c67')); ?></span></label>
                <div class="form-field">
                    <select id="content_admin_groups_status" name="status" class="form-select">
                        <?php foreach ($allowedGroupStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($values['status'] ?? 'enabled') === $status ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="content_admin_groups_sort_order"><?php echo sr_e(sr_t('content::ui.text.7d2dc215')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('content::ui.required.1f227c67')); ?></span></label>
                <div class="form-field">
                    <input id="content_admin_groups_sort_order" type="number" name="sort_order" min="0" max="1000000" value="<?php echo sr_e((string) (int) ($values['sort_order'] ?? 0)); ?>" required class="form-input">
                </div>
            </div>
        </section>
        <?php if ($editing) { ?>
            <?php $contentGroupDeleteModalId = 'content-group-delete-modal'; ?>
        <?php } ?>
        <div class="form-sticky-actions form-actions form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/content-groups')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('content::ui.list.f07b3200')); ?></a>
            <?php if ($editing) { ?>
                <button type="button" class="btn btn-outline-danger" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($contentGroupDeleteModalId); ?>" data-overlay="#<?php echo sr_e($contentGroupDeleteModalId); ?>">삭제</button>
            <?php } ?>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('content::ui.save.5fb92622')); ?></button>
        </div>
    </form>
    <?php if ($editing) { ?>
        <?php $contentGroupDeleteCheck = sr_content_can_delete_group($pdo, (int) ($editPageGroup['id'] ?? 0)); ?>
        <div id="<?php echo sr_e($contentGroupDeleteModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($contentGroupDeleteModalId); ?>-label" aria-hidden="true" inert>
            <div class="modal-dialog">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/content-groups')); ?>" class="modal-content admin-form ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($contentGroupDeleteModalId); ?>-label" class="modal-title">콘텐츠 그룹 삭제</h3>
                        <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($contentGroupDeleteModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="intent" value="delete_group">
                        <input type="hidden" name="group_id" value="<?php echo sr_e((string) ($editPageGroup['id'] ?? 0)); ?>">
                        <p class="form-help">
                            콘텐츠 그룹을 삭제하면 그룹 정보가 삭제되고,
                            연결 콘텐츠 <?php echo sr_e((string) (int) ($contentGroupDeleteCheck['references']['contents'] ?? 0)); ?>건은 삭제하지 않고 그룹 연결만 해제됩니다.
                            댓글 <?php echo sr_e((string) (int) ($contentGroupDeleteCheck['references']['comments'] ?? 0)); ?>건,
                            파일 <?php echo sr_e((string) (int) ($contentGroupDeleteCheck['references']['files'] ?? 0)); ?>건,
                            revision snapshot 참조 <?php echo sr_e((string) (int) ($contentGroupDeleteCheck['references']['revision_references'] ?? 0)); ?>건,
                            외부 참조 <?php echo sr_e((string) array_sum(array_map('intval', is_array($contentGroupDeleteCheck['external_references'] ?? null) ? $contentGroupDeleteCheck['external_references'] : []))); ?>건.
                            현재 편집 중인 변경사항은 삭제 실행 전에 저장되지 않습니다.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($contentGroupDeleteModalId); ?>">닫기</button>
                        <button type="submit" class="btn btn-outline-danger modal-action">삭제</button>
                    </div>
                </form>
            </div>
        </div>
    <?php } ?>
<?php } ?>

<?php if ($pageGroupsPage === 'list') { ?>
<script>
(function () {
    var bulkForm = document.querySelector('[data-content-group-bulk-form]');
    if (!bulkForm) {
        return;
    }

    var countNode = document.querySelector('[data-content-group-selected-count]');
    var submitButtons = Array.prototype.slice.call(document.querySelectorAll('[data-content-group-bulk-submit]'));
    var clear = document.querySelector('[data-content-group-bulk-clear]');
    var selectAll = document.querySelector('[data-content-group-select-all]');
    var rowChecks = Array.prototype.slice.call(document.querySelectorAll('[data-content-group-row-select]'));

    var checkedRows = function () {
        return rowChecks.filter(function (input) {
            return input.checked && !input.disabled;
        });
    };

    var syncBulkState = function () {
        var selectedCount = checkedRows().length;
        if (countNode) {
            countNode.textContent = String(selectedCount);
        }
        submitButtons.forEach(function (button) {
            button.disabled = selectedCount < 1;
        });
        if (clear) {
            clear.hidden = selectedCount < 1;
        }
        if (selectAll) {
            selectAll.checked = selectedCount > 0 && selectedCount === rowChecks.length;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < rowChecks.length;
        }
    };

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowChecks.forEach(function (input) {
                if (!input.disabled) {
                    input.checked = selectAll.checked;
                }
            });
            syncBulkState();
        });
    }
    rowChecks.forEach(function (input) {
        input.addEventListener('change', syncBulkState);
    });
    if (clear) {
        clear.addEventListener('click', function () {
            rowChecks.forEach(function (input) {
                input.checked = false;
            });
            syncBulkState();
        });
    }
    bulkForm.addEventListener('submit', function (event) {
        var selectedCount = checkedRows().length;
        if (selectedCount < 1) {
            event.preventDefault();
            syncBulkState();
            return;
        }
        var submitter = event.submitter || document.activeElement;
        var statusLabel = submitter && submitter.getAttribute ? submitter.getAttribute('data-status-label') : '';
        if (!statusLabel) {
            statusLabel = submitter && submitter.textContent ? submitter.textContent.replace(/\s+/g, ' ').trim() : '선택한 상태';
        }
        if (!window.confirm('선택한 콘텐츠 그룹 ' + selectedCount + '건의 상태를 "' + statusLabel + '"(으)로 변경합니다.')) {
            event.preventDefault();
        }
    });
    syncBulkState();
})();
</script>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
