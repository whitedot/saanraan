<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_coupon_create_definition(PDO $pdo, array $data): int
{
    $couponKey = sr_coupon_clean_key((string) ($data['coupon_key'] ?? ''));
    $title = sr_coupon_clean_text((string) ($data['title'] ?? ''), 120);
    $description = sr_coupon_clean_text((string) ($data['description'] ?? ''), 1000);
    $status = sr_coupon_optional_enum_value($data, 'status', sr_coupon_statuses(), 'active', '쿠폰 상태가 올바르지 않습니다.');
    $couponType = sr_coupon_clean_key((string) ($data['coupon_type'] ?? 'access'), 40);
    if ($couponType === '') {
        $couponType = 'access';
    }
    if (!array_key_exists($couponType, sr_coupon_types())) {
        throw new InvalidArgumentException('쿠폰 혜택 유형이 올바르지 않습니다.');
    }
    $discountAmount = 0;
    $discountPercent = 0;
    $discountCurrencyCode = '';
    if ($couponType === 'fixed_discount') {
        $discountAmountValue = $data['discount_amount'] ?? '';
        if (is_array($discountAmountValue)) {
            throw new InvalidArgumentException('정액 할인 금액은 1 이상 정수로 입력하세요.');
        }
        $discountAmountString = trim((string) $discountAmountValue);
        if ($discountAmountString === '' || preg_match('/\A[1-9][0-9]*\z/', $discountAmountString) !== 1) {
            throw new InvalidArgumentException('정액 할인 금액은 1 이상 정수로 입력하세요.');
        }
        $discountAmount = (int) $discountAmountString;
        if ($discountAmount < 1 || $discountAmount > 999999999) {
            throw new InvalidArgumentException('정액 할인 금액은 1부터 999999999 사이로 입력하세요.');
        }
        $discountCurrencyCode = strtoupper(trim((string) ($data['discount_currency_code'] ?? 'KRW')));
        if ($discountCurrencyCode === '') {
            $discountCurrencyCode = 'KRW';
        }
        if (preg_match('/\A[A-Z]{3}\z/', $discountCurrencyCode) !== 1) {
            throw new InvalidArgumentException('정액 할인 통화는 영문 3자리로 입력하세요.');
        }
    } elseif ($couponType === 'percent_discount') {
        $discountPercentValue = $data['discount_percent'] ?? '';
        if (is_array($discountPercentValue)) {
            throw new InvalidArgumentException('정률 할인율은 1부터 100 사이의 정수로 입력하세요.');
        }
        $discountPercentString = trim((string) $discountPercentValue);
        if ($discountPercentString === '' || preg_match('/\A[1-9][0-9]*\z/', $discountPercentString) !== 1) {
            throw new InvalidArgumentException('정률 할인율은 1부터 100 사이의 정수로 입력하세요.');
        }
        $discountPercent = (int) $discountPercentString;
        if ($discountPercent < 1 || $discountPercent > 100) {
            throw new InvalidArgumentException('정률 할인율은 1부터 100 사이의 정수로 입력하세요.');
        }
    }
    if ($couponType !== 'access' && !sr_coupon_definition_discount_columns_available($pdo)) {
        throw new InvalidArgumentException('쿠폰 할인 설정 업데이트를 먼저 적용하세요.');
    }
    $targetType = array_key_exists((string) ($data['target_type'] ?? 'all'), sr_coupon_target_types($pdo)) ? (string) $data['target_type'] : 'all';
    $targetId = sr_coupon_clean_text((string) ($data['target_id'] ?? ''), 80);
    $refundablePolicy = sr_coupon_optional_enum_value($data, 'refundable_policy', array_keys(sr_coupon_refundable_policies()), 'none', '쿠폰 환급 정책이 올바르지 않습니다.');
    $maxUsesValue = $data['max_uses_per_issue'] ?? '1';
    if (is_array($maxUsesValue)) {
        throw new InvalidArgumentException('사용 가능 횟수는 1부터 1000 사이의 정수로 입력하세요.');
    }
    $maxUsesString = trim((string) $maxUsesValue);
    if ($maxUsesString === '' || preg_match('/\A[1-9][0-9]*\z/', $maxUsesString) !== 1) {
        throw new InvalidArgumentException('사용 가능 횟수는 1부터 1000 사이의 정수로 입력하세요.');
    }
    $maxUses = (int) $maxUsesString;
    if ($maxUses < 1 || $maxUses > 1000) {
        throw new InvalidArgumentException('사용 가능 횟수는 1부터 1000 사이의 정수로 입력하세요.');
    }

    if (!sr_coupon_key_is_valid($couponKey)) {
        throw new InvalidArgumentException('쿠폰 키는 영문 소문자로 시작하고 소문자, 숫자, 밑줄만 사용할 수 있습니다.');
    }

    if ($title === '') {
        throw new InvalidArgumentException('쿠폰 키와 이름을 입력하세요.');
    }
    sr_coupon_assert_refundable_benefit_model($couponType, $refundablePolicy);
    sr_coupon_assert_refundable_target_contract($pdo, $targetType, $refundablePolicy);

    $stmt = $pdo->prepare('SELECT id FROM sr_coupon_definitions WHERE coupon_key = :coupon_key LIMIT 1');
    $stmt->execute(['coupon_key' => $couponKey]);
    if (is_array($stmt->fetch())) {
        throw new InvalidArgumentException('이미 사용 중인 쿠폰 키입니다.');
    }

    $now = sr_now();
    $validity = sr_coupon_definition_validity_payload($data, $now);
    $insertColumns = [
        'coupon_key',
        'title',
        'description',
        'status',
        'coupon_type',
        'target_type',
        'target_id',
        'refundable_policy',
        'max_uses_per_issue',
        'validity_policy',
        'validity_days',
        'valid_from',
        'valid_until',
        'created_at',
        'updated_at',
    ];
    $insertValues = [
        'coupon_key' => $couponKey,
        'title' => $title,
        'description' => $description,
        'status' => $status,
        'coupon_type' => $couponType,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'refundable_policy' => $refundablePolicy,
        'max_uses_per_issue' => $maxUses,
        'validity_policy' => (string) $validity['validity_policy'],
        'validity_days' => $validity['validity_days'],
        'valid_from' => $validity['valid_from'],
        'valid_until' => $validity['valid_until'],
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if (sr_coupon_definition_discount_columns_available($pdo)) {
        array_splice($insertColumns, 5, 0, ['discount_amount', 'discount_percent', 'discount_currency_code']);
        $insertValues['discount_amount'] = $discountAmount;
        $insertValues['discount_percent'] = $discountPercent;
        $insertValues['discount_currency_code'] = $discountCurrencyCode;
    }
    $placeholders = array_map(static fn (string $column): string => ':' . $column, $insertColumns);
    $stmt = $pdo->prepare(
        'INSERT INTO sr_coupon_definitions
            (' . implode(', ', $insertColumns) . ')
         VALUES
            (' . implode(', ', $placeholders) . ')'
    );
    $stmt->execute($insertValues);

    return (int) $pdo->lastInsertId();
}

function sr_coupon_update_definition_status(PDO $pdo, int $definitionId, string $status): void
{
    if ($definitionId <= 0 || !in_array($status, sr_coupon_statuses(), true)) {
        throw new InvalidArgumentException('쿠폰 종류의 상태가 올바르지 않습니다.');
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

function sr_coupon_unused_active_issue_ids_for_definition(PDO $pdo, int $definitionId): array
{
    if ($definitionId <= 0 || !sr_coupon_tables_available($pdo)) {
        return [];
    }

    sr_coupon_expire_active_issues($pdo);
    $stmt = $pdo->prepare(
        "SELECT i.id
         FROM sr_coupon_issues i
         WHERE i.coupon_definition_id = :definition_id
           AND i.status = 'active'
           AND i.used_count = 0
           AND (i.expires_at IS NULL OR i.expires_at >= :now_value)
         ORDER BY i.id ASC"
    );
    $stmt->execute([
        'definition_id' => $definitionId,
        'now_value' => sr_now(),
    ]);

    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

function sr_coupon_notify_definition_disabled_unused_issue_reclaims(PDO $pdo, array $definitionIds, ?int $createdByAccountId = null): array
{
    $definitionIds = array_values(array_unique(array_filter(array_map('intval', $definitionIds), static fn (int $definitionId): bool => $definitionId > 0)));
    if ($definitionIds === []) {
        return ['target_issue_count' => 0, 'notification_count' => 0, 'skipped' => true];
    }

    $settings = sr_coupon_settings($pdo);
    $caseSetting = sr_coupon_notification_setting_for_event($settings, 'issue.definition_disabled');
    if (!is_array($caseSetting) || empty($caseSetting['enabled'])) {
        return ['target_issue_count' => 0, 'notification_count' => 0, 'skipped' => true];
    }

    $eventKey = (string) ($settings['disabled_reclaim_notification_event_key'] ?? 'issue.definition_disabled');
    if (preg_match('/\A[a-z0-9_.-]{1,120}\z/', $eventKey) !== 1 || sr_coupon_notification_event_function($pdo) === '') {
        return ['target_issue_count' => 0, 'notification_count' => 0, 'skipped' => true];
    }
    $channels = sr_coupon_notification_channels_from_value($caseSetting['channels'] ?? ['site']);

    $targetIssueCount = 0;
    $notificationCount = 0;
    foreach ($definitionIds as $definitionId) {
        foreach (sr_coupon_unused_active_issue_ids_for_definition($pdo, $definitionId) as $issueId) {
            $targetIssueCount++;
            $notificationId = sr_coupon_notify_issue_event($pdo, $issueId, $eventKey, $createdByAccountId, [
                'definition_status' => 'disabled',
                'reclaim_reason' => 'coupon_definition_disabled',
            ], $channels);
            if ($notificationId !== null && $notificationId > 0) {
                $notificationCount++;
            }
        }
    }

    return [
        'target_issue_count' => $targetIssueCount,
        'notification_count' => $notificationCount,
        'skipped' => false,
    ];
}

function sr_coupon_issue_to_account(PDO $pdo, int $definitionId, int $accountId, string $reason = '', ?int $issuedByAccountId = null, ?string $expiresAt = null, array $claimContext = []): int
{
    if (!sr_coupon_usage_enabled($pdo)) {
        throw new RuntimeException('쿠폰·이용권을 사용하지 않도록 설정되어 있습니다.');
    }

    if ($definitionId <= 0 || $accountId <= 0) {
        throw new InvalidArgumentException('쿠폰 종류와 지급할 회원을 선택해 주세요.');
    }

    $definition = sr_coupon_definition_by_id($pdo, $definitionId);
    if (!is_array($definition) || !sr_coupon_definition_allows_issue((string) ($definition['status'] ?? ''))) {
        throw new InvalidArgumentException('사용 중인 쿠폰 종류만 지급할 수 있습니다.');
    }

    $now = sr_now();
    $claimType = sr_coupon_clean_key((string) ($claimContext['claim_type'] ?? 'manual'), 20);
    if (!in_array($claimType, ['manual', 'free', 'paid', 'admin'], true)) {
        $claimType = 'manual';
    }
    $validityWindow = sr_coupon_issue_validity_window($definition, $now, $expiresAt, $claimContext);
    $snapshotJson = null;
    if (isset($claimContext['claim_snapshot_json']) && is_string($claimContext['claim_snapshot_json'])) {
        $snapshotJson = $claimContext['claim_snapshot_json'];
    } elseif (isset($claimContext['claim_snapshot']) && is_array($claimContext['claim_snapshot'])) {
        $encodedSnapshot = json_encode($claimContext['claim_snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $snapshotJson = is_string($encodedSnapshot) ? $encodedSnapshot : null;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_coupon_issues
            (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, claim_type, claim_campaign_id, claim_log_id, nominal_price_amount, nominal_price_currency_code, asset_reference_module, asset_reference_type, asset_reference_id, claim_snapshot_json, issued_at, starts_at, expires_at, used_count, created_at, updated_at)
         VALUES
            (:coupon_definition_id, :account_id, :status, :issued_reason, :issued_by_account_id, :claim_type, :claim_campaign_id, :claim_log_id, :nominal_price_amount, :nominal_price_currency_code, :asset_reference_module, :asset_reference_type, :asset_reference_id, :claim_snapshot_json, :issued_at, :starts_at, :expires_at, 0, :created_at, :updated_at)'
    );
    $stmt->execute([
        'coupon_definition_id' => $definitionId,
        'account_id' => $accountId,
        'status' => 'active',
        'issued_reason' => sr_coupon_clean_text($reason, 255),
        'issued_by_account_id' => $issuedByAccountId !== null && $issuedByAccountId > 0 ? $issuedByAccountId : null,
        'claim_type' => $claimType,
        'claim_campaign_id' => isset($claimContext['claim_campaign_id']) && (int) $claimContext['claim_campaign_id'] > 0 ? (int) $claimContext['claim_campaign_id'] : null,
        'claim_log_id' => isset($claimContext['claim_log_id']) && (int) $claimContext['claim_log_id'] > 0 ? (int) $claimContext['claim_log_id'] : null,
        'nominal_price_amount' => max(0, (int) ($claimContext['nominal_price_amount'] ?? 0)),
        'nominal_price_currency_code' => sr_coupon_clean_currency_code((string) ($claimContext['nominal_price_currency_code'] ?? '')),
        'asset_reference_module' => sr_coupon_clean_key((string) ($claimContext['asset_reference_module'] ?? ''), 60),
        'asset_reference_type' => sr_coupon_clean_text((string) ($claimContext['asset_reference_type'] ?? ''), 80),
        'asset_reference_id' => sr_coupon_clean_text((string) ($claimContext['asset_reference_id'] ?? ''), 120),
        'claim_snapshot_json' => $snapshotJson,
        'issued_at' => $now,
        'starts_at' => $validityWindow['starts_at'],
        'expires_at' => $validityWindow['expires_at'],
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $issueId = (int) $pdo->lastInsertId();
    sr_coupon_notify_issue_event($pdo, $issueId, 'issue.created', $issuedByAccountId);

    return $issueId;
}

function sr_coupon_public_claim_campaign_count(PDO $pdo): int
{
    if (!sr_coupon_usage_enabled($pdo) || !sr_coupon_claim_tables_available($pdo)) {
        return 0;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM sr_coupon_claim_campaigns c
         INNER JOIN sr_coupon_definitions d ON d.id = c.coupon_definition_id
         WHERE c.status = 'active'
           AND c.visibility = 'public'
           AND c.exposure_surfaces_json LIKE :surface_like
           AND (c.starts_at IS NULL OR c.starts_at <= :starts_now)
           AND (c.ends_at IS NULL OR c.ends_at >= :ends_now)
           AND d.status = 'active'"
    );
    $stmt->execute([
        'surface_like' => '%"coupon_zone"%',
        'starts_now' => $now,
        'ends_now' => $now,
    ]);

    return max(0, (int) $stmt->fetchColumn());
}

function sr_coupon_public_claim_campaigns(PDO $pdo, int $accountId = 0, int $limit = 50, int $offset = 0): array
{
    if (!sr_coupon_usage_enabled($pdo) || !sr_coupon_claim_tables_available($pdo)) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $now = sr_now();
    $stmt = $pdo->prepare(
        "SELECT c.*, d.coupon_key, d.title AS coupon_title, d.description AS coupon_description, d.status AS coupon_status, d.target_type, d.target_id, d.max_uses_per_issue
         FROM sr_coupon_claim_campaigns c
         INNER JOIN sr_coupon_definitions d ON d.id = c.coupon_definition_id
         WHERE c.status = 'active'
           AND c.visibility = 'public'
           AND c.exposure_surfaces_json LIKE :surface_like
           AND (c.starts_at IS NULL OR c.starts_at <= :starts_now)
           AND (c.ends_at IS NULL OR c.ends_at >= :ends_now)
           AND d.status = 'active'
         ORDER BY c.id DESC
         LIMIT " . $limit . " OFFSET " . $offset
    );
    $stmt->execute([
        'surface_like' => '%"coupon_zone"%',
        'starts_now' => $now,
        'ends_now' => $now,
    ]);

    $campaigns = [];
    foreach ($stmt->fetchAll() as $campaign) {
        $campaign['claim_state'] = sr_coupon_claim_campaign_state($pdo, $campaign, $accountId);
        $campaigns[] = $campaign;
    }

    return $campaigns;
}

function sr_coupon_public_claim_campaign(PDO $pdo, string $campaignKey, int $accountId = 0, array $allowedSurfaces = ['coupon_zone', 'direct_link', 'content_embed']): ?array
{
    $campaign = sr_coupon_claim_campaign_by_key($pdo, $campaignKey);
    if (!is_array($campaign)) {
        return null;
    }
    if ((string) ($campaign['status'] ?? '') !== 'active' || (string) ($campaign['visibility'] ?? '') !== 'public') {
        return null;
    }
    if ((string) ($campaign['coupon_status'] ?? '') !== 'active') {
        return null;
    }

    $now = sr_now();
    if ((string) ($campaign['starts_at'] ?? '') !== '' && strcmp((string) $campaign['starts_at'], $now) > 0) {
        return null;
    }
    if ((string) ($campaign['ends_at'] ?? '') !== '' && strcmp((string) $campaign['ends_at'], $now) < 0) {
        return null;
    }

    $surfaces = array_fill_keys(sr_coupon_claim_surfaces_from_value($campaign['exposure_surfaces_json'] ?? ''), true);
    $claimSource = '';
    foreach ($allowedSurfaces as $surface) {
        $surface = (string) $surface;
        if (isset($surfaces[$surface])) {
            $claimSource = $surface;
            break;
        }
    }
    if ($claimSource === '') {
        return null;
    }

    $campaign['claim_source'] = $claimSource;
    $campaign['claim_state'] = sr_coupon_claim_campaign_state($pdo, $campaign, $accountId);

    return $campaign;
}

function sr_coupon_claim_campaign_state(PDO $pdo, array $campaign, int $accountId = 0): array
{
    $campaignId = (int) ($campaign['id'] ?? 0);
    if ($campaignId <= 0 || !sr_coupon_claim_tables_available($pdo)) {
        return ['claimable' => false, 'remaining' => null, 'claimed_count' => 0, 'message' => ''];
    }

    $now = sr_now();
    $occupiedCondition = sr_coupon_claim_campaign_occupied_condition(':now_value');
    $params = ['campaign_id' => $campaignId, 'now_value' => $now];
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count_value
         FROM sr_coupon_claim_logs
         WHERE campaign_id = :campaign_id
           AND (' . $occupiedCondition . ')'
    );
    $stmt->execute($params);
    $occupiedCount = (int) $stmt->fetchColumn();
    $totalLimit = (int) ($campaign['total_claim_limit'] ?? 0);
    $remaining = $totalLimit > 0 ? max(0, $totalLimit - $occupiedCount) : null;

    $claimedCount = 0;
    if ($accountId > 0) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS count_value
             FROM sr_coupon_claim_logs
             WHERE campaign_id = :campaign_id
               AND account_id = :account_id
               AND (' . $occupiedCondition . ')'
        );
        $stmt->execute([
            'campaign_id' => $campaignId,
            'account_id' => $accountId,
            'now_value' => $now,
        ]);
        $claimedCount = (int) $stmt->fetchColumn();
    }

    $perLimit = max(1, (int) ($campaign['per_account_limit'] ?? 1));
    $claimable = ($remaining === null || $remaining > 0) && ($accountId <= 0 || $claimedCount < $perLimit);
    $message = '';
    if ($remaining !== null && $remaining <= 0) {
        $message = '준비된 쿠폰이 모두 발급되었습니다.';
    } elseif ($accountId > 0 && $claimedCount >= $perLimit) {
        $message = '이미 받을 수 있는 수량을 모두 받았습니다.';
    }

    return [
        'claimable' => $claimable,
        'remaining' => $remaining,
        'occupied_count' => $occupiedCount,
        'claimed_count' => $claimedCount,
        'message' => $message,
    ];
}

function sr_coupon_claim_issue_expires_at(array $campaign): ?string
{
    $issueExpiresAt = (string) ($campaign['issue_expires_at'] ?? '');
    if ($issueExpiresAt !== '') {
        return $issueExpiresAt;
    }

    $days = (int) ($campaign['issue_expires_in_days'] ?? 0);
    if ($days < 1) {
        return null;
    }

    return (new DateTimeImmutable(sr_now()))->modify('+' . (string) $days . ' days')->format('Y-m-d H:i:s');
}

function sr_coupon_self_expire_claims(PDO $pdo, int $campaignId, int $accountId): int
{
    if ($campaignId <= 0 || $accountId <= 0) {
        return 0;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        "UPDATE sr_coupon_claim_logs
         SET status = 'expired',
             occupying_account_id = NULL,
             updated_at = :updated_at
         WHERE campaign_id = :campaign_id
           AND account_id = :account_id
           AND status IN ('reserved', 'pending_payment')
           AND reserved_until IS NOT NULL
           AND reserved_until < :now_value"
    );
    $stmt->execute([
        'updated_at' => $now,
        'campaign_id' => $campaignId,
        'account_id' => $accountId,
        'now_value' => $now,
    ]);

    return $stmt->rowCount();
}

function sr_coupon_claim_free_campaign(PDO $pdo, string $campaignKey, int $accountId, string $intentToken, string $claimSource = 'coupon_zone', array $sourceContext = []): array
{
    if (!sr_coupon_usage_enabled($pdo)) {
        throw new InvalidArgumentException('쿠폰·이용권을 사용하지 않도록 설정되어 있습니다.');
    }

    if ($accountId <= 0) {
        throw new InvalidArgumentException('로그인이 필요한 쿠폰입니다.');
    }
    if (!sr_coupon_claim_tables_available($pdo)) {
        throw new InvalidArgumentException('쿠폰 발급 캠페인 업데이트를 먼저 적용하세요.');
    }

    $campaign = sr_coupon_claim_campaign_by_key($pdo, $campaignKey);
    if (!is_array($campaign)) {
        throw new InvalidArgumentException('쿠폰 캠페인을 찾을 수 없습니다.');
    }
    if ((string) ($campaign['claim_type'] ?? '') !== 'free') {
        throw new InvalidArgumentException('무료 발급 캠페인만 바로 받을 수 있습니다.');
    }

    $intentToken = sr_coupon_clean_text($intentToken, 120);
    if ($intentToken === '') {
        throw new InvalidArgumentException('쿠폰 발급 요청 토큰이 올바르지 않습니다.');
    }
    $dedupeKey = 'coupon_claim:' . (string) (int) $campaign['id'] . ':' . (string) $accountId . ':' . $intentToken;
    $dedupeHash = sr_coupon_claim_dedupe_hash($dedupeKey);
    $claimSource = in_array($claimSource, sr_coupon_claim_surfaces(), true) ? $claimSource : 'coupon_zone';

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $campaign = sr_coupon_claim_campaign_by_key($pdo, $campaignKey, true);
        if (!is_array($campaign)) {
            throw new InvalidArgumentException('쿠폰 캠페인을 찾을 수 없습니다.');
        }

        sr_coupon_validate_claim_campaign($campaign, $claimSource);
        sr_coupon_self_expire_claims($pdo, (int) $campaign['id'], $accountId);

        $existing = sr_coupon_claim_log_by_dedupe_hash($pdo, (int) $campaign['id'], $dedupeHash);
        if (is_array($existing) && (string) ($existing['status'] ?? '') === 'issued') {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return [
                'claimed' => true,
                'already_claimed' => true,
                'coupon_issue_id' => (int) ($existing['coupon_issue_id'] ?? 0),
                'claim_log_id' => (int) ($existing['id'] ?? 0),
            ];
        }
        if (is_array($existing)) {
            throw new InvalidArgumentException('이전 발급 요청이 아직 처리 중입니다.');
        }

        sr_coupon_assert_claim_limits($pdo, $campaign, $accountId);

        $now = sr_now();
        $stmt = $pdo->prepare(
            'INSERT INTO sr_coupon_claim_logs
                (campaign_id, coupon_definition_id, account_id, coupon_issue_id, claim_source, source_context_json, dedupe_key, dedupe_hash, occupying_account_id, status, reserved_until, failure_code, failure_message, created_at, issued_at, updated_at)
             VALUES
                (:campaign_id, :coupon_definition_id, :account_id, NULL, :claim_source, :source_context_json, :dedupe_key, :dedupe_hash, :occupying_account_id, :status, NULL, \'\', \'\', :created_at, NULL, :updated_at)'
        );
        $stmt->execute([
            'campaign_id' => (int) $campaign['id'],
            'coupon_definition_id' => (int) $campaign['coupon_definition_id'],
            'account_id' => $accountId,
            'claim_source' => $claimSource,
            'source_context_json' => json_encode($sourceContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'dedupe_key' => $dedupeKey,
            'dedupe_hash' => $dedupeHash,
            'occupying_account_id' => (int) ($campaign['per_account_limit'] ?? 1) === 1 ? $accountId : null,
            'status' => 'reserved',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $claimLogId = (int) $pdo->lastInsertId();

        $issueId = sr_coupon_issue_to_account(
            $pdo,
            (int) $campaign['coupon_definition_id'],
            $accountId,
            'claim_campaign:' . (string) ($campaign['campaign_key'] ?? ''),
            null,
            null,
            [
                'claim_type' => 'free',
                'claim_campaign_id' => (int) $campaign['id'],
                'claim_log_id' => $claimLogId,
                'issue_expires_at' => (string) ($campaign['issue_expires_at'] ?? ''),
                'issue_expires_in_days' => $campaign['issue_expires_in_days'] ?? null,
                'clamp_starts_at_to_issued_at' => true,
                'claim_snapshot' => [
                    'schema_version' => 'coupon_claim_snapshot_v1',
                    'claim_type' => 'free',
                    'campaign_id' => (int) $campaign['id'],
                    'campaign_key' => (string) ($campaign['campaign_key'] ?? ''),
                    'claim_log_id' => $claimLogId,
                    'nominal_price' => [
                        'amount' => 0,
                        'currency_code' => '',
                    ],
                    'charged_allocations' => [],
                    'settlement_kind' => 'free',
                ],
            ]
        );

        $stmt = $pdo->prepare(
            "UPDATE sr_coupon_claim_logs
             SET coupon_issue_id = :coupon_issue_id,
                 status = 'issued',
                 issued_at = :issued_at,
                 updated_at = :updated_at
             WHERE id = :id"
        );
        $stmt->execute([
            'coupon_issue_id' => $issueId,
            'issued_at' => $now,
            'updated_at' => $now,
            'id' => $claimLogId,
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'claimed' => true,
            'already_claimed' => false,
            'coupon_issue_id' => $issueId,
            'claim_log_id' => $claimLogId,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
