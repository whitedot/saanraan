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
        'series' => 0,
        'series_items' => 0,
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

    if (sr_community_series_supported($pdo)) {
        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT s.id) FROM sr_community_series s INNER JOIN sr_community_series_items si ON si.series_id = s.id INNER JOIN sr_community_posts p ON p.id = si.post_id WHERE p.board_id = :board_id');
        $stmt->execute(['board_id' => $boardId]);
        $counts['series'] = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM sr_community_series_items si INNER JOIN sr_community_posts p ON p.id = si.post_id WHERE p.board_id = :board_id');
        $stmt->execute(['board_id' => $boardId]);
        $counts['series_items'] = (int) $stmt->fetchColumn();
    }

    return $counts;
}

function sr_community_board_copy_series_suggestions(PDO $pdo, int $boardId): array
{
    if ($boardId < 1 || !sr_community_series_supported($pdo)) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT DISTINCT s.id, s.title
         FROM sr_community_series s
         INNER JOIN sr_community_series_items si ON si.series_id = s.id
         INNER JOIN sr_community_posts p ON p.id = si.post_id
         WHERE s.board_id = :board_id
           AND p.board_id = :board_id_for_posts
         ORDER BY s.id ASC'
    );
    $stmt->execute(['board_id' => $boardId, 'board_id_for_posts' => $boardId]);

    return array_map(static function (array $row): array {
        return [
            'series_id' => (int) $row['id'],
            'title' => sr_community_clean_single_line((string) ($row['title'] ?? '') . ' 복사본', 160),
        ];
    }, $stmt->fetchAll());
}

function sr_community_board_copy_series_validate_options(PDO $pdo, int $boardId, array $values): array
{
    if (empty($values['copy_series'])) {
        return [];
    }
    $suggestions = sr_community_board_copy_series_suggestions($pdo, $boardId);
    if ($suggestions === []) {
        return [];
    }
    $titles = is_array($values['series_titles'] ?? null) ? $values['series_titles'] : [];
    $errors = [];
    foreach ($suggestions as $suggestion) {
        $seriesId = (int) $suggestion['series_id'];
        $title = sr_community_clean_single_line((string) ($titles[(string) $seriesId] ?? $titles[$seriesId] ?? $suggestion['title']), 160);
        if ($title === '') {
            $errors[] = '새 커뮤니티 시리즈 제목을 입력하세요.';
        }
        $titles[(string) $seriesId] = $title;
    }
    $GLOBALS['sr_community_board_copy_series_options'][$boardId] = ['series_titles' => $titles];

    return $errors;
}

function sr_community_board_copy_series_option_title(int $sourceBoardId, int $seriesId): string
{
    if ($sourceBoardId < 1 || $seriesId < 1 || !isset($GLOBALS['sr_community_board_copy_series_options']) || !is_array($GLOBALS['sr_community_board_copy_series_options'])) {
        return '';
    }
    $options = $GLOBALS['sr_community_board_copy_series_options'][$sourceBoardId] ?? null;
    if (!is_array($options)) {
        return '';
    }
    $titles = $options['series_titles'] ?? null;
    if (!is_array($titles)) {
        return '';
    }

    return sr_community_clean_single_line((string) ($titles[(string) $seriesId] ?? $titles[$seriesId] ?? ''), 160);
}

function sr_community_board_copy_limit_errors(array $counts): array
{
    return array_merge(
        sr_community_board_copy_batch_threshold_errors($counts),
        sr_community_board_copy_batch_block_errors($counts)
    );
}

function sr_community_board_copy_batch_threshold_errors(array $counts): array
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

    return $errors;
}

function sr_community_board_copy_batch_block_errors(array $counts): array
{
    $errors = [];
    if (!empty($counts['unsupported_storage'])) {
        $errors[] = '현재 저장소 driver에서는 첨부파일 포함 복사를 지원하지 않습니다.';
    }
    if (($counts['missing_files'] ?? []) !== []) {
        $errors[] = '원본 첨부파일을 확인할 수 없어 복사를 시작하지 않았습니다.';
    }

    return $errors;
}

