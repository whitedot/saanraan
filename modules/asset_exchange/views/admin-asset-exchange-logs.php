<?php

$adminPageTitle = '자산 환전 로그';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<section class="admin-card admin-list-card card">
    <div class="admin-list-summary-row">
        <?php echo sr_admin_pagination_summary_html($pagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>처리일</th>
                    <th>회원</th>
                    <th>환전 묶음 ID</th>
                    <th>자산</th>
                    <th>출금</th>
                    <th>입금</th>
                    <th>수수료</th>
                    <th>상태</th>
                    <th>실패 사유</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs === []) { ?>
                    <tr><td colspan="9" class="admin-empty-state">환전 로그가 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($logs as $log) { ?>
                    <?php $failureReason = trim((string) ($log['failure_reason'] ?? '')); ?>
                    <tr>
                        <td><?php echo sr_e((string) $log['created_at']); ?></td>
                        <td><?php echo sr_e(sr_admin_member_display_name_preview($log)); ?><br><?php echo sr_e(sr_admin_member_email_display($log)); ?></td>
                        <td><?php echo sr_e((string) $log['exchange_group_id']); ?></td>
                        <td><?php echo sr_e(sr_asset_exchange_asset_label($assets, (string) $log['from_module_key']) . ' -> ' . sr_asset_exchange_asset_label($assets, (string) $log['to_module_key'])); ?></td>
                        <td><?php echo sr_e(number_format((int) $log['request_amount'])); ?></td>
                        <td><?php echo sr_e(number_format((int) $log['deposit_amount'])); ?></td>
                        <td><?php echo sr_e(number_format((int) $log['fee_amount'])); ?></td>
                        <td><?php echo sr_e((string) $log['status']); ?></td>
                        <td><?php echo sr_e($failureReason !== '' ? $failureReason : '-'); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php echo sr_admin_pagination_html($pagination, '자산 환전 로그 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
