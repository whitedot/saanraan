<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, array $context = []): array {
    if ($accountId < 1) {
        return ['cleaned' => false];
    }

    $columnExists = static function (PDO $pdo, string $tableName, string $columnName): bool {
        static $exists = [];
        if (!preg_match('/\Asr_[a-z0-9_]+\z/', $tableName) || !preg_match('/\A[a-zA-Z0-9_]+\z/', $columnName)) {
            return false;
        }

        $cacheKey = (string) spl_object_id($pdo) . ':' . $tableName . '.' . $columnName;
        if (array_key_exists($cacheKey, $exists)) {
            return $exists[$cacheKey];
        }

        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $stmt = $pdo->query('PRAGMA table_info(' . $tableName . ')');
                foreach ($stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
                    if ((string) ($row['name'] ?? '') === $columnName) {
                        $exists[$cacheKey] = true;
                        return true;
                    }
                }
                $exists[$cacheKey] = false;
                return false;
            }

            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                   AND COLUMN_NAME = :column_name'
            );
            $stmt->execute([
                'table_name' => $tableName,
                'column_name' => $columnName,
            ]);
            $exists[$cacheKey] = (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $exception) {
            $exists[$cacheKey] = false;
        }

        return $exists[$cacheKey];
    };

    if (!function_exists('sr_community_delete_member_nickname')) {
        require_once SR_ROOT . '/modules/community/helpers/members.php';
    }
    if (!function_exists('sr_community_delete_account_level_data')) {
        require_once SR_ROOT . '/modules/community/helpers/levels.php';
    }
    if (!function_exists('sr_community_anonymize_access_entitlements')) {
        require_once SR_ROOT . '/modules/community/helpers/assets.php';
    }
    if (!function_exists('sr_community_series_supported')) {
        require_once SR_ROOT . '/modules/community/helpers.php';
    }
    if (!function_exists('sr_community_extra_field_values_cleanup_json')) {
        require_once SR_ROOT . '/modules/community/helpers/posts.php';
    }

    $deleted = sr_community_delete_member_nickname($pdo, $accountId);
    $levelCleanup = sr_community_delete_account_level_data($pdo, $accountId);
    $entitlementCount = sr_community_anonymize_access_entitlements($pdo, $accountId);
    $seriesMetadataCount = 0;
    $seriesScrapDeletedCount = 0;
    $authorSnapshotAnonymizedCount = 0;
    $postExtraValuesAnonymizedCount = 0;
    $postFieldValuesAnonymizedCount = 0;
    $submissionConsentAnonymizedCount = 0;
    $attachmentDownloadLogAnonymizedCount = 0;
    $postReadPaymentLogAnonymizedCount = 0;
    $reportAutoActionActorLinksCleared = 0;
    $hiddenTargetActorLinksCleared = 0;
    $accountGuardEventAnonymizedCount = 0;
    $accountGuardAnonymizedCount = 0;
    $assetRecoveryFailureAnonymizedCount = 0;
    $assetRecoveryFailureActorLinksCleared = 0;
    $postDraftDeletedCount = 0;

    foreach (['sr_community_posts', 'sr_community_comments'] as $tableName) {
        if (!$columnExists($pdo, $tableName, 'author_public_name_snapshot')) {
            continue;
        }

        $stmt = $pdo->prepare(
            'UPDATE ' . $tableName . '
             SET author_public_name_snapshot = \'\'
             WHERE author_account_id = :account_id
               AND author_public_name_snapshot <> \'\''
        );
        $stmt->execute(['account_id' => $accountId]);
        $authorSnapshotAnonymizedCount += $stmt->rowCount();
    }

    if ($columnExists($pdo, 'sr_community_posts', 'extra_values_json')) {
        $stmt = $pdo->prepare(
            "SELECT id, extra_values_json
             FROM sr_community_posts
             WHERE author_account_id = :account_id
               AND COALESCE(extra_values_json, '') <> ''"
        );
        $stmt->execute(['account_id' => $accountId]);
        $updateStmt = $pdo->prepare(
            'UPDATE sr_community_posts
             SET extra_values_json = :extra_values_json,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $originalJson = (string) ($row['extra_values_json'] ?? '');
            $cleanedJson = function_exists('sr_community_extra_field_values_cleanup_json')
                ? sr_community_extra_field_values_cleanup_json($originalJson)
                : '[]';
            if ($cleanedJson === $originalJson) {
                continue;
            }
            $updateStmt->execute([
                'extra_values_json' => $cleanedJson,
                'updated_at' => sr_now(),
                'id' => (int) ($row['id'] ?? 0),
            ]);
            $postExtraValuesAnonymizedCount++;
        }
    }

    if ($columnExists($pdo, 'sr_community_post_drafts', 'account_id')) {
        $stmt = $pdo->prepare(
            'DELETE FROM sr_community_post_drafts
             WHERE account_id = :account_id'
        );
        $stmt->execute(['account_id' => $accountId]);
        $postDraftDeletedCount = $stmt->rowCount();
    }

    if (function_exists('sr_community_post_field_values_table_exists') && sr_community_post_field_values_table_exists($pdo)) {
        $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $stmt = $pdo->prepare($isSqlite
            ? "UPDATE sr_community_post_field_values
               SET value_text = '',
                   value_json = NULL,
                   updated_at = :updated_at
               WHERE post_id IN (SELECT id FROM sr_community_posts WHERE author_account_id = :account_id)
                 AND cleanup_policy_snapshot <> 'retain'
                 AND (COALESCE(value_text, '') <> '' OR value_json IS NOT NULL)"
            : "UPDATE sr_community_post_field_values v
               INNER JOIN sr_community_posts p ON p.id = v.post_id
               SET v.value_text = '',
                   v.value_json = NULL,
                   v.updated_at = :updated_at
               WHERE p.author_account_id = :account_id
                 AND v.cleanup_policy_snapshot <> 'retain'
                 AND (COALESCE(v.value_text, '') <> '' OR v.value_json IS NOT NULL)"
        );
        $stmt->execute([
            'account_id' => $accountId,
            'updated_at' => sr_now(),
        ]);
        $postFieldValuesAnonymizedCount = $stmt->rowCount();
    }

    if (function_exists('sr_community_series_supported') && sr_community_series_supported($pdo)) {
        if (function_exists('sr_community_series_scraps_table_exists') && sr_community_series_scraps_table_exists($pdo)) {
            $stmt = $pdo->prepare(
                'DELETE FROM sr_community_series_scraps
                 WHERE account_id = :account_id'
            );
            $stmt->execute(['account_id' => $accountId]);
            $seriesScrapDeletedCount = $stmt->rowCount();
        }

        foreach (['created_by', 'updated_by', 'moderated_by'] as $columnName) {
            if (!$columnExists($pdo, 'sr_community_series', $columnName)) {
                continue;
            }

            $stmt = $pdo->prepare(
                'UPDATE sr_community_series
                 SET ' . $columnName . ' = NULL
                 WHERE ' . $columnName . ' = :account_id'
            );
            $stmt->execute(['account_id' => $accountId]);
            $seriesMetadataCount += $stmt->rowCount();
        }

        if ($columnExists($pdo, 'sr_community_series_items', 'created_by')) {
            $stmt = $pdo->prepare(
                'UPDATE sr_community_series_items
                 SET created_by = NULL
                 WHERE created_by = :account_id'
            );
            $stmt->execute(['account_id' => $accountId]);
            $seriesMetadataCount += $stmt->rowCount();
        }
    }

    if ($columnExists($pdo, 'sr_community_submission_consents', 'account_id')) {
        $stmt = $pdo->prepare(
            'UPDATE sr_community_submission_consents
             SET account_id = NULL,
                 ip_hash = NULL,
                 user_agent_hash = NULL
             WHERE account_id = :account_id'
        );
        $stmt->execute(['account_id' => $accountId]);
        $submissionConsentAnonymizedCount = $stmt->rowCount();
    }

    if ($columnExists($pdo, 'sr_community_attachment_download_logs', 'account_id')) {
        $attachmentDownloadSetSql = $columnExists($pdo, 'sr_community_attachment_download_logs', 'coupon_dedupe_key')
            ? "account_id = NULL,\n                 coupon_dedupe_key = ''"
            : 'account_id = NULL';
        $stmt = $pdo->prepare(
            'UPDATE sr_community_attachment_download_logs
             SET ' . $attachmentDownloadSetSql . '
             WHERE account_id = :account_id'
        );
        $stmt->execute(['account_id' => $accountId]);
        $attachmentDownloadLogAnonymizedCount = $stmt->rowCount();
    }

    if ($columnExists($pdo, 'sr_community_post_read_payment_logs', 'account_id')) {
        $postReadPaymentSetSql = $columnExists($pdo, 'sr_community_post_read_payment_logs', 'coupon_dedupe_key')
            ? "account_id = NULL,\n                 coupon_dedupe_key = ''"
            : 'account_id = NULL';
        $stmt = $pdo->prepare(
            'UPDATE sr_community_post_read_payment_logs
             SET ' . $postReadPaymentSetSql . '
             WHERE account_id = :account_id'
        );
        $stmt->execute(['account_id' => $accountId]);
        $postReadPaymentLogAnonymizedCount = $stmt->rowCount();
    }

    if (
        $columnExists($pdo, 'sr_community_report_auto_actions', 'reviewer_account_id')
        && $columnExists($pdo, 'sr_community_report_auto_actions', 'target_hidden_by_account_id')
    ) {
        $stmt = $pdo->prepare(
            'UPDATE sr_community_report_auto_actions
             SET reviewer_account_id = CASE WHEN reviewer_account_id = :reviewer_account_id THEN NULL ELSE reviewer_account_id END,
                 target_hidden_by_account_id = CASE WHEN target_hidden_by_account_id = :target_hidden_by_account_id THEN NULL ELSE target_hidden_by_account_id END
             WHERE reviewer_account_id = :matched_reviewer_account_id
                OR target_hidden_by_account_id = :matched_target_hidden_by_account_id'
        );
        $stmt->execute([
            'reviewer_account_id' => $accountId,
            'target_hidden_by_account_id' => $accountId,
            'matched_reviewer_account_id' => $accountId,
            'matched_target_hidden_by_account_id' => $accountId,
        ]);
        $reportAutoActionActorLinksCleared = $stmt->rowCount();
    }

    if ($columnExists($pdo, 'sr_community_hidden_targets', 'hidden_by_account_id')) {
        $stmt = $pdo->prepare(
            'UPDATE sr_community_hidden_targets
             SET hidden_by_account_id = NULL
             WHERE hidden_by_account_id = :hidden_by_account_id'
        );
        $stmt->execute(['hidden_by_account_id' => $accountId]);
        $hiddenTargetActorLinksCleared = $stmt->rowCount();
    }

    if (
        $columnExists($pdo, 'sr_community_account_guard_events', 'account_id')
        && $columnExists($pdo, 'sr_community_account_guard_events', 'reviewer_account_id')
    ) {
        $stmt = $pdo->prepare(
            'UPDATE sr_community_account_guard_events
             SET account_id = CASE WHEN account_id = :event_account_id THEN 0 ELSE account_id END,
                 reviewer_account_id = CASE WHEN reviewer_account_id = :event_reviewer_account_id THEN NULL ELSE reviewer_account_id END
             WHERE account_id = :matched_event_account_id
                OR reviewer_account_id = :matched_event_reviewer_account_id'
        );
        $stmt->execute([
            'event_account_id' => $accountId,
            'event_reviewer_account_id' => $accountId,
            'matched_event_account_id' => $accountId,
            'matched_event_reviewer_account_id' => $accountId,
        ]);
        $accountGuardEventAnonymizedCount = $stmt->rowCount();
    }

    if (
        $columnExists($pdo, 'sr_community_account_guards', 'account_id')
        && $columnExists($pdo, 'sr_community_account_guards', 'reviewer_account_id')
        && $columnExists($pdo, 'sr_community_account_guards', 'active_guard_uid')
    ) {
        $stmt = $pdo->prepare(
            'UPDATE sr_community_account_guards
             SET active_guard_uid = CASE WHEN account_id = :guard_active_account_id THEN NULL ELSE active_guard_uid END,
                 account_id = CASE WHEN account_id = :guard_account_id THEN 0 ELSE account_id END,
                 reviewer_account_id = CASE WHEN reviewer_account_id = :guard_reviewer_account_id THEN NULL ELSE reviewer_account_id END
             WHERE account_id = :matched_guard_account_id
                OR reviewer_account_id = :matched_guard_reviewer_account_id'
        );
        $stmt->execute([
            'guard_active_account_id' => $accountId,
            'guard_account_id' => $accountId,
            'guard_reviewer_account_id' => $accountId,
            'matched_guard_account_id' => $accountId,
            'matched_guard_reviewer_account_id' => $accountId,
        ]);
        $accountGuardAnonymizedCount = $stmt->rowCount();
    }

    if ($columnExists($pdo, 'sr_community_asset_recovery_failures', 'account_id')) {
        $hasRecoveryActorColumn = $columnExists($pdo, 'sr_community_asset_recovery_failures', 'actor_account_id');
        $hasRecoveryContextColumn = $columnExists($pdo, 'sr_community_asset_recovery_failures', 'operation_context_json');
        $setParts = ['account_id = 0'];
        if ($hasRecoveryActorColumn) {
            $setParts[] = 'actor_account_id = CASE WHEN actor_account_id = :actor_account_id THEN NULL ELSE actor_account_id END';
        }
        if ($hasRecoveryContextColumn) {
            $setParts[] = "operation_context_json = '{}'";
        }
        $stmt = $pdo->prepare(
            'UPDATE sr_community_asset_recovery_failures
             SET ' . implode(",\n                 ", $setParts) . '
             WHERE account_id = :account_id'
        );
        $params = ['account_id' => $accountId];
        if ($hasRecoveryActorColumn) {
            $params['actor_account_id'] = $accountId;
        }
        $stmt->execute($params);
        $assetRecoveryFailureAnonymizedCount = $stmt->rowCount();

        if ($hasRecoveryActorColumn) {
            $actorSetParts = ['actor_account_id = NULL'];
            if ($hasRecoveryContextColumn) {
                $actorSetParts[] = "operation_context_json = '{}'";
            }
            $stmt = $pdo->prepare(
                'UPDATE sr_community_asset_recovery_failures
                 SET ' . implode(",\n                     ", $actorSetParts) . '
                 WHERE actor_account_id = :actor_account_id'
            );
            $stmt->execute(['actor_account_id' => $accountId]);
            $assetRecoveryFailureActorLinksCleared = $stmt->rowCount();
        }
    }

    return [
        'cleaned' => true,
        'event_type' => (string) ($context['event_type'] ?? ''),
        'community_member_nickname_deleted' => $deleted,
        'community_account_level_deleted' => (bool) ($levelCleanup['account_level_deleted'] ?? false),
        'community_level_log_deleted_count' => (int) ($levelCleanup['level_log_deleted_count'] ?? 0),
        'community_access_entitlement_anonymized_count' => $entitlementCount,
        'community_author_snapshot_anonymized_count' => $authorSnapshotAnonymizedCount,
        'community_post_extra_values_anonymized_count' => $postExtraValuesAnonymizedCount,
        'community_post_field_values_anonymized_count' => $postFieldValuesAnonymizedCount,
        'community_post_draft_deleted_count' => $postDraftDeletedCount,
        'community_submission_consent_anonymized_count' => $submissionConsentAnonymizedCount,
        'community_attachment_download_log_anonymized_count' => $attachmentDownloadLogAnonymizedCount,
        'community_post_read_payment_log_anonymized_count' => $postReadPaymentLogAnonymizedCount,
        'community_report_auto_action_actor_links_cleared' => $reportAutoActionActorLinksCleared,
        'community_hidden_target_actor_links_cleared' => $hiddenTargetActorLinksCleared,
        'community_account_guard_event_anonymized_count' => $accountGuardEventAnonymizedCount,
        'community_account_guard_anonymized_count' => $accountGuardAnonymizedCount,
        'community_asset_recovery_failure_anonymized_count' => $assetRecoveryFailureAnonymizedCount,
        'community_asset_recovery_failure_actor_links_cleared' => $assetRecoveryFailureActorLinksCleared,
        'community_series_scrap_deleted_count' => $seriesScrapDeletedCount,
        'community_series_metadata_anonymized_count' => $seriesMetadataCount,
    ];
};