function sr_community_board_copy_batch_errors(array $counts): array
{
    $errors = sr_community_board_copy_batch_block_errors($counts);
    if ($errors !== []) {
        return $errors;
    }
    if (sr_community_board_copy_batch_threshold_errors($counts) === []) {
        $errors[] = '배치 복사가 필요한 상한 초과 항목이 없습니다.';
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
        $errors = array_merge($errors, sr_community_board_copy_series_validate_options($pdo, $sourceBoardId, $values));
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
            $postMap = sr_community_copy_board_posts($pdo, $sourceBoardId, $newBoardId, $categoryMap, $createdFiles, $now);
            if (!empty($values['copy_series'])) {
                sr_community_copy_board_series($pdo, $sourceBoardId, $newBoardId, $postMap, $accountId, $now);
            }
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

function sr_community_copy_board_posts(PDO $pdo, int $sourceBoardId, int $newBoardId, array $categoryMap, array &$createdFiles, string $now): array
{
    $postMap = [];
    $stmt = $pdo->prepare('SELECT * FROM sr_community_posts WHERE board_id = :board_id ORDER BY id ASC');
    $stmt->execute(['board_id' => $sourceBoardId]);
    $categorySupported = sr_community_categories_supported($pdo);
    $categoryColumnSql = $categorySupported ? 'category_id, ' : '';
    $categoryValueSql = $categorySupported ? ':category_id, ' : '';
    $authorSnapshotColumnSql = sr_community_author_public_name_snapshot_column_exists($pdo, 'sr_community_posts') ? 'author_public_name_snapshot, ' : '';
    $authorSnapshotValueSql = $authorSnapshotColumnSql !== '' ? ':author_public_name_snapshot, ' : '';
    $insertPost = $pdo->prepare(
        'INSERT INTO sr_community_posts
            (board_id, ' . $categoryColumnSql . 'author_account_id, ' . $authorSnapshotColumnSql . 'title, body_text, body_format, status, view_count, last_commented_at, created_at, updated_at)
         VALUES
            (:board_id, ' . $categoryValueSql . ':author_account_id, ' . $authorSnapshotValueSql . ':title, :body_text, :body_format, :status, 0, :last_commented_at, :created_at, :updated_at)'
    );
    foreach ($stmt->fetchAll() as $post) {
        $sourceCategoryId = (int) ($post['category_id'] ?? 0);
        $params = [
            'board_id' => $newBoardId,
            'author_account_id' => (int) $post['author_account_id'],
            'title' => (string) $post['title'],
            'body_text' => (string) $post['body_text'],
            'body_format' => (string) ($post['body_format'] ?? 'plain'),
            'status' => (string) $post['status'],
            'last_commented_at' => $post['last_commented_at'] ?? null,
            'created_at' => (string) $post['created_at'],
            'updated_at' => (string) $post['updated_at'],
        ];
        if ($categorySupported) {
            $params['category_id'] = $sourceCategoryId > 0 && isset($categoryMap[$sourceCategoryId]) ? $categoryMap[$sourceCategoryId] : null;
        }
        if ($authorSnapshotColumnSql !== '') {
            $params['author_public_name_snapshot'] = (string) ($post['author_public_name_snapshot'] ?? '');
        }
        $insertPost->execute($params);
        $postMap[(int) $post['id']] = (int) $pdo->lastInsertId();
    }

    sr_community_copy_board_comments($pdo, $postMap);
    sr_community_copy_board_link_refs($pdo, $postMap, $now);
    sr_community_copy_board_attachments($pdo, $postMap, $createdFiles);

    return $postMap;
}

function sr_community_copy_board_comments(PDO $pdo, array $postMap): void
{
    if ($postMap === []) {
        return;
    }
    $authorSnapshotColumnSql = sr_community_author_public_name_snapshot_column_exists($pdo, 'sr_community_comments') ? 'author_public_name_snapshot, ' : '';
    $authorSnapshotValueSql = $authorSnapshotColumnSql !== '' ? ':author_public_name_snapshot, ' : '';
    $insert = $pdo->prepare(
        'INSERT INTO sr_community_comments
            (post_id, author_account_id, ' . $authorSnapshotColumnSql . 'body_text, status, created_at, updated_at)
         VALUES
            (:post_id, :author_account_id, ' . $authorSnapshotValueSql . ':body_text, :status, :created_at, :updated_at)'
    );
    foreach ($postMap as $sourcePostId => $newPostId) {
        $stmt = $pdo->prepare('SELECT * FROM sr_community_comments WHERE post_id = :post_id ORDER BY id ASC');
        $stmt->execute(['post_id' => (int) $sourcePostId]);
        foreach ($stmt->fetchAll() as $comment) {
            $params = [
                'post_id' => (int) $newPostId,
                'author_account_id' => (int) $comment['author_account_id'],
                'body_text' => (string) $comment['body_text'],
                'status' => (string) $comment['status'],
                'created_at' => (string) $comment['created_at'],
                'updated_at' => (string) $comment['updated_at'],
            ];
            if ($authorSnapshotColumnSql !== '') {
                $params['author_public_name_snapshot'] = (string) ($comment['author_public_name_snapshot'] ?? '');
            }
            $insert->execute($params);
        }
    }
}

function sr_community_copy_board_series(PDO $pdo, int $sourceBoardId, int $newBoardId, array $postMap, int $accountId, string $now): array
{
    if ($sourceBoardId < 1 || $newBoardId < 1 || $postMap === [] || !sr_community_series_supported($pdo)) {
        return ['series' => 0, 'items' => 0, 'excluded_items' => 0];
    }

    $stmt = $pdo->prepare(
        'SELECT DISTINCT s.*
         FROM sr_community_series s
         INNER JOIN sr_community_series_items si ON si.series_id = s.id
         INNER JOIN sr_community_posts p ON p.id = si.post_id
         WHERE s.board_id = :board_id
           AND p.board_id = :board_id_for_posts
         ORDER BY s.id ASC'
    );
    $stmt->execute(['board_id' => $sourceBoardId, 'board_id_for_posts' => $sourceBoardId]);

    $insertSeries = $pdo->prepare(
        'INSERT INTO sr_community_series
            (board_id, owner_account_id, title, description, status, visibility, admin_note, created_by, updated_by, moderated_by, moderated_at, created_at, updated_at)
         VALUES
            (:board_id, :owner_account_id, :title, :description, :status, :visibility, :admin_note, :created_by, :updated_by, :moderated_by, :moderated_at, :created_at, :updated_at)'
    );
    $insertItem = $pdo->prepare(
        'INSERT INTO sr_community_series_items
            (series_id, post_id, active_post_id, episode_label, item_status, sort_order, created_by, created_at, updated_at)
         VALUES
            (:series_id, :post_id, :active_post_id, :episode_label, :item_status, :sort_order, :created_by, :created_at, :updated_at)'
    );

    $result = ['series' => 0, 'items' => 0, 'excluded_items' => 0];
    foreach ($stmt->fetchAll() as $series) {
        $insertSeries->execute([
            'board_id' => $newBoardId,
            'owner_account_id' => (int) ($series['owner_account_id'] ?? 0),
            'title' => sr_community_board_copy_series_option_title($sourceBoardId, (int) $series['id']) ?: sr_community_clean_single_line((string) ($series['title'] ?? '') . ' 복사본', 160),
            'description' => (string) ($series['description'] ?? ''),
            'status' => (string) ($series['status'] ?? 'active'),
            'visibility' => (string) ($series['visibility'] ?? 'public'),
            'admin_note' => null,
            'created_by' => $accountId,
            'updated_by' => $accountId,
            'moderated_by' => null,
            'moderated_at' => null,
            'created_at' => (string) ($series['created_at'] ?? $now),
            'updated_at' => (string) ($series['updated_at'] ?? $now),
        ]);
        $newSeriesId = (int) $pdo->lastInsertId();
        $result['series']++;

        $items = $pdo->prepare('SELECT * FROM sr_community_series_items WHERE series_id = :series_id ORDER BY sort_order ASC, id ASC');
        $items->execute(['series_id' => (int) $series['id']]);
        foreach ($items->fetchAll() as $item) {
            $sourcePostId = (int) ($item['post_id'] ?? 0);
            if (!isset($postMap[$sourcePostId])) {
                $result['excluded_items']++;
                continue;
            }
            $newPostId = (int) $postMap[$sourcePostId];
            $insertItem->execute([
                'series_id' => $newSeriesId,
                'post_id' => $newPostId,
                'active_post_id' => $newPostId,
                'episode_label' => (string) ($item['episode_label'] ?? ''),
                'item_status' => (string) ($item['item_status'] ?? 'active'),
                'sort_order' => (int) ($item['sort_order'] ?? 0),
                'created_by' => $item['created_by'] !== null ? (int) $item['created_by'] : null,
                'created_at' => (string) ($item['created_at'] ?? $now),
                'updated_at' => (string) ($item['updated_at'] ?? $now),
            ]);
            $result['items']++;
        }
    }

    return $result;
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
