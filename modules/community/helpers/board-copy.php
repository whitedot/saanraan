<?php

declare(strict_types=1);

function sr_community_board_copy_modes(): array
{
    return [
        'settings' => '설정만 복사',
        'full' => '게시글/댓글/첨부파일 포함',
    ];
}

function sr_community_board_copy_limits(): array
{
    return [
        'posts' => 500,
        'comments' => 5000,
        'link_refs' => 5000,
        'attachments' => 500,
        'bytes' => 314572800,
    ];
}

function sr_community_board_copy_suggestion(array $board): array
{
    $key = strtolower((string) ($board['board_key'] ?? 'board')) . '_copy';
    if (!sr_community_board_key_is_valid($key)) {
        $key = 'board_copy';
    }

    return [
        'board_key' => $key,
        'title' => sr_community_clean_single_line((string) ($board['title'] ?? '') . ' 복사본', 120),
    ];
}

function sr_community_clean_single_line(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $value)) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function sr_community_board_copy_counts(PDO $pdo, int $boardId): array
{
    $counts = [
        'posts' => 0,
        'comments' => 0,
        'link_refs' => 0,
        'attachments' => 0,
        'bytes' => 0,
        'unsupported_storage' => false,
        'missing_files' => [],
    ];
    if ($boardId < 1) {
        return $counts;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sr_community_posts WHERE board_id = :board_id');
    $stmt->execute(['board_id' => $boardId]);
    $counts['posts'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_community_comments c
         INNER JOIN sr_community_posts p ON p.id = c.post_id
         WHERE p.board_id = :board_id'
    );
    $stmt->execute(['board_id' => $boardId]);
    $counts['comments'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_community_link_refs r
         INNER JOIN sr_community_posts p ON p.id = r.post_id
         WHERE p.board_id = :board_id'
    );
    $stmt->execute(['board_id' => $boardId]);
    $counts['link_refs'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT a.*
         FROM sr_community_attachments a
         INNER JOIN sr_community_posts p ON p.id = a.post_id
         WHERE p.board_id = :board_id'
    );
    $stmt->execute(['board_id' => $boardId]);
    foreach ($stmt->fetchAll() as $attachment) {
        $counts['attachments']++;
        $counts['bytes'] += (int) ($attachment['size_bytes'] ?? 0);
        $driver = sr_community_attachment_storage_driver($attachment);
        $key = sr_community_attachment_storage_key($attachment);
        if ($driver !== 'local') {
            $counts['unsupported_storage'] = true;
            continue;
        }
        if ($key === '' || sr_storage_head($driver, $key) === null) {
            $counts['missing_files'][] = (int) ($attachment['id'] ?? 0);
        }
    }

    return $counts;
}

function sr_community_board_copy_limit_errors(array $counts): array
{
    $limits = sr_community_board_copy_limits();
    $errors = [];
    foreach (['posts', 'comments', 'link_refs', 'attachments'] as $key) {
        if ((int) ($counts[$key] ?? 0) > (int) $limits[$key]) {
            $errors[] = '동기 복사 상한을 초과했습니다: ' . $key;
        }
    }
    if ((int) ($counts['bytes'] ?? 0) > (int) $limits['bytes']) {
        $errors[] = '첨부 총량이 동기 복사 상한을 초과했습니다.';
    }
    if (!empty($counts['unsupported_storage'])) {
        $errors[] = '현재 저장소 driver에서는 첨부파일 포함 복사를 지원하지 않습니다.';
    }
    if (($counts['missing_files'] ?? []) !== []) {
        $errors[] = '원본 첨부파일을 확인할 수 없어 복사를 시작하지 않았습니다.';
    }

    return $errors;
}

function sr_community_copy_board(PDO $pdo, int $sourceBoardId, array $values, int $accountId): int
{
    $source = sr_community_board_by_id($pdo, $sourceBoardId);
    if (!is_array($source)) {
        throw new RuntimeException('복사할 게시판을 찾을 수 없습니다.');
    }

    $mode = (string) ($values['mode'] ?? 'settings');
    if (!isset(sr_community_board_copy_modes()[$mode])) {
        throw new InvalidArgumentException('복사 범위가 올바르지 않습니다.');
    }
    $boardKey = strtolower(trim((string) ($values['board_key'] ?? '')));
    $title = sr_community_clean_single_line((string) ($values['title'] ?? ''), 120);
    $errors = [];
    if (!sr_community_board_key_is_valid($boardKey)) {
        $errors[] = '게시판 key는 소문자 영문, 숫자, _만 사용할 수 있습니다.';
    } elseif (is_array(sr_community_board_by_key($pdo, $boardKey))) {
        $errors[] = '이미 사용 중인 게시판 key입니다.';
    }
    if ($title === '') {
        $errors[] = '새 게시판 제목을 입력하세요.';
    }
    if ($mode === 'full') {
        $errors = array_merge($errors, sr_community_board_copy_limit_errors(sr_community_board_copy_counts($pdo, $sourceBoardId)));
    }
    if ($errors !== []) {
        throw new InvalidArgumentException(implode("\n", $errors));
    }

    $createdFiles = [];
    $now = sr_now();
    $pdo->beginTransaction();
    try {
        $newBoardId = sr_community_create_board($pdo, [
            'board_group_id' => (int) ($source['board_group_id'] ?? 0),
            'board_key' => $boardKey,
            'title' => $title,
            'description' => (string) ($source['description'] ?? ''),
            'status' => 'disabled',
            'read_policy' => (string) ($source['read_policy'] ?? 'public'),
            'write_policy' => (string) ($source['write_policy'] ?? 'member'),
            'comment_policy' => (string) ($source['comment_policy'] ?? 'member'),
            'image_uploads_enabled' => (int) ($source['image_uploads_enabled'] ?? 1),
            'sort_order' => (int) ($source['sort_order'] ?? 0),
        ]);

        sr_community_copy_board_settings($pdo, $sourceBoardId, $newBoardId, $now);
        $categoryMap = sr_community_copy_board_categories($pdo, $sourceBoardId, $newBoardId, $now);
        if ($mode === 'full') {
            sr_community_copy_board_posts($pdo, $sourceBoardId, $newBoardId, $categoryMap, $createdFiles, $now);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($createdFiles as $file) {
            sr_storage_delete((string) $file['driver'], (string) $file['key']);
        }
        throw $exception;
    }

    return $newBoardId;
}

function sr_community_copy_board_settings(PDO $pdo, int $sourceBoardId, int $newBoardId, string $now): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_board_settings (board_id, setting_key, setting_value, value_type, created_at, updated_at)
         SELECT :new_board_id, setting_key, setting_value, value_type, :created_at, :updated_at
         FROM sr_community_board_settings
         WHERE board_id = :source_board_id'
    );
    $stmt->execute(['new_board_id' => $newBoardId, 'created_at' => $now, 'updated_at' => $now, 'source_board_id' => $sourceBoardId]);

    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_board_setting_sources (board_id, setting_key, source, created_at, updated_at)
         SELECT :new_board_id, setting_key, source, :created_at, :updated_at
         FROM sr_community_board_setting_sources
         WHERE board_id = :source_board_id'
    );
    $stmt->execute(['new_board_id' => $newBoardId, 'created_at' => $now, 'updated_at' => $now, 'source_board_id' => $sourceBoardId]);
}

