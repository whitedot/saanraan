<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/assets/recovery-failures', 'view');

if (sr_request_method() === 'POST') {
    sr_require_csrf();
}

sr_redirect('/admin/assets/recovery-failures' . ((string) ($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . (string) ($_SERVER['QUERY_STRING'] ?? '') : ''));
