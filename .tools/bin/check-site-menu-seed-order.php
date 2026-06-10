#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));
chdir(SR_ROOT);

require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/site_menu/helpers.php';

$options = [];
foreach (['content', 'quiz', 'survey', 'community'] as $moduleKey) {
    $metadata = sr_module_metadata($moduleKey);
    $serviceDomain = is_array($metadata['service_domain'] ?? null) ? $metadata['service_domain'] : [];
    $mainPage = is_array($serviceDomain['main_page'] ?? null) ? $serviceDomain['main_page'] : [];
    $options[$moduleKey] = [
        'label' => (string) ($mainPage['label'] ?? $moduleKey),
        'path' => (string) ($mainPage['path'] ?? ''),
    ];
}

$items = sr_site_menu_seed_default_header_menu_items($options, ['survey', 'quiz', 'community', 'content']);
$labels = array_map('strval', array_column($items, 'label'));
$expected = ['홈', '콘텐츠 메인', '커뮤니티 홈', '퀴즈 메인', '설문 메인'];
if ($labels !== $expected) {
    fwrite(STDERR, 'Site menu seed order must follow admin service menu order: ' . implode(' > ', $labels) . "\n");
    exit(1);
}

echo "site menu seed order checks completed.\n";
