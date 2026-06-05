<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/popup_layer/helpers.php';

$popupLayerIdValue = sr_get_string('popup_layer_id', 20);
$popupLayerId = preg_match('/\A[1-9][0-9]*\z/', $popupLayerIdValue) === 1 ? (int) $popupLayerIdValue : 0;
$tmpToken = sr_get_string('tmp', 64);
$fileName = sr_get_string('file', 180);
sr_popup_layer_send_body_file($pdo, $popupLayerId, $fileName, $tmpToken);
