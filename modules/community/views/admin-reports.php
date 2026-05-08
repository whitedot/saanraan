<?php

$adminPageTitle = '커뮤니티 신고';
include TOY_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo toy_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo toy_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<section>
    <h2>신고 목록</h2>
    <?php if ($reports === []) { ?>
        <p>접수된 신고가 없습니다.</p>
    <?php } else { ?>
        <table>
            <thead>
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
                    <th>처리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report) { ?>
                    <tr>
                        <td><?php echo toy_e((string) $report['id']); ?></td>
                        <td><?php echo toy_e((string) $report['target_type'] . ' #' . (string) $report['target_id']); ?></td>
                        <td><?php echo toy_e(toy_community_report_reason_label((string) $report['reason_key'])); ?></td>
                        <td><?php echo toy_e((string) $report['status']); ?></td>
                        <td><?php echo toy_e(toy_community_report_account_label(
                            is_string($report['reporter_display_name'] ?? null) ? $report['reporter_display_name'] : null,
                            (int) $report['reporter_account_id']
                        )); ?></td>
                        <td><?php echo toy_e(toy_community_report_account_label(
                            is_string($report['reported_display_name'] ?? null) ? $report['reported_display_name'] : null,
                            (int) $report['reported_account_id']
                        )); ?></td>
                        <td><?php echo toy_e((string) ($report['memo_text'] ?? '')); ?></td>
                        <td><?php echo toy_e((string) $report['created_at']); ?></td>
                        <td>
                            <?php if ((int) ($report['reviewer_account_id'] ?? 0) > 0) { ?>
                                <?php echo toy_e(toy_community_report_account_label(
                                    is_string($report['reviewer_display_name'] ?? null) ? $report['reviewer_display_name'] : null,
                                    (int) $report['reviewer_account_id']
                                )); ?>
                            <?php } ?>
                        </td>
                        <td><?php echo toy_e((string) ($report['reviewed_at'] ?? '')); ?></td>
                        <td>
                            <form method="post" action="<?php echo toy_e(toy_url('/admin/community/reports')); ?>">
                                <?php echo toy_csrf_field(); ?>
                                <input type="hidden" name="report_id" value="<?php echo toy_e((string) $report['id']); ?>">
                                <p>
                                    <label>상태<br>
                                        <select name="status">
                                            <?php foreach ($allowedStatuses as $status) { ?>
                                                <option value="<?php echo toy_e($status); ?>"<?php echo $status === (string) $report['status'] ? ' selected' : ''; ?>><?php echo toy_e($status); ?></option>
                                            <?php } ?>
                                        </select>
                                    </label>
                                </p>
                                <p>
                                    <label>처리 메모<br>
                                        <textarea name="review_note" rows="3" cols="30"><?php echo toy_e((string) ($report['review_note'] ?? '')); ?></textarea>
                                    </label>
                                </p>
                                <button type="submit">변경</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</section>

<?php include TOY_ROOT . '/modules/admin/views/layout-footer.php'; ?>
