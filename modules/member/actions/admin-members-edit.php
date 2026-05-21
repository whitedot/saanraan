<?php

declare(strict_types=1);

$memberAdminPage = 'edit_form';
if (isset($_GET['id']) && !isset($_GET['edit_id'])) {
    $_GET['edit_id'] = $_GET['id'];
}

include SR_ROOT . '/modules/member/actions/admin-members.php';
