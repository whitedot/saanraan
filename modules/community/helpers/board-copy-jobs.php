<?php

declare(strict_types=1);

function sr_community_board_copy_job_statuses(): array
{
    return ['pending', 'running', 'paused', 'failed', 'cleanup_required', 'cleaning', 'cancelled', 'completed'];
}

function sr_community_board_copy_job_status_label(string $status): string
{
    $labels = [
        'pending' => '대기',
        'running' => '처리 중',
        'paused' => '일시 중지',
        'failed' => '실패',
        'cleanup_required' => '정리 필요',
        'cleaning' => '정리 중',
        'cancelled' => '취소됨',
        'completed' => '완료',
    ];

    return $labels[$status] ?? $status;
}

function sr_community_board_copy_job_stages(): array
{
    return ['prepare', 'board', 'posts', 'comments', 'attachments', 'series', 'verify', 'complete', 'cleanup'];
}

function sr_community_board_copy_job_stage_label(string $stage): string
{
    $labels = [
        'prepare' => '준비',
        'board' => '게시판 생성',
        'posts' => '게시글 복사',
        'comments' => '댓글 복사',
        'attachments' => '첨부 복사',
        'series' => '시리즈 복사',
        'verify' => '검증',
        'complete' => '완료',
        'cleanup' => '정리',
    ];

    return $labels[$stage] ?? $stage;
}

function sr_community_board_copy_job_stage_number(string $stage): int
{
    $index = array_search($stage, sr_community_board_copy_job_stages(), true);
    return is_int($index) ? $index + 1 : 0;
}

function sr_community_board_copy_job_stage_total(): int
{
    return count(sr_community_board_copy_job_stages());
}

function sr_community_board_copy_job_stage_progress_label(string $stage): string
{
    $number = sr_community_board_copy_job_stage_number($stage);
    if ($number < 1) {
        return sr_community_board_copy_job_stage_label($stage);
    }

    return '(' . (string) $number . '/' . (string) sr_community_board_copy_job_stage_total() . ') ' . sr_community_board_copy_job_stage_label($stage);
}

function sr_community_board_copy_map_statuses(): array
{
    return ['pending', 'copied', 'verified', 'failed', 'skipped', 'cleaned', 'cleanup_failed'];
}

