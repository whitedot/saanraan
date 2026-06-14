<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/policy_documents/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_owner($pdo, (int) $account['id']);

$errors = [];
$notice = '';
$selectedDocumentId = max(0, (int) ($_GET['document_id'] ?? $_POST['document_id'] ?? 0));

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_version') {
            if ($selectedDocumentId < 1) {
                throw new InvalidArgumentException(sr_t('policy_documents::error.document_required'));
            }

            $versionStatus = sr_post_string('status', 30);
            $versionId = sr_policy_document_create_version($pdo, $selectedDocumentId, [
                'version_key' => sr_post_string('version_key', 40),
                'title' => sr_post_string('title', 190),
                'body_html' => sr_post_string_without_truncation('body_html', 100000) ?? '',
                'summary_text' => sr_post_string('summary_text', 1000),
                'status' => $versionStatus,
                'effective_from' => sr_post_string('effective_from', 30),
            ]);
            if ($versionStatus === 'published') {
                sr_policy_document_create_notice_job(
                    $pdo,
                    $selectedDocumentId,
                    $versionId,
                    sr_t('policy_documents::mail.notice.subject'),
                    sr_t('policy_documents::mail.notice.body')
                );
            }
            $notice = sr_t('policy_documents::notice.version_created');
        } elseif ($action === 'run_mail_batch') {
            $jobId = max(0, (int) ($_POST['job_id'] ?? 0));
            $result = sr_policy_document_process_mail_batch($pdo, $site, $jobId, 20);
            $notice = sr_t('policy_documents::notice.mail_batch_processed', [
                'sent' => (string) (int) $result['sent'],
                'failed' => (string) (int) $result['failed'],
            ]);
        }
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$documents = sr_policy_documents_with_current_versions($pdo);
if ($selectedDocumentId < 1 && $documents !== []) {
    $selectedDocumentId = (int) $documents[0]['id'];
}

$selectedDocument = null;
foreach ($documents as $document) {
    if ((int) $document['id'] === $selectedDocumentId) {
        $selectedDocument = $document;
        break;
    }
}

$versions = $selectedDocumentId > 0 ? sr_policy_document_versions($pdo, $selectedDocumentId) : [];
$mailJobs = sr_policy_document_mail_jobs($pdo);

include SR_ROOT . '/modules/policy_documents/views/admin-policy-documents.php';
