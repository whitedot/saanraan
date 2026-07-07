<?php

declare(strict_types=1);

function sr_community_report_reason_keys(): array
{
    return ['spam', 'abuse', 'personal_info', 'illegal', 'other'];
}

function sr_community_report_reason_label(string $reasonKey): string
{
    $labels = [
        'spam' => sr_t('community::report.reason.spam'),
        'abuse' => sr_t('community::report.reason.abuse'),
        'personal_info' => sr_t('community::report.reason.personal_info'),
        'illegal' => sr_t('community::report.reason.illegal'),
        'other' => sr_t('community::report.reason.other'),
    ];

    return (string) ($labels[$reasonKey] ?? $reasonKey);
}

function sr_community_report_target_type_label(string $targetType): string
{
    $labels = [
        'post' => sr_t('community::ui.text.0b138cfe'),
        'comment' => sr_t('community::ui.text.c9fff683'),
        'message' => sr_t('community::ui.text.919bd592'),
    ];

    return (string) ($labels[$targetType] ?? $targetType);
}

function sr_community_report_statuses(): array
{
    return ['open', 'reviewing', 'resolved', 'dismissed'];
}

function sr_community_report_auto_action_target_types(): array
{
    return ['post', 'comment'];
}

function sr_community_report_auto_action_statuses(): array
{
    return ['active', 'confirmed', 'released', 'skipped', 'failed'];
}

function sr_community_report_auto_action_status_label(string $status): string
{
    return [
        'active' => '자동 숨김',
        'confirmed' => '확정',
        'released' => '해제',
        'skipped' => '건너뜀',
        'failed' => '실패',
    ][$status] ?? $status;
}

function sr_community_report_auto_action_review_options(): array
{
    return [
        'none' => '변경 안 함',
        'confirmed' => '자동조치 확정',
        'released' => '자동조치 해제',
    ];
}

function sr_community_report_auto_action_terminal_statuses(): array
{
    return ['confirmed', 'released', 'skipped', 'failed'];
}

function sr_community_report_auto_action_status_is_terminal(string $status): bool
{
    return in_array($status, sr_community_report_auto_action_terminal_statuses(), true);
}

function sr_community_report_auto_action_active_target_uid(string $targetType, int $targetId): string
{
    if ($targetId < 1 || !in_array($targetType, sr_community_report_auto_action_target_types(), true)) {
        return '';
    }

    return $targetType . ':' . (string) $targetId;
}

