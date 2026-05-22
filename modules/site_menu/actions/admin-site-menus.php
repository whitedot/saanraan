<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/site_menu/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$allowedStatuses = ['enabled', 'disabled'];
$allowedTargets = ['self', 'blank'];
$errors = [];
$notice = '';
$menuLinkSuggestions = sr_site_menu_link_suggestions($pdo);

function sr_site_menu_admin_parent_depth(PDO $pdo, int $menuId, int $parentId, int $excludeItemId = 0): ?int
{
    if ($parentId <= 0) {
        return 0;
    }

    $depth = 1;
    $currentId = $parentId;
    $visited = [];
    while ($currentId > 0 && $depth <= 3) {
        if ($excludeItemId > 0 && $currentId === $excludeItemId) {
            return null;
        }
        if (isset($visited[$currentId])) {
            return null;
        }
        $visited[$currentId] = true;

        $stmt = $pdo->prepare('SELECT id, parent_id FROM sr_site_menu_items WHERE id = :id AND menu_id = :menu_id LIMIT 1');
        $stmt->execute(['id' => $currentId, 'menu_id' => $menuId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        $nextParentId = (int) ($row['parent_id'] ?? 0);
        if ($nextParentId <= 0) {
            return $depth;
        }

        $currentId = $nextParentId;
        $depth++;
    }

    return null;
}

function sr_site_menu_admin_descendant_ids(PDO $pdo, int $itemId): array
{
    $ids = [];
    $frontier = [$itemId];
    while ($frontier !== []) {
        $placeholders = implode(',', array_fill(0, count($frontier), '?'));
        $stmt = $pdo->prepare('SELECT id FROM sr_site_menu_items WHERE parent_id IN (' . $placeholders . ')');
        $stmt->execute($frontier);
        $frontier = [];
        foreach ($stmt->fetchAll() as $row) {
            $childId = (int) ($row['id'] ?? 0);
            if ($childId > 0 && !in_array($childId, $ids, true)) {
                $ids[] = $childId;
                $frontier[] = $childId;
            }
        }
    }

    return $ids;
}

function sr_site_menu_admin_subtree_max_relative_depth(PDO $pdo, int $itemId): int
{
    $maxDepth = 1;
    $walk = static function (int $parentId, int $depth) use (&$walk, &$maxDepth, $pdo): void {
        $maxDepth = max($maxDepth, $depth);
        $stmt = $pdo->prepare('SELECT id FROM sr_site_menu_items WHERE parent_id = :parent_id');
        $stmt->execute(['parent_id' => $parentId]);
        foreach ($stmt->fetchAll() as $row) {
            $childId = (int) ($row['id'] ?? 0);
            if ($childId > 0) {
                $walk($childId, $depth + 1);
            }
        }
    };
    $walk($itemId, 1);

    return $maxDepth;
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);
    $menuId = (int) sr_post_string('menu_id', 20);
    $itemId = (int) sr_post_string('item_id', 20);

    if ($intent === 'save_menu') {
        $menuKey = sr_site_menu_clean_key(sr_post_string('menu_key', 60));
        $originalMenuKey = sr_site_menu_clean_key(sr_post_string('original_menu_key', 60));
        $label = sr_site_menu_clean_label(sr_post_string('label', 120));
        $status = sr_post_string('status', 30);

        if ($menuKey === '') {
            $errors[] = sr_t('site_menu::action.admin.menu_key_invalid');
        }
        if ($label === '') {
            $errors[] = sr_t('site_menu::action.admin.menu_name_required');
        }
        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = sr_t('site_menu::action.admin.menu_status_invalid');
        }

        if ($errors === []) {
            $now = sr_now();
            if ($originalMenuKey !== '') {
                $stmt = $pdo->prepare('SELECT id FROM sr_site_menus WHERE menu_key = :menu_key LIMIT 1');
                $stmt->execute(['menu_key' => $originalMenuKey]);
                if (!is_array($stmt->fetch())) {
                    $errors[] = sr_t('site_menu::action.admin.menu_edit_not_found');
                }

                if ($originalMenuKey !== $menuKey) {
                    $stmt = $pdo->prepare('SELECT id FROM sr_site_menus WHERE menu_key = :menu_key LIMIT 1');
                    $stmt->execute(['menu_key' => $menuKey]);
                    if (is_array($stmt->fetch())) {
                        $errors[] = sr_t('site_menu::action.admin.menu_key_duplicate');
                    }
                }

                if ($errors === []) {
                    $stmt = $pdo->prepare(
                        'UPDATE sr_site_menus
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
                    'INSERT INTO sr_site_menus (menu_key, label, status, created_at, updated_at)
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
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'site_menu.saved',
                    'target_type' => 'site_menu',
                    'target_id' => $menuKey,
                    'result' => 'success',
                    'message' => 'Site menu saved.',
                    'metadata' => ['original_menu_key' => $originalMenuKey],
                ]);

                $notice = sr_t('site_menu::action.admin.menu_saved');
            }
        }
    } elseif ($intent === 'save_item') {
        $label = sr_site_menu_clean_label(sr_post_string('label', 120));
        $url = sr_site_menu_clean_url(sr_post_string('url', 255));
        $target = sr_post_string('target', 20);
        $status = sr_post_string('status', 30);
        $sortOrder = max(-100000, min(100000, (int) sr_post_string('sort_order', 20)));
        $parentId = (int) sr_post_string('parent_id', 20);

        if ($menuId <= 0) {
            $errors[] = sr_t('site_menu::action.admin.menu_required');
        }
        if ($label === '') {
            $errors[] = sr_t('site_menu::action.admin.item_name_required');
        }
        if ($url === '') {
            $errors[] = sr_t('site_menu::action.admin.item_url_invalid');
        }
        if (!in_array($target, $allowedTargets, true)) {
            $errors[] = sr_t('site_menu::action.admin.link_target_invalid');
        }
        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = sr_t('site_menu::action.admin.item_status_invalid');
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('SELECT id FROM sr_site_menus WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $menuId]);
            if (!is_array($stmt->fetch())) {
                $errors[] = sr_t('site_menu::action.admin.menu_not_found');
            }
        }

        if ($errors === [] && $parentId > 0) {
            $parentDepth = sr_site_menu_admin_parent_depth($pdo, $menuId, $parentId, $itemId);
            if ($parentDepth === null) {
                $errors[] = sr_t('site_menu::action.admin.parent_invalid');
            } elseif ($parentDepth >= 3) {
                $errors[] = sr_t('site_menu::action.admin.depth_limit');
            } elseif ($itemId > 0 && $parentDepth + sr_site_menu_admin_subtree_max_relative_depth($pdo, $itemId) > 3) {
                $errors[] = sr_t('site_menu::action.admin.descendant_depth_limit');
            }
        }

        if ($errors === []) {
            $stmt = $pdo->prepare(
                'SELECT id FROM sr_site_menu_items
                 WHERE menu_id = :menu_id AND url = :url AND id <> :id
                 LIMIT 1'
            );
            $stmt->execute([
                'menu_id' => $menuId,
                'url' => $url,
                'id' => $itemId > 0 ? $itemId : 0,
            ]);
            if (is_array($stmt->fetch())) {
                $errors[] = sr_t('site_menu::action.admin.item_url_duplicate');
            }
        }

        if ($errors === []) {
            $now = sr_now();
            if ($itemId > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE sr_site_menu_items
                     SET parent_id = :parent_id, label = :label, url = :url, target = :target, status = :status, sort_order = :sort_order, updated_at = :updated_at
                     WHERE id = :id AND menu_id = :menu_id'
                );
                $stmt->execute([
                    'parent_id' => $parentId > 0 ? $parentId : null,
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
                    'INSERT INTO sr_site_menu_items
                        (menu_id, parent_id, label, url, target, status, sort_order, created_at, updated_at)
                     VALUES
                        (:menu_id, :parent_id, :label, :url, :target, :status, :sort_order, :created_at, :updated_at)'
                );
                $stmt->execute([
                    'menu_id' => $menuId,
                    'parent_id' => $parentId > 0 ? $parentId : null,
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

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'site_menu.item.saved',
                'target_type' => 'site_menu_item',
                'target_id' => (string) $itemId,
                'result' => 'success',
                'message' => 'Site menu item saved.',
                'metadata' => ['menu_id' => $menuId, 'parent_id' => $parentId > 0 ? $parentId : null],
            ]);

            $notice = sr_t('site_menu::action.admin.item_saved');
        }
    } elseif ($intent === 'save_item_order') {
        $sortOrders = $_POST['item_sort_order'] ?? [];
        if (!is_array($sortOrders)) {
            $errors[] = sr_t('site_menu::action.admin.sort_order_invalid');
        }

        if ($errors === []) {
            $now = sr_now();
            $stmt = $pdo->prepare('UPDATE sr_site_menu_items SET sort_order = :sort_order, updated_at = :updated_at WHERE id = :id');
            foreach ($sortOrders as $id => $sortOrderValue) {
                $id = (int) $id;
                if ($id <= 0) {
                    continue;
                }
                $sortOrder = max(-100000, min(100000, (int) $sortOrderValue));
                $stmt->execute(['sort_order' => $sortOrder, 'updated_at' => $now, 'id' => $id]);
            }

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'site_menu.item_order.saved',
                'target_type' => 'site_menu_items',
                'target_id' => 'site_menu',
                'result' => 'success',
                'message' => 'Site menu item order saved.',
            ]);

            $notice = sr_t('site_menu::action.admin.item_order_saved');
        }
    } elseif ($intent === 'delete_item') {
        if ($itemId <= 0) {
            $errors[] = sr_t('site_menu::action.admin.item_delete_not_found');
        }

        if ($errors === []) {
            $deleteIds = array_merge([$itemId], sr_site_menu_admin_descendant_ids($pdo, $itemId));
            $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
            $stmt = $pdo->prepare('DELETE FROM sr_site_menu_items WHERE id IN (' . $placeholders . ')');
            $stmt->execute($deleteIds);

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'site_menu.item.deleted',
                'target_type' => 'site_menu_item',
                'target_id' => (string) $itemId,
                'result' => 'success',
                'message' => 'Site menu item deleted.',
            ]);

            $notice = sr_t('site_menu::action.admin.item_deleted');
        }
    } elseif ($intent === 'delete_menu') {
        if ($menuId <= 0) {
            $errors[] = sr_t('site_menu::action.admin.menu_delete_not_found');
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('SELECT menu_key FROM sr_site_menus WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $menuId]);
            $menu = $stmt->fetch();
            if (!is_array($menu)) {
                $errors[] = sr_t('site_menu::action.admin.menu_delete_not_found');
            }
        }

        if ($errors === [] && is_array($menu)) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('DELETE FROM sr_site_menu_items WHERE menu_id = :menu_id');
                $stmt->execute(['menu_id' => $menuId]);

                $stmt = $pdo->prepare('DELETE FROM sr_site_menus WHERE id = :id');
                $stmt->execute(['id' => $menuId]);

                $pdo->commit();

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'site_menu.deleted',
                    'target_type' => 'site_menu',
                    'target_id' => (string) $menu['menu_key'],
                    'result' => 'success',
                    'message' => 'Site menu deleted.',
                ]);

                $notice = sr_t('site_menu::action.admin.menu_deleted');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errors[] = sr_t('site_menu::action.admin.menu_delete_failed');
            }
        }
    } else {
        $errors[] = sr_t('site_menu::action.admin.intent_invalid');
    }
}

