#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_release_package_read(string $file, array &$errors): string
{
    if (!is_file($file)) {
        $errors[] = 'Required release package policy file is missing: ' . $file;
        return '';
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        $errors[] = 'Required release package policy file cannot be read: ' . $file;
        return '';
    }

    return $contents;
}

function sr_release_package_contains(string $file, array $markers, array &$errors): void
{
    $contents = sr_release_package_read($file, $errors);
    if ($contents === '') {
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $errors[] = 'Release package policy marker missing in ' . $file . ': ' . $marker;
        }
    }
}

function sr_release_package_file_exists(string $file, array &$errors): void
{
    if (!is_file($file)) {
        $errors[] = 'Release package required file is missing from working tree: ' . $file;
    }
}

function sr_release_package_file_executable(string $file, array &$errors): void
{
    sr_release_package_file_exists($file, $errors);
    if (is_file($file) && !is_executable($file)) {
        $errors[] = 'Release package executable tool is not executable: ' . $file;
    }
}

function sr_release_package_file_absent(string $file, array &$errors): void
{
    if (is_file($file) || is_dir($file)) {
        $errors[] = 'Release package should not rely on local-only path being absent from packaging rules: ' . $file;
    }
}

function sr_release_package_exec(string $command, array &$errors): string
{
    $output = [];
    exec($command . ' 2>&1', $output, $exitCode);
    $text = implode("\n", $output);
    if ($exitCode !== 0) {
        $errors[] = 'Release package command failed: ' . $command . "\n" . $text;
        return '';
    }

    return $text;
}

foreach ([
    'index.php',
    '.htaccess',
    'LICENSE',
    'README.md',
    'docs/deployment/nginx-saanraan.conf',
    'docs/deployment/nginx-saanraan-subdirectory.conf',
    'modules/htmlpurifier/DEPENDENCY.md',
    'modules/htmlpurifier/vendor/autoload.php',
    'modules/htmlpurifier/vendor/ezyang/htmlpurifier/LICENSE',
    'modules/htmlpurifier/vendor/ezyang/htmlpurifier/VERSION',
    'modules/htmlpurifier/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php',
    'modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js',
    'modules/ckeditor/vendor/ckeditor5/ckeditor5.css',
    'modules/ckeditor/vendor/ckeditor5/LICENSE.md',
    'modules/ckeditor/vendor/ckeditor5/COPYING.GPL',
] as $file) {
    sr_release_package_file_exists($file, $errors);
}

foreach ([
    '.tools/bin/smoke-privacy-export-cleanup.php',
    '.tools/bin/smoke-ckeditor-upload-save.php',
] as $file) {
    sr_release_package_file_executable($file, $errors);
}

