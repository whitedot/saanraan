<?php

$adminPageTitle = $adminPageTitle ?? '관리자';
$seo = [
    'title' => $adminPageTitle,
    'robots' => 'noindex, nofollow',
];
$adminNavigationGroups = isset($pdo) && $pdo instanceof PDO ? sr_admin_navigation_groups($pdo) : [];
?>
<!doctype html>
<html lang="<?php echo sr_e(sr_locale()); ?>" data-color-scheme="<?php echo sr_e(sr_color_scheme($site ?? null)); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo sr_seo_tags($seo, $site ?? null); ?>
    <?php echo sr_stylesheet_tag(); ?>
</head>
<body>
    <header>
        <h1><?php echo sr_e($adminPageTitle); ?></h1>
        <nav>
            <?php foreach ($adminNavigationGroups as $adminNavigationGroup) { ?>
                <div>
                    <strong><?php echo sr_e((string) $adminNavigationGroup['label']); ?></strong>
                    <?php $adminNavigationModuleGroups = isset($adminNavigationGroup['module_groups']) && is_array($adminNavigationGroup['module_groups']) ? $adminNavigationGroup['module_groups'] : []; ?>
                    <?php foreach ($adminNavigationModuleGroups as $adminNavigationModuleGroup) { ?>
                        <details>
                            <summary><?php echo sr_e((string) $adminNavigationModuleGroup['label']); ?></summary>
                            <?php foreach ($adminNavigationModuleGroup['items'] as $adminNavigationItem) { ?>
                                <a href="<?php echo sr_e(sr_url((string) $adminNavigationItem['path'])); ?>"><?php echo sr_e($adminNavigationItem['label']); ?></a>
                            <?php } ?>
                        </details>
                    <?php } ?>
                </div>
            <?php } ?>
            <form method="post" action="<?php echo sr_e(sr_url('/logout')); ?>" style="display:inline">
                <?php echo sr_csrf_field(); ?>
                <button type="submit">로그아웃</button>
            </form>
        </nav>
    </header>
    <main>
