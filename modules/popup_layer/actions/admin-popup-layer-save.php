<?php

declare(strict_types=1);

$popupLayerAdminPage = 'form';
$_POST['intent'] = 'save';
if (isset($_POST['popup_id']) && (int) $_POST['popup_id'] > 0) {
    $_GET['edit_id'] = $_POST['popup_id'];
}

include SR_ROOT . '/modules/popup_layer/actions/admin-popup-layers.php';
