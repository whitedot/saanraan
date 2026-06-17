#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}
chdir($root);

require_once SR_ROOT . '/core/version.php';
require_once SR_ROOT . '/core/helpers/settings.php';
require_once SR_ROOT . '/core/helpers/output.php';
require_once SR_ROOT . '/core/helpers/module-source.php';
require_once SR_ROOT . '/core/helpers/module-lifecycle.php';

function sr_check_module_source_policy_remove_dir(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($directory);
}

function sr_check_module_source_policy_write_file(string $root, string $relative): void
{
    $path = $root . '/' . $relative;
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create fixture directory: ' . $directory);
    }

    file_put_contents($path, 'fixture');
}

function sr_check_module_source_policy_write_module(string $moduleKey, string $route): void
{
    $moduleDir = SR_ROOT . '/modules/' . $moduleKey;
    if (!is_dir($moduleDir . '/actions') && !mkdir($moduleDir . '/actions', 0777, true) && !is_dir($moduleDir . '/actions')) {
        throw new RuntimeException('Cannot create fixture module directory: ' . $moduleDir);
    }

    file_put_contents(
        $moduleDir . '/module.php',
        "<?php\nreturn [\n"
        . "    'name' => '" . $moduleKey . "',\n"
        . "    'version' => '2026.06.001',\n"
        . "    'type' => 'module',\n"
        . "    'saanraan' => [\n"
        . "        'min_version' => '0.2.0',\n"
        . "        'tested_with' => ['0.2.0'],\n"
        . "        'module_contract' => '2.0',\n"
        . "    ],\n"
        . "    'contracts' => [\n"
        . "        'provides' => ['paths.php'],\n"
        . "    ],\n"
        . "];\n"
    );
    file_put_contents(
        $moduleDir . '/paths.php',
        "<?php\nreturn [\n"
        . "    '" . $route . "' => 'actions/page.php',\n"
        . "];\n"
    );
    file_put_contents($moduleDir . '/actions/page.php', "<?php\n");
}

function sr_check_module_source_policy_create_test_zip(string $root, string $zipPath): void
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('Cannot create fixture zip.');
    }

    foreach ([
        'pkg/.env.local',
        'pkg/module/module.php',
        'pkg/module/install.sql',
    ] as $relative) {
        $zip->addFile($root . '/' . $relative, $relative);
    }

    $zip->close();
}

function sr_check_module_source_policy_zip_files(string $root, string $zipPath, array $relativePaths): void
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('Cannot create fixture zip.');
    }

    foreach ($relativePaths as $relative) {
        $zip->addFile($root . '/' . $relative, $relative);
    }

    $zip->close();
}

function sr_check_module_source_policy_metadata(string $name = 'Route Test'): array
{
    return [
        'name' => $name,
        'version' => '2026.06.001',
        'type' => 'module',
        'saanraan' => [
            'min_version' => '0.2.0',
            'tested_with' => ['0.2.0'],
            'module_contract' => '2.0',
        ],
        'contracts' => [
            'provides' => ['paths.php'],
        ],
    ];
}

$fixtureRoot = sys_get_temp_dir() . '/sr-module-source-policy-' . bin2hex(random_bytes(6));
mkdir($fixtureRoot, 0777, true);

