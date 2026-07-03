#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
define('SR_ROOT', $root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/admin/helpers/operational-status.php';
require_once $root . '/modules/point/helpers.php';

$errors = [];

function sr_operational_status_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_operational_status_fixture_check(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE sr_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, module_key TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec("INSERT INTO sr_modules (module_key, status) VALUES ('fixture_mod', 'enabled')");
    $pdo->exec('CREATE TABLE sr_fixture_operation_queue (id INTEGER PRIMARY KEY AUTOINCREMENT, status TEXT NOT NULL, updated_at TEXT NOT NULL)');

    $baseCheck = [
        'label' => 'fixture.queue.pending',
        'title' => 'Fixture queue pending',
        'module' => 'fixture_mod',
        'table' => 'sr_fixture_operation_queue',
        'where' => "status = 'pending'",
        'age_column' => 'updated_at',
        'delay_tolerance' => '1시간',
        'warn_after_seconds' => 3600,
        'target_sql' => "SELECT status AS target_label, id AS target_fallback FROM sr_fixture_operation_queue WHERE status = 'pending' ORDER BY updated_at ASC, id ASC LIMIT 5",
        'followup' => 'fixture followup',
    ];

    $row = sr_admin_operational_status_row($pdo, $baseCheck);
    if ((string) ($row['status'] ?? '') !== 'ok' || (int) ($row['count'] ?? -1) !== 0) {
        sr_operational_status_error('Fixture empty queue should be ok with count 0.');
    }

    $recentAt = date('Y-m-d H:i:s', time() - 300);
    $stmt = $pdo->prepare('INSERT INTO sr_fixture_operation_queue (status, updated_at) VALUES (:status, :updated_at)');
    $stmt->execute(['status' => 'pending', 'updated_at' => $recentAt]);
    $row = sr_admin_operational_status_row($pdo, $baseCheck);
    if ((string) ($row['status'] ?? '') !== 'warning' || (int) ($row['count'] ?? 0) !== 1 || !is_int($row['age_seconds'] ?? null)) {
        sr_operational_status_error('Fixture recent pending queue should be warning with age_seconds.');
    }
    if (($row['targets'] ?? []) !== ['pending']) {
        sr_operational_status_error('Fixture recent pending queue should include target labels.');
    }

    $formattedCheck = $baseCheck;
    $formattedCheck['target_sql'] = "SELECT status, id AS target_fallback FROM sr_fixture_operation_queue WHERE status = 'pending' ORDER BY updated_at ASC, id ASC LIMIT 5";
    $formattedCheck['target_format'] = '상태 {status}';
    $row = sr_admin_operational_status_row($pdo, $formattedCheck);
    if (($row['targets'] ?? []) !== ['상태 pending']) {
        sr_operational_status_error('Fixture formatted target labels should be built from selected columns.');
    }

    $oldAt = date('Y-m-d H:i:s', time() - 7200);
    $stmt->execute(['status' => 'pending', 'updated_at' => $oldAt]);
    $row = sr_admin_operational_status_row($pdo, $baseCheck);
    if ((string) ($row['status'] ?? '') !== 'overdue' || (int) ($row['count'] ?? 0) !== 2 || (string) ($row['oldest_at'] ?? '') !== $oldAt) {
        sr_operational_status_error('Fixture old pending queue should be overdue and use oldest timestamp.');
    }

    $stmt->execute(['status' => 'failed', 'updated_at' => date('Y-m-d H:i:s')]);
    $failedCheck = $baseCheck;
    $failedCheck['label'] = 'fixture.queue.failed';
    $failedCheck['where'] = "status = 'failed'";
    $failedCheck['delay_tolerance'] = '즉시';
    $failedCheck['warn_after_seconds'] = 0;
    $row = sr_admin_operational_status_row($pdo, $failedCheck);
    if ((string) ($row['status'] ?? '') !== 'overdue') {
        sr_operational_status_error('Fixture immediate failure signal should be overdue.');
    }

    $disabledCheck = $baseCheck;
    $disabledCheck['module'] = 'disabled_mod';
    $row = sr_admin_operational_status_row($pdo, $disabledCheck);
    if ((string) ($row['status'] ?? '') !== 'skipped') {
        sr_operational_status_error('Fixture disabled module should be skipped.');
    }

    $unsafeCheck = $baseCheck;
    $unsafeCheck['table'] = 'sr_fixture_operation_queue;DROP';
    $row = sr_admin_operational_status_row($pdo, $unsafeCheck);
    if ((string) ($row['status'] ?? '') !== 'error') {
        sr_operational_status_error('Fixture unsafe table identifier should be an error.');
    }

    $unsafeAgeColumnCheck = $baseCheck;
    $unsafeAgeColumnCheck['age_column'] = 'updated_at;DROP';
    $row = sr_admin_operational_status_row($pdo, $unsafeAgeColumnCheck);
    if ((string) ($row['status'] ?? '') !== 'error') {
        sr_operational_status_error('Fixture unsafe age column identifier should be an error.');
    }

    $unsafeWhereCheck = $baseCheck;
    $unsafeWhereCheck['where'] = "status = 'pending'; DROP TABLE sr_modules";
    $row = sr_admin_operational_status_row($pdo, $unsafeWhereCheck);
    if ((string) ($row['status'] ?? '') !== 'error') {
        sr_operational_status_error('Fixture unsafe where predicate should be an error.');
    }

    $unsafeTargetCheck = $baseCheck;
    $unsafeTargetCheck['target_sql'] = 'UPDATE sr_fixture_operation_queue SET status = status';
    $row = sr_admin_operational_status_row($pdo, $unsafeTargetCheck);
    if (($row['targets'] ?? []) !== []) {
        sr_operational_status_error('Fixture unsafe target SQL should not return target labels.');
    }

    $commentWhereCheck = $baseCheck;
    $commentWhereCheck['where'] = "status = 'pending' -- hide";
    $row = sr_admin_operational_status_row($pdo, $commentWhereCheck);
    if ((string) ($row['status'] ?? '') !== 'error') {
        sr_operational_status_error('Fixture commented where predicate should be an error.');
    }

    $missingCheck = $baseCheck;
    $missingCheck['table'] = 'sr_fixture_missing_queue';
    $row = sr_admin_operational_status_row($pdo, $missingCheck);
    if ((string) ($row['status'] ?? '') !== 'skipped') {
        sr_operational_status_error('Fixture missing optional table should be skipped.');
    }

    $summary = sr_admin_operational_status_summary([
        ['status' => 'ok', 'count' => 0],
        ['status' => 'warning', 'count' => 1],
        ['status' => 'overdue', 'count' => 2],
        ['status' => 'skipped', 'count' => 0],
        ['status' => 'error', 'count' => 0],
    ]);
    if ((int) ($summary['ok'] ?? 0) !== 1 || (int) ($summary['warning'] ?? 0) !== 1 || (int) ($summary['overdue'] ?? 0) !== 1 || (int) ($summary['skipped'] ?? 0) !== 1 || (int) ($summary['error'] ?? 0) !== 1 || (int) ($summary['total_count'] ?? 0) !== 3) {
        sr_operational_status_error('Fixture operational summary totals mismatch.');
    }

    $line = sr_admin_operational_status_cli_row_line([
        'label' => 'fixture.queue.pending',
        'status' => 'warning',
        'count' => 3,
        'delay_tolerance' => "  1시간\n",
        'oldest_at' => '',
    ]);
    if ($line !== "fixture.queue.pending\tstatus=warning\tcount=3\tallowed_delay=1시간\toldest_at=-") {
        sr_operational_status_error('Fixture operational CLI row line should normalize values.');
    }

    $skippedLine = sr_admin_operational_status_cli_row_line([
        'label' => 'fixture.queue.skipped',
        'status' => 'skipped',
        'message' => "module disabled\nwith detail\tand spacing",
    ]);
    if ($skippedLine !== "fixture.queue.skipped\tskipped\tmodule disabled with detail and spacing") {
        sr_operational_status_error('Fixture operational CLI skipped line should stay single-line.');
    }

    $summaryLine = sr_admin_operational_status_cli_summary_line($summary);
    if ($summaryLine !== "summary\tok=1\twarning=1\toverdue=1\tskipped=1\terror=1\ttotal_count=3") {
        sr_operational_status_error('Fixture operational CLI summary line mismatch.');
    }
}

function sr_operational_status_bundle_signal_fixture_check(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE sr_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, module_key TEXT NOT NULL, status TEXT NOT NULL)');
    foreach (['policy_documents', 'asset_ledger', 'notification', 'community', 'content', 'point'] as $moduleKey) {
        $stmt = $pdo->prepare('INSERT INTO sr_modules (module_key, status) VALUES (:module_key, :status)');
        $stmt->execute(['module_key' => $moduleKey, 'status' => 'enabled']);
    }
    $pdo->exec('CREATE TABLE sr_policy_documents (id INTEGER PRIMARY KEY AUTOINCREMENT, document_key TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_policy_document_versions (id INTEGER PRIMARY KEY AUTOINCREMENT, document_id INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE sr_policy_document_mail_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, document_id INTEGER NOT NULL, version_id INTEGER NOT NULL, job_key TEXT NOT NULL, status TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_asset_recovery_failures (id INTEGER PRIMARY KEY AUTOINCREMENT, source_module TEXT NOT NULL, subject_type TEXT NOT NULL, subject_id INTEGER NOT NULL, account_id INTEGER NOT NULL, status TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_asset_recovery_failures (id INTEGER PRIMARY KEY AUTOINCREMENT, asset_module TEXT NOT NULL, subject_type TEXT NOT NULL, subject_id INTEGER NOT NULL, account_id INTEGER NOT NULL, status TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_notification_deliveries (id INTEGER PRIMARY KEY AUTOINCREMENT, status TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_board_copy_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, status TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_level_recalculate_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, requested_by INTEGER NOT NULL, processed_total INTEGER NOT NULL, total_count INTEGER NOT NULL, status TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_publisher_reward_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER NOT NULL, attachment_id INTEGER NOT NULL, publisher_account_id INTEGER NOT NULL, status TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_content_author_reward_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, submission_id INTEGER NOT NULL, content_id INTEGER NOT NULL, author_account_id INTEGER NOT NULL, status TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_point_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, expires_at TEXT NULL, expires_remaining INTEGER NOT NULL DEFAULT 0)');

    $oldAt = date('Y-m-d H:i:s', time() - 7200);
    $recentAt = date('Y-m-d H:i:s', time() - 300);
    $dueAt = date('Y-m-d H:i:s', time() - 90000);
    $futureAt = date('Y-m-d H:i:s', time() + 90000);

    $pdo->exec("INSERT INTO sr_policy_documents (id, document_key) VALUES (1, 'member_terms')");
    $pdo->exec('INSERT INTO sr_policy_document_versions (id, document_id) VALUES (1, 1)');
    $stmt = $pdo->prepare('INSERT INTO sr_policy_document_mail_jobs (document_id, version_id, job_key, status, updated_at) VALUES (:document_id, :version_id, :job_key, :status, :updated_at)');
    $stmt->execute(['document_id' => 1, 'version_id' => 1, 'job_key' => 'member_terms:queued', 'status' => 'queued', 'updated_at' => $oldAt]);
    $stmt->execute(['document_id' => 1, 'version_id' => 1, 'job_key' => 'member_terms:failed', 'status' => 'failed', 'updated_at' => $recentAt]);

    $stmt = $pdo->prepare('INSERT INTO sr_asset_recovery_failures (source_module, subject_type, subject_id, account_id, status, updated_at) VALUES (:source_module, :subject_type, :subject_id, :account_id, :status, :updated_at)');
    $stmt->execute(['source_module' => 'community', 'subject_type' => 'post', 'subject_id' => 10, 'account_id' => 7, 'status' => 'open', 'updated_at' => $recentAt]);

    $stmt = $pdo->prepare('INSERT INTO sr_community_asset_recovery_failures (asset_module, subject_type, subject_id, account_id, status, updated_at) VALUES (:asset_module, :subject_type, :subject_id, :account_id, :status, :updated_at)');
    $stmt->execute(['asset_module' => 'point', 'subject_type' => 'community.comment', 'subject_id' => 11, 'account_id' => 8, 'status' => 'open', 'updated_at' => $recentAt]);

    $stmt = $pdo->prepare('INSERT INTO sr_notification_deliveries (status, created_at, updated_at) VALUES (:status, :created_at, :updated_at)');
    $stmt->execute(['status' => 'queued', 'created_at' => $oldAt, 'updated_at' => $oldAt]);
    $stmt->execute(['status' => 'failed', 'created_at' => $recentAt, 'updated_at' => $recentAt]);

    $stmt = $pdo->prepare('INSERT INTO sr_community_board_copy_jobs (status, updated_at) VALUES (:status, :updated_at)');
    $stmt->execute(['status' => 'running', 'updated_at' => $oldAt]);
    $stmt->execute(['status' => 'cancelled', 'updated_at' => $recentAt]);

    $stmt = $pdo->prepare('INSERT INTO sr_community_level_recalculate_jobs (requested_by, processed_total, total_count, status, updated_at) VALUES (:requested_by, :processed_total, :total_count, :status, :updated_at)');
    $stmt->execute(['requested_by' => 901, 'processed_total' => 25, 'total_count' => 100, 'status' => 'running', 'updated_at' => $oldAt]);
    $stmt->execute(['requested_by' => 902, 'processed_total' => 30, 'total_count' => 120, 'status' => 'failed', 'updated_at' => $recentAt]);

    $stmt = $pdo->prepare('INSERT INTO sr_community_publisher_reward_logs (post_id, attachment_id, publisher_account_id, status, updated_at) VALUES (:post_id, :attachment_id, :publisher_account_id, :status, :updated_at)');
    $stmt->execute(['post_id' => 101, 'attachment_id' => 201, 'publisher_account_id' => 301, 'status' => 'pending', 'updated_at' => $oldAt]);
    $stmt->execute(['post_id' => 102, 'attachment_id' => 202, 'publisher_account_id' => 302, 'status' => 'failed', 'updated_at' => $recentAt]);

    $stmt = $pdo->prepare('INSERT INTO sr_content_author_reward_logs (submission_id, content_id, author_account_id, status, updated_at) VALUES (:submission_id, :content_id, :author_account_id, :status, :updated_at)');
    $stmt->execute(['submission_id' => 401, 'content_id' => 501, 'author_account_id' => 601, 'status' => 'pending', 'updated_at' => $oldAt]);
    $stmt->execute(['submission_id' => 402, 'content_id' => 502, 'author_account_id' => 602, 'status' => 'failed', 'updated_at' => $recentAt]);

    $stmt = $pdo->prepare('INSERT INTO sr_point_transactions (expires_at, expires_remaining) VALUES (:expires_at, :expires_remaining)');
    $stmt->execute(['expires_at' => $dueAt, 'expires_remaining' => 10]);
    $stmt->execute(['expires_at' => $futureAt, 'expires_remaining' => 20]);
    $stmt->execute(['expires_at' => $dueAt, 'expires_remaining' => 0]);

    $rows = sr_admin_operational_status_rows($pdo);
    $byLabel = [];
    foreach ($rows as $row) {
        $byLabel[(string) ($row['label'] ?? '')] = $row;
    }

    if ((string) ($byLabel['notification.deliveries.queued']['status'] ?? '') !== 'overdue') {
        sr_operational_status_error('Bundle fixture queued notifications should be overdue.');
    }
    if ((string) ($byLabel['policy_documents.mail_jobs.queued']['status'] ?? '') !== 'overdue') {
        sr_operational_status_error('Bundle fixture queued policy document mail job should be overdue.');
    }
    if (($byLabel['policy_documents.mail_jobs.queued']['targets'] ?? []) !== ['member_terms']) {
        sr_operational_status_error('Bundle fixture queued policy document mail job should hide internal version keys from operator targets.');
    }
    if ((string) ($byLabel['policy_documents.mail_jobs.failed']['status'] ?? '') !== 'overdue') {
        sr_operational_status_error('Bundle fixture failed policy document mail job should be overdue immediately.');
    }
    if ((string) ($byLabel['asset_recovery.open']['status'] ?? '') !== 'overdue') {
        sr_operational_status_error('Bundle fixture open asset recovery should be overdue immediately.');
    }
    if (($byLabel['asset_recovery.open']['targets'] ?? []) !== ['community post#10 / 계정 #7']) {
        sr_operational_status_error('Bundle fixture open asset recovery should include source, subject, and account target.');
    }
    if ((string) ($byLabel['community.asset_recovery_legacy.open']['status'] ?? '') !== 'overdue') {
        sr_operational_status_error('Bundle fixture open legacy community asset recovery should be overdue immediately.');
    }
    if (($byLabel['community.asset_recovery_legacy.open']['targets'] ?? []) !== ['point community.comment#11 / 계정 #8']) {
        sr_operational_status_error('Bundle fixture open legacy community asset recovery should include asset module, subject, and account target.');
    }
    if ((int) ($byLabel['notification.deliveries.queued']['count'] ?? 0) !== 1) {
        sr_operational_status_error('Bundle fixture queued notifications count mismatch.');
    }
    if ((string) ($byLabel['notification.deliveries.failed']['status'] ?? '') !== 'overdue') {
        sr_operational_status_error('Bundle fixture failed notifications should be overdue immediately.');
    }
    if ((string) ($byLabel['community.board_copy.active']['status'] ?? '') !== 'overdue') {
        sr_operational_status_error('Bundle fixture active board copy job should be overdue.');
    }
    if ((string) ($byLabel['community.board_copy.failed']['status'] ?? '') !== 'overdue') {
        sr_operational_status_error('Bundle fixture failed/cancelled board copy job should be overdue immediately.');
    }
    if ((string) ($byLabel['community.level_recalculate.running']['status'] ?? '') !== 'overdue') {
        sr_operational_status_error('Bundle fixture running community level recalculate job should be overdue.');
    }
    if (($byLabel['community.level_recalculate.running']['targets'] ?? []) !== ['작업 #1 / 요청자 #901 / 25/100']) {
        sr_operational_status_error('Bundle fixture running community level recalculate job should include job, requester, and progress target.');
    }
    if ((string) ($byLabel['community.level_recalculate.failed']['status'] ?? '') !== 'overdue') {
        sr_operational_status_error('Bundle fixture failed community level recalculate job should be overdue immediately.');
    }
    if ((string) ($byLabel['community.publisher_rewards.pending']['status'] ?? '') !== 'overdue') {
        sr_operational_status_error('Bundle fixture pending publisher reward should be overdue.');
    }
    if (($byLabel['community.publisher_rewards.pending']['targets'] ?? []) !== ['게시글 #101 / 첨부 #201 / 게시자 #301']) {
        sr_operational_status_error('Bundle fixture pending publisher reward should include post, attachment, and publisher target.');
    }
    if ((string) ($byLabel['community.publisher_rewards.failed']['status'] ?? '') !== 'overdue') {
        sr_operational_status_error('Bundle fixture failed publisher reward should be overdue immediately.');
    }
    if ((string) ($byLabel['content.author_rewards.pending']['status'] ?? '') !== 'overdue') {
        sr_operational_status_error('Bundle fixture pending content author reward should be overdue.');
    }
    if (($byLabel['content.author_rewards.pending']['targets'] ?? []) !== ['제출본 #401 / 콘텐츠 #501 / 작성자 #601']) {
        sr_operational_status_error('Bundle fixture pending content author reward should include submission, content, and author target.');
    }
    if ((string) ($byLabel['content.author_rewards.failed']['status'] ?? '') !== 'overdue') {
        sr_operational_status_error('Bundle fixture failed content author reward should be overdue immediately.');
    }
    if ((string) ($byLabel['point.expiration.due']['status'] ?? '') !== 'overdue' || (int) ($byLabel['point.expiration.due']['count'] ?? 0) !== 1) {
        sr_operational_status_error('Bundle fixture point expiration due signal should count only expired remaining points.');
    }

    $summary = sr_admin_operational_status_summary($rows);
    if ((int) ($summary['overdue'] ?? 0) < 15 || (int) ($summary['total_count'] ?? 0) !== 15) {
        sr_operational_status_error('Bundle fixture operational summary should include overdue signal counts.');
    }
}

