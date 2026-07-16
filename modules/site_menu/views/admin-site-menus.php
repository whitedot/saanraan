<?php

$adminPageTitle = sr_t('site_menu::ui.menu.766fbd09');
$adminPageSubtitle = '항목은 최대 3단계까지 구성할 수 있습니다.';
$adminContainerClass = 'admin-page-site-menu admin-ui-scope';
include SR_ROOT . '/modules/admin/views/layout-header.php';

$siteMenuHelpOpenLabel = '도움말 보기';
$siteMenuHelp = [
    'menu' => [
        'id' => 'site-menu-help-menu',
        'title' => '메뉴 식별값과 사용 상태',
        'body' => '<p>메뉴 식별값은 콘텐츠·커뮤니티 레이아웃 설정 등에서 표시할 메뉴 묶음을 찾는 기준입니다.</p>'
            . '<p>식별값을 바꿔도 다른 설정에 저장된 선택값은 자동으로 바뀌지 않습니다. 공개 반영 후 이 메뉴를 사용하던 레이아웃 설정에서 다시 선택해 주세요.</p>'
            . '<p>메뉴 묶음을 사용 중지하면 속한 항목을 포함한 전체 메뉴가 공개 화면에 표시되지 않습니다.</p>',
    ],
    'link' => [
        'id' => 'site-menu-help-link',
        'title' => '메뉴 항목 연결 방법',
        'body' => '<p>서비스·대상 종류·연결 대상은 등록된 화면을 쉽게 찾는 선택 도구입니다. 연결 대상을 고르면 항목 이름과 연결 주소가 자동으로 채워지며, 필요하면 두 값을 직접 바꿔도 됩니다.</p>'
            . '<p>사이트 안의 화면은 <code>/</code>로 시작하는 주소를, 외부 사이트는 <code>http://</code> 또는 <code>https://</code>로 시작하는 주소를 입력하세요. 주소를 비우면 누를 수 없는 텍스트 항목으로 표시됩니다.</p>',
    ],
    'structure' => [
        'id' => 'site-menu-help-structure',
        'title' => '상위 항목과 공개 상태',
        'body' => '<p>메뉴는 최상위 항목부터 최대 3단계까지 구성할 수 있습니다. 상위 항목을 바꾸면 현재 항목의 하위 항목도 함께 이동하며, 이동 후 3단계를 넘을 수는 없습니다.</p>'
            . '<p>항목을 사용 중지하면 그 항목과 안에 속한 하위 항목이 공개 메뉴에서 함께 숨겨집니다.</p>',
    ],
    'order' => [
        'id' => 'site-menu-help-order',
        'title' => '표시 순서와 공개 반영',
        'body' => '<p>같은 상위 항목 안에서 표시 순서 숫자가 작을수록 먼저 나옵니다. 드래그하거나 이동 버튼을 누르면 목록의 숫자가 바뀍니다.</p>'
            . '<p>항목 추가·수정 창에서 저장하면 그 항목의 표시 순서만 저장됩니다. 목록에서 바꾼 여러 항목의 순서는 <strong>초안 순서 저장</strong> 또는 <strong>공개 반영</strong>을 눌러야 저장됩니다. 공개 사이트에는 공개 반영을 누른 시점의 초안 전체가 적용됩니다.</p>',
    ],
];

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
    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('site_menu::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($modalId); ?>">
        <?php echo sr_material_icon_html('close', '', sr_t('site_menu::ui.close.1e8c1020')); ?>
    </button>
    <?php
};

