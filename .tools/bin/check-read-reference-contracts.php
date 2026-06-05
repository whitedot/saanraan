#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

define('SR_ROOT', $root);
require_once 'core/version.php';
require_once 'core/helpers.php';

$errors = [];

function sr_read_reference_check_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_read_reference_check_module_dirs(): array
{
    $dirs = [];
    foreach (new DirectoryIterator('modules') as $entry) {
        if ($entry->isDot() || !$entry->isDir()) {
            continue;
        }
        $dirs[] = $entry->getPathname();
    }
    sort($dirs);

    return $dirs;
}

function sr_read_reference_check_metadata(string $moduleDir): array
{
    $moduleFile = $moduleDir . '/module.php';
    if (!is_file($moduleFile)) {
        return [];
    }

    $metadata = include $moduleFile;
    return is_array($metadata) ? $metadata : [];
}

function sr_read_reference_check_declared(array $metadata, string $key): array
{
    $contracts = is_array($metadata['contracts'] ?? null) ? $metadata['contracts'] : [];
    $files = is_array($contracts[$key] ?? null) ? $contracts[$key] : [];

    return array_values(array_filter(array_map('strval', $files)));
}

function sr_read_reference_check_entries(array $contract): array
{
    if (isset($contract['count_function']) || isset($contract['rows_function'])) {
        return [$contract];
    }

    return $contract;
}

function sr_read_reference_check_callable_signature(string $path, string $functionKey, string $functionName): void
{
    if (!function_exists($functionName)) {
        return;
    }

    if (!sr_read_reference_callable_signature_is_valid($functionKey, $functionName)) {
        sr_read_reference_check_error('read reference callable signature mismatch: ' . $path . ' ' . $functionKey . ' ' . $functionName);
    }
}

function sr_read_reference_check_count_function_source(string $path, string $functionName): void
{
    if (!function_exists($functionName)) {
        return;
    }

    $reflection = new ReflectionFunction($functionName);
    $fileName = $reflection->getFileName();
    if (!is_string($fileName) || !is_file($fileName)) {
        sr_read_reference_check_error('read reference count function source is not readable: ' . $path . ' ' . $functionName);
        return;
    }

    $lines = file($fileName);
    if (!is_array($lines)) {
        sr_read_reference_check_error('read reference count function source is not readable: ' . $path . ' ' . $functionName);
        return;
    }

    $body = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
    if (strpos($body, 'return count(') === false) {
        sr_read_reference_check_error('read reference count_function must count returned reference rows: ' . $path . ' ' . $functionName);
    }
}

function sr_read_reference_check_helper_values(string $moduleDir, string $path, $helpers): array
{
    if (is_string($helpers) && $helpers !== '') {
        $helpers = [$helpers];
    }
    if ($helpers === null || $helpers === '') {
        $helpers = [];
    }
    if (!is_array($helpers)) {
        sr_read_reference_check_error('read reference helpers must be string or array: ' . $path);
        return [];
    }

    $validHelpers = [];
    foreach ($helpers as $helper) {
        if (!is_string($helper)) {
            sr_read_reference_check_error('read reference helper is invalid or missing: ' . $path);
            continue;
        }
        $helper = trim($helper);
        if (preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helper) !== 1 || !is_file($moduleDir . '/' . $helper)) {
            sr_read_reference_check_error('read reference helper is invalid or missing: ' . $path . ' ' . $helper);
            continue;
        }
        $validHelpers[] = $helper;
    }

    return $validHelpers;
}