function sr_operational_status_point_expiration_fixture_check(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE sr_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, module_key TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec("INSERT INTO sr_modules (module_key, status) VALUES ('point', 'enabled'), ('notification', 'disabled')");
    $pdo->exec(
        'CREATE TABLE sr_point_balances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL UNIQUE,
            balance INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_point_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            amount INTEGER NOT NULL,
            balance_after INTEGER NOT NULL,
            transaction_type TEXT NOT NULL,
            reason TEXT NOT NULL DEFAULT \'\',
            reference_type TEXT NOT NULL DEFAULT \'\',
            reference_id TEXT NOT NULL DEFAULT \'\',
            created_by_account_id INTEGER NULL,
            expires_at TEXT NULL,
            expires_remaining INTEGER NOT NULL DEFAULT 0,
            expired_at TEXT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_point_expiration_consumptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            consume_transaction_id INTEGER NOT NULL,
            source_transaction_id INTEGER NOT NULL,
            amount INTEGER NOT NULL,
            source_expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );

    $now = '2026-06-12 12:00:00';
    $dueTransactionId = sr_point_insert_ledger_transaction($pdo, [
        'account_id' => 1,
        'amount' => 100,
        'transaction_type' => 'grant',
        'reason' => 'fixture due grant',
        'expires_at' => '2026-06-11 12:00:00',
        'expires_remaining' => 80,
        'created_at' => '2026-06-01 12:00:00',
    ]);
    sr_point_insert_ledger_transaction($pdo, [
        'account_id' => 1,
        'amount' => 50,
        'transaction_type' => 'grant',
        'reason' => 'fixture future grant',
        'expires_at' => '2026-06-13 12:00:00',
        'expires_remaining' => 50,
        'created_at' => '2026-06-02 12:00:00',
    ]);
    sr_point_insert_ledger_transaction($pdo, [
        'account_id' => 2,
        'amount' => 30,
        'transaction_type' => 'grant',
        'reason' => 'fixture consumed grant',
        'expires_at' => '2026-06-11 12:00:00',
        'expires_remaining' => 0,
        'created_at' => '2026-06-01 12:00:00',
    ]);

    $preview = sr_point_expire_due_preview_transactions($pdo, 10, $now);
    if ((int) ($preview['due_count'] ?? 0) !== 1 || (int) ($preview['due_amount'] ?? 0) !== 80) {
        sr_operational_status_error('Point expiration preview fixture should count due remaining grants without expiring them.');
    }
    $expireRowsBefore = (int) $pdo->query("SELECT COUNT(*) FROM sr_point_transactions WHERE transaction_type = 'expire'")->fetchColumn();
    if ($expireRowsBefore !== 0) {
        sr_operational_status_error('Point expiration preview fixture should not create expire ledger transactions.');
    }

    $result = sr_point_expire_due_transactions($pdo, 10, $now);
    if ((int) ($result['expired_count'] ?? 0) !== 1 || (int) ($result['expired_amount'] ?? 0) !== 80) {
        sr_operational_status_error('Point expiration fixture should expire only due remaining grants.');
    }

    $balance = (int) $pdo->query('SELECT balance FROM sr_point_balances WHERE account_id = 1')->fetchColumn();
    if ($balance !== 70) {
        sr_operational_status_error('Point expiration fixture should subtract the expired remaining amount from balance.');
    }

    $source = $pdo->query('SELECT expires_remaining, expired_at FROM sr_point_transactions WHERE id = ' . (int) $dueTransactionId)->fetch(PDO::FETCH_ASSOC);
    if (!is_array($source) || (int) ($source['expires_remaining'] ?? -1) !== 0 || (string) ($source['expired_at'] ?? '') !== $now) {
        sr_operational_status_error('Point expiration fixture should close the source grant expiration fields.');
    }

    $expire = $pdo->query("SELECT amount, balance_after, transaction_type, reference_type, reference_id FROM sr_point_transactions WHERE transaction_type = 'expire'")->fetch(PDO::FETCH_ASSOC);
    if (
        !is_array($expire)
        || (int) ($expire['amount'] ?? 0) !== -80
        || (int) ($expire['balance_after'] ?? 0) !== 70
        || (string) ($expire['reference_type'] ?? '') !== 'point_expiration'
        || (string) ($expire['reference_id'] ?? '') !== 'point_transaction:' . (string) $dueTransactionId
    ) {
        sr_operational_status_error('Point expiration fixture should create a linked expire ledger transaction.');
    }

    $secondRun = sr_point_expire_due_transactions($pdo, 10, $now);
    if ((int) ($secondRun['expired_count'] ?? 0) !== 0 || (int) ($secondRun['expired_amount'] ?? 0) !== 0) {
        sr_operational_status_error('Point expiration fixture should be idempotent after source expiration is closed.');
    }
}

