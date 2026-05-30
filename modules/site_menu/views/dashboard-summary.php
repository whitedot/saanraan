<?php

$primaryRow = is_array($dashboardRows[0] ?? null) ? $dashboardRows[0] : [];
?>

<div class="site-menu-dashboard-summary admin-card card">
    <div class="site-menu-dashboard-copy">
        <p class="site-menu-dashboard-kicker type-meta"><?php echo sr_e(sr_t('site_menu::ui.text.7773bc85')); ?></p>
        <h2 class="type-section-title"><?php echo sr_e($dashboardSectionTitle); ?></h2>
        <p class="type-small"><?php echo sr_e((string) ($primaryRow['detail'] ?? sr_t('site_menu::ui.menu.28e3b026'))); ?></p>
    </div>
    <div class="site-menu-dashboard-meter">
        <span class="type-display"><?php echo sr_e((string) ($primaryRow['value'] ?? '0')); ?></span>
        <strong class="type-small"><?php echo sr_e((string) ($primaryRow['label'] ?? sr_t('site_menu::ui.menu.33822da6'))); ?></strong>
    </div>
    <div class="site-menu-dashboard-actions">
        <a href="/admin/site-menus" class="btn btn-solid-primary"><?php echo sr_e(sr_t('site_menu::ui.menu.55d2a2dd')); ?></a>
    </div>
</div>
