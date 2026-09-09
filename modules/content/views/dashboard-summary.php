<?php

$summaryRows = [
    array_merge(['label' => '공개 콘텐츠', 'value' => '0', 'detail' => '초안 0', 'state' => 'default', 'emphasis' => 'primary'], is_array($dashboardRows[0] ?? null) ? $dashboardRows[0] : []),
    array_merge(['label' => '검토 대기', 'value' => '0', 'detail' => '작성자 신청 0', 'state' => 'default', 'emphasis' => 'default'], is_array($dashboardRows[1] ?? null) ? $dashboardRows[1] : []),
];
?>

<div class="card admin-dashboard-module-default">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e($dashboardSectionTitle); ?></h2>
    </div>
    <dl class="admin-dashboard-module-stats">
        <?php foreach ($summaryRows as $row) { ?>
            <div class="admin-dashboard-module-stat" data-admin-dashboard-state="<?php echo sr_e((string) $row['state']); ?>" data-admin-dashboard-emphasis="<?php echo sr_e((string) $row['emphasis']); ?>">
                <dt><?php echo sr_e((string) $row['label']); ?></dt>
                <dd><?php echo sr_e((string) $row['value']); ?></dd>
                <?php if ((string) $row['detail'] !== '') { ?>
                    <dd class="admin-dashboard-module-stat-detail"><?php echo sr_e((string) $row['detail']); ?></dd>
                <?php } ?>
            </div>
        <?php } ?>
    </dl>
    <div class="card-footer">
        <a href="<?php echo sr_e(sr_url('/admin/content')); ?>" class="btn btn-sm btn-ghost-light"><?php echo sr_e('콘텐츠 관리'); ?></a>
    </div>
</div>
