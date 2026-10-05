#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/helpers.php';
require_once $root . '/modules/content/helpers.php';
require_once $root . '/modules/community/helpers.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$fixtureValue = [
    ['area_key' => 'header_links', 'label' => "  첫 메뉴\n영역  ", 'menu_key' => 'Header_Menu'],
    ['slot_key' => 'footer_links', 'name' => '둘째 메뉴', 'menu_key' => 'footer_menu'],
    ['area_key' => 'invalid-key', 'label' => '자동 키', 'menu_key' => 'member_menu'],
    ['area_key' => 'ignored', 'label' => '무효 메뉴', 'menu_key' => '-invalid'],
];
$moduleContracts = [
    'content' => 'sr_content',
    'community' => 'sr_community',
];
foreach ($moduleContracts as $ownerKey => $functionPrefix) {
    $itemsFunction = $functionPrefix . '_layout_extra_menu_items_from_value';
    $pairFunction = $functionPrefix . '_layout_extra_menu_items_from_pair_values';
    $settingsFunction = $functionPrefix . '_layout_extra_menu_items_from_settings';
    $keysFunction = $functionPrefix . '_layout_extra_menu_keys_from_settings';
    $jsonFunction = $functionPrefix . '_layout_extra_menu_keys_json';
    $hashFunction = $functionPrefix . '_layout_extra_menu_hash_key';

    $expectedItems = sr_layout_extra_menu_items_from_value($fixtureValue, $ownerKey);
    $assert($itemsFunction($fixtureValue) === $expectedItems, $ownerKey . ' must delegate extra-menu item normalization to the common helper.');
    $assert(($expectedItems[0]['label'] ?? '') === '첫 메뉴 영역', $ownerKey . ' must normalize extra-menu labels.');
    $assert(count($expectedItems) === 3, $ownerKey . ' must discard invalid menu keys.');

    $pairArguments = [
        ['primary_links', 'secondary_links'],
        ['첫 영역', '둘째 영역'],
        ['header_menu', 'footer_menu'],
    ];
    $expectedPairs = sr_layout_extra_menu_items_from_pair_values(...[...$pairArguments, $ownerKey]);
    $assert($pairFunction(...$pairArguments) === $expectedPairs, $ownerKey . ' must delegate paired extra-menu normalization.');

    $settings = [
        'layout_extra_menu_keys_json' => $fixtureValue,
        'layout_secondary_menu_key' => 'legacy_secondary',
        'layout_tertiary_menu_key' => '-invalid',
    ];
    $expectedSettingsItems = sr_layout_extra_menu_items_from_settings($settings, $ownerKey);
    $assert($settingsFunction($settings) === $expectedSettingsItems, $ownerKey . ' must delegate legacy-setting migration.');
    $assert($keysFunction($settings) === array_column($expectedSettingsItems, 'menu_key'), $ownerKey . ' must expose normalized menu keys.');
    $assert($jsonFunction($fixtureValue) === sr_layout_extra_menu_keys_json($fixtureValue, $ownerKey), $ownerKey . ' must delegate canonical JSON encoding.');

    $expectedHash = substr(hash('sha256', $ownerKey . '.layout_extra_menu|fixture|0'), 0, 12);
    $assert($hashFunction([], 'fixture') === $expectedHash, $ownerKey . ' must preserve its deterministic area-key namespace.');
}

foreach ([
    'sr_content_clean_layout_menu_key',
    'sr_content_clean_layout_extra_menu_area_key',
    'sr_content_layout_extra_menu_label',
    'sr_content_layout_extra_menu_hash_key',
    'sr_content_layout_extra_menu_items_from_value',
    'sr_content_layout_extra_menu_items_from_pair_values',
    'sr_content_layout_extra_menu_keys_from_value',
    'sr_content_layout_extra_menu_items_from_settings',
    'sr_content_layout_extra_menu_keys_from_settings',
    'sr_content_layout_extra_menu_keys_json',
    'sr_community_clean_layout_menu_key',
    'sr_community_clean_layout_extra_menu_area_key',
    'sr_community_layout_extra_menu_label',
    'sr_community_layout_extra_menu_hash_key',
    'sr_community_layout_extra_menu_items_from_value',
    'sr_community_layout_extra_menu_items_from_pair_values',
    'sr_community_layout_extra_menu_keys_from_value',
    'sr_community_layout_extra_menu_items_from_settings',
    'sr_community_layout_extra_menu_keys_from_settings',
    'sr_community_layout_extra_menu_keys_json',
] as $adapterFunction) {
    $reflection = new ReflectionFunction($adapterFunction);
    $lineCount = $reflection->getEndLine() - $reflection->getStartLine() + 1;
    $assert($lineCount <= 4, $adapterFunction . ' must remain a thin module-boundary adapter.');
}

// A configured site menu (including explicitly disabled) must not query or
// replace itself with the module's automatic group navigation.
$menuFixturePdo = new PDO('sqlite::memory:');
foreach (['content', 'community'] as $consumer) {
    $contextFunction = 'sr_' . $consumer . '_public_layout_context';
    foreach (['header', 'custom_navigation', ''] as $menuKey) {
        $context = $contextFunction(['layout_primary_menu_key' => $menuKey], [
            'layout_key' => $consumer === 'content' ? 'community.basic' : 'content.basic',
        ], $menuFixturePdo);
        $assert(($context['site_menus']['primary'] ?? null) === $menuKey, $consumer . ' must preserve the selected site menu key.');
        $assert(($context['module_navigation_html'] ?? null) === '', $consumer . ' must not override a site menu with automatic group links.');
        $assert(($context['layout_key'] ?? '') === ($consumer === 'content' ? 'community.basic' : 'content.basic'), 'Cross-module layout selection must preserve the consumer menu.');
    }
}

if ($errors !== []) {
    fwrite(STDERR, "layout extra-menu checks failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "layout extra-menu checks completed.\n");
