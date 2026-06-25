#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

$helper = file_get_contents($root . '/modules/coupon/helpers.php');
if (!is_string($helper)) {
    $errors[] = 'Coupon helper cannot be read.';
} else {
    if (strpos($helper, 'function sr_coupon_key_is_valid') === false
        || strpos($helper, "'/\\A[a-z][a-z0-9_]{1,59}\\z/'") === false
    ) {
        $errors[] = 'Coupon definitions must validate coupon_key with the admin key pattern server-side.';
    }
    if (strpos($helper, "preg_match('/\\A[1-9][0-9]*\\z/', \$maxUsesString)") === false) {
        $errors[] = 'Coupon max_uses_per_issue must reject non-integer POST values server-side.';
    }
    if (strpos($helper, 'SELECT id FROM sr_coupon_definitions WHERE coupon_key = :coupon_key LIMIT 1') === false) {
        $errors[] = 'Coupon definitions must reject duplicate coupon_key values before insert.';
    }
    if (strpos($helper, 'function sr_coupon_expire_active_issues(PDO $pdo, ?int $accountId = null): int') === false
        || substr_count($helper, 'sr_coupon_expire_active_issues($pdo') < 4
    ) {
        $errors[] = 'Coupon issue queries and redemption must transition expired active issues.';
    }
    if (strpos($helper, 'sr_coupon_redemption_pricing_columns_available($pdo)') === false
        || strpos($helper, 'r.amount, r.currency_code, r.asset_unit, r.policy_summary, r.priced_at') === false
    ) {
        $errors[] = 'Coupon admin redemption queries must include pricing snapshot columns with a legacy fallback.';
    }
    if (strpos($helper, 'function sr_coupon_target_capability_summary(array $capabilities): string') === false
        || strpos($helper, "'capability_label'") === false
        || strpos($helper, "'pricing_label'") === false
        || strpos($helper, "['source' => 'admin_lookup']") === false
    ) {
        $errors[] = 'Coupon target lookup results must expose capability and pricing summaries for admin selection.';
    }
    if (strpos($helper, 'function sr_coupon_assert_refundable_target_contract(PDO $pdo, string $targetType, string $refundablePolicy): void') === false
        || strpos($helper, 'sr_coupon_target_contract_has_capability($target, \'revoke_access\')') === false
        || strpos($helper, '환급 가능 쿠폰은 접근권 회수를 지원하는 사용처에만 연결할 수 있습니다.') === false
    ) {
        $errors[] = 'Coupon definition save must require revoke_access capability for refundable target-specific coupons.';
    }
}

$action = file_get_contents($root . '/modules/coupon/actions/admin-coupons.php');
if (!is_string($action)) {
    $errors[] = 'Coupon admin action cannot be read.';
} elseif (strpos($action, "'max_uses_per_issue' => sr_post_string('max_uses_per_issue', 10)") === false
    || strpos($action, "'max_uses_per_issue' => (int) sr_post_string('max_uses_per_issue', 10)") !== false
) {
    $errors[] = 'Coupon admin action must pass raw max_uses_per_issue input to server-side validation.';
}

$view = file_get_contents($root . '/modules/coupon/views/admin-coupons.php');
if (!is_string($view)) {
    $errors[] = 'Coupon admin view cannot be read.';
} elseif (strpos($view, 'pattern="[a-z][a-z0-9_]{1,59}"') === false
    || strpos($view, 'data-admin-key-input') === false
    || strpos($view, 'inputmode="latin"') === false
    || strpos($view, 'autocapitalize="none"') === false
    || strpos($view, 'spellcheck="false"') === false
) {
    $errors[] = 'Coupon admin view must align coupon_key browser validation with admin key input rules.';
}
if (is_string($view)
    && (
        strpos($view, '<th>가격 스냅샷</th>') === false
        || strpos($view, '$redemptionHasPriceSnapshot') === false
    )
) {
    $errors[] = 'Coupon admin redemption view must expose the stored pricing snapshot.';
}
if (is_string($view)
    && (
        strpos($view, 'data-coupon-claim-campaign-form') === false
        || strpos($view, 'data-coupon-paid-required-input') === false
        || strpos($view, 'data-coupon-paid-asset-checkbox') === false
        || strpos($view, 'function syncClaimCampaignPaidFields(form)') === false
        || strpos($view, 'assetCheckboxes[0].setCustomValidity') === false
    )
) {
    $errors[] = 'Coupon paid claim campaign form must align conditional required UI, browser validation, and paid asset selection.';
}

$assetAdjustJs = file_get_contents($root . '/modules/admin/assets/asset-adjust.js');
if (!is_string($assetAdjustJs)
    || strpos($assetAdjustJs, 'item.capability_label ||') === false
    || strpos($assetAdjustJs, 'item.pricing_label ||') === false
    || strpos($assetAdjustJs, 'item.policy_summary ||') === false
) {
    $errors[] = 'Admin reference lookup rendering must display coupon capability and pricing metadata when supplied.';
}

$module = file_get_contents($root . '/modules/coupon/module.php');
$update = file_get_contents($root . '/modules/coupon/updates/2026.05.003.sql');
if (!is_string($module)
    || preg_match("/'version'\\s*=>\\s*'([0-9]{4}\\.[0-9]{2}\\.[0-9]{3})'/", $module, $versionMatch) !== 1
    || version_compare($versionMatch[1], '2026.05.003', '<')
) {
    $errors[] = 'Coupon module version must be bumped for the validation behavior change.';
}
if (!is_string($update) || strpos($update, "WHERE module_key = 'coupon'") === false) {
    $errors[] = 'Coupon validation update must include a module version marker update.';
}
$expiryUpdate = file_get_contents($root . '/modules/coupon/updates/2026.05.006.sql');
if (!is_string($expiryUpdate)
    || strpos($expiryUpdate, "SET status = 'expired'") === false
    || strpos($expiryUpdate, "WHERE module_key = 'coupon'") === false
) {
    $errors[] = 'Coupon expiry update must transition existing expired active issues and bump the module version.';
}

if ($errors !== []) {
    fwrite(STDERR, "coupon admin validation checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "coupon admin validation checks completed.\n";
