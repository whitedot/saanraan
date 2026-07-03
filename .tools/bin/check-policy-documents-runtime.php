#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/helpers.php';
require_once $root . '/modules/policy_documents/helpers.php';

$errors = [];

foreach ([
    'modules/policy_documents/install.sql',
    'modules/policy_documents/helpers.php',
    'modules/policy_documents/actions/admin-policy-documents.php',
    'modules/policy_documents/views/admin-policy-documents.php',
] as $policyDocumentSourcePath) {
    $policyDocumentSource = file_get_contents($policyDocumentSourcePath);
    if (!is_string($policyDocumentSource)) {
        $errors[] = 'policy document source must be readable: ' . $policyDocumentSourcePath;
        continue;
    }

    if (str_contains($policyDocumentSource, 'document_type')) {
        $errors[] = 'legacy policy document document_type column must stay removed from ' . $policyDocumentSourcePath;
    }
}
foreach (glob('modules/policy_documents/updates/*.sql') ?: [] as $policyDocumentUpdatePath) {
    $policyDocumentUpdate = file_get_contents($policyDocumentUpdatePath);
    if (!is_string($policyDocumentUpdate)) {
        $errors[] = 'policy document update source must be readable: ' . $policyDocumentUpdatePath;
        continue;
    }

    if (str_contains($policyDocumentUpdate, 'document_type')) {
        $errors[] = 'legacy policy document document_type cleanup update must stay removed from ' . $policyDocumentUpdatePath;
    }
}

function sr_policy_documents_check_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

$policyDocumentViewSource = file_get_contents('modules/policy_documents/views/admin-policy-documents.php');
if (is_string($policyDocumentViewSource)) {
    sr_policy_documents_check_assert(
        str_contains($policyDocumentViewSource, '$policyDocumentLatestVersionKeyByDocumentId')
            && str_contains($policyDocumentViewSource, 'policy_documents::ui.version_key.latest_badge')
            && str_contains($policyDocumentViewSource, 'badge badge-soft-secondary badge-pill')
            && str_contains($policyDocumentViewSource, 'policy_documents::ui.version.body_view')
            && str_contains($policyDocumentViewSource, 'sr_policy_document_sanitize_body((string) ($version[\'body_html\'] ?? \'\'))')
            && str_contains($policyDocumentViewSource, 'modal-dialog-fluid')
            && str_contains($policyDocumentViewSource, 'modal-content-fullscreen modal-radius-md')
            && !str_contains($policyDocumentViewSource, 'class="modal-content-fullscreen">')
            && str_contains($policyDocumentViewSource, 'data-overlay-stack="true"'),
        'policy document admin view should show the latest existing version key badge and provide fullscreen read-only body viewing for each version.'
    );
}

function sr_policy_documents_check_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(
        'CREATE TABLE sr_policy_documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_key TEXT NOT NULL,
            title TEXT NOT NULL,
            description TEXT NULL,
            status TEXT NOT NULL DEFAULT "enabled",
            sort_order INTEGER NOT NULL DEFAULT 100,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_policy_document_versions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL,
            version_key TEXT NOT NULL,
            title_snapshot TEXT NOT NULL,
            body_html TEXT NOT NULL,
            summary_text TEXT NULL,
            body_hash TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "draft",
            effective_from TEXT NULL,
            published_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_policy_document_mail_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL,
            version_id INTEGER NOT NULL,
            job_key TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "queued",
            target_status_snapshot TEXT NOT NULL DEFAULT "active",
            subject_snapshot TEXT NOT NULL,
            body_snapshot TEXT NOT NULL,
            dry_run INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_policy_document_mail_deliveries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_id INTEGER NOT NULL,
            account_id INTEGER NULL,
            status TEXT NOT NULL DEFAULT "queued",
            failure_code TEXT NOT NULL DEFAULT "",
            claimed_at TEXT NULL,
            sent_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_member_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "active"
        )'
    );
    $pdo->exec(
        "INSERT INTO sr_policy_documents (id, document_key, title, description, status, sort_order, created_at, updated_at)
         VALUES (1, 'member_terms', '이용약관', '', 'enabled', 10, '', '')"
    );
    $pdo->exec(
        "INSERT INTO sr_member_accounts (id, email, status)
         VALUES (1, 'active@example.test', 'active'),
                (2, 'suspended@example.test', 'suspended')"
    );

    return $pdo;
}