foreach ([
    'docs/release-process.md' => [
        '릴리스 zip은 현재 저장소의 파일 구조를 보존해야 한다',
        '포함 기준',
        '제외 기준',
        '`index.php`, `core/`, `database/`, `modules/`',
        '`.htaccess`',
        '`config/` 디렉터리',
        '`docs/`, `examples/`, `README.md`, `LICENSE`',
        '`docs/deployment/nginx-saanraan.conf`',
        '`docs/deployment/nginx-saanraan-subdirectory.conf`',
        '릴리스에 포함하기로 결정한 `vendor/` 파일 또는 선택 모듈 내부 vendor 파일과 라이선스 문서',
        '`.tools/browser-qa/node_modules/`, `.tools/browser-qa/results/`, `.tools/browser-qa/test-results/`, `.tools/browser-qa/package-lock.json`',
        '`config/config.php`, `config/config.php.tmp`, `config/config-*.tmp.php`, `config/*.bak`, `config/*.backup`, `config/*.old`, `config/*.orig`, `.env`, `.env.*`',
        '`storage/installed.lock`',
        '`storage/logs/`, `storage/module-backups/`, `storage/update-failed.json`',
        '비밀값이 들어 있는 파일',
        'SHA-256 checksum',
        'php .tools/bin/release-preflight.php',
        'php .tools/bin/release-installed-gate-status.php',
        'php .tools/bin/release-installed-gate-status.php --json',
        'php .tools/bin/release-installed-gate-status.php --fail-on-unresolved',
        'php .tools/bin/release-installed-gate-status.php --run-http-smoke',
        '`--markdown-table`과 `--json`은 서로 배타적인 출력 형식',
        'URL userinfo를 metadata, gate 환경값, 실행 출력 요약에 남기기 전에 마스킹',
        '기본 HTTP smoke',
        'HTML Purifier가 로드되지 않거나',
        '런타임 버전이 `VERSION` 파일과 다르거나',
        'cache가 `storage/cache/htmlpurifier` 아래에 쓰기 가능하게 준비되지 않으면 preflight는 실패',
        'release-preflight.php',
        'release-installed-gate-status.php',
        'gate-result-summary',
        'unresolved-gates',
        'result_counts',
        'unresolved_gates',
        'release-package-dry-run.php --manifest',
        'manifest-sha256',
        '설치 DB smoke용 `.tools/bin/smoke-*.php` 도구 중 릴리스 검증 절차에서 직접 실행하는 파일은 패키지 정책 점검에서 실행권한도 확인한다',
        'composer install --no-dev --prefer-dist',
        'composer update ezyang/htmlpurifier --no-dev --prefer-dist',
        'GitHub source zip',
        'modules/htmlpurifier/vendor/ezyang/htmlpurifier/',
        'modules/htmlpurifier/DEPENDENCY.md',
    ],
    'docs/dependency-policy.md' => [
        '런타임 서버에 Composer 실행을 요구하지 않는다',
        '기본 배포 zip에는 `modules/htmlpurifier/vendor/ezyang/htmlpurifier/`와 autoload 파일을 함께 포함한다',
        '배포물에는 `modules/htmlpurifier/DEPENDENCY.md`, HTML Purifier의 라이선스 파일, 버전 파일을 포함한다',
        'Purifier cache는 vendor 내부가 아니라 `storage/cache/htmlpurifier`를 사용한다',
        '1.0 운영 배포 기준 경로는 `modules/htmlpurifier/`다',
    ],
    'docs/release-verification-template.md' => [
        '릴리스 zip checksum 또는 GitHub source zip 사용 여부',
        'php .tools/bin/release-preflight.php',
        'php .tools/bin/release-package-dry-run.php',
        'php .tools/bin/release-package-dry-run.php --manifest',
        'dry-run manifest',
        'manifest-sha256',
        'dependency policy',
        'modules/htmlpurifier/DEPENDENCY.md',
        'Purifier 로드 상태',
    ],
    'docs/records/improvement-hardening-verification-2026-06-11.md' => [
        '릴리스 zip에서 Purifier 로드 상태와 라이선스/버전 포함 확인',
        'release-installed-gate-status.php',
        'modules/htmlpurifier/DEPENDENCY.md',
        'vendor 포함 확인',
        'release-preflight.php',
        'release-package-files',
        'fallback sanitizer fixture',
        'release-package-dry-run.php',
        'release-package-dry-run.php --manifest',
        'manifest-sha256',
        '금지 경로 제외 확인',
    ],
    '.tools/bin/release-package-dry-run.php' => [
        'config/config.php.tmp',
        'config-[^\\/]+\\.tmp\\.php',
        '.env.local',
        '.env.production',
        'modules/example/.env',
        'modules/example/backup.sql',
        'modules/example/id_rsa',
        'database/production.sql',
        'vendor/autoload.php',
        'dist/saanraan.zip',
        'storage/logs/error.log',
        'Release package exclusion policy does not exclude sample path',
        '\\.env',
        'bak|backup|old|orig|tmp|sqlite|sqlite3|db',
        'dump|backup|production|prod|staging|local',
        'id_rsa|id_dsa|id_ecdsa|id_ed25519',
        'path traversal segment',
    ],
    '.tools/bin/release-preflight.php' => [
        'release-preflight-version: 1',
        'purifier-available',
        'purifier-version',
        'purifier-module-autoload',
        'purifier-autoload-path',
        'purifier-cache-dir',
        'purifier-cache-writable',
        'HTML Purifier must be loadable for a release preflight',
        'HTML Purifier runtime version does not match VERSION file',
        'HTML Purifier release preflight must use module autoload path',
        'HTML Purifier release preflight must use storage cache path',
        'HTML Purifier cache directory must be writable for release preflight',
        'release-package-files',
        'release-package-manifest-sha256',
        'ckeditor-assets',
        'ckeditor-version',
        'ckeditor-license-files',
        'release-package-dry-run.php',
    ],
    '.tools/bin/release-installed-gate-status.php' => [
        'release-installed-gate-status-version: 1',
        'config-mode',
        'config-owner-group',
        '--json',
        '--fail-on-unresolved',
        '--markdown-table and --json are mutually exclusive',
        'sr_release_gate_status_mask_url_userinfo_in_text',
        'sr_release_gate_status_mask_url_userinfo',
        'result_counts',
        'unresolved_gates',
        'CKEditor upload/save browser smoke',
        '개인정보 export/cleanup smoke',
        '성능 수동 점검',
    ],
    '.gitignore' => [
        '.env',
        '.env.*',
        '.tools/browser-qa/node_modules/',
        '.tools/browser-qa/results/',
        '.tools/browser-qa/test-results/',
        'dist/',
    ],
    'config/.gitignore' => [
        'config.php',
        'config.php.tmp',
        'config-*.tmp.php',
        '*.bak',
        '*.backup',
        '*.old',
        '*.orig',
        '*.tmp',
    ],
    'storage/.gitignore' => [
        '*',
        '!.gitignore',
    ],
] as $file => $markers) {
    sr_release_package_contains($file, $markers, $errors);
}

