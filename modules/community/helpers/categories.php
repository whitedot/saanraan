<?php

declare(strict_types=1);

function sr_community_category_key_is_valid(string $categoryKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $categoryKey) === 1
        && !in_array($categoryKey, sr_community_reserved_category_keys(), true);
}

function sr_community_reserved_category_keys(): array
{
    return ['all', 'none', 'new', 'edit', 'delete', 'admin'];
}

function sr_community_category_statuses(): array
{
    return ['enabled', 'disabled'];
}

function sr_community_categories_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_community_categories LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_community_categories_supported(PDO $pdo): bool
{
    return sr_community_categories_table_exists($pdo);
}

function sr_community_categories(PDO $pdo, int $boardId, bool $enabledOnly = false): array
{
    if ($boardId < 1 || !sr_community_categories_supported($pdo)) {
        return [];
    }

    $where = 'board_id = :board_id';
    if ($enabledOnly) {
        $where .= " AND status = 'enabled'";
    }

    $stmt = $pdo->prepare(
        'SELECT id, board_id, category_key, title, description, status, sort_order, created_at, updated_at
         FROM sr_community_categories
         WHERE ' . $where . '
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['board_id' => $boardId]);

    return $stmt->fetchAll();
}

function sr_community_category_by_id(PDO $pdo, int $categoryId): ?array
{
    if ($categoryId < 1 || !sr_community_categories_supported($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, board_id, category_key, title, description, status, sort_order, created_at, updated_at
         FROM sr_community_categories
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $categoryId]);
    $category = $stmt->fetch();

    return is_array($category) ? $category : null;
}

function sr_community_category_by_key(PDO $pdo, int $boardId, string $categoryKey): ?array
{
    if ($boardId < 1 || $categoryKey === '' || !sr_community_categories_supported($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, board_id, category_key, title, description, status, sort_order, created_at, updated_at
         FROM sr_community_categories
         WHERE board_id = :board_id
           AND category_key = :category_key
         LIMIT 1'
    );
    $stmt->execute([
        'board_id' => $boardId,
        'category_key' => $categoryKey,
    ]);
    $category = $stmt->fetch();

    return is_array($category) ? $category : null;
}

function sr_community_create_category(PDO $pdo, int $boardId, array $values): int
{
    if (!sr_community_categories_supported($pdo)) {
        throw new RuntimeException('Community category schema is not available.');
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_categories
            (board_id, category_key, title, description, status, sort_order, created_at, updated_at)
         VALUES
            (:board_id, :category_key, :title, :description, :status, :sort_order, :created_at, :updated_at)'
    );
    $stmt->execute([
        'board_id' => $boardId,
        'category_key' => (string) $values['category_key'],
        'title' => trim((string) $values['title']),
        'description' => trim((string) ($values['description'] ?? '')),
        'status' => (string) $values['status'],
        'sort_order' => (int) ($values['sort_order'] ?? 0),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_community_update_category(PDO $pdo, int $categoryId, array $values): void
{
    if (!sr_community_categories_supported($pdo)) {
        throw new RuntimeException('Community category schema is not available.');
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_categories
         SET title = :title,
             description = :description,
             status = :status,
             sort_order = :sort_order,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'title' => trim((string) $values['title']),
        'description' => trim((string) ($values['description'] ?? '')),
        'status' => (string) $values['status'],
        'sort_order' => (int) ($values['sort_order'] ?? 0),
        'updated_at' => sr_now(),
        'id' => $categoryId,
    ]);
}

function sr_community_delete_category(PDO $pdo, int $categoryId): bool
{
    if (!sr_community_categories_supported($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sr_community_posts WHERE category_id = :category_id');
    $stmt->execute(['category_id' => $categoryId]);
    if ((int) $stmt->fetchColumn() > 0) {
        return false;
    }

    $stmt = $pdo->prepare('DELETE FROM sr_community_categories WHERE id = :id');
    $stmt->execute(['id' => $categoryId]);

    return $stmt->rowCount() > 0;
}

function sr_community_board_category_required(PDO $pdo, int $boardId): bool
{
    return sr_community_categories_supported($pdo)
        && sr_community_board_category_enabled($pdo, $boardId)
        && (string) (sr_community_board_setting_value($pdo, $boardId, 'category_required') ?? '0') === '1';
}

function sr_community_board_category_enabled(PDO $pdo, int $boardId): bool
{
    return sr_community_categories_supported($pdo)
        && (string) (sr_community_board_setting_value($pdo, $boardId, 'category_enabled') ?? '1') === '1';
}

function sr_community_post_category_validation_errors(PDO $pdo, array $board, array $values, ?array $existingPost = null): array
{
    if (!sr_community_categories_supported($pdo) || !sr_community_board_category_enabled($pdo, (int) ($board['id'] ?? 0))) {
        return [];
    }

    $errors = [];
    $boardId = (int) ($board['id'] ?? 0);
    $categoryId = (int) ($values['category_id'] ?? 0);
    $required = sr_community_board_category_required($pdo, $boardId);
    $currentCategoryId = is_array($existingPost) ? (int) ($existingPost['category_id'] ?? 0) : 0;

    if ($categoryId < 1) {
        if ($required) {
            $errors[] = '카테고리를 선택해 주세요.';
        }
        return $errors;
    }

    $category = sr_community_category_by_id($pdo, $categoryId);
    if (!is_array($category) || (int) $category['board_id'] !== $boardId) {
        return ['게시판에 속한 카테고리만 선택할 수 있습니다.'];
    }

    if ((string) $category['status'] !== 'enabled' && $categoryId !== $currentCategoryId) {
        $errors[] = '비활성 카테고리는 새로 선택할 수 없습니다.';
    }

    return $errors;
}
