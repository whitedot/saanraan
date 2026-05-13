<?php

declare(strict_types=1);

$siteMenuPage = 'item_form';
if (isset($_GET['id']) && !isset($_GET['edit_item_id'])) {
    $_GET['edit_item_id'] = $_GET['id'];
}

include SR_ROOT . '/modules/site_menu/actions/admin-site-menus.php';
