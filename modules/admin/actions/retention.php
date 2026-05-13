<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner']);

$values = sr_admin_retention_default_values();
$errors = [];
$notice = '';
$deletedCounts = [];

$hasNotificationTables = sr_admin_retention_notification_tables_exist($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $postResult = sr_admin_handle_retention_post($pdo, $account, $hasNotificationTables);
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
    $values = $postResult['values'];
    $deletedCounts = $postResult['deleted_counts'];
}

$previewCutoffs = sr_admin_retention_preview_cutoffs($values);
$previewCounts = sr_admin_retention_preview_counts($pdo, $previewCutoffs, $hasNotificationTables);

include SR_ROOT . '/modules/admin/views/retention.php';
