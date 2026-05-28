SET @schema_has_coupon_redemptions_refunded_at = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_redemptions'
      AND COLUMN_NAME = 'refunded_at'
);
SET @schema_sql = IF(
    @schema_has_coupon_redemptions_refunded_at = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_redemptions ADD COLUMN refunded_at DATETIME NULL AFTER redeemed_at',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_coupon_redemptions_refunded_by = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_redemptions'
      AND COLUMN_NAME = 'refunded_by_account_id'
);
SET @schema_sql = IF(
    @schema_has_coupon_redemptions_refunded_by = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_redemptions ADD COLUMN refunded_by_account_id BIGINT UNSIGNED NULL AFTER refunded_at',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_coupon_redemptions_refund_note = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}coupon_redemptions'
      AND COLUMN_NAME = 'refund_note'
);
SET @schema_sql = IF(
    @schema_has_coupon_redemptions_refund_note = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}coupon_redemptions ADD COLUMN refund_note VARCHAR(255) NOT NULL DEFAULT '''' AFTER refunded_by_account_id',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE sr_modules
SET version = '2026.05.005',
    updated_at = NOW()
WHERE module_key = 'coupon';
