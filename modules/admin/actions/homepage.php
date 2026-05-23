<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/settings', 'view');
if (sr_request_method() === 'POST') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/settings', 'edit');
    sr_require_csrf();
}

sr_redirect('/admin/settings');
