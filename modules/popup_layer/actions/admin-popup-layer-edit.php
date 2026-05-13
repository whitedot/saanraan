<?php

declare(strict_types=1);

$popupLayerAdminPage = 'form';
if (isset($_GET['id']) && !isset($_GET['edit_id'])) {
    $_GET['edit_id'] = $_GET['id'];
}

include SR_ROOT . '/modules/popup_layer/actions/admin-popup-layers.php';
