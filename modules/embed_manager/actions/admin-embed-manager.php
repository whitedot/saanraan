<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/embed_manager/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/embed-manager', 'view');

$filters = [
    'status' => sr_admin_get_allowed_single_array('status', sr_embed_manager_allowed_statuses(), 30),
    'q' => trim(sr_get_string('q', 120)),
];
$refs = sr_embed_manager_admin_refs($pdo, $filters, 100);
$tableReady = sr_embed_manager_table_exists($pdo);

include SR_ROOT . '/modules/embed_manager/views/admin-embed-manager.php';
