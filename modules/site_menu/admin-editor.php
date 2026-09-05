<?php

declare(strict_types=1);

// Private site_menu editor state. Public providers continue to read published tables.
function sr_site_menu_editor_snapshot(PDO $pdo, bool $lock = false): array
{
    $suffix = $lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $menus = $pdo->query('SELECT id, menu_key, label, status, created_at, updated_at FROM sr_site_menu_draft_menus ORDER BY id ASC LIMIT 101' . $suffix)->fetchAll(PDO::FETCH_ASSOC);
    $items = $pdo->query('SELECT id, menu_id, parent_id, label, url, icon_name, target, status, sort_order, created_at, updated_at FROM sr_site_menu_draft_items ORDER BY id ASC LIMIT 501' . $suffix)->fetchAll(PDO::FETCH_ASSOC);
    if (count($menus) > 100 || count($items) > 500) {
        throw new DomainException('편집 가능한 범위는 메뉴 묶음 100개, 항목 500개까지입니다.');
    }
    return ['menus' => array_column($menus, null, 'id'), 'items' => array_column($items, null, 'id')];
}

function sr_site_menu_editor_create(array $snapshot, int $accountId): array
{
    return [
        'account_id' => $accountId,
        'revision' => bin2hex(random_bytes(16)),
        'base' => $snapshot,
        'menus' => $snapshot['menus'],
        'items' => $snapshot['items'],
        'next_menu_id' => max(array_merge([0], array_keys($snapshot['menus']))) + 1,
        'next_item_id' => max(array_merge([0], array_keys($snapshot['items']))) + 1,
    ];
}

function sr_site_menu_editor_dirty(array $editor): bool
{
    return $editor['menus'] !== $editor['base']['menus'] || $editor['items'] !== $editor['base']['items'];
}

function sr_site_menu_editor_has_deletions(array $editor): bool
{
    return array_diff_key($editor['base']['menus'], $editor['menus']) !== []
        || array_diff_key($editor['base']['items'], $editor['items']) !== [];
}

function sr_site_menu_editor_descendants(array $items, int $id): array
{
    $descendants = [];
    $frontier = [$id];
    while ($frontier !== []) {
        $parent = array_pop($frontier);
        foreach ($items as $item) {
            $childId = (int) $item['id'];
            if ((int) ($item['parent_id'] ?? 0) === $parent && $childId !== $id && !isset($descendants[$childId])) {
                $descendants[$childId] = $childId;
                $frontier[] = $childId;
            }
        }
    }
    return array_values($descendants);
}

function sr_site_menu_editor_validate(array $editor): void
{
    if (count($editor['menus']) > 100 || count($editor['items']) > 500) {
        throw new DomainException('편집 가능한 범위는 메뉴 묶음 100개, 항목 500개까지입니다.');
    }
    $keys = [];
    foreach ($editor['menus'] as $menu) {
        if (sr_site_menu_clean_key((string) $menu['menu_key']) === '') {
            throw new DomainException(sr_t('site_menu::action.admin.menu_key_invalid'));
        }
        if (isset($keys[$menu['menu_key']])) {
            throw new DomainException(sr_t('site_menu::action.admin.menu_key_duplicate'));
        }
        $keys[$menu['menu_key']] = true;
        if (trim((string) $menu['label']) === '' || !in_array($menu['status'], ['enabled', 'disabled'], true)) {
            throw new DomainException('메뉴 이름과 사용 상태를 확인해 주세요.');
        }
    }
    foreach ($editor['items'] as $item) {
        if (!isset($editor['menus'][$item['menu_id']])) {
            throw new DomainException(sr_t('site_menu::action.admin.menu_not_found'));
        }
        if (trim((string) $item['label']) === '') {
            throw new DomainException(sr_t('site_menu::action.admin.item_name_required'));
        }
        if ($item['url'] !== '' && sr_site_menu_clean_url((string) $item['url']) === '') {
            throw new DomainException(sr_t('site_menu::action.admin.item_url_invalid'));
        }
        if (!in_array($item['target'], ['self', 'blank'], true) || !in_array($item['status'], ['enabled', 'disabled'], true)) {
            throw new DomainException('항목의 링크 열기 방식과 사용 상태를 확인해 주세요.');
        }
        $visited = [(int) $item['id'] => true];
        $parent = (int) ($item['parent_id'] ?? 0);
        $depth = 1;
        while ($parent > 0) {
            if (isset($visited[$parent]) || !isset($editor['items'][$parent])
                || (int) $editor['items'][$parent]['menu_id'] !== (int) $item['menu_id']) {
                throw new DomainException(sr_t('site_menu::action.admin.parent_invalid'));
            }
            $visited[$parent] = true;
            if (++$depth > 3) {
                throw new DomainException(sr_t('site_menu::action.admin.descendant_depth_limit'));
            }
            $parent = (int) ($editor['items'][$parent]['parent_id'] ?? 0);
        }
    }
}