function sr_community_report_active_auto_action(PDO $pdo, string $targetType, int $targetId, bool $lock = false): ?array
{
    $activeTargetUid = sr_community_report_auto_action_active_target_uid($targetType, $targetId);
    if ($activeTargetUid === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_community_report_auto_actions
         WHERE active_target_uid = :active_target_uid
           AND status = \'active\'
         LIMIT 1' . ($lock ? sr_community_report_auto_action_lock_suffix($pdo) : '')
    );
    $stmt->execute(['active_target_uid' => $activeTargetUid]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_report_auto_actions_by_targets(PDO $pdo, array $reports): array
{
    $uids = [];
    foreach ($reports as $report) {
        if (!is_array($report)) {
            continue;
        }
        $uid = sr_community_report_auto_action_active_target_uid((string) ($report['target_type'] ?? ''), (int) ($report['target_id'] ?? 0));
        if ($uid !== '') {
            $uids[$uid] = $uid;
        }
    }
    if ($uids === []) {
        return [];
    }

    [$condition, $params] = sr_admin_sql_in_condition('active_target_uid', 'active_target_uid', array_values($uids));
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_community_report_auto_actions
         WHERE status = \'active\'
           AND ' . $condition
    );
    $stmt->execute($params);

    $rowsByUid = [];
    foreach ($stmt->fetchAll() as $row) {
        if (is_array($row) && (string) ($row['active_target_uid'] ?? '') !== '') {
            $rowsByUid[(string) $row['active_target_uid']] = $row;
        }
    }

    return $rowsByUid;
}

function sr_community_report_auto_action_transition(PDO $pdo, int $autoActionId, string $status, array $context = []): bool
{
    if ($autoActionId < 1 || !in_array($status, sr_community_report_auto_action_statuses(), true)) {
        return false;
    }

    $now = function_exists('sr_now') ? sr_now() : date('Y-m-d H:i:s');
    $setParts = [
        'status = :status',
        'updated_at = :updated_at',
    ];
    $params = [
        'id' => $autoActionId,
        'status' => $status,
        'updated_at' => $now,
    ];

    if (sr_community_report_auto_action_status_is_terminal($status)) {
        $setParts[] = 'active_target_uid = NULL';
    }

    $reviewerAccountId = (int) ($context['reviewer_account_id'] ?? 0);
    if ($reviewerAccountId > 0) {
        $setParts[] = 'reviewer_account_id = :reviewer_account_id';
        $setParts[] = 'reviewed_at = :reviewed_at';
        $params['reviewer_account_id'] = $reviewerAccountId;
        $params['reviewed_at'] = $now;
    }

    if ($status === 'released') {
        $setParts[] = 'released_at = :released_at';
        $params['released_at'] = $now;
    }

    if (array_key_exists('failure_reason', $context)) {
        $failureReason = preg_replace('/[^a-z0-9_:. -]/i', '', (string) $context['failure_reason']);
        $setParts[] = 'failure_reason = :failure_reason';
        $params['failure_reason'] = substr(trim((string) $failureReason), 0, 80);
    }

    if (array_key_exists('metadata', $context)) {
        $metadata = is_array($context['metadata']) ? $context['metadata'] : [];
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $setParts[] = 'metadata_json = :metadata_json';
        $params['metadata_json'] = is_string($encodedMetadata) ? $encodedMetadata : '{}';
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_report_auto_actions
         SET ' . implode(', ', $setParts) . '
         WHERE id = :id'
    );
    $stmt->execute($params);

    return $stmt->rowCount() > 0;
}

function sr_community_report_auto_action_json(array $data): string
{
    $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return is_string($encoded) ? $encoded : '{}';
}

function sr_community_report_auto_action_lock_suffix(PDO $pdo): string
{
    try {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    } catch (Throwable) {
        return ' FOR UPDATE';
    }
}

function sr_community_report_auto_action_settings_snapshot(array $settings): array
{
    return [
        'enabled' => !empty($settings['report_auto_action_enabled']),
        'threshold' => min(100, max(2, (int) ($settings['report_auto_action_threshold'] ?? 5))),
        'window_days' => min(365, max(0, (int) ($settings['report_auto_action_window_days'] ?? 0))),
        'public_mode' => in_array((string) ($settings['report_auto_action_public_mode'] ?? 'exclude'), ['exclude', 'placeholder'], true)
            ? (string) $settings['report_auto_action_public_mode']
            : 'exclude',
    ];
}

function sr_community_report_auto_action_cutoff(PDO $pdo, string $targetType, int $targetId): string
{
    $reportStmt = $pdo->prepare(
        "SELECT MAX(reviewed_at) AS cutoff_at
         FROM sr_community_reports
         WHERE target_type = :target_type
           AND target_id = :target_id
           AND status IN ('resolved', 'dismissed')
           AND reviewed_at IS NOT NULL"
    );
    $reportStmt->execute([
        'target_type' => $targetType,
        'target_id' => $targetId,
    ]);
    $reportCutoff = (string) ($reportStmt->fetchColumn() ?: '');

    $autoStmt = $pdo->prepare(
        "SELECT MAX(COALESCE(NULLIF(released_at, ''), NULLIF(reviewed_at, ''), updated_at)) AS cutoff_at
         FROM sr_community_report_auto_actions
         WHERE target_type = :target_type
           AND target_id = :target_id
           AND status IN ('confirmed', 'released', 'skipped', 'failed')"
    );
    $autoStmt->execute([
        'target_type' => $targetType,
        'target_id' => $targetId,
    ]);
    $autoCutoff = (string) ($autoStmt->fetchColumn() ?: '');

    return max($reportCutoff, $autoCutoff);
}

function sr_community_report_auto_action_report_counts(PDO $pdo, string $targetType, int $targetId, int $windowDays): array
{
    $where = 'target_type = :target_type AND target_id = :target_id';
    $params = [
        'target_type' => $targetType,
        'target_id' => $targetId,
    ];
    $cutoff = sr_community_report_auto_action_cutoff($pdo, $targetType, $targetId);
    if ($cutoff !== '') {
        $where .= ' AND created_at > :cutoff';
        $params['cutoff'] = $cutoff;
    }
    if ($windowDays > 0) {
        $now = function_exists('sr_now') ? sr_now() : date('Y-m-d H:i:s');
        $createdAfter = (new DateTimeImmutable($now))->modify('-' . (string) $windowDays . ' days')->format('Y-m-d H:i:s');
        $where .= ' AND created_at >= :created_after';
        $params['created_after'] = $createdAfter;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total_report_count,
                COUNT(DISTINCT reporter_account_id) AS total_reporter_count,
                COUNT(CASE WHEN status NOT IN ('open', 'reviewing') THEN 1 END) AS excluded_report_count,
                COUNT(DISTINCT CASE WHEN status NOT IN ('open', 'reviewing') THEN reporter_account_id ELSE NULL END) AS excluded_reporter_count,
                COUNT(DISTINCT CASE WHEN status IN ('open', 'reviewing') THEN reporter_account_id ELSE NULL END) AS eligible_reporter_count
         FROM sr_community_reports
         WHERE " . $where
    );
    $stmt->execute($params);
    $row = $stmt->fetch();

    return [
        'total_report_count' => is_array($row) ? (int) ($row['total_report_count'] ?? 0) : 0,
        'total_reporter_count' => is_array($row) ? (int) ($row['total_reporter_count'] ?? 0) : 0,
        'eligible_reporter_count' => is_array($row) ? (int) ($row['eligible_reporter_count'] ?? 0) : 0,
        'excluded_reporter_count' => is_array($row) ? (int) ($row['excluded_reporter_count'] ?? 0) : 0,
        'excluded_report_count' => is_array($row) ? (int) ($row['excluded_report_count'] ?? 0) : 0,
        'cutoff' => $cutoff,
    ];
}

function sr_community_report_auto_action_locked_target(PDO $pdo, string $targetType, int $targetId): ?array
{
    if ($targetType === 'post') {
        $stmt = $pdo->prepare(
            'SELECT p.id, p.status, ' . sr_community_hidden_target_select_columns('hidden_meta') . ', p.board_id,
                    b.status AS board_status
             FROM sr_community_posts p
             INNER JOIN sr_community_boards b ON b.id = p.board_id
             ' . sr_community_hidden_target_join_sql('post', 'p', 'hidden_meta') . '
             WHERE p.id = :id
             LIMIT 1' . sr_community_report_auto_action_lock_suffix($pdo)
        );
        $stmt->execute(['id' => $targetId]);
        $target = $stmt->fetch();
        if (!is_array($target)) {
            return null;
        }

        $target['auto_action_eligible'] = (string) ($target['status'] ?? '') === 'published'
            && (string) ($target['board_status'] ?? '') === 'enabled';
        return $target;
    }

    if ($targetType === 'comment') {
        $stmt = $pdo->prepare(
            'SELECT c.id, c.status, ' . sr_community_hidden_target_select_columns('hidden_meta') . ', c.post_id,
                    p.status AS post_status,
                    b.status AS board_status
             FROM sr_community_comments c
             INNER JOIN sr_community_posts p ON p.id = c.post_id
             INNER JOIN sr_community_boards b ON b.id = p.board_id
             ' . sr_community_hidden_target_join_sql('comment', 'c', 'hidden_meta') . '
             WHERE c.id = :id
             LIMIT 1' . sr_community_report_auto_action_lock_suffix($pdo)
        );
        $stmt->execute(['id' => $targetId]);
        $target = $stmt->fetch();
        if (!is_array($target)) {
            return null;
        }

        $target['auto_action_eligible'] = (string) ($target['status'] ?? '') === 'published'
            && (string) ($target['post_status'] ?? '') === 'published'
            && (string) ($target['board_status'] ?? '') === 'enabled';
        return $target;
    }

    return null;
}

function sr_community_release_report_auto_action_target(PDO $pdo, array $autoAction): array
{
    $targetType = (string) ($autoAction['target_type'] ?? '');
    $targetId = (int) ($autoAction['target_id'] ?? 0);
    if (!in_array($targetType, sr_community_report_auto_action_target_types(), true) || $targetId < 1) {
        return ['restored' => false, 'reason' => 'invalid_target'];
    }

    $target = sr_community_report_auto_action_locked_target($pdo, $targetType, $targetId);
    if (!is_array($target)) {
        return ['restored' => false, 'reason' => 'target_not_found'];
    }
    if ((string) ($target['status'] ?? '') !== 'hidden') {
        return ['restored' => false, 'reason' => 'target_already_changed'];
    }
    if ((string) ($target['hidden_reason'] ?? '') !== 'report_threshold') {
        return ['restored' => false, 'reason' => 'target_not_auto_hidden'];
    }
    if ((int) ($target['hidden_by_account_id'] ?? 0) > 0) {
        return ['restored' => false, 'reason' => 'target_hidden_by_admin'];
    }
    $hiddenAtSnapshot = (string) ($autoAction['target_hidden_at_snapshot'] ?? '');
    if ($hiddenAtSnapshot !== '' && (string) ($target['hidden_at'] ?? '') !== $hiddenAtSnapshot) {
        return ['restored' => false, 'reason' => 'target_hidden_fingerprint_changed'];
    }

    $restoreStatus = (string) ($target['hidden_before_status'] ?? '');
    if (!in_array($restoreStatus, ['published', 'draft'], true)) {
        $restoreStatus = 'published';
    }

    if ($targetType === 'post') {
        sr_community_update_post_status($pdo, $targetId, $restoreStatus);
        $updatedAttachmentCount = $restoreStatus === 'published'
            ? sr_community_update_post_attachments_status($pdo, $targetId, 'active')
            : 0;

        return [
            'restored' => true,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'restore_status' => $restoreStatus,
            'updated_attachment_count' => $updatedAttachmentCount,
        ];
    }

    sr_community_update_comment_status($pdo, $targetId, $restoreStatus);

    return [
        'restored' => true,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'restore_status' => $restoreStatus,
    ];
}

function sr_community_report_auto_action_insert(PDO $pdo, array $data): int
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_report_auto_actions
            (target_type, target_id, active_target_uid, source_report_id, action_key, status,
             target_before_status, target_hidden_at_snapshot, target_hidden_reason, target_hidden_by_account_id,
             threshold_value, total_reporter_count, eligible_reporter_count, excluded_reporter_count, excluded_report_count,
             abuse_guard_summary_json, settings_snapshot_json, failure_reason, metadata_json,
             applied_at, released_at, reviewed_at, reviewer_account_id, created_at, updated_at)
         VALUES
            (:target_type, :target_id, :active_target_uid, :source_report_id, :action_key, :status,
             :target_before_status, :target_hidden_at_snapshot, :target_hidden_reason, :target_hidden_by_account_id,
             :threshold_value, :total_reporter_count, :eligible_reporter_count, :excluded_reporter_count, :excluded_report_count,
             :abuse_guard_summary_json, :settings_snapshot_json, :failure_reason, :metadata_json,
             :applied_at, NULL, NULL, NULL, :created_at, :updated_at)'
    );
    $stmt->execute([
        'target_type' => (string) $data['target_type'],
        'target_id' => (int) $data['target_id'],
        'active_target_uid' => (string) ($data['active_target_uid'] ?? '') !== '' ? (string) $data['active_target_uid'] : null,
        'source_report_id' => (int) ($data['source_report_id'] ?? 0) > 0 ? (int) $data['source_report_id'] : null,
        'action_key' => (string) ($data['action_key'] ?? ''),
        'status' => (string) ($data['status'] ?? 'active'),
        'target_before_status' => (string) ($data['target_before_status'] ?? ''),
        'target_hidden_at_snapshot' => (string) ($data['target_hidden_at_snapshot'] ?? '') !== '' ? (string) $data['target_hidden_at_snapshot'] : null,
        'target_hidden_reason' => (string) ($data['target_hidden_reason'] ?? ''),
        'target_hidden_by_account_id' => (int) ($data['target_hidden_by_account_id'] ?? 0) > 0 ? (int) $data['target_hidden_by_account_id'] : null,
        'threshold_value' => (int) ($data['threshold_value'] ?? 0),
        'total_reporter_count' => (int) ($data['total_reporter_count'] ?? 0),
        'eligible_reporter_count' => (int) ($data['eligible_reporter_count'] ?? 0),
        'excluded_reporter_count' => (int) ($data['excluded_reporter_count'] ?? 0),
        'excluded_report_count' => (int) ($data['excluded_report_count'] ?? 0),
        'abuse_guard_summary_json' => (string) ($data['abuse_guard_summary_json'] ?? '{}'),
        'settings_snapshot_json' => (string) ($data['settings_snapshot_json'] ?? '{}'),
        'failure_reason' => (string) ($data['failure_reason'] ?? ''),
        'metadata_json' => (string) ($data['metadata_json'] ?? '{}'),
        'applied_at' => (string) ($data['applied_at'] ?? '') !== '' ? (string) $data['applied_at'] : null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_community_maybe_apply_report_auto_action(PDO $pdo, int $sourceReportId, array $settings): array
{
    $snapshot = sr_community_report_auto_action_settings_snapshot($settings);
    if (empty($snapshot['enabled']) || $sourceReportId < 1) {
        return ['status' => 'disabled'];
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_community_reports WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $sourceReportId]);
    $report = $stmt->fetch();
    if (!is_array($report)) {
        return ['status' => 'source_report_not_found'];
    }

    $targetType = (string) ($report['target_type'] ?? '');
    $targetId = (int) ($report['target_id'] ?? 0);
    if (!in_array($targetType, sr_community_report_auto_action_target_types(), true) || $targetId < 1) {
        return ['status' => 'target_type_excluded', 'target_type' => $targetType, 'target_id' => $targetId];
    }

    $threshold = (int) $snapshot['threshold'];
    $windowDays = (int) $snapshot['window_days'];
    $activeTargetUid = sr_community_report_auto_action_active_target_uid($targetType, $targetId);
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $target = sr_community_report_auto_action_locked_target($pdo, $targetType, $targetId);
        $counts = sr_community_report_auto_action_report_counts($pdo, $targetType, $targetId, $windowDays);
        if ((int) $counts['eligible_reporter_count'] < $threshold) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return [
                'status' => 'below_threshold',
                'target_type' => $targetType,
                'target_id' => $targetId,
                'eligible_reporter_count' => (int) $counts['eligible_reporter_count'],
                'threshold' => $threshold,
            ];
        }

        $activeStmt = $pdo->prepare(
            'SELECT id
             FROM sr_community_report_auto_actions
             WHERE active_target_uid = :active_target_uid
             LIMIT 1' . sr_community_report_auto_action_lock_suffix($pdo)
        );
        $activeStmt->execute(['active_target_uid' => $activeTargetUid]);
        $activeRow = $activeStmt->fetch();
        if (is_array($activeRow)) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return [
                'status' => 'active_exists',
                'auto_action_id' => (int) ($activeRow['id'] ?? 0),
                'target_type' => $targetType,
                'target_id' => $targetId,
            ];
        }

        if (!is_array($target)) {
            $autoActionId = sr_community_report_auto_action_insert($pdo, [
                'target_type' => $targetType,
                'target_id' => $targetId,
                'source_report_id' => $sourceReportId,
                'action_key' => 'hide_target',
                'status' => 'skipped',
                'threshold_value' => $threshold,
                'total_reporter_count' => (int) $counts['total_reporter_count'],
                'eligible_reporter_count' => (int) $counts['eligible_reporter_count'],
                'excluded_reporter_count' => (int) $counts['excluded_reporter_count'],
                'excluded_report_count' => (int) $counts['excluded_report_count'],
                'settings_snapshot_json' => sr_community_report_auto_action_json($snapshot),
                'failure_reason' => 'target_not_found',
                'metadata_json' => sr_community_report_auto_action_json(['source' => 'report_threshold']),
            ]);
            if ($startedTransaction) {
                $pdo->commit();
            }
            return ['status' => 'skipped', 'reason' => 'target_not_found', 'auto_action_id' => $autoActionId];
        }

        $targetBeforeStatus = (string) ($target['status'] ?? '');
        if (empty($target['auto_action_eligible'])) {
            $autoActionId = sr_community_report_auto_action_insert($pdo, [
                'target_type' => $targetType,
                'target_id' => $targetId,
                'source_report_id' => $sourceReportId,
                'action_key' => 'hide_target',
                'status' => 'skipped',
                'target_before_status' => $targetBeforeStatus,
                'target_hidden_at_snapshot' => (string) ($target['hidden_at'] ?? ''),
                'target_hidden_reason' => (string) ($target['hidden_reason'] ?? ''),
                'target_hidden_by_account_id' => (int) ($target['hidden_by_account_id'] ?? 0),
                'threshold_value' => $threshold,
                'total_reporter_count' => (int) $counts['total_reporter_count'],
                'eligible_reporter_count' => (int) $counts['eligible_reporter_count'],
                'excluded_reporter_count' => (int) $counts['excluded_reporter_count'],
                'excluded_report_count' => (int) $counts['excluded_report_count'],
                'settings_snapshot_json' => sr_community_report_auto_action_json($snapshot),
                'failure_reason' => 'target_not_eligible',
                'metadata_json' => sr_community_report_auto_action_json([
                    'source' => 'report_threshold',
                    'target_status' => $targetBeforeStatus,
                    'board_status' => (string) ($target['board_status'] ?? ''),
                    'post_status' => (string) ($target['post_status'] ?? ''),
                ]),
            ]);
            if ($startedTransaction) {
                $pdo->commit();
            }
            return ['status' => 'skipped', 'reason' => 'target_not_eligible', 'auto_action_id' => $autoActionId];
        }

        $appliedAt = sr_now();
        $autoActionId = sr_community_report_auto_action_insert($pdo, [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'active_target_uid' => $activeTargetUid,
            'source_report_id' => $sourceReportId,
            'action_key' => 'hide_target',
            'status' => 'active',
            'target_before_status' => $targetBeforeStatus,
            'target_hidden_at_snapshot' => (string) ($target['hidden_at'] ?? ''),
            'target_hidden_reason' => (string) ($target['hidden_reason'] ?? ''),
            'target_hidden_by_account_id' => (int) ($target['hidden_by_account_id'] ?? 0),
            'threshold_value' => $threshold,
            'total_reporter_count' => (int) $counts['total_reporter_count'],
            'eligible_reporter_count' => (int) $counts['eligible_reporter_count'],
            'excluded_reporter_count' => (int) $counts['excluded_reporter_count'],
            'excluded_report_count' => (int) $counts['excluded_report_count'],
            'abuse_guard_summary_json' => sr_community_report_auto_action_json([
                'distinct_reporters' => (int) $counts['eligible_reporter_count'],
                'excluded_report_count' => (int) $counts['excluded_report_count'],
                'cutoff' => (string) ($counts['cutoff'] ?? ''),
            ]),
            'settings_snapshot_json' => sr_community_report_auto_action_json($snapshot),
            'metadata_json' => sr_community_report_auto_action_json([
                'source' => 'report_threshold',
                'source_report_id' => $sourceReportId,
            ]),
            'applied_at' => $appliedAt,
        ]);

        $hiddenOptions = [
            'hidden_reason' => 'report_threshold',
            'hidden_note' => 'Automatically hidden after report threshold was reached.',
            'hidden_by_account_id' => null,
        ];
        if ($targetType === 'post') {
            sr_community_update_post_status($pdo, $targetId, 'hidden', $hiddenOptions);
            sr_community_update_post_attachments_status($pdo, $targetId, 'hidden');
        } else {
            sr_community_update_comment_status($pdo, $targetId, 'hidden', $hiddenOptions);
        }

        $freshTarget = sr_community_report_auto_action_locked_target($pdo, $targetType, $targetId);
        if (!is_array($freshTarget) || (string) ($freshTarget['status'] ?? '') !== 'hidden' || (string) ($freshTarget['hidden_reason'] ?? '') !== 'report_threshold') {
            sr_community_report_auto_action_transition($pdo, $autoActionId, 'failed', [
                'failure_reason' => 'hidden_readback_failed',
                'metadata' => ['source' => 'report_threshold'],
            ]);
            if ($startedTransaction) {
                $pdo->commit();
            }
            return [
                'status' => 'failed',
                'reason' => 'hidden_readback_failed',
                'auto_action_id' => $autoActionId,
                'target_type' => $targetType,
                'target_id' => $targetId,
            ];
        }
        $snapshotStmt = $pdo->prepare(
            'UPDATE sr_community_report_auto_actions
             SET target_hidden_at_snapshot = :target_hidden_at_snapshot,
                 target_hidden_reason = :target_hidden_reason,
                 target_hidden_by_account_id = :target_hidden_by_account_id,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $snapshotStmt->execute([
            'target_hidden_at_snapshot' => (string) ($freshTarget['hidden_at'] ?? '') !== '' ? (string) $freshTarget['hidden_at'] : null,
            'target_hidden_reason' => (string) ($freshTarget['hidden_reason'] ?? ''),
            'target_hidden_by_account_id' => (int) ($freshTarget['hidden_by_account_id'] ?? 0) > 0 ? (int) $freshTarget['hidden_by_account_id'] : null,
            'updated_at' => sr_now(),
            'id' => $autoActionId,
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        }
        return [
            'status' => 'applied',
            'auto_action_id' => $autoActionId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'eligible_reporter_count' => (int) $counts['eligible_reporter_count'],
            'threshold' => $threshold,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_community_report_target_action_options(string $targetType): array
{
    if ($targetType === 'post') {
        return [
            'none' => '대상 조치 없음',
            'hide_post' => '게시글 숨김',
            'delete_post' => '게시글 삭제',
            'hide_post_suspend_publisher' => '게시글 숨김+게시자 정지',
            'delete_post_suspend_publisher' => '게시글 삭제+게시자 정지',
            'suspend_reported_account' => '게시자 정지',
        ];
    }
    if ($targetType === 'comment') {
        return [
            'none' => '대상 조치 없음',
            'hide_comment' => '댓글 숨김',
            'delete_comment' => '댓글 삭제',
            'hide_comment_suspend_publisher' => '댓글 숨김+게시자 정지',
            'delete_comment_suspend_publisher' => '댓글 삭제+게시자 정지',
            'suspend_reported_account' => '게시자 정지',
        ];
    }
    if ($targetType === 'message') {
        return [
            'none' => '대상 조치 없음',
            'suspend_reported_account' => '게시자 정지',
        ];
    }

    return ['none' => '대상 조치 없음'];
}

function sr_community_report_batch_target_action_options(): array
{
    return [
        'none' => '대상 조치 없음',
        'hide_target' => '게시글/댓글 숨김',
        'delete_target' => '게시글/댓글 삭제',
        'hide_target_suspend_publisher' => '게시글/댓글 숨김+게시자 정지',
        'delete_target_suspend_publisher' => '게시글/댓글 삭제+게시자 정지',
        'suspend_reported_account' => '게시자 정지',
    ];
}

function sr_community_report_batch_target_action_for_report(string $batchActionKey, string $targetType): string
{
    if ($batchActionKey === '' || $batchActionKey === 'none') {
        return 'none';
    }

    if ($batchActionKey === 'suspend_reported_account') {
        return 'suspend_reported_account';
    }

    if ($batchActionKey === 'hide_target') {
        if ($targetType === 'post') {
            return 'hide_post';
        }
        if ($targetType === 'comment') {
            return 'hide_comment';
        }
    }

    if ($batchActionKey === 'hide_target_suspend_publisher') {
        if ($targetType === 'post') {
            return 'hide_post_suspend_publisher';
        }
        if ($targetType === 'comment') {
            return 'hide_comment_suspend_publisher';
        }
    }

    if ($batchActionKey === 'delete_target') {
        if ($targetType === 'post') {
            return 'delete_post';
        }
        if ($targetType === 'comment') {
            return 'delete_comment';
        }
    }

    if ($batchActionKey === 'delete_target_suspend_publisher') {
        if ($targetType === 'post') {
            return 'delete_post_suspend_publisher';
        }
        if ($targetType === 'comment') {
            return 'delete_comment_suspend_publisher';
        }
    }

    return '';
}

function sr_community_report_reporter_action_options(): array
{
    return [
        'none' => '신고자 조치 없음',
        'suspend_reporter_account' => '허위신고자 정지',
    ];
}

function sr_community_report_status_policy_descriptions(): array
{
    return [
        'open' => '신고가 접수된 상태입니다. 대상 조치는 실행하지 않습니다.',
        'reviewing' => '운영자가 내용을 확인 중인 상태입니다. 대상 조치는 실행하지 않습니다.',
        'resolved' => '검토를 마친 상태입니다. 필요한 경우 대상 조치를 함께 실행할 수 있습니다.',
        'dismissed' => '제재 없이 기각한 상태입니다. 대상 조치는 실행하지 않으며 이미 적용된 조치를 되돌리지 않습니다.',
    ];
}

function sr_community_report_target_action_policy_error(string $status, string $actionKey): string
{
    if ($actionKey === '' || $actionKey === 'none') {
        return '';
    }

    if ($status !== 'resolved') {
        return '대상 조치는 신고 상태를 처리 완료로 저장할 때만 실행할 수 있습니다.';
    }

    return '';
}

function sr_community_report_reporter_action_policy_error(string $status, string $actionKey): string
{
    if ($actionKey === '' || $actionKey === 'none') {
        return '';
    }

    if ($status !== 'dismissed') {
        return '허위신고자 조치는 신고 상태를 기각으로 저장할 때만 실행할 수 있습니다.';
    }

    return '';
}

function sr_community_report_account_label(?string $displayName, int $accountId, ?string $accountStatus = null, ?string $nickname = null, ?array $communitySettings = null): string
{
    if (sr_community_nickname_status_blocks_identity((string) $accountStatus)) {
        return sr_t('member::account.withdrawn_display_name');
    }

    $label = is_array($communitySettings)
        ? sr_community_public_display_name([
            'display_name' => (string) $displayName,
            'community_nickname' => (string) $nickname,
            'status' => (string) $accountStatus,
        ], $communitySettings)
        : trim((string) $displayName);

    if ($label !== '') {
        return $label;
    }

    return $accountId > 0 ? sr_t('community::report.account.member') : sr_t('community::report.account.unknown');
}

function sr_community_report_target(PDO $pdo, string $targetType, int $targetId, ?int $actorAccountId = null): ?array
{
    if ($targetId < 1) {
        return null;
    }

    if ($targetType === 'post') {
        $account = $actorAccountId !== null ? ['id' => $actorAccountId] : null;
        $post = sr_community_post_for_read($pdo, $targetId, $account);
        if (!is_array($post)) {
            return null;
        }

        return [
            'target_type' => 'post',
            'target_id' => (int) $post['id'],
            'reported_account_id' => (int) $post['author_account_id'],
            'post_id' => (int) $post['id'],
            'redirect_path' => '/community/post?id=' . (string) $post['id'],
        ];
    }

    if ($targetType === 'comment') {
        $account = $actorAccountId !== null ? ['id' => $actorAccountId] : null;
        $comment = sr_community_comment_for_read($pdo, $targetId, $account);
        if (!is_array($comment)) {
            return null;
        }

        return [
            'target_type' => 'comment',
            'target_id' => (int) $comment['id'],
            'reported_account_id' => (int) $comment['author_account_id'],
            'post_id' => (int) $comment['post_id'],
            'redirect_path' => '/community/post?id=' . (string) $comment['post_id'] . '#comments',
        ];
    }

    if ($actorAccountId !== null) {
        return sr_community_report_contract_target($pdo, $targetType, $targetId, $actorAccountId);
    }

    return null;
}

function sr_community_report_target_contract_helper_path(string $moduleKey, array $target): string
{
    $helpers = (string) ($target['helpers'] ?? '');
    if ($helpers === '' || preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) !== 1) {
        return '';
    }

    $path = SR_ROOT . '/modules/' . $moduleKey . '/' . $helpers;
    return is_file($path) ? $path : '';
}

function sr_community_report_target_contracts(PDO $pdo): array
{
    $contracts = [];
    foreach (sr_enabled_module_contract_files($pdo, 'report-targets.php', ['community']) as $moduleKey => $file) {
        $contractTargets = sr_load_module_contract_file($moduleKey, $file);
        if (!is_array($contractTargets)) {
            continue;
        }

        foreach ($contractTargets as $target) {
            if (!is_array($target)) {
                continue;
            }

            $targetType = (string) ($target['target_type'] ?? '');
            $resolverFunction = (string) ($target['resolver_function'] ?? '');
            if (
                preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $targetType) !== 1
                || preg_match('/\A[a-z][a-z0-9_]{1,80}\z/', $resolverFunction) !== 1
                || isset($contracts[$targetType])
            ) {
                continue;
            }

            $helperPath = sr_community_report_target_contract_helper_path((string) $moduleKey, $target);
            if ($helperPath !== '') {
                require_once $helperPath;
            }
            if (!function_exists($resolverFunction)) {
                continue;
            }

            $target['module_key'] = (string) $moduleKey;
            $contracts[$targetType] = $target;
        }
    }

    return $contracts;
}

function sr_community_report_contract_target(PDO $pdo, string $targetType, int $targetId, int $actorAccountId): ?array
{
    $contracts = sr_community_report_target_contracts($pdo);
    if (!isset($contracts[$targetType])) {
        return null;
    }

    $resolverFunction = (string) ($contracts[$targetType]['resolver_function'] ?? '');
    if ($resolverFunction === '' || !function_exists($resolverFunction)) {
        return null;
    }

    $target = $resolverFunction($pdo, $targetId, $actorAccountId);
    return is_array($target) ? $target : null;
}

function sr_community_comment_for_read(PDO $pdo, int $commentId, ?array $account): ?array
{
    if ($commentId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT c.id, c.post_id, c.author_account_id, c.status,
                p.status AS post_status,
                b.id AS board_id, b.board_group_id, b.status AS board_status, b.read_policy
         FROM sr_community_comments c
         INNER JOIN sr_community_posts p ON p.id = c.post_id
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         WHERE c.id = :id
           AND c.status = 'published'
           AND p.status = 'published'
           AND b.status = 'enabled'
         LIMIT 1"
    );
    $stmt->execute(['id' => $commentId]);
    $comment = $stmt->fetch();

    if (!is_array($comment)) {
        return null;
    }

    $board = [
        'id' => (int) $comment['board_id'],
        'board_group_id' => (int) ($comment['board_group_id'] ?? 0),
        'status' => (string) $comment['board_status'],
        'read_policy' => (string) $comment['read_policy'],
    ];

    return sr_community_account_can_read_board($pdo, $board, $account) ? $comment : null;
}

function sr_community_report_exists(PDO $pdo, int $reporterAccountId, string $targetType, int $targetId): bool
{
    if ($reporterAccountId < 1 || $targetId < 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_community_reports
         WHERE reporter_account_id = :reporter_account_id
           AND target_type = :target_type
           AND target_id = :target_id
         LIMIT 1'
    );
    $stmt->execute([
        'reporter_account_id' => $reporterAccountId,
        'target_type' => $targetType,
        'target_id' => $targetId,
    ]);

    return is_array($stmt->fetch());
}

function sr_community_create_report(PDO $pdo, array $data): int
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_reports
            (target_type, target_id, reporter_account_id, reported_account_id, reason_key, memo_text, status, reviewer_account_id, review_note, created_at, updated_at, reviewed_at)
         VALUES
            (:target_type, :target_id, :reporter_account_id, :reported_account_id, :reason_key, :memo_text, :status, NULL, NULL, :created_at, :updated_at, NULL)'
    );
    $stmt->execute([
        'target_type' => (string) $data['target_type'],
        'target_id' => (int) $data['target_id'],
        'reporter_account_id' => (int) $data['reporter_account_id'],
        'reported_account_id' => (int) $data['reported_account_id'],
        'reason_key' => (string) $data['reason_key'],
        'memo_text' => (string) $data['memo_text'],
        'status' => 'open',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_community_report_rate_limited(PDO $pdo, int $accountId, array $settings): bool
{
    $windowSeconds = min(86400, max(60, (int) ($settings['report_create_window_seconds'] ?? 300)));
    $limit = min(200, max(1, (int) ($settings['report_create_limit'] ?? 20)));

    return sr_community_rate_limits_table_exists($pdo)
        && sr_rate_limit_count($pdo, 'community.report.account', (string) $accountId, $windowSeconds) >= $limit;
}

function sr_community_record_report_rate_limit(PDO $pdo, int $accountId, array $settings): void
{
    if (!sr_community_rate_limits_table_exists($pdo)) {
        return;
    }

    $windowSeconds = min(86400, max(60, (int) ($settings['report_create_window_seconds'] ?? 300)));
    sr_rate_limit_increment($pdo, 'community.report.account', (string) $accountId, $windowSeconds);
}

function sr_community_report_query_parts(array $filters): array
{
    $where = [];
    $params = [];

    if ((int) ($filters['report_id'] ?? 0) > 0) {
        $where[] = 'r.id = :report_id';
        $params['report_id'] = (int) $filters['report_id'];
    }

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('r.status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['target_type'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('r.target_type', 'target_type', $filters['target_type']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['reason_key'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('r.reason_key', 'reason_key', $filters['reason_key']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $field = (string) ($filters['field'] ?? 'all');
        if ($field === 'reporter') {
            $where[] = '(reporter.display_name LIKE :reporter_name_keyword OR (reporter.status NOT IN (\'withdrawn\', \'anonymized\') AND reporter_nickname.nickname LIKE :reporter_nickname_keyword) OR CAST(r.reporter_account_id AS CHAR) LIKE :reporter_id_keyword)';
            $params['reporter_name_keyword'] = '%' . $keyword . '%';
            $params['reporter_nickname_keyword'] = '%' . $keyword . '%';
            $params['reporter_id_keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'reported') {
            $where[] = '(reported.display_name LIKE :reported_name_keyword OR (reported.status NOT IN (\'withdrawn\', \'anonymized\') AND reported_nickname.nickname LIKE :reported_nickname_keyword) OR CAST(r.reported_account_id AS CHAR) LIKE :reported_id_keyword)';
            $params['reported_name_keyword'] = '%' . $keyword . '%';
            $params['reported_nickname_keyword'] = '%' . $keyword . '%';
            $params['reported_id_keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'reviewer') {
            $where[] = '(reviewer.display_name LIKE :reviewer_name_keyword OR (reviewer.status NOT IN (\'withdrawn\', \'anonymized\') AND reviewer_nickname.nickname LIKE :reviewer_nickname_keyword) OR CAST(r.reviewer_account_id AS CHAR) LIKE :reviewer_id_keyword)';
            $params['reviewer_name_keyword'] = '%' . $keyword . '%';
            $params['reviewer_nickname_keyword'] = '%' . $keyword . '%';
            $params['reviewer_id_keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'memo') {
            $where[] = '(r.memo_text LIKE :memo_keyword OR r.review_note LIKE :review_note_keyword)';
            $params['memo_keyword'] = '%' . $keyword . '%';
            $params['review_note_keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'target') {
            $where[] = '(r.target_type LIKE :target_type_keyword OR CAST(r.target_id AS CHAR) LIKE :target_id_keyword)';
            $params['target_type_keyword'] = '%' . $keyword . '%';
            $params['target_id_keyword'] = '%' . $keyword . '%';
        } else {
            $where[] = '(r.memo_text LIKE :memo_keyword OR r.review_note LIKE :review_note_keyword OR reporter.display_name LIKE :reporter_keyword OR (reporter.status NOT IN (\'withdrawn\', \'anonymized\') AND reporter_nickname.nickname LIKE :reporter_nickname_keyword) OR reported.display_name LIKE :reported_keyword OR (reported.status NOT IN (\'withdrawn\', \'anonymized\') AND reported_nickname.nickname LIKE :reported_nickname_keyword) OR reviewer.display_name LIKE :reviewer_keyword OR (reviewer.status NOT IN (\'withdrawn\', \'anonymized\') AND reviewer_nickname.nickname LIKE :reviewer_nickname_keyword) OR r.target_type LIKE :target_type_keyword OR CAST(r.target_id AS CHAR) LIKE :target_id_keyword)';
            $params['memo_keyword'] = '%' . $keyword . '%';
            $params['review_note_keyword'] = '%' . $keyword . '%';
            $params['reporter_keyword'] = '%' . $keyword . '%';
            $params['reporter_nickname_keyword'] = '%' . $keyword . '%';
            $params['reported_keyword'] = '%' . $keyword . '%';
            $params['reported_nickname_keyword'] = '%' . $keyword . '%';
            $params['reviewer_keyword'] = '%' . $keyword . '%';
            $params['reviewer_nickname_keyword'] = '%' . $keyword . '%';
            $params['target_type_keyword'] = '%' . $keyword . '%';
            $params['target_id_keyword'] = '%' . $keyword . '%';
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_community_report_count(PDO $pdo, array $filters = []): int
{
    $queryParts = sr_community_report_query_parts($filters);
    $sql = 'SELECT COUNT(*) AS count_value
            FROM sr_community_reports r'
            . sr_community_report_count_join_sql($filters);
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_community_report_count_join_sql(array $filters): string
{
    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword === '') {
        return '';
    }

    $field = (string) ($filters['field'] ?? 'all');
    $usesAll = !in_array($field, ['target', 'reporter', 'reported', 'reviewer', 'memo'], true);
    $usesReporter = $field === 'reporter' || $usesAll;
    $usesReported = $field === 'reported' || $usesAll;
    $usesReviewer = $field === 'reviewer' || $usesAll;
    $joins = [];

    if ($usesReporter) {
        $joins[] = 'LEFT JOIN sr_member_accounts reporter ON reporter.id = r.reporter_account_id';
        $joins[] = 'LEFT JOIN sr_member_nicknames reporter_nickname ON reporter_nickname.account_id = reporter.id';
    }
    if ($usesReported) {
        $joins[] = 'LEFT JOIN sr_member_accounts reported ON reported.id = r.reported_account_id';
        $joins[] = 'LEFT JOIN sr_member_nicknames reported_nickname ON reported_nickname.account_id = reported.id';
    }
    if ($usesReviewer) {
        $joins[] = 'LEFT JOIN sr_member_accounts reviewer ON reviewer.id = r.reviewer_account_id';
        $joins[] = 'LEFT JOIN sr_member_nicknames reviewer_nickname ON reviewer_nickname.account_id = reviewer.id';
    }

    return $joins === [] ? '' : "\n            " . implode("\n            ", $joins);
}

function sr_community_reports(PDO $pdo, int $limit = 100, array $filters = [], int $offset = 0): array
{
    $useLimit = $limit > 0;
    if ($useLimit) {
        $limit = max(1, min(1000, $limit));
    }
    $queryParts = sr_community_report_query_parts($filters);
    $where = $queryParts['where'];
    $params = $queryParts['params'];
    $sql = 'SELECT r.id, r.target_type, r.target_id, r.reporter_account_id, r.reported_account_id, r.reason_key, r.memo_text,
                   r.status, r.reviewer_account_id, r.review_note, r.created_at, r.updated_at, r.reviewed_at,
                   CASE
                       WHEN r.target_type = \'post\' THEN target_post.title
                       WHEN r.target_type = \'comment\' THEN target_comment_post.title
                       ELSE \'\'
                   END AS target_post_title,
                   CASE
                       WHEN r.target_type = \'post\' THEN target_post.id
                       WHEN r.target_type = \'comment\' THEN target_comment_post.id
                       ELSE NULL
                   END AS target_post_id,
                   reporter.display_name AS reporter_display_name,
                   reporter_nickname.nickname AS reporter_nickname,
                   reporter.status AS reporter_account_status,
                   reported.display_name AS reported_display_name,
                   reported_nickname.nickname AS reported_nickname,
                   reported.status AS reported_account_status,
                   reviewer.display_name AS reviewer_display_name,
                   reviewer_nickname.nickname AS reviewer_nickname,
                   reviewer.status AS reviewer_account_status
            FROM sr_community_reports r
            LEFT JOIN sr_member_accounts reporter ON reporter.id = r.reporter_account_id
            LEFT JOIN sr_member_accounts reported ON reported.id = r.reported_account_id
            LEFT JOIN sr_member_accounts reviewer ON reviewer.id = r.reviewer_account_id
            LEFT JOIN sr_member_nicknames reporter_nickname ON reporter_nickname.account_id = reporter.id
            LEFT JOIN sr_member_nicknames reported_nickname ON reported_nickname.account_id = reported.id
            LEFT JOIN sr_member_nicknames reviewer_nickname ON reviewer_nickname.account_id = reviewer.id
            LEFT JOIN sr_community_posts target_post ON r.target_type = \'post\' AND target_post.id = r.target_id
            LEFT JOIN sr_community_comments target_comment ON r.target_type = \'comment\' AND target_comment.id = r.target_id
            LEFT JOIN sr_community_posts target_comment_post ON target_comment_post.id = target_comment.post_id';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY r.id DESC';
    if ($useLimit) {
        $sql .= ' LIMIT :limit_value OFFSET :offset_value';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, PDO::PARAM_STR);
    }
    if ($useLimit) {
        $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_community_report_by_id(PDO $pdo, int $reportId): ?array
{
    if ($reportId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_community_reports WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $reportId]);
    $report = $stmt->fetch();

    return is_array($report) ? $report : null;
}

function sr_community_apply_report_target_action(PDO $pdo, array $report, string $actionKey, int $adminAccountId, bool $requireAuditLog = false): array
{
    $targetType = (string) ($report['target_type'] ?? '');
    $targetId = (int) ($report['target_id'] ?? 0);
    if ($actionKey === '' || $actionKey === 'none') {
        return ['action_key' => 'none', 'applied' => false];
    }
    if (!array_key_exists($actionKey, sr_community_report_target_action_options($targetType))) {
        return ['action_key' => $actionKey, 'applied' => false, 'error' => 'invalid_action'];
    }

    $combinedActions = [
        'hide_post_suspend_publisher' => ['hide_post', 'suspend_reported_account'],
        'delete_post_suspend_publisher' => ['delete_post', 'suspend_reported_account'],
        'hide_comment_suspend_publisher' => ['hide_comment', 'suspend_reported_account'],
        'delete_comment_suspend_publisher' => ['delete_comment', 'suspend_reported_account'],
    ];
    if (array_key_exists($actionKey, $combinedActions)) {
        $results = [];
        $applied = false;
        foreach ($combinedActions[$actionKey] as $childActionKey) {
            $childResult = sr_community_apply_report_target_action($pdo, $report, $childActionKey, $adminAccountId, $requireAuditLog);
            $results[] = $childResult;
            if (!empty($childResult['error'])) {
                return [
                    'action_key' => $actionKey,
                    'applied' => $applied,
                    'error' => (string) $childResult['error'],
                    'results' => $results,
                ];
            }
            if (!empty($childResult['applied'])) {
                $applied = true;
            }
        }

        return [
            'action_key' => $actionKey,
            'applied' => $applied,
            'results' => $results,
        ];
    }

    if ($targetType === 'post' && in_array($actionKey, ['hide_post', 'delete_post'], true)) {
        $status = $actionKey === 'hide_post' ? 'hidden' : 'deleted';
        $post = sr_community_admin_post_by_id($pdo, $targetId);
        if (!is_array($post)) {
            return ['action_key' => $actionKey, 'applied' => false, 'error' => 'target_not_found'];
        }
        sr_community_update_post_status($pdo, $targetId, $status, $status === 'hidden' ? [
            'hidden_reason' => 'moderation',
            'hidden_note' => 'Report target action applied by administrator.',
            'hidden_by_account_id' => $adminAccountId,
        ] : []);
        $updatedAttachmentCount = in_array($status, ['hidden', 'deleted'], true)
            ? sr_community_update_post_attachments_status($pdo, $targetId, $status)
            : 0;
        sr_community_report_target_action_audit_log($pdo, [
            'actor_account_id' => $adminAccountId,
            'actor_type' => 'admin',
            'event_type' => 'community.report.target_post_action',
            'target_type' => 'community_post',
            'target_id' => (string) $targetId,
            'result' => 'success',
            'message' => 'Community report target post action applied.',
            'metadata' => [
                'report_id' => (int) ($report['id'] ?? 0),
                'before_status' => (string) ($post['status'] ?? ''),
                'after_status' => $status,
                'updated_attachment_count' => $updatedAttachmentCount,
            ],
        ], $requireAuditLog);
        return ['action_key' => $actionKey, 'applied' => true, 'target_status' => $status];
    }

    if ($targetType === 'comment' && in_array($actionKey, ['hide_comment', 'delete_comment'], true)) {
        $status = $actionKey === 'hide_comment' ? 'hidden' : 'deleted';
        $comment = sr_community_admin_comment_by_id($pdo, $targetId);
        if (!is_array($comment)) {
            return ['action_key' => $actionKey, 'applied' => false, 'error' => 'target_not_found'];
        }
        sr_community_update_comment_status($pdo, $targetId, $status, $status === 'hidden' ? [
            'hidden_reason' => 'moderation',
            'hidden_note' => 'Report target action applied by administrator.',
            'hidden_by_account_id' => $adminAccountId,
        ] : []);
        sr_community_report_target_action_audit_log($pdo, [
            'actor_account_id' => $adminAccountId,
            'actor_type' => 'admin',
            'event_type' => 'community.report.target_comment_action',
            'target_type' => 'community_comment',
            'target_id' => (string) $targetId,
            'result' => 'success',
            'message' => 'Community report target comment action applied.',
            'metadata' => [
                'report_id' => (int) ($report['id'] ?? 0),
                'before_status' => (string) ($comment['status'] ?? ''),
                'after_status' => $status,
                'post_id' => (int) ($comment['post_id'] ?? 0),
            ],
        ], $requireAuditLog);
        return ['action_key' => $actionKey, 'applied' => true, 'target_status' => $status];
    }

    if ($actionKey === 'suspend_reported_account') {
        $reportedAccountId = (int) ($report['reported_account_id'] ?? 0);
        if ($reportedAccountId < 1 || !function_exists('sr_member_update_status')) {
            return ['action_key' => $actionKey, 'applied' => false, 'error' => 'account_action_unavailable'];
        }
        sr_member_update_status($pdo, $reportedAccountId, 'suspended');
        sr_community_report_target_action_audit_log($pdo, [
            'actor_account_id' => $adminAccountId,
            'actor_type' => 'admin',
            'event_type' => 'community.report.reported_account_suspended',
            'target_type' => 'member_account',
            'target_id' => (string) $reportedAccountId,
            'result' => 'success',
            'message' => 'Reported account suspended from community report.',
            'metadata' => [
                'report_id' => (int) ($report['id'] ?? 0),
                'reported_target_type' => $targetType,
                'reported_target_id' => $targetId,
            ],
        ], $requireAuditLog);
        return ['action_key' => $actionKey, 'applied' => true, 'account_status' => 'suspended'];
    }

    return ['action_key' => $actionKey, 'applied' => false, 'error' => 'unsupported_action'];
}

function sr_community_apply_report_reporter_action(PDO $pdo, array $report, string $actionKey, int $adminAccountId, bool $requireAuditLog = false): array
{
    $targetType = (string) ($report['target_type'] ?? '');
    $targetId = (int) ($report['target_id'] ?? 0);
    if ($actionKey === '' || $actionKey === 'none') {
        return ['action_key' => 'none', 'applied' => false];
    }
    if (!array_key_exists($actionKey, sr_community_report_reporter_action_options())) {
        return ['action_key' => $actionKey, 'applied' => false, 'error' => 'invalid_action'];
    }

    if ($actionKey === 'suspend_reporter_account') {
        $reporterAccountId = (int) ($report['reporter_account_id'] ?? 0);
        if ($reporterAccountId < 1 || !function_exists('sr_member_update_status')) {
            return ['action_key' => $actionKey, 'applied' => false, 'error' => 'account_action_unavailable'];
        }
        sr_member_update_status($pdo, $reporterAccountId, 'suspended');
        sr_community_report_target_action_audit_log($pdo, [
            'actor_account_id' => $adminAccountId,
            'actor_type' => 'admin',
            'event_type' => 'community.report.reporter_account_suspended',
            'target_type' => 'member_account',
            'target_id' => (string) $reporterAccountId,
            'result' => 'success',
            'message' => 'Reporter account suspended from dismissed community report.',
            'metadata' => [
                'report_id' => (int) ($report['id'] ?? 0),
                'reported_target_type' => $targetType,
                'reported_target_id' => $targetId,
                'reported_account_id' => (int) ($report['reported_account_id'] ?? 0),
            ],
        ], $requireAuditLog);
        return ['action_key' => $actionKey, 'applied' => true, 'account_status' => 'suspended'];
    }

    return ['action_key' => $actionKey, 'applied' => false, 'error' => 'unsupported_action'];
}

function sr_community_report_target_action_audit_log(PDO $pdo, array $data, bool $required): void
{
    if ($required && function_exists('sr_audit_log_required')) {
        sr_audit_log_required($pdo, $data);
        return;
    }

    sr_audit_log($pdo, $data);
}
