#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
require_once 'core/version.php';

$errors = [];

function sr_check_add_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_check_run(string $command): void
{
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        sr_check_add_error('command failed: ' . $command);
    }
}

function sr_check_files(string $root, string $extension, array $skipDirs = []): array
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

function sr_check_module_dirs(): array
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

function sr_check_sql_files(): void
{
    foreach (['database', 'modules', 'examples'] as $root) {
        foreach (sr_check_files($root, 'sql') as $file) {
            if (filesize($file) <= 0) {
                sr_check_add_error('SQL file is empty: ' . $file);
            }
        }
    }
}

function sr_check_sql_runtime_table_prefix_placeholders(): void
{
    foreach (['database', 'modules', 'examples'] as $root) {
        foreach (sr_check_files($root, 'sql') as $file) {
            $sql = file_get_contents($file);
            if (!is_string($sql)) {
                sr_check_add_error('SQL file cannot be read: ' . $file);
                continue;
            }

            if (preg_match("/TABLE_NAME\\s*=\\s*'sr_[a-z0-9_]+'/i", $sql) === 1) {
                sr_check_add_error('SQL update must use {{SR_TABLE_PREFIX}} inside INFORMATION_SCHEMA TABLE_NAME checks: ' . $file);
            }

            if (preg_match("/'\\s*(?:ALTER\\s+TABLE|INSERT\\s+INTO|UPDATE|FROM|JOIN|CREATE\\s+TABLE|DROP\\s+TABLE)\\s+sr_[a-z0-9_]+/i", $sql) === 1) {
                sr_check_add_error('SQL dynamic statement must use {{SR_TABLE_PREFIX}} for table names inside string literals: ' . $file);
            }
        }
    }
}

function sr_check_module_source_files(): void
{
    $blockedNames = [
        '.ds_store' => 'server config or secret files',
        '.dockercfg' => 'container registry auth files',
        '.dockerconfigjson' => 'container registry auth files',
        '.netrc' => 'credential files',
        '.npmrc' => 'package registry auth files',
        '.yarnrc' => 'package registry auth files',
        'authorized_keys' => 'SSH auth files',
        'auth.json' => 'package registry auth files',
        'credentials' => 'cloud credential files',
        'credentials.json' => 'cloud credential files',
        'id_dsa' => 'SSH key files',
        'id_ecdsa' => 'SSH key files',
        'id_ed25519' => 'SSH key files',
        'id_rsa' => 'SSH key files',
        'known_hosts' => 'SSH auth files',
        'service-account.json' => 'cloud service account files',
        'service_account.json' => 'cloud service account files',
    ];
    $blockedExtensions = [
        'asp' => true,
        'aspx' => true,
        'bak' => true,
        'bash' => true,
        'bat' => true,
        'backup' => true,
        'cgi' => true,
        'cmd' => true,
        'com' => true,
        'db' => true,
        'dll' => true,
        'dump' => true,
        'exe' => true,
        'hta' => true,
        'jsp' => true,
        'key' => true,
        'msi' => true,
        'old' => true,
        'orig' => true,
        'p12' => true,
        'pem' => true,
        'phar' => true,
        'pfx' => true,
        'pht' => true,
        'php3' => true,
        'php4' => true,
        'php5' => true,
        'php6' => true,
        'php7' => true,
        'php8' => true,
        'phtml' => true,
        'pl' => true,
        'py' => true,
        'shtml' => true,
        'sh' => true,
        'sqlite' => true,
        'sqlite3' => true,
    ];

    foreach (sr_check_module_dirs() as $moduleDir) {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($moduleDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($items as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }

            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($moduleDir) + 1));
            $basename = strtolower($item->getFilename());
            $extension = strtolower(pathinfo($item->getFilename(), PATHINFO_EXTENSION));

            if ($item->isDir() && sr_check_module_source_is_repository_meta_name($basename)) {
                sr_check_add_error('Module source must not include repository metadata directories: ' . $moduleDir . '/' . $relative);
                continue;
            }

            if ($item->isDir() && sr_check_module_source_is_server_config_name($basename)) {
                sr_check_add_error('Module source must not include server config or secret directories: ' . $moduleDir . '/' . $relative);
                continue;
            }

            if ($item->isDir() && sr_check_module_source_is_credential_meta_name($basename)) {
                sr_check_add_error('Module source must not include credential directories: ' . $moduleDir . '/' . $relative);
                continue;
            }

            if (!$item->isFile()) {
                continue;
            }

            if (sr_check_module_source_is_repository_meta_name($basename)) {
                sr_check_add_error('Module source must not include repository metadata files: ' . $moduleDir . '/' . $relative);
                continue;
            }

            if (sr_check_module_source_is_credential_meta_name($basename)) {
                sr_check_add_error('Module source must not include credential files: ' . $moduleDir . '/' . $relative);
                continue;
            }

            if (sr_check_module_source_is_server_config_name($basename)) {
                sr_check_add_error('Module source must not include server config or secret files: ' . $moduleDir . '/' . $relative);
                continue;
            }

            if (isset($blockedNames[$basename])) {
                sr_check_add_error('Module source must not include ' . $blockedNames[$basename] . ': ' . $moduleDir . '/' . $relative);
                continue;
            }

            if (sr_check_module_source_is_public_asset_executable($relative, $extension)) {
                sr_check_add_error('Module source assets must not include executable or SQL files: ' . $moduleDir . '/' . $relative);
                continue;
            }

            if ($extension !== '' && isset($blockedExtensions[$extension])) {
                sr_check_add_error('Module source must not include blocked executable extensions: ' . $moduleDir . '/' . $relative);
            }
        }
    }
}

function sr_check_module_source_is_repository_meta_name(string $basename): bool
{
    return in_array($basename, [
        '.git',
        '.gitattributes',
        '.gitignore',
        '.gitmodules',
        '.hg',
        '.hgignore',
        '.hgrc',
        '.svn',
    ], true);
}

function sr_check_module_source_is_credential_meta_name(string $basename): bool
{
    return in_array($basename, [
        '.aws',
        '.docker',
        '.gnupg',
        '.kube',
        '.ssh',
    ], true);
}

function sr_check_module_source_is_server_config_name(string $basename): bool
{
    return $basename === '.env'
        || str_starts_with($basename, '.env.')
        || $basename === '.htaccess'
        || str_starts_with($basename, '.htaccess.')
        || $basename === '.htpasswd'
        || str_starts_with($basename, '.htpasswd.')
        || $basename === '.user.ini'
        || str_starts_with($basename, '.user.ini.')
        || $basename === 'php.ini'
        || str_starts_with($basename, 'php.ini.')
        || $basename === 'web.config'
        || str_starts_with($basename, 'web.config.');
}

