<?php

$adminPageTitle = sr_t('admin::ui.text.1ab0e3a9');
$adminPageSubtitle = sr_t('admin::ui.status.130b725f');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php
$adminModuleCardIconHtml = static function (PDO $pdo, string $moduleKey): string {
    $category = sr_admin_module_menu_category_key($moduleKey);
    $icon = sr_admin_module_menu_icon($moduleKey, $category);
    if ($icon === []) {
        $icon = sr_admin_default_menu_icon($category);
    }

    if ((string) ($icon['type'] ?? '') === 'asset') {
        $url = trim((string) ($icon['url'] ?? ''));
        if ($url !== '') {
            return '<span class="admin-module-card-icon" aria-hidden="true"><img class="admin-module-card-icon-image" src="' . sr_e($url) . '" alt=""></span>';
        }
    }

    $symbolName = trim((string) ($icon['name'] ?? $icon['symbol'] ?? ''));
    if ($symbolName === '') {
        $symbolName = sr_admin_default_menu_icon_id($category);
    }

    $renderIcon = sr_admin_icon_render_icon($pdo, $symbolName);
    if ((string) ($renderIcon['type'] ?? '') === 'asset') {
        $url = trim((string) ($renderIcon['url'] ?? ''));
        if ($url !== '') {
            return '<span class="admin-module-card-icon" aria-hidden="true"><img class="admin-module-card-icon-image" src="' . sr_e($url) . '" alt=""></span>';
        }
    }

    return '<span class="admin-module-card-icon" aria-hidden="true">' . sr_material_icon_html((string) ($renderIcon['name'] ?? 'extension'), 'admin-module-card-symbol') . '</span>';
};

$installableSections = [
    [
        'title' => '설치 가능 모듈',
        'rows' => $installableModules,
        'pagination' => $installableModulePagination,
        'empty' => '설치 가능한 모듈이 없습니다.',
        'pagination_label' => '설치 가능 모듈 목록 페이지',
        'show_foundation_toggle' => true,
    ],
    [
        'title' => '설치 가능 플러그인',
        'rows' => $installablePlugins,
        'pagination' => $installablePluginPagination,
        'empty' => '설치 가능한 플러그인이 없습니다.',
        'pagination_label' => '설치 가능 플러그인 목록 페이지',
        'show_foundation_toggle' => false,
    ],
];
$installedSections = [
    [
        'title' => '설치된 모듈',
        'rows' => $modules,
        'pagination' => $modulePagination,
        'pagination_label' => '설치된 모듈 목록 페이지',
    ],
    [
        'title' => '설치된 플러그인',
        'rows' => $plugins,
        'pagination' => $pluginPagination,
        'pagination_label' => '설치된 플러그인 목록 페이지',
    ],
];
?>

<div class="admin-section-heading">
    <h2>모듈 파일 반영</h2>
    <?php if ($canManageModuleSources && !$moduleSourcesEnabled) { ?>
        <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="module-source-enable-modal" data-overlay="#module-source-enable-modal">
            <?php echo sr_material_icon_html('lock_open'); ?>
            <span>일시 허용</span>
        </button>
    <?php } elseif ($canManageModuleSources && $moduleSourcesEnabled) { ?>
        <?php if ($moduleUploadAvailable) { ?>
            <button type="button" class="btn btn-solid-primary" aria-haspopup="dialog" aria-expanded="false" aria-controls="module-upload-modal" data-overlay="#module-upload-modal">
                <?php echo sr_material_icon_html('upload'); ?>
                <span><?php echo sr_e(sr_t('admin::ui.zip.580feeda')); ?></span>
            </button>
        <?php } ?>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="admin-inline-edit-form">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="disable_module_source_writes">
            <button type="submit" class="btn btn-solid-light">
                <?php echo sr_material_icon_html('lock'); ?>
                <span>허용 닫기</span>
            </button>
        </form>
    <?php } ?>
