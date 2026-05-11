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
    $knownContractFiles = [
        'admin-menu.php',
        'extension-points.php',
        'member-group-rules.php',
        'menu-links.php',
        'output-slots.php',
        'paths.php',
        'privacy-export.php',
        'sitemap.php',
    ];

    foreach (toy_check_module_dirs() as $moduleDir) {
        $moduleFile = $moduleDir . '/module.php';
        if (!is_file($moduleFile)) {
            continue;
        }

        if (!is_file($moduleDir . '/install.sql')) {
            toy_check_add_error('Module install.sql is missing: ' . $moduleDir);
        }

        if (is_file($moduleDir . '/admin-menu.php') && !is_file($moduleDir . '/paths.php')) {
            toy_check_add_error('Module paths.php is required with admin-menu.php: ' . $moduleDir);
        }

        $metadata = include $moduleFile;
        if (!is_array($metadata)) {
            toy_check_add_error('Module metadata must return an array: ' . $moduleFile);
            continue;
        }

        $provides = isset($metadata['contracts']['provides']) && is_array($metadata['contracts']['provides'])
            ? $metadata['contracts']['provides']
            : [];
        $providedFiles = [];
        foreach ($provides as $contractFile) {
            $contractFile = is_string($contractFile) ? $contractFile : '';
            if (preg_match('/\A[a-z0-9][a-z0-9_.-]{0,80}\.php\z/', $contractFile) !== 1) {
                toy_check_add_error('Module contracts.provides entry is invalid: ' . $moduleFile . ' ' . $contractFile);
                continue;
            }

            $providedFiles[$contractFile] = true;
            if (!is_file($moduleDir . '/' . $contractFile)) {
                toy_check_add_error('Module declared contract file is missing: ' . $moduleDir . '/' . $contractFile);
            }
        }

        foreach ($knownContractFiles as $contractFile) {
            if (is_file($moduleDir . '/' . $contractFile) && !isset($providedFiles[$contractFile])) {
                toy_check_add_error('Module contract file must be declared in contracts.provides: ' . $moduleDir . '/' . $contractFile);
            }
        }
    }
}

function toy_check_module_versions_and_updates(): void
{
    foreach (toy_check_module_dirs() as $moduleDir) {
        $moduleFile = $moduleDir . '/module.php';
        if (!is_file($moduleFile)) {
            continue;
        }

        $metadata = include $moduleFile;
        if (!is_array($metadata)) {
            toy_check_add_error('Module metadata must return an array: ' . $moduleFile);
            continue;
        }

        $moduleVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
        if (preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $moduleVersion) !== 1) {
            toy_check_add_error('Module version must use YYYY.MM.NNN format: ' . $moduleFile);
            continue;
        }

        $updatesDir = $moduleDir . '/updates';
        if (!is_dir($updatesDir)) {
            continue;
        }

        foreach (toy_check_files($updatesDir, 'sql') as $updateFile) {
            $updateVersion = pathinfo($updateFile, PATHINFO_FILENAME);
            if (preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $updateVersion) !== 1) {
                toy_check_add_error('Module update SQL filename must use YYYY.MM.NNN.sql format: ' . $updateFile);
                continue;
            }

            if (strcmp($updateVersion, $moduleVersion) > 0) {
                toy_check_add_error('Module update SQL version must not be newer than module.php version: ' . $updateFile);
            }
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

function toy_check_module_route_conflicts(): void
{
    $routeOwners = [];
    foreach (toy_check_module_dirs() as $moduleDir) {
        $pathsFile = $moduleDir . '/paths.php';
        if (!is_file($pathsFile)) {
            continue;
        }

        $paths = include $pathsFile;
        if (!is_array($paths)) {
            toy_check_add_error('Module paths.php must return an array: ' . $pathsFile);
            continue;
        }

        foreach ($paths as $route => $actionRelativePath) {
            $route = (string) $route;
            $actionRelativePath = (string) $actionRelativePath;
            if (preg_match('/\A(GET|POST) \/.+\z/', $route) !== 1) {
                toy_check_add_error('Route key format is invalid: ' . $pathsFile . ' ' . $route);
                continue;
            }

            if (preg_match('/\Aactions\/[a-z0-9_\-\/]+\.php\z/', $actionRelativePath) !== 1 || strpos($actionRelativePath, '..') !== false) {
                toy_check_add_error('Action path is invalid: ' . $pathsFile . ' ' . $route . ' -> ' . $actionRelativePath);
                continue;
            }

            if (!is_file($moduleDir . '/' . $actionRelativePath)) {
                toy_check_add_error('Action file is missing: ' . $pathsFile . ' ' . $route . ' -> ' . $actionRelativePath);
                continue;
            }

            if (isset($routeOwners[$route])) {
                toy_check_add_error('Module route conflict: ' . $route . ' in ' . $routeOwners[$route] . ' and ' . $moduleDir);
                continue;
            }

            $routeOwners[$route] = $moduleDir;
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
toy_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-upload-helpers.php'));
toy_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-member-auth-policy.php'));
toy_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-admin-action-security.php'));
toy_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-community-release.php'));
toy_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-popup-layer-targets.php'));
toy_check_sql_files();
toy_check_module_contract_files();
toy_check_module_versions_and_updates();
toy_check_admin_menu_paths();
toy_check_module_route_conflicts();
toy_check_php_lint();

if ($errors !== []) {
    fwrite(STDERR, "toycore checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "toycore checks completed.\n";
