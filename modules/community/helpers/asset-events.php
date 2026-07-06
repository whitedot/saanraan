<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/asset_ledger/helpers.php';

function sr_community_paid_read_session_key(int $accountId, int $postId): string
{
    return (string) $accountId . ':' . (string) $postId;
}

function sr_community_has_paid_read_session(int $accountId, int $postId): bool
{
    $key = sr_community_paid_read_session_key($accountId, $postId);
    $sessions = is_array($_SESSION['sr_community_paid_read_posts'] ?? null) ? $_SESSION['sr_community_paid_read_posts'] : [];
    $createdAt = isset($sessions[$key]) ? (int) $sessions[$key] : 0;

    if ($createdAt > 0 && $createdAt >= time() - 300) {
        return true;
    }

    unset($_SESSION['sr_community_paid_read_posts'][$key]);
    return false;
}

function sr_community_mark_paid_read_session(int $accountId, int $postId): void
{
    if ($accountId < 1 || $postId < 1) {
        return;
    }

    if (!isset($_SESSION['sr_community_paid_read_posts']) || !is_array($_SESSION['sr_community_paid_read_posts'])) {
        $_SESSION['sr_community_paid_read_posts'] = [];
    }

    $_SESSION['sr_community_paid_read_posts'][sr_community_paid_read_session_key($accountId, $postId)] = time();
}

function sr_community_attachment_paid_read_bridge_key(int $accountId, int $attachmentId): string
{
    return (string) $accountId . ':' . (string) $attachmentId;
}

function sr_community_mark_attachment_paid_read_bridge(int $accountId, int $attachmentId, string $fingerprint, int $createdAt = 0): void
{
    if ($accountId < 1 || $attachmentId < 1 || $fingerprint === '') {
        return;
    }

    if (!isset($_SESSION['sr_community_attachment_paid_read_bridges']) || !is_array($_SESSION['sr_community_attachment_paid_read_bridges'])) {
        $_SESSION['sr_community_attachment_paid_read_bridges'] = [];
    }

    $_SESSION['sr_community_attachment_paid_read_bridges'][sr_community_attachment_paid_read_bridge_key($accountId, $attachmentId)] = [
        'created_at' => $createdAt > 0 ? $createdAt : time(),
        'fingerprint' => $fingerprint,
    ];
}

function sr_community_consume_attachment_paid_read_bridge_created_at(int $accountId, int $attachmentId, string $fingerprint): int
{
    $key = sr_community_attachment_paid_read_bridge_key($accountId, $attachmentId);
    $sessions = is_array($_SESSION['sr_community_attachment_paid_read_bridges'] ?? null) ? $_SESSION['sr_community_attachment_paid_read_bridges'] : [];
    $session = isset($sessions[$key]) && is_array($sessions[$key]) ? $sessions[$key] : [];
    $createdAt = (int) ($session['created_at'] ?? 0);
    $sessionFingerprint = (string) ($session['fingerprint'] ?? '');
    unset($_SESSION['sr_community_attachment_paid_read_bridges'][$key]);

    if ($createdAt > 0
        && $createdAt >= time() - 300
        && $fingerprint !== ''
        && hash_equals($sessionFingerprint, $fingerprint)
    ) {
        return $createdAt;
    }

    return 0;
}

function sr_community_asset_dedupe_key(string $assetModule, int $accountId, string $eventKey, int $subjectId): string
{
    return 'community.' . $eventKey . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId;
}

function sr_community_asset_log(PDO $pdo, string $dedupeKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_community_asset_logs
         WHERE dedupe_key = :dedupe_key
         LIMIT 1'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_has_asset_event(PDO $pdo, string $assetModule, int $accountId, string $eventKey, int $subjectId): bool
{
    $log = sr_community_asset_log($pdo, sr_community_asset_dedupe_key($assetModule, $accountId, $eventKey, $subjectId));

    return is_array($log)
        && (string) ($log['log_status'] ?? sr_community_asset_log_status_completed()) === sr_community_asset_log_status_completed()
        && ((int) ($log['transaction_id'] ?? 0) > 0 || (int) ($log['amount'] ?? -1) === 0);
}

function sr_community_has_asset_event_for_modules(PDO $pdo, array $assetModules, int $accountId, string $eventKey, int $subjectId): bool
{
    foreach (sr_community_asset_module_keys_from_value($assetModules, true) as $assetModule) {
        if (sr_community_has_asset_event($pdo, $assetModule, $accountId, $eventKey, $subjectId)) {
            return true;
        }
    }

    return false;
}

function sr_community_has_asset_event_history(PDO $pdo, array $assetModules, int $accountId, string $eventKey, int $subjectId, string $policy): bool
{
    $policy = sr_community_once_history_policy($policy);
    if ($policy === 'current_asset_once') {
        return sr_community_has_asset_event_for_modules($pdo, $assetModules, $accountId, $eventKey, $subjectId);
    }

    $params = [
        'account_id' => $accountId,
        'event_key' => $eventKey,
        'subject_id' => $subjectId,
    ];
    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_community_asset_logs
         WHERE account_id = :account_id
           AND event_key = :event_key
           AND subject_id = :subject_id
           AND direction = \'use\'
           AND log_status = \'completed\'
           AND (transaction_id > 0 OR amount = 0)'
        . ' LIMIT 1'
    );
    $stmt->execute($params);

    return is_array($stmt->fetch());
}

function sr_community_has_coupon_access_history(PDO $pdo, int $accountId, string $dedupeKey): bool
{
    if ($accountId <= 0 || $dedupeKey === '' || !sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return false;
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_has_redemption')) {
        return false;
    }

    return sr_coupon_has_redemption($pdo, $accountId, $dedupeKey);
}

function sr_community_access_entitlements_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query('SELECT 1 FROM sr_community_access_entitlements LIMIT 1');
        $exists = $stmt !== false;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_community_grant_access_entitlement(PDO $pdo, int $accountId, string $subjectType, int $subjectId, string $eventKey, string $sourceKind, string $sourceAssetModule = '', string $sourceChargePolicy = 'once', string $sourceReference = ''): void
{
    if ($accountId <= 0 || $subjectType === '' || $subjectId <= 0 || $eventKey === '' || !sr_community_access_entitlements_table_exists($pdo)) {
        return;
    }

    $insertVerb = 'INSERT IGNORE';
    try {
        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $insertVerb = 'INSERT OR IGNORE';
        }
    } catch (Throwable $exception) {
        $insertVerb = 'INSERT IGNORE';
    }
    $stmt = $pdo->prepare(
        $insertVerb . ' INTO sr_community_access_entitlements
            (account_id, subject_type, subject_id, event_key, source_kind, source_asset_module, source_charge_policy, source_reference, granted_at, created_at)
         VALUES
            (:account_id, :subject_type, :subject_id, :event_key, :source_kind, :source_asset_module, :source_charge_policy, :source_reference, :granted_at, :created_at)'
    );
    $now = sr_now();
    $stmt->execute([
        'account_id' => $accountId,
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'event_key' => $eventKey,
        'source_kind' => $sourceKind,
        'source_asset_module' => $sourceAssetModule,
        'source_charge_policy' => $sourceChargePolicy,
        'source_reference' => $sourceReference,
        'granted_at' => $now,
        'created_at' => $now,
    ]);
}

function sr_community_revoke_coupon_access_entitlements(PDO $pdo, int $accountId, string $sourceReference): int
{
    if ($accountId <= 0 || $sourceReference === '' || !sr_community_access_entitlements_table_exists($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "DELETE FROM sr_community_access_entitlements
         WHERE account_id = :account_id
           AND source_kind = 'coupon'
           AND source_reference = :source_reference"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'source_reference' => $sourceReference,
    ]);

    return $stmt->rowCount();
}

function sr_community_revoke_access_entitlement_by_source(PDO $pdo, int $accountId, string $subjectType, int $subjectId, string $eventKey, string $sourceKind, string $sourceReference): int
{
    if (
        $accountId <= 0
        || $subjectType === ''
        || $subjectId <= 0
        || $eventKey === ''
        || $sourceKind === ''
        || $sourceReference === ''
        || !sr_community_access_entitlements_table_exists($pdo)
    ) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM sr_community_access_entitlements
         WHERE account_id = :account_id
           AND subject_type = :subject_type
           AND subject_id = :subject_id
           AND event_key = :event_key
           AND source_kind = :source_kind
           AND source_reference = :source_reference'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'event_key' => $eventKey,
        'source_kind' => $sourceKind,
        'source_reference' => $sourceReference,
    ]);

    return $stmt->rowCount();
}

function sr_community_record_payment_ledger_if_available(PDO $pdo, array $record, array $items): int
{
    if (!function_exists('sr_module_enabled') || !sr_module_enabled($pdo, 'payment_ledger')) {
        return 0;
    }
    if (!is_file(SR_ROOT . '/modules/payment_ledger/helpers.php')) {
        throw new RuntimeException('결제 기록 기반 모듈 helper를 찾을 수 없습니다.');
    }

    require_once SR_ROOT . '/modules/payment_ledger/helpers.php';
    if (!function_exists('sr_payment_ledger_record_payment') || !sr_payment_ledger_tables_available($pdo)) {
        throw new RuntimeException('결제 기록 기반 테이블이 준비되지 않았습니다.');
    }

    return sr_payment_ledger_record_payment($pdo, $record, $items);
}

function sr_community_payment_subject_type(string $eventKey, string $subjectType): string
{
    if ($eventKey === 'post_read' && $subjectType === 'community.post') {
        return 'community.post.read';
    }
    if ($eventKey === 'attachment_download' && $subjectType === 'community.attachment') {
        return 'community.attachment.download';
    }

    return 'community.' . $eventKey;
}

function sr_community_payment_description(string $eventKey): string
{
    return match ($eventKey) {
        'post_read' => '커뮤니티 게시글 열람 결제',
        'attachment_download' => '커뮤니티 첨부 다운로드 결제',
        'post_write_charge' => '커뮤니티 글쓰기 결제',
        'comment_write_charge' => '커뮤니티 댓글 작성 결제',
        default => '커뮤니티 자산 결제',
    };
}

function sr_community_payment_coupon_item(array $couponResult, string $settlementCurrency): array
{
    $redemptionId = (int) ($couponResult['coupon_redemption_id'] ?? 0);
    if ($redemptionId <= 0 || empty($couponResult['processed'])) {
        return [];
    }

    return [
        'item_kind' => 'coupon_redemption',
        'owner_module' => 'coupon',
        'reference_type' => 'coupon_redemption',
        'reference_id' => (string) $redemptionId,
        'amount' => -max(0, (int) ($couponResult['discount_amount'] ?? 0)),
        'currency_code' => $settlementCurrency,
        'reversible' => true,
        'snapshot' => [
            'coupon_issue_id' => (int) ($couponResult['coupon_issue_id'] ?? 0),
            'coupon_definition_id' => (int) ($couponResult['coupon_definition_id'] ?? 0),
            'coupon_type' => (string) ($couponResult['coupon_type'] ?? ''),
            'dedupe_key' => (string) ($couponResult['dedupe_key'] ?? ''),
        ],
    ];
}

function sr_community_payment_access_item(string $subjectType, int $subjectId, string $eventKey, string $sourceKind, string $sourceReference = ''): array
{
    return [
        'item_kind' => 'access_entitlement',
        'owner_module' => 'community',
        'reference_type' => 'community.access_entitlement',
        'reference_id' => $subjectType . ':' . (string) $subjectId . ':' . $eventKey,
        'amount' => 0,
        'currency_code' => '',
        'reversible' => true,
        'snapshot' => [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'event_key' => $eventKey,
            'source_kind' => $sourceKind,
            'source_reference' => $sourceReference,
        ],
    ];
}

function sr_community_post_read_payment_refund_policy_version(): string
{
    return 'community_post_read_refund_v1';
}

function sr_community_post_read_payment_clean_text(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

    return substr($value, 0, max(1, $maxLength));
}

function sr_community_post_read_payment_clean_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_]/', '_', $value) ?? '';
    $value = preg_replace('/_+/', '_', $value) ?? '';

    return trim($value, '_');
}

function sr_community_post_read_payment_type(array $couponResult, bool $hasAssetSettlement): string
{
    if (!empty($couponResult['processed'])) {
        if ($hasAssetSettlement) {
            return 'coupon_partial_discount_asset';
        }

        return (string) ($couponResult['coupon_type'] ?? '') === 'access'
            ? 'coupon_access'
            : 'coupon_full_discount';
    }

    return $hasAssetSettlement ? 'asset_only' : 'settled_zero';
}

