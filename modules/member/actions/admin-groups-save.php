<?php

declare(strict_types=1);

$_POST['intent'] = 'save_group';
$memberGroupsPage = 'groups';

include SR_ROOT . '/modules/member/actions/admin-groups.php';
