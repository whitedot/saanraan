<?php

$summaryRows = [
    array_merge(['label' => sr_t('community::ui.text.0b138cfe'), 'value' => '0', 'detail' => sr_t('community::ui.0.11d4b1e9'), 'state' => 'default', 'emphasis' => 'primary'], is_array($dashboardRows[0] ?? null) ? $dashboardRows[0] : []),
    array_merge(['label' => sr_t('community::ui.text.bbb56c63'), 'value' => '0', 'detail' => sr_t('community::ui.0.c86ae2ee'), 'state' => 'default', 'emphasis' => 'default'], is_array($dashboardRows[1] ?? null) ? $dashboardRows[1] : []),
];
?>

<div class="card admin-dashboard-module-default">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e($dashboardSectionTitle); ?></h2>
    </div>
    <dl class="admin-dashboard-module-stats">
        <?php foreach ($summaryRows as $row) { ?>
            <div class="admin-dashboard-module-stat" data-admin-dashboard-state="<?php echo sr_e((string) $row['state']); ?>" data-admin-dashboard-emphasis="<?php echo sr_e((string) $row['emphasis']); ?>">
                <dt><?php echo sr_e((string) $row['label']); ?></dt>
                <dd><?php echo sr_e((string) $row['value']); ?></dd>
                <?php if ((string) $row['detail'] !== '') { ?>
                    <dd class="admin-dashboard-module-stat-detail"><?php echo sr_e((string) $row['detail']); ?></dd>
                <?php } ?>
            </div>
        <?php } ?>
    </dl>
    <div class="card-footer">
        <a href="<?php echo sr_e(sr_url('/admin/community/posts')); ?>" class="btn btn-sm btn-ghost-light"><?php echo sr_e(sr_t('community::ui.text.0b138cfe')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="btn btn-sm btn-ghost-light"><?php echo sr_e(sr_t('community::ui.text.4732a58f')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/community/reports')); ?>" class="btn btn-sm btn-ghost-light"><?php echo sr_e(sr_t('community::ui.text.35c80e56')); ?></a>
    </div>
</div>
