#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);

require_once $root . '/modules/admin/helpers/retention.php';

$errors = [];

function sr_retention_check_error(array &$errors, string $message): void
{
    $errors[] = $message;
}

$expectedKeys = [
    'auth_logs',
    'audit_logs',
    'password_resets',
    'email_verifications',
    'sessions',
    'runtime_sessions',
    'rate_limits',
    'content_asset_access_pending_logs',
    'content_asset_action_pending_logs',
    'community_asset_pending_logs',
    'banner_clicks',
    'notifications',
    'notification_deliveries',
    'notification_reads',
    'admin_notification_reads',
    'admin_notifications',
    'module_backups',
];

$targets = sr_admin_retention_target_definitions(true, true, true, true, true, true, true, true);
if (array_keys($targets) !== $expectedKeys) {
    sr_retention_check_error($errors, 'Retention target keys changed unexpectedly.');
}

$forbiddenRetentionTables = [
    'sr_content_asset_access_logs',
    'sr_content_asset_action_logs',
    'sr_community_asset_logs',
    'sr_point_transactions',
    'sr_reward_transactions',
    'sr_deposit_transactions',
    'sr_coupon_issues',
    'sr_coupon_redemptions',
];

foreach ($targets as $key => $target) {
    if (!array_key_exists('enabled', $target) || !is_bool($target['enabled'])) {
        sr_retention_check_error($errors, 'Retention target enabled flag is invalid: ' . $key);
    }

    if (empty($target['cutoff_key']) || !is_string($target['cutoff_key'])) {
        sr_retention_check_error($errors, 'Retention target cutoff key is missing: ' . $key);
    }

    if (!isset($target['count_callback']) && (empty($target['count_sql']) || !is_array($target['count_params'] ?? null))) {
        sr_retention_check_error($errors, 'Retention target count metadata is missing: ' . $key);
    }

    if (!isset($target['delete_callback']) && (empty($target['delete_sql']) || !is_array($target['delete_params'] ?? null))) {
        sr_retention_check_error($errors, 'Retention target delete metadata is missing: ' . $key);
    }

    $targetSql = implode("\n", [
        (string) ($target['count_sql'] ?? ''),
        (string) ($target['delete_sql'] ?? ''),
        (string) ($target['delete_limited_sql'] ?? ''),
    ]);
    foreach ($forbiddenRetentionTables as $tableName) {
        if (preg_match('/\b' . preg_quote($tableName, '/') . '\b/', $targetSql) === 1) {
            if (!str_ends_with($key, '_pending_logs') || strpos($targetSql, "log_status = 'pending'") === false || strpos($targetSql, 'transaction_id = 0') !== false) {
                sr_retention_check_error($errors, 'Retention target must not delete asset history table without pending status marker ' . $tableName . ': ' . $key);
            }
        }
    }
}

$disabledTargets = sr_admin_retention_target_definitions(false, false, false, false, false, false, false);
foreach (['sessions', 'runtime_sessions', 'rate_limits', 'content_asset_access_pending_logs', 'content_asset_action_pending_logs', 'community_asset_pending_logs', 'banner_clicks', 'notifications', 'notification_deliveries', 'notification_reads', 'admin_notification_reads', 'admin_notifications'] as $key) {
    if ($disabledTargets[$key]['enabled'] !== false) {
        sr_retention_check_error($errors, 'Retention optional target should be disabled: ' . $key);
    }
}

$cleanupKeys = sr_admin_retention_cleanup_target_keys();
sort($cleanupKeys);
$sortedExpectedKeys = $expectedKeys;
sort($sortedExpectedKeys);
if ($cleanupKeys !== $sortedExpectedKeys) {
    sr_retention_check_error($errors, 'Retention cleanup keys do not match target keys.');
}

$defaults = sr_admin_retention_default_values();
if (($defaults['banner_clicks_days'] ?? null) !== 180) {
    sr_retention_check_error($errors, 'Banner click hash retention default must stay 180 days.');
}

$bannerClickSql = implode("\n", [
    (string) ($targets['banner_clicks']['count_sql'] ?? ''),
    (string) ($targets['banner_clicks']['delete_sql'] ?? ''),
    (string) ($targets['banner_clicks']['delete_limited_sql'] ?? ''),
]);
if (($targets['banner_clicks']['enabled'] ?? null) !== true
    || ($targets['banner_clicks']['auto_scope'] ?? '') !== 'public'
    || ($targets['banner_clicks']['cutoff_key'] ?? '') !== 'banner_clicks'
    || !str_contains($bannerClickSql, 'sr_banner_clicks')
    || str_contains($bannerClickSql, 'sr_banners')
    || str_contains($bannerClickSql, 'click_count')) {
    sr_retention_check_error($errors, 'Banner click hash retention target is missing.');
}