$dependency = sr_release_package_read('modules/htmlpurifier/DEPENDENCY.md', $errors);
$releaseProcess = sr_release_package_read('docs/release-process.md', $errors);
$version = is_file('modules/htmlpurifier/vendor/ezyang/htmlpurifier/VERSION')
    ? trim((string) file_get_contents('modules/htmlpurifier/vendor/ezyang/htmlpurifier/VERSION'))
    : '';
if ($version !== '' && !str_contains($dependency, '`v' . $version . '`')) {
    $errors[] = 'HTML Purifier dependency record version does not match local VERSION file: ' . $version;
}

foreach ([
    'vendor/',
    'config/config.php',
    'config/config.php.tmp',
    'config/config-*.tmp.php',
    'config/*.bak',
    'config/*.backup',
    'config/*.old',
    'config/*.orig',
    '.env',
    '.env.*',
    'storage/installed.lock',
    'storage/logs/',
    'storage/module-backups/',
    'storage/update-failed.json',
    '.tools/browser-qa/node_modules/',
    '.tools/browser-qa/results/',
    '.tools/browser-qa/test-results/',
    '.tools/browser-qa/package-lock.json',
] as $marker) {
    if (!str_contains($releaseProcess, '`' . rtrim($marker, '/') . (str_ends_with($marker, '/') ? '/' : '') . '`')) {
        $errors[] = 'Release exclusion/include decision is not documented for: ' . $marker;
    }
}

foreach ([
    'vendor',
    'dist',
] as $localOnlyPath) {
    sr_release_package_file_absent($localOnlyPath, $errors);
}

$manifest = sr_release_package_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/release-package-dry-run.php') . ' --manifest', $errors);
if ($manifest !== '') {
    if (!str_starts_with($manifest, "release-package-dry-run-version: 1\n")) {
        $errors[] = 'Release package dry-run manifest header is invalid.';
    }

    if (preg_match('/^files: (\d+)$/m', $manifest, $matches) !== 1 || (int) $matches[1] <= 0) {
        $errors[] = 'Release package dry-run manifest file count is invalid.';
    }

    if (preg_match('/^manifest-sha256: [a-f0-9]{64}$/m', $manifest) !== 1) {
        $errors[] = 'Release package dry-run manifest sha256 is missing or invalid.';
    }

    foreach ([
        '.htaccess',
        'index.php',
        'modules/htmlpurifier/DEPENDENCY.md',
        'modules/htmlpurifier/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php',
        'modules/ckeditor/vendor/ckeditor5/README.md',
        'modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js',
        'modules/ckeditor/vendor/ckeditor5/ckeditor5.css',
        'modules/ckeditor/vendor/ckeditor5/LICENSE.md',
        'modules/ckeditor/vendor/ckeditor5/COPYING.GPL',
        '.tools/bin/check-coupon-redemption-runtime.php',
        '.tools/bin/check-privacy-export-runtime.php',
        '.tools/bin/check-privacy-cleanup-runtime.php',
        '.tools/bin/check-ckeditor-assets.php',
        '.tools/bin/check-admin-pagination-runtime.php',
        '.tools/bin/check-community-board-copy-limits.php',
        '.tools/bin/check-community-board-copy-job-lock.php',
        '.tools/bin/check-htmlpurifier-vendor-integrity.php',
        '.tools/bin/check-htmlpurifier-runtime.php',
        '.tools/bin/check-installed-gate-status.php',
        '.tools/bin/check-request-contract-runtime.php',
        '.tools/bin/check-tool-gate-coverage.php',
        '.tools/bin/check-release-verification-records.php',
        '.tools/bin/release-installed-gate-status.php',
        '.tools/bin/smoke-privacy-export-cleanup.php',
        '.tools/bin/smoke-ckeditor-upload-save.php',
    ] as $requiredManifestFile) {
        if (preg_match('/^[a-f0-9]{64}  ' . preg_quote($requiredManifestFile, '/') . '$/m', $manifest) !== 1) {
            $errors[] = 'Release package dry-run manifest is missing required file hash: ' . $requiredManifestFile;
        }
    }

    foreach ([
        'config/config.php',
        'config/config.php.tmp',
        'config/config-',
        '.env',
        '.sqlite',
        '.db',
        'id_rsa',
        'storage/installed.lock',
        '.tools/browser-qa/node_modules/',
        '.tools/browser-qa/package-lock.json',
        'AGENTS.md',
    ] as $forbiddenManifestPath) {
        if (str_contains($manifest, $forbiddenManifestPath)) {
            $errors[] = 'Release package dry-run manifest includes forbidden path: ' . $forbiddenManifestPath;
        }
    }
}

