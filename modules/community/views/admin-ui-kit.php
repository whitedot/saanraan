<?php

$adminPageTitle = '커뮤니티 UI Kit';
$adminPageSubtitle = '커뮤니티 모듈이 소유하는 화면 요소와 공개 타이포그래피 기준입니다.';
$adminContainerClass = 'admin-page-ui-kit admin-page-community-ui-kit';

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
    'tables-static' => 'Tables',
];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<link rel="stylesheet" href="<?php echo sr_e(sr_admin_asset_url('/modules/community/assets/community-ui-kit.css')); ?>">
<link rel="stylesheet" href="<?php echo sr_e(sr_admin_asset_url('/modules/community/assets/community-public.css')); ?>">

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">커뮤니티 모듈 UI Kit</h2>
        <a href="<?php echo sr_e(sr_url('/admin/ui-kit')); ?>" class="btn btn-sm btn-outline-secondary">관리자 UI Kit</a>
    </div>
    <div class="card-body">
        <p class="admin-card-subtitle">공통 런타임을 참고하되, 커뮤니티 모듈이 실제로 쓰는 UI 기준은 이 미리보기에서 관리합니다.</p>
        <nav class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2" aria-label="커뮤니티 UI Kit 샘플">
            <?php foreach ($uiKitSamples as $sampleKey => $sampleLabel) { ?>
                <a class="btn btn-sm btn-soft-default" href="#ui-kit-<?php echo sr_e($sampleKey); ?>"><?php echo sr_e($sampleLabel); ?></a>
            <?php } ?>
        </nav>
    </div>
</section>

<div class="ui-kit-sample-body admin-ui-kit-samples community-ui-kit-samples ui-form-theme">
    <?php foreach ($uiKitSamples as $sampleKey => $sampleLabel) { ?>
        <section id="ui-kit-<?php echo sr_e($sampleKey); ?>" class="admin-ui-kit-section ui-kit-space-before-base" aria-labelledby="ui-kit-title-<?php echo sr_e($sampleKey); ?>">
            <h2 id="ui-kit-title-<?php echo sr_e($sampleKey); ?>" class="admin-ui-kit-section-title"><?php echo sr_e($sampleLabel); ?></h2>
            <?php
            $sampleFile = SR_ROOT . '/modules/community/views/ui-kit-samples/' . $sampleKey . '.php';
            if (is_file($sampleFile)) {
                include $sampleFile;
            }
            ?>
        </section>
    <?php } ?>
</div>

<script>
(function () {
    var root = document.querySelector('.community-ui-kit-samples');
    if (!root) {
        return;
    }

    root.querySelectorAll('button.dropdown-toggle:not([aria-label]):not([aria-labelledby])').forEach(function (button) {
        button.setAttribute('aria-label', 'Open sample menu');
    });

    root.querySelectorAll('select:not([aria-label]):not([aria-labelledby])').forEach(function (select) {
        var group = select.closest('.ui-kit-grid');
        var label = group ? group.querySelector('.form-label') : null;
        var labelText = label ? label.textContent.trim() : '';
        select.setAttribute('aria-label', labelText || 'Sample select');
    });

    root.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([aria-label]):not([aria-labelledby])').forEach(function (input) {
        if (input.id && root.querySelector('label[for="' + input.id.replace(/"/g, '\\"') + '"]')) {
            return;
        }

        var group = input.closest('.ui-kit-grid');
        var label = group ? group.querySelector('.form-label') : null;
        var labelText = label ? label.textContent.trim() : '';
        input.setAttribute('aria-label', labelText || input.getAttribute('placeholder') || 'Sample input');
    });

    root.querySelectorAll('textarea:not([aria-label]):not([aria-labelledby])').forEach(function (textarea) {
        var group = textarea.closest('.ui-kit-grid');
        var label = group ? group.querySelector('.form-label') : null;
        var labelText = label ? label.textContent.trim() : '';
        textarea.setAttribute('aria-label', labelText || textarea.getAttribute('placeholder') || 'Sample textarea');
    });
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
