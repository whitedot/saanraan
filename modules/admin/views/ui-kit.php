<?php

$adminPageTitle = sr_t('admin::ui.admin.ui.kit.e8bf017c');
$adminPageSubtitle = sr_t('admin::ui.admin.ui.kit.6edaa059');
$adminContainerClass = 'admin-page-ui-kit';

$uiKitSamples = [
    'typography' => 'Typography',
    'ui-buttons' => 'Buttons',
    'ui-cards' => 'Cards',
    'ui-alerts' => 'Alerts',
    'ui-badges' => 'Badges',
    'ui-modals' => 'Modals',
    'ui-dropdowns' => 'Dropdowns',
    'ui-tabs' => 'Tabs',
    'form-elements' => 'Form Elements',
    'form-validation' => 'Form Validation',
    'tables-static' => 'Static Tables',
];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<link rel="stylesheet" href="<?php echo sr_e(sr_admin_asset_url('/modules/admin/assets/ui-kit.css')); ?>">

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('admin::ui.text.1f36938c')); ?></h2>
        <a href="<?php echo sr_e(sr_url('/ui-kit')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo sr_e(sr_t('admin::ui.public.ui.kit.cba054e6')); ?></a>
    </div>
    <div class="card-body">
        <p class="admin-card-subtitle"><?php echo sr_e(sr_t('admin::ui.admin.ui.49666d14')); ?></p>
        <nav class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2" aria-label="<?php echo sr_e(sr_t('admin::ui.admin.ui.kit.03cf9fea')); ?>">
            <?php foreach ($uiKitSamples as $sampleKey => $sampleLabel) { ?>
                <a class="btn btn-sm btn-soft-default" href="#ui-kit-<?php echo sr_e($sampleKey); ?>"><?php echo sr_e($sampleLabel); ?></a>
            <?php } ?>
        </nav>
    </div>
</section>

<div class="ui-kit-sample-body admin-ui-kit-samples ui-form-theme">
    <?php foreach ($uiKitSamples as $sampleKey => $sampleLabel) { ?>
        <section id="ui-kit-<?php echo sr_e($sampleKey); ?>" class="admin-ui-kit-section ui-kit-space-before-base" aria-labelledby="ui-kit-title-<?php echo sr_e($sampleKey); ?>">
            <h2 id="ui-kit-title-<?php echo sr_e($sampleKey); ?>" class="admin-ui-kit-section-title"><?php echo sr_e($sampleLabel); ?></h2>
            <?php
            $sampleFile = SR_ROOT . '/modules/admin/views/ui-kit-samples/' . $sampleKey . '.php';
            if (is_file($sampleFile)) {
                include $sampleFile;
            }
            ?>
        </section>
    <?php } ?>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
