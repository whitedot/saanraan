<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_community_board_copy_modes(): array
{
    return [
        'settings' => '설정만 복사',
        'full' => '게시글/댓글/첨부파일 포함',
    ];
}

function sr_community_board_copy_scope_options(): array
{
    return [
        'all' => '전체',
        'settings' => '설정',
        'posts_comments' => '게시글+댓글',
        'attachments' => '첨부파일',
        'series' => '시리즈',
    ];
}

function sr_community_board_copy_scope_item_keys(): array
{
    return ['settings', 'posts_comments', 'attachments', 'series'];
}

function sr_community_board_copy_scope_values(array $values): array
{
    $scope = [];
    if (array_key_exists('copy_scope', $values)) {
        $rawScope = is_array($values['copy_scope']) ? $values['copy_scope'] : [(string) $values['copy_scope']];
        foreach ($rawScope as $rawValue) {
            $scope[] = (string) $rawValue;
        }
    } else {
        $mode = (string) ($values['mode'] ?? 'settings');
        $scope = $mode === 'full'
            ? ['settings', 'posts_comments', 'attachments']
            : ['settings'];
        if (!empty($values['copy_series'])) {
            $scope[] = 'series';
        }
    }

    $scope = array_values(array_unique(array_filter(array_map(static function (string $value): string {
        return strtolower(trim($value));
    }, $scope), static function (string $value): bool {
        return $value !== '';
    })));

    if (in_array('all', $scope, true)) {
        return sr_community_board_copy_scope_item_keys();
    }

    $allowed = array_flip(sr_community_board_copy_scope_item_keys());
    $normalized = [];
    foreach ($scope as $value) {
        if (isset($allowed[$value])) {
            $normalized[] = $value;
        }
    }

    return array_values(array_unique($normalized));
}

function sr_community_board_copy_scope_has(array $values, string $scopeKey): bool
{
    return in_array($scopeKey, sr_community_board_copy_scope_values($values), true);
}

function sr_community_board_copy_scope_all_selected(array $values): bool
{
    $selected = sr_community_board_copy_scope_values($values);
    foreach (sr_community_board_copy_scope_item_keys() as $scopeKey) {
        if (!in_array($scopeKey, $selected, true)) {
            return false;
        }
    }

    return true;
}

function sr_community_board_copy_scope_labels_for_values(array $values): array
{
    if (sr_community_board_copy_scope_all_selected($values)) {
        return ['전체'];
    }

    $labels = sr_community_board_copy_scope_options();
    $result = [];
    foreach (sr_community_board_copy_scope_values($values) as $scopeKey) {
        if (isset($labels[$scopeKey])) {
            $result[] = (string) $labels[$scopeKey];
        }
    }

    return $result;
}

function sr_community_board_copy_scope_errors(array $values): array
{
    $scope = sr_community_board_copy_scope_values($values);
    $errors = [];
    if ($scope === []) {
        $errors[] = '복사 범위를 하나 이상 선택하세요.';
    }
    if (in_array('attachments', $scope, true) && !in_array('posts_comments', $scope, true)) {
        $errors[] = '첨부파일을 복사하려면 게시글+댓글도 함께 선택하세요.';
    }
    if (in_array('series', $scope, true) && !in_array('posts_comments', $scope, true)) {
        $errors[] = '시리즈를 복사하려면 게시글+댓글도 함께 선택하세요.';
    }

    return $errors;
}

function sr_community_board_copy_normalized_values(array $values): array
{
    $scope = sr_community_board_copy_scope_values($values);
    $values['copy_scope'] = $scope;
    $values['mode'] = in_array('posts_comments', $scope, true) ? 'full' : 'settings';
    $values['copy_settings'] = in_array('settings', $scope, true);
    $values['copy_posts_comments'] = in_array('posts_comments', $scope, true);
    $values['copy_attachments'] = in_array('attachments', $scope, true);
    $values['copy_series'] = in_array('series', $scope, true);

    return $values;
}

