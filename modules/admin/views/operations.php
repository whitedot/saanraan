<?php

$adminPageTitle = '운영 상태';
$adminPageSubtitle = 'queue, cron, batch 지연 신호';
include SR_ROOT . '/modules/admin/views/layout-header.php';

$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'ok' => 'badge-soft-success',
        'warning' => 'badge-soft-warning',
        'overdue' => 'badge-soft-danger',
        'skipped' => 'badge-soft-secondary',
        default => 'badge-soft-danger',
    };
};
?>

<section class="admin-card admin-list-card card">
    <div class="card-header">
        <h2 class="card-title">운영 상태 요약</h2>
    </div>
    <div class="admin-list-summary-row">
        <div class="badge-list">
            <span class="badge-list-item">
                <span class="badge-list-label">지연 초과</span>
                <span class="badge-list-summary"><?php echo sr_e((string) (int) ($operationStatusSummary['overdue'] ?? 0)); ?>개</span>
            </span>
            <span class="badge-list-item">
                <span class="badge-list-label">확인 필요</span>
                <span class="badge-list-summary"><?php echo sr_e((string) (int) ($operationStatusSummary['warning'] ?? 0)); ?>개</span>
            </span>
            <span class="badge-list-item">
                <span class="badge-list-label">점검 대상 항목</span>
                <span class="badge-list-summary"><?php echo sr_e((string) (int) ($operationStatusSummary['total_count'] ?? 0)); ?>건</span>
            </span>
            <span class="badge-list-item">
                <span class="badge-list-label">마지막 확인</span>
                <span class="badge-list-summary"><?php echo sr_admin_time_html($operationStatusCheckedAt); ?></span>
            </span>
        </div>
    </div>
    <div class="alert alert-info">
        <strong>읽기 전용 점검</strong>
        <p>이 화면은 지연과 실패 신호만 조회하며 데이터를 변경하지 않습니다. 재시도, 취소, 정정은 각 소유 모듈의 관리자 화면과 감사 로그 기준으로 처리합니다.</p>
    </div>
</section>

<section class="admin-card admin-list-card card">
    <div class="card-header">
        <h2 class="card-title">지연/실패 신호</h2>
    </div>
    <div class="table-wrapper">
        <table class="table admin-operations-table">
            <thead class="ui-table-head">
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
                        <td>
                            <span class="badge <?php echo sr_e($statusBadgeClass($rowStatus)); ?>">
                                <?php echo sr_e((string) ($row['status_label'] ?? '오류')); ?>
                            </span>
                        </td>
                        <td>
                            <strong><?php echo sr_e((string) ($row['title'] ?? $row['label'] ?? '')); ?></strong>
                            <div class="type-meta"><?php echo sr_e((string) ($row['label'] ?? '')); ?></div>
                            <?php if ($rowMessage !== '') { ?>
                                <div class="type-caption"><?php echo sr_e($rowMessage); ?></div>
                            <?php } ?>
                        </td>
                        <td><?php echo sr_e((string) ($row['module'] ?? '')); ?></td>
                        <td><?php echo sr_e((string) (int) ($row['count'] ?? 0)); ?></td>
                        <td><?php echo sr_e((string) ($row['delay_tolerance'] ?? '')); ?></td>
                        <td>
                            <?php if ((string) ($row['oldest_at'] ?? '') !== '') { ?>
                                <?php echo sr_admin_time_html((string) $row['oldest_at']); ?>
                            <?php } else { ?>
                                -
                            <?php } ?>
                        </td>
                        <td><?php echo sr_e((string) ($row['followup'] ?? '')); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
