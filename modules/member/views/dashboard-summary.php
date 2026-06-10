<?php

$accountRow = is_array($dashboardRows[0] ?? null) ? $dashboardRows[0] : [];
$groupRow = is_array($dashboardRows[1] ?? null) ? $dashboardRows[1] : [];
?>

<div class="member-dashboard-summary admin-card card">
    <div class="member-dashboard-main">
        <p class="type-meta">회원 기반</p>
        <h2 class="type-section-title"><?php echo sr_e($dashboardSectionTitle); ?></h2>
        <strong class="type-display"><?php echo sr_e((string) ($accountRow['value'] ?? '0')); ?></strong>
        <span class="type-small"><?php echo sr_e((string) ($accountRow['label'] ?? '활성 회원')); ?></span>
        <small class="type-small"><?php echo sr_e((string) ($accountRow['detail'] ?? '최근 가입 0')); ?></small>
    </div>
    <div class="member-dashboard-side">
        <span class="type-small"><?php echo sr_e((string) ($groupRow['label'] ?? '회원 그룹')); ?></span>
        <strong class="type-section-title"><?php echo sr_e((string) ($groupRow['value'] ?? '0')); ?></strong>
        <small class="type-small"><?php echo sr_e((string) ($groupRow['detail'] ?? '활성 배정 0')); ?></small>
        <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-surface-default-soft">회원 보기</a>
    </div>
</div>