function sr_check_module_source_is_public_asset_executable(string $relative, string $extension): bool
{
    if (!str_starts_with($relative, 'assets/')) {
        return false;
    }

    return in_array($extension, [
        'phar',
        'php',
        'php3',
        'php4',
        'php5',
        'php6',
        'php7',
        'php8',
        'phtml',
        'sql',
    ], true);
}

function sr_check_version_format(string $version): string
{
    if (preg_match('/\Av?\d+\.\d+\.\d+\z/', $version) === 1) {
        return 'semver';
    }

    if (preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $version) === 1) {
        return 'date';
    }

    return '';
}

function sr_check_core_version_satisfies(string $minimumVersion): bool
{
    $coreVersion = SR_CORE_VERSION;
    $coreFormat = sr_check_version_format($coreVersion);
    $minimumFormat = sr_check_version_format($minimumVersion);
    if ($coreFormat === '' || $minimumFormat === '' || $coreFormat !== $minimumFormat) {
        return false;
    }

    if ($coreFormat === 'semver') {
        return version_compare(ltrim($coreVersion, 'vV'), ltrim($minimumVersion, 'vV'), '>=');
    }

    return strcmp($coreVersion, $minimumVersion) >= 0;
}

function sr_check_module_lifecycle_metadata(): void
{
    $requiredModules = ['member', 'admin', 'privacy'];
    $knownContractFiles = [
        'admin-menu.php' => true,
        'extension-points.php' => true,
        'dashboard.php' => true,
        'asset-exchange.php' => true,
        'member-group-rules.php' => true,
        'layout-options.php' => true,
        'menu-links.php' => true,
        'output-slots.php' => true,
        'privacy-cleanup.php' => true,
        'paths.php' => true,
        'privacy-export.php' => true,
        'sitemap.php' => true,
        'member-assets.php' => true,
        'member-withdrawal-assets.php' => true,
        'member-action-rows.php' => true,
        'member-only-routes.php' => true,
        'member-registration.php' => true,
        'homepage-candidates.php' => true,
        'editor-options.php' => true,
        'coupon-targets.php' => true,
        'coupon-references.php' => true,
        'banner-references.php' => true,
        'popup-layer-references.php' => true,
        'member-group-references.php' => true,
        'site-setting-references.php' => true,
        'logo-positions.php' => true,
        'notification-events.php' => true,
        'admin-notification-events.php' => true,
        'antispam-providers.php' => true,
        'oauth-providers.php' => true,
        'embed-manager-targets.php' => true,
        'embed-manager-url-targets.php' => true,
        'reaction-targets.php' => true,
    ];

    foreach ($requiredModules as $moduleKey) {
        if (!is_file('modules/' . $moduleKey . '/module.php') || !is_file('modules/' . $moduleKey . '/install.sql')) {
            sr_check_add_error('Required module files are missing: ' . $moduleKey);
        }
    }

    foreach (sr_check_module_dirs() as $moduleDir) {
        $moduleFile = $moduleDir . '/module.php';
        if (!is_file($moduleFile)) {
            continue;
        }

        $moduleKey = basename($moduleDir);
        $metadata = include $moduleFile;
        if (!is_array($metadata)) {
            sr_check_add_error('Module metadata must return an array: ' . $moduleFile);
            continue;
        }

        $name = is_string($metadata['name'] ?? null) ? trim((string) $metadata['name']) : '';
        if ($name === '') {
            sr_check_add_error('Module name is required: ' . $moduleFile);
        }

        $type = (string) ($metadata['type'] ?? 'module');
        if (!in_array($type, ['module', 'plugin'], true)) {
            sr_check_add_error('Module type must be module or plugin: ' . $moduleFile);
        }

        $saanraan = is_array($metadata['saanraan'] ?? null) ? $metadata['saanraan'] : [];
        $minVersion = is_string($saanraan['min_version'] ?? null) ? (string) $saanraan['min_version'] : '';
        $moduleContract = is_string($saanraan['module_contract'] ?? null) ? (string) $saanraan['module_contract'] : '';
        $testedWith = $saanraan['tested_with'] ?? null;

        if ($minVersion === '' || sr_check_version_format($minVersion) === '') {
            sr_check_add_error('Module saanraan.min_version is required: ' . $moduleFile);
        } elseif (!sr_check_core_version_satisfies($minVersion)) {
            sr_check_add_error('Module saanraan.min_version is newer than current core: ' . $moduleFile);
        }

        if ($moduleContract !== SR_MODULE_CONTRACT_VERSION) {
            sr_check_add_error('Module saanraan.module_contract must match current contract: ' . $moduleFile);
        }

        if (!is_array($testedWith) || $testedWith === []) {
            sr_check_add_error('Module saanraan.tested_with is required: ' . $moduleFile);
        }

        $contracts = is_array($metadata['contracts'] ?? null) ? $metadata['contracts'] : [];
        foreach (['provides', 'consumes'] as $contractKey) {
            if (!isset($contracts[$contractKey])) {
                continue;
            }

            if (!is_array($contracts[$contractKey])) {
                sr_check_add_error('Module contracts.' . $contractKey . ' must be an array: ' . $moduleFile);
                continue;
            }

            foreach ($contracts[$contractKey] as $contractFile) {
                if (!is_string($contractFile) || !isset($knownContractFiles[$contractFile])) {
                    sr_check_add_error('Module contracts.' . $contractKey . ' has an unknown contract file: ' . $moduleFile);
                }
            }
        }

        $requires = is_array($metadata['requires'] ?? null) ? $metadata['requires'] : [];
        $requiredModuleMap = is_array($requires['modules'] ?? null) ? $requires['modules'] : [];
        foreach ($requiredModuleMap as $key => $value) {
            $requiredModuleKey = is_string($key) ? $key : (string) $value;
            if (preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $requiredModuleKey) !== 1 || $requiredModuleKey === $moduleKey) {
                sr_check_add_error('Module requires.modules entry is invalid: ' . $moduleFile);
            }
        }
    }
}

