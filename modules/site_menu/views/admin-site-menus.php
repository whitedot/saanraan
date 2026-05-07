<?php

$adminPageTitle = '사이트 메뉴';
$editingItem = is_array($editItem);
$editingMenu = is_array($editMenu);
include TOY_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo toy_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo toy_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<section>
    <h2><?php echo $editingMenu ? '메뉴 수정' : '메뉴 추가'; ?></h2>
    <form method="post" action="<?php echo toy_e(toy_url('/admin/site-menus')); ?>">
        <?php echo toy_csrf_field(); ?>
        <input type="hidden" name="intent" value="save_menu">
        <input type="hidden" name="original_menu_key" value="<?php echo $editingMenu ? toy_e((string) $editMenu['menu_key']) : ''; ?>">
        <p>
            <label>메뉴 key<br>
                <input type="text" name="menu_key" value="<?php echo $editingMenu ? toy_e((string) $editMenu['menu_key']) : 'header'; ?>" maxlength="60" required>
            </label>
        </p>
        <p>
            <label>메뉴 이름<br>
                <input type="text" name="label" value="<?php echo $editingMenu ? toy_e((string) $editMenu['label']) : '헤더 메뉴'; ?>" maxlength="120" required>
            </label>
        </p>
        <p>
            <label>상태<br>
                <select name="status">
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <?php $currentMenuStatus = $editingMenu ? (string) $editMenu['status'] : 'enabled'; ?>
                        <option value="<?php echo toy_e($status); ?>"<?php echo $currentMenuStatus === $status ? ' selected' : ''; ?>>
                            <?php echo toy_e($status); ?>
                        </option>
                    <?php } ?>
                </select>
            </label>
        </p>
        <button type="submit">메뉴 저장</button>
        <?php if ($editingMenu) { ?>
            <a href="<?php echo toy_e(toy_url('/admin/site-menus')); ?>">새 메뉴 추가</a>
        <?php } ?>
    </form>
</section>

