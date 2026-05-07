<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';
require_once TOY_ROOT . '/modules/site_menu/helpers.php';

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$allowedStatuses = ['enabled', 'disabled'];
$allowedTargets = ['self', 'blank'];
$errors = [];
$notice = '';
$menuLinkSuggestions = toy_site_menu_link_suggestions($pdo);

if (toy_request_method() === 'POST') {
    toy_require_csrf();

    $intent = toy_post_string('intent', 40);
    $menuId = (int) toy_post_string('menu_id', 20);
    $itemId = (int) toy_post_string('item_id', 20);

    if ($intent === 'save_menu') {
        $menuKey = toy_site_menu_clean_key(toy_post_string('menu_key', 60));
        $originalMenuKey = toy_site_menu_clean_key(toy_post_string('original_menu_key', 60));
        $label = toy_site_menu_clean_label(toy_post_string('label', 120));
        $status = toy_post_string('status', 30);

        if ($menuKey === '') {
            $errors[] = '메뉴 key는 영문 소문자로 시작하고 영문 소문자, 숫자, underscore를 사용해야 합니다.';
        }
        if ($label === '') {
            $errors[] = '메뉴 이름을 입력하세요.';
        }
        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = '메뉴 상태가 올바르지 않습니다.';
        }

        if ($errors === []) {
            $now = toy_now();
            if ($originalMenuKey !== '') {
                $stmt = $pdo->prepare('SELECT id FROM toy_site_menus WHERE menu_key = :menu_key LIMIT 1');
                $stmt->execute(['menu_key' => $originalMenuKey]);
                if (!is_array($stmt->fetch())) {
                    $errors[] = '수정할 메뉴를 찾을 수 없습니다.';
                }

                if ($originalMenuKey !== $menuKey) {
                    $stmt = $pdo->prepare('SELECT id FROM toy_site_menus WHERE menu_key = :menu_key LIMIT 1');
                    $stmt->execute(['menu_key' => $menuKey]);
                    if (is_array($stmt->fetch())) {
                        $errors[] = '변경하려는 메뉴 key가 이미 사용 중입니다.';
                    }
                }

                if ($errors === []) {
                    $stmt = $pdo->prepare(
                        'UPDATE toy_site_menus
                         SET menu_key = :menu_key, label = :label, status = :status, updated_at = :updated_at
                         WHERE menu_key = :original_menu_key'
                    );
                    $stmt->execute([
                        'menu_key' => $menuKey,
                        'label' => $label,
                        'status' => $status,
                        'updated_at' => $now,
                        'original_menu_key' => $originalMenuKey,
                    ]);
                }
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO toy_site_menus (menu_key, label, status, created_at, updated_at)
                     VALUES (:menu_key, :label, :status, :created_at, :updated_at)
                     ON DUPLICATE KEY UPDATE label = VALUES(label), status = VALUES(status), updated_at = VALUES(updated_at)'
                );
                $stmt->execute([
                    'menu_key' => $menuKey,
                    'label' => $label,
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($errors === []) {
                toy_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'site_menu.saved',
                    'target_type' => 'site_menu',
                    'target_id' => $menuKey,
                    'result' => 'success',
                    'message' => 'Site menu saved.',
                    'metadata' => ['original_menu_key' => $originalMenuKey],
                ]);

                $notice = '메뉴를 저장했습니다.';
            }
        }
    } elseif ($intent === 'save_item') {
        $label = toy_site_menu_clean_label(toy_post_string('label', 120));
        $url = toy_site_menu_clean_url(toy_post_string('url', 255));
        $target = toy_post_string('target', 20);
        $status = toy_post_string('status', 30);
        $sortOrder = max(-100000, min(100000, (int) toy_post_string('sort_order', 20)));

        if ($menuId <= 0) {
            $errors[] = '메뉴를 선택하세요.';
        }
        if ($label === '') {
            $errors[] = '항목 이름을 입력하세요.';
        }
        if ($url === '') {
            $errors[] = '항목 URL은 /로 시작하는 내부 URL 또는 http/https URL이어야 합니다.';
        }
        if (!in_array($target, $allowedTargets, true)) {
            $errors[] = '링크 target 값이 올바르지 않습니다.';
        }
        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = '항목 상태가 올바르지 않습니다.';
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('SELECT id FROM toy_site_menus WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $menuId]);
            if (!is_array($stmt->fetch())) {
                $errors[] = '메뉴를 찾을 수 없습니다.';
            }
        }

        if ($errors === []) {
            $stmt = $pdo->prepare(
                'SELECT id FROM toy_site_menu_items
                 WHERE menu_id = :menu_id AND url = :url AND id <> :id
                 LIMIT 1'
            );
            $stmt->execute([
                'menu_id' => $menuId,
                'url' => $url,
                'id' => $itemId > 0 ? $itemId : 0,
            ]);
            if (is_array($stmt->fetch())) {
                $errors[] = '같은 메뉴에 동일한 URL 항목이 이미 있습니다.';
            }
        }

        if ($errors === []) {
            $now = toy_now();
            if ($itemId > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE toy_site_menu_items
                     SET label = :label, url = :url, target = :target, status = :status, sort_order = :sort_order, updated_at = :updated_at
                     WHERE id = :id AND menu_id = :menu_id'
                );
                $stmt->execute([
                    'label' => $label,
                    'url' => $url,
                    'target' => $target,
                    'status' => $status,
                    'sort_order' => $sortOrder,
                    'updated_at' => $now,
                    'id' => $itemId,
                    'menu_id' => $menuId,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO toy_site_menu_items
                        (menu_id, parent_id, label, url, target, status, sort_order, created_at, updated_at)
                     VALUES
                        (:menu_id, NULL, :label, :url, :target, :status, :sort_order, :created_at, :updated_at)'
                );
                $stmt->execute([
                    'menu_id' => $menuId,
                    'label' => $label,
                    'url' => $url,
                    'target' => $target,
                    'status' => $status,
                    'sort_order' => $sortOrder,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $itemId = (int) $pdo->lastInsertId();
            }

            toy_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'site_menu.item.saved',
                'target_type' => 'site_menu_item',
                'target_id' => (string) $itemId,
                'result' => 'success',
                'message' => 'Site menu item saved.',
                'metadata' => ['menu_id' => $menuId],
            ]);

            $notice = '메뉴 항목을 저장했습니다.';
        }
    } elseif ($intent === 'delete_item') {
        if ($itemId <= 0) {
            $errors[] = '삭제할 항목을 찾을 수 없습니다.';
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('DELETE FROM toy_site_menu_items WHERE id = :id');
            $stmt->execute(['id' => $itemId]);

            toy_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'site_menu.item.deleted',
                'target_type' => 'site_menu_item',
                'target_id' => (string) $itemId,
                'result' => 'success',
                'message' => 'Site menu item deleted.',
            ]);

            $notice = '메뉴 항목을 삭제했습니다.';
        }
    } elseif ($intent === 'delete_menu') {
        if ($menuId <= 0) {
            $errors[] = '삭제할 메뉴를 찾을 수 없습니다.';
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('SELECT menu_key FROM toy_site_menus WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $menuId]);
            $menu = $stmt->fetch();
            if (!is_array($menu)) {
                $errors[] = '삭제할 메뉴를 찾을 수 없습니다.';
            }
        }

        if ($errors === [] && is_array($menu)) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('DELETE FROM toy_site_menu_items WHERE menu_id = :menu_id');
                $stmt->execute(['menu_id' => $menuId]);

                $stmt = $pdo->prepare('DELETE FROM toy_site_menus WHERE id = :id');
                $stmt->execute(['id' => $menuId]);

                $pdo->commit();

                toy_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'site_menu.deleted',
                    'target_type' => 'site_menu',
                    'target_id' => (string) $menu['menu_key'],
                    'result' => 'success',
                    'message' => 'Site menu deleted.',
                ]);

                $notice = '메뉴를 삭제했습니다.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errors[] = '메뉴 삭제 중 오류가 발생했습니다.';
            }
        }
    } else {
        $errors[] = '요청한 작업을 처리할 수 없습니다.';
    }
}

