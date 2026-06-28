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

function m10_ok(string $label): void
{
    global $checks;
    $checks[] = $label;
    echo '[ok] ' . $label . "\n";
}

function m10_fail(string $label, string $message): void
{
    global $errors;
    $errors[] = $label . ': ' . $message;
    fwrite(STDERR, '[fail] ' . $label . ': ' . $message . "\n");
}

function m10_assert(bool $condition, string $label, string $message = 'assertion failed'): void
{
    if ($condition) {
        m10_ok($label);
        return;
    }

    m10_fail($label, $message);
}

function m10_one(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function m10_value(PDO $pdo, string $sql, array $params = []): mixed
{
    $row = m10_one($pdo, $sql, $params);
    if ($row === null) {
        return null;
    }

    return reset($row);
}

function m10_exec(PDO $pdo, string $sql, array $params = []): void
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function m10_account(PDO $pdo, array $config, string $loginId, string $email, string $displayName): int
{
    return sr_member_create_account($pdo, $config, [
        'email' => $email,
        'login_id' => $loginId,
        'password' => 'SaanraanM10!',
        'display_name' => $displayName,
        'locale' => 'ko',
        'status' => 'active',
        'email_verified_at' => sr_now(),
        'allow_existing_update' => true,
    ]);
}

function m10_module_id(PDO $pdo, string $moduleKey): int
{
    return (int) m10_value($pdo, 'SELECT id FROM sr_modules WHERE module_key = :module_key LIMIT 1', ['module_key' => $moduleKey]);
}

function m10_upsert_module_setting(PDO $pdo, string $moduleKey, string $key, string $value, string $type = 'string'): void
{
    $moduleId = m10_module_id($pdo, $moduleKey);
    $now = sr_now();
    m10_exec(
        $pdo,
        'INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), updated_at = VALUES(updated_at)',
        [
            'module_id' => $moduleId,
            'setting_key' => $key,
            'setting_value' => $value,
            'value_type' => $type,
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    sr_clear_module_settings_cache($moduleKey);
}

function m10_upsert_site_setting(PDO $pdo, string $key, string $value, string $type = 'string'): void
{
    sr_save_site_settings($pdo, [$key => ['value' => $value, 'type' => $type]]);
}

try {
    $config = sr_load_config();
    sr_set_runtime_config($config);
    sr_apply_runtime_config($config);
    $pdo = sr_db($config);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $adminId = (int) m10_value($pdo, "SELECT account_id FROM sr_admin_account_roles WHERE role_key = 'owner' ORDER BY id ASC LIMIT 1");
    $writerId = m10_account($pdo, $config, 'm10_writer', 'm10-writer@example.test', 'M10작성자');
    $memberId = m10_account($pdo, $config, 'm10_member', 'm10-member@example.test', 'M10회원');
    $limitedAdminId = m10_account($pdo, $config, 'm10_limited_admin', 'm10-limited-admin@example.test', 'M10제한관리자');
    $cleanupId = m10_account($pdo, $config, 'm10_cleanup', 'm10-cleanup@example.test', 'M10정리대상');

    m10_assert($adminId > 0, '#132 owner role exists');

    $now = sr_now();

    m10_exec(
        $pdo,
        "INSERT INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at)
         VALUES (:account_id, '/admin/community/reports', 'view', :created_at)
         ON DUPLICATE KEY UPDATE created_at = VALUES(created_at)",
        ['account_id' => $limitedAdminId, 'created_at' => $now]
    );
    m10_assert(
        sr_admin_has_permission($pdo, $limitedAdminId, '/admin/community/reports', 'view')
        && !sr_admin_has_permission($pdo, $limitedAdminId, '/admin/community/reports', 'edit'),
        '#132 limited admin permission matrix'
    );

    $installedModules = (int) m10_value($pdo, "SELECT COUNT(*) FROM sr_modules WHERE status = 'enabled'");
    m10_assert($installedModules >= 17, '#131 all bundled modules enabled', 'enabled module count is ' . (string) $installedModules);
    m10_assert(sr_pending_schema_updates($pdo) === [], '#131 schema updates are idempotent');
    m10_assert(count(sr_schema_version_rows($pdo)) > 0, '#131 schema version rows recorded');

    m10_upsert_site_setting($pdo, 'site.name', 'Saanraan M10 Deep');
    m10_assert((string) (sr_site_settings($pdo)['site.name'] ?? '') === 'Saanraan M10 Deep', '#132 site setting save/read');
    sr_audit_log($pdo, [
        'actor_account_id' => $adminId,
        'actor_type' => 'admin',
        'event_type' => 'm10.audit',
        'target_type' => 'milestone',
        'target_id' => '10',
        'result' => 'success',
        'message' => 'Milestone 10 audit assertion.',
        'metadata' => ['fixture' => true],
    ]);
    m10_assert((int) m10_value($pdo, "SELECT COUNT(*) FROM sr_audit_logs WHERE event_type = 'm10.audit'") > 0, '#132 audit log records metadata');

    $resetToken = sr_member_create_password_reset($pdo, $config, $memberId);
    $verifyToken = sr_member_create_email_verification($pdo, $config, $memberId, 'm10-member@example.test');
    m10_assert($resetToken !== '' && $verifyToken !== '', '#133 member tokens created');
    m10_assert(
        (int) m10_value($pdo, 'SELECT COUNT(*) FROM sr_member_password_resets WHERE account_id = :id AND used_at IS NULL', ['id' => $memberId]) > 0
        && (int) m10_value($pdo, 'SELECT COUNT(*) FROM sr_member_email_verifications WHERE account_id = :id AND verified_at IS NULL', ['id' => $memberId]) > 0,
        '#133 member token hashes stored'
    );

    m10_exec(
        $pdo,
        'INSERT INTO sr_member_groups (group_key, title, description, status, is_system, sort_order, created_at, updated_at)
         VALUES (:group_key, :title, :description, :status, 0, 10, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE title = VALUES(title), status = VALUES(status), updated_at = VALUES(updated_at)',
        [
            'group_key' => 'm10_group',
            'title' => 'M10 그룹',
            'description' => 'Milestone 10 fixture group',
            'status' => 'enabled',
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    $groupId = (int) m10_value($pdo, "SELECT id FROM sr_member_groups WHERE group_key = 'm10_group'");
    m10_exec(
        $pdo,
        'INSERT INTO sr_member_group_memberships (group_id, account_id, assignment_type, source_module_key, source_rule_key, status, granted_at, expires_at, revoked_at, created_by_account_id, updated_at)
         VALUES (:group_id, :account_id, :assignment_type, :source_module_key, :source_rule_key, :status, :granted_at, NULL, NULL, :created_by_account_id, :updated_at)
         ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = VALUES(updated_at)',
        [
            'group_id' => $groupId,
            'account_id' => $memberId,
            'assignment_type' => 'manual',
            'source_module_key' => 'member',
            'source_rule_key' => 'm10',
            'status' => 'active',
            'granted_at' => $now,
            'created_by_account_id' => $adminId,
            'updated_at' => $now,
        ]
    );
    m10_assert(sr_member_account_in_any_group($pdo, $memberId, ['m10_group']), '#133 member group membership');

    $contentId = sr_content_save($pdo, [
        'content_group_id' => 0,
        'slug' => 'm10-content',
        'title' => 'M10 콘텐츠',
        'summary' => 'M10 summary',
        'body_text' => 'M10 body with [[community_post:1:compact]]',
        'body_format' => 'plain',
        'status' => 'published',
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
        'seo_title' => 'M10 SEO',
        'seo_description' => 'M10 SEO description',
    ], $adminId, (int) m10_value($pdo, "SELECT id FROM sr_content_items WHERE slug = 'm10-content' LIMIT 1"));
    $commentId = sr_content_create_comment($pdo, $contentId, $memberId, ['body_text' => '@M10 작성자 콘텐츠 댓글']);
    $seriesId = (int) m10_value($pdo, "SELECT id FROM sr_content_series WHERE series_key = 'm10_series' LIMIT 1");
    if ($seriesId < 1) {
        $seriesId = sr_content_create_series($pdo, [
            'series_key' => 'm10_series',
            'title' => 'M10 콘텐츠 시리즈',
            'description' => 'M10 series',
            'status' => 'active',
            'visibility' => 'public',
            'sort_order' => 10,
        ], $adminId);
    }
    sr_content_set_content_series($pdo, $contentId, $seriesId, '1화', 10, $adminId);
    m10_assert($contentId > 0 && $commentId > 0 && sr_content_series_for_content($pdo, $contentId, null, true) !== null, '#134 content CRUD/comment/series');

    $board = sr_community_board_by_key($pdo, 'free');
    $boardId = is_array($board) ? (int) $board['id'] : 0;
    m10_assert($boardId > 0, '#135 community default board exists');
    m10_exec(
        $pdo,
        'INSERT INTO sr_community_categories (board_id, category_key, title, description, status, sort_order, created_at, updated_at)
         VALUES (:board_id, :category_key, :title, :description, :status, 10, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE title = VALUES(title), status = VALUES(status), updated_at = VALUES(updated_at)',
        [
            'board_id' => $boardId,
            'category_key' => 'm10_category',
            'title' => 'M10 카테고리',
            'description' => 'M10 category',
            'status' => 'enabled',
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    m10_exec(
        $pdo,
        'INSERT INTO sr_community_series (board_id, owner_account_id, title, description, status, visibility, admin_note, created_by, updated_by, created_at, updated_at)
         VALUES (:board_id, :owner_account_id, :title, :description, :status, :visibility, :admin_note, :created_by, :updated_by, :created_at, :updated_at)',
        [
            'board_id' => $boardId,
            'owner_account_id' => $writerId,
            'title' => 'M10 커뮤니티 시리즈',
            'description' => 'M10 community series',
            'status' => 'active',
            'visibility' => 'public',
            'admin_note' => '',
            'created_by' => $writerId,
            'updated_by' => $writerId,
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    $communitySeriesId = (int) $pdo->lastInsertId();
    $postId = (int) m10_value($pdo, 'SELECT id FROM sr_community_posts ORDER BY id DESC LIMIT 1');
    if ($postId > 0) {
        m10_exec(
            $pdo,
            'INSERT INTO sr_community_series_items (series_id, post_id, active_post_id, episode_label, item_status, sort_order, created_by, created_at, updated_at)
             VALUES (:series_id, :post_id, :active_post_id, :episode_label, :item_status, 10, :created_by, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE item_status = VALUES(item_status), updated_at = VALUES(updated_at)',
            [
                'series_id' => $communitySeriesId,
                'post_id' => $postId,
                'active_post_id' => $postId,
                'episode_label' => '1화',
                'item_status' => 'active',
                'created_by' => $writerId,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
        m10_exec(
            $pdo,
            'INSERT INTO sr_community_series_scraps (account_id, series_id, created_at)
             VALUES (:account_id, :series_id, :created_at)',
            ['account_id' => $memberId, 'series_id' => $communitySeriesId, 'created_at' => $now]
        );
    }
    m10_assert($communitySeriesId > 0 && $postId > 0, '#135 community category/series/scrap fixture');

    m10_assert(str_contains(sr_editor_assets_html($pdo, 'ckeditor', 'default'), 'ckeditor'), '#136 CKEditor asset option renders');

    $pointBefore = sr_point_balance($pdo, $memberId);
    $pointTxId = sr_point_create_transaction($pdo, [
        'account_id' => $memberId,
        'amount' => 5000,
        'transaction_type' => 'adjustment',
        'reason' => 'M10 point grant',
        'reference_type' => 'm10',
        'reference_id' => 'point',
        'created_by_account_id' => $adminId,
    ]);
    $rewardTxId = sr_reward_create_transaction($pdo, [
        'account_id' => $memberId,
        'amount' => 5000,
        'transaction_type' => 'adjustment',
        'reason' => 'M10 reward grant',
        'reference_type' => 'm10',
        'reference_id' => 'reward',
        'created_by_account_id' => $adminId,
    ]);
    $depositTxId = sr_deposit_create_transaction($pdo, [
        'account_id' => $memberId,
        'amount' => 5000,
        'transaction_type' => 'adjustment',
        'reason' => 'M10 deposit grant',
        'reference_type' => 'm10',
        'reference_id' => 'deposit',
        'created_by_account_id' => $adminId,
    ]);
    sr_reward_save_settings($pdo, ['withdrawal_allowed_group_keys' => [sr_reward_withdrawal_all_members_key()]]);
    $withdrawalId = sr_reward_create_withdrawal_request($pdo, $memberId, [
        'amount' => 1000,
        'bank_name' => 'M10 Bank',
        'bank_account_number' => '123-456',
        'bank_account_holder' => 'M10 회원',
        'requester_note' => 'M10 withdrawal',
    ]);
    $refundId = sr_deposit_create_refund_request($pdo, $memberId, [
        'amount' => 1000,
        'bank_name' => 'M10 Bank',
        'bank_account_number' => '123-456',
        'bank_account_holder' => 'M10 회원',
        'requester_note' => 'M10 refund',
    ]);
    $existingPolicyId = (int) m10_value(
        $pdo,
        "SELECT id FROM sr_asset_exchange_policies WHERE from_module_key = 'point' AND to_module_key = 'deposit' ORDER BY id ASC LIMIT 1"
    );
    $policyId = sr_asset_exchange_save_policy($pdo, [
        'id' => $existingPolicyId,
        'from_module_key' => 'point',
        'to_module_key' => 'deposit',
        'status' => 'enabled',
        'rate_ratio' => '2:1',
        'min_amount' => '2',
        'max_amount' => '10000',
        'rounding_mode' => 'floor',
        'fee_trigger' => 'none',
        'fee_basis' => 'to_amount',
        'fee_type' => 'rate',
        'fee_rate_numerator' => '0',
        'fee_fixed_amount' => '0',
        'fee_min_amount' => '',
        'fee_max_amount' => '',
        'sort_order' => '10',
    ]);
    $policy = sr_asset_exchange_policy($pdo, $policyId);
    $exchangeLogId = is_array($policy) ? sr_asset_exchange_execute($pdo, $policy, $memberId, 100, $memberId) : 0;
    m10_assert(
        $pointTxId > 0 && $rewardTxId > 0 && $depositTxId > 0 && $withdrawalId > 0 && $refundId > 0 && $exchangeLogId > 0
        && sr_point_balance($pdo, $memberId) !== $pointBefore,
        '#137 assets ledger/withdrawal/refund/exchange'
    );

    $couponKey = 'm10_coupon_' . (string) $memberId . '_' . (string) $contentId;
    $couponDefinitionId = (int) m10_value($pdo, 'SELECT id FROM sr_coupon_definitions WHERE coupon_key = :coupon_key LIMIT 1', ['coupon_key' => $couponKey]);
    if ($couponDefinitionId < 1) {
        $couponDefinitionId = sr_coupon_create_definition($pdo, [
            'coupon_key' => $couponKey,
            'title' => 'M10 쿠폰',
            'description' => 'M10 coupon',
            'status' => 'active',
            'coupon_type' => 'access',
            'target_type' => 'content',
            'target_id' => (string) $contentId,
            'refundable_policy' => 'refundable',
            'max_uses_per_issue' => 1,
            'valid_from' => null,
            'valid_until' => null,
        ]);
    } else {
        m10_exec(
            $pdo,
            "UPDATE sr_coupon_definitions
             SET status = 'active',
                 target_type = 'content',
                 target_id = :target_id,
                 refundable_policy = 'refundable',
                 updated_at = :updated_at
             WHERE id = :id",
            ['target_id' => (string) $contentId, 'updated_at' => $now, 'id' => $couponDefinitionId]
        );
    }
    $couponIssueId = sr_coupon_issue_to_account($pdo, $couponDefinitionId, $memberId, 'M10 issue', $adminId);
    $dedupeKey = 'm10-content-' . (string) $memberId . '-' . (string) $contentId . '-' . (string) $couponIssueId;
    $redemption = sr_coupon_redeem_for_target($pdo, $memberId, 'content', (string) $contentId, [
        'reference_module' => 'content',
        'reference_type' => 'content_view',
        'reference_id' => (string) $contentId,
        'dedupe_key' => $dedupeKey,
    ]);
    $redemptionId = (int) m10_value($pdo, 'SELECT id FROM sr_coupon_redemptions WHERE dedupe_key = :dedupe_key LIMIT 1', ['dedupe_key' => $dedupeKey]);
    $refundResult = $redemptionId > 0 ? sr_coupon_refund_redemption($pdo, $redemptionId, $adminId, 'M10 refund') : ['error' => 'missing redemption'];
    m10_assert($couponIssueId > 0 && $redemptionId > 0 && empty($refundResult['error']), '#138 coupon issue/redeem/refund');

    m10_exec(
        $pdo,
        'INSERT INTO sr_site_menus (menu_key, label, status, created_at, updated_at)
         VALUES (:menu_key, :label, :status, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE label = VALUES(label), status = VALUES(status), updated_at = VALUES(updated_at)',
        ['menu_key' => 'm10_menu', 'label' => 'M10 메뉴', 'status' => 'enabled', 'created_at' => $now, 'updated_at' => $now]
    );
    $menuId = (int) m10_value($pdo, "SELECT id FROM sr_site_menus WHERE menu_key = 'm10_menu'");
    m10_exec(
        $pdo,
        'INSERT INTO sr_site_menu_items (menu_id, parent_id, label, url, target, status, sort_order, created_at, updated_at)
         VALUES (:menu_id, NULL, :label, :url, :target, :status, 10, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE label = VALUES(label), status = VALUES(status), updated_at = VALUES(updated_at)',
        ['menu_id' => $menuId, 'label' => 'M10 콘텐츠', 'url' => '/content/m10-content', 'target' => 'self', 'status' => 'enabled', 'created_at' => $now, 'updated_at' => $now]
    );
    m10_exec(
        $pdo,
        'INSERT INTO sr_banners (title, body_text, link_url, image_url, status, skin_key, starts_at, ends_at, sort_order, click_count, created_at, updated_at)
         VALUES (:title, :body_text, :link_url, :image_url, :status, :skin_key, NULL, NULL, 10, 0, :created_at, :updated_at)',
        ['title' => 'M10 배너', 'body_text' => 'M10 banner', 'link_url' => '/', 'image_url' => '/assets/module.css', 'status' => 'active', 'skin_key' => 'basic', 'created_at' => $now, 'updated_at' => $now]
    );
    $bannerId = (int) $pdo->lastInsertId();
    m10_exec(
        $pdo,
        'INSERT INTO sr_popup_layers (title, body_text, status, skin_key, starts_at, ends_at, dismiss_cookie_days, created_at, updated_at)
         VALUES (:title, :body_text, :status, :skin_key, NULL, NULL, 1, :created_at, :updated_at)',
        ['title' => 'M10 팝업', 'body_text' => 'M10 popup', 'status' => 'active', 'skin_key' => 'basic', 'created_at' => $now, 'updated_at' => $now]
    );
    $popupId = (int) $pdo->lastInsertId();
    m10_upsert_module_setting($pdo, 'seo', 'robots_txt', "User-agent: *\nAllow: /\n");
    m10_assert($menuId > 0 && $bannerId > 0 && $popupId > 0, '#139 site menu/banner/popup fixtures');

    $notificationId = sr_notification_create($pdo, [
        'account_id' => $memberId,
        'audience' => 'account',
        'title' => 'M10 알림',
        'body_text' => 'M10 notification',
        'body_format' => 'plain',
        'link_url' => '/account/notifications',
        'status' => 'active',
        'created_by_account_id' => $adminId,
        'channels' => ['site', 'email'],
    ]);
    m10_exec(
        $pdo,
        'INSERT INTO sr_privacy_requests (account_id, request_type, status, requester_email_hash, requester_snapshot, request_message, admin_note, handled_by_account_id, handled_at, created_at, updated_at)
         VALUES (:account_id, :request_type, :status, :requester_email_hash, :requester_snapshot, :request_message, :admin_note, :handled_by_account_id, :handled_at, :created_at, :updated_at)',
        [
            'account_id' => $memberId,
            'request_type' => 'export',
            'status' => 'completed',
            'requester_email_hash' => sr_hmac_hash('m10-member@example.test', $config),
            'requester_snapshot' => 'm10-member@example.test',
            'request_message' => 'M10 privacy request',
            'admin_note' => 'M10 handled',
            'handled_by_account_id' => $adminId,
            'handled_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    $privacyExport = sr_privacy_export_data($pdo, $memberId);
    $cleanupResults = sr_member_run_privacy_cleanup_contracts($pdo, $cleanupId, 'm10.cleanup');
    m10_assert(
        $notificationId > 0
        && isset($privacyExport['module_exports'])
        && is_array($privacyExport['module_exports'])
        && $cleanupResults !== [],
        '#140 notification/privacy export/cleanup'
    );

    m10_exec(
        $pdo,
        'INSERT INTO sr_rate_limits (rate_key, bucket, subject_hash, attempt_count, expires_at, created_at, updated_at)
         VALUES (:rate_key, :bucket, :subject_hash, :attempt_count, :expires_at, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE attempt_count = VALUES(attempt_count), updated_at = VALUES(updated_at)',
        [
            'rate_key' => hash('sha256', 'm10-rate'),
            'bucket' => 'm10',
            'subject_hash' => hash('sha256', 'member:' . (string) $memberId),
            'attempt_count' => 3,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    m10_assert((int) m10_value($pdo, "SELECT COUNT(*) FROM sr_rate_limits WHERE bucket = 'm10'") > 0, '#141 rate limit persistence');
} catch (Throwable $exception) {
    m10_fail('fatal', $exception::class . ' ' . $exception->getMessage());
}

if ($errors !== []) {
    fwrite(STDERR, "milestone 10 deep checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo 'milestone 10 deep checks completed: ' . count($checks) . " assertions.\n";
