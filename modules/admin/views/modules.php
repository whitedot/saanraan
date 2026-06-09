<?php

$adminPageTitle = sr_t('admin::ui.text.1ab0e3a9');
$adminPageSubtitle = sr_t('admin::ui.status.130b725f');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-section-heading">
    <h2><?php echo sr_e(sr_t('admin::ui.text.2d01e6b6')); ?></h2>
    <a class="btn btn-solid-light" href="<?php echo sr_e(sr_url($showFoundationModules ? '/admin/modules' : '/admin/modules?show_foundations=1')); ?>">
        <?php echo sr_e($showFoundationModules ? '기반 모듈 숨기기' : '기반 모듈 보기'); ?>
    </a>
    <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="module-upload-modal" data-overlay="#module-upload-modal" hidden>
        <?php echo sr_material_icon_html('upload'); ?>
        <span><?php echo sr_e(sr_t('admin::ui.zip.580feeda')); ?></span>
    </button>
</div>
<?php echo sr_admin_pagination_summary_html($installableModulePagination); ?>
<?php if ($installableModules === []) { ?>
    <section class="admin-card admin-list-card card">
        <p><?php echo sr_e(sr_t('admin::ui.text.228a1836')); ?></p>
    </section>
<?php } else { ?>
    <div class="admin-module-card-grid admin-module-installable-grid">
        <?php foreach ($installableModules as $module) { ?>
            <?php $moduleKey = (string) $module['module_key']; ?>
            <?php $moduleModalId = 'installable-module-detail-' . $moduleKey; ?>
            <?php $moduleInstallModalId = 'installable-module-install-' . $moduleKey; ?>
            <?php $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
            <?php $canInstall = $moduleErrors === []; ?>
            <article class="admin-card admin-module-card card admin-list-form">
                <div class="admin-module-card-header">
                    <div>
                        <h3><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></h3>
                        <p><?php echo sr_e($moduleKey); ?> · <?php echo sr_e(sr_admin_code_label((string) $module['type'], 'module_type')); ?></p>
                    </div>
                    <span class="admin-status <?php echo $canInstall ? 'is-normal' : 'is-blocked'; ?>">
                        <?php echo sr_e($canInstall ? sr_t('admin::ui.text.24a5a830') : sr_t('admin::ui.text.944c1818')); ?>
                    </span>
                </div>
                <div class="admin-module-card-body">
                    <dl class="admin-module-card-meta">
                        <div>
                            <dt><?php echo sr_e(sr_t('admin::ui.text.cf4479f3')); ?></dt>
                            <dd><?php echo sr_e((string) ($module['version'] !== '' ? $module['version'] : '-')); ?></dd>
                        </div>
                        <div>
                            <dt><?php echo sr_e(sr_t('admin::ui.text.77fbead1')); ?></dt>
                            <dd>
                                <?php echo sr_e((string) ($module['lifecycle_label'] ?? sr_t('admin::ui.text.4e4764fd'))); ?>
                                <span><?php echo sr_e((string) ($module['lifecycle_action'] ?? sr_t('admin::ui.text.24a5a830'))); ?></span>
                            </dd>
                        </div>
                        <div>
                            <dt><?php echo sr_e(sr_t('admin::ui.saanraan.b9033156')); ?></dt>
                            <dd><?php echo sr_e((string) ($module['saanraan_min_version'] !== '' ? $module['saanraan_min_version'] : '-')); ?></dd>
                        </div>
                        <div>
                            <dt><?php echo sr_e(sr_t('admin::ui.saanraan.959fc83c')); ?></dt>
                            <dd><?php echo sr_e((string) ($module['saanraan_tested_with'] !== '' ? $module['saanraan_tested_with'] : '-')); ?></dd>
                        </div>
                    </dl>
                    <p><?php echo sr_e((string) ($module['description'] !== '' ? sr_admin_module_description_label((string) $module['description']) : '-')); ?></p>
                    <?php if ($moduleErrors !== []) { ?>
                        <p class="admin-module-card-warning"><?php echo sr_e(sr_t('admin::ui.text.afad906d')); ?></p>
                    <?php } ?>
                </div>
                <div class="admin-module-card-actions">
                    <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($moduleModalId); ?>" data-overlay="#<?php echo sr_e($moduleModalId); ?>">
                        <?php echo sr_e(sr_t('admin::ui.text.9caeb34c')); ?>
                    </button>
                    <?php if ($canInstall) { ?>
                        <button type="button" class="btn btn-sm btn-solid-primary" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($moduleInstallModalId); ?>" data-overlay="#<?php echo sr_e($moduleInstallModalId); ?>">
                            <?php echo sr_e(sr_t('admin::ui.text.6a28baa5')); ?>
                        </button>
                    <?php } else { ?>
                        <span class="admin-module-card-action-note"><?php echo sr_e(sr_t('admin::ui.text.b4052951')); ?></span>
                    <?php } ?>
                </div>
            </article>
            <div id="<?php echo sr_e($moduleModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($moduleModalId); ?>-label">
                <div class="modal-dialog modal-dialog-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 id="<?php echo sr_e($moduleModalId); ?>-label" class="modal-title"><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?> <?php echo sr_e(sr_t('admin::ui.text.d6a8c525')); ?></h3>
                            <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($moduleModalId); ?>">
                                <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                            </button>
                        </div>
                        <div class="modal-body">
                            <dl class="admin-module-detail-list">
                                <dt><?php echo sr_e(sr_t('admin::ui.text.e37db65f')); ?></dt>
                                <dd><?php echo sr_e($moduleKey); ?></dd>
                                <dt><?php echo sr_e(sr_t('admin::ui.name.253d1510')); ?></dt>
                                <dd><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></dd>
                                <dt><?php echo sr_e(sr_t('admin::ui.text.5cf2792b')); ?></dt>
                                <dd><?php echo sr_e(sr_admin_code_label((string) $module['type'], 'module_type')); ?></dd>
                                <dt><?php echo sr_e(sr_t('admin::ui.text.cf4479f3')); ?></dt>
                                <dd><?php echo sr_e((string) ($module['version'] !== '' ? $module['version'] : '-')); ?></dd>
                                <dt><?php echo sr_e(sr_t('admin::ui.text.77fbead1')); ?></dt>
                                <dd><?php echo sr_e((string) ($module['lifecycle_label'] ?? sr_t('admin::ui.text.4e4764fd'))); ?> · <?php echo sr_e((string) ($module['lifecycle_action'] ?? sr_t('admin::ui.text.24a5a830'))); ?></dd>
                                <dt><?php echo sr_e(sr_t('admin::ui.saanraan.b9033156')); ?></dt>
                                <dd><?php echo sr_e((string) ($module['saanraan_min_version'] !== '' ? $module['saanraan_min_version'] : '-')); ?></dd>
                                <dt><?php echo sr_e(sr_t('admin::ui.saanraan.959fc83c')); ?></dt>
                                <dd><?php echo sr_e((string) ($module['saanraan_tested_with'] !== '' ? $module['saanraan_tested_with'] : '-')); ?></dd>
                                <dt><?php echo sr_e(sr_t('admin::ui.text.38de236e')); ?></dt>
                                <dd><?php echo sr_e((string) (($module['saanraan_module_contract'] ?? '') !== '' ? $module['saanraan_module_contract'] : '-')); ?></dd>
                                <dt><?php echo sr_e(sr_t('admin::ui.text.9974018f')); ?></dt>
                                <dd>
                                    <?php if ($moduleErrors === []) { ?>
                                        -
                                    <?php } else { ?>
                                        <ul>
                                            <?php foreach ($moduleErrors as $moduleError) { ?>
                                                <li><?php echo sr_e((string) $moduleError); ?></li>
                                            <?php } ?>
                                        </ul>
                                    <?php } ?>
                                </dd>
                                <dt><?php echo sr_e(sr_t('admin::ui.text.8c3f651d')); ?></dt>
                                <dd><?php echo sr_e((string) ($module['description'] !== '' ? sr_admin_module_description_label((string) $module['description']) : '-')); ?></dd>
                            </dl>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($moduleModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($canInstall) { ?>
                <div id="<?php echo sr_e($moduleInstallModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($moduleInstallModalId); ?>-label">
                    <div class="modal-dialog-sm admin-module-status-dialog">
                        <div class="modal-content">
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="admin-form ui-form-theme">
                                <div class="modal-header">
                                    <h3 id="<?php echo sr_e($moduleInstallModalId); ?>-label" class="modal-title"><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?> <?php echo sr_e(sr_t('admin::ui.text.6a28baa5')); ?></h3>
                                    <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($moduleInstallModalId); ?>">
                                        <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="install">
                                    <input type="hidden" name="module_key" value="<?php echo sr_e($moduleKey); ?>">
                                    <div class="admin-form-row">
                                        <span class="form-label"><?php echo sr_e(sr_t('admin::ui.text.6d2d8bf4')); ?></span>
                                        <div class="admin-form-field">
                                            <strong><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></strong>
                                            <span class="admin-form-help"><?php echo sr_e($moduleKey); ?></span>
                                        </div>
                                    </div>
                                    <div class="admin-form-row">
                                        <label class="form-label" for="<?php echo sr_e($moduleInstallModalId); ?>-status"><?php echo sr_e(sr_t('admin::ui.status.e19e9f32')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                        <div class="admin-form-field">
                                            <select id="<?php echo sr_e($moduleInstallModalId); ?>-status" name="status" class="form-select" data-overlay-focus>
                                                <?php foreach ($allowedInstallStatuses as $status) { ?>
                                                    <option value="<?php echo sr_e($status); ?>"<?php echo $status === 'enabled' ? ' selected' : ''; ?>>
                                                        <?php echo sr_e(sr_admin_code_label($status, 'module_status')); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($moduleInstallModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                                    <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('admin::ui.text.6a28baa5')); ?></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
    <?php echo sr_admin_pagination_html($installableModulePagination, '설치 가능 모듈 목록 페이지'); ?>
<?php } ?>

<div class="admin-section-heading">
    <h2><?php echo sr_e(sr_t('admin::ui.text.9c3c31da')); ?></h2>
</div>
<?php echo sr_admin_pagination_summary_html($modulePagination); ?>
<div class="admin-module-card-grid">
    <?php foreach ($modules as $module) { ?>
        <?php $moduleKey = (string) $module['module_key']; ?>
        <?php $moduleModalId = 'module-detail-' . $moduleKey; ?>
        <?php $moduleStatusModalId = 'module-status-' . $moduleKey; ?>
        <?php $isRequired = in_array($moduleKey, $requiredModules, true); ?>
        <?php $foundationDependents = isset($module['foundation_dependents']) && is_array($module['foundation_dependents']) ? $module['foundation_dependents'] : []; ?>
        <?php $statusLocked = $isRequired || $foundationDependents !== []; ?>
        <?php $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
        <?php $moduleStatus = (string) $module['status']; ?>
        <?php $moduleStatusClass = $moduleStatus === 'enabled' ? 'is-normal' : (in_array($moduleStatus, ['failed', 'installing'], true) ? 'is-left' : 'is-blocked'); ?>
        <article class="admin-card admin-module-card card admin-list-form">
            <div class="admin-module-card-header">
                <div>
                    <h3><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></h3>
                    <p><?php echo sr_e($moduleKey); ?> · <?php echo sr_e(sr_admin_code_label((string) ($module['code_type'] ?? 'module'), 'module_type')); ?></p>
                </div>
                <span class="admin-status <?php echo sr_e($moduleStatusClass); ?>">
                    <?php echo sr_e(sr_admin_code_label($moduleStatus, 'module_status')); ?>
                </span>
            </div>
            <div class="admin-module-card-body">
                <dl class="admin-module-card-meta">
                    <div>
                        <dt><?php echo sr_e(sr_t('admin::ui.text.364d60db')); ?></dt>
                        <dd><?php echo sr_e((string) $module['version']); ?></dd>
                    </div>
                    <div>
                        <dt><?php echo sr_e(sr_t('admin::ui.text.cf4479f3')); ?></dt>
                        <dd><?php echo sr_e((string) ($module['code_version'] !== '' ? $module['code_version'] : '-')); ?></dd>
                    </div>
                    <div>
                        <dt><?php echo sr_e(sr_t('admin::ui.text.77fbead1')); ?></dt>
                        <dd>
                            <?php echo sr_e((string) ($module['lifecycle_label'] ?? sr_t('admin::ui.status.264d7d27'))); ?>
                            <span><?php echo sr_e((string) ($module['lifecycle_action'] ?? sr_t('admin::ui.status.77578d77'))); ?></span>
                        </dd>
                    </div>
                    <div>
                        <dt><?php echo sr_e(sr_t('admin::ui.text.9ea9bd59')); ?></dt>
                        <dd>
                            <?php if ((int) ($module['pending_update_count'] ?? 0) > 0) { ?>
                                <a href="<?php echo sr_e(sr_url('/admin/updates')); ?>"><?php echo sr_e((string) $module['pending_update_count']); ?><?php echo sr_e(sr_t('admin::ui.sql.ff779501')); ?></a>
                            <?php } elseif (($module['version_state'] ?? '') === 'code_newer') { ?>
                                <?php if ($canManageModuleSources && $moduleSourcesEnabled) { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="admin-module-sync-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="sync_module_version">
                                        <input type="hidden" name="module_key" value="<?php echo sr_e($moduleKey); ?>">
                                        <label for="modules_admin_modules_owner_password">
                                            <span class="sr-only"><?php echo sr_e(sr_t('admin::ui.password.6fda7f23')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></span>
                                            <input id="modules_admin_modules_owner_password" type="password" name="owner_password" class="form-input" autocomplete="current-password" required placeholder="<?php echo sr_e(sr_t('admin::ui.password.6fda7f23')); ?>">
                                        </label>
                                        <button type="submit" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('admin::ui.text.d39a4955')); ?></button>
                                    </form>
                                <?php } elseif ($canManageModuleSources) { ?>
                                    <?php echo sr_e(sr_t('admin::ui.text.8a6736c0')); ?>
                                <?php } else { ?>
                                    <?php echo sr_e(sr_t('admin::ui.text.dfee523a')); ?>
                                <?php } ?>
                            <?php } elseif (($module['version_state'] ?? '') === 'code_older') { ?>
                                <?php echo sr_e(sr_t('admin::ui.text.be6a1a14')); ?>
                            <?php } else { ?>
                                -
                            <?php } ?>
                        </dd>
                    </div>
                </dl>
                <p><?php echo sr_e((string) ($module['description'] !== '' ? sr_admin_module_description_label((string) $module['description']) : '-')); ?></p>
                <?php if ($moduleStatus === 'enabled' && $moduleErrors !== []) { ?>
                    <p class="admin-module-card-warning"><?php echo sr_e(sr_t('admin::ui.text.2de2b3ad')); ?></p>
                <?php } ?>
            </div>
            <div class="admin-module-card-actions">
                <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($moduleModalId); ?>" data-overlay="#<?php echo sr_e($moduleModalId); ?>">
                    <?php echo sr_e(sr_t('admin::ui.text.9caeb34c')); ?>
                </button>
                <?php if (in_array($moduleStatus, ['failed', 'installing'], true)) { ?>
                    <details class="admin-inline-edit-details">
                        <summary class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('admin::ui.text.098a7941')); ?></summary>
                        <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="admin-inline-edit-form">
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="intent" value="install">
                            <input type="hidden" name="module_key" value="<?php echo sr_e($moduleKey); ?>">
                            <label for="modules_admin_modules_status_2">
                                <span><?php echo sr_e(sr_t('admin::ui.status.e19e9f32')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></span>
                                <select id="modules_admin_modules_status_2" name="status" class="form-select">
                                    <?php foreach ($allowedInstallStatuses as $status) { ?>
                                        <option value="<?php echo sr_e($status); ?>"<?php echo $status === 'enabled' ? ' selected' : ''; ?>>
                                            <?php echo sr_e(sr_admin_code_label($status, 'module_status')); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </label>
                            <button type="submit" class="btn btn-sm btn-solid-primary"><?php echo sr_e(sr_t('admin::ui.text.098a7941')); ?></button>
                        </form>
                    </details>
                <?php } else { ?>
                    <button type="button" class="btn btn-sm btn-solid-primary"<?php echo $statusLocked ? ' disabled aria-disabled="true"' : ''; ?> aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($moduleStatusModalId); ?>" data-overlay="#<?php echo sr_e($moduleStatusModalId); ?>">
                        <?php echo sr_e(sr_t('admin::ui.status.22916f6e')); ?>
                    </button>
                <?php } ?>
            </div>
        </article>
        <div id="<?php echo sr_e($moduleModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($moduleModalId); ?>-label">
            <div class="modal-dialog modal-dialog-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($moduleModalId); ?>-label" class="modal-title"><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?> <?php echo sr_e(sr_t('admin::ui.text.d6a8c525')); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($moduleModalId); ?>">
                            <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <dl class="admin-module-detail-list">
                            <dt><?php echo sr_e(sr_t('admin::ui.text.e37db65f')); ?></dt>
                            <dd><?php echo sr_e($moduleKey); ?></dd>
                            <dt><?php echo sr_e(sr_t('admin::ui.name.253d1510')); ?></dt>
                            <dd><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></dd>
                            <dt><?php echo sr_e(sr_t('admin::ui.text.5cf2792b')); ?></dt>
                            <dd><?php echo sr_e(sr_admin_code_label((string) ($module['code_type'] ?? 'module'), 'module_type')); ?></dd>
                            <dt><?php echo sr_e(sr_t('admin::ui.text.364d60db')); ?></dt>
                            <dd><?php echo sr_e((string) $module['version']); ?></dd>
                            <dt><?php echo sr_e(sr_t('admin::ui.text.cf4479f3')); ?></dt>
                            <dd><?php echo sr_e((string) ($module['code_version'] !== '' ? $module['code_version'] : '-')); ?></dd>
                            <dt><?php echo sr_e(sr_t('admin::ui.text.77fbead1')); ?></dt>
                            <dd><?php echo sr_e((string) ($module['lifecycle_label'] ?? sr_t('admin::ui.status.264d7d27'))); ?> · <?php echo sr_e((string) ($module['lifecycle_action'] ?? sr_t('admin::ui.status.77578d77'))); ?></dd>
                            <dt><?php echo sr_e(sr_t('admin::ui.text.9ea9bd59')); ?></dt>
                            <dd>
                                <?php if ((int) ($module['pending_update_count'] ?? 0) > 0) { ?>
                                    <a href="<?php echo sr_e(sr_url('/admin/updates')); ?>"><?php echo sr_e((string) $module['pending_update_count']); ?><?php echo sr_e(sr_t('admin::ui.sql.ff779501')); ?></a>
                                <?php } elseif (($module['version_state'] ?? '') === 'code_newer') { ?>
                                    <?php echo sr_e(sr_t('admin::ui.text.e5b5943a')); ?>
                                <?php } elseif (($module['version_state'] ?? '') === 'code_older') { ?>
                                    <?php echo sr_e(sr_t('admin::ui.text.be6a1a14')); ?>
                                <?php } else { ?>
                                    -
                                <?php } ?>
                            </dd>
                            <dt><?php echo sr_e(sr_t('admin::ui.saanraan.b9033156')); ?></dt>
                            <dd><?php echo sr_e((string) ($module['saanraan_min_version'] !== '' ? $module['saanraan_min_version'] : '-')); ?></dd>
                            <dt><?php echo sr_e(sr_t('admin::ui.saanraan.959fc83c')); ?></dt>
                            <dd><?php echo sr_e((string) ($module['saanraan_tested_with'] !== '' ? $module['saanraan_tested_with'] : '-')); ?></dd>
                            <dt><?php echo sr_e(sr_t('admin::ui.text.38de236e')); ?></dt>
                            <dd><?php echo sr_e((string) (($module['saanraan_module_contract'] ?? '') !== '' ? $module['saanraan_module_contract'] : '-')); ?></dd>
                            <dt><?php echo sr_e(sr_t('admin::ui.text.9974018f')); ?></dt>
                            <dd>
                                <?php if ($moduleErrors === []) { ?>
                                    -
                                <?php } else { ?>
                                    <ul>
                                        <?php foreach ($moduleErrors as $moduleError) { ?>
                                            <li><?php echo sr_e((string) $moduleError); ?></li>
                                        <?php } ?>
                                    </ul>
                                <?php } ?>
                            </dd>
                            <dt><?php echo sr_e(sr_t('admin::ui.status.e10195a1')); ?></dt>
                            <dd>
                                <?php echo sr_e(sr_admin_code_label($moduleStatus, 'module_status')); ?>
                                <?php if ($moduleStatus === 'enabled' && $moduleErrors !== []) { ?>
                                    <br><?php echo sr_e(sr_t('admin::ui.text.2de2b3ad')); ?>
                                <?php } ?>
                            </dd>
                            <dt><?php echo sr_e(sr_t('admin::ui.text.06834fc9')); ?></dt>
                            <dd><?php echo sr_e(!empty($module['is_bundled']) ? sr_t('admin::ui.text.2eb73fba') : sr_t('admin::ui.text.4c490f1c')); ?></dd>
                            <dt><?php echo sr_e('기반 모듈'); ?></dt>
                            <dd>
                                <?php echo !empty($module['is_foundation']) ? sr_e('예') : sr_e('아니오'); ?>
                                <?php if ($foundationDependents !== []) { ?>
                                    <br><?php echo sr_e('사용 중인 자산 모듈: ' . implode(', ', array_map('strval', $foundationDependents))); ?>
                                <?php } ?>
                            </dd>
                            <dt><?php echo sr_e(sr_t('admin::ui.text.dd537afa')); ?></dt>
                            <dd><?php echo sr_e((string) ($module['installed_at'] ?? '')); ?></dd>
                            <dt><?php echo sr_e(sr_t('admin::ui.text.8c3f651d')); ?></dt>
                            <dd><?php echo sr_e((string) ($module['description'] !== '' ? sr_admin_module_description_label((string) $module['description']) : '-')); ?></dd>
                        </dl>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($moduleModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php if (!in_array($moduleStatus, ['failed', 'installing'], true)) { ?>
            <div id="<?php echo sr_e($moduleStatusModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($moduleStatusModalId); ?>-label">
                <div class="modal-dialog-sm admin-module-status-dialog">
                    <div class="modal-content">
                        <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="admin-form ui-form-theme">
                            <div class="modal-header">
                                <h3 id="<?php echo sr_e($moduleStatusModalId); ?>-label" class="modal-title"><?php echo sr_e(sr_t('admin::ui.status.7fb05f5d')); ?></h3>
                                <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($moduleStatusModalId); ?>">
                                    <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                                </button>
                            </div>
                            <div class="modal-body">
                                <?php if ($isRequired) { ?>
                                    <p class="admin-form-help admin-module-status-help"><?php echo sr_e(sr_t('admin::ui.required.status.22c14e16')); ?></p>
                                <?php } elseif ($foundationDependents !== []) { ?>
                                    <p class="admin-form-help admin-module-status-help"><?php echo sr_e('활성 자산 모듈(' . implode(', ', array_map('strval', $foundationDependents)) . ')이 사용하는 기반 모듈은 비활성화할 수 없습니다.'); ?></p>
                                <?php } else { ?>
                                    <p class="admin-form-help admin-module-status-help"><?php echo sr_e(sr_t('admin::ui.status.f9873f1e')); ?></p>
                                <?php } ?>
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="status">
                                <input type="hidden" name="module_key" value="<?php echo sr_e($moduleKey); ?>">
                                <div class="admin-form-row">
                                    <span class="form-label"><?php echo sr_e(sr_t('admin::ui.text.6d2d8bf4')); ?></span>
                                    <div class="admin-form-field">
                                        <strong><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></strong>
                                        <span class="admin-form-help"><?php echo sr_e($moduleKey); ?></span>
                                    </div>
                                </div>
                                <div class="admin-form-row">
                                    <span class="form-label"><?php echo sr_e(sr_t('admin::ui.status.a00fce68')); ?></span>
                                    <div class="admin-form-field">
                                        <span class="admin-status <?php echo sr_e($moduleStatusClass); ?>">
                                            <?php echo sr_e(sr_admin_code_label($moduleStatus, 'module_status')); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="admin-form-row">
                                    <label class="form-label" for="<?php echo sr_e($moduleStatusModalId); ?>-status"><?php echo sr_e(sr_t('admin::ui.status.098d4cea')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                    <div class="admin-form-field">
                                        <select id="<?php echo sr_e($moduleStatusModalId); ?>-status" name="status" class="form-select" data-overlay-focus>
                                            <?php foreach ($allowedStatuses as $status) { ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $moduleStatus === $status ? ' selected' : ''; ?>>
                                                    <?php echo sr_e(sr_admin_code_label($status, 'module_status')); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($moduleStatusModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                                <button type="submit" class="btn btn-solid-primary modal-action"<?php echo $statusLocked ? ' disabled' : ''; ?>><?php echo sr_e(sr_t('admin::ui.status.save.e0adaad1')); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
</div>
<?php echo sr_admin_pagination_html($modulePagination, '설치된 모듈 목록 페이지'); ?>

<?php $moduleUploadModalLabelId = (!$canManageModuleSources || !$moduleUploadAvailable) ? 'module-upload-modal-label-unavailable' : 'module-upload-modal-label'; ?>
<div id="module-upload-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($moduleUploadModalLabelId); ?>">
    <div class="modal-dialog modal-dialog-lg">
        <div class="modal-content">
            <?php if (!$canManageModuleSources || !$moduleUploadAvailable) { ?>
                <div class="modal-header">
                    <h3 id="module-upload-modal-label-unavailable" class="modal-title"><?php echo sr_e(sr_t('admin::ui.zip.270ef751')); ?></h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#module-upload-modal">
                        <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <?php if (!$canManageModuleSources) { ?>
                        <p><?php echo sr_e(sr_t('admin::ui.text.db7d2323')); ?></p>
                    <?php } elseif (!$moduleUploadAvailable) { ?>
                        <p><?php echo sr_e(sr_t('admin::ui.php.ziparchive.zip.active.cc251e55')); ?> <code>modules/{module_key}</code><?php echo sr_e(sr_t('admin::ui.text.e285ef90')); ?></p>
                    <?php } ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#module-upload-modal"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                </div>
            <?php } else { ?>
                <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" enctype="multipart/form-data" class="admin-form ui-form-theme">
                    <div class="modal-header">
                        <h3 id="module-upload-modal-label" class="modal-title"><?php echo sr_e(sr_t('admin::ui.zip.270ef751')); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#module-upload-modal">
                            <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="intent" value="upload_module_zip">
                        <div class="admin-form-row">
                            <label class="form-label" for="admin_modules_module_zip"><?php echo sr_e(sr_t('admin::ui.zip.af88d6a3')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                            <div class="admin-form-field">
                                <input id="admin_modules_module_zip" type="file" name="module_zip" accept=".zip,application/zip" required class="form-input" data-overlay-focus>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="admin_modules_upload_module_key"><?php echo sr_e(sr_t('admin::ui.key.d2f54e12')); ?></label>
                            <div class="admin-form-field">
                                <input id="admin_modules_upload_module_key" type="text" name="upload_module_key" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" class="form-input" data-admin-key-input>
                            </div>
                        </div>
                        <div class="admin-form-grid">
                            <div class="admin-form-row">
                                <span class="form-label"><?php echo sr_e(sr_t('admin::ui.text.7313e7a0')); ?></span>
                                <div class="admin-form-field">
                                    <div class="filtering-toggle-group admin-checkbox-toggle-group" role="group">
                                        <span class="filtering-toggle-item">
                                            <input id="modules_admin_modules_confirm_file_replace" type="checkbox" name="confirm_file_replace" value="1" class="form-choice-toggle-input sr-only">
                                            <label for="modules_admin_modules_confirm_file_replace" class="btn btn-choice-light"><?php echo sr_admin_choice_label_html(sr_t('admin::ui.text.7313e7a0')); ?></label>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="admin-form-row">
                                <span class="form-label"><?php echo sr_e(sr_t('admin::ui.text.ab7807a7')); ?></span>
                                <div class="admin-form-field">
                                    <div class="filtering-toggle-group admin-checkbox-toggle-group" role="group">
                                        <span class="filtering-toggle-item">
                                            <input id="modules_admin_modules_allow_downgrade" type="checkbox" name="allow_downgrade" value="1" class="form-choice-toggle-input sr-only">
                                            <label for="modules_admin_modules_allow_downgrade" class="btn btn-choice-light"><?php echo sr_admin_choice_label_html(sr_t('admin::ui.text.ab7807a7')); ?></label>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="admin_modules_owner_password"><?php echo sr_e(sr_t('admin::ui.password.6fda7f23')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                            <div class="admin-form-field">
                                <input id="admin_modules_owner_password" type="password" name="owner_password" autocomplete="current-password" required class="form-input">
                            </div>
                        </div>
                        <p><?php echo sr_e(sr_t('admin::ui.password.db92fb99')); ?> <?php echo sr_e($moduleUploadLimitLabel); ?><?php echo sr_e(sr_t('admin::ui.text.52241dbe')); ?> <?php echo sr_e(sr_format_bytes(sr_module_source_uncompressed_limit_bytes())); ?><?php echo sr_e(sr_t('admin::ui.text.f20440e8')); ?> <code>{module_key}/module.php</code> <?php echo sr_e(sr_t('admin::ui.text.ffa27cfc')); ?> <code>module/module.php</code> <?php echo sr_e(sr_t('admin::ui.module.fb45cdc5')); ?></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#module-upload-modal"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('admin::ui.zip.580feeda')); ?></button>
                    </div>
                </form>
            <?php } ?>
        </div>
    </div>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