function sr_community_record_post_read_payment_log(PDO $pdo, array $row): void
{
    $postId = max(0, (int) ($row['post_id'] ?? 0));
    $accountId = max(0, (int) ($row['account_id'] ?? 0));
    $paymentDedupeKey = sr_community_post_read_payment_clean_text((string) ($row['payment_dedupe_key'] ?? ''), 190);
    if ($postId <= 0 || $accountId <= 0 || $paymentDedupeKey === '') {
        throw new InvalidArgumentException('커뮤니티 게시글 열람 결제 단위 로그의 필수 값이 없습니다.');
    }

    $assetLogIds = [];
    foreach ((array) ($row['asset_access_log_ids'] ?? []) as $assetLogId) {
        $assetLogId = (int) $assetLogId;
        if ($assetLogId > 0) {
            $assetLogIds[$assetLogId] = $assetLogId;
        }
    }
    $assetLogIdsJson = json_encode(array_values($assetLogIds), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $paymentType = sr_community_post_read_payment_clean_key((string) ($row['payment_type'] ?? 'asset_only'));
    $allowedPaymentTypes = ['asset_only', 'coupon_access', 'coupon_full_discount', 'coupon_partial_discount_asset', 'settled_zero'];
    if (!in_array($paymentType, $allowedPaymentTypes, true)) {
        $paymentType = 'asset_only';
    }

    $settlementKind = sr_community_post_read_payment_clean_key((string) ($row['settlement_kind'] ?? ''));
    $allowedSettlementKinds = ['paid', 'free', 'paid_settled_zero', 'preview_test_zero'];
    if (!in_array($settlementKind, $allowedSettlementKinds, true)) {
        $settlementKind = max(0, (int) ($row['settlement_amount'] ?? 0)) > 0 ? 'paid' : 'paid_settled_zero';
    }

    $insertVerb = 'INSERT IGNORE';
    try {
        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $insertVerb = 'INSERT OR IGNORE';
        }
    } catch (Throwable $exception) {
        $insertVerb = 'INSERT IGNORE';
    }

    $stmt = $pdo->prepare(
        $insertVerb . ' INTO sr_community_post_read_payment_logs
            (board_id, post_id, post_title_snapshot, account_id, payment_type, settlement_kind, charge_policy, asset_module, payable_amount, settlement_amount, settlement_currency, asset_access_log_ids_json, coupon_redemption_id, coupon_dedupe_key, payment_dedupe_key, refund_status, refund_transaction_ids_json, refund_note, refund_policy_version, created_at)
         VALUES
            (:board_id, :post_id, :post_title_snapshot, :account_id, :payment_type, :settlement_kind, :charge_policy, :asset_module, :payable_amount, :settlement_amount, :settlement_currency, :asset_access_log_ids_json, :coupon_redemption_id, :coupon_dedupe_key, :payment_dedupe_key, \'\', \'[]\', \'\', :refund_policy_version, :created_at)'
    );
    $stmt->execute([
        'board_id' => max(0, (int) ($row['board_id'] ?? 0)),
        'post_id' => $postId,
        'post_title_snapshot' => sr_community_post_read_payment_clean_text((string) ($row['post_title_snapshot'] ?? ''), 160),
        'account_id' => $accountId,
        'payment_type' => $paymentType,
        'settlement_kind' => $settlementKind,
        'charge_policy' => sr_community_post_read_payment_clean_key((string) ($row['charge_policy'] ?? 'once')),
        'asset_module' => sr_community_post_read_payment_clean_text((string) ($row['asset_module'] ?? ''), 60),
        'payable_amount' => max(0, (int) ($row['payable_amount'] ?? 0)),
        'settlement_amount' => max(0, (int) ($row['settlement_amount'] ?? 0)),
        'settlement_currency' => sr_community_asset_settlement_currency($pdo, ['asset_settlement_currency' => (string) ($row['settlement_currency'] ?? 'KRW')]),
        'asset_access_log_ids_json' => is_string($assetLogIdsJson) ? $assetLogIdsJson : '[]',
        'coupon_redemption_id' => (int) ($row['coupon_redemption_id'] ?? 0) > 0 ? (int) $row['coupon_redemption_id'] : null,
        'coupon_dedupe_key' => sr_community_post_read_payment_clean_text((string) ($row['coupon_dedupe_key'] ?? ''), 160),
        'payment_dedupe_key' => $paymentDedupeKey,
        'refund_policy_version' => sr_community_post_read_payment_refund_policy_version(),
        'created_at' => sr_now(),
    ]);
}

function sr_community_post_read_payment_log_by_id_for_update(PDO $pdo, int $paymentLogId): ?array
{
    if ($paymentLogId <= 0) {
        return null;
    }

    $lockClause = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    $stmt = $pdo->prepare('SELECT * FROM sr_community_post_read_payment_logs WHERE id = :id LIMIT 1' . $lockClause);
    $stmt->execute(['id' => $paymentLogId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_post_read_payment_log_asset_log_ids(array $paymentLog): array
{
    $decoded = json_decode((string) ($paymentLog['asset_access_log_ids_json'] ?? '[]'), true);
    if (!is_array($decoded)) {
        return [];
    }

    $ids = [];
    foreach ($decoded as $value) {
        $id = (int) $value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

function sr_community_post_read_payment_asset_logs_for_refund(PDO $pdo, array $paymentLog): array
{
    $ids = sr_community_post_read_payment_log_asset_log_ids($paymentLog);
    if ($ids === []) {
        return [];
    }

    $params = [
        'account_id' => (int) ($paymentLog['account_id'] ?? 0),
        'post_id' => (int) ($paymentLog['post_id'] ?? 0),
    ];
    $placeholders = [];
    foreach ($ids as $index => $id) {
        $key = 'id_' . (string) $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_community_asset_logs
         WHERE id IN (' . implode(', ', $placeholders) . ')
           AND account_id = :account_id
           AND subject_type = \'community.post\'
           AND subject_id = :post_id
           AND event_key = \'post_read\'
         ORDER BY id ASC'
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_community_post_read_payment_access_revoke_sources(array $paymentLog, array $assetLogs): array
{
    $sources = [];
    $couponDedupeKey = sr_community_post_read_payment_clean_text((string) ($paymentLog['coupon_dedupe_key'] ?? ''), 160);
    if ($couponDedupeKey !== '') {
        $sources['coupon:' . $couponDedupeKey] = [
            'source_kind' => 'coupon',
            'source_reference' => $couponDedupeKey,
        ];
    }

    foreach ($assetLogs as $assetLog) {
        $assetModule = sr_community_post_read_payment_clean_key((string) ($assetLog['asset_module'] ?? ''));
        $transactionId = (int) ($assetLog['transaction_id'] ?? 0);
        if ($assetModule !== '' && $transactionId > 0) {
            $sourceReference = $assetModule . ':' . (string) $transactionId;
            $sources['asset:' . $sourceReference] = [
                'source_kind' => 'asset',
                'source_reference' => $sourceReference,
            ];
            continue;
        }

        $dedupeKey = sr_community_post_read_payment_clean_text((string) ($assetLog['dedupe_key'] ?? ''), 160);
        if ($dedupeKey !== '') {
            $sources['asset_group_policy:' . $dedupeKey] = [
                'source_kind' => 'asset_group_policy',
                'source_reference' => $dedupeKey,
            ];
        }
    }

    return array_values($sources);
}

function sr_community_mark_post_read_payment_ledger_items_reversed_if_available(PDO $pdo, int $accountId, array $references, string $reason): array
{
    if ($references === [] || !function_exists('sr_module_enabled') || !sr_module_enabled($pdo, 'payment_ledger')) {
        return ['payment_record_ids' => [], 'reversed_item_count' => 0, 'refunded_record_ids' => []];
    }
    if (!is_file(SR_ROOT . '/modules/payment_ledger/helpers.php')) {
        throw new RuntimeException('결제 기록 기반 모듈 helper를 찾을 수 없습니다.');
    }

    require_once SR_ROOT . '/modules/payment_ledger/helpers.php';
    if (!function_exists('sr_payment_ledger_mark_item_references_reversed') || !sr_payment_ledger_tables_available($pdo)) {
        throw new RuntimeException('결제 기록 기반 테이블이 준비되지 않았습니다.');
    }

    return sr_payment_ledger_mark_item_references_reversed($pdo, $accountId, $references, $reason, false);
}

function sr_community_refund_post_read_payment(PDO $pdo, int $paymentLogId, int $adminAccountId, string $refundNote, string $refundExpirationPolicy = 'original'): array
{
    $refundNote = sr_community_post_read_payment_clean_text($refundNote, 255);
    $refundExpirationPolicy = in_array($refundExpirationPolicy, ['original', 'reset'], true) ? $refundExpirationPolicy : 'original';
    if ($paymentLogId <= 0) {
        return ['ok' => false, 'message' => '환불할 게시글 열람 결제 내역을 선택하세요.'];
    }
    if ($adminAccountId <= 0) {
        return ['ok' => false, 'message' => '처리 관리자 정보를 확인할 수 없습니다.'];
    }
    if ($refundNote === '') {
        return ['ok' => false, 'message' => '환불 사유를 입력하세요.'];
    }

    $couponNotification = [];
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $paymentLog = sr_community_post_read_payment_log_by_id_for_update($pdo, $paymentLogId);
        if (!is_array($paymentLog)) {
            throw new RuntimeException('환불할 게시글 열람 결제 내역을 찾을 수 없습니다.');
        }
        if ((string) ($paymentLog['refund_status'] ?? '') !== '') {
            throw new RuntimeException('이미 환불 또는 접근권 회수 처리된 결제입니다.');
        }

        $accountId = (int) ($paymentLog['account_id'] ?? 0);
        $postId = (int) ($paymentLog['post_id'] ?? 0);
        if ($accountId <= 0 || $postId <= 0) {
            throw new RuntimeException('환불 대상 회원 또는 게시글 정보를 확인할 수 없습니다.');
        }

        $assetLogIds = sr_community_post_read_payment_log_asset_log_ids($paymentLog);
        $assetLogs = $assetLogIds !== [] ? sr_community_post_read_payment_asset_logs_for_refund($pdo, $paymentLog) : [];
        if ($assetLogIds !== [] && $assetLogs === []) {
            throw new RuntimeException('연결된 차감 또는 접근권 로그를 찾을 수 없습니다.');
        }

        $refundTransactionIds = [];
        $paymentLedgerReferences = [];
        foreach ($assetLogs as $assetLog) {
            $amount = (int) ($assetLog['amount'] ?? 0);
            $transactionId = (int) ($assetLog['transaction_id'] ?? 0);
            if ($amount <= 0 || $transactionId <= 0) {
                continue;
            }

            $assetModule = (string) ($assetLog['asset_module'] ?? '');
            if (!sr_community_asset_module_is_available($pdo, $assetModule)) {
                throw new RuntimeException('환불할 차감 항목을 사용할 수 없습니다: ' . $assetModule);
            }

            $assetOption = sr_community_asset_modules($pdo)[$assetModule];
            $transactionData = [
                'account_id' => $accountId,
                'amount' => $amount,
                'transaction_type' => (string) ($assetOption['refund_type'] ?? 'refund'),
                'reason' => '커뮤니티 게시글 열람 환불: ' . $refundNote,
                'reference_type' => 'refund',
                'reference_id' => $assetModule . '_transaction:' . (string) $transactionId,
                'created_by_account_id' => $adminAccountId,
            ];
            $transactionData['refund_expiration_policy'] = $refundExpirationPolicy;
            $paymentLedgerReferences[] = [
                'item_kind' => 'asset_transaction',
                'owner_module' => $assetModule,
                'reference_type' => $assetModule . '_transaction',
                'reference_id' => (string) $transactionId,
            ];
            $paymentLedgerReferences[] = [
                'item_kind' => 'asset_access_log',
                'owner_module' => 'community',
                'reference_type' => 'community_asset_log',
                'reference_id' => (string) (int) ($assetLog['id'] ?? 0),
            ];

            foreach (sr_community_create_asset_refund_transactions($pdo, $assetModule, $transactionData) as $refundTransactionId) {
                $refundTransactionIds[] = $assetModule . ':' . (string) $refundTransactionId;
            }
        }

        $couponRefund = [];
        $couponRedemptionId = (int) ($paymentLog['coupon_redemption_id'] ?? 0);
        if ($couponRedemptionId > 0) {
            if (!is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
                throw new RuntimeException('쿠폰 환불 helper를 찾을 수 없습니다.');
            }
            require_once SR_ROOT . '/modules/coupon/helpers.php';
            if (!function_exists('sr_coupon_refund_redemption_state_only')) {
                throw new RuntimeException('쿠폰 상태 환불 계약을 찾을 수 없습니다.');
            }
            $couponRefund = sr_coupon_refund_redemption_state_only($pdo, $couponRedemptionId, $adminAccountId, $refundNote, [
                'allowed_coupon_types' => ['access', 'fixed_discount', 'percent_discount'],
                'require_refundable_policy' => false,
            ]);
            $paymentLedgerReferences[] = [
                'item_kind' => 'coupon_redemption',
                'owner_module' => 'coupon',
                'reference_type' => 'coupon_redemption',
                'reference_id' => (string) $couponRedemptionId,
            ];
            $couponNotification = [
                'coupon_issue_id' => (int) ($couponRefund['coupon_issue_id'] ?? 0),
                'event_key' => (string) ($couponRefund['notification_event_key'] ?? 'redemption.refunded'),
                'payload' => is_array($couponRefund['notification_payload'] ?? null) ? $couponRefund['notification_payload'] : [],
            ];
        }

        $accessRevoked = false;
        $shouldRevokeAccess = (string) ($paymentLog['charge_policy'] ?? '') === 'once' || (int) ($paymentLog['settlement_amount'] ?? 0) <= 0;
        if ($shouldRevokeAccess) {
            foreach (sr_community_post_read_payment_access_revoke_sources($paymentLog, $assetLogs) as $source) {
                $accessRevoked = sr_community_revoke_access_entitlement_by_source(
                    $pdo,
                    $accountId,
                    'community.post',
                    $postId,
                    'post_read',
                    (string) ($source['source_kind'] ?? ''),
                    (string) ($source['source_reference'] ?? '')
                ) > 0 || $accessRevoked;
            }
        }
        if ($shouldRevokeAccess && ($refundTransactionIds !== [] || $couponRefund !== []) && !$accessRevoked) {
            throw new RuntimeException('결제 단위와 일치하는 게시글 열람 접근권을 회수할 수 없습니다.');
        }
        if ($accessRevoked) {
            $paymentLedgerReferences[] = [
                'item_kind' => 'access_entitlement',
                'owner_module' => 'community',
                'reference_type' => 'community.access_entitlement',
                'reference_id' => 'community.post:' . (string) $postId . ':post_read',
            ];
        }

        if ($refundTransactionIds === [] && !$accessRevoked && $couponRefund === []) {
            throw new RuntimeException('환불할 원장 거래나 회수할 접근권을 찾을 수 없습니다.');
        }

        $refundTransactionIdsJson = json_encode($refundTransactionIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $now = sr_now();
        $refundStatus = $refundTransactionIds !== [] || $couponRefund !== [] ? 'refunded' : 'access_revoked';
        $stmt = $pdo->prepare(
            'UPDATE sr_community_post_read_payment_logs
             SET refund_status = :refund_status,
                 refund_transaction_ids_json = :refund_transaction_ids_json,
                 refund_note = :refund_note,
                 refunded_by_account_id = :refunded_by_account_id,
                 refunded_at = :refunded_at,
                 access_revoked_at = :access_revoked_at
             WHERE id = :id
               AND refund_status = \'\''
        );
        $stmt->execute([
            'refund_status' => $refundStatus,
            'refund_transaction_ids_json' => is_string($refundTransactionIdsJson) ? $refundTransactionIdsJson : '[]',
            'refund_note' => $refundNote,
            'refunded_by_account_id' => $adminAccountId,
            'refunded_at' => $now,
            'access_revoked_at' => $accessRevoked ? $now : null,
            'id' => $paymentLogId,
        ]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('이미 처리된 게시글 열람 결제입니다.');
        }
        sr_community_mark_post_read_payment_ledger_items_reversed_if_available($pdo, $accountId, $paymentLedgerReferences, '커뮤니티 게시글 열람 환불: ' . $refundNote);

        if ($startedTransaction) {
            $pdo->commit();
        }

        if ($startedTransaction && $couponNotification !== [] && function_exists('sr_coupon_notify_issue_event')) {
            $payload = is_array($couponNotification['payload'] ?? null) ? $couponNotification['payload'] : [];
            $payload['revoked_access_count'] = $accessRevoked ? 1 : 0;
            sr_coupon_notify_issue_event($pdo, (int) ($couponNotification['coupon_issue_id'] ?? 0), (string) ($couponNotification['event_key'] ?? 'redemption.refunded'), $adminAccountId, $payload);
        }

        try {
            sr_audit_log($pdo, [
                'actor_account_id' => $adminAccountId,
                'actor_type' => 'admin',
                'event_type' => 'community_post_read_payment.refunded',
                'target_type' => 'community_post_read_payment',
                'target_id' => (string) $paymentLogId,
                'result' => 'success',
                'message' => 'Community post read payment refunded.',
                'metadata' => [
                    'post_id' => $postId,
                    'account_id' => $accountId,
                    'coupon_redemption_id' => $couponRedemptionId,
                    'refund_status' => $refundStatus,
                    'refund_expiration_policy' => $refundExpirationPolicy,
                    'refund_transaction_ids' => $refundTransactionIds,
                    'access_revoked' => $accessRevoked,
                ],
            ]);
        } catch (Throwable $auditException) {
            if (function_exists('sr_log_exception')) {
                sr_log_exception($auditException, 'community_post_read_payment_refund_audit_failed');
            }
        }

        return [
            'ok' => true,
            'message' => $refundStatus === 'refunded' ? '게시글 열람 결제를 환불 처리했습니다.' : '게시글 열람 접근권을 회수했습니다.',
            'coupon_notification' => $startedTransaction ? [] : $couponNotification,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'community_post_read_payment_refund_failed');
        }

        return ['ok' => false, 'message' => $exception->getMessage()];
    }
}

function sr_community_anonymize_access_entitlements(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0 || !sr_community_access_entitlements_table_exists($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_access_entitlements
         SET account_id = NULL,
             source_reference = \'\',
             anonymized_at = :anonymized_at
         WHERE account_id = :account_id'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'anonymized_at' => sr_now(),
    ]);

    return $stmt->rowCount();
}

function sr_community_has_access_entitlement(PDO $pdo, array $assetModules, int $accountId, string $eventKey, string $subjectType, int $subjectId, string $couponDedupeKey, string $policy): bool
{
    $policy = sr_community_once_history_policy($policy);
    if (!sr_community_access_entitlements_table_exists($pdo)) {
        if (sr_community_has_asset_event_history($pdo, $assetModules, $accountId, $eventKey, $subjectId, $policy)) {
            return true;
        }

        return $policy === 'all_access'
            && $couponDedupeKey !== ''
            && sr_community_has_coupon_access_history($pdo, $accountId, $couponDedupeKey);
    }

    $conditions = [
        'account_id = :account_id',
        'subject_type = :subject_type',
        'subject_id = :subject_id',
        'event_key = :event_key',
        'anonymized_at IS NULL',
    ];
    $params = [
        'account_id' => $accountId,
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'event_key' => $eventKey,
    ];

    if ($policy === 'asset_any') {
        $conditions[] = 'source_kind = \'asset\'';
    } elseif ($policy === 'current_asset_once') {
        $moduleKeys = sr_community_asset_module_keys_from_value($assetModules, true);
        if ($moduleKeys === []) {
            return false;
        }
        $placeholders = [];
        foreach ($moduleKeys as $index => $assetModule) {
            $key = 'asset_module_' . (string) $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $assetModule;
        }
        $conditions[] = 'source_kind = \'asset\'';
        $conditions[] = 'source_charge_policy = \'once\'';
        $conditions[] = 'source_asset_module IN (' . implode(', ', $placeholders) . ')';
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_community_access_entitlements
         WHERE ' . implode(' AND ', $conditions) . '
         LIMIT 1'
    );
    $stmt->execute($params);

    return is_array($stmt->fetch());
}

function sr_community_once_access_already_granted(PDO $pdo, array $config, int $accountId, string $eventKey, int $subjectId, string $couponDedupeKey = ''): bool
{
    $settings = sr_community_settings($pdo);
    $policy = sr_community_once_history_policy((string) ($settings['once_history_policy'] ?? 'all_access'));
    $assetModules = sr_community_asset_module_keys_from_value($config['asset_module'] ?? '', true);
    $subjectType = $eventKey === 'attachment_download' ? 'community.attachment' : 'community.post';

    return sr_community_has_access_entitlement($pdo, $assetModules, $accountId, $eventKey, $subjectType, $subjectId, $couponDedupeKey, $policy);
}

function sr_community_available_paid_read_coupon_issues(PDO $pdo, int $accountId, array $post, int $limit = 20): array
{
    $postId = (int) ($post['id'] ?? 0);
    $boardId = (int) ($post['board_id'] ?? 0);
    if ($accountId <= 0 || $postId <= 0 || !sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return [];
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_active_account_target_issues')) {
        return [];
    }

    $issues = sr_coupon_active_account_target_issues($pdo, $accountId, 'community_post', (string) $postId, $limit);
    if ($boardId > 0 && count($issues) < $limit) {
        $seen = [];
        foreach ($issues as $issue) {
            $seen[(int) ($issue['id'] ?? 0)] = true;
        }
        foreach (sr_coupon_active_account_target_issues($pdo, $accountId, 'community_board', (string) $boardId, $limit) as $issue) {
            $issueId = (int) ($issue['id'] ?? 0);
            if ($issueId <= 0 || isset($seen[$issueId])) {
                continue;
            }
            $issues[] = $issue;
            $seen[$issueId] = true;
            if (count($issues) >= $limit) {
                break;
            }
        }
    }

    return $issues;
}

function sr_community_available_attachment_download_coupon_issues(PDO $pdo, int $accountId, int $attachmentId, int $limit = 20): array
{
    if ($accountId <= 0 || $attachmentId <= 0 || !sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return [];
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_active_account_target_issues')) {
        return [];
    }

    return sr_coupon_active_account_target_issues($pdo, $accountId, 'community_attachment', (string) $attachmentId, $limit);
}

// This helper owns its transaction boundary so fallback coupon targets cannot
// commit partial redemption work from a previous target attempt.
function sr_community_try_paid_read_coupon_access(PDO $pdo, int $accountId, array $post, array $paidReadConfig, string $couponDedupeKey, int $couponIssueId = 0, array $pricingContext = []): array
{
    $postId = (int) ($post['id'] ?? 0);
    $boardId = (int) ($post['board_id'] ?? 0);
    if ($accountId <= 0 || $postId <= 0 || $couponDedupeKey === '') {
        return ['allowed' => false, 'processed' => false];
    }

    if ((string) ($paidReadConfig['charge_policy'] ?? 'once') === 'once'
        && sr_community_once_access_already_granted($pdo, $paidReadConfig, $accountId, 'post_read', $postId, $couponDedupeKey)
    ) {
        return ['allowed' => true, 'processed' => false, 'already_redeemed' => true];
    }

    if (!sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return ['allowed' => false, 'processed' => false];
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_redeem_for_target')) {
        return ['allowed' => false, 'processed' => false];
    }

    $assetModules = sr_community_asset_module_keys_from_value($paidReadConfig['asset_module'] ?? '', true);
    $assetModuleValue = sr_community_asset_module_value_from_keys($assetModules, true);
    $amounts = is_array($paidReadConfig['amounts'] ?? null) ? $paidReadConfig['amounts'] : [];
    $policyAmounts = sr_community_asset_amounts_with_group_policy($pdo, $accountId, $assetModules, $amounts, (int) ($paidReadConfig['amount'] ?? 0), $paidReadConfig['group_policies_json'] ?? '', (int) ($paidReadConfig['policy_set_id'] ?? 0), 'use');
    $policySnapshotJson = sr_community_asset_group_policy_snapshot_json($policyAmounts['snapshots']);
    $settlementCurrency = sr_community_asset_settlement_currency($pdo, $paidReadConfig);
    $confirmationFingerprint = sr_community_asset_confirmation_fingerprint(
        'post_read',
        'community.post',
        (string) ($paidReadConfig['charge_policy'] ?? 'once'),
        $assetModuleValue,
        (int) $policyAmounts['amount'],
        is_array($policyAmounts['amounts'] ?? null) ? $policyAmounts['amounts'] : [],
        $policySnapshotJson,
        $settlementCurrency
    );

    $couponContext = [
        'dedupe_key' => $couponDedupeKey,
        'reference_module' => 'community',
        'reference_type' => 'community.post',
        'reference_id' => (string) $postId,
        'price_amount' => (int) $policyAmounts['amount'],
        'currency_code' => $settlementCurrency,
        'policy_summary' => '게시글 열람 ' . number_format((int) $policyAmounts['amount']) . $settlementCurrency,
    ];
    if ($couponIssueId > 0) {
        $couponContext['coupon_issue_id'] = $couponIssueId;
    }
    $couponContext = array_merge($couponContext, $pricingContext);

    foreach ([['community_post', (string) $postId], ['community_board', (string) $boardId]] as $target) {
        if ((string) $target[1] === '0') {
            continue;
        }

        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $couponResult = sr_coupon_redeem_for_target($pdo, $accountId, (string) $target[0], (string) $target[1], $couponContext);
            if (!empty($couponResult['allowed'])) {
                $remainingAmount = max(0, (int) ($couponResult['remaining_amount'] ?? 0));
                if ($remainingAmount > 0 && $startedTransaction) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    return ['allowed' => false, 'processed' => false, 'message' => '할인 쿠폰은 자산 결제와 함께 처리해야 합니다.'];
                }
                if ($remainingAmount <= 0 && empty($couponResult['already_entitled'])) {
                    sr_community_grant_access_entitlement($pdo, $accountId, 'community.post', $postId, 'post_read', 'coupon', '', (string) ($paidReadConfig['charge_policy'] ?? 'once'), $couponDedupeKey);
                }
                if ($remainingAmount <= 0 && !empty($couponResult['processed'])) {
                    $paymentItems = array_values(array_filter([
                        sr_community_payment_coupon_item($couponResult, $settlementCurrency),
                        sr_community_payment_access_item('community.post', $postId, 'post_read', 'coupon', $couponDedupeKey),
                    ]));
                    sr_community_record_payment_ledger_if_available($pdo, [
                        'dedupe_key' => 'community.post.read:payment:coupon:' . (string) ($couponResult['coupon_redemption_id'] ?? ''),
                        'account_id' => $accountId,
                        'subject_module' => 'community',
                        'subject_type' => 'community.post.read',
                        'subject_id' => (string) $postId,
                        'payment_kind' => 'purchase',
                        'payable_amount' => (int) $policyAmounts['amount'],
                        'settlement_amount' => 0,
                        'settlement_currency' => $settlementCurrency,
                        'description' => '커뮤니티 게시글 열람 쿠폰 결제',
                        'snapshot' => [
                            'charge_policy' => (string) ($paidReadConfig['charge_policy'] ?? 'once'),
                            'coupon_covered_amount' => (int) $policyAmounts['amount'],
                        ],
                    ], $paymentItems);
                    sr_community_record_post_read_payment_log($pdo, [
                        'board_id' => $boardId,
                        'post_id' => $postId,
                        'post_title_snapshot' => (string) ($post['title'] ?? ''),
                        'account_id' => $accountId,
                        'payment_type' => sr_community_post_read_payment_type($couponResult, false),
                        'settlement_kind' => 'paid_settled_zero',
                        'charge_policy' => (string) ($paidReadConfig['charge_policy'] ?? 'once'),
                        'asset_module' => '',
                        'payable_amount' => (int) $policyAmounts['amount'],
                        'settlement_amount' => 0,
                        'settlement_currency' => $settlementCurrency,
                        'asset_access_log_ids' => [],
                        'coupon_redemption_id' => (int) ($couponResult['coupon_redemption_id'] ?? 0),
                        'coupon_dedupe_key' => (string) ($couponResult['dedupe_key'] ?? $couponDedupeKey),
                        'payment_dedupe_key' => 'community.post.read:payment-unit:coupon:' . (string) ($couponResult['coupon_redemption_id'] ?? ''),
                    ]);
                }
                if ($startedTransaction) {
                    $pdo->commit();
                }

                $couponResult['confirmation_fingerprint'] = $confirmationFingerprint;
                return $couponResult;
            }

            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'community_coupon_entitlement_failed');
            }
        }
    }

    return ['allowed' => false, 'processed' => false];
}

function sr_community_try_attachment_download_coupon_access(PDO $pdo, int $accountId, int|array $attachment, array $downloadConfig, string $couponDedupeKey, int $couponIssueId = 0, array $pricingContext = []): array
{
    $attachmentId = is_array($attachment) ? (int) ($attachment['id'] ?? 0) : (int) $attachment;
    if ($accountId <= 0 || $attachmentId <= 0 || $couponDedupeKey === '') {
        return ['allowed' => false, 'processed' => false];
    }

    if ((string) ($downloadConfig['charge_policy'] ?? 'once') === 'once'
        && sr_community_once_access_already_granted($pdo, $downloadConfig, $accountId, 'attachment_download', $attachmentId, $couponDedupeKey)
    ) {
        return ['allowed' => true, 'processed' => false, 'already_redeemed' => true];
    }

    if (!sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return ['allowed' => false, 'processed' => false];
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_redeem_for_target')) {
        return ['allowed' => false, 'processed' => false];
    }

    $assetModules = sr_community_asset_module_keys_from_value($downloadConfig['asset_module'] ?? '', true);
    $assetModuleValue = sr_community_asset_module_value_from_keys($assetModules, true);
    $amounts = is_array($downloadConfig['amounts'] ?? null) ? $downloadConfig['amounts'] : [];
    $policyAmounts = sr_community_asset_amounts_with_group_policy($pdo, $accountId, $assetModules, $amounts, (int) ($downloadConfig['amount'] ?? 0), $downloadConfig['group_policies_json'] ?? '', (int) ($downloadConfig['policy_set_id'] ?? 0), 'use');
    $policySnapshotJson = sr_community_asset_group_policy_snapshot_json($policyAmounts['snapshots']);
    $settlementCurrency = sr_community_asset_settlement_currency($pdo, $downloadConfig);
    $confirmationFingerprint = sr_community_asset_confirmation_fingerprint(
        'attachment_download',
        'community.attachment',
        (string) ($downloadConfig['charge_policy'] ?? 'once'),
        $assetModuleValue,
        (int) $policyAmounts['amount'],
        is_array($policyAmounts['amounts'] ?? null) ? $policyAmounts['amounts'] : [],
        $policySnapshotJson,
        $settlementCurrency
    );

    $couponContext = [
        'dedupe_key' => $couponDedupeKey,
        'reference_module' => 'community',
        'reference_type' => 'community.attachment.download',
        'reference_id' => (string) $attachmentId,
        'price_amount' => (int) $policyAmounts['amount'],
        'currency_code' => $settlementCurrency,
        'policy_summary' => '첨부 다운로드 ' . number_format((int) $policyAmounts['amount']) . $settlementCurrency,
    ];
    if ($couponIssueId > 0) {
        $couponContext['coupon_issue_id'] = $couponIssueId;
    }
    $couponContext = array_merge($couponContext, $pricingContext);

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $couponResult = sr_coupon_redeem_for_target($pdo, $accountId, 'community_attachment', (string) $attachmentId, $couponContext);
        if (!empty($couponResult['allowed'])) {
            $remainingAmount = max(0, (int) ($couponResult['remaining_amount'] ?? 0));
            if ($remainingAmount > 0 && $startedTransaction) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                return ['allowed' => false, 'processed' => false, 'message' => '할인 쿠폰은 자산 결제와 함께 처리해야 합니다.'];
            }
            if ($remainingAmount <= 0 && empty($couponResult['already_entitled'])) {
                sr_community_grant_access_entitlement($pdo, $accountId, 'community.attachment', $attachmentId, 'attachment_download', 'coupon', '', (string) ($downloadConfig['charge_policy'] ?? 'once'), $couponDedupeKey);
            }
            if ($remainingAmount <= 0 && !empty($couponResult['processed'])) {
                $paymentItems = array_values(array_filter([
                    sr_community_payment_coupon_item($couponResult, $settlementCurrency),
                    sr_community_payment_access_item('community.attachment', $attachmentId, 'attachment_download', 'coupon', $couponDedupeKey),
                ]));
                sr_community_record_payment_ledger_if_available($pdo, [
                    'dedupe_key' => 'community.attachment.download:payment:coupon:' . (string) ($couponResult['coupon_redemption_id'] ?? ''),
                    'account_id' => $accountId,
                    'subject_module' => 'community',
                    'subject_type' => 'community.attachment.download',
                    'subject_id' => (string) $attachmentId,
                    'payment_kind' => 'purchase',
                    'payable_amount' => (int) $policyAmounts['amount'],
                    'settlement_amount' => 0,
                    'settlement_currency' => $settlementCurrency,
                    'description' => '커뮤니티 첨부 다운로드 쿠폰 결제',
                    'snapshot' => [
                        'charge_policy' => (string) ($downloadConfig['charge_policy'] ?? 'once'),
                        'coupon_covered_amount' => (int) $policyAmounts['amount'],
                    ],
                ], $paymentItems);
            }
            if ($startedTransaction) {
                $pdo->commit();
            }

            $couponResult['confirmation_fingerprint'] = $confirmationFingerprint;
            return $couponResult;
        }

        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'community_attachment_coupon_entitlement_failed');
        }
    }

    return ['allowed' => false, 'processed' => false];
}

