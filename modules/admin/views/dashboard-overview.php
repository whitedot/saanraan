<?php

$dashboardOverview = is_array($dashboardOverview ?? null) ? $dashboardOverview : [];
$dashboardGroupVisible = [];
foreach (['service', 'asset', 'activity'] as $role) {
    $dashboardGroupVisible[$role] = count(array_filter((array) ($dashboardOverview[$role] ?? []), static fn (array $item): bool => !empty($item['visible']))) > 0;
}
$dashboardTaskTotal = 0;
$dashboardTaskUnknown = 0;
$dashboardVisibleTasks = 0;
foreach ((array) ($dashboardOverview['task'] ?? []) as $item) {
    if (!$item['visible']) {
        continue;
    }
    $dashboardVisibleTasks++;
    if ($item['count'] === null) {
        $dashboardTaskUnknown++;
    } else {
        $dashboardTaskTotal += $item['count'];
    }
}
$dashboardTaskSummary = $dashboardTaskUnknown > 0
    ? number_format($dashboardTaskTotal) . '건 대기 · ' . $dashboardTaskUnknown . '개 항목 확인 필요'
    : ($dashboardTaskTotal > 0 ? number_format($dashboardTaskTotal) . '건 대기' : '대기 업무 없음');
if ($dashboardVisibleTasks === 0) {
    $dashboardTaskSummary = '표시 항목 없음';
}
?>

