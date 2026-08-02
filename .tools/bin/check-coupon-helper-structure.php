#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

$errors = [];
$files = [
    'foundation.php',
    'claim-campaigns.php',
    'target-contracts.php',
    'admin-queries.php',
    'issuance.php',
    'paid-claims.php',
    'account-coupons.php',
    'refunds.php',
    'redemption.php',
];

$entryFile = 'modules/coupon/helpers.php';
$entrySource = file_get_contents($entryFile);
if (!is_string($entrySource)) {
    $errors[] = '쿠폰 helper 집계 파일을 읽을 수 없습니다.';
    $entrySource = '';
}
if (substr_count($entrySource, "\n") + 1 > 40 || preg_match('/^function\s+/m', $entrySource) === 1) {
    $errors[] = 'modules/coupon/helpers.php는 구현 없이 명시적 include 순서만 유지해야 합니다.';
}

$lastIndex = -1;
$functions = [];
foreach ($files as $file) {
    $path = 'modules/coupon/helpers/' . $file;
    $source = file_get_contents($path);
    if (!is_string($source)) {
        $errors[] = '쿠폰 helper 구현 파일을 읽을 수 없습니다: ' . $path;
        continue;
    }

    $marker = "require_once __DIR__ . '/helpers/" . $file . "';";
    $index = strpos($entrySource, $marker);
    if ($index === false || $index <= $lastIndex) {
        $errors[] = '쿠폰 helper include 순서가 올바르지 않습니다: ' . $file;
    }
    $lastIndex = is_int($index) ? $index : $lastIndex;

    if (substr_count($source, "\n") + 1 > 900) {
        $errors[] = '쿠폰 helper 구현 파일이 다시 과대해졌습니다: ' . $path;
    }

    preg_match_all('/^function\s+(sr_coupon_[a-z0-9_]+)\s*\(/m', $source, $matches);
    foreach ($matches[1] ?? [] as $function) {
        if (isset($functions[$function])) {
            $errors[] = '쿠폰 helper 함수가 여러 파일에 중복 선언되었습니다: ' . $function;
        }
        $functions[$function] = $path;
    }
}

require_once $root . '/' . $entryFile;
foreach ([
    'sr_coupon_default_settings',
    'sr_coupon_claim_campaign_by_key',
    'sr_coupon_target_contracts',
    'sr_coupon_admin_definitions',
    'sr_coupon_issue_to_account',
    'sr_coupon_claim_paid_campaign_with_asset',
    'sr_coupon_active_account_issues',
    'sr_coupon_refund_redemption',
    'sr_coupon_redeem_for_target',
] as $function) {
    if (!function_exists($function)) {
        $errors[] = '쿠폰 helper 대표 함수를 불러올 수 없습니다: ' . $function;
    }
}

if ($errors !== []) {
    fwrite(STDERR, "coupon helper structure checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo 'coupon helper structure checks completed. files=' . count($files) . ' functions=' . count($functions) . "\n";