$docFile = 'docs/operational-status.md';
$toolFile = '.tools/bin/ops-status.php';
$expireToolFile = '.tools/bin/expire-points.php';
$notificationToolFile = '.tools/bin/run-notification-deliveries.php';
$helperFile = 'modules/admin/helpers/operational-status.php';
$pathsFile = 'modules/admin/paths.php';
$navigationFile = 'modules/admin/helpers/navigation.php';
$viewFile = 'modules/admin/views/operations.php';
$doc = is_file($docFile) ? file_get_contents($docFile) : false;
$tool = is_file($toolFile) ? file_get_contents($toolFile) : false;
$expireTool = is_file($expireToolFile) ? file_get_contents($expireToolFile) : false;
$notificationTool = is_file($notificationToolFile) ? file_get_contents($notificationToolFile) : false;
$helper = is_file($helperFile) ? file_get_contents($helperFile) : false;
$paths = is_file($pathsFile) ? file_get_contents($pathsFile) : false;
$navigation = is_file($navigationFile) ? file_get_contents($navigationFile) : false;
$view = is_file($viewFile) ? file_get_contents($viewFile) : false;
$operationalContractSource = '';
foreach (glob('modules/*/operational-status.php') ?: [] as $contractFile) {
    $contractContents = file_get_contents($contractFile);
    if (is_string($contractContents)) {
        $operationalContractSource .= "\n" . $contractContents;
    }
}

