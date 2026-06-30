<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_owner($pdo, (int) $account['id']);

$values = sr_admin_retention_values($pdo);
$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $postResult = sr_admin_handle_retention_post($pdo, $account, $values);
    sr_admin_flash_result($postResult);
    sr_redirect('/admin/retention');
}

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$values = sr_admin_retention_values($pdo);
$previewCutoffs = sr_admin_retention_preview_cutoffs($values);
$previewCounts = sr_admin_retention_preview_counts($pdo, $previewCutoffs);
$hasNotificationTables = array_key_exists('notifications', $previewCounts);
$hasAdminNotificationTables = array_key_exists('admin_notifications', $previewCounts);

include SR_ROOT . '/modules/admin/views/retention.php';
