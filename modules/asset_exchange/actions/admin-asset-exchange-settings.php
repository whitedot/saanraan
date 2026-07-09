<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/asset-exchange', 'view');

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_flash_result(sr_admin_action_result(['환전 환경설정은 통합 환전 환경설정 화면에서 저장하세요.'], ''));
}

sr_redirect('/admin/asset-exchange');