if (!is_string($doc)) {
    sr_operational_status_error('Operational status document is missing or unreadable.');
}
if (!is_string($tool)) {
    sr_operational_status_error('ops-status.php is missing or unreadable.');
}
if (!is_string($expireTool)) {
    sr_operational_status_error('expire-points.php is missing or unreadable.');
}
if (!is_string($notificationTool)) {
    sr_operational_status_error('run-notification-deliveries.php is missing or unreadable.');
}
if (!is_string($helper)) {
    sr_operational_status_error('Operational status helper is missing or unreadable.');
}
if (!is_string($view)) {
    sr_operational_status_error('Admin operational status view is missing or unreadable.');
}

$signals = [
    'policy_documents.mail_jobs.queued',
    'policy_documents.mail_jobs.failed',
    'asset_recovery.open',
    'community.asset_recovery_legacy.open',
    'community.publisher_rewards.pending',
    'community.publisher_rewards.failed',
    'content.author_rewards.pending',
    'content.author_rewards.failed',
    'community.level_recalculate.running',
    'community.level_recalculate.failed',
    'notification.deliveries.queued',
    'notification.deliveries.failed',
    'content.storage_cleanup.pending',
    'community.storage_cleanup.pending',
    'community.board_copy.active',
    'community.board_copy.failed',
    'quiz.reward_grants.pending',
    'quiz.reward_grants.failed',
    'survey.reward_grants.pending',
    'survey.reward_grants.failed',
    'point.expiration.due',
];

