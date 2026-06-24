<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/levels', 'edit');
sr_require_csrf();

$settings = sr_community_settings($pdo);
if (empty($settings['level_enabled'])) {
    sr_json_response([
        'ok' => false,
        'message' => sr_t('community::action.admin.level_recalculate_disabled'),
    ], 422, ['Cache-Control: no-store']);
}

$confirmationText = sr_post_string('recalculate_confirm_text', 40);
if (sr_post_string('recalculate_confirmed', 1) !== '1' || $confirmationText !== sr_t('community::ui.level_recalculate_confirmation_text')) {
    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => 'community.levels.recalculate_confirmation_failed',
        'target_type' => 'module',
        'target_id' => 'community',
        'result' => 'failure',
        'message' => 'Community level recalculation confirmation failed.',
        'metadata' => [
            'confirmation_checked' => false,
            'load_grade' => sr_admin_high_load_assessment([
                'target_records' => sr_community_recalculate_target_account_count($pdo),
                'table_count' => 4,
                'batch_available' => true,
            ])['grade'],
        ],
    ]);
    sr_json_response([
        'ok' => false,
        'message' => sr_t('community::action.admin.level_recalculate_confirmation_required'),
    ], 422, ['Cache-Control: no-store']);
}

$cursorInput = sr_post_string('cursor', 20);
$batchSizeInput = sr_post_string('batch_size', 20);
$processedTotalInput = sr_post_string('processed_total', 20);
$jobIdInput = sr_post_string('job_id', 20);
$lockToken = sr_post_string('lock_token', 80);
$cursor = preg_match('/\A[0-9]+\z/', $cursorInput) === 1 ? (int) $cursorInput : 0;
$batchSize = preg_match('/\A[1-9][0-9]*\z/', $batchSizeInput) === 1 ? (int) $batchSizeInput : 50;
$processedTotal = preg_match('/\A[0-9]+\z/', $processedTotalInput) === 1 ? (int) $processedTotalInput : 0;
$jobId = preg_match('/\A[0-9]+\z/', $jobIdInput) === 1 ? (int) $jobIdInput : 0;
$batchSize = max(1, min(100, $batchSize));
$processedTotal = max(0, min(1000000000, $processedTotal));

$total = sr_community_recalculate_target_account_count($pdo);
$loadAssessment = sr_admin_high_load_assessment([
    'target_records' => $total,
    'table_count' => 4,
    'batch_available' => true,
]);
$job = null;
try {
    if ($jobId > 0) {
        $job = sr_community_level_recalculate_job_by_id($pdo, $jobId);
        if (!is_array($job)) {
            throw new RuntimeException('레벨 재계산 작업을 찾을 수 없습니다.');
        }
        sr_community_level_recalculate_job_require_running($job, $lockToken);
        $cursor = (int) ($job['cursor_value'] ?? 0);
        $processedTotal = (int) ($job['processed_total'] ?? 0);
        $total = (int) ($job['total_count'] ?? $total);
        $batchSize = (int) ($job['batch_size'] ?? $batchSize);
        $batchSize = max(1, min(100, $batchSize));
    } else {
        $job = sr_community_level_recalculate_job_create($pdo, (int) $account['id'], $total, $batchSize);
        $jobId = (int) ($job['id'] ?? 0);
        $lockToken = (string) ($job['lock_token'] ?? '');
    }

    $summary = sr_community_recalculate_account_levels_batch($pdo, $cursor, $batchSize, $settings);
    $processed = (int) ($summary['accounts'] ?? 0);
    $processedTotal += $processed;
    $nextCursor = (int) ($summary['next_cursor'] ?? $cursor);
    $done = !empty($summary['done']);

    if ($done) {
        sr_community_level_recalculate_job_complete($pdo, $jobId, $lockToken, $nextCursor, $processedTotal, $total);
    } else {
        sr_community_level_recalculate_job_progress($pdo, $jobId, $lockToken, $nextCursor, $processedTotal, $total);
    }
} catch (Throwable $exception) {
    sr_community_level_recalculate_job_fail($pdo, $jobId, $lockToken, $exception);
    sr_log_exception($exception, 'community_level_recalculate_job_failed');
    sr_json_response([
        'ok' => false,
        'message' => $exception->getMessage(),
        'job_id' => $jobId,
    ], 500, ['Cache-Control: no-store']);
}

if ($done) {
    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => 'community.levels.recalculated',
        'target_type' => 'module',
        'target_id' => 'community',
        'result' => 'success',
        'message' => 'Community levels recalculated.',
        'metadata' => [
            'accounts' => $processedTotal,
            'total' => $total,
            'batch_size' => $batchSize,
            'next_cursor' => $nextCursor,
            'job_id' => $jobId,
            'failed_count' => 0,
            'batch' => true,
            'load_grade' => (string) $loadAssessment['grade'],
            'confirmation_checked' => true,
        ],
    ]);
}

sr_json_response([
    'ok' => true,
    'processed' => $processed,
    'processed_total' => $processedTotal,
    'total' => $total,
    'next_cursor' => $nextCursor,
    'job_id' => $jobId,
    'lock_token' => $lockToken,
    'done' => $done,
    'message' => $done
        ? sr_t('community::action.admin.levels_recalculated', ['accounts' => (string) $processedTotal])
        : sr_t('community::action.admin.levels_recalculating', [
            'processed' => (string) $processedTotal,
            'total' => (string) $total,
        ]),
], 200, ['Cache-Control: no-store']);
