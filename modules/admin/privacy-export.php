<?php

declare(strict_types=1);

require_once SR_ROOT . '/core/helpers/privacy-export.php';
require_once SR_ROOT . '/modules/admin/helpers/form-drafts.php';

return static function (PDO $pdo, int $accountId): array {
    $sectionLimits = [];
    if ($accountId < 1) {
        return ['form_drafts' => [], '_limits' => []];
    }

    if (!sr_admin_form_draft_table_exists($pdo)) {
        return ['form_drafts' => [], '_limits' => []];
    }

    $stmt = $pdo->prepare(
        'SELECT form_key, context_key, payload_json, created_at, updated_at
         FROM sr_admin_form_drafts
         WHERE account_id = :account_id
         ORDER BY updated_at DESC, id DESC
         LIMIT 101'
    );
    $stmt->execute(['account_id' => $accountId]);
    $drafts = sr_privacy_export_limit_rows($stmt->fetchAll(), 'form_drafts', $sectionLimits, 100);

    return [
        'form_drafts' => $drafts,
        '_limits' => $sectionLimits,
    ];
};