function sr_check_module_lifecycle_ui_contract(): void
{
    $moduleActions = file_get_contents('modules/admin/helpers/module-actions.php');
    $moduleView = file_get_contents('modules/admin/views/modules.php');
    $adminLang = file_get_contents('modules/admin/lang/ko.php');
    $updatesHelper = file_get_contents('modules/admin/helpers/updates.php');
    $moduleSources = file_get_contents('modules/admin/helpers/module-sources.php');
    $moduleLifecycle = file_get_contents('core/helpers/module-lifecycle.php');
    $moduleSourceCore = file_get_contents('core/helpers/module-source.php');
    $moduleMetadataCore = file_get_contents('core/helpers/module-metadata.php');
    $schemaUpdatesCore = file_get_contents('core/helpers/schema-updates.php');
    if (!is_string($moduleActions) || !is_string($moduleView) || !is_string($adminLang) || !is_string($updatesHelper) || !is_string($moduleSources) || !is_string($moduleLifecycle) || !is_string($moduleSourceCore) || !is_string($moduleMetadataCore) || !is_string($schemaUpdatesCore)) {
        sr_check_add_error('Admin module lifecycle files cannot be read.');
        return;
    }

    $moduleLifecycleContent = $moduleActions . "\n" . $moduleSources . "\n" . $moduleLifecycle . "\n" . $moduleSourceCore . "\n" . $moduleMetadataCore . "\n" . $schemaUpdatesCore;
    foreach ([
        'function sr_module_lifecycle_state',
        'install_incomplete',
        'contract_error',
        'sql_pending',
        'file_only_update',
        'code_older',
        'sr_module_code_older_errors',
    ] as $needle) {
        if (!str_contains($moduleLifecycleContent, $needle)) {
            sr_check_add_error('Admin module lifecycle state handling is missing: ' . $needle);
        }
    }

    $moduleViewText = $moduleView . "\n" . $adminLang;
    foreach (['수명주기', '파일 재배치 필요', '설치 차단'] as $needle) {
        if (!str_contains($moduleViewText, $needle)) {
            sr_check_add_error('Admin module lifecycle UI label is missing: ' . $needle);
        }
    }

    foreach (['sr_admin_acquire_update_lock', 'update-failed.json', 'schema.update.failed', 'backup_confirmed'] as $needle) {
        if (!str_contains($updatesHelper, $needle)) {
            sr_check_add_error('Admin update safety marker is missing: ' . $needle);
        }
    }

    foreach (['sr_module_zip_upload_stats', 'sr_validate_extracted_module_tree', 'sr_module_source_file_errors', 'sr_module_upload_version_errors', '기존 모듈 백업을 복구할 수 없습니다.'] as $needle) {
        if (!str_contains($moduleLifecycleContent, $needle)) {
            sr_check_add_error('Admin module source safety marker is missing: ' . $needle);
        }
    }
}

function sr_check_module_contract_files(): void
{
    $knownContractFiles = [
        'admin-menu.php',
        'extension-points.php',
        'dashboard.php',
        'asset-exchange.php',
        'member-group-rules.php',
        'layout-options.php',
        'menu-links.php',
        'output-slots.php',
        'privacy-cleanup.php',
        'paths.php',
        'privacy-export.php',
        'sitemap.php',
        'member-assets.php',
        'member-withdrawal-assets.php',
        'member-action-rows.php',
        'member-only-routes.php',
        'member-registration.php',
        'homepage-candidates.php',
        'editor-options.php',
        'coupon-targets.php',
        'coupon-references.php',
        'banner-references.php',
        'popup-layer-references.php',
        'member-group-references.php',
        'site-setting-references.php',
        'logo-positions.php',
        'notification-events.php',
        'admin-notification-events.php',
        'antispam-providers.php',
        'oauth-providers.php',
        'embed-manager-targets.php',
        'embed-manager-url-targets.php',
        'reaction-targets.php',
    ];
    $requiredConsumes = [
        'admin' => [
            'dashboard.php',
        ],
    ];

    foreach (sr_check_module_dirs() as $moduleDir) {
        $moduleFile = $moduleDir . '/module.php';
        if (!is_file($moduleFile)) {
            continue;
        }

        $moduleKey = basename($moduleDir);
        if (!is_file($moduleDir . '/install.sql')) {
            sr_check_add_error('Module install.sql is missing: ' . $moduleDir);
        }

        if (is_file($moduleDir . '/admin-menu.php') && !is_file($moduleDir . '/paths.php')) {
            sr_check_add_error('Module paths.php is required with admin-menu.php: ' . $moduleDir);
        }

        $metadata = include $moduleFile;
        if (!is_array($metadata)) {
            sr_check_add_error('Module metadata must return an array: ' . $moduleFile);
            continue;
        }

        $provides = isset($metadata['contracts']['provides']) && is_array($metadata['contracts']['provides'])
            ? $metadata['contracts']['provides']
            : [];
        $consumes = isset($metadata['contracts']['consumes']) && is_array($metadata['contracts']['consumes'])
            ? $metadata['contracts']['consumes']
            : [];
        $consumedFiles = [];
        foreach ($consumes as $contractFile) {
            if (is_string($contractFile)) {
                $consumedFiles[$contractFile] = true;
            }
        }
        foreach ($requiredConsumes[$moduleKey] ?? [] as $contractFile) {
            if (!isset($consumedFiles[$contractFile])) {
                sr_check_add_error('Module contract file must be declared in contracts.consumes: ' . $moduleFile . ' ' . $contractFile);
            }
        }

        $providedFiles = [];
        foreach ($provides as $contractFile) {
            $contractFile = is_string($contractFile) ? $contractFile : '';
            if (preg_match('/\A[a-z0-9][a-z0-9_.-]{0,80}\.php\z/', $contractFile) !== 1) {
                sr_check_add_error('Module contracts.provides entry is invalid: ' . $moduleFile . ' ' . $contractFile);
                continue;
            }

            $providedFiles[$contractFile] = true;
            $contractPath = $moduleDir . '/' . $contractFile;
            if (!is_file($contractPath)) {
                sr_check_add_error('Module declared contract file is missing: ' . $contractPath);
                continue;
            }

            $contractContents = file_get_contents($contractPath);
            $contractContents = is_string($contractContents) ? $contractContents : '';
            if (
                $contractFile === 'member-action-rows.php'
                && preg_match('/return\s+(?:static\s+)?function\s*\(\s*PDO\s+\$pdo\s*,\s*int\s+\$accountId\s*\)\s*:\s*array/', $contractContents) !== 1
                && preg_match('/[\'"]rows_function[\'"]\s*=>/', $contractContents) !== 1
            ) {
                sr_check_add_error('Module member-action-rows.php must expose a rows provider shape: ' . $contractPath);
            }
            if (
                $contractFile === 'member-only-routes.php'
                && (
                    preg_match('/[\'"]protected_routes[\'"]\s*=>\s*\[/', $contractContents) !== 1
                    || preg_match('/[\'"]public_routes[\'"]\s*=>\s*\[/', $contractContents) !== 1
                    || preg_match('/[\'"]public_path_prefixes[\'"]\s*=>\s*\[/', $contractContents) !== 1
                )
            ) {
                sr_check_add_error('Module member-only-routes.php must expose route list arrays: ' . $contractPath);
            }
        }

        foreach ($knownContractFiles as $contractFile) {
            if (is_file($moduleDir . '/' . $contractFile) && !isset($providedFiles[$contractFile])) {
                sr_check_add_error('Module contract file must be declared in contracts.provides: ' . $moduleDir . '/' . $contractFile);
            }
        }
    }
}

