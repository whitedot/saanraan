<?php

$adminPageTitle = '사이트 메뉴';
$adminContainerClass = 'admin-page-site-menu admin-ui-scope';
include SR_ROOT . '/modules/admin/views/layout-header.php';

$siteMenuParentOptions = static function (int $menuId, int $selectedParentId = 0, int $excludeItemId = 0) use ($items, $itemDepths): void {
    ?>
    <option value="0"<?php echo $selectedParentId <= 0 ? ' selected' : ''; ?>>최상위</option>
    <?php foreach ($items as $parentOption) { ?>
        <?php
        $parentOptionMenuId = (int) ($parentOption['menu_id'] ?? 0);
        $parentOptionId = (int) ($parentOption['id'] ?? 0);
        $parentOptionDepth = (int) ($itemDepths[$parentOptionId] ?? 1);
        if ($parentOptionMenuId !== $menuId || $parentOptionId === $excludeItemId || $parentOptionDepth >= 3) {
            continue;
        }
        $prefix = str_repeat('- ', max(0, $parentOptionDepth - 1));
        ?>
        <option value="<?php echo sr_e((string) $parentOptionId); ?>"<?php echo $selectedParentId === $parentOptionId ? ' selected' : ''; ?>>
            <?php echo sr_e($prefix . (string) $parentOption['label']); ?>
        </option>
    <?php } ?>
    <?php
};

$siteMenuModuleAssetGroups = [];
$siteMenuModuleLabel = static function (string $moduleKey): string {
    $metadata = sr_module_metadata($moduleKey);
    $moduleName = is_string($metadata['name'] ?? null) && (string) $metadata['name'] !== ''
        ? (string) $metadata['name']
        : $moduleKey;

    return function_exists('sr_admin_module_name_label') ? sr_admin_module_name_label($moduleName) : $moduleName;
};
foreach (sr_enabled_module_contract_files($pdo, 'menu-links.php', ['site_menu']) as $moduleKey => $_menuLinksFile) {
    if (!isset($siteMenuModuleAssetGroups[$moduleKey])) {
        $siteMenuModuleAssetGroups[$moduleKey] = [
            'label' => $siteMenuModuleLabel((string) $moduleKey),
            'types' => [],
        ];
    }
}
foreach ($menuLinkSuggestions as $asset) {
    $moduleKey = (string) ($asset['module_key'] ?? '');
    $assetType = (string) ($asset['asset_type'] ?? 'link');
    $assetTypeLabel = (string) ($asset['asset_type_label'] ?? '링크');
    $assetLabel = (string) ($asset['label'] ?? '');
    $assetUrl = (string) ($asset['url'] ?? '');
    if ($moduleKey === '' || $assetType === '' || $assetTypeLabel === '' || $assetLabel === '' || $assetUrl === '') {
        continue;
    }
    if (!isset($siteMenuModuleAssetGroups[$moduleKey])) {
        $siteMenuModuleAssetGroups[$moduleKey] = [
            'label' => $siteMenuModuleLabel($moduleKey),
            'types' => [],
        ];
    }
    if (!isset($siteMenuModuleAssetGroups[$moduleKey]['types'][$assetType])) {
        $siteMenuModuleAssetGroups[$moduleKey]['types'][$assetType] = [
            'label' => $assetTypeLabel,
            'assets' => [],
        ];
    }
    $siteMenuModuleAssetGroups[$moduleKey]['types'][$assetType]['assets'][] = [
        'label' => $assetLabel,
        'url' => $assetUrl,
    ];
}

$siteMenuSelectedAssetKeys = static function (string $selectedUrl) use ($siteMenuModuleAssetGroups): array {
    if ($selectedUrl === '') {
        return ['', ''];
    }

    foreach ($siteMenuModuleAssetGroups as $moduleKey => $moduleData) {
        $assetTypes = is_array($moduleData['types'] ?? null) ? $moduleData['types'] : [];
        foreach ($assetTypes as $assetType => $assetTypeData) {
            foreach ($assetTypeData['assets'] ?? [] as $asset) {
                if ((string) ($asset['url'] ?? '') === $selectedUrl) {
                    return [(string) $moduleKey, (string) $assetType];
                }
            }
        }
    }

    return ['', ''];
};

