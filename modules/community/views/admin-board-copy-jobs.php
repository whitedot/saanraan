<?php

$adminPageTitle = '게시판 배치 복사';
$adminPageSubtitle = '상한을 넘는 게시판 복사를 단계별로 처리합니다.';
$adminContainerClass = 'admin-community-board-copy-jobs admin-ui-scope';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if (is_array($job)) { ?>
    <?php
    $counts = sr_community_board_copy_job_json($job, 'counts_json');
    $options = sr_community_board_copy_job_json($job, 'options_json');
    $jobStatus = (string) ($job['status'] ?? '');
    $canRun = in_array($jobStatus, ['pending', 'running', 'cleanup_required'], true);
    $canRetry = in_array($jobStatus, ['failed', 'paused'], true);
    $canCancel = in_array($jobStatus, ['pending', 'failed', 'paused'], true);
    ?>
    <section class="admin-card card">
        <h2><?php echo sr_e('작업 #' . (string) (int) $job['id']); ?></h2>
        <dl class="admin-meta-list">
            <dt><?php echo sr_e('상태'); ?></dt>
            <dd><?php echo sr_e((string) $job['status'] . ' / ' . (string) $job['stage']); ?></dd>
            <dt><?php echo sr_e('원본 / 대상'); ?></dt>
            <dd><?php echo sr_e((string) (int) $job['source_board_id'] . ' -> ' . (string) (int) $job['target_board_id']); ?></dd>
            <dt><?php echo sr_e('새 게시판'); ?></dt>
            <dd><?php echo sr_e((string) ($options['title'] ?? '') . ' / ' . (string) ($options['board_key'] ?? '')); ?></dd>
            <dt><?php echo sr_e('복사 수'); ?></dt>
            <dd><?php echo sr_e('게시글 ' . number_format((int) ($counts['posts'] ?? 0)) . ', 댓글 ' . number_format((int) ($counts['comments'] ?? 0)) . ', 첨부 ' . number_format((int) ($counts['attachments'] ?? 0)) . ', 첨부 총량 ' . sr_community_format_bytes((int) ($counts['bytes'] ?? 0))); ?></dd>
            <?php if ((string) ($job['last_error'] ?? '') !== '') { ?>
                <dt><?php echo sr_e('마지막 오류'); ?></dt>
                <dd><?php echo sr_e((string) $job['last_error']); ?></dd>
            <?php } ?>
        </dl>
        <div class="admin-form-actions">
            <?php if ($canRun || $canRetry || $canCancel) { ?>
                <form method="post" action="<?php echo sr_e(sr_url('/admin/community/board-copy-jobs?id=' . rawurlencode((string) (int) $job['id']))); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="job_id" value="<?php echo sr_e((string) (int) $job['id']); ?>">
                    <?php if ($canRun) { ?>
                        <button type="submit" name="intent" value="run" class="btn btn-solid-primary"><?php echo sr_e($jobStatus === 'cleanup_required' ? '정리 다시 시도' : '다음 묶음 처리'); ?></button>
                    <?php } ?>
                    <?php if ($canRetry) { ?>
                        <button type="submit" name="intent" value="retry" class="btn btn-solid-light"><?php echo sr_e('재시도 준비'); ?></button>
                    <?php } ?>
                    <?php if ($canCancel) { ?>
                        <button type="submit" name="intent" value="cancel" class="btn btn-solid-danger" data-confirm="<?php echo sr_e('생성된 대상 게시판과 파일을 정리합니다. 계속할까요?'); ?>"><?php echo sr_e('취소 및 정리'); ?></button>
                    <?php } ?>
                </form>
            <?php } ?>
            <?php if ((int) ($job['target_board_id'] ?? 0) > 0) { ?>
                <a href="<?php echo sr_e(sr_url('/admin/community/boards/edit?id=' . rawurlencode((string) (int) $job['target_board_id']))); ?>" class="btn btn-solid-light"><?php echo sr_e('대상 게시판 열기'); ?></a>
            <?php } ?>
        </div>
    </section>
<?php } ?>

<section class="admin-card card">
    <h2><?php echo sr_e('최근 작업'); ?></h2>
    <?php if ($jobs === []) { ?>
        <p class="admin-empty-state"><?php echo sr_e('배치 복사 작업이 없습니다.'); ?></p>
    <?php } else { ?>
        <div class="admin-table-responsive">
            <table class="admin-table">
                <thead>
                <tr>
                    <th><?php echo sr_e('작업'); ?></th>
                    <th><?php echo sr_e('원본'); ?></th>
                    <th><?php echo sr_e('대상'); ?></th>
                    <th><?php echo sr_e('상태'); ?></th>
                    <th><?php echo sr_e('생성일'); ?></th>
                    <th><?php echo sr_e('관리'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($jobs as $row) { ?>
                    <tr>
                        <td><?php echo sr_e('#' . (string) (int) $row['id']); ?></td>
                        <td><?php echo sr_e((string) ($row['source_title'] ?? '') . ' #' . (string) (int) $row['source_board_id']); ?></td>
                        <td><?php echo sr_e((string) ($row['target_title'] ?? '') . ' #' . (string) (int) $row['target_board_id']); ?></td>
                        <td><?php echo sr_e((string) $row['status'] . ' / ' . (string) $row['stage']); ?></td>
                        <td><?php echo sr_community_time_html((string) $row['created_at']); ?></td>
                        <td><a href="<?php echo sr_e(sr_url('/admin/community/board-copy-jobs?id=' . rawurlencode((string) (int) $row['id']))); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e('열기'); ?></a></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
