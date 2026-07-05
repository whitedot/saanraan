<?php

$popupLayerIncludeScript = !isset($includeScript) || $includeScript;
$popupLayerPdo = isset($popupLayerPdo) && $popupLayerPdo instanceof PDO ? $popupLayerPdo : null;
echo sr_popup_layer_render_basic_stack($popups, $popupLayerIncludeScript, $popupLayerPdo);
