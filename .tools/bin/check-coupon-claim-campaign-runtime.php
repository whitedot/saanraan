#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/helpers.php';
require_once $root . '/modules/embed_manager/helpers.php';
require_once $root . '/modules/coupon/helpers.php';

$errors = [];

function sr_coupon_claim_runtime_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

function sr_coupon_claim_runtime_row(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? $row : [];
}

function sr_coupon_claim_runtime_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE sr_modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_key TEXT NOT NULL,
        status TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_module_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_id INTEGER NOT NULL,
        setting_key TEXT NOT NULL,
        setting_value TEXT NOT NULL,
        value_type TEXT NOT NULL DEFAULT 'string'
    )");
    $pdo->exec("CREATE TABLE sr_site_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        setting_key TEXT NOT NULL,
        setting_value TEXT NOT NULL,
        value_type TEXT NOT NULL DEFAULT 'string'
    )");
    $pdo->exec("INSERT INTO sr_modules (module_key, status) VALUES ('coupon', 'enabled'), ('point', 'enabled'), ('reward', 'enabled')");
    $pdo->exec("CREATE TABLE sr_member_accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL DEFAULT '',
        login_id TEXT NOT NULL DEFAULT '',
        display_name TEXT NOT NULL DEFAULT ''
    )");
    $pdo->exec("INSERT INTO sr_member_accounts (id, email, login_id, display_name) VALUES
        (7, 'member7@example.test', 'member7', '회원7'),
        (8, 'member8@example.test', 'member8', '회원8'),
        (10, 'member10@example.test', 'member10', '회원10')");
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
        dedupe_key TEXT NOT NULL UNIQUE,
        status TEXT NOT NULL DEFAULT 'redeemed',
        redeemed_at TEXT NOT NULL,
        refunded_at TEXT,
        refunded_by_account_id INTEGER,
        refund_note TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_coupon_claim_campaigns (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_key TEXT NOT NULL UNIQUE,
        coupon_definition_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        status TEXT NOT NULL DEFAULT 'draft',
        claim_type TEXT NOT NULL DEFAULT 'free',
        price_amount INTEGER,
        price_currency_code TEXT NOT NULL DEFAULT '',
        allowed_asset_modules_json TEXT,
        starts_at TEXT,
        ends_at TEXT,
        issue_expires_in_days INTEGER,
        issue_expires_at TEXT,
        total_claim_limit INTEGER,
        per_account_limit INTEGER NOT NULL DEFAULT 1,
        visibility TEXT NOT NULL DEFAULT 'hidden',
        exposure_surfaces_json TEXT NOT NULL,
        login_required INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE sr_coupon_claim_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id INTEGER NOT NULL,
        coupon_definition_id INTEGER NOT NULL,
        account_id INTEGER NOT NULL,
        coupon_issue_id INTEGER,
        claim_source TEXT NOT NULL DEFAULT 'coupon_zone',
        source_context_json TEXT NOT NULL,
        payment_reference_module TEXT NOT NULL DEFAULT '',
        payment_reference_type TEXT NOT NULL DEFAULT '',
        payment_reference_id TEXT NOT NULL DEFAULT '',
        asset_reference_module TEXT NOT NULL DEFAULT '',
        asset_reference_type TEXT NOT NULL DEFAULT '',
        asset_reference_id TEXT NOT NULL DEFAULT '',
        dedupe_key TEXT NOT NULL,
        dedupe_hash TEXT NOT NULL,
        occupying_account_id INTEGER,
        status TEXT NOT NULL DEFAULT 'reserved',
        reserved_until TEXT,
        failure_code TEXT NOT NULL DEFAULT '',
        failure_message TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL,
        issued_at TEXT,
        updated_at TEXT NOT NULL,
        UNIQUE(campaign_id, dedupe_hash),
        UNIQUE(coupon_issue_id),
        UNIQUE(campaign_id, occupying_account_id)
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
        source_expires_at TEXT NOT NULL,
        created_at TEXT NOT NULL
    )");
}

