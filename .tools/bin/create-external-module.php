#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/core/version.php';

$moduleKey = (string) ($argv[1] ?? '');
$targetDir = (string) ($argv[2] ?? '');
$toycoreRef = 'v' . TOY_CORE_VERSION;
$withCi = true;

for ($i = 3; $i < $argc; $i++) {
    $argument = (string) $argv[$i];
    if ($argument === '--no-ci') {
        $withCi = false;
        continue;
    }

    if ($argument !== '' && $argument[0] !== '-') {
        $toycoreRef = $argument;
        continue;
    }

    fwrite(STDERR, "Usage: php .tools/bin/create-external-module.php <module-key> <target-dir> [toycore-ref] [--no-ci]\n");
    exit(1);
}

if ($moduleKey === '' || $targetDir === '' || preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $moduleKey) !== 1) {
    fwrite(STDERR, "Usage: php .tools/bin/create-external-module.php <module-key> <target-dir> [toycore-ref] [--no-ci]\n");
    exit(1);
}

if (
    $toycoreRef === ''
    || strlen($toycoreRef) > 120
    || str_contains($toycoreRef, '..')
    || str_starts_with($toycoreRef, '/')
    || str_ends_with($toycoreRef, '/')
    || str_contains($toycoreRef, '//')
    || preg_match('/\A[A-Za-z0-9._\/-]+\z/', $toycoreRef) !== 1
) {
    fwrite(STDERR, "Toycore ref is invalid.\n");
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

function toy_create_external_module_package_script(string $moduleKey): string
{
    return <<<'PHP'
#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$moduleDir = $root . '/module';
$moduleFile = $moduleDir . '/module.php';
$metadata = is_file($moduleFile) ? include $moduleFile : [];
$metadata = is_array($metadata) ? $metadata : [];
$moduleKey = '__MODULE_KEY__';
$version = (string) ($argv[1] ?? ($metadata['version'] ?? ''));

if (preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $version) !== 1) {
    fwrite(STDERR, "Usage: ./.tools/bin/package-module YYYY.MM.NNN\n");
    exit(1);
}

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "PHP ZipArchive extension is required to create module zip files.\n");
    exit(1);
}

$distDir = $root . '/dist';
if (!is_dir($distDir) && !mkdir($distDir, 0755, true)) {
    fwrite(STDERR, "dist directory cannot be created.\n");
    exit(1);
}

$zipPath = $distDir . '/' . $moduleKey . '-' . $version . '.zip';
if (is_file($zipPath) && !unlink($zipPath)) {
    fwrite(STDERR, "existing zip cannot be removed: " . $zipPath . "\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "zip cannot be created: " . $zipPath . "\n");
    exit(1);
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($moduleDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($files as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }

    $path = $file->getPathname();
    $relative = str_replace('\\', '/', substr($path, strlen($moduleDir) + 1));
    $zip->addFile($path, $moduleKey . '/' . $relative);
}

$zip->close();
echo "Created module zip: " . $zipPath . "\n";
PHP;
}

$moduleName = toy_create_external_module_title($moduleKey);
$repositoryName = basename(str_replace('\\', '/', rtrim($targetDir, "/\\")));
$replacements = [
    'MODULE_NAME' => $moduleName,
    'MODULE_KEY' => $moduleKey,
    'MODULE_REPOSITORY' => $repositoryName,
    'TOYCORE_VERSION' => TOY_CORE_VERSION,
    'TOYCORE_REF' => $toycoreRef,
    'MODULE_CONTRACT_VERSION' => TOY_MODULE_CONTRACT_VERSION,
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
toy_create_external_module_write_file($targetDir . '/.tools/bin/package-module', str_replace('__MODULE_KEY__', $moduleKey, toy_create_external_module_package_script($moduleKey)));
@chmod($targetDir . '/.tools/bin/package-module', 0755);
if ($withCi) {
    toy_create_external_module_write_file($targetDir . '/.github/workflows/check.yml', $ciTemplate);
}

echo "Created external module scaffold: " . $targetDir . "\n";
