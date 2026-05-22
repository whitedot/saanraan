<?php

$adminPageTitle = sr_t('admin::ui.admin.dashboard.e12d3646');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<div class="admin-dashboard-toolbar">
    <button type="button" class="btn btn-surface-default-soft" data-admin-dashboard-manager-toggle aria-expanded="false">
        <?php echo sr_material_icon_html('dashboard_customize'); ?>
        <span><?php echo sr_e(sr_t('admin::ui.text.22a12dff')); ?></span>
    </button>
</div>

<section class="admin-card card admin-dashboard-manager" data-admin-dashboard-manager hidden>
    <div class="admin-dashboard-manager-header">
        <h2><?php echo sr_e(sr_t('admin::ui.text.22a12dff')); ?></h2>
        <button type="button" class="btn btn-ghost-default btn-icon" data-admin-dashboard-manager-close aria-label="<?php echo sr_e(sr_t('admin::ui.close.a6d9b729')); ?>"><?php echo sr_material_icon_html('close'); ?></button>
    </div>
    <div class="admin-dashboard-manager-list" data-admin-dashboard-manager-list></div>
    <div class="admin-dashboard-manager-actions">
        <button type="button" class="btn btn-outline-default" data-admin-dashboard-visibility-reset><?php echo sr_e(sr_t('admin::ui.text.13ea0bb0')); ?></button>
    </div>
</section>

<div class="admin-dashboard-sections" data-admin-dashboard-sections>
<section class="admin-card admin-list-card card admin-dashboard-site-card admin-dashboard-section" data-admin-dashboard-section="site" data-admin-dashboard-label="<?php echo sr_e(sr_t('admin::ui.text.b2c8d45c')); ?>" data-admin-dashboard-default-visible="1">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('admin::ui.text.b2c8d45c')); ?></h2>
        <button type="button" class="admin-dashboard-section-handle" draggable="true" aria-label="<?php echo sr_e(sr_t('admin::ui.text.ab837ab1')); ?>"><?php echo sr_material_icon_html('apps', 'admin-dashboard-section-handle-icon'); ?></button>
    </div>
    <dl class="admin-dashboard-site-grid">
        <div>
            <dt><?php echo sr_e(sr_t('admin::ui.name.253d1510')); ?></dt>
            <dd><?php echo sr_e((string) ($site['name'] ?? '')); ?></dd>
        </div>
        <div>
            <dt><?php echo sr_e(sr_t('admin::ui.status.e10195a1')); ?></dt>
            <dd><?php echo sr_e(sr_admin_code_label((string) ($site['status'] ?? ''), 'site_status')); ?></dd>
        </div>
        <div>
            <dt><?php echo sr_e(sr_t('admin::ui.locale.c7cd39b4')); ?></dt>
            <dd><?php echo sr_e((string) ($site['default_locale'] ?? '')); ?></dd>
        </div>
    </dl>
</section>

<section class="admin-card admin-list-card card admin-list-form admin-dashboard-section" data-admin-dashboard-section="install_protection" data-admin-dashboard-label="<?php echo sr_e(sr_t('admin::ui.text.cc7c1e87')); ?>" data-admin-dashboard-default-visible="1">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('admin::ui.text.cc7c1e87')); ?></h2>
        <button type="button" class="admin-dashboard-section-handle" draggable="true" aria-label="<?php echo sr_e(sr_t('admin::ui.text.a61c9d55')); ?>"><?php echo sr_material_icon_html('apps', 'admin-dashboard-section-handle-icon'); ?></button>
    </div>
    <div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th><?php echo sr_e(sr_t('admin::ui.text.962f286b')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.status.e10195a1')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.text.fb77d92a')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.text.d211d97f')); ?></th>
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

<section class="admin-card admin-list-card card admin-list-form admin-dashboard-section" data-admin-dashboard-section="sensitive_settings" data-admin-dashboard-label="<?php echo sr_e(sr_t('admin::ui.settings.a22146cd')); ?>" data-admin-dashboard-default-visible="1">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('admin::ui.settings.a22146cd')); ?></h2>
        <button type="button" class="admin-dashboard-section-handle" draggable="true" aria-label="<?php echo sr_e(sr_t('admin::ui.settings.54c2c2b2')); ?>"><?php echo sr_material_icon_html('apps', 'admin-dashboard-section-handle-icon'); ?></button>
    </div>
    <div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th><?php echo sr_e(sr_t('admin::ui.text.962f286b')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.text.e37db65f')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.status.e10195a1')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.text.fb77d92a')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.edit.d3a98476')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.text.d211d97f')); ?></th>
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

