<?php

$postRow = is_array($dashboardRows[0] ?? null) ? $dashboardRows[0] : [];
$reportRow = is_array($dashboardRows[1] ?? null) ? $dashboardRows[1] : [];
?>

<div class="community-dashboard-summary admin-card card">
    <div class="community-dashboard-main">
        <p><?php echo sr_e(sr_t('community::ui.community.ae00cbeb')); ?></p>
        <h2><?php echo sr_e($dashboardSectionTitle); ?></h2>
        <strong><?php echo sr_e((string) ($postRow['value'] ?? '0')); ?></strong>
        <span><?php echo sr_e((string) ($postRow['label'] ?? sr_t('community::ui.text.0b138cfe'))); ?></span>
        <small><?php echo sr_e((string) ($postRow['detail'] ?? sr_t('community::ui.0.11d4b1e9'))); ?></small>
    </div>
    <div class="community-dashboard-side">
        <div>
            <span><?php echo sr_e((string) ($reportRow['label'] ?? sr_t('community::ui.text.bbb56c63'))); ?></span>
            <strong><?php echo sr_e((string) ($reportRow['value'] ?? '0')); ?></strong>
            <small><?php echo sr_e((string) ($reportRow['detail'] ?? sr_t('community::ui.0.c86ae2ee'))); ?></small>
        </div>
        <a href="/admin/community/reports" class="btn btn-outline-default"><?php echo sr_e(sr_t('community::ui.text.35c80e56')); ?></a>
    </div>
    <div class="community-dashboard-links">
        <a href="/admin/community/posts" class="btn btn-surface-default-soft"><?php echo sr_e(sr_t('community::ui.text.0b138cfe')); ?></a>
        <a href="/admin/community/boards" class="btn btn-surface-default-soft"><?php echo sr_e(sr_t('community::ui.text.4732a58f')); ?></a>
    </div>
</div>
