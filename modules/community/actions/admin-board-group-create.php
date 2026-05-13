<?php

declare(strict_types=1);

$communityBoardGroupsPage = 'new';
$_POST['intent'] = 'create_group';

include SR_ROOT . '/modules/community/actions/admin-board-groups.php';
