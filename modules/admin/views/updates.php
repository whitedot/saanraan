<?php

$adminPageTitle = '업데이트';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($previousUpdateFailure !== null) { ?>
    <section>
        <h2>이전 업데이트 실패 기록</h2>
        <dl>
            <dt>단계</dt>
            <dd><?php echo sr_e((string) $previousUpdateFailure['stage']); ?></dd>
            <dt>범위</dt>
            <dd><?php echo sr_e((string) ($previousUpdateFailure['scope'] !== '' ? $previousUpdateFailure['scope'] : '-')); ?></dd>
            <dt>모듈</dt>
            <dd><?php echo sr_e((string) ($previousUpdateFailure['module_key'] !== '' ? $previousUpdateFailure['module_key'] : 'core')); ?></dd>
            <dt>버전</dt>
            <dd><?php echo sr_e((string) ($previousUpdateFailure['version'] !== '' ? $previousUpdateFailure['version'] : '-')); ?></dd>
            <dt>체크섬</dt>
            <dd><code><?php echo sr_e(substr((string) $previousUpdateFailure['checksum'], 0, 16)); ?></code></dd>
            <dt>기록 시각</dt>
            <dd><?php echo sr_e((string) ($previousUpdateFailure['recorded_at'] !== '' ? $previousUpdateFailure['recorded_at'] : '-')); ?></dd>
            <dt>오류 요약</dt>
            <dd><?php echo sr_e((string) ($previousUpdateFailure['message'] !== '' ? $previousUpdateFailure['message'] : '-')); ?></dd>
        </dl>
        <p>실패 원인과 백업 상태를 확인한 뒤 다시 업데이트를 실행하세요. 성공하면 이 기록은 자동으로 삭제됩니다.</p>
    </section>
<?php } ?>

<?php if ($moduleVersionDrifts !== []) { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">모듈 버전 차이</h2>
        </div>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th scope="col">모듈</th>
                    <th scope="col">설치 버전</th>
                    <th scope="col">코드 버전</th>
                    <th scope="col">상태</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($moduleVersionDrifts as $drift) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $drift['module_key']); ?></td>
                        <td><?php echo sr_e((string) $drift['installed_version']); ?></td>
                        <td><?php echo sr_e((string) $drift['code_version']); ?></td>
                        <td>
                            <?php if ((int) $drift['pending_update_count'] > 0) { ?>
                                <?php echo sr_e((string) $drift['pending_update_count']); ?>개 SQL 적용 필요
                            <?php } elseif ((string) $drift['state'] === 'code_newer') { ?>
                                파일 전용 업데이트 반영 가능
                            <?php } else { ?>
                                코드 버전이 설치 버전보다 낮음
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
        <?php if ($fileOnlyModuleVersionDrifts !== []) { ?>
            <form method="post" action="<?php echo sr_e(sr_url('/admin/updates')); ?>">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="sync_file_only_versions">
                <p>DB 변경이 없는 파일 업데이트입니다. SQL은 실행하지 않고, 설치 버전 기록만 현재 코드 버전에 맞춥니다.</p>
                <div class="admin-list-actions">
                    <button type="submit" class="btn btn-solid-primary">파일 전용 업데이트 반영</button>
                </div>
            </form>
        <?php } ?>
    </section>
<?php } ?>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">대기 중인 업데이트</h2>
    </div>
    <?php if ($pendingUpdates === []) { ?>
        <div class="table-wrapper">
        <table class="table">
            <tbody>
                <tr><td class="admin-empty-state">적용할 업데이트가 없습니다.</td></tr>
            </tbody>
        </table>
        </div>
    <?php } else { ?>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th scope="col">범위</th>
                    <th scope="col">버전</th>
                    <th scope="col">SQL 문</th>
                    <th scope="col">파일</th>
                    <th scope="col">체크섬</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingUpdates as $update) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $update['label']); ?></td>
                        <td><?php echo sr_e((string) $update['version']); ?></td>
                        <td>
                            <?php echo ((int) ($update['statements'] ?? 0) > 0)
                                ? sr_e((string) $update['statements'])
                                : '기록만'; ?>
                        </td>
                        <td><?php echo sr_e(str_replace(SR_ROOT . '/', '', (string) $update['path'])); ?></td>
                        <td><code><?php echo sr_e(substr((string) ($update['checksum'] ?? ''), 0, 16)); ?></code></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>

        <form method="post" action="<?php echo sr_e(sr_url('/admin/updates')); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="apply_updates">
            <p>
                <label class="admin-form-check form-label">
                    <input type="checkbox" name="backup_confirmed" value="1" class="form-checkbox" required>
                    <?php echo sr_admin_choice_label_html('DB와 파일 백업을 확인했습니다.'); ?>
                </label>
            </p>
            <div class="admin-list-actions">
                <button type="submit" class="btn btn-solid-primary">업데이트 적용</button>
            </div>
        </form>
    <?php } ?>
</section>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">적용된 스키마 버전</h2>
    </div>
    <?php if ($schemaVersions === []) { ?>
        <div class="table-wrapper">
        <table class="table">
            <tbody>
                <tr><td class="admin-empty-state">기록된 스키마 버전이 없습니다.</td></tr>
            </tbody>
        </table>
        </div>
    <?php } else { ?>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th scope="col">범위</th>
                    <th scope="col">모듈</th>
                    <th scope="col">버전</th>
                    <th scope="col">적용 시각</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schemaVersions as $version) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $version['scope']); ?></td>
                        <td><?php echo sr_e((string) ($version['module_key'] === '' ? 'core' : $version['module_key'])); ?></td>
                        <td><?php echo sr_e((string) $version['version']); ?></td>
                        <td><?php echo sr_e((string) $version['applied_at']); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    <?php } ?>
</section>

<?php if ($appliedUpdates !== []) { ?>
    <section>
        <h2>적용한 업데이트</h2>
        <ul>
            <?php foreach ($appliedUpdates as $update) { ?>
                <li>
                    <?php echo sr_e((string) $update['label'] . ' ' . (string) $update['version']); ?>
                    <code><?php echo sr_e(substr((string) ($update['checksum'] ?? ''), 0, 16)); ?></code>
                </li>
            <?php } ?>
        </ul>
    </section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