$pdo = sr_policy_documents_check_pdo();
try {
    sr_policy_document_create_version($pdo, 404, [
        'version_key' => '2026.06.000',
        'title' => '없는 문서',
        'body_html' => '<p>본문</p>',
        'summary_text' => '',
        'status' => 'published',
    ]);
    sr_policy_documents_check_assert(false, 'policy document version creation should reject missing document ids.');
} catch (InvalidArgumentException) {
    sr_policy_documents_check_assert(true, 'policy document version creation should reject missing document ids.');
}

$firstVersionId = sr_policy_document_create_version($pdo, 1, [
    'version_key' => '2026.06.001',
    'title' => '이용약관',
    'body_html' => '<p>첫 버전</p>',
    'summary_text' => '',
    'status' => 'published',
]);
$secondVersionId = sr_policy_document_create_version($pdo, 1, [
    'version_key' => '2026.06.002',
    'title' => '이용약관',
    'body_html' => '<p>둘째 버전</p>',
    'summary_text' => '',
    'status' => 'published',
]);
sr_policy_documents_check_assert($firstVersionId > 0 && $secondVersionId > $firstVersionId, 'published policy document versions should be inserted.');
sr_policy_documents_check_assert(
    (int) $pdo->query('SELECT COUNT(*) FROM sr_policy_document_versions WHERE document_id = 1 AND status = "published"')->fetchColumn() === 1,
    'publishing a new policy document version should archive previous published versions.'
);
sr_policy_documents_check_assert(
    (int) $pdo->query('SELECT id FROM sr_policy_document_versions WHERE status = "published"')->fetchColumn() === $secondVersionId,
    'the latest published policy document version should remain published.'
);
$futureVersionId = sr_policy_document_create_version($pdo, 1, [
    'version_key' => '2026.06.003',
    'title' => '이용약관',
    'body_html' => '<p>미래 버전</p>',
    'summary_text' => '',
    'status' => 'published',
    'effective_from' => '2099-01-01T00:00',
]);
sr_policy_documents_check_assert($futureVersionId > $secondVersionId, 'future policy document version should be inserted.');
sr_policy_documents_check_assert(
    (int) $pdo->query('SELECT id FROM sr_policy_document_versions WHERE status = "published" AND (effective_from IS NULL OR effective_from <= CURRENT_TIMESTAMP) ORDER BY id DESC LIMIT 1')->fetchColumn() === $secondVersionId,
    'future published policy document versions should not archive the currently effective version.'
);
sr_policy_documents_check_assert(
    (string) sr_policy_document_published_version($pdo, 'member_terms')['version_key'] === '2026.06.002',
    'current policy document lookup should ignore future effective versions until their effective time.'
);
$currentDocuments = sr_policy_documents_with_current_versions($pdo);
sr_policy_documents_check_assert(
    (string) ($currentDocuments[0]['published_version_key'] ?? '') === '2026.06.002',
    'admin policy document list should ignore future effective versions until their effective time.'
);
$enabledChoices = sr_policy_document_enabled_choices($pdo);
sr_policy_documents_check_assert(
    (string) ($enabledChoices[0]['published_version_key'] ?? '') === '2026.06.002',
    'policy document choices should ignore future effective versions until their effective time.'
);
$allVersions = sr_policy_document_all_versions($pdo);
sr_policy_documents_check_assert(
    str_contains((string) ($allVersions[0]['body_html'] ?? ''), '<p>미래 버전</p>'),
    'admin policy document version list should include body HTML for read-only previous version review.'
);
$plainVersionId = sr_policy_document_create_version($pdo, 1, [
    'version_key' => '2026.06.004',
    'title' => '일반 텍스트 약관',
    'body_editor_mode' => 'plain',
    'body_plain' => "첫 줄\n둘째 줄",
    'summary_text' => '',
    'status' => 'draft',
]);
$plainVersion = sr_policy_document_version_by_id($pdo, $plainVersionId);
sr_policy_documents_check_assert(
    is_array($plainVersion) && str_contains((string) ($plainVersion['body_html'] ?? ''), '<p>첫 줄<br>'),
    'plain policy document bodies should be converted to sanitized HTML.'
);