foreach ($signals as $signal) {
    if (is_string($doc) && !str_contains($doc, $signal)) {
        sr_operational_status_error('Operational status document is missing signal: ' . $signal);
    }
    if (!str_contains($operationalContractSource, $signal)) {
        sr_operational_status_error('Operational status contract is missing signal: ' . $signal);
    }
}

foreach ([
    'php .tools/bin/ops-status.php',
    'php .tools/bin/expire-points.php',
    'php .tools/bin/run-notification-deliveries.php --help',
    'Usage: php .tools/bin/ops-status.php [--help]',
    'Usage: php .tools/bin/expire-points.php [--dry-run] [limit]',
    'Usage: php .tools/bin/run-notification-deliveries.php [--help]',
    '자동 정리 실행 상태',
    '정책 문서 안내메일 기준',
    '/admin/operations',
    '/admin/assets/reconciliation',
    '/admin/retention',
    '/admin/community/board-copy-jobs',
    '자산 불일치 대응 절차',
    'balance row를 직접 수정하지 않는다',
    '환전 묶음 정정',
    'DB에서 balance row, 거래 row, `balance_after`를 직접 UPDATE',
    'read-only',
    '공유호스팅',
    '허용 지연',
    '지연 초과',
    '1시간',
    '15분',
    '24시간',
] as $marker) {
    if (is_string($doc) && !str_contains($doc, $marker)) {
        sr_operational_status_error('Operational status document is missing marker: ' . $marker);
    }
}

