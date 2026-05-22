<?php

$uiKitSamples = [
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

$seo = [
    'title' => 'Public UI-KIT',
    'robots' => 'noindex, nofollow',
];

sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'stylesheets' => [
        '/assets/public-ui-kit.css',
    ],
]);
?>
    <main class="public-ui-kit">
        <section class="card public-ui-kit-summary">
            <div class="card-header">
                <h1 class="card-title">Public UI-KIT</h1>
            </div>
            <div class="card-body">
                <p class="public-ui-kit-subtitle"><?php echo sr_e(sr_t('ui.public.layout.ui.85d967fa')); ?></p>
                <nav class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2" aria-label="<?php echo sr_e(sr_t('ui.public.ui.kit.6c2248dd')); ?>">
                    <?php foreach ($uiKitSamples as $sampleKey => $sampleLabel) { ?>
                        <a class="btn btn-sm btn-soft-default" href="#ui-kit-<?php echo sr_e($sampleKey); ?>"><?php echo sr_e($sampleLabel); ?></a>
                    <?php } ?>
                </nav>
            </div>
        </section>

        <div class="ui-kit-sample-body public-ui-kit-samples">
            <?php foreach ($uiKitSamples as $sampleKey => $sampleLabel) { ?>
                <section id="ui-kit-<?php echo sr_e($sampleKey); ?>" class="public-ui-kit-section ui-kit-space-before-base" aria-labelledby="ui-kit-title-<?php echo sr_e($sampleKey); ?>">
                    <h2 id="ui-kit-title-<?php echo sr_e($sampleKey); ?>" class="public-ui-kit-section-title"><?php echo sr_e($sampleLabel); ?></h2>
                    <?php
                    $sampleFile = __DIR__ . '/ui-kit-samples/' . $sampleKey . '.php';
                    if (is_file($sampleFile)) {
                        include $sampleFile;
                    }
                    ?>
                </section>
            <?php } ?>
        </div>
    </main>
<?php sr_public_layout_end(); ?>