$jobId = sr_policy_document_create_notice_job($pdo, 1, $secondVersionId, 'subject', 'body', true);
try {
    sr_policy_document_create_notice_job($pdo, 404, $secondVersionId, 'subject', 'body', true);
    sr_policy_documents_check_assert(false, 'policy document notice job creation should reject version/document mismatch.');
} catch (InvalidArgumentException) {
    sr_policy_documents_check_assert(true, 'policy document notice job creation should reject version/document mismatch.');
}
$sameJobId = sr_policy_document_create_notice_job($pdo, 1, $secondVersionId, 'subject', 'body', true);
sr_policy_documents_check_assert($jobId === $sameJobId, 'notice job creation should be idempotent per version.');
sr_policy_documents_check_assert(
    (int) $pdo->query('SELECT COUNT(*) FROM sr_policy_document_mail_jobs')->fetchColumn() === 1,
    'duplicate notice jobs should not be created for the same version.'
);
sr_policy_documents_check_assert(
    (int) $pdo->query('SELECT COUNT(*) FROM sr_policy_document_mail_deliveries')->fetchColumn() === 1,
    'notice delivery seed should include only active member accounts.'
);

$pdo->exec(
    "INSERT INTO sr_policy_document_mail_deliveries (job_id, account_id, status, failure_code, created_at, updated_at)
     VALUES (" . (int) $jobId . ", 2, 'queued', '', '', '')"
);
$pdo->exec(
    "INSERT INTO sr_policy_document_mail_deliveries (job_id, account_id, status, failure_code, claimed_at, created_at, updated_at)
     VALUES (" . (int) $jobId . ", 2, 'processing', '', '2000-01-01 00:00:00', '', '')"
);
$result = sr_policy_document_process_mail_batch($pdo, [], $jobId, 20);
sr_policy_documents_check_assert((int) $result['sent'] === 1, 'dry-run policy document mail batch should mark active queued deliveries as sent.');
sr_policy_documents_check_assert((int) $result['claimed'] === 1, 'policy document mail batch should only send claimed deliveries.');
sr_policy_documents_check_assert((int) $result['skipped'] === 2, 'policy document mail batch should skip inactive queued or stale processing deliveries.');
sr_policy_documents_check_assert(
    (int) $pdo->query('SELECT COUNT(*) FROM sr_policy_document_mail_deliveries WHERE account_id = 2 AND status = "skipped"')->fetchColumn() === 2,
    'inactive policy document mail deliveries should be closed as skipped.'
);
sr_policy_documents_check_assert(
    (string) $pdo->query('SELECT status FROM sr_policy_document_mail_jobs WHERE id = ' . (int) $jobId)->fetchColumn() === 'sent',
    'policy document mail job should finish when queued deliveries are sent or skipped.'
);

$pdo->exec(
    "INSERT INTO sr_policy_document_mail_deliveries (job_id, account_id, status, failure_code, claimed_at, created_at, updated_at)
     VALUES (" . (int) $jobId . ", 3, 'failed', 'send_failed', '', '', '')"
);
$pdo->exec("INSERT INTO sr_member_accounts (id, email, status) VALUES (3, 'retry@example.test', 'active'), (4, 'cancel@example.test', 'active')");
$requeued = sr_policy_document_requeue_failed_mail_deliveries($pdo, $jobId);
sr_policy_documents_check_assert($requeued === 1, 'failed policy document mail deliveries should be requeued explicitly.');
sr_policy_documents_check_assert(
    (string) $pdo->query('SELECT status FROM sr_policy_document_mail_deliveries WHERE account_id = 3')->fetchColumn() === 'queued',
    'requeued policy document delivery should return to queued status.'
);
$result = sr_policy_document_process_mail_batch($pdo, [], $jobId, 20);
sr_policy_documents_check_assert((int) $result['sent'] === 1 && (int) $result['claimed'] === 1, 'requeued policy document delivery should be claimed and sent once.');
$pdo->exec(
    "INSERT INTO sr_policy_document_mail_deliveries (job_id, account_id, status, failure_code, created_at, updated_at)
     VALUES (" . (int) $jobId . ", 4, 'queued', '', '', '')"
);
$cancelled = sr_policy_document_cancel_pending_mail_deliveries($pdo, $jobId);
sr_policy_documents_check_assert($cancelled === 1, 'queued policy document mail deliveries should be cancellable.');
sr_policy_documents_check_assert(
    (string) $pdo->query('SELECT status FROM sr_policy_document_mail_deliveries WHERE account_id = 4')->fetchColumn() === 'cancelled',
    'cancelled policy document delivery should not remain queued.'
);
sr_policy_documents_check_assert(
    (string) $pdo->query('SELECT status FROM sr_policy_document_mail_jobs WHERE id = ' . (int) $jobId)->fetchColumn() === 'sent',
    'policy document mail job should ignore cancelled deliveries when all live deliveries are closed.'
);

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo "policy documents runtime checks completed.\n";
