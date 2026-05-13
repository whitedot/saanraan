<?php

declare(strict_types=1);

$siteMenuPage = 'item_form';
$_POST['intent'] = 'save_item';
if (isset($_POST['item_id']) && (int) $_POST['item_id'] > 0) {
    $_GET['edit_item_id'] = $_POST['item_id'];
}

include SR_ROOT . '/modules/site_menu/actions/admin-site-menus.php';
