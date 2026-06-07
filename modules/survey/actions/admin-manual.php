<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/surveys/manual', 'view');

$adminPageTitle = '설문 매뉴얼';
include SR_ROOT . '/modules/survey/views/admin-manual.php';