$bannerHelper = file_get_contents($root . '/modules/banner/helpers.php');
if (!is_string($bannerHelper)) {
    sr_retention_check_error($errors, 'Banner helper cannot be read.');
} elseif (
    strpos($bannerHelper, "return 'account:' . (string) \$accountId;") === false
    || strpos($bannerHelper, "return 'session:' . hash('sha256', \$sessionId);") === false
    || strpos($bannerHelper, "return 'guest:' . sr_client_ip() . '|' . hash('sha256', sr_client_user_agent());") === false
    || strpos($bannerHelper, "sr_hmac_hash('banner-click|' . (string) \$bannerId . '|' . sr_banner_click_subject(), \$config)") === false
    || strpos($bannerHelper, 'INSERT IGNORE INTO sr_banner_clicks') === false
    || strpos($bannerHelper, 'SET click_count = click_count + 1') === false
) {
    sr_retention_check_error($errors, 'Banner click dedupe hash and counter contract changed unexpectedly.');
}

$bannerCopyAction = file_get_contents($root . '/modules/banner/actions/admin-banner-copy.php');
if (!is_string($bannerCopyAction)) {
    sr_retention_check_error($errors, 'Banner copy action cannot be read.');
} elseif (
    strpos($bannerCopyAction, "(\$_POST['copy_click_count'] ?? '') === '1'") === false
    || strpos($bannerCopyAction, "'click_count' => \$copyClickCount ?") === false
    || strpos($bannerCopyAction, "'copy_click_count' => \$copyClickCount") === false
    || preg_match('/INSERT\s+(?:IGNORE\s+)?INTO\s+sr_banner_clicks/is', $bannerCopyAction) === 1
    || strpos($bannerCopyAction, 'copy_clicks') !== false
) {
    sr_retention_check_error($errors, 'Banner copy must not duplicate click dedupe hashes.');
}

$bannerAdminView = file_get_contents($root . '/modules/banner/views/admin-banners.php');
if (!is_string($bannerAdminView)) {
    sr_retention_check_error($errors, 'Banner admin view cannot be read.');
} elseif (
    strpos($bannerAdminView, "'copy_click_count'") === false
    || strpos($bannerAdminView, '집계 클릭 수만 복사') === false
    || strpos($bannerAdminView, 'copy_clicks') !== false
    || strpos($bannerAdminView, '클릭 수와 클릭 로그를 함께 복사') !== false
) {
    sr_retention_check_error($errors, 'Banner copy UI must only offer aggregate click count copy.');
}

$cleanupOrder = sr_admin_retention_cleanup_target_keys();
$notificationsPosition = array_search('notifications', $cleanupOrder, true);
foreach (['notification_deliveries', 'notification_reads'] as $key) {
    $position = array_search($key, $cleanupOrder, true);
    if (!is_int($position) || !is_int($notificationsPosition) || $position > $notificationsPosition) {
        sr_retention_check_error($errors, 'Retention notification cleanup order is unsafe: ' . $key);
    }
}

$adminNotificationsPosition = array_search('admin_notifications', $cleanupOrder, true);
foreach (['admin_notification_reads'] as $key) {
    $position = array_search($key, $cleanupOrder, true);
    if (!is_int($position) || !is_int($adminNotificationsPosition) || $position > $adminNotificationsPosition) {
        sr_retention_check_error($errors, 'Retention admin notification cleanup order is unsafe: ' . $key);
    }
}

$params = sr_admin_retention_query_params(
    [
        'revoked_cutoff' => 'sessions',
        'expired_cutoff' => 'sessions',
    ],
    [
        'sessions' => '2026-01-01 00:00:00',
    ]
);
if ($params !== ['revoked_cutoff' => '2026-01-01 00:00:00', 'expired_cutoff' => '2026-01-01 00:00:00']) {
    sr_retention_check_error($errors, 'Retention query params mapping failed.');
}

$retentionHelper = file_get_contents($root . '/modules/admin/helpers/retention.php');
if (!is_string($retentionHelper)) {
    sr_retention_check_error($errors, 'Retention helper cannot be read.');
} elseif (
    strpos($retentionHelper, 'sr_admin_post_int_in_range($key, 1, 3650, 5) ?? 0') === false
    || strpos($retentionHelper, "(int) sr_post_string('auth_logs_days', 5)") !== false
) {
    sr_retention_check_error($errors, 'Retention post values must reject malformed or out-of-range raw day inputs.');
}

if ($errors !== []) {
    fwrite(STDERR, "retention target checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "retention target checks completed.\n";
