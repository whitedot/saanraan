<?php

if (!isset($dashboardSection) || !is_array($dashboardSection)) {
    $dashboardSection = [];
}
if (!isset($dashboardRows) || !is_array($dashboardRows)) {
    $dashboardRows = [];
}
$dashboardLayout = (string) ($dashboardSection['layout'] ?? 'table');
?>

<div class="card admin-list-card admin-list-form admin-dashboard-module-default">
    <div class="card-header">
        <div>
            <h2 class="card-title"><?php echo sr_e((string) ($dashboardSection['title'] ?? sr_t('admin::ui.text.6d2d8bf4'))); ?></h2>
            <p class="admin-dashboard-meta"><?php echo sr_e(sr_admin_code_label((string) ($dashboardSection['module_key'] ?? ''), 'module_key')); ?> <?php echo sr_e(sr_t('admin::ui.text.6d2d8bf4')); ?></p>
        </div>
    </div>
    <?php if ($dashboardLayout === 'stats') { ?>
        <dl class="admin-dashboard-module-stats">
            <?php foreach ($dashboardRows as $row) { ?>
                <div class="admin-dashboard-module-stat" data-admin-dashboard-state="<?php echo sr_e((string) ($row['state'] ?? 'default')); ?>" data-admin-dashboard-emphasis="<?php echo sr_e((string) ($row['emphasis'] ?? 'default')); ?>">
                    <dt><?php echo sr_e((string) ($row['label'] ?? '')); ?></dt>
                    <dd><?php echo sr_e((string) ($row['value'] ?? '')); ?></dd>
                    <?php if ((string) ($row['detail'] ?? '') !== '') { ?>
                        <dd class="admin-dashboard-module-stat-detail"><?php echo sr_e((string) $row['detail']); ?></dd>
                    <?php } ?>
                </div>
            <?php } ?>
        </dl>
    <?php } else { ?>
        <div class="table-wrapper">
            <table class="table table-list">
                <thead>
                    <tr>
                        <th><?php echo sr_e(sr_t('admin::ui.text.962f286b')); ?></th>
                        <th><?php echo sr_e(sr_t('admin::ui.text.e1042931')); ?></th>
                        <th><?php echo sr_e(sr_t('admin::ui.text.d211d97f')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dashboardRows as $row) { ?>
                        <tr>
                            <td><?php echo sr_e((string) ($row['label'] ?? '')); ?></td>
                            <td><?php echo sr_e((string) ($row['value'] ?? '')); ?></td>
                            <td><?php echo sr_e((string) ($row['detail'] ?? '')); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>
