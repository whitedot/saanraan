<?php

declare(strict_types=1);

$policyDocumentAdminPage = 'form';
$policyDocumentFormMode = 'edit';
if (isset($_GET['id']) && !isset($_GET['version_id'])) {
    $_GET['version_id'] = $_GET['id'];
}

include SR_ROOT . '/modules/policy_documents/actions/admin-policy-documents.php';
