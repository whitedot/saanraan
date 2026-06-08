<?php

$adminPageTitle = sr_t('admin::ui.text.9ea9bd59');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($previousUpdateFailure !== null) { ?>
    <section>
        <h2><?php echo sr_e(sr_t('admin::ui.text.76d88be0')); ?></h2>
        <dl>
            <dt><?php echo sr_e(sr_t('admin::ui.text.29ee1bb7')); ?></dt>
            <dd><?php echo sr_e((string) $previousUpdateFailure['stage']); ?></dd>
            <dt><?php echo sr_e(sr_t('admin::ui.text.2281025b')); ?></dt>
            <dd><?php echo sr_e((string) ($previousUpdateFailure['scope'] !== '' ? $previousUpdateFailure['scope'] : '-')); ?></dd>
            <dt><?php echo sr_e(sr_t('admin::ui.text.6d2d8bf4')); ?></dt>
            <dd><?php echo sr_e((string) ($previousUpdateFailure['module_key'] !== '' ? $previousUpdateFailure['module_key'] : 'core')); ?></dd>
            <dt><?php echo sr_e(sr_t('admin::ui.text.002f73c3')); ?></dt>
            <dd><?php echo sr_e((string) ($previousUpdateFailure['version'] !== '' ? $previousUpdateFailure['version'] : '-')); ?></dd>
            <dt><?php echo sr_e(sr_t('admin::ui.text.374c62d4')); ?></dt>
            <dd><code><?php echo sr_e(substr((string) $previousUpdateFailure['checksum'], 0, 16)); ?></code></dd>
            <dt><?php echo sr_e(sr_t('admin::ui.text.90dcdf19')); ?></dt>
            <dd><?php echo sr_e((string) ($previousUpdateFailure['recorded_at'] !== '' ? $previousUpdateFailure['recorded_at'] : '-')); ?></dd>
            <dt><?php echo sr_e(sr_t('admin::ui.text.22a68c78')); ?></dt>
            <dd><?php echo sr_e((string) ($previousUpdateFailure['message'] !== '' ? $previousUpdateFailure['message'] : '-')); ?></dd>
        </dl>
        <p><?php echo sr_e(sr_t('admin::ui.status.delete.d862522f')); ?></p>
    </section>
<?php } ?>

<?php if ($moduleVersionDrifts !== []) { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('admin::ui.text.5fec9b05')); ?></h2>
        </div>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th scope="col"><?php echo sr_e(sr_t('admin::ui.text.6d2d8bf4')); ?></th>
                    <th scope="col"><?php echo sr_e(sr_t('admin::ui.text.364d60db')); ?></th>
                    <th scope="col"><?php echo sr_e(sr_t('admin::ui.text.cf4479f3')); ?></th>
                    <th scope="col"><?php echo sr_e(sr_t('admin::ui.status.e10195a1')); ?></th>
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
                                <?php echo sr_e((string) $drift['pending_update_count']); ?><?php echo sr_e(sr_t('admin::ui.sql.49c88d1c')); ?>
                            <?php } elseif ((string) $drift['state'] === 'code_newer') { ?>
                                <?php echo sr_e(sr_t('admin::ui.text.710ee67a')); ?>
                            <?php } else { ?>
                                <?php echo sr_e(sr_t('admin::ui.text.b9f30bfb')); ?>
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
                <p><?php echo sr_e(sr_t('admin::ui.db.74be7570')); ?></p>
                <div class="admin-list-actions">
                    <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('admin::ui.text.55c2fb85')); ?></button>
                </div>
            </form>
        <?php } ?>
    </section>
<?php } ?>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('admin::ui.text.6b574fee')); ?></h2>
    </div>
    <div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th scope="col"><?php echo sr_e(sr_t('admin::ui.text.2281025b')); ?></th>
                <th scope="col"><?php echo sr_e(sr_t('admin::ui.text.002f73c3')); ?></th>
                <th scope="col"><?php echo sr_e(sr_t('admin::ui.sql.566cef5d')); ?></th>
                <th scope="col"><?php echo sr_e(sr_t('admin::ui.text.0c8354d0')); ?></th>
                <th scope="col"><?php echo sr_e(sr_t('admin::ui.text.374c62d4')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($pendingUpdates === []) { ?>
                <tr><td colspan="5" class="admin-empty-state"><?php echo sr_e(sr_t('admin::ui.text.ed61c015')); ?></td></tr>
            <?php } else { ?>
                <?php foreach ($pendingUpdates as $update) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $update['label']); ?></td>
                        <td><?php echo sr_e((string) $update['version']); ?></td>
                        <td>
                            <?php echo ((int) ($update['statements'] ?? 0) > 0)
                                ? sr_e((string) $update['statements'])
                                : sr_e(sr_t('admin::ui.text.03f022d3')); ?>
                        </td>
                        <td><?php echo sr_e(str_replace(SR_ROOT . '/', '', (string) $update['path'])); ?></td>
                        <td><code><?php echo sr_e(substr((string) ($update['checksum'] ?? ''), 0, 16)); ?></code></td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
    </div>

    <?php if ($pendingUpdates !== []) { ?>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/updates')); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="apply_updates">
            <p>
                <span class="filtering-toggle-group admin-checkbox-toggle-group" role="group">
                    <span class="filtering-toggle-item">
                        <input id="modules_admin_updates_backup_confirmed" type="checkbox" name="backup_confirmed" value="1" class="form-choice-toggle-input sr-only" required>
                        <label for="modules_admin_updates_backup_confirmed" class="btn btn-choice-light"><?php echo sr_admin_choice_label_html(sr_t('admin::ui.text.863749e6')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                    </span>
                </span>
            </p>
            <div class="admin-list-actions">
                <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('admin::ui.text.3da662af')); ?></button>
            </div>
        </form>
    <?php } ?>
</section>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('admin::ui.text.488e2350')); ?></h2>
    </div>
    <div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th scope="col"><?php echo sr_e(sr_t('admin::ui.text.2281025b')); ?></th>
                <th scope="col"><?php echo sr_e(sr_t('admin::ui.text.6d2d8bf4')); ?></th>
                <th scope="col"><?php echo sr_e(sr_t('admin::ui.text.002f73c3')); ?></th>
                <th scope="col"><?php echo sr_e(sr_t('admin::ui.text.aacb8392')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($schemaVersions === []) { ?>
                <tr><td colspan="4" class="admin-empty-state"><?php echo sr_e(sr_t('admin::ui.text.e4626e28')); ?></td></tr>
            <?php } else { ?>
                <?php foreach ($schemaVersions as $version) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $version['scope']); ?></td>
                        <td><?php echo sr_e((string) ($version['module_key'] === '' ? 'core' : $version['module_key'])); ?></td>
                        <td><?php echo sr_e((string) $version['version']); ?></td>
                        <td><?php echo sr_e((string) $version['applied_at']); ?></td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
    </div>
</section>

<?php if ($appliedUpdates !== []) { ?>
    <section>
        <h2><?php echo sr_e(sr_t('admin::ui.text.3ac6c97f')); ?></h2>
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
