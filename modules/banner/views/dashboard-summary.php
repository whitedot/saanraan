<?php

$primaryRow = is_array($dashboardRows[0] ?? null) ? $dashboardRows[0] : [];
?>

<div class="banner-dashboard-summary admin-card card">
    <div class="banner-dashboard-preview" aria-hidden="true">
        <span></span>
        <span></span>
        <span></span>
    </div>
    <div class="banner-dashboard-body">
        <p class="banner-dashboard-kicker type-meta"><?php echo sr_e(sr_t('banner::ui.text.7300622f')); ?></p>
        <h2 class="type-section-title"><?php echo sr_e($dashboardSectionTitle); ?></h2>
        <div class="banner-dashboard-value">
            <strong class="type-display"><?php echo sr_e((string) ($primaryRow['value'] ?? '0')); ?></strong>
            <span class="type-small"><?php echo sr_e((string) ($primaryRow['label'] ?? sr_t('banner::ui.banner.bb1a9b01'))); ?></span>
        </div>
        <p class="type-small"><?php echo sr_e((string) ($primaryRow['detail'] ?? sr_t('banner::ui.banner.status.4bbdd30d'))); ?></p>
        <a href="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="btn btn-surface-default-soft"><?php echo sr_e(sr_t('banner::ui.banner.list.f989d740')); ?></a>
    </div>
</div>
