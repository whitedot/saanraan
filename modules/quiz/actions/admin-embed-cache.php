<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$urlEmbedCacheModuleKey = 'quiz';
$urlEmbedCacheModuleLabel = '퀴즈·테스트';
$urlEmbedCacheAdminPath = '/admin/quiz/embed-cache';
$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], $urlEmbedCacheAdminPath, 'view');
if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], $urlEmbedCacheAdminPath, 'delete');
}

include SR_ROOT . '/core/actions/admin-url-embed-fragment-cache.php';
