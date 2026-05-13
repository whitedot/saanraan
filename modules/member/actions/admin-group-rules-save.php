<?php

declare(strict_types=1);

$memberGroupsPage = 'rule_form';
$_POST['intent'] = 'save_rule';
if (isset($_POST['rule_id']) && (string) $_POST['rule_id'] !== '') {
    $_GET['edit_rule_id'] = $_POST['rule_id'];
}

include SR_ROOT . '/modules/member/actions/admin-groups.php';
