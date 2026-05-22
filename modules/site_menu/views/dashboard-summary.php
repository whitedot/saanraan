<?php

$primaryRow = is_array($dashboardRows[0] ?? null) ? $dashboardRows[0] : [];
?>

<div class="site-menu-dashboard-summary admin-card card">
    <div class="site-menu-dashboard-copy">
        <p class="site-menu-dashboard-kicker">사이트 구조</p>
        <h2><?php echo sr_e($dashboardSectionTitle); ?></h2>
        <p><?php echo sr_e((string) ($primaryRow['detail'] ?? '메뉴 항목을 정리해 공개 화면 탐색을 구성합니다.')); ?></p>
    </div>
    <div class="site-menu-dashboard-meter">
        <span><?php echo sr_e((string) ($primaryRow['value'] ?? '0')); ?></span>
        <strong><?php echo sr_e((string) ($primaryRow['label'] ?? '활성 메뉴')); ?></strong>
    </div>
    <div class="site-menu-dashboard-actions">
        <a href="/admin/site-menus" class="btn btn-solid-primary">사이트 메뉴 관리</a>
    </div>
</div>
