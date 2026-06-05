<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_owner($pdo, (int) $account['id']);
$canManageModuleSources = sr_admin_is_owner($pdo, (int) $account['id']);
$moduleSourcesEnabled = sr_module_sources_enabled($pdo, $config);

$requiredModules = ['member', 'admin', 'privacy'];
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
$installableModulePagination = sr_admin_paginate_array($pdo, $installableModules, 'installable_page');
$installableModules = $installableModulePagination['rows'];
$installableModulePagination = $installableModulePagination['pagination'];
$modulePagination = sr_admin_paginate_array($pdo, $modules, 'module_page');
$modules = $modulePagination['rows'];
$modulePagination = $modulePagination['pagination'];

include SR_ROOT . '/modules/admin/views/modules.php';
