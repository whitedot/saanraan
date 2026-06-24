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
    $pdo->exec("INSERT INTO sr_modules (module_key, status) VALUES ('coupon', 'enabled')");
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
}

sr_coupon_claim_runtime_fixture();

if ($errors !== []) {
    fwrite(STDERR, "coupon claim campaign runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "coupon claim campaign runtime checks completed.\n";
