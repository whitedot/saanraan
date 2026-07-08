<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/boards', 'delete');

$jobId = (int) sr_get_string('id', 20);
$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $errors = [];
    $notice = '';
    $jobId = (int) sr_post_string('job_id', 20);
    $intent = sr_post_string('intent', 30);
    try {
        $targetJob = sr_community_board_delete_job_by_id($pdo, $jobId);
        if (!is_array($targetJob)) {
            throw new RuntimeException('삭제 작업을 찾을 수 없습니다.');
        }
        $status = (string) ($targetJob['status'] ?? '');
        if ($intent === 'run') {
            if (!in_array($status, ['pending', 'running', 'cleanup_required'], true)) {
                throw new RuntimeException('현재 상태에서는 다음 단계를 실행할 수 없습니다.');
            }
            $result = sr_community_board_delete_job_run($pdo, $jobId, (int) $account['id']);
            $notice = (string) ($result['message'] ?? '삭제 작업을 처리했습니다.');
            if (!empty($result['done']) && (string) ($result['status'] ?? '') === 'completed') {
                $snapshot = sr_community_board_delete_job_json($targetJob, 'board_snapshot_json');
                $counts = sr_community_board_delete_job_json($targetJob, 'counts_json');
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'community.board.deleted',
                    'target_type' => 'community_board',
                    'target_id' => (string) (int) ($targetJob['board_id'] ?? 0),
                    'result' => 'success',
                    'message' => 'Community board deleted by delete job.',
                    'metadata' => [
                        'board_key' => (string) ($snapshot['board_key'] ?? ''),
                        'title' => (string) ($snapshot['title'] ?? ''),
                        'delete_job_id' => $jobId,
                        'counts' => $counts,
                        'batch' => true,
                        'confirmation_checked' => true,
                    ],
                ]);
            }
        } elseif ($intent === 'retry') {
            if ($status !== 'failed') {
                throw new RuntimeException('현재 상태에서는 재시도 준비를 실행할 수 없습니다.');
            }
            $pdo->prepare("UPDATE sr_community_board_delete_jobs SET status = 'pending', lock_token = '', locked_at = NULL, updated_at = :updated_at WHERE id = :id AND status = 'failed'")
                ->execute(['updated_at' => sr_now(), 'id' => $jobId]);
            $notice = '삭제 작업을 재시도할 수 있습니다.';
        } else {
            throw new RuntimeException('지원하지 않는 작업입니다.');
        }
    } catch (Throwable $exception) {
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'community_board_delete_job_failed');
        }
        $errors[] = $exception->getMessage();
    }
    sr_admin_flash_result(sr_admin_action_result($errors, $notice));
    sr_redirect(sr_admin_post_return_url('/admin/community/board-delete-jobs' . ($jobId > 0 ? '?id=' . (string) $jobId : '')));
}

$job = $jobId > 0 ? sr_community_board_delete_job_by_id($pdo, $jobId) : null;
$jobMapStatusCounts = is_array($job) ? sr_community_board_delete_job_map_status_counts($pdo, (int) $job['id']) : [];
$jobFailedMaps = is_array($job) ? sr_community_board_delete_job_failed_maps($pdo, (int) $job['id'], 10) : [];
$jobs = sr_community_board_delete_jobs_recent($pdo);

include SR_ROOT . '/modules/community/views/admin-board-delete-jobs.php';