try {
    $allowedDir = $fixtureRoot . '/allowed';
    mkdir($allowedDir, 0777, true);
    foreach (['module.php', 'install.sql', 'assets/app.js', 'views/page.html', 'assets/style.css'] as $relative) {
        sr_check_module_source_policy_write_file($allowedDir, $relative);
    }

    $allowedErrors = sr_module_source_file_errors($allowedDir);
    if ($allowedErrors !== []) {
        fwrite(STDERR, "Allowed module source fixture was rejected:\n" . implode("\n", $allowedErrors) . "\n");
        exit(1);
    }

    $blockedDir = $fixtureRoot . '/blocked';
    mkdir($blockedDir, 0777, true);
    $blockedFiles = [
        '.env.local',
        '.gitignore',
        '.npmrc',
        'composer/auth.json',
        'keys/id_rsa',
        'certs/client.p12',
        'data/export.sqlite',
        'backup/module.bak',
        'actions/route.php7',
        'actions/hook.pht',
        'actions/template.phtml',
        'assets/runtime.php',
        'assets/schema.sql',
        'bin/task.sh',
    ];
    foreach ($blockedFiles as $relative) {
        sr_check_module_source_policy_write_file($blockedDir, $relative);
    }

    $blockedErrors = sr_module_source_file_errors($blockedDir);
    foreach ($blockedFiles as $relative) {
        $found = false;
        foreach ($blockedErrors as $error) {
            if (str_contains($error, $relative)) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            fwrite(STDERR, 'Blocked module source fixture was not rejected: ' . $relative . "\n");
            fwrite(STDERR, implode("\n", $blockedErrors) . "\n");
            exit(1);
        }
    }

    $packageDir = $fixtureRoot . '/package';
    mkdir($packageDir . '/module/assets', 0777, true);
    foreach ([
        '.env.local',
        'module/module.php',
        'module/install.sql',
        'module/assets/.gitkeep',
    ] as $relative) {
        sr_check_module_source_policy_write_file($packageDir, $relative);
    }

    $packageErrors = sr_module_source_file_errors($packageDir);
    $foundPackageRootEnv = false;
    foreach ($packageErrors as $error) {
        if (str_contains($error, '.env.local')) {
            $foundPackageRootEnv = true;
            break;
        }
    }

    if (!$foundPackageRootEnv) {
        fwrite(STDERR, "Package root forbidden file was not rejected:\n" . implode("\n", $packageErrors) . "\n");
        exit(1);
    }

    foreach ($packageErrors as $error) {
        if (str_contains($error, '.gitkeep')) {
            fwrite(STDERR, "Allowed placeholder file was rejected:\n" . implode("\n", $packageErrors) . "\n");
            exit(1);
        }
    }

    $routeDir = $fixtureRoot . '/routecheck';
    mkdir($routeDir . '/actions', 0777, true);
    file_put_contents($routeDir . '/module.php', "<?php\nreturn [];\n");
    sr_check_module_source_policy_write_file($routeDir, 'install.sql');
    sr_check_module_source_policy_write_file($routeDir, 'actions/page.php');
    file_put_contents(
        $routeDir . '/paths.php',
        "<?php\nreturn [\n"
        . "    'GET /route-check' => 'actions/page.php',\n"
        . "];\n"
    );

    $routeErrors = sr_validate_module_source('routecheck', $routeDir, sr_check_module_source_policy_metadata());
    if ($routeErrors !== []) {
        fwrite(STDERR, "Valid module source route fixture was rejected:\n" . implode("\n", $routeErrors) . "\n");
        exit(1);
    }

    file_put_contents(
        $routeDir . '/paths.php',
        "<?php\nreturn [\n"
        . "    'FETCH /bad' => 'actions/page.php',\n"
        . "];\n"
    );
    $routeErrors = sr_validate_module_source('routecheck', $routeDir, sr_check_module_source_policy_metadata());
    if (!in_array('routecheck 모듈 주소 경로 형식이 올바르지 않습니다: FETCH /bad', $routeErrors, true)) {
        fwrite(STDERR, "Invalid module source route was not rejected:\n" . implode("\n", $routeErrors) . "\n");
        exit(1);
    }

    file_put_contents(
        $routeDir . '/paths.php',
        "<?php\nreturn [\n"
        . "    'GET /route-check' => 'actions/missing.php',\n"
        . "];\n"
    );
    $routeErrors = sr_validate_module_source('routecheck', $routeDir, sr_check_module_source_policy_metadata());
    if (!in_array('routecheck 모듈 실행 파일을 찾을 수 없습니다: GET /route-check', $routeErrors, true)) {
        fwrite(STDERR, "Missing module source action was not rejected:\n" . implode("\n", $routeErrors) . "\n");
        exit(1);
    }

    file_put_contents(
        $routeDir . '/paths.php',
        "<?php\n"
        . "\$route = 'GET /route-check';\n"
        . "return [\$route => 'actions/page.php'];\n"
    );
    $routeErrors = sr_validate_module_source('routecheck', $routeDir, sr_check_module_source_policy_metadata());
    if (!in_array('routecheck 모듈의 paths.php는 정적 문자열 배열을 반환해야 합니다.', $routeErrors, true)) {
        fwrite(STDERR, "Dynamic module source paths.php was not rejected:\n" . implode("\n", $routeErrors) . "\n");
        exit(1);
    }

    file_put_contents(
        $routeDir . '/paths.php',
        "<?php\n"
        . "file_put_contents(sys_get_temp_dir() . '/sr-route-side-effect', 'x');\n"
        . "return [\n"
        . "    'GET /route-check' => 'actions/page.php',\n"
        . "];\n"
    );
    $routeErrors = sr_validate_module_source('routecheck', $routeDir, sr_check_module_source_policy_metadata());
    if (!in_array('routecheck 모듈의 paths.php는 정적 문자열 배열을 반환해야 합니다.', $routeErrors, true)) {
        fwrite(STDERR, "Module source paths.php with a pre-return statement was not rejected:\n" . implode("\n", $routeErrors) . "\n");
        exit(1);
    }

    mkdir($routeDir . '/updates', 0777, true);
    sr_check_module_source_policy_write_file($routeDir, 'updates/not-a-version.sql');
    $updateErrors = sr_validate_module_source('routecheck', $routeDir, sr_check_module_source_policy_metadata());
    if (!in_array('routecheck 모듈 업데이트 SQL 파일명은 updates/YYYY.MM.NNN.sql 형식이어야 합니다: updates/not-a-version.sql', $updateErrors, true)) {
        fwrite(STDERR, "Invalid module source update SQL filename was not rejected:\n" . implode("\n", $updateErrors) . "\n");
        exit(1);
    }

    unlink($routeDir . '/updates/not-a-version.sql');
    sr_check_module_source_policy_write_file($routeDir, 'updates/2026.06.002.sql');
    $updateErrors = sr_validate_module_source('routecheck', $routeDir, sr_check_module_source_policy_metadata());
    if (!in_array('routecheck 모듈 업데이트 SQL 버전은 module.php version보다 높을 수 없습니다: updates/2026.06.002.sql', $updateErrors, true)) {
        fwrite(STDERR, "Newer-than-module module source update SQL was not rejected:\n" . implode("\n", $updateErrors) . "\n");
        exit(1);
    }

    unlink($routeDir . '/updates/2026.06.002.sql');
    mkdir($routeDir . '/updates/nested', 0777, true);
    sr_check_module_source_policy_write_file($routeDir, 'updates/nested/2026.06.001.sql');
    $updateErrors = sr_validate_module_source('routecheck', $routeDir, sr_check_module_source_policy_metadata());
    if (!in_array('routecheck 모듈 updates 디렉터리에는 버전 SQL 파일만 포함할 수 있습니다: updates/nested', $updateErrors, true)) {
        fwrite(STDERR, "Nested module source update SQL directory was not rejected:\n" . implode("\n", $updateErrors) . "\n");
        exit(1);
    }

    unlink($routeDir . '/updates/nested/2026.06.001.sql');
    rmdir($routeDir . '/updates/nested');
    rmdir($routeDir . '/updates');

    $moduleContentDir = $fixtureRoot . '/module-content';
    mkdir($moduleContentDir, 0777, true);
    sr_check_module_source_policy_write_file($moduleContentDir, 'install.sql');
    file_put_contents(
        $moduleContentDir . '/module.php',
        "<?php\n"
        . "file_put_contents(sys_get_temp_dir() . '/sr-module-side-effect', 'x');\n"
        . "return [\n"
        . "    'name' => 'Module Content',\n"
        . "    'version' => '2026.06.001',\n"
        . "    'type' => 'module',\n"
        . "    'saanraan' => [\n"
        . "        'min_version' => '0.2.0',\n"
        . "        'tested_with' => ['0.2.0'],\n"
        . "        'module_contract' => '2.0',\n"
        . "    ],\n"
        . "];\n"
    );
    $moduleContentErrors = sr_validate_module_source('modulecontent', $moduleContentDir, sr_load_module_metadata_from_file($moduleContentDir . '/module.php'));
    if (!in_array('module.php는 정적 return 배열로 시작해야 합니다.', $moduleContentErrors, true)) {
        fwrite(STDERR, "Module source module.php with a pre-return statement was not rejected:\n" . implode("\n", $moduleContentErrors) . "\n");
        exit(1);
    }

    file_put_contents(
        $moduleContentDir . '/module.php',
        "<?php\nreturn [\n"
        . "    'meta' => [\n"
        . "        'name' => 'Nested Module',\n"
        . "        'version' => '2026.06.001',\n"
        . "        'type' => 'module',\n"
        . "        'saanraan' => [\n"
        . "            'min_version' => '0.2.0',\n"
        . "            'tested_with' => ['0.2.0'],\n"
        . "            'module_contract' => '2.0',\n"
        . "        ],\n"
        . "    ],\n"
        . "];\n"
    );
    $nestedMetadata = sr_load_module_metadata_from_file($moduleContentDir . '/module.php');
    if ($nestedMetadata !== []) {
        fwrite(STDERR, "Nested-only module.php metadata was read as top-level metadata:\n" . var_export($nestedMetadata, true) . "\n");
        exit(1);
    }

    file_put_contents(
        $moduleContentDir . '/module.php',
        "<?php\nreturn [\n"
        . "    'name' => 'Nested List Module',\n"
        . "    'version' => '2026.06.001',\n"
        . "    'type' => 'module',\n"
        . "    'saanraan' => [\n"
        . "        'min_version' => '0.2.0',\n"
        . "        'tested_with' => [['0.2.0']],\n"
        . "        'module_contract' => '2.0',\n"
        . "    ],\n"
        . "];\n"
    );
    $nestedListMetadata = sr_load_module_metadata_from_file($moduleContentDir . '/module.php');
    $nestedListErrors = sr_module_metadata_errors($nestedListMetadata);
    if (!in_array('module.php의 saanraan.tested_with는 배열이어야 합니다.', $nestedListErrors, true)) {
        fwrite(STDERR, "Nested saanraan.tested_with list was read as a flat string list:\n" . implode("\n", $nestedListErrors) . "\n");
        exit(1);
    }

    file_put_contents(
        $moduleContentDir . '/module.php',
        "<?php\nreturn [\n"
        . "    'name' => 'Nested Saanraan Module',\n"
        . "    'version' => '2026.06.001',\n"
        . "    'type' => 'module',\n"
        . "    'saanraan' => [\n"
        . "        'compat' => [\n"
        . "            'min_version' => '0.2.0',\n"
        . "            'module_contract' => '2.0',\n"
        . "        ],\n"
        . "        'tested_with' => ['0.2.0'],\n"
        . "    ],\n"
        . "];\n"
    );
    $nestedSaanraanMetadata = sr_load_module_metadata_from_file($moduleContentDir . '/module.php');
    $nestedSaanraanErrors = sr_module_metadata_errors($nestedSaanraanMetadata);
    if (
        !in_array('module.php의 saanraan.module_contract가 필요합니다.', $nestedSaanraanErrors, true)
        || !in_array('module.php의 saanraan.min_version이 필요합니다.', $nestedSaanraanErrors, true)
    ) {
        fwrite(STDERR, "Nested saanraan scalar values were read as top-level metadata:\n" . implode("\n", $nestedSaanraanErrors) . "\n");
        exit(1);
    }

    sr_check_module_source_policy_write_file($moduleContentDir, 'paths.php');
    file_put_contents(
        $moduleContentDir . '/module.php',
        "<?php\nreturn [\n"
        . "    'name' => 'Nested Contract Module',\n"
        . "    'version' => '2026.06.001',\n"
        . "    'type' => 'module',\n"
        . "    'saanraan' => [\n"
        . "        'min_version' => '0.2.0',\n"
        . "        'tested_with' => ['0.2.0'],\n"
        . "        'module_contract' => '2.0',\n"
        . "    ],\n"
        . "    'contracts' => [\n"
        . "        'provides' => [['paths.php']],\n"
        . "    ],\n"
        . "];\n"
    );
    $nestedContractErrors = sr_validate_module_source('modulecontent', $moduleContentDir, sr_load_module_metadata_from_file($moduleContentDir . '/module.php'));
    if (!in_array('paths.php 파일은 module.php의 contracts.provides에 선언해야 합니다.', $nestedContractErrors, true)) {
        fwrite(STDERR, "Nested contracts.provides list was read as a flat contract file list:\n" . implode("\n", $nestedContractErrors) . "\n");
        exit(1);
    }

    file_put_contents(
        $moduleContentDir . '/module.php',
        "<?php\nreturn [\n"
        . "    'name' => 'Nested Contract Key Module',\n"
        . "    'version' => '2026.06.001',\n"
        . "    'type' => 'module',\n"
        . "    'saanraan' => [\n"
        . "        'min_version' => '0.2.0',\n"
        . "        'tested_with' => ['0.2.0'],\n"
        . "        'module_contract' => '2.0',\n"
        . "    ],\n"
        . "    'contracts' => [\n"
        . "        'wrapped' => [\n"
        . "            'provides' => ['paths.php'],\n"
        . "        ],\n"
        . "    ],\n"
        . "];\n"
    );
    $nestedContractKeyErrors = sr_validate_module_source('modulecontent', $moduleContentDir, sr_load_module_metadata_from_file($moduleContentDir . '/module.php'));
    if (!in_array('paths.php 파일은 module.php의 contracts.provides에 선언해야 합니다.', $nestedContractKeyErrors, true)) {
        fwrite(STDERR, "Nested contracts.provides key was read as a top-level contract file list:\n" . implode("\n", $nestedContractKeyErrors) . "\n");
        exit(1);
    }

    if (!class_exists('ZipArchive')) {
        fwrite(STDERR, "ZipArchive is required for module source policy checks.\n");
        exit(1);
    }

    $uploadDir = $fixtureRoot . '/upload';
    mkdir($uploadDir . '/pkg/module', 0777, true);
    sr_check_module_source_policy_write_file($uploadDir, 'pkg/.env.local');
    sr_check_module_source_policy_write_file(
        $uploadDir,
        'pkg/module/module.php'
    );
    file_put_contents(
        $uploadDir . '/pkg/module/module.php',
        "<?php\nreturn [\n"
        . "    'name' => 'Test Module',\n"
        . "    'version' => '2026.06.001',\n"
        . "    'type' => 'module',\n"
        . "    'saanraan' => [\n"
        . "        'min_version' => '0.2.0',\n"
        . "        'tested_with' => ['0.2.0'],\n"
        . "        'module_contract' => '2.0',\n"
        . "    ],\n"
        . "    'contracts' => [\n"
        . "        'provides' => ['paths.php'],\n"
        . "    ],\n"
        . "];\n"
    );
    sr_check_module_source_policy_write_file($uploadDir, 'pkg/module/install.sql');

    $zipPath = $fixtureRoot . '/testmod-2026.06.001.zip';
    sr_check_module_source_policy_create_test_zip($uploadDir, $zipPath);

    $acceptedUpload = false;
    $uploadResult = [];
    try {
        $uploadResult = sr_extract_module_upload([
            'error' => UPLOAD_ERR_OK,
            'name' => basename($zipPath),
            'size' => filesize($zipPath),
            'tmp_name' => $zipPath,
        ], 'testmod');
        $acceptedUpload = true;
    } catch (Throwable $exception) {
        if (!str_contains($exception->getMessage(), '.env.local')) {
            fwrite(STDERR, 'Upload fixture failed for the wrong reason: ' . $exception->getMessage() . "\n");
            exit(1);
        }
    } finally {
        if (is_array($uploadResult) && is_string($uploadResult['extract_dir'] ?? null) && is_dir($uploadResult['extract_dir'])) {
            sr_remove_directory($uploadResult['extract_dir']);
        }
    }

    if ($acceptedUpload) {
        fwrite(STDERR, "Upload fixture with a package-root forbidden file was accepted.\n");
        exit(1);
    }

    $routeUploadDir = $fixtureRoot . '/route-upload';
    mkdir($routeUploadDir . '/routetest/actions', 0777, true);
    file_put_contents(
        $routeUploadDir . '/routetest/module.php',
        "<?php\nreturn [\n"
        . "    'name' => 'Route Test',\n"
        . "    'version' => '2026.06.001',\n"
        . "    'type' => 'module',\n"
        . "    'saanraan' => [\n"
        . "        'min_version' => '0.2.0',\n"
        . "        'tested_with' => ['0.2.0'],\n"
        . "        'module_contract' => '2.0',\n"
        . "    ],\n"
        . "    'contracts' => [\n"
        . "        'provides' => ['paths.php'],\n"
        . "    ],\n"
        . "];\n"
    );
    sr_check_module_source_policy_write_file($routeUploadDir, 'routetest/install.sql');
    file_put_contents(
        $routeUploadDir . '/routetest/paths.php',
        "<?php\nreturn [\n"
        . "    'GET /route-upload' => 'actions/missing.php',\n"
        . "];\n"
    );
    $routeZipPath = $fixtureRoot . '/routetest.zip';
    sr_check_module_source_policy_zip_files($routeUploadDir, $routeZipPath, [
        'routetest/module.php',
        'routetest/install.sql',
        'routetest/paths.php',
    ]);

    try {
        $routeUploadResult = sr_extract_module_upload([
            'error' => UPLOAD_ERR_OK,
            'name' => basename($routeZipPath),
            'size' => filesize($routeZipPath),
            'tmp_name' => $routeZipPath,
        ], '');
        if (is_string($routeUploadResult['extract_dir'] ?? null) && is_dir($routeUploadResult['extract_dir'])) {
            sr_remove_directory($routeUploadResult['extract_dir']);
        }

        fwrite(STDERR, "Upload fixture with a missing action route was accepted.\n");
        exit(1);
    } catch (Throwable $exception) {
        if (!str_contains($exception->getMessage(), '실행 파일을 찾을 수 없습니다')) {
            fwrite(STDERR, 'Route upload fixture failed for the wrong reason: ' . $exception->getMessage() . "\n");
            exit(1);
        }
    }

    $moduleUploadDir = $fixtureRoot . '/module-upload';
    mkdir($moduleUploadDir . '/modulecontent', 0777, true);
    file_put_contents(
        $moduleUploadDir . '/modulecontent/module.php',
        "<?php\n"
        . "file_put_contents(sys_get_temp_dir() . '/sr-module-upload-side-effect', 'x');\n"
        . "return [\n"
        . "    'name' => 'Module Content',\n"
        . "    'version' => '2026.06.001',\n"
        . "    'type' => 'module',\n"
        . "    'saanraan' => [\n"
        . "        'min_version' => '0.2.0',\n"
        . "        'tested_with' => ['0.2.0'],\n"
        . "        'module_contract' => '2.0',\n"
        . "    ],\n"
        . "];\n"
    );
    sr_check_module_source_policy_write_file($moduleUploadDir, 'modulecontent/install.sql');
    $moduleZipPath = $fixtureRoot . '/modulecontent.zip';
    sr_check_module_source_policy_zip_files($moduleUploadDir, $moduleZipPath, [
        'modulecontent/module.php',
        'modulecontent/install.sql',
    ]);

    try {
        $moduleUploadResult = sr_extract_module_upload([
            'error' => UPLOAD_ERR_OK,
            'name' => basename($moduleZipPath),
            'size' => filesize($moduleZipPath),
            'tmp_name' => $moduleZipPath,
        ], '');
        if (is_string($moduleUploadResult['extract_dir'] ?? null) && is_dir($moduleUploadResult['extract_dir'])) {
            sr_remove_directory($moduleUploadResult['extract_dir']);
        }

        fwrite(STDERR, "Upload fixture with a pre-return module.php statement was accepted.\n");
        exit(1);
    } catch (Throwable $exception) {
        if (!str_contains($exception->getMessage(), 'module.php는 정적 return 배열로 시작해야 합니다')) {
            fwrite(STDERR, 'Module upload fixture failed for the wrong reason: ' . $exception->getMessage() . "\n");
            exit(1);
        }
    }

    $multiDir = $fixtureRoot . '/multi';
    mkdir($multiDir, 0777, true);
    foreach (['alpha', 'beta'] as $moduleKey) {
        mkdir($multiDir . '/' . $moduleKey, 0777, true);
        file_put_contents(
            $multiDir . '/' . $moduleKey . '/module.php',
            "<?php\nreturn [\n"
            . "    'name' => '" . $moduleKey . "',\n"
            . "    'version' => '2026.06.001',\n"
            . "    'type' => 'module',\n"
            . "    'saanraan' => [\n"
            . "        'min_version' => '0.2.0',\n"
            . "        'tested_with' => ['0.2.0'],\n"
            . "        'module_contract' => '2.0',\n"
            . "    ],\n"
            . "];\n"
        );
        sr_check_module_source_policy_write_file($multiDir, $moduleKey . '/install.sql');
    }

    $multiZipPath = $fixtureRoot . '/multi.zip';
    sr_check_module_source_policy_zip_files($multiDir, $multiZipPath, [
        'alpha/module.php',
        'alpha/install.sql',
        'beta/module.php',
        'beta/install.sql',
    ]);

    foreach (['', 'alpha'] as $requestedModuleKey) {
        try {
            $multiResult = sr_extract_module_upload([
                'error' => UPLOAD_ERR_OK,
                'name' => basename($multiZipPath),
                'size' => filesize($multiZipPath),
                'tmp_name' => $multiZipPath,
            ], $requestedModuleKey);
            if (is_string($multiResult['extract_dir'] ?? null) && is_dir($multiResult['extract_dir'])) {
                sr_remove_directory($multiResult['extract_dir']);
            }

            fwrite(STDERR, "Upload fixture with multiple module sources was accepted.\n");
            exit(1);
        } catch (Throwable $exception) {
            if (
                !str_contains($exception->getMessage(), '여러 모듈 구조')
                && !str_contains($exception->getMessage(), '요청한 모듈 하나')
            ) {
                fwrite(STDERR, 'Multi-module upload fixture failed for the wrong reason: ' . $exception->getMessage() . "\n");
                exit(1);
            }
        }
    }

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE sr_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, module_key TEXT NOT NULL, version TEXT NOT NULL, status TEXT NOT NULL)');

    $existingModuleKey = 'zip_policy_existing_' . strtolower(bin2hex(random_bytes(3)));
    $candidateModuleKey = 'zip_policy_candidate_' . strtolower(bin2hex(random_bytes(3)));
    $inactiveCandidateModuleKey = 'zip_policy_inactive_' . strtolower(bin2hex(random_bytes(3)));
    $statusCandidateModuleKey = 'zip_policy_status_' . strtolower(bin2hex(random_bytes(3)));
    $fixtureModuleKeys = [$existingModuleKey, $candidateModuleKey, $inactiveCandidateModuleKey, $statusCandidateModuleKey];

    try {
        sr_check_module_source_policy_write_module($existingModuleKey, 'GET /zip-policy/*');
        sr_check_module_source_policy_write_module($statusCandidateModuleKey, 'GET /zip-policy/status');
        $candidateDir = $fixtureRoot . '/candidate-source';
        mkdir($candidateDir . '/actions', 0777, true);
        sr_check_module_source_policy_write_file($candidateDir, 'install.sql');
        sr_check_module_source_policy_write_file($candidateDir, 'actions/page.php');
        file_put_contents(
            $candidateDir . '/paths.php',
            "<?php\nreturn [\n"
            . "    'GET /zip-policy/new' => 'actions/page.php',\n"
            . "];\n"
        );

        foreach ([
            [$existingModuleKey, '2026.06.001', 'enabled'],
            [$candidateModuleKey, '2026.06.001', 'enabled'],
            [$inactiveCandidateModuleKey, '2026.06.001', 'disabled'],
            [$statusCandidateModuleKey, '2026.06.001', 'disabled'],
        ] as $row) {
            $stmt = $pdo->prepare('INSERT INTO sr_modules (module_key, version, status) VALUES (:module_key, :version, :status)');
            $stmt->execute([
                'module_key' => $row[0],
                'version' => $row[1],
                'status' => $row[2],
            ]);
        }

        $conflictErrors = sr_module_source_route_conflict_errors($pdo, $candidateModuleKey, $candidateDir);
        $foundConflict = false;
        foreach ($conflictErrors as $error) {
            if (str_contains($error, $existingModuleKey) && str_contains($error, 'GET /zip-policy/new') && str_contains($error, 'GET /zip-policy/*')) {
                $foundConflict = true;
                break;
            }
        }

        if (!$foundConflict) {
            fwrite(STDERR, "Enabled module replacement route conflict was not rejected:\n" . implode("\n", $conflictErrors) . "\n");
            exit(1);
        }

        $inactiveConflictErrors = sr_module_source_route_conflict_errors($pdo, $inactiveCandidateModuleKey, $candidateDir);
        if ($inactiveConflictErrors !== []) {
            fwrite(STDERR, "Inactive module replacement route conflict should not be rejected before activation:\n" . implode("\n", $inactiveConflictErrors) . "\n");
            exit(1);
        }

        try {
            sr_update_module_status($pdo, $statusCandidateModuleKey, 'enabled');
            fwrite(STDERR, "Lifecycle status update accepted a conflicting enabled route.\n");
            exit(1);
        } catch (RuntimeException $exception) {
            if (!str_contains($exception->getMessage(), $existingModuleKey) || !str_contains($exception->getMessage(), 'GET /zip-policy/status')) {
                fwrite(STDERR, 'Lifecycle status update failed for the wrong reason: ' . $exception->getMessage() . "\n");
                exit(1);
            }
        }
    } finally {
        foreach ($fixtureModuleKeys as $fixtureModuleKey) {
            sr_check_module_source_policy_remove_dir(SR_ROOT . '/modules/' . $fixtureModuleKey);
        }
    }
} finally {
    sr_check_module_source_policy_remove_dir($fixtureRoot);
}

echo "module source file policy checks completed.\n";
