<?php

$adminPageTitle = sr_t('admin::ui.text.1ab0e3a9');
$adminPageSubtitle = sr_t('admin::ui.status.130b725f');
ob_start();
?>
<?php if ($canManageModuleSources && !$moduleSourcesEnabled) { ?>
    <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="module-source-enable-modal" data-overlay="#module-source-enable-modal">
        <?php echo sr_material_icon_html('lock_open'); ?>
        <span>파일 반영 일시 허용</span>
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
            <span>파일 반영 닫기</span>
        </button>
    </form>
<?php } ?>
<?php
$adminPageTitleActionsHtml = trim((string) ob_get_clean());
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="alert admin-module-source-alert <?php echo $moduleSourcesEnabled ? 'alert-warning' : 'alert-info'; ?>" role="status">
    <?php if ($moduleSourcesEnabled) { ?>
        <?php if ($moduleUploadAvailable) { ?>
            <p>모듈 파일 반영이 일시 허용되어 있습니다. zip 업로드 또는 파일 업데이트 반영을 마치면 자동으로 닫히며, 필요하면 페이지 상단에서 즉시 닫을 수 있습니다.</p>
        <?php } else { ?>
            <p>모듈 파일 반영이 일시 허용되어 있습니다. ZipArchive 확장이 없어 zip 업로드는 사용할 수 없지만, 파일 업데이트 반영은 실행할 수 있습니다.</p>
        <?php } ?>
    <?php } elseif (!$moduleUploadAvailable) { ?>
        <p>PHP ZipArchive 확장이 없어 zip 업로드를 처리할 수 없습니다. 파일 전용 업데이트 반영은 매니저 비밀번호 재확인 후 일시 허용해 사용할 수 있습니다.</p>
    <?php } else { ?>
        <p>모듈 zip 업로드와 파일 전용 업데이트 반영은 매니저 비밀번호 재확인 후 일시 허용됩니다. 기본 운영 경로는 FTP나 호스팅 파일 관리자로 파일을 배치한 뒤 설치와 업데이트를 진행하는 방식입니다.</p>
    <?php } ?>
</div>

<?php
$installableSections = [
    [
        'id' => 'admin-modules-section-installable-modules',
        'title' => '설치 가능 모듈',
        'rows' => $installableModules,
        'empty' => '설치 가능한 모듈이 없습니다.',
        'sort' => $installableModuleSort,
        'sort_param' => 'im_sort',
        'dir_param' => 'im_dir',
    ],
    [
        'id' => 'admin-modules-section-installable-plugins',
        'title' => '설치 가능 플러그인',
        'rows' => $installablePlugins,
        'empty' => '설치 가능한 플러그인이 없습니다.',
        'sort' => $installablePluginSort,
        'sort_param' => 'ip_sort',
        'dir_param' => 'ip_dir',
    ],
];
$installedSections = [
    [
        'id' => 'admin-modules-section-installed-modules',
        'title' => '설치된 모듈',
        'rows' => $modules,
        'sort' => $moduleSort,
        'sort_param' => 'm_sort',
        'dir_param' => 'm_dir',
    ],
    [
        'id' => 'admin-modules-section-installed-plugins',
        'title' => '설치된 플러그인',
        'rows' => $plugins,
        'sort' => $pluginSort,
        'sort_param' => 'p_sort',
        'dir_param' => 'p_dir',
    ],
];
$moduleManagementSectionNavItems = [
    'admin-modules-section-installable-modules' => '설치 가능 모듈',
    'admin-modules-section-installable-plugins' => '설치 가능 플러그인',
    'admin-modules-section-installed-modules' => '설치된 모듈',
    'admin-modules-section-installed-plugins' => '설치된 플러그인',
];
$moduleManagementClassificationLabel = static function (array $module, array $requiredModules): string {
    $moduleKey = (string) ($module['module_key'] ?? '');
    if (in_array($moduleKey, $requiredModules, true)) {
        return '필수';
    }

    if (!empty($module['is_foundation'])) {
        return '기반';
    }

    return '선택';
};
$moduleManagementClassificationBadgeHtml = static function (array $module, array $requiredModules) use ($moduleManagementClassificationLabel): string {
    $classification = $moduleManagementClassificationLabel($module, $requiredModules);
    $badgeClass = $classification === '필수' ? 'badge-soft-primary' : ($classification === '기반' ? 'badge-soft-info' : 'badge-soft-secondary');

    return '<span class="badge ' . $badgeClass . '">' . sr_e($classification) . '</span>';
};
?>

