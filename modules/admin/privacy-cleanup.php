<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/admin/helpers/form-drafts.php';

return static function (PDO $pdo, int $accountId): array {
    if ($accountId < 1) {
        return ['cleaned' => false];
    }
    if (!sr_admin_form_draft_table_exists($pdo)) {
        return [
            'cleaned' => true,
            'admin_form_draft_deleted_count' => 0,
        ];
    }

    $stmt = $pdo->prepare('DELETE FROM sr_admin_form_drafts WHERE account_id = :account_id');
    $stmt->execute(['account_id' => $accountId]);
    return [
        'cleaned' => true,
        'admin_form_draft_deleted_count' => $stmt->rowCount(),
    ];
};
