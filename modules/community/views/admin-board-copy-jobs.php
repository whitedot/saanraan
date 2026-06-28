<?php

$adminPageTitle = '게시판 작업 관리';
if (is_array($job ?? null)) {
    $adminPageTitle = sr_community_board_copy_job_stage_progress_label((string) ($job['stage'] ?? 'prepare')) . ' - 게시판 작업 관리';
}
$adminPageSubtitle = '대량 데이터는 배치로 이어서 실행됩니다.';
$adminContainerClass = 'admin-community-board-copy-jobs admin-ui-scope';
$adminPageTitleActionsHtml = '<a href="' . sr_e(sr_url('/admin/community/boards')) . '" class="btn btn-ghost-secondary">'
    . sr_e('게시판 목록')
    . '</a>';
$communityBoardCopyJobStatusClass = static function (string $status): string {
    return match ($status) {
        'completed' => 'is-normal',
        'pending', 'running', 'cleaning' => 'is-warning',
        'failed', 'cleanup_required' => 'is-danger',
        'paused' => 'is-blocked',
        'cancelled' => 'is-left',
        default => 'is-blocked',
    };
};
$communityBoardCopyJobs = is_array($jobs ?? null) ? $jobs : [];
$communityBoardCopyJobStatusCounts = [];
foreach ($communityBoardCopyJobs as $communityBoardCopyJobRow) {
    if (!is_array($communityBoardCopyJobRow)) {
        continue;
    }
    $communityBoardCopyJobRowStatus = (string) ($communityBoardCopyJobRow['status'] ?? '');
    $communityBoardCopyJobStatusCounts[$communityBoardCopyJobRowStatus] = (int) ($communityBoardCopyJobStatusCounts[$communityBoardCopyJobRowStatus] ?? 0) + 1;
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if (is_array($job)) { ?>
    <?php
    $counts = sr_community_board_copy_job_json($job, 'counts_json');
    $options = sr_community_board_copy_job_json($job, 'options_json');
    $scopeLabels = [];
    if (array_key_exists('copy_scope', $options) || array_key_exists('mode', $options)) {
        $scopeLabels = sr_community_board_copy_scope_labels_for_values($options);
    }
    if ($scopeLabels === []) {
        $scopeLabels = ['게시글+댓글', '첨부파일'];
        if (!empty($options['copy_series'])) {
            $scopeLabels[] = '시리즈';
        }
    }
    $stageProgressLabel = sr_community_board_copy_job_stage_progress_label((string) ($job['stage'] ?? 'prepare'));
    $jobStatus = (string) ($job['status'] ?? '');
    $canRun = in_array($jobStatus, ['pending', 'running', 'cleanup_required'], true);
    $canRetry = in_array($jobStatus, ['failed', 'paused'], true);
    $canCancel = in_array($jobStatus, ['pending', 'failed', 'paused'], true);
    $hasTargetBoard = (int) ($job['target_board_id'] ?? 0) > 0;
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
                <p class="admin-form-static"><span class="admin-status <?php echo sr_e($communityBoardCopyJobStatusClass($jobStatus)); ?>"><?php echo sr_e(sr_community_board_copy_job_status_label($jobStatus)); ?></span></p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('현재 단계'); ?></span>
            <div class="form-field">
                <p class="admin-form-static"><?php echo sr_e($stageProgressLabel); ?></p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('원본 / 대상'); ?></span>
            <div class="form-field">
                <p class="admin-form-static"><?php echo sr_e((string) (int) $job['source_board_id'] . ' -> ' . (string) (int) $job['target_board_id']); ?></p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('새 게시판'); ?></span>
            <div class="form-field">
                <p class="admin-form-static"><?php echo sr_e((string) ($options['title'] ?? '') . ' (' . (string) ($options['board_key'] ?? '') . ')'); ?></p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('복사 범위'); ?></span>
            <div class="form-field">
                <p class="admin-form-static"><?php echo sr_e(implode(', ', $scopeLabels)); ?></p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('복사 수'); ?></span>
            <div class="form-field">
                <p class="admin-form-static"><?php echo sr_e('게시글 ' . number_format((int) ($counts['posts'] ?? 0)) . ', 댓글 ' . number_format((int) ($counts['comments'] ?? 0)) . ', 첨부 ' . number_format((int) ($counts['attachments'] ?? 0)) . ', 시리즈 ' . number_format((int) ($counts['series'] ?? 0)) . ', 첨부 총량 ' . sr_community_format_bytes((int) ($counts['bytes'] ?? 0))); ?></p>
            </div>
        </div>
        <?php if ((string) ($job['last_error'] ?? '') !== '') { ?>
            <div class="form-row">
                <span class="form-label"><?php echo sr_e('마지막 오류'); ?></span>
                <div class="form-field">
                    <p class="admin-form-static"><?php echo sr_e((string) $job['last_error']); ?></p>
                </div>
            </div>
        <?php } ?>
    </section>
    <?php if ($canRun || $canRetry || $canCancel || $hasTargetBoard) { ?>
        <div class="form-sticky-actions form-actions form-actions-split admin-community-board-copy-job-actions">
            <?php if ($canRun || $canRetry || $canCancel) { ?>
            <form method="post" action="<?php echo sr_e(sr_url('/admin/community/board-copy-jobs?id=' . rawurlencode((string) (int) $job['id']))); ?>" class="admin-community-board-copy-job-action-form">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/community/board-copy-jobs?id=' . (string) (int) $job['id'])); ?>">
                <input type="hidden" name="job_id" value="<?php echo sr_e((string) (int) $job['id']); ?>">
                    <div class="admin-community-board-copy-job-action-left">
                        <?php if ($canCancel) { ?>
                        <button type="submit" name="intent" value="cancel" class="btn btn-outline-danger" data-confirm="<?php echo sr_e('생성된 대상 게시판과 파일을 정리합니다. 계속할까요?'); ?>"><?php echo sr_e('취소 및 정리'); ?></button>
                        <?php } ?>
                    <?php if ($hasTargetBoard) { ?>
                        <a href="<?php echo sr_e(sr_url('/admin/community/boards/edit?id=' . rawurlencode((string) (int) $job['target_board_id']))); ?>" class="btn btn-solid-light"><?php echo sr_e('대상 게시판 열기'); ?></a>
                    <?php } ?>
                </div>
                <div class="admin-community-board-copy-job-action-right">
                    <?php if ($canRetry) { ?>
                        <button type="submit" name="intent" value="retry" class="btn btn-solid-light"><?php echo sr_e('재시도 준비'); ?></button>
                    <?php } ?>
                    <?php if ($canRun) { ?>
                        <button type="submit" name="intent" value="run" class="btn btn-solid-primary"><?php echo sr_e($jobStatus === 'cleanup_required' ? '정리 다시 시도' : '다음 단계'); ?></button>
                    <?php } ?>
                </div>
            </form>
            <?php } else { ?>
                <div class="admin-community-board-copy-job-action-left">
                    <a href="<?php echo sr_e(sr_url('/admin/community/boards/edit?id=' . rawurlencode((string) (int) $job['target_board_id']))); ?>" class="btn btn-solid-light"><?php echo sr_e('대상 게시판 열기'); ?></a>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
<?php } else { ?>
<section class="card admin-list-card admin-list-form admin-community-board-copy-job-list">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e('최근 작업'); ?></h2>
    </div>
    <div class="admin-list-summary-row admin-community-board-copy-job-summary-row">
        <div class="badge-list">
            <span class="badge badge-soft-secondary"><?php echo sr_e('최근 ' . number_format(count($communityBoardCopyJobs)) . '건'); ?></span>
            <?php foreach (sr_community_board_copy_job_statuses() as $summaryStatus) { ?>
                <?php if ((int) ($communityBoardCopyJobStatusCounts[$summaryStatus] ?? 0) < 1) { ?>
                    <?php continue; ?>
                <?php } ?>
                <span class="badge badge-soft-secondary"><?php echo sr_e(sr_community_board_copy_job_status_label($summaryStatus) . ' ' . number_format((int) $communityBoardCopyJobStatusCounts[$summaryStatus]) . '건'); ?></span>
            <?php } ?>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-community-board-copy-job-table">
            <caption class="sr-only"><?php echo sr_e('게시판 작업 관리 목록'); ?></caption>
            <thead>
            <tr>
                <th><?php echo sr_e('작업'); ?></th>
                <th><?php echo sr_e('상태'); ?></th>
                <th><?php echo sr_e('단계'); ?></th>
                <th><?php echo sr_e('원본'); ?></th>
                <th><?php echo sr_e('대상'); ?></th>
                <th><?php echo sr_e('생성일'); ?></th>
                <th class="text-end"><?php echo sr_e('관리'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($communityBoardCopyJobs === []) { ?>
                <tr>
                    <td colspan="7" class="admin-empty-state"><?php echo sr_e('배치 복사 작업이 없습니다.'); ?></td>
                </tr>
            <?php } ?>
            <?php foreach ($communityBoardCopyJobs as $row) { ?>
                <?php
                $rowJobId = (int) $row['id'];
                $rowStatus = (string) ($row['status'] ?? '');
                $rowStage = (string) ($row['stage'] ?? 'prepare');
                $rowOpenLabel = in_array($rowStatus, ['completed', 'cancelled'], true) ? '확인' : '계속하기';
                $rowCanCancel = in_array($rowStatus, ['pending', 'failed', 'paused'], true);
                ?>
                <tr>
                    <td class="admin-table-nowrap"><?php echo sr_e('#' . (string) $rowJobId); ?></td>
                    <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($communityBoardCopyJobStatusClass($rowStatus)); ?>"><?php echo sr_e(sr_community_board_copy_job_status_label($rowStatus)); ?></span></td>
                    <td class="admin-table-nowrap"><?php echo sr_e(sr_community_board_copy_job_stage_progress_label($rowStage)); ?></td>
                    <td class="admin-table-break"><?php echo sr_e((string) ($row['source_title'] ?? '') . ' #' . (string) (int) $row['source_board_id']); ?></td>
                    <td class="admin-table-break"><?php echo sr_e((string) ($row['target_title'] ?? '') . ' #' . (string) (int) $row['target_board_id']); ?></td>
                    <td class="admin-table-nowrap"><?php echo sr_community_time_html((string) $row['created_at']); ?></td>
                    <td class="admin-table-actions-cell">
                        <div class="admin-row-actions">
                            <a href="<?php echo sr_e(sr_url('/admin/community/board-copy-jobs?id=' . rawurlencode((string) $rowJobId))); ?>" class="btn btn-sm btn-outline-secondary" aria-label="<?php echo sr_e('작업 #' . (string) $rowJobId . ' ' . $rowOpenLabel); ?>"><?php echo sr_e($rowOpenLabel); ?></a>
                            <?php if ($rowCanCancel) { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/community/board-copy-jobs?id=' . rawurlencode((string) $rowJobId))); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/community/board-copy-jobs')); ?>">
                                    <input type="hidden" name="job_id" value="<?php echo sr_e((string) $rowJobId); ?>">
                                    <button type="submit" name="intent" value="cancel" class="btn btn-sm btn-outline-danger" data-confirm="<?php echo sr_e('생성된 대상 게시판과 파일을 정리합니다. 계속할까요?'); ?>"><?php echo sr_e('취소'); ?></button>
                                </form>
                            <?php } ?>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php echo sr_admin_status_description_list_html('community_board_copy_job_status', array_combine(sr_community_board_copy_job_statuses(), array_map('sr_community_board_copy_job_status_label', sr_community_board_copy_job_statuses())) ?: [], [], '복사 작업 상태 설명'); ?>
</section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
