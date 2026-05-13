<?php

declare(strict_types=1);

$communityBoardGroupsPage = 'edit';
$_POST['intent'] = 'update_group';
if (isset($_POST['group_id'])) {
    $_GET['edit_id'] = $_POST['group_id'];
}

include SR_ROOT . '/modules/community/actions/admin-board-groups.php';
