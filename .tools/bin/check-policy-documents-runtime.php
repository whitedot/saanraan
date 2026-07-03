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
    'modules/policy_documents/module.php',
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
        str_contains($policyDocumentViewSource, 'policy_documents::ui.version.body_view')
            && str_contains($policyDocumentViewSource, 'policy_documents::ui.previous_versions.option')
            && str_contains($policyDocumentViewSource, 'data-policy-document-standard-template')
            && str_contains($policyDocumentViewSource, 'data-policy-document-standard-template-json')
            && str_contains($policyDocumentViewSource, 'policy_documents::ui.effective_from.help')
            && str_contains($policyDocumentViewSource, 'sr_datetime_local_value($policyDocumentVersionValue(\'effective_from\'))')
            && str_contains($policyDocumentViewSource, 'sr_admin_time_html((string) ($version[\'effective_from\'] ?? \'\'), sr_t(\'policy_documents::ui.effective_from.empty\'))')
            && str_contains($policyDocumentViewSource, 'sr_policy_document_standard_template_verified_label($policyDocumentSelectedDocumentKey)')
            && str_contains($policyDocumentViewSource, '$policyDocumentStandardTemplateVerifiedLabel')
            && str_contains($policyDocumentViewSource, 'sr_policy_document_render_body_html($pdo, $version)')
            && str_contains($policyDocumentViewSource, 'modal-dialog-fluid')
            && str_contains($policyDocumentViewSource, 'modal-content-fullscreen modal-radius-md')
            && str_contains($policyDocumentViewSource, 'policy_documents::ui.mail_status.queued')
            && str_contains($policyDocumentViewSource, '$policyDocumentMailStatusLabels[$mailJobStatus] ?? $mailJobStatus')
            && !str_contains($policyDocumentViewSource, 'class="modal-content-fullscreen">')
            && !str_contains($policyDocumentViewSource, "modal-body-fill\">\n                            <section class=\"card admin-list-card admin-list-form\">")
            && str_contains($policyDocumentViewSource, 'data-overlay-stack="true"')
            && !str_contains($policyDocumentViewSource, 'name="version_key"')
            && !str_contains($policyDocumentViewSource, 'data-admin-version-key-input')
            && !str_contains($policyDocumentViewSource, 'policy_documents::ui.version_key'),
        'policy document admin view should hide internal version keys and provide fullscreen read-only body viewing for each version.'
    );
}
$policyDocumentInstallSource = file_get_contents('modules/policy_documents/install.sql');
if (is_string($policyDocumentInstallSource)) {
    sr_policy_documents_check_assert(
        !str_contains($policyDocumentInstallSource, 'version_key'),
        'policy document install schema should not include the removed version_key compatibility column.'
    );
}
$policyDocumentCleanupUpdateSource = file_get_contents('modules/policy_documents/updates/2026.07.002.sql');
if (is_string($policyDocumentCleanupUpdateSource)) {
    sr_policy_documents_check_assert(
        str_contains($policyDocumentCleanupUpdateSource, "COLUMN_NAME = 'version_key'")
            && str_contains($policyDocumentCleanupUpdateSource, 'DROP COLUMN version_key')
            && str_contains($policyDocumentCleanupUpdateSource, "version = '2026.07.002'"),
        'policy document 2026.07.002 update should remove leftover version_key columns from already-installed development databases.'
    );
}
$policyDocumentModuleSource = file_get_contents('modules/policy_documents/module.php');
if (is_string($policyDocumentModuleSource)) {
    sr_policy_documents_check_assert(
        str_contains($policyDocumentModuleSource, "'version' => '2026.07.002'"),
        'policy document module version should expose the version_key cleanup update as pending.'
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
            title_snapshot TEXT NOT NULL,
            body_html TEXT NOT NULL,
            summary_text TEXT NULL,
            body_hash TEXT NOT NULL,
            append_previous_versions INTEGER NOT NULL DEFAULT 0,
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
        'CREATE TABLE sr_site_settings (
            setting_key TEXT PRIMARY KEY,
            setting_value TEXT NOT NULL,
            value_type TEXT NOT NULL DEFAULT "string"
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
    $pdo->exec(
        "INSERT INTO sr_site_settings (setting_key, setting_value, value_type)
         VALUES ('site.name', '테스트몰', 'string'),
                ('site.base_url', 'https://example.test', 'string'),
                ('site.business_info_items', '[{\"key\":\"company_name\",\"label\":\"상호\",\"value\":\"테스트 주식회사\"},{\"key\":\"representative_name\",\"label\":\"대표자명\",\"value\":\"김대표\"},{\"key\":\"business_registration_number\",\"label\":\"사업자등록번호\",\"value\":\"123-45-67890\"},{\"key\":\"mail_order_report_number\",\"label\":\"통신판매업 신고번호\",\"value\":\"2026-서울테스트-0001\"},{\"key\":\"business_address\",\"label\":\"사업장 주소\",\"value\":\"서울특별시 테스트구 테스트로 1\"},{\"key\":\"business_email\",\"label\":\"사업자 전자우편주소\",\"value\":\"hello@example.test\"},{\"key\":\"customer_service_phone\",\"label\":\"고객센터 전화번호\",\"value\":\"070-1234-5678\"},{\"key\":\"customer_service_email\",\"label\":\"고객센터 전자우편주소\",\"value\":\"support@example.test\"},{\"key\":\"privacy_officer_name\",\"label\":\"개인정보보호책임자\",\"value\":\"홍길동\"},{\"key\":\"privacy_officer_email\",\"label\":\"개인정보보호책임자 이메일\",\"value\":\"privacy@example.test\"},{\"key\":\"hosting_provider\",\"label\":\"호스팅 제공자\",\"value\":\"테스트호스팅\"}]', 'json')"
    );

    return $pdo;
}

$pdo = sr_policy_documents_check_pdo();
try {
    sr_policy_document_create_version($pdo, 404, [
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
    'title' => '이용약관',
    'body_html' => '<p>첫 버전</p>',
    'summary_text' => '첫 변경 기록',
    'effective_from' => '2026-07-02T00:00',
    'status' => 'published',
]);
$secondVersionId = sr_policy_document_create_version($pdo, 1, [
    'title' => '이용약관',
    'body_html' => '<p>둘째 버전</p>',
    'summary_text' => '',
    'append_previous_versions' => 1,
    'status' => 'published',
]);
sr_policy_documents_check_assert($firstVersionId > 0 && $secondVersionId > $firstVersionId, 'published policy document versions should be inserted.');
$firstVersion = sr_policy_document_version_by_id($pdo, $firstVersionId);
$secondVersion = sr_policy_document_version_by_id($pdo, $secondVersionId);
sr_policy_documents_check_assert(
    is_array($firstVersion)
        && is_array($secondVersion)
        && !array_key_exists('version_key', $firstVersion)
        && !array_key_exists('version_key', $secondVersion),
    'policy document version rows should not expose the removed version_key compatibility column.'
);
sr_policy_documents_check_assert(
    (int) $pdo->query('SELECT COUNT(*) FROM sr_policy_document_versions WHERE document_id = 1 AND status = "published"')->fetchColumn() === 1,
    'publishing a new policy document version should archive previous published versions.'
);
sr_policy_documents_check_assert(
    (int) $pdo->query('SELECT id FROM sr_policy_document_versions WHERE status = "published"')->fetchColumn() === $secondVersionId,
    'the latest published policy document version should remain published.'
);
$renderData = sr_policy_document_public_render_data($pdo, 'member_terms');
sr_policy_documents_check_assert(
    is_array($renderData)
        && str_contains((string) ($renderData['body_html'] ?? ''), 'policy-document-version-history')
        && str_contains((string) ($renderData['body_html'] ?? ''), '2026년 7월 2일')
        && str_contains((string) ($renderData['body_html'] ?? ''), '/policy-documents/version?id=' . (string) $firstVersionId)
        && str_contains((string) ($renderData['body_html'] ?? ''), 'target="_blank" rel="noopener noreferrer"')
        && !str_contains((string) ($renderData['body_html'] ?? ''), '2026.06.001')
        && !str_contains((string) ($renderData['body_html'] ?? ''), '첫 변경 기록')
        && !str_contains((string) ($renderData['body_html'] ?? ''), '<p>첫 버전</p>')
        && str_contains((string) ($renderData['body_html'] ?? ''), '<time datetime="2026-07-02T00:00:00">2026년 7월 2일</time>'),
    'published policy document render data should append previous policy date links only when requested.'
);
sr_policy_documents_check_assert(
    (string) $pdo->query('SELECT body_html FROM sr_policy_document_versions WHERE id = ' . (int) $secondVersionId)->fetchColumn() === '<p>둘째 버전</p>',
    'previous version history should not be inserted into the stored policy document body.'
);
sr_policy_documents_check_assert(
    is_array($renderData)
        && (string) ($renderData['body_hash'] ?? '') === hash('sha256', (string) ($renderData['body_html'] ?? ''))
        && (string) ($renderData['stored_body_hash'] ?? '') === hash('sha256', '<p>둘째 버전</p>'),
    'policy document render data should expose a rendered body hash while preserving the stored body hash.'
);
$snapshot = sr_policy_document_snapshot($pdo, 'member_terms');
sr_policy_documents_check_assert(
    (string) ($snapshot['body_hash'] ?? '') === (string) ($renderData['body_hash'] ?? ''),
    'policy document snapshots should store the hash of the rendered policy content.'
);
$previousPublicVersion = sr_policy_document_public_version_by_id($pdo, $firstVersionId);
sr_policy_documents_check_assert(
    is_array($previousPublicVersion)
        && !array_key_exists('version_key', $previousPublicVersion)
        && (string) ($previousPublicVersion['body_html'] ?? '') === '<p>첫 버전</p>',
    'linked previous policy document versions should be readable through the public version lookup.'
);
$futureVersionId = sr_policy_document_create_version($pdo, 1, [
    'title' => '이용약관',
    'body_html' => '<p>미래 버전</p>',
    'summary_text' => '',
    'status' => 'published',
    'effective_from' => '2099-01-01T00:00',
]);
sr_policy_documents_check_assert($futureVersionId > $secondVersionId, 'future policy document version should be inserted.');
sr_policy_documents_check_assert(
    str_contains(sr_policy_document_notice_mail_body($pdo, $futureVersionId), '시행일: 2099년 1월 1일')
        && str_contains(sr_policy_document_notice_mail_body($pdo, $secondVersionId), '시행일: 공개 즉시'),
    'policy document notice mail body should include the policy effective date.'
);
sr_policy_documents_check_assert(
    (int) $pdo->query('SELECT id FROM sr_policy_document_versions WHERE status = "published" AND (effective_from IS NULL OR effective_from <= CURRENT_TIMESTAMP) ORDER BY id DESC LIMIT 1')->fetchColumn() === $secondVersionId,
    'future published policy document versions should not archive the currently effective version.'
);
sr_policy_documents_check_assert(
    (int) sr_policy_document_published_version($pdo, 'member_terms')['id'] === $secondVersionId,
    'current policy document lookup should ignore future effective versions until their effective time.'
);
$currentDocuments = sr_policy_documents_with_current_versions($pdo);
sr_policy_documents_check_assert(
    (int) ($currentDocuments[0]['published_version_id'] ?? 0) === $secondVersionId
        && !array_key_exists('published_version_key', $currentDocuments[0]),
    'admin policy document list should ignore future effective versions until their effective time.'
);
$enabledChoices = sr_policy_document_enabled_choices($pdo);
sr_policy_documents_check_assert(
    (int) ($enabledChoices[0]['published_version_id'] ?? 0) === $secondVersionId
        && !array_key_exists('published_version_key', $enabledChoices[0]),
    'policy document choices should ignore future effective versions until their effective time.'
);
$allVersions = sr_policy_document_all_versions($pdo);
sr_policy_documents_check_assert(
    str_contains((string) ($allVersions[0]['body_html'] ?? ''), '<p>미래 버전</p>'),
    'admin policy document version list should include body HTML for read-only previous version review.'
);
$plainVersionId = sr_policy_document_create_version($pdo, 1, [
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
$termsTemplate = sr_policy_document_standard_template_html($pdo, 'member_terms', ['name' => '테스트몰', 'base_url' => 'https://example.test']);
sr_policy_documents_check_assert(
    str_contains($termsTemplate, '테스트몰')
        && str_contains($termsTemplate, 'https://example.test')
        && str_contains($termsTemplate, '070-1234-5678')
        && str_contains($termsTemplate, '테스트 주식회사')
        && str_contains($termsTemplate, '김대표')
        && str_contains($termsTemplate, '2026-서울테스트-0001')
        && str_contains($termsTemplate, '전자상거래 등에서의 소비자보호에 관한 법률')
        && str_contains(sr_policy_document_standard_template_verified_label('member_terms'), '2026년 7월 3일'),
    'standard terms template should use official e-commerce terms structure and site business information.'
);
$privacyTemplate = sr_policy_document_standard_template_html($pdo, 'member_privacy_policy', ['name' => '테스트몰', 'base_url' => 'https://example.test']);
sr_policy_documents_check_assert(
    str_contains($privacyTemplate, '테스트몰')
        && str_contains($privacyTemplate, '홍길동')
        && str_contains($privacyTemplate, 'privacy@example.test')
        && str_contains($privacyTemplate, 'support@example.test')
        && str_contains($privacyTemplate, '테스트호스팅')
        && str_contains($privacyTemplate, '동의 없이 처리하는 개인정보')
        && str_contains($privacyTemplate, '자동화된 결정')
        && str_contains(sr_policy_document_standard_template_button_label('member_privacy_policy'), '개인정보처리방침'),
    'standard privacy policy template should follow the current privacy policy guide structure and use site business information.'
);

$staleJobId = sr_policy_document_create_notice_job($pdo, 1, $futureVersionId, 'future subject', 'future body', true);
sr_policy_documents_check_assert(
    (string) $pdo->query('SELECT status FROM sr_policy_document_mail_jobs WHERE id = ' . (int) $staleJobId)->fetchColumn() === 'queued',
    'policy document notice job should start as queued before a newer notice job supersedes it.'
);
$jobId = sr_policy_document_create_notice_job($pdo, 1, $secondVersionId, 'subject', 'body', true);
sr_policy_documents_check_assert(
    (string) $pdo->query('SELECT status FROM sr_policy_document_mail_jobs WHERE id = ' . (int) $staleJobId)->fetchColumn() === 'cancelled',
    'new policy document notice jobs should cancel unfinished notice jobs for the same document.'
);
sr_policy_documents_check_assert(
    (string) $pdo->query('SELECT status FROM sr_policy_document_mail_deliveries WHERE job_id = ' . (int) $staleJobId . ' LIMIT 1')->fetchColumn() === 'cancelled',
    'superseded policy document notice deliveries should be marked cancelled.'
);
try {
    sr_policy_document_create_notice_job($pdo, 404, $secondVersionId, 'subject', 'body', true);
    sr_policy_documents_check_assert(false, 'policy document notice job creation should reject version/document mismatch.');
} catch (InvalidArgumentException) {
    sr_policy_documents_check_assert(true, 'policy document notice job creation should reject version/document mismatch.');
}
$sameJobId = sr_policy_document_create_notice_job($pdo, 1, $secondVersionId, 'subject', 'body', true);
sr_policy_documents_check_assert($jobId === $sameJobId, 'notice job creation should be idempotent per version.');
sr_policy_documents_check_assert(
    (int) $pdo->query('SELECT COUNT(*) FROM sr_policy_document_mail_jobs WHERE version_id = ' . (int) $secondVersionId)->fetchColumn() === 1,
    'duplicate notice jobs should not be created for the same version.'
);
sr_policy_documents_check_assert(
    (int) $pdo->query('SELECT COUNT(*) FROM sr_policy_document_mail_deliveries WHERE job_id = ' . (int) $jobId)->fetchColumn() === 1,
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
    (string) $pdo->query('SELECT status FROM sr_policy_document_mail_jobs WHERE id = ' . (int) $jobId)->fetchColumn() === 'cancelled',
    'policy document mail job should be marked cancelled when remaining live deliveries are cancelled.'
);

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo "policy documents runtime checks completed.\n";
