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
require_once SR_ROOT . '/core/helpers/module-source.php';

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
} finally {
    sr_check_module_source_policy_remove_dir($fixtureRoot);
}

echo "module source file policy checks completed.\n";
