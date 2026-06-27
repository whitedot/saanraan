<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/embed_manager/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/embed-manager', 'view');

$flashResult = sr_admin_pop_flash_result();
$notice = (string) ($flashResult['notice'] ?? '');
$errors = isset($flashResult['errors']) && is_array($flashResult['errors']) ? $flashResult['errors'] : [];

$filters = [
    'status' => sr_admin_get_allowed_single_array('status', sr_embed_manager_url_cache_statuses(), 30),
    'q' => trim(sr_get_string('q', 120)),
];
$tableReady = sr_embed_manager_table_exists($pdo);
$urlCacheSummary = sr_embed_manager_admin_url_cache_summary($pdo);
$urlCacheRows = $tableReady ? sr_embed_manager_admin_url_cache_rows($pdo, $filters, 100) : [];

include SR_ROOT . '/modules/embed_manager/views/admin-embed-manager.php';
