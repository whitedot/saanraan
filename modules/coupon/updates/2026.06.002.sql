SET @schema_has_coupon_redemptions_amount = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_redemptions'
      AND COLUMN_NAME = 'amount'
);
SET @schema_sql = IF(
    @schema_has_coupon_redemptions_amount = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_redemptions ADD COLUMN amount BIGINT NOT NULL DEFAULT 0 AFTER reference_id',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_coupon_redemptions_currency_code = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_redemptions'
      AND COLUMN_NAME = 'currency_code'
);
SET @schema_sql = IF(
    @schema_has_coupon_redemptions_currency_code = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_redemptions ADD COLUMN currency_code VARCHAR(3) NOT NULL DEFAULT '''' AFTER amount',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_coupon_redemptions_asset_unit = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_redemptions'
      AND COLUMN_NAME = 'asset_unit'
);
SET @schema_sql = IF(
    @schema_has_coupon_redemptions_asset_unit = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_redemptions ADD COLUMN asset_unit VARCHAR(40) NOT NULL DEFAULT '''' AFTER currency_code',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_coupon_redemptions_policy_summary = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_redemptions'
      AND COLUMN_NAME = 'policy_summary'
);
SET @schema_sql = IF(
    @schema_has_coupon_redemptions_policy_summary = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_redemptions ADD COLUMN policy_summary VARCHAR(255) NOT NULL DEFAULT '''' AFTER asset_unit',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_coupon_redemptions_priced_at = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_redemptions'
      AND COLUMN_NAME = 'priced_at'
);
SET @schema_sql = IF(
    @schema_has_coupon_redemptions_priced_at = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_redemptions ADD COLUMN priced_at DATETIME NULL AFTER policy_summary',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_coupon_redemptions_target_snapshot_json = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_redemptions'
      AND COLUMN_NAME = 'target_snapshot_json'
);
SET @schema_sql = IF(
    @schema_has_coupon_redemptions_target_snapshot_json = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_redemptions ADD COLUMN target_snapshot_json TEXT NULL AFTER priced_at',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE sr_modules
SET version = '2026.06.002',
    updated_at = NOW()
WHERE module_key = 'coupon';
