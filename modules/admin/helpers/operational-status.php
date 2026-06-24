<?php

declare(strict_types=1);

function sr_admin_operational_status_checks(): array
{
    return [
        [
            'label' => 'policy_documents.mail_jobs.queued',
            'title' => '정책 문서 안내메일 대기',
            'module' => 'policy_documents',
            'table' => 'sr_policy_document_mail_jobs',
            'where' => "status = 'queued'",
            'age_column' => 'updated_at',
            'delay_tolerance' => '1시간',
            'warn_after_seconds' => 3600,
            'target_sql' => "SELECT d.document_key, v.version_key, j.job_key AS target_fallback
                FROM sr_policy_document_mail_jobs j
                INNER JOIN sr_policy_documents d ON d.id = j.document_id
                INNER JOIN sr_policy_document_versions v ON v.id = j.version_id
                WHERE j.status = 'queued'
                ORDER BY j.updated_at ASC, j.id ASC
                LIMIT 5",
            'target_format' => '{document_key} / {version_key}',
            'target_fallback_prefix' => '작업',
            'followup' => '/admin/policy-documents에서 안내메일 발송 배치를 실행하거나 delivery 실패를 확인합니다.',
        ],
        [
            'label' => 'policy_documents.mail_jobs.failed',
            'title' => '정책 문서 안내메일 실패',
            'module' => 'policy_documents',
            'table' => 'sr_policy_document_mail_jobs',
            'where' => "status = 'failed'",
            'age_column' => 'updated_at',
            'delay_tolerance' => '즉시',
            'warn_after_seconds' => 0,
            'target_sql' => "SELECT d.document_key, v.version_key, j.job_key AS target_fallback
                FROM sr_policy_document_mail_jobs j
                INNER JOIN sr_policy_documents d ON d.id = j.document_id
                INNER JOIN sr_policy_document_versions v ON v.id = j.version_id
                WHERE j.status = 'failed'
                ORDER BY j.updated_at ASC, j.id ASC
                LIMIT 5",
            'target_format' => '{document_key} / {version_key}',
            'target_fallback_prefix' => '작업',
            'followup' => '/admin/policy-documents에서 실패 delivery와 메일 설정을 확인합니다.',
        ],
        [
            'label' => 'asset_recovery.open',
            'title' => '포인트/금액 미회수',
            'module' => 'asset_ledger',
            'table' => 'sr_asset_recovery_failures',
            'where' => "status = 'open'",
            'age_column' => 'updated_at',
            'delay_tolerance' => '즉시',
            'warn_after_seconds' => 0,
            'target_sql' => "SELECT source_module, subject_type, subject_id, account_id, id AS target_fallback
                FROM sr_asset_recovery_failures
                WHERE status = 'open'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5",
            'target_format' => '{source_module} {subject_type}#{subject_id} / 계정 #{account_id}',
            'target_fallback_prefix' => '미회수',
            'followup' => '/admin/assets/recovery-failures에서 재회수, 수동 해소, 취소 기준을 적용합니다.',
        ],
        [
            'label' => 'community.asset_recovery_legacy.open',
            'title' => '커뮤니티 legacy 자산 미회수',
            'module' => 'community',
            'table' => 'sr_community_asset_recovery_failures',
            'where' => "status = 'open'",
            'age_column' => 'updated_at',
            'delay_tolerance' => '즉시',
            'warn_after_seconds' => 0,
            'target_sql' => "SELECT asset_module, subject_type, subject_id, account_id, id AS target_fallback
                FROM sr_community_asset_recovery_failures
                WHERE status = 'open'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5",
            'target_format' => '{asset_module} {subject_type}#{subject_id} / 계정 #{account_id}',
            'target_fallback_prefix' => 'legacy 미회수',
            'followup' => '/admin/assets/recovery-failures에서 공통 미회수 큐를 우선 확인하고 legacy 잔여 row를 해소합니다.',
        ],
        [
            'label' => 'community.publisher_rewards.pending',
            'title' => '커뮤니티 게시자 보상 대기',
            'module' => 'community',
            'table' => 'sr_community_publisher_reward_logs',
            'where' => "status = 'pending'",
            'age_column' => 'updated_at',
            'delay_tolerance' => '15분',
            'warn_after_seconds' => 900,
            'target_sql' => "SELECT post_id, attachment_id, publisher_account_id, id AS target_fallback
                FROM sr_community_publisher_reward_logs
                WHERE status = 'pending'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5",
            'target_format' => '게시글 #{post_id} / 첨부 #{attachment_id} / 게시자 #{publisher_account_id}',
            'target_fallback_prefix' => '로그',
            'followup' => '/admin/community/publisher-rewards에서 대기 로그와 자산 지급 상태를 확인합니다.',
        ],
        [
            'label' => 'community.publisher_rewards.failed',
            'title' => '커뮤니티 게시자 보상 실패',
            'module' => 'community',
            'table' => 'sr_community_publisher_reward_logs',
            'where' => "status = 'failed'",
            'age_column' => 'updated_at',
            'delay_tolerance' => '즉시',
            'warn_after_seconds' => 0,
            'target_sql' => "SELECT post_id, attachment_id, publisher_account_id, id AS target_fallback
                FROM sr_community_publisher_reward_logs
                WHERE status = 'failed'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5",
            'target_format' => '게시글 #{post_id} / 첨부 #{attachment_id} / 게시자 #{publisher_account_id}',
            'target_fallback_prefix' => '로그',
            'followup' => '/admin/community/publisher-rewards에서 실패 사유와 중복 지급 가능성을 확인합니다.',
        ],
        [
            'label' => 'content.author_rewards.pending',
            'title' => '콘텐츠 작성자 보상 대기',
            'module' => 'content',
            'table' => 'sr_content_author_reward_logs',
            'where' => "status = 'pending'",
            'age_column' => 'updated_at',
            'delay_tolerance' => '15분',
            'warn_after_seconds' => 900,
            'target_sql' => "SELECT submission_id, content_id, author_account_id, id AS target_fallback
                FROM sr_content_author_reward_logs
                WHERE status = 'pending'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5",
            'target_format' => '제출본 #{submission_id} / 콘텐츠 #{content_id} / 작성자 #{author_account_id}',
            'target_fallback_prefix' => '로그',
            'followup' => '/admin/content/author-rewards에서 대기 로그와 자산 지급 상태를 확인합니다.',
        ],
        [
            'label' => 'content.author_rewards.failed',
            'title' => '콘텐츠 작성자 보상 실패',
            'module' => 'content',
            'table' => 'sr_content_author_reward_logs',
            'where' => "status = 'failed'",
            'age_column' => 'updated_at',
            'delay_tolerance' => '즉시',
            'warn_after_seconds' => 0,
            'target_sql' => "SELECT submission_id, content_id, author_account_id, id AS target_fallback
                FROM sr_content_author_reward_logs
                WHERE status = 'failed'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5",
            'target_format' => '제출본 #{submission_id} / 콘텐츠 #{content_id} / 작성자 #{author_account_id}',
            'target_fallback_prefix' => '로그',
            'followup' => '/admin/content/author-rewards에서 실패 사유와 중복 지급 가능성을 확인합니다.',
        ],
        [
            'label' => 'notification.deliveries.queued',
            'title' => '알림 delivery 대기',
            'module' => 'notification',
            'table' => 'sr_notification_deliveries',
            'where' => "status IN ('queued', 'processing')",
            'age_column' => 'created_at',
            'delay_tolerance' => '1시간',
            'warn_after_seconds' => 3600,
            'target_sql' => "SELECT n.title AS target_label, d.id AS target_fallback
                FROM sr_notification_deliveries d
                LEFT JOIN sr_notifications n ON n.id = d.notification_id
                WHERE d.status IN ('queued', 'processing')
                ORDER BY d.created_at ASC, d.id ASC
                LIMIT 5",
            'target_fallback_prefix' => 'delivery',
            'followup' => '알림 발송 provider 설정과 delivery queue를 확인합니다.',
        ],
        [
            'label' => 'notification.deliveries.failed',
            'title' => '알림 delivery 실패',
            'module' => 'notification',
            'table' => 'sr_notification_deliveries',
            'where' => "status IN ('failed', 'dead')",
            'age_column' => 'updated_at',
            'delay_tolerance' => '즉시',
            'warn_after_seconds' => 0,
            'target_sql' => "SELECT n.title AS target_label, d.id AS target_fallback
                FROM sr_notification_deliveries d
                LEFT JOIN sr_notifications n ON n.id = d.notification_id
                WHERE d.status IN ('failed', 'dead')
                ORDER BY d.updated_at ASC, d.id ASC
                LIMIT 5",
            'target_fallback_prefix' => 'delivery',
            'followup' => '실패 사유를 확인하고 재발송 또는 취소 기준을 적용합니다.',
        ],
        [
            'label' => 'content.storage_cleanup.pending',
            'title' => '콘텐츠 저장소 정리 대기',
            'module' => 'content',
            'table' => 'sr_content_storage_cleanup_failures',
            'where' => "status = 'pending'",
            'age_column' => 'updated_at',
            'delay_tolerance' => '24시간',
            'warn_after_seconds' => 86400,
            'target_sql' => "SELECT storage_key AS target_label, source_id AS target_fallback
                FROM sr_content_storage_cleanup_failures
                WHERE status = 'pending'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5",
            'followup' => '콘텐츠 저장소 정리 실패 목록과 파일 권한을 확인합니다.',
        ],
        [
            'label' => 'community.storage_cleanup.pending',
            'title' => '커뮤니티 저장소 정리 대기',
            'module' => 'community',
            'table' => 'sr_community_storage_cleanup_failures',
            'where' => "status = 'pending'",
            'age_column' => 'updated_at',
            'delay_tolerance' => '24시간',
            'warn_after_seconds' => 86400,
            'target_sql' => "SELECT storage_key AS target_label, source_id AS target_fallback
                FROM sr_community_storage_cleanup_failures
                WHERE status = 'pending'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5",
            'followup' => '커뮤니티 저장소 정리 실패 목록과 파일 권한을 확인합니다.',
        ],
        [
            'label' => 'community.board_copy.active',
            'title' => '게시판 복사 진행 중',
            'module' => 'community',
            'table' => 'sr_community_board_copy_jobs',
            'where' => "status IN ('pending', 'running')",
            'age_column' => 'updated_at',
            'delay_tolerance' => '15분',
            'warn_after_seconds' => 900,
            'target_sql' => "SELECT b.title AS target_label, j.id AS target_fallback
                FROM sr_community_board_copy_jobs j
                LEFT JOIN sr_community_boards b ON b.id = j.source_board_id
                WHERE j.status IN ('pending', 'running')
                ORDER BY j.updated_at ASC, j.id ASC
                LIMIT 5",
            'target_fallback_prefix' => '작업',
            'followup' => '진행 상태와 lock 만료, takeover 필요 여부를 확인합니다.',
        ],
        [
            'label' => 'community.board_copy.failed',
            'title' => '게시판 복사 실패',
            'module' => 'community',
            'table' => 'sr_community_board_copy_jobs',
            'where' => "status IN ('failed', 'canceled')",
            'age_column' => 'updated_at',
            'delay_tolerance' => '즉시',
            'warn_after_seconds' => 0,
            'target_sql' => "SELECT b.title AS target_label, j.id AS target_fallback
                FROM sr_community_board_copy_jobs j
                LEFT JOIN sr_community_boards b ON b.id = j.source_board_id
                WHERE j.status IN ('failed', 'canceled')
                ORDER BY j.updated_at ASC, j.id ASC
                LIMIT 5",
            'target_fallback_prefix' => '작업',
            'followup' => '실패 단계와 부분 생성물 정리 필요 여부를 확인합니다.',
        ],
        [
            'label' => 'community.level_recalculate.running',
            'title' => '커뮤니티 레벨 재계산 진행 중',
            'module' => 'community',
            'table' => 'sr_community_level_recalculate_jobs',
            'where' => "status = 'running'",
            'age_column' => 'updated_at',
            'delay_tolerance' => '15분',
            'warn_after_seconds' => 900,
            'target_sql' => "SELECT id AS target_fallback, requested_by, processed_total, total_count
                FROM sr_community_level_recalculate_jobs
                WHERE status = 'running'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5",
            'target_format' => '작업 #{target_fallback} / 요청자 #{requested_by} / {processed_total}/{total_count}',
            'target_fallback_prefix' => '작업',
            'followup' => '/admin/community/levels에서 재계산 진행 상태를 확인하고 필요하면 새 재계산을 실행합니다.',
        ],
        [
            'label' => 'community.level_recalculate.failed',
            'title' => '커뮤니티 레벨 재계산 실패',
            'module' => 'community',
            'table' => 'sr_community_level_recalculate_jobs',
            'where' => "status = 'failed'",
            'age_column' => 'updated_at',
            'delay_tolerance' => '즉시',
            'warn_after_seconds' => 0,
            'target_sql' => "SELECT id AS target_fallback, requested_by, processed_total, total_count
                FROM sr_community_level_recalculate_jobs
                WHERE status = 'failed'
                ORDER BY updated_at ASC, id ASC
                LIMIT 5",
            'target_format' => '작업 #{target_fallback} / 요청자 #{requested_by} / {processed_total}/{total_count}',
            'target_fallback_prefix' => '작업',
            'followup' => '/admin/community/levels에서 실패 사유를 확인하고 재계산을 다시 실행합니다.',
        ],
        [
            'label' => 'quiz.reward_grants.pending',
            'title' => '퀴즈 보상 지급 대기',
            'module' => 'quiz',
            'table' => 'sr_quiz_reward_grants',
            'where' => "status = 'pending'",
            'age_column' => 'updated_at',
            'delay_tolerance' => '15분',
            'warn_after_seconds' => 900,
            'target_sql' => "SELECT q.title AS target_label, g.quiz_id AS target_fallback
                FROM sr_quiz_reward_grants g
                LEFT JOIN sr_quiz_sets q ON q.id = g.quiz_id
                WHERE g.status = 'pending'
                ORDER BY g.updated_at ASC, g.id ASC
                LIMIT 5",
            'target_fallback_prefix' => '퀴즈',
            'followup' => '보상 정책과 자산 또는 쿠폰 provider 상태를 확인합니다.',
        ],
        [
            'label' => 'quiz.reward_grants.failed',
            'title' => '퀴즈 보상 지급 실패',
            'module' => 'quiz',
            'table' => 'sr_quiz_reward_grants',
            'where' => "status = 'failed'",
            'age_column' => 'failed_at',
            'delay_tolerance' => '즉시',
            'warn_after_seconds' => 0,
            'target_sql' => "SELECT q.title AS target_label, g.quiz_id AS target_fallback
                FROM sr_quiz_reward_grants g
                LEFT JOIN sr_quiz_sets q ON q.id = g.quiz_id
                WHERE g.status = 'failed'
                ORDER BY g.failed_at ASC, g.id ASC
                LIMIT 5",
            'target_fallback_prefix' => '퀴즈',
            'followup' => '중복 지급 없이 복구할 수 있는지 보상 로그를 확인합니다.',
        ],
        [
            'label' => 'survey.reward_grants.pending',
            'title' => '설문 보상 지급 대기',
            'module' => 'survey',
            'table' => 'sr_survey_reward_grants',
            'where' => "status = 'pending'",
            'age_column' => 'updated_at',
            'delay_tolerance' => '15분',
            'warn_after_seconds' => 900,
            'target_sql' => "SELECT s.title AS target_label, g.survey_id AS target_fallback
                FROM sr_survey_reward_grants g
                LEFT JOIN sr_survey_forms s ON s.id = g.survey_id
                WHERE g.status = 'pending'
                ORDER BY g.updated_at ASC, g.id ASC
                LIMIT 5",
            'target_fallback_prefix' => '설문',
            'followup' => '보상 정책과 자산 또는 쿠폰 provider 상태를 확인합니다.',
        ],
        [
            'label' => 'survey.reward_grants.failed',
            'title' => '설문 보상 지급 실패',
            'module' => 'survey',
            'table' => 'sr_survey_reward_grants',
            'where' => "status = 'failed'",
            'age_column' => 'failed_at',
            'delay_tolerance' => '즉시',
            'warn_after_seconds' => 0,
            'target_sql' => "SELECT s.title AS target_label, g.survey_id AS target_fallback
                FROM sr_survey_reward_grants g
                LEFT JOIN sr_survey_forms s ON s.id = g.survey_id
                WHERE g.status = 'failed'
                ORDER BY g.failed_at ASC, g.id ASC
                LIMIT 5",
            'target_fallback_prefix' => '설문',
            'followup' => '중복 지급 없이 복구할 수 있는지 보상 로그를 확인합니다.',
        ],
        [
            'label' => 'point.expiration.due',
            'title' => '포인트 만료 처리 대상',
            'module' => 'point',
            'table' => 'sr_point_transactions',
            'where' => "expires_at IS NOT NULL AND expires_at <= NOW() AND expires_remaining > 0",
            'age_column' => 'expires_at',
            'delay_tolerance' => '24시간',
            'warn_after_seconds' => 86400,
            'target_sql' => "SELECT '' AS target_label, account_id AS target_fallback
                FROM sr_point_transactions
                WHERE expires_at IS NOT NULL AND expires_at <= NOW() AND expires_remaining > 0
                ORDER BY expires_at ASC, id ASC
                LIMIT 5",
            'target_fallback_prefix' => '계정',
            'followup' => '포인트 만료 CLI 또는 다음 포인트 거래 처리 흐름을 확인합니다.',
        ],
    ];
}