<nav class="sticky-tabs anchor-tabs tab-nav-justified" aria-label="모듈 플러그인 관리 섹션">
    <?php $moduleManagementSectionNavIndex = 0; ?>
    <?php foreach ($moduleManagementSectionNavItems as $sectionId => $sectionLabel) { ?>
        <a href="#<?php echo sr_e((string) $sectionId); ?>" class="tab-trigger-underline-justified<?php echo $moduleManagementSectionNavIndex === 0 ? ' active' : ''; ?>"<?php echo $moduleManagementSectionNavIndex === 0 ? ' aria-current="location"' : ''; ?>>
            <?php echo sr_e((string) $sectionLabel); ?>
        </a>
        <?php $moduleManagementSectionNavIndex++; ?>
    <?php } ?>
</nav>

<?php foreach ($installableSections as $installableSection) { ?>
    <?php $installableRows = $installableSection['rows']; ?>
<section id="<?php echo sr_e((string) $installableSection['id']); ?>" class="card admin-list-card admin-list-form" data-admin-section-anchor>
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e((string) $installableSection['title']); ?></h2>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($installableSection['sort']['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url($moduleTableSortOptions, $moduleTableDefaultSort, '', '', (string) $installableSection['sort_param'], (string) $installableSection['dir_param'])); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="<?php echo sr_e((string) $installableSection['title']); ?> 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php $installablePagination = ['total' => count($installableRows), 'start' => $installableRows === [] ? 0 : 1, 'end' => count($installableRows)]; ?>
        <?php echo sr_admin_pagination_summary_html($installablePagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-module-management-table">
            <caption class="sr-only"><?php echo sr_e((string) $installableSection['title']); ?></caption>
            <thead>
                <tr>
                    <th class="admin-module-name-column"<?php echo sr_admin_sort_aria('name', $installableSection['sort']); ?>><?php echo sr_admin_sort_header_html('이름', 'name', $installableSection['sort'], $moduleTableSortOptions, $moduleTableDefaultSort, (string) $installableSection['sort_param'], (string) $installableSection['dir_param']); ?></th>
                    <th<?php echo sr_admin_sort_aria('module_key', $installableSection['sort']); ?>><?php echo sr_admin_sort_header_html('Key', 'module_key', $installableSection['sort'], $moduleTableSortOptions, $moduleTableDefaultSort, (string) $installableSection['sort_param'], (string) $installableSection['dir_param']); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $installableSection['sort']); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $installableSection['sort'], $moduleTableSortOptions, $moduleTableDefaultSort, (string) $installableSection['sort_param'], (string) $installableSection['dir_param']); ?></th>
                    <th<?php echo sr_admin_sort_aria('version', $installableSection['sort']); ?>><?php echo sr_admin_sort_header_html('버전', 'version', $installableSection['sort'], $moduleTableSortOptions, $moduleTableDefaultSort, (string) $installableSection['sort_param'], (string) $installableSection['dir_param']); ?></th>
                    <th<?php echo sr_admin_sort_aria('lifecycle', $installableSection['sort']); ?>><?php echo sr_admin_sort_header_html('수명주기', 'lifecycle', $installableSection['sort'], $moduleTableSortOptions, $moduleTableDefaultSort, (string) $installableSection['sort_param'], (string) $installableSection['dir_param']); ?></th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($installableRows === []) { ?>
                    <tr>
                        <td colspan="6" class="admin-empty-state"><?php echo sr_e((string) $installableSection['empty']); ?></td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($installableRows as $module) { ?>
                        <?php $moduleKey = (string) $module['module_key']; ?>
                        <?php $moduleModalId = 'installable-module-detail-' . $moduleKey; ?>
                        <?php $moduleInstallModalId = 'installable-module-install-' . $moduleKey; ?>
                        <?php $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
                        <?php $canInstall = $moduleErrors === []; ?>
                        <tr>
                            <td class="admin-table-break admin-module-name-column">
                                <?php echo $moduleManagementClassificationBadgeHtml($module, $requiredModules); ?>
                                <strong><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></strong>
                                <span class="table-meta"><?php echo sr_e((string) ($module['description'] !== '' ? sr_admin_module_description_label((string) $module['description']) : '-')); ?></span>
                            </td>
                            <td class="admin-table-nowrap"><code><?php echo sr_e($moduleKey); ?></code></td>
                            <td class="admin-table-nowrap">
                                <span class="admin-status <?php echo $canInstall ? 'is-normal' : 'is-blocked'; ?>"><?php echo sr_e($canInstall ? sr_t('admin::ui.text.24a5a830') : sr_t('admin::ui.text.944c1818')); ?></span>
                                <?php if ($moduleErrors !== []) { ?>
                                    <span class="table-meta"><?php echo sr_e(sr_t('admin::ui.text.afad906d')); ?></span>
                                <?php } ?>
                            </td>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) ($module['version'] !== '' ? $module['version'] : '-')); ?></td>
                            <td class="admin-table-break">
                                <?php echo sr_e((string) ($module['lifecycle_label'] ?? sr_t('admin::ui.text.4e4764fd'))); ?>
                                <span class="table-meta"><?php echo sr_e((string) ($module['lifecycle_action'] ?? sr_t('admin::ui.text.24a5a830'))); ?></span>
                            </td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
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
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php echo sr_admin_status_description_list_html('module_installable_status', ['installable' => '설치 가능', 'blocked' => '설치 불가'], [], '설치 가능 상태 설명'); ?>
</section>
<?php foreach ($installableRows as $module) { ?>
    <?php $moduleKey = (string) $module['module_key']; ?>
    <?php $moduleModalId = 'installable-module-detail-' . $moduleKey; ?>
    <?php $moduleInstallModalId = 'installable-module-install-' . $moduleKey; ?>
    <?php $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
    <?php $canInstall = $moduleErrors === []; ?>
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
                                <dd>(<?php echo sr_e($moduleKey); ?>)</dd>
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
                                            <span class="form-help">(<?php echo sr_e($moduleKey); ?>)</span>
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
<?php } ?>

<?php foreach ($installedSections as $installedSection) { ?>
    <?php $installedRows = $installedSection['rows']; ?>
<section id="<?php echo sr_e((string) $installedSection['id']); ?>" class="card admin-list-card admin-list-form" data-admin-section-anchor>
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e((string) $installedSection['title']); ?></h2>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($installedSection['sort']['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url($installedModuleTableSortOptions, $installedModuleTableDefaultSort, '', '', (string) $installedSection['sort_param'], (string) $installedSection['dir_param'])); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="<?php echo sr_e((string) $installedSection['title']); ?> 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php $installedPagination = ['total' => count($installedRows), 'start' => $installedRows === [] ? 0 : 1, 'end' => count($installedRows)]; ?>
        <?php echo sr_admin_pagination_summary_html($installedPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-module-management-table">
            <caption class="sr-only"><?php echo sr_e((string) $installedSection['title']); ?></caption>
            <thead>
                <tr>
                    <th class="admin-module-name-column"<?php echo sr_admin_sort_aria('name', $installedSection['sort']); ?>><?php echo sr_admin_sort_header_html('이름', 'name', $installedSection['sort'], $installedModuleTableSortOptions, $installedModuleTableDefaultSort, (string) $installedSection['sort_param'], (string) $installedSection['dir_param']); ?></th>
                    <th<?php echo sr_admin_sort_aria('module_key', $installedSection['sort']); ?>><?php echo sr_admin_sort_header_html('Key', 'module_key', $installedSection['sort'], $installedModuleTableSortOptions, $installedModuleTableDefaultSort, (string) $installedSection['sort_param'], (string) $installedSection['dir_param']); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $installedSection['sort']); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $installedSection['sort'], $installedModuleTableSortOptions, $installedModuleTableDefaultSort, (string) $installedSection['sort_param'], (string) $installedSection['dir_param']); ?></th>
                    <th<?php echo sr_admin_sort_aria('version', $installedSection['sort']); ?>><?php echo sr_admin_sort_header_html('설치 버전', 'version', $installedSection['sort'], $installedModuleTableSortOptions, $installedModuleTableDefaultSort, (string) $installedSection['sort_param'], (string) $installedSection['dir_param']); ?></th>
                    <th<?php echo sr_admin_sort_aria('code_version', $installedSection['sort']); ?>><?php echo sr_admin_sort_header_html('파일 버전', 'code_version', $installedSection['sort'], $installedModuleTableSortOptions, $installedModuleTableDefaultSort, (string) $installedSection['sort_param'], (string) $installedSection['dir_param']); ?></th>
                    <th<?php echo sr_admin_sort_aria('lifecycle', $installedSection['sort']); ?>><?php echo sr_admin_sort_header_html('수명주기', 'lifecycle', $installedSection['sort'], $installedModuleTableSortOptions, $installedModuleTableDefaultSort, (string) $installedSection['sort_param'], (string) $installedSection['dir_param']); ?></th>
                    <th<?php echo sr_admin_sort_aria('updates', $installedSection['sort']); ?>><?php echo sr_admin_sort_header_html('업데이트', 'updates', $installedSection['sort'], $installedModuleTableSortOptions, $installedModuleTableDefaultSort, (string) $installedSection['sort_param'], (string) $installedSection['dir_param']); ?></th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($installedRows === []) { ?>
                    <tr>
                        <td colspan="8" class="admin-empty-state"><?php echo sr_e((string) $installedSection['title']); ?><?php echo sr_e('이 없습니다.'); ?></td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($installedRows as $module) { ?>
                        <?php $moduleKey = (string) $module['module_key']; ?>
                        <?php $moduleModalId = 'module-detail-' . $moduleKey; ?>
                        <?php $moduleStatusModalId = 'module-status-' . $moduleKey; ?>
                        <?php $moduleSyncOwnerPasswordId = 'modules_admin_modules_owner_password_' . $moduleKey; ?>
                        <?php $moduleRecoveryStatusId = 'modules_admin_modules_status_' . $moduleKey; ?>
                        <?php $isRequired = in_array($moduleKey, $requiredModules, true); ?>
                        <?php $requiredByModules = isset($module['required_by_modules']) && is_array($module['required_by_modules']) ? $module['required_by_modules'] : []; ?>
                        <?php $foundationDependents = isset($module['foundation_dependents']) && is_array($module['foundation_dependents']) ? $module['foundation_dependents'] : []; ?>
                        <?php $statusLocked = $isRequired || $requiredByModules !== []; ?>
                        <?php $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
                        <?php $moduleStatus = (string) $module['status']; ?>
                        <?php $moduleStatusClass = $moduleStatus === 'enabled' ? 'is-normal' : (in_array($moduleStatus, ['failed', 'installing'], true) ? 'is-left' : 'is-blocked'); ?>
                        <tr>
                            <td class="admin-table-break admin-module-name-column">
                                <?php echo $moduleManagementClassificationBadgeHtml($module, $requiredModules); ?>
                                <strong><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></strong>
                                <span class="table-meta"><?php echo sr_e((string) ($module['description'] !== '' ? sr_admin_module_description_label((string) $module['description']) : '-')); ?></span>
                            </td>
                            <td class="admin-table-nowrap"><code><?php echo sr_e($moduleKey); ?></code></td>
                            <td class="admin-table-nowrap">
                                <span class="admin-status <?php echo sr_e($moduleStatusClass); ?>"><?php echo sr_e(sr_admin_code_label($moduleStatus, 'module_status')); ?></span>
                                <?php if ($moduleStatus === 'enabled' && $moduleErrors !== []) { ?>
                                    <span class="table-meta"><?php echo sr_e(sr_t('admin::ui.text.2de2b3ad')); ?></span>
                                <?php } ?>
                            </td>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) $module['version']); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) ($module['code_version'] !== '' ? $module['code_version'] : '-')); ?></td>
                            <td class="admin-table-break">
                                <?php echo sr_e((string) ($module['lifecycle_label'] ?? sr_t('admin::ui.status.264d7d27'))); ?>
                                <span class="table-meta"><?php echo sr_e((string) ($module['lifecycle_action'] ?? sr_t('admin::ui.status.77578d77'))); ?></span>
                            </td>
                            <td class="admin-table-break">
                                <?php if ((int) ($module['pending_update_count'] ?? 0) > 0) { ?>
                                    <a href="<?php echo sr_e(sr_url('/admin/updates')); ?>"><?php echo sr_e((string) $module['pending_update_count']); ?><?php echo sr_e(sr_t('admin::ui.sql.ff779501')); ?></a>
                                <?php } elseif (($module['version_state'] ?? '') === 'code_newer') { ?>
                                    <?php if ($canManageModuleSources && $moduleSourcesEnabled) { ?>
                                        <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="admin-module-sync-form">
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
                            </td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
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
                                                <label for="<?php echo sr_e($moduleRecoveryStatusId); ?>">
                                                    <span><?php echo sr_e(sr_t('admin::ui.status.e19e9f32')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></span>
                                                    <select id="<?php echo sr_e($moduleRecoveryStatusId); ?>" name="status" class="form-select">
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
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php echo sr_admin_status_description_list_html('module_status'); ?>
</section>
<?php foreach ($installedRows as $module) { ?>
    <?php $moduleKey = (string) $module['module_key']; ?>
    <?php $moduleModalId = 'module-detail-' . $moduleKey; ?>
    <?php $moduleStatusModalId = 'module-status-' . $moduleKey; ?>
    <?php $isRequired = in_array($moduleKey, $requiredModules, true); ?>
    <?php $requiredByModules = isset($module['required_by_modules']) && is_array($module['required_by_modules']) ? $module['required_by_modules'] : []; ?>
    <?php $foundationDependents = isset($module['foundation_dependents']) && is_array($module['foundation_dependents']) ? $module['foundation_dependents'] : []; ?>
    <?php $statusLocked = $isRequired || $requiredByModules !== []; ?>
    <?php $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
    <?php $moduleStatus = (string) $module['status']; ?>
    <?php $moduleStatusClass = $moduleStatus === 'enabled' ? 'is-normal' : (in_array($moduleStatus, ['failed', 'installing'], true) ? 'is-left' : 'is-blocked'); ?>
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
                            </dd>
                            <dt><?php echo sr_e('사용 중인 의존 모듈'); ?></dt>
                            <dd>
                                <?php echo $requiredByModules !== [] ? sr_e(implode(', ', array_map('strval', $requiredByModules))) : sr_e('-'); ?>
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
                                    <p class="form-help form-help-warning admin-module-status-help"><?php echo sr_e('활성 의존 모듈(' . implode(', ', array_map('strval', $foundationDependents)) . ')이 사용하는 기반 모듈은 비활성화할 수 없습니다.'); ?></p>
                                <?php } elseif ($requiredByModules !== []) { ?>
                                    <p class="form-help form-help-warning admin-module-status-help"><?php echo sr_e('활성 의존 모듈(' . implode(', ', array_map('strval', $requiredByModules)) . ')이 사용하는 모듈은 비활성화할 수 없습니다.'); ?></p>
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
                                        <span class="form-help">(<?php echo sr_e($moduleKey); ?>)</span>
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
<?php } ?>

<div id="module-source-enable-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="module-source-enable-modal-label">
    <div class="modal-dialog">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="modal-content admin-form ui-form-theme">
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
                        <p><?php echo sr_e(sr_t('admin::ui.php.ziparchive.zip.active.cc251e55')); ?> <code>modules/{module_key}</code><?php echo sr_e(sr_t('admin::ui.text.e285ef90')); ?></p>
                    <?php } elseif (!$moduleSourcesEnabled) { ?>
                        <p>모듈 zip 업로드는 매니저 비밀번호 재확인으로 모듈 파일 반영을 일시 허용한 뒤 사용할 수 있습니다.</p>
                    <?php } ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#module-upload-modal"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                </div>
            <?php } else { ?>
                <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" enctype="multipart/form-data" class="admin-form ui-form-theme">
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