<section>
    <h2><?php echo $editingItem ? '메뉴 항목 수정' : '메뉴 항목 추가'; ?></h2>
    <?php if ($menus === []) { ?>
        <p>먼저 메뉴를 추가하세요.</p>
    <?php } else { ?>
        <form method="post" action="<?php echo toy_e(toy_url('/admin/site-menus')); ?>">
            <?php echo toy_csrf_field(); ?>
            <input type="hidden" name="intent" value="save_item">
            <input type="hidden" name="item_id" value="<?php echo $editingItem ? toy_e((string) $editItem['id']) : '0'; ?>">
            <p>
                <label>메뉴<br>
                    <select name="menu_id">
                        <?php $selectedMenuId = $editingItem ? (int) $editItem['menu_id'] : (int) $menus[0]['id']; ?>
                        <?php foreach ($menus as $menu) { ?>
                            <option value="<?php echo toy_e((string) $menu['id']); ?>"<?php echo $selectedMenuId === (int) $menu['id'] ? ' selected' : ''; ?>>
                                <?php echo toy_e((string) $menu['label'] . ' (' . (string) $menu['menu_key'] . ')'); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>항목 이름<br>
                    <input type="text" name="label" value="<?php echo $editingItem ? toy_e((string) $editItem['label']) : ''; ?>" maxlength="120" required>
                </label>
            </p>
            <p>
                <label>URL<br>
                    <input type="text" name="url" value="<?php echo $editingItem ? toy_e((string) $editItem['url']) : '/'; ?>" maxlength="255" required>
                </label>
            </p>
            <p>
                <label>target<br>
                    <select name="target">
                        <?php foreach ($allowedTargets as $target) { ?>
                            <?php $currentTarget = $editingItem ? (string) $editItem['target'] : 'self'; ?>
                            <option value="<?php echo toy_e($target); ?>"<?php echo $currentTarget === $target ? ' selected' : ''; ?>>
                                <?php echo toy_e($target); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>상태<br>
                    <select name="status">
                        <?php foreach ($allowedStatuses as $status) { ?>
                            <?php $currentStatus = $editingItem ? (string) $editItem['status'] : 'enabled'; ?>
                            <option value="<?php echo toy_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>>
                                <?php echo toy_e($status); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>정렬<br>
                    <input type="number" name="sort_order" value="<?php echo $editingItem ? toy_e((string) $editItem['sort_order']) : '100'; ?>">
                </label>
            </p>
            <button type="submit">항목 저장</button>
            <?php if ($editingItem) { ?>
                <a href="<?php echo toy_e(toy_url('/admin/site-menus')); ?>">새 항목 추가</a>
            <?php } ?>
        </form>
    <?php } ?>
</section>

<section>
    <h2>메뉴 후보</h2>
    <?php if ($menus === []) { ?>
        <p>먼저 메뉴를 추가하세요.</p>
    <?php } elseif ($menuLinkSuggestions === []) { ?>
        <p>활성 모듈이 제공한 메뉴 후보가 없습니다.</p>
    <?php } else { ?>
        <table>
            <thead>
                <tr>
                    <th>모듈</th>
                    <th>항목</th>
                    <th>URL</th>
                    <th>추가</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($menuLinkSuggestions as $suggestion) { ?>
                    <tr>
                        <td><?php echo toy_e((string) $suggestion['module_key']); ?></td>
                        <td><?php echo toy_e((string) $suggestion['label']); ?></td>
                        <td><?php echo toy_e((string) $suggestion['url']); ?></td>
                        <td>
                            <?php foreach ($menus as $menu) { ?>
                                <?php
                                $suggestionMenuId = (int) $menu['id'];
                                $suggestionUrl = (string) $suggestion['url'];
                                $alreadyAdded = isset($menuItemUrls[$suggestionMenuId][$suggestionUrl]);
                                ?>
                                <?php if ($alreadyAdded) { ?>
                                    <span><?php echo toy_e((string) $menu['label']); ?> 추가됨</span><br>
                                <?php } else { ?>
                                    <form method="post" action="<?php echo toy_e(toy_url('/admin/site-menus')); ?>" style="display:inline">
                                        <?php echo toy_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="save_item">
                                        <input type="hidden" name="item_id" value="0">
                                        <input type="hidden" name="menu_id" value="<?php echo toy_e((string) $suggestionMenuId); ?>">
                                        <input type="hidden" name="label" value="<?php echo toy_e((string) $suggestion['label']); ?>">
                                        <input type="hidden" name="url" value="<?php echo toy_e($suggestionUrl); ?>">
                                        <input type="hidden" name="target" value="self">
                                        <input type="hidden" name="status" value="enabled">
                                        <input type="hidden" name="sort_order" value="<?php echo toy_e((string) ($menuNextSortOrders[$suggestionMenuId] ?? 100)); ?>">
                                        <button type="submit"><?php echo toy_e((string) $menu['label']); ?>에 추가</button>
                                    </form><br>
                                <?php } ?>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</section>

<section>
    <h2>메뉴 목록</h2>
    <?php if ($menus === []) { ?>
        <p>등록된 메뉴가 없습니다.</p>
    <?php } else { ?>
        <table>
            <thead>
                <tr>
                    <th>key</th>
                    <th>이름</th>
                    <th>상태</th>
                    <th>수정일</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($menus as $menu) { ?>
                    <tr>
                        <td><?php echo toy_e((string) $menu['menu_key']); ?></td>
                        <td><?php echo toy_e((string) $menu['label']); ?></td>
                        <td><?php echo toy_e((string) $menu['status']); ?></td>
                        <td><?php echo toy_e((string) $menu['updated_at']); ?></td>
                        <td>
                            <a href="<?php echo toy_e(toy_url('/admin/site-menus?edit_menu_id=' . (string) $menu['id'])); ?>">수정</a>
                            <form method="post" action="<?php echo toy_e(toy_url('/admin/site-menus')); ?>" style="display:inline">
                                <?php echo toy_csrf_field(); ?>
                                <input type="hidden" name="intent" value="delete_menu">
                                <input type="hidden" name="menu_id" value="<?php echo toy_e((string) $menu['id']); ?>">
                                <button type="submit">삭제</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</section>

<section>
    <h2>항목 목록</h2>
    <?php if ($items === []) { ?>
        <p>등록된 메뉴 항목이 없습니다.</p>
    <?php } else { ?>
        <table>
            <thead>
                <tr>
                    <th>메뉴</th>
                    <th>항목</th>
                    <th>URL</th>
                    <th>상태</th>
                    <th>정렬</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item) { ?>
                    <tr>
                        <td><?php echo toy_e((string) $item['menu_key']); ?></td>
                        <td><?php echo toy_e((string) $item['label']); ?></td>
                        <td><?php echo toy_e((string) $item['url']); ?></td>
                        <td><?php echo toy_e((string) $item['status']); ?></td>
                        <td><?php echo toy_e((string) $item['sort_order']); ?></td>
                        <td>
                            <a href="<?php echo toy_e(toy_url('/admin/site-menus?edit_item_id=' . (string) $item['id'])); ?>">수정</a>
                            <form method="post" action="<?php echo toy_e(toy_url('/admin/site-menus')); ?>" style="display:inline">
                                <?php echo toy_csrf_field(); ?>
                                <input type="hidden" name="intent" value="delete_item">
                                <input type="hidden" name="item_id" value="<?php echo toy_e((string) $item['id']); ?>">
                                <button type="submit">삭제</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</section>

<?php include TOY_ROOT . '/modules/admin/views/layout-footer.php'; ?>
