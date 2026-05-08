<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/community/helpers.php';

$boards = toy_community_enabled_boards($pdo);
$settings = toy_module_settings($pdo, 'community');
$themeKey = toy_community_theme_key($settings);
$themeView = toy_community_theme_view($themeKey, 'home');

include $themeView;
