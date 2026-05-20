<?php

$uiKitSamples = [
    'index' => 'Dashboard',
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
    <main class="public-ui-scope public-ui-kit">
        <section class="public-ui-card">
            <h1 class="public-ui-title">Public UI-KIT</h1>
            <p class="public-ui-copy">사이트 기본 public layout 런타임에서 공통 UI 원형을 확인하는 화면입니다.</p>
            <nav class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2" aria-label="Public UI-KIT 섹션">
                <?php foreach ($uiKitSamples as $sampleKey => $sampleLabel) { ?>
                    <a class="public-ui-button" href="#ui-kit-<?php echo sr_e($sampleKey); ?>"><?php echo sr_e($sampleLabel); ?></a>
                <?php } ?>
            </nav>
        </section>

        <div class="ui-kit-sample-body public-ui-kit-samples">
            <?php foreach ($uiKitSamples as $sampleKey => $sampleLabel) { ?>
                <section id="ui-kit-<?php echo sr_e($sampleKey); ?>" class="public-ui-card ui-kit-space-before-base">
                    <h2 class="public-ui-title"><?php echo sr_e($sampleLabel); ?></h2>
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
