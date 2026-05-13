<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$adminPageTitle = '샘플 공지';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<p>샘플 모듈 관리자 화면입니다.</p>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
