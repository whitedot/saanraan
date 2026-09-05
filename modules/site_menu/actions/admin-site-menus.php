<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/site_menu/helpers.php';
require_once SR_ROOT . '/modules/site_menu/admin-editor.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/site-menus', 'view');

$allowedStatuses = ['enabled', 'disabled'];
$allowedTargets = ['self', 'blank'];
$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$menuLinkSuggestions = sr_site_menu_link_suggestions($pdo);
$siteMenuIconOptions = sr_site_menu_icon_options($pdo);

function sr_site_menu_admin_publish_draft(PDO $pdo): int
{
    $now = sr_now();
    $publishedCount = 0;
    $ownsTransaction = !$pdo->inTransaction();

    try {
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        $pdo->exec('DELETE FROM sr_site_menu_items');
        $pdo->exec('DELETE FROM sr_site_menus');

        $stmt = $pdo->prepare(
            'INSERT INTO sr_site_menus (id, menu_key, label, status, created_at, updated_at)
             SELECT id, menu_key, label, status, created_at, :updated_at
             FROM sr_site_menu_draft_menus
             ORDER BY id ASC'
        );
        $stmt->execute(['updated_at' => $now]);
        $publishedCount += $stmt->rowCount();

        $stmt = $pdo->prepare(
            'INSERT INTO sr_site_menu_items
                (id, menu_id, parent_id, label, url, icon_name, target, status, sort_order, created_at, updated_at)
             SELECT id, menu_id, parent_id, label, url, icon_name, target, status, sort_order, created_at, :updated_at
             FROM sr_site_menu_draft_items
             ORDER BY id ASC'
        );
        $stmt->execute(['updated_at' => $now]);
        $publishedCount += $stmt->rowCount();

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return $publishedCount;
}

$canEditSiteMenus = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/site-menus', 'edit');
$canDeleteSiteMenus = $canEditSiteMenus && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/site-menus', 'delete');
$siteMenuEditor = $_SESSION['sr_site_menu_editor'] ?? null;
if (!is_array($siteMenuEditor) || (int) ($siteMenuEditor['account_id'] ?? 0) !== (int) $account['id']) {
    $siteMenuEditor = sr_site_menu_editor_create(sr_site_menu_editor_snapshot($pdo), (int) $account['id']);
    $_SESSION['sr_site_menu_editor'] = $siteMenuEditor;
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/site-menus', 'edit');
    $intent = sr_post_string('intent', 40);
    if (in_array($intent, ['delete_item', 'delete_menu', 'delete_draft'], true)
        || (in_array($intent, ['save_draft', 'publish_site_menus'], true) && sr_site_menu_editor_has_deletions($siteMenuEditor))) {
        sr_admin_require_permission($pdo, (int) $account['id'], '/admin/site-menus', 'delete');
    }
    $input = [];
    foreach (['intent', 'form_id', 'original_menu_key', 'menu_key', 'menu_id', 'item_id', 'parent_id', 'label', 'url', 'icon_name', 'target', 'status', 'sort_order', 'confirm_descendant_delete'] as $key) {
        if (isset($_POST[$key]) && is_scalar($_POST[$key])) {
            $input[$key] = substr((string) $_POST[$key], 0, 1024);
        }
    }
    try {
        if (!hash_equals((string) $siteMenuEditor['revision'], sr_post_string('editor_revision', 32))) {
            throw new DomainException('편집 화면이 바뀌었습니다. 새로고침한 뒤 다시 시도해 주세요.');
        }
        if ($intent === 'delete_draft') {
            $pdo->beginTransaction();
            $restored = sr_site_menu_editor_delete_draft($pdo, $siteMenuEditor, sr_post_string('confirm_delete_draft', 20));
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'], 'actor_type' => 'admin',
                'event_type' => 'site_menu.draft.deleted', 'target_type' => 'site_menu',
                'target_id' => 'site_menu', 'result' => 'success',
                'message' => 'Saved site menu changes discarded and published menus restored as the editor baseline.',
                'metadata' => ['menu_count' => count($restored['menus']), 'item_count' => count($restored['items'])],
            ]);
            $pdo->commit();
            $siteMenuEditor = sr_site_menu_editor_create($restored, (int) $account['id']);
            $notice = sr_t('site_menu::action.admin.draft_deleted');
        } elseif ($intent === 'discard_changes') {
            $siteMenuEditor = sr_site_menu_editor_create(sr_site_menu_editor_snapshot($pdo), (int) $account['id']);
            $notice = '저장하지 않은 변경을 취소하고 저장된 초안을 불러왔습니다.';
        } else {
            $candidate = sr_site_menu_editor_apply_orders($siteMenuEditor, $_POST['item_sort_order'] ?? [], in_array($intent, ['save_draft', 'publish_site_menus'], true));
            // Keep valid list order edits even if a modal field fails validation.
            $siteMenuEditor = $candidate;
            if (in_array($intent, ['save_draft', 'publish_site_menus'], true)) {
                $pdo->beginTransaction();
                sr_site_menu_editor_save($pdo, $candidate);
                if ($intent === 'publish_site_menus') {
                    $publishedCount = sr_site_menu_admin_publish_draft($pdo);
                }
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'], 'actor_type' => 'admin',
                    'event_type' => $intent === 'publish_site_menus' ? 'site_menu.published' : 'site_menu.draft.saved',
                    'target_type' => 'site_menu', 'target_id' => 'site_menu', 'result' => 'success',
                    'message' => $intent === 'publish_site_menus' ? 'Site menu draft published.' : 'Site menu draft saved.',
                    'metadata' => ['menu_count' => count($candidate['menus']), 'item_count' => count($candidate['items'])],
                ]);
                $pdo->commit();
                $siteMenuEditor = sr_site_menu_editor_create(sr_site_menu_editor_snapshot($pdo), (int) $account['id']);
                $notice = $intent === 'publish_site_menus' ? sr_t('site_menu::action.admin.published') : sr_t('site_menu::action.admin.draft_saved');
                if ($intent === 'publish_site_menus') {
                    if (!sr_site_menu_clear_cache()) {
                        $notice .= ' ' . sr_t('site_menu::action.admin.cache_invalidation_failed');
                    }
                }
            } else {
                $siteMenuEditor = sr_site_menu_editor_change($pdo, $candidate, $intent, $input);
                $notice = '편집 목록에 적용했습니다. 임시저장을 누르면 추가·수정·삭제·순서 변경이 함께 저장됩니다.';
            }
        }
    } catch (DomainException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $exception->getMessage();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sr_log_exception($exception, 'site_menu_editor_save');
        $errors[] = '사이트 메뉴 변경을 저장하지 못했습니다. 편집 내용은 유지됩니다. 다시 시도해 주세요.';
    }
    $siteMenuEditor['revision'] = bin2hex(random_bytes(16));
    $_SESSION['sr_site_menu_editor'] = $siteMenuEditor;
    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice, $errors !== [] ? ['input' => $input] : []), '/admin/site-menus');
}

