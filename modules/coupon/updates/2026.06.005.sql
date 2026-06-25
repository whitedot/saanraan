SET @schema_has_coupon_claim_logs_asset_reference_module = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_claim_logs'
      AND COLUMN_NAME = 'asset_reference_module'
);
SET @schema_coupon_claim_logs_asset_reference_module_sql = IF(
    @schema_has_coupon_claim_logs_asset_reference_module = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_claim_logs ADD COLUMN asset_reference_module VARCHAR(60) NOT NULL DEFAULT '''' AFTER payment_reference_id',
    'SELECT 1'
);
PREPARE stmt FROM @schema_coupon_claim_logs_asset_reference_module_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_coupon_claim_logs_asset_reference_type = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_claim_logs'
      AND COLUMN_NAME = 'asset_reference_type'
);
SET @schema_coupon_claim_logs_asset_reference_type_sql = IF(
    @schema_has_coupon_claim_logs_asset_reference_type = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_claim_logs ADD COLUMN asset_reference_type VARCHAR(80) NOT NULL DEFAULT '''' AFTER asset_reference_module',
    'SELECT 1'
);
PREPARE stmt FROM @schema_coupon_claim_logs_asset_reference_type_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_coupon_claim_logs_asset_reference_id = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_claim_logs'
      AND COLUMN_NAME = 'asset_reference_id'
);
SET @schema_coupon_claim_logs_asset_reference_id_sql = IF(
    @schema_has_coupon_claim_logs_asset_reference_id = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_claim_logs ADD COLUMN asset_reference_id VARCHAR(120) NOT NULL DEFAULT '''' AFTER asset_reference_type',
    'SELECT 1'
);
PREPARE stmt FROM @schema_coupon_claim_logs_asset_reference_id_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE {{SR_TABLE_PREFIX}}coupon_claim_logs
SET asset_reference_module = payment_reference_module,
    asset_reference_type = payment_reference_type,
    asset_reference_id = payment_reference_id,
    payment_reference_module = '',
    payment_reference_type = '',
    payment_reference_id = ''
WHERE payment_reference_module <> ''
  AND payment_reference_type = 'paid_claim'
  AND asset_reference_module = '';

UPDATE sr_modules
SET version = '2026.06.005',
    updated_at = NOW()
WHERE module_key = 'coupon';
