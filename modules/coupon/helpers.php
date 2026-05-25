<?php

declare(strict_types=1);

function sr_coupon_clean_key(string $value, int $maxLength = 60): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_]/', '', $value);
    $value = is_string($value) ? $value : '';

    return substr($value, 0, $maxLength);
}

function sr_coupon_clean_text(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function sr_coupon_statuses(): array
{
    return ['active', 'disabled'];
}

function sr_coupon_issue_statuses(): array
{
    return ['active', 'used', 'expired', 'revoked', 'withdrawn_expired', 'refund_requested', 'refunded'];
}

function sr_coupon_target_types(): array
{
    return [
        'all' => '전체',
        'content' => '콘텐츠',
        'community_board' => '커뮤니티 게시판',
        'community_post' => '커뮤니티 게시글',
        'shop_product' => '쇼핑몰 상품',
    ];
}

function sr_coupon_refundable_policies(): array
{
    return [
        'none' => '환급 없음',
        'refundable' => '환급 가능',
    ];
}

function sr_coupon_definition_by_id(PDO $pdo, int $definitionId): ?array
{
    if ($definitionId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_coupon_definitions WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $definitionId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_coupon_issue_by_id(PDO $pdo, int $issueId): ?array
{
    if ($issueId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT i.*, d.coupon_key, d.title, d.description, d.coupon_type, d.target_type, d.target_id, d.refundable_policy, d.max_uses_per_issue
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         WHERE i.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $issueId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_coupon_definitions(PDO $pdo, int $limit = 100): array
{
    $limit = max(1, min(300, $limit));
    $stmt = $pdo->query(
        'SELECT *
         FROM sr_coupon_definitions
         ORDER BY id DESC
         LIMIT ' . $limit
    );

    return $stmt->fetchAll();
}

function sr_coupon_create_definition(PDO $pdo, array $data): int
{
    $couponKey = sr_coupon_clean_key((string) ($data['coupon_key'] ?? ''));
    $title = sr_coupon_clean_text((string) ($data['title'] ?? ''), 120);
    $description = sr_coupon_clean_text((string) ($data['description'] ?? ''), 1000);
    $status = in_array((string) ($data['status'] ?? 'active'), sr_coupon_statuses(), true) ? (string) $data['status'] : 'active';
    $couponType = sr_coupon_clean_key((string) ($data['coupon_type'] ?? 'access'), 40);
    $targetType = array_key_exists((string) ($data['target_type'] ?? 'all'), sr_coupon_target_types()) ? (string) $data['target_type'] : 'all';
    $targetId = sr_coupon_clean_text((string) ($data['target_id'] ?? ''), 80);
    $refundablePolicy = array_key_exists((string) ($data['refundable_policy'] ?? 'none'), sr_coupon_refundable_policies()) ? (string) $data['refundable_policy'] : 'none';
    $maxUses = max(1, min(1000, (int) ($data['max_uses_per_issue'] ?? 1)));

    if ($couponKey === '' || $title === '') {
        throw new InvalidArgumentException('Coupon key and title are required.');
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_coupon_definitions
            (coupon_key, title, description, status, coupon_type, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES
            (:coupon_key, :title, :description, :status, :coupon_type, :target_type, :target_id, :refundable_policy, :max_uses_per_issue, NULL, NULL, :created_at, :updated_at)'
    );
    $stmt->execute([
        'coupon_key' => $couponKey,
        'title' => $title,
        'description' => $description,
        'status' => $status,
        'coupon_type' => $couponType !== '' ? $couponType : 'access',
        'target_type' => $targetType,
        'target_id' => $targetId,
        'refundable_policy' => $refundablePolicy,
        'max_uses_per_issue' => $maxUses,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_coupon_update_definition_status(PDO $pdo, int $definitionId, string $status): void
{
    if ($definitionId <= 0 || !in_array($status, sr_coupon_statuses(), true)) {
        throw new InvalidArgumentException('Coupon status is invalid.');
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_coupon_definitions
         SET status = :status,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => sr_now(),
        'id' => $definitionId,
    ]);
}

function sr_coupon_issue_to_account(PDO $pdo, int $definitionId, int $accountId, string $reason = '', ?int $issuedByAccountId = null, ?string $expiresAt = null): int
{
    if ($definitionId <= 0 || $accountId <= 0) {
        throw new InvalidArgumentException('Coupon definition and account are required.');
    }

    $definition = sr_coupon_definition_by_id($pdo, $definitionId);
    if (!is_array($definition) || (string) $definition['status'] !== 'active') {
        throw new InvalidArgumentException('Coupon definition is not active.');
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_coupon_issues
            (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, issued_at, expires_at, used_count, created_at, updated_at)
         VALUES
            (:coupon_definition_id, :account_id, :status, :issued_reason, :issued_by_account_id, :issued_at, :expires_at, 0, :created_at, :updated_at)'
    );
    $stmt->execute([
        'coupon_definition_id' => $definitionId,
        'account_id' => $accountId,
        'status' => 'active',
        'issued_reason' => sr_coupon_clean_text($reason, 255),
        'issued_by_account_id' => $issuedByAccountId !== null && $issuedByAccountId > 0 ? $issuedByAccountId : null,
        'issued_at' => $now,
        'expires_at' => $expiresAt,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $issueId = (int) $pdo->lastInsertId();
    sr_coupon_notify_issue_event($pdo, $issueId, 'issue.created', $issuedByAccountId);

    return $issueId;
}

function sr_coupon_update_issue_status(PDO $pdo, int $issueId, string $status, ?int $updatedByAccountId = null): void
{
    if ($issueId <= 0 || !in_array($status, sr_coupon_issue_statuses(), true)) {
        throw new InvalidArgumentException('Coupon issue status is invalid.');
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_coupon_issues
         SET status = :status,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => sr_now(),
        'id' => $issueId,
    ]);

    sr_coupon_notify_issue_event($pdo, $issueId, 'issue.status_updated', $updatedByAccountId, [
        'status_label' => sr_coupon_issue_status_label($status),
    ]);
}

function sr_coupon_issue_status_label(string $status): string
{
    $labels = [
        'active' => '사용 가능',
        'used' => '사용 완료',
        'expired' => '만료',
        'revoked' => '회수',
        'withdrawn_expired' => '탈퇴 만료',
        'refund_requested' => '환급 요청',
        'refunded' => '환급 완료',
    ];

    return $labels[$status] ?? $status;
}

function sr_coupon_active_account_issues(PDO $pdo, int $accountId, int $limit = 100): array
{
    if ($accountId <= 0) {
        return [];
    }

    $limit = max(1, min(300, $limit));
    $stmt = $pdo->prepare(
        "SELECT i.*, d.coupon_key, d.title, d.description, d.coupon_type, d.target_type, d.target_id, d.refundable_policy, d.max_uses_per_issue
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         WHERE i.account_id = :account_id
           AND i.status = 'active'
           AND d.status = 'active'
           AND (i.expires_at IS NULL OR i.expires_at >= :now_value)
         ORDER BY i.id DESC
         LIMIT " . $limit
    );
    $stmt->execute([
        'account_id' => $accountId,
        'now_value' => sr_now(),
    ]);

    return $stmt->fetchAll();
}

function sr_coupon_active_account_issue_count(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0 || !sr_coupon_tables_available($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         WHERE i.account_id = :account_id
           AND i.status = 'active'
           AND d.status = 'active'
           AND (i.expires_at IS NULL OR i.expires_at >= :now_value)"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'now_value' => sr_now(),
    ]);

    return (int) $stmt->fetchColumn();
}

function sr_coupon_tables_available(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM sr_coupon_issues LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_coupon_definitions LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_coupon_redemptions LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

function sr_coupon_issue_matches_target(array $issue, string $targetType, string $targetId): bool
{
    $definitionTargetType = (string) ($issue['target_type'] ?? '');
    $definitionTargetId = (string) ($issue['target_id'] ?? '');
    if ($definitionTargetType === 'all') {
        return true;
    }

    return $definitionTargetType === $targetType
        && ($definitionTargetId === '' || $definitionTargetId === $targetId);
}

function sr_coupon_has_redemption(PDO $pdo, int $accountId, string $dedupeKey): bool
{
    if ($accountId <= 0 || $dedupeKey === '') {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM sr_coupon_redemptions
         WHERE account_id = :account_id
           AND dedupe_key = :dedupe_key
           AND status = 'redeemed'
         LIMIT 1"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'dedupe_key' => $dedupeKey,
    ]);

    return is_array($stmt->fetch());
}

function sr_coupon_redeem_for_target(PDO $pdo, int $accountId, string $targetType, string $targetId, array $context = []): array
{
    $dedupeKey = sr_coupon_clean_text((string) ($context['dedupe_key'] ?? ''), 160);
    if ($accountId <= 0 || $targetType === '' || $dedupeKey === '' || !sr_coupon_tables_available($pdo)) {
        return ['allowed' => false, 'processed' => false, 'message' => ''];
    }

    if (sr_coupon_has_redemption($pdo, $accountId, $dedupeKey)) {
        return ['allowed' => true, 'processed' => false, 'already_redeemed' => true, 'message' => ''];
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT i.*, d.coupon_key, d.title, d.target_type, d.target_id, d.max_uses_per_issue, d.refundable_policy
             FROM sr_coupon_issues i
             INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
             WHERE i.account_id = :account_id
               AND i.status = 'active'
               AND d.status = 'active'
               AND (i.expires_at IS NULL OR i.expires_at >= :now_value)
             ORDER BY i.expires_at IS NULL ASC, i.expires_at ASC, i.id ASC
             FOR UPDATE"
        );
        $stmt->execute([
            'account_id' => $accountId,
            'now_value' => sr_now(),
        ]);
        $selectedIssue = null;
        foreach ($stmt->fetchAll() as $issue) {
            if (sr_coupon_issue_matches_target($issue, $targetType, $targetId)) {
                $selectedIssue = $issue;
                break;
            }
        }

        if (!is_array($selectedIssue)) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }
            return ['allowed' => false, 'processed' => false, 'message' => ''];
        }

        $now = sr_now();
        $stmt = $pdo->prepare(
            'INSERT INTO sr_coupon_redemptions
                (coupon_issue_id, coupon_definition_id, account_id, target_type, target_id, reference_module, reference_type, reference_id, dedupe_key, status, redeemed_at, created_at)
             VALUES
                (:coupon_issue_id, :coupon_definition_id, :account_id, :target_type, :target_id, :reference_module, :reference_type, :reference_id, :dedupe_key, :status, :redeemed_at, :created_at)'
        );
        $stmt->execute([
            'coupon_issue_id' => (int) $selectedIssue['id'],
            'coupon_definition_id' => (int) $selectedIssue['coupon_definition_id'],
            'account_id' => $accountId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reference_module' => sr_coupon_clean_key((string) ($context['reference_module'] ?? ''), 60),
            'reference_type' => sr_coupon_clean_text((string) ($context['reference_type'] ?? ''), 80),
            'reference_id' => sr_coupon_clean_text((string) ($context['reference_id'] ?? $targetId), 120),
            'dedupe_key' => $dedupeKey,
            'status' => 'redeemed',
            'redeemed_at' => $now,
            'created_at' => $now,
        ]);
        $redemptionId = (int) $pdo->lastInsertId();

        $usedCount = (int) $selectedIssue['used_count'] + 1;
        $maxUses = max(1, (int) $selectedIssue['max_uses_per_issue']);
        $newStatus = $usedCount >= $maxUses ? 'used' : 'active';
        $stmt = $pdo->prepare(
            'UPDATE sr_coupon_issues
             SET used_count = :used_count,
                 status = :status,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'used_count' => $usedCount,
            'status' => $newStatus,
            'updated_at' => $now,
            'id' => (int) $selectedIssue['id'],
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        }

        sr_coupon_notify_issue_event($pdo, (int) $selectedIssue['id'], 'redemption.redeemed', null, [
            'redemption_id' => $redemptionId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reference_module' => sr_coupon_clean_key((string) ($context['reference_module'] ?? ''), 60),
            'reference_type' => sr_coupon_clean_text((string) ($context['reference_type'] ?? ''), 80),
            'reference_id' => sr_coupon_clean_text((string) ($context['reference_id'] ?? $targetId), 120),
            'used_count' => $usedCount,
            'max_uses_per_issue' => $maxUses,
            'status_label' => sr_coupon_issue_status_label($newStatus),
            'created_at' => $now,
        ]);

        return [
            'allowed' => true,
            'processed' => true,
            'coupon_issue_id' => (int) $selectedIssue['id'],
            'coupon_definition_id' => (int) $selectedIssue['coupon_definition_id'],
            'coupon_title' => (string) $selectedIssue['title'],
            'message' => '',
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'coupon_redeem_for_target');
        }

        return ['allowed' => false, 'processed' => false, 'message' => ''];
    }
}

function sr_coupon_notify_issue_event(PDO $pdo, int $issueId, string $eventKey, ?int $createdByAccountId = null, array $metadata = []): ?int
{
    if (!sr_module_enabled($pdo, 'notification') || !is_file(SR_ROOT . '/modules/notification/helpers.php')) {
        return null;
    }

    $issue = sr_coupon_issue_by_id($pdo, $issueId);
    if (!is_array($issue)) {
        return null;
    }

    try {
        require_once SR_ROOT . '/modules/notification/helpers.php';
        if (!function_exists('sr_notification_create_account_event')) {
            return null;
        }

        return sr_notification_create_account_event($pdo, [
            'account_id' => (int) $issue['account_id'],
            'module_key' => 'coupon',
            'event_key' => $eventKey,
            'created_by_account_id' => $createdByAccountId !== null && $createdByAccountId > 0 ? $createdByAccountId : null,
            'metadata' => array_merge(sr_coupon_issue_notification_metadata($issue), $metadata),
        ]);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'coupon_issue_notification');
        return null;
    }
}

function sr_coupon_issue_notification_metadata(array $issue): array
{
    return [
        'coupon_issue_id' => (int) ($issue['id'] ?? 0),
        'coupon_definition_id' => (int) ($issue['coupon_definition_id'] ?? 0),
        'coupon_key' => (string) ($issue['coupon_key'] ?? ''),
        'coupon_title' => (string) ($issue['title'] ?? ''),
        'asset_label' => '쿠폰·이용권',
        'status' => (string) ($issue['status'] ?? ''),
        'status_label' => sr_coupon_issue_status_label((string) ($issue['status'] ?? '')),
        'issued_reason' => (string) ($issue['issued_reason'] ?? ''),
        'target_type' => (string) ($issue['target_type'] ?? ''),
        'target_id' => (string) ($issue['target_id'] ?? ''),
        'used_count' => (int) ($issue['used_count'] ?? 0),
        'max_uses_per_issue' => (int) ($issue['max_uses_per_issue'] ?? 1),
        'issued_at' => (string) ($issue['issued_at'] ?? ''),
        'expires_at' => (string) ($issue['expires_at'] ?? ''),
        'created_at' => sr_now(),
    ];
}

function sr_coupon_process_account_withdrawal(PDO $pdo, int $accountId): array
{
    if ($accountId <= 0 || !sr_coupon_tables_available($pdo)) {
        return [];
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        "UPDATE sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         SET i.status = CASE WHEN d.refundable_policy = 'refundable' THEN 'refund_requested' ELSE 'withdrawn_expired' END,
             i.updated_at = :updated_at
         WHERE i.account_id = :account_id
           AND i.status = 'active'"
    );
    $stmt->execute([
        'updated_at' => $now,
        'account_id' => $accountId,
    ]);

    return [
        'label' => '쿠폰·이용권',
        'amount' => $stmt->rowCount(),
        'process' => '소멸/환급 검토',
    ];
}
