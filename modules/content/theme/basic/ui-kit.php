<?php

$uiKitSamples = [
    'ui-compositions' => 'Compositions & States',
    'typography' => 'Typography',
    'ui-buttons' => 'Buttons',
    'ui-cards' => 'Cards',
    'ui-alerts' => 'Alerts',
    'ui-badges' => 'Badges',
    'ui-modals' => 'Modals',
    'ui-dropdowns' => 'Dropdowns',
    'ui-dropdown-menus' => 'Dropdown Menus',
    'member-profile-images' => 'Member Profile Images',
    'ui-tabs' => 'Tabs',
    'form-elements' => 'Form Elements',
    'form-validation' => 'Form Validation',
    'tables-static' => 'Tables',
];

$seo = [
    'title' => '콘텐츠 UI Kit',
    'robots' => 'noindex, nofollow',
];

$contentLayoutSettings = isset($contentLayoutSettings) && is_array($contentLayoutSettings)
    ? $contentLayoutSettings
    : sr_content_settings($pdo);

sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_content_ui_kit_layout_context($contentLayoutSettings, [
    'include_installed_layout_options' => true,
]));
?>

    <main class="content-ui-kit" data-theme-ui-kit-view="content.basic">
        <section class="card content-ui-kit-summary">
            <div class="card-header">
                <h1 class="card-title">콘텐츠 UI Kit</h1>
            </div>
            <div class="card-body">
                <p class="content-ui-kit-subtitle">콘텐츠 모듈이 실제 공개 화면에서 쓰는 UI 기준입니다.</p>
                <nav class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2" aria-label="콘텐츠 UI Kit 샘플">
                    <?php foreach ($uiKitSamples as $sampleKey => $sampleLabel) { ?>
                        <a class="btn btn-sm btn-soft-default" href="#ui-kit-<?php echo sr_e($sampleKey); ?>"><?php echo sr_e($sampleLabel); ?></a>
                    <?php } ?>
                </nav>
            </div>
        </section>

        <div class="ui-kit-sample-body content-ui-kit-samples ui-form-theme">
            <?php foreach ($uiKitSamples as $sampleKey => $sampleLabel) { ?>
                <section id="ui-kit-<?php echo sr_e($sampleKey); ?>" class="content-ui-kit-section ui-kit-space-before-base" aria-labelledby="ui-kit-title-<?php echo sr_e($sampleKey); ?>">
                    <h2 id="ui-kit-title-<?php echo sr_e($sampleKey); ?>" class="content-ui-kit-section-title"><?php echo sr_e($sampleLabel); ?></h2>
                    <?php
                    $sampleFile = SR_ROOT . '/modules/content/views/ui-kit-samples/' . $sampleKey . '.php';
                    if (is_file($sampleFile)) {
                        include $sampleFile;
                    }
                    ?>
                </section>
            <?php } ?>
        </div>
    </main>

<script<?php echo sr_csp_nonce_attribute(); ?>>
(function () {
    var root = document.querySelector('.content-ui-kit-samples');
    if (!root) {
        return;
    }

    root.querySelectorAll('button.dropdown-toggle:not([aria-label]):not([aria-labelledby])').forEach(function (button) {
        button.setAttribute('aria-label', 'Open sample menu');
    });

})();
</script>

<?php sr_public_layout_end(); ?>