function sr_community_insert_asset_log_placeholder(PDO $pdo, array $row): bool
{
    $settlementAmount = max(0, (int) ($row['settlement_amount'] ?? 0));
    $purchasePowerSnapshotJson = (string) ($row['purchase_power_snapshot_json'] ?? '');
    $settlementKind = sr_community_asset_settlement_kind(
        (string) $row['direction'],
        (int) $row['amount'],
        $settlementAmount,
        $purchasePowerSnapshotJson
    );
    $insertVerb = 'INSERT IGNORE';
    try {
        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $insertVerb = 'INSERT OR IGNORE';
        }
    } catch (Throwable $exception) {
        $insertVerb = 'INSERT IGNORE';
    }
    $stmt = $pdo->prepare(
        $insertVerb . ' INTO sr_community_asset_logs
            (account_id, asset_module, transaction_id, reference_type, reference_id, subject_type, subject_id, event_key, direction, charge_policy, amount, settlement_amount, settlement_currency, purchase_power_snapshot_json, settlement_kind, snapshot_schema_version, rounding_policy_version, log_status, group_policy_snapshot_json, dedupe_key, created_at)
         VALUES
            (:account_id, :asset_module, 0, :reference_type, :reference_id, :subject_type, :subject_id, :event_key, :direction, :charge_policy, :amount, :settlement_amount, :settlement_currency, :purchase_power_snapshot_json, :settlement_kind, :snapshot_schema_version, :rounding_policy_version, :log_status, :group_policy_snapshot_json, :dedupe_key, :created_at)'
    );
    $stmt->execute([
        'account_id' => (int) $row['account_id'],
        'asset_module' => (string) $row['asset_module'],
        'reference_type' => (string) $row['reference_type'],
        'reference_id' => (string) $row['reference_id'],
        'subject_type' => (string) $row['subject_type'],
        'subject_id' => (int) $row['subject_id'],
        'event_key' => (string) $row['event_key'],
        'direction' => (string) $row['direction'],
        'charge_policy' => (string) $row['charge_policy'],
        'amount' => (int) $row['amount'],
        'settlement_amount' => $settlementAmount,
        'settlement_currency' => sr_community_asset_settlement_currency($pdo, ['asset_settlement_currency' => (string) ($row['settlement_currency'] ?? 'KRW')]),
        'purchase_power_snapshot_json' => $purchasePowerSnapshotJson,
        'settlement_kind' => $settlementKind,
        'snapshot_schema_version' => sr_community_asset_snapshot_schema_version(),
        'rounding_policy_version' => sr_community_asset_rounding_policy_version(),
        'log_status' => sr_community_asset_log_status_pending(),
        'group_policy_snapshot_json' => (string) ($row['group_policy_snapshot_json'] ?? ''),
        'dedupe_key' => (string) $row['dedupe_key'],
        'created_at' => sr_now(),
    ]);

    return $stmt->rowCount() > 0;
}