function sr_check_module_versions_and_updates(): void
{
    foreach (sr_check_module_dirs() as $moduleDir) {
        $moduleFile = $moduleDir . '/module.php';
        if (!is_file($moduleFile)) {
            continue;
        }

        $metadata = include $moduleFile;
        if (!is_array($metadata)) {
            sr_check_add_error('Module metadata must return an array: ' . $moduleFile);
            continue;
        }

        $moduleVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
        if (preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $moduleVersion) !== 1) {
            sr_check_add_error('Module version must use YYYY.MM.NNN format: ' . $moduleFile);
            continue;
        }

        $updatesDir = $moduleDir . '/updates';
        if (!is_dir($updatesDir)) {
            continue;
        }

        foreach (sr_check_files($updatesDir, 'sql') as $updateFile) {
            $updateVersion = pathinfo($updateFile, PATHINFO_FILENAME);
            if (preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $updateVersion) !== 1) {
                sr_check_add_error('Module update SQL filename must use YYYY.MM.NNN.sql format: ' . $updateFile);
                continue;
            }

            if (strcmp($updateVersion, $moduleVersion) > 0) {
                sr_check_add_error('Module update SQL version must not be newer than module.php version: ' . $updateFile);
            }
        }
    }
}

function sr_check_admin_menu_paths(): void
{
    foreach (sr_check_module_dirs() as $moduleDir) {
        $adminMenu = $moduleDir . '/admin-menu.php';
        $pathsFile = $moduleDir . '/paths.php';
        if (!is_file($adminMenu)) {
            continue;
        }

        $menu = file_get_contents($adminMenu);
        $paths = is_file($pathsFile) ? file_get_contents($pathsFile) : '';
        if (!is_string($menu) || !is_string($paths)) {
            sr_check_add_error('Module menu or paths file cannot be read: ' . $moduleDir);
            continue;
        }

        preg_match_all("/'path'\\s*=>\\s*'(\\/admin\\/[^']*)'/", $menu, $matches);
        foreach ($matches[1] as $path) {
            if (preg_match("/'GET\\s+" . preg_quote($path, '/') . "'\\s*=>/", $paths) !== 1) {
                sr_check_add_error('Admin menu path is missing from paths.php: ' . $moduleDir . ' ' . $path);
            }
        }
    }
}

function sr_check_module_route_conflicts(): void
{
    $routeOwners = [];
    foreach (sr_check_module_dirs() as $moduleDir) {
        $pathsFile = $moduleDir . '/paths.php';
        if (!is_file($pathsFile)) {
            continue;
        }

        $paths = include $pathsFile;
        if (!is_array($paths)) {
            sr_check_add_error('Module paths.php must return an array: ' . $pathsFile);
            continue;
        }

        foreach ($paths as $route => $actionRelativePath) {
            $route = (string) $route;
            $actionRelativePath = (string) $actionRelativePath;
            if (!sr_check_valid_module_route($route)) {
                sr_check_add_error('Route key format is invalid: ' . $pathsFile . ' ' . $route);
                continue;
            }

            if (preg_match('/\Aactions\/[a-z0-9_\-\/]+\.php\z/', $actionRelativePath) !== 1 || strpos($actionRelativePath, '..') !== false) {
                sr_check_add_error('Action path is invalid: ' . $pathsFile . ' ' . $route . ' -> ' . $actionRelativePath);
                continue;
            }

            if (!is_file($moduleDir . '/' . $actionRelativePath)) {
                sr_check_add_error('Action file is missing: ' . $pathsFile . ' ' . $route . ' -> ' . $actionRelativePath);
                continue;
            }

            foreach ($routeOwners as $ownedRoute => $ownedModuleDir) {
                if (sr_check_module_routes_conflict((string) $ownedRoute, $route)) {
                    if (
                        $ownedModuleDir === $moduleDir
                        && (str_ends_with((string) $ownedRoute, '/*') xor str_ends_with($route, '/*'))
                    ) {
                        continue;
                    }

                    sr_check_add_error('Module route conflict: ' . $route . ' in ' . $ownedModuleDir . ' and ' . $moduleDir);
                    continue 2;
                }
            }

            $routeOwners[$route] = $moduleDir;
        }
    }
}

function sr_check_module_ui_kit_routes(): void
{
    foreach (sr_check_module_dirs() as $moduleDir) {
        $moduleKey = basename($moduleDir);
        $uiKitAction = $moduleDir . '/actions/ui-kit.php';
        if (!is_file($uiKitAction)) {
            continue;
        }

        $pathsFile = $moduleDir . '/paths.php';
        if (!is_file($pathsFile)) {
            sr_check_add_error('Module UI kit action requires paths.php: ' . $moduleDir);
            continue;
        }

        $paths = include $pathsFile;
        if (!is_array($paths)) {
            sr_check_add_error('Module paths.php must return an array: ' . $pathsFile);
            continue;
        }

        $routeKeys = array_map('strval', array_keys($paths));
        $uiKitRoute = 'GET /' . $moduleKey . '/ui-kit';
        $wildcardRoute = 'GET /' . $moduleKey . '/*';
        $uiKitIndex = array_search($uiKitRoute, $routeKeys, true);
        if (!is_int($uiKitIndex)) {
            sr_check_add_error('Module UI kit route is missing from paths.php: ' . $moduleDir . ' ' . $uiKitRoute);
            continue;
        }

        $wildcardIndex = array_search($wildcardRoute, $routeKeys, true);
        if (is_int($wildcardIndex) && $uiKitIndex > $wildcardIndex) {
            sr_check_add_error('Module UI kit route must be registered before wildcard route: ' . $moduleDir . ' ' . $uiKitRoute);
        }

        if ((string) ($paths[$uiKitRoute] ?? '') !== 'actions/ui-kit.php') {
            sr_check_add_error('Module UI kit route must map to actions/ui-kit.php: ' . $moduleDir . ' ' . $uiKitRoute);
        }
    }
}

