<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/policy_documents/helpers.php';

$policyDocumentVersionId = max(0, (int) ($_GET['id'] ?? 0));
$policyDocumentVersion = sr_policy_document_public_version_by_id($pdo, $policyDocumentVersionId);
if (!is_array($policyDocumentVersion)) {
    sr_render_error(404, sr_t('policy_documents::error.version_required'));
}

include SR_ROOT . '/modules/policy_documents/views/version.php';
