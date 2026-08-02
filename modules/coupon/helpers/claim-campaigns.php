<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_coupon_target_types(?PDO $pdo = null): array
{
    $targetTypes = [
        'all' => '전체',
    ];

    if ($pdo === null) {
        return $targetTypes;
    }

    foreach (sr_coupon_target_contracts($pdo) as $targetType => $target) {
        $targetTypes[(string) $targetType] = (string) ($target['label'] ?? $targetType);
    }

    return $targetTypes;
}

function sr_coupon_target_display(string $targetType, string $targetId, ?PDO $pdo = null): string
{
    $targetTypes = sr_coupon_target_types($pdo);
    $label = (string) ($targetTypes[$targetType] ?? $targetType);
    if ($targetType === 'all' || $targetId === '') {
        return $label;
    }

    return $label . ' #' . $targetId;
}

function sr_coupon_reference_display(string $moduleKey, string $referenceType, string $referenceId): string
{
    $moduleLabels = [
        'content' => '콘텐츠',
        'community' => '커뮤니티',
        'quiz' => '퀴즈',
        'survey' => '설문',
    ];
    $referenceLabels = [
        'content.view' => '콘텐츠 열람',
        'content.download' => '콘텐츠 다운로드',
        'content.action' => '콘텐츠 완료 처리',
        'community.post' => '커뮤니티 게시글',
        'community.comment' => '커뮤니티 댓글',
        'quiz.attempt' => '퀴즈 응시',
        'survey.response' => '설문 응답',
    ];

    $parts = [];
    if ($moduleKey !== '') {
        $parts[] = (string) ($moduleLabels[$moduleKey] ?? $moduleKey);
    }
    if ($referenceType !== '') {
        $parts[] = function_exists('sr_admin_code_label')
            ? sr_admin_code_label($referenceType, 'reference_type')
            : (string) ($referenceLabels[$referenceType] ?? $referenceType);
    }
    if ($referenceId !== '') {
        $parts[] = '#' . $referenceId;
    }

    return implode(' ', $parts);
}

function sr_coupon_refundable_policies(): array
{
    return [
        'none' => '환급 없음',
        'refundable' => '환급 가능',
    ];
}

function sr_coupon_claim_campaign_statuses(): array
{
    return ['draft', 'active', 'paused', 'ended'];
}

function sr_coupon_claim_campaign_status_label(string $status): string
{
    return match ($status) {
        'draft' => '초안',
        'active' => '진행',
        'paused' => '중지',
        'ended' => '종료',
        default => $status,
    };
}

function sr_coupon_claim_types(): array
{
    return ['free', 'paid'];
}

function sr_coupon_claim_type_label(string $claimType): string
{
    return match ($claimType) {
        'paid' => '유료',
        default => '무료',
    };
}

function sr_coupon_asset_options(PDO $pdo): array
{
    if (!function_exists('sr_member_ledger_asset_definitions')) {
        require_once SR_ROOT . '/modules/member/helpers/assets.php';
    }

    return sr_member_ledger_asset_definitions($pdo);
}

function sr_coupon_asset_module_keys_from_value(PDO $pdo, mixed $value): array
{
    $assetOptions = sr_coupon_asset_options($pdo);
    $rawValues = is_array($value) ? $value : json_decode((string) $value, true);
    if (!is_array($rawValues)) {
        $rawValues = preg_split('/[\s,]+/', (string) $value);
    }

    $selected = [];
    foreach (is_array($rawValues) ? $rawValues : [] as $rawValue) {
        $assetModule = sr_coupon_clean_key((string) $rawValue, 60);
        if (isset($assetOptions[$assetModule])) {
            $selected[$assetModule] = true;
        }
    }

    $ordered = [];
    foreach (array_keys($assetOptions) as $assetModule) {
        if (isset($selected[$assetModule])) {
            $ordered[] = $assetModule;
        }
    }

    return $ordered;
}

