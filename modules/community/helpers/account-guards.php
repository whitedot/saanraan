<?php

declare(strict_types=1);

function sr_community_account_guard_types(): array
{
    return ['publication_hold', 'confirmed_hold', 'write_cooldown', 'needs_review'];
}

function sr_community_account_guard_statuses(): array
{
    return ['active', 'released', 'expired', 'cancelled', 'needs_review'];
}

function sr_community_account_guard_active_statuses(): array
{
    return ['active'];
}

function sr_community_account_guard_active_uid(int $accountId, string $guardType): string
{
    if ($accountId < 1 || !in_array($guardType, sr_community_account_guard_types(), true)) {
        return '';
    }

    return (string) $accountId . ':' . $guardType;
}

function sr_community_account_guard_status_is_active(string $status): bool
{
    return in_array($status, sr_community_account_guard_active_statuses(), true);
}

function sr_community_account_guard_json(array $data): string
{
    $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return is_string($encoded) ? $encoded : '{}';
}

function sr_community_account_guard_lock_suffix(PDO $pdo): string
{
    try {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    } catch (Throwable) {
        return ' FOR UPDATE';
    }
}

function sr_community_account_guard_settings_snapshot(array $settings): array
{
    return [
        'publication_hold_enabled' => !empty($settings['account_guard_publication_hold_enabled']),
        'publication_hold_threshold' => min(20, max(2, (int) ($settings['account_guard_publication_hold_threshold'] ?? 3))),
        'publication_hold_overlap_review_percent' => min(100, max(0, (int) ($settings['account_guard_publication_hold_overlap_review_percent'] ?? 80))),
        'publication_hold_duration_minutes' => min(10080, max(10, (int) ($settings['account_guard_publication_hold_duration_minutes'] ?? 120))),
        'confirmed_hold_enabled' => !empty($settings['account_guard_confirmed_hold_enabled']),
        'confirmed_hold_threshold' => min(20, max(2, (int) ($settings['account_guard_confirmed_hold_threshold'] ?? 3))),
        'confirmed_hold_window_days' => min(365, max(1, (int) ($settings['account_guard_confirmed_hold_window_days'] ?? 30))),
        'confirmed_hold_duration_minutes' => min(10080, max(10, (int) ($settings['account_guard_confirmed_hold_duration_minutes'] ?? 1440))),
    ];
}

function sr_community_account_guard_target_uid(string $targetType, int $targetId): string
{
    return $targetId > 0 && in_array($targetType, ['post', 'comment'], true) ? $targetType . ':' . (string) $targetId : '';
}

function sr_community_account_guard_trigger_fingerprint(int $accountId, string $guardType, array $targetUids, array $policySnapshot): string
{
    $targetUids = array_values(array_unique(array_filter(array_map('strval', $targetUids))));
    sort($targetUids, SORT_STRING);
    $policyHash = hash('sha256', sr_community_account_guard_json($policySnapshot));
    $hash = hash('sha256', sr_community_account_guard_json([
        'account_id' => $accountId,
        'guard_type' => $guardType,
        'target_uids' => $targetUids,
        'policy_hash' => $policyHash,
    ]));

    return substr((string) $accountId . ':' . $guardType . ':' . $hash, 0, 160);
}

function sr_community_account_guard_lock_account(PDO $pdo, int $accountId): bool
{
    if ($accountId < 1) {
        return false;
    }

    $now = function_exists('sr_now') ? sr_now() : date('Y-m-d H:i:s');
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable) {
        $driver = '';
    }
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO sr_community_account_guard_locks (account_id, updated_at) VALUES (:account_id, :updated_at)');
    } else {
        $stmt = $pdo->prepare('INSERT IGNORE INTO sr_community_account_guard_locks (account_id, updated_at) VALUES (:account_id, :updated_at)');
    }
    $stmt->execute([
        'account_id' => $accountId,
        'updated_at' => $now,
    ]);

    $stmt = $pdo->prepare(
        'SELECT account_id
         FROM sr_community_account_guard_locks
         WHERE account_id = :account_id
         LIMIT 1' . sr_community_account_guard_lock_suffix($pdo)
    );
    $stmt->execute(['account_id' => $accountId]);

    return is_array($stmt->fetch());
}