$siteMenuModuleOptions = static function (string $selectedModuleKey = '') use ($siteMenuModuleAssetGroups): void {
    ?>
    <option value=""<?php echo $selectedModuleKey === '' ? ' selected' : ''; ?>>직접 입력</option>
    <?php foreach ($siteMenuModuleAssetGroups as $moduleKey => $moduleData) { ?>
        <?php $moduleLabel = (string) ($moduleData['label'] ?? $moduleKey); ?>
        <option value="<?php echo sr_e((string) $moduleKey); ?>"<?php echo $selectedModuleKey === (string) $moduleKey ? ' selected' : ''; ?>>
            <?php echo sr_e($moduleLabel); ?>
        </option>
    <?php } ?>
    <?php
};

$siteMenuAssetTypeOptions = static function (string $selectedModuleKey = '', string $selectedAssetType = '') use ($siteMenuModuleAssetGroups): void {
    ?>
    <option value="">종류 선택</option>
    <?php foreach ($siteMenuModuleAssetGroups as $moduleKey => $moduleData) { ?>
        <?php $assetTypes = is_array($moduleData['types'] ?? null) ? $moduleData['types'] : []; ?>
        <?php foreach ($assetTypes as $assetType => $assetTypeData) { ?>
            <?php $typeLabel = (string) ($assetTypeData['label'] ?? $assetType); ?>
            <option
                value="<?php echo sr_e((string) $assetType); ?>"
                data-site-menu-asset-module="<?php echo sr_e((string) $moduleKey); ?>"
                <?php echo $selectedModuleKey === (string) $moduleKey && $selectedAssetType === (string) $assetType ? ' selected' : ''; ?>>
                <?php echo sr_e($typeLabel); ?>
            </option>
        <?php } ?>
    <?php } ?>
    <?php
};

$siteMenuAssetOptions = static function (string $selectedModuleKey = '', string $selectedAssetType = '', string $selectedUrl = '') use ($siteMenuModuleAssetGroups): void {
    $selectedAssigned = false;
    ?>
    <option value="">자산 선택</option>
    <?php foreach ($siteMenuModuleAssetGroups as $moduleKey => $moduleData) { ?>
        <?php $assetTypes = is_array($moduleData['types'] ?? null) ? $moduleData['types'] : []; ?>
        <?php foreach ($assetTypes as $assetType => $assetTypeData) { ?>
            <?php foreach ($assetTypeData['assets'] ?? [] as $asset) { ?>
                <?php
                $assetLabel = (string) ($asset['label'] ?? '');
                $assetUrl = (string) ($asset['url'] ?? '');
                if ($assetLabel === '' || $assetUrl === '') {
                    continue;
                }
                $assetValue = (string) $moduleKey . '|' . (string) $assetType . '|' . $assetUrl;
                $isSelected = $selectedModuleKey === (string) $moduleKey && $selectedAssetType === (string) $assetType && $selectedUrl === $assetUrl && !$selectedAssigned;
                if ($isSelected) {
                    $selectedAssigned = true;
                }
                ?>
                <option
                    value="<?php echo sr_e($assetValue); ?>"
                    data-site-menu-asset-module="<?php echo sr_e((string) $moduleKey); ?>"
                    data-site-menu-asset-type="<?php echo sr_e((string) $assetType); ?>"
                    data-site-menu-asset-label="<?php echo sr_e($assetLabel); ?>"
                    data-site-menu-asset-url="<?php echo sr_e($assetUrl); ?>"
                    <?php echo $isSelected ? ' selected' : ''; ?>>
                    <?php echo sr_e($assetLabel . ' · ' . $assetUrl); ?>
                </option>
            <?php } ?>
        <?php } ?>
    <?php } ?>
    <?php
};

$siteMenuModalCloseButton = static function (string $modalId): void {
    ?>
    <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($modalId); ?>">
        <?php echo sr_material_icon_html('close', '', '닫기'); ?>
    </button>
    <?php
};