function sr_admin_operational_status_rows(PDO $pdo): array
{
    $rows = [];
    foreach (sr_admin_operational_status_checks() as $check) {
        $rows[] = sr_admin_operational_status_row($pdo, $check);
    }

    return $rows;
}

function sr_admin_operational_status_row(PDO $pdo, array $check): array
{
    $label = (string) ($check['label'] ?? '');
    $moduleKey = (string) ($check['module'] ?? '');
    $table = (string) ($check['table'] ?? '');
    $where = (string) ($check['where'] ?? '1 = 1');
    $ageColumn = (string) ($check['age_column'] ?? 'updated_at');

    $row = [
        'label' => $label,
        'title' => (string) ($check['title'] ?? $label),
        'module' => $moduleKey,
        'table' => $table,
        'count' => 0,
        'oldest_at' => '',
        'age_seconds' => null,
        'delay_tolerance' => (string) ($check['delay_tolerance'] ?? ''),
        'warn_after_seconds' => max(0, (int) ($check['warn_after_seconds'] ?? 0)),
        'status' => 'ok',
        'status_label' => '정상',
        'message' => '',
        'targets' => [],
        'followup' => (string) ($check['followup'] ?? ''),
    ];

    if ($moduleKey !== '' && !sr_module_enabled($pdo, $moduleKey)) {
        $row['status'] = 'skipped';
        $row['status_label'] = '건너뜀';
        $row['message'] = '모듈이 비활성화되어 있습니다.';
        return $row;
    }

    if (!sr_admin_operational_status_safe_identifier($table) || !sr_admin_operational_status_safe_identifier($ageColumn) || !sr_admin_operational_status_safe_where($where)) {
        $row['status'] = 'error';
        $row['status_label'] = '오류';
        $row['message'] = '점검 기준의 SQL 조건이 안전하지 않습니다.';
        return $row;
    }

    try {
        $whereSql = sr_admin_operational_status_where_sql($pdo, $where);
        $stmt = $pdo->query(
            'SELECT COUNT(*) AS item_count, MIN(' . $ageColumn . ') AS oldest_at
             FROM ' . $table . '
             WHERE ' . $whereSql
        );
        $result = $stmt ? $stmt->fetch() : false;
        if (!is_array($result)) {
            $row['status'] = 'error';
            $row['status_label'] = '오류';
            $row['message'] = '점검 결과를 읽을 수 없습니다.';
            return $row;
        }

        $row['count'] = (int) ($result['item_count'] ?? 0);
        $row['oldest_at'] = trim((string) ($result['oldest_at'] ?? ''));
        if ((int) $row['count'] > 0) {
            $row['status'] = 'warning';
            $row['status_label'] = '확인 필요';
            $row['message'] = '처리되지 않은 항목이 있습니다.';
            $row['age_seconds'] = sr_admin_operational_status_age_seconds((string) $row['oldest_at']);
            $row['targets'] = sr_admin_operational_status_targets($pdo, $check);
            if ((int) $row['warn_after_seconds'] === 0 || (is_int($row['age_seconds']) && $row['age_seconds'] >= (int) $row['warn_after_seconds'])) {
                $row['status'] = 'overdue';
                $row['status_label'] = '지연 초과';
                $row['message'] = '허용 지연 기준을 넘긴 항목이 있습니다.';
            }
        }
    } catch (Throwable $exception) {
        $row['status'] = 'skipped';
        $row['status_label'] = '건너뜀';
        $row['message'] = sr_admin_operational_status_single_line($exception->getMessage());
    }

    return $row;
}