function sr_check_module_public_ui_kit_stylesheets(): void
{
    $adminCommonCss = is_file('modules/admin/assets/common.css') ? file_get_contents('modules/admin/assets/common.css') : false;
    if (!is_string($adminCommonCss)) {
        sr_check_add_error('Admin common stylesheet is missing: modules/admin/assets/common.css');
    } elseif (!str_contains($adminCommonCss, 'url("../../../assets/fonts/material-symbols-outlined.ttf")')) {
        sr_check_add_error('Admin common stylesheet must use bundled Material Symbols fallback font path: modules/admin/assets/common.css');
    }

    $publicModuleCss = is_file('assets/module.css') ? file_get_contents('assets/module.css') : false;
    if (!is_string($publicModuleCss)) {
        sr_check_add_error('Public module stylesheet is missing: assets/module.css');
    } else {
        foreach (['.public-layout-', '--sr-bg:', ':root[data-color-scheme'] as $forbiddenMarker) {
            if (str_contains($publicModuleCss, $forbiddenMarker)) {
                sr_check_add_error('Public module stylesheet must not own layout or theme markers: assets/module.css ' . $forbiddenMarker);
                break;
            }
        }
    }

    $publicLayoutCss = is_file('assets/layout.css') ? file_get_contents('assets/layout.css') : false;
    if (!is_string($publicLayoutCss) || !str_contains($publicLayoutCss, '.public-layout-header')) {
        sr_check_add_error('Public layout stylesheet is missing or invalid: assets/layout.css');
    }

    foreach (['content', 'community', 'quiz', 'survey'] as $moduleKey) {
        $moduleResetStylesheetPath = 'modules/' . $moduleKey . '/assets/reset.css';
        $sourceReset = is_file('assets/reset.css') ? file_get_contents('assets/reset.css') : false;
        $moduleReset = is_file($moduleResetStylesheetPath) ? file_get_contents($moduleResetStylesheetPath) : false;
        if (is_string($sourceReset) && is_string($moduleReset)) {
            $sourceReset = str_replace('url("fonts/material-symbols-outlined.ttf")', 'url("__SR_MATERIAL_SYMBOLS_FONT__")', $sourceReset);
            $moduleReset = str_replace('url("../../../assets/fonts/material-symbols-outlined.ttf")', 'url("__SR_MATERIAL_SYMBOLS_FONT__")', $moduleReset);
        }
        if (!is_string($sourceReset) || !is_string($moduleReset) || $sourceReset !== $moduleReset) {
            sr_check_add_error('Module reset stylesheet copy must match source: ' . $moduleResetStylesheetPath . ' -> assets/reset.css');
        }

        $moduleUiStylesheetPath = 'modules/' . $moduleKey . '/assets/ui-kit.css';
        $moduleUiStylesheet = is_file($moduleUiStylesheetPath) ? file_get_contents($moduleUiStylesheetPath) : false;
        if (!is_string($moduleUiStylesheet) || !str_contains($moduleUiStylesheet, '.' . $moduleKey . '-ui-scope') || str_contains($moduleUiStylesheet, '.public-ui-') || str_contains($moduleUiStylesheet, '--public-ui-')) {
            sr_check_add_error('Module UI kit stylesheet must use module UI namespace: ' . $moduleUiStylesheetPath);
        }

        $moduleUiKitLayoutPath = 'modules/' . $moduleKey . '/assets/ui-kit-layout.css';
        $moduleUiKitLayout = is_file($moduleUiKitLayoutPath) ? file_get_contents($moduleUiKitLayoutPath) : false;
        if (!is_string($moduleUiKitLayout) || !str_contains($moduleUiKitLayout, '.' . $moduleKey . '-ui-kit') || str_contains($moduleUiKitLayout, '.public-ui-kit')) {
            sr_check_add_error('Module UI kit layout stylesheet must use module UI kit namespace: ' . $moduleUiKitLayoutPath);
        }

        foreach (['common.css', 'public-ui.css', 'public.css'] as $oldStylesheet) {
            $oldPath = 'modules/' . $moduleKey . '/assets/' . $oldStylesheet;
            if (file_exists($oldPath)) {
                sr_check_add_error('Module public stylesheet legacy file must be removed: ' . $oldPath);
            }
        }

        if (!is_file('modules/' . $moduleKey . '/assets/module.css')) {
            sr_check_add_error('Module public stylesheet is missing: modules/' . $moduleKey . '/assets/module.css');
        }
        if (!is_file('modules/' . $moduleKey . '/assets/module.js')) {
            sr_check_add_error('Module public script is missing: modules/' . $moduleKey . '/assets/module.js');
        }

        $moduleCssPath = 'modules/' . $moduleKey . '/assets/module.css';
        $moduleCss = is_file($moduleCssPath) ? file_get_contents($moduleCssPath) : false;
        if (is_string($moduleCss) && str_contains($moduleCss, '.' . $moduleKey . '-layout-')) {
            sr_check_add_error('Module public stylesheet must not own layout selectors: ' . $moduleCssPath);
        }
        if (is_string($moduleCss) && preg_match('/\.' . preg_quote($moduleKey, '/') . '-skin-[a-z0-9_-]+/', $moduleCss) === 1) {
            sr_check_add_error('Module public stylesheet must not own skin selectors: ' . $moduleCssPath);
        }

        if (in_array($moduleKey, ['content', 'community', 'quiz', 'survey'], true)) {
            $moduleLayoutCssPath = 'modules/' . $moduleKey . '/assets/layout.css';
            $moduleLayoutCss = is_file($moduleLayoutCssPath) ? file_get_contents($moduleLayoutCssPath) : false;
            if (!is_string($moduleLayoutCss)) {
                sr_check_add_error('Module public layout stylesheet is missing: ' . $moduleLayoutCssPath);
            } else {
                if (!str_contains($moduleLayoutCss, '.' . $moduleKey . '-layout-main')) {
                    sr_check_add_error('Module public layout stylesheet is missing module layout selectors: ' . $moduleLayoutCssPath);
                }
                if ($moduleKey !== 'public' && str_contains($moduleLayoutCss, '.public-layout-')) {
                    sr_check_add_error('Module public layout stylesheet must not use public layout selectors: ' . $moduleLayoutCssPath);
                }
            }

            $moduleLayoutScriptPath = 'modules/' . $moduleKey . '/assets/layout.js';
            if (!is_file($moduleLayoutScriptPath)) {
                sr_check_add_error('Module public layout script is missing: ' . $moduleLayoutScriptPath);
            }
        }

        foreach ([
            'modules/' . $moduleKey . '/assets',
            'modules/' . $moduleKey . '/layouts',
            'modules/' . $moduleKey . '/skins',
        ] as $modulePublicNamespaceRoot) {
            foreach (sr_check_files($modulePublicNamespaceRoot, 'php') as $modulePublicNamespaceFile) {
                $modulePublicNamespaceSource = file_get_contents($modulePublicNamespaceFile);
                if (is_string($modulePublicNamespaceSource) && preg_match('/\b(?:public|sr-public)-[a-z0-9_-]+/', $modulePublicNamespaceSource) === 1) {
                    sr_check_add_error('Module public asset must not use public-prefixed classes: ' . $modulePublicNamespaceFile);
                }
            }
            foreach (sr_check_files($modulePublicNamespaceRoot, 'css') as $modulePublicNamespaceFile) {
                $modulePublicNamespaceSource = file_get_contents($modulePublicNamespaceFile);
                if (is_string($modulePublicNamespaceSource) && preg_match('/\b(?:public|sr-public)-[a-z0-9_-]+/', $modulePublicNamespaceSource) === 1) {
                    sr_check_add_error('Module public asset must not use public-prefixed classes: ' . $modulePublicNamespaceFile);
                }
            }
            foreach (sr_check_files($modulePublicNamespaceRoot, 'js') as $modulePublicNamespaceFile) {
                $modulePublicNamespaceSource = file_get_contents($modulePublicNamespaceFile);
                if (is_string($modulePublicNamespaceSource) && preg_match('/\b(?:public|sr-public)-[a-z0-9_-]+/', $modulePublicNamespaceSource) === 1) {
                    sr_check_add_error('Module public asset must not use public-prefixed classes: ' . $modulePublicNamespaceFile);
                }
            }
        }
        $moduleUiKitView = 'modules/' . $moduleKey . '/views/ui-kit.php';
        $moduleUiKitViewSource = is_file($moduleUiKitView) ? file_get_contents($moduleUiKitView) : false;
        if (is_string($moduleUiKitViewSource) && preg_match('/\b(?:public|sr-public)-[a-z0-9_-]+/', $moduleUiKitViewSource) === 1) {
            sr_check_add_error('Module UI kit view must not use public-prefixed classes: ' . $moduleUiKitView);
        }

        if ($moduleKey === 'quiz' && !is_file('modules/quiz/assets/skin.css')) {
            sr_check_add_error('Module public skin stylesheet is missing: modules/quiz/assets/skin.css');
        }

        $helperFile = $moduleKey === 'community'
            ? 'modules/community/helpers/levels.php'
            : 'modules/' . $moduleKey . '/helpers.php';
        $source = is_file($helperFile) ? file_get_contents($helperFile) : false;
        if (!is_string($source)) {
            sr_check_add_error('Module public layout helper cannot be read: ' . $helperFile);
            continue;
        }

        $functionName = 'sr_' . $moduleKey . '_public_layout_context';
        $pattern = '/function\s+' . preg_quote($functionName, '/') . '\s*\([^)]*\):\s*array(?<body>.*?)(?=\nfunction\s+sr_|\z)/s';
        if (preg_match($pattern, $source, $matches) !== 1) {
            sr_check_add_error('Module public layout context helper is missing: ' . $helperFile . ' ' . $functionName);
            continue;
        }

        $body = (string) ($matches['body'] ?? '');
        $expectedOrder = [
            "'/modules/" . $moduleKey . "/assets/reset.css'",
            "'/modules/" . $moduleKey . "/assets/ui-kit.css'",
            "'/modules/" . $moduleKey . "/assets/module.css'",
        ];
        if (in_array($moduleKey, ['content', 'community', 'quiz', 'survey'], true)) {
            if (!str_contains($body, 'sr_public_layout_module_stylesheet($layoutKey)') || !str_contains($body, '$stylesheets[] = $layoutStylesheet')) {
                sr_check_add_error('Module public layout context selected layout stylesheet helper is missing: ' . $helperFile);
            }
            if (!str_contains($body, "'/modules/" . $moduleKey . "/assets/module.js'") || !str_contains($body, '$context[\'scripts\']')) {
                sr_check_add_error('Module public layout context module script is missing: ' . $helperFile);
            }
        }
        $lastIndex = -1;
        foreach ($expectedOrder as $marker) {
            $index = strpos($body, $marker);
            if ($index === false || $index < $lastIndex) {
                sr_check_add_error('Module public layout context stylesheet order is invalid: ' . $helperFile . ' ' . $marker);
                break;
            }
            $lastIndex = $index;
        }
        if (in_array($moduleKey, ['content', 'community', 'quiz', 'survey'], true)) {
            $uiKitIndex = strpos($body, "'/modules/" . $moduleKey . "/assets/ui-kit.css'");
            $layoutIndex = strpos($body, '$stylesheets[] = $layoutStylesheet');
            $moduleIndex = strpos($body, "'/modules/" . $moduleKey . "/assets/module.css'");
            if ($uiKitIndex === false || $layoutIndex === false || $moduleIndex === false || $layoutIndex < $uiKitIndex || $layoutIndex > $moduleIndex) {
                sr_check_add_error('Module public layout context selected layout stylesheet order is invalid: ' . $helperFile);
            }
        }

        if ($moduleKey === 'quiz') {
            $skinMarker = "'/modules/quiz/assets/skin.css'";
            $skinIndex = strpos($body, $skinMarker);
            if ($skinIndex === false || $skinIndex < $lastIndex) {
                sr_check_add_error('Module public skin stylesheet order is invalid: ' . $helperFile . ' ' . $skinMarker);
            }
        }

        $uiKitLayoutMarker = "'/modules/" . $moduleKey . "/assets/ui-kit-layout.css'";
        if (!str_contains($source, $uiKitLayoutMarker)) {
            sr_check_add_error('Module UI kit layout stylesheet is missing from helper: ' . $helperFile . ' ' . $uiKitLayoutMarker);
        }

        if (preg_match('/\b(?:public|sr-public)-[a-z0-9_-]+/', $source) === 1) {
            sr_check_add_error('Module public layout helper must not use public-prefixed classes: ' . $helperFile);
        }
    }

    foreach ([
        'layouts/public/basic/layout.php' => '/assets/public-layout.js',
        'modules/content/layouts/basic/layout.php' => '/modules/content/assets/layout.js',
        'modules/community/layouts/basic/layout.php' => '/modules/community/assets/layout.js',
        'modules/quiz/layouts/basic/layout.php' => '/modules/quiz/assets/layout.js',
        'modules/survey/layouts/basic/layout.php' => '/modules/survey/assets/layout.js',
    ] as $layoutFile => $layoutScript) {
        $layoutSource = is_file($layoutFile) ? file_get_contents($layoutFile) : false;
        if (!is_string($layoutSource)) {
            sr_check_add_error('Public layout template cannot be read: ' . $layoutFile);
            continue;
        }
        if (!str_contains($layoutSource, '$layoutContextScripts') || !str_contains($layoutSource, 'sr_script_tags($layoutScripts)')) {
            sr_check_add_error('Public layout template context script rendering is missing: ' . $layoutFile);
        }
        if (!str_contains($layoutSource, $layoutScript)) {
            sr_check_add_error('Public layout template layout script is missing: ' . $layoutFile . ' ' . $layoutScript);
        }
        if (str_contains($layoutSource, '/assets/quiz-layout.js')) {
            sr_check_add_error('Public layout template uses legacy quiz layout script path: ' . $layoutFile);
        }
    }
}

