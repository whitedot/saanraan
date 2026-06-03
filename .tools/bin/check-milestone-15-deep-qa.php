#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));
chdir(SR_ROOT);

require_once SR_ROOT . '/core/helpers.php';

foreach ([
    'member',
    'admin',
    'privacy',
    'notification',
    'point',
    'reward',
    'deposit',
] as $moduleKey) {
    $helper = SR_ROOT . '/modules/' . $moduleKey . '/helpers.php';
    if (is_file($helper)) {
        require_once $helper;
    }
}

$errors = [];
$checks = [];

function m15_deep_ok(string $label): void
{
    global $checks;
    $checks[] = $label;
    echo '[ok] ' . $label . "\n";
}

function m15_deep_fail(string $label, string $message): void
{
    global $errors;
    $errors[] = $label . ': ' . $message;
    fwrite(STDERR, '[fail] ' . $label . ': ' . $message . "\n");
}

function m15_deep_assert(bool $condition, string $label, string $message = 'assertion failed'): void
{
    if ($condition) {
        m15_deep_ok($label);
        return;
    }

    m15_deep_fail($label, $message);
}

function m15_deep_one(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function m15_deep_value(PDO $pdo, string $sql, array $params = []): mixed
{
    $row = m15_deep_one($pdo, $sql, $params);
    if ($row === null) {
        return null;
    }

    return reset($row);
}

function m15_deep_exec(PDO $pdo, string $sql, array $params = []): void
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function m15_deep_account(PDO $pdo, array $config): int
{
    return sr_member_create_account($pdo, $config, [
        'email' => 'm15-deep@example.test',
        'login_id' => 'm15_deep',
        'password' => 'SaanraanM15!',
        'display_name' => 'M15깊은검사',
        'locale' => 'ko',
        'status' => 'active',
        'email_verified_at' => sr_now(),
        'allow_existing_update' => true,
    ]);
}

function m15_deep_owner_id(PDO $pdo): int
{
    return (int) m15_deep_value(
        $pdo,
        "SELECT account_id FROM sr_admin_account_roles WHERE role_key = 'owner' ORDER BY id ASC LIMIT 1"
    );
}

try {
    $config = sr_load_config();
    sr_set_runtime_config($config);
    sr_apply_runtime_config($config);
    $pdo = sr_db($config);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $accountId = m15_deep_account($pdo, $config);
    $ownerId = m15_deep_owner_id($pdo);
    $now = sr_now();
    $fixtureKey = 'm15_deep';

    m15_deep_assert($accountId > 0, '#174 idempotent fixture account');
    m15_deep_assert($ownerId > 0, '#174 owner account fixture');

    m15_deep_exec(
        $pdo,
        'INSERT INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at)
         VALUES (:account_id, :menu_path, :action_key, :created_at)
         ON DUPLICATE KEY UPDATE created_at = VALUES(created_at)',
        [
            'account_id' => $accountId,
            'menu_path' => '/admin/community/reports',
            'action_key' => 'view',
            'created_at' => $now,
        ]
    );
    m15_deep_assert(
        sr_admin_has_permission($pdo, $accountId, '/admin/community/reports', 'view')
        && !sr_admin_has_permission($pdo, $accountId, '/admin/community/reports', 'edit'),
        '#174 DB helper permission assertion'
    );

    $pointBefore = sr_point_balance($pdo, $accountId);
    $pointTxId = sr_point_create_transaction($pdo, [
        'account_id' => $accountId,
        'amount' => 37,
        'transaction_type' => 'adjustment',
        'reason' => 'M15 deep QA point fixture',
        'reference_type' => 'milestone_15',
        'reference_id' => $fixtureKey . ':point:' . (string) time(),
        'created_by_account_id' => $ownerId,
    ]);
    m15_deep_assert($pointTxId > 0 && sr_point_balance($pdo, $accountId) === $pointBefore + 37, '#174 DB helper point balance assertion');

    $rewardBefore = sr_reward_balance($pdo, $accountId);
    $rewardTxId = sr_reward_create_transaction($pdo, [
        'account_id' => $accountId,
        'amount' => 41,
        'transaction_type' => 'adjustment',
        'reason' => 'M15 deep QA reward fixture',
        'reference_type' => 'milestone_15',
        'reference_id' => $fixtureKey . ':reward:' . (string) time(),
        'created_by_account_id' => $ownerId,
    ]);
    m15_deep_assert($rewardTxId > 0 && sr_reward_balance($pdo, $accountId) === $rewardBefore + 41, '#174 DB helper reward balance assertion');

    $depositBefore = sr_deposit_balance($pdo, $accountId);
    $depositTxId = sr_deposit_create_transaction($pdo, [
        'account_id' => $accountId,
        'amount' => 43,
        'transaction_type' => 'deposit',
        'reason' => 'M15 deep QA deposit fixture',
        'reference_type' => 'milestone_15',
        'reference_id' => $fixtureKey . ':deposit:' . (string) time(),
        'created_by_account_id' => $ownerId,
    ]);
    m15_deep_assert($depositTxId > 0 && sr_deposit_balance($pdo, $accountId) === $depositBefore + 43, '#174 DB helper deposit balance assertion');

    $notificationTitle = 'M15 deep QA notification';
    m15_deep_exec(
        $pdo,
        'INSERT INTO sr_notifications
            (account_id, audience, title, body_text, body_format, link_url, status, read_at, created_by_account_id, created_at, updated_at)
         VALUES
            (:account_id, :audience, :title, :body_text, :body_format, :link_url, :status, NULL, :created_by_account_id, :created_at, :updated_at)',
        [
            'account_id' => $accountId,
            'audience' => 'account',
            'title' => $notificationTitle,
            'body_text' => 'Milestone 15 deep QA notification fixture.',
            'body_format' => 'plain',
            'link_url' => '/account/notifications',
            'status' => 'active',
            'created_by_account_id' => $ownerId,
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    $notificationId = (int) $pdo->lastInsertId();
    m15_deep_exec(
        $pdo,
        'INSERT INTO sr_notification_deliveries
            (notification_id, channel, recipient, status, provider_message_id, error_message, attempted_at, created_at, updated_at)
         VALUES
            (:notification_id, :channel, :recipient, :status, :provider_message_id, :error_message, NULL, :created_at, :updated_at)',
        [
            'notification_id' => $notificationId,
            'channel' => 'site',
            'recipient' => 'account:' . (string) $accountId,
            'status' => 'queued',
            'provider_message_id' => '',
            'error_message' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    m15_deep_assert(
        (int) m15_deep_value($pdo, 'SELECT COUNT(*) FROM sr_notifications WHERE account_id = :account_id AND title = :title', ['account_id' => $accountId, 'title' => $notificationTitle]) > 0
        && (int) m15_deep_value($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :notification_id', ['notification_id' => $notificationId]) === 1,
        '#174 DB helper notification assertion'
    );

    m15_deep_exec(
        $pdo,
        'INSERT INTO sr_privacy_requests
            (account_id, request_type, status, requester_email_hash, requester_snapshot, request_message, admin_note, handled_by_account_id, handled_at, created_at, updated_at)
         VALUES
            (:account_id, :request_type, :status, :requester_email_hash, :requester_snapshot, :request_message, :admin_note, NULL, NULL, :created_at, :updated_at)',
        [
            'account_id' => $accountId,
            'request_type' => 'access',
            'status' => 'requested',
            'requester_email_hash' => sr_hmac_hash('m15-deep@example.test', $config),
            'requester_snapshot' => 'm15-deep@example.test',
            'request_message' => 'Milestone 15 deep QA privacy request fixture.',
            'admin_note' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    $privacyRequestId = (int) $pdo->lastInsertId();
    m15_deep_assert(
        $privacyRequestId > 0
        && (string) m15_deep_value($pdo, 'SELECT status FROM sr_privacy_requests WHERE id = :id', ['id' => $privacyRequestId]) === 'requested',
        '#174 DB helper privacy request assertion'
    );

    sr_audit_log($pdo, [
        'actor_account_id' => $ownerId,
        'actor_type' => 'admin',
        'event_type' => 'm15.deep_qa',
        'target_type' => 'milestone',
        'target_id' => '15',
        'result' => 'success',
        'message' => 'Milestone 15 deep QA audit assertion.',
        'metadata' => ['fixture' => $fixtureKey, 'account_id' => $accountId],
    ]);
    m15_deep_assert(
        (int) m15_deep_value($pdo, "SELECT COUNT(*) FROM sr_audit_logs WHERE event_type = 'm15.deep_qa' AND target_id = '15'") > 0,
        '#174 DB helper audit log assertion'
    );

    $rateSubject = 'm15-deep-' . (string) $accountId;
    $rateBefore = sr_rate_limit_count($pdo, 'm15_deep_qa', $rateSubject, 300);
    sr_rate_limit_increment($pdo, 'm15_deep_qa', $rateSubject, 300);
    sr_rate_limit_increment($pdo, 'm15_deep_qa', $rateSubject, 300);
    $rateAfter = sr_rate_limit_count($pdo, 'm15_deep_qa', $rateSubject, 300);
    m15_deep_assert($rateAfter >= $rateBefore + 2, '#174 concurrency/rate-limit helper assertion');

    $permissionRows = (int) m15_deep_value($pdo, 'SELECT COUNT(*) FROM sr_admin_account_permissions WHERE account_id = :account_id AND menu_path = :menu_path AND action_key = :action_key', [
        'account_id' => $accountId,
        'menu_path' => '/admin/community/reports',
        'action_key' => 'view',
    ]);
    m15_deep_assert($permissionRows === 1, '#174 idempotent fixture catalog assertion');
} catch (Throwable $exception) {
    m15_deep_fail('milestone 15 deep QA', $exception->getMessage());
}

if ($errors !== []) {
    fwrite(STDERR, "\nMilestone 15 deep QA failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "\nMilestone 15 deep QA passed: " . (string) count($checks) . " checks.\n";