foreach ([
    'sr_is_installed()',
    '--help',
    'Usage: php .tools/bin/ops-status.php [--help]',
    'Unknown option:',
    'sr_admin_operational_status_rows($pdo)',
    'sr_admin_operational_status_cli_row_line($row)',
    'sr_admin_operational_status_cli_summary_line(sr_admin_operational_status_summary($rows))',
] as $marker) {
    if (is_string($tool) && !str_contains($tool, $marker)) {
        sr_operational_status_error('ops-status.php is missing marker: ' . $marker);
    }
}

foreach ([
    'sr_is_installed()',
    "require_once SR_ROOT . '/modules/point/helpers.php'",
    '--help',
    'Usage: php .tools/bin/expire-points.php [--dry-run] [limit]',
    '--dry-run',
    'sr_point_expire_due_preview_transactions($pdo, $limit)',
    'dry_run=yes',
    'due_count=',
    'due_amount=',
    'sr_module_enabled($pdo, \'point\')',
    'sr_point_expire_due_transactions($pdo, $limit)',
    'dry_run=no',
    'expired_count=',
    'expired_amount=',
    'Unknown option or invalid limit:',
] as $marker) {
    if (is_string($expireTool) && !str_contains($expireTool, $marker)) {
        sr_operational_status_error('expire-points.php is missing marker: ' . $marker);
    }
}

