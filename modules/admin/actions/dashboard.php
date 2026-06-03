<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin', 'view');

if (!sr_admin_is_owner($pdo, (int) $account['id'])) {
    $firstPermittedPath = sr_admin_first_permitted_menu_path($pdo, (int) $account['id']);
    if ($firstPermittedPath !== '') {
        sr_redirect($firstPermittedPath);
    }

    sr_render_error(403, sr_t('admin::auth.role_required'));
}

$moduleDashboardSections = sr_admin_dashboard_module_sections($pdo);
$recoveryMarkers = sr_admin_dashboard_recovery_markers();
$moduleBackupSummary = sr_admin_dashboard_module_backup_summary();

include SR_ROOT . '/modules/admin/views/dashboard.php';
