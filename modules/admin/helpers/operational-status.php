<?php

declare(strict_types=1);

function sr_admin_operational_status_checks(): array
{
    return [
        [
            'label' => 'notification.deliveries.queued',
            'title' => '알림 delivery 대기',
            'module' => 'notification',
            'table' => 'sr_notification_deliveries',
            'where' => "status IN ('queued', 'processing')",
            'age_column' => 'created_at',
            'delay_tolerance' => '1시간',
            'warn_after_seconds' => 3600,
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
            'followup' => '실패 단계와 부분 생성물 정리 필요 여부를 확인합니다.',
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
