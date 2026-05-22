<?php

$primaryRow = is_array($dashboardRows[0] ?? null) ? $dashboardRows[0] : [];
?>

<div class="notification-dashboard-summary admin-card card">
    <div class="notification-dashboard-head">
        <p><?php echo sr_e(sr_t('notification::ui.text.ecf16058')); ?></p>
        <h2><?php echo sr_e($dashboardSectionTitle); ?></h2>
    </div>
    <div class="notification-dashboard-count">
        <span><?php echo sr_e((string) ($primaryRow['label'] ?? sr_t('notification::ui.all.notification.cff83725'))); ?></span>
        <strong><?php echo sr_e((string) ($primaryRow['value'] ?? '0')); ?></strong>
    </div>
    <div class="notification-dashboard-detail">
        <span><?php echo sr_e((string) ($primaryRow['detail'] ?? sr_t('notification::ui.0.e82c2721'))); ?></span>
        <a href="/admin/notifications" class="btn btn-surface-default-soft"><?php echo sr_e(sr_t('notification::ui.notification.list.7475cac1')); ?></a>
    </div>
</div>
