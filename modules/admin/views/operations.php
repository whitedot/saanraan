<?php

$adminPageTitle = '운영 지연/실패 점검';
$adminPageSubtitle = [
    '지연되었거나 실패한 항목은 해당 관리 화면에서 처리해 주세요.',
];
$adminOperationsHelp = [
    'id' => 'admin-operations-status-action-help',
    'title' => '운영 점검 상태 처리 도움말',
    'body' => '<p>이 화면의 ‘확인됨’과 ‘정상으로 취급’은 실패한 발송·정리·회수 작업을 실제로 재시도하거나 해결하는 기능이 아닙니다. 먼저 각 행의 처리 화면에서 원인과 실패 항목을 확인하세요.</p>'
        . '<p>‘확인됨’은 운영자가 현재 경고를 확인했다는 상태로 표시합니다. 대상 항목은 그대로 남아 있으며, 건수·가장 오래된 시각·원래 상태가 바뀌면 다시 경고합니다.</p>'
        . '<p>‘정상으로 취급’은 확인됨 상태의 경고를 현재 조건에서 정상으로 표시합니다. 예상한 대기 물량이거나 운영 정책상 문제가 아닌 경우에만 사용하세요. 원인을 해결하지 않아도 건수·가장 오래된 시각·원래 상태가 바뀌기 전까지는 정상으로 보일 수 있습니다.</p>',
];
include SR_ROOT . '/modules/admin/views/layout-header.php';

$statusClass = static function (string $status): string {
    return match ($status) {
        'ok' => 'is-success',
        'warning' => 'is-warning',
        'overdue', 'error' => 'is-danger',
        'acknowledged' => 'is-warning',
        'skipped' => 'is-warning',
        default => 'is-danger',
    };
};
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>
<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">지연/실패 신호</h2>
    </div>
    <div class="admin-list-summary-row admin-operations-summary-row">
        <div class="badge-list">
            <span class="badge badge-soft-danger">지연 초과 <?php echo sr_e((string) (int) ($operationStatusSummary['overdue'] ?? 0)); ?>개</span>
            <span class="badge badge-soft-warning">확인 필요 <?php echo sr_e((string) (int) ($operationStatusSummary['warning'] ?? 0)); ?>개</span>
            <span class="badge badge-soft-secondary">확인됨 <?php echo sr_e((string) (int) ($operationStatusSummary['acknowledged'] ?? 0)); ?>개</span>
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
                    <th>대상</th>
                    <th>후속 확인</th>
                    <th class="text-end">
                        <span class="form-label-help">
                            <button type="button" class="admin-label-help-button" aria-label="상태 처리 도움말 보기" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($adminOperationsHelp['id']); ?>" data-overlay="#<?php echo sr_e($adminOperationsHelp['id']); ?>"><?php echo sr_material_icon_html('help'); ?></button>
                            <span>관리</span>
                        </span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if ($operationStatusRows === []) { ?>
                    <tr><td colspan="9" class="admin-empty-state">운영 지연/실패 점검 항목이 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($operationStatusRows as $row) { ?>
                    <?php
                    $rowStatus = (string) ($row['status'] ?? 'error');
                    $rowMessage = trim((string) ($row['message'] ?? ''));
                    $rowTargets = isset($row['targets']) && is_array($row['targets']) ? $row['targets'] : [];
                    $rowFollowup = (string) ($row['followup'] ?? '');
                    $rowActionUrl = $rowStatus !== 'ok' ? (string) ($row['action_url'] ?? '') : '';
                    $rowActionLabel = trim((string) ($row['action_label'] ?? '처리 화면'));
                    $rowActionLabel = $rowActionLabel !== '' ? $rowActionLabel : '처리 화면';
                    ?>
                    <tr>
                        <td class="admin-table-nowrap">
                            <span class="badge-status <?php echo sr_e($statusClass($rowStatus)); ?>">
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
                        <td class="admin-table-break">
                            <?php if ($rowTargets !== []) { ?>
                                <div class="admin-operations-target-list">
                                    <?php foreach ($rowTargets as $target) { ?>
                                        <div><?php echo sr_e((string) $target); ?></div>
                                    <?php } ?>
                                </div>
                            <?php } else { ?>
                                -
                            <?php } ?>
                        </td>
                        <td class="admin-table-break">
                            <?php if ($rowFollowup !== '') { ?>
                                <span><?php echo sr_e($rowFollowup); ?></span>
                            <?php } else { ?>
                                -
                            <?php } ?>
                        </td>
                        <td class="admin-table-actions-cell">
                            <?php if ($rowActionUrl !== '' || in_array($rowStatus, ['warning', 'overdue', 'acknowledged'], true)) { ?>
                                <div class="admin-row-actions">
                                    <?php if ($rowActionUrl !== '') { ?>
                                        <a href="<?php echo sr_e(sr_url($rowActionUrl)); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e($rowActionLabel . ' 바로가기'); ?>" title="<?php echo sr_e($rowActionLabel); ?>"><?php echo sr_material_icon_html('open_in_new'); ?></a>
                                    <?php } ?>
                                    <?php if (in_array($rowStatus, ['warning', 'overdue'], true)) { ?>
                                        <form method="post" action="<?php echo sr_e(sr_url('/admin/operations')); ?>">
                                            <?php echo sr_csrf_field(); ?>
                                            <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/operations')); ?>">
                                            <input type="hidden" name="intent" value="acknowledge">
                                            <input type="hidden" name="label" value="<?php echo sr_e((string) ($row['label'] ?? '')); ?>">
                                            <button type="submit" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e((string) ($row['title'] ?? '운영 점검 항목') . ' 확인한 항목으로 표시'); ?>" title="확인한 항목으로 표시"><?php echo sr_material_icon_html('done'); ?></button>
                                        </form>
                                    <?php } ?>
                                    <?php if ($rowStatus === 'acknowledged') { ?>
                                        <form method="post" action="<?php echo sr_e(sr_url('/admin/operations')); ?>">
                                            <?php echo sr_csrf_field(); ?>
                                            <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/operations')); ?>">
                                            <input type="hidden" name="intent" value="treat_as_ok">
                                            <input type="hidden" name="label" value="<?php echo sr_e((string) ($row['label'] ?? '')); ?>">
                                            <button type="submit" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e((string) ($row['title'] ?? '운영 점검 항목') . ' 정상으로 취급'); ?>" title="정상으로 취급"><?php echo sr_material_icon_html('check_circle'); ?></button>
                                        </form>
                                    <?php } ?>
                                </div>
                            <?php } else { ?>
                                -
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php echo sr_admin_status_description_list_html('operational_status', ['ok' => '정상', 'warning' => '주의', 'overdue' => '지연', 'acknowledged' => '확인됨', 'skipped' => '건너뜀', 'error' => '오류'], [
        'ok' => '지연·실패 기준에 해당하지 않거나 운영자가 현재 조건을 정상으로 취급했습니다.',
        'warning' => '허용 시간 안이지만 실패하거나 처리되지 않은 항목을 확인해야 합니다.',
        'overdue' => '허용한 지연 시간을 넘겼습니다. 해당 처리 화면에서 원인을 확인하세요.',
        'acknowledged' => '운영자가 경고를 확인했지만 대상 항목은 아직 남아 있습니다.',
        'skipped' => '현재 환경에서 점검할 수 없거나 적용 대상이 아닙니다.',
        'error' => '점검 자체를 완료하지 못했습니다. 표시된 오류를 확인하세요.',
    ]); ?>
</section>

<?php echo sr_admin_help_modal_html($adminOperationsHelp['id'], $adminOperationsHelp['title'], $adminOperationsHelp['body']); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