$siteMenuRenderMenuModal = static function (string $modalId, string $title, ?array $menu = null) use ($allowedStatuses, $siteMenuHelp, $siteMenuHelpOpenLabel, $siteMenuModalCloseButton): void {
    $editingMenu = is_array($menu);
    $menuKey = $editingMenu ? (string) ($menu['menu_key'] ?? '') : '';
    $label = $editingMenu ? (string) ($menu['label'] ?? '') : '';
    $statusValue = $editingMenu ? (string) ($menu['status'] ?? 'enabled') : 'enabled';
    ?>
    <div id="<?php echo sr_e($modalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($modalId); ?>_title" aria-hidden="true" inert>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/site-menus')); ?>" class="modal-content ui-form-theme" data-sr-validate-form>
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($modalId); ?>_title" class="modal-title"><?php echo sr_e($title); ?></h3>
                    <?php $siteMenuModalCloseButton($modalId); ?>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="save_menu">
                    <input type="hidden" name="original_menu_key" value="<?php echo $editingMenu ? sr_e($menuKey) : ''; ?>">
                    <div class="form-row">
                        <?php echo sr_admin_form_label_help_html($modalId . '_menu_key', sr_t('site_menu::ui.menu.key.20cd5d6a'), $siteMenuHelp['menu']['id'], $siteMenuHelpOpenLabel, true); ?>
                        <div class="form-field">
                            <input id="<?php echo sr_e($modalId); ?>_menu_key" type="text" name="menu_key" value="<?php echo sr_e($menuKey); ?>" class="form-input" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" required data-validation-message="영문 소문자로 시작하고 소문자, 숫자, 밑줄만 입력해 주세요." data-admin-key-input data-overlay-focus>
                            <p class="form-help">다른 설정에서 이 메뉴 묶음을 찾을 때 사용합니다. 영문 소문자로 시작하고 소문자, 숫자, 밑줄만 입력하세요.</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_label"><?php echo sr_e(sr_t('site_menu::ui.menu.name.0615c5f4')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('site_menu::ui.required.1f227c67')); ?></span></label>
                        <div class="form-field">
                            <input id="<?php echo sr_e($modalId); ?>_label" type="text" name="label" value="<?php echo sr_e($label); ?>" class="form-input form-control-full" maxlength="120" required data-validation-message="메뉴 이름을 입력해 주세요.">
                            <p class="form-help">관리 화면과 레이아웃 설정에서 메뉴 묶음을 구분하는 이름입니다.</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <?php echo sr_admin_form_label_help_html($modalId . '_status', sr_t('site_menu::ui.status.e10195a1'), $siteMenuHelp['menu']['id'], $siteMenuHelpOpenLabel, true); ?>
                        <div class="form-field">
                            <select id="<?php echo sr_e($modalId); ?>_status" name="status" class="form-select" required data-validation-message="상태를 선택해 주세요.">
                                <?php foreach ($allowedStatuses as $status) { ?>
                                    <option value="<?php echo sr_e($status); ?>"<?php echo $statusValue === $status ? ' selected' : ''; ?>>
                                        <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <p class="form-help">사용 중지하면 이 메뉴 묶음 전체가 공개 화면에 표시되지 않습니다.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer-note">
                    <p class="form-help">여기서 저장하면 메뉴 초안만 바뀍니다. 공개 사이트에 적용하려면 목록에서 공개 반영을 눌러야 합니다.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>"><?php echo sr_e(sr_t('site_menu::ui.close.1e8c1020')); ?></button>
                    <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('site_menu::ui.menu.draft.save.97477650')); ?></button>
                </div>
            </form>
        </div>
    </div>
    <?php
};