function sr_admin_operational_status_targets(PDO $pdo, array $check): array
{
    $sql = trim((string) ($check['target_sql'] ?? ''));
    if ($sql === '' || !sr_admin_operational_status_safe_select($sql)) {
        return [];
    }

    try {
        $stmt = $pdo->query(sr_admin_operational_status_where_sql($pdo, $sql));
        if (!$stmt) {
            return [];
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }

    $targets = [];
    $fallbackPrefix = trim((string) ($check['target_fallback_prefix'] ?? ''));
    $format = trim((string) ($check['target_format'] ?? ''));
    foreach ($rows as $row) {
        $label = $format !== ''
            ? sr_admin_operational_status_format_target($row, $format)
            : sr_admin_operational_status_single_line((string) ($row['target_label'] ?? ''));
        $fallback = sr_admin_operational_status_single_line((string) ($row['target_fallback'] ?? ''));
        if ($label === '' && $fallback !== '') {
            $label = ($fallbackPrefix !== '' ? $fallbackPrefix . ' #' : '#') . $fallback;
        }
        if ($label !== '' && !in_array($label, $targets, true)) {
            $targets[] = $label;
        }
    }

    return $targets;
}

function sr_admin_operational_status_format_target(array $row, string $format): string
{
    $hasValue = false;
    $label = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', static function (array $matches) use ($row, &$hasValue): string {
        $key = (string) ($matches[1] ?? '');
        $value = sr_admin_operational_status_single_line((string) ($row[$key] ?? ''));
        if ($value !== '') {
            $hasValue = true;
        }

        return $value;
    }, $format);

    if (!$hasValue) {
        return '';
    }

    return sr_admin_operational_status_single_line((string) $label);
}

