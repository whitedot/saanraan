<?php

$adminPageTitle = '관리자 UI-KIT';
$adminPageSubtitle = '실제 관리자 CSS와 공용 JS로 중앙 UI-KIT 예시를 확인합니다.';
$adminContainerClass = 'admin-page-ui-kit';

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

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<link rel="stylesheet" href="<?php echo sr_e(sr_admin_asset_url('/modules/admin/assets/ui-kit.css')); ?>">
<script src="https://code.iconify.design/3/3.1.0/iconify.min.js" defer></script>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">조회 범위</h2>
        <a href="<?php echo sr_e(sr_url('/admin/ui-kit-public')); ?>" class="btn btn-sm btn-primary">Public 런타임 미리보기</a>
    </div>
    <div class="card-body">
        <p class="muted-text">기존 중앙 UI-KIT의 모든 카테고리 예시를 관리자 런타임 안으로 옮긴 조회 화면입니다.</p>
        <nav class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2" aria-label="관리자 UI-KIT 섹션">
            <?php foreach ($uiKitSamples as $sampleKey => $sampleLabel) { ?>
                <a class="btn btn-sm btn-light" href="#ui-kit-<?php echo sr_e($sampleKey); ?>"><?php echo sr_e($sampleLabel); ?></a>
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
