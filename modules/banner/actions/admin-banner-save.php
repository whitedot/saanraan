<?php

declare(strict_types=1);

$bannerAdminPage = 'form';
$_POST['intent'] = 'save';
if (isset($_POST['banner_id']) && (int) $_POST['banner_id'] > 0) {
    $_GET['edit_id'] = $_POST['banner_id'];
}

include SR_ROOT . '/modules/banner/actions/admin-banners.php';
