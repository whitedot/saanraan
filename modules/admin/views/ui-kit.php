<?php

$adminPageTitle = sr_t('admin::ui.admin.ui.kit.e8bf017c');
$adminPageSubtitle = '';
$adminContainerClass = 'admin-page-ui-kit';

$uiKitSamples = [
    'typography' => 'Typography',
    'ui-buttons' => 'Buttons',
    'ui-cards' => 'Cards',
    'ui-alerts' => 'Alerts',
    'ui-badges' => 'Badges',
    'ui-modals' => 'Modals',
    'ui-dropdowns' => 'Dropdowns',
    'ui-dropdown-menus' => 'Dropdown Menus',
    'member-avatars' => 'Member Avatars',
    'ui-tabs' => 'Tabs',
    'form-elements' => 'Form Elements',
    'form-validation' => 'Form Validation',
    'tables-static' => 'Tables',
];

$publicUiKitLinks = [
    [
        'label' => '초기 UI Kit',
        'path' => '/ui-kit',
    ],
];
if (sr_module_enabled($pdo, 'content') && is_file(SR_ROOT . '/modules/content/actions/ui-kit.php')) {
    $publicUiKitLinks[] = [
        'label' => '콘텐츠 UI Kit',
        'path' => '/content/ui-kit',
    ];
}
if (sr_module_enabled($pdo, 'community') && is_file(SR_ROOT . '/modules/community/actions/ui-kit.php')) {
    $publicUiKitLinks[] = [
        'label' => '커뮤니티 UI Kit',
        'path' => '/community/ui-kit',
    ];
}
if (sr_module_enabled($pdo, 'quiz') && is_file(SR_ROOT . '/modules/quiz/actions/ui-kit.php')) {
    $publicUiKitLinks[] = [
        'label' => '퀴즈·테스트 UI Kit',
        'path' => '/quiz/ui-kit',
    ];
}
if (sr_module_enabled($pdo, 'survey') && is_file(SR_ROOT . '/modules/survey/actions/ui-kit.php')) {
    $publicUiKitLinks[] = [
        'label' => '설문·여론조사 UI Kit',
        'path' => '/survey/ui-kit',
    ];
}

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<link rel="stylesheet" href="<?php echo sr_e(sr_admin_asset_url('/modules/admin/assets/ui-kit-layout.css')); ?>">

<section class="card">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('admin::ui.text.1f36938c')); ?></h2>
        <nav class="ui-kit-cluster ui-kit-wrap ui-kit-gap-2" aria-label="사용자 화면 UI Kit">
            <?php foreach ($publicUiKitLinks as $publicUiKitLink) { ?>
                <a href="<?php echo sr_e(sr_url((string) $publicUiKitLink['path'])); ?>" class="btn btn-sm btn-outline-secondary"><?php echo sr_e((string) $publicUiKitLink['label']); ?></a>
            <?php } ?>
        </nav>
    </div>
    <div class="card-body">
        <p class="card-subtitle"><?php echo sr_e(sr_t('admin::ui.admin.ui.49666d14')); ?></p>
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

<script>
(function () {
    var root = document.querySelector('.admin-ui-kit-samples');
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
