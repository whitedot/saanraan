#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

$errors = [];

function sr_privacy_matrix_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_privacy_matrix_module_metadata(string $moduleKey): array
{
    $file = 'modules/' . $moduleKey . '/module.php';
    if (!is_file($file)) {
        sr_privacy_matrix_error('module.php is missing: ' . $moduleKey);
        return [];
    }

    $metadata = include $file;
    if (!is_array($metadata)) {
        sr_privacy_matrix_error('module.php must return an array: ' . $moduleKey);
        return [];
    }

    return $metadata;
}

function sr_privacy_matrix_contracts(array $metadata, string $key): array
{
    $contracts = is_array($metadata['contracts'] ?? null) ? $metadata['contracts'] : [];
    $values = is_array($contracts[$key] ?? null) ? $contracts[$key] : [];
    return array_values(array_filter($values, 'is_string'));
}

function sr_privacy_matrix_account_reference_pattern(): string
{
    return '/\b[a-z0-9_]*account_id\b/i';
}

function sr_privacy_matrix_sql_files(string $moduleDir): array
{
    $files = [];
    foreach ([$moduleDir . '/install.sql', $moduleDir . '/updates/*.sql'] as $pattern) {
        foreach (glob($pattern) ?: [] as $file) {
            if (is_file($file)) {
                $files[] = $file;
            }
        }
    }

    sort($files, SORT_STRING);
    return $files;
}

function sr_privacy_matrix_sql_account_reference_files(string $moduleDir): array
{
    $files = [];
    foreach (sr_privacy_matrix_sql_files($moduleDir) as $file) {
        $sql = file_get_contents($file);
        if (!is_string($sql)) {
            sr_privacy_matrix_error('SQL file cannot be read for privacy matrix scan: ' . $file);
            continue;
        }

        if (preg_match(sr_privacy_matrix_account_reference_pattern(), $sql) === 1) {
            $files[] = $file;
        }
    }

    return $files;
}

function sr_privacy_matrix_check_callable_signature(string $moduleKey, string $contractFile, callable $callable, int $minimumParams): void
{
    try {
        if (is_array($callable)) {
            $reflection = new ReflectionMethod($callable[0], (string) $callable[1]);
        } else {
            $reflection = new ReflectionFunction(Closure::fromCallable($callable));
        }
    } catch (Throwable $exception) {
        sr_privacy_matrix_error($moduleKey . ' ' . $contractFile . ' callable signature cannot be inspected.');
        return;
    }

    if ($reflection->getNumberOfParameters() < $minimumParams) {
        sr_privacy_matrix_error($moduleKey . ' ' . $contractFile . ' callable must accept at least ' . $minimumParams . ' parameters.');
        return;
    }

    $parameters = $reflection->getParameters();
    $firstType = $parameters[0]->getType();
    $secondType = $parameters[1]->getType();
    if (!$firstType instanceof ReflectionNamedType || $firstType->getName() !== 'PDO') {
        sr_privacy_matrix_error($moduleKey . ' ' . $contractFile . ' first callable parameter must be PDO.');
    }
    if (!$secondType instanceof ReflectionNamedType || $secondType->getName() !== 'int') {
        sr_privacy_matrix_error($moduleKey . ' ' . $contractFile . ' second callable parameter must be int account id.');
    }

    $returnType = $reflection->getReturnType();
    if ($contractFile === 'privacy-cleanup.php' && (!$returnType instanceof ReflectionNamedType || $returnType->getName() !== 'array')) {
        sr_privacy_matrix_error($moduleKey . ' privacy-cleanup.php callable must declare an array return type.');
    }
}

function sr_privacy_matrix_check_contract_return(string $moduleKey, string $contractFile): void
{
    $path = 'modules/' . $moduleKey . '/' . $contractFile;
    if (!is_file($path)) {
        return;
    }

    try {
        $contract = include $path;
    } catch (Throwable $exception) {
        sr_privacy_matrix_error($moduleKey . ' ' . $contractFile . ' cannot be included: ' . $exception->getMessage());
        return;
    }

    if ($contractFile === 'privacy-export.php') {
        if (!is_callable($contract) && !is_array($contract)) {
            sr_privacy_matrix_error($moduleKey . ' privacy-export.php must return callable or array.');
            return;
        }

        if (is_callable($contract)) {
            sr_privacy_matrix_check_callable_signature($moduleKey, $contractFile, $contract, 2);
        }
        return;
    }

    if ($contractFile === 'privacy-cleanup.php') {
        if (!is_callable($contract)) {
            sr_privacy_matrix_error($moduleKey . ' privacy-cleanup.php must return callable.');
            return;
        }

        sr_privacy_matrix_check_callable_signature($moduleKey, $contractFile, $contract, 2);
    }
}

$matrixFile = 'docs/privacy-contract-matrix.md';
if (!is_file($matrixFile)) {
    sr_privacy_matrix_error('privacy contract matrix document is missing.');
    $matrix = '';
} else {
    $matrix = (string) file_get_contents($matrixFile);
}