function sr_admin_operational_status_age_seconds(string $value): ?int
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return max(0, time() - $timestamp);
}

function sr_admin_operational_status_safe_identifier(string $value): bool
{
    return preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/', $value) === 1;
}

function sr_admin_operational_status_where_sql(PDO $pdo, string $where): string
{
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable) {
        $driver = '';
    }

    if ($driver === 'sqlite') {
        return preg_replace('/\bNOW\(\)/i', 'CURRENT_TIMESTAMP', $where) ?? $where;
    }

    return $where;
}

function sr_admin_operational_status_safe_where(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }

    if (preg_match('/(?:;|--|\/\*|\*\/)/', $value) === 1) {
        return false;
    }

    return preg_match('/\b(?:ALTER|CREATE|DELETE|DROP|GRANT|INSERT|LOAD|OUTFILE|REPLACE|REVOKE|TRUNCATE|UPDATE)\b/i', $value) !== 1;
}

function sr_admin_operational_status_safe_select(string $value): bool
{
    $value = trim($value);
    if ($value === '' || preg_match('/\ASELECT\b/i', $value) !== 1) {
        return false;
    }

    if (preg_match('/(?:;|--|\/\*|\*\/)/', $value) === 1) {
        return false;
    }

    return preg_match('/\b(?:ALTER|CREATE|DELETE|DROP|GRANT|INSERT|LOAD|OUTFILE|REPLACE|REVOKE|TRUNCATE|UPDATE)\b/i', $value) !== 1;
}

