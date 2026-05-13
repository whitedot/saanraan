<?php

declare(strict_types=1);

$memberGroupsPage = 'group_form';
$_POST['intent'] = 'save_group';
if (isset($_POST['group_id']) && (string) $_POST['group_id'] !== '') {
    $_GET['edit_id'] = $_POST['group_id'];
}

include SR_ROOT . '/modules/member/actions/admin-groups.php';