foreach ([
    'sr_is_installed()',
    '--help',
    'Usage: php .tools/bin/run-notification-deliveries.php [--help]',
    'Unknown option:',
    'sr_notification_run_delivery_batch($pdo',
    'claimed=',
] as $marker) {
    if (is_string($notificationTool) && !str_contains($notificationTool, $marker)) {
        sr_operational_status_error('run-notification-deliveries.php is missing marker: ' . $marker);
    }
}

foreach ([
    'sr_ledger_insert_ignore_into_clause($pdo)',
    'sr_ledger_for_update_clause($pdo)',
    'function sr_point_expire_due_transactions(PDO $pdo',
    'function sr_point_expire_due_preview_transactions(PDO $pdo',
    'function sr_point_expire_grant_transaction(PDO $pdo',
] as $marker) {
    $pointHelper = is_file('modules/point/helpers.php') ? file_get_contents('modules/point/helpers.php') : false;
    if (!is_string($pointHelper) || !str_contains($pointHelper, $marker)) {
        sr_operational_status_error('Point helper expiration marker missing: ' . $marker);
    }
}

foreach ([
    'function sr_admin_operational_status_checks(?PDO $pdo = null): array',
    'function sr_admin_operational_status_rows(PDO $pdo): array',
    "sr_enabled_module_contract_files(\$pdo, 'operational-status.php')",
    'sr_module_enabled($pdo',
    'delay_tolerance',
    'warn_after_seconds',
    'function sr_admin_operational_status_where_sql(PDO $pdo, string $where): string',
    "preg_replace('/\\bNOW\\(\\)/i', 'CURRENT_TIMESTAMP', \$where)",
    'function sr_admin_operational_status_age_seconds(string $value): ?int',
    'function sr_admin_operational_status_safe_where(string $value): bool',
    'function sr_admin_operational_status_format_target(array $row, string $format): string',
    'function sr_admin_operational_status_cli_row_line(array $row): string',
    'function sr_admin_operational_status_cli_summary_line(array $summary): string',
    "'overdue' => 0",
    'COUNT(*) AS item_count',
    'MIN(',
    'DROP|GRANT|INSERT|LOAD|OUTFILE|REPLACE|REVOKE|TRUNCATE|UPDATE',
] as $marker) {
    if (is_string($helper) && !str_contains($helper, $marker)) {
        sr_operational_status_error('Operational status helper is missing marker: ' . $marker);
    }
}

foreach ([
    'function sr_community_board_copy_job_assert_lock(PDO $pdo, int $jobId, string $lockToken): void',
    'function sr_community_board_copy_job_map_status_counts(PDO $pdo, int $jobId): array',
    'function sr_community_board_copy_job_failed_maps(PDO $pdo, int $jobId, int $limit = 10): array',
    '복사 작업 lock이 만료되었거나 다른 요청이 이어받았습니다.',
] as $marker) {
    $boardCopyJobs = is_file('modules/community/helpers/board-copy-jobs.php') ? file_get_contents('modules/community/helpers/board-copy-jobs.php') : false;
    if (!is_string($boardCopyJobs) || !str_contains($boardCopyJobs, $marker)) {
        sr_operational_status_error('Community board copy job lock marker missing: ' . $marker);
    }
}

