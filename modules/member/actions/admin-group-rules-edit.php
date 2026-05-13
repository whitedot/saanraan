<?php

declare(strict_types=1);

$memberGroupsPage = 'rule_form';
if (isset($_GET['id']) && !isset($_GET['edit_rule_id'])) {
    $_GET['edit_rule_id'] = $_GET['id'];
}

include SR_ROOT . '/modules/member/actions/admin-groups.php';
