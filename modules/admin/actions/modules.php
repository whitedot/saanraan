<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_owner($pdo, (int) $account['id']);
$canManageModuleSources = sr_admin_is_owner($pdo, (int) $account['id']);
$moduleSourcesEnabled = sr_module_sources_enabled($pdo, $config);

$requiredModules = ['member', 'admin', 'policy_documents', 'privacy'];
$allowedStatuses = ['enabled', 'disabled'];
$allowedInstallStatuses = ['enabled', 'disabled'];
$moduleUploadLimitBytes = sr_module_source_upload_limit_bytes();
$moduleUploadLimitLabel = sr_format_bytes($moduleUploadLimitBytes);
$moduleUploadAvailable = class_exists('ZipArchive');
$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $postResult = sr_admin_handle_modules_post(
        $pdo,
        $account,
        $canManageModuleSources,
        $requiredModules,
        $allowedStatuses,
        $allowedInstallStatuses,
        $moduleUploadAvailable,
        $moduleSourcesEnabled
    );
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/modules');
}

$viewData = sr_admin_load_module_management_view_data($pdo);
$modules = $viewData['modules'];
$installableModules = $viewData['installable_modules'];

$moduleTableSortOptions = [
    'name' => ['columns' => ['name', 'module_key']],
    'module_key' => ['columns' => ['module_key']],
    'status' => ['columns' => ['status', 'name']],
    'version' => ['columns' => ['version', 'name']],
    'lifecycle' => ['columns' => ['lifecycle_label', 'lifecycle_action', 'name']],
];
$installedModuleTableSortOptions = $moduleTableSortOptions + [
    'code_version' => ['columns' => ['code_version', 'name']],
    'updates' => ['columns' => ['pending_update_count', 'version_state', 'name']],
    'installed_at' => ['columns' => ['installed_at', 'name']],
];
$moduleTableDefaultSort = sr_admin_sort_default('name', 'asc');
$installedModuleTableDefaultSort = sr_admin_sort_default('status', 'asc');
$installableModuleSort = sr_admin_sort_from_request($moduleTableSortOptions, $moduleTableDefaultSort, 'im_sort', 'im_dir');
$installablePluginSort = sr_admin_sort_from_request($moduleTableSortOptions, $moduleTableDefaultSort, 'ip_sort', 'ip_dir');
$moduleSort = sr_admin_sort_from_request($installedModuleTableSortOptions, $installedModuleTableDefaultSort, 'm_sort', 'm_dir');
$pluginSort = sr_admin_sort_from_request($installedModuleTableSortOptions, $installedModuleTableDefaultSort, 'p_sort', 'p_dir');

$moduleSortValue = static function (array $row, string $key, bool $installed): string {
    if ($key === 'status') {
        $status = (string) ($row['status'] ?? '');
        if (!$installed) {
            $status = ((isset($row['metadata_errors']) && is_array($row['metadata_errors']) ? $row['metadata_errors'] : []) === []) ? 'installable' : 'blocked';
        }

        return $status;
    }

    if ($key === 'version') {
        return (string) ($installed ? ($row['version'] ?? '') : ($row['version'] ?? ''));
    }

    if ($key === 'code_version') {
        return (string) ($row['code_version'] ?? '');
    }

    if ($key === 'lifecycle') {
        return (string) ($row['lifecycle_label'] ?? '') . ' ' . (string) ($row['lifecycle_action'] ?? '');
    }

    if ($key === 'updates') {
        return str_pad((string) (int) ($row['pending_update_count'] ?? 0), 10, '0', STR_PAD_LEFT) . ' ' . (string) ($row['version_state'] ?? '');
    }

    if ($key === 'installed_at') {
        return (string) ($row['installed_at'] ?? '');
    }

    if ($key === 'module_key') {
        return (string) ($row['module_key'] ?? '');
    }

    return sr_admin_module_name_label((string) ($row['name'] ?? ''));
};

$sortModuleRows = static function (array $rows, array $sort, bool $installed) use ($moduleSortValue): array {
    $key = (string) ($sort['key'] ?? 'name');
    $dir = (string) ($sort['dir'] ?? 'asc');

    usort($rows, static function (array $left, array $right) use ($key, $dir, $installed, $moduleSortValue): int {
        $comparison = strnatcasecmp($moduleSortValue($left, $key, $installed), $moduleSortValue($right, $key, $installed));
        if ($comparison === 0) {
            $comparison = strnatcasecmp((string) ($left['module_key'] ?? ''), (string) ($right['module_key'] ?? ''));
        }
        if ($comparison === 0) {
            $comparison = ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
        }

        return $dir === 'desc' ? -$comparison : $comparison;
    });

    return $rows;
};

$installableModules = array_values(array_filter($installableModules, static function (array $module): bool {
    return (string) ($module['type'] ?? 'module') === 'module';
}));

$installablePlugins = array_values(array_filter($viewData['installable_modules'], static function (array $module): bool {
    return (string) ($module['type'] ?? 'module') === 'plugin';
}));

$modules = array_values(array_filter($modules, static function (array $module): bool {
    return (string) ($module['code_type'] ?? 'module') === 'module';
}));

$plugins = array_values(array_filter($viewData['modules'], static function (array $module): bool {
    return (string) ($module['code_type'] ?? 'module') === 'plugin';
}));

$installableModules = $sortModuleRows($installableModules, $installableModuleSort, false);
$installablePlugins = $sortModuleRows($installablePlugins, $installablePluginSort, false);
$modules = $sortModuleRows($modules, $moduleSort, true);
$plugins = $sortModuleRows($plugins, $pluginSort, true);

include SR_ROOT . '/modules/admin/views/modules.php';
