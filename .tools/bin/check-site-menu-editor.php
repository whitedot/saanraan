#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));
require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/site_menu/helpers.php';
require_once SR_ROOT . '/modules/site_menu/admin-editor.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$reject = static function (callable $operation, string $message) use ($assert): void {
    try {
        $operation();
    } catch (DomainException $exception) {
        return;
    }
    $assert(false, $message);
};
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE sr_site_menu_draft_menus (id INTEGER PRIMARY KEY, menu_key TEXT UNIQUE, label TEXT, status TEXT, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE sr_site_menu_draft_items (id INTEGER PRIMARY KEY, menu_id INTEGER, parent_id INTEGER, label TEXT, url TEXT, icon_name TEXT, target TEXT, status TEXT, sort_order INTEGER, created_at TEXT, updated_at TEXT)');
$pdo->exec("INSERT INTO sr_site_menu_draft_menus VALUES (1,'header','Header','enabled','2026-01-01','2026-01-01'),(2,'footer','Footer','disabled','2026-01-01','2026-01-01')");
$pdo->exec("INSERT INTO sr_site_menu_draft_items VALUES (1,1,NULL,'First','/','','self','enabled',10,'2026-01-01','2026-01-01'),(2,1,NULL,'Second','/second','','self','enabled',20,'2026-01-01','2026-01-01'),(3,1,2,'Child','/child','','blank','disabled',10,'2026-01-01','2026-01-01')");
$pdo->exec('CREATE TABLE sr_site_menus AS SELECT * FROM sr_site_menu_draft_menus');
$pdo->exec('CREATE TABLE sr_site_menu_items AS SELECT * FROM sr_site_menu_draft_items');
$baseline = sr_site_menu_editor_snapshot($pdo);
$editor = sr_site_menu_editor_create($baseline, 7);
$assert(!sr_site_menu_editor_dirty($editor), 'Initial editor must be clean.');
$editor = sr_site_menu_editor_apply_orders($editor, [1 => '40', 2 => '10']);
$editor = sr_site_menu_editor_change($pdo, $editor, 'save_menu', ['menu_key' => 'new_menu', 'label' => 'New menu', 'status' => 'enabled']);
$editor = sr_site_menu_editor_change($pdo, $editor, 'save_item', ['menu_id' => 3, 'label' => 'New item', 'url' => '', 'status' => 'enabled', 'target' => 'self', 'sort_order' => 10]);
$assert(isset($editor['items'][4]) && (int)$editor['items'][4]['menu_id'] === 3, 'Items must attach to a newly staged menu.');
$reject(fn () => sr_site_menu_editor_change($pdo, $editor, 'delete_item', ['item_id' => 2]), 'Child deletion must require confirmation.');
$editor = sr_site_menu_editor_change($pdo, $editor, 'delete_item', ['item_id' => 2, 'confirm_descendant_delete' => '1']);
$assert(!isset($editor['items'][2]) && !isset($editor['items'][3]), 'Confirmed deletion must remove the subtree.');
$assert(sr_site_menu_editor_has_deletions($editor), 'Batch save must identify deletion permission requirements.');
$assert(sr_site_menu_editor_snapshot($pdo) === $baseline, 'Staged additions, deletions and ordering must not write draft tables.');
$assert(sr_site_menu_editor_dirty($editor), 'Staged changes must mark the editor dirty.');
$reject(fn () => sr_site_menu_editor_apply_orders($editor, [], true), 'Truncated batch orders must be rejected.');
$reject(fn () => sr_site_menu_editor_apply_orders($editor, 'invalid'), 'Non-array orders must be rejected.');
$reject(fn () => sr_site_menu_editor_apply_orders($editor, [1 => ['5']]), 'Nested sort payload must be rejected.');
$reject(fn () => sr_site_menu_editor_apply_orders($editor, [1 => '1.5']), 'Fractional sort order must be rejected.');
$reject(fn () => sr_site_menu_editor_apply_orders($editor, [999 => '10']), 'Unknown item order must be rejected.');
$reject(fn () => sr_site_menu_editor_change($pdo, $editor, 'save_menu', ['menu_key'=>'header','label'=>'Duplicate','status'=>'enabled']), 'Duplicate add must not overwrite an existing menu.');
$badItem = ['menu_id'=>1,'label'=>'Bad','url'=>'javascript:alert(1)','status'=>'enabled','target'=>'self','sort_order'=>10];
$reject(fn () => sr_site_menu_editor_change($pdo, $editor, 'save_item', $badItem), 'Unsafe URL must be rejected on the server.');
$badItem['url'] = '/'; $badItem['parent_id'] = 4;
$reject(fn () => sr_site_menu_editor_change($pdo, $editor, 'save_item', $badItem), 'Parent from another menu must be rejected.');
$badItem['parent_id'] = 1; $badItem['item_id'] = 1;
$reject(fn () => sr_site_menu_editor_change($pdo, $editor, 'save_item', $badItem), 'Self parent must be rejected.');
$badItem['label'] = ''; $badItem['parent_id'] = 0;
$reject(fn () => sr_site_menu_editor_change($pdo, $editor, 'save_item', $badItem), 'Empty item label must be rejected.');
$depthEditor = sr_site_menu_editor_create($baseline,7);
$depthEditor = sr_site_menu_editor_change($pdo,$depthEditor,'save_item',['menu_id'=>1,'parent_id'=>3,'label'=>'Third level','url'=>'','target'=>'self','status'=>'enabled','sort_order'=>10]);
$reject(fn () => sr_site_menu_editor_change($pdo,$depthEditor,'save_item',['menu_id'=>1,'parent_id'=>4,'label'=>'Fourth level','url'=>'','target'=>'self','status'=>'enabled','sort_order'=>10]), 'Fourth level must be rejected.');
$reject(fn () => sr_site_menu_editor_change($pdo,$depthEditor,'save_item',['item_id'=>2,'menu_id'=>1,'parent_id'=>4,'label'=>'Cycle','url'=>'','target'=>'self','status'=>'enabled','sort_order'=>10]), 'Descendant parenting cycle must be rejected.');
$reject(fn () => sr_site_menu_editor_change($pdo,$editor,'delete_menu',['menu_id'=>3]), 'Menu with items must require confirmation.');
$pdo->beginTransaction();
sr_site_menu_editor_save($pdo, $editor);
$pdo->commit();
$saved = sr_site_menu_editor_snapshot($pdo);
$assert(count($saved['menus']) === 3 && count($saved['items']) === 2, 'One batch must persist additions and deletions.');
$assert((int)$saved['items'][1]['sort_order'] === 40, 'Batch must persist reordered surviving items.');
$assert((int)$pdo->query('SELECT COUNT(*) FROM sr_site_menu_items')->fetchColumn() === 3, 'Draft save must preserve published items.');
$assert((int)$pdo->query('SELECT COUNT(*) FROM sr_site_menus')->fetchColumn() === 2, 'Draft save must preserve published menus.');
$pdo->beginTransaction();
$reject(fn () => sr_site_menu_editor_save($pdo, $editor), 'Stale editor must not overwrite newer DB state.');
$pdo->rollBack();
$fresh = sr_site_menu_editor_create($saved,7);
$assert(!sr_site_menu_editor_dirty($fresh), 'Reloading saved draft must clear pending state.');
$empty = sr_site_menu_editor_change($pdo,$fresh,'delete_menu',['menu_id'=>1,'confirm_descendant_delete'=>'1']);
$empty = sr_site_menu_editor_change($pdo,$empty,'delete_menu',['menu_id'=>2]);
$empty = sr_site_menu_editor_change($pdo,$empty,'delete_menu',['menu_id'=>3,'confirm_descendant_delete'=>'1']);
$assert($empty['menus'] === [] && $empty['items'] === [], 'All menus can be staged for deletion without orphan items.');
$pdo->exec("CREATE TRIGGER fail_delete BEFORE DELETE ON sr_site_menu_draft_menus BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
$pdo->beginTransaction();
try {
    sr_site_menu_editor_save($pdo,$empty);
    throw new RuntimeException('Fixture failure must abort the write.');
} catch (PDOException $exception) {
    $pdo->rollBack();
}
$assert(sr_site_menu_editor_snapshot($pdo) === $saved, 'Failed batch transaction must restore all previous draft rows.');
$pdo->exec('DROP TRIGGER fail_delete');
$pdo->beginTransaction(); sr_site_menu_editor_save($pdo,$empty); $pdo->commit();
$assert(sr_site_menu_editor_snapshot($pdo) === ['menus'=>[], 'items'=>[]], 'Empty draft must be savable.');
$assert(count(sr_site_menu_editor_create($saved,8)['items']) === 2, 'Fresh account workspace must start from DB snapshot.');
$tooLarge = sr_site_menu_editor_create($baseline, 7);
$tooLarge['menus'] = array_fill(1, 101, $baseline['menus'][1]);
$reject(fn () => sr_site_menu_editor_validate($tooLarge), 'Oversized saves must be bounded on the server.');
$action = (string)file_get_contents(SR_ROOT . '/modules/site_menu/actions/admin-site-menus.php');
$assert(str_contains($action, 'sr_require_csrf();') && str_contains($action, "'/admin/site-menus', 'edit'") && str_contains($action, "'/admin/site-menus', 'delete'"), 'Request flow must enforce CSRF and edit/delete permissions.');
$assert(str_contains($action, 'hash_equals(') && str_contains($action, 'sr_admin_redirect_with_result('), 'Editor requests must reject stale forms and use PRG.');
// Saved-draft deletion restores the published baseline, not an empty live menu.
$emptyEditor = sr_site_menu_editor_create(sr_site_menu_editor_snapshot($pdo), 7);
$pdo->beginTransaction();
$reject(fn () => sr_site_menu_editor_delete_draft($pdo, $emptyEditor, '0'), 'Draft deletion must require confirmation.');
$pdo->rollBack();
$assert(sr_site_menu_editor_snapshot($pdo) === ['menus'=>[], 'items'=>[]], 'Missing confirmation must leave the draft intact.');
$pdo->beginTransaction();
$restored = sr_site_menu_editor_delete_draft($pdo, $emptyEditor, '1');
$pdo->commit();
$assert($restored === $baseline && sr_site_menu_editor_snapshot($pdo) === $baseline, 'Deleting a saved draft must restore all published menu/item fields and IDs.');
$assert(!sr_site_menu_editor_dirty(sr_site_menu_editor_create($restored,7)), 'Restored editor must have no pending changes.');
$pdo->beginTransaction();
$reject(fn () => sr_site_menu_editor_delete_draft($pdo, $emptyEditor, '1'), 'Stale draft deletion must not discard newer saved changes.');
$pdo->rollBack();
$pdo->exec("UPDATE sr_site_menu_draft_menus SET label='Saved change' WHERE id=1");
$changed = sr_site_menu_editor_snapshot($pdo);
$changedEditor = sr_site_menu_editor_create($changed,7);
$pdo->exec("CREATE TRIGGER fail_restore BEFORE INSERT ON sr_site_menu_draft_items BEGIN SELECT RAISE(ABORT, 'restore failure'); END");
$pdo->beginTransaction();
try {
    sr_site_menu_editor_delete_draft($pdo,$changedEditor,'1');
    throw new RuntimeException('Fixture failure must abort draft deletion.');
} catch (PDOException $exception) {
    $pdo->rollBack();
}
$assert(sr_site_menu_editor_snapshot($pdo) === $changed, 'Failed restore must roll back every draft deletion and insert.');
$pdo->exec('DROP TRIGGER fail_restore');
$pdo->beginTransaction(); sr_site_menu_editor_delete_draft($pdo,$changedEditor,'1'); $pdo->commit();
$assert($pdo->query('SELECT * FROM sr_site_menus ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === array_values($baseline['menus']), 'Draft deletion must preserve published menu rows.');
$assert($pdo->query('SELECT * FROM sr_site_menu_items ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === array_values($baseline['items']), 'Draft deletion must preserve published item rows.');
$pdo->exec('DELETE FROM sr_site_menu_items'); $pdo->exec('DELETE FROM sr_site_menus');
$pdo->beginTransaction();
$restoredEmpty = sr_site_menu_editor_delete_draft($pdo,sr_site_menu_editor_create(sr_site_menu_editor_snapshot($pdo),7),'1');
$pdo->commit();
$assert($restoredEmpty === ['menus'=>[], 'items'=>[]] && sr_site_menu_editor_snapshot($pdo) === $restoredEmpty, 'Empty published menus must restore an empty draft safely.');
$assert(str_contains($action, "['delete_item', 'delete_menu', 'delete_draft']"), 'Draft deletion must require delete permission.');
echo "site menu editor checks completed.\n";