$siteMenuRenderMenuModal = static function (string $modalId, string $title, ?array $menu = null) use ($allowedStatuses, $siteMenuModalCloseButton): void {
    $editingMenu = is_array($menu);
    $menuKey = $editingMenu ? (string) ($menu['menu_key'] ?? '') : 'header';
    $label = $editingMenu ? (string) ($menu['label'] ?? '') : '헤더 메뉴';
    $statusValue = $editingMenu ? (string) ($menu['status'] ?? 'enabled') : 'enabled';
    ?>
    <div id="<?php echo sr_e($modalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($modalId); ?>_title" aria-hidden="true" inert>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/site-menus')); ?>" class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($modalId); ?>_title" class="modal-title"><?php echo sr_e($title); ?></h3>
                    <?php $siteMenuModalCloseButton($modalId); ?>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="save_menu">
                    <input type="hidden" name="original_menu_key" value="<?php echo $editingMenu ? sr_e($menuKey) : ''; ?>">
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_menu_key">메뉴 key</label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e($modalId); ?>_menu_key" type="text" name="menu_key" value="<?php echo sr_e($menuKey); ?>" class="form-input" maxlength="60" required data-overlay-focus>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_label">메뉴 이름</label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e($modalId); ?>_label" type="text" name="label" value="<?php echo sr_e($label); ?>" class="form-input form-control-full" maxlength="120" required>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_status">상태</label>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e($modalId); ?>_status" name="status" class="form-select">
                                <?php foreach ($allowedStatuses as $status) { ?>
                                    <option value="<?php echo sr_e($status); ?>"<?php echo $statusValue === $status ? ' selected' : ''; ?>>
                                        <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>">닫기</button>
                    <button type="submit" class="btn btn-solid-primary modal-action">메뉴 저장</button>
                </div>
            </form>
        </div>
    </div>
    <?php
};

$siteMenuRenderItemModal = static function (string $modalId, string $title, int $menuId, int $parentId = 0, ?array $item = null, int $defaultSortOrder = 100) use ($allowedStatuses, $allowedTargets, $siteMenuModalCloseButton, $siteMenuParentOptions, $siteMenuSelectedAssetKeys, $siteMenuModuleOptions, $siteMenuAssetTypeOptions, $siteMenuAssetOptions): void {
    $editingItem = is_array($item);
    $itemId = $editingItem ? (int) ($item['id'] ?? 0) : 0;
    $itemMenuId = $editingItem ? (int) ($item['menu_id'] ?? $menuId) : $menuId;
    $itemParentId = $editingItem ? (int) ($item['parent_id'] ?? 0) : $parentId;
    $label = $editingItem ? (string) ($item['label'] ?? '') : '';
    $url = $editingItem ? (string) ($item['url'] ?? '/') : '/';
    $targetValue = $editingItem ? (string) ($item['target'] ?? 'self') : 'self';
    $statusValue = $editingItem ? (string) ($item['status'] ?? 'enabled') : 'enabled';
    $sortOrder = $editingItem ? (int) ($item['sort_order'] ?? $defaultSortOrder) : $defaultSortOrder;
    [$selectedModuleKey, $selectedAssetType] = $siteMenuSelectedAssetKeys($url);
    ?>
    <div id="<?php echo sr_e($modalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($modalId); ?>_title" aria-hidden="true" inert>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/site-menus')); ?>" class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($modalId); ?>_title" class="modal-title"><?php echo sr_e($title); ?></h3>
                    <?php $siteMenuModalCloseButton($modalId); ?>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="save_item">
                    <input type="hidden" name="item_id" value="<?php echo sr_e((string) $itemId); ?>">
                    <input type="hidden" name="menu_id" value="<?php echo sr_e((string) $itemMenuId); ?>">
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_module">서비스</label>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e($modalId); ?>_module" class="form-select" data-site-menu-module-select data-overlay-focus>
                                <?php $siteMenuModuleOptions($selectedModuleKey); ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_asset_type">대상 종류</label>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e($modalId); ?>_asset_type" class="form-select" data-site-menu-asset-type-select>
                                <?php $siteMenuAssetTypeOptions($selectedModuleKey, $selectedAssetType); ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_asset">연결 자산</label>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e($modalId); ?>_asset" class="form-select" data-site-menu-asset-select>
                                <?php $siteMenuAssetOptions($selectedModuleKey, $selectedAssetType, $url); ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_parent_id">상위 항목</label>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e($modalId); ?>_parent_id" name="parent_id" class="form-select">
                                <?php $siteMenuParentOptions($itemMenuId, $itemParentId, $itemId); ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_label">항목 이름</label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e($modalId); ?>_label" type="text" name="label" value="<?php echo sr_e($label); ?>" class="form-input form-control-full" maxlength="120" required data-site-menu-label-input>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_url">URL</label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e($modalId); ?>_url" type="text" name="url" value="<?php echo sr_e($url); ?>" class="form-input form-control-full" maxlength="255" required data-site-menu-url-input>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_target">링크 대상</label>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e($modalId); ?>_target" name="target" class="form-select">
                                <?php foreach ($allowedTargets as $target) { ?>
                                    <option value="<?php echo sr_e($target); ?>"<?php echo $targetValue === $target ? ' selected' : ''; ?>>
                                        <?php echo sr_e(sr_admin_code_label($target, 'menu_target')); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_status">상태</label>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e($modalId); ?>_status" name="status" class="form-select">
                                <?php foreach ($allowedStatuses as $status) { ?>
                                    <option value="<?php echo sr_e($status); ?>"<?php echo $statusValue === $status ? ' selected' : ''; ?>>
                                        <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_sort_order">정렬</label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e($modalId); ?>_sort_order" type="number" name="sort_order" value="<?php echo sr_e((string) $sortOrder); ?>" class="form-input">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>">닫기</button>
                    <button type="submit" class="btn btn-solid-primary modal-action">항목 저장</button>
                </div>
            </form>
        </div>
    </div>
    <?php
};
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<section class="admin-card admin-list-card card admin-list-form admin-site-menu-form">
    <div class="card-header">
        <div>
            <h2 class="card-title">사이트 메뉴 구성</h2>
            <p class="admin-dashboard-meta">메뉴 묶음과 항목을 한 화면에서 관리합니다. 항목은 최대 3단계까지 구성할 수 있습니다.</p>
        </div>
        <button type="button" class="btn btn-sm btn-solid-primary" aria-haspopup="dialog" aria-expanded="false" aria-controls="site_menu_add_menu_modal" data-overlay="#site_menu_add_menu_modal">메뉴 추가</button>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>이동</th>
                    <th>범위</th>
                    <th>대상</th>
                    <th>URL</th>
                    <th>상태</th>
                    <th>표시 순서</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($menuRows === []) { ?>
                    <tr>
                        <td colspan="7" class="admin-empty-state">등록된 메뉴가 없습니다.</td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($menuRows as $row) { ?>
                        <?php if ((string) ($row['row_type'] ?? '') === 'menu') { ?>
                            <?php
                            $menuId = (int) $row['id'];
                            $menuModalId = 'site_menu_edit_menu_' . $menuId;
                            $addItemModalId = 'site_menu_add_item_menu_' . $menuId;
                            ?>
                            <tr class="admin-menu-row admin-menu-row-depth-0">
                                <td></td>
                                <td><span class="admin-menu-scope-badge admin-menu-scope-category">메뉴</span></td>
                                <td class="admin-menu-target-cell">
                                    <div class="admin-menu-target admin-menu-target-depth-0">
                                        <span class="admin-menu-target-copy">
                                            <span class="admin-menu-target-label"><?php echo sr_e((string) $row['label']); ?></span>
                                            <span class="admin-menu-target-context"><?php echo sr_e((string) $row['menu_key']); ?></span>
                                        </span>
                                    </div>
                                </td>
                                <td></td>
                                <td><span class="admin-status <?php echo (string) $row['status'] === 'enabled' ? 'is-normal' : 'is-left'; ?>"><?php echo sr_e(sr_admin_code_label((string) $row['status'], 'content_status')); ?></span></td>
                                <td></td>
                                <td class="admin-table-actions-cell">
                                    <div class="admin-row-actions">
                                        <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($addItemModalId); ?>" data-overlay="#<?php echo sr_e($addItemModalId); ?>">항목 추가</button>
                                        <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($menuModalId); ?>" data-overlay="#<?php echo sr_e($menuModalId); ?>">수정</button>
                                        <form method="post" action="<?php echo sr_e(sr_url('/admin/site-menus')); ?>">
                                            <?php echo sr_csrf_field(); ?>
                                            <input type="hidden" name="intent" value="delete_menu">
                                            <input type="hidden" name="menu_id" value="<?php echo sr_e((string) $menuId); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } else { ?>
                            <?php
                            $itemId = (int) $row['id'];
                            $rowDepth = max(1, min(3, (int) ($row['depth'] ?? 1)));
                            $itemEditModalId = 'site_menu_edit_item_' . $itemId;
                            $childAddModalId = 'site_menu_add_child_' . $itemId;
                            ?>
                            <tr class="admin-menu-row admin-menu-row-depth-<?php echo sr_e((string) $rowDepth); ?>" data-admin-sortable-row data-sort-scope="site_menu_<?php echo sr_e((string) $row['menu_id']); ?>" data-sort-parent="<?php echo sr_e((string) ((int) ($row['parent_id'] ?? 0))); ?>" data-sort-key="<?php echo sr_e((string) $itemId); ?>" data-sort-depth="<?php echo sr_e((string) $rowDepth); ?>">
                                <td><span class="admin-drag-handle" draggable="true" aria-label="드래그해서 순서 변경"><?php echo sr_material_icon_html('apps', 'admin-drag-handle-icon'); ?></span></td>
                                <td><span class="admin-menu-scope-badge admin-menu-scope-item"><?php echo sr_e((string) $rowDepth); ?>단계</span></td>
                                <td class="admin-menu-target-cell">
                                    <div class="admin-menu-target admin-menu-target-depth-<?php echo sr_e((string) $rowDepth); ?>">
                                        <span class="admin-menu-tree-branch" aria-hidden="true"></span>
                                        <span class="admin-menu-target-copy">
                                            <span class="admin-menu-target-label"><?php echo sr_e((string) $row['label']); ?></span>
                                            <span class="admin-menu-target-context"><?php echo sr_e((string) $row['menu_key']); ?></span>
                                        </span>
                                    </div>
                                </td>
                                <td class="admin-table-break"><?php echo sr_e((string) $row['url']); ?></td>
                                <td><span class="admin-status <?php echo (string) $row['status'] === 'enabled' ? 'is-normal' : 'is-left'; ?>"><?php echo sr_e(sr_admin_code_label((string) $row['status'], 'content_status')); ?></span></td>
                                <td>
                                    <input type="number" name="item_sort_order[<?php echo sr_e((string) $itemId); ?>]" value="<?php echo sr_e((string) $row['sort_order']); ?>" form="site-menu-order-form" data-admin-sort-order class="form-input">
                                </td>
                                <td class="admin-table-actions-cell">
                                    <div class="admin-row-actions">
                                        <?php if ($rowDepth < 3) { ?>
                                            <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($childAddModalId); ?>" data-overlay="#<?php echo sr_e($childAddModalId); ?>">하위 추가</button>
                                        <?php } ?>
                                        <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($itemEditModalId); ?>" data-overlay="#<?php echo sr_e($itemEditModalId); ?>">수정</button>
                                        <form method="post" action="<?php echo sr_e(sr_url('/admin/site-menus')); ?>">
                                            <?php echo sr_csrf_field(); ?>
                                            <input type="hidden" name="intent" value="delete_item">
                                            <input type="hidden" name="item_id" value="<?php echo sr_e((string) $itemId); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <form id="site-menu-order-form" method="post" action="<?php echo sr_e(sr_url('/admin/site-menus')); ?>" class="admin-form-actions admin-form-sticky-actions admin-site-menu-form-actions">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="save_item_order">
        <button type="submit" class="btn btn-solid-primary">표시 순서 저장</button>
    </form>
</section>

<?php $siteMenuRenderMenuModal('site_menu_add_menu_modal', '메뉴 추가'); ?>
<?php foreach ($menus as $menu) { ?>
    <?php
    $menuId = (int) $menu['id'];
    $siteMenuRenderMenuModal('site_menu_edit_menu_' . $menuId, '메뉴 수정', $menu);
    $siteMenuRenderItemModal('site_menu_add_item_menu_' . $menuId, '항목 추가', $menuId, 0, null, (int) ($menuParentNextSortOrders[$menuId][0] ?? 100));
    ?>
<?php } ?>
<?php foreach ($items as $item) { ?>
    <?php
    $itemId = (int) $item['id'];
    $itemDepth = (int) ($itemDepths[$itemId] ?? 1);
    $siteMenuRenderItemModal('site_menu_edit_item_' . $itemId, '항목 수정', (int) $item['menu_id'], (int) ($item['parent_id'] ?? 0), $item);
    if ($itemDepth < 3) {
        $siteMenuRenderItemModal('site_menu_add_child_' . $itemId, '하위 항목 추가', (int) $item['menu_id'], $itemId, null, (int) ($menuParentNextSortOrders[(int) $item['menu_id']][$itemId] ?? 100));
    }
    ?>
<?php } ?>

<script src="<?php echo sr_e(sr_admin_asset_url('/modules/site_menu/assets/admin.js')); ?>" defer></script>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
