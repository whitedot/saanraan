<?php

declare(strict_types=1);

function sr_community_board_delete_job_statuses(): array
{
    return ['pending', 'running', 'failed', 'cleanup_required', 'completed'];
}

function sr_community_board_delete_job_status_label(string $status): string
{
    $labels = [
        'pending' => '대기',
        'running' => '처리 중',
        'failed' => '실패',
        'cleanup_required' => '정리 필요',
        'completed' => '완료',
    ];

    return $labels[$status] ?? $status;
}

function sr_community_board_delete_job_stages(): array
{
    return ['prepare', 'reports', 'entitlements', 'series_refs', 'scraps', 'comments', 'attachments', 'posts', 'series', 'board_meta', 'storage', 'complete'];
}

function sr_community_board_delete_job_stage_label(string $stage): string
{
    $labels = [
        'prepare' => '준비',
        'reports' => '신고 정리',
        'entitlements' => '접근권 정리',
        'series_refs' => '시리즈 연결 정리',
        'scraps' => '스크랩 정리',
        'comments' => '댓글 정리',
        'attachments' => '첨부 정리',
        'posts' => '게시글 정리',
        'series' => '시리즈 정리',
        'board_meta' => '게시판 설정 정리',
        'storage' => '저장소 정리',
        'complete' => '완료',
    ];

    return $labels[$stage] ?? $stage;
}

function sr_community_board_delete_job_stage_progress_label(string $stage): string
{
    $stages = sr_community_board_delete_job_stages();
    $index = array_search($stage, $stages, true);
    $number = $index === false ? 1 : ((int) $index + 1);

    return (string) $number . '/' . (string) count($stages) . ' ' . sr_community_board_delete_job_stage_label($stage);
}

function sr_community_board_delete_job_thresholds(): array
{
    return [
        'posts' => 500,
        'comments' => 5000,
        'attachments' => 500,
        'series' => 500,
        'files' => 100,
    ];
}

function sr_community_board_delete_job_required(array $references): bool
{
    $thresholds = sr_community_board_delete_job_thresholds();

    return (int) ($references['posts'] ?? 0) >= $thresholds['posts']
        || (int) ($references['comments'] ?? 0) >= $thresholds['comments']
        || (int) ($references['attachments'] ?? 0) >= $thresholds['attachments']
        || (int) ($references['series'] ?? 0) >= $thresholds['series']
        || (int) ($references['attachments'] ?? 0) >= $thresholds['files'];
}

function sr_community_board_delete_load_assessment(array $references): array
{
    $posts = max(0, (int) ($references['posts'] ?? 0));
    $comments = max(0, (int) ($references['comments'] ?? 0));
    $attachments = max(0, (int) ($references['attachments'] ?? 0));
    $series = max(0, (int) ($references['series'] ?? 0));
    $targetRecords = $posts + $comments + $attachments + $series;
    $batchRequired = sr_community_board_delete_job_required($references);

    $grade = 'low';
    if ($batchRequired) {
        $grade = 'very_high';
    } elseif ($posts >= 200 || $comments >= 1000 || $attachments >= 50 || $series >= 100) {
        $grade = 'high';
    } elseif ($posts >= 50 || $comments >= 200 || $attachments >= 20 || $series >= 50) {
        $grade = 'caution';
    }

    return [
        'grade' => $grade,
        'label' => sr_community_board_delete_load_grade_label($grade),
        'target_records' => $targetRecords,
        'requires_confirmation' => true,
        'requires_batch_review' => $batchRequired,
        'recommended_time' => sr_community_board_delete_load_recommended_time($grade),
        'failure_state' => $batchRequired
            ? '배치 작업은 처리된 항목을 유지하고 실패 상태와 다음 처리 위치를 작업 목록에 남깁니다.'
            : '소량 삭제는 한 요청에서 처리되며, 실패하면 화면 오류와 감사 로그로 확인합니다.',
    ];
}

function sr_community_board_delete_load_grade_label(string $grade): string
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

function sr_community_board_delete_load_recommended_time(string $grade): string
{
    if ($grade === 'low') {
        return '일반 운영 시간에도 실행할 수 있습니다. 실행 직전 대상 수만 확인하세요.';
    }
    if ($grade === 'caution') {
        return '일반 운영 시간에도 실행할 수 있지만, 변경 직전 대상 수와 저장소 여유를 확인하세요.';
    }

    return '방문자가 적은 시간에 실행하고, 가능하면 백업 또는 staging 검증 후 진행하세요.';
}

