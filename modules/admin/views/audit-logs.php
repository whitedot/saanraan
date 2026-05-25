<?php

$adminPageTitle = sr_t('admin::ui.admin.d0bd9568');
include SR_ROOT . '/modules/admin/views/layout-header.php';
$auditMetadataModals = [];
?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/audit-logs')); ?>" class="admin-filter admin-audit-filter ui-form-theme">
    <?php if (($filters['event_type'] ?? '') !== '') { ?>
        <input type="hidden" name="event_type" value="<?php echo sr_e((string) $filters['event_type']); ?>">
    <?php } ?>
    <?php if (($filters['target_type'] ?? '') !== '') { ?>
        <input type="hidden" name="target_type" value="<?php echo sr_e((string) $filters['target_type']); ?>">
    <?php } ?>
    <?php if (($filters['target_id'] ?? '') !== '') { ?>
        <input type="hidden" name="target_id" value="<?php echo sr_e((string) $filters['target_id']); ?>">
    <?php } ?>
    <div class="admin-filter-header">
        <strong><?php echo sr_e(sr_t('admin::ui.search.3aa5fca0')); ?></strong>
    </div>
    <div class="admin-filter-grid admin-audit-search-grid">
        <label class="admin-filter-field admin-audit-filter-field" for="modules_admin_audit_logs_field">
            <span class="admin-filter-label"><?php echo sr_e(sr_t('admin::ui.search.b79bc9c8')); ?></span>
            <select id="modules_admin_audit_logs_field" name="field" class="form-select">
                <?php foreach (['event_type' => sr_t('admin::ui.text.b7c0f34b'), 'target_type' => sr_t('admin::ui.text.91df7a82'), 'target_id' => '대상 ID', 'actor_account_id' => sr_t('admin::ui.id.2ea55f7c')] as $value => $label) { ?>
                    <option value="<?php echo sr_e($value); ?>"<?php echo $filters['field'] === $value ? ' selected' : ''; ?>>
                        <?php echo sr_e($label); ?>
                    </option>
                <?php } ?>
            </select>
        </label>
        <label class="admin-filter-field admin-audit-filter-result" for="modules_admin_audit_logs_result">
            <span class="admin-filter-label"><?php echo sr_e(sr_t('admin::ui.text.109383e3')); ?></span>
            <select id="modules_admin_audit_logs_result" name="result" class="form-select">
                <?php foreach (['' => sr_t('admin::ui.all.a4b69faf'), 'success' => sr_t('admin::ui.text.b4f76a33'), 'failure' => sr_t('admin::ui.text.2743911f')] as $value => $label) { ?>
                    <option value="<?php echo sr_e((string) $value); ?>"<?php echo $filters['result'] === (string) $value ? ' selected' : ''; ?>>
                        <?php echo sr_e($label); ?>
                    </option>
                <?php } ?>
            </select>
        </label>
        <label class="admin-filter-field admin-audit-filter-date" for="modules_admin_audit_logs_date_from">
            <span class="admin-filter-label"><?php echo sr_e(sr_t('admin::ui.text.f86e346d')); ?></span>
            <input id="modules_admin_audit_logs_date_from" type="date" name="date_from" value="<?php echo sr_e($filters['date_from']); ?>" class="form-input">
        </label>
        <label class="admin-filter-field admin-audit-filter-date" for="modules_admin_audit_logs_date_to">
            <span class="admin-filter-label"><?php echo sr_e(sr_t('admin::ui.text.9e586213')); ?></span>
            <input id="modules_admin_audit_logs_date_to" type="date" name="date_to" value="<?php echo sr_e($filters['date_to']); ?>" class="form-input">
        </label>
        <label class="admin-filter-field admin-audit-filter-keyword" for="modules_admin_audit_logs_keyword">
            <span class="admin-filter-label"><?php echo sr_e(sr_t('admin::ui.search.bda397fc')); ?></span>
            <input id="modules_admin_audit_logs_keyword" type="text" name="q" value="<?php echo sr_e($filters['q']); ?>" class="form-input" maxlength="80" placeholder="<?php echo sr_e(sr_t('admin::ui.id.f8d506bd')); ?>">
        </label>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit"><?php echo sr_e(sr_t('admin::ui.text.f8d240bf')); ?></button>
    </div>
    <?php if (($filters['event_type'] ?? '') !== '' || ($filters['target_type'] ?? '') !== '' || ($filters['target_id'] ?? '') !== '') { ?>
        <div class="admin-summary-stats">
            <?php if (($filters['event_type'] ?? '') !== '') { ?>
                <span class="admin-summary-meta">이벤트 <strong><?php echo sr_e((string) $filters['event_type']); ?></strong></span>
            <?php } ?>
            <?php if (($filters['target_type'] ?? '') !== '') { ?>
                <span class="admin-summary-meta">대상 유형 <strong><?php echo sr_e(sr_admin_code_label((string) $filters['target_type'], 'target_type')); ?></strong></span>
            <?php } ?>
            <?php if (($filters['target_id'] ?? '') !== '') { ?>
                <span class="admin-summary-meta">대상 ID <strong><?php echo sr_e((string) $filters['target_id']); ?></strong></span>
            <?php } ?>
            <a href="<?php echo sr_e(sr_url('/admin/audit-logs')); ?>" class="admin-summary-meta">필터 해제</a>
        </div>
    <?php } ?>
