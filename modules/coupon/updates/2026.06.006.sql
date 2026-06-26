SET @schema_has_coupon_definitions_discount_amount = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_definitions'
      AND COLUMN_NAME = 'discount_amount'
);
SET @schema_coupon_definitions_discount_amount_sql = IF(
    @schema_has_coupon_definitions_discount_amount = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_definitions ADD COLUMN discount_amount BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER coupon_type',
    'SELECT 1'
);
PREPARE stmt FROM @schema_coupon_definitions_discount_amount_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_coupon_definitions_discount_percent = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_definitions'
      AND COLUMN_NAME = 'discount_percent'
);
SET @schema_coupon_definitions_discount_percent_sql = IF(
    @schema_has_coupon_definitions_discount_percent = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_definitions ADD COLUMN discount_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER discount_amount',
    'SELECT 1'
);
PREPARE stmt FROM @schema_coupon_definitions_discount_percent_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_coupon_definitions_discount_currency_code = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_definitions'
      AND COLUMN_NAME = 'discount_currency_code'
);
SET @schema_coupon_definitions_discount_currency_code_sql = IF(
    @schema_has_coupon_definitions_discount_currency_code = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_definitions ADD COLUMN discount_currency_code VARCHAR(3) NOT NULL DEFAULT '''' AFTER discount_percent',
    'SELECT 1'
);
PREPARE stmt FROM @schema_coupon_definitions_discount_currency_code_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.006',
    updated_at = NOW()
WHERE module_key = 'coupon';
