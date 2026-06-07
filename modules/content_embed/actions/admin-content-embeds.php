<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content_embed/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content-embeds', 'view');

$filters = [
    'status' => sr_admin_get_allowed_single_array('status', sr_content_embed_allowed_statuses(), 30),
    'q' => trim(sr_get_string('q', 120)),
];
$refs = sr_content_embed_admin_refs($pdo, $filters, 100);
$tableReady = sr_content_embed_table_exists($pdo);

include SR_ROOT . '/modules/content_embed/views/admin-content-embeds.php';
