<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/posts', 'view');

$brokenOnly = sr_get_string('status', 20) === 'broken';
$linkRefs = sr_community_admin_link_refs($pdo, $brokenOnly);
$notice = '';
$errors = [];

include SR_ROOT . '/modules/community/views/admin-link-refs.php';
