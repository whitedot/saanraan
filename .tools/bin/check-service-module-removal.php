#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));
require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/reaction/helpers.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$installer = (string) file_get_contents(SR_ROOT . '/core/actions/install.php');
$entry = (string) file_get_contents(SR_ROOT . '/index.php');
foreach (['quiz', 'survey'] as $moduleKey) {
    $assert(!file_exists(SR_ROOT . '/modules/' . $moduleKey), $moduleKey . ' source must not be bundled.');
    $assert(sr_module_metadata($moduleKey) === [], $moduleKey . ' metadata must not be available.');
    $assert(!str_contains($installer, "'" . $moduleKey . "' =>"), $moduleKey . ' must not be offered by the installer.');
    $assert(!str_contains($entry, '/' . $moduleKey . '/ui-kit'), $moduleKey . ' UI kit must not have a core route.');
    $assert(!in_array($moduleKey, sr_public_layout_domains(), true), $moduleKey . ' must not be a layout domain.');
}

foreach (array_keys(sr_reaction_allowed_target_map()) as $target) {
    $assert(preg_match('/\A(?:quiz|survey)\//', $target) !== 1, 'Removed reaction targets must not be accepted.');
}

foreach (['content', 'community'] as $provider) {
    $layouts = include SR_ROOT . '/modules/' . $provider . '/layout-options.php';
    $assert(is_array($layouts) && $layouts !== [], $provider . ' layout provider must remain available.');
    foreach ($layouts as $layout) {
        foreach ($layout['supports'] ?? [] as $target) {
            $assert(preg_match('/\A(?:quiz|survey)(?:\.|\z)/', (string) $target) !== 1, 'Remaining layouts must not advertise removed targets.');
        }
    }
}

// Old installation records cannot restore removed request/contract files.
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE sr_modules (id INTEGER PRIMARY KEY, module_key TEXT, version TEXT, status TEXT)');
$pdo->exec("INSERT INTO sr_modules VALUES (1, 'quiz', '2026.07.012', 'enabled'), (2, 'survey', '2026.07.012', 'enabled'), (3, 'content', 'test', 'enabled'), (4, 'community', 'test', 'enabled')");
foreach (['paths.php', 'admin-menu.php', 'privacy-export.php', 'reaction-targets.php', 'url-embed-targets.php'] as $contract) {
    $files = sr_enabled_module_contract_files($pdo, $contract);
    $assert(!isset($files['quiz']) && !isset($files['survey']), 'Stale records must not expose ' . $contract . '.');
    $assert(isset($files['content'], $files['community']), 'Remaining services must still expose ' . $contract . '.');
}

if ($errors !== []) {
    fwrite(STDERR, "service module removal checks failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "service module removal checks completed.\n";