function sr_community_copy_board_categories(PDO $pdo, int $sourceBoardId, int $newBoardId, string $now): array
{
    $map = [];
    if (!sr_community_categories_supported($pdo)) {
        return $map;
    }
    $stmt = $pdo->prepare('SELECT * FROM sr_community_categories WHERE board_id = :board_id ORDER BY sort_order ASC, id ASC');
    $stmt->execute(['board_id' => $sourceBoardId]);
    $insert = $pdo->prepare(
        'INSERT INTO sr_community_categories (board_id, category_key, title, description, status, sort_order, created_at, updated_at)
         VALUES (:board_id, :category_key, :title, :description, :status, :sort_order, :created_at, :updated_at)'
    );
    foreach ($stmt->fetchAll() as $category) {
        $insert->execute([
            'board_id' => $newBoardId,
            'category_key' => (string) $category['category_key'],
            'title' => (string) $category['title'],
            'description' => (string) ($category['description'] ?? ''),
            'status' => (string) $category['status'],
            'sort_order' => (int) $category['sort_order'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $map[(int) $category['id']] = (int) $pdo->lastInsertId();
    }

    return $map;
}

function sr_community_copy_board_posts(PDO $pdo, int $sourceBoardId, int $newBoardId, array $categoryMap, array &$createdFiles, string $now): void
{
    $postMap = [];
    $stmt = $pdo->prepare('SELECT * FROM sr_community_posts WHERE board_id = :board_id ORDER BY id ASC');
    $stmt->execute(['board_id' => $sourceBoardId]);
    $insertPost = $pdo->prepare(
        'INSERT INTO sr_community_posts
            (board_id, category_id, author_account_id, author_public_name_snapshot, title, body_text, body_format, status, view_count, last_commented_at, created_at, updated_at)
         VALUES
            (:board_id, :category_id, :author_account_id, :author_public_name_snapshot, :title, :body_text, :body_format, :status, 0, :last_commented_at, :created_at, :updated_at)'
    );
    foreach ($stmt->fetchAll() as $post) {
        $sourceCategoryId = (int) ($post['category_id'] ?? 0);
        $insertPost->execute([
            'board_id' => $newBoardId,
            'category_id' => $sourceCategoryId > 0 && isset($categoryMap[$sourceCategoryId]) ? $categoryMap[$sourceCategoryId] : null,
            'author_account_id' => (int) $post['author_account_id'],
            'author_public_name_snapshot' => (string) ($post['author_public_name_snapshot'] ?? ''),
            'title' => (string) $post['title'],
            'body_text' => (string) $post['body_text'],
            'body_format' => (string) ($post['body_format'] ?? 'plain'),
            'status' => (string) $post['status'],
            'last_commented_at' => $post['last_commented_at'] ?? null,
            'created_at' => (string) $post['created_at'],
            'updated_at' => (string) $post['updated_at'],
        ]);
        $postMap[(int) $post['id']] = (int) $pdo->lastInsertId();
    }

    sr_community_copy_board_comments($pdo, $postMap);
    sr_community_copy_board_link_refs($pdo, $postMap, $now);
    sr_community_copy_board_attachments($pdo, $postMap, $createdFiles);
}

function sr_community_copy_board_comments(PDO $pdo, array $postMap): void
{
    if ($postMap === []) {
        return;
    }
    $insert = $pdo->prepare(
        'INSERT INTO sr_community_comments
            (post_id, author_account_id, author_public_name_snapshot, body_text, status, created_at, updated_at)
         VALUES
            (:post_id, :author_account_id, :author_public_name_snapshot, :body_text, :status, :created_at, :updated_at)'
    );
    foreach ($postMap as $sourcePostId => $newPostId) {
        $stmt = $pdo->prepare('SELECT * FROM sr_community_comments WHERE post_id = :post_id ORDER BY id ASC');
        $stmt->execute(['post_id' => (int) $sourcePostId]);
        foreach ($stmt->fetchAll() as $comment) {
            $insert->execute([
                'post_id' => (int) $newPostId,
                'author_account_id' => (int) $comment['author_account_id'],
                'author_public_name_snapshot' => (string) ($comment['author_public_name_snapshot'] ?? ''),
                'body_text' => (string) $comment['body_text'],
                'status' => (string) $comment['status'],
                'created_at' => (string) $comment['created_at'],
                'updated_at' => (string) $comment['updated_at'],
            ]);
        }
    }
}

function sr_community_copy_board_link_refs(PDO $pdo, array $postMap, string $now): void
{
    $insert = $pdo->prepare(
        'INSERT IGNORE INTO sr_community_link_refs
            (post_id, target_module, target_entity_type, target_entity_id, slot_key, variant, label, sort_order, created_by, created_at, updated_at)
         VALUES
            (:post_id, :target_module, :target_entity_type, :target_entity_id, :slot_key, :variant, :label, :sort_order, :created_by, :created_at, :updated_at)'
    );
    foreach ($postMap as $sourcePostId => $newPostId) {
        $stmt = $pdo->prepare('SELECT * FROM sr_community_link_refs WHERE post_id = :post_id ORDER BY sort_order ASC, id ASC');
        $stmt->execute(['post_id' => (int) $sourcePostId]);
        foreach ($stmt->fetchAll() as $ref) {
            $insert->execute([
                'post_id' => (int) $newPostId,
                'target_module' => (string) $ref['target_module'],
                'target_entity_type' => (string) $ref['target_entity_type'],
                'target_entity_id' => (string) $ref['target_entity_id'],
                'slot_key' => (string) $ref['slot_key'],
                'variant' => (string) $ref['variant'],
                'label' => (string) ($ref['label'] ?? ''),
                'sort_order' => (int) $ref['sort_order'],
                'created_by' => $ref['created_by'] !== null ? (int) $ref['created_by'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

function sr_community_copy_board_attachments(PDO $pdo, array $postMap, array &$createdFiles): void
{
    $insert = $pdo->prepare(
        'INSERT INTO sr_community_attachments
            (post_id, uploader_account_id, original_name, stored_name, storage_path, storage_driver, storage_key, mime_type, size_bytes, checksum_sha256, width, height, status, created_at)
         VALUES
            (:post_id, :uploader_account_id, :original_name, :stored_name, :storage_path, :storage_driver, :storage_key, :mime_type, :size_bytes, :checksum_sha256, :width, :height, :status, :created_at)'
    );
    foreach ($postMap as $sourcePostId => $newPostId) {
        $stmt = $pdo->prepare('SELECT * FROM sr_community_attachments WHERE post_id = :post_id ORDER BY id ASC');
        $stmt->execute(['post_id' => (int) $sourcePostId]);
        foreach ($stmt->fetchAll() as $attachment) {
            $driver = sr_community_attachment_storage_driver($attachment);
            $sourceKey = sr_community_attachment_storage_key($attachment);
            $extension = strtolower(pathinfo((string) ($attachment['stored_name'] ?: $attachment['original_name']), PATHINFO_EXTENSION));
            $extension = preg_match('/\A[a-z0-9]{1,16}\z/', $extension) === 1 ? $extension : 'bin';
            $datePath = date('Y/m');
            $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
            $targetKey = 'community/attachments/' . $datePath . '/' . $storedName;
            sr_storage_copy($driver, $sourceKey, $targetKey);
            $createdFiles[] = ['driver' => $driver, 'key' => $targetKey];
            $insert->execute([
                'post_id' => (int) $newPostId,
                'uploader_account_id' => (int) $attachment['uploader_account_id'],
                'original_name' => (string) $attachment['original_name'],
                'stored_name' => $storedName,
                'storage_path' => 'storage/' . $targetKey,
                'storage_driver' => $driver,
                'storage_key' => $targetKey,
                'mime_type' => (string) $attachment['mime_type'],
                'size_bytes' => (int) $attachment['size_bytes'],
                'checksum_sha256' => (string) $attachment['checksum_sha256'],
                'width' => $attachment['width'] !== null ? (int) $attachment['width'] : null,
                'height' => $attachment['height'] !== null ? (int) $attachment['height'] : null,
                'status' => (string) ($attachment['status'] ?? 'active'),
                'created_at' => (string) $attachment['created_at'],
            ]);
        }
    }
}
