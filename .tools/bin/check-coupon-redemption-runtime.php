#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/helpers.php';
require_once $root . '/modules/coupon/helpers.php';
require_once $root . '/modules/content/helpers.php';
require_once $root . '/modules/community/helpers.php';

$errors = [];

function sr_coupon_runtime_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

function sr_coupon_runtime_row(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? $row : [];
}

function sr_coupon_runtime_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn();
}

function sr_coupon_runtime_payment_record(PDO $pdo, string $subjectModule, string $subjectType, string $subjectId): array
{
    return sr_coupon_runtime_row(
        $pdo,
        'SELECT *
         FROM sr_payment_records
         WHERE subject_module = :subject_module
           AND subject_type = :subject_type
           AND subject_id = :subject_id
         ORDER BY id DESC
         LIMIT 1',
        [
            'subject_module' => $subjectModule,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ]
    );
}

function sr_coupon_runtime_payment_item_count(PDO $pdo, int $paymentRecordId, string $itemKind): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_payment_record_items
         WHERE payment_record_id = :payment_record_id
           AND item_kind = :item_kind'
    );
    $stmt->execute([
        'payment_record_id' => $paymentRecordId,
        'item_kind' => $itemKind,
    ]);

    return (int) $stmt->fetchColumn();
}

function sr_coupon_runtime_payment_item_reference(PDO $pdo, int $paymentRecordId, string $itemKind): string
{
    $stmt = $pdo->prepare(
        'SELECT reference_id
         FROM sr_payment_record_items
         WHERE payment_record_id = :payment_record_id
           AND item_kind = :item_kind
         ORDER BY id ASC
         LIMIT 1'
    );
    $stmt->execute([
        'payment_record_id' => $paymentRecordId,
        'item_kind' => $itemKind,
    ]);

    return (string) ($stmt->fetchColumn() ?: '');
}

function sr_coupon_runtime_payment_item_status_count(PDO $pdo, int $paymentRecordId, string $itemKind, string $status): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_payment_record_items
         WHERE payment_record_id = :payment_record_id
           AND item_kind = :item_kind
           AND reversal_status = :reversal_status'
    );
    $stmt->execute([
        'payment_record_id' => $paymentRecordId,
        'item_kind' => $itemKind,
        'reversal_status' => $status,
    ]);

    return (int) $stmt->fetchColumn();
}