function sr_read_reference_check_forbidden_owner_writes(string $root): void
{
    $rules = [
        'coupon' => [
            'sr_quiz_',
            'sr_content_',
            'sr_community_',
            'sr_commerce_',
        ],
        'banner' => [
            'sr_content_',
            'sr_community_',
            'sr_site_menu_',
        ],
        'popup_layer' => [
            'sr_content_',
            'sr_community_',
            'sr_site_menu_',
        ],
        'member' => [
            'sr_reward_',
            'sr_deposit_',
            'sr_content_',
            'sr_community_',
            'sr_asset_exchange_',
        ],
        'admin' => [
            'sr_seo_',
            'sr_logo_manager_',
        ],
    ];

    foreach ($rules as $moduleKey => $forbiddenPrefixes) {
        $moduleDir = $root . '/modules/' . $moduleKey;
        if (!is_dir($moduleDir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleDir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            if (!is_string($contents)) {
                continue;
            }
            foreach ($forbiddenPrefixes as $prefix) {
                if (preg_match('/\b(?:UPDATE|DELETE\s+FROM)\s+`?' . preg_quote($prefix, '/') . '[a-z0-9_]*`?/i', $contents) === 1) {
                    sr_read_reference_check_error('read reference owner must not write consumer policy table directly: ' . $file->getPathname() . ' ' . $prefix);
                }
            }
        }
    }
}

function sr_read_reference_check_admin_url_safety_samples(): void
{
    $validUrls = [
        '/admin/content',
        '/admin/content/edit?id=1',
    ];
    foreach ($validUrls as $url) {
        if (!sr_read_reference_admin_url_is_safe($url)) {
            sr_read_reference_check_error('read reference admin URL safety rejected valid sample: ' . $url);
        }
    }

    $invalidUrls = [
        '',
        'https://example.com/admin',
        '//example.com/admin',
        '/admin/../settings',
        '/admin/%2e%2e/settings',
        '/admin/%2f../settings',
        "/admin/settings\n",
    ];
    foreach ($invalidUrls as $url) {
        if (sr_read_reference_admin_url_is_safe($url)) {
            sr_read_reference_check_error('read reference admin URL safety accepted invalid sample: ' . str_replace("\n", '\\n', $url));
        }
    }
}

function sr_read_reference_check_sample_count(PDO $pdo, array $target, array $context): int
{
    unset($pdo, $target, $context);

    return 0;
}

function sr_read_reference_check_sample_rows(PDO $pdo, array $target, array $context): array
{
    unset($pdo, $target, $context);

    return [];
}

function sr_read_reference_check_sample_health(PDO $pdo, array $target, array $row, array $context): array
{
    unset($pdo, $target, $row, $context);

    return ['status' => 'ok'];
}

function sr_read_reference_check_sample_admin_url(array $row, array $context): string
{
    unset($row, $context);

    return '/admin/sample';
}

function sr_read_reference_check_prepare_entry_samples(): void
{
    $entry = [
        'consumer_module_key' => 'sample_module',
        'label' => 'sample',
        'reference_type' => 'sample_reference',
        'supports_target_types' => ['banner'],
        'count_function' => 'sr_read_reference_check_sample_count',
        'rows_function' => 'sr_read_reference_check_sample_rows',
        'health_function' => 'sr_read_reference_check_sample_health',
        'admin_url_function' => 'sr_read_reference_check_sample_admin_url',
    ];

    $validErrors = sr_read_reference_prepare_entry('sample_module', $entry, 'banner');
    if ($validErrors !== []) {
        sr_read_reference_check_error('read reference prepare entry sample rejected valid supports_target_types');
    }

    $spacedEntry = $entry;
    $spacedEntry['supports_target_types'] = [' banner '];
    $spacedErrors = sr_read_reference_prepare_entry('sample_module', $spacedEntry, 'banner');
    if (!in_array('supports_target_types가 대상 type과 맞지 않습니다.', $spacedErrors, true)) {
        sr_read_reference_check_error('read reference prepare entry sample accepted spaced supports_target_types');
    }

    $invalidReferenceTypeEntry = $entry;
    $invalidReferenceTypeEntry['reference_type'] = ' sample_reference ';
    $invalidReferenceTypeErrors = sr_read_reference_prepare_entry('sample_module', $invalidReferenceTypeEntry, 'banner');
    if (!in_array('reference_type 값이 올바르지 않습니다.', $invalidReferenceTypeErrors, true)) {
        sr_read_reference_check_error('read reference prepare entry sample accepted invalid reference_type');
    }
}

function sr_read_reference_check_normalize_row_target_samples(): void
{
    $entry = [
        'consumer_module_key' => 'sample_module',
        'reference_type' => 'sample_reference',
    ];
    $target = [
        'target_type' => 'site_setting',
        'target_id' => 0,
        'target_key' => 'site.name',
    ];
    $baseRow = [
        'consumer_module_key' => 'sample_module',
        'reference_type' => 'sample_reference',
        'reference_id' => 'sample:1',
        'title' => 'sample',
        'target_type' => 'site_setting',
        'target_id' => '0',
        'target_key' => 'site.name',
    ];

    $valid = sr_read_reference_normalize_row('sample_module', $entry, $target, $baseRow, ['status' => 'ok'], '/admin/sample');
    if (!is_array($valid['row'] ?? null) || ($valid['errors'] ?? []) !== []) {
        sr_read_reference_check_error('read reference normalize row target sample rejected valid row');
    }

    $validRawStatus = $baseRow;
    $validRawStatus['status'] = 'stale';
    $validRawStatusRow = sr_read_reference_normalize_row('sample_module', $entry, $target, $validRawStatus, ['status' => 'ok'], '/admin/sample');
    if (!is_array($validRawStatusRow['row'] ?? null) || ($validRawStatusRow['row']['status'] ?? '') !== 'ok' || ($validRawStatusRow['errors'] ?? []) !== []) {
        sr_read_reference_check_error('read reference normalize row target sample rejected valid raw status');
    }

    $invalidRawStatus = $baseRow;
    $invalidRawStatus['status'] = 'archived';
    $invalidRawStatusRow = sr_read_reference_normalize_row('sample_module', $entry, $target, $invalidRawStatus, ['status' => 'ok'], '/admin/sample');
    if (is_array($invalidRawStatusRow['row'] ?? null) || !in_array('status 값이 올바르지 않습니다.', $invalidRawStatusRow['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted invalid raw status');
    }

    $nullRawStatus = $baseRow;
    $nullRawStatus['status'] = null;
    $nullRawStatusRow = sr_read_reference_normalize_row('sample_module', $entry, $target, $nullRawStatus, ['status' => 'ok'], '/admin/sample');
    if (is_array($nullRawStatusRow['row'] ?? null) || !in_array('status 값이 올바르지 않습니다.', $nullRawStatusRow['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted null raw status');
    }

    $validRawAdminUrl = $baseRow;
    $validRawAdminUrl['admin_url'] = '/admin/sample';
    $validRawAdminUrlRow = sr_read_reference_normalize_row('sample_module', $entry, $target, $validRawAdminUrl, ['status' => 'ok'], '/admin/sample');
    if (!is_array($validRawAdminUrlRow['row'] ?? null) || ($validRawAdminUrlRow['errors'] ?? []) !== []) {
        sr_read_reference_check_error('read reference normalize row target sample rejected valid raw admin_url');
    }

    $invalidRawAdminUrl = $baseRow;
    $invalidRawAdminUrl['admin_url'] = 'https://example.com/admin';
    $invalidRawAdminUrlRow = sr_read_reference_normalize_row('sample_module', $entry, $target, $invalidRawAdminUrl, ['status' => 'ok'], '/admin/sample');
    if (is_array($invalidRawAdminUrlRow['row'] ?? null) || !in_array('admin_url이 내부 상대 URL이 아닙니다.', $invalidRawAdminUrlRow['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted external raw admin_url');
    }

    $nullRawAdminUrl = $baseRow;
    $nullRawAdminUrl['admin_url'] = null;
    $nullRawAdminUrlRow = sr_read_reference_normalize_row('sample_module', $entry, $target, $nullRawAdminUrl, ['status' => 'ok'], '/admin/sample');
    if (is_array($nullRawAdminUrlRow['row'] ?? null) || !in_array('admin_url이 내부 상대 URL이 아닙니다.', $nullRawAdminUrlRow['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted null raw admin_url');
    }

    $invalidHealthStatus = sr_read_reference_normalize_row('sample_module', $entry, $target, $baseRow, ['status' => null], '/admin/sample');
    if (is_array($invalidHealthStatus['row'] ?? null) || !in_array('status 값이 올바르지 않습니다.', $invalidHealthStatus['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted null health status');
    }

    $invalidHealthMessage = sr_read_reference_normalize_row('sample_module', $entry, $target, $baseRow, [
        'status' => 'ok',
        'message' => null,
    ], '/admin/sample');
    if (is_array($invalidHealthMessage['row'] ?? null) || !in_array('message 값이 올바르지 않습니다.', $invalidHealthMessage['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted null health message');
    }

    $invalidHealthPolicyStatus = sr_read_reference_normalize_row('sample_module', $entry, $target, $baseRow, [
        'status' => 'ok',
        'policy_status' => null,
    ], '/admin/sample');
    if (is_array($invalidHealthPolicyStatus['row'] ?? null) || !in_array('policy_status 값이 올바르지 않습니다.', $invalidHealthPolicyStatus['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted null health policy_status');
    }

    $mismatchedReferenceType = $baseRow;
    $mismatchedReferenceType['reference_type'] = 'other_reference';
    $mismatchedReference = sr_read_reference_normalize_row('sample_module', $entry, $target, $mismatchedReferenceType, ['status' => 'ok'], '/admin/sample');
    if (is_array($mismatchedReference['row'] ?? null) || !in_array('reference_type이 계약 항목과 맞지 않습니다.', $mismatchedReference['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted mismatched reference_type');
    }

    $invalidReferenceType = $baseRow;
    $invalidReferenceType['reference_type'] = ' sample_reference ';
    $invalidReference = sr_read_reference_normalize_row('sample_module', $entry, $target, $invalidReferenceType, ['status' => 'ok'], '/admin/sample');
    if (is_array($invalidReference['row'] ?? null) || !in_array('reference_type 값이 올바르지 않습니다.', $invalidReference['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted invalid reference_type');
    }

    $nullConsumerModuleKey = $baseRow;
    $nullConsumerModuleKey['consumer_module_key'] = null;
    $nullConsumer = sr_read_reference_normalize_row('sample_module', $entry, $target, $nullConsumerModuleKey, ['status' => 'ok'], '/admin/sample');
    if (is_array($nullConsumer['row'] ?? null) || !in_array('consumer_module_key 필수값이 비어 있습니다.', $nullConsumer['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted null consumer_module_key');
    }

    $nullReferenceType = $baseRow;
    $nullReferenceType['reference_type'] = null;
    $nullReference = sr_read_reference_normalize_row('sample_module', $entry, $target, $nullReferenceType, ['status' => 'ok'], '/admin/sample');
    if (is_array($nullReference['row'] ?? null) || !in_array('reference_type 필수값이 비어 있습니다.', $nullReference['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted null reference_type');
    }

    $missingTargetKey = $baseRow;
    unset($missingTargetKey['target_key']);
    $missing = sr_read_reference_normalize_row('sample_module', $entry, $target, $missingTargetKey, ['status' => 'ok'], '/admin/sample');
    if (is_array($missing['row'] ?? null) || !in_array('target_key가 조회 대상과 맞지 않습니다.', $missing['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted missing target_key');
    }

    $mismatchedTargetKey = $baseRow;
    $mismatchedTargetKey['target_key'] = 'site.description';
    $mismatched = sr_read_reference_normalize_row('sample_module', $entry, $target, $mismatchedTargetKey, ['status' => 'ok'], '/admin/sample');
    if (is_array($mismatched['row'] ?? null) || !in_array('target_key가 조회 대상과 맞지 않습니다.', $mismatched['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted mismatched target_key');
    }

    $invalidRowTargetKey = $baseRow;
    $invalidRowTargetKey['target_key'] = ' site.name ';
    $invalidKeyRow = sr_read_reference_normalize_row('sample_module', $entry, $target, $invalidRowTargetKey, ['status' => 'ok'], '/admin/sample');
    if (is_array($invalidKeyRow['row'] ?? null) || !in_array('target_key 값이 올바르지 않습니다.', $invalidKeyRow['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted invalid row target_key');
    }

    $unexpectedTargetKey = $baseRow;
    $unexpectedTargetKey['target_type'] = 'banner';
    $unexpectedTargetKey['target_id'] = '10';
    $unexpected = sr_read_reference_normalize_row('sample_module', $entry, [
        'target_type' => 'banner',
        'target_id' => 10,
        'target_key' => '',
    ], $unexpectedTargetKey, ['status' => 'ok'], '/admin/sample');
    if (is_array($unexpected['row'] ?? null) || !in_array('target_key가 조회 대상과 맞지 않습니다.', $unexpected['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted unexpected target_key');
    }

    $invalidRowTargetId = $baseRow;
    $invalidRowTargetId['target_type'] = 'banner';
    $invalidRowTargetId['target_id'] = true;
    unset($invalidRowTargetId['target_key']);
    $invalidIdRow = sr_read_reference_normalize_row('sample_module', $entry, [
        'target_type' => 'banner',
        'target_id' => 1,
        'target_key' => '',
    ], $invalidRowTargetId, ['status' => 'ok'], '/admin/sample');
    if (is_array($invalidIdRow['row'] ?? null) || !in_array('target_id 필수값이 비어 있습니다.', $invalidIdRow['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted invalid row target_id');
    }

    $nullRowTargetId = $baseRow;
    $nullRowTargetId['target_type'] = 'banner';
    $nullRowTargetId['target_id'] = null;
    unset($nullRowTargetId['target_key']);
    $nullIdRow = sr_read_reference_normalize_row('sample_module', $entry, [
        'target_type' => 'banner',
        'target_id' => 1,
        'target_key' => '',
    ], $nullRowTargetId, ['status' => 'ok'], '/admin/sample');
    if (is_array($nullIdRow['row'] ?? null) || !in_array('target_id 필수값이 비어 있습니다.', $nullIdRow['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted null row target_id');
    }

    $invalidRowTargetType = $baseRow;
    $invalidRowTargetType['target_type'] = true;
    $invalidTypeRow = sr_read_reference_normalize_row('sample_module', $entry, $target, $invalidRowTargetType, ['status' => 'ok'], '/admin/sample');
    if (is_array($invalidTypeRow['row'] ?? null) || !in_array('target_type 필수값이 비어 있습니다.', $invalidTypeRow['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted invalid row target_type');
    }

    $invalidTargetTypeRow = $baseRow;
    $invalidTargetTypeRow['target_type'] = 'banner';
    $invalidTargetTypeRow['target_id'] = '1';
    unset($invalidTargetTypeRow['target_key']);
    $invalidTarget = sr_read_reference_normalize_row('sample_module', $entry, [
        'target_type' => 'banner',
        'target_id' => true,
        'target_key' => '',
    ], $invalidTargetTypeRow, ['status' => 'ok'], '/admin/sample');
    if (is_array($invalidTarget['row'] ?? null) || !in_array('target_id가 조회 대상과 맞지 않습니다.', $invalidTarget['errors'] ?? [], true)) {
        sr_read_reference_check_error('read reference normalize row target sample accepted invalid typed target_id');
    }
}

function sr_read_reference_check_collect_target_samples(): void
{
    $validSamples = [
        ['coupon-references.php', ['target_type' => 'coupon_definition', 'target_id' => 1, 'target_key' => 'welcome_coupon']],
        ['banner-references.php', ['target_type' => 'banner', 'target_id' => 1, 'target_key' => '']],
        ['popup-layer-references.php', ['target_type' => 'popup_layer', 'target_id' => 1, 'target_key' => '']],
        ['member-group-references.php', ['target_type' => 'member_group', 'target_id' => 1, 'target_key' => 'vip_member']],
        ['site-setting-references.php', ['target_type' => 'site_setting', 'target_id' => 0, 'target_key' => 'site.name']],
    ];
    foreach ($validSamples as [$contractFile, $target]) {
        if (sr_read_reference_target_errors($contractFile, $target) !== []) {
            sr_read_reference_check_error('read reference target sample rejected valid target: ' . $contractFile);
        }
    }

    $invalidSamples = [
        ['banner-references.php', ['target_type' => 'banner', 'target_id' => 0, 'target_key' => ''], '읽기 참조 대상 ID가 올바르지 않습니다.'],
        ['banner-references.php', ['target_type' => 'banner', 'target_id' => true, 'target_key' => ''], '읽기 참조 대상 ID가 올바르지 않습니다.'],
        ['banner-references.php', ['target_type' => 'banner', 'target_id' => ' 1 ', 'target_key' => ''], '읽기 참조 대상 ID가 올바르지 않습니다.'],
        ['banner-references.php', ['target_type' => 'banner', 'target_id' => '01', 'target_key' => ''], '읽기 참조 대상 ID가 올바르지 않습니다.'],
        ['member-group-references.php', ['target_type' => 'member_group', 'target_id' => 1, 'target_key' => ''], '읽기 참조 대상 key가 비어 있습니다.'],
        ['member-group-references.php', ['target_type' => 'member_group', 'target_id' => 1, 'target_key' => ' vip_member '], '읽기 참조 대상 key가 올바르지 않습니다.'],
        ['member-group-references.php', ['target_type' => 'member_group', 'target_id' => 1.5, 'target_key' => 'vip_member'], '읽기 참조 대상 ID가 올바르지 않습니다.'],
        ['member-group-references.php', ['target_type' => 'member_group', 'target_id' => 1, 'target_key' => true], '읽기 참조 대상 key가 올바르지 않습니다.'],
        ['site-setting-references.php', ['target_type' => 'site_setting', 'target_id' => 1, 'target_key' => 'site.name'], '읽기 참조 대상 ID가 올바르지 않습니다.'],
        ['site-setting-references.php', ['target_type' => 'site_setting', 'target_id' => 0, 'target_key' => ['site.name']], '읽기 참조 대상 key가 올바르지 않습니다.'],
    ];
    foreach ($invalidSamples as [$contractFile, $target, $expectedError]) {
        $errors = sr_read_reference_target_errors($contractFile, $target);
        if (!in_array($expectedError, $errors, true)) {
            sr_read_reference_check_error('read reference target sample accepted invalid target: ' . $contractFile);
        }
    }
}

function sr_read_reference_check_collect_count_guard_source(string $root): void
{
    $path = $root . '/core/helpers/read-references.php';
    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        sr_read_reference_check_error('read reference helper is not readable: ' . $path);
        return;
    }

    if (strpos($contents, 'count($rawRows) !== $count') === false) {
        sr_read_reference_check_error('read reference collect must reject count_function and rows_function count mismatch');
    }
}

function sr_read_reference_check_keyed_contract_row_sources(string $root): void
{
    $couponHelper = $root . '/modules/coupon/helpers.php';
    $contents = is_file($couponHelper) ? file_get_contents($couponHelper) : false;
    if (!is_string($contents)) {
        sr_read_reference_check_error('read reference coupon helper is missing: ' . $couponHelper);
        return;
    }

    if (strpos($contents, '$targetKey = (string) ($target[\'target_key\'] ?? \'\');') === false) {
        sr_read_reference_check_error('read reference coupon rows must normalize target_key from target only');
    }
    if (substr_count($contents, "'target_key' => \$targetKey") < 2) {
        sr_read_reference_check_error('read reference coupon issue and redemption rows must include target_key');
    }
}

$readReferenceFiles = array_keys(sr_read_reference_contract_files());
$expectedConsumers = [
    'coupon' => ['coupon-references.php'],
    'banner' => ['banner-references.php'],
    'popup_layer' => ['popup-layer-references.php'],
    'member' => ['member-group-references.php'],
    'admin' => ['site-setting-references.php'],
];

foreach (sr_read_reference_check_module_dirs() as $moduleDir) {
    $moduleKey = basename($moduleDir);
    $metadata = sr_read_reference_check_metadata($moduleDir);
    $provides = sr_read_reference_check_declared($metadata, 'provides');
    $consumes = sr_read_reference_check_declared($metadata, 'consumes');

    foreach ($readReferenceFiles as $contractFile) {
        $path = $moduleDir . '/' . $contractFile;
        if (!is_file($path)) {
            continue;
        }

        if (!in_array($contractFile, $provides, true)) {
            sr_read_reference_check_error('read reference provider must declare contracts.provides: ' . $path);
        }

        $contract = include $path;
        if (!is_array($contract)) {
            sr_read_reference_check_error('read reference contract must return array: ' . $path);
            continue;
        }

        foreach (sr_read_reference_check_entries($contract) as $entry) {
            if (!is_array($entry)) {
                sr_read_reference_check_error('read reference entry must be array: ' . $path);
                continue;
            }

            if ((string) ($entry['consumer_module_key'] ?? '') !== $moduleKey) {
                sr_read_reference_check_error('read reference consumer_module_key must match module: ' . $path);
            }

            $expectedTargetType = (string) (sr_read_reference_contract_files()[$contractFile] ?? '');
            $supportsTargetTypes = $entry['supports_target_types'] ?? null;
            if (!is_array($supportsTargetTypes) || $supportsTargetTypes === []) {
                sr_read_reference_check_error('read reference entry requires supports_target_types: ' . $path);
            } else {
                foreach ($supportsTargetTypes as $targetType) {
                    if (!is_string($targetType)) {
                        sr_read_reference_check_error('read reference supports_target_types must match contract target type: ' . $path);
                        continue;
                    }
                    if ($targetType !== $expectedTargetType) {
                        sr_read_reference_check_error('read reference supports_target_types must match contract target type: ' . $path . ' ' . $targetType);
                    }
                }
            }

            foreach (['label', 'reference_type', 'count_function', 'rows_function', 'health_function', 'admin_url_function'] as $requiredKey) {
                if (!is_string($entry[$requiredKey] ?? null) || trim((string) $entry[$requiredKey]) === '') {
                    sr_read_reference_check_error('read reference entry requires ' . $requiredKey . ': ' . $path);
                }
            }
            if (is_string($entry['reference_type'] ?? null) && !sr_read_reference_reference_type_is_valid((string) $entry['reference_type'])) {
                sr_read_reference_check_error('read reference entry reference_type is invalid: ' . $path);
            }

            foreach (sr_read_reference_check_helper_values($moduleDir, $path, $entry['helpers'] ?? []) as $helper) {
                require_once $moduleDir . '/' . $helper;
            }

            foreach (['count_function', 'rows_function', 'health_function', 'admin_url_function'] as $functionKey) {
                if (!is_string($entry[$functionKey] ?? null)) {
                    continue;
                }
                $functionName = trim((string) $entry[$functionKey]);
                if ($functionName !== '' && !function_exists($functionName)) {
                    sr_read_reference_check_error('read reference callable does not exist: ' . $path . ' ' . $functionName);
                    continue;
                }
                sr_read_reference_check_callable_signature($path, $functionKey, $functionName);
                if ($functionKey === 'count_function') {
                    sr_read_reference_check_count_function_source($path, $functionName);
                }
            }
        }
    }

    if (is_file($moduleDir . '/coupon-targets.php')) {
        $contract = include $moduleDir . '/coupon-targets.php';
        if (!is_array($contract)) {
            sr_read_reference_check_error('coupon-targets.php must return array: ' . $moduleDir);
            continue;
        }
        foreach ($contract as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            foreach (['health_function', 'admin_url_function'] as $optionalKey) {
                if (!isset($entry[$optionalKey])) {
                    continue;
                }
                if (!is_string($entry[$optionalKey]) || trim((string) $entry[$optionalKey]) === '') {
                    sr_read_reference_check_error('coupon-targets optional callable must be string: ' . $moduleDir . ' ' . $optionalKey);
                    continue;
                }
                $functionName = trim((string) $entry[$optionalKey]);
                foreach (sr_read_reference_check_helper_values($moduleDir, $moduleDir . '/coupon-targets.php', $entry['helpers'] ?? []) as $helper) {
                    require_once $moduleDir . '/' . $helper;
                }
                if (!function_exists($functionName)) {
                    sr_read_reference_check_error('coupon-targets optional callable does not exist: ' . $moduleDir . ' ' . $functionName);
                    continue;
                }
                $reflection = new ReflectionFunction($functionName);
                $expectedParameterCount = $optionalKey === 'health_function' ? 3 : 2;
                if ($reflection->getNumberOfRequiredParameters() > $expectedParameterCount || $reflection->getNumberOfParameters() < $expectedParameterCount) {
                    sr_read_reference_check_error('coupon-targets optional callable signature mismatch: ' . $moduleDir . ' ' . $functionName);
                }
            }
        }
    }
}

foreach ($expectedConsumers as $moduleKey => $contractFiles) {
    $metadata = sr_read_reference_check_metadata('modules/' . $moduleKey);
    $consumes = sr_read_reference_check_declared($metadata, 'consumes');
    foreach ($contractFiles as $contractFile) {
        if (!in_array($contractFile, $consumes, true)) {
            sr_read_reference_check_error('read reference owner must declare contracts.consumes: modules/' . $moduleKey . '/module.php ' . $contractFile);
        }
    }
}

sr_read_reference_check_forbidden_owner_writes($root);
sr_read_reference_check_admin_url_safety_samples();
sr_read_reference_check_prepare_entry_samples();
sr_read_reference_check_normalize_row_target_samples();
sr_read_reference_check_collect_target_samples();
sr_read_reference_check_collect_count_guard_source($root);
sr_read_reference_check_keyed_contract_row_sources($root);

if ($errors !== []) {
    fwrite(STDERR, "read reference contract checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "read reference contract checks completed.\n";
