#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function toy_check_add_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function toy_check_run(string $command): void
{
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        toy_check_add_error('command failed: ' . $command);
    }
}

function toy_check_files(string $root, string $extension, array $skipDirs = []): array
{
    if (!is_dir($root)) {
        return [];
    }

    $files = [];
    $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
    $filter = new RecursiveCallbackFilterIterator(
        $directory,
        static function (SplFileInfo $current) use ($skipDirs): bool {
            if ($current->isDir()) {
                return !in_array($current->getFilename(), $skipDirs, true);
            }

            return true;
        }
    );

    $iterator = new RecursiveIteratorIterator($filter);
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === $extension) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);
    return $files;
}

function toy_check_module_dirs(): array
{
    $dirs = [];
    foreach (['modules', 'examples/sample_module'] as $root) {
        if (!is_dir($root)) {
            continue;
        }

        if (is_file($root . '/module.php')) {
            $dirs[] = $root;
            continue;
        }

        foreach (new DirectoryIterator($root) as $entry) {
            if ($entry->isDot() || !$entry->isDir()) {
                continue;
            }

            $dirs[] = $entry->getPathname();
        }
    }

    sort($dirs);
    return $dirs;
}

function toy_check_sql_files(): void
{
    foreach (['database', 'modules', 'examples'] as $root) {
        foreach (toy_check_files($root, 'sql') as $file) {
            if (filesize($file) <= 0) {
                toy_check_add_error('SQL file is empty: ' . $file);
            }
        }
    }
}

function toy_check_module_contract_files(): void
{
    foreach (toy_check_module_dirs() as $moduleDir) {
        if (!is_file($moduleDir . '/module.php')) {
            continue;
        }

        if (!is_file($moduleDir . '/install.sql')) {
            toy_check_add_error('Module install.sql is missing: ' . $moduleDir);
        }

        if (is_file($moduleDir . '/admin-menu.php') && !is_file($moduleDir . '/paths.php')) {
            toy_check_add_error('Module paths.php is required with admin-menu.php: ' . $moduleDir);
        }
    }
}

function toy_check_admin_menu_paths(): void
{
    foreach (toy_check_module_dirs() as $moduleDir) {
        $adminMenu = $moduleDir . '/admin-menu.php';
        $pathsFile = $moduleDir . '/paths.php';
        if (!is_file($adminMenu)) {
            continue;
        }

        $menu = file_get_contents($adminMenu);
        $paths = is_file($pathsFile) ? file_get_contents($pathsFile) : '';
        if (!is_string($menu) || !is_string($paths)) {
            toy_check_add_error('Module menu or paths file cannot be read: ' . $moduleDir);
            continue;
        }

        preg_match_all("/'path'\\s*=>\\s*'(\\/admin\\/[^']*)'/", $menu, $matches);
        foreach ($matches[1] as $path) {
            if (preg_match("/'GET\\s+" . preg_quote($path, '/') . "'\\s*=>/", $paths) !== 1) {
                toy_check_add_error('Admin menu path is missing from paths.php: ' . $moduleDir . ' ' . $path);
            }
        }
    }
}

function toy_check_php_lint(): void
{
    $phpFiles = toy_check_files('.', 'php', ['.git', 'dist']);
    foreach (toy_check_files('.tools/bin', '', []) as $file) {
        $header = file_get_contents($file, false, null, 0, 200);
        if (!is_string($header)) {
            continue;
        }

        if (str_contains($header, '<?php') || preg_match('/\A#!.*\bphp\b/', $header) === 1) {
            $phpFiles[] = $file;
        }
    }

    $phpFiles = array_values(array_unique($phpFiles));
    sort($phpFiles, SORT_STRING);

    foreach ($phpFiles as $file) {
        $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file);
        $output = [];
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            toy_check_add_error('PHP lint failed: ' . $file . "\n" . implode("\n", $output));
        }
    }
}

toy_check_run('git diff --check');
toy_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-retention-targets.php'));
toy_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-auth-runtime.php'));
toy_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-runtime-helpers.php'));
toy_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-output-helpers.php'));
toy_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-member-auth-policy.php'));
toy_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-admin-action-security.php'));
toy_check_sql_files();
toy_check_module_contract_files();
toy_check_admin_menu_paths();
toy_check_php_lint();

if ($errors !== []) {
    fwrite(STDERR, "toycore checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "toycore checks completed.\n";