$list = sr_release_package_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/release-package-dry-run.php') . ' --list', $errors);
if ($list !== '' && $manifest !== '') {
    $listFiles = array_values(array_filter(explode("\n", trim($list)), static fn (string $line): bool => $line !== ''));
    $sortedListFiles = $listFiles;
    sort($sortedListFiles, SORT_STRING);
    if ($listFiles !== $sortedListFiles) {
        $errors[] = 'Release package dry-run list output must stay sorted.';
    }
    if (count($listFiles) !== count(array_unique($listFiles))) {
        $errors[] = 'Release package dry-run list output contains duplicate paths.';
    }

    $manifestFiles = [];
    foreach (explode("\n", trim($manifest)) as $line) {
        if (preg_match('/\A[a-f0-9]{64}  (.+)\z/', $line, $matches) === 1) {
            $manifestFiles[] = $matches[1];
        }
    }

    if ($listFiles !== $manifestFiles) {
        $errors[] = 'Release package dry-run --list and --manifest file sets differ.';
    }

    if (preg_match('/^files: (\d+)$/m', $manifest, $matches) === 1 && (int) $matches[1] !== count($listFiles)) {
        $errors[] = 'Release package dry-run manifest file count does not match --list output.';
    }

    foreach ($listFiles as $file) {
        foreach ([
            '/\A(?:vendor|dist|storage|\.git|\.agents|\.claude)\//',
            '/(?:^|\/)\.env(?:\..*)?\z/',
            '/(?:^|\/)[^\/]+\.(?:bak|backup|old|orig|tmp|sqlite|sqlite3|db)\z/i',
            '/(?:^|\/)(?:dump|backup|production|prod|staging|local)[^\/]*\.sql\z/i',
            '/(?:^|\/)(?:id_rsa|id_dsa|id_ecdsa|id_ed25519|\.npmrc|\.pypirc|composer\.auth\.json)\z/i',
            '/(?:^|\/)\.\.(?:\/|$)/',
        ] as $pattern) {
            if (preg_match($pattern, $file) === 1) {
                $errors[] = 'Release package dry-run list includes forbidden path by policy scan: ' . $file;
            }
        }
    }

    $manifestBody = '';
    foreach (explode("\n", $manifest) as $line) {
        if (preg_match('/\A[a-f0-9]{64}  .+\z/', $line) === 1) {
            $manifestBody .= $line . "\n";
        }
    }
    if (preg_match('/^manifest-sha256: ([a-f0-9]{64})$/m', $manifest, $matches) === 1 && hash('sha256', $manifestBody) !== $matches[1]) {
        $errors[] = 'Release package dry-run manifest-sha256 does not match manifest body.';
    }
}

$preflight = sr_release_package_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/release-preflight.php'), $errors);
if ($preflight !== '') {
    foreach ([
        '/^release-preflight-version: 1$/m',
        '/^php-version: .+$/m',
        '/^purifier-available: yes$/m',
        '/^purifier-version: 4\.19\.0$/m',
        '/^purifier-module-autoload: present$/m',
        '/^purifier-autoload-path: modules\/htmlpurifier\/vendor\/autoload\.php$/m',
        '/^purifier-cache-dir: storage\/cache\/htmlpurifier$/m',
        '/^purifier-cache-writable: yes$/m',
        '/^dependency-record: present$/m',
        '/^ckeditor-assets: present$/m',
        '/^ckeditor-version: 48\.3\.0$/m',
        '/^ckeditor-license-files: present$/m',
        '/^release-package-files: \d+$/m',
        '/^release-package-manifest-sha256: [a-f0-9]{64}$/m',
        '/^release preflight checks completed\.$/m',
    ] as $pattern) {
        if (preg_match($pattern, $preflight) !== 1) {
            $errors[] = 'Release preflight output is missing expected pattern: ' . $pattern;
        }
    }

    if (preg_match('/^files: (\d+)$/m', $manifest, $manifestMatches) === 1
        && preg_match('/^release-package-files: (\d+)$/m', $preflight, $preflightMatches) === 1
        && $manifestMatches[1] !== $preflightMatches[1]
    ) {
        $errors[] = 'Release preflight file count does not match dry-run manifest.';
    }

    if (preg_match('/^manifest-sha256: ([a-f0-9]{64})$/m', $manifest, $manifestMatches) === 1
        && preg_match('/^release-package-manifest-sha256: ([a-f0-9]{64})$/m', $preflight, $preflightMatches) === 1
        && $manifestMatches[1] !== $preflightMatches[1]
    ) {
        $errors[] = 'Release preflight manifest hash does not match dry-run manifest.';
    }
}

if ($errors !== []) {
    fwrite(STDERR, "release package policy checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "release package policy checks completed.\n";
