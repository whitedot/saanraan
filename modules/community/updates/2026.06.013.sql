CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}community_publisher_reward_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    charge_asset_log_id BIGINT UNSIGNED NOT NULL,
    charge_transaction_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reward_transaction_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reversal_transaction_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    post_id BIGINT UNSIGNED NOT NULL,
    attachment_id BIGINT UNSIGNED NOT NULL,
    downloader_account_id BIGINT UNSIGNED NOT NULL,
    publisher_account_id BIGINT UNSIGNED NOT NULL,
    asset_module VARCHAR(20) NOT NULL,
    charge_amount BIGINT NOT NULL DEFAULT 0,
    reward_rate INT UNSIGNED NOT NULL DEFAULT 0,
    reward_amount BIGINT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    dedupe_key VARCHAR(160) NOT NULL,
    failure_message TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_publisher_reward_dedupe (dedupe_key),
    KEY idx_sr_community_publisher_reward_publisher (publisher_account_id, created_at),
    KEY idx_sr_community_publisher_reward_downloader (downloader_account_id, created_at),
    KEY idx_sr_community_publisher_reward_attachment (attachment_id, created_at),
    KEY idx_sr_community_publisher_reward_charge_log (charge_asset_log_id),
    KEY idx_sr_community_publisher_reward_status (status, updated_at)
);

INSERT IGNORE INTO {{SR_TABLE_PREFIX}}admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id,
       '/admin/community/publisher-rewards',
       action_key,
       NOW()
FROM {{SR_TABLE_PREFIX}}admin_account_permissions
WHERE menu_path IN ('/admin/community/settings', '/admin/community/posts')
  AND action_key IN ('view', 'edit');

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.013',
    updated_at = NOW()
WHERE module_key = 'community';