$siteMenuEditorRevision = (string) $siteMenuEditor['revision'];
$siteMenuEditorDirty = sr_site_menu_editor_dirty($siteMenuEditor);
$siteMenuFailedInput = is_array($flashResult['data']['input'] ?? null) ? $flashResult['data']['input'] : [];
$menus = array_values($siteMenuEditor['menus']);
usort($menus, static fn (array $a, array $b): int => strcmp($a['menu_key'], $b['menu_key']));
$items = array_values($siteMenuEditor['items']);
foreach ($items as &$item) {
    $item['menu_key'] = (string) $siteMenuEditor['menus'][$item['menu_id']]['menu_key'];
}
unset($item);
usort($items, static fn (array $a, array $b): int => [$a['menu_key'], (int) $a['sort_order'], (int) $a['id']] <=> [$b['menu_key'], (int) $b['sort_order'], (int) $b['id']]);
$menuParentNextSortOrders = [];
foreach ($items as $row) {
    $rowMenuId = (int) $row['menu_id'];
    $rowParentId = (int) ($row['parent_id'] ?? 0);
    $menuParentNextSortOrders[$rowMenuId][$rowParentId] = max((int) ($menuParentNextSortOrders[$rowMenuId][$rowParentId] ?? 0), (int) $row['sort_order'] + 10);
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

$itemDescendantCounts = [];
$countDescendants = static function (int $menuId, int $itemId) use (&$countDescendants, &$itemDescendantCounts, $itemsByMenuParent): int {
    if (isset($itemDescendantCounts[$itemId])) {
        return (int) $itemDescendantCounts[$itemId];
    }

    $count = 0;
    foreach ($itemsByMenuParent[$menuId][$itemId] ?? [] as $childItem) {
        $childItemId = (int) ($childItem['id'] ?? 0);
        if ($childItemId <= 0) {
            continue;
        }
        $count += 1 + $countDescendants($menuId, $childItemId);
    }

    $itemDescendantCounts[$itemId] = $count;
    return $count;
};
foreach ($items as $item) {
    $countDescendants((int) $item['menu_id'], (int) $item['id']);
}

include SR_ROOT . '/modules/site_menu/views/admin-site-menus.php';