function sr_community_board_copy_limits(): array
{
    return [
        'posts' => 500,
        'comments' => 5000,
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
    return sr_clean_single_line($value, $maxLength);
}

function sr_community_board_copy_counts(PDO $pdo, int $boardId): array
{
    $counts = [
        'posts' => 0,
        'comments' => 0,
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

function sr_community_board_copy_storage_warnings(array $counts): array
{
    $bytes = (int) ($counts['bytes'] ?? 0);
    if ($bytes > 0) {
        return [
            '첨부파일까지 복사하면 새 파일을 다시 만들기 때문에 최소 ' . sr_community_format_bytes($bytes) . ' 이상의 여유 공간이 더 필요합니다.',
            '공유 호스팅은 디스크 용량이 남아 있어도 DB 용량, 파일 개수 제한, inode 제한 때문에 중간에 멈출 수 있습니다.',
            '큰 게시판은 호스팅 관리 화면에서 여유 용량을 먼저 확인한 뒤 실행하세요.',
        ];
    }

    return [
        '첨부파일이 없어도 게시글과 댓글을 새로 저장하므로 DB 용량이 부족하면 복사가 실패할 수 있습니다.',
        '큰 게시판은 호스팅 관리 화면에서 DB 여유 용량을 먼저 확인하세요.',
    ];
}

function sr_community_board_copy_batch_threshold_errors(array $counts): array
{
    $limits = sr_community_board_copy_limits();
    $errors = [];
    foreach (['posts', 'comments', 'attachments'] as $key) {
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
    return sr_community_board_copy_batch_block_errors_for_values($counts, [
        'copy_scope' => ['settings', 'posts_comments', 'attachments', 'series'],
    ]);
}

function sr_community_board_copy_batch_block_errors_for_values(array $counts, array $values): array
{
    $values = sr_community_board_copy_normalized_values($values);
    $errors = [];
    if (!empty($values['copy_attachments']) && !empty($counts['unsupported_storage'])) {
        $errors[] = '현재 저장소 driver에서는 첨부파일 포함 복사를 지원하지 않습니다.';
    }
    if (!empty($values['copy_attachments']) && ($counts['missing_files'] ?? []) !== []) {
        $errors[] = '원본 첨부파일을 확인할 수 없어 복사를 시작하지 않았습니다.';
    }

    return $errors;
}

function sr_community_board_copy_batch_errors(array $counts): array
{
    return sr_community_board_copy_batch_block_errors($counts);
}

function sr_community_board_copy_batch_errors_for_values(array $counts, array $values): array
{
    return sr_community_board_copy_batch_block_errors_for_values($counts, $values);
}

function sr_community_board_copy_counts_for_values(array $counts, array $values): array
{
    $values = sr_community_board_copy_normalized_values($values);
    $selected = $counts;

    if (empty($values['copy_posts_comments'])) {
        $selected['posts'] = 0;
        $selected['comments'] = 0;
    }
    if (empty($values['copy_attachments'])) {
        $selected['attachments'] = 0;
        $selected['bytes'] = 0;
        $selected['unsupported_storage'] = false;
        $selected['missing_files'] = [];
    }
    if (empty($values['copy_series'])) {
        $selected['series'] = 0;
        $selected['series_items'] = 0;
    }

    return $selected;
}

function sr_community_board_copy_load_assessment(array $counts, array $values, bool $batchAvailable): array
{
    $values = sr_community_board_copy_normalized_values($values);
    $copyPosts = !empty($values['copy_posts_comments']);
    $posts = $copyPosts ? max(0, (int) ($counts['posts'] ?? 0)) : 0;
    $comments = $copyPosts ? max(0, (int) ($counts['comments'] ?? 0)) : 0;
    $attachments = !empty($values['copy_attachments']) ? max(0, (int) ($counts['attachments'] ?? 0)) : 0;
    $bytes = !empty($values['copy_attachments']) ? max(0, (int) ($counts['bytes'] ?? 0)) : 0;
    $series = !empty($values['copy_series']) ? max(0, (int) ($counts['series'] ?? 0)) : 0;
    $seriesItems = !empty($values['copy_series']) ? max(0, (int) ($counts['series_items'] ?? 0)) : 0;
    $targetRecords = $copyPosts ? $posts + $comments + $attachments + $series + $seriesItems : 1;
    $limits = sr_community_board_copy_limits();

    $grade = 'low';
    if ($copyPosts) {
        if (
            !$batchAvailable
            || $posts > (int) $limits['posts']
            || $comments > (int) $limits['comments']
            || $attachments > (int) $limits['attachments']
            || $bytes > (int) $limits['bytes']
        ) {
            $grade = 'very_high';
        } elseif (
            $posts >= 200
            || $comments >= 1000
            || $attachments >= 100
            || $bytes >= 104857600
            || $seriesItems >= 500
        ) {
            $grade = 'high';
        } elseif (
            $posts >= 50
            || $comments >= 200
            || $attachments >= 20
            || $bytes >= 20971520
            || $seriesItems >= 100
        ) {
            $grade = 'caution';
        }
    }

    return [
        'grade' => $grade,
        'label' => sr_community_board_copy_load_grade_label($grade),
        'target_records' => $targetRecords,
        'requires_confirmation' => in_array($grade, ['high', 'very_high'], true),
        'requires_batch_review' => $grade === 'very_high',
        'recommended_time' => sr_community_board_copy_load_recommended_time($grade),
        'failure_state' => $copyPosts
            ? '배치 작업은 처리된 항목을 유지하고 실패 상태와 다음 처리 위치를 작업 목록에 남깁니다.'
            : '설정만 복사는 한 요청에서 처리되며, 실패하면 화면 오류와 감사 로그로 확인합니다.',
    ];
}

function sr_community_board_copy_load_grade_label(string $grade): string
{
    if ($grade === 'very_high') {
        return '매우 높음';
    }
    if ($grade === 'high') {
        return '높음';
    }
    if ($grade === 'caution') {
        return '주의';
    }

    return '낮음';
}

function sr_community_board_copy_load_recommended_time(string $grade): string
{
    if ($grade === 'low') {
        return '일반 운영 시간에도 실행할 수 있습니다. 실행 직전 대상 수만 확인하세요.';
    }
    if ($grade === 'caution') {
        return '일반 운영 시간에도 실행할 수 있지만, 변경 직전 대상 수와 저장소 여유를 확인하세요.';
    }

    return '방문자가 적은 시간에 실행하고, 가능하면 백업 또는 staging 검증 후 진행하세요.';
}

function sr_community_copy_board(PDO $pdo, int $sourceBoardId, array $values, int $accountId): int
{
    $source = sr_community_board_by_id($pdo, $sourceBoardId);
    if (!is_array($source)) {
        throw new RuntimeException('복사할 게시판을 찾을 수 없습니다.');
    }

    $values = sr_community_board_copy_normalized_values($values);
    if (!empty($values['copy_posts_comments'])) {
        throw new InvalidArgumentException('게시글/댓글/첨부파일 포함 복사는 게시판 복사 작업 경로로만 실행할 수 있습니다.');
    }
    $boardKey = strtolower(trim((string) ($values['board_key'] ?? '')));
    $title = sr_community_clean_single_line((string) ($values['title'] ?? ''), 120);
    $errors = sr_community_board_copy_scope_errors($values);
    if (!sr_community_board_key_is_valid($boardKey)) {
        $errors[] = '게시판 Key는 소문자 영문, 숫자, _만 사용할 수 있습니다.';
    } elseif (is_array(sr_community_board_by_key($pdo, $boardKey))) {
        $errors[] = '이미 사용 중인 게시판 Key입니다.';
    }
    if ($title === '') {
        $errors[] = '새 게시판 제목을 입력하세요.';
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
        sr_community_copy_board_categories($pdo, $sourceBoardId, $newBoardId, $now);
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
    $reactionColumnSql = sr_community_post_reaction_preset_columns_exist($pdo) ? 'reaction_preset_key, reaction_comment_preset_key, ' : '';
    $reactionValueSql = $reactionColumnSql !== '' ? ':reaction_preset_key, :reaction_comment_preset_key, ' : '';
    $secretColumnSql = sr_community_post_secret_column_exists($pdo) ? 'is_secret, ' : '';
    $secretValueSql = $secretColumnSql !== '' ? ':is_secret, ' : '';
    $summaryFeedCandidateColumnSql = sr_community_post_summary_feed_candidate_column_exists($pdo) ? 'summary_feed_candidate, ' : '';
    $summaryFeedCandidateValueSql = $summaryFeedCandidateColumnSql !== '' ? ':summary_feed_candidate, ' : '';
    $summaryFeedCandidate = sr_community_summary_feed_candidate_value_for_board($pdo, $newBoardId);
    $insertPost = $pdo->prepare(
        'INSERT INTO sr_community_posts
            (board_id, ' . $categoryColumnSql . 'author_account_id, ' . $authorSnapshotColumnSql . 'title, body_text, body_format, ' . $reactionColumnSql . $secretColumnSql . $summaryFeedCandidateColumnSql . 'status, view_count, last_commented_at, created_at, updated_at)
         VALUES
            (:board_id, ' . $categoryValueSql . ':author_account_id, ' . $authorSnapshotValueSql . ':title, :body_text, :body_format, ' . $reactionValueSql . $secretValueSql . $summaryFeedCandidateValueSql . ':status, 0, :last_commented_at, :created_at, :updated_at)'
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
        if ($reactionColumnSql !== '') {
            $params['reaction_preset_key'] = (string) ($post['reaction_preset_key'] ?? '');
            $params['reaction_comment_preset_key'] = (string) ($post['reaction_comment_preset_key'] ?? '');
        }
        if ($secretColumnSql !== '') {
            $params['is_secret'] = (int) ($post['is_secret'] ?? 0) === 1 ? 1 : 0;
        }
        if ($summaryFeedCandidateColumnSql !== '') {
            $params['summary_feed_candidate'] = $summaryFeedCandidate;
        }
        if ((string) ($post['body_format'] ?? 'plain') === 'html') {
            $params['body_text'] = sr_community_sanitize_post_html((string) $params['body_text']);
        }
        $insertPost->execute($params);
        $newPostId = (int) $pdo->lastInsertId();
        if ((string) ($post['body_format'] ?? 'plain') === 'html') {
            $bodyText = sr_community_clone_body_files($pdo, (int) $post['id'], $newPostId, (string) $params['body_text'], $createdFiles);
            $bodyText = sr_community_sanitize_post_html($bodyText);
            if ($bodyText !== (string) $params['body_text']) {
                $pdo->prepare('UPDATE sr_community_posts SET body_text = :body_text, updated_at = :updated_at WHERE id = :id')->execute([
                    'body_text' => $bodyText,
                    'updated_at' => $now,
                    'id' => $newPostId,
                ]);
            }
        }
        $postMap[(int) $post['id']] = $newPostId;
    }

    sr_community_copy_board_comments($pdo, $postMap);
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
    $secretColumnSql = sr_community_comment_secret_column_exists($pdo) ? 'is_secret, ' : '';
    $secretValueSql = $secretColumnSql !== '' ? ':is_secret, ' : '';
    $insert = $pdo->prepare(
        'INSERT INTO sr_community_comments
            (post_id, author_account_id, ' . $authorSnapshotColumnSql . 'body_text, ' . $secretColumnSql . 'status, created_at, updated_at)
         VALUES
            (:post_id, :author_account_id, ' . $authorSnapshotValueSql . ':body_text, ' . $secretValueSql . ':status, :created_at, :updated_at)'
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
            if ($secretColumnSql !== '') {
                $params['is_secret'] = (int) ($comment['is_secret'] ?? 0) === 1 ? 1 : 0;
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
