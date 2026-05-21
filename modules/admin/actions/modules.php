<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);
$canManageModuleSources = sr_admin_has_role($pdo, (int) $account['id'], ['owner']);
$moduleSourcesEnabled = sr_module_sources_enabled($pdo, $config);

$requiredModules = ['member', 'admin', 'privacy'];
$allowedStatuses = ['enabled', 'disabled'];
$allowedInstallStatuses = ['enabled', 'disabled'];
$moduleUploadLimitBytes = sr_module_source_upload_limit_bytes();
$moduleUploadLimitLabel = sr_format_bytes($moduleUploadLimitBytes);
$moduleUploadAvailable = class_exists('ZipArchive');
$errors = [];
$notice = '';

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
}

$viewData = sr_load_module_management_view_data($pdo);
$modules = $viewData['modules'];
$installableModules = $viewData['installable_modules'];

include SR_ROOT . '/modules/admin/views/modules.php';
