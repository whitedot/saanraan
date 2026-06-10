<?php

$adminPageTitle = sr_t('site_menu::ui.menu.766fbd09');
$adminContainerClass = 'admin-page-site-menu admin-ui-scope';
include SR_ROOT . '/modules/admin/views/layout-header.php';

$siteMenuParentOptions = static function (int $menuId, int $selectedParentId = 0, int $excludeItemId = 0) use ($items, $itemDepths): void {
    ?>
    <option value="0"<?php echo $selectedParentId <= 0 ? ' selected' : ''; ?>><?php echo sr_e(sr_t('site_menu::ui.text.d8b14d7a')); ?></option>
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
    $assetTypeLabel = (string) ($asset['asset_type_label'] ?? sr_t('site_menu::ui.text.3d54da9c'));
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
    <option value=""<?php echo $selectedModuleKey === '' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('site_menu::ui.text.eb613054')); ?></option>
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
    <option value=""><?php echo sr_e(sr_t('site_menu::ui.select.635a3c01')); ?></option>
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
    <option value=""><?php echo sr_e(sr_t('site_menu::ui.select.a33d6c70')); ?></option>
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
    <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('site_menu::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($modalId); ?>">
        <?php echo sr_material_icon_html('close', '', sr_t('site_menu::ui.close.1e8c1020')); ?>
    </button>
    <?php
};

$siteMenuRenderMenuModal = static function (string $modalId, string $title, ?array $menu = null) use ($allowedStatuses, $siteMenuModalCloseButton): void {
    $editingMenu = is_array($menu);
    $menuKey = $editingMenu ? (string) ($menu['menu_key'] ?? '') : '';
    $label = $editingMenu ? (string) ($menu['label'] ?? '') : '';
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
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_menu_key"><?php echo sr_e(sr_t('site_menu::ui.menu.key.20cd5d6a')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('site_menu::ui.required.1f227c67')); ?></span></label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e($modalId); ?>_menu_key" type="text" name="menu_key" value="<?php echo sr_e($menuKey); ?>" class="form-input" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-key-input data-overlay-focus>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_label"><?php echo sr_e(sr_t('site_menu::ui.menu.name.0615c5f4')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('site_menu::ui.required.1f227c67')); ?></span></label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e($modalId); ?>_label" type="text" name="label" value="<?php echo sr_e($label); ?>" class="form-input form-control-full" maxlength="120" required>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_status"><?php echo sr_e(sr_t('site_menu::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('site_menu::ui.required.1f227c67')); ?></span></label>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e($modalId); ?>_status" name="status" class="form-select" required>
                                <?php foreach ($allowedStatuses as $status) { ?>
                                    <option value="<?php echo sr_e($status); ?>"<?php echo $statusValue === $status ? ' selected' : ''; ?>>
                                        <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer-note">
                    <p class="admin-form-help">이 모달의 저장 버튼은 메뉴 정보만 저장합니다. 목록에서 작성 중인 정렬 값은 함께 저장되지 않습니다.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>"><?php echo sr_e(sr_t('site_menu::ui.close.1e8c1020')); ?></button>
                    <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('site_menu::ui.menu.save.f55aafc2')); ?></button>
                </div>
            </form>
        </div>
    </div>
    <?php
};

