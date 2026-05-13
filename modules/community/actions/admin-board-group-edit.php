<?php

declare(strict_types=1);

$communityBoardGroupsPage = 'edit';
if (isset($_GET['id']) && !isset($_GET['edit_id'])) {
    $_GET['edit_id'] = $_GET['id'];
}

include SR_ROOT . '/modules/community/actions/admin-board-groups.php';
