<?php

$adminPageTitle = '관리자 작업 로그';
include SR_ROOT . '/modules/admin/views/layout-header.php';
$auditMetadataModals = [];
?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/audit-logs')); ?>" class="admin-filter admin-audit-filter ui-form-theme">
    <div class="admin-filter-header">
        <strong>로그 검색</strong>
        <a href="<?php echo sr_e(sr_url('/admin/audit-logs')); ?>" class="btn btn-sm btn-solid-light">초기화</a>
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
                        <?php
                        $metadataModalId = 'admin-audit-metadata-modal-' . (int) $log['id'];
                        $auditMetadataModals[] = [
                            'id' => $metadataModalId,
                            'log_id' => (int) $log['id'],
                            'created_at' => (string) $log['created_at'],
                            'event_type' => sr_admin_event_type_label((string) $log['event_type']),
                            'metadata' => $metadata,
                        ];
                        ?>
                        <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($metadataModalId); ?>" data-overlay="#<?php echo sr_e($metadataModalId); ?>">보기</button>
                    <?php } ?>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>
</div>
</div>

<?php foreach ($auditMetadataModals as $auditMetadataModal) { ?>
    <div id="<?php echo sr_e((string) $auditMetadataModal['id']); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e((string) $auditMetadataModal['id']); ?>_title" aria-hidden="true" inert>
        <div class="modal-dialog admin-audit-metadata-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e((string) $auditMetadataModal['id']); ?>_title" class="modal-title">메타 정보</h3>
                    <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e((string) $auditMetadataModal['id']); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="admin-summary-stats">
                        <span class="admin-summary-meta">로그 <strong>#<?php echo sr_e((string) $auditMetadataModal['log_id']); ?></strong></span>
                        <span class="admin-summary-meta"><?php echo sr_e((string) $auditMetadataModal['event_type']); ?></span>
                        <span class="admin-summary-meta"><?php echo sr_e((string) $auditMetadataModal['created_at']); ?></span>
                    </div>
                    <pre class="admin-audit-metadata-pre"><code><?php echo sr_e((string) $auditMetadataModal['metadata']); ?></code></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e((string) $auditMetadataModal['id']); ?>">닫기</button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