function sr_community_account_guard_by_type(PDO $pdo, int $accountId, string $guardType, bool $lock = false): ?array
{
    $activeUid = sr_community_account_guard_active_uid($accountId, $guardType);
    if ($activeUid === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_community_account_guards
         WHERE active_guard_uid = :active_guard_uid
           AND status = \'active\'
         LIMIT 1' . ($lock ? sr_community_account_guard_lock_suffix($pdo) : '')
    );
    $stmt->execute(['active_guard_uid' => $activeUid]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_account_guard_existing_event(PDO $pdo, int $accountId, string $guardType, string $triggerFingerprint): ?array
{
    if ($accountId < 1 || $triggerFingerprint === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_community_account_guard_events
         WHERE account_id = :account_id
           AND guard_type = :guard_type
           AND trigger_fingerprint = :trigger_fingerprint
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'guard_type' => $guardType,
        'trigger_fingerprint' => $triggerFingerprint,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_account_guard_insert_event(PDO $pdo, array $data): int
{
    $now = function_exists('sr_now') ? sr_now() : date('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_account_guard_events
            (account_id, source_type, source_id, guard_type, trigger_reason, status,
             starts_at, expires_at, released_at, reviewer_account_id, trigger_fingerprint,
             snapshot_json, created_at, updated_at)
         VALUES
            (:account_id, :source_type, :source_id, :guard_type, :trigger_reason, :status,
             :starts_at, :expires_at, NULL, :reviewer_account_id, :trigger_fingerprint,
             :snapshot_json, :created_at, :updated_at)'
    );
    $stmt->execute([
        'account_id' => (int) $data['account_id'],
        'source_type' => (string) ($data['source_type'] ?? ''),
        'source_id' => (int) ($data['source_id'] ?? 0) > 0 ? (int) $data['source_id'] : null,
        'guard_type' => (string) $data['guard_type'],
        'trigger_reason' => (string) ($data['trigger_reason'] ?? ''),
        'status' => (string) ($data['status'] ?? 'active'),
        'starts_at' => (string) ($data['starts_at'] ?? '') !== '' ? (string) $data['starts_at'] : null,
        'expires_at' => (string) ($data['expires_at'] ?? '') !== '' ? (string) $data['expires_at'] : null,
        'reviewer_account_id' => (int) ($data['reviewer_account_id'] ?? 0) > 0 ? (int) $data['reviewer_account_id'] : null,
        'trigger_fingerprint' => (string) ($data['trigger_fingerprint'] ?? ''),
        'snapshot_json' => sr_community_account_guard_json(is_array($data['snapshot'] ?? null) ? $data['snapshot'] : []),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_community_account_guard_insert_current(PDO $pdo, array $data): int
{
    $now = function_exists('sr_now') ? sr_now() : date('Y-m-d H:i:s');
    $accountId = (int) $data['account_id'];
    $guardType = (string) $data['guard_type'];
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_account_guards
            (account_id, guard_type, status, active_guard_uid, source_event_id,
             starts_at, expires_at, released_at, reviewer_account_id, snapshot_json,
             created_at, updated_at)
         VALUES
            (:account_id, :guard_type, :status, :active_guard_uid, :source_event_id,
             :starts_at, :expires_at, NULL, :reviewer_account_id, :snapshot_json,
             :created_at, :updated_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'guard_type' => $guardType,
        'status' => (string) ($data['status'] ?? 'active'),
        'active_guard_uid' => sr_community_account_guard_active_uid($accountId, $guardType),
        'source_event_id' => (int) ($data['source_event_id'] ?? 0) > 0 ? (int) $data['source_event_id'] : null,
        'starts_at' => (string) ($data['starts_at'] ?? '') !== '' ? (string) $data['starts_at'] : null,
        'expires_at' => (string) ($data['expires_at'] ?? '') !== '' ? (string) $data['expires_at'] : null,
        'reviewer_account_id' => (int) ($data['reviewer_account_id'] ?? 0) > 0 ? (int) $data['reviewer_account_id'] : null,
        'snapshot_json' => sr_community_account_guard_json(is_array($data['snapshot'] ?? null) ? $data['snapshot'] : []),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_community_account_guard_target_author_id(PDO $pdo, string $targetType, int $targetId): int
{
    if ($targetType === 'post' && $targetId > 0) {
        $stmt = $pdo->prepare('SELECT author_account_id FROM sr_community_posts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $targetId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }
    if ($targetType === 'comment' && $targetId > 0) {
        $stmt = $pdo->prepare('SELECT author_account_id FROM sr_community_comments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $targetId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    return 0;
}

function sr_community_account_guard_active_post_auto_actions(PDO $pdo, int $accountId): array
{
    if ($accountId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT a.*
         FROM sr_community_report_auto_actions a
         INNER JOIN sr_community_posts p ON p.id = a.target_id AND a.target_type = \'post\'
         WHERE a.status = \'active\'
           AND a.active_target_uid IS NOT NULL
           AND p.author_account_id = :account_id
         ORDER BY a.target_id ASC, a.id ASC'
    );
    $stmt->execute(['account_id' => $accountId]);

    return $stmt->fetchAll();
}

function sr_community_account_guard_confirmed_auto_actions(PDO $pdo, int $accountId, int $windowDays, ?string $now = null): array
{
    if ($accountId < 1) {
        return [];
    }

    $now = $now !== null && $now !== '' ? $now : (function_exists('sr_now') ? sr_now() : date('Y-m-d H:i:s'));
    $createdAfter = (new DateTimeImmutable($now))->modify('-' . (string) min(365, max(1, $windowDays)) . ' days')->format('Y-m-d H:i:s');
    $rows = [];
    foreach ([
        ['post', 'sr_community_posts', 'author_account_id'],
        ['comment', 'sr_community_comments', 'author_account_id'],
    ] as $targetSource) {
        [$targetType, $table, $authorColumn] = $targetSource;
        $stmt = $pdo->prepare(
            'SELECT a.*
             FROM sr_community_report_auto_actions a
             INNER JOIN ' . $table . ' t ON t.id = a.target_id AND a.target_type = :target_type
             WHERE a.status = \'confirmed\'
               AND COALESCE(NULLIF(a.reviewed_at, \'\'), a.updated_at) >= :created_after
               AND t.' . $authorColumn . ' = :account_id
             ORDER BY a.target_id ASC, a.id ASC'
        );
        $stmt->execute([
            'target_type' => $targetType,
            'created_after' => $createdAfter,
            'account_id' => $accountId,
        ]);
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
    }

    usort($rows, static function (array $a, array $b): int {
        return strcmp((string) ($a['target_type'] ?? ''), (string) ($b['target_type'] ?? ''))
            ?: ((int) ($a['target_id'] ?? 0) <=> (int) ($b['target_id'] ?? 0))
            ?: ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
    });

    return $rows;
}

function sr_community_account_guard_reporter_sets(PDO $pdo, array $autoActions): array
{
    $postIds = [];
    foreach ($autoActions as $autoAction) {
        if (is_array($autoAction) && (string) ($autoAction['target_type'] ?? '') === 'post') {
            $targetId = (int) ($autoAction['target_id'] ?? 0);
            if ($targetId > 0) {
                $postIds[$targetId] = $targetId;
            }
        }
    }
    if ($postIds === []) {
        return [];
    }

    $params = [];
    $placeholders = [];
    foreach (array_values($postIds) as $index => $postId) {
        $placeholder = 'target_id_' . (string) $index;
        $placeholders[] = ':' . $placeholder;
        $params[$placeholder] = (int) $postId;
    }
    $stmt = $pdo->prepare(
        "SELECT target_id, reporter_account_id
         FROM sr_community_reports
         WHERE target_type = 'post'
           AND status IN ('open', 'reviewing')
           AND target_id IN (" . implode(', ', $placeholders) . ')'
    );
    $stmt->execute($params);

    $sets = [];
    foreach ($postIds as $postId) {
        $sets['post:' . (string) $postId] = [];
    }
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $targetUid = 'post:' . (string) (int) ($row['target_id'] ?? 0);
        $reporterId = (int) ($row['reporter_account_id'] ?? 0);
        if ($reporterId > 0 && isset($sets[$targetUid])) {
            $sets[$targetUid][$reporterId] = $reporterId;
        }
    }

    return $sets;
}

function sr_community_account_guard_overlap_summary(array $reporterSets): array
{
    $targetUids = array_keys($reporterSets);
    $maxPercent = 0;
    $maxPair = [];
    for ($i = 0, $count = count($targetUids); $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            $left = array_values((array) ($reporterSets[$targetUids[$i]] ?? []));
            $right = array_values((array) ($reporterSets[$targetUids[$j]] ?? []));
            $base = min(count($left), count($right));
            if ($base < 1) {
                continue;
            }
            $intersection = count(array_intersect($left, $right));
            $percent = (int) floor(($intersection / $base) * 100);
            if ($percent > $maxPercent) {
                $maxPercent = $percent;
                $maxPair = [$targetUids[$i], $targetUids[$j]];
            }
        }
    }

    return [
        'max_overlap_percent' => $maxPercent,
        'max_overlap_pair' => $maxPair,
        'target_count' => count($targetUids),
    ];
}

function sr_community_account_guard_target_uids(array $autoActions): array
{
    $targetUids = [];
    foreach ($autoActions as $autoAction) {
        if (!is_array($autoAction)) {
            continue;
        }
        $targetUid = sr_community_account_guard_target_uid((string) ($autoAction['target_type'] ?? ''), (int) ($autoAction['target_id'] ?? 0));
        if ($targetUid !== '') {
            $targetUids[$targetUid] = $targetUid;
        }
    }

    return array_values($targetUids);
}

function sr_community_account_guard_expiry(string $now, int $durationMinutes): string
{
    return (new DateTimeImmutable($now))->modify('+' . (string) $durationMinutes . ' minutes')->format('Y-m-d H:i:s');
}

function sr_community_evaluate_account_publication_hold(PDO $pdo, int $accountId, array $settings, int $sourceAutoActionId = 0): array
{
    $snapshot = sr_community_account_guard_settings_snapshot($settings);
    if ($accountId < 1 || empty($snapshot['publication_hold_enabled'])) {
        return ['status' => 'disabled'];
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        sr_community_account_guard_lock_account($pdo, $accountId);
        $activeAutoActions = sr_community_account_guard_active_post_auto_actions($pdo, $accountId);
        $threshold = (int) $snapshot['publication_hold_threshold'];
        $activeGuard = sr_community_account_guard_by_type($pdo, $accountId, 'publication_hold', true);
        if (count($activeAutoActions) < $threshold) {
            if (is_array($activeGuard)) {
                sr_community_account_guard_transition($pdo, (int) $activeGuard['id'], 'released', [
                    'snapshot' => [
                        'reason' => 'below_active_auto_action_threshold',
                        'active_target_count' => count($activeAutoActions),
                        'threshold' => $threshold,
                    ],
                ]);
            }
            if ($startedTransaction) {
                $pdo->commit();
            }
            return ['status' => 'below_threshold', 'active_target_count' => count($activeAutoActions), 'threshold' => $threshold];
        }

        $targetUids = sr_community_account_guard_target_uids($activeAutoActions);
        $policySnapshot = [
            'guard_type' => 'publication_hold',
            'threshold' => $threshold,
            'duration_minutes' => (int) $snapshot['publication_hold_duration_minutes'],
            'overlap_review_percent' => (int) $snapshot['publication_hold_overlap_review_percent'],
        ];
        $fingerprint = sr_community_account_guard_trigger_fingerprint($accountId, 'publication_hold', $targetUids, $policySnapshot);
        $reporterSets = sr_community_account_guard_reporter_sets($pdo, $activeAutoActions);
        $overlap = sr_community_account_guard_overlap_summary($reporterSets);
        $overlapReviewPercent = (int) $snapshot['publication_hold_overlap_review_percent'];
        $eventStatus = (int) $overlap['max_overlap_percent'] >= $overlapReviewPercent ? 'needs_review' : 'active';
        if ($eventStatus === 'needs_review') {
            if (is_array($activeGuard)) {
                sr_community_account_guard_transition($pdo, (int) $activeGuard['id'], 'released', [
                    'snapshot' => [
                        'reason' => 'reporter_overlap_needs_review',
                        'overlap' => $overlap,
                    ],
                ]);
            }
            $reviewFingerprint = sr_community_account_guard_trigger_fingerprint($accountId, 'needs_review', $targetUids, $policySnapshot);
            $existingReviewEvent = sr_community_account_guard_existing_event($pdo, $accountId, 'needs_review', $reviewFingerprint);
            if (is_array($existingReviewEvent)) {
                if ($startedTransaction) {
                    $pdo->commit();
                }
                return ['status' => 'needs_review_deduped', 'event_id' => (int) ($existingReviewEvent['id'] ?? 0), 'overlap' => $overlap];
            }
            $eventId = sr_community_account_guard_insert_event($pdo, [
                'account_id' => $accountId,
                'source_type' => 'report_auto_action',
                'source_id' => $sourceAutoActionId,
                'guard_type' => 'needs_review',
                'trigger_reason' => 'reporter_overlap',
                'status' => 'needs_review',
                'trigger_fingerprint' => $reviewFingerprint,
                'snapshot' => [
                    'policy' => $policySnapshot,
                    'target_uids' => $targetUids,
                    'overlap' => $overlap,
                ],
            ]);
            if ($startedTransaction) {
                $pdo->commit();
            }
            return ['status' => 'needs_review', 'event_id' => $eventId, 'overlap' => $overlap];
        }

        $existingEvent = sr_community_account_guard_existing_event($pdo, $accountId, 'publication_hold', $fingerprint);
        if (is_array($existingEvent)) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return ['status' => 'deduped', 'event_id' => (int) ($existingEvent['id'] ?? 0)];
        }
        if (is_array($activeGuard)) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return ['status' => 'active_exists', 'guard_id' => (int) ($activeGuard['id'] ?? 0)];
        }

        $now = function_exists('sr_now') ? sr_now() : date('Y-m-d H:i:s');
        $expiresAt = sr_community_account_guard_expiry($now, (int) $snapshot['publication_hold_duration_minutes']);
        $eventId = sr_community_account_guard_insert_event($pdo, [
            'account_id' => $accountId,
            'source_type' => 'report_auto_action',
            'source_id' => $sourceAutoActionId,
            'guard_type' => 'publication_hold',
            'trigger_reason' => 'multiple_active_auto_actions',
            'status' => 'active',
            'starts_at' => $now,
            'expires_at' => $expiresAt,
            'trigger_fingerprint' => $fingerprint,
            'snapshot' => [
                'policy' => $policySnapshot,
                'target_uids' => $targetUids,
                'overlap' => $overlap,
            ],
        ]);
        $guardId = sr_community_account_guard_insert_current($pdo, [
            'account_id' => $accountId,
            'guard_type' => 'publication_hold',
            'status' => 'active',
            'source_event_id' => $eventId,
            'starts_at' => $now,
            'expires_at' => $expiresAt,
            'snapshot' => [
                'policy' => $policySnapshot,
                'target_uids' => $targetUids,
            ],
        ]);
        if ($startedTransaction) {
            $pdo->commit();
        }

        return ['status' => 'created', 'event_id' => $eventId, 'guard_id' => $guardId, 'target_uids' => $targetUids];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_community_evaluate_account_confirmed_hold(PDO $pdo, int $accountId, array $settings, int $sourceAutoActionId = 0): array
{
    $snapshot = sr_community_account_guard_settings_snapshot($settings);
    if ($accountId < 1 || empty($snapshot['confirmed_hold_enabled'])) {
        return ['status' => 'disabled'];
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        sr_community_account_guard_lock_account($pdo, $accountId);
        $confirmedAutoActions = sr_community_account_guard_confirmed_auto_actions($pdo, $accountId, (int) $snapshot['confirmed_hold_window_days']);
        $threshold = (int) $snapshot['confirmed_hold_threshold'];
        if (count($confirmedAutoActions) < $threshold) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return ['status' => 'below_threshold', 'confirmed_target_count' => count($confirmedAutoActions), 'threshold' => $threshold];
        }
        $activeGuard = sr_community_account_guard_by_type($pdo, $accountId, 'confirmed_hold', true);
        if (is_array($activeGuard)) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return ['status' => 'active_exists', 'guard_id' => (int) ($activeGuard['id'] ?? 0)];
        }

        $targetUids = sr_community_account_guard_target_uids($confirmedAutoActions);
        $policySnapshot = [
            'guard_type' => 'confirmed_hold',
            'threshold' => $threshold,
            'window_days' => (int) $snapshot['confirmed_hold_window_days'],
            'duration_minutes' => (int) $snapshot['confirmed_hold_duration_minutes'],
        ];
        $fingerprint = sr_community_account_guard_trigger_fingerprint($accountId, 'confirmed_hold', $targetUids, $policySnapshot);
        $existingEvent = sr_community_account_guard_existing_event($pdo, $accountId, 'confirmed_hold', $fingerprint);
        if (is_array($existingEvent)) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return ['status' => 'deduped', 'event_id' => (int) ($existingEvent['id'] ?? 0)];
        }

        $now = function_exists('sr_now') ? sr_now() : date('Y-m-d H:i:s');
        $expiresAt = sr_community_account_guard_expiry($now, (int) $snapshot['confirmed_hold_duration_minutes']);
        $eventId = sr_community_account_guard_insert_event($pdo, [
            'account_id' => $accountId,
            'source_type' => 'report_auto_action',
            'source_id' => $sourceAutoActionId,
            'guard_type' => 'confirmed_hold',
            'trigger_reason' => 'confirmed_violation_repeat',
            'status' => 'active',
            'starts_at' => $now,
            'expires_at' => $expiresAt,
            'trigger_fingerprint' => $fingerprint,
            'snapshot' => [
                'policy' => $policySnapshot,
                'target_uids' => $targetUids,
            ],
        ]);
        $guardId = sr_community_account_guard_insert_current($pdo, [
            'account_id' => $accountId,
            'guard_type' => 'confirmed_hold',
            'status' => 'active',
            'source_event_id' => $eventId,
            'starts_at' => $now,
            'expires_at' => $expiresAt,
            'snapshot' => [
                'policy' => $policySnapshot,
                'target_uids' => $targetUids,
            ],
        ]);
        if ($startedTransaction) {
            $pdo->commit();
        }

        return ['status' => 'created', 'event_id' => $eventId, 'guard_id' => $guardId, 'target_uids' => $targetUids];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_community_evaluate_account_guard_after_auto_action(PDO $pdo, int $autoActionId, array $settings): array
{
    if ($autoActionId < 1) {
        return ['status' => 'invalid_auto_action'];
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_community_report_auto_actions WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $autoActionId]);
    $autoAction = $stmt->fetch();
    if (!is_array($autoAction)) {
        return ['status' => 'auto_action_not_found'];
    }

    $accountId = sr_community_account_guard_target_author_id($pdo, (string) ($autoAction['target_type'] ?? ''), (int) ($autoAction['target_id'] ?? 0));
    if ($accountId < 1) {
        return ['status' => 'target_author_not_found'];
    }

    $status = (string) ($autoAction['status'] ?? '');
    $results = [];
    if (in_array($status, ['active', 'released'], true)) {
        $results['publication_hold'] = sr_community_evaluate_account_publication_hold($pdo, $accountId, $settings, $autoActionId);
    }
    if ($status === 'confirmed') {
        $results['publication_hold'] = sr_community_evaluate_account_publication_hold($pdo, $accountId, $settings, $autoActionId);
        $results['confirmed_hold'] = sr_community_evaluate_account_confirmed_hold($pdo, $accountId, $settings, $autoActionId);
    }

    return [
        'status' => $results === [] ? 'no_evaluation' : 'evaluated',
        'account_id' => $accountId,
        'auto_action_status' => $status,
        'results' => $results,
    ];
}

function sr_community_account_guard_transition(PDO $pdo, int $guardId, string $status, array $context = []): bool
{
    if ($guardId < 1 || !in_array($status, sr_community_account_guard_statuses(), true)) {
        return false;
    }

    $guardStmt = $pdo->prepare('SELECT source_event_id FROM sr_community_account_guards WHERE id = :id LIMIT 1');
    $guardStmt->execute(['id' => $guardId]);
    $sourceEventId = (int) ($guardStmt->fetchColumn() ?: 0);
    $now = function_exists('sr_now') ? sr_now() : date('Y-m-d H:i:s');
    $setParts = [
        'status = :status',
        'updated_at = :updated_at',
    ];
    $params = [
        'id' => $guardId,
        'status' => $status,
        'updated_at' => $now,
    ];

    if (!sr_community_account_guard_status_is_active($status)) {
        $setParts[] = 'active_guard_uid = NULL';
    }
    if ($status === 'released') {
        $setParts[] = 'released_at = :released_at';
        $params['released_at'] = $now;
    }
    $reviewerAccountId = (int) ($context['reviewer_account_id'] ?? 0);
    if ($reviewerAccountId > 0) {
        $setParts[] = 'reviewer_account_id = :reviewer_account_id';
        $params['reviewer_account_id'] = $reviewerAccountId;
    }
    if (array_key_exists('snapshot', $context)) {
        $snapshot = is_array($context['snapshot']) ? $context['snapshot'] : [];
        $setParts[] = 'snapshot_json = :snapshot_json';
        $params['snapshot_json'] = sr_community_account_guard_json($snapshot);
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_account_guards
         SET ' . implode(', ', $setParts) . '
         WHERE id = :id'
    );
    $stmt->execute($params);

    $updated = $stmt->rowCount() > 0;
    if ($updated && $sourceEventId > 0) {
        $eventSetParts = [
            'status = :status',
            'updated_at = :updated_at',
        ];
        $eventParams = [
            'id' => $sourceEventId,
            'status' => $status,
            'updated_at' => $now,
        ];
        if ($status === 'released') {
            $eventSetParts[] = 'released_at = :released_at';
            $eventParams['released_at'] = $now;
        }
        if ($reviewerAccountId > 0) {
            $eventSetParts[] = 'reviewer_account_id = :reviewer_account_id';
            $eventParams['reviewer_account_id'] = $reviewerAccountId;
        }
        if (array_key_exists('snapshot', $context)) {
            $eventSetParts[] = 'snapshot_json = :snapshot_json';
            $eventParams['snapshot_json'] = sr_community_account_guard_json(is_array($context['snapshot']) ? $context['snapshot'] : []);
        }
        $eventStmt = $pdo->prepare(
            'UPDATE sr_community_account_guard_events
             SET ' . implode(', ', $eventSetParts) . '
             WHERE id = :id'
        );
        $eventStmt->execute($eventParams);
    }

    return $updated;
}

function sr_community_account_active_guards(PDO $pdo, int $accountId, ?string $now = null): array
{
    if ($accountId < 1) {
        return [];
    }

    $now = $now !== null && $now !== '' ? $now : (function_exists('sr_now') ? sr_now() : date('Y-m-d H:i:s'));
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_community_account_guards
         WHERE account_id = :account_id
           AND status = \'active\'
           AND (expires_at IS NULL OR expires_at > :now_value)
         ORDER BY id ASC'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'now_value' => $now,
    ]);

    return $stmt->fetchAll();
}

function sr_community_account_guard_write_decision(PDO $pdo, int $accountId, string $targetType): array
{
    if ($accountId < 1 || !in_array($targetType, ['post', 'comment'], true)) {
        return ['action' => 'allow', 'initial_status' => 'published', 'guard_type' => 'none'];
    }

    $activeGuards = sr_community_account_active_guards($pdo, $accountId);
    foreach ($activeGuards as $guard) {
        if (is_array($guard) && (string) ($guard['guard_type'] ?? '') === 'write_cooldown') {
            return [
                'action' => 'block',
                'initial_status' => 'published',
                'guard_type' => 'write_cooldown',
                'guard_id' => (int) ($guard['id'] ?? 0),
                'message' => '커뮤니티 작성 제한이 적용 중입니다. 잠시 후 다시 시도해 주세요.',
            ];
        }
    }

    if ($targetType === 'post') {
        foreach (['confirmed_hold', 'publication_hold'] as $guardType) {
            foreach ($activeGuards as $guard) {
                if (is_array($guard) && (string) ($guard['guard_type'] ?? '') === $guardType) {
                    return [
                        'action' => 'hold',
                        'initial_status' => 'pending',
                        'guard_type' => $guardType,
                        'guard_id' => (int) ($guard['id'] ?? 0),
                        'message' => '게시글이 검토 대기 상태로 저장되었습니다.',
                    ];
                }
            }
        }
    }

    return ['action' => 'allow', 'initial_status' => 'published', 'guard_type' => 'none'];
}