<section class="admin-card admin-list-card card admin-list-form admin-dashboard-section" data-admin-dashboard-section="auth_runtime" data-admin-dashboard-label="<?php echo sr_e(sr_t('admin::ui.text.67a2e6fa')); ?>" data-admin-dashboard-default-visible="1">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('admin::ui.text.67a2e6fa')); ?></h2>
        <button type="button" class="admin-dashboard-section-handle" draggable="true" aria-label="<?php echo sr_e(sr_t('admin::ui.text.b9c1866e')); ?>"><?php echo sr_material_icon_html('apps', 'admin-dashboard-section-handle-icon'); ?></button>
    </div>
    <div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th><?php echo sr_e(sr_t('admin::ui.text.962f286b')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.status.e10195a1')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.text.fb77d92a')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.text.d211d97f')); ?></th>
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
    <section class="admin-card admin-list-card card admin-list-form admin-dashboard-section" data-admin-dashboard-section="recovery" data-admin-dashboard-label="<?php echo sr_e(sr_t('admin::ui.status.47a0c439')); ?>" data-admin-dashboard-default-visible="1">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('admin::ui.status.47a0c439')); ?></h2>
            <button type="button" class="admin-dashboard-section-handle" draggable="true" aria-label="<?php echo sr_e(sr_t('admin::ui.status.879d9629')); ?>"><?php echo sr_material_icon_html('apps', 'admin-dashboard-section-handle-icon'); ?></button>
        </div>

        <?php if ($recoveryMarkers !== []) { ?>
            <div class="table-wrapper">
            <table class="table">
                <thead class="ui-table-head">
                    <tr>
                        <th><?php echo sr_e(sr_t('admin::ui.text.962f286b')); ?></th>
                        <th><?php echo sr_e(sr_t('admin::ui.text.29ee1bb7')); ?></th>
                        <th><?php echo sr_e(sr_t('admin::ui.text.8c609deb')); ?></th>
                        <th><?php echo sr_e(sr_t('admin::ui.text.90dcdf19')); ?></th>
                        <th><?php echo sr_e(sr_t('admin::ui.text.50f30154')); ?></th>
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
                <?php echo sr_e(sr_t('admin::ui.text.d475b8ea')); ?> <?php echo sr_e((string) $moduleBackupSummary['count']); ?><?php echo sr_e(sr_t('admin::ui.text.a57ab057')); ?>
                <?php if ((string) $moduleBackupSummary['latest_name'] !== '') { ?>
                    <?php echo sr_e(sr_t('admin::ui.text.360a1fbb')); ?>
                    <?php echo sr_e((string) $moduleBackupSummary['latest_name']); ?>
                    <?php echo sr_e((string) $moduleBackupSummary['latest_modified_at']); ?>
                <?php } ?>
            </p>
        <?php } ?>
    </section>
<?php } ?>

<?php if (($moduleDashboardSections ?? []) !== []) { ?>
    <?php foreach ($moduleDashboardSections as $section) { ?>
        <section class="admin-dashboard-section admin-dashboard-module-section" data-admin-dashboard-section="module_<?php echo sr_e((string) $section['key']); ?>" data-admin-dashboard-label="<?php echo sr_e((string) $section['title']); ?>" data-admin-dashboard-default-visible="<?php echo !empty($section['default_visible']) ? '1' : '0'; ?>" data-admin-dashboard-layout="<?php echo sr_e((string) ($section['layout'] ?? 'table')); ?>"<?php echo !empty($section['default_visible']) ? '' : ' hidden'; ?>>
            <button type="button" class="admin-dashboard-section-handle admin-dashboard-module-section-handle" draggable="true" aria-label="<?php echo sr_e(sr_t('admin::ui.dashboard.section.move.35fe8045', ['title' => (string) $section['title']])); ?>"><?php echo sr_material_icon_html('apps', 'admin-dashboard-section-handle-icon'); ?></button>
            <?php echo sr_admin_dashboard_module_section_body($pdo, $section); ?>
        </section>
    <?php } ?>
<?php } ?>

<section class="admin-card admin-list-card card admin-list-form admin-dashboard-section" data-admin-dashboard-section="modules" data-admin-dashboard-label="<?php echo sr_e(sr_t('admin::ui.text.6d2d8bf4')); ?>" data-admin-dashboard-default-visible="1">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('admin::ui.text.6d2d8bf4')); ?></h2>
        <button type="button" class="admin-dashboard-section-handle" draggable="true" aria-label="<?php echo sr_e(sr_t('admin::ui.text.8c5bde76')); ?>"><?php echo sr_material_icon_html('apps', 'admin-dashboard-section-handle-icon'); ?></button>
    </div>
    <div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th><?php echo sr_e(sr_t('admin::ui.text.e37db65f')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.name.253d1510')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.text.002f73c3')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.status.e10195a1')); ?></th>
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
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
