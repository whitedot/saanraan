#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));
chdir(SR_ROOT);

require_once SR_ROOT . '/core/helpers.php';
foreach ([
    'member',
    'admin',
    'privacy',
    'notification',
    'point',
    'reward',
    'deposit',
    'asset_exchange',
    'coupon',
    'content',
    'community',
    'site_menu',
    'banner',
    'popup_layer',
    'logo_manager',
    'seo',
    'ckeditor',
] as $moduleKey) {
    $helper = SR_ROOT . '/modules/' . $moduleKey . '/helpers.php';
    if (is_file($helper)) {
        require_once $helper;
    }
}

$errors = [];
$checks = [];

function m11_ok(string $label): void
{
    global $checks;
    $checks[] = $label;
    echo '[ok] ' . $label . "\n";
}

function m11_fail(string $label, string $message): void
{
    global $errors;
    $errors[] = $label . ': ' . $message;
    fwrite(STDERR, '[fail] ' . $label . ': ' . $message . "\n");
}

function m11_assert(bool $condition, string $label, string $message = 'assertion failed'): void
{
    if ($condition) {
        m11_ok($label);
        return;
    }

    m11_fail($label, $message);
}

function m11_one(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function m11_value(PDO $pdo, string $sql, array $params = []): mixed
{
    $row = m11_one($pdo, $sql, $params);
    return $row === null ? null : reset($row);
}

function m11_exec(PDO $pdo, string $sql, array $params = []): void
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function m11_table_exists(PDO $pdo, string $tableName): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $stmt->execute(['table_name' => $tableName]);
    return (int) $stmt->fetchColumn() > 0;
}

function m11_columns(PDO $pdo, string $tableName): array
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $stmt->execute(['table_name' => $tableName]);
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function m11_account(PDO $pdo, array $config, string $loginId, string $email, string $displayName): int
{
    return sr_member_create_account($pdo, $config, [
        'email' => $email,
        'login_id' => $loginId,
        'password' => 'SaanraanM11!',
        'display_name' => $displayName,
        'locale' => 'ko',
        'status' => 'active',
        'email_verified_at' => sr_now(),
        'allow_existing_update' => true,
    ]);
}

function m11_content_values(string $slug, string $title, string $bodyText, string $status = 'published', string $scheduledAt = ''): array
{
    return [
        'content_group_id' => 0,
        'slug' => $slug,
        'title' => $title,
        'summary' => $title . ' summary',
        'body_text' => $bodyText,
        'body_format' => 'html',
        'status' => $status,
        'scheduled_publish_at' => $scheduledAt,
        'layout_key' => '',
        'asset_access_enabled' => 0,
        'asset_module' => '',
        'asset_access_amount' => 0,
        'asset_access_amounts_json' => '{}',
        'asset_access_group_policies_json' => '',
        'asset_access_policy_set_id' => 0,
        'asset_charge_policy' => 'once',
        'asset_action_enabled' => 0,
        'asset_action_module' => '',
        'asset_action_amount' => 0,
        'asset_action_amounts_json' => '{}',
        'asset_action_group_policies_json' => '',
        'asset_action_policy_set_id' => 0,
        'asset_action_direction' => 'grant',
        'asset_action_label' => '완료',
        'banner_before_content_id' => 0,
        'banner_after_content_id' => 0,
        'popup_layer_id' => 0,
        'seo_title' => $title,
        'seo_description' => $title . ' description',
    ];
}

function m11_asset_ledger_is_balanced(PDO $pdo, string $assetKey): bool
{
    $balanceTable = 'sr_' . $assetKey . '_balances';
    $transactionTable = 'sr_' . $assetKey . '_transactions';
    $sql = sprintf(
        'SELECT COUNT(*)
         FROM %s b
         LEFT JOIN (
             SELECT account_id, COALESCE(SUM(amount), 0) AS transaction_total
             FROM %s
             GROUP BY account_id
         ) t ON t.account_id = b.account_id
         WHERE b.balance <> COALESCE(t.transaction_total, 0)',
        $balanceTable,
        $transactionTable
    );

    return (int) m11_value($pdo, $sql) === 0;
}