<div class="admin-dashboard-overview" role="region" aria-label="운영 현황">
    <div class="admin-dashboard-overview-heading">
        <div><h2>운영 현황</h2><p>처리할 업무를 확인하고, 서비스와 자산의 현재 상태를 살펴보세요.</p></div>
        <span class="admin-dashboard-overview-caption">페이지 조회 시점 기준</span>
    </div>
    <?php if (($recoveryMarkers ?? []) !== []) { ?>
        <div class="alert alert-warning">복구 상태를 확인할 항목이 있습니다. <a href="#admin-dashboard-details" data-admin-dashboard-show-recovery>복구 항목 보기</a></div>
    <?php } ?>

    <div class="admin-dashboard-service-strip" data-admin-overview-group<?php echo $dashboardGroupVisible['service'] ? '' : ' hidden'; ?>>
        <?php foreach ((array) ($dashboardOverview['service'] ?? []) as $item) { ?>
            <a class="card admin-dashboard-service-metric" href="<?php echo sr_e(sr_url($item['path'])); ?>" data-admin-overview-item="<?php echo sr_e($item['section_key']); ?>"<?php echo $item['visible'] ? '' : ' hidden'; ?>>
                <span class="admin-dashboard-overview-label"><?php echo sr_e($item['label']); ?></span>
                <strong><?php echo sr_e($item['count'] !== null ? number_format($item['count']) : $item['value']); ?></strong>
                <span class="admin-dashboard-overview-detail"><?php echo sr_e($item['detail']); ?></span>
                <?php echo sr_material_icon_html('arrow_outward', 'admin-dashboard-overview-arrow'); ?>
            </a>
        <?php } ?>
    </div>

    <div class="admin-dashboard-work-grid">
        <section class="card admin-dashboard-work-card">
            <div class="card-header">
                <div><h2 class="card-title">처리 대기</h2><p class="admin-dashboard-overview-caption">요청을 확인하고 해당 관리 화면에서 처리하세요.</p></div>
                <span class="badge <?php echo $dashboardTaskTotal > 0 || $dashboardTaskUnknown > 0 ? 'badge-soft-warning' : 'badge-solid-light'; ?>" data-admin-overview-task-summary aria-live="polite"><?php echo sr_e($dashboardTaskSummary); ?></span>
            </div>
            <div class="admin-dashboard-task-list">
                <?php foreach ((array) ($dashboardOverview['task'] ?? []) as $item) { ?>
                    <a class="admin-dashboard-task-row" href="<?php echo sr_e(sr_url($item['path'])); ?>" data-admin-overview-item="<?php echo sr_e($item['section_key']); ?>" data-admin-overview-task-count="<?php echo sr_e($item['count'] !== null ? (string) $item['count'] : ''); ?>"<?php echo $item['visible'] ? '' : ' hidden'; ?>>
                        <span class="admin-dashboard-task-copy"><span class="admin-dashboard-overview-caption"><?php echo sr_e($item['module_title']); ?></span><span class="admin-dashboard-task-label"><?php echo sr_e($item['label']); ?></span></span>
                        <span class="admin-dashboard-task-value"><strong><?php echo sr_e($item['count'] !== null ? number_format($item['count']) : '—'); ?></strong><span><?php echo $item['count'] !== null ? '건' : '확인 필요'; ?></span></span>
                        <?php echo sr_material_icon_html('chevron_right', 'admin-dashboard-overview-arrow'); ?>
                    </a>
                <?php } ?>
                <p class="admin-dashboard-overview-empty" data-admin-overview-task-empty<?php echo $dashboardVisibleTasks > 0 ? ' hidden' : ''; ?>>표시 중인 업무 항목이 없습니다. 대시보드 설정에서 모듈 표시 상태를 확인하세요.</p>
            </div>
        </section>
        <section class="card admin-dashboard-assets-card" data-admin-overview-group<?php echo $dashboardGroupVisible['asset'] ? '' : ' hidden'; ?>>
            <div class="card-header"><div><h2 class="card-title">자산 · 쿠폰</h2><p class="admin-dashboard-overview-caption">모듈별 보유·지급 현황</p></div></div>
            <div class="admin-dashboard-asset-list">
                <?php foreach ((array) ($dashboardOverview['asset'] ?? []) as $item) { ?>
                    <a class="admin-dashboard-asset-row" href="<?php echo sr_e(sr_url($item['path'])); ?>" data-admin-overview-item="<?php echo sr_e($item['section_key']); ?>"<?php echo $item['visible'] ? '' : ' hidden'; ?>>
                        <span class="admin-dashboard-asset-name"><?php echo sr_e($item['title']); ?></span>
                        <strong><?php echo sr_e($item['detail_value'] !== '' ? $item['detail_value'] : '집계 확인 필요'); ?></strong>
                        <span class="admin-dashboard-overview-detail"><?php echo sr_e($item['label']); ?> <?php echo sr_e($item['count'] !== null ? number_format($item['count']) : $item['value']); ?></span>
                        <?php echo sr_material_icon_html('chevron_right', 'admin-dashboard-overview-arrow'); ?>
                    </a>
                <?php } ?>
            </div>
        </section>
    </div>

    <section class="card admin-dashboard-activity-card" data-admin-overview-group<?php echo $dashboardGroupVisible['activity'] ? '' : ' hidden'; ?>>
        <div class="card-header"><h2 class="card-title">최근 7일 활동</h2><span class="admin-dashboard-overview-caption">거래와 사용 현황</span></div>
        <div class="admin-dashboard-activity-list">
            <?php foreach ((array) ($dashboardOverview['activity'] ?? []) as $item) { ?>
                <a class="admin-dashboard-activity-item" href="<?php echo sr_e(sr_url($item['path'])); ?>" data-admin-overview-item="<?php echo sr_e($item['section_key']); ?>"<?php echo $item['visible'] ? '' : ' hidden'; ?>>
                    <span class="admin-dashboard-overview-label"><?php echo sr_e($item['module_title']); ?></span>
                    <span class="admin-dashboard-activity-value"><strong><?php echo sr_e($item['count'] !== null ? number_format($item['count']) : $item['value']); ?></strong><?php echo sr_material_icon_html('arrow_outward', 'admin-dashboard-overview-arrow'); ?></span>
                    <span class="admin-dashboard-overview-detail"><?php echo sr_e($item['label']); ?></span>
                </a>
            <?php } ?>
        </div>
    </section>
</div>
