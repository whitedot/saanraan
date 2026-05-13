<?php

$adminSettings = isset($pdo) && $pdo instanceof PDO ? sr_admin_settings($pdo) : ['admin_skin_key' => 'basic'];
$adminSkinView = sr_admin_skin_view(sr_admin_skin_key($adminSettings), 'layout-footer');
include $adminSkinView;
