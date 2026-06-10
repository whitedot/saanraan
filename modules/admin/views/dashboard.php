<?php

$adminPageTitle = sr_t('admin::ui.admin.dashboard.e12d3646');
$adminDashboardSettingsModalId = 'admin-dashboard-settings-modal';
$adminDashboardSettingsModalTitleId = $adminDashboardSettingsModalId . '-label';
$adminPageTitleActionsHtml = '<button type="button" class="btn btn-solid-light" data-admin-dashboard-manager-toggle aria-haspopup="dialog" aria-controls="' . sr_e($adminDashboardSettingsModalId) . '" aria-expanded="false">'
    . sr_material_icon_html('dashboard_customize')
    . '<span>' . sr_e(sr_t('admin::ui.settings.115bced4')) . '</span>'
    . '</button>';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<div id="<?php echo sr_e($adminDashboardSettingsModalId); ?>" class="modal-overlay modal-overlay-fade overlay admin-dashboard-manager hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-modal="true" aria-hidden="true" aria-labelledby="<?php echo sr_e($adminDashboardSettingsModalTitleId); ?>" data-admin-dashboard-manager hidden>
    <div class="modal-dialog admin-dashboard-manager-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="<?php echo sr_e($adminDashboardSettingsModalTitleId); ?>" class="modal-title"><?php echo sr_e(sr_t('admin::ui.dashboard.settings.title.2df7f452')); ?></h3>
                <button type="button" class="modal-close" data-admin-dashboard-manager-close aria-label="<?php echo sr_e(sr_t('admin::ui.close.dashboard.settings.6d0816de')); ?>"><?php echo sr_material_icon_html('close'); ?></button>
            </div>
            <div class="modal-body admin-dashboard-manager-body">
                <p class="admin-dashboard-manager-help"><?php echo sr_e(sr_t('admin::ui.dashboard.settings.help.4c1b2f80')); ?></p>
                <div class="admin-dashboard-manager-list" data-admin-dashboard-manager-list></div>
            </div>
            <div class="modal-footer">
                <div class="admin-dashboard-manager-reset-actions">
                    <button type="button" class="btn btn-soft-warning modal-action" data-admin-dashboard-change-cancel hidden><?php echo sr_material_icon_html('undo'); ?><span><?php echo sr_e(sr_t('admin::ui.dashboard.settings.change_cancel.8d8f35d8')); ?></span></button>
                </div>
                <button type="button" class="btn btn-solid-light modal-action" data-admin-dashboard-manager-close><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
            </div>
        </div>
    </div>
</div>

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
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