function sr_community_update_asset_log_transaction(PDO $pdo, string $dedupeKey, int $transactionId): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_community_asset_logs
         SET transaction_id = :transaction_id,
             log_status = :log_status
         WHERE dedupe_key = :dedupe_key'
    );
    $stmt->execute([
        'transaction_id' => $transactionId,
        'log_status' => sr_community_asset_log_status_completed(),
        'dedupe_key' => $dedupeKey,
    ]);
}

function sr_community_complete_zero_asset_log(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_community_asset_logs
         SET log_status = :log_status
         WHERE dedupe_key = :dedupe_key
           AND transaction_id = 0
           AND amount = 0'
    );
    $stmt->execute([
        'log_status' => sr_community_asset_log_status_completed(),
        'dedupe_key' => $dedupeKey,
    ]);
}

function sr_community_delete_asset_log_placeholder(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM sr_community_asset_logs
         WHERE dedupe_key = :dedupe_key
           AND log_status = :log_status'
    );
    $stmt->execute([
        'dedupe_key' => $dedupeKey,
        'log_status' => sr_community_asset_log_status_pending(),
    ]);
}

function sr_community_run_asset_event(PDO $pdo, array $config, int $accountId, string $eventKey, string $subjectType, int $subjectId, string $direction, string $reason, bool $process = true, string $requestToken = '', bool $consumeConfirmationSession = true, bool $confirmedPost = false, bool $assetExchangeConfirmed = false, ?int $settlementAmountOverride = null, array $paymentContext = []): array
{
    return sr_community_asset_retry_operation($pdo, static function () use ($pdo, $config, $accountId, $eventKey, $subjectType, $subjectId, $direction, $reason, $process, $requestToken, $consumeConfirmationSession, $confirmedPost, $assetExchangeConfirmed, $settlementAmountOverride, $paymentContext): array {
        return sr_community_run_asset_event_once($pdo, $config, $accountId, $eventKey, $subjectType, $subjectId, $direction, $reason, $process, $requestToken, $consumeConfirmationSession, $confirmedPost, $assetExchangeConfirmed, $settlementAmountOverride, $paymentContext);
    });
}

