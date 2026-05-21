<?php

declare(strict_types=1);

$postedAccountId = isset($_POST['account_id']) ? trim((string) $_POST['account_id']) : '';
$memberAdminPage = $postedAccountId !== '' ? 'edit_form' : 'create_form';
$_POST['intent'] = $postedAccountId !== '' ? 'edit' : 'create';
if ($postedAccountId !== '') {
    $_GET['edit_id'] = $postedAccountId;
}

include SR_ROOT . '/modules/member/actions/admin-members.php';
