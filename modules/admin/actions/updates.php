<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_owner($pdo, (int) $account['id']);

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$appliedUpdates = [];
$flashedAppliedUpdates = $_SESSION['sr_admin_applied_updates'] ?? [];
unset($_SESSION['sr_admin_applied_updates']);
if (is_array($flashedAppliedUpdates)) {
    $appliedUpdates = $flashedAppliedUpdates;
}
$previousUpdateFailure = sr_previous_schema_update_failure();

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $postResult = sr_admin_handle_updates_post($pdo, $account);
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
    $appliedUpdates = $postResult['applied_updates'];
    $_SESSION['sr_admin_applied_updates'] = $appliedUpdates;
    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/updates');
}

$pendingUpdates = sr_pending_schema_updates($pdo);
$schemaVersions = sr_schema_version_rows($pdo);
$pendingUpdateCounts = sr_module_pending_update_counts($pendingUpdates);
$moduleVersionDrifts = sr_module_version_drifts($pdo, $pendingUpdateCounts);
$fileOnlyModuleVersionDrifts = sr_file_only_module_version_drifts($moduleVersionDrifts);

include SR_ROOT . '/modules/admin/views/updates.php';
