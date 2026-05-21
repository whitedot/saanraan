<?php

$siteMenuPage = isset($siteMenuPage) ? (string) $siteMenuPage : 'menus';
$editingItem = is_array($editItem);
$editingMenu = is_array($editMenu);
$adminPageTitle = '사이트 메뉴';
if ($siteMenuPage === 'menu_form') {
    $adminPageTitle = $editingMenu ? '사이트 메뉴 수정' : '사이트 메뉴 추가';
} elseif ($siteMenuPage === 'items') {
    $adminPageTitle = '사이트 메뉴 항목';
} elseif ($siteMenuPage === 'item_form') {
    $adminPageTitle = $editingItem ? '사이트 메뉴 항목 수정' : '사이트 메뉴 항목 추가';
}

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo sr_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo sr_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<?php if ($siteMenuPage === 'menu_form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/site-menus/save')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo $editingMenu ? '메뉴 수정' : '메뉴 추가'; ?></h2>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="original_menu_key" value="<?php echo $editingMenu ? sr_e((string) $editMenu['menu_key']) : ''; ?>">
            <div class="admin-form-row">
                <label class="form-label" for="site_menu_admin_site_menus_menu_key">메뉴 key</label>
                <div class="admin-form-field">
                    <input id="site_menu_admin_site_menus_menu_key" type="text" name="menu_key" value="<?php echo $editingMenu ? sr_e((string) $editMenu['menu_key']) : 'header'; ?>" class="form-input" maxlength="60" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="site_menu_admin_site_menus_label">메뉴 이름</label>
                <div class="admin-form-field">
                    <input id="site_menu_admin_site_menus_label" type="text" name="label" value="<?php echo $editingMenu ? sr_e((string) $editMenu['label']) : '헤더 메뉴'; ?>" class="form-input" maxlength="120" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="site_menu_admin_site_menus_status">상태</label>
                <div class="admin-form-field">
                    <select id="site_menu_admin_site_menus_status" name="status" class="form-select">
                                            <?php foreach ($allowedStatuses as $status) { ?>
                                                <?php $currentMenuStatus = $editingMenu ? (string) $editMenu['status'] : 'enabled'; ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $currentMenuStatus === $status ? ' selected' : ''; ?>>
                                                    <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/site-menus')); ?>" class="btn btn-soft-default">목록</a>
            <button type="submit" class="btn btn-solid-primary">메뉴 저장</button>
        </div>
    </form>
<?php } elseif ($siteMenuPage === 'menus') { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">메뉴 목록</h2>
            <a href="<?php echo sr_e(sr_url('/admin/site-menus/new')); ?>" class="btn btn-sm btn-soft-default">새 메뉴 추가</a>
        </div>
        <?php if ($menus === []) { ?>
            <p>등록된 메뉴가 없습니다.</p>
        <?php } else { ?>
            <div class="table-wrapper">
            <table class="table">
                <thead class="ui-table-head">
                    <tr>
                        <th>key</th>
                        <th>이름</th>
                        <th>상태</th>
                        <th>수정일</th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($menus as $menu) { ?>
                        <tr>
                            <td><?php echo sr_e((string) $menu['menu_key']); ?></td>
                            <td><?php echo sr_e((string) $menu['label']); ?></td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $menu['status'], 'content_status')); ?></td>
                            <td><?php echo sr_e((string) $menu['updated_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <a href="<?php echo sr_e(sr_url('/admin/site-menus/edit?id=' . rawurlencode((string) $menu['id']))); ?>" class="btn btn-sm btn-soft-default">수정</a>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/site-menus/delete')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="menu_id" value="<?php echo sr_e((string) $menu['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            </div>
        <?php } ?>
    </section>
<?php } elseif ($siteMenuPage === 'item_form') { ?>
    <?php if ($menus === []) { ?>
        <section class="admin-card card">
            <h2><?php echo $editingItem ? '메뉴 항목 수정' : '메뉴 항목 추가'; ?></h2>
            <p>먼저 메뉴를 추가하세요.</p>
        </section>
    <?php } else { ?>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/site-menu-items/save')); ?>" class="admin-form ui-form-theme">
            <section class="admin-card card">
                <h2><?php echo $editingItem ? '메뉴 항목 수정' : '메뉴 항목 추가'; ?></h2>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="item_id" value="<?php echo $editingItem ? sr_e((string) $editItem['id']) : '0'; ?>">
                <div class="admin-form-row">
                    <label class="form-label" for="site_menu_admin_site_menus_menu_id">메뉴</label>
                    <div class="admin-form-field">
                        <select id="site_menu_admin_site_menus_menu_id" name="menu_id" class="form-select">
                                                    <?php $selectedMenuId = $editingItem ? (int) $editItem['menu_id'] : (int) $menus[0]['id']; ?>
                                                    <?php foreach ($menus as $menu) { ?>
                                                        <option value="<?php echo sr_e((string) $menu['id']); ?>"<?php echo $selectedMenuId === (int) $menu['id'] ? ' selected' : ''; ?>>
                                                            <?php echo sr_e((string) $menu['label'] . ' (' . (string) $menu['menu_key'] . ')'); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="site_menu_admin_site_menus_label_2">항목 이름</label>
                    <div class="admin-form-field">
                        <input id="site_menu_admin_site_menus_label_2" type="text" name="label" value="<?php echo $editingItem ? sr_e((string) $editItem['label']) : ''; ?>" class="form-input" maxlength="120" required>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="site_menu_admin_site_menus_url">URL</label>
                    <div class="admin-form-field">
                        <input id="site_menu_admin_site_menus_url" type="text" name="url" value="<?php echo $editingItem ? sr_e((string) $editItem['url']) : '/'; ?>" class="form-input" maxlength="255" required>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="site_menu_admin_site_menus_target">링크 대상</label>
                    <div class="admin-form-field">
                        <select id="site_menu_admin_site_menus_target" name="target" class="form-select">
                                                    <?php foreach ($allowedTargets as $target) { ?>
                                                        <?php $currentTarget = $editingItem ? (string) $editItem['target'] : 'self'; ?>
                                                        <option value="<?php echo sr_e($target); ?>"<?php echo $currentTarget === $target ? ' selected' : ''; ?>>
                                                            <?php echo sr_e(sr_admin_code_label($target, 'menu_target')); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="site_menu_admin_site_menus_status_2">상태</label>
                    <div class="admin-form-field">
                        <select id="site_menu_admin_site_menus_status_2" name="status" class="form-select">
                                                    <?php foreach ($allowedStatuses as $status) { ?>
                                                        <?php $currentStatus = $editingItem ? (string) $editItem['status'] : 'enabled'; ?>
                                                        <option value="<?php echo sr_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>>
                                                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="site_menu_admin_site_menus_sort_order">정렬</label>
                    <div class="admin-form-field">
                        <input id="site_menu_admin_site_menus_sort_order" type="number" name="sort_order" value="<?php echo $editingItem ? sr_e((string) $editItem['sort_order']) : '100'; ?>" class="form-input">
                    </div>
                </div>
            </section>
            <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
                <a href="<?php echo sr_e(sr_url('/admin/site-menu-items')); ?>" class="btn btn-soft-default">목록</a>
                <button type="submit" class="btn btn-solid-primary">항목 저장</button>
            </div>
        </form>
    <?php } ?>
<?php } elseif ($siteMenuPage === 'items') { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">메뉴 후보</h2>
        </div>
        <?php if ($menus === []) { ?>
            <p>먼저 메뉴를 추가하세요.</p>
        <?php } elseif ($menuLinkSuggestions === []) { ?>
            <p>활성 모듈이 제공한 메뉴 후보가 없습니다.</p>
        <?php } else { ?>
            <div class="table-wrapper">
            <table class="table">
                <thead class="ui-table-head">
                    <tr>
                        <th>모듈</th>
                        <th>항목</th>
                        <th>URL</th>
                        <th class="text-end">추가</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($menuLinkSuggestions as $suggestion) { ?>
                        <tr>
                            <td><?php echo sr_e((string) $suggestion['module_key']); ?></td>
                            <td><?php echo sr_e((string) $suggestion['label']); ?></td>
                            <td><?php echo sr_e((string) $suggestion['url']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                <?php foreach ($menus as $menu) { ?>
                                    <?php
                                    $suggestionMenuId = (int) $menu['id'];
                                    $suggestionUrl = (string) $suggestion['url'];
                                    $alreadyAdded = isset($menuItemUrls[$suggestionMenuId][$suggestionUrl]);
                                    ?>
                                    <?php if ($alreadyAdded) { ?>
                                        <span class="admin-status is-normal"><?php echo sr_e((string) $menu['label']); ?> 추가됨</span>
                                    <?php } else { ?>
                                        <form method="post" action="<?php echo sr_e(sr_url('/admin/site-menu-items/save')); ?>">
                                            <?php echo sr_csrf_field(); ?>
                                            <input type="hidden" name="item_id" value="0">
                                            <input type="hidden" name="menu_id" value="<?php echo sr_e((string) $suggestionMenuId); ?>">
                                            <input type="hidden" name="label" value="<?php echo sr_e((string) $suggestion['label']); ?>">
                                            <input type="hidden" name="url" value="<?php echo sr_e($suggestionUrl); ?>">
                                            <input type="hidden" name="target" value="self">
                                            <input type="hidden" name="status" value="enabled">
                                            <input type="hidden" name="sort_order" value="<?php echo sr_e((string) ($menuNextSortOrders[$suggestionMenuId] ?? 100)); ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-default"><?php echo sr_e((string) $menu['label']); ?>에 추가</button>
                                        </form>
                                    <?php } ?>
                                <?php } ?>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            </div>
        <?php } ?>
    </section>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">항목 목록</h2>
            <a href="<?php echo sr_e(sr_url('/admin/site-menu-items/new')); ?>" class="btn btn-sm btn-soft-default">새 항목 추가</a>
        </div>
        <?php if ($items === []) { ?>
            <p>등록된 메뉴 항목이 없습니다.</p>
        <?php } else { ?>
            <div class="table-wrapper">
            <table class="table">
                <thead class="ui-table-head">
                    <tr>
                        <th>메뉴</th>
                        <th>항목</th>
                        <th>URL</th>
                        <th>상태</th>
                        <th>정렬</th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item) { ?>
                        <tr>
                            <td><?php echo sr_e((string) $item['menu_key']); ?></td>
                            <td><?php echo sr_e((string) $item['label']); ?></td>
                            <td><?php echo sr_e((string) $item['url']); ?></td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $item['status'], 'content_status')); ?></td>
                            <td><?php echo sr_e((string) $item['sort_order']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <a href="<?php echo sr_e(sr_url('/admin/site-menu-items/edit?id=' . rawurlencode((string) $item['id']))); ?>" class="btn btn-sm btn-soft-default">수정</a>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/site-menu-items/delete')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="item_id" value="<?php echo sr_e((string) $item['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            </div>
        <?php } ?>
    </section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
