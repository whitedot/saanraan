<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/boards', 'edit');

$jobId = (int) sr_get_string('id', 20);
$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $jobId = (int) sr_post_string('job_id', 20);
    $intent = sr_post_string('intent', 30);
    try {
        $targetJob = sr_community_board_copy_job_by_id($pdo, $jobId);
        if (!is_array($targetJob)) {
            throw new RuntimeException('복사 작업을 찾을 수 없습니다.');
        }
        $status = (string) ($targetJob['status'] ?? '');
        if ($intent === 'run') {
            if (!in_array($status, ['pending', 'running', 'cleanup_required'], true)) {
                throw new RuntimeException('현재 상태에서는 다음 묶음 처리를 실행할 수 없습니다.');
            }
            $result = sr_community_board_copy_job_run($pdo, $jobId, (int) $account['id']);
            $notice = (string) ($result['message'] ?? '복사 작업을 처리했습니다.');
        } elseif ($intent === 'cancel') {
            if (!in_array($status, ['pending', 'failed', 'paused'], true)) {
                throw new RuntimeException('현재 상태에서는 취소 및 정리를 실행할 수 없습니다.');
            }
            $pdo->prepare("UPDATE sr_community_board_copy_jobs SET status = 'cleanup_required', stage = 'cleanup', updated_at = :updated_at WHERE id = :id AND status NOT IN ('completed', 'cancelled')")
                ->execute(['updated_at' => sr_now(), 'id' => $jobId]);
            $result = sr_community_board_copy_job_run($pdo, $jobId, (int) $account['id']);
            $notice = (string) ($result['message'] ?? '복사 작업을 정리했습니다.');
        } elseif ($intent === 'retry') {
            if (!in_array($status, ['failed', 'paused'], true)) {
                throw new RuntimeException('현재 상태에서는 재시도 준비를 실행할 수 없습니다.');
            }
            $pdo->prepare("UPDATE sr_community_board_copy_jobs SET status = 'pending', lock_token = '', locked_at = NULL, updated_at = :updated_at WHERE id = :id AND status IN ('failed', 'paused')")
                ->execute(['updated_at' => sr_now(), 'id' => $jobId]);
            $pdo->prepare("UPDATE sr_community_board_copy_job_maps SET status = 'pending', updated_at = :updated_at WHERE job_id = :job_id AND status = 'failed'")
                ->execute(['updated_at' => sr_now(), 'job_id' => $jobId]);
            $notice = '복사 작업을 재시도할 수 있습니다.';
        } else {
            throw new RuntimeException('지원하지 않는 작업입니다.');
        }
    } catch (Throwable $exception) {
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'community_board_copy_job_failed');
        }
        $errors[] = $exception->getMessage();
    }
}

$job = $jobId > 0 ? sr_community_board_copy_job_by_id($pdo, $jobId) : null;
$jobs = sr_community_board_copy_jobs_recent($pdo);

include SR_ROOT . '/modules/community/views/admin-board-copy-jobs.php';
