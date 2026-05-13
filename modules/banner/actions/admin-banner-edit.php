<?php

declare(strict_types=1);

$bannerAdminPage = 'form';
if (isset($_GET['id']) && !isset($_GET['edit_id'])) {
    $_GET['edit_id'] = $_GET['id'];
}

include SR_ROOT . '/modules/banner/actions/admin-banners.php';