</form>

<div class="admin-card admin-list-card card admin-list-form">
<div class="table-wrapper">
<table class="table admin-audit-log-table">
    <thead class="ui-table-head">
        <tr>
            <th>ID</th>
            <th><?php echo sr_e(sr_t('admin::ui.text.faea4ccf')); ?></th>
            <th><?php echo sr_e(sr_t('admin::ui.text.750086e9')); ?></th>
            <th><?php echo sr_e(sr_t('admin::ui.text.46b289bb')); ?></th>
            <th><?php echo sr_e(sr_t('admin::ui.text.8c609deb')); ?></th>
            <th><?php echo sr_e(sr_t('admin::ui.text.109383e3')); ?></th>
            <th>IP</th>
            <th><?php echo sr_e(sr_t('admin::ui.text.4cd44bae')); ?></th>
            <th><?php echo sr_e(sr_t('admin::ui.text.7d98432e')); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if ($logs === []) { ?>
            <tr>
                <td colspan="9" class="admin-empty-state"><?php echo sr_e(sr_t('admin::ui.admin.7d324209')); ?></td>
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
                        <button type="button" class="btn btn-sm btn-icon btn-solid-light" aria-label="<?php echo sr_e(sr_t('admin::ui.text.ac5b575f')); ?>" title="<?php echo sr_e(sr_t('admin::ui.text.ac5b575f')); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($metadataModalId); ?>" data-overlay="#<?php echo sr_e($metadataModalId); ?>"><?php echo sr_material_icon_html('visibility'); ?></button>
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
                    <h3 id="<?php echo sr_e((string) $auditMetadataModal['id']); ?>_title" class="modal-title"><?php echo sr_e(sr_t('admin::ui.text.a72ac849')); ?></h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e((string) $auditMetadataModal['id']); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="admin-summary-stats">
                        <span class="admin-summary-meta"><?php echo sr_e(sr_t('admin::ui.text.e0918eb0')); ?> <strong>#<?php echo sr_e((string) $auditMetadataModal['log_id']); ?></strong></span>
                        <span class="admin-summary-meta"><?php echo sr_e((string) $auditMetadataModal['event_type']); ?></span>
                        <span class="admin-summary-meta"><?php echo sr_e((string) $auditMetadataModal['created_at']); ?></span>
                    </div>
                    <pre class="admin-audit-metadata-pre"><code><?php echo sr_e((string) $auditMetadataModal['metadata']); ?></code></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e((string) $auditMetadataModal['id']); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
