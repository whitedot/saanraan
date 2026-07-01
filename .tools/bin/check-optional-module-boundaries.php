#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_optional_boundary_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_optional_boundary_php_files(array $roots): array
{
    $files = [];
    foreach ($roots as $root) {
        if (!is_dir($root)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $files[] = $file->getPathname();
        }
    }

    sort($files);
    return $files;
}

$consumerRoots = [
    'modules/content',
    'modules/community',
    'modules/quiz',
    'modules/survey',
];
$optionalModules = ['banner', 'popup_layer', 'reaction'];

foreach (sr_optional_boundary_php_files($consumerRoots) as $file) {
    $source = file_get_contents($file);
    if (!is_string($source)) {
        sr_optional_boundary_error('cannot read ' . $file);
        continue;
    }

    $source = str_replace(["\r\n", "\r"], "\n", $source);
    $relativePath = $file;
    $lines = explode("\n", $source);

    foreach ($optionalModules as $moduleKey) {
        if (str_contains($source, "sr_module_enabled(, '" . $moduleKey . "')")) {
            sr_optional_boundary_error($relativePath . ' should pass a PDO when checking ' . $moduleKey . '.');
        }
    }

    foreach ($lines as $lineNumber => $line) {
        $lineLabel = $relativePath . ':' . (string) ($lineNumber + 1);
        $context = implode("\n", array_slice($lines, max(0, $lineNumber - 5), 11));

        foreach ($optionalModules as $moduleKey) {
            if (str_contains($line, "/modules/" . $moduleKey . "/helpers.php")) {
                $guarded = str_contains($context, "sr_module_enabled(\$pdo, '" . $moduleKey . "')")
                    || str_contains($context, "sr_module_enabled(\$layoutPdo, '" . $moduleKey . "')");
                if (!$guarded) {
                    sr_optional_boundary_error($lineLabel . ' should only load ' . $moduleKey . ' helpers when the module is enabled.');
                }
            }

            if (str_contains($line, "/modules/" . $moduleKey . "/assets/module.css")) {
                $guarded = str_contains($context, 'sr_enabled_module_asset_paths(')
                    || str_contains($context, "sr_module_enabled(\$layoutPdo, '" . $moduleKey . "')");
                if (!$guarded) {
                    sr_optional_boundary_error($lineLabel . ' should only add ' . $moduleKey . ' assets when the module is enabled.');
                }
            }
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "optional module boundary checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "optional module boundary checks completed.\n";