function sr_site_menu_editor_apply_orders(array $editor, mixed $orders, bool $complete = false): array
{
    if (!is_array($orders) || count($orders) > 500) {
        throw new DomainException(sr_t('site_menu::action.admin.sort_order_invalid'));
    }
    if ($complete && count($orders) !== count($editor['items'])) {
        throw new DomainException('항목 순서가 모두 전달되지 않았습니다. 새로고침한 뒤 다시 저장해 주세요.');
    }
    foreach ($orders as $id => $value) {
        if (!isset($editor['items'][$id]) || !is_scalar($value)
            || filter_var($value, FILTER_VALIDATE_INT) === false
            || (int) $value < -100000 || (int) $value > 50000) {
            throw new DomainException(sr_t('site_menu::action.admin.sort_order_invalid'));
        }
        if ((int) $editor['items'][$id]['sort_order'] !== (int) $value) {
            $editor['items'][$id]['sort_order'] = (int) $value;
            $editor['items'][$id]['updated_at'] = sr_now();
        }
    }
    return $editor;
}

function sr_site_menu_editor_change(PDO $pdo, array $editor, string $intent, array $input): array
{
    $text = static function (string $key) use ($input): string {
        if (isset($input[$key]) && !is_scalar($input[$key])) {
            throw new DomainException('입력 형식이 올바르지 않습니다.');
        }
        return trim((string) ($input[$key] ?? ''));
    };
    $now = sr_now();
    if ($intent === 'save_menu') {
        $original = $text('original_menu_key');
        $id = 0;
        foreach ($editor['menus'] as $menu) {
            if ($menu['menu_key'] === $original) {
                $id = (int) $menu['id'];
            }
        }
        if ($original !== '' && $id === 0) {
            throw new DomainException(sr_t('site_menu::action.admin.menu_edit_not_found'));
        }
        if ($id === 0) {
            $id = $editor['next_menu_id']++;
        }
        $editor['menus'][$id] = [
            'id' => $id, 'menu_key' => sr_site_menu_clean_key($text('menu_key')),
            'label' => sr_site_menu_clean_label($text('label')), 'status' => $text('status'),
            'created_at' => $editor['menus'][$id]['created_at'] ?? $now, 'updated_at' => $now,
        ];
    } elseif ($intent === 'save_item') {
        $id = (int) $text('item_id');
        $menuId = (int) $text('menu_id');
        if ($id > 0 && (!isset($editor['items'][$id]) || (int) $editor['items'][$id]['menu_id'] !== $menuId)) {
            throw new DomainException(sr_t('site_menu::action.admin.item_edit_not_found'));
        }
        if ($id <= 0) {
            $id = $editor['next_item_id']++;
        }
        $order = $text('sort_order');
        if (filter_var($order, FILTER_VALIDATE_INT) === false || (int) $order < -100000 || (int) $order > 50000) {
            throw new DomainException(sr_t('site_menu::action.admin.sort_order_invalid'));
        }
        $editor['items'][$id] = [
            'id' => $id, 'menu_id' => $menuId, 'parent_id' => (int) $text('parent_id') > 0 ? (int) $text('parent_id') : null,
            'label' => sr_site_menu_clean_label($text('label')), 'url' => $text('url'),
            'icon_name' => sr_site_menu_clean_icon_name($pdo, $text('icon_name')),
            'target' => $text('target'), 'status' => $text('status'), 'sort_order' => (int) $order,
            'created_at' => $editor['items'][$id]['created_at'] ?? $now, 'updated_at' => $now,
        ];
        if (strlen($editor['items'][$id]['url']) > 255) {
            throw new DomainException(sr_t('site_menu::action.admin.item_url_invalid'));
        }
    } elseif ($intent === 'delete_item') {
        $id = (int) $text('item_id');
        if (!isset($editor['items'][$id])) {
            throw new DomainException(sr_t('site_menu::action.admin.item_delete_not_found'));
        }
        $children = sr_site_menu_editor_descendants($editor['items'], $id);
        if ($children !== [] && $text('confirm_descendant_delete') !== '1') {
            throw new DomainException(sr_t('site_menu::action.admin.item_descendant_delete_confirmation_required'));
        }
        foreach (array_merge([$id], $children) as $deleteId) {
            unset($editor['items'][$deleteId]);
        }
    } elseif ($intent === 'delete_menu') {
        $id = (int) $text('menu_id');
        if (!isset($editor['menus'][$id])) {
            throw new DomainException(sr_t('site_menu::action.admin.menu_delete_not_found'));
        }
        $children = array_filter($editor['items'], static fn (array $item): bool => (int) $item['menu_id'] === $id);
        if ($children !== [] && $text('confirm_descendant_delete') !== '1') {
            throw new DomainException(sr_t('site_menu::action.admin.item_descendant_delete_confirmation_required'));
        }
        $editor['items'] = array_diff_key($editor['items'], $children);
        unset($editor['menus'][$id]);
    } else {
        throw new DomainException(sr_t('site_menu::action.admin.intent_invalid'));
    }
    sr_site_menu_editor_validate($editor);
    return $editor;
}

