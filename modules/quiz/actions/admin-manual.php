<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz/manual', 'view');

$adminPageTitle = '퀴즈 매뉴얼';
include SR_ROOT . '/modules/quiz/views/admin-manual.php';
