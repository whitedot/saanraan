<?php

$adminPageTitle = '관리자 작업 로그';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/audit-logs')); ?>" class="admin-filter admin-audit-filter ui-form-theme">
    <div class="admin-filter-header">
        <strong>로그 검색</strong>
        <a href="<?php echo sr_e(sr_url('/admin/audit-logs')); ?>" class="btn btn-sm btn-soft-default">초기화</a>
    </div>
    <div class="admin-filter-grid">
        <label class="admin-filter-field" for="modules_admin_audit_logs_event_type">
            <span class="admin-filter-label">이벤트 유형</span>
            <input id="modules_admin_audit_logs_event_type" type="text" name="event_type" value="<?php echo sr_e($filters['event_type']); ?>" class="form-input" maxlength="80">
        </label>
        <label class="admin-filter-field" for="modules_admin_audit_logs_target_type">
            <span class="admin-filter-label">대상 유형</span>
            <input id="modules_admin_audit_logs_target_type" type="text" name="target_type" value="<?php echo sr_e($filters['target_type']); ?>" class="form-input" maxlength="60">
        </label>
        <label class="admin-filter-field" for="modules_admin_audit_logs_actor_account_id">
            <span class="admin-filter-label">처리자 계정 ID</span>
            <input id="modules_admin_audit_logs_actor_account_id" type="text" name="actor_account_id" value="<?php echo sr_e($filters['actor_account_id']); ?>" class="form-input" maxlength="20" inputmode="numeric" pattern="[0-9]*">
        </label>
        <label class="admin-filter-field" for="modules_admin_audit_logs_result">
            <span class="admin-filter-label">결과</span>
            <select id="modules_admin_audit_logs_result" name="result" class="form-select">
                <?php foreach (['' => '전체', 'success' => '성공', 'failure' => '실패'] as $value => $label) { ?>
                    <option value="<?php echo sr_e((string) $value); ?>"<?php echo $filters['result'] === (string) $value ? ' selected' : ''; ?>>
                        <?php echo sr_e($label); ?>
                    </option>
                <?php } ?>
            </select>
        </label>
        <label class="admin-filter-field" for="modules_admin_audit_logs_date_from">
            <span class="admin-filter-label">시작일</span>
            <input id="modules_admin_audit_logs_date_from" type="date" name="date_from" value="<?php echo sr_e($filters['date_from']); ?>" class="form-input">
        </label>
        <label class="admin-filter-field" for="modules_admin_audit_logs_date_to">
            <span class="admin-filter-label">종료일</span>
            <input id="modules_admin_audit_logs_date_to" type="date" name="date_to" value="<?php echo sr_e($filters['date_to']); ?>" class="form-input">
        </label>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit">조회</button>
    </div>
</form>

<div class="admin-card admin-list-card card admin-list-form">
<div class="table-wrapper">
<table class="table admin-audit-log-table">
    <thead class="ui-table-head">
        <tr>
            <th>ID</th>
            <th>시각</th>
            <th>처리자</th>
            <th>이벤트</th>
            <th>대상</th>
            <th>결과</th>
            <th>IP</th>
            <th>메시지</th>
            <th>메타</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($logs === []) { ?>
            <tr>
                <td colspan="9" class="admin-empty-state">관리자 작업 로그가 없습니다.</td>
            </tr>
        <?php } ?>
        <?php foreach ($logs as $log) { ?>
            <tr>
                <td><?php echo sr_e((string) $log['id']); ?></td>
                <td><?php echo sr_e((string) $log['created_at']); ?></td>
                <td><?php echo sr_e((string) ($log['actor_account_id'] ?? $log['actor_type'])); ?></td>
                <td><?php echo sr_e(sr_admin_event_type_label((string) $log['event_type'])); ?></td>
                <td><?php echo sr_e(sr_admin_code_label((string) $log['target_type'], 'target_type') . ':' . (string) $log['target_id']); ?></td>
                <td><?php echo sr_e(sr_admin_code_label((string) $log['result'], 'result')); ?></td>
                <td><?php echo sr_e((string) $log['ip_address']); ?></td>
                <td class="admin-audit-message"><?php echo sr_e(sr_admin_audit_log_display_message($log)); ?></td>
                <td class="admin-audit-metadata">
                    <?php $metadata = sr_admin_audit_log_display_metadata($log); ?>
                    <?php if ($metadata === '') { ?>
                        -
                    <?php } else { ?>
                        <details>
                            <summary>보기</summary>
                            <code><?php echo sr_e($metadata); ?></code>
                        </details>
                    <?php } ?>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>
</div>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
