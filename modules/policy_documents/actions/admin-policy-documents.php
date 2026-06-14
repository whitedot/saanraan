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

            sr_policy_document_create_version($pdo, $selectedDocumentId, [
                'version_key' => sr_post_string('version_key', 40),
                'title' => sr_post_string('title', 190),
                'body_html' => sr_post_string_without_truncation('body_html', 100000) ?? '',
                'summary_text' => sr_post_string('summary_text', 1000),
                'status' => sr_post_string('status', 30),
                'effective_from' => sr_post_string('effective_from', 30),
            ]);
            $notice = sr_t('policy_documents::notice.version_created');
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

include SR_ROOT . '/modules/policy_documents/views/admin-policy-documents.php';
