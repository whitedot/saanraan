#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/core/version.php';

$moduleKey = (string) ($argv[1] ?? '');
$targetDir = (string) ($argv[2] ?? '');
$toycoreRef = (string) ($argv[3] ?? ('v' . TOY_CORE_VERSION));

if ($moduleKey === '' || $targetDir === '' || preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $moduleKey) !== 1) {
    fwrite(STDERR, "Usage: php .tools/bin/create-external-module.php <module-key> <target-dir> [toycore-ref]\n");
    exit(1);
}

if ($targetDir !== '' && !str_starts_with($targetDir, '/') && preg_match('/\A[A-Za-z]:[\/\\\\]/', $targetDir) !== 1) {
    $targetDir = $root . '/' . $targetDir;
}

function toy_create_external_module_fail(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function toy_create_external_module_title(string $moduleKey): string
{
    $parts = explode('_', $moduleKey);
    $words = [];
    foreach ($parts as $part) {
        $words[] = ucfirst($part);
    }

    return implode(' ', $words);
}

function toy_create_external_module_write_file(string $path, string $content): void
{
    if (file_exists($path)) {
        toy_create_external_module_fail('Refusing to overwrite existing file: ' . $path);
    }

    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        toy_create_external_module_fail('Directory cannot be created: ' . $dir);
    }

    if (file_put_contents($path, $content, LOCK_EX) === false) {
        toy_create_external_module_fail('File cannot be written: ' . $path);
    }
}

function toy_create_external_module_template(string $path, array $replacements): string
{
    $content = file_get_contents($path);
    if (!is_string($content)) {
        toy_create_external_module_fail('Template cannot be read: ' . $path);
    }

    return strtr($content, $replacements);
}

$moduleName = toy_create_external_module_title($moduleKey);
$repositoryName = basename(str_replace('\\', '/', rtrim($targetDir, "/\\")));
$replacements = [
    'MODULE_NAME' => $moduleName,
    'MODULE_KEY' => $moduleKey,
    'MODULE_REPOSITORY' => $repositoryName,
    '0.1.1' => TOY_CORE_VERSION,
    '1.0' => TOY_MODULE_CONTRACT_VERSION,
];

if (file_exists($targetDir)) {
    $items = scandir($targetDir);
    if ($items === false) {
        toy_create_external_module_fail('Target directory cannot be read: ' . $targetDir);
    }

    $visibleItems = array_values(array_diff($items, ['.', '..']));
    if ($visibleItems !== []) {
        toy_create_external_module_fail('Target directory is not empty: ' . $targetDir);
    }
} elseif (!mkdir($targetDir, 0755, true)) {
    toy_create_external_module_fail('Target directory cannot be created: ' . $targetDir);
}

$readmeTemplate = $root . '/docs/templates/external-module-README.md';
$readme = toy_create_external_module_template($readmeTemplate, $replacements);
$ciTemplate = toy_create_external_module_template($root . '/docs/module-ci-template.yml', [
    'TOYCORE_MODULE_KEY: example' => 'TOYCORE_MODULE_KEY: ' . $moduleKey,
    'TOYCORE_REF: main' => 'TOYCORE_REF: ' . $toycoreRef,
]);

$modulePhp = "<?php\n\nreturn [\n"
    . "    'name' => '" . addslashes($moduleName) . "',\n"
    . "    'version' => '2026.05.001',\n"
    . "    'type' => 'module',\n"
    . "    'description' => '" . addslashes($moduleName) . " module.',\n"
    . "    'toycore' => [\n"
    . "        'min_version' => '" . TOY_CORE_VERSION . "',\n"
    . "        'tested_with' => ['" . TOY_CORE_VERSION . "'],\n"
    . "        'module_contract' => '" . TOY_MODULE_CONTRACT_VERSION . "',\n"
    . "    ],\n"
    . "];\n";

toy_create_external_module_write_file($targetDir . '/README.md', $readme);
toy_create_external_module_write_file($targetDir . '/CHANGELOG.md', "# Changelog\n\n## 2026.05.001\n\n- Initial module scaffold.\n");
toy_create_external_module_write_file($targetDir . '/module/module.php', $modulePhp);
toy_create_external_module_write_file($targetDir . '/module/install.sql', '-- ' . $moduleName . " module has no tables yet.\n");
toy_create_external_module_write_file($targetDir . '/.github/workflows/check.yml', $ciTemplate);

echo "Created external module scaffold: " . $targetDir . "\n";