function sr_coupon_claim_runtime_static_contract(): void
{
    $helpers = (string) file_get_contents('modules/coupon/helpers.php');
    $moduleGuide = (string) file_get_contents('docs/module-guide.md');
    $verificationStatus = (string) file_get_contents('docs/verification-status.md');

    sr_coupon_claim_runtime_assert(str_contains($helpers, 'function sr_coupon_claim_campaign_by_key(PDO $pdo, string $campaignKey, bool $forUpdate = false)'), 'claim campaign lookup must expose an explicit FOR UPDATE option.');
    sr_coupon_claim_runtime_assert(str_contains($helpers, "LIMIT 1' . (\$forUpdate ? sr_coupon_for_update_clause(\$pdo) : '')"), 'claim campaign lookup must append FOR UPDATE when requested.');
    sr_coupon_claim_runtime_assert(str_contains($helpers, 'sr_coupon_claim_campaign_by_key($pdo, $campaignKey, true)'), 'claim helpers must lock the campaign row before claim limit checks.');
    sr_coupon_claim_runtime_assert(str_contains($helpers, 'function sr_coupon_public_claim_intent_token_matches(int $campaignId, int $accountId, string $token): bool'), 'public claim must expose a session-bound intent token verifier.');
    sr_coupon_claim_runtime_assert(str_contains($helpers, 'sr_coupon_public_claim_intent_key($campaignId, $accountId)'), 'public claim intent token storage must be bound to the current account and campaign.');
    sr_coupon_claim_runtime_assert(str_contains((string) file_get_contents('modules/coupon/actions/coupons.php'), 'sr_coupon_public_claim_intent_token_matches'), 'public coupon claim POST must validate the session-bound intent token.');
    sr_coupon_claim_runtime_assert(str_contains($moduleGuide, 'campaign row를 `FOR UPDATE`로 잠근 뒤 발급 한도 검증'), 'module guide must document campaign row locking for coupon claims.');
    sr_coupon_claim_runtime_assert(str_contains($verificationStatus, 'campaign row `FOR UPDATE` 잠금 아래에서 한도 검증과 점유'), 'verification status must describe the claim campaign lock contract check.');
}

function sr_coupon_claim_runtime_intent_token_fixture(): void
{
    $_SESSION['sr_coupon_claim_intents'] = [];
    $first = sr_coupon_public_claim_intent_token(11, 7);
    $same = sr_coupon_public_claim_intent_token(11, 7);
    $otherAccount = sr_coupon_public_claim_intent_token(11, 8);

    sr_coupon_claim_runtime_assert($first !== '' && $first === $same, 'public claim intent token should be stable for the same account and campaign.');
    sr_coupon_claim_runtime_assert($otherAccount !== '' && $otherAccount !== $first, 'public claim intent token should be account-bound.');
    sr_coupon_claim_runtime_assert(sr_coupon_public_claim_intent_token_matches(11, 7, $first), 'public claim intent verifier should accept the current account token.');
    sr_coupon_claim_runtime_assert(!sr_coupon_public_claim_intent_token_matches(11, 8, $first), 'public claim intent verifier should reject another account token.');

    $rotated = sr_coupon_public_rotate_claim_intent_token(11, 7);
    sr_coupon_claim_runtime_assert($rotated !== '' && $rotated !== $first, 'public claim intent token should rotate after successful claim.');
    sr_coupon_claim_runtime_assert(!sr_coupon_public_claim_intent_token_matches(11, 7, $first), 'public claim intent verifier should reject a rotated old token.');
    sr_coupon_claim_runtime_assert(sr_coupon_public_claim_intent_token_matches(11, 7, $rotated), 'public claim intent verifier should accept the rotated token.');
    unset($_SESSION['sr_coupon_claim_intents']);
}