function sr_community_run_asset_event_once(PDO $pdo, array $config, int $accountId, string $eventKey, string $subjectType, int $subjectId, string $direction, string $reason, bool $process = true, string $requestToken = '', bool $consumeConfirmationSession = true, bool $confirmedPost = false, bool $assetExchangeConfirmed = false, ?int $settlementAmountOverride = null, array $paymentContext = []): array
{
    $assetModules = sr_community_asset_module_keys_from_value($config['asset_module'] ?? '', true);
    $assetModuleValue = sr_community_asset_module_value_from_keys($assetModules, true);
    $amounts = is_array($config['amounts'] ?? null) ? $config['amounts'] : [];
    $amount = $amounts !== [] ? sr_community_asset_amount_total($amounts) : (int) ($config['amount'] ?? 0);
    $chargePolicy = (string) ($config['charge_policy'] ?? 'once');

    if ($accountId <= 0 || $subjectId <= 0 || $assetModules === []) {
        return ['allowed' => true, 'processed' => false, 'message' => ''];
    }

    if (!sr_community_asset_modules_available($pdo, $assetModules)) {
        return [
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_modules_unavailable',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'message' => sr_t('community::action.error.asset_modules_unavailable'),
        ];
    }

    $once = in_array($chargePolicy, ['once'], true) || in_array($direction, ['grant', 'refund'], true);
    $alreadyProcessed = false;
    if ($once && $direction === 'use') {
        $settings = sr_community_settings($pdo);
        $onceHistoryPolicy = sr_community_once_history_policy((string) ($settings['once_history_policy'] ?? 'all_access'));
        $alreadyProcessed = sr_community_has_access_entitlement($pdo, $assetModules, $accountId, $eventKey, $subjectType, $subjectId, '', $onceHistoryPolicy);
    } elseif ($once) {
        $alreadyProcessed = sr_community_has_asset_event_for_modules($pdo, $assetModules, $accountId, $eventKey, $subjectId);
    }
    if ($alreadyProcessed) {
        return [
            'allowed' => true,
            'processed' => false,
            'already_processed' => true,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'message' => '',
        ];
    }

    $policyAmounts = sr_community_asset_amounts_with_group_policy($pdo, $accountId, $assetModules, $amounts, (int) ($config['amount'] ?? 0), $config['group_policies_json'] ?? '', (int) ($config['policy_set_id'] ?? 0), $direction === 'use' ? 'use' : 'grant');
    $amounts = $amounts !== [] ? $policyAmounts['amounts'] : [];
    $amount = (int) $policyAmounts['amount'];
    $paymentPayableAmount = array_key_exists('payable_amount', $paymentContext)
        ? max(0, (int) $paymentContext['payable_amount'])
        : $amount;
    if ($direction === 'use' && $settlementAmountOverride !== null) {
        $amount = max(0, $settlementAmountOverride);
    }
    $policySnapshotJson = sr_community_asset_group_policy_snapshot_json($policyAmounts['snapshots']);
    $settlementCurrency = sr_community_asset_settlement_currency($pdo, $config);
    $confirmationFingerprint = sr_community_asset_confirmation_fingerprint($eventKey, $subjectType, $chargePolicy, $assetModuleValue, $amount, $amounts, $policySnapshotJson, $settlementCurrency);
    $confirmationRequired = $direction === 'use'
        && in_array($eventKey, ['post_read', 'attachment_download'], true)
        && sr_community_asset_policy_requires_confirmation($chargePolicy);
    if ($confirmationRequired && !$process) {
        if ($consumeConfirmationSession && sr_community_consume_asset_confirmation_session($eventKey, $subjectType, $accountId, $subjectId, $confirmationFingerprint)) {
            return [
                'allowed' => true,
                'processed' => false,
                'confirmed_access' => true,
                'asset_module' => $assetModuleValue,
                'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
                'amount' => $amount,
                'confirmation_fingerprint' => $confirmationFingerprint,
                'message' => '',
            ];
        }

        $extra = sr_community_asset_settlement_exchange_confirmation_extra($pdo, $assetModules, $accountId, $amount, $settlementCurrency);

        return array_merge([
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_confirmation_required',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'confirmation_fingerprint' => $confirmationFingerprint,
            'confirmation_request_token' => sr_community_asset_confirmation_request_token($eventKey, $subjectType, $accountId, $subjectId, $confirmationFingerprint),
            'message' => (string) ($extra['message'] ?? sr_community_asset_confirmation_required_message()),
        ], $extra);
    } elseif ($confirmationRequired && $process && !$confirmedPost) {
        $extra = sr_community_asset_settlement_exchange_confirmation_extra($pdo, $assetModules, $accountId, $amount, $settlementCurrency);

        return array_merge([
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_confirmation_required',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'confirmation_fingerprint' => $confirmationFingerprint,
            'confirmation_request_token' => sr_community_asset_confirmation_request_token($eventKey, $subjectType, $accountId, $subjectId, $confirmationFingerprint),
            'message' => (string) ($extra['message'] ?? sr_community_asset_confirmation_required_message()),
        ], $extra);
    }

    if ($amount <= 0) {
        if ($direction === 'use') {
            $zeroAmountSuffix = match ($eventKey) {
                'post_read' => '게시글을 열람할 수 없습니다.',
                'attachment_download' => '첨부 파일을 다운로드할 수 없습니다.',
                'post_write_charge' => '글을 작성할 수 없습니다.',
                'comment_write_charge' => '댓글을 작성할 수 없습니다.',
                default => '처리할 수 없습니다.',
            };
            $configErrorMessage = sr_community_asset_settlement_config_error_message($pdo, $assetModules, $accountId, 0, $settlementCurrency, $zeroAmountSuffix);
            if ($configErrorMessage !== '') {
                return [
                    'allowed' => false,
                    'processed' => false,
                    'error_key' => 'asset_settlement_config_error',
                    'asset_module' => $assetModuleValue,
                    'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
                    'amount' => $amount,
                    'message' => $configErrorMessage,
                ];
            }
        }

        $assetModule = (string) ($assetModules[0] ?? $assetModuleValue);
        $stableRequestToken = preg_match('/\A[a-f0-9]{32}(?:[a-f0-9]{32})?\z/', $requestToken) === 1 ? $requestToken : bin2hex(random_bytes(16));
        $dedupeKey = $once
            ? sr_community_asset_dedupe_key($assetModule, $accountId, $eventKey, $subjectId)
            : 'community.' . $eventKey . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId . ':0:' . $settlementCurrency . ':' . $stableRequestToken;
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }
        try {
            sr_community_insert_asset_log_placeholder($pdo, [
                'account_id' => $accountId,
                'asset_module' => $assetModule,
                'reference_type' => $subjectType,
                'reference_id' => (string) $subjectId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'event_key' => $eventKey,
                'direction' => $direction,
                'charge_policy' => $chargePolicy,
                'amount' => 0,
                'group_policy_snapshot_json' => $policySnapshotJson,
                'dedupe_key' => $dedupeKey,
            ]);
            sr_community_complete_zero_asset_log($pdo, $dedupeKey);
            if ($direction === 'use' && in_array($eventKey, ['post_read', 'attachment_download'], true)) {
                sr_community_grant_access_entitlement($pdo, $accountId, $subjectType, $subjectId, $eventKey, 'asset_group_policy', $assetModule, $chargePolicy, $dedupeKey);
            }
            if ($direction === 'use' && $eventKey === 'post_read' && $subjectType === 'community.post') {
                $zeroAssetLog = sr_community_asset_log($pdo, $dedupeKey);
                sr_community_record_post_read_payment_log($pdo, [
                    'board_id' => (int) ($paymentContext['board_id'] ?? 0),
                    'post_id' => $subjectId,
                    'post_title_snapshot' => (string) ($paymentContext['post_title_snapshot'] ?? ''),
                    'account_id' => $accountId,
                    'payment_type' => 'settled_zero',
                    'settlement_kind' => 'paid_settled_zero',
                    'charge_policy' => $chargePolicy,
                    'asset_module' => $assetModule,
                    'payable_amount' => $paymentPayableAmount,
                    'settlement_amount' => 0,
                    'settlement_currency' => $settlementCurrency,
                    'asset_access_log_ids' => is_array($zeroAssetLog) && (int) ($zeroAssetLog['id'] ?? 0) > 0 ? [(int) $zeroAssetLog['id']] : [],
                    'payment_dedupe_key' => 'community.post.read:payment-unit:' . sha1($dedupeKey),
                ]);
            }
            if ($startedTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($startedTransaction && sr_community_asset_is_retryable_transaction_exception($exception)) {
                throw $exception;
            }
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'community_asset_group_event_failed');
            }

            return [
                'allowed' => false,
                'processed' => false,
                'error_key' => 'asset_processing_failed',
                'asset_module' => $assetModuleValue,
                'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
                'amount' => 0,
                'message' => sr_t('community::action.error.asset_processing_failed'),
            ];
        }

        return [
            'allowed' => true,
            'processed' => true,
            'group_policy_applied' => true,
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => 0,
            'direction' => $direction,
            'message' => '',
        ];
    }

    $assetExchangeSuggestion = [];
    $allocations = $direction === 'use'
        ? sr_community_allocate_asset_settlement_use($pdo, $assetModules, $accountId, $amount, $settlementCurrency)
        : [['asset_module' => $assetModules[0], 'amount' => $amount]];
    if (
        $direction === 'use'
        && $allocations !== []
        && in_array($eventKey, ['post_read', 'attachment_download'], true)
        && !sr_community_multi_asset_payment_enabled($pdo)
        && sr_community_multi_asset_payment_allocation_count($allocations) > 1
    ) {
        $message = $eventKey === 'attachment_download'
            ? sr_community_multi_asset_payment_disabled_message('첨부 다운로드')
            : sr_community_multi_asset_payment_disabled_message('게시글 열람');

        return [
            'allowed' => false,
            'processed' => false,
            'error_key' => 'multi_asset_payment_disabled',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'confirmation_fingerprint' => $confirmationFingerprint,
            'message' => $message,
        ];
    }
    if ($direction === 'use' && $allocations === []) {
        $assetExchangeSuggestion = sr_community_asset_settlement_exchange_suggestion($pdo, $assetModules, $accountId, $amount, $settlementCurrency);
        if ($assetExchangeSuggestion !== [] && $confirmationRequired) {
            if (!$assetExchangeConfirmed) {
                return [
                    'allowed' => false,
                    'processed' => false,
                    'error_key' => 'asset_confirmation_required',
                    'asset_module' => $assetModuleValue,
                    'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
                    'amount' => $amount,
                    'confirmation_fingerprint' => $confirmationFingerprint,
                    'confirmation_request_token' => sr_community_asset_confirmation_request_token($eventKey, $subjectType, $accountId, $subjectId, $confirmationFingerprint),
                    'asset_exchange_suggestion' => $assetExchangeSuggestion,
                    'asset_exchange_confirmation_required' => true,
                    'message' => sr_member_asset_settlement_exchange_message($pdo, sr_community_asset_modules($pdo), $assetExchangeSuggestion, sr_community_asset_confirmation_required_message()),
                ];
            }
        } else {
            $assetExchangeSuggestion = [];
        }
        $balanceLowSuffix = match ($eventKey) {
            'post_read' => '게시글을 열람할 수 없습니다.',
            'attachment_download' => '첨부 파일을 다운로드할 수 없습니다.',
            'post_write_charge' => '글을 작성할 수 없습니다.',
            'comment_write_charge' => '댓글을 작성할 수 없습니다.',
            default => '처리할 수 없습니다.',
        };
        if ($assetExchangeSuggestion === []) {
            return [
                'allowed' => false,
                'processed' => false,
                'error_key' => 'asset_balance_low',
                'asset_module' => $assetModuleValue,
                'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
                'amount' => $amount,
                'confirmation_fingerprint' => $confirmationFingerprint,
                'message' => sr_community_asset_balance_shortage_message($pdo, $assetModules, $accountId, $amount, $settlementCurrency, $balanceLowSuffix, sr_t('community::action.error.asset_balance_low')),
            ];
        }
    }

    $processed = false;
    $processedLogs = [];
    $dedupeKey = '';
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $assetExchangeLogId = 0;
        if ($direction === 'use' && $assetExchangeSuggestion !== [] && $assetExchangeConfirmed) {
            $assetExchangeLogId = sr_member_asset_settlement_execute_exchange_suggestion($pdo, $assetExchangeSuggestion, $accountId);
            $allocations = sr_community_allocate_asset_settlement_use($pdo, $assetModules, $accountId, $amount, $settlementCurrency);
            if ($allocations === []) {
                throw new RuntimeException('Automatic asset exchange did not create a payable community settlement plan.');
            }
            if (
                in_array($eventKey, ['post_read', 'attachment_download'], true)
                && !sr_community_multi_asset_payment_enabled($pdo)
                && sr_community_multi_asset_payment_allocation_count($allocations) > 1
            ) {
                throw new RuntimeException('Multi-asset community payment is disabled.');
            }
        }

        $pendingAssetEvents = [];
        foreach ($allocations as $allocation) {
            $assetModule = (string) $allocation['asset_module'];
            $allocatedAmount = (int) ($allocation['asset_amount'] ?? $allocation['amount']);
            $allocatedSettlementAmount = $direction === 'use' ? (int) ($allocation['settlement_amount'] ?? 0) : 0;
            $allocationSettlementCurrency = $direction === 'use' ? (string) ($allocation['settlement_currency'] ?? $settlementCurrency) : $settlementCurrency;
            $purchasePowerSnapshotJson = $direction === 'use' ? sr_community_asset_purchase_power_snapshot_json(is_array($allocation['purchase_power_snapshot'] ?? null) ? $allocation['purchase_power_snapshot'] : []) : '';
            $stableRequestToken = preg_match('/\A[a-f0-9]{32}(?:[a-f0-9]{32})?\z/', $requestToken) === 1 ? $requestToken : bin2hex(random_bytes(16));
            $dedupeKey = $once
                ? sr_community_asset_dedupe_key($assetModule, $accountId, $eventKey, $subjectId)
                : 'community.' . $eventKey . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId . ':' . (string) max(0, $amount) . ':' . $settlementCurrency . ':' . $stableRequestToken;
            $inserted = sr_community_insert_asset_log_placeholder($pdo, [
                'account_id' => $accountId,
                'asset_module' => $assetModule,
                'reference_type' => $subjectType,
                'reference_id' => (string) $subjectId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'event_key' => $eventKey,
                'direction' => $direction,
                'charge_policy' => $chargePolicy,
                'amount' => $allocatedAmount,
                'settlement_amount' => $allocatedSettlementAmount,
                'settlement_currency' => $allocationSettlementCurrency,
                'purchase_power_snapshot_json' => $purchasePowerSnapshotJson,
                'group_policy_snapshot_json' => sr_community_asset_group_policy_snapshot_json(isset($policyAmounts['snapshots'][$assetModule]) ? [$policyAmounts['snapshots'][$assetModule]] : []),
                'dedupe_key' => $dedupeKey,
            ]);
            if (!$inserted) {
                if ($once) {
                    throw new RuntimeException('Incomplete or duplicate community asset event.');
                }
                $existingLog = sr_community_asset_log($pdo, $dedupeKey);
                if (is_array($existingLog) && (string) ($existingLog['log_status'] ?? '') === sr_community_asset_log_status_completed()) {
                    $transactionId = (int) ($existingLog['transaction_id'] ?? 0);
                    if ($direction === 'use' && $transactionId > 0 && in_array($eventKey, ['post_read', 'attachment_download'], true)) {
                        sr_community_grant_access_entitlement($pdo, $accountId, $subjectType, $subjectId, $eventKey, 'asset', $assetModule, $chargePolicy, $assetModule . ':' . (string) $transactionId);
                    }
                    $processedLogs[] = [
                        'dedupe_key' => $dedupeKey,
                        'asset_module' => $assetModule,
                        'transaction_id' => $transactionId,
                        'amount' => (int) ($existingLog['amount'] ?? $allocatedAmount),
                        'settlement_amount' => (int) ($existingLog['settlement_amount'] ?? $allocatedSettlementAmount),
                        'settlement_currency' => (string) ($existingLog['settlement_currency'] ?? $allocationSettlementCurrency),
                    ];
                    continue;
                }
                throw new RuntimeException('Community asset event is still processing.');
            }

            $pendingAssetEvents[] = [
                'asset_module' => $assetModule,
                'amount' => $allocatedAmount,
                'dedupe_key' => $dedupeKey,
            ];
        }

        foreach ($pendingAssetEvents as $pendingAssetEvent) {
            $assetModule = (string) $pendingAssetEvent['asset_module'];
            $allocatedAmount = (int) $pendingAssetEvent['amount'];
            $dedupeKey = (string) $pendingAssetEvent['dedupe_key'];
            $module = sr_community_asset_modules($pdo)[$assetModule];
            $signedAmount = $direction === 'use' ? -$allocatedAmount : $allocatedAmount;
            $transactionType = $direction === 'use'
                ? (string) ($module['use_type'] ?? 'use')
                : ($direction === 'refund' ? (string) ($module['refund_type'] ?? 'refund') : (string) ($module['credit_type'] ?? 'grant'));
            $transactionId = sr_community_create_asset_transaction($pdo, $assetModule, [
                'account_id' => $accountId,
                'amount' => $signedAmount,
                'transaction_type' => $transactionType,
                'reason' => $reason,
                'reference_type' => $subjectType,
                'reference_id' => (string) $subjectId,
                'created_by_account_id' => null,
            ]);
            sr_community_update_asset_log_transaction($pdo, $dedupeKey, $transactionId);
            if ($direction === 'use' && in_array($eventKey, ['post_read', 'attachment_download'], true)) {
                sr_community_grant_access_entitlement($pdo, $accountId, $subjectType, $subjectId, $eventKey, 'asset', $assetModule, $chargePolicy, $assetModule . ':' . (string) $transactionId);
            }
            $assetLog = sr_community_asset_log($pdo, $dedupeKey);
            $processedLogs[] = [
                'dedupe_key' => $dedupeKey,
                'asset_module' => $assetModule,
                'transaction_id' => $transactionId,
                'amount' => $allocatedAmount,
                'settlement_amount' => is_array($assetLog) ? (int) ($assetLog['settlement_amount'] ?? 0) : 0,
                'settlement_currency' => is_array($assetLog) ? (string) ($assetLog['settlement_currency'] ?? $settlementCurrency) : $settlementCurrency,
            ];
            $processed = true;
        }

        if ($direction === 'use' && $processed && $processedLogs !== []) {
            $paymentItems = [];
            $paymentDedupeParts = [];
            $assetLogIds = [];
            $couponResult = is_array($paymentContext['coupon_result'] ?? null) ? $paymentContext['coupon_result'] : [];
            $couponPaymentItem = sr_community_payment_coupon_item($couponResult, $settlementCurrency);
            if ($couponPaymentItem !== []) {
                $paymentItems[] = $couponPaymentItem;
                $paymentDedupeParts[] = 'coupon:' . (string) ($couponResult['coupon_redemption_id'] ?? '');
            }

            foreach ($processedLogs as $processedLog) {
                $logDedupeKey = (string) ($processedLog['dedupe_key'] ?? '');
                $assetModule = (string) ($processedLog['asset_module'] ?? '');
                $transactionId = (int) ($processedLog['transaction_id'] ?? 0);
                $allocatedAmount = max(0, (int) ($processedLog['amount'] ?? 0));
                $logSettlementAmount = max(0, (int) ($processedLog['settlement_amount'] ?? 0));
                $logSettlementCurrency = (string) ($processedLog['settlement_currency'] ?? $settlementCurrency);
                if ($logDedupeKey === '' || $assetModule === '' || $transactionId <= 0 || $allocatedAmount <= 0) {
                    continue;
                }

                $paymentDedupeParts[] = $logDedupeKey;
                $paymentItems[] = [
                    'item_kind' => 'asset_transaction',
                    'owner_module' => $assetModule,
                    'reference_type' => $assetModule . '_transaction',
                    'reference_id' => (string) $transactionId,
                    'amount' => -$allocatedAmount,
                    'currency_code' => $logSettlementCurrency,
                    'reversible' => true,
                    'snapshot' => [
                        'settlement_amount' => $logSettlementAmount,
                        'community_asset_dedupe_key' => $logDedupeKey,
                    ],
                ];

                $assetLog = sr_community_asset_log($pdo, $logDedupeKey);
                if (is_array($assetLog) && (int) ($assetLog['id'] ?? 0) > 0) {
                    $assetLogIds[] = (int) $assetLog['id'];
                    $paymentItems[] = [
                        'item_kind' => 'asset_access_log',
                        'owner_module' => 'community',
                        'reference_type' => 'community_asset_log',
                        'reference_id' => (string) ((int) ($assetLog['id'] ?? 0)),
                        'amount' => $logSettlementAmount,
                        'currency_code' => $logSettlementCurrency,
                        'reversible' => true,
                        'snapshot' => [
                            'asset_module' => $assetModule,
                            'transaction_id' => $transactionId,
                            'dedupe_key' => $logDedupeKey,
                            'event_key' => $eventKey,
                        ],
                    ];
                }
            }

            if ($paymentDedupeParts !== []) {
                if (in_array($eventKey, ['post_read', 'attachment_download'], true)) {
                    $sourceReference = implode(',', $paymentDedupeParts);
                    $sourceKind = $couponPaymentItem !== [] ? 'mixed' : 'asset';
                    $paymentItems[] = sr_community_payment_access_item($subjectType, $subjectId, $eventKey, $sourceKind, $sourceReference);
                }

                sr_community_record_payment_ledger_if_available($pdo, [
                    'dedupe_key' => sr_community_payment_subject_type($eventKey, $subjectType) . ':payment:' . sha1(implode('|', $paymentDedupeParts)),
                    'account_id' => $accountId,
                    'subject_module' => 'community',
                    'subject_type' => sr_community_payment_subject_type($eventKey, $subjectType),
                    'subject_id' => (string) $subjectId,
                    'payment_kind' => 'purchase',
                    'payable_amount' => $paymentPayableAmount,
                    'settlement_amount' => $amount,
                    'settlement_currency' => $settlementCurrency,
                    'description' => sr_community_payment_description($eventKey),
                    'snapshot' => [
                        'charge_policy' => $chargePolicy,
                        'coupon_discount_amount' => (int) ($couponResult['discount_amount'] ?? 0),
                        'remaining_amount' => $amount,
                        'asset_exchange_log_id' => $assetExchangeLogId ?? 0,
                    ],
                ], $paymentItems);
                if ($eventKey === 'post_read' && $subjectType === 'community.post') {
                    sr_community_record_post_read_payment_log($pdo, [
                        'board_id' => (int) ($paymentContext['board_id'] ?? 0),
                        'post_id' => $subjectId,
                        'post_title_snapshot' => (string) ($paymentContext['post_title_snapshot'] ?? ''),
                        'account_id' => $accountId,
                        'payment_type' => sr_community_post_read_payment_type($couponResult, true),
                        'settlement_kind' => 'paid',
                        'charge_policy' => $chargePolicy,
                        'asset_module' => $assetModuleValue,
                        'payable_amount' => $paymentPayableAmount,
                        'settlement_amount' => $amount,
                        'settlement_currency' => $settlementCurrency,
                        'asset_access_log_ids' => $assetLogIds,
                        'coupon_redemption_id' => (int) ($couponResult['coupon_redemption_id'] ?? 0),
                        'coupon_dedupe_key' => (string) ($couponResult['dedupe_key'] ?? ''),
                        'payment_dedupe_key' => 'community.post.read:payment-unit:' . sha1(implode('|', $paymentDedupeParts)),
                    ]);
                }
            }
        }

        if ($pendingAssetEvents === [] && $processedLogs !== []) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return [
                'allowed' => true,
                'processed' => false,
                'already_processed' => true,
                'asset_module' => $assetModuleValue,
                'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
                'amount' => $amount,
                'direction' => $direction,
                'logs' => $processedLogs,
                'message' => '',
            ];
        }

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        } elseif ($dedupeKey !== '') {
            sr_community_delete_asset_log_placeholder($pdo, $dedupeKey);
        }
        if ($startedTransaction && sr_community_asset_is_retryable_transaction_exception($exception)) {
            throw $exception;
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'community_asset_event_failed');
        }

        return [
            'allowed' => false,
            'processed' => false,
            'error_key' => 'asset_processing_failed',
            'asset_module' => $assetModuleValue,
            'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
            'amount' => $amount,
            'message' => sr_t('community::action.error.asset_processing_failed'),
        ];
    }

    return [
        'allowed' => true,
        'processed' => $processed,
        'asset_module' => $assetModuleValue,
        'asset_label' => sr_community_asset_module_labels($assetModuleValue, $pdo),
        'amount' => $amount,
        'direction' => $direction,
        'processed_logs' => $processedLogs,
        'confirmation_fingerprint' => $confirmationFingerprint,
        'asset_exchange_log_id' => $assetExchangeLogId ?? 0,
        'message' => '',
    ];
}

function sr_community_asset_log_by_dedupe_key(PDO $pdo, string $dedupeKey): ?array
{
    return sr_community_asset_log($pdo, $dedupeKey);
}