$editItem = null;
$editItemId = (int) toy_get_string('edit_item_id', 20);
if ($editItemId > 0) {
    $stmt = $pdo->prepare('SELECT id, menu_id, label, url, target, status, sort_order FROM toy_site_menu_items WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editItemId]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        $editItem = $row;
    }
}

$editMenu = null;
$editMenuId = (int) toy_get_string('edit_menu_id', 20);
if ($editMenuId > 0) {
    $stmt = $pdo->prepare('SELECT id, menu_key, label, status FROM toy_site_menus WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editMenuId]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        $editMenu = $row;
    }
}

$menus = [];
$stmt = $pdo->query('SELECT id, menu_key, label, status, updated_at FROM toy_site_menus ORDER BY menu_key ASC');
foreach ($stmt->fetchAll() as $row) {
    $menus[] = $row;
}

$items = [];
$menuItemUrls = [];
$menuNextSortOrders = [];
$stmt = $pdo->query(
    'SELECT i.id, i.menu_id, m.menu_key, i.label, i.url, i.target, i.status, i.sort_order, i.updated_at
     FROM toy_site_menu_items i
     INNER JOIN toy_site_menus m ON m.id = i.menu_id
     ORDER BY m.menu_key ASC, i.sort_order ASC, i.id ASC'
);
foreach ($stmt->fetchAll() as $row) {
    $items[] = $row;
    $rowMenuId = (int) $row['menu_id'];
    $rowUrl = (string) $row['url'];
    $rowSortOrder = (int) $row['sort_order'];
    $menuItemUrls[$rowMenuId][$rowUrl] = true;
    $menuNextSortOrders[$rowMenuId] = max((int) ($menuNextSortOrders[$rowMenuId] ?? 0), $rowSortOrder + 10);
}

foreach ($menus as $menu) {
    $rowMenuId = (int) $menu['id'];
    if (!isset($menuNextSortOrders[$rowMenuId])) {
        $menuNextSortOrders[$rowMenuId] = 100;
    }
}

include TOY_ROOT . '/modules/site_menu/views/admin-site-menus.php';