function sr_site_menu_editor_save(PDO $pdo, array $editor): void
{
    // Caller owns the transaction so saving and publishing can commit together.
    if (!$pdo->inTransaction()) {
        throw new LogicException('Site menu editor save requires a transaction.');
    }
    sr_site_menu_editor_validate($editor);
    if (sr_site_menu_editor_snapshot($pdo, true) !== $editor['base']) {
        throw new DomainException('다른 작업에서 저장된 초안이 바뀌었습니다. 변경 취소로 최신 초안을 불러온 뒤 다시 편집해 주세요.');
    }
    // Explicit owner tables and bounded rows; do not replace shared or public data here.
    foreach (['items' => 'sr_site_menu_draft_items', 'menus' => 'sr_site_menu_draft_menus'] as $kind => $table) {
        $stmt = $pdo->prepare('DELETE FROM ' . $table . ' WHERE id = :id');
        foreach (array_diff_key($editor['base'][$kind], $editor[$kind]) as $id => $_row) {
            $stmt->execute(['id' => $id]);
        }
    }
    foreach (['menus' => 'sr_site_menu_draft_menus', 'items' => 'sr_site_menu_draft_items'] as $kind => $table) {
        $columns = $kind === 'menus'
            ? ['id', 'menu_key', 'label', 'status', 'created_at', 'updated_at']
            : ['id', 'menu_id', 'parent_id', 'label', 'url', 'icon_name', 'target', 'status', 'sort_order', 'created_at', 'updated_at'];
        $insert = $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (:' . implode(', :', $columns) . ')');
        $assignments = array_map(static fn (string $column): string => $column . ' = :' . $column, array_slice($columns, 1));
        $update = $pdo->prepare('UPDATE ' . $table . ' SET ' . implode(', ', $assignments) . ' WHERE id = :id');
        foreach ($editor[$kind] as $id => $row) {
            if (isset($editor['base'][$kind][$id]) && $row === $editor['base'][$kind][$id]) {
                continue;
            }
            $params = array_intersect_key($row, array_flip($columns));
            $statement = isset($editor['base'][$kind][$id]) ? $update : $insert;
            $statement->execute($params);
        }
    }
}

function sr_site_menu_editor_delete_draft(PDO $pdo, array $editor, string $confirmation): array
{
    if ($confirmation !== '1') {
        throw new DomainException('임시저장한 변경과 현재 편집 내용을 삭제할지 확인해 주세요.');
    }
    if (!$pdo->inTransaction()) {
        throw new LogicException('Site menu draft deletion requires a transaction.');
    }
    if (sr_site_menu_editor_snapshot($pdo, true) !== $editor['base']) {
        throw new DomainException('다른 작업에서 저장된 초안이 바뀌었습니다. 변경 취소로 최신 초안을 불러온 뒤 다시 삭제해 주세요.');
    }
    $suffix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $menus = $pdo->query('SELECT id, menu_key, label, status, created_at, updated_at FROM sr_site_menus ORDER BY id ASC LIMIT 101' . $suffix)->fetchAll(PDO::FETCH_ASSOC);
    $items = $pdo->query('SELECT id, menu_id, parent_id, label, url, icon_name, target, status, sort_order, created_at, updated_at FROM sr_site_menu_items ORDER BY id ASC LIMIT 501' . $suffix)->fetchAll(PDO::FETCH_ASSOC);
    $published = ['menus' => array_column($menus, null, 'id'), 'items' => array_column($items, null, 'id')];
    sr_site_menu_editor_validate($published);

    // Discard saved changes, including deleted/renamed menu keys; leave public rows intact.
    sr_execute_sql_statement($pdo, 'DELETE FROM sr_site_menu_draft_items');
    sr_execute_sql_statement($pdo, 'DELETE FROM sr_site_menu_draft_menus');
    $insertMenu = $pdo->prepare(
        'INSERT INTO sr_site_menu_draft_menus (id, menu_key, label, status, created_at, updated_at)
         VALUES (:id, :menu_key, :label, :status, :created_at, :updated_at)'
    );
    foreach ($menus as $menu) {
        $insertMenu->execute($menu);
    }
    $insertItem = $pdo->prepare(
        'INSERT INTO sr_site_menu_draft_items (id, menu_id, parent_id, label, url, icon_name, target, status, sort_order, created_at, updated_at)
         VALUES (:id, :menu_id, :parent_id, :label, :url, :icon_name, :target, :status, :sort_order, :created_at, :updated_at)'
    );
    foreach ($items as $item) {
        $insertItem->execute($item);
    }
    return $published;
}