function sr_community_publisher_reward_log_by_dedupe_key(PDO $pdo, string $dedupeKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_community_publisher_reward_logs
         WHERE dedupe_key = :dedupe_key
         LIMIT 1'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_create_publisher_reward_notification(PDO $pdo, int $publisherAccountId, array $rewardLog): void
{
    if ($publisherAccountId < 1 || !is_file(SR_ROOT . '/modules/community/helpers/notifications.php')) {
        return;
    }

    require_once SR_ROOT . '/modules/community/helpers/notifications.php';
    if (!function_exists('sr_community_create_account_event_notification')) {
        return;
    }

    sr_community_create_account_event_notification($pdo, $publisherAccountId, 'attachment.publisher_reward.granted', [
        'amount' => number_format((int) ($rewardLog['reward_amount'] ?? 0)),
        'asset' => sr_community_asset_module_label((string) ($rewardLog['asset_module'] ?? ''), $pdo),
        'link_url' => sr_url('/community/post?id=' . rawurlencode((string) (int) ($rewardLog['post_id'] ?? 0))),
    ], null);
}

function sr_community_grant_attachment_publisher_reward(PDO $pdo, array $board, array $settings, array $post, array $attachment, int $downloaderAccountId, array $downloadResult): void
{
    $config = sr_community_publisher_reward_config($pdo, $board, $settings);
    if (empty($config['enabled']) || (int) ($config['rate'] ?? 0) <= 0 || empty($downloadResult['processed'])) {
        return;
    }

    $publisherAccountId = (int) ($post['author_account_id'] ?? 0);
    $attachmentId = (int) ($attachment['id'] ?? 0);
    $postId = (int) ($post['id'] ?? 0);
    if ($publisherAccountId < 1 || $downloaderAccountId < 1 || $attachmentId < 1 || $postId < 1 || $publisherAccountId === $downloaderAccountId) {
        return;
    }

    $processedLogs = is_array($downloadResult['processed_logs'] ?? null) ? $downloadResult['processed_logs'] : [];
    foreach ($processedLogs as $processedLog) {
        $dedupeKey = (string) ($processedLog['dedupe_key'] ?? '');
        if ($dedupeKey === '') {
            continue;
        }

        $chargeLog = sr_community_asset_log_by_dedupe_key($pdo, $dedupeKey);
        if (!is_array($chargeLog)
            || (string) ($chargeLog['event_key'] ?? '') !== 'attachment_download'
            || (string) ($chargeLog['direction'] ?? '') !== 'use'
            || (int) ($chargeLog['transaction_id'] ?? 0) < 1
            || (int) ($chargeLog['amount'] ?? 0) <= 0
        ) {
            continue;
        }

        $chargeAmount = (int) $chargeLog['amount'];
        $rewardRate = (int) $config['rate'];
        $rewardAmount = intdiv($chargeAmount * $rewardRate, 100);
        if ($rewardAmount <= 0) {
            continue;
        }

        $rewardDedupeKey = 'community.attachment_download.publisher_reward:' . (string) (int) $chargeLog['id'];
        if (sr_community_publisher_reward_log_by_dedupe_key($pdo, $rewardDedupeKey) !== null) {
            continue;
        }

        $now = sr_now();
        $insert = $pdo->prepare(
            'INSERT INTO sr_community_publisher_reward_logs
                (charge_asset_log_id, charge_transaction_id, reward_transaction_id, reversal_transaction_id,
                 post_id, attachment_id, downloader_account_id, publisher_account_id, asset_module,
                 charge_amount, reward_rate, reward_amount, status, dedupe_key, failure_message, created_at, updated_at)
             VALUES
                (:charge_asset_log_id, :charge_transaction_id, 0, 0,
                 :post_id, :attachment_id, :downloader_account_id, :publisher_account_id, :asset_module,
                 :charge_amount, :reward_rate, :reward_amount, \'pending\', :dedupe_key, NULL, :created_at, :updated_at)'
        );
        try {
            $insert->execute([
                'charge_asset_log_id' => (int) $chargeLog['id'],
                'charge_transaction_id' => (int) $chargeLog['transaction_id'],
                'post_id' => $postId,
                'attachment_id' => $attachmentId,
                'downloader_account_id' => $downloaderAccountId,
                'publisher_account_id' => $publisherAccountId,
                'asset_module' => (string) $chargeLog['asset_module'],
                'charge_amount' => $chargeAmount,
                'reward_rate' => $rewardRate,
                'reward_amount' => $rewardAmount,
                'dedupe_key' => $rewardDedupeKey,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable $exception) {
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'community_publisher_reward_log_insert_failed');
            }
            continue;
        }

        try {
            $transactionId = sr_community_create_asset_transaction($pdo, (string) $chargeLog['asset_module'], [
                'account_id' => $publisherAccountId,
                'amount' => $rewardAmount,
                'transaction_type' => (string) ((sr_community_asset_modules($pdo)[(string) $chargeLog['asset_module']]['credit_type'] ?? 'grant')),
                'reason' => '첨부 다운로드 리워드',
                'reference_type' => 'community.attachment.publisher_reward',
                'reference_id' => (string) (int) $chargeLog['id'],
                'created_by_account_id' => null,
            ]);
            $stmt = $pdo->prepare(
                "UPDATE sr_community_publisher_reward_logs
                 SET reward_transaction_id = :transaction_id,
                     status = 'granted',
                     updated_at = :updated_at
                 WHERE dedupe_key = :dedupe_key"
            );
            $stmt->execute([
                'transaction_id' => $transactionId,
                'updated_at' => sr_now(),
                'dedupe_key' => $rewardDedupeKey,
            ]);
            $rewardLog = sr_community_publisher_reward_log_by_dedupe_key($pdo, $rewardDedupeKey);
            if (is_array($rewardLog)) {
                sr_community_create_publisher_reward_notification($pdo, $publisherAccountId, $rewardLog);
            }
        } catch (Throwable $exception) {
            $stmt = $pdo->prepare(
                "UPDATE sr_community_publisher_reward_logs
                 SET status = 'failed',
                     failure_message = :failure_message,
                     updated_at = :updated_at
                 WHERE dedupe_key = :dedupe_key"
            );
            $stmt->execute([
                'failure_message' => mb_substr($exception->getMessage(), 0, 1000),
                'updated_at' => sr_now(),
                'dedupe_key' => $rewardDedupeKey,
            ]);
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'community_publisher_reward_grant_failed');
            }
        }
    }
}

function sr_community_asset_reversal_config(array $originalLog): array
{
    return [
        'enabled' => true,
        'asset_module' => (string) ($originalLog['asset_module'] ?? 'point'),
        'amount' => (int) ($originalLog['amount'] ?? 0),
        'charge_policy' => 'once',
    ];
}

function sr_community_asset_grant_log_for_reversal(PDO $pdo, int $accountId, string $grantEventKey, int $subjectId): ?array
{
    foreach (array_keys(sr_community_asset_modules($pdo)) as $assetModule) {
        $original = sr_community_asset_log($pdo, sr_community_asset_dedupe_key((string) $assetModule, $accountId, $grantEventKey, $subjectId));
        if (!is_array($original)
            || (int) ($original['transaction_id'] ?? 0) < 1
            || (string) ($original['direction'] ?? '') !== 'grant'
            || (string) ($original['log_status'] ?? sr_community_asset_log_status_completed()) !== sr_community_asset_log_status_completed()
        ) {
            continue;
        }

        return $original;
    }

    return null;
}