function sr_admin_operational_status_single_line(string $value): string
{
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return substr(trim($value), 0, 180);
}

function sr_admin_operational_status_summary(array $rows): array
{
    $summary = [
        'ok' => 0,
        'warning' => 0,
        'overdue' => 0,
        'skipped' => 0,
        'error' => 0,
        'total_count' => 0,
    ];

    foreach ($rows as $row) {
        $status = (string) ($row['status'] ?? 'error');
        if (!array_key_exists($status, $summary)) {
            $status = 'error';
        }
        $summary[$status]++;
        $summary['total_count'] += (int) ($row['count'] ?? 0);
    }

    return $summary;
}

function sr_admin_operational_status_cli_row_line(array $row): string
{
    $label = (string) ($row['label'] ?? '');
    $status = (string) ($row['status'] ?? 'error');
    if ($status === 'skipped' || $status === 'error') {
        return $label . "\t" . $status . "\t" . sr_admin_operational_status_single_line((string) ($row['message'] ?? ''));
    }

    return $label
        . "\tstatus=" . $status
        . "\tcount=" . (int) ($row['count'] ?? 0)
        . "\tallowed_delay=" . sr_admin_operational_status_cli_value((string) ($row['delay_tolerance'] ?? ''))
        . "\toldest_at=" . sr_admin_operational_status_cli_value((string) ($row['oldest_at'] ?? ''));
}

function sr_admin_operational_status_cli_summary_line(array $summary): string
{
    return 'summary'
        . "\tok=" . (int) ($summary['ok'] ?? 0)
        . "\twarning=" . (int) ($summary['warning'] ?? 0)
        . "\toverdue=" . (int) ($summary['overdue'] ?? 0)
        . "\tskipped=" . (int) ($summary['skipped'] ?? 0)
        . "\terror=" . (int) ($summary['error'] ?? 0)
        . "\ttotal_count=" . (int) ($summary['total_count'] ?? 0);
}

function sr_admin_operational_status_cli_value(string $value): string
{
    $value = trim($value);
    return $value === '' ? '-' : sr_admin_operational_status_single_line($value);
}
