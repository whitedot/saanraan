<?php

$adminSettings = isset($pdo) && $pdo instanceof PDO ? sr_admin_settings($pdo) : ['admin_theme_key' => 'basic'];
$adminThemeView = sr_admin_theme_view(sr_admin_theme_key($adminSettings), 'layout-header');
include $adminThemeView;