function sr_coupon_claim_runtime_definition(PDO $pdo, string $couponKey): int
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        "INSERT INTO sr_coupon_definitions
            (coupon_key, title, description, status, coupon_type, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES
            (:coupon_key, :title, '', 'active', 'access', 'all', '', 'none', 1, NULL, NULL, :created_at, :updated_at)"
    );
    $stmt->execute([
        'coupon_key' => $couponKey,
        'title' => $couponKey,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_coupon_claim_runtime_fixture(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_coupon_claim_runtime_schema($pdo);

    $definitionId = sr_coupon_claim_runtime_definition($pdo, 'claim_free');
    $campaignId = sr_coupon_create_claim_campaign($pdo, [
        'campaign_key' => 'claim_free',
        'coupon_definition_id' => $definitionId,
        'title' => 'Free claim',
        'status' => 'active',
        'claim_type' => 'free',
        'total_claim_limit' => 2,
        'per_account_limit' => 1,
        'visibility' => 'public',
        'exposure_surfaces' => ['coupon_zone', 'content_embed'],
        'login_required' => 1,
    ]);
    sr_coupon_claim_runtime_assert($campaignId > 0, 'claim campaign fixture should create campaign.');

    $first = sr_coupon_claim_free_campaign($pdo, 'claim_free', 7, 'intent-a');
    sr_coupon_claim_runtime_assert(!empty($first['claimed']) && empty($first['already_claimed']), 'first free claim should issue a coupon.');
    $again = sr_coupon_claim_free_campaign($pdo, 'claim_free', 7, 'intent-a');
    sr_coupon_claim_runtime_assert(!empty($again['already_claimed']) && (int) $again['coupon_issue_id'] === (int) $first['coupon_issue_id'], 'same intent token should return the existing issued coupon.');
    sr_coupon_claim_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_coupon_claim_logs')->fetchColumn() === 1, 'same intent token must not create another claim log.');
    sr_coupon_claim_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_coupon_issues')->fetchColumn() === 1, 'same intent token must not create another coupon issue.');
    $issuedRow = sr_coupon_claim_runtime_row(
        $pdo,
        'SELECT claim_type, claim_campaign_id, claim_log_id, nominal_price_amount, claim_snapshot_json FROM sr_coupon_issues WHERE id = :id',
        ['id' => $first['coupon_issue_id']]
    );
    $claimSnapshot = json_decode((string) ($issuedRow['claim_snapshot_json'] ?? ''), true);
    sr_coupon_claim_runtime_assert(($issuedRow['claim_type'] ?? '') === 'free', 'free campaign issue must freeze claim_type on the issue row.');
    sr_coupon_claim_runtime_assert((int) ($issuedRow['claim_campaign_id'] ?? 0) === $campaignId, 'free campaign issue must freeze claim campaign id.');
    sr_coupon_claim_runtime_assert((int) ($issuedRow['claim_log_id'] ?? 0) === (int) $first['claim_log_id'], 'free campaign issue must link the claim log.');
    sr_coupon_claim_runtime_assert((int) ($issuedRow['nominal_price_amount'] ?? -1) === 0, 'free campaign issue must freeze zero nominal price.');
    sr_coupon_claim_runtime_assert(is_array($claimSnapshot) && ($claimSnapshot['schema_version'] ?? '') === 'coupon_claim_snapshot_v1' && ($claimSnapshot['settlement_kind'] ?? '') === 'free', 'free campaign issue must store a claim snapshot.');

    try {
        sr_coupon_claim_free_campaign($pdo, 'claim_free', 7, 'intent-b');
        sr_coupon_claim_runtime_assert(false, 'per account limit one should reject a new intent from the same account.');
    } catch (InvalidArgumentException $exception) {
        sr_coupon_claim_runtime_assert(str_contains($exception->getMessage(), '이미'), 'per account limit failure should be user-facing.');
    }

    $second = sr_coupon_claim_free_campaign($pdo, 'claim_free', 8, 'intent-c');
    sr_coupon_claim_runtime_assert(!empty($second['claimed']), 'second account should claim remaining stock.');
    try {
        sr_coupon_claim_free_campaign($pdo, 'claim_free', 9, 'intent-d');
        sr_coupon_claim_runtime_assert(false, 'total claim limit should reject claims after stock is occupied.');
    } catch (InvalidArgumentException $exception) {
        sr_coupon_claim_runtime_assert(str_contains($exception->getMessage(), '모두'), 'stock failure should be user-facing.');
    }

    $pendingDefinitionId = sr_coupon_claim_runtime_definition($pdo, 'claim_pending');
    $pendingCampaignId = sr_coupon_create_claim_campaign($pdo, [
        'campaign_key' => 'claim_pending',
        'coupon_definition_id' => $pendingDefinitionId,
        'title' => 'Pending claim',
        'status' => 'active',
        'claim_type' => 'free',
        'total_claim_limit' => 1,
        'per_account_limit' => 1,
        'visibility' => 'public',
        'exposure_surfaces' => ['coupon_zone'],
        'login_required' => 1,
    ]);
    $expiredTime = (new DateTimeImmutable(sr_now()))->modify('-10 minutes')->format('Y-m-d H:i:s');
    $pdo->prepare(
        "INSERT INTO sr_coupon_claim_logs
            (campaign_id, coupon_definition_id, account_id, coupon_issue_id, claim_source, source_context_json, dedupe_key, dedupe_hash, occupying_account_id, status, reserved_until, failure_code, failure_message, created_at, issued_at, updated_at)
         VALUES
            (:campaign_id, :definition_id, 10, NULL, 'coupon_zone', '{}', 'old', :dedupe_hash, 10, 'pending_payment', :reserved_until, '', '', :created_at, NULL, :updated_at)"
    )->execute([
        'campaign_id' => $pendingCampaignId,
        'definition_id' => $pendingDefinitionId,
        'dedupe_hash' => sr_coupon_claim_dedupe_hash('old'),
        'reserved_until' => $expiredTime,
        'created_at' => $expiredTime,
        'updated_at' => $expiredTime,
    ]);
    $pending = sr_coupon_claim_free_campaign($pdo, 'claim_pending', 10, 'new-intent');
    sr_coupon_claim_runtime_assert(!empty($pending['claimed']), 'claim should self-expire old pending rows before applying per-account unique occupancy.');
    $expired = sr_coupon_claim_runtime_row($pdo, "SELECT status, occupying_account_id FROM sr_coupon_claim_logs WHERE dedupe_key = 'old'");
    sr_coupon_claim_runtime_assert((string) ($expired['status'] ?? '') === 'expired' && $expired['occupying_account_id'] === null, 'self-expired row should release occupying account id.');

    $lazyDefinitionId = sr_coupon_claim_runtime_definition($pdo, 'claim_lazy');
    $lazyCampaignId = sr_coupon_create_claim_campaign($pdo, [
        'campaign_key' => 'claim_lazy',
        'coupon_definition_id' => $lazyDefinitionId,
        'title' => 'Lazy claim',
        'status' => 'active',
        'claim_type' => 'free',
        'total_claim_limit' => 1,
        'per_account_limit' => 1,
        'visibility' => 'public',
        'exposure_surfaces' => ['coupon_zone'],
        'login_required' => 1,
    ]);
    $pdo->prepare(
        "INSERT INTO sr_coupon_claim_logs
            (campaign_id, coupon_definition_id, account_id, coupon_issue_id, claim_source, source_context_json, dedupe_key, dedupe_hash, occupying_account_id, status, reserved_until, failure_code, failure_message, created_at, issued_at, updated_at)
         VALUES
            (:campaign_id, :definition_id, 10, NULL, 'coupon_zone', '{}', 'lazy-old', :dedupe_hash, 10, 'reserved', :reserved_until, '', '', :created_at, NULL, :updated_at)"
    )->execute([
        'campaign_id' => $lazyCampaignId,
        'definition_id' => $lazyDefinitionId,
        'dedupe_hash' => sr_coupon_claim_dedupe_hash('lazy-old'),
        'reserved_until' => $expiredTime,
        'created_at' => $expiredTime,
        'updated_at' => $expiredTime,
    ]);
    $adminLogs = sr_coupon_admin_claim_logs($pdo, 20);
    $lazyLog = [];
    foreach ($adminLogs as $adminLog) {
        if ((string) ($adminLog['dedupe_key'] ?? '') === 'lazy-old') {
            $lazyLog = $adminLog;
            break;
        }
    }
    sr_coupon_claim_runtime_assert((string) ($lazyLog['display_status'] ?? '') === 'expired_unmaterialized', 'admin claim logs should show lazy-expired reservations as unmaterialized expiry.');

    $embedDefinitionId = sr_coupon_claim_runtime_definition($pdo, 'claim_embed');
    $embedCampaignId = sr_coupon_create_claim_campaign($pdo, [
        'campaign_key' => 'claim_embed',
        'coupon_definition_id' => $embedDefinitionId,
        'title' => 'Embed claim',
        'description' => 'Embedded coupon campaign',
        'status' => 'active',
        'claim_type' => 'free',
        'per_account_limit' => 1,
        'visibility' => 'public',
        'exposure_surfaces' => ['content_embed'],
        'login_required' => 1,
    ]);
    sr_coupon_claim_runtime_assert($embedCampaignId > 0, 'content embed campaign fixture should create campaign.');
    $contract = require SR_ROOT . '/modules/coupon/embed-manager-url-targets.php';
    $target = $contract['targets'][0] ?? [];
    $resolved = is_array($target) && is_callable($target['resolve_url'] ?? null)
        ? $target['resolve_url']($pdo, ['url' => '/coupons?campaign=claim_embed'])
        : null;
    sr_coupon_claim_runtime_assert(is_array($resolved) && (string) ($resolved['target_id'] ?? '') === (string) $embedCampaignId, 'coupon embed URL contract should resolve campaign detail URLs.');
    $rendered = is_array($resolved) && is_callable($target['render_embed'] ?? null)
        ? $target['render_embed']($pdo, $resolved, ['viewer_account_id' => 10])
        : [];
    $sanitized = sr_embed_manager_sanitize_rendered_fragment((string) ($rendered['html'] ?? ''));
    sr_coupon_claim_runtime_assert(str_contains($sanitized, 'data-coupon-embed="claim"'), 'coupon embed rendered fragment should keep coupon embed namespace.');
    sr_coupon_claim_runtime_assert(str_contains($sanitized, '/coupons?campaign=claim_embed'), 'coupon embed rendered fragment should link to the campaign detail page.');

    $editDefinitionId = sr_coupon_claim_runtime_definition($pdo, 'claim_edit');
    $editCampaignId = sr_coupon_create_claim_campaign($pdo, [
        'campaign_key' => 'claim_edit',
        'coupon_definition_id' => $editDefinitionId,
        'title' => 'Editable claim',
        'status' => 'active',
        'claim_type' => 'free',
        'total_claim_limit' => 5,
        'per_account_limit' => 2,
        'visibility' => 'public',
        'exposure_surfaces' => ['coupon_zone'],
        'login_required' => 1,
    ]);
    sr_coupon_update_claim_campaign($pdo, $editCampaignId, [
        'campaign_key' => 'claim_edit_renamed',
        'coupon_definition_id' => $editDefinitionId,
        'title' => 'Edited claim',
        'status' => 'active',
        'claim_type' => 'free',
        'total_claim_limit' => 3,
        'per_account_limit' => 2,
        'visibility' => 'public',
        'exposure_surfaces' => ['coupon_zone', 'popup_layer'],
        'login_required' => 1,
    ]);
    $edited = sr_coupon_claim_campaign_by_id($pdo, $editCampaignId);
    sr_coupon_claim_runtime_assert(is_array($edited) && (string) ($edited['campaign_key'] ?? '') === 'claim_edit_renamed', 'claim campaign edit should update key before claims exist.');
    sr_coupon_claim_runtime_assert(is_array($edited) && in_array('popup_layer', sr_coupon_claim_surfaces_from_value($edited['exposure_surfaces_json'] ?? ''), true), 'claim campaign edit should update exposure surfaces.');

    $paidDefinitionId = sr_coupon_claim_runtime_definition($pdo, 'claim_paid_policy');
    $paidCampaignId = sr_coupon_create_claim_campaign($pdo, [
        'campaign_key' => 'claim_paid_policy',
        'coupon_definition_id' => $paidDefinitionId,
        'title' => 'Paid policy',
        'status' => 'active',
        'claim_type' => 'paid',
        'price_amount' => '120',
        'price_currency_code' => 'krw',
        'allowed_asset_modules' => ['point'],
        'total_claim_limit' => 3,
        'per_account_limit' => 2,
        'visibility' => 'public',
        'exposure_surfaces' => ['coupon_zone'],
        'login_required' => 1,
    ]);
    $paidCampaign = sr_coupon_claim_campaign_by_id($pdo, $paidCampaignId);
    sr_coupon_claim_runtime_assert(is_array($paidCampaign) && (string) ($paidCampaign['claim_type'] ?? '') === 'paid', 'paid claim campaign should persist claim type.');
    sr_coupon_claim_runtime_assert((int) ($paidCampaign['price_amount'] ?? 0) === 120 && (string) ($paidCampaign['price_currency_code'] ?? '') === 'KRW', 'paid claim campaign should persist normalized price.');
    sr_coupon_claim_runtime_assert(in_array('point', sr_coupon_asset_module_keys_from_value($pdo, $paidCampaign['allowed_asset_modules_json'] ?? ''), true), 'paid claim campaign should persist allowed asset modules.');
    try {
        sr_coupon_create_claim_campaign($pdo, [
            'campaign_key' => 'claim_paid_currency_mismatch',
            'coupon_definition_id' => $paidDefinitionId,
            'title' => 'Paid currency mismatch',
            'status' => 'active',
            'claim_type' => 'paid',
            'price_amount' => '120',
            'price_currency_code' => 'USD',
            'allowed_asset_modules' => ['point'],
            'total_claim_limit' => 3,
            'per_account_limit' => 2,
            'visibility' => 'public',
            'exposure_surfaces' => ['coupon_zone'],
            'login_required' => 1,
        ]);
        sr_coupon_claim_runtime_assert(false, 'active paid claim campaign should reject assets that cannot settle in the price currency.');
    } catch (InvalidArgumentException $exception) {
        sr_coupon_claim_runtime_assert(str_contains($exception->getMessage(), '환산 통화') || str_contains($exception->getMessage(), '통화'), 'active paid claim purchase power validation should be user-facing.');
    }
    $now = sr_now();
    $pdo->prepare('INSERT INTO sr_point_balances (account_id, balance, created_at, updated_at) VALUES (7, 500, :created_at, :updated_at)')->execute([
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $claimLogsBeforeStrictFailure = (int) $pdo->query('SELECT COUNT(*) FROM sr_coupon_claim_logs')->fetchColumn();
    try {
        sr_coupon_claim_paid_campaign_with_asset($pdo, 'claim_paid_policy', 7, 'paid-intent-disallowed-asset', ['point', 'reward']);
        sr_coupon_claim_runtime_assert(false, 'paid claim should reject selected asset modules outside the campaign allowlist.');
    } catch (InvalidArgumentException $exception) {
        sr_coupon_claim_runtime_assert(str_contains($exception->getMessage(), '다시 선택'), 'paid claim disallowed selected asset failure should be user-facing.');
    }
    sr_coupon_claim_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_coupon_claim_logs')->fetchColumn() === $claimLogsBeforeStrictFailure, 'paid claim disallowed selected asset failure should not leave a durable claim log.');
    $paidClaim = sr_coupon_claim_paid_campaign_with_asset($pdo, 'claim_paid_policy', 7, 'paid-intent-a', ['point']);
    sr_coupon_claim_runtime_assert(!empty($paidClaim['claimed']) && (int) ($paidClaim['coupon_issue_id'] ?? 0) > 0, 'paid claim should issue a coupon.');
    $paidPointRow = sr_coupon_claim_runtime_row($pdo, 'SELECT amount, balance_after, reference_type, reference_id FROM sr_point_transactions WHERE account_id = 7 ORDER BY id DESC LIMIT 1');
    sr_coupon_claim_runtime_assert((int) ($paidPointRow['amount'] ?? 0) === -120 && (int) ($paidPointRow['balance_after'] ?? 0) === 380, 'paid claim should deduct the selected asset in the same flow.');
    sr_coupon_claim_runtime_assert((string) ($paidPointRow['reference_type'] ?? '') === 'coupon_claim' && (int) ($paidPointRow['reference_id'] ?? 0) === (int) ($paidClaim['claim_log_id'] ?? 0), 'paid claim asset transaction should reference claim log.');
    $paidAgain = sr_coupon_claim_paid_campaign_with_asset($pdo, 'claim_paid_policy', 7, 'paid-intent-a', ['point']);
    sr_coupon_claim_runtime_assert(!empty($paidAgain['already_claimed']) && (int) ($paidAgain['coupon_issue_id'] ?? 0) === (int) ($paidClaim['coupon_issue_id'] ?? 0), 'same paid nonce should converge to existing issue.');
    sr_coupon_claim_runtime_assert((int) $pdo->query("SELECT COUNT(*) FROM sr_point_transactions WHERE reference_type = 'coupon_claim'")->fetchColumn() === 1, 'same paid nonce must not deduct twice.');
    $paidClaimLog = sr_coupon_claim_runtime_row($pdo, 'SELECT payment_reference_module, payment_reference_type, payment_reference_id, asset_reference_module, asset_reference_type, asset_reference_id FROM sr_coupon_claim_logs WHERE id = :id', ['id' => $paidClaim['claim_log_id']]);
    sr_coupon_claim_runtime_assert((string) ($paidClaimLog['payment_reference_module'] ?? '') === '' && (string) ($paidClaimLog['payment_reference_type'] ?? '') === '' && (string) ($paidClaimLog['payment_reference_id'] ?? '') === '', 'paid claim log must not overload payment reference fields for asset deduction.');
    sr_coupon_claim_runtime_assert((string) ($paidClaimLog['asset_reference_module'] ?? '') === 'coupon' && (string) ($paidClaimLog['asset_reference_type'] ?? '') === 'paid_claim' && (int) ($paidClaimLog['asset_reference_id'] ?? 0) === (int) ($paidClaim['claim_log_id'] ?? 0), 'paid claim log should store asset reference fields.');
    $paidIssue = sr_coupon_claim_runtime_row($pdo, 'SELECT claim_type, nominal_price_amount, nominal_price_currency_code, claim_snapshot_json FROM sr_coupon_issues WHERE id = :id', ['id' => $paidClaim['coupon_issue_id']]);
    $paidSnapshot = json_decode((string) ($paidIssue['claim_snapshot_json'] ?? ''), true);
    sr_coupon_claim_runtime_assert((string) ($paidIssue['claim_type'] ?? '') === 'paid' && (int) ($paidIssue['nominal_price_amount'] ?? 0) === 120 && (string) ($paidIssue['nominal_price_currency_code'] ?? '') === 'KRW', 'paid claim issue should freeze nominal price.');
    $paidAllocations = is_array($paidSnapshot) ? (array) ($paidSnapshot['charged_allocations'] ?? []) : [];
    $paidAllocationSnapshot = is_array($paidAllocations[0]['purchase_power_snapshot'] ?? null) ? $paidAllocations[0]['purchase_power_snapshot'] : [];
    sr_coupon_claim_runtime_assert(is_array($paidSnapshot) && ($paidSnapshot['settlement_kind'] ?? '') === 'paid' && count($paidAllocations) === 1, 'paid claim issue should freeze charged allocation snapshot.');
    sr_coupon_claim_runtime_assert((string) ($paidSnapshot['snapshot_schema_version'] ?? '') === 'asset_settlement_snapshot_v1', 'paid claim issue snapshot should freeze the settlement snapshot schema version.');
    sr_coupon_claim_runtime_assert((string) ($paidSnapshot['rounding_policy_version'] ?? '') === 'asset_settlement_rounding_v1', 'paid claim issue snapshot should freeze the settlement rounding policy version.');
    sr_coupon_claim_runtime_assert((string) ($paidAllocationSnapshot['rounding_policy_version'] ?? '') === 'asset_settlement_rounding_v1' && (int) ($paidAllocationSnapshot['currency_min_unit'] ?? 0) > 0, 'paid claim charged allocation should include purchase power metadata from the settlement plan.');
    $refundResult = sr_coupon_refund_paid_issue_assets($pdo, (int) $paidClaim['coupon_issue_id'], 99, 'paid issue refund');
    sr_coupon_claim_runtime_assert(count($refundResult['refund_transactions'] ?? []) === 1, 'paid issue refund should create one asset refund transaction.');
    $paidRefundPointRow = sr_coupon_claim_runtime_row($pdo, 'SELECT amount, balance_after, transaction_type, reference_type, reference_id FROM sr_point_transactions WHERE account_id = 7 ORDER BY id DESC LIMIT 1');
    sr_coupon_claim_runtime_assert((int) ($paidRefundPointRow['amount'] ?? 0) === 120 && (int) ($paidRefundPointRow['balance_after'] ?? 0) === 500 && (string) ($paidRefundPointRow['transaction_type'] ?? '') === 'refund', 'paid issue refund should restore the deducted asset.');
    sr_coupon_claim_runtime_assert((string) ($paidRefundPointRow['reference_type'] ?? '') === 'refund' && str_starts_with((string) ($paidRefundPointRow['reference_id'] ?? ''), 'point_transaction:'), 'paid issue refund should reference the original asset transaction.');
    $refundedIssue = sr_coupon_claim_runtime_row($pdo, 'SELECT status FROM sr_coupon_issues WHERE id = :id', ['id' => $paidClaim['coupon_issue_id']]);
    $refundedClaimLog = sr_coupon_claim_runtime_row($pdo, 'SELECT status, occupying_account_id FROM sr_coupon_claim_logs WHERE id = :id', ['id' => $paidClaim['claim_log_id']]);
    sr_coupon_claim_runtime_assert((string) ($refundedIssue['status'] ?? '') === 'refunded', 'paid issue refund should mark the issue refunded.');
    sr_coupon_claim_runtime_assert((string) ($refundedClaimLog['status'] ?? '') === 'cancelled' && $refundedClaimLog['occupying_account_id'] === null, 'paid issue refund should release claim occupancy.');
    try {
        sr_coupon_refund_paid_issue_assets($pdo, (int) $paidClaim['coupon_issue_id'], 99, 'again');
        sr_coupon_claim_runtime_assert(false, 'paid issue refund should reject duplicate refunds.');
    } catch (InvalidArgumentException $exception) {
        sr_coupon_claim_runtime_assert(str_contains($exception->getMessage(), '이미'), 'paid issue duplicate refund failure should be user-facing.');
    }
    $paidReclaim = sr_coupon_claim_paid_campaign_with_asset($pdo, 'claim_paid_policy', 7, 'paid-intent-b', ['point']);
    sr_coupon_claim_runtime_assert(!empty($paidReclaim['claimed']) && (int) ($paidReclaim['coupon_issue_id'] ?? 0) !== (int) ($paidClaim['coupon_issue_id'] ?? 0), 'paid issue refund should allow a new paid claim with a new nonce after slot release.');
    $claimLogsBeforeFailure = (int) $pdo->query('SELECT COUNT(*) FROM sr_coupon_claim_logs')->fetchColumn();
    try {
        sr_coupon_claim_paid_campaign_with_asset($pdo, 'claim_paid_policy', 8, 'paid-insufficient', ['point']);
        sr_coupon_claim_runtime_assert(false, 'paid claim should fail before durable claim row when balance is insufficient.');
    } catch (InvalidArgumentException $exception) {
        sr_coupon_claim_runtime_assert(str_contains($exception->getMessage(), '잔액'), 'paid claim insufficient balance failure should be user-facing.');
    }
    sr_coupon_claim_runtime_assert((int) $pdo->query('SELECT COUNT(*) FROM sr_coupon_claim_logs')->fetchColumn() === $claimLogsBeforeFailure, 'paid pre-deduction failure should not leave a durable claim log.');
    try {
        sr_coupon_create_claim_campaign($pdo, [
            'campaign_key' => 'claim_paid_without_asset',
            'coupon_definition_id' => $paidDefinitionId,
            'title' => 'Paid without asset',
            'status' => 'draft',
            'claim_type' => 'paid',
            'price_amount' => '120',
            'price_currency_code' => 'KRW',
            'allowed_asset_modules' => [],
            'per_account_limit' => 1,
            'visibility' => 'hidden',
            'exposure_surfaces' => ['coupon_zone'],
            'login_required' => 1,
        ]);
        sr_coupon_claim_runtime_assert(false, 'paid claim campaign should require at least one allowed asset module.');
    } catch (InvalidArgumentException $exception) {
        sr_coupon_claim_runtime_assert(str_contains($exception->getMessage(), '포인트/금액 항목'), 'paid allowed asset validation should be user-facing.');
    }

    $editClaim = sr_coupon_claim_free_campaign($pdo, 'claim_edit_renamed', 10, 'edit-intent');
    sr_coupon_claim_runtime_assert(!empty($editClaim['claimed']), 'edited claim campaign should remain claimable.');
    try {
        sr_coupon_update_claim_campaign($pdo, $editCampaignId, [
            'campaign_key' => 'claim_edit_changed_after_log',
            'coupon_definition_id' => $editDefinitionId,
            'title' => 'Edited claim',
            'status' => 'active',
            'claim_type' => 'free',
            'total_claim_limit' => 3,
            'per_account_limit' => 2,
            'visibility' => 'public',
            'exposure_surfaces' => ['coupon_zone'],
            'login_required' => 1,
        ]);
        sr_coupon_claim_runtime_assert(false, 'claim campaign key should not change after claim logs exist.');
    } catch (InvalidArgumentException $exception) {
        sr_coupon_claim_runtime_assert(str_contains($exception->getMessage(), 'key'), 'claim campaign key immutability failure should be user-facing.');
    }
    try {
        sr_coupon_update_claim_campaign($pdo, $editCampaignId, [
            'campaign_key' => 'claim_edit_renamed',
            'coupon_definition_id' => $editDefinitionId,
            'title' => 'Edited claim',
            'status' => 'active',
            'claim_type' => 'free',
            'total_claim_limit' => '',
            'per_account_limit' => 0,
            'visibility' => 'public',
            'exposure_surfaces' => ['coupon_zone'],
            'login_required' => 1,
        ]);
        sr_coupon_claim_runtime_assert(false, 'claim campaign per-account limit should reject invalid numbers.');
    } catch (InvalidArgumentException $exception) {
        sr_coupon_claim_runtime_assert(str_contains($exception->getMessage(), '회원당 발급 한도'), 'per-account limit validation should be user-facing.');
    }
}

sr_coupon_claim_runtime_static_contract();
sr_coupon_claim_runtime_intent_token_fixture();
sr_coupon_claim_runtime_fixture();

if ($errors !== []) {
    fwrite(STDERR, "coupon claim campaign runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "coupon claim campaign runtime checks completed.\n";
