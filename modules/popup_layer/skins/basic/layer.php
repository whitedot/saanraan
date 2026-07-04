<?php

$popupLayerIncludeScript = !isset($includeScript) || $includeScript;
echo sr_popup_layer_render_basic_stack($popups, $popupLayerIncludeScript);