try {
    $config = sr_load_config();
    sr_set_runtime_config($config);
    sr_apply_runtime_config($config);
    $pdo = sr_db($config);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $now = sr_now();
    $site = sr_load_site($pdo);
    $adminId = (int) m11_value($pdo, "SELECT account_id FROM sr_admin_account_roles WHERE role_key = 'owner' ORDER BY id ASC LIMIT 1");
    $memberAId = m11_account($pdo, $config, 'm11_member_a', 'm11-member-a@example.test', 'M11회원A');
    $memberBId = m11_account($pdo, $config, 'm11_member_b', 'm11-member-b@example.test', 'M11회원B');
    $limitedAdminId = m11_account($pdo, $config, 'm11_limited_admin', 'm11-limited-admin@example.test', 'M11제한관리자');
    $cleanupId = m11_account($pdo, $config, 'm11_cleanup', 'm11-cleanup@example.test', 'M11정리대상');

    m11_assert($adminId > 0, '#142 consistency fixture has owner admin');

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $nonSrTables = array_filter(array_map('strval', $tables), static fn (string $table): bool => !str_starts_with($table, 'sr_'));
    m11_assert($nonSrTables === [], '#143 installed tables use sr_ namespace', implode(', ', $nonSrTables));
    m11_assert(sr_pending_schema_updates($pdo) === [], '#143 schema updater is idempotent');
    m11_assert(count(sr_schema_version_rows($pdo)) > 0, '#143 schema version rows are recorded');
    foreach ([
        'sr_modules' => ['module_key', 'status', 'installed_at', 'updated_at'],
        'sr_coupon_redemptions' => ['dedupe_key', 'coupon_issue_id', 'coupon_definition_id'],
        'sr_notification_deliveries' => ['notification_id', 'channel', 'status', 'error_message'],
        'sr_privacy_requests' => ['account_id', 'request_type', 'status', 'admin_note'],
    ] as $tableName => $requiredColumns) {
        $columns = m11_columns($pdo, $tableName);
        m11_assert(m11_table_exists($pdo, $tableName) && array_diff($requiredColumns, $columns) === [], '#143 schema columns exist: ' . $tableName);
    }

    m11_exec(
        $pdo,
        "INSERT INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at)
         VALUES (:account_id, '/admin/content', 'view', :created_at)
         ON DUPLICATE KEY UPDATE created_at = VALUES(created_at)",
        ['account_id' => $limitedAdminId, 'created_at' => $now]
    );
    m11_assert(
        sr_admin_has_permission($pdo, $limitedAdminId, '/admin/content', 'view')
        && !sr_admin_has_permission($pdo, $limitedAdminId, '/admin/content', 'edit')
        && !sr_admin_has_permission($pdo, $memberAId, '/admin/content', 'view'),
        '#144 admin permission boundary rejects overreach'
    );
    $hashA = sr_member_public_account_hash($config, $memberAId);
    $hashB = sr_member_public_account_hash($config, $memberBId);
    m11_assert(
        preg_match('/\A[a-f0-9]{32}\z/', $hashA) === 1 && $hashA !== $hashB,
        '#144 public account identifiers are hashed and stable'
    );

    $publishedId = sr_content_save($pdo, m11_content_values('m11-public', 'M11 공개', '<p>M11 public</p>', 'published'), $adminId, (int) m11_value($pdo, "SELECT id FROM sr_content_items WHERE slug = 'm11-public' LIMIT 1"));
    $hiddenId = sr_content_save($pdo, m11_content_values('m11-hidden', 'M11 숨김', '<p>M11 hidden</p>', 'hidden'), $adminId, (int) m11_value($pdo, "SELECT id FROM sr_content_items WHERE slug = 'm11-hidden' LIMIT 1"));
    $scheduledId = sr_content_save($pdo, m11_content_values('m11-scheduled', 'M11 예약', '<p>M11 scheduled</p>', 'scheduled', '2099-01-01 00:00:00'), $adminId, (int) m11_value($pdo, "SELECT id FROM sr_content_items WHERE slug = 'm11-scheduled' LIMIT 1"));
    $sitemapUrls = array_map(static fn (array $entry): string => (string) ($entry['loc'] ?? ''), sr_seo_sitemap_entries($pdo, $site));
    m11_assert(
        in_array('/content/m11-public', array_map(static fn (string $url): string => (string) parse_url($url, PHP_URL_PATH), $sitemapUrls), true)
        && !in_array('/content/m11-hidden', array_map(static fn (string $url): string => (string) parse_url($url, PHP_URL_PATH), $sitemapUrls), true)
        && !in_array('/content/m11-scheduled', array_map(static fn (string $url): string => (string) parse_url($url, PHP_URL_PATH), $sitemapUrls), true),
        '#145 public visibility and time conditions match sitemap'
    );
    m11_assert($publishedId > 0 && $hiddenId > 0 && $scheduledId > 0, '#145 status fixtures saved');

    $linkPageId = sr_content_save($pdo, m11_content_values('m11-url-embed', 'M11 URL 링크', '<p><a href="/content/m11-public">일반 링크</a></p>', 'published'), $adminId, (int) m11_value($pdo, "SELECT id FROM sr_content_items WHERE slug = 'm11-url-embed' LIMIT 1"));
    sr_content_save($pdo, m11_content_values('m11-url-embed', 'M11 URL 링크', '<p>URL 링크 저장 유지</p>', 'published'), $adminId, $linkPageId);
    m11_assert($linkPageId > 0, '#146 ordinary URL links save without legacy refs');

    foreach (['point', 'reward', 'deposit'] as $assetKey) {
        $function = 'sr_' . $assetKey . '_create_transaction';
        $function($pdo, [
            'account_id' => $memberAId,
            'amount' => 11,
            'transaction_type' => 'adjustment',
            'reason' => 'M11 consistency fixture',
            'reference_type' => 'milestone_11',
            'reference_id' => 'fixture-' . $assetKey . '-' . $memberAId,
            'created_by_account_id' => $adminId,
        ]);
        m11_assert(m11_asset_ledger_is_balanced($pdo, $assetKey), '#147 ' . $assetKey . ' ledger balance matches transactions');
    }

    $couponKey = 'm11_access_coupon';
    m11_exec(
        $pdo,
        'INSERT INTO sr_coupon_definitions (coupon_key, title, description, status, coupon_type, target_type, target_id, refundable_policy, max_uses_per_issue, valid_from, valid_until, created_at, updated_at)
         VALUES (:coupon_key, :title, :description, :status, :coupon_type, :target_type, :target_id, :refundable_policy, 1, NULL, NULL, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE status = VALUES(status), target_type = VALUES(target_type), target_id = VALUES(target_id), updated_at = VALUES(updated_at)',
        [
            'coupon_key' => $couponKey,
            'title' => 'M11 접근 쿠폰',
            'description' => 'Milestone 11 coupon fixture',
            'status' => 'active',
            'coupon_type' => 'access',
            'target_type' => 'content:item',
            'target_id' => (string) $publishedId,
            'refundable_policy' => 'none',
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    $couponDefinitionId = (int) m11_value($pdo, 'SELECT id FROM sr_coupon_definitions WHERE coupon_key = :coupon_key', ['coupon_key' => $couponKey]);
    m11_exec(
        $pdo,
        'INSERT INTO sr_coupon_issues (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, issued_at, expires_at, used_count, created_at, updated_at)
         VALUES (:coupon_definition_id, :account_id, :status, :issued_reason, :issued_by_account_id, :issued_at, NULL, 0, :created_at, :updated_at)',
        [
            'coupon_definition_id' => $couponDefinitionId,
            'account_id' => $memberAId,
            'status' => 'active',
            'issued_reason' => 'M11 fixture',
            'issued_by_account_id' => $adminId,
            'issued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    $dedupeKey = 'm11-content-' . $publishedId . '-' . $memberAId . '-' . str_replace('.', '', uniqid('', true));
    $firstRedeem = sr_coupon_redeem_for_target($pdo, $memberAId, 'content:item', (string) $publishedId, [
        'dedupe_key' => $dedupeKey,
        'reference_module' => 'content',
        'reference_type' => 'item',
        'reference_id' => (string) $publishedId,
    ]);
    $secondRedeem = sr_coupon_redeem_for_target($pdo, $memberAId, 'content:item', (string) $publishedId, ['dedupe_key' => $dedupeKey]);
    m11_assert(
        !empty($firstRedeem['allowed']) && !empty($firstRedeem['processed'])
        && !empty($secondRedeem['allowed']) && empty($secondRedeem['processed']) && !empty($secondRedeem['already_redeemed']),
        '#147 coupon access redemption is deduped'
    );

    sr_audit_log($pdo, [
        'actor_account_id' => $adminId,
        'actor_type' => 'admin',
        'event_type' => 'm11.consistency',
        'target_type' => 'milestone',
        'target_id' => '11',
        'result' => 'success',
        'message' => 'Milestone 11 consistency assertion.',
        'metadata' => ['issue' => 148, 'fixture' => true],
    ]);
    $notificationId = sr_notification_create($pdo, [
        'audience' => 'account',
        'account_id' => $memberAId,
        'title' => 'M11 알림',
        'body_text' => 'M11 notification',
        'body_format' => 'plain',
        'link_url' => '/content/m11-public',
        'channels' => ['site', 'email'],
        'created_by_account_id' => $adminId,
    ]);
    $deliveryCount = (int) m11_value($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :notification_id', ['notification_id' => $notificationId]);
    $leakedFixtureSecrets = (int) m11_value($pdo, "SELECT COUNT(*) FROM sr_audit_logs WHERE metadata_json LIKE '%SaanraanM11!%' OR metadata_json LIKE '%password_hash%' OR metadata_json LIKE '%_token_hash%'");
    m11_assert((int) m11_value($pdo, "SELECT COUNT(*) FROM sr_audit_logs WHERE event_type = 'm11.consistency'") > 0, '#148 audit log records consistency event');
    m11_assert($notificationId > 0 && $deliveryCount > 0, '#148 notification site/email delivery rows are isolated');
    m11_assert($leakedFixtureSecrets === 0, '#148 audit metadata scan excludes credentials and hashes');

    $privacyExport = sr_privacy_export_data($pdo, $memberAId);
    $exportedModules = array_keys(is_array($privacyExport['module_exports'] ?? null) ? $privacyExport['module_exports'] : []);
    $expectedExportModules = array_keys(sr_enabled_module_contract_files($pdo, 'privacy-export.php', ['privacy']));
    $cleanupResults = sr_member_run_privacy_cleanup_contracts($pdo, $cleanupId, 'erasure');
    m11_assert(array_diff($expectedExportModules, $exportedModules) === [], '#149 privacy export includes enabled module contracts');
    m11_assert($cleanupResults !== [] && m11_table_exists($pdo, 'sr_privacy_requests'), '#149 privacy cleanup contracts run without removing operational request table');

    $menuContractCount = 0;
    $unsafeMenuUrls = [];
    foreach (sr_enabled_module_contract_files($pdo, 'menu-links.php') as $moduleKey => $menuFile) {
        $links = sr_load_module_contract_file($moduleKey, $menuFile);
        if (is_callable($links)) {
            $links = $links($pdo, $site);
        }
        if (!is_array($links)) {
            continue;
        }
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $menuContractCount++;
            $url = (string) ($link['url'] ?? '');
            if ($url === '' || (!str_starts_with($url, '/') && !preg_match('/\Ahttps?:\/\//', $url))) {
                $unsafeMenuUrls[] = $moduleKey . ':' . $url;
            }
        }
    }
    $robots = sr_seo_robots_txt($site, sr_seo_settings($pdo));
    m11_assert($menuContractCount > 0 && $unsafeMenuUrls === [], '#150 menu candidates expose safe URLs');
    m11_assert(count($sitemapUrls) === count(array_unique($sitemapUrls)) && str_contains($robots, 'Sitemap:'), '#150 sitemap entries are unique and robots advertises sitemap');
    m11_exec(
        $pdo,
        'INSERT INTO sr_banners (title, body_text, link_url, image_url, status, skin_key, starts_at, ends_at, sort_order, created_at, updated_at)
         VALUES (:title, :body_text, :link_url, :image_url, :status, :skin_key, NULL, NULL, 111, :created_at, :updated_at)',
        [
            'title' => 'M11 배너 <script>alert(1)</script>',
            'body_text' => '<strong>M11 banner</strong>',
            'link_url' => '/content/m11-public',
            'image_url' => '',
            'status' => 'active',
            'skin_key' => 'basic',
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    m11_exec(
        $pdo,
        'INSERT INTO sr_popup_layers (title, body_text, status, skin_key, starts_at, ends_at, dismiss_cookie_days, created_at, updated_at)
         VALUES (:title, :body_text, :status, :skin_key, NULL, NULL, 1, :created_at, :updated_at)',
        [
            'title' => 'M11 팝업',
            'body_text' => '<p>M11 popup</p>',
            'status' => 'active',
            'skin_key' => 'basic',
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    m11_assert(
        (int) m11_value($pdo, "SELECT COUNT(*) FROM sr_banners WHERE status = 'active' AND (starts_at IS NULL OR starts_at <= :starts_now) AND (ends_at IS NULL OR ends_at >= :ends_now)", ['starts_now' => $now, 'ends_now' => $now]) > 0
        && (int) m11_value($pdo, "SELECT COUNT(*) FROM sr_popup_layers WHERE status = 'active' AND (starts_at IS NULL OR starts_at <= :starts_now) AND (ends_at IS NULL OR ends_at >= :ends_now)", ['starts_now' => $now, 'ends_now' => $now]) > 0
        && sr_e('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;',
        '#150 banner/popup active window and output escaping'
    );
} catch (Throwable $exception) {
    m11_fail('fatal', $exception->getMessage());
}

if ($errors !== []) {
    fwrite(STDERR, "\nMilestone 11 consistency checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "\nMilestone 11 consistency checks completed: " . count($checks) . " assertions.\n";
