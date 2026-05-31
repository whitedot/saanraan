<?php

$primaryRow = is_array($dashboardRows[0] ?? null) ? $dashboardRows[0] : [];
?>

<div class="popup-layer-dashboard-summary admin-card card">
    <div class="popup-layer-dashboard-stack" aria-hidden="true">
        <span></span>
        <span></span>
        <span></span>
    </div>
    <div class="popup-layer-dashboard-content">
        <p class="type-meta"><?php echo sr_e(sr_t('popup_layer::ui.text.ac4c6ea9')); ?></p>
        <h2 class="type-section-title"><?php echo sr_e($dashboardSectionTitle); ?></h2>
        <strong class="type-display"><?php echo sr_e((string) ($primaryRow['value'] ?? '0')); ?></strong>
        <span class="type-small"><?php echo sr_e((string) ($primaryRow['label'] ?? sr_t('popup_layer::ui.text.903a4275'))); ?></span>
        <small class="type-small"><?php echo sr_e((string) ($primaryRow['detail'] ?? sr_t('popup_layer::ui.save.0.ace7e512'))); ?></small>
        <a href="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="btn btn-outline-default"><?php echo sr_e(sr_t('popup_layer::ui.list.f0aa41f6')); ?></a>
    </div>
</div>
