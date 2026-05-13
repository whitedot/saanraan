<?php

$adminPageTitle = '관리자 대시보드';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<section>
    <h2>사이트</h2>
    <dl>
        <dt>이름</dt>
        <dd><?php echo sr_e((string) ($site['name'] ?? '')); ?></dd>
        <dt>상태</dt>
        <dd><?php echo sr_e(sr_admin_code_label((string) ($site['status'] ?? ''), 'site_status')); ?></dd>
        <dt>기본 locale</dt>
        <dd><?php echo sr_e((string) ($site['default_locale'] ?? '')); ?></dd>
    </dl>
</section>

<section class="member-table-card admin-member-list-form">
    <div class="card-header">
        <h2 class="card-title">설치 보호</h2>
    </div>
    <div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th>항목</th>
                <th>상태</th>
                <th>판정</th>
                <th>상세</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($installProtectionSummary as $summary) { ?>
                <tr>
                    <td><?php echo sr_e((string) $summary['label']); ?></td>
                    <td><?php echo sr_e((string) $summary['value']); ?></td>
                    <td><?php echo sr_e((string) $summary['state']); ?></td>
                    <td><?php echo sr_e((string) $summary['detail']); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    </div>
</section>

<section class="member-table-card admin-member-list-form">
    <div class="card-header">
        <h2 class="card-title">고위험 설정</h2>
    </div>
    <div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th>항목</th>
                <th>키</th>
                <th>상태</th>
                <th>판정</th>
                <th>수정일</th>
                <th>상세</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sensitiveSettingSummary as $summary) { ?>
                <tr>
                    <td><?php echo sr_e((string) $summary['label']); ?></td>
                    <td><?php echo sr_e((string) $summary['setting_key']); ?></td>
                    <td><?php echo sr_e((string) $summary['value']); ?></td>
                    <td><?php echo sr_e((string) $summary['state']); ?></td>
                    <td><?php echo sr_e((string) $summary['updated_at']); ?></td>
                    <td><?php echo sr_e((string) $summary['detail']); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    </div>
</section>

<section class="member-table-card admin-member-list-form">
    <div class="card-header">
        <h2 class="card-title">인증 런타임</h2>
    </div>
    <div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th>항목</th>
                <th>상태</th>
                <th>판정</th>
                <th>상세</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($authRuntimeSummary as $summary) { ?>
                <tr>
                    <td><?php echo sr_e((string) $summary['label']); ?></td>
                    <td><?php echo sr_e((string) $summary['value']); ?></td>
                    <td><?php echo sr_e((string) $summary['state']); ?></td>
                    <td><?php echo sr_e((string) $summary['detail']); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    </div>
</section>

<?php if ($recoveryMarkers !== [] || (int) $moduleBackupSummary['count'] > 0) { ?>
    <section class="member-table-card admin-member-list-form">
        <div class="card-header">
            <h2 class="card-title">복구 상태</h2>
        </div>

        <?php if ($recoveryMarkers !== []) { ?>
            <div class="table-wrapper">
            <table class="table">
                <thead class="ui-table-head">
                    <tr>
                        <th>항목</th>
                        <th>단계</th>
                        <th>대상</th>
                        <th>기록 시각</th>
                        <th>요약</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recoveryMarkers as $marker) { ?>
                        <?php
                        $target = trim((string) ($marker['scope'] ?? '') . ' ' . (string) ($marker['module_key'] ?? '') . ' ' . (string) ($marker['version'] ?? ''));
                        ?>
                        <tr>
                            <td><?php echo sr_e((string) $marker['label']); ?></td>
                            <td><?php echo sr_e((string) $marker['stage']); ?></td>
                            <td><?php echo sr_e($target); ?></td>
                            <td><?php echo sr_e((string) $marker['recorded_at']); ?></td>
                            <td><?php echo sr_e((string) $marker['message']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            </div>
        <?php } ?>

        <?php if ((int) $moduleBackupSummary['count'] > 0) { ?>
            <p>
                모듈 백업 <?php echo sr_e((string) $moduleBackupSummary['count']); ?>개
                <?php if ((string) $moduleBackupSummary['latest_name'] !== '') { ?>
                    / 최근 백업:
                    <?php echo sr_e((string) $moduleBackupSummary['latest_name']); ?>
                    <?php echo sr_e((string) $moduleBackupSummary['latest_modified_at']); ?>
                <?php } ?>
            </p>
        <?php } ?>
    </section>
<?php } ?>

<?php if ($operationSummary !== []) { ?>
    <section class="member-table-card admin-member-list-form">
        <div class="card-header">
            <h2 class="card-title">운영 모듈</h2>
        </div>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>항목</th>
                    <th>주요 수치</th>
                    <th>상세</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($operationSummary as $summary) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $summary['label']); ?></td>
                        <td><?php echo sr_e((string) $summary['value']); ?></td>
                        <td><?php echo sr_e((string) $summary['detail']); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
<?php } ?>

<section class="member-table-card admin-member-list-form">
    <div class="card-header">
        <h2 class="card-title">모듈</h2>
    </div>
    <div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th>키</th>
                <th>이름</th>
                <th>버전</th>
                <th>상태</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($modules as $module) { ?>
                <tr>
                    <td><?php echo sr_e((string) $module['module_key']); ?></td>
                    <td><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></td>
                    <td><?php echo sr_e((string) $module['version']); ?></td>
                    <td><?php echo sr_e(sr_admin_code_label((string) $module['status'], 'module_status')); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    </div>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
