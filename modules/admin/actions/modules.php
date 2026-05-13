<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);
$canManageAdvancedModuleSettings = toy_admin_has_role($pdo, (int) $account['id'], ['owner']);
$canManageModuleSources = toy_admin_has_role($pdo, (int) $account['id'], ['owner']);
$moduleSourcesEnabled = toy_admin_module_sources_enabled($pdo, $config);

$requiredModules = ['member', 'admin', 'privacy'];
$allowedStatuses = ['enabled', 'disabled'];
$allowedSettingTypes = ['string', 'int', 'bool', 'json'];
$allowedInstallStatuses = ['enabled', 'disabled'];
$moduleUploadLimitBytes = toy_admin_module_upload_limit_bytes();
$moduleUploadLimitLabel = toy_admin_format_bytes($moduleUploadLimitBytes);
$moduleUploadAvailable = class_exists('ZipArchive');
$errors = [];
$notice = '';

if (toy_request_method() === 'POST') {
    toy_require_csrf();

    $postResult = toy_admin_handle_modules_post(
        $pdo,
        $account,
        $canManageAdvancedModuleSettings,
        $canManageModuleSources,
        $requiredModules,
        $allowedStatuses,
        $allowedSettingTypes,
        $allowedInstallStatuses,
        $moduleUploadAvailable,
        $moduleSourcesEnabled
    );
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
}

$viewData = toy_admin_load_module_management_view_data($pdo);
$modules = $viewData['modules'];
$installableModules = $viewData['installable_modules'];
$moduleSettings = $viewData['module_settings'];

include TOY_ROOT . '/modules/admin/views/modules.php';