function sr_coupon_asset_modules_json(array $assetModules): string
{
    $encoded = json_encode(array_values($assetModules), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return is_string($encoded) ? $encoded : '[]';
}

function sr_coupon_assert_paid_claim_asset_purchase_power(PDO $pdo, array $assetModules, string $priceCurrencyCode): void
{
    $assetOptions = sr_coupon_asset_options($pdo);
    $priceCurrencyCode = function_exists('sr_normalize_currency_code')
        ? sr_normalize_currency_code($priceCurrencyCode)
        : strtoupper(trim($priceCurrencyCode));
    $priceMinUnit = function_exists('sr_currency_min_unit') ? sr_currency_min_unit($priceCurrencyCode) : 1;
    if ($priceCurrencyCode === '' || $priceMinUnit < 1) {
        throw new InvalidArgumentException('유료 발급 통화가 지원되지 않습니다.');
    }

    foreach ($assetModules as $assetModule) {
        $assetModule = (string) $assetModule;
        if (!isset($assetOptions[$assetModule])) {
            throw new InvalidArgumentException('유료 발급에 사용할 포인트/금액 항목을 다시 선택하세요.');
        }

        $purchasePower = function_exists('sr_member_asset_purchase_power_from_contract')
            ? sr_member_asset_purchase_power_from_contract($pdo, $assetOptions[$assetModule])
            : (is_array($assetOptions[$assetModule]['purchase_power'] ?? null) ? $assetOptions[$assetModule]['purchase_power'] : []);
        $assetUnits = (int) ($purchasePower['asset_units'] ?? 0);
        $settlementUnits = (int) ($purchasePower['settlement_units'] ?? 0);
        $settlementCurrency = function_exists('sr_normalize_currency_code')
            ? sr_normalize_currency_code((string) ($purchasePower['settlement_currency'] ?? ''))
            : strtoupper(trim((string) ($purchasePower['settlement_currency'] ?? '')));

        if ($assetUnits < 1 || $settlementUnits < 1 || $settlementCurrency !== $priceCurrencyCode) {
            throw new InvalidArgumentException('유료 발급 통화와 허용 포인트/금액 항목의 환산 통화가 일치해야 합니다.');
        }
    }
}

function sr_coupon_asset_module_labels(PDO $pdo, mixed $value): string
{
    $assetOptions = sr_coupon_asset_options($pdo);
    $labels = [];
    foreach (sr_coupon_asset_module_keys_from_value($pdo, $value) as $assetModule) {
        $labels[] = (string) ($assetOptions[$assetModule]['label'] ?? $assetModule);
    }

    return $labels !== [] ? implode(', ', $labels) : '없음';
}

function sr_coupon_claim_surfaces(): array
{
    return ['coupon_zone', 'direct_link', 'popup_layer', 'content_embed'];
}

function sr_coupon_claim_log_statuses(): array
{
    return ['reserved', 'pending_payment', 'issued', 'failed', 'cancelled', 'expired'];
}

function sr_coupon_claim_log_status_label(string $status): string
{
    return match ($status) {
        'reserved' => '예약',
        'pending_payment' => '결제 대기',
        'issued' => '발급 완료',
        'failed' => '실패',
        'cancelled' => '취소',
        'expired' => '만료',
        'expired_unmaterialized' => '만료(미정리)',
        default => $status,
    };
}

function sr_coupon_claim_source_label(string $source): string
{
    return match ($source) {
        'coupon_zone' => '쿠폰존',
        'direct_link' => '직접 링크',
        'popup_layer' => '팝업레이어',
        'content_embed' => '본문 임베드',
        'admin' => '관리자',
        default => $source,
    };
}

function sr_coupon_claim_tables_available(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM sr_coupon_claim_campaigns LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_coupon_claim_logs LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

function sr_coupon_claim_log_asset_reference_columns_available(PDO $pdo): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }

    try {
        $stmt = $pdo->query('SELECT asset_reference_module, asset_reference_type, asset_reference_id FROM sr_coupon_claim_logs LIMIT 1');
        $available = $stmt !== false;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function sr_coupon_claim_dedupe_hash(string $dedupeKey): string
{
    return hash('sha256', $dedupeKey);
}

function sr_coupon_claim_surfaces_json(array $surfaces): string
{
    $allowed = array_fill_keys(sr_coupon_claim_surfaces(), true);
    $values = [];
    foreach ($surfaces as $surface) {
        $surface = (string) $surface;
        if (isset($allowed[$surface])) {
            $values[$surface] = true;
        }
    }

    return json_encode(array_keys($values), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function sr_coupon_claim_surfaces_from_value(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($value)) {
        return [];
    }

    $allowed = array_fill_keys(sr_coupon_claim_surfaces(), true);
    $surfaces = [];
    foreach ($value as $surface) {
        $surface = (string) $surface;
        if (isset($allowed[$surface])) {
            $surfaces[$surface] = true;
        }
    }

    return array_keys($surfaces);
}

function sr_coupon_claim_datetime_or_null(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $normalized = str_replace('T', ' ', substr($value, 0, 19));
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', strlen($normalized) === 16 ? $normalized . ':00' : $normalized);
    if (!$date instanceof DateTimeImmutable) {
        throw new InvalidArgumentException('날짜와 시간 형식이 올바르지 않습니다.');
    }

    return $date->format('Y-m-d H:i:s');
}

function sr_coupon_definition_validity_payload(array $data, string $now): array
{
    $policy = sr_coupon_optional_enum_value(
        $data,
        'validity_policy',
        array_keys(sr_coupon_validity_policies()),
        'none',
        '쿠폰 사용기간 정책이 올바르지 않습니다.'
    );
    $validFrom = sr_coupon_claim_datetime_or_null((string) ($data['valid_from'] ?? ''));
    $validUntil = sr_coupon_claim_datetime_or_null((string) ($data['valid_until'] ?? ''));
    $validityDays = null;

    if ($policy === 'none') {
        $validFrom = null;
        $validUntil = null;
    } elseif ($policy === 'fixed_range') {
        if ($validFrom === null || $validUntil === null) {
            throw new InvalidArgumentException('고정 사용 기간은 시작 시각과 만료 시각을 모두 입력하세요.');
        }
        if (strcmp($validFrom, $validUntil) >= 0) {
            throw new InvalidArgumentException('고정 사용 기간의 만료 시각은 시작 시각 이후여야 합니다.');
        }
        if (strcmp($validUntil, $now) <= 0) {
            throw new InvalidArgumentException('쿠폰 만료 시각은 현재 이후여야 합니다.');
        }
    } elseif ($policy === 'fixed_expiry') {
        $validFrom = null;
        if ($validUntil === null) {
            throw new InvalidArgumentException('고정 만료일 정책은 만료 시각을 입력하세요.');
        }
        if (strcmp($validUntil, $now) <= 0) {
            throw new InvalidArgumentException('쿠폰 만료 시각은 현재 이후여야 합니다.');
        }
    } elseif ($policy === 'relative_days') {
        $validFrom = null;
        $validUntil = null;
        $validityDays = sr_coupon_claim_positive_int($data['validity_days'] ?? '', 3650, '발급 후 사용일수');
    }

    return [
        'validity_policy' => $policy,
        'validity_days' => $validityDays,
        'valid_from' => $validFrom,
        'valid_until' => $validUntil,
    ];
}

function sr_coupon_issue_validity_window(array $definition, string $issuedAt, ?string $expiresAtOverride = null, array $claimContext = []): array
{
    $policy = (string) ($definition['validity_policy'] ?? 'none');
    if (!array_key_exists($policy, sr_coupon_validity_policies())) {
        $policy = 'none';
    }

    $startsAt = null;
    $expiresAt = null;
    if ($policy === 'fixed_range') {
        $startsAt = trim((string) ($definition['valid_from'] ?? '')) ?: null;
        $expiresAt = trim((string) ($definition['valid_until'] ?? '')) ?: null;
    } elseif ($policy === 'fixed_expiry') {
        $expiresAt = trim((string) ($definition['valid_until'] ?? '')) ?: null;
    } elseif ($policy === 'relative_days') {
        $days = (int) ($definition['validity_days'] ?? 0);
        if ($days > 0) {
            $expiresAt = (new DateTimeImmutable($issuedAt))->modify('+' . (string) $days . ' days')->format('Y-m-d H:i:s');
        }
    }

    $contextExpiresAt = null;
    if (isset($claimContext['issue_expires_at'])) {
        $contextExpiresAt = sr_coupon_claim_datetime_or_null((string) $claimContext['issue_expires_at']);
    }
    if ($contextExpiresAt === null && isset($claimContext['issue_expires_in_days'])) {
        $days = sr_coupon_claim_optional_positive_int($claimContext['issue_expires_in_days'], 3650, '발급본 만료일수');
        if ($days !== null) {
            $contextExpiresAt = (new DateTimeImmutable($issuedAt))->modify('+' . (string) $days . ' days')->format('Y-m-d H:i:s');
        }
    }
    if ($contextExpiresAt !== null) {
        $expiresAt = $contextExpiresAt;
    } elseif ($expiresAtOverride !== null && trim($expiresAtOverride) !== '') {
        $expiresAt = sr_coupon_claim_datetime_or_null($expiresAtOverride);
    }

    if (!empty($claimContext['clamp_starts_at_to_issued_at']) && $startsAt !== null && strcmp($startsAt, $issuedAt) > 0) {
        $startsAt = $issuedAt;
    }

    $effectiveStart = $startsAt ?? $issuedAt;
    if ($expiresAt !== null && strcmp($expiresAt, $issuedAt) <= 0) {
        throw new InvalidArgumentException('이미 만료된 쿠폰은 지급할 수 없습니다.');
    }
    if ($expiresAt !== null && strcmp($expiresAt, $effectiveStart) <= 0) {
        throw new InvalidArgumentException('쿠폰 만료 시각은 사용 시작 시각 이후여야 합니다.');
    }

    return [
        'starts_at' => $startsAt,
        'expires_at' => $expiresAt,
    ];
}

function sr_coupon_assert_definition_issueable_now(PDO $pdo, int $definitionId): void
{
    $definition = sr_coupon_definition_by_id($pdo, $definitionId);
    if (!is_array($definition) || !sr_coupon_definition_allows_issue((string) ($definition['status'] ?? ''))) {
        throw new InvalidArgumentException('사용 중인 쿠폰 종류만 지급할 수 있습니다.');
    }

    sr_coupon_issue_validity_window($definition, sr_now());
}

function sr_coupon_claim_campaign_by_key(PDO $pdo, string $campaignKey, bool $forUpdate = false): ?array
{
    $campaignKey = sr_coupon_clean_key($campaignKey);
    if (!sr_coupon_key_is_valid($campaignKey) || !sr_coupon_claim_tables_available($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT c.*, d.coupon_key, d.title AS coupon_title, d.description AS coupon_description, d.status AS coupon_status, d.target_type, d.target_id, d.max_uses_per_issue
         FROM sr_coupon_claim_campaigns c
         INNER JOIN sr_coupon_definitions d ON d.id = c.coupon_definition_id
         WHERE c.campaign_key = :campaign_key
         LIMIT 1' . ($forUpdate ? sr_coupon_for_update_clause($pdo) : '')
    );
    $stmt->execute(['campaign_key' => $campaignKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_coupon_claim_campaign_by_id(PDO $pdo, int $campaignId): ?array
{
    if ($campaignId <= 0 || !sr_coupon_claim_tables_available($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT c.*, d.coupon_key, d.title AS coupon_title, d.description AS coupon_description, d.status AS coupon_status, d.target_type, d.target_id, d.max_uses_per_issue
         FROM sr_coupon_claim_campaigns c
         INNER JOIN sr_coupon_definitions d ON d.id = c.coupon_definition_id
         WHERE c.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $campaignId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_coupon_public_flash_result(array $result): void
{
    $_SESSION['sr_coupon_public_flash'] = [
        'errors' => array_values(array_map('strval', $result['errors'] ?? [])),
        'notice' => (string) ($result['notice'] ?? ''),
    ];
}

function sr_coupon_public_pop_flash_result(): array
{
    $result = is_array($_SESSION['sr_coupon_public_flash'] ?? null)
        ? $_SESSION['sr_coupon_public_flash']
        : ['errors' => [], 'notice' => ''];
    unset($_SESSION['sr_coupon_public_flash']);

    return [
        'errors' => array_values(array_map('strval', $result['errors'] ?? [])),
        'notice' => (string) ($result['notice'] ?? ''),
    ];
}

function sr_coupon_public_claim_intent_key(int $campaignId, int $accountId): string
{
    return (string) max(0, $accountId) . ':' . (string) $campaignId;
}

function sr_coupon_public_claim_intent_token(int $campaignId, int $accountId = 0): string
{
    if ($campaignId <= 0) {
        return '';
    }

    if (!isset($_SESSION['sr_coupon_claim_intents']) || !is_array($_SESSION['sr_coupon_claim_intents'])) {
        $_SESSION['sr_coupon_claim_intents'] = [];
    }

    $key = sr_coupon_public_claim_intent_key($campaignId, $accountId);
    $token = (string) ($_SESSION['sr_coupon_claim_intents'][$key] ?? '');
    if ($token === '') {
        $token = bin2hex(random_bytes(16));
        $_SESSION['sr_coupon_claim_intents'][$key] = $token;
    }

    return $token;
}

function sr_coupon_public_claim_intent_token_matches(int $campaignId, int $accountId, string $token): bool
{
    $token = trim($token);
    if ($campaignId <= 0 || $accountId <= 0 || $token === '') {
        return false;
    }
    if (!isset($_SESSION['sr_coupon_claim_intents']) || !is_array($_SESSION['sr_coupon_claim_intents'])) {
        return false;
    }

    $current = (string) ($_SESSION['sr_coupon_claim_intents'][sr_coupon_public_claim_intent_key($campaignId, $accountId)] ?? '');

    return $current !== '' && hash_equals($current, $token);
}

function sr_coupon_public_rotate_claim_intent_token(int $campaignId, int $accountId = 0): string
{
    if ($campaignId <= 0) {
        return '';
    }

    if (!isset($_SESSION['sr_coupon_claim_intents']) || !is_array($_SESSION['sr_coupon_claim_intents'])) {
        $_SESSION['sr_coupon_claim_intents'] = [];
    }

    $token = bin2hex(random_bytes(16));
    $_SESSION['sr_coupon_claim_intents'][sr_coupon_public_claim_intent_key($campaignId, $accountId)] = $token;

    return $token;
}

function sr_coupon_create_claim_campaign(PDO $pdo, array $data): int
{
    if (!sr_coupon_claim_tables_available($pdo)) {
        throw new InvalidArgumentException('쿠폰 발급 캠페인 업데이트를 먼저 적용하세요.');
    }

    $payload = sr_coupon_claim_campaign_payload($pdo, $data, null);
    $now = sr_now();
    $payload['created_at'] = $now;
    $payload['updated_at'] = $now;

    $stmt = $pdo->prepare(
        'INSERT INTO sr_coupon_claim_campaigns
            (campaign_key, coupon_definition_id, title, description, status, claim_type, price_amount, price_currency_code, allowed_asset_modules_json, starts_at, ends_at, issue_expires_in_days, issue_expires_at, total_claim_limit, per_account_limit, visibility, exposure_surfaces_json, login_required, created_at, updated_at)
         VALUES
            (:campaign_key, :coupon_definition_id, :title, :description, :status, :claim_type, :price_amount, :price_currency_code, :allowed_asset_modules_json, :starts_at, :ends_at, :issue_expires_in_days, :issue_expires_at, :total_claim_limit, :per_account_limit, :visibility, :exposure_surfaces_json, :login_required, :created_at, :updated_at)'
    );
    $stmt->execute($payload);

    return (int) $pdo->lastInsertId();
}

function sr_coupon_update_claim_campaign(PDO $pdo, int $campaignId, array $data): void
{
    if (!sr_coupon_claim_tables_available($pdo)) {
        throw new InvalidArgumentException('쿠폰 발급 캠페인 업데이트를 먼저 적용하세요.');
    }
    if ($campaignId <= 0) {
        throw new InvalidArgumentException('수정할 발급 캠페인을 선택하세요.');
    }

    $current = sr_coupon_claim_campaign_by_id($pdo, $campaignId);
    if (!is_array($current)) {
        throw new InvalidArgumentException('수정할 발급 캠페인을 찾을 수 없습니다.');
    }
    $payload = sr_coupon_claim_campaign_payload($pdo, $data, $current);
    $hasClaims = sr_coupon_claim_campaign_log_count($pdo, $campaignId) > 0;
    if ($hasClaims && (string) ($payload['campaign_key'] ?? '') !== (string) ($current['campaign_key'] ?? '')) {
        throw new InvalidArgumentException('발급 로그가 있는 캠페인의 key는 변경할 수 없습니다.');
    }
    if ($hasClaims && (int) ($payload['coupon_definition_id'] ?? 0) !== (int) ($current['coupon_definition_id'] ?? 0)) {
        throw new InvalidArgumentException('발급 로그가 있는 캠페인의 연결 쿠폰은 변경할 수 없습니다.');
    }
    if ($hasClaims && (string) ($payload['claim_type'] ?? '') !== (string) ($current['claim_type'] ?? '')) {
        throw new InvalidArgumentException('발급 로그가 있는 캠페인의 발급 유형은 변경할 수 없습니다.');
    }
    if ($hasClaims && (int) ($payload['price_amount'] ?? 0) !== (int) ($current['price_amount'] ?? 0)) {
        throw new InvalidArgumentException('발급 로그가 있는 캠페인의 가격은 변경할 수 없습니다.');
    }
    if ($hasClaims && (string) ($payload['price_currency_code'] ?? '') !== (string) ($current['price_currency_code'] ?? '')) {
        throw new InvalidArgumentException('발급 로그가 있는 캠페인의 통화는 변경할 수 없습니다.');
    }
    if ($hasClaims && (string) ($payload['allowed_asset_modules_json'] ?? '') !== (string) ($current['allowed_asset_modules_json'] ?? '')) {
        throw new InvalidArgumentException('발급 로그가 있는 캠페인의 허용 포인트/금액 항목은 변경할 수 없습니다.');
    }

    $occupiedTotal = sr_coupon_claim_campaign_occupied_count($pdo, $campaignId);
    if ($payload['total_claim_limit'] !== null && $occupiedTotal > (int) $payload['total_claim_limit']) {
        throw new InvalidArgumentException('총 발급 한도는 이미 점유된 발급 수보다 작게 줄일 수 없습니다.');
    }
    $maxPerAccount = sr_coupon_claim_campaign_max_account_occupancy($pdo, $campaignId);
    if ($maxPerAccount > (int) $payload['per_account_limit']) {
        throw new InvalidArgumentException('회원당 발급 한도는 이미 점유된 회원별 발급 수보다 작게 줄일 수 없습니다.');
    }

    $payload['id'] = $campaignId;
    $payload['updated_at'] = sr_now();
    $stmt = $pdo->prepare(
        'UPDATE sr_coupon_claim_campaigns
         SET campaign_key = :campaign_key,
             coupon_definition_id = :coupon_definition_id,
             title = :title,
             description = :description,
             status = :status,
             claim_type = :claim_type,
             price_amount = :price_amount,
             price_currency_code = :price_currency_code,
             allowed_asset_modules_json = :allowed_asset_modules_json,
             starts_at = :starts_at,
             ends_at = :ends_at,
             issue_expires_in_days = :issue_expires_in_days,
             issue_expires_at = :issue_expires_at,
             total_claim_limit = :total_claim_limit,
             per_account_limit = :per_account_limit,
             visibility = :visibility,
             exposure_surfaces_json = :exposure_surfaces_json,
             login_required = :login_required,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute($payload);
}

function sr_coupon_claim_campaign_payload(PDO $pdo, array $data, ?array $current): array
{
    $campaignKey = sr_coupon_clean_key((string) ($data['campaign_key'] ?? ''));
    if (!sr_coupon_key_is_valid($campaignKey)) {
        throw new InvalidArgumentException('캠페인 키는 영문 소문자로 시작하고 소문자, 숫자, 밑줄만 사용할 수 있습니다.');
    }
    $stmt = $pdo->prepare('SELECT id FROM sr_coupon_claim_campaigns WHERE campaign_key = :campaign_key LIMIT 1');
    $stmt->execute(['campaign_key' => $campaignKey]);
    $existing = $stmt->fetch();
    if (is_array($existing) && (int) ($existing['id'] ?? 0) !== (int) ($current['id'] ?? 0)) {
        throw new InvalidArgumentException('이미 사용 중인 캠페인 key입니다.');
    }

    $definitionId = (int) ($data['coupon_definition_id'] ?? 0);
    $definition = sr_coupon_definition_by_id($pdo, $definitionId);
    if (!is_array($definition)) {
        throw new InvalidArgumentException('연결할 쿠폰을 선택하세요.');
    }

    $title = sr_coupon_clean_text((string) ($data['title'] ?? ''), 120);
    if ($title === '') {
        throw new InvalidArgumentException('캠페인 제목을 입력하세요.');
    }

    $status = sr_coupon_optional_enum_value($data, 'status', sr_coupon_claim_campaign_statuses(), 'draft', '발급 캠페인 상태가 올바르지 않습니다.');
    if ($status === 'active' && !sr_coupon_definition_allows_issue((string) ($definition['status'] ?? ''))) {
        throw new InvalidArgumentException('발급 가능한 쿠폰만 활성 발급 캠페인에 연결할 수 있습니다.');
    }
    $claimType = sr_coupon_optional_enum_value($data, 'claim_type', sr_coupon_claim_types(), 'free', '발급 유형이 올바르지 않습니다.');
    $priceAmount = null;
    $priceCurrencyCode = '';
    $allowedAssetModulesJson = null;
    if ($claimType === 'paid') {
        $priceAmount = sr_coupon_claim_positive_int($data['price_amount'] ?? '', 999999999, '유료 발급 가격');
        $priceCurrencyCode = sr_coupon_clean_currency_code((string) ($data['price_currency_code'] ?? 'KRW'));
        if ($priceCurrencyCode === '') {
            throw new InvalidArgumentException('유료 발급 통화를 입력하세요.');
        }
        $allowedAssetModules = sr_coupon_asset_module_keys_from_value($pdo, $data['allowed_asset_modules'] ?? []);
        if ($allowedAssetModules === []) {
            throw new InvalidArgumentException('유료 발급에 사용할 포인트/금액 항목을 하나 이상 선택하세요.');
        }
        if ($status === 'active') {
            sr_coupon_assert_paid_claim_asset_purchase_power($pdo, $allowedAssetModules, $priceCurrencyCode);
        }
        $allowedAssetModulesJson = sr_coupon_asset_modules_json($allowedAssetModules);
    }

    $perAccountLimit = sr_coupon_claim_positive_int($data['per_account_limit'] ?? '1', 1000, '회원당 발급 한도');
    $totalClaimLimit = sr_coupon_claim_optional_positive_int($data['total_claim_limit'] ?? '', 999999999, '총 발급 한도');
    $startsAt = sr_coupon_claim_datetime_or_null((string) ($data['starts_at'] ?? ''));
    $endsAt = sr_coupon_claim_datetime_or_null((string) ($data['ends_at'] ?? ''));
    if ($startsAt !== null && $endsAt !== null && strcmp($startsAt, $endsAt) > 0) {
        throw new InvalidArgumentException('발급 종료 시각은 시작 시각 이후여야 합니다.');
    }

    $surfaces = sr_coupon_claim_surfaces_from_value($data['exposure_surfaces'] ?? ['coupon_zone']);
    if ($surfaces === []) {
        $surfaces = ['coupon_zone'];
    }
    $issueExpiresInDays = sr_coupon_claim_optional_positive_int($data['issue_expires_in_days'] ?? '', 3650, '발급본 만료일수');
    $issueExpiresAt = sr_coupon_claim_datetime_or_null((string) ($data['issue_expires_at'] ?? ''));
    if ($issueExpiresInDays !== null && $issueExpiresAt !== null) {
        throw new InvalidArgumentException('발급본 만료는 상대 일수와 고정 시각 중 하나만 입력하세요.');
    }

    return [
        'campaign_key' => $campaignKey,
        'coupon_definition_id' => $definitionId,
        'title' => $title,
        'description' => sr_coupon_clean_text((string) ($data['description'] ?? ''), 1000),
        'status' => $status,
        'claim_type' => $claimType,
        'price_amount' => $priceAmount,
        'price_currency_code' => $priceCurrencyCode,
        'allowed_asset_modules_json' => $allowedAssetModulesJson,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'issue_expires_in_days' => $issueExpiresInDays,
        'issue_expires_at' => $issueExpiresAt,
        'total_claim_limit' => $totalClaimLimit,
        'per_account_limit' => $perAccountLimit,
        'visibility' => (string) ($data['visibility'] ?? 'hidden') === 'public' ? 'public' : 'hidden',
        'exposure_surfaces_json' => sr_coupon_claim_surfaces_json($surfaces),
        'login_required' => !empty($data['login_required']) ? 1 : 0,
    ];
}

function sr_coupon_claim_positive_int(mixed $value, int $max, string $label): int
{
    if (is_array($value)) {
        throw new InvalidArgumentException($label . '는 1부터 ' . number_format($max) . ' 사이의 정수로 입력하세요.');
    }
    $stringValue = trim((string) $value);
    if ($stringValue === '' || preg_match('/\A[1-9][0-9]*\z/', $stringValue) !== 1) {
        throw new InvalidArgumentException($label . '는 1부터 ' . number_format($max) . ' 사이의 정수로 입력하세요.');
    }
    $intValue = (int) $stringValue;
    if ($intValue < 1 || $intValue > $max) {
        throw new InvalidArgumentException($label . '는 1부터 ' . number_format($max) . ' 사이의 정수로 입력하세요.');
    }

    return $intValue;
}

function sr_coupon_claim_optional_positive_int(mixed $value, int $max, string $label): ?int
{
    if (is_array($value)) {
        throw new InvalidArgumentException($label . '는 비워 두거나 1부터 ' . number_format($max) . ' 사이의 정수로 입력하세요.');
    }
    $stringValue = trim((string) $value);
    if ($stringValue === '') {
        return null;
    }

    return sr_coupon_claim_positive_int($stringValue, $max, $label);
}

function sr_coupon_claim_campaign_log_count(PDO $pdo, int $campaignId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sr_coupon_claim_logs WHERE campaign_id = :campaign_id');
    $stmt->execute(['campaign_id' => $campaignId]);

    return (int) $stmt->fetchColumn();
}

function sr_coupon_claim_campaign_occupied_condition(string $nowParam = ':now_value'): string
{
    return "status = 'issued' OR (status IN ('reserved', 'pending_payment') AND (reserved_until IS NULL OR reserved_until >= " . $nowParam . '))';
}

function sr_coupon_claim_campaign_occupied_count(PDO $pdo, int $campaignId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_coupon_claim_logs
         WHERE campaign_id = :campaign_id
           AND (' . sr_coupon_claim_campaign_occupied_condition(':now_value') . ')'
    );
    $stmt->execute([
        'campaign_id' => $campaignId,
        'now_value' => sr_now(),
    ]);

    return (int) $stmt->fetchColumn();
}

function sr_coupon_claim_campaign_max_account_occupancy(PDO $pdo, int $campaignId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS occupied_count
         FROM sr_coupon_claim_logs
         WHERE campaign_id = :campaign_id
           AND (' . sr_coupon_claim_campaign_occupied_condition(':now_value') . ')
         GROUP BY account_id
         ORDER BY occupied_count DESC
         LIMIT 1'
    );
    $stmt->execute([
        'campaign_id' => $campaignId,
        'now_value' => sr_now(),
    ]);

    return (int) $stmt->fetchColumn();
}
