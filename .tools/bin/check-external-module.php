#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('TOY_ROOT', $root);

require_once $root . '/core/version.php';
require_once $root . '/core/helpers/runtime.php';
require_once $root . '/core/helpers/settings.php';

$moduleDir = (string) ($argv[1] ?? '');
$moduleKey = (string) ($argv[2] ?? '');
$errors = [];

function toy_external_module_error(array &$errors, string $message): void
{
    $errors[] = $message;
}

if ($moduleDir === '' || $moduleKey === '') {
    fwrite(STDERR, "Usage: php .tools/bin/check-external-module.php <module-dir> <module-key>\n");
    exit(1);
}

if (!toy_is_safe_module_key($moduleKey)) {
    toy_external_module_error($errors, 'Module key is invalid: ' . $moduleKey);
}

$realModuleDir = realpath($moduleDir);
if (!is_string($realModuleDir) || !is_dir($realModuleDir)) {
    toy_external_module_error($errors, 'Module directory does not exist: ' . $moduleDir);
} else {
    $moduleFile = $realModuleDir . '/module.php';
    if (!is_file($moduleFile)) {
        toy_external_module_error($errors, 'module.php is missing.');
    }

    if (!is_file($realModuleDir . '/install.sql')) {
        toy_external_module_error($errors, 'install.sql is missing.');
    }

    $metadata = [];
    if (is_file($moduleFile)) {
        $loaded = include $moduleFile;
        if (!is_array($loaded)) {
            toy_external_module_error($errors, 'module.php must return an array.');
        } else {
            $metadata = $loaded;
        }
    }

    $name = is_string($metadata['name'] ?? null) ? (string) $metadata['name'] : '';
    if ($name === '') {
        toy_external_module_error($errors, 'module.php name is required.');
    }

    $version = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
    if ($version === '' || preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $version) !== 1) {
        toy_external_module_error($errors, 'module.php version must use YYYY.MM.NNN.');
    }

    $type = (string) ($metadata['type'] ?? 'module');
    if (!in_array($type, ['module', 'plugin'], true)) {
        toy_external_module_error($errors, 'module.php type must be module or plugin.');
    }

    foreach (toy_module_contract_errors($metadata) as $error) {
        toy_external_module_error($errors, $error);
    }

    foreach (['paths.php', 'admin-menu.php', 'output-slots.php', 'extension-points.php', 'menu-links.php', 'sitemap.php', 'privacy-export.php'] as $contractFile) {
        $path = $realModuleDir . '/' . $contractFile;
        if (is_file($path)) {
            $loaded = include $path;
            if (!is_array($loaded)) {
                toy_external_module_error($errors, $contractFile . ' must return an array.');
            }
        }
    }

    $phpFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($realModuleDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($phpFiles as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname());
        $output = [];
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            toy_external_module_error($errors, 'PHP lint failed: ' . $file->getPathname());
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "toycore external module checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "toycore external module checks completed for " . $moduleKey . ".\n";