function sr_community_asset_recovery_failure_by_original(PDO $pdo, int $originalAssetLogId, string $reversalEventKey): ?array
{
    if (!sr_community_asset_recovery_failures_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_community_asset_recovery_failures
         WHERE original_asset_log_id = :original_asset_log_id
           AND reversal_event_key = :reversal_event_key
         LIMIT 1'
    );
    $stmt->execute([
        'original_asset_log_id' => $originalAssetLogId,
        'reversal_event_key' => $reversalEventKey,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_asset_recovery_failures_table_exists(PDO $pdo): bool
{
    static $existsByConnection = [];

    $connectionKey = (string) spl_object_id($pdo);
    if (array_key_exists($connectionKey, $existsByConnection)) {
        return $existsByConnection[$connectionKey];
    }

    try {
        $stmt = $pdo->query('SELECT 1 FROM sr_community_asset_recovery_failures LIMIT 1');
        $existsByConnection[$connectionKey] = $stmt !== false;
    } catch (Throwable $exception) {
        $existsByConnection[$connectionKey] = false;
    }

    return $existsByConnection[$connectionKey];
}

function sr_community_asset_completed_reversal_amount(PDO $pdo, array $originalLog, string $reversalEventKey): int
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0) AS amount_value
         FROM sr_community_asset_logs
         WHERE account_id = :account_id
           AND asset_module = :asset_module
           AND subject_type = :subject_type
           AND subject_id = :subject_id
           AND event_key = :event_key
           AND direction = \'use\'
           AND log_status = \'completed\'
           AND transaction_id > 0'
    );
    $stmt->execute([
        'account_id' => (int) ($originalLog['account_id'] ?? 0),
        'asset_module' => (string) ($originalLog['asset_module'] ?? ''),
        'subject_type' => (string) ($originalLog['subject_type'] ?? ''),
        'subject_id' => (int) ($originalLog['subject_id'] ?? 0),
        'event_key' => $reversalEventKey,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? max(0, (int) ($row['amount_value'] ?? 0)) : 0;
}

function sr_community_asset_recovery_context_json(array $operationContext): string
{
    $allowedKeys = [
        'operation_event_key',
        'before_status',
        'after_status',
        'actor_type',
        'route_context',
        'batch_operation_key',
    ];
    $context = [];
    foreach ($allowedKeys as $key) {
        if (!array_key_exists($key, $operationContext)) {
            continue;
        }
        $value = $operationContext[$key];
        if (is_scalar($value) || $value === null) {
            $context[$key] = mb_substr((string) $value, 0, 120);
        }
    }
    $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return is_string($encoded) ? $encoded : '{}';
}

function sr_community_asset_recovery_canonical_event_key(string $eventKey, string $subjectType, string $purpose): string
{
    $subjectDomain = match ($subjectType) {
        'community.comment' => 'comment',
        default => 'post',
    };
    $purposeName = match ($purpose) {
        'reversal' => 'reward_reversal',
        default => 'reward_grant',
    };
    $canonical = 'community.' . $subjectDomain . '.' . $purposeName;

    return function_exists('sr_asset_recovery_event_key_valid') && sr_asset_recovery_event_key_valid($eventKey) ? $eventKey : $canonical;
}

function sr_community_asset_recovery_legacy_event_key(string $eventKey, string $subjectType, string $purpose): string
{
    if ($eventKey === 'community.post.reward_grant') {
        return 'post_reward';
    }
    if ($eventKey === 'community.comment.reward_grant') {
        return 'comment_reward';
    }
    if ($eventKey === 'community.post.reward_reversal') {
        return 'post_reward_reversal';
    }
    if ($eventKey === 'community.comment.reward_reversal') {
        return 'comment_reward_reversal';
    }

    if (!str_contains($eventKey, '.')) {
        return $eventKey;
    }

    $subjectDomain = $subjectType === 'community.comment' ? 'comment' : 'post';
    if ($purpose === 'reversal') {
        return $subjectDomain . '_reward_reversal';
    }

    return $subjectDomain . '_reward';
}

function sr_community_asset_recovery_savepoint_name(): string
{
    static $counter = 0;
    $counter++;

    return 'sr_community_recovery_' . (string) $counter;
}

function sr_community_asset_recovery_upsert(PDO $pdo, array $originalLog, string $subjectType, int $subjectId, string $grantEventKey, string $reversalEventKey, int $recoveredAmount, string $failureReason, array $operationContext = []): int
{
    if (!sr_community_asset_recovery_failures_table_exists($pdo)) {
        throw new RuntimeException('Community asset recovery failure table is not available.');
    }

    $attemptedAmount = max(0, (int) ($originalLog['amount'] ?? 0));
    $recoveredAmount = max(0, min($attemptedAmount, $recoveredAmount));
    $unrecoveredAmount = max(0, $attemptedAmount - $recoveredAmount);
    $status = $unrecoveredAmount > 0 ? 'open' : 'recovered';
    $now = sr_now();
    $canonicalGrantEventKey = sr_community_asset_recovery_canonical_event_key($grantEventKey, $subjectType, 'grant');
    $canonicalReversalEventKey = sr_community_asset_recovery_canonical_event_key($reversalEventKey, $subjectType, 'reversal');
    $operationEventKey = mb_substr((string) ($operationContext['operation_event_key'] ?? ''), 0, 80);
    $actorAccountIdValue = isset($operationContext['actor_account_id']) ? (int) $operationContext['actor_account_id'] : 0;
    $actorAccountId = $actorAccountIdValue > 0 ? $actorAccountIdValue : null;
    $actorType = mb_substr((string) ($operationContext['actor_type'] ?? ''), 0, 30);
    $contextJson = sr_community_asset_recovery_context_json($operationContext);

    $driver = '';
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable $exception) {
        $driver = '';
    }

    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_community_asset_recovery_failures
                (account_id, asset_module, original_asset_log_id, original_transaction_id, subject_type, subject_id,
                 grant_event_key, reversal_event_key, operation_event_key, attempted_amount, recovered_amount,
                 unrecovered_amount, failure_reason, status, actor_account_id, actor_type, operation_context_json,
                 attempt_count, created_at, updated_at, last_attempted_at, resolved_at)
             VALUES
                (:account_id, :asset_module, :original_asset_log_id, :original_transaction_id, :subject_type, :subject_id,
                 :grant_event_key, :reversal_event_key, :operation_event_key, :attempted_amount, :recovered_amount,
                 :unrecovered_amount, :failure_reason, :status, :actor_account_id, :actor_type, :operation_context_json,
                 1, :created_at, :updated_at, :last_attempted_at, :resolved_at)
             ON CONFLICT(original_asset_log_id, reversal_event_key) DO UPDATE SET
                 recovered_amount = CASE WHEN status = \'open\' THEN MAX(recovered_amount, excluded.recovered_amount) ELSE recovered_amount END,
                 unrecovered_amount = CASE WHEN status = \'open\' THEN excluded.attempted_amount - MAX(recovered_amount, excluded.recovered_amount) ELSE unrecovered_amount END,
                 failure_reason = CASE WHEN status = \'open\' THEN excluded.failure_reason ELSE failure_reason END,
                 actor_account_id = CASE WHEN status = \'open\' THEN excluded.actor_account_id ELSE actor_account_id END,
                 actor_type = CASE WHEN status = \'open\' THEN excluded.actor_type ELSE actor_type END,
                 operation_context_json = CASE WHEN status = \'open\' THEN excluded.operation_context_json ELSE operation_context_json END,
                 attempt_count = CASE WHEN status = \'open\' THEN attempt_count + 1 ELSE attempt_count END,
                 updated_at = CASE WHEN status = \'open\' THEN excluded.updated_at ELSE updated_at END,
                 last_attempted_at = CASE WHEN status = \'open\' THEN excluded.last_attempted_at ELSE last_attempted_at END,
                 resolved_at = CASE WHEN status = \'open\' AND excluded.attempted_amount - MAX(recovered_amount, excluded.recovered_amount) <= 0 THEN excluded.updated_at ELSE resolved_at END,
                 status = CASE WHEN status = \'open\' AND excluded.attempted_amount - MAX(recovered_amount, excluded.recovered_amount) <= 0 THEN \'recovered\' ELSE status END'
        );
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_community_asset_recovery_failures
                (account_id, asset_module, original_asset_log_id, original_transaction_id, subject_type, subject_id,
                 grant_event_key, reversal_event_key, operation_event_key, attempted_amount, recovered_amount,
                 unrecovered_amount, failure_reason, status, actor_account_id, actor_type, operation_context_json,
                 attempt_count, created_at, updated_at, last_attempted_at, resolved_at)
             VALUES
                (:account_id, :asset_module, :original_asset_log_id, :original_transaction_id, :subject_type, :subject_id,
                 :grant_event_key, :reversal_event_key, :operation_event_key, :attempted_amount, :recovered_amount,
                 :unrecovered_amount, :failure_reason, :status, :actor_account_id, :actor_type, :operation_context_json,
                 1, :created_at, :updated_at, :last_attempted_at, :resolved_at)
             ON DUPLICATE KEY UPDATE
                 recovered_amount = IF(status = \'open\', GREATEST(recovered_amount, VALUES(recovered_amount)), recovered_amount),
                 unrecovered_amount = IF(status = \'open\', GREATEST(0, attempted_amount - GREATEST(recovered_amount, VALUES(recovered_amount))), unrecovered_amount),
                 failure_reason = IF(status = \'open\', VALUES(failure_reason), failure_reason),
                 actor_account_id = IF(status = \'open\', VALUES(actor_account_id), actor_account_id),
                 actor_type = IF(status = \'open\', VALUES(actor_type), actor_type),
                 operation_context_json = IF(status = \'open\', VALUES(operation_context_json), operation_context_json),
                 attempt_count = IF(status = \'open\', attempt_count + 1, attempt_count),
                 updated_at = IF(status = \'open\', VALUES(updated_at), updated_at),
                 last_attempted_at = IF(status = \'open\', VALUES(last_attempted_at), last_attempted_at),
                 resolved_at = IF(status = \'open\' AND attempted_amount - GREATEST(recovered_amount, VALUES(recovered_amount)) <= 0, VALUES(updated_at), resolved_at),
                 status = IF(status = \'open\' AND attempted_amount - GREATEST(recovered_amount, VALUES(recovered_amount)) <= 0, \'recovered\', status)'
        );
    }

    $stmt->execute([
        'account_id' => (int) ($originalLog['account_id'] ?? 0),
        'asset_module' => (string) ($originalLog['asset_module'] ?? ''),
        'original_asset_log_id' => (int) ($originalLog['id'] ?? 0),
        'original_transaction_id' => (int) ($originalLog['transaction_id'] ?? 0),
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'grant_event_key' => $grantEventKey,
        'reversal_event_key' => $reversalEventKey,
        'operation_event_key' => $operationEventKey,
        'attempted_amount' => $attemptedAmount,
        'recovered_amount' => $recoveredAmount,
        'unrecovered_amount' => $unrecoveredAmount,
        'failure_reason' => $failureReason,
        'status' => $status,
        'actor_account_id' => $actorAccountId,
        'actor_type' => $actorType,
        'operation_context_json' => $contextJson,
        'created_at' => $now,
        'updated_at' => $now,
        'last_attempted_at' => $now,
        'resolved_at' => $status === 'recovered' ? $now : null,
    ]);

    if (function_exists('sr_asset_recovery_record_failure') && sr_asset_recovery_failures_table_exists($pdo)) {
        sr_asset_recovery_record_failure($pdo, [
            'source_module' => 'community',
            'source_log_id' => (int) ($originalLog['id'] ?? 0),
            'asset_module' => (string) ($originalLog['asset_module'] ?? ''),
            'account_id' => (int) ($originalLog['account_id'] ?? 0),
            'original_transaction_id' => (int) ($originalLog['transaction_id'] ?? 0),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'grant_event_key' => $canonicalGrantEventKey,
            'reversal_event_key' => $canonicalReversalEventKey,
            'attempted_amount' => $attemptedAmount,
            'recovered_amount' => $recoveredAmount,
            'failure_reason' => $failureReason === 'resolved' ? 'recovered' : $failureReason,
            'operation_context' => $operationContext,
        ]);
    }

    $row = sr_community_asset_recovery_failure_by_original($pdo, (int) ($originalLog['id'] ?? 0), $reversalEventKey);
    return is_array($row) ? (int) ($row['id'] ?? 0) : 0;
}

function sr_community_reverse_asset_grant_for_operation(PDO $pdo, int $accountId, string $grantEventKey, string $subjectType, int $subjectId, string $reversalEventKey, string $canonicalReversalEventKey, string $reason, array $operationContext = []): array
{
    $legacyGrantEventKey = sr_community_asset_recovery_legacy_event_key($grantEventKey, $subjectType, 'grant');
    $legacyReversalEventKey = sr_community_asset_recovery_legacy_event_key($reversalEventKey, $subjectType, 'reversal');
    $canonicalReversalEventKey = sr_community_asset_recovery_canonical_event_key($canonicalReversalEventKey, $subjectType, 'reversal');

    $original = sr_community_asset_grant_log_for_reversal($pdo, $accountId, $legacyGrantEventKey, $subjectId);
    if (!is_array($original)) {
        return ['operation_allowed' => true, 'recovery_status' => 'not_needed', 'recovery_failure_id' => 0, 'processed' => false, 'message' => ''];
    }

    $attemptedAmount = max(0, (int) ($original['amount'] ?? 0));
    $existingFailure = sr_community_asset_recovery_failure_by_original($pdo, (int) ($original['id'] ?? 0), $legacyReversalEventKey);
    if (is_array($existingFailure) && in_array((string) ($existingFailure['status'] ?? ''), ['recovered', 'resolved', 'manually_resolved', 'cancelled'], true)) {
        return ['operation_allowed' => true, 'recovery_status' => 'not_needed', 'recovery_failure_id' => (int) ($existingFailure['id'] ?? 0), 'processed' => false, 'message' => ''];
    }

    $alreadyRecoveredAmount = sr_community_asset_completed_reversal_amount($pdo, $original, $canonicalReversalEventKey);
    if ($attemptedAmount <= 0 || $alreadyRecoveredAmount >= $attemptedAmount) {
        if (is_array($existingFailure) && (string) ($existingFailure['status'] ?? '') === 'open') {
            $failureId = sr_community_asset_recovery_upsert($pdo, $original, $subjectType, $subjectId, $legacyGrantEventKey, $legacyReversalEventKey, $attemptedAmount, 'recovered', $operationContext);
            return ['operation_allowed' => true, 'recovery_status' => 'completed', 'recovery_failure_id' => $failureId, 'processed' => false, 'message' => ''];
        }

        return ['operation_allowed' => true, 'recovery_status' => 'not_needed', 'recovery_failure_id' => 0, 'processed' => false, 'message' => ''];
    }

    $isManualRetry = (string) ($operationContext['operation_event_key'] ?? '') === 'manual_retry';
    if (!$isManualRetry && is_array($existingFailure) && (string) ($existingFailure['status'] ?? '') === 'open') {
        $failureId = sr_community_asset_recovery_upsert($pdo, $original, $subjectType, $subjectId, $legacyGrantEventKey, $legacyReversalEventKey, $alreadyRecoveredAmount, 'balance_low', $operationContext);
        return ['operation_allowed' => true, 'recovery_status' => 'unrecovered', 'recovery_failure_id' => $failureId, 'processed' => false, 'message' => ''];
    }

    $remainingAmount = max(0, $attemptedAmount - $alreadyRecoveredAmount);
    $availableAmount = sr_community_asset_balance($pdo, (string) ($original['asset_module'] ?? ''), $accountId);
    $recoverableAmount = min($remainingAmount, max(0, $availableAmount));
    if ($recoverableAmount <= 0) {
        $failureId = sr_community_asset_recovery_upsert($pdo, $original, $subjectType, $subjectId, $legacyGrantEventKey, $legacyReversalEventKey, $alreadyRecoveredAmount, 'balance_low', $operationContext);
        return ['operation_allowed' => true, 'recovery_status' => 'unrecovered', 'recovery_failure_id' => $failureId, 'processed' => false, 'error_key' => 'asset_balance_low', 'message' => ''];
    }

    $savepoint = sr_community_asset_recovery_savepoint_name();
    $pdo->exec('SAVEPOINT ' . $savepoint);
    try {
        $config = sr_community_asset_reversal_config($original);
        $config['amount'] = $recoverableAmount;
        $config['charge_policy'] = 'every_action';
        $result = sr_community_run_asset_event($pdo, $config, $accountId, $canonicalReversalEventKey, $subjectType, $subjectId, 'use', $reason, true, bin2hex(random_bytes(16)));
        if (!empty($result['allowed'])) {
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            $totalRecovered = $alreadyRecoveredAmount + $recoverableAmount;
            if ($totalRecovered >= $attemptedAmount) {
                $failureId = 0;
                if (is_array($existingFailure) && (string) ($existingFailure['status'] ?? '') === 'open') {
                    $failureId = sr_community_asset_recovery_upsert($pdo, $original, $subjectType, $subjectId, $legacyGrantEventKey, $legacyReversalEventKey, $totalRecovered, 'recovered', $operationContext);
                }
                if ($failureId > 0 && function_exists('sr_asset_recovery_record_reversal_link')) {
                    $commonFailure = sr_asset_recovery_failure_by_dedupe_key($pdo, sr_asset_recovery_dedupe_key('community', (int) ($original['id'] ?? 0), sr_community_asset_recovery_canonical_event_key($canonicalReversalEventKey, $subjectType, 'reversal')));
                    $commonFailureId = is_array($commonFailure) ? (int) ($commonFailure['id'] ?? 0) : 0;
                    foreach ((array) ($result['processed_logs'] ?? []) as $processedLog) {
                        sr_asset_recovery_record_reversal_link($pdo, $commonFailureId, (string) ($processedLog['asset_module'] ?? ''), (int) ($processedLog['transaction_id'] ?? 0), (int) ($processedLog['amount'] ?? 0));
                    }
                }
                return ['operation_allowed' => true, 'recovery_status' => 'completed', 'recovery_failure_id' => $failureId, 'processed' => true, 'message' => ''];
            }

            $failureId = sr_community_asset_recovery_upsert($pdo, $original, $subjectType, $subjectId, $legacyGrantEventKey, $legacyReversalEventKey, $totalRecovered, 'balance_low', $operationContext);
            if ($failureId > 0 && function_exists('sr_asset_recovery_record_reversal_link')) {
                $commonFailure = sr_asset_recovery_failure_by_dedupe_key($pdo, sr_asset_recovery_dedupe_key('community', (int) ($original['id'] ?? 0), sr_community_asset_recovery_canonical_event_key($canonicalReversalEventKey, $subjectType, 'reversal')));
                $commonFailureId = is_array($commonFailure) ? (int) ($commonFailure['id'] ?? 0) : 0;
                foreach ((array) ($result['processed_logs'] ?? []) as $processedLog) {
                    sr_asset_recovery_record_reversal_link($pdo, $commonFailureId, (string) ($processedLog['asset_module'] ?? ''), (int) ($processedLog['transaction_id'] ?? 0), (int) ($processedLog['amount'] ?? 0));
                }
            }
            return ['operation_allowed' => true, 'recovery_status' => 'unrecovered', 'recovery_failure_id' => $failureId, 'processed' => true, 'message' => ''];
        }

        $errorKey = (string) ($result['error_key'] ?? '');
        if ($errorKey === 'asset_balance_low') {
            $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            $failureId = sr_community_asset_recovery_upsert($pdo, $original, $subjectType, $subjectId, $legacyGrantEventKey, $legacyReversalEventKey, $alreadyRecoveredAmount, 'balance_low', $operationContext);
            return ['operation_allowed' => true, 'recovery_status' => 'unrecovered', 'recovery_failure_id' => $failureId, 'processed' => false, 'error_key' => 'asset_balance_low', 'message' => ''];
        }

        $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
        $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        return ['operation_allowed' => false, 'recovery_status' => 'failed', 'recovery_failure_id' => 0, 'processed' => false, 'error_key' => $errorKey !== '' ? $errorKey : 'asset_processing_failed', 'message' => (string) ($result['message'] ?? '')];
    } catch (Throwable $exception) {
        if (sr_community_asset_is_retryable_transaction_exception($exception)) {
            throw $exception;
        }
        $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
        $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        return ['operation_allowed' => false, 'recovery_status' => 'failed', 'recovery_failure_id' => 0, 'processed' => false, 'error_key' => 'asset_processing_failed', 'message' => $exception->getMessage()];
    }
}

function sr_community_reverse_asset_grant(PDO $pdo, int $accountId, string $grantEventKey, string $subjectType, int $subjectId, string $reversalEventKey, string $reason): array
{
    $original = sr_community_asset_grant_log_for_reversal($pdo, $accountId, $grantEventKey, $subjectId);
    if (is_array($original)) {
        return sr_community_run_asset_event($pdo, sr_community_asset_reversal_config($original), $accountId, $reversalEventKey, $subjectType, $subjectId, 'use', $reason);
    }

    return ['allowed' => true, 'processed' => false, 'message' => ''];
}

function sr_community_asset_reversal_error_message(array $result, string $balanceLowKey, string $fallbackKey): string
{
    if ((string) ($result['error_key'] ?? '') === 'asset_balance_low') {
        return sr_t($balanceLowKey);
    }

    $message = trim((string) ($result['message'] ?? ''));
    return $message !== '' ? $message : sr_t($fallbackKey);
}

function sr_community_asset_recovery_failure_by_id_for_update(PDO $pdo, int $failureId): ?array
{
    if (!sr_community_asset_recovery_failures_table_exists($pdo)) {
        return null;
    }

    $lockSql = '';
    try {
        $lockSql = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    } catch (Throwable $exception) {
        $lockSql = '';
    }

    $stmt = $pdo->prepare(
        'SELECT f.*
         FROM sr_community_asset_recovery_failures f
         WHERE f.id = :id
         LIMIT 1' . $lockSql
    );
    $stmt->execute(['id' => $failureId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_asset_recovery_failure_where(array $filters, array &$params): string
{
    $conditions = ['1 = 1'];
    if ((string) ($filters['status'] ?? '') !== '') {
        $conditions[] = 'f.status = :status';
        $params['status'] = (string) $filters['status'];
    }
    if ((string) ($filters['asset_module'] ?? '') !== '') {
        $conditions[] = 'f.asset_module = :asset_module';
        $params['asset_module'] = (string) $filters['asset_module'];
    }
    if ((string) ($filters['subject_type'] ?? '') !== '') {
        $conditions[] = 'f.subject_type = :subject_type';
        $params['subject_type'] = (string) $filters['subject_type'];
    }
    if ((int) ($filters['subject_id'] ?? 0) > 0) {
        $conditions[] = 'f.subject_id = :subject_id';
        $params['subject_id'] = (int) $filters['subject_id'];
    }
    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $conditions[] = "(f.account_id = :keyword_id OR ma.email LIKE :keyword_like ESCAPE '\\\\' OR ma.display_name LIKE :keyword_like ESCAPE '\\\\')";
        $params['keyword_id'] = preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0;
        $params['keyword_like'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
    }
    foreach (['created_from' => '>=', 'created_to' => '<='] as $key => $operator) {
        $value = (string) ($filters[$key] ?? '');
        if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value) === 1) {
            $conditions[] = 'f.created_at ' . $operator . ' :' . $key;
            $params[$key] = $key === 'created_to' ? $value . ' 23:59:59' : $value . ' 00:00:00';
        }
    }

    return implode(' AND ', $conditions);
}

function sr_community_asset_recovery_failures(PDO $pdo, array $filters, int $limit, int $offset): array
{
    if (!sr_community_asset_recovery_failures_table_exists($pdo)) {
        return [];
    }

    $params = [];
    $where = sr_community_asset_recovery_failure_where($filters, $params);
    $stmt = $pdo->prepare(
        'SELECT f.*, ma.email AS account_email, ma.display_name AS account_display_name
         FROM sr_community_asset_recovery_failures f
         LEFT JOIN sr_member_accounts ma ON ma.id = f.account_id
         WHERE ' . $where . '
         ORDER BY f.updated_at DESC, f.id DESC
         LIMIT ' . max(1, min(200, $limit)) . ' OFFSET ' . max(0, $offset)
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_community_payment_history_table_exists(PDO $pdo, string $tableName): bool
{
    static $cache = [];
    if (!preg_match('/\Asr_[a-z0-9_]+\z/', $tableName)) {
        return false;
    }
    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    try {
        $pdo->query('SELECT 1 FROM ' . $tableName . ' LIMIT 1');
        $cache[$tableName] = true;
    } catch (Throwable) {
        $cache[$tableName] = false;
    }

    return $cache[$tableName];
}

function sr_community_admin_payment_history_sort_options(): array
{
    return [
        'created_at' => ['label' => '결제 시각', 'columns' => ['p.created_at', 'p.source_id']],
        'target' => ['label' => '대상', 'columns' => ['p.target_title', 'p.source_id']],
        'account_id' => ['label' => '회원', 'columns' => ['p.account_id', 'p.source_id']],
        'payment_type' => ['label' => '결제 유형', 'columns' => ['p.payment_type', 'p.source_id']],
        'settlement_kind' => ['label' => '정산', 'columns' => ['p.settlement_kind', 'p.source_id']],
        'settlement_amount' => ['label' => '금액', 'columns' => ['p.settlement_amount', 'p.source_id']],
    ];
}

function sr_community_admin_payment_history_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}

function sr_community_admin_payment_history_filters_from_request(PDO $pdo): array
{
    $legacySettlementKind = sr_community_asset_settlement_kind('use', 1, 0, '');
    $filters = [
        'kind' => sr_community_admin_filter_values('kind', ['community_post_read', 'community_attachment_download']),
        'payment_type' => sr_community_admin_filter_values('payment_type', ['asset_only', 'coupon_access', 'coupon_partial_discount_asset']),
        'settlement_kind' => sr_community_admin_filter_values('settlement_kind', ['paid', 'paid_settled_zero', $legacySettlementKind]),
        'refund_status' => sr_community_admin_filter_values('refund_status', ['none', 'refunded', 'access_revoked']),
        'coupon_used' => sr_community_admin_filter_values('coupon_used', ['yes', 'no']),
        'board_id' => (int) sr_get_string('board_id', 20),
        'target_id' => (int) sr_get_string('target_id', 20),
        'account_id' => sr_admin_member_account_id_from_identifier($pdo, sr_runtime_config(), sr_get_string('account_id', 80)),
        'date_from' => sr_get_string('date_from', 10),
        'date_to' => sr_get_string('date_to', 10),
        'q' => sr_get_string('q', 120),
    ];
    if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', (string) $filters['date_from']) !== 1) {
        $filters['date_from'] = '';
    }
    if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', (string) $filters['date_to']) !== 1) {
        $filters['date_to'] = '';
    }

    return $filters;
}

function sr_community_admin_payment_history_sources(PDO $pdo): array
{
    $sources = [];
    if (sr_community_payment_history_table_exists($pdo, 'sr_community_post_read_payment_logs')) {
        $sources[] = "SELECT 'community_post_read' AS source_kind,
                             r.id AS source_id,
                             r.board_id AS board_id,
                             r.post_id AS post_id,
                             0 AS attachment_id,
                             r.post_id AS target_id,
                             r.post_title_snapshot AS target_title,
                             COALESCE(NULLIF(b.title, ''), '') AS target_meta,
                             r.account_id AS account_id,
                             r.payment_type AS payment_type,
                             r.settlement_kind AS settlement_kind,
                             r.charge_policy AS charge_policy,
                             r.asset_module AS asset_module,
                             r.payable_amount AS payable_amount,
                             r.settlement_amount AS settlement_amount,
                             r.settlement_currency AS settlement_currency,
                             r.asset_access_log_ids_json AS asset_access_log_ids_json,
                             r.coupon_redemption_id AS coupon_redemption_id,
                             r.coupon_dedupe_key AS coupon_dedupe_key,
                             r.payment_dedupe_key AS payment_dedupe_key,
                             r.refund_status AS refund_status,
                             r.refund_transaction_ids_json AS refund_transaction_ids_json,
                             r.refund_note AS refund_note,
                             r.refunded_by_account_id AS refunded_by_account_id,
                             r.refunded_at AS refunded_at,
                             r.access_revoked_at AS access_revoked_at,
                             r.refund_policy_version AS refund_policy_version,
                             r.created_at AS created_at
                      FROM sr_community_post_read_payment_logs r
                      LEFT JOIN sr_community_boards b ON b.id = r.board_id";
    }
    if (sr_community_attachment_download_logs_table_exists($pdo)) {
        $sources[] = "SELECT 'community_attachment_download' AS source_kind,
                             d.id AS source_id,
                             d.board_id AS board_id,
                             d.post_id AS post_id,
                             d.attachment_id AS attachment_id,
                             d.attachment_id AS target_id,
                             COALESCE(NULLIF(d.attachment_original_name_snapshot, ''), NULLIF(a.original_name, '')) AS target_title,
                             COALESCE(NULLIF(d.post_title_snapshot, ''), NULLIF(p.title, ''), NULLIF(b.title, '')) AS target_meta,
                             d.account_id AS account_id,
                             CASE
                                 WHEN COALESCE(d.coupon_redemption_id, 0) > 0 AND COALESCE(d.amount, 0) <= 0 THEN 'coupon_access'
                                 WHEN COALESCE(d.coupon_redemption_id, 0) > 0 THEN 'coupon_partial_discount_asset'
                                 ELSE 'asset_only'
                             END AS payment_type,
                             CASE
                                 WHEN COALESCE(d.amount, 0) > 0 THEN 'paid'
                                 WHEN COALESCE(d.coupon_redemption_id, 0) > 0 THEN 'paid_settled_zero'
                                 ELSE ''
                             END AS settlement_kind,
                             d.charge_policy AS charge_policy,
                             d.asset_module AS asset_module,
                             d.amount AS payable_amount,
                             d.amount AS settlement_amount,
                             'KRW' AS settlement_currency,
                             d.asset_access_log_ids_json AS asset_access_log_ids_json,
                             d.coupon_redemption_id AS coupon_redemption_id,
                             d.coupon_dedupe_key AS coupon_dedupe_key,
                             '' AS payment_dedupe_key,
                             d.refund_status AS refund_status,
                             d.refund_transaction_ids_json AS refund_transaction_ids_json,
                             d.refund_note AS refund_note,
                             d.refunded_by_account_id AS refunded_by_account_id,
                             d.refunded_at AS refunded_at,
                             d.access_revoked_at AS access_revoked_at,
                             d.refund_policy_version AS refund_policy_version,
                             d.created_at AS created_at
                      FROM sr_community_attachment_download_logs d
                      LEFT JOIN sr_community_attachments a ON a.id = d.attachment_id
                      LEFT JOIN sr_community_posts p ON p.id = d.post_id
                      LEFT JOIN sr_community_boards b ON b.id = d.board_id
                      WHERE d.download_type = 'paid'";
    }

    return $sources;
}

function sr_community_admin_payment_history_where_sql(array $filters): array
{
    $conditions = [];
    $params = [];

    foreach (['kind' => 'source_kind', 'payment_type' => 'payment_type', 'settlement_kind' => 'settlement_kind'] as $filterKey => $column) {
        $values = is_array($filters[$filterKey] ?? null) ? $filters[$filterKey] : [];
        if ($values === []) {
            continue;
        }
        $paramKey = $filterKey . '_0';
        $conditions[] = 'p.' . $column . ' = :' . $paramKey;
        $params[$paramKey] = (string) reset($values);
    }

    $refundStatuses = is_array($filters['refund_status'] ?? null) ? $filters['refund_status'] : [];
    if ($refundStatuses !== []) {
        $refundConditions = [];
        foreach (array_values($refundStatuses) as $index => $refundStatus) {
            if ((string) $refundStatus === 'none') {
                $refundConditions[] = "p.refund_status = ''";
                continue;
            }
            $paramKey = 'refund_status_' . (string) $index;
            $refundConditions[] = 'p.refund_status = :' . $paramKey;
            $params[$paramKey] = (string) $refundStatus;
        }
        if ($refundConditions !== []) {
            $conditions[] = '(' . implode(' OR ', $refundConditions) . ')';
        }
    }

    $couponUsed = is_array($filters['coupon_used'] ?? null) ? (string) reset($filters['coupon_used']) : '';
    if ($couponUsed === 'yes') {
        $conditions[] = 'COALESCE(p.coupon_redemption_id, 0) > 0';
    } elseif ($couponUsed === 'no') {
        $conditions[] = 'COALESCE(p.coupon_redemption_id, 0) = 0';
    }

    foreach (['board_id' => 'board_id', 'account_id' => 'account_id'] as $filterKey => $column) {
        $value = (int) ($filters[$filterKey] ?? 0);
        if ($value > 0) {
            $conditions[] = 'p.' . $column . ' = :' . $filterKey;
            $params[$filterKey] = $value;
        }
    }

    $targetId = (int) ($filters['target_id'] ?? 0);
    if ($targetId > 0) {
        $conditions[] = '(p.post_id = :target_id OR p.attachment_id = :target_id OR p.target_id = :target_id)';
        $params['target_id'] = $targetId;
    }

    $dateFrom = (string) ($filters['date_from'] ?? '');
    if ($dateFrom !== '') {
        $conditions[] = 'p.created_at >= :date_from';
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }
    $dateTo = (string) ($filters['date_to'] ?? '');
    if ($dateTo !== '') {
        $conditions[] = 'p.created_at <= :date_to';
        $params['date_to'] = $dateTo . ' 23:59:59';
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $conditions[] = "(p.target_title LIKE :q ESCAPE '\\\\' OR p.target_meta LIKE :q ESCAPE '\\\\' OR p.coupon_dedupe_key LIKE :q ESCAPE '\\\\' OR p.payment_dedupe_key LIKE :q ESCAPE '\\\\')";
        $params['q'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    }

    return [
        'sql' => $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '',
        'params' => $params,
    ];
}

function sr_community_admin_payment_history_count(PDO $pdo, array $filters): int
{
    $sources = sr_community_admin_payment_history_sources($pdo);
    if ($sources === []) {
        return 0;
    }
    $where = sr_community_admin_payment_history_where_sql($filters);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM (' . implode(' UNION ALL ', $sources) . ') p ' . $where['sql']);
    $stmt->execute($where['params']);

    return (int) $stmt->fetchColumn();
}

function sr_community_admin_payment_history_logs(PDO $pdo, array $filters, int $limit, int $offset, array $sort = []): array
{
    $sources = sr_community_admin_payment_history_sources($pdo);
    if ($sources === []) {
        return [];
    }
    $where = sr_community_admin_payment_history_where_sql($filters);
    $stmt = $pdo->prepare(
        'SELECT p.*, a.email, a.display_name, rb.display_name AS refunded_by_display_name
         FROM (' . implode(' UNION ALL ', $sources) . ') p
         LEFT JOIN sr_member_accounts a ON a.id = p.account_id
         LEFT JOIN sr_member_accounts rb ON rb.id = p.refunded_by_account_id
         ' . $where['sql'] . '
         ' . sr_admin_sort_order_sql(sr_community_admin_payment_history_sort_options(), $sort, sr_community_admin_payment_history_default_sort()) . '
         LIMIT :limit_value OFFSET :offset_value'
    );
    foreach ($where['params'] as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset_value', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return sr_community_admin_payment_history_logs_with_asset_summaries($pdo, $stmt->fetchAll());
}

function sr_community_admin_payment_history_logs_with_asset_summaries(PDO $pdo, array $paymentLogs): array
{
    if ($paymentLogs === []) {
        return $paymentLogs;
    }

    $idsByIndex = [];
    $allIds = [];
    foreach ($paymentLogs as $index => $paymentLog) {
        $decoded = json_decode((string) ($paymentLog['asset_access_log_ids_json'] ?? '[]'), true);
        if (!is_array($decoded)) {
            continue;
        }
        foreach ($decoded as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $idsByIndex[(int) $index][$id] = $id;
                $allIds[$id] = $id;
            }
        }
    }
    if ($allIds === []) {
        return $paymentLogs;
    }

    $placeholders = [];
    $params = [];
    foreach (array_values($allIds) as $index => $id) {
        $key = 'id_' . (string) $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }

    $stmt = $pdo->prepare(
        'SELECT id, account_id, asset_module, transaction_id, reference_type, reference_id, subject_type, subject_id, event_key, amount,
                settlement_amount, settlement_currency, settlement_kind, snapshot_schema_version, rounding_policy_version
         FROM sr_community_asset_logs
         WHERE id IN (' . implode(', ', $placeholders) . ')
         ORDER BY id ASC'
    );
    $stmt->execute($params);

    $assetLogsById = [];
    foreach ($stmt->fetchAll() as $assetLog) {
        $assetLogsById[(int) ($assetLog['id'] ?? 0)] = $assetLog;
    }

    foreach ($idsByIndex as $index => $ids) {
        $summaryLines = [];
        $paymentLog = $paymentLogs[$index];
        foreach ($ids as $id) {
            $assetLog = $assetLogsById[$id] ?? null;
            if (!is_array($assetLog)) {
                continue;
            }
            $sourceKind = (string) ($paymentLog['source_kind'] ?? '');
            if ($sourceKind === 'community_attachment_download') {
                $valid = (int) ($assetLog['account_id'] ?? 0) === (int) ($paymentLog['account_id'] ?? 0)
                    && (string) ($assetLog['reference_type'] ?? '') === 'community.attachment'
                    && (string) ($assetLog['subject_type'] ?? '') === 'community.attachment'
                    && (int) ($assetLog['subject_id'] ?? 0) === (int) ($paymentLog['attachment_id'] ?? 0)
                    && (string) ($assetLog['event_key'] ?? '') === 'attachment_download';
            } else {
                $valid = (int) ($assetLog['account_id'] ?? 0) === (int) ($paymentLog['account_id'] ?? 0)
                    && (string) ($assetLog['subject_type'] ?? '') === 'community.post'
                    && (int) ($assetLog['subject_id'] ?? 0) === (int) ($paymentLog['post_id'] ?? 0)
                    && (string) ($assetLog['event_key'] ?? '') === 'post_read';
            }
            if (!$valid) {
                continue;
            }
            $assetModule = (string) ($assetLog['asset_module'] ?? '');
            $summaryLines[] = trim(implode(' · ', array_filter([
                sr_community_asset_module_labels($assetModule, $pdo) . ' ' . number_format((int) ($assetLog['amount'] ?? 0)),
                '기준 ' . number_format((int) ($assetLog['settlement_amount'] ?? 0)) . ' ' . (string) ($assetLog['settlement_currency'] ?? 'KRW'),
                (string) ($assetLog['settlement_kind'] ?? sr_community_asset_settlement_kind('use', 1, 0, '')),
                'snapshot ' . (string) ($assetLog['snapshot_schema_version'] ?? 'asset_settlement_snapshot_v1'),
                'rounding ' . (string) ($assetLog['rounding_policy_version'] ?? 'asset_settlement_rounding_v1'),
            ], static fn (string $part): bool => $part !== '')));
        }
        $paymentLogs[$index]['asset_log_summary'] = implode("\n", $summaryLines);
    }

    return $paymentLogs;
}