</div>
<section class="card admin-list-card">
    <?php if ($moduleSourcesEnabled) { ?>
        <?php if ($moduleUploadAvailable) { ?>
            <p>모듈 파일 반영이 일시 허용되어 있습니다. zip 업로드 또는 파일 업데이트 반영을 마치면 자동으로 닫히며, 필요하면 즉시 허용을 닫을 수 있습니다.</p>
        <?php } else { ?>
            <p>모듈 파일 반영이 일시 허용되어 있습니다. ZipArchive 확장이 없어 zip 업로드는 사용할 수 없지만, 파일 업데이트 반영은 실행할 수 있습니다.</p>
        <?php } ?>
    <?php } elseif (!$moduleUploadAvailable) { ?>
        <p>PHP ZipArchive 확장이 없어 zip 업로드를 처리할 수 없습니다. 파일 전용 업데이트 반영은 매니저 비밀번호 재확인 후 일시 허용해 사용할 수 있습니다.</p>
    <?php } else { ?>
        <p>모듈 zip 업로드와 파일 전용 업데이트 반영은 매니저 비밀번호 재확인 후 일시 허용됩니다. 기본 운영 경로는 FTP나 호스팅 파일 관리자로 파일을 배치한 뒤 설치와 업데이트를 진행하는 방식입니다.</p>
    <?php } ?>
</section>

