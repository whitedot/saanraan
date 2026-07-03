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
$showFoundationModules = $viewData['show_foundation_modules'];

$sortDisabledFirst = static function (array $rows): array {
    usort($rows, static function (array $left, array $right): int {
        $leftDisabled = (string) ($left['status'] ?? '') === 'disabled' ? 0 : 1;
        $rightDisabled = (string) ($right['status'] ?? '') === 'disabled' ? 0 : 1;
        if ($leftDisabled !== $rightDisabled) {
            return $leftDisabled <=> $rightDisabled;
        }

        return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
    });

    return $rows;
};

$installableModules = array_values(array_filter($installableModules, static function (array $module): bool {
    return (string) ($module['type'] ?? 'module') === 'module';
}));

$installablePlugins = array_values(array_filter($viewData['installable_modules'], static function (array $module): bool {
    return (string) ($module['type'] ?? 'module') === 'plugin';
}));

$modules = $sortDisabledFirst(array_values(array_filter($modules, static function (array $module): bool {
    return (string) ($module['code_type'] ?? 'module') === 'module';
})));

$plugins = $sortDisabledFirst(array_values(array_filter($viewData['modules'], static function (array $module): bool {
    return (string) ($module['code_type'] ?? 'module') === 'plugin';
})));

include SR_ROOT . '/modules/admin/views/modules.php';