function sr_coupon_runtime_create_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE sr_modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_key TEXT NOT NULL,
        status TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_site_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        setting_key TEXT NOT NULL,
        setting_value TEXT NOT NULL,
        value_type TEXT NOT NULL DEFAULT 'string'
    )");
    $pdo->exec("CREATE TABLE sr_module_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_id INTEGER NOT NULL,
        setting_key TEXT NOT NULL,
        setting_value TEXT NOT NULL,
        value_type TEXT NOT NULL DEFAULT 'string',
        created_at TEXT,
        updated_at TEXT,
        UNIQUE(module_id, setting_key)
    )");
    $pdo->exec("CREATE TABLE sr_member_accounts (
        id INTEGER PRIMARY KEY,
        email TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'active'
    )");
    $pdo->exec("CREATE TABLE sr_notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NULL,
        audience TEXT NOT NULL DEFAULT 'account',
        title TEXT NOT NULL,
        body_text TEXT,
        body_format TEXT NOT NULL DEFAULT 'plain',
        link_url TEXT NOT NULL DEFAULT '',
        source_module_key TEXT NOT NULL DEFAULT '',
        event_key TEXT NOT NULL DEFAULT '',
        metadata_json TEXT,
        status TEXT NOT NULL DEFAULT 'active',
        read_at TEXT,
        created_by_account_id INTEGER,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_notification_deliveries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        notification_id INTEGER NOT NULL,
        channel TEXT NOT NULL,
        recipient TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'queued',
        provider_message_id TEXT NOT NULL DEFAULT '',
        error_message TEXT NOT NULL DEFAULT '',
        attempted_at TEXT,
        locked_at TEXT,
        locked_by TEXT NOT NULL DEFAULT '',
        attempt_count INTEGER NOT NULL DEFAULT 0,
        next_attempt_at TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_notification_push_endpoints (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        provider_key TEXT NOT NULL,
        recipient_type TEXT NOT NULL DEFAULT 'personal',
        endpoint_ciphertext TEXT NOT NULL,
        endpoint_fingerprint TEXT NOT NULL,
        recipient_label TEXT NOT NULL DEFAULT '',
        recipient_masked TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'active',
        key_version TEXT NOT NULL DEFAULT 'v1',
        verified_at TEXT,
        disabled_at TEXT,
        last_used_at TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_notification_event_templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_key TEXT NOT NULL,
        event_key TEXT NOT NULL,
        title_template TEXT NOT NULL,
        body_template TEXT,
        link_template TEXT NOT NULL DEFAULT '',
        channels_json TEXT,
        status TEXT NOT NULL DEFAULT 'active',
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE(module_key, event_key)
    )");
    $pdo->exec("CREATE TABLE sr_coupon_definitions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        coupon_key TEXT NOT NULL UNIQUE,
        title TEXT NOT NULL,
        description TEXT,
        status TEXT NOT NULL DEFAULT 'active',
        coupon_type TEXT NOT NULL DEFAULT 'access',
        discount_amount INTEGER NOT NULL DEFAULT 0,
        discount_percent INTEGER NOT NULL DEFAULT 0,
        discount_currency_code TEXT NOT NULL DEFAULT '',
        target_type TEXT NOT NULL DEFAULT 'all',
        target_id TEXT NOT NULL DEFAULT '',
        refundable_policy TEXT NOT NULL DEFAULT 'none',
        max_uses_per_issue INTEGER NOT NULL DEFAULT 1,
        validity_policy TEXT NOT NULL DEFAULT 'none',
        validity_days INTEGER,
        valid_from TEXT,
        valid_until TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_coupon_issues (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        coupon_definition_id INTEGER NOT NULL,
        account_id INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'active',
        issued_reason TEXT NOT NULL DEFAULT '',
        issued_by_account_id INTEGER,
        claim_type TEXT NOT NULL DEFAULT 'manual',
        claim_campaign_id INTEGER,
        claim_log_id INTEGER,
        nominal_price_amount INTEGER NOT NULL DEFAULT 0,
        nominal_price_currency_code TEXT NOT NULL DEFAULT '',
        asset_reference_module TEXT NOT NULL DEFAULT '',
        asset_reference_type TEXT NOT NULL DEFAULT '',
        asset_reference_id TEXT NOT NULL DEFAULT '',
        claim_snapshot_json TEXT,
        issued_at TEXT NOT NULL,
        starts_at TEXT,
        expires_at TEXT,
        used_count INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_coupon_redemptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        coupon_issue_id INTEGER NOT NULL,
        coupon_definition_id INTEGER NOT NULL,
        account_id INTEGER NOT NULL,
        target_type TEXT NOT NULL,
        target_id TEXT NOT NULL DEFAULT '',
        reference_module TEXT NOT NULL DEFAULT '',
        reference_type TEXT NOT NULL DEFAULT '',
        reference_id TEXT NOT NULL DEFAULT '',
        amount INTEGER NOT NULL DEFAULT 0,
        currency_code TEXT NOT NULL DEFAULT '',
        asset_unit TEXT NOT NULL DEFAULT '',
        policy_summary TEXT NOT NULL DEFAULT '',
        priced_at TEXT,
        target_snapshot_json TEXT,
        dedupe_key TEXT NOT NULL UNIQUE,
        status TEXT NOT NULL DEFAULT 'redeemed',
        redeemed_at TEXT NOT NULL,
        refunded_at TEXT,
        refunded_by_account_id INTEGER,
        refund_note TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_payment_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        dedupe_key TEXT NOT NULL UNIQUE,
        account_id INTEGER NOT NULL,
        subject_module TEXT NOT NULL,
        subject_type TEXT NOT NULL,
        subject_id TEXT NOT NULL,
        payment_kind TEXT NOT NULL DEFAULT 'purchase',
        status TEXT NOT NULL DEFAULT 'paid',
        payable_amount INTEGER NOT NULL DEFAULT 0,
        settlement_amount INTEGER NOT NULL DEFAULT 0,
        settlement_currency TEXT NOT NULL DEFAULT '',
        description TEXT NOT NULL DEFAULT '',
        snapshot_json TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        cancelled_at TEXT
    )");
    $pdo->exec("CREATE TABLE sr_payment_record_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        payment_record_id INTEGER NOT NULL,
        item_kind TEXT NOT NULL,
        owner_module TEXT NOT NULL,
        reference_type TEXT NOT NULL,
        reference_id TEXT NOT NULL,
        amount INTEGER NOT NULL DEFAULT 0,
        currency_code TEXT NOT NULL DEFAULT '',
        reversible INTEGER NOT NULL DEFAULT 0,
        reversal_status TEXT NOT NULL DEFAULT 'none',
        snapshot_json TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE(payment_record_id, item_kind, owner_module, reference_type, reference_id)
    )");
    $pdo->exec("CREATE TABLE sr_content_access_entitlements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        content_id INTEGER NOT NULL,
        subject_type TEXT NOT NULL,
        subject_id INTEGER NOT NULL,
        access_kind TEXT NOT NULL,
        source_kind TEXT NOT NULL,
        source_asset_module TEXT NOT NULL DEFAULT '',
        source_charge_policy TEXT NOT NULL DEFAULT 'once',
        source_reference TEXT NOT NULL DEFAULT '',
        granted_at TEXT NOT NULL,
        anonymized_at TEXT,
        created_at TEXT NOT NULL,
        UNIQUE(account_id, subject_type, subject_id, access_kind)
    )");
    $pdo->exec("CREATE TABLE sr_content_asset_access_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        content_id INTEGER NOT NULL,
        account_id INTEGER NOT NULL,
        asset_module TEXT NOT NULL,
        transaction_id INTEGER NOT NULL DEFAULT 0,
        reference_type TEXT NOT NULL,
        reference_id TEXT NOT NULL DEFAULT '',
        access_kind TEXT NOT NULL DEFAULT 'view',
        charge_policy TEXT NOT NULL DEFAULT 'once',
        amount INTEGER NOT NULL DEFAULT 0,
        settlement_amount INTEGER NOT NULL DEFAULT 0,
        settlement_currency TEXT NOT NULL DEFAULT 'KRW',
        purchase_power_snapshot_json TEXT NOT NULL DEFAULT '',
        settlement_kind TEXT NOT NULL DEFAULT 'legacy_unknown',
        snapshot_schema_version TEXT NOT NULL DEFAULT 'asset_settlement_snapshot_v1',
        rounding_policy_version TEXT NOT NULL DEFAULT 'asset_settlement_rounding_v1',
        log_status TEXT NOT NULL DEFAULT 'completed',
        group_policy_snapshot_json TEXT NOT NULL DEFAULT '',
        dedupe_key TEXT NOT NULL UNIQUE,
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_content_view_payment_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        content_id INTEGER NOT NULL,
        content_title_snapshot TEXT NOT NULL DEFAULT '',
        content_slug_snapshot TEXT NOT NULL DEFAULT '',
        account_id INTEGER,
        payment_type TEXT NOT NULL DEFAULT 'asset_only',
        settlement_kind TEXT NOT NULL DEFAULT 'paid',
        charge_policy TEXT NOT NULL DEFAULT 'once',
        asset_module TEXT NOT NULL DEFAULT '',
        payable_amount INTEGER NOT NULL DEFAULT 0,
        settlement_amount INTEGER NOT NULL DEFAULT 0,
        settlement_currency TEXT NOT NULL DEFAULT 'KRW',
        asset_access_log_ids_json TEXT,
        coupon_redemption_id INTEGER,
        coupon_dedupe_key TEXT NOT NULL DEFAULT '',
        payment_dedupe_key TEXT NOT NULL UNIQUE,
        refund_status TEXT NOT NULL DEFAULT '',
        refund_transaction_ids_json TEXT,
        refund_note TEXT NOT NULL DEFAULT '',
        refunded_by_account_id INTEGER,
        refunded_at TEXT,
        access_revoked_at TEXT,
        refund_policy_version TEXT NOT NULL DEFAULT 'content_view_refund_v1',
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_content_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        slug TEXT NOT NULL,
        status TEXT NOT NULL,
        asset_access_enabled INTEGER NOT NULL DEFAULT 0,
        asset_module TEXT NOT NULL DEFAULT '',
        asset_access_amount INTEGER NOT NULL DEFAULT 0,
        asset_access_amounts_json TEXT NOT NULL DEFAULT '',
        asset_access_settlement_currency TEXT NOT NULL DEFAULT 'KRW',
        asset_charge_policy TEXT NOT NULL DEFAULT 'once',
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_content_files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        content_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        original_name TEXT NOT NULL,
        status TEXT NOT NULL,
        asset_download_enabled INTEGER NOT NULL DEFAULT 0,
        asset_module TEXT NOT NULL DEFAULT '',
        asset_download_amount INTEGER NOT NULL DEFAULT 0,
        asset_download_settlement_currency TEXT NOT NULL DEFAULT 'KRW',
        asset_download_amounts_json TEXT NOT NULL DEFAULT '',
        asset_download_group_policies_json TEXT NOT NULL DEFAULT '',
        asset_download_policy_set_id INTEGER NOT NULL DEFAULT 0,
        asset_charge_policy TEXT NOT NULL DEFAULT 'once',
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_content_file_download_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        content_id INTEGER NOT NULL,
        content_title_snapshot TEXT NOT NULL DEFAULT '',
        content_slug_snapshot TEXT NOT NULL DEFAULT '',
        file_id INTEGER NOT NULL,
        file_title_snapshot TEXT NOT NULL DEFAULT '',
        file_original_name_snapshot TEXT NOT NULL DEFAULT '',
        account_id INTEGER,
        download_type TEXT NOT NULL DEFAULT 'free',
        charge_policy TEXT NOT NULL DEFAULT 'once',
        asset_module TEXT NOT NULL DEFAULT '',
        amount INTEGER NOT NULL DEFAULT 0,
        asset_access_log_ids_json TEXT,
        coupon_redemption_id INTEGER,
        coupon_dedupe_key TEXT NOT NULL DEFAULT '',
        refund_status TEXT NOT NULL DEFAULT '',
        refund_transaction_ids_json TEXT,
        refund_note TEXT NOT NULL DEFAULT '',
        refunded_by_account_id INTEGER,
        refunded_at TEXT,
        access_revoked_at TEXT,
        refund_policy_version TEXT NOT NULL DEFAULT 'content_file_download_refund_v1',
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_point_balances (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL UNIQUE,
        balance INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_point_transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        amount INTEGER NOT NULL,
        balance_after INTEGER NOT NULL,
        transaction_type TEXT NOT NULL,
        reason TEXT NOT NULL DEFAULT '',
        reference_type TEXT NOT NULL DEFAULT '',
        reference_id TEXT NOT NULL DEFAULT '',
        created_by_account_id INTEGER,
        expires_at TEXT,
        expires_remaining INTEGER NOT NULL DEFAULT 0,
        expired_at TEXT,
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_point_expiration_consumptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        consume_transaction_id INTEGER NOT NULL,
        source_transaction_id INTEGER NOT NULL,
        amount INTEGER NOT NULL,
        source_expires_at TEXT,
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_reward_balances (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL UNIQUE,
        balance INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_reward_transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        amount INTEGER NOT NULL,
        balance_after INTEGER NOT NULL,
        transaction_type TEXT NOT NULL,
        reason TEXT NOT NULL DEFAULT '',
        reference_type TEXT NOT NULL DEFAULT '',
        reference_id TEXT NOT NULL DEFAULT '',
        created_by_account_id INTEGER,
        expires_at TEXT,
        expires_remaining INTEGER NOT NULL DEFAULT 0,
        expired_at TEXT,
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_reward_expiration_consumptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        consume_transaction_id INTEGER NOT NULL,
        source_transaction_id INTEGER NOT NULL,
        amount INTEGER NOT NULL,
        source_expires_at TEXT,
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_community_access_entitlements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        subject_type TEXT NOT NULL,
        subject_id INTEGER NOT NULL,
        event_key TEXT NOT NULL,
        source_kind TEXT NOT NULL,
        source_asset_module TEXT NOT NULL DEFAULT '',
        source_charge_policy TEXT NOT NULL DEFAULT 'once',
        source_reference TEXT NOT NULL DEFAULT '',
        granted_at TEXT NOT NULL,
        anonymized_at TEXT,
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_community_board_groups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_key TEXT NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        status TEXT NOT NULL DEFAULT 'enabled',
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_community_boards (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        board_group_id INTEGER,
        board_key TEXT NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        status TEXT NOT NULL DEFAULT 'enabled',
        read_policy TEXT NOT NULL DEFAULT 'public',
        write_policy TEXT NOT NULL DEFAULT 'member',
        comment_policy TEXT NOT NULL DEFAULT 'member',
        image_uploads_enabled INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_community_board_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        board_id INTEGER NOT NULL,
        setting_key TEXT NOT NULL,
        setting_value TEXT,
        value_type TEXT NOT NULL DEFAULT 'string',
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_community_posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        board_id INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'published',
        title TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_community_attachments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NOT NULL,
        uploader_account_id INTEGER,
        original_name TEXT NOT NULL,
        stored_name TEXT NOT NULL DEFAULT '',
        storage_path TEXT NOT NULL DEFAULT '',
        storage_driver TEXT NOT NULL DEFAULT 'local',
        storage_key TEXT NOT NULL DEFAULT '',
        mime_type TEXT NOT NULL DEFAULT 'application/octet-stream',
        size_bytes INTEGER NOT NULL DEFAULT 0,
        checksum_sha256 TEXT NOT NULL DEFAULT '',
        width INTEGER NOT NULL DEFAULT 0,
        height INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT 'active',
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_community_asset_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        asset_module TEXT NOT NULL,
        transaction_id INTEGER NOT NULL DEFAULT 0,
        reference_type TEXT NOT NULL,
        reference_id TEXT NOT NULL,
        subject_type TEXT NOT NULL,
        subject_id INTEGER NOT NULL,
        event_key TEXT NOT NULL,
        direction TEXT NOT NULL,
        charge_policy TEXT NOT NULL DEFAULT 'once',
        amount INTEGER NOT NULL,
        settlement_amount INTEGER NOT NULL DEFAULT 0,
        settlement_currency TEXT NOT NULL DEFAULT 'KRW',
        purchase_power_snapshot_json TEXT NOT NULL DEFAULT '',
        settlement_kind TEXT NOT NULL DEFAULT 'legacy_unknown',
        snapshot_schema_version TEXT NOT NULL DEFAULT 'asset_settlement_snapshot_v1',
        rounding_policy_version TEXT NOT NULL DEFAULT 'asset_settlement_rounding_v1',
        log_status TEXT NOT NULL DEFAULT 'completed',
        group_policy_snapshot_json TEXT NOT NULL DEFAULT '',
        dedupe_key TEXT NOT NULL UNIQUE,
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_community_post_read_payment_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        board_id INTEGER NOT NULL DEFAULT 0,
        post_id INTEGER NOT NULL,
        post_title_snapshot TEXT NOT NULL DEFAULT '',
        account_id INTEGER,
        payment_type TEXT NOT NULL DEFAULT 'asset_only',
        settlement_kind TEXT NOT NULL DEFAULT 'paid',
        charge_policy TEXT NOT NULL DEFAULT 'once',
        asset_module TEXT NOT NULL DEFAULT '',
        payable_amount INTEGER NOT NULL DEFAULT 0,
        settlement_amount INTEGER NOT NULL DEFAULT 0,
        settlement_currency TEXT NOT NULL DEFAULT 'KRW',
        asset_access_log_ids_json TEXT,
        coupon_redemption_id INTEGER,
        coupon_dedupe_key TEXT NOT NULL DEFAULT '',
        payment_dedupe_key TEXT NOT NULL UNIQUE,
        refund_status TEXT NOT NULL DEFAULT '',
        refund_transaction_ids_json TEXT,
        refund_note TEXT NOT NULL DEFAULT '',
        refunded_by_account_id INTEGER,
        refunded_at TEXT,
        access_revoked_at TEXT,
        refund_policy_version TEXT NOT NULL DEFAULT 'community_post_read_refund_v1',
        created_at TEXT NOT NULL
    )");
    $pdo->exec("INSERT INTO sr_modules (module_key, status) VALUES ('coupon', 'enabled'), ('content', 'enabled'), ('community', 'enabled'), ('point', 'enabled'), ('reward', 'enabled'), ('notification', 'enabled'), ('payment_ledger', 'enabled')");
    $pdo->exec("INSERT INTO sr_site_settings (setting_key, setting_value, value_type) VALUES ('site.default_currency', 'KRW', 'string')");
    $pdo->exec("INSERT INTO sr_member_accounts (id, email, status) VALUES (7, 'member7@example.test', 'active'), (8, 'member8@example.test', 'active')");
    $pdo->exec("INSERT INTO sr_notification_event_templates
        (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
        VALUES
        ('coupon', 'issue.created', '쿠폰·이용권이 발급되었습니다.', '쿠폰·이용권: {coupon_title}', '/account/coupons', '[\"site\"]', 'active', '2026-06-26 00:00:00', '2026-06-26 00:00:00'),
        ('coupon', 'issue.refunded', '쿠폰·이용권 발급이 환불되었습니다.', '쿠폰·이용권: {coupon_title}', '/account/coupons', '[\"site\"]', 'active', '2026-06-26 00:00:00', '2026-06-26 00:00:00'),
        ('coupon', 'issue.definition_disabled', '쿠폰·이용권 사용이 중지되었습니다.', '쿠폰·이용권: {coupon_title}', '/account/coupons', '[\"site\"]', 'active', '2026-06-26 00:00:00', '2026-06-26 00:00:00')");
}

function sr_coupon_runtime_create_legacy_definition_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE sr_modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_key TEXT NOT NULL,
        status TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_coupon_definitions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        coupon_key TEXT NOT NULL UNIQUE,
        title TEXT NOT NULL,
        description TEXT,
        status TEXT NOT NULL DEFAULT 'active',
        coupon_type TEXT NOT NULL DEFAULT 'access',
        target_type TEXT NOT NULL DEFAULT 'all',
        target_id TEXT NOT NULL DEFAULT '',
        refundable_policy TEXT NOT NULL DEFAULT 'none',
        max_uses_per_issue INTEGER NOT NULL DEFAULT 1,
        validity_policy TEXT NOT NULL DEFAULT 'none',
        validity_days INTEGER,
        valid_from TEXT,
        valid_until TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("INSERT INTO sr_modules (module_key, status) VALUES ('coupon', 'enabled')");
}

function sr_coupon_runtime_issue(PDO $pdo, string $couponKey, string $targetType, string $targetId, int $accountId, int $maxUses = 1): int
{
    $now = sr_now();
    $pdo->prepare(
        "INSERT INTO sr_coupon_definitions
            (coupon_key, title, description, status, coupon_type, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES
            (:coupon_key, :title, '', 'active', 'access', :target_type, :target_id, 'refundable', :max_uses, NULL, NULL, :created_at, :updated_at)"
    )->execute([
        'coupon_key' => $couponKey,
        'title' => $couponKey,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'max_uses' => $maxUses,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $definitionId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO sr_coupon_issues
            (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, issued_at, expires_at, used_count, created_at, updated_at)
         VALUES
            (:definition_id, :account_id, 'active', 'fixture', NULL, :issued_at, NULL, 0, :created_at, :updated_at)"
    )->execute([
        'definition_id' => $definitionId,
        'account_id' => $accountId,
        'issued_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_coupon_runtime_assert_issue_unused(PDO $pdo, int $issueId, string $label): void
{
    $row = sr_coupon_runtime_row($pdo, 'SELECT status, used_count FROM sr_coupon_issues WHERE id = :id', ['id' => $issueId]);
    sr_coupon_runtime_assert((string) ($row['status'] ?? '') === 'active', $label . ' should leave coupon issue active after rollback.');
    sr_coupon_runtime_assert((int) ($row['used_count'] ?? -1) === 0, $label . ' should leave coupon issue used_count at zero after rollback.');
}

function sr_coupon_runtime_partial_failure_fixture(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_coupon_runtime_create_schema($pdo);
    $previousErrorLog = ini_get('error_log');
    $temporaryErrorLog = tempnam(sys_get_temp_dir(), 'sr_coupon_runtime_error_log_');
    if (is_string($temporaryErrorLog) && $temporaryErrorLog !== '') {
        ini_set('error_log', $temporaryErrorLog);
    }

    $now = sr_now();
    $pdo->prepare(
        "INSERT INTO sr_point_balances (account_id, balance, created_at, updated_at)
         VALUES (7, 1000, :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);

    $contentIssueId = sr_coupon_runtime_issue($pdo, 'content_rollback', 'content', '8801', 7);
    $pdo->exec("CREATE TRIGGER sr_fixture_content_entitlement_failure
        BEFORE INSERT ON sr_content_access_entitlements
        BEGIN
            SELECT RAISE(ABORT, 'fixture content entitlement failure');
        END");

    $_SESSION['sr_content_asset_confirmation_requests'] = [];
    $_SESSION['sr_content_asset_confirmations'] = [];
    $paidPage = [
        'id' => 8801,
        'asset_access_enabled' => 1,
        'asset_module' => 'point',
        'asset_access_amount' => 100,
        'asset_access_amounts_json' => '{"point":100}',
        'asset_access_group_policies_json' => '',
        'asset_access_policy_set_id' => 0,
        'asset_access_settlement_currency' => 'KRW',
        'asset_charge_policy' => 'once',
    ];
    $confirmation = sr_content_charge_view_access($pdo, $paidPage, 7, false);
    $token = (string) ($confirmation['confirmation_request_token'] ?? '');
    $contentResult = sr_content_charge_view_access($pdo, $paidPage, 7, true, $token, $contentIssueId);
    sr_coupon_runtime_assert(empty($contentResult['allowed']), 'content coupon entitlement failure should not allow access.');
    sr_coupon_runtime_assert((string) ($contentResult['message'] ?? '') !== '', 'content coupon entitlement failure should return a user-facing failure message.');
    sr_coupon_runtime_assert_issue_unused($pdo, $contentIssueId, 'content coupon entitlement failure');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_coupon_redemptions WHERE dedupe_key = 'content.view:coupon:7:8801'")->fetchColumn() === 0, 'content coupon entitlement failure should roll back redemption row.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_content_access_entitlements')->fetchColumn() === 0, 'content coupon entitlement failure should leave no content entitlement.');
    $pdo->exec('DROP TRIGGER sr_fixture_content_entitlement_failure');

    $communityIssueId = sr_coupon_runtime_issue($pdo, 'community_rollback', 'community_post', '9901', 7);
    $pdo->exec("CREATE TRIGGER sr_fixture_community_entitlement_failure
        BEFORE INSERT ON sr_community_access_entitlements
        BEGIN
            SELECT RAISE(ABORT, 'fixture community entitlement failure');
        END");
    $communityResult = sr_community_try_paid_read_coupon_access($pdo, 7, ['id' => 9901, 'board_id' => 9902], [
        'asset_module' => 'point',
        'amount' => 100,
        'amounts' => ['point' => 100],
        'group_policies_json' => '',
        'policy_set_id' => 0,
        'charge_policy' => 'once',
    ], 'community.post:coupon:7:9901');
    sr_coupon_runtime_assert(empty($communityResult['allowed']), 'community coupon entitlement failure should not allow access.');
    sr_coupon_runtime_assert_issue_unused($pdo, $communityIssueId, 'community coupon entitlement failure');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_coupon_redemptions WHERE dedupe_key = 'community.post:coupon:7:9901'")->fetchColumn() === 0, 'community coupon entitlement failure should roll back redemption row.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_community_access_entitlements')->fetchColumn() === 0, 'community coupon entitlement failure should leave no community entitlement.');
    if (is_string($previousErrorLog)) {
        ini_set('error_log', $previousErrorLog);
    }
    if (is_string($temporaryErrorLog) && is_file($temporaryErrorLog)) {
        unlink($temporaryErrorLog);
    }
}

function sr_coupon_runtime_notification_settings_fixture(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_coupon_runtime_create_schema($pdo);

    $now = sr_now();
    $definitionId = sr_coupon_create_definition($pdo, [
        'coupon_key' => 'notification_settings',
        'title' => 'Notification settings',
        'coupon_type' => 'access',
        'target_type' => 'content',
        'target_id' => '4401',
        'refundable_policy' => 'none',
        'max_uses_per_issue' => '1',
    ]);
    $couponModuleId = (int) $pdo->query("SELECT id FROM sr_modules WHERE module_key = 'coupon'")->fetchColumn();
    $couponNotificationCases = sr_coupon_default_notification_case_settings();
    $couponNotificationCases['issue_created']['enabled'] = false;
    $couponNotificationCasesJson = json_encode($couponNotificationCases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $pdo->prepare(
        "INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES (:module_id, 'notification_cases', :notification_cases, 'json', :created_at, :updated_at)"
    )->execute([
        'module_id' => $couponModuleId,
        'notification_cases' => is_string($couponNotificationCasesJson) ? $couponNotificationCasesJson : '{}',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    sr_clear_module_settings_cache('coupon');

    sr_coupon_issue_to_account($pdo, $definitionId, 7, 'disabled notification fixture');
    sr_coupon_runtime_assert(
        (int) $pdo->query("SELECT COUNT(*) FROM sr_notifications WHERE account_id = 7 AND source_module_key = 'coupon' AND event_key = 'issue.created'")->fetchColumn() === 0,
        'Coupon issue-created notification setting must suppress the actual issue notification event.'
    );

    $couponNotificationCases['issue_created']['enabled'] = true;
    $couponNotificationCases['issue_created']['channels'] = ['site', 'email'];
    $couponNotificationCasesJson = json_encode($couponNotificationCases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $pdo->prepare(
        "UPDATE sr_module_settings
         SET setting_value = :notification_cases,
             updated_at = :updated_at
         WHERE module_id = :module_id
           AND setting_key = 'notification_cases'"
    )->execute([
        'module_id' => $couponModuleId,
        'notification_cases' => is_string($couponNotificationCasesJson) ? $couponNotificationCasesJson : '{}',
        'updated_at' => $now,
    ]);
    sr_clear_module_settings_cache('coupon');

    sr_coupon_issue_to_account($pdo, $definitionId, 8, 'enabled notification fixture');
    sr_coupon_runtime_assert(
        (int) $pdo->query("SELECT COUNT(*) FROM sr_notifications WHERE account_id = 8 AND source_module_key = 'coupon' AND event_key = 'issue.created'")->fetchColumn() === 1,
        'Coupon issue-created notification setting must enable the actual issue notification event.'
    );
    sr_coupon_runtime_assert(
        (int) $pdo->query("SELECT COUNT(*) FROM sr_notification_deliveries WHERE channel = 'email' AND recipient = 'member8@example.test'")->fetchColumn() === 1,
        'Coupon issue-created notification setting must apply configured email delivery channel.'
    );
}

function sr_coupon_runtime_check_model_decision(): void
{
    $installSql = (string) file_get_contents('modules/coupon/install.sql');
    $adminView = (string) file_get_contents('modules/coupon/views/admin-coupons.php');
    $coreDecisions = (string) file_get_contents('docs/core-decisions.md');
    $moduleGuide = (string) file_get_contents('docs/module-guide.md');

    sr_coupon_runtime_assert(str_contains($installSql, "coupon_type VARCHAR(40) NOT NULL DEFAULT 'access'"), 'coupon schema must keep access as the default coupon use model.');
    sr_coupon_runtime_assert(str_contains($installSql, 'discount_amount BIGINT UNSIGNED NOT NULL DEFAULT 0'), 'coupon schema must store fixed discount amount.');
    sr_coupon_runtime_assert(str_contains($installSql, 'discount_percent TINYINT UNSIGNED NOT NULL DEFAULT 0'), 'coupon schema must store percent discount value.');
    sr_coupon_runtime_assert(!str_contains($installSql, 'sr_coupon_balance_ledger'), 'coupon v1 must not introduce a partial-use balance ledger.');
    sr_coupon_runtime_assert(str_contains($adminView, 'data-coupon-type-select'), 'coupon admin form must expose coupon benefit type selection.');
    sr_coupon_runtime_assert(str_contains($coreDecisions, '`access`, `fixed_discount`, `percent_discount`'), 'core decisions must pin the supported coupon benefit models.');
    sr_coupon_runtime_assert(str_contains($moduleGuide, '`fixed_discount`와 `percent_discount`는 콘텐츠 유료 열람'), 'module guide must describe discount coupon mixed payment runtime.');
}

function sr_coupon_runtime_discount_schema_detection_fixture(): void
{
    $currentSchemaPdo = new PDO('sqlite::memory:');
    $currentSchemaPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $currentSchemaPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_coupon_runtime_create_schema($currentSchemaPdo);
    sr_coupon_runtime_assert(sr_coupon_definition_discount_columns_available($currentSchemaPdo), 'discount column detection should return true on the current coupon schema.');

    $legacySchemaPdo = new PDO('sqlite::memory:');
    $legacySchemaPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $legacySchemaPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_coupon_runtime_create_legacy_definition_schema($legacySchemaPdo);
    sr_coupon_runtime_assert(!sr_coupon_definition_discount_columns_available($legacySchemaPdo), 'discount column detection should not reuse a previous PDO result for a legacy schema.');

    $accessDefinitionId = sr_coupon_create_definition($legacySchemaPdo, [
        'coupon_key' => 'legacy_access',
        'title' => 'Legacy access',
        'coupon_type' => 'access',
        'target_type' => 'all',
        'refundable_policy' => 'none',
        'max_uses_per_issue' => '1',
    ]);
    sr_coupon_runtime_assert($accessDefinitionId > 0, 'legacy schema should still allow access coupon definitions without discount columns.');

    try {
        sr_coupon_create_definition($legacySchemaPdo, [
            'coupon_key' => 'legacy_fixed_discount',
            'title' => 'Legacy fixed discount',
            'coupon_type' => 'fixed_discount',
            'discount_amount' => '5000',
            'target_type' => 'all',
            'refundable_policy' => 'none',
            'max_uses_per_issue' => '1',
        ]);
        sr_coupon_runtime_assert(false, 'legacy schema should reject fixed discount definitions until the discount update is applied.');
    } catch (InvalidArgumentException $exception) {
        sr_coupon_runtime_assert(str_contains($exception->getMessage(), '업데이트'), 'legacy schema fixed discount rejection should tell the admin to apply the update.');
    }
}

function sr_coupon_runtime_fixture(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_coupon_runtime_create_schema($pdo);

    $now = sr_now();
    try {
        sr_coupon_create_definition($pdo, [
            'coupon_key' => 'stored_value_attempt',
            'title' => 'Stored value attempt',
            'coupon_type' => 'stored_value',
            'target_type' => 'all',
            'refundable_policy' => 'none',
            'max_uses_per_issue' => '1',
        ]);
        sr_coupon_runtime_assert(false, 'coupon definition save should reject unimplemented stored_value coupon model.');
    } catch (InvalidArgumentException $exception) {
        sr_coupon_runtime_assert(str_contains($exception->getMessage(), '혜택 유형'), 'unimplemented coupon model failure should be user-facing.');
    }
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_coupon_definitions WHERE coupon_key = 'stored_value_attempt'")->fetchColumn() === 0, 'rejected coupon model should not create a coupon definition.');
    try {
        sr_coupon_create_definition($pdo, [
            'coupon_key' => 'invalid_status_attempt',
            'title' => 'Invalid status attempt',
            'status' => 'enabled',
            'coupon_type' => 'access',
            'target_type' => 'all',
            'refundable_policy' => 'none',
            'max_uses_per_issue' => '1',
        ]);
        sr_coupon_runtime_assert(false, 'coupon definition save should reject invalid statuses instead of defaulting them.');
    } catch (InvalidArgumentException $exception) {
        sr_coupon_runtime_assert(str_contains($exception->getMessage(), '상태'), 'invalid coupon definition status failure should be user-facing.');
    }
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_coupon_definitions WHERE coupon_key = 'invalid_status_attempt'")->fetchColumn() === 0, 'rejected coupon status should not create a coupon definition.');
    try {
        sr_coupon_create_definition($pdo, [
            'coupon_key' => 'invalid_refund_policy_attempt',
            'title' => 'Invalid refund policy attempt',
            'coupon_type' => 'access',
            'target_type' => 'all',
            'refundable_policy' => 'refund_yes',
            'max_uses_per_issue' => '1',
        ]);
        sr_coupon_runtime_assert(false, 'coupon definition save should reject invalid refundable policies instead of defaulting them.');
    } catch (InvalidArgumentException $exception) {
        sr_coupon_runtime_assert(str_contains($exception->getMessage(), '환급 정책'), 'invalid coupon refundable policy failure should be user-facing.');
    }
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_coupon_definitions WHERE coupon_key = 'invalid_refund_policy_attempt'")->fetchColumn() === 0, 'rejected coupon refundable policy should not create a coupon definition.');

    $fixedDiscountId = sr_coupon_create_definition($pdo, [
        'coupon_key' => 'fixed_discount_attempt',
        'title' => 'Fixed discount attempt',
        'coupon_type' => 'fixed_discount',
        'discount_amount' => '5000',
        'target_type' => 'all',
        'refundable_policy' => 'none',
        'max_uses_per_issue' => '1',
    ]);
    $fixedDiscount = sr_coupon_runtime_row($pdo, 'SELECT coupon_type, discount_amount, discount_currency_code FROM sr_coupon_definitions WHERE id = :id', ['id' => $fixedDiscountId]);
    sr_coupon_runtime_assert((string) ($fixedDiscount['coupon_type'] ?? '') === 'fixed_discount', 'fixed discount coupon definition should be stored.');
    sr_coupon_runtime_assert((int) ($fixedDiscount['discount_amount'] ?? 0) === 5000, 'fixed discount coupon amount should be stored.');
    sr_coupon_runtime_assert((string) ($fixedDiscount['discount_currency_code'] ?? '') === 'KRW', 'fixed discount coupon currency should default to KRW when the admin form omits it.');
    sr_coupon_runtime_assert(sr_coupon_definition_benefit_label($fixedDiscount) === '5,000원 할인', 'fixed discount coupon benefit label should use the same won unit as the admin form.');
    $invalidCurrencyPricing = sr_coupon_normalize_target_pricing([
        'ok' => true,
        'price_amount' => 5000,
        'currency_code' => 'KRWX',
    ], 'content', '7701');
    sr_coupon_runtime_assert(empty($invalidCurrencyPricing['ok']) && (string) ($invalidCurrencyPricing['failure_code'] ?? '') === 'pricing_unit_invalid', 'coupon target pricing should reject overlong currency codes instead of truncating them.');
    $invalidAmountPricing = sr_coupon_normalize_target_pricing([
        'ok' => true,
        'price_amount' => '5000abc',
        'currency_code' => 'KRW',
    ], 'content', '7701');
    sr_coupon_runtime_assert(empty($invalidAmountPricing['ok']) && (string) ($invalidAmountPricing['failure_code'] ?? '') === 'pricing_amount_invalid', 'coupon target pricing should reject non-integer price amounts instead of casting them.');
    $invalidCurrencyDiscount = sr_coupon_discount_application([
        'coupon_type' => 'fixed_discount',
        'discount_amount' => 1000,
        'discount_currency_code' => 'KRW',
    ], [
        'ok' => true,
        'price_amount' => 5000,
        'currency_code' => 'KRWX',
    ]);
    sr_coupon_runtime_assert(empty($invalidCurrencyDiscount['ok']), 'fixed discount application should reject overlong price currency codes instead of truncating them.');
    $invalidAmountDiscount = sr_coupon_discount_application([
        'coupon_type' => 'fixed_discount',
        'discount_amount' => 1000,
        'discount_currency_code' => 'KRW',
    ], [
        'ok' => true,
        'price_amount' => '5000abc',
        'currency_code' => 'KRW',
    ]);
    sr_coupon_runtime_assert(empty($invalidAmountDiscount['ok']), 'fixed discount application should reject non-integer price amounts instead of casting them.');

    $percentDiscountId = sr_coupon_create_definition($pdo, [
        'coupon_key' => 'percent_discount_attempt',
        'title' => 'Percent discount attempt',
        'coupon_type' => 'percent_discount',
        'discount_percent' => '15',
        'target_type' => 'all',
        'refundable_policy' => 'none',
        'max_uses_per_issue' => '1',
    ]);
    $percentDiscount = sr_coupon_runtime_row($pdo, 'SELECT coupon_type, discount_percent FROM sr_coupon_definitions WHERE id = :id', ['id' => $percentDiscountId]);
    sr_coupon_runtime_assert((string) ($percentDiscount['coupon_type'] ?? '') === 'percent_discount', 'percent discount coupon definition should be stored.');
    sr_coupon_runtime_assert((int) ($percentDiscount['discount_percent'] ?? 0) === 15, 'percent discount coupon value should be stored.');

    try {
        sr_coupon_create_definition($pdo, [
            'coupon_key' => 'refundable_discount_attempt',
            'title' => 'Refundable discount attempt',
            'coupon_type' => 'fixed_discount',
            'discount_amount' => '1000',
            'target_type' => 'content',
            'target_id' => '7701',
            'refundable_policy' => 'refundable',
            'max_uses_per_issue' => '1',
        ]);
        sr_coupon_runtime_assert(false, 'discount coupon definitions should reject refundable policy until mixed asset cancellation is contracted.');
    } catch (InvalidArgumentException $exception) {
        sr_coupon_runtime_assert(str_contains($exception->getMessage(), '복합 자산 결제 취소 계약'), 'refundable discount rejection should explain the missing mixed asset cancellation contract.');
    }
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_coupon_definitions WHERE coupon_key = 'refundable_discount_attempt'")->fetchColumn() === 0, 'rejected refundable discount coupon should not create a coupon definition.');

    $futureStart = (new DateTimeImmutable($now))->modify('+1 day')->format('Y-m-d H:i:s');
    $futureEnd = (new DateTimeImmutable($now))->modify('+2 days')->format('Y-m-d H:i:s');
    $futureDefinitionId = sr_coupon_create_definition($pdo, [
        'coupon_key' => 'future_start_access',
        'title' => 'Future start access',
        'coupon_type' => 'access',
        'target_type' => 'all',
        'refundable_policy' => 'none',
        'max_uses_per_issue' => '1',
        'validity_policy' => 'fixed_range',
        'valid_from' => $futureStart,
        'valid_until' => $futureEnd,
    ]);
    $futureIssueId = sr_coupon_issue_to_account($pdo, $futureDefinitionId, 7, 'future-start-test', null, null);
    $futureIssue = sr_coupon_runtime_row($pdo, 'SELECT starts_at, expires_at, used_count FROM sr_coupon_issues WHERE id = :id', ['id' => $futureIssueId]);
    sr_coupon_runtime_assert((string) ($futureIssue['starts_at'] ?? '') === $futureStart, 'fixed range definition must copy valid_from into issue starts_at.');
    sr_coupon_runtime_assert((string) ($futureIssue['expires_at'] ?? '') === $futureEnd, 'fixed range definition must copy valid_until into issue expires_at.');
    $holdingIssueIds = array_map('intval', array_column(sr_coupon_active_account_issues($pdo, 7), 'id'));
    $usableIssueIds = array_map('intval', array_column(sr_coupon_active_account_target_issues($pdo, 7, 'content', '7701'), 'id'));
    sr_coupon_runtime_assert(in_array($futureIssueId, $holdingIssueIds, true), 'future-start coupons must remain visible in the active holding list.');
    sr_coupon_runtime_assert(!in_array($futureIssueId, $usableIssueIds, true), 'future-start coupons must not be returned as usable target candidates.');
    sr_coupon_runtime_assert(sr_coupon_active_account_issue_count($pdo, 7) === 1, 'active coupon count must include future-start holdings.');
    sr_coupon_runtime_assert(sr_coupon_usable_account_issue_count($pdo, 7) === 0, 'usable coupon count must exclude future-start holdings.');
    $futureRedeem = sr_coupon_redeem_for_target($pdo, 7, 'content', '7701', [
        'dedupe_key' => 'future-start-redeem',
        'coupon_issue_id' => $futureIssueId,
    ]);
    sr_coupon_runtime_assert(empty($futureRedeem['allowed']) && empty($futureRedeem['processed']), 'redemption SQL must reject future-start coupon issues.');
    $futureAfterRedeem = sr_coupon_runtime_row($pdo, 'SELECT used_count FROM sr_coupon_issues WHERE id = :id', ['id' => $futureIssueId]);
    sr_coupon_runtime_assert((int) ($futureAfterRedeem['used_count'] ?? -1) === 0, 'future-start redemption attempts must not consume the issue.');
    try {
        sr_coupon_issue_to_account($pdo, $futureDefinitionId, 8, 'expired-override-test', null, $now);
        sr_coupon_runtime_assert(false, 'expired issue override should reject before creating a coupon issue.');
    } catch (InvalidArgumentException $exception) {
        sr_coupon_runtime_assert(str_contains($exception->getMessage(), '만료'), 'expired issue override rejection should explain the dead issue.');
    }

    $pdo->prepare(
        "INSERT INTO sr_coupon_definitions
            (coupon_key, title, description, status, coupon_type, discount_amount, discount_percent, discount_currency_code, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES
            ('legacy_refundable_discount', 'Legacy refundable discount', '', 'active', 'fixed_discount', 1000, 0, 'KRW', 'content', '7701', 'refundable', 1, NULL, NULL, :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $legacyRefundableDiscountDefinitionId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO sr_coupon_issues
            (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, issued_at, expires_at, used_count, created_at, updated_at)
         VALUES
            (:definition_id, 7, 'used', 'legacy fixture', NULL, :issued_at, NULL, 1, :created_at, :updated_at)"
    )->execute([
        'definition_id' => $legacyRefundableDiscountDefinitionId,
        'issued_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $legacyRefundableDiscountIssueId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO sr_coupon_redemptions
            (coupon_issue_id, coupon_definition_id, account_id, target_type, target_id, reference_module, reference_type, reference_id, dedupe_key, status, redeemed_at, created_at)
         VALUES
            (:issue_id, :definition_id, 7, 'content', '7701', 'content', 'content.view', '7701', 'legacy-refundable-discount-dedupe', 'redeemed', :redeemed_at, :created_at)"
    )->execute([
        'issue_id' => $legacyRefundableDiscountIssueId,
        'definition_id' => $legacyRefundableDiscountDefinitionId,
        'redeemed_at' => $now,
        'created_at' => $now,
    ]);
    $legacyRefundableDiscountRedemptionId = (int) $pdo->lastInsertId();
    try {
        sr_coupon_refund_redemption($pdo, $legacyRefundableDiscountRedemptionId, 1, 'legacy refundable discount refund');
        sr_coupon_runtime_assert(false, 'legacy refundable discount redemption refund should be rejected before state changes.');
    } catch (InvalidArgumentException $exception) {
        sr_coupon_runtime_assert(str_contains($exception->getMessage(), '접근권 쿠폰'), 'legacy refundable discount refund rejection should explain that only access coupons can be refunded.');
    }
    $legacyRefundableDiscountRedemption = sr_coupon_runtime_row($pdo, 'SELECT status, dedupe_key, refund_note FROM sr_coupon_redemptions WHERE id = :id', ['id' => $legacyRefundableDiscountRedemptionId]);
    $legacyRefundableDiscountIssue = sr_coupon_runtime_row($pdo, 'SELECT status, used_count FROM sr_coupon_issues WHERE id = :id', ['id' => $legacyRefundableDiscountIssueId]);
    sr_coupon_runtime_assert((string) ($legacyRefundableDiscountRedemption['status'] ?? '') === 'redeemed', 'legacy refundable discount refund rejection should keep redemption active.');
    sr_coupon_runtime_assert((string) ($legacyRefundableDiscountRedemption['dedupe_key'] ?? '') === 'legacy-refundable-discount-dedupe', 'legacy refundable discount refund rejection should keep original dedupe key.');
    sr_coupon_runtime_assert((string) ($legacyRefundableDiscountRedemption['refund_note'] ?? '') === '', 'legacy refundable discount refund rejection should not persist refund note.');
    sr_coupon_runtime_assert((string) ($legacyRefundableDiscountIssue['status'] ?? '') === 'used' && (int) ($legacyRefundableDiscountIssue['used_count'] ?? -1) === 1, 'legacy refundable discount refund rejection should keep issue status and used_count.');

    $statusLifecycleId = sr_coupon_create_definition($pdo, [
        'coupon_key' => 'status_lifecycle',
        'title' => 'Status lifecycle',
        'coupon_type' => 'access',
        'target_type' => 'content',
        'target_id' => '7701',
        'refundable_policy' => 'none',
        'max_uses_per_issue' => '2',
    ]);
    $statusLifecycleIssueId = sr_coupon_issue_to_account($pdo, $statusLifecycleId, 7, 'status lifecycle fixture');
    $statusLifecycleUnusedIssueId = sr_coupon_issue_to_account($pdo, $statusLifecycleId, 8, 'status lifecycle unused fixture');
    sr_coupon_update_definition_status($pdo, $statusLifecycleId, 'issue_stopped');
    try {
        sr_coupon_issue_to_account($pdo, $statusLifecycleId, 8, 'stopped issue attempt');
        sr_coupon_runtime_assert(false, 'issue_stopped coupon definition should reject new issue creation.');
    } catch (InvalidArgumentException $exception) {
        sr_coupon_runtime_assert(str_contains($exception->getMessage(), '사용 중인 쿠폰'), 'issue_stopped new issue failure should be user-facing.');
    }
    $stoppedRedemption = sr_coupon_redeem_for_target($pdo, 7, 'content', '7701', [
        'dedupe_key' => 'content:view:7701:account:7:intent:stopped',
        'reference_module' => 'content',
        'reference_type' => 'content.view',
        'reference_id' => '7701',
    ]);
    sr_coupon_runtime_assert(!empty($stoppedRedemption['allowed']) && !empty($stoppedRedemption['processed']), 'issue_stopped coupon definition should allow existing issued coupons to redeem.');
    $statusLifecycleIssue = sr_coupon_runtime_row($pdo, 'SELECT status, used_count FROM sr_coupon_issues WHERE id = :id', ['id' => $statusLifecycleIssueId]);
    sr_coupon_runtime_assert((string) ($statusLifecycleIssue['status'] ?? '') === 'active' && (int) ($statusLifecycleIssue['used_count'] ?? 0) === 1, 'issue_stopped redemption should preserve remaining active uses.');
    sr_coupon_update_definition_status($pdo, $statusLifecycleId, 'disabled');
    $couponModuleId = (int) $pdo->query("SELECT id FROM sr_modules WHERE module_key = 'coupon'")->fetchColumn();
    $couponNotificationCases = sr_coupon_default_notification_case_settings();
    $couponNotificationCases['definition_disabled']['channels'] = ['site', 'email'];
    $couponNotificationCasesJson = json_encode($couponNotificationCases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $couponNotificationCasesJson = is_string($couponNotificationCasesJson) ? $couponNotificationCasesJson : '{}';
    $pdo->prepare(
        "INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, 'notification_cases', :notification_cases, 'json', :created_at, :updated_at),
            (:module_id, 'disabled_reclaim_notifications_enabled', '1', 'bool', :created_at, :updated_at),
            (:module_id, 'disabled_reclaim_notification_channels', '[\"site\",\"email\"]', 'json', :created_at, :updated_at)"
    )->execute([
        'module_id' => $couponModuleId,
        'notification_cases' => $couponNotificationCasesJson,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    sr_clear_module_settings_cache('coupon');
    $disabledNotificationResult = sr_coupon_notify_definition_disabled_unused_issue_reclaims($pdo, [$statusLifecycleId], 1);
    sr_coupon_runtime_assert(empty($disabledNotificationResult['skipped']), 'disabled coupon definition notification should run when notification module is enabled.');
    sr_coupon_runtime_assert((int) ($disabledNotificationResult['target_issue_count'] ?? -1) === 1, 'disabled coupon definition notification should target only unused active issued coupons.');
    sr_coupon_runtime_assert((int) ($disabledNotificationResult['notification_count'] ?? -1) === 1, 'disabled coupon definition notification should create one reclaim notification.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_notifications WHERE account_id = 8 AND source_module_key = 'coupon' AND event_key = 'issue.definition_disabled'")->fetchColumn() === 1, 'disabled coupon definition notification should store a coupon account event.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*)
        FROM sr_notification_deliveries d
        INNER JOIN sr_notifications n ON n.id = d.notification_id
        WHERE d.channel = 'email'
          AND d.recipient = 'member8@example.test'
          AND n.source_module_key = 'coupon'
          AND n.event_key = 'issue.definition_disabled'")->fetchColumn() === 1, 'disabled coupon definition notification should queue configured email delivery.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_notifications WHERE account_id = 7 AND event_key = 'issue.definition_disabled'")->fetchColumn() === 0, 'disabled coupon definition notification should not notify already used issue holders.');
    sr_coupon_runtime_assert(in_array($statusLifecycleUnusedIssueId, sr_coupon_unused_active_issue_ids_for_definition($pdo, $statusLifecycleId), true), 'disabled coupon definition notification should not mutate unused issue status.');
    $disabledRedemption = sr_coupon_redeem_for_target($pdo, 7, 'content', '7701', [
        'dedupe_key' => 'content:view:7701:account:7:intent:disabled',
        'reference_module' => 'content',
        'reference_type' => 'content.view',
        'reference_id' => '7701',
    ]);
    sr_coupon_runtime_assert(empty($disabledRedemption['allowed']) && empty($disabledRedemption['processed']), 'disabled coupon definition should block existing issued coupons from redeeming.');

    $pdo->prepare(
        "INSERT INTO sr_coupon_definitions
            (coupon_key, title, description, status, coupon_type, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES
            ('paid_access', 'Paid access', '', 'active', 'access', 'content', '42', 'refundable', 2, NULL, NULL, :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $definitionId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO sr_coupon_issues
            (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, issued_at, expires_at, used_count, created_at, updated_at)
         VALUES
            (:definition_id, 7, 'active', 'fixture', NULL, :issued_at, NULL, 0, :created_at, :updated_at)"
    )->execute([
        'definition_id' => $definitionId,
        'issued_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $issueId = (int) $pdo->lastInsertId();

    foreach ([
        ['paid_access_choice_a', 'Paid access choice A', '43'],
        ['paid_access_choice_b', 'Paid access choice B', '43'],
        ['paid_access_choice_other', 'Paid access choice other', '44'],
    ] as $choiceDefinitionFixture) {
        $pdo->prepare(
            "INSERT INTO sr_coupon_definitions
                (coupon_key, title, description, status, coupon_type, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
             VALUES
                (:coupon_key, :title, '', 'active', 'access', 'content', :target_id, 'refundable', 1, NULL, NULL, :created_at, :updated_at)"
        )->execute([
            'coupon_key' => $choiceDefinitionFixture[0],
            'title' => $choiceDefinitionFixture[1],
            'target_id' => $choiceDefinitionFixture[2],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    $choiceDefinitionIds = $pdo->query("SELECT id, coupon_key FROM sr_coupon_definitions WHERE coupon_key IN ('paid_access_choice_a', 'paid_access_choice_b', 'paid_access_choice_other')")->fetchAll();
    $choiceIssueIds = [];
    foreach ($choiceDefinitionIds as $choiceDefinition) {
        $pdo->prepare(
            "INSERT INTO sr_coupon_issues
                (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, issued_at, expires_at, used_count, created_at, updated_at)
             VALUES
                (:definition_id, 7, 'active', 'fixture', NULL, :issued_at, NULL, 0, :created_at, :updated_at)"
        )->execute([
            'definition_id' => (int) $choiceDefinition['id'],
            'issued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $choiceIssueIds[(string) $choiceDefinition['coupon_key']] = (int) $pdo->lastInsertId();
    }

    $choiceIssues = sr_coupon_active_account_target_issues($pdo, 7, 'content', '43', 10);
    $choiceIssueTitles = array_map(static fn (array $issue): string => (string) ($issue['title'] ?? ''), $choiceIssues);
    sr_coupon_runtime_assert(in_array('Paid access choice A', $choiceIssueTitles, true), 'target coupon choices should include the first matching content coupon.');
    sr_coupon_runtime_assert(in_array('Paid access choice B', $choiceIssueTitles, true), 'target coupon choices should include the second matching content coupon.');
    sr_coupon_runtime_assert(!in_array('Paid access choice other', $choiceIssueTitles, true), 'target coupon choices should exclude coupons for a different target.');

    $selectedChoice = sr_coupon_redeem_for_target($pdo, 7, 'content', '43', [
        'dedupe_key' => 'content:view:43:account:7:intent:selected',
        'reference_module' => 'content',
        'reference_type' => 'content.view',
        'reference_id' => '43',
        'coupon_issue_id' => $choiceIssueIds['paid_access_choice_b'],
    ]);
    sr_coupon_runtime_assert(!empty($selectedChoice['allowed']) && !empty($selectedChoice['processed']), 'selected coupon redemption should process.');
    sr_coupon_runtime_assert((int) ($selectedChoice['coupon_issue_id'] ?? 0) === $choiceIssueIds['paid_access_choice_b'], 'selected coupon redemption should use the requested issue.');
    sr_coupon_runtime_assert_issue_unused($pdo, $choiceIssueIds['paid_access_choice_a'], 'unselected target coupon choice');
    sr_coupon_runtime_assert_issue_unused($pdo, $choiceIssueIds['paid_access_choice_other'], 'different target coupon choice');

    $wrongSelectedChoice = sr_coupon_redeem_for_target($pdo, 7, 'content', '43', [
        'dedupe_key' => 'content:view:43:account:7:intent:wrong-selected',
        'reference_module' => 'content',
        'reference_type' => 'content.view',
        'reference_id' => '43',
        'coupon_issue_id' => $choiceIssueIds['paid_access_choice_other'],
    ]);
    sr_coupon_runtime_assert(empty($wrongSelectedChoice['allowed']) && empty($wrongSelectedChoice['processed']), 'selected coupon redemption should reject an issue for a different target.');

    $pdo->prepare(
        "INSERT INTO sr_coupon_definitions
            (coupon_key, title, description, status, coupon_type, discount_amount, discount_percent, discount_currency_code, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES
            ('auto_discount_not_access', 'Auto discount should not be implicit', '', 'active', 'fixed_discount', 30, 0, 'KRW', 'content', '45', 'none', 1, NULL, NULL, :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $autoDiscountDefinitionId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO sr_coupon_issues
            (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, issued_at, expires_at, used_count, created_at, updated_at)
         VALUES
            (:definition_id, 7, 'active', 'fixture', NULL, :issued_at, NULL, 0, :created_at, :updated_at)"
    )->execute([
        'definition_id' => $autoDiscountDefinitionId,
        'issued_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $autoDiscountIssueId = (int) $pdo->lastInsertId();
    $discountChoices = sr_coupon_active_account_target_issues($pdo, 7, 'content', '45', 10);
    sr_coupon_runtime_assert(isset($discountChoices[0]) && (int) ($discountChoices[0]['id'] ?? 0) === $autoDiscountIssueId, 'target coupon choices should include matching discount coupon issues.');
    $implicitDiscount = sr_coupon_redeem_for_target($pdo, 7, 'content', '45', [
        'dedupe_key' => 'content:view:45:account:7:intent:implicit-discount',
        'reference_module' => 'content',
        'reference_type' => 'content.view',
        'reference_id' => '45',
    ]);
    sr_coupon_runtime_assert(empty($implicitDiscount['allowed']), 'implicit coupon redemption without a selected issue should not consume discount coupons.');
    sr_coupon_runtime_assert_issue_unused($pdo, $autoDiscountIssueId, 'implicit discount coupon choice');

    $first = sr_coupon_redeem_for_target($pdo, 7, 'content', '42', [
        'dedupe_key' => 'content:view:42:account:7:intent:abc',
        'reference_module' => 'content',
        'reference_type' => 'content.view',
        'reference_id' => '42',
    ]);
    sr_coupon_runtime_assert(!empty($first['allowed']) && !empty($first['processed']), 'first matching coupon redemption should process.');

    $afterFirst = sr_coupon_runtime_row($pdo, 'SELECT status, used_count FROM sr_coupon_issues WHERE id = :id', ['id' => $issueId]);
    sr_coupon_runtime_assert((string) ($afterFirst['status'] ?? '') === 'active', 'issue should stay active before max uses is reached.');
    sr_coupon_runtime_assert((int) ($afterFirst['used_count'] ?? -1) === 1, 'first redemption should increment used_count.');

    $duplicate = sr_coupon_redeem_for_target($pdo, 7, 'content', '42', [
        'dedupe_key' => 'content:view:42:account:7:intent:abc',
        'reference_module' => 'content',
        'reference_type' => 'content.view',
        'reference_id' => '42',
    ]);
    sr_coupon_runtime_assert(!empty($duplicate['allowed']) && empty($duplicate['processed']) && !empty($duplicate['already_redeemed']), 'same dedupe key should return already_redeemed without new row.');
    $redemptionCount = (int) $pdo->query("SELECT COUNT(*) FROM sr_coupon_redemptions WHERE dedupe_key = 'content:view:42:account:7:intent:abc'")->fetchColumn();
    sr_coupon_runtime_assert($redemptionCount === 1, 'duplicate redemption must not insert another redemption row.');

    $second = sr_coupon_redeem_for_target($pdo, 7, 'content', '42', [
        'dedupe_key' => 'content:view:42:account:7:intent:def',
        'reference_module' => 'content',
        'reference_type' => 'content.view',
        'reference_id' => '42',
    ]);
    sr_coupon_runtime_assert(!empty($second['allowed']) && !empty($second['processed']), 'second unique redemption should process while issue has remaining uses.');
    $afterSecond = sr_coupon_runtime_row($pdo, 'SELECT status, used_count FROM sr_coupon_issues WHERE id = :id', ['id' => $issueId]);
    sr_coupon_runtime_assert((string) ($afterSecond['status'] ?? '') === 'used', 'issue should become used after max uses.');
    sr_coupon_runtime_assert((int) ($afterSecond['used_count'] ?? -1) === 2, 'second redemption should reach max used_count.');

    $third = sr_coupon_redeem_for_target($pdo, 7, 'content', '42', [
        'dedupe_key' => 'content:view:42:account:7:intent:ghi',
    ]);
    sr_coupon_runtime_assert(empty($third['allowed']) && empty($third['processed']), 'used issue should not allow a third unique redemption.');

    $firstRedemption = sr_coupon_runtime_row($pdo, "SELECT id, dedupe_key FROM sr_coupon_redemptions WHERE dedupe_key = 'content:view:42:account:7:intent:abc'");
    $pdo->prepare(
        "INSERT INTO sr_content_access_entitlements
            (account_id, content_id, subject_type, subject_id, access_kind, source_kind, source_asset_module, source_charge_policy, source_reference, granted_at, created_at)
         VALUES
            (7, 42, 'content', 42, 'view', 'coupon', '', 'once', :source_reference, :granted_at, :created_at)"
    )->execute([
        'source_reference' => (string) ($firstRedemption['dedupe_key'] ?? ''),
        'granted_at' => sr_now(),
        'created_at' => sr_now(),
    ]);
    $pdo->prepare(
        "INSERT INTO sr_community_access_entitlements
            (account_id, subject_type, subject_id, event_key, source_kind, source_asset_module, source_charge_policy, source_reference, granted_at, created_at)
         VALUES
            (7, 'community.post', 99, 'post_read', 'coupon', '', 'once', :source_reference, :granted_at, :created_at)"
    )->execute([
        'source_reference' => (string) ($firstRedemption['dedupe_key'] ?? ''),
        'granted_at' => sr_now(),
        'created_at' => sr_now(),
    ]);

    $previousErrorLog = ini_get('error_log');
    $temporaryErrorLog = tempnam(sys_get_temp_dir(), 'sr_coupon_revoke_error_log_');
    if (is_string($temporaryErrorLog) && $temporaryErrorLog !== '') {
        ini_set('error_log', $temporaryErrorLog);
    }
    $pdo->exec("CREATE TRIGGER sr_fixture_coupon_revoke_failure
        BEFORE DELETE ON sr_content_access_entitlements
        BEGIN
            SELECT RAISE(ABORT, 'fixture coupon revoke failure');
        END");
    try {
        sr_coupon_refund_redemption($pdo, (int) ($firstRedemption['id'] ?? 0), 1, 'fixture refund failure');
        sr_coupon_runtime_assert(false, 'coupon refund should fail when access revoke fails.');
    } catch (RuntimeException $exception) {
        sr_coupon_runtime_assert(str_contains($exception->getMessage(), '접근권 회수'), 'coupon refund revoke failure should be user-facing.');
    }
    $pdo->exec('DROP TRIGGER sr_fixture_coupon_revoke_failure');
    if (is_string($previousErrorLog)) {
        ini_set('error_log', $previousErrorLog);
    }
    if (is_string($temporaryErrorLog) && is_file($temporaryErrorLog)) {
        unlink($temporaryErrorLog);
    }
    $afterFailedRefundRedemption = sr_coupon_runtime_row($pdo, 'SELECT status, dedupe_key, refund_note FROM sr_coupon_redemptions WHERE id = :id', ['id' => (int) ($firstRedemption['id'] ?? 0)]);
    $afterFailedRefundIssue = sr_coupon_runtime_row($pdo, 'SELECT status, used_count FROM sr_coupon_issues WHERE id = :id', ['id' => $issueId]);
    sr_coupon_runtime_assert((string) ($afterFailedRefundRedemption['status'] ?? '') === 'redeemed', 'coupon refund revoke failure should keep redemption active.');
    sr_coupon_runtime_assert((string) ($afterFailedRefundRedemption['dedupe_key'] ?? '') === 'content:view:42:account:7:intent:abc', 'coupon refund revoke failure should keep original dedupe key.');
    sr_coupon_runtime_assert((string) ($afterFailedRefundRedemption['refund_note'] ?? '') === '', 'coupon refund revoke failure should not persist refund note.');
    sr_coupon_runtime_assert((string) ($afterFailedRefundIssue['status'] ?? '') === 'used' && (int) ($afterFailedRefundIssue['used_count'] ?? -1) === 2, 'coupon refund revoke failure should roll back issue status and used_count.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_content_access_entitlements')->fetchColumn() === 1, 'coupon refund revoke failure should leave content entitlement in place.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_community_access_entitlements')->fetchColumn() === 1, 'coupon refund revoke failure should leave community entitlement in place.');

    $refund = sr_coupon_refund_redemption($pdo, (int) ($firstRedemption['id'] ?? 0), 1, 'fixture refund');
    sr_coupon_runtime_assert((int) ($refund['used_count'] ?? -1) === 1, 'refund should decrement issue used_count.');
    sr_coupon_runtime_assert((string) ($refund['issue_status'] ?? '') === 'active', 'refund should reactivate an issue that was used only because max uses was reached.');
    sr_coupon_runtime_assert((string) ($refund['original_dedupe_key'] ?? '') === 'content:view:42:account:7:intent:abc', 'refund result should preserve original dedupe key.');
    sr_coupon_runtime_assert(str_starts_with((string) ($refund['refunded_dedupe_key'] ?? ''), 'refunded:'), 'refund should move redemption to a refunded dedupe namespace.');
    sr_coupon_runtime_assert((int) ($refund['revoked_access_count'] ?? -1) === 1, 'refund should revoke only the redemption target access entitlement.');

    $refundedOriginalVisible = sr_coupon_has_redemption($pdo, 7, 'content:view:42:account:7:intent:abc');
    sr_coupon_runtime_assert(!$refundedOriginalVisible, 'refunded redemption should not satisfy active redemption lookup for original dedupe key.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_content_access_entitlements')->fetchColumn() === 0, 'content coupon entitlement should be removed after refund.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_community_access_entitlements')->fetchColumn() === 1, 'content redemption refund should not revoke unrelated community entitlements with the same dedupe key.');

    $afterRefund = sr_coupon_runtime_row($pdo, 'SELECT status, dedupe_key, refund_note FROM sr_coupon_redemptions WHERE id = :id', ['id' => (int) ($firstRedemption['id'] ?? 0)]);
    sr_coupon_runtime_assert((string) ($afterRefund['status'] ?? '') === 'refunded', 'refund should mark redemption as refunded.');
    sr_coupon_runtime_assert((string) ($afterRefund['dedupe_key'] ?? '') === (string) ($refund['refunded_dedupe_key'] ?? ''), 'refund should update stored dedupe key to refunded namespace.');
    sr_coupon_runtime_assert((string) ($afterRefund['refund_note'] ?? '') === 'fixture refund', 'refund should store admin refund note.');

    $notificationsBeforeStateOnly = (int) $pdo->query("SELECT COUNT(*) FROM sr_notifications WHERE event_key = 'redemption.refunded'")->fetchColumn();
    $pdo->prepare(
        "INSERT INTO sr_coupon_definitions
            (coupon_key, title, description, status, coupon_type, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES
            ('state_only_refund', 'State-only refund', '', 'active', 'access', 'content', '43', 'refundable', 1, NULL, NULL, :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $stateOnlyDefinitionId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO sr_coupon_issues
            (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, issued_at, expires_at, used_count, created_at, updated_at)
         VALUES
            (:definition_id, 7, 'used', 'fixture', NULL, :issued_at, NULL, 1, :created_at, :updated_at)"
    )->execute([
        'definition_id' => $stateOnlyDefinitionId,
        'issued_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $stateOnlyIssueId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO sr_coupon_redemptions
            (coupon_issue_id, coupon_definition_id, account_id, target_type, target_id, reference_module, reference_type, reference_id, dedupe_key, status, redeemed_at, created_at)
         VALUES
            (:issue_id, :definition_id, 7, 'content', '43', 'content', 'content.view', '43', 'content:view:43:account:7:intent:state-only', 'redeemed', :redeemed_at, :created_at)"
    )->execute([
        'issue_id' => $stateOnlyIssueId,
        'definition_id' => $stateOnlyDefinitionId,
        'redeemed_at' => $now,
        'created_at' => $now,
    ]);
    $stateOnlyRedemptionId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO sr_content_access_entitlements
            (account_id, content_id, subject_type, subject_id, access_kind, source_kind, source_asset_module, source_charge_policy, source_reference, granted_at, created_at)
         VALUES
            (7, 43, 'content', 43, 'view', 'coupon', '', 'once', 'content:view:43:account:7:intent:state-only', :granted_at, :created_at)"
    )->execute([
        'granted_at' => $now,
        'created_at' => $now,
    ]);
    $stateOnlyRefund = sr_coupon_refund_redemption_state_only($pdo, $stateOnlyRedemptionId, 1, 'state only fixture refund');
    sr_coupon_runtime_assert((int) ($stateOnlyRefund['used_count'] ?? -1) === 0, 'state-only refund should decrement issue used_count.');
    sr_coupon_runtime_assert((int) ($stateOnlyRefund['revoked_access_count'] ?? -1) === 0, 'state-only refund should not revoke access itself.');
    sr_coupon_runtime_assert(is_array($stateOnlyRefund['notification_payload'] ?? null), 'state-only refund should return a notification payload for an outer orchestrator.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_content_access_entitlements WHERE source_reference = 'content:view:43:account:7:intent:state-only'")->fetchColumn() === 1, 'state-only refund should leave access revocation to the domain orchestrator.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_notifications WHERE event_key = 'redemption.refunded'")->fetchColumn() === $notificationsBeforeStateOnly, 'state-only refund should not emit notification by itself.');

    $pdo->prepare(
        "INSERT INTO sr_coupon_definitions
            (coupon_key, title, description, status, coupon_type, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES
            ('missing_revoke_contract', 'Missing revoke contract', '', 'active', 'access', 'missing_revoke', '1', 'refundable', 1, NULL, NULL, :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $missingRevokeDefinitionId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO sr_coupon_issues
            (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, issued_at, expires_at, used_count, created_at, updated_at)
         VALUES
            (:definition_id, 7, 'used', 'fixture', NULL, :issued_at, NULL, 1, :created_at, :updated_at)"
    )->execute([
        'definition_id' => $missingRevokeDefinitionId,
        'issued_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $missingRevokeIssueId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO sr_coupon_redemptions
            (coupon_issue_id, coupon_definition_id, account_id, target_type, target_id, reference_module, reference_type, reference_id, dedupe_key, status, redeemed_at, created_at)
         VALUES
            (:issue_id, :definition_id, 7, 'missing_revoke', '1', 'legacy', 'legacy.access', '1', 'missing-revoke-dedupe', 'redeemed', :redeemed_at, :created_at)"
    )->execute([
        'issue_id' => $missingRevokeIssueId,
        'definition_id' => $missingRevokeDefinitionId,
        'redeemed_at' => $now,
        'created_at' => $now,
    ]);
    $missingRevokeRedemptionId = (int) $pdo->lastInsertId();
    try {
        sr_coupon_refund_redemption($pdo, $missingRevokeRedemptionId, 1, 'missing revoke refund');
        sr_coupon_runtime_assert(false, 'coupon refund should fail when the redemption target has no revoke contract.');
    } catch (RuntimeException $exception) {
        sr_coupon_runtime_assert(str_contains($exception->getMessage(), '회수 계약'), 'coupon refund missing revoke contract failure should be user-facing.');
    }
    $missingRevokeRedemption = sr_coupon_runtime_row($pdo, 'SELECT status, dedupe_key, refund_note FROM sr_coupon_redemptions WHERE id = :id', ['id' => $missingRevokeRedemptionId]);
    $missingRevokeIssue = sr_coupon_runtime_row($pdo, 'SELECT status, used_count FROM sr_coupon_issues WHERE id = :id', ['id' => $missingRevokeIssueId]);
    sr_coupon_runtime_assert((string) ($missingRevokeRedemption['status'] ?? '') === 'redeemed', 'missing revoke contract refund should keep redemption active.');
    sr_coupon_runtime_assert((string) ($missingRevokeRedemption['dedupe_key'] ?? '') === 'missing-revoke-dedupe', 'missing revoke contract refund should keep original dedupe key.');
    sr_coupon_runtime_assert((string) ($missingRevokeRedemption['refund_note'] ?? '') === '', 'missing revoke contract refund should not persist refund note.');
    sr_coupon_runtime_assert((string) ($missingRevokeIssue['status'] ?? '') === 'used' && (int) ($missingRevokeIssue['used_count'] ?? -1) === 1, 'missing revoke contract refund should roll back issue status and used_count.');

    $pdo->prepare(
        "INSERT INTO sr_coupon_definitions
            (coupon_key, title, description, status, coupon_type, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES
            ('content_missing_access', 'Content missing access', '', 'active', 'access', 'content', '88', 'refundable', 1, NULL, NULL, :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $missingAccessDefinitionId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO sr_coupon_issues
            (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, issued_at, expires_at, used_count, created_at, updated_at)
         VALUES
            (:definition_id, 7, 'used', 'fixture', NULL, :issued_at, NULL, 1, :created_at, :updated_at)"
    )->execute([
        'definition_id' => $missingAccessDefinitionId,
        'issued_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $missingAccessIssueId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO sr_coupon_redemptions
            (coupon_issue_id, coupon_definition_id, account_id, target_type, target_id, reference_module, reference_type, reference_id, dedupe_key, status, redeemed_at, created_at)
         VALUES
            (:issue_id, :definition_id, 7, 'content', '88', 'content', 'content.view', '88', 'content:view:88:account:7:intent:missing-access', 'redeemed', :redeemed_at, :created_at)"
    )->execute([
        'issue_id' => $missingAccessIssueId,
        'definition_id' => $missingAccessDefinitionId,
        'redeemed_at' => $now,
        'created_at' => $now,
    ]);
    $missingAccessRedemptionId = (int) $pdo->lastInsertId();
    try {
        sr_coupon_refund_redemption($pdo, $missingAccessRedemptionId, 1, 'missing access refund');
        sr_coupon_runtime_assert(false, 'coupon refund should fail when the target revoke contract revokes no access rows.');
    } catch (RuntimeException $exception) {
        sr_coupon_runtime_assert(str_contains($exception->getMessage(), '접근권 회수'), 'coupon refund missing access failure should be user-facing.');
    }
    $missingAccessRedemption = sr_coupon_runtime_row($pdo, 'SELECT status, dedupe_key, refund_note FROM sr_coupon_redemptions WHERE id = :id', ['id' => $missingAccessRedemptionId]);
    $missingAccessIssue = sr_coupon_runtime_row($pdo, 'SELECT status, used_count FROM sr_coupon_issues WHERE id = :id', ['id' => $missingAccessIssueId]);
    sr_coupon_runtime_assert((string) ($missingAccessRedemption['status'] ?? '') === 'redeemed', 'missing access refund should keep redemption active.');
    sr_coupon_runtime_assert((string) ($missingAccessRedemption['dedupe_key'] ?? '') === 'content:view:88:account:7:intent:missing-access', 'missing access refund should keep original dedupe key.');
    sr_coupon_runtime_assert((string) ($missingAccessRedemption['refund_note'] ?? '') === '', 'missing access refund should not persist refund note.');
    sr_coupon_runtime_assert((string) ($missingAccessIssue['status'] ?? '') === 'used' && (int) ($missingAccessIssue['used_count'] ?? -1) === 1, 'missing access refund should roll back issue status and used_count.');

    $pdo->prepare(
        "INSERT INTO sr_coupon_definitions
            (coupon_key, title, description, status, coupon_type, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES
            ('content_priority', 'Content priority coupon', '', 'active', 'access', 'content', '77', 'refundable', 1, NULL, NULL, :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $priorityDefinitionId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO sr_coupon_issues
            (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, issued_at, expires_at, used_count, created_at, updated_at)
         VALUES
            (:definition_id, 7, 'active', 'fixture', NULL, :issued_at, NULL, 0, :created_at, :updated_at)"
    )->execute([
        'definition_id' => $priorityDefinitionId,
        'issued_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $priorityIssueId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO sr_point_balances (account_id, balance, created_at, updated_at)
         VALUES (7, 1000, :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);

    $_SESSION['sr_content_asset_confirmation_requests'] = [];
    $_SESSION['sr_content_asset_confirmations'] = [];
    $paidPage = [
        'id' => 77,
        'asset_access_enabled' => 1,
        'asset_module' => 'point',
        'asset_access_amount' => 100,
        'asset_access_amounts_json' => '{"point":100}',
        'asset_access_group_policies_json' => '',
        'asset_access_policy_set_id' => 0,
        'asset_access_settlement_currency' => 'KRW',
        'asset_charge_policy' => 'once',
    ];
    $confirmation = sr_content_charge_view_access($pdo, $paidPage, 7, false);
    $token = (string) ($confirmation['confirmation_request_token'] ?? '');
    sr_coupon_runtime_assert($token !== '', 'content paid view fixture should create a confirmation token before processing.');

    $priorityResult = sr_content_charge_view_access($pdo, $paidPage, 7, true, $token, $priorityIssueId);
    sr_coupon_runtime_assert(!empty($priorityResult['allowed']), 'content paid view fixture should allow coupon-backed access.');
    sr_coupon_runtime_assert(empty($priorityResult['charged']), 'content paid view fixture must not charge assets when selected coupon is applied.');
    sr_coupon_runtime_assert(!empty($priorityResult['coupon_used']), 'content paid view fixture must report coupon_used on first selected coupon-backed access.');
    sr_coupon_runtime_assert((int) ($priorityResult['amount'] ?? -1) === 0, 'content paid view fixture should reduce payable amount to zero when selected coupon is used first.');
    sr_coupon_runtime_assert((string) ($priorityResult['asset_label'] ?? '') === '쿠폰', 'content paid view fixture should label coupon-backed access as coupon.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_point_transactions')->fetchColumn() === 0, 'content paid view fixture must not create point transactions when selected coupon is applied.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_content_asset_access_logs')->fetchColumn() === 0, 'content paid view fixture must not create asset access logs when selected coupon is applied.');

    $priorityRedemption = sr_coupon_runtime_row($pdo, "SELECT status, used_count FROM sr_coupon_issues WHERE id = :id", ['id' => $priorityIssueId]);
    sr_coupon_runtime_assert((string) ($priorityRedemption['status'] ?? '') === 'used', 'content paid view fixture should mark the priority coupon issue used.');
    sr_coupon_runtime_assert((int) ($priorityRedemption['used_count'] ?? -1) === 1, 'content paid view fixture should increment the priority coupon issue once.');
    $couponEntitlement = sr_coupon_runtime_row(
        $pdo,
        "SELECT source_kind, source_asset_module, source_charge_policy, source_reference
         FROM sr_content_access_entitlements
         WHERE account_id = 7
           AND content_id = 77
           AND subject_type = 'content'
           AND subject_id = 77
           AND access_kind = 'view'"
    );
    sr_coupon_runtime_assert((string) ($couponEntitlement['source_kind'] ?? '') === 'coupon', 'content paid view fixture should grant a coupon source entitlement.');
    sr_coupon_runtime_assert((string) ($couponEntitlement['source_asset_module'] ?? '') === '', 'content paid view fixture should not attach an asset module to coupon entitlement.');
    sr_coupon_runtime_assert((string) ($couponEntitlement['source_charge_policy'] ?? '') === 'once', 'content paid view fixture should preserve the content charge policy on coupon entitlement.');
    sr_coupon_runtime_assert((string) ($couponEntitlement['source_reference'] ?? '') === 'content.view:coupon:7:77', 'content paid view fixture should store the coupon dedupe key on entitlement.');
    $contentViewPayment = sr_coupon_runtime_payment_record($pdo, 'content', 'content.view', '77');
    $contentViewPaymentId = (int) ($contentViewPayment['id'] ?? 0);
    sr_coupon_runtime_assert($contentViewPaymentId > 0, 'content paid view fixture should record a common payment ledger row.');
    sr_coupon_runtime_assert((int) ($contentViewPayment['payable_amount'] ?? -1) === 100 && (int) ($contentViewPayment['settlement_amount'] ?? -1) === 0, 'content paid view payment ledger should separate payable amount from zero settlement.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_count($pdo, $contentViewPaymentId, 'coupon_redemption') === 1, 'content paid view payment ledger should include the coupon redemption item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_count($pdo, $contentViewPaymentId, 'access_entitlement') === 1, 'content paid view payment ledger should include the access entitlement item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_reference($pdo, $contentViewPaymentId, 'access_entitlement') === 'content:77:view', 'content paid view payment ledger access item should not embed the account id.');
    $contentViewUnit = sr_coupon_runtime_row($pdo, 'SELECT id, payment_type, settlement_kind, payable_amount, settlement_amount, asset_access_log_ids_json, coupon_redemption_id, refund_policy_version FROM sr_content_view_payment_logs WHERE content_id = 77 LIMIT 1');
    sr_coupon_runtime_assert((string) ($contentViewUnit['payment_type'] ?? '') === 'coupon_access', 'content paid view payment-unit should classify full access coupon payments.');
    sr_coupon_runtime_assert((string) ($contentViewUnit['settlement_kind'] ?? '') === 'paid_settled_zero', 'content paid view payment-unit should preserve zero settlement classification.');
    sr_coupon_runtime_assert((int) ($contentViewUnit['payable_amount'] ?? -1) === 100 && (int) ($contentViewUnit['settlement_amount'] ?? -1) === 0, 'content paid view payment-unit should separate payable and settlement amounts.');
    sr_coupon_runtime_assert((string) ($contentViewUnit['asset_access_log_ids_json'] ?? '') === '[]', 'content paid view payment-unit should not attach asset logs for coupon-only access.');
    sr_coupon_runtime_assert((int) ($contentViewUnit['coupon_redemption_id'] ?? 0) > 0, 'content paid view payment-unit should link the coupon redemption.');
    sr_coupon_runtime_assert((string) ($contentViewUnit['refund_policy_version'] ?? '') === 'content_view_refund_v1', 'content paid view payment-unit should stamp the refund policy version.');

    $repeat = sr_content_charge_view_access($pdo, $paidPage, 7, true, $token);
    sr_coupon_runtime_assert(!empty($repeat['allowed']) && empty($repeat['charged']) && !empty($repeat['already_paid']), 'content paid view fixture should reuse existing coupon entitlement without another charge.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_coupon_redemptions WHERE reference_module = 'content' AND reference_type = 'content.view' AND reference_id = '77'")->fetchColumn() === 1, 'content paid view fixture should not create another coupon redemption for once access.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_content_view_payment_logs WHERE content_id = 77')->fetchColumn() === 1, 'content paid view repeat should not create another payment-unit row for existing entitlement.');
    $contentViewRefund = sr_content_refund_view_payment($pdo, (int) ($contentViewUnit['id'] ?? 0), 1, 'content coupon view domain refund');
    sr_coupon_runtime_assert(!empty($contentViewRefund['ok']), 'content paid view payment-unit refund should handle full coupon payments.');
    sr_coupon_runtime_assert((string) sr_coupon_runtime_scalar($pdo, 'SELECT status FROM sr_coupon_redemptions WHERE id = :id', ['id' => (int) ($contentViewUnit['coupon_redemption_id'] ?? 0)]) === 'refunded', 'content paid view payment-unit refund should refund the linked coupon redemption.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_content_access_entitlements WHERE account_id = 7 AND content_id = 77 AND subject_type = 'content' AND subject_id = 77 AND access_kind = 'view'")->fetchColumn() === 0, 'content paid view payment-unit refund should revoke the coupon-source entitlement.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $contentViewPaymentId, 'coupon_redemption', 'reversed') === 1, 'content paid view payment-unit refund should reverse the coupon payment item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $contentViewPaymentId, 'access_entitlement', 'reversed') === 1, 'content paid view payment-unit refund should reverse the access payment item.');

    $pdo->prepare(
        "INSERT INTO sr_content_items (id, title, slug, status, updated_at)
         VALUES (77, 'Paid content', 'paid-content', 'published', :updated_at)"
    )->execute(['updated_at' => $now]);
    $pdo->prepare(
        "INSERT INTO sr_content_files
            (id, content_id, title, original_name, status, asset_download_enabled, asset_module, asset_download_amount, asset_download_settlement_currency, asset_download_amounts_json, asset_download_group_policies_json, asset_download_policy_set_id, asset_charge_policy, updated_at)
         VALUES
            (501, 77, 'Paid file', 'paid-file.txt', 'active', 1, 'point', 120, 'KRW', '{\"point\":120}', '', 0, 'once', :updated_at)"
    )->execute(['updated_at' => $now]);
    $pdo->prepare(
        "INSERT INTO sr_coupon_definitions
            (coupon_key, title, description, status, coupon_type, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES
            ('download_priority', 'Download priority coupon', '', 'active', 'access', 'content_file', '501', 'refundable', 1, NULL, NULL, :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $downloadDefinitionId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO sr_coupon_issues
            (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, issued_at, expires_at, used_count, created_at, updated_at)
         VALUES
            (:definition_id, 7, 'active', 'fixture', NULL, :issued_at, NULL, 0, :created_at, :updated_at)"
    )->execute([
        'definition_id' => $downloadDefinitionId,
        'issued_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $downloadIssueId = (int) $pdo->lastInsertId();

    $targetTypes = sr_coupon_target_types($pdo);
    sr_coupon_runtime_assert(isset($targetTypes['content_file']), 'content coupon target contract should expose content_file targets.');
    $fileTargets = sr_content_coupon_target_search($pdo, 'content_file', 'Paid file', 5);
    sr_coupon_runtime_assert(isset($fileTargets[0]) && (string) ($fileTargets[0]['reference_id'] ?? '') === '501', 'content_file coupon target search should return matching download files.');
    $adminFileTargets = sr_coupon_target_search($pdo, 'content_file', 'Paid file', 5);
    sr_coupon_runtime_assert(isset($adminFileTargets[0]) && (string) ($adminFileTargets[0]['reference_id'] ?? '') === '501', 'coupon admin target search should return matching download files.');
    sr_coupon_runtime_assert(strpos((string) ($adminFileTargets[0]['capability_label'] ?? ''), '가격 조회') !== false, 'coupon admin target search should expose target capabilities.');
    sr_coupon_runtime_assert((string) ($adminFileTargets[0]['pricing_label'] ?? '') === '현재 가격: 120KRW', 'coupon admin target search should expose current pricing.');
    sr_coupon_runtime_assert((string) ($adminFileTargets[0]['policy_summary'] ?? '') === '콘텐츠 다운로드 120KRW', 'coupon admin target search should expose pricing policy summary.');
    $fileHealth = sr_content_coupon_target_health($pdo, 'content_file', '501');
    sr_coupon_runtime_assert((string) ($fileHealth['status'] ?? '') === 'ok', 'content_file coupon target health should accept active download files.');
    sr_coupon_runtime_assert(sr_content_coupon_target_admin_url('content_file', '501') === '/admin/content/files?id=501', 'content_file coupon target admin URL should point to the download file editor.');

    $_SESSION['sr_content_asset_confirmation_requests'] = [];
    $_SESSION['sr_content_asset_confirmations'] = [];
    $paidFile = [
        'id' => 501,
        'content_id' => 77,
        'asset_download_enabled' => 1,
        'asset_module' => 'point',
        'asset_download_amount' => 120,
        'asset_download_amounts_json' => '{"point":120}',
        'asset_download_group_policies_json' => '',
        'asset_download_policy_set_id' => 0,
        'asset_download_settlement_currency' => 'KRW',
        'asset_charge_policy' => 'once',
    ];
    $downloadConfirmation = sr_content_charge_file_download($pdo, $paidFile, 7, false);
    $downloadToken = (string) ($downloadConfirmation['confirmation_request_token'] ?? '');
    sr_coupon_runtime_assert($downloadToken !== '', 'content paid download fixture should create a confirmation token before processing.');

    $downloadResult = sr_content_charge_file_download($pdo, $paidFile, 7, true, $downloadToken, $downloadIssueId);
    sr_coupon_runtime_assert(!empty($downloadResult['allowed']), 'content paid download fixture should allow coupon-backed access.');
    sr_coupon_runtime_assert(empty($downloadResult['charged']), 'content paid download fixture must not charge assets when selected coupon is applied.');
    sr_coupon_runtime_assert(!empty($downloadResult['coupon_used']), 'content paid download fixture must report coupon_used on first selected coupon-backed download.');
    sr_coupon_runtime_assert((int) ($downloadResult['amount'] ?? -1) === 0, 'content paid download fixture should reduce payable amount to zero when selected coupon is used first.');
    sr_coupon_runtime_assert((string) ($downloadResult['asset_label'] ?? '') === '쿠폰', 'content paid download fixture should label coupon-backed download as coupon.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_point_transactions')->fetchColumn() === 0, 'content paid download fixture must not create point transactions when selected coupon is applied.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_content_asset_access_logs')->fetchColumn() === 0, 'content paid download fixture must not create asset access logs when selected coupon is applied.');

    $downloadIssue = sr_coupon_runtime_row($pdo, 'SELECT status, used_count FROM sr_coupon_issues WHERE id = :id', ['id' => $downloadIssueId]);
    sr_coupon_runtime_assert((string) ($downloadIssue['status'] ?? '') === 'used', 'content paid download fixture should mark the priority coupon issue used.');
    sr_coupon_runtime_assert((int) ($downloadIssue['used_count'] ?? -1) === 1, 'content paid download fixture should increment the priority coupon issue once.');
    $downloadRedemption = sr_coupon_runtime_row(
        $pdo,
        "SELECT amount, currency_code, asset_unit, policy_summary, priced_at, target_snapshot_json
         FROM sr_coupon_redemptions
         WHERE reference_module = 'content'
           AND reference_type = 'content.download'
           AND reference_id = '501'
         LIMIT 1"
    );
    $downloadSnapshot = json_decode((string) ($downloadRedemption['target_snapshot_json'] ?? ''), true);
    sr_coupon_runtime_assert((int) ($downloadRedemption['amount'] ?? -1) === 120, 'content paid download redemption should store the pricing amount snapshot.');
    sr_coupon_runtime_assert((string) ($downloadRedemption['currency_code'] ?? '') === 'KRW', 'content paid download redemption should store the pricing currency snapshot.');
    sr_coupon_runtime_assert((string) ($downloadRedemption['asset_unit'] ?? '') === '', 'content paid download redemption should leave asset_unit empty for currency pricing.');
    sr_coupon_runtime_assert((string) ($downloadRedemption['policy_summary'] ?? '') === '콘텐츠 다운로드 120KRW', 'content paid download redemption should store the pricing policy summary.');
    sr_coupon_runtime_assert((string) ($downloadRedemption['priced_at'] ?? '') !== '', 'content paid download redemption should store priced_at.');
    sr_coupon_runtime_assert(is_array($downloadSnapshot) && (string) ($downloadSnapshot['target_type'] ?? '') === 'content_file' && (int) ($downloadSnapshot['amount'] ?? -1) === 120, 'content paid download redemption should store a whitelisted target pricing snapshot.');
    $downloadEntitlement = sr_coupon_runtime_row(
        $pdo,
        "SELECT source_kind, source_asset_module, source_charge_policy, source_reference
         FROM sr_content_access_entitlements
         WHERE account_id = 7
           AND content_id = 77
           AND subject_type = 'content_file'
           AND subject_id = 501
           AND access_kind = 'download'"
    );
    sr_coupon_runtime_assert((string) ($downloadEntitlement['source_kind'] ?? '') === 'coupon', 'content paid download fixture should grant a coupon source entitlement.');
    sr_coupon_runtime_assert((string) ($downloadEntitlement['source_reference'] ?? '') === 'content.download:coupon:7:501', 'content paid download fixture should store the coupon dedupe key on entitlement.');
    $contentDownloadPayment = sr_coupon_runtime_payment_record($pdo, 'content', 'content.download', '501');
    $contentDownloadPaymentId = (int) ($contentDownloadPayment['id'] ?? 0);
    sr_coupon_runtime_assert($contentDownloadPaymentId > 0, 'content paid download fixture should record a common payment ledger row.');
    sr_coupon_runtime_assert((int) ($contentDownloadPayment['payable_amount'] ?? -1) === 120 && (int) ($contentDownloadPayment['settlement_amount'] ?? -1) === 0, 'content paid download payment ledger should separate payable amount from zero settlement.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_count($pdo, $contentDownloadPaymentId, 'coupon_redemption') === 1, 'content paid download payment ledger should include the coupon redemption item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_count($pdo, $contentDownloadPaymentId, 'access_entitlement') === 1, 'content paid download payment ledger should include the access entitlement item.');
    sr_content_record_file_download($pdo, $paidFile, 7, $downloadResult);
    $downloadLog = sr_coupon_runtime_row($pdo, 'SELECT download_type, amount, asset_access_log_ids_json, coupon_redemption_id, coupon_dedupe_key, refund_policy_version FROM sr_content_file_download_logs WHERE file_id = 501 LIMIT 1');
    sr_coupon_runtime_assert((string) ($downloadLog['download_type'] ?? '') === 'paid', 'content paid download fixture should record coupon-backed downloads as paid downloads.');
    sr_coupon_runtime_assert((int) ($downloadLog['amount'] ?? -1) === 0, 'content paid download fixture should record zero asset amount for coupon-backed downloads.');
    sr_coupon_runtime_assert((string) ($downloadLog['asset_access_log_ids_json'] ?? '') === '[]', 'content paid download fixture should not attach asset log ids to coupon-backed downloads.');
    sr_coupon_runtime_assert((int) ($downloadLog['coupon_redemption_id'] ?? 0) > 0, 'content paid download fixture should link the coupon redemption on the download log.');
    sr_coupon_runtime_assert((string) ($downloadLog['coupon_dedupe_key'] ?? '') === 'content.download:coupon:7:501', 'content paid download fixture should store the coupon dedupe key on the download log.');
    sr_coupon_runtime_assert((string) ($downloadLog['refund_policy_version'] ?? '') === 'content_file_download_refund_v1', 'content paid download fixture should stamp the file download refund policy version.');

    $downloadRepeat = sr_content_charge_file_download($pdo, $paidFile, 7, true, $downloadToken);
    sr_coupon_runtime_assert(!empty($downloadRepeat['allowed']) && empty($downloadRepeat['charged']) && !empty($downloadRepeat['already_paid']), 'content paid download fixture should reuse existing coupon entitlement without another charge.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_coupon_redemptions WHERE reference_module = 'content' AND reference_type = 'content.download' AND reference_id = '501'")->fetchColumn() === 1, 'content paid download fixture should not create another coupon redemption for once download access.');

    $alreadyEntitledDownloadIssueId = sr_coupon_runtime_issue($pdo, 'download_already_entitled', 'content_file', '501', 7);
    $alreadyEntitledDownload = sr_coupon_redeem_for_target($pdo, 7, 'content_file', '501', [
        'dedupe_key' => 'content.download:coupon:7:501:already-entitled',
        'reference_module' => 'content',
        'reference_type' => 'content.download',
        'reference_id' => '501',
    ]);
    sr_coupon_runtime_assert(!empty($alreadyEntitledDownload['allowed']) && empty($alreadyEntitledDownload['processed']) && !empty($alreadyEntitledDownload['already_entitled']), 'content already-entitled coupon attempt should return already_entitled without consuming a coupon.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_coupon_redemptions WHERE dedupe_key = 'content.download:coupon:7:501:already-entitled'")->fetchColumn() === 0, 'content already-entitled coupon attempt should not create a redemption row.');
    sr_coupon_runtime_assert_issue_unused($pdo, $alreadyEntitledDownloadIssueId, 'content already-entitled coupon attempt');

    $downloadLogId = (int) $pdo->query('SELECT id FROM sr_content_file_download_logs WHERE file_id = 501 LIMIT 1')->fetchColumn();
    $revokeResult = sr_content_refund_file_download($pdo, $downloadLogId, 1, 'coupon access revoke');
    sr_coupon_runtime_assert(!empty($revokeResult['ok']), 'content paid download fixture should revoke coupon-backed download access through refund helper.');
    sr_coupon_runtime_assert((string) ($revokeResult['message'] ?? '') === '다운로드 접근권을 회수했습니다.', 'content paid download fixture should return the access revoke message for coupon-backed access.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_content_access_entitlements WHERE account_id = 7 AND subject_type = 'content_file' AND subject_id = 501 AND access_kind = 'download'")->fetchColumn() === 0, 'content paid download fixture should remove coupon-backed file entitlement on access revoke.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_point_transactions')->fetchColumn() === 0, 'content paid download fixture must still have no point transactions after coupon access revocation.');
    $revokedDownloadLog = sr_coupon_runtime_row($pdo, 'SELECT refund_status, refund_transaction_ids_json, refund_note, access_revoked_at FROM sr_content_file_download_logs WHERE id = :id', ['id' => $downloadLogId]);
    sr_coupon_runtime_assert((string) ($revokedDownloadLog['refund_status'] ?? '') === 'access_revoked', 'content paid download fixture should persist access_revoked status.');
    sr_coupon_runtime_assert((string) ($revokedDownloadLog['refund_transaction_ids_json'] ?? '') === '[]', 'content paid download fixture should persist empty refund transaction ids.');
    sr_coupon_runtime_assert((string) ($revokedDownloadLog['refund_note'] ?? '') === 'coupon access revoke', 'content paid download fixture should persist access revoke note.');
    sr_coupon_runtime_assert((string) ($revokedDownloadLog['access_revoked_at'] ?? '') !== '', 'content paid download fixture should store access_revoked_at.');
    $contentDownloadPaymentAfterRevoke = sr_coupon_runtime_row($pdo, 'SELECT status FROM sr_payment_records WHERE id = :id', ['id' => $contentDownloadPaymentId]);
    sr_coupon_runtime_assert((string) ($contentDownloadPaymentAfterRevoke['status'] ?? '') === 'paid', 'content paid download access revoke should keep the payment record paid while the coupon item remains used.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $contentDownloadPaymentId, 'access_entitlement', 'reversed') === 1, 'content paid download access revoke should mark the access entitlement payment item reversed.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $contentDownloadPaymentId, 'coupon_redemption', 'none') === 1, 'content paid download access revoke should not reverse the coupon redemption payment item.');

    sr_content_grant_access_entitlement($pdo, 7, 77, 'content_file', 501, 'download', 'coupon', '', 'once', 'content.download:coupon:7:501:current');
    $pdo->exec("INSERT INTO sr_content_file_download_logs (content_id, file_id, account_id, download_type, charge_policy, asset_module, amount, asset_access_log_ids_json, coupon_redemption_id, coupon_dedupe_key, refund_status, refund_transaction_ids_json, refund_note, refunded_by_account_id, refunded_at, access_revoked_at, refund_policy_version, created_at) VALUES (77, 501, 7, 'paid', 'once', '', 0, '[]', 999, 'content.download:coupon:7:501:stale', '', '[]', '', NULL, NULL, NULL, 'content_file_download_refund_v1', '')");
    $staleDownloadLogId = (int) $pdo->lastInsertId();
    $staleRevokeResult = sr_content_refund_file_download($pdo, $staleDownloadLogId, 1, 'stale coupon access revoke');
    sr_coupon_runtime_assert(empty($staleRevokeResult['ok']), 'content paid download refund helper should fail closed when source-scope access does not match.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_content_access_entitlements WHERE account_id = 7 AND subject_type = 'content_file' AND subject_id = 501 AND access_kind = 'download' AND source_reference = 'content.download:coupon:7:501:current'")->fetchColumn() === 1, 'content paid download source-scope mismatch must leave the current entitlement intact.');

    $communityIssueId = sr_coupon_runtime_issue($pdo, 'community_priority', 'community_post', '9901', 7);
    $pdo->prepare(
        "INSERT INTO sr_community_boards
            (id, board_group_id, board_key, title, description, status, read_policy, write_policy, comment_policy, image_uploads_enabled, sort_order, created_at, updated_at)
         VALUES
            (9902, NULL, 'paid_board', 'Paid board', '', 'enabled', 'public', 'member', 'member', 1, 0, :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    foreach ([
        'paid_read_enabled' => ['1', 'bool'],
        'paid_read_asset_module' => ['point', 'string'],
        'paid_read_amount' => ['150', 'int'],
        'paid_read_settlement_currency' => ['KRW', 'string'],
        'paid_read_charge_policy' => ['once', 'string'],
        'paid_attachment_download_enabled' => ['1', 'bool'],
        'paid_attachment_download_asset_module' => ['point', 'string'],
        'paid_attachment_download_amount' => ['180', 'int'],
        'paid_attachment_download_settlement_currency' => ['KRW', 'string'],
        'paid_attachment_download_charge_policy' => ['once', 'string'],
    ] as $settingKey => $setting) {
        $pdo->prepare(
            "INSERT INTO sr_community_board_settings
                (board_id, setting_key, setting_value, value_type, created_at, updated_at)
             VALUES
                (9902, :setting_key, :setting_value, :value_type, :created_at, :updated_at)"
        )->execute([
            'setting_key' => $settingKey,
            'setting_value' => $setting[0],
            'value_type' => $setting[1],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    $pdo->prepare(
        "INSERT INTO sr_community_posts
            (id, board_id, status, title, created_at, updated_at)
         VALUES
            (9901, 9902, 'published', 'Paid post', :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $pdo->prepare(
        "INSERT INTO sr_community_attachments
            (id, post_id, uploader_account_id, original_name, stored_name, storage_path, storage_driver, storage_key, mime_type, size_bytes, checksum_sha256, status, created_at)
         VALUES
            (8801, 9901, 8, 'paid-attachment.txt', 'paid-attachment.txt', '', 'local', 'community/paid-attachment.txt', 'text/plain', 100, :checksum, 'active', :created_at)"
    )->execute([
        'checksum' => str_repeat('a', 64),
        'created_at' => $now,
    ]);
    $communityPaidReadConfig = [
        'asset_module' => 'point',
        'amount' => 150,
        'amounts' => ['point' => 150],
        'group_policies_json' => '',
        'policy_set_id' => 0,
        'charge_policy' => 'once',
    ];
    $communityResult = sr_community_try_paid_read_coupon_access($pdo, 7, ['id' => 9901, 'board_id' => 9902, 'title' => 'Paid post'], $communityPaidReadConfig, 'community.post:coupon:7:9901');
    sr_coupon_runtime_assert(!empty($communityResult['allowed']), 'community paid read fixture should allow coupon-backed access.');
    sr_coupon_runtime_assert(!empty($communityResult['processed']), 'community paid read fixture should process the first coupon-backed access.');
    sr_coupon_runtime_assert((string) ($communityResult['coupon_title'] ?? '') === 'community_priority', 'community paid read fixture should return the coupon title that backed access.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_point_transactions')->fetchColumn() === 0, 'community paid read fixture must not create point transactions when coupon takes priority.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_community_asset_logs')->fetchColumn() === 0, 'community paid read fixture must not create asset logs when coupon takes priority.');

    $communityIssue = sr_coupon_runtime_row($pdo, 'SELECT status, used_count FROM sr_coupon_issues WHERE id = :id', ['id' => $communityIssueId]);
    sr_coupon_runtime_assert((string) ($communityIssue['status'] ?? '') === 'used', 'community paid read fixture should mark the priority coupon issue used.');
    sr_coupon_runtime_assert((int) ($communityIssue['used_count'] ?? -1) === 1, 'community paid read fixture should increment the priority coupon issue once.');
    $communitySnapshotRow = sr_coupon_runtime_row(
        $pdo,
        "SELECT amount, currency_code, policy_summary, target_snapshot_json
         FROM sr_coupon_redemptions
         WHERE reference_module = 'community'
           AND reference_type = 'community.post'
           AND reference_id = '9901'
         LIMIT 1"
    );
    $communitySnapshot = json_decode((string) ($communitySnapshotRow['target_snapshot_json'] ?? ''), true);
    sr_coupon_runtime_assert((int) ($communitySnapshotRow['amount'] ?? -1) === 150, 'community paid read redemption should store the pricing amount snapshot.');
    sr_coupon_runtime_assert((string) ($communitySnapshotRow['currency_code'] ?? '') === 'KRW', 'community paid read redemption should store the pricing currency snapshot.');
    sr_coupon_runtime_assert((string) ($communitySnapshotRow['policy_summary'] ?? '') === '게시글 열람 150KRW', 'community paid read redemption should store the pricing policy summary.');
    sr_coupon_runtime_assert(is_array($communitySnapshot) && (string) ($communitySnapshot['target_type'] ?? '') === 'community_post' && (int) ($communitySnapshot['amount'] ?? -1) === 150, 'community paid read redemption should store a whitelisted target pricing snapshot.');
    $communityEntitlement = sr_coupon_runtime_row(
        $pdo,
        "SELECT source_kind, source_asset_module, source_charge_policy, source_reference
         FROM sr_community_access_entitlements
         WHERE account_id = 7
           AND subject_type = 'community.post'
           AND subject_id = 9901
           AND event_key = 'post_read'"
    );
    sr_coupon_runtime_assert((string) ($communityEntitlement['source_kind'] ?? '') === 'coupon', 'community paid read fixture should grant a coupon source entitlement.');
    sr_coupon_runtime_assert((string) ($communityEntitlement['source_asset_module'] ?? '') === '', 'community paid read fixture should not attach an asset module to coupon entitlement.');
    sr_coupon_runtime_assert((string) ($communityEntitlement['source_charge_policy'] ?? '') === 'once', 'community paid read fixture should preserve the paid read charge policy on coupon entitlement.');
    sr_coupon_runtime_assert((string) ($communityEntitlement['source_reference'] ?? '') === 'community.post:coupon:7:9901', 'community paid read fixture should store the coupon dedupe key on entitlement.');
    $communityReadPayment = sr_coupon_runtime_payment_record($pdo, 'community', 'community.post.read', '9901');
    $communityReadPaymentId = (int) ($communityReadPayment['id'] ?? 0);
    sr_coupon_runtime_assert($communityReadPaymentId > 0, 'community paid read fixture should record a common payment ledger row.');
    sr_coupon_runtime_assert((int) ($communityReadPayment['payable_amount'] ?? -1) === 150 && (int) ($communityReadPayment['settlement_amount'] ?? -1) === 0, 'community paid read payment ledger should separate payable amount from zero settlement.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_count($pdo, $communityReadPaymentId, 'coupon_redemption') === 1, 'community paid read payment ledger should include the coupon redemption item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_count($pdo, $communityReadPaymentId, 'access_entitlement') === 1, 'community paid read payment ledger should include the access entitlement item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_reference($pdo, $communityReadPaymentId, 'access_entitlement') === 'community.post:9901:post_read', 'community paid read payment ledger access item should not embed the account id.');
    $communityReadUnit = sr_coupon_runtime_row($pdo, 'SELECT id, board_id, post_title_snapshot, payment_type, settlement_kind, payable_amount, settlement_amount, asset_access_log_ids_json, coupon_redemption_id, refund_policy_version FROM sr_community_post_read_payment_logs WHERE post_id = 9901 LIMIT 1');
    $communityReadLogIds = json_decode((string) ($communityReadUnit['asset_access_log_ids_json'] ?? '[]'), true);
    sr_coupon_runtime_assert((int) ($communityReadUnit['board_id'] ?? 0) === 9902 && (string) ($communityReadUnit['post_title_snapshot'] ?? '') === 'Paid post', 'community paid read payment-unit should store post snapshots.');
    sr_coupon_runtime_assert((string) ($communityReadUnit['payment_type'] ?? '') === 'coupon_access', 'community paid read payment-unit should classify full access coupon payments.');
    sr_coupon_runtime_assert((string) ($communityReadUnit['settlement_kind'] ?? '') === 'paid_settled_zero', 'community paid read payment-unit should classify zero settlement.');
    sr_coupon_runtime_assert((int) ($communityReadUnit['payable_amount'] ?? -1) === 150 && (int) ($communityReadUnit['settlement_amount'] ?? -1) === 0, 'community paid read payment-unit should separate payable amount from zero settlement.');
    sr_coupon_runtime_assert(is_array($communityReadLogIds) && $communityReadLogIds === [], 'community paid read payment-unit should store an empty asset log list for full coupon payments.');
    sr_coupon_runtime_assert((int) ($communityReadUnit['coupon_redemption_id'] ?? 0) > 0, 'community paid read payment-unit should link the coupon redemption.');
    sr_coupon_runtime_assert((string) ($communityReadUnit['refund_policy_version'] ?? '') === 'community_post_read_refund_v1', 'community paid read payment-unit should stamp the refund policy version.');

    $communityRepeat = sr_community_try_paid_read_coupon_access($pdo, 7, ['id' => 9901, 'board_id' => 9902, 'title' => 'Paid post'], $communityPaidReadConfig, 'community.post:coupon:7:9901');
    sr_coupon_runtime_assert(!empty($communityRepeat['allowed']) && empty($communityRepeat['processed']) && !empty($communityRepeat['already_redeemed']), 'community paid read fixture should reuse existing coupon entitlement without another redemption.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_coupon_redemptions WHERE reference_module = 'community' AND reference_type = 'community.post' AND reference_id = '9901'")->fetchColumn() === 1, 'community paid read fixture should not create another coupon redemption for once access.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_community_post_read_payment_logs WHERE post_id = 9901')->fetchColumn() === 1, 'community paid read repeat should not create another payment-unit row for existing entitlement.');

    $alreadyEntitledCommunityIssueId = sr_coupon_runtime_issue($pdo, 'community_already_entitled', 'community_post', '9901', 7);
    $alreadyEntitledCommunity = sr_coupon_redeem_for_target($pdo, 7, 'community_post', '9901', [
        'dedupe_key' => 'community.post:coupon:7:9901:already-entitled',
        'reference_module' => 'community',
        'reference_type' => 'community.post',
        'reference_id' => '9901',
    ]);
    sr_coupon_runtime_assert(!empty($alreadyEntitledCommunity['allowed']) && empty($alreadyEntitledCommunity['processed']) && !empty($alreadyEntitledCommunity['already_entitled']), 'community already-entitled coupon attempt should return already_entitled without consuming a coupon.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_coupon_redemptions WHERE dedupe_key = 'community.post:coupon:7:9901:already-entitled'")->fetchColumn() === 0, 'community already-entitled coupon attempt should not create a redemption row.');
    sr_coupon_runtime_assert_issue_unused($pdo, $alreadyEntitledCommunityIssueId, 'community already-entitled coupon attempt');

    $communityRedemptionId = (int) ($communityReadUnit['coupon_redemption_id'] ?? 0);
    $communityRefund = sr_community_refund_post_read_payment($pdo, (int) ($communityReadUnit['id'] ?? 0), 1, 'community coupon read domain refund');
    sr_coupon_runtime_assert(!empty($communityRefund['ok']), 'community paid read payment-unit refund should handle full coupon payments.');
    sr_coupon_runtime_assert((string) sr_coupon_runtime_scalar($pdo, 'SELECT status FROM sr_coupon_redemptions WHERE id = :id', ['id' => $communityRedemptionId]) === 'refunded', 'community paid read payment-unit refund should refund the linked coupon redemption.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_community_access_entitlements WHERE account_id = 7 AND subject_type = 'community.post' AND subject_id = 9901 AND event_key = 'post_read'")->fetchColumn() === 0, 'community paid read payment-unit refund should remove coupon-backed post entitlement.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_point_transactions')->fetchColumn() === 0, 'community paid read fixture must still have no point transactions after coupon refund.');
    $communityReadPaymentAfterRefund = sr_coupon_runtime_row($pdo, 'SELECT status FROM sr_payment_records WHERE id = :id', ['id' => $communityReadPaymentId]);
    sr_coupon_runtime_assert((string) ($communityReadPaymentAfterRefund['status'] ?? '') === 'refunded', 'community paid read coupon refund should mark the payment record refunded.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $communityReadPaymentId, 'coupon_redemption', 'reversed') === 1, 'community paid read payment-unit refund should mark the coupon payment item reversed.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $communityReadPaymentId, 'access_entitlement', 'reversed') === 1, 'community paid read payment-unit refund should mark the access payment item reversed.');

    $attachmentIssueId = sr_coupon_runtime_issue($pdo, 'community_attachment_download', 'community_attachment', '8801', 7);
    $attachmentTargets = sr_community_coupon_target_search($pdo, 'community_attachment', 'paid-attachment', 5);
    sr_coupon_runtime_assert(isset($attachmentTargets[0]) && (string) ($attachmentTargets[0]['reference_id'] ?? '') === '8801', 'community_attachment coupon target search should return matching attachments.');
    $adminAttachmentTargets = sr_coupon_target_search($pdo, 'community_attachment', 'paid-attachment', 5);
    sr_coupon_runtime_assert(isset($adminAttachmentTargets[0]) && (string) ($adminAttachmentTargets[0]['reference_id'] ?? '') === '8801', 'coupon admin target search should return matching community attachments.');
    $attachmentHealth = sr_community_coupon_target_health($pdo, 'community_attachment', '8801');
    sr_coupon_runtime_assert((string) ($attachmentHealth['status'] ?? '') === 'ok', 'community_attachment coupon target health should accept active published attachments.');
    sr_coupon_runtime_assert(sr_community_coupon_target_admin_url('community_attachment', '8801') === '/admin/community/posts?field=attachment_id&q=8801', 'community_attachment coupon target admin URL should point to the community post admin search.');
    $attachmentChoices = sr_community_available_attachment_download_coupon_issues($pdo, 7, 8801);
    sr_coupon_runtime_assert(isset($attachmentChoices[0]) && (int) ($attachmentChoices[0]['id'] ?? 0) === $attachmentIssueId, 'community attachment download modal choices should include matching coupon issue.');

    $attachmentDownloadConfig = [
        'asset_module' => 'point',
        'amount' => 180,
        'amounts' => ['point' => 180],
        'group_policies_json' => '',
        'policy_set_id' => 0,
        'charge_policy' => 'once',
        'settlement_currency' => 'KRW',
    ];
    $attachmentResult = sr_community_try_attachment_download_coupon_access($pdo, 7, 8801, $attachmentDownloadConfig, 'community.attachment.download:coupon:7:8801', $attachmentIssueId);
    sr_coupon_runtime_assert(!empty($attachmentResult['allowed']) && !empty($attachmentResult['processed']), 'community attachment download fixture should process selected coupon-backed access.');
    sr_coupon_runtime_assert((string) ($attachmentResult['coupon_title'] ?? '') === 'community_attachment_download', 'community attachment download fixture should return the coupon title that backed access.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_community_asset_logs')->fetchColumn() === 0, 'community attachment download fixture must not create asset logs when selected coupon is applied.');
    $attachmentSnapshotRow = sr_coupon_runtime_row(
        $pdo,
        "SELECT amount, currency_code, policy_summary, target_snapshot_json
         FROM sr_coupon_redemptions
         WHERE reference_module = 'community'
           AND reference_type = 'community.attachment.download'
           AND reference_id = '8801'
         LIMIT 1"
    );
    $attachmentSnapshot = json_decode((string) ($attachmentSnapshotRow['target_snapshot_json'] ?? ''), true);
    sr_coupon_runtime_assert((int) ($attachmentSnapshotRow['amount'] ?? -1) === 180, 'community attachment download redemption should store the pricing amount snapshot.');
    sr_coupon_runtime_assert((string) ($attachmentSnapshotRow['currency_code'] ?? '') === 'KRW', 'community attachment download redemption should store the pricing currency snapshot.');
    sr_coupon_runtime_assert((string) ($attachmentSnapshotRow['policy_summary'] ?? '') === '첨부 다운로드 180KRW', 'community attachment download redemption should store the pricing policy summary.');
    sr_coupon_runtime_assert(is_array($attachmentSnapshot) && (string) ($attachmentSnapshot['target_type'] ?? '') === 'community_attachment' && (int) ($attachmentSnapshot['amount'] ?? -1) === 180, 'community attachment download redemption should store a whitelisted target pricing snapshot.');
    $attachmentEntitlement = sr_coupon_runtime_row(
        $pdo,
        "SELECT source_kind, source_asset_module, source_charge_policy, source_reference
         FROM sr_community_access_entitlements
         WHERE account_id = 7
           AND subject_type = 'community.attachment'
           AND subject_id = 8801
           AND event_key = 'attachment_download'"
    );
    sr_coupon_runtime_assert((string) ($attachmentEntitlement['source_kind'] ?? '') === 'coupon', 'community attachment download fixture should grant a coupon source entitlement.');
    sr_coupon_runtime_assert((string) ($attachmentEntitlement['source_asset_module'] ?? '') === '', 'community attachment download fixture should not attach an asset module to coupon entitlement.');
    sr_coupon_runtime_assert((string) ($attachmentEntitlement['source_charge_policy'] ?? '') === 'once', 'community attachment download fixture should preserve the paid attachment charge policy on coupon entitlement.');
    sr_coupon_runtime_assert((string) ($attachmentEntitlement['source_reference'] ?? '') === 'community.attachment.download:coupon:7:8801', 'community attachment download fixture should store the coupon dedupe key on entitlement.');
    $communityAttachmentPayment = sr_coupon_runtime_payment_record($pdo, 'community', 'community.attachment.download', '8801');
    $communityAttachmentPaymentId = (int) ($communityAttachmentPayment['id'] ?? 0);
    sr_coupon_runtime_assert($communityAttachmentPaymentId > 0, 'community attachment download fixture should record a common payment ledger row.');
    sr_coupon_runtime_assert((int) ($communityAttachmentPayment['payable_amount'] ?? -1) === 180 && (int) ($communityAttachmentPayment['settlement_amount'] ?? -1) === 0, 'community attachment download payment ledger should separate payable amount from zero settlement.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_count($pdo, $communityAttachmentPaymentId, 'coupon_redemption') === 1, 'community attachment download payment ledger should include the coupon redemption item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_count($pdo, $communityAttachmentPaymentId, 'access_entitlement') === 1, 'community attachment download payment ledger should include the access entitlement item.');

    $attachmentRedemptionId = (int) $pdo->query("SELECT id FROM sr_coupon_redemptions WHERE reference_module = 'community' AND reference_type = 'community.attachment.download' AND reference_id = '8801' LIMIT 1")->fetchColumn();
    $attachmentRefund = sr_coupon_refund_redemption($pdo, $attachmentRedemptionId, 1, 'community attachment coupon access revoke');
    sr_coupon_runtime_assert((int) ($attachmentRefund['revoked_access_count'] ?? -1) === 1, 'community attachment download fixture should revoke coupon access entitlement through refund helper.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_community_access_entitlements WHERE account_id = 7 AND subject_type = 'community.attachment' AND subject_id = 8801 AND event_key = 'attachment_download'")->fetchColumn() === 0, 'community attachment download fixture should remove coupon-backed attachment entitlement on refund.');
    $communityAttachmentPaymentAfterRefund = sr_coupon_runtime_row($pdo, 'SELECT status FROM sr_payment_records WHERE id = :id', ['id' => $communityAttachmentPaymentId]);
    sr_coupon_runtime_assert((string) ($communityAttachmentPaymentAfterRefund['status'] ?? '') === 'refunded', 'community attachment coupon refund should mark the payment record refunded.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $communityAttachmentPaymentId, 'coupon_redemption', 'reversed') === 1, 'community attachment coupon refund should mark the coupon payment item reversed.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $communityAttachmentPaymentId, 'access_entitlement', 'reversed') === 1, 'community attachment coupon refund should mark the access payment item reversed.');

    $pdo->prepare(
        "INSERT INTO sr_community_posts
            (id, board_id, status, title, created_at, updated_at)
         VALUES
            (9904, 9902, 'published', 'Asset paid post', :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $communityAssetOnlyConfig = $communityPaidReadConfig;
    $communityAssetOnlyConfig['amount'] = 80;
    $communityAssetOnlyConfig['amounts'] = ['point' => 80];
    $communityAssetOnlyResult = sr_community_run_asset_event($pdo, $communityAssetOnlyConfig, 7, 'post_read', 'community.post', 9904, 'use', 'community.post.read', true, '', true, true, false, null, [
        'board_id' => 9902,
        'post_title_snapshot' => 'Asset paid post',
    ]);
    sr_coupon_runtime_assert(!empty($communityAssetOnlyResult['allowed']) && !empty($communityAssetOnlyResult['processed']), 'community asset-only paid read fixture should charge assets.');
    $communityAssetOnlyUnit = sr_coupon_runtime_row($pdo, 'SELECT id, board_id, post_title_snapshot, payment_type, settlement_kind, payable_amount, settlement_amount, asset_access_log_ids_json, coupon_redemption_id, refund_policy_version FROM sr_community_post_read_payment_logs WHERE post_id = 9904 LIMIT 1');
    $communityAssetOnlyLogIds = json_decode((string) ($communityAssetOnlyUnit['asset_access_log_ids_json'] ?? '[]'), true);
    sr_coupon_runtime_assert((int) ($communityAssetOnlyUnit['board_id'] ?? 0) === 9902 && (string) ($communityAssetOnlyUnit['post_title_snapshot'] ?? '') === 'Asset paid post', 'community asset-only paid read payment-unit should store post snapshots.');
    sr_coupon_runtime_assert((string) ($communityAssetOnlyUnit['payment_type'] ?? '') === 'asset_only', 'community asset-only paid read payment-unit should classify asset payments.');
    sr_coupon_runtime_assert((string) ($communityAssetOnlyUnit['settlement_kind'] ?? '') === 'paid', 'community asset-only paid read payment-unit should classify paid settlement.');
    sr_coupon_runtime_assert((int) ($communityAssetOnlyUnit['payable_amount'] ?? -1) === 80 && (int) ($communityAssetOnlyUnit['settlement_amount'] ?? -1) === 80, 'community asset-only paid read payment-unit should store payable and settlement amounts.');
    sr_coupon_runtime_assert(is_array($communityAssetOnlyLogIds) && count($communityAssetOnlyLogIds) === 1, 'community asset-only paid read payment-unit should link one community asset log.');
    sr_coupon_runtime_assert((int) ($communityAssetOnlyUnit['coupon_redemption_id'] ?? 0) === 0, 'community asset-only paid read payment-unit should not link a coupon redemption.');
    sr_coupon_runtime_assert((string) ($communityAssetOnlyUnit['refund_policy_version'] ?? '') === 'community_post_read_refund_v1', 'community asset-only paid read payment-unit should stamp the refund policy version.');
    $communityAssetOnlyPayment = sr_coupon_runtime_payment_record($pdo, 'community', 'community.post.read', '9904');
    $communityAssetOnlyPaymentId = (int) ($communityAssetOnlyPayment['id'] ?? 0);
    $communityAssetOnlyRefund = sr_community_refund_post_read_payment($pdo, (int) ($communityAssetOnlyUnit['id'] ?? 0), 1, 'community asset read domain refund');
    sr_coupon_runtime_assert(!empty($communityAssetOnlyRefund['ok']), 'community asset-only paid read payment-unit refund should succeed.');
    sr_coupon_runtime_assert((string) sr_coupon_runtime_scalar($pdo, 'SELECT refund_status FROM sr_community_post_read_payment_logs WHERE id = :id', ['id' => (int) ($communityAssetOnlyUnit['id'] ?? 0)]) === 'refunded', 'community asset-only paid read payment-unit refund should stamp refunded.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_community_access_entitlements WHERE account_id = 7 AND subject_type = 'community.post' AND subject_id = 9904 AND event_key = 'post_read'")->fetchColumn() === 0, 'community asset-only paid read payment-unit refund should revoke post access.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $communityAssetOnlyPaymentId, 'asset_transaction', 'reversed') === 1, 'community asset-only paid read payment-unit refund should reverse the asset transaction item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $communityAssetOnlyPaymentId, 'asset_access_log', 'reversed') === 1, 'community asset-only paid read payment-unit refund should reverse the asset log item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $communityAssetOnlyPaymentId, 'access_entitlement', 'reversed') === 1, 'community asset-only paid read payment-unit refund should reverse the access item.');

    $assetOnlyPage = [
        'id' => 78,
        'title' => 'Asset paid content',
        'slug' => 'asset-paid-content',
        'asset_access_enabled' => 1,
        'asset_module' => 'point',
        'asset_access_amount' => 90,
        'asset_access_amounts_json' => '{"point":90}',
        'asset_access_group_policies_json' => '',
        'asset_access_policy_set_id' => 0,
        'asset_access_settlement_currency' => 'KRW',
        'asset_charge_policy' => 'once',
    ];
    $assetOnlyResult = sr_content_charge_view_access($pdo, $assetOnlyPage, 7, true, '', 0, true, true, false);
    sr_coupon_runtime_assert(!empty($assetOnlyResult['allowed']) && !empty($assetOnlyResult['charged']), 'content asset-only paid view fixture should charge assets.');
    $assetOnlyUnit = sr_coupon_runtime_row($pdo, 'SELECT id, payment_type, settlement_kind, payable_amount, settlement_amount, asset_access_log_ids_json, coupon_redemption_id FROM sr_content_view_payment_logs WHERE content_id = 78 LIMIT 1');
    $assetOnlyLogIds = json_decode((string) ($assetOnlyUnit['asset_access_log_ids_json'] ?? '[]'), true);
    sr_coupon_runtime_assert((string) ($assetOnlyUnit['payment_type'] ?? '') === 'asset_only', 'content asset-only paid view payment-unit should classify asset payments.');
    sr_coupon_runtime_assert((string) ($assetOnlyUnit['settlement_kind'] ?? '') === 'paid', 'content asset-only paid view payment-unit should classify paid settlement.');
    sr_coupon_runtime_assert((int) ($assetOnlyUnit['payable_amount'] ?? -1) === 90 && (int) ($assetOnlyUnit['settlement_amount'] ?? -1) === 90, 'content asset-only paid view payment-unit should store payable and settlement amounts.');
    sr_coupon_runtime_assert(is_array($assetOnlyLogIds) && count($assetOnlyLogIds) === 1, 'content asset-only paid view payment-unit should link one asset access log.');
    sr_coupon_runtime_assert((int) ($assetOnlyUnit['coupon_redemption_id'] ?? 0) === 0, 'content asset-only paid view payment-unit should not link a coupon redemption.');
    $assetOnlyPayment = sr_coupon_runtime_payment_record($pdo, 'content', 'content.view', '78');
    $assetOnlyPaymentId = (int) ($assetOnlyPayment['id'] ?? 0);
    $assetOnlyRefund = sr_content_refund_view_payment($pdo, (int) ($assetOnlyUnit['id'] ?? 0), 1, 'content asset view domain refund');
    sr_coupon_runtime_assert(!empty($assetOnlyRefund['ok']), 'content asset-only paid view payment-unit refund should succeed.');
    sr_coupon_runtime_assert((string) sr_coupon_runtime_scalar($pdo, 'SELECT refund_status FROM sr_content_view_payment_logs WHERE id = :id', ['id' => (int) ($assetOnlyUnit['id'] ?? 0)]) === 'refunded', 'content asset-only paid view payment-unit refund should stamp refunded.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_content_access_entitlements WHERE account_id = 7 AND content_id = 78 AND subject_type = 'content' AND subject_id = 78 AND access_kind = 'view'")->fetchColumn() === 0, 'content asset-only paid view payment-unit refund should revoke content access.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $assetOnlyPaymentId, 'asset_transaction', 'reversed') === 1, 'content asset-only paid view payment-unit refund should reverse the asset transaction item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $assetOnlyPaymentId, 'asset_access_log', 'reversed') === 1, 'content asset-only paid view payment-unit refund should reverse the asset log item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $assetOnlyPaymentId, 'access_entitlement', 'reversed') === 1, 'content asset-only paid view payment-unit refund should reverse the access item.');

    $pdo->prepare(
        "INSERT INTO sr_content_items
            (id, title, slug, status, asset_access_enabled, asset_module, asset_access_amount, asset_access_amounts_json, asset_access_settlement_currency, asset_charge_policy, updated_at)
         VALUES
            (7801, 'Mixed paid content', 'mixed-paid-content', 'published', 1, 'point', 100, '{\"point\":100}', 'KRW', 'once', :updated_at)"
    )->execute(['updated_at' => $now]);
    $pdo->prepare(
        "INSERT INTO sr_coupon_definitions
            (coupon_key, title, description, status, coupon_type, discount_amount, discount_percent, discount_currency_code, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES
            ('content_fixed_mixed', 'Content fixed mixed', '', 'active', 'fixed_discount', 40, 0, 'KRW', 'content', '7801', 'none', 1, NULL, NULL, :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $contentMixedDefinitionId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO sr_coupon_issues
            (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, issued_at, expires_at, used_count, created_at, updated_at)
         VALUES
            (:definition_id, 7, 'active', 'fixture', NULL, :issued_at, NULL, 0, :created_at, :updated_at)"
    )->execute([
        'definition_id' => $contentMixedDefinitionId,
        'issued_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $contentMixedIssueId = (int) $pdo->lastInsertId();
    $mixedPage = [
        'id' => 7801,
        'asset_access_enabled' => 1,
        'asset_module' => 'point',
        'asset_access_amount' => 100,
        'asset_access_amounts_json' => '{"point":100}',
        'asset_access_group_policies_json' => '',
        'asset_access_policy_set_id' => 0,
        'asset_access_settlement_currency' => 'KRW',
        'asset_charge_policy' => 'once',
    ];
    $mixedContentResult = sr_content_charge_view_access($pdo, $mixedPage, 7, true, '', $contentMixedIssueId, true, true, false);
    sr_coupon_runtime_assert(!empty($mixedContentResult['allowed']) && !empty($mixedContentResult['charged']), 'content mixed coupon fixture should allow access and charge remaining assets.');
    sr_coupon_runtime_assert(!empty($mixedContentResult['coupon_used']), 'content mixed coupon fixture should report coupon_used.');
    sr_coupon_runtime_assert((int) ($mixedContentResult['amount'] ?? -1) === 60, 'content mixed coupon fixture should charge only the remaining settlement amount.');
    sr_coupon_runtime_assert((int) ($mixedContentResult['coupon_discount_amount'] ?? -1) === 40, 'content mixed coupon fixture should report the fixed coupon discount amount.');
    $contentMixedIssue = sr_coupon_runtime_row($pdo, 'SELECT status, used_count FROM sr_coupon_issues WHERE id = :id', ['id' => $contentMixedIssueId]);
    sr_coupon_runtime_assert((string) ($contentMixedIssue['status'] ?? '') === 'used' && (int) ($contentMixedIssue['used_count'] ?? -1) === 1, 'content mixed coupon fixture should consume the discount coupon once.');
    $contentMixedTransaction = sr_coupon_runtime_row($pdo, "SELECT amount, reference_type, reference_id FROM sr_point_transactions WHERE reference_type = 'content.view' AND reference_id = '7801' LIMIT 1");
    sr_coupon_runtime_assert((int) ($contentMixedTransaction['amount'] ?? 0) === -60, 'content mixed coupon fixture should create a point transaction for the remaining amount.');
    $contentMixedAssetLog = sr_coupon_runtime_row($pdo, "SELECT amount, settlement_amount, settlement_currency FROM sr_content_asset_access_logs WHERE content_id = 7801 LIMIT 1");
    sr_coupon_runtime_assert((int) ($contentMixedAssetLog['amount'] ?? 0) === 60 && (int) ($contentMixedAssetLog['settlement_amount'] ?? 0) === 60, 'content mixed coupon fixture should store remaining asset and settlement amounts.');
    $contentMixedRedemption = sr_coupon_runtime_row($pdo, "SELECT target_snapshot_json FROM sr_coupon_redemptions WHERE reference_type = 'content.view' AND reference_id = '7801' LIMIT 1");
    $contentMixedSnapshot = json_decode((string) ($contentMixedRedemption['target_snapshot_json'] ?? ''), true);
    sr_coupon_runtime_assert(is_array($contentMixedSnapshot) && (int) ($contentMixedSnapshot['discount_amount'] ?? -1) === 40 && (int) ($contentMixedSnapshot['remaining_amount'] ?? -1) === 60, 'content mixed coupon fixture should store discount and remaining amounts in the redemption snapshot.');
    $contentMixedPayment = sr_coupon_runtime_payment_record($pdo, 'content', 'content.view', '7801');
    $contentMixedPaymentId = (int) ($contentMixedPayment['id'] ?? 0);
    sr_coupon_runtime_assert($contentMixedPaymentId > 0, 'content mixed coupon fixture should record one common payment ledger row.');
    sr_coupon_runtime_assert((int) ($contentMixedPayment['payable_amount'] ?? -1) === 100 && (int) ($contentMixedPayment['settlement_amount'] ?? -1) === 60, 'content mixed coupon payment ledger should preserve payable and remaining settlement amounts.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_count($pdo, $contentMixedPaymentId, 'coupon_redemption') === 1, 'content mixed coupon payment ledger should include coupon redemption item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_count($pdo, $contentMixedPaymentId, 'asset_transaction') === 1, 'content mixed coupon payment ledger should include asset transaction item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_count($pdo, $contentMixedPaymentId, 'asset_access_log') === 1, 'content mixed coupon payment ledger should include content asset access log item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_count($pdo, $contentMixedPaymentId, 'access_entitlement') === 1, 'content mixed coupon payment ledger should include access entitlement item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_reference($pdo, $contentMixedPaymentId, 'access_entitlement') === 'content:7801:view', 'content mixed coupon payment ledger access item should not embed the account id.');
    $contentMixedUnit = sr_coupon_runtime_row($pdo, 'SELECT id, payment_type, settlement_kind, payable_amount, settlement_amount, asset_access_log_ids_json, coupon_redemption_id FROM sr_content_view_payment_logs WHERE content_id = 7801 LIMIT 1');
    $contentMixedLogIds = json_decode((string) ($contentMixedUnit['asset_access_log_ids_json'] ?? '[]'), true);
    sr_coupon_runtime_assert((string) ($contentMixedUnit['payment_type'] ?? '') === 'coupon_partial_discount_asset', 'content mixed coupon payment-unit should classify coupon plus asset payments.');
    sr_coupon_runtime_assert((string) ($contentMixedUnit['settlement_kind'] ?? '') === 'paid', 'content mixed coupon payment-unit should classify paid settlement.');
    sr_coupon_runtime_assert((int) ($contentMixedUnit['payable_amount'] ?? -1) === 100 && (int) ($contentMixedUnit['settlement_amount'] ?? -1) === 60, 'content mixed coupon payment-unit should preserve payable and remaining settlement amounts.');
    sr_coupon_runtime_assert(is_array($contentMixedLogIds) && count($contentMixedLogIds) === 1, 'content mixed coupon payment-unit should link the asset access log.');
    sr_coupon_runtime_assert((int) ($contentMixedUnit['coupon_redemption_id'] ?? 0) > 0, 'content mixed coupon payment-unit should link the coupon redemption.');
    $contentMixedPaymentLogId = (int) ($contentMixedUnit['id'] ?? 0);
    $contentMixedRedemptionId = (int) ($contentMixedUnit['coupon_redemption_id'] ?? 0);
    try {
        sr_coupon_refund_redemption($pdo, $contentMixedRedemptionId, 1, 'content mixed standalone refund');
        sr_coupon_runtime_assert(false, 'content mixed coupon redemption standalone refund should fail closed.');
    } catch (RuntimeException $exception) {
        sr_coupon_runtime_assert(str_contains($exception->getMessage(), '복합결제'), 'content mixed coupon standalone refund should direct admins to the domain payment refund.');
    }
    sr_coupon_runtime_assert((string) sr_coupon_runtime_scalar($pdo, 'SELECT status FROM sr_coupon_redemptions WHERE id = :id', ['id' => $contentMixedRedemptionId]) === 'redeemed', 'content mixed standalone refund failure should keep the coupon redemption active.');
    sr_coupon_runtime_assert((string) sr_coupon_runtime_scalar($pdo, 'SELECT refund_status FROM sr_content_view_payment_logs WHERE id = :id', ['id' => $contentMixedPaymentLogId]) === '', 'content mixed standalone refund failure should not stamp the payment-unit row.');

    $contentMixedRefund = sr_content_refund_view_payment($pdo, $contentMixedPaymentLogId, 1, 'content mixed domain refund');
    sr_coupon_runtime_assert(!empty($contentMixedRefund['ok']), 'content mixed payment-unit domain refund should succeed.');
    sr_coupon_runtime_assert((string) sr_coupon_runtime_scalar($pdo, 'SELECT status FROM sr_coupon_redemptions WHERE id = :id', ['id' => $contentMixedRedemptionId]) === 'refunded', 'content mixed domain refund should refund the linked coupon redemption.');
    sr_coupon_runtime_assert((string) sr_coupon_runtime_scalar($pdo, 'SELECT refund_status FROM sr_content_view_payment_logs WHERE id = :id', ['id' => $contentMixedPaymentLogId]) === 'refunded', 'content mixed domain refund should stamp the payment-unit row refunded.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_content_access_entitlements WHERE account_id = 7 AND content_id = 7801 AND subject_type = 'content' AND subject_id = 7801 AND access_kind = 'view'")->fetchColumn() === 0, 'content mixed domain refund should revoke the asset-source content entitlement.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $contentMixedPaymentId, 'coupon_redemption', 'reversed') === 1, 'content mixed domain refund should reverse the coupon payment item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $contentMixedPaymentId, 'asset_transaction', 'reversed') === 1, 'content mixed domain refund should reverse the asset transaction payment item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $contentMixedPaymentId, 'asset_access_log', 'reversed') === 1, 'content mixed domain refund should reverse the asset access log payment item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $contentMixedPaymentId, 'access_entitlement', 'reversed') === 1, 'content mixed domain refund should reverse the access entitlement payment item.');
    sr_coupon_runtime_assert((string) sr_coupon_runtime_scalar($pdo, 'SELECT status FROM sr_payment_records WHERE id = :id', ['id' => $contentMixedPaymentId]) === 'refunded', 'content mixed domain refund should mark the payment record refunded.');

    $pdo->prepare(
        "INSERT INTO sr_community_posts
            (id, board_id, status, title, created_at, updated_at)
         VALUES
            (9903, 9902, 'published', 'Mixed paid post', :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $pdo->prepare(
        "INSERT INTO sr_coupon_definitions
            (coupon_key, title, description, status, coupon_type, discount_amount, discount_percent, discount_currency_code, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES
            ('community_percent_mixed', 'Community percent mixed', '', 'active', 'percent_discount', 0, 50, '', 'community_post', '9903', 'none', 1, NULL, NULL, :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $communityMixedDefinitionId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO sr_coupon_issues
            (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, issued_at, expires_at, used_count, created_at, updated_at)
         VALUES
            (:definition_id, 7, 'active', 'fixture', NULL, :issued_at, NULL, 0, :created_at, :updated_at)"
    )->execute([
        'definition_id' => $communityMixedDefinitionId,
        'issued_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $communityMixedIssueId = (int) $pdo->lastInsertId();
    $pdo->beginTransaction();
    $communityMixedCoupon = sr_community_try_paid_read_coupon_access($pdo, 7, ['id' => 9903, 'board_id' => 9902, 'title' => 'Mixed paid post'], $communityPaidReadConfig, 'community.post:coupon:7:9903', $communityMixedIssueId);
    $communityMixedRemaining = max(0, (int) ($communityMixedCoupon['remaining_amount'] ?? 0));
    $communityMixedResult = !empty($communityMixedCoupon['allowed'])
        ? sr_community_run_asset_event($pdo, $communityPaidReadConfig, 7, 'post_read', 'community.post', 9903, 'use', 'community.post.read', true, '', true, true, false, $communityMixedRemaining, [
            'coupon_result' => $communityMixedCoupon,
            'payable_amount' => $communityMixedRemaining + max(0, (int) ($communityMixedCoupon['discount_amount'] ?? 0)),
            'board_id' => 9902,
            'post_title_snapshot' => 'Mixed paid post',
        ])
        : ['allowed' => false, 'processed' => false];
    if (!empty($communityMixedResult['allowed'])) {
        $pdo->commit();
    } else {
        $pdo->rollBack();
    }
    sr_coupon_runtime_assert(!empty($communityMixedCoupon['allowed']) && !empty($communityMixedCoupon['processed']), 'community mixed coupon fixture should consume the discount coupon.');
    sr_coupon_runtime_assert($communityMixedRemaining === 75, 'community mixed coupon fixture should calculate the percent discount remaining amount.');
    sr_coupon_runtime_assert(!empty($communityMixedResult['allowed']) && !empty($communityMixedResult['processed']), 'community mixed coupon fixture should charge the remaining assets.');
    $communityMixedLog = sr_coupon_runtime_row($pdo, "SELECT amount, settlement_amount FROM sr_community_asset_logs WHERE subject_type = 'community.post' AND subject_id = 9903 LIMIT 1");
    sr_coupon_runtime_assert((int) ($communityMixedLog['amount'] ?? 0) === 75 && (int) ($communityMixedLog['settlement_amount'] ?? 0) === 75, 'community mixed coupon fixture should store the remaining charge in the asset log.');
    $communityMixedRedemption = sr_coupon_runtime_row($pdo, "SELECT target_snapshot_json FROM sr_coupon_redemptions WHERE reference_type = 'community.post' AND reference_id = '9903' LIMIT 1");
    $communityMixedSnapshot = json_decode((string) ($communityMixedRedemption['target_snapshot_json'] ?? ''), true);
    sr_coupon_runtime_assert(is_array($communityMixedSnapshot) && (int) ($communityMixedSnapshot['discount_amount'] ?? -1) === 75 && (int) ($communityMixedSnapshot['remaining_amount'] ?? -1) === 75, 'community mixed coupon fixture should store percent discount and remaining amounts in the redemption snapshot.');
    $communityMixedPayment = sr_coupon_runtime_payment_record($pdo, 'community', 'community.post.read', '9903');
    $communityMixedPaymentId = (int) ($communityMixedPayment['id'] ?? 0);
    sr_coupon_runtime_assert($communityMixedPaymentId > 0, 'community mixed coupon fixture should record one common payment ledger row.');
    sr_coupon_runtime_assert((int) ($communityMixedPayment['payable_amount'] ?? -1) === 150 && (int) ($communityMixedPayment['settlement_amount'] ?? -1) === 75, 'community mixed coupon payment ledger should preserve payable and remaining settlement amounts.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_count($pdo, $communityMixedPaymentId, 'coupon_redemption') === 1, 'community mixed coupon payment ledger should include coupon redemption item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_count($pdo, $communityMixedPaymentId, 'asset_transaction') === 1, 'community mixed coupon payment ledger should include asset transaction item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_count($pdo, $communityMixedPaymentId, 'asset_access_log') === 1, 'community mixed coupon payment ledger should include community asset log item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_count($pdo, $communityMixedPaymentId, 'access_entitlement') === 1, 'community mixed coupon payment ledger should include access entitlement item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_reference($pdo, $communityMixedPaymentId, 'access_entitlement') === 'community.post:9903:post_read', 'community mixed coupon payment ledger access item should not embed the account id.');
    $communityMixedUnit = sr_coupon_runtime_row($pdo, 'SELECT id, board_id, post_title_snapshot, payment_type, settlement_kind, payable_amount, settlement_amount, asset_access_log_ids_json, coupon_redemption_id, refund_policy_version FROM sr_community_post_read_payment_logs WHERE post_id = 9903 LIMIT 1');
    $communityMixedLogIds = json_decode((string) ($communityMixedUnit['asset_access_log_ids_json'] ?? '[]'), true);
    sr_coupon_runtime_assert((int) ($communityMixedUnit['board_id'] ?? 0) === 9902 && (string) ($communityMixedUnit['post_title_snapshot'] ?? '') === 'Mixed paid post', 'community mixed coupon payment-unit should store post snapshots.');
    sr_coupon_runtime_assert((string) ($communityMixedUnit['payment_type'] ?? '') === 'coupon_partial_discount_asset', 'community mixed coupon payment-unit should classify coupon plus asset payments.');
    sr_coupon_runtime_assert((string) ($communityMixedUnit['settlement_kind'] ?? '') === 'paid', 'community mixed coupon payment-unit should classify paid settlement.');
    sr_coupon_runtime_assert((int) ($communityMixedUnit['payable_amount'] ?? -1) === 150 && (int) ($communityMixedUnit['settlement_amount'] ?? -1) === 75, 'community mixed coupon payment-unit should preserve payable and remaining settlement amounts.');
    sr_coupon_runtime_assert(is_array($communityMixedLogIds) && count($communityMixedLogIds) === 1, 'community mixed coupon payment-unit should link the community asset log.');
    sr_coupon_runtime_assert((int) ($communityMixedUnit['coupon_redemption_id'] ?? 0) > 0, 'community mixed coupon payment-unit should link the coupon redemption.');
    sr_coupon_runtime_assert((string) ($communityMixedUnit['refund_policy_version'] ?? '') === 'community_post_read_refund_v1', 'community mixed coupon payment-unit should stamp the refund policy version.');
    $communityMixedPaymentLogId = (int) ($communityMixedUnit['id'] ?? 0);
    $communityMixedRedemptionId = (int) ($communityMixedUnit['coupon_redemption_id'] ?? 0);
    try {
        sr_coupon_refund_redemption($pdo, $communityMixedRedemptionId, 1, 'community mixed standalone refund');
        sr_coupon_runtime_assert(false, 'community mixed coupon redemption standalone refund should fail closed.');
    } catch (RuntimeException $exception) {
        sr_coupon_runtime_assert(str_contains($exception->getMessage(), '복합결제'), 'community mixed coupon standalone refund should direct admins to the domain payment refund.');
    }
    sr_coupon_runtime_assert((string) sr_coupon_runtime_scalar($pdo, 'SELECT status FROM sr_coupon_redemptions WHERE id = :id', ['id' => $communityMixedRedemptionId]) === 'redeemed', 'community mixed standalone refund failure should keep the coupon redemption active.');
    sr_coupon_runtime_assert((string) sr_coupon_runtime_scalar($pdo, 'SELECT refund_status FROM sr_community_post_read_payment_logs WHERE id = :id', ['id' => $communityMixedPaymentLogId]) === '', 'community mixed standalone refund failure should not stamp the payment-unit row.');

    $communityMixedRefund = sr_community_refund_post_read_payment($pdo, $communityMixedPaymentLogId, 1, 'community mixed domain refund');
    sr_coupon_runtime_assert(!empty($communityMixedRefund['ok']), 'community mixed payment-unit domain refund should succeed.');
    sr_coupon_runtime_assert((string) sr_coupon_runtime_scalar($pdo, 'SELECT status FROM sr_coupon_redemptions WHERE id = :id', ['id' => $communityMixedRedemptionId]) === 'refunded', 'community mixed domain refund should refund the linked coupon redemption.');
    sr_coupon_runtime_assert((string) sr_coupon_runtime_scalar($pdo, 'SELECT refund_status FROM sr_community_post_read_payment_logs WHERE id = :id', ['id' => $communityMixedPaymentLogId]) === 'refunded', 'community mixed domain refund should stamp the payment-unit row refunded.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_community_access_entitlements WHERE account_id = 7 AND subject_type = 'community.post' AND subject_id = 9903 AND event_key = 'post_read'")->fetchColumn() === 0, 'community mixed domain refund should revoke the asset-source post entitlement.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $communityMixedPaymentId, 'coupon_redemption', 'reversed') === 1, 'community mixed domain refund should reverse the coupon payment item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $communityMixedPaymentId, 'asset_transaction', 'reversed') === 1, 'community mixed domain refund should reverse the asset transaction payment item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $communityMixedPaymentId, 'asset_access_log', 'reversed') === 1, 'community mixed domain refund should reverse the asset access log payment item.');
    sr_coupon_runtime_assert(sr_coupon_runtime_payment_item_status_count($pdo, $communityMixedPaymentId, 'access_entitlement', 'reversed') === 1, 'community mixed domain refund should reverse the access entitlement payment item.');
    sr_coupon_runtime_assert((string) sr_coupon_runtime_scalar($pdo, 'SELECT status FROM sr_payment_records WHERE id = :id', ['id' => $communityMixedPaymentId]) === 'refunded', 'community mixed domain refund should mark the payment record refunded.');

    $contentModuleId = (int) sr_coupon_runtime_scalar($pdo, "SELECT id FROM sr_modules WHERE module_key = 'content' LIMIT 1");
    $communityModuleId = (int) sr_coupon_runtime_scalar($pdo, "SELECT id FROM sr_modules WHERE module_key = 'community' LIMIT 1");
    $pdo->prepare(
        "INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES (:module_id, 'multi_asset_payment_enabled', '0', 'bool', :created_at, :updated_at)"
    )->execute(['module_id' => $contentModuleId, 'created_at' => $now, 'updated_at' => $now]);
    $pdo->prepare(
        "INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES (:module_id, 'multi_asset_payment_enabled', '0', 'bool', :created_at, :updated_at)"
    )->execute(['module_id' => $communityModuleId, 'created_at' => $now, 'updated_at' => $now]);
    sr_clear_module_settings_cache('content');
    sr_clear_module_settings_cache('community');

    $pdo->prepare(
        "INSERT INTO sr_point_balances (account_id, balance, created_at, updated_at)
         VALUES (8, 40, :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $pdo->prepare(
        "INSERT INTO sr_reward_balances (account_id, balance, created_at, updated_at)
         VALUES (8, 100, :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);

    $pdo->prepare(
        "INSERT INTO sr_content_items
            (id, title, slug, status, asset_access_enabled, asset_module, asset_access_amount, asset_access_amounts_json, asset_access_settlement_currency, asset_charge_policy, updated_at)
         VALUES
            (8802, 'Multi asset disabled content', 'multi-asset-disabled-content', 'published', 1, 'point,reward', 100, '{\"point\":50,\"reward\":50}', 'KRW', 'once', :updated_at)"
    )->execute(['updated_at' => $now]);
    $pdo->prepare(
        "INSERT INTO sr_coupon_definitions
            (coupon_key, title, description, status, coupon_type, discount_amount, discount_percent, discount_currency_code, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES
            ('content_multi_asset_disabled', 'Content multi asset disabled', '', 'active', 'fixed_discount', 40, 0, 'KRW', 'content', '8802', 'none', 1, NULL, NULL, :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $contentDisabledDefinitionId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO sr_coupon_issues
            (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, issued_at, expires_at, used_count, created_at, updated_at)
         VALUES
            (:definition_id, 8, 'active', 'fixture', NULL, :issued_at, NULL, 0, :created_at, :updated_at)"
    )->execute([
        'definition_id' => $contentDisabledDefinitionId,
        'issued_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $contentDisabledIssueId = (int) $pdo->lastInsertId();
    $contentDisabledPage = [
        'id' => 8802,
        'asset_access_enabled' => 1,
        'asset_module' => 'point,reward',
        'asset_access_amount' => 100,
        'asset_access_amounts_json' => '{"point":50,"reward":50}',
        'asset_access_group_policies_json' => '',
        'asset_access_policy_set_id' => 0,
        'asset_access_settlement_currency' => 'KRW',
        'asset_charge_policy' => 'once',
    ];
    $contentDisabledResult = sr_content_charge_view_access($pdo, $contentDisabledPage, 8, true, '', $contentDisabledIssueId, true, true, false);
    sr_coupon_runtime_assert(
        empty($contentDisabledResult['allowed']) && str_contains((string) ($contentDisabledResult['message'] ?? ''), '하나만'),
        'content multi-asset-disabled policy should reject split asset settlement. message=' . (string) ($contentDisabledResult['message'] ?? '')
    );
    $contentDisabledIssue = sr_coupon_runtime_row($pdo, 'SELECT status, used_count FROM sr_coupon_issues WHERE id = :id', ['id' => $contentDisabledIssueId]);
    sr_coupon_runtime_assert((string) ($contentDisabledIssue['status'] ?? '') === 'active' && (int) ($contentDisabledIssue['used_count'] ?? -1) === 0, 'content multi-asset-disabled rejection should roll back the coupon issue.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_coupon_redemptions WHERE reference_module = 'content' AND reference_type = 'content.view' AND reference_id = '8802'")->fetchColumn() === 0, 'content multi-asset-disabled rejection should roll back the coupon redemption row.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_point_transactions WHERE account_id = 8 AND reference_type = 'content.view' AND reference_id = '8802'")->fetchColumn() === 0, 'content multi-asset-disabled rejection should not create point transactions.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_reward_transactions WHERE account_id = 8 AND reference_type = 'content.view' AND reference_id = '8802'")->fetchColumn() === 0, 'content multi-asset-disabled rejection should not create reward transactions.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_content_view_payment_logs WHERE content_id = 8802')->fetchColumn() === 0, 'content multi-asset-disabled rejection should not create a payment-unit row.');

    $pdo->prepare(
        "INSERT INTO sr_community_posts
            (id, board_id, status, title, created_at, updated_at)
         VALUES
            (9905, 9902, 'published', 'Multi asset disabled post', :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $pdo->prepare(
        "INSERT INTO sr_coupon_definitions
            (coupon_key, title, description, status, coupon_type, discount_amount, discount_percent, discount_currency_code, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES
            ('community_multi_asset_disabled', 'Community multi asset disabled', '', 'active', 'fixed_discount', 40, 0, 'KRW', 'community_post', '9905', 'none', 1, NULL, NULL, :created_at, :updated_at)"
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $communityDisabledDefinitionId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO sr_coupon_issues
            (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, issued_at, expires_at, used_count, created_at, updated_at)
         VALUES
            (:definition_id, 8, 'active', 'fixture', NULL, :issued_at, NULL, 0, :created_at, :updated_at)"
    )->execute([
        'definition_id' => $communityDisabledDefinitionId,
        'issued_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $communityDisabledIssueId = (int) $pdo->lastInsertId();
    $communityDisabledConfig = $communityPaidReadConfig;
    $communityDisabledConfig['asset_module'] = 'point,reward';
    $communityDisabledConfig['asset_modules'] = ['point', 'reward'];
    $communityDisabledConfig['amount'] = 100;
    $communityDisabledConfig['amounts'] = ['point' => 50, 'reward' => 50];
    $pdo->beginTransaction();
    $communityDisabledCoupon = sr_community_try_paid_read_coupon_access($pdo, 8, ['id' => 9905, 'board_id' => 9902, 'title' => 'Multi asset disabled post'], $communityDisabledConfig, 'community.post:coupon:8:9905', $communityDisabledIssueId);
    $communityDisabledRemaining = max(0, (int) ($communityDisabledCoupon['remaining_amount'] ?? 0));
    $communityDisabledResult = !empty($communityDisabledCoupon['allowed'])
        ? sr_community_run_asset_event($pdo, $communityDisabledConfig, 8, 'post_read', 'community.post', 9905, 'use', 'community.post.read', true, '', true, true, false, $communityDisabledRemaining, [
            'coupon_result' => $communityDisabledCoupon,
            'payable_amount' => $communityDisabledRemaining + max(0, (int) ($communityDisabledCoupon['discount_amount'] ?? 0)),
            'board_id' => 9902,
            'post_title_snapshot' => 'Multi asset disabled post',
        ])
        : ['allowed' => false, 'processed' => false];
    if (!empty($communityDisabledResult['allowed'])) {
        $pdo->commit();
    } else {
        $pdo->rollBack();
    }
    sr_coupon_runtime_assert(!empty($communityDisabledCoupon['allowed']) && !empty($communityDisabledCoupon['processed']), 'community multi-asset-disabled fixture should reach the mixed coupon stage.');
    sr_coupon_runtime_assert(
        empty($communityDisabledResult['allowed']) && str_contains((string) ($communityDisabledResult['message'] ?? ''), '하나만'),
        'community multi-asset-disabled policy should reject split asset settlement. message=' . (string) ($communityDisabledResult['message'] ?? '')
    );
    $communityDisabledIssue = sr_coupon_runtime_row($pdo, 'SELECT status, used_count FROM sr_coupon_issues WHERE id = :id', ['id' => $communityDisabledIssueId]);
    sr_coupon_runtime_assert((string) ($communityDisabledIssue['status'] ?? '') === 'active' && (int) ($communityDisabledIssue['used_count'] ?? -1) === 0, 'community multi-asset-disabled rejection should roll back the coupon issue.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_coupon_redemptions WHERE reference_module = 'community' AND reference_type = 'community.post' AND reference_id = '9905'")->fetchColumn() === 0, 'community multi-asset-disabled rejection should roll back the coupon redemption row.');
    sr_coupon_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_community_asset_logs WHERE account_id = 8 AND subject_type = 'community.post' AND subject_id = 9905")->fetchColumn() === 0, 'community multi-asset-disabled rejection should not create community asset logs.');
    sr_coupon_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_community_post_read_payment_logs WHERE post_id = 9905')->fetchColumn() === 0, 'community multi-asset-disabled rejection should not create a payment-unit row.');
}

sr_coupon_runtime_check_model_decision();
sr_coupon_runtime_discount_schema_detection_fixture();
sr_coupon_runtime_notification_settings_fixture();
sr_coupon_runtime_fixture();
sr_coupon_runtime_partial_failure_fixture();

if ($errors !== []) {
    fwrite(STDERR, "coupon redemption runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "coupon redemption runtime checks completed.\n";