function sr_community_board_copy_job_create(PDO $pdo, int $sourceBoardId, array $values, int $accountId): int
{
    $source = sr_community_board_by_id($pdo, $sourceBoardId);
    if (!is_array($source)) {
        throw new RuntimeException('복사할 게시판을 찾을 수 없습니다.');
    }

    $boardKey = strtolower(trim((string) ($values['board_key'] ?? '')));
    $title = sr_community_clean_single_line((string) ($values['title'] ?? ''), 120);
    $errors = [];
    if (!sr_community_board_key_is_valid($boardKey)) {
        $errors[] = '게시판 Key는 소문자 영문, 숫자, _만 사용할 수 있습니다.';
    } else {
        $boardKey = sr_community_board_copy_unique_board_key($pdo, $boardKey);
    }
    if ($title === '') {
        $errors[] = '새 게시판 제목을 입력하세요.';
    }
    $counts = sr_community_board_copy_counts($pdo, $sourceBoardId);
    $values = sr_community_board_copy_normalized_values($values);
    $errors = array_merge($errors, sr_community_board_copy_scope_errors($values));
    if (empty($values['copy_posts_comments'])) {
        $errors[] = '게시글+댓글을 포함한 복사는 게시판 복사 작업으로 만들어야 합니다.';
    }
    $errors = array_merge($errors, sr_community_board_copy_batch_block_errors_for_values($counts, $values));
    $errors = array_merge($errors, sr_community_board_copy_series_validate_options($pdo, $sourceBoardId, $values));
    if ($errors !== []) {
        throw new InvalidArgumentException(implode("\n", $errors));
    }

    $selectedCounts = sr_community_board_copy_counts_for_values($counts, $values);
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_board_copy_jobs
            (source_board_id, requested_by, mode, status, stage, source_snapshot_json, options_json, counts_json, processed_json, created_at, updated_at)
         VALUES
            (:source_board_id, :requested_by, :mode, :status, :stage, :source_snapshot_json, :options_json, :counts_json, :processed_json, :created_at, :updated_at)'
    );
    $stmt->execute([
        'source_board_id' => $sourceBoardId,
        'requested_by' => $accountId,
        'mode' => !empty($values['copy_attachments']) ? 'posts_comments_attachments' : 'posts_comments',
        'status' => 'pending',
        'stage' => 'prepare',
        'source_snapshot_json' => json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'options_json' => json_encode([
            'board_key' => $boardKey,
            'title' => $title,
            'copy_scope' => $values['copy_scope'],
            'copy_settings' => !empty($values['copy_settings']),
            'copy_posts_comments' => !empty($values['copy_posts_comments']),
            'copy_attachments' => !empty($values['copy_attachments']),
            'copy_series' => !empty($values['copy_series']),
            'series_titles' => is_array($values['series_titles'] ?? null) ? $values['series_titles'] : [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'counts_json' => json_encode($selectedCounts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'processed_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_community_board_copy_job_by_id(PDO $pdo, int $jobId): ?array
{
    if ($jobId < 1) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM sr_community_board_copy_jobs WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $jobId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_board_copy_job_json(array $job, string $key): array
{
    $decoded = json_decode((string) ($job[$key] ?? ''), true);
    return is_array($decoded) ? $decoded : [];
}

function sr_community_board_copy_job_option_enabled(array $job, string $optionKey, bool $default): bool
{
    $options = sr_community_board_copy_job_json($job, 'options_json');
    if (array_key_exists($optionKey, $options)) {
        return !empty($options[$optionKey]);
    }

    return $default;
}

function sr_community_board_copy_job_next_after_comments(array $job): string
{
    if (sr_community_board_copy_job_option_enabled($job, 'copy_attachments', true)) {
        return 'attachments';
    }
    if (sr_community_board_copy_job_option_enabled($job, 'copy_series', false)) {
        return 'series';
    }

    return 'verify';
}

function sr_community_board_copy_job_next_after_attachments(array $job): string
{
    return sr_community_board_copy_job_option_enabled($job, 'copy_series', false) ? 'series' : 'verify';
}

function sr_community_board_copy_jobs_recent(PDO $pdo, int $limit = 30): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->query(
        'SELECT j.*, b.title AS source_title, tb.title AS target_title
         FROM sr_community_board_copy_jobs j
         LEFT JOIN sr_community_boards b ON b.id = j.source_board_id
         LEFT JOIN sr_community_boards tb ON tb.id = j.target_board_id
         ORDER BY CASE WHEN j.status IN (\'pending\', \'running\', \'paused\', \'failed\', \'cleanup_required\', \'cleaning\') THEN 0 ELSE 1 END ASC,
                  j.updated_at DESC,
                  j.id DESC
         LIMIT ' . $limit
    );

    return $stmt->fetchAll();
}

function sr_community_board_copy_job_map_status_counts(PDO $pdo, int $jobId): array
{
    if ($jobId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT entity_type, status, COUNT(*) AS count_value
         FROM sr_community_board_copy_job_maps
         WHERE job_id = :job_id
         GROUP BY entity_type, status
         ORDER BY entity_type ASC, status ASC'
    );
    $stmt->execute(['job_id' => $jobId]);

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $entityType = (string) ($row['entity_type'] ?? '');
        $status = (string) ($row['status'] ?? '');
        if ($entityType === '' || $status === '') {
            continue;
        }
        if (!isset($counts[$entityType])) {
            $counts[$entityType] = [];
        }
        $counts[$entityType][$status] = (int) ($row['count_value'] ?? 0);
    }

    return $counts;
}

function sr_community_board_copy_job_failed_maps(PDO $pdo, int $jobId, int $limit = 10): array
{
    if ($jobId < 1) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $stmt = $pdo->prepare(
        'SELECT entity_type, source_id, target_id, error_text, updated_at
         FROM sr_community_board_copy_job_maps
         WHERE job_id = :job_id
           AND status = "failed"
         ORDER BY updated_at DESC, id DESC
         LIMIT ' . (string) $limit
    );
    $stmt->execute(['job_id' => $jobId]);

    return $stmt->fetchAll();
}

function sr_community_board_copy_job_run(PDO $pdo, int $jobId, int $accountId, array $limits = []): array
{
    $job = sr_community_board_copy_job_by_id($pdo, $jobId);
    if (!is_array($job)) {
        throw new RuntimeException('복사 작업을 찾을 수 없습니다.');
    }
    if (in_array((string) $job['status'], ['completed', 'cancelled'], true)) {
        return ['done' => true, 'message' => '이미 종료된 작업입니다.'];
    }

    $token = bin2hex(random_bytes(16));
    $now = sr_now();
    $stmt = $pdo->prepare(
        "UPDATE sr_community_board_copy_jobs
         SET status = 'running', lock_token = :lock_token, locked_at = :locked_at, started_at = COALESCE(started_at, :started_at), updated_at = :updated_at
         WHERE id = :id
           AND status IN ('pending', 'running', 'failed', 'cleanup_required')
           AND (lock_token = '' OR locked_at IS NULL OR locked_at < DATE_SUB(:lock_cutoff, INTERVAL 2 MINUTE))"
    );
    $stmt->execute([
        'lock_token' => $token,
        'locked_at' => $now,
        'started_at' => $now,
        'updated_at' => $now,
        'id' => $jobId,
        'lock_cutoff' => $now,
    ]);
    if ($stmt->rowCount() < 1) {
        return ['done' => false, 'message' => '다른 요청이 이 작업을 처리 중입니다.'];
    }

    try {
        $job = sr_community_board_copy_job_by_id($pdo, $jobId);
        if (!is_array($job)) {
            throw new RuntimeException('복사 작업을 찾을 수 없습니다.');
        }
        $stage = (string) ($job['stage'] ?? 'prepare');
        $result = sr_community_board_copy_job_run_stage($pdo, $job, $accountId, $limits, $token);
        $releaseStatus = !empty($result['done']) ? (string) ($result['status'] ?? 'running') : 'running';
        $releaseStage = (string) ($result['stage'] ?? $stage);
        $completedAtSql = $releaseStatus === 'completed' ? ', completed_at = :completed_at' : '';
        $stmt = $pdo->prepare(
            "UPDATE sr_community_board_copy_jobs
             SET status = :status, stage = :stage, lock_token = '', locked_at = NULL, last_error = :last_error, updated_at = :updated_at" . $completedAtSql . "
             WHERE id = :id AND lock_token = :lock_token"
        );
        $params = [
            'status' => $releaseStatus,
            'stage' => $releaseStage,
            'last_error' => (string) ($result['error'] ?? ''),
            'updated_at' => sr_now(),
            'id' => $jobId,
            'lock_token' => $token,
        ];
        if ($releaseStatus === 'completed') {
            $params['completed_at'] = sr_now();
        }
        $stmt->execute($params);

        return $result;
    } catch (Throwable $exception) {
        $stmt = $pdo->prepare(
            "UPDATE sr_community_board_copy_jobs
             SET status = 'failed', lock_token = '', locked_at = NULL, last_error = :last_error, updated_at = :updated_at
             WHERE id = :id AND lock_token = :lock_token"
        );
        $stmt->execute([
            'last_error' => $exception->getMessage(),
            'updated_at' => sr_now(),
            'id' => $jobId,
            'lock_token' => $token,
        ]);
        throw $exception;
    }
}

function sr_community_board_copy_job_assert_lock(PDO $pdo, int $jobId, string $lockToken): void
{
    if ($jobId < 1 || $lockToken === '') {
        throw new RuntimeException('복사 작업 lock token이 없습니다.');
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sr_community_board_copy_jobs WHERE id = :id AND status = 'running' AND lock_token = :lock_token");
    $stmt->execute([
        'id' => $jobId,
        'lock_token' => $lockToken,
    ]);
    if ((int) $stmt->fetchColumn() !== 1) {
        throw new RuntimeException('복사 작업 lock이 만료되었거나 다른 요청이 이어받았습니다.');
    }
}

function sr_community_board_copy_job_run_stage(PDO $pdo, array $job, int $accountId, array $limits, string $lockToken): array
{
    $stage = (string) ($job['stage'] ?? 'prepare');
    sr_community_board_copy_job_assert_lock($pdo, (int) $job['id'], $lockToken);
    if ($stage === 'prepare') {
        $prepareDone = sr_community_board_copy_job_prepare($pdo, $job, $lockToken, (int) ($limits['prepare'] ?? 500));
        if (!$prepareDone) {
            return ['done' => false, 'stage' => 'prepare', 'status' => 'running', 'message' => '복사 대상 목록을 묶음으로 준비했습니다.'];
        }
        sr_community_board_copy_job_refresh_counts($pdo, $job, $lockToken);
        return ['done' => false, 'stage' => 'board', 'status' => 'running', 'message' => '복사 대상 목록 준비를 완료했습니다.'];
    }
    if ($stage === 'board') {
        sr_community_board_copy_job_create_board($pdo, $job, $lockToken);
        return ['done' => false, 'stage' => 'posts', 'status' => 'running', 'message' => '대상 게시판을 만들고 다음 단계로 이동합니다.'];
    }
    if ($stage === 'posts') {
        return sr_community_board_copy_job_copy_posts($pdo, $job, (int) ($limits['posts'] ?? 50), $lockToken);
    }
    if ($stage === 'comments') {
        return sr_community_board_copy_job_copy_comments($pdo, $job, (int) ($limits['comments'] ?? 300), $lockToken);
    }
    if ($stage === 'attachments') {
        if (!sr_community_board_copy_job_option_enabled($job, 'copy_attachments', true)) {
            return ['done' => false, 'stage' => sr_community_board_copy_job_next_after_attachments($job), 'status' => 'running', 'message' => '첨부파일 복사를 선택하지 않아 다음 단계로 이동합니다.'];
        }
        return sr_community_board_copy_job_copy_attachments($pdo, $job, (int) ($limits['attachments'] ?? 50), $lockToken);
    }
    if ($stage === 'series') {
        if (!sr_community_board_copy_job_option_enabled($job, 'copy_series', false)) {
            return ['done' => false, 'stage' => 'verify', 'status' => 'running', 'message' => '시리즈 복사를 선택하지 않아 검증 단계로 이동합니다.'];
        }
        return sr_community_board_copy_job_copy_series($pdo, $job, $lockToken);
    }
    if ($stage === 'verify') {
        sr_community_board_copy_job_assert_lock($pdo, (int) $job['id'], $lockToken);
        $verify = sr_community_board_copy_job_verify($pdo, $job, (int) ($limits['verify'] ?? 100), $lockToken);
        if ((int) ($verify['remaining'] ?? 0) > 0) {
            return ['done' => false, 'stage' => 'verify', 'status' => 'running', 'message' => '복사 결과를 묶음으로 확인했습니다.'];
        }
        return ['done' => false, 'stage' => 'complete', 'status' => 'running', 'message' => '복사 결과를 확인했습니다.'];
    }
    if ($stage === 'complete') {
        return ['done' => true, 'stage' => 'complete', 'status' => 'completed', 'message' => '게시판 복사가 완료되었습니다.'];
    }
    if ($stage === 'cleanup') {
        $cleanup = sr_community_board_copy_job_cleanup($pdo, $job, $lockToken, (int) ($limits['cleanup'] ?? 100));
        if ((int) ($cleanup['failed'] ?? 0) > 0) {
            return [
                'done' => true,
                'stage' => 'cleanup',
                'status' => 'cleanup_required',
                'message' => '일부 파일을 정리하지 못했습니다. 정리 상태를 확인한 뒤 다시 시도하세요.',
                'error' => '정리 실패 항목 ' . (string) (int) $cleanup['failed'] . '개가 남아 있습니다.',
            ];
        }
        if ((int) ($cleanup['remaining'] ?? 0) > 0) {
            return ['done' => false, 'stage' => 'cleanup', 'status' => 'running', 'message' => '정리 작업을 묶음으로 처리했습니다.'];
        }
        return ['done' => true, 'stage' => 'cleanup', 'status' => 'cancelled', 'message' => '복사 작업을 정리했습니다.'];
    }

    throw new RuntimeException('복사 작업 단계가 올바르지 않습니다.');
}

function sr_community_board_copy_job_prepare(PDO $pdo, array $job, string $lockToken, int $limit = 500): bool
{
    $jobId = (int) $job['id'];
    $boardId = (int) $job['source_board_id'];
    sr_community_board_copy_job_assert_lock($pdo, $jobId, $lockToken);
    $limit = max(1, min(1000, $limit));
    $now = sr_now();
    $insert = $pdo->prepare(
        'INSERT IGNORE INTO sr_community_board_copy_job_maps (job_id, entity_type, source_id, status, created_at, updated_at)
         VALUES (:job_id, :entity_type, :source_id, :status, :created_at, :updated_at)'
    );
    $options = sr_community_board_copy_job_json($job, 'options_json');
    $sources = [];
    if (!array_key_exists('copy_settings', $options) || !empty($options['copy_settings'])) {
        if (sr_community_categories_supported($pdo)) {
            $sources['category'] = "SELECT c.id FROM sr_community_categories c WHERE c.board_id = :board_id AND NOT EXISTS (SELECT 1 FROM sr_community_board_copy_job_maps m WHERE m.job_id = :job_id AND m.entity_type = 'category' AND m.source_id = c.id) ORDER BY c.id ASC";
        }
    }
    if (!array_key_exists('copy_posts_comments', $options) || !empty($options['copy_posts_comments'])) {
        $sources['post'] = "SELECT p.id FROM sr_community_posts p WHERE p.board_id = :board_id AND NOT EXISTS (SELECT 1 FROM sr_community_board_copy_job_maps m WHERE m.job_id = :job_id AND m.entity_type = 'post' AND m.source_id = p.id) ORDER BY p.id ASC";
        $sources['comment'] = "SELECT c.id FROM sr_community_comments c INNER JOIN sr_community_posts p ON p.id = c.post_id WHERE p.board_id = :board_id AND NOT EXISTS (SELECT 1 FROM sr_community_board_copy_job_maps m WHERE m.job_id = :job_id AND m.entity_type = 'comment' AND m.source_id = c.id) ORDER BY c.id ASC";
    }
    if (!array_key_exists('copy_attachments', $options) || !empty($options['copy_attachments'])) {
        $sources['attachment'] = "SELECT a.id FROM sr_community_attachments a INNER JOIN sr_community_posts p ON p.id = a.post_id WHERE p.board_id = :board_id AND NOT EXISTS (SELECT 1 FROM sr_community_board_copy_job_maps m WHERE m.job_id = :job_id AND m.entity_type = 'attachment' AND m.source_id = a.id) ORDER BY a.id ASC";
    }
    if (!empty($options['copy_series']) && sr_community_series_supported($pdo)) {
        $sources['series'] = "SELECT DISTINCT s.id FROM sr_community_series s INNER JOIN sr_community_series_items si ON si.series_id = s.id INNER JOIN sr_community_posts p ON p.id = si.post_id WHERE s.board_id = :board_id AND p.board_id = s.board_id AND NOT EXISTS (SELECT 1 FROM sr_community_board_copy_job_maps m WHERE m.job_id = :job_id AND m.entity_type = 'series' AND m.source_id = s.id) ORDER BY s.id ASC";
        $sources['series_item'] = "SELECT si.id FROM sr_community_series_items si INNER JOIN sr_community_posts p ON p.id = si.post_id INNER JOIN sr_community_series s ON s.id = si.series_id WHERE s.board_id = :board_id AND p.board_id = s.board_id AND NOT EXISTS (SELECT 1 FROM sr_community_board_copy_job_maps m WHERE m.job_id = :job_id AND m.entity_type = 'series_item' AND m.source_id = si.id) ORDER BY si.id ASC";
    }
    $done = true;
    foreach ($sources as $type => $sql) {
        sr_community_board_copy_job_assert_lock($pdo, $jobId, $lockToken);
        $stmt = $pdo->prepare($sql . ' LIMIT ' . (string) $limit);
        $stmt->execute(['board_id' => $boardId, 'job_id' => $jobId]);
        $rows = $stmt->fetchAll();
        if (count($rows) >= $limit) {
            $done = false;
        }
        foreach ($rows as $row) {
            sr_community_board_copy_job_assert_lock($pdo, $jobId, $lockToken);
            $insert->execute([
                'job_id' => $jobId,
                'entity_type' => $type,
                'source_id' => (int) $row['id'],
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    return $done;
}

function sr_community_board_copy_job_refresh_counts(PDO $pdo, array $job, string $lockToken): void
{
    $jobId = (int) $job['id'];
    sr_community_board_copy_job_assert_lock($pdo, $jobId, $lockToken);
    $counts = sr_community_board_copy_job_json($job, 'counts_json');
    foreach ([
        'post' => 'posts',
        'comment' => 'comments',
        'attachment' => 'attachments',
        'series' => 'series',
        'series_item' => 'series_items',
    ] as $entityType => $countKey) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM sr_community_board_copy_job_maps WHERE job_id = :job_id AND entity_type = :entity_type');
        $stmt->execute(['job_id' => $jobId, 'entity_type' => $entityType]);
        $counts[$countKey] = (int) $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(a.size_bytes), 0)
         FROM sr_community_board_copy_job_maps m
         INNER JOIN sr_community_attachments a ON a.id = m.source_id
         WHERE m.job_id = :job_id
           AND m.entity_type = 'attachment'"
    );
    $stmt->execute(['job_id' => $jobId]);
    $counts['bytes'] = (int) $stmt->fetchColumn();

    $pdo->prepare('UPDATE sr_community_board_copy_jobs SET counts_json = :counts_json, updated_at = :updated_at WHERE id = :id')
        ->execute([
            'counts_json' => json_encode($counts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => sr_now(),
            'id' => $jobId,
        ]);
}

function sr_community_board_copy_job_create_board(PDO $pdo, array $job, string $lockToken): void
{
    if ((int) ($job['target_board_id'] ?? 0) > 0) {
        return;
    }
    sr_community_board_copy_job_assert_lock($pdo, (int) $job['id'], $lockToken);
    $source = sr_community_board_by_id($pdo, (int) $job['source_board_id']);
    if (!is_array($source)) {
        throw new RuntimeException('원본 게시판을 찾을 수 없습니다.');
    }
    $options = sr_community_board_copy_job_json($job, 'options_json');
    $now = sr_now();
    $copySettings = !array_key_exists('copy_settings', $options) || !empty($options['copy_settings']);
    $boardKey = sr_community_board_copy_unique_board_key($pdo, (string) ($options['board_key'] ?? ''), (int) $job['id']);
    if ($boardKey !== (string) ($options['board_key'] ?? '')) {
        $options['board_key'] = $boardKey;
        $pdo->prepare('UPDATE sr_community_board_copy_jobs SET options_json = :options_json, updated_at = :updated_at WHERE id = :id AND lock_token = :lock_token')
            ->execute([
                'options_json' => json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => $now,
                'id' => (int) $job['id'],
                'lock_token' => $lockToken,
            ]);
        sr_community_board_copy_job_assert_lock($pdo, (int) $job['id'], $lockToken);
    }
    $pdo->beginTransaction();
    try {
        $newBoardId = sr_community_create_board($pdo, [
            'board_group_id' => $copySettings ? (int) ($source['board_group_id'] ?? 0) : 0,
            'board_key' => $boardKey,
            'title' => (string) ($options['title'] ?? ''),
            'description' => $copySettings ? (string) ($source['description'] ?? '') : '',
            'status' => 'disabled',
            'read_policy' => $copySettings ? (string) ($source['read_policy'] ?? 'public') : 'public',
            'write_policy' => $copySettings ? (string) ($source['write_policy'] ?? 'member') : 'member',
            'comment_policy' => $copySettings ? (string) ($source['comment_policy'] ?? 'member') : 'member',
            'image_uploads_enabled' => $copySettings ? (int) ($source['image_uploads_enabled'] ?? 1) : 1,
            'sort_order' => $copySettings ? (int) ($source['sort_order'] ?? 0) : 0,
        ]);
        if ($copySettings) {
            sr_community_copy_board_settings($pdo, (int) $job['source_board_id'], $newBoardId, $now);
        }

        if ($copySettings && sr_community_categories_supported($pdo)) {
            $stmt = $pdo->prepare('SELECT * FROM sr_community_categories WHERE board_id = :board_id ORDER BY id ASC');
            $stmt->execute(['board_id' => (int) $job['source_board_id']]);
            $insert = $pdo->prepare(
                'INSERT INTO sr_community_categories (board_id, category_key, title, description, status, sort_order, created_at, updated_at)
                 VALUES (:board_id, :category_key, :title, :description, :status, :sort_order, :created_at, :updated_at)'
            );
            $updateMap = $pdo->prepare("UPDATE sr_community_board_copy_job_maps SET target_id = :target_id, status = 'copied', updated_at = :updated_at WHERE job_id = :job_id AND entity_type = 'category' AND source_id = :source_id");
            foreach ($stmt->fetchAll() as $category) {
                sr_community_board_copy_job_assert_lock($pdo, (int) $job['id'], $lockToken);
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
                $updateMap->execute([
                    'target_id' => (int) $pdo->lastInsertId(),
                    'updated_at' => $now,
                    'job_id' => (int) $job['id'],
                    'source_id' => (int) $category['id'],
                ]);
            }
        }
        sr_community_board_copy_job_assert_lock($pdo, (int) $job['id'], $lockToken);
        $pdo->prepare('UPDATE sr_community_board_copy_jobs SET target_board_id = :target_board_id, updated_at = :updated_at WHERE id = :id')
            ->execute(['target_board_id' => $newBoardId, 'updated_at' => $now, 'id' => (int) $job['id']]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_community_board_copy_job_target_board_id(PDO $pdo, array $job): int
{
    $targetId = (int) ($job['target_board_id'] ?? 0);
    if ($targetId > 0) {
        return $targetId;
    }
    $fresh = sr_community_board_copy_job_by_id($pdo, (int) $job['id']);
    return is_array($fresh) ? (int) ($fresh['target_board_id'] ?? 0) : 0;
}

function sr_community_board_copy_job_map_target(PDO $pdo, int $jobId, string $type, int $sourceId): int
{
    $stmt = $pdo->prepare('SELECT target_id FROM sr_community_board_copy_job_maps WHERE job_id = :job_id AND entity_type = :entity_type AND source_id = :source_id LIMIT 1');
    $stmt->execute(['job_id' => $jobId, 'entity_type' => $type, 'source_id' => $sourceId]);
    return (int) $stmt->fetchColumn();
}

function sr_community_board_copy_job_pending_maps(PDO $pdo, int $jobId, string $type, int $limit): array
{
    $limit = max(1, min(1000, $limit));
    $stmt = $pdo->prepare("SELECT * FROM sr_community_board_copy_job_maps WHERE job_id = :job_id AND entity_type = :entity_type AND status = 'pending' ORDER BY id ASC LIMIT " . $limit);
    $stmt->execute(['job_id' => $jobId, 'entity_type' => $type]);
    return $stmt->fetchAll();
}

function sr_community_board_copy_job_stage_result(PDO $pdo, int $jobId, string $type, string $nextStage, int $processed): array
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sr_community_board_copy_job_maps WHERE job_id = :job_id AND entity_type = :entity_type AND status = 'failed'");
    $stmt->execute(['job_id' => $jobId, 'entity_type' => $type]);
    $failed = (int) $stmt->fetchColumn();
    if ($failed > 0) {
        return [
            'done' => true,
            'stage' => sr_community_board_copy_job_stage_for_map_type($type),
            'status' => 'failed',
            'message' => '복사 항목 처리 중 실패가 발생했습니다.',
            'error' => '실패한 ' . $type . ' 항목 ' . (string) $failed . '건이 있습니다.',
        ];
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sr_community_board_copy_job_maps WHERE job_id = :job_id AND entity_type = :entity_type AND status = 'pending'");
    $stmt->execute(['job_id' => $jobId, 'entity_type' => $type]);
    $remaining = (int) $stmt->fetchColumn();
    return [
        'done' => false,
        'stage' => $remaining < 1 ? $nextStage : sr_community_board_copy_job_stage_for_map_type($type),
        'status' => 'running',
        'message' => $processed > 0 ? '묶음 처리를 완료했습니다.' : '다음 단계로 이동합니다.',
    ];
}

function sr_community_board_copy_job_stage_for_map_type(string $type): string
{
    return [
        'post' => 'posts',
        'comment' => 'comments',
        'attachment' => 'attachments',
        'series' => 'series',
        'series_item' => 'series',
    ][$type] ?? $type;
}

function sr_community_board_copy_job_copy_posts(PDO $pdo, array $job, int $limit, string $lockToken): array
{
    sr_community_board_copy_job_assert_lock($pdo, (int) $job['id'], $lockToken);
    $targetBoardId = sr_community_board_copy_job_target_board_id($pdo, $job);
    if ($targetBoardId < 1) {
        throw new RuntimeException('대상 게시판이 아직 생성되지 않았습니다.');
    }
    $maps = sr_community_board_copy_job_pending_maps($pdo, (int) $job['id'], 'post', $limit);
    $categorySupported = sr_community_categories_supported($pdo);
    $categoryColumnSql = $categorySupported ? 'category_id, ' : '';
    $categoryValueSql = $categorySupported ? ':category_id, ' : '';
    $summaryFeedCandidate = sr_community_summary_feed_candidate_value_for_board($pdo, $targetBoardId);
    $insert = $pdo->prepare(
        'INSERT INTO sr_community_posts
            (board_id, ' . $categoryColumnSql . 'author_account_id, author_public_name_snapshot, title, body_text, is_secret, is_notice, summary_feed_candidate, status, view_count, last_commented_at, created_at, updated_at)
         VALUES
            (:board_id, ' . $categoryValueSql . ':author_account_id, :author_public_name_snapshot, :title, :body_text, :is_secret, :is_notice, :summary_feed_candidate, :status, 0, :last_commented_at, :created_at, :updated_at)'
    );
    $processed = 0;
    foreach ($maps as $map) {
        sr_community_board_copy_job_assert_lock($pdo, (int) $job['id'], $lockToken);
        $stmt = $pdo->prepare('SELECT * FROM sr_community_posts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $map['source_id']]);
        $post = $stmt->fetch();
        if (!is_array($post)) {
            sr_community_board_copy_job_mark_map($pdo, (int) $map['id'], 0, 'skipped', '', '', '', (int) $job['id'], $lockToken);
            continue;
        }
        $sourceBodyFormat = sr_community_post_body_format($pdo, $post);
        $params = [
            'board_id' => $targetBoardId,
            'author_account_id' => (int) $post['author_account_id'],
            'author_public_name_snapshot' => (string) ($post['author_public_name_snapshot'] ?? ''),
            'title' => (string) $post['title'],
            'body_text' => (string) $post['body_text'],
            'is_secret' => (int) ($post['is_secret'] ?? 0) === 1 ? 1 : 0,
            'is_notice' => (int) ($post['is_notice'] ?? 0) === 1 ? 1 : 0,
            'summary_feed_candidate' => $summaryFeedCandidate,
            'status' => (string) $post['status'],
            'last_commented_at' => $post['last_commented_at'] ?? null,
            'created_at' => (string) $post['created_at'],
            'updated_at' => (string) $post['updated_at'],
        ];
        if ($categorySupported) {
            $sourceCategoryId = (int) ($post['category_id'] ?? 0);
            $targetCategoryId = $sourceCategoryId > 0 ? sr_community_board_copy_job_map_target($pdo, (int) $job['id'], 'category', $sourceCategoryId) : 0;
            $params['category_id'] = $targetCategoryId > 0 ? $targetCategoryId : null;
            if ((int) $params['category_id'] < 1) {
                $params['category_id'] = null;
            }
        }
        if ($sourceBodyFormat === 'html') {
            $params['body_text'] = sr_community_sanitize_post_html((string) $params['body_text']);
        }
        $createdBodyFiles = [];
        $newPostId = 0;
        $pdo->beginTransaction();
        try {
            $insert->execute($params);
            $newPostId = (int) $pdo->lastInsertId();
            if ($sourceBodyFormat === 'html') {
                $bodyText = sr_community_clone_body_files($pdo, (int) $post['id'], $newPostId, (string) $params['body_text'], $createdBodyFiles);
                $bodyText = sr_community_sanitize_post_html($bodyText);
                if ($bodyText !== (string) $params['body_text']) {
                    $pdo->prepare('UPDATE sr_community_posts SET body_text = :body_text, updated_at = :updated_at WHERE id = :id')->execute([
                        'body_text' => $bodyText,
                        'updated_at' => (string) $post['updated_at'],
                        'id' => $newPostId,
                    ]);
                }
                sr_url_embed_sync_body_url_cache($pdo, 'community', 'post', $newPostId, 'body', $bodyText, (int) $post['author_account_id']);
            } else {
                sr_url_embed_sync_body_url_cache($pdo, 'community', 'post', $newPostId, 'body', '', (int) $post['author_account_id']);
            }
            sr_community_board_copy_job_mark_map($pdo, (int) $map['id'], $newPostId, 'copied', '', '', '', (int) $job['id'], $lockToken);
            $pdo->commit();
            $processed++;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            sr_community_cleanup_storage_file_refs($pdo, $createdBodyFiles, 'body_file_clone_rollback', isset($newPostId) ? (int) $newPostId : 0, '게시판 복사 실패 후 본문 이미지 저장소 정리에 실패했습니다.');
            sr_community_board_copy_job_mark_map($pdo, (int) $map['id'], 0, 'failed', $exception->getMessage(), '', '', (int) $job['id'], $lockToken);
        }
    }

    return sr_community_board_copy_job_stage_result($pdo, (int) $job['id'], 'post', 'comments', $processed);
}

function sr_community_board_copy_job_copy_comments(PDO $pdo, array $job, int $limit, string $lockToken): array
{
    sr_community_board_copy_job_assert_lock($pdo, (int) $job['id'], $lockToken);
    $maps = sr_community_board_copy_job_pending_maps($pdo, (int) $job['id'], 'comment', $limit);
    $insert = $pdo->prepare(
        'INSERT INTO sr_community_comments
            (post_id, author_account_id, author_public_name_snapshot, body_text, is_secret, status, created_at, updated_at)
         VALUES
            (:post_id, :author_account_id, :author_public_name_snapshot, :body_text, :is_secret, :status, :created_at, :updated_at)'
    );
    $processed = 0;
    foreach ($maps as $map) {
        sr_community_board_copy_job_assert_lock($pdo, (int) $job['id'], $lockToken);
        $stmt = $pdo->prepare('SELECT * FROM sr_community_comments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $map['source_id']]);
        $comment = $stmt->fetch();
        if (!is_array($comment)) {
            sr_community_board_copy_job_mark_map($pdo, (int) $map['id'], 0, 'skipped', '', '', '', (int) $job['id'], $lockToken);
            continue;
        }
        $newPostId = sr_community_board_copy_job_map_target($pdo, (int) $job['id'], 'post', (int) $comment['post_id']);
        if ($newPostId < 1) {
            sr_community_board_copy_job_mark_map($pdo, (int) $map['id'], 0, 'failed', '게시글 매핑을 찾을 수 없습니다.', '', '', (int) $job['id'], $lockToken);
            continue;
        }
        $params = [
            'post_id' => $newPostId,
            'author_account_id' => (int) $comment['author_account_id'],
            'author_public_name_snapshot' => (string) ($comment['author_public_name_snapshot'] ?? ''),
            'body_text' => (string) $comment['body_text'],
            'is_secret' => (int) ($comment['is_secret'] ?? 0) === 1 ? 1 : 0,
            'status' => (string) $comment['status'],
            'created_at' => (string) $comment['created_at'],
            'updated_at' => (string) $comment['updated_at'],
        ];
        $insert->execute($params);
        sr_community_board_copy_job_mark_map($pdo, (int) $map['id'], (int) $pdo->lastInsertId(), 'copied', '', '', '', (int) $job['id'], $lockToken);
        $processed++;
    }

    return sr_community_board_copy_job_stage_result($pdo, (int) $job['id'], 'comment', sr_community_board_copy_job_next_after_comments($job), $processed);
}

function sr_community_board_copy_job_copy_attachments(PDO $pdo, array $job, int $limit, string $lockToken): array
{
    sr_community_board_copy_job_assert_lock($pdo, (int) $job['id'], $lockToken);
    $maps = sr_community_board_copy_job_pending_maps($pdo, (int) $job['id'], 'attachment', $limit);
    $insert = $pdo->prepare(
        'INSERT INTO sr_community_attachments
            (post_id, uploader_account_id, original_name, stored_name, storage_path, storage_driver, storage_key, mime_type, size_bytes, checksum_sha256, width, height, status, created_at)
         VALUES
            (:post_id, :uploader_account_id, :original_name, :stored_name, :storage_path, :storage_driver, :storage_key, :mime_type, :size_bytes, :checksum_sha256, :width, :height, :status, :created_at)'
    );
    $processed = 0;
    foreach ($maps as $map) {
        sr_community_board_copy_job_assert_lock($pdo, (int) $job['id'], $lockToken);
        $stmt = $pdo->prepare('SELECT * FROM sr_community_attachments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $map['source_id']]);
        $attachment = $stmt->fetch();
        if (!is_array($attachment)) {
            sr_community_board_copy_job_mark_map($pdo, (int) $map['id'], 0, 'skipped', '', '', '', (int) $job['id'], $lockToken);
            continue;
        }
        $newPostId = sr_community_board_copy_job_map_target($pdo, (int) $job['id'], 'post', (int) $attachment['post_id']);
        if ($newPostId < 1) {
            sr_community_board_copy_job_mark_map($pdo, (int) $map['id'], 0, 'failed', '게시글 매핑을 찾을 수 없습니다.', '', '', (int) $job['id'], $lockToken);
            continue;
        }
        $driver = sr_community_attachment_storage_driver($attachment);
        $sourceKey = sr_community_attachment_storage_key($attachment);
        $extension = strtolower(pathinfo((string) ($attachment['stored_name'] ?: $attachment['original_name']), PATHINFO_EXTENSION));
        $extension = preg_match('/\A[a-z0-9]{1,16}\z/', $extension) === 1 ? $extension : 'bin';
        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $targetKey = 'community/attachments/' . date('Y/m') . '/' . $storedName;
        try {
            sr_storage_copy($driver, $sourceKey, $targetKey);
            $insert->execute([
                'post_id' => $newPostId,
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
            sr_community_board_copy_job_mark_map($pdo, (int) $map['id'], (int) $pdo->lastInsertId(), 'copied', '', $driver, $targetKey, (int) $job['id'], $lockToken);
            $processed++;
        } catch (Throwable $exception) {
            sr_community_board_copy_job_mark_map($pdo, (int) $map['id'], 0, 'failed', $exception->getMessage(), '', '', (int) $job['id'], $lockToken);
        }
    }

    return sr_community_board_copy_job_stage_result($pdo, (int) $job['id'], 'attachment', sr_community_board_copy_job_next_after_attachments($job), $processed);
}

function sr_community_board_copy_job_copy_series(PDO $pdo, array $job, string $lockToken): array
{
    sr_community_board_copy_job_assert_lock($pdo, (int) $job['id'], $lockToken);
    if (!sr_community_series_supported($pdo)) {
        return ['done' => false, 'stage' => 'verify', 'status' => 'running', 'message' => '시리즈 테이블이 없어 다음 단계로 이동합니다.'];
    }
    $jobId = (int) $job['id'];
    $targetBoardId = sr_community_board_copy_job_target_board_id($pdo, $job);
    if ($targetBoardId < 1) {
        throw new RuntimeException('대상 게시판이 아직 생성되지 않았습니다.');
    }
    $options = sr_community_board_copy_job_json($job, 'options_json');
    $seriesTitles = is_array($options['series_titles'] ?? null) ? $options['series_titles'] : [];
    $now = sr_now();
    $insertSeries = $pdo->prepare(
        'INSERT INTO sr_community_series
            (board_id, owner_account_id, title, description, status, visibility, admin_note, created_by, updated_by, moderated_by, moderated_at, created_at, updated_at)
         VALUES
            (:board_id, :owner_account_id, :title, :description, :status, :visibility, :admin_note, :created_by, :updated_by, :moderated_by, :moderated_at, :created_at, :updated_at)'
    );
    foreach (sr_community_board_copy_job_pending_maps($pdo, $jobId, 'series', 1000) as $map) {
        sr_community_board_copy_job_assert_lock($pdo, $jobId, $lockToken);
        $stmt = $pdo->prepare('SELECT * FROM sr_community_series WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $map['source_id']]);
        $series = $stmt->fetch();
        if (!is_array($series)) {
            sr_community_board_copy_job_mark_map($pdo, (int) $map['id'], 0, 'skipped', '', '', '', $jobId, $lockToken);
            continue;
        }
        $seriesTitle = sr_community_clean_single_line((string) ($seriesTitles[(string) (int) $series['id']] ?? $seriesTitles[(int) $series['id']] ?? ''), 160);
        if ($seriesTitle === '') {
            $seriesTitle = sr_community_clean_single_line((string) ($series['title'] ?? '') . ' 복사본', 160);
        }
        $insertSeries->execute([
            'board_id' => $targetBoardId,
            'owner_account_id' => (int) ($series['owner_account_id'] ?? 0),
            'title' => $seriesTitle,
            'description' => (string) ($series['description'] ?? ''),
            'status' => (string) ($series['status'] ?? 'active'),
            'visibility' => (string) ($series['visibility'] ?? 'public'),
            'admin_note' => null,
            'created_by' => (int) ($job['requested_by'] ?? 0) ?: null,
            'updated_by' => (int) ($job['requested_by'] ?? 0) ?: null,
            'moderated_by' => null,
            'moderated_at' => null,
            'created_at' => (string) ($series['created_at'] ?? $now),
            'updated_at' => (string) ($series['updated_at'] ?? $now),
        ]);
        sr_community_board_copy_job_mark_map($pdo, (int) $map['id'], (int) $pdo->lastInsertId(), 'copied', '', '', '', $jobId, $lockToken);
    }

    $insertItem = $pdo->prepare(
        'INSERT INTO sr_community_series_items
            (series_id, post_id, active_post_id, episode_label, item_status, sort_order, created_by, created_at, updated_at)
         VALUES
            (:series_id, :post_id, :active_post_id, :episode_label, :item_status, :sort_order, :created_by, :created_at, :updated_at)'
    );
    foreach (sr_community_board_copy_job_pending_maps($pdo, $jobId, 'series_item', 1000) as $map) {
        sr_community_board_copy_job_assert_lock($pdo, $jobId, $lockToken);
        $stmt = $pdo->prepare('SELECT * FROM sr_community_series_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $map['source_id']]);
        $item = $stmt->fetch();
        if (!is_array($item)) {
            sr_community_board_copy_job_mark_map($pdo, (int) $map['id'], 0, 'skipped', '', '', '', $jobId, $lockToken);
            continue;
        }
        $newSeriesId = sr_community_board_copy_job_map_target($pdo, $jobId, 'series', (int) $item['series_id']);
        $newPostId = sr_community_board_copy_job_map_target($pdo, $jobId, 'post', (int) $item['post_id']);
        if ($newSeriesId < 1 || $newPostId < 1) {
            sr_community_board_copy_job_mark_map($pdo, (int) $map['id'], 0, 'failed', '시리즈 또는 게시글 매핑을 찾을 수 없습니다.', '', '', $jobId, $lockToken);
            continue;
        }
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
        sr_community_board_copy_job_mark_map($pdo, (int) $map['id'], (int) $pdo->lastInsertId(), 'copied', '', '', '', $jobId, $lockToken);
    }

    $seriesResult = sr_community_board_copy_job_stage_result($pdo, $jobId, 'series', 'verify', 0);
    if ((string) ($seriesResult['status'] ?? '') === 'failed') {
        return $seriesResult;
    }
    return sr_community_board_copy_job_stage_result($pdo, $jobId, 'series_item', 'verify', 0);
}

function sr_community_board_copy_job_mark_map(PDO $pdo, int $mapId, int $targetId, string $status, string $errorText = '', string $driver = '', string $key = '', int $jobId = 0, string $lockToken = ''): void
{
    if (!in_array($status, sr_community_board_copy_map_statuses(), true)) {
        $status = 'failed';
    }
    if ($jobId > 0 || $lockToken !== '') {
        sr_community_board_copy_job_assert_lock($pdo, $jobId, $lockToken);
    }
    $stmt = $pdo->prepare(
        'UPDATE sr_community_board_copy_job_maps
         SET target_id = :target_id, status = :status, error_text = :error_text, created_storage_driver = :driver, created_storage_key = :storage_key, updated_at = :updated_at
         WHERE id = :id' . ($jobId > 0 ? " AND job_id = :job_id
           AND EXISTS (
               SELECT 1
               FROM sr_community_board_copy_jobs
               WHERE id = :lock_job_id
                 AND status = 'running'
                 AND lock_token = :lock_token
           )" : '')
    );
    $params = [
        'target_id' => $targetId,
        'status' => $status,
        'error_text' => $errorText,
        'driver' => $driver,
        'storage_key' => $key,
        'updated_at' => sr_now(),
        'id' => $mapId,
    ];
    if ($jobId > 0) {
        $params['job_id'] = $jobId;
        $params['lock_job_id'] = $jobId;
        $params['lock_token'] = $lockToken;
    }
    $stmt->execute($params);
    if ($jobId > 0 && $stmt->rowCount() < 1) {
        sr_community_board_copy_job_assert_lock($pdo, $jobId, $lockToken);
        $check = $pdo->prepare('SELECT COUNT(*) FROM sr_community_board_copy_job_maps WHERE id = :id AND job_id = :job_id');
        $check->execute([
            'id' => $mapId,
            'job_id' => $jobId,
        ]);
        if ((int) $check->fetchColumn() !== 1) {
            throw new RuntimeException('복사 작업 항목을 찾을 수 없습니다.');
        }
    }
}

function sr_community_board_copy_job_verify(PDO $pdo, array $job, int $limit = 100, string $lockToken = ''): array
{
    $jobId = (int) $job['id'];
    if ($lockToken !== '') {
        sr_community_board_copy_job_assert_lock($pdo, $jobId, $lockToken);
    }
    foreach (['post', 'comment', 'attachment', 'series', 'series_item'] as $type) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sr_community_board_copy_job_maps WHERE job_id = :job_id AND entity_type = :entity_type AND status IN ('pending', 'failed')");
        $stmt->execute(['job_id' => $jobId, 'entity_type' => $type]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('아직 완료되지 않은 복사 항목이 있습니다: ' . $type);
        }
    }
    $targetBoardId = sr_community_board_copy_job_target_board_id($pdo, $job);
    $targetBoard = sr_community_board_by_id($pdo, $targetBoardId);
    if (!is_array($targetBoard) || (string) ($targetBoard['status'] ?? '') !== 'disabled') {
        throw new RuntimeException('대상 게시판이 disabled 상태가 아닙니다.');
    }

    $counts = sr_community_board_copy_job_json($job, 'counts_json');
    foreach ([
        'post' => ['expected' => 'posts', 'sql' => 'SELECT COUNT(*) FROM sr_community_posts WHERE board_id = :board_id'],
        'comment' => ['expected' => 'comments', 'sql' => 'SELECT COUNT(*) FROM sr_community_comments c INNER JOIN sr_community_posts p ON p.id = c.post_id WHERE p.board_id = :board_id'],
        'attachment' => ['expected' => 'attachments', 'sql' => 'SELECT COUNT(*) FROM sr_community_attachments a INNER JOIN sr_community_posts p ON p.id = a.post_id WHERE p.board_id = :board_id'],
    ] as $type => $rule) {
        $expected = (int) ($counts[(string) $rule['expected']] ?? 0);
        $stmt = $pdo->prepare((string) $rule['sql']);
        $stmt->execute(['board_id' => $targetBoardId]);
        $actual = (int) $stmt->fetchColumn();
        if ($actual !== $expected) {
            throw new RuntimeException('복사 결과 수가 일치하지 않습니다: ' . $type);
        }
    }

    $limit = max(1, min(500, $limit));
    $stmt = $pdo->prepare(
        "SELECT m.*, a.size_bytes, a.checksum_sha256
         FROM sr_community_board_copy_job_maps m
         INNER JOIN sr_community_attachments a ON a.id = m.source_id
         WHERE m.job_id = :job_id
           AND m.entity_type = 'attachment'
           AND m.status = 'copied'
         ORDER BY m.id ASC
         LIMIT " . (string) $limit
    );
    $stmt->execute(['job_id' => $jobId]);
    $maps = $stmt->fetchAll();
    foreach ($maps as $map) {
        if ($lockToken !== '') {
            sr_community_board_copy_job_assert_lock($pdo, $jobId, $lockToken);
        }
        $head = sr_storage_head((string) $map['created_storage_driver'], (string) $map['created_storage_key']);
        if (!is_array($head)) {
            throw new RuntimeException('복사된 첨부파일을 확인할 수 없습니다.');
        }
        if ((int) ($head['content_length'] ?? -1) !== (int) $map['size_bytes']) {
            throw new RuntimeException('복사된 첨부파일 크기가 일치하지 않습니다.');
        }
        $metadata = is_array($head['metadata'] ?? null) ? $head['metadata'] : [];
        if ((string) ($metadata['sha256'] ?? '') !== '' && (string) $metadata['sha256'] !== (string) $map['checksum_sha256']) {
            throw new RuntimeException('복사된 첨부파일 checksum이 일치하지 않습니다.');
        }
        sr_community_board_copy_job_mark_map($pdo, (int) $map['id'], (int) $map['target_id'], 'verified', '', (string) $map['created_storage_driver'], (string) $map['created_storage_key'], $jobId, $lockToken);
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sr_community_board_copy_job_maps WHERE job_id = :job_id AND entity_type = 'attachment' AND status = 'copied'");
    $stmt->execute(['job_id' => $jobId]);

    return ['remaining' => (int) $stmt->fetchColumn(), 'processed' => count($maps)];
}

function sr_community_board_copy_job_cleanup(PDO $pdo, array $job, string $lockToken, int $limit = 100): array
{
    $jobId = (int) $job['id'];
    sr_community_board_copy_job_assert_lock($pdo, $jobId, $lockToken);
    $limit = max(1, min(500, $limit));
    $failed = 0;
    $stmt = $pdo->prepare("SELECT * FROM sr_community_board_copy_job_maps WHERE job_id = :job_id AND created_storage_driver <> '' AND created_storage_key <> '' AND status <> 'cleaned' ORDER BY id DESC LIMIT " . (string) $limit);
    $stmt->execute(['job_id' => $jobId]);
    $maps = $stmt->fetchAll();
    foreach ($maps as $map) {
        sr_community_board_copy_job_assert_lock($pdo, $jobId, $lockToken);
        if (sr_storage_delete((string) $map['created_storage_driver'], (string) $map['created_storage_key'])) {
            sr_thumbnail_delete_variants([
                'storage_driver' => (string) $map['created_storage_driver'],
                'storage_key' => (string) $map['created_storage_key'],
            ]);
            sr_community_board_copy_job_mark_map($pdo, (int) $map['id'], (int) $map['target_id'], 'cleaned', '', '', '', $jobId, $lockToken);
        } else {
            $failed++;
            sr_community_board_copy_job_mark_map(
                $pdo,
                (int) $map['id'],
                (int) $map['target_id'],
                'cleanup_failed',
                '파일 삭제 실패',
                (string) $map['created_storage_driver'],
                (string) $map['created_storage_key'],
                $jobId,
                $lockToken
            );
        }
    }
    if ($failed > 0) {
        return ['failed' => $failed, 'remaining' => 0, 'processed' => count($maps)];
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sr_community_board_copy_job_maps WHERE job_id = :job_id AND created_storage_driver <> '' AND created_storage_key <> '' AND status <> 'cleaned'");
    $stmt->execute(['job_id' => $jobId]);
    $remainingFiles = (int) $stmt->fetchColumn();
    if ($remainingFiles > 0) {
        return ['failed' => 0, 'remaining' => $remainingFiles, 'processed' => count($maps)];
    }

    $targetBoardId = sr_community_board_copy_job_target_board_id($pdo, $job);
    if ($targetBoardId > 0) {
        sr_community_board_copy_job_assert_lock($pdo, $jobId, $lockToken);
        $stmt = $pdo->prepare('SELECT id FROM sr_community_posts WHERE board_id = :board_id');
        $stmt->execute(['board_id' => $targetBoardId]);
        $postIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        foreach ([
            'DELETE a FROM sr_community_attachments a INNER JOIN sr_community_posts p ON p.id = a.post_id WHERE p.board_id = :board_id',
            'DELETE si FROM sr_community_series_items si INNER JOIN sr_community_series s ON s.id = si.series_id WHERE s.board_id = :board_id',
            'DELETE FROM sr_community_series WHERE board_id = :board_id',
            'DELETE c FROM sr_community_comments c INNER JOIN sr_community_posts p ON p.id = c.post_id WHERE p.board_id = :board_id',
            'DELETE FROM sr_community_posts WHERE board_id = :board_id',
            'DELETE FROM sr_community_board_settings WHERE board_id = :board_id',
            'DELETE FROM sr_community_board_setting_sources WHERE board_id = :board_id',
            'DELETE FROM sr_community_boards WHERE id = :board_id',
        ] as $sql) {
            $pdo->prepare($sql)->execute(['board_id' => $targetBoardId]);
        }
        if (sr_community_categories_supported($pdo)) {
            $pdo->prepare('DELETE FROM sr_community_categories WHERE board_id = :board_id')->execute(['board_id' => $targetBoardId]);
        }
        sr_community_cleanup_body_files_for_deleted_posts($pdo, $postIds);
    }

    return ['failed' => 0, 'remaining' => 0, 'processed' => count($maps)];
}
