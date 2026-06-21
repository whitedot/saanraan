<?php

declare(strict_types=1);

$communityBoardsPage = 'edit';
if (!isset($_POST['intent']) || (string) $_POST['intent'] === '') {
    $_POST['intent'] = 'update';
}
if (isset($_POST['board_id'])) {
    $_GET['edit_id'] = $_POST['board_id'];
}

include SR_ROOT . '/modules/community/actions/admin-boards.php';
