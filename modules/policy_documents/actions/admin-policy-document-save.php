<?php

declare(strict_types=1);

$policyDocumentAdminPage = 'form';
$policyDocumentFormMode = (string) ($_POST['form_mode'] ?? 'new') === 'edit' ? 'edit' : 'new';

include SR_ROOT . '/modules/policy_documents/actions/admin-policy-documents.php';