$expected = [
    'admin' => ['status' => 'operational_retained', 'export' => false, 'cleanup' => false],
    'asset_exchange' => ['status' => 'export_retained', 'export' => true, 'cleanup' => false],
    'asset_ledger' => ['status' => 'no_member_personal_data', 'export' => false, 'cleanup' => false],
    'banner' => ['status' => 'no_member_personal_data', 'export' => false, 'cleanup' => false],
    'ckeditor' => ['status' => 'no_member_personal_data', 'export' => false, 'cleanup' => false],
    'community' => ['status' => 'export_cleanup', 'export' => true, 'cleanup' => true],
    'content' => ['status' => 'export_cleanup', 'export' => true, 'cleanup' => true],
    'coupon' => ['status' => 'export_retained', 'export' => true, 'cleanup' => false],
    'deposit' => ['status' => 'export_retained', 'export' => true, 'cleanup' => false],
    'embed_manager' => ['status' => 'operational_retained', 'export' => false, 'cleanup' => false],
    'logo_manager' => ['status' => 'operational_retained', 'export' => false, 'cleanup' => false],
    'member' => ['status' => 'export_owner', 'export' => true, 'cleanup' => false, 'consumes_cleanup' => true],
    'notification' => ['status' => 'export_retained', 'export' => true, 'cleanup' => false],
    'point' => ['status' => 'export_retained', 'export' => true, 'cleanup' => false],
    'popup_layer' => ['status' => 'no_member_personal_data', 'export' => false, 'cleanup' => false],
    'privacy' => ['status' => 'coordinator_direct', 'export' => false, 'cleanup' => false, 'consumes_export' => true],
    'quiz' => ['status' => 'export_cleanup', 'export' => true, 'cleanup' => true],
    'reward' => ['status' => 'export_retained', 'export' => true, 'cleanup' => false],
    'seo' => ['status' => 'no_member_personal_data', 'export' => false, 'cleanup' => false],
    'site_menu' => ['status' => 'no_member_personal_data', 'export' => false, 'cleanup' => false],
    'survey' => ['status' => 'export_cleanup', 'export' => true, 'cleanup' => true],
];

$operationalRetainedModules = ['admin', 'embed_manager', 'logo_manager'];

foreach ($expected as $moduleKey => $policy) {
    if ($matrix !== '' && strpos($matrix, '| `' . $moduleKey . '` | `' . $policy['status'] . '` |') === false) {
        sr_privacy_matrix_error('privacy matrix row is missing or status changed without checker update: ' . $moduleKey);
    }

    $metadata = sr_privacy_matrix_module_metadata($moduleKey);
    $provides = sr_privacy_matrix_contracts($metadata, 'provides');
    $consumes = sr_privacy_matrix_contracts($metadata, 'consumes');

    foreach (['privacy-export.php' => 'export', 'privacy-cleanup.php' => 'cleanup'] as $contractFile => $policyKey) {
        $required = (bool) ($policy[$policyKey] ?? false);
        $hasContract = in_array($contractFile, $provides, true);
        $hasFile = is_file('modules/' . $moduleKey . '/' . $contractFile);

        if ($required && (!$hasContract || !$hasFile)) {
            sr_privacy_matrix_error($moduleKey . ' must provide ' . $contractFile . ' according to privacy matrix.');
        }

        if (!$required && $moduleKey !== 'member' && $moduleKey !== 'privacy' && ($hasContract || $hasFile)) {
            sr_privacy_matrix_error($moduleKey . ' has unexpected ' . $contractFile . ' for current privacy matrix status.');
        }

        if ($hasFile) {
            sr_privacy_matrix_check_contract_return($moduleKey, $contractFile);
        }
    }

    if ((bool) ($policy['consumes_cleanup'] ?? false) && !in_array('privacy-cleanup.php', $consumes, true)) {
        sr_privacy_matrix_error($moduleKey . ' must consume privacy-cleanup.php.');
    }

    if ((bool) ($policy['consumes_export'] ?? false) && !in_array('privacy-export.php', $consumes, true)) {
        sr_privacy_matrix_error($moduleKey . ' must consume privacy-export.php.');
    }
}

$moduleDirs = glob('modules/*', GLOB_ONLYDIR);
if (is_array($moduleDirs)) {
    sort($moduleDirs);
    foreach ($moduleDirs as $moduleDir) {
        $moduleKey = basename($moduleDir);
        if (!is_file($moduleDir . '/module.php')) {
            continue;
        }

        if (!isset($expected[$moduleKey])) {
            sr_privacy_matrix_error('module is missing from privacy contract matrix: ' . $moduleKey);
            continue;
        }

        $accountReferenceFiles = sr_privacy_matrix_sql_account_reference_files($moduleDir);
        $hasAccountReference = $accountReferenceFiles !== [];
        if ($hasAccountReference && $matrix !== '' && strpos($matrix, '| `' . $moduleKey . '` |') === false) {
            sr_privacy_matrix_error('install.sql has account references but matrix row is missing: ' . $moduleKey);
        }

        $status = (string) ($expected[$moduleKey]['status'] ?? '');
        if ($status === 'no_member_personal_data' && $hasAccountReference) {
            sr_privacy_matrix_error($moduleKey . ' is marked no_member_personal_data but SQL schema files contain account references: ' . implode(', ', $accountReferenceFiles));
        }

        if ($hasAccountReference && in_array($status, ['export_cleanup', 'export_retained', 'export_owner', 'coordinator_direct'], true)) {
            continue;
        }

        if ($hasAccountReference && $status === 'operational_retained' && !in_array($moduleKey, $operationalRetainedModules, true)) {
            sr_privacy_matrix_error($moduleKey . ' has account references and operational_retained status but no detailed retention policy.');
        }
    }
}

