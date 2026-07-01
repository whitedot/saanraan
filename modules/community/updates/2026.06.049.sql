SET @schema_has_community_attachment_download_logs = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_attachment_download_logs'
);

SET @schema_has_community_attachment_download_logs_coupon_redemption_id = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_attachment_download_logs'
      AND COLUMN_NAME = 'coupon_redemption_id'
);

SET @sql = IF(
    @schema_has_community_attachment_download_logs = 1
      AND @schema_has_community_attachment_download_logs_coupon_redemption_id = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_attachment_download_logs ADD COLUMN coupon_redemption_id BIGINT UNSIGNED NULL AFTER asset_access_log_ids_json',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_community_attachment_download_logs_coupon_dedupe_key = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_attachment_download_logs'
      AND COLUMN_NAME = 'coupon_dedupe_key'
);

SET @sql = IF(
    @schema_has_community_attachment_download_logs = 1
      AND @schema_has_community_attachment_download_logs_coupon_dedupe_key = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_attachment_download_logs ADD COLUMN coupon_dedupe_key VARCHAR(160) NOT NULL DEFAULT '''' AFTER coupon_redemption_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_community_attachment_download_logs_refund_policy_version = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_attachment_download_logs'
      AND COLUMN_NAME = 'refund_policy_version'
);

SET @sql = IF(
    @schema_has_community_attachment_download_logs = 1
      AND @schema_has_community_attachment_download_logs_refund_policy_version = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_attachment_download_logs ADD COLUMN refund_policy_version VARCHAR(40) NOT NULL DEFAULT ''community_attachment_download_refund_v1'' AFTER access_revoked_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_community_attachment_downloads_coupon_index = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_attachment_download_logs'
      AND INDEX_NAME = 'idx_sr_community_attachment_downloads_coupon'
);

SET @sql = IF(
    @schema_has_community_attachment_download_logs = 1
      AND @schema_has_community_attachment_downloads_coupon_index = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_attachment_download_logs ADD INDEX idx_sr_community_attachment_downloads_coupon (coupon_redemption_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.049',
    updated_at = NOW()
WHERE module_key = 'community';
