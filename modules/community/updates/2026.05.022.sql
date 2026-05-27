SET @schema_has_community_asset_logs_group_policy_snapshot = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_asset_logs'
      AND COLUMN_NAME = 'group_policy_snapshot_json'
);

SET @schema_sql = IF(
    @schema_has_community_asset_logs_group_policy_snapshot = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_asset_logs ADD COLUMN group_policy_snapshot_json TEXT NULL AFTER amount',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

CREATE TABLE IF NOT EXISTS sr_community_asset_policy_sets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    set_key VARCHAR(60) NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    policies_json TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_asset_policy_sets_key (set_key),
    KEY idx_sr_community_asset_policy_sets_status (status, title)
);

INSERT IGNORE INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id,
       '/admin/community/asset-policy-sets',
       action_key,
       NOW()
FROM sr_admin_account_permissions
WHERE menu_path = '/admin/community/settings';

UPDATE sr_modules
SET version = '2026.05.022',
    updated_at = NOW()
WHERE module_key = 'community';