foreach ([
    ['needle' => 'sr_member_run_privacy_cleanup_contracts', 'file' => 'modules/member/helpers/privacy.php'],
    ['needle' => 'sr_privacy_export_data', 'file' => 'modules/privacy/helpers/requests.php'],
    ['needle' => 'sr_privacy_export_runtime_check_content', 'file' => '.tools/bin/check-privacy-export-runtime.php'],
    ['needle' => 'sr_privacy_export_runtime_check_community', 'file' => '.tools/bin/check-privacy-export-runtime.php'],
    ['needle' => 'sr_privacy_export_runtime_check_retained_modules', 'file' => '.tools/bin/check-privacy-export-runtime.php'],
    ['needle' => 'PRAGMA table_info(sr_content_comments)', 'file' => 'modules/content/helpers/comments.php'],
    ['needle' => 'PRAGMA table_info(sr_community_comments)', 'file' => 'modules/community/helpers/posts.php'],
    ['needle' => 'PRAGMA table_info(', 'file' => 'modules/content/privacy-cleanup.php'],
    ['needle' => 'PRAGMA table_info(', 'file' => 'modules/community/privacy-cleanup.php'],
    ['needle' => 'sr_privacy_cleanup_runtime_check_content', 'file' => '.tools/bin/check-privacy-cleanup-runtime.php'],
    ['needle' => 'sr_privacy_cleanup_runtime_check_community', 'file' => '.tools/bin/check-privacy-cleanup-runtime.php'],
] as $runtimeMarker) {
    $needle = (string) $runtimeMarker['needle'];
    $file = (string) $runtimeMarker['file'];
    $contents = is_file($file) ? (string) file_get_contents($file) : '';
    if ($contents === '' || strpos($contents, $needle) === false) {
        sr_privacy_matrix_error('privacy runtime function marker is missing: ' . $needle);
    }
}

foreach ([
    'export_cleanup',
    'export_retained',
    'export_owner',
    'coordinator_direct',
    'operational_retained',
    'no_member_personal_data',
    '설치 SQL 또는 update SQL 기준',
    '.tools/bin/check-privacy-contract-matrix.php',
] as $needle) {
    if ($matrix !== '' && strpos($matrix, $needle) === false) {
        sr_privacy_matrix_error('privacy matrix document is missing marker: ' . $needle);
    }
}

foreach ([
    'admin' => '관리자 권한',
    'embed_manager' => '본문 참조',
    'logo_manager' => '로고 변경',
] as $moduleKey => $retentionMarker) {
    if ($matrix !== '' && strpos($matrix, '| `' . $moduleKey . '` | ' . $retentionMarker) === false) {
        sr_privacy_matrix_error('operational retained module is missing detailed retention row: ' . $moduleKey);
    }
}

foreach ([
    'asset_exchange' => ['환전 실행 증빙', '환전 묶음 ID'],
    'coupon' => ['쿠폰 지급/사용/환불 권리 증빙', 'refunded_by_account_id'],
    'deposit' => ['현금성 예치금 원장', '은행명/계좌번호/예금주'],
    'notification' => ['회원 알림 제공 이력', 'delivery destination/metadata'],
    'point' => ['포인트 원장', '만료 source/consume transaction 연결'],
    'reward' => ['적립금 원장', '은행명/계좌번호/예금주'],
] as $moduleKey => $markers) {
    if ($matrix !== '' && strpos($matrix, '| `' . $moduleKey . '` | ' . $markers[0]) === false) {
        sr_privacy_matrix_error('export retained module is missing detailed retention row: ' . $moduleKey);
        continue;
    }

    foreach ($markers as $marker) {
        if ($matrix !== '' && strpos($matrix, $marker) === false) {
            sr_privacy_matrix_error('export retained module detail is missing marker for ' . $moduleKey . ': ' . $marker);
        }
    }
}

foreach ([
    '## 운영 보존 세부 기준',
    '보존 사유',
    '운영자 접근 범위',
    '1.0 전 검토 항목',
    '탈퇴 계정 표시명',
    'orphan ref',
    '감사 로그로 충분히 대체',
    '재식별성이 높은 필드',
    '보존기간 정책',
    '고위험 필드/연결',
    '계좌정보 마스킹',
    '주소 마스킹',
] as $needle) {
    if ($matrix !== '' && strpos($matrix, $needle) === false) {
        sr_privacy_matrix_error('privacy matrix document is missing retention policy marker: ' . $needle);
    }
}

if ($errors !== []) {
    fwrite(STDERR, "privacy contract matrix check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "privacy contract matrix check completed.\n";
