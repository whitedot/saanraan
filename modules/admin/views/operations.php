<?php

$adminPageTitle = '운영 상태';
$adminPageSubtitle = [
    '지연되었거나 실패한 항목은 해당 관리 화면에서 처리해 주세요.',
];
include SR_ROOT . '/modules/admin/views/layout-header.php';

$statusClass = static function (string $status): string {
    return match ($status) {
        'ok' => 'is-normal',
        'warning' => 'is-warning',
        'overdue', 'error' => 'is-danger',
        'skipped' => 'is-blocked',
        default => 'is-danger',
    };
};
?>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">지연/실패 신호</h2>
    </div>
    <div class="admin-list-summary-row admin-operations-summary-row">
        <div class="badge-list">
            <span class="badge badge-soft-danger">지연 초과 <?php echo sr_e((string) (int) ($operationStatusSummary['overdue'] ?? 0)); ?>개</span>
            <span class="badge badge-soft-warning">확인 필요 <?php echo sr_e((string) (int) ($operationStatusSummary['warning'] ?? 0)); ?>개</span>
            <span class="badge badge-soft-secondary">점검 대상 <?php echo sr_e((string) (int) ($operationStatusSummary['total_count'] ?? 0)); ?>건</span>
            <span class="badge badge-soft-secondary">마지막 확인 <?php echo sr_admin_time_html($operationStatusCheckedAt); ?></span>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-operations-table">
            <caption class="sr-only">지연/실패 신호 목록</caption>
            <thead>
                <tr>
                    <th>상태</th>
                    <th>항목</th>
                    <th>모듈</th>
                    <th>건수</th>
                    <th>허용 지연</th>
                    <th>가장 오래된 시각</th>
                    <th>후속 확인</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($operationStatusRows === []) { ?>
                    <tr><td colspan="7" class="admin-empty-state">운영 상태 점검 항목이 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($operationStatusRows as $row) { ?>
                    <?php
                    $rowStatus = (string) ($row['status'] ?? 'error');
                    $rowMessage = trim((string) ($row['message'] ?? ''));
                    ?>
                    <tr>
                        <td class="admin-table-nowrap">
                            <span class="admin-status <?php echo sr_e($statusClass($rowStatus)); ?>">
                                <?php echo sr_e((string) ($row['status_label'] ?? '오류')); ?>
                            </span>
                        </td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e((string) ($row['title'] ?? '')); ?></strong>
                            <?php if ($rowMessage !== '') { ?>
                                <div class="type-caption"><?php echo sr_e($rowMessage); ?></div>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_e(sr_admin_code_label((string) ($row['module'] ?? ''), 'module_key')); ?></td>
                        <td class="admin-table-nowrap text-end"><?php echo sr_e((string) (int) ($row['count'] ?? 0)); ?></td>
                        <td class="admin-table-nowrap"><?php echo sr_e((string) ($row['delay_tolerance'] ?? '')); ?></td>
                        <td class="admin-table-nowrap">
                            <?php if ((string) ($row['oldest_at'] ?? '') !== '') { ?>
                                <?php echo sr_admin_time_html((string) $row['oldest_at']); ?>
                            <?php } else { ?>
                                -
                            <?php } ?>
                        </td>
                        <td class="admin-table-break"><?php echo sr_e((string) ($row['followup'] ?? '')); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php echo sr_admin_status_description_list_html('operational_status', ['ok' => '정상', 'warning' => '주의', 'overdue' => '지연', 'skipped' => '건너뜀', 'error' => '오류']); ?>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