<?php foreach ($installableSections as $installableSection) { ?>
    <?php $installableRows = $installableSection['rows']; ?>
    <?php $installablePagination = $installableSection['pagination']; ?>
<div class="admin-section-heading">
    <h2><?php echo sr_e((string) $installableSection['title']); ?></h2>
    <?php if (!empty($installableSection['show_foundation_toggle'])) { ?>
        <a class="btn btn-solid-light" href="<?php echo sr_e(sr_url($showFoundationModules ? '/admin/modules' : '/admin/modules?show_foundations=1')); ?>">
            <?php echo sr_e($showFoundationModules ? '기반 모듈 숨기기' : '기반 모듈 보기'); ?>
        </a>
    <?php } ?>
</div>
<?php echo sr_admin_pagination_summary_html($installablePagination); ?>
<?php if ($installableRows === []) { ?>
    <section class="card admin-list-card">
        <p><?php echo sr_e((string) $installableSection['empty']); ?></p>
    </section>
<?php } else { ?>
    <div class="admin-module-card-grid admin-module-installable-grid">
        <?php foreach ($installableRows as $module) { ?>
            <?php $moduleKey = (string) $module['module_key']; ?>
            <?php $moduleModalId = 'installable-module-detail-' . $moduleKey; ?>
            <?php $moduleInstallModalId = 'installable-module-install-' . $moduleKey; ?>
            <?php $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
            <?php $canInstall = $moduleErrors === []; ?>
            <article class="card admin-module-card admin-list-form">
                <div class="admin-module-card-header">
                    <div class="admin-module-card-title">
                        <?php echo $adminModuleCardIconHtml($pdo, $moduleKey); ?>
                        <div class="admin-module-card-title-text">
                            <h3><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></h3>
                            <p>관리용 키: <?php echo sr_e($moduleKey); ?> · <?php echo sr_e(sr_admin_code_label((string) $module['type'], 'module_type')); ?></p>
                        </div>
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
                            <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($moduleModalId); ?>">
                                <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                            </button>
                        </div>
                        <div class="modal-body">
                            <dl class="admin-module-detail-list">
                                <dt><?php echo sr_e(sr_t('admin::ui.text.e37db65f')); ?></dt>
                                <dd>관리용 키: <?php echo sr_e($moduleKey); ?></dd>
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
                                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($moduleInstallModalId); ?>">
                                        <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="install">
                                    <input type="hidden" name="module_key" value="<?php echo sr_e($moduleKey); ?>">
                                    <div class="form-row">
                                        <span class="form-label"><?php echo sr_e(sr_t('admin::ui.text.6d2d8bf4')); ?></span>
                                        <div class="form-field">
                                            <strong><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></strong>
                                            <span class="form-help">관리용 키: <?php echo sr_e($moduleKey); ?></span>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <label class="form-label" for="<?php echo sr_e($moduleInstallModalId); ?>-status"><?php echo sr_e(sr_t('admin::ui.status.e19e9f32')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                        <div class="form-field">
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
    <?php echo sr_admin_status_description_list_html('module_installable_status', ['installable' => '설치 가능', 'blocked' => '설치 불가'], [], '설치 가능 상태 설명'); ?>
    <?php echo sr_admin_pagination_html($installablePagination, (string) $installableSection['pagination_label']); ?>
<?php } ?>
<?php } ?>

<?php foreach ($installedSections as $installedSection) { ?>
    <?php $installedRows = $installedSection['rows']; ?>
    <?php $installedPagination = $installedSection['pagination']; ?>
<div class="admin-section-heading">
    <h2><?php echo sr_e((string) $installedSection['title']); ?></h2>
</div>
<?php echo sr_admin_pagination_summary_html($installedPagination); ?>
<?php if ($installedRows === []) { ?>
    <section class="card admin-list-card">
        <p><?php echo sr_e((string) $installedSection['title']); ?><?php echo sr_e('이 없습니다.'); ?></p>
    </section>
<?php } else { ?>
<div class="admin-module-card-grid">
    <?php foreach ($installedRows as $module) { ?>
        <?php $moduleKey = (string) $module['module_key']; ?>
        <?php $moduleModalId = 'module-detail-' . $moduleKey; ?>
        <?php $moduleStatusModalId = 'module-status-' . $moduleKey; ?>
        <?php $moduleSyncOwnerPasswordId = 'modules_admin_modules_owner_password_' . $moduleKey; ?>
        <?php $isRequired = in_array($moduleKey, $requiredModules, true); ?>
        <?php $foundationDependents = isset($module['foundation_dependents']) && is_array($module['foundation_dependents']) ? $module['foundation_dependents'] : []; ?>
        <?php $statusLocked = $isRequired || $foundationDependents !== []; ?>
        <?php $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
        <?php $moduleStatus = (string) $module['status']; ?>
        <?php $moduleStatusClass = $moduleStatus === 'enabled' ? 'is-normal' : (in_array($moduleStatus, ['failed', 'installing'], true) ? 'is-left' : 'is-blocked'); ?>
        <article class="card admin-module-card admin-list-form">
            <div class="admin-module-card-header">
                <div class="admin-module-card-title">
                    <?php echo $adminModuleCardIconHtml($pdo, $moduleKey); ?>
                    <div class="admin-module-card-title-text">
                        <h3><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></h3>
                        <p>관리용 키: <?php echo sr_e($moduleKey); ?> · <?php echo sr_e(sr_admin_code_label((string) ($module['code_type'] ?? 'module'), 'module_type')); ?></p>
                    </div>
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
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="admin-module-sync-form" data-sr-validate-form>
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="sync_module_version">
                                        <input type="hidden" name="module_key" value="<?php echo sr_e($moduleKey); ?>">
                                        <label for="<?php echo sr_e($moduleSyncOwnerPasswordId); ?>">
                                            <span class="sr-only"><?php echo sr_e(sr_t('admin::ui.password.6fda7f23')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></span>
                                            <input id="<?php echo sr_e($moduleSyncOwnerPasswordId); ?>" type="password" name="owner_password" class="form-input" autocomplete="current-password" required placeholder="<?php echo sr_e(sr_t('admin::ui.password.6fda7f23')); ?>">
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
                        <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($moduleModalId); ?>">
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
                                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($moduleStatusModalId); ?>">
                                    <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                                </button>
                            </div>
                            <div class="modal-body">
                                <?php if ($isRequired) { ?>
                                    <p class="form-help admin-module-status-help"><?php echo sr_e(sr_t('admin::ui.required.status.22c14e16')); ?></p>
                                <?php } elseif ($foundationDependents !== []) { ?>
                                    <p class="form-help admin-module-status-help"><?php echo sr_e('활성 자산 모듈(' . implode(', ', array_map('strval', $foundationDependents)) . ')이 사용하는 기반 모듈은 비활성화할 수 없습니다.'); ?></p>
                                <?php } else { ?>
                                    <p class="form-help admin-module-status-help"><?php echo sr_e(sr_t('admin::ui.status.f9873f1e')); ?></p>
                                <?php } ?>
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="status">
                                <input type="hidden" name="module_key" value="<?php echo sr_e($moduleKey); ?>">
                                <div class="form-row">
                                    <span class="form-label"><?php echo sr_e(sr_t('admin::ui.text.6d2d8bf4')); ?></span>
                                    <div class="form-field">
                                        <strong><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></strong>
                                        <span class="form-help">관리용 키: <?php echo sr_e($moduleKey); ?></span>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <span class="form-label"><?php echo sr_e(sr_t('admin::ui.status.a00fce68')); ?></span>
                                    <div class="form-field">
                                        <span class="admin-status <?php echo sr_e($moduleStatusClass); ?>">
                                            <?php echo sr_e(sr_admin_code_label($moduleStatus, 'module_status')); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <label class="form-label" for="<?php echo sr_e($moduleStatusModalId); ?>-status"><?php echo sr_e(sr_t('admin::ui.status.098d4cea')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                                    <div class="form-field">
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
<?php echo sr_admin_status_description_list_html('module_status'); ?>
<?php echo sr_admin_pagination_html($installedPagination, (string) $installedSection['pagination_label']); ?>
<?php } ?>
<?php } ?>

<div id="module-source-enable-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="module-source-enable-modal-label">
    <div class="modal-dialog">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="modal-content admin-form ui-form-theme" data-sr-validate-form>
            <div class="modal-header">
                <h3 id="module-source-enable-modal-label" class="modal-title">모듈 파일 반영 일시 허용</h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#module-source-enable-modal">
                    <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                </button>
            </div>
            <div class="modal-body">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="enable_module_source_writes">
                <div class="form-row">
                    <label class="form-label" for="admin_modules_source_enable_owner_password"><?php echo sr_e(sr_t('admin::ui.password.6fda7f23')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                    <div class="form-field">
                        <input id="admin_modules_source_enable_owner_password" type="password" name="owner_password" autocomplete="current-password" required class="form-input" data-overlay-focus>
                    </div>
                </div>
                <?php if ($moduleUploadAvailable) { ?>
                    <p>허용 후 모듈 zip 업로드와 파일 전용 업데이트 반영을 실행할 수 있습니다. zip 업로드가 끝나면 허용 값은 자동으로 다시 꺼집니다.</p>
                <?php } else { ?>
                    <p>허용 후 파일 전용 업데이트 반영을 실행할 수 있습니다. ZipArchive 확장이 없어 zip 업로드는 사용할 수 없습니다.</p>
                <?php } ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#module-source-enable-modal"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                <button type="submit" class="btn btn-solid-primary modal-action">일시 허용</button>
            </div>
        </form>
    </div>
</div>

<?php $moduleUploadModalLabelId = (!$canManageModuleSources || !$moduleUploadAvailable || !$moduleSourcesEnabled) ? 'module-upload-modal-label-unavailable' : 'module-upload-modal-label'; ?>
<div id="module-upload-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($moduleUploadModalLabelId); ?>">
    <div class="modal-dialog modal-dialog-lg">
        <div class="modal-content">
            <?php if (!$canManageModuleSources || !$moduleUploadAvailable || !$moduleSourcesEnabled) { ?>
                <div class="modal-header">
                    <h3 id="module-upload-modal-label-unavailable" class="modal-title"><?php echo sr_e(sr_t('admin::ui.zip.270ef751')); ?></h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#module-upload-modal">
                        <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <?php if (!$canManageModuleSources) { ?>
                        <p><?php echo sr_e(sr_t('admin::ui.text.db7d2323')); ?></p>
                    <?php } elseif (!$moduleUploadAvailable) { ?>
                        <p><?php echo sr_e(sr_t('admin::ui.php.ziparchive.zip.active.cc251e55')); ?> <code>modules/{모듈관리용키}</code><?php echo sr_e(sr_t('admin::ui.text.e285ef90')); ?></p>
                    <?php } elseif (!$moduleSourcesEnabled) { ?>
                        <p>모듈 zip 업로드는 매니저 비밀번호 재확인으로 모듈 파일 반영을 일시 허용한 뒤 사용할 수 있습니다.</p>
                    <?php } ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#module-upload-modal"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                </div>
            <?php } else { ?>
                <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" enctype="multipart/form-data" class="admin-form ui-form-theme" data-sr-validate-form>
                    <div class="modal-header">
                        <h3 id="module-upload-modal-label" class="modal-title"><?php echo sr_e(sr_t('admin::ui.zip.270ef751')); ?></h3>
                        <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#module-upload-modal">
                            <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="intent" value="upload_module_zip">
                        <div class="form-row">
                            <label class="form-label" for="admin_modules_module_zip"><?php echo sr_e(sr_t('admin::ui.zip.af88d6a3')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                            <div class="form-field">
                                <input id="admin_modules_module_zip" type="file" name="module_zip" accept=".zip,application/zip" required class="form-input" data-overlay-focus>
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="form-label" for="admin_modules_upload_module_key"><?php echo sr_e(sr_t('admin::ui.key.d2f54e12')); ?></label>
                            <div class="form-field">
                                <input id="admin_modules_upload_module_key" type="text" name="upload_module_key" maxlength="40" pattern="[a-z][a-z0-9_]{1,39}" inputmode="latin" autocapitalize="none" spellcheck="false" class="form-input" data-admin-key-input>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="form-row">
                                <span class="form-label"><?php echo sr_e(sr_t('admin::ui.text.7313e7a0')); ?></span>
                                <div class="form-field">
                                    <div class="filtering-toggle-group admin-checkbox-toggle-group" role="group">
                                        <span class="filtering-toggle-item">
                                            <input id="modules_admin_modules_confirm_file_replace" type="checkbox" name="confirm_file_replace" value="1" class="form-choice-toggle-input sr-only">
                                            <label for="modules_admin_modules_confirm_file_replace" class="btn btn-choice-light"><?php echo sr_admin_choice_label_html(sr_t('admin::ui.text.7313e7a0')); ?></label>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <span class="form-label"><?php echo sr_e(sr_t('admin::ui.text.ab7807a7')); ?></span>
                                <div class="form-field">
                                    <div class="filtering-toggle-group admin-checkbox-toggle-group" role="group">
                                        <span class="filtering-toggle-item">
                                            <input id="modules_admin_modules_allow_downgrade" type="checkbox" name="allow_downgrade" value="1" class="form-choice-toggle-input sr-only">
                                            <label for="modules_admin_modules_allow_downgrade" class="btn btn-choice-light"><?php echo sr_admin_choice_label_html(sr_t('admin::ui.text.ab7807a7')); ?></label>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="form-label" for="admin_modules_owner_password"><?php echo sr_e(sr_t('admin::ui.password.6fda7f23')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label>
                            <div class="form-field">
                                <input id="admin_modules_owner_password" type="password" name="owner_password" autocomplete="current-password" required class="form-input">
                            </div>
                        </div>
                        <p><?php echo sr_e(sr_t('admin::ui.password.db92fb99')); ?> <?php echo sr_e($moduleUploadLimitLabel); ?><?php echo sr_e(sr_t('admin::ui.text.52241dbe')); ?> <?php echo sr_e(sr_format_bytes(sr_module_source_uncompressed_limit_bytes())); ?><?php echo sr_e(sr_t('admin::ui.text.f20440e8')); ?> <code>{모듈관리용키}/module.php</code> <?php echo sr_e(sr_t('admin::ui.text.ffa27cfc')); ?> <code>module/module.php</code> <?php echo sr_e(sr_t('admin::ui.module.fb45cdc5')); ?></p>
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