$menus = [];
$stmt = $pdo->query('SELECT id, menu_key, label, status, updated_at FROM sr_site_menus ORDER BY menu_key ASC');
foreach ($stmt->fetchAll() as $row) {
    $menus[] = $row;
}

$items = [];
$menuParentNextSortOrders = [];
$stmt = $pdo->query(
    'SELECT i.id, i.menu_id, i.parent_id, m.menu_key, i.label, i.url, i.target, i.status, i.sort_order, i.updated_at
     FROM sr_site_menu_items i
    INNER JOIN sr_site_menus m ON m.id = i.menu_id
     ORDER BY m.menu_key ASC, i.sort_order ASC, i.id ASC'
);
foreach ($stmt->fetchAll() as $row) {
    $items[] = $row;
    $rowMenuId = (int) $row['menu_id'];
    $rowParentId = (int) ($row['parent_id'] ?? 0);
    $rowSortOrder = (int) $row['sort_order'];
    $menuParentNextSortOrders[$rowMenuId][$rowParentId] = max((int) ($menuParentNextSortOrders[$rowMenuId][$rowParentId] ?? 0), $rowSortOrder + 10);
}

foreach ($menus as $menu) {
    $rowMenuId = (int) $menu['id'];
    if (!isset($menuParentNextSortOrders[$rowMenuId][0])) {
        $menuParentNextSortOrders[$rowMenuId][0] = 100;
    }
}

$menuRows = [];
$itemsByMenuParent = [];
$itemDepths = [];
foreach ($items as $item) {
    $rowMenuId = (int) $item['menu_id'];
    $rowParentId = (int) ($item['parent_id'] ?? 0);
    $itemsByMenuParent[$rowMenuId][$rowParentId][] = $item;
}

$appendItems = static function (int $menuId, int $parentId, int $depth) use (&$appendItems, &$menuRows, &$itemsByMenuParent, &$itemDepths): void {
    foreach ($itemsByMenuParent[$menuId][$parentId] ?? [] as $item) {
        $itemId = (int) $item['id'];
        $itemDepths[$itemId] = $depth;
        $item['row_type'] = 'item';
        $item['depth'] = $depth;
        $menuRows[] = $item;
        if ($depth < 3) {
            $appendItems($menuId, $itemId, $depth + 1);
        }
    }
};

foreach ($menus as $menu) {
    $menu['row_type'] = 'menu';
    $menu['depth'] = 0;
    $menuRows[] = $menu;
    $appendItems((int) $menu['id'], 0, 1);
}

include SR_ROOT . '/modules/site_menu/views/admin-site-menus.php';
