<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/policy_documents/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_owner($pdo, (int) $account['id']);

$policyDocumentAdminPage = isset($policyDocumentAdminPage) ? (string) $policyDocumentAdminPage : 'list';
$policyDocumentFormMode = isset($policyDocumentFormMode) ? (string) $policyDocumentFormMode : 'new';
$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = $flashResult['notice'];
$selectedDocumentId = max(0, (int) ($_GET['document_id'] ?? $_POST['document_id'] ?? 0));
$selectedVersionId = max(0, (int) ($_GET['version_id'] ?? $_POST['version_id'] ?? 0));

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_document') {
            $documentId = sr_policy_document_create_document($pdo, [
                'document_key' => sr_post_string('document_key', 80),
                'title' => sr_post_string('title', 190),
                'description' => sr_post_string('description', 2000),
                'status' => sr_post_string('status', 30),
                'sort_order' => (int) ($_POST['sort_order'] ?? 100),
            ]);
            sr_admin_flash_result(sr_admin_action_result([], sr_t('policy_documents::notice.document_created')));
            sr_redirect('/admin/policy-documents?document_id=' . (string) $documentId);
        } elseif ($action === 'save_version') {
            if ($policyDocumentFormMode === 'edit') {
                if ($selectedVersionId < 1) {
                    throw new InvalidArgumentException(sr_t('policy_documents::error.version_required'));
                }
                sr_policy_document_update_draft_version($pdo, $selectedVersionId, [
                    'title' => sr_post_string('title', 190),
                    'body_editor_mode' => sr_post_string('body_editor_mode', 20),
                    'body_plain' => sr_post_string_without_truncation('body_plain', 100000) ?? '',
                    'body_html' => sr_post_string_without_truncation('body_html', 100000) ?? '',
                    'body_ckeditor_html' => sr_post_string_without_truncation('body_ckeditor_html', 100000) ?? '',
                    'summary_text' => sr_post_string('summary_text', 1000),
                    'effective_from' => sr_post_string('effective_from', 30),
                ]);
                sr_admin_flash_result(sr_admin_action_result([], sr_t('policy_documents::notice.version_updated')));
                sr_redirect('/admin/policy-documents/edit?id=' . (string) $selectedVersionId);
            }

            if ($selectedDocumentId < 1) {
                throw new InvalidArgumentException(sr_t('policy_documents::error.document_required'));
            }

            $versionStatus = sr_post_string('status', 30);
            $versionId = sr_policy_document_create_version($pdo, $selectedDocumentId, [
                'version_key' => sr_post_string('version_key', 40),
                'title' => sr_post_string('title', 190),
                'body_editor_mode' => sr_post_string('body_editor_mode', 20),
                'body_plain' => sr_post_string_without_truncation('body_plain', 100000) ?? '',
                'body_html' => sr_post_string_without_truncation('body_html', 100000) ?? '',
                'body_ckeditor_html' => sr_post_string_without_truncation('body_ckeditor_html', 100000) ?? '',
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
            sr_admin_flash_result(sr_admin_action_result([], sr_t('policy_documents::notice.version_created')));
            sr_redirect('/admin/policy-documents?document_id=' . (string) $selectedDocumentId);
        } elseif ($action === 'publish_version') {
            if ($selectedVersionId < 1) {
                throw new InvalidArgumentException(sr_t('policy_documents::error.version_required'));
            }
            $publishResult = sr_policy_document_publish_draft_version($pdo, $selectedVersionId);
            sr_policy_document_create_notice_job(
                $pdo,
                (int) $publishResult['document_id'],
                (int) $publishResult['version_id'],
                sr_t('policy_documents::mail.notice.subject'),
                sr_t('policy_documents::mail.notice.body')
            );
            sr_admin_flash_result(sr_admin_action_result([], sr_t('policy_documents::notice.version_published')));
            sr_redirect('/admin/policy-documents?document_id=' . (string) (int) $publishResult['document_id']);
        } elseif ($action === 'run_mail_batch') {
            $jobId = max(0, (int) ($_POST['job_id'] ?? 0));
            $result = sr_policy_document_process_mail_batch($pdo, $site, $jobId, 20);
            $notice = sr_t('policy_documents::notice.mail_batch_processed', [
                'sent' => (string) (int) $result['sent'],
                'failed' => (string) (int) $result['failed'],
            ]);
            sr_admin_flash_result(sr_admin_action_result([], $notice));
            sr_redirect('/admin/policy-documents');
        }
    } catch (Throwable $exception) {
        sr_admin_flash_result(sr_admin_action_result([$exception->getMessage()], ''));
        if ($policyDocumentAdminPage === 'form') {
            if ($policyDocumentFormMode === 'document_new') {
                $fallback = '/admin/policy-documents/document-new';
            } elseif ($policyDocumentFormMode === 'edit' && $selectedVersionId > 0) {
                $fallback = '/admin/policy-documents/edit?id=' . (string) $selectedVersionId;
            } else {
                $fallback = '/admin/policy-documents/new?document_id=' . (string) $selectedDocumentId;
            }
            sr_redirect($fallback);
        }
        sr_redirect('/admin/policy-documents');
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

$versions = sr_policy_document_all_versions($pdo);
$mailJobs = sr_policy_document_mail_jobs($pdo);
$formVersion = null;
if ($policyDocumentAdminPage === 'form' && $policyDocumentFormMode === 'edit') {
    $formVersion = sr_policy_document_version_by_id($pdo, $selectedVersionId);
    if (!is_array($formVersion)) {
        sr_admin_flash_result(sr_admin_action_result([sr_t('policy_documents::error.version_required')], ''));
        sr_redirect('/admin/policy-documents');
    }
    if ((string) ($formVersion['status'] ?? '') !== 'draft') {
        sr_admin_flash_result(sr_admin_action_result([sr_t('policy_documents::error.version_edit_draft_only')], ''));
        sr_redirect('/admin/policy-documents?document_id=' . (string) (int) $formVersion['document_id']);
    }
    $selectedDocumentId = (int) $formVersion['document_id'];
    $selectedDocument = sr_policy_document_by_id($pdo, $selectedDocumentId);
}

include SR_ROOT . '/modules/policy_documents/views/admin-policy-documents.php';
