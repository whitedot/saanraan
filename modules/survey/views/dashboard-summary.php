<?php

$surveyRow = is_array($dashboardRows[0] ?? null) ? $dashboardRows[0] : [];
$responseRow = is_array($dashboardRows[1] ?? null) ? $dashboardRows[1] : [];
?>

<div class="survey-dashboard-summary card">
    <div class="survey-dashboard-title">
        <p class="type-meta">응답 수집</p>
        <h2 class="type-section-title"><?php echo sr_e($dashboardSectionTitle); ?></h2>
    </div>
    <div class="survey-dashboard-meter">
        <strong class="type-display"><?php echo sr_e((string) ($responseRow['value'] ?? '0')); ?></strong>
        <span class="type-small"><?php echo sr_e((string) ($responseRow['label'] ?? '제출 응답')); ?></span>
        <small class="type-small"><?php echo sr_e((string) ($responseRow['detail'] ?? '검토 필요 0')); ?></small>
    </div>
    <div class="survey-dashboard-list">
        <span class="type-small"><?php echo sr_e((string) ($surveyRow['label'] ?? '공개 설문')); ?></span>
        <strong class="type-section-title"><?php echo sr_e((string) ($surveyRow['value'] ?? '0')); ?></strong>
        <small class="type-small"><?php echo sr_e((string) ($surveyRow['detail'] ?? '초안 0')); ?></small>
        <a href="<?php echo sr_e(sr_url('/admin/surveys')); ?>" class="btn btn-surface-default-soft">설문 관리</a>
    </div>
</div>