$siteMenuRenderItemModal = static function (string $modalId, string $title, int $menuId, int $parentId = 0, ?array $item = null, int $defaultSortOrder = 100) use ($allowedStatuses, $allowedTargets, $siteMenuIconOptions, $siteMenuModalCloseButton, $siteMenuParentOptions, $siteMenuSelectedAssetKeys, $siteMenuModuleOptions, $siteMenuAssetTypeOptions, $siteMenuAssetOptions): void {
    $editingItem = is_array($item);
    $itemId = $editingItem ? (int) ($item['id'] ?? 0) : 0;
    $itemMenuId = $editingItem ? (int) ($item['menu_id'] ?? $menuId) : $menuId;
    $itemParentId = $editingItem ? (int) ($item['parent_id'] ?? 0) : $parentId;
    $label = $editingItem ? (string) ($item['label'] ?? '') : '';
    $url = $editingItem ? (string) ($item['url'] ?? '/') : '/';
    $iconName = $editingItem ? (string) ($item['icon_name'] ?? '') : '';
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
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_module"><?php echo sr_e(sr_t('site_menu::ui.text.06aff97f')); ?></label>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e($modalId); ?>_module" class="form-select" data-site-menu-module-select data-overlay-focus>
                                <?php $siteMenuModuleOptions($selectedModuleKey); ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_asset_type"><?php echo sr_e(sr_t('site_menu::ui.text.75c3bd09')); ?></label>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e($modalId); ?>_asset_type" class="form-select" data-site-menu-asset-type-select>
                                <?php $siteMenuAssetTypeOptions($selectedModuleKey, $selectedAssetType); ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_asset"><?php echo sr_e(sr_t('site_menu::ui.text.ea61edcb')); ?></label>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e($modalId); ?>_asset" class="form-select" data-site-menu-asset-select>
                                <?php $siteMenuAssetOptions($selectedModuleKey, $selectedAssetType, $url); ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_parent_id"><?php echo sr_e(sr_t('site_menu::ui.text.6ab1927c')); ?></label>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e($modalId); ?>_parent_id" name="parent_id" class="form-select">
                                <?php $siteMenuParentOptions($itemMenuId, $itemParentId, $itemId); ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_label"><?php echo sr_e(sr_t('site_menu::ui.name.661e423c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('site_menu::ui.required.1f227c67')); ?></span></label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e($modalId); ?>_label" type="text" name="label" value="<?php echo sr_e($label); ?>" class="form-input form-control-full" maxlength="120" required data-site-menu-label-input>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_url">URL <span class="sr-required-label"><?php echo sr_e(sr_t('site_menu::ui.required.1f227c67')); ?></span></label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e($modalId); ?>_url" type="text" name="url" value="<?php echo sr_e($url); ?>" class="form-input form-control-full" maxlength="255" required data-site-menu-url-input>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_icon_name"><?php echo sr_e(sr_t('site_menu::ui.icon.8b29d6ef')); ?></label>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e($modalId); ?>_icon_name" name="icon_name" class="form-select">
                                <option value=""<?php echo $iconName === '' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('site_menu::ui.icon.none.9445f03f')); ?></option>
                                <?php foreach ($siteMenuIconOptions as $optionIconName => $_enabled) { ?>
                                    <option value="<?php echo sr_e((string) $optionIconName); ?>"<?php echo $iconName === (string) $optionIconName ? ' selected' : ''; ?>><?php echo sr_e((string) $optionIconName); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_target"><?php echo sr_e(sr_t('site_menu::ui.text.5235ffd9')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('site_menu::ui.required.1f227c67')); ?></span></label>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e($modalId); ?>_target" name="target" class="form-select" required>
                                <?php foreach ($allowedTargets as $target) { ?>
                                    <option value="<?php echo sr_e($target); ?>"<?php echo $targetValue === $target ? ' selected' : ''; ?>>
                                        <?php echo sr_e(sr_admin_code_label($target, 'menu_target')); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_status"><?php echo sr_e(sr_t('site_menu::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('site_menu::ui.required.1f227c67')); ?></span></label>
                        <div class="admin-form-field">
                            <select id="<?php echo sr_e($modalId); ?>_status" name="status" class="form-select" required>
                                <?php foreach ($allowedStatuses as $status) { ?>
                                    <option value="<?php echo sr_e($status); ?>"<?php echo $statusValue === $status ? ' selected' : ''; ?>>
                                        <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_sort_order"><?php echo sr_e(sr_t('site_menu::ui.text.3788952d')); ?></label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e($modalId); ?>_sort_order" type="number" name="sort_order" value="<?php echo sr_e((string) $sortOrder); ?>" class="form-input">
                        </div>
                    </div>
                </div>
                <div class="modal-footer-note">
                    <p class="admin-form-help">이 모달의 저장 버튼은 메뉴 항목 정보만 저장합니다. 목록에서 작성 중인 정렬 값은 함께 저장되지 않습니다.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>"><?php echo sr_e(sr_t('site_menu::ui.close.1e8c1020')); ?></button>
                    <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('site_menu::ui.save.964f6f83')); ?></button>
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
            <h2 class="card-title"><?php echo sr_e(sr_t('site_menu::ui.menu.5b2bf65a')); ?></h2>
            <p class="admin-dashboard-meta"><?php echo sr_e(sr_t('site_menu::ui.menu.3ddcbf35')); ?></p>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="site_menu_add_menu_modal" data-overlay="#site_menu_add_menu_modal"><?php echo sr_e(sr_t('site_menu::ui.menu.ba050327')); ?></button>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th><?php echo sr_e(sr_t('site_menu::ui.text.83b651b8')); ?></th>
                    <th><?php echo sr_e(sr_t('site_menu::ui.text.2281025b')); ?></th>
                    <th><?php echo sr_e(sr_t('site_menu::ui.text.8c609deb')); ?></th>
                    <th>URL</th>
                    <th><?php echo sr_e(sr_t('site_menu::ui.status.e10195a1')); ?></th>
                    <th class="admin-menu-sort-order-cell"><?php echo sr_e(sr_t('site_menu::ui.text.ff0e602e')); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('site_menu::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($menuRows === []) { ?>
                    <tr>
                        <td colspan="7" class="admin-empty-state"><?php echo sr_e(sr_t('site_menu::ui.create.menu.12031c65')); ?></td>
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
                                <td><span class="admin-menu-scope-badge admin-menu-scope-category"><?php echo sr_e(sr_t('site_menu::ui.menu.13b36d6d')); ?></span></td>
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
                                <td class="admin-menu-sort-order-cell"></td>
                                <td class="admin-table-actions-cell">
                                    <div class="admin-row-actions">
                                        <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($addItemModalId); ?>" data-overlay="#<?php echo sr_e($addItemModalId); ?>"><?php echo sr_e(sr_t('site_menu::ui.text.2c54ca2d')); ?></button>
                                        <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('site_menu::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('site_menu::ui.edit.3537f0cc')); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($menuModalId); ?>" data-overlay="#<?php echo sr_e($menuModalId); ?>"><?php echo sr_material_icon_html('edit'); ?></button>
                                        <form method="post" action="<?php echo sr_e(sr_url('/admin/site-menus')); ?>">
                                            <?php echo sr_csrf_field(); ?>
                                            <input type="hidden" name="intent" value="delete_menu">
                                            <input type="hidden" name="menu_id" value="<?php echo sr_e((string) $menuId); ?>">
                                            <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="<?php echo sr_e(sr_t('site_menu::ui.delete.6139b6c3')); ?>" title="<?php echo sr_e(sr_t('site_menu::ui.delete.6139b6c3')); ?>"><?php echo sr_material_icon_html('delete'); ?></button>
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
                                <td><span class="admin-drag-handle" draggable="true" aria-label="<?php echo sr_e(sr_t('site_menu::ui.text.baef0d03')); ?>"><?php echo sr_material_icon_html('apps', 'admin-drag-handle-icon'); ?></span></td>
                                <td><span class="admin-menu-scope-badge admin-menu-scope-item"><?php echo sr_e((string) $rowDepth); ?><?php echo sr_e(sr_t('site_menu::ui.text.29ee1bb7')); ?></span></td>
                                <td class="admin-menu-target-cell">
                                    <div class="admin-menu-target admin-menu-target-depth-<?php echo sr_e((string) $rowDepth); ?>">
                                        <span class="admin-menu-tree-branch" aria-hidden="true"></span>
                                        <span class="admin-menu-target-copy">
                                            <span class="admin-menu-target-label">
                                                <?php if (trim((string) ($row['icon_name'] ?? '')) !== '' && sr_site_menu_icon_allowed($pdo, (string) $row['icon_name'])) { ?>
                                                    <?php echo sr_icon(sr_admin_icon_material_name($pdo, (string) $row['icon_name']), 'admin-site-menu-target-icon'); ?>
                                                <?php } ?>
                                                <span><?php echo sr_e((string) $row['label']); ?></span>
                                            </span>
                                            <span class="admin-menu-target-context"><?php echo sr_e((string) $row['menu_key']); ?></span>
                                        </span>
                                    </div>
                                </td>
                                <td class="admin-table-break"><?php echo sr_e((string) $row['url']); ?></td>
                                <td><span class="admin-status <?php echo (string) $row['status'] === 'enabled' ? 'is-normal' : 'is-left'; ?>"><?php echo sr_e(sr_admin_code_label((string) $row['status'], 'content_status')); ?></span></td>
                                <td class="admin-menu-sort-order-cell">
                                    <input type="number" name="item_sort_order[<?php echo sr_e((string) $itemId); ?>]" value="<?php echo sr_e((string) $row['sort_order']); ?>" form="site-menu-order-form" data-admin-sort-order class="form-input admin-menu-sort-order-input">
                                </td>
                                <td class="admin-table-actions-cell">
                                    <div class="admin-row-actions">
                                        <?php if ($rowDepth < 3) { ?>
                                            <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($childAddModalId); ?>" data-overlay="#<?php echo sr_e($childAddModalId); ?>"><?php echo sr_e(sr_t('site_menu::ui.text.8d136d31')); ?></button>
                                        <?php } ?>
                                        <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('site_menu::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('site_menu::ui.edit.3537f0cc')); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($itemEditModalId); ?>" data-overlay="#<?php echo sr_e($itemEditModalId); ?>"><?php echo sr_material_icon_html('edit'); ?></button>
                                        <form method="post" action="<?php echo sr_e(sr_url('/admin/site-menus')); ?>">
                                            <?php echo sr_csrf_field(); ?>
                                            <input type="hidden" name="intent" value="delete_item">
                                            <input type="hidden" name="item_id" value="<?php echo sr_e((string) $itemId); ?>">
                                            <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="<?php echo sr_e(sr_t('site_menu::ui.delete.6139b6c3')); ?>" title="<?php echo sr_e(sr_t('site_menu::ui.delete.6139b6c3')); ?>"><?php echo sr_material_icon_html('delete'); ?></button>
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
        <p class="admin-form-help">순서 적용하기는 목록의 정렬 값만 저장합니다. 열려 있는 메뉴/항목 모달 입력값은 함께 저장되지 않습니다.</p>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('site_menu::ui.save.cc86610d')); ?></button>
    </form>
</section>

<?php $siteMenuRenderMenuModal('site_menu_add_menu_modal', sr_t('site_menu::ui.menu.ba050327')); ?>
<?php foreach ($menus as $menu) { ?>
    <?php
    $menuId = (int) $menu['id'];
    $siteMenuRenderMenuModal('site_menu_edit_menu_' . $menuId, sr_t('site_menu::ui.menu.edit.c61bd0a4'), $menu);
    $siteMenuRenderItemModal('site_menu_add_item_menu_' . $menuId, sr_t('site_menu::ui.text.2c54ca2d'), $menuId, 0, null, (int) ($menuParentNextSortOrders[$menuId][0] ?? 100));
    ?>
<?php } ?>
<?php foreach ($items as $item) { ?>
    <?php
    $itemId = (int) $item['id'];
    $itemDepth = (int) ($itemDepths[$itemId] ?? 1);
    $siteMenuRenderItemModal('site_menu_edit_item_' . $itemId, sr_t('site_menu::ui.edit.e6e14581'), (int) $item['menu_id'], (int) ($item['parent_id'] ?? 0), $item);
    if ($itemDepth < 3) {
        $siteMenuRenderItemModal('site_menu_add_child_' . $itemId, sr_t('site_menu::ui.text.56b2723f'), (int) $item['menu_id'], $itemId, null, (int) ($menuParentNextSortOrders[(int) $item['menu_id']][$itemId] ?? 100));
    }
    ?>
<?php } ?>

<script src="<?php echo sr_e(sr_admin_asset_url('/modules/site_menu/assets/admin.js')); ?>" defer></script>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
