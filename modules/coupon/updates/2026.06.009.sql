SET @schema_has_coupon_definitions_validity_policy = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_definitions'
      AND COLUMN_NAME = 'validity_policy'
);
SET @schema_coupon_definitions_validity_policy_sql = IF(
    @schema_has_coupon_definitions_validity_policy = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_definitions ADD COLUMN validity_policy VARCHAR(30) NOT NULL DEFAULT ''none'' AFTER max_uses_per_issue',
    'SELECT 1'
);
PREPARE stmt FROM @schema_coupon_definitions_validity_policy_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_coupon_definitions_validity_days = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_definitions'
      AND COLUMN_NAME = 'validity_days'
);
SET @schema_coupon_definitions_validity_days_sql = IF(
    @schema_has_coupon_definitions_validity_days = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_definitions ADD COLUMN validity_days INT UNSIGNED NULL AFTER validity_policy',
    'SELECT 1'
);
PREPARE stmt FROM @schema_coupon_definitions_validity_days_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_coupon_issues_starts_at = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_issues'
      AND COLUMN_NAME = 'starts_at'
);
SET @schema_coupon_issues_starts_at_sql = IF(
    @schema_has_coupon_issues_starts_at = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_issues ADD COLUMN starts_at DATETIME NULL AFTER issued_at',
    'SELECT 1'
);
PREPARE stmt FROM @schema_coupon_issues_starts_at_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_coupon_issues_start_index = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_issues'
      AND INDEX_NAME = 'idx_sr_coupon_issues_account_status'
      AND COLUMN_NAME = 'starts_at'
);
SET @schema_coupon_issues_start_index_sql = IF(
    @schema_has_coupon_issues_start_index = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_issues DROP INDEX idx_sr_coupon_issues_account_status, ADD INDEX idx_sr_coupon_issues_account_status (account_id, status, starts_at, expires_at, id)',
    'SELECT 1'
);
PREPARE stmt FROM @schema_coupon_issues_start_index_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.009',
    updated_at = NOW()
WHERE module_key = 'coupon';
