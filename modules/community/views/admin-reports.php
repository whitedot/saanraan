<?php

$adminPageTitle = '커뮤니티 신고';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo sr_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo sr_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<section class="member-table-card admin-member-list-form">
    <div class="card-header"><h2 class="card-title">신고 목록</h2></div>
    <?php if ($reports === []) { ?>
        <p>접수된 신고가 없습니다.</p>
    <?php } else { ?>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>대상</th>
                    <th>사유</th>
                    <th>상태</th>
                    <th>신고자</th>
                    <th>대상 회원</th>
                    <th>메모</th>
                    <th>접수일</th>
                    <th>처리자</th>
                    <th>처리일</th>
                    <th class="text-end">처리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $report['id']); ?></td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $report['target_type']) . ' #' . (string) $report['target_id']); ?></td>
                        <td><?php echo sr_e(sr_community_report_reason_label((string) $report['reason_key'])); ?></td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $report['status'], 'report_status')); ?></td>
                        <td><?php echo sr_e(sr_community_report_account_label(
                            is_string($report['reporter_display_name'] ?? null) ? $report['reporter_display_name'] : null,
                            (int) $report['reporter_account_id']
                        )); ?></td>
                        <td><?php echo sr_e(sr_community_report_account_label(
                            is_string($report['reported_display_name'] ?? null) ? $report['reported_display_name'] : null,
                            (int) $report['reported_account_id']
                        )); ?></td>
                        <td><?php echo sr_e((string) ($report['memo_text'] ?? '')); ?></td>
                        <td><?php echo sr_e((string) $report['created_at']); ?></td>
                        <td>
                            <?php if ((int) ($report['reviewer_account_id'] ?? 0) > 0) { ?>
                                <?php echo sr_e(sr_community_report_account_label(
                                    is_string($report['reviewer_display_name'] ?? null) ? $report['reviewer_display_name'] : null,
                                    (int) $report['reviewer_account_id']
                                )); ?>
                            <?php } ?>
                        </td>
                        <td><?php echo sr_e((string) ($report['reviewed_at'] ?? '')); ?></td>
                        <td class="member-cell-manage">
                            <div class="member-manage">
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/community/reports')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="report_id" value="<?php echo sr_e((string) $report['id']); ?>">
                                <p>
                                    <label>상태<br>
                                        <select name="status">
                                            <?php foreach ($allowedStatuses as $status) { ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $status === (string) $report['status'] ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'report_status')); ?></option>
                                            <?php } ?>
                                        </select>
                                    </label>
                                </p>
                                <p>
                                    <label>처리 메모<br>
                                        <textarea name="review_note" rows="3" cols="30"><?php echo sr_e((string) ($report['review_note'] ?? '')); ?></textarea>
                                    </label>
                                </p>
                                <button type="submit" class="btn btn-sm btn-surface-default-soft">변경</button>
                            </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    <?php } ?>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