function sr_check_banner_public_layout_slots(): void
{
    $bannerHelper = 'modules/banner/helpers.php';
    $bannerSource = is_file($bannerHelper) ? file_get_contents($bannerHelper) : false;
    if (!is_string($bannerSource)) {
        sr_check_add_error('Banner helper cannot be read: ' . $bannerHelper);
        return;
    }

    foreach ([
        'sr_banner_layout_targets',
        'sr_public_layout_options($pdo)',
        "'point_key' => \$providerModuleKey . '.layout'",
        "'slot_key' => 'before_layout'",
        "'slot_key' => 'before_footer'",
    ] as $marker) {
        if (!str_contains($bannerSource, $marker)) {
            sr_check_add_error('Banner layout target marker is missing: ' . $marker);
        }
    }

    foreach (['content', 'community', 'quiz', 'survey'] as $moduleKey) {
        $layoutFile = 'modules/' . $moduleKey . '/layouts/basic/layout.php';
        $layoutSource = is_file($layoutFile) ? file_get_contents($layoutFile) : false;
        if (!is_string($layoutSource)) {
            sr_check_add_error('Module public layout template cannot be read for banner slots: ' . $layoutFile);
            continue;
        }

        foreach ([
            "'module_key' => '" . $moduleKey . "'",
            "'point_key' => '" . $moduleKey . ".layout'",
            "'slot_key' => 'before_layout'",
            "'slot_key' => 'before_footer'",
            '$layoutModuleBeforeLayoutHtml',
            '$layoutModuleBeforeFooterHtml',
        ] as $marker) {
            if (!str_contains($layoutSource, $marker)) {
                sr_check_add_error('Module public layout banner slot marker is missing: ' . $layoutFile . ' ' . $marker);
            }
        }

        $bodyIndex = strpos($layoutSource, '<body class=');
        $topSlotIndex = strpos($layoutSource, '<?php echo $layoutModuleBeforeLayoutHtml; ?>');
        $headerIndex = strpos($layoutSource, '<header class="' . $moduleKey . '-layout-header');
        if ($bodyIndex === false || $topSlotIndex === false || $headerIndex === false || !($bodyIndex < $topSlotIndex && $topSlotIndex < $headerIndex)) {
            sr_check_add_error('Module public layout banner top slot must render before layout header: ' . $layoutFile);
        }

        $mainIndex = strpos($layoutSource, '<div class="' . $moduleKey . '-layout-main');
        $bottomSlotIndex = strpos($layoutSource, '<?php echo $layoutModuleBeforeFooterHtml; ?>');
        $footerIndex = strpos($layoutSource, '<footer class="' . $moduleKey . '-layout-footer');
        if ($mainIndex === false || $bottomSlotIndex === false || $footerIndex === false || !($mainIndex < $bottomSlotIndex && $bottomSlotIndex < $footerIndex)) {
            sr_check_add_error('Module public layout banner bottom slot must render before layout footer: ' . $layoutFile);
        }
    }
}

