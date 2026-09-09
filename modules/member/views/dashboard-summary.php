<?php

$summaryRows = [
    array_merge(['label' => '활성 회원', 'value' => '0', 'detail' => '최근 가입 0', 'state' => 'default', 'emphasis' => 'primary'], is_array($dashboardRows[0] ?? null) ? $dashboardRows[0] : []),
    array_merge(['label' => '회원 그룹', 'value' => '0', 'detail' => '활성 배정 0', 'state' => 'default', 'emphasis' => 'default'], is_array($dashboardRows[1] ?? null) ? $dashboardRows[1] : []),
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
        <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-sm btn-ghost-light"><?php echo sr_e('회원 보기'); ?></a>
    </div>
</div>
