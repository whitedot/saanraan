<?php

$quizRow = is_array($dashboardRows[0] ?? null) ? $dashboardRows[0] : [];
$attemptRow = is_array($dashboardRows[1] ?? null) ? $dashboardRows[1] : [];
?>

<div class="quiz-dashboard-summary admin-card card">
    <div>
        <p class="type-meta">풀이 현황</p>
        <h2 class="type-section-title"><?php echo sr_e($dashboardSectionTitle); ?></h2>
    </div>
    <dl class="quiz-dashboard-grid">
        <div>
            <dt><?php echo sr_e((string) ($quizRow['label'] ?? '공개 퀴즈')); ?></dt>
            <dd><?php echo sr_e((string) ($quizRow['value'] ?? '0')); ?></dd>
            <p><?php echo sr_e((string) ($quizRow['detail'] ?? '초안 0')); ?></p>
        </div>
        <div>
            <dt><?php echo sr_e((string) ($attemptRow['label'] ?? '완료 시도')); ?></dt>
            <dd><?php echo sr_e((string) ($attemptRow['value'] ?? '0')); ?></dd>
            <p><?php echo sr_e((string) ($attemptRow['detail'] ?? '보상 대기 0')); ?></p>
        </div>
    </dl>
    <a href="<?php echo sr_e(sr_url('/admin/quiz')); ?>" class="btn btn-outline-default">퀴즈 관리</a>
</div>