function sr_check_module_ui_kit_samples_match_public(): void
{
    $publicSampleDir = 'layouts/public/basic/ui-kit-samples';
    $publicSampleFiles = is_dir($publicSampleDir) ? glob($publicSampleDir . '/*.php') : false;
    if ($publicSampleFiles === false || $publicSampleFiles === []) {
        sr_check_add_error('Public UI kit sample files are missing.');
        return;
    }

    sort($publicSampleFiles, SORT_STRING);
    foreach (['content', 'community', 'quiz', 'survey'] as $moduleKey) {
        $moduleSampleDir = 'modules/' . $moduleKey . '/views/ui-kit-samples';
        foreach ($publicSampleFiles as $publicSampleFile) {
            $basename = basename($publicSampleFile);
            $moduleSampleFile = $moduleSampleDir . '/' . $basename;
            if (!is_file($moduleSampleFile)) {
                sr_check_add_error('Module UI kit sample file is missing: ' . $moduleSampleFile);
                continue;
            }

            $publicSource = file_get_contents($publicSampleFile);
            $moduleSource = file_get_contents($moduleSampleFile);
            if (!is_string($publicSource) || !is_string($moduleSource) || $publicSource !== $moduleSource) {
                sr_check_add_error('Module UI kit sample must match public UI kit sample: ' . $moduleSampleFile);
            }
        }
    }
}

function sr_check_valid_module_route(string $route): bool
{
    if (preg_match('/\A(GET|POST) (\/[^\x00-\x1F\x7F\\\\]*)\z/', $route, $matches) !== 1) {
        return false;
    }

    $path = (string) $matches[2];
    if ($path === '/' || str_starts_with($path, '//')) {
        return false;
    }

    $asteriskCount = substr_count($path, '*');
    if ($asteriskCount === 0) {
        return true;
    }

    return $asteriskCount === 1 && str_ends_with($path, '/*') && strlen($path) > 2;
}