$siteMenuRenderItemModal = static function (string $modalId, string $title, int $menuId, int $parentId = 0, ?array $item = null, int $defaultSortOrder = 100) use ($allowedStatuses, $allowedTargets, $siteMenuHelp, $siteMenuHelpOpenLabel, $siteMenuIconOptions, $siteMenuModalCloseButton, $siteMenuParentOptions, $siteMenuSelectedAssetKeys, $siteMenuModuleOptions, $siteMenuAssetTypeOptions, $siteMenuAssetOptions): void {
    $editingItem = is_array($item);
    $itemId = $editingItem ? (int) ($item['id'] ?? 0) : 0;
    $itemMenuId = $editingItem ? (int) ($item['menu_id'] ?? $menuId) : $menuId;
    $itemParentId = $editingItem ? (int) ($item['parent_id'] ?? 0) : $parentId;
    $label = $editingItem ? (string) ($item['label'] ?? '') : '';
    $url = $editingItem ? (string) ($item['url'] ?? '') : '';
    $iconName = $editingItem ? (string) ($item['icon_name'] ?? '') : '';
    $targetValue = $editingItem ? (string) ($item['target'] ?? 'self') : 'self';
    $statusValue = $editingItem ? (string) ($item['status'] ?? 'enabled') : 'enabled';
    $sortOrder = $editingItem ? (int) ($item['sort_order'] ?? $defaultSortOrder) : $defaultSortOrder;
    [$selectedModuleKey, $selectedAssetType] = $siteMenuSelectedAssetKeys($url);
    ?>
    <div id="<?php echo sr_e($modalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($modalId); ?>_title" aria-hidden="true" inert>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/site-menus')); ?>" class="modal-content ui-form-theme" data-sr-validate-form>
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($modalId); ?>_title" class="modal-title"><?php echo sr_e($title); ?></h3>
                    <?php $siteMenuModalCloseButton($modalId); ?>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="save_item">
                    <input type="hidden" name="item_id" value="<?php echo sr_e((string) $itemId); ?>">
                    <input type="hidden" name="menu_id" value="<?php echo sr_e((string) $itemMenuId); ?>">
                    <div class="form-row">
                        <?php echo sr_admin_form_label_help_html($modalId . '_module', sr_t('site_menu::ui.text.06aff97f'), $siteMenuHelp['link']['id'], $siteMenuHelpOpenLabel, false, true); ?>
                        <div class="form-field">
                            <select id="<?php echo sr_e($modalId); ?>_module" class="form-select" data-site-menu-module-select data-overlay-focus>
                                <?php $siteMenuModuleOptions($selectedModuleKey); ?>
                            </select>
                            <p class="form-help">연결할 화면을 쉽게 찾기 위한 선택 도구입니다. 직접 주소를 입력하려면 직접 입력을 선택하세요.</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_asset_type"><?php echo sr_e(sr_t('site_menu::ui.text.75c3bd09')); ?></label>
                        <div class="form-field">
                            <select id="<?php echo sr_e($modalId); ?>_asset_type" class="form-select" data-site-menu-asset-type-select>
                                <?php $siteMenuAssetTypeOptions($selectedModuleKey, $selectedAssetType); ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_asset"><?php echo sr_e(sr_t('site_menu::ui.text.ea61edcb')); ?></label>
                        <div class="form-field">
                            <select id="<?php echo sr_e($modalId); ?>_asset" class="form-select" data-site-menu-asset-select>
                                <?php $siteMenuAssetOptions($selectedModuleKey, $selectedAssetType, $url); ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <?php echo sr_admin_form_label_help_html($modalId . '_parent_id', sr_t('site_menu::ui.text.6ab1927c'), $siteMenuHelp['structure']['id'], $siteMenuHelpOpenLabel, false, true); ?>
                        <div class="form-field">
                            <select id="<?php echo sr_e($modalId); ?>_parent_id" name="parent_id" class="form-select">
                                <?php $siteMenuParentOptions($itemMenuId, $itemParentId, $itemId); ?>
                            </select>
                            <p class="form-help">최상위 항목부터 최대 3단계까지 구성할 수 있습니다.</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_label"><?php echo sr_e(sr_t('site_menu::ui.name.661e423c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('site_menu::ui.required.1f227c67')); ?></span></label>
                        <div class="form-field">
                            <input id="<?php echo sr_e($modalId); ?>_label" type="text" name="label" value="<?php echo sr_e($label); ?>" class="form-input form-control-full" maxlength="120" required data-validation-message="메뉴 항목 이름을 입력해 주세요." data-site-menu-label-input>
                        </div>
                    </div>
                    <div class="form-row">
                        <?php echo sr_admin_form_label_help_html($modalId . '_url', '연결 주소(URL)', $siteMenuHelp['link']['id'], $siteMenuHelpOpenLabel); ?>
                        <div class="form-field">
                            <input id="<?php echo sr_e($modalId); ?>_url" type="text" name="url" value="<?php echo sr_e($url); ?>" class="form-input form-control-full" maxlength="255" data-validation-message="URL은 /로 시작하는 내부 URL 또는 http/https URL이어야 합니다." data-site-menu-url-input>
                            <p class="form-help">비우면 누를 수 없는 텍스트 항목으로 표시됩니다.</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_icon_name"><?php echo sr_e(sr_t('site_menu::ui.icon.8b29d6ef')); ?></label>
                        <div class="form-field">
                            <select id="<?php echo sr_e($modalId); ?>_icon_name" name="icon_name" class="form-select">
                                <option value=""<?php echo $iconName === '' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('site_menu::ui.icon.none.9445f03f')); ?></option>
                                <?php foreach ($siteMenuIconOptions as $optionIconName => $_enabled) { ?>
                                    <option value="<?php echo sr_e((string) $optionIconName); ?>"<?php echo $iconName === (string) $optionIconName ? ' selected' : ''; ?>><?php echo sr_e((string) $optionIconName); ?></option>
                                <?php } ?>
                            </select>
                            <p class="form-help">공개 메뉴에서 항목 이름 앞에 표시할 아이콘입니다.</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>_target"><?php echo sr_e(sr_t('site_menu::ui.text.5235ffd9')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('site_menu::ui.required.1f227c67')); ?></span></label>
                        <div class="form-field">
                            <select id="<?php echo sr_e($modalId); ?>_target" name="target" class="form-select" required data-validation-message="링크 열기 방식을 선택해 주세요.">
                                <?php foreach ($allowedTargets as $target) { ?>
                                    <option value="<?php echo sr_e($target); ?>"<?php echo $targetValue === $target ? ' selected' : ''; ?>>
                                        <?php echo sr_e(sr_admin_code_label($target, 'menu_target')); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <p class="form-help">현재 창에서 열지, 새 창에서 열지 선택합니다.</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <?php echo sr_admin_form_label_help_html($modalId . '_status', sr_t('site_menu::ui.status.e10195a1'), $siteMenuHelp['structure']['id'], $siteMenuHelpOpenLabel, true); ?>
                        <div class="form-field">
                            <select id="<?php echo sr_e($modalId); ?>_status" name="status" class="form-select" required data-validation-message="상태를 선택해 주세요.">
                                <?php foreach ($allowedStatuses as $status) { ?>
                                    <option value="<?php echo sr_e($status); ?>"<?php echo $statusValue === $status ? ' selected' : ''; ?>>
                                        <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <p class="form-help">사용 중지하면 이 항목과 하위 항목이 공개 메뉴에서 함께 숨겨집니다.</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <?php echo sr_admin_form_label_help_html($modalId . '_sort_order', sr_t('site_menu::ui.text.3788952d'), $siteMenuHelp['order']['id'], $siteMenuHelpOpenLabel); ?>
                        <div class="form-field">
                            <input id="<?php echo sr_e($modalId); ?>_sort_order" type="number" name="sort_order" value="<?php echo sr_e((string) $sortOrder); ?>" class="form-input">
                            <p class="form-help">같은 상위 항목 안에서 숫자가 작을수록 먼저 표시됩니다.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer-note">
                    <p class="form-help">여기서 저장하면 이 항목의 초안만 바뀍니다. 목록에서 바꾼 다른 항목의 표시 순서는 함께 저장되지 않으며, 공개 사이트에 적용하려면 공개 반영을 눌러야 합니다.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>"><?php echo sr_e(sr_t('site_menu::ui.close.1e8c1020')); ?></button>
                    <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('site_menu::ui.item.draft.save.73d4c98b')); ?></button>
                </div>
            </form>
        </div>
    </div>
    <?php
};
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<section class="card admin-list-card admin-list-form admin-site-menu-form">
    <div class="card-header">
        <div>
            <h2 class="card-title"><?php echo sr_e(sr_t('site_menu::ui.menu.5b2bf65a')); ?></h2>
            <p class="admin-dashboard-meta"><?php echo sr_e(sr_t('site_menu::ui.draft.notice.66a164cf')); ?></p>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="site_menu_add_menu_modal" data-overlay="#site_menu_add_menu_modal"><?php echo sr_e(sr_t('site_menu::ui.menu.ba050327')); ?></button>
    </div>
    <div class="table-wrapper">
        <table class="table table-list">
            <thead>
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
                                            <span class="admin-menu-target-context">(<?php echo sr_e((string) $row['menu_key']); ?>)</span>
                                        </span>
                                    </div>
                                </td>
                                <td></td>
                                <td><span class="badge-status <?php echo (string) $row['status'] === 'enabled' ? 'is-success' : 'is-danger'; ?>"><?php echo sr_e(sr_admin_code_label((string) $row['status'], 'content_status')); ?></span></td>
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
                            $itemDescendantCount = (int) ($itemDescendantCounts[$itemId] ?? 0);
                            $itemDeleteConfirmMessage = $itemDescendantCount > 0
                                ? sprintf(sr_t('site_menu::ui.delete.descendants.confirm.3fe6176d'), $itemDescendantCount)
                                : '';
                            ?>
                            <tr class="admin-menu-row admin-menu-row-depth-<?php echo sr_e((string) $rowDepth); ?>" data-admin-sortable-row data-sort-scope="site_menu_<?php echo sr_e((string) $row['menu_id']); ?>" data-sort-parent="<?php echo sr_e((string) ((int) ($row['parent_id'] ?? 0))); ?>" data-sort-key="<?php echo sr_e((string) $itemId); ?>" data-sort-depth="<?php echo sr_e((string) $rowDepth); ?>">
                                <td>
                                    <span class="admin-menu-move-controls" role="group" aria-label="메뉴 항목 이동 도구">
                                        <span class="admin-drag-handle" draggable="true" aria-label="<?php echo sr_e(sr_t('site_menu::ui.text.baef0d03')); ?>" title="<?php echo sr_e(sr_t('site_menu::ui.text.baef0d03')); ?>"><?php echo sr_material_icon_html('apps', 'admin-drag-handle-icon'); ?></span>
                                        <button type="button" class="btn btn-icon-xs btn-ghost-default admin-menu-move-button" data-admin-sort-move="up" aria-label="<?php echo sr_e((string) $row['label'] . ' 위로 이동'); ?>" title="위로 이동"><?php echo sr_material_icon_html('keyboard_arrow_up'); ?></button>
                                        <button type="button" class="btn btn-icon-xs btn-ghost-default admin-menu-move-button" data-admin-sort-move="down" aria-label="<?php echo sr_e((string) $row['label'] . ' 아래로 이동'); ?>" title="아래로 이동"><?php echo sr_material_icon_html('keyboard_arrow_down'); ?></button>
                                    </span>
                                </td>
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
                                            <span class="admin-menu-target-context">(<?php echo sr_e((string) $row['menu_key']); ?>)</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="admin-table-break"><?php echo sr_e(trim((string) ($row['url'] ?? '')) !== '' ? (string) $row['url'] : '링크 없음'); ?></td>
                                <td><span class="badge-status <?php echo (string) $row['status'] === 'enabled' ? 'is-success' : 'is-danger'; ?>"><?php echo sr_e(sr_admin_code_label((string) $row['status'], 'content_status')); ?></span></td>
                                <td class="admin-menu-sort-order-cell">
                                    <input type="number" name="item_sort_order[<?php echo sr_e((string) $itemId); ?>]" value="<?php echo sr_e((string) $row['sort_order']); ?>" form="site-menu-order-form" data-admin-sort-order class="form-input admin-menu-sort-order-input">
                                </td>
                                <td class="admin-table-actions-cell">
                                    <div class="admin-row-actions">
                                        <?php if ($rowDepth < 3) { ?>
                                            <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($childAddModalId); ?>" data-overlay="#<?php echo sr_e($childAddModalId); ?>"><?php echo sr_e(sr_t('site_menu::ui.text.8d136d31')); ?></button>
                                        <?php } ?>
                                        <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('site_menu::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('site_menu::ui.edit.3537f0cc')); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($itemEditModalId); ?>" data-overlay="#<?php echo sr_e($itemEditModalId); ?>"><?php echo sr_material_icon_html('edit'); ?></button>
                                        <form method="post" action="<?php echo sr_e(sr_url('/admin/site-menus')); ?>"<?php echo $itemDescendantCount > 0 ? ' data-site-menu-delete-descendants="' . sr_e((string) $itemDescendantCount) . '" data-site-menu-delete-message="' . sr_e($itemDeleteConfirmMessage) . '"' : ''; ?>>
                                            <?php echo sr_csrf_field(); ?>
                                            <input type="hidden" name="intent" value="delete_item">
                                            <input type="hidden" name="item_id" value="<?php echo sr_e((string) $itemId); ?>">
                                            <input type="hidden" name="confirm_descendant_delete" value="0" data-site-menu-delete-confirm-input>
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
    <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> <?php echo sr_e(sr_t('site_menu::ui.edit.3537f0cc')); ?></span>
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('delete'); ?> <?php echo sr_e(sr_t('site_menu::ui.delete.6139b6c3')); ?></span>
    </div>
    <?php echo sr_admin_status_description_list_html('content_status', sr_admin_code_label_options(['enabled', 'disabled'], 'content_status')); ?>
</section>

<div class="form-actions form-sticky-actions admin-site-menu-form-actions">
    <p class="form-help">초안 저장 작업은 공개 사이트에 바로 반영되지 않습니다. 공개 반영을 누르면 현재 초안이 실제 메뉴로 적용됩니다.</p>
    <button type="submit" form="site-menu-order-form" class="btn btn-solid-light"><?php echo sr_e(sr_t('site_menu::ui.draft.order.save.17fa471c')); ?></button>
    <button type="submit" form="site-menu-publish-form" class="btn btn-solid-primary" onclick="return confirm(<?php echo sr_e(sr_js_json_encode(sr_t('site_menu::ui.publish.confirm.46c70ccb'))); ?>);"><?php echo sr_e(sr_t('site_menu::ui.publish.30a64fe2')); ?></button>
</div>

<form id="site-menu-order-form" method="post" action="<?php echo sr_e(sr_url('/admin/site-menus')); ?>">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_item_order">
</form>

<form id="site-menu-publish-form" method="post" action="<?php echo sr_e(sr_url('/admin/site-menus')); ?>">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="publish_site_menus">
</form>

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

<?php foreach ($siteMenuHelp as $siteMenuHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $siteMenuHelpModal['id'], (string) $siteMenuHelpModal['title'], (string) $siteMenuHelpModal['body']); ?>
<?php } ?>

<script src="<?php echo sr_e(sr_admin_asset_url('/modules/site_menu/assets/admin.js')); ?>" defer></script>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