function sr_community_board_delete_jobs_available(PDO $pdo): bool
{
    static $available = null;
    if (is_bool($available)) {
        return $available;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_community_board_delete_jobs LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_community_board_delete_job_maps LIMIT 1');
        $available = true;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function sr_community_board_delete_job_missing_table(Throwable $exception): bool
{
    $message = strtolower($exception->getMessage());
    if (str_contains($message, 'no such table') || str_contains($message, 'base table or view not found')) {
        return true;
    }
    if ($exception instanceof PDOException) {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        return in_array($sqlState, ['42S02', '42P01'], true);
    }

    return false;
}

function sr_community_board_delete_job_json(array $job, string $key): array
{
    $decoded = json_decode((string) ($job[$key] ?? ''), true);

    return is_array($decoded) ? $decoded : [];
}

function sr_community_board_delete_job_active_for_board(PDO $pdo, int $boardId): ?array
{
    if ($boardId < 1 || !sr_community_board_delete_jobs_available($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_community_board_delete_jobs
         WHERE board_id = :board_id
           AND status IN ('pending', 'running', 'failed', 'cleanup_required')
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute(['board_id' => $boardId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_board_delete_job_create(PDO $pdo, int $boardId, int $accountId): int
{
    $activeJob = sr_community_board_delete_job_active_for_board($pdo, $boardId);
    if (is_array($activeJob)) {
        return (int) $activeJob['id'];
    }

    $check = sr_community_can_delete_board($pdo, $boardId);
    if (empty($check['can_delete']) || !is_array($check['board'] ?? null)) {
        $errors = is_array($check['errors'] ?? null) ? $check['errors'] : ['게시판을 삭제할 수 없습니다.'];
        throw new RuntimeException(implode("\n", array_map('strval', $errors)));
    }
    if (!sr_community_board_delete_jobs_available($pdo)) {
        throw new RuntimeException('게시판 삭제 작업 테이블이 준비되지 않았습니다. 모듈 업데이트를 먼저 실행하세요.');
    }

    $board = $check['board'];
    $now = sr_now();
    $counts = [
        'posts' => (int) ($check['references']['posts'] ?? 0),
        'comments' => (int) ($check['references']['comments'] ?? 0),
        'attachments' => (int) ($check['references']['attachments'] ?? 0),
        'series' => (int) ($check['references']['series'] ?? 0),
    ];
    $snapshot = [
        'id' => (int) ($board['id'] ?? $boardId),
        'board_key' => (string) ($board['board_key'] ?? ''),
        'title' => (string) ($board['title'] ?? ''),
        'status' => (string) ($board['status'] ?? ''),
        'created_at' => (string) ($board['created_at'] ?? ''),
        'updated_at' => (string) ($board['updated_at'] ?? ''),
    ];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO sr_community_board_delete_jobs
                (board_id, requested_by, status, stage, board_snapshot_json, counts_json, processed_json, created_at, updated_at)
             VALUES
                (:board_id, :requested_by, 'pending', 'prepare', :board_snapshot_json, :counts_json, :processed_json, :created_at, :updated_at)"
        );
        $stmt->execute([
            'board_id' => $boardId,
            'requested_by' => $accountId > 0 ? $accountId : null,
            'board_snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'counts_json' => json_encode($counts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'processed_json' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $jobId = (int) $pdo->lastInsertId();
        $pdo->prepare("UPDATE sr_community_boards SET status = 'disabled', updated_at = :updated_at WHERE id = :id")
            ->execute(['updated_at' => $now, 'id' => $boardId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    if (function_exists('sr_community_enabled_boards_file_cache_mark_stale')) {
        sr_community_enabled_boards_file_cache_mark_stale();
    }

    return $jobId;
}

function sr_community_board_delete_job_by_id(PDO $pdo, int $jobId): ?array
{
    if ($jobId < 1 || !sr_community_board_delete_jobs_available($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_community_board_delete_jobs WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $jobId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_board_delete_jobs_recent(PDO $pdo, int $limit = 30): array
{
    if (!sr_community_board_delete_jobs_available($pdo)) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $stmt = $pdo->query(
        "SELECT j.*, b.title AS current_board_title, b.board_key AS current_board_key
         FROM sr_community_board_delete_jobs j
         LEFT JOIN sr_community_boards b ON b.id = j.board_id
         ORDER BY CASE WHEN j.status IN ('pending', 'running', 'failed', 'cleanup_required') THEN 0 ELSE 1 END ASC,
                  j.updated_at DESC,
                  j.id DESC
         LIMIT " . $limit
    );

    return $stmt ? $stmt->fetchAll() : [];
}

function sr_community_board_delete_job_map_status_counts(PDO $pdo, int $jobId): array
{
    if ($jobId < 1 || !sr_community_board_delete_jobs_available($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT entity_type, status, COUNT(*) AS count_value
         FROM sr_community_board_delete_job_maps
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

function sr_community_board_delete_job_failed_maps(PDO $pdo, int $jobId, int $limit = 10): array
{
    if ($jobId < 1 || !sr_community_board_delete_jobs_available($pdo)) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_community_board_delete_job_maps
         WHERE job_id = :job_id
           AND status = 'failed'
         ORDER BY updated_at DESC, id DESC
         LIMIT " . (string) $limit
    );
    $stmt->execute(['job_id' => $jobId]);

    return $stmt->fetchAll();
}

function sr_community_board_delete_job_assert_lock(PDO $pdo, int $jobId, string $lockToken): void
{
    if ($jobId < 1 || $lockToken === '') {
        throw new RuntimeException('삭제 작업 잠금이 올바르지 않습니다.');
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sr_community_board_delete_jobs WHERE id = :id AND status = 'running' AND lock_token = :lock_token");
    $stmt->execute(['id' => $jobId, 'lock_token' => $lockToken]);
    if ((int) $stmt->fetchColumn() < 1) {
        throw new RuntimeException('삭제 작업 잠금이 만료되었습니다.');
    }
}

function sr_community_board_delete_job_run(PDO $pdo, int $jobId, int $accountId, array $limits = []): array
{
    unset($accountId);
    $job = sr_community_board_delete_job_by_id($pdo, $jobId);
    if (!is_array($job)) {
        throw new RuntimeException('삭제 작업을 찾을 수 없습니다.');
    }
    if ((string) ($job['status'] ?? '') === 'completed') {
        return ['done' => true, 'stage' => 'complete', 'status' => 'completed', 'message' => '게시판 삭제 작업이 이미 완료되었습니다.'];
    }

    $token = bin2hex(random_bytes(16));
    $now = sr_now();
    $stmt = $pdo->prepare(
        "UPDATE sr_community_board_delete_jobs
         SET status = 'running', lock_token = :lock_token, locked_at = :locked_at, started_at = COALESCE(started_at, :started_at), updated_at = :updated_at
         WHERE id = :id
           AND status IN ('pending', 'running', 'failed', 'cleanup_required')
           AND (lock_token = '' OR locked_at IS NULL OR locked_at < :stale_before)"
    );
    $stmt->execute([
        'lock_token' => $token,
        'locked_at' => $now,
        'started_at' => $now,
        'updated_at' => $now,
        'id' => $jobId,
        'stale_before' => date('Y-m-d H:i:s', time() - 900),
    ]);
    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('다른 요청이 삭제 작업을 처리 중입니다. 잠시 후 다시 시도하세요.');
    }

    $job = sr_community_board_delete_job_by_id($pdo, $jobId);
    if (!is_array($job)) {
        throw new RuntimeException('삭제 작업을 찾을 수 없습니다.');
    }

    try {
        $result = sr_community_board_delete_job_run_stage($pdo, $job, $limits, $token);
        $releaseStatus = !empty($result['done']) ? (string) ($result['status'] ?? 'running') : 'running';
        $completedAtSql = $releaseStatus === 'completed' ? ', completed_at = :completed_at' : '';
        $stmt = $pdo->prepare(
            "UPDATE sr_community_board_delete_jobs
             SET status = :status, stage = :stage, lock_token = '', locked_at = NULL, last_error = :last_error, updated_at = :updated_at" . $completedAtSql . "
             WHERE id = :id AND lock_token = :lock_token"
        );
        $params = [
            'status' => $releaseStatus,
            'stage' => (string) ($result['stage'] ?? $job['stage']),
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
        $pdo->prepare(
            "UPDATE sr_community_board_delete_jobs
             SET status = 'failed', lock_token = '', locked_at = NULL, last_error = :last_error, updated_at = :updated_at
             WHERE id = :id AND lock_token = :lock_token"
        )->execute([
            'last_error' => $exception->getMessage(),
            'updated_at' => sr_now(),
            'id' => $jobId,
            'lock_token' => $token,
        ]);
        throw $exception;
    }
}

function sr_community_board_delete_job_run_stage(PDO $pdo, array $job, array $limits, string $lockToken): array
{
    $stage = (string) ($job['stage'] ?? 'prepare');
    if ($stage === 'prepare') {
        sr_community_board_delete_job_assert_lock($pdo, (int) $job['id'], $lockToken);
        $pdo->prepare("UPDATE sr_community_boards SET status = 'disabled', updated_at = :updated_at WHERE id = :id")
            ->execute(['updated_at' => sr_now(), 'id' => (int) $job['board_id']]);
        if (function_exists('sr_community_enabled_boards_file_cache_mark_stale')) {
            sr_community_enabled_boards_file_cache_mark_stale();
        }
        return ['done' => false, 'stage' => 'reports', 'status' => 'running', 'message' => '게시판을 비활성화하고 삭제 준비를 완료했습니다.'];
    }
    if ($stage === 'reports') {
        return sr_community_board_delete_job_delete_reports($pdo, $job, (int) ($limits['reports'] ?? 300), $lockToken);
    }
    if ($stage === 'entitlements') {
        return sr_community_board_delete_job_delete_entitlements($pdo, $job, (int) ($limits['entitlements'] ?? 300), $lockToken);
    }
    if ($stage === 'series_refs') {
        return sr_community_board_delete_job_delete_series_refs($pdo, $job, (int) ($limits['series_refs'] ?? 300), $lockToken);
    }
    if ($stage === 'scraps') {
        return sr_community_board_delete_job_delete_id_stage($pdo, $job, 'sr_community_scraps', "post_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)", [], 'scraps', 'comments', (int) ($limits['scraps'] ?? 300), $lockToken);
    }
    if ($stage === 'comments') {
        return sr_community_board_delete_job_delete_id_stage($pdo, $job, 'sr_community_comments', "post_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)", [], 'comments', 'attachments', (int) ($limits['comments'] ?? 300), $lockToken);
    }
    if ($stage === 'attachments') {
        return sr_community_board_delete_job_delete_attachments($pdo, $job, (int) ($limits['attachments'] ?? 100), $lockToken);
    }
    if ($stage === 'posts') {
        return sr_community_board_delete_job_delete_posts($pdo, $job, (int) ($limits['posts'] ?? 100), $lockToken);
    }
    if ($stage === 'series') {
        return sr_community_board_delete_job_delete_id_stage($pdo, $job, 'sr_community_series', 'board_id = :board_id', [], 'series', 'board_meta', (int) ($limits['series'] ?? 100), $lockToken);
    }
    if ($stage === 'board_meta') {
        sr_community_board_delete_job_delete_board_meta($pdo, $job, $lockToken);
        return ['done' => false, 'stage' => 'storage', 'status' => 'running', 'message' => '게시판 설정과 게시판 row를 정리했습니다.'];
    }
    if ($stage === 'storage') {
        $cleanup = sr_community_board_delete_job_cleanup_storage($pdo, $job, (int) ($limits['storage'] ?? 100), $lockToken);
        if ((int) ($cleanup['failed'] ?? 0) > 0) {
            return [
                'done' => true,
                'stage' => 'storage',
                'status' => 'cleanup_required',
                'message' => '일부 저장소 파일 정리가 실패했습니다. 다시 시도하거나 저장소 정리 실패 목록을 확인하세요.',
                'error' => '저장소 정리 실패 항목 ' . (string) (int) $cleanup['failed'] . '개가 남아 있습니다.',
            ];
        }
        if ((int) ($cleanup['remaining'] ?? 0) > 0) {
            return ['done' => false, 'stage' => 'storage', 'status' => 'running', 'message' => '저장소 파일을 묶음으로 정리했습니다.'];
        }
        return ['done' => false, 'stage' => 'complete', 'status' => 'running', 'message' => '저장소 정리를 완료했습니다.'];
    }
    if ($stage === 'complete') {
        return ['done' => true, 'stage' => 'complete', 'status' => 'completed', 'message' => '게시판 삭제 작업이 완료되었습니다.'];
    }

    throw new RuntimeException('삭제 작업 단계가 올바르지 않습니다.');
}

function sr_community_board_delete_job_stage_result(PDO $pdo, int $jobId, string $stage, string $nextStage, int $processed): array
{
    $processedJson = [];
    $stmt = $pdo->prepare('SELECT processed_json FROM sr_community_board_delete_jobs WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $jobId]);
    $decoded = json_decode((string) $stmt->fetchColumn(), true);
    if (is_array($decoded)) {
        $processedJson = $decoded;
    }
    $processedJson[$stage] = (int) ($processedJson[$stage] ?? 0) + $processed;
    $pdo->prepare('UPDATE sr_community_board_delete_jobs SET processed_json = :processed_json, updated_at = :updated_at WHERE id = :id')
        ->execute([
            'processed_json' => json_encode($processedJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => sr_now(),
            'id' => $jobId,
        ]);

    return [
        'done' => false,
        'stage' => $processed > 0 ? $stage : $nextStage,
        'status' => 'running',
        'message' => $processed > 0 ? '묶음 처리를 완료했습니다.' : '다음 단계로 이동합니다.',
    ];
}

function sr_community_board_delete_job_fetch_ids(PDO $pdo, string $tableName, string $whereSql, array $params, int $limit): array
{
    if (!preg_match('/\Asr_[a-z0-9_]+\z/', $tableName)) {
        return [];
    }

    $limit = max(1, min(1000, $limit));
    try {
        $stmt = $pdo->prepare('SELECT id FROM ' . $tableName . ' WHERE ' . $whereSql . ' ORDER BY id ASC LIMIT ' . (string) $limit);
        $stmt->execute($params);
    } catch (Throwable $exception) {
        if (sr_community_board_delete_job_missing_table($exception)) {
            return [];
        }
        throw $exception;
    }

    return array_values(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
}

function sr_community_board_delete_job_delete_ids(PDO $pdo, string $tableName, array $ids): int
{
    if ($ids === [] || !preg_match('/\Asr_[a-z0-9_]+\z/', $tableName)) {
        return 0;
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if ($ids === []) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = $pdo->prepare('DELETE FROM ' . $tableName . ' WHERE id IN (' . $placeholders . ')');
        $stmt->execute($ids);
    } catch (Throwable $exception) {
        if (sr_community_board_delete_job_missing_table($exception)) {
            return 0;
        }
        throw $exception;
    }

    return (int) $stmt->rowCount();
}

function sr_community_board_delete_job_delete_id_stage(PDO $pdo, array $job, string $tableName, string $whereSql, array $params, string $stage, string $nextStage, int $limit, string $lockToken): array
{
    sr_community_board_delete_job_assert_lock($pdo, (int) $job['id'], $lockToken);
    $params['board_id'] = (int) $job['board_id'];
    $ids = sr_community_board_delete_job_fetch_ids($pdo, $tableName, $whereSql, $params, $limit);
    $deleted = sr_community_board_delete_job_delete_ids($pdo, $tableName, $ids);

    return sr_community_board_delete_job_stage_result($pdo, (int) $job['id'], $stage, $nextStage, $deleted);
}

function sr_community_board_delete_job_delete_reports(PDO $pdo, array $job, int $limit, string $lockToken): array
{
    $boardId = (int) $job['board_id'];
    $targets = [
        ["target_type = 'post' AND target_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)", []],
        ["target_type = 'comment' AND target_id IN (SELECT c.id FROM sr_community_comments c INNER JOIN sr_community_posts p ON p.id = c.post_id WHERE p.board_id = :board_id)", []],
        ["target_type = 'series' AND target_id IN (SELECT id FROM sr_community_series WHERE board_id = :board_id)", []],
    ];
    foreach ($targets as $target) {
        $ids = sr_community_board_delete_job_fetch_ids($pdo, 'sr_community_reports', $target[0], ['board_id' => $boardId], $limit);
        if ($ids !== []) {
            sr_community_board_delete_job_assert_lock($pdo, (int) $job['id'], $lockToken);
            $deleted = sr_community_board_delete_job_delete_ids($pdo, 'sr_community_reports', $ids);
            return sr_community_board_delete_job_stage_result($pdo, (int) $job['id'], 'reports', 'entitlements', $deleted);
        }
    }

    return sr_community_board_delete_job_stage_result($pdo, (int) $job['id'], 'reports', 'entitlements', 0);
}

function sr_community_board_delete_job_delete_entitlements(PDO $pdo, array $job, int $limit, string $lockToken): array
{
    $boardId = (int) $job['board_id'];
    $targets = [
        ["subject_type IN ('community_post', 'community.post') AND subject_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)", []],
        ["subject_type IN ('community.attachment', 'community_attachment') AND subject_id IN (SELECT a.id FROM sr_community_attachments a INNER JOIN sr_community_posts p ON p.id = a.post_id WHERE p.board_id = :board_id)", []],
    ];
    foreach ($targets as $target) {
        $ids = sr_community_board_delete_job_fetch_ids($pdo, 'sr_community_access_entitlements', $target[0], ['board_id' => $boardId], $limit);
        if ($ids !== []) {
            sr_community_board_delete_job_assert_lock($pdo, (int) $job['id'], $lockToken);
            $deleted = sr_community_board_delete_job_delete_ids($pdo, 'sr_community_access_entitlements', $ids);
            return sr_community_board_delete_job_stage_result($pdo, (int) $job['id'], 'entitlements', 'series_refs', $deleted);
        }
    }

    return sr_community_board_delete_job_stage_result($pdo, (int) $job['id'], 'entitlements', 'series_refs', 0);
}

function sr_community_board_delete_job_delete_series_refs(PDO $pdo, array $job, int $limit, string $lockToken): array
{
    $targets = [
        ['sr_community_series_scraps', 'series_id IN (SELECT id FROM sr_community_series WHERE board_id = :board_id)'],
        ['sr_community_series_items', 'series_id IN (SELECT id FROM sr_community_series WHERE board_id = :board_id)'],
    ];
    foreach ($targets as $target) {
        $ids = sr_community_board_delete_job_fetch_ids($pdo, $target[0], $target[1], ['board_id' => (int) $job['board_id']], $limit);
        if ($ids !== []) {
            sr_community_board_delete_job_assert_lock($pdo, (int) $job['id'], $lockToken);
            $deleted = sr_community_board_delete_job_delete_ids($pdo, $target[0], $ids);
            return sr_community_board_delete_job_stage_result($pdo, (int) $job['id'], 'series_refs', 'scraps', $deleted);
        }
    }

    return sr_community_board_delete_job_stage_result($pdo, (int) $job['id'], 'series_refs', 'scraps', 0);
}

function sr_community_board_delete_job_record_storage_map(PDO $pdo, int $jobId, int $sourceId, string $driver, string $key): void
{
    if ($jobId < 1 || $sourceId < 1 || $key === '' || !sr_storage_key_is_safe($key)) {
        return;
    }
    $driver = in_array($driver, ['local', 's3'], true) ? $driver : 'local';
    $now = sr_now();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO sr_community_board_delete_job_maps
                (job_id, entity_type, source_id, status, storage_driver, storage_key, created_at, updated_at)
             VALUES
                (:job_id, 'attachment_file', :source_id, 'pending', :storage_driver, :storage_key, :created_at, :updated_at)"
        );
        $stmt->execute([
            'job_id' => $jobId,
            'source_id' => $sourceId,
            'storage_driver' => $driver,
            'storage_key' => $key,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    } catch (Throwable) {
        // Duplicate storage maps are harmless; the attachment row will not be deleted twice.
    }
}

function sr_community_board_delete_job_delete_attachments(PDO $pdo, array $job, int $limit, string $lockToken): array
{
    sr_community_board_delete_job_assert_lock($pdo, (int) $job['id'], $lockToken);
    $limit = max(1, min(500, $limit));
    $stmt = $pdo->prepare(
        'SELECT a.*
         FROM sr_community_attachments a
         INNER JOIN sr_community_posts p ON p.id = a.post_id
         WHERE p.board_id = :board_id
         ORDER BY a.id ASC
         LIMIT ' . (string) $limit
    );
    $stmt->execute(['board_id' => (int) $job['board_id']]);
    $attachments = $stmt->fetchAll();
    $ids = [];
    foreach ($attachments as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $attachmentId = (int) ($attachment['id'] ?? 0);
        $driver = function_exists('sr_community_attachment_storage_driver')
            ? sr_community_attachment_storage_driver($attachment)
            : (string) ($attachment['storage_driver'] ?? 'local');
        $key = function_exists('sr_community_attachment_storage_key')
            ? sr_community_attachment_storage_key($attachment)
            : (string) ($attachment['storage_key'] ?? '');
        if ($attachmentId > 0) {
            $ids[] = $attachmentId;
            sr_community_board_delete_job_record_storage_map($pdo, (int) $job['id'], $attachmentId, $driver, $key);
        }
    }
    $deleted = sr_community_board_delete_job_delete_ids($pdo, 'sr_community_attachments', $ids);

    return sr_community_board_delete_job_stage_result($pdo, (int) $job['id'], 'attachments', 'posts', $deleted);
}

function sr_community_board_delete_job_delete_posts(PDO $pdo, array $job, int $limit, string $lockToken): array
{
    sr_community_board_delete_job_assert_lock($pdo, (int) $job['id'], $lockToken);
    $ids = sr_community_board_delete_job_fetch_ids($pdo, 'sr_community_posts', 'board_id = :board_id', ['board_id' => (int) $job['board_id']], $limit);
    if ($ids !== []) {
        sr_community_cleanup_body_files_for_deleted_posts($pdo, $ids);
    }
    $deleted = sr_community_board_delete_job_delete_ids($pdo, 'sr_community_posts', $ids);

    return sr_community_board_delete_job_stage_result($pdo, (int) $job['id'], 'posts', 'series', $deleted);
}

function sr_community_board_delete_job_delete_board_meta(PDO $pdo, array $job, string $lockToken): void
{
    sr_community_board_delete_job_assert_lock($pdo, (int) $job['id'], $lockToken);
    $boardId = (int) $job['board_id'];
    $pdo->beginTransaction();
    try {
        foreach ([
            'sr_community_board_setting_sources',
            'sr_community_board_settings',
            'sr_community_board_managers',
            'sr_community_categories',
        ] as $tableName) {
            try {
                $pdo->prepare('DELETE FROM ' . $tableName . ' WHERE board_id = :board_id')->execute(['board_id' => $boardId]);
            } catch (Throwable $exception) {
                if (!sr_community_board_delete_job_missing_table($exception)) {
                    throw $exception;
                }
            }
        }
        $pdo->prepare('DELETE FROM sr_community_boards WHERE id = :id')->execute(['id' => $boardId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_community_board_delete_job_cleanup_storage(PDO $pdo, array $job, int $limit, string $lockToken): array
{
    sr_community_board_delete_job_assert_lock($pdo, (int) $job['id'], $lockToken);
    $limit = max(1, min(500, $limit));
    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_community_board_delete_job_maps
         WHERE job_id = :job_id
           AND entity_type = 'attachment_file'
           AND status <> 'cleaned'
         ORDER BY id ASC
         LIMIT " . (string) $limit
    );
    $stmt->execute(['job_id' => (int) $job['id']]);
    $maps = $stmt->fetchAll();
    $failed = 0;
    foreach ($maps as $map) {
        if (!is_array($map)) {
            continue;
        }
        $driver = (string) ($map['storage_driver'] ?? 'local');
        $key = (string) ($map['storage_key'] ?? '');
        if ($key !== '') {
            sr_thumbnail_delete_variants([
                'module_key' => 'community',
                'storage_driver' => $driver,
                'storage_key' => $key,
            ]);
        }
        if ($key !== '' && sr_storage_delete($driver, $key)) {
            $pdo->prepare("UPDATE sr_community_board_delete_job_maps SET status = 'cleaned', error_text = '', updated_at = :updated_at WHERE id = :id")
                ->execute(['updated_at' => sr_now(), 'id' => (int) $map['id']]);
            continue;
        }
        $failed++;
        $pdo->prepare("UPDATE sr_community_board_delete_job_maps SET status = 'failed', error_text = :error_text, updated_at = :updated_at WHERE id = :id")
            ->execute([
                'error_text' => '저장소 파일 정리에 실패했습니다.',
                'updated_at' => sr_now(),
                'id' => (int) $map['id'],
            ]);
        sr_community_record_storage_cleanup_failure($pdo, 'board_delete_attachment', (int) $job['board_id'], $driver, $key, '게시판 삭제 후 첨부 파일 저장소 정리에 실패했습니다.');
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM sr_community_board_delete_job_maps
         WHERE job_id = :job_id
           AND entity_type = 'attachment_file'
           AND status <> 'cleaned'"
    );
    $stmt->execute(['job_id' => (int) $job['id']]);

    return ['failed' => $failed, 'remaining' => (int) $stmt->fetchColumn(), 'processed' => count($maps)];
}