function sr_check_module_routes_conflict(string $leftRoute, string $rightRoute): bool
{
    if (!sr_check_valid_module_route($leftRoute) || !sr_check_valid_module_route($rightRoute)) {
        return false;
    }

    [$leftMethod, $leftPath] = explode(' ', $leftRoute, 2);
    [$rightMethod, $rightPath] = explode(' ', $rightRoute, 2);
    if ($leftMethod !== $rightMethod) {
        return false;
    }

    if ($leftPath === $rightPath) {
        return true;
    }

    $leftWildcard = str_ends_with($leftPath, '/*');
    $rightWildcard = str_ends_with($rightPath, '/*');
    if (!$leftWildcard && !$rightWildcard) {
        return false;
    }

    $leftPrefix = $leftWildcard ? substr($leftPath, 0, -1) : $leftPath;
    $rightPrefix = $rightWildcard ? substr($rightPath, 0, -1) : $rightPath;

    if ($leftWildcard && $rightWildcard) {
        return str_starts_with($leftPrefix, $rightPrefix) || str_starts_with($rightPrefix, $leftPrefix);
    }

    return $leftWildcard
        ? str_starts_with($rightPath, $leftPrefix)
        : str_starts_with($leftPath, $rightPrefix);
}

function sr_check_php_lint(): void
{
    $phpFiles = sr_check_files('.', 'php', ['.git', 'config', 'dist', 'storage']);
    foreach (sr_check_files('.tools/bin', '', []) as $file) {
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
            sr_check_add_error('PHP lint failed: ' . $file . "\n" . implode("\n", $output));
        }
    }
}

function sr_check_admin_anchor_tabs_scroll_spy(): void
{
    $script = file_get_contents('modules/admin/assets/admin-shell.js');
    if (!is_string($script)) {
        sr_check_add_error('Admin shell script cannot be read.');
        return;
    }

    foreach ([
        "document.querySelectorAll('.sticky-tabs.anchor-tabs')",
        'initAnchorTabsScrollSpy',
        'setAnchorTabActive',
        "link.setAttribute('aria-current', 'location')",
        "window.addEventListener('scroll', requestSync, { passive: true })",
        'anchorTabs.forEach(initAnchorTabsScrollSpy)',
    ] as $marker) {
        if (!str_contains($script, $marker)) {
            sr_check_add_error('Admin anchor tabs scroll spy marker missing: ' . $marker);
        }
    }
}

function sr_check_quiz_survey_skin_files(): void
{
    $contracts = [
        'quiz' => ['home', 'view', 'result'],
        'survey' => ['home', 'view', 'complete'],
    ];

    foreach ($contracts as $moduleKey => $views) {
        $skinDir = 'modules/' . $moduleKey . '/skins/basic';
        if (!is_dir($skinDir)) {
            sr_check_add_error('Default skin directory is missing: ' . $skinDir);
            continue;
        }

        foreach ($views as $view) {
            $file = $skinDir . '/' . $view . '.php';
            if (!is_file($file)) {
                sr_check_add_error('Default skin view is missing: ' . $file);
            }
        }
    }
}

sr_check_run('git diff --check');
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-retention-targets.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-auth-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-runtime-helpers.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-output-helpers.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-rich-text-sanitizer.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-rich-text-sanitizer-policy.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-upload-helpers.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-module-status.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-verification-template.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-release-verification-records.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-installed-gate-status.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-operational-status.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-privacy-contract-matrix.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-policy-documents-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-privacy-export-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-privacy-cleanup-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-asset-reconciliation.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-asset-recovery-queue.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-asset-idempotency.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-asset-deadlock-retry.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-paid-download-delivery.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-content-file-cleanup-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-content-copy-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-view-count-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-asset-exchange-logs.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-asset-exchange-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-coupon-admin-validation.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-admin-form-validation.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-admin-ux-writing.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-coupon-redemption-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-coupon-claim-campaign-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-admin-asset-limits.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-dependency-policy.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-htmlpurifier-vendor-integrity.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-htmlpurifier-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-ckeditor-assets.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-browser-qa.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-release-package-policy.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/release-preflight.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/release-package-dry-run.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-tool-gate-coverage.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-admin-pagination-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-community-board-copy-limits.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-community-board-copy-job-lock.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-community-level-recalculate-job.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-community-board-settings.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-community-feed-cache-contract.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-group-delete-detach-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-community-attachment-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-community-asset-recovery.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-performance-policy.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-performance-baseline.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-contribution-guide.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-security-policy.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-security-baseline.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-request-contract-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-deployment-protection.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-deployment-config.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-installer-module-list.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-risk-register.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-positioning.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-doc-links.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-member-auth-policy.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-privacy-request-admin-note.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-member-oauth-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-mention-ux.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-notification-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-admin-navigation-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-admin-action-security.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-module-upload-action-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-module-source-file-policy.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-antispam-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-community-release.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-community-message-policy.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-community-guest-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-community-privacy-consent.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-popup-layer-targets.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-content-scheduled-scope.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-link-card.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-embed-manager-contracts.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-read-reference-contracts.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-reaction-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-quiz-consistency.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-quiz-reward-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-quiz-delete-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-reward-abuse-standards.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-asset-settlement-contract.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-member-assets-transaction-contract.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-milestone-28-currency-policy.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-survey-consistency.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-survey-response-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-survey-reward-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-survey-statistics-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-survey-export-runtime.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-site-menu-seed-order.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-site-reset-fixtures.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-logo-manager-favicon.php'));
sr_check_run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-seo-runtime.php'));
sr_check_sql_files();
sr_check_sql_runtime_table_prefix_placeholders();
sr_check_module_source_files();
sr_check_module_lifecycle_metadata();
sr_check_module_lifecycle_ui_contract();
sr_check_module_contract_files();
sr_check_module_versions_and_updates();
sr_check_admin_menu_paths();
sr_check_module_route_conflicts();
sr_check_module_ui_kit_routes();
sr_check_module_public_ui_kit_stylesheets();
sr_check_banner_public_layout_slots();
sr_check_module_ui_kit_samples_match_public();
sr_check_admin_anchor_tabs_scroll_spy();
sr_check_quiz_survey_skin_files();
sr_check_php_lint();

if ($errors !== []) {
    fwrite(STDERR, "saanraan checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "saanraan checks completed.\n";