foreach ([
    '단계별 처리 현황',
    '실패 항목',
    '실패 map을 다시 대기 상태로 돌립니다.',
] as $marker) {
    $boardCopyJobsView = is_file('modules/community/views/admin-board-copy-jobs.php') ? file_get_contents('modules/community/views/admin-board-copy-jobs.php') : false;
    if (!is_string($boardCopyJobsView) || !str_contains($boardCopyJobsView, $marker)) {
        sr_operational_status_error('Community board copy job view marker missing: ' . $marker);
    }
}

foreach ([
    'function sr_policy_document_requeue_failed_mail_deliveries(PDO $pdo, int $jobId): int',
    'function sr_policy_document_cancel_pending_mail_deliveries(PDO $pdo, int $jobId): int',
    'stale_claim_cutoff',
    'claimed_at = :claimed_at',
] as $marker) {
    $policyDocumentHelper = is_file('modules/policy_documents/helpers.php') ? file_get_contents('modules/policy_documents/helpers.php') : false;
    if (!is_string($policyDocumentHelper) || !str_contains($policyDocumentHelper, $marker)) {
        sr_operational_status_error('Policy document mail operation marker missing: ' . $marker);
    }
}

foreach ([
    'requeue_mail_failures',
    'cancel_mail_pending',
    '실패 재대기',
    '남은 발송 취소',
] as $marker) {
    $policyDocumentAdminSource = '';
    foreach (['modules/policy_documents/actions/admin-policy-documents.php', 'modules/policy_documents/views/admin-policy-documents.php'] as $policyDocumentAdminFile) {
        $contents = is_file($policyDocumentAdminFile) ? file_get_contents($policyDocumentAdminFile) : false;
        if (is_string($contents)) {
            $policyDocumentAdminSource .= "\n" . $contents;
        }
    }
    if (!str_contains($policyDocumentAdminSource, $marker)) {
        sr_operational_status_error('Policy document admin mail operation marker missing: ' . $marker);
    }
}

foreach ([
    'function sr_admin_retention_record_auto_cleanup_failure(PDO $pdo, string $autoScope, Throwable $exception): void',
    'function sr_admin_retention_auto_cleanup_runtime_status(PDO $pdo): array',
    '자동 정리 실행 상태',
    '마지막 실패',
] as $marker) {
    $retentionSource = '';
    foreach (['modules/admin/helpers/retention.php', 'modules/admin/views/retention.php'] as $retentionFile) {
        $contents = is_file($retentionFile) ? file_get_contents($retentionFile) : false;
        if (is_string($contents)) {
            $retentionSource .= "\n" . $contents;
        }
    }
    if (!str_contains($retentionSource, $marker)) {
        sr_operational_status_error('Retention auto cleanup failure marker missing: ' . $marker);
    }
}

foreach ([
    "'GET /admin/operations' => 'actions/operations.php'",
] as $marker) {
    if (is_string($paths) && !str_contains($paths, $marker)) {
        sr_operational_status_error('Admin paths are missing operational status route.');
    }
}

foreach ([
    "'path' => '/admin/operations'",
    '운영 지연/실패 점검',
] as $marker) {
    if (is_string($navigation) && !str_contains($navigation, $marker)) {
        sr_operational_status_error('Admin navigation is missing operational status marker: ' . $marker);
    }
}

foreach ([
    '지연/실패 신호',
    '지연되었거나 실패한 항목은 해당 관리 화면에서 처리해 주세요.',
    '지연 초과',
    '허용 지연',
    '<th class="text-center">바로가기</th>',
    '<th>대상</th>',
    'colspan="9"',
    'admin-operations-target-list',
    'community.board_copy.',
    '/admin/community/board-copy-jobs',
    '게시판 작업 관리',
    'btn btn-sm btn-icon btn-outline-secondary',
    "sr_material_icon_html('open_in_new')",
    'sr_admin_time_html($operationStatusCheckedAt)',
    "sr_admin_code_label((string) (\$row['module'] ?? ''), 'module_key')",
] as $marker) {
    if (is_string($view) && !str_contains($view, $marker)) {
        sr_operational_status_error('Admin operational status view is missing marker: ' . $marker);
    }
}

if (class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    sr_operational_status_fixture_check($pdo);
    $bundlePdo = new PDO('sqlite::memory:');
    $bundlePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    sr_operational_status_bundle_signal_fixture_check($bundlePdo);
    $pointExpirationPdo = new PDO('sqlite::memory:');
    $pointExpirationPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    sr_operational_status_point_expiration_fixture_check($pointExpirationPdo);
} else {
    sr_operational_status_error('PDO sqlite driver is required for operational status fixture.');
}

if ($errors !== []) {
    fwrite(STDERR, "operational status checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "operational status checks completed.\n";
