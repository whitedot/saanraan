<?php

$contentRow = is_array($dashboardRows[0] ?? null) ? $dashboardRows[0] : [];
$reviewRow = is_array($dashboardRows[1] ?? null) ? $dashboardRows[1] : [];
?>

<div class="content-dashboard-summary admin-card card">
    <div class="content-dashboard-head">
        <p class="type-meta">서비스 콘텐츠</p>
        <h2 class="type-section-title"><?php echo sr_e($dashboardSectionTitle); ?></h2>
    </div>
    <div class="content-dashboard-count">
        <strong class="type-display"><?php echo sr_e((string) ($contentRow['value'] ?? '0')); ?></strong>
        <span class="type-small"><?php echo sr_e((string) ($contentRow['label'] ?? '공개 콘텐츠')); ?></span>
        <small class="type-small"><?php echo sr_e((string) ($contentRow['detail'] ?? '초안 0')); ?></small>
    </div>
    <div class="content-dashboard-review">
        <span class="type-small"><?php echo sr_e((string) ($reviewRow['label'] ?? '검토 대기')); ?></span>
        <strong class="type-section-title"><?php echo sr_e((string) ($reviewRow['value'] ?? '0')); ?></strong>
        <small class="type-small"><?php echo sr_e((string) ($reviewRow['detail'] ?? '작성자 신청 0')); ?></small>
        <a href="<?php echo sr_e(sr_url('/admin/content')); ?>" class="btn btn-surface-default-soft">콘텐츠 관리</a>
    </div>
</div>
