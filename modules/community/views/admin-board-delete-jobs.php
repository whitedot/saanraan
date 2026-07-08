<?php

$adminPageTitle = '게시판 삭제 작업 관리';
if (is_array($job ?? null)) {
    $adminPageTitle = sr_community_board_delete_job_stage_progress_label((string) ($job['stage'] ?? 'prepare')) . ' - 게시판 삭제 작업 관리';
}
$adminPageSubtitle = '큰 게시판 삭제는 요청당 처리량을 제한해 이어서 실행합니다.';
$adminContainerClass = 'admin-community-board-delete-jobs admin-ui-scope';
$adminPageTitleActionsHtml = '<a href="' . sr_e(sr_url('/admin/community/boards')) . '" class="btn btn-ghost-secondary">'
    . sr_e('게시판 목록')
    . '</a>';
$communityBoardDeleteJobStatusClass = static function (string $status): string {
    return match ($status) {
        'completed' => 'is-normal',
        'pending', 'running' => 'is-warning',
        'failed', 'cleanup_required' => 'is-danger',
        default => 'is-blocked',
    };
};
$communityBoardDeleteJobs = is_array($jobs ?? null) ? $jobs : [];
$communityBoardDeleteJobStatusCounts = [];
foreach ($communityBoardDeleteJobs as $communityBoardDeleteJobRow) {
    if (!is_array($communityBoardDeleteJobRow)) {
        continue;
    }
    $communityBoardDeleteJobRowStatus = (string) ($communityBoardDeleteJobRow['status'] ?? '');
    $communityBoardDeleteJobStatusCounts[$communityBoardDeleteJobRowStatus] = (int) ($communityBoardDeleteJobStatusCounts[$communityBoardDeleteJobRowStatus] ?? 0) + 1;
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if (is_array($job)) { ?>
    <?php
    $counts = sr_community_board_delete_job_json($job, 'counts_json');
    $processed = sr_community_board_delete_job_json($job, 'processed_json');
    $snapshot = sr_community_board_delete_job_json($job, 'board_snapshot_json');
    $stageProgressLabel = sr_community_board_delete_job_stage_progress_label((string) ($job['stage'] ?? 'prepare'));
    $jobStatus = (string) ($job['status'] ?? '');
    $canRun = in_array($jobStatus, ['pending', 'running', 'cleanup_required'], true);
    $canRetry = $jobStatus === 'failed';
    $mapStatusCounts = is_array($jobMapStatusCounts ?? null) ? $jobMapStatusCounts : [];
    $failedMaps = is_array($jobFailedMaps ?? null) ? $jobFailedMaps : [];
    $mapEntityLabels = [
        'attachment_file' => '첨부 파일',
    ];
    ?>
    <section class="card admin-form ui-form-theme admin-community-board-copy-job-detail">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e('작업 내용'); ?></h2>
            <div class="badge-list admin-community-board-copy-job-heading-badges">
                <span class="badge badge-soft-secondary"><?php echo sr_e('작업 #' . (string) (int) $job['id']); ?></span>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('상태'); ?></span>
            <div class="form-field">
                <p class="admin-form-static"><span class="admin-status <?php echo sr_e($communityBoardDeleteJobStatusClass($jobStatus)); ?>"><?php echo sr_e(sr_community_board_delete_job_status_label($jobStatus)); ?></span></p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('현재 단계'); ?></span>
            <div class="form-field">
                <p class="admin-form-static"><?php echo sr_e($stageProgressLabel); ?></p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('게시판'); ?></span>
            <div class="form-field">
                <p class="admin-form-static"><?php echo sr_e((string) ($snapshot['title'] ?? '') . ' (' . (string) ($snapshot['board_key'] ?? '') . ') #' . (string) (int) $job['board_id']); ?></p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('삭제 대상'); ?></span>
            <div class="form-field">
                <p class="admin-form-static"><?php echo sr_e('게시글 ' . number_format((int) ($counts['posts'] ?? 0)) . ', 댓글 ' . number_format((int) ($counts['comments'] ?? 0)) . ', 첨부 ' . number_format((int) ($counts['attachments'] ?? 0)) . ', 시리즈 ' . number_format((int) ($counts['series'] ?? 0))); ?></p>
            </div>
        </div>
        <?php if ($processed !== []) { ?>
            <div class="form-row">
                <span class="form-label"><?php echo sr_e('처리 현황'); ?></span>
                <div class="form-field">
                    <p class="admin-form-static">
                        <?php echo sr_e(implode(', ', array_map(static function (string $stageKey, int $count): string {
                            return sr_community_board_delete_job_stage_label($stageKey) . ' ' . number_format($count);
                        }, array_keys($processed), array_map('intval', array_values($processed))))); ?>
                    </p>
                </div>
            </div>
        <?php } ?>
        <?php if ((string) ($job['last_error'] ?? '') !== '') { ?>
            <div class="form-row">
                <span class="form-label"><?php echo sr_e('마지막 오류'); ?></span>
                <div class="form-field">
                    <p class="admin-form-static"><?php echo sr_e((string) $job['last_error']); ?></p>
                </div>
            </div>
        <?php } ?>
    </section>

    <?php if ($mapStatusCounts !== []) { ?>
        <section class="card admin-list-card admin-list-form">
            <div class="card-header">
                <h2 class="card-title"><?php echo sr_e('저장소 정리 현황'); ?></h2>
            </div>
            <div class="table-wrapper">
                <table class="table table-list">
                    <caption class="sr-only"><?php echo sr_e('게시판 삭제 저장소 정리 현황'); ?></caption>
                    <thead>
                    <tr>
                        <th><?php echo sr_e('대상'); ?></th>
                        <th><?php echo sr_e('대기'); ?></th>
                        <th><?php echo sr_e('정리됨'); ?></th>
                        <th><?php echo sr_e('실패'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($mapStatusCounts as $entityType => $statusCounts) { ?>
                        <tr>
                            <td class="admin-table-nowrap"><?php echo sr_e($mapEntityLabels[(string) $entityType] ?? (string) $entityType); ?></td>
                            <?php foreach (['pending', 'cleaned', 'failed'] as $statusKey) { ?>
                                <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($statusCounts[$statusKey] ?? 0))); ?></td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php } ?>

    <?php if ($failedMaps !== []) { ?>
        <section class="card admin-list-card admin-list-form">
            <div class="card-header">
                <h2 class="card-title"><?php echo sr_e('실패 항목'); ?></h2>
            </div>
            <div class="table-wrapper">
                <table class="table table-list">
                    <caption class="sr-only"><?php echo sr_e('게시판 삭제 실패 항목'); ?></caption>
                    <thead>
                    <tr>
                        <th><?php echo sr_e('대상'); ?></th>
                        <th><?php echo sr_e('원본 ID'); ?></th>
                        <th><?php echo sr_e('저장소'); ?></th>
                        <th><?php echo sr_e('오류'); ?></th>
                        <th><?php echo sr_e('갱신일'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($failedMaps as $failedMap) { ?>
                        <tr>
                            <td class="admin-table-nowrap"><?php echo sr_e($mapEntityLabels[(string) ($failedMap['entity_type'] ?? '')] ?? (string) ($failedMap['entity_type'] ?? '')); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) (int) ($failedMap['source_id'] ?? 0)); ?></td>
                            <td class="admin-table-break"><code><?php echo sr_e((string) ($failedMap['storage_driver'] ?? 'local') . ':' . (string) ($failedMap['storage_key'] ?? '')); ?></code></td>
                            <td class="admin-table-break"><?php echo sr_e((string) ($failedMap['error_text'] ?? '')); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_community_time_html((string) ($failedMap['updated_at'] ?? '')); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php } ?>

    <?php if ($canRun || $canRetry) { ?>
        <div class="form-sticky-actions form-actions form-actions-split admin-community-board-copy-job-actions">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/community/board-delete-jobs?id=' . rawurlencode((string) (int) $job['id']))); ?>" class="admin-community-board-copy-job-action-form">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/community/board-delete-jobs?id=' . (string) (int) $job['id'])); ?>">
                <input type="hidden" name="job_id" value="<?php echo sr_e((string) (int) $job['id']); ?>">
                <div class="admin-community-board-copy-job-action-left">
                    <a href="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="btn btn-solid-light"><?php echo sr_e('게시판 목록'); ?></a>
                </div>
                <div class="admin-community-board-copy-job-action-right">
                    <?php if ($canRetry) { ?>
                        <button type="submit" name="intent" value="retry" class="btn btn-solid-light" data-confirm="<?php echo sr_e('실패한 삭제 작업을 다시 대기 상태로 돌립니다. 이미 삭제된 항목은 복구되지 않습니다. 계속할까요?'); ?>"><?php echo sr_e('재시도 준비'); ?></button>
                    <?php } ?>
                    <?php if ($canRun) { ?>
                        <button type="submit" name="intent" value="run" class="btn btn-solid-primary"><?php echo sr_e($jobStatus === 'cleanup_required' ? '정리 다시 시도' : '다음 단계'); ?></button>
                    <?php } ?>
                </div>
            </form>
        </div>
    <?php } ?>
<?php } else { ?>
<section class="card admin-list-card admin-list-form admin-community-board-copy-job-list">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e('최근 삭제 작업'); ?></h2>
    </div>
    <div class="admin-list-summary-row admin-community-board-copy-job-summary-row">
        <div class="badge-list">
            <span class="badge badge-soft-secondary"><?php echo sr_e('최근 ' . number_format(count($communityBoardDeleteJobs)) . '건'); ?></span>
            <?php foreach (sr_community_board_delete_job_statuses() as $summaryStatus) { ?>
                <?php if ((int) ($communityBoardDeleteJobStatusCounts[$summaryStatus] ?? 0) < 1) { ?>
                    <?php continue; ?>
                <?php } ?>
                <span class="badge badge-soft-secondary"><?php echo sr_e(sr_community_board_delete_job_status_label($summaryStatus) . ' ' . number_format((int) $communityBoardDeleteJobStatusCounts[$summaryStatus]) . '건'); ?></span>
            <?php } ?>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-community-board-copy-job-table">
            <caption class="sr-only"><?php echo sr_e('게시판 삭제 작업 관리 목록'); ?></caption>
            <thead>
            <tr>
                <th><?php echo sr_e('작업'); ?></th>
                <th><?php echo sr_e('상태'); ?></th>
                <th><?php echo sr_e('단계'); ?></th>
                <th><?php echo sr_e('게시판'); ?></th>
                <th><?php echo sr_e('생성일'); ?></th>
                <th class="text-end"><?php echo sr_e('관리'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($communityBoardDeleteJobs === []) { ?>
                <tr>
                    <td colspan="6" class="admin-empty-state"><?php echo sr_e('삭제 작업이 없습니다.'); ?></td>
                </tr>
            <?php } ?>
            <?php foreach ($communityBoardDeleteJobs as $row) { ?>
                <?php
                $rowJobId = (int) $row['id'];
                $rowStatus = (string) ($row['status'] ?? '');
                $rowStage = (string) ($row['stage'] ?? 'prepare');
                $rowOpenLabel = $rowStatus === 'completed' ? '확인' : '계속하기';
                $rowSnapshot = sr_community_board_delete_job_json($row, 'board_snapshot_json');
                $rowBoardLabel = trim((string) ($rowSnapshot['title'] ?? '') . ' (' . (string) ($rowSnapshot['board_key'] ?? '') . ')');
                if ($rowBoardLabel === '()') {
                    $rowBoardLabel = '게시판 #' . (string) (int) ($row['board_id'] ?? 0);
                }
                ?>
                <tr>
                    <td class="admin-table-nowrap"><?php echo sr_e('#' . (string) $rowJobId); ?></td>
                    <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($communityBoardDeleteJobStatusClass($rowStatus)); ?>"><?php echo sr_e(sr_community_board_delete_job_status_label($rowStatus)); ?></span></td>
                    <td class="admin-table-nowrap"><?php echo sr_e(sr_community_board_delete_job_stage_progress_label($rowStage)); ?></td>
                    <td class="admin-table-break"><?php echo sr_e($rowBoardLabel); ?></td>
                    <td class="admin-table-nowrap"><?php echo sr_community_time_html((string) $row['created_at']); ?></td>
                    <td class="admin-table-actions-cell">
                        <div class="admin-row-actions">
                            <a href="<?php echo sr_e(sr_url('/admin/community/board-delete-jobs?id=' . rawurlencode((string) $rowJobId))); ?>" class="btn btn-sm btn-outline-secondary" aria-label="<?php echo sr_e('삭제 작업 #' . (string) $rowJobId . ' ' . $rowOpenLabel); ?>"><?php echo sr_e($rowOpenLabel); ?></a>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php echo sr_admin_status_description_list_html('community_board_delete_job_status', array_combine(sr_community_board_delete_job_statuses(), array_map('sr_community_board_delete_job_status_label', sr_community_board_delete_job_statuses())) ?: [], [], '삭제 작업 상태 설명'); ?>
</section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
