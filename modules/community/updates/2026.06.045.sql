SET @schema_has_community_attachment_download_logs = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_attachment_download_logs'
);

SET @schema_has_community_attachment_download_logs_refund_status = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_attachment_download_logs'
      AND COLUMN_NAME = 'refund_status'
);

SET @sql = IF(
    @schema_has_community_attachment_download_logs = 1
      AND @schema_has_community_attachment_download_logs_refund_status = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_attachment_download_logs ADD COLUMN refund_status VARCHAR(20) NOT NULL DEFAULT '''' AFTER asset_access_log_ids_json',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_community_attachment_download_logs_refund_transaction_ids_json = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_attachment_download_logs'
      AND COLUMN_NAME = 'refund_transaction_ids_json'
);

SET @sql = IF(
    @schema_has_community_attachment_download_logs = 1
      AND @schema_has_community_attachment_download_logs_refund_transaction_ids_json = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_attachment_download_logs ADD COLUMN refund_transaction_ids_json TEXT NULL AFTER refund_status',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_community_attachment_download_logs_refund_note = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_attachment_download_logs'
      AND COLUMN_NAME = 'refund_note'
);

SET @sql = IF(
    @schema_has_community_attachment_download_logs = 1
      AND @schema_has_community_attachment_download_logs_refund_note = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_attachment_download_logs ADD COLUMN refund_note VARCHAR(255) NOT NULL DEFAULT '''' AFTER refund_transaction_ids_json',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_community_attachment_download_logs_refunded_by_account_id = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_attachment_download_logs'
      AND COLUMN_NAME = 'refunded_by_account_id'
);

SET @sql = IF(
    @schema_has_community_attachment_download_logs = 1
      AND @schema_has_community_attachment_download_logs_refunded_by_account_id = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_attachment_download_logs ADD COLUMN refunded_by_account_id BIGINT UNSIGNED NULL AFTER refund_note',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_community_attachment_download_logs_refunded_at = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_attachment_download_logs'
      AND COLUMN_NAME = 'refunded_at'
);

SET @sql = IF(
    @schema_has_community_attachment_download_logs = 1
      AND @schema_has_community_attachment_download_logs_refunded_at = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_attachment_download_logs ADD COLUMN refunded_at DATETIME NULL AFTER refunded_by_account_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_community_attachment_download_logs_access_revoked_at = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_attachment_download_logs'
      AND COLUMN_NAME = 'access_revoked_at'
);

SET @sql = IF(
    @schema_has_community_attachment_download_logs = 1
      AND @schema_has_community_attachment_download_logs_access_revoked_at = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_attachment_download_logs ADD COLUMN access_revoked_at DATETIME NULL AFTER refunded_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_community_attachment_downloads_refund_index = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_attachment_download_logs'
      AND INDEX_NAME = 'idx_sr_community_attachment_downloads_refund'
);

SET @sql = IF(
    @schema_has_community_attachment_download_logs = 1
      AND @schema_has_community_attachment_downloads_refund_index = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_attachment_download_logs ADD INDEX idx_sr_community_attachment_downloads_refund (refund_status, refunded_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.045',
    updated_at = NOW()
WHERE module_key = 'community';
