SET @schema_has_content_items_asset_access_group_policies = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_items'
      AND COLUMN_NAME = 'asset_access_group_policies_json'
);
SET @schema_sql = IF(
    @schema_has_content_items_asset_access_group_policies = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_items ADD COLUMN asset_access_group_policies_json TEXT NULL AFTER asset_access_amounts_json',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_content_items_asset_access_policy_set = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_items'
      AND COLUMN_NAME = 'asset_access_policy_set_id'
);
SET @schema_sql = IF(
    @schema_has_content_items_asset_access_policy_set = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_items ADD COLUMN asset_access_policy_set_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER asset_access_group_policies_json',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_content_items_asset_action_group_policies = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_items'
      AND COLUMN_NAME = 'asset_action_group_policies_json'
);
SET @schema_sql = IF(
    @schema_has_content_items_asset_action_group_policies = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_items ADD COLUMN asset_action_group_policies_json TEXT NULL AFTER asset_action_amounts_json',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_content_items_asset_action_policy_set = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_items'
      AND COLUMN_NAME = 'asset_action_policy_set_id'
);
SET @schema_sql = IF(
    @schema_has_content_items_asset_action_policy_set = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_items ADD COLUMN asset_action_policy_set_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER asset_action_group_policies_json',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_content_revisions_asset_access_group_policies = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_revisions'
      AND COLUMN_NAME = 'asset_access_group_policies_json'
);
SET @schema_sql = IF(
    @schema_has_content_revisions_asset_access_group_policies = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_revisions ADD COLUMN asset_access_group_policies_json TEXT NULL AFTER asset_access_amounts_json',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_content_revisions_asset_access_policy_set = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_revisions'
      AND COLUMN_NAME = 'asset_access_policy_set_id'
);
SET @schema_sql = IF(
    @schema_has_content_revisions_asset_access_policy_set = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_revisions ADD COLUMN asset_access_policy_set_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER asset_access_group_policies_json',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_content_revisions_asset_action_group_policies = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_revisions'
      AND COLUMN_NAME = 'asset_action_group_policies_json'
);
SET @schema_sql = IF(
    @schema_has_content_revisions_asset_action_group_policies = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_revisions ADD COLUMN asset_action_group_policies_json TEXT NULL AFTER asset_action_amounts_json',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_content_revisions_asset_action_policy_set = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_revisions'
      AND COLUMN_NAME = 'asset_action_policy_set_id'
);
SET @schema_sql = IF(
    @schema_has_content_revisions_asset_action_policy_set = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_revisions ADD COLUMN asset_action_policy_set_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER asset_action_group_policies_json',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_content_files_asset_download_group_policies = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_files'
      AND COLUMN_NAME = 'asset_download_group_policies_json'
);
SET @schema_sql = IF(
    @schema_has_content_files_asset_download_group_policies = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_files ADD COLUMN asset_download_group_policies_json TEXT NULL AFTER asset_download_amounts_json',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_content_files_asset_download_policy_set = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_files'
      AND COLUMN_NAME = 'asset_download_policy_set_id'
);
SET @schema_sql = IF(
    @schema_has_content_files_asset_download_policy_set = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_files ADD COLUMN asset_download_policy_set_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER asset_download_group_policies_json',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_content_asset_access_logs_group_policy_snapshot = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_asset_access_logs'
      AND COLUMN_NAME = 'group_policy_snapshot_json'
);
SET @schema_sql = IF(
    @schema_has_content_asset_access_logs_group_policy_snapshot = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_asset_access_logs ADD COLUMN group_policy_snapshot_json TEXT NULL AFTER amount',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_content_asset_action_logs_group_policy_snapshot = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_asset_action_logs'
      AND COLUMN_NAME = 'group_policy_snapshot_json'
);
SET @schema_sql = IF(
    @schema_has_content_asset_action_logs_group_policy_snapshot = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_asset_action_logs ADD COLUMN group_policy_snapshot_json TEXT NULL AFTER amount',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

CREATE TABLE IF NOT EXISTS sr_content_asset_policy_sets (
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
    UNIQUE KEY uq_sr_content_asset_policy_sets_key (set_key),
    KEY idx_sr_content_asset_policy_sets_status (status, title)
);

INSERT IGNORE INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id,
       '/admin/content/asset-policy-sets',
       action_key,
       NOW()
FROM sr_admin_account_permissions
WHERE menu_path = '/admin/content/settings';

UPDATE sr_modules
SET version = '2026.05.013',
    updated_at = NOW()
WHERE module_key = 'content';
