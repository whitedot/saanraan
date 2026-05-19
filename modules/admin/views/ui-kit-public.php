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
    'icons-tabler' => 'Tabler Icons',
    'icons-lucide' => 'Lucide Icons',
];

$seo = [
    'title' => 'Public UI-KIT',
    'robots' => 'noindex, nofollow',
];

sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'stylesheets' => ['/assets/public-ui-kit.css'],
]);
?>
    <script src="https://code.iconify.design/3/3.1.0/iconify.min.js" defer></script>
    <main class="public-ui-scope public-ui-kit">
        <section class="public-ui-card">
            <h1 class="public-ui-title">Public UI-KIT</h1>
            <p class="public-ui-copy">기존 중앙 UI-KIT의 모든 카테고리 예시를 public layout 런타임 안으로 옮긴 조회 화면입니다.</p>
            <p class="public-ui-copy"><a href="<?php echo sr_e(sr_url('/admin/ui-kit')); ?>">관리자 UI-KIT 보기</a></p>
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
                    $sampleFile = SR_ROOT . '/layouts/public/basic/ui-kit-samples/' . $sampleKey . '.php';
                    if (is_file($sampleFile)) {
                        include $sampleFile;
                    }
                    ?>
                </section>
            <?php } ?>
        </div>
    </main>
<?php sr_public_layout_end(); ?>
