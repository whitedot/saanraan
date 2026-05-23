<?php

declare(strict_types=1);

$memberGroupsPage = 'groups';
$_POST['intent'] = 'revoke_manual';

include SR_ROOT . '/modules/member/actions/admin-groups.php';
