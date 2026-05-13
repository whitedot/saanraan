<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner']);

$errors = [];
$notice = '';
$appliedUpdates = [];
$previousUpdateFailure = sr_admin_previous_update_failure();

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $postResult = sr_admin_handle_updates_post($pdo, $account);
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
    $appliedUpdates = $postResult['applied_updates'];
}

$pendingUpdates = sr_admin_pending_updates($pdo);
$schemaVersions = sr_admin_schema_versions($pdo);
$pendingUpdateCounts = sr_admin_module_pending_update_counts($pendingUpdates);
$moduleVersionDrifts = sr_admin_module_version_drifts($pdo, $pendingUpdateCounts);
$fileOnlyModuleVersionDrifts = sr_admin_file_only_module_version_drifts($moduleVersionDrifts);

include SR_ROOT . '/modules/admin/views/updates.php';
