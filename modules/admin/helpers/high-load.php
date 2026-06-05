<?php

declare(strict_types=1);

function sr_admin_high_load_int(mixed $value): int
{
    if (is_int($value)) {
        return max(0, $value);
    }
    if (is_string($value) && preg_match('/\A[0-9]+\z/', $value) === 1) {
        return (int) $value;
    }

    return 0;
}

function sr_admin_high_load_assessment(array $metrics): array
{
    $targetRecords = sr_admin_high_load_int($metrics['target_records'] ?? 0);
    $fileOperations = sr_admin_high_load_int($metrics['file_operations'] ?? 0);
    $externalOperations = sr_admin_high_load_int($metrics['external_operations'] ?? 0);
    $tableCount = sr_admin_high_load_int($metrics['table_count'] ?? 0);
    $longTransaction = !empty($metrics['long_transaction']);
    $rollbackLimited = !empty($metrics['rollback_limited']);
    $batchAvailable = !empty($metrics['batch_available']);

    $grade = 'caution';
    if (
        $targetRecords >= 1000
        || $fileOperations >= 1000
        || $externalOperations > 0
        || ($targetRecords >= 500 && ($fileOperations > 0 || $tableCount >= 3))
        || ($batchAvailable && ($targetRecords >= 500 || $fileOperations >= 100))
    ) {
        $grade = 'very_high';
    } elseif ($targetRecords >= 100 || $fileOperations >= 100 || $tableCount >= 3 || $longTransaction || $rollbackLimited) {
        $grade = 'high';
    }

    return [
        'grade' => $grade,
        'label' => sr_admin_high_load_grade_label($grade),
        'requires_confirmation' => $grade !== 'caution',
        'requires_batch_review' => $grade === 'very_high',
        'recommended_time' => $grade === 'caution'
            ? '일반 운영 시간에도 실행할 수 있지만 변경 직전 대상 수를 확인하세요.'
            : '방문자가 적은 시간에 실행하고, 가능하면 백업 또는 staging 검증 후 진행하세요.',
        'failure_state' => $batchAvailable
            ? '배치 작업은 처리된 항목을 유지하고 실패 상태와 다음 처리 위치를 작업 목록에 남깁니다.'
            : '단일 요청 작업은 완료된 변경만 남을 수 있으므로 실패 시 감사 로그와 화면 피드백을 확인하세요.',
    ];
}

function sr_admin_high_load_grade_label(string $grade): string
{
    if ($grade === 'very_high') {
        return '매우 높음';
    }
    if ($grade === 'high') {
        return '높음';
    }

    return '주의';
}
