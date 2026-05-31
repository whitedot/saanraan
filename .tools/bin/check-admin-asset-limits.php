#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

$assets = [
    'point' => [
        'helper' => 'modules/point/helpers.php',
        'action' => 'modules/point/actions/admin-points.php',
        'function' => 'sr_point_validate_admin_adjustment_limit',
        'table' => 'sr_point_transactions',
        'version' => '2026.05.005',
    ],
    'reward' => [
        'helper' => 'modules/reward/helpers.php',
        'action' => 'modules/reward/actions/admin-rewards.php',
        'function' => 'sr_reward_validate_admin_adjustment_limit',
        'table' => 'sr_reward_transactions',
        'version' => '2026.05.005',
    ],
    'deposit' => [
        'helper' => 'modules/deposit/helpers.php',
        'action' => 'modules/deposit/actions/admin-deposits.php',
        'function' => 'sr_deposit_validate_admin_adjustment_limit',
        'table' => 'sr_deposit_transactions',
        'version' => '2026.05.005',
    ],
];

foreach ($assets as $moduleKey => $asset) {
    $helper = file_get_contents($root . '/' . $asset['helper']);
    $action = file_get_contents($root . '/' . $asset['action']);
    $module = file_get_contents($root . '/modules/' . $moduleKey . '/module.php');
    $update = file_get_contents($root . '/modules/' . $moduleKey . '/updates/' . $asset['version'] . '.sql');

    if (
        !is_string($helper)
        || strpos($helper, 'function ' . $asset['function'] . '(PDO $pdo, array $runtimeConfig, int $adminAccountId, string $permissionPath, int $amount') === false
        || strpos($helper, $asset['table']) === false
        || strpos($helper, 'created_by_account_id = :admin_account_id') === false
        || strpos($helper, "created_at >= :started_at") === false
        || strpos($helper, 'approval_threshold') === false
        || strpos($helper, "SELECT status FROM sr_member_accounts WHERE id = :id LIMIT 1") === false
        || strpos($helper, 'sr_admin_has_permission($pdo, $approvalAccountId, $permissionPath, \'edit\')') === false
    ) {
        $errors[] = $moduleKey . ' helper must enforce one-time, daily, and dual-approval admin adjustment policy.';
    }

    if (
        !is_string($action)
        || strpos($action, $asset['function'] . '($pdo, $runtimeConfig, (int) $account[\'id\']') === false
        || strpos($action, "sr_post_string('approval_account_identifier', 80)") === false
        || strpos($action, "'approval_account_id' => \$approvalAccountId") === false
    ) {
        $errors[] = $moduleKey . ' admin action must call the server-side adjustment approval validator before saving.';
    }

    $viewPath = $root . '/modules/' . $moduleKey . '/views/admin-' . ($moduleKey === 'point' ? 'points' : $moduleKey . 's') . '.php';
    $view = file_get_contents($viewPath);
    if (!is_string($view) || strpos($view, 'name="approval_account_identifier"') === false || strpos($view, 'name="approval_note"') === false) {
        $errors[] = $moduleKey . ' admin view must expose dual-approval fields for large adjustments.';
    }

    if (!is_string($module) || strpos($module, "'version' => '" . $asset['version'] . "'") === false) {
        $errors[] = $moduleKey . ' module version must be bumped for admin adjustment limits.';
    }

    if (!is_string($update) || strpos($update, "WHERE module_key = '" . $moduleKey . "'") === false) {
        $errors[] = $moduleKey . ' update SQL must bump the stored module version.';
    }
}

if ($errors !== []) {
    fwrite(STDERR, "admin asset limit checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "admin asset limit checks completed.\n";
